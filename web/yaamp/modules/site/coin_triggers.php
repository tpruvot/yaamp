<?php
if (!$coin) $this->goback();
$this->pageTitle = 'Triggers - ' . $coin->symbol;

$remote = new WalletRPC($coin);

echo getAdminSideBarLinks() . '<br/><br/>';

$info = $remote->getinfo();
if (!$info)
{
    echo $remote->error;
    return;
}

echo getAdminWalletLinks($coin, $info, 'console') . '<br/><br/>';

//////////////////////////////////////////////////////////////////////////////////////
echo <<<end
<style type="text/css">
td.red { color: darkred; }
table.dataGrid a.red { color: darkred; }
.main-submit-button { cursor: pointer; }
div.form { float: left; width: 450px; }
div.help { float: left; color: #555; background-color: #ffffe0; padding: 6px; border: 1px solid #d0d0b0; margin-top: 32px; }
.help ul { width: 10px; margin: 6px 4px; padding: 0; overflow:hidden; width:390px; -moz-column-count: 2; -webkit-column-count: 2; column-count: 2; }
.help li { list-style-type: none; width: 140px; }
.page .footer { width: auto; }
span.cmd { color: gray; }
</style>
end;


showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	textExtraction: {
		5: function(node, table, n) { return $(node).attr('data'); }
	},
	widgets: ['zebra','Storage','saveSort'],
	widgetOptions: {
		saveSort: true
	}
}");

echo <<<end
<thead>
<tr>
<th data-sorter="text">Type</th>
<th data-sorter="text">Condition</th>
<th data-sorter="currency">Value</th>
<th data-sorter="text" width="50%">Description / Command</th>
<th data-sorter="text">Status</th>
<th data-sorter="numeric" width="80">Last check</th>
<th data-sorter="" align="right" width="200">Operations</th>
</tr>
</thead><tbody>
end;


$notifications = getdbolist('db_notifications', "idcoin={$coin->id}");
foreach ($notifications as $rule)
{
    if ($rule->enabled) $operations = '<a title="Disable this rule" href="/site/triggerEnable?id=' . $rule->id . '&en=0">disable</a>';
    else $operations = '<a title="Enable this rule" href="/site/triggerEnable?id=' . $rule->id . '&en=1">enable</a>';
    $operations .= '&nbsp;<a class="red" title="Remove this market" href="/site/triggerDel?id=' . $rule->id . '">delete</a>';

    if ($rule->lasttriggered && $rule->lasttriggered == $rule->lastchecked)
    {
        $status = '<span class="green">Triggered</span>';
        $operations = '<a title="Reset trigger" href="/site/triggerReset?id=' . $rule->id . '">reset</a>' . '&nbsp' . $operations;
    }
    else
    {
        $status = '<span class="green"></span>';
    }

    $description = $rule->description;
    if (!empty($description) && !empty($rule->notifycmd)) $description .= '<br/>';
    $description .= '<span class="cmd">' . $rule->notifycmd . '</span>';

    echo '<tr class="ssrow">';

    echo '<td><b>' . $rule->notifytype . '</b></td>';
    echo '<td>' . $rule->conditiontype . '</td>';
    echo '<td>' . $rule->conditionvalue . '</td>';
    echo '<td>' . $description . '</td>';
    echo '<td>' . $status . '</td>';
    echo '<td data="' . $rule->lastchecked . '">' . datetoa2($rule->lastchecked) . '</td>';
    echo '<td align="right">' . $operations . '</td>';

    echo "</tr>";
}

echo '</tbody></table><br/>';

echo <<<end
<div class="form">
<form action="/site/triggerAdd?id={$coin->id}" method="post" style="padding: 0px;">
<input type="hidden" name="idcoin" value="{$coin->id}">
<label for="notifytype">Type</label>
<select id="notifytype" name="notifytype">
<option value="email">Email</option>
<option value="rpc">RPC command</option>
<option value="system">System command</option>
</select><br/><br/>
<input type="text" name="conditiontype" class="main-text-input" placeholder="Condition like 'balance >'" style="width: 190px; margin-right: 4px;">
<input type="text" name="conditionvalue" class="main-text-input" placeholder="Value" style="width: 100px; margin-right: 4px;">
<input type="submit" value="Add rule" class="main-submit-button" ><br/>
<input type="text" name="notifycmd" class="main-text-input" placeholder="Email or Command (optional)" style="width: 400px; margin-right: 4px; margin-top: 8px;"><br/>
<input type="text" name="description" class="main-text-input" placeholder="Description (optional)" style="width: 400px; margin-right: 4px; margin-top: 8px;">
</form>
</div>

<div class="help">
<b>Command variables:</b>
<ul>
<li>\$X  current value</li>
<li>\$F  condition db field</li>
<li>\$T  condition type</li>
<li>\$V  condition ref value</li>
<li>\$SYM  coin symbol</li>
<li>\$S2  coin symbol2</li>
<li>\$N  coin name</li>
<li>\$A  wallet address</li>
</ul>
</div>

<div style="clear: both; margin-bottom: 24px; "/>

end;



?>
