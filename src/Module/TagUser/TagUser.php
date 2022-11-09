<?php
/****
 * NAME
 *   \Seolan\Module\TagUser\TagUser -- gestion d'un dictionnaire de tags
 * DESCRIPTION
 *   Stockage de la relation entre le tag, l'utilisateur et l'objet taggé.
 * SYNOPSIS
 *   La creation d'un module est realisee par utilisation de la methode de classe \Seolan\Core\Module\Module::objectFactory.
 * PARAMETERS
 ****/

namespace Seolan\Module\TagUser;
class TagUser extends \Seolan\Module\Table\Table {

  static function getUserTags($koid, $moid) {
    $mod = \Seolan\Core\Module\Module::objectFactory($moid);
    $query = 'select tag,user from '.$mod->table.' where objoid=?';
    $rs = getDB()->fetchAll($query, array($koid));
    $usertags = array();
    foreach($rs as $ors) {
      $usertags[$ors['tag']] = $ors['user'];
    }
    return $usertags;
  }
  
  static function addUserTag($tag, $tagmoid, $koid, $moid, $table, $field, $userid) {
    $mod = \Seolan\Core\Module\Module::objectFactory($tagmoid);
    $ar = array();
    $ar['table'] = $mod->table;
    $ar['tag'] = $tag;
    $ar['objoid'] = $koid;
    $ar['objmoid'] = $moid;
    $ar['objtable'] = $table;
    $ar['objfield'] = $field;
    $ar['user'] = $userid;
    $res = $mod->procInsert($ar);
    return $res['oid'];
  }
  
  function searchTag($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array());
    $tplentry = $p->get('tplentry');
    $tag = $p->get('tag');
    $moid = $p->get('moid');
    $mod=\Seolan\Core\Module\Module::objectFactory($moid);
    $query = 'select objoid,objmoid,objtable from '.$mod->table.' where tag=? order by objoid';
    $rs = getDB()->fetchAll($query, array($tag));
    $koids = array();
    $res = array();
    $res['tag'] = $tag;
    $cmoid = "";
    $cmod = "";
    $oid = "";

    $LANG_DATA = \Seolan\Core\Shell::getLangData();
    $lang = \Seolan\Core\Shell::getLangUser();

    foreach($rs as $ors) {
      if ($ors['objmoid'] != $cmoid) {
        $cmoid = $ors['objmoid'];
        $cmod = \Seolan\Core\Module\Module::objectFactory(array('moid'=>$cmoid,'tplentry'=>TZR_RETURN_DATA));
        $table = $ors['objtable'];
        $target_ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
      }
      if (!$target_ds) continue; // deleted datasource
      $oid = $ors['objoid'];
      $sec = $cmod->secure($oid,':ro');
      if (!$sec) continue; // no access rights

      $values = &$target_ds->rDisplay($oid, array(), false, $LANG_DATA, $lang, array());
      if (!is_array($values)) { // deleted object
        $query = 'delete from '.$mod->table.' where objoid=?';
        getDB()->execute($query, array($oid));
        continue;
      }

      if(!isset($res['modules'][$cmoid])){
        $res['modules'][$cmoid]=array('template' => $cmod->searchtemplate,
                                      'name'=> $cmod->getLabel(),
                                      'lines_oid' => array(),
                                      'lines_moid' => array(),
                                      'lines_score' => array(),
                                      'lines_title' => array(),
                                      'count'=> 0);
      }
      $res['modules'][$cmoid]['lines_oid'][] = $oid;
      $res['modules'][$cmoid]['lines_moid'][] = $cmoid;
      $res['modules'][$cmoid]['count']++;
      $res['modules'][$cmoid]['lines_score'][] = $res['modules'][$cmoid]['count'];
      
      $text = "";
      $sep = "";
      foreach($values['fields_object'] as $fo) {
        if ($fo->field == "UPD" || 
            $fo->field == "OWN" || 
            $fo->field == "TAG" || 
            $fo->field == "PUBLISH" || 
            !$fo->text) continue;

        $text .= $sep.$fo->text;
        $sep = " ";
      }

      $res['modules'][$cmoid]['lines_title'][] = $text;


    }
    $br=\Seolan\Core\Shell::from_screen($tplentry);
        
    \Seolan\Core\Shell::toScreen1($tplentry,$res);

  }

  function deleteTag($tag) {
    $query = 'select KOID,objoid,objfield,objtable from '.$this->table.' where tag=?';
    $rs = getDB()->fetchAll($query, array($tag));
    foreach($rs as $ors) {
      $query = 'select '.$ors['objfield'].' from '.$ors['objtable'].' where KOID=?';
      $oldvalue = getDB()->fetchOne($query,array($ors['objoid']));
      $newvalue = str_replace(\Seolan\Field\Tag\Tag::$TAG_PREFIX.$tag." ", "", $oldvalue);
      // remove tag value from field
      $query = 'update '.$ors['objtable'].' set '.$ors['objfield'].'=? where KOID=?';
      getDB()->execute($query,array($newvalue, $ors['objoid']));
      // delete taguser object
      $query = 'delete from '.$this->table.' where KOID=?';
      getDB()->execute($query,array($ors['KOID']));
    }
  }

}
