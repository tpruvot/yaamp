<?php

function WriteBoxHeader($title)
{
	echo "<div class='main-left-box'>";
	echo "<div class='main-left-title'>$title</div>";
	echo "<div class='main-left-inner'>";
}

$algo = user()->getState('yaamp-algo');
$total_rate = Itoa2(yaamp_pool_rate());

$list = getdbolist('db_coins', "enable and algo=:algo order by index_avg desc", array(':algo'=>$algo));
$count = count($list);
$worker = getdbocount('db_workers', "algo=:algo", array(':algo'=>$algo));
$services = getdbolist('db_services', "algo=:algo order by price desc", array(':algo'=>$algo));

////////////

$table = array(
	'scrypt'=>0,
	'sha256'=>1,
	'scryptn'=>2,
	'x11'=>3,
	'x13'=>4,
	'x15'=>6,
	'nist5'=>7,
	'neoscrypt'=>8,
	'lyra2'=>9,
);

$res = false;
if (isset($table[$algo]) && YAAMP_USE_NICEHASH_API)
	$res = fetch_url("https://api.nicehash.com/api?method=orders.get&algo={$table[$algo]}");

if(!$res) return;

$a = json_decode($res);
$niceorders = $a->result->orders;

$allorders = array();

$nicehash = getdbosql('db_nicehash', "algo=:algo and orderid!=0", array(':algo'=>$algo));
if($nicehash)
{
	$index = $nicehash->price*1000+1;

	$allorders[$index] = array();
	$allorders[$index]['speed'] = $nicehash->accepted;
	$allorders[$index]['price'] = $nicehash->price;
	$allorders[$index]['workers'] = $nicehash->workers;
	$allorders[$index]['btc'] = $nicehash->btc;
	$allorders[$index]['limit'] = $nicehash->speed;
	$allorders[$index]['me'] = true;
}

foreach($niceorders as $order)
{
	if(!$order->alive) continue;
	if(!$order->workers) continue;
	if(!$order->type == 0) continue;

	$index = $order->price*1000;
	if(!isset($allorders[$index]))
	{
		$allorders[$index] = array();

		$allorders[$index]['price'] = $order->price;
		$allorders[$index]['speed'] = 0;
		$allorders[$index]['workers'] = 0;
		$allorders[$index]['btc'] = 0;
		$allorders[$index]['limit'] = 0;
	}

	$allorders[$index]['speed'] += $order->accepted_speed;
	$allorders[$index]['workers'] += $order->workers;
	//$allorders[$index]['btc'] += $order->btc_avail;
	$allorders[$index]['limit'] += $order->limit_speed;
}

$total_nicehash = 0;
foreach($allorders as $i=>$order)
	$total_nicehash += $order['speed'];

function cmp($a, $b)
{
	return $a['price'] < $b['price'];
}

usort($allorders, 'cmp');


///////

WriteBoxHeader("Mining $count coins at {$total_rate}h/s * with $worker miners ($algo)");

echo "<table  class='dataGrid2'>";
echo "<thead>";
echo "<tr>";
echo "<th></th>";
echo "<th>Name</th>";
echo "<th align=right>max</th>";
echo "<th align=right>mbtc</th>";
echo "<th align=right>profit</th>";
echo "<th align=right>TTF</th>";
echo "<th align=right>Hash *</th>";
echo "<th align=right>**</th>";
echo "</tr>";
echo "</thead>";

