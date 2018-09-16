<?php

// https://open.big.one/docs/api_tickers.html
// https://big.one/api/v2/tickers

function bigone_api_query($method, $params='', $returnType='object')
{
	$uri = "https://big.one/api/v2/{$method}";
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
 	return $ret->data;
}
