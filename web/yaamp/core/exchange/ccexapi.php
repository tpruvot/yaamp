<?php
/**
 * API-call related functions
 *
 * @author Remdev
 * @author tpruvot 2016
 *
 * @license MIT License - https://github.com/Remdev/PHP-ccex-api
 */

class CcexAPI
{
	protected $api_url = 'https://c-cex.com/t/';
	protected $api_key = EXCH_CCEX_KEY;

	public $timeout = 10;

	protected function jsonQueryAuth($url)
	{
		require_once('/etc/yiimp/keys.php');
		if (!defined('EXCH_CCEX_SECRET')) define('EXCH_CCEX_SECRET', '');
		if (empty(EXCH_CCEX_SECRET)) return false;

		$nonce = time();
		$uri = $url.'&apikey='.$this->api_key.'&nonce='.$nonce;

		$sign = hash_hmac('sha512', $uri, EXCH_CCEX_SECRET);
		$ch = curl_init($uri);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('apisign:'.$sign));
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; C-Cex PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout/2);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

		$feed = curl_exec($ch);
		if ($feed) {
			$a = json_decode($feed, true);
			$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if(!$a) debuglog("c-cex: auth api failed ($status) ".strip_data($feed).' '.curl_error($ch));
		}
		curl_close($ch);

		return isset($a) ? $a : false;
	}

	protected function jsonQuery($url)
	{
		$opts = array(
			'http' => array(
				'method'  => 'GET',
				'timeout' => $this->timeout,
			)
		);

		$context = stream_context_create($opts);
		$feed = @file_get_contents($url, false, $context);

		if(!$feed) {

			debuglog("c-cex error $url");
			return null; //array('error' => 'Invalid parameters');

		} else {

			$a = json_decode($feed, true);
			if(!$a) debuglog("c-cex: $feed");

			return $a;
		}
	}

	public function getTickerInfo($pair){
		$json = $this->jsonQuery($this->api_url.$pair.'.json');
		return $json['ticker'];
	}

	public function getCoinNames(){
		$json = $this->jsonQuery($this->api_url.'coinnames.json');
		return is_array($json) ? $json : array();
	}

	public function getMarkets(){
		$json = $this->jsonQuery($this->api_url.'api_pub.html?a=getmarkets');
		return isset($json['result']) ? $json['result'] : array();
	}

	public function getMarketSummaries(){
		$json = $this->jsonQuery($this->api_url.'api_pub.html?a=getmarketsummaries');
		return isset($json['result']) ? $json['result'] : array();
	}

	public function getPairs(){
		$json = $this->jsonQuery($this->api_url.'pairs.json');
		return isset($json['pairs'])? $json['pairs']: array();
	}

	public function getVolumes($hours=24,$pair=false){
		$url = ($pair) ? 'volume' : 'lastvolumes&pair='.$pair.'&';
		return $this->jsonQuery($this->api_url."s.html?a=".$url."&h=".$hours);
	}

	public function getOrders($pair,$self = 0){
		$self = intval( (bool)$self );//return only 0 or 1
		return $this->jsonQuery($this->api_url."r.html?key={$this->api_key}&a=orderlist&self={$self}&pair={$pair}");
	}

	public function getHistory($pair,$fromTime = false,$toTime = false,$self = false){

		if($fromTime === false){
			$fromTime = 0;
		}

		if($toTime === false){
			$toTime = time();
		}

		$fromDate = date('Y-d-m',(int)$fromTime);
		$toDate = date('Y-d-m',(int)$toTime);

		$url = ($self) ? "r.html?key={$this->api_key}&" : "s.html?";
		return $this->jsonQuery($this->api_url.$url."a=tradehistory&d1={$fromDate}&d2={$toDate}&pair={$pair}");
	}

	public function makeOrder($type,$pair,$quantity,$price){
		if(strtolower($type) == 'sell'){
			$type = 's';
		}
		if(strtolower($type) == 'buy'){
			$type = 'b';
		}
		return $this->jsonQuery($this->api_url."r.html?key={$this->api_key}&a=makeorder&pair={$pair}&q={$quantity}&t={$type}&r={$price}");
	}

	public function cancelOrder($order) {
		return $this->jsonQuery($this->api_url."r.html?key={$this->api_key}&a=cancelorder&id={$order}");
	}

	public function getBalance() {
		return $this->jsonQuery($this->api_url."r.html?key={$this->api_key}&a=getbalance");
	}

	public function getBalances() {
		return $this->jsonQueryAuth($this->api_url."api.html?a=getbalances");
	}

	// If not exists - will generate new
	public function getDepositAddress($symbol) {
		$coin = strtolower($symbol);
		return $this->jsonQuery($this->api_url."r.html?key={$this->api_key}&a=getaddress&coin={$coin}");
	}

	public function checkDeposit($symbol, $txid) {
		$coin = strtolower($symbol);
		return $this->jsonQuery($this->api_url."r.html?key={$this->api_key}&a=checkdeposit&coin={$coin}&tid={$txid}");
	}

	public function withdraw($symbol, $amount, $address) {
		$coin = strtolower($symbol);
		return $this->jsonQuery($this->api_url."r.html?key={$this->api_key}&a=withdraw&coin={$coin}&amount={$amount}&address={$address}");
	}

	public function getDepositHistory($symbol, $limit=100) {
		$coin = strtolower($symbol);
		return $this->jsonQuery($this->api_url."r.html?key={$this->api_key}&a=deposithistory&coin={$coin}&limit={$limit}");
	}

	public function getWithdrawalHistory($symbol, $limit=100) {
		$coin = strtolower($symbol);
		return $this->jsonQuery($this->api_url."r.html?key={$this->api_key}&a=withdrawalhistory&coin={$coin}&limit={$limit}");
	}

}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function ccex_update_market($market)
{
	$exchange = 'c-cex';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = strtolower($symbol."-btc");
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = strtolower($symbol."-btc");
		if (!empty($market->base_coin)) $pair = strtolower($symbol.'-'.$market->base_coin);
	}

	$t1 = microtime(true);
	$ccex = new CcexAPI;
	$ticker = $ccex->getTickerInfo($pair);
	if (!$ticker || !is_array($ticker) || !isset($ticker['buy'])) {
		$apims = round((microtime(true) - $t1)*1000,3);
		user()->setFlash('error', "$exchange $symbol: error after $apims ms, ".json_encode($ticker));
		return false;
	}

	$price2 = ($ticker['buy']+$ticker['sell'])/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $ticker['buy']);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
