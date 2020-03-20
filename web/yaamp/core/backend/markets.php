<?php
function BackendPricesUpdate()
{
    //	debuglog(__FUNCTION__);
    market_set_default('c-cex', 'DCR', 'disabled', true); // no deposit
    market_set_default('yobit', 'DCR', 'disabled', true); // no withdraw
    settings_prefetch_all();

    updateBittrexMarkets();
    updateBitzMarkets();
    updatePoloniexMarkets();
    updateBleutradeMarkets();
    updateCryptoBridgeMarkets();
    updateEscoDexMarkets();
    updateGateioMarkets();
    updateGraviexMarkets();
    updateKrakenMarkets();
    updateKuCoinMarkets();
    updateCCexMarkets();
    updateCoinbeneMarkets();
    updateCrex24Markets();
    updateCryptopiaMarkets();
    updateHitBTCMarkets();
    updateYobitMarkets();
    updateAlcurexMarkets();
    updateBinanceMarkets();
    //updateEmpoexMarkets();
    updateJubiMarkets();
    updateLiveCoinMarkets();
    updateNovaMarkets();
    updateCoinExchangeMarkets();
    updateCoinsMarketsMarkets();
    updateStocksExchangeMarkets();
    updateTradeSatoshiMarkets();

    updateShapeShiftMarkets();
    updateOtherMarkets();

    $list2 = getdbolist('db_coins', "installed AND IFNULL(symbol2,'') != ''");
    foreach ($list2 as $coin2)
    {
        $coin = getdbosql('db_coins', "symbol='$coin2->symbol2'");
        if (!$coin) continue;

        $list = getdbolist('db_markets', "coinid=$coin->id");
        foreach ($list as $market)
        {
            $market2 = getdbosql('db_markets', "coinid=$coin2->id and name='$market->name'");
            if (!$market2) continue;

            $market2->price = $market->price;
            $market2->price2 = $market->price2;
            $market2->deposit_address = $market->deposit_address;
            $market2->pricetime = $market->pricetime;

            $market2->save();
        }
    }

    $coins = getdbolist('db_coins', "installed and id in (select distinct coinid from markets)");
    foreach ($coins as $coin)
    {
        if ($coin->symbol == 'BTC')
        {
            $coin->price = 1;
            $coin->price2 = 1;
            $coin->save();
            continue;
        }

        $market = getBestMarket($coin);
        if ($market)
        {
            $coin->price = $market->price * (1 - YAAMP_FEES_EXCHANGE / 100);
            $coin->price2 = $market->price2;

            $base_coin = !empty($market->base_coin) ? getdbosql('db_coins', "symbol='{$market->base_coin}'") : null;
            if ($base_coin)
            {
                $coin->price *= $base_coin->price;
                $coin->price2 *= $base_coin->price;
            }
        }
        else
        {
            $coin->price = 0;
            $coin->price2 = 0;
        }

        $coin->save();
        dborun("UPDATE earnings SET price={$coin->price} WHERE status!=2 AND coinid={$coin->id}");
        dborun("UPDATE markets SET message=NULL WHERE disabled=0 AND message='disabled from settings'");
    }
}

function BackendWatchMarkets($marketname = NULL)
{
    // temporary to fill new coin 'watch' field
    if (defined('YIIMP_WATCH_CURRENCIES'))
    {
        $watched = explode(',', YIIMP_WATCH_CURRENCIES);
        foreach ($watched as $symbol)
        {
            dborun("UPDATE coins SET watch=1 WHERE symbol=:sym", array(
                ':sym' => $symbol
            ));
        }
    }

    $coins = new db_coins;
    $coins = $coins->findAllByAttributes(array(
        'watch' => 1
    ));
    foreach ($coins as $coin)
    {
        // track btc/usd for history analysis
        if ($coin->symbol == 'BTC')
        {
            if ($marketname) continue;
            $mh = new db_market_history;
            $mh->time = time();
            $mh->idcoin = $coin->id;
            $mh->idmarket = NULL;
            $mh->price = dboscalar("SELECT usdbtc FROM mining LIMIT 1");
            if (YIIMP_FIAT_ALTERNATIVE == 'EUR') $mh->price2 = kraken_btceur();
            $mh->balance = dboscalar("SELECT SUM(balance) AS btc FROM balances");
            $mh->save();
            continue;
        }
        else if ($coin->installed)
        {
            // "yiimp" prices and balance history
            $mh = new db_market_history;
            $mh->time = time();
            $mh->idcoin = $coin->id;
            $mh->idmarket = NULL;
            $mh->price = $coin->price;
            $mh->price2 = $coin->price2;
            $mh->balance = $coin->balance;
            $mh->save();
        }

        if ($coin->rpcencoding == 'DCR')
        {
            // hack to store the locked balance history as a "stake" market
            $remote = new WalletRPC($coin);
            $stake = 0.; //(double) $remote->getbalance('*',0,'locked');
            $balances = $remote->getbalance('*', 0);
            if (isset($balances["balances"]))
            {
                foreach ($balances["balances"] as $accb)
                {
                    $stake += (double)arraySafeVal($accb, 'lockedbytickets', 0);
                }
            }
            $info = $remote->getstakeinfo();
            if (empty($remote->error) && isset($info['difficulty'])) dborun("UPDATE markets SET balance=0, ontrade=:stake, balancetime=:time,
				price=:ticketprice, price2=:live, pricetime=NULL WHERE coinid=:id AND name='stake'", array(
                ':ticketprice' => $info['difficulty'],
                ':live' => $info['live'],
                ':stake' => $stake,
                ':id' => $coin->id,
                ':time' => time()
            ));
        }

        // user watched currencies
        $markets = getdbolist('db_markets', "coinid={$coin->id} AND NOT disabled");
        foreach ($markets as $market)
        {
            if ($marketname && $market->name != $marketname) continue;
            if (!empty($market->base_coin)) continue; // todo ?
            if (empty($market->price)) continue;
            $mh = new db_market_history;
            $mh->time = time(); // max(intval($market->balancetime), intval($market->pricetime));
            $mh->idcoin = $coin->id;
            $mh->idmarket = $market->id;
            $mh->price = $market->price;
            $mh->price2 = $market->price2;
            $mh->balance = (double)($market->balance) + (double)($market->ontrade);
            $mh->save();
        }
    }
}

