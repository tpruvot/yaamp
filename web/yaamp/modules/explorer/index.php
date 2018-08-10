<?php

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

echo <<<end
<script type="text/javascript">
function wallet_peers(id)
{
	var w = window.open("/explorer/peers?id=" + id, "peers",
		"width=400,height=600,location=no,menubar=no,resizable=yes,status=no,toolbar=no");
}
</script>

<style type="text/css">
a.low { color: red; font-weight: bold; }
</style>

<br/>
<div class="main-left-box">
<div class="main-left-title">Block Explorer</div>
<div class="main-left-inner">
end;

showTableSorter('maintable', "{
	tableClass: 'dataGrid2',
	textExtraction: {
		6: function(node, table, n) { return $(node).attr('data'); },
		8: function(node, table, n) { return $(node).attr('data'); }
	}
}");

echo <<<end
<thead>
<tr>
<th width="30" data-sorter=""></th>
<th>Name</th>
<th>Symbol</th>
<th>Algo</th>
<th>Version</th>
<th>Height</th>
<th>Difficulty</th>
<th>Connections</th>
<th>Network Hash</th>
<th data-sorter=""></th>
</tr>
</thead><tbody>
end;

$list = getdbolist('db_coins', "enable and visible order by name");
foreach($list as $coin)
{
	if($coin->symbol == 'BTC') continue;
	if(!empty($coin->symbol2)) continue;

	$coin->version = formatWalletVersion($coin);

	//if (!$coin->network_hash)
		$coin->network_hash = controller()->memcache->get("yiimp-nethashrate-{$coin->symbol}");
	if (!$coin->network_hash) {
		$remote = new WalletRPC($coin);
		if ($remote)
			$info = $remote->getmininginfo();
		if (isset($info['networkhashps'])) {
			$coin->network_hash = $info['networkhashps'];
			controller()->memcache->set("yiimp-nethashrate-{$coin->symbol}", $info['networkhashps'], 60);
		}
		else if (isset($info['netmhashps'])) {
			$coin->network_hash = floatval($info['netmhashps']) * 1e6;
			controller()->memcache->set("yiimp-nethashrate-{$coin->symbol}", $coin->network_hash, 60);
		}
	}

	$difficulty = Itoa2($coin->difficulty, 3);
	$nethash_sfx = $coin->network_hash? strtoupper(Itoa2($coin->network_hash)).'H/s': '';

	echo '<tr class="ssrow">';
	echo '<td><img src="'.$coin->image.'" width="18"></td>';

	echo '<td><b>'.$coin->createExplorerLink($coin->name).'</a></b></td>';
	echo '<td><b>'.$coin->symbol.'</b></td>';

	echo '<td>'.$coin->algo.'</td>';
	echo '<td>'.$coin->version.'</td>';

	echo '<td>'.$coin->block_height.'</td>';
	$diffnote = '';
	if ($coin->algo == 'equihash' || $coin->algo == 'quark') $diffnote = '*';
	echo '<td data="'.$coin->difficulty.'">'.$difficulty.$diffnote.'</td>';
	$cnx_class = (intval($coin->connections) > 3) ? '' : 'low';
	$peers_link = CHtml::link($coin->connections, "javascript:wallet_peers({$coin->id});", array('class'=>$cnx_class));
	echo '<td>'.$peers_link.'</td>';
	echo '<td data="'.$coin->network_hash.'">'.$nethash_sfx.'</td>';

	echo "<td>";

	if(!empty($coin->link_bitcointalk))
		echo CHtml::link('forum', $coin->link_bitcointalk, array('target'=>'_blank'));

	elseif(!empty($coin->link_site))
		echo CHtml::link('site', $coin->link_site, array('target'=>'_blank'));

	echo "</td>";
	echo "</tr>";
}

echo <<<end
</tbody>
</table>
<p style="font-size: .8em;">
	&nbsp;* Unified difficulty based on the hash target (might be different than wallet one)<br/>
</p>
</div></div>

<br><br><br><br><br><br><br><br><br><br>

end;

