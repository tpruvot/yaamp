<?php

echo getAdminSideBarLinks();

echo <<<end
<div align="right" style="margin-top: -14px; margin-bottom: 6px;">
<input class="search" type="search" data-column="all" style="width: 140px;" placeholder="Search..." />
</div>
<style type="text/css">
tr.ssrow.filtered { display: none; }
</style>
end;

showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	headers: { 1: { sorter: false} },
	widgets: ['zebra','filter'],
	widgetOptions: {
		filter_external: '.search',
		filter_columnFilters: false,
		filter_childRows : true,
		filter_ignoreCase: true
	}
}");

echo <<<end
<thead>
<tr>
<th>UID</th>
<th></th>
<th>Coin</th>
<th>Address</th>
<th></th>
<th>Last</th>
<th>Blocks</th>
<th>Balance</th>
<th>Total Paid</th>
<th>Miners</th>
<th>Shares</th>
<th></th>
<th></th>
</tr>
</thead>
<tbody>
end;

function showUser($userid, $what)
{
	$user = getdbo('db_accounts', $userid);
	if(!$user) return;

	$d = datetoa2($user->last_earning);
	$balance = bitcoinvaluetoa($user->balance);
	$paid = dboscalar("select sum(amount) from payouts where account_id=$user->id");
	$paid = bitcoinvaluetoa($paid);

	$t = time()-24*60*60;

	$miner_count = getdbocount('db_workers', "userid=$user->id");
	$share_count = getdbocount('db_shares', "userid=$user->id");
	$block_count = getdbocount('db_blocks', "userid=$user->id and time>$t");

	$coin = getdbo('db_coins', $user->coinid);

	echo "<tr class='ssrow'>";

	echo "<td width=24>$user->id</td>";

	if(!$coin)
		echo '<td width=60 colspan="2"></td>';
	else {
		$coinlink = CHtml::link($coin->symbol, '/site/coin?id='.$coin->id);
		echo '<td width=16><img src="'.$coin->image.'" width="16"></td><td width=48><b>'.$coinlink.'</b></td>';
	}

	echo "<td><a href='/site?address=$user->username'><b>$user->username</a></b></td>";
	echo "<td>$what</td>";
	echo "<td>$d</td>";

	echo "<td>$block_count</td>";
	echo "<td>$balance</td>";

	if(intval($paid) > 0.01)
		echo "<td><b>$paid</b></td>";
	else
		echo "<td>$paid</td>";

	echo "<td>$miner_count</td>";
	echo "<td>$share_count</td>";

	if($user->is_locked)
	{
		echo "<td>locked</td>";
		echo "<td><a href='/site/unblockuser?wallet=$user->username'>unblock</a></td>";
	}

	else
	{
		echo "<td></td>";
		echo "<td><a href='/site/blockuser?wallet=$user->username'>block</a></td>";
	}

	echo "</tr>";
}

$t = time()-24*60*60;

$list = dbolist("select userid from shares where pid is null or pid not in (select pid from stratums) group by userid");
foreach($list as $item)
	showUser($item['userid'], 'pid');

$list = dbolist("select id from accounts where balance>0.001 and id not in (select distinct userid from blocks where userid is not null and time>$t)");
foreach($list as $item)
	showUser($item['id'], 'blocks');

$monsters = dbolist("SELECT COUNT(*) AS total, userid FROM workers GROUP BY userid ORDER BY total DESC LIMIT 5");
foreach($monsters as $item)
	showUser($item['userid'], 'miners');

$monsters = dbolist("SELECT COUNT(*) AS total, workerid FROM shares GROUP BY workerid ORDER BY total DESC LIMIT 5");
foreach($monsters as $item)
{
	$worker = getdbo('db_workers', $item['workerid']);
	if(!$worker) continue;

	showUser($worker->userid, 'shares');
}

$list = getdbolist('db_accounts', "is_locked");
foreach($list as $user)
	showUser($user->id, 'locked');

echo "</tbody></table>";











