<?php
// see https://hitbtc.com/api
// https://hitbtc.com/api/1/public/ticker

function hitbtc_api_query($method, $params='', $returnType='object')
{
	$url = "https://api.hitbtc.com/api/1/public/$method";
	if (!empty($params))
		$url .= "?$params";

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; HitBTC API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$execResult = curl_exec($ch);
	if ($returnType == 'object')
		$ret = json_decode($execResult);
	else
		$ret = json_decode($execResult,true);

	return $ret;
}

// https://hitbtc.com/api#tradingbalance

function hitbtc_api_user($method, $params=NULL)
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_HITBTC_SECRET')) define('EXCH_HITBTC_SECRET', '');

	if (empty(EXCH_HITBTC_KEY) || empty(EXCH_HITBTC_SECRET)) return false;

	$isPostMethod = ($method == 'trading/new_order' || $method == 'trading/cancel_order');

	//$api_host = 'https://demo-api.hitbtc.com';
	$api_host = 'https://api.hitbtc.com';
	$mt = explode(' ', microtime());
	$nonce = $mt[1].substr($mt[0], 2, 3);
	//$nonce = time()*1E3;
	$url = "/api/1/$method?nonce=$nonce&apikey=".EXCH_HITBTC_KEY;

	if (empty($params)) $params = array();
	$query = http_build_query($params);
	if (strlen($query) && !$isPostMethod) {
		$url .= '&'.$query; $query = '';
	}
	$hmac = strtolower(hash_hmac('sha512', $url . $query, EXCH_HITBTC_SECRET));

	$headers = array(
		'Content-Type: application/json; charset=utf-8',
		'X-Signature: '.$hmac,
	);

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $api_host.$url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	if ($isPostMethod) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; HitBTC API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');
	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	//curl_setopt($ch, CURLOPT_VERBOSE, 1);

	$res = curl_exec($ch);
	if($res === false) {
		$e = curl_error($ch);
		debuglog("hitbtc: $e");
		curl_close($ch);
		return false;
	}

	$result = json_decode($res);
	if(!is_object($result) && !is_array($result)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("hitbtc: $method failed ($status) ".strip_data($res));
	}

	curl_close($ch);

	return $result;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function hitbtc_update_market($market)
{
	$exchange = 'hitbtc';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = 'BTC'.$symbol;
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = 'BTC'.$symbol;
		if (!empty($market->base_coin)) $pair = $market->base_coin.$symbol;
	}

	$t1 = microtime(true);
	$ticker = hitbtc_api_query($pair.'/ticker');
	if(!is_object($ticker) || !isset($ticker->ask)) return false;

	$price2 = ((double) $ticker->bid + (double)$ticker->ask)/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $ticker->bid);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
