<?php
/**
 * ShiftCommand is a console command to do shapeshift txs
 *
 * To use this command, enter the following on the command line:
 * <pre>
 * yiimp shift help
 * </pre>
 */
class ShiftCommand extends CConsoleCommand
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

		if (!isset($args[0]) || $args[0] == 'help') {

			echo "Yiimp shift command\n";
			echo "Usage: yiimp shift list - to list supported coins\n";
			echo "       yiimp shift query <SYM-src> [SYM-dest]\n";
			echo "       yiimp shift start <SYM-src> <SYM-dest> [dest-addr] - to start a shapeshift tx\n";
			if (YIIMP_CLI_ALLOW_TXS) echo "       yiimp shift send <deposit-addr> <amount> <SYM>\n";
			echo "       yiimp shift status <deposit-addr>\n";
			echo "       yiimp shift cancel <deposit-addr>\n";
			return 1;

		} else if ($args[0] == 'list') {
			return $this->listShiftCoins($args);

		} else if ($args[0] == 'query') {
			return $this->queryRate($args);

		} else if ($args[0] == 'start') {
			return $this->startShift($args);

		} else if ($args[0] == 'send') {
			return $this->sendShift($args);

		} else if ($args[0] == 'status') {
			return $this->statusOrder($args);

		} else if ($args[0] == 'cancel') {
			return $this->cancelOrder($args);
		}

		return 1;
	}

	/**
	 * Provides the command description.
	 * @return string the command description.
	 */
	public function getHelp()
	{
		return $this->run(array('help'));
	}

	////////////////////////////////////////////////////////////////////////////////////

	private function checkSymbol($symbol)
	{
		return dboscalar("SELECT COUNT(*) FROM coins WHERE symbol=:symbol",
			array(':symbol'=>$symbol)
		);
	}

	private function shapeshiftAllowed($symbol)
	{
		return dboscalar("SELECT COUNT(M.id) FROM coins C INNER JOIN markets M ON M.coinid = C.id ".
			"WHERE C.symbol=:symbol AND M.name='shapeshift'",
			array(':symbol'=>$symbol)
		);
	}

	public function listShiftCoins($args)
	{
		$res = dbolist("SELECT C.symbol, C.available FROM coins C INNER JOIN markets M ON M.coinid = C.id ".
			"WHERE M.name='shapeshift' AND C.installed ORDER BY symbol"
		);
		echo "installed: ";
		foreach ($res as $c) {
			echo $c['symbol'];
			if ($c['available']) echo ' ('.bitcoinvaluetoa($c['available']).')';
			echo ', ';
		}
		echo "\n";
		/*
		$res = dbolist("SELECT C.symbol FROM coins C INNER JOIN markets M ON M.coinid = C.id ".
		"WHERE M.name='shapeshift' AND IFNULL(C.installed,0)=0 ORDER BY symbol");
		echo "others: ";
		foreach ($res as $c) echo $c['symbol'].' ';
		echo "\n";
		*/
	}

	////////////////////////////////////////////////////////////////////////////////////

	public function queryRate($args)
	{
		if (count($args) < 2)
			die("usage: shift query <SYM-src> [SYM-dst=BTC]\n");

		$srcsym = $args[1];
		$dstsym = arraySafeVal($args, 2, 'BTC');
		if (!$this->checkSymbol($srcsym))
			die("error: symbol '$srcsym' does not exist!\n");
		if (!$this->shapeshiftAllowed($srcsym))
			die("error: $srcsym is not supported by shapeshift!\n");
		if (!$this->checkSymbol($dstsym))
			die("error: symbol '$dstsym' does not exist!\n");
		if (!$this->shapeshiftAllowed($dstsym))
			die("error: $dstsym is not supported by shapeshift!\n");

		$coins = new db_coins;
		$src = $coins->find(array('condition'=>'symbol=:sym', 'params'=>array(':sym'=>$srcsym)));
		$dst = $coins->find(array('condition'=>'symbol=:sym', 'params'=>array(':sym'=>$dstsym)));

		$pair = strtolower($src->getOfficialSymbol().'_'.$dst->getOfficialSymbol());
		$res = shapeshift_api_query('marketinfo', $pair);
		if (!is_array($res)) die(json_encode($res)."\n");

		echo "info: $pair ".json_encode($res)."\n";
		$rate = round($src->price / $dst->price, 8);
		$diff = round(arraySafeVal($res, 'rate',1) - $rate, 8);
		//$rate1 = round($dst->price / $src->price, 8);
		//$diff1 = round($rate1 - 1/arraySafeVal($res, 'rate',1), 8);
		echo "DB prices: ".bitcoinvaluetoa($src->price)." ".bitcoinvaluetoa($dst->price)." $rate:1 ($diff)\n";
	}

	////////////////////////////////////////////////////////////////////////////////////

	public function startShift($args)
	{
		if (count($args) < 3)
			die("usage: shift start <SYM-src> <SYM-dest> [dest-addr]\n");

		$srcsym = $args[1];
		$dstsym = $args[2];
		$dstaddr = arraySafeVal($args, 3);
		if (!$this->checkSymbol($srcsym)) {
			echo "error: symbol '$srcsym' does not exist!\n";
			return 1;
		}
		if (!$this->shapeshiftAllowed($srcsym)) {
			echo "error: $srcsym is not supported by shapeshift!\n";
			return 1;
		}
		if (!$this->checkSymbol($dstsym)) {
			echo "error: symbol '$dstsym' does not exist!\n";
			return 1;
		}
		if (!$this->shapeshiftAllowed($dstsym)) {
			echo "error: $dstsym is not supported by shapeshift!\n";
			return 1;
		}

		$coins = new db_coins;
		$src = $coins->find(array('condition'=>'symbol=:sym', 'params'=>array(':sym'=>$srcsym)));
		$dst = $coins->find(array('condition'=>'symbol=:sym', 'params'=>array(':sym'=>$dstsym)));

		$data = array();
		$data['returnAddress'] = $src->master_wallet;
		$data['pair'] = strtolower($src->getOfficialSymbol().'_'.$dst->getOfficialSymbol());
		$data['withdrawal'] = $dst->master_wallet;
		if (!empty($dstaddr)) {
			$data['withdrawal'] = $dstaddr;
		}
		//$data->apiKey = ...;

		$res = shapeshift_api_query('marketinfo', $data['pair']);
		if (!is_array($res)) {
			echo json_encode($res)."\n";
			return 1;
		}
		echo "info: ".json_encode($res)."\n";

		$res = shapeshift_api_post('shift', $data);
		if (isset($res['error'])) {
			echo json_encode($res)."\n";
			return 1;
		}

		if (isset($res['deposit'])) {
			echo json_encode($res)."\n";
			echo "Transaction allowed, please do the following now:\n";
			if (!YIIMP_CLI_ALLOW_TXS)
				echo "  <coin>-cli sendtoaddress {$res['deposit']} <amount>\n";
			else
				echo "  yiimp shift send {$res['deposit']} <amount> $srcsym\n";
			echo "  yiimp shift status {$res['deposit']}\n";
		}
		return 0;
	}

	////////////////////////////////////////////////////////////////////////////////////

	public function sendShift($args)
	{
		if (count($args) < 4)
			die("usage: shift send <deposit-addr> <amount> <SYM>\n");

		$deposit = $args[1];
		$amount = bitcoinvaluetoa($args[2]);
		$symbol = $args[3];

		$coin = getdbosql('db_coins', 'symbol=:sym', array(':sym'=>$symbol));
		if (!$coin) return 1;

		$remote = new WalletRPC($coin);
		$res = $remote->validateaddress($deposit);
		if (objSafeVal($res,'isvalid',false) == false) {
			echo json_encode($res)."\n";
			return 1;
		}

		if (!YIIMP_CLI_ALLOW_TXS) {
			echo "method no allowed\n";
			return 1;
		}

		$res = $remote->sendtoaddress($deposit, (double)$amount, "", "", true);
		echo json_encode($res)."\n";

	}

	////////////////////////////////////////////////////////////////////////////////////

	public function statusOrder($args)
	{
		if (count($args) < 2)
			die("usage: shift status <deposit-addr>\n");

		$res = shapeshift_api_query('txStat', $args[1]);
		echo json_encode($res)."\n";
		if (isset($res['outgoingCoin']) && isset($res['incomingCoin'])) {
			echo "real rate: ".round($res['outgoingCoin'] / $res['incomingCoin'], 8)."\n";
		}
		return 0;
	}

	////////////////////////////////////////////////////////////////////////////////////

	public function cancelOrder($args)
	{
		if (count($args) < 2)
			die("usage: shift cancel <deposit-addr>\n");

		$res = shapeshift_api_post('cancelpending', array('address'=>$args[1]));
		echo json_encode($res)."\n";
		return 0;
	}

}
