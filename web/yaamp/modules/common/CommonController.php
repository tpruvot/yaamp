<?php

class CommonController extends CController
{
	public $memcache;
	public $t1;

	// read-only via getAdmin()
	private $admin = false;
	protected function getAdmin() { return $this->admin; }

	protected function elapsedTime()
	{
		$t2 = microtime(true);
		return ($t2 - $this->t1);
	}

	protected function beforeAction($action)
	{
	//	debuglog("before action ".$action->getId());

		$this->memcache = new YaampMemcache;
		$this->t1 = microtime(true);

		if(user()->getState('yaamp_admin')) {
			$this->admin = true;
			$client_ip = arraySafeVal($_SERVER,'REMOTE_ADDR');
			if (!isAdminIP($client_ip)) {
				user()->setState('yaamp_admin', false);
				debuglog("admin attempt from $client_ip");
				$this->admin = false;
			}
		}

		$algo = user()->getState('yaamp-algo');
		if(!$algo) user()->setState('yaamp-algo', YAAMP_DEFAULT_ALGO);

		return true;
	}

	protected function afterAction($action)
	{
	//	debuglog("after action ".$action->getId());

		$d1 = $this->elapsedTime();

		$url = "$this->id/{$this->action->id}";
		$this->memcache->add_monitoring_function($url, $d1);
	}

	public function actionMaintenance()
	{
		$this->render('maintenance');
	}

	public function goback($count=-1)
	{
		Javascript("window.history.go($count);");
		die;
	}

}

