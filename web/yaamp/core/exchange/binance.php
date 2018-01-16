<?php

// markets /api/v1/ticker/allBookTickers

function binance_api_query($method)
{
	$exchange = 'binance';
	$uri = "https://www.binance.com/api/v1/$method";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Binance API PHP client; '.php_uname('s').'; PHP/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.')');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$res = curl_exec($ch);
	$obj = json_decode($res);
	if(!is_object($obj) && !is_array($obj)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("$exchange: $method failed ($status) ".strip_data($res));
	}

	return $obj;
}

// https://api.binance.com/api/v3/account

function binance_api_user($method, $params=NULL)
{
	$exchange = 'binance';
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_BINANCE_SECRET')) define('EXCH_BINANCE_SECRET', '');

	if (empty(EXCH_BINANCE_KEY) || empty(EXCH_BINANCE_SECRET)) return false;

	$mt = explode(' ', microtime());
	$nonce = $mt[1].substr($mt[0], 2, 3);
	$url = "https://api.binance.com/api/v3/$method";

	if (empty($params)) $params = array();
	$params['timestamp'] = $nonce;
	$query = http_build_query($params, '', '&');

	$hmac = strtolower(hash_hmac('sha256', $query, EXCH_BINANCE_SECRET));
	$isPostMethod = ($method == 'order');
	if ($isPostMethod)
		$query .= '&signature='.$hmac;
	else
		$url .= '?'.$query.'&signature='.$hmac;

	$headers = array(
		'Content-Type: application/json;charset=UTF-8',
		'X-MBX-APIKEY: '.EXCH_BINANCE_KEY,
	);

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	if ($isPostMethod) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Binance API PHP client; '.php_uname('s').'; PHP/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.')');
	curl_setopt($ch, CURLOPT_ENCODING , '');
	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	//curl_setopt($ch, CURLOPT_VERBOSE, 1);

	$res = curl_exec($ch);
	if($res === false) {
		$e = curl_error($ch);
		debuglog("$exchange: $method $e");
		curl_close($ch);
		return false;
	}

	$result = json_decode($res);
	if(!is_object($result) && !is_array($result)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("$exchange: $method failed ($status) ".strip_data($res));
	}

	curl_close($ch);

	return $result;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function binance_update_market($market)
{
	$exchange = 'binance';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = strtoupper($symbol).'BTC';
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = strtoupper($symbol).'BTC';
		if (!empty($market->base_coin)) $pair = strtoupper($symbol).$market->base_coin;
	}

	$t1 = microtime(true);
	$tickers = binance_api_query('ticker/allBookTickers');
	if(empty($tickers) || !is_array($tickers)) return false;
	foreach ($tickers as $t) {
		if ($t->symbol == $pair) $ticker = $t;
	}
	if (!isset($ticker)) return false;

	$price2 = ($ticker->bidPrice+$ticker->askPrice)/2;
	$market->price = AverageIncrement($market->price, $ticker->bidPrice);
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
