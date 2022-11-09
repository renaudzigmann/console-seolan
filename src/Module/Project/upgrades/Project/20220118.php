<?php
  /**
   * ajout du champ filtre annuaire : filtre par groupes sur l'annuaire
   * et du champ module annuaire 
   */

use \Seolan\Core\Module\Module;
use \Seolan\Core\DataSource\DataSource;

function Project_20220118() {

  foreach(Project_20220118_modlist() as $m){
    // datasource associé
    $ds = DataSource::objectFactoryHelper8($m['tab']);

    if (!$ds->fieldExists('directoryfiltergroups')){
      \Seolan\Library\Upgrades::addFields(
	$m['tab'],
	[
	  [
	    'directoryfiltergroups',
	    'Groupes visibles dans l\'annuaire',
	    '\Seolan\Field\Link\Link',
	    1,
	    null,
	    0,
	    0,
	    0,
	    0,
	    1,
	    0,
	    'GRP',
	    []
	  ]
	]
      );
      DataSource::clearCache();
      $ds = DataSource::objectFactoryHelper8($m['tab']);  
      $ds->procEditField([
	'field'=>'directoryfiltergroups',
	'options'=>[
	  'display_format'=>'%_GRP',
	  'doublebox'=>1
	]
      ]);
    }
    
    if (!$ds->fieldExists('directorymodule')){
      \Seolan\Library\Upgrades::addFields(
	$m['tab'],
	[
	  [
	    'directorymodule',
	    'Module annuaire du projet',
	    '\Seolan\Field\Module\Module',
	    1,
	    null,
	    0,
	    0,
	    0,
	    0,
	    0,
	    0,
	    null,
	    []
	  ]
	]
      );
      DataSource::clearCache();
      $ds = DataSource::objectFactoryHelper8($m['tab']);  
      $ds->procEditField([
	'field'=>'directorymodule',
	'options'=>[
	  'readonly'=>1,
	]
      ]);
    }
  }
}

function Project_20220118_modlist(){
  return getDB()->fetchAll('select moid, module, json_value(mparam,"$.table.value") as tab from MODULES where toid=?', ['8006']);
}

function  Project_comment_20220118(){

  foreach(Project_20220118_modlist() as $m){
    $list .= "\n\t moid : {$m['moid']} module : '{$m['module']}' table '{$m['tab']}'";
  }
  
  if (!empty($list))
    $list = "<pre>Modules concernés :{$list}</pre>";

  return "Ajout du champ 'directoryfiltergroups', filtre par les groupes sur l'annuaire projet $list";

}
