<?php
namespace Seolan\Module\RGPD;

/// Wizard de creation d'un module  de gestion de cache
class Wizard extends \Seolan\Module\Table\Wizard {

  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_RGPD_RGPD');
    $i=0;
    $found=false;
    while(!$found) {
      $tablename=sprintf('RGPD%02d',$i);
      if(!\Seolan\Core\System::tableExists($tablename)) {
	$found=true;
      } else $i++;
    }
    $this->_module->table=$tablename;
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_RGPD_RGPD',"modulename","text");
  }

  function istep1() {
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','table'), 'table', 'table', 
			    array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"modulename"), "modulename", "text");
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"group"), "group", "text");
    $this->createStructure();
  }
  function iend($ar=NULL) {
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_RGPD_RGPD',"modulename","text");
    parent::iend();
  }
  private function createStructure() {
    if(!\Seolan\Core\System::tableExists($this->_module->table)) {
      $lg = TZR_DEFAULT_LANG;
      $ar1=array();
      $ar1["translatable"]="0";
      $ar1["auto_translate"]="0";
      $ar1["btab"]=$this->_module->table;
      $ar1["bname"][$lg]="System - ".\Seolan\Core\Labels::getSysLabel('Seolan_Module_RGPD_RGPD',"modulename","text");
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->_module->table);
      $ord=3;
      //                                                                                          size  ord   obl que bro tra mul pub tar 
      $x->createField('title', \Seolan\Core\Labels::getSysLabel('Seolan_Module_RGPD_RGPD','title','text'), '\Seolan\Field\ShortText\ShortText',
		      '200',$ord++,'1','1','1','0','0','1');
      $x->createField('report', \Seolan\Core\Labels::getSysLabel('Seolan_Module_RGPD_RGPD','report','text'),'\Seolan\Field\RichText\RichText',
		      '80',$ord++,'0','0','1','0','0','0');
      $x->createField('datereport', \Seolan\Core\Labels::getSysLabel('Seolan_Module_RGPD_RGPD','dateport','text'),'\Seolan\Field\DateTime\DateTime',
		      '80',$ord++,'0','1','1','0','0','0');
      $x->createField('checkedby', \Seolan\Core\Labels::getSysLabel('Seolan_Module_RGPD_RGPD','checkedby','text'),'\Seolan\Field\Link\Link',
		      '80',$ord++,'0','1','1','0','0','0','USERS');
      $x->createField('checkeddate', \Seolan\Core\Labels::getSysLabel('Seolan_Module_RGPD_RGPD','checkeddate','text'),'\Seolan\Field\DateTime\DateTime',
		      '80',$ord++,'0','1','1','0','0','0');
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    }
  }
}
