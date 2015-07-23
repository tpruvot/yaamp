<?php

function getCryptsyTicker($marketid)
{
	try {
		$res = @ fetch_url("http://pubapi2.cryptsy.com/api.php?method=singleorderdata&marketid=$marketid");
		if(!$res)
			$res = @ fetch_url("http://pubapi1.cryptsy.com/api.php?method=singleorderdata&marketid=$marketid");
		if(!$res) return null;

		$ticker = json_decode($res);

	} catch (Exception $e) {
		debuglog("cryptsy ticker http failure");
		$ticker = null;
	}

	return $ticker;
}

function doCryptsyTrading($quick=false)
{
	$flushall = rand(0, 4) == 0;
	if($quick) $flushall = false;

//	debuglog("-------------- doCryptsyTrading() $flushall");

	// add orders
	$savebalance = getdbosql('db_balances', "name='cryptsy'");
	$savebalance->balance = 0;

	$balances = cryptsy_api_query('getinfo');
	if(!$balances) return;
	if(!isset($balances['return'])) {
		debuglog("balances: ".strip_tags($balances));
		return;
	}
	foreach($balances['return']['balances_available'] as $symbol=>$balance)
	{
		if($symbol == 'BTC') {
			$savebalance->balance = floatval($balance);
			$savebalance->save();
			break;
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$orders = cryptsy_api_query('allmyorders');
	if(empty($orders)) return;
	if(!is_array($orders) || !isset($orders['return'])) {
		debuglog("-------------- doCryptsyTrading() $flushall ".json_encode($orders));
		return;
	}

	foreach($orders['return'] as $order)
	{
		if(!isset($order['marketid'])) continue;
		if(!isset($order['orderid'])) continue;

		$market = getdbosql('db_markets', "marketid=:marketid", array(':marketid'=>$order['marketid']));
		if(!$market) continue;

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) continue;

		$symbol = $coin->symbol;

		$ticker = getCryptsyTicker($market->marketid);
		if(!$ticker || !isset($ticker->return->$symbol->sellorders[0])) continue;

		$ask = bitcoinvaluetoa($ticker->return->$symbol->sellorders[0]->price);
		$sellprice = bitcoinvaluetoa($order['price']);

		// flush orders not on the ask
		if($ask+0.00000005 < $sellprice || $flushall)
		{
// 			debuglog("cryptsy cancel order for $coin->symbol ($ask != $sellprice)");
			cryptsy_api_query('cancelorder', array('orderid'=>$order['orderid']));

			$db_order = getdbosql('db_orders', "uuid=:uuid", array(':uuid'=>$order['orderid']));
			if($db_order) $db_order->delete();

			sleep(1);
		}

		// add existing orders (shouldnt happen after init)
		else
		{
			$db_order = getdbosql('db_orders', "uuid=:uuid", array(':uuid'=>$order['orderid']));
			if($db_order) continue;

			debuglog("adding order $coin->symbol");

			$db_order = new db_orders;
			$db_order->market = 'cryptsy';
			$db_order->coinid = $coin->id;
			$db_order->amount = $order['quantity'];
			$db_order->price = $sellprice;
			$db_order->ask = $ticker->return->$symbol->sellorders[0]->price;
			$db_order->bid = isset($ticker->return->$symbol->buyorders)? $ticker->return->$symbol->buyorders[0]->price: 0;
			$db_order->uuid = $order['orderid'];
			$db_order->created = time();
			$db_order->save();
  		}
	}

	$list = getdbolist('db_orders', "market='cryptsy'");
	foreach($list as $db_order)
	{
		$coin = getdbo('db_coins', $db_order->coinid);

		$found = false;
		foreach($orders['return'] as $order)
		{
			if(!isset($order['orderid'])) continue;

			if($order['orderid'] == $db_order->uuid)
			{
				$found = true;
				break;
			}
		}

		if(!$found)
		{
			debuglog("cryptsy deleting order $coin->name $db_order->amount");
			$db_order->delete();
		}
	}

//	if($flushall)
//	{
//		debuglog("cryptsy flushall");
//		return;
//	}

	sleep(2);

	foreach($balances['return']['balances_available'] as $symbol=>$balance)
	{
		if($symbol == 'Points') continue;
		if($symbol == 'BTC') continue;

		$balance = floatval($balance);
		if(!$balance) continue;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if(!$coin || $coin->dontsell) continue;

		$market = getdbosql('db_markets', "coinid=$coin->id and name='cryptsy'");
		if(!$market) continue;

		$market->lasttraded = time();
		$market->save();

		if($balance*$market->price < 0.00001000) continue;

		$ticker = getCryptsyTicker($market->marketid);
		if(!$ticker || !isset($ticker->return->$symbol->buyorders[0])) continue;

		// for 0 to 4
		{
			$nextbuy = $ticker->return->$symbol->buyorders[0];
			if($balance >= $nextbuy->quantity && $nextbuy->quantity*$nextbuy->price > 0.00001000)
			{
				$sellprice = bitcoinvaluetoa($nextbuy->price);
				debuglog("cryptsy selling market $coin->symbol, $nextbuy->quantity, $sellprice");

				$res = cryptsy_api_query('createorder',
					array('marketid'=>$market->marketid, 'ordertype'=>'Sell', 'quantity'=>$nextbuy->quantity, 'price'=>$sellprice));
				if($res) $balance -= $nextbuy->quantity;

			//	TradingClearExchangeCoin($coin, $nextbuy->quantity, $ticker->return->$symbol->buyorders[1]->price, 'cryptsy');
				sleep(1);
			}

			if($coin->sellonbid && $balance*$nextbuy->price > 0.00001000)
			{
				$sellprice = bitcoinvaluetoa($nextbuy->price);
				debuglog("cryptsy selling market $coin->symbol, $balance, $sellprice");

				$res = cryptsy_api_query('createorder',
					array('marketid'=>$market->marketid, 'ordertype'=>'Sell', 'quantity'=>$balance, 'price'=>$sellprice));

			//	TradingClearExchangeCoin($coin, $balance, $ticker->return->$symbol->buyorders[1]->price, 'cryptsy');
				sleep(1);
				continue;
			}
		}

		if($coin->sellonbid)
			$sellprice = $ticker->return->$symbol->buyorders[0]->price;
		else
			$sellprice = $ticker->return->$symbol->sellorders[0]->price;
		//	if($balance * $sellprice < 0.0001) continue;

//		debuglog("cryptsy selling $coin->symbol, $sellprice, $balance");
		$res = cryptsy_api_query('createorder',
			array('marketid'=>$market->marketid, 'ordertype'=>'Sell', 'quantity'=>$balance, 'price'=>$sellprice));
		if(!$res || !isset($res['orderid'])) continue;

		$db_order = new db_orders;
		$db_order->market = 'cryptsy';
		$db_order->coinid = $coin->id;
		$db_order->amount = $balance;
		$db_order->price = $sellprice;
		$db_order->ask = $ticker->return->$symbol->sellorders[0]->price;
		$db_order->bid = $ticker->return->$symbol->buyorders[0]->price;
		$db_order->uuid = $res['orderid'];
		$db_order->created = time();
		$db_order->save();
	}

    if(floatval(EXCH_AUTO_WITHDRAW) > 0 && $savebalance->balance >= (EXCH_AUTO_WITHDRAW + 0.0002))
	{
		$btcaddr = YAAMP_BTCADDRESS; //'14LS7Uda6EZGXLtRrFEZ2kWmarrxobkyu9';

		$amount = $savebalance->balance;	// - 0.001;
		debuglog("cryptsy withdraw $amount to $btcaddr");

		sleep(1);

		$res = cryptsy_api_query('makewithdrawal',
			array('address'=>$btcaddr, 'amount'=>$amount));

		debuglog($res);
		if($res && $res['success'])
		{
			$withdraw = new db_withdraws;
			$withdraw->market = 'cryptsy';
			$withdraw->address = $btcaddr;
			$withdraw->amount = $amount;
			$withdraw->time = time();
		//	$withdraw->uuid = $res->result->uuid;
			$withdraw->save();

		//	$savebalance->balance = 0;
		}
	}

//	debuglog('-------------- doCryptsyTrading() done');
}






