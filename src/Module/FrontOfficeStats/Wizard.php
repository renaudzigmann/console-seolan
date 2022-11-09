<?php
namespace Seolan\Module\FrontOfficeStats;
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    if(!\Seolan\Core\System::tableExists('_MARKS')) \Seolan\Module\FrontOfficeStats\FrontOfficeStats::createTasks();
    parent::__construct($ar);
    $this->_module->table="_MARKS";
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_FrontOfficeStats_FrontOfficeStats',"modulename","text");
  }
  function istep1() {
    parent::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','table'),'table','table',array('validate'=>true));
    $this->_options->setRO("table");
    $this->_options->delOpt('createstructure');
  }
  function iend($ar=NULL) {
    parent::iend();
  }
  static function createStructure() {
    if(\Seolan\Core\System::tableExists('_MARKS')) return;
    $lg=TZR_DEFAULT_LANG;
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']='_MARKS';
    $ar1['bname'][$lg]='System - '.\Seolan\Core\Labels::getSysLabel('Seolan_Module_FrontOfficeStats_FrontOfficeStats','modulename','text');
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_MARKS');
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    $x->delField(array('field'=>'OWN','action'=>'OK'));
    //                                                                                              ord obl que bro tra mul pub tar
    $x->createField('name',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','name','text'),'\Seolan\Field\ShortText\ShortText','60',      '1','1','1','1','0','0','1');
    $x->createField('ts',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','date','text'),'\Seolan\Field\Date\Date','0',              '2','1','1','1','0','0','0');
    $x->createField('mlang',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','language','text'),'\Seolan\Field\ShortText\ShortText','2',  '3','1','1','1','0','0','0');
    $x->createField('cnt','Count','\Seolan\Field\Real\Real','4',                                                   '4','0','0','1','0','0','0');
    getDB()->execute('ALTER TABLE _MARKS ADD INDEX mlang(mlang);');
    getDB()->execute('ALTER TABLE _MARKS ADD INDEX name(name);');
    getDB()->execute('ALTER TABLE _MARKS ADD INDEX ts(ts);');
  }
}
?>
