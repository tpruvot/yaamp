<?php

$defaultalgo = user()->getState('yaamp-algo');

$rent = dboscalar("select rent from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$defaultalgo));
$rent = mbitcoinvaluetoa($rent);

$renter = getrenterparam(''.getparam('address'));

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>All started jobs ($defaultalgo) - Current Price $rent</div>";
echo "<div class='main-left-inner'>";

//echo "<table class='dataGrid2'>";
showTableSorter('maintable1');
echo "<thead>";
echo "<tr>";
//echo "<th width=80></th>";
echo "<th>Server</th>";
//echo "<th>Algo</th>";
echo "<th align=right>Max Price</th>";
echo "<th align=right>Max Hash</th>";
echo "<th align=right>Hash*</th>";
echo "<th align=right>Diff</th>";
echo "</tr>";
echo "</thead><tbody>";

$list = getdbolist('db_jobs', "ready and algo=:algo order by price desc, time", array(':algo'=>$defaultalgo));
foreach($list as $job)
{
	$hashrate_bad = yaamp_job_rate_bad($job->id);
	$hashrate = yaamp_job_rate($job->id)+$hashrate_bad;

	$title_percent = '';
	if($hashrate_bad)
	{
		$percent = round($hashrate_bad/$hashrate*100, 1).'%';
		$hashrate_bad = Itoa2($hashrate_bad).'h/s';

		$title_percent = "Rejected $hashrate_bad ($percent)";
	}

	$hashrate = $hashrate? Itoa2($hashrate).'h/s': '';
	$maxhash = $job->speed? Itoa2($job->speed).'h/s': '';

	$title = controller()->admin? "-o stratum+tcp://$job->host:$job->port -u $job->username -p $job->password": '';

	$servername = substr($job->host, 0, 22);
	$price = mbitcoinvaluetoa($job->price);

	$diff = $job->difficulty>0? round($job->difficulty, 3): '';
	$d = datetoa2($job->time);

	if($job->active)
		echo "<tr class='ssrow' style='background-color: #dfd'>";
	else
		echo "<tr class='ssrow'>";

	if($renter && $renter->id == $job->renterid)
		echo "<td title='$title'>$job->host</td>";
	else
		echo "<td title='$title'></td>";

//	echo "<td>$d</td>";
//	echo "<td>$job->algo</td>";

	echo "<td align=right>$price</td>";
	echo "<td align=right>$maxhash</td>";
	echo "<td align=right title='$title_percent'>$hashrate</td>";
	echo "<td align=right>$diff</td>";
	echo "</tr>";

	$style_ext = '';	//$job->active? 'background-color: #dfd;': '';
	echo "<tr id='graph_placeholder_job-$job->id' style='display: none; $style_ext'><td colspan=8>";
	echo "<div id='graph_results_job-$job->id' style='height: 240px;'></div>";
	echo "</td></tr>";
}

echo "</tbody></table>";

echo "<p style='font-size: .8em'>
	&nbsp;* approximate from the last 5 minutes submitted shares<br>
	&nbsp;** price in mBTC/MH/day (GH/day for sha and blake algos)<br>
	</p>";

echo "<br>";
echo "</div></div><br>";








