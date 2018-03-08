<?php

class db_coins extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'coins';
	}

	public function rules()
	{
		return array(
			array('name', 'required'),
			array('symbol', 'required'),
			array('symbol', 'unique'),
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
			'symbol2'	=> 'Official Symbol',
			'auxpow'	=> 'AUX PoW',
			'dontsell'	=> 'Don\'t sell',
			'sellonbid'	=> 'Sell on Bid',
			'txfee'		=> 'Tx Fee',
			'program'	=> 'Process name',
			'conf_folder'	=> 'Conf. folder',
			'mature_blocks' => 'PoW Confirmations',
			'powend_height' => 'End of PoW',
			'rpchost'	=> 'RPC Host',
			'rpcport'	=> 'RPC Port',
			'rpcuser'	=> 'RPC User',
			'rpcpasswd'	=> 'RPC Password',
			'rpccurl'	=> 'RPC via curl',
			'rpcssl'	=> 'RPC SSL',
			'rpccert'	=> 'RPC Certificate',
			'serveruser'	=> 'Server user',
			'hasgetinfo'	=> 'Has getinfo',
			'hassubmitblock'=> 'Has submitblock',
			'hasmasternodes'=> 'Masternodes',
			'usesegwit'	=> 'Use segwit',
			'market'	=> 'Preferred market',
			'rpcencoding'	=> 'RPC Type',
			'specifications'=> 'Notes'
		);
	}

	public function getOfficialSymbol()
	{
		if(!empty($this->symbol2))
			return $this->symbol2;
		else
			return $this->symbol;
	}

	public function getSymbol_show()
	{
		// virtual property $coin->symbol_show
		return $this->getOfficialSymbol();
	}

	public function deleteDeps()
	{
		$coin = $this;
		$ids_query = "(SELECT id FROM accounts WHERE coinid=".$coin->id.")";

		dborun("DELETE FROM balanceuser WHERE userid IN $ids_query");
		dborun("DELETE FROM hashuser WHERE userid IN $ids_query");
		dborun("DELETE FROM shares WHERE userid IN $ids_query");
		dborun("DELETE FROM workers WHERE userid IN $ids_query");
		dborun("DELETE FROM payouts WHERE account_id IN $ids_query");

		dborun("DELETE FROM blocks WHERE coin_id=".$coin->id);
		dborun("DELETE FROM shares WHERE coinid=".$coin->id);
		dborun("DELETE FROM earnings WHERE coinid=".$coin->id);
		dborun("DELETE FROM notifications WHERE idcoin=".$coin->id);
		dborun("DELETE FROM market_history WHERE idcoin=".$coin->id);
		dborun("DELETE FROM markets WHERE coinid=".$coin->id);

		dborun("DELETE FROM accounts WHERE coinid=".$coin->id);
	}

	public function deleteWithDeps()
	{
		$this->deleteDeps();
		return $this->delete();
	}

	/**
	 * Link for txs
	 * @param string $label link content
	 * @param array $params 'height'=>123 or 'hash'=>'xxx' or 'txid'=>'xxx'
	 * @param array $htmlOptions target/title ...
	 */
	public function createExplorerLink($label, $params=array(), $htmlOptions=array(), $force=false)
	{
		if($this->id == 6 && isset($params['txid'])) {
			// BTC txid
			$url = 'https://blockchain.info/tx/'.$params['txid'];
			$htmlOpts = array_merge(array('target'=>'_blank'), $htmlOptions);
			return CHtml::link($label, $url, $htmlOpts);
		}
		else if (YIIMP_PUBLIC_EXPLORER || $force || user()->getState('yaamp_admin')) {
			$urlParams = array_merge(array('id'=>$this->id), $params);
			Yii::import('application.modules.explorer.ExplorerController');
			$url = ExplorerController::createUrl('/explorer', $urlParams);
			return CHtml::link($label, trim($url,'?'), $htmlOptions);
		}
		return $label;
	}

}

