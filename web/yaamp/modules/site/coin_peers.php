<?php
if (!$coin) $this->goback();

$this->pageTitle = 'Peers - ' . $coin->symbol;

$remote = new WalletRPC($coin);
$info = $remote->getinfo();

echo getAdminSideBarLinks() . '<br/><br/>';
echo getAdminWalletLinks($coin, $info, 'peers') . '<br/><br/>';

//////////////////////////////////////////////////////////////////////////////////////
JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

echo <<<end
<style type="text/css">
td.red { color: darkred; }
table.dataGrid a.red { color: darkred; }
div.form { text-align: right; height: 30px; width: 350px; float: right; margin-top: -48px; margin-bottom: 16px; margin-right: -8px; }
.main-submit-button { cursor: pointer; }
</style>

<div class="form">
<form action="/site/peerAdd?id={$coin->id}" method="post" style="padding: 8px;">
<input type="text" name="node" class="main-text-input" placeholder="addr[:port]" autocomplete="off" style="width: 150px; margin-right: 4px;">
<input type="submit" value="Add node" class="main-submit-button" >
</form>
</div>
end;


showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	headers: {
		0:{sorter:'text'},
		1:{sorter:'text'},
		2:{sorter:'numeric'},
		3:{sorter:'numeric'},
		4:{sorter:'text'},
		5:{sorter:'metadata'},
		6:{sorter:'metadata'},
		7:{sorter:'numeric'},
		7:{sorter:false}
	},
	widgets: ['zebra','Storage','saveSort'],
	widgetOptions: {
		saveSort: true
	}
}");

echo <<<end
<thead>
<tr>
<th>Address</th>
<th>Version</th>
<th>Height</th>
<th>Ping</th>
<th>Services</th>
<th>Since</th>
<th>Last</th>
<th>Rx / Tx (kB)</th>
<th width="30"></th>
</tr>
</thead><tbody>
end;


$addnode = array();
$version = '';
$localheight = arraySafeVal($info, 'blocks');

$list = $remote->getpeerinfo();

if (!empty($list)) foreach ($list as $peer)
{
    echo '<tr class="ssrow">';

    $node = arraySafeVal($peer, 'addr');
    echo '<td>' . $node . '</td>';
    $addnode[] = ($coin->rpcencoding == 'DCR' ? 'addpeer=' : 'addnode=') . $node;

    $peerver = trim(arraySafeVal($peer, 'subver') , '/');
    $version = max($version, $peerver);
    echo '<td>' . $peerver . '</td>';

    $height = arraySafeVal($peer, 'currentheight');
    $class = abs($height - $localheight) > 5 ? 'red' : '';
    if (!$height) $height = arraySafeVal($peer, 'synced_blocks');
    echo '<td class="' . $class . '">' . $height . '</td>';

    echo '<td>' . arraySafeVal($peer, 'pingtime', '') . '</td>';
    echo '<td>' . arraySafeVal($peer, 'services', '') . '</td>';

    $conntime = arraySafeVal($peer, 'conntime', time());
    $startingheight = arraySafeVal($peer, 'startingheight');
    echo '<td>' . datetoa2($conntime) . " ($startingheight)" . '</td>';

    $lastrecv = arraySafeVal($peer, 'lastrecv', time());
    $lastsend = arraySafeVal($peer, 'lastsend', time());
    echo '<td>' . datetoa2(max($lastrecv, $lastsend)) . '</td>';

    $bytesrecv = round(arraySafeVal($peer, 'bytesrecv') / 1024., 1);
    $bytessent = round(arraySafeVal($peer, 'bytessent') / 1024., 1);
    if ($bytesrecv + $bytessent) echo '<td>' . "$bytesrecv / $bytessent" . '</td>';
    else echo '<td></td>';

    echo '<td>';
    $options = array(
        'class' => 'red',
        'title' => 'Disconnect from node'
    );
    echo CHtml::link('remove', '/site/peerRemove?id=' . $coin->id . '&node=' . $node, $options);
    echo '</td>';

    echo '</tr>';
}

echo '</tbody></table><br>';

echo '<b>Local version: </b>' . formatWalletVersion($coin) . ' ';
echo '<b>Latest : </b>' . $version;

echo '<pre>';
echo implode("\n", $addnode);
echo '</pre>';
//echo json_encode($list);
