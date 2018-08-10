<?php

require dirname(__FILE__).'/../../ui/lib/pageheader.php';

$renter = getrenterparam(''.getparam('address'));
if(!$renter) return;

$this->pageTitle = "$renter->address | yiimp";

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Transactions from $renter->address</div>";
echo "<div class='main-left-inner'>";

echo "<table class='dataGrid2'>";
echo "<thead class=''>";
echo "<tr>";
echo "<th>Time</th>";
echo "<th>Amount</th>";
echo "<th>Confirmations</th>";
echo "<th>Tx</th>";
echo "</tr>";
echo "</thead><tbody>";

$btc = getdbosql('db_coins', "symbol='BTC'");
if(!$btc) return;

$remote = new WalletRPC($btc);
$ts = $remote->listtransactions(yaamp_renter_account($renter), 10);

$res_array = array();
foreach($ts as $val)
{
	$t = $val['time'];
	if($t<$renter->created) continue;
	$res_array[$t] = $val;
}

krsort($res_array);
$total = 0;

foreach($res_array as $transaction)
{
	if($transaction['category'] != 'receive') continue;
	$d = datetoa2($transaction['time']);

	echo "<tr class='ssrow'>";
	echo "<td><b>$d</b></td>";
	echo "<td>{$transaction['amount']}</td>";

	if(isset($transaction['confirmations']))
		echo "<td>{$transaction['confirmations']}</td>";
	else
		echo "<td></td>";

	echo "<td>";
	if(isset($transaction['txid']))
		echo "<span style='font-family: monospace;'><a href='https://blockchain.info/tx/{$transaction['txid']}' target=_blank>{$transaction['txid']}</a></span>";

	echo "</td>";
	echo "</tr>";

	$total += $transaction['amount'];
}

echo "</tbody>";

echo "<tr><td>total</td><td colspan=3>$total</td></tr>";

echo "</table><br>";
echo "</div></div><br>";




