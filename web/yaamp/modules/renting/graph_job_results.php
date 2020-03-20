<?php
$percent = 16;
$step = 15 * 60;
$t = time() - 24 * 60 * 60;

$jobid = getparam('jobid');

$stats = getdbolist('db_hashrenter', "time>$t and jobid=:jobid order by time", array(
    ':jobid' => $jobid
));
$averages = array();

echo '[[';

for ($i = $t + $step, $j = 0;$i < time();$i += $step)
{
    if ($i != $t + $step) echo ',';
    $m = 0;

    if ($i + $step >= time())
    {
        $m = round(yaamp_job_rate($jobid) / 1000000, 3);
        //		debuglog("last $m");

    }

    else if (isset($stats[$j]) && $i > $stats[$j]->time)
    {
        $m = round($stats[$j]->hashrate / 1000000, 3);
        $j++;
    }

    $d = date('Y-m-d H:i:s', $i);
    echo "[\"$d\",$m]";

    $averages[] = array(
        $d,
        $m
    );
}

echo '],[';

$average = $averages[0][1];
foreach ($averages as $i => $n)
{
    if ($i) echo ',';

    $average = ($average * (100 - $percent) + $n[1] * $percent) / 100;
    $m = round($average, 3);

    echo "[\"{$n[0]}\",$m]";
}

echo '],[';

for ($i = $t + $step, $j = 0;$i < time();$i += $step)
{
    if ($i != $t + $step) echo ',';
    $m = 0;

    if ($i + $step >= time())
    {
        $m = round(yaamp_user_rate_bad($jobid) / 1000000, 3);
        //	debuglog("last $m");

    }

    else if (isset($stats[$j]) && $i > $stats[$j]->time)
    {
        $m = round($stats[$j]->hashrate_bad / 1000000, 3);
        $j++;
    }

    $d = date('Y-m-d H:i:s', $i);
    echo "[\"$d\",$m]";
}

echo ']]';
