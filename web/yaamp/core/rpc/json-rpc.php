<?php

/**
 * Simple JSON-RPC interface.
 */

class JSON_RPC
{
	protected $host, $port, $version;
	protected $id = 0;

	// Information and debugging
	public $error;

	function __construct($host, $port, $version="2.0")
	{
		$this->host = $host;
		$this->port = $port;
		$this->version = $version;
	}

	function request($method, $params=array())
	{
		$data = array();
		$data['jsonrpc'] = $this->version;
		$data['id'] = $this->id++;
		$data['method'] = $method;
		$data['params'] = $params;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->host);
		curl_setopt($ch, CURLOPT_PORT, $this->port);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		$ret = curl_exec($ch);

		if($ret !== FALSE)
		{
			$formatted = $this->format_response($ret);
			if(property_exists($formatted,'error')) {
				$err = $formatted->error;
				$this->error = objSafeVal($err, 'message', json_encode($err));
				throw new RPCException($err->message, $err->code);
			} else {
				return $formatted;
			}
		} else {
			throw new RPCException("Server did not respond");
		}
	}

	function format_response($response)
	{
		return @json_decode($response);
	}
}

class RPCException extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return "RPC: ".trim(($this->code>0?"[{$this->code}]:":"")." ".$this->message)."\n";
    }
}