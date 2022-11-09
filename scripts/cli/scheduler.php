<?php
$options = getopt('C:if:m:c:t:u:',["moid:","function:","config","userid","help"]);
define('TZR_ADMINI',1);
define('TZR_SCHEDULER',1);
$c=$options['c']??null; // class
$m=($options['m']??'').($options['moid']??''); // moid
$f=($options['f']??'').($options["function"]??''); // function
$t=$options['t']??null; // toid
$u=($options['u']??'').($options["userid"]??''); // uid
$upgrade=$options['upgrade']??null; // upgrade au format \Seolan\Core\Shell_20190131

if(isset($options["h"]) || isset($options["help"])) {
    $help=<<<END
Usage: php-seolan10 scheduler.php [options]
   sans options: exécution des taches en attente
   --help : ce message
   --moid : moid du module à charger, à utiliser en conjonction avec --function
   --function : fonction à appeler sur le module moid, à utiliser en conjonction avec --mid
   --userid identifiant de l utilisateur qui doit être utilisé pour exécuter la fonction (avec moid et function)

END;
    echo $help;
    exit();
}

if(empty($options['C'])) $options["C"]=getenv('HOME').'/../tzr/local.php';


if(file_exists($options["C"])) {
  include_once($options["C"]);
  include_once($LIBTHEZORRO.'bootstrap.php');
  $START_CLASS='\Seolan\Core\Shell';
  if(empty($c)) $_REQUEST["class"]="\Seolan\Module\Scheduler\Scheduler";
  else $_REQUEST["class"]=$c;
  if(empty($t)) {
    if(empty($m) && empty($c)) $_REQUEST["moid"]=\Seolan\Module\Scheduler\Scheduler::getMoid(XMODSCHEDULER_TOID);
    else $_REQUEST["moid"]=$m;
  } else $_REQUEST["moid"]=\Seolan\Core\Module\Module::getMoid(constant($t));

  if(empty($f)) $_REQUEST["function"]="selectJob";
  else $_REQUEST["function"]=$f;
  $_REQUEST["template"]="Core.empty.txt";

  if(!empty($u)) {
    setSessionVar('UID',$u);
  }
}
$dateStart = date('YmdHis');

// capture de toute erreur / exception non gérées pendant l'éxécution du script
set_exception_handler(function(\Throwable $t)use($dateStart){
  $date = date('YmdHis');
  // display des infos : pas garanti que l'accès base de donnée sera ok
  ob_start();
  
  echo("\n====== Error during scheduler execution ======\nStart date : {$dateStart}, Error date : {$date}\nError : '{$t->getMessage()}' code : '{$t->getCode()}' in '{$t->getFile()}' at line {$t->getLine()}\nTrace : \n".$t->getTraceAsString()."\n==============================================\n");
  
  $mess = ob_get_contents();

  ob_end_flush();

  getDB()->execute("insert into TASKS (KOID, LANG, title, ptime, etime, status, rem, pid) values(?,?,?,now(),now(),?,?,?)",
		   ["TASKS:{$date}", TZR_DEFAULT_LANG, 'Scheduler crash report ...', 'crashed', $mess, getmypid()]);
  
});


if(defined('TZR_MAX_SCHEDULER')) $maxscheduler=TZR_MAX_SCHEDULER;
else $maxscheduler=getCPUCores()/2;
if($maxscheduler<2) $maxscheduler=2;
if(!\Seolan\Library\Lock::getGlobalLock('scheduler',$maxscheduler,6,30)){
  \Seolan\Core\Logs::critical('Global Scheduler','Too many schedulers are running on this server. Execution stopped.');
  if(defined('TZR_MAX_SCHEDULER_ALERT')){
    $xmail=new \Seolan\Library\Mail();
    $xmail->addAddress(TZR_DEBUG_ADDRESS);
    $xmail->Subject='!!ERROR!! '.TZR_SERVER_NAME.' scheduler failed !!ERROR!!';
    $xmail->Body='Scheduler execution on '.TZR_SERVER_NAME.' failed because server is overloaded.';
    $xmail->send();
  }
  exit(3);
 }

// dans le cas où le fichier noscheduler existe, on n'exécute pas le scheduler
if(file_exists(TZR_VAR_DIR.'noscheduler')) {
  exit(0);
}

