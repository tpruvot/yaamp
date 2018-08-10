<?php

///////////////////////////////////////////////////////////////////////

function getdbo($class, $id)
{
	$record = CActiveRecord::model($class);
	$table = $record->getTableSchema();

	$sql = "$table->primaryKey=:db_key";
	return $record->find($sql, array(':db_key'=>$id));

//	return $record->findByPk($id);
}

function getdbosql($class, $sql='1', $params=array())
{
//	debuglog("$class, $sql");
	return CActiveRecord::model($class)->find($sql, $params);
}

function getdbolist($class, $sql='1', $params=array())
{
//	debuglog("sql $sql");
	return CActiveRecord::model($class)->findAll($sql, $params);
}

function getdbocount($class, $sql='1', $params=array())
{
//	debuglog("sql $sql");
	return CActiveRecord::model($class)->count($sql, $params);
}

function dborun($sql, $params=array())
{
	$command = app()->db->createCommand($sql);

	foreach($params as $name=>$value)
		$command->bindValue($name, $value);

	return $command->execute();
}

function dboscalar($sql, $params=array())
{
	$command = app()->db->createCommand($sql);

	foreach($params as $name=>$value)
		$command->bindValue($name, $value);

	return $command->queryScalar();
}

function dborow($sql, $params=array())
{
	$command = app()->db->createCommand($sql);

	foreach($params as $name=>$value)
		$command->bindValue($name, $value);

	return $command->queryRow();
}

function dbocolumn($sql, $params=array())
{
	$command = app()->db->createCommand($sql);

	foreach($params as $name=>$value)
		$command->bindValue($name, $value);

	return $command->queryColumn();
}

function dbolist($sql, $params=array())
{
	$command = app()->db->createCommand($sql);

	foreach($params as $name=>$value)
		$command->bindValue($name, $value);

	return $command->queryAll();
}

function getdbolistWith($model, $with, $criteria)
{
	return CActiveRecord::model($model)->with($with)->findAll($criteria);
}

function sqlQuote($value, $querytype='')
{
	$db = app()->db;
	$quoted = $value;

	if ($querytype == '')
		$quoted = $db->quoteValue($value);
	elseif ($querytype == 'beginWith')
		$quoted = $db->quoteValue($value.'%');
	elseif ($querytype == 'endWith')
		$quoted = $db->quoteValue('%'.$value);
	elseif ($querytype == 'contains')
		$quoted = $db->quoteValue('%'.$value.'%');

	return $quoted;
}
