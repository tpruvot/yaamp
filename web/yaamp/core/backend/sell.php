<?php

///////////////////////////////////////////////////////////////////////////////////////////////////////////

function TradingSellCoins()
{
    //    debuglog(__FUNCTION__);

    $coins = getdbolist('db_coins', "enable and balance>0 and symbol!='BTC'");
    foreach ($coins as $coin)
        sellCoinToExchange($coin);
}

function sellCoinToExchange($coin)
{
    if ($coin->dontsell)
        return;

    $remote = new WalletRPC($coin);

    $info = $remote->getinfo();
    if (!$info || !$info['balance'])
        return false;

    if (!empty($coin->symbol2)) {
        $coin2 = getdbosql('db_coins', "symbol='$coin->symbol2'");
        if (!$coin2)
            return;

        $amount = $info['balance'] - $info['paytxfee'];
        $amount *= 0.9;

        //        debuglog("sending $amount $coin->symbol to main wallet");

        $tx = $remote->sendtoaddress($coin2->master_wallet, $amount);
        //        if(!$tx) debuglog($remote->error);

        return;
    }

    $market = getBestMarket($coin);
    if (!$market)
        return;

    if ($market->lastsent != null && $market->lastsent > $market->lasttraded) {
        //        mail(YAAMP_ADMIN_EMAIL, "Late Transaction", "A previous transaction for $coin->name to $market->name has not been recieved.");
        //        debuglog("*** not sending $coin->name to $market->name. last tx is late ***");
        return;
    }

    $deposit_address = $market->deposit_address;
    $marketname      = $market->name;

    if (empty($deposit_address))
        return false;
    $reserved1 = dboscalar("select sum(balance) from accounts where coinid=$coin->id");
    $reserved2 = dboscalar("select sum(amount*price) from earnings
        where status!=2 and userid in (select id from accounts where coinid=$coin->id)");

    $reserved = ($reserved1 + $reserved2) * 10;
    $amount   = $info['balance'] - $info['paytxfee'] - $reserved;

    //    if($reserved>0)
    //    {
    //        debuglog("$reserved1 $reserved2 out of {$info['balance']}");
    //        debuglog("reserving $reserved $coin->symbol out of $coin->balance, available $amount");
    //    }

    if ($amount < $coin->reward / 4) {
        //    debuglog("not enough $coin->symbol to sell $amount < $coin->reward /4");
        return false;
    }

    $deposit_info = $remote->validateaddress($deposit_address);
    if (!$deposit_info || !isset($deposit_info['isvalid']) || !$deposit_info['isvalid']) {
        debuglog("sell invalid address $deposit_address");
        return;
    }

    $amount = round($amount, 8);
    //    debuglog("sending $amount $coin->symbol to $marketname, $deposit_address");

    //    sleep(1);

    $tx = $remote->sendtoaddress($deposit_address, $amount);
    if (!$tx) {
        //    debuglog($remote->error);

        if ($coin->symbol == 'DIME')
            $amount = min($amount, 10000000);
        else if ($coin->symbol == 'CNOTE')
            $amount = min($amount, 10000);
        else if ($coin->symbol == 'SRC')
            $amount = min($amount, 500);
        else
            $amount = round($amount * 0.99, 8);

        //        debuglog("sending $amount $coin->symbol to $deposit_address");
        sleep(1);

        $tx = $remote->sendtoaddress($deposit_address, $amount);
        if (!$tx) {
            debuglog("sending $amount $coin->symbol to $deposit_address");
            debuglog($remote->error);
            return;
        }
    }

    if ($tx) {
        $market->lastsent = time();
        $market->save();
    }

    $exchange                 = new db_exchange;
    $exchange->market         = $marketname;
    $exchange->coinid         = $coin->id;
    $exchange->send_time      = time();
    $exchange->quantity       = $amount;
    $exchange->price_estimate = $coin->price;
    $exchange->status         = 'waiting';
    $exchange->tx             = $tx;
    $exchange->save();

    return;
}