function getBestMarket($coin)
{
    $market = NULL;
    if ($coin->symbol == 'BTC') return NULL;

    if (!empty($coin->symbol2))
    {
        $alt = getdbosql('db_coins', "symbol=:symbol", array(
            ':symbol' => $coin->symbol2
        ));
        if ($alt && $alt->symbol2 != $coin->symbol2) return getBestMarket($alt);
    }

    if (!empty($coin->market))
    {
        // get coin market first (if set)
        if ($coin->market != 'BEST' && $coin->market != 'unknown') $market = getdbosql('db_markets', "coinid={$coin->id} AND price!=0 AND NOT deleted AND
				NOT disabled AND IFNULL(deposit_address,'') != '' AND name=:name", array(
            ':name' => $coin->market
        ));
        else
        // else take one of the big exchanges...
        $market = getdbosql('db_markets', "coinid={$coin->id} AND price!=0 AND NOT deleted AND
				NOT disabled AND IFNULL(deposit_address,'') != '' AND
				name IN ('poloniex','bittrex') ORDER BY priority DESC, price DESC");
    }

    if (!$market)
    {
        $market = getdbosql('db_markets', "coinid={$coin->id} AND price!=0 AND NOT deleted AND
			NOT disabled AND IFNULL(deposit_address,'') != '' ORDER BY priority DESC, price DESC");
    }

    if (!$market && empty($coin->market))
    {
        debuglog("best market for {$coin->symbol} is unknown");
        $coin->market = 'unknown';
        $coin->save();
    }

    return $market;
}

function AverageIncrement($value1, $value2)
{
    $percent = 80;
    $value = ($value1 * (100 - $percent) + $value2 * $percent) / 100;

    return $value;
}

///////////////////////////////////////////////////////////////////////////////////////////////////
function updateBleutradeMarkets()
{
    $exchange = 'bleutrade';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;

    $list = bleutrade_api_query('public/getcurrencies');
    if (!is_object($list)) return;

    foreach ($list->result as $currency)
    {
        //	debuglog($currency);
        if ($currency->Currency == 'BTC') continue;

        $coin = getdbosql('db_coins', "symbol='{$currency->Currency}'");
        if (!$coin) continue;

        $market = getdbosql('db_markets', "coinid={$coin->id} and name='$exchange'");
        if (!$market) continue;

        $market->txfee = $currency->TxFee;
        if ($market->disabled < 9) $market->disabled = !$currency->IsActive;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
        }

        $market->save();

        if ($market->disabled) continue;

        sleep(1);
        $pair = "{$symbol}_BTC";
        $ticker = bleutrade_api_query('public/getticker', '&market=' . $pair);
        if (!$ticker || !$ticker->success || !$ticker->result) continue;

        $price2 = ($ticker->result[0]->Bid + $ticker->result[0]
            ->Ask) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, $ticker->result[0]
            ->Bid);
        $market->pricetime = time();

        if (!empty(EXCH_BLEUTRADE_KEY))
        {
            $last_checked = cache()->get($exchange . '-deposit_address-check-' . $symbol);
            if (empty($market->deposit_address) && !$last_checked)
            {
                sleep(1);
                $address = bleutrade_api_query('account/getdepositaddress', '&currency=' . $symbol);
                if (is_object($address) && is_object($address->result))
                {
                    $addr = $address
                        ->result->Address;
                    if (!empty($addr) && $addr != $market->deposit_address)
                    {
                        $market->deposit_address = $addr;
                        debuglog("$exchange: deposit address for {$coin->symbol} updated");
                    }
                }
            }
            cache()
                ->set($exchange . '-deposit_address-check-' . $symbol, time() , 24 * 3600);
        }

        $market->save();

        //		debuglog("$exchange: update $coin->symbol: $market->price $market->price2");

    }

}

/////////////////////////////////////////////////////////////////////////////////////////////
function updateBitzMarkets($force = false)
{
    $exchange = 'bitz';
    if (exchange_get($exchange, 'disabled')) return;

    $markets = bitz_api_query('tickerall');

    foreach ($markets as $c => $ticker)
    {
        $pairs = explode('_', $c);
        $symbol = strtoupper(reset($pairs));
        $base = end($pairs);
        if ($symbol == 'BTC' || $base != 'btc') continue;

        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
        }
        $coin = getdbosql('db_coins', "symbol='{$symbol}'");
        if (!$coin) continue;
        if (!$coin->installed && !$coin->watch) continue;
        $market = getdbosql('db_markets', "coinid={$coin->id} and name='{$exchange}'");

        if (!$market) continue;
        $price2 = ($ticker->bidPrice + $ticker->askPrice) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, $ticker->bidPrice);
        $market->pricetime = time();
        $market->priority = - 1;
        $market->txfee = 0.2; // trade pct
        $market->save();
        // debuglog("$exchange: update $symbol: {$market->price} {$market->price2}");

    }
}

/////////////////////////////////////////////////////////////////////////////////////////////
function updateCryptoBridgeMarkets($force = false)
{
    $exchange = 'cryptobridge';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;

    $result = cryptobridge_api_query('ticker');
    if (!is_array($result)) return;

    foreach ($result as $ticker)
    {
        if (is_null(objSafeVal($ticker, 'id'))) continue;
        $pairs = explode('_', $ticker->id);
        $symbol = reset($pairs);
        $base = end($pairs);
        if ($symbol == 'BTC' || $base != 'BTC') continue;

        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
        }

        $coin = getdbosql('db_coins', "symbol='{$symbol}'");
        if (!$coin) continue;
        if (!$coin->installed && !$coin->watch) continue;

        $market = getdbosql('db_markets', "coinid={$coin->id} and name='{$exchange}'");
        if (!$market) continue;

        $price2 = ($ticker->bid + $ticker->ask) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, $ticker->bid);
        $market->pricetime = time();
        $market->priority = - 1;
        $market->txfee = 0.2; // trade pct
        $market->save();

        //debuglog("$exchange: update $symbol: {$market->price} {$market->price2}");

    }
}

