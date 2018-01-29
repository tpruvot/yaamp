<?php

class ApiController extends CommonController
{
	public $defaultAction='status';

	/////////////////////////////////////////////////

//	debuglog("saving renter {$_SERVER['REMOTE_ADDR']} $renter->address");

	public function actionStatus()
	{
		$client_ip = arraySafeVal($_SERVER,'REMOTE_ADDR');
		$whitelisted = isAdminIP($client_ip);
		if (!$whitelisted && is_file(YAAMP_LOGS.'/overloaded')) {
			header('HTTP/1.0 503 Disabled, server overloaded');
			return;
		}
		if(!$whitelisted && !LimitRequest('api-status', 10)) {
			return;
		}

		$memcache = controller()->memcache->memcache;
		$json = memcache_get($memcache, "api_status");

		if (!empty($json)) {
			echo $json;
			return;
		}

		$stats = array();
		foreach(yaamp_get_algos() as $i=>$algo)
		{
			$coins = (int) controller()->memcache->get_database_count_ex("api_status_coins-$algo",
				'db_coins', "enable and visible and auto_ready and algo=:algo", array(':algo'=>$algo));

			if (!$coins) continue;

			$workers = (int) controller()->memcache->get_database_scalar("api_status_workers-$algo",
				"select COUNT(id) FROM workers WHERE algo=:algo",
				array(':algo'=>$algo)
			);

			$hashrate = controller()->memcache->get_database_scalar("api_status_hashrate-$algo",
				"select hashrate from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));

			$price = controller()->memcache->get_database_scalar("api_status_price-$algo",
				"select price from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));

			$price = bitcoinvaluetoa(take_yaamp_fee($price/1000, $algo));

			$rental = controller()->memcache->get_database_scalar("api_status_rental-$algo",
				"select rent from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));

			$rental = bitcoinvaluetoa($rental);

			$t = time() - 24*60*60;

			$avgprice = controller()->memcache->get_database_scalar("api_status_avgprice-$algo",
				"select avg(price) from hashrate where algo=:algo and time>$t", array(':algo'=>$algo));

			$avgprice = bitcoinvaluetoa(take_yaamp_fee($avgprice/1000, $algo));

			$total1 = controller()->memcache->get_database_scalar("api_status_total-$algo",
				"select sum(amount*price) from blocks where category!='orphan' and time>$t and algo=:algo", array(':algo'=>$algo));

			$hashrate1 = (double) controller()->memcache->get_database_scalar("api_status_avghashrate-$algo",
				"select avg(hashrate) from hashrate where time>$t and algo=:algo", array(':algo'=>$algo));

			$algo_unit_factor = yaamp_algo_mBTC_factor($algo);
			$btcmhday1 = $hashrate1 > 0 ? mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 1000 * $algo_unit_factor) : 0;

			$fees = yaamp_fee($algo);
			$port = getAlgoPort($algo);

			$stat  = array(
				"name" => $algo,
				"port" => (int) $port,
				"coins" => $coins,
				"fees" => (double) $fees,
				"hashrate" => (double) $hashrate,
				"workers" => (int) $workers,
				"estimate_current" => $price,
				"estimate_last24h" => $avgprice,
				"actual_last24h" => $btcmhday1,
				"hashrate_last24h" => (double) $hashrate1,
			);
			if(YAAMP_RENTAL) {
				$stat["rental_current"] = $rental;
			}

			$stats[$algo] = $stat;
		}

		ksort($stats);

		$json = json_encode($stats);
		echo $json;

		memcache_set($memcache, "api_status", $json, MEMCACHE_COMPRESSED, 30);
	}

	public function actionCurrencies()
	{
		$client_ip = arraySafeVal($_SERVER,'REMOTE_ADDR');
		$whitelisted = isAdminIP($client_ip);
		if (!$whitelisted && is_file(YAAMP_LOGS.'/overloaded')) {
			header('HTTP/1.0 503 Disabled, server overloaded');
			return;
		}
		if(!$whitelisted && !LimitRequest('api-currencies', 10)) {
			return;
		}

		$memcache = controller()->memcache->memcache;

		$json = memcache_get($memcache, "api_currencies");
		if (empty($json)) {

			$data = array();
			$coins = getdbolist('db_coins', "enable AND visible AND auto_ready AND IFNULL(algo,'PoS')!='PoS' ORDER BY symbol");
			foreach ($coins as $coin)
			{
				$symbol = $coin->symbol;

				$last = dborow("SELECT height, time FROM blocks ".
					"WHERE coin_id=:id AND category IN ('immature','generate') ORDER BY height DESC LIMIT 1",
					array(':id'=>$coin->id)
				);
				$lastblock = (int) arraySafeVal($last,'height');
				$timesincelast = $timelast = (int) arraySafeVal($last,'time');
				if ($timelast > 0) $timesincelast = time() - $timelast;

				$workers = (int) dboscalar("SELECT count(W.userid) AS workers FROM workers W ".
					"INNER JOIN accounts A ON A.id = W.userid ".
					"WHERE W.algo=:algo AND A.coinid IN (:id, 6)", // 6: btc id
					array(':algo'=>$coin->algo, ':id'=>$coin->id)
				);

				$since = $timelast ? $timelast : time() - 60*60;
				$shares = dborow("SELECT count(id) AS shares, SUM(difficulty) AS coin_hr FROM shares WHERE time>$since AND algo=:algo AND coinid IN (0,:id)",
					array(':id'=>$coin->id,':algo'=>$coin->algo)
				);

				$t24 = time() - 24*60*60;
				$res24h = controller()->memcache->get_database_row("history_item2-{$coin->id}-{$coin->algo}",
					"SELECT COUNT(id) as a, SUM(amount*price) as b FROM blocks ".
					"WHERE coin_id=:id AND NOT category IN ('orphan','stake','generated') AND time>$t24 AND algo=:algo",
					array(':id'=>$coin->id, ':algo'=>$coin->algo)
				);

				// Coin hashrate, we only store the hashrate per algo in the db,
				// we need to compute the % of the coin compared to others with the same algo
				if ($workers > 0) {

					$algohr = (double) dboscalar("SELECT SUM(difficulty) AS algo_hr FROM shares WHERE time>$since AND algo=:algo",array(':algo'=>$coin->algo));
					$factor = ($algohr > 0 && !empty($shares)) ? (double) $shares['coin_hr'] / $algohr : 1.;
					$algo_hashrate = controller()->memcache->get_database_scalar("api_status_hashrate-{$coin->algo}",
						"SELECT hashrate FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1", array(':algo'=>$coin->algo)
					);

				} else {
					$factor = $algo_hashrate = 0;
				}

				$btcmhd = yaamp_profitability($coin);
				$btcmhd = mbitcoinvaluetoa($btcmhd);

				$data[$symbol] = array(
					'algo' => $coin->algo,
					'port' => getAlgoPort($coin->algo),
					'name' => $coin->name,
					'height' => (int) $coin->block_height,
					'workers' => $workers,
					'shares' =>  (int) arraySafeVal($shares,'shares'),
					'hashrate' => round($factor * $algo_hashrate),
					'estimate' => $btcmhd,
					//'percent' => round($factor * 100, 1),
					'24h_blocks' => (int) arraySafeVal($res24h,'a'),
					'24h_btc' => round(arraySafeVal($res24h,'b',0), 8),
					'lastblock' => $lastblock,
					'timesincelast' => $timesincelast,
				);

				if (!empty($coin->symbol2))
					$data[$symbol]['symbol'] = $coin->symbol2;
			}
			$json = json_encode($data);
			memcache_set($memcache, "api_currencies", $json, MEMCACHE_COMPRESSED, 15);
		}

		echo str_replace("},","},\n", $json);
	}

	public function actionWallet()
	{
		if(!LimitRequest('api-wallet', 10)) {
			return;
		}
		if (is_file(YAAMP_LOGS.'/overloaded')) {
			header('HTTP/1.0 503 Disabled, server overloaded');
			return;
		}
		$wallet = getparam('address');

		$user = getuserparam($wallet);
		if(!$user || $user->is_locked) return;

		$total_unsold = yaamp_convert_earnings_user($user, "status!=2");

		$t = time() - 24*60*60;
		$total_paid = bitcoinvaluetoa(controller()->memcache->get_database_scalar("api_wallet_paid-".$user->id,
			"SELECT SUM(amount) FROM payouts WHERE time >= $t AND account_id=".$user->id));

		$balance = bitcoinvaluetoa($user->balance);
		$total_unpaid = bitcoinvaluetoa($balance + $total_unsold);
		$total_earned = bitcoinvaluetoa($total_unpaid + $total_paid);

		$coin = getdbo('db_coins', $user->coinid);
		if(!$coin) return;

		echo "{";
		echo "\"currency\": \"{$coin->symbol}\", ";
		echo "\"unsold\": $total_unsold, ";
		echo "\"balance\": $balance, ";
		echo "\"unpaid\": $total_unpaid, ";
		echo "\"paid24h\": $total_paid, ";
		echo "\"total\": $total_earned";
		echo "}";
	}

	public function actionWalletEx()
	{
		$wallet = getparam('address');
		if (is_file(YAAMP_LOGS.'/overloaded')) {
			header('HTTP/1.0 503 Disabled, server overloaded');
			return;
		}
		if(!LimitRequest('api-wallet', 60)) {
			return;
		}

		$user = getuserparam($wallet);
		if(!$user || $user->is_locked) return;

		$total_unsold = yaamp_convert_earnings_user($user, "status!=2");

		$t = time() - 24*60*60;
		$total_paid = bitcoinvaluetoa(controller()->memcache->get_database_scalar("api_wallet_paid-".$user->id,
			"SELECT SUM(amount) FROM payouts WHERE time >= $t AND account_id=".$user->id));

		$balance = bitcoinvaluetoa($user->balance);
		$total_unpaid = bitcoinvaluetoa($balance + $total_unsold);
		$total_earned = bitcoinvaluetoa($total_unpaid + $total_paid);

		$coin = getdbo('db_coins', $user->coinid);
		if(!$coin) return;

		echo "{";
		echo "\"currency\": ".json_encode($coin->symbol).", ";
		echo "\"unsold\": $total_unsold, ";
		echo "\"balance\": $balance, ";
		echo "\"unpaid\": $total_unpaid, ";
		echo "\"paid24h\": $total_paid, ";
		echo "\"total\": $total_earned, ";

		echo "\"miners\": ";
		echo "[";

		$workers = getdbolist('db_workers', "userid={$user->id} ORDER BY password");
		foreach($workers as $i=>$worker)
		{
			$user_rate1 = yaamp_worker_rate($worker->id, $worker->algo);
			$user_rate1_bad = yaamp_worker_rate_bad($worker->id, $worker->algo);

			if($i) echo ", ";

			echo "{";
			echo "\"version\": ".json_encode($worker->version).", ";
			echo "\"password\": ".json_encode($worker->password).", ";
			echo "\"ID\": ".json_encode($worker->worker).", ";
			echo "\"algo\": \"{$worker->algo}\", ";
			echo "\"difficulty\": ".doubleval($worker->difficulty).", ";
			echo "\"subscribe\": ".intval($worker->subscribe).", ";
			echo "\"accepted\": ".round($user_rate1,3).", ";
			echo "\"rejected\": ".round($user_rate1_bad,3);
			echo "}";
		}

		echo "]";
		echo "}";
	}

	public function actionRental()
	{
		if(!LimitRequest('api-rental', 10)) return;

		$key = getparam('key');
		$renter = getdbosql('db_renters', "apikey=:apikey", array(':apikey'=>$key));
		if(!$renter) return;

		$balance = bitcoinvaluetoa($renter->balance);
		$unconfirmed = bitcoinvaluetoa($renter->unconfirmed);

		echo "{";
		echo "\"balance\": $balance, ";
		echo "\"unconfirmed\": $unconfirmed, ";

		echo "\"jobs\": [";
		$list = getdbolist('db_jobs', "renterid=$renter->id");
		foreach($list as $i=>$job)
		{
			if($i) echo ", ";

			$hashrate = yaamp_job_rate($job->id);
			$hashrate_bad = yaamp_job_rate_bad($job->id);

			echo '{';
			echo "\"jobid\": \"$job->id\", ";
			echo "\"algo\": \"$job->algo\", ";
			echo "\"price\": \"$job->price\", ";
			echo "\"hashrate\": \"$job->speed\", ";
			echo "\"server\": \"$job->host\", ";
			echo "\"port\": \"$job->port\", ";
			echo "\"username\": \"$job->username\", ";
			echo "\"password\": \"$job->password\", ";
			echo "\"started\": \"$job->ready\", ";
			echo "\"active\": \"$job->active\", ";
			echo "\"accepted\": \"$hashrate\", ";
			echo "\"rejected\": \"$hashrate_bad\", ";
			echo "\"diff\": \"$job->difficulty\"";

			echo '}';
		}

		echo "]}";
	}

	public function actionRental_price()
	{
		$key = getparam('key');
		$renter = getdbosql('db_renters', "apikey=:apikey", array(':apikey'=>$key));
		if(!$renter) return;

		$jobid = getparam('jobid');
		$price = getparam('price');

		$job = getdbo('db_jobs', $jobid);
		if($job->renterid != $renter->id) return;

		$job->price = $price;
		$job->time = time();
		$job->save();
	}

	public function actionRental_hashrate()
	{
		$key = getparam('key');
		$renter = getdbosql('db_renters', "apikey=:apikey", array(':apikey'=>$key));
		if(!$renter) return;

		$jobid = getparam('jobid');
		$hashrate = getparam('hashrate');

		$job = getdbo('db_jobs', $jobid);
		if($job->renterid != $renter->id) return;

		$job->speed = $hashrate;
		$job->time = time();
		$job->save();
	}

	public function actionRental_start()
	{
		$key = getparam('key');
		$renter = getdbosql('db_renters', "apikey=:apikey", array(':apikey'=>$key));
		if(!$renter || $renter->balance<=0) return;

		$jobid = getparam('jobid');

		$job = getdbo('db_jobs', $jobid);
		if($job->renterid != $renter->id) return;

		$job->ready = true;
		$job->time = time();
		$job->save();
	}

	public function actionRental_stop()
	{
		$key = getparam('key');
		$renter = getdbosql('db_renters', "apikey=:apikey", array(':apikey'=>$key));
		if(!$renter) return;

		$jobid = getparam('jobid');

		$job = getdbo('db_jobs', $jobid);
		if($job->renterid != $renter->id) return;

		$job->ready = false;
		$job->time = time();
		$job->save();
	}

// 	public function actionNodeReport()
// 	{
// 		$name = getparam('name');
// 		$uptime = getparam('uptime');

// 		$server = getdbosql('db_servers', "name='$name'");
// 		if(!$server)
// 		{
// 			$server = new db_servers;
// 			$server->name = $name;
// 		}

// 		$server->uptime = $uptime;
// 		$server->save();
// 	}

}

// function dummy()
// {
// 	$uptime = system('uptime');
// 	$name = system('hostname');

// 	fetch_url("http://".YAAMP_SITE_URL."/api/nodereport?name=$name&uptime=$uptime");
// }





