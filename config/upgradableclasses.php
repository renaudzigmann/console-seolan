<?php
/**
 * liste des classes (hors modules et champs)
 * portant des upgrades
 */
function Seolan_upgradableClasses(){
  return [
    '\Seolan\Core\Options',
    '\Seolan\Core\DataSource\DataSource',
    '\Seolan\Core\Logs',
    '\Seolan\Core\Registry'
  ];    
}