function updateEscoDexMarkets($force = false)
{
    $exchange = 'escodex';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;
    $result = escodex_api_query('ticker');
    if (!is_array($result)) return;
    foreach ($result as $ticker)
    {
        if (is_null(objSafeVal($ticker, 'id'))) continue;
        #$pairs = explode('_', $ticker->id);
        $symbol = $ticker->quote;
        $base = $ticker->base;
        if ($symbol == 'BTC' || $base != 'BTC') continue;
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
        }

        $coin = getdbosql('db_coins', "symbol='{$symbol}'");
        if (!$coin) continue;
        if (!$coin->installed && !$coin->watch) continue;
        $market = getdbosql('db_markets', "coinid={$coin->id} and name='{$exchange}'");
        if (!$market) continue;

        $price2 = ($ticker->highest_bid + $ticker->lowest_ask) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, $ticker->highest_bid);
        $market->pricetime = time();
        $market->priority = - 1;
        $market->txfee = 0.2; // trade pct
        $market->save();
        //debuglog("$exchange: update $symbol: {$market->price} {$market->price2}");
        if ((empty($coin->price)) || (empty($coin->price2)))
        {
            $coin->price = $market->price;
            $coin->price2 = $market->price2;
            $coin->market = $exchange;
            $coin->save();
        }
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////
function updateGateioMarkets($force = false)
{
    $exchange = 'gateio';
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $markets = gateio_api_query('tickers');
    if (!is_array($markets)) return;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $dbpair = strtolower($symbol) . '_btc';
        foreach ($markets as $pair => $ticker)
        {
            if ($pair != $dbpair) continue;
            $price2 = (doubleval($ticker['highestBid']) + doubleval($ticker['lowestAsk'])) / 2;
            $market->price = AverageIncrement($market->price, doubleval($ticker['highestBid']));
            $market->price2 = AverageIncrement($market->price2, $price2);
            $market->pricetime = time();
            $market->priority = - 1;
            $market->txfee = 0.2; // trade pct
            $market->save();

            if (empty($coin->price2))
            {
                $coin->price = $market->price;
                $coin->price2 = $market->price2;
                $coin->market = $exchange;
                $coin->save();
            }
        }
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////
function updateGraviexMarkets($force = false)
{
    $exchange = 'graviex';
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $markets = graviex_api_query('tickers');
    if (!is_array($markets)) return;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $symbol = strtolower($symbol);
        $dbpair = $symbol . 'btc';
        foreach ($markets as $pair => $ticker)
        {
            if ($pair != $dbpair) continue;
            $price2 = ($ticker['ticker']['buy'] + $ticker['ticker']['sell']) / 2;
            $market->price = AverageIncrement($market->price, $ticker['ticker']['buy']);
            $market->price2 = AverageIncrement($market->price2, $price2);
            $market->pricetime = time();
            $market->save();

            if (empty($coin->price2))
            {
                $coin->price = $market->price;
                $coin->price2 = $market->price2;
                $coin->market = $exchange;
                $coin->save();
            }
        }
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////
function updateKrakenMarkets($force = false)
{
    $exchange = 'kraken';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;

    $result = kraken_api_query('AssetPairs');
    if (!is_array($result)) return;

    foreach ($result as $pair => $data)
    {
        $pairs = explode('-', $pair);
        $base = reset($pairs);
        $symbol = end($pairs);
        if ($symbol == 'BTC' || $base != 'BTC') continue;
        if (in_array($symbol, array(
            'GBP',
            'CAD',
            'EUR',
            'USD',
            'JPY'
        ))) continue;
        if (strpos($symbol, '.d') !== false) continue;

        $coin = getdbosql('db_coins', "symbol='{$symbol}'");
        if (!$coin) continue;
        if (!$coin->installed && !$coin->watch) continue;

        $fees = reset($data['fees']);
        $feepct = is_array($fees) ? end($fees) : null;
        $market = getdbosql('db_markets', "coinid={$coin->id} and name='{$exchange}'");
        if (!$market) continue;

        $market->txfee = $feepct;

        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
        }

        $market->save();
        if ($market->disabled || $market->deleted) continue;

        sleep(1);
        $ticker = kraken_api_query('Ticker', $symbol);
        if (!is_array($ticker) || !isset($ticker[$pair])) continue;

        $ticker = arraySafeVal($ticker, $pair);
        if (!is_array($ticker) || !isset($ticker['b'])) continue;

        $price1 = (double)$ticker['a'][0]; // a = ask
        $price2 = (double)$ticker['b'][0]; // b = bid, c = last
        // Alt markets on kraken (LTC/DOGE/NMC) are "reversed" against BTC (1/x)
        if ($price2 > $price1)
        {
            $price = $price2 ? 1 / $price2 : 0;
            $price2 = $price1 ? 1 / $price1 : 0;
        }
        else
        {
            $price = $price1 ? 1 / $price1 : 0;
            $price2 = $price2 ? 1 / $price2 : 0;
        }

        $market->price = AverageIncrement($market->price, $price);
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->pricetime = time();

        $market->save();
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////
function updateBittrexMarkets($force = false)
{
    $exchange = 'bittrex';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;

    $list = bittrex_api_query('public/getcurrencies');
    if (!is_object($list)) return;
    foreach ($list->result as $currency)
    {
        $market = objSafeVal($currency, 'Currency', '');
        if (empty($market) || $market == 'BTC') continue;

        $coin = getdbosql('db_coins', "symbol=:sym", array(
            ':sym' => $currency->Currency
        ));
        if (!$coin) continue;

        $market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
        if (!$market) continue;

        $market->txfee = $currency->TxFee; // withdraw cost, not a percent!
        $market->message = $currency->Notice;
        if ($market->disabled < 9) $market->disabled = !$currency->IsActive;

        $market->save();
    }

    sleep(1);

    $list = bittrex_api_query('public/getmarketsummaries');
    if (!is_object($list)) return;

    foreach ($list->result as $m)
    {
        $a = explode('-', $m->MarketName);
        if (!isset($a[1])) continue;
        if ($a[0] != 'BTC') continue;
        $symbol = $a[1];
        if ($symbol == 'BTC') continue;

        $coin = getdbosql('db_coins', "symbol=:sym", array(
            ':sym' => $symbol
        ));
        if (!$coin) continue;

        $market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
        if (!$market) continue;

        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
        }

        $price2 = ($m->Bid + $m->Ask) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, $m->Bid);
        $market->pricetime = time();
        $market->save();

        // deposit address
        if (!empty(EXCH_BITTREX_KEY))
        {
            $last_checked = cache()->get($exchange . '-deposit_address-check-' . $symbol);
            if ($force || (empty($market->deposit_address) && !$last_checked))
            {
                $address = bittrex_api_query('account/getdepositaddress', "&currency={$symbol}");
                if (is_object($address) && isset($address->result))
                {
                    $addr = $address
                        ->result->Address;
                    if (!empty($addr) && $addr != $market->deposit_address)
                    {
                        $market->deposit_address = $addr;
                        $market->save();
                        debuglog("$exchange: deposit address for {$coin->symbol} updated");
                    }
                }
            }
            cache()
                ->set($exchange . '-deposit_address-check-' . $coin->symbol, time() , 12 * 3600);
        }

        //		debuglog("$exchange: update $coin->symbol: $market->price $market->price2");

    }
}

////////////////////////////////////////////////////////////////////////////////////
function updateCCexMarkets()
{
    $exchange = 'c-cex';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;

    $ccex = new CcexAPI;
    $list = $ccex->getMarketSummaries();
    if (!is_array($list)) return;

    foreach ($list as $ticker)
    {
        if (!isset($ticker['MarketName'])) continue;
        $e = explode('-', $ticker['MarketName']);

        $symbol = strtoupper($e[0]);
        $base_symbol = strtoupper($e[1]);

        $sqlFilter = '';
        if ($base_symbol != 'BTC')
        {
            // Only track ALT markets (LTC, DOGE) if the market record exists in the DB, sample market name "c-cex LTC"
            $in_db = (int)dboscalar("SELECT count(M.id) FROM markets M INNER JOIN coins C ON C.id=M.coinid
				WHERE C.installed AND C.symbol=:sym AND M.base_coin=:base", array(
                ':sym' => $symbol,
                ':base' => $base_symbol
            ));
            if (!$in_db) continue;
            $sqlFilter = "AND base_coin='$base_symbol'";
        }

        $coin = getdbosql('db_coins', "symbol=:symbol", array(
            ':symbol' => $symbol
        ));
        if (!$coin) continue;
        if (!$coin->installed && !$coin->watch) continue;

        $market = getdbosql('db_markets', "coinid={$coin->id} AND name LIKE '$exchange%' $sqlFilter");
        if (!$market) continue;
        //if ($market->disabled < 9) $market->disabled = !$ticker['IsActive']; // only in GetMarkets()
        if ($market->disabled < 9) $market->disabled = ($ticker['OpenBuyOrders'] <= 1);

        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
        }

        $market->save();

        if ($market->disabled || $market->deleted) continue;

        $price2 = ($ticker['Bid'] + $ticker['Ask']) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, $ticker['Bid']);
        $market->pricetime = time();
        $market->save();

        if (empty($coin->price2) && $base_symbol == 'BTC')
        {
            $coin->price = $market->price;
            $coin->price2 = $market->price2;
            $coin->save();
        }

        if (!empty(EXCH_CCEX_KEY))
        {
            $last_checked = cache()->get($exchange . '-deposit_address-check-' . $symbol);
            if (empty($market->deposit_address) && !$last_checked)
            {
                sleep(1);
                $address = $ccex->getDepositAddress($symbol);
                if (!empty($address))
                {
                    $addr = arraySafeVal($address, 'return');
                    if (!empty($addr) && $addr != $market->deposit_address)
                    {
                        if (strpos($addr, 'Error') !== false)
                        {
                            $market->message = $addr;
                            debuglog("$exchange: deposit address for $symbol returned $addr");
                        }
                        else
                        {
                            $market->deposit_address = $addr;
                            $market->message = null;
                            debuglog("$exchange: deposit address for {$coin->symbol} updated");
                        }
                        $market->save();
                    }
                }
            }
            cache()
                ->set($exchange . '-deposit_address-check-' . $coin->symbol, time() , 24 * 3600);
        }

        //		debuglog("$exchange: update $coin->symbol: $market->price $market->price2");

    }
}

