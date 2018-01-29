<?php

function doLiveCoinCancelOrder($pair = false, $id = false, $live = false)
{
	if (!$pair || !$id) {
		return;
	}

	if (!$livecoin) {
		$livecoin = new LiveCoinApi;
	}

	$res = $livecoin->cancelLimitOrder($pair, $id);

	if ($res->success === TRUE) {
		$db_order = getdbosql(
			'db_orders',
			'market=:market AND uuid=:uuid',
			array(':market'=>'livecoin', ':uuid'=>$id)
		);

		if ($db_order) {
			$db_order->delete();
		}
	}
}

function doLiveCoinTrading($quick = false)
{
	$exchange = 'livecoin';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) {
		return;
	}

	$livecoin = new LiveCoinApi;

	$savebalance = getdbosql('db_balances', "name='$exchange'");
	if (is_object($savebalance)) {
		$savebalance->balance = 0;
		$savebalance->save();
	} else {
		dborun("INSERT INTO balances (name,balance) VALUES ('$exchange',0)");
		return;
	}

	$balances = $livecoin->getBalances();
	if (!$balances || !is_array($balances)) {
		return;
	}

	foreach ($balances as $balance) {
		if ($balance->currency == 'BTC' && $balance->type == "available") {
			if (!is_object($savebalance)) continue;
			$savebalance->balance = $balance->value;
			$savebalance->save();
			continue;
		}
		if ($balance->currency == 'BTC' && $balance->type == "trade") {
			if (!is_object($savebalance)) continue;
			$savebalance->onsell = $balance->value;
			$savebalance->save();
			continue;
		}

		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist(
				'db_coins',
				'symbol=:symbol OR symbol2=:symbol',
				array(':symbol'=>$balance->currency)
			);

			if (empty($coins)) {
				continue;
			}

			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));

				if (!$market) {
					continue;
				}

				if ($balance->type == 'available') {
					$market->balance = arraySafeVal($balance, 'value', 0.0);
					$market->balancetime = time();
					$market->save();
				} elseif ($balance->type == 'trade') {
					$market->ontrade = arraySafeVal($balance, 'value', 0.0);
					$market->balancetime = time();
					$market->save();
				}

			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) {
		return;
	}

	$flushall = rand(0, 8) == 0;
	if ($quick) {
		$flushall = false;
	}

	$min_btc_trade = exchange_get($exchange, 'trade_min_btc', 0.0001);
	$sell_ask_pct = exchange_get($exchange, 'trade_sell_ask_pct', 1.05);
	$cancel_ask_pct = exchange_get($exchange, 'trade_cancel_ask_pct', 1.20);

	// upgrade orders
	$coins = getdbolist('db_coins', "enable=1 AND IFNULL(dontsell,0)=0 AND id IN (SELECT DISTINCT coinid FROM markets WHERE name='livecoin')");
	foreach ($coins as $coin) {
		if ($coin->dontsell || $coin->symbol == 'BTC') {
			continue;
		}

		$pair = $coin->symbol.'/BTC';
		sleep(1);
		$orders = $livecoin->getClientOrders($pair, 'OPEN');

		if (isset($orders->data)) {
			$order_data = $orders->data;
		} else {
			$order_data = array();
		}

		foreach ($order_data as $order) {
			$uuid = $order->id;
			$pair = $order->currencyPair;
			sleep(1);
			$ticker = $livecoin->getTickerInfo($pair);

			if (!is_object($ticker) || !$order->price) {
				continue;
			}

			if ($order->price > $cancel_ask_pct*$ticker->best_ask || $flushall) {
				sleep(1);
				doLiveCoinCancelOrder($pair, $uuid, $livecoin);
			} else {
				$db_order = getdbosql(
					'db_orders',
					'market=:market AND uuid=:uuid',
					array(':market'=>'livecoin', ':uuid'=>$uuid)
				);

				if ($db_order) {
					continue;
				}

				$db_order = new db_orders;
				$db_order->market = 'livecoin';
				$db_order->coinid = $coin->id;
				$db_order->amount = $order->quantity;
				$db_order->price = $order->price;
				$db_order->ask = $ticker->best_ask;
				$db_order->bid = $ticker->best_sell;
				$db_order->uuid = $uuid;
				$db_order->created = time();
				$db_order->save();
			}
		}
		$list = getdbolist('db_orders', "coinid=$coin->id and market='livecoin'");
		foreach ($list as $db_order) {
			$found = false;
			foreach ($order_data as $order) {
				$uuid = $order->id;
				if ($uuid == $db_order->uuid) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				debuglog("LiveCoin: Deleting order $coin->name $db_order->amount");
				$db_order->delete();
			}
		}
	}
	sleep(2);

	/* Update balances  and sell */
	if (!$balances) {
		return;
	}

	foreach ($balances as $balance) {
		if ($balance->type != 'available') {
			continue;
		}

		$amount = $balance->value;
		$symbol = $balance->currency;
		if ($symbol == 'BTC') {
			continue;
		}

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if (!$coin || $coin->dontsell) {
			continue;
		}

		$market2 = getdbosql('db_markets', "coinid={$coin->id} AND (name='bittrex' OR name='poloniex')");
		if ($market2) {
			continue;
		}

		$market = getdbosql('db_markets', "coinid=$coin->id and name='livecoin'");
		if ($market) {
			$market->lasttraded = time();
			$market->save();
		}

		if ($amount*$coin->price < $min_btc_trade) {
			continue;
		}

		sleep(1);

		$pair = "$symbol/BTC";
		$ticker = $livecoin->getTickerInfo($pair);
		if(!(isset($ticker->best_bid) && isset($ticker->best_ask))) continue;
		if($coin->sellonbid)
			$sellprice = bitcoinvaluetoa($ticker->best_bid);
		else
			$sellprice = bitcoinvaluetoa($ticker->best_ask * $sell_ask_pct);

		if ($amount*$sellprice > $min_btc_trade) {
			debuglog("LiveCoin: Selling market $pair, $sellprice, $sellprice");
			sleep(1);

			$res = $livecoin->sellLimit($pair, $sellprice, $amount);
			if (!($res->success === TRUE && $res->added === TRUE)) {
				debuglog('LiveCoin: Sell failed');
				continue;
			}
		}

		$db_order = new db_orders;
		$db_order->market = 'livecoin';
		$db_order->coinid = $coin->id;
		$db_order->amount = $amount;
		$db_order->price = $sellprice;
		$db_order->ask = $ticker->best_ask;
		$db_order->bid = $ticker->best_bid;
		$db_order->uuid = $res->orderId;
		$db_order->created = time();
		$db_order->save();
	}

	/* Withdrawals */
	$btcaddr = YAAMP_BTCADDRESS;
	$withdraw_min = exchange_get($exchange, 'withdraw_min_btc', EXCH_AUTO_WITHDRAW);
	$withdraw_fee = exchange_get($exchange, 'withdraw_fee_btc', 0.0005);
	if (floatval($withdraw_min) > 0 && $savebalance->balance >= ($withdraw_min + $withdraw_fee)) {
		$amount = $savebalance->balance - $withdraw_fee;
		debuglog("$exchange: withdraw $amount BTC to $btcaddr");
		sleep(1);
		$res = $livecoin->withdrawCoin($amount, 'BTC', $btcaddr);
		debuglog("$exchange: withdraw ".json_encode($res));
		if (is_object($res)) {
			$withdraw = new db_withdraws;
			$withdraw->market = 'livecoin';
			$withdraw->address = $btcaddr;
			$withdraw->amount = $amount;
			$withdraw->time = time();
			$withdraw->uuid = $res->id;
			$withdraw->save();
			$savebalance->balance = 0;
			$savebalance->save();
		} else {
			debuglog("$exchange: Withdraw Failed ".json_encode($res));
		}
	}
}
