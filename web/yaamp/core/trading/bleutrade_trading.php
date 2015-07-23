<?php

function doBleutradeTrading($quick=false)
{
	$flushall = rand(0, 4) == 0;
	if($quick) $flushall = false;

//	debuglog("-------------- dobleutradeTrading() flushall $flushall");

//	debuglog($balances);
	$balances = bleutrade_api_query('account/getbalances');
	if(!$balances || !isset($balances->result) || !$balances->success) return;

	$savebalance = getdbosql('db_balances', "name='bleutrade'");
	$savebalance->balance = 0;

	foreach($balances->result as $balance)
	{
		if($balance->Currency == 'BTC') {
			$savebalance->balance = $balance->Available;
			$savebalance->save();
			break;
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

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

		$ticker = bleutrade_api_query('public/getticker', "&market=$pair");
		if(!$ticker || !$ticker->success || !isset($ticker->result[0])) continue;

		$ask = bitcoinvaluetoa($ticker->result[0]->Ask);
		$sellprice = bitcoinvaluetoa($order->Price);

		// flush orders not on the ask
		if($ask+0.00000005 < $sellprice || $flushall)
		{
 //			debuglog("bleutrade cancel order $order->Exchange $sellprice -> $ask");
			bleutrade_api_query('market/cancel', "&orderid=$order->OrderId");

			$db_order = getdbosql('db_orders', "uuid=:uuid", array(':uuid'=>$order->OrderId));
			if($db_order) $db_order->delete();

			sleep(1);
		}

		// add existing orders (shouldnt happen after init)
		else
		{
			$db_order = getdbosql('db_orders', "uuid=:uuid", array(':uuid'=>$order->OrderId));
			if($db_order) continue;

			debuglog("bleutrade adding order $coin->symbol");

		//	$ticker = bleutrade_api_query('public/getticker', "&market=$pair");
		//	$sellprice = bitcoinvaluetoa($ticker->result->Ask);

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

	sleep(2);

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

		if($amount*$coin->price < 0.00001000) continue;
		$pair = "{$balance->Currency}_BTC";

		$data = bleutrade_api_query('public/getorderbook', "&market=$pair&type=BUY&depth=10");
		if(!$data) continue;
	//	if(!isset($data->result[0])) continue;

		for($i = 0; $i < 5; $i++)
		{
			if(!isset($data->result->buy[$i])) break;

			$nextbuy = $data->result->buy[$i];
			if($amount < $nextbuy->Quantity || $nextbuy->Quantity*$nextbuy->Rate < 0.00001000)
				break;

			$sellprice = bitcoinvaluetoa($nextbuy->Rate);

//			debuglog("bleutrade selling market $pair, $nextbuy->Quantity, $sellprice");
			$res = bleutrade_api_query('market/selllimit', "&market=$pair&quantity=$nextbuy->Quantity&rate=$sellprice");

			if(!$res->success)
			{
				debuglog($res);
				break;
			}

			$amount -= $nextbuy->Quantity;
		}

		$ticker = bleutrade_api_query('public/getticker', "&market=$pair");
		if(!$ticker || !$ticker->success || !isset($ticker->result[0])) continue;

		if($coin->sellonbid)
			$sellprice = bitcoinvaluetoa($ticker->result[0]->Bid);
		else
			$sellprice = bitcoinvaluetoa($ticker->result[0]->Ask);
		if($amount*$sellprice < 0.00050000) continue;

		debuglog("bleutrade selling $pair, $amount, $sellprice");

		$res = bleutrade_api_query('market/selllimit', "&market=$pair&quantity=$amount&rate=$sellprice");
		if(!$res || !$res->success || !isset($res->result))
		{
			debuglog($res);
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

		sleep(1);
	}

    if(floatval(EXCH_AUTO_WITHDRAW) > 0 && $savebalance->balance >= (EXCH_AUTO_WITHDRAW + 0.0002))
	{
		$btcaddr = YAAMP_BTCADDRESS; //'14LS7Uda6EZGXLtRrFEZ2kWmarrxobkyu9';

		$amount = $savebalance->balance;	// - 0.0002;
		debuglog("bleutrade withdraw $amount to $btcaddr");

		sleep(1);

		$res = bleutrade_api_query('account/withdraw', "&currency=BTC&quantity=$amount&address=$btcaddr");
		debuglog($res);

		if($res && $res->success)
		{
			$withdraw = new db_withdraws;
			$withdraw->market = 'bleutrade';
			$withdraw->address = $btcaddr;
			$withdraw->amount = $amount;
			$withdraw->time = time();
			$withdraw->uuid = $res->result->orderid;
			$withdraw->save();

		//	$savebalance->balance = 0;
		}
	}

	//	debuglog('-------------- dobleutradeTrading() done');
}






