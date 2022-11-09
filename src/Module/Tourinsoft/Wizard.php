<?php
namespace Seolan\Module\Tourinsoft;

use Seolan\Core\DataSource\DataSource;
use Seolan\Core\Labels;
use Seolan\Core\Module\Module;
use Seolan\Core\System;

class Wizard extends \Seolan\Core\Module\Wizard {
  function istep1() {
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_General',"group"), "group", "text");
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Tourinsoft_Tourinsoft',"clientid"), 'clientId', 'text', array('compulsory'=>true));
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Tourinsoft_Tourinsoft',"syndicid"), 'syndicId', 'text', array('compulsory'=>true,'rows'=>5,'cols'=>40));
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Tourinsoft_Tourinsoft',"tblprefix"), 'tblPrefix', 'text', array('compulsory'=>true));
    $this->_module->group = 'Tourinsoft';
    $this->_module->clientId = 'decibelles-data';
    $this->_module->syndicId = 'accd3011-952e-4eac-9178-2600cbb8ad26';
    $this->_module->tblPrefix = 'TRNS_';
    $this->_module->modulename = '1 - Tourinsoft admin';
  }

  function iend($ar=NULL) {
    $moid = parent::iend();

    $module = Module::objectFactory($moid);
    $module->fetchStructure();

    System::array2xml(array('function' => 'cronImport', 'uid' => 'USERS:1'), $more);
    System::array2xml(array('period' => 'daily','time' => '8:00'), $cron);
    $tasks = DataSource::objectFactoryHelper8("SPECS=TASKS");
    $tasks->procInput(array(
      'title' => 'Import Tourinsoft',
      'status' => 'cron',
      'more' => $more,
      'cron' => $cron,
      'amoid' => $moid
    ));
  }
}
