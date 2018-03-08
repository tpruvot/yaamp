<?php

function doEmpoexTrading($quick=false)
{
	$exchange = 'empoex';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	$flushall = rand(0, 8) == 0;
	if($quick) $flushall = false;

	$balances = empoex_api_user('account/balance','BTC');
	// {"available":[{"Coin":"BTC","Amount":"0.00102293"}],"pending":[],"held":[]}

	if(!$balances || !isset($balances->available) || empty($balances->available)) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");
	if (is_object($savebalance)) {
		$savebalance->balance = 0;
		$savebalance->save();
	}

	foreach($balances->available as $balance)
	{
		if($balance->Coin == 'BTC') {
			if (!is_object($savebalance)) continue;
			$savebalance->balance = $balance->Amount;
			$savebalance->save();
			continue;
		}

		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>$balance->Coin)
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $balance->Amount;
				//$market->ontrade = $balance->held ... todo
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;
}
