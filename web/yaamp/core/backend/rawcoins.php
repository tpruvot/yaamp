<?php
/**
 * This function adds the new markets
 * It also create new coins in the database (if present on the most common exchanges)
 */
function updateRawcoins()
{
    //    debuglog(__FUNCTION__);

    exchange_set_default('alcurex', 'disabled', true);
    exchange_set_default('binance', 'disabled', true);
    exchange_set_default('empoex', 'disabled', true);
    exchange_set_default('coinbene', 'disabled', true);
    exchange_set_default('coinexchange', 'disabled', true);
    exchange_set_default('coinsmarkets', 'disabled', true);
    exchange_set_default('escodex', 'disabled', true);
    exchange_set_default('gateio', 'disabled', true);
    exchange_set_default('jubi', 'disabled', true);
    exchange_set_default('nova', 'disabled', true);
    exchange_set_default('stocksexchange', 'disabled', true);
    exchange_set_default('tradesatoshi', 'disabled', true);

    settings_prefetch_all();

    if (!exchange_get('bittrex', 'disabled')) {
        $list = bittrex_api_query('public/getcurrencies');
        if (isset($list->result) && !empty($list->result)) {
            dborun("UPDATE markets SET deleted=true WHERE name='bittrex'");
            foreach ($list->result as $currency) {
                if ($currency->Currency == 'BTC') {
                    exchange_set('bittrex', 'withdraw_fee_btc', $currency->TxFee);
                    continue;
                }
                updateRawCoin('bittrex', $currency->Currency, $currency->CurrencyLong);
            }
        }
    }

    if (!exchange_get('bitz', 'disabled')) {
        $list = bitz_api_query('tickerall');
        if (!empty($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='bitz'");
            foreach ($list as $c => $ticker) {
                $e = explode('_', $c);
                if (strtoupper($e[1]) !== 'BTC')
                    continue;
                $symbol = strtoupper($e[0]);
                updateRawCoin('bitz', $symbol);
            }
        }
    }

    if (!exchange_get('bleutrade', 'disabled')) {
        $list = bleutrade_api_query('public/getcurrencies');
        if (isset($list->result) && !empty($list->result)) {
            dborun("UPDATE markets SET deleted=true WHERE name='bleutrade'");
            foreach ($list->result as $currency) {
                if ($currency->Currency == 'BTC') {
                    exchange_set('bleutrade', 'withdraw_fee_btc', $currency->TxFee);
                    continue;
                }
                updateRawCoin('bleutrade', $currency->Currency, $currency->CurrencyLong);
            }
        }
    }

    if (!exchange_get('coinbene', 'disabled')) {
        $data = coinbene_api_query('market/symbol');
        $list = objSafeVal($data, 'symbol');
        if (is_array($list) && !empty($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='coinbene'");
            foreach ($list as $ticker) {
                if ($ticker->quoteAsset != 'BTC')
                    continue;
                $symbol = $ticker->baseAsset;
                updateRawCoin('coinbene', $symbol);
            }
        }
    }

    if (!exchange_get('crex24', 'disabled')) {
        $list = crex24_api_query('currencies');
        if (is_array($list) && !empty($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='crex24'");
            foreach ($list as $currency) {
                $symbol = objSafeVal($currency, 'symbol');
                $name   = objSafeVal($currency, 'name');
                if ($currency->isFiat || $currency->isDelisted)
                    continue;
                updateRawCoin('crex24', $symbol, $name);
            }
        }
    }

    if (!exchange_get('poloniex', 'disabled')) {
        $poloniex = new poloniex;
        $tickers  = $poloniex->get_currencies();
        if (!$tickers)
            $tickers = array();
        else
            dborun("UPDATE markets SET deleted=true WHERE name='poloniex'");
        foreach ($tickers as $symbol => $ticker) {
            if (arraySafeVal($ticker, 'disabled'))
                continue;
            if (arraySafeVal($ticker, 'delisted'))
                continue;
            updateRawCoin('poloniex', $symbol);
        }
    }

    if (!exchange_get('c-cex', 'disabled')) {
        $ccex = new CcexAPI;
        $list = $ccex->getPairs();
        if ($list) {
            sleep(1);
            $names = $ccex->getCoinNames();

            dborun("UPDATE markets SET deleted=true WHERE name='c-cex'");
            foreach ($list as $item) {
                $e      = explode('-', $item);
                $symbol = strtoupper($e[0]);

                updateRawCoin('c-cex', $symbol, arraySafeVal($names, $e[0], 'unknown'));
            }
        }
    }

    if (!exchange_get('yobit', 'disabled')) {
        $res = yobit_api_query('info');
        if ($res) {
            dborun("UPDATE markets SET deleted=true WHERE name='yobit'");
            foreach ($res->pairs as $i => $item) {
                $e      = explode('_', $i);
                $symbol = strtoupper($e[0]);
                updateRawCoin('yobit', $symbol);
            }
        }
    }

    if (!exchange_get('coinexchange', 'disabled')) {
        $list = coinexchange_api_query('getmarkets');
        if (isset($list->result) && !empty($list->result)) {
            dborun("UPDATE markets SET deleted=true WHERE name='coinexchange'");
            foreach ($list->result as $item) {
                if ($item->BaseCurrencyCode != 'BTC')
                    continue;
                $symbol = $item->MarketAssetCode;
                $label  = objSafeVal($item, 'MarketAssetName');
                updateRawCoin('coinexchange', $symbol, $label);
            }
        }
    }

    if (!exchange_get('coinsmarkets', 'disabled')) {
        $list = coinsmarkets_api_query('apicoin');
        if (!empty($list) && is_array($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='coinsmarkets'");
            foreach ($list as $pair => $data) {
                $e = explode('_', $pair);
                if ($e[0] != 'BTC')
                    continue;
                $symbol = strtoupper($e[1]);
                updateRawCoin('coinsmarkets', $symbol);
            }
        }
    }

    if (!exchange_get('cryptopia', 'disabled')) {
        $list = cryptopia_api_query('GetMarkets');
        if (isset($list->Data)) {
            dborun("UPDATE markets SET deleted=true WHERE name='cryptopia'");
            foreach ($list->Data as $item) {
                $e = explode('/', $item->Label);
                if (strtoupper($e[1]) !== 'BTC')
                    continue;
                $symbol = strtoupper($e[0]);
                updateRawCoin('cryptopia', $symbol);
            }
        }
    }

    if (!exchange_get('cryptobridge', 'disabled')) {
        $list = cryptobridge_api_query('ticker');
        if (is_array($list) && !empty($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='cryptobridge'");
            foreach ($list as $ticker) {
                $e = explode('_', $ticker->id);
                if (strtoupper($e[1]) !== 'BTC')
                    continue;
                $symbol = strtoupper($e[0]);
                updateRawCoin('cryptobridge', $symbol);
            }
        }
    }

    if (!exchange_get('escodex', 'disabled')) {
        $list = escodex_api_query('ticker');
        if (is_array($list) && !empty($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='escodex'");
            foreach ($list as $ticker) {
                #debuglog (json_encode($ticker));
                if (strtoupper($ticker->base) !== 'BTC')
                    continue;
                $symbol = strtoupper($ticker->quote);
                updateRawCoin('escodex', $symbol);
            }
        }
    }

    if (!exchange_get('hitbtc', 'disabled')) {
        $list = hitbtc_api_query('symbols');
        if (is_object($list) && isset($list->symbols) && is_array($list->symbols)) {
            dborun("UPDATE markets SET deleted=true WHERE name='hitbtc'");
            foreach ($list->symbols as $data) {
                $base = strtoupper($data->currency);
                if ($base != 'BTC')
                    continue;
                $symbol = strtoupper($data->commodity);
                updateRawCoin('hitbtc', $symbol);
            }
        }
    }

    if (!exchange_get('kraken', 'disabled')) {
        $list = kraken_api_query('AssetPairs');
        if (is_array($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='kraken'");
            foreach ($list as $pair => $item) {
                $pairs  = explode('-', $pair);
                $base   = reset($pairs);
                $symbol = end($pairs);
                if ($symbol == 'BTC' || $base != 'BTC')
                    continue;
                if (in_array($symbol, array(
                    'GBP',
                    'CAD',
                    'EUR',
                    'USD',
                    'JPY'
                )))
                    continue;
                if (strpos($symbol, '.d') !== false)
                    continue;
                $symbol = strtoupper($symbol);
                updateRawCoin('kraken', $symbol);
            }
        }
    }

    if (!exchange_get('alcurex', 'disabled')) {
        $list = alcurex_api_query('market', '?info=on');
        if (is_object($list) && isset($list->MARKETS)) {
            dborun("UPDATE markets SET deleted=true WHERE name='alcurex'");
            foreach ($list->MARKETS as $item) {
                $e      = explode('_', $item->Pair);
                $symbol = strtoupper($e[0]);
                updateRawCoin('alcurex', $symbol);
            }
        }
    }

    if (!exchange_get('binance', 'disabled')) {
        $list = binance_api_query('ticker/allBookTickers');
        if (is_array($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='binance'");
            foreach ($list as $ticker) {
                $base = substr($ticker->symbol, -3, 3);
                // XXXBTC XXXETH BTCUSDT (no separator!)
                if ($base != 'BTC')
                    continue;
                $symbol = substr($ticker->symbol, 0, strlen($ticker->symbol) - 3);
                updateRawCoin('binance', $symbol);
            }
        }
    }

    if (!exchange_get('gateio', 'disabled')) {
        $json = gateio_api_query('marketlist');
        $list = arraySafeVal($json, 'data');
        if (!empty($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='gateio'");
            foreach ($list as $item) {
                if ($item['curr_b'] != 'BTC')
                    continue;
                $symbol = trim(strtoupper($item['symbol']));
                $name   = trim($item['name']);
                updateRawCoin('gateio', $symbol, $name);
            }
        }
    }

    if (!exchange_get('nova', 'disabled')) {
        $list = nova_api_query('markets');
        if (is_object($list) && !empty($list->markets)) {
            dborun("UPDATE markets SET deleted=true WHERE name='nova'");
            foreach ($list->markets as $item) {
                if ($item->basecurrency != 'BTC')
                    continue;
                $symbol = strtoupper($item->currency);
                updateRawCoin('nova', $symbol);
                //debuglog("nova: $symbol");
            }
        }
    }

    if (!exchange_get('stocksexchange', 'disabled')) {
        $list = stocksexchange_api_query('markets');
        if (is_array($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='stocksexchange'");
            foreach ($list as $item) {
                if ($item->partner != 'BTC')
                    continue;
                if ($item->active == false)
                    continue;
                $symbol = strtoupper($item->currency);
                $name   = trim($item->currency_long);
                updateRawCoin('stocksexchange', $symbol, $name);
            }
        }
    }

    if (!exchange_get('empoex', 'disabled')) {
        $list = empoex_api_query('marketinfo');
        if (is_array($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='empoex'");
            foreach ($list as $item) {
                $e    = explode('-', $item->pairname);
                $base = strtoupper($e[1]);
                if ($base != 'BTC')
                    continue;
                $symbol = strtoupper($e[0]);
                updateRawCoin('empoex', $symbol);
            }
        }
    }

    if (!exchange_get('kucoin', 'disabled')) {
        $list = kucoin_api_query('currencies');
        if (kucoin_result_valid($list) && !empty($list->data)) {
            dborun("UPDATE markets SET deleted=true WHERE name='kucoin'");
            foreach ($list->data as $item) {
                $symbol = $item->name;
                $name   = $item->fullName;
                updateRawCoin('kucoin', $symbol, $name);
            }
        }
    }

    if (!exchange_get('livecoin', 'disabled')) {
        $list = livecoin_api_query('exchange/ticker');
        if (is_array($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='livecoin'");
            foreach ($list as $item) {
                $e    = explode('/', $item->symbol);
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
        if (is_array($list) && !empty($list)) {
            dborun("UPDATE markets SET deleted=true WHERE name='shapeshift'");
            foreach ($list as $item) {
                $status = $item['status'];
                if ($status != 'available')
                    continue;
                $symbol = strtoupper($item['symbol']);
                $name   = trim($item['name']);
                updateRawCoin('shapeshift', $symbol, $name);
                //debuglog("shapeshift: $symbol $name");
            }
        }
    }

    if (!exchange_get('tradesatoshi', 'disabled')) {
        $data = tradesatoshi_api_query('getcurrencies');
        if (is_object($data) && !empty($data->result)) {
            dborun("UPDATE markets SET deleted=true WHERE name='tradesatoshi'");
            foreach ($data->result as $item) {
                $symbol = $item->currency;
                $name   = trim($item->currencyLong);
                updateRawCoin('tradesatoshi', $symbol, $name);
            }
        }
    }

    //////////////////////////////////////////////////////////

    $markets = dbocolumn("SELECT DISTINCT name FROM markets");
    foreach ($markets as $exchange) {
        if (exchange_get($exchange, 'disabled')) {
            $res = dborun("UPDATE markets SET disabled=8 WHERE name='$exchange'");
            if (!$res)
                continue;
            $coins = getdbolist('db_coins', "id IN (SELECT coinid FROM markets WHERE name='$exchange')");
            foreach ($coins as $coin) {
                // allow to track a single market on a disabled exchange (dev test)
                if (market_get($exchange, $coin->getOfficialSymbol(), 'disabled', 1) == 0) {
                    $res -= dborun("UPDATE markets SET disabled=0 WHERE name='$exchange' AND coinid={$coin->id}");
                }
            }
            debuglog("$exchange: $res markets disabled from db settings");
        } else {
            $res = dborun("UPDATE markets SET disabled=0 WHERE name='$exchange' AND disabled=8");
            if ($res)
                debuglog("$exchange: $res markets re-enabled from db settings");
        }
    }

    dborun("DELETE FROM markets WHERE deleted");

    $list = getdbolist('db_coins', "not enable and not installed and id not in (select distinct coinid from markets)");
    foreach ($list as $coin) {
        if ($coin->visible)
            debuglog("{$coin->symbol} is no longer active");
        // todo: proper cleanup in all tables (like "yiimp coin SYM delete")
        //    if ($coin->symbol != 'BTC')
        //        $coin->delete();
    }
}

function updateRawCoin($marketname, $symbol, $name = 'unknown')
{
    if ($symbol == 'BTC')
        return;

    $coin = getdbosql('db_coins', "symbol=:symbol", array(
        ':symbol' => $symbol
    ));
    if (!$coin && YAAMP_CREATE_NEW_COINS) {
        $algo = '';
        if ($marketname == 'cryptopia') {
            // get coin label and algo (different api)
            $labels = cryptopia_api_query('GetCurrencies');
            if (is_object($labels) && !empty($labels->Data)) {
                foreach ($labels->Data as $coin) {
                    if ($coin->Symbol == $symbol) {
                        $name = $coin->Name;
                        $algo = strtolower($coin->Algorithm);
                        if ($algo == 'scrypt')
                            $algo = ''; // cryptopia default generally wrong
                        break;
                    }
                }
            }
        }

        if (in_array($marketname, array(
            'nova',
            'askcoin',
            'binance',
            'bitz',
            'coinexchange',
            'coinsmarkets',
            'cryptobridge',
            'hitbtc'
        ))) {
            // don't polute too much the db with new coins, its better from exchanges with labels
            return;
        }

        // some other to ignore...
        if (in_array($marketname, array(
            'crex24',
            'escodex',
            'yobit',
            'coinbene',
            'kucoin',
            'tradesatoshi'
        )))
            return;

        if (market_get($marketname, $symbol, "disabled")) {
            return;
        }

        debuglog("new coin $marketname $symbol $name");

        $coin                 = new db_coins;
        $coin->txmessage      = true;
        $coin->hassubmitblock = true;
        $coin->name           = $name;
        $coin->algo           = $algo;
        $coin->symbol         = $symbol;
        $coin->created        = time();
        $coin->save();

        $url = getMarketUrl($coin, $marketname);
        if (YAAMP_NOTIFY_NEW_COINS)
            mail(YAAMP_ADMIN_EMAIL, "New coin $symbol", "new coin $symbol ($name) on $marketname\r\n\r\n$url");
        sleep(30);
    }

    else if ($coin && $coin->name == 'unknown' && $name != 'unknown') {
        $coin->name = $name;
        $coin->save();
    }

    $list = getdbolist('db_coins', "symbol=:symbol or symbol2=:symbol", array(
        ':symbol' => $symbol
    ));
    foreach ($list as $coin) {
        $market = getdbosql('db_markets', "coinid=$coin->id and name='$marketname'");
        if (!$market) {
            $market         = new db_markets;
            $market->coinid = $coin->id;
            $market->name   = $marketname;
        }

        $market->deleted = false;
        $market->save();
    }

}
