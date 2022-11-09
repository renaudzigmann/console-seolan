<?php
// stockage en base de donnees dans la table _VARS de variable
// globales a l'instance de l'application. Les valeurs peuvent 
// etre des tableaux ou des objets : elles sont serialisees.

namespace Seolan\Core;

class DbIni {

  private $toclear = array(); // tableau des données à supprimer
  
  function __destruct() {
    self::clearDelayed();
  }
  
  function clearDelayed() {
    // no log pour les requetes : le logger n'existe peut-être plus
    if (!UPDATE_USED_VALUES) {
      foreach ($this->toclear as $spec) {
	$toUpdate = getDB()->fetchAll('SELECT sessid, user, name FROM _VARS WHERE name LIKE ?', array($spec), false);
	foreach ($toUpdate as $line) {
	  getDB()->execute('UPDATE _VARS SET active=0, UPD=NOW() WHERE (sessid is null or sessid=?) AND (user is null or user=?) AND name=?', array($line['sessid'], $line['user'], $line['name']), false, false, false);
	}
      }
      return;
    }
    foreach ($this->toclear as $spec) {
      if (false === strpos($spec, 'usedValues')) {
	$toUpdate = getDB()->fetchAll('SELECT sessid, user, name FROM _VARS WHERE name LIKE ?', array($spec), false);
	foreach ($toUpdate as $line) {
	  getDB()->execute('UPDATE _VARS SET active=0, UPD=NOW() WHERE (sessid is null or sessid=?) AND (user is null or user=?) AND name=?',array($line['sessid'], $line['user'], $line['name']), false, false, false);
	}
        continue;
      }

      // pour les usedValues, on les marque "mettre à jour"
      $usedValues = getDB()->select('SELECT name, value FROM _VARS WHERE name like ? and sessid  is null', [$spec], null, null, false)->fetchAll(\PDO::FETCH_COLUMN | \PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
      foreach ($usedValues as $name => $value) {
        $value = unserialize($value);
        $value['toupdate'] = true;
        $value = serialize($value);
        getDB()->execute('UPDATE _VARS set UPD=UPD, value=? where name=?', [$value, $name], false, false, false);
	unset($value);
      }
    }
    $this->toclear=[];
  }

  // mise en jour
  static function updateUsedValues() {
    if(!isset($GLOBALS['XDBINI'])) {
      $GLOBALS['XDBINI']=new \Seolan\Core\DbIni();
    }
    if (!defined('UPDATE_USED_VALUES') || !UPDATE_USED_VALUES) {
      $todel = getDB()->fetchAll('SELECT sessid, user, name FROM _VARS WHERE active!=1 and UPD < DATE_ADD(NOW(), INTERVAL 1 DAY)');
      foreach($todel as $line) {
	getDB()->execute('DELETE FROM _VARS WHERE (sessid is null or sessid=?) AND (user is null or user=?)  AND name=?', [$line['sessid'], $line['user'], $line['name']], false, false, false);
      }
      unset($todel);
      return;
    }
    if (!($lock = \Seolan\Library\Lock::getLock('updateUsedValues'))) {
      return;
    }
    if (defined('USED_VALUES_STORAGE_TIME')) {
      $todel = getDB()->fetchAll("SELECT sessid, user, name FROM _VARS WHERE UPD < ?", [date('Y-m-d', strtotime('- ' . USED_VALUES_STORAGE_TIME))]);
      foreach($todel as $line) {
	getDB()->execute("DELETE FROM _VARS WHERE (sessid is null or sessid=?) AND (user is null or user=?)  AND name=?", [$line["sessid"], $line["user"], $line["name"]], false, false, false);
      }
      unset($todel);
    }
    $lastUpdate = \Seolan\Core\DbIni::get('refreshUpdateValue', 'val');
    \Seolan\Core\DbIni::set('refreshUpdateValue', date('Y-m-d H:i:s'));
    if (empty($lastUpdate)) {
      $lastUpdate = date('Y-m-d H:i:s', strtotime('- 1 days'));
    }
    $vars = getDB()->fetchAll('select * from _VARS where name like "%usedValues%" and value like "%toupdate%"');
    foreach ($vars as $var) {
      $value = unserialize($var['value']);
      if (empty($value['s'])) {
        self::clear($var['name']);
        continue;
      }
      $value['v'] = \Seolan\Core\Field\Field::_getUsedValuesFromDB($value['s'], $value['multivalued'], $value['separator']);
      unset($value['toupdate']);
      getDB()->execute('UPDATE _VARS SET UPD=UPD, value=? where name=?', [serialize($value), $var['name']], false);
    }
    \Seolan\Library\Lock::releaseLock($lock);
  }

  /// rend la variable stockée en base et sa date de mise a jour
  static function get($varname,$type="full") {
    if(!isset($GLOBALS['XDBINI'])) {
      $GLOBALS['XDBINI']=new \Seolan\Core\DbIni();
    }
    if(isset($GLOBALS['XDBINI']->cache[$varname])) {
      if($type=="val")
	return @$GLOBALS['XDBINI']->cache[$varname][0];
      else
	return $GLOBALS['XDBINI']->cache[$varname];
    }
    if (isset($GLOBALS['XDBINI']->toclear[$varname]))
      return NULL;
    
    $ors=getDB()->fetchRow('SELECT * FROM _VARS WHERE name=? and active=1 and sessid is null',array($varname));
    if($ors){
      $val = @unserialize(stripslashes($ors['value']));
      $GLOBALS['XDBINI']->cache[$varname]=array($val,$ors['UPD']);
      if($type=="val") return $val;
      return array($val,$ors['UPD']);
    } else {
      $GLOBALS['XDBINI']->cache[$varname]=array();
      return NULL;
    }
  }
  
  /// incrément de la variable stockée $varname
  static function inc($varname) {
    if(!isset($GLOBALS['XDBINI'])) {
      $GLOBALS['XDBINI']=new \Seolan\Core\DbIni();
    }
    $ors=getDB()->fetchRow('SELECT * FROM _VARS WHERE name = ? and sessid is null',array($varname));
    if(!$ors) {
      $val=1;
      getDB()->execute('INSERT INTO _VARS SET UPD=NULL, name=?, active=1, value=?',array($varname,serialize($val)),false);
    } else {
      if($ors['active']!=1) {
	$val=1;
	getDB()->execute('UPDATE _VARS SET UPD=NULL, value=? where name=?',array(serialize($val),$varname),false);
      } else {
	$val = unserialize(stripslashes($ors['value']));
	$val++;
	getDB()->execute('UPDATE _VARS SET UPD=NULL, value=? where name=?',array(serialize($val),$varname),false);
      }
    }
    unset($GLOBALS['XDBINI']->cache[$varname]);
  }

  static function isUptodate($varname,$timeout) {
    if(!isset($GLOBALS['XDBINI'])) {
      $GLOBALS['XDBINI']=new \Seolan\Core\DbIni();
    }
    if(isset($GLOBALS['XDBINI']->cache[$varname])) {
      if($type=="val")
	return @$GLOBALS['XDBINI']->cache[$varname][0];
      else
	return $GLOBALS['XDBINI']->cache[$varname];
    }
    $ors=getDB()->fetchRow("SELECT * FROM _VARS WHERE name = ? and active=1 and sessid is null",array($varname));
    if($ors) {
      $val = unserialize(stripslashes($ors['value']));
      $GLOBALS['XDBINI']->cache[$varname]=array($val,$ors['UPD']);
      if($type=="val") return $val;
      return array($val,$ors['UPD']);
    } else {
      return false;
    }
  }
  static function set($varname,$value) {
    if(!isset($GLOBALS['XDBINI'])) {
      $GLOBALS['XDBINI']=new \Seolan\Core\DbIni();
    }
    unset($GLOBALS['XDBINI']->toclear[$varname]);
    $cnt=getDB()->count('SELECT count(*) FROM _VARS WHERE name = ? and sessid is null',array($varname));
    if($cnt<=0) {
      getDB()->execute('INSERT INTO _VARS SET UPD=NULL, active=1, name=?, value=?',array($varname,serialize($value)),false);
    }else{
      getDB()->execute('UPDATE _VARS SET UPD=NULL, value=?, active=1 where name=?',array(serialize($value),$varname),false);
    }
    unset($GLOBALS['XDBINI']->cache[$varname]);
  }

  static function clear($spec, $delayed=true) {
    if(!isset($GLOBALS['XDBINI'])) {
      $GLOBALS['XDBINI']=new \Seolan\Core\DbIni();
    }
    if (isset($GLOBALS['XDBINI']->cache[$spec])){
      unset($GLOBALS['XDBINI']->cache[$spec]);
    }
    if ($delayed){
      $GLOBALS['XDBINI']->toclear[$spec] = $spec;
    } else {
      getDB()->execute("UPDATE _VARS SET active=0 WHERE name LIKE ?",array($spec),false);
    }
  }

  static function clearForUser($uoid) {
    if(!isset($GLOBALS['XDBINI'])) {
      $GLOBALS['XDBINI']=new \Seolan\Core\DbIni();
    }
    getDB()->execute("UPDATE _VARS SET active=0 WHERE user=?",array($uoid),false);
  }

  static function getStatic($varname,$type="full", $default=NULL) {
    $ors=getDB()->fetchRow("SELECT * FROM _STATICVARS WHERE name = ?",array($varname));
    if($ors) {
      if($type=='raw') return $ors['value'];
      $val=@unserialize($ors['value']);
      if($val===false) $val=$ors['value'];
      if($type=='val') return $val;
      return array($val,$ors['UPD']);
    } else {
      return $default;
    }
  }
  static function setStatic($varname,$value) {
    if(is_array($value) || is_object($value)) $value=serialize($value);
    $cnt=getDB()->count("SELECT COUNT(*) FROM _STATICVARS WHERE name = ?",array($varname));
    if($cnt) {
      getDB()->execute("UPDATE _STATICVARS SET UPD=NULL, value=? where name=?",array($value, $varname),false);
    } else {
      getDB()->execute("INSERT INTO _STATICVARS (UPD,value,name) values (NULL,?,?)",array($value, $varname),false);
    }
  }
  static function clearStatic($spec) {
    getDB()->execute("DELETE FROM _STATICVARS WHERE name LIKE ?",array($spec),false);
  }
}
