<?php
// Gestion des logs

namespace Seolan\Core;

class Logs {
  static $_logger=NULL;
  static $_writer=NULL;
  static $_upgrade_logger=NULL;
  static $_upgrade_writer=NULL;
  static $_xset=NULL;
  static $_firephp=NULL;
  static $_filters=NULL;
  public static $upgrades = ['20200220'=>''];

  /// Initialise le fichier
  static function &_initLogFile(){
    // On vérifie qu'un Writter est toujours associé au Logger sinon une Exception est générée dans XDbIni::__destruct
    if(empty(self::$_logger) || self::$_logger->getWriters()->count() === 0) {
      self::$_writer = new \Zend\Log\Writer\Stream(TZR_LOG_FILE);
      self::$_logger = new \Zend\Log\Logger();
      self::$_logger->addWriter(self::$_writer);
      if(is_int(constant(TZR_LOG_LEVEL))) {
	$filter = new \Zend\Log\Filter\Priority(constant(TZR_LOG_LEVEL));
	self::$_writer->addFilter($filter);
      }
      if(TZR_DEBUG_MODE==1) self::$_firephp=new \FirePHP();
      if(isset($GLOBALS['TZR_LOG_FILTERS'])) self::setFilter($GLOBALS['TZR_LOG_FILTERS']);
    }
    return self::$_logger;
  }

  /// Initialise le fichier
  static function &_initUpgradeLogFile(){
    if(empty(self::$_upgrade_logger)) {
      self::$_upgrade_writer = new \Zend\Log\Writer\Stream(TZR_UPGRADE_LOG_FILE);
      self::$_upgrade_logger = new \Zend\Log\Logger();
      self::$_upgrade_logger->addWriter(self::$_upgrade_writer);
      $filter = new \Zend\Log\Filter\Priority(\Zend\log\Logger::DEBUG);
      self::$_upgrade_writer->addFilter($filter);
    }
    return self::$_upgrade_logger;
  }

  /// Envoi une trace via FirePHP/FireBug
  static function fb($obj,$label=null){
    if(!\Seolan\Core\Logs::$_firephp) return;
    \Seolan\Core\Logs::$_firephp->info($obj,$label);
  }

  /// change le niveau de log
  /// @param string $priority PEAR_LOG_*
  static function _setLevel($priority) {
    if (is_string($priority))
      $priority = constant($priority);
    $filter = new \Zend\Log\Filter\Priority($priority);
    self::$_writer->addFilter($filter);
  }

  /// Envoi d'une notice dans le fichier de log
  public static function notice($prefix, $s='') {
    self::_initLogFile();
    $moid='';
    if(!empty($_REQUEST['moid'])) $moid=$_REQUEST['moid'];
    if(!empty($_REQUEST['_moid'])) $moid=$_REQUEST['_moid'];
    if (!empty($moid))
        $s = 'moid '.$moid.' '.$s;
    $memory=get_memory();

    self::$_logger->notice($prefix.','.$memory.'MB pid='.getmypid().' '.$s);
  }

  /// Envoi d'une alerte dans le fichier de log. Toujours affichee quel que soit le niveau de log
  public static function critical($prefix, $s='', $backtrace=false) {
    self::_initLogFile();
    $moid='';
    if(!empty($_REQUEST['moid'])) $moid=$_REQUEST['moid'];
    if(!empty($_REQUEST['_moid'])) $moid=$_REQUEST['_moid'];
    if (!empty($moid))
      $s = 'moid '.$moid.' '.$s;
    $memory=get_memory();
    self::$_logger->crit($prefix.' '.$memory.'MB pid='.getmypid().' '.$s);
    if($backtrace) self::$_logger->crit(backtrace2());
    if (\Seolan\Core\Shell::isRoot()) \Seolan\Core\Shell::alert($prefix.': '.$s);
  }

  /// Envoi d'un message de debug dans le fichier de log. N'est prise en compte que si TZR_LOG_LEVEL est positionne a TZR_LOG_DEBUG
  public static function debug($msg) {
    self::_initLogFile();
    if (is_object($msg) ) {
        self::$_logger->debug($msg);
    } else{
      if(self::filtered($msg)) {
	$elapsed=(int)((System::getmicrotime()-TZR_START_TIME)*1000);
	$memory=get_memory();
	try {
          self::$_logger->debug($elapsed.'ms '.$memory.'MB pid='.getmypid().' '. self::getDebugPrefix() .' '.$msg);
	} catch(\Exception $e) {
	}
      }
    }
  }

