<?php

function doYobitCancelOrder($OrderID=false)
{
	if(!$OrderID) return;

	$res = yobit_api_query2('CancelOrder', array('order_id'=>$OrderID));

	if($res && $res['success']) {
		$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
			':market'=>'yobit', ':uuid'=>$OrderID
		));
		if($db_order) $db_order->delete();
	}
}

function doYobitTrading($quick=false)
{
	$savebalance = getdbosql('db_balances', "name='yobit'");
	if(!$savebalance) return;

	$savebalance->balance = 0;

	$balances = yobit_api_query2('getInfo');
	if(!$balances || !isset($balances['return'])) return;
	if(!isset($balances['return']['funds'])) return;

	foreach($balances['return']['funds'] as $symbol => $amount)
	{
		if ($symbol == 'btc') {
			$savebalance->balance = $amount;
			$savebalance->save();
			continue;
		}
		if (!YAAMP_ALLOW_EXCHANGE) {
			// store balance in market table (= available + onorders on yobit)
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>strtoupper($symbol))
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='yobit'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				if ($market->balance != $amount) {
					$market->balance = $amount;
					$market->save();
				}
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$flushall = rand(0, 8) == 0;
	if($quick) $flushall = false;

	$min_btc_trade = 0.00010000; // minimum allowed by the exchange
	$sell_ask_pct = 1.05;        // sell on ask price + 5%
	$cancel_ask_pct = 1.20;      // cancel order if our price is more than ask price + 20%

	$coins = getdbolist('db_coins', "enable=1 AND IFNULL(dontsell,0)=0 AND id IN (SELECT DISTINCT coinid FROM markets WHERE name='yobit')");
	foreach($coins as $coin)
	{
		if($coin->dontsell) continue;
		$pair = strtolower("{$coin->symbol}_btc");

		sleep(1);
		$orders = yobit_api_query2('ActiveOrders', array('pair'=>$pair));
		if(isset($orders['return'])) foreach($orders['return'] as $uuid=>$order)
		{
			sleep(1);
			$ticker = yobit_api_query("ticker/$pair");
			if(!$ticker) continue;

			if($order['rate'] > $cancel_ask_pct*$ticker->$pair->sell || $flushall)
			{
				debuglog("yobit: cancel order for $pair $uuid");
				sleep(1);
				doYobitCancelOrder($uuid);
				// $res = yobit_api_query2('CancelOrder', array('order_id'=>$uuid));

				//$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
				//    ':market'=>'yobit', ':uuid'=>$uuid
				//));
				//if($db_order) $db_order->delete();
			}

			else
			{
				$db_order = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
					':market'=>'yobit', ':uuid'=>$uuid
				));
				if($db_order) continue;

				// debuglog("yobit adding order $coin->symbol");
				$db_order = new db_orders;
				$db_order->market = 'yobit';
				$db_order->coinid = $coin->id;
				$db_order->amount = $order['amount'];
				$db_order->price = $order['rate'];
				$db_order->ask = $ticker->$pair->sell;
				$db_order->bid = $ticker->$pair->buy;
				$db_order->uuid = $uuid;
				$db_order->created = time();
				$db_order->save();
			}
		}

		$list = getdbolist('db_orders', "coinid={$coin->id} and market='yobit'");
		foreach($list as $db_order)
		{
			$found = false;
			if(isset($orders['return'])) foreach($orders['return'] as $uuid=>$order)
			{
				if($uuid == $db_order->uuid)
				{
					$found = true;
					break;
				}
			}

			if(!$found)
			{
				debuglog("yobit deleting order $coin->name $db_order->amount");
				$db_order->delete();
			}
		}
	}

	sleep(2);

	//////////////////////////////////////////////////////////////////////////////////////////////////

	foreach($balances['return']['funds'] as $symbol=>$amount)
	{
// 		debuglog("$symbol, $amount");
		$amount -= 0.0001;
		if($amount<=0) continue;
		if($symbol == 'btc') continue;

		$coin = getdbosql('db_coins', "symbol=:symbol OR symbol2=:symbol", array(':symbol'=>strtoupper($symbol)));
		if(!$coin || is_array($coin) || $coin->dontsell) continue;

		$market = getdbosql('db_markets', "coinid=$coin->id and name='yobit'");
		if($market) {
			$market->lasttraded = time();
			$market->save();
		}

		$market2 = getdbosql('db_markets', "coinid={$coin->id} AND (name='bittrex' OR name='poloniex')");
		if($market2) continue;

		if($amount*$coin->price < $min_btc_trade) continue;
		$pair = "{$symbol}_btc";

		sleep(1);
		$data = yobit_api_query("depth/$pair?limit=11");
		if(!$data) continue;

		$sold_amount = 0;
		if($coin->sellonbid)
		for($i = 0; $i < 10 && $amount >= 0; $i++)
		{
			if(!isset($data->$pair->bids[$i])) break;

			$nextbuy = $data->$pair->bids[$i];
			if($amount*1.1 < $nextbuy[1]) break;

			$sellprice = bitcoinvaluetoa($nextbuy[0]);
			$sellamount = min($amount, $nextbuy[1]);

			if($sellamount*$sellprice < $min_btc_trade) continue;

			debuglog("yobit: selling market $pair, $sellamount, $sellprice");
			sleep(1);
			$res = yobit_api_query2('Trade', array('pair'=>$pair, 'type'=>'sell', 'rate'=>$sellprice, 'amount'=>$sellamount));

			if(!$res || !$res['success']) {
				debuglog("yobit err: ".json_encode($res));
				break;
			}

			$amount -= $sellamount;
			$sold_amount += $sellamount;
		}

		sleep(1);
		$ticker = yobit_api_query("ticker/$pair");
		if(!$ticker) continue;

		if($amount <= 0) continue;

		if($coin->sellonbid)
			$sellprice = bitcoinvaluetoa($ticker->$pair->buy);
		else
			$sellprice = bitcoinvaluetoa($ticker->$pair->sell * $sell_ask_pct);
		if($amount*$sellprice < $min_btc_trade) continue;

// 		debuglog("yobit selling $pair, $amount, $sellprice");

		sleep(1);
		$res = yobit_api_query2('Trade', array('pair'=>$pair, 'type'=>'sell', 'rate'=>$sellprice, 'amount'=>$amount));
		if(!$res || !$res['success']) {
			debuglog("yobit err: ".json_encode($res));
			continue;
		}

		$db_order = new db_orders;
		$db_order->market = 'yobit';
		$db_order->coinid = $coin->id;
		$db_order->amount = $amount;
		$db_order->price = $sellprice;
		$db_order->ask = $ticker->$pair->sell;
		$db_order->bid = $ticker->$pair->buy;
		$db_order->uuid = $res['return']['order_id'];
		$db_order->created = time();
		$db_order->save();
	}

}
