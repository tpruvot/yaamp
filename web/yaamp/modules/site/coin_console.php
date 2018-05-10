<?php

if (!$coin) $this->goback();
$this->pageTitle = 'Console - '.$coin->symbol;

$remote = new WalletRPC($coin);

echo getAdminSideBarLinks().'<br/><br/>';

$info = $remote->getinfo();
if (!$info) {
	echo $remote->error;
	return;
}

echo getAdminWalletLinks($coin, $info, 'console').'<br/><br/>';

//////////////////////////////////////////////////////////////////////////////////////

function colorizeJson($json)
{
	$json = str_replace('"', '&quot;', $json);
	// strings
	$res = preg_match_all("# &quot;([^&]+)&quot;([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$sfx = $matches[2][$n];
		$class = '';
		if (strlen($m) == 64 && ctype_xdigit($m)) $class = 'hash';
		if (strlen($m) == 34 && ctype_alnum($m)) $class = 'addr';
		if (strlen($m) == 35 && ctype_alnum($m)) $class = 'addr';
		if (strlen($m) > 160 && ctype_alnum($m)) $class = 'data';
		if ($class == '' && strlen($m) < 64 && ctype_xdigit($m)) $class = 'hexa';
		$json = str_replace(' &quot;'.$m."&quot;".$sfx, ' "<s class="'.$class.'">'.$m.'</s>"'.$sfx, $json);
	}
	// keys
	$res = preg_match_all("#&quot;([^&]+)&quot;:#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$json = str_replace('&quot;'.$m."&quot;", '"<s class="key">'.$m.'</s>"', $json);
	}
	// humanize timestamps like "blocktime": 1462359961,
	$res = preg_match_all("#: ([0-9]{10})([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$ts = intval($m);
		if ($ts > 1400000000 && $ts < 1600000000) {
			$sfx = $matches[2][$n];
			$date = strftime("<u>%Y-%m-%d %T %z</u>", $ts);
			$json = str_replace(' '.$m.$sfx, ' "'.$date.'"'.$sfx, $json);
		}
	}
	// numeric
	$res = preg_match_all("#: ([e\-\.0-9]+)([,\s])#", $json, $matches);
	if ($res) foreach($matches[1] as $n=>$m) {
		$sfx = $matches[2][$n];
		$json = str_replace(' '.$m.$sfx, ' <i>'.$m.'</i>'.$sfx, $json);
	}
	$json = preg_replace('#\[\s+\]#', '[]', $json);
	$json = str_replace('[', '<b>[</b>', $json);
	$json = str_replace(']', '<b>]</b>', $json);
	$json = str_replace('{', '<b>{</b>', $json);
	$json = str_replace('}', '<b>}</b>', $json);
	return $json;
}

//////////////////////////////////////////////////////////////////////////////////////

$last_query = htmlentities(trim($query));

echo <<<end
<script type="text/javascript">
function main_resize() {
	var w = 0 + jQuery('div.form').width();
	var wpx = (w - 100).toString() + 'px';
	jQuery('.main-text-input').css({width: wpx});
}

var lazyLinks;
function main_json_links() {
	if (lazyLinks) clearTimeout(lazyLinks);
	jQuery('s.addr').each(function(n) {
		var el = $(this);
		var addr = el[0].innerText;
		var link = '<a href="/?address='+addr+'" target="_blank">' + addr + '</a>';
		el.html(link);
	});
	jQuery('s.hash').each(function(n) {
		var el = $(this);
		var hash = el[0].innerText;
		var link = '<a href="/explorer/search?SYM={$coin->symbol}&q='+hash+'" target="_blank">' + hash + '</a>';
		el.html(link);
	});
}
</script>

<style type="text/css">
div.form { margin-right: 8px; }
div.rpcerror, div.terminal {
	white-space: pre; font-family: monospace; unicode-bidi: embed; padding: 4px;
	overflow-x: hidden;
}
div.rpcerror { color: darkred; background: transparent; margin-top: 0; margin-bottom: -8px; }
div.terminal { color: silver; background: black; min-height: 180px; margin-left: 0; margin-right: 8px; margin-bottom: 8px; margin-top: 8px; }
.terminal s { text-decoration: none; color: #ffffcf; }
.terminal s.key { color: #ffff7f; }
.terminal s a { color: #ffffcf; text-decoration: none; }
.terminal s a:hover { text-decoration: underline; }
.terminal u { text-decoration: none; color: #ff7f7f; }
.terminal i { font-style: normal; color: #ff7fff; }
.terminal b { font-style: normal; color: #ff3f3f; }
.page .footer { width: auto; }
</style>

<div class="form">
<form action="/site/console?id={$coin->id}" method="post" style="padding: 0px;">
<input class="main-text-input" value="{$last_query}" type="text" name="query" placeholder="Query" style="width: 50%; margin-right: 4px;">
<input class="main-submit-button" type="submit" value="Execute" style="width: 80px;">
</form>
</div>
end;

$result = '';
if (!empty($query)) {
	$result = $remote->execute($query);
	if ($result === false) {
		$result = $remote->error;
	}
	debuglog("{$coin->symbol} CONSOLE {$query}");
}

if (!empty($remote->error) && $remote->error != $result) {
	$err = $remote->error;
	echo '<div class="rpcerror">';
	echo is_string($err) ? htmlentities($err) : htmlentities(json_encode($err, 128));
	echo '</div>';
}

echo '<div class="terminal">';
echo is_string($result) ? htmlentities($result) : colorizeJson(htmlentities(json_encode($result, 128)));
echo '</div>';

JavascriptReady("main_resize(); $(window).resize(main_resize); $('.main-text-input:first').focus();");

JavascriptReady("lazyLinks = setTimeout(main_json_links, 2000);");