foreach($list as $coin)
{
	$name = substr($coin->name, 0, 12);
	$difficulty = Itoa2($coin->difficulty, 3);
	$price = bitcoinvaluetoa($coin->price);
	$height = number_format($coin->block_height, 0, '.', ' ');
	$pool_ttf = $coin->pool_ttf? sectoa2($coin->pool_ttf): '';
	$reward = round($coin->reward, 3);
	$btcmhd = mbitcoinvaluetoa(yaamp_profitability($coin));

	$pool_hash = yaamp_coin_rate($coin->id);
	$pool_hash = $pool_hash? Itoa2($pool_hash).'h/s': '';

	show_orders($allorders, $services, $btcmhd);
	show_services($services, $btcmhd);

	if(!$coin->auto_ready)
		echo "<tr style='opacity: 0.4;'>";
	else
		echo "<tr class='ssrow'>";

	echo "<td width=18><img width=16 src='$coin->image'></td>";
	echo "<td><b><a href='/site/coin?id=$coin->id'>$name</a></b></td>";

	echo "<td align=right style='font-size: .8em;'><b>$reward $coin->symbol</a></td>";
	echo "<td align=right style='font-size: .8em;'>$difficulty</td>";

	if(!empty($coin->errors))
		echo "<td align=right style='font-size: .8em; color: red;' title='$coin->errors'>$height</td>";
	else
		echo "<td align=right style='font-size: .8em;'>$height</td>";

	echo "<td align=right style='font-size: .8em;'>$pool_ttf</td>";

	echo "<td align=right style='font-size: .8em;'>$pool_hash</td>";
	echo "<td align=right style='font-size: .8em;'><b>$btcmhd</b></td>";
	echo "</tr>";
}

show_orders($allorders, $services);
show_services($services);

echo "</table><br>";

/////////////////////////////////////////////////////////////////////////////////////////////

$target = yaamp_hashrate_constant($algo);
$interval = yaamp_hashrate_step();
$delay = time()-$interval;
$version = 'NiceHash/1.0.0';

$hashrate = dboscalar("select sum(difficulty) * $target / $interval / 1000 from shares where valid and time>$delay and
	workerid in (select id from workers where algo=:algo and version='$version')", array(':algo'=>$algo));

$invalid = dboscalar("select sum(difficulty) * $target / $interval / 1000 from shares where not valid and time>$delay and
	workerid in (select id from workers where algo=:algo and version='$version')", array(':algo'=>$algo));

$count = getdbocount('db_workers', "algo=:algo and version='$version'", array(':algo'=>$algo));

$percent = $total_nicehash&&$hashrate? round($hashrate * 100 / $total_nicehash / 1000000000, 2).'%': '';

$hashrate = $hashrate? Itoa2($hashrate).'h/s': '';
$version = substr($version, 0, 30);

$total_nicehash = round($total_nicehash, 3);
echo "Total Nicehash: <b>$total_nicehash Gh/s</b> *** yaamp: ";
echo "<b>$hashrate</b> ";
echo "$count workers ";
echo "$percent ";

echo "</div></div><br>";

//////////////////////////////////////////////////////////////////////////////////////////////////

function show_services(&$services, $btcmhd=0)
{
	if(!controller()->admin || !$services) return;
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

function show_orders(&$allorders, &$services, $btcmhd=0)
{
	$algo = user()->getState('yaamp-algo');
	$price = controller()->memcache->get_database_scalar("current_price-$algo",
		"select price from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));

	foreach($allorders as $i=>$order)
	{
		if($order['price'] < $btcmhd) continue;
		if($order['workers'] <= 0 && !isset($order['me'])) continue;
		if($order['speed'] <= 0 && !isset($order['me'])) continue;

		$service_btcmhd = mbitcoinvaluetoa($order['price']);
		$hash = Itoa2($order['speed']*1000000000).'h/s';
		$limit = Itoa2($order['limit']*1000000000).'h/s';
		$btc = round($order['btc']*1000, 1);
		$profit = $price>$service_btcmhd? round(($price-$service_btcmhd)/$service_btcmhd*100).'%': '';

		show_services($services, $service_btcmhd);

		if(isset($order['me']))
			echo "<tr class='ssrow' style='background-color: #dfd'>";
		else
			echo "<tr class='ssrow'>";

		echo "<td></td>";
		echo "<td><b>$hash</b> ({$order['workers']})</td>";
		echo "<td align=right style='font-size: .8em;'>$limit</td>";
		echo "<td align=right style='font-size: .8em;'>$btc</td>";
		echo "<td align=right style='font-size: .8em;'><b>$profit</b></td>";
		echo "<td></td>";
		echo "<td></td>";
		echo "<td align=right style='font-size: .8em;'><b>$service_btcmhd</b></td>";
		echo "</tr>";

		unset($allorders[$i]);
	}
}



