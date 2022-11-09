<?php
namespace Seolan\Module\Cache;

/// Wizard de creation d'un module  de gestion de cache
class Wizard extends \Seolan\Module\Table\Wizard {

  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Cache_Cache');
    $this->_module->table="_PCACHE";
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cache_Cache',"modulename","text");
  }

  function istep1() {
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','table'), 'table', 'table', 
			    array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"modulename"), "modulename", "text");
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"group"), "group", "text");
    $this->_options->setRO("table");
    $this->_options->setRO("modulename");
    $this->_options->setRO("group");
    $this->createStructure();
  }
  function iend($ar=NULL) {
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cache_Cache',"modulename","text");
    parent::iend();
  }
  private function createStructure() {
    if(!\Seolan\Core\System::tableExists($this->_module->table)) {
      $lg = TZR_DEFAULT_LANG;
      $ar1=array();
      $ar1["translatable"]="0";
      $ar1["auto_translate"]="0";
      $ar1["btab"]=$this->_module->table;
      $ar1["bname"][$lg]="System - ".\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cache_Cache',"modulename","text");
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->_module->table);
      $ord=3;
      $x->createField('url', \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cache_Cache','address','text'), '\Seolan\Field\ShortText\ShortText',
		      '200',$ord++,'1','1','1','0','0','1');
      $x->createField('file', \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cache_Cache','file','text'),'\Seolan\Field\Text\Text',
		      '60',$ord++,'1','1','1','0','0','1');
      //                                                                                          size  ord   obl que bro tra mul pub tar 
      $x->createField('endcache', \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cache_Cache','endcache','text'), '\Seolan\Field\DateTime\DateTime', '4',$ord++,'0','0','0','0','0','0');
      $x->createField('pagedefaults', \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cache_Cache','reqs','text'), '\Seolan\Field\ShortText\ShortText',           '4',$ord++,'0','0','1','0','0','0');
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    }
  }
}
