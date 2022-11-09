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

// vérification de l'URL
include_once($LIBTHEZORRO.'src/Library/SecurityCheck.php');
\Seolan\Library\SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);

// Initialise les applications
if (TZR_USE_APP) {
  \Seolan\Module\Application\Application::init();
}

$f=$_REQUEST['function'];
$c=$_REQUEST['class'];

if (empty($f)){
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

// boucle sur la classe et les parentes
// jusque à trouver la fonction dans l'espace de nom de la classe.
$ffound = false;
while(!$ffound && $c != null){
  //RR ? spl_autoload_call($c);
  // cas xxxx_xxxx_... et cas namespace standard
  if (substr_count($c, '\\') >= 1){
    $fns = explode('\\', $c);
  } else {
    $fns=explode('_',$c);
  }
  
  $classname = implode('\\',$fns);
  if (!class_exists($classname)){
    break;
  }
  //suppression du dernier terme (la classe) et ajout de la fonction
  //pour appel dans l'espace de nom de la classe
  array_pop($fns);
  array_push($fns,$f);
  $fns=implode('\\',$fns);
  
  $obj = new $classname(); // force class loading
  if (function_exists($fns))
    $ffound = true;
  else
    $c = get_parent_class($c);
}
if ($ffound)
  $fns();

die();

function activeSec(){
  sessionStart();
  $GLOBALS['XUSER']=new \Seolan\Core\User(array('UID'=>getSessionVar('UID')));
  $GLOBALS['XLANG']=new \Seolan\Core\Lang();
}
?>
