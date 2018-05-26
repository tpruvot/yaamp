<?php

function getProductIdSuffix($row)
{
	$vidpid = $row['vendorid'];

	$known = array(
		// ASUS 960
		'1043:8520' => 'Strix',
		// ASUS 970
		'1043:8508' => 'Strix',
		// Gigabyte 750 ti
		'1458:362d' => 'OC',
		'1458:3649' => 'Black',
		// Gigabyte 960
		'1458:36ae' => '4GB',
		// Gigabyte 1070
		'1458:3701' => 'G1',
		// Gigabyte 1080
		'1458:3702' => 'G1',
		// MSI 960
		'1462:3202' => 'Gaming 2G',
		// MSI 970
		'1462:3160' => 'Gaming',
		// MSI 980
		'1462:3170' => 'Gaming',
		// MSI 1070
		'1462:3301' => 'Armor',
		'1462:3306' => 'Gaming X',
		// MSI 1080
		'1462:3362' => 'Gaming',
		// Zotac 1070
		'19da:1435' => 'Extreme',
		// EVGA 740
		'3842:2744' => 'SC DDR3',
		// EVGA 750 Ti
		'3842:3753' => 'SC',
		'3842:3757' => 'FTW',
		// EVGA 950
		'3842:2951' => 'SC',
		'3842:2956' => 'SC+',
		'3842:2957' => 'SSC',
		'3842:2958' => 'FTW',
		// EVGA 960
		'3842:2962' => 'SC',
		'3842:2966' => 'SSC',
		'3842:3966' => 'SSC 4GB',
		// EVGA 970
		'3842:2974' => 'SC',
		'3842:2978' => 'FTW',
		'3842:3975' => 'SSC',
		// EVGA 980
		'3842:2983' => 'SC',
		'3842:2986' => 'FTW',
		'3842:2989' => 'Hydro',
		// EVGA 980 Ti
		'3842:4995' => 'SC+',
		'3842:1996' => 'Hybrid',
		// EVGA 1070
		'3842:6173' => 'SC',
		'3842:6276' => 'FTW',
	);

	if (isset($known[$vidpid])) {
		return ' '.$known[$vidpid];
	}

	// table with user suffixes...
	$suffix = dboscalar("SELECT suffix FROM bench_suffixes WHERE vendorid=:vid",
		array(':vid'=>$vidpid)
	);
	if (!empty($suffix))
		return ' '.$suffix;

	return '';
}

function formatCudaArch($arch)
{
	if (is_numeric($arch)) {
		$a = intval($arch);
		return 'SM '.floor($a / 100).'.'.(($a % 100)/10);
	} else if (strpos($arch, '@')) {
		$p = explode('@', $arch);
		$a = intval($p[0]);
		$b = intval($p[1]);
		$hard = floor($a / 100).'.'.(($a % 100)/10);
		$real = floor($b / 100).'.'.(($b % 100)/10);
		return "SM {$hard}@{$real}";
	}
	return $arch;
}

function formatCPU($row)
{
	$device = preg_replace('/[ \t]+/', ' ', $row['device']);
	if (strpos($device, '(R)')) {
		// from /proc/cpuinfo (or vendor cpuid)
		$device = str_replace('(R)', '', $device);
		$device = str_replace(' CPU','', $device);
		$device = str_replace(' V2',' v2', $device);
		$device = str_replace(' V3',' v3', $device);
		$device = str_replace(' V4',' v4', $device);
	} else {
		// from windows env PROCESSOR_IDENTIFIER (to reduce the len)
		$device = str_replace(' Family', '', $device);
		$device = str_replace(' Stepping ', '.', $device);
		$device = str_replace(' GenuineIntel', ' Intel', $device);
		$device = str_replace(' AuthenticAMD', ' AMD', $device);
		$device = str_replace(' Quad-Core','', $device);
		$device = str_replace(' Dual-Core','', $device);
		$device = str_replace(' Triple-Core','', $device);
		$device = str_replace(' Quad Core','', $device);
		$device = str_replace(' Dual Core','', $device);
		$device = str_replace(' Triple Core','', $device);
		$device = str_replace(' Processor', '', $device);
		if (strpos($device, 'Intel64') !== false && strpos($device, ' Intel')) {
			$device = str_replace(' Intel','', $device);
			$device = str_replace('Intel64','Intel', $device);
		}
		if (strpos($device, 'AMD64') !== false && strpos($device, ' AMD')) {
			$device = str_replace(' AMD','', $device);
			$device = str_replace('AMD64','AMD', $device);
		}
		$device = rtrim($device, ',');
	}
	$device = str_ireplace('(tm)','', $device);
	$device = str_replace(' APU with Radeon','', $device);
	$device = str_replace(' APU with AMD Radeon','', $device);
	$device = str_replace(' version ',' ', $device);
	$device = str_replace(' Core2 Quad',' Core2-Quad', $device);
	$device = preg_replace('/(HD|R\d) Graphics/','', $device);
	$device = preg_replace('/ 0$/', '', $device);
	// VIA Nano processor U2250 (1.6GHz Capable)
	$device = str_replace(' (1.6GHz Capable)','', $device);
	if (stristr($device, 'Virtual CPU') || stristr($device, 'QEMU')) {
		$row['chip'] = 'Virtual';
		$device = 'Virtual';
	}
	return trim($device);
}

