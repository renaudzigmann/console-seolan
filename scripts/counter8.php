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

session_cache_limiter('nocache');

$tables = getMetaTables();

$src=$_REQUEST['_src'];
$dst=$_REQUEST['_dst'];

if(isset($tables['_LINKS'])) {
  $now=date("Y-m-d");
  $q="SELECT * FROM _LINKS WHERE src = ? AND dst = ? AND ts= ? limit 1";
  $rs=getDB()->fetchRow($q, array($src,$dst, $now));	
  if(!$rs) {
    $q="INSERT INTO _LINKS SET src=?, dst=?, cnt=1, ts=? ";
  }else{
    $q="UPDATE LOW_PRIORITY _LINKS SET cnt=cnt+1 WHERE src = ? AND dst=? and ts= ?";
  }
  getDB()->execute($q,array($src,$dst, $now));
}
if($dst=='open'){
  if(isset($tables['_MLOGS'])) {
    getDB()->execute('update _MLOGS set nbouv=nbouv+1 where KOID=?', array($src));
  }
  $filepath=$GLOBALS['LIBTHEZORRO']."/public/templates/images/pixel.gif";
  if(!file_exists($filepath)) die();
  header('Content-type: image/gif');
  header('Content-disposition: inline');
  $size=filesize($filepath);
  header('Accept-Ranges: bytes');
  header('Content-Length: '.$size);
  readfile($filepath);
  die();
}elseif(!empty($dst)){
  header("Location: $dst");
}
?>
