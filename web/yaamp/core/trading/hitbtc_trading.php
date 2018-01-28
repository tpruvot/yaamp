<?php

function doHitBTCCancelOrder($OrderID=false)
{
	if(!$OrderID) return;

	// todo
}

function doHitBTCTrading($quick=false)
{
	$exchange = 'hitbtc';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	$data = hitbtc_api_user('trading/balance');
	if (!is_object($data) || !isset($data->balance)) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");

	if (is_array($data->balance))
	foreach($data->balance as $balance)
	{
		if ($balance->currency_code == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance->cash;
				$savebalance->onsell = $balance->reserved;
				$savebalance->save();
			}
			continue;
		}

		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>$balance->currency_code)
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $balance->cash;
				$market->ontrade = $balance->reserved;
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	// real trading, todo..
}