function formatGPU($row)
{
	$label = $row['device'].getProductIdSuffix($row);
	return strip_tags($label);
}

function formatDevice($row)
{
	if ($row['type'] == 'gpu')
		return formatGPU($row);
	else
		return formatCPU($row);
}

function getChipName($row)
{
	if ($row['type'] == 'cpu') {

		$device = formatCPU($row);
		$device = str_ireplace(' V2', 'v2', $device);
		$device = str_ireplace(' V2', 'v2', $device);
		$device = str_ireplace(' V2', 'v2', $device);
		$device = str_ireplace(' V3', 'v3', $device);
		$device = str_ireplace(' V4', 'v4', $device);
		$device = str_ireplace(' V5', 'v5', $device);
		if (strpos($device, 'AMD Athlon ')) {
			return str_replace('AMD ', '', $device);
		}
		$device = preg_replace('/AMD (A6\-[1-9]+[KM]*) APU .+/','\1', $device);
		$device = preg_replace('/AMD (E[\d]*\-[\d]+) APU .+/','\1', $device);
		$device = preg_replace('/AMD (A[\d]+\-[\d]+[KP]*) Radeon .+/','\1', $device);
		$words = explode(' ', $device);
		$chip = array_pop($words);
		if (strpos($device, 'Fam.')) $chip = '-'; // WIN ENV

	} else {

		// nVidia
		$device = str_replace(' with Max-Q Design', '', $row['device']);
		$device = str_replace(' COLLECTORS EDITION', '', $device);

		$words = explode(' ', $device);
		$chip = array_pop($words);
		$vendorid = $row['vendorid'];
		if (!is_numeric($chip)) {
			// 750 Ti / 1060 3GB / GeForce 920M / Tesla M60
			$chip = array_pop($words).' '.$chip;
			$chip = str_replace('GeForce ','', $chip);
			$chip = str_replace('GT ','', $chip);
			$chip = str_replace('GTX ','', $chip);
			$chip = str_replace('650 Ti BOOST','650 Ti', $chip);
			$chip = str_replace('760 Ti OEM','760 Ti', $chip);
			$chip = str_replace(' (Pascal)',' Pascal', $chip);
			$chip = str_replace('Quadro M6000 24GB','Quadro M6000', $chip);
			$chip = str_replace('Tesla P100 (PCIe)','Tesla P100', $chip);
			$chip = str_replace('Tesla P100-SXM2-16GB','Tesla P100', $chip);
			$chip = str_replace('Tesla P100-PCIE-16GB','Tesla P100', $chip);
			$chip = str_replace('Tesla V100-SXM2-16GB','Tesla V100', $chip);
			$chip = preg_replace('/ASUS ([6-9]\d\dM)/','\1', $chip); // ASUS 940M
			$chip = preg_replace('/MSI ([6-9]\d\dM)/','\1', $chip); // MSI 840M
			$chip = preg_replace('/MSI ([6-9]\d\dMX)/','\1', $chip); // MSI 940MX
			if (strpos($chip, 'P106-100') !== false || strpos($chip, 'CMP3-1') !== false)
				$chip = 'P106-100';
			if (strpos($chip, 'P104-100') !== false || strpos($chip, 'CMP4-1') !== false)
				$chip = 'P104-100';
		}
		// Quadro 600 - Quadro 2000
		if (strstr($row['device'], 'Quadro') && !strstr($chip, 'Quadro')) $chip = "Quadro $chip";
	}

	return $chip;
}

function formatClientName($version)
{
	return $version;
}

function powercost_mBTC($watts)
{
	$btcusd = (double) controller()->memcache->get_database_scalar("btc_in_usd", "SELECT usdbtc FROM mining LIMIT 1");
	if (!$btcusd) $btcusd = 500;
	return (YIIMP_KWH_USD_PRICE * 24 * $watts) / $btcusd;
}
