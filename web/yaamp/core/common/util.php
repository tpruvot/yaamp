<?php

function controller()
{
	// console app has no controller
	if (app() instanceof CConsoleApplication)
		return app();

	return app()->getController();
}

function cache()
{
	if (app() instanceof CConsoleApplication)
		return app()->cache;

	return app()->getController()->memcache;
}

function objSafeVal($obj,$key,$default=NULL)
{
	if (is_object($obj) && property_exists($obj,$key))
		return $obj->$key;
	elseif (is_array($obj))
		return arraySafeVal($obj,$key,$default);
	return $default;
}

function arraySafeVal($arr,$key,$default=NULL)
{
	if (is_array($arr) && isset($arr[$key]))
		return $arr[$key];
	elseif (is_object($arr))
		return objSafeVal($arr,$key,$default);
	return $default;
}

function getparam($p,$default='')
{
	return isset($_REQUEST[$p]) ? $_REQUEST[$p] : $default;
}

function gethexparam($p,$default='')
{
	$str = getparam($p, NULL);
	$hex = (is_string($str) && ctype_xdigit($str)) ? $str : $default;
	return $hex;
}

function getiparam($p,$default=0)
{
	// workaround for yii default /route/<id> ....
	if ($p == 'id') {
		$id = isset($_REQUEST[$p]) ? $_REQUEST[$p] : $default;
		if (!$id) {
			$url = explode('/', $_SERVER['REQUEST_URI']);
			$id = array_pop($url);
		}
		return (int) $id;
	}
	return isset($_REQUEST[$p]) ? intval($_REQUEST[$p]) : $default;
}

function getalgoparam()
{
	$algo = strip_tags(substr(getparam('algo'), 0, 32));
	return $algo;
}

//////////////////////////////////////////////////////

function downloadFile($url, &$size)
{
	$data = file_get_contents($url);
	$tempname = gettempfile('.ext');

	file_put_contents($tempname, $data);
	$size = dos_filesize($tempname);

	unlink($tempname);
	return $data;
}

function getServerName()
{
	if(strpos($_SERVER['SERVER_NAME'], ':'))
		return substr($_SERVER['SERVER_NAME'], 0, strpos($_SERVER['SERVER_NAME'], ':'));

	return $_SERVER['SERVER_NAME'];
}

function getFullServerName()
{
	if(isset($_SERVER['HTTPS']) && !strcasecmp($_SERVER['HTTPS'], 'on'))
		$protocol = 'https';
	else
		$protocol = 'http';

	return $protocol.'://'.$_SERVER['HTTP_HOST'];
}

///////////////////

function getClientPlatform()
{
	$agent = $_SERVER['HTTP_USER_AGENT'];
	$bname = 'Unknown';
	$platform = 'Unknown';
	$version= "";

	if(preg_match('/ipad/i', $agent))
		$platform = 'Ipad';

	else if(preg_match('/iphone/i', $agent))
		$platform = 'Iphone';

	else if(preg_match('/android/i', $agent))
		$platform = 'Android';

	else if(preg_match('/linux/i', $agent))
		$platform = 'Linux';

	elseif(preg_match('/macintosh|mac os x/i', $agent))
		$platform = 'Mac';

	elseif(preg_match('/windows|win32/i', $agent))
		$platform = 'Windows';

    //////////////////////////////////////////////////////////////////////

    if(preg_match('/MSIE/i',$agent) && !preg_match('/Opera/i',$agent))
    {
        $bname = 'Internet Explorer';
        $ub = "MSIE";
    }
    elseif(preg_match('/Firefox/i',$agent))
    {
        $bname = 'Firefox';
        $ub = "Firefox";
    }
    elseif(preg_match('/Chrome/i',$agent))
    {
        $bname = 'Chrome';
        $ub = "Chrome";
    }
    elseif(preg_match('/Safari/i',$agent))
    {
        $bname = 'Safari';
        $ub = "Safari";
    }
    elseif(preg_match('/Opera/i',$agent))
    {
        $bname = 'Opera';
        $ub = "Opera";
    }
    elseif(preg_match('/Netscape/i',$agent))
    {
        $bname = 'Netscape';
        $ub = "Netscape";
    }

    // finally get the correct version number
	$known = array('Version', $ub, 'other');
	$pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';

	preg_match_all($pattern, $agent, $matches);

    // see how many we have
    $i = count($matches['browser']);
    if ($i != 1) {
        //we will have two since we are not using 'other' argument yet
        //see if version is before or after the name
        if (strripos($agent,"Version") < strripos($agent,$ub)){
            $version= $matches['version'][0];
        }
        else {
            $version= $matches['version'][1];
        }
    }
    else {
        $version= $matches['version'][0];
    }

    // check if we have a number
    if ($version==null || $version=="") {$version="?";}

    return "$platform, $bname $version";
}

