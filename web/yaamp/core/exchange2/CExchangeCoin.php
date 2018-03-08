<?php

class CExchangeCoin
{
	public $marketname;

	public $coin;
	public $market;

	public $bid;
	public $ask;

	public $bids;
	public $asks;

	abstract protected function load();
	abstract protected function sell($amount, $price);
	abstract protected function cancel();

	//////

	public function __construct($coin, $marketname)
	{
		$this->coin = $coin;
		$this->marketname = $marketname;

		$this->market = getdbosql('db_markets', "coinid=$coin->id and name='$marketname'");
		if(!$this->market) return;
	}

	public static function create($coin, $marketname)
	{
/*
		switch($marketname)
		{
			case 'cryptsy':
				$cexcoin = new CExchangeCoinCryptsy($coin, $marketname);
				break;
		}

		$cexcoin->load();
		return $cexcoin;
*/
	}

};

/*

class CExchangeCoinCryptsy extends CExchangeCoin
{
	private $marketid;

	protected function load()
	{
		$ticker = getCryptsyTicker($market->marketid);
		if(!$ticker || !isset($ticker->return->$symbol->sellorders[0])) continue;

		///
	}

	protected function sell($amount, $price)
	{
		$res = cryptsy_api_query('createorder',
			array('marketid'=>$this->marketid, 'ordertype'=>'Sell', 'quantity'=>$amount, 'price'=>$price));
		if(!$res || !isset($res['orderid'])) return;

		$db_order = new db_orders;
		$db_order->market = 'cryptsy';
		$db_order->coinid = $this->coin->id;
		$db_order->amount = $balance;
		$db_order->price = $sellprice;
		$db_order->ask = $price;
		$db_order->bid = $price;
		$db_order->uuid = $res['orderid'];
		$db_order->created = time();
		$db_order->save();
	}

	protected function cancel($amount, $price)
	{
	}

};

*/

