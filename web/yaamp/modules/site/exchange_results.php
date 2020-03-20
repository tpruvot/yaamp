<?php
$orders = getdbolist('db_orders', "1 order by (amount*bid) desc");

echo "<br><table class='dataGrid'>";
//showTableSorter('maintable');
echo "<thead>";
echo "<tr>";
echo "<th width=20></th>";
echo "<th>Name</th>";
echo "<th>Exchange</th>";
echo "<th>Created</th>";
echo "<th>Quantity</th>";
echo "<th>Ask</th>";
echo "<th>Bid</th>";
echo "<th>Value</th>";
//echo "<th></th>";
echo "</tr>";
echo "</thead><tbody>";

$totalvalue = 0;
$totalbid = 0;

foreach ($orders as $order)
{
    $coin = getdbo('db_coins', $order->coinid);
    $marketurl = getMarketUrl($coin, $order->market);
    $coinimg = CHtml::image($coin->image, $coin->symbol, array(
        'width' => '16'
    ));

    echo "<tr class='ssrow'>";

    $created = datetoa2($order->created) . ' ago';
    $price = $order->price ? bitcoinvaluetoa($order->price) : '';

    $price = bitcoinvaluetoa($order->price);
    $bid = bitcoinvaluetoa($order->bid);
    $value = bitcoinvaluetoa($order->amount * $order->price);
    $bidvalue = bitcoinvaluetoa($order->amount * $order->bid);
    $totalvalue += $value;
    $totalbid += $bidvalue;
    $bidpercent = $value > 0 ? round(($value - $bidvalue) / $value * 100, 1) : 0;

    echo '<td width="16">' . $coinimg . '</td>';
    echo "<td><b><a href='/site/coin?id=$coin->id'>$coin->name</a></b>&nbsp;($coin->symbol)</td>";
    echo "<td><b><a href='$marketurl' target=_blank>$order->market</a></b></td>";

    echo "<td>$created</td>";
    echo "<td>$order->amount</td>";
    echo "<td>$price</td>";
    echo "<td>$bid ({$bidpercent}%)</td>";
    echo $bidvalue > 0.01 ? "<td><b>$bidvalue</b></td>" : "<td>$bidvalue</td>";

    // 	echo "<td>";
    // 	echo "<a href='/site/cancelorder?id=$order->id'>[cancel]</a> ";
    // 	echo "<a href='/site/sellorder?id=$order->id'>[sell]</a>";
    // 	echo "</td>";
    echo "</tr>";
}

$bidpercent = $totalvalue ? round(($totalvalue - $totalbid) / $totalvalue * 100, 1) : '';

echo "<tr>";
echo "<td></td>";
echo "<td>Total</td>";
echo "<td colspan=3></td>";
echo "<td><b>$totalvalue</b></td>";
echo "<td><b>$totalbid ({$bidpercent}%)</b></td>";
echo "<td></td>";
echo "</tr>";

echo "</tbody></table>";

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
$exchanges = getdbolist('db_exchange', "1 order by send_time desc limit 150");
//$exchanges = getdbolist('db_exchange', "status='waiting' order by send_time desc");
echo "<br><table class='dataGrid'>";
echo "<thead>";
echo "<tr>";
echo "<th width=20></th>";
echo "<th>Name</th>";
echo "<th>Market</th>";
echo "<th>Created</th>";
echo "<th>Quantity</th>";
echo "<th>Estimate</th>";
echo "<th>Sold Price</th>";
echo "<th>Value</th>";
echo "<th></th>";
echo "</tr>";
echo "</thead><tbody>";

foreach ($exchanges as $exchange)
{
    $coin = getdbo('db_coins', $exchange->coinid);
    $lowsymbol = strtolower($coin->symbol);
    $coinimg = CHtml::image($coin->image, $coin->symbol, array(
        'width' => '16'
    ));

    $marketurl = getMarketUrl($coin, $exchange->market);

    if ($exchange->status == 'waiting') echo "<tr style='background-color: #e0d3e8;'>";
    else echo "<tr class='ssrow'>";

    $sent = datetoa2($exchange->send_time) . ' ago';
    $received = $exchange->receive_time ? sectoa($exchange->receive_time - $exchange->send_time) : '';
    $price = $exchange->price ? bitcoinvaluetoa($exchange->price) : bitcoinvaluetoa($coin->price);
    $estimate = bitcoinvaluetoa($exchange->price_estimate);
    $total = $exchange->price ? bitcoinvaluetoa($exchange->quantity * $exchange->price) : bitcoinvaluetoa($exchange->quantity * $coin->price);

    echo '<td width="16">' . $coinimg . '</td>';
    echo '<td><b><a href="/site/coin?id=' . $coin->id . '">' . "$coin->name</a></b>&nbsp;($coin->symbol)</td>";
    echo '<td><b><a href="' . $marketurl . '" target="_blank">' . $exchange->market . '</a></b></td>';
    echo '<td>' . $sent . '</td>';
    echo '<td>' . $exchange->quantity . '</td>';
    echo '<td>' . $estimate . '</td>';
    echo '<td>' . $price . '</td>';
    echo $total > 0.01 ? "<td><b>$total</b></td>" : "<td>$total</td>";

    echo "<td>";

    if ($exchange->status == 'waiting')
    {
        //	echo "<a href='/site/clearexchange?id=$exchange->id'>[clear]</a>";
        echo "<a href='/site/deleteexchange?id=$exchange->id'>[del]</a>";
    }

    echo "</td>";
    echo "</tr>";
}

echo "</tbody></table>";
