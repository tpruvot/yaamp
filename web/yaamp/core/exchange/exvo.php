<?php
// https://exvo.io/api/v2/tickers.json
function exvo_api_query($method, $params='')
{
	$uri = "https://exvo.io/api/v2/{$method}";
	if (!empty($params)) $uri .= "/{$params}";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$execResult = strip_tags(curl_exec($ch));

	// array required for ticker "foreach"
	$array = json_decode($execResult, true);

	return $array;
}