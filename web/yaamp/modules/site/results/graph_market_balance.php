<?php

//$id = getiparam('id');
if (!$id) return;

$coin = getdbo('db_coins', $id);
if (!$coin) return;

$t = time() - 7*24*60*60;

$markets = dbolist("SELECT M.id, M.name, M.priority, MIN(MH.balance) AS min, MAX(MH.balance) AS max
	FROM market_history MH INNER JOIN markets M ON M.id = MH.idmarket
	WHERE MH.idcoin=$id AND MH.time>$t AND NOT M.disabled
	GROUP BY M.id, M.name, M.priority HAVING max > 0
	ORDER BY M.priority DESC, M.name");

$stackedMax = (double) 0;

$series = array();
foreach ($markets as $m) {

	$market = getdbo('db_markets', $m['id']);

	$stats = getdbolist('db_market_history', "time>$t AND idmarket={$market->id} ORDER BY time");

	$max = 0;
	foreach($stats as $histo) {
		$d = date('Y-m-d H:i', $histo->time);
		$series[$m['name']][] = array($d, (double) bitcoinvaluetoa($histo->balance));

		$max = max($max, $histo->balance);
	}

	$stackedMax += $max;
}

// "yiimp" balance

$stats = getdbolist('db_market_history', "time>$t AND idcoin={$id} AND idmarket IS NULL ORDER BY time");

$max = 0;
foreach($stats as $histo) {
	$d = date('Y-m-d H:i', $histo->time);
	$series[YAAMP_SITE_NAME][] = array($d, (double) bitcoinvaluetoa($histo->balance));
	$max = max($max, $histo->balance);
}
$stackedMax += $max;

// Stacked graph specific : seems to require same amount of points :/
$max = 0; $seriefull = '';
foreach ($series as $name => $serie) {
	if (count($serie) > $max) $seriefull = $name;
	$max = max($max, count($serie));
}
foreach ($series as $name => $serie) {
	if ($seriefull && count($serie) < $max) {
		$first_dt = $serie[0][0];
		$fill_start = ($first_dt > $series[$seriefull][0][0]);
	}
	for ($i = count($serie), $n = 0; $i < $max; $i++, $n++) {
		if ($seriefull == '') {
			$dt = $serie[0][0];
			array_unshift($series[$name], array($dt, 0));
			continue;
		}
		if ($fill_start) {
			if ($series[$seriefull][$n][0] >= $first_dt) {
				array_unshift($series[$name], array($dt, 0));
				$fill_start = false;
			} else {
				$dt = $series[$seriefull][$n][0];
				array_unshift($series[$name], array($dt, 0));
			}
		} else {
			$dt = $series[$seriefull][$i][0];
			$last = end($series[$name]);
			$series[$name][] = array($dt, $last[1]);
		}
	}
}

echo json_encode(array(
	'data'=>array_values($series),
	'labels'=>array_keys($series),
	'rangeMin'=> (double) 0.0,
	'rangeMax'=> ($stackedMax * 1.10),
));
