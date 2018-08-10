<?php
/**
 * CheckupCommand is a console command, to double check the site requirements :
 *  - directories rights
 *  - stored procedures
 *
 * To use this command, enter the following on the command line:
 * <pre>
 * php web/yaamp/yiic.php checkup
 * </pre>
 *
 * @property string $help The command description.
 *
 */
class CheckupCommand extends CConsoleCommand
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

		if (isset($args[0])) {

			echo "Yiimp checkup command\n";
			echo "Usage: yiimp checkup\n";
			return 1;

		} else {

			self::checkDirectories();
			self::checkPHP();
			//self::checkStoredProcedures();
			self::checkModels();

			self::autolinkCoinsImages();

			if (!YAAMP_ALLOW_EXCHANGE)
				self::cleanUserBalancesBTC();

			echo "ok\n";
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

	private function isDirWritable($dir)
	{
		if (!is_writable($dir)) {
			echo "directory $dir is not writable!\n";
		}
	}

	/**
	 * Check we can write in specific folders
	 */
	public function checkDirectories()
	{
		$root = $this->basePath;

		self::isDirWritable("$root/assets/.");
		self::isDirWritable("$root/yaamp/runtime/.");
		self::isDirWritable(YAAMP_LOGS."/.");

		if (!is_readable('/etc/yiimp/keys.php'))
			echo "private keys.php file missing in etc!\n";
	}

	/**
	 * Check all required php modules are present
	 */
	public function checkPHP()
	{
		if (!function_exists('curl_init'))
			echo("missing curl php extension!\n");
		if (!function_exists('memcache_get'))
			echo("missing memcache php extension!\n");
	}

	/**
	 * Test a stored proc
	 */
	private function callStoredProc($proc, $params=array())
	{
		$db = Yii::app()->db;
		$params = implode(',', $params);
		$command = $db->createCommand("CALL $proc($params);");
		try {
			$res = $command->execute();
			$command->cancel();
		} catch (CDbException $e) {
			return $e->getMessage();
		}
		return true;
	}

	/**
	 * Check stored procs (if any)
	 */
	public function checkStoredProcedures()
	{
		$procs = array();
		$procs['sp_test'] = array();

		foreach ($procs as $name => $params) {
			$res = self::callStoredProc($name, $params);
			if ($res !== true) {
				echo "$name: $res\n";
				// TODO: execute this script automatically in dev.
				// $sql = file_get_contents($this->basePath.'/yaamp/sql/DB_Procedures.sql');
			}
		}
	}


	/**
	 * Check Database Model
	 */
	public function checkModels()
	{
		$modelsPath = $this->basePath.'/yaamp/models';

		if(!is_dir($modelsPath))
			echo "Directory $modelsPath is not a directory\n";

		$files = scandir($modelsPath);
		foreach ($files as $model) {
			if ($model=="." || $model=="..")
				continue;

			require_once($modelsPath.'/'.$model);

			$table = pathinfo($model,PATHINFO_FILENAME);
			$table = str_replace('Model','',$table);

			$obj = CActiveRecord::model($table);

			try{
				$test = new $obj;
			}catch (Exception $e){
				echo "Error Model: $table \n";
				echo $e->getMessage();
				continue;
			}

			if ($test instanceof CActiveRecord){
				$test->count();
				//echo "count: $table"." - " .$test->count() ."\n";
			}
		}
	}

	/**
	 * Link new coin pictures /images/coin-<SYMBOL>.png
	 */
	public function autolinkCoinsImages()
	{
		$modelsPath = $this->basePath.'/yaamp/models';
		if(!is_dir($modelsPath))
			echo "Directory $modelsPath is not a directory\n";

		require_once($modelsPath.'/db_coinsModel.php');

		$coins = new db_coins;
		if ($coins instanceof CActiveRecord)
		{
			echo "".$coins->count()." coins in the database\n";

			$nbUpdated = 0; $nbDropped = 0;
			foreach ($coins->findAll() as $coin) {
				if (!empty($coin->image)) {
					if (file_exists($this->basePath.$coin->image)) {
						$data = file_get_contents($this->basePath.$coin->image);
						if (strstr($data, "<script") || strstr($data, "<html")) {
							unlink($this->basePath.$coin->image);
							$coin->image = NULL;
							$nbDropped += $coin->save();
						}
						continue;
					}
					if (file_exists($this->basePath."/images/coin-$coin->symbol.png")) {
						$coin->image = "/images/coin-$coin->symbol.png";
						$nbUpdated += $coin->save();
					}
					if (!file_exists($this->basePath.$coin->image)) {
						$coin->image = NULL;
						$nbDropped += $coin->save();
					}
				}
				if (empty($coin->image) && file_exists($this->basePath."/images/coin-$coin->symbol.png")) {
					$coin->image = "/images/coin-$coin->symbol.png";
					$nbUpdated += $coin->save();
				}
			}
			if ($nbUpdated || $nbDropped)
				echo "$nbUpdated images updated, $nbDropped removed.\n";
		}
	}

	/**
	 * Drop BTC user balances if YAAMP_ALLOW_EXCHANGE is false
	 */
	public function cleanUserBalancesBTC()
	{
		$modelsPath = $this->basePath.'/yaamp/models';
		if(!is_dir($modelsPath))
			echo "Directory $modelsPath is not a directory\n";

		require_once($modelsPath.'/db_accountsModel.php');

		$obj = CActiveRecord::model('db_accounts');
		$table = $obj->getTableSchema()->name;

		try{
			$users = new $obj;
		} catch (Exception $e) {
			echo "Error Model: $table \n";
			echo $e->getMessage();
			return;
		}

		if ($users instanceof CActiveRecord)
		{
			echo "$table: ".$users->count()." records\n";

			$nbUpdated = 0;
			foreach ($users->findAll() as $user)
			{
				if ($user->coinid != 6)
					continue;

				$user->balance = 0;
				dborun("DELETE FROM balanceuser WHERE userid=".$user->id);
				dborun("DELETE FROM hashuser WHERE userid=".$user->id);
				dborun("DELETE FROM shares WHERE userid=".$user->id);
				dborun("DELETE FROM workers WHERE userid=".$user->id);
				dborun("UPDATE earnings SET userid=0 WHERE userid=".$user->id);
				dborun("UPDATE blocks SET userid=0 WHERE userid=".$user->id);
				dborun("UPDATE payouts SET account_id=0 WHERE account_id=".$user->id);

				$nbUpdated += $user->save();
			}
			echo "$nbUpdated users cleaned\n";
		}
	}
}
