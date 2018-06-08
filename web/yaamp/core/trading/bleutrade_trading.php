<?php

function doBleutradeCancelOrder($orderID)
{
	if(empty($orderID)) return false;

	$res = bleutrade_api_query('market/cancel', "&orderid={$orderID}");
	if($res->success) {
		$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
			':market'=>'bleutrade', ':uuid'=>$orderID
		));
		if($db_order) $db_order->delete();
		return true;
	}
	return false;
}

function doBleutradeTrading($quick=false)
{
	$exchange = 'bleutrade';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	$balances = bleutrade_api_query('account/getbalances');
	if(!$balances || !isset($balances->result) || !$balances->success) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");
	if (is_object($savebalance)) {
		$savebalance->balance = 0;
		$savebalance->onsell = 0;
		$savebalance->save();
	}

	foreach($balances->result as $balance)
	{
		if ($balance->Currency == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance->Available;
				$savebalance->onsell = (double) $balance->Balance - (double) $balance->Available;
				$savebalance->save();
			}
			continue;
		}
		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>$balance->Currency)
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $balance->Available;
				$market->ontrade = $balance->Balance - $balance->Available;
				if (!empty($balance->CryptoAddress) && $market->deposit_address != $balance->CryptoAddress) {
					debuglog("$exchange: {$coin->symbol} deposit address updated");
					$market->deposit_address = $balance->CryptoAddress;
				}
				if ($market->disabled < 9 && property_exists($balance,'IsActive')) {
					// disabled = 9 means permanent disable by admin
					$market->disabled = (int) ($balance->IsActive != "true");
				}
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$flushall = rand(0, 8) == 0;
	if($quick) $flushall = false;

	// minimum order allowed by the exchange
	$min_btc_trade = exchange_get($exchange, 'trade_min_btc', 0.00050000);
	// sell on ask price + 5%
	$sell_ask_pct = exchange_get($exchange, 'trade_sell_ask_pct', 1.05);
	// cancel order if our price is more than ask price + 20%
	$cancel_ask_pct = exchange_get($exchange, 'trade_cancel_ask_pct', 1.20);

	sleep(1);
	$orders = bleutrade_api_query('market/getopenorders');
	if(!$orders) return;

	foreach($orders->result as $order)
	{
		$e = explode('_', $order->Exchange);
		$symbol = $e[0];		/// "Exchange" : "LTC_BTC",
		$pair = $order->Exchange;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if(!$coin) continue;
		if($coin->dontsell) continue;

		sleep(1);
		$ticker = bleutrade_api_query('public/getticker', "&market=$pair");
		if(!$ticker || !$ticker->success || !isset($ticker->result[0])) continue;

		$ask = bitcoinvaluetoa($ticker->result[0]->Ask);
		$sellprice = bitcoinvaluetoa($order->Price);

		// flush orders not on the ask
		if($sellprice > $ask*$cancel_ask_pct || $flushall)
		{
//			debuglog("bleutrade: cancel order $order->Exchange $sellprice -> $ask");
			sleep(1);
			doBleutradeCancelOrder($order->OrderId);
		}

		// save existing orders
		else
		{
			$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
				':market'=>'bleutrade', ':uuid'=>$order->OrderId
			));
			if($db_order) continue;

			// debuglog("bleutrade: adding order $coin->symbol");
			$db_order = new db_orders;
			$db_order->market = 'bleutrade';
			$db_order->coinid = $coin->id;
			$db_order->amount = $order->Quantity;
			$db_order->price = $sellprice;
			$db_order->ask = $ticker->result[0]->Ask;
			$db_order->bid = $ticker->result[0]->Bid;
			$db_order->uuid = $order->OrderId;
			$db_order->created = time();
			$db_order->save();
		}
	}

	// flush obsolete orders
	$list = getdbolist('db_orders', "market='bleutrade'");
	foreach($list as $db_order)
	{
		$coin = getdbo('db_coins', $db_order->coinid);
		if(!$coin) continue;

		$found = false;
		foreach($orders->result as $order)
			if($order->OrderId == $db_order->uuid)
			{
				$found = true;
				break;
			}

		if(!$found)
		{
			debuglog("bleutrade deleting order $coin->name $db_order->amount");
			$db_order->delete();
		}
	}

