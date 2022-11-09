<?php
namespace Seolan\Module\Workflow\Rule;
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->_module->table='_RULES';
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties','text');
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Workflow_Rule_Rule','modulename','text');
  }
  function istep1() {
    $this->createStructure();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','table'), 'table', 'table', array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"modulename"), "modulename", "text");
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"group"), "group", "text");
    $this->_options->setRO("table");
    $this->_options->setRO("modulename");
    $this->_options->setRO("group");
  }
  function iend($ar=NULL){
    $this->createStructure();
    parent::iend($ar);
  }
  static function createStructure(){
    if(\Seolan\Core\System::tableExists('_RULES')) return;
    $ar1=array();
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']='_RULES';
    $ar1['bname'][TZR_DEFAULT_LANG]='System - '.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Workflow_Rule_Rule','modulename','text');
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_RULES');
    //                                                          size ord  obl que bro tra mul pub tar
    $x->createField('name','Nom','\Seolan\Field\ShortText\ShortText',              '255','3' ,'1','1','1','1','0','1');
    $x->createField('modid','Module','\Seolan\Field\Module\Module',               '0','4' ,'1','1','1','0','0','0');
    $x->createField('q','Requête','\Seolan\Field\Link\Link',                    '0','5' ,'1','1','1','0','0','0','QUERIES');
    $x->createField('p','Paramètres','\Seolan\Field\Text\Text',                '60','6' ,'1','1','0','0','0','0');
  }
}
?>
