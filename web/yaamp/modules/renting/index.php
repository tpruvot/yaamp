<?php
$algo = user()->getState('yaamp-algo');

JavascriptFile("/extensions/jqplot/jquery.jqplot.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.dateAxisRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.barRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.highlighter.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.cursor.js");
JavascriptFile('/yaamp/ui/js/auto_refresh.js');

$this->widget('UniForm');

$balance = bitcoinvaluetoa($renter->balance);

echo <<<END

<table cellspacing=20 width=100%>
<tr><td valign=top width=50%>

<!--  -->

<div id='balance_results'></div>
<div id='orders_results'></div>

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

<script>

function page_refresh()
{
	balance_refresh();
	orders_refresh();
	all_orders_refresh();
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

////////////////////////////////////////////////////

function balance_ready(data)
{
	$('#balance_results').html(data);
}

function balance_refresh()
{
	var url = "/renting/balance_results?address=$renter->address";
	$.get(url, '', balance_ready);
}

////////////////////////////////////////////////////

function orders_ready(data)
{
	$('#orders_results').html(data);
}

function orders_refresh()
{
	var url = "/renting/orders_results?address=$renter->address";
	$.get(url, '', orders_ready);
}

////////////////////////////////////////////////////

function all_orders_ready(data)
{
	$('#all_orders_results').html(data);
}

function all_orders_refresh()
{
	var url = "/renting/all_orders_results?address=$renter->address";
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

////////////////////////////////////////////////////////////////////////////////////////////////////

function order_edit(jobid)
{
	$('#order-edit-dialog').load('/renting/orderdialog?address=$renter->address&id='+jobid).dialog(
	{
		title: 'Edit Job',
		autoOpen: true,
		modal: true,
		width: 480,
		height: 480,
		buttons:
		{
			"Submit": function()
			{
				$('#order-edit-form').submit();
			},

			"Cancel": function()
			{
				$(this).dialog('close');
			},

			"Delete": function()
			{
				var r = confirm("Are you sure you want to delete this job?");
				if(r) window.location.href = '/renting/orderdelete?id='+jobid;
			},
		}
	});
}

function order_new()
{
	$('#order-edit-dialog').load('/renting/orderdialog?address=$renter->address').dialog(
	{
		title: 'New Job',
		autoOpen: true,
		modal: true,
		width: 480,
		height: 480,
		buttons:
		{
			"Submit": function()
			{
				$('#order-edit-form').submit();
			}
		}
	});
}

function reset_spent()
{
	var r = confirm("Are you sure you want to reset the spent counter?");
	window.location.href = '/renting/resetspent?address=$renter->address';
}

function show_job_graph(jobid)
{
	if($('#graph_placeholder_job-'+jobid).is(':visible'))
	{
		$('#graph_toggle_job-'+jobid).attr('src', '/images/plus2-78.png');
		$('#graph_placeholder_job-'+jobid).hide();
	}
	else
	{
		$('#graph_toggle_job-'+jobid).attr('src', '/images/minus2-78.png');
		$('#graph_placeholder_job-'+jobid).show();

		var url = "/renting/graph_job_results?jobid="+jobid;
	//	var url = "/renting/graph_price_results";

		$.get(url, '', function (data)
		{
			$('#graph_results_job-'+jobid).empty();

			var t = $.parseJSON(data);
			var plot1 = $.jqplot('graph_results_job-'+jobid, t,
			{
				title: '<b>Hashrate (Mh/s)</b>',
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
		});
	}
}

function main_renter_tx()
{
	var w = window.open("/renting/tx?address=$renter->address", "yaamp_tx",
		"width=800,height=600,location=no,menubar=no,resizable=yes,status=yes,toolbar=no");
}

function yaamp_withdraw()
{
	$('#yaamp-withdraw').dialog(
	{
		title: 'Withdraw',
		autoOpen: true,
		modal: true,
		width: 480
	});
}

</script>

<!-- ------------------------------------------------------------------------------ -->

<div id="order-edit-dialog" style='display: none; overflow: hidden;'></div>


<div id="yaamp-withdraw" style='display: none; overflow: hidden;'>
<br>
<form action='/renting/withdraw' method='post'>

Amount: <input type="text" name="withdraw_amount" class="main-text-input" style='width: 100px;' value='$balance'><br>
Address: <input type="text" name="withdraw_address" class="main-text-input" style='width: 300px;'>

<br><br>
<p>withdraw fees 0.0001</p>
<br>
<input type="submit" value="Withdraw" class="main-submit-button">
</form>

</div>

END;
