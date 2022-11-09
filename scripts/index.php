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

/* la sécurité sur les classes, les templates et les fonctions est vérifiée dans Shell::run() */
if(!preg_match('@^/([^&]+)\.html@', $_SERVER['REQUEST_URI'])) {
  if(empty($_REQUEST['class']) && empty($_REQUEST['_class']) && empty($_REQUEST['moid']))
      $_REQUEST['class'] = $START_CLASS;
}
if(empty($_REQUEST['template']))
	$_REQUEST['template'] = TZR_DEFAULT_TEMPLATE;
if(empty($_REQUEST['function']) && empty($_REQUEST['_function']))
  $_REQUEST['function'] = 'index';

sessionStart();

$XSHELL = new $START_CLASS();
$XSHELL->run(array());

