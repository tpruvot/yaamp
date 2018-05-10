<?php

/**
 * Ethereum JSON-RPC interface
 *
 * See Ethereum API documentation for more information:
 * http://ethereum.gitbooks.io/frontier-guide/content/rpc.html
 */

//require_once(dirname(__FILE__).'/json-rpc.php');

class Ethereum extends JSON_RPC
{
	function ether_request($method, $params=array())
	{
		try {
			$ret = $this->request($method, $params);
			return $ret->result;
		}
		catch(RPCException $e) {
			$this->error = "$e";
			return false;
		}
	}

	function decode_hex($input)
	{
		if(substr($input, 0, 2) == '0x')
			$input = substr($input, 2);

		if(preg_match('/[a-f0-9]+/', $input))
			return hexdec($input);

		return $input;
	}

	function web3_clientVersion()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function web3_sha3($input)
	{
		return $this->ether_request(__FUNCTION__, array($input));
	}

	function net_version()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function net_listening()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function net_peerCount($decode_hex=true)
	{
		$peers = $this->ether_request(__FUNCTION__);
		if($decode_hex)
			$peers = $this->decode_hex($peers);
		return $peers;
	}

	function eth_protocolVersion()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function eth_coinbase()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function eth_mining()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function eth_hashrate()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function eth_gasPrice($decode_hex=true)
	{
		$gaz = $this->ether_request(__FUNCTION__);
		if($decode_hex)
			$gaz = $this->decode_hex($gaz);
		return $gaz;
	}

	function eth_accounts()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function eth_blockNumber($decode_hex=true)
	{
		$block = $this->ether_request(__FUNCTION__);

		if($decode_hex)
			$block = $this->decode_hex($block);

		return $block;
	}

	function eth_getBalance($address, $block='latest', $decode_hex=true)
	{
		$balance = $this->ether_request(__FUNCTION__, array($address, $block));
		if($decode_hex)
			$balance = $this->decode_hex($balance);
		return $balance;
	}

	function eth_getStorageAt($address, $at, $block='latest')
	{
		return $this->ether_request(__FUNCTION__, array($address, $at, $block));
	}

	function eth_getTransactionCount($address, $block='latest', $decode_hex=true)
	{
		$count = $this->ether_request(__FUNCTION__, array($address, $block));

        if($decode_hex)
            $count = $this->decode_hex($count);

        return $count;
	}

	function eth_getBlockTransactionCountByHash($tx_hash)
	{
		return $this->ether_request(__FUNCTION__, array($tx_hash));
	}

	function eth_getBlockTransactionCountByNumber($tx='latest')
	{
		return $this->ether_request(__FUNCTION__, array($tx));
	}

	function eth_getUncleCountByBlockHash($block_hash)
	{
		return $this->ether_request(__FUNCTION__, array($block_hash));
	}

	function eth_getUncleCountByBlockNumber($block='latest')
	{
		return $this->ether_request(__FUNCTION__, array($block));
	}

	function eth_getCode($address, $block='latest')
	{
		return $this->ether_request(__FUNCTION__, array($address, $block));
	}

	function eth_sign($address, $input)
	{
		return $this->ether_request(__FUNCTION__, array($address, $input));
	}

	function eth_sendTransaction($transaction)
	{
		if(!is_a($transaction, 'Ethereum_Transaction'))
		{
			throw new ErrorException('Transaction object expected');
		}
		else
		{
			return $this->ether_request(__FUNCTION__, $transaction->toArray());
		}
	}

	function eth_call($message, $block)
	{
		if(!is_a($message, 'Ethereum_Message'))
		{
			throw new ErrorException('Message object expected');
		}
		else
		{
			return $this->ether_request(__FUNCTION__, $message->toArray());
		}
	}

	function eth_estimateGas($message, $block)
	{
		if(!is_a($message, 'Ethereum_Message'))
		{
			throw new ErrorException('Message object expected');
		}
		else
		{
			return $this->ether_request(__FUNCTION__, $message->toArray());
		}
	}

	function eth_getBlockByHash($hash, $full_tx=TRUE)
	{
		return $this->ether_request(__FUNCTION__, array($hash, $full_tx));
	}

	function eth_getBlockByNumber($block='latest', $full_tx=TRUE)
	{
		return $this->ether_request(__FUNCTION__, array($block, $full_tx));
	}

	function eth_getTransactionByHash($hash)
	{
		return $this->ether_request(__FUNCTION__, array($hash));
	}

	function eth_getTransactionByBlockHashAndIndex($hash, $index)
	{
		return $this->ether_request(__FUNCTION__, array($hash, $index));
	}

	function eth_getTransactionByBlockNumberAndIndex($block, $index)
	{
		return $this->ether_request(__FUNCTION__, array($block, $index));
	}

	function eth_getTransactionReceipt($tx_hash)
	{
		return $this->ether_request(__FUNCTION__, array($tx_hash));
	}

	function eth_getUncleByBlockHashAndIndex($hash, $index)
	{
		return $this->ether_request(__FUNCTION__, array($hash, $index));
	}

	function eth_getUncleByBlockNumberAndIndex($block, $index)
	{
		return $this->ether_request(__FUNCTION__, array($block, $index));
	}

	function eth_getCompilers()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function eth_compileSolidity($code)
	{
		return $this->ether_request(__FUNCTION__, array($code));
	}

	function eth_compileLLL($code)
	{
		return $this->ether_request(__FUNCTION__, array($code));
	}

