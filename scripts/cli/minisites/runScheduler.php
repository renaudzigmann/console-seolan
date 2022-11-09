<?php
$options=getopt('C:',['db::',"config::","dry","help"]);
$db = $options['db'];


if(isset($options['help'])) {
    $help=<<<END
Usage: php-seolan10 minisites/runScheduler.php [options]
   --help : ce message
   --db=DATABASE : base de données du minisite. Par exemple: v8minisite259
   -C CONFIG,--config=CONFIG : nom du fichier de config (fichier standard par default)
Exemple:
php-seolan10 minisites/runScheduler.php --db=v8minisite259

END;
    echo $help;
    exit();
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
//define('TZR_BATCH',1);

include_once($LIBTHEZORRO.'bootstrap.php');
$prefix = 'scripts/cli/minisites/runScheduler.php';

\Seolan\Library\Database::deactivateCache();

//setSessionVar('root', true);
//$XUSER = new \Seolan\Core\User(['UID' => 'USERS:1']);
//setSessionVar('UID', 'USERS:1');


$XSHELL = new \Seolan\Core\Shell();
//$XSHELL->_load_user();
$XSHELL->_cache=false;
$XLANG = new \Seolan\Core\Lang();
$TZR_LOG_FILTERS = ['.'];

if (empty($db)) {
  \Seolan\Core\Logs::critical(date('Y-m-d H:i:s').' '.$prefix." Missing args db=$db - ABORT");
  exit(3);
}
if (!$customConfig) \Seolan\Core\Logs::notice(date('Y-m-d H:i:s').' '.$prefix." $db : using default config file");

$minisite = getDB()->select('select * from VHOSTS where db=? ', [$db])->fetch();
if (!is_array($minisite)) {
  \Seolan\Core\Logs::critical(date('Y-m-d H:i:s').' '.$prefix." Minisite db=$db NOT FOUND - ABORT");
  exit(3);
}
\Seolan\Core\Logs::notice(date('Y-m-d H:i:s').' '.$prefix.' Minisite from DB OK');

\Seolan\Module\MiniSite\MiniSite::_store_master_context();
if (!\Seolan\Module\MiniSite\MiniSite::_change_context_to_vhost($minisite)) {
  \Seolan\Core\Logs::critical(date('Y-m-d H:i:s').' '.$prefix." Minisite db=$db _change_context_to_vhost FAILED - ABORT");
  exit(3);
}

$moid = \Seolan\Core\Module\Module::getMoid(XMODSCHEDULER_TOID);

try { 
  \Seolan\Core\Logs::notice(date('Y-m-d H:i:s').' '.$prefix.' ________________________ Running scheduler for vhost '.$minisite['vhost'].' - '.$minisite['db']);
  $_REQUEST['moid'] = $moid;
  $_REQUEST["class"]="\Seolan\Module\Scheduler\Scheduler";
  $_REQUEST["function"]="selectJob";
  $_REQUEST["template"]="Core.empty.txt";
  
  $XSHELL->run(array());
        
  //TMP check bug DbIni
  \Seolan\Core\DbIni::set('xmodscheduler:vhost',$minisite['vhost']);
  \Seolan\Module\MiniSite\MiniSite::checkMinisiteConsole();
  
} catch(\Throwable $t) {
   \Seolan\Core\Logs::critical(date('Y-m-d H:i:s').' '.$prefix.' '.$t->getMessage());
   exit(3);
}

\Seolan\Core\Logs::notice(date('Y-m-d H:i:s').' '.$prefix.' ________________________ Running scheduler DONE for vhost '.$minisite['vhost'].' - '.$minisite['db']);
//\Seolan\Module\MiniSite\MiniSite::_change_context_to_master();

