<?php

$renter = getrenterparam(''.getparam('address'));
if(!$renter) return;

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Jobs $renter->address</div>";
echo "<div class='main-left-inner'>";

//echo "<table class='dataGrid2'>";
showTableSorter('maintable2');
echo "<thead>";
echo "<tr>";
echo "<th></th>";
echo "<th>Server</th>";
echo "<th>Algo</th>";
echo "<th align=right>Max Price</th>";
//echo "<th>Current<br>Price</th>";
echo "<th align=right>Max Hash</th>";
echo "<th align=right>Hash*</th>";
echo "<th align=right>Diff</th>";
echo "<th></th>";
echo "</tr>";
echo "</thead><tbody>";

$list = getdbolist('db_jobs', "renterid=$renter->id order by algo, price desc");
foreach($list as $job)
{
	$hashrate_bad = yaamp_job_rate_bad($job->id);
	$hashrate = yaamp_job_rate($job->id)+$hashrate_bad;

	$title_percent = '';
	if($hashrate_bad)
	{
		$percent = 100;
		if ($hashrate)
			$percent = round($hashrate_bad/$hashrate * 100, 1).'%';
		$hashrate_bad = Itoa2($hashrate_bad).'h/s';

		$title_percent = "Rejected $hashrate_bad ($percent)";
	}

	$hashrate = $hashrate? Itoa2($hashrate).'h/s': '';

	$maxhash = $job->speed? Itoa2($job->speed).'h/s': '';
	$title = "-o stratum+tcp://$job->host:$job->port -u $job->username -p $job->password";

	$servername = substr($job->host, 0, 22);
	$price = mbitcoinvaluetoa($job->price);

	$rent = dboscalar("select rent from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$job->algo));
	$rent = mbitcoinvaluetoa($rent);

	$diff = $job->difficulty>0? round($job->difficulty, 3): '';

	if($job->active)
		echo "<tr class='ssrow' style='background-color: #dfd'>";
	else
		echo "<tr class='ssrow'>";

	echo "<td title='Show details'><a href='javascript:show_job_graph($job->id)'>
		<img id='graph_toggle_job-$job->id' width=14 src='/images/plus2-78.png'></a></td>";

	$p = $job->percent? "($job->percent%)": '';

	echo "<td title='$title'>$servername</td>";
	echo "<td>$job->algo</td>";
	echo "<td align=right title='Current Price $rent'>$price $p</td>";
//	echo "<td>$rent</td>";
	echo "<td align=right>$maxhash</td>";
	echo "<td align=right title='$title_percent'>$hashrate</td>";
	echo "<td align=right>$diff</td>";

	echo "<td valign=center>";

	if(YAAMP_RENTAL)
	{
	if($job->ready)
		echo "<a title='pause job' href='/renting/jobs_stop?id=$job->id'><img height=16 src='/images/base/pause.png'></a>";
	else
		echo "<a title='start job' href='/renting/jobs_start?id=$job->id'><img height=16 src='/images/base/play.png'></a>";
	}

	echo "&nbsp;&nbsp;";
	echo "<a title='edit job' href='javascript:order_edit($job->id)'><img height=16 src='/images/base/edit.png'></a>";

	echo "</td></tr>";

	$style_ext = '';	//$job->active? 'background-color: #dfd;': '';

	echo "<tr id='graph_placeholder_job-$job->id' style='display: none; $style_ext'><td colspan=8>";
	echo "<div id='graph_results_job-$job->id' style='height: 240px;'></div>";
	echo "</td></tr>";
}

echo "</tbody></table>";

if(count($list) > 20 && !controller()->admin)
	;
else if($renter->balance > 0)
	echo "<br><button class='main-submit-button' onclick='javascript:order_new()'>New Job</button>";
else
	echo "<p style='padding: 10px;'>You have to fund your account by sending bitcoin to your deposit address before you can start creating mining jobs.</p>";

if(YAAMP_RENTAL)
{
echo " <button class='main-submit-button' onclick='javascript:window.location.href=\"/renting/jobs_startall\"'>Start All</button>";
echo " <button class='main-submit-button' onclick='javascript:window.location.href=\"/renting/jobs_stopall\"'>Stop All</button>";
}

echo "<br><br>";
echo "<p style='font-size: .8em'>
	Your started jobs will activate when the current price goes equal or below your max price.
	When a job is activated, you only pay the current price, not your max price.</p>";

echo "<p style='font-size: .8em'>
	You can start your jobs by clicking the play button and pause them with the pause button.</p>";

echo "<p style='font-size: .8em'>
	You may not get much hashpower if the difficulty of your pool is too low.</p>";

echo "<p style='font-size: .8em'>
		&nbsp;* approximate from the last 5 minutes submitted shares<br>
		&nbsp;** price in mBTC/MH/day (GH/day for sha and blake algos)<br>
		</p>";

echo "<br>";
echo "</div></div><br>";




