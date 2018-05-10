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
	if (!kucoin_result_valid($data)) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");

	if (is_array($data->data))
	foreach($data->data as $balance)
	{
		if ($balance->coinType == 'BTC') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance->balance;
				$savebalance->onsell = $balance->freezeBalance;
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

				$checked_today = cache()->get($exchange.'-deposit_address-check-'.$coin->symbol);
				if ($coin->installed && !$checked_today) {
					sleep(1);
					$obj = kucoin_api_user("account/{$coin->symbol}/wallet/address");
					if (!kucoin_result_valid($obj)) continue;
					$result = $obj->data;
					$deposit_address = objSafeVal($result,'address');
					if (!empty($deposit_address) && $deposit_address != $market->deposit_address) {
						debuglog("$exchange: updated {$coin->symbol} deposit address $deposit_address");
						$market->save();
					}
					cache()->set($exchange.'-deposit_address-check-'.$coin->symbol, time(), 24*3600);
				}
			}
		}
	}

	if (!YAAMP_ALLOW_EXCHANGE) return;

	// real trading, todo..
}
