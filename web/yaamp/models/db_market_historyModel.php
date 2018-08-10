<?php
/**
 * This table tracks currency price and your balance on a market
 */
class db_market_history extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'market_history';
	}

	public function rules()
	{
		return array(
			array('idcoin', 'required'),
			array('idcoin, idmarket', 'numerical', 'integerOnly'=>true),
		);
	}

	public function relations()
	{
		return array(
			'coin' => array(self::BELONGS_TO, 'db_coins', 'idcoin', 'alias'=>'mh_coin'),
			'market' => array(self::BELONGS_TO, 'db_markets', 'idmarket', 'alias'=>'mh_market'),
		);
	}

	public function attributeLabels()
	{
		return array(
		);
	}

	public function save($runValidation=true,$attributes=null)
	{
		if (empty($this->idmarket)) $this->idmarket = null;

		return parent::save($runValidation, $attributes);
	}
}

