<?php
class LiveCoinApi
{
	protected $api_url = 'https://api.livecoin.net/';
	protected $api_key = EXCH_LIVECOIN_KEY;

	public $timeout = 10;

	protected function jsonAuth($url, $params=array(), $post=false)
	{
		require_once('/etc/yiimp/keys.php');
		if (!defined('EXCH_LIVECOIN_SECRET')) {
			define('EXCH_LIVECOIN_SECRET', '');
		}
		if (empty(EXCH_LIVECOIN_SECRET)) {
			return false;
		}

		ksort($params);
		$fields = http_build_query($params, '', '&');
		$signature = strtoupper(hash_hmac('sha256', $fields, EXCH_LIVECOIN_SECRET));

		$headers = array(
			"Api-Key: $this->api_key",
			"Sign: $signature"
		);

		if ($post) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		} else {
			$ch = curl_init($url."?".$fields);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; LiveCoin PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout/2);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

		$response = curl_exec($ch);
		if ($response) {
			$a = json_decode($response);
			$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if (!$a) {
				debuglog("LiveCoin: Auth API failed ($status) ".strip_data($response).' '.curl_error($ch));
			}
		}
		curl_close($ch);

		return isset($a) ? $a : false;
	}

	protected function jsonGet($url, $params=array())
	{

		$fields = http_build_query($params, '', '&');
		$ch = curl_init($url."?".$fields);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; LiveCoin PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout/2);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

		$response = curl_exec($ch);

		if ($response) {
			$a = json_decode($response);
			$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if (!$a) {
				debuglog("LiveCoin: Auth API failed ($status) ".strip_data($response).' '.curl_error($ch));
			}
		}
		curl_close($ch);

		return isset($a) ? $a : false;
	}

	// Public data
	public function getTickerInfo($pair=false)
	{
		$params = array();
		if ($pair) {
			$params['currencyPair'] = $pair;
		}

		return $this->jsonGet($this->api_url.'/exchange/ticker', $params);
	}

	public function getLastTrades($pair, $minorhr='false', $type='flase')
	{
		$params = array(
			'currencyPair' => $pair,
			'minutesOrHour' => $minorhr,
			'type' => $type
		);
		return $this->jsonGet($this->api_url.'/exchange/last_trades', $params);
	}

	public function getOrderBook($pair, $group='false', $depth=10)
	{
		$params = array('currencyPair' => $pair, 'groupByPrice' => $group, 'depth' => $depth);
		return $this->jsonGet($this->api_url.'/exchange/order_book', $params);
	}

	public function getAllOrderBook($group='false', $depth=10)
	{
		$params = array('groupByPrice' => $group, 'depth' => $depth);
		return $this->jsonGet($this->api_url.'/exchange/all/order_book', $params);
	}

	public function getMaxMin($pair=false)
	{
		$params = array();
		if ($pair) {
			$params['currencyPair'] = $pair;
		}

		return $this->jsonGet($this->api_url.'/exchange/maxbid_minask', $params);
	}

	public function getRestrictions()
	{
		return $this->jsonGet($this->api_url.'/exchange/restrictions');
	}

	public function getCoinInfo()
	{
		return $this->jsonGet($this->api_url.'/info/coininfo');
	}


	// Private user data
	public function getTrades($pair=false, $order='true', $limit=100, $offset=0)
	{
		$params = array(
			'orderDesc' => $order,
			'limit' => $limit,
			'offset' => $offset
		);
		if ($pair) {
			$params['currencyPair'] = $pair;
		}

		return $this->jsonAuth($this->api_url.'/exchange/trades', $params);
	}

	public function getClientOrders($pair=false, $open='ALL', $from=false, $to=false, $start=0, $end=2147483646)
	{
		$params = array(
			'open' => $open,
			'start' => $start,
			'end' => $end
		);
		if ($pair) {
			$params['currencyPair'] = $pair;
		}
		if ($from) {
			$params['issuedFrom'] = $from;
		}
		if ($to) {
			$params['issuedTo'] = $to;
		}

		return $this->jsonAuth($this->api_url.'/exchange/client_orders', $params);
	}

	public function getOrder($id)
	{
		$params = array('orderId' => $id);
		return $this->jsonAuth($this->api_url.'/exchange/order', $params);
	}

	public function getBalances($currency=false)
	{
		$params = array();
		if ($currency) {
			$params['currency'] = $currency;
		}
		return $this->jsonAuth($this->api_url.'/payment/balances', $params);
	}

	public function getTransactions($start, $end, $types='BUY,SELL,DEPOSIT,WITHDRAWAL', $limit=100, $offset=0)
	{
		$params = array(
			'start' => $start,
			'end' => $end,
			'types' => $types,
			'limit' => $limit,
			'offset' => $offset
		);
		return $this->jsonAuth($this->api_url.'/payment/history/transactions', $params);
	}


	// Orders
	public function buyLimit($pair, $price, $quantity)
	{
		$params = array(
			'currencyPair' => $pair,
			'price' => $price,
			'quantity' => $quantity
		);
		return $this->jsonAuth($this->api_url.'/exchange/buylimit', $params, true);
	}

	public function sellLimit($pair, $price, $quantity)
	{
		$params = array(
			'currencyPair' => $pair,
			'price' => $price,
			'quantity' => $quantity
		);
		return $this->jsonAuth($this->api_url.'/exchange/selllimit', $params, true);
	}

	public function cancelLimitOrder($pair, $id)
	{
		$params = array(
			'currencyPair' => $pair,
			'orderId' => $id
		);
		return $this->jsonAuth($this->api_url.'/exchange/cancellimit', $params, true);
	}

	// Deposit and Withdrawal
	public function getDepositAddress($symbol) {
		$params = array('currency' => $symbol);
		return $this->jsonAuth($this->api_url.'/payment/get/address', $params);
	}

	public function withdrawCoin($amnt, $currency, $wallet)
	{
		$params = array(
			'amount' => $amnt,
			'currency' => $currency,
			'wallet' => $wallet
		);
		return $this->jsonAuth($this->api_url.'/payment/out/coin', $params, true);
	}
}

// public api
// https://api.livecoin.net/exchange/ticker
function livecoin_api_query($method, $params='')
{
	$uri = "https://api.livecoin.net/$method";
	if (!empty($params))
		$uri .= "/$params";
	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	$execResult = curl_exec($ch);
	$obj = json_decode($execResult);
	return $obj;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function livecoin_update_market($market)
{
	$exchange = 'livecoin';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = $symbol.'/BTC';
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = $symbol.'/BTC';
		if (!empty($market->base_coin)) $pair = $symbol.'/'.$market->base_coin;
	}

	$t1 = microtime(true);
	$ticker = livecoin_api_query('exchange/ticker','?currencyPair='.urlencode($pair));
	if(!empty($ticker->errorMessage)) {
		user()->setFlash('error', "$exchange $symbol {$ticker->errorMessage}");
	}
	if(!is_object($ticker) || !isset($ticker->best_bid)) return false;

	$price2 = ($ticker->best_bid+$ticker->best_ask)/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $ticker->best_bid);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms {$market->price}");

	return true;
}
