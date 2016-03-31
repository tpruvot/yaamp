<?php

function BackendPricesUpdate()
{
//	debuglog(__FUNCTION__);

	updateBittrexMarkets();
	updatePoloniexMarkets();
	updateBleutradeMarkets();
	updateKrakenMarkets();
	updateCCexMarkets();
	updateCryptopiaMarkets();
	updateYobitMarkets();
	updateSafecexMarkets();
	updateAlcurexMarkets();
	updateBterMarkets();
	//updateEmpoexMarkets();
	updateCryptsyMarkets();
	updateJubiMarkets();
	updateBanxioMarkets();

	updateOtherMarkets();

	$list2 = getdbolist('db_coins', "installed AND IFNULL(symbol2,'') != ''");
	foreach($list2 as $coin2)
	{
		$coin = getdbosql('db_coins', "symbol='$coin2->symbol2'");
		if(!$coin) continue;

		$list = getdbolist('db_markets', "coinid=$coin->id");
		foreach($list as $market)
		{
			$market2 = getdbosql('db_markets', "coinid=$coin2->id and name='$market->name'");
			if(!$market2) continue;

			$market2->price = $market->price;
			$market2->price2 = $market->price2;
			$market2->deposit_address = $market->deposit_address;

			$market2->save();
		}
	}

	$coins = getdbolist('db_coins', "installed and id in (select distinct coinid from markets)");
	foreach($coins as $coin)
	{
		if($coin->symbol=='BTC')
		{
			$coin->price = 1;
			$coin->price2 = 1;

			$coin->save();
			continue;
		}

		$market = getBestMarket($coin);
		if($market)
		{
			$coin->price = $market->price*(1-YAAMP_FEES_EXCHANGE/100);
			$coin->price2 = $market->price2;

			$base_coin = !empty($market->base_coin)? getdbosql('db_coins', "symbol='$market->base_coin'"): null;
			if($base_coin)
			{
				$coin->price *= $base_coin->price;
				$coin->price2 *= $base_coin->price;
			}

//			if($market->name == 'c-cex')
//				$coin->price *= 0.95;
		}

		else
		{
			$coin->price = 0;
			$coin->price2 = 0;
		}

		$coin->save();
		dborun("UPDATE earnings SET price=$coin->price WHERE status!=2 AND coinid=$coin->id");
	}
}

