<?php

// https://docs.bitfinex.com/docs/rest-general
// https://docs.bitfinex.com/v1/reference#rest-public-ticker
// https://api.bitfinex.com/v1/symbols
// not used yet : https://api.bitfinex.com/v1/symbols_details
// https://api.bitfinex.com/v1/pubticker/btcusd

function bitfinex_api_query($method, $params='', $returnType='object')
{
	$uri = "https://api.bitfinex.com/v1/{$method}";
	if (!empty($params)) $uri .= "/{$params}";
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
