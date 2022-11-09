<?php
/**
 * renseigne le champ directorymodule (si il existe) de la table des modules projets (toid=8006) avec le moid d'un des modules rattachés 
 * le module annuaire (annumod) du module projet être renseigné (c'est lui qui est dupliqué)
 * le module rattaché porte la classe mailing (toid=1) et être le seul dans ce cas 
 * scripts/cli/upgrades.php --config ~/consoles/consoleX2-1/tzr/local.php  --class \\Seolan\\Module\\Project\\Project --upgrade 20220314_directory_module
 */
use \Seolan\Core\Module\Module;
use \Seolan\Core\DataSource\DataSource;

function Project_20220314_directory_module(){
  $run = false;
  //$targettoid = 25; //
  $targettoid = 1; // mailinglist
  //$targettoid = 997; // module user
  foreach(getDB()->select("select moid, module, ".
			  "json_value(mparam,'$.annumod.value'), ".
			  "json_value(mparam,'$.table.value') ".
			  " from MODULES where toid=?",[8006],false, \PDO::FETCH_NUM) as list($moid,$module,$annumoid,$ptab)){
    
    echo("\nmodule projet ($moid,$module,$annumoid,$ptab)\n==============================================");
    if (empty($annumoid)){
      echo("\n\tno annumod, skip");
      continue;
    }
    
    $annutab = getDB()->fetchOne("select json_value(mparam,'$.table.value') ".
				 "from MODULES where moid=?",[$annumoid]);

    if (empty($annutab) || $annutab != 'USERS'){
      echo("\n\tempty or non USERS annu table (moid $annumoid) $annutab, skip");
      continue;
    }
    
    $ds = DataSource::objectFactoryHelper8($ptab);

    if (!$ds->fieldExists('directorymodule')){
      echo("\n\tmodule no patched with directorymodule field, skip");
      continue;
    }
    $nbtot=$nbupd=0;
    foreach(getDB()->select("select koid,title,prefix,amods,directorymodule from $ptab", [], false, \PDO::FETCH_NUM) as list($pkoid,$ptitle, $prefix, $amoids,$dirmod)){
      $nbtot++;
      echo("\n\tproject title '$ptitle', prefix  '$prefix', attached modules '$amoids', directory module '$dirmod'");
      if (!empty($dirmod)){
	echo("\n\t\tdirectorymodule not empty, skip project");
	continue;
      }
      $amoids = preg_split('/\|\|/', $amoids, -1, PREG_SPLIT_NO_EMPTY);
      $annumods = [];
      foreach($amoids as  $amoid){
	list($atoid, $amodule) = getDB()->select('select toid, module from MODULES where moid=?', [$amoid])->fetch(\PDO::FETCH_NUM);
	if (empty($atoid))
	  continue;
	if ($atoid == $targettoid){
	  $annumods[] = [$amoid,$amodule];
	}
      }
      
      if(count($annumods)>1){
	echo("\n\t\ttoo many attached modules with toid $targettoid (mailinglist), skip project");
	continue;
      }
      if (count($annumods) == 0){
	echo("\n\t\tno attached modules with toid $targettoid (mailinglist), skip project");
	continue;
      }
      $nbupd++;
      list($dirmoid, $dirmodule) = $annumods[0]; 
      echo("\n\t\t updating project $annutab::$ptitle $pkoid => directory module to '$dirmoid' '$dirmodule'");
      // ds->procEdit ....
      if ($run){
	getDB()->execute("update $ptab set directorymodule=? where koid=?", [$dirmoid, $pkoid]);
      }
    }
    echo("\n\n ======> $nbupd project(s) updated / $nbtot project(s) \n\n");
  }
  echo("\n\n");
}

