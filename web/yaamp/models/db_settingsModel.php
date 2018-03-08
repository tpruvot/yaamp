<?php

class db_settings extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'settings';
	}

	public function rules()
	{
		return array(
			array('param', 'required'),
		);
	}

	public function relations()
	{
		return array(
		);
	}

	public function attributeLabels()
	{
		return array(
		);
	}

	public function save($runValidation=true,$attributes=null)
	{
		if (empty($this->type)) $this->type = null;

		return parent::save($runValidation, $attributes);
	}
}
