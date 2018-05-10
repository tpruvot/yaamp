<?php

//	$order
//		orderid
//		price
//		amount
//		marketid (cryptsy)
//

class CExchange
{
	protected $marketname;

	protected $orders;
	protected $balances;

	protected $balance_btc;
	protected $balance_ltc;

	abstract protected function loadOrders();
	abstract protected function loadBalances();
	abstract protected function withdraw();

	protected function get_mintrade() {return 0.00050000;}
	protected function get_minwithdraw() {return 0.25000000;}

	public function __construct($marketname)
	{
		$this->marketname = $marketname;
	}

	public function doTrading()
	{
		$this->orders = $this->loadOrders();
		foreach($this->orders as $order)
		{
			$cexcoin = new CExchangeCoin($order->coin, $this->marketname);
			// cancel if too high
			// add to our db if not there already
		}

		$list = getdbolist('db_orders', "market='{$this->marketname}'");
		foreach($list as $db_order)
		{
			$found = false;
			foreach($this->orders as $order)
			{
				if($order->orderid == $db_order->uuid)
				{
					$found = true;
					break;
				}
			}

			if(!$found)
			{
				debuglog("$this->marketname deleting order");
				$db_order->delete();
			}
		}

		$savebalance = getdbosql('db_balances', "name='{$this->marketname}'");
		$savebalance->balance = 0;

		$this->balances = $this->loadBalances();
		foreach($this->balances as $balance)
		{
			if($balance->amount <= 0) continue;
			$cexcoin = new CExchangeCoin($balance->coin, $this->marketname);

			foreach($cexcoin->bids as $bid)
			{
				if($balance->amount*1.5 < $bid->amount && !$coin->sellonbid) break;

				$sellamount = min($balance->amount, $bid->price);
				if($sellamount*$bid->price < $this->get_mintrade()) continue;

				$cex->sell($sellamount, $bid->price);
				$balance->amount -= $sellamount;

				sleep(1);
			}

			$cexcoin = new CExchangeCoin($balance->coin, $this->marketname);
			if($balance->amount*$cexcoin->ask < $this->get_mintrade()) continue;

			$cex->sell($balance->amount, $cexcoin->ask);
			sleep(1);
		}

		if($this->balance_btc >= $this->get_minwithdraw())
		{
			debuglog("withdraw $this->marketname $this->balance_btc");
			$this->withdraw($this->balance_btc);
		}
	}

};

////////////////////////////////////////////////////////////////////////////////////////////////

/*

class CExchangeCryptsy extends CExchange
{
	protected function loadOrders()
	{
		$ordertab = array();

		$orders = cryptsy_api_query('allmyorders');
		if(!$orders) return $ordertab;
		if(!isset($orders['return'])) return $ordertab;

		foreach($orders['return'] as $order)
		{
			if(!isset($order['marketid'])) continue;
			if(!isset($order['orderid'])) continue;

			$object = new object();
			$object->orderid = $order['orderid'];
			$object->price = $order['price'];
			$object->amount = $order['quantity'];
			$object->marketid = $order['marketid'];

			$market = getdbosql('db_markets', "marketid=$object->marketid");
			if(!$market) continue;

			$object->coin = getdbo('db_coins', $market->coinid);
			if(!$object->coin) continue;

			$ordertab[] = $object;
		}

		return $ordertab;
	}

	protected function loadBalances()
	{
		$balancetab = array();

		$balances = cryptsy_api_query('getinfo');
		if(!$balances) return;
		if(!isset($balances['return']))
		{
			debuglog($balances);
			return $balancetab;
		}

		foreach($balances['return']['balances_available'] as $symbol=>$balance)
		{
			$balance = floatval($balance);

			if($symbol == 'Points') continue;
			if($symbol == 'BTC')
			{
				$this->balance_btc = floatval($balance);
				continue;
			}

			if($symbol == 'LTC')
			{
				$this->balance_ltc = floatval($balance);
				continue;
			}

			if(!$balance) continue;

			$object = new object();
			$object->balance = $balance;

			$object->coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
			if(!$object->coin) continue;

			$balancetab[] = $object;
		}

		return $balancetab;
	}

	protected function withdraw($amount)
	{
		$btcaddr = YAAMP_BTCADDRESS; //'14LS7Uda6EZGXLtRrFEZ2kWmarrxobkyu9';
		$res = cryptsy_api_query('makewithdrawal', array('address'=>$btcaddr, 'amount'=>$amount));
		debuglog($res);

		if($res && $res['success'])
		{
			$withdraw = new db_withdraws;
			$withdraw->market = 'cryptsy';
			$withdraw->address = $btcaddr;
			$withdraw->amount = $amount;
			$withdraw->time = time();
			$withdraw->save();
		}
	}

};

*/

