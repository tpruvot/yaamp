<?php
define("ADDRESSVERSION", "00"); //this is a hex byte
function decodeHex($hex)
{
    $hex = strtoupper($hex);
    $chars = "0123456789ABCDEF";
    $return = "0";
    for ($i = 0;$i < strlen($hex);$i++)
    {
        $current = (string)strpos($chars, $hex[$i]);
        $return = (string)bcmul($return, "16", 0);
        $return = (string)bcadd($return, $current, 0);
    }
    return $return;
}

function encodeHex($dec)
{
    $chars = "0123456789ABCDEF";
    $return = "";
    while (bccomp($dec, 0) == 1)
    {
        $dv = (string)bcdiv($dec, "16", 0);
        $rem = (integer)bcmod($dec, "16");
        $dec = $dv;
        $return = $return . $chars[$rem];
    }
    return strrev($return);
}

function decodeBase58($base58)
{
    $origbase58 = $base58;

    $chars = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
    $return = "0";
    for ($i = 0;$i < strlen($base58);$i++)
    {
        $current = (string)strpos($chars, $base58[$i]);
        $return = (string)bcmul($return, "58", 0);
        $return = (string)bcadd($return, $current, 0);
    }

    $return = encodeHex($return);

    //leading zeros
    for ($i = 0;$i < strlen($origbase58) && $origbase58[$i] == "1";$i++)
    {
        $return = "00" . $return;
    }

    if (strlen($return) % 2 != 0)
    {
        $return = "0" . $return;
    }

    return $return;
}

function encodeBase58($hex)
{
    if (strlen($hex) % 2 != 0)
    {
        die("encodeBase58: uneven number of hex characters");
    }
    $orighex = $hex;

    $chars = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
    $hex = decodeHex($hex);
    $return = "";
    while (bccomp($hex, 0) == 1)
    {
        $dv = (string)bcdiv($hex, "58", 0);
        $rem = (integer)bcmod($hex, "58");
        $hex = $dv;
        $return = $return . $chars[$rem];
    }
    $return = strrev($return);

    //leading zeros
    for ($i = 0;$i < strlen($orighex) && substr($orighex, $i, 2) == "00";$i += 2)
    {
        $return = "1" . $return;
    }

    return $return;
}

function hash160ToAddress($hash160, $addressversion = ADDRESSVERSION)
{
    $hash160 = $addressversion . $hash160;
    $check = pack("H*", $hash160);
    $check = hash("sha256", hash("sha256", $check, true));
    $check = substr($check, 0, 8);
    $hash160 = strtoupper($hash160 . $check);
    return encodeBase58($hash160);
}

function addressToHash160($addr)
{
    $addr = decodeBase58($addr);
    $addr = substr($addr, 2, strlen($addr) - 10);
    return $addr;
}

function checkAddress($addr, $addressversion = ADDRESSVERSION)
{
    $addr = decodeBase58($addr);
    if (strlen($addr) != 50)
    {
        return false;
    }
    $version = substr($addr, 0, 2);
    if (hexdec($version) > hexdec($addressversion))
    {
        return false;
    }
    $check = substr($addr, 0, strlen($addr) - 8);
    $check = pack("H*", $check);
    $check = strtoupper(hash("sha256", hash("sha256", $check, true)));
    $check = substr($check, 0, 8);
    return $check == substr($addr, strlen($addr) - 8);
}

function hash160($data)
{
    $data = pack("H*", $data);
    return strtoupper(hash("ripemd160", hash("sha256", $data, true)));
}

function pubKeyToAddress($pubkey)
{
    return hash160ToAddress(hash160($pubkey));
}

function remove0x($string)
{
    if (substr($string, 0, 2) == "0x" || substr($string, 0, 2) == "0X")
    {
        $string = substr($string, 2);
    }
    return $string;
}

