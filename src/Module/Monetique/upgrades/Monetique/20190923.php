<?php

use Seolan\Core\Logs;
use Seolan\Core\System;
use Seolan\Library\Upgrades;

function Monetique_20190923() {
  Logs::upgradeLog('Monetique changement taille champ porteur ENROMONETIQUE');

  $table = 'ENROMONETIQUE';
  $fields = [['field' => 'porteur', 'fcount' => 30]];

  if(System::tableExists($table)) {
    Upgrades::editFields($table, $fields);
  }
}
