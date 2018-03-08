<?php
/**
 * Uni-Form widget to add needed css and javascript files on page
 *
 * @author Alexander Hramov
 * @link http://www.hramov.info
 * @version 0.1
 */
//Yii::import('zii.widgets.jui.CJuiWidget');

class UniForm extends CWidget /* or CJuiWidget */
{
	public function init()
	{
		parent::init();

		echo CHtml::cssFile('/yaamp/ui/css/uni-form.css');
	}

	public function run()
	{
		$cs = Yii::app()->getClientScript();
		$cs->registerCoreScript("jquery");
		$cs->registerCoreScript("jquery.ui");
		$cs->registerScriptFile('/yaamp/ui/js/uni-form.jquery.js', CClientScript::POS_END);

		CHtml::$requiredCss = '';
		CHtml::$afterRequiredLabel='';
		CHtml::$beforeRequiredLabel='<em>*</em> ';
		CHtml::$errorSummaryCss = 'errorMsg';
	}
}

