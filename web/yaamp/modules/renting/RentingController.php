<?php

class RentingController extends CommonController
{
	public $defaultAction='index';

	public function actions()
	{
		return array(
			'captcha'=>array(
				'class'=>'CCaptchaAction',
				'backColor'=>0xeeeeee,
			),
		);
	}

	private function verifyparam()
	{
		$deposit = user()->getState('yaamp-deposit');
		$address = getparam('address');

		if(!$this->admin && $deposit != $address)
			return false;

		return true;
	}

	public function actionLogin()
	{
		$deposit = isset($_POST['deposit_address'])? substr($_POST['deposit_address'], 0, 35): '';
		$password = isset($_POST['deposit_password'])? substr($_POST['deposit_password'], 0, 64): '';

		$renter = getdbosql('db_renters', "address=:address", array(':address'=>$deposit));
		if(!$renter)
		{
			$this->render('login');
			return;
		}

		if(md5($password) != $renter->password && (!empty($renter->password) || !empty($password)))
		{
			user()->setFlash('error', "Login failed.");
			$this->render('login');
			return;
		}

		user()->setState('yaamp-deposit', $renter->address);
		$this->redirect("/renting");
	}

	public function actionIndex()
	{
		$deposit = user()->getState('yaamp-deposit');
		if(!$deposit && !$this->admin)
		{
			$this->render('login');
			return;
		}

		$address = getparam('address');
		if($this->admin && !empty($address))
		{
			$deposit = $address;
			user()->setState('yaamp-deposit', $deposit);
		}

		$renter = getdbosql('db_renters', "address=:deposit", array(':deposit'=>$deposit));
		if(!$renter)
		{
			$this->render('login');
			return;
		}

		$changed = false;
		if(isset($_POST['deposit_email']))
		{
			$renter->email = $_POST['deposit_email'];
			$changed = true;
		}

		if(isset($_POST['deposit_password']) && !empty($_POST['deposit_password']))
		{
			if($_POST['deposit_password'] == $_POST['deposit_confirm'])
			{
				$renter->password = md5($_POST['deposit_password']);
				$changed = true;
			}
			else
			{
				user()->setFlash('error', "Confirm different from password.");
				$this->goback();

				return;
			}
		}

		if($changed)
		{
			debuglog("saving renter {$_SERVER['REMOTE_ADDR']} $renter->address");

			dborun("update renters set email=:email, password=:password where id=$renter->id",
				array(':email'=>$renter->email, ':password'=>$renter->password));

//			$renter->save();

			user()->setFlash('message', "Settings saved.");
			$this->redirect("/renting");
		}

		$this->render('index', array('renter'=>$renter));
	}

	public function actionSettings()
	{
		$this->render('settings');
	}

	public function actionAdmin()
	{
		if(!$this->admin) return;
		$this->render('admin');
	}

	public function actionCreate()
	{
		$this->render('create');
	}

	public function actionLogout()
	{
		user()->setState('yaamp-deposit', '');
		$this->redirect('/renting');
	}

