<?php
namespace Seolan\Module\MailingList;
class Wizard extends \Seolan\Module\Table\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }
  function istep1() {
    parent::istep1();
  }
  function istep2() {
    parent::istep2();
    $this->_options->delOpt("translatable");
    $this->_options->delOpt("auto_translate");
  }
  function istep3() {
    if($this->_module->createstructure) $this->createStructure();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_MailingList_MailingList',"key"), "key", "field", array("table"=>"table"));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_MailingList_MailingList',"from"), "from", "text");
  }
  function iend($ar=NULL) {
    $this->_module->createstructure=false;
    parent::iend();
    $mods=\Seolan\Core\Module\Module::modulesUsingTable('_MLOGS',true,false,false);
    if(empty($mods)){
      $mod=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
      $mod->_module->modulename='Historique des envois mails';
      $mod->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties','text');
      $mod->_module->table='_MLOGS';
      $mod->_module->order='modid,datep';
      $mod->iend();
    }
    $mods=\Seolan\Core\Module\Module::modulesUsingTable('_MLOGSD',false,false,false);
    if(empty($mods)){
      $mod=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
      $mod->_module->modulename='Historique des envois mails : anomalies';
      $mod->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties','text');
      $mod->_module->table='_MLOGSD';
      $mod->iend();
    }
  }

  private function createStructure() {
    $lg=TZR_DEFAULT_LANG;
    $ar1=array();
    $ar1["translatable"]="0";
    $ar1["auto_translate"]="0";
    $ar1["btab"]=$this->_module->btab;
    $ar1["bname"][$lg]=$this->_module->bname;
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->_module->btab);
    //                                                          ord obl que bro tra mul pub tar
    $x->createField('email','Email','\Seolan\Field\ShortText\ShortText',      '255','3','1','1','1','0','0','1');
    $x->createField('datee','Date','\Seolan\Field\Date\Date',              '0','4','1','0','1','0','0','0');
    $x->createField('confed',\Seolan\Core\Labels::getSysLabel('Seolan_Module_MailingList_MailingList','confirmedfield'),'\Seolan\Field\Boolean\Boolean',
		    '0','5','1','0','1','0','0','0');
    $x->createField('bounce','NPAI','\Seolan\Field\Boolean\Boolean',             '0','5','0','1','1','0','0','0');
    $this->_module->key="email";
    $this->_module->date="datee";
    $this->_module->table=$this->_module->btab;
  }
}
?>
