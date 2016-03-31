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
//	debuglog("-------------- doCCexTrading() $flushall");

	$ccex = new CcexAPI;

	$savebalance = getdbosql('db_balances', "name='c-cex'");
	$savebalance->balance = 0;

	$balances = $ccex->getBalance();
	if(!$balances || !isset($balances['return'])) return;

	foreach($balances['return'] as $balance) foreach($balance as $symbol=>$amount)
	{
		if ($symbol == 'btc') {
			$savebalance->balance = $amount; // (available one)
			$savebalance->save();
			continue;
		}

		if (!YAAMP_ALLOW_EXCHANGE) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:sym OR symbol2=:sym", array(':sym'=>strtoupper($symbol)));
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='c-cex'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				if ($market->balance != $amount) {
					$market->balance = $amount;
					$market->save();
				}
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$flushall = rand(0, 4) == 0;
	if($quick) $flushall = false;

	$min_btc_trade = 0.00005000; // minimum allowed by the exchange
	$sell_ask_pct = 1.05;        // sell on ask price + 5%
	$cancel_ask_pct = 1.20;      // cancel order if our price is more than ask price + 20%

	// upgrade orders
	$coins = getdbolist('db_coins', "enable=1 AND IFNULL(dontsell,0)=0 AND id IN (SELECT DISTINCT coinid FROM markets WHERE name='c-cex')");
	foreach($coins as $coin)
	{
		if($coin->dontsell) continue;
		if($coin->symbol == 'BTC') continue;

		$market2 = getdbosql('db_markets', "coinid={$coin->id} AND (name='bittrex' OR name='poloniex')");
		if($market2) continue;

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
				//$ccex->cancelOrder($uuid);

				//$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
				//    ':market'=>'c-cex', ':uuid'=>$uuid
				//));
				//if($db_order) $db_order->delete();
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

	if(floatval(EXCH_AUTO_WITHDRAW) > 0 && $savebalance->balance >= (EXCH_AUTO_WITHDRAW + 0.0002))
	{
		$btcaddr = YAAMP_BTCADDRESS;
		$amount = $savebalance->balance - 0.0002;
		debuglog("[ccex] - withdraw $amount to $btcaddr");
		sleep(1);
		$res = $ccex->withdraw('BTC', $amount, $btcaddr);
		debuglog("[ccex] - withdraw: ".json_encode($res));
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






