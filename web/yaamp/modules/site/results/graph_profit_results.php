<?php
$percent = 16;

$step = 15 * 60;
$t = time() - 24 * 60 * 60;
$stats = getdbolist('db_stats', "time>$t order by time");

echo '[[';
foreach ($stats as $i => $n)
{
    $m = round($n->profit, 8);
    if ($i) echo ',';

    $d = date('Y-m-d H:i:s', $n->time);
    echo "[\"$d\",$m]";
}

echo ']]';
