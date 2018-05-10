<?php

// DB Settings, initially made for trade settings on exchanges

function settings_key_type($key)
{
	if (strpos($key, 'enabled') !== false) return 'bool';
	if (strpos($key, 'disabled') !== false) return 'bool';
	if (substr($key, -3) == 'pct') return 'percent';
	if (substr($key, -3) == 'btc') return 'price';
	if (substr($key, -5) == 'price') return 'price';
	return 'string';
}

function settings_get($key, $default=null)
{
	$row = dborow("SELECT value, type FROM settings WHERE param=:key", array(':key'=>$key));
	if (!$row) return $default;

	$type = arraySafeVal($row, 'type', settings_key_type($key));
	$value = $row['value'];
	switch ($type) {
	case 'bool':
		return (bool) $value;
	case 'int':
		return intval($value);
	case 'percent':
		return ((double) $value) / 100.0;
	case 'price':
	case 'real':
		return (double) $value;
	case 'json':
		return json_decode($value, true);
	}
	return $value;
}

function settings_set($key, $value)
{
	$type = settings_key_type($key);
	if ($type == 'json') {
		$value = json_encode($value);
		if (strlen($value) > 255) {
			debuglog("warning: settings_set($key) value is too long!");
			return false;
		}
	}

	if ($type == 'bool' && strcasecmp($value,'true') == 0) $value = 1;
	if ($type == 'bool' && strcasecmp($value,'false')== 0) $value = 0;
	if ($type == 'percent' && strpos($value, '%') === false) $value = floatval($value) * 100;

	dborun("INSERT IGNORE INTO settings(param,value) VALUES (:key,:val)", array(
		':key'=>$key,':val'=>$value
	));
	dborun("UPDATE settings SET value=:val, type=:type WHERE param=:key", array(
		':key'=>$key,':val'=>$value,':type'=>$type
	));
	return true;
}

function settings_set_default($key, $value)
{
	$nb = dboscalar("SELECT COUNT(param) FROM settings WHERE param=:key", array(':key'=>$key));
	if ($nb) return false;
	return settings_set($key, $value);
}

