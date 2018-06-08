<?php

// https://tradesatoshi.com/api/public/getcurrencies
// https://tradesatoshi.com/api/public/getmarketsummaries

function tradesatoshi_api_query($method, $params='')
{
	$uri = "https://tradesatoshi.com/api/public/$method";
	if (!empty($params))
		$uri .= "$params";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$res = curl_exec($ch);
	$result = json_decode($res);
	if(!is_object($result) && !is_array($result)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("tradesatoshi: $method failed ($status) ".strip_data($res));
	}

	curl_close($ch);
	return $result;
}

// https://tradesatoshi.com/api/private/getbalance

function tradesatoshi_api_user($method, $params=NULL)
{
	require_once('/etc/yiimp/keys.php');
	// todo
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// https://tradesatoshi.com/api/public/getmarketsummary?market=LTC_BTC

// manual update of one market
function tradesatoshi_update_market($market)
{
	$exchange = 'tradesatoshi';
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
	$m = tradesatoshi_api_query('getmarketsummary', '?market='.$pair);
	if(!is_object($m) || !$m->success || !is_object($m->result)) return false;
	$ticker = $m->result;

	$price2 = ($ticker->bid+$ticker->ask)/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $ticker->bid*0.98);
	$market->marketid = $ticker->TradePairId;
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
