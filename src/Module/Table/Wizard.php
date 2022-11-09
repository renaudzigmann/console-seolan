<?php
namespace Seolan\Module\Table;
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }
  function istep1() {
    parent::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','createstructure'), 'createstructure', 'boolean');
  }
  function istep2(){
    if(!$this->_module->createstructure){

      $opt['emptyok']=false;

      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','table'), 'table', 'table', $opt);
    }else{
      $this->_module->bname=$this->_module->modulename;
      $this->_module->btab=\Seolan\Model\DataSource\Table\Table::newTableNumber();
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','table_name'), 'bname', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','table_code'), 'btab', 'text');
      if(!\Seolan\Core\Shell::getMonoLang()) {
	$this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','translate'), 'translatable', 'boolean');
	$this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','auto_translate'), 'auto_translate', 'boolean');
      } else {
        $this->_module->translatable=false;
        $this->_module->auto_translate=false;
      }
      $this->_module->trackchanges = true;
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','trackchanges'), 'trackchanges', 'boolean');
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','create_field') . ' CREAD', 'cread', 'boolean');
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','create_field') . ' PUBLISH', 'publish', 'boolean');
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','create_field') . ' OWN', 'own', 'boolean');
      if (\Seolan\Core\Module\Module::getMoid(XMODTAG_TOID)) {
        $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','create_field') . ' TAG', 'tag', 'boolean');
      }
    }
  }
  function iend($ar=NULL) {
    if($this->_module->createstructure){
      $this->_module->createstructure=false;
      $ar1=array();
      $ar1['translatable']=$this->_module->translatable;
      $ar1['auto_translate']=$this->_module->auto_translate;
      $ar1['trackchanges']=$this->_module->trackchanges;
      $ar1['cread']=$this->_module->cread;
      $ar1['publish']=$this->_module->publish;
      $ar1['own']=$this->_module->own;
      $ar1['tag']=$this->_module->tag;
      $ar1['btab']=$this->_module->btab;
      $ar1['bname'][TZR_DEFAULT_LANG]=$this->_module->bname;
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->_module->btab);
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText','255','3','1','1','1','0','0','1');
      $this->_module->table=$this->_module->btab;
    }
    return parent::iend();
  }
  function quickCreate($modulename, $options) {
    return parent::quickCreate($modulename, $options);
  }
}
?>
