<?php

$defaultalgo = user()->getState('yaamp-algo');

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Renting Status</div>";
echo "<div class='main-left-inner'>";

//echo "<table class='dataGrid2'>";
showTableSorter('maintable3');
echo "<thead>";
echo "<tr>";
echo "<th>Algo</th>";
echo "<th align=right>Jobs</th>";
echo "<th align=right>Total</th>";
//echo "<th>For Rent**</th>";
echo "<th align=right>Rented</th>";
echo "<th></th>";
echo "<th align=right>Available</th>";
//echo "<th>Paying</th>";
echo "<th align=right>Current Price</th>";
echo "</tr>";
echo "</thead>";

$algos = array();
foreach(yaamp_get_algos() as $algo)
{
	$algo_norm = yaamp_get_algo_norm($algo);

	$price = controller()->memcache->get_database_scalar("current_price-$algo",
			"select price from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));

	$norm = $price*$algo_norm;
	$norm = take_yaamp_fee($norm, $algo);

	$algos[] = array($norm, $algo);
}

function cmp($a, $b)
{
	return $a[0] < $b[0];
}

usort($algos, 'cmp');

foreach($algos as $item)
{
	$norm = $item[0];
	$algo = $item[1];

	$count1 = getdbocount('db_jobs', "algo=:algo and ready and active", array(':algo'=>$algo));
	$count2 = getdbocount('db_jobs', "algo=:algo and ready", array(':algo'=>$algo));

	$total = yaamp_pool_rate($algo);
	$hashrate = yaamp_pool_rate_rentable($algo);
	$hashrate_jobs = yaamp_rented_rate($algo);

	$hashrate = min($total, $hashrate);
	$hashrate_jobs = min($hashrate, $hashrate_jobs);

	$available = $hashrate - $hashrate_jobs;
	$percent = $hashrate_jobs && $hashrate? '('.round($hashrate_jobs/$hashrate*100, 1).'%)': '';

	$hashrate_jobs = $hashrate_jobs>0? Itoa2($hashrate_jobs).'h/s': '';
	$available = $available>0? Itoa2($available).'h/s': '';
	$hashrate = $hashrate>0? Itoa2($hashrate).'h/s': '';
	$total = $total>0? Itoa2($total).'h/s': '';

	$renting = controller()->memcache->get_database_scalar("current_renting-$algo",
		"select rent from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));
	$renting = mbitcoinvaluetoa($renting);

	if($defaultalgo == $algo)
		echo "<tr style='cursor: pointer; background-color: #e0d3e8;' onclick='javascript:select_algo(\"$algo\")'>";
	else
		echo "<tr style='cursor: pointer' class='ssrow' onclick='javascript:select_algo(\"$algo\")'>";

	echo "<td><b>$algo</b></td>";
	echo "<td align=right style='font-size: .9em;'>$count1 / $count2</td>";
//	echo "<td align=right style='font-size: .9em;'>$total</td>";
	echo "<td align=right style='font-size: .9em;' title='pool hashrate $total'>$hashrate</td>";
	echo "<td align=right style='font-size: .9em;'>$hashrate_jobs</td>";
	echo "<td align=right style='font-size: .8em;'>$percent</td>";
	echo "<td align=right style='font-size: .9em;'>$available</td>";
//	echo "<td align=right style='font-size: .9em;'>$price</td>";
	echo "<td align=right style='font-size: .9em;'><b>$renting</b></td>";
	echo "</tr>";
}

echo "</table>";

echo "<p style='font-size: .8em'>
		&nbsp;* values in mBTC/MH/day (GH/day for sha and blake algos)<br>
		&nbsp;** only hashpower with extranonce.subscribe or reconnect support can be rented<br>
		</p>";

echo "</div></div><br>";






