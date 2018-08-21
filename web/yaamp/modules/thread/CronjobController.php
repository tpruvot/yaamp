<?php

require_once('serverconfig.php');
require_once('yaamp/defaultconfig.php');

class CronjobController extends CommonController
{
	private function monitorApache()
	{
		if(!YAAMP_PRODUCTION) return;
		if(!YAAMP_USE_NGINX) return;

		$uptime = exec('uptime');

		$apache_locked = memcache_get($this->memcache->memcache, 'apache_locked');
		if($apache_locked) return;

		$b = preg_match('/load average: (.*)$/', $uptime, $m);
		if(!$b) return;

		$e = explode(', ', $m[1]);

		$webserver = 'nginx';
		$res = exec("pgrep $webserver");
		$webserver_running = !empty($res);

		if($e[0] > 4 && $webserver_running)
		{
			debuglog('server overload!');
	//		debuglog('stopping webserver');
	//		system("service $webserver stop");
			sleep(1);
		}

		else if(!$webserver_running)
		{
			debuglog('starting webserver');
			system("service $webserver start");
		}
	}

        public function actionRunStartLightning()
        {
              debuglog(__METHOD__);
                set_time_limit(0);
                 $output = shell_exec('lightningd --bitcoin-rpcconnect=192.168.10.35 --bitcoin-rpcuser=bitcoin --bitcoin-rpcpassword=Test');
                $output = json_decode($output);
                debuglog($output);
        }