// 	if($flushall)
// 	{
//		debuglog("bleutrade flushall got here");
// 		return;
// 	}

	// add orders

	foreach($balances->result as $balance)
	{
		if($balance->Currency == 'BTC')	continue;

		$amount = floatval($balance->Available);
		if(!$amount) continue;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$balance->Currency));
		if(!$coin || $coin->dontsell) continue;

		$market = getdbosql('db_markets', "coinid=$coin->id and name='bleutrade'");
		if($market)
		{
			$market->lasttraded = time();
			$market->save();
		}

		if($amount*$coin->price < $min_btc_trade) continue;
		$pair = "{$balance->Currency}_BTC";

		sleep(1);
		$data = bleutrade_api_query('public/getorderbook', "&market=$pair&type=BUY&depth=10");
		if(!$data) continue;
	//	if(!isset($data->result[0])) continue;

		if($coin->sellonbid)
		for($i = 0; $i < 5 && $amount >= 0; $i++)
		{
			if(!isset($data->result->buy[$i])) break;

			$nextbuy = $data->result->buy[$i];
			if($amount*1.1 < $nextbuy->Quantity) break;

			$sellprice = bitcoinvaluetoa($nextbuy->Rate);
			$sellamount = min($amount, $nextbuy->Quantity);

			if($sellamount*$sellprice < $min_btc_trade) continue;

			$sellprice = bitcoinvaluetoa($nextbuy->Rate);

//			debuglog("bleutrade selling market $pair, $nextbuy->Quantity, $sellprice");
			sleep(1);
			$res = bleutrade_api_query('market/selllimit', "&market=$pair&quantity=$nextbuy->Quantity&rate=$sellprice");
			if(!$res->success) {
				debuglog("bleutrade err: ".json_encode($res));
				break;
			}

			$amount -= $sellamount;
		}

		if($amount <= 0) continue;

		sleep(1);
		$ticker = bleutrade_api_query('public/getticker', "&market=$pair");
		if(!$ticker || !$ticker->success || !isset($ticker->result[0])) continue;

		if($coin->sellonbid)
			$sellprice = bitcoinvaluetoa($ticker->result[0]->Bid);
		else
			$sellprice = bitcoinvaluetoa($ticker->result[0]->Ask * $sell_ask_pct);
		if($amount*$sellprice < $min_btc_trade) continue;

		debuglog("bleutrade: selling $pair, $amount at $sellprice");

		sleep(1);
		$res = bleutrade_api_query('market/selllimit', "&market=$pair&quantity=$amount&rate=$sellprice");
		if(!$res || !$res->success || !isset($res->result)) {
			debuglog("bleutrade err: ".json_encode($res));
			continue;
		}

		$db_order = new db_orders;
		$db_order->market = 'bleutrade';
		$db_order->coinid = $coin->id;
		$db_order->amount = $amount;
		$db_order->price = $sellprice;
		$db_order->ask = $ticker->result[0]->Ask;
		$db_order->bid = $ticker->result[0]->Bid;
		$db_order->uuid = $res->result->orderid;
		$db_order->created = time();
		$db_order->save();
	}

	$withdraw_min = exchange_get($exchange, 'withdraw_min_btc', EXCH_AUTO_WITHDRAW);
	$withdraw_fee = exchange_get($exchange, 'withdraw_fee_btc', 0.001);

	if(floatval($withdraw_min) > 0 && $savebalance->balance >= ($withdraw_min + $withdraw_fee))
	{
		// $btcaddr = exchange_get($exchange, 'withdraw_btc_address', YAAMP_BTCADDRESS);
		$btcaddr = YAAMP_BTCADDRESS;

		$amount = $savebalance->balance - $withdraw_fee;
		debuglog("bleutrade: withdraw $amount BTC to $btcaddr");

		sleep(1);
		$res = bleutrade_api_query('account/withdraw', "&currency=BTC&quantity=$amount&address=$btcaddr");
		debuglog("bleutrade: withdraw ".json_encode($res));

		if($res && $res->success)
		{
			$withdraw = new db_withdraws;
			$withdraw->market = 'bleutrade';
			$withdraw->address = $btcaddr;
			$withdraw->amount = $amount;
			$withdraw->time = time();
			$withdraw->save();

			$savebalance->balance = 0;
			$savebalance->save();
		}
	}

	//	debuglog('-------------- dobleutradeTrading() done');
}






