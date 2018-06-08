<?php

class db_blocks extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'blocks';
	}

	public function rules()
	{
		return array(
		);
	}

	public function relations()
	{
		return array(
			'coin' => array(self::BELONGS_TO, 'db_coins', 'coin_id', 'alias'=>'coin'),
		);
	}

	public function attributeLabels()
	{
		return array(
		);
	}
}

