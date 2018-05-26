<?php

// http://data.bter.com/api/1/marketlist

function bter_api_query($method, $params='')
{
	$uri = 'http://data.bter.com/api/1/'.$method;
	if (!empty($params))
		$uri .= '/'.$params;

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);

	$data = strip_tags(curl_exec($ch));
	if ($method == 'tickers' && empty($params)) {
		$obj = json_decode($data, true);
	}
	else $obj = json_decode($data);

	return $obj;
}

// https://bter.com/api/1/private/...

function bter_api_user($method, $params=array())
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_BTER_SECRET')) return false;

	$apikey = EXCH_BTER_KEY; // your API-key
	$apisecret = EXCH_BTER_SECRET; // your Secret-key

	$req = $params;

	// generate a nonce to avoid problems with 32bits systems
	$mt = explode(' ', microtime());
	$req['nonce'] = $mt[1].substr($mt[0], 2, 6);

	$post_data = http_build_query($req, '', '&');
	$sign = hash_hmac('sha512', $post_data, $apisecret);

	$uri = "https://bter.com/api/1/private/$method";
	$headers = array(
		'KEY: '.$apikey,
		'SIGN: '.$sign,
	);

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Bter API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$data = curl_exec($ch);
	$obj = json_decode($data, true);

	if(!is_array($obj)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("bter: $method failed ($status) ".strip_data($data).' '.curl_error($ch));
	}

	curl_close($ch);

	return $obj;
}
