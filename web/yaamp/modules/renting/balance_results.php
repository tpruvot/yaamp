<?php
$renter = getrenterparam('' . getparam('address'));
if (!$renter) return;

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Deposit $renter->address</div>";
echo "<div class='main-left-inner'>";

if (!YAAMP_RENTAL) echo "<p style='font-size: 1.2em; font-weight: bold; color: red;'>Renting is temporarily disabled.</p>";

echo "<table cellspacing=10>";

$balance = bitcoinvaluetoa($renter->balance);
$unconfirmed = bitcoinvaluetoa($renter->unconfirmed);
$spent = bitcoinvaluetoa($renter->spent);

echo "<tr><td>Deposit Address</td><td colspan=2><span style='font-family: monospace; background-color: #eee;'>$renter->address</span></td></tr>";
echo "<tr><td>Balance</td><td><a href='javascript:main_renter_tx()' target=yaamp_tx>$balance BTC</a></td>";

if ($renter->balance >= 0.001) echo "<td><input type='button' value='Withdraw' class='main-submit-button' onclick='javascript:yaamp_withdraw()'></td>";
else if ($renter->balance > 0) echo "<td><span style='font-size: .8em;'>(withdraw minimum 0.001)</span></td>";

echo "</tr>";

if ($unconfirmed > 0)
{
    echo "<tr><td>Unconfirmed</td><td>$unconfirmed BTC";
    echo "<span style='font-size: .8em;'> (waiting for 1 confirmation)</span>";
    echo "</td></tr>";
}

if (controller()->admin)
{
    echo "<tr><td>Spent</td><td>$spent BTC</td>
		<td><input type='button' value='Reset' class='main-submit-button' onclick='javascript:reset_spent()'></td></tr>";

    if ($renter->id == 7)
    {
        // 		$balance = $renter->custom_balance - $renter->custom_start;
        $profit = $renter->custom_start + $renter->custom_balance - $spent;

        $start = bitcoinvaluetoa($renter->custom_start);
        $balance = bitcoinvaluetoa($renter->custom_balance);
        $profit = bitcoinvaluetoa($profit);

        echo "<tr><td>Received</td><td>$start BTC</td></tr>";
        echo "<tr><td>Unpaid</td><td>$balance BTC</td></tr>";
        echo "<tr><td>Profit</td><td>$profit BTC</td></tr>";
    }
}

echo "</table><br>";

echo "<a style='margin: 10px;' href='/renting/settings'><b>Settings</b></a>";
echo "<a href='/renting/logout'><b>Logout</b></a> ";

echo "<br></div></div><br>";

////////////////////////////////////////////////////////////////////////////////////////////////////////
$list = getdbolist('db_rentertxs', "renterid=$renter->id order by time desc limit 5");
if (count($list) == 0) return;

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Last 5 transactions $renter->address</div>";
echo "<div class='main-left-inner'>";

echo "<table class='dataGrid2'>";

echo "<thead>";
echo "<tr>";
echo "<th align=right>Time</th>";
echo "<th align=right>Type</th>";
echo "<th align=right>Amount</th>";
echo "<th>Tx</th>";
echo "</tr>";
echo "</thead>";

foreach ($list as $tx)
{
    $d = datetoa2($tx->time);
    $amount = bitcoinvaluetoa($tx->amount);

    echo "<tr class='ssrow'>";

    echo "<td align=right><b>$d ago</b></td>";
    echo "<td align=right title='$tx->address'>$tx->type</td>";
    echo "<td align=right><b>$amount</b></td>";

    if (strlen($tx->tx) > 32)
    {
        $tx_show = substr($tx->tx, 0, 36) . '...';
        $txurl = "https://blockchain.info/tx/$tx->tx";
        echo "<td style='font-family: monospace;'><a href='$txurl' target=_blank>$tx_show</a></td>";
    }
    else echo "<td>$tx->tx</td>";

    echo "</tr>";
}

echo "</table><br>";
echo "</div>";

echo "</div><br>";
