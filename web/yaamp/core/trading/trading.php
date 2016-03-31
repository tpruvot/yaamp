<?php

require_once('poloniex_trading.php');
require_once('bittrex_trading.php');
require_once('bleutrade_trading.php');
require_once('bter_trading.php');
require_once('cryptsy_trading.php');
require_once('c-cex_trading.php');
require_once('kraken_trading.php');
require_once('yobit_trading.php');
require_once('alcurex_trading.php');
require_once('banx_trading.php');
require_once('cryptopia_trading.php');
require_once('safecex_trading.php');

function cancelExchangeOrder($order=false) {

	if ($order)
		switch ($order->market)
		{
			case 'yobit':
				doYobitCancelOrder($order->uuid);
				break;
			case 'c-cex':
				doCCexCancelOrder($order->uuid);
				break;
			case 'bittrex':
				doBittrexCancelOrder($order->uuid);
				break;
			case 'bleutrade':
				doBleutradeCancelOrder($order->uuid);
				break;
			case 'safecex':
				doSafecexCancelOrder($order->uuid);
				break;
			case 'cryptopia':
				doCryptopiaCancelOrder($order->uuid);
				break;
		}
}

function runExchange($exchangeName=false) {

	if ($exchangeName)
		switch($exchangeName)
		{
			case 'alcurex':
				//doAlcurexTrading(true);
				updateAlcurexMarkets();
				break;

			case 'banx':
				doBanxTrading(true);
				updateBanxMarkets();
				break;

			case 'bter':
				doBterTrading(true);
				updateBterMarkets();
				break;

			case 'cryptopia':
				doCryptopiaTrading(true);
				updateCryptopiaMarkets();
				break;

			case 'cryptsy':
				//doCryptsyTrading(true);
				updateCryptsyMarkets();
				break;

			case 'bittrex':
				doBittrexTrading(true);
				updateBittrexMarkets();
				break;

			case 'c-cex':
				doCCexTrading(true);
				updateCCexMarkets();
				break;

			case 'empoex':
				//doEmpoexTrading(true);
				//updateEmpoexMarkets();
				break;

			case 'safecex':
				doSafecexTrading(true);
				updateSafecexMarkets();
				break;

			case 'yobit':
				doYobitTrading(true);
				updateYobitMarkets();
				break;

			case 'bleutrade':
				doBleutradeTrading(true);
				updateBleutradeMarkets();
				break;

			case 'kraken':
				doKrakenTrading(true);
				updateKrakenMarkets();
				break;

			case 'poloniex':
				doPoloniexTrading(true);
				updatePoloniexMarkets();
				break;
		}
}