<?php
use \Seolan\Core\System;
use \Seolan\Core\Logs;
function Lock_comment_20211028(){
  return "Correction clé primaire, ajout module origine, nettoyage anciens locks";
}
/**
 * - nettoyage des locks passés
 * - ajout d'un champ modid
 * - ajout d'une clé primaire (koid)
 */
function Lock_20211028(){
  if (!System::tableExists('_LOCKS'))
    return;
  $executeAndTrace = function($query, $message){
    $stmt = getDB()->prepare($query);
    $stmt->execute();
    Logs::upgradeLog($message."\t\t".$stmt->rowCount());
  };
  // nettoyage
  $executeAndTrace("delete from _LOCKS where dend < now()", "Nettoyage des locks dépassés");
  // ajout colonne module origin
  $executeAndTrace("alter table _LOCKS add column MOID bigint", "Ajout du champ MOID");
  // ajout clé primaire
  $executeAndTrace("alter table _LOCKS add primary key (KOID,LANG)", "Ajout clé primaire");
}

