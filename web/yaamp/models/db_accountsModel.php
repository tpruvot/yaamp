<?php

class db_accounts extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'accounts';
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

	public function deleteWithDeps()
	{
		$user = $this;
		dborun("DELETE FROM balanceuser WHERE userid=".$user->id);
		dborun("DELETE FROM hashuser WHERE userid=".$user->id);
		dborun("DELETE FROM shares WHERE userid=".$user->id);
		dborun("DELETE FROM workers WHERE userid=".$user->id);
		dborun("DELETE FROM earnings WHERE userid=".$user->id);
		dborun("UPDATE blocks SET userid=NULL WHERE userid=".$user->id);
		dborun("DELETE FROM payouts WHERE account_id=".$user->id);
		return $user->delete();
	}
}

