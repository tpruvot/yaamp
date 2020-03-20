<?php
if (!$coin) $this->goback();

require dirname(__FILE__) . '/../../ui/lib/pageheader.php';

$this->pageTitle = 'Peers - ' . $coin->name;

$remote = new WalletRPC($coin);
$info = $remote->getinfo();

//////////////////////////////////////////////////////////////////////////////////////
echo <<<end
<style type="text/css">
body { margin: 4px; }
pre { margin: 0 4px; }
</style>

<div class="main-left-box">
<div class="main-left-title">{$this->pageTitle}</div>
<div class="main-left-inner">
end;


$addnode = array();
$version = '';
$localheight = arraySafeVal($info, 'blocks');

$list = $remote->getpeerinfo();

if (!empty($list)) foreach ($list as $peer)
{
    $node = arraySafeVal($peer, 'addr');
    if (strstr($node, '127.0.0.1')) continue;
    if (strstr($node, '192.168.')) continue;
    if (strstr($node, 'yiimp')) continue;

    $addnode[] = ($coin->rpcencoding == 'DCR' ? 'addpeer=' : 'addnode=') . $node;

    $peerver = trim(arraySafeVal($peer, 'subver') , '/');
    $version = max($version, $peerver);
}

asort($addnode);

echo '<pre>';
echo implode("\n", $addnode);
echo '</pre>';

echo '</div>';
echo '</div>';
