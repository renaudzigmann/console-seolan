<?php
namespace Seolan\Module\Lock;

class Wizard extends \Seolan\Core\Module\Wizard {

  function __construct($ar=NULL) {
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Lock_Lock',"modulename","text");
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Lock_Lock');
  }

  function istep1() {
    parent::istep1();
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Lock_Lock',"modulename","text");
    $this->_options->setRO("modulename");
    $this->_options->setRO("group");
  }
  function iend($ar=NULL) {
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Lock_Lock',"modulename","text");
    if(!\Seolan\Core\System::tableExists('_LOCKS')) {
      $this->createStructure();
    }
    parent::iend();
  }
  function createStructure() {
    $txt='CREATE TABLE `_LOCKS` (`UPD` TIMESTAMP NOT NULL,`KOID` varchar(40) default NULL,`LANG` char(2) default NULL,'.
      '`OWN` varchar(40) default NULL,`STATUS` varchar(10) default NULL,`DSTART` TIMESTAMP default "0000-00-00 00:00:00",'.
      '`DEND` TIMESTAMP default "0000-00-00 00:00:00", MOID BIGINT, PRIMARY KEY `KOID` (`KOID`,`LANG`));';
    getDB()->execute($txt);
  }
}
?>
