<?php
echo getAdminSideBarLinks();

echo <<<end

<div id='main_results'></div>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<script>

var main_delay = 30000;

$(function()
{
	main_refresh();
});

function main_ready(data)
{
	$('#main_results').html(data);
	setTimeout(main_refresh, main_delay);
}

function main_error()
{
	setTimeout(main_refresh, main_delay*2);
}

function main_refresh()
{
	var url = "/site/connections_results";
	$.get(url, '', main_ready).error(main_error);
}

</script>

end;
