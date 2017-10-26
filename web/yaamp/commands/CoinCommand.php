<?php
/**
 * CoinCommand is a console command, to get/set coin user settings
 *
 * To use this command, enter the following on the command line:
 * <pre>
 * php web/yaamp/yiic.php coin help
 * </pre>
 *
 * @property string $help The command description.
 *
 */
class CoinCommand extends CConsoleCommand
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

		if (!isset($args[1]) || $args[1] == 'help') {

			echo "Yiimp coin command\n";
			echo "Usage: yiimp coin <SYM> delete - to delete with all related records\n";
			echo "       yiimp coin <SYM> purge - to clean users and history \n";
			echo "       yiimp coin <SYM> diff - to check if wallet diff is standard\n";
			echo "       yiimp coin <SYM> blocktime - estimate the chain blocktime\n";
			echo "       yiimp coin <SYM> checkblocks - recheck confirmed blocks\n";
			echo "       yiimp coin <SYM> generated [height] - search blocks not notified, set height to fix\n";
			echo "       yiimp coin <SYM> get <key>\n";
			echo "       yiimp coin <SYM> set <key> <value>\n";
			echo "       yiimp coin <SYM> unset <key>\n";
			echo "       yiimp coin <SYM> settings\n";
			return 1;

		} else if ($args[1] == 'delete') {
			return $this->deleteCoin($symbol);

		} else if ($args[1] == 'purge') {
			return $this->purgeCoin($symbol);

		} else if ($args[1] == 'diff') {
			return $this->checkMiningDiff($symbol);

		} else if ($args[1] == 'blocktime') {
			return $this->estimateBlockTime($symbol);

		} else if ($args[1] == 'checkblocks') {
			return $this->checkConfirmedBlocks($symbol);

		} else if ($args[1] == 'generated') {
			$start_height = arraySafeVal($args, 2, 0);
			// if start_height is set, it will create missed block(s)
			return $this->listGeneratedTxs($symbol, $start_height);

		} else if ($args[1] == 'get') {
			return $this->getCoinSetting($args);

		} else if ($args[1] == 'set') {
			return $this->setCoinSetting($args);

		} else if ($args[1] == 'unset') {
			return $this->unsetCoinSetting($args);

		} else if ($args[1] == 'settings') {
			return $this->listCoinSettings($args);
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

	private function checkSymbol($symbol)
	{
		return dboscalar("SELECT COUNT(*) FROM coins WHERE symbol=:symbol",
			array(':symbol'=>$symbol));
	}

	/**
	 * Purge coin by symbol
	 */
	public function purgeCoin($symbol)
	{
		$coins = new db_coins;

		if (!$coins instanceof CActiveRecord)
			return;

		$coin = $coins->find(array('condition'=>'symbol=:sym', 'params'=>array(':sym'=>$symbol)));
		if ($coin)
		{
			$nbAccounts = getdbocount('db_accounts', "coinid=".$coin->id);
			$coin->deleteDeps();

			echo "coin ".$coin->symbol." purged\n";
			if ($nbAccounts) {
				$nbAccounts -= getdbocount('db_accounts', "coinid=".$coin->id);
				echo " $nbAccounts accounts deleted\n";
			}
		}
	}

	/**
	 * Delete (and purge) coin by symbol
	 */
	public function deleteCoin($symbol)
	{
		$coins = new db_coins;

		if (!$coins instanceof CActiveRecord)
			return;

		$coin = $coins->find(array('condition'=>'symbol=:sym', 'params'=>array(':sym'=>$symbol)));
		if ($coin)
		{
			$this->purgeCoin($symbol);

			$coin->installed=0;
			$coin->enable=0;
			$coin->save();

			$coin->delete();
			echo "coin ".$coin->symbol." deleted\n";
		}
	}

	////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Compare getminininginfo difficulty and computed one (from the target hash)
	 */
	public function checkMiningDiff($symbol)
	{
		$coins = new db_coins;

		if (!$coins instanceof CActiveRecord)
			return;

		$coin = $coins->find(array('condition'=>'symbol=:sym', 'params'=>array(':sym'=>$symbol)));
		if ($coin)
		{
			$remote = new WalletRPC($coin);
			$tpl = $remote->getblocktemplate();
			$mnf = $remote->getmininginfo();
			if (empty($tpl)) die("error {$remote->error} ".json_encode($tpl)."\n");

			$target = arraySafeVal($tpl,"target","");
			$computed_diff = hash_to_difficulty($coin,$target);
			$wallet_diff = arraySafeVal($mnf,"difficulty",0);
			$factor = $computed_diff ? round($wallet_diff/$computed_diff,3) : 'NaN';

			echo $coin->symbol.": network=".Itoa2(arraySafeVal($mnf,"networkhashps",0)*1000, 3)."H/s\n".
				"bits=".arraySafeVal($tpl,"bits","")." target=$target\n".
				"difficulty=$wallet_diff hash_to_difficulty(target)=$computed_diff factor=$factor\n";
		}
	}

	/**
	 * Extract/Compute the average block time of a block chain
	 */
	public function estimateBlockTime($symbol)
	{
		$coins = new db_coins;

		if (!$coins instanceof CActiveRecord)
			return;

		$coin = $coins->find(array('condition'=>'symbol=:sym', 'params'=>array(':sym'=>$symbol)));
		if ($coin)
		{
			$remote = new WalletRPC($coin);
			$nfo = $remote->getinfo();
			if (empty($nfo)) die("error {$remote->error} ".json_encode($nfo)."\n");
			$height = arraySafeVal($nfo,"blocks",0);

			$hash = $remote->getblockhash($height-1024);
			if (empty($hash)) die("error {$remote->error} ".json_encode($hash)."\n");
			$block = $remote->getblock($hash);
			$time1 = arraySafeVal($block, 'time', 0);

			$hash = $remote->getblockhash($height-512);
			if (empty($hash)) die("error {$remote->error} ".json_encode($hash)."\n");
			$block = $remote->getblock($hash);
			$time2 = arraySafeVal($block, 'time', 0);

			$hash = $remote->getblockhash($height-128);
			if (empty($hash)) die("error {$remote->error} ".json_encode($hash)."\n");
			$block = $remote->getblock($hash);
			$time3 = arraySafeVal($block, 'time', 0);

			$hash = $remote->getblockhash($height);
			if (empty($hash)) die("error {$remote->error} ".json_encode($hash)."\n");
			$block = $remote->getblock($hash);
			$time = arraySafeVal($block, 'time', 0);

			$t = intval($coin->block_time);
			$human_time = sprintf('%dmn%02d', ($t/60), ($t%60));
			echo $coin->symbol.": current block_time set in the db $human_time ($t sec) \n";

			$t = round(($time - $time1) / 1024);
			$human_time = sprintf('%dmn%02d', ($t/60), ($t%60));
			echo $coin->symbol.": avg time for the last 1024 blocks = $human_time ($t sec) \n";
			if (empty($coin->block_time) && $t > 10) {
				$coin->block_time = $t;
				$coin->save();
				echo $coin->symbol.": db block_time set to $t\n";
			}
			$t = round(($time - $time2) / 512);
			$human_time = sprintf('%dmn%02d', ($t/60), ($t%60));
			echo $coin->symbol.": avg time for the last  512 blocks = $human_time ($t sec) \n";
			$t = round(($time - $time3) / 128);
			$human_time = sprintf('%dmn%02d', ($t/60), ($t%60));
			echo $coin->symbol.": avg time for the last  128 blocks = $human_time ($t sec) \n";
		}
	}

	////////////////////////////////////////////////////////////////////////////////////

	public function checkConfirmedBlocks($symbol)
	{
		$coin = getdbosql('db_coins', 'symbol=:sym', array(':sym'=>$symbol));
		if (!$coin) return -1;
		$blocks = new db_blocks;

		$data = $blocks->findAll(array('condition'=>"coin_id=:id AND category='generate'", 'order'=>'height DESC', 'params'=>array(':id'=>$coin->id)));
		//echo count($data)." confirmed blocks to check...\n";
		if (!$data || !count($data)) return 0;

		$remote = new WalletRPC($coin);
		$nbReset = 0; $totAmount = 0.0;

		foreach ($data as $block) {
			$b = $remote->getblock($block->blockhash);
			$confs = arraySafeVal($b,'confirmations', 0);
			if ($confs <= 0 || !$b) {
				$date = strftime("%Y-%m-%d %H:%M", arraySafeVal($b,'time', $block->time));
				$height = arraySafeVal($b,'height', $block->height);
				$conf2 = $coin->block_height - $height;
				echo arraySafeVal($b,'height')." $confs/$conf2 $date\n";
				$totAmount += $block->amount;
				$block->amount = 0;
				$block->category = 'orphan';
				$nbReset += dborun('UPDATE earnings SET amount=0 WHERE blockid='.$block->id);
				$block->save();
			}
		}
		if ($totAmount) {
			echo "found $totAmount $symbol orphaned after confirmed status ($nbReset earnings reset)!\n";
		} else {
			echo count($data)." confirmed blocks verified\n";
		}
		return $nbReset;
	}

	////////////////////////////////////////////////////////////////////////////////////

	public function listGeneratedTxs($symbol,$start_height=0)
	{
		$coin = getdbosql('db_coins', 'symbol=:sym', array(':sym'=>$symbol));
		if (!$coin) return -1;

		$remote = new WalletRPC($coin);
		$cur_height = $remote->getblockcount();
		if (!$cur_height) {
			echo "unable to query current block height!\n";
			return 0;
		}

		// note: rpc not compatible with decred
		$txs = $remote->listtransactions($coin->account, 900);
		if(!$txs || !is_array($txs)) {
			echo "no txs found!\n";
			return 0;
		}

		$nbOk = $nbMissed = 0;
		foreach($txs as $tx) {
			if ($tx['category'] == 'generate' || $tx['category'] == 'immature') {
				$height = $cur_height - $tx['confirmations'] + 1;
				if ($tx['confirmations'] < 3) continue; // let the backend detect them...
				$block = getdbosql('db_blocks', "coin_id={$coin->id} AND height=$height");
				if ($block) {
					if ($block->category == 'orphan') {
						if ($start_height > 0 && $height >= $start_height) {
							$block->category = 'new';
							if ($block->save()) echo "Fixed orphan block id {$block->id}\n";
							$nbOk++;
						} else {
							echo("warning: orphan block {$block->height} with confirmations!\n");
							$nbMissed++;
						}
					} else
						$nbOk++;
				} else {
					$time = round($tx['time'] / 900) * 900;
					if ($time < time() - 2 * 24 * 3600) continue;
					echo strftime("%Y-%m-%d %H:%M", $tx['time'])." $time missed block $height : ".json_encode($tx)."\n";
					$data = getdbolist('db_hashuser','algo=:algo AND time='.$time, array(':algo'=>$coin->algo));
					$b = new db_blocks;
					$b->coin_id = $coin->id;
					$b->height = $height;
					$b->time = $tx['time'];
					$b->category = 'new';
					$b->algo = $coin->algo;
					$b->blockhash = $tx['blockhash'];
					$b->txhash = $tx['txid'];
					$b->isNewRecord = true;
					if ($start_height > 0 && $height >= $start_height) {
						if ($b->save()) echo "Added new block id {$b->id}\n";
					}
					$nbMissed++;
				}
			}
		}
		echo "$nbOk blocks checked, $nbMissed missed by the backend\n";
		return 0;
	}

	////////////////////////////////////////////////////////////////////////////////////

	public function getCoinSetting($args)
	{
		if (count($args) < 3)
			die("usage: yiimp coin <SYM> get <key>\n");
		$symbol   = $args[0];
		$key      = $args[2];
		if (!$this->checkSymbol($symbol)) {
			echo "error: symbol '$symbol' does not exist!\n";
			return 1;
		}
		$value = coin_get($symbol, $key);
		echo "$value\n";
		return 0;
	}

	public function setCoinSetting($args)
	{
		if (count($args) < 4)
			die("usage: yiimp coin <SYM> set <key> <value>\n");
		$symbol   = $args[0];
		$key      = $args[2];
		$value    = $args[3];
		if (!$this->checkSymbol($symbol)) {
			echo "error: symbol '$symbol' does not exist!\n";
			return 1;
		}
		$res = coin_set($symbol, $key, $value);
		$val = coin_get($symbol, $key);
		echo ($res ? "$symbol $exchange $key ".json_encode($val) : "error") . "\n";
		return 0;
	}

	public function unsetCoinSetting($args)
	{
		if (count($args) < 3)
			die("usage: yiimp coin <SYM> unset <key>\n");
		$symbol   = $args[0];
		$key      = $args[2];
		if (!$this->checkSymbol($symbol)) {
			echo "error: symbol '$symbol' does not exist!\n";
			return 1;
		}
		coin_unset($symbol, $key);
		echo "ok\n";
		return 0;
	}

	public function listCoinSettings($args)
	{
		if (count($args) < 2)
			die("usage: yiimp coin <SYM> settings\n");
		$symbol = $args[0];
		if (!$this->checkSymbol($symbol)) {
			echo "error: symbol '$symbol' does not exist!\n";
			return 1;
		}
		$keys = coin_settings_prefetch($symbol);
		foreach ($keys as $key => $value) {
			if ($key == $symbol) continue;
			echo "$key ".json_encode($value)."\n";
		}
		return 0;
	}

}
