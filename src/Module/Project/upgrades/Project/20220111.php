<?php
  /**
   * ajout du champ linkup à la table des modules projets
   * => overide 'top' dans le menu gauche si présent et renseigné
   */

use \Seolan\Core\Module\Module;
use \Seolan\Core\DataSource\DataSource;

function Project_20220111() {

  foreach(Project_20220111_modlist() as $m){
    // datasource associé
    $ds = DataSource::objectFactoryHelper8($m['tab']);

    if (!$ds->fieldExists('menuid')){
      \Seolan\Library\Upgrades::addFields(
	$m['tab'],
	[
	  [
	    'menuid',
	    'Rubrique parente du groupe projet',
	    '\Seolan\Field\Thesaurus\Thesaurus',
	    1,
	    null,
	    0,
	    0,
	    0,
	    0,
	    0,
	    0,
	    'CS8',
	    [
	     'fparent'=>'linkup',
	     'flabel'=>'title',
	     'quickadd'=>0
	    ]
	  ]
	]
      );
      DataSource::clearCache();
      $ds = DataSource::objectFactoryHelper8($m['tab']);  
      $ds->procEditField([
	'field'=>'menuid',
	'options'=>[
	  'fparent'=>'linkup',
	  'flabel'=>'title',
	  'quickadd'=>0
	]
      ]);
    }
  }
}

function Project_20220111_modlist(){
  return getDB()->fetchAll('select moid, module, json_value(mparam,"$.table.value") as tab from MODULES where toid=?', ['8006']);
}

function  Project_comment_20220111(){

  foreach(Project_20220111_modlist() as $m){
    $list .= "\n\t moid : {$m['moid']} module : '{$m['module']}' table '{$m['tab']}'";
  }
  
  if (!empty($list))
    $list = "<pre>Modules concernés :{$list}</pre>";

  return "Ajout du champ 'menuid' rubrique destination de l'arbo du projet dans le menu gauche $list";

}
