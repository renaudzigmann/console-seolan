<?php
ini_set('arg_separator.input','&;');

// Autoloader
require_once('src/Helper/system.php');
include('Vendor/autoload.php');
$GLOBALS['autoloadLogs'] = ['missing'=>[],'loaded'=>[],'calls'=>0];
spl_autoload_register('tzr_autoload', true, true);
checkSERVEREnvVariables();
/*
if ( ($local_config=include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/config/local.inc')) ===false ) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}
if(!$server_config) $server_config=array();
$seolan_config = require_once('config/config.php');
if(!$seolan_config) $seolan_config=array();

$CONFIG=new \Zend\Config\Config(array_merge($seolan_config,$server_config,$local_config));
    die('r');var_dump($CONFIG);

// Définition des paramètre de log
error_reporting($CONFIG['error_reporting']);
*/

if(defined('TZR_DEBUG_MODE')) {
  if(TZR_DEBUG_MODE==1)
    error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
  else
    error_reporting(TZR_DEBUG_MODE);
} else {
  define('TZR_DEBUG_MODE', -1);
  error_reporting(E_ERROR);
}


// Check les minisites
if (@$HAS_VHOSTS) {
  require_once('src/Application/MiniSite/checkminisite.php');
  \Seolan\Application\MiniSite\checkMinisite();
}
@include_once('/etc/seolan10/config.php');

require_once('config/tzr.php');

// Vérifie la version de PHP
if(version_compare(PHP_VERSION,TZR_PHP_RELEASE,'<')){
  echo('La console doit fonctionner avec php>='.TZR_PHP_RELEASE);
  exit(2);
}
loadIni();

// Créé l'objet langue
$XLANG=new \Seolan\Core\Lang();

$TZR_PACKS = new \Seolan\Core\Pack();
if(!isset($TZR_SITE_PACKS)) $TZR_SITE_PACKS=array();
if(TZR_ALLPACKS == 1 || \Seolan\Core\Shell::admini_mode()) {
  get_user_browser();
  $TZR_SITE_PACKS['\Seolan\Pack\DatePicker\DatePicker']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\Resizable\Resizable']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\Dialog\Dialog']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\Accordion\Accordion']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\AutoComplete\AutoComplete']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\Plupload\Plupload']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\EditInPlace\EditInPlace']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\NyroModal2\NyroModal2']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\ContextMenu\ContextMenu']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\DragnDrop\DragnDrop']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\Selectable\Selectable']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\Rating\Rating']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\Sortable\Sortable']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\Crop\Crop']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\SlickGrid\SlickGrid']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\Leaflet\Leaflet']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\MediaElement\MediaElement']=1;
  $TZR_SITE_PACKS['\Seolan\Pack\Phone\Phone']=1;
} else {
  $TZR_SITE_PACKS['\Seolan\Pack\CookieBar\CookieBar']=1;
  if(defined('HTML5MEDIA')) $TZR_SITE_PACKS['\Seolan\Pack\MediaElement\MediaElement'] = 1;
}



// Recuperation du PUT si necessaire
{
  $filename="";
  if(@$_SERVER['REQUEST_METHOD']=='PUT') {
    $filename="/tmp/tzrput-".uniqid();
    $putdata = fopen("php://input", "r");
    $fp = fopen($filename, "w");
    while ($data = fread($putdata, 1024))
      fwrite($fp, $data);
    fclose($fp);
    fclose($putdata);
    $_REQUEST['phpputdata']=$filename;
    register_shutdown_function('unlink', $filename);
  }
}

{ // block pour assurer que $f1 et $f2 sont des variables locales
  global $TZR_SITE_PACKS;
  global $TZR_PACKS;

  foreach($TZR_SITE_PACKS as $f1 => $f2) {
    $TZR_PACKS->addNamedPack($f1,$f2);
  }
}

?>
