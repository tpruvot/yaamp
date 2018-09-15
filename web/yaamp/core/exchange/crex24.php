<?php

// https://docs.crex24.com/trade-api/v2/
// https://api.crex24.com/v2/public/currencies

function crex24_api_query($method, $params='', $returnType='object')
{
	$uri = "https://api.crex24.com/v2/public/{$method}";
	if (!empty($params)) $uri .= "?{$params}";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	$execResult = strip_tags(curl_exec($ch));

	if ($returnType == 'object')
		$ret = json_decode($execResult);
	else
		$ret = json_decode($execResult,true);

	return $ret;
}

function crex24_api_user($method, $url_params=array(), $json_body='')
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_CREX24_SECRET')) define('EXCH_CREX24_SECRET', '');

	if (empty(EXCH_CREX24_KEY) || empty(EXCH_CREX24_SECRET)) return false;

	$base = 'https://api.crex24.com';
	$path = '/v2/'.$method;

	if (!empty($url_params)) {
		if (is_array($url_params)) {
			$path .= '?'.http_build_query($url_params, '', '&');
		} elseif (is_string($url_params)) {
			$path .= '?'.$url_params;
		}
	}

	$mt = explode(' ', microtime());
	$nonce = $mt[1].substr($mt[0], 2, 6);
	$sign = base64_encode(hash_hmac('sha512', $path.$nonce.$json_body, base64_decode(EXCH_CREX24_SECRET), true));

	$headers = array(
		'X-CREX24-API-KEY: '.EXCH_CREX24_KEY,
		'X-CREX24-API-NONCE: '.$nonce,
		'X-CREX24-API-SIGN: '.$sign,
	);

	$uri = $base.$path;

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	if (!empty($json_body)) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json_body);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	//curl_setopt($ch, CURLOPT_SSLVERSION, 1 /*CURL_SSLVERSION_TLSv1*/);
	curl_setopt($ch, CURLOPT_SSL_SESSIONID_CACHE, 0);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; crex24 API client; '.php_uname('s').'; PHP/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$data = curl_exec($ch);
	$res = json_decode($data);
	unset($headers);

	if(empty($res)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("crex24: $method failed ($status) ".strip_data($data).' '.curl_error($ch));
	}

	curl_close($ch);

	return $res;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