function getBestMarket($coin)
{
	$market = NULL;
	if ($coin->symbol == 'BTC')
		return NULL;

	if (!empty($coin->symbol2)) {
		$alt = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$coin->symbol2));
		if ($alt)
			return getBestMarket($alt);
	}

	if (!empty($coin->market)) {
		// get coin market first (if set)
		if ($coin->market != 'BEST')
			$market = getdbosql('db_markets', "coinid=$coin->id AND price!=0 AND
				deposit_address IS NOT NULL AND deposit_address != '' AND name=:name",
				array(':name'=>$coin->market));
		else
		// else take one of the big exchanges...
			$market = getdbosql('db_markets', "coinid=$coin->id AND price!=0 AND
				deposit_address IS NOT NULL AND deposit_address != '' AND
				name IN ('poloniex','bittrex') ORDER BY price DESC");
	}
	if(!$market) {
		// else the best price
		$market = getdbosql('db_markets', "coinid=$coin->id AND price!=0 AND
			deposit_address IS NOT NULL AND deposit_address != ''
			AND	name!='safecex' AND name!='cryptsy' ORDER BY price DESC");
	}
	if(!$market) {
		$market = getdbosql('db_markets', "coinid=$coin->id AND price!=0 AND
			deposit_address IS NOT NULL AND deposit_address != ''
			ORDER BY price DESC");
	}

	if (!$market && empty($coin->market)) {
		debuglog("best market for {$coin->symbol} is unknown");
		$coin->market = 'unknown';
		$coin->save();
	}

	return $market;
}

function AverageIncrement($value1, $value2)
{
	$percent = 80;
	$value = ($value1*(100-$percent) + $value2*$percent) / 100;

	return $value;
}

///////////////////////////////////////////////////////////////////////////////////////////////////

function updateBleutradeMarkets()
{
	$exchange = 'bleutrade';
	$list = bleutrade_api_query('public/getcurrencies');
	if(!is_object($list)) return;

	foreach($list->result as $currency)
	{
	//	debuglog($currency);
		if($currency->Currency == 'BTC') continue;

		$coin = getdbosql('db_coins', "symbol='{$currency->Currency}'");
		if(!$coin || !$coin->installed) continue;

		$market = getdbosql('db_markets', "coinid=$coin->id and name='$exchange'");
		if(!$market)
		{
			$market = new db_markets;
			$market->coinid = $coin->id;
			$market->name = $exchange;
		}

		$market->txfee = $currency->TxFee;
		if(!$currency->IsActive)
		{
			$market->price = 0;
			$market->save();

			continue;
		}

		$market->save();
		$pair = "{$coin->symbol}_BTC";

		sleep(1);
		$ticker = bleutrade_api_query('public/getticker', "&market=$pair");
		if(!$ticker || !$ticker->success || !$ticker->result) continue;

		$price2 = ($ticker->result[0]->Bid+$ticker->result[0]->Ask)/2;
		$market->price2 = AverageIncrement($market->price2, $price2);
		$market->price = AverageIncrement($market->price, $ticker->result[0]->Bid);

		if(!empty(EXCH_BLEUTRADE_KEY))
		{
			$last_checked = cache()->get($exchange.'-deposit_address-check-'.$coin->symbol);
			if(empty($market->deposit_address) && !$last_checked)
			{
				sleep(1);
				$address = bleutrade_api_query('account/getdepositaddress', "&currency={$coin->symbol}");
				if(is_object($address) && is_object($address->result)) {
					$addr = $address->result->Address;
					if (!empty($addr) && $addr != $market->deposit_address) {
						$market->deposit_address = $addr;
						debuglog("$exchange: deposit address for {$coin->symbol} updated");
					}
				}
			}
			cache()->set($exchange.'-deposit_address-check-'.$coin->symbol, time(), 24*3600);
		}

		$market->save();

//		debuglog("$exchange: update $coin->symbol: $market->price $market->price2");
	}

}

/////////////////////////////////////////////////////////////////////////////////////////////

function updateKrakenMarkets($force = false)
{
	$exchange = 'kraken';
	$result = kraken_api_query('AssetPairs');
	if(!is_array($result)) return;

	foreach($result as $pair => $data)
	{
		$pairs = explode('-', $pair);
		$base = reset($pairs); $symbol = end($pairs);
		if($symbol == 'BTC' || $base != 'BTC') continue;
		if(in_array($symbol, array('GBP','CAD','EUR','USD','JPY'))) continue;
		if(strpos($symbol,'.d') !== false) continue;

		$coin = getdbosql('db_coins', "symbol='{$symbol}'");
		if(!$coin || !$coin->installed) continue;

		debuglog("kraken: $symbol/$base ".json_encode($data));
		$fees = reset($currency['fees']);
		$feepct = end($fees);
		$market = getdbosql('db_markets', "coinid={$coin->id} and name='{$exchange}'");
		if(!$market) {
			$market = new db_markets;
			$market->coinid = $coin->id;
			$market->name = $exchange;
		}

		$market->txfee = $feepct;

		sleep(1);
		$ticker = kraken_api_query('Ticker', $symbol);
		if(!is_array($ticker) || !isset($ticker[$pair])) continue;

		$ticker = arraySafeVal($ticker,$pair);
		if(!is_array($ticker) || !isset($ticker['b'])) continue;

		debuglog("kraken: $symbol/$base ticker ".json_encode($ticker));

		$price2 = ($ticker['b'][0] + $ticker['a'][0]) / 2;
		$market->price2 = AverageIncrement($market->price2, $price2);
		$market->price = AverageIncrement($market->price, $ticker['b'][0]);

		$market->save();
	}
}

/////////////////////////////////////////////////////////////////////////////////////////////

function updateBittrexMarkets($force = false)
{
	$exchange = 'bittrex';
	$list = bittrex_api_query('public/getcurrencies');
	if(!is_object($list)) return;

	foreach($list->result as $currency)
	{
		if($currency->Currency == 'BTC') continue;

		$coin = getdbosql('db_coins', "symbol='$currency->Currency'");
		if(!$coin || !$coin->installed) continue;

		$market = getdbosql('db_markets', "coinid=$coin->id and name='$exchange'");
		if(!$market) continue;

		$market->txfee = $currency->TxFee;
		$market->message = $currency->Notice;

		if(!$currency->IsActive)
		{
			$market->price = 0;
			$market->save();

			continue;
		}

		$market->save();
		$pair = "BTC-$coin->symbol";

		$ticker = bittrex_api_query('public/getticker', "&market=$pair");
		if(!$ticker || !$ticker->success || !$ticker->result) continue;

		// deposit address
		if(!empty(EXCH_BITTREX_KEY))
		{
			$last_checked = cache()->get($exchange.'-deposit_address-check-'.$coin->symbol);
			if($force || (empty($market->deposit_address) && !$last_checked))
			{
				$address = bittrex_api_query('account/getdepositaddress', "&currency={$coin->symbol}");
				if(is_object($address) && isset($address->result)) {
					$addr = $address->result->Address;
					if (!empty($addr) && $addr != $market->deposit_address) {
						$market->deposit_address = $addr;
						debuglog("$exchange: deposit address for {$coin->symbol} updated");
					}
				}
			}
			cache()->set($exchange.'-deposit_address-check-'.$coin->symbol, time(), 12*3600);
		}

		$price2 = ($ticker->result->Bid+$ticker->result->Ask)/2;
		$market->price2 = AverageIncrement($market->price2, $price2);
		$market->price = AverageIncrement($market->price, $ticker->result->Bid);

		$market->save();

//		debuglog("$exchange: update $coin->symbol: $market->price $market->price2");
	}
}

/////////////////////////////////////////////////////////////////////////////////////////////

function updateCryptsyMarkets()
{
	$exchange = 'cryptsy';
//	dborun("update markets set price=0 where name='$exchange'");
//	return;

	$markets = cryptsy_api_query('getmarkets');
	if(!$markets || !isset($markets['return']))
	{
		debuglog($markets);
		return;
	}

	$list = cryptsy_api_query('getcoindata');
	if(!$list || !isset($list['return']))
	{
		debuglog($list);
		return;
	}

	foreach($list['return'] as $currency)
	{
		if($currency['code'] == 'BTC') continue;
		$symbol = $currency['code'];

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if(!$coin || !$coin->installed) continue;

//		debuglog("$exchange:  $coin->name $symbol");

		$market = getdbosql('db_markets', "coinid=$coin->id and name='$exchange'");
		if(!$market)
		{
			$market = new db_markets;
			$market->coinid = $coin->id;
			$market->name = $exchange;

			foreach($markets['return'] as $item)
			{
				if($item['secondary_currency_code'] != 'BTC') continue;
				if($item['primary_currency_code'] != $symbol) continue;

				$market->marketid = $item['marketid'];
			}
		}

		if(empty($market->marketid))
		{
			foreach($markets['return'] as $item)
			{
				if($item['secondary_currency_code'] != 'BTC') continue;
				if($item['primary_currency_code'] != $symbol) continue;

				$market->marketid = $item['marketid'];
			}
		}

		// Cryptsy! Unfair methods...
		$currency['maintenancemode'] = 666;

		$market->txfee = $currency['withdrawalfee']*100;
		switch($currency['maintenancemode'])
		{
			case 0:
				$market->message = '';
				break;
			case 1:
				$market->message = 'Maintenance';
				break;
			case 2:
				$market->message = 'Updating Wallet';
				break;
			case 3:
				$market->message = 'Network Issues';
				break;
			case 666:
				$market->message = 'Funds holded';
				break;
			default:
				$market->message = 'Unknown Error';
				break;
		}

		$market->save();

		if($currency['maintenancemode']) {
			$market->price = 0;
			$market->save();
			continue;
		}

		$ticker = getCryptsyTicker($market->marketid);
		if(!$ticker) continue;

		if(!isset($ticker->return->$symbol->buyorders[0]))
		{
			debuglog("$exchange: error $coin->name id {$market->marketid}");
			if (isset($ticker->error))
				debuglog($ticker->error);
			else
				debuglog($ticker, 5);
			continue;
		}

		$price2 = $ticker->return->$symbol->buyorders[0]->price;
		if (isset($ticker->return->$symbol->sellorders))
			$price2 = ($price2 + $ticker->return->$symbol->sellorders[0]->price) / 2.0;

		$market->price2 = AverageIncrement($market->price2, $price2);
		$market->price = AverageIncrement($market->price, $ticker->return->$symbol->buyorders[0]->price);

		$market->save();

//		debuglog("cryptsy update $coin->symbol: $market->price $market->price2");
	}

	if(!empty(EXCH_CRYPTSY_KEY))
	{
		// deposit addresses
		$list = array();
		$last_checked = cache()->get($exchange.'-deposit_address-check');
		if (!$last_checked) {
			$list = cryptsy_api_query('getmydepositaddresses');
		}
		if (empty($list)) return;
		if (!is_array($list)) return;
		$success = arraySafeVal($list,'success',0);
		if (!$success || !is_array($list['return'])) return;

		foreach($list['return'] as $symbol => $address)
		{
			if($symbol == 'BTC') continue;

			$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
			if(!$coin) continue;

			$market = getdbosql('db_markets', "coinid={$coin->id} and name='$exchange'");
			if(!$market) continue;

			if (!is_string($address))
				debuglog("$exchange: $symbol deposit address format error ".json_encode($address));
			else if (!empty($address) && $market->deposit_address != $address) {
				$market->deposit_address = $address;
				$market->save();
				debuglog("$exchange: deposit address for $symbol updated");
			}
		}
		cache()->set($exchange.'-deposit_address-check', time(), 12*3600);
	}
}

////////////////////////////////////////////////////////////////////////////////////

function updateCCexMarkets()
{
	$exchange = 'c-cex';
//	dborun("update markets set price=0 where name='c-cex'");	<- add that line
	$ccex = new CcexAPI;

	$list = $ccex->getPairs();
	if (!is_array($list)) return;

	foreach($list as $item)
	{
		$e = explode('-', $item);
		if(!isset($e[1])) continue;
		if($e[1] != 'btc') continue;

		$symbol = strtoupper($e[0]);

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if(!$coin || !$coin->installed) continue;

		$market = getdbosql('db_markets', "coinid={$coin->id} and name='$exchange'");
		if(!$market)
		{
			// is that required ?
			$market = new db_markets;
			$market->coinid = $coin->id;
			$market->name = $exchange;
		}

		$market->save();

		sleep(1);
		$ticker = $ccex->getTickerInfo($item);
		if(!$ticker) continue;

		$price2 = ($ticker['buy']+$ticker['sell'])/2;

		$market->price2 = AverageIncrement($market->price2, $price2);
		$market->price = AverageIncrement($market->price, $ticker['buy']);

		$market->save();

		if (empty($coin->price2)) {
			$coin->price = $market->price;
			$coin->price2 = $market->price2;
			$coin->save();
		}

		if(!empty(EXCH_CCEX_SECRET))
		{
			$last_checked = cache()->get($exchange.'-deposit_address-check-'.$coin->symbol);
			if(empty($market->deposit_address) && !$last_checked)
			{
				sleep(1);
				$address = $ccex->getDepositAddress($coin->symbol);
				if(!empty($address)) {
					$addr = arraySafeVal($address,'return');
					if (!empty($addr) && $addr != $market->deposit_address) {
						if (strpos($addr, 'Error') !== false)
							$market->message = $addr;
						else {
							$market->deposit_address = $addr;
							$market->message = null;
							debuglog("$exchange: deposit address for {$coin->symbol} updated");
						}
						$market->save();
					}
				}
			}
			cache()->set($exchange.'-deposit_address-check-'.$coin->symbol, time(), 24*3600);
		}

//		debuglog("$exchange: update $coin->symbol: $market->price $market->price2");
	}
}

////////////////////////////////////////////////////////////////////////////////////

function updatePoloniexMarkets()
{
	$exchange = 'poloniex';
	$poloniex = new poloniex;

	$tickers = $poloniex->get_ticker();
	if(!is_array($tickers)) return;

	foreach($tickers as $symbol=>$ticker)
	{
		$a = explode('_', $symbol);
		if(!isset($a[1])) continue;
		if($a[0] != 'BTC') continue;

		$symbol = $a[1];

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if(!$coin || !$coin->installed) continue;

		$market = getdbosql('db_markets', "coinid=$coin->id and name='poloniex'");
		if(!$market)
		{
			// is that required ?
			$market = new db_markets;
			$market->coinid = $coin->id;
			$market->name = 'poloniex';
		}

		$price2 = ($ticker['highestBid']+$ticker['lowestAsk'])/2;
		$market->price2 = AverageIncrement($market->price2, $price2);
		$market->price = AverageIncrement($market->price, $ticker['highestBid']);

		$market->save();

		if(empty($market->deposit_address) && $coin->installed && !empty(EXCH_POLONIEX_KEY)) {
			if ($coin->symbol != 'EXE')
				$poloniex->generate_address($coin->symbol);
		}

//		debuglog("$exchange: update $coin->symbol: $market->price $market->price2");
	}

	// deposit addresses
	if(!empty(EXCH_POLONIEX_KEY))
	{
		$list = array();
		$last_checked = cache()->get($exchange.'-deposit_address-check');
		if (!$last_checked) {
			$list = $poloniex->get_deposit_addresses();
			if (!is_array($list)) return;
		}

		foreach($list as $symbol=>$item)
		{
			if($symbol == 'BTC') continue;

			$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
			if(!$coin) continue;

			$market = getdbosql('db_markets', "coinid=$coin->id and name='poloniex'");
			if(!$market) continue;

			if ($market->deposit_address != $item) {
				$market->deposit_address = $item;
				$market->save();
				debuglog("$exchange: deposit address for {$coin->symbol} updated");
			}
		}
		cache()->set($exchange.'-deposit_address-check', time(), 12*3600);
	}
}

////////////////////////////////////////////////////////////////////////////////////

function updateYobitMarkets()
{
	$exchange = 'yobit';
	$res = yobit_api_query('info');
	if(!is_object($res)) return;

	foreach($res->pairs as $i=>$item)
	{
		$e = explode('_', $i);
		$symbol = strtoupper($e[0]);
		if($e[1] != 'btc') continue;
		if($symbol == 'BTC') continue;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if(!$coin || !$coin->installed) continue;

		$market = getdbosql('db_markets', "coinid={$coin->id} and name='$exchange'");
		if(!$market)
		{
			// is that required ?
			$market = new db_markets;
			$market->coinid = $coin->id;
			$market->name = $exchange;
		}

		$pair = strtolower($coin->symbol).'_btc';

		$ticker = yobit_api_query("ticker/$pair");
		if(!$ticker) continue;

		if(!empty(EXCH_YOBIT_KEY))
		{
			$last_checked = cache()->get($exchange.'-deposit_address-check-'.$coin->symbol);
			if ($last_checked) continue;

			sleep(1); // for the tapi nonce
			$address = yobit_api_query2('GetDepositAddress', array("coinName"=>$coin->symbol));
			if (!empty($address) && isset($address['return']) && $address['success']) {
				$addr = $address['return']['address'];
				if (!empty($addr) && $addr != $market->deposit_address) {
					$market->deposit_address = $addr;
					debuglog("$exchange: deposit address for {$coin->symbol} updated");
				}
			}
			cache()->set($exchange.'-deposit_address-check-'.$coin->symbol, time(), 24*3600);
		}

		$price2 = ($ticker->$pair->buy + $ticker->$pair->sell) / 2;
		$market->price2 = AverageIncrement($market->price2, $price2);
		$market->price = AverageIncrement($market->price, $ticker->$pair->buy);

		$market->save();
	}
}

// http://www.jubi.com/ ?
function updateJubiMarkets()
{
	$exchange = 'jubi';
	$btc = jubi_api_query('ticker', "?coin=btc");
	if(!is_object($btc)) return;

	$list = getdbolist('db_markets', "name='jubi'");
	foreach($list as $market)
	{
		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) continue;

		$lowsymbol = strtolower($coin->symbol);

		$ticker = jubi_api_query('ticker', "?coin=".$lowsymbol);
		if(!is_object($ticker)) continue;

		if (isset($btc->sell) && $btc->sell != 0.)
			$ticker->buy /= $btc->sell;
		if (isset($btc->buy) && $btc->buy != 0.)
			$ticker->sell /= $btc->buy;

		$price2 = ($ticker->buy+$ticker->sell)/2;
		$market->price2 = AverageIncrement($market->price2, $price2);
		$market->price = AverageIncrement($market->price, $ticker->buy*0.95);

//		debuglog("jubi update $coin->symbol: $market->price $market->price2");

		$market->save();
	}
}

function updateAlcurexMarkets()
{
	$exchange = 'alcurex';
	$data = alcurex_api_query('market', "?info=on");
	if(!is_object($data)) return;

	$list = getdbolist('db_markets', "name='$exchange'");
	foreach($list as $market)
	{
		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin || !$coin->installed) continue;

		$pair = strtoupper($coin->symbol).'_BTC';
		foreach ($data->MARKETS as $ticker) {
			if ($ticker->Pair === $pair) {
				$lpair = strtolower($pair);
				$last = alcurex_api_query('market', "?pair=$lpair&last=last");
				if (is_object($last) && !empty($last->$lpair)) {
					$last = $last->$lpair;
					$market->price = AverageIncrement($market->price, $last->price);
					$market->save();
				}
				$last = alcurex_api_query('market', "?pair=$lpair&last=sell");
				if (is_object($last) && !empty($last->$lpair)) {
					$last = $last->$lpair;
					$market->price2 = AverageIncrement($market->price2, $last->price);
					$market->save();
				}
				if (empty($coin->price)) {
					$coin->price = $market->price;
					$coin->price2 = $market->price2;
					$coin->save();
				}
//				debuglog("alcurex: $pair $market->price ".bitcoinvaluetoa($market->price2));
			}
		}
	}
}

