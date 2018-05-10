<?php

$defaultalgo = user()->getState('yaamp-algo');

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Pool Status</div>";
echo "<div class='main-left-inner'>";

$total_rate = yaamp_pool_rate();
$total_rate_d = $total_rate? 'at '.Itoa2($total_rate).'h/s': '';
$t2 = time() - 24*60*60;

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
<th>Algo</th>
<th data-sorter="numeric" align="right" data-title"Ensure that you mine on correct port. Hover over coins port to see connection details.">Port</th>
<th data-sorter="numeric" align="right">Coins</th>
<th data-sorter="numeric" align="right" data-title"Current stats on how many miners are connnected to our pool for each algo and coin">Miners</th>
<th data-sorter="numeric" align="right" data-title"Current hashrate for each algo and coin on our pool">Hashrate</th>
<th data-sorter="numeric" align="right" data-title"Currently estimated time to find block">TTF</th>
<th data-sorter="numeric" align="right" data-title"Blocks found by pool last 24 hours">BF/24h</th>
<th data-sorter="currency" align="right" data-title="Flat fee for each algorithm">Fees</th>
<th data-sorter="currency" class="estimate" align="right">Current<br>Estimate</th>
<!--<th data-sorter="currency" >Norm</th>-->
<th data-sorter="currency" class="estimate" align="right">24 Hours<br>Estimated</th>
<th data-sorter="currency"align="right" data-title="values in mBTC/MH/day, per GH for sha & blake algos">24 Hours<br>Actual</th>
</tr>
</thead>
END;

$best_algo = '';
$best_norm = 0;

