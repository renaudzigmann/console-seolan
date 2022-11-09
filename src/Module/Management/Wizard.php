<?php
namespace Seolan\Module\Management;

/// Wizard de creation d'un module administration
class Wizard extends \Seolan\Module\Table\Wizard {
  function __construct($ar=NULL) {
    if(!\Seolan\Core\System::tableExists('_ADM')) \Seolan\Module\Management\Management::createTasks();
    parent::__construct($ar);
    $this->_module->table="_ADM";
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management',"modulename","text");
  }

  function istep1() {
    \Seolan\Core\Module\Wizard::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','table'), 'table', 'table', 
			    array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"modulename"), "modulename", "text");
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"group"), "group", "text");
    $this->_options->setRO("table");
    $this->_options->setRO("modulename");
    $this->_options->setRO("group");
  }
  function iend($ar=NULL) {
    $this->_module->table="_ADM";
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management',"modulename","text");
    parent::iend();
  }
}

?>