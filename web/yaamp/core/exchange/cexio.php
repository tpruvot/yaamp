<?php

// cex.io api queries - tpruvot 2018

// see https://cex.io/rest-api for the methods

function cexio_api_query($method, $params='')
{
	$url = "https://cex.io/api/$method/";
	if (!empty($params)) $url .= "$params";

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'PHP API');
	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	$execResult = curl_exec($ch);
	$res = json_decode($execResult, true);

	return $res;
}

function cexio_api_user($method, $params=array())
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_CEXIO_KEY')) return false;
	if (!defined('EXCH_CEXIO_SECRET')) return false;
	if (!defined('EXCH_CEXIO_ID')) return false;

	$username = EXCH_CEXIO_ID;
	$apikey = EXCH_CEXIO_KEY; // your API-key
	$apisecret = EXCH_CEXIO_SECRET; // your Secret-key

	if (empty($username) || empty($apikey) || empty($apisecret)) return false;

	$mt = explode(' ', microtime());
	$nonce = $mt[1].substr($mt[0], 2, 6);
	$nonce = $mt[1];

	$msg = "{$nonce}{$username}{$apikey}";
	$sha = hash_hmac('sha256', $msg, $apisecret);
	$sign = strtoupper($sha);

	$url = "https://cex.io/api/$method/";

	$postdata = array(
		'key' => $apikey,
		'signature' => $sign,
		'nonce' => $nonce
	);

	if (!empty($params)) {
		foreach($params as $k=>$v) $postdata[$k] = $v;
	}

	$post_data = http_build_query($postdata, '', '&');

	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	//curl_setopt($ch, CURLOPT_SSLVERSION, 1 /*CURL_SSLVERSION_TLSv1*/);
	curl_setopt($ch, CURLOPT_SSL_SESSIONID_CACHE, 0);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; cex.io API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$execResult = curl_exec($ch);
	$res = json_decode($execResult, true);

	return $res;
}

// https://cex.io/rest-api#ticker

function cexio_btceur()
{
	$ticker = cexio_api_query('ticker', 'BTC/EUR');
	return is_array($ticker) ? floatval($ticker["last"]) : false;
}

function cexio_btcusd()
{
	$ticker = cexio_api_query('ticker', 'BTC/USD');
	return is_array($ticker) ? floatval($ticker["last"]) : false;
}

// https://cex.io/rest-api#account-balance

function getCexIoBalances()
{
	$exchange = 'cexio';
	if (exchange_get($exchange, 'disabled')) return;

	$savebalance = getdbosql('db_balances', "name='$exchange'");
	if (is_object($savebalance)) {
		$balances = cexio_api_user('balance');
		if (is_array($balances)) {
			$b = arraySafeVal($balances, 'BTC');
			$savebalance->balance = arraySafeVal($b, 'available',0.);
			$savebalance->onsell = arraySafeVal($b, 'orders',0.);
			$savebalance->save();
		}
	}
}
