<?php
/**
 * surcharge du client http solarium pour fonctionner en https self signed
 */
namespace Seolan\Library\SolR;
use \Seolan\Core\Logs;
use \Solarium\Core\Client\Adapter\Http;
class SSLClient extends Http {
  public function createContext($request, $endpoint){

    $context = parent::createContext($request, $endpoint);

    stream_context_set_option($context,
			      'ssl',
			      'verify_peer',
			      true);
    stream_context_set_option($context,
			      'ssl',
			      'allow_self_signed',
			      true);
    
    return $context;
  }
  protected function getData($uri, $context){
    if(TZR_DEBUG_MODE==1){
      $url = parse_url($uri);
      $query = parse_str($url['query'],$params);
      Logs::notice(__METHOD__,tzr_var_dump($url));
      Logs::notice(__METHOD__,tzr_var_dump($params));
    }
    return parent::getData($uri, $context);
    
  }
}
