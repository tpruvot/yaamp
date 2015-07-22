<?php

ini_set('date.timezone', 'UTC');

define('YAAMP_LOGS', '/var/log/yaamp');
define('YAAMP_HTDOCS', '/var/web');

define('YAAMP_DBHOST', 'localhost');
define('YAAMP_DBNAME', 'yaamp');
define('YAAMP_DBUSER', 'root');
define('YAAMP_DBPASSWORD', 'password');

define('YAAMP_PRODUCTION', true);
define('YAAMP_RENTAL', true);
define('YAAMP_LIMIT_ESTIMATE', false);

define('YAAMP_FEES_MINING', 0.5);
define('YAAMP_FEES_EXCHANGE', 2);
define('YAAMP_FEES_RENTING', 2);
define('YAAMP_PAYMENTS_FREQ', 3*60*60);

define('YAAMP_BTCADDRESS', '');
define('YAAMP_SITE_URL', '');
define('YAAMP_ADMIN_EMAIL', '');

define('EXCH_CRYPTSY_KEY','');
define('EXCH_CRYPTSY_SECRET','');

define('EXCH_BITTREX_KEY','');
define('EXCH_BITTREX_SECRET','');

define('EXCH_BLUETRADE_KEY','');
define('EXCH_BLUETRADE_SECRET','');

define('EXCH_CCEX_KEY','');
define('EXCH_CCEX_SECRET','');

define('EXCH_POLONIEX_KEY','');
define('EXCH_POLONIEX_SECRET','');

define('EXCH_YOBIT_KEY','');
define('EXCH_YOBIT_SECRET','');


$cold_wallet_table = array(
	'' => 0.10,
);

