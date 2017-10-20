<?php

// markets

function nova_api_query($method)
{
	$uri = "https://novaexchange.com/remote/v2/$method/";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$res= curl_exec($ch);
	$obj = json_decode($res);

	return $obj;
}

function nova_api_user($method, $params=array())
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_NOVA_SECRET')) define('EXCH_NOVA_SECRET', '');

	if (empty(EXCH_NOVA_KEY) || empty(EXCH_NOVA_SECRET)) return false;

	$uri = "https://novaexchange.com/remote/v2/private/$method/?nonce=".time();

	$headers = array(
		'Content-Type: application/x-www-form-urlencoded',
	);

	$params['apikey'] = EXCH_NOVA_KEY;
	$params['signature'] = base64_encode(hash_hmac('sha512', $uri, EXCH_NOVA_SECRET, true));

	$postdata = http_build_query($params, '', '&');
	// Clear the params array so that we don't accidentaly leak our keys
	$params = array();

	$ch = curl_init($uri);
	//curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	//curl_setopt($ch, CURLOPT_SSLVERSION, 1 /*CURL_SSLVERSION_TLSv1*/);
	curl_setopt($ch, CURLOPT_SSL_SESSIONID_CACHE, 0);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Nova API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$data = curl_exec($ch);
	$res = json_decode($data);

	if(!is_object($res) || $res->status == 'error') {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("nova: $method failed ($status) ".strip_data($data).' '.curl_error($ch));
	}

	curl_close($ch);

	return $res;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function nova_update_market($market)
{
	$exchange = 'nova';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = 'BTC_'.strtoupper($symbol);
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = 'BTC_'.strtoupper($symbol);
		if (!empty($market->base_coin)) $pair = $market->base_coin.'_'.strtoupper($symbol);
	}

	$t1 = microtime(true);
	$m = nova_api_query('market/info/'.$pair);
	if(!is_object($m) || $m->status != 'success' || empty($m->markets)) return false;
	$ticker = $m->markets[0];

	$price2 = ($ticker->bid+$ticker->ask)/2;
	$market->price = AverageIncrement($market->price, $ticker->bid);
	$market->price2 = AverageIncrement($market->price2, $ticker->last_price);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
