<?php

/////////////////////////////////////////////////////////////////////////////////////

echo <<<end
<style type="text/css">
tr.ssrow.filtered { display: none; }
</style>
end;

showTableSorter('maintable', '{
tableClass: "dataGrid",
headers: {
	0:{sorter:"metadata"}, /* reset filter */
	1:{sorter:"metadata"},
	2:{sorter:"text"},
	3:{sorter:"text"},
	4:{sorter:"currency"},
	5:{sorter:"currency"},
	6:{sorter:"currency"},
	7:{sorter:"currency"},
	8:{sorter:"currency"},
	9:{sorter:"currency"},
	10:{sorter:"currency"},
	11:{sorter:"currency"}
},
widgets: ["zebra","filter","Storage","saveSort"],
widgetOptions: {
	saveSort: true,
	filter_saveFilters: true,
	filter_external: ".search",
	filter_columnFilters: false,
	filter_childRows : true,
	filter_ignoreCase: true
}}');

echo <<<end
<thead class="">
<tr>
<th width="30"></th>
<th></th>

<th>Name</th>
<th>Server</th>
<th align="right">Diff/Height</th>
<th align="right">Profit</th>
<th align="right">Owed/BTC</th>
<th align="right">Balance/Mint</th>
<th align="right">Price</th>
<th align="right">BTC</th>
<th align="right">USD</th>
<th align="right">Win/Market</th>

</tr>
</thead><tbody>
end;

$server = getparam('server');
if(!empty($server))
{
	$coins = getdbolist('db_coins', "(installed or enable) and rpchost=:server order by algo, index_avg desc",
		array(':server'=>$server));
}
else
	$coins = getdbolist('db_coins', "installed or enable order by algo, index_avg desc");

$mining = getdbosql('db_mining');

foreach($coins as $coin)
{
	echo '<tr class="ssrow">';

	$lowsymbol = strtolower($coin->symbol);
	echo "<td><img src='$coin->image' width=24></td>";

	$algo_color = getAlgoColors($coin->algo);
        echo "<td style='background-color:$algo_color;'><b>";

	if($coin->enable)
	{
		echo "u";
		if($coin->auto_ready) echo "<span style='color: green;'> a</span>";
		else echo "<span style='color: red;'> d</span>";

		echo '<br>';

		if($coin->visible) echo "v";
		else echo '&nbsp;';

		if($coin->auxpow) echo " x";

		if($coin->block_height < $coin->target_height)
		{
			$percent = round($coin->block_height*100/$coin->target_height, 2);
			echo "<br><span style='font-size: .8em'>$percent%</span>";
		}
	}

	echo "</b></td>";
	$version = formatWalletVersion($coin);
	if (!empty($coin->symbol2)) $version .= " ({$coin->symbol2})";

	echo "<td><b><a href='/site/coin?id=$coin->id'>$coin->name ($coin->symbol)</a></b>
		<br><span style='font-size: .8em'>$version</span></td>";

	echo "<td>$coin->rpchost:$coin->rpcport";
	if($coin->connections) echo " ($coin->connections)";
	echo "<br><span style='font-size: .8em'>$coin->rpcencoding <span style='background-color:$algo_color;'>&nbsp; ($coin->algo) &nbsp;</span></span></td>";

	$difficulty = Itoa2($coin->difficulty, 3);
	if ($difficulty > 1e20) $difficulty = '&nbsp;';

	if(!empty($coin->errors))
		echo "<td align=right style='color: red; font-size: .9em;' title='$coin->errors'><b>$difficulty</b><br>$coin->block_height</td>";
	else
		echo "<td align=right style='font-size: .9em'><b>$difficulty</b><br>$coin->block_height</td>";

// 	$network_ttf = $coin->network_ttf? sectoa($coin->network_ttf): '';
// 	$actual_ttf = $coin->actual_ttf? sectoa($coin->actual_ttf): '';
// 	$pool_ttf = $coin->pool_ttf? sectoa($coin->pool_ttf): '';
	$btcmhd = yaamp_profitability($coin);
	$btcmhd = mbitcoinvaluetoa($btcmhd);

	$h = $coin->block_height-100;
	$ss1 = dboscalar("select count(*) from blocks where coin_id=$coin->id and height>=$h and category!='orphan'");
	$ss2 = dboscalar("select count(*) from blocks where coin_id=$coin->id and height>=$h and category='orphan'");

	$percent_pool1 = $ss1? $ss1.'%': '';
	$percent_pool2 = $ss2? $ss2.'%': '';

// 	echo "<td align=right style='font-size: .9em'>$network_ttf<br>$actual_ttf</td>";
// 	echo "<td align=right style='font-size: .9em'>$pool_ttf<br></td>";

	if($ss1 > 50)
		echo "<td align=right style='font-size: .9em'><b>$btcmhd</b><br><span style='color: blue;'>$percent_pool1</span>";
	else
		echo "<td align=right style='font-size: .9em'><b>$btcmhd</b><br>$percent_pool1";

	echo "<span style='color: red;'> $percent_pool2</span></td>";

	$owed = dboscalar("select sum(balance) from accounts where coinid=$coin->id");
	$owed_btc = bitcoinvaluetoa($owed*$coin->price);
	$owed = bitcoinvaluetoa($owed);

	if($coin->balance+$coin->mint < $owed)
		echo "<td align=right style='font-size: .9em'><span style='color: red;'>$owed<br>$owed_btc</span></td>";
	else
		echo "<td align=right style='font-size: .9em'>$owed<br>$owed_btc</td>";

	echo '<td align="right" style="font-size: .9em;">'.$coin->balance.'<br/>'.$coin->mint.'</td>';

	$price = bitcoinvaluetoa($coin->price);
	$price2 = bitcoinvaluetoa($coin->price2);
//	$marketcount = getdbocount('db_markets', "coinid=$coin->id");

	if($coin->dontsell)
		echo "<td align=right style='font-size: .9em; background-color: #ffaaaa'>$price<br>$price2</td>";
	else
		echo "<td align=right style='font-size: .9em'>$price<br>$price2</td>";

	$btc = bitcoinvaluetoa($coin->balance * $coin->price);
	$mint = bitcoinvaluetoa($coin->mint * $coin->price);
	echo '<td align="right" style="font-size: .9em;">'.$btc.'<br/>'.$mint.'</td>';

	$fiat = round($coin->balance * $coin->price * $mining->usdbtc, 2). ' $';
	$mint = round($coin->mint * $coin->price * $mining->usdbtc, 2). ' $';
	echo '<td align="right" style="font-size: .9em;">'.$fiat.'<br/>'.$mint.'</td>';

	$marketname = '';
	$bestmarket = getBestMarket($coin);
	if($bestmarket)	$marketname = $bestmarket->name;

	echo "<td align=right style='font-size: .9em'>$coin->reward<br>$marketname</td>";

	echo "</tr>";
}

$total = count($coins);
echo "<tr>";
echo "<td colspan=2></td>";
echo "<td colspan=9>$total Coins</td>";
echo "</tr>";

echo "</tbody>";
echo "</table>";

//////////////////////////////////////////

echo "<br>";













