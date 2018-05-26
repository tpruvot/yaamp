<?php

// https://www.bitstamp.net/api/

function bitstamp_api_query($method, $params='')
{
	$url = "https://www.bitstamp.net/api/v2/$method/";
	if (!empty($params)) $url .= "$params/";

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$execResult = curl_exec($ch);
	$res = json_decode($execResult, true);

	return $res;
}

function bitstamp_api_user($method, $params='')
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_BITSTAMP_SECRET')) return false;

	$cid = EXCH_BITSTAMP_ID; // your Customer ID
	$apikey = EXCH_BITSTAMP_KEY; // your API-key
	$apisecret = EXCH_BITSTAMP_SECRET; // your Secret-key

	if (empty($cid) || empty($apikey) || empty($apisecret)) return false;

	$mt = explode(' ', microtime());
	$nonce = $mt[1].substr($mt[0], 2, 6);

	$msg = "{$nonce}{$cid}{$apikey}";
	$sha = hash_hmac('sha256', $msg, $apisecret);
	$sign = strtoupper($sha);

	$url = "https://www.bitstamp.net/api/$method/";
	if (!empty($params)) $url .= "$params/";

	$postdata = array(
		'key' => $apikey,
		'signature' => $sign,
		'nonce' => $nonce
	);
	$post_data = http_build_query($postdata, '', '&');

	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	//curl_setopt($ch, CURLOPT_SSLVERSION, 1 /*CURL_SSLVERSION_TLSv1*/);
	curl_setopt($ch, CURLOPT_SSL_SESSIONID_CACHE, 0);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Bitstamp API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$execResult = curl_exec($ch);
	$res = json_decode($execResult, true);

	return $res;
}

// https://www.bitstamp.net/api/v2/ticker/btceur/

function bitstamp_btceur()
{
	$ticker = bitstamp_api_query('ticker', 'btceur');
	return is_array($ticker) ? floatval($ticker["last"]) : false;
}

function bitstamp_btcusd()
{
	$ticker = bitstamp_api_query('ticker', 'btcusd');
	return is_array($ticker) ? floatval($ticker["last"]) : false;
}

function getBitstampBalances()
{
	$exchange = 'bitstamp';
	if (exchange_get($exchange, 'disabled')) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");
	if (is_object($savebalance)) {
		$balances = bitstamp_api_user('balance');
		if (is_array($balances)) {
			$savebalance->balance = arraySafeVal($balances, 'btc_balance',0.) - arraySafeVal($balances, 'btc_reserved');
			$savebalance->onsell = arraySafeVal($balances, 'btc_reserved');
			$savebalance->save();
		}
	}
}
