<?php

echo getAdminSideBarLinks();

echo " - <a href='/site/coin?id={$coin->id}'>{$coin->name}</a><br/>";

$this->widget('UniForm');

echo CUFHtml::beginForm();
echo CUFHtml::errorSummary($coin);
echo CUFHtml::openTag('fieldset', array('class'=>'inlineLabels'));

InitMenuTabs('#tabs');

echo <<<end
<style type="text/css">
[readonly~=readonly] {
	color: gray;
}
</style>
<div id="tabs"><ul>
<li><a href="#tabs-1">General</a></li>
<li><a href="#tabs-2">Settings</a></li>
<li><a href="#tabs-3">Exchange</a></li>
<li><a href="#tabs-4">Daemon</a></li>
<li><a href="#tabs-5">Links</a></li>
</ul><br>
end;

echo '<div id="tabs-1">';

echo CUFHtml::openActiveCtrlHolder($coin, 'name');
echo CUFHtml::activeLabelEx($coin, 'name');
echo CUFHtml::activeTextField($coin, 'name', array('maxlength'=>200));
echo '<p class="formHint2">Required</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'symbol');
echo CUFHtml::activeLabelEx($coin, 'symbol');
echo CUFHtml::activeTextField($coin, 'symbol', array('maxlength'=>200,'style'=>'width: 120px;'));
echo '<p class="formHint2">Required all upper case</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'symbol2');
echo CUFHtml::activeLabelEx($coin, 'symbol2');
echo CUFHtml::activeTextField($coin, 'symbol2', array('maxlength'=>200,'style'=>'width: 120px;'));
echo '<p class="formHint2">Set it if symbol is different</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'algo');
echo CUFHtml::activeLabelEx($coin, 'algo');
echo CUFHtml::activeTextField($coin, 'algo', array('maxlength'=>64,'style'=>'width: 120px;'));
echo '<p class="formHint2">Required all lower case</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'image');
echo CUFHtml::activeLabelEx($coin, 'image');
echo CUFHtml::activeTextField($coin, 'image', array('maxlength'=>200));
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'payout_min');
echo CUFHtml::activeLabelEx($coin, 'payout_min');
echo CUFHtml::activeTextField($coin, 'payout_min', array('maxlength'=>200,'style'=>'width: 120px;'));
echo '<p class="formHint2">Pay users when they reach this amount</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'payout_max');
echo CUFHtml::activeLabelEx($coin, 'payout_max');
echo CUFHtml::activeTextField($coin, 'payout_max', array('maxlength'=>200,'style'=>'width: 120px;'));
echo '<p class="formHint2">Maximum transaction amount</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'txfee');
echo CUFHtml::activeLabelEx($coin, 'txfee');
echo CUFHtml::activeTextField($coin, 'txfee', array('maxlength'=>200,'style'=>'width: 100px;','readonly'=>'readonly'));
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'block_height');
echo CUFHtml::activeLabelEx($coin, 'block_height');
echo CUFHtml::activeTextField($coin, 'block_height', array('readonly'=>'readonly','style'=>'width: 120px;'));
echo '<p class="formHint2">Current height</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'target_height');
echo CUFHtml::activeLabelEx($coin, 'target_height');
echo CUFHtml::activeTextField($coin, 'target_height', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Known height of the network</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'powend_height');
echo CUFHtml::activeLabelEx($coin, 'powend_height');
echo CUFHtml::activeTextField($coin, 'powend_height', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Height of the end of PoW mining</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'mature_blocks');
echo CUFHtml::activeLabelEx($coin, 'mature_blocks');
echo CUFHtml::activeTextField($coin, 'mature_blocks', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Required block count to mature</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'block_time');
echo CUFHtml::activeLabelEx($coin, 'block_time');
echo CUFHtml::activeTextField($coin, 'block_time', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Average block time (sec)</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'errors');
echo CUFHtml::activeLabelEx($coin, 'errors');
echo CUFHtml::activeTextField($coin, 'errors', array('maxlength'=>200,'readonly'=>'readonly','style'=>'width: 600px;'));
echo CUFHtml::closeCtrlHolder();

echo "</div>";

//////////////////////////////////////////////////////////////////////////////////////////

echo '<div id="tabs-2">';

