<?php

if (false === include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/local.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}
if (false === include_once($LIBTHEZORRO.'bootstrap.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}
// vérification de l'URL
include_once($LIBTHEZORRO.'src/Library/SecurityCheck.php');
\Seolan\Library\SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);

\Seolan\Core\Logs::critical('monetique-retour-auto.php', var_export($_REQUEST, true));
if (empty($_REQUEST['moid'])) {
  $_REQUEST['moid'] = TZR_MONETIQUE_DEFAULT_MOID;
}
\Seolan\Library\SecurityCheck::assertIsNumeric($_REQUEST['moid'], __METHOD__.': monetique moid');

$XSHELL = new \Seolan\Core\Shell();
$XSHELL->labels = new \Seolan\Core\Labels();
\Seolan\Core\Module\Module::objectFactory($_REQUEST['moid'])->autoresponse();
