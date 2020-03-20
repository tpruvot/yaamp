<?php

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

echo getAdminSideBarLinks();

echo '&nbsp;<a href="/site/emptymarkets">Empty Markets</a>&nbsp;';

$server = getparam('server');

echo <<<end
<div align="right" style="margin-top: -14px; margin-bottom: 6px;">
Select Server:
<select id="server_select">
<option value="">all</option>
end;

$serverlist = dbolist("SELECT DISTINCT rpchost FROM coins WHERE installed=1 ORDER BY rpchost");
foreach ($serverlist as $srv) {
    echo '<option value="' . $srv['rpchost'] . '">' . $srv['rpchost'] . '</option>';
}

echo <<<end
</select>&nbsp;
<input class="search" type="search" data-column="all" style="width: 140px;" placeholder="Search..." />
</div>

<div id='main_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>

<br><a href='/site/create'><img width=16 src=''><b>CREATE COIN</b></a>
<!-- br><a href='/site/updateprice'><img width=16 src=''><b>UPDATE PRICE</b></a -->
<!-- br><a href='/site/dopayments'><img width=16 src=''><b>DO PAYMENTS</b></a -->

<br><br><br>

<script>

$('#server_select').change(function(event)
{
    var server = $('#server_select').val();
    clearTimeout(main_timeout);
    window.location.href = '/site/admin?server='+server;
});

$(function()
{
    main_refresh();
});

var main_delay=30000;
var main_timeout;
var lastSearch = false;

function main_ready(data)
{
    $('#main_results').html(data);
    $('#server_select').val('{$server}');

    if (lastSearch !== false) {
        $('input.search').val(lastSearch);
        $('table.dataGrid').trigger('search');
    }

    main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
    main_timeout = setTimeout(main_refresh, main_delay*2);
}

function main_refresh()
{
    var url = "/site/admin_results?server=$server";

    clearTimeout(main_timeout);
    lastSearch = $('input.search').val();
    $.get(url, '', main_ready).error(main_error);
}

</script>

end;
