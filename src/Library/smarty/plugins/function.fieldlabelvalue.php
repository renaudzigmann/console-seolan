<?php
  /**
   * plugin d'affichage d'un label géré par les champs Field\Label
   * recherche du label par son oid au lieu de la variable
   * les labels sont supposés chargés de façon habituelle
   */
function smarty_function_fieldlabelvalue($ar, $smarty){
  $varname = \Seolan\Field\Label\Label::getVariablFromId($ar['id']);
  $label =  $smarty->getTemplateVars('labels')[$varname];
  return $label;
}
