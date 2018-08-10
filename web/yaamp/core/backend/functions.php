<?php
/**
 * Directly output Logs in screen (main/blocks/loop2)
 */
function screenlog($line)
{
	$app = Yii::app();
	if ($app instanceof CYiimpConsoleApp) {
		$date = date('y-m-d H:i:s');
		echo("$date $line\n");
	}
}
