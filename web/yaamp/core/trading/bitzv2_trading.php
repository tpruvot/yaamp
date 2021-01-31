<?php
 function doBitzCancelOrder($OrderID=false)
{
	if(!$OrderID) return;
 	// todo
}
 function doBitzTrading($quick=false)
{
	if (empty(EXCH_BITZ_KEY) || empty(EXCH_BITZ_SECRET)) return;
	
	$exchange = 'bitz';
	$updatebalances = true;
 	if (exchange_get($exchange, 'disabled')) return;
  
	$bitz = new bitz();
	$data = json_decode($bitz->getUserAssets())->data->info;
 	if (!is_array($data) || empty($data)) return;
 	$savebalance = getdbosql('db_balances', "name='$exchange'");
 	foreach($data as $balance)
	{
		if ($balance->name == 'btc') {
			if (is_object($savebalance)) {
				$savebalance->balance = $balance->over;
				$savebalance->onsell = $balance->lock;
				$savebalance->save();
			}
			continue;
		}
 		if ($updatebalances) {
			// store available balance in market table
			$coins = getdbolist('db_coins', "symbol=:symbol OR symbol2=:symbol",
				array(':symbol'=>strtoupper($balance->name))
			);
			if (empty($coins)) continue;
			foreach ($coins as $coin) {
				$market = getdbosql('db_markets', "coinid=:coinid AND name='$exchange'", array(':coinid'=>$coin->id));
				if (!$market) continue;
				$market->balance = $balance->over;
				$market->ontrade = $balance->lock;
				$market->balancetime = time();
				$market->save();
			}
		}
	}
 	if (!YAAMP_ALLOW_EXCHANGE) return;
 	// real trading, todo..
}
