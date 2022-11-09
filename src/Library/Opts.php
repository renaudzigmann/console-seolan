<?php
namespace Seolan\Library;
/// Gestion des options dans la table opts
class Opts {
  /// rechercher une option
  static function getOpt($user,$moid,$dtype) {
    $lang=\Seolan\Core\Shell::getLangData();
    $t = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=OPTS');
    if(!$t->isTranslatable()) $lang = TZR_DEFAULT_LANG;
    $ors=getDB()->fetchRow('SELECT * FROM OPTS WHERE user=? AND modid=? AND dtype=? AND LANG=?',array($user, $moid, $dtype, $lang));
    if($ors) {
      $opts = static::decodeSpecs($ors['specs']);
    } else {
      $opts=array();
    }
    // en cas de ...
    if (!is_array($opts)){
      $opts = [$opts];
    }
    return $opts;
  }
  /// encapsulation de la serialization
  static public function decodeSpecs($specs){
    if(substr($specs,0,5)=='<?xml') {
      return '';
    }
    if(substr($specs, 0, 2)=='a:' || substr($specs, 0, 2)=='s:') {
      return unserialize(stripslashes($specs));
    }
    $opts = json_decode($specs, true);
    if($opts!==null) {
      return $opts;
    }
    return $specs;
  }
  static public function encodeSpecs($specs){
    return json_encode($specs);
  }
  /// positionnement d'une option
  function setOpt($user,$moid,$dtype,$content) {
    $lang=\Seolan\Core\Shell::getLangData();
    $t = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=OPTS');
    if(!$t->isTranslatable()) $lang = TZR_DEFAULT_LANG;
    $ors=getDB()->fetchRow('SELECT * FROM OPTS WHERE user=? AND modid=? AND dtype=? AND LANG=?',array($user, $moid, $dtype, $lang));
    $specs=static::encodeSpecs($content);
    if($ors) {
      $t->procEdit(array('oid'=>$ors['KOID'],
			 '_options'=>['local'=>1],
			 'user'=>$user, 'modid'=>$moid, 'dtype'=>$dtype, 'specs'=>$specs));
    } else {
      $t->procInput(array('_options'=>['local'=>1],
			  'user'=>$user, 'modid'=>$moid, 'dtype'=>$dtype, 'specs'=>$specs));
    }
  }

  /// positionnement d'une sous option
  function setSubOpt($user,$moid,$dtype,$ssopt,$content) {
    $opts=\Seolan\Library\Opts::getOpt($user,$moid,$dtype);
    $opts[$ssopt]=$content;
    \Seolan\Library\Opts::setOpt($user,$moid,$dtype,$opts);
  }

  /// effacement d'une sous option
  function unsetSubOpt($user, $moid, $dtype, $ssopt) {
    $opts=\Seolan\Library\Opts::getOpt($user, $moid, $dtype);
    unset($opts[$ssopt]);
    \Seolan\Library\Opts::setOpt($user, $moid, $dtype, $opts);
  }

}
?>
