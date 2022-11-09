<?php
include_once(__DIR__.'/isadmin.php');
if (isAdmin())
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

// SetEnvIf Authorization .+ HTTP_AUTHORIZATION=$0
if (empty($_SERVER['PHP_AUTH_PW']) && empty($_SERVER['PHP_AUTH_USER'])) {
    
    header('WWW-Authenticate: Basic realm="Seolan"');
    header('HTTP/1.0 401 Unauthorized');
    die();
}

$_REQUEST['password'] = $_SERVER['PHP_AUTH_PW'];
$_REQUEST['login'] = $_SERVER['PHP_AUTH_USER'];

if(!isset($_REQUEST["template"]) && !isset($_REQUEST["moid"])) {
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
if(!\Seolan\Core\User::authentified()){
  $sess=new \Seolan\Core\Session();
  $ret=$sess->procAuth();
  if(!$ret){
    header('WWW-Authenticate: Basic realm="Seolan"');
    header('HTTP/1.0 401 Unauthorized');
    die();
  }
 }
$XSHELL->run(array());
