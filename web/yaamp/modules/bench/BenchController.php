<?php

class BenchController extends CommonController
{
	public $defaultAction='index';

	/////////////////////////////////////////////////

	public function actionIndex()
	{
		$algo = substr(getparam('algo'), 0, 32);
		if ($algo) {
			$a = dboscalar('SELECT count(id) FROM benchmarks WHERE algo LIKE :algo', array(':algo'=>$algo));
			user()->setState('bench-algo', $a ? $algo : 'all');
		} else {
			$algo = user()->getState('bench-algo');
		}
		$vid = getparam('vid');
		if ($vid) {
			$a = dboscalar('SELECT count(id) FROM benchmarks WHERE vendorid LIKE :vendorid', array(':vendorid'=>$vid));
			$vid = $a ? $vid : '';
		}
		$idchip = getiparam('chip');
		$this->render('index', array('algo'=>$algo,'idchip'=>$idchip,'vid'=>$vid));
	}

	public function actionDel()
	{
		$id = getiparam('id');
		if ($id > 0 && $this->admin) {
			dborun("DELETE FROM benchmarks WHERE id=$id");
		}
		$this->goback();
	}

	/////////////////////////////////////////////////

	public function actionDevices()
	{
		$this->render('devices');
	}

	/////////////////////////////////////////////////

	public function actionAlgo()
	{
		$algo = substr(getparam('algo'), 0, 32);
		if (!empty($algo))
			$this->render('algo', array('algo'=>$algo));
		else
			$this->goback();
	}

}
