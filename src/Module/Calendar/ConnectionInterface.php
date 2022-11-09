<?php
namespace Seolan\Module\Calendar;

interface ConnectionInterface {
  /// Retourne les infos pour le choix des champs pour une consolidation
  function XCalParamsConsolidation($ar);
  ///Retourne la requete pour une consolidation
  function XCalGetConsolidationQuery(&$diary,$params,$fields,$begin,$end,$type='all');
  ///Retourne l'url à utiliser sur l'evenement/agenda
  function XCalGetUrl($type);
  /// Génère un display à partir d'un oid et d'une liste de correspondance de champ (clé=>champ evt, valeur=>champ cible)
  function XCalRDisplay($oid,$params);
}
?>
