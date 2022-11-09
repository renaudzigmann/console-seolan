<?php
// Changement de taille d'une image
include_once(__DIR__.'/isadmin.php');
if (isAdmin())
  define('TZR_ADMINI',1);
if (false === include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/local.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

if (false === include_once($LIBTHEZORRO.'config/tzr.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

include_once($LIBTHEZORRO.'src/Library/SecurityCheck.php');
\Seolan\Library\SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);

include_once($LIBTHEZORRO.'src/Library/MimeTypes.php');

define('IMAGE_MEMORY_LIMIT',32);
define('IMAGE_MAP_LIMIT',64);
define('IMAGE_DISK_LIMIT',64);
if(!defined('TZR_WATERMARK_FILE')) define('TZR_WATERMARK_FILE',$TZR_WWW_DIR.'watermark.png');
if(!defined('TZR_WATERMARK_POSITION')) define('TZR_WATERMARK_POSITION','bottom-right');
if(!defined('TZR_WATERMARK_RATIO')) define('TZR_WATERMARK_RATIO',0.25);
if(!defined('TZR_WATERMARK_MARGIN')) define('TZR_WATERMARK_MARGIN', 5);
if(!defined('TZR_RESIZER_COLORSPACE')) define('TZR_RESIZER_COLORSPACE','sRGB');

session_cache_limiter('public');

// Récupération des paramètres
if(isset($_GET['filename'])) $resizerFileName=$_GET['filename'];

$cli='';
// récupération des options et valeurs par défaut
foreach($TZR_RESIZER_DEFAULT_OPTIONS as $option=>$value) {
  if(!isset($_GET[substr($option, 1)]))
    $cli.=$option.' '.$value.' ';
}

if(isset($_GET['originalname'])) $originalname=$_GET['originalname'];

if(isset($_GET['geometry'])) {
  $geometry=$_GET['geometry'];
  \Seolan\Library\SecurityCheck::assertIsGeometry($geometry, 'resizer: geometry');
}

if(isset($_GET['mime'])) {
  $mime=rawurldecode($_GET['mime']);
  \Seolan\Library\SecurityCheck::assertIsMime($mime, 'resizer: mime');
}

if(isset($_GET['crop'])) {
  $crop=$_GET['crop'];
  \Seolan\Library\SecurityCheck::assertIsGeometry($crop, 'resizer: crop');
}

if(isset($_GET['gravity'])) {
  $gravity=$_GET['gravity'];
  \Seolan\Library\SecurityCheck::assertIsGravity($gravity, 'resizer: gravity');
}

if(isset($_GET['cli'])) $cli=$_GET['cli'];

if(isset($_GET['density'])) {
  $density=$_GET['density'];
  \Seolan\Library\SecurityCheck::assertIsNumeric($density, 'resizer: density');
}

if(isset($_GET['disp'])) $disp=$_GET['disp'];
if(isset($_GET['quality'])) {
  $quality=$_GET['quality'];
  \Seolan\Library\SecurityCheck::assertIsNumeric($quality, 'resizer: quality');
}

if(isset($_GET['rotate'])) {
  $rotate=$_GET['rotate'];
  \Seolan\Library\SecurityCheck::assertIsRotate($rotate, 'resizer: rotate');
}

if(isset($_GET['meta'])) $meta=$_GET['meta'];
if(isset($_GET['extent'])) {
  $extent=$_GET['extent'];
  \Seolan\Library\SecurityCheck::assertIsGeometry($extent, 'resizer: extent');
}

if(!isset($meta) && defined('TZR_ADMINI')) $meta=1;
if(!isset($watermarkmode)) $watermarkmode=false;
$tomark = false;
if (isset($extent)){
  $extent = '-extent '.rawurldecode($extent);
} else {
  $extent = '';
}
$geometry=rawurldecode(@$geometry);
if(isset($crop)){
  $crop=rawurldecode($crop);
  $crop=str_replace(' ','+',$crop);
  if(strpos($crop,'+')===false) $crop.='+0+0';
  if(empty($gravity)) $gravity='Center';
  $crop='-crop '.$crop;
}else{
  $crop='';
}

if(!empty($gravity)){
  $gravity='-gravity '.$gravity;
} else {
  $gravity='';
}
if(!empty($meta)){
  $profile='';
}else{
  $profile="+profile '*' ";
}
if(isset($cli) && strpos($cli,';')===false){
  $cli_nf=md5($cli);
  $cli=rawurldecode($cli);
}else{
  $cli_nf='';
  $cli='';
}
 
list($table,$field,$niv1,$niv2,$oid)=explode('/',$resizerFileName);

\Seolan\Library\SecurityCheck::assertIsSimpleString($table, 'resizer: table');
\Seolan\Library\SecurityCheck::assertIsSimpleString($field, 'resizer: field');
\Seolan\Library\SecurityCheck::assertIsSimpleString($niv1, 'resizer: niv1');
\Seolan\Library\SecurityCheck::assertIsSimpleString($niv2, 'resizer: niv2');
\Seolan\Library\SecurityCheck::assertIsSimpleString($oid, 'resizer: oid', true);
// virer la langue
if(($pos=strpos($oid,'.'))!==false) $oid=substr($oid,$pos+1);

$oid=$table.':'.$oid;
\Seolan\Library\SecurityCheck::assertIsKOID($oid, 'resizer: isKoid');

if (!empty($TZR_SECURE) && is_array($TZR_SECURE)){
  include_once($LIBTHEZORRO.'bootstrap.php');
  $is_secure = isSecureField($table, $field);
} else {
  $is_secure = false;
}

if ($is_secure) {
  session_cache_limiter('private, must-revalidate') ;
  // restauration de la session (en BO seulement pour le moment)
  // dans le cas de fichiers securisés  (voir File::getResizer, sessionStart)
  if (isset($_GET['sessionid'])){ 
    \Seolan\Library\SecurityCheck::assertIsSessionID($_REQUEST['sessionid']);
    $_GET[TZR_SESSION_NAME] = $_GET['sessionid'];
    \Seolan\Core\Logs::notice('resizer','attempt to restore session from GET sessionid : '.$_GET['sessionid']);
    \Seolan\Core\Logs::notice('resizer', 'attemps to restore session from GET headers : '.var_export(getAllHeaders(), true));
  }
  sessionStart(); 
  if (issetSessionVar('UID'))
    $XUSER = new \Seolan\Core\User(array('UID'=>getSessionVar('UID')));
  else
    $XUSER = new \Seolan\Core\User();
  \Seolan\Core\Logs::notice('resizer', 'restored session : '.getSessionVar('UID'));
  // on vérifie les droits en lecture sur le champ
  $ok = false;
  $moids = \Seolan\Core\Module\Module::modulesUsingTable($table);
  foreach($moids as $moid=>$module) {
    $mod = \Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid,'tplentry'=>TZR_RETURN_DATA));
    if (empty($mod->fieldssec[$field]) || $mod->fieldssec[$field] != 'none') {
      $ok = $mod->secure($oid,':ro',$XUSER);
      if($ok) break;
    }
  }
  if(!$ok) {
    header("HTTP/1.1 403 Forbidden"); 
    die();
  }
}

if(isset($GLOBALS['TZR_WATERMARKED'][$table][$field])){
  if(isset($_GET["code"])) $code=$_GET["code"];
  if($code!=md5($table.':'.$oid.'-'.$field.'-'.$geometry)){
    $watermarkmode=true;
  }
}

$additonalOpts = '';
if($mime=='application/pdf'){
  if(isset($_GET['page'])) {
    \Seolan\Library\SecurityCheck::assertIsNumeric($_GET['page'], 'resizer: page');
    $page='['.$_GET['page'].']';
  } else $page='[0]';
  $mime='image/jpeg';
  if (defined('IMAGEMAGICK_VERSION'))
    $imVersion = IMAGEMAGICK_VERSION;
  else {
    $res = array();
    exec('convert --version', $res);
    $imVersion = preg_replace('/(.*) (\d\.\d)(.*)/', '\2', $res[0]);
  }
  if ($imVersion >= 6.7)
    $additonalOpts = '-background white -alpha remove';
}else{
  $page='';
}

if(empty($mime)) $mime='image/jpeg';
else list($ty,$st)=explode('/',$mime);
if($st=='postscript') $st='eps2';
if($st=='photoshop') $st='psd';
if($st=='png') {
  $st = 'png32';
  $additonalOpts = '-filter Lanczos';
  if (empty($quality))
    $quality = 96;
}
if(empty($st)) $st='jpeg';
$mimeClasse=\Seolan\Library\MimeTypes::getInstance();
if(!$mimeClasse->isImage($mime) && $mime!='application/pdf') die("'$mime' is not valid image type");

if(isset($originalname)) $originalname=rawurldecode($originalname);
$resizerFileName=$GLOBALS['DATA_DIR'].$resizerFileName;

\Seolan\Library\SecurityCheck::assertFileIsUnder($resizerFileName, $GLOBALS['DATA_DIR'], 'resizer: filename');

if (!file_exists($resizerFileName)) {
  header('HTTP/1.1 404 Not found');
  die();
}

// Les gifs animés sont mal rendus avec l'interlace par défaut (line)
if($mime == 'image/gif')
  $cli .= '-interlace none ';

$sourceFilename = $resizerFileName;
$filename_prefix = '';
if($st=='x-icon') {
  $st = 'ico';
  $filename_prefix = 'ico:';
  $page = '[0]';
}
// use ld image, jpeg only
if($mimeClasse->isImage($mime) && (empty($density) || $density == 72) && $geometry) {
  preg_match('/(\d*)x?(\d*)/', $geometry, $matches);
  $width = $matches[1];
  $height = $matches[2];
  list($widthLD, $heightLD) = explode('x', TZR_LD_IMAGE_SIZE);
  $useLDImage = ($width < $widthLD && $height < $heightLD && $st != 'svg+xml');
  if($useLDImage){
    $original_time=@filemtime($resizerFileName);
    $ld_time=@filemtime($resizerFileName.'_ld');
    if($ld_time===false || $original_time>$ld_time) {
      computeWithLock(TZR_MOGRIFY_RESIZER." -resize ".escapeshellarg(TZR_LD_IMAGE_SIZE.">")." -density 72x72 ".escapeshellarg($filename_prefix.$resizerFileName.$page)." ".escapeshellarg($resizerFileName."_ld")." 2>&1  > /dev/null",
		      $oid.'_'.$field.'_ld');
      $ld_time=time();
    }
    $sourceFilename .= '_ld';
    $source_time=$ld_time;
  }
}
if(!isset($source_time)) $source_time=@filemtime($sourceFilename);

// Récupération de l'image cache
if(!empty($density)) $d='-density '.$density.'x'.$density.' ';
else $d='';
if(!empty($quality)) $q='-quality '.$quality;
else $q='';
if(strlen($geometry)>=1) $g="-resize '".$geometry."'";
else{
   $g='';
   $d='-density 300x300';
}
if(!empty($rotate)) $rotate = "-rotate '".$rotate."'";
else $rotate='';
$hash=md5($geometry.$crop.$extent.$profile.$gravity.$d.$q.$rotate.$mime.$cli_nf.$page);
if($watermarkmode) $filename_cache=$resizerFileName.'-'.$hash.'-WE-cache';
else $filename_cache=$resizerFileName.'-'.$hash.'-cache';
$cache_time=@filemtime($filename_cache);
clearstatcache(true, realpath($filename_cache));
if($cache_time===false || $source_time>$cache_time) {
  checkParams();
  if($st == 'svg+xml') {
    copy($sourceFilename, $filename_cache);
  }
  else if ($st == 'gif') {
    computeWithLock(TZR_MOGRIFY_RESIZER." ".escapeshellarg($sourceFilename.$page)." -coalesce $profile $d $q $g ".
		    "$crop $gravity $rotate $extent -colorspace ".TZR_RESIZER_COLORSPACE."  ".escapeshellarg($st.":".$filename_cache)." 2>&1  > /dev/null",
		    $oid.'-'.$field.'-'.$hash.'-cache');
  } else {
    computeWithLock(TZR_MOGRIFY_RESIZER." $profile $d $q $g ".
		    "$crop $gravity $cli $rotate $extent -colorspace ".TZR_RESIZER_COLORSPACE." $additonalOpts ".escapeshellarg($filename_prefix.$sourceFilename.$page)." ".
		    escapeshellarg($st.":".$filename_cache)." 2>&1  > /dev/null",
		    $oid.'-'.$field.'-'.$hash.'-cache');
  }
  $tomark=1;
}

// Watermarker l'image
if($tomark && $watermarkmode && file_exists(TZR_WATERMARK_FILE)){
  $original=@imgCreateFrom($filename_cache);
  $watermark=@imgCreateFrom(TZR_WATERMARK_FILE);
  $emp=TZR_WATERMARK_POSITION;
  if($original && $watermark){
    //taille de l'original
    $origInfo = getimagesize($filename_cache);
    $origX = $origInfo[0];
    $origY = $origInfo[1];
    //taille du watermark
    $waterMarkInfo = getimagesize(TZR_WATERMARK_FILE);
    $tailleWX = $waterMarkX = $waterMarkInfo[0];
    $tailleWY = $waterMarkY = $waterMarkInfo[1];
    if (TZR_WATERMARK_RATIO !== false) {
      $ratio = (($origX*TZR_WATERMARK_RATIO)/$waterMarkX);
      $tailleWX = intval($ratio * $waterMarkX);
      $tailleWY = intval($ratio * $waterMarkY);
    }
    if($emp=='center'){
      //calcul des coordonnee du watermark dans l'image (centrer)
      $posX = ($origX-$tailleWX)/2;
      $posY = ($origY-$tailleWY)/2;
    }elseif($emp=='bottom-right'){
      //calcul des coordonnee du watermark dans l'image (bas-droite)
      $posX = ($origX-($tailleWX+TZR_WATERMARK_MARGIN));
      $posY = ($origY-($tailleWY+TZR_WATERMARK_MARGIN));
    }
    $watermarkResized = @imagecreatetruecolor($tailleWX,$tailleWY);
    @imageAlphaBlending($watermarkResized, false);
    @imageSaveAlpha($watermarkResized, true);
    @imagecopyresampled ($watermarkResized,$watermark,0,0,0,0,$tailleWX, $tailleWY,$waterMarkX,$waterMarkY);
    @imagecopy ($original,$watermarkResized,$posX,$posY,0,0,$tailleWX,$tailleWY);
  }
  if($mime=='image/jpeg') @imagejpeg($original,$filename_cache);
  elseif($mime=='image/png') @imagepng($original,$filename_cache);
}

// Envoi
$mtime=filemtime($filename_cache);
$last_modified=gmdate('D, d M Y H:i:s T',$mtime);
header('Last-Modified: '.$last_modified);
header("ETag: ".md5($last_modified));
$cachetime=TZR_PAGE_EXPIRES;
if(defined('TZR_ADMINI')) {
  $cachetime=10;
}
$expires = gmdate('D, d M Y H:i:s T',time()+$cachetime);
header("Expires: ".$expires);
header("Cache-Control: max-age=".$cachetime);
header('Content-type: '.$mime);
if(!isset($disp)) $disp = 'inline';
if(isset($originalname)) {
  header('Content-disposition: '.$disp.'; filename="'.addcslashes($originalname, '"\\').'"');
} else {
  header('Content-disposition: '.$disp);
}
clearstatcache(true, realpath($filename_cache));
$size=filesize($filename_cache);

header('Accept-Ranges: bytes');
header('Content-Length: '.$size);
readfile($filename_cache);
die();

function computeWithLock($command, $lock) {
  include_once($LIBTHEZORRO.'src/Library/Lock.php');
  if($lck=\Seolan\Library\Lock::getLock($lock, 1, 1)) {
    exec($command);
    \Seolan\Library\Lock::releaseLock($lck);
    return true;
  } else {
    header("HTTP/1.1 503 Service not available");
    die();
  }
}
function checkParams(){
    if(@$GLOBALS['checked']) return;
    $safe='';
    if(!empty($GLOBALS['geometry']) && !preg_match('/^[0-9x<>!%^]+$/',$GLOBALS['geometry'])) $safe="1";
    if(!empty($GLOBALS['crop']) && !preg_match('/^-crop [0-9x+\-%]+$/',$GLOBALS['crop'])) $safe=$GLOBALS['crop'];
    if(!preg_match('/^[a-zA-Z0-9\/._-]+$/',$GLOBALS['resizerFileName'])) $safe="3";
    if(!empty($GLOBALS['density']) && is_nan($GLOBALS['density'])) $safe="4";
    if(!empty($GLOBALS['cli']) && !preg_match('/^[^;]+$/',$GLOBALS['cli'])) $safe="5";
    if(!empty($GLOBALS['page']) && !preg_match('/^\[[0-9]+\]$/',$GLOBALS['page'])) $safe="6";
    if(!empty($GLOBALS['quality']) && is_nan($GLOBALS['quality'])) $safe="7";
    if(!empty($GLOBALS['rotate']) && is_nan($GLOBALS['rotate'])) $safe="8";
    if(!empty($GLOBALS['gravity']) && !in_array($GLOBALS['gravity'],array('-gravity NorthWest','-gravity North','-gravity NorthEast','-gravity West','-gravity Center','-gravity East','-gravity SouthWest','-gravity South','-gravity SouthEast'))) $safe="9";
    if(!empty($GLOBALS['extent']) && !preg_match('/^\-extent [0-9x]+$/',$GLOBALS['extent'])) $safe="10";
    if($safe!='') {
      include_once($LIBTHEZORRO.'src/Helper/system.php');
      include_once($LIBTHEZORRO.'src/Library/Security.php');
      \Seolan\Library\Security::alert('resizer '.$safe);
    }
    $GLOBALS['checked']=true;
}

function imgCreateFrom($path){
  if(!file_exists($path)){
    return false;
  }else{
    $info = getimagesize($path);
    $X = $info[0];
    $Y = $info[1];
    $mime = $info['mime'];
    switch($mime){
    case 'image/jpeg':
      return @imagecreatefromjpeg($path);
      break;
    case 'image/png':
      $res = @imagecreatefrompng($path);
      imageAlphaBlending($res, false);
      imageSaveAlpha($res, true);
      return $res;
      break;
    case 'image/gif':
      return @imagecreatefromgif($path);
      break;
    }
  }
}
?>
