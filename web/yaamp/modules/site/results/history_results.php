<?php
$mining = getdbosql('db_mining');
$algo = user()->getState('yaamp-algo');
if ($algo == 'all') return;

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Pool Stats ($algo)</div>";
echo "<div class='main-left-inner'>";

echo <<<END
<style type="text/css">
td.symb, th.symb {
	width: 50px;
	max-width: 50px;
	text-align: right;
}
td.symb {
	font-size: .8em;
}
</style>

<table class="dataGrid2">
<thead>
<tr>
<th></th>
<th>Name</th>
<th class="symb">Symbol</th>
<th align=right>Last Hour</th>
<th align=right>Last 24 Hours</th>
<th align=right>Last 7 Days</th>
<th align=right>Last 30 Days</th>
</tr>
</thead>
END;


$t1 = time() - 60 * 60;
$t2 = time() - 24 * 60 * 60;
$t3 = time() - 7 * 24 * 60 * 60;
$t4 = time() - 30 * 24 * 60 * 60;

$total1 = 0;
$total2 = 0;
$total3 = 0;
$total4 = 0;

$main_ids = array();

$algo = user()->getState('yaamp-algo');
$list = dbolist("SELECT coin_id FROM blocks WHERE coin_id IN (select id from coins where algo=:algo and enable=1 and visible=1)
	AND time>$t4 AND NOT category IN ('orphan','stake','generated') GROUP BY coin_id ORDER BY coin_id DESC", array(
    ':algo' => $algo
));

foreach ($list as $item)
{
    $coin = getdbo('db_coins', $item['coin_id']);

    $id = $coin->id;
    $main_ids[$id] = $coin->symbol;

    if ($coin->symbol == 'BTC') continue;

    $res1 = controller()
        ->memcache
        ->get_database_row("history_item1-$id-$algo", "SELECT COUNT(id) as a, SUM(amount*price) as b FROM blocks WHERE coin_id=$id AND NOT category IN ('orphan','stake','generated') AND time>$t1 AND algo=:algo", array(
        ':algo' => $algo
    ));

    $res2 = controller()
        ->memcache
        ->get_database_row("history_item2-$id-$algo", "SELECT COUNT(id) as a, SUM(amount*price) as b FROM blocks WHERE coin_id=$id AND NOT category IN ('orphan','stake','generated') AND time>$t2 AND algo=:algo", array(
        ':algo' => $algo
    ));

    $res3 = controller()
        ->memcache
        ->get_database_row("history_item3-$id-$algo", "SELECT COUNT(id) as a, SUM(amount*price) as b, MIN(time) as t FROM blocks WHERE coin_id=$id AND NOT category IN ('orphan','stake','generated') AND time>$t3 AND algo=:algo", array(
        ':algo' => $algo
    ));

    $res4 = controller()
        ->memcache
        ->get_database_row("history_item4-$id-$algo", "SELECT COUNT(id) as a, SUM(amount*price) as b, MIN(time) as t FROM blocks WHERE coin_id=$id AND NOT category IN ('orphan','stake','generated') AND time>$t4 AND algo=:algo", array(
        ':algo' => $algo
    ));

    $total1 += $res1['b'];
    $total2 += $res2['b'];
    $total3 += $res3['b'];
    $total4 += $res4['b'];

    if ($res3['a'] == $res2['a'] || count($list) == 1)
    {
        // blocks table may be purged before 7 days, so use same source as stat graphs
        // TODO: add block count in hashstats or keep longer cleared blocks
        if ($res3['t'] > ($t3 + 24 * 60 * 60)) $res3['a'] = '-';
        $total3 = controller()
            ->memcache
            ->get_database_scalar("history_item3-$id-$algo-btc", "SELECT SUM(earnings) as b FROM hashstats WHERE time>$t3 AND algo=:algo", array(
            ':algo' => $algo
        ));
    }

    if ($res4['a'] == $res3['a'] || count($list) == 1)
    {
        $res4['a'] = '-';
        $total4 = controller()
            ->memcache
            ->get_database_scalar("history_item4-$id-$algo-btc", "SELECT SUM(earnings) as b FROM hashstats WHERE time>$t4 AND algo=:algo", array(
            ':algo' => $algo
        ));
    }

    $name = substr($coin->name, 0, 12);

    echo '<tr class="ssrow">';

    echo '<td width=18><img width=16 src="' . $coin->image . '"></td>';
    echo '<td><b><a href="/site/block?id=' . $id . '">' . $name . '</a></b></td>';
    echo '<td class="symb">' . $coin->symbol . '</td>';

    echo '<td align="right" style="font-size: .9em;">' . $res1['a'] . '</td>';
    echo '<td align="right" style="font-size: .9em;">' . $res2['a'] . '</td>';
    echo '<td align="right" style="font-size: .9em;">' . $res3['a'] . '</td>';
    echo '<td align="right" style="font-size: .9em;">' . $res4['a'] . '</td>';

    echo '</tr>';
}

