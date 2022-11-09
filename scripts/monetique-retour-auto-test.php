<?php

/**
 * script pour test IPN
 * paramètres:
 * - orderOid, koid commande
 * - status, succes (défaut) ou error
 */
if (false === include_once($_SERVER['DOCUMENT_ROOT'] . '../tzr/local.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}
if (false === include_once($LIBTHEZORRO . 'bootstrap.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

// vérification de l'URL
include_once($LIBTHEZORRO.'src/Library/SecurityCheck.php');
\Seolan\Library\SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);

\Seolan\Core\Logs::critical('monetique-retour-auto-test.php', var_export($_REQUEST, true));
if (!in_array($_SERVER['REMOTE_ADDR'], explode(',', \Seolan\Core\Ini::get('testmode_ips')))) {
  Seolan\Core\Logs::critical("{$_SERVER['SCRIPT_NAME']} call by {$_SERVER['REMOTE_ADDR']} not in testmode_ips");
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

$XSHELL = new \Seolan\Core\Shell();
$XSHELL->labels = new \Seolan\Core\Labels();
$fakeMonetique = new \Seolan\Module\Monetique\Fake();
$fakeMonetique->autoresponse();
