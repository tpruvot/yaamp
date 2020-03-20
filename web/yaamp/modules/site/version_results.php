<?php
if (isset($_GET['algo'])) user()->setState('yaamp-algo', $_GET['algo']);

$algo = user()->getState('yaamp-algo');
$target = yaamp_hashrate_constant($algo);
$interval = yaamp_hashrate_step();
$delay = time() - $interval;

echo "<br><table class='dataGrid'>";
echo "<thead>";
echo "<tr>";
echo "<th>Version</th>";
echo "<th>Workers</th>";
echo "<th>Hashrate</th>";
echo "<th>Bad</th>";
echo "<th></th>";
echo "</tr>";
echo "</thead><tbody>";

$versions = dbolist("select version, count(*) as c from workers where algo=:algo group by version", array(
    ':algo' => $algo
));
foreach ($versions as $item)
{
    $version = $item['version'];
    $count = $item['c'];

    $hashrate = dboscalar("select sum(difficulty) * $target / $interval / 1000 from shares where valid and time>$delay and
		workerid in (select id from workers where algo=:algo and version=:version)", array(
        ':algo' => $algo,
        ':version' => $version
    ));

    $invalid = dboscalar("select sum(difficulty) * $target / $interval / 1000 from shares where not valid and time>$delay and
		workerid in (select id from workers where algo=:algo and version=:version)", array(
        ':algo' => $algo,
        ':version' => $version
    ));

    $percent = $hashrate ? round($invalid * 100 / $hashrate, 3) : 0;
    $hashrate = Itoa2($hashrate) . 'h/s';
    $invalid = Itoa2($invalid) . 'h/s';

    echo "<tr class='ssrow'>";
    echo "<td><b>$version</b></td>";
    echo "<td>$count</td>";
    echo "<td>$hashrate</td>";
    echo "<td>$invalid</td>";
    echo "<td align=right>{$percent}%</td>";
    echo "</tr>";
}

echo "</tbody></table>";
