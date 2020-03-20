<?php
function send_email_alert($name, $title, $message, $t = 10)
{
    //	debuglog(__FUNCTION__);
    $last = controller()
        ->memcache
        ->get("last_email_sent_$name");
    if ($last + $t * 60 > time()) return;

    debuglog("mail('" . YAAMP_ADMIN_EMAIL . "', $title, ...)");

    $b = mail(YAAMP_ADMIN_EMAIL, $title, $message);
    if (!$b) debuglog('error sending email');

    controller()
        ->memcache
        ->set("last_email_sent_$name", time());
}

function dos_filesize($fn)
{
    return exec('FOR %A IN ("' . $fn . '") DO @ECHO %~zA');
}

//////////////////////////////////////////////////////////////
function GetCpuLoad()
{
    $wmi = new COM("Winmgmts://");
    $result = $wmi->execquery("SELECT LoadPercentage FROM Win32_Processor");

    $cpu_num = 0;
    $load_total = 0;

    foreach ($result as $cpu)
    {
        $cpu_num++;
        $load_total += $cpu->loadpercentage;
    }

    $load = round($load_total / $cpu_num);
    return $load;
}

function GetNetworkLoad()
{
    $wmi = new COM("Winmgmts://");
    $allnets = $wmi->execquery("Select BytesTotalPersec From Win32_PerfFormattedData_Tcpip_NetworkInterface where BytesTotalPersec>1");

    $totalbps = 0;
    foreach ($allnets as $network)
    {
        $bps = $network->BytesTotalPersec * 8;
        $totalbps += $bps;
    }

    return $totalbps;
}

function getclientip()
{
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];

    return $_SERVER['REMOTE_ADDR'];
}

/**
 * Retrieves the best guess of the client's actual IP address.
 * Takes into account numerous HTTP proxy headers due to variations
 * in how different ISPs handle IP addresses in headers between hops.
 */
function get_ip_address()
{
    // check for shared internet/ISP IP
    if (!empty($_SERVER['HTTP_CLIENT_IP']) && validate_ip($_SERVER['HTTP_CLIENT_IP']))
    {
        return $_SERVER['HTTP_CLIENT_IP'];
    }

    // check for IPs passing through proxies
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
    {
        // check if multiple ips exist in var
        if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false)
        {
            $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($iplist as $ip)
            {
                if (validate_ip($ip)) return $ip;
            }
        }
        else
        {
            if (validate_ip($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED']) && validate_ip($_SERVER['HTTP_X_FORWARDED'])) return $_SERVER['HTTP_X_FORWARDED'];
    if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && validate_ip($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && validate_ip($_SERVER['HTTP_FORWARDED_FOR'])) return $_SERVER['HTTP_FORWARDED_FOR'];
    if (!empty($_SERVER['HTTP_FORWARDED']) && validate_ip($_SERVER['HTTP_FORWARDED'])) return $_SERVER['HTTP_FORWARDED'];

    // return unreliable ip since all else failed
    return $_SERVER['REMOTE_ADDR'];
}

/**
 * Ensures an ip address is both a valid IP and does not fall within
 * a private network range.
 */
function validate_ip($ip)
{
    if (strtolower($ip) === 'unknown') return false;

    // generate ipv4 network address
    $ip = ip2long($ip);

    // if the ip is set and not equivalent to 255.255.255.255
    if ($ip !== false && $ip !== - 1)
    {
        // make sure to get unsigned long representation of ip
        // due to discrepancies between 32 and 64 bit OSes and
        // signed numbers (ints default to signed in PHP)
        $ip = sprintf('%u', $ip);
        // do private network range checking
        if ($ip >= 0 && $ip <= 50331647) return false;
        if ($ip >= 167772160 && $ip <= 184549375) return false;
        if ($ip >= 2130706432 && $ip <= 2147483647) return false;
        if ($ip >= 2851995648 && $ip <= 2852061183) return false;
        if ($ip >= 2886729728 && $ip <= 2887778303) return false;
        if ($ip >= 3221225984 && $ip <= 3221226239) return false;
        if ($ip >= 3232235520 && $ip <= 3232301055) return false;
        if ($ip >= 4294967040) return false;
    }
    return true;
}
