<?php
namespace Seolan\Library;
/**
 * Classe centralisée pour la gestion du cache mémoire
 **/
class ProcessCache{
  private static $cache=NULL;
  private static $path_cache=array();
  private static $empty_key='__empty__';
  private static $activated=true;
  private static $memcached=NULL;
  
  
  /// si parametre = vrai, active le cache, sinon le desactive. Rend l'etat précedent
  public static function setStatus($activated) {
    $status=self::$activated;
    self::$activated=$activated;
    return $status;
  }
  /// Retourne le cache brut
  public static function debugGetCache(){
    return static::$cache;
  }
  /// Active le cache, rend l'état précédent d'activation du cache
  public static function activate(){
    $status = self::$activated;
    self::$activated=true;
    return $status;
  }
  
  /// Désaccive le cache, rend l'état précédent d'activation du cache
  public static function deactivate(){
    $status = self::$activated;
    self::$activated=false;
    return $status;
  }
  
  /// Recupere un cache à partir du chemin (et le créé si $create vaut true et qu'il n'existe pas)
  public static function getCacheFromPath($path,$create=true){
    if(!self::$activated) return false;
    if(!isset(self::$path_cache[$path])){
      if(self::$cache===NULL) self::$cache=new \StdClass();
      $list=explode('/',$path);
      $cache=self::$cache;
      foreach($list as $n){
        if(!isset($cache->$n)){
	  if(!isset($n)) throw new \Exception('\Seolan\Library\ProcessCache path '.$path.' contains empty level');
          if($create) $cache->$n=new \StdClass();
          else return false;
        }
        $cache=$cache->$n;
      }
      self::$path_cache[$path]=$cache;
    }
    return self::$path_cache[$path];
  }

  // Ajoute une variable dans le cache
  // $name pourrait etre dans le path mais en cas de présence d'un slash dans $name, il y aurait problème
  static function set($path,$name,&$value){
    if(!self::$activated) return false;
    static $cache_size=0;
    $cache_size++;
    if($cache_size>TZR_MEMCACHE_MAXSIZE) {
      \Seolan\Core\Logs::debug('\Seolan\Library\ProcessCache: empty cache');
      self::$cache=NULL;
      self::$path_cache=array();
      $cache_size=0;
    }

    $cache=self::getCacheFromPath($path);
    if(!$name) $name=self::$empty_key;
    $cache->$name=&$value;
  }

  // Recupere une variable dans le cache
  // $name pourrait etre dans le path mais en cas de présence d'un slash dans $name, il y aurait problème
  static function &get($path,$name){
    static $null=null;

    if(!self::$activated) return $null;
    if(!($cache=self::getCacheFromPath($path,false))) return $cache;
    if(!$name) $name=self::$empty_key;
    return $cache->$name;
  }

  // Efface une variable du cache
  // $name pourrait etre dans le path mais en cas de présence d'un slash dans $name, il y aurait problème
  static function delete($path,$name=NULL){
    $list=explode('/',$path);
    $last=array_pop($list);
    $path=implode('/',$list);
    if(!($cache=self::getCacheFromPath($path,false))) return;
    if($name) unset($cache->$last->$name);
    else unset($cache->$last);
  }

  // Génère un hash unique à partir d'un tableau d'options
  static function generateHash(&$options){
    \Seolan\Core\Audit::plusplus('xmemcache_generateHash');
    //$start=microtime(true);
    if(empty($options)) return 'default';
    if(is_array($options)) ksort($options,SORT_STRING);
    $h=crc32(json_encode($options));
    //$end=microtime(true);
    //\Seolan\Core\Audit::plus('xmemcache_generateHash_time',$end-$start);
    return $h;
  }
  
  // Récupère l'objet memcached
  // Renvoie false si pas de memcached configuré
  static function getMemcached(){
    if(self::$memcached===NULL){
      if(!empty($GLOBALS['MEMCACHED_SERVER'])){
        self::$memcached=new Memcached();
        self::$memcached->addServer($GLOBALS['MEMCACHED_SERVER'], $GLOBALS['MEMCACHED_PORT']);
      }else{
        self::$memcached=false;
      }
    }
    return self::$memcached;
  }

  // Lit une variable dans memcached
  static function getFromMemcached($name){
    if(!self::$activated) return false;
    if(!self::getMemcached()) return false;
    $name=$GLOBALS['DATABASE_NAME'].'-'.$name;
    \Seolan\Core\Logs::debug('\Seolan\Library\ProcessCache::getFromMemcached '.$name);
    return self::$memcached->get($name);
  }

  // Ecrit une variable dans memcached
  static function setToMemcached($name, $value, $expiration=NULL){
    if(!self::$activated) return false;
    if(!self::getMemcached()) return false;
    if($expiration===NULL) $expiration=TZR_MEMCACHED_DEFAULT_EXPIRATION;
    $name=$GLOBALS['DATABASE_NAME'].'-'.$name;
    \Seolan\Core\Logs::debug('\Seolan\Library\ProcessCache::setToMemcached '.$name);
    return self::$memcached->set($name, $value, $expiration);
  }

  // Efface une variable du memcached
  static function deleteFromMemcached($name){
    if(!self::getMemcached()) return false;
    $name=$GLOBALS['DATABASE_NAME'].'-'.$name;
    \Seolan\Core\Logs::debug('\Seolan\Library\ProcessCache::deleteFromMemcached '.$name);
    return self::$memcached->delete($name);
  }

  // Efface toutes les variables de memcached
  static function flushMemcached($name){
    if(!self::getMemcached()) return false;
    $name=$GLOBALS['DATABASE_NAME'].'-';
    \Seolan\Core\Logs::debug('\Seolan\Library\ProcessCache::flushMemcached');
    $keys=self::$memcached->getAllKeys();
    $todelete=array();
    foreach($keys as $key){
      if(strpos($key,$name)===0){
        $todelete[]=$key;
      }
    }
    self::$memcached->deleteMulti($todelete);
    return true;
  }
}
