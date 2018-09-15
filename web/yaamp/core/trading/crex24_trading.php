<?php

function doCrex24CancelOrder($OrderID=false)
{
	if(!$OrderID) return;

	// todo
}

function doCrex24Trading($quick=false)
{
	$exchange = 'crex24';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	$data = crex24_api_user('account/balance','nonZeroOnly=false');
	if (!is_array($data) || empty($data)) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");

	foreach($data as $balance)
	{
		if ($balance->currency == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance->available;
				$savebalance->onsell = $balance->reserved;
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
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $balance->available;
				$market->ontrade = $balance->reserved;
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	// real trading, todo..
}
