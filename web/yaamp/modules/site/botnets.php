<?php
$this->pageTitle = 'Botnets';

echo getAdminSideBarLinks() . '<br/><br/>';

//////////////////////////////////////////////////////////////////////////////////////
JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

echo <<<end
<style type="text/css">
.red { color: darkred; }
table.dataGrid { max-width: 99.5%; }
table.dataGrid a.red { color: darkred; }
</style>
end;


showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	textExtraction: {
		4: function(node, table, n) { return $(node).attr('data'); }
	},
	widgets: ['zebra','Storage','saveSort'],
	widgetOptions: {
		saveSort: true
	}
}");

echo <<<end
<thead>
<tr>
<th data-sorter="" width="20"></th>
<th data-sorter="text">Coin</th>
<th data-sorter="text">Algo</th>
<th data-sorter="text">Address</th>
<th data-sorter="numeric">Time</th>
<th data-sorter="numeric">PID</th>
<th data-sorter="numeric">IPs</th>
<th data-sorter="numeric">Workers</th>
<th data-sorter="text">Version</th>
<th data-sorter="false" align="right" class="actions" width="150">Actions</th>
</tr>
</thead><tbody>
end;


$botnets = dbolist("SELECT userid, algo, pid, max(time) AS time, count(userid) AS workers, count(DISTINCT ip) AS ips, max(version) AS version " . " FROM workers GROUP BY userid, algo, pid HAVING ips > 10 ORDER BY ips DESC");

if (!empty($botnets)) foreach ($botnets as $botnet)
{
    if (!$botnet['userid']) continue;

    $user = getdbo('db_accounts', $botnet['userid']);
    if (!$user) continue;

    $coin = getdbo('db_coins', $user->coinid);
    if (!$coin) continue;

    $coinsym = $coin->symbol;
    $coinimg = CHtml::image($coin->image, $coin->symbol, array(
        'width' => '16'
    ));
    $coinlink = CHtml::link($coin->name, '/site/coin?id=' . $coin->id);

    $d = datetoa2($botnet['time']);

    echo '<tr class="ssrow">';

    echo '<td>' . $coinimg . '</td>';
    echo '<td>' . $coinsym . '</td>';
    echo '<td>' . $botnet['algo'] . '</td>';
    echo '<td>' . CHtml::link($user->username, '/?address=' . $user->username) . '</td>';
    echo '<td data="' . $botnet['time'] . '">' . $d . '</td>';
    echo '<td>' . $botnet['pid'] . '</td>';
    echo '<td>' . $botnet['ips'] . '</td>';
    echo '<td>' . $botnet['workers'] . '</td>';
    echo '<td>' . $botnet['version'] . '</td>';

    echo '<td class="actions" align="right">';

    if ($user->logtraffic) echo '<a href="/site/loguser?id=' . $user->id . '&en=0">unwatch</a> ';
    else echo '<a href="/site/loguser?id=' . $user->id . '&en=1">watch</a> ';

    if ($user->is_locked) echo '<a href="/site/unblockuser?wallet=' . $user->username . '">unblock</a> ';
    else echo '<a href="/site/blockuser?wallet=' . $user->username . '">block</a> ';

    echo '<a href="/site/banuser?id=' . $user->id . '"><span class="red">BAN</span></a>';

    echo '</td>';

    echo '</tr>';
}

echo '</tbody>';

echo '<tfoot>';
if (empty($botnets))
{
    echo '<tr><th colspan="10">' . "No botnets detected" . '</th></tr>';
}
echo '</tfoot>';

echo '</table><br>';
