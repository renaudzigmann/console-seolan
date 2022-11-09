<?php
namespace Seolan\Module\Subscription;

class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->_module->table="OPTS";
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties');
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Subscription_Subscription','modulename');
    
  }
  public function istep1() {
    parent::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','table'), 'table', 'table', 
			    array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','modulename'), "modulename", "text");
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','group'), "group", "text");
    $this->_options->setRO("table");
    $this->_options->setRO("modulename");
    $this->_options->setRO("group");
  }

  function iend($ar=NULL) {
    $this->_module->table="OPTS";
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties');
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Subscription_Subscription','modulename');
    parent::iend();
  }
}

?>
