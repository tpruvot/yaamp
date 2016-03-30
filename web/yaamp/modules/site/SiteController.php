<?php

class SiteController extends CommonController
{
	public $defaultAction='index';

	///////////////////////////////////////////////////

	public function actionAdminRights()
	{
		$client_ip = $_SERVER['REMOTE_ADDR'];

		$valid = false;
		if (strpos(YAAMP_ADMIN_IP, ','))
			$valid = in_array($client_ip, explode(',',YAAMP_ADMIN_IP), true);
		else
			$valid = ($client_ip === YAAMP_ADMIN_IP);

		if ($valid)
			debuglog("admin connect from $client_ip");
		else
			debuglog("admin connect failure from $client_ip");

		user()->setState('yaamp_admin', $valid);

		$this->redirect("/site/common");
	}

	/////////////////////////////////////////////////

	public function actionCreate()
	{
		if(!$this->admin) return;

		$coin = new db_coins;
		$coin->txmessage = true;
		$coin->created = time();
		$coin->index_avg = 1;
		$coin->difficulty = 1;
		$coin->installed = 1;
		$coin->visible = 1;

	//	$coin->deposit_minimum = 1;
		$coin->lastblock = '';

		if(isset($_POST['db_coins']))
		{
			$coin->attributes = $_POST['db_coins'];
			if($coin->save())
				$this->redirect(array('admin'));
		}

		$this->render('coin_form', array('update'=>false, 'coin'=>$coin));
	}

	public function actionUpdate()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$txfee = $coin->txfee;

		if(isset($_POST['db_coins']))
		{
			$coin->attributes = $_POST['db_coins'];
			if($coin->save())
			{
				if($txfee != $coin->txfee)
				{
					$remote = new Bitcoin($coin->rpcuser, $coin->rpcpasswd, $coin->rpchost, $coin->rpcport);
					$remote->settxfee($coin->txfee);
				}

			//	$this->redirect(array('admin'));
				$this->goback();
			}
		}

