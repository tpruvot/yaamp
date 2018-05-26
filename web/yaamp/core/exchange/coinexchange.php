<?php

// http://coinexchangeio.github.io/slate/

function coinexchange_api_query($method, $params='')
{
	$exchange = 'coinexchange';

	$mt = explode(' ', microtime());
	$nonce = $mt[1].substr($mt[0], 2, 6);

	$uri = "https://www.coinexchange.io/api/v1/$method?nonce=$nonce";
	if (!empty($params)) $uri .= '&'.$params;

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSLVERSION, 1 /*CURL_SSLVERSION_TLSv1*/);
	curl_setopt($ch, CURLOPT_SSL_SESSIONID_CACHE, 0);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; CoinExchange API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$data = curl_exec($ch);
	$obj = json_decode($data);

	if(!is_object($obj)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("$exchange: $method failed ($status) ".strip_data($data).' '.curl_error($ch));
	}

	curl_close($ch);

	return $obj;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function coinexchange_update_market($market)
{
	$exchange = 'coinexchange';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
	}

	if (empty($market->marketid)) {
		user()->setFlash('error', "$exchange $symbol marketid not set!");
		return false;
	}

	$t1 = microtime(true);
	$m = coinexchange_api_query('getmarketsummary', 'market_id='.$market->marketid);
	if(!is_object($m) || !$m->success || empty($m->result)) return false;
	$ticker = $m->result;

	$price2 = ((double) $ticker->BidPrice + (double) $ticker->AskPrice)/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, (double) $ticker->BidPrice);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms {$market->price}");

	return true;
}
