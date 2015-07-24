<?php

$coin = getdbo('db_coins', getiparam('id'));
if (!$coin) $this->goback();

$remote = new Bitcoin($coin->rpcuser, $coin->rpcpasswd, $coin->rpchost, $coin->rpcport);

$reserved1 = dboscalar("select sum(balance) from accounts where coinid=$coin->id");
$reserved2 = dboscalar("select sum(amount*price) from earnings
	where status!=2 and userid in (select id from accounts where coinid=$coin->id)");

$reserved = ($reserved1 + $reserved2) * 2;

$owed = dboscalar("select sum(amount) from earnings where status!=2 and coinid=$coin->id");
$owed_btc = $owed? bitcoinvaluetoa($owed*$coin->price): '';
$owed = $owed? altcoinvaluetoa($owed): '';

echo "cleared $reserved1, earnings $reserved2, reserved $reserved, balance $coin->balance, owed $owed, owned btc $owed_btc<br><br>";

//////////////////////////////////////////////////////////////////////////////////////

$list = getdbolist('db_markets', "coinid=$coin->id order by price desc");

echo "<table class='dataGrid'>";
echo "<thead class=''>";

echo "<tr>";
echo "<th>Name</th>";
echo "<th>Price</th>";
echo "<th>Price2</th>";
echo "<th>Sent</th>";
echo "<th>Traded</th>";
echo "<th>Late</th>";
echo "<th>Deposit</th>";
echo "<th>Message</th>";
echo "</tr>";
echo "</thead><tbody>";

$bestmarket = getBestMarket($coin);
foreach($list as $market)
{
	$marketurl = '#';
	$price = bitcoinvaluetoa($market->price);
	$price2 = bitcoinvaluetoa($market->price2);
	$lowsymbol = strtolower($coin->symbol);

	if($market->name == 'cryptsy')
		$marketurl = "https://www.cryptsy.com/markets/view/$market->marketid";

	else if($market->name == 'bittrex')
		$marketurl = "https://bittrex.com/Market/Index?MarketName=BTC-$coin->symbol";

	else if($market->name == 'poloniex')
		$marketurl = "https://poloniex.com/exchange/btc_$coin->symbol";

	else if($market->name == 'bleutrade')
		$marketurl = "https://bleutrade.com/exchange/$coin->symbol/BTC";

	else if($market->name == 'c-cex')
		$marketurl = "https://c-cex.com/?p=$lowsymbol-btc";

	else if($market->name == 'yobit')
		$marketurl = "https://yobit.net/en/trade/$coin->symbol/BTC";

	else if($market->name == 'jubi')
		$marketurl = "http://jubi.com/coin/$lowsymbol";

	else if($market->name == 'cryptopia')
		$marketurl = "https://www.cryptopia.co.nz/Exchange?market={$coin->symbol}_BTC";

	else if($market->name == 'alcurex')
		$marketurl = "https://alcurex.org/index.php/crypto/market?pair={$lowsymbol}_btc";

	if($bestmarket && $market->id == $bestmarket->id)
		echo "<tr class='ssrow' style='background-color: #dfd'>";
	else
		echo "<tr class='ssrow'>";

	echo "<td><b><a href='$marketurl' target=_blank>$market->name</a></b></td>";

	echo "<td>$price</td>";
	echo "<td>$price2</td>";

	$sent = datetoa2($market->lastsent);
	$traded = datetoa2($market->lasttraded);
	$late = $sent > $traded? 'late': '';

	echo "<td>$sent ago</td>";
	echo "<td>$traded ago</td>";
	echo "<td>$late</td>";
	echo "<td>$market->deposit_address ";

	echo "<a href='/market/update?id=$market->id'>edit</a> ";
	echo "<a href='javascript:showSellAmountDialog($market->id)'>sell</a> ";
	echo "<a href='/market/delete?id=$market->id'>del</a></td>";

	echo "<td>$market->message</td>";
	echo "</tr>";
}

echo "</tbody></table><br>";

//////////////////////////////////////////////////////////////////////////////////////

echo "<table class='dataGrid'>";
//showTableSorter('maintable');
echo "<thead class=''>";

echo "<tr>";
echo "<th width=30></th>";
echo "<th width=30></th>";
echo "<th>Name</th>";
echo "<th>Symbol</th>";

echo "<th>Difficulty</th>";
echo "<th>Blocks</th>";
echo "<th>Balance</th>";
echo "<th>BTC</th>";
echo "<th>Conns</th>";

echo "<th>Price</th>";
echo "<th>Reward</th>";
echo "<th>Index *</th>";

echo "</tr>";
echo "</thead><tbody>";

echo "<tr class='ssrow'>";
echo "<td><img src='$coin->image' width=24></td>";

if($coin->enable)
	echo "<td>[ + ]</td>";
else
	echo "<td>[&nbsp;&nbsp;&nbsp;&nbsp;]</td>";

echo "<td><b><a href='/site/coin?id=$coin->id'>$coin->name</a></b></td>";
echo "<td><b>$coin->symbol</b></td>";

$info = $remote->getinfo();
if(!$info)
{
	echo "ERROR $remote->error<br><br>";

//	echo "<td></td>";
	return;
}

$errors = isset($info['errors'])? $info['errors']: '';
$balance = isset($info['balance'])? $info['balance']: '';
$txfee = isset($info['paytxfee'])? $info['paytxfee']: '';
$connections = isset($info['connections'])? $info['connections']: '';
$blocks = isset($info['blocks'])? $info['blocks']: '';

echo "<td>$coin->difficulty</td>";
if(!empty($errors))
	echo "<td style='color: red;' title='$errors'>$blocks</td>";
else
	echo "<td>$blocks</td>";

echo "<td>$balance</td>";

$btc = $balance*$coin->price;
echo "<td>$btc</td>";
echo "<td>$connections</td>";

echo "<td>$coin->price</td>";
echo "<td>$coin->reward</td>";

if($coin->difficulty)
	$index = round($coin->reward * $coin->price / $coin->difficulty * 10000, 3);

echo "<td>$index</td>";
echo "</tr>";

echo "</tbody></table>";
echo "<br><br>";

//////////////////////////////////////////////////////////////////////////////////////

echo "<table class='dataGrid'>";
//showTableSorter('maintable');
echo "<thead class=''>";

echo "<tr>";
echo "<th>Time</th>";
echo "<th>Height</th>";
echo "<th>Category</th>";
echo "<th>Amount</th>";
echo "<th>Confirmations</th>";
echo "<th></th>";
echo "</tr>";
echo "</thead><tbody>";

//$transactions = $remote->listsinceblock('');
$ts = $remote->listtransactions('', 10);

$res_array = array();
if (!empty($ts))
foreach($ts as $val)
{
	$t = $val['time'];
	$res_array[$t] = $val;
}

krsort($res_array);
foreach($res_array as $transaction)
{
	$block = null;
	if(isset($transaction['blockhash']))
		$block = $remote->getblock($transaction['blockhash']);

	$d = datetoa2($transaction['time']);

	echo "<tr class='ssrow'>";
	echo "<td><b>$d</b></td>";

	if($block)
		echo "<td>{$block['height']}</td>";
	else
		echo "<td></td>";

	echo "<td>{$transaction['category']}</td>";
	echo "<td>{$transaction['amount']}</td>";

	if(isset($transaction['confirmations']))
		echo "<td>{$transaction['confirmations']}</td>";
	else
		echo "<td></td>";

	echo "<td>";
	if(isset($transaction['address']))
	{
		$address = $transaction['address'];
		echo "address $address<br>";
	}

//	if($block) foreach($block['tx'] as $i=>$tx)
//		echo "tx-$i <a href='$coin->block_explorer/tx/$tx' target=_blank>$tx</a><br>";

	echo "</td>";
	echo "</tr>";
}

echo "</tbody></table>";





