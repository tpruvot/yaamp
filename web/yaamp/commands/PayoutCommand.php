<?php
/**
 * PayoutCommand is a console command :
 *  - check: compare wallet's chain history and database payouts
 *
 * To use this command, enter the following on the command line:
 * <pre>
 * yiimp payout check LYB
 * </pre>
 *
 * @property string $help The command description.
 *
 */
class PayoutCommand extends CConsoleCommand
{
	protected $basePath;

	/**
	 * Execute the action.
	 * @param array $args command line parameters specific for this command
	 * @return integer non zero application exit code after printing help
	 */
	public function run($args)
	{
		$runner=$this->getCommandRunner();
		$commands=$runner->commands;

		$root = realpath(Yii::app()->getBasePath().DIRECTORY_SEPARATOR.'..');
		$this->basePath = str_replace(DIRECTORY_SEPARATOR, '/', $root);

		$command = arraySafeVal($args,0);

		if (!isset($args[0]) || $args[0] == 'help') {

			echo "Yiimp payout command\n";
			echo "Usage: yiimp payout check <symbol> [fixit]\n";
			echo "Usage:       payout coinswaps\n";
			echo "Usage:       payout confirmations <symbol>\n";
			if (YIIMP_CLI_ALLOW_TXS)
			echo "Usage:       payout redotx <txid>\n";

			return 1;

		} elseif ($command == 'check') {

			if (!isset($args[1]))
				die("Usage: yiimp payout check <symbol> [fixit]\n");

			$coinsym = arraySafeVal($args,1);
			$fixit = arraySafeVal($args,2); // optional

			$nbUpdated  = $this->checkPayouts($coinsym, $fixit);
			echo "total updated: $nbUpdated\n";
			return 0;

		} elseif ($command == 'coinswaps') {
			$this->checkCoinSwaps($args);
			return 0;

		} elseif ($command == 'confirmations') {
			$this->checkPayoutsConfirmations($args);
			return 0;

		} elseif ($command == 'redotx' && YIIMP_CLI_ALLOW_TXS) {
			$this->redoTransaction($args);
			return 0;
		}
	}

	/**
	 * Provides the command description.
	 * @return string the command description.
	 */
	public function getHelp()
	{
		return $this->run(array('help'));
	}

