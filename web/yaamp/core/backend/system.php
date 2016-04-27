<?php

function BackendDoBackup()
{
	$d = date('Y-m-d-H', time());
	$filename = "/root/backup/yaamp-$d.sql.gz";

	include_once("/etc/yiimp/keys.php");

        $host = YAAMP_DBHOST;
        $db   = YAAMP_DBNAME;
	$user = YIIMP_MYSQLDUMP_USER;
	$pass = YIIMP_MYSQLDUMP_PASS;

	system("mysqldump -h $host -u$user -p$pass --skip-extended-insert $db | gzip > $filename");
}

function BackendQuickClean()
{
	$coins = getdbolist('db_coins', "installed");

	foreach($coins as $coin)
	{
		$delay = time() - 24*60*60;
		if ($coin->symbol=='DCR') $delay = time() - 7*24*60*60;

		$id = dboscalar("select id from blocks where coin_id=$coin->id and time<$delay and
			id not in (select blockid from earnings where coinid=$coin->id)
			order by id desc limit 200, 1");

		if($id) dborun("delete from blocks where coin_id=$coin->id and time<$delay and
			id not in (select blockid from earnings where coinid=$coin->id) and id<$id");
	}

	dborun("delete from earnings where blockid in (select id from blocks where category='orphan')");
	dborun("delete from earnings where blockid not in (select id from blocks)");
	dborun("UPDATE blocks SET amount=0 WHERE category='orphan' AND amount>0");
}

function BackendCleanDatabase()
{
	$delay = time() - 60*24*60*60;
//	dborun("delete from blocks where time<$delay");
	dborun("delete from hashstats where time<$delay");
	dborun("delete from payouts where time<$delay");
	dborun("delete from rentertxs where time<$delay");
	dborun("DELETE FROM shares WHERE time<$delay");

	$delay = time() - 2*24*60*60;
	dborun("delete from stats where time<$delay");
	dborun("delete from hashrate where time<$delay");
	dborun("delete from hashuser where time<$delay");
	dborun("delete from hashrenter where time<$delay");
	dborun("delete from balanceuser where time<$delay");
	dborun("delete from exchange where send_time<$delay");
	dborun("DELETE FROM shares WHERE time<$delay AND coinid NOT IN (select id from coins)");

	$delay = time() - 12*60*60;
	dborun("delete from earnings where status=2 and mature_time<$delay");
}

function BackendOptimizeTables()
{
	$list = dbolist("show tables");
	foreach($list as $item)
	{
		$tablename = $item['Tables_in_yaamp'];
	 	dbolist("optimize table $tablename");

	 	sleep(1);
	}
}

function BackendProcessList()
{
	$list = dbolist("show processlist");
	foreach($list as $item)
	{
		$conn = getdbo('db_connections', $item['Id']);
		if(!$conn)
		{
			$conn = new db_connections;
			$conn->id = $item['Id'];
			$conn->user = $item['User'];
			$conn->host = $item['Host'];
			$conn->db = $item['db'];
			$conn->created = time();
		}

		$conn->idle = $item['Time'];
		$conn->last = time();

		$conn->save();
	}

	$delay = time() - 5*60;
	dborun("delete from connections where last<$delay");
}

function BackendRunCoinActions()
{
//	debuglog(__FUNCTION__);

// 	$hostname = exec("hostname");
// 	$server = getdbosql('db_servers', "name='$hostname'");
// 	if(!$server)
// 	{
// 		$server = new db_servers;
// 		$server->name = $hostname;
// 	}

// 	$server->uptime = exec("uptime");
// 	$server->save();

	//////////////////////////////////////////////////////////////////////////

// 	if($hostname == 'yaamp')
// 	{
// 		$mining = getdbosql('db_mining');
// 		exec("pgrep stratum", $ids, $ret);

// 		$mining->stratumids = implode(',', $ids);
// 		$mining->save();
// 	}

	/////////////////////////////////////////////////////////////////////////////////////////////////

// 	$coins = getdbolist('db_coins');
// 	foreach($coins as $coin)
// 	{
// 		if(empty($coin->action) || empty($coin->program) || empty($coin->conf_folder)) continue;
// 		$hostname = exec("hostname");
// 		if($hostname != $coin->rpchost) continue;
// 		switch($coin->action)
// 		{
// 			case 1:
// 				debuglog("starting $coin->program");

// 				$debugfile = YAAMP_WALLETS."/$coin->conf_folder/debug.log";
// 				@unlink($debugfile);

// 				exec(YAAMP_BIN."/run-background.sh ".YAAMP_BIN."/$coin->program");
// 				$coin->enable = true;
// 				break;

// 			case 2:
// 				debuglog("stopping $coin->program");
// 				exec(YAAMP_BIN."/$coin->program stop");
// 				break;

// 			case 4:
// 				debuglog("restarting $coin->program");
// 				exec(YAAMP_BIN."/$coin->program stop");
// 				sleep(10);

// 				$debugfile = YAAMP_WALLETS."/$coin->conf_folder/debug.log";
// 				@unlink($debugfile);

// 				exec(YAAMP_BIN."/run-background.sh ".YAAMP_BIN."/$coin->program");
// 				$coin->enable = true;
// 				break;

// 			case 3:
// 				debuglog("reset blockchain $coin->conf_folder");
// 				$folder = YAAMP_WALLETS."/$coin->conf_folder";

// 			//	exec("rm {$folder}/blk*.dat");
// 				exec("rm -fr {$folder}/database");
// 				break;

// 			case 5:
// 				$t = time();
// 				$coin->rpcport = $coin->id*2+10240;
// 				$coin->rpcuser = 'yaamprpc';
// 				$coin->rpcpasswd = md5("$t.$coin->id");
// 				$coin->save();

// 				$configfile = YAAMP_WALLETS."/$coin->conf_folder/".substr($coin->conf_folder, 1).".conf";
// 				debuglog("make config $configfile");

// 				$stratum_port = 3433;

// 				if($coin->algo == 'sha256')
// 					$stratum_port = 3333;

// 				else if($coin->algo == 'scrypt')
// 					$stratum_port = 3433;

// 				else if($coin->algo == 'x11')
// 					$stratum_port = 3533;

// 				else if($coin->algo == 'x13')
// 					$stratum_port = 3633;

// 				else if($coin->algo == 'x14')
// 					$stratum_port = 3933;

// 				else if($coin->algo == 'x15')
// 					$stratum_port = 3733;

// 				else if($coin->algo == 'nist5')
// 					$stratum_port = 3833;

// 				else if($coin->algo == 'quark')
// 					$stratum_port = 4033;

// 				else if($coin->algo == 'fresh')
// 					$stratum_port = 4133;

// 				else if($coin->algo == 'neoscrypt')
// 					$stratum_port = 4233;

// 				else if($coin->algo == 'scryptn')
// 					$stratum_port = 4333;

// 				else if($coin->algo == 'lyra2')
// 					$stratum_port = 4433;

// 				else if($coin->algo == 'blake')
// 					$stratum_port = 4533;

// // 				else if($coin->algo == 'keccak')
// // 					$stratum_port = 3933;

// 				$data = "server=1\ndeamon=1\ngen=0\nrpcuser=$coin->rpcuser\nrpcpassword=$coin->rpcpasswd\nrpcport=$coin->rpcport\nrpcallowip=10.*.*.*\nblocknotify=".YAAMP_BIN."/blocknotify.sh --host yaamp --port $stratum_port --id $coin->id --block %s --password tu8tu5\n";
// 				file_put_contents($configfile, $data);

// 				debuglog("starting $coin->program");

// 				$debugfile = YAAMP_WALLETS."/$coin->conf_folder/debug.log";
// 				@unlink($debugfile);

// 				exec(YAAMP_BIN."/run-background.sh ".YAAMP_BIN."/$coin->program");
// 				$coin->enable = true;

// 				break;
// 		}

// 		$coin->action = null;
// 		$coin->save();
// 	}

// 	$filename = '/root/stratum/stratum.log';
// 	$filesize = filesize($filename);

// 	$oldsize = controller()->memcache->get('stratum_log_size');
// 	if($oldsize == $filesize) return;

// 	$file = fopen($filename, $r);
// 	if(!$file) return;

// 	fseek($file, $oldsize);
// 	$data = fread($file, $filesize-$oldsize);
// 	fclose($file);

// 	controller()->memcache->set('stratum_log_size', $filesize);
// 	system("echo \"$data\" | mail -s \"yiimp server\" ".YAAMP_ADMIN_EMAIL);
}

