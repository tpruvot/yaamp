<?php

$algo = user()->getState('yaamp-algo');

$s = 24 * 60 * 60;
$t = time() - 60 * 24 * 60 * 60;
$stats = getdbolist('db_hashstats', "time>$t and algo=:algo", array(':algo' => $algo));

$algo_unit_factor = yaamp_algo_mBTC_factor($algo);

$res = array();
foreach ($stats as $n) {
    if (!$s) {
        continue;
    }

    $i = (int)floor($n->time / $s) * $s;

    if (!isset($res[$i])) {
        $res[$i] = array();
        $res[$i]['earnings'] = 0;
        $res[$i]['hashrate'] = 0;
    }

    $res[$i]['earnings'] += $n->earnings;
    $res[$i]['hashrate'] += $n->hashrate / 24;
}

$data = array();
foreach ($res as $i => $n) {
    if (!$n['hashrate']) {
        continue;
    }

    $m = bitcoinvaluetoa($n['earnings'] * $algo_unit_factor * 1000000 / $n['hashrate']);
    $d = date('Y-m-d H:i:s', $i);

    $data[] = array($d, (float)$m);
}

if (count($data) === 0) {
    $data = array(array());
}

echo json_encode($data);