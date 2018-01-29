<?php

class db_payouts extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'payouts';
	}

	public function rules()
	{
		return array(
		);
	}

	public function relations()
	{
		return array(
			'account' => array(self::BELONGS_TO, 'db_accounts', 'account_id', 'alias'=>'account'),
		);
	}

	public function attributeLabels()
	{
		return array(
		);
	}
}

