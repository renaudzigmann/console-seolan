<?php
/****
 * NAME
 *   \Seolan\Module\Tag\Tag -- gestion d'un dictionnaire de tags
 * DESCRIPTION
 *   Stockage des tags posés par les utilisateurs à l'aide des champs Tag.
 * SYNOPSIS
 *   La creation d'un module est realisee par utilisation de la methode de classe \Seolan\Core\Module\Module::objectFactory.
 * PARAMETERS
 ****/

namespace Seolan\Module\Tag;
class Tag extends \Seolan\Module\Table\Table {

  static function updateTags($field, $value, $table, $koid, $moid, $userid) {
    $tagmoid = \Seolan\Core\Module\Module::getMoid(XMODTAG_TOID);
    $tagusermoid = \Seolan\Core\Module\Module::getMoid(XMODTAGUSER_TOID);
    if ($tagmoid) {
      $tags = explode(\Seolan\Field\Tag\Tag::$TAG_PREFIX, $value);
      if ($tagusermoid) {
        $usertags = \Seolan\Module\TagUser\TagUser::getUserTags($koid, $tagusermoid);
      }
      foreach ($tags as $tag) {
        $tag = trim($tag);
        if ($tag) {
          if (!\Seolan\Module\Tag\Tag::getTagOid($tag, $tagmoid)) { // add new tag
            \Seolan\Module\Tag\Tag::addTag($tag, $tagmoid);
          }
          if ($tagusermoid && !array_key_exists($tag, $usertags)) { // add new user tag
            \Seolan\Module\TagUser\TagUser::addUserTag($tag, $tagusermoid, $koid, $moid, $table, $field, $userid);
          }
        }
      }
    }
  }
  
  static function getTagOid($tag, $moid) {
    $mod=\Seolan\Core\Module\Module::objectFactory($moid);
    $query = 'select KOID from '.$mod->table.' where tag=?';
    $toid = getDB()->fetchOne($query, array($tag));
    return $toid;
  }
  
  static function addTag($tag, $moid) {
    $mod=\Seolan\Core\Module\Module::objectFactory($moid);
    $ar = array();
    $ar['table'] = $mod->table;
    $ar['tag'] = $tag;
    $res = $mod->procInsert($ar);
    return $res['oid'];
  }

  /// Suppression d'un tag = suppression des références à ce tag
  function del($ar) {
    $p = new \Seolan\Core\Param($ar, array());
    $oid = $p->get('oid');
    $query = 'select tag from '.$this->table.' where KOID=?';
    $tag =  getDB()->fetchOne($query, array($oid));
    $tumoid = \Seolan\Core\Module\Module::getMoid(XMODTAGUSER_TOID);
    if ($tumoid) {
      $tumod=\Seolan\Core\Module\Module::objectFactory($tumoid);
      $tumod->deleteTag($tag);
    }
    return parent::del($ar);
  }
}