  private static function getDebugPrefix() {
    static $prefix = NULL;
    if (!$prefix) {
      if (empty($_SERVER['REMOTE_ADDR'])) {
        $prefix = getmypid();
      } else {
        $prefix = $_SERVER['REMOTE_ADDR'];
        if (class_exists('\Seolan\Core\User') && \Seolan\Core\User::authentified()) {
          $prefix .= ' ' . \Seolan\Core\User::get_current_user_uid();
        }
      }
    }
    return $prefix;
  }

  /// filtrage des logs
  public static function setFilter(array $filters) {
    if(!empty($filters)) self::$_filters='^('.implode('|',$filters).')';
    else self::$_filters=NULL;
  }

  /// indique si un message doit être loggué ou pas en fonction du filtre
  public static function filtered($msg) {
    if(self::$_filters && preg_match('@'.self::$_filters.'@i', $msg)) {
      return true;
    } else return false;
  }

  /// Effectue la rotation des fichiers de log
  static function rotate() {
    if(empty(self::$_logger)) self::_initLogFile();
    // Economiser de l'espace sur les sites avec minisites : zipper tous les anciens logs
    if ($GLOBALS['HAS_VHOSTS'] || $GLOBALS['IS_VHOST']) {
      \Seolan\Library\Dir::rotate(TZR_LOG_DIR,0,TZR_LOG_ROTATE);
    } else {
      \Seolan\Library\Dir::rotate(TZR_LOG_DIR,1,TZR_LOG_ROTATE);
    }
  }

  function __construct($ar=NULL) {
  }

  /// Recherche du journal des modifs d'un enregistrement dont l'oid est passé en paramètre
  static function getJournal($oid,$select=NULL, $datefrom=NULL, $dateto=NULL, $formatwithds=NULL, $fieldssec=NULL, $maxrecords=1000) {
    if(empty(self::$_xset)) self::$_xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS=LOGS');
    if($fieldssec===NULL) $fieldssec=array();
    $cond=array();
    if(!empty($select)) $cond=$select;
    $cond['object']=array('=',$oid);
    if(!empty($datefrom)) {
      $cond['UPD']=array('>=',$datefrom);
    }
    if(!empty($dateto)) {
      $cond['UPD ']=array('<=',$dateto);
    }
    $labelsmap = ['delete'=>'deletion','movefromtrash'=>'undeletion','update'=>'update','create'=>'create','autoupdate'=>'auto_update'];
    $query=self::$_xset->select_query(array('cond'=>$cond));
    $p1=array('select'=>$query,'selected'=>0,'pagesize'=>$maxrecords,'order'=>'UPD DESC','tplentry'=>TZR_RETURN_DATA,'selectedfields'=>'all');
    $r=self::$_xset->browse($p1);

    if($formatwithds) {
      if (!\Seolan\Core\Shell::getMonoLang()) {
        $r['lines__lang']=array();
      }
      foreach($r['lines_odetails'] as $i => $o) {
        $arr=\Seolan\Core\System::xml2array($o->raw);
        $r['lines_journal'][$i]=array();
        $r['lines_fields'][$i]=array();
      	if ($r['lines_oetype'][$i]->raw == 'update'){
      	  foreach($arr as $field => $change) {
      	    if ($field == 'LANG'){
      	      continue;
      	    }
      	    $ofield = $formatwithds->getField($field);
      	    if(is_object($ofield)) {
      	      if(!empty($fieldssec) && @$fieldssec[$ofield->field]=='none') {
      		$r['lines_journal'][$i][]='<b>'.$ofield->label.'</b> ('.$ofield->field.') : *****';
      	      } else {
      		$r['lines_journal'][$i][]='<b>'.$ofield->label.'</b> ('.$ofield->field.') : '.$change;
      	      }
      	      $r['lines_fields'][$i][]=$ofield->field;
      	    } else {
      	      $r['lines_journal'][$i][]='<b>?</b> ('.$field.') : '.$change;
      	      $r['lines_fields'][$i][]='?';
      	    }
      	  }
      	}
      	if (isset($labelsmap[$r['lines_oetype'][$i]->raw])){
      	  $labelname = $labelsmap[$r['lines_oetype'][$i]->raw];
      	} else {
      	  $labelname = $r['lines_oetype'][$i]->raw;
      	}
        $r['lines_elabel'][$i]=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',$labelname,'text');
        if (isset($arr['LANG']) && isset($r['lines__lang'])){
          $r['lines__lang'][$i] .= $arr['LANG'];
        }
      }
    }
    return $r;
  }

  /// Rend un tableau contenant le log de création d'un oid
  static function &getCreated($oid) {
    $ret=array();
    if(empty($oid)) return $ret;
    $LANG_DATA=\Seolan\Core\Shell::getLangData();
    return getDB()->fetchRow('SELECT * FROM LOGS WHERE object=? and LANG=? and etype="create" order by dateupd desc limit 0,1',
			     [$oid,$LANG_DATA]);
  }


