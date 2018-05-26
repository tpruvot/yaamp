<?php

function jubi_api_query($method, $params)
{
	$uri = "http://www.jubi.com/api/v1/$method$params";
//	debuglog("$uri");

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$execResult = curl_exec($ch);
	$obj = json_decode($execResult);

	return $obj;
}
