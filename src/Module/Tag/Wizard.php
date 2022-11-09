<?php
namespace Seolan\Module\Tag;
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }
  function istep1() {
    parent::istep1();
    if(!\Seolan\Core\Module\Module::getMoid(XMODTAG_TOID)) {
      $this->_module->createstructure = true;
      $this->_module->modulename = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Tag_Tag','modulename');
      $this->_module->group = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties');
      $this->_module->comment[TZR_DEFAULT_LANG] = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Tag_Tag','comment');
    }
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','createstructure'), 'createstructure', 'boolean');

  }
  function istep2(){
    if(!$this->_module->createstructure){
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','table'), 'table', 'table');
    }else{
      $this->_module->bname=$this->_module->modulename;
      if(!\Seolan\Core\Module\Module::getMoid(XMODTAG_TOID)) {
        $this->_module->btab="TAGS";
      }
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_name'), 'bname', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_code'), 'btab', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','translate'), 'translatable', 'boolean');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','auto_translate'), 'auto_translate', 'boolean');
      $this->_module->trackchanges = true;
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','trackchanges'), 'trackchanges', 'boolean');
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','create_field') . ' PUBLISH', 'publish', 'boolean');
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','create_field') . ' OWN', 'own', 'boolean');
    }
  }
  function iend($ar=NULL) {
    if($this->_module->createstructure){
      $this->_module->createstructure=false;
      $ar1=array();
      $ar1['translatable']=$this->_module->translatable;
      $ar1['auto_translate']=$this->_module->auto_translate;
      $ar1['trackchanges']=$this->_module->trackchanges;
      $ar1['publish']=$this->_module->publish;
      $ar1['own']=$this->_module->own;
      $ar1['btab']=$this->_module->btab;
      $ar1['bname'][TZR_DEFAULT_LANG]=$this->_module->bname;
      $ar1['tag']=0;
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->_module->btab);
      $x->createField('tag','Terme','\Seolan\Field\ShortText\ShortText','255','3','1','1','1','0','0','1');
      $this->_module->table=$this->_module->btab;
    }
    return parent::iend();
  }
  function quickCreate($modulename, $options) {
    parent::quickCreate($modulename, $options);
  }
}
?>
