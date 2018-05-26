<?php

// https://bter.com/api#trade

function doBterTrading($quick=false)
{
	$exchange = 'bter';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	// "available_funds":{"BTC":"0.03337671","LTC":"4.364",...}, "locked_funds":{"BTC":"0.0002",...
	$balances = bter_api_user('getfunds');
	if(!$balances || !isset($balances['result']) || $balances['result'] != 'true') return;

	$savebalance = getdbosql('db_balances', "name='{$exchange}'");
	if (is_object($savebalance)) {
		$savebalance->balance = 0;
		$savebalance->save();
	}

	// bter only returns non-zero balances
	if ($updatebalances) dborun("UPDATE markets SET balance=0 WHERE name='{$exchange}'");

	foreach($balances['available_funds'] as $symbol => $available)
	{
		if ($symbol == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance->available;
				$savebalance->onsell = arraySafeVal($balances['locked_funds'],$symbol,0);
				$savebalance->save();
			}
			continue;
		}

		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>$symbol)
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='{$exchange}'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $available;
				$market->ontrade = arraySafeVal($balances['locked_funds'],$symbol,0);
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$flushall = rand(0, 8) == 0;
	if($quick) $flushall = false;
}
