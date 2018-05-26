<?php

function doCoinsMarketsTrading($quick=false)
{
	$exchange = 'coinsmarkets';
	$updatebalances = true;

	if (exchange_get($exchange, 'disabled')) return;

	$balances = coinsmarkets_api_user('gettradinginfo');
	// todo: function which check success status and return key
	if (!coinsmarkets_result_valid($balances)) return;
	$balances = $balances['return'];
	if (!is_array($balances) || !isset($balances['funds'])) return;
	$balances = $balances['funds'];
	if (!is_array($balances) || empty($balances)) return;

	$savebalance = getdbosql('db_balances', "name='{$exchange}'");
	if (is_object($savebalance)) {
		$savebalance->balance = 0;
		$savebalance->save();
	}

	foreach ($balances as $symbol => $amount) {
		if ($symbol == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $amount;
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
				$market->balance = $amount;
				$market->balancetime = time();
				$market->save();

				$checked_today = cache()->get($exchange.'-deposit_address-check-'.$symbol);
				if ($coin->installed && !$checked_today) {
					sleep(1);
					$data = coinsmarkets_api_user('depositaddress', $symbol);
					if (!coinsmarkets_result_valid($data)) continue;
					$result = $data['return'];
					$deposit_address = arraySafeVal($result,'address');
					if (!empty($deposit_address) && $deposit_address != $market->deposit_address) {
						debuglog("$exchange: updated $symbol deposit address $deposit_address");
						$market->save();
					}
					cache()->set($exchange.'-deposit_address-check-'.$symbol, time(), 24*3600);
				}
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	// not ready for auto trade ... wont do it
}