////////////////////////////////////////////////////////////////////////////////////
function updatePoloniexMarkets()
{
    $exchange = 'poloniex';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;

    $poloniex = new poloniex;

    $tickers = $poloniex->get_ticker();
    if (!is_array($tickers)) return;

    foreach ($tickers as $symbol => $ticker)
    {
        $a = explode('_', $symbol);
        if (!isset($a[1])) continue;
        if ($a[0] != 'BTC') continue;

        $symbol = $a[1];

        $coin = getdbosql('db_coins', "symbol=:symbol", array(
            ':symbol' => $symbol
        ));
        if (!$coin) continue;

        $market = getdbosql('db_markets', "coinid={$coin->id} and name='poloniex'");
        if (!$market) continue;

        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
        }

        if ($market->disabled || $market->deleted) continue;

        $price2 = ($ticker['highestBid'] + $ticker['lowestAsk']) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, $ticker['highestBid']);
        $market->pricetime = time();

        $market->save();

        if (empty($market->deposit_address) && $coin->installed && !empty(EXCH_POLONIEX_KEY))
        {
            $last_checked = cache()->get($exchange . '-deposit_address-check');
            if (time() - $last_checked < 3600)
            {
                // if still empty after get_deposit_addresses(), generate one
                $poloniex->generate_address($coin->symbol);
                sleep(1);
            }
            // empty address found, so force get_deposit_addresses check
            cache()
                ->set($exchange . '-deposit_address-check', 0, 10);
        }

        //		debuglog("$exchange: update $coin->symbol: $market->price $market->price2");

    }

    // deposit addresses
    if (!empty(EXCH_POLONIEX_KEY))
    {
        $list = array();
        $last_checked = cache()->get($exchange . '-deposit_address-check');
        if (!$last_checked)
        {
            $list = $poloniex->get_deposit_addresses();
            if (!is_array($list)) return;
        }

        foreach ($list as $symbol => $item)
        {
            if ($symbol == 'BTC') continue;

            $coin = getdbosql('db_coins', "symbol=:symbol", array(
                ':symbol' => $symbol
            ));
            if (!$coin) continue;

            $market = getdbosql('db_markets', "coinid=$coin->id and name='poloniex'");
            if (!$market) continue;

            if ($market->deposit_address != $item)
            {
                $market->deposit_address = $item;
                $market->save();
                debuglog("$exchange: deposit address for {$coin->symbol} updated");
            }
        }
        cache()
            ->set($exchange . '-deposit_address-check', time() , 12 * 3600);
    }
}

////////////////////////////////////////////////////////////////////////////////////
function updateYobitMarkets()
{
    $exchange = 'yobit';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;

    $res = yobit_api_query('info');
    if (!is_object($res)) return;

    foreach ($res->pairs as $i => $item)
    {
        $e = explode('_', $i);
        $symbol = strtoupper($e[0]);
        $base_symbol = strtoupper($e[1]);

        if ($symbol == 'BTC') continue;

        $coin = getdbosql('db_coins', "symbol=:symbol", array(
            ':symbol' => $symbol
        ));
        if (!$coin) continue;

        $sqlFilter = "AND IFNULL(base_coin,'')=''";
        if ($base_symbol != 'BTC')
        {
            // Only track ALT markets (ETH, DOGE) if the market record exists in the DB, sample market name "yobit DOGE"
            $in_db = (int)dboscalar("SELECT count(M.id) FROM markets M INNER JOIN coins C ON C.id=M.coinid " . " WHERE C.installed AND C.symbol=:sym AND M.name LIKE '$exchange %' AND M.base_coin=:base", array(
                ':sym' => $symbol,
                ':base' => $base_symbol
            ));
            if (!$in_db) continue;
            $sqlFilter = "AND base_coin='$base_symbol'";
        }

        $market = getdbosql('db_markets', "coinid={$coin->id} AND name LIKE '$exchange%' $sqlFilter");
        if (!$market) continue;

        $market->txfee = objSafeVal($item, 'fee', 0.2);
        if ($market->disabled < 9) $market->disabled = arraySafeVal($item, 'hidden', 0);
        if (time() - $market->pricetime > 6 * 3600) $market->price = 0;

        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
        }

        $market->save();

        if ($market->deleted || $market->disabled) continue;
        if (!$coin->installed && !$coin->watch) continue;

        $symbol = $coin->getOfficialSymbol();
        $pair = strtolower($symbol . '_' . $base_symbol);

        $ticker = yobit_api_query("ticker/$pair");
        if (!$ticker || objSafeVal($ticker, $pair) === NULL) continue;
        if (objSafeVal($ticker->$pair, 'buy') === NULL)
        {
            debuglog("$exchange: invalid data received for $pair ticker");
            continue;
        }

        $price2 = ($ticker
            ->$pair->buy + $ticker
            ->$pair->sell) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, $ticker
            ->$pair->buy);
        if ($ticker
            ->$pair->buy < $market->price) $market->price = $ticker
            ->$pair->buy;
        $market->pricetime = time();
        $market->save();

        if (!empty(EXCH_YOBIT_KEY))
        {
            $last_checked = cache()->get($exchange . '-deposit_address-check-' . $symbol);
            if ($last_checked) continue;

            sleep(1); // for the api nonce
            $address = yobit_api_query2('GetDepositAddress', array(
                "coinName" => $symbol
            ));
            if (!empty($address) && isset($address['return']) && $address['success'])
            {
                $addr = $address['return']['address'];
                if (!empty($addr) && $addr != $market->deposit_address)
                {
                    $market->deposit_address = $addr;
                    debuglog("$exchange: deposit address for {$symbol} updated");
                    $market->save();
                }
            }
            cache()
                ->set($exchange . '-deposit_address-check-' . $symbol, time() , 24 * 3600);
        }
    }
}

