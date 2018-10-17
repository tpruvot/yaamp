<?php

// https://api.coinbene.com/v1/market/symbol
// https://api.coinbene.com/v1/market/ticker?symbol=all

function coinbene_api_query($method, $params='')
{
	$uri = "https://api.coinbene.com/v1/{$method}";
	if (!empty($params)) $uri .= "?{$params}";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	$execResult = strip_tags(curl_exec($ch));
	$obj = json_decode($execResult);
	return $obj;
}

