<?php

function updateRawcoins()
{
//	debuglog(__FUNCTION__);

	exchange_set_default('empoex', 'disabled', true);
	exchange_set_default('coinexchange', 'disabled', true);

	settings_prefetch_all();

	if (!exchange_get('bittrex', 'disabled')) {
		$list = bittrex_api_query('public/getcurrencies');
		if(isset($list->result))
		{
			dborun("UPDATE markets SET deleted=true WHERE name='bittrex'");
			foreach($list->result as $currency)
				updateRawCoin('bittrex', $currency->Currency, $currency->CurrencyLong);
		}
	}

	if (!exchange_get('bleutrade', 'disabled')) {
		$list = bleutrade_api_query('public/getcurrencies');
		if(isset($list->result))
		{
			dborun("UPDATE markets SET deleted=true WHERE name='bleutrade'");
			foreach($list->result as $currency)
				updateRawCoin('bleutrade', $currency->Currency, $currency->CurrencyLong);
		}
	}

	if (!exchange_get('poloniex', 'disabled')) {
		$poloniex = new poloniex;
		$tickers = $poloniex->get_currencies();
		if (!$tickers)
			$tickers = array();
		else
			dborun("UPDATE markets SET deleted=true WHERE name='poloniex'");
		foreach($tickers as $symbol=>$ticker)
		{
			if(arraySafeVal($ticker,'disabled')) continue;
			if(arraySafeVal($ticker,'delisted')) continue;
			updateRawCoin('poloniex', $symbol);
		}
	}

	if (!exchange_get('c-cex', 'disabled')) {
		$ccex = new CcexAPI;
		$list = $ccex->getPairs();
		if($list)
		{
			sleep(1);
			$names = $ccex->getCoinNames();

			dborun("UPDATE markets SET deleted=true WHERE name='c-cex'");
			foreach($list as $item)
			{
				$e = explode('-', $item);
				$symbol = strtoupper($e[0]);

				updateRawCoin('c-cex', $symbol, arraySafeVal($names, $e[0], 'unknown'));
			}
		}
	}

	if (!exchange_get('bter', 'disabled')) {
		$list = bter_api_query('marketlist');
		if(is_object($list) && is_array($list->data))
		{
			dborun("UPDATE markets SET deleted=true WHERE name='bter'");
			foreach($list->data as $item) {
				if (strtoupper($item->curr_b) !== 'BTC')
					continue;
				if (strpos($item->name, 'Asset') !== false)
					continue;
				if (strpos($item->name, 'BitShares') !== false && $item->symbol != 'BTS')
					continue;
				// ignore some dead coins and assets
				if (in_array($item->symbol, array('BITGLD','DICE','ROX','TOKEN')))
					continue;
				updateRawCoin('bter', $item->symbol, $item->name);
			}
		}
	}

	if (!exchange_get('yobit', 'disabled')) {
		$res = yobit_api_query('info');
		if($res)
		{
			dborun("UPDATE markets SET deleted=true WHERE name='yobit'");
			foreach($res->pairs as $i=>$item)
			{
				$e = explode('_', $i);
				$symbol = strtoupper($e[0]);
				updateRawCoin('yobit', $symbol);
			}
		}
	}

	if (!exchange_get('coinexchange', 'disabled')) {
		$list = coinexchange_api_query('getmarkets');
		if(isset($list->result) && !empty($list->result))
		{
			dborun("UPDATE markets SET deleted=true WHERE name='coinexchange'");
			foreach($list->result as $item) {
				if ($item->BaseCurrencyCode != 'BTC')
					continue;
				$symbol = $item->MarketAssetCode;
				$label = objSafeVal($item, 'MarketAssetName');
				updateRawCoin('coinexchange', $symbol, $label);
			}
		}
	}

	if (!exchange_get('cryptopia', 'disabled')) {
		$list = cryptopia_api_query('GetMarkets');
		if(isset($list->Data))
		{
			dborun("UPDATE markets SET deleted=true WHERE name='cryptopia'");
			foreach($list->Data as $item) {
				$e = explode('/', $item->Label);
				if (strtoupper($e[1]) !== 'BTC')
					continue;
				$symbol = strtoupper($e[0]);
				updateRawCoin('cryptopia', $symbol);
			}
		}
	}

	if (!exchange_get('kraken', 'disabled')) {
		$list = kraken_api_query('AssetPairs');
		if(is_array($list))
		{
			dborun("UPDATE markets SET deleted=true WHERE name='kraken'");
			foreach($list as $pair => $item) {
				$pairs = explode('-', $pair);
				$base = reset($pairs); $symbol = end($pairs);
				if($symbol == 'BTC' || $base != 'BTC') continue;
				if(in_array($symbol, array('GBP','CAD','EUR','USD','JPY'))) continue;
				if(strpos($symbol,'.d') !== false) continue;
				$symbol = strtoupper($symbol);
				updateRawCoin('kraken', $symbol);
			}
		}
	}

	if (!exchange_get('alcurex', 'disabled')) {
		$list = alcurex_api_query('market','?info=on');
		if(is_object($list) && isset($list->MARKETS))
		{
			dborun("UPDATE markets SET deleted=true WHERE name='alcurex'");
			foreach($list->MARKETS as $item) {
				$e = explode('_', $item->Pair);
				$symbol = strtoupper($e[0]);
				updateRawCoin('alcurex', $symbol);
			}
		}
	}

	if (!exchange_get('nova', 'disabled')) {
		$list = nova_api_query('markets');
		if(is_object($list) && !empty($list->markets))
		{
			dborun("UPDATE markets SET deleted=true WHERE name='nova'");
			foreach($list->markets as $item) {
				if ($item->basecurrency != 'BTC')
					continue;
				$symbol = strtoupper($item->currency);
				updateRawCoin('nova', $symbol);
				//debuglog("nova: $symbol");
			}
		}
	}

	if (!exchange_get('empoex', 'disabled')) {
		$list = empoex_api_query('marketinfo');
		if(is_array($list))
		{
			dborun("UPDATE markets SET deleted=true WHERE name='empoex'");
			foreach($list as $item) {
				$e = explode('-', $item->pairname);
				$base = strtoupper($e[1]);
				if ($base != 'BTC')
					continue;
				$symbol = strtoupper($e[0]);
				updateRawCoin('empoex', $symbol);
			}
		}
	}

	if (!exchange_get('livecoin', 'disabled')) {
		$list = livecoin_api_query('exchange/ticker');
		if(is_array($list))
		{
			dborun("UPDATE markets SET deleted=true WHERE name='livecoin'");
			foreach($list as $item) {
				$e = explode('/', $item->symbol);
				$base = strtoupper($e[1]);
				if ($base != 'BTC')
					continue;
				$symbol = strtoupper($e[0]);
				updateRawCoin('livecoin', $symbol);
			}
		}
	}

	if (!exchange_get('shapeshift', 'disabled')) {
		$list = shapeshift_api_query('getcoins');
		if(is_array($list) && !empty($list))
		{
			dborun("UPDATE markets SET deleted=true WHERE name='shapeshift'");
			foreach($list as $item) {
				$status = $item['status'];
				if ($status != 'available') continue;
				$symbol = strtoupper($item['symbol']);
				$name = trim($item['name']);
				updateRawCoin('shapeshift', $symbol, $name);
				//debuglog("shapeshift: $symbol $name");
			}
		}
	}

	//////////////////////////////////////////////////////////

	$markets = dbocolumn("SELECT DISTINCT name FROM markets");
	foreach ($markets as $exchange) {
		if (exchange_get($exchange, 'disabled')) {
			$res = dborun("UPDATE markets SET disabled=8 WHERE name=:name", array(':name'=>$exchange));
			if ($res) debuglog("$exchange: $res markets disabled from db settings");
		}
	}

	dborun("DELETE FROM markets WHERE deleted");

	$list = getdbolist('db_coins', "not enable and not installed and id not in (select distinct coinid from markets)");
	foreach($list as $coin)
	{
		if ($coin->visible)
			debuglog("{$coin->symbol} is no longer active");
	// todo: proper cleanup in all tables (like "yiimp deletecoin <id>")
	//	if ($coin->symbol != 'BTC')
	//		$coin->delete();
	}
}

function updateRawCoin($marketname, $symbol, $name='unknown')
{
	if($symbol == 'BTC') return;

	$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
	if(!$coin && $marketname != 'yobit')
	{
		$algo = '';
		if ($marketname == 'cryptopia') {
			// get coin label and algo (different api)
			$labels = cryptopia_api_query('GetCurrencies');
			if (is_object($labels) && !empty($labels->Data)) {
				foreach ($labels->Data as $coin) {
					if ($coin->Symbol == $symbol) {
						$name = $coin->Name;
						$algo = strtolower($coin->Algorithm);
						break;
					}
				}
			}
		}

		if ($marketname == 'nova' && !defined('EXCH_NOVA_KEY')) {
			return;
		}
		if ($marketname == 'askcoin' || $marketname == 'coinexchange') {
			// don't polute too much the db
			return;
		}

		if (market_get($marketname, $symbol, "disabled")) {
			return;
		}

		debuglog("new coin $marketname $symbol $name");

		$coin = new db_coins;
		$coin->txmessage = true;
		$coin->hassubmitblock = true;
		$coin->name = $name;
		$coin->algo = $algo;
		$coin->symbol = $symbol;
		$coin->created = time();
		$coin->save();

		$url = getMarketUrl($coin, $marketname);
		if (YAAMP_NOTIFY_NEW_COINS)
			mail(YAAMP_ADMIN_EMAIL, "New coin $symbol", "new coin $symbol ($name) on $marketname\r\n\r\n$url");
		sleep(30);
	}

	else if($coin && $coin->name == 'unknown' && $name != 'unknown')
	{
		$coin->name = $name;
		$coin->save();
	}

	$list = getdbolist('db_coins', "symbol=:symbol or symbol2=:symbol", array(':symbol'=>$symbol));
	foreach($list as $coin)
	{
		$market = getdbosql('db_markets', "coinid=$coin->id and name='$marketname'");
		if(!$market)
		{
			$market = new db_markets;
			$market->coinid = $coin->id;
			$market->name = $marketname;
		}

		$market->deleted = false;
		$market->save();
	}

}

