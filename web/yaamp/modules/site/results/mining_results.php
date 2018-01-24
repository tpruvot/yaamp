<?php

function WriteBoxHeader($title)
{
	echo "<div class='main-left-box'>";
	echo "<div class='main-left-title'>$title</div>";
	echo "<div class='main-left-inner'>";
}

$showrental = (bool) YAAMP_RENTAL;

$algo = user()->getState('yaamp-algo');

$total_rate = yaamp_pool_rate();
$total_rate_d = $total_rate? 'at '.Itoa2($total_rate).'h/s': '';

if($algo == 'all')
	$list = getdbolist('db_coins', "enable and visible order by index_avg desc");
else
	$list = getdbolist('db_coins', "enable and visible and algo=:algo order by index_avg desc", array(':algo'=>$algo));

$count = count($list);

if($algo == 'all')
	$worker = getdbocount('db_workers');
else
	$worker = getdbocount('db_workers', "algo=:algo", array(':algo'=>$algo));

if ($showrental)
	$services = getdbolist('db_services', "algo=:algo ORDER BY price DESC", array(':algo'=>$algo));
else
	$services = array();

////////////////////////////////////////////////////////////////////////////////////

$coin_count = $count > 1 ? "on $count wallets" : 'on a single wallet';
$miner_count = $worker > 1 ? "$worker miners" : "$worker miner";
WriteBoxHeader("Mining $coin_count $total_rate_d, $miner_count");

showTableSorter('maintable3', "{
	tableClass: 'dataGrid2',
	textExtraction: {
		3: function(node, table, n) { return $(node).attr('data'); },
		6: function(node, table, n) { return $(node).attr('data'); },
		7: function(node, table, n) { return $(node).attr('data'); }
	}
}");

echo <<<END
<thead>
<tr>
<th data-sorter=""></th>
<th data-sorter="text">Name</th>
<th align="right">Amount</th>
<th data-sorter="numeric" align="right">Diff</th>
<th align="right">Block</th>
<th align="right">TTF***</th>
<th data-sorter="numeric" align="right">Hash**</th>
<th data-sorter="currency" align="right">Profit*</th>
</tr>
</thead>
END;

if($algo != 'all' && $showrental)
{
	$hashrate_jobs = yaamp_rented_rate($algo);
	$hashrate_jobs = $hashrate_jobs? Itoa2($hashrate_jobs).'h/s': '';

	$price_rent = dboscalar("select rent from hashrate where algo=:algo order by time desc", array(':algo'=>$algo));
	$price_rent = mbitcoinvaluetoa($price_rent);

	$amount_rent = dboscalar("select sum(amount) from jobsubmits where status=1 and algo=:algo", array(':algo'=>$algo));
	$amount_rent = bitcoinvaluetoa($amount_rent);
}

