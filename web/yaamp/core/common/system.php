<?php

function send_email_alert($name, $title, $message, $t=10)
{
//	debuglog(__FUNCTION__);

	$last = memcache_get(controller()->memcache->memcache, "last_email_sent_$name");
	if($last + $t*60 > time()) return;

	debuglog("mail('".YAAMP_ADMIN_EMAIL."', $title, ...)");

	$b = mail(YAAMP_ADMIN_EMAIL, $title, $message);
	if(!$b) debuglog('error sending email');

	memcache_set(controller()->memcache->memcache, "last_email_sent_$name", time());
}

function dos_filesize($fn)
{
	return exec('FOR %A IN ("'.$fn.'") DO @ECHO %~zA');
}
