<?php

function doBittrexTrading($quick=false)
{
	$flushall = rand(0, 4) == 0;
	if($quick) $flushall = false;

//	debuglog("-------------- doBittrexTrading() flushall $flushall");

	$balances = bittrex_api_query('account/getbalances');
	if(!$balances || !isset($balances->result) || !$balances->success) return;

	$savebalance = getdbosql('db_balances', "name='bittrex'");
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

	$orders = bittrex_api_query('market/getopenorders');
	if(!$orders || !$orders->success) return;

	foreach($orders->result as $order)
	{
		$symbol = substr($order->Exchange, 4);
		$pair = $order->Exchange;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if(!$coin) continue;
		if($coin->dontsell) continue;

		// ignore buy orders
		if(strpos($order->OrderType, 'SELL') == false) continue;

		$ticker = bittrex_api_query('public/getticker', "&market=$order->Exchange");
		if(!$ticker || !$ticker->success || !$ticker->result) continue;

		$ask = bitcoinvaluetoa($ticker->result->Ask);
		$sellprice = bitcoinvaluetoa($order->Limit);

		// flush orders not on the ask
		if($ask+0.00000005 < $sellprice || $flushall)
		{
			debuglog("bittrex cancel order $order->Exchange $sellprice -> $ask");
			bittrex_api_query('market/cancel', "&uuid=$order->OrderUuid");

			$db_order = getdbosql('db_orders', "uuid=:uuid", array(':uuid'=>$order->OrderUuid));
			if($db_order) $db_order->delete();

			sleep(1);
		}

		// add existing orders (shouldnt happen after init)
		else
		{
			$db_order = getdbosql('db_orders', "uuid=:uuid", array(':uuid'=>$order->OrderUuid));
			if($db_order) continue;

			debuglog("adding order $coin->symbol");

		//	$ticker = bittrex_api_query('public/getticker', "&market=$pair");
		//	$sellprice = bitcoinvaluetoa($ticker->result->Ask);

			$db_order = new db_orders;
			$db_order->market = 'bittrex';
			$db_order->coinid = $coin->id;
			$db_order->amount = $order->Quantity;
			$db_order->price = $sellprice;
			$db_order->ask = $ticker->result->Ask;
			$db_order->bid = $ticker->result->Bid;
			$db_order->uuid = $order->OrderUuid;
			$db_order->created = time();
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
			if(strpos($order->OrderType, 'SELL') == false) continue;

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

// 	if($flushall)
// 	{
// 		debuglog("bittrex flushall");
// 		return;
// 	}

	sleep(2);

	// add orders

	foreach($balances->result as $balance)
	{
		if($balance->Currency == 'BTC') continue;

		$amount = floatval($balance->Available);
		if(!$amount) continue;

	//	debuglog($balance->Currency);

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$balance->Currency));
		if(!$coin || $coin->dontsell) continue;

		$market = getdbosql('db_markets', "coinid=$coin->id and name='bittrex'");
		if($market)
		{
			$market->lasttraded = time();
			$market->save();
		}

		if($amount*$coin->price < 0.00050000) continue;
		$pair = "BTC-$balance->Currency";

		$data = bittrex_api_query('public/getorderbook', "&market=$pair&type=buy&depth=10");
		if(!$data || !$data->success) continue;

		for($i = 0; $i < 5 && $amount >= 0; $i++)
		{
			if(!isset($data->result->buy[$i])) break;

			$nextbuy = $data->result->buy[$i];
			if($amount*1.1 < $nextbuy->Quantity) break;

			$sellprice = bitcoinvaluetoa($nextbuy->Rate);
			$sellamount = min($amount, $nextbuy->Quantity);

			if($sellamount*$sellprice < 0.00050000) continue;

			debuglog("bittrex selling market $pair, $sellamount, $sellprice");
			$res = bittrex_api_query('market/selllimit', "&market=$pair&quantity=$sellamount&rate=$sellprice");

			if(!$res->success)
			{
				debuglog($res);
				break;
			}

			$amount -= $sellamount;
		}

		if($amount <= 0) continue;

		$ticker = bittrex_api_query('public/getticker', "&market=$pair");
		if(!$ticker || !$ticker->success || !$ticker->result) continue;

		if($coin->sellonbid)
			$sellprice = bitcoinvaluetoa($ticker->result->Bid);
		else
			$sellprice = bitcoinvaluetoa($ticker->result->Ask);
		if($amount*$sellprice < 0.00050000) continue;

		debuglog("bittrex selling $pair, $amount, $sellprice");

		$res = bittrex_api_query('market/selllimit', "&market=$pair&quantity=$amount&rate=$sellprice");
		if(!$res || !$res->success)
		{
			debuglog($res);
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

		sleep(1);
	}

	if(floatval(EXCH_AUTO_WITHDRAW) > 0 && $savebalance->balance >= (EXCH_AUTO_WITHDRAW + 0.0002))
	{
		$btcaddr = YAAMP_BTCADDRESS;
		$amount = $savebalance->balance + 0.0002;
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

		//	$savebalance->balance = 0;
		}
	}

//	debuglog('-------------- doBittrexTrading() done');
}
