<?php

function NotifyCheckRules()
{
	$time = time();
	$since = $time - (10 * 60); // for later cmdline check, cron doesn't need that

	$search = new db_notifications;
	$notifications = $search->with('coin')->findAll(
		"enabled AND lastchecked <= {$since} ".
		" AND (nc.installed OR nc.enable OR nc.watch)"
	);

	foreach($notifications as $rule)
	{
		$coin = $rule->coin;

		$condw = explode(" ", $rule->conditiontype);
		$field = $condw[0];
		if (count($condw) < 2) {
			debuglog("notify: invalid conditiontype for {coin->symbol}, need at least one space like 'price >'");
			continue;
		}
		$comp = $condw[1];

		$triggered = false;
		if (array_key_exists($field, $rule->coin->attributes)) {
			$value  = $rule->coin->attributes[$field];
			$valref = $rule->conditionvalue;
			switch ($comp) {
			case '<':
				$triggered = $value < $valref;
				break;
			case '>':
				$triggered = $value > $valref;
				break;
			case '<=':
				$triggered = $value <= $valref;
				break;
			case '>=':
				$triggered = $value >= $valref;
				break;
			case '=':
			case '==':
				$triggered = $value == $valref;
				break;
			}
		} else {
			debuglog("notify: invalid field '{$field}' in conditiontype for {$coin->symbol}!");
			continue;
		}

		if ($triggered && $rule->lasttriggered == $rule->lastchecked) {
			// already notified
			$rule->lasttriggered = $time;
			$rule->lastchecked = $time;
			$rule->save();
			continue;
		} else {
			$rule->lasttriggered = $triggered ? $time : 0;
			$rule->lastchecked = $time;
			$rule->save();
		}

		if (!$triggered) continue;

		$value = bitcoinvaluetoa($value);
		debuglog("trigger: {$coin->symbol} {$rule->notifytype} {$rule->conditiontype} {$rule->conditionvalue} ({$value})");

		switch ($rule->notifytype)
		{
			case 'email':
				$subject = "[{$coin->symbol}] Trigger {$rule->conditiontype} {$rule->conditionvalue} ({$value})";

				$message  = "Description: {$rule->description}\n\n";

				$message .= "Field: {$field}\n";
				$message .= "Value: {$value} at ".strftime("%Y-%m-%d %T %z", $time)."\n";

				// replace some possible vars in message (description)
				$message = str_replace('$X', $value, $message);
				$message = str_replace('$F', $field, $message);
				$message = str_replace('$T', $rule->conditiontype, $message);
				$message = str_replace('$V', $rule->conditionvalue, $message);
				$message = str_replace('$N', $coin->name, $message);
				$message = str_replace('$SYM', $coin->symbol, $message);
				$message = str_replace('$S2', $coin->symbol2, $message);
				$message = str_replace('$A', $coin->master_wallet, $message);

				$dest = YAAMP_ADMIN_EMAIL;
				if (!empty($rule->notifycmd) && strstr($rule->notifycmd, "@")) {
					$dest = $rule->notifycmd;
				}

				$res = mail($dest, $subject, $message);
				if (!$res)
					debuglog("notify: {$coin->symbol} unable to send mail to {$dest}!");
				break;

			case 'rpc':

				$command = $rule->notifycmd;

				// replace some possible vars in user command
				$command = str_replace('$X', $value, $command);
				$command = str_replace('$F', $field, $command);
				$command = str_replace('$T', $rule->conditiontype, $command);
				$command = str_replace('$V', $rule->conditionvalue, $command);
				$command = str_replace('$N', $coin->name, $command);
				$command = str_replace('$SYM', $coin->symbol, $command);
				$command = str_replace('$S2', $coin->symbol2, $command);
				$command = str_replace('$A', $coin->master_wallet, $command);

				$remote = new WalletRPC($coin);

				$res = $remote->execute($command);
				if ($res === false)
					debuglog("trigger: {$coin->symbol} rpc error '{$command}' {$remote->error}");
				else
					debuglog("trigger: {$coin->symbol} rpc -> $res");
				break;

			case 'system':

				$command = $rule->notifycmd;

				// replace some possible vars in user command
				$command = str_replace('$X', $value, $command);
				$command = str_replace('$F', $field, $command);
				$command = str_replace('$T', $rule->conditiontype, $command);
				$command = str_replace('$V', $rule->conditionvalue, $command);
				$command = str_replace('$N', $coin->name, $command);
				$command = str_replace('$SYM', $coin->symbol, $command);
				$command = str_replace('$S2', $coin->symbol2, $command);
				$command = str_replace('$A', $coin->master_wallet, $command);

				$res = system($command);
				if ($res === false)
					debuglog("trigger: {$coin->symbol} unable to execute '{$command}'!");
				break;
		}
	}
}
