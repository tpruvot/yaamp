<?php

// https://alcurex.org/index.php/crypto/api_documentation

function alcurex_api_query($method, $params='')
{
	$uri = "https://alcurex.org/api/{$method}.php{$params}";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$execResult = strip_tags(curl_exec($ch));
	$obj = json_decode($execResult);

	return $obj;
}
