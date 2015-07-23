<?php

function doPoloniexTrading()
{
//	debuglog('-------------- doPoloniexTrading()');

	$flushall = rand(0, 4) == 0;
	$poloniex = new poloniex;

	// add orders
	$savebalance = getdbosql('db_balances', "name='poloniex'");
	$balances = $poloniex->get_balances();

	if (is_array($balances))
	foreach($balances as $symbol => $balance)
	{
		if($symbol == 'BTC')
		{
			$savebalance->balance = $balance;
			$savebalance->save();
			break;
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$tickers = $poloniex->get_ticker();
	if(!$tickers) return;

	// upgrade orders
	$coins = getdbolist('db_coins', "enable and id in (select distinct coinid from markets where name='poloniex')");
	foreach($coins as $coin)
	{
		if($coin->dontsell) continue;
		$pair = "BTC_$coin->symbol";
		if(!isset($tickers[$pair])) continue;

		$orders = $poloniex->get_open_orders($pair);
		if(!$orders || !isset($orders[0]))
		{
			dborun("delete from orders where coinid=$coin->id and market='poloniex'");
			continue;
		}

		foreach($orders as $order)
		{
			if(!isset($order['orderNumber']))
			{
				debuglog($order);
				continue;
			}

			if($order['rate'] > $tickers[$pair]['lowestAsk']+0.00000005 || $flushall)
			{
//				debuglog("poloniex cancel order for $pair {$order['orderNumber']}");
				$poloniex->cancel_order($pair, $order['orderNumber']);

				$db_order = getdbosql('db_orders', "uuid=:uuid", array(':uuid'=>$order['orderNumber']));
				if($db_order) $db_order->delete();

				sleep(1);
			}

			else
			{
				$db_order = getdbosql('db_orders', "uuid=:uuid", array(':uuid'=>$order['orderNumber']));
				if($db_order) continue;

				debuglog("poloniex adding order $coin->symbol");

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

		$list = getdbolist('db_orders', "coinid=$coin->id and market='poloniex'");
		foreach($list as $db_order)
		{
			$found = false;
			foreach($orders as $order)
			{
				if(!isset($order['orderNumber']))
				{
					debuglog($order);
					continue;
				}

				if($order['orderNumber'] == $db_order->uuid)
				{
					$found = true;
					break;
				}
			}

			if(!$found)
			{
				debuglog("poloniex deleting order $coin->name $db_order->amount");
				$db_order->delete();
			}
		}
	}

	// add orders

	foreach($balances as $symbol=>$balance)
	{
		if(!$balance) continue;
		if($symbol == 'BTC') continue;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if(!$coin || $coin->dontsell) continue;

		$market = getdbosql('db_markets', "coinid=$coin->id and name='poloniex'");
		if($market)
		{
			$market->lasttraded = time();
			$market->save();
		}

		$pair = "BTC_$symbol";
		if(!isset($tickers[$pair])) continue;

		$sellprice = $tickers[$pair]['highestBid'];
		if($balance * $sellprice < 0.0001) continue;

//		debuglog("poloniex selling $pair, $sellprice, $balance");
		$res = $poloniex->sell($pair, $sellprice, $balance);

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
		$db_order->amount = $balance;
		$db_order->price = $sellprice;
		$db_order->ask = $tickers[$pair]['lowestAsk'];
		$db_order->bid = $tickers[$pair]['highestBid'];
		$db_order->uuid = $res['orderNumber'];
		$db_order->created = time();
		$db_order->save();
	}

    if(floatval(EXCH_AUTO_WITHDRAW) > 0 && $savebalance->balance >= (EXCH_AUTO_WITHDRAW + 0.0002))
	{
		$btcaddr = YAAMP_BTCADDRESS;

		$amount = $savebalance->balance;	// - 0.0002;
		debuglog("poloniex withdraw $amount to $btcaddr");

		sleep(1);

		$res = $poloniex->withdraw('BTC', $amount, $btcaddr);
		debuglog($res);

		if($res && $res->success)
		{
			$withdraw = new db_withdraws;
			$withdraw->market = 'poloniex';
			$withdraw->address = $btcaddr;
			$withdraw->amount = $amount;
			$withdraw->time = time();
		//	$withdraw->uuid = $res->result->uuid;
			$withdraw->save();
		}
	}

//	debuglog('-------------- doPoloniexTrading() done');
}