  /// dernière mise a jour d'un champ
  static function lastFieldUpdate($oid, $field) {
    return getDB()->fetchOne('SELECT MAX(UPD) from LOGS where object=? and etype in ("create","update") and details like "%<th>'.$field.'</th>%"', [$oid]);
  }

  /// Decode d'une entree du journal
  static function decodeEvent($ors) {
    $details=\Seolan\Core\System::xml2array($ors['details']);
    $ors['details']=$details;
    return $ors;
  }

  /// Rend un tableau contenant la date et l'utilisateur de la derniere modification de l'object d'oid $oid
  static function getLastUpdate($oid,$default,$raw=false) {
    $ret=array();
    if(empty($oid)) return $ret;
    $LANG_DATA=\Seolan\Core\Shell::getLangData();
    $ors=getDB()->fetchRow('SELECT * FROM LOGS WHERE object=? and LANG=? and etype in ("create","update") order by dateupd desc limit 0,1',
                           array($oid,$LANG_DATA));
    if(!empty($ors)){
      if(!$raw){
	$ors['dateupd']=\Seolan\Field\Timestamp\Timestamp::dateFormat($ors['dateupd']);
	$ors['datecre']=\Seolan\Field\Timestamp\Timestamp::dateFormat($ors['datecre']);
      }
    }else {
      $table=\Seolan\Core\Kernel::getTable($oid);
      if(\Seolan\Core\System::tableExists($table)) {
        $o1=getDB()->fetchRow("SELECT KOID,UPD FROM $table WHERE KOID=? AND LANG=?",array($oid,$LANG_DATA));
        if(!$raw) {
          $o1['UPD']=\Seolan\Field\Timestamp\Timestamp::dateFormat($o1['UPD']);
        }
        $ors=array('dateupd'=>$o1['UPD'],'datecre'=>$default,'usernam'=>'','user'=>'','details'=>'');
      }
    }
    return $ors;
  }
  /// raccourci pour tracer un évènement de sécurité
  public static function secEvent($source, $details, $object=null){
    if(empty(self::$_xset))
      self::$_xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('LOGS');
    $ar = [];
    $ar['object'] = $object;
    $ar['datecre'] = $ar['dateupd'] = $ar['dateeve'] = date('Y-m-d H:i:s');
    $ar['etype'] = 'security';
    $ar['setuid'] = issetSessionVar('SUID')?getSessionVar('SUID'):'';
    if (!empty($GLOBALS['XUSER'])){
      $ar['user'] = $GLOBALS['XUSER']->_curoid;
      $ar['usernam'] = $GLOBALS['XUSER']->_cur['fullnam'];
    } else {
      $ar['user'] = '';
      $ar['usernam'] ='Anonymous';
    }
    $ar['comment'] = $source;
    $ar['details'] = $details;
    self::$_xset->procInput($ar);
  }
  /// Introduction d'une entree dans le journal des logs
  static function update($event,$object=0,$comment='',$dateeve=NULL,$force=false,$xset=NULL,$unique=false) {
    if($xset==NULL && !empty($object) && \Seolan\Core\Kernel::isAKoid($object)) $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$object);
    if($xset && !$force && !$xset->toLog()) return NULL;
    if(empty(self::$_xset))
      self::$_xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('LOGS');

