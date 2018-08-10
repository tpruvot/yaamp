<?php

if(php_sapi_name() != "cli") return;

require_once('serverconfig.php');
require_once('yaamp/defaultconfig.php');

require_once('framework/yii.php');
require_once('yaamp/include.php');

require_once('yaamp/components/CYiimpConsoleApp.php');

$config = require_once('yaamp/console.php');
$app = Yii::createApplication('CYiimpConsoleApp', $config);

try
{
	$app->runController($argv[1]);
}

catch(CException $e)
{
	debuglog($e, 5);

	$message = $e->getMessage();
	echo "exception: $message\n";
// 	send_email_alert('backend', "backend error", "$message");
}