// version is used for multi algo coins
function versionToAlgo($coin, $version)
{
    // could be filled by block json (chain analysis)
    $algos['MYR'] = array(
        0 => 'sha256',
        1 => 'scrypt',
        2 => 'myr-gr',
        3 => 'skein',
        4 => 'qubit',
        5 => 'yescrypt'
    );
    $algos['DGB'] = array(
        0 => 'scrypt',
        1 => 'sha256',
        2 => 'myr-gr',
        3 => 'skein',
        4 => 'qubit'
    );
    $algos['AUR'] = array(
        0 => 'sha256',
        1 => 'scrypt',
        2 => 'myr-gr',
        3 => 'skein',
        4 => 'qubit'
    );
    $algos['DGC'] = array(
        0 => 'scrypt',
        1 => 'sha256',
        2 => 'x11'
    );
    $algos['DUO'] = array(
        0 => 'sha256',
        1 => 'scrypt'
    );
    $algos['J'] = array(
        2 => 'sha256',
        3 => 'x11',
        4 => 'x13',
        5 => 'x15',
        6 => 'scrypt',
        7 => 'nist5',
        8 => 'myr-gr',
        9 => 'penta',
        10 => 'whirlpool',
        11 => 'luffa',
        12 => 'keccak',
        13 => 'quark',
        15 => 'bastion'
    );
    $algos['GCH'] = array(
        0 => 'x12',
        1 => 'x11',
        2 => 'x13',
        3 => 'sha256',
        4 => 'blake2s'
    );
    $algos['GLT'] = array(
        0 => 'sha256',
        1 => 'scrypt',
        2 => 'x11',
        3 => 'neoscrypt',
        4 => 'equihash',
        5 => 'yescrypt',
        6 => 'hmq1725',
        7 => 'xevan',
        8 => 'nist5',
        9 => 'bitcore',
        10 => 'pawelhash',
        11 => 'x13',
        12 => 'x14',
        13 => 'x15',
        14 => 'x17',
        15 => 'lyra2v2',
        16 => 'blake2s',
        17 => 'blake2b',
        18 => 'astralhash',
        19 => 'padihash',
        20 => 'jeonghash',
        21 => 'keccak',
        22 => 'zhash',
        23 => 'globalhash',
        24 => 'skein',
        25 => 'myr-gr',
        26 => 'qubit',
        27 => 'skunk',
        28 => 'quark',
        29 => 'x16r'
    );
    $algos['RICHX'] = array(
        0 => 'sha256',
        1 => 'scrypt',
        2 => 'myr-gr',
        3 => 'skein',
        4 => 'qubit'
    );
    $algos['SFR'] = array(
        0 => 'sha256',
        1 => 'scrypt',
        2 => 'myr-gr',
        3 => 'x11',
        4 => 'blake'
    );
    $algos['UIS'] = array(
        0 => 'lyra2v2',
        1 => 'skein',
        2 => 'qubit',
        3 => 'yescrypt',
        4 => 'x11'
    );
    $algos['XVG'] = array(
        0 => 'scrypt',
        1 => 'scrypt',
        2 => 'myr-gr',
        3 => 'x17',
        4 => 'blake2s',
        10 => 'lyra2v2',
    );
    $algos['XSH'] = array(
        0 => 'scrypt',
        1 => 'scrypt',
        2 => 'myr-gr',
        3 => 'x17',
        4 => 'blake2s',
        10 => 'lyra2v2',
        11 => 'x16s',
    );
    $algos['ARG'] = array(
        0 => 'sha256',
        1 => 'scrypt',
        2 => 'lyra2v2',
        3 => 'myr-gr',
        4 => 'argon2d',
        5 => 'yescrypt',
    );
    $symbol = $coin->symbol;
    if (!empty($coin->symbol2)) $symbol = $coin->symbol2;

    if ($symbol == 'J') return arraySafeVal($algos[$symbol], $version, '');
    else if ($symbol == 'GCH') return arraySafeVal($algos[$symbol], ($version - 9) , '');
    else if ($symbol == 'XVG') return arraySafeVal($algos[$symbol], ($version >> 11) , 'scrypt');
    else if ($symbol == 'XSH') return arraySafeVal($algos[$symbol], (($version - 536870000) >> 11) , 'scrypt');
    else if (isset($algos[$symbol])) return arraySafeVal($algos[$symbol], ($version >> 9) & 7, '');
    return false;
}
