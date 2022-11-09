<?php
/**
 * traitement de l'appel d'une login url par un serveur oauth
 * auquel on a passé : `domaine`/oauth-return.php?id=`id de la directory' 
 * `id de la directory` étant l'id dans tzr/config/directories-configuration.php
 * les autres paramètres sont ceux de OpenIdConnect : 
 * code : le auth token
 * state : la clé passée à l'appel, qui sera contrôlé (_VARS)
 */
define('TZR_ADMINI',1); // à voir si ensuite on s'en sert en FO
if (false === include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/local.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}
if (false === include_once($LIBTHEZORRO.'bootstrap.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}
use \Seolan\Library\SecurityCheck;
use \Seolan\Core\Directory\Directory;
use \Seolan\Core\Logs;

sessionStart();

SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);
SecurityCheck::assertIsSimpleString($_GET['id']);

$id = $_GET['id'];

// recherche des paramètres de cette directory
$dirsConf = Directory::getConfigurations();
$dirsId = $dirsConf->directoriesId->toArray();

if (empty($id) || !in_array($id, $dirsId)
    || empty($_GET['code'])
    || empty($_GET['state'])
){
  header("HTTP/1.1 400 Bad Request");
  exit(0);
}
$dirConf = $dirsConf->$dirid;
$dir = \Seolan\Core\Directory\Directory::objectFactory($id, $dirsConf->$id);
$_REQUEST = ['_options'=>['local'=>true],
       'oauth'=>['directoryid'=>md5($id)],
       'class'=>$GLOBALS['TZR_SESSION_MANAGER'],
       'function'=>'procAuth',
       'template'=>'Core.empty.html',
       'code'=>$_GET['code'],
       'state'=>$_GET['state'],
       '_next'=>$dir->getURI('bohome')
];
unset($_GET);
unset($_POST);
Logs::notice(__METHOD__,var_export($ar));
sessionStart();
$XSHELL = new $ADMIN_START_CLASS();
$XSHELL->_cache=false;
$XSHELL->run([]);




