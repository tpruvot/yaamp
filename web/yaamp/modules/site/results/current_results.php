<?php
$defaultalgo = user()->getState('yaamp-algo');
echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Pool Status</div>";
echo "<div class='main-left-inner'>";
showTableSorter('maintable1', "{
    tableClass: 'dataGrid2',
    textExtraction: {
        4: function(node, table, n) { return $(node).attr('data'); },
        8: function(node, table, n) { return $(node).attr('data'); }
    }
}");
echo <<<END
<thead>
<tr>
<th>Coins</th>
<th data-sorter="numeric" align="center">Auto Exchanged</th>
<th data-sorter="numeric" align="center">Port</th>
<th data-sorter="numeric" align="center">Symbol</th>
<th data-sorter="numeric" align="center">Miners</th>
<th data-sorter="numeric" align="center">Pool HashRate</th>
<th data-sorter="numeric" align="center">Network Hashrate</th>
<th data-sorter="currency" align="center">Fees**</th>
<!--<th data-sorter="currency" class="estimate" align="right">Current<br />Estimate</th>-->
<!--<th data-sorter="currency" >Norm</th>-->
<!--<th data-sorter="currency" class="estimate" align="right">24 Hours<br />Estimated</th>-->
<th data-sorter="currency"align="center">24 Hours<br />Actual***</th>
</tr>
</thead>
END;
$best_algo = '';
$best_norm = 0;
$algos = array();

foreach (yaamp_get_algos() as $algo)
{
    $algo_norm = yaamp_get_algo_norm($algo);
    $price = controller()
        ->memcache
        ->get_database_scalar("current_price-$algo", "select price from hashrate where algo=:algo order by time desc limit 1", array(
        ':algo' => $algo
    ));
    $norm = $price * $algo_norm;
    $norm = take_yaamp_fee($norm, $algo);
    $algos[] = array(
        $norm,
        $algo
    );
    if ($norm > $best_norm)
    {
        $best_norm = $norm;
        $best_algo = $algo;
    }
}

function cmp($a, $b)
{
    return $a[0] < $b[0];
}

usort($algos, 'cmp');
$total_coins = 0;
$total_miners = 0;
$showestimates = false;
echo "<tbody>";

