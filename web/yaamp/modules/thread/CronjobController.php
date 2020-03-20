<?php
require_once ('serverconfig.php');
require_once ('yaamp/defaultconfig.php');

class CronjobController extends CommonController
{
    private function monitorApache()
    {
        if (!YAAMP_PRODUCTION) return;
        if (!YAAMP_USE_NGINX) return;

        $uptime = exec('uptime');

        $apache_locked = memcache_get($this
            ->memcache->memcache, 'apache_locked');
        if ($apache_locked) return;

        $b = preg_match('/load average: (.*)$/', $uptime, $m);
        if (!$b) return;

        $e = explode(', ', $m[1]);

        $webserver = 'nginx';
        $res = exec("pgrep $webserver");
        $webserver_running = !empty($res);

        if ($e[0] > 4 && $webserver_running)
        {
            debuglog('server overload!');
            //		debuglog('stopping webserver');
            //		system("service $webserver stop");
            sleep(1);
        }

        else if (!$webserver_running)
        {
            debuglog('starting webserver');
            system("service $webserver start");
        }
    }

    public function actionRunBlocks()
    {
        //		screenlog(__FUNCTION__);
        set_time_limit(0);

        $this->monitorApache();

        $last_complete = memcache_get($this
            ->memcache->memcache, "cronjob_block_time_start");
        if ($last_complete + (5 * 60) < time()) dborun("update jobs set active=false");
        BackendBlockFind1();
        if (!memcache_get($this
            ->memcache->memcache, 'balances_locked'))
        {
            BackendClearEarnings();
        }
        BackendRentingUpdate();
        BackendProcessList();
        BackendBlocksUpdate();

        memcache_set($this
            ->memcache->memcache, "cronjob_block_time_start", time());
        //		screenlog(__FUNCTION__.' done');

    }

    public function actionRunLoop2()
    {
        //		screenlog(__FUNCTION__);
        set_time_limit(0);

        $this->monitorApache();

        BackendCoinsUpdate();
        BackendStatsUpdate();
        BackendUsersUpdate();

        BackendUpdateServices();
        BackendUpdateDeposit();

        MonitorBTC();

        $last = memcache_get($this
            ->memcache->memcache, 'last_renting_payout2');
        if ($last + 5 * 60 < time() && !memcache_get($this
            ->memcache->memcache, 'balances_locked'))
        {
            memcache_set($this
                ->memcache->memcache, 'last_renting_payout2', time());
            BackendRentingPayout();
        }

        $last = memcache_get($this
            ->memcache->memcache, 'last_stats2');
        if ($last + 5 * 60 < time())
        {
            memcache_set($this
                ->memcache->memcache, 'last_stats2', time());
            BackendStatsUpdate2();
        }

        memcache_set($this
            ->memcache->memcache, "cronjob_loop2_time_start", time());
        //		screenlog(__FUNCTION__.' done');

    }

    public function actionRun()
    {
        //		debuglog(__METHOD__);
        set_time_limit(0);

        //		BackendRunCoinActions();
        $state = memcache_get($this
            ->memcache->memcache, 'cronjob_main_state');
        if (!$state) $state = 0;

        memcache_set($this
            ->memcache->memcache, 'cronjob_main_state', $state + 1);
        memcache_set($this
            ->memcache->memcache, "cronjob_main_state_$state", 1);

        switch ($state)
        {
            case 0:
                updateRawcoins();

                $btcusd = bitstamp_btcusd();
                if ($btcusd)
                {
                    $mining = getdbosql('db_mining');
                    if (!$mining) $mining = new db_mining;
                    $mining->usdbtc = $btcusd;
                    $mining->save();
                }

                break;

            case 1:
                if (!YAAMP_PRODUCTION) break;

                getBitstampBalances();
                getCexIoBalances();
                doBittrexTrading();
                doCrex24Trading();
                doCryptopiaTrading();
                doKrakenTrading();
                doLiveCoinTrading();
                doPoloniexTrading();
                break;

            case 2:
                if (!YAAMP_PRODUCTION) break;

                doBinanceTrading();
                doCCexTrading();
                doBleutradeTrading();
                doCryptobridgeTrading();
                doKuCoinTrading();
                doNovaTrading();
                doYobitTrading();
                doCoinsMarketsTrading();
                break;

            case 3:
                BackendPricesUpdate();
                BackendWatchMarkets();
                break;

            case 4:
                BackendBlocksUpdate();
                break;

            case 5:
                TradingSellCoins();
                break;

            case 6:
                BackendBlockFind2();
                BackendUpdatePoolBalances();
                break;

            case 7:
                NotifyCheckRules();
                BenchUpdateChips();
                break;

            default:
                memcache_set($this
                    ->memcache->memcache, 'cronjob_main_state', 0);
                BackendQuickClean();

                $t = memcache_get($this
                    ->memcache->memcache, "cronjob_main_start_time");
                $n = time();

                memcache_set($this
                    ->memcache->memcache, "cronjob_main_time", $n - $t);
                memcache_set($this
                    ->memcache->memcache, "cronjob_main_start_time", $n);
            }

            debuglog(__METHOD__ . " $state");
            memcache_set($this
                ->memcache->memcache, "cronjob_main_state_$state", 0);

            memcache_set($this
                ->memcache->memcache, "cronjob_main_time_start", time());
            if (!YAAMP_PRODUCTION) return;

            ///////////////////////////////////////////////////////////////////
            $mining = getdbosql('db_mining');
            if ($mining->last_payout + YAAMP_PAYMENTS_FREQ > time()) return;

            debuglog("--------------------------------------------------------");

            $mining->last_payout = time();
            $mining->save();

            memcache_set($this
                ->memcache->memcache, 'apache_locked', true);
            if (YAAMP_USE_NGINX) system("service nginx stop");

            sleep(10);
            BackendDoBackup();
            memcache_set($this
                ->memcache->memcache, 'apache_locked', false);

            // prevent user balances changes during payments (blocks thread)
            memcache_set($this
                ->memcache->memcache, 'balances_locked', true, 0, 300);
            BackendPayments();
            memcache_set($this
                ->memcache->memcache, 'balances_locked', false);

            BackendCleanDatabase();

            //	BackendOptimizeTables();
            debuglog('payments sequence done');
        }

    }
