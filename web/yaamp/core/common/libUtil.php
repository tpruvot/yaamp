<?php

$global_lastlog = 0;
function logtime($text)
{
	global $global_lastlog;

	$t = microtime(true);
	$d = $t - $global_lastlog;

	if($d >= 1) $text = $text.' =====================';
	error_log("$t, $d - $text");

	$global_lastlog = $t;
}

//

function LimitRequest($name, $limit=1)
{
	$t = controller()->memcache->get("yaamp-timestamp-$name-{$_SERVER['REMOTE_ADDR']}");
	$a = controller()->memcache->get("yaamp-average-$name-{$_SERVER['REMOTE_ADDR']}");

	if(!$a || !$t) $a = $limit;

	else
	{
		$p = 33;
		$a = ($a * (100-$p) + (microtime(true)-$t) * $p) / 100;
	}

	if($a < $limit) return false;

	controller()->memcache->set("yaamp-timestamp-$name-{$_SERVER['REMOTE_ADDR']}", microtime(true), 300);
	controller()->memcache->set("yaamp-average-$name-{$_SERVER['REMOTE_ADDR']}", $a, 300);

	return true;
}

function getuserparam($address)
{
	if(empty($address)) return null;

	$address = trim(substr($address, 0, 98));
	$user = getdbosql('db_accounts', "username=:ad", array(':ad'=>$address));

	return $user;
}

function getrenterparam($address)
{
	if(empty($address)) return null;

	$address = trim(substr($address, 0, 98));
	$renter = getdbosql('db_renters', "address=:ad", array(':ad'=>$address));

	return $renter;
}

///////////////////////////////////////////////////////////

function GetSSModulePath($name)
{
	$result = findfile('yaamp/models', "/\/{$name}.php/");
	if(!$result)
		$result = findfile('yaamp/modules', "/\/{$name}.php/");

//	debuglog($result);
	return $result;
}

function findfile($path, $pattern)
{
	$result = null;

	$path = rtrim(str_replace("\\", "/", $path), '/') . '/*';
	foreach(glob($path) as $fullname)
	{
		if(is_dir($fullname))
		{
			$result = findfile($fullname, $pattern);
			if($result) break;
		}

		else if(preg_match($pattern, $fullname))
		{
			$result = $fullname;
			break;
		}
	}

	return $result;
}

function mydump($obj, $level=2)
{
	CVarDumper::dump($obj, $level, true);
	echo "<br>";
}

function mydumperror($obj, $level=2)
{
	CVarDumper::dumperror($obj, $level);
}

function debuglog($string, $level=2)
{
	if(is_object($string))
	{
		mydumperror($string, $level);
		return;
	}

	if(is_array($string))
	{
		mydumperror($string, $level);
		return;
	}

	$now = now();
	if(!is_dir(YAAMP_LOGS)) mkdir(YAAMP_LOGS);
	error_log("[$now] $string\n", 3, YAAMP_LOGS."/debug.log");
}

function echolog($string, $level=2)
{
	$now = now();
	echo "[$now] $string\n";
}

function rentallog($string)
{
	$now = now();
	if(!is_dir(YAAMP_LOGS)) mkdir(YAAMP_LOGS);
	error_log("[$now] $string\n", 3, YAAMP_LOGS."/rental.log");

	debuglog($string);
}

/////////////////////////////////////////////////////////////////////////////////////////

function xmltoarray($xmlcontent)
{
	$xml = simplexml_load_string($xmlcontent);
	$json = json_encode($xml);
	$array = json_decode($json, true);

	return $array;
}

function XssFilter($data)
{
	$data = str_replace(">", "", $data);
	$data = str_replace("<", "", $data);
	$data = str_replace("'", "", $data);
	$data = str_replace('"', "", $data);
//	$data = str_replace(".", "", $data);
	$data = str_replace("\\", "", $data);
	$data = str_replace("&", "", $data);
	$data = str_replace(";", "", $data);

//	mydump($data); die;
	return $data;
}

