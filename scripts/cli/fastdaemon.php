<?php

$options = getopt('C:');
define('TZR_ADMINI', 1);
define('TZR_SCHEDULER', 1);

if (empty($options['C'])) {
  $options['C'] = getenv('HOME') . '/../tzr/local.php';
}

if (false === include_once($options['C'])) {
  echo('include local.php failed');
  exit(1);
}
if (false === include_once($LIBTHEZORRO . 'bootstrap.php')) {
  echo('include bootstrap.php failed');
  exit(1);

}
// dans le cas où le fichier noscheduler existe, on n'exécute pas le scheduler
if(file_exists(TZR_VAR_DIR.'noscheduler')) exit(0);

$XSHELL = new \Seolan\Core\Shell();
$XSHELL->_cache = false;
//$XSHELL->labels = new \Seolan\Core\Labels();
$XUSER = new \Seolan\Core\User(['UID' => 'USERS:1']);
setSessionVar('UID', 'USERS:1');
$XLANG = new \Seolan\Core\Lang();

// permet d'éviter que le scheduler tourne pendant des upgrades
if (!$lock = \Seolan\Library\Lock::getSharedLock('upgrade')) {
  \Seolan\Core\Logs::critical('Local Scheduler', 'ongoing upgrade');
  exit(3);
}

\Seolan\Core\Module\Module::fastDaemon();

\Seolan\Library\Lock::releaseLock($lock);
