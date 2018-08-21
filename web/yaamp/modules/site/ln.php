<?php

JavascriptFile("/extensions/jqplot/jquery.jqplot.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.dateAxisRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.barRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.highlighter.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.cursor.js");
JavascriptFile('/yaamp/ui/js/auto_refresh.js');

echo <<<END
<!--
<div id='resume_update_button' style='color: #444; background-color: #ffd; border: 1px solid #eea;
        padding: 10px; margin-left: 20px; margin-right: 20px; margin-top: 15px; cursor: pointer; display: none;'
        onclick='auto_page_resume();' align=center>
        <b>Auto refresh is paused - Click to resume</b></div>
-->
<table cellspacing=20 width=100%>
<tr><td valign=top width=50%>
END;

echo <<<END
<div class="main-left-box">
<div class="main-left-title">Lightning Network (testnet)</div>
<div class="main-left-inner">
END;

//echo "<h1>Lightning Network (testnet)</h1>";

echo "<p>Lightning Network (LN) is a second-layer network based on bitcoin on-chain transactions. ";
echo "It allows to perform bitcoin transactions with less fees and high speed.</p>";
//echo "More informations: <a href=\"https://lightning.network/\">lightning.network</a></p>";
echo "<p><b>This page allows you to pay LN bills/invoices on testnet for any service on testnet.</b></p>";

//echo "<h2> ?</h2>";
?>


<ul>
<!--
<li>Bitcoin, altcoins and mining pools are still in development.</li>
<li>This pool allows miners to pay any testnet bill from <a href="https://testnet.millionbitcoinhomepage.net/">The Million Bitcoin Homepage</a>
(but you can also try with yaals, Starblocks or testnet.satoshis.place) using testnet coins.</li>
<b>How to use ?</b>
<li>1. Go to <a href="https://testnet.millionbitcoinhomepage.net/">The Million Bitcoin Homepage</a>,</li>
<li>2. Make a drawing, click on "Publish your pixels",</li>
<li>3. Copy/paste the payment request (invoice bolt11 reference) into the field here below and click on "Pay my testnet bill".</li>
<li>4. Refresh the page to make sure that the payment was executed.</li>
-->
<?php

$bill = getparam('bill');
if (!empty($bill) && preg_match('/[^A-Za-z0-9]/', $bill)) {
echo "<b>Please type a bolt11 testnet bill. <a href='/site/ln'>Try again</a></b>";
//      die;
}
else if (!empty($bill)) {
// Save bill (or pay it directly if enough funds)
$db_bill = getdbosql('db_invoices', "bolt11=:bolt11", array(':bolt11'=>$bill));
// etdbo('db_invoices', $bill);

if (!$db_bill)  {
        echo  <<<END
<br />
<div class="main-left-box">
<div class="main-left-title">Invoice payment status</div>
<div class="main-left-inner">
<p>
        <b>Your invoice was saved in the list of invoices to pay</b>, it will be paid soon. Please refresh this page in few seconds.<br />
A scheduled task is running once per 20 seconds to pay automatically all bills in the list.
</p>
</div></div>
END;
        dborun("INSERT IGNORE INTO invoices(bolt11, status) VALUES (:key,:val)", array(
                ':key'=>$bill,':val'=>"New"
        ));
}
else    {
        echo <<<END
<br />
<div class="main-left-box">
<div class="main-left-title">Invoice payment status</div>
<div class="main-left-inner">
END;
        echo "<p><b>This bill is already into the list of bills.</b><br />";
        echo "Its status is: <b>".$db_bill->status."</b><br />";
        switch ($db_bill->status)       {
                case 'New':
                        echo "<b>The scheduled task did not run yet, your invoice will be processed in a short while.</b>";
                        break;
                case 'maxfee':
                        echo "<b>The fee associated with your invoice is too high compared to the invoice amount. As the maximum fee is 4% for normal invoices, your invoice won't be paid. Please try again with a higher amount.</b>";
                        break;
                case 'synok':
                        echo "<b>Synthax and destinator of the bill are correct.</b>";
                        break;
                case 'underpay':
                        echo "Payment of bill is <b>ongoing</b>.";
                        break;
                case 'complete':
                        echo "<b style='color: green;'>Bill of ".$db_bill->amount." millisatoshi is paid (";
                        echo number_format($db_bill->amount / 1000, 0)." satoshi = ";
                        echo number_format($db_bill->amount / 100000000000, 8)." bitcoin).</b><br />";
                        echo "Fee was ".($db_bill->paid - $db_bill->amount)." millisatoshi (";
                        echo number_format(100*($db_bill->paid - $db_bill->amount)/$db_bill->amount, 2)." %)";
                        break;
                default:
                        echo "Err";
        }
echo "</p><p>";
global $configLNGamePlayers;
$found = false;
if ($db_bill->shop != "")       {
//echo $db_bill->shop;
//var_dump($db_bill);
        foreach ($configLNGamePlayers as $name => $player)      {
                if ($player[0] == $db_bill->shop)       {
                        echo "<b>Shop:</b> <a href='http://$name'>".$name."</a> (".$db_bill->shop.") ";
                        $found = true;
                        break;
                        }
                }
        if ($found == false)    {
                echo "<b>Shop:</b> Unknown shop (".$db_bill->shop.").";
                }

echo "<br /><b>Amount:</b> ".$db_bill->amount. " millisatoshi (";
                        echo number_format($db_bill->amount / 1000, 3)." satoshi = ";
                        echo number_format($db_bill->amount / 100000000000, 11)." bitcoin).<br />";
echo "<b>Description:</b> ".$db_bill->description;
        }
echo "</p></div></div>";

}
echo "<br />";
echo "<p>Please refresh this screen to check the status of payment of the testnet bill.<br /></p>";

}
else
echo <<<END
<br />
<div class="main-left-box">
<div class="main-left-title">Pay testnet bill:</div>
<div class="main-left-inner">
<form method="GET" action="/site/ln" style="padding: 10px;">
<label for="bill">Enter your testnet bill:</label>
<input type="text" name="bill" class="main-text-input" placeholder="invoice"/>
<input type="submit" value="Pay invoice"  class="main-submit-button" />
<br />
<br />
Your testnet bill should be created on a testnet shop such as <a href="https://testnet.millionbitcoinhomepage.net/">The Million Bitcoin Homepage</a> or another testnet shop that works with the LN node of the pool.
</form>
</div></div>
<br />
END;

