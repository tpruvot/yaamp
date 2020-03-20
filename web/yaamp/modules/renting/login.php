<?php
$algo = user()->getState('yaamp-algo');

JavascriptFile("/extensions/jqplot/jquery.jqplot.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.dateAxisRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.barRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.highlighter.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.cursor.js");
JavascriptFile('/yaamp/ui/js/auto_refresh.js');

$this->widget('UniForm');

$address = getparam('address');
if ($address == 0) $address = '';
if (!empty($address) && preg_match('/[^A-Za-z0-9]/', $address))
{
    die;
}

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
<form action='/renting/login' method='post'>

end;


if (!YAAMP_RENTAL) echo "<p style='font-size: 1.2em; font-weight: bold; color: red;'>Renting is temporarily disabled.</p>";

echo <<<end
<p style='font-size: 1.2em; font-weight: bold;'>You need to login to access the renting area.</p>

<p>Type your deposit address and password below if you already registered.</p>

<p>When you register, you will be given a bitcoin deposit address to which you can send funds. You will
then be allowed to rent hashpower to use on third party pools.</p>

<table cellspacing=10>
<tr><td>Deposit Address</td><td><input type="text" value='$address' name="deposit_address" class="main-text-input" style='width: 280px;'></td></tr>
<tr><td>Password</td><td><input type="password" name="deposit_password" class="main-text-input" style='width: 280px;'></td></tr>
</table>

<br><br>

<input type="submit" value="Login" class="main-submit-button">

end;


$recents = isset($_COOKIE['deposits']) ? explode("|", $_COOKIE['deposits']) : array();

if (controller()->admin || sizeof($recents) < 10) echo "<input type=button value='Register' class='main-submit-button' onclick='javascript:deposit_create()' >";

echo "</form><br><br>";

echo "<table class='dataGrid2'>";

foreach ($recents as $address)
{
    if (empty($address)) continue;

    $renter = getdbosql('db_renters', "address=:address", array(
        ':address' => $address
    ));
    if (!$renter) continue;
    //	debuglog($address);
    echo "<tr class='ssrow'><td width=24>";
    echo "<img width=16 src='/images/btc.png'>";
    echo "</td><td><a href='/renting/login?address=$renter->address' style='font-family: monospace; font-size: 1.1em;'>$address</a></td>";
    echo "<tr>";
}

echo "</table><br>";

echo <<<end

</div>

</td><td valign=top>

<div id='pool_current_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>

<div id='all_orders_results'></div>

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

<!-- ------------------------------------------------------------------------------ -->

<div id="deposit-create-dialog" style='display: none; overflow: hidden;'>
<form action='/renting/create' method='post'>
<p>You are about to create a new Bitcoin deposit address to send fund to. You will then be able to rent hashpower from yaamp.</p>

<p>It is recommended that you send small amount (minimum 0.001) to start with and make sure your pool is working fine with yaamp.</p>
<div>
end;
$this->widget('CCaptcha');
echo <<<end
</div>
<p>Enter the code in the field below and click the Register button to get your new deposit address.</p>
<br>Code:
<input type="text" name="create_code" class="main-text-input" style='width: 200px;' autofocus>
<br><br><br>
<input type="submit" value="Register" class="main-submit-button">
</form>
<br>

</div>

<!-- ------------------------------------------------------------------------------ -->

<script>

function page_refresh()
{
	pool_current_refresh();
	main_refresh_price();
	all_orders_refresh();
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

////////////////////////////////////////////////////

function all_orders_ready(data)
{
	$('#all_orders_results').html(data);
}

function all_orders_refresh()
{
	var url = "/renting/all_orders_results";
	$.get(url, '', all_orders_ready);
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

function deposit_create()
{
	$('#deposit-create-dialog').dialog(
	{
		title: 'Create Deposit Address',
		autoOpen: true,
		modal: true,
		width: 480
	});
}

</script>


end;
