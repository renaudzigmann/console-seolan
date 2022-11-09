<?php
namespace Seolan\Module\Tasks;

class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Tasks_Tasks');
  }

  function istep1() {
    parent::istep1();
  }
  function iend($ar=NULL) {
    parent::iend();
  }
}

?>
