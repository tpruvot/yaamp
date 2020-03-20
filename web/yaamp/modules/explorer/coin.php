<?php
if (!$coin) $this->goback();

JavascriptFile("/extensions/jqplot/jquery.jqplot.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.dateAxisRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.highlighter.js");

$this->pageTitle = $coin->name . " block explorer";

$start = (int)getiparam('start');

echo <<<END
<script type="text/javascript">
$(function() {
	$('#favicon').remove();
	$('head').append('<link href="{$coin->image}" id="favicon" rel="shortcut icon">');
});
</script>
<style type="text/css">
table.dataGrid2 { margin-top: 0; }
span.monospace { font-family: monospace; }
.main-text-input { }
.page .footer { width: auto; }
</style>
END;


// version is used for multi algo coins
// but each coin use different values...
$multiAlgos = $coin->multialgos || versionToAlgo($coin, 0) !== false;

echo '<br/>';
echo '<div class="main-left-box">';
echo '<div class="main-left-title">' . $coin->name . ' Explorer</div>';
echo '<div class="main-left-inner" style="padding-left: 8px; padding-right: 8px;">';

echo '<table class="dataGrid2">';

echo "<thead>";
echo "<tr>";
echo "<th>Age</th>";
echo "<th>Height</th>";
echo "<th>Difficulty</th>";
echo "<th>Type</th>";
if ($multiAlgos) echo "<th>Algo</th>";
echo "<th>Tx</th>";
echo "<th>Conf</th>";
echo "<th>Blockhash</th>";
echo "</tr>";
echo "</thead>";

$remote = new WalletRPC($coin);
if (!$start || $start > $coin->block_height) $start = $coin->block_height;
for ($i = $start;$i > max(1, $start - 21);$i--)
{
    $hash = $remote->getblockhash($i);
    if (!$hash) continue;

    $block = $remote->getblock($hash);
    if (!$block) continue;

    $d = datetoa2($block['time']);
    $confirms = isset($block['confirmations']) ? $block['confirmations'] : '';
    $tx = count($block['tx']);
    $diff = $block['difficulty'];
    $algo = versionToAlgo($coin, $block['version']);
    $type = '';
    if (arraySafeval($block, 'nonce', 0) > 0) $type = 'PoW';
    else if (isset($block['auxpow'])) $type = 'Aux';
    else if (isset($block['mint']) || strstr(arraySafeVal($block, 'flags', '') , 'proof-of-stake')) $type = 'PoS';

    // nonce 256bits
    if ($type == '' && $coin->symbol == 'ZEC') $type = 'PoW';

    //	debuglog($block);
    echo '<tr class="ssrow">';
    echo '<td>' . $d . '</td>';

    echo '<td>' . $coin->createExplorerLink($i, array(
        'height' => $i
    )) . '</td>';

    echo '<td>' . $diff . '</td>';
    echo '<td>' . $type . '</td>';
    if ($multiAlgos) echo "<td>$algo</td>";
    echo '<td>' . $tx . '</td>';
    echo '<td>' . $confirms . '</td>';

    echo '<td style="overflow-x: hidden; max-width:800px;"><span class="monospace">';
    echo $coin->createExplorerLink($hash, array(
        'hash' => $hash
    ));
    echo '</span></td>';

    echo "</tr>";
}

echo "</table>";

$pager = '';
if ($start <= $coin->block_height - 20) $pager = $coin->createExplorerLink('<< Prev', array(
    'start' => min($coin->block_height, $start + 20)
));
if ($start != $coin->block_height) $pager .= '&nbsp; ' . $coin->createExplorerLink('Now');
if ($start > 20) $pager .= '&nbsp; ' . $coin->createExplorerLink('Next >>', array(
    'start' => max(1, $start - 20)
));

$actionUrl = $coin->visible ? '/explorer/' . $coin->symbol : '/explorer/search?id=' . $coin->id;

echo <<<end
<div id="pager" style="float: right; width: 200px; text-align: right; margin-right: 16px; margin-top: 8px;">$pager</div>
<div id="form" style="width: 660px; height: 50px; overflow: hidden;">
<form action="{$actionUrl}" method="POST" style="padding-top: 4px; width: 650px;">
<input type="text" name="height" class="main-text-input" placeholder="Height" style="width: 80px;">
<input type="text" name="txid" class="main-text-input" placeholder="Transaction hash" style="width: 450px; margin: 4px;">
<input type="submit" value="Search" class="main-submit-button" >
</form>
</div>
end;


if ($start != $coin->block_height) return;

echo <<<end
<div id="diff_graph" style="margin-right: 8px; margin-top: -16px;">
<br><br><br><br><br><br><br><br><br><br><br><br><br><br>
</div>

<style type="text/css">
.jqplot-title {
	margin-bottom: 3px;
}
.jqplot-xaxis-tick {
	margin-top: 4px;
	font-size: 10px;
}
.jqplot-yaxis-tick {
	font-size: 7pt;
	margin-top: -4px;
	margin-right: 8px;
}
</style>

<script type="text/javascript" event="">

var last_graph_update = 0;

function graph_refresh()
{
	var now = Date.now()/1000;

	if (now < last_graph_update + 900) return;
	last_graph_update = now;

	var url = "/explorer/graph?id={$coin->id}";
	$.get(url, '', diff_graph_data);
}

function diff_graph_data_trace(data)
{
	 $('#diff_graph').html(data);
}

function diff_graph_data(data)
{
	var t = $.parseJSON(data);
	var plot1 = $.jqplot('diff_graph', t,
	{
		title: '<b>Network diff</b>',
		axes: {
			xaxis: {
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: { formatString: '%H:%M' }
			},
			yaxis: {
				min: 0.0,
				tickOptions: { labelPosition: 'top', formatString: '%.3f' }
			}
		},

		seriesDefaults:
		{
			markerOptions: { style: 'none' }
		},

		series:[
			{
				highlighter: { yvalues: 2, formatString: '<font size="1">%s %.3f<br/>Block %u</font>' }
			},
			{
				showLine: false,
				markerOptions: { style: 'circle', size: 6, color: 'silver' },
				animation: { show: true },
				highlighter: { yvalues: 3, formatString: '<font size="1">%s <span style="display:none;">%.1f</span>%g<br/>User block %u</font>' }
			}
		],

		grid:
		{
			borderWidth: 1,
			shadowWidth: 0,
			shadowDepth: 0,
			background: '#ffffff'
		},

		highlighter:
		{
			show: true
		},

	});
}
</script>
end;


app()
    ->clientScript
    ->registerScript('graph', "
	graph_refresh();
", CClientScript::POS_READY);
