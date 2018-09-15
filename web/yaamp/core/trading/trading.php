<?php
require_once('poloniex_trading.php');
require_once('binance_trading.php');
require_once('bittrex_trading.php');
require_once('bleutrade_trading.php');
require_once('bter_trading.php');
require_once('c-cex_trading.php');
require_once('kraken_trading.php');
require_once('yobit_trading.php');
require_once('alcurex_trading.php');
require_once('coinsmarkets_trading.php');
require_once('crex24_trading.php');
require_once('cryptopia_trading.php');
require_once('hitbtc_trading.php');
require_once('kucoin_trading.php');
require_once('livecoin_trading.php');
require_once('nova_trading.php');


function cancelExchangeOrder($order=false)
{
	if ($order)
		switch ($order->market)
		{
			case 'yobit':
				doYobitCancelOrder($order->uuid);
				break;
			case 'binance':
				doBinanceCancelOrder($order->uuid);
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
			case 'crex24':
				doCrex24CancelOrder($order->uuid);
				break;
			case 'cryptopia':
				doCryptopiaCancelOrder($order->uuid);
				break;
			case 'hitbtc':
				doHitBTCCancelOrder($order->uuid);
				break;
			case 'kucoin':
				doKuCoinCancelOrder($order->uuid);
				break;
			case 'livecoin':
				doLiveCoinCancelOrder($order->uuid);
				break;

		}
}

function runExchange($exchangeName=false)
{
	if (!empty($exchangeName))
	{
		switch($exchangeName)
		{
			case 'alcurex':
				//doAlcurexTrading(true);
				updateAlcurexMarkets();
				break;

			case 'binance':
				doBinanceTrading(true);
				updateBinanceMarkets();
				break;

			case 'bter':
				doBterTrading(true);
				updateBterMarkets();
				break;

			case 'crex24':
				doCrex24Trading(true);
				updateCrex24Markets();
				break;

			case 'cryptopia':
				doCryptopiaTrading(true);
				updateCryptopiaMarkets();
				break;

			case 'cryptobridge':
				updateCryptoBridgeMarkets();
				break;

			case 'bitstamp':
				getBitstampBalances();
				break;

			case 'bittrex':
				doBittrexTrading(true);
				updateBittrexMarkets();
				break;
			case 'bitz':
				updateBitzMarkets();
				break;

			case 'cexio':
				getCexIoBalances();
				break;

			case 'c-cex':
				doCCexTrading(true);
				updateCCexMarkets();
				break;

			case 'coinexchange':
				updateCoinExchangeMarkets();
				break;

			case 'coinsmarkets':
				doCoinsMarketsTrading(true);
				updateCoinsMarketsMarkets();
				break;

			case 'empoex':
				//doEmpoexTrading(true);
				//updateEmpoexMarkets();
				break;

			case 'yobit':
				doYobitTrading(true);
				updateYobitMarkets();
				break;

			case 'bleutrade':
				doBleutradeTrading(true);
				updateBleutradeMarkets();
				break;

			case 'hitbtc':
				doHitBTCTrading(true);
				updateHitBTCMarkets();
				break;

			case 'kraken':
				doKrakenTrading(true);
				updateKrakenMarkets();
				break;

			case 'kucoin':
				doKuCoinTrading(true);
				updateKucoinMarkets();
				break;

			case 'livecoin':
				doLiveCoinTrading(true);
				updateLiveCoinMarkets();
				break;

			case 'nova':
				doNovaTrading(true);
				updateNovaMarkets();
				break;

			case 'poloniex':
				doPoloniexTrading(true);
				updatePoloniexMarkets();
				break;

			default:
				debuglog(__FUNCTION__.' '.$exchangeName.' not implemented');
		}
	}
}
