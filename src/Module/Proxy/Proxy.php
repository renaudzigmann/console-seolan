<?php
namespace Seolan\Module\Proxy;
class Proxy extends \Seolan\Core\Module\Module {
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Proxy_Proxy');
  }

  // suppression du module
  //
  function delete($ar=NULL) {
    parent::delete($ar);
  }

  // initialisation des propriétés
  //
  public function initOptions() {
    parent::initOptions();
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }
}
?>