echo CUFHtml::openActiveCtrlHolder($coin, 'enable');
echo CUFHtml::activeLabelEx($coin, 'enable');
echo CUFHtml::activeCheckBox($coin, 'enable');
echo '<p class="formHint2">Required</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'auto_ready');
echo CUFHtml::activeLabelEx($coin, 'auto_ready');
echo CUFHtml::activeCheckBox($coin, 'auto_ready');
echo '<p class="formHint2">Allowed to mine</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'visible');
echo CUFHtml::activeLabelEx($coin, 'visible');
echo CUFHtml::activeCheckBox($coin, 'visible');
echo '<p class="formHint2">Visibility for the public</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'installed');
echo CUFHtml::activeLabelEx($coin, 'installed');
echo CUFHtml::activeCheckBox($coin, 'installed');
echo '<p class="formHint2">Required to be visible in the Wallets board</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'no_explorer');
echo CUFHtml::activeLabelEx($coin, 'no_explorer');
echo CUFHtml::activeCheckBox($coin, 'no_explorer');
echo '<p class="formHint2">Disable block explorer for the public</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'watch');
echo CUFHtml::activeLabelEx($coin, 'watch');
echo CUFHtml::activeCheckBox($coin, 'watch');
echo '<p class="formHint2">Track balance and markets history</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'auxpow');
echo CUFHtml::activeLabelEx($coin, 'auxpow');
echo CUFHtml::activeCheckBox($coin, 'auxpow');
echo '<p class="formHint2">Merged mining</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'max_miners');
echo CUFHtml::activeLabelEx($coin, 'max_miners');
echo CUFHtml::activeTextField($coin, 'max_miners', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Miners allowed by the stratum</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'max_shares');
echo CUFHtml::activeLabelEx($coin, 'max_shares');
echo CUFHtml::activeTextField($coin, 'max_shares', array('maxlength'=>32,'style'=>'width: 120px;'));
echo '<p class="formHint2">Auto restart stratum after this amount of shares</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'master_wallet');
echo CUFHtml::activeLabelEx($coin, 'master_wallet');
echo CUFHtml::activeTextField($coin, 'master_wallet', array('maxlength'=>200));
echo '<p class="formHint2">The pool wallet address</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'reward');
echo CUFHtml::activeLabelEx($coin, 'reward');
echo CUFHtml::activeTextField($coin, 'reward', array('maxlength'=>200,'readonly'=>'readonly','style'=>'width: 120px;'));
echo '<p class="formHint2">PoW block value</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'reward_mul');
echo CUFHtml::activeLabelEx($coin, 'reward_mul');
echo CUFHtml::activeTextField($coin, 'reward_mul', array('maxlength'=>200,'style'=>'width: 120px;'));
echo '<p class="formHint2">Adjust the block reward if incorrect</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'charity_percent');
echo CUFHtml::activeLabelEx($coin, 'charity_percent');
echo CUFHtml::activeTextField($coin, 'charity_percent', array('maxlength'=>10,'style'=>'width: 30px;'));
echo '<p class="formHint2">Reward for foundation or dev fees, generally between 1 and 10 %</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'charity_address');
echo CUFHtml::activeLabelEx($coin, 'charity_address');
echo CUFHtml::activeTextField($coin, 'charity_address', array('maxlength'=>200));
echo '<p class="formHint2">Foundation address if "dev fees" are required</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'hasgetinfo');
echo CUFHtml::activeLabelEx($coin, 'hasgetinfo');
echo CUFHtml::activeCheckBox($coin, 'hasgetinfo');
echo '<p class="formHint2">Enable if getinfo rpc method is present</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'hassubmitblock');
echo CUFHtml::activeLabelEx($coin, 'hassubmitblock');
echo CUFHtml::activeCheckBox($coin, 'hassubmitblock');
echo '<p class="formHint2">Enable if submitblock method is present</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'txmessage');
echo CUFHtml::activeLabelEx($coin, 'txmessage');
echo CUFHtml::activeCheckBox($coin, 'txmessage');
echo '<p class="formHint2">Block template with a tx message</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'hasmasternodes');
echo CUFHtml::activeLabelEx($coin, 'hasmasternodes');
echo CUFHtml::activeCheckBox($coin, 'hasmasternodes');
echo '<p class="formHint2">Require "payee" and "payee_amount", or masternode object in getblocktemplate</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'usesegwit');
echo CUFHtml::activeLabelEx($coin, 'usesegwit');
echo CUFHtml::activeCheckBox($coin, 'usesegwit');
echo '<p class="formHint2"></p>';
echo CUFHtml::closeCtrlHolder();

