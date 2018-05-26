<?php

function doBittrexCancelOrder($orderID)
{
	if(empty($orderID)) return false;

	$res = bittrex_api_query('market/cancel', "&uuid={$orderID}");
	if ($res && $res->success) {
		$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
			':market'=>'bittrex', ':uuid'=>$orderID
		));
		if ($db_order) $db_order->delete();
		return true;
	}
	return false;
}

function doBittrexTrading($quick=false)
{
	$exchange = 'bittrex';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	$balances = bittrex_api_query('account/getbalances');
	if(!$balances || !isset($balances->result) || !$balances->success) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");
	if (is_object($savebalance)) {
		$savebalance->balance = 0.;
		$savebalance->onsell = 0.;
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
			$coins = getdbolist('db_coins', "symbol=:sym OR symbol2=:sym", array(':sym'=>$balance->Currency));
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
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$flushall = rand(0, 8) == 0;
	if($quick) $flushall = false;

	// minimum order allowed by the exchange
	$min_btc_trade = exchange_get($exchange, 'trade_min_btc', 0.00100000);
	// sell on ask price + 5%
	$sell_ask_pct = exchange_get($exchange, 'trade_sell_ask_pct', 1.05);
	// cancel order if our price is more than ask price + 20%
	$cancel_ask_pct = exchange_get($exchange, 'trade_cancel_ask_pct', 1.20);

	sleep(1);
	$orders = bittrex_api_query('market/getopenorders');
	if(!$orders || !$orders->success) return;

	foreach($orders->result as $order)
	{
		// ignore buy orders
		if(stripos($order->OrderType, 'SELL') === false) continue;

		$pair = $order->Exchange;
		$pairs = explode("-", $pair);
		if ($pairs[0] != 'BTC') continue;
		$symbol = $pairs[1];

		$coin = getdbosql('db_coins', "symbol=:symbol OR symbol2=:symbol", array(':symbol'=>$symbol));
		if(!$coin || is_array($coin) || $coin->dontsell) continue;

		sleep(1);
		$ticker = bittrex_api_query('public/getticker', "&market=$pair");
		if(!$ticker || !$ticker->success || !$ticker->result) continue;

		$ask = bitcoinvaluetoa($ticker->result->Ask);
		$sellprice = bitcoinvaluetoa($order->Limit);

		// flush orders not on the ask
		if($sellprice > $ask*$cancel_ask_pct || $flushall)
		{
			debuglog("bittrex: cancel order {$order->Exchange} at $sellprice, ask price is now $ask");
			sleep(1);
			doBittrexCancelOrder($order->OrderUuid);
		}

		// import existing orders
		else
		{
			$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
				':market'=>'bittrex', ':uuid'=>$order->OrderUuid
			));
			if($db_order) continue;

			debuglog("bittrex: store new order of {$order->Quantity} {$coin->symbol} at $sellprice BTC");

			$db_order = new db_orders;
			$db_order->market = 'bittrex';
			$db_order->coinid = $coin->id;
			$db_order->amount = $order->Quantity;
			$db_order->price = $sellprice;
			$db_order->ask = $ticker->result->Ask;
			$db_order->bid = $ticker->result->Bid;
			$db_order->uuid = $order->OrderUuid;
			$db_order->created = time(); // Opened "2016-03-05T19:32:08.63"
			$db_order->save();
		}
	}

	// flush obsolete orders
	$list = getdbolist('db_orders', "market='bittrex'");
	foreach($list as $db_order)
	{
		$coin = getdbo('db_coins', $db_order->coinid);
		if(!$coin) continue;

		$found = false;
		foreach($orders->result as $order) {
			if(stripos($order->OrderType, 'SELL') === false) continue;

			if($order->OrderUuid == $db_order->uuid) {
				$found = true;
				break;
			}
		}

		if(!$found) {
			debuglog("bittrex deleting order $coin->name $db_order->amount");
			$db_order->delete();
		}
	}

	// create orders

	foreach($balances->result as $balance)
	{
		if($balance->Currency == 'BTC') continue;

		$amount = floatval($balance->Available);
		if(!$amount) continue;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$balance->Currency));
		if(!$coin || $coin->dontsell) continue;

		$market = getdbosql('db_markets', "coinid=$coin->id and name='bittrex'");
		if($market)
		{
			$market->lasttraded = time();
			$market->save();
		}

		if($amount*$coin->price < $min_btc_trade) continue;
		$pair = "BTC-$balance->Currency";

		sleep(1);
		$data = bittrex_api_query('public/getorderbook', "&market=$pair&type=buy&depth=10");
		if(!$data || !$data->success) continue;

		if($coin->sellonbid)
		for($i = 0; $i < 5 && $amount >= 0; $i++)
		{
			if(!isset($data->result->buy[$i])) break;

			$nextbuy = $data->result->buy[$i];
			if($amount*1.1 < $nextbuy->Quantity) break;

			$sellprice = bitcoinvaluetoa($nextbuy->Rate);
			$sellamount = min($amount, $nextbuy->Quantity);

			if($sellamount*$sellprice < $min_btc_trade) continue;

			debuglog("bittrex selling market $pair, $sellamount, $sellprice");
			sleep(1);
			$res = bittrex_api_query('market/selllimit', "&market=$pair&quantity=$sellamount&rate=$sellprice");
			if(!$res->success) {
				debuglog("bittrex err: ".json_encode($res));
				break;
			}

			$amount -= $sellamount;
		}

		if($amount <= 0) continue;

		sleep(1);
		$ticker = bittrex_api_query('public/getticker', "&market=$pair");
		if(!$ticker || !$ticker->success || !$ticker->result) continue;

		if($coin->sellonbid)
			$sellprice = bitcoinvaluetoa($ticker->result->Bid);
		else
			$sellprice = bitcoinvaluetoa($ticker->result->Ask * $sell_ask_pct);
		if($amount*$sellprice < $min_btc_trade) continue;

		debuglog("bittrex selling $pair, $amount, $sellprice");

		sleep(1);
		$res = bittrex_api_query('market/selllimit', "&market=$pair&quantity=$amount&rate=$sellprice");
		if(!$res || !$res->success) {
			debuglog("bittrex err: ".json_encode($res));
			continue;
		}

		$db_order = new db_orders;
		$db_order->market = 'bittrex';
		$db_order->coinid = $coin->id;
		$db_order->amount = $amount;
		$db_order->price = $sellprice;
		$db_order->ask = $ticker->result->Ask;
		$db_order->bid = $ticker->result->Bid;
		$db_order->uuid = $res->result->uuid;
		$db_order->created = time();
		$db_order->save();
	}

	$withdraw_min = exchange_get($exchange, 'withdraw_min_btc', EXCH_AUTO_WITHDRAW);
	$withdraw_fee = exchange_get($exchange, 'withdraw_fee_btc', 0.0005);
	if($withdraw_min > 0 && $savebalance->balance >= ($withdraw_min + $withdraw_fee))
	{
		// $btcaddr = exchange_get($exchange, 'withdraw_btc_address', YAAMP_BTCADDRESS);
		$btcaddr = YAAMP_BTCADDRESS;
		$amount = $savebalance->balance - $withdraw_fee;
		debuglog("bittrex withdraw $amount to $btcaddr");

		sleep(1);

		$res = bittrex_api_query('account/withdraw', "&currency=BTC&quantity=$amount&address=$btcaddr");
		debuglog("bittrex withdraw: ".json_encode($res));

		if($res && $res->success)
		{
			$withdraw = new db_withdraws;
			$withdraw->market = 'bittrex';
			$withdraw->address = $btcaddr;
			$withdraw->amount = $amount;
			$withdraw->time = time();
			$withdraw->uuid = $res->result->uuid;
			$withdraw->save();

			$savebalance->balance = 0;
			$savebalance->save();
		}
	}

//	debuglog('-------------- doBittrexTrading() done');
}
