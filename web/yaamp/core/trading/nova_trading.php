<?php

function doNovaCancelOrder($id=false)
{
	if (!$id) {
		return;
	}

	$res = nova_api_user("cancelorder/{$id}");

	if (!is_object($res)) return;

	if ($res->status == 'ok') {
		$db_order = getdbosql(
			'db_orders',
			'market=:market AND uuid=:uuid',
			array(':market'=>'nova', ':uuid'=>$id)
		);

		if ($db_order) {
			$db_order->delete();
		}
	}
}

function doNovaTrading($quick=false)
{
	$exchange = 'nova';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	$balances = nova_api_user('getbalances');
	if(!is_object($balances) || $balances->status != 'success' || !isset($balances->balances)) return;

	//{"currencyid":1,"currency":"BTC","amount":"0.00000000","amount_trades":"0.00000000","amount_total":"0.00000000","currencyname":"Bitcoin","amount_lockbox":"0.00000000"}

	$savebalance = getdbosql('db_balances', "name='{$exchange}'");
	if (is_object($savebalance)) {
		$savebalance->balance = 0;
		$savebalance->onsell = 0;
		$savebalance->save();
	} else {
		dborun("INSERT INTO balances (name,balance) VALUES ('$exchange',0)");
		$savebalance = getdbosql('db_balances', "name='{$exchange}'");
	}

	foreach($balances->balances as $balance)
	{
		if ($balance->currency == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance->amount;
				$savebalance->onsell = $balance->amount_trades;
				$savebalance->save();
			}
			continue;
		}

		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>$balance->currency)
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='{$exchange}'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $balance->amount;
				$market->ontrade = $balance->amount_trades;
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$flushall = rand(0, 8) == 0;
	if($quick) $flushall = false;


	$min_btc_trade = exchange_get($exchange, 'trade_min_btc', 0.0001);
	$sell_ask_pct = exchange_get($exchange, 'trade_sell_ask_pct', 1.05);
	$cancel_ask_pct = exchange_get($exchange, 'trade_cancel_ask_pct', 1.20);


	$orders = nova_api_user("myopenorders");
	if(!$orders || $orders->status != 'success') return;

	foreach ($orders->items as $order) {
		if($order->ordertype != 'SELL') continue;
		if($order->tocurrency != 'BTC') continue;

		$uuid = $order->orderid;
		$pair = $order->market;
		$symbol = $order->fromcurrency;

		$coin = getdbosql('db_coins', "symbol=:symbol OR symbol2=:symbol", array(':symbol'=>$symbol));
		if(!$coin || is_array($coin) || $coin->dontsell) continue;

		sleep(1);
		$res = nova_api_query("market/info/{$pair}");
		if (!is_object($res) || empty($res->markets)) continue;
		$ticker = $res->markets[0];

		if(!(isset($ticker->bid) && isset($ticker->ask)) || !$order->price) continue;

		if ($order->price > $cancel_ask_pct*$ticker->ask || $flushall) {
			sleep(1);
			doNovaCancelOrder($uuid);
		} else {
			$db_order = getdbosql(
				'db_orders',
				'market=:market AND uuid=:uuid',
				array(':market'=>$exchange, ':uuid'=>$uuid)
			);

			if ($db_order) {
				continue;
			}

			$db_order = new db_orders;
			$db_order->market = $exchange;
			$db_order->coinid = $coin->id;
			$db_order->amount = $order->fromamount;
			$db_order->price = $order->price;
			$db_order->ask = $ticker->ask;   //
			$db_order->bid = $ticker->bid;  //
			$db_order->uuid = $uuid;
			$db_order->created = time();
			$db_order->save();
		}
	}

	$list = getdbolist('db_orders', "market='$exchange'");
	foreach ($list as $db_order) {
		$coin = getdbo('db_coins', $db_order->coinid);
		if(!$coin) continue;

		$found = false;
		foreach ($orders->items as $order) {
			if($order->ordertype != 'SELL') continue;

			if ($order->orderid == $db_order->uuid) {
				$found = true;
				break;
			}
		}
		if (!$found) {
			debuglog("Nova: Deleting order $coin->name $db_order->amount");
			$db_order->delete();
		}
	}

	sleep(2);

	/* Update balances  and sell */
	if (!$balances) {
		return;
	}

	foreach ($balances->balances as $balance) {
		$amount = $balance->amount;
		$symbol = $balance->currency;
		if ($symbol == 'BTC') {
			continue;
		}

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if (!$coin || $coin->dontsell) {
			continue;
		}

		$market = getdbosql('db_markets', "coinid=$coin->id and name='{$exchange}'");
		if ($market) {
			$market->lasttraded = time();
			$market->save();
		}

		if ($amount*$coin->price < $min_btc_trade) {
			continue;
		}

		sleep(1);

		$pair = "BTC_{$symbol}";
		$info = nova_api_query("market/info/{$pair}");
		if (!is_object($info) || empty($info->markets)) continue;
		$ticker = $info->markets[0];

		if(!(isset($ticker->bid) && isset($ticker->ask))) continue;
		if($coin->sellonbid)
			$sellprice = bitcoinvaluetoa($ticker->bid);
		else
			$sellprice = bitcoinvaluetoa($ticker->ask * $sell_ask_pct);

		if ($amount*$sellprice > $min_btc_trade) {
			debuglog("Nova: Selling market $pair, $amount, $sellprice");
			sleep(1);

			$res = nova_api_user("trade/{$pair}", array("tradetype" => "SELL", "tradeprice" => $sellprice, "tradeamount" => $amount, "tradebase" => 0));
			if (!($res->status == 'success')) {
				debuglog('Nova: Sell failed');
				continue;
			}

			$db_order = new db_orders;
			$db_order->market = $exchange;
			$db_order->coinid = $coin->id;
			$db_order->amount = $amount;
			$db_order->price = $sellprice;
			$db_order->ask = $ticker->ask;
			$db_order->bid = $ticker->bid;
			$db_order->uuid = $res->tradeitems[0]->orderid;
			$db_order->created = time();
			$db_order->save();
		}
	}

	/* Withdrawals */
	$btcaddr = YAAMP_BTCADDRESS;
	$withdraw_min = exchange_get($exchange, 'withdraw_min_btc', EXCH_AUTO_WITHDRAW);
	$withdraw_fee = exchange_get($exchange, 'withdraw_fee_btc', 0.001);
	if (floatval($withdraw_min) > 0 && $savebalance->balance >= ($withdraw_min + $withdraw_fee)) {
		$amount = $savebalance->balance - $withdraw_fee;
		debuglog("$exchange: withdraw $amount BTC to $btcaddr");
		sleep(1);
		$res = nova_api_user("withdraw/BTC", array("currency" => "BTC", "amount" => $amount, "address" => $btcaddr));
		debuglog("$exchange: withdraw ".json_encode($res));
		if ($res->status == 'success') {
			$withdraw = new db_withdraws;
			$withdraw->market = $exchange;
			$withdraw->address = $btcaddr;
			$withdraw->amount = $amount;
			$withdraw->time = time();
			//$withdraw->uuid = $res->id;
			$withdraw->save();
			$savebalance->balance = $res->amount_after_withdraw;
			$savebalance->save();
		} else {
			debuglog("$exchange: Withdraw Failed ".json_encode($res));
		}
	}

}
