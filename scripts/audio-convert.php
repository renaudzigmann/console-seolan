<?php
// encodage d'un audio ...
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

define('IMAGE_MEMORY_LIMIT',32);
define('IMAGE_MAP_LIMIT',64);
define('IMAGE_DISK_LIMIT',64);

session_cache_limiter('public');

// Récupération des paramètres
if(isset($_GET['filename'])) $filename=$_GET['filename'];
if(isset($_GET['default'])) $default=$_GET['default'];
if(isset($_GET['bitrate'])) $bitrate=$_GET['bitrate'];
else $bitrate='128';
\Seolan\Library\SecurityCheck::assertIsNumeric($bitrate, 'audio-convert: bitrate');

if(isset($_GET['prehear'])) $prehear=$_GET['prehear'];
else $prehear=false;

if (isset($_GET['mime']))
  $mime = $_GET['mime'];
else
  $mime = 'audio/mpeg';
$hash=md5($bitrate.$mime);
$filenamefp=$GLOBALS['DATA_DIR'].$filename;

\Seolan\Library\SecurityCheck::assertFileIsUnder($filenamefp, $GLOBALS['DATA_DIR'], 'audio-converter: filename');


// Preecoute
if($prehear=='true'){
  $filewait_cache=$filenamefp.'-'.$hash.'-wait-cache';
  if(!file_exists($filewait_cache)){
    if ($mime == 'audio/ogg')
      $r=exec(FFMPEG.' -y -v -1 -i '.escapeshellarg($filenamefp).' -acodec libvorbis -ab 64k -ar 44100 -f ogg -t 30 '.escapeshellarg($filewait_cache));
    else
      $r=exec(FFMPEG.' -y -v -1 -i '.escapeshellarg($filenamefp).'" -ab '.$bitrate.'k -ar 44100 -f mp3 -t 30 '.escapeshellarg($filewait_cache));
  }
  $mtime=filemtime($filewait_cache);
  $last_modified=gmdate('D, d M Y H:i:s T',$mtime);
  header('Last-Modified: '.$last_modified);
  header('Content-type: '.$mime);
  $size=filesize($filewait_cache);
  header('Accept-Ranges: bytes');
  header('Content-Length: '.$size);
  readfile($filewait_cache);
  die();
}

// Mode normal
$filename_cache=$filenamefp.'-'.$hash.'-cache';
// L'audio n'existe pas encore, on créé la tache et on envoi un fichier de remplacement
if(!file_exists($filename_cache) || (filemtime($filenamefp)>filemtime($filename_cache))) {
  $XSHELL=new \Seolan\Core\Shell();
  $GLOBALS['XLANG']=new \Seolan\Core\Lang;
  $s=new \Seolan\Module\Scheduler\Scheduler();
  if ($mime == 'audio/ogg')
    $ffmpeg=FFMPEG.' -y -v -1 -i '.escapeshellarg($filenamefp).' -acodec libvorbis -b '.$bitrate.'k -ar 44100 -f ogg '.escapeshellarg($filename_cache);
  else
    $ffmpeg=FFMPEG.' -y -v -1 -i '.escapeshellarg($filenamefp).' -b '.$bitrate.'k -ar 44100 -f mp3 '.escapeshellarg($filename_cache);
  $s->createIdleShellJob($filename_cache,'Audio encoding (audio-convert)',$ffmpeg,'');
  if(!isset($default) || $default=='none'){
    $filewait_cache=$filenamefp.'-'.$hash.'-wait-cache';
    $realfile=$filewait_cache;
    if(!file_exists($filewait_cache)){
      if ($mime == 'audio/ogg')
        $r=exec(FFMPEG.' -y -v -1 -i '.escapeshellarg($filenamefp).' -acodec libvorbis -b 64k -ar 44100 -f ogg -t 30 '.escapeshellarg($filewait_cache));
      else
        $r=exec(FFMPEG.' -y -v -1 -i '.escapeshellarg($filenamefp).' -b 64k -ar 44100 -f mp3 -t 30 '.escapeshellarg($filewait_cache));
    }
  }else{
    $realfile=$default;
  }
}else{
  $realfile=$filename_cache;
}

download($realfile, $mime);

?>
