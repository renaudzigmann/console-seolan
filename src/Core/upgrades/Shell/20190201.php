<?php
/**
 * premier patch X2 : vérification version
 * -> on vérifie que l'on a : Core\Module 20190614, Core\Shell 20190527 et BOInfoTree 20190624
 * dont on est sur que le composant associé est instancié
 * -> on vérifie la version de MariaDB
 * -> on vérifie les droits pour triggers et procédures
 */
use \Seolan\Core\DbIni;
use \Seolan\Core\Logs;
use \Seolan\Field\Link\Normalizer;
function Shell_comment_20190201(){
  return "Premier patch X2. Vérifie que l'on a Core\Module 20190614, Core\Shell 20190527 et BOInfoTree 20190624. Si ok, nettoie la variable _STATICVARS upgrades";
}
function Shell_20190201(){
  $verbose = defined('TZR_BATCH');
  Logs::upgradeLog("Checking current X version ... ");
  $x2 = ['\Seolan\Core\Module\Module'=>['20190411','20190507','20190528','20190701'],
	 '\Seolan\Core\Shell'=>['20190222','20191121'],
	 '\Seolan\Module\MailLogs\MailLogs'=>['20190722'],
	 '\Seolan\Module\Monetique\Monetique'=>['20190923']
	 ];
  $upgrades = DbIni::getStatic('upgrades', 'val', []);
  $ok = true;
  foreach([['\Seolan\Core\Shell', '20190527'],['\Seolan\Core\Module\Module', '20190614']] as list($classname, $num)){
    if (!isset($upgrades[$classname])){
      Logs::upgradeLog("\tmissing upgrades list for $classname", $verbose);
      $ok = false;
      break;
    } elseif (!in_array((int)$num, $upgrades[$classname],false)){
      Logs::upgradeLog("\tmissing upgrades '$num' of $classname", $verbose);
      $ok = false;
    } else {
      Logs::upgradeLog("\tupgrades '$num' of $classname ok", $verbose);
    }
  }
  if(!$ok){
    Logs::upgradeLog("\tVersion X upgrade required before switching to X2", $verbose);
    die();
  }
  // version MariaDB
  if (!Normalizer::versionOk()){
    Logs::upgradeLog("\tDatabase version mismatch, see Link/Normalize", $verbose);
    die();
  }
  // version MariaDB
  
  $grants = getDB()->fetchCol('show grants for current_user()');
  $privileges = ['REFERENCES'=>false,'CREATE ROUTINE'=>false,'ALTER ROUTINE'=>false,'EVENT'=>false,'TRIGGER'=>false];
  foreach($grants as $line){
    var_dump($line);
    foreach(array_keys($privileges) as $name){
      if (strpos($line, $name) !== false){
	$privileges[$name] = true;
      }
    }
  }
  $pok = true;
  foreach($privileges as $name=>$isok){
    if (!$isok)
      $pok = false;
  }
  if (!$pok){
    Logs::upgradeLog("\tInvalid database access privileges : ".implode(',', array_keys($privileges))." required", $verbose);
    die();
  }
  // ok, on finit
  Logs::upgradeLog("\tReady for X2");
  Logs::upgradeLog(json_encode($upgrades, true), $verbose);
  Logs::upgradeLog("\tDeleting staticvar 'upgrades'",$verbose);
  // !!! on doi garder les upgrades X2 pre existants !!!
  foreach($upgrades as $classname=>&$classUpgrades){
    if (!isset($x2[$classname])){
      unset($upgrades[$classname]);
    } else {
      foreach($classUpgrades as $i=>$num){
	if (!in_array((int)$num, $x2[$classname],false)){
	  unset($classUpgrades[$i]);
	}
      }
    }
    $classUpgrades = array_values($classUpgrades);
    if (empty($classUpgrades))
      unset($classUpgrades);
    
  }
  $upgrades = array_filter($upgrades);
  // maj avec les valeurs restantes
  DbIni::setStatic('upgrades', $upgrades);
}
