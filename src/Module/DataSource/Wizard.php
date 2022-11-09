<?php
namespace Seolan\Module\DataSource;
/// Wizard de creation d'un module gestion des sources de donnees
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_DataSource_DataSource');
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_DataSource_DataSource',"modulename","text");
  }

  function istep1() {
    parent::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"modulename"), "modulename", "text");
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"group"), "group", "text");
    $this->_options->setRO("modulename");
    $this->_options->setRO("group");
  }
  function iend($ar=NULL) {
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_DataSource_DataSource',"modulename","text");
    parent::iend();
  }
}
?>