        public function actionRunLightning()
        {
              debuglog(__METHOD__);
                set_time_limit(0);

//                $this->monitorApache();

                $last_complete = memcache_get($this->memcache->memcache, "cronjob_ln_time_start");

                // debuglog("Lightning turned on ?");
                $output = shell_exec('sudo lightning-cli -J getinfo');
                $output = json_decode($output);

                $lightning = false;
                foreach ($output as $key => $out)
                        {
                        if ($key == 'id')       {
                                debuglog("[LN] 1. Lightning is turned ON: OK");
                                debuglog("[LN] My LN id = $out");
                                $lightning = true;
                                }
                        }
		                if (!$lightning)
                        debuglog("[LN] Error: Please run lightningd in a screen");
                else    {


                debuglog("[LN] 2. Check LN BTC address");
                $outpute = shell_exec("sudo lightning-cli -J dev-listaddrs");
                $output = json_decode($outpute);
                //debuglog("ok");
//              debuglog($outpute);

                $found = false;
                //if (isset($output->addresses))
                foreach ($output->addresses as $out)        {
//                      debuglog("in"); // value = ".$out["value"]." status = ".$out["status"]);
                        if (LN_MY_BTC_ADDRESS == $out->p2sh)    {
                                $found = true;
                                debuglog("[LN] LN_MY_BTC_ADDRESS found and own : OK");
                                break;
                        }
                }
                if ($found == false)    {
                        debuglog("[LN] Error: Please create a p2sh-segwit address using lightning-cli newaddr then fill LN_MY_BTC_ADDRESS");
                }
                else    {

                debuglog("[LN] 3. Check funds");

                $output = shell_exec("sudo lightning-cli -J listfunds");
                $listfunds = json_decode($output);
//              debuglog($output);

                $ln_balance = 0;

                $found = false;
                if (count($listfunds->outputs) > 0)
                foreach ($listfunds->outputs as $out)        {
//                      debuglog("in"); // value = ".$out["value"]." status = ".$out["status"]);
                        if ($out->status == "unconfirmed" && $out->output == 1 && count($listfunds->channels) > 0)      {
                                debuglog("[LN] Channel creation ongoing");
                                $found = true;
                        }
			
		                        if ($out->status == "confirmed" && $out->output == 1 && count($listfunds->channels) > 0 && $out->value > 100)      {
                                $ln_balance = $out->value;
                                debuglog("[LN] seems ok, to analyse later");
                                $found = true;
                        }
                        if ("confirmed" == $out->status && 0 == $out->output && count($listfunds->channels) > 0)    {
                                $ln_balance = $out->value;
                                $found = true;
                                debuglog("[LN] Initial funding onchain : OK");
                                break;
                        }
                }
                if ($found == false)    {
                        debuglog("[LN] Error: Please perform a Bitcoin onchain transaction to ".LN_MY_BTC_ADDRESS);
// testnet faucet : https://testnet.manu.backend.hamburg/faucet
                }
                else    {

                debuglog("LN Balance (not in channels) = ".$ln_balance." sat = ".($ln_balance/100000000)." BTC");

                debuglog("[LN] 4. Check connections");

                $output = shell_exec('sudo lightning-cli -J listpeers');
                $output = json_decode($output);
			
		                global $configLNGamePlayers;
                foreach ($configLNGamePlayers as $player)       {
//debuglog($player[0]);
                        $connected = false;
                        foreach ($output->peers as $out)        {
                                if ($out->id == $player[1])     {
                                        debuglog("Connected to player OK");
                                        $connected = true;
                                        break;
                                }
                        }
                        if ($connected == false)        {
                                debuglog("Connect to player...");
                                $ttt = 'sudo lightning-cli -J connect ' . $player[1] . ' ' . $player[2] . ' ' . $player[3]; // . "'";
                                //debuglog("OK");
                                debuglog($ttt);

                                $output = shell_exec($ttt);
                                debuglog($output);
                                // todo: handle errors to avoid this peer (into memcache ?)
                                // -1, "message" : "Connection establishment: Connection timed out
                        }
                }

                debuglog("[LN] 5. Check channels funds : cancelled !");

//                $output = shell_exec('sudo lightning-cli -J list');
//                $output = json_decode($output);
                $found = true;
/*                if (count($listfunds->channels) > 0)
                foreach ($listfunds->channels as $out)        {
//                      debuglog("in"); // value = ".$out["value"]." status = ".$out["status"]);
                        if ($out->channel_sat > 100 && isset($out->id_peer) && $out->id_peer == LN_MAIN_NODE)    {
                                $found = true;
                                debuglog("[LN] Channel credit > 100 : OK");
                                //break;
                        }
                        else    {
                                debuglog("[LN] Channel $out->peer_id with less than 100 !!! To refund ...");
                                // I dont think that the function to refund a channel is already developped in LN ...
		                                $output = shell_exec('sudo lightning-cli close '.$out->id_peer); //. ' ' . max( 16777215, round($ln_balance / LN_FRACTION)));
                                $output = json_decode($output);
                                debuglog($output);
                                break;
                        }
                }
*/
                if ($found == false)    {
                        debuglog("[LN] No channels credited: Channel credit with a fraction of the remaining funds");
                        //foreach ($configLNGamePlayers as $player)       {
                                //  code : -32602    [message] => 'Funding satoshi must be <= 16777215'
//                              $output = shell_exec('sudo lightning-cli -J fundchannel '.$out->id_peer. ' ' . min( 16777215, round($ln_balance / LN_FRACTION)));
//                              $output = json_decode($output);
//                              debuglog($output);
                        //}

                }
                else    {
                        debuglog("[LN] 6. Channel open on LN : cancelled !");
/*
                        $outpute = shell_exec('sudo lightning-cli -J listpeers');
                        $output = json_decode($outpute);
//debuglog($outpute);
                        $found = true; // false;
                        if (isset($output->peers))
                                if(count($output->peers) > 0)
                        foreach ($output->peers as $out)        {
                                debuglog("Peer ".$out->alias);
                                if (count($out->channels) > 0)
                                foreach ($out->channels as $o)        {
                                        if ($o->state == "CHANNELD_AWAITING_LOCKIN")    {
                                                debuglog("[LN] Waiting for 3 to 6 confirmations on bitcoin network. CHANNELD_AWAITING_LOCKIN:Funding needs more confirmations.");
                                        }
                                        if ($o->state == "CHANNELD_NORMAL")    {
                                                debuglog("[LN] Channel open : OK");
                                        }
                                        if ($o->state == "GOSSIPING")    {
                                                $output = shell_exec('sudo lightning-cli -J fundchannel '.$out->id_peer. ' ' . min( 16777215, round($ln_balance / LN_FRACTION)));
                                                $output = json_decode($output);
                                                debuglog($output);
                                        }
                                        if ($o->state == "ONCHAIN")
                                                && in_array("ONCHAIN:All outputs resolved: waiting 99 more blocks before forgetting channel", $o->states))      {
                                                $output = shell_exec('sudo lightning-cli -J fundchannel '.$out->id_peer. ' ' . min( 16777215, round($ln_balance / LN_FRACTION)));
                                                $output = json_decode($output);
                                                debuglog($output);
                                        }
                                }
                        }
                        debuglog("channels chekcs ended");
*/
                        if (true)       {
                                debuglog("Payment will be done");

                                if (true) { // $ln_balance > LN_MIN_PAY)        {

$db_bills = dbolist("SELECT bolt11 from invoices WHERE status='New' ORDER by id ASC");
if (!$db_bills)  {
        debuglog("Nothing to pay");
        }
else    {
        debuglog("To pay");
        foreach ($db_bills as $bill)    {
//              debuglog("Bill");
//              break;
//              }
//      }

//if (!empty($bill) && preg_match('/[^A-Za-z0-9]/', $bill)) {
//echo "<b>Please type a bolt11 testnet bill. <a href='/'>Try again</a></b>";
//      die;
//}

//              debuglog("before");
//              debuglog($bill['bolt11']);
                $oo = "sudo lightning-cli -J decodepay ".$bill['bolt11'];
		                debuglog($oo);
                $outpute = shell_exec($oo);
                $output = json_decode($outpute);
                debuglog($outpute);
                if (isset($output->message) && $output->message == "Invalid bolt11: Bad bech32 string")   {
                        // debuglog("clean invalid");
                        dborun("UPDATE invoices SET status = 'Invalid' WHERE bolt11='".$bill['bolt11']."'");
                        debuglog("Invoice with invalid bolt11");
                        break;
                        }
                if (isset($output->description))        {
                        dborun("UPDATE invoices SET description = '".addslashes(htmlentities($output->description))."' WHERE bolt11='".$bill['bolt11']."'");
                        debuglog("Update description");
                        }
                if (isset($output->payee))  {
                        dborun("UPDATE invoices SET shop = '".$output->payee."' WHERE bolt11='".$bill['bolt11']."'");
                        debuglog("Update shop");
                        }
                if (isset($output->msatoshi))  {
                        dborun("UPDATE invoices SET amount = '".$output->msatoshi."' WHERE bolt11='".$bill['bolt11']."'");
                        debuglog("Update amount");
                        }

                $oo = "sudo lightning-cli -J pay maxfeepercent=4 maxdelay=1200 bolt11=".$bill['bolt11'];
                debuglog($oo);
                $outpute = shell_exec($oo);
//              debuglog("ok");
                $output = json_decode($outpute);
                debuglog($outpute);
                if (isset($output->message) && $output->message == "Invoice expired")   {
                        dborun("UPDATE invoices SET status = 'Expired' WHERE bolt11='".$bill['bolt11']."'");
                        debuglog("Invoice expired");
                        break;
                        }

                if (isset($output->message) && $output->message == "Invalid bolt11: Bad bech32 string")   {
                        // debuglog("clean invalid");
                        dborun("UPDATE invoices SET status = 'Invalid' WHERE bolt11='".$bill['bolt11']."'");
                        debuglog("Invoice with invalid bolt11");
                        break;
                        }
		                if (isset($output->message) && (strpos($output->message == "max fee requested is")  !== false)) {
                        // Fee 2001 is 1.352941% of payment 147900; max fee requested is
                        // debuglog("clean invalid");
                        dborun("UPDATE invoices SET status = 'maxfee' WHERE bolt11='".$bill['bolt11']."'");
                        debuglog("Invoice with too much fee.");
                        break;
                        }

                if (isset($output->status) && $output->status == "complete")   {
                        // debuglog("clean invalid");
                        dborun("UPDATE invoices SET status = 'complete', exectime = '".time()."' WHERE bolt11='".$bill['bolt11']."'");
                        debuglog("Bill paid ! :-)");
                        if (isset($output->msatoshi_sent))      {
                                dborun("UPDATE invoices SET paid = '".$output->msatoshi_sent."' WHERE bolt11='".$bill['bolt11']."'");
                                }
                        break;
                        }

/*

                                        //debuglog($output);
//                                      if ($output->msatoshi == LN_MIN_PAY * 1000)     { // * 1000 because millisat
                                                $outpute = shell_exec('sudo lightning-cli -J pay '.$bill);
                                                $output = json_decode($outpute);
                                                debuglog($outpute);
                                                if ($output->code == 206) // "code" : 206, "message" : "Delay (locktime) is 576 blocks; max delay requested is 500."
                                                // https://github.com/ElementsProject/lightning/issues/1586 issue still opened
                                                {
                                                        $outpute = shell_exec('sudo lightning-cli -J pay maxdelay=577 bolt11='.$bill);
                                                        $output = json_decode($outpute);
                                                        debuglog($outpute);
                                                }
                                                if ($output->code == 200) //  "code" : 200, "message" : "Stopped retrying during payment attempt; continue monitoring with pay or listpayments"
                                                {
                                                        debuglog("[LN] Err 200: Stopped retrying during payment attempt; continue monitoring with pay or l");
                                                }
                                                if ($output->code == 205) //   "code" : 205, "message" : "Could not find a route"
                                                {
                                                         debuglog("[LN] Err 205");
                                                }
                                        }
                                        else
                                                debuglog("[LN] Bill: bad amount ".$output->msatoshi." ".(LN_MIN_PAY * 1000));

*/
break;
                }
        }


//                                      $bill = file_get_contents("");
                                }
				                        }
                        //debuglog($outpute);
                }

                //debuglog($output["id"]);
                //debuglog("Refund channel if new payment occured");

                }
                }
                }

                memcache_set($this->memcache->memcache, "cronjob_ln_time_start", time());
//              debuglog(__METHOD__);
        }
	
	
	public function actionRunBlocks()
	{
//		screenlog(__FUNCTION__);
		set_time_limit(0);

		$this->monitorApache();

		$last_complete = memcache_get($this->memcache->memcache, "cronjob_block_time_start");
		if($last_complete+(5*60) < time())
			dborun("update jobs set active=false");
		BackendBlockFind1();
		if(!memcache_get($this->memcache->memcache, 'balances_locked')) {
			BackendClearEarnings();
		}
		BackendRentingUpdate();
		BackendProcessList();
		BackendBlocksUpdate();

		memcache_set($this->memcache->memcache, "cronjob_block_time_start", time());
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

		$last = memcache_get($this->memcache->memcache, 'last_renting_payout2');
		if($last + 5*60 < time() && !memcache_get($this->memcache->memcache, 'balances_locked'))
		{
			memcache_set($this->memcache->memcache, 'last_renting_payout2', time());
			BackendRentingPayout();
		}

		$last = memcache_get($this->memcache->memcache, 'last_stats2');
		if($last + 5*60 < time())
		{
			memcache_set($this->memcache->memcache, 'last_stats2', time());
			BackendStatsUpdate2();
		}

		memcache_set($this->memcache->memcache, "cronjob_loop2_time_start", time());
//		screenlog(__FUNCTION__.' done');
	}

