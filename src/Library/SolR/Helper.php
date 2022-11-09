<?php
namespace Seolan\Library\SolR;
/**
 * fonction de détection des versions V1/V2 pas intéressantes à mettre dans le module
 * ou dans la classe
 */
use \Seolan\Core\Module\Module;
use \Seolan\Core\{DbIni,Ini,Logs};

class Helper  {

  /// vérification module V2 monté
  public static function checkV2ModuleReady(){
    $mod = Module::singletonFactory(XMODSEARCH_TOID);
    if (empty($mod)){
      $mess = "SolR V2 ready, search module not installed";
      Logs::critical(__METHOD__,$mess.' - mail sent to TZR_DEBUG_ADDRESS ('.TZR_DEBUG_ADDRESS.')');
      bugWarning($mess, false, false);
    }
  }
  /// vérification version
  public static function v2CoreReady($core, $port, $host, $user=null){
    $client = SearchV2::getAdminClient($core, $port, $host, $user);
    try{
      // !! le core passé à l'instance n'est pas utilisé par la requête
      $coreAdminQuery = $client->createCoreAdmin();
      $statusAction = $coreAdminQuery->createStatus();
      $statusAction->setCore($core);
      $coreAdminQuery->setAction($statusAction);
      $response = $client->coreAdmin($coreAdminQuery);
      $statusResult = $response->getStatusResult();
      $rescore = $statusResult->getCoreName();
      if ($rescore != $core)
	return false;
    }catch(\Throwable $t){
      Logs::critical(__METHOD__,$t->getMessage());
      return false;
    }
    return true;
  }
  /**
   * check de la version disponible sur le serveur
   * si détecté une fois, on ne checke plus rien
   * -> recherche d'un serveur solr
   * -> ping
   * -> tentative de connexion
   */
  public static function checkVersion(){

    try{
      $conf = SearchV2::getSolrConfiguration(false);
      if ($conf != null  && !$conf->active)
	return;
    } catch(\Exception $e){
      $conf = null;
    }
    if (!$conf){
      $host = static::solrIp(); 
      $port = '8983';
      $scheme = 'https';
    } else {
      $host = $conf->host;
      $port = $conf->port;
      $scheme = $conf->scheme;
    } 
    
    if (empty($host)){
      Logs::debug(__METHOD__." store solr_v2_ready to 0, no host");
      DbIni::setStatic('solr_v2_ready', '0');
      return;
    }
    
    // ping 
    if (!static::pinghost($host, $port)){
      Logs::debug(__METHOD__." store solr_v2_ready to 0, no ping");
      DbIni::setStatic('solr_v2_ready', '0');
    }

    $context = null;

    list($admin, $passwd) = SearchV2::adminCredentials();
    // 1 solr tourne en v8 ou plus (url utilisée par l'interface solr) 
    $context = stream_context_create();
    stream_context_set_option($context,'ssl','verify_peer',true);
    stream_context_set_option($context,'ssl','allow_self_signed',true);

    // avant passagee solr8, erreur sur file_get_contents = normale
    $res = @file_get_contents(
      $scheme."://$admin:$passwd@{$host}:{$port}/solr/admin/info/system?_=".http_build_query(['_'=>time(),
									       'wt'=>'json']),
      false,
      $context);
    
    $lasterr = error_get_last();

    if (empty($res) || !$res){

      Logs::debug(__METHOD__." error accessing solrv8 server {$lasterr['message']}");

      Logs::debug(__METHOD__."store solr_v2_ready to 0, no login '{$admin}', '{$passwd}'");

      DbIni::setStatic('solr_v2_ready', '0');

      return;

    }

    $json = json_decode($res,true);
    $version = $json['lucene']['solr-spec-version'];
    if (!version_compare($version, '8.11.0', '>=')){
      Logs::debug(__METHOD__."store solr_v2_ready to 0");
      DbIni::setStatic('solr_v2_ready', '0');
      return;
    }

    Logs::debug(__METHOD__."store solr_v2_ready to 1 / OK");

    DBIni::setStatic('solr_v2_ready', '1');

  }
  /**
   * essai de connextion au serveur passé en paramètre
   */
  public static function pinghost($host, $port='8983', $timeout=360){
    
    $f = fSockOpen($host, $port, $errno, $errstr, $timeout);
    if (!$f){
      Logs::debug(__METHOD__." {$errno} {$errstr}");
      return false;
    }
    fclose($f);
    return true;

  }
  /**
   * recherche de l'ip d'un éventuel serveur solr
   */
  public static function solrIp(){
    exec('cat /etc/network/interfaces.d/solr 2> /dev/null', $lines);
    $solrip = null;
    foreach($lines as $line){
      if (preg_match('/address (127\.[\d]{1,3}\.[\d]{1,3}\.[\d]{1,3})/', $line, $res))
	$solrip = $res[1];
    }
    return $solrip;
  }


}
