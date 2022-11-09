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

if(!empty($_REQUEST['sessionid'])) {
  \Seolan\Library\SecurityCheck::assertIsSessionID($_REQUEST['sessionid']);
  $_GET[TZR_SESSION_NAME] = $_REQUEST['sessionid'];
}

if (!empty($_REQUEST['json_mode'])) {
  define('TZR_JSON_MODE', 1);
}

$del=false;
$moid = '';
if(isset($_GET['key'])) {
  list($filename,$moid,$mime,$originalname,$disp)=explode(',',$_GET['key']);
}
if(isset($_GET['mime'])) {
  $mime=rawurldecode($_GET['mime']);
  \Seolan\Library\SecurityCheck::assertIsMime($mime, 'downloader2: mime');
}

if(isset($_GET['moid'])) {
  $moid=$_GET['moid'];
  \Seolan\Library\SecurityCheck::assertIsNumeric($moid, 'downloader2: moid');
}

if(isset($_GET['originalname'])) $originalname=$_GET['originalname'];
if(isset($_GET['disp'])) $disp=$_GET['disp'];
if(isset($_GET['filename'])) $filename=$_GET['filename'];
// Pour compatibilite
if(isset($_GET['tempfile'])){
  $_GET['tmp']=$_GET['del']=1;
  $c=0;
  $filename=str_replace(TZR_TMP_DIR,'',$filename,$c);
  if(!$c) $filename='';
}
$tmpfile = false;
// Si 1, le fichier est récupéré dans le dossier temporaire
if (isset($_GET['tmp'])) {
  $DATA_DIR=TZR_TMP_DIR;
  $del=$_GET['del'];
  $tmpfile = true;
}
// statusFile, \Seolan\Module\Table\Table::export
if (isset($_GET['statusFile'])) {
  $DATA_DIR = TZR_TMP_DIR;
  $tmpfile = true;
}

$oid='';
$decodedFilename = explode('/', $filename);
$nbparts = count($decodedFilename); 
if($nbparts ==5 || $nbparts == 6 || $nbparts == 7){
  // cas des consultations d'archives +/- fichiers multivalués
  if ($nbparts == 7){ // archive d'un multi valué
    $table=$decodedFilename[0];
    $field=$decodedFilename[2];
    $niv1=$decodedFilename[3];
    $niv2=$decodedFilename[4];
    $oid=$decodedFilename[5];
    $table = str_replace('A_','',$table);
    $isArchive = true;
  } else if($nbparts==6) { // archive mono valuée ou multi valué
    if (is_numeric($decodedFilename[1])) { // archive mono, un nom de champ ne peut pas être numérique
      $table=$decodedFilename[0];
      $field=$decodedFilename[2];
      $niv1=$decodedFilename[3];
      $niv2=$decodedFilename[4];
      $oid=$decodedFilename[5];
      $table = str_replace('A_','',$table);
      $isArchive = true;
    } else { // multi valué
      $table=$decodedFilename[0];
      $field=$decodedFilename[1];
      $niv1=$decodedFilename[2];
      $niv2=$decodedFilename[3];
      $oid=$decodedFilename[4];
      $table = str_replace('A_','',$table);
      $isArchive = false;
    }
  } else { // 5 
    $table=$decodedFilename[0];
    $field=$decodedFilename[1];
    $niv1=$decodedFilename[2];
    $niv2=$decodedFilename[3];
    $oid=$decodedFilename[4];
    $isArchive = false;
  }
  if(($pos=strpos($oid,'.'))!==false) $oid=substr($oid,$pos+1);
  \Seolan\Library\SecurityCheck::assertIsSimpleString($table, 'downloader2: table '.$table);
  \Seolan\Library\SecurityCheck::assertIsSimpleString($field, 'downloader2: field '.$field);
  \Seolan\Library\SecurityCheck::assertIsSimpleString($niv1, 'downloader2: niv1');
  \Seolan\Library\SecurityCheck::assertIsSimpleString($niv2, 'downloader2: niv2');
  \Seolan\Library\SecurityCheck::assertIsSimpleString($oid, 'downloader2: oid', true);
  if ($isArchive)
    \Seolan\Library\SecurityCheck::assertIsSimpleString($decodedFilename[1], 'downloader2: archive date');
  $oid="{$table}:{$oid}";
}


