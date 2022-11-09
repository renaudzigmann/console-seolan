<?php
function FrontOfficeStats_comment_20200311(){
  return "Suppression des tables _RPATH et _REFERERS qui contenaient les statistiques des Robots et des visites de moteurs de recherche. Ces statistiques ne sont plus gérées sur la Console Séolan";
  }

function FrontOfficeStats_20200311() {
  // suppression des referers et des robots
  if(\Seolan\Core\System::tableExists('_RPATH')) {
      getDB()->execute('DROP TABLE _RPATH');
  }
  if(\Seolan\Core\System::tableExists('_REFERERS')) {
      getDB()->execute('DROP TABLE _REFERERS');
  }
}
