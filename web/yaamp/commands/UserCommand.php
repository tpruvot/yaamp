<?php
/**
 * UserCommand is a console command, to delete an user and its history
 *
 * To use this command, enter the following on the command line:
 * <pre>
 * yiic user delete <id|addr>
 * </pre>
 *
 * @property string $help The command description.
 *
 */
class UserCommand extends CConsoleCommand
{
	/**
	 * Execute the action.
	 * @param array $args command line parameters specific for this command
	 * @return integer non zero application exit code after printing help
	 */
	public function run($args)
	{
		$runner=$this->getCommandRunner();
		$commands=$runner->commands;

		if (!isset($args[0]) || $args[0] == 'help') {
			echo "YiiMP user command(s)\n";
			echo "Usage: yiimp user delete <id|address>\n";
			echo "       yiimp user swap <address> <symbol> - assign symbol\n";
			echo "       yiimp user search <ip>\n";
			echo "       yiimp user purge [days] (default 180)\n";
			return 1;

		} else if ($args[0] == 'delete') {
			$id = -1; $addr = '';
			if (strlen($args[1]) < 26)
				$id = (int) $args[1];
			else
				$addr = $args[1];
			$this->deleteUser($id, $addr);
			return 0;

		} else if ($args[0] == 'purge') {
			$days = (int) arraySafeVal($args, 1, '180');
			if ($days < 1) return 1;
			$inter = new DateInterval('P'.$days.'D');
			$since = new DateTime;
			$since->sub($inter);
			$nb =  $this->purgeInactiveUsers($since->getTimestamp());
			echo "$nb user(s) deleted\n";
			return 0;

		} else if ($args[0] == 'search') {
			if (!isset($args[1]))
				die("usage: yiimp user search <ip>\n");
			$addr = $args[1];
			$this->searchUserByIP($addr);

		} else if ($args[0] == 'swap') {
			if (!isset($args[2]))
				die("usage: yiimp user swap <address> <symbol> [force] - assign symbol\n");
			$addr = $args[1];
			$symbol = $args[2];
			$force = arraySafeVal($args, 3, false);
			$this->swapUserCoin($addr, $symbol, $force);
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
	 * Delete user by id or wallet address
	 */
	public function deleteUser($id, $addr)
	{
		$nbDeleted = 0;

		$users = new db_accounts;
		$user = $users->find(array('condition'=>'id=:id OR username=:username', 'params'=>array(
			':id'=>$id, ':username'=>$addr,
		)));
		if ($user && $user->id)	{
			$name = $user->username;
			$nbDeleted += $user->deleteWithDeps();
			echo "user $name deleted\n";
		} else {
			echo "user not found!\n";
		}
	}

	/**
	 * Delete users inactive since a timestamp
	 */
	public function purgeInactiveUsers($ts)
	{
		$nbDeleted = 0;

		$users = new db_accounts;
		$rows = $users->findAll(array(
			// to improve with earnings table
			'condition'=>'last_earning<:ts AND last_earning IS NOT NULL'.
				' AND IFNULL(balance,0)=0 AND IFNULL(donation,0)=0 AND IFNULL(no_fees,0)=0',
			'params'=>array(':ts'=>intval($ts)),
			'order'=>'id ASC'
		));

		if (empty($rows)) {
			$date = strftime("%Y-%m-%d", $ts);
			echo "no user(s) found which are inactive since $date!\n";
			return 0;
		}

		foreach ($rows as $user) {
			if ($user && $user->id)	{
				$payouts = dboscalar('SELECT SUM(amount) FROM payouts WHERE account_id='.$user->id);
				$earnings = dboscalar('SELECT SUM(amount) FROM earnings WHERE userid='.$user->id);
				$workers = dboscalar('SELECT COUNT(*) FROM workers WHERE userid='.$user->id);
				if ($payouts == 0 && $earnings == 0 && $workers == 0) {
					echo "$user->username\n";
					$nbDeleted += $user->deleteWithDeps();
				}
			}
		}

		return $nbDeleted;
	}

	/**
	 * Search users by worker ip
	 */
	public function searchUserByIP($ip)
	{
		$workers = new db_workers;
		$rows = $workers->findAll(array(
			'condition'=>'ip LIKE :ip',
			'params'=>array(':ip'=>"%$ip%"),
			'limit'=>25,
			'order'=>'id DESC',
		));

		if (empty($rows)) {
			echo "no user(s) found with this ip\n";
			return 0;
		}

		foreach ($rows as $worker) {
			$user = getdbo('db_accounts', $worker->userid);
			if (!$user) continue;
			$time = strftime("%Y-%m-%d %H:%M:%S", $worker->time);
			echo "$time\t{$user->username}\t{$worker->ip}\t{$worker->algo}\n";
		}

		return 0;
	}

	/**
	 * Manually assign the right currency symbol to an user (for yiimp mode without exchange)
	 */
	public function swapUserCoin($addr, $symbol, $force=false)
	{
		$user = getdbosql('db_accounts', 'username=:addr', array(':addr'=>$addr));
		if (!$user) die("invalid user address\n");

		$coin = getdbosql('db_coins', 'symbol=:sym AND installed AND enable', array(':sym'=>$symbol));
		if (!$coin) die("invalid symbol\n");

		$user->coinid = $coin->id;
		if ($user->balance > 0 && !$force)
			die("Sorry, user has a pending balance of ".bitcoinvaluetoa($user->balance)."!\n");

		$payouts = dboscalar('SELECT SUM(amount) FROM payouts WHERE account_id='.$user->id);
		if ($payouts > 0) die("Sorry, user already had payouts!\n");

		$algo = dboscalar('SELECT algo FROM workers WHERE userid='.$user->id);
		if (!empty($algo) && $coin->algo != $algo) {
			if (!YAAMP_ALLOW_EXCHANGE) die("User is currently mining on $algo stratum!\n");
			else echo("note: user is currently mining on $algo stratum...\n");
		}

		$remote = new WalletRPC($coin);
		$b = $remote->validateaddress($user->username);
		if(!arraySafeVal($b,'isvalid')) die("Sorry, bad address for this coin!\n");

		$nbUpd = dborun("UPDATE earnings SET status=0 WHERE status=-1 AND coinid=".$coin->id);
		$blocks = getdbolist('db_blocks', "coin_id=:coinid AND id IN ".
			"(SELECT blockid FROM earnings WHERE coinid=:coinid AND userid=:userid)",
			array(':coinid'=>$coin->id, ':userid'=>$user->id)
		);
		$nbConf = 0;
		foreach ($blocks as $b) {
			if ($b->category == 'generate') {
				$nbConf += dborun("UPDATE earnings SET status=1, mature_time=:time".
					" WHERE blockid=:blockid AND userid=:userid AND status<1",
					array(':time'=>time(), ':blockid'=>$b->id, ':userid'=>$user->id)
				);
			}
		}

		if ($user->save())
			echo "user coin $symbol assigned, $nbUpd invalid earnings updated, $nbConf confirmed.\n";
	}
}
