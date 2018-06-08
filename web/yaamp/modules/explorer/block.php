<?php

if (!$coin) return;

$this->pageTitle = $coin->name." block explorer";

$txid = getparam('txid');
$q = getparam('q');
if (!empty($q) && ctype_xdigit($q)) $txid = $q;
elseif (empty($txid)) $txid = 'txid not set'; // prevent highlight

echo <<<END
<script type="text/javascript">
function toggleRaw(el) {
	$(el).parents('tr').next('tr.raw').toggle();
}
$(function() {
	$('#favicon').remove();
	$('head').append('<link href="{$coin->image}" id="favicon" rel="shortcut icon">');
	$('span.txid').bind('click', function(el) { toggleRaw(el.target); });
	$('span.txid:contains("{$txid}")').css('color','darkred');
});
</script>

<style type="text/css">
span.monospace { font-family: monospace; }
span.txid { cursor: pointer; }
tr.raw td { max-width: 1880px; }
td.ntx { width: 8px; font-family: monospace; }
th {
	text-align: left;
	box-shadow: 2px 2px 2px #c0c0c0, -1px -1px 2px white;
}
div.json {
	font-size: 11px;
	white-space: pre; font-family: monospace; unicode-bidi: embed; padding: 4px;
	overflow-x: hidden;
	background-color: #f0f0f0;
	border: 1px solid silver;
	box-shadow: 2px 2px 10px silver inset;
	-moz-box-shadow: 2px 2px 10px silver inset;
	-webkit-box-shadow: 2px 2px 10px silver inset;
}
div.json s { text-decoration: none; color: #003f7f; }
div.json s.key { color: #000000; }
div.json s.addr { color: #3f003f; }
div.json s a { text-decoration: none; }
div.json s a:hover { text-decoration: underline; }
div.json u { text-decoration: none; color: #003f7f; }
div.json i { font-style: normal; color: #7f0000; }
div.json b { font-style: normal; color: #7f0000; }
.main-text-input { margin-top: 4px; margin-bottom: 4px; }
.page .footer { width: auto; }
</style>
END;

//////////////////////////////////////////////////////////////////////////////////////

function colorizeJson($json)
{
	$json = str_replace('"', '&quot;', $json);
	// strings
	$res = preg_match_all("# &quot;([^&]+)&quot;([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$sfx = $matches[2][$n];
		$class = '';
		if (strlen($m) == 64 && ctype_xdigit($m)) $class = 'hash';
		if (strlen($m) == 34 && ctype_alnum($m)) $class = 'addr';
		if (strlen($m) == 35 && ctype_alnum($m)) $class = 'addr';
		if (strlen($m) > 160 && ctype_alnum($m)) $class = 'data';
		if ($class == '' && strlen($m) < 64 && ctype_xdigit($m)) $class = 'hexa';
		$json = str_replace(' &quot;'.$m."&quot;".$sfx, ' "<s class="'.$class.'">'.$m.'</s>"'.$sfx, $json);
	}
	// keys
	$res = preg_match_all("#&quot;([^&]+)&quot;:#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$json = str_replace('&quot;'.$m."&quot;", '"<s class="key">'.$m.'</s>"', $json);
	}
	// humanize timestamps like "blocktime": 1462359961,
	$res = preg_match_all("#: ([0-9]{10})([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$ts = intval($m);
		if ($ts > 1400000000 && $ts < 1600000000) {
			$sfx = $matches[2][$n];
			$date = strftime("<u>%Y-%m-%d %T %z</u>", $ts);
			$json = str_replace(' '.$m.$sfx, ' "'.$date.'"'.$sfx, $json);
		}
	}
	// numeric
	$res = preg_match_all("#: ([e\-\.0-9]+)([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$sfx = $matches[2][$n];
		$json = str_replace(' '.$m.$sfx, ' <i>'.$m.'</i>'.$sfx, $json);
	}
	$json = preg_replace('#\[\s+\]#', '[]', $json);
	$json = str_replace('[', '<b>[</b>', $json);
	$json = str_replace(']', '<b>]</b>', $json);
	$json = str_replace('{', '<b>{</b>', $json);
	$json = str_replace('}', '<b>}</b>', $json);
	return $json;
}

function simplifyscript($script)
{
	$script = preg_replace("/[0-9a-f]+ OP_DROP ?/","", $script);
	$script = preg_replace("/OP_NOP ?/","", $script);
	return trim($script);
}

///////////////////////////////////////////////////////////////////////////////////////////////

$remote = new WalletRPC($coin);

$block = $remote->getblock($hash);
if(!$block) return;
//debuglog($block);

$d = date('Y-m-d H:i:s', $block['time']);
$confirms = isset($block['confirmations'])? $block['confirmations']: '';
$txcount = count($block['tx']);

$version = dechex($block['version']);
$nonce = $block['nonce'];

echo '<table class="dataGrid1">';
echo '<tr><td width=100></td><td></td></tr>';

echo '<tr><td>Coin:</td><td><b>'.$coin->createExplorerLink($coin->name).'</b></td></tr>';
echo '<tr><td>Blockhash:</td><td><span class="txid monospace">'.$hash.'</span></td></tr>';

echo '</tr><tr class="raw" style="display:none;"><td colspan="2"><div class="json">';
echo colorizeJson(json_encode($block, 128));
echo '</div></td>';

echo '<tr><td>Confirmations:</td><td>'.$confirms.'</td></tr>';
echo '<tr><td>Height:</td><td>'.$block['height'].'</td></tr>';
echo '<tr><td>Time:</td><td>'.$d.' ('.$block['time'].')'.'</td></tr>';
echo '<tr><td>Difficulty:</td><td>'.$block['difficulty'].'</td></tr>';
echo '<tr><td>Bits:</td><td><span class="monospace">'.$block['bits'].'</span></td></tr>';
echo '<tr><td>Nonce:</td><td><span class="monospace">'.$nonce.'</span></td></tr>';
echo '<tr><td>Version:</td><td><span class="monospace">'.$version.'</span></td></tr>';
echo '<tr><td>Size:</td><td>'.$block['size'].' bytes</td></tr>';

if(isset($block['flags']))
	echo '<tr><td>Flags:</td><td><span class="monospace">'.$block['flags'].'</span></td></tr>';

if(isset($block['previousblockhash']) && $coin->algo == 'x16r') {
	echo '<tr><td>Hash order:</td><td><span class="monospace">'.
		substr($block['previousblockhash'], -16).
	'</span></td></tr>';
}

if(isset($block['previousblockhash']))
	echo '<tr><td>Previous Hash:</td><td><span class="monospace">'.
		$coin->createExplorerLink($block['previousblockhash'], array('hash'=>$block['previousblockhash'])).
	'</span></td></tr>';

if(isset($block['nextblockhash']))
	echo '<tr><td>Next Hash:</td><td><span class="monospace">'.
		$coin->createExplorerLink($block['nextblockhash'], array('hash'=>$block['nextblockhash'])).
	'</span></td></tr>';

echo '<tr><td>Merkle Root:</td><td><span class="monospace">'.$block['merkleroot'].'</span></td></tr>';

echo '<tr><td>Transactions:</td><td>'.$txcount.'</td></tr>';

echo "</table><br>";

////////////////////////////////////////////////////////////////////////////////

echo <<<end

<table class="dataGrid">
<thead>
<tr>
<th width="8px" class="ntx">#</th>
<th>Transaction Hash</th>
<th>Size</th>
<th>Value</th>
<th>From</th>
<th>To (amount)</th>
</tr>
</thead>
end;

$ntx = 0;
foreach($block['tx'] as $txhash)
{
	$ntx++;
	$tx = $remote->getrawtransaction($txhash, 1);
	if(!$tx && ($ntx == 1 || $txid == $txhash)) {
		// some transactions are not found directly with getrawtransaction
		$tx = $remote->gettransaction($txhash);
		if ($tx && isset($tx['hex'])) {
			$hex = $tx['hex'];
			$tx = $remote->decoderawtransaction($hex);
			$tx['hex'] = $hex;
		} else {
			continue;
		}
	}
	if(!$tx) continue;

	$valuetx = 0;
	foreach($tx['vout'] as $vout)
		$valuetx += $vout['value'];

	echo '<tr class="ssrow">';
	echo '<td class="ntx">'.$ntx.'</td>';
	echo '<td><span class="txid monospace">'.$tx['txid'].'</span></td>';
	$size = (strlen($tx['hex'])/2);
	echo "<td>$size</td>";
	echo "<td>$valuetx</td>";

	echo "<td>";
	$segwit = false;
	foreach($tx['vin'] as $vin) {
		if(isset($vin['coinbase']))
			echo "Generation";
		if(isset($vin['txinwitness']))
			$segwit = true;
	}
	if($segwit)
		echo '&nbsp;<img src="/images/ui/segwit.png" height="8px" valign="center" title="segwit"/>';
	echo "</td>";

	echo "<td>";
	$nvout = count($tx['vout']);;
	if ($nvout > 500) echo "Too much addresses to display ($nvout)";
	else
	foreach($tx['vout'] as $vout)
	{
		$value = $vout['value'];
		if ($value == 0) continue;

		if(isset($vout['scriptPubKey']['addresses'][0]))
			echo '<span class="monospace">'.$vout['scriptPubKey']['addresses'][0]."</span> ($value)";
		else
			echo "($value)";

		echo '<br>';
	}
	echo "</td>";

	echo '</tr><tr class="raw" style="display:none;"><td colspan="6"><div class="json">';
	unset($tx['hex']);
	echo ($nvout > 500) ? 'truncated' : colorizeJson(json_encode($tx, 128));
	echo '</div></td>';

	echo "</tr>";

	if ($ntx > 100) {
		echo '<tr class="ssrow"><td colspan="6">Too much transations to display...</td></tr>';
		break;
	}
}

if ($coin->rpcencoding == 'DCR' && isset($block['stx'])) {

	echo '<tr><th class="section" colspan="6">';
	echo 'Stake';
	echo '</th></tr>';

	$ntx = 0;
	foreach($block['stx'] as $txhash)
	{
		$ntx++;
		$stx = $remote->getrawtransaction($txhash, 1);
		if(!$stx) continue;

		$valuetx = 0;
		foreach($stx['vout'] as $vout)
			$valuetx += $vout['value'];

		echo '<tr class="ssrow">';
		echo '<td class="ntx">'.$ntx.'</td>';
		echo '<td><span class="txid monospace">'.$stx['txid'].'</span></td>';
		$size = (strlen($stx['hex'])/2);
		echo "<td>$size</td>";
		echo "<td>$valuetx</td>";

		echo "<td>";
		if(isset($stx['vout'][0]['scriptPubKey']) && arraySafeVal($stx['vout'][0]['scriptPubKey'],'type') == 'stakesubmission')
			echo "Ticket";
		else foreach($stx['vin'] as $vin) {
			if (arraySafeVal($vin,'blockheight') > 0) {
				echo $coin->createExplorerLink($vin['blockheight'], array('height'=>$vin['blockheight']));
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
				echo '<span class="monospace">'.$vout['scriptPubKey']['addresses'][0]."</span> ($value)";
			else
				echo "($value)";

			echo '<br/>';
		}
		echo "</td>";

		echo '</tr><tr class="raw" style="display:none;"><td colspan="6"><div class="json">';
		unset($stx['hex']);
		echo colorizeJson(json_encode($stx, 128));
		echo '</div></td>';

		echo '</tr>';
	}
}

echo '</table>';

$actionUrl = $coin->visible ? '/explorer/'.$coin->symbol : '/explorer/search?id='.$coin->id;

echo <<<end
<form action="{$actionUrl}" method="POST" style="padding: 8px; padding-left: 0px;">
<input type="text" name="height" class="main-text-input" placeholder="block height" style="width: 80px;">
<input type="text" name="txid" class="main-text-input" placeholder="tx hash" style="width: 450px; margin: 4px;">
<input type="submit" value="Search" class="main-submit-button">
</form>
end;

echo '<br><br><br><br><br><br><br><br><br><br>';
echo '<br><br><br><br><br><br><br><br><br><br>';
echo '<br><br><br><br><br><br><br><br><br><br>';


