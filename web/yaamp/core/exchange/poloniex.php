<?php
/**
 * Poloniex trading and public API
 */
class poloniex
{
		protected $api_key = '';
		protected $api_secret = '';
		protected $trading_url = "https://poloniex.com/tradingApi";
		protected $public_url = "https://poloniex.com/public";

		public function __construct() {
			require_once('/etc/yiimp/keys.php');
			if (defined('EXCH_POLONIEX_SECRET')) {
				$this->api_key = EXCH_POLONIEX_KEY;
				$this->api_secret = EXCH_POLONIEX_SECRET;
			}
		}

		private function query(array $req = array()) {
			// API settings
			$key = $this->api_key;
			$secret = $this->api_secret;

			// generate a nonce to avoid problems with 32bit systems
			$mt = explode(' ', microtime());
			$req['nonce'] = $mt[1].substr($mt[0], 2, 6);

			// generate the POST data string
			$post_data = http_build_query($req, '', '&');
			$sign = hash_hmac('sha512', $post_data, $secret);

			// generate the extra headers
			$headers = array(
				'Key: '.$key,
				'Sign: '.$sign,
			);

			// curl handle (initialize if required)
			static $ch = null;
			if (is_null($ch)) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_USERAGENT,
					'Mozilla/4.0 (compatible; Poloniex PHP bot; '.php_uname('a').'; PHP/'.phpversion().')'
				);
			}
			curl_setopt($ch, CURLOPT_URL, $this->trading_url);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 40);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

			// run the query
			$res = curl_exec($ch);

			if ($res === false) {
				debuglog('poloniex: curl error '.curl_error($ch));
				return false;
			}
			//echo $res;
			$dec = json_decode($res, true);
			if (!$dec){
				//throw new Exception('Invalid data: '.$res);
				return false;
			}else{
				return $dec;
			}
		}

		protected function retrieveJSON($URL) {
			$opts = array('http' =>
				array(
					'method'  => 'GET',
					'timeout' => 20
				)
			);
			$context = stream_context_create($opts);
			$feed = @file_get_contents($URL, false, $context);
			if(!$feed) return null;

			$json = json_decode($feed, true);
			return $json;
		}

		public function get_balances() {
			return $this->query(
				array(
					'command' => 'returnBalances'
				)
			);
		}

		public function get_complete_balances() {
			return $this->query(
				array(
					'command' => 'returnCompleteBalances'
				)
			);
		}

		public function get_available_balances() {
			return $this->query(
				array(
					'command' => 'returnAvailableAccountBalances',
				)
			);
		}

		public function get_deposit_addresses() {
			return $this->query(
				array(
					'command' => 'returnDepositAddresses'
				)
			);
		}

		public function generate_address($currency) {
			return $this->query(
				array(
					'command' => 'generateNewAddress',
					'currency' => strtoupper($currency),
				)
			);
		}

		public function get_open_orders($pair) {
			return $this->query(
				array(
					'command' => 'returnOpenOrders',
					'currencyPair' => strtoupper($pair)
				)
			);
		}

		public function get_my_trade_history($pair) {
			return $this->query(
				array(
					'command' => 'returnTradeHistory',
					'currencyPair' => strtoupper($pair)
				)
			);
		}

		public function buy($pair, $rate, $amount) {
			return $this->query(
				array(
					'command' => 'buy',
					'currencyPair' => strtoupper($pair),
					'rate' => $rate,
					'amount' => $amount
				)
			);
		}

		public function sell($pair, $rate, $amount) {
			return $this->query(
				array(
					'command' => 'sell',
					'currencyPair' => strtoupper($pair),
					'rate' => $rate,
					'amount' => $amount
				)
			);
		}

		public function cancel_order($pair, $order_number) {
			return $this->query(
				array(
					'command' => 'cancelOrder',
					'currencyPair' => strtoupper($pair),
					'orderNumber' => $order_number
				)
			);
		}

		public function withdraw($currency, $amount, $address) {
			return $this->query(
				array(
					'command' => 'withdraw',
					'currency' => strtoupper($currency),
					'amount' => $amount,
					'address' => $address
				)
			);
		}

		public function get_trade_history($pair) {
			$trades = $this->retrieveJSON($this->public_url.'?command=returnTradeHistory&currencyPair='.strtoupper($pair));
			return $trades;
		}

		public function get_order_book($pair) {
			$orders = $this->retrieveJSON($this->public_url.'?command=returnOrderBook&currencyPair='.strtoupper($pair));
			return $orders;
		}

		public function get_volume() {
			$volume = $this->retrieveJSON($this->public_url.'?command=return24hVolume');
			return $volume;
		}

		public function get_ticker($pair = "ALL") {
			$pair = strtoupper($pair);
			$prices = $this->retrieveJSON($this->public_url.'?command=returnTicker');
			if($pair == "ALL"){
				return $prices;
			}else{
				$pair = strtoupper($pair);
				if(isset($prices[$pair])){
					return $prices[$pair];
				}else{
					return array();
				}
			}
		}

		public function get_trading_pairs() {
			$tickers = $this->retrieveJSON($this->public_url.'?command=returnTicker');
			return array_keys($tickers);
		}

		public function get_currencies() {
			$tickers = $this->retrieveJSON($this->public_url.'?command=returnCurrencies');
			return $tickers;
		}

		public function get_total_btc_balance() {
			$balances = $this->get_balances();
			$prices = $this->get_ticker();

			$tot_btc = 0;

			foreach($balances as $coin => $amount){
				$pair = "BTC_".strtoupper($coin);

				// convert coin balances to btc value
				if($amount > 0){
					if($coin != "BTC"){
						$tot_btc += $amount * $prices[$pair];
					}else{
						$tot_btc += $amount;
					}
				}

				// process open orders as well
				if($coin != "BTC"){
					$open_orders = $this->get_open_orders($pair);
					foreach($open_orders as $order){
						if($order['type'] == 'buy'){
							$tot_btc += $order['total'];
						}elseif($order['type'] == 'sell'){
							$tot_btc += $order['amount'] * $prices[$pair];
						}
					}
				}
			}

			return $tot_btc;
		}

}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function poloniex_update_market($market)
{
	$exchange = 'poloniex';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = "BTC_$symbol";
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = "BTC_$symbol";
		if (!empty($market->base_coin)) $pair = $market->base_coin.'_'.$symbol;
	}

	$t1 = microtime(true);
	$poloniex = new poloniex;
	$ticker = $poloniex->get_ticker($pair);
	if (!is_array($ticker) || !isset($ticker['highestBid'])) {
		$apims = round((microtime(true) - $t1)*1000,3);
		user()->setFlash('error', "$exchange $symbol: error after $apims ms, ".json_encode($ticker));
		return false;
	}

	$price2 = ($ticker['highestBid']+$ticker['lowestAsk'])/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $ticker['highestBid']);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
