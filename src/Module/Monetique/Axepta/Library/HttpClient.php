<?php
namespace Seolan\Module\Monetique\Axepta\Library;
/**
 * Curl Http Client
 * json encoding
 */

class HttpClient {
  public $className = 'HttpClient';
  
  public $error_codes = array(
    1 => 'CURLE_UNSUPPORTED_PROTOCOL', 
    2 => 'CURLE_FAILED_INIT', 
    3 => 'CURLE_URL_MALFORMAT', 
    4 => 'CURLE_URL_MALFORMAT_USER', 
    5 => 'CURLE_COULDNT_RESOLVE_PROXY', 
    6 => 'CURLE_COULDNT_RESOLVE_HOST', 
    7 => 'CURLE_COULDNT_CONNECT', 
    8 => 'CURLE_FTP_WEIRD_SERVER_REPLY',
    9 => 'CURLE_REMOTE_ACCESS_DENIED',
    11 => 'CURLE_FTP_WEIRD_PASS_REPLY',
    13 => 'CURLE_FTP_WEIRD_PASV_REPLY',
    14 => 'CURLE_FTP_WEIRD_227_FORMAT',
    15 => 'CURLE_FTP_CANT_GET_HOST',
    17 => 'CURLE_FTP_COULDNT_SET_TYPE',
    18 => 'CURLE_PARTIAL_FILE',
    19 => 'CURLE_FTP_COULDNT_RETR_FILE',
    21 => 'CURLE_QUOTE_ERROR',
    22 => 'CURLE_HTTP_RETURNED_ERROR',
    23 => 'CURLE_WRITE_ERROR',
    25 => 'CURLE_UPLOAD_FAILED',
    26 => 'CURLE_READ_ERROR',
    27 => 'CURLE_OUT_OF_MEMORY',
    28 => 'CURLE_OPERATION_TIMEDOUT',
    //...
    52 => "The server didn't reply anything. (HTTP/HTTPS?)",
  );
  protected $currentError = ['type'=>'', 'error_code'=>'', 'error_msg'=>''];
  
  private $debug = 0;
  private $retries = 0;
    
  function __construct($ar) {
    if (!empty($ar['debug'])) $this->debug = $ar['debug'];
    if (!empty($ar['retries'])) $this->retries = $ar['retries'];
  }
  
  function getCurrentError() {
    return $this->currentError;
  }
  
  function get($url, $params='', $options=null, $returnHttpCode=false, $postBody=false) {
    if ($params && !$postBody)
      $url = $url .'?'.$this->urlEncodeParams($params);
    $c = $this->init($url);
    foreach($options as $opt => $val) {
      curl_setopt($c, $opt, $val);
    }
    if ($params && $postBody) {
      curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'GET');
      curl_setopt($c, CURLOPT_POSTFIELDS, $this->urlEncodeParams($params));
    }
    for ($retry=0; $retry <= $this->retries; $retry++) {
      $data = curl_exec($c);
      if (curl_errno($c) != 28) break;
      if ($this->retries > 0) sleep(rand(1,10));
    }
    $curlError = curl_errno($c);
    if ($curlError) {
      \Seolan\Core\Logs::critical(__METHOD__.' error:'.$curlError.'='.$this->error_codes[$curlError]);
      \Seolan\Core\Logs::critical(__METHOD__.' request='.$url);
      $this->currentError = ['type'=>'CURL', 'error_code'=>$curlError, 'error_msg'=>$this->error_codes[$curlError]];
      curl_close($c);
      return -1;
    }
    
    $http_code = curl_getinfo($c)['http_code'];
    $ret = [];
    if (!$returnHttpCode && strpos("$http_code", '2')!==0) {
      //ENTREPYR
      $this->currentError = ['type'=>'HTTP', 'error_code'=>$http_code, 'error_msg'=>''];
      \Seolan\Core\Logs::critical(__METHOD__.' ERROR got http_code='.$http_code);
      \Seolan\Core\Logs::critical(__METHOD__.' curl request ='.print_r(curl_getinfo($c), 1));
      if ($params && $postBody) {
        \Seolan\Core\Logs::critical("curl GET body=".print_r($params, 1));
      }
      \Seolan\Core\Logs::critical(__METHOD__.' curl response ='.print_r($data, 1));
      return -1;
    }    
    elseif($returnHttpCode) {
      //OpenSystem
      $ret['data'] = $data;
      $ret['http_code'] = $http_code;
    } else {
      $ret = $data;
    }
    if ($this->debug) {
      \Seolan\Core\Logs::critical("curl GET request =".print_r(curl_getinfo($c), 1));
      if ($params && $postBody) {
        \Seolan\Core\Logs::critical("curl GET body=".print_r($params, 1));
      }
      \Seolan\Core\Logs::critical("curl GET http code =".$http_code);
      \Seolan\Core\Logs::critical("curl GET response =".print_r($ret, 1));
    }
    curl_close($c);
    return $ret;
  }
  
  // Content Type is set automatically by Curl if CURLOPT_POSTFIELDS is an array
  // preferred method is url encoded post data since it reduces content-size
  //   => 'Content-Type= application/x-www-form-urlencoded'
  // if data is posted raw
  //   => Content-Type= multipart/form-data;
  //   with boundary string and Expect: 100-continue
  // Alternatively Content-Type can be set via Options
  function post($url, $params, $urlEncodeData=true, $options=null, $returnHttpCode=false) {
    $c = $this->init($url);
    if ( !($options && in_array(CURLOPT_POST, array_keys($options))) )
      curl_setopt($c, CURLOPT_CUSTOMREQUEST, "POST");
    foreach($options as $opt => $val) {
      curl_setopt($c, $opt, $val);
    }
    if ($urlEncodeData)
      curl_setopt($c, CURLOPT_POSTFIELDS, $this->urlEncodeParams($params));
    else
      curl_setopt($c, CURLOPT_POSTFIELDS, $params);
    for ($retry=0; $retry <= $this->retries; $retry++) {
      $data = curl_exec($c);
      if (curl_errno($c) != 28) break;
      if ($this->retries > 0) sleep(rand(1,10));
    }
    $curlError = curl_errno($c);
    if ($curlError) {
      \Seolan\Core\Logs::critical(__METHOD__.' error:'.$curlError.'='.$this->error_codes[$curlError]);
      \Seolan\Core\Logs::critical(__METHOD__.' curl POST request='.print_r(curl_getinfo($c),1));
      \Seolan\Core\Logs::critical(__METHOD__.' curl POST data ='.print_r($params, 1));
      $this->currentError = ['type'=>'CURL', 'error_code'=>$curlError, 'error_msg'=>$this->error_codes[$curlError]];
      curl_close($c);
      return -1;
    }
    
    $http_code = curl_getinfo($c)['http_code'];
    $ret = [];
     if (!$returnHttpCode && strpos("$http_code", '2')!==0) {
      //ENTREPYR
      $this->currentError = ['type'=>'HTTP', 'error_code'=>$http_code, 'error_msg'=>''];
      \Seolan\Core\Logs::critical(__METHOD__.' ERROR got http_code='.$http_code);
      \Seolan\Core\Logs::critical(__METHOD__.' curl POST request ='.print_r(curl_getinfo($c),1));
      \Seolan\Core\Logs::critical(__METHOD__.' curl POST data ='.print_r($params,1));
      \Seolan\Core\Logs::critical(__METHOD__.' curl POST response ='.print_r($data,1));
      return -1;
    }    
    elseif($returnHttpCode) {
      //OpenSystem
      $ret['data'] = $data;
      $ret['http_code'] = $http_code;
    } else {
      $ret = $data;
    }
    if ($this->debug) {
      \Seolan\Core\Logs::critical("curl POST request =".print_r(curl_getinfo($c),1));
      \Seolan\Core\Logs::critical("curl POST data =".print_r($params,1));
      \Seolan\Core\Logs::critical("curl POST http code =".$http_code);
      \Seolan\Core\Logs::critical("curl POST response =".print_r($ret,1));
    }
    curl_close($c);
    return $ret;
  }
  
  private function urlEncodeParams($params) {
    // http_build_query($params, numeric_prefix, arg_separator)
    // &amp; is default separator in php 5.3/environment => specify &
    return http_build_query($params, '', '&');
  }
  
  private function init($url) {
    $c = curl_init($url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_TIMEOUT, 5);
    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 5);
    if ($this->debug) {
      curl_setopt($c, CURLINFO_HEADER_OUT,1);
      curl_setopt($c, CURLOPT_VERBOSE,1);
    }
    /*
    if (defined('PREPROD') && PREPROD) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //avoid error 52 on http server (through ajax request)
    }
    */
    return $c;
  }
}
  
  
