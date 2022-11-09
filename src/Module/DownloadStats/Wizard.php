<?php
namespace Seolan\Module\DownloadStats;

class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    \Seolan\Module\DownloadStats\DownloadStats::createTasks();
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_DownloadStats_DownloadStats');
    $this->_module->group = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties');
    $this->_module->modulename = \Seolan\Core\Labels::getSysLabel('Seolan_Module_DownloadStats_DownloadStats','modulename');
  }

  function istep1() {
    parent::istep1();
  }
  function iend($ar=NULL) {
    parent::iend();
  }
}

?>
