<?php
/****
 * NAME
 *   \Seolan\Module\Moodle\Moodle -- gestion des mappings seolan/moodle
 * DESCRIPTION
 *   Stockage des liens seolan/moodle avec mise à jour auto de moodle depuis seolan.
 *   Synchro des users moodle par rapport aux users seolan 
 * SYNOPSIS
 *   La creation d'un module est realisee par utilisation de la methode de classe \Seolan\Core\Module\Module::objectFactory.
 * PARAMETERS
 ****/

namespace Seolan\Module\Moodle;
class Moodle extends \Seolan\Module\Table\Table {

  /// update (or create) moodle user from seolan user
  function updateMoodleUser($oid, $login) {

    $suser = getDB()->fetchRow('select * from USERS where KOID=?', array($oid));

    $rs = getDB()->fetchAll('select * from '.$this->table.' where lang=? and seolan_table=?', array(\Seolan\Core\Shell::getLangUser(), 'USERS'));
    foreach($rs as $ors) {
      if ($ors['pkey'] == 1) {
        $mkey = $ors['moodle_field'];
        $skey = $ors['seolan_field'];
        $mtable = $ors['moodle_table'];
      }
    }
    
    $muser = getDB()->fetchOne('select id from '.$mtable.' where '.$mkey.'=?', array($login));

    if ($muser) { // update user
      $query = 'update '.$mtable.' set ';
      $sep = '';
      $values = array();
      foreach($rs as $ors) {
        $query .= $sep.$ors['moodle_field'].'=?';
        if ($ors['tfunc']) { // apply transformation function
          $func = $ors['tfunc'];
          $values[] = $this->$func($suser[$ors['seolan_field']]);
        } else {
          $values[] = $suser[$ors['seolan_field']];
        }
        $sep = ',';
      }
      $values[] = $muser;
      $query .= ' where id=?';
      $res = getDB()->execute($query ,$values);
      $muser = getDB()->fetchRow('select id from '.$mtable.' where '.$mkey.'=?', array($suser[$skey]));
    } else { // insert user
      $query = 'insert into '.$mtable.' (';
      $sep = '';
      $values = array();
      foreach($rs as $ors) {
        $query .= $sep.$ors['moodle_field'];
        $valstr .= $sep.'?';
        if ($ors['tfunc']) { // apply transformation function
          $func = $ors['tfunc'];
          $values[] = $this->$func($suser[$ors['seolan_field']]);
        } else {
          $values[] = $suser[$ors['seolan_field']];
        }
        $sep = ',';
      }
      $query .= ') values ('.$valstr.')';
      $res = getDB()->execute($query ,$values);
      $muser = getDB()->fetchOne('select id from '.$mtable.' where '.$mkey.'=?', array($suser[$skey]));
    }
    return $muser;
  }

    /// delete moodle user by seolan user login
  function deleteMoodleUser($login) {

    $rs = getDB()->fetchAll('select * from '.$this->table.' where lang=? and seolan_table=?', array(\Seolan\Core\Shell::getLangUser(), 'USERS'));
    foreach($rs as $ors) {
      if ($ors['pkey'] == 1) {
        $mkey = $ors['moodle_field'];
        $skey = $ors['seolan_field'];
        $mtable = $ors['moodle_table'];
      }
    }
    $muser = getDB()->fetchOne('select id from '.$mtable.' where '.$mkey.'=?', array($login));
    
    if ($muser) { // delete user
      $query = 'update '.$mtable.' set deleted=1 where id=?'; 
      $res = getDB()->execute($query ,array($muser));
    }
    return $muser;
  }
  
  // transformation function : lowercase the value
  function lowercase($value) {
    return strtolower($value);
  }
  
  // transformation function : return 'cas' for auth
  function auth_cas($value) {
    return 'cas';
  }
  
  // transformation function : return 'not cached' for password
  function pwd_not_cached($value) {
    return 'not cached';
  }

  // transformation function : return 1 for confirmed
  function confirmed($value) {
    return 1;
  }

}
