<?php

// https://api.empoex.com/marketinfo/[LOG-BTC]

function empoex_api_query($method)
{
	$uri = "https://api.empoex.com/$method";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$execResult = curl_exec($ch);
	$obj = json_decode($execResult);

	return $obj;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

function empoex_api_user($method, $params = "")
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_EMPOEX_SECKEY')) define('EXCH_EMPOEX_SECKEY', '');

	// optional secret key
	if (empty(EXCH_EMPOEX_SECKEY) && strpos($method, 'public') === FALSE) return FALSE;

	$api_key = EXCH_EMPOEX_SECKEY;

	$url = "https://api.empoex.com/$method/$api_key/$params";

	$ch = null;
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; SMART_API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_ENCODING , 'gzip');

	$res = curl_exec($ch);
	if($res === false)
	{
		$e = curl_error($ch);
		debuglog($e);
		curl_close($ch);
		return null;
	}

	curl_close($ch);

	$result = json_decode($res, true);
	if(!$result) debuglog(strip_tags($res));

	return $result;
}
