<?php
//https://btc-alpha.com/api/v1/ticker/?format=json
function btcalpha_api_query($method, $params='')
{
	$uri = "https://btc-alpha.com/api/v1/{$method}/?format=json";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$execResult = strip_tags(curl_exec($ch));
	$obj = json_decode($execResult);

	return $obj;
}

