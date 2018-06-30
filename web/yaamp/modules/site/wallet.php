<?php

JavascriptFile("/extensions/jqplot/jquery.jqplot.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.dateAxisRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.barRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.highlighter.js");
JavascriptFile('/yaamp/ui/js/auto_refresh.js');

$recents = array();
$raw_recents = isset($_COOKIE['wallets'])? explode("|", $_COOKIE['wallets']): array();
// make it unique
foreach($raw_recents as $addr) {
	$recents[$addr] = $addr;
}

$address = getparam('address');
if (!empty($address) && preg_match('/[^A-Za-z0-9]/', $address)) {
	// Just to make happy XSS seekers who can hack their own browser html...
	die;
}

$drop_address = getparam('drop');
if (!empty($drop_address)) {
	// to clean cookies
	foreach($recents as $k=>$addr) {
		if ($addr == $drop_address) {
			unset($recents[$k]);
			if (controller()->admin)
				setcookie('wallets', implode("|", $recents), time()+60*60*24*30, '/');
			break;
		}
	}
}

$user = getuserparam($address);
if($user)
{
	user()->setState('yaamp-wallet', $user->username);
	$recents[$user->username] = $user->username;

	$coin = getdbo('db_coins', $user->coinid);
	if($coin) echo <<<END
	<script type="text/javascript">
	$(function() {
		$('#favicon').remove();
		$('head').append('<link href="{$coin->image}" id="favicon" rel="shortcut icon">');
	});
	</script>
END;

	if(empty($user->hostaddr) && !$this->admin) {
		$user->hostaddr = $_SERVER['REMOTE_ADDR'];
		$user->save();
	}
}

$username = $user? $user->username: '';

if(!controller()->admin)
	setcookie('wallets', implode("|", $recents), time()+60*60*24*30, '/');

echo <<<END
<div id='resume_update_button' style='color: #444; background-color: #ffd; border: 1px solid #eea;
	padding: 10px; margin-left: 20px; margin-right: 20px; margin-top: 15px; cursor: pointer; display: none;'
	onclick='auto_page_resume();' align=center>
	<b>Auto refresh is paused - Click to resume</b></div>

<table cellspacing=20 width=100%>
<tr><td valign=top width=50%>
END;

if($user) echo <<<END
<div id='main_wallet_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;

if($user) echo <<<END
<div class="main-left-box">
<div class="main-left-title">Last 24 Hours Balance: $user->username</div>
<div class="main-left-inner"><br>
<div id='graph_earnings_results' style='height: 240px;'></div>
<div style='float: right;'>
<span style='font-size: .8em; color: #4bb2c5;'>Balance</span>
<span style='font-size: .8em; color: #eaa228;'>Pending</span>
</div>
<br>
</div></div><br>
END;

if($user) echo <<<END
<div id='main_graphs_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;

if($user) echo <<<END
<div id='main_miners_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;

echo <<<END
<div class="main-left-box">
<div class="main-left-title">Search Wallet:</div>
<div class="main-left-inner">
<form action="/" method="get" style="padding: 10px;">
<input type="text" name="address" class="main-text-input" placeholder="Wallet Address">
<input type="submit" value="Submit" class="main-submit-button" ><br><br>
END;

echo "<table class='dataGrid2'>";
foreach($recents as $addr)
{
	if(empty($addr)) continue;

	$user = getuserparam($addr);
	if(!$user) continue;

	$coin = getdbo('db_coins', $user->coinid);

	if($user->username == $username)
		echo "<tr style='background-color: #e0d3e8;'><td width=24>";
	else
		echo "<tr class='ssrow'><td width=24>";

	if($coin)
		echo '<img width="16px" src="'.$coin->image.'">';

	echo '</td><td><a class="address" href="/?address='.$addr.'" style="font-family: monospace; font-size: 1.1em;">'.
		$addr.'</a></td>';

	$balance = bitcoinvaluetoa($user->balance);

	if($coin)
		$balance = $balance>0? "$balance $coin->symbol": '';
	else
		$balance = $balance>0? "$balance BTC": '';

	echo '<td align="right">'.$balance.'</td>';
	
	$delicon = $address == $addr ? '' : '<img src="/images/base/delete.png" onclick="javascript:drop_cookie(this);" style="cursor:pointer;"/>';
	echo '<td style="width: 16px; max-width: 16px;">'.$delicon.'</td>';
	
	echo '</tr>';
}

echo "</table></form></div></div><br>";

echo "</td><td valign=top>";

echo <<<END
<div id='pool_current_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;

if($user) echo <<<END
<div id='found_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;

echo <<<END

