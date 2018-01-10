<?php

function doKuCoinCancelOrder($OrderID=false)
{
	if(!$OrderID) return;

	// todo
}

function doKuCoinTrading($quick=false)
{
	$exchange = 'kucoin';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	$data = kucoin_api_user('account/balance');
	if (!is_object($data) || !isset($data->data)) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");

	if (is_array($data->data))
	foreach($data->data as $balance)
	{
		if ($balance->coinType == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance->balance;
				$savebalance->save();
			}
			continue;
		}

		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>$balance->coinType)
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets',
					"coinid=:coinid AND name='$exchange' ORDER BY balance"
					, array(':coinid'=>$coin->id)
				);
				if (!$market) continue;
				$market->balance = $balance->balance;
				$market->ontrade = $balance->freezeBalance;
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	// real trading, todo..
}
