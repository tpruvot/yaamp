<?php

function doKrakenTrading($quick=false)
{
	$exchange = 'kraken';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	$balances = kraken_api_user('Balance');
	if(!$balances || !is_array($balances)) return;

	//$total = kraken_api_user('TradeBalance', array('asset'=>'XXBT'));
	//if(!$total || !is_array($total)) return;

	foreach($balances as $symbol => $balance)
	{
		if ($symbol == 'BTC') {
			$db_balance = getdbosql('db_balances', "name='$exchange'");
			if ($db_balance) {
				$db_balance->balance = $balance;
				//$db_balance->onsell = (double) $total['result']['tb'] - $balance;
				$db_balance->save();
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
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $balance;
				$market->balancetime = time();
				$market->save();
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	$flushall = rand(0, 8) == 0;
	if($quick) $flushall = false;

}
