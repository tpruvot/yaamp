<?php

function bittrex_api_query($method, $params='')
{
	$apikey = EXCH_BITTREX_KEY; // your API-key
	$apisecret = EXCH_BITTREX_SECRET; // your Secret-key

	$nonce = time();
	$uri = "https://bittrex.com/api/v1.1/$method?apikey=$apikey&nonce=$nonce$params";

	$sign = hash_hmac('sha512', $uri, $apisecret);
	$ch = curl_init($uri);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("apisign:$sign"));

	$execResult = curl_exec($ch);
	$obj = json_decode($execResult);

	return $obj;
}







