<?php

class db_markets extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'markets';
	}

	public function rules()
	{
		return array(
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
		if (empty($this->base_coin)) $this->base_coin = null;
		else if ($this->base_coin == 'BTC') $this->base_coin = null;
		else if (strpos($this->name, $this->base_coin) === false) {
			$parts = explode(' ', $this->name);
			$this->name = trim($parts[0].' '.$this->base_coin);
		}
		if (empty($this->message)) $this->message = null;

		return parent::save($runValidation, $attributes);
	}
}

