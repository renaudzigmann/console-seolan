<?php
namespace Seolan\Core;
/**
 * catalogue d'objets de configuration
 * -> chargement global + surcharges locales
 */
class Config{
  private static $configs=array();

  static function load($name,$allowModifications=false,$refresh=false){
    if(!$refresh && isset(self::$configs[$name])) return self::$configs[$name];
    $local=$seolan=false;
    $file='config/'.$name.'.php';
    self::$configs[$name]=new \Zend\Config\Config(array(),$allowModifications);
    if(file_exists($GLOBALS['LIBTHEZORRO'].$file)){
      $seolan = true;
      self::$configs[$name]->merge(new \Zend\Config\Config(include($GLOBALS['LIBTHEZORRO'].$file),$allowModifications));
    }
    if(file_exists($GLOBALS['LOCALLIBTHEZORRO'].$file)){
      $local = true;
      self::$configs[$name]->merge(new \Zend\Config\Config(include($GLOBALS['LOCALLIBTHEZORRO'].$file),$allowModifications));
    }
    if (!$local && !$seolan)
      Logs::critical(__METHOD__,"required conf file '$name' does not exists in '{$GLOBALS['LIBTHEZORRO']}' nor in '{$GLOBALS['LOCALLIBTHEZORRO']}'");
    return self::$configs[$name];
  }

  static function get($name){
    return self::$configs[$name];
  }
  
}
