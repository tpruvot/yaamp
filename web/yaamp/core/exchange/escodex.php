<?php
// http://labs.escodex.com/api/ticker
function escodex_api_query($method, $params='')
{
	$uri = "http://labs.escodex.com/api/{$method}";
	if (!empty($params)) $uri .= "/{$params}";
	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	$execResult = strip_tags(curl_exec($ch));
	$obj = json_decode($execResult);
	return $obj;
}