	public function actionTx()
	{
		$this->renderPartial('tx');
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////

	public function actionJobs_stop()
	{
		$job = getdbo('db_jobs', getiparam('id'));

		$renter = getdbo('db_renters', $job->renterid);
		if(!$renter || $renter->address != user()->getState('yaamp-deposit')) $this->goback();

		$job->active = false;
		$job->ready = false;
		$job->time = time();
//		$job->difficulty = null;
		$job->save();

		$this->goback();
	}

	public function actionJobs_start()
	{
		$job = getdbo('db_jobs', getiparam('id'));
//		if($job->algo == 'sha256') $this->goback();

		$renter = getdbo('db_renters', $job->renterid);
		if(!$renter || $renter->balance<=0.00001000 || $renter->address != user()->getState('yaamp-deposit')) $this->goback();

		$rent = dboscalar("select rent from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$job->algo));
		if($job->price > $rent) $job->active = true;

		$job->ready = true;
		$job->time = time();
//		$job->difficulty = null;
		$job->save();

		$this->goback();
	}

	public function actionJobs_startall()
	{
		$deposit = user()->getState('yaamp-deposit');
		$renter = getrenterparam($deposit);
		if(!$renter || $renter->balance<=0.00001000) $this->goback();

		$list = getdbolist('db_jobs', "renterid=$renter->id");
		foreach($list as $job)
		{
			$rent = dboscalar("select rent from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$job->algo));
			if($job->price > $rent) $job->active = true;

			$job->ready = true;
			$job->time = time();
			$job->save();
		}

		$this->goback();
	}

	public function actionJobs_stopall()
	{
		$deposit = user()->getState('yaamp-deposit');
		$renter = getrenterparam($deposit);
		if(!$renter) $this->goback();

		$list = getdbolist('db_jobs', "renterid=$renter->id");
		foreach($list as $job)
		{
			$job->active = false;
			$job->ready = false;
			$job->time = time();
			$job->save();
		}

		$this->goback();
	}

	/////////////////////////////////////////////////////////////////////////////////////////////

	public function actionBalance_results()
	{
		if(!$this->verifyparam()) return;
		$this->renderPartial('balance_results');
	}

	public function actionOrders_results()
	{
		if(!$this->verifyparam()) return;
		$this->renderPartial('orders_results');
	}

	public function actionAll_orders_results()
	{
		$this->renderPartial('all_orders_results');
	}

	public function actionGraph_job_results()
	{
		$this->renderPartial('graph_job_results');
	}

	public function actionStatus_results()
	{
		$this->renderPartial('status_results');
	}

	public function actionGraph_price_results()
	{
		$this->renderPartial('graph_price_results');
	}

	/////////////////////////////////////////////////////////////////////////////////////////////

	public function actionOrderDelete()
	{
		$job = getdbo('db_jobs', getiparam('id'));
		if(!$job) return;

		$renter = getdbo('db_renters', $job->renterid);
		if(!$renter || $renter->address != user()->getState('yaamp-deposit')) return;

		$job->delete();
		$this->redirect("/renting?address=$renter->address");
	}

	public function actionOrderSave()
	{
		$renter = getdbo('db_renters', XssFilter(''.getparam('order_renterid')));
		if(!$renter || $renter->address != user()->getState('yaamp-deposit')) return;

		$job = getdbo('db_jobs', XssFilter(''.getparam('order_id')));
		if(!$job)
		{
			$job = new db_jobs;
			$job->renterid = getparam('order_renterid');
		}

		$job->algo = getparam('order_algo');
		$job->username = getparam('order_username');
		$job->password = getparam('order_password');
		$job->percent = getparam('order_percent');
		$job->price = getparam('order_price');
		$job->speed = getparam('order_speed')*1000000;

		if(	empty($job->algo) || empty($job->username) || empty($job->password) || empty($job->price) ||
			empty($job->speed) || empty(''.getparam('order_address')) || empty(''.getparam('order_host')))
		{
			$this->redirect('/renting');
			return;
		}

		if($job->speed<100000)
		{
			$this->redirect('/renting');
			return;
		}

		$host = str_replace('stratum+tcp://', '', getparam('order_host'));
		$a = explode(':', $host);
		if(!isset($a[0]) || !isset($a[1]))
		{
			user()->setFlash('error', "invalid server url");
			$this->redirect('/renting');
			return;
		}

		$job->host = trim($a[0]);
		$job->port = intval($a[1]);

		if(stripos($job->host, YAAMP_STRATUM_URL) !== false) {
			user()->setFlash('error', "invalid server url");
			$this->redirect('/renting');
			return;
		}

		$rent = dboscalar("select rent from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$job->algo));

		if($job->price > $rent && $job->ready)
			 $job->active = true;

		else if($job->price < $rent)
			$job->active = false;

		$job->time = time();
//		$job->difficulty = null;
		$job->save();

		$this->redirect("/renting?address=".getparam('order_address'));
	}

	/////////////////////////////////////////////////////////////////////////////////////////////

	public function actionOrderDialog()
	{
		$renter = getrenterparam(''.getparam('address'));
		if(!$renter) return;

		$a = YAAMP_DEFAULT_ALGO;
		$server = '';
		$username = '';
		$password = 'xx';
		$percent = '';
		$price = '';
		$speed = '';
		$id = 0;

		$job = getdbo('db_jobs', getiparam('id'));
		if($job)
		{
			$id = $job->id;
			$a = $job->algo;
			$server = "$job->host:$job->port";
			$username = $job->username;
			$password = $job->password;
			$percent = $job->percent;
			$price = mbitcoinvaluetoa($job->price);
			$speed = $job->speed/1000000;
		}

		echo <<<end
<form id='order-edit-form' action='/renting/ordersave' method='post'>
<input type="hidden" value='$id' name="order_id">
<input type="hidden" value='$renter->id' name="order_renterid">
<input type="hidden" value='$renter->address' name="order_address">

<p>Enter your job information below and click Submit when you are ready.</p>

<table cellspacing=10 width=100%>
<tr><td>Algo:</td><td><select class="main-text-input" name="order_algo">
end;

		foreach(yaamp_get_algos() as $algo)
		{
			if(!controller()->admin && $algo == 'sha256') continue;
			if(!controller()->admin && $algo == 'scryptn') continue;

			$selected = $algo==$a? 'selected': '';
			echo "<option $selected value='$algo'>$algo</option>";
		}

		echo <<<end
</select></td></tr>
<tr><td>Server:</td><td><input type="text" value='$server' name="order_host" class="main-text-input" placeholder="stratum.server.com:3333"></td></tr>
<tr><td>Username:</td><td><input type="text" value='$username' name="order_username" class="main-text-input" placeholder="wallet_address"></td></tr>
<tr><td>Password:</td><td><input type="text" value='$password' name="order_password" class="main-text-input"></td></tr>
<tr><td>Max Price<br><span style='font-size: .8em;'>(mBTC/mh/day)</span>:</td><td><input type="text" value='$price' name="order_price" class="main-text-input" placeholder=""></td></tr>
<tr><td width=110>Max Hashrate<br><span style='font-size: .8em;'>(Mh/s)</span>:</td><td><input type="text" value='$speed' name="order_speed" class="main-text-input" placeholder=""></td></tr>
end;

		if(controller()->admin)
			echo "<tr><td>Percent:</td><td><input type=text value='$percent' name=order_percent class=main-text-input></td></tr>";

		echo "</table></form>";
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////

	public function actionResetSpent()
	{
		$renter = getrenterparam(''.getparam('address'));
		if(!$renter) return;

		$renter->custom_start = 0;
		$renter->spent = $renter->custom_balance;

		$renter->save();
		$this->goback();
	}

	public function actionWithdraw()
	{
		$fees = YAAMP_TXFEE_RENTING_WD; // 0.002

		$deposit = user()->getState('yaamp-deposit');
		if(!$deposit)
		{
			$this->render('login');
			return;
		}

		$renter = getrenterparam($deposit);
		if(!$renter)
		{
			$this->render('login');
			return;
		}

		$amount = getparam('withdraw_amount');
		$address = getparam('withdraw_address');

		$amount = floatval(bitcoinvaluetoa(min($amount, $renter->balance-$fees)));
		if($amount < YAAMP_PAYMENTS_MINI) // 0.001
		{
			user()->setFlash('error', 'Minimum withdraw is '.YAAMP_PAYMENTS_MINI);
			$this->redirect("/renting");
			return;
		}

		$coin = getdbosql('db_coins', "symbol='BTC'");
		if(!$coin) return;

		$remote = new WalletRPC($coin);

		$res = $remote->validateaddress($address);
		if(!$res || !isset($res['isvalid']) || !$res['isvalid'])
		{
			user()->setFlash('error', 'Invalid address');
			$this->redirect("/renting");

			return;
		}

		$rentertx = new db_rentertxs;
		$rentertx->renterid = $renter->id;
		$rentertx->time = time();
		$rentertx->amount = $amount;
		$rentertx->type = 'withdraw';
		$rentertx->address = $address;
		$rentertx->tx = 'scheduled';
		$rentertx->save();

		debuglog("withdraw scheduled $renter->id $renter->address, $amount to $address");

		user()->setFlash('message', "withdraw scheduled");
		$this->redirect("/renting");
	}

}








