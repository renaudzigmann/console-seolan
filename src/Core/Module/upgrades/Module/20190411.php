<?php
use  \Seolan\Core\Options;
use \Seolan\Core\DataSource\DataSource;
use \Seolan\Core\Field\Field;
/**
 * transformation des valeurs xml d'options en JSON
 * -> passer de la liste XML à du JSON associatif
 * -> en utilisant Core\Options::decode (qui accepete <> formats)
 * et les méthodes de sérialisation mise à niveau
 */

function Module_20190411(){
  // les modules (MODULES : mparam)
  updateModuleMparam();
  // les datasources (BASEBASE : bparam, DICT : dparam)
  updateBaseBaseAndDict();
}
/**
 * MODULE
 */
function updateModuleMparam(){

  $a = getDB()->query("SELECT MOID, MPARAM FROM MODULES");
  
  foreach($a as $line) {
    if(isset($line["MPARAM"])) {
      $moid = $line["MOID"];      
      $module=\Seolan\Core\Module\Module::objectFactory($moid);
      if (empty($module->_options)) {
        \Seolan\Core\Logs::notice(__METHOD__, " invalid MODULES entry table:$table, field:$field");
        continue;
      }
      $json=$module->_options->toJSON($module);
      getDB()->execute('UPDATE MODULES set MPARAM=? where MOID=? ',[$json, $moid]);
    }
  } 
}
/**
 * BASEBASE et DICT
 */
function updateBaseBaseAndDict(){ 

  $maj_basebase =getDB()->query("SELECT BOID, BPARAM FROM BASEBASE");
  
  foreach($maj_basebase as $line) {
    $boid = $line['BOID'];
    $ds = DataSource::objectFactory8($line['BOID']);
    if (empty($ds->_options)) {
      \Seolan\Core\Logs::notice(__METHOD__, " invalid BASEBASE entry table:$table, field:$field");
      continue;
    }
    $json=$ds->_options->toJSON($ds); 
    getDB()->execute('UPDATE BASEBASE set BPARAM=? where BOID=? ', [$json, $boid]);
  }
  
  $maj_dict = getDB()->query("SELECT FIELD, DTAB, DPARAM FROM DICT");
  
  foreach($maj_dict as $line) {
    $table = $line['DTAB'];
    $field = $line['FIELD'];
    $dparam_obj = Field::objectFactory2($table, $field);
    if (empty($dparam_obj->_options)) {
      \Seolan\Core\Logs::notice(__METHOD__, " invalid DICT entry table:$table, field:$field");
      continue;
    }
    $json=$dparam_obj->_options->toJSON($dparam_obj);
    getDB()->execute('UPDATE DICT set DPARAM=? where FIELD=? and DTAB=?',[$json, $field, $table]); 
  }
  
}

  

