
<a href='/site/common'>Summary</a>&nbsp;
<a href='/site/admin'>Coins</a>&nbsp;
<a href='/site/exchange'>Exchange</a>&nbsp;
<a href='/site/user?symbol=BTC'>Users</a>&nbsp;
<a href='/site/worker'>Workers</a>&nbsp;
<a href='/site/version'>Version</a>&nbsp;
<a href='/site/earning'>Earnings</a>&nbsp;
<a href='/site/payments'>Payments</a>&nbsp;
<br>

<?php

$id = getiparam('id');
$coin = getdbo('db_coins', $id);
if (!$coin) {
	$this->goback();
}

$remote = new Bitcoin($coin->rpcuser, $coin->rpcpasswd, $coin->rpchost, $coin->rpcport);
$info = $remote->getinfo();

echo "<br><a href='/site/update?id=$coin->id'><b>COIN PROPERTIES</b></a>";
echo " || <a href='/coin/update?id=$coin->id'><b>EXTRA</b></a>";

if($info)
	echo " || <a href='/explorer?id=$coin->id'><b>EXPLORER</b></a>";

if(!$info && $coin->enable)
	echo "<br><a href='/site/stopcoin?id=$coin->id'><b>STOP COIND</b></a>";

if($coin->auto_ready)
	echo "<br><a href='/site/unsetauto?id=$coin->id'><b>UNSET AUTO</b></a>";
else
	echo "<br><a href='/site/setauto?id=$coin->id'><b>SET AUTO</b></a>";

echo "<br>";

if(!empty($coin->link_bitcointalk))
	echo "<a href='$coin->link_bitcointalk' target=_blank>forum</a> ";

if(!empty($coin->link_github))
	echo "<a href='$coin->link_github' target=_blank>git</a> ";

echo "<a href='http://google.com/search?q=$coin->name%20$coin->symbol%20bitcointalk' target=_blank>google</a> ";

echo "<br><div id='main_results'></div>";

echo "<br><a href='/site/makeconfigfile?id=$coin->id'><b>MAKE CONFIG & START</b></a>";

if($info)
{
	echo "<br><a href='/site/restartcoin?id=$coin->id'><b>RESTART COIND</b></a>";
	echo "<br><a href='/site/stopcoin?id=$coin->id'><b>STOP COIND</b></a>";

//	if(isset($info['balance']) && $info['balance'] && !empty($coin->deposit_address))
//		echo "<br><a href='javascript:showSellAmountDialog()'><b>SEND BALANCE TO</b></a> - $coin->deposit_address";
}
else
{
	echo "<br><a href='/site/startcoin?id=$coin->id'><b>START COIND</b></a>";
	echo "<br><br><a href='/site/resetblockchain?id=$coin->id'><b>RESET BLOCKCHAIN</b></a>";

	if($coin->installed)
		echo "<br><a href='javascript:uninstall_coin();'><b>UNINSTALL COIN</b></a><br>";
}

echo "<br><a href='/site/clearearnings?id=$coin->id'><b>CLEAR EARNINGS</b></a>";
echo "<br><a href='/site/deleteearnings?id=$coin->id'><b>DELETE EARNINGS</b></a>";
echo "<br><a href='/site/payuserscoin?id=$coin->id'><b>DO PAYMENTS</b></a>";
//echo "<br><a href='/site/checkblocks?id=$coin->id'><b>CHECK FOR NEW BLOCKS</b></a>";

echo <<<END

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<script>

function uninstall_coin()
{
	if(!confirm("Uninstall this coin?"))
		return;

	window.location.href = '/site/uninstallcoin?id=$coin->id';
}

$(function()
{
	main_refresh();
});

var main_delay=30000;
var main_timeout;

function main_ready(data)
{
	$('#main_results').html(data);
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
	main_timeout = setTimeout(main_refresh, main_delay*2);
}

function main_refresh()
{
	var url = "/site/coin_results?id={$_GET['id']}";

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

function showSellAmountDialog(marketid)
{
	$("#sell-amount-dialog").dialog(
	{
    	autoOpen: true,
		width: 400,
		height: 240,
		modal: true,
		title: 'Sell $coin->symbol to market '+marketid,

		buttons:
		{
			"Sell": function()
			{
				amount = $('#input_sell_amount').val();
				window.location.href = '/market/sellto?id='+marketid+'&amount='+amount;
			},
		}
	});
}

</script>

<div id="sell-amount-dialog" style='display: none;'>
<br>
Address: xxxxxxxxxxxx<br><br>
Amount: <input type=text id='input_sell_amount' value='$coin->balance'>
<br>
</div>

END;



