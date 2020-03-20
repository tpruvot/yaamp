<?php
echo getAdminSideBarLinks();

$coin_id = getiparam('id');
if ($coin_id)
{
    $coin = getdbo('db_coins', $coin_id);
    $this->pageTitle = 'Earnings - ' . $coin->symbol;
}

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

?>

<div id='main_results'></div>

<script type="text/javascript">

var main_delay=60000;
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
	var url = '/site/earning_results?id=<?=$coin_id
?>';
	var minh = $(window).height() - 150;
	$('#main_results').css({'min-height': minh + 'px'});

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

</script>

<?php
JavascriptReady("main_refresh();");