// permet d'éviter que le scheduler tourne pendant des upgrades
if(!$lock=\Seolan\Library\Lock::getSharedLock('upgrade')){
  \Seolan\Core\Logs::critical('Local Scheduler','ongoing upgrade');
  exit(3);
}


if(empty($c)) $_REQUEST['class']='\Seolan\Module\Scheduler\Scheduler';
else $_REQUEST['class']=$c;
if(empty($t)) {
  if(empty($m) && empty($c)) $_REQUEST['moid']=\Seolan\Module\Scheduler\Scheduler::getMoid(XMODSCHEDULER_TOID);
  else $_REQUEST['moid']=$m;
} else $_REQUEST['moid']=\Seolan\Core\Module\Module::getMoid(constant($t));

if(empty($f)) $_REQUEST['function']='selectJob';
else $_REQUEST['function']=$f;
$_REQUEST['template']='Core.empty.txt';

if(!empty($u)) {
  setSessionVar('UID',$u);
}

\Seolan\Library\Database::deactivateCache();
$dailyRun = (\Seolan\Core\DbIni::get('xmodscheduler:daily','val')!=date("Ymd") || \Seolan\Core\DbIni::get('xmodscheduler:lastchk2','val')!=date("Ymd"));

$XSHELL = new \Seolan\Core\Shell();
$XSHELL->_cache=false;
$XSHELL->run(array());

$forkMinisite = true;

if (isset($GLOBALS['HAS_VHOSTS']) && $GLOBALS['HAS_VHOSTS']) {
    \Seolan\Core\Logs::notice('______________ Scheduler for vhosts start ______________');
    \Seolan\Module\MiniSite\MiniSite::_store_master_context();
    
    $rs = getDB()->fetchAll('select * from VHOSTS where publish=1 and ifnull(templat,"")!="" order by rand() ');
    
    foreach($rs as $minisite) {
      if ($minisite['db'] == $MASTER_DB_NAME)
        continue;
      if (!$forkMinisite) {
        if (!\Seolan\Module\MiniSite\MiniSite::_change_context_to_vhost($minisite))
          continue;
        \Seolan\Core\Logs::notice('____________________________ Running scheduler for vhost '.$minisite['vhost'].' - '.$minisite['db']);
        
        $_REQUEST['moid'] = \Seolan\Core\Module\Module::getMoid(XMODSCHEDULER_TOID);
        try {
          $XSHELL = new \Seolan\Core\Shell();
          $XSHELL->_cache=false;
          $XSHELL->run(array());

          //TMP check bug DbIni
          \Seolan\Core\DbIni::set('xmodscheduler:vhost',$minisite['vhost']);
          \Seolan\Module\MiniSite\MiniSite::checkMinisiteConsole();
        } catch (\Exception $e) {
          \Seolan\Core\Logs::critical('SCHEDULER catched Exception for vhost '.$minisite['vhost'].' - '.$minisite['db'].': '. $e->getMessage());
        }
        \Seolan\Core\Logs::notice('____________________________ Running scheduler done for vhost '.$minisite['vhost'].' - '.$minisite['db']);
        sleep(1);
      
      } else {
        //new process for each minisite
        \Seolan\Module\MiniSite\MiniSite::runSchedulerForMinisite($minisite);
        if($dailyRun) {
          \Seolan\Core\Logs::notice('____________________________ Daily run (sleep 8): Forked scheduler for vhost '.$minisite['vhost'].' - '.$minisite['db']);
          sleep(8);
        } else {
          \Seolan\Core\Logs::notice('____________________________ Any run (sleep 1): Forked scheduler for vhost '.$minisite['vhost'].' - '.$minisite['db']);
          sleep(2);
        }
      }
    }// end loop minisite

    \Seolan\Core\Logs::notice('______________ Scheduler for vhosts end ______________');
    ///Back to Master Context for XDbIni::__destruct
    \Seolan\Module\MiniSite\MiniSite::_change_context_to_master();
    
    \Seolan\Core\Logs::notice("Scheduler back to master {$GLOBALS['DATABASE_NAME']}");
    \Seolan\Core\Logs::notice("$MASTER_DATA_DIR - $MASTER_WWW_DIR - $MASTER_HOME_ROOT_URL - $MASTER_HOME - $MASTER_LOCALLIBTHEZORRO");
}
