<?php
ini_set('max_execution_time', 0);
include_once(__DIR__.'/isadmin.php');
if (isAdmin())
  define('TZR_ADMINI',1);
define('TZR_JSON_MODE',1);

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

if($_REQUEST['sessionid']) {
  \Seolan\Library\SecurityCheck::assertIsSessionID($_REQUEST['sessionid']);
  $_GET[TZR_SESSION_NAME] = $_REQUEST['sessionid'];
}

$XSHELL = new $JSON_START_CLASS();
// on regarde si c'est une page qui peut etre conservée dans le cache navigateur
session_cache_limiter('private, must-revalidate');
if (!$JSON_START_CLASS::restAPI()) {
  sessionStart();
}
$XSHELL->_cache=true;
$XSHELL->decodeRewriting($_SERVER['REQUEST_URI']);
$XSHELL->run(array());