echo "</div>";

//////////////////////////////////////////////////////////////////////////////////////////

echo '<div id="tabs-3">';

echo CUFHtml::openActiveCtrlHolder($coin, 'dontsell');
echo CUFHtml::activeLabelEx($coin, 'dontsell');
echo CUFHtml::activeCheckBox($coin, 'dontsell');
echo '<p class="formHint2">Disable auto send to exchange</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'sellonbid');
echo CUFHtml::activeLabelEx($coin, 'sellonbid');
echo CUFHtml::activeCheckBox($coin, 'sellonbid');
echo '<p class="formHint2">Reduce the sell price on exchanges</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'market');
echo CUFHtml::activeLabelEx($coin, 'market');
echo CUFHtml::activeTextField($coin, 'market', array('maxlength'=>128,'style'=>'width: 180px;'));
echo '<p class="formHint2">Selected exchange</p>';
echo CUFHtml::closeCtrlHolder();

if (empty($coin->price) || empty($coin->market) || $coin->market == 'unknown') {

	echo CUFHtml::openActiveCtrlHolder($coin, 'price');
	echo CUFHtml::activeLabelEx($coin, 'price');
	echo CUFHtml::activeTextField($coin, 'price', array('maxlength'=>16,'style'=>'width: 180px;'));
	echo '<p class="formHint2">Manually set the BTC price if missing</p>';
	echo CUFHtml::closeCtrlHolder();

}

echo '</div>';

//////////////////////////////////////////////////////////////////////////////////////////

echo '<div id="tabs-4">';

echo CUFHtml::openActiveCtrlHolder($coin, 'program');
echo CUFHtml::activeLabelEx($coin, 'program');
echo CUFHtml::activeTextField($coin, 'program', array('maxlength'=>128,'style'=>'width: 180px;'));
echo '<p class="formHint2">Daemon Name - I.e. bitcoind</p>';
echo CUFHtml::closeCtrlHolder();

if(empty($coin->program)){
echo CUFHtml::openActiveCtrlHolder($coin, 'conf_folder');
echo CUFHtml::activeLabelEx($coin, 'conf_folder');
echo CUFHtml::activeTextField($coin, 'conf_folder', array('maxlength'=>228,'readonly'=>'readonly'));
echo '<p class="formHint2">Field will automatically update on save.</p>';
echo CUFHtml::closeCtrlHolder();}

else
if(empty($coin->conf_folder)){
$program = substr($coin->program, 0, -1);
$coin->conf_folder = "/home/crypto-data/wallets/.$program";
echo CUFHtml::openActiveCtrlHolder($coin, 'conf_folder');
echo CUFHtml::activeLabelEx($coin, 'conf_folder');
echo CUFHtml::activeTextField($coin, 'conf_folder', array('maxlength'=>228,'readonly'=>'readonly'));
echo '<p class="formHint2">Field will automatically update on save.</p>';
echo CUFHtml::closeCtrlHolder();}

echo CUFHtml::openActiveCtrlHolder($coin, 'rpchost');
echo CUFHtml::activeLabelEx($coin, 'rpchost');
echo CUFHtml::activeTextField($coin, 'rpchost', array('maxlength'=>128,'style'=>'width: 180px;'));
echo '<p class="formHint2">I.e. 127.0.0.1</p>';
echo CUFHtml::closeCtrlHolder();

if(empty($coin->rpcport))
	$coin->rpcport = $coin->id*10;

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcport');
echo CUFHtml::activeLabelEx($coin, 'rpcport');
echo CUFHtml::activeTextField($coin, 'rpcport', array('maxlength'=>5,'style'=>'width: 60px;'));
echo '<p class="formHint2">AutoGenerated</p>';
echo CUFHtml::closeCtrlHolder();

