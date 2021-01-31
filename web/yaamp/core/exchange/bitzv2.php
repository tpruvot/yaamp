<?php
/**
 * Created by Bitz
 * https://apidoc.bit-z.com/en/Demo/PHP.html
 * https://github.com/Shiva-ly/api-dependent-file
 */
 require_once('/etc/yiimp/keys.php');
if (!defined('EXCH_BITZ_KEY')) define('EXCH_BITZ_KEY', '');
if (!defined('EXCH_BITZ_SECRET')) define('EXCH_BITZ_SECRET', '');
if (!defined('EXCH_BITZ_TRADEPWD')) define('EXCH_BITZ_TRADEPWD', '');
 class bitz
{
    
    protected $secretKey = EXCH_BITZ_SECRET;
     protected $apiKey = EXCH_BITZ_KEY;
     protected $tradePwd = EXCH_BITZ_TRADEPWD;
     protected $base_url  = 'https://apiv2.bitz.com';
     protected $url = '';
    public function __construct($options = null)
    {
        $this->url = $this->base_url;
        try {
            if (is_array($options))
            {
                foreach ($options as $option => $value)
                {
                    $this->$option = $value;
                }
            }
            else
            {
                return false;
            }
        }
        catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }
     /**
     * Obtain single quoted price data
     * @param symbol  required string
     * @return array
     */
    public function ticker(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Market/ticker?'.http_build_query($sendData);
        return $this->httpRequest($url);
    }
     /**
     * Obtain K-line data
     * @param symbol     required string
     * @param resolution required string
     * @param size       optional int
     * @param to         optional int
     * @return array
     */
    public function kline(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Market/kline?'.http_build_query($sendData);
        return $this->httpRequest($url);
    }
     /**
     * Obtain deep data
     * @param symbol   required  string
     * @return array
     */
    public function depth(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Market/depth?'.http_build_query($sendData);
        return $this->httpRequest($url);
    }
     /**
     * Obtain filled orders
     * @param symbol  required  string
     * @return array
     */
    public function order(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Market/order?'.http_build_query($sendData);
        return $this->httpRequest($url);
    }
     /**
     * Obtain all quoted price data
     * @param symbols  optional  string
     * @return array
     */
    public function tickerall(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Market/tickerall?'.http_build_query($sendData);
        return $this->httpRequest($url);
    }
     /**
     * Obtain trading pairs
     * @param symbols  optional  string
     * @return array
     */
    public function symbolList(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Market/symbolList?'.http_build_query($sendData);
        return $this->httpRequest($url);
    }
     /**
     * Obtain exchange rate for current legal currency
     * @param symbols  optional  string
     * @return array
     */
    public function currencyRate(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Market/currencyRate?'.http_build_query($sendData);
        return $this->httpRequest($url);
    }
     /**
     * Obtain exchange rate for digital currency and legal currency
     * @param coins  optional  string
     * @return array
     */
    public function currencyCoinRate(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Market/currencyCoinRate?'.http_build_query($sendData);
        return $this->httpRequest($url);
    }
     /**
     * Obtain exchange rate for corresponding currency
     * @param coins  optional  string
     * @return array
     */
    public function coinRate(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Market/coinRate?'.http_build_query($sendData);
        return $this->httpRequest($url);
    }
     /**
     * Obtain personal asset info
     * @return array
     */
    public function getUserAssets(){
        $sendData  = $this->getData();
        $url = $this->url.'/Assets/getUserAssets';
        return $this->httpRequest($url,$sendData);
    }
     /**
     * Submit orders
     * @param symbol   required  eth_btc
     * @param number   required  float
     * @param price    required  float
     * @param type     required  int
     * @return array
     */
    public function addEntrustSheet(array $parms)
    {
        $parms['tradePwd'] = $this->tradePwd;
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Trade/addEntrustSheet';
        return $this->httpRequest($url,$sendData);
    }
     /**
     * Cancel single order
     * @param entrustSheetId  required  string 787945820
     * @return array
     */
    public function cancelEntrustSheet(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Trade/cancelEntrustSheet';
        return $this->httpRequest($url,$sendData);
    }
     /**
     * Cancel batch orders
     * @param ids  required  string '787945739,787945820'
     * @return array
     */
    public function cancelAllEntrustSheet(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Trade/cancelAllEntrustSheet';
        return $this->httpRequest($url,$sendData);
    }
     /**
     * Obtain order details
     * @param entrustSheetId required  string 787945820
     * @return array
     */
    public function getEntrustSheetInfo(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Trade/getEntrustSheetInfo';
        return $this->httpRequest($url,$sendData);
    }
     /**
     * Obtain current order details
     * @param pageSize   optional  int
     * @param page       optional  int
     * @param coinForm   required  eth
     * @param coinTo     required  btc
     * @param startTime  optional  timestamp
     * @param endTime    optional  timestamp
     * @return array
     */
    public function getUserNowEntrustSheet(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Trade/getUserNowEntrustSheet';
        return $this->httpRequest($url,$sendData);
    }
     /**
     * Obtain past order details
     * @param pageSize   optional  int
     * @param page       optional  int
     * @param coinForm   required  eth
     * @param coinTo     required  btc
     * @param startTime  optional  timestamp
     * @param endTime    optional  timestamp
     * @return array
     */
    public function getUserHistoryEntrustSheet(array $parms){
        $sendData  = $this->getData($parms);
        $url = $this->url.'/Trade/getUserHistoryEntrustSheet';
        return $this->httpRequest($url,$sendData);
    }
     protected function getData($data = null){
        $baseArr = array(
            'timeStamp'   =>  time(),
            'nonce'          =>  $this->getRandomString(6),
            'apiKey'      =>  $this->apiKey,
        );
        if(isset($data)){
            $sendData = array_merge($baseArr,$data);
        }else{
            $sendData = $baseArr;
        }
        $sendData['sign'] = $this->getSign($sendData);
        return $sendData;
    }
     protected function getSign($data)
    {
        ksort($data);
        $dataStr = '';
        foreach ($data as $k => $v)
        {
            $dataStr .= $k.'='.$v."&";
        }
         return md5(trim($dataStr,"&").$this->secretKey);
    }
     protected function getRandomString($len, $chars=null)
    {
        if (is_null($chars)){
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        }
         for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < $len; $i++){
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }
     protected function httpRequest($url,$data = null){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if(!empty($data)){
            curl_setopt($curl,CURLOPT_POST,1);
            curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
}
