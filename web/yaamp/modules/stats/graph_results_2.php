<?php

$algo = user()->getState('yaamp-algo');

$t = time() - 48 * 60 * 60;
$stats = getdbolist('db_hashstats', "time>$t and algo=:algo", array(':algo' => $algo));

$data = array();
foreach ($stats as $i => $n) {
    $e = bitcoinvaluetoa($n->earnings * 24);
    $d = date('Y-m-d H:i:s', $n->time);

    $data[] = array($d, (float)$e);
}

if (count($data) === 0) {
    $data = array(array());
}

echo json_encode($data);