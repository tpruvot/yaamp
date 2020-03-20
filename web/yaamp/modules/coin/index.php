<?php

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

echo <<<end
<div align="right" style="margin-bottom: 6px;">
<input class="search" type="search" data-column="all" style="width: 140px;" placeholder="Search..." />
</div>
<style type="text/css">
tr.ssrow.filtered { display: none; }
.page .footer { clear: both; width: auto; margin-top: 16px; }
</style>
end;

showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    textExtraction: {
        6: function(node, table, n) { return $(node).attr('data'); }
    },
    widgets: ['zebra','filter'],
    widgetOptions: {
        filter_external: '.search',
        filter_columnFilters: false,
        filter_childRows : true,
        filter_ignoreCase: true
    }
}");

echo <<<end
<thead><tr>
<th data-sorter="" width="30"></th>
<th data-sorter="text">Name</th>
<th data-sorter="text">Symbol</th>
<th data-sorter="text">Algo</th>
<th data-sorter="text">Status</th>
<th data-sorter="text">Version</th>
<th data-sorter="numeric">Created</th>
<th data-sorter="numeric">Height</th>
<th data-sorter="text">Message</th>
<th data-sorter="">Links</th>
</tr></thead>
<tbody>
end;

$total_active    = 0;
$total_installed = 0;

$coins = getdbolist('db_coins', "1 ORDER BY created DESC");
foreach ($coins as $coin) {
    //    if($coin->symbol == 'BTC') continue;
    if ($coin->enable)
        $total_active++;
    if ($coin->installed)
        $total_installed++;

    $coin->errors  = substr($coin->errors, 0, 30);
    $coin->version = substr($coin->version, 0, 20);
    $difficulty    = Itoa2($coin->difficulty, 3);
    $created       = datetoa2($coin->created);

    echo '<tr class="ssrow">';
    echo '<td><img src="' . $coin->image . '" width="18"></td>';

    echo '<td><b><a href="/coin/update?id=' . $coin->id . '">' . $coin->name . '</a></b></td>';

    if ($this->admin)
        echo "<td><b><a href='/site/update?id=$coin->id'>$coin->symbol</a></b></td>";
    else
        echo "<td><b>$coin->symbol</b></td>";

    echo "<td>$coin->algo</td>";

    if ($coin->enable)
        echo "<td>running</td>";

    else if ($coin->installed)
        echo "<td>installed</td>";

    else
        echo "<td></td>";

    echo "<td>$coin->version</td>";
    echo '<td data="' . $coin->created . '">' . $created . '</td>';

    //    echo "<td align=right>$difficulty</td>";
    echo '<td align="center">' . $coin->block_height . '</td>';

    echo "<td>$coin->errors</td>";
    echo "<td>";

    if (!empty($coin->link_bitcointalk))
        echo "<a href='$coin->link_bitcointalk' target=_blank>forum</a> ";

    if (!empty($coin->link_github))
        echo "<a href='$coin->link_github' target=_blank>git</a> ";

    //    if(!empty($coin->link_explorer))
    //        echo "<a href='$coin->link_explorer' target=_blank>expl</a> ";

    echo "<a href='http://google.com/search?q=$coin->name%20$coin->symbol%20bitcointalk' target=_blank>google</a> ";

    //    if(!empty($coin->link_exchange))
    //        echo "<a href='$coin->link_exchange' target=_blank>exch</a> ";

    $list2 = getdbolist('db_markets', "coinid=$coin->id");
    foreach ($list2 as $market) {
        $url = getMarketUrl($coin, $market->name);
        echo "<a href='$url' target=_blank>$market->name</a> ";
    }


    echo "</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "<tfoot>";

$total = count($coins);

echo '<tr class="ssrow sfooter">';
echo '<th></th>';
echo '<th colspan="9">';
echo "<b>$total coins, $total_installed installed, $total_active running</b>";
echo '<br/><br/><a href="/coin/create">Add a new coin</a>';
echo '</th>';
echo "</tr>";

echo '</tfoot>';
echo "</table>";

echo "<br><br><br><br><br>";