if(empty($coin->rpcuser))
	$coin->rpcuser = 'yiimprpc';

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcuser');
echo CUFHtml::activeLabelEx($coin, 'rpcuser');
echo CUFHtml::activeTextField($coin, 'rpcuser', array('maxlength'=>128,'style'=>'width: 180px;'));
echo '<p class="formHint2">AutoGenerated</p>';
echo CUFHtml::closeCtrlHolder();

// generate a random password
if(empty($coin->rpcpasswd))
	$coin->rpcpasswd = preg_replace("|[^\w]|m",'',base64_encode(pack("H*",md5("".time().YAAMP_SITE_URL))));

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcpasswd');
echo CUFHtml::activeLabelEx($coin, 'rpcpasswd');
echo CUFHtml::activeTextField($coin, 'rpcpasswd', array('maxlength'=>128));
echo '<p class="formHint2">AutoGenerated</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'serveruser');
echo CUFHtml::activeLabelEx($coin, 'serveruser');
echo CUFHtml::activeTextField($coin, 'serveruser', array('maxlength'=>35,'style'=>'width: 180px;'));
echo '<p class="formHint2">Leave Blank</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcencoding');
echo CUFHtml::activeLabelEx($coin, 'rpcencoding');
echo CUFHtml::activeTextField($coin, 'rpcencoding', array('maxlength'=>5,'style'=>'width: 60px;'));
echo '<p class="formHint2">POW/POS</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpccurl');
echo CUFHtml::activeLabelEx($coin, 'rpccurl');
echo CUFHtml::activeCheckBox($coin, 'rpccurl');
echo '<p class="formHint2">Force the stratum to use curl for RPC</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpcssl');
echo CUFHtml::activeLabelEx($coin, 'rpcssl');
echo CUFHtml::activeCheckBox($coin, 'rpcssl');
echo '<p class="formHint2">Wallet RPC secured via SSL</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'rpccert');
echo CUFHtml::activeLabelEx($coin, 'rpccert');
echo CUFHtml::activeTextField($coin, 'rpccert');
echo "<p class='formHint2'>Certificat file for RPC via SSL</p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'account');
echo CUFHtml::activeLabelEx($coin, 'account');
echo CUFHtml::activeTextField($coin, 'account', array('maxlength'=>128,'style'=>'width: 180px;'));
echo '<p class="formHint2">Leave Blank</p>';
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'specifications');
echo CUFHtml::activeLabelEx($coin, 'specifications');
echo CUFHtml::activeTextArea($coin, 'specifications', array('maxlength'=>1048,'lines'=>35,'style'=>'width: 200px;'));
echo "<p class='formHint2'>Additional Config Settings:<br>";
echo "<br>";
echo "deprecatedrpc=accounts<br>";
echo "addnode=x.x.x.x<br>";
echo "algo=x<br>";
echo "<br>";
echo "Max 35 line limit<br>";
echo "<br>";
echo "Once all new parameters are added, you need to hit save and reload the page for the autogenerated config to update.<br></p>";
echo CUFHtml::closeCtrlHolder();

if(empty($coin->id))
if(empty($coin->program)){
echo CHtml::tag("hr");
echo "Configuration information will be displayed after Process Name is entered and saved. Please reload page once completed.\n";
}

