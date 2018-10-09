<?php

// markets https://app.stex.com/api2/markets
// prices https://app.stex.com/api2/ticker

function stocksexchange_api_query($method)
{
	$uri = "https://app.stex.com/api2/$method";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$res = curl_exec($ch);
	$obj = json_decode($res);

	return $obj;
}

function stocksexchange_api_user($method, $params=array())
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_STOCKSEXCHANGE_SECRET')) define('EXCH_STOCKSEXCHANGE_SECRET', '');

	if (empty(EXCH_STOCKSEXCHANGE_KEY) || empty(EXCH_STOCKSEXCHANGE_SECRET)) return false;

	$exchange = 'stocksexchange';
	$mt = explode(' ', microtime());
	$nonce = $mt[1].substr($mt[0], 2, 6);
	$url = "https://app.stex.com/api2?method=$method&nonce=$nonce";

	$sign_data = json_encode($params);
	$sign = hash_hmac('sha512', $sign_data, EXCH_STOCKSEXCHANGE_SECRET);

	$headers = array(
		'Content-Type: application/json; charset=utf-8',
		'Key: '.EXCH_STOCKSEXCHANGE_KEY,
		'Sign: '.$sign,
	);

	$ch = curl_init();

	//curl_setopt($ch, CURLOPT_VERBOSE, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $sign_data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Stocks.exchange API PHP client; '.php_uname('s').'; PHP/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$res = curl_exec($ch);
	if($res === false) {
		$e = curl_error($ch);
		debuglog("$exchange: $e");
		curl_close($ch);
		return false;
	}

	$result = json_decode($res, true);
	if(!is_object($result) && !is_array($result)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (strpos($res,'Maintenance'))
			debuglog("$exchange: $method failed (Maintenance)");
		else
			debuglog("$exchange: $method failed ($status) ".strip_data($res));
	}

	curl_close($ch);

	return $result;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function stocksexchange_update_market($market)
{
	$exchange = 'stocksexchange';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = strtoupper($symbol).'_BTC';
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = strtoupper($symbol).'_BTC';
		if (!empty($market->base_coin)) $pair = strtoupper($symbol).'_'.$market->base_coin;
	}

	$t1 = microtime(true);
	$tickers = stocksexchange_api_query('ticker');
	if(empty($tickers) || !is_array($tickers)) return false;
	foreach ($tickers as $t) {
		if ($t->market_name == $pair) $ticker = $t;
	}
	if (!isset($ticker)) return false;

	$price2 = ($ticker->bid+$ticker->ask)/2;
	$market->price = AverageIncrement($market->price, $ticker->bid);
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