		$this->render('coin_form', array('update'=>true, 'coin'=>$coin));
	}

	/////////////////////////////////////////////////

	public function actionPeers()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if (!$coin) {
			$this->goback();
		}

		$this->render('coin_peers', array('coin'=>$coin));
	}

	public function actionPeerRemove()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$node = getparam('node');
		if ($coin && $node) {
			$remote = new Bitcoin($coin->rpcuser, $coin->rpcpasswd, $coin->rpchost, $coin->rpcport);
			if ($coin->symbol == 'DCR') {
				$res = $remote->node('disconnect', $node);
				if (!$res) $res = $remote->node('remove', $node);
				$remote->error = false; // ignore
			} else {
				$res = $remote->addnode($node, 'remove');
			}
			if (!$res && $remote->error) {
				user()->setFlash('error', "$node ".$remote->error);
			}
		}
		$this->goback();
	}

	public function actionPeerAdd()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$node = arraySafeVal($_POST, 'node');
		if ($coin && $node) {
			$remote = new Bitcoin($coin->rpcuser, $coin->rpcpasswd, $coin->rpchost, $coin->rpcport);
			if ($coin->symbol == 'DCR') {
				$remote->addnode($node, 'add');
				usleep(500*1000);
				$remote->node('connect', $node);
				sleep(1);
			} else {
				$res = $remote->addnode($node, 'add');
				if (!$res) {
					user()->setFlash('error', "$node ".$remote->error);
				} else {
					sleep(1);
				}
			}
		}
		$this->goback();
	}

	/////////////////////////////////////////////////

	public function actionIndex()
	{
		if(isset($_GET['address']))
			$this->render('wallet');
		else
			$this->render('index');
	}

	public function actionApi()
	{
		$this->render('api');
	}

	public function actionDiff()
	{
		$this->render('diff');
	}

	public function actionMultialgo()
	{
		$this->render('multialgo');
	}

	public function actionMining()
	{
		$this->render('mining');
	}

	public function actionMiners()
	{
		$this->render('miners');
	}

	/////////////////////////////////

	public function actionCurrent_results()
	{
		$this->renderPartial('results/current_results');
	}

	public function actionHistory_results()
	{
		$this->renderPartial('results/history_results');
	}

	public function actionMining_results()
	{
		$this->renderPartial('results/mining_results');
	}

	public function actionMiners_results()
	{
		$this->renderPartial('results/miners_results');
	}

	public function actionWallet_results()
	{
		$this->renderPartial('results/wallet_results');
	}

	public function actionWallet_miners_results()
	{
		$this->renderPartial('results/wallet_miners_results');
	}

	public function actionWallet_graphs_results()
	{
		$this->renderPartial('results/wallet_graphs_results');
	}

	public function actionGraph_earnings_results()
	{
		$this->renderPartial('results/graph_earnings_results');
	}

	public function actionFound_results()
	{
		$this->renderPartial('results/found_results');
	}

	public function actionUser_earning_results()
	{
		$this->renderPartial('results/user_earning_results');
	}

	public function actionGraph_hashrate_results()
	{
		$this->renderPartial('results/graph_hashrate_results');
	}

	public function actionGraph_user_results()
	{
		$this->renderPartial('results/graph_user_results');
	}

	public function actionGraph_price_results()
	{
		$this->renderPartial('results/graph_price_results');
	}

	public function actionGraph_assets_results()
	{
		$this->renderPartial('results/graph_assets_results');
	}

	public function actionGraph_negative_results()
	{
		$this->renderPartial('results/graph_negative_results');
	}

	public function actionGraph_profit_results()
	{
		$this->renderPartial('results/graph_profit_results');
	}

	public function actionTitle_results()
	{
		$user = getuserparam(getparam('address'));
		if($user)
		{
			$balance = bitcoinvaluetoa($user->balance);
			$coin = getdbo('db_coins', $user->coinid);

			if($coin)
				echo "$balance $coin->symbol - ".YAAMP_SITE_NAME;
			else
				echo "$balance - ".YAAMP_SITE_NAME;
		}
		else
			echo YAAMP_SITE_URL;
	}

	/////////////////////////////////////////////////

	public function actionAbout()
	{
		$this->render('about');
	}

	public function actionTerms()
	{
		$this->render('terms');
	}

	/////////////////////////////////////////////////

	public function actionAdmin()
	{
		if(!$this->admin) return;
		$this->render('admin');
	}

	public function actionAdmin_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('admin_results');
	}

	/////////////////////////////////////////////////

	public function actionConnections()
	{
		if(!$this->admin) return;
		$this->render('connections');
	}

	public function actionConnections_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('connections_results');
	}

	/////////////////////////////////////////////////

	public function actionBlock()
	{
		$this->render('block');
	}

	public function actionBlock_results()
	{
		$this->renderPartial('block_results');
	}

	/////////////////////////////////////////////////

	public function actionEarning()
	{
		if(!$this->admin) return;
		$this->render('earning');
	}

	public function actionEarning_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('earning_results');
	}

	// called from the wallet
	public function actionClearearnings()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if ($coin) {
			BackendClearEarnings($coin->id);
		}
		$this->goback();
	}

	// called from the earnings page
	public function actionClearearning()
	{
		if(!$this->admin) return;
		$earning = getdbo('db_earnings', getiparam('id'));
		if ($earning && $earning->status == 0) {
			$earning->delete();
		}
		$this->goback();
	}

	/////////////////////////////////////////////////

	public function actionPayments()
	{
		if(!$this->admin) return;
		$this->render('payments');
	}

	public function actionPayments_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('payments_results');
	}

	public function actionCancelUserPayment()
	{
		if(!$this->admin) return;
		$user = getdbo('db_accounts', getiparam('id'));
		if ($user) {
			BackendUserCancelFailedPayment($user->id);
		}
		$this->goback();
	}

	/////////////////////////////////////////////////

	public function actionUser()
	{
		if(!$this->admin) return;
		$this->render('user');
	}

	public function actionUser_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('user_results');
	}

	/////////////////////////////////////////////////

	public function actionWorker()
	{
		if(!$this->admin) return;
		$this->render('worker');
	}

	public function actionWorker_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('worker_results');
	}

	/////////////////////////////////////////////////

	public function actionVersion()
	{
		if(!$this->admin) return;
		$this->render('version');
	}

	public function actionVersion_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('version_results');
	}

	/////////////////////////////////////////////////

	public function actionCommon()
	{
		if(!$this->admin) return;
		$this->render('common');
	}

	public function actionCommon_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('common_results');
	}

	/////////////////////////////////////////////////

	public function actionExchange()
	{
		if(!$this->admin) return;
		$this->render('exchange');
	}

	public function actionExchange_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('exchange_results');
	}

	/////////////////////////////////////////////////

	public function actionCoin()
	{
		if(!$this->admin) return;
		$this->render('coin');
	}

	public function actionCoin_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('coin_results');
	}

	public function actionMemcached()
	{
		if(!$this->admin) return;
		$this->render('memcached');
	}

	public function actionMonsters()
	{
		if(!$this->admin) return;
		$this->render('monsters');
	}

	public function actionEmptyMarkets()
	{
		if(!$this->admin) return;
		$this->render('emptymarkets');
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionTx()
	{
		$this->renderPartial('tx');
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionResetBlockchain()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$coin->action = 3;
		$coin->save();

		$this->redirect("/site/coin?id=$coin->id");
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionRestartCoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 4;
		$coin->enable = false;
		$coin->auto_ready = false;
		$coin->installed = true;
		$coin->connections = 0;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	public function actionStartCoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 1;
		$coin->enable = true;
		$coin->auto_ready = false;
		$coin->installed = true;
		$coin->connections = 0;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	public function actionStopCoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 2;
		$coin->enable = false;
		$coin->auto_ready = false;
		$coin->connections = 0;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	public function actionMakeConfigfile()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 5;
		$coin->installed = true;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionSetauto()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->auto_ready = true;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	public function actionUnsetauto()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->auto_ready = false;
		$coin->save();

		$this->redirect('/site/admin');
	//	$this->goback();
	}

	public function actionSellBalance()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if ($coin) {
			$amount = getparam('amount');
			$res = $this->doSellBalance($coin, $amount);
			if(!$res)
				$this->redirect('/site/admin');
			else
				$this->redirect('/site/exchange');
			return;
		}
		$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////

	public function actionBanUser()
	{
		if(!$this->admin) return;

		$user = getdbo('db_accounts', getiparam('id'));
		if($user) {
			$user->is_locked = true;
			$user->balance = 0;
			$user->save();
		}

		$this->goback();
	}

	public function actionBlockuser()
	{
		if(!$this->admin) return;

		$wallet = getparam('wallet');
		$user = getuserparam($wallet);
		if($user) {
			$user->is_locked = true;
			$user->save();
		}

		$this->goback();
	}

	public function actionUnblockuser()
	{
		if(!$this->admin) return;

		$wallet = getparam('wallet');
		$user = getuserparam($wallet);
		if($user) {
			$user->is_locked = false;
			$user->save();
		}

		$this->goback();
	}

	public function actionLoguser()
	{
		if(!$this->admin) return;

		$user = getdbo('db_accounts', getiparam('id'));
		if($user) {
			$user->logtraffic = getiparam('en');
			$user->save();
		}

		$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////

	// called from the wallet
	public function actionPayuserscoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if($coin) {
			BackendCoinPayments($coin);
		}
		$this->goback();
	}

	// called from the wallet
	public function actionCheckblocks()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if($coin) {
			BackendBlockFind1($coin->id);
			BackendBlocksUpdate($coin->id);
			BackendBlockFind2($coin->id);
		}
		$this->goback();
	}

	////

	// called from the wallet
	public function actionDeleteEarnings()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if($coin) {
			dborun("DELETE FROM earnings WHERE coinid={$coin->id}");
		}
		$this->goback();
	}

	// called from the earnings page
	public function actionDeleteEarning()
	{
		if(!$this->admin) return;
		$earning = getdbo('db_earnings', getiparam('id'));
		if($earning) {
			$earning->delete();
		}
		$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////

	public function actionDeleteExchange()
	{
		if(!$this->admin) return;

		$exchange = getdbo('db_exchange', getiparam('id'));
		if ($exchange) {

			$exchange->status = 'deleted';
			$exchange->price = 0;
			$exchange->receive_time = time();
			$exchange->save();

			/*
			$unspent = $exchange->quantity;
			$earnings = getdbolist('db_earnings', "coinid=$exchange->coinid and not cleared order by create_time");
			foreach($earnings as $earning) {
				$unspent -= $earning->amount;
				$earning->delete();
				if($unspent <= 0) break;
			}
			*/
		}
		$this->goback();
	}

	public function actionClearMarket()
	{
		if(!$this->admin) return;
		$market = getdbo('db_markets', getiparam('id'));
		if($market) {
			$market->lastsent = null;
			$market->save();
		}
		$this->goback();
	}

	// called from the dashboard page
	public function actionClearorder()
	{
		if(!$this->admin) return;
		$order = getdbo('db_orders', getiparam('id'));
		if ($order) {
			$order->delete();
		}
		$this->goback();
	}

    public function actionCancelorder()
	{
		if(!$this->admin) return;
		$order = getdbo('db_orders', getiparam('id'));
		
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

        $this->goback();
	}

	////////////////////////////////////////////////////////////////////////////////////////

	public function actionAlgo()
	{
		$algo = substr(getparam('algo'), 0, 32);
		$a = getdbosql('db_algos', "name=:name", array(':name'=>$algo));

		if($a)
			user()->setState('yaamp-algo', $a->name);
		else
			user()->setState('yaamp-algo', 'all');

		$this->goback();
	}

	public function actionGomining()
	{
		user()->setState('yaamp-algo', getparam('algo'));
		$this->redirect("/site/mining");
	}

	public function actionUpdatePrice()
	{
		if(!$this->admin) return;
		BackendPricesUpdate();
		$this->goback();
	}

	public function actionUninstallCoin()
	{
		if(!$this->admin) return;

		$coin = getdbo('db_coins', getiparam('id'));
		if($coin)
		{
		//	dborun("delete from blocks where coin_id=$coin->id");
			dborun("delete from exchange where coinid=$coin->id");
			dborun("delete from earnings where coinid=$coin->id");
		//	dborun("delete from markets where coinid=$coin->id");
			dborun("delete from orders where coinid=$coin->id");
			dborun("delete from shares where coinid=$coin->id");

			$coin->enable = false;
			$coin->installed = false;
			$coin->auto_ready = false;
			$coin->master_wallet = null;
			$coin->mint = 0;
			$coin->balance = 0;
			$coin->save();
		}

		$this->redirect("/site/admin");
	}

	public function actionOptimize()
	{
		BackendOptimizeTables();
		$this->goback();
	}

	public function actionRunExchange()
	{
		if(!$this->admin) return;

		$id = getiparam('id');
		$balance = getdbo('db_balances', $id);

		if($balance) switch($balance->name)
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

		if ($balance)
			debuglog("runexchange done ($balance->name)");
		else
			debuglog("runexchange failed (no id!)");

		$this->redirect("/site/common");
	}

	public function actionEval()
	{
//		if(!$this->admin) return;

//		$param = getparam('param');
//		if($param) eval($param);
//		else $param = '';

//		$this->render('eval', array('param'=>$param));
	}

	public function actionMainbtc()
	{
		debuglog(__METHOD__);
		setcookie('mainbtc', '1', time()+60*60*24, '/');
	}

	public function actionTest()
	{
		if(!$this->admin) return;

		debuglog("action test");

		$ticker = jubi_api_query('ticker', "?coin=sak");
		debuglog($ticker);

		debuglog("action test end");
	}

}
