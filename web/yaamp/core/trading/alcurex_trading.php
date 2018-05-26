<?php

function doAlcurexTrading()
{
	$exchange = 'alcurex';

	if (exchange_get($exchange, 'disabled')) return;

	if (!YAAMP_ALLOW_EXCHANGE) return;
}