// http://www.jubi.com/ ?
function updateJubiMarkets()
{
    $exchange = 'jubi';
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $btc = jubi_api_query('ticker', "?coin=btc");
    if (!is_object($btc)) return;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $ticker = jubi_api_query('ticker', "?coin=" . strtolower($symbol));
        if (!$ticker || !is_object($ticker)) continue;
        if (objSafeVal($ticker, 'buy') === NULL)
        {
            debuglog("$exchange: invalid data received for $symbol ticker");
            continue;
        }

        if (isset($btc->sell) && $btc->sell != 0.) $ticker->buy /= $btc->sell;
        if (isset($btc->buy) && $btc->buy != 0.) $ticker->sell /= $btc->buy;

        $price2 = ($ticker->buy + $ticker->sell) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, $ticker->buy * 0.95);
        $market->pricetime = time();

        //		debuglog("jubi update $symbol: $market->price $market->price2");
        $market->save();
    }
}

function updateAlcurexMarkets()
{
    $exchange = 'alcurex';
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $data = alcurex_api_query('market', "?info=on");
    if (!is_object($data)) return;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;
        if (!$coin->installed && !$coin->watch) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $pair = strtoupper($symbol) . '_BTC';
        foreach ($data->MARKETS as $ticker)
        {
            if ($ticker->Pair === $pair)
            {
                $lpair = strtolower($pair);
                $last = alcurex_api_query('market', "?pair=$lpair&last=last");
                if (is_object($last) && !empty($last->$lpair))
                {
                    $last = $last->$lpair;
                    $market->price = AverageIncrement($market->price, $last->price);
                    $market->pricetime = time();
                    $market->save();
                }
                $last = alcurex_api_query('market', "?pair=$lpair&last=sell");
                if (is_object($last) && !empty($last->$lpair))
                {
                    $last = $last->$lpair;
                    $market->price2 = AverageIncrement($market->price2, $last->price);
                    $market->pricetime = time();
                    $market->save();
                }
                if (empty($coin->price))
                {
                    $coin->price = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->save();
                }
                //debuglog("$exchange: $pair price updated to {$market->price}");
                break;
            }
        }
    }
}

function updateCoinbeneMarkets()
{
    $exchange = 'coinbene';
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $data = coinbene_api_query('market/ticker', 'symbol=all');
    $data = objSafeVal($data, 'ticker');
    if (!is_array($data)) return;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;
        if (!$coin->installed && !$coin->watch) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $pair = $symbol . 'BTC';
        foreach ($data as $ticker)
        {
            if ($ticker->symbol != $pair) continue;

            $price2 = ($ticker->bid + $ticker->ask) / 2;
            $market->price2 = AverageIncrement($market->price2, $price2);
            $market->price = AverageIncrement($market->price, $ticker->bid);
            $market->pricetime = time();
            $market->save();

            break;
        }
    }
}

function updateCrex24Markets()
{
    $exchange = 'crex24';
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $data = crex24_api_query('tickers');
    if (!is_array($data)) return;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $symbol = $coin->getOfficialSymbol();
        $pair = strtoupper($symbol) . '-BTC';

        $sqlFilter = '';
        if (!empty($market->base_coin))
        {
            $pair = strtoupper($symbol . '-' . $market->base_coin);
            $sqlFilter = "AND base_coin='{$market->base_coin}'";
        }

        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        foreach ($data as $ticker)
        {
            if ($ticker->instrument === $pair)
            {
                if ($market->disabled < 9)
                {
                    $nbm = (int)dboscalar("SELECT COUNT(id) FROM markets WHERE coinid={$coin->id} $sqlFilter");
                    $market->disabled = ($ticker->bid < $ticker->ask / 2) && ($nbm > 1);
                }

                $price2 = ($ticker->bid + $ticker->ask) / 2;
                $market->price2 = AverageIncrement($market->price2, $price2);
                $market->price = AverageIncrement($market->price, $ticker->bid);
                $market->pricetime = time(); // $ticker->timestamp "2018-08-31T12:48:56Z"
                $market->save();

                if (empty($coin->price) && $ticker->ask)
                {
                    $coin->price = $market->price;
                    $coin->price2 = $price2;
                    $coin->save();
                }
                //debuglog("$exchange: $pair price updated to {$market->price}");
                break;
            }
        }
    }
}

function updateCryptopiaMarkets()
{
    $exchange = 'cryptopia';
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $data = cryptopia_api_query('GetMarkets', 24);
    if (!is_object($data)) return;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $symbol = $coin->getOfficialSymbol();
        $pair = strtoupper($symbol) . '/BTC';

        $sqlFilter = '';
        if (!empty($market->base_coin))
        {
            $pair = strtoupper($symbol . '/' . $market->base_coin);
            $sqlFilter = "AND base_coin='{$market->base_coin}'";
        }

        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }
        else if ($market->message == 'disabled from settings')
        {
            $market->disabled = 0;
            $market->message = '';
            $market->save();
        }

        foreach ($data->Data as $ticker)
        {
            if ($ticker->Label === $pair)
            {

                if ($market->disabled < 9)
                {
                    $nbm = (int)dboscalar("SELECT COUNT(id) FROM markets WHERE coinid={$coin->id} $sqlFilter");
                    $market->disabled = ($ticker->BidPrice < $ticker->AskPrice / 2) && ($nbm > 1);
                }

                $price2 = ($ticker->BidPrice + $ticker->AskPrice) / 2;
                $market->price2 = AverageIncrement($market->price2, $price2);
                $market->price = AverageIncrement($market->price, $ticker->BidPrice * 0.98);
                $market->marketid = $ticker->TradePairId;
                $market->pricetime = time();
                $market->save();

                if (empty($coin->price) && !$market->disabled && strpos($pair, 'BTC'))
                {
                    $coin->price = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->save();
                }
                //				debuglog("$exchange: $pair $market->price ".bitcoinvaluetoa($market->price2));
                break;
            }
        }
    }

    if (empty(EXCH_CRYPTOPIA_KEY)) return;

    $last_checked = cache()->get($exchange . '-deposit_address-check');
    if ($last_checked) return;

    $addresses = array();
    sleep(1);
    $query = cryptopia_api_user('GetBalance');
    if (is_object($query) && is_array($query->Data)) foreach ($query->Data as $balance)
    {
        $addr = objSafeVal($balance, 'Address');
        if (!empty($addr)) $addresses[$balance->Symbol] = $addr;
    }
    // for some reason, no more available in global GetBalance api
    $needCurrencyQueries = empty($addresses);

    if (!empty($list)) foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $symbol = $coin->getOfficialSymbol();
        $addr = arraySafeVal($addresses, $symbol);
        if ($needCurrencyQueries)
        {
            if (!$coin->installed) continue;
            sleep(2);
            $query = cryptopia_api_user('GetDepositAddress', array(
                'Currency' => $symbol
            ));
            $dep = objSafeVal($query, 'Data');
            $addr = objSafeVal($dep, 'Address');
        }
        if (!empty($addr) && $market->deposit_address != $addr)
        {
            debuglog("$exchange: deposit address for {$symbol} updated");
            $market->deposit_address = $addr;
            $market->save();
        }
    }
    cache()
        ->set($exchange . '-deposit_address-check', time() , 12 * 3600);
}

