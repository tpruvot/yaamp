<?php

function doCCexCancelOrder($OrderID=false, $ccex=false)
{
	if(!$OrderID) return;

	if(!$ccex) $ccex = new CcexAPI;

	$res = $ccex->cancelOrder($OrderID);
	if($res && !isset($res['error'])) {
		$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
			':market'=>'c-cex', ':uuid'=>$OrderID
		));
		if($db_order) $db_order->delete();
	}
}

function doCCexTrading($quick=false)
{
	$exchange = 'c-cex';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	$ccex = new CcexAPI;

	$savebalance = getdbosql('db_balances', "name='$exchange'");
	if (is_object($savebalance)) {
		$savebalance->balance = 0;
		$savebalance->save();
	}

	$balances = $ccex->getBalances();
	if(!$balances || !isset($balances['result'])) return;

	foreach($balances['result'] as $balance)
	{
		$symbol = strtoupper($balance['Currency']);
		if ($symbol == 'BTC') {
			if (!is_object($savebalance)) continue;
			$savebalance->balance = arraySafeVal($balance,'Available');
			$savebalance->onsell = arraySafeVal($balance,'Balance',0.) - arraySafeVal($balance,'Available');
			$savebalance->save();
			continue;
		}

		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:sym OR symbol2=:sym", array(':sym'=>$symbol));
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = arraySafeVal($balance,'Available',0.0);
				$market->ontrade = arraySafeVal($balance,'Balance') - $market->balance;
				$market->balancetime = time();
				$address = arraySafeVal($balance,'CryptoAddress');
				if (!empty($address) && $market->deposit_address != $address) {
					debuglog("$exchange: {$coin->symbol} deposit address updated");
					$market->deposit_address = $address;
				}
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$flushall = rand(0, 4) == 0;
	if($quick) $flushall = false;

	// minimum order allowed by the exchange
	$min_btc_trade = exchange_get($exchange, 'trade_min_btc', 0.00005000);
	// sell on ask price + 5%
	$sell_ask_pct = exchange_get($exchange, 'trade_sell_ask_pct', 1.05);
	// cancel order if our price is more than ask price + 20%
	$cancel_ask_pct = exchange_get($exchange, 'trade_cancel_ask_pct', 1.20);

	// upgrade orders
	$coins = getdbolist('db_coins', "enable=1 AND IFNULL(dontsell,0)=0 AND id IN (SELECT DISTINCT coinid FROM markets WHERE name='c-cex')");
	foreach($coins as $coin)
	{
		if($coin->dontsell) continue;
		if($coin->symbol == 'BTC') continue;

		$pair = strtolower($coin->symbol).'-btc';

		sleep(1);
		$orders = $ccex->getOrders($pair, 1);
		if(!$orders || isset($orders['error'])) continue;

		foreach($orders['return'] as $uuid => $order)
		{
			sleep(1);
			$ticker = $ccex->getTickerInfo($pair);
			if(!$ticker) continue;

			if($order['price'] > $cancel_ask_pct*$ticker['sell'] || $flushall)
			{
				// debuglog("c-cex: cancel order for $pair $uuid");
				sleep(1);
				doCCexCancelOrder($uuid, $ccex);
			}

			else
			{
				$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
					':market'=>'c-cex', ':uuid'=>$uuid
				));
				if($db_order) continue;

				// debuglog("c-cex: import order $coin->symbol");
				$db_order = new db_orders;
				$db_order->market = 'c-cex';
				$db_order->coinid = $coin->id;
				$db_order->amount = $order['amount'];
				$db_order->price = $order['price'];
				$db_order->ask = $ticker['sell'];
				$db_order->bid = $ticker['buy'];
				$db_order->uuid = $uuid;
				$db_order->created = time();
				$db_order->save();
			}
		}

		$list = getdbolist('db_orders', "coinid=$coin->id and market='c-cex'");
		foreach($list as $db_order)
		{
			$found = false;
			foreach($orders['return'] as $uuid=>$order)
			{
				if($uuid == $db_order->uuid)
				{
					$found = true;
					break;
				}
			}

			if(!$found)
			{
				debuglog("c-cex deleting order $coin->name $db_order->amount");
				$db_order->delete();
			}
		}
	}

	sleep(2);

	//////////////////////////////////////////////////////////////////////////////////////////////////

	$balances = $ccex->getBalance(); // old api
	if(!$balances || !isset($balances['return'])) return;

	foreach($balances['return'] as $balance) foreach($balance as $symbol=>$amount)
	{
		if(!$amount) continue;
		if($symbol == 'btc') continue;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if(!$coin || $coin->dontsell) continue;

		$market2 = getdbosql('db_markets', "coinid={$coin->id} AND (name='bittrex' OR name='poloniex')");
		if($market2) continue;

		$market = getdbosql('db_markets', "coinid=$coin->id and name='c-cex'");
		if($market)
		{
			$market->lasttraded = time();
			$market->save();
		}

		if($amount*$coin->price < $min_btc_trade) continue;
		$pair = "$symbol-btc";

		////////////////////////

		$maxprice = 0;
		$maxamount = 0;

//		debuglog("c-cex list order for $pair all");
		sleep(1);
		$orders = $ccex->getOrders($pair, 0);

		if(!empty($orders) && !empty($orders['return']))
		foreach($orders['return'] as $order)
		{
			if($order['type'] == 'sell') continue;
			if($order['price'] > $maxprice)
			{
				$maxprice = $order['price'];
				$maxamount = $order['amount'];
			}
		}

	//	debuglog("maxbuy for $pair $maxamount $maxprice");
		if($amount >= $maxamount && $maxamount*$maxprice > $min_btc_trade)
		{
			$sellprice = bitcoinvaluetoa($maxprice);

			debuglog("c-cex selling market $pair, $maxamount, $sellprice");

			sleep(1);
			$res = $ccex->makeOrder('sell', $pair, $maxamount, $sellprice);
			if(!$res || !isset($res['return']))
				debuglog($res);
			else
				$amount -= $maxamount;

			sleep(1);
		}

		sleep(1);
		$ticker = $ccex->getTickerInfo($pair);
		if(!$ticker) continue;

		$sellprice = bitcoinvaluetoa($ticker['sell']);

//		debuglog("c-cex selling $pair, $amount, $sellprice");
		sleep(1);
		$res = $ccex->makeOrder('sell', $pair, $amount, $sellprice);
		if(!$res || !isset($res['return'])) continue;

		$db_order = new db_orders;
		$db_order->market = 'c-cex';
		$db_order->coinid = $coin->id;
		$db_order->amount = $amount;
		$db_order->price = $sellprice;
		$db_order->ask = $ticker['sell'];
		$db_order->bid = $ticker['buy'];
		$db_order->uuid = $res['return'];
		$db_order->created = time();
		$db_order->save();
	}

	$withdraw_min = exchange_get($exchange, 'withdraw_min_btc', EXCH_AUTO_WITHDRAW);
	$withdraw_fee = exchange_get($exchange, 'withdraw_fee_btc', 0.0002);

	if(floatval($withdraw_min) > 0 && $savebalance->balance >= ($withdraw_min + $withdraw_fee))
	{
		// $btcaddr = exchange_get($exchange, 'withdraw_btc_address', YAAMP_BTCADDRESS);
		$btcaddr = YAAMP_BTCADDRESS;

		$amount = $savebalance->balance - $withdraw_fee;
		debuglog("$exchange: withdraw $amount BTC to $btcaddr");

		sleep(1);
		$res = $ccex->withdraw('BTC', $amount, $btcaddr);
		debuglog("$exchange: withdraw ".json_encode($res));
		if(isset($res['return']))
		{
			$withdraw = new db_withdraws;
			$withdraw->market = 'c-cex';
			$withdraw->address = $btcaddr;
			$withdraw->amount = $amount;
			$withdraw->time = time();
			$withdraw->uuid = $res['return'];
			$withdraw->save();

			$savebalance->balance = 0;
			$savebalance->save();
		}
	}
//	debuglog('-------------- doCCexTrading() done');
}