echo <<<END
<div class="main-left-box">
<div class="main-left-title">How to use ?</div>
<div class="main-left-inner">
<ul>
<li><b>Bitcoin, altcoins and mining pools are still in development.</b></li>
<li>This pool allows miners to pay any testnet bill from <a href="https://testnet.millionbitcoinhomepage.net/">The Million Bitcoin Homepage</a>
(but you can also try with other LN testnet merchants such as <a href="https://yalls.org/">yaals</a>, <a href="https://starblocks.acinq.co/">
Starblocks</a> or <a href="https://testnet.satoshis.place/">testnet.satoshis.place</a>) with Lightning Network on Bitcoin testnet.</li>
<b>How to use ?</b>
<li>1. Go to <a href="https://testnet.millionbitcoinhomepage.net/">The Million Bitcoin Homepage</a>,</li>
<li>2. Make a drawing, click on "Publish your pixels",</li>
<li>3. Copy/paste the payment request (invoice bolt11 reference) into the field here below and click on "Pay my testnet bill".</li>
<li>4. Refresh the page to make sure that the payment was executed.</li>

</div></div>

<br />

<div class="main-left-box">
<div class="main-left-title">How does it work ?</div>
<div class="main-left-inner">
<p>
<ul>
<li>It is based on magic enhanced features of Bitcoin and Lightning Network. Integration within YiiMP will be released as opensource soon.</li>
<li>More informations: <a href="https://lightning.network/">lightning.network</a></li>
<li><a href="http://lightningnetworkstores.com/testnet">Testnet stores list</a></li>
<li><a href="https://github.com/phm87/LN-testnet-list">Testnet Ressources list</a></li>
</ul>
</p>
END;

echo "</div></div>
<br />";
?>
<br />

</ul>


<?php
echo "</td><td valign=top>";
echo <<<END
<div id='pool_current_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>
END;

echo <<<END
</td></tr></table>

<script>
function page_refresh()
{
        pool_current_refresh();
        pool_history_refresh();
}
function select_algo(algo)
{
        window.location.href = '/site/algo?algo='+algo+'&r=/';
}
////////////////////////////////////////////////////
function pool_current_ready(data)
{
        $('#pool_current_results').html(data);
}
function pool_current_refresh()
{
        var url = "/site/current_results";
        $.get(url, '', pool_current_ready);
}
////////////////////////////////////////////////////
function pool_history_ready(data)
{
        $('#pool_history_results').html(data);
}
function pool_history_refresh()
{
        var url = "/site/history_results";
        $.get(url, '', pool_history_ready);
}
</script>

END;
?>