function updateHitBTCMarkets()
{
    $exchange = 'hitbtc';
    if (exchange_get($exchange, 'disabled')) return;

    $markets = getdbolist('db_markets', "name LIKE '$exchange%'"); // allow "hitbtc LTC"
    if (empty($markets)) return;

    $data = hitbtc_api_query('ticker', '', 'array');
    if (!is_array($data) || empty($data)) return;

    foreach ($markets as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $base = 'BTC';
        $symbol = $coin->getOfficialSymbol();
        $pair = strtoupper($symbol) . $base;

        $sqlFilter = '';
        if (!empty($market->base_coin))
        {
            $base = $market->base_coin;
            $pair = strtoupper($market->base_coin . $symbol);
            $sqlFilter = "AND base_coin='{$market->base_coin}'";
        }

        if (market_get($exchange, $symbol, "disabled", false, $base))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        foreach ($data as $p => $ticker)
        {
            if ($p === $pair)
            {
                $price2 = ((double)$ticker['bid'] + (double)$ticker['ask']) / 2;
                $market->price = AverageIncrement($market->price, (double)$ticker['bid']);
                $market->price2 = AverageIncrement($market->price2, $price2);
                $market->pricetime = time(); // $ticker->timestamp
                $market->priority = - 1;
                $market->save();

                if (empty($coin->price2) && strpos($pair, 'BTC') !== false)
                {
                    $coin->price = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->save();
                }
                //debuglog("$exchange: $pair $market->price ".bitcoinvaluetoa($market->price2));
                break;
            }
        }

        if (!empty(EXCH_HITBTC_KEY))
        {
            $last_checked = cache()->get($exchange . '-deposit_address-check-' . $symbol);
            if ($coin->installed && !$last_checked && empty($market->deposit_address))
            {
                sleep(1);
                $res = hitbtc_api_user('payment/address/' . $symbol); // GET method
                if (is_object($res) && isset($res->address))
                {
                    if (!empty($res->address))
                    {
                        $market->deposit_address = $res->address;
                        debuglog("$exchange: deposit address for {$symbol} updated");
                        $market->save();
                        if ($symbol == 'WAVES' || $symbol == 'LSK') // Wallet address + Public key
                        debuglog("$exchange: $symbol deposit address data: " . json_encode($res));
                    }
                }
                cache()->set($exchange . '-deposit_address-check-' . $symbol, time() , 24 * 3600);
            }
        }
    }
}

function updateNovaMarkets()
{
    $exchange = 'nova';
    if (exchange_get($exchange, 'disabled')) return;

    $markets = getdbolist('db_markets', "name LIKE '$exchange%'"); // allow "nova LTC"
    if (empty($markets)) return;

    $data = nova_api_query('markets');
    if (!is_object($data) || $data->status != 'success' || !is_array($data->markets)) return;

    foreach ($markets as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $base = 'BTC';
        $symbol = $coin->getOfficialSymbol();
        $pair = $base . '_' . strtoupper($symbol);

        $sqlFilter = '';
        if (!empty($market->base_coin))
        {
            $base = $market->base_coin;
            $pair = strtoupper($market->base_coin . '_' . $symbol);
            $sqlFilter = "AND base_coin='{$market->base_coin}'";
        }

        if (market_get($exchange, $symbol, "disabled", false, $base))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        foreach ($data->markets as $ticker)
        {
            if ($ticker->marketname === $pair)
            {

                $market->marketid = $ticker->marketid;

                if ($market->disabled < 9)
                {
                    $nbm = (int)dboscalar("SELECT COUNT(id) FROM markets WHERE coinid={$coin->id} $sqlFilter");
                    $market->disabled = (floatval($ticker->volume24h) <= 0.005) && $nbm > 1; // in btc

                }

                if (!$market->disabled)
                {
                    $market->price = AverageIncrement($market->price, $ticker->bid);
                    $market->price2 = AverageIncrement($market->price2, $ticker->last_price);
                    $market->pricetime = time();
                    $market->save();

                    if (empty($coin->price2) && strpos($pair, 'BTC') !== false)
                    {
                        $coin->price = $market->price;
                        $coin->price2 = $market->price2;
                        $coin->save();
                    }
                }
                break;
            }
        }

        if (!empty(EXCH_NOVA_KEY))
        {
            $last_checked = cache()->get($exchange . '-deposit_address-check-' . $symbol);
            if (empty($market->deposit_address) && !$last_checked)
            {
                sleep(1);
                $res = nova_api_user('getdepositaddress/' . $symbol);
                if (objSafeVal($res, 'status') == 'success')
                {
                    $addr = objSafeVal($res, 'address');
                    if (!empty($addr))
                    {
                        $market->deposit_address = $addr;
                        // delimiter "::" for memo / payment id
                        $market->message = null;
                        debuglog("$exchange: deposit address for {$symbol} updated");
                        $market->save();
                    }
                    else
                    {
                        debuglog("$exchange: Failed to update $symbol deposit address, " . json_encode($res));
                    }
                }
                cache()->set($exchange . '-deposit_address-check-' . $symbol, time() , 24 * 3600);
            }
        }
    }
}

