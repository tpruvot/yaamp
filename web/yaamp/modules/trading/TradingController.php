<?php
class TradingController extends CommonController
{
    public $defaultAction = 'index';

    /////////////////////////////////////////////////
    public function actionIndex()
    {
        if (!$this->admin) return;
        $this->render('index');
    }

    public function actionMining_results()
    {
        if (!$this->admin) return;
        $this->renderPartial('mining_results');
    }

    // 	public function actionCreate()
    // 	{
    // 		if(!$this->admin) return;
    // 		$coin = new db_coins;
    // 		if(isset($_POST['db_coins']))
    // 		{
    // 			$coin->setAttributes($_POST['db_coins'], false);
    // 			if($coin->save())
    // 				$this->redirect(array('index'));
    // 		}
    // 		$this->render('_form', array('coin'=>$coin, 'update'=>false));
    // 	}
    // 	public function actionUpdate()
    // 	{
    // 		if(!$this->admin) return;
    // 		$coin = getdbo('db_coins', getiparam('id'));
    // 		if(isset($_POST['db_coins']))
    // 		{
    // 			$coin->setAttributes($_POST['db_coins'], false);
    // 			if($coin->save())
    // 				$this->redirect(array('index'));
    // 		}
    // 		$this->render('_form', array('coin'=>$coin, 'update'=>true));
    // 	}

}
