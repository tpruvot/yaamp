<?php
/**
 * Reference implementation for Kraken's REST API.
 *
 * See https://www.kraken.com/help/api for more info.
 *
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2013 Payward, Inc
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class KrakenAPIException extends ErrorException {};

class KrakenAPI
{
	protected $key;     // API key
	protected $secret;  // API secret
	protected $url;     // API base URL
	protected $version; // API version
	protected $curl;    // curl handle

	/**
	 * Constructor for KrakenAPI
	 *
	 * @param string $key API key
	 * @param string $secret API secret
	 * @param string $url base URL for Kraken API
	 * @param string $version API version
	 * @param bool $sslverify enable/disable SSL peer verification.  disable if using beta.api.kraken.com
	 */
	function __construct($key, $secret, $url='https://api.kraken.com', $version='0', $sslverify=true)
	{
		$this->key = $key;
		$this->secret = $secret;
		$this->url = $url;
		$this->version = $version;
		$this->curl = curl_init();

		curl_setopt_array($this->curl, array(
			CURLOPT_SSL_VERIFYPEER => $sslverify,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_USERAGENT => 'Kraken PHP API Agent',
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true)
		);
	}

	function __destruct()
	{
		curl_close($this->curl);
	}

	/**
	 * Query public methods
	 *
	 * @param string $method method name
	 * @param array $request request parameters
	 * @return array request result on success
	 * @throws KrakenAPIException
	 */
	function QueryPublic($method, array $request = array())
	{
		// build the POST data string
		$postdata = http_build_query($request, '', '&');

		// make request
		curl_setopt($this->curl, CURLOPT_URL, $this->url . '/' . $this->version . '/public/' . $method);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, array());
		curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 20);

		$result = curl_exec($this->curl);
		if($result===false)
			throw new KrakenAPIException('CURL error: ' . curl_error($this->curl));

		// decode results
		$result = json_decode($result, true);
		if(!is_array($result))
			throw new KrakenAPIException('JSON decode error');

		return $result;
	}

	/**
	 * Query private methods
	 *
	 * @param string $path method path
	 * @param array $request request parameters
	 * @return array request result on success
	 * @throws KrakenAPIException
	 */
	function QueryPrivate($method, array $request = array())
	{
		if(!isset($request['nonce'])) {
			// generate a 64 bit nonce using a timestamp at microsecond resolution
			// string functions are used to avoid problems on 32 bit systems
			$nonce = explode(' ', microtime());
			$request['nonce'] = $nonce[1] . str_pad(substr($nonce[0], 2, 6), 6, '0');
		}

		// build the POST data string
		$postdata = http_build_query($request, '', '&');

		// set API key and sign the message
		$path = '/' . $this->version . '/private/' . $method;
		$sign = hash_hmac('sha512', $path . hash('sha256', $request['nonce'] . $postdata, true), base64_decode($this->secret), true);
		$headers = array(
			'API-Key: ' . $this->key,
			'API-Sign: ' . base64_encode($sign)
		);

		// make request
		curl_setopt($this->curl, CURLOPT_URL, $this->url . $path);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 20);

		$result = curl_exec($this->curl);
		if($result===false)
			throw new KrakenAPIException('CURL error: ' . curl_error($this->curl));

		// decode results
		$result = json_decode($result, true);
		if(!is_array($result))
			throw new KrakenAPIException('JSON decode error');

		return $result;
	}
}

// convert ISO-4217-A3-X to generic crypto symbols
function kraken_ISOtoSymbol($iso, $default='')
{
	$conv = array(
		'XXBT'=>'BTC',
		'XXDG'=>'DOGE',
	);
	if (empty($default)) $default = substr($iso, 1);
	$symbol = arraySafeVal($conv, $iso, $default);
	return $symbol;
}

// convert yiimp symbols to ISO-4217-A3-X
function kraken_symbolToISO($symbol)
{
	$conv = array(
		'BTC' => 'XXBT',
		'DOGE' => 'XXDG',
	);
	$iso = arraySafeVal($conv, $symbol, 'X'.$symbol);
	return $iso;
}

function kraken_convertPair($symbol, $base='BTC')
{
	$btc_k = kraken_symbolToISO($base);
	$sym_k = kraken_symbolToISO($symbol);
	$pair  = $btc_k.$sym_k;
	return $pair;
}

// https://www.kraken.com/help/api

