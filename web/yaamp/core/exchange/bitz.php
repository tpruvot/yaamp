<?php
// see https://www.bit-z.com/api.html
// https://www.bit-z.com/api_v1/ticker?coin=mzc_btc
// https://www.bit-z.com/api_v1/tickerall
function bitz_api_query($method, $params='', $returnType='object')
{
        $url = "https://www.bit-z.com/api_v1/$method/";
        if (!empty($params))
                $url .= "?$params";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; BitZ API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
        curl_setopt($ch, CURLOPT_ENCODING , '');
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $execResult = curl_exec($ch);
        if ($returnType == 'object')
                $ret = json_decode($execResult);
        else
                $ret = json_decode($execResult,true);
        return $ret->data;
}
