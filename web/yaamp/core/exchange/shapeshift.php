<?php

// https://info.shapeshift.io/api

// https://shapeshift.io/getcoins/

function shapeshift_api_query($method, $params='')
{
	$uri = "https://shapeshift.io/{$method}/{$params}";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

	$execResult = strip_tags(curl_exec($ch));
	$arr = json_decode($execResult, true);

	return $arr;
}

// https://shapeshift.io/shift
// example data: {"withdrawal":"AAAAAAAAAAAAA", "pair":"btc_ltc", returnAddress:"BBBBBBBBBBB"}

function shapeshift_api_post($method, $data=array())
{
	$uri = "https://shapeshift.io/{$method}";

	$post_data = json_encode($data);

	$headers = array(
		'Content-Type: application/json',
	);

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

	$execResult = strip_tags(curl_exec($ch));
	$arr = json_decode($execResult, true);

	return $arr;
}
