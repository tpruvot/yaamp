<?php
$user = getuserparam(getparam('address'));
if (!$user) return;

$step = 15 * 60;
$t = time() - 24 * 60 * 60;

$stats = getdbolist('db_balanceuser', "time>$t and userid=$user->id order by time");
echo '[[';

for ($i = $t + $step, $j = 0;$i < time();$i += $step)
{
    if ($i != $t + $step) echo ',';
    $m = 0;

    if (isset($stats[$j]) && $i > $stats[$j]->time)
    {
        $m = bitcoinvaluetoa($stats[$j]->balance);
        $j++;
    }

    $d = date('Y-m-d H:i:s', $i);
    echo "[\"$d\",$m]";

}

echo '],[';

for ($i = $t + $step, $j = 0;$i < time();$i += $step)
{
    if ($i != $t + $step) echo ',';
    $m = 0;

    if (isset($stats[$j]) && $i > $stats[$j]->time)
    {
        $m = bitcoinvaluetoa($stats[$j]->pending);
        $j++;
    }

    $d = date('Y-m-d H:i:s', $i);
    echo "[\"$d\",$m]";

}

echo ']]';