	/**
	 * Check in a wallet completed payouts and missing/extra ones
	 */
	public function checkPayouts($symbol, $fixit)
	{
		$nbUpdated = 0; $nbCreated = 0;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if (!$coin) {
			echo "wallet $symbol not found!\n";
			return 0;
		}

		// failed payouts, generally related to bad wallet 'accounts' balances (VNL)
		$dbPayouts = new db_payouts;
		$min_payout = max($coin->txfee, floatval(YAAMP_PAYMENTS_MINI));
		$failed_payouts = $dbPayouts->with('account')->findAll(array(
			'condition'=>"tx IS NULL AND amount > $min_payout AND account.coinid = ".$coin->id,
			'order'=>'time DESC',
		));

		$condOr = '';
		if (!empty($failed_payouts)) {
			$ids = array();
			$sum = 0.;
			foreach ($failed_payouts as $payout) {
				$uid = (int) $payout['account_id'];
				$ids[$uid] = floatval($payout['amount']) + arraySafeVal($ids, $uid, 0.);
				$sum += floatval($payout['amount']);
			}
			echo "failed payouts detected for ".count($ids)." account(s), $sum {$coin->symbol}\n";
			$condOr = "OR A.id IN (".implode(',', array_keys($ids)).')';
		}

		// Get users using the coin...
		$users = dbolist("SELECT DISTINCT A.id AS userid, A.username AS username ".
			"FROM accounts A LEFT JOIN coins C ON C.id = A.coinid ".
			"WHERE A.coinid={$coin->id} AND (A.balance > 0.0 $condOr)"
		);
		$ids = array();
		foreach ($users as $uids) {
			$uid = (int) $uids['userid'];
			$ids[$uid] = $uids['username'];
		}
		if (empty($ids))
			return 0;

		// Get their payouts
		$dbPayouts = new db_payouts;
		$payouts = $dbPayouts->findAll(array(
			'condition'=>"account_id IN (".implode(',',array_keys($ids)).')',
			'order'=>'time DESC',
		));

		if (empty($payouts) || empty($ids))
			return 0;

		$DCR = ($coin->rpcencoding == 'DCR' || $coin->getOfficialSymbol() == 'DCR');
		$DGB = ($coin->rpcencoding == 'DGB' || $coin->getOfficialSymbol() == 'DGB');

		$remote = new WalletRPC($coin);
		$account = '';
		if ($DCR || $DGB) $account = '*';
		$rawtxs = $remote->listtransactions($account, 25000);

		foreach ($ids as $uid => $user_addr)
		{
			$totalsent = 0.0; $totalpayouts = 0.0;

			// check for previous resolved problems
			$since = (int) dboscalar("SELECT MAX(time) as time FROM payouts WHERE account_id=:uid AND fee > 0.0",
				array(':uid'=>$uid)
			);

			// else check the last week
			if (empty($since)) $since = time()-(7*24*3600);

			// Get db payouts
			$payouts = $dbPayouts->findAll(array(
				'condition'=>"account_id=$uid AND time >= ".intval($since),
				'order'=>'time DESC',
			));
			if (empty($payouts)) $payouts = array();

			echo "$user_addr payouts since ".strftime('%F %c', $since).": ".count($payouts)."\n";

			// filter user raw transactions
			foreach ($rawtxs as $ntx => $tx) {
				$time = arraySafeVal($tx,'time');
				if ($time < $since) continue;
				$match = false;
				if (arraySafeVal($tx,'category') == 'send' && arraySafeVal($tx,'address') == $user_addr) {
					$amount = abs(arraySafeVal($tx,'amount'));
					$txid = arraySafeVal($tx,'txid');
					$totalsent += $amount + (float) abs(arraySafeVal($tx,'fee'));

					foreach ($payouts as $payout) {
						if ($payout->tx == $txid && round($payout->amount) == round($amount)) {
							$totalpayouts += $amount + (float) abs(arraySafeVal($tx,'fee'));
							$match = true;
							if (arraySafeVal($tx, 'confirmations') > 5) {
								$payout->completed = 1;
								$nbUpdated += $payout->save();
								//echo "tx {$payout->tx} {$payout->amount} $symbol confirmed\n";
							}
							break;
						} elseif ($payout->tx == $txid) {
							echo "tx {$payout->tx} {$payout->amount} $symbol != $amount $symbol (possible match)\n";
							$match = true;
						}
					}
					// These extra payouts will be shown during 24h in the user wallet txs
					if (!$match && $fixit == 'fixit') {
						// do it manually with the fixit cmdline argument (need manual checks)
						$payout = new db_payouts;
						$payout->account_id = $uid;
						$payout->tx = $txid;
						$payout->time = $time;
						$payout->completed = 1;
						$payout->amount = $amount;
						$payout->fee = abs(arraySafeVal($tx,'fee'));
						$nbCreated += $payout->save();
						$user = getdbo('db_accounts', $uid);
						if ($user) {
							$user->balance = floatval($user->balance) - $amount;
							dborun("UPDATE balanceuser SET balance = (balance - $amount) WHERE userid=$uid AND time>=$time");
							$user->save();
						}
						$match = true;
						$time = strftime('%F %c', $time);
						echo "extra user tx $txid $time $amount $symbol\n";
					}
				}
				//if (0 && !$match && arraySafeVal($tx,'category') == 'send') {
				//	$time = strftime('%F %c', $time);
				//	$txid = arraySafeVal($tx,'txid');
				//	$amount = abs(arraySafeVal($tx,'amount'));
				//	$address = arraySafeVal($tx,'address');
				//	echo "unknown tx $txid $time $amount $symbol to $address\n";
				//}
			}
			// get the extra payouts
			$payouts = $dbPayouts->findAll(array(
				'condition'=>"completed=0 AND account_id=$uid AND time > ".intval($since),
				'order'=>'time DESC',
			));

			$totaldiff = $totalsent - $totalpayouts;
			if ($totaldiff > 0.0) {
				// search payouts not in db
				foreach ($payouts as $payout) {
					$time = strftime('%F %c', $payout->time);
					echo "extra db tx: $time {$payout->tx} {$payout->amount} $symbol\n";
				}
			}
			if ($totaldiff != 0.0)
				echo "$user_addr: Total sent $totalsent (real), $totalpayouts (db) -> Diff $totaldiff $symbol\n";
			else
				echo "$user_addr: ok\n";
		}

		if ($nbCreated)
			echo "$nbUpdated payouts confirmed, $nbCreated payouts created\n";
		else if ($nbUpdated)
			echo "$nbUpdated payouts confirmed\n";
		return $nbCreated;
	}

