<?php
/**
 * migration du champs opts de TEMPLATES
 * de xml vers json (le xml est un xml de définition
 * d'options)
 */
use \Seolan\Core\Logs;
use \Seolan\Core\Options;
function Options_20200124(){
  upgradeTemplatesOptions();
}
function upgradeTemplatesOptions(){
  $rs = getDB()->fetchAll('select * from TEMPLATES where ifnull(opts,"")!=""');
  foreach($rs as $ors){
    $specs = ['fields'=>[]];
    if(substr($ors['opts'],0,5)!='<?xml') {
      continue;
    }
    $oxml = simplexml_load_string($ors['opts']);
    if (empty($oxml->field)) return;
    foreach ($oxml->field as $field) {
      //$default = may be a lang indexed array
      if ($field['type'] == 'ttext'){
	$default = (array) $field->default;
      } else {
	$default = (string)$field->default;
      }
      $specs['fields'][] = [
	'name'=>(string) $field['name'],
	'type'=>(string) $field['type'],
	'label'=>(string) $field->label,
	'default'=>$default,
	'comment'=>(string) $field->comment,
	'options'=>(array) $field->options,
	'group'=>(string) $field->group,
	'level'=>(string) $field->level,
      ];
    }
    $json = json_encode($specs);
    // === tests
    //$options_values = (object) null;
    //$opts = new \Seolan\Core\Options();
    //$opts->setOptsFromXMLOrJSON($options_values, $json);
    //echo("\n$opts");
    // ===
    //echo(json_encode($specs, JSON_PRETTY_PRINT));
    getDB()->execute('update TEMPLATES set opts=? where KOID=?', [$json,$ors['KOID']]);
  }
}
