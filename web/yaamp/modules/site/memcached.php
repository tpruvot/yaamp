<?php

echo "<a href='/site/memcached'>refresh</a><br>";

$memcache = controller()->memcache->memcache;
$a = memcache_get($this->memcache->memcache, 'url-map');

$res = array();

function cmp($a, $b)
{
	return $a[2] < $b[2];
}

if (!empty($a))
foreach($a as $url=>$n)
{
	$d = memcache_get($this->memcache->memcache, "$url-time");
	$avg = $d/$n;

	$res[] = array($url, $n, $d, $avg);
}

usort($res, 'cmp');

echo "<br><table class='dataGrid'>";
echo "<thead>";
echo "<tr>";
echo "<th>Url</th>";
echo "<th align=right>Count</th>";
echo "<th align=right>Time</th>";
echo "<th align=right>Average</th>";
echo "</tr>";
echo "</thead><tbody>";

if (!empty($res))
foreach($res as $item)
{
//	debuglog("$i => $n");

	$url = $item[0];
	$n = $item[1];
	$d = round($item[2], 3);
	$avg = round($item[3], 3);

	echo "<tr class='ssrow'>";
	echo "<td><a href='/$url'>$url</a></td>";
	echo "<td align=right>$n</td>";
	echo "<td align=right>$d</td>";
	echo "<td align=right>$avg</td>";
	echo "</tr>";
}

echo "</tbody></table>";




