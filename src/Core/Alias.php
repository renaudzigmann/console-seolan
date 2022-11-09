<?php
namespace Seolan\Core;
/// classe de gestion des alias, y compris alias entre modules
class Alias {
  static $_aliases=Array();
  static $_oaliases=Array();
  static $_singleton=NULL;
  static $_mods=array();
  static $_initialized=false;

  function __construct() {
    \Seolan\Core\Alias::$_singleton=$this;
  }

  /// Rend le moid du module dans lequel l'alias est defini, NULL sinon
  static function checkAlias($alias) {
    $alias=trim($alias);
    if(!self::initialized()) self::register(\Seolan\Core\Ini::get('corailv3_xmodinfotree'));

    if(isset(self::$_aliases[$alias])) {
      return self::$_aliases[$alias];
    } else {
      return NULL;
    }
  }

  /// Rend l'alias qui correspond à un oid dans un module
  static function checkRep($moid,$oid) {
    self::register($moid);
    if(isset(self::$_oaliases[$oid])) {
      return self::$_oaliases[$oid];
    } else {
      return NULL;
    }
  }

  /// Charge les alias du module passé en parametre
  static function register($mod) {
    self::$_initialized=true;
    if(!is_object($mod) && self::registered($mod)) {
      return;
    } elseif(!is_object($mod)) {
      $mod=self::getModule($mod);
      if(!$mod) return;
    }
    if(self::registered($mod->_moid)) return;

    $aliases=$mod->getAliases();
    if (! empty($aliases)){
      foreach($aliases as $oid=>$al){
        self::$_aliases[$al] = $mod->_moid;
        self::$_oaliases[$oid] = $al;
      }
      self::$_mods[$mod->_moid]=&$mod;
    }
  }

  /// Retourne un module xmodinfotree
  static function getModule($moid) {
    if (! \Seolan\Core\Module\Module::moduleExists($moid))
      return false;
    $mod = \Seolan\Core\Module\Module::objectFactory($moid);
    if (! is_a($mod, '\Seolan\Module\InfoTree\InfoTree'))
      return false;
    return $mod;
  }

  /// Vérifie si un module a été enregistré
  static function registered($modid) {
    return isset(self::$_mods[$modid]);
  }

  /// Vérifie si XAlaias a été initialisé
  static function initialized(){
    return (!empty(self::$_aliases) || self::$_initialized);
  }

  /// Rend le lien correspondant à un alias (recherche globale dans tous les modules déclarés)
  static function mklink($alias) {
    $modid=self::checkAlias($alias);
    if($modid) {
      if(\Seolan\Core\Shell::admini_mode()) {
	$link=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&amp;class=\Seolan\Module\InfoTree\InfoTree&amp;function=viewpage'.
	  '&amp;template=Module/InfoTree.viewpage.html&amp;moid='.$modid.'&amp;tplentry=it&amp;alias='.$alias;
      } else {
	$link=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&amp;alias='.$alias;
      }
      return $link;
    }
    return $alias;
  }
  
  /// Rend le lien correspondant à un oid dans un module
  static function mklink2($modid, $oid) {
    $alias = self::checkRep($modid, $oid);
    if (\Seolan\Core\Shell::admini_mode()){
      $link = $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . '&amp;class=\Seolan\Module\InfoTree\InfoTree&amp;function=viewpage' . '&amp;template=Module/InfoTree.viewpage.html&amp;moid=' . $modid . '&amp;tplentry=it&amp;alias=' . $alias;
    }else{
      $link = $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . 'alias=' . $alias;
    }
    return $link;
  }

  /// Rend l'oid et le moid d'un alias
  static function getInternalRep($alias,$modid=null) {
    if(!$modid) $modid=self::checkAlias($alias);
    if(!$modid) return $alias;
    $mod=self::getModule($modid);
    if(!$mod) return $alias;
    $oid=\Seolan\Core\Module\Module::objectFactory($modid)->getOidFromAlias($alias);
    return array($modid,$oid);
  }
}
?>