function updateCryptopiaMarkets()
{
	$exchange = 'cryptopia';
	$data = cryptopia_api_query('GetMarkets', 24);
	if(!is_object($data)) return;

	$list = getdbolist('db_markets', "name='$exchange'");
	foreach($list as $market)
	{
		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin || !$coin->installed) continue;

		$pair = strtoupper($coin->symbol).'/BTC';

		foreach ($data->Data as $ticker) {
			if ($ticker->Label === $pair) {

				$price2 = ($ticker->BidPrice+$ticker->AskPrice)/2;
				$market->price2 = AverageIncrement($market->price2, $price2);
				$market->price = AverageIncrement($market->price, $ticker->BidPrice*0.98);
				$market->marketid = $ticker->TradePairId;
				$market->save();
				if (empty($coin->price)) {
					$coin->price = $market->price;
					$coin->price2 = $market->price2;
					$coin->save();
				}
//				debuglog("$exchange: $pair $market->price ".bitcoinvaluetoa($market->price2));
				break;
			}
		}
	}

	if(empty(EXCH_CRYPTOPIA_KEY)) return;

	$last_checked = cache()->get($exchange.'-deposit_address-check');
	if ($last_checked) return;

	$addresses = array();
	$query = cryptopia_api_user('GetBalance');
	if (is_object($query) && is_array($query->Data))
	foreach($query->Data as $balance) {
		$addresses[$balance->Symbol] = $balance->Address;
	}

	if (!empty($addresses))
	foreach($list as $market) {
		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) continue;

		if (isset($addresses[$coin->symbol])) {
			$addr = $addresses[$coin->symbol];
			if ($market->deposit_address != $addr) {
				debuglog("$exchange: deposit address for {$coin->symbol} updated");
				$market->deposit_address = $addr;
				$market->save();
			}
		}
	}
	cache()->set($exchange.'-deposit_address-check', time(), 12*3600);
}

