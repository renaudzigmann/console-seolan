<?php
namespace Seolan\Module\Manual;

/// module de gestion de commentaires BO ou FO
class Manual extends \Seolan\Module\Table\Table {
  public $object_sec=true;
  public static $singleton = true;
  
  function __construct($ar=NULL) {
    $ar["moid"]=self::getMoid(XMODMANUAL_TOID);
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Manual_Manual');
  }

  static function getInstance() {
    \Seolan\Core\Module\Module::singletonFactory(XMODMANUAL_TOID);
  }

  // suppression du module
  //
  function delete($ar=NULL) {
    parent::delete($ar);
  }

  // initialisation des proprietes
  //
  public function initOptions() {
    parent::initOptions();
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['showPage']=array('none', 'list','ro','rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  function getPages($moid, $func, $fld='') {
    $pages = getDB()->fetchAll("SELECT KOID FROM _MANUAL WHERE modid=? and func=? and fld=?", [$moid, $func, $fld]);
  }
  
  function showPage($ar=NULL) {
    $p = new Param($ar, []);
    $oid=$p->get('oid');
    $d = $this->rDisplay($oid);
    return \Seolan\Core\Shell::toScreen1('manual',$r);
  }



  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=browse&tplentry=br&template=Module/Table.browse.html';
  }

  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my);
    $myclass=get_class($this);
    $moid=$this->_moid;
    $myoid=@$_REQUEST['oid'];
    $user=\Seolan\Core\User::get_user();
  }


}
