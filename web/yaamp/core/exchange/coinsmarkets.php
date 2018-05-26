<?php

function coinsmarkets_result_valid($data, $method='')
{
	if (!is_array($data) || arraySafeVal($data,'success') != 1 || !isset($data['return'])) return false;
	return true;
}

// markets : https://coinsmarkets.com/apicoin.php

function coinsmarkets_api_query($method)
{
	$uri = "https://coinsmarkets.com/{$method}.php";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_SESSIONID_CACHE, 0);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$res= curl_exec($ch);
	$obj = json_decode($res, true);

	curl_close($ch);
	return $obj;
}

// see https://coinsmarkets.com/tradeapi.php
// balances: gettradinginfo

function coinsmarkets_api_user($method, $params='')
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_COINMARKETS_PASS')) define('EXCH_COINMARKETS_PASS', '');

	$user = EXCH_COINMARKETS_USER;
	$pass = EXCH_COINMARKETS_PASS;
	$pin  = EXCH_COINMARKETS_PIN;
	if (empty($user) || empty($pass) || empty($pin)) return false;

	$uri = "https://coinsmarkets.com/apiv1.php";
	$headers = array(
		'Accept: application/json',
	);

	if (is_array($params))
		$opts = implode('_', array_values($params));
	else
		$opts = "$params";

	$data = "username=$user&password=$pass&pin=$pin&data=$method";
	if (!empty($opts)) $data .= "[$opts]";

	$ch = curl_init($uri);
	//curl_setopt($ch, CURLOPT_VERBOSE, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; coinsmarkets PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$res = curl_exec($ch);
	if ($method == 'depositaddress') {
		// fix bad json... missing quotes on coin label...
		$res = preg_replace('/"name":([^"]*),/', '"name":"${1}",', $res);
	}
	$obj = json_decode($res, true);

	curl_close($ch);

	return $obj;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function coinsmarkets_update_market($market)
{
	$exchange = 'coinsmarkets';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = 'BTC_'.strtoupper($symbol);
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = 'BTC_'.strtoupper($symbol);
		if (!empty($market->base_coin)) $pair = $market->base_coin.'_'.strtoupper($symbol);
	}

	$t1 = microtime(true);
	$m = coinsmarkets_api_query('apicoin');
	if(!is_array($m) || empty($m)) return false;
	foreach ($m as $p=>$data) {
		if ($p == $pair) {
			$ticker = $data;
			break;
		}
	}
	if(!isset($ticker)) return false;

	$price2 = ((double)$ticker['highestBid'] + (double)$ticker['lowestAsk'])/2;
	$market->price = AverageIncrement($market->price, $ticker['highestBid']);
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
