<?php
namespace Seolan\Module\Calendar\Management;
/// Wizard de creation d'un module  de gestion des agenda
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
  }

  function istep1() {
    parent::istep1();
    $this->_options->setOpt('Module agenda à administrer', 'calmod', 'module');
    $this->_options->setOpt('Table des agendas','table','table');
  }

  function iend($ar=NULL) {
    if(empty($this->_module->group)) $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->order='name,OWN';
    parent::iend();
  }
}
?>