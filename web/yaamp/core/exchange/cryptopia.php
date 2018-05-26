<?php

// https://www.cryptopia.co.nz/api/GetMarkets/24
// https://www.cryptopia.co.nz/api/GetCurrencies

function cryptopia_api_query($method, $params='')
{
	$uri = "https://www.cryptopia.co.nz/api/$method";
	if (!empty($params))
		$uri .= "/$params";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$res = curl_exec($ch);
	$result = json_decode($res);
	if(!is_object($result) && !is_array($result)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (strpos($res,'Maintenance'))
			debuglog("cryptopia: $method failed (Maintenance)");
		else
			debuglog("cryptopia: $method failed ($status) ".strip_data($res));
	}

	curl_close($ch);
	return $result;
}

// https://www.cryptopia.co.nz/api/GetBalance

function cryptopia_api_user($method, $params=NULL)
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_CRYPTOPIA_SECRET')) define('EXCH_CRYPTOPIA_SECRET', '');

	if (empty(EXCH_CRYPTOPIA_KEY) || empty(EXCH_CRYPTOPIA_SECRET)) return false;

	$apikey = EXCH_CRYPTOPIA_KEY;

	$mt = explode(' ', microtime());
	$nonce = $mt[1].substr($mt[0], 2, 6);
	$url = "https://www.cryptopia.co.nz/Api/$method";

	if (empty($params)) $params = new stdclass;
	$post_data = json_encode($params);
	$hashpost = base64_encode(md5($post_data, true));
	$url_encoded = strtolower(urlencode($url));
	$sig = "{$apikey}POST{$url_encoded}{$nonce}{$hashpost}";
	$hmac = base64_encode(hash_hmac('sha256', $sig, base64_decode(EXCH_CRYPTOPIA_SECRET), true));

	$headers = array(
		'Content-Type: application/json; charset=utf-8',
		'Authorization: amx '.$apikey.':'.$hmac.':'.$nonce,
	);

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Cryptopia API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$res = curl_exec($ch);
	if($res === false)
	{
		$e = curl_error($ch);
		debuglog("cryptopia: $e");
		curl_close($ch);
		return false;
	}

	$p = strrpos($res,']}');
	if($p > 0 && strrpos($res,'500 Error')) {
		$res = substr($res, 0, $p+2);
		debuglog("cryptopia: $method stripped to fix json");
	}

	$result = json_decode($res);
	if(!is_object($result) && !is_array($result)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (strpos($res,'Maintenance'))
			debuglog("cryptopia: $method failed (Maintenance)");
		else
			debuglog("cryptopia: $method failed ($status) ".strip_data($res));
	}

	curl_close($ch);

	return $result;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function cryptopia_update_market($market)
{
	$exchange = 'cryptopia';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = $symbol.'_BTC';
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = $symbol.'_BTC';
		if (!empty($market->base_coin)) $pair = $symbol.'_'.$market->base_coin;
	}

	$t1 = microtime(true);
	$m = cryptopia_api_query('GetMarket', $pair);
	if(!is_object($m) || !$m->Success || !is_object($m->Data)) return false;
	$ticker = $m->Data;

	$price2 = ($ticker->BidPrice+$ticker->AskPrice)/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $ticker->BidPrice*0.98);
	$market->marketid = $ticker->TradePairId;
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
