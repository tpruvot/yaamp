<?php

$exch = getparam('exch');
$mining = getdbosql('db_mining');

$markets = getdbolist('db_markets', "name=:exch ORDER BY ((balance+ontrade)*price) DESC", array(':exch'=>$exch));

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

require_once('yaamp/ui/misc.php');
showFlashMessage();

echo <<<EOT
<style type="text/css">
td.disabled { color: gray; }
table.dataGrid th.ops, td.ops { text-align: right; padding-right: 16px; }
th.btc, td.btc { width: 120px; max-width: 120px; }
th.addr, td.addr { width: 300px; max-width: 300px; text-overflow: ellipsis; overflow: hidden; }
</style>
<br/>
<table class="dataGrid">
<thead>
<tr><th width="20"></th>
<th>Name</th>
<th>Market</th>
<th class="btc">Bid</th>
<th class="btc">Ask</th>
<th title="last backend price update">Updated</th>
<th class="btc">Locked</th>
<th class="btc">Total</th>
<th class="btc">BTC</th>
<th>USD</th>
<th title="last backend balance update">Updated</th>
<th class="addr">Deposit</th>
<th>Status</th>
<th class="ops">API</th>
</tr>
</thead><tbody>
EOT;

$totals_trade = $totals = $totals_usd = 0;

$outdated = time() - 24 * 3600;
$symbols = array();

foreach($markets as $market)
{
	if ($market->pricetime == 0) continue;
	$balance = $market->balance;
	$balance += $market->ontrade;
	if ($balance*$market->price2 < 200*1e-8) continue;

	$coin = getdbo('db_coins', $market->coinid);
	$coinimg = CHtml::image($coin->image, $coin->symbol, array('width'=>'16'));
	$symbol = $coin->symbol;
	if (!empty($coin->symbol2)) $symbol = $coin->symbol2;

	if (arraySafeVal($symbols, $symbol)) continue; // prevent dups
	$symbols[$symbol] = 1;

	$marketurl = getMarketUrl($coin, $market->name);

	echo "<tr class='ssrow'>";

	$btime = $market->balancetime ? datetoa2($market->balancetime). ' ago' : 'never';
	$ptime = $market->pricetime ? datetoa2($market->pricetime). ' ago' : 'never';
	$price = $market->price? bitcoinvaluetoa($market->price): bitcoinvaluetoa($coin->price);
	$price2 = $market->price2? bitcoinvaluetoa($market->price2): bitcoinvaluetoa($coin->price2);
	$ontrade= $market->ontrade ? $market->ontrade : '-';
	$total = bitcoinvaluetoa($balance*$price);
	$total_usd = round($balance*$price * $mining->usdbtc,2);

	$tdclass = $market->disabled ? 'disabled' : '';

	echo '<td width="16" class="'.$tdclass.'">'.$coinimg.'</td>';
	echo '<td><b><a href="/site/coin?id='.$coin->id.'">'.$symbol."</a></td>";
	echo '<td><b><a href="'.$marketurl.'" target="_blank">'.$market->name.'</a></b></td>';
	echo '<td class="btc">'.$price.'</td>';
	echo '<td class="btc">'.$price2.'</td>';
	echo '<td>'.$ptime.'</td>';
	echo '<td>'.$ontrade.'</td>';
	echo '<td>'.$balance.'</td>';
	echo $total>0.1? "<td><b>$total</b></td>": "<td>$total</td>";
	echo $total>0.1? "<td><b>$total_usd</b></td>": '<td>'.sprintf('%.2f',$total_usd).'</td>';
	echo '<td>'.$btime.'</td>';
	echo '<td class="addr">'.$market->deposit_address.'</td>';
	$disabled = $market->disabled > 0 ? 'market disabled ('.$market->disabled.')' : 'OK';
	if (!$coin->enable) $disabled = "coin disabled";
	echo '<td>'.$disabled.'</td>';

	echo '<td class="ops"><a href="/site/balanceUpdate?market='.$market->id.'">update ticker</a></td>';

	$totals_trade += $market->ontrade*$price;
	$totals += $total; $totals_usd += $total_usd;

	echo "</tr>";
}

echo "</tbody><tfoot>";
echo "<tr>";

echo '<th colspan="7"></th>';
echo '<th>Total</th>';
echo '<th>'.bitcoinvaluetoa($totals).'</th>';
echo '<th>'.round($totals_usd,2).'</th>';
echo '<th></th>';
echo '<th></th>';
echo '<th></th>';
echo '<th></th>';

echo "</tr>";
echo "</tfoot>";

echo "</table>";
