<?php

// same as bittrex

function bleutrade_api_query($method, $params='')
{
	$apikey = EXCH_BLUETRADE_KEY; // your API-key
	$apisecret = EXCH_BLUETRADE_SECRET; // your Secret-key

	$nonce = time();
	$uri = "https://bleutrade.com/api/v2/$method?apikey=$apikey&nonce=$nonce$params";
//	debuglog($uri);

	$sign = hash_hmac('sha512', $uri, $apisecret);
	$ch = curl_init($uri);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("apisign:$sign"));

	$execResult = curl_exec($ch);
	$obj = json_decode($execResult);

	return $obj;
}




