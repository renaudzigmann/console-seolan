<?php
// $Id: admin.php,v 1.1.1.1 2004/01/28 20:03:11 tzr-master Exp $
define('TZR_ADMINI',1);
if (false === include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/local.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

if (false === include_once($LIBTHEZORRO.'bootstrap.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

include_once($LIBTHEZORRO.'src/Library/SecurityCheck.php');
\Seolan\Library\SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);

/* la sécurité sur les classes, les templates et les fonctions est vérifiée dans Shell::run() */
if(!isset($_REQUEST["template"]) && !isset($_REQUEST["moid"]) && !isset($_REQUEST["toid"])) {
  if(!isset($_REQUEST["class"])) $_REQUEST["class"] = '\Seolan\Core\Session';
  if(!isset($_REQUEST["function"])) $_REQUEST["function"]='auth';
  $_REQUEST["template"] = 'Core.layout/auth.html';
}
if(!isset($_REQUEST["template"])) $_REQUEST["template"] = 'Core.empty.html';

// on regarde si c'est une page qui peut etre conservée dans le cache navigateur
session_cache_limiter('private, must-revalidate') ;
sessionStart();

$XSHELL = new $ADMIN_START_CLASS();
$XSHELL->_cache=false;
$XSHELL->run(array());