foreach ($algos as $item)
{
    $norm = $item[0];
    $algo = $item[1];
    $coinsym = '';
    $coins = getdbocount('db_coins', "enable and visible and auto_ready and algo=:algo", array(
        ':algo' => $algo
    ));
    if ($coins == 2)
    {

        // If we only mine one coin, show it...
        $coin = getdbosql('db_coins', "enable and visible and auto_ready and algo=:algo", array(
            ':algo' => $algo
        ));
        $coinsym = empty($coin->symbol2) ? $coin->symbol : $coin->symbol2;
        $coinsym = '<span title="' . $coin->name . '">' . $coinsym . '</a>';
    }

    if (!$coins) continue;
    $workers = getdbocount('db_workers', "algo=:algo", array(
        ':algo' => $algo
    ));
    $hashrate = controller()
        ->memcache
        ->get_database_scalar("current_hashrate-$algo", "select hashrate from hashrate where algo=:algo order by time desc limit 1", array(
        ':algo' => $algo
    ));
    $hashrate_sfx = $hashrate ? Itoa2($hashrate) . 'h/s' : '-';
    $price = controller()
        ->memcache
        ->get_database_scalar("current_price-$algo", "select price from hashrate where algo=:algo order by time desc limit 1", array(
        ':algo' => $algo
    ));
    $price = $price ? mbitcoinvaluetoa(take_yaamp_fee($price, $algo)) : '-';
    $norm = mbitcoinvaluetoa($norm);
    $t = time() - 24 * 60 * 60;
    $avgprice = controller()
        ->memcache
        ->get_database_scalar("current_avgprice-$algo", "select avg(price) from hashrate where algo=:algo and time>$t", array(
        ':algo' => $algo
    ));
    $avgprice = $avgprice ? mbitcoinvaluetoa(take_yaamp_fee($avgprice, $algo)) : '-';
    $total1 = controller()
        ->memcache
        ->get_database_scalar("current_total-$algo", "SELECT SUM(amount*price) AS total FROM blocks WHERE time>$t AND algo=:algo AND NOT category IN ('orphan','stake','generated')", array(
        ':algo' => $algo
    ));
    $hashrate1 = controller()
        ->memcache
        ->get_database_scalar("current_hashrate1-$algo", "select avg(hashrate) from hashrate where time>$t and algo=:algo", array(
        ':algo' => $algo
    ));
    $algo_unit_factor = yaamp_algo_mBTC_factor($algo);
    $btcmhday1 = $hashrate1 != 0 ? mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 1000 * $algo_unit_factor) : '';
    $fees = yaamp_fee($algo);
    $port = getAlgoPort($algo);

    if ($defaultalgo == $algo) echo "<tr style='cursor: pointer; background-color: #d9d9d9;' onclick='javascript:select_algo(\"$algo\")'>";
    else echo "<tr style='cursor: pointer' class='ssrow' onclick='javascript:select_algo(\"$algo\")'>";
    echo "<td style='font-size: 110%; background-color: #f2f2f2;'><b>$algo</b></td>";
    echo "<td align=center style='font-size: .8em; background-color: #f2f2f2;'></td>";
    echo "<td align=center style='font-size: .8em; background-color: #f2f2f2;'></td>";
    echo "<td align=center style='font-size: .8em; background-color: #f2f2f2;'></td>";
    echo "<td align=center style='font-size: .8em; background-color: #f2f2f2;'></td>";
    echo '<td align="center" style="font-size: .8em; background-color: #f2f2f2;"></td>';
    echo '<td align="center" style="font-size: .8em; background-color: #f2f2f2;"></td>';
    echo "<td align=center style='font-size: .8em; background-color: #f2f2f2;'></td>";
    echo "<td align=center style='font-size: .8em; background-color: #f2f2f2;'></td>";
    if ($algo == $best_algo) echo '<td class="estimate" align="center" style="font-size: .8em; background-color: #f2f2f2;" title="normalized ' . $norm . '"><b>' . $price . '*</b></td>';
    else if ($norm > 0) echo '<td class="estimate" align="center" style="font-size: .8em; background-color: #f2f2f2;" title="normalized ' . $norm . '">' . $price . '</td>';
    else echo '<td class="estimate" align="center" style="font-size: .8em; background-color: #f2f2f2;"></td>';
    echo '<td class="estimate" align="center" style="font-size: .8em; background-color: #f2f2f2;"></td>';
    if ($algo == $best_algo) echo '<td align="center" style="font-size: .8em; background-color: #f2f2f2;" data="' . $btcmhday1 . '"><b>' . $btcmhday1 . '*</b></td>';
    else echo '<td align="center" style="font-size: .8em; background-color: #f2f2f2;" data="' . $btcmhday1 . '">' . $btcmhday1 . '</td>';
    echo "</tr>";
    if ($coins > 0)
    {
        $list = getdbolist('db_coins', "enable and visible and auto_ready and algo=:algo order by index_avg desc", array(
            ':algo' => $algo
        ));

        foreach ($list as $coin)
        {
            $name = substr($coin->name, 0, 18);
            $symbol = $coin->getOfficialSymbol();
            echo "<td align='left' valign='top' style='font-size: .8em;'><img width='10' src='" . $coin->image . "'>  <b>$name</b> </td>";
            $port_count = getdbocount('db_stratums', "algo=:algo and symbol=:symbol", array(
                ':algo' => $algo,
                ':symbol' => $symbol
            ));
            $port_db = getdbosql('db_stratums', "algo=:algo and symbol=:symbol", array(
                ':algo' => $algo,
                ':symbol' => $symbol
            ));

            $dontsell = $coin->dontsell;
            if ($dontsell == 1) echo "<td align='center' valign='top' style='font-size: .8em;'><img width=13 src='/images/cancel.png'></td>";
            else echo "<td align='center' valign='top' style='font-size: .8em;'><img width=13 src='/images/ok.png'></td>";

            if ($port_count == 1) echo "<td align='center' style='font-size: .8em;'><b>" . $port_db->port . "</b></td>";
            else echo "<td align='center' style='font-size: .8em;'><b>$port</b></td>";
            echo "<td align='center' style='font-size: .8em;'>$symbol</td>";
            if ($port_count == 1) echo "<td align='center' style='font-size: .8em;'>" . $port_db->workers . "</td>";
            else echo "<td align='center' style='font-size: .8em;'>$workers</td>";
            $pool_hash = yaamp_coin_rate($coin->id);
            $pool_hash_sfx = $pool_hash ? Itoa2($pool_hash) . 'h/s' : '';
            echo "<td align='center' style='font-size: .8em;'>$pool_hash_sfx</td>";
            $pool_hash_sfx = $pool_hash ? Itoa2($pool_hash) . 'h/s' : '';
            $min_ttf = $coin->network_ttf > 0 ? min($coin->actual_ttf, $coin->network_ttf) : $coin->actual_ttf;
            $network_hash = controller()
                ->memcache
                ->get("yiimp-nethashrate-{$coin->symbol}");
            if (!$network_hash)
            {
                $remote = new WalletRPC($coin);
                if ($remote) $info = $remote->getmininginfo();
                if (isset($info['networkhashps']))
                {
                    $network_hash = $info['networkhashps'];
                    controller()
                        ->memcache
                        ->set("yiimp-nethashrate-{$coin->symbol}", $info['networkhashps'], 60);
                }
                else if (isset($info['netmhashps']))
                {
                    $network_hash = floatval($info['netmhashps']) * 1e6;
                    controller()
                        ->memcache
                        ->set("yiimp-nethashrate-{$coin->symbol}", $network_hash, 60);
                }
            }
            $network_hash = $network_hash ? Itoa2($network_hash) . 'h/s' : '';
            echo "<td align='center' style='font-size: .8em;' data='$pool_hash'>$network_hash</td>";
            echo "<td align='center' style='font-size: .8em;'>{$fees}%</td>";
            $btcmhd = yaamp_profitability($coin);
            $btcmhd = mbitcoinvaluetoa($btcmhd);
            echo "<td align='center' style='font-size: .8em;'>$btcmhd</td>";
            echo "</tr>";
        }
    }

    $total_coins += $coins;
    $total_miners += $workers;
}

echo "</tbody>";

if ($defaultalgo == 'all') echo "<tr style='cursor: pointer; background-color: #d9d9d9;' onclick='javascript:select_algo(\"all\")'>";
else echo "<tr style='cursor: pointer' class='ssrow' onclick='javascript:select_algo(\"all\")'>";
echo "<td><b>all</b></td>";
echo "<td></td>";
echo "<td></td>";
echo "<td align=center style='font-size: .8em;'>$total_coins</td>";
echo "<td align=center style='font-size: .8em;'>$total_miners</td>";
echo "<td></td>";
echo "<td></td>";
echo '<td class="estimate"></td>';
echo '<td class="estimate"></td>';
echo "<td></td>";
echo "<td></td>";
echo "</tr>";
echo "</table>";
echo '<p style="font-size: .8em;">&nbsp;* values in mBTC/MH/day, per GH for sha & blake algos</p>';
echo "</div></div><br />";
?>

<?php
if (!$showestimates):
?>

<style type="text/css">
#maintable1 .estimate { display: none; }
</style>

<?php
endif;
?>