	function checkCoinSwaps($args)
	{
		// check the last week
		$since = time()-(7*24*3600);

		$data = dbolist("SELECT C.symbol, C.algo, C2.symbol, C2.algo, A.username FROM payouts P ".
			"INNER JOIN coins C ON P.idcoin=C.id ".
			"INNER JOIN accounts A ON P.account_id = A.id ".
			"INNER JOIN coins C2 ON A.coinid = C2.id ".
			"WHERE P.time>$since AND A.coinid != P.idcoin"
		);
		if (!empty($data)) {
			echo "user payouts to check:\n";
			foreach ($data as $row) {
				echo json_encode($row)."\n";
			}
		} else {
			echo "payouts: all fine\n";
		}

		$data = dbolist("SELECT DISTINCT C.symbol, C.algo, A.username FROM earnings E ".
			"INNER JOIN accounts A ON E.userid = A.id ".
			"INNER JOIN coins C ON E.coinid = C.id ".
			"WHERE E.create_time>$since AND E.status < 0"
		);
		if (!empty($data)) {
			echo "user earnings to check:\n";
			foreach ($data as $row) {
				echo json_encode($row)."\n";
			}
		} else {
			echo "earnings: all fine\n";
		}
	}

	/**
	 * Can be used to redo a payment made on a bad fork...
	 */
	protected function redoTransaction($args)
	{
		$txid = arraySafeVal($args, 1);
		if (empty($txid))
			die("Usage..\n");

		$payouts = getdbolist('db_payouts', "tx=:txid", array(':txid'=>$txid));
		if (empty($payouts))
			die("Invalid payout txid\n");
		echo "users to pay: ".count($payouts)."\n";

		$payout = $payouts[0];
		$coin = getdbo('db_coins', $payout->idcoin);
		if (!$coin || !$coin->installed)
			die("Invalid payout coin id\n");

		$relayfee = 0.0001;

		$dests = array(); $total = 0.;
		foreach ($payouts as $payout) {
			$user = getdbo('db_accounts', $payout->account_id);
			if (!$user || $user->coinid != $coin->id) continue;
			if (doubleval($payout->amount) < $relayfee) continue; // dust if < relayfee
			$dests[$user->username] = doubleval($payout->amount);
			$total += doubleval($payout->amount);
		}

		echo "$total {$coin->symbol} to pay...\n";

		$nbnew = 0;
		$remote = new WalletRPC($coin);
		$res = $remote->sendmany((string) $coin->account, $dests);
		if (!$res) var_dump($remote->error);
		else {
			$new_txid = $res;
			echo "txid: $new_txid\n";
			foreach ($payouts as $payout) {
				if (doubleval($payout->amount) < $relayfee) continue;
				$p = new db_payouts;
				$p->time = time();
				$p->idcoin = $coin->id;
				$p->amount = doubleval($payout->amount);
				$p->account_id = $payout->account_id;
				$p->completed = 1;
				$p->fee = 0;
				$p->tx = $new_txid;
				$nbnew += $p->insert();
			}
			echo "payouts rows added: $nbnew\n";
			if ($nbnew == count($payouts)) {
				$res = dborun("UPDATE payouts SET completed=0, tx='orphaned', memoid='redo' WHERE tx=:txid", array(':txid'=>$txid));
				echo "payouts marked as 'orphaned': $res\n";
			}
		}
		return $nbnew;
	}

	/**
	 * List the last payouts made for a wallet and check if the tx have confirmations
	 */
	protected function checkPayoutsConfirmations($args)
	{
		$symbol = arraySafeVal($args, 1);
		if (empty($symbol))
			die("payout confirmations <symbol>\n");

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if (!$coin) {
			echo "wallet $symbol not found!\n";
			return 0;
		}
		$since = time() - (72 * 3600);
		$data = dbolist("SELECT P.tx, MAX(P.time) as time, SUM(P.amount) as amount FROM payouts P ".
			"WHERE P.time>$since AND P.idcoin=".$coin->id." ".
			"GROUP BY P.tx ORDER BY time DESC"
		);

		$remote = new WalletRPC($coin);
		foreach ($data as $row) {
			$txid = $row['tx'];
			$tx = $remote->gettransaction($txid);
			echo strftime('%Y-%m-%d %H:%M', $row['time'])." $txid ".$tx['confirmations'].
				" confs (".altcoinvaluetoa($row['amount'],4)." $symbol, fees: ".bitcoinvaluetoa($tx['fee']).")\n";
		}
	}
}
