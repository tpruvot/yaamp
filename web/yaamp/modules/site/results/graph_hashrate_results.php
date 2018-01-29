<?php

/* Graph shown in Pool Tab, Last 24 Hours algo hashrate */

$percent = 16;
$algo = user()->getState('yaamp-algo');
$factor = yaamp_algo_mBTC_factor($algo); // 1000 sha (GH/s), 1 for normal MH/s

$step = 15*60;
$t = time() - 24*60*60;

$stats = getdbolist('db_hashrate', "time > $t AND algo=:algo ORDER BY time", array(':algo'=>$algo));
$averages = array();

$json = '';

for($i = 0; $i < 95-count($stats); $i++)
{
	$d = date('Y-m-d H:i:s', $t);
	$json .= "[\"$d\",0],";

	$averages[] = array($d, 0);
	$t += $step;
}

foreach($stats as $n)
{
	$r = $n->hashrate/1000000;
	$m = round($r / $factor, 3);

	$d = date('Y-m-d H:i:s', $n->time);
	$json .= "[\"$d\",$m],";

	$averages[] = array($d, $m);
}

echo '[['.rtrim($json,',').'],';
echo '[';

$json = '';
$average = $averages[0][1];
foreach($averages as $n)
{
	$average = ($average*(100-$percent) + $n[1]*$percent) / 100;
	$m = round($average, 3);

	$json .= "[\"{$n[0]}\",$m],";
}

// $a = 10;
// foreach($averages as $i=>$n)
// {
// 	if($i < $a) continue;

// 	$average = 0;
// 	for($j = $i-$a+1; $j<=$i; $j++)
// 		$average += $averages[$j][1]/$a;

// 	$m = round($average, 3);

// 	$json .= "[\"{$n[0]}\",$m]";
// }

echo rtrim($json,',');
echo ']]';






