<?php
$algo = user()->getState('yaamp-algo');

JavascriptFile("/extensions/jqplot/jquery.jqplot.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.dateAxisRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.barRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.highlighter.js");

$height = '240px';

$wallet = user()->getState('yaamp-wallet');
if (!empty($wallet) && preg_match('/[^A-Za-z0-9]/', $wallet))
{
    die;
}
$user = getuserparam($wallet);

$algo_unit = 'Mh';
$algo_factor = yaamp_algo_mBTC_factor($algo);
if ($algo_factor == 1000) $algo_unit = 'Gh';

echo <<<end

<table cellspacing=20 width=100%>
<tr><td valign=top width=50%>

<div id='mining_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>

<div id='main_miners_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>

<div class="main-left-box">
<div class="main-left-title">Last 24 Hours Hashrate ($algo)</div>
<div class="main-left-inner"><br>
<div id='graph_results_hashrate' style='height: $height;'></div><br>
</div></div><br>

<div class="main-left-box">
<div class="main-left-title">Last 24 Hours Estimate ($algo)</div>
<div class="main-left-inner"><br>
<div id='graph_results_price' style='height: $height;'></div><br>
</div></div><br>

</td><td valign=top>

<div id='pool_current_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>

<div id='main_wallet_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>

</td></tr></table>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<script>

var delay = 60000;

$(function()
{
	pool_current_refresh();
	mining_refresh();
//	found_refresh();
	user_hashrate_refresh();
	main_refresh_price();
	main_wallet_refresh();
	main_miners_refresh();
});

function select_algo(algo)
{
	window.location.href = '/site/algo?algo='+algo;
}

////////////////////////////////////////////////////

function pool_current_ready(data)
{
	$('#pool_current_results').html(data);
	setTimeout(pool_current_refresh, delay);
}

function pool_current_error()
{
	setTimeout(pool_current_refresh, delay*2);
}

function pool_current_refresh()
{
	var url = "/site/current_results";
	$.get(url, '', pool_current_ready).error(pool_current_error);
}

////////////////////////////////////////////////////

function mining_ready(data)
{
	$('#mining_results').html(data);
	setTimeout(mining_refresh, delay);
}

function mining_error()
{
	setTimeout(mining_refresh, delay*2);
}

function mining_refresh()
{
	var url = "/trading/mining_results";
	$.get(url, '', mining_ready).error(mining_error);
}

////////////////////////////////////////////////////

function main_miners_ready(data)
{
	$('#main_miners_results').html(data);
}

function main_miners_refresh()
{
	var url = "/site/wallet_miners_results?address=$wallet";
	$.get(url, '', main_miners_ready);
}

////////////////////////////////////////////////////

function main_wallet_ready(data)
{
	$('#main_wallet_results').html(data);
	setTimeout(main_wallet_refresh, delay);
}

function main_wallet_error()
{
	setTimeout(main_wallet_refresh, delay*2);
}

function main_wallet_refresh()
{
	var url = "/site/wallet_results?address=$wallet";
	$.get(url, '', main_wallet_ready).error(main_wallet_error);
}

///////////////////////////////////////////////////////////////////////

function main_ready_price(data)
{
	graph_init_price(data);
	setTimeout(main_refresh_price, delay);
}

function main_error_price()
{
	setTimeout(main_refresh_price, delay*2);
}

function main_refresh_price()
{
	var url = "/site/graph_price_results";
	$.get(url, '', main_ready_price).error(main_error_price);
}

function graph_init_price(data)
{
	$('#graph_results_price').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_price', t,
	{
		title: '<b>Estimate (mBTC/{$algo_unit}/day)</b>',
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

	});
}

///////////////////////////////////////////////////////////////////////

function user_hashrate_ready(data)
{
	user_hashrate_graph_init(data);
	setTimeout(user_hashrate_refresh, delay);
}

function user_hashrate_error()
{
	setTimeout(user_hashrate_refresh, delay*2);
}

function user_hashrate_refresh()
{
	var url = "/site/graph_user_results?address=$wallet";
	$.get(url, '', user_hashrate_ready).error(user_hashrate_error);
}

function user_hashrate_graph_init(data)
{
	$('#graph_results_hashrate').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_hashrate', t,
	{
		title: '<b>$wallet Hashrate (Mh/s)</b>',
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

</script>


end;
