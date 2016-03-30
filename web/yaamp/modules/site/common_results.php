<?php

$mining = getdbosql('db_mining');

$showrental = (bool) YAAMP_RENTAL;

echo '<br><table width="100%"><tr><td valign="top">';

///////////////////////////////////////////////////////////////////////////////////////////////////////

echo '<table class="dataGrid">';
echo '<thead>';
echo '<tr>';
echo '<th align="left">Algo</th>';
echo '<th align="right"></th>';
echo '<th align="right">C</th>';
echo '<th align="right">M</th>';
echo '<th align="right">Fee</th>';
echo '<th align="right">Rate</th>';
echo '<th align="right" class="rental">Rent</th>';
echo '<th align="right">Bad</th>';
echo '<th align="right">Now</th>';
echo '<th align="right" class="rental">Rent</th>';
//echo '<th align="right">Norm</th>';
echo '<th align="right">24E</th>';
echo '<th align="right">24A</th>';
echo '</tr>';
echo '</thead>';

$total_coins = 0;
$total_workers = 0;
$total_hashrate = 0;
$total_hashrate_bad = 0;

$algos = array();
foreach(yaamp_get_algos() as $algo)
{
	$algo_norm = yaamp_get_algo_norm($algo);

	$price = controller()->memcache->get_database_scalar("current_price-$algo",
		"select price from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));

	$norm = $price*$algo_norm;
	$norm = take_yaamp_fee($norm, $algo);

	$algos[] = array($norm, $algo);
}

function cmp($a, $b)
{
	return $a[0] < $b[0];
}

usort($algos, 'cmp');
foreach($algos as $item)
{
	$norm = $item[0];
	$algo = $item[1];

	$algo_color = getAlgoColors($algo);
	$algo_norm = yaamp_get_algo_norm($algo);

//	debuglog($algo);
	$coins = getdbocount('db_coins', "enable and auto_ready and algo=:algo", array(':algo'=>$algo));
	$count = getdbocount('db_workers', "algo=:algo", array(':algo'=>$algo));

	$total_coins += $coins;
	$total_workers += $count;

	$hashrate = dboscalar("select hashrate from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));
	$hashrate_bad = dboscalar("select hashrate_bad from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));
	$bad = ($hashrate+$hashrate_bad)? round($hashrate_bad * 100 / ($hashrate+$hashrate_bad), 1): '';

	$total_hashrate += $hashrate;
	$total_hashrate_bad += $hashrate_bad;

	$hashrate = $hashrate? Itoa2($hashrate).'h/s': '-';
	$hashrate_bad = $hashrate_bad? Itoa2($hashrate_bad).'h/s': '-';

	$hashrate_jobs = yaamp_rented_rate($algo);
	$hashrate_jobs = $hashrate_jobs>0? Itoa2($hashrate_jobs).'h/s': '';

	$price = dboscalar("select price from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));
	$price = $price? mbitcoinvaluetoa($price): '-';

	$rent = dboscalar("select rent from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));
	$rent = $rent? mbitcoinvaluetoa($rent): '-';

	$norm = mbitcoinvaluetoa($norm);

	$t = time() - 24*60*60;
	$avgprice = dboscalar("select avg(price) from hashrate where algo=:algo and time>$t", array(':algo'=>$algo));
	$avgprice = $avgprice? mbitcoinvaluetoa(take_yaamp_fee($avgprice, $algo)): '-';

	$t1 = time() - 24*60*60;
	$total1 = dboscalar("select sum(amount*price) from blocks where category!='orphan' and time>$t1 and algo=:algo", array(':algo'=>$algo));
	$hashrate1 = dboscalar("select avg(hashrate) from hashrate where time>$t1 and algo=:algo", array(':algo'=>$algo));

	$algo_unit_factor = yaamp_algo_mBTC_factor($algo);
	$btcmhday1 = $hashrate1 != 0? mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 1000 * $algo_unit_factor): '';

	$fees = yaamp_fee($algo);

	$stratum = getdbosql('db_stratums', "algo=:algo", array(':algo'=>$algo));
	$isup = Booltoa($stratum);

	echo '<tr class="ssrow">';
	echo '<td style="background-color: '.$algo_color.'"><b>';
	echo CHtml::link($algo, '/site/gomining?algo='.$algo);
	echo '</b></td>';
	echo '<td align="right" style="width: 16px;">'.$isup.'</td>';
	echo '<td align="right" style="font-size: .8em;">'.(empty($coins) ? '-' : $coins).'</td>';
	echo '<td align="right" style="font-size: .8em;">'.(empty($count) ? '-' : $count).'</td>';
	echo '<td align="right" style="font-size: .8em;">'.(empty($fees) ? '-' : "$fees %").'</td>';
	echo '<td align="right" style="font-size: .8em;">'.$hashrate.'</td>';
	echo '<td align="right" style="font-size: .8em;" class="rental">'.$hashrate_jobs.'</td>';

	if ($bad > 10)
		echo '<td align="right" style="font-size: .8em; color: white; background-color: #d9534f">'.$bad.'%</td>';
	else if($bad > 5)
		echo '<td align="right" style="font-size: .8em; color: white; background-color: #f0ad4e">'.$bad.'%</td>';
	else
		echo '<td align="right" style="font-size: .8em;">'.(empty($bad) ? '-' : "$bad %").'</td>';

	if ($norm>0)
		echo '<td align=right style="font-size: .8em;" title="normalized '.$norm.'">'.($price == 0.0 ? '-' : $price).'</td>';
	else
		echo '<td align=right style="font-size: .8em;">'.($price == 0.0 ? '-' : $price).'</td>';

	echo '<td align="right" style="font-size: .8em;" class="rental">'.$rent.'</td>';
	echo '<td align="right" style="font-size: .8em;">'.($avgprice == 0.0 ? '-' : $avgprice).'</td>';

	if($btcmhday1 != '-' && $btcmhday1 > $avgprice*1.1)
		echo '<td align="right" style="font-size: .8em; color: white; background-color: #5cb85c">'.$btcmhday1.'</td>';
	else if($btcmhday1 != '-' && $btcmhday1*1.3 < $avgprice)
		echo '<td align="right" style="font-size: .8em; color: white; background-color: #d9534f">'.$btcmhday1.'</td>';
	else if($btcmhday1 != '-' && $btcmhday1*1.2 < $avgprice)
		echo '<td align="right" style="font-size: .8em; color: white; background-color: #e4804e">'.$btcmhday1.'</td>';
	else if($btcmhday1 != '-' && $btcmhday1*1.1 < $avgprice)
		echo '<td align="right" style="font-size: .8em; color: white; background-color: #f0ad4e">'.$btcmhday1.'</td>';
	else
		echo '<td align="right" style="font-size: .8em;">'.($btcmhday1 == 0.0 ? '-' : $btcmhday1).'</td>';

	echo '</tr>';
}

$bad = ($total_hashrate+$total_hashrate_bad)? round($total_hashrate_bad * 100 / ($total_hashrate+$total_hashrate_bad), 1): '';
$total_hashrate = Itoa2($total_hashrate).'h/s';

echo '<tr class="ssrow">';
echo '<td colspan="2"></td>';
echo '<td align="right" style="font-size: .8em;">'.$total_coins.'</td>';
echo '<td align="right" style="font-size: .8em;">'.$total_workers.'</td>';
echo '<td align="right" style="font-size: .8em;"></td>';
echo '<td align="right" style="font-size: .8em;">'.$total_hashrate.'</td>';
echo '<td align="right" style="font-size: .8em;" class="rental"></td>';
echo '<td align="right" style="font-size: .8em;">'.$bad.'%</td>';
echo '<td align="right" style="font-size: .8em;"></td>';
echo '<td align="right" style="font-size: .8em;"></td>';
echo '<td align="right" style="font-size: .8em;"></td>';
echo '</tr>';

echo '</table><br>';

///////////////////////////////////////////////////////////////////////////////////////////////////////

$markets = getdbolist('db_balances', "1 order by name");
$total_balance = 0;
$total_onsell = 0;
$total_total = 0;

echo '<table class="dataGrid">';
echo '<thead>';

echo '<tr>';
echo '<th></th>';

foreach($markets as $market)
	echo '<th align="right"><a href="/site/runExchange?id='.$market->id.'">'.$market->name.'</a></th>';

echo '<th align="right">Total</th>';

echo '</tr>';
echo '</thead>';

echo '<tr class="ssrow"><td>to sell</td>';
foreach($markets as $market)
{
	$onsell = bitcoinvaluetoa(dboscalar("select sum(amount*bid) from orders where market='$market->name'"));

	if($onsell > 0.2)
		echo '<td align="right" style="color: white; background-color: #d9534f">'.$onsell.'</td>';
	else if($onsell > 0.1)
		echo '<td align="right" style="color: white; background-color: #f0ad4e">'.$onsell.'</td>';
	else if($onsell == 0.0)
		echo '<td align="right">-</td>';
	else
		echo '<td align="right">'.$onsell.'</td>';

	$total_onsell += $onsell;
}

$total_onsell = bitcoinvaluetoa($total_onsell);

echo '<td align="right" style="color: white; background-color: #c5b47f">'.$total_onsell.'</td>';
echo '</tr>';

echo '<tr class="ssrow"><td>balance</td>';
foreach($markets as $market)
{
	$balance = bitcoinvaluetoa($market->balance);

	if($balance > 0.250)
		echo '<td align="right" style="color: white; background-color: #5cb85c">'.$balance.'</td>';
	else if($balance > 0.200)
		echo '<td align="right" style="color: white; background-color: #f0ad4e">'.$balance.'</td>';
	else if($market->balance == 0.0)
		echo '<td align="right">-</td>';
	else
		echo '<td align="right">'.$balance.'</td>';

	$total_balance += $balance;
}

$total_balance = bitcoinvaluetoa($total_balance);

echo '<td align="right" style="color: white; background-color: #eaa228">'.$total_balance.'</td>';
echo '</tr>';

echo '<tr class="ssrow"><td>total</td>';
foreach($markets as $market)
{
	$total = $market->balance + dboscalar("select sum(amount*bid) from orders where market='$market->name'");

	echo '<td align="right">'.($total > 0.0 ? bitcoinvaluetoa($total) : '-').'</td>';
	$total_total += $total;
}

$total_total = bitcoinvaluetoa($total_total);

echo '<td align="right">'.$total_total.'</td>';
echo '</tr>';
echo '</table><br/>';

//////////////////////////////////////////////////////////////////////////////////////////////////

$minsent = time()-2*60*60;
$list = getdbolist('db_markets', "lastsent<$minsent and lastsent>lasttraded order by lastsent");

echo '<table class="dataGrid">';
echo '<thead class="">';

echo '<tr>';
echo '<th width="20px"></th>';
echo '<th>Name</th>';
echo '<th>Exchange</th>';
echo '<th>Sent</th>';
echo '<th>Traded</th>';
echo '<th></th>';
echo '</tr>';
echo '</thead><tbody>';

foreach($list as $market)
{
	$price = bitcoinvaluetoa($market->price);
	$coin = getdbo('db_coins', $market->coinid);

	$marketurl = getMarketUrl($coin, $market->name);

//	echo '<tr class="ssrow">';
	$algo_color = getAlgoColors($coin->algo);
	echo '<tr style="background-color: '.$algo_color.';">';

	echo '<td><img width="16px" src="'.$coin->image.'"></td>';
	echo '<td><b><a href="/site/coin?id='.$coin->id.'">'.$coin->name.' ('.$coin->symbol.')</a></b></td>';

	echo '<td><b><a href="'.$marketurl.'" target="_blank">'.$market->name.'</a></b></td>';

	$sent = datetoa2($market->lastsent);
	$traded = datetoa2($market->lasttraded);

	echo '<td>'.$sent.' ago</td>';
	echo '<td>'.$traded.' ago</td>';

	echo '<td><a href="/site/clearmarket?id='.$market->id.'">clear</a></td>';
	echo '</tr>';
}

echo '</tbody></table><br>';

//////////////////////////////////////////////////////////////////////////////////////////////////

$orders = getdbolist('db_orders', "1 order by (amount*bid) desc");

echo '<table class="dataGrid">';
//showTableSorter('maintable');
echo '<thead>';
echo '<tr>';
echo '<th width="20px"></th>';
echo '<th>Name</th>';
echo '<th>Exchange</th>';
echo '<th>Created</th>';
echo '<th>Quantity</th>';
echo '<th>Ask</th>';
echo '<th>Bid</th>';
echo '<th>Value</th>';
echo '<th></th>';
echo '</tr>';
echo '</thead><tbody>';

$totalvalue = 0;
$totalbid = 0;

foreach($orders as $order)
{
	$coin = getdbo('db_coins', $order->coinid);
	if(!$coin) continue;

	$marketurl = getMarketUrl($coin, $order->market);

	$algo_color = getAlgoColors($coin->algo);
	echo '<tr class="ssrow" style="background-color: '.$algo_color.';">';

	$created = datetoa2($order->created). ' ago';
	$price = $order->price? bitcoinvaluetoa($order->price): '';

	$price = bitcoinvaluetoa($order->price);
	$bid = bitcoinvaluetoa($order->bid);
	$value = bitcoinvaluetoa($order->amount*$order->price);
	$bidvalue = bitcoinvaluetoa($order->amount*$order->bid);
	$totalvalue += $value;
	$totalbid += $bidvalue;
	$bidpercent = $value>0? round(($value-$bidvalue)/$value*100, 1): 0;
	$amount = round($order->amount, 3);

	echo '<td><img width="16px" src="'.$coin->image.'"></td>';
	echo '<td><b><a href="/site/coin?id='.$coin->id.'">'.$coin->name.'</a></b></td>';
	echo '<td><b><a href="'.$marketurl.'" target="_blank">'.$order->market.'</a></b></td>';

	echo '<td style="font-size: .8em">'.$created.'</td>';
	echo '<td style="font-size: .8em">'.$amount.'</td>';
	echo '<td style="font-size: .8em">'.$price.'</td>';
	echo '<td style="font-size: .8em">'."$bid ({$bidpercent}%)".'</td>';
	echo $bidvalue>0.01? '<td style="font-size: .8em;"><b>'.$bidvalue.'</b></td>': '<td style="font-size: .8em;">'.$bidvalue.'</td>';

	echo '<td>';
	echo '<a href="/site/cancelorder?id='.$order->id.'" title="Cancel the order on the exchange!">cancel</a> ';
	echo '<a href="/site/clearorder?id='.$order->id.'" title="Clear the order from the DB, NOT FROM THE EXCHANGE!">clear</a> ';
//	echo '<a href="/site/sellorder?id='.$order->id.'">sell</a>';
	echo '</td>';
	echo '</tr>';
}

$bidpercent = $totalvalue>0? round(($totalvalue-$totalbid)/$totalvalue*100, 1): '';

if ($totalvalue) {
echo '<tr>';
echo '<td></td>';
echo '<td>Total</td>';
echo '<td colspan="3"></td>';
echo '<td style="font-size: .8em;"><b>'.$totalvalue.'</b></td>';
echo '<td style="font-size: .8em;"><b>'."$totalbid ({$bidpercent}%)</b></td>";
echo '<td></td>';
echo '</tr>';
}

echo '</tbody></table><br>';

///////////////////////////////////////////////////////////////////////////////////////

echo '</td><td>&nbsp;&nbsp;</td><td valign="top">';

//////////////////////////////////////////////////////////////////////////////////

function cronstate2text($state)
{
	switch($state)
	{
		case 0:
			return '';
		case 1:
			return 'new coins';
		case 2:
			return 'trading';
		case 3:
			return 'markets';
		case 4:
			return 'blocks';
		case 5:
			return 'sell';
		case 6:
			return 'find2';
	}
}

//$state_block = memcache_get($this->memcache->memcache, 'cronjob_block_state');
$state_main = memcache_get($this->memcache->memcache, 'cronjob_main_state');
$btc = getdbosql('db_coins', "symbol='BTC'");
if (!$btc) $btc = json_decode('{"id": 6, "balance": 0}');

echo '<span style="font-weight: bold; color: red;">';
for($i=0; $i<10; $i++)
{
// 	if($i != $state_block-1 && $state_block>0)
// 	{
// 		$state = memcache_get($this->memcache->memcache, "cronjob_block_state_$i");
// 		if($state) echo "block $i ";
// 	}

	if($i != $state_main-1 && $state_main>0)
	{
		$state = memcache_get($this->memcache->memcache, "cronjob_main_state_$i");
		if($state) echo "main $i ";
	}
}

echo '</span>';

$block_time = sectoa(time()-memcache_get($this->memcache->memcache, "cronjob_block_time_start"));
$loop2_time = sectoa(time()-memcache_get($this->memcache->memcache, "cronjob_loop2_time_start"));
$main_time2 = sectoa(time()-memcache_get($this->memcache->memcache, "cronjob_main_time_start"));

$main_time = sectoa(memcache_get($this->memcache->memcache, "cronjob_main_time"));
$main_text = cronstate2text($state_main);

echo "*** main  ($main_time) $state_main $main_text ($main_time2), loop2 ($loop2_time), block ($block_time)<br>";

$topay = dboscalar("select sum(balance) from accounts where coinid=$btc->id");	//here: take other currencies too
$topay2 = bitcoinvaluetoa(dboscalar("select sum(balance) from accounts where coinid=$btc->id and balance>0.001"));

$renter = dboscalar("select sum(balance) from renters");

$stats = getdbosql('db_stats', "1 order by time desc");
$margin2 = bitcoinvaluetoa($btc->balance - $topay - $renter + $stats->balances + $stats->onsell + $stats->wallets);

$margin = bitcoinvaluetoa($btc->balance - $topay - $renter);

$topay = bitcoinvaluetoa($topay);
$renter = bitcoinvaluetoa($renter);

$immature = dboscalar("select sum(amount*price) from earnings where status=0");
$mints = dboscalar("select sum(mint*price) from coins where enable");
$off = $mints-$immature;

$immature = bitcoinvaluetoa($immature);
$mints = bitcoinvaluetoa($mints);
$off = bitcoinvaluetoa($off);

$btcaddr = YAAMP_BTCADDRESS; //'14LS7Uda6EZGXLtRrFEZ2kWmarrxobkyu9';

echo '<a href="https://www.okcoin.com/market.do" target="_blank">Bitstamp '.$mining->usdbtc.'</a>, ';
echo '<a href="https://blockchain.info/address/'.$btcaddr.'" target="_blank">wallet '.$btc->balance.'</a>, next payout '.$topay2.'<br/>';

echo "pay $topay, renter $renter, marg $margin, $margin2<br/>";
echo "mint $mints immature $immature off $off<br/>";

echo '<br/>';

//////////////////////////////////////////////////////////////////////////////////////////////////

echo '<div style="height: 160px;" id="graph_results_negative"></div>';
//echo '<div style="height: 160px;' id="graph_results_profit"></div>';
echo '<div style="height: 200px;" id="graph_results_assets"></div>';

///////////////////////////////////////////////////////////////////////////

$db_blocks = getdbolist('db_blocks', "1 order by time desc limit 50");

echo '<br><table class="dataGrid">';
echo '<thead>';
echo '<tr>';
echo '<th></th>';
echo '<th>Name</th>';
echo '<th align=right>Amount</th>';
echo '<th align=right>Diff</th>';
echo '<th align=right>Block</th>';
echo '<th align=right>Time</th>';
echo '<th align=right>Status</th>';
echo '</tr>';
echo '</thead>';

foreach($db_blocks as $db_block)
{
	$d = datetoa2($db_block->time);
	if(!$db_block->coin_id)
	{
		if (!$showrental)
			continue;

		$reward = bitcoinvaluetoa($db_block->amount);

		$algo_color = getAlgoColors($db_block->algo);
		echo '<tr style="background-color: '.$algo_color.';">';
		echo '<td width="18px"><img width="16px" src="/images/btc.png"></td>';
		echo '<td><b>Rental</b> ('.$db_block->algo.')</td>';
		echo '<td align="right" style="font-size: .8em"><b>$reward BTC</b></td>';
		echo '<td align="right" style="font-size: .8em"></td>';
		echo '<td align="right" style="font-size: .8em"></td>';
		echo '<td align="right" style="font-size: .8em">'.$d.' ago</td>';
		echo '<td align="right" style="font-size: .8em">';
		echo '<span style="padding: 2px; color: white; background-color: #5cb85c;">Confirmed</span>';
		echo '</td>';
		echo '</tr>';
		continue;
	}

	$coin = getdbo('db_coins', $db_block->coin_id);
	if(!$coin)
	{
		debuglog("coin not found {$db_block->coin_id}");
		continue;
	}

	$height = number_format($db_block->height, 0, '.', ' ');
	$diff = Itoa2($db_block->difficulty, 3);

	$algo_color = getAlgoColors($coin->algo);
	echo '<tr style="background-color: '.$algo_color.';">';
	echo '<td width="18px"><img width="16px" src="'.$coin->image.'"></td>';
	echo '<td><b><a href="/site/coin?id='.$coin->id.'">'.$coin->name.'</a></b></td>';

	echo '<td align="right" style="font-size: .8em">'.$db_block->amount.' '.$coin->symbol.'</td>';
	echo '<td align="right" style="font-size: .8em" title="found '.$db_block->difficulty_user.'">'.$diff.'</td>';

	echo '<td align="right" style="font-size: .8em">'.$height.'</td>';
	echo '<td align="right" style="font-size: .8em">'.$d.' ago</td>';
	echo '<td align="right" style="font-size: .8em">';

	if($db_block->category == 'orphan')
		echo '<span class="block orphan" style="padding: 2px; color: white; background-color: #d9534f;">Orphan</span>';

	else if($db_block->category == 'immature')
		echo '<span class="block immature" style="padding: 2px; color: white; background-color: #f0ad4e">Immature ('.$db_block->confirmations.')</span>';

	else if($db_block->category == 'stake')
		echo '<span class="block stake" style="padding: 2px; color: white; background-color: #a0a0a0">Stake ('.$db_block->confirmations.')</span>';

	else if($db_block->category == 'generated')
		echo '<span class="block staked" style="padding: 2px; color: white; background-color: #a0a0a0">Confirmed</span>';

	else if($db_block->category == 'generate')
		echo '<span class="block generate" style="padding: 2px; color: white; background-color: #5cb85c">Confirmed</span>';

	else if($db_block->category == 'new')
		echo '<span class="block new" style="padding: 2px; color: white; background-color: #ad4ef0">New</span>';

	echo '</td>';
	echo '</tr>';
}


echo '</table><br/>';

echo '</td></tr></table>';

?>

<?php if (!$showrental) : ?>

<style type="text/css">
.dataGrid .rental { display: none; }
</style>

<?php endif; ?>

