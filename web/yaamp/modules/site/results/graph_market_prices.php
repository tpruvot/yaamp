<?php
//$id = getiparam('id');
if (!$id) return;

$coin = getdbo('db_coins', $id);
if (!$coin) return;

$t = time() - 7 * 24 * 60 * 60;

$markets = dbolist("SELECT M.id AS id, M.name, M.priority, MIN(MH.price) AS min, MAX(MH.price) AS max
	FROM market_history MH LEFT JOIN markets M ON M.id = MH.idmarket
	WHERE MH.idcoin=$id AND MH.time>$t AND NOT M.disabled AND M.name != 'stake'
	GROUP BY M.id, M.name, M.priority
	ORDER BY M.priority DESC, M.name");

$min = 999999999;
$max = 0;

$series = array();
foreach ($markets as $m)
{

    $market = getdbo('db_markets', $m['id']);

    $stats = getdbolist('db_market_history', "time>$t AND idmarket={$market->id} ORDER BY time");

    foreach ($stats as $histo)
    {
        $d = date('Y-m-d H:i', $histo->time);
        $series[$m['name']][] = array(
            $d,
            (double)bitcoinvaluetoa($histo->price)
        );
    }

    if ($histo && $market->pricetime && $market->pricetime > $histo->time)
    {
        $d = date('Y-m-d H:i', $market->pricetime);
        $series[$m['name']][] = array(
            $d,
            (double)bitcoinvaluetoa($market->price)
        );
    }

    $min = min($min, (double)$m['min']);
    $max = max($max, (double)$m['max']);
}

if ($min == 999999999)
{
    // empty
    $min = 0;
}

// "yiimp" price
$stats = getdbolist('db_market_history', "time>$t AND idcoin={$id} AND idmarket IS NULL ORDER BY time");
foreach ($stats as $histo)
{
    $d = date('Y-m-d H:i', $histo->time);
    $series[YAAMP_SITE_NAME][] = array(
        $d,
        (double)bitcoinvaluetoa($histo->price)
    );
    $max = max($max, $histo->price);
}

echo json_encode(array(
    'data' => array_values($series) ,
    'labels' => array_keys($series) ,
    'rangeMin' => (double)($min * 0.95) ,
    'rangeMax' => (double)($max * 1.05) ,
));
