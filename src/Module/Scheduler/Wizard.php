<?php
namespace Seolan\Module\Scheduler;

class Wizard extends \Seolan\Module\Table\Wizard {

  function __construct($ar=NULL) {
    if(!\Seolan\Core\System::tableExists('TASKS')) \Seolan\Module\Scheduler\Scheduler::createTasks();
    parent::__construct($ar);
    $this->_module->table="TASKS";
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Scheduler_Scheduler',"modulename");
  }
  public function istep1() {
    parent::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','table'), 'table', 'table', 
			    array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"modulename"), "modulename", "text");
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"group"), "group", "text");
    $this->_options->setRO("table");
    $this->_options->setRO("modulename");
    $this->_options->setRO("group");
  }

  function iend($ar=NULL) {
    $this->_module->table="TASKS";
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Scheduler_Scheduler',"modulename");
    parent::iend();
  }
}

?>
