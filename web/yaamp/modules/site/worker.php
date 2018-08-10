<?php
echo getAdminSideBarLinks();

$algo = user()->getState('yaamp-algo');
$algos = yaamp_get_algos();
$algo_opts = '';
foreach($algos as $a) {
	if($a == $algo)
		$algo_opts .= "<option value='$a' selected>$a</option>";
	else
		$algo_opts .= "<option value='$a'>$a</option>";
}
if (!strstr($algo_opts, 'selected') && $this->admin) {
	$algo_opts = "<option value=\"$algo\" selected>$algo</option>" . $algo_opts;
}

echo <<<end
<div align="right" style="margin-top: -14px; margin-bottom: -6px; margin-right: 140px;">
Select Algo: <select id="algo_select">$algo_opts</select>&nbsp;
</div>
end;
?>

<div id='main_results'></div>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<script>

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
	var url = '/site/worker_results?algo=' + $('#algo_select').val();

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

$('#algo_select').bind('change', function() {
	main_refresh();
});

</script>



