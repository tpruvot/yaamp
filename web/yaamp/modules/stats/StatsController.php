<?php
class StatsController extends CommonController
{
    public $defaultAction = 'index';

    /////////////////////////////////////////////////
    public function actionIndex()
    {
        $this->render('index');
    }

    public function actionGraph_results_1()
    {
        $this->renderPartial('graph_results_1');
    }

    public function actionGraph_results_2()
    {
        $this->renderPartial('graph_results_2');
    }

    public function actionGraph_results_3()
    {
        $this->renderPartial('graph_results_3');
    }

    public function actionGraph_results_4()
    {
        $this->renderPartial('graph_results_4');
    }

    public function actionGraph_results_5()
    {
        $this->renderPartial('graph_results_5');
    }

    public function actionGraph_results_6()
    {
        $this->renderPartial('graph_results_6');
    }

    public function actionGraph_results_7()
    {
        $this->renderPartial('graph_results_7');
    }

    public function actionGraph_results_8()
    {
        $this->renderPartial('graph_results_8');
    }

    public function actionGraph_results_9()
    {
        $this->renderPartial('graph_results_9');
    }

}
