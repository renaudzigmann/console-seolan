<?php
namespace Seolan\Module\Record;
class Record extends \Seolan\Module\Table\Table {
  public $table='T001';
  public $selector='';
  public $order='UPD DESC';
  public $pagesize=NULL;

  function __construct($ar=NULL) {
    parent::__construct($ar);
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=display&tplentry=br&template=Module/Record.view.html';
  }

  /// Liste des actions
  protected function _actionlist(&$my,$alfunction=true) {
    \Seolan\Core\Module\Module::_actionlist($my);
    if($this->secure('','adminBrowseFields')){
      $o1=new \Seolan\Core\Module\Action($this,'administration',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','administration','text'),
			    '&moid='.$this->_moid.'&function=adminBrowseFields&template=Core/Module.admin/browseFields.html','admin');
      $o1->setToolbar('Seolan_Core_General', 'administration');
      $my['administration']=$o1;
    }

    if($this->interactive) {
      $o1=new \Seolan\Core\Module\Action($this,'display',$this->getLabel(),
					 '&moid='.$this->_moid.'&_function=display&template=Module/Record.view.html&tplentry=br','display');
      $my['stack'][]=$o1;
      $o1->setToolbar('Seolan_Core_General','display');
      $my['home']=$o1;
    }
  }
  
  /// Actions spécifique à la visualisation
  function al_display(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $myoid=@$_REQUEST['oid'];
    // Edition
    if($this->secure($myoid,'edit')){
      $o1=new \Seolan\Core\Module\Action($this,'edit',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit','text'),
			    '&moid='.$this->_moid.'&_function=edit&template=Module/Record.edit.html&tplentry=br','edit');
      $o1->setToolbar('Seolan_Core_General','edit');
      $my['edit']=$o1;
    }
    // Impression
    if($this->secure($myoid,'printDisplay')){
      $o1=new \Seolan\Core\Module\Action($this,'print',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','print','text'),
			    'javascript:'.$uniqid.'.printselected();','edit');
      $o1->setToolbar('Seolan_Core_General','print');
      $my['print']=$o1;
    }
    // Export
    if($this->secure($myoid,'exportDisplay')){
      $o1=new \Seolan\Core\Module\Action($this,'export',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','export','text'),'javascript:'.$uniqid.'.exportselected();',
			    'more');
      $o1->menuable=true;
      $my['sexport']=$o1;
    }
  }
  
  /// Actions spécifique à l'edition
  function al_edit(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $myoid=@$_REQUEST['oid'];

    // Sauver
    $o1=new \Seolan\Core\Module\Action($this,'save',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','save','text'),'javascript:'.$uniqid.'.saverecord();','edit');
    $o1->order=1;
    $o1->setToolbar('Seolan_Core_General','save');
    $my['save']=$o1;

    // Afficher
    $o1=new \Seolan\Core\Module\Action($this,'home',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','display','text'),
			  '&moid='.$this->_moid.'&_function=display&template=Module/Record.view.html&tplentry=br','display');
    $o1->setToolbar('Seolan_Core_General','display');
    $my['home']=$o1; 

    // Impression
    if($this->secure($myoid,'printDisplay')){
      $o1=new \Seolan\Core\Module\Action($this,'print',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','print','text'),
			    'javascript:'.$uniqid.'.printselected();','edit');
      $o1->setToolbar('Seolan_Core_General','print');
      $my['print']=$o1;
    }
    // Export
    if($this->secure($myoid,'exportDisplay')){
      $o1=new \Seolan\Core\Module\Action($this,'export',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','export','text'),'javascript:'.$uniqid.'.exportselected();',
			    'more');
      $o1->menuable=true;
      $my['sexport']=$o1;
    }
  }

  /// Edition d'une fiche
  function edit($ar) {
    $p = new \Seolan\Core\Param($ar);
    $f= 'edit';
    if (!$p->is_set('oid')) {
      $ar['oid'] = $this->getFirstOid($ar);
      if (empty($ar['oid'])){
        $foo = ['function'=>'procInsert','save_text'=>\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','save','text')];
	\Seolan\Core\Shell::toScreen1('_', $foo);
	\Seolan\Core\Shell::changeTemplate('Module/Table.new.html');
	$f = 'insert';
      }
    }
    return parent::$f($ar);
  }
  /// Edition d'une fiche
  function procEdit($ar) {
    $p = new \Seolan\Core\Param($ar);
    if (!$p->is_set('oid')) {
      $ar['oid'] = $this->getFirstOid($ar);
    }
    return parent::procEdit($ar);
  }
  /// Display d'une fiche
  function display($ar=NULL) {
    $p = new \Seolan\Core\Param($ar);
    if (!$p->is_set('oid')) {
      $ar['oid'] = $this->getFirstOid($ar);
    }
    return parent::display($ar);
  }
  /// Retourn le premier oid de la table
  protected function getFirstOid($ar){
    $ar['pagesize']=1;
    $ar['tplentry']=TZR_RETURN_DATA;
    $ar['selectedfields']='none';
    $ar['nocount']=1;
    $r=parent::browse($ar);
    return $r['lines_oid'][0];
  }
}
?>
