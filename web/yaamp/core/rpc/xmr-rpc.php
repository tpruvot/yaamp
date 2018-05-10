<?php
/*
if (!function_exists('debuglog')) {
	function debuglog($x) { echo "$x\n"; }
}
*/
class CryptoRPC
{
	// Configuration options
	private $username;
	private $password;

	private $proto;
	private $host;
	private $port;
	private $url;

	// Information and debugging
	public $status;
	public $error;
	public $raw_response;
	public $response;

	private $id = 0;

	function __construct($host='localhost', $port=18081, $username='', $password='')
	{
		$this->proto    = 'http';
		$this->host     = $host;
		$this->port     = $port;
		$this->url      = 'json_rpc';
		$this->username = $username;
		$this->password = $password;
	}

	function __call($method, $params=array())
	{
		$this->status       = null;
		$this->error        = null;
		$this->raw_response = null;
		$this->response     = null;

		switch ($method) {
			case 'getheight':
			case 'getinfo':
			case 'start_mining':
			case 'stop_mining':
				return $this->rpcget($method, $params);

			case 'gettransactions': // decodetransaction
			case 'sendrawtransaction':
				return $this->rpcpost($method, $params);

			// binary stuff
			case 'getblocks':
			case 'get_o_indexes':
			case 'getrandom_outs':
			case 'get_tx_pool':
			case 'set_maintainers_info':
			case 'check_keyimages':
				return $this->rpcget($method.'.bin', $params);

			// queries with named params
			case 'getblocktemplate':
			case 'get_payments':
			case 'incoming_transfers':
				if (count($params) == 1) {
					// __call put all params in array $params
					$pop = array_shift($params);
					if (is_object($pop) || is_array($pop)) {
						$params = (object) $pop;
					}
				}
				break;
			case 'transfer':
			case 'transfer_original':
				if (is_string($params[0])) { // assume json
					//debuglog("params: ".$params[0]);
					$params = array(json_decode($params[0]));
				}
				else if (is_array($params) && count($params) == 1) {
					// __call put all params in array $params
					$pop = array_shift($params);
					if (is_object($pop) || is_array($pop)) {
						$params = (object) $pop;
					}
				}
				break;
		}

		//debuglog(json_encode($params));

		$data = array();
		$data['method'] = $method;
		$data['params'] = $params;

		$data['id'] = $this->id++;
		$data['jsonrpc'] = '2.0';

		// Build the cURL session, to check later {$this->username}:{$this->password}@
		$curl = curl_init("{$this->proto}://{$this->host}:{$this->port}/{$this->url}");

		$options = array(
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
		);

		curl_setopt_array($curl, $options);
		$postdata = json_encode($data);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
		//debuglog($postdata);

		// Execute the request and decode to an array
		$this->raw_response = curl_exec($curl);
		//debuglog($this->raw_response);

		$this->response = json_decode($this->raw_response, TRUE);

		// If the status is not 200, something is wrong
		$this->status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// If there was no error, this will be an empty string
		$curl_error = curl_error($curl);

		curl_close($curl);

		if (!empty($curl_error)) {
			$this->error = $curl_error;
		}

		if (isset($this->response['error']) && $this->response['error']) {
			$this->error = strtolower($this->response['error']['message']);
		}

		elseif ($this->status != 200) {
			// If didn't return a nice error message, we need to make our own
			switch ($this->status) {
				case 400:
					$this->error = 'HTTP_BAD_REQUEST';
					break;
				case 401:
					$this->error = 'HTTP_UNAUTHORIZED';
					break;
				case 403:
					$this->error = 'HTTP_FORBIDDEN';
					break;
				case 404:
					$this->error = 'HTTP_NOT_FOUND';
					break;
			}
		}

		if ($this->error) {
			return FALSE;
		}

		return $this->response['result'];
	}

	// these methods use other urls
	function rpcget($url, $params=array())
	{
		$url = "{$this->proto}://{$this->host}:{$this->port}/{$url}";
		if (!empty($params)) {
			$url = "?ts=".time();
			foreach ($params as $key => $val) {
				$url .= '&'.urlencode($key).'='.urlencode($val);
			}
		}
		$curl = curl_init($url);

		$options = array(
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_POST           => false,
		);
		curl_setopt_array($curl, $options);
		$this->raw_response = curl_exec($curl);
		$this->status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// If there was no error, this will be an empty string
		$curl_error = curl_error($curl);

		curl_close($curl);
		//debuglog($this->response);

		if (!empty($curl_error)) {
			$this->error = $curl_error;
		}

		if (isset($this->response['error']) && $this->response['error']) {
			$this->error = strtolower($this->response['error']['message']);
		}

		elseif ($this->status != 200) {
			// If didn't return a nice error message, we need to make our own
			switch ($this->status) {
				case 400:
					$this->error = 'HTTP_BAD_REQUEST';
					break;
				case 401:
					$this->error = 'HTTP_UNAUTHORIZED';
					break;
				case 403:
					$this->error = 'HTTP_FORBIDDEN';
					break;
				case 404:
					$this->error = 'HTTP_NOT_FOUND';
					break;
			}
		} else {
			// getinfo
			$this->response = json_decode($this->raw_response, TRUE);
		}

		return $this->response;
	}

	// sendrawtransaction (untested yet)
	function rpcpost($url, $params=array())
	{
		$curl = curl_init("{$this->proto}://{$this->host}:{$this->port}/{$url}");

		$options = array(
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
		);
		curl_setopt_array($curl, $options);

		$pop = array_pop($params);
		if (is_object($pop) || is_array($pop)) {
			$params = (object) $pop;
		}
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));

		$this->raw_response = curl_exec($curl);
		$this->status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// If there was no error, this will be an empty string
		$curl_error = curl_error($curl);

		curl_close($curl);

		if (!empty($curl_error)) {
			$this->error = $curl_error;
		}

		if (isset($this->response['error']) && $this->response['error']) {
			$this->error = strtolower($this->response['error']['message']);
		}

		elseif ($this->status != 200) {
			// If didn't return a nice error message, we need to make our own
			switch ($this->status) {
				case 400:
					$this->error = 'HTTP_BAD_REQUEST';
					break;
				case 401:
					$this->error = 'HTTP_UNAUTHORIZED';
					break;
				case 403:
					$this->error = 'HTTP_FORBIDDEN';
					break;
				case 404:
					$this->error = 'HTTP_NOT_FOUND';
					break;
			}
		} else {
			// getinfo
			$this->response = json_decode($this->raw_response, TRUE);
		}

		return $this->response;
	}

}
