<?php

// correction et initialisation des noms de module (suite patch 55caea, passage en traduisible modulename)
//  initialiser AMSG et récupérer les module de MODULES qui seraient vides
function Module_20200403(){
  $echo = false;
  $upd=$init=0;
  $langs = array_keys($GLOBALS['TZR_LANGUAGES']);
  foreach(getDB()->select('select m.moid as moid, m.module as module, extractValue(m.mparam, \'//field[@name="modulename"]/value\') as oname, a.mtxt as nname from MODULES m'.
			  ' left outer join AMSG a on a.moid=concat("module:", m.moid, ":modulename") and a.mlang=?'
			, [TZR_DEFAULT_LANG]) as $line){
    if (empty($line['module']) && !empty($line['nname'])){
      \Seolan\Core\Logs::upgradeLog("\ncorrection : {$line['moid']} {$line['nname']} ", $echo);
      $q = 'UPDATE MODULES SET module=? where moid=?';
      $p = [$line['nname'],$line['moid']];
      getDB()->execute($q, $p);
      $upd++;
    }
    if (empty($line['nname'])){
      if (!empty($line['module'])){
	\Seolan\Core\Logs::upgradeLog("\ninitialisation amsg : {$line['moid']} {$line['module']} ",$echo);
	foreach($langs as $lang){
	  $q = 'INSERT INTO AMSG (moid, mtxt, mlang) VALUES (?,?,?)';
	  $p = ["module:{$line['moid']}:modulename", $line['module'], $lang];
	  getDB()->execute($q, $p);
	  $init++;
	}
      } else {
	\Seolan\Core\Logs::upgradeLog("impossible de corriger {$line['moid']}", $echo);
      }
    }
  }
  \Seolan\Core\Logs::upgradeLog("$upd correction(s), $init initialisation(s)", $echo);
}
