<?php

require dirname(__FILE__).'/../../ui/lib/pageheader.php';

$user = getuserparam(getparam('address'));
if(!$user) return;

$this->pageTitle = $user->username.' | '.YAAMP_SITE_NAME;

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Transactions to $user->username</div>";
echo "<div class='main-left-inner'>";

$list = getdbolist('db_payouts', "account_id={$user->id} ORDER BY time DESC");

echo '<table class="dataGrid2">';

echo "<thead>";
echo "<tr>";
echo "<th></th>";
echo "<th>Time</th>";
echo "<th align=right>Amount</th>";
echo "<th>Tx</th>";
echo "</tr>";
echo "</thead>";

$bitcoin = getdbosql('db_coins', "symbol='BTC'");
$coin = ($bitcoin && $user->coinid == $bitcoin->id) ? $bitcoin : getdbo('db_coins', $user->coinid);

$total = 0;
foreach($list as $payout)
{
	$d = datetoa2($payout->time);
	$amount = bitcoinvaluetoa($payout->amount);

	echo "<tr class='ssrow'>";
	echo "<td width=18></td>";
	echo "<td><b>$d ago</b></td>";

	echo "<td align=right><b>$amount</b></td>";

	$url = $coin->createExplorerLink($payout->tx, array('txid'=>$payout->tx), array('target'=>'_blank'));
	echo '<td style="font-family: monospace;">'.$url.'</td>';

	echo "</tr>";
	$total += $payout->amount;
}

$total = bitcoinvaluetoa($total);

echo "<tr class='ssrow' style='border-top: 2px solid #eee;'>";
echo "<td width=18></td>";
echo "<td><b>Total</b></td>";

echo "<td align=right><b>$total</b></td>";
echo "<td></td>";

echo "</tr>";

echo "</table><br>";
echo "</div></div><br>";


