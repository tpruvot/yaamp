<?php

// https://cryptowat.ch/docs/api
// Each client has an allowance of 2000000000 nanoseconds (2 seconds) of CPU time per hour.
// The allowance is reset every hour on the hour.

/* sample output of https://api.cryptowat.ch/markets/prices
{
	"result": {
		"bitfinex:btcusd": 4050.6,
		"bitstamp:btcusd": 4046.92,
		"bitstamp:eurusd": 1.17193,
		"okcoin:btcusd": 4287.28,
		"kraken:btcusd": 4060,
		"kraken:dashbtc": 0.048353,
		"kraken:etceur": 11.50205,
		"kraken:etcusd": 13.55,
		"poloniex:btcusd": 4040,
		"poloniex:dashbtc": 0.04799951,
		"poloniex:xmrbtc": 0.01164362
	},
	"allowance": {
		"cost": 707330,
		"remaining": 3929492542
	}
}

// https://api.cryptowat.ch/markets/summaries
*/
function cryptowatch_api_query($method, $params='')
{
	$uri = "https://api.cryptowat.ch/{$method}";
	if (!empty($params)) $uri .= "{$params}";

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

	$execResult = strip_tags(curl_exec($ch));
	$arr = json_decode($execResult, true);

	return $arr;
}
