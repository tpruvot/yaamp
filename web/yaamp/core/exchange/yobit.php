<?php

function yobit_api_query($method)
{
	$uri = "https://yobit.net/api/3/$method";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$execResult = curl_exec($ch);
	$obj = json_decode($execResult);

	return $obj;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// function getnoonce()
// {
// 	$filename = 'yobit_nonce.dat';
// 	$n = 100;

// 	if(file_exists($filename))
// 		$n = intval(trim(file_get_contents($filename))) + 1;

// 	file_put_contents($filename, $n);
// 	return $n;
// }

function yobit_api_query2($method, $req = array())
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_YOBIT_SECRET')) define('EXCH_YOBIT_SECRET', '');

	if (empty(EXCH_YOBIT_SECRET)) return FALSE;

	$api_key    = EXCH_YOBIT_KEY;
	$api_secret = EXCH_YOBIT_SECRET;

	$req['method'] = $method;
	$req['nonce'] = time(); //sleep(1);

	$post_data = http_build_query($req, '', '&');
	$sign = hash_hmac("sha512", $post_data, $api_secret);

	$headers = array(
		'Sign: '.$sign,
		'Key: '.$api_key,
	);

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; SMART_API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_URL, 'https://yobit.net/tapi/');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_ENCODING , 'gzip');

	$res = curl_exec($ch);
	if($res === false)
	{
		$e = curl_error($ch);
		debuglog($e);
		curl_close($ch);
		return null;
	}

	$result = json_decode($res, true);
	if(!$result) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("yobit: $method failed ($status) ".strip_data($res));
	}

	curl_close($ch);

	return $result;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function yobit_update_market($market)
{
	$exchange = 'yobit';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = strtolower($symbol).'_btc';
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = strtolower($symbol).'_btc';
		if (!empty($market->base_coin)) $pair = $symbol.'_'.$market->base_coin;
	}

	$t1 = microtime(true);
	$ticker = yobit_api_query("ticker/$pair");
	if(!$ticker || objSafeVal($ticker,$pair) === NULL) return false;
	if(objSafeVal($ticker->$pair,'buy') === NULL) {
		debuglog("$exchange: invalid data received for $pair ticker");
		return false;
	}

	$price2 = ($ticker->$pair->buy + $ticker->$pair->sell) / 2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $ticker->$pair->buy);
	if ($ticker->$pair->buy < $market->price) $market->price = $ticker->$pair->buy;
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
