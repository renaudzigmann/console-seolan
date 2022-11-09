<?php
include_once(__DIR__.'/isadmin.php');

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

$del=false;
// todo: garder le del
if(isset($_GET['mime'])) {
  $mime=rawurldecode($_GET['mime']);
  \Seolan\Library\SecurityCheck::assertIsMime($mime, 'downloader-public: mime');
}
if(isset($_GET['originalname'])) $originalname=$_GET['originalname'];
if(isset($_GET['disp'])) $disp=$_GET['disp'];
if(isset($_GET['filename'])) $filename=$_GET['filename'];

$datadir=TZR_TMP_DIR.'public/';

/// tester sir $datadir existe et le créer sinon

/// faire la différence entre le realpath sur TMP/public et sur realpath de filename

$filename = $datadir.$filename;
if(!is_file($filename)){
  header("HTTP/1.1 404 Not Found");
  exit(0);
}
\Seolan\Library\SecurityCheck::assertFileIsUnder($filename, $datadir, 'downloader-public: filename');

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
