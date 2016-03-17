<?php

if (!$coin) return;

$this->pageTitle = $coin->name." block explorer";

echo <<<ENDJS
<script type="text/javascript">
function toggleRaw(el) {
	$(el).parents('tr').next('tr.raw').toggle();
}
$(function() {
	$('#favicon').remove();
	$('head').append('<link href="{$coin->image}" id="favicon" rel="shortcut icon">');
	$('span.txid').bind('click', function(el) { toggleRaw(el.target); });
});
</script>
ENDJS;

function simplifyscript($script)
{
	$script = preg_replace("/[0-9a-f]+ OP_DROP ?/","", $script);
	$script = preg_replace("/OP_NOP ?/","", $script);
	return trim($script);
}

///////////////////////////////////////////////////////////////////////////////////////////////

$remote = new Bitcoin($coin->rpcuser, $coin->rpcpasswd, $coin->rpchost, $coin->rpcport);

$block = $remote->getblock($hash);
if(!$block) return;
//debuglog($block);

$d = date('Y-m-d H:i:s', $block['time']);
$confirms = isset($block['confirmations'])? $block['confirmations']: '';
$txcount = count($block['tx']);

$version = dechex($block['version']);
$nonce = dechex($block['nonce']);

echo "<table class='dataGrid1'>";
echo "<tr><td width=100></td><td></td></tr>";

echo "<tr><td>Coin:</td><td><b><a href='/explorer?id=$coin->id'>$coin->name</a></b></td></tr>";
echo "<tr><td>Blockhash:</td><td><span style='font-family: monospace;'>$hash</span></td></tr>";

echo "<tr><td>Confirmations:</td><td>$confirms</td></tr>";
echo "<tr><td>Size:</td><td>{$block['size']} bytes</td></tr>";
echo "<tr><td>Height:</td><td>{$block['height']}</td></tr>";
echo "<tr><td>Time:</td><td>$d</td></tr>";
echo "<tr><td>Difficulty:</td><td>{$block['difficulty']}</td></tr>";

echo "<tr><td>Version:</td><td><span style='font-family: monospace;'>$version</span></td></tr>";
echo "<tr><td>Merkle Root:</td><td><span style='font-family: monospace;'>{$block['merkleroot']}</span></td></tr>";

echo "<tr><td>Nonce:</td><td><span style='font-family: monospace;'>$nonce</span></td></tr>";
echo "<tr><td>Bits:</td><td><span style='font-family: monospace;'>{$block['bits']}</span></td></tr>";

if(isset($block['flags']))
	echo "<tr><td>Flags:</td><td><span style='font-family: monospace;'>{$block['flags']}</span></td></tr>";

if(isset($block['previousblockhash']))
	echo "<tr><td>Previous Hash:</td><td><span style='font-family: monospace;'>
		<a href='/explorer?id=$coin->id&hash={$block['previousblockhash']}'>{$block['previousblockhash']}</a></span></td></tr>";

if(isset($block['nextblockhash']))
	echo "<tr><td>Next Hash:</td><td><span style='font-family: monospace;'>
		<a href='/explorer?id=$coin->id&hash={$block['nextblockhash']}'>{$block['nextblockhash']}</a></span></td></tr>";

echo "<tr><td>Transactions:</td><td>$txcount</td></tr>";

echo "</table><br>";

////////////////////////////////////////////////////////////////////////////////

echo <<<end
<style type="text/css">
span.txid { font-family: monospace; cursor: pointer; }
tr.raw td { overflow-x: scroll; max-width: 1880px;}
pre.json { font-size: 10px; }
</style>

<table class="dataGrid2">
<thead>
<tr>
<th>Transaction Hash</th>
<th>Value</th>
<th>From</th>
<th>To (amount)</th>
</tr>
</thead>
end;

foreach($block['tx'] as $txhash)
{
	$tx = $remote->getrawtransaction($txhash, 1);
	if(!$tx) continue;

	$valuetx = 0;
	foreach($tx['vout'] as $vout)
		$valuetx += $vout['value'];

	echo "<tr class='ssrow'>";

	echo '<td><span class="txid">'.$tx['txid'].'</span></td>';
	echo "<td>$valuetx</td>";

	echo "<td>";
	foreach($tx['vin'] as $vin) {
		if(isset($vin['coinbase']))
			echo "Generation";
	}
	echo "</td>";

	echo "<td>";
	foreach($tx['vout'] as $vout)
	{
		$value = $vout['value'];
		if ($value == 0) continue;

		if(isset($vout['scriptPubKey']['addresses'][0]))
			echo '<span style="font-family: monospace;">'.$vout['scriptPubKey']['addresses'][0]."</span> ($value)";
		else
			echo "($value)";

		echo '<br>';
	}
	echo "</td>";

//	if (user()->getState('yaamp_admin')) {
		echo '</tr><tr class="raw" style="display:none;"><td colspan="4"><pre class="json">';
		unset($tx['hex']);
		echo json_encode($tx, 128);
		echo '</pre></td>';
//	}

	echo "</tr>";
}

if ($coin->symbol == 'DCR') {

	echo '<tr><th colspan="4">';
	echo '<b>Stake</b>';
	echo '</th></tr>';

	foreach($block['stx'] as $txhash)
	{
		$stx = $remote->getrawtransaction($txhash, 1);
		if(!$stx) continue;

		$valuetx = 0;
		foreach($stx['vout'] as $vout)
			$valuetx += $vout['value'];

		echo '<tr class="ssrow">';
		echo '<td><span class="txid">'.$stx['txid'].'</span></td>';
		echo "<td>$valuetx</td>";

		echo "<td>";
		foreach($stx['vin'] as $vin) {
			if(arraySafeVal($vin,'blockheight') > 0) {
				echo '<a href="/explorer?id='.$coin->id.'&height='.$vin['blockheight'].'">'.$vin['blockheight'].'</a>';
				echo '<br/>';
			}
		}
		echo "</td>";

		echo "<td>";
		foreach($stx['vout'] as $vout)
		{
			$value = $vout['value'];
			if ($value == 0) continue;

			if(isset($vout['scriptPubKey']['addresses'][0]))
				echo '<span style="font-family: monospace;">'.$vout['scriptPubKey']['addresses'][0]."</span> ($value)";
			else
				echo "($value)";

			echo '<br/>';
		}
		echo "</td>";

//		if (user()->getState('yaamp_admin')) {
			echo '</tr><tr class="raw" style="display:none;"><td colspan="4"><pre class="json">';
			unset($stx['hex']);
			echo json_encode($stx, 128);
			echo '</pre></td>';
//		}

		echo '</tr>';
	}
}

echo '</table>';

if (user()->getState('yaamp_admin')) {
	echo '<pre class="json">'.json_encode($block, 128).'</pre>';
}

echo <<<end
<form action="/explorer" method="get" style="padding: 10px;">
<input type="hidden" name="id" value="$coin->id">
<input type="text" name="height" class="main-text-input" placeholder="block height" style="width: 80px;">
<input type="text" name="txid" class="main-text-input" placeholder="tx hash" style="width: 450px;">
<input type="submit" value="Search" class="main-submit-button" >
</form>
end;

echo '<br><br><br><br><br><br><br><br><br><br>';
echo '<br><br><br><br><br><br><br><br><br><br>';
echo '<br><br><br><br><br><br><br><br><br><br>';


