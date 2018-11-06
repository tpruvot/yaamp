<?php
	//https://api.bibox.com/v1/mdata?cmd=marketAll
function bibox_api_query($method)
{
	$uri = "https://api.bibox.com/v1/mdata?cmd={$method}";

    $ch = curl_init($uri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $execResult = strip_tags(curl_exec($ch));
    $ret = json_decode($execResult,true);

    return $ret;
}