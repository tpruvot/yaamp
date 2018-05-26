<?php

$id = getiparam('id');
$coin = getdbo('db_coins', $id);
if (!$coin) {
	$this->goback();
}

$this->pageTitle = 'Wallet - '.$coin->symbol;

// force a refresh after 10mn to prevent memory leaks in chrome
app()->clientScript->registerMetaTag('600', null, 'refresh');

if (!empty($coin->algo) && $coin->algo != 'PoS')
	user()->setState('yaamp-algo', $coin->algo);

$remote = new WalletRPC($coin);
$info = $remote->getinfo();

$sellamount = $coin->balance;
//if ($info) $sellamount = floatval($sellamount) - arraySafeVal($info, "paytxfee") * 3;

echo getAdminSideBarLinks().'<br/><br/>';
echo getAdminWalletLinks($coin, $info, 'wallet');

$maxrows = arraySafeVal($_REQUEST,'rows', 500);
$since = arraySafeVal($_REQUEST,'since', time() - (7*24*3600)); // one week

echo '<div id="main_actions">';

app()->clientScript->registerCoreScript('jquery.ui'); // dialog

/* 
echo "<br><a href='/site/makeconfigfile?id=$coin->id'><b>MAKE CONFIG & START</b></a>";

if($info)
{
	echo "<br><a href='/site/restartcoin?id=$coin->id'><b>RESTART COIND</b></a>";
	echo "<br><a href='/site/stopcoin?id=$coin->id'><b>STOP COIND</b></a>";

	if(isset($info['balance']) && $info['balance'] && !empty($coin->deposit_address))
		echo "<br><a href='javascript:showSellAmountDialog()'><b>SEND BALANCE TO</b></a> - $coin->deposit_address";
}
else
{
	echo "<br><a href='/site/startcoin?id=$coin->id'><b>START COIND</b></a>";
	echo "<br><br><a href='/site/resetblockchain?id=$coin->id'><b>RESET BLOCKCHAIN</b></a>";

	if($coin->installed)
		echo "<br><a href='javascript:uninstall_coin();'><b>UNINSTALL COIN</b></a><br>";
}

*/
echo <<<END

<br/><a class="red" href="/site/deleteearnings?id={$coin->id}"><b>DELETE EARNINGS</b></a>
<br/><a href="/site/clearearnings?id={$coin->id}"><b>CLEAR EARNINGS</b></a>
<br/><a href="/site/checkblocks?id={$coin->id}"><b>UPDATE BLOCKS</b></a>
<br/><a href="/site/payuserscoin?id={$coin->id}"><b>DO PAYMENTS</b></a>
<br/>
</div>

<style type="text/css">
table.dataGrid a.red, table.dataGrid a.red:visited, a.red { color: darkred; }
div#main_actions {
	position: absolute; top: 60px; right: 16px; width: 280px; text-align: right;
}
div#markets {
	overflow-x: hidden; overflow-y: scroll; max-height: 156px;
}
div#transactions {
	overflow-x: hidden; overflow-y: scroll; min-height: 200px; max-height: 360px;
	margin-bottom: 8px;
}
div#sums {
	overflow-x: hidden; overflow-y: scroll; min-height: 250px; max-height: 600px;
	width: 380px; float: left; margin-top: 16px; margin-bottom: 8px; margin-right: 16px;
}
.page .footer { clear: both; width: auto; margin-top: 16px; }
tr.ssrow.bestmarket { background-color: #dfd; }
tr.ssrow.disabled { background-color: #fdd; color: darkred; }
tr.ssrow.orphan { color: darkred; }
</style>

<div id="main_results"></div>

<script type="text/javascript">

function uninstall_coin()
{
	if(!confirm("Uninstall this coin?"))
		return;

	window.location.href = '/site/uninstallcoin?id=$coin->id';
}

var main_delay=30000;
var main_timeout;

function main_refresh()
{
	var url = "/site/coin_results?id={$id}&rows={$maxrows}&since={$since}";

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

function main_ready(data)
{
	$('#main_results').html(data);
	$(window).trigger('resize'); // will draw graph
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
	main_timeout = setTimeout(main_refresh, main_delay*2);
}

function showSellAmountDialog(marketname, address, marketid, bookmarkid)
{
	$("#dlgaddr").html(address);
	$("#sell-amount-dialog").dialog(
	{
    	autoOpen: true,
		width: 400,
		height: 240,
		modal: true,
		title: 'Send $coin->symbol to '+marketname,

		buttons:
		{
			"Send / Sell": function()
			{
				amount = $('#input_sell_amount').val();
				if (marketid > 0)
					window.location.href = '/market/sellto?id='+marketid+'&amount='+amount;
				else
					window.location.href = '/site/bookmarkSend?id='+bookmarkid+'&amount='+amount;
			},
		}
	});
	return false;
}

</script>

<div id="sell-amount-dialog" style="display: none;">
<br>
Address: <span id="dlgaddr">xxxxxxxxxxxx</span><br><br>
Amount: <input type=text id="input_sell_amount" value="$sellamount">
<br>
</div>

END;

JavascriptReady("main_refresh();");

if ($coin->watch) {
	$this->renderPartial('coin_market_graph', array('coin'=>$coin));
	JavascriptReady("$(window).resize(graph_resized);");
}

//////////////////////////////////////////////////////////////////////////////////////