function kraken_api_query($method, $params='')
{
	$kraken = new KrakenAPI('', '');

	$arrParams = array();
	if (is_array($params)) $arrParams = $params;
	else
	switch ($method) {
		case 'Ticker':
			$pair = kraken_convertPair($params);
			$arrParams = array('pair'=>$pair);
			break;
	}

	try {
		$res = $kraken->QueryPublic($method, $arrParams);

	} catch (KrakenAPIException $e) {
		debuglog('kraken: QueryPublic() exception '.$e->getMessage());
		return false;
	}

	$proper = array();
	if (!empty($res) && isset($res['result']) && !empty($res['result'])) {
		switch ($method) {
		case 'Assets':
			foreach ($res['result'] as $symk => $asset) {
				$symbol = kraken_ISOtoSymbol($symk, $asset['altname']);
				$proper[$symbol] = $asset;
			}
			return $proper;
		case 'AssetPairs':
			foreach ($res['result'] as $pairk => $asset) {
				$symk = substr($pairk, 0, 4);
				$symbol1 = kraken_ISOtoSymbol($symk);
				$symk = substr($pairk, 4);
				$symbol2 = kraken_ISOtoSymbol($symk);
				$asset['base'] = kraken_ISOtoSymbol($asset['base']);
				$asset['quote'] = kraken_ISOtoSymbol($asset['quote']);
				$asset['fee_volume_currency'] = kraken_ISOtoSymbol($asset['fee_volume_currency']);
				$proper[$symbol1.'-'.$symbol2] = $asset;
			}
			return $proper;
		case 'Ticker':
			foreach ($res['result'] as $pairk => $asset) {
				$symk = substr($pairk, 0, 4);
				$symbol1 = kraken_ISOtoSymbol($symk);
				$symk = substr($pairk, 4);
				$symbol2 = kraken_ISOtoSymbol($symk);
				$proper[$symbol1.'-'.$symbol2] = $asset;
			}
			return $proper;
		}
	}
	return $res;
}

function kraken_api_user($method, $params='')
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_KRAKEN_SECRET')) return false;

	$apikey = EXCH_KRAKEN_KEY; // your API-key
	$apisecret = EXCH_KRAKEN_SECRET; // your Secret-key

	$kraken = new KrakenAPI($apikey, $apisecret);

	$arrParams = array();
	switch ($method) {
		case 'OpenOrders':
			$arrParams = array('trades'=> (bool) ($params));
			break;
		case 'Withdraw':
			$arrParams = array('trades'=> (bool) ($params));
			break;
	}

	try {
		$res = $kraken->QueryPrivate($method, $arrParams);

	} catch (KrakenAPIException $e) {
		debuglog('kraken: QueryPrivate() exception '.$e->getMessage());
		return false;
	}

	$proper = array();
	if (!empty($res) && isset($res['result']) && !empty($res['result'])) {
		switch ($method) {
		case 'Balance':
			foreach ($res['result'] as $symk => $balance) {
				$symbol = kraken_ISOtoSymbol($symk);
				$proper[$symbol] = $balance;
			}
			return $proper;
		}
	}

	return $res;
}

function kraken_btceur()
{
	$ticker = kraken_api_query('Ticker',array('pair'=>'XXBTZEUR'));
	if (isset($ticker["BTC-EUR"])) {
		$a = $ticker["BTC-EUR"]['a'][0];
		$b = $ticker["BTC-EUR"]['b'][0];
		$btceur = (($a + $b) / 2);
		return $btceur;
	}
	return false;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function kraken_update_market($market)
{
	$exchange = 'kraken';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = kraken_convertPair($symbol);
		$pair2 = $symbol.'-BTC';
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = kraken_convertPair($symbol);
		$pair2 = $symbol.'-BTC';
		if (!empty($market->base_coin)) {
			$pair = kraken_convertPair($symbol, $market->base_coin);
			$pair2 = $symbol.'-'.$market->base_coin;
		}
	}

	$t1 = microtime(true);
	$m = kraken_api_query('Ticker', array('pair'=>$pair));
	if(!$m || !isset($m[$pair2])) {
		user()->setFlash('error', "$exchange $symbol price returned ".json_encode($m));
		return false;
	}
	$ticker = $m[$pair2];

	$a = (double) $ticker['a'][0];
	$b = (double) $ticker['b'][0];
	$price2 = ($a + $b) / 2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $a*0.98);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