	public function actionRun()
	{
//		debuglog(__METHOD__);
		set_time_limit(0);

//		BackendRunCoinActions();

		$state = memcache_get($this->memcache->memcache, 'cronjob_main_state');
		if(!$state) $state = 0;

		memcache_set($this->memcache->memcache, 'cronjob_main_state', $state+1);
		memcache_set($this->memcache->memcache, "cronjob_main_state_$state", 1);

		switch($state)
		{
			case 0:
				updateRawcoins();

				$btcusd = bitstamp_btcusd();
				if($btcusd) {
					$mining = getdbosql('db_mining');
					if (!$mining) $mining = new db_mining;
					$mining->usdbtc = $btcusd;
					$mining->save();
				}

				break;

			case 1:
				if(!YAAMP_PRODUCTION) break;

				getBitstampBalances();
				getCexIoBalances();
				doBittrexTrading();
				doCryptopiaTrading();
				doKrakenTrading();
				doLiveCoinTrading();
				doPoloniexTrading();
				break;

			case 2:
				if(!YAAMP_PRODUCTION) break;

				doBinanceTrading();
				doCCexTrading();
				doBterTrading();
				doBleutradeTrading();
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
				memcache_set($this->memcache->memcache, 'cronjob_main_state', 0);
				BackendQuickClean();

				$t = memcache_get($this->memcache->memcache, "cronjob_main_start_time");
				$n = time();

				memcache_set($this->memcache->memcache, "cronjob_main_time", $n-$t);
				memcache_set($this->memcache->memcache, "cronjob_main_start_time", $n);
		}

		debuglog(__METHOD__." $state");
		memcache_set($this->memcache->memcache, "cronjob_main_state_$state", 0);

		memcache_set($this->memcache->memcache, "cronjob_main_time_start", time());
		if(!YAAMP_PRODUCTION) return;

		///////////////////////////////////////////////////////////////////

		$mining = getdbosql('db_mining');
		if($mining->last_payout + YAAMP_PAYMENTS_FREQ > time()) return;

		debuglog("--------------------------------------------------------");

		$mining->last_payout = time();
		$mining->save();

		memcache_set($this->memcache->memcache, 'apache_locked', true);
		if(YAAMP_USE_NGINX)
			system("service nginx stop");

		sleep(10);
		BackendDoBackup();
		memcache_set($this->memcache->memcache, 'apache_locked', false);

		// prevent user balances changes during payments (blocks thread)
		memcache_set($this->memcache->memcache, 'balances_locked', true, 0, 300);
		BackendPayments();
		memcache_set($this->memcache->memcache, 'balances_locked', false);

		BackendCleanDatabase();

	//	BackendOptimizeTables();
		debuglog('payments sequence done');
	}

}

