<?php
/**
 * DistcleanCommand is a console command, to erase all user data before a release
 *
 * To use this command, enter the following on the command line:
 * <pre>
 * yiic distclean <mysql_yaamp_password>
 * </pre>
 *
 * @property string $help The command description.
 *
 */
class DistcleanCommand extends CConsoleCommand
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

			echo "Yii checkup command\n";
			echo "Usage: yiic distclean <yaamp db password>\n";
			return 1;

		} else {

			$pw = $args[0];
			if ($pw != YAAMP_DBPASSWORD)
				die("Bad password\n");
			self::distClean();
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
	 * Delete user with all its records
	 */
	private static function deleteUser($user)
	{
		dborun("DELETE FROM balanceuser WHERE userid=".$user->id);
		dborun("DELETE FROM hashuser WHERE userid=".$user->id);
		dborun("DELETE FROM shares WHERE userid=".$user->id);
		dborun("DELETE FROM workers WHERE userid=".$user->id);
		dborun("DELETE FROM earnings WHERE userid=".$user->id);
		dborun("DELETE FROM blocks WHERE userid=".$user->id);
		dborun("DELETE FROM payouts WHERE account_id=".$user->id);

		return (int) $user->delete();
	}

	/**
	 * Delete all accounts
	 */
	private static function distClean()
	{
		$nbDeleted = 0;
		$users = getdbosql('db_accounts');
		if ($users) {
			$nbUsers = (int) $users->count();
			foreach($users->findAll() as $user) {
				$nbDeleted += self::deleteUser($user);
			}
			echo "$nbDeleted users deleted\n";
		}

		dborun("DELETE FROM stats");
		dborun("DELETE FROM blocks");
		dborun("DELETE FROM hashrate");
		dborun("DELETE FROM hashstats");
		dborun("DELETE FROM payouts");
		dborun("DELETE FROM stats");
		dborun("DELETE FROM connections");
		dborun("DELETE FROM stratums");
		dborun("DELETE FROM exchange");
		dborun("UPDATE balances SET balance=0");
		dborun("DELETE FROM markets WHERE coinid NOT IN (SELECT id FROM coins)");
		dborun("UPDATE markets SET deposit_address=NULL");
		dborun("UPDATE coins SET master_wallet=NULL, charity_address=NULL, deposit_address=NULL,
			balance=0, rpcpasswd=NULL, rpcuser=NULL WHERE 1");
	}

}
