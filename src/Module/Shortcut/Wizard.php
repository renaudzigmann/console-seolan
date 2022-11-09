<?php
namespace Seolan\Module\Shortcut;

class Wizard extends \Seolan\Core\Module\Wizard {

  function __construct($ar=NULL) {
    parent::__construct($ar);
  }
  function istep1() {
    parent::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','target'), "url", "text");
  }

  function iend($ar=NULL) {
    parent::iend();
  }
}

?>
