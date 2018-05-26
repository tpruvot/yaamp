<?php

class db_notifications extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'notifications';
	}

	public function rules()
	{
		return array(
			array('idcoin', 'required'),
			array('enabled', 'numerical', 'integerOnly'=>true),
		);
	}

	public function relations()
	{
		return array(
			'coin' => array(self::BELONGS_TO, 'db_coins', 'idcoin', 'alias'=>'nc'),
		);
	}

	public function attributeLabels()
	{
		return array(
		);
	}
}

