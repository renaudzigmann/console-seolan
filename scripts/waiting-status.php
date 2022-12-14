<?php

include_once(__DIR__ . '/isadmin.php');
if (isAdmin()) {
  define('TZR_ADMINI', 1);
}

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

sessionStart();

$waitingRoom = \Seolan\Core\Ini::get('wr_queue_class') ?? '\Seolan\Module\WaitingRoom\Queue';
$waitingRoom::getStatus();

