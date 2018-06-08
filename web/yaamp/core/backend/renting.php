<?php

function BackendRentingUpdate()
{
//	debuglog(__FUNCTION__);
	if(!YAAMP_RENTAL)
	{
	 	dborun("update jobs set active=false, ready=false");
		return;
	}

	dborun("update jobs set active=false where not ready");
	foreach(yaamp_get_algos() as $algo)
	{
		$rent = dboscalar("select rent from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));

		dborun("update jobs set active=true where ready and price>$rent and algo=:algo", array(':algo'=>$algo));
		dborun("update jobs set active=false where active and price<$rent and algo=:algo", array(':algo'=>$algo));
	}

	$list = getdbolist('db_jobsubmits', "status=0");
	foreach($list as $submit)
	{
		$rent = dboscalar("select rent from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$submit->algo));
		$amount = $rent * $submit->difficulty / 20116.56761169;

		$factor = yaamp_algo_mBTC_factor($submit->algo); // 1000 for sha256
		$amount /= $factor;

		$submit->amount = $amount - $amount*YAAMP_FEES_RENTING/100;
		$submit->status = 1;
		$submit->save();

		$job = getdbo('db_jobs', $submit->jobid);
		if(!$job)
		{
			$submit->delete();
			continue;
		}

		$renter = getdbo('db_renters', $job->renterid);
		if(!$renter)
		{
			$job->delete();
			$submit->delete();
			continue;
		}

		$renter->balance -= $amount;
		$renter->spent += $amount;

		if($renter->balance <= 0.00001000)
		{
			debuglog("resetting balance to 0, $renter->balance, $renter->id, $renter->address");
			$renter->balance = 0;
			dborun("update jobs set active=false, ready=false where renterid=$renter->id");
		}

		$renter->updated = time();
		$renter->save();
	}

//	debuglog(__FUNCTION__);
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////

function BackendRentingPayout()
{
//	debuglog(__FUNCTION__);

	$total_cleared = 0;
	foreach(yaamp_get_algos() as $algo)
	{
		$delay = time() - 5*60;
		dborun("delete from jobsubmits where status=2 and algo=:algo and time<$delay", array(':algo'=>$algo));

		$amount = dboscalar("select sum(amount) from jobsubmits where status=1 and algo=:algo", array(':algo'=>$algo));
		if($amount < 0.00002000) continue;

		dborun("update jobsubmits set status=2 where status=1 and algo=:algo", array(':algo'=>$algo));
		$total_cleared += $amount;

		$block = new db_blocks;
		$block->coin_id = 0;
		$block->time = time();
		$block->amount = $amount;
		$block->price = 1;
		$block->algo = $algo;
		$block->category = 'generate';
		$block->save();

		$total_hash_power = dboscalar("SELECT sum(difficulty) FROM shares where valid and algo=:algo", array(':algo'=>$algo));
		if(!$total_hash_power) continue;

		$list = dbolist("SELECT userid, sum(difficulty) as total FROM shares where valid and algo=:algo GROUP BY userid", array(':algo'=>$algo));
		foreach($list as $item)
		{
			$hash_power = $item['total'];
			if(!$hash_power) continue;

			$user = getdbo('db_accounts', $item['userid']);
			if(!$user) continue;

			$earning = new db_earnings;
			$earning->userid = $user->id;
			$earning->coinid = 0;
			$earning->blockid = $block->id;
			$earning->create_time = time();
			$earning->price = 1;
			$earning->status = 2;		// cleared

			$earning->amount = $amount * $hash_power / $total_hash_power;
			if(!$user->no_fees) $earning->amount = take_yaamp_fee($earning->amount, $algo);
			if(!empty($user->donation)) {
				$earning->amount = take_yaamp_fee($earning->amount, $algo, $user->donation);
				if ($earning->amount <= 0) continue;
			}

			$earning->save();

			$refcoin = getdbo('db_coins', $user->coinid);
			$value = $earning->amount / (($refcoin && $refcoin->price2)? $refcoin->price2: 1);

		//	$value = yaamp_convert_amount_user($coin, $earning->amount, $user);

			$user->last_earning = time();
			$user->balance += $value;
			$user->save();
		}

		$delay = time() - 5*60;
		dborun("delete from shares where algo=:algo and time<$delay", array(':algo'=>$algo));
	}

	if($total_cleared>0)
	 	debuglog("total cleared from rental $total_cleared BTC");
}

////////////////////////////////////////////////////////////////////////////////

function BackendUpdateDeposit()
{
//	debuglog(__FUNCTION__);

	$btc = getdbosql('db_coins', "symbol='BTC'");
	if(!$btc) return;

	$remote = new WalletRPC($btc);

	$info = $remote->getinfo();
	if(!$info) return;
	if(!isset($info['blocks'])) return;

	$hash = $remote->getblockhash(intval($info['blocks']));
	if(!$hash) return;

	$block = $remote->getblock($hash);
	if(!$block) return;

	if(!isset($block['time'])) return;
	if($block['time'] + 30*60 < time()) return;

	$list = $remote->listaccounts(1);
	foreach($list as $r=>$a)
	{
		if($a == 0) continue;

		$b = preg_match('/renter-prod-([0-9]+)/', $r, $m);
		if(!$b) continue;

		$renter = getdbo('db_renters', $m[1]);
		if(!$renter) continue;

		$ts = $remote->listtransactions(yaamp_renter_account($renter), 1);
		if(!$ts || !isset($ts[0])) continue;

		$moved = $remote->move(yaamp_renter_account($renter), '', $a);
		if(!$moved) continue;

		debuglog("deposit $renter->id $renter->address, $a");

		$rentertx = new db_rentertxs;
		$rentertx->renterid = $renter->id;
		$rentertx->time = time();
		$rentertx->amount = $a;
		$rentertx->type = 'deposit';
		$rentertx->tx = isset($ts[0]['txid'])? $ts[0]['txid']: '';
		$rentertx->save();

		$renter->unconfirmed = 0;
		$renter->balance += $a;
		$renter->updated = time();
		$renter->save();
	}

	$list = $remote->listaccounts(0);
	foreach($list as $r=>$a)
	{
		if($a == 0) continue;

		$b = preg_match('/renter-prod-([0-9]+)/', $r, $m);
		if(!$b) continue;

		$renter = getdbo('db_renters', $m[1]);
		if(!$renter) continue;

		debuglog("unconfirmed $renter->id $renter->address, $a");

		$renter->unconfirmed = $a;
		$renter->updated = time();
		$renter->save();
	}

	/////////////////////////////////////////////////////////////////////////////////////////////////////

	$received1 = $remote->getbalance('bittrex', 1);		//nicehash payments
	if($received1>0)
	{
		$moved = $remote->move('bittrex', '', $received1);
		debuglog("moved from bittrex $received1");

		dborun("update renters set balance=balance+$received1 where id=7");
		dborun("update renters set custom_start=custom_start+$received1 where id=7");
	}

	/////////////////////////////////////////////////////////////////////////////////////////////////////

	$fees = YAAMP_TXFEE_RENTING_WD; // 0.002

	$list = getdbolist('db_rentertxs', "type='withdraw' and tx='scheduled'");
	foreach($list as $tx)
	{
		$renter = getdbo('db_renters', $tx->renterid);
		if(!$renter) continue;

//		debuglog("$renter->balance < $tx->amount + $fees");
		$tx->amount = bitcoinvaluetoa(min($tx->amount, $renter->balance-$fees));
		if($tx->amount < $fees*2)
		{
			$tx->tx = 'failed';
			$tx->save();

			continue;
		}

		debuglog("withdraw send $renter->id $renter->address sendtoaddress($tx->address, $tx->amount)");
		$tx->tx = $remote->sendtoaddress($tx->address, round($tx->amount, 8));

		if(!$tx->tx)
		{
			$tx->tx = 'failed';
			$tx->save();

			continue;
		}

		$renter->balance -= $tx->amount+$fees;
		$renter->balance = max($renter->balance, 0);

		dborun("update renters set balance=$renter->balance where id=$renter->id");

		$tx->save();

		if($renter->balance <= 0.0001)
			dborun("update jobs set active=false, ready=false where id=$renter->id");
	}

}







