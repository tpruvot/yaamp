<?php

function doBinanceCancelOrder($OrderID=false)
{
	if(!$OrderID) return;

	// todo
}

function doBinanceTrading($quick=false)
{
	$exchange = 'binance';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	$data = binance_api_user('account');
	if(!is_object($data) || empty($data->balances)) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");

	if (is_array($data->balances))
	foreach($data->balances as $balance)
	{
		if ($balance->asset == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance->free;
				$savebalance->onsell = $balance->locked;
				$savebalance->save();
			}
			continue;
		}

		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>$balance->asset)
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets',
					"coinid=:coinid AND name='$exchange' ORDER BY balance"
					, array(':coinid'=>$coin->id)
				);
				if (!$market) continue;
				$market->balance = $balance->free;
				$market->ontrade = $balance->locked;
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	// real trading, todo..
}
