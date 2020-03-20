<?php
$algo = user()->getState('yaamp-algo');

JavascriptFile("/extensions/jqplot/jquery.jqplot.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.dateAxisRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.barRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.highlighter.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.cursor.js");
JavascriptFile('/yaamp/ui/js/auto_refresh.js');

$this->widget('UniForm');

$renter = getrenterparam(user()->getState('yaamp-deposit'));
if (!$renter) return;

echo <<<end
<style>
.yaamp-login-container
{
	padding: 20px;
	border: 1px solid #ddd;
	border-radius: 8px;
	-moz-border-radius: 8px;
	-webkit-border-radius: 8px;
}
</style>

<table cellspacing=20 width=100%>
<tr><td valign=top width=50%>

<div class="yaamp-login-container">
<form action='/renting?address=$renter->address' method='post'>

<p style='font-size: 1.2em;'><b>This is your bitcoin deposit address to use to fund your account.</b></p>
<p style='font-size: 1.2em;'><b>Save it as you will need it to login the next time you want to access your account.</b></p>

<span style='font-family: monospace; background-color: #eee; font-size: 1.3em;'>$renter->address</span><br>
<img width="200" height="200" src="https://chart.googleapis.com/chart?cht=qr&amp;chl=bitcoin%3A$renter->address&amp;choe=UTF-8&amp;chs=200x200">

<p>Minimum deposit 0.001 BTC.</p>

<p>Choose a password to secure access to your account. The email is optional and may be used in the future
if you forget your password.</p>

<table cellspacing=10>
<tr><td>Email</td><td><input value='$renter->email' type="text" name="deposit_email" placeholder="optional" class="main-text-input" style='width: 280px;'></td></tr>
<tr><td>API Key</td><td><input readonly value='$renter->apikey' type="text" name="deposit_apikey" class="main-text-input" style='width: 280px;'></td></tr>
<tr><td>Deposit Address</td><td><input readonly value='$renter->address' type="text" name="deposit_address" class="main-text-input" style='width: 280px;'></td></tr>
<tr><td>Password</td><td><input type="password" name="deposit_password" placeholder='leave empty for no change' class="main-text-input" style='width: 280px;'></td></tr>
<tr><td>Confirm</td><td><input type="password" name="deposit_confirm" class="main-text-input" style='width: 280px;'></td></tr>
</table>

<br><br>
<input type="submit" value="Save" class="main-submit-button">
<input type="button" value="Cancel" class="main-submit-button" onclick='javascript:window.history.back()'>
</form>
</div>

</td><td valign=top>

<div id='pool_current_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>

<div class="main-left-box">
<div class="main-left-title">Last 24 Hours Renting ($algo)</div>
<div class="main-left-inner"><br>
<div id='graph_results_price' style='height: 240px;'></div><br>
</div></div><br>

</td></tr></table>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<script>

function page_refresh()
{
	pool_current_refresh();
	main_refresh_price();
}

function select_algo(algo)
{
	window.location.href = '/site/algo?algo='+algo;
}

////////////////////////////////////////////////////

function pool_current_ready(data)
{
	$('#pool_current_results').html(data);
}

function pool_current_refresh()
{
	var url = "/renting/status_results";
	$.get(url, '', pool_current_ready);
}

///////////////////////////////////////////////////////////////////////

function main_refresh_price()
{
	var url = "/renting/graph_price_results";
	$.get(url, '', graph_init_price);
}

function graph_init_price(data)
{
	$('#graph_results_price').empty();

	var t = $.parseJSON(data);
	var plot1 = $.jqplot('graph_results_price', t,
	{
		title: '<b>Renting Price (mBTC/Mh/day)</b>',
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

</script>

end;