function showDatetimePicker($model, $attribute, $options='')
{
	$name = "{$model->tableSchema->name}[{$attribute}]";
	$id = "{$model->tableSchema->name}_{$attribute}";

	echo "<script>
	  $(function() {
	    $('#$id').datepicker(
	    {
			changeMonth: true,
			changeYear: true,
			dateFormat: 'yy-mm-dd'
		});
	  });
	  </script>";

	echo "<input id='$id' name='$name' class='textInput tweetnews-input' type='text'
		value='{$model->$attribute}' $options>";
}

function showDatetimePicker2($name, $value, $options='', $callback='null')
{
	$id = $name;
	echo "<script>
	  $(function() {
	    $('#$id').datepicker(
	    {
			changeMonth: true,
			changeYear: true,
			dateFormat: 'yy-mm-dd',
			onSelect: $callback
		});
	  });
	  </script>";

	if(empty($value)) $value = $name;
	echo "<input id='$id' name='$name' type='text' $options class='tweetnews-input' value='$value' size='10'>";
}

function showSubmitButton($name)
{
	echo "<div class='buttonHolder'>";
	echo CUFHtml::submitButton($name, array('id'=>'btnSubmit'));
	echo "</div>";
	echo "<script>$(function(){ $('#btnSubmit').button(); }); </script>";
}

function InitMenuTabs($tabname)
{
	JavascriptReady("$('$tabname').tabs({ select: function(event, ui){
		window.location.replace(ui.tab.hash); return true;}});");
}

function fetch_url($url)
{
//	debuglog("fetch_url($url)");
	$buffer = '';

	$file = @fopen($url, "r");
	if(!$file) return null;

	while(!feof($file))
	{
		$line = fgets($file, 1024);
		$buffer .= $line;
	}

	fclose($file);
	return $buffer;
}

function gettempfile($ext)
{
	$phpsessid = session_id();
	$random = mt_rand();

	$filename = SANSSPACE_TEMP."\\{$phpsessid}-{$random}{$ext}";
	return $filename;
}

function make_bitly_url($url, $format = 'xml', $version = '2.0.1')
{
	$login = 'o_1uu6u4g2h4';
	$appkey = 'R_433ebafeb24374d6c183c0fcbcc01575';

	$bitly = 'http://api.bit.ly/shorten?version='.$version.'&longUrl='.urlencode($url).'&login='.$login.'&apiKey='.$appkey.'&format='.$format;
	$response = file_get_contents($bitly);
//	debuglog($response);

	if(strtolower($format) == 'json')
	{
		$json = @json_decode($response,true);
		return $json['results'][$url]['shortUrl'];
	}
	else //xml
	{
		$xml = simplexml_load_string($response);
		return 'bit.ly/'.$xml->results->nodeKeyVal->hash;
	}
}

function resolveShortURL($url1)
{
	$ch = curl_init("$url1");

	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_ENCODING , "deflate,gzip");

	$http_data = curl_exec($ch);
	$curl_info = curl_getinfo($ch);
	$headers = substr($http_data, 0, $curl_info["header_size"]);

	preg_match("!\r\n(?:Location|URI): *(.*?) *\r\n!", $headers, $matches);
	$url = $matches[1];

//	debuglog(" short $url1 -> $url");
	return empty($url)? $url1: $url;
}

function is_short_url($url)
{
	// 1. Overall URL length - May be a max of 30 charecters
	if (strlen($url) > 30) return false;

	$parts = parse_url($url);

	// No query string & no fragment
	if ($parts["query"] || $parts["fragment"]) return false;

	$path = $parts["path"];
	$pathParts = explode("/", $path);

	// 3. Number of '/' after protocol (http://) - Max 2
	if (count($pathParts) > 2) return false;

	// 2. URL length after last '/' - May be a max of 10 characters
	$lastPath = array_pop($pathParts);
	if (strlen($lastPath) > 12) return false;

	// 4. Max length of host
	if (strlen($parts["host"]) > 10) return false;

	return true;
}