// on regarde s'il s'agit d'un champ dit "sécurisé"
$is_secure = $DATA_DIR != TZR_TMP_DIR && isSecureField($table, $field);
// on regarde s'il faut tracer les acces
$is_trackable = (!empty($TZR_DLSTATS[$table][$field])
                || (!empty($TZR_DLSTATS[$table]) && $TZR_DLSTATS[$table] == '_all'));

if ($is_secure || $is_trackable) {
  // on a besoin de la session
  if (!TZR_ADMINI && !empty($JSON_INTERFACE['restAPI'])) {
    try {
      \Seolan\Core\Session::checkRestAuth();
    } catch (Exception $e) {}
  }
  if (!issetSessionVar('UID')) {
    session_cache_limiter('private, must-revalidate');
    sessionStart();
  }
  if (issetSessionVar('UID'))
    $XUSER = new \Seolan\Core\User(array('UID'=>getSessionVar('UID')));
  else
    $XUSER = new \Seolan\Core\User();
}
// on vérifie les droits en lecture sur le champ
if($is_secure && !$tmpfile && (empty($_GET['code']) || $_GET['code']!=getDownloaderToken($filename,$mime))) {
  $ok = false;

  if (!empty($moid)) {
    $mod = \Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid,'tplentry'=>TZR_RETURN_DATA));
    if (empty($mod->fieldssec[$field]) || $mod->fieldssec[$field] != 'none') {
      $ok = $mod->secure($oid,':ro',$XUSER);
    }
  }
  if(!$ok) {
    if (!TZR_ADMINI && !empty($JSON_INTERFACE['restAPI'])) {
      header('HTTP/1.1 403 Forbidden');
      die;
    }
    $message=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Security','permission_denied');
    $next=$_SERVER['REQUEST_URI'];
    header('Location: '.TZR_SHARE_ADMIN_PHP.'?message='.urlencode($message).'&next='.urlencode($next));
    die();
  }
}

$filename = $DATA_DIR.$filename;
\Seolan\Library\SecurityCheck::assertFileIsUnder($filename, $DATA_DIR, 'downloader2: filename '.$filename);

if(!is_file($filename) and !is_link($filename)){
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

$xset=NULL;
if (!empty($TZR_GPGDECRYPT_KEY_FILE) || !empty($TZR_GPGVERIFY_KEY_FILE)) {
  if(empty($XUSER)){
    @include_once($LIBTHEZORRO.'bootstrap.php');
  }
  $xset = &\Seolan\Core\DataSource\DataSource::objectFactoryHelper8("\Seolan\Model\DataSource\Table\Table&SPECS=$oid");
}

// Traitement des fichiers gzippés
if(isset($_GET['gzip']) && $_GET['gzip'] == 1) {
  $fh = gzopen($filename, 'r');
  $content = gzread($fh, 100000000);
  gzclose($fh);
}
// Cas des fichiers crypté/signé
if ($xset && $xset->desc[$field]->crypt) {
  $content = decyptAndVerifyFile($filename);
  if ($content === false) {
    \Seolan\Core\Logs::critical('Downloader2','Downloader2 : '.\Seolan\Core\User::get_current_user_uid().' cannot verify file "'.$filename.'"');
    header('Location:'.TZR_SHARE_ADMIN_PHP.'?template=message.html&message='.
            rawurlencode(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','decrypterror')));
    die();
  }
}
if (isset($content)) {
  $size = strlen($content);
} else {
  $size=filesize($filename);
}

// on trace les acces
if ($is_trackable) {
  \Seolan\Module\DownloadStats\DownloadStats::trace($XUSER->_curoid, $moid, $originalname, $size);
}
header("Last-Modified: $last_modified");
// la version du fichier est basée sur la date de dernière modif du fichier
header("ETag: ".md5($last_modified));
// on considère que le fichier peut être gardé en cache 10 secondes
define('DOWNLOADER_CACHE_CONTROL', 10);
$expires = gmdate('D, d M Y H:i:s T',time()+DOWNLOADER_CACHE_CONTROL);
header("Expires: ".$expires);
header("Cache-Control: max-age=".DOWNLOADER_CACHE_CONTROL);
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