function updateBanxioMarkets()
{
	$exchange = 'banx';
	$data = banx_public_api_query('getmarketsummaries');
	if(!$data || !is_array($data->result)) return;

	$symbols = array();

	$currencies = getdbolist('db_markets', "name='banx'");
	foreach($currencies as $market)
	{
		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin || !$coin->installed) continue;

		$pair = strtoupper($coin->symbol).'-BTC';
		foreach ($data->result as $ticker) {
			if ($ticker->marketname === $pair) {
				$market->price = AverageIncrement($market->price, $ticker->bid);
				$market->price2 = AverageIncrement($market->price2, $ticker->last);
//				debuglog("banx: $pair {$ticker->bid} {$ticker->last} => {$market->price} {$market->price2}");
				if (intval($ticker->dayvolume) > 1)
					$market->save();
				if (empty($coin->price2)) {
					$coin->price = $market->price;
					$coin->price2 = $market->price2;
					$coin->save();
				}
				if ($coin->name == 'unknown' && !empty($ticker->currencylong)) {
					$coin->name = $ticker->currencylong;
					$coin->save();
					debuglog("$exchange: update $symbol label {$coin->name}");
				}
				// store for deposit addresses
				$symbols[$ticker->currencylong] = $coin->symbol;
				$symbols[$ticker->partnerlong] = $coin->symbol;
				break;
			}
		}
	}

	if(!empty(EXCH_BANX_USERNAME))
	{
		// deposit addresses
		$last_checked = cache()->get($exchange.'-deposit_address-check');
		if (!$last_checked) {
			// no coin symbols in the results wtf ! only labels :/
			sleep(1);
			$query = banx_api_user('account/getdepositaddresses');
		}
		if (!isset($query)) return;
		if (!is_object($query)) return;
		if (!$query->success || !is_array($query->result)) return;

		foreach($query->result as $account)
		{
			if (!isset($account->currency) || !isset($account->address)) continue;
			if (empty($account->currency) || empty($account->address)) continue;

			$label = $account->currency;
			if (!isset($symbols[$label])) continue;

			$symbol = $symbols[$label];

			if($symbol == 'BTC') continue;

			$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
			if(!$coin) continue;

			$market = getdbosql('db_markets', "coinid={$coin->id} and name='$exchange'");
			if(!$market) continue;

			if ($market->deposit_address != $account->address) {
				$market->deposit_address = $account->address;
				$market->save();
				debuglog("$exchange: deposit address for $symbol updated");
			}
		}
		cache()->set($exchange.'-deposit_address-check', time(), 12*3600);
	}
}

