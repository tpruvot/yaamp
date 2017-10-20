<?php
/**
 * GraphesCommand is a console command, to check for holes in stats history
 */
class GraphesCommand extends CConsoleCommand
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

		$symbol = arraySafeVal($args, 0);

		if (!isset($args[0]) || $args[0] == 'help') {

			echo "Yiimp graphes command\n";
			echo "Usage: yiimp graphes poolrate [algo] - find holes in hashrate graphes\n";
			echo "       yiimp graphes hashrate [algo] - find holes in user hashrate graphes\n";
			echo "       yiimp graphes balances [coin] - find holes in user balances graphes\n";

		} else if ($args[0] == 'poolrate') {
			$algos = yaamp_get_algos();
			foreach ($algos as $algo) {
				$algo = arraySafeVal($args,1,$algo);
				$added = $this->checkAndFillPoolHashrateHoles($algo);
				while ($added > 0) $added = $this->checkAndFillPoolHashrateHoles($algo);
			}
		} else if ($args[0] == 'hashrate') {
			$algos = yaamp_get_algos();
			foreach ($algos as $algo) {
				$algo = arraySafeVal($args,1,$algo);
				$added = $this->checkAndFillUserHashrateHoles($algo);
				while ($added > 0) $added = $this->checkAndFillUserHashrateHoles($algo);
			}
		} else if ($args[0] == 'balances' && arraySafeVal($args,1,'') == '') {
			$coins = dbolist('SELECT symbol FROM coins WHERE enable AND visible ORDER by symbol');
			foreach ($coins as $row) {
				$symbol = $row['symbol'];
				echo("checking for $symbol holes\n");
				$added = $this->checkAndFillUserBalanceHoles($symbol);
				while ($added > 0) $added = $this->checkAndFillUserBalanceHoles($symbol);
			}
		} else if ($args[0] == 'balances') {
			$symbol = arraySafeVal($args,1,'DCR');
			$added = $this->checkAndFillUserBalanceHoles($symbol);
			while ($added > 0) $added = $this->checkAndFillUserBalanceHoles($symbol);
		}
	}

	public function getHelp()
	{
		return $this->run(array('help'));
	}

	////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Fill empty data in db_hashrate
	 */
	public function checkAndFillPoolHashrateHoles($algo)
	{
		$stats = new db_hashrate;

		if (!$stats instanceof CActiveRecord)
			return;

		$t2 = 0; $added = 0;
		$since = time() - 24 * 3600;

		$data = $stats->findAll(array('condition'=>"time>=$since AND algo=:algo", 'order'=>'time ASC', 'params'=>array(':algo'=>$algo)));
		foreach ($data as $row) {
			$t = (int) $row['time'];
			$d = $t - $t2;
			if (!$t2) $d = 0;
			$h = strftime('%H:%M', $t);
			if ($d && $d != 900) {
				$h0 = strftime('%H:%M', $t2);
				echo "hole detected between $h0 and $h ($d sec, $t)\n";
				$fill = new db_hashrate;
				$fill->isNewRecord = true;
				$fill->time = $t2 + 900;
				if ($d > 3600)
					$fill->hashrate = 0;
				else
					$fill->hashrate = ($last_row->hashrate + $row->hashrate) / 2;
				$fill->price = ($last_row->price + $row->price) / 2;
				$fill->rent = ($last_row->rent + $row->rent) / 2;
				$fill->difficulty = ($last_row->difficulty + $row->difficulty) / 2;
				$fill->algo = $algo;
				//$fill->earnings = ...;
				$added += $fill->save();
			}
			$t2 = $t;
			$last_row = clone($row);
		}
		echo count($data)." records for $algo ($added added)\n";
		return $added;
	}

	/**
	 * Fill empty data in db_hashuser
	 */
	public function checkAndFillUserHashrateHoles($algo)
	{
		$stats = new db_hashuser;

		if (!$stats instanceof CActiveRecord)
			return;

		$t2 = 0; $added = 0; $last_row = array();
		$since = time() - 24 * 3600;

		$data = $stats->findAll(array('condition'=>"time>=$since AND algo=:algo", 'order'=>'userid, time', 'params'=>array(':algo'=>$algo)));
		foreach ($data as $row) {
			$t = (int) $row->time;
			$d = $t - $t2;
			if (!$t2 || arraySafeVal($last_row,'userid') != $row->userid) $d = 0;
			if ($d && $d != 900 && $row->hashrate > 0) {
				$h = strftime('%H:%M', $t);
				$h0 = strftime('%H:%M', $t2);
				echo "uid {$row->userid}: hole detected between $h0 and $h ($d sec, ts $t)\n";
				$fill = new db_hashuser;
				$fill->isNewRecord = true;
				$fill->time = $t2 + 900;
				$fill->userid = $row->userid;
				if ($d > 3600)
					$fill->hashrate = 0;
				else
					$fill->hashrate = ($last_row['hashrate'] + $row->hashrate) / 2;
				$fill->algo = $algo;
				$added += $fill->save();
			}
			$t2 = $t;
			$last_row = $row->getAttributes();
		}
		echo count($data)." records for $algo ($added added)\n";
		return $added;
	}

	/**
	 * Fill empty data in db_balanceuser
	 */
	public function checkAndFillUserBalanceHoles($symbol)
	{
		$t2 = 0; $added = 0; $last_row = array();
		$since = time() - 24 * 3600;

		$coin = getdbosql('db_coins', "symbol=:symbol", array(':symbol'=>$symbol));
		if (!$coin) return 0;

		$data = dbolist('SELECT B.userid, B.time, B.balance, B.pending, A.username'.
			' FROM balanceuser B INNER JOIN accounts A on A.id = B.userid'.
			' WHERE A.coinid=:coinid AND A.last_earning>:since ORDER BY B.userid, B.time',
			array(':coinid'=>$coin->id,':since'=>$since));
		foreach ($data as $row) {
			$t = (int) $row['time'];
			$d = $t - $t2;
			if (!$t2 || $last_row['userid'] != $row['userid']) $d = 0;
			if ($d && $d != 900 && ($row['pending'] + $row['balance']) > 0) {
				if ($d > 3600) continue;
				$h = strftime('%H:%M', $t);
				$h0 = strftime('%H:%M', $t2);
				echo $row['username'].": hole detected between $h0 and $h ($d sec, $t)\n";
				$fill = new db_balanceuser;
				$fill->isNewRecord = true;
				$fill->time = $t2 + 900;
				$fill->userid = $row['userid'];
				$fill->balance = $last_row['balance'];
				$fill->pending = $last_row['pending'];
				$added += $fill->save();
			}
			$t2 = $t;
			$last_row = array_merge($row);
		}
		echo count($data)." records for {$coin->symbol} ($added added)\n";
		return $added;
	}
}
