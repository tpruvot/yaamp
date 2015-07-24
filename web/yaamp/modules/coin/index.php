<?php

//echo "<a href='/coin/create'>Add a coin</a>";
echo '<br>';

showTableSorter('maintable', '{headers: {0: {sorter: false}}}');
echo "<thead>";

echo "<tr>";
echo "<th width=30></th>";
echo "<th>Name</th>";
echo "<th>Symbol</th>";
echo "<th>Algo</th>";
echo "<th>Status</th>";
echo "<th>Version</th>";
echo "<th>Created</th>";
//echo "<th>Difficulty</th>";
echo "<th>Height</th>";
echo "<th>Message</th>";
echo "<th>Links</th>";
echo "</tr>";
echo "</thead><tbody>";

$total_active = 0;
$total_installed = 0;

$coins = getdbolist('db_coins', "1 order by id desc");
foreach($coins as $coin)
{
//	if($coin->symbol == 'BTC') continue;
	if($coin->enable) $total_active++;
	if($coin->installed) $total_installed++;

	$coin->errors = substr($coin->errors, 0, 30);
	$coin->version = substr($coin->version, 0, 20);
	$difficulty = Itoa2($coin->difficulty, 3);
	$d = datetoa2($coin->created);

	echo "<tr class='ssrow' title='$coin->specifications'>";
	echo "<td><img src='$coin->image' width=18></td>";

	echo "<td><b><a href='/coin/update?id=$coin->id'>$coin->name</a></b></td>";

	if($this->admin)
		echo "<td><b><a href='/site/update?id=$coin->id'>$coin->symbol</a></b></td>";
	else
		echo "<td><b>$coin->symbol</b></td>";

	echo "<td>$coin->algo</td>";

	if($coin->enable)
		echo "<td>running</td>";

	else if($coin->installed)
		echo "<td>installed</td>";

	else
		echo "<td></td>";

	echo "<td>$coin->version</td>";
	echo "<td>$d ago</td>";

//	echo "<td align=right>$difficulty</td>";
	echo "<td align=right>$coin->block_height</td>";

	echo "<td>$coin->errors</td>";
	echo "<td>";

	if(!empty($coin->link_bitcointalk))
		echo "<a href='$coin->link_bitcointalk' target=_blank>forum</a> ";

	if(!empty($coin->link_github))
		echo "<a href='$coin->link_github' target=_blank>git</a> ";

//	if(!empty($coin->link_explorer))
//		echo "<a href='$coin->link_explorer' target=_blank>expl</a> ";

	echo "<a href='http://google.com/search?q=$coin->name%20$coin->symbol%20bitcointalk' target=_blank>google</a> ";

//	if(!empty($coin->link_exchange))
//		echo "<a href='$coin->link_exchange' target=_blank>exch</a> ";

	$list2 = getdbolist('db_markets', "coinid=$coin->id");
	foreach($list2 as $market)
	{
		$url = '';
		$lowsymbol = strtolower($coin->symbol);

		if($market->name == 'cryptsy')
			$url = "https://www.cryptsy.com/markets/view/{$coin->symbol}_BTC";

		else if($market->name == 'bittrex')
			$url = "https://bittrex.com/Market/Index?MarketName=BTC-$coin->symbol";

		else if($market->name == 'poloniex')
			$url = "https://poloniex.com/exchange/btc_$coin->symbol";

		else if($market->name == 'c-cex')
			$url = "https://c-cex.com/?p=$lowsymbol-btc";

		else if($market->name == 'bleutrade')
			$url = "https://bleutrade.com/exchange/$coin->symbol/BTC";

		else if($market->name == 'yobit')
			$url = "https://yobit.net/en/trade/$coin->symbol/BTC";

		else if($market->name == 'cryptopia')
			$url = "https://www.cryptopia.co.nz/Exchange?market={$coin->symbol}_BTC";

		else if($market->name == 'alcurex')
			$url = "https://alcurex.org/index.php/crypto/market?pair={$lowsymbol}_btc";

		echo "<a href='$url' target=_blank>$market->name</a> ";
	}


	echo "</td>";
	echo "</tr>";
}

echo "</tbody>";

$total = count($coins);

echo "<tr class='ssrow'>";
echo "<td></td>";
echo "<td colspan=10><b>$total coins, $total_installed installed, $total_active running</b></td>";
echo "</tr>";

echo "</table>";

echo "<br><br><br><br><br>";
echo "<br><br><br><br><br>";