function updateSafecexMarkets()
{
	$exchange = 'safecex';
	$data = safecex_api_query('getmarkets');
	if(empty($data)) return;

	$list = getdbolist('db_markets', "name='$exchange'");
	foreach($list as $market)
	{
		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin || !$coin->installed) continue;

		$pair = strtoupper($coin->symbol).'/BTC';

		foreach ($data as $ticker) {
			if ($ticker->market === $pair) {

				$price2 = ($ticker->bid + $ticker->ask)/2;
				$market->price2 = AverageIncrement($market->price2, $price2);
				$market->price = AverageIncrement($market->price, $ticker->bid*0.98);
				$market->save();

				if (empty($coin->price)) {
					$coin->price = $market->price;
					$coin->price2 = $market->price2;
					$coin->save();
				}

				if (empty(EXCH_SAFECEX_KEY)) continue;

				// deposit addresses (in getbalances api)
				$last_checked = cache()->get($exchange.'-deposit_address-check');
				if (empty($market->deposit_address) || isset($getbalances_called) || !$last_checked) {
					// note: will try to get all missing installed coins deposit address
					//       but these addresses are not automatically created on safecex.
					if (!isset($getbalances_called)) {
						// only one query is enough
						$balances = safecex_api_user('getbalances');
						$getbalances_called = true;
						// allow to check once all new/changed deposit addresses (in 2 job loops)
						if (dborun("UPDATE markets SET deposit_address=NULL WHERE name='$exchange' AND deposit_address=' '"))
							$need_new_loop = true;
						cache()->set($exchange.'-deposit_address-check', time(), 12*3600); // recheck all in 12h
					}
					if(is_array($balances)) foreach ($balances as $balance) {
						if ($balance->symbol == $coin->symbol) {
							if (!isset($balance->deposit)) break;
							if (empty(trim($market->deposit_address))) {
								$market->deposit_address = $balance->deposit;
								$market->save();
								debuglog("$exchange: {$coin->symbol} deposit address imported");
							} else if (trim($market->deposit_address) != $balance->deposit) {
								$market->deposit_address = $balance->deposit;
								$market->save();
								debuglog("$exchange: {$coin->symbol} deposit address was wrong, updated.");
							}
						}
					}
				}
			}
		}
	}

	if (isset($getbalances_called))
	{
		// update btc balance too btw
		if (is_array($balances)) foreach ($balances as $balance) {
			if ($balance->symbol == 'BTC') {
				$balance = floatval($balance->balance);
				dborun("UPDATE balances SET balance=$balance WHERE name='$exchange'");
			}
		}

		// prevent api calls each 15mn for deposit addresses
		// will be rechecked on new coins or if a market address is empty (or forced in 12 hours)
		$list = dbolist("SELECT C.symbol AS symbol FROM markets M INNER JOIN coins C on M.coinid = C.id " .
			"WHERE M.name='$exchange' AND C.installed AND IFNULL(M.deposit_address,'')='' ORDER BY symbol");
		$missing = array();
		foreach($list as $row) {
			$missing[] = $row['symbol'];
		}
		if(!empty($missing) && !isset($need_new_loop)) {
			debuglog("$exchange: no deposit address found for ".implode(',',$missing));
			// stop asking safecex for inexistant deposit addresses (except on new coin or empty address, or 12h)
			dborun("UPDATE markets SET deposit_address=' ' WHERE name='$exchange' AND IFNULL(deposit_address,'')=''");
		}
	}
}

