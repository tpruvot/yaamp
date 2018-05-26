<?php

$deposit = user()->getState('yaamp-deposit');
echo "<a href='/renting/admin'>refresh</a><br>";

////////////////////////////////////////////////////////////////////////////////////////////////////////

$list = getdbolist('db_rentertxs', "1 order by time desc limit 10");
if(count($list) == 0) return;

echo "<table class='dataGrid'>";

echo "<thead>";
echo "<tr>";
echo "<th>ID</th>";
echo "<th>Address</th>";
echo "<th align=right>Time</th>";
echo "<th align=right>Type</th>";
echo "<th align=right>Amount</th>";
echo "<th>Tx</th>";
echo "</tr>";
echo "</thead>";

foreach($list as $tx)
{
	$d = datetoa2($tx->time);
	$amount = bitcoinvaluetoa($tx->amount);

	$renter = getdbo('db_renters', $tx->renterid);
	if(!$renter) continue;

	echo "<tr class='ssrow'>";

	echo "<td>$renter->id</td>";
	echo "<td><a href='/renting?address=$renter->address'>$renter->address</a></td>";

	echo "<td align=right><b>$d ago</b></td>";
	echo "<td align=right title='$tx->address'>$tx->type</td>";
	echo "<td align=right><b>$amount</b></td>";

	if(strlen($tx->tx) > 32)
	{
		$tx_show = substr($tx->tx, 0, 36).'...';
		$txurl = "https://blockchain.info/tx/$tx->tx";
		echo "<td style='font-family: monospace;'><a href='$txurl' target=_blank>$tx_show</a></td>";
	}
	else
		echo "<td>$tx->tx</td>";

	echo "</tr>";
}

echo "</table><br>";

/////////////////////////////////////////////////////////////////////////////////////////////////////////

echo "<br><table class='dataGrid'>";
echo "<thead>";
echo "<tr>";
echo "<th>Renter</th>";
echo "<th>Address</th>";
echo "<th>Email</th>";
echo "<th>Spent</th>";
echo "<th>Balance</th>";
echo "<th>Unconfirmed</th>";
echo "<th>Jobs</th>";
echo "<th>Active</th>";
echo "</tr>";
echo "</thead><tbody>";

$list = getdbolist('db_renters', "balance>0 order by balance desc");
foreach($list as $renter)
{
	$count = dboscalar("select count(*) from jobs where renterid=$renter->id");
	$active = dboscalar("select count(*) from jobs where renterid=$renter->id and active");

	if($deposit == $renter->address)
		echo "<tr class='ssrow' style='background-color: #dfd'>";
	else
		echo "<tr class='ssrow'>";

	echo "<td>$renter->id</td>";
	echo "<td><a href='/renting?address=$renter->address'>$renter->address</a></td>";
	echo "<td>$renter->email</td>";
	echo "<td>$renter->spent</td>";
	echo "<td>$renter->balance</td>";
	echo "<td>$renter->unconfirmed</td>";
	echo "<td>$count</td>";
	echo "<td>$active</td>";
	echo "</tr>";
}

echo "</tbody></table>";

/////////////////////////////////////////////////////////////////////////////

echo "<br><table class='dataGrid'>";
echo "<thead>";
echo "<tr>";
echo "<th>Renter</th>";
echo "<th>Job</th>";
echo "<th>Address</th>";
echo "<th>Algo</th>";
echo "<th>Host</th>";
echo "<th>Max Price</th>";
echo "<th>Max Hash</th>";
echo "<th>Current Hash</th>";
echo "<th>Difficulty</th>";
echo "<th>Ready</th>";
echo "<th>Active</th>";
echo "</tr>";
echo "</thead><tbody>";

$list = getdbolist('db_jobs', "ready");
foreach($list as $job)
{
	$hashrate = yaamp_job_rate($job->id);
	$hashrate = $hashrate? Itoa2($hashrate).'h/s': '';

	$speed = Itoa2($job->speed).'h/s';

	$renter = getdbo('db_renters', $job->renterid);
	if(!$renter) continue;

	if($deposit == $renter->address)
		echo "<tr class='ssrow' style='background-color: #dfd'>";
	else
		echo "<tr class='ssrow'>";

	echo "<td>$job->renterid</td>";
	echo "<td>$job->id</td>";
	echo "<td><a href='/renting?address=$renter->address'>$renter->address</a></td>";
	echo "<td><a href='/site/gomining?algo=$job->algo'>$job->algo</td>";
	echo "<td>$job->host:$job->port</td>";
	echo "<td>$job->price</td>";
	echo "<td>$speed</td>";
	echo "<td>$hashrate</td>";
	echo "<td>$job->difficulty</td>";
	echo "<td>$job->ready</td>";
	echo "<td>$job->active</td>";
	echo "</tr>";
}

echo "</tbody></table>";

echo "<br><br><br><br><br><br><br><br><br><br>";