function settings_unset($key)
{
	dborun("DELETE FROM settings WHERE param=:key", array(':key'=>$key));
	return 0;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////

// exchange specific settings

$cacheset_exchange = array();
function exchange_settings_prefetch()
{
	global $cacheset_exchange;

	$exchanges = dbocolumn("SELECT DISTINCT name FROM markets");
	foreach ($exchanges as $exchange) {
		$settings = dbocolumn("SELECT param FROM settings WHERE param LIKE '{$exchange}-%'");
		if (!$settings) return array();
		foreach ($settings as $key) {
			if (substr_count($key,'-') > 2) continue;
			$cacheset_exchange[$key] = settings_get($key);
		}
		$cacheset_exchange[$exchange] = true;
	}
	return $cacheset_exchange;
}

function exchange_get($exchange, $key, $default=null)
{
	// cache to prevent repeated sql queries in loops
	global $cacheset_exchange;
	if (isset($cacheset_exchange[$exchange])) {
		return arraySafeVal($cacheset_exchange, "$exchange-$key", $default);
	}
	$value = settings_get("$exchange-$key", $default);
	return $value;
}

function exchange_set($exchange, $key, $value)
{
	global $cacheset_exchange;
	$cacheset_exchange = array();
	return settings_set("$exchange-$key", $value);
}

function exchange_set_default($exchange, $key, $value)
{
	// set value, only if not already exist in database
	$res = settings_set_default("$exchange-$key", $value);
	if ($res) {
		global $cacheset_exchange;
		$cacheset_exchange = array();
	}
	return $res;
}

function exchange_unset($exchange, $key)
{
	// reset to default value
	global $cacheset_exchange;
	$cacheset_exchange = array();
	return settings_unset("$exchange-$key");
}

/**
 * Returns the list of valid keys, with a description
 */
function exchange_valid_keys()
{
	return array(
		'disabled' => 'Fully disable the exchange',

		'trade_min_btc'			=> 'Minimum order on the exchange',
		'trade_sell_ask_pct'	=> 'Initial order ask price related to the lowest ask (in %)',
		'trade_cancel_ask_pct'	=> 'Cancel orders if the lowest ask reach this % of your order',

	//	'withdraw_btc_address'	=> 'Custom withdraw BTC address for the exchange',
		'withdraw_min_btc'		=> 'Auto withdraw when your BTC balance reach this amount (0=disabled)',
		'withdraw_fee_btc'		=> 'Fees in BTC required to withdraw BTCs on the exchange',
	);
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////

// market specific user settings

$cacheset_market = array();
function market_settings_prefetch($exchange)
{
	global $cacheset_market;
	$settings = dbocolumn("SELECT param FROM settings WHERE param LIKE '{$exchange}-%'");
	if (!$settings) return array();
	foreach ($settings as $key) {
		if (substr_count($key,'-') < 3) continue;
		$cacheset_market[$key] = settings_get($key);
	}
	$cacheset_market[$exchange] = true;
	return $cacheset_market;
}

function market_settings_prefetch_all()
{
	$exchanges = dbocolumn("SELECT DISTINCT name FROM markets");
	foreach ($exchanges as $name) {
		market_settings_prefetch($name);
	}
}

function market_get($exchange, $symbol, $key, $default=null, $base='BTC')
{
	// cache to prevent repeated sql queries in loops
	global $cacheset_market;
	if (isset($cacheset_market[$exchange])) {
		return arraySafeVal($cacheset_market, "$exchange-$symbol-$base-$key", $default);
	}
	$value = settings_get("$exchange-$symbol-$base-$key", $default);
	return $value;
}

function market_set($exchange, $symbol, $key, $value, $base='BTC')
{
	global $cacheset_market;
	$cacheset_market = array();
	return settings_set("$exchange-$symbol-$base-$key", $value);
}

function market_set_default($exchange, $symbol, $key, $value, $base='BTC')
{
	$res = settings_set_default("$exchange-$symbol-$base-$key", $value);
	if ($res) {
		global $cacheset_market;
		$cacheset_market = array();
	}
	return $res;
}

function market_unset($exchange, $symbol, $key, $base='BTC')
{
	global $cacheset_market;
	$cacheset_market = array();
	return settings_unset("$exchange-$symbol-$base-$key");
}

/**
 * Returns a list of market valid keys, with a description
 */
function market_valid_keys()
{
	return array(
		'disabled' => 'Fully disable (ignore) the market',
	);
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////

// coin specific user settings

$cacheset_coin = array();
function coin_settings_prefetch($symbol)
{
	global $cacheset_coin;
	$settings = dbocolumn("SELECT param FROM settings WHERE param LIKE 'coin-{$symbol}-%'");
	if (!$settings) return array();
	foreach ($settings as $key) {
		$cacheset_coin[$key] = settings_get($key);
	}
	$cacheset_coin[$symbol] = true;
	return $cacheset_coin;
}

function coin_settings_prefetch_all()
{
	global $cacheset_coin;
	$settings = dbocolumn("SELECT param FROM settings WHERE param LIKE 'coin-%'");
	if (!$settings) return array();
	foreach ($settings as $key) {
		$parts = explode('-',$key);
		$symbol = $parts[1];
		$cacheset_coin[$key] = settings_get($key);
		$cacheset_coin[$symbol] = true;
	}
	return $cacheset_coin;
}


function coin_get($symbol, $key, $default=null)
{
	// cache to prevent repeated sql queries in loops
	global $cacheset_coin;
	if (isset($cacheset_coin[$symbol])) {
		return arraySafeVal($cacheset_coin, "coin-$symbol-$key", $default);
	}
	$value = settings_get("coin-$symbol-$key", $default);
	return $value;
}

function coin_set($symbol, $key, $value)
{
	global $cacheset_coin;
	$cacheset_coin = array();
	return settings_set("coin-$symbol-$key", $value);
}

function coin_set_default($symbol, $key, $value)
{
	$res = settings_set_default("coin-$symbol-$key", $value);
	if ($res) {
		global $cacheset_coin;
		$cacheset_coin = array();
	}
	return $res;
}

function coin_unset($exchange, $symbol, $key)
{
	global $cacheset_coin;
	$cacheset_coin = array();
	return settings_unset("coin-$symbol-$key");
}

/**
 * Returns a list of handled settings keys, with a description
 */
function coin_valid_keys()
{
	return array(
	);
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////

function settings_prefetch_all()
{
	$exchanges = dbocolumn("SELECT DISTINCT name FROM markets");
	foreach ($exchanges as $name) {
		exchange_settings_prefetch($name);
		market_settings_prefetch($name);
	}
	coin_settings_prefetch_all();
}
