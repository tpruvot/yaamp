<?php

// https://cryptohub.online/api/market/ticker/

function cryptohub_api_query($method, $params='')
{
	$uri = "https://cryptohub.online/api/{$method}/";
	if (!empty($params)) $uri .= "{$params}/";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$execResult = strip_tags(curl_exec($ch));

	// array required for ticker "foreach"
	$array = json_decode($execResult, true);

	return $array;
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function cryptohub_update_market($market)
{
	$exchange = 'cryptohub';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = $symbol;
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = $symbol;
		if (!empty($market->base_coin)) $pair = $market->base_coin.'_'.$symbol;
	}

	$t1 = microtime(true);
	$ticker = cryptohub_api_query("market/ticker",$pair);
	if(!$ticker || empty($ticker)) return false;
	$ticker = array_pop($ticker);
	if(arraySafeVal($ticker,'highestBid') === NULL) {
		debuglog("$exchange: invalid data received for $pair ticker");
		return false;
	}

	$price2 = ((double) $ticker['highestBid'] + $ticker['lowestAsk']) / 2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, (double) $ticker['highestBid']);
	if ($ticker['lowestAsk'] < $market->price) $market->price = $ticker['lowestAsk'];
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