foreach($list as $coin)
{
	$name = substr($coin->name, 0, 12);
	$difficulty = Itoa2($coin->difficulty, 3);
	$price = bitcoinvaluetoa($coin->price);
	$height = number_format($coin->block_height, 0, '.', ' ');
//	$pool_ttf = $coin->pool_ttf? sectoa2($coin->pool_ttf): '';
	$pool_ttf = $total_rate? $coin->difficulty * 0x100000000 / $total_rate: 0;
	$reward = round($coin->reward, 3);

	$btcmhd = yaamp_profitability($coin);
	$pool_hash = yaamp_coin_rate($coin->id);
	$real_ttf = $pool_hash? $coin->difficulty * 0x100000000 / $pool_hash: 0;

	$pool_hash_sfx = $pool_hash? Itoa2($pool_hash).'h/s': '';
	$real_ttf = $real_ttf? sectoa2($real_ttf): '';
	$pool_ttf = $pool_ttf? sectoa2($pool_ttf): '';

	$pool_hash_pow = yaamp_pool_rate_pow($coin->algo);
	$pool_hash_pow_sfx = $pool_hash_pow? Itoa2($pool_hash_pow).'h/s': '';

	$min_ttf = $coin->network_ttf>0? min($coin->actual_ttf, $coin->network_ttf): $coin->actual_ttf;
	$network_hash = $coin->difficulty * 0x100000000 / ($min_ttf? $min_ttf: 60);
	$network_hash = $network_hash? 'network hash '.Itoa2($network_hash).'h/s': '';

	if(controller()->admin && $services)
	{
		foreach($services as $i=>$service)
		{
			if($service->price*1000 < $btcmhd) continue;
			$service_btcmhd = mbitcoinvaluetoa($service->price*1000);

			echo "<tr class='ssrow'>";
			echo "<td width=18><img width=16 src='/images/btc.png'></td>";
			echo "<td><b>$service->name</b></td>";
			echo "<td></td>";
			echo "<td></td>";
			echo "<td></td>";
			echo "<td></td>";
			echo "<td></td>";
			echo "<td align=right style='font-size: .8em;'><b>$service_btcmhd</b></td>";
			echo "</tr>";

			unset($services[$i]);
		}
	}

	if(isset($price_rent) && $price_rent > $btcmhd)
	{
		echo "<tr class='ssrow'>";
		echo "<td width=18><img width=16 src='/images/btc.png'></td>";
		echo "<td><b>Rental</b></td>";
		echo "<td align=right style='font-size: .8em;'><b>$amount_rent BTC</b></td>";
		echo "<td></td>";
		echo "<td></td>";
		echo "<td></td>";
		echo "<td align=right style='font-size: .8em;'>$hashrate_jobs</td>";
		echo "<td align=right style='font-size: .8em;'><b>$price_rent</b></td>";
		echo "</tr>";

		unset($price_rent);
	}

	if(!$coin->auto_ready)
		echo "<tr style='opacity: 0.4;'>";
	else
		echo "<tr class='ssrow'>";

	echo '<td width="18">';
	echo $coin->createExplorerLink('<img width="16" src="'.$coin->image.'">');
	echo '</td>';

	$owed = dboscalar("select sum(balance) from accounts where coinid=$coin->id");
	if(YAAMP_ALLOW_EXCHANGE && $coin->balance+$coin->mint < $owed*0.9 ) {
		$owed2 = bitcoinvaluetoa($owed - $coin->balance);
		$symbol = $coin->getOfficialSymbol();
		$title = "We are short of this currency ($owed2 $symbol). Please switch to another currency until we find more $symbol blocks.";
		echo "<td><b><a href=\"/site/block?id={$coin->id}\" title=\"$title\" style=\"color: #c55;\">$name</a></b><span style=\"font-size: .8em;\"> ({$coin->algo})</span></td>";
	} else {
		echo "<td><b><a href='/site/block?id=$coin->id'>$name</a></b><span style='font-size: .8em'> ($coin->algo)</span></td>";
	}
	echo "<td align=right style='font-size: .8em;'><b>$reward $coin->symbol_show</b></td>";

	$title = "POW $coin->difficulty";
	if($coin->rpcencoding == 'POS')
		$title .= "\nPOS $coin->difficulty_pos";

	echo '<td align="right" style="font-size: .8em;" data="'.$coin->difficulty.'" title="'.$title.'">'.$difficulty.'</td>';

	if(!empty($coin->errors))
		echo "<td align=right style='font-size: .8em; color: red;' title='$coin->errors'>$height</td>";
	else
		echo "<td align=right style='font-size: .8em;'>$height</td>";

	if(!YAAMP_ALLOW_EXCHANGE && !empty($real_ttf))
		echo '<td align="right" style="font-size: .8em;" title="'.$pool_ttf.' at full pool speed">'.$real_ttf.'</td>';
	elseif(!empty($real_ttf))
		echo '<td align="right" style="font-size: .8em;" title="'.$real_ttf.' at '.Itoa2($pool_hash).'">'.$pool_ttf.'</td>';
	else
		echo '<td align="right" style="font-size: .8em;" title="At current pool speed">'.$pool_ttf.'</td>';

	if($coin->auxpow && $coin->auto_ready)
		echo "<td align=right style='font-size: .8em; opacity: 0.6;' title='merge mined\n$network_hash' data='$pool_hash_pow'>$pool_hash_pow_sfx</td>";
	else
		echo "<td align=right style='font-size: .8em;' title='$network_hash' data='$pool_hash'>$pool_hash_sfx</td>";

	$btcmhd = mbitcoinvaluetoa($btcmhd);
	echo "<td align=right style='font-size: .8em;' data='$btcmhd'><b>$btcmhd</b></td>";
	echo "</tr>";
}

if(controller()->admin && $services)
{
	foreach($services as $i=>$service)
	{
		$service_btcmhd = mbitcoinvaluetoa($service->price*1000);

		echo "<tr class='ssrow'>";
		echo "<td width=18><img width=16 src='/images/btc.png'></td>";
		echo "<td><b>$service->name</b></td>";
		echo "<td></td>";
		echo "<td></td>";
		echo "<td></td>";
		echo "<td></td>";
		echo "<td></td>";
		echo "<td align=right style='font-size: .8em;'><b>$service_btcmhd</b></td>";
		echo "</tr>";
	}
}

if(isset($price_rent) && $showrental)
{
	echo "<tr class='ssrow'>";
	echo "<td width=18><img width=16 src='/images/btc.png'></td>";
	echo "<td><b>Rental</b></td>";
	echo "<td align=right style='font-size: .8em;'><b>$amount_rent BTC</b></td>";
	echo "<td></td>";
	echo "<td></td>";
	echo "<td></td>";
	echo "<td align=right style='font-size: .8em;'>$hashrate_jobs</td>";
	echo "<td align=right style='font-size: .8em;'><b>$price_rent</b></td>";
	echo "</tr>";

	unset($price_rent);
}


echo "</table>";

echo '<p style="font-size: .8em;">
	&nbsp;*** estimated average time to find a block at full pool speed<br/>
	&nbsp;** approximate from the last 5 minutes submitted shares<br/>
	&nbsp;* 24h estimation from net difficulty in mBTC/MH/day (GH/day for sha & blake algos)<br>
</p>';

echo "</div></div><br>";


