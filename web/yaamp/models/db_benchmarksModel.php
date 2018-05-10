<?php

class db_benchmarks extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'benchmarks';
	}

	public function rules()
	{
		return array(
			array('algo, vendorid', 'required'), // allow the search also
		);
	}

	public function relations()
	{
		return array(
			'bench_chip' => array(self::BELONGS_TO, 'db_bench_chips', 'idchip', 'alias'=>'BC'),
		);
	}

	public function attributeLabels()
	{
		return array(
		);
	}

	public function search()
	{
		$criteria = new CDbCriteria;

		$t = $this->getTableAlias(false);

		$criteria->compare("$t.algo",$this->algo);
		$criteria->compare("$t.idchip",$this->idchip);
		$criteria->compare("$t.vendorid",$this->vendorid);

		$sort = array('defaultOrder'=>"$t.time DESC");

		$criteria->limit = 150;
		if (empty($this->algo) || $this->algo == 'all') {
			$criteria->limit = 50;
		}

		$dataProvider = new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
			'pagination'=>array('pageSize'=>50),
			'sort'=>$sort,
		));

		return $dataProvider;
	}

}