function updateBterMarkets()
{
	$exchange = 'bter';
	$markets = bter_api_query('tickers');
	if(!is_array($markets)) return;

	$list = getdbolist('db_markets', "name='$exchange'");
	foreach($list as $market)
	{
		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin || !$coin->installed) continue;

		$lowsymbol = strtolower($coin->symbol);
		$dbpair = $lowsymbol.'_btc';
		foreach ($markets as $pair => $ticker) {
			if ($pair != $dbpair) continue;

			$market->price = AverageIncrement($market->price, $ticker['buy']);
			$market->price2 = AverageIncrement($market->price2, $ticker['avg']);
			$market->deleted = (floatval($ticker['vol_btc']) < 0.01);
			$market->save();

			if (empty($coin->price2)) {
				$coin->price = $market->price;
				$coin->price2 = $market->price2;
				$coin->save();
			}
		}
	}
}

function updateEmpoexMarkets()
{
	$exchange = 'empoex';
	$markets = empoex_api_query('marketinfo');
	if(!is_array($markets)) return;

	$list = getdbolist('db_markets', "name='$exchange'");
	foreach($list as $market)
	{
		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin || !$coin->installed) continue;

		$pair = strtoupper($coin->symbol).'-BTC';

		foreach ($markets as $ticker) {
			if ($ticker->pairname != $pair) continue;

			$market->price = AverageIncrement($market->price, $ticker->bid);
			$market->price2 = AverageIncrement($market->price2, $ticker->ask);

			if (floatval($ticker->base_volume_24hr) > 0.01)
				$market->save();

			if (empty($coin->price2)) {
				$coin->price = $market->price;
				$coin->price2 = $market->price2;
				$coin->market = 'empoex';
				$coin->save();
			}
		}
	}
}

// update other installed coins price from cryptonator
function updateOtherMarkets()
{
	$coins = getdbolist('db_coins', "installed AND IFNULL(price,0.0) = 0.0");
	foreach($coins as $coin)
	{
		$symbol = $coin->symbol;
		if (!empty($coin->symbol2)) $symbol = $coin->symbol2;

		$json = @ file_get_contents("https://www.cryptonator.com/api/full/".strtolower($symbol)."-btc");
		$object = json_decode($json);
		if (empty($object)) continue;

		if (is_object($object) && isset($object->ticker)) {
			$ticker = $object->ticker;
			if ($ticker->target == 'BTC' && $ticker->volume > 1) {
				$coin->price2 = $ticker->price;
				$coin->price  = AverageIncrement((float)$coin->price, (float)$coin->price2);
				if ($coin->save()) {
					debuglog("update price of $symbol ".bitcoinvaluetoa($coin->price));
				}
			}
		}
	}
}
