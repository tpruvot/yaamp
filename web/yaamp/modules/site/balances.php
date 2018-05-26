<?php

$exch = getparam('exch');
echo getAdminSideBarLinks();

$this->pageTitle = "Balances - $exch";

?>
<style type="text/css">
p.notes { opacity: 0.7; }
</style>
<div id="main_results"></div>

<p class="notes">This table show all non-zero balances tracked by yiimp. It also allow manual API calls to manually check the exchange API reliability</p>

<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>
<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>
<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>

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
	var url = '/site/balances_results?exch=<?php echo $exch;?>';
	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

</script>

<?php

app()->clientScript->registerScript('init', 'main_refresh();', CClientScript::POS_READY);