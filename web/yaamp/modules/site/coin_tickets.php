<?php

if (!$coin) $this->goback();
$DCR = ($coin->rpcencoding == 'DCR');

if (!$DCR) $this->goback();

$this->pageTitle = 'Tickets - '.$coin->symbol;

// last week
$list_since = arraySafeVal($_GET,'since',time()-(7*24*3600));

$maxrows = arraySafeVal($_GET,'rows', 2500);

$remote = new WalletRPC($coin);
$info = $remote->getinfo();
$stakeinfo = $remote->getstakeinfo();
$walletinfo = $remote->walletinfo(); // pfff
$balances = $remote->getbalance('*',0);
$locked = 0; $balance = 0;
if (isset($balances["balances"])) {
	foreach ($balances["balances"] as $accb) {
		$locked += arraySafeVal($accb, 'lockedbytickets', 0);
		$balance += arraySafeVal($accb, 'spendable', 0);
	}
}

echo getAdminSideBarLinks().'<br/><br/>';
echo getAdminWalletLinks($coin, $info, 'tickets').'<br/><br/>';

//////////////////////////////////////////////////////////////////////////////////////

JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

echo <<<end
<style type="text/css">
td.missed { color: darkred; }
tr.voted { color: darkgreen; }
div.balance { text-align: right; height: 30px; width: 200px; float: right; margin-top: -80px; margin-bottom: 16px; }
div.form { text-align: right; height: 30px; width: 350px; float: right; margin-top: -48px; margin-bottom: 16px; margin-right: -8px; }
.tool-button { padding: 3px 5px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; }
.main-submit-button { cursor: pointer; }
</style>

<div class="balance" style="display: block;">
Stake: </b>{$locked} {$coin->symbol}<br/>
Spendable: </b>{$balance} {$coin->symbol}<br/>
</div>

<div class="form">
<form action="/site/ticketBuy?id={$coin->id}" method="post" style="padding: 8px;">
<input type="button" id="autofill" class="tool-button" value="fill" />
<input type="text" name="spendlimit" class="main-text-input" placeholder="Spend limit" autocomplete="off" style="width: 80px; margin-right: 4px;">
<input type="text" name="quantity" class="main-text-input" placeholder="Quantity" style="width: 60px; margin-right: 4px;">
<input type="submit" value="Buy" class="main-submit-button" >
</form>
</div>

end;

showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	widgets: ['zebra','Storage','saveSort'],
	widgetOptions: {
		saveSort: true
	}
}");

echo <<<end
<table class="dataGrid">
<thead class="">
<tr>
<th>Time</th>
<th>Category</th>
<th>Amount</th>
<th>Stake</th>
<th>Height</th>
<th>Confirm</th>
<th>Fees</th>
<th>Tx</th>
</tr>
</thead><tbody>
end;

$account = $DCR ? '*' : '';

$txs = $remote->listtransactions($account, $maxrows);

if (empty($txs)) {
	if (!empty($remote->error)) {
		echo "<b>RPC Error: {$remote->error}</b><p/>";
	}
	// retry...
	$txs = $remote->listtransactions($account, 400);
}

$txs_array = array(); $lastday = '';

if (!empty($txs)) {
	// to hide truncated days sums
	$tx = reset($txs);
	if (count($txs) == $maxrows)
		$lastday = strftime('%F', arraySafeVal($tx,'blocktime', $tx['time']));

	if (!empty($txs)) foreach($txs as $tx)
	{
		if (arraySafeVal($tx,'time',time()) > $list_since)
			$txs_array[] = $tx;
	}
	krsort($txs_array);
}

$voted_txs = array();
$list_txs = array();
$tickets = $remote->gettickets(true);

// normal value since 0.1.5
$amountin_mul = $info['version'] >= 10500 ? 1.0 : 0.00000001;

// extract  stxs from decred transactions
if (!empty($txs_array)) {

	$prev_tx = array(); $lastday = '';
	foreach($txs_array as $key => $tx)
	{
		// required after a wallet resynch/import
		$txs_array[$key]['time'] = min($tx['timereceived'], arraySafeVal($tx,'blocktime', $tx['time']));

		$prev_txid = arraySafeVal($prev_tx,"txid");

		$category = $tx['category'];
		if (arraySafeVal($tx, 'txtype') == 'regular') {
			unset($txs_array[$key]);
			continue;
		}
		if ($category == 'send' && arraySafeVal($tx,'generated')) {
			$txs_array[$key]['category'] = 'spent';
		}
		else if ($category == 'send' && $tx['amount'] == -0) {
			$stx = $remote->getrawtransaction($tx['txid'], 1);

			// voted (listed twice ? in listtransactions)
			if ($tx['vout'] > 0) {
				if (in_array($tx['txid'], $list_txs)) continue; // dup
				$category = 'ticket';
				// ticket price
				$input = 0.;
				if ($stx && !empty($stx['vin'])) foreach ($stx['vin'] as $vin) {
					$input += $vin['amountin'] * $amountin_mul;
				}
				$txs_array[$key]['input'] = $input;
				$list_txs[] = $tx['txid'];
			} else {
				$category = 'stake';
				if ($stx && isset($stx['vin'][0])) {
					// won ticket value
					$txs_array[$key]['amount'] = $stx['vin'][0]['amountin'] * $amountin_mul;
				}
				if ($stx && isset($stx['vin'][1])) {
					$voted_txs[] = arraySafeVal($stx['vin'][1],'txid');
					$list_txs[] = $stx['vin'][1]['txid'];
				}
			}

			$txs_array[$key]['category'] = $category;
			$txs_array[$key]['stx'] = $stx;

		}
		else if ($category == 'send' && $prev_txid === arraySafeVal($tx,"txid")) {
			// if txid is the same as the last income... it's not a real "send"
			if ($prev_tx['amount'] == 0 - $tx['amount'])
				$txs_array[$key]['category'] = 'spent';
		}
		else if ($category == 'receive') {
			$prev_tx = $tx;
		}
		// for truncated day sums
		if ($lastday == '' && count($txs) == $maxrows)
			$lastday = strftime('%F', arraySafeVal($tx,'blocktime', $tx['time']));
	}
	if ($info['version'] < 1010200)
		ksort($txs_array);
}

if (!empty($tickets)) foreach ($tickets['hashes'] as $n => $txid) {
	if (!in_array($txid, $list_txs)) {
		$stx = $remote->getrawtransaction($txid, 1);
		$k = time() - arraySafeVal($stx,'time', time()) + $n; // sort key
		$stx['category'] = 'ticket';
		if (isset($stx['vin'][0]))
			$stx['input'] = $stx['vin'][0]['amountin'] * $amountin_mul;
		$commitamt = 0.;
		foreach ($stx['vout'] as $v) {
			if (arraySafeVal($v['scriptPubKey'],'type') == 'sstxcommitment')
				$commitamt += $v['scriptPubKey']['commitamt'];
		}
		if ($commitamt && isset($stx['vout'][0])) {
			$stx['fee'] = round($commitamt - $stx['vout'][0]['value'], 4);
		}
		$stx['stx'] = $stx;
		$txs_array[$k] = $stx;
	}
	if ($info['version'] < 1010200)
		ksort($txs_array);
}

$rows = 0;
foreach($txs_array as $tx)
{
	$category = arraySafeVal($tx, 'category');

	if ($category != 'stake' && $category != 'ticket') continue;

	$conf = arraySafeVal($tx,'confirmations');
	if ($category == 'stake' && $conf < 256) $category = 'immature';

	$stx = arraySafeVal($tx,'stx');
	$stake = ''; $amount = '';
	if ($category == 'ticket') {
		$stake = arraySafeVal($stx, 'stake', $stx['vout'][0]['value']);
		if ($stake === 0) continue;
		if (in_array($tx['txid'], $voted_txs)) $category = 'voted';
		else if (!in_array($tx['txid'], $tickets['hashes'])) $category = 'missed';
	} else {
		$amount = (double) arraySafeVal($tx,'amount');
		$stake = $amount - $stx['vout'][2]['value'];
		if (isset($stx['vout'][3]['value'])) // with pool fees
			$stake -= arraySafeVal($stx['vout'][3],'value');
	}

	$block = null;
	if(isset($tx['blockhash']))
		$block = $remote->getblock($tx['blockhash']);

	echo '<tr class="ssrow '.$category.'">';

	$d = datetoa2(arraySafeVal($tx,'time', time()));
	echo '<td><b>'.$d.'</b></td>';

	echo '<td>'.$category.'</td>';
	echo '<td>'.$amount.'</td>';
	echo '<td>'.$stake.'</td>';

	if($block)
		echo '<td>'.$block['height'].'</td>';
	else
		echo '<td></td>';

	echo '<td>'.$conf.'</td>';

	$fees = abs(arraySafeVal($tx,'fee'));
	echo '<td>'.($fees ? altcoinvaluetoa($fees) : '').'</td>';

	echo '<td>';
	if(!empty($block)) {
		$txid = arraySafeVal($tx, 'txid');
		$label = substr($txid, 0, 7);
		echo $coin->createExplorerLink($label, array('txid'=>$txid), array('target'=>'_blank'));
	}
	echo '</td>';

	echo '</tr>';

	$rows++;
	if ($rows >= $maxrows) break;
}

echo '</tbody></table><br>';

echo '<b>Ticket price: </b>'.$stakeinfo['difficulty'].' + '.$remote->getticketfee().' '.$coin->symbol.'/kB<br/>';
echo '<b>Tickets: </b>'.$stakeinfo['live'];
if ($stakeinfo['immature']) echo ' + '.$stakeinfo['immature'].' immature';
if ($stakeinfo['ownmempooltix']) echo ' + '.$stakeinfo['ownmempooltix'].' purchased';
echo '<br/>';
if (arraySafeVal($stakeinfo,'missed',false)) {
	echo '<b>Missed: </b>'.arraySafeVal($stakeinfo,'missed', -1);
	echo ' ('.arraySafeVal($stakeinfo,'revoked',0).' revoked';
	echo ', '.arraySafeVal($stakeinfo,'expired',0).' expired';
	echo ')<br/>';
}
echo '<b>Total won: </b>'.$stakeinfo['totalsubsidy'].' '.$coin->symbol.' ('.$stakeinfo['voted'].')<br/>';

$voting = arraySafeVal($walletinfo,'voting',0) > 0 ? 'enabled' : 'disabled';
echo '<br/>';
echo '<b>Voting: </b>'.$voting.'<br/>';

$netfees = $remote->ticketfeeinfo(1,1);
if (!empty($netfees)) {
	$fees = reset($netfees['feeinfoblocks']);
	echo '<br/>';
	echo '<b>Last block ticket fees (network): </b></br>';
	echo 'From '.arraySafeVal($fees,'min').' to '.arraySafeVal($fees,'max').' '.$coin->symbol.'/kB<br/>';
}

//echo '<pre>'.json_encode($stakeinfo, 128).'</pre>';

$spendlimit = round(ceil(10.0 * $stakeinfo['difficulty'])/10, 1);
JavascriptReady("$('#autofill').click(function() {
	$('form input[name=spendlimit]').val('{$spendlimit}');
});");
