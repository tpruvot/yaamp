<?php

function moondex_api_query($method, $params='', $returnType='object')
{
        $uri = "https://dex.moondex.org/api/v1/$method";
        if (!empty($params))
                $uri .= "/$params";

        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = strip_tags(curl_exec($ch));

        if ($returnType == 'object')
        { $result = json_decode($res); }
        else
        { $result = json_decode($res,true); }

        if(!is_object($result) && !is_array($result)) {
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if (strpos($res,'Maintenance'))
                        debuglog("moondex: $method failed (Maintenance)");
                else
                        debuglog("moondex: $method failed ($status) ".strip_data($res));
        }

        curl_close($ch);
        return $result;
}
