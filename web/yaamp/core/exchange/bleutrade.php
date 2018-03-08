<?php

// close to bittrex api

function bleutrade_api_query($method, $params='')
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_BLEUTRADE_SECRET')) define('EXCH_BLEUTRADE_SECRET', '');

	// optional secret key
	if (empty(EXCH_BLEUTRADE_SECRET) && strpos($method, 'public') === FALSE) return false;
	if (empty(EXCH_BLEUTRADE_KEY) && strpos($method, 'public') === FALSE) return false;

	$apikey = EXCH_BLEUTRADE_KEY; // your API-key
	$apisecret = EXCH_BLEUTRADE_SECRET; // your Secret-key

	$nonce = time();
	//$mt = explode(' ', microtime());
	//$nonce = $mt[1].substr($mt[0], 2, 6);

	$uri = "https://bleutrade.com/api/v2/$method?apikey=$apikey&nonce=$nonce$params";

	$sign = hash_hmac('sha512', $uri, $apisecret);
	$ch = curl_init($uri);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("apisign:$sign"));
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSLVERSION, 1 /*CURL_SSLVERSION_TLSv1*/);
	curl_setopt($ch, CURLOPT_SSL_SESSIONID_CACHE, 0);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Bleutrade API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$data = curl_exec($ch);
	$obj = json_decode($data);

	if(!is_object($obj)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("bleutrade: $method failed ($status) ".strip_data($data).' '.curl_error($ch));
	}

	curl_close($ch);

	return $obj;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function bleutrade_update_market($market)
{
	$exchange = 'bleutrade';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = $symbol."_BTC";
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = $symbol."_BTC";
		if (!empty($market->base_coin)) $pair = $symbol.'_'.$market->base_coin;
	}

	$t1 = microtime(true);
	$m = bleutrade_api_query('public/getticker', '&market='.$pair);
	if(!is_object($m) || !$m->success || empty($m->result)) return false;
	$ticker = $m->result[0];

	$price2 = ($ticker->Bid+$ticker->Ask)/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $ticker->Bid);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
