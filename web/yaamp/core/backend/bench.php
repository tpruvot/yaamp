<?php

///////////////////////////////////////////////////////////////////////////////////////////////////////////

function BenchUpdateChips()
{
	require_once(app()->getModulePath().'/bench/functions.php');

	// some data cleanup tasks...
	dborun("UPDATE benchmarks SET device=TRIM(device) WHERE type='cpu'");
	dborun("UPDATE benchmarks SET power=NULL WHERE power<=3");
	dborun("UPDATE benchmarks SET plimit=NULL WHERE plimit=0");
	dborun("UPDATE benchmarks SET freq=NULL WHERE freq=0");
	dborun("UPDATE benchmarks SET memf=NULL WHERE memf=0");
	dborun("UPDATE benchmarks SET realmemf=NULL WHERE realmemf<=100");
	dborun("UPDATE benchmarks SET realfreq=NULL WHERE realfreq<=200");
	// bug in nvml 378.x and 381.x (linux + win) fixed in 382.05
	dborun("UPDATE benchmarks SET realfreq=NULL WHERE realfreq<=200 AND driver LIKE '% 378.%'");
	dborun("UPDATE benchmarks SET realfreq=NULL WHERE realfreq<=200 AND driver LIKE '% 381.%'");
	// sanity check on long fields (no html wanted)
	dborun("DELETE FROM benchmarks WHERE device LIKE '%<%' OR client LIKE '%<%'");

	$benchs = getdbolist('db_benchmarks', "IFNULL(chip,'')=''");
	foreach ($benchs as $bench) {
		if (empty($bench->vendorid) || empty($bench->device)) continue;

		if ($bench->algo == 'x16r') { // not constant, inaccurate
			$bench->delete();
			continue;
		}

		$rawdata = json_encode($bench->attributes);
		if (strpos($rawdata,"script")) {
			debuglog("bench record deleted : $rawdata");
			$bench->delete();
			continue;
		}

		$dups = getdbocount('db_benchmarks', "vendorid=:vid AND client=:client AND os=:os AND driver=:drv AND throughput=:thr AND userid=:uid",
			array(':vid'=>$bench->vendorid, ':client'=>$bench->client, ':os'=>$bench->os, ':drv'=>$bench->driver,':thr'=>$bench->throughput,':uid'=>$bench->userid)
		);
		if ($dups > 10 || round($bench->khps,3) == 0) {
			//debuglog("bench: {$bench->device} ignored ($dups records already present)");
			$bench->delete();
			continue;
		}

		$chip = getChipName($bench->attributes);
		if (!empty($chip) && $chip != '-') {
			$bench->chip = $chip;
			$rates = dborow("SELECT AVG(khps) AS avg, COUNT(id) as cnt FROM benchmarks WHERE algo=:algo AND chip=:chip",
				array(':algo'=>$bench->algo, ':chip'=>$chip)
			);
			$avg = (double) $rates['avg'];
			$cnt = intval($rates['cnt']);
			if ($cnt > 250) {
				$bench->delete();
				continue;
			} elseif ($cnt > 5 && $bench->khps < $avg / 2) {
				$user = getdbo('db_accounts', $bench->userid);
				debuglog("bench: {$bench->device} ignored, bad {$bench->algo} hashrate {$bench->khps} kHs by {$user->username}");
				$bench->delete();
				continue;
			}
			if ($bench->chip == 'GPU' || $bench->chip == 'Graphics Device') {
				$bench->delete();
				continue;
			}
			debuglog("bench: {$bench->device} ($chip)...");
			$bench->save();
		}
	}

	// add new chips
	$rows = dbolist('SELECT DISTINCT B.chip, B.type FROM benchmarks B WHERE B.chip NOT IN (
		SELECT DISTINCT C.chip FROM bench_chips C WHERE C.devicetype = B.type
	)');

	foreach ($rows as $row) {
		if (empty($row['chip']) || empty($row['type'])) continue;
		$chip = new db_bench_chips;
		$chip->chip = $row['chip'];
		$chip->devicetype = $row['type'];
		if ($chip->insert()) {
			debuglog("bench: added {$chip->devicetype} chip {$chip->chip}");
			dborun('UPDATE benchmarks SET idchip=:id WHERE chip=:chip AND type=:type', array(
				':id'=>$chip->id, ':chip'=>$row['chip'], ':type'=>$row['type'],
			));
		}
	}

	// update existing ones
	$rows = dbolist('SELECT DISTINCT chip, type FROM benchmarks WHERE idchip IS NULL');
	foreach ($rows as $row) {
		if (empty($row['chip']) || empty($row['type'])) continue;
		$chip = getdbosql('db_bench_chips', 'chip=:name AND devicetype=:type', array(':name'=>$row['chip'], ':type'=>$row['type']));
		if (!$chip || !$chip->id) continue;
		dborun('UPDATE benchmarks SET idchip=:id WHERE chip=:chip AND type=:type', array(
			':id'=>$chip->id, ':chip'=>$row['chip'], ':type'=>$row['type'],
		));
	}

}

///////////////////////////////////////////////////////////////////////////////////////////////////////////
