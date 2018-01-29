<?php
/**
 * CUFHtml is helper class for easily integrate Uni-Form into ready forms
 *
 * @author Alexander Hramov
 * @link http://www.hramov.info
 * @version 0.1
 */
class CUFHtml extends CHtml
{
   public static function beginForm($action='', $method='post', $htmlOptions = array())
   {
     if (isset($htmlOptions['class']))
       $htmlOptions['class'] = $htmlOptions['class'].' uniForm';
     else
       $htmlOptions['class'] = 'uniForm';
     return parent::beginForm($action, $method, $htmlOptions);
   }

  public static function errorSummary($model,$header=null,$footer=null,$opt=array())
  {
    $content='';
    if(!is_array($model))
      $model=array($model);
    foreach($model as $m)
    {
      foreach($m->getErrors() as $attribute=>$errors)
      {
        foreach($errors as $error)
        {
          if($error!='')
            $content.="<li><a href=\"#error_$attribute\">$error</a></li>\n";
        }
      }
    }
    if($content!=='')
    {
      $header='<h3>'.Yii::t('yii','Please fix the following input errors:').'</h3>';
      return self::tag('div',array('id'=>self::$errorSummaryCss),$header."\n<ol>\n$content</ol>".$footer);
    }
    else
      return '';
  }


  public static function activeLabel($model,$attribute,$htmlOptions=array())
  {
    $for=self::getIdByName(self::resolveName($model,$attribute));
    if(isset($htmlOptions['label']))
    {
      $label=$htmlOptions['label'];
      unset($htmlOptions['label']);
    }
    else
      $label=$model->getAttributeLabel($attribute);
    return self::label($label,$for,$htmlOptions);
  }

  public static function activeLabelEx($model,$attribute,$htmlOptions=array())
  {
    $realAttribute=$attribute;
    self::resolveName($model,$attribute); // strip off square brackets if any
    $htmlOptions['required']=$model->isAttributeRequired($attribute);
    return self::activeLabel($model,$realAttribute,$htmlOptions);
  }

  public static function submitButton($label='submit',$htmlOptions=array())
  {
    $htmlOptions['type']='submit';
    $htmlOptions = self::addUniFormClass($htmlOptions, 'submitButton');
    return self::button($label,$htmlOptions);
  }

  public static function resetButton($label='reset',$htmlOptions=array())
  {
    $htmlOptions['type']='reset';
    $htmlOptions = self::addUniFormClass($htmlOptions, 'resetButton');
    // Workaround for IE alignment
    return self::tag('div', array('class'=>'resetButton', self::button($label, $htmlOptions)));
  }

  public static function activeTextField($model,$attribute,$htmlOptions=array())
  {
    self::resolveNameID($model,$attribute,$htmlOptions);
    self::clientChange('change',$htmlOptions);
    $htmlOptions = self::addUniFormClass($htmlOptions, 'textInput');
	$htmlOptions = self::addUniFormClass($htmlOptions, 'tweetnews-input');
    return self::activeInputField('text',$model,$attribute,$htmlOptions);
  }

  protected static function activeInputField($type,$model,$attribute,$htmlOptions)
  {
    $htmlOptions['type']=$type;
    if($type==='file')
      unset($htmlOptions['value']);
    else if(!isset($htmlOptions['value']))
      $htmlOptions['value']=$model->$attribute;
    return self::tag('input',$htmlOptions);
  }

  public static function openActiveCtrlHolder($model,$attribute,$htmlOptions=array())
  {
	$opentag='';
    if($model->hasErrors($attribute))
    {
      self::addErrorCss($htmlOptions);
      $opentag = '<p id="error_'.$attribute.'" class="errorField"><strong>'.$model->getError($attribute)."</strong></p>\n";
    }
    $opentag = self::openCtrlHolder($htmlOptions)."\n".$opentag;
    return $opentag;
  }

  public static function openCtrlHolder($htmlOptions=array())
  {
    if (isset($htmlOptions['class']))
      $htmlOptions['class'] = $htmlOptions['class'].' ctrlHolder';
    else
      $htmlOptions['class'] = 'ctrlHolder';
    return self::openTag('div', $htmlOptions);
  }

  public static function closeCtrlHolder()
  {
    return "<div style='clear:both'></div>".self::closeTag('div')."\n";
  }

  protected static function addUniFormClass($htmlOptions, $class)
  {
    if (isset($htmlOptions['class']))
      $htmlOptions['class'] = $htmlOptions['class'].' '.$class;
    else
      $htmlOptions['class'] = $class;
    return $htmlOptions;
  }
}