$others = dbolist("SELECT id, image, symbol, name FROM coins
	WHERE algo=:algo AND installed=1 AND enable=1 AND auto_ready=1 AND visible=1 ORDER BY symbol", array(
    ':algo' => $algo
));

foreach ($others as $item)
{
    if (array_key_exists($item['id'], $main_ids)) continue;
    echo '<tr class="ssrow">';
    echo '<td width="18px"><img width="16px" src="' . $item['image'] . '"></td>';
    echo '<td><b><a href="/site/block?id=' . $item['id'] . '">' . $item['name'] . '</a></b></td>';
    echo '<td class="symb">' . $item['symbol'] . '</td>';
    echo '<td colspan="4"></td>';
    echo '</tr>';
}

///////////////////////////////////////////////////////////////////////
$hashrate1 = controller()
    ->memcache
    ->get_database_scalar("history_hashrate1-$algo", "SELECT AVG(hashrate) FROM hashrate WHERE time>$t1 AND algo=:algo", array(
    ':algo' => $algo
));

$hashrate2 = controller()
    ->memcache
    ->get_database_scalar("history_hashrate2-$algo", "SELECT AVG(hashrate) FROM hashrate WHERE time>$t2 AND algo=:algo", array(
    ':algo' => $algo
));

$hashrate3 = controller()
    ->memcache
    ->get_database_scalar("history_hashrate3-$algo", "SELECT AVG(hashrate) FROM hashrate WHERE time>$t3 AND algo=:algo", array(
    ':algo' => $algo
));

$hashrate4 = controller()
    ->memcache
    ->get_database_scalar("history_hashrate4-$algo", "SELECT AVG(hashrate) FROM hashstats WHERE time>$t4 AND algo=:algo", array(
    ':algo' => $algo
));

$hashrate1 = max($hashrate1, 1);
$hashrate2 = max($hashrate2, 1);
$hashrate3 = max($hashrate3, 1);
$hashrate4 = max($hashrate4, 1);

$btcmhday1 = mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 24 * 1000);
$btcmhday2 = mbitcoinvaluetoa($total2 / $hashrate2 * 1000000 * 1 * 1000);
$btcmhday3 = mbitcoinvaluetoa($total3 / $hashrate3 * 1000000 / 7 * 1000);
$btcmhday4 = mbitcoinvaluetoa($total4 / $hashrate4 * 1000000 / 30 * 1000);

$hashrate1 = Itoa2($hashrate1);
$hashrate2 = Itoa2($hashrate2);
$hashrate3 = Itoa2($hashrate3);
$hashrate4 = Itoa2($hashrate4);

$total1 = bitcoinvaluetoa($total1);
$total2 = bitcoinvaluetoa($total2);
$total3 = bitcoinvaluetoa($total3);
$total4 = bitcoinvaluetoa($total4);

echo '<tr class="ssrow" style="border-top: 2px solid #eee;">';
echo '<td width="18px"><img width="16px" src="/images/btc.png"></td>';
echo '<td colspan="2"><b>BTC Value</b></td>';

echo '<td align="right" style="font-size: .9em;">' . $total1 . '</td>';
echo '<td align="right" style="font-size: .9em;">' . $total2 . '</td>';
echo '<td align="right" style="font-size: .9em;">' . $total3 . '</td>';
echo '<td align="right" style="font-size: .9em;">' . $total4 . '</td>';

echo "</tr>";

///////////////////////////////////////////////////////////////////////
echo '<tr class="ssrow" style="border-top: 2px solid #eee;">';
echo '<td width="18px"></td>';
echo '<td colspan="2"><b>Avg Hashrate</b></td>';

echo '<td align="right" style="font-size: .9em;">' . $hashrate1 . 'h/s</td>';
echo '<td align="right" style="font-size: .9em;">' . $hashrate2 . 'h/s</td>';
echo '<td align="right" style="font-size: .9em;">' . $hashrate3 . 'h/s</td>';
echo '<td align="right" style="font-size: .9em;">' . $hashrate4 . 'h/s</td>';

echo '</tr>';

///////////////////////////////////////////////////////////////////////
echo '<tr class="ssrow" style="border-top: 2px solid #eee;">';
echo '<td width="18px"></td>';
echo '<td colspan="2"><b>mBTC/Mh/d</b></td>';

echo '<td align="right" style="font-size: .9em;">' . $btcmhday1 . '</td>';
echo '<td align="right" style="font-size: .9em;">' . $btcmhday2 . '</td>';
echo '<td align="right" style="font-size: .9em;">' . $btcmhday3 . '</td>';
echo '<td align="right" style="font-size: .9em;">' . $btcmhday4 . '</td>';

echo '</tr>';

echo '</table>';

echo '</div>';

echo '<br>';
echo '</div></div><br>';
