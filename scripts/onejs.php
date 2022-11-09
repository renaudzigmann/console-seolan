<?php
if (false === include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/local.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
 }
if (false === include_once($LIBTHEZORRO.'bootstrap.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
 }
 
include_once(__DIR__.'/isadmin.php');
if (!defined('TZR_ADMINI') && isAdmin()) define('TZR_ADMINI',1);
ini_set("display_errors",0);

$TZR_WWW_DIR=$_SERVER['DOCUMENT_ROOT'];
ini_set("display_errors",0);

$cachedir = CACHE_DIR;
$scriptdir = $TZR_WWW_DIR;
$files = explode(':',$_GET['files']); // Recupere la liste des fichiers a traiter
$version = (isset($_GET['v']))?$_GET['v']:0; //Ajoute un numero de version a l'interieur du hash du fichier
$minify = (isset($_GET['minify']))?$_GET['minify']:false; //Si a true on minify le fichier, a false par defaut car nécessite un control avant la mise en place
$forcecache = (strpos($_SERVER['HTTP_REFERER'],'nocache=') !== false)?true:false; //Initialise true or false en fonction du referer si on est en nocache
$cache = (isset($_GET['cache']))?$_GET['cache']:!$forcecache; //Utilise les fichiers en cache ou pas, si parametre passé en get on utilise sinon on vérifie si on est en nocache
$forceall = (isset($_GET['forceall']))?$_GET['forceall']:false; //Force la regeneration du cache en supprimant également les fichiers en cache

echo \Seolan\Core\OneJs::stackJs($files,$version,$cachedir,$scriptdir,array('minify'=> $minify,'cache' => $cache,'forceall' => $forceall));

