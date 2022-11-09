<?php
namespace Seolan\Module\DocSet;
/**
 * base doc associée
 * nom du type de document
 * ajout du type de document pour sur le module pour la base doc associée
 */
use \Seolan\Core\{Labels,Logs,DataSource\DataSource};
class Wizard extends \Seolan\Module\Table\Wizard {

  function istep1(){
    parent::istep1();

    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_DocSet_DocSet','docmngt'),
			    'daclMoid',
			    'module',
			    ['toid'=>XMODDOCMGT_TOID,
			     'compulsory'=>1]);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_DocumentManagement_DocumentManagement', 'doctype'),
			    'doctype',
			    'text',
			    ['compulsory'=>1]);
  }
  /** 
   * création du type de document
   */
  function iend(){
    $moid = parent::iend();
    if (!empty($moid)){
      $ds = DataSource::objectFactoryHelper8('_TYPES');
      $ds->procInput(['_options'=>['local'=>true],
		      'title'=>$this->_module->doctype,
		      'modid'=>$this->_module->daclMoid,
		      'node'=>'2',
		      'modidd'=>$moid]);
    } else {
      Logs::critical(__METHOD__,"no moid returned");
    }
    return $moid;
  }
}
