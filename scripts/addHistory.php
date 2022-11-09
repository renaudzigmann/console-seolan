<?php
include_once(__DIR__.'/isadmin.php');
if (isAdmin())
  define('TZR_ADMINI',1);
if (false === include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/local.php')){
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}
if (false === include_once($LIBTHEZORRO.'bootstrap.php')){
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

// vérification de l'URL
include_once($LIBTHEZORRO.'src/Library/SecurityCheck.php');
\Seolan\Library\SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);

session_cache_limiter('private, must-revalidate');
sessionStart();

$_SESSION[HISTORY_SESSION_VAR][$_POST['hid']]=$_POST;
if(isset($_POST['todel']) && is_array($_POST['todel'])) {
  foreach($_POST['todel'] as $hid){
    unset($_SESSION[HISTORY_SESSION_VAR][$hid]);
  }
}
sessionClose();
?>
