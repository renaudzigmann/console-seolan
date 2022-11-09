<?php
$options=getopt('C:',['db::','class::','upgrade::',"config::","dry","help"]);
$db = $options['db'];
$classname = $options['class'];
$upgradeno = $options['upgrade'];

if(isset($options['help'])) {
    $help=<<<END
Usage: php-seolan10 minisites/applyClassUpgrade.php [options]
   --help : ce message
   --db=DATABASE : base de données du minisite. Par exemple: v8minisite259 (optionnel - absent => tous les minisites)
   --class=CLASS : nom de la classe sur laquelle appliquer les upgrades
   --upgrade=NUM : numéro upgrade à appliquer. Par exemple: 20190131
   -C CONFIG,--config=CONFIG : nom du fichier de config (fichier standard par default)
   --dry : détection, traces etc sans appliquer
Exemples:
Application d une mise à jour particulière
php-seolan10 minisites/applyClassUpgrade.php --db=v8minisite259 --class=\\\Seolan\\\Core\\\Shell --upgrade=20190131

END;
    echo $help;
    exit();
}
$dryrun = 0;
if (isset($options['dry'])){
  define('TZR_UPGRADE_DRYRUN', 1);
  $dryrun = 1;
}

$customConfig = false;
if ($options['C'] && file_exists($options['C'])) {
  require_once($options['C']);
  $customConfig = true;
} elseif ($options['config'] && file_exists($options['config'])) {
  require_once($options['config']);
  $customConfig = true;
} else {
  require_once(getenv('HOME').'/../tzr/local.php');
}

define('TZR_LOG_FILE', 'php://stdout');
define('TZR_UPGRADE_LOG_FILE', 'php://stdout');
define('TZR_LOG_LEVEL', 'PEAR_LOG_DEBUG');
define('TZR_DEBUG_MODE', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_WARNING);
define('TZR_ADMINI',1);
define('TZR_SCHEDULER',1);
define('TZR_BATCH',1);

include_once($LIBTHEZORRO.'bootstrap.php');
$prefix = 'scripts/cli/minisites/applyClassUpgrade.php';

\Seolan\Library\Database::deactivateCache();

// il existe des upgrades qui ne sont accessibles que aux admin
setUserRoot();
$XSHELL = new \Seolan\Core\Shell();
$XSHELL->_load_user();
$XSHELL->_cache=false;
$XLANG = new \Seolan\Core\Lang();
$TZR_LOG_FILTERS = ['.'];


if (empty($classname) || empty($upgradeno)) {
   \Seolan\Core\Logs::upgradeLog(date('Y-m-d H:i:s').' '.$prefix." Missing args class=$classname or upgrade=$upgradeno - ABORT");
   exit(3);
}
if (!$customConfig) \Seolan\Core\Logs::upgradeLog(date('Y-m-d H:i:s').' '.$prefix." $classname $upgradeno : using default config file");
list($upgradefile, $functionName, $commentFunctionName) = \Seolan\Library\Upgrades::getUpgradeParameters($classname, $upgradeno);
try {
  $ok = include_once($upgradefile);
  if (!$ok || !function_exists($functionName)) {
    \Seolan\Core\Logs::upgradeLog(date('Y-m-d H:i:s').' '.$prefix." Error including file $upgradefile Or function $functionName not found - ABORT");
    exit(3);
  }
} catch(\Throwable $t) {
   \Seolan\Core\Logs::upgradeLog(date('Y-m-d H:i:s').' '.$prefix.$t->getMessage());
   exit(3);
}

if (empty($db)) {
  $rs = getDB()->fetchAll('select * from VHOSTS /*script upgrade*/');
} else {
  $minisite = getDB()->select('select * from VHOSTS where db=? ', [$db])->fetch();
  if (!is_array($minisite)) {
    \Seolan\Core\Logs::critical(date('Y-m-d H:i:s').' '.$prefix." Minisite db=$db NOT FOUND - ABORT");
    exit(3);
  }
  $rs[0] = $minisite;
}
\Seolan\Module\MiniSite\MiniSite::_store_master_context();

foreach($rs as $minisite) {
  if ($minisite['db'] == \Seolan\Module\MiniSite\MiniSite::$_master_config['DATABASE_NAME'])
    continue;
  if (!\Seolan\Module\MiniSite\MiniSite::_change_context_to_vhost($minisite))
    continue;
  $upgrades=\Seolan\Core\DbIni::getStatic('upgrades','val');
  if ($upgrades[$classname] && in_array($upgradeno,$upgrades[$classname]))
    continue;
  \Seolan\Core\Logs::upgradeLog(date('Y-m-d H:i:s').' '.$prefix."_____ applyUpgrade $classname $upgradeno start for vhost {$minisite['vhost']} - {$minisite['db']}");
  
  
  if (!$dryrun) {
    try {
      $functionName();
  	  // on met à jour la base pour indiquer que cet upgrade est fait
      $upgrades[$classname][]=$upgradeno;
	    \Seolan\Core\DbIni::setStatic('upgrades',$upgrades);

    } catch(\Throwable $t) {
      \Seolan\Core\Logs::upgradeLog(date('Y-m-d H:i:s').' '.$prefix." Error running $functionName:".$t->getMessage());
      continue;
    }
  } else {
    \Seolan\Core\Logs::upgradeLog(date('Y-m-d H:i:s').' '.$prefix." dryrun $upgradefile $functionName");
  }

  \Seolan\Core\Logs::upgradeLog(date('Y-m-d H:i:s').' '.$prefix."______ applyUpgrade $classname $upgradeno end for vhost {$minisite['vhost']} - {$minisite['db']}");
}
if (empty($db))
  \Seolan\Core\Logs::upgradeLog(date('Y-m-d H:i:s').' '.$prefix." end $classname $upgradeno");
\Seolan\Module\MiniSite\MiniSite::_change_context_to_master();
