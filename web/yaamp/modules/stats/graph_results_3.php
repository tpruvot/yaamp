<?php

$algo = user()->getState('yaamp-algo');

$t = time() - 48*60*60;
$stats = getdbolist('db_hashstats', "time>$t and algo=:algo", array(':algo'=>$algo));

$algo_unit_factor = yaamp_algo_mBTC_factor($algo);

echo '[';

foreach($stats as $i=>$n)
{
	$m = $n->hashrate ? bitcoinvaluetoa($n->earnings * 24 * $algo_unit_factor * 1000000/$n->hashrate): 0;

	if($i) echo ',';
	$d = date('Y-m-d H:i:s', $n->time);
	echo "[\"$d\",$m]";
}

echo ']';

