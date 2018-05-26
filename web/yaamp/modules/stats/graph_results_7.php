<?php

$algo = user()->getState('yaamp-algo');

$s = 24*60*60;
$t = time() - 60*24*60*60;
$stats = getdbolist('db_hashstats', "time>$t and algo=:algo", array(':algo'=>$algo));

$algo_unit_factor = yaamp_algo_mBTC_factor($algo);

$res = array();
$first = 0;
foreach($stats as $n)
{
	$i = floor($n->time/$s)*$s;
	if(!$first) $first = $i;

	if(!isset($res[$i]))
		$res[$i] = 0;

	$res[$i] += $n->hashrate/24;
}

$data = array();

foreach($res as $i=>$n)
{
	$m = round($n/(1000000*$algo_unit_factor), 3);
	$d = date('Y-m-d H:i:s', $i);
	$data[] = array($d, $m);
}

echo json_encode($data);
