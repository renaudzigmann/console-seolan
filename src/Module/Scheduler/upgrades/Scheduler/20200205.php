<?php
/**
 * table TASKS : conversion du champ more (xml, xml2array) en JSON
 * -> ne concerne que les tâches planifiées
 */
use \Seolan\Core\Logs;
use \Seolan\Core\System;
function Scheduler_20200205(){
  $lines = getDB()->select('select koid, lang, more, cron from TASKS where status in(?,?)', ['cron','scheduled']);
  if ($lines){
    Logs::upgradeLog("TASKS, more conversion : ".$lines->rowCount());
    while($line = $lines->fetch()){
      if (!empty($line['more']) && substr($line['more'], 0,5) == '<?xml'){
        $moreArray = System::xml2array($line['more']);
	$json = json_encode($moreArray);
	getDB()->execute('update TASKS set UPD=UPD, more=? where koid=? and lang=?', [$json,$line['koid'], $line['lang']], false);
      }
      if (!empty($line['cron']) && substr($line['cron'], 0,5) == '<?xml'){
        $cronArray = System::xml2array($line['cron']);
	$jsonCron = json_encode($cronArray);
	getDB()->execute('update TASKS set UPD=UPD, cron=? where koid=? and lang=?', [$jsonCron,$line['koid'], $line['lang']], false);
      }
    }
  }

}
