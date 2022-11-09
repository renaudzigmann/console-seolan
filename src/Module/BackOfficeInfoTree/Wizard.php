<?php
// Support BEN le 01/03/20119 : création de la classe Wizard à cause des erreurs dans les logs 
namespace Seolan\Module\BackOfficeStats;
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }
  function istep1() {
    parent::istep1();
  }
  function iend($ar=NULL) {
    parent::iend();
  }
}
?>
