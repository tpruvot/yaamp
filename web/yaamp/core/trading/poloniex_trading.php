<?php

function doPoloniexCancelOrder($OrderID=false, $pair=false, $poloniex=false)
{
	if(!$OrderID) return;
	if(!$pair) return;

	if(!$poloniex) $poloniex = new CcexAPI;

	$res = $poloniex->cancel_order($pair,$OrderID);
	if($res && $res['success'])
		{
		$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
			':market'=>'poloniex', ':uuid'=>$OrderID
		));
		if($db_order) $db_order->delete();
	}
}

function doPoloniexTrading()
{
	$exchange = 'poloniex';
	$updatebalances = true;

	$poloniex = new poloniex;

	// add orders
	$savebalance = getdbosql('db_balances', "name='$exchange'");
	$balances = $poloniex->get_complete_balances();

	if (is_array($balances))
	foreach($balances as $symbol => $balance)
	{
		if ($symbol == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = arraySafeVal($balance,'available');
				$savebalance->onsell = arraySafeVal($balance,'onOrders');
				$savebalance->save();
			}
			continue;
		}

		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>$symbol)
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = arraySafeVal($balance,'available',0);
				$market->ontrade = arraySafeVal($balance,'onOrders',0);
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$flushall = rand(0, 8) == 0;

	// minimum order allowed by the exchange
	$min_btc_trade = exchange_get($exchange, 'trade_min_btc', 0.00010000);
	// sell on ask price + 5%
	$sell_ask_pct = exchange_get($exchange, 'trade_sell_ask_pct', 1.05);
	// cancel order if our price is more than ask price + 20%
	$cancel_ask_pct = exchange_get($exchange, 'trade_cancel_ask_pct', 1.20);

	sleep(1);
	$tickers = $poloniex->get_ticker();
	if(!$tickers) return;

	// update orders
	$coins = getdbolist('db_coins', "enable=1 AND dontsell=0 AND id IN (SELECT DISTINCT coinid FROM markets WHERE name='poloniex')");
	foreach($coins as $coin)
	{
		$pair = "BTC_$coin->symbol";
		if(!isset($tickers[$pair])) continue;

		sleep(1);
		$orders = $poloniex->get_open_orders($pair);
		if(!$orders || !isset($orders[0]))
		{
			dborun("DELETE FROM orders WHERE coinid={$coin->id} AND market='poloniex'");
			continue;
		}

		foreach($orders as $order)
		{
			if(!isset($order['orderNumber']))
			{
				debuglog($order);
				continue;
			}

			if($order['rate'] > $tickers[$pair]['lowestAsk']*$cancel_ask_pct || $flushall)
			{
				debuglog("poloniex: cancel order for $pair {$order['orderNumber']}");
				sleep(1);
				doPoloniexCancelOrder($order['orderNumber'], $pair, $poloniex);
				//$poloniex->cancel_order($pair, $order['orderNumber']);

				//$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
				//    ':market'=>'poloniex', ':uuid'=>$order['orderNumber']
				//));
				//if($db_order) $db_order->delete();
			}

			else
			{
				$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
					':market'=>'poloniex', ':uuid'=>$order['orderNumber']
				));
				if($db_order) continue;

				// debuglog("poloniex: save order $coin->symbol");
				$db_order = new db_orders;
				$db_order->market = 'poloniex';
				$db_order->coinid = $coin->id;
				$db_order->amount = $order['amount'];
				$db_order->price = $order['rate'];
				$db_order->ask = $tickers[$pair]['lowestAsk'];
				$db_order->bid = $tickers[$pair]['highestBid'];
				$db_order->uuid = $order['orderNumber'];
				$db_order->created = time();
				$db_order->save();
			}
		}

		$list = getdbolist('db_orders', "coinid={$coin->id} AND market='poloniex'");
		foreach($list as $db_order)
		{
			$found = false;
			foreach($orders as $order)
			{
				if(!isset($order['orderNumber'])) {
					debuglog("poloniex no order id: ".json_encode($order));
					continue;
				}

				if($order['orderNumber'] == $db_order->uuid) {
					$found = true;
					break;
				}
			}

			if(!$found)
			{
				debuglog("poloniex: deleting order {$coin->symbol} $db_order->amount");
				$db_order->delete();
			}
		}
	}

	// add orders

	if (is_array($balances))
	foreach($balances as $symbol=>$balance)
	{
		if(!$balance || !arraySafeVal($balance,'available')) continue;
		if($symbol == 'BTC') continue;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if(!$coin || $coin->dontsell) continue;

		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='poloniex'");
		if($market) {
			$market->lasttraded = time();
			$market->balance = $balance['onOrders'];
			$market->save();
		}

		$pair = "BTC_$symbol";
		if(!isset($tickers[$pair])) continue;

		if($coin->sellonbid)
			$sellprice = bitcoinvaluetoa($tickers[$pair]['highestBid']);
		else
			$sellprice = bitcoinvaluetoa($tickers[$pair]['lowestAsk'] * $sell_ask_pct);

		if($balance['available'] * $sellprice < $min_btc_trade) continue;

//		debuglog("poloniex selling $pair, $sellprice, $balance");
		sleep(1);
		$res = $poloniex->sell($pair, $sellprice, $balance['available']);

		if(!isset($res['orderNumber']))
		{
			debuglog($res, 5);
			continue;
		}

		if(!isset($tickers[$pair])) continue;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if(!$coin) continue;

		$db_order = new db_orders;
		$db_order->market = 'poloniex';
		$db_order->coinid = $coin->id;
		$db_order->amount = $balance['available'];
		$db_order->price = $sellprice;
		$db_order->ask = $tickers[$pair]['lowestAsk'];
		$db_order->bid = $tickers[$pair]['highestBid'];
		$db_order->uuid = $res['orderNumber'];
		$db_order->created = time();
		$db_order->save();
	}

	$withdraw_min = exchange_get($exchange, 'withdraw_min_btc', EXCH_AUTO_WITHDRAW);
	$withdraw_fee = exchange_get($exchange, 'withdraw_fee_btc', 0.0001);

	if(is_object($savebalance))
	if(floatval($withdraw_min) > 0 && $savebalance->balance >= ($withdraw_min + $withdraw_fee))
	{
		// $btcaddr = exchange_get($exchange, 'withdraw_btc_address', YAAMP_BTCADDRESS);
		$btcaddr = YAAMP_BTCADDRESS;

		$amount = $savebalance->balance - $withdraw_fee;
		debuglog("poloniex: withdraw $amount BTC to $btcaddr");

		sleep(1);
		$res = $poloniex->withdraw('BTC', $amount, $btcaddr);
		debuglog($res);

		if($res && isset($res->response) && strpos($res->response, 'Withdrew') !== false)
		{
			$withdraw = new db_withdraws;
			$withdraw->market = 'poloniex';
			$withdraw->address = $btcaddr;
			$withdraw->amount = $amount;
			$withdraw->time = time();
			$withdraw->save();

			$savebalance->balance = 0;
			$savebalance->save();
		}
	}

//	debuglog('-------------- doPoloniexTrading() done');
}