function updateBinanceMarkets()
{
    $exchange = 'binance';
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $tickers = binance_api_query('ticker/allBookTickers');
    if (!is_array($tickers)) return;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $pair = $symbol . 'BTC';
        foreach ($tickers as $ticker)
        {
            if ($pair != $ticker->symbol) continue;

            $price2 = ($ticker->bidPrice + $ticker->askPrice) / 2;
            $market->price = AverageIncrement($market->price, $ticker->bidPrice);
            $market->price2 = AverageIncrement($market->price2, $price2);
            $market->pricetime = time();
            if ($market->disabled < 9) $market->disabled = (floatval($ticker->bidQty) < 0.01);
            $market->save();

            if (empty($coin->price2))
            {
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
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $markets = empoex_api_query('marketinfo');
    if (!is_array($markets)) return;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $pair = strtoupper($symbol) . '-BTC';

        foreach ($markets as $ticker)
        {
            if ($ticker->pairname != $pair) continue;

            $market->price = AverageIncrement($market->price, $ticker->bid);
            $market->price2 = AverageIncrement($market->price2, $ticker->ask);
            $market->pricetime = time();

            if (floatval($ticker->base_volume_24hr) > 0.01) $market->save();

            if (empty($coin->price2))
            {
                $coin->price = $market->price;
                $coin->price2 = $market->price2;
                $coin->market = 'empoex';
                $coin->save();
            }
        }
    }
}

function updateKuCoinMarkets()
{
    $exchange = 'kucoin';
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $symbols = kucoin_api_query('symbols', 'market=BTC');
    if (!kucoin_result_valid($symbols) || empty($symbols->data)) return;

    usleep(500);
    $markets = kucoin_api_query('market/allTickers');
    if (!kucoin_result_valid($markets) || empty($markets->data)) return;
    if (!isset($markets
        ->data
        ->ticker) || !is_array($markets
        ->data
        ->ticker)) return;
    $tickers = $markets
        ->data->ticker;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $pair = strtoupper($symbol) . '-BTC';

        $enableTrading = false;
        foreach ($symbols->data as $sym)
        {
            if (objSafeVal($sym, 'symbol') != $pair) continue;
            $enableTrading = objSafeVal($sym, 'enableTrading', false);
            break;
        }

        if ($market->disabled == $enableTrading)
        {
            $market->disabled = (int)(!$enableTrading);
            $market->save();
            if ($market->disabled) continue;
        }

        foreach ($tickers as $ticker)
        {
            if ($ticker->symbol != $pair) continue;
            if (objSafeVal($ticker, 'buy', -1) == - 1) continue;

            $market->price = AverageIncrement($market->price, $ticker->buy);
            $market->price2 = AverageIncrement($market->price2, objSafeVal($ticker, 'sell', $ticker->buy));
            $market->priority = - 1;
            $market->pricetime = time();

            if (floatval($ticker->vol) > 0.01) $market->save();

            if (empty($coin->price2))
            {
                $coin->price = $market->price;
                $coin->price2 = $market->price2;
                $coin->save();
            }
        }
    }
}

function updateLiveCoinMarkets()
{
    $exchange = 'livecoin';
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $markets = livecoin_api_query('exchange/ticker');
    if (!is_array($markets)) return;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $pair = strtoupper($symbol) . '/BTC';

        foreach ($markets as $ticker)
        {
            if ($ticker->symbol != $pair) continue;

            $market->price = AverageIncrement($market->price, $ticker->best_bid);
            $market->price2 = AverageIncrement($market->price2, $ticker->best_ask);
            $market->txfee = 0.2;
            $market->priority = 0;
            $market->pricetime = time();

            if (floatval($ticker->volume) > 0.01) $market->save();

            if (empty($coin->price2))
            {
                $coin->price = $market->price;
                $coin->price2 = $market->price2;
                $coin->save();
            }

            if (!empty(EXCH_LIVECOIN_KEY) && $market->disabled == 0)
            {
                $last_checked = cache()->get($exchange . '-deposit_address-check-' . $symbol);
                if (empty($market->deposit_address) && !$last_checked)
                {
                    sleep(1);
                    $livecoin = new LiveCoinApi();
                    $data = $livecoin->getDepositAddress($symbol);
                    if (!empty($data) && objSafeVal($data, 'wallet', '') != '')
                    {
                        $addr = arraySafeVal($data, 'wallet');
                        if (!empty($addr))
                        {
                            $market->deposit_address = $addr;
                            // delimiter "::" for memo / payment id
                            $market->message = null;
                            debuglog("$exchange: deposit address for {$coin->symbol} updated");
                            $market->save();
                        }
                        else
                        {
                            debuglog("$exchange: Failed to update $symbol deposit address, " . json_decode($data));
                        }
                    }
                }
                cache()->set($exchange . '-deposit_address-check-' . $symbol, time() , 24 * 3600);
            }
        }
    }
}

function updateCoinExchangeMarkets()
{
    $exchange = 'coinexchange';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;

    $list = coinexchange_api_query('getmarkets');
    if (!is_object($list)) return;
    $markets = coinexchange_api_query('getmarketsummaries');
    if (!is_object($markets)) return;
    foreach ($list->result as $currency)
    {
        $symbol = objSafeVal($currency, 'MarketAssetCode', '');
        $exchid = objSafeVal($currency, 'MarketID', 0);
        if (empty($symbol) || !$exchid || $symbol == 'BTC') continue;

        $coin = getdbosql('db_coins', "symbol=:sym", array(
            ':sym' => $symbol
        ));
        if (!$coin) continue;

        $market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange' AND IFNULL(base_coin,'') IN ('','BTC')");
        $base = objSafeVal($currency, 'BaseCurrencyCode', '');
        if ($base != 'BTC')
        {
            $market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange' AND base_coin=:base", array(
                ':base' => $base
            ));
        }
        if (!$market) continue;

        $symbol = $coin->getOfficialSymbol();
        if ($market->disabled < 9) $market->disabled = !$currency->Active;

        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        if ($currency->Active && $coin->enable)
        {
            // check wallet status (deposit/withdrawals)
            $status = coinexchange_api_query('getcurrency', 'ticker_code=' . $symbol);
            if (is_object($status) && is_object($status->result))
            {
                $res = $status->result;
                if ($market->disabled < 9) $market->disabled = (objSafeVal($res, 'WalletStatus') == "offline");
                $market->message = $market->disabled ? $res->WalletStatus : '';
                //debuglog("$exchange: $symbol wallet is {$res->WalletStatus}");

            }
        }

        $market->save();

        if ($market->disabled || $market->deleted) continue;

        foreach ($markets->result as $m)
        {
            if ($m->MarketID == $exchid)
            {
                $price2 = ((double)$m->BidPrice + (double)$m->AskPrice) / 2;
                $market->price2 = AverageIncrement($market->price2, $price2);
                $market->price = AverageIncrement($market->price, (double)$m->BidPrice);
                $market->pricetime = time();
                $market->marketid = $exchid;
                $market->priority = - 1; // not ready for trading
                $market->save();
                //debuglog("$exchange: $symbol price set to ".bitcoinvaluetoa($market->price));
                if (empty($coin->price2))
                {
                    $coin->price = $market->price;
                    $coin->price2 = $market->price2;
                    $coin->save();
                }
                break;
            }
        }
    }
}