	function eth_compileSerpent($code)
	{
		return $this->ether_request(__FUNCTION__, array($code));
	}

	function eth_newFilter($filter, $decode_hex=true)
	{
		if(!is_a($filter, 'Ethereum_Filter'))
		{
			throw new ErrorException('Expected a Filter object');
		}
		else
		{
			$id = $this->ether_request(__FUNCTION__, $filter->toArray());

			if($decode_hex)
				$id = $this->decode_hex($id);

			return $id;
		}
	}

	function eth_newBlockFilter($decode_hex=true)
	{
		$id = $this->ether_request(__FUNCTION__);

		if($decode_hex)
			$id = $this->decode_hex($id);

		return $id;
	}

	function eth_newPendingTransactionFilter($decode_hex=true)
	{
		$id = $this->ether_request(__FUNCTION__);

		if($decode_hex)
			$id = $this->decode_hex($id);

		return $id;
	}

	function eth_uninstallFilter($id)
	{
		return $this->ether_request(__FUNCTION__, array($id));
	}

	function eth_getFilterChanges($id)
	{
		return $this->ether_request(__FUNCTION__, array($id));
	}

	function eth_getFilterLogs($id)
	{
		return $this->ether_request(__FUNCTION__, array($id));
	}

	function eth_getLogs($filter)
	{
		if(!is_a($filter, 'Ethereum_Filter'))
		{
			throw new ErrorException('Expected a Filter object');
		}
		else
		{
			return $this->ether_request(__FUNCTION__, $filter->toArray());
		}
	}

	function eth_getWork()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function eth_submitWork($nonce, $pow_hash, $mix_digest)
	{
		return $this->ether_request(__FUNCTION__, array($nonce, $pow_hash, $mix_digest));
	}

	function db_putString($db, $key, $value)
	{
		return $this->ether_request(__FUNCTION__, array($db, $key, $value));
	}

	function db_getString($db, $key)
	{
		return $this->ether_request(__FUNCTION__, array($db, $key));
	}

	function db_putHex($db, $key, $value)
	{
		return $this->ether_request(__FUNCTION__, array($db, $key, $value));
	}

	function db_getHex($db, $key)
	{
		return $this->ether_request(__FUNCTION__, array($db, $key));
	}

	function shh_version()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function shh_post($post)
	{
		if(!is_a($post, 'Whisper_Post'))
		{
			throw new ErrorException('Expected a Whisper post');
		}
		else
		{
			return $this->ether_request(__FUNCTION__, $post->toArray());
		}
	}

	function shh_newIdentinty()
	{
		return $this->ether_request(__FUNCTION__);
	}

	function shh_hasIdentity($id)
	{
		return $this->ether_request(__FUNCTION__);
	}

	function shh_newFilter($to=NULL, $topics=array())
	{
		return $this->ether_request(__FUNCTION__, array(array('to'=>$to, 'topics'=>$topics)));
	}

	function shh_uninstallFilter($id)
	{
		return $this->ether_request(__FUNCTION__, array($id));
	}

	function shh_getFilterChanges($id)
	{
		return $this->ether_request(__FUNCTION__, array($id));
	}

	function shh_getMessages($id)
	{
		return $this->ether_request(__FUNCTION__, array($id));
	}
}

/**
 *	Ethereum transaction object
 */
class Ethereum_Transaction
{
	private $to, $from, $gas, $gasPrice, $value, $data, $nonce;

	function __construct($from, $to, $gas, $gasPrice, $value, $data='', $nonce=NULL)
	{
		$this->from = $from;
		$this->to = $to;
		$this->gas = $gas;
		$this->gasPrice = $gasPrice;
		$this->value = $value;
		$this->data = $data;
		$this->nonce = $nonce;
	}

	function toArray()
	{
		return array(
			array
			(
				'from'=>$this->from,
				'to'=>$this->to,
				'gas'=>$this->gas,
				'gasPrice'=>$this->gasPrice,
				'value'=>$this->value,
				'data'=>$this->data,
				'nonce'=>$this->nonce
			)
		);
	}
}

/**
 *	Ethereum message -- Same as a transaction, except using this won't
 *  post the transaction to the blockchain.
 */
class Ethereum_Message extends Ethereum_Transaction
{

}

/**
 *	Ethereum transaction filter object
 */
class Ethereum_Filter
{
	private $fromBlock, $toBlock, $address, $topics;

	function __construct($fromBlock, $toBlock, $address, $topics)
	{
		$this->fromBlock = $fromBlock;
		$this->toBlock = $toBlock;
		$this->address = $address;
		$this->topics = $topics;
	}

	function toArray()
	{
		return array(
			array
			(
				'fromBlock'=>$this->fromBlock,
				'toBlock'=>$this->toBlock,
				'address'=>$this->address,
				'topics'=>$this->topics
			)
		);
	}
}

/**
 * 	Ethereum whisper post object
 */
class Whisper_Post
{
	private $from, $to, $topics, $payload, $priority, $ttl;

	function __construct($from, $to, $topics, $payload, $priority, $ttl)
	{
		$this->from = $from;
		$this->to = $to;
		$this->topics = $topics;
		$this->payload = $payload;
		$this->priority = $priority;
		$this->ttl = $ttl;
	}

	function toArray()
	{
		return array(
			array
			(
				'from'=>$this->from,
				'to'=>$this->to,
				'topics'=>$this->topics,
				'payload'=>$this->payload,
				'priority'=>$this->priority,
				'ttl'=>$this->ttl
			)
		);
	}
}
