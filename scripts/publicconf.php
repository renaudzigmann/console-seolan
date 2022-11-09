<?php
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

$metaconf_ip_adresses = preg_split('/;/', \Seolan\Core\Ini::get('metaconf_ip_adresses'), null, PREG_SPLIT_NO_EMPTY);
if (!is_array($metaconf_ip_adresses) || !in_array($_SERVER['REMOTE_ADDR'], $metaconf_ip_adresses)){
  die('No auth');
}
define('TZR_ADMINI',1);

$GLOBALS['XUSER']=new \Seolan\Core\User(\Seolan\Core\Ini::get('metaconf_user_alias'));
setSessionVar("UID", $GLOBALS['XUSER']->_curoid);

$modlist = \Seolan\Core\Module\Module::modlist();

$config = array('console_release'=>TZR_CONSOLE_RELEASE, 
		'libthezorro'=>'',
		'libthezorro_link'=>'',
		'upgrades'=>\Seolan\Core\DbIni::getStatic('upgrades', 'val'),
		'languages'=>array(),
		'default_lang'=>NULL,
		'modules'=>array());

// les modules
foreach($modlist['lines_oid'] as $i=>$moid){
    $mod = \Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid, 'interactive'=>false, 'tplentry'=>TZR_RETURN_DATA));
    $config['modules'][] = $mod->getPublicConfig();
}

// langues
$config['default_lang'] = TZR_DEFAULT_LANG;
$config['languages'] = $GLOBALS['TZR_LANGUAGES'];
// biblio tzr
$config['libthezorro'] = $GLOBALS['LIBTHEZORRO'];
$config['libthezorro_link'] = @readlink($config['libthezorro']);
// 
// classes spécifiques ? (xshell?)


header('Content-Type: application/json');
header("Cache-Control: no-cache, must-revalidate"); 
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
die(json_encode($config,  JSON_PRETTY_PRINT));