if ($coin->id) {
if(empty($coin->program)){
echo CHtml::tag("hr");
echo "Configuration information will be displayed after Process Name is entered and saved. Please reload page once completed.\n";
}
else    {
$program = substr($coin->program, 0, -1);
echo CHtml::tag("hr");
echo "<b>Autogenerated config - if using daemonbuilder copy from rpcuser through blocknotify line</b>\n";
echo "<b>If you manually built a coin without daemonbuilder, copy this entire section</b>:\n";
echo CHtml::opentag("pre");
echo "mkdir -p {$coin->conf_folder}\n";
$port = getAlgoPort($coin->algo);
echo "echo '\n";
echo " \n";
echo "rpcuser={$coin->rpcuser}\n";
echo "rpcpassword={$coin->rpcpasswd}\n";
echo "rpcport={$coin->rpcport}\n";
echo "rpcthreads=64\n";
echo "rpcallowip=127.0.0.1\n";
echo "# onlynet=ipv4\n";
echo "maxconnections=12\n";
echo "daemon=1\n";
echo "gen=0\n";
if(empty($coin->specifications)){
echo "\n";
}
else {
echo "{$coin->specifications}\n";
echo "\n";
}
echo "alertnotify=echo %s | mail -s \"{$coin->name} alert!\" ".YAAMP_ADMIN_EMAIL."\n";
echo "blocknotify=blocknotify.sh $port {$coin->id} %s\n";
echo " \n";
echo "' | sudo -E tee {$coin->conf_folder}/$program.conf >/dev/null 2>&1\n";
echo CHtml::closetag("pre");

echo CHtml::tag("hr");
echo "<b>Add coind to system startup (cron)</b>:";
echo CHtml::opentag("pre");
echo "(crontab -l 2>/dev/null; echo \"@reboot sleep 60 && {$coin->program} -datadir={$coin->conf_folder} -conf=$program.conf -daemon -shrinkdebugfile\") | crontab -\n";
echo "\n";
echo '<p class="formHint2">add -reindex if coin fails to start.</p>';
echo CHtml::closetag("pre");

echo CHtml::tag("hr");
echo "<b>Daemon Commands</b>:";
echo "<b>You MUST use this format or coins will not work!</b>:";
echo CHtml::opentag("pre");
echo "To START a coind:\n";
echo "{$coin->program} -datadir={$coin->conf_folder} -conf=$program.conf -daemon -shrinkdebugfile\n\n";
echo "To STOP a coind:\n";
echo "{$coin->program} -datadir={$coin->conf_folder} -conf=$program.conf stop\n\n";
echo "Or if your coin has a -cli (bitcoin-cli) file...\n";
echo "$program-cli -datadir={$coin->conf_folder} -conf=$program.conf stop\n\n";
echo "To run other CLI functions:\n";
echo "$program-cli -datadir={$coin->conf_folder} -conf=$program.conf help\n\n";
echo " \n";
echo "To edit the coin.config file:\n";
echo "sudo nano {$coin->conf_folder}/$program.conf\n";
echo CHtml::closetag("pre");

echo CHtml::tag("hr");
echo "<b>Miner command line</b>:";
echo CHtml::opentag("pre");
echo "-a {$coin->algo} ";
echo "-o stratum+tcp://".YAAMP_STRATUM_URL.':'.$port.' ';
echo "-u {$coin->master_wallet} ";
echo "-p c={$coin->symbol} ";
echo "\n";
echo CHtml::closetag("pre");}
}

echo "</div>";



//////////////////////////////////////////////////////////////////////////////////////////

echo '<div id="tabs-5">';

echo CUFHtml::openActiveCtrlHolder($coin, 'link_github');
echo CUFHtml::activeLabelEx($coin, 'link_github');
echo CUFHtml::activeTextField($coin, 'link_github');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_site');
echo CUFHtml::activeLabelEx($coin, 'link_site');
echo CUFHtml::activeTextField($coin, 'link_site');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_bitcointalk');
echo CUFHtml::activeLabelEx($coin, 'link_bitcointalk');
echo CUFHtml::activeTextField($coin, 'link_bitcointalk');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_twitter');
echo CUFHtml::activeLabelEx($coin, 'link_twitter');
echo CUFHtml::activeTextField($coin, 'link_twitter');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_facebook');
echo CUFHtml::activeLabelEx($coin, 'link_facebook');
echo CUFHtml::activeTextField($coin, 'link_facebook');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_discord');
echo CUFHtml::activeLabelEx($coin, 'link_discord');
echo CUFHtml::activeTextField($coin, 'link_discord');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_exchange');
echo CUFHtml::activeLabelEx($coin, 'link_exchange');
echo CUFHtml::activeTextField($coin, 'link_exchange');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo CUFHtml::openActiveCtrlHolder($coin, 'link_explorer');
echo CUFHtml::activeLabelEx($coin, 'link_explorer');
echo CUFHtml::activeTextField($coin, 'link_explorer');
echo "<p class='formHint2'></p>";
echo CUFHtml::closeCtrlHolder();

echo "</div>";


echo "</div>";

echo CUFHtml::closeTag('fieldset');
showSubmitButton($update? 'Save': 'Create');
echo CUFHtml::endForm();
