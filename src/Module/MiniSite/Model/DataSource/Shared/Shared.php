<?php
/**
 * Class de gestion d'une table partagée entre minisites ( ex templates )
 * propagation des modifs à tout les ms du même modèle
 * les datas sont supposées en liens 
 * ! pas de gestion des modifs/ajouts de champ
 * ! les fiches sont créés dans toutes les langues du site d'origine de la modif, certain ms traduisible
 **/

namespace Seolan\Module\MiniSite\Model\DataSource\Shared;
class Shared extends \Seolan\Model\DataSource\Table\Table {

  function procEdit($ar) {
    $ret = parent::procEdit($ar);
    $this->propagate($ret['oid']);
    return $ret;
  }

  function procInput($ar) {
    $ret = parent::procInput($ar);
    $this->propagate($ret['oid']);
    return $ret;
  }

  function propagate($oids) {
    if (!is_array($oids))
      $oids = array($oids);
    foreach ($this->otherDb() as $db)
      foreach ($oids as $oid)
        getDB()->execute('replace into '.$db.'.'.$this->base.' (select * from '.$this->base." where koid=?)", array($oid));
  }

  // retourne un tableau des autres bases
  function otherDb() {
    if (isset($GLOBALS['MASTER_DB_NAME'])) // depuis un minisite
      $select = 'select db from '.$GLOBALS['MASTER_DB_NAME'].'.VHOSTS where db != "'.$GLOBALS['DATABASE_NAME'].'" and (koid="'.$GLOBALS['VHOST_TEMPLATE_OID'].'" or templat="'.$GLOBALS['VHOST_TEMPLATE_OID'].'")';
    else // sur le master
      $select = 'select db from VHOSTS where templat in (select koid from VHOSTS where vhost="'.$_SERVER['SERVER_NAME'].'")';
    return getDB()->select($select)->fetchAll(\PDO::FETCH_COLUMN);
  }
  
  function del($ar) {
    $ret = parent::del($ar);
    if (!isset($GLOBALS['MASTER_DB_NAME']) || !isset($GLOBALS['VHOST_TEMPLATE_OID']))
      return $ret;
    // report des modifs dans tout les ms du même modèle
    $oids = $ret['oid'];
    if ($oids) {
      $LANG_DATA = \Seolan\Core\Shell::getLangData();
      if (!is_array($oids))
        $oids = array($oids);
      foreach ($this->otherDb() as $db) {
        foreach ($oids as $oid) {
          if($LANG_DATA==TZR_DEFAULT_LANG || !$this->isTranslatable()) {
            getDB()->execute('DELETE FROM '.$db.'.'.$this->base.' where KOID=?', array($oid));
            getDB()->execute('DELETE FROM '.$db.'.ACL4 where AKOID=?', array($oid));
          } else {
            getDB()->execute('DELETE FROM '.$db.'.'.$this->base.' where KOID=? and LANG=?', array($oid, $LANG_DATA));
            getDB()->execute('DELETE FROM '.$db.'.ACL4 where AKOID=? and LANG=?', array($oid, $LANG_DATA));
          }
          getDB()->execute('delete from '.$db.'.'.$this->base." where koid=?", array($oid));
        }
      }
    }
    return $ret;
  }
}