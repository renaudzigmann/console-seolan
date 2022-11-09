<?php
namespace Seolan\Module\BackOfficeStats;
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    \Seolan\Module\BackOfficeStats\Wizard::createStructure();
    parent::__construct($ar);
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties');
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_BackOfficeStats_BackOfficeStats','modulename');
  }
  function istep1() {
    parent::istep1();
  }
  function iend($ar=NULL) {
    parent::iend();
  }
  static function createStructure(){
    if(\Seolan\Core\System::tableExists('_STATS')) return;
    getDB()->execute('CREATE TABLE _STATS(KOID varchar(40) NOT NULL default "",SMOID varchar(10) default NULL,SGRP varchar(40) default NULL,'.
		'TS date default NULL,SFUNCTION varchar(40) default NULL,CNT bigint(20) default NULL,PRIMARY KEY(KOID),KEY TS(TS,SMOID,SGRP))');
  }
}
?>