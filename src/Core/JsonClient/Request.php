<?php
namespace Seolan\Core\JsonClient;
use \Seolan\Core\JsonClient\Response;

/**
 * encapsulation de requetes json api
 * ici -> curl
 * constantes pour les différents headers utilisés par les 'clients'
 * gestion des headers par defaut (bearer)
 * gestion de la serialization des paramètres selon le cas
 */

class Request {  
  const POST = 'POST';
  const GET = 'GET';
  const PUT = 'PUT';
  const DELETE = 'DELETE';
  const HEADER_BEARER = 'Authorization: Bearer %s';
  const HEADER_JSON = 'Content-Type: application/json';
  const HEADER_WWW_FORM = 'Content-Type: application/x-www-form-urlencoded';
  const HEADER_OCTECT_STREAM = 'Content-Type: application/octet-stream';
  const HEADER_CONTENT_LENGTH = 'Content-Length: %s';
  private $defaultHeaders = null;
  private $debug = false;

  function __construct($defaultHeaders=[], $debug=false){
    $this->defaultHeaders = $defaultHeaders;
    $this->debug = $debug;
  }
  /**
   * execution d'un requete
   * @param $type : POST ...
   * @param $url : le end point
   * @headers : les entêtes de la requête (viennent s'ajouter à ceux par défaut)
   * 
   */
  public function doRequest($type, $url, $params = array(), $headers) {
    
    $headers = array_merge($this->defaultHeaders, $headers);
    
    $paramsRaw = $params;
    if (is_array($params) && in_array(static::HEADER_JSON, $headers)){
      $params = json_encode($params);
    }
    
    \Seolan\Core\Logs::notice(__METHOD__." url: $url");
    \Seolan\Core\Logs::notice(__METHOD__.' headers:'.implode(',', $headers));

    // pourrait y avoir ... si HEADER_WWW faire un string url encoded (http_build_query)
    $s = curl_init();
    curl_setopt($s, CURLOPT_CUSTOMREQUEST, $type);
    switch ($type) {
    case self::DELETE:
      $query = $url;
      if (count($params)) {
	$query .= '?' . http_build_query($params);
      }
      curl_setopt($s, CURLOPT_URL, $query);
      break;
    case self::POST:
      curl_setopt($s, CURLOPT_URL, $url);
      curl_setopt($s, CURLOPT_POST, true);
      curl_setopt($s, CURLOPT_POSTFIELDS, $params);
      break;
    case self::PUT:
      curl_setopt($s, CURLOPT_URL, $url);
      curl_setopt($s, CURLOPT_POST, true);
      curl_setopt($s, CURLOPT_POSTFIELDS, $params);
      break;
    case self::GET:
      $query = $url;
      if (count($params)) {
	$query .= '?' . http_build_query($params);
      }
      curl_setopt($s, CURLOPT_URL, $query);
      break;
    default :
      throw new \Seolan\Core\Exception\Exception("Type non valide");
    }
    curl_setopt($s, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($s, CURLOPT_HEADER, true);
    curl_setopt($s, CURLOPT_VERBOSE, $this->debug);
    curl_setopt($s, CURLOPT_FORBID_REUSE, true);

    $reponseCurl = curl_exec($s);

    $curl_error = curl_error($s);

    if (!empty($curl_error))
        \Seolan\Core\Logs::critical(__METHOD__, $curl_error);
    
    \Seolan\Core\Logs::debug(__METHOD__.$reponseCurl);
    
    // status, headers, content
    $status = curl_getinfo($s, CURLINFO_HTTP_CODE);
    $hsize = curl_getinfo($s, CURLINFO_HEADER_SIZE);

    $responseHeaders = $this->parseHeader(substr($reponseCurl, 0, $hsize));
    
    $data = substr($reponseCurl, $hsize);
    if(json_decode($data) === null) {
      $data = null; 
    }
    curl_close($s);
    return new Response($status,$data,$responseHeaders);
  }
  /**
   * recupération des headers dans un tableau
   */
  private function parseHeader($str) {
    $headers = array();
    $tabTmpHead = substr($str, stripos($str, "\r\n"));
    $tabTmpHead = explode("\r\n", $tabTmpHead);
    foreach ($tabTmpHead as $h) {
      if($h !== '') {
	list($v, $val) = explode(": ", $h);
	if ($v == null) continue;
	$headers[$v] = $val;
      }
    }
    return $headers;
  }
}