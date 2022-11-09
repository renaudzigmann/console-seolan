<?php
include_once(__DIR__.'/isadmin.php');
if (isAdmin()){
  define('TZR_ADMINI',1);
} 
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

sessionStart();
// Verification des droits et de l'existance du fichier
$tmpname=md5(session_id().$_GET['table'].$_GET['field'].$_GET['oid']);
$filename=TZR_TMP_DIR.$tmpname;
if(!file_exists($filename)){
  die('file not exist '.$filename);
}
if(empty($_GET['moid'])) die('no moid');
$GLOBALS['XUSER']=new \Seolan\Core\User(array('UID'=>getSessionVar('UID')));
$GLOBALS['XLANG']=new \Seolan\Core\Lang();
$ok=false;
$mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$_GET['moid'],'tplentry'=>TZR_RETURN_DATA));
if(empty($mod->fieldssec[$field]) || $mod->fieldssec[$field]!='none') {
  $ok=$mod->secure($oid,'procEdit',$XUSER);
}
if(!$ok) {
  $message=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Security','permission_denied');
  $next=$_SERVER['REQUEST_URI'];
  header('Location: '.TZR_SHARE_ADMIN_PHP.'?message='.urlencode($message).'&next='.urlencode($next));
  die();
}
file_put_contents($filename,stripslashes($_POST['filecontent']));
?>
<html>
<head><title></title></head>
<body><script type="text/javascript">window.close()</script></body>
</html>
