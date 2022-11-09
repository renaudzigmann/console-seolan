<?php
namespace Seolan\Module\Tasks;
/// Module de gestion de la corbeille de taches
class Tasks extends \Seolan\Core\Module\Module {

  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Tasks_Tasks');
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
    $g=array('index'=>array('ro','rw','rwv','admin'));
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  function index($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get("tplentry");
    $list = \Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA,'withmodules'=>true));
    $tasklets=array();
    foreach($list['lines_mod'] as $i=>&$mod) {
      if(is_object($mod)) {
	$tasklet=$mod->getTasklet();
	if(!empty($tasklet)){
        $tasklets[]=array('title'=>$mod->getLabel(),'tasklet'=>$tasklet,'mod'=>$mod);
	}
      }
    }
    $r['tasks']=&$tasklets;
    if(empty($tasklets)){
      $_REQUEST['message']='Vous n\'avez aucune tâche en attente.';
    }

    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  function &portlet2(){
    $list = \Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA));
    $txt='';
    foreach($list['lines_mod'] as $i=>&$mod) {
      if(is_object($mod)) {
	$txt.=$mod->getShortTasklet();
      }
    }
    if(!empty($txt)) {
      $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,true).'&class=\Seolan\Module\Tasks\Tasks&moid=154&_function=index&tplentry=br&template=Module/Tasks.index.html';
      $txt='<H1><a href="'.$url.'">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','view').'</a>'.$this->getLabel().'</H1>'.$txt;
    }
    return $txt;
  }
  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=index&tplentry=br&template=Module/Tasks.index.html';
  }
  // cette fonction est appliquee pour afficher l'ensemble des methodes
  // de ce module
  //
  protected function _actionlist(&$my=NULL, $alfunction=true) {
    parent::_actionlist($my);
    $myclass=get_class($this);
    $moid=$this->_moid;
    $o1=new \Seolan\Core\Module\Action($this, 'index', \Seolan\Core\Labels::getSysLabel('Seolan_Module_Tasks_Tasks','index','text'),
			  'moid='.$moid.
			  '&_function=index&tplentry=br&'.
			  '&template=Module/Tasks.index.html');
    $o1->homepageable=$o1->menuable=$o1->quicklinkable=true;
    $my['index']=$o1;
    $my['default']='index';
  }

  function status($ar=NULL) {
  }
 
}
?>
