<?php

function BackendBlockNew($coin, $db_block)
{
    //    debuglog("NEW BLOCK $coin->name $db_block->height");
    $reward = $db_block->amount;
    if (!$reward || $db_block->algo == 'PoS' || $db_block->algo == 'MN')
        return;
    if ($db_block->category == 'stake' || $db_block->category == 'generated')
        return;

    $sqlCond = "valid = 1";
    if (!YAAMP_ALLOW_EXCHANGE) // only one coin mined
        $sqlCond .= " AND coinid = " . intval($coin->id);

    $total_hash_power = dboscalar("SELECT SUM(difficulty) FROM shares WHERE $sqlCond AND algo=:algo", array(
        ':algo' => $coin->algo
    ));
    if (!$total_hash_power)
        return;

    $list = dbolist("SELECT userid, SUM(difficulty) AS total FROM shares WHERE $sqlCond AND algo=:algo GROUP BY userid", array(
        ':algo' => $coin->algo
    ));

    foreach ($list as $item) {
        $hash_power = $item['total'];
        if (!$hash_power)
            continue;

        $user = getdbo('db_accounts', $item['userid']);
        if (!$user)
            continue;

        $amount = $reward * $hash_power / $total_hash_power;
        if (!$user->no_fees)
            $amount = take_yaamp_fee($amount, $coin->algo);
        if (!empty($user->donation)) {
            $amount = take_yaamp_fee($amount, $coin->algo, $user->donation);
            if ($amount <= 0)
                continue;
        }

        $earning              = new db_earnings;
        $earning->userid      = $user->id;
        $earning->coinid      = $coin->id;
        $earning->blockid     = $db_block->id;
        $earning->create_time = $db_block->time;
        $earning->amount      = $amount;
        $earning->price       = $coin->price;

        if ($db_block->category == 'generate') {
            $earning->mature_time = time();
            $earning->status      = 1;
        } else // immature
            $earning->status = 0;

        $ucoin = (object) dborow("SELECT algo,symbol FROM coins WHERE id=:id", array(
            ':id' => $user->coinid
        ));
        if (!YAAMP_ALLOW_EXCHANGE && $ucoin && $ucoin->algo != $coin->algo) {
            debuglog($coin->symbol . ": invalid earning for {$user->username}, user coin is {$ucoin->symbol}");
            $earning->status = -1;
        }

        if (!$earning->save())
            debuglog(__FUNCTION__ . ": Unable to insert earning!");

        dborun('UPDATE accounts SET last_earning=:time WHERE id=:id', array(
            ':id' => $user->id,
            ':time' => time()
        ));
    }

    $delay   = time() - 5 * 60;
    $sqlCond = "time < $delay";
    if (!YAAMP_ALLOW_EXCHANGE) // only one coin mined
        $sqlCond .= " AND coinid = " . intval($coin->id);

    try {
        dborun("DELETE FROM shares WHERE algo=:algo AND $sqlCond", array(
            ':algo' => $coin->algo
        ));

    }
    catch (CDbException $e) {

        debuglog("unable to delete shares $sqlCond retrying...");
        sleep(1);
        dborun("DELETE FROM shares WHERE algo=:algo AND $sqlCond", array(
            ':algo' => $coin->algo
        ));
        // [errorInfo] => array(0 => 'HY000', 1 => 1205, 2 => 'Lock wait timeout exceeded; try restarting transaction')
        // [*:message] => 'CDbCommand failed to execute the SQL statement: SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction'
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////////
// Import new blocks (notified by the stratum)

function BackendBlockFind1($coinid = NULL)
{
    $sqlFilter = $coinid ? " AND coin_id=" . intval($coinid) : '';

    //    debuglog(__METHOD__);
    $list = getdbolist('db_blocks', "category='new' $sqlFilter ORDER BY time");
    foreach ($list as $db_block) {
        $coin = getdbo('db_coins', $db_block->coin_id);
        if (!$coin || !$db_block->coin_id) {
            debuglog("warning: bad coin id {$db_block->coin_id} for block id {$db_block->id}!");
            $db_block->delete();
            continue;
        }
        if (!$coin->enable)
            continue;
        if ($coin->rpcencoding == 'DCR' && !$coin->auto_ready)
            continue;

        $dblock = getdbosql('db_blocks', "coin_id=:coinid AND blockhash=:hash AND height=:height AND id!=:blockid", array(
            ':coinid' => $coin->id,
            ':hash' => $db_block->blockhash,
            ':height' => $db_block->height,
            ':blockid' => $db_block->id
        ));

        if ($dblock) {
            debuglog("warning: Doubled {$coin->symbol} block found for block height {$db_block->height}!");
            $db_block->delete();
            continue;
        }

        $db_block->category = 'orphan';
        $remote             = new WalletRPC($coin);

        $block     = $remote->getblock($db_block->blockhash);
        $block_age = time() - $db_block->time;
        if ($coin->rpcencoding == 'DCR' && $block_age < 2000) {
            // DCR generated blocks need some time to be accepted by the network (gettransaction)
            if (!$block)
                continue;
            $txid = $block['tx'][0];
            $tx   = $remote->gettransaction($txid);
            if (!$tx || !isset($tx['details']))
                continue;
            debuglog("{$coin->symbol} {$db_block->height} confirmed after " . $block_age . " seconds");
        } else if (!$block || !isset($block['tx']) || !isset($block['tx'][0])) {
            $db_block->amount = 0;
            $db_block->save();
            debuglog("{$coin->symbol} orphan {$db_block->height} after " . (time() - $db_block->time) . " seconds");
            continue;
        } else if ($coin->rpcencoding == 'POS' && arraySafeVal($block, 'nonce') == 0) {
            $db_block->category = 'stake';
            $db_block->save();
            continue;
        }

        $tx = $remote->gettransaction($block['tx'][0]);
        if (!$tx || !isset($tx['details']) || !isset($tx['details'][0])) {
            $db_block->amount = 0;
            $db_block->save();
            continue;
        }

        $db_block->txhash        = $block['tx'][0];
        $db_block->category      = 'immature'; //$tx['details'][0]['category'];
        $db_block->amount        = $tx['details'][0]['amount'];
        $db_block->confirmations = $tx['confirmations'];
        $db_block->price         = $coin->price;

        // save worker to compute blocs found per worker (current workers stats)
        // now made directly in stratum - require DB update 2015-09-20
        if (empty($db_block->workerid) && $db_block->userid > 0) {
            $db_block->workerid = (int) dboscalar("SELECT workerid FROM shares WHERE userid=:user AND coinid=:coin AND valid=1 AND time <= :time " . "ORDER BY difficulty DESC LIMIT 1", array(
                ':user' => $db_block->userid,
                ':coin' => $db_block->coin_id,
                ':time' => $db_block->time
            ));
            if (!$db_block->workerid)
                $db_block->workerid = NULL;
        }

        if (!$db_block->save())
            debuglog(__FUNCTION__ . ": unable to insert block!");

        if ($db_block->category != 'orphan')
            BackendBlockNew($coin, $db_block); // will drop shares
    }
}

/////////////////////////////////////////////////////////////////////////////////
// Refresh immature blocks status (confirmations)

function BackendBlocksUpdate($coinid = NULL)
{
    //    debuglog(__METHOD__);
    $t1 = microtime(true);

    $sqlFilter = $coinid ? " AND coin_id=" . intval($coinid) : '';

    $list = getdbolist('db_blocks', "category IN ('immature','stake','orphan') $sqlFilter ORDER BY time");
    foreach ($list as $block) {
        $coin = getdbo('db_coins', $block->coin_id);
        if (!$block->coin_id || !$coin) {
            debuglog("warning: bad coin id {$block->coin_id} for block id {$block->id}!");
            $block->delete();
            continue;
        }

        if (!$coin->auto_ready || ($coin->target_height && $coin->target_height > $coin->block_height)) {
            continue;
        }

        $remote = new WalletRPC($coin);
        if (empty($block->txhash)) {
            $blockext = $remote->getblock($block->blockhash);

            if ($coin->rpcencoding == 'POS' && arraySafeVal($blockext, 'nonce') == 0) {
                $block->category = 'stake';
                $block->save();
            }

            if (!$blockext || !isset($blockext['tx'][0]))
                continue;

            $block->txhash = $blockext['tx'][0];

            if (empty($block->txhash))
                continue;
        }

        $tx = $remote->gettransaction($block->txhash);
        if (!$tx && $block->category != 'orphan') {
            if ($coin->enable) {
                debuglog("{$coin->name} unable to find {$block->category} block {$block->height} tx {$block->txhash}!");
                // DCR orphaned confirmations are not(no more) -1!
                if ($coin->rpcencoding == 'DCR' && $block->category == 'immature' && $coin->auto_ready) {
                    $blockext = $remote->getblock($block->blockhash);
                    $conf     = arraySafeVal($blockext, 'confirmations', -1);
                    if ($conf == -1 || ($conf > 2 && arraySafeVal($blockext, 'nextblockhash', '') == '')) {
                        debuglog("{$coin->name} orphan block {$block->height} detected! (after $conf confirmations)");
                        $block->confirmations = -1;
                        $block->amount        = 0;
                        $block->category      = 'orphan';
                        $block->save();
                        continue;
                    }
                }
            } else if ((time() - $block->time) > (7 * 24 * 3600)) {
                debuglog("{$coin->name} outdated immature block {$block->height} detected!");
                $block->category = 'orphan';
            }
            $block->save();
            continue;
        }

        if ($block->category == 'orphan') {
            // LUX doing multiple reorg ? Only seen on this wallet
            if ($coin->enable && (time() - $block->time) < 3600) {
                $blockext = $remote->getblock($block->blockhash);
                $conf     = arraySafeVal($blockext, 'confirmations', -1);
                if ($conf > 2 && arraySafeVal($blockext, 'nextblockhash', '') != '') {
                    debuglog("{$coin->name} orphan block {$block->height} is not anymore! ($conf confirmations)");
                    $block->category = 'new'; // will set amount and restore user earnings
                    $block->save();
                }
            }
            continue;
        }

        $block->confirmations = $tx['confirmations'];

        $category = $block->category;
        if ($block->confirmations == -1 && $coin->enable && $coin->auto_ready) {
            $category      = 'orphan';
            $block->amount = 0;
        }

        else if (isset($tx['details']) && isset($tx['details'][0]))
            $category = $tx['details'][0]['category'];

        else if (isset($tx['category']))
            $category = $tx['category'];

        // PoS blocks
        if ($block->category == 'stake') {
            if ($category == 'generate') {
                $block->category = 'generated';
            } else if ($category == 'orphan') {
                $block->category = 'orphan';
            }
            $block->save();
            continue;
        }

        // PoW blocks
        $block->category = $category;
        $block->save();

        if ($category == 'generate') {
            dborun("UPDATE earnings SET status=1, mature_time=UNIX_TIMESTAMP() WHERE blockid=" . intval($block->id) . " AND status!=-1");

            // auto update mature_blocks
            if ($block->confirmations > 0 && $block->confirmations < $coin->mature_blocks || empty($coin->mature_blocks)) {
                $coin = getdbo('db_coins', $block->coin_id); // refresh coin data
                debuglog("{$coin->symbol} mature_blocks updated to {$block->confirmations}");
                $coin->mature_blocks = $block->confirmations;
                $coin->save();
            }
        } else if ($category != 'immature')
            dborun("DELETE FROM earnings WHERE blockid=" . intval($block->id) . " AND status!=-1");
    }

    $d1 = microtime(true) - $t1;
    controller()->memcache->add_monitoring_function(__METHOD__, $d1);
}

////////////////////////////////////////////////////////////////////////////////////////////
// Search new block transactions (main thread)

function BackendBlockFind2($coinid = NULL)
{
    $t1 = microtime(true);

    $sqlFilter = $coinid ? "id=" . intval($coinid) : 'enable=1';

    $coins = getdbolist('db_coins', $sqlFilter);
    foreach ($coins as $coin) {
        if ($coin->symbol == 'BTC')
            continue;
        $remote = new WalletRPC($coin);

        $timerpc    = microtime(true);
        $mostrecent = 0;
        if (empty($coin->lastblock))
            $coin->lastblock = '';
        $list     = $remote->listsinceblock($coin->lastblock);
        $rpcdelay = microtime(true) - $timerpc;
        if ($rpcdelay > 0.5)
            screenlog(__FUNCTION__ . ": {$coin->symbol} listsinceblock took " . round($rpcdelay, 3) . " sec, " . (is_array($list) ? count($list) : 0) . "txs");
        if (!$list)
            continue;

        foreach ($list['transactions'] as $transaction) {
            if (!isset($transaction['blockhash']))
                continue;
            if ($transaction['time'] > time() - 5 * 60)
                continue;
            if ($transaction['time'] < time() - 60 * 60)
                continue;
            if ($transaction['category'] != 'generate' && $transaction['category'] != 'immature')
                continue;

            $blockext = $remote->getblock($transaction['blockhash']);
            if (!$blockext)
                continue;

            $db_block = getdbosql('db_blocks', "coin_id=:id AND (blockhash=:hash OR height=:height)", array(
                ':id' => $coin->id,
                ':hash' => $transaction['blockhash'],
                ':height' => $blockext['height']
            ));
            if ($db_block)
                continue;

            if ($coin->rpcencoding == 'DCR')
                debuglog("{$coin->name} generated block {$blockext['height']} detected!");

            if ($transaction['time'] > $mostrecent) {
                $coin            = getdbo('db_coins', $coin->id); // refresh coin data
                $coin->lastblock = $transaction['blockhash'];
                $coin->save();
                $mostrecent = $transaction['time'];
            }

            $db_block            = new db_blocks;
            $db_block->blockhash = $transaction['blockhash'];
            $db_block->coin_id   = $coin->id;
            $db_block->category  = 'immature'; //$transaction['category'];
            $db_block->time      = $transaction['time'];
            $db_block->amount    = $transaction['amount'];
            $db_block->algo      = $coin->algo;

            if (arraySafeVal($blockext, 'nonce', 0) != 0) {
                $db_block->difficulty_user = hash_to_difficulty($coin, $transaction['blockhash']);
            } else if ($coin->rpcencoding == 'POS') {
                $db_block->category = 'stake';
            }

            // masternode earnings...
            if (empty($db_block->userid) && $transaction['amount'] == 0 && $transaction['generated']) {
                $db_block->algo = 'MN';
                $tx             = $remote->getrawtransaction($transaction['txid'], 1);

                // assume the MN amount is in the last vout record (should check "addresses")
                if (isset($tx['vout']) && !empty($tx['vout'])) {
                    $vout             = end($tx['vout']);
                    $db_block->amount = $vout['value'];
                    debuglog("MN " . bitcoinvaluetoa($db_block->amount) . ' ' . $coin->symbol . ' (' . $blockext['height'] . ')');
                }

                if (!$coin->hasmasternodes) {
                    $coin                 = getdbo('db_coins', $coin->id); // refresh coin data
                    $coin->hasmasternodes = true;
                    $coin->save();
                }
            }

            $db_block->confirmations = $transaction['confirmations'];
            $db_block->height        = $blockext['height'];
            $db_block->difficulty    = $blockext['difficulty'];
            $db_block->price         = $coin->price;
            if (!$db_block->save())
                debuglog(__FUNCTION__ . ": unable to insert block!");

            BackendBlockNew($coin, $db_block);
        } // tx
    }

    $d1 = microtime(true) - $t1;
    controller()->memcache->add_monitoring_function(__FUNCTION__, $d1);
    if ($d1 > 3.0)
        screenlog(__FUNCTION__ . ": took " . round($d1, 3) . " sec");
}

////////////////////////////////////////////////////////////////////////////////////////////
// Update coin totals from the db blocks/earnings (allow triggers and easier balance sums)

function BackendUpdatePoolBalances($coinid = NULL)
{
    $t1 = microtime(true);

    $sqlFilter = 'enable=1';

    if ($coinid) { // used from wallet manual send
        $sqlFilter = "id=" . intval($coinid);
        // refresh balance field from the wallet info
        $coin      = getdbo('db_coins', $coinid);
        $remote    = new WalletRPC($coin);
        $info      = $remote->getinfo();
        if (isset($info['balance'])) {
            $coin->balance = $info['balance'];
            $coin->save();
        }
    }

    $coins = getdbolist('db_coins', $sqlFilter);
    foreach ($coins as $coin) {
        $coin->immature  = (double) dboscalar("SELECT SUM(amount) FROM blocks WHERE category='immature' AND coin_id=" . intval($coin->id));
        $coin->cleared   = (double) dboscalar("SELECT SUM(balance) FROM accounts WHERE coinid=" . intval($coin->id));
        $pending         = (double) dboscalar("SELECT SUM(amount) FROM earnings WHERE status=1 AND coinid=" . intval($coin->id)); // (to be cleared)
        $coin->available = (double) $coin->balance - $coin->cleared - $pending;
        //if ($pending) debuglog("{$coin->symbol} immature {$coin->immature}, cleared {$coin->cleared}, pending {$pending}, available {$coin->available}");
        $coin->save();
    }

    $d1 = microtime(true) - $t1;
    controller()->memcache->add_monitoring_function(__FUNCTION__, $d1);
    //debuglog(__FUNCTION__." took ".round($d1,3)." sec");
}

////////////////////////////////////////////////////////////////////////////////////////////

function MonitorBTC()
{
    //    debuglog(__FUNCTION__);

    $coin = getdbosql('db_coins', "symbol='BTC'");
    if (!$coin)
        return;

    $remote = new WalletRPC($coin);
    if (!$remote)
        return;

    $mostrecent = 0;
    if ($coin->lastblock == null)
        $coin->lastblock = '';
    $list = $remote->listsinceblock($coin->lastblock);
    if (!$list)
        return;

    $coin->lastblock = $list['lastblock'];
    $coin->save();

    foreach ($list['transactions'] as $transaction) {
        if (!isset($transaction['blockhash']))
            continue;
        if ($transaction['confirmations'] == 0)
            continue;
        if ($transaction['category'] != 'send')
            continue;
        //if($transaction['fee'] != -0.0001) continue;

        debuglog(__FUNCTION__);
        debuglog($transaction);

        $txurl = "https://blockchain.info/tx/{$transaction['txid']}";

        $b = mail(YAAMP_ADMIN_EMAIL, "withdraw {$transaction['amount']}", "<a href='$txurl'>{$transaction['address']}</a>");

        if (!$b)
            debuglog('error sending email');
    }
}
