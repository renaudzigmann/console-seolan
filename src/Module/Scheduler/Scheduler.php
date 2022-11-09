<?php
namespace Seolan\Module\Scheduler;
/// planification des taches et suivi de leur execution/ test
class Scheduler extends \Seolan\Module\Table\Table {
  public $order='status,ptime,title';
  public $table="TASKS";
  public static $singleton = true;
  public static $upgrades = ['20200205'=>''];
  /// constructeur
  function __construct($ar=NULL) {
    $ar["moid"]=self::getMoid(XMODSCHEDULER_TOID);
    if(!\Seolan\Core\System::tableExists('TASKS')) \Seolan\Module\Scheduler\Scheduler::createTasks();
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Scheduler_Scheduler');
    $this->group=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','systemproperties');
    $this->modulename=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Scheduler_Scheduler','modulename');
  }

  /// initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $this->_options->setRO('table');
    $this->_options->setRO('group');
    $this->_options->setRO('modulename');
  }

  /// securite des fonctions accessibles par le web
  public function secGroups($function,$group=NULL) {
    $g=array();
    $g['selectJob']=array('none','ro','rw','rwv','admin');
    $g['runAsap']=array('rw','rwv','admin');
    $g['shellTask']=array('none','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }


  /// liste des catégories reconnues dans cette classe
  public function secList() {
    return array('none','list','ro','rw','rwv','admin');
  }
  /**
   * Fournir des valeurs de more pre-renseignées
   */
  function insert($ar){
    if (!isset($ar['options'])){
	if (!isset($ar['options']['more'])){
	  $ar['options']['more']['value'] = json_encode(['function'=>'...','uid'=>'User oid or alias'], JSON_PRETTY_PRINT);
	}
	if (!isset($ar['options']['cron'])){
	  $ar['options']['cron']['value'] = json_encode(['period'=>'hourly','freq'=>'*/4'], JSON_PRETTY_PRINT); 
	}
      }
      return parent::insert($ar);
  }
  /// forcer l'exécution d'un job dès que possible
  public function runAsap($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $oid=$p->get("oid");
    $selected=$p->get("_selected");
    if(is_array($selected) && !empty($selected)) {
      $oid=array_keys($selected);
    } else {
      $oid=array($oid);
    }
    if(!empty($oid)) {
      foreach($oid as $i=>$aoid) {
        $rs = getDB()->fetchAll("SELECT * FROM TASKS WHERE KOID=?",array($aoid));
        foreach($rs as $ors) {
          $time=date("Y-m-d H:i:s");
          if ($ors['status'] == 'cron') {
            $omore=\Seolan\Core\System::xml2array($ors['more']);
            $this->createJob($ors['amoid'], NULL, $ors['title'], $omore, "Scheduled from cron",$ors['files'],$ors['cron']);
          } else {
            $cnt=getDB()->count("SELECT count(*) FROM TASKS WHERE KOID=?",array($ors['KOID']));
            if($cnt) {
              getDB()->execute("UPDATE TASKS SET ptime=?,status='scheduled' WHERE KOID=?",array($time, $ors['KOID']),false);
            }
          }
        }
      }
    }
  }

  /****m* \Seolan\Module\Scheduler\Scheduler/createJob
   * NAME
   *   \Seolan\Module\Scheduler\Scheduler::createJob -- Création d'un nouveau job planifié
   * FUNCTION
   *   Fonction permettant de créer un nouveau job dans la liste des jobs
   * INPUTS
   *   moid - identifiant du module contenant la fonction a exécuter
   *   date - date d'éxécution souhaitée
   *   title - nom de la tâche a exécuter, donné à titre indicatif pour apparition dans la liste
   *   more
   *   remark
   *   files - éventuels fichiers complémentaires
   ****/
  public function createJob($moid, $date, $title, $more, $remark, $files=NULL,$cron=NULL,$type="scheduled") {
    \Seolan\Core\Logs::notice("\Seolan\Module\Scheduler\Scheduler::createJob","creating job for module #$moid title $title periodicity $cron");
    if(empty($date)) $date=date("Y-m-d H:i:s");
    $tarfile=NULL;
    if(!empty($files)){
      if(is_array($files)) $tarfile=\Seolan\Core\System::tarfiles($files);
      else $tarfile=$files;
    }
    if(!empty($more['uid']) && !\Seolan\Core\Kernel::isAKoid($more["uid"])) {
      $u1=new \Seolan\Core\User($more["uid"]);
      if(is_object($u1)) $more['uid']=$u1->uid();
    }
    
    if(empty($more["uid"])) $more["uid"]=getSessionVar('UID');

    $moreString = json_encode($more);
    $roid=$this->xset->procInput(array("amoid"=>$moid, 
				       "ptime"=>$date,
				       "more"=>$moreString,
				       "title"=>$title,
				       "_nojournal"=>true,
				       "tplentry"=>TZR_RETURN_DATA,
				       "cron"=>$cron,
				       "status"=>$type,
				       "rem"=>$remark,
				       "file"=>$tarfile));
    if(!empty($tarfile)) \Seolan\Library\Dir::unlink($tarfile,true,true);
    return $roid;
  }

  /// rend vrai si il existe un job en cron pour le module en question, avec la fonction $fnbet le type $status (cron ou scheduled)
  public function isThereACronJob(string $moid, string $fn, string $status="cron") {
    return getDB()->count("select count(*) from TASKS where amoid=? and JSON_VALUE(more, '$.function')=? AND status=?",[$moid, $fn, $status])>=1;
  }
  
  /* création d'une tâche dans le scheduler
   * type = scheduled ou cron
   * uid : oid ou alias de l'utilisateur sous lequel la tâche va s'exécuter. Si NULL c'est l'utilisateur courant.
   * date : date d'eécution de la tâche si type = scheduled
   * files : fichiers attachés
   * moid : identifiant du module qui supporte la fonction  a exécuter. 
   * fn : méthode à exécuter
   * period : seuelement si type=cron, daily/monthly/hourly
   * freq : seuelement si type=cron,  * /4 toutes les 4 heures si daily
   */
  public function createSimpleJob(string $type, $moid, string $fn, $date=NULL, $uid=NULL, $title, $remark="", $files=NULL,$period=NULL, $freq=NULL, $more=[]) {
    \Seolan\Core\Logs::notice(__METHOD__,"creating job for module #{$moid} title {$title} periodicity {$period} {$freq}");
    // date d'exécution de la tâche
    if(empty($date)) $date=date("Y-m-d H:i:s");

    // fichiers attachés
    $tarfile=NULL;
    if(!empty($files)){
      if(is_array($files)) $tarfile=\Seolan\Core\System::tarfiles($files);
      else $tarfile=$files;
    }

    // utilisateur qui exécute la tâche
    if(!empty($uid) && !\Seolan\Core\Kernel::isAKoid($uid)) {
      $u1=new \Seolan\Core\User($uid);
      if(is_object($u1)) $uid=$u1->uid();
    }
    if(empty($uid)) $uid=getSessionVar('UID');
    $more['uid']=$uid;
    $more['function']=$fn;

    // si pas de module on rend faux
    if(empty($moid)) return NULL;
    
    // si le job existe déjà on rend faux
    if($this->isThereACronJob($moid, $fn, $type)) return NULL;
    
    // création de la tâche
    $roid=$this->xset->procInput(array("amoid"=>$moid, 
				       "ptime"=>$date,
				       "more"=>json_encode($more),
				       "title"=>$title,
				       "_nojournal"=>true,
				       "tplentry"=>TZR_RETURN_DATA,
				       "cron"=>json_encode($type=="cron"?["period"=>$period, "freq"=>$freq]:""),
				       "status"=>$type,
				       "rem"=>$remark,
				       "file"=>$tarfile));
    if(!empty($tarfile)) \Seolan\Library\Dir::unlink($tarfile,true,true);
    return $roid;
  }


  /// verifie s'il faut planifier des taches régulieres
  private function checkCronJobs($ar=NULL) {
    // selection d'un job a exécuter
    $rs=getDB()->fetchAll("SELECT * FROM TASKS  where status = 'cron'");
    foreach($rs as $ors) {
      // Verifie le format xml de la tache
      if(!empty($ors['cron']))
	$cron = json_decode($ors['cron'], true);
      else
	continue;

      $period=@$cron["period"];
      if(empty($period)) break;

      $cnt2=getDB()->count("SELECT COUNT(*) FROM TASKS WHERE status = 'scheduled' AND title=? AND cron=?",array($ors['title'],$period));

      if($cnt2<=0) {
        $nt = self::getNextDateFromParam($cron);
	if(!empty($nt)) {
	  $omore=json_decode($ors['more'], true);
	  $this->createJob($ors['amoid'], $nt, $ors['title'], $omore, "Scheduled from cron", NULL, $period);
	}
      }
    }
    unset($rs);
  }

  static function getNextDateFromParam($cron, $baseTimestamp = null) {
    if(!$baseTimestamp) $baseTimestamp = time();
    $period=@$cron["period"];

    if($cron["freq"]) {
      if($period=="weekly")     $factor = 60*60*24*7;
      elseif($period=="daily")  $factor = 60*60;
      elseif($period=="hourly") $factor = 60;
      else                      return false;
      $aspec=explode("/",$cron["freq"]);
      if(empty($aspec[1])) $aspec[1]="1";
      $factor = $factor*$aspec[1];
      if($factor && $factor>60) {
        return date("Y-m-d H:i:s", $baseTimestamp+$factor);
      }
    }

    if($period=="weekly" && $cron["day"] && $cron["time"]) {
      return date('Y-m-d H:i:s', strtotime('next '.$cron['day'].' '.$cron['time'].' '.date('Y-m-d', $baseTimestamp)));
    }
    elseif($period=="daily" && $cron["time"]) {
      return date("Y-m-d", $baseTimestamp+60*60*24)." ".$cron["time"];
    }
    elseif($period=="hourly" && $cron["time"]) {
      return date("Y-m-d H:", $baseTimestamp+60*60).$cron["time"];
    }
    else {
      return false;
    }
  }

  /// execution du job dont l'identifiant est passé en parametre
  public function executeJob($jobid,$delay=false) {
    // selection d'un job a exécuter
    $ors=getDB()->fetchRow("SELECT * FROM TASKS WHERE (status = 'scheduled' or status='idle') AND KOID=?",array($jobid));
    if($ors) {
      // le job doi être exécuté, on le passe en état running
      $ors['more']=$ors['more'];
      $olduser=NULL;
      if (substr($ors['more'], 0, 5) == '<?xml'){
	$more=\Seolan\Core\System::xml2array($ors['more']);
      } else {
	$more=json_decode($ors['more'], true);
	if ($more==null){
	  $this->setStatusJob($jobid, 'badspec');
	  \Seolan\Core\Logs::notice(__METHOD__,"executing job #$jobid module {$ors['amoid']} title {$ors['title']} bad task format : : ".json_last_error_msg());
	  return;
	}
      }
      if(empty($more["uid"])) {
	$this->setStatusJob($jobid, 'refused');
	\Seolan\Core\Logs::notice("\Seolan\Module\Scheduler\Scheduler::executeJob","executing job #$jobid module ".$ors['amoid'].
			  " title ".$ors['title']." : no user specified");
	return;
      }
      if ((isset($more['hmin']) && date('H')<$more['hmin']) || (isset($more['hmax']) && date('H')>$more['hmax'])){
          getDB()->execute('DELETE FROM TASKS where KOID=?', array($jobid));
          \Seolan\Core\Logs::notice("\Seolan\Module\Scheduler\Scheduler::executeJob","delete job #$jobid moid ".$ors['amoid'].
			" title ".$ors['title']." ".
			date('H')." out of defined interval {$more['hmin']} {$more['hmax']}");
          return;
      }
      if (isset($more['nice']) && \Seolan\Module\WaitingRoom\Queue::active()) {
        getDB()->execute('UPDATE TASKS SET ptime=?, status="scheduled", rem=? WHERE KOID=?',
          [date('Y-m-d H:i:s', strtotime('+5 min')), 'waiting room active, delayed', $jobid]);
        return;
      }
      $olduser=$GLOBALS['XUSER'];
      $GLOBALS['XUSER']=new \Seolan\Core\User(array("UID"=>$more["uid"]));
      setSessionVar("UID",$more["uid"]);

      $o = (object)$ors;
      $koid=$o->KOID;
      $pid=getmypid();
      getDB()->execute("UPDATE TASKS SET status = 'running', etime=NOW(), pid=? where KOID=?",array($pid, $koid),false);
      \Seolan\Core\Logs::notice("\Seolan\Module\Scheduler\Scheduler::executeJob","executing job #$koid module ".$ors['amoid'].
			" title ".$ors['title']." ".
			"as user uid ".\Seolan\Core\User::get_current_user_uid()." pid ".$pid);
      if(!empty($ors['amoid'])) {
	$mod = \Seolan\Core\Module\Module::objectFactory($ors['amoid']);
	$todo=true;
	\Seolan\Core\Logs::debug("iptask ".$mod->iptask);
	if(!empty($mod->iptask)) {
	  $hostname=@system('hostname');
	  $ips=gethostbynamel($hostname);
	  if(!in_array($mod->iptask, $ips)) {
	    \Seolan\Core\Logs::debug("cron: host $hostname ip ".$mod->iptask." no");
	    $todo=false;
	  } else {
	    \Seolan\Core\Logs::debug("cron: host $hostname ip ".$mod->iptask." yes");
	  }
	}
      } else {
	if(empty($more['class'])) $mod=$this;
	else {
	  $class=$more['class'];
	  $mod = new $class();
	}
	$todo=true;
      }
      if($todo) {
	$f=$more["function"];
	$omore=(object)$more;

	// decodage des fichiers
	if(!emptyOrUnchanged($ors['file'])) {
	  $file=$this->xset->getField('file');
	  $f1=$file->display($ors['file']);
          if (in_array($f1->decoded_raw->mime, ['application/gzip', 'application/x-gtar-compressed'])) {
	    $o->file=\Seolan\Core\System::untarfiles($f1->filename);
          } else {
            $o->file = $f1->filename;
          }
	}
	// exécution de la fonction
	if($mod->secure('',$f)){
	  \Seolan\Core\Logs::notice("\Seolan\Module\Scheduler\Scheduler::executeJob","security check ok (2)");
	  try{
	    // niveau de log définit dans la tache
	    if (!empty($more['debug_mode'])) {
	      $old_mask = \Seolan\Core\Logs::$_logger->getMask();
	      \Seolan\Core\Logs::_setLevel($more['debug_mode']);
	    }
	    $comm=$mod->$f($this, $o, $omore);
	    if (!empty($old_mask))  \Seolan\Core\Logs::$_logger->setMask($old_mask);
	  }catch(\Throwable $ex){
	    $error_detail = $ex->getMessage()."\n=====\n".$ex->getTraceAsString();
	    $this->setStatusJob($koid, 'crashed', 'Error during task execution : '.$error_detail);
	    bugWarning('xmodscheduler: exception during task execution '.get_class($mod).'->'.$f.' moid='.$ors['amoid'].' '.$error_detail,false, false);
	  } 
	} else {
	  \Seolan\Library\Security::warning('security attempt (2) in \Seolan\Module\Scheduler\Scheduler '.get_class($mod).'->'.$f.' moid='.$ors['amoid']);
	  $this->setStatusJob($koid, 'refused');
	}
	if(!empty($ors['file']['tzr-dir']))
	  \Seolan\Library\Dir::unlink($ors['file']['tzr-dir'],true,true);

	if(!empty($olduser)) {
	  setSessionVar("UID",$olduser->uid());
	  $GLOBALS["XUSER"]=$olduser;
	}
	// on ne doit jamais laisser un job en running. On corrige les erreurs eventuelles
	// des implenteurs des fonctions
      }

      $this->statusJob($koid, $status);
      \Seolan\Core\Logs::notice(__METHOD__, ' check job status '.$status);
      if($status=='running') $this->setStatusJob($koid, 'finished', $comm);
    }
  }


  /// execution d'un job
  public function selectJob($ar=NULL) {
    $x = new \Seolan\Core\Param($ar, array("execute"=>false));

    // suppression des entrees trop anciennes
    getDB()->execute("DELETE FROM TASKS WHERE STATUS = 'finished' AND etime < DATE_SUB(NOW(), INTERVAL 2 DAY) ",array(),false);
    getDB()->execute("DELETE FROM TASKS WHERE STATUS = 'refused' AND etime < DATE_SUB(NOW(), INTERVAL 7 DAY) ",array(),false);
    getDB()->execute("DELETE FROM TASKS WHERE STATUS = 'crashed' AND etime < DATE_SUB(NOW(), INTERVAL 7 DAY) ",array(),false);

    // pour chaque job en running on verifie que le job est toujours vivant
    $rs=getDB()->fetchAll('SELECT KOID,pid,title FROM TASKS WHERE status="running"');
    $nbrunning=0;
    foreach($rs as $ors) {
      if(!checkPid($ors['pid'])) {
	getDB()->execute('UPDATE TASKS SET status="crashed" WHERE KOID=?',array($ors['KOID']));
	bugWarning('xmodscheduler: job '.$ors['KOID'].','.$ors['title'].' pid '.$ors['pid'].' disappeared, status set from running to failed',false,false);
      } else {
	$nbrunning++;
      }
    }
    unset($rs);

    if (TZR_XMODSCHEDULER_TASKDURATION > 0){
      $cnt=getDB()->count('SELECT COUNT(DISTINCT KOID) FROM TASKS WHERE status = \'running\' and UPD < DATE_SUB(NOW(), INTERVAL '.TZR_XMODSCHEDULER_TASKDURATION.' MINUTE)',array());
    } else {
      $cnt=getDB()->count("SELECT COUNT(DISTINCT KOID) FROM TASKS WHERE status = 'running'",array());
    }
    if($cnt>TZR_XMODSCHEDULER_RUNNINGPENDING) {
      bugWarning('xmodscheduler: running job is stucked');
      exit(0);
    }
    // set du user root pour les daemon et les checks
    \Seolan\Core\Logs::notice(get_class($this), '::selectJob set root user');
    $olduser=$GLOBALS['XUSER'];
    $GLOBALS['XUSER']=new \Seolan\Core\User('root');
    setSessionVar('root', 1);
    setSessionVar('UID',$GLOBALS['XUSER']->uid());

    // execution des tâches php en attente
    $batch = new \Seolan\Core\Batch();
    $batch->execute();

    // verification de la console et des taches de nettoyage
    $this->checkConsole();
    // mise à jour des usedValues
    \Seolan\Core\DbIni::updateUsedValues();

    // nettoyage des tokens périmés
    \Seolan\Core\Token::factory()->purge();

    
    // lancement des daemons des application
    $appDaemons = ['any'=>[], 'daily'=>[]];
    $this->processApplicationDaemons($appDaemons);

    // lancement des daemons journaliers
    if(\Seolan\Core\DbIni::get('xmodscheduler:daily','val')!=date("Ymd")) {
      // execution des taches journalieres
      \Seolan\Core\DbIni::set('xmodscheduler:daily',date('Ymd'));
      try{
	\Seolan\Core\Logs::notice('\Seolan\Module\Scheduler\Scheduler::selectJob','running daily tasks');
	\Seolan\Core\Module\Module::daemon('daily', $appDaemons['daily']);
      }catch(Exception $ex){
	bugWarning('xmodscheduler: exception during daemon daily : '.$ex->getMessage(), false, false);
      }
    }

    // lancement des daemons permanents
    try{
      \Seolan\Core\Module\Module::daemon('any', $appDaemons['any']);
    } catch(\Exception $ex){
      bugWarning('xmodscheduler: exception during daemon any : '.$ex->getMessage(), false, false);
    }

    // verification des fichiers a importer
    \Seolan\Core\Logs::notice('scheduler','running imports');
    $this->runImports();

    // reset du USER (users:0)
    if(!empty($olduser)) {
      setSessionVar("UID",$olduser->uid());
      $GLOBALS["XUSER"]=$olduser;
      clearSessionVar('root');
    } else {
      clearSessionVar('UID');
      $GLOBALS["XUSER"]=NULL;
      clearSessionVar('root');
    }
    \Seolan\Core\Logs::notice(get_class($this), '::selectJob unset root user');

    // verification des crons regulières pour création de nouvelles tâches
    $this->checkCronJobs();
    
    // selection d'un job a exécuter
    while($ors=getDB()->fetchRow("SELECT DISTINCT KOID FROM TASKS WHERE status = 'scheduled' AND ptime <= NOW() ORDER BY ptime ASC")) {
      // le job doit être exécuté, on le passe en état running
      $koid=$ors['KOID'];
      $this->executeJob($koid,true);
      // verification des crons regulières pour création de nouvelles tâches
      $this->checkCronJobs();
    }

    // execution des jobs idle
    while($ors=getDB()->fetchRow("SELECT DISTINCT KOID FROM TASKS WHERE status = 'idle' ORDER BY UPD ASC")) {
      // le job doit être exécuté, on le passe en état running
      $koid=$ors['KOID'];
      $this->executeJob($koid,false);
    }

  }
  /// traitement des démons des applications
  protected function processApplicationDaemons(&$appDaemons){
    if (TZR_USE_APP){
      $modApp = \Seolan\Core\Module\Module::singletonFactory(XMODAPP_TOID);
      if(\Seolan\Core\DbIni::get('application:dailyscheduler','val')!=date("Ymd")) {
	\Seolan\Core\DbIni::set('application:dailyscheduler',date('Ymd'));
	try{
	  $appDaemons['daily'] = $modApp->app_daemon('daily');
	}catch(Exception $ex){
	  bugWarning('xmodscheduler: exception during application daemon daily : '.$ex->getMessage(), false, false);
	}
      }
      try{
	$appDaemons['any'] = $modApp->app_daemon('any');
      } catch(\Exception $ex){
	bugWarning('xmodscheduler: exception during application daemon any : '.$ex->getMessage(), false, false);
      }
    }
  }
  /// methode de verification de cohérence et de nettoyage de la console
  protected function checkConsole() {
    if(!$lck=\Seolan\Library\Lock::getLock('checkconsole')){
      \Seolan\Core\Logs::notice(__METHOD__,'unabled to gain lock "checkconsole"');
      return false;
    }
    if(\Seolan\Core\DbIni::get('xmodscheduler:lastchk2','val')!=date("Ymd")) {
      // execution des taches journalieres
      \Seolan\Core\DbIni::set('xmodscheduler:lastchk2',date('Ymd'));
      \Seolan\Core\Logs::notice('\Seolan\Module\Scheduler\Scheduler','checking the modules');
      try {
	$report="";
	$error=false;
	if(isset($GLOBALS['XREPLI'])){
	  $report = "Journalisation suspendue\n";
	  \Seolan\Core\Logs::notice('\Seolan\Module\Scheduler\Scheduler', 'Journalisation suspendue');
	  $GLOBALS['XREPLI']->suspended = true;
	}
	if(!\Seolan\Core\Ini::get('nocheck')) {
	  \Seolan\Core\Integrity::chkDatabases($report);
	  \Seolan\Core\Integrity::chkModules($report);
	  \Seolan\Core\Integrity::chkOpts($report);
	  // Calcul de la place occupee
	  $workspace=0;
	  $rs=getDB()->fetchCol('select BTAB from BASEBASE');
	  foreach($rs as $ors) {
	    $dir=$ors;
	    $space=\Seolan\Library\Dir::du($GLOBALS['DATA_DIR'].$dir);
	    $workspace+=$space;
	    $aspace=\Seolan\Library\Dir::du($GLOBALS['DATA_DIR'].'A_'.$dir);
	    $workspace+=$aspace;
	    \Seolan\Core\DbIni::set('xmodadmin:workspacesize_'.$dir,$space);
	    \Seolan\Core\DbIni::set('xmodadmin:workspacesize_A_'.$dir,$aspace);
	  }
	  unset($rs);
	  \Seolan\Core\DbIni::set('xmodadmin:workspacesize',$workspace);
	}
	// vérification de la protection des répertoires
	\Seolan\Core\Integrity::checkDataDirAccess($report);
	// verification d'existence des principaux repertoires
	\Seolan\Core\Integrity::chkDirs($report);
	// verification de version des cgi
	\Seolan\Core\Integrity::chkCGIs($report, $error);
	\Seolan\Core\Integrity::chkParameters($report);
	\Seolan\Core\Integrity::chkLogs($report, $error);
	// Rotation des fichiers d'archive
	\Seolan\Core\Archive::rotate();
	// Nettoyage des fichiers temporaires
	\Seolan\Library\Dir::clean(TZR_TMP_DIR, TZR_PAGE_EXPIRES);
	// Nettoyage des fichiers temporaires
	\Seolan\Library\Dir::clean(TZR_DBCACHE_DIR, TZR_PAGE_EXPIRES);
	// Nettoyage des fichiers en cache serveur
	\Seolan\Library\Dir::clean(CACHE_BASE_DIR, TZR_PAGE_EXPIRES*3);

	if (isset($GLOBALS['HAS_VHOSTS']) && empty($GLOBALS['IS_VHOST']))
	  \Seolan\Module\MiniSite\MiniSite::clean();

	if(isset($GLOBALS['XREPLI'])){
	  $report .= "Journalisation réactivée\n";
	  \Seolan\Core\Logs::notice('\Seolan\Module\Scheduler\Scheduler', 'Journalisation réactivée');
	  $GLOBALS['XREPLI']->suspended = false;
	}
	\Seolan\Core\Integrity::sendReport($report,$error);
	// Verification de la construction du moteur de recherche
	$search=\Seolan\Library\SolR\Search::objectFactory();
	$search->optimize();
      } catch(Exception $e) {
        $report .= "\n---------- CHECK FAILED ----------\n" .
          $e->getMessage()."\n\n".
          "BACKTRACE:\n".
          $e->getTraceAsString();
	\Seolan\Core\Integrity::sendReport($report,true);
      }
      
      // Disponibilité Search V2 : il y a un solr v2 de dispo
      if (!\Seolan\Library\SolR\SearchV2::v2Ready())
	\Seolan\Library\SolR\Helper::checkVersion();
      if (\Seolan\Core\Ini::get('solr_activated')
	  && \Seolan\Library\SolR\SearchV2::v2Ready())
	\Seolan\Library\SolR\Helper::checkV2ModuleReady();

    }
    
    // Verification de la construction du moteur de recherche
    $search=\Seolan\Library\SolR\Search::objectFactory();
    $search->checkCore();
    $search->checkIndex(null,null,$more=(object)array('check'=>false,'cond'=>'UPD'));
    
    // Vidage de la table _TMP
    if(\Seolan\Core\DataSource\DataSource::sourceExists('_TMP')){
      getDB()->execute('delete FROM _TMP where UPD<if(vtime is null or vtime=0,date_sub(NOW(),interval 60 minute),date_sub(NOW(),interval vtime minute))');
    }
    // Vidage du cache utilisateur innactif
    \Seolan\Core\User::clearOldDbSessionDataAndRightCache();
    // Vérification des mail retournés en erreur
    \Seolan\Library\Mail::sendQueuedMails();

    \Seolan\Library\Lock::releaseLock($lck);

    return true;
  }

  /// rend l'etat ou positionne l'etat d'un job
  /** $koid est le nom du job
   $status est le status que l'on veut affecter
   si $ctxt=NULL alors on rend l'état sinon on le positionne
  */
  public function statusJob($koid, &$status, $ctxt=NULL) {
    $ors=getDB()->fetchRow("select * from TASKS where KOID=?",array($koid));
    // traitement des cas où la tâche est finie
    if(!$ors) {
      $status="cancelled";
      return false;
    }
    if($ors['status']=="cancelled") {
      $status="cancelled";
      return false;
    }
    $status=$ors['status'];
    if($ctxt==NULL) {
      $octxt = unserialize(stripslashes($ors['ctxt']));
      return $octxt;
    } else {
      getDB()->execute("UPDATE TASKS set ctxt=? where KOID=?",array(serialize($ctxt),$koid),false);
    }
  }

  /// change l'etat d'un job - notifie si demandé
  public function setStatusJob($koid, $status, $comment='', $notificationStatus=NULL) {
    $rs = getDB()->select("SELECT * FROM TASKS WHERE KOID=?",array($koid));
    // traitement des cas où la tâche est finie
    if(!$rs || $rs->rowCount() != 1) {
      $status="cancelled";
      return false;
    }
    if (mb_check_encoding($comment, 'UTF-8')) {
      $plus = '';
      if (strlen($comment)>60000) $plus='...';
      $comment = substr($comment,0,60000).$plus;
    } else {
      $comment = '';
    }
    try {
      if($status=='running') {
        $pid=getmypid();
        getDB()->execute("UPDATE IGNORE TASKS SET status=?, rem=ifnull(concat(rem, '\n', ?), ?),pid=?  WHERE KOID=?",array($status, $comment, $comment, $pid, $koid),false);
      } else {
        getDB()->execute("UPDATE IGNORE TASKS SET status=?, rem=ifnull(concat(rem, '\n', ?), ?)  WHERE KOID=?",array($status, $comment, $comment, $koid),false);
      }
    } catch(\Exception $e) {
      \Seolan\Core\Logs::critical(__METHOD__." Task $koid update comment error: " . $e->getMessage());
      if($status=='running') {
        $pid=getmypid();
        getDB()->execute("UPDATE IGNORE TASKS SET status=?, pid=?  WHERE KOID=?",array($status, $pid, $koid),false);
      } else {
        getDB()->execute("UPDATE IGNORE TASKS SET status=? WHERE KOID=?",array($status, $koid),false);
      }
    }
    
    \Seolan\Core\Logs::notice('\Seolan\Module\Scheduler\Scheduler::setStatusJob',"jobs #$koid set to $status");
    // notifications
    if ($status == 'finished' and $notificationStatus != NULL){
      $this->taskNotifications($rs->fetch(), $comment, $notificationStatus);
    }
  
    return true;
  }
  /// envoi des notifications de fin de tache
  function taskNotifications($ors, $comment, $notificationStatus){
    $more=json_decode($ors['more'], true);
    $recipients = NULL;
    if (!is_array($notificationStatus)){
      $notificationStatus = array($notificationStatus);
    }
    foreach($notificationStatus as $nstatus){
      if (isset($more[$nstatus.'_notification']['recipients'])){
	$recipients = $more[$nstatus.'_notification']['recipients'];
      }
      if (is_array($recipients) && count($recipients)>0){
	\Seolan\Core\Logs::notice(__METHOD__, 'notifications '.$nstatus);
	$mail = new \Seolan\Library\Mail();
	$mail->isHTML = true;
	if (isset($more[$nstatus.'_notification']['from'])){
	  list($femail, $fname) = explode(';', $more[$nstatus.'_notification']['from']);
	  $mail->From = $femail;
	  $mail->FromName = $fname;
	}
	if (isset($more[$nstatus.'_notification']['subject'])){
	  $mail->Subject = $more[$nstatus.'_notification']['subject'];
	} else {
	  $mail->Subject = $ors['title'].'('.$nstatus.')';
	}
	if (isset($more[$nstatus.'_notification']['body'])){
	  $mail->setTZRBody($mail->Subject.'<hr><pre style="align:left">'.$more[$nstatus.'_notification']['body'].'</pre>');
	} else {
	  $mail->setTZRBody($mail->Subject.'<hr><pre style="align:left">'.$comment.'</pre>');
	}
	foreach($recipients as $recipient){
	  list($email, $name) = explode(';', $recipient);
	  $mail->AddBCC($email, $name);
	}
	$mail->Send();
      }
    }
  }
  /// lance les imports automatisés
  private function runImports() {
    if(\Seolan\Core\DataSource\DataSource::sourceExists('IMPORTS')) {
      $rs=getDB()->select("SELECT * FROM IMPORTS WHERE auto='1' ORDER BY ID");
      while($ors=$rs->fetch()) {
	$mod = \Seolan\Core\Module\Module::objectFactory($ors['modid']);
	$xml = json_decode($ors['spec']);
	$mod->import(array("spec"=>$ors['ID']));
      }
    }
  }

  /// liste des actions disponibles dans le BO
  function _actionlist(&$my, $alfunction=true) {
    parent::_actionlist($my);
    $uniqid=\Seolan\Core\Shell::uniqid();
    $f=\Seolan\Core\Shell::_function();
    if($this->secure('','edit') && in_array($f, array('browse', 'procQuery'))){
      $o1=new \Seolan\Core\Module\Action($this,'runasap',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Scheduler_Scheduler','runasap','text'),
					 'javascript:TZR.Table.applyfunction("'.$uniqid.'","runAsap",false,{},true,false);','actions');
      $o1->containable=false;
      $o1->menuable = true;
      $my['runasap']=$o1;
    }
  }
  function menu($ar=NULL) {
    $menu['browse']['actions'][]=
      array(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Scheduler_Scheduler','runasap'),
	    "javascript:applyselected('runAsap');");
	return     \Seolan\Core\Shell::toScreen1("menu",$menu);
  }


  static function createTasks() {
    if(\Seolan\Core\System::tableExists('TASKS')) {
      return;
    }
    $lg = TZR_DEFAULT_LANG;
    $ar1["translatable"]="1";
    $ar1["auto_translate"]="1";
    $ar1["btab"]='TASKS';
    $ar1["bname"][$lg]='System - '.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','tasks');
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TASKS');
    //                                                          ord obl que bro tra mul pub tar
    $x->createField('title','Tâche','\Seolan\Field\ShortText\ShortText',         '60','3','1','1','1','0','0','1');
    $x->createField('ptime','Date prévue','\Seolan\Field\DateTime\DateTime',   '','4','1','0','1','0','0','0');
    $x->createField('etime','Date exécution','\Seolan\Field\DateTime\DateTime','','5','1','0','1','0','0','0');
    $x->createField('more','Autres éléments','\Seolan\Field\Text\Text',      '60','6','0','0','0','0','0','0');
    $x->createField('status','Etat','\Seolan\Field\ShortText\ShortText',         '60','7','0','0','0','0','0','0');
    $x->createField('file','Fichier','\Seolan\Field\File\File',              '','8','0','0','0','0','0','0');
    $x->createField('rem','Commentaires','\Seolan\Field\Text\Text',          '60','9','0','0','0','0','0','0');
    $x->createField('ctxt','Données temporaires','\Seolan\Field\Text\Text',  '60','10','0','0','0','0','0','0');
    $x->createField('amoid','Module concerné','\Seolan\Field\Module\Module',      '','11','1','0','0','0','0','0');
    $x->createField('cron',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Scheduler_Scheduler','scheduler'),'\Seolan\Field\Text\Text','60','12','0','0','0','0','0','0');
  }


  // creation d'un job tache externe
  //
  function createIdleShellJob($id, $title, $command, $remark){
    \Seolan\Core\Logs::notice('scheduler',"creating job for task $command title $title");
    // verifier que la tache existe pas deja
    $s = $this->xset->select_query(array('cond'=>array('status'=>['!=','finished'],'more'=>array('like',"%$id%"))));
    $cnt = getDB()->count($s,array(),true);
    if ($cnt > 0){
      \Seolan\Core\Logs::notice('scheduler',"task id $id already queued ");
      return;
    }
    // creer la tache
    $moid = NULL;
    $tarfile=NULL;
    if(empty($date)) $date=date('Y-m-d H:i:s');
    $more = array();
    $more['id'] = $id;
    $more['command'] = $command;
    $more['function']='shellTask';
    $more['uid']=getSessionVar('UID');
    if(empty($more['uid'])) $more['uid']=TZR_USERID_NOBODY;

    \Seolan\Core\System::array2xml($more, $xml);
    $roid=$this->xset->procInput(array('amoid'=>$this->_moid,
				       'ptime'=>$date,
				       'more'=>$xml,
				       'title'=>$title,
				       '_nojournal'=>true,
				       'tplentry'=>TZR_RETURN_DATA,
				       'cron'=>'',
				       'status'=>'idle',
				       'file'=>'',
				       '_local' => true,
				       'options' => array('more' => array('raw' => true))
				       ));
    return $roid;
  }

  // execution du tache shell
  //
  private function shellTask(\Seolan\Module\Scheduler\Scheduler &$scheduler, &$o, &$more) {
    $command=$more->command;
    try {
      $ret=syscall($command);
    }
    catch(\Exception $e) {
      $scheduler->setStatusJob($o->KOID, 'crashed');
      bugWarning($command.' crashed');
    }
    $scheduler->setStatusJob($o->KOID, 'finished',$ret);
  }

  /// vérification que le module est en route et qu'il tourne correctement
  public function getPublicConfig($ar=NULL){
    $ret=parent::getPublicConfig($ar);
    $all=getDB()->fetchAll("select * from TASKS WHERE (STATUS = 'refused' OR STATUS='crashed') AND UPD > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    if(count($all)>0) {
      $ret['errors']=array(count($all).' tasks failed');
    }
    // on vérifie que la crontab est bien passée au moins une fois depuis le début de la journée, et qu'il est 8h passée
    if(\Seolan\Core\DbIni::get('xmodscheduler:lastchk2','val')!=date("Ymd") && date("H")>"08") {
      $ret['errors'][]='scheduler not activated';
    }
    return $ret;
  }
}

?>
