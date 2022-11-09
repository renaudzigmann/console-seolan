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

$moid = '';
if(isset($_GET['key'])) {
  list($filename,$moid,$mime,$originalname,$disp)=explode(',',$_GET['key']);
}
if(isset($_GET['mime'])) {
  $mime=rawurldecode($_GET['mime']);
  \Seolan\Library\SecurityCheck::assertIsMime($mime, 'downloader-tmp: mime');
}

$del=$_REQUEST["del"]??false; // si vrai, suppression du fichier après premier chargement

if(isset($_GET['moid'])) {
  $moid=$_GET['moid'];
  \Seolan\Library\SecurityCheck::assertIsNumeric($moid, 'downloader-tmp: moid');
}

if(isset($_GET['originalname'])) $originalname=$_GET['originalname'];
if(isset($_GET['disp'])) $disp=$_GET['disp'];
if(isset($_GET['filename'])) $filename=$_GET['filename'];

// Sécurité sur le chemin du fichier
if(strpos($filename,'..')!==false){
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

$filename = TZR_TMP_DIR.$filename;
\Seolan\Library\SecurityCheck::assertFileIsUnder($filename, TZR_TMP_DIR, 'downloader-tmp: filename '.$filename);
if(!is_file($filename)){
  header("HTTP/1.1 404 Not Found");
  exit(0);
}

$mime = rawurldecode($mime);
if (@$mime=='')
  $mime = 'application/x-octet-stream';
$originalname = rawurldecode($originalname);
if (@$originalname=='')
  $originalname = 'downloaded';
if (@$disp=='')
  $disp = 'attachment';
$mtime = filemtime($filename);
$last_modified = gmdate('D, d M Y H:i:s T',$mtime);


// Traitement des fichiers gzippés
if(isset($_GET['gzip']) && $_GET['gzip'] == 1) {
  $fh = gzopen($filename, 'r');
  $content = gzread($fh, 100000000);
  gzclose($fh);
}
if (isset($content)) {
  $size = strlen($content);
} else {
  $size=filesize($filename);
}

header("Last-Modified: $last_modified");
header("Content-type: $mime");
header('Accept-Ranges: bytes');
header('Content-disposition: '.$disp.'; filename="'.addcslashes($originalname, '"\\').'"');
header("Content-Length: $size");

if (isset($content)) {
  echo $content;
} else {
  ob_clean();
  flush();
  ob_end_clean();
  @readfile($filename);
}


if ($del) {
  register_shutdown_function('unlink', $filename);
}
