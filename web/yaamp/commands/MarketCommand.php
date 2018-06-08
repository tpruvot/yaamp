<?php
/**
 * MarketCommand is a console command, to query market and history
 *
 * To use this command, enter the following on the command line:
 * <pre>
 * php web/yaamp/yiic.php market help
 * </pre>
 *
 * @property string $help The command description.
 *
 */
class MarketCommand extends CConsoleCommand
{
	protected $basePath;

	/**
	 * Execute the action.
	 * @param array $args command line parameters specific for this command
	 * @return integer non zero application exit code after printing help
	 */
	public function run($args)
	{
		$runner=$this->getCommandRunner();
		$commands=$runner->commands;

		$root = realpath(Yii::app()->getBasePath().DIRECTORY_SEPARATOR.'..');
		$this->basePath = str_replace(DIRECTORY_SEPARATOR, '/', $root);

		$symbol = arraySafeVal($args, 0);

		if (!isset($args[1]) || $args[1] == 'help') {

			echo "Yiimp market command\n";
			echo "Usage: yiimp market <SYM> list\n";
			echo "       yiimp market <SYM> histo <exchange>\n";
			echo "       yiimp market <SYM> prune\n";
			echo "       yiimp market <SYM> get <exchange> <key>\n";
			echo "       yiimp market <SYM> set <exchange> <key> <value>\n";
			echo "       yiimp market <SYM> unset <exchange> <key>\n";
			echo "       yiimp market <SYM> settings <exchange>\n";
			return 1;

		} else if ($args[1] == 'list') {

			$this->listMarkets($symbol);
			return 0;

		} else if ($args[1] == 'histo') {

			$market = arraySafeVal($args,2,'');
			if (empty($market)) die("Usage: yiimp market <SYM> histo <market>\n");

			$this->queryMarketHistory($symbol, $market);
			return 0;

		} else if ($args[1] == 'prune') {

			marketHistoryPrune($symbol);
			return 0;

		} else if ($args[1] == 'get') {
			return $this->getMarketSetting($args);

		} else if ($args[1] == 'set') {
			return $this->setMarketSetting($args);

		} else if ($args[1] == 'unset') {
			return $this->unsetMarketSetting($args);

		} else if ($args[1] == 'settings') {
			return $this->listMarketSettings($args);
		}

	}

	/**
	 * Provides the command description.
	 * @return string the command description.
	 */
	public function getHelp()
	{
		return $this->run(array('help'));
	}

	private function checkSymbol($symbol)
	{
		return dboscalar("SELECT COUNT(*) FROM coins WHERE symbol=:symbol",
			array(':symbol'=>$symbol));
	}

	private function checkExchangeValue($exchange)
	{
		return dboscalar("SELECT COUNT(*) FROM markets WHERE name=:exch",
			array(':exch'=>$exchange));
	}

	public function getMarketSetting($args)
	{
		if (count($args) < 4)
			die("usage: yiimp market <SYM> get <exchange> <key>\n");
		$symbol   = $args[0];
		$exchange = $args[2];
		$key      = $args[3];
		if (!$this->checkSymbol($symbol)) {
			echo "error: symbol '$symbol' does not exist!\n";
			return 1;
		}
		if (!$this->checkExchangeValue($exchange)) {
			echo "error: exchange '$exchange' does not exist!\n";
			return 1;
		}
		$value = market_get($exchange, $symbol, $key);
		echo "$value\n";
		return 0;
	}

	public function setMarketSetting($args)
	{
		if (count($args) < 5)
			die("usage: yiimp market <SYM> set <exchange> <key> <value>\n");
		$symbol   = $args[0];
		$exchange = $args[2];
		$key      = $args[3];
		$value    = $args[4];
		if (!$this->checkSymbol($symbol)) {
			echo "error: symbol '$symbol' does not exist!\n";
			return 1;
		}
		if (!$this->checkExchangeValue($exchange)) {
			echo "error: exchange '$exchange' does not exist!\n";
			return 1;
		}
		$res = market_set($exchange, $symbol, $key, $value);
		$val = market_get($exchange, $symbol, $key);
		echo ($res ? "$symbol $exchange $key ".json_encode($val) : "error") . "\n";
		return 0;
	}

	public function unsetMarketSetting($args)
	{
		if (count($args) < 4)
			die("usage: yiimp market <SYM> unset <exchange> <key>\n");
		$symbol   = $args[0];
		$exchange = $args[2];
		$key      = $args[3];
		if (!$this->checkSymbol($symbol)) {
			echo "error: symbol '$symbol' does not exist!\n";
			return 1;
		}
		if (!$this->checkExchangeValue($exchange)) {
			echo "error: exchange '$exchange' does not exist!\n";
			return 1;
		}
		market_unset($exchange, $symbol, $key);
		echo "ok\n";
		return 0;
	}

	public function listMarketSettings($args)
	{
		if (count($args) < 3)
			die("usage: yiimp market <SYM> settings <exchange>\n");
		$symbol = $args[0];
		$exchange = $args[2];
		if (!$this->checkSymbol($symbol)) {
			echo "error: symbol '$symbol' does not exist!\n";
			return 1;
		}
		if (!$this->checkExchangeValue($exchange)) {
			echo "error: exchange '$exchange' does not exist!\n";
			return 1;
		}
		$settings = market_settings_prefetch($exchange);
		foreach ($settings as $key => $value) {
			if ($key === $exchange) continue;
			if (strpos($key, $symbol) !== false) {
				echo "$key ".json_encode($value)."\n";
			}
		}
		return 0;
	}

	/**
	 * List markets of a currency
	 */
	public function listMarkets($symbol)
	{
		require_once($this->basePath.'/yaamp/core/core.php');

		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if (!$coin) die("coin $symbol not found!\n");

		$markets = new db_markets;
		foreach ($markets->findAll("coinid={$coin->id} ORDER BY disabled, price DESC") as $market) {
			$price = $market->disabled ? '*DISABLED*' : bitcoinvaluetoa($market->price);
			if (market_get($market->name, $symbol, "disabled") || $market->deleted) $price = ' *DELETED*';
			echo "{$price} {$market->name}\n";
		}
	}

	/**
	 * Query market history of a "watched" currency
	 */
	public function queryMarketHistory($symbol, $market)
	{
		require_once($this->basePath.'/yaamp/core/core.php');

		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if (!$coin) die("coin $symbol not found!\n");

		//$last_update = (int) dboscalar(
		//	"SELECT time FROM market_history WHERE idcoin={$coin->id} ORDER BY id DESC LIMIT 1"
		//);
		//if (time() - $last_update > 300) BackendWatchMarkets();

		$history = new db_market_history;
		$c = new CDbCriteria;
		$c->condition = "idcoin={$coin->id}";
		if ($coin->symbol != 'BTC') $c->condition .= " AND mh_market.name='$market'"; // table alias set in model
		$c->order = 'time DESC';
		$c->limit = 100;
		$items = getdbolistWith('db_market_history', 'market', $c);

		foreach ($items as $histo) {
			$date = strftime('%F %T', $histo->time);
			$price1 = bitcoinvaluetoa($histo->price);
			$price2 = bitcoinvaluetoa($histo->price2);
			echo "$date $price1 $price2\n";
		}
	}
}