function updateCoinsMarketsMarkets()
{
    $exchange = 'coinsmarkets';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;

    $list = coinsmarkets_api_query('apicoin');
    if (empty($list) || !is_array($list)) return;
    foreach ($list as $pair => $data)
    {
        $e = explode('_', $pair);
        $base = $e[0];
        $symbol = strtoupper($e[1]);
        //if($pair == 'DOG_BTC') todo: handle reverted DOG_BTC
        if ($base != 'BTC') continue;

        $coin = getdbosql('db_coins', "symbol=:sym", array(
            ':sym' => $symbol
        ));
        if (!$coin) continue;

        $market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange' AND IFNULL(base_coin,'') IN ('','BTC')");
        if (!$market) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $price2 = ((double)$data['lowestAsk'] + (double)$data['highestBid']) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, (double)$data['highestBid']);
        $market->price = min($market->price, $market->price2); // reversed bid/ask ?
        $market->marketid = arraySafeVal($data, 'id');
        $market->priority = - 1; // not ready for trading
        if ($price2 < $market->price * 2)
        {
            // 24htrade field seems not filled in json
            //if ($market->disabled == 1) $market->disabled = 0;

        }
        else
        {
            // reduce price2
            $market->price2 = AverageIncrement($market->price2, $market->price);
            //if (!$market->disabled) $market->disabled = 1;

        }

        //debuglog("$exchange: $symbol price set to ".bitcoinvaluetoa($market->price));
        $market->pricetime = time();
        $market->save();

        if (empty($coin->price2))
        {
            $coin->price = $market->price;
            $coin->price2 = $market->price2;
            $coin->save();
        }
    }
}

function updateStocksExchangeMarkets()
{
    $exchange = 'stocksexchange';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;

    $list = stocksexchange_api_query('ticker');
    if (empty($list) || !is_array($list)) return;
    foreach ($list as $m)
    {
        $e = explode('_', $m->market_name);
        $symbol = strtoupper($e[0]);
        $base = $e[1];
        if ($base != 'BTC') continue;

        $coin = getdbosql('db_coins', "symbol=:sym", array(
            ':sym' => $symbol
        ));
        if (!$coin) continue;

        $market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange' AND IFNULL(base_coin,'') IN ('','BTC')");
        if (!$market) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $market->disabled = ($m->bid == 0);

        $price2 = ((double)$m->ask + (double)$m->bid) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, (double)$m->bid);
        $market->priority = - 1; // not ready for trading
        $market->txfee = $m->sell_fee_percent;

        //debuglog("$exchange: $symbol price set to ".bitcoinvaluetoa($market->price));
        $market->pricetime = time(); // $m->updated_time;
        $market->save();

        if (empty($coin->price2))
        {
            $coin->price = $market->price;
            $coin->price2 = $market->price2;
            $coin->save();
        }
    }
}

function updateTradeSatoshiMarkets()
{
    $exchange = 'tradesatoshi';
    if (exchange_get($exchange, 'disabled')) return;

    $count = (int)dboscalar("SELECT count(id) FROM markets WHERE name LIKE '$exchange%'");
    if ($count == 0) return;

    $data = tradesatoshi_api_query('getmarketsummaries');
    if (!is_object($data) || !$data->success || !is_array($data->result)) return;
    foreach ($data->result as $m)
    {
        $e = explode('_', $m->market);
        $symbol = strtoupper($e[0]);
        $base = $e[1];
        if ($base != 'BTC') continue;

        $coin = getdbosql('db_coins', "symbol=:sym", array(
            ':sym' => $symbol
        ));
        if (!$coin) continue;

        $market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange' AND IFNULL(base_coin,'') IN ('','BTC')");
        if (!$market) continue;

        $symbol = $coin->getOfficialSymbol();
        if (market_get($exchange, $symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $market->disabled = ($m->openBuyOrders == 0);

        $price2 = ((double)$m->ask + (double)$m->bid) / 2;
        $market->price2 = AverageIncrement($market->price2, $price2);
        $market->price = AverageIncrement($market->price, (double)$m->bid);
        $market->priority = - 1; // not ready for trading
        //debuglog("$exchange: $symbol price set to ".bitcoinvaluetoa($market->price));
        $market->pricetime = time();
        $market->save();

        if (empty($coin->price2))
        {
            $coin->price = $market->price;
            $coin->price2 = $market->price2;
            $coin->save();
        }
    }
}

// todo: store min/max txs limits
function updateShapeShiftMarkets()
{
    $exchange = 'shapeshift';
    if (exchange_get($exchange, 'disabled')) return;

    $list = getdbolist('db_markets', "name LIKE '$exchange%'");
    if (empty($list)) return;

    $markets = shapeshift_api_query('marketinfo');
    if (!is_array($markets) || empty($markets)) return;

    foreach ($list as $market)
    {
        $coin = getdbo('db_coins', $market->coinid);
        if (!$coin) continue;

        if (market_get($exchange, $coin->symbol, "disabled"))
        {
            $market->disabled = 1;
            $market->message = 'disabled from settings';
            $market->save();
            continue;
        }

        $symbol = $coin->getOfficialSymbol();
        $pair = strtoupper($symbol) . '_BTC';
        if (!empty($market->base_coin)) $pair = strtoupper($symbol) . '_' . strtoupper($market->base_coin);

        foreach ($markets as $ticker)
        {
            if ($ticker['pair'] != $pair) continue;

            $market->price = AverageIncrement($market->price, $ticker['rate']);
            $market->price2 = AverageIncrement($market->price2, $ticker['rate']);
            $market->txfee = $ticker['minerFee'] * 100;
            $market->pricetime = time();
            $market->priority = - 1; // not ready for trading
            $market->save();

            if (empty($coin->price2))
            {
                $coin->price = $market->price;
                $coin->price2 = $market->price2;
                //$coin->market = 'shapeshift';
                $coin->save();
            }
        }
    }
}

// update other installed coins price from cryptonator
function updateOtherMarkets()
{
    $coins = getdbolist('db_coins', "installed AND IFNULL(price,0.0) = 0.0");
    foreach ($coins as $coin)
    {
        $symbol = $coin->getOfficialSymbol();
        if (market_get("cryptonator", $coin->symbol, "disabled"))
        {
            continue;
        }

        $json = @file_get_contents("https://www.cryptonator.com/api/full/" . strtolower($symbol) . "-btc");
        $object = json_decode($json);
        if (empty($object)) continue;

        if (is_object($object) && isset($object->ticker))
        {
            $ticker = $object->ticker;
            if ($ticker->target == 'BTC' && $ticker->volume > 1)
            {
                $coin->price2 = $ticker->price;
                $coin->price = AverageIncrement((float)$coin->price, (float)$coin->price2);
                if ($coin->save())
                {
                    debuglog("cryptonator: $symbol price set to " . bitcoinvaluetoa($coin->price));
                }
            }
        }
    }
}
