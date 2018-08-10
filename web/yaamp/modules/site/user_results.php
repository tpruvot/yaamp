<?php

/////////////////////////////////////////////////////////////////////////////////////////////////

$symbol = getparam('symbol');
$coin = null;

if($symbol == 'all')
	$users = getdbolist('db_accounts', "balance>.001 OR id IN (SELECT DISTINCT userid FROM workers) ORDER BY balance DESC");
else
{
	$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
	if(!$coin) return;

	$users = getdbolist('db_accounts', "coinid={$coin->id} AND (balance>.001 OR id IN (SELECT DISTINCT userid FROM workers)) ORDER BY balance DESC");
}

echo <<<end
<div align="right" style="margin-top: -20px; margin-bottom: 6px;">
<input class="search" type="search" data-column="all" style="width: 140px;" placeholder="Search..." />
</div>
<style type="text/css">
.red { color: darkred; }
tr.ssrow.filtered { display: none; }
</style>
end;

showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	textExtraction: {
		4: function(node, table, cellIndex) { return $(node).attr('data'); },
		6: function(node, table, cellIndex) { return $(node).attr('data'); },
	},
	widgets: ['zebra','filter','Storage','saveSort'],
	widgetOptions: {
		saveSort: true,
		filter_saveFilters: false,
		filter_external: '.search',
		filter_columnFilters: false,
		filter_childRows : true,
		filter_ignoreCase: true
	}
}");

echo <<<end
<thead>
<tr>
<th data-sorter="numeric">UID</th>
<th data-sorter="false">&nbsp;</th>
<th data-sorter="text">Coin</th>
<th data-sorter="text">Address</th>
<th data-sorter="numeric">Last</th>
<th data-sorter="numeric" align="right">Miners</th>
<th data-sorter="numeric" align="right">Hashrate</th>
<th data-sorter="numeric" align="right">Bad</th>
<th data-sorter="numeric" align="right">Blocks</th>
<th data-sorter="numeric" align="right">Diff/Paid</th>
<th data-sorter="currency" align="right">Balance</th>
<th data-sorter="currency" align="right">Total Paid</th>
<th data-sorter="false" align="right" class="actions" width="150">Actions</th>
</tr>
</thead><tbody>
end;

$total_balance = 0;
$total_paid = 0;
$total_unsold = 0;

foreach($users as $user)
{
	$target = yaamp_hashrate_constant();
	$interval = yaamp_hashrate_step(); // 300 seconds
	$delay = time()-$interval;

	$user_rate = dboscalar("SELECT (sum(difficulty) * $target / $interval / 1000) FROM shares WHERE valid AND time>$delay AND userid=".$user->id);
	$user_bad = yaamp_user_rate_bad($user->id);// dboscalar("SELECT (count(id) * $target / $interval / 1000) FROM shares WHERE valid=0 AND time>$delay AND userid=".$user->id);
	$pct_bad = $user_rate? round($user_bad*100/$user_rate, 3): 0;

	$balance = bitcoinvaluetoa($user->balance);
	$paid = dboscalar("SELECT sum(amount) FROM payouts WHERE account_id=".$user->id);
	$d = datetoa2($user->last_earning);

	$miner_count = getdbocount('db_workers', "userid=".$user->id);
	$block_count = getdbocount('db_blocks', "userid=".$user->id);
	$block_diff = ($paid && $block_count) ? round(dboscalar("SELECT sum(difficulty) FROM blocks WHERE userid=".$user->id)/$paid, 3): '?';

	$paid = bitcoinvaluetoa($paid);

	$user_bad = Itoa2($user_bad);

	$coinimg = ''; $coinlink = '';
	$imgopt = array('width'=>'16');
	if ($coin && $user->coinid == $coin->id) {
		$coinimg = CHtml::image($coin->image, $coin->symbol, $imgopt);
		$coinlink = CHtml::link($coin->symbol, '/site/coin?id='.$coin->id);
	} else if ($user->coinid > 0) {
		$user_coin = getdbosql('db_coins', "id=:id", array(':id'=>$user->coinid));
		if ($user_coin) {
			$coinimg = CHtml::image($user_coin->image, $user_coin->symbol, $imgopt);
			$coinlink = CHtml::link($user_coin->symbol, '/site/coin?id='.$user_coin->id);
		}
	}

	echo '<tr class="ssrow">';
	echo '<td width="24">'.$user->id.'</td>';
	echo '<td width="16">'.$coinimg.'</td>';
	echo '<td width="48"><b>'.$coinlink.'</b></td>';
	echo '<td><a href="/?address='.$user->username.'"><b>'.$user->username.'</b></a></td>';
	echo '<td data="'.$user->last_earning.'">'.$d.'</td>';
	echo '<td align=right>'.$miner_count.'</td>';

	echo '<td width="32" data="'.(0+$user_rate).'" align="right">'.($user_rate ? Itoa2($user_rate) : '').'</td>';
	echo '<td width="32" align="right">';
	if ($pct_bad) echo round($pct_bad,1)."&nbsp;%";
	echo '</td>';

	echo '<td align="right">'.$block_count.'</td>';
	echo '<td align="right">'.($user_rate ? $block_diff : '').'</td>';
	echo '<td align="right">'.$balance.'</td>';
	echo '<td align="right">'.$paid.'</td>';

	echo '<td class="actions" align="right">';

	if ($user->logtraffic)
		echo '<a href="/site/loguser?id='.$user->id.'&en=0">unwatch</a> ';
	else
		echo '<a href="/site/loguser?id='.$user->id.'&en=1">watch</a> ';

	if ($user->is_locked)
		echo '<a href="/site/unblockuser?wallet='.$user->username.'">unblock</a> ';
	else
		echo '<a href="/site/blockuser?wallet='.$user->username.'">block</a> ';

	echo '<a href="/site/banuser?id='.$user->id.'"><span class="red">BAN</span></a>';

	echo '</td>';

	echo '</tr>';

	$total_balance += $user->balance;
	$total_paid += $paid;
}

echo "</tbody>";

// totals colspan
$colspan = 7;

$total_balance = bitcoinvaluetoa($total_balance);
$total_paid = bitcoinvaluetoa($total_paid);
$user_count = count($users);

echo '<tr class="ssfoot" style="border-top: 2px solid #eee;">';
echo '<th colspan=3><b>Users Total ('.$user_count.')</b></a></th>';
for ($c=0; $c<$colspan; $c++) echo '<th></th>';
echo '<th align="right"><b>'.$total_balance.'</b></th>';
echo '<th align="right"><b>'.$total_paid.'</b></th>';
echo '<th></th>';
echo '</tr>';

if($coin)
{
	$balance = bitcoinvaluetoa($coin->balance);
	$profit = bitcoinvaluetoa($balance - $total_balance);

	echo '<tr class="ssfoot" style="border-top: 2px solid #eee;"">';
	echo '<th colspan="3"><b>Wallet Balance</b></a></th>';
	for ($c=0; $c<$colspan; $c++) echo '<th></th>';
	echo '<th align="right"><b>'.$balance.'</b></th>';
	echo '<th colspan="2"></th>';
	echo '</tr>';

	echo '<tr class="ssfoot" style="border-top: 2px solid #eee;">';
	echo '<th colspan="3"><b>Wallet Profit</b></a></th>';
	for ($c=0; $c<$colspan; $c++) echo '<th></th>';
	echo '<th align="right"><b>'.$profit.'</b></th>';
	echo '<th colspan="2"></th>';
	echo '</tr>';
}

echo "</table>";

//echo "<p><a href='/site/bonususers'>1% bonus</a></p>";