</td></tr></table>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<script>

function page_refresh()
{
	pool_current_refresh();
	found_refresh();

	if('$username' != '')
	{
		main_wallet_refresh();
		main_miners_refresh();

		main_graphs_refresh();
		main_title_refresh();
	}
}

function select_algo(algo)
{
	window.location.href = '/site/algo?algo='+algo+'&r=/site/mining';
}

////////////////////////////////////////////////////

function main_wallet_ready(data)
{
	$('#main_wallet_results').html(data);
}

function main_wallet_refresh()
{
	var url = "/site/wallet_results?address=$username";
	$.get(url, '', main_wallet_ready);
}

function main_wallet_refresh_details()
{
	var url = "/site/wallet_results?address=$username&showdetails=1";
	$.get(url, '', main_wallet_ready);
}

////////////////////////////////////////////////////

function main_miners_ready(data)
{
	$('#main_miners_results').html(data);
}

function main_miners_refresh()
{
	var url = "/site/wallet_miners_results?address=$username";
	$.get(url, '', main_miners_ready);
}

////////////////////////////////////////////////////

function pool_current_ready(data)
{
	$('#pool_current_results').html(data);
}

function pool_current_refresh()
{
	var url = "/site/current_results";
	$.get(url, '', pool_current_ready);
}

////////////////////////////////////////////////////

function main_title_ready(data)
{
	document.title = data;
}

function main_title_refresh()
{
	var url = "/site/title_results?address=$username";
	$.get(url, '', main_title_ready);
}

////////////////////////////////////////////////////

function found_ready(data)
{
	$('#found_results').html(data);
}

function found_refresh()
{
	var url = "/site/user_earning_results?address=$username";
	$.get(url, '', found_ready);
}

////////////////////////////////////////////////////

var last_graph_update = 0;

function main_graphs_ready(data)
{
	$('#main_graphs_results').html(data);
	$('.graph_algo').each(function()
	{
		var algo = $(this).attr('id');
		main_refresh_hashrate(algo);
	});
}

function main_graphs_refresh()
{
	var now = Date.now()/1000;

	if(now < last_graph_update + 900) return;
	last_graph_update = now;

	var url = "/site/wallet_graphs_results?address=$username";
	$.get(url, '', main_graphs_ready);

	graph_earnings_refresh();
}

///////////////////////////////////////////////////////////////////////

function main_refresh_hashrate(algo)
{
	var url = "/site/graph_user_results?address=$username&algo="+algo;
	$.get(url, '', function(data)
	{
		graph_init_hashrate(data, algo);
	});
}

///////////////////////////////////////////////////////////////////////

function graph_init_hashrate(data, algo)
{
	$('#graph_results_'+algo).empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_'+algo, t,
	{
		title: '<b>'+algo+' Hashrate (Mh/s)</b>',
		axes: {
			xaxis: {
				tickInterval: 7200,
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: {formatString: '<font size=1>%#Hh</font>'}
			},
			yaxis: {
				min: 0,
				tickOptions: {formatString: '<font size=1>%#.3f &nbsp;</font>'}
			}
		},

		seriesDefaults:
		{
			markerOptions: { style: 'none' }
		},

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

///////////////////////////////////////////////////////////////////////

function graph_earnings_ready(data)
{
	graph_earnings_init(data);
}

function graph_earnings_refresh()
{
	var url = "/site/graph_earnings_results?address=$username";
	$.get(url, '', graph_earnings_ready);
}

function graph_earnings_init(data)
{
	$('#graph_earnings_results').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_earnings_results', t,
	{
	//	title: '<b></b>',
		stackSeries: true,
		axes: {
			xaxis: {
				tickInterval: 7200,
				renderer: $.jqplot.DateAxisRenderer,
				tickOptions: {formatString: '<font size=1>%#Hh</font>'}
			},
			yaxis: {
				min: 0,
				tickOptions: {formatString: '<font size=1>%#.8f &nbsp;</font>'}
			}
		},

		seriesDefaults:
		{
			markerOptions: { style: 'none' }
		},

		grid:
		{
			borderWidth: 1,
			shadowWidth: 0,
			shadowDepth: 0,
			background: '#ffffff'
		},

	});
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////

function main_wallet_tx()
{
	var w = window.open("/site/tx?address=$username", "yaamp_tx",
		"width=800,height=600,location=no,menubar=no,resizable=yes,status=yes,toolbar=no");
}

function drop_cookie(el)
{
	var addr = $(el).closest('tr').find('td a.address').text();
	window.location.href = '?address={$address}&drop=' + addr;
}

</script>


END;

