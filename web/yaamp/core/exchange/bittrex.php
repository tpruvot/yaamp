<?php

function bittrex_api_query($method, $params='')
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_BITTREX_SECRET')) define('EXCH_BITTREX_SECRET', '');

	// optional secret key
	if (empty(EXCH_BITTREX_SECRET) && strpos($method, 'public') === FALSE) return FALSE;
	if (empty(EXCH_BITTREX_KEY) && strpos($method, 'public') === FALSE) return FALSE;

	$apikey = EXCH_BITTREX_KEY; // your API-key
	$apisecret = EXCH_BITTREX_SECRET; // your Secret-key

	$nonce = time();
	$uri = "https://bittrex.com/api/v1.1/$method?apikey=$apikey&nonce=$nonce$params";

	$sign = hash_hmac('sha512', $uri, $apisecret);
	$ch = curl_init($uri);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("apisign:$sign"));

	$execResult = curl_exec($ch);
	$obj = json_decode($execResult);

	return $obj;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function bittrex_update_market($market)
{
	$exchange = 'bittrex';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = "BTC-$symbol";
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = "BTC-$symbol";
		if (!empty($market->base_coin)) $pair = $market->base_coin."-$symbol";
	}

	$t1 = microtime(true);
	$m = bittrex_api_query('public/getticker', '&market='.$pair);
	if(!is_object($m) || !$m->success || !is_object($m->result)) return false;
	$m = $m->result;

	$price2 = ($m->Bid + $m->Ask)/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $m->Bid);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