    if(is_array($comment)) {
      // ne pas logguer les update sans modif, $comment a toujours la clef 'LANG'
      if ($event == 'update' && (empty($comment) || count($comment) == 1)) {
        return null;
      }
      \Seolan\Core\System::array2xml($comment,$details);
      $comment='';
    }else{
      $details='';
    }
    if(empty($dateeve)) $dateeve=date('Y-m-d H:i:s');
    $uoid=(!empty($GLOBALS['XUSER'])?$GLOBALS['XUSER']->_curoid:'');
    $ar1=array('_options'=>array('local'=>1),'etype'=>$event,'user'=>$uoid,'object'=>$object,'comment'=>$comment,
	       'usernam'=>($uoid?@$GLOBALS['XUSER']->_cur['fullnam']:'Anonymous'),
	       'dateupd'=>$dateeve,'tplentry'=>TZR_RETURN_DATA,'details'=>$details,'ip'=>@$_SERVER['REMOTE_ADDR'],
	       'options' => array('details' => array('raw' => 1), 'comment' => array('raw' => 1)));
    $f='procInput';
    if($unique){
      $ors=getDB()->fetchRow('SELECT KOID FROM LOGS WHERE object=? and etype=? and user=? LIMIT 0,1',
			     array($object,$event,$uoid));
      if(!empty($ors)){
	$ar1['oid']=$ors['KOID'];
	$f='procEdit';
      }
    } else {
      // en création ajout du setuid
      if (issetSessionVar('SUID')){
	$ar1['setuid'] = getSessionVar('SUID');
      }
    }
    self::$_xset->$f($ar1);
    return $dateeve;
  }
  static function uniqueUpdate($event,$object=0,$comment='',$dateeve=NULL,$force=false,$xset=NULL) {
    return self::update($event,$object,$comment,$dateeve,$force,$xset,true);
  }

  /// Rend la derniere date d'execution d'un type/objet donné pour l'utilisateur en cours
  static function last($event,$oid=NULL) {
    $uid=$GLOBALS['XUSER']->_curoid;
    if($oid) $ors=getDB()->fetchRow('SELECT * FROM LOGS WHERE user=? AND object=? AND etype=? ORDER BY dateupd DESC limit 1', array($uid, $oid, $event));
    else $ors=getDB()->fetchRow('SELECT * FROM LOGS WHERE user=? AND etype=? ORDER BY dateupd DESC limit 1', array($uid,$event));
    return @$ors['dateupd'];
  }

  /// Nettoyage des logs. On raccourcit le delai de garde jusqu'a avoir la taille souhaitee.
  /// Les logs sont envoyés dans un fichier d'archive, et ne sont pas effaces
  static function clean(&$report, &$error) {
    $archive = TZR_LOG_DAYS;
    $tablesLogStatus = \Seolan\Core\DataSource\DataSource::tablesLogStatus();

    \Seolan\Library\Database::deactivateCache();
    $select = 'SELECT * FROM LOGS WHERE dateupd<DATE_SUB(curDate(),INTERVAL '.$archive.' DAY)';
    $step = 1000;
    do {
      $start = 0;
      do {
        unset($logs);
        $logs = getDB()->fetchAll("$select limit $start,$step");
        $start += $step;
        foreach ($logs as $log) {
          $table = empty($log['object']) ? '' : \Seolan\Core\Kernel::getTable($log['object']);
          if (empty($table) || !@$tablesLogStatus[$table] || $log['etype']=="security") {
            \Seolan\Core\Archive::appendData('LOGS', $log);
            getDB()->execute('DELETE FROM LOGS WHERE KOID=? AND LANG=?', array($log['KOID'], $log['LANG']));
          }
        }
      } while (count($logs));
      $select = 'SELECT * FROM LOGS WHERE substr(dateupd,1,10)=DATE_SUB(curDate(),INTERVAL '.$archive.' DAY)';
      $archive--;
    } while (($archive>=7) && (($nb=getDB()->count('SELECT COUNT(*) FROM LOGS'))>TZR_LOG_MAXSIZE));

    if ($nb > TZR_LOG_MAXSIZE) {
      $report .= "LOGS overflow: $nb lines, clean LOGS or increase TZR_LOG_MAXSIZE (".TZR_LOG_MAXSIZE.")\n";
      $error = true;
    }
  }
  /// AJoute un log dans le fichier des log d'upgrade
  static public function upgradeLog($message,$echo=true){
    self::_initUpgradeLogFile();
    try {
      $elapsed=(int)((System::getmicrotime()-TZR_START_TIME)*1000);
      $memory=get_memory();
      $message = $elapsed.'ms '.$memory.'MB pid='.getmypid().' '.$message;
      self::$_upgrade_logger->log(\Zend\log\Logger::DEBUG, $message);
    } catch(\Exception $e) {
      self::$_upgrade_logger->log(\Zend\log\Logger::DEBUG, $message);
    }
    if($echo && !defined('UNITTEST_NOLOG_ECHO')){
      $message.="\n";
      if(!empty($_SERVER['REQUEST_URI'])) $message=str_replace("\n","<br/>\n",$message);
      echo $message;
    }
  }
  /// AJoute un log dans le fichier des log d'upgrade
  static public function upgradeLog__($message,$echo=true){
    static $init=false;
    if($echo && !$init){
      // Initilise PHP pour que les echo soient affichés en temps réel
      ini_set('output_buffering', 'off');
      ini_set('zlib.output_compression', false);
      ini_set('implicit_flush',true);
      header('Content-type: text/html; charset=utf-8');
      header("Content-Encoding: none"); // Bien que ce ne soit pas valide, force la desactiovation de gzip
      while(@ob_end_flush());
      $init=true;
    }
    self::_initUpgradeLogFile();
    self::$_upgrade_logger->log(\Zend\log\Logger::DEBUG, $message);
    if($echo){
      $message.="\n";
      if(!empty($_SERVER['REQUEST_URI'])) $message=str_replace("\n","<br/>\n",$message);
      echo $message;
    }
  }

}
