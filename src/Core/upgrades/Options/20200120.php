<?php
/**
 * migration des options 
 * - json array -> json object
 */
use \Seolan\Core\Logs;
use \Seolan\Core\Options;
function Options_20200120(){
  updateAll();
}
// mise à jour d'un champ
function updateField($tab, $field, $keysStr, &$stats){
  echo("\nupgrade $tab $field $keysStr");
  $cnt = (object)['error'=>0,
		  'empty'=>0,
		  'ready'=>0,
		  'converted'=>0
		  ];
  $keysArr = explode(',', $keysStr);
  $lines = getDB()->fetchAll("select $field, $keysStr from $tab where ifnull($field,'')!=''");
  foreach($lines as $line){
    //echo("\nOLD :  {$line[$field]}");
    if (empty($line[$field])){
      $cnt->empty++;
      continue;
    }
    $ojson = json_decode($line[$field], true);
    if (!is_array($ojson)){
      Logs::upgradeLog("$tab $field error decoding $field");
      $cnt->error++;
      continue;
    }
    if (isset($ojson['@comment@'])){
      $cnt->ready++;
      continue;
    }
    $newJson = ['@comment@'=>Options::JSON_COMMENT];
    foreach($ojson as $lineOpt){
      $oname = $lineOpt['name'];
      $ovalue = $lineOpt['value'];
      $otype = $lineOpt['type'];
      foreach(array_keys($lineOpt) as $parmName){
	if (!in_array($parmName, ['name','type','value'])){
	  Logs::upgradeLog("$tab $field unknown parameter $parmName ");
	}
      }
      $cnt->converted++;
      $newJson[$oname] = ['type'=>$otype,
			  'value'=>$ovalue];
      statAdd($stats, "$tab $field $oname");
    }
    
    $newJsonStr = json_encode($newJson, true);

    // mise à jour avec la nouvelle valeur
    $rqParms = [$newJsonStr];
    foreach($keysArr as $fn){
      $rqParms[] = $line[$fn];
    }
    $rq = "update $tab set $field=? where ".implode('=? and ', $keysArr)."=?";
    echo("\n\tREQ $rq \n\t".implode(',',$rqParms));
    getDB()->execute($rq, $rqParms);
  }
  Logs::upgradeLog("$tab $field, counts : converted {$cnt->converted}, empty {$cnt->empty}, errors : {$cnt->error}, alreaddy converted {$cnt->ready}");
}
// mises à jour
function updateAll() {
  $stats = [];
  foreach(['MODULES MPARAM MOID',
	   'BASEBASE BPARAM BTAB',
	   'DICT DPARAM DTAB,FIELD'] as $specs){
    list($tab, $field, $keysStr) = explode(' ', $specs);
    updateField($tab, $field, $keysStr, $stats);
  }
  ksort($stats);
  foreach($stats as $name=>$nb){
    echo("\n$name : $nb");
  }
}
function statAdd(&$stats, $name){
  if (!isset($stats[$name]))
    $stats[$name] = 0;
  $stats[$name]++;
}