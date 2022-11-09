<?php
// Encodage d'une video ...
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

define('IMAGE_MEMORY_LIMIT',32);
define('IMAGE_MAP_LIMIT',64);
define('IMAGE_DISK_LIMIT',64);

session_cache_limiter('public');

include_once($LIBTHEZORRO.'src/Library/SecurityCheck.php');

\Seolan\Library\SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);

// Récupération des paramètres
if(isset($_GET['geometry'])) $geometry=rawurldecode($_GET['geometry']);
else $geometry='480x360>';
\Seolan\Library\SecurityCheck::assertIsGeometry($geometry, 'video-convert: geometry');

if(isset($_GET['bitrate'])) $bitrate=rawurldecode($_GET['bitrate']);
else $bitrate='512';
\Seolan\Library\SecurityCheck::assertIsNumeric($bitrate, 'video-convert: bitrate');

if(isset($_GET['filename'])) {
  $filename=$GLOBALS['DATA_DIR'].$_GET['filename'];
  \Seolan\Library\SecurityCheck::assertFileIsUnder($filename, $GLOBALS['DATA_DIR'], 'video-converter: filename');
}

if(isset($_GET['default'])) $default=$_GET['default'];
if(isset($_GET['preview'])) $preview=$_GET['preview'];
else $preview=false;
if(isset($_GET['frame'])) $frame=$_GET['frame'];
else $frame=3;
\Seolan\Library\SecurityCheck::assertIsNumeric($frame, 'video-convert: frame');

// Calcul la geometry si on demande un retaillage sans deformation
$geometry=\Seolan\Field\File\File::videoGetGeometry($filename,$geometry);

// Mode preview
if($preview=='true'){
  $mime='image/jpeg';
  $hash=md5($geometry.$bitrate.$mime.$frame);
  $filewait_cache=$filename.'-'.$hash.'-wait-cache';
  $realfile=$filewait_cache;
  if(!file_exists($filewait_cache) || (filemtime($filename)>filemtime($filewait_cache))){
    unlink($filewait_cache);
    $r=exec(FFMPEG.' -y -i '.escapeshellarg($filename).' -f image2 -vcodec mjpeg -ss '.$frame.' -vframes 1 -s '.
	    escapeshellarg($geometry).' -an '.escapeshellarg($filewait_cache));
    if(!file_exists($filewait_cache)){ // si exec a echoue on retente sans offset (-ss)
      $r=exec(FFMPEG.' -y -i '.escapeshellarg($filename).' -f image2 -vcodec mjpeg -vframes 1 -s '.escapeshellarg($geometry).' -an '.escapeshellarg($filewait_cache));
    }
  } 
  $mtime=filemtime($realfile);
  $last_modified=gmdate('D, d M Y H:i:s T',$mtime);
  header('Last-Modified: '.$last_modified);
  header('Content-type: '.$mime);
  $size=filesize($realfile);
  header('Accept-Ranges: bytes');
  header('Content-Length: '.$size);
  readfile($realfile);
  die();
}

// Mode normal
if (!empty($_GET['format']))
  $mime='video/'.$_GET['format'];
else
  $mime='video/x-flv'; // old lecteur swf
\Seolan\Library\SecurityCheck::assertIsMime($mime, 'video-convert: mime');

$hash=md5($geometry.$bitrate.$mime);
$filename_cache=$filename.'-'.$hash.'-cache';
// La video n'existe pas encore, on créé la tache et on envoi une image de remplacement
if(!file_exists($filename_cache) || (filemtime($filename)>filemtime($filename_cache))) {
  $XSHELL=new \Seolan\Core\Shell();
  $GLOBALS['XLANG']=new \Seolan\Core\Lang;
  $s=new \Seolan\Module\Scheduler\Scheduler();
  if (isset(\Seolan\Field\File\File::$html5_video_format[$_GET['format']])){
    $ffmpeg = FFMPEG." -y -nostats -i ".escapeshellarg($filename)." -vb {$bitrate}k -s ".escapeshellarg($geometry).
      " ".\Seolan\Field\File\File::$html5_video_format[$_GET['format']]['ffmpeg_opts']." ".escapeshellarg($filename_cache);
  } else {
    $ffmpeg = FFMPEG.' -y -nostats -i '.escapeshellarg($filename).' -vb '.$bitrate.'k -ab 64 -ar 22050 -s '.
      escapeshellarg($geometry).' -f flv '.escapeshellarg($filename_cache);
  }
  $s->createIdleShellJob($filename_cache,'Video encoding',$ffmpeg,'');
  if(!empty($default)){
    $realfile=$default; 
  }else{
    $mime='image/jpeg';
    $filewait_cache=$filename.'-'.$hash.'-wait-cache';
    $realfile=$filewait_cache;
    if(!file_exists($filewait_cache)){
      $r=exec(FFMPEG." -y -i ".escapeshellarg($filename)." -f image2 -vcodec mjpeg -vframes 10 -s ".
	      escapeshellarg($geometry)." -an ".escapeshellarg($filewait_cache));
    } 
  }
}else{
  $realfile=$filename_cache;
}

\Seolan\Core\Logs::debug("read video: ".$realfile);

sessionStart();

list($table, $field, $niv1, $niv2, $oid) = explode('/', $_GET['filename']);

\Seolan\Library\SecurityCheck::assertIsSimpleString($table, 'resizer: table');
\Seolan\Library\SecurityCheck::assertIsSimpleString($field, 'resizer: field');
\Seolan\Library\SecurityCheck::assertIsSimpleString($niv1, 'resizer: niv1');
\Seolan\Library\SecurityCheck::assertIsSimpleString($niv2, 'resizer: niv2');
\Seolan\Library\SecurityCheck::assertIsSimpleString($oid, 'resizer: oid', true);

$isSecure = (!empty($TZR_SECURE['_all']) || !empty($TZR_SECURE[$table][$field])
             || (!empty($TZR_SECURE[$table]) && $TZR_SECURE[$table] === '_all'));

if (!$isSecure || isBrowserNotCompatible()){
  \Seolan\Core\Logs::debug("read video: ".$realfile);
  download($realfile, $mime);
} else {
  $md5XsaltoProtectVideo = md5('XsaltoProtectVideo');
  $protectVideo          = (int) $_GET['_pv'];
  $filePathEncoded       = str_replace($md5XsaltoProtectVideo, '', getSessionVar('file'.$protectVideo, 'SECU_VIDEO'));
  $file                  = base64_decode(base64_decode($filePathEncoded));
  
  if ($protectVideo>0 && ((int) getSessionVar('x'.$file.$protectVideo, 'SECU_VIDEO')===0 || isset($_SERVER['HTTP_RANGE'])) && (bool)getSessionVar('enable_play', 'SECU_VIDEO')) {
    setSessionVar('x'.$file.$protectVideo, (int) getSessionVar('x'.$file.$protectVideo, 'SECU_VIDEO')+1, 'SECU_VIDEO');
    
    \Seolan\Core\Logs::debug("read video: ".$realfile);
    download($realfile, $mime);
  } else {
    \Seolan\Core\Logs::debug("block read video: ".$realfile);
  }
}

function isBrowserNotCompatible() {
  return preg_match('/(Trident|MSIE|android|blackberry)/i', $_SERVER['HTTP_USER_AGENT']);
}