function IsMobileDevice()
{
	$agent = $_SERVER['HTTP_USER_AGENT'];

	return preg_match('/ipad/i', $agent) ||
		preg_match('/iphone/i', $agent) ||
		preg_match('/android/i', $agent);
}

function file_get_contents_curl($url, $user=null)
{
	$ch = curl_init($url);

	if($user)
	{
		$a = explode(',', $user->access_token);
		$oauth_token = $a[0];
		$oauth_token_secret = $a[1];

		$oauth = array(
				'oauth_consumer_key' => CONSUMER_KEY,
				'oauth_nonce' => md5(microtime().mt_rand()),
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_timestamp' => time(),
				'oauth_token' => $oauth_token,
				'oauth_version' => '1.0');

		$base_info = buildBaseString($url, 'POST', $oauth);
		$composite_key = rawurlencode(CONSUMER_SECRET).'&'.rawurlencode($oauth_token_secret);
		$oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
		$header = buildAuthorizationHeader($oauth);

	//	debuglog($header);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array($header));
	}

	$maxallowed = 64*1024;
	$totalread = 0;
	$data = '';

	$callback = function($ch, $text) use(&$data, &$maxallowed, &$totalread)
	{
		$data .= $text;
		$count = strlen($text);
		$totalread += $count;

		if($totalread >= $maxallowed || stristr($data, '</head>'))
			return 0;

		return $count;
	};

	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Tweetshow 2.1)');
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_setopt($ch, CURLOPT_ENCODING , "deflate,gzip");
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_WRITEFUNCTION, $callback);

	curl_exec($ch);
	curl_close($ch);

//	debuglog(" total read $totalread, ".strlen($data));
	return $data;
}

function force_wordbreak($text, $max)
{
	$last = 0;
	for($i=0; $i<strlen($text); $i++)
	{
		if($text[$i] == ' ')
			$last = $i;

		else if($i - $last > $max)
		{
			$text = substr_replace($text, ' ', $i, 0);
			$i++;
			$last = $i;
		}
	}

	return $text;
}


/////////////////////////////////////////////////////////////////////////

function adjust_background_color($color)
{
	sscanf($color, "%x", $rgb);
	$r = ($rgb >> 16) & 0xFF;
	$g = ($rgb >> 8) & 0xFF;
	$b = $rgb & 0xFF;
	$limit1 = 0xf4;
	if($r < $limit1) $r = $limit1-($limit1 - $r)/16;
	if($g < $limit1) $g = $limit1-($limit1 - $g)/16;
	if($b < $limit1) $b = $limit1-($limit1 - $b)/32;
	$color1 = ($r << 16) + ($g << 8) + $b;
	$color1 = sprintf("%x", $color1);
	return $color1;
}

function adjust_foreground_color($color)
{
	sscanf($color, "%x", $rgb);
	$r = ($rgb >> 16) & 0xFF;
	$g = ($rgb >> 8) & 0xFF;
	$b = $rgb & 0xFF;
	$limit2 = 0x33;
	if($r > $limit2) $r = $limit2;
	if($g > $limit2) $g = $limit2;
	if($b > $limit2) $b = $limit2;
	$color2 = ($r << 16) + ($g << 8) + $b;
	$color2 = sprintf("%x", $color2);
	return $color2;
}

function changeColor($user)
{
	if(!$user) return;

	$color = $user->profile_background_color;
	sscanf($color, "%x", $rgb);

	$r = ($rgb >> 16) & 0xFF;
	$g = ($rgb >> 8) & 0xFF;
	$b = $rgb & 0xFF;

	$r = $r * 5/6;
	$g = $g * 5/6;
	$b = $b * 5/6;

	$color2 = ($r << 16) + ($g << 8) + $b;
	$color2 = sprintf("%x", $color2);

	echo "<style>.page .header { background-color: #$color;}</style>\n";
	echo "<style>.page .footer { background-color: #$color2;}</style>\n";
	echo "<style>.page .content { background-color: #$color2;}</style>\n";
	echo "<style>.page .tabmenu { background-color: #$color2;}</style>\n";
}

class RecursiveDOMIterator implements RecursiveIterator
{
	protected $_position;
	protected $_nodeList;
	public function __construct(DOMNode $domNode)
	{
		$this->_position = 0;
		$this->_nodeList = $domNode->childNodes;
	}
	public function getChildren() { return new self($this->current()); }
	public function key()         { return $this->_position; }
	public function next()        { $this->_position++; }
	public function rewind()      { $this->_position = 0; }
	public function valid()
	{
		return $this->_position < $this->_nodeList->length;
	}
	public function hasChildren()
	{
		return $this->current()->hasChildNodes();
	}
	public function current()
	{
		return $this->_nodeList->item($this->_position);
	}
}







