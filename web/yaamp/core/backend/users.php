<?php

function BackendUsersUpdate()
{
    $t1 = microtime(true);

    $list = getdbolist('db_accounts', "coinid IS NULL OR IFNULL(coinsymbol,'') != ''");
    foreach ($list as $user) {
        $old_usercoinid = $user->coinid;
        //    debuglog("testing user $user->username, $user->coinsymbol");
        if (!empty($user->coinsymbol)) {
            $coin             = getdbosql('db_coins', "symbol=:symbol", array(
                ':symbol' => $user->coinsymbol
            ));
            $user->coinsymbol = '';

            if ($coin) {
                if ($user->coinid == $coin->id) {
                    $user->save();
                    continue;
                }

                $remote = new WalletRPC($coin);

                $b = $remote->validateaddress($user->username);
                if (arraySafeVal($b, 'isvalid')) {
                    $old_balance = $user->balance;
                    if ($user->balance > 0) {
                        $coinref = getdbo('db_coins', $user->coinid);
                        if (!$coinref) {
                            if (YAAMP_ALLOW_EXCHANGE)
                                $coinref = getdbosql('db_coins', "symbol='BTC'");
                            else
                                continue;
                        }

                        $user->balance = $user->balance * $coinref->price / $coin->price;
                    }

                    $user->coinid = $coin->id;
                    $user->save();

                    debuglog("{$user->username} converted to {$user->balance} {$coin->symbol} (old: $old_balance)");
                    continue;
                }
            }
        }

        $user->coinid = 0;

        $order = YAAMP_ALLOW_EXCHANGE ? "difficulty" : "id";
        $coins = getdbolist('db_coins', "enable ORDER BY $order DESC");
        foreach ($coins as $coin) {
            $remote = new WalletRPC($coin);

            $b = $remote->validateaddress($user->username);
            if (!arraySafeVal($b, 'isvalid'))
                continue;

            if ($old_usercoinid && $old_usercoinid != $coin->id) {
                debuglog("{$user->username} set to {$coin->symbol}, balance {$user->balance} reset to 0");
                $user->balance = 0;
            }
            $user->coinid = $coin->id;
            break;
        }

        if (empty($user->coinid)) {
            debuglog("{$user->hostaddr} - {$user->username} is an unknown address!");
        }

        $user->save();
    }

    //    $delay=time()-60*60;
    //    $list = dborun("UPDATE coins SET dontsell=1 WHERE id in (SELECT coinid FROM accounts WHERE balance>0 OR last_earning>$delay GROUP BY coinid)");
    //    $list = dborun("UPDATE coins SET dontsell=0 WHERE id not in (SELECT coinid FROM accounts WHERE balance>0 OR last_earning>$delay GROUP BY coinid)");


    //    $list = getdbolist('db_workers', "dns is null");
    //    foreach($list as $worker)
    //    {
    //        $worker->dns = $worker->ip;
    //        $res = system("resolveip $worker->ip");

    //        if($res)
    //        {
    //            $a = explode(' ', $res);
    //            if($a && isset($a[5]))
    //                $worker->dns = $a[5];
    //        }

    //        $worker->save();
    //    }

    $d1 = microtime(true) - $t1;
    controller()->memcache->add_monitoring_function(__METHOD__, $d1);
}
