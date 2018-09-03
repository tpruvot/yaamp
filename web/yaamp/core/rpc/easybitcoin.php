<?php
/*
EasyBitcoin-PHP

A simple class for making calls to Bitcoin's API using PHP.
https://github.com/aceat64/EasyBitcoin-PHP

====================

The MIT License (MIT)

Copyright (c) 2013 Andrew LeCody

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

====================

// Initialize Bitcoin connection/object
$bitcoin = new Bitcoin('username','password');

// Optionally, you can specify a host and port.
$bitcoin = new Bitcoin('username','password','host','port');
// Defaults are:
//  host = localhost
//  port = 8332
//  proto = http

// If you wish to make an SSL connection you can set an optional CA certificate or leave blank
// This will set the protocol to HTTPS and some CURL flags
$bitcoin->setSSL('/full/path/to/mycertificate.cert');

// Make calls to bitcoind as methods for your object. Responses are returned as an array.
// Examples:
$bitcoin->getinfo();
$bitcoin->getrawtransaction('0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098',1);
$bitcoin->getblock('000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f');

// The full response (not usually needed) is stored in $this->response while the raw JSON is stored in $this->raw_response

// When a call fails for any reason, it will return FALSE and put the error message in $this->error
// Example:
echo $bitcoin->error;

// The HTTP status code can be found in $this->status and will either be a valid HTTP status code or will be 0 if cURL was unable to connect.
// Example:
echo $bitcoin->status;

*/

class Bitcoin {
	// Configuration options
	private $username;
	private $password;
	private $proto;
	private $host;
	private $port;
	private $url;
	private $CACertificate;

	// Information and debugging
	public $status;
	public $error;
	public $raw_response;
	public $response;

	private $id = 0;

	/**
	 * @param string $username
	 * @param string $password
	 * @param string $host
	 * @param int $port
	 * @param string $proto
	 * @param string $url
	 */
	function __construct($username, $password, $host = 'localhost', $port = 8332, $url = null) {
		$this->username      = $username;
		$this->password      = $password;
		$this->host          = $host;
		$this->port          = $port;
		$this->url           = $url;

		// Set some defaults
		$this->proto         = 'http';
		$this->CACertificate = null;

		// Hack to be able to connect to wallets over TLS
		// sample coin HOST:  https://dcrd@127.0.0.1
		// where dcrd is the certificate filename.
		if (strstr($host, 'https://') !== false) {
			$host = substr($host, strlen('https://'));
			if (strstr($host, '@') !== false) {
				$parts = explode('@', $host);
				$cert = $parts[0];
				$host = $parts[1];
			} else {
				$cert = 'yiimp';
			}
			$this->host = $host;
			$this->setSSL("/usr/share/ca-certificates/$cert.crt");
		}
	}

	/**
	 * @param string|null $certificate
	 */
	function setSSL($certificate = null) {
		$this->proto         = 'https'; // force HTTPS
		$this->CACertificate = $certificate;
	}

	function __call($method, $params)
	{
		$this->status       = null;
		$this->error        = null;
		$this->raw_response = null;
		$this->response     = null;

		// The ID should be unique for each call
		$this->id++;

		if (stripos($method, 'dump') !== false || stripos($method, 'backupwallet') !== false) {
			$this->error = "$method method is not authorized!";
			return FALSE;
		}

		// If no parameters are passed, this will be an empty array
		if($method == 'getblocktemplate')
		{
			$param = isset($params[0]) ? $params[0] : '';
			$request = "{\"method\":\"$method\",\"params\":[$param],\"id\":$this->id}";
		//  debuglog($request);
		}

		else
		{
			$params = array_values($params);

			// Build the request, it's ok that params might have any empty array
			$request = json_encode(array(
				'method' => $method,
				'params' => $params,
				'id'     => $this->id
			));
		}

		// Build the cURL session
		$curl    = curl_init("{$this->proto}://{$this->username}:{$this->password}@{$this->host}:{$this->port}/{$this->url}");
		$options = array(
			CURLOPT_CONNECTTIMEOUT => 20,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_HTTPHEADER     => array('Content-type: application/json'),
			CURLOPT_POST           => TRUE,
			CURLOPT_POSTFIELDS     => $request
		);

		if ($this->proto == 'https') {
			// If the CA Certificate was specified we change CURL to look for it
			if ($this->CACertificate != null) {
				$options[CURLOPT_CAINFO] = $this->CACertificate;
				$options[CURLOPT_CAPATH] = DIRNAME($this->CACertificate);
				$options[CURLOPT_SSLVERSION] = 1; // TLSv1
			}
			else {
				// If not we need to assume the SSL cannot be verified so we set this flag to FALSE to allow the connection
				$options[CURLOPT_SSL_VERIFYPEER] = FALSE;
			}
		}

		curl_setopt_array($curl, $options);

		// Execute the request and decode to an array
		$this->raw_response = curl_exec($curl);
//      debuglog($this->raw_response);
		$this->response     = json_decode($this->raw_response, TRUE);

		// If the status is not 200, something is wrong
		$this->status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// If there was no error, this will be an empty string
		$curl_error = curl_error($curl);

		curl_close($curl);
//      debuglog($this->response);

		if (!empty($curl_error)) {
			$this->error = $curl_error;
		}

		if (isset($this->response['error']) && $this->response['error']) {
			// store wallet error
			$code = arraySafeVal($this->response['error'], 'code', '');
			$message = arraySafeVal($this->response['error'], 'message', '');
			$this->error = "error $code: ".strtolower($message);
		}
		elseif ($this->status != 200) {
			// If bitcoind didn't return a nice error message, we need to make our own
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

	// direct json..
	public function request_json($json)
	{
		$this->status       = null;
		$this->error        = null;
		$this->response     = null;
		$this->raw_response = null;

		$data = json_decode($json);
		$data->id = $this->id++;
		debuglog($json);

		$ch = curl_init("{$this->proto}://{$this->username}:{$this->password}@{$this->host}:{$this->port}/{$this->url}");
		$options = array(
			CURLOPT_CONNECTTIMEOUT => 20,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_HTTPHEADER     => array('Content-type: application/json'),
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => json_encode($data)
		);
		curl_setopt_array($ch, $options);

		$this->raw_response = curl_exec($ch);
		$this->response = json_decode($this->raw_response, true);
		$this->status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);

		if (!empty($curl_error)) {
			$this->error = $curl_error;
		}

		if (isset($this->response['error']) && $this->response['error']) {
			// store wallet error
			$this->error = strtolower($this->response['error']['message']);
		}
		elseif ($this->status != 200) {
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
			return false;
		}

		return $this->response['result'];
	}

}
