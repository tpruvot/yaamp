<?php
if (!$coin) $this->goback();

$this->pageTitle = $coin->name . " block explorer";

$remote = new WalletRPC($coin);

echo <<<END
<script type="text/javascript">
$(function() {
	$('#favicon').remove();
	$('head').append('<link href="{$coin->image}" id="favicon" rel="shortcut icon">');
});
</script>

<style type="text/css">
span.monospace { font-family: monospace; }
.main-text-input { margin-top: 4px; margin-bottom: 4px; }
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
<tbody>
END;


$tx = $remote->getrawtransaction($txhash, 1);
if (!$tx) continue;

$valuetx = 0;
foreach ($tx['vout'] as $vout) $valuetx += $vout['value'];

$coinUrl = $this->createUrl('/explorer', array(
    'id' => $coin->id
));

echo '<tr class="ssrow">';

echo '<td><span class="monospace">' . CHtml::link($tx['txid'], $coinUrl . 'txid=' . $tx['txid']) . '</a></span></td>';
echo '<td>' . $valuetx . '</td>';

echo "<td>";
foreach ($tx['vin'] as $vin)
{
    if (isset($vin['coinbase'])) echo "Generation";

}
echo "</td>";

echo "<td>";
foreach ($tx['vout'] as $vout)
{
    $value = $vout['value'];

    if (isset($vout['scriptPubKey']['addresses'][0])) echo '<span class="monospace">' . $vout['scriptPubKey']['addresses'][0] . "</span> ($value)";
    else echo "($value)";

    echo '<br>';
}
echo "</td>";

echo "</tr></tbody>";
echo "</table>";

$actionUrl = $coin->visible ? '/explorer/' . $coin->symbol : '/explorer/search?id=' . $coin->id;

echo <<<end
<form action="{$actionUrl}" method="POST" style="padding: 10px;">
<input type="text" name="height" class="main-text-input" placeholder="block height" style="width: 80px;">
<input type="text" name="txid" class="main-text-input" placeholder="tx hash" style="width: 450px; margin: 4px;">
<input type="submit" value="Search" class="main-submit-button" >
</form>
end;


echo '<br><br><br><br><br><br><br><br><br><br>';
echo '<br><br><br><br><br><br><br><br><br><br>';
echo '<br><br><br><br><br><br><br><br><br><br>';
