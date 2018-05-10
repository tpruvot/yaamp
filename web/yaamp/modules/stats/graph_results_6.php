<?php

$algo = user()->getState('yaamp-algo');

$s = 4*60*60;
$t = time() - 7*24*60*60;
$stats = getdbolist('db_hashstats', "time>$t and algo=:algo", array(':algo'=>$algo));

$algo_unit_factor = yaamp_algo_mBTC_factor($algo);

$res = array();
$first = 0;
foreach($stats as $n)
{
	$i = floor($n->time/$s)*$s;
	if(!$first) $first = $i;

	if(!isset($res[$i]))
	{
		$res[$i] = array();
		$res[$i]['earnings'] = 0;
		$res[$i]['hashrate'] = 0;
	}

	$res[$i]['earnings'] += $n->earnings;
	$res[$i]['hashrate'] += $n->hashrate/24;
}

echo '[';

foreach($res as $i=>$n)
{
	$m = $n['hashrate']? bitcoinvaluetoa($n['earnings'] * $algo_unit_factor * 1000000 / $n['hashrate']): 0;

	if($i != $first) echo ',';
	$d = date('Y-m-d H:i:s', $i);
	echo "[\"$d\",$m]";
}

echo ']';