$algos = array();
foreach(yaamp_get_algos() as $algo)
{
	$algo_norm = yaamp_get_algo_norm($algo);

	$price = controller()->memcache->get_database_scalar("current_price-$algo",
		"select price from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));

	$norm = $price*$algo_norm;
	$norm = take_yaamp_fee($norm, $algo);

	$algos[] = array($norm, $algo);

	if($norm > $best_norm)
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
foreach($algos as $item)
{
	$norm = $item[0];
	$algo = $item[1];

	$coinsym = '';
	$coins = getdbocount('db_coins', "enable and visible and auto_ready and algo=:algo", array(':algo'=>$algo));
	if ($coins == 1) {
		// If we only mine one coin, show it...
		$coin = getdbosql('db_coins', "enable and visible and auto_ready and algo=:algo", array(':algo'=>$algo));
		$coinsym = empty($coin->symbol2) ? $coin->symbol : $coin->symbol2;
		$coinsym = '<span title="'.$coin->name.'">'.$coinsym.'</a>';
	}

	if (!$coins) continue;

	$workers = getdbocount('db_workers', "algo=:algo", array(':algo'=>$algo));

	$hashrate = controller()->memcache->get_database_scalar("current_hashrate-$algo",
		"select hashrate from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));
	$hashrate_sfx = $hashrate? Itoa2($hashrate).'h/s': '-';

	$price = controller()->memcache->get_database_scalar("current_price-$algo",
		"select price from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));

	$price = $price? mbitcoinvaluetoa(take_yaamp_fee($price, $algo)): '-';
	$norm = mbitcoinvaluetoa($norm);

	$t = time() - 24*60*60;

	$avgprice = controller()->memcache->get_database_scalar("current_avgprice-$algo",
		"select avg(price) from hashrate where algo=:algo and time>$t", array(':algo'=>$algo));
	$avgprice = $avgprice? mbitcoinvaluetoa(take_yaamp_fee($avgprice, $algo)): '-';

	$total1 = controller()->memcache->get_database_scalar("current_total-$algo",
		"SELECT SUM(amount*price) AS total FROM blocks WHERE time>$t AND algo=:algo AND NOT category IN ('orphan','stake','generated')",
		array(':algo'=>$algo)
	);

	$hashrate1 = controller()->memcache->get_database_scalar("current_hashrate1-$algo",
		"select avg(hashrate) from hashrate where time>$t and algo=:algo", array(':algo'=>$algo));

	$algo_unit_factor = yaamp_algo_mBTC_factor($algo);
	$btcmhday1 = $hashrate1 != 0? mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 1000 * $algo_unit_factor): '';

	$fees = yaamp_fee($algo);
	$port = getAlgoPort($algo);

	if($defaultalgo == $algo)
		echo "<tr style='cursor: pointer; font-size: .9em; background-color: #e0d3e8;' onclick='javascript:select_algo(\"$algo\")'>";
	else
		echo "<tr style='cursor: pointer;font-size: .9em;' class='ssrow' onclick='javascript:select_algo(\"$algo\")'>";

	echo "<td><b>$algo</b></td>";
	if ($coins == 1)
		echo '<td data-title="Use this port to mine '.$algo.'." align=right>'.$port.'</td>';
	else
		echo '<td data-title="Use appropiate port below for the coin you would like to mine" align=right>-</td>';
	echo '<td data-title="Available coin(s) for '.$algo.'." align=right>'.($coins==1 ? $coinsym : $coins).'</td>';
	echo '<td data-title="Currently connected workers for '.$algo.'." align=right>'.$workers.'</td>';
	echo '<td data-title="Average pool hashrate for '.$algo.'." align="right" data="'.$hashrate.'">'.$hashrate_sfx.'</td>';
	echo '<td align=right>-</td>'; // Empty for TTF based on coin
	echo '<td align=right>-</td>'; // Empty for blocks pr 24h based on coin
	echo '<td align=right data-title="Pool fee for '.$algo.'">'.$fees.'%</td>';

	if($algo == $best_algo)
		echo '<td class="estimate" align="right" title="normalized '.$norm.'"><b>'.$price.'*</b></td>';
	else if($norm>0)
		echo '<td class="estimate" align="right" title="normalized '.$norm.'">'.$price.'</td>';

	else
		echo '<td class="estimate" align="right">'.$price.'</td>';


	echo '<td class="estimate" align="right">'.$avgprice.'</td>';

	if($algo == $best_algo)
		echo '<td align="right" data="'.$btcmhday1.'"><b>'.$btcmhday1.'*</b></td>';
	else
		echo '<td align="right" data="'.$btcmhday1.'">'.$btcmhday1.'</td>';

	echo "</tr>";
	$coinscount = getdbocount('db_coins', "enable and visible and auto_ready and algo=:algo", array(':algo'=>$algo));
	if ($coins > 1)
	{
		$others = dbolist("SELECT id, image, symbol, name, difficulty, price, block_height, reward, network_ttf, actual_ttf FROM coins WHERE algo=:algo AND installed=1 AND enable=1 AND auto_ready=1 AND visible=1 ORDER BY symbol",
	              //getdbolist('db_coins', "enable and visible and algo=:algo order by index_avg desc", array(':algo'=>$algo));

		array(':algo'=>$algo)
		);
		foreach($others as $item)
		{
			$name = substr($item['name'], 0, 12);
			$id = $item['id'];
			$difficulty = Itoa2($item['difficulty'], 3);
			$price = bitcoinvaluetoa($item['price']);
			$height = number_format($item['block_height'], 0, '.', ' ');
			//$pool_ttf = $total_rate? $item['difficulty'] * 0x100000000 / $total_rate: 0;
			$reward = round($item['reward'], 3);
			//$btcmhd = yaamp_profitability($item); Needs more work.
			$pool_hash = yaamp_coin_rate($item['id']);
			$real_ttf = $pool_hash? $item['difficulty'] * 0x100000000 / $pool_hash: 0;
			$pool_hash_sfx = $pool_hash? Itoa2($pool_hash).'h/s': '';
			$real_ttf = $real_ttf? sectoa2($real_ttf): '';
			//$pool_ttf = $pool_ttf? sectoa2($pool_ttf): '';
			$pool_hash_pow = $hashrate;
			$pool_hash_pow_sfx = $hashrate_sfx;

			$min_ttf = $item['network_ttf']>0? min($item['actual_ttf'], $item['network_ttf']): $item['actual_ttf'];
			$network_hash = $item['difficulty'] * 0x100000000 / ($min_ttf? $min_ttf: 60);
			$network_hash = $network_hash? 'network hash '.Itoa2($network_hash).'h/s': '';

			$currentsym = $item['symbol'];
			$getport = dbolist("SELECT port FROM stratums WHERE symbol LIKE '$currentsym'", array(':algo'=>$algo) );
			$geturl = dbolist("SELECT url FROM stratums WHERE symbol LIKE '$currentsym'", array(':algo'=>$algo) );
			$getworkers = dbolist("SELECT workers FROM stratums WHERE symbol LIKE '$currentsym'", array(':algo'=>$algo) );

			// Get block count 24h
			$res2 = controller()->memcache->get_database_row("history_item2-$id-$algo",
		"SELECT COUNT(id) as a, SUM(amount*price) as b FROM blocks WHERE coin_id=$id AND NOT category IN ('orphan','stake','generated') AND time>$t2 AND algo=:algo",
		array(':algo'=>$algo));

			// TESTS
			//echo $name;
			//echo $difficulty;
			//echo $price;
			//echo $height;
			//echo $pool_ttf;
			//echo $reward;
			//echo $btcmhd;
			//echo '<br>Coin: '.$name;
			//echo '<br>Pool Hash Sfx: '.$pool_hash_sfx;
			//echo '<br>Pool hash:'.$pool_hash.'<br>';
			//echo 'Real TTF:'.$real_ttf.'<br>';
			//echo 'Pool TTF:'.$pool_ttf.'<br>';
			//echo 'Minimum TTF:'.$min_ttf.'<br>';
			//echo 'Pool TTF:'.$pool_ttf.'<br>';
			//echo 'Pool Hash Pow:'.$pool_hash_pow.'<br>';
			//echo 'Pool Hash Pow Sfx:'.$pool_hash_pow_sfx.'<br>';
			//echo 'Blocks 24h: '.$res2['a'].'<br>';
			// TESTS END

			echo '<tr class="coininfo" style="font-size: .8em;" style="cursor:pointer">';
			echo '<td><img style="min-width:16px" src="'.$item['image'].'"><b><a href="/site/block?id='.$item['id'].'">'.$item['name'].'</a></b></td>';


			if (isset($getport[0]['port']) && isset($geturl[0]['url'])) 
			{ 
				echo '<td align=right class="port" style="cursor:pointer" data-title="Pool URL:<br>stratum+tcp://'.$geturl[0]['url'].':'.$getport[0]['port'].'">'.$getport[0]['port'].'</td>';
			} 
			else 
			echo '<td class="port"></td>';
			echo '<td align=right class="symb" data-title="Pool Password:<br>c='.$item['symbol'].'">'.$item['symbol'].'</td>';


			if (isset($getworkers[0]['workers'])) 
			{ 
				if ($getworkers[0]['workers'] == 0) {
				echo '<td align=right class="'.$algo.'x'.$name.'xw" style="cursor:pointer" data-title="There is no workers on '.$name.'">-</td>';
				}
				else echo '<td align=right class="'.$algo.'x'.$name.'xw" style="cursor:pointer" data-title="Actual workers on '.$name.'">'.$getworkers[0]['workers'].'</td>';
			}
			if ($pool_hash_sfx == NULL) { // adds - instead of showing nothing.
				echo '<td align=right style="cursor:pointer" data-title="No reported hashrate for this coin" class="'.$algo.'x'.$name.'xhs">-</td>'; // Coin Hashrate
				}
				else echo '<td align=right style="cursor:pointer" data-title="Current hashrate for '.$name.'" class="'.$algo.'x'.$name.'xhs">'.$pool_hash_sfx.'</td>'; // Coin Hashrate
			if ($real_ttf == NULL) { // adds - instead of showing nothing.
				echo '<td align=right style="cursor:pointer" data-title="No estimated time to find block for this coin" class="'.$algo.'x'.$name.'xttf">-</td>'; // Coin TTF
			} else echo '<td align=right style="cursor:pointer" data-title="At current hashrate it takes ~'.$real_ttf.' to find a block for '.$name.'" class="'.$algo.'x'.$name.'xttf">'.$real_ttf.'</td>'; // Coin TTF

			if ($res2['a'] == 0) {
				echo '<td align=right class="'.$algo.'x'.$name.'xw" style="cursor:pointer" data-title="This pool have not been able to digg up a block for '.$name.' the last 24 hours">-</td>';
				}
				else echo '<td align=right class="'.$algo.'x'.$name.'xw" style="cursor:pointer" data-title="We have processed  '.$res2['a'].' blocks for '.$name.' the last 24 hours">'.$res2['a'].'</td>';
			echo "<td align=right>{$fees}%</td>";
			echo '<td></td>';
			echo '</tr>';
		};
	};

	$total_coins += $coins;
	$total_miners += $workers;
}

echo "</tbody>";

if($defaultalgo == 'all')
	echo "<tr style='cursor: pointer; background-color: #e0d3e8;' onclick='javascript:select_algo(\"all\")'>";
else
	echo "<tr style='cursor: pointer' class='ssrow' onclick='javascript:select_algo(\"all\")'>";

echo "<td><b>all</b></td>";
echo "<td></td>";
echo '<td align=right style="font-size: .8em;">'.$total_coins.'</td>';
echo '<td align=right style="font-size: .8em;">'.$total_miners.'</td>';
echo "<td></td>";
echo "<td></td>";
echo '<td class="estimate"></td>';
echo '<td class="estimate"></td>';
echo "<td></td>";
echo "</tr>";

echo "</table>";

echo '<p style="font-size: .8em;">&nbsp;* values in mBTC/MH/day, per GH for sha & blake algos</p>';

echo "</div></div><br>";
?>

<?php if (!$showestimates): ?>

<style type="text/css">
#maintable1 .estimate { display: none; }
</style>
<script>
$(document).tooltip({
    items:"[title],[data-title]",
    content: function () { 
        var element = $(this);
        if ( element.is( "[data-title]" ) ) {
            return element.data("title");
            }
        if ( element.is( "[title]" ) ) {
           return element.attr( "title" );
            }
        }
});
</script>

<?php endif; ?>

