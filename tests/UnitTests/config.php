<?php
define('TZR_ADMINI',1);
define('TZR_SCHEDULER',1);
$_SERVER['REMOTE_ADDR'] = "127.0.0.1"; // localhost
/*
 * depuis csx/tests/UnitTests
 * $php-seolan10 phpunit --debug --verbose --configuration ~/csx/tests/UnitTests/phpunit.xml --include-path ./src LinkRelationTableTests.php
 * !! phpunit lit le fichier de conf phpunit.xml si présent
 * ou define('TZR_UNITTESTS_DEBUG', 1) &&  define('TZR_UNITTESTS_KEEPSTRUCTURES', 1)
 */

// pour charger boostrap
set_include_path(get_include_path() . PATH_SEPARATOR . '../../');

// pour changer l'emplacement
// export TZR_UNITTESTS_HOME=/home/xxx/yyy/etc/ 
$overloadHome = getEnv('TZR_UNITTESTS_HOME');
if (!empty($overloadHome) && file_exists($overloadHome))
  $homeEnv = $overloadHome;
else
  $homeEnv = getEnv('HOME');
try{
require($homeEnv.'/../tzr/local.php');
}catch(\Throwable $t){
}

// workaround sur initialization des locales 
$GLOBALS['LIBTHEZORRO'] = $LIBTHEZORRO;

if (!isset($GLOBALS['REPOSITORIES']))
  $GLOBALS['REPOSITORIES'] = [];

$GLOBALS['REPOSITORIES']['UnitTests']=['src'=>$GLOBALS['LIBTHEZORRO'].'tests/UnitTests/'];

require('bootstrap.php');

$GLOBALS['LOCALLIBTHEZORRO'] = $LOCALLIBTHEZORRO;

// ajout variables globales initialisées dans boostrap qui en phpunit ne génère pas de globales
$GLOBALS['TZR_PACKS'] = new \Seolan\Core\Pack();
$GLOBALS['XLANG'] = new \Seolan\Core\Lang();
// pas mis le shell pour le moment : fait au cas par cas, et faudrait faire pareil pour PACK et LANGS, à voir -> avoir le moins de dépendances possibles quand elles servent à rien ...
