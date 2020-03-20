<?php

function WriteBoxHeader($title)
{
    echo "<div class='main-left-box'>";
    echo "<div class='main-left-title'>$title</div>";
    echo "<div class='main-left-inner'>";
}

$algo = user()->getState('yaamp-algo');

$target   = yaamp_hashrate_constant($algo);
$interval = yaamp_hashrate_step();
$delay    = time() - $interval;

$total_workers    = getdbocount('db_workers', "algo=:algo", array(
    ':algo' => $algo
));
$total_extranonce = getdbocount('db_workers', "algo=:algo and subscribe", array(
    ':algo' => $algo
));
$total_hashrate   = controller()->memcache->get_database_scalar("current_hashrate-$algo",
//"SELECT SUM(difficulty) * $target / $interval / 1000 FROM shares WHERE valid AND time>$delay AND algo=:algo", array(':algo'=>$algo)
    "SELECT hashrate FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1", array(
    ':algo' => $algo
));
$total_invalid    = !$this->admin ? 0 : controller()->memcache->get_database_scalar("current_hashrate_bad-$algo",
//"SELECT SUM(difficulty) * $target / $interval / 1000 FROM shares WHERE NOT valid AND time>$delay AND algo=:algo", array(':algo'=>$algo)
    "SELECT hashrate_bad FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1", array(
    ':algo' => $algo
));

WriteBoxHeader("Miners Version ($algo)");

//showTableSorter('maintable2');
echo <<<end
<br/>
<table id="maintable2" class="dataGrid2">
<thead>
<tr>
<th>Version</th>
<th align="right">Count</th>
<th align="right">Donators</th>
<th align="right" title="* Extranonce Subscribe">ES</th>
<th align="right">Percent</th>
<th align="right">Hashrate*</th>
<th align="right" title="Rate per miner">Avg</th>
<th align="right" class="rejects" style="display:none;">Reject</th>
</tr>
</thead><tbody>
end;

$error_tab = array(
    20 => 'Invalid nonce size',
    21 => 'Invalid job id',
    22 => 'Duplicate share',
    23 => 'Invalid time rolling',
    24 => 'Invalid extranonce2 size',
    25 => 'Invalid share',
    26 => 'Low difficulty share',
    27 => 'Invalid extranonce'
);

$total_donators = 0;

$versions = dbolist("select version, count(*) as c, sum(subscribe) as s from workers where algo=:algo group by version order by c desc", array(
    ':algo' => $algo
));
foreach ($versions as $item) {
    $version    = $item['version'];
    $count      = $item['c'];
    $extranonce = $item['s'];

    $hashrate = controller()->memcache->get_database_scalar("miners-valid-$algo-v$version", "SELECT sum(difficulty) * $target / $interval / 1000 FROM shares WHERE valid AND time>$delay
         AND workerid IN (SELECT id FROM workers WHERE algo=:algo and version=:version)", array(
        ':algo' => $algo,
        ':version' => $version
    ));

    if (!$hashrate && !$this->admin)
        continue;

    $invalid = !$total_invalid ? 0 : controller()->memcache->get_database_scalar("miners-invalid-$algo-v$version", "SELECT SUM(difficulty) * $target / $interval / 1000 FROM shares WHERE not valid AND time>$delay
         AND workerid IN (SELECT id FROM workers WHERE algo=:algo AND version=:version)", array(
        ':algo' => $algo,
        ':version' => $version
    ));

    $title = '';
    foreach ($error_tab as $i => $s) {
        $invalid2 = !$total_invalid ? 0 : controller()->memcache->get_database_scalar("miners-invalid-$algo-v$version-err$i", "SELECT sum(difficulty) * $target / $interval / 1000 from shares WHERE error=$i AND time>$delay
            AND workerid in (SELECT id FROM workers WHERE algo=:algo AND version=:version)", array(
            ':algo' => $algo,
            ':version' => $version
        ));

        if ($invalid2) {
            $bad2 = round($invalid2 * 100 / ($hashrate + $invalid2), 2) . '%';
            $title .= "$bad2 - $s\n";
        }
    }

    $donators = dboscalar("SELECT COUNT(*) AS donators FROM workers W LEFT JOIN accounts A ON A.id = W.userid" . " WHERE W.algo=:algo AND W.version=:version AND A.donation > 0", array(
        ':algo' => $algo,
        ':version' => $version
    ));
    $total_donators += $donators;

    $percent = $total_hashrate && $hashrate ? round($hashrate * 100 / $total_hashrate, 2) . '%' : '';
    if (!$percent || $percent == '0%')
        $percent = '-';
    $bad = ($hashrate + $invalid) ? round($invalid * 100 / ($hashrate + $invalid), 1) . '%' : '';
    if (!$bad || $bad == '0%')
        $bad = '-';
    $avg      = intval($count) ? $hashrate / intval($count) : '';
    $avg      = $avg ? Itoa2($avg) . 'H/s' : '';
    $hashrate = $hashrate ? Itoa2($hashrate) . 'H/s' : '';
    $version  = substr($version, 0, 30);

    echo '<tr class="ssrow">';
    echo '<td><b>' . $version . '</b></td>';
    echo '<td align="right">' . $count . '</td>';
    echo '<td align="right">' . ($donators ? $donators : '-') . '</td>';
    echo '<td align="right">' . ($extranonce ? $extranonce : '-') . '</td>';
    if (floatval($percent) > 50)
        echo '<td align="right"><b>' . $percent . '</b></td>';
    else
        echo '<td align="right">' . $percent . '</td>';
    echo '<td align="right">' . $hashrate . '</td>';
    echo '<td align="right">' . $avg . '</td>';
    echo '<td align="right" class="rejects" style="display:none;" title="' . $title . '">' . $bad . '</td>';
    echo '</tr>';
}

echo "</tbody>";

$title = '';
foreach ($error_tab as $i => $s) {
    $invalid2 = !$total_invalid ? 0 : controller()->memcache->get_database_scalar("miners-invalid-$algo-err$i", "SELECT SUM(difficulty) * $target / $interval / 1000 FROM shares WHERE time>$delay AND algo=:algo AND error=$i " . "AND workerid IN (SELECT id FROM workers WHERE algo=:algo)", array(
        ':algo' => $algo
    ));

    if ($invalid2) {
        $bad2 = round($invalid2 * 100 / ($total_hashrate + $invalid2), 2);
        $title .= "$bad2 - $s\n";
    }
}

$bad            = ($total_hashrate + $total_invalid) && $total_invalid ? round($total_invalid * 100 / ($total_hashrate + $total_invalid), 1) . '%' : '';
$avg            = intval($total_workers) ? Itoa2($total_hashrate / intval($total_workers)) . 'H/s' : '';
$total_hashrate = Itoa2($total_hashrate) . 'H/s';

echo '<tr class="ssrow">';
echo '<th><b>Total</b></th>';
echo '<th align="right">' . $total_workers . '</th>';
echo '<th align="right">' . $total_donators . '</th>';
echo '<th align="right">' . $total_extranonce . '</th>';
echo '<th align="right"></th>';
echo '<th align="right">' . $total_hashrate . '</th>';
echo '<th align="right">' . $avg . '</th>';
echo '<th align="right" title="' . $title . '" class="rejects" style="display:none;">' . $bad . '</th>';
echo '</tr>';

echo "</table>";

echo "<p style='font-size: .8em'>
        &nbsp;* approximate from the last 5 minutes submitted shares<br>
        </p>";

echo "<br></div></div><br>";

if ($this->admin) {
    // show reject column
    echo '<script type="text/javascript">jQuery(".rejects").show();</script>';
}
