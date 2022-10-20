<?php
namespace Seolan\Core\Module;

// TYPES DE MODULES
//
define('XMODMAILINGLIST_TOID',	   1);
define('XMODCART_TOID',    	   3);
define('XMODINFOTREE_TOID',    	   4);
define('XMODSCHEDULER_TOID',	   5);
define('XMODREPLICATION_TOID',	   6);
define('XMODEXTERN_TOID',	   8);
define('XMODTABLE_TOID',          25);
define('XMODSHORTCUT_TOID',       26);
define('XMODCRM_TOID',       	  27);
define('XMODTAG_TOID',          30);
define('XMODTAGUSER_TOID',      31);
define('XMODBACKOFFICEINFOTREE_TOID', 50);
// from 60 to 79 : dedicated to renaud.zigmann@xsalto.com
define('XMODMANUAL_TOID', 55);
// from 200 to 300 : dedicated to richard.reynaud@xsalto.com
define('XMODSEARCH_TOID',        200);
define('XMODMINISITES_TOID',     993);
define('XMODBLOG_TOID',          994);
define('XMODREF_TOID',  	 995);
define('XMODGROUP_TOID',	 996);
define('XMODUSER2_TOID',	 997);
define('XMODADMIN_TOID',	 998);
define('XMODSUB_TOID',		 999);
define('XMODRECORD_TOID',	1001);
define('XMODCALENDAR_TOID',     6102);
define('XMODDOCMGT_TOID',  	6103);
define('XMODTASKS_TOID',        6104);
define('XMODLOCK_TOID',         6105);
define('XMODSTATS_TOID',        6106);
define('XMODCALENDARADM_TOID',  6107);
define('XMODMAP_TOID',          6108);
define('XMODTIF_TOID',          6109);
define('XMODSECURITY_TOID',     6110); /* module qui n'existe plus mais identifiant à garder pour la procédure d'upgrade */
define('XMODDATASOURCE_TOID',   8000);
define('XMODMEDIA_TOID',        8001);
define('XMODDLSTATS_TOID',      8002);
define('XMODSOCIAL_TOID',       8003);
define('XMODFORM_TOID',         8004);
define('XMODRULE_TOID',         8005);
define('XMODPROJECT_TOID',      8006);
define('XMODWORKFLOW_TOID',     8007);
define('XMODCOMMENT_TOID',      8008);
define('XMODMEDIACOLLECTION_TOID',        8010);
define('XMODWEBRESA_TOID',	8011);
define('XMODMONETIQUE_TOID',	8012);
define('XMODMOODLE_TOID',       8013);
define('XMODMAILLOGS_TOID',  	8016);
define('XMODAPP_TOID',       	8017);
define('XMODCACHE_TOID',     	8018);
define('XMODSITRA_TOID',        8019);
define('XMODMULTITABLE_TOID',   8020);
define('XMODSKIPLAN_TOID',      8021);
define('XMODWALL_TOID',         8022);
define('XMODREDIRECT_TOID',     8023);
define('XMODMONETICO_TOID',	8025);
define('XMODCHAT_TOID',         8026);
define('XMODRGPD_TOID',         8027);
define('XMODTOURINSOFT_TOID',   8028);
define('XMODCART2_TOID',    	8040);
define('XMODFRONTUSERS_TOID',	8041);
//define('XMODCARTOFFERS_TOID',   8042); à voir
define('XMODMKTREDOUTE_TOID', 8043);
define('XMODWAITINGROOM_TOID',  8044);
define('XMODTARTEAUCITRON_TOID', 8045);
define('XMODCRM2_TOID',          8046);
define('XMODDOCSET_TOID',          8047);
define('XMODPUSHNOTIFICATION_TOID', 8048);
define('XMODPUSHNOTIFICATIONDEVICE_TOID', 8049);
define('XMODCONFIGMOBAPP_TOID', 8050);

/****
 * NAME
 *   \Seolan\Core\Module\Module -- interface de base des méthodes
 * DESCRIPTION
 *   Cette classe est la base de tous les modules.
 * SYNOPSIS
 ****/
class Module implements \Seolan\Core\ISec {

  /**
   * Evenement déclenché lors de la validation des propriétés du module.
   */
  const EVENT_PRE_PROC_EDIT_PROPERTIES = 'pre_proc_edit_properties';

  /**
   * Evenement déclenché lors de l'affichage du formulaire des propriétés du module.
   */
  const EVENT_PRE_EDIT_PROPERTIES = 'pre_edit_properties';

  static public $upgrades = ['20190411'=>''
			     ,'20190507'=>''
			     ,'20190528'=>''
			     ,'20190701'=>''
			     ,'20200403'=>''
			     ,'20200715'=>''
  ];
  static protected $browsetrashtemplate='Core/Module.browseTrash.html';
  static protected $trashmenuorder=1;
  static protected $trashmenugroup='display';
  static protected $mailerClass='\Seolan\Library\Mail';
  static protected $iconcssclass='csico-module-list';
  public $_moid;
  public $interactive=false;
  public $toid = NULL;
  static $_mcache = [];
  static $_modlist = NULL;
  static $_objs = [];
  static $_modules;
  static $_default=array();
  public $trackchanges=false;
  public $usetrash=false;
  public $defaultispublished=false;
  public $archive=false;
  public $has_subobjects=false;
  public $object_sec=false;
  public $reportto=TZR_DEBUG_ADDRESS;
  public $dependant_module=false;
  public $home=true;
  public $inxlink=true;
  public $insearchengine=false;
  public $saveUserPref=false;
  public $sendacopyto=true;
  public $sendacopytosimple=false;
  public $directorymodule=NULL;
  public $iptask=NULL;
  public $comment=NULL;
  public $submodmax=TZR_MODTABLE_SUBMOD_MAX;
  public $cache=NULL;
  public $available_display_modules=true;
  public $testmode=false;
  public $_testmode=false;
  public $mailLayoutAlias='csx_mail_layout'; // alias réservé dans un InfoTree pour fond de page mail
  public $mailLayoutTemplate='Module/InfoTree.misc/mail-layout.html';
  /* RGPD */
  public $RGPD_personalData=false;
  public $RGPD_identity=true;
  public $RGPD_deleteDataMethod='anonymize';
  public $RGPD_typeOfData='legal';
  public $RGPD_retention=3*365;

  /** @var \Seolan\Core\Options */
  public $_options;

  // options de configurations
  protected $_configuration_options=null;

  /**
   * Liste de callbacks ajoutés au module.
   * @var array de fonctions de rappels
   */
  private $callbacks=array();
  protected $_app_daemon = null;
  // si vrai, la classe est un singleton
  static $singleton=false;
  // Vaut vrai lorsque un modlist est en cours
  static $modlist_loading=false;

  /// recupération des définitions de tous les modules depuis la base de données
  static private function _preloadModules($refresh=false) {
    if(empty(\Seolan\Core\Module\Module::$_mcache) || $refresh) {
      $rs=getDB()->fetchAll('select * from MODULES');
      foreach($rs as $ors) {
	$toid=$ors['TOID'];
	$moid=$ors['MOID'];
	$ors['MPARAM_encoded']=$ors['MPARAM'];
	unset($ors['MPARAM']);
	\Seolan\Core\Module\Module::$_modules[$toid]['_modules'][$moid]=true;
	$ors['CLASSNAME'] = @\Seolan\Core\Module\Module::$_modules[$toid]['CLASSNAME'];
	// la recherche des surcharges depuis MPARAM est décallée au dernier moment
	\Seolan\Core\Module\Module::$_mcache[$ors['MOID']]=$ors;
      }
      unset($rs);
    }
  }
  static private function _loadModules() {
    $rs=getDB()->fetchAll('select * from MODULES');
    $moduleList=[];
    foreach($rs as $ors) {
      $toid=$ors['TOID'];
      $moid=$ors['MOID'];
      $ors['MPARAM']=\Seolan\Core\Options::decode($ors['MPARAM']);
      \Seolan\Core\Module\Module::$_modules[$toid]['_modules'][$moid]=true;
      $c=@\Seolan\Core\Module\Module::$_modules[$toid]['CLASSNAME'];
      if(!empty($ors['MPARAM']['theclass']) && class_exists($ors['MPARAM']['theclass']))
	$c=$ors['MPARAM']['theclass'];
      $ors['CLASSNAME']=$c;
      $moduleList[$ors['MOID']]=$ors;
    }
    unset($rs);
    return $moduleList;
  }

  static public function getMoid($toid) {
    if(!isInteger($toid)){
      \Seolan\Library\Security::alert(__METHOD__.': trying to use wrong TOID='.var_export($toid, true));
    }
    \Seolan\Core\Module\Module::_preloadModules();
    foreach(\Seolan\Core\Module\Module::$_mcache as $moid=>$ors) {
      if($ors['TOID']==$toid) return $moid;
    }
    return NULL;
  }

  static public function getMoidFromClassname($classname) {
    if(!is_string($classname)){
      \Seolan\Library\Security::alert(__METHOD__.': trying to use wrong classname='.var_export($classname, true));
    }
    \Seolan\Core\Module\Module::_preloadModules();
    foreach(\Seolan\Core\Module\Module::$_mcache as $moid=>&$ors) {
      if($ors['CLASSNAME']==$classname) return $moid;
    }
    return NULL;
  }

  /// Retourne le TOID à partir du nom (class+namepsace) de la classe
  static public function getToidFromClassname($classname) {
    if (substr($classname,0,1) != '\\'){
      $classname ='\\'.$classname;
    }
    foreach(\Seolan\Core\Module\Module::$_modules as $toid=>$moddesc){
      if ($moddesc['CLASSNAME'] == $classname){
	return $toid;
      }
    }
    return null;
  }
  /// Retourne la classe du module associé au wizard
  static public function getModuleClassnameFromWizard($wizardClassname){
    $w=explode('\\',$wizardClassname);
    array_pop($w);
    $w[]=$w[count($w)-1];
    return implode('\\',$w);
  }
  /// Retourne le chemin d'accès à la classe de wizard
  static private function getModuleWizardPath($module){
    $w=explode('\\',$module);
    array_pop($w);
    $w[]='Wizard';
    return implode('\\',$w);
  }
  static public function getModuleWizardPathFromParents($module, &$parents=null){
    $wizard = static::getModuleWizardPath($module);
    if (!class_exists($wizard)){
      $parents[] = $module;
      $parentclass = get_parent_class($module);
      if ($parentclass)
	return static::getModuleWizardPathFromParents($parentclass, $parents);
      else
	throw new \Exception("unable to load wizard for $module (".implode(',',$parents).")");
    }
    return $wizard;
  }

  /// rend le bloc de paramètres de modules
  static function &findParam($moid) {
    if(!isset(\Seolan\Core\Module\Module::$_mcache[$moid]))
      \Seolan\Core\Module\Module::_preloadModules();

    if(isset(\Seolan\Core\Module\Module::$_mcache[$moid])) {
      $cacheBloc=&\Seolan\Core\Module\Module::$_mcache[$moid];
      if(!isset($cacheBloc['MPARAM'])) {
	\Seolan\Core\Logs::debug(__METHOD__.' decoding '.$moid);
	$cacheBloc['MPARAM']=\Seolan\Core\Options::decode($cacheBloc['MPARAM_encoded']);
	$theclass=$cacheBloc['MPARAM']['theclass']??'';
	// recherche de la bonne classe si elle a été surchargée
	if(!empty($theclass)){
	  if (class_exists($theclass))
	    $cacheBloc['CLASSNAME']=$theclass;
	  else {
	    \Seolan\Core\Logs::critical(__METHOD__,"$moid '$theclass' does not exists");
	  }
	}
      }
      return \Seolan\Core\Module\Module::$_mcache[$moid];
    }
    $r=array();
    return $r;
  }
  /// rend vrai si le module est dans la version demandée
  protected static function  hasUpgrade(\Seolan\Core\Module\Module $instance, string $no):bool{
    return \Seolan\Library\Upgrades::hasUpgrade($instance, $no);
  }
  /// rend vrai si le module existe, faux sinon
  static function moduleExists($moid, $toid=NULL) {
    \Seolan\Core\Module\Module::_preloadModules();
    if(empty($moid)) {
      foreach(\Seolan\Core\Module\Module::$_mcache as $moid=>&$ors) {
	if($ors['TOID']==$toid) return true;
      }
      return false;
    } else {
      if(empty($toid)){
	return isset(\Seolan\Core\Module\Module::$_mcache[$moid]);
      }else{
	if(\Seolan\Core\Module\Module::$_mcache[$moid]['TOID']==$toid) return true;
	else return false;
      }
    }
  }

  /// creation d'un module a partir de son type
  /* @return \Seolan\Core\Module\Module */
  static function singletonFactory($toid) {
    $moid=\Seolan\Core\Module\Module::getMoid($toid);
    if(!empty($moid)) {
      return \Seolan\Core\Module\Module::objectFactory($moid);
    }
    return NULL;
  }

  /// rend vrai si le module est un singleton et s'il est déjà instancié
  static function singletonModuleExists($toid) {
    $moid=\Seolan\Core\Module\Module::getMoid($toid);
    if(empty($moid)) return false;

    $param = &\Seolan\Core\Module\Module::findParam($moid);
    if (!$param) return false;

    $classname = $param['CLASSNAME'];
    return class_exists($classname) && $classname::$singleton && self::moduleExists($moid);
  }

  /// creation d'un module a partir de son identifiant (moid).
  /* @return \Seolan\Core\Module\Module */
  static function objectFactory($ar=NULL) {
    if (!is_array($ar)) {
      $moid = $ar;
      $ar = array('moid' => $moid, 'tplentry' => TZR_RETURN_DATA, '_local'=>1);
    } else {
      $p = new \Seolan\Core\Param($ar, array());
      $moid = $p->get('moid');
      if (empty($moid)) $ar['moid'] = $moid = \Seolan\Core\Module\Module::getMoid( $p->get('toid') );
    }
    if (!isset(\Seolan\Core\Module\Module::$_objs[$moid])) {
      $param = &\Seolan\Core\Module\Module::findParam($moid);
      if (!$param)
        $mod = NULL;
      $classname = $param['CLASSNAME']??'';
      if (class_exists($classname)) {
	// creation de l'objet, on garde l'objet en cache
	$mod = new $classname($ar);
	if (TZR_USE_APP) {
	  \Seolan\Module\Application\Application::newModuleHook($mod);
	}
	\Seolan\Core\Module\Module::$_objs[$moid] = $mod;
      }
    }
    if (!empty(\Seolan\Core\Module\Module::$_objs[$moid]) && !empty($ar['interactive'])) {
      \Seolan\Core\Module\Module::$_objs[$moid]->setInteractive($ar);
    }
    return \Seolan\Core\Module\Module::$_objs[$moid]??NULL;
  }

  /// Duplication d'un module
  function duplicateModule($ar=NULL) {
    $p=new \Seolan\Core\Param($ar);
    $prefix=$p->get('prefix');
    $ar['_options']=array('local'=>1);
    $default= \Seolan\Core\Module\Module::_getProperties($this->_moid);
    $p=new \Seolan\Core\Param($ar, $default);
    $params=$p->getArray();
    $newmoid=\Seolan\Core\Module\Wizard::newMoid();
    $params['modulename'] = trim($params['modulename']);
    if (empty($params['modulename']))
      $params['modulename'] = $this->getLabel();
    if (empty($prefix)){
      $params['modulename'] = static::getNewModuleName($params['modulename'], $params['group']);
    }
    $params['modulename']=$this->getDuplicateModuleGenerateName($prefix,$params['modulename']);
    $ret=$this->_duplicateModule($newmoid,$params,$prefix);
    $json=$this->_options->rawToJSON($params);
    getDB()->execute('INSERT INTO MODULES(MOID,TOID,MODULE,MPARAM) '.
                     'SELECT ?,TOID,?,? from MODULES WHERE MOID=?',array($newmoid,$params['modulename'],$json,$this->_moid));
    // créer le nom du nouveau module (AMSG) traduisible
    foreach($GLOBALS['TZR_LANGUAGES'] as $lang=>$foo){
      getDB()->execute('INSERT INTO AMSG(MOID,MLANG,MTXT) values(?,?,?)',["module:{$newmoid}:modulename", $lang, $params['modulename']]);
    }
    // dupliquer les commentaires (AMSG)
    $oldamoid = "module:".$this->_moid.":comment";
    $newamoid = "module:".$newmoid.":comment";
    $rs=getDB()->fetchAll('select MLANG,MTXT from AMSG where MOID=?',array($oldamoid));
    foreach($rs as $ors) {
      getDB()->execute('INSERT INTO AMSG(MOID,MLANG,MTXT) values(?,?,?)',array($newamoid,$ors['MLANG'],$ors['MTXT']));
    }
    \Seolan\Core\Module\Module::clearCache();
    \Seolan\Core\Module\Module::_preloadModules(true);
    $mod=\Seolan\Core\Module\Module::objectFactory($newmoid);
    $mod->chk();
    $ret['moid']=$newmoid;
    if(!is_array($ret['duplicatetables'])) $ret['duplicatetables']=array();
    if(!is_array($ret['duplicatemods'])) $ret['duplicatemods']=array();
    $ret['duplicatemods'][$this->_moid]=$newmoid;
    setSessionVar('_reloadmods',1);
    setSessionVar('_reloadmenu',1);

    return $ret;
  }
  protected function getDuplicateModuleGenerateName($prefix,$name){
    if($prefix){
      if(($pos=strpos($name,':'))!==false) return $prefix.' : '.substr($name,$pos+2);
      else return $prefix.' : '.$name;
    }else{
      return $name;
    }
  }
  /**
   * recherche des modules de nom équivalent dans le mmême groupe
   * et retourne le nom avac l'incrément
   */
  protected static function getNewModuleName($name, $group=''){
    $name = preg_replace('/\(\d\)$/', '', $name);
    $nb = getDb()->fetchOne("select count(*) from MODULES where (MODULE=? or MODULE rlike ?) and json_value(mparam, '$.group.value')=?", [$name,"$name\\([0-9]+\\)\$", $group]);
    if ($nb==0)
      return $name;

    $name=$name."($nb)";
    while(getDB()->fetchOne('select 1 from MODULES where module=?', [$name])){
      $nb++;
      $name = preg_replace('/\(\d\)$/', '', $name);
      $name=$name."($nb)";
    }
    return $name;
  }
  /// Duplication d'un module, méthode interne
  /// Retour : duplicatetables => liste des tables dupliquées par le module (cle : ancienne table, valeur : nouvelle table))
  /// Retour : duplicatemods => liste des modules dupliqués par le module (cle : ancien moid, valeur : nouveau moid))
  function _duplicateModule($newmoid,&$params,$prefix) {
    return array('duplicatetables'=>array(),'duplicatemods'=>array());
  }

  /// diverses corrections dans les paramètres des modules une fois que tous les modules sont dupliqués
  function postDuplicateModule($modules) {
  }

  static function clearCache() {
    \Seolan\Core\Module\Module::$_mcache = [];
    \Seolan\Core\Module\Module::$_objs = [];
    \Seolan\Core\Module\Module::$_modlist = NULL;
    clearSessionVar(TZR_SESSION_PREFIX.'modules');
    clearSessionVar(TZR_SESSION_PREFIX.'modmenu');
  }

  static function authorizedModules() {
    if(issetSessionVar(TZR_SESSION_PREFIX.'modules')) $r=getSessionVar(TZR_SESSION_PREFIX.'modules');
    else $r=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA,'basic'=>true));
    return $r['lines_oid'];
  }

  static function authorizedModule($moid) {
    if(issetSessionVar(TZR_SESSION_PREFIX.'modules')) $r=getSessionVar(TZR_SESSION_PREFIX.'modules');
    else $r=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA,'basic'=>true));
    return in_array($moid,$r['lines_oid']);
  }

  /// Appel une fonction de nettoyage sur tous les modules suite à une suppression
  static function removeRegisteredOid($oid) {
    $rs=getDB()->fetchCol('select MOID from MODULES');
    foreach($rs as $moid) {
      $m=\Seolan\Core\Module\Module::objectFactory($moid);
      if(is_object($m)) $m->_removeRegisteredOid($oid);
    }
    unset($rs);
  }
  /// Fonction de nettoyage appelée suite à une suppression
  function _removeRegisteredOid($oid) {
    return false;
  }


  // rend la liste des modules utilisant cette table. Filtré par les droits de l'utilisateur connecté
  // $table: table vers laquelle on pose la question
  // $refresh: est ce qu'on utilise l'information qui se trouve en cache ou pas.
  // L'info en cas n'est pas censee etre filtree en fonction des droits users
  // $astarget : si vrai, on essaie de ne chercher que les modules
  // visibles en page d'accueil, sinon on se rabat sur tous les
  // modules. si 'only', on se rabat pas automatiquement sur tous les modules
  // $auth : est ce qu'on filtre les modules en fonction des droits du
  // user connecte
  // $main : recherche seulement les modules permettant l'affichage d'un objet de la table
  static function modulesUsingTable($table,$refresh=false,$astarget=false,$auth=true,$main=false) {
    $varname=TZR_SESSION_PREFIX.'modules'.$table.':'.$astarget.'_'.$main;
    if($refresh) {
      // suppression du cache de correspondance entre tables et modules
      \Seolan\Core\DbIni::clear($varname);
    }
    $vcache=\Seolan\Core\DbIni::get($varname,'val');

    if($vcache!==NULL) {
      $r=$vcache;
    } else {
      $rs=getDB()->fetchAll('select * from MODULES');
      $r=array();			// tableau des modules utilsant cette table
      if(empty($rs)) return array();
      foreach($rs as $ors) {
	$m = \Seolan\Core\Module\Module::objectFactory($ors['MOID']); // création du module
	if(is_object($m) && (!$main && $m->usesTable($table) || $main && $m->usesMainTable($table))) {
	  if(!$astarget || ($m->home && $m->inxlink)) $r[$ors['MOID']]=$m->getLabel();
	}
      }
      unset($rs);
      if(empty($r) && $astarget && $astarget!=='only') $r=self::modulesUsingTable($table,$refresh,false,false,$main);
      \Seolan\Core\DbIni::set($varname,$r);
    }
    if($auth) {
      $r1=\Seolan\Core\Module\Module::authorizedModules();
      foreach($r as $moid=>$title) {
	if(!in_array($moid, $r1)) unset($r[$moid]);
      }
    }
    return $r;
  }

  function __construct($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $moid=$p->get('moid');
    \Seolan\Core\Logs::debug(__METHOD__.': trying to load module '.$moid);
    $this->_options = new \Seolan\Core\Options();
    \Seolan\Core\Labels::loadLabels('Seolan_Core_Module_Module');
    if(!empty($moid)) {
      $this->_moid=$moid;
      $this->dependant_module = $this->isDependant();
      $this->load($moid);
      $this->testmode = $this->testMode();
      \Seolan\Core\Logs::debug(__METHOD__.'('.$moid.'): '.get_class($this).' '.$this->getLabel().' loaded');
      $this->RGPD_retention=\Seolan\Module\RGPD\RGPD::getRetentionFromDataType($this->RGPD_typeOfData) ?? $this->RGPD_retention;
      $this->batchesFile = TZR_TMP_DIR."batchesfile_{$this->_moid}.json";
    }
  }

  function setInteractive($ar) {
    $p = new \Seolan\Core\Param($ar);
    $tplentry = $p->get('tplentry');
    $this->interactive = true;
    self::clearNav();
    $o = get_object_vars($this);
    $o['classname'] = get_class($this);
    $o['modulename'] = $this->getLabel();
    \Seolan\Core\Shell::toScreen2($tplentry, 'mod', $o);
    \Seolan\Core\Shell::toScreen2('imod', 'mod', $this);
    \Seolan\Core\Shell::toScreen2('imod', 'props', $o);
    \Seolan\Core\Shell::toScreen2($tplentry, 'moid', $ar['moid']);
    if ($tplentry != '') {
      $r['modulename'] = $this->getLabel();
      $r['classname'] = get_class($this);
      $r['moid'] = $this->_moid;
      \Seolan\Core\Shell::toScreen1('', $r);
    }
    \Seolan\Core\Logs::debug('setInteractive module ' . $ar['moid'] . ' ' . get_class($this) . ' ' . $this->getLabel());
  }

  // rend vrai si il faut garder la trace
  function tablesToTrack() {
    return NULL;
  }

  // rend vrai si un module est strictement dependant dun autre module
  function isDependant() {
    return false;
  }

  function hook1($ar=NULL) {
  }

  function hookBatch1(\Seolan\Module\Scheduler\Scheduler &$sc, &$o, &$more) {
  }

  function getLabel() {
    return $this->modulename;
  }

  /// creation de cas de workflow
  public function newWFCase($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    if(\Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID)) {
      $wfid=$p->get('wfid');
      $oid=$p->get('oid');

      $user=\Seolan\Core\User::get_user();
      $umod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
      if($umod->isPendingCase($oid)) {
	\Seolan\Core\Shell::toScreen2($tplentry, 'message', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Workflow_Workflow', 'onecaseonly'));
        return;
      }

      $workflow = $umod->xset->rDisplay($wfid);

      /// preparation du cas
      $field = $umod->xset->getField('parameters');
      $fields= $field->createFieldsFromText($workflow['oparameters']->raw);
      foreach($fields as $f) {
	$options=array();
	$br[]=$f->edit($f->getDefaultValue(),$options);
      }
      \Seolan\Core\Shell::toScreen2('br','display',$workflow);
      return \Seolan\Core\Shell::toScreen2('br','edit',$br);
    }
  }

  /// creation d'un nouveau processus actif
  public function procNewWFCase($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    if(\Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID)) {
      $wfid=$p->get('wfid');
      $oid=$p->get('oid');

      $user=\Seolan\Core\User::get_user();
      $umod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
      // on ne crée par deux processus simultanement sur un objet
      if($umod->isPendingCase($oid)) {
        \Seolan\Core\Shell::toScreen2($tplentry, 'message', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Workflow_Workflow', 'onecaseonly'));
        return;
      }
      $workflow = $umod->xset->rDisplay($wfid);

      /// preparation du cas
      $field = $umod->xset->getField('parameters');
      $fields= $field->createFieldsFromText($workflow['oparameters']->raw);
      $context=array();
      foreach($fields as $f) {
        $value=$p->get($f->field);
        $value_hid=$p->get($f->field.'_HID');
        $options=array();
        $inputs=array();
        $r1=$f->post_edit($value,$options,$inputs);
        $context[$r1->field]=$r1->raw;
      }
      $umod->addCase($wfid, $oid, $user, $context, $this->_moid);
      \Seolan\Core\Shell::toScreen2($tplentry, 'message', \Seolan\Core\Labels::getSysLabel('Seolan_Module_Workflow_Workflow', 'casecreated', 'text'));
    }
  }

  public function index() {
  }

  /// Verification qu'un module est bien installé. Si le parametre repair est a oui, on fait les reparations si possible.
  public function chk(&$message=NULL) {
    return true;
  }

  /// Gestion initialisation des options du module.
  public function initOptions() {
    $this->_options->setId('module:'.$this->_moid);
    $genlabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','general');
    $devlabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','developer');
    $slabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','security');
    $tlabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','tracking');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','modulename'),'modulename','ttext',array('compulsory'=>true),NULL,$genlabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','group'),'group','label',array('compulsory'=>true),'Attente',$genlabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','comment','text'),'comment','ttext',array('compulsory'=>true,
												     'rows'=>2,'cols'=>'40'),NULL, $genlabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','object_sec'),'object_sec','boolean',NULL,NULL,$slabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','theclass'),'theclass', 'text',NULL,'',$devlabel);

    $sendacopylabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','sendacopyto');
    $this->_options->setOpt($sendacopylabel,'sendacopyto','boolean',NULL,true,$sendacopylabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','sendacopytofiles'),'sendacopytofiles','boolean',NULL,false,$sendacopylabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','sendacopytodirectory'),'directorymodule','module',NULL,NULL,$sendacopylabel);

    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','defaultispublished'),'defaultispublished','boolean',NULL,false,$genlabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','reportto'),'reportto','text',array(),TZR_DEBUG_ADDRESS,$genlabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','homepageable'),'home','boolean',NULL,true,$genlabel);
    $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Messages','home_help'),
				'home');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','inxlink'),'inxlink','boolean',NULL,true,$genlabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','insearchengine'),'insearchengine','boolean',NULL,false,$genlabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','available_in_display_modules'),'available_in_display_modules','boolean',NULL,true,$genlabel);

    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','saveuserpref'),'saveUserPref','boolean',NULL,NULL,$genlabel);
    $this->_options->setOpt('Réplication','replicate','boolean',NULL,true,$genlabel);
    $this->_options->setOpt('Test mode','_testmode','boolean',NULL,false,$slabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','trash'),'usetrash','boolean',NULL,NULL,$tlabel);

    $rgpdgroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','RGPD');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','personaldata'), 'RGPD_personalData', 'boolean', null, false, $rgpdgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','identity'), 'RGPD_identity', 'boolean', null, false, $rgpdgroup);

    $methods=['values'=>['anonimize','delete'], 'labels'=>[\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','anonimize'),\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete')]];
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','deletedatamethod'), 'RGPD_deleteDataMethod', 'list', $methods, 'anonimize', $rgpdgroup);

    $typepersonaldata=['values'=>['other','commercial', 'legal'], 'labels'=>[\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','other'),\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','commercial'), \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','legal')]];
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','typeofdata'), 'RGPD_typeOfData', 'list', $typepersonaldata, NULL, $rgpdgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','retention'), 'RGPD_retention', 'text', [], 3*365, $rgpdgroup);
  }

  /**
   * Fonction appelee a chaque appel du scheduler, pour effectuer des taches diverses
   */
static function daemon($period){
    $list=self::modlist(array('tplentry'=>TZR_RETURN_DATA, 'noauth'=>true, 'withmodules' => true, 'basic' => true));
    foreach($list['lines_mod'] as $i=>$o) {
      if(is_object($o)) $o->_daemon($period);
    }
    return true;
  }

  // Fonction appelee a chaque appel de fastdaemon, pour les taches à effectuer souvent
  static function fastDaemon() {
    global $FAST_DAEMONS;
    foreach ($FAST_DAEMONS as $_mod) {
      if ($_mod['toid'] && is_string($_mod['toid'])) {
        $_mod['toid'] = constant($_mod['toid']);
      }
      $mod = self::objectFactory($_mod);
      if ($mod) {
        $mod->_fastDaemon();
      }
    }
  }
  /**
   * retourne les options de configuration du module
   * si la clé toid existe, elle prime (à utiliser pour les singletons)
   */
  protected function loadConfig(){
    if ($this->_configuration_options === null){
      if (file_exists("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php")){
	try{
	  // pas de include once / tu (à voir ?)
	  $modules_configuration_options = include("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php");
	  if (isset($modules_configuration_options[$this->_moid]['options'])){
	    \Seolan\Core\Logs::debug(__METHOD__." loading conf from moid {$this->_moid}");
	    $this->_configuration_options = $modules_configuration_options[$this->_moid]['options'];
	  } elseif (isset($modules_configuration_options['toid:'.$this->toid]['options'])){
	    \Seolan\Core\Logs::debug(__METHOD__." loading conf from toid");	    
	    $this->_configuration_options = $modules_configuration_options['toid:'.$this->toid]['options'];
	  } else {
	    \Seolan\Core\Logs::debug(__METHOD__." no conf found");
	    $this->_configuration_options = [];
	  }
	} catch(\Throwable $t){
	  $this->_configuration_options = [];
	  \Seolan\Core\Logs::critical(__METHOD__,"error loading file '{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php'");
	}
      } else {
	\Seolan\Core\Logs::debug(__METHOD__." file {$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php not found");
	$this->_configuration_options = [];
      }
    }
    return $this->_configuration_options;
  }
  /// acces à une option de configuration
  public function getConfigurationOption(string $name, $default=null){
    $options = $this->loadConfig();
    if (!empty($options) && isset($options[$name]))
      return $options[$name];
    return $default;
  }
  /// rend vrai si on est en mode test, faux sinon
  public function testMode($configonly=false) {
    if($configonly) return $this->_testmode;
    else return $this->_testmode && in_array(@$_SERVER['REMOTE_ADDR'], explode(',',\Seolan\Core\Ini::get('testmode_ips')));
  }

  /// Indexation du module dans le moteur de recherche
  public function buildSearchIndex($searchEngine,$checkbefore=true,$limit=NULL,$cond=NULL,$optimize=true){
    if(!$this->insearchengine){
      $searchEngine->deleteQuery('moid:'.$this->_moid);
      \Seolan\Core\DbIni::clear('lastindexation_'.$this->_moid,false);
      if($optimize) $searchEngine->optimize();
      return false;
    }else{
      $ret=$this->_buildSearchIndex($searchEngine,$checkbefore,$limit,$cond);
      if($optimize) $searchEngine->optimize();
      return $ret;
    }
  }
  public function _buildSearchIndex($searchEngine,$checkbefore=true,$limit=NULL,$cond=NULL){
    return true;
  }
  /// Recupere les infos d'un objet par l'affichage du résultat d'une recherche
  public function getSearchResult($oid,$filter=NULL){
    return NULL;
  }

  /// presentation d'un resultat de recherche dans le module
  public function showSearchResult($oids) {
    return array();
  }

  /// preview des resultats de recherche dans la liste globale des resultats de la recherche plain texte
  public function previewSearchResult($oids) {
    return array();
  }

  //
  protected function getInSearchengineFields():?string{
    return '';
  }
  protected function _daemon($period='any'){
    return true;
  }
  /**
  * Exécution du daemon
  * dans le contexte d'une application utilisatrice du module
  */
  public function applicationDaemon($period){
    return $this->_daemon($period);
  }


  /// Gestion des variables de session pour le module en cours
  final function _setSession($p,$v, $sessionPrefix=NULL) {
    setSessionVar('mod'.$this->_moid.$p,$v, $sessionPrefix);
  }
  final function _issetSession($p, $sessionPrefix=NULL) {
    return issetSessionVar('mod'.$this->_moid.$p, $sessionPrefix);
  }
  final function _clearSession($p, $sessionPrefix=NULL) {
    clearSessionVar('mod'.$this->_moid.$p, $sessionPrefix);
  }
  final function _getSession($p, $sessionPrefix=NULL) {
    return getSessionVar('mod'.$this->_moid.$p, $sessionPrefix);
  }

  // gestion de la barre de navigation
  //
  protected function clearNav() {
    if($this->interactive && !empty($GLOBALS['XSHELL'])) {
      $GLOBALS['XSHELL']->clear_navbar();
    }
  }

  function pushNav($label, $url) {
    if($this->interactive && !empty($GLOBALS['XSHELL'])) {
      $GLOBALS['XSHELL']->push_navbar($label, $url);
    }
  }

  function setTitleNav($label, $url=NULL) {
    if($this->interactive && !empty($GLOBALS['XSHELL'])) {
      $GLOBALS['XSHELL']->set_navbar_pagetitle($label, $url);
    }
  }
  function menu($ar=NULL) {
  }

  function  _getFunctionLabel($func) {
    $c=strtolower(get_class($this));
    $labl=NULL;
    while(!($label=\Seolan\Core\Labels::getTextSysLabelFromClass($c, $func)) && ($c=strtolower(get_parent_class($c)))) {
    }
    if(empty($label)) $label = $func;
    return $label;
  }
  function nav($ar=NULL) {
    $p = new \Seolan\Core\Param($ar,array());
    $this->clearNav();
    $label=$this->_getFunctionLabel($ar['_function']);
    $this->setTitleNav($label);
    $LANG_USER = \Seolan\Core\Shell::getLangUser();
    $LANG_DATA = \Seolan\Core\Shell::getLangData();
    $ar['modulename']=$this->modulename;
    list($access,$l) = $GLOBALS['XUSER']->getUserAccess(get_class($this), $this->_moid, $LANG_DATA);
    $LANG_TRAD=$p->get('LANG_TRAD');
    if(isset($LANG_TRAD)) {
      list($access_trad,$l) = $GLOBALS['XUSER']->getUserAccess(get_class($this), $this->_moid, $p->get('LANG_TRAD'));
    }
    if($this->interactive) {
      $modsec=\Seolan\Core\Shell::from_screen('modsec');
      $modsec['mods'][$this->_moid]=array_flip($access);
      if(isset($LANG_TRAD)) {
	$modsec['mods_trad'][$this->_moid]=array_flip($access_trad);
      }
      \Seolan\Core\Shell::toScreen1('modsec',$modsec);
    }
    $this->status($ar);
  }

  // liste des catégories reconnues dans cette classe
  function secList() {
    return array_merge(array('none','list'),static::getRoList(),array('rw','rwv','admin'));
  }
  function secListList() {
    return array_merge(array('list'),static::getRoList(),array('rw','rwv','admin'));
  }
  function secRoList() {
    return array_merge(static::getRoList(),array('rw','rwv','admin'));
  }
  function secStrictRoList() {
    return array_merge(array('ro','rw','rwv','admin'));
  }
  static function getRoList(){
    return array('ro');
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function,$group=NULL) {
    $list=static::secListList();
    $ro=static::secRoList();
    $all=static::secList();
    $g=array();
    $g[':none']=$all;
    $g[':list']=$list;
    $g[':ro']=$ro;
    $g[':sro']=static::secStrictRoList();;
    $g[':rw']=array('rw','rwv','admin');
    $g[':rwv']=array('rwv','admin');
    $g[':admin']=array('admin');
    $g['_index']=$list;
    $g['SOAPRequest']=$all;
    $g['SOAPWSDL']=$all;
    $g['activity']=$ro;
    $g['addToUserSelection']=$list;
    $g['addBatchToSelection']=$list;
    $g['emptyUserSelection']=$ro;
    $g['ajaxGetContainableActionList']=$ro;
    $g['ajaxGetUIFunctionList']=$ro;
    $g['browseUserSelection']=$ro;
    $g['clearSecEdit']=array('admin');
    $g['newWFCase']=$g[':rw'];
    $g['procNewWFCase']=$g[':rw'];
    $g['delToUserSelection']=$ro;
    $g['delete']=array('admin');
    $g['developer']=array('admin');
    $g['duplicateModule']=array('admin');
    $g['editPrefs']=$all;
    $g['editProperties']=array('rwv','admin');
    $g['getInfos']=array('admin');
    $g['goto1']=$list;
    $g['gotoMainAction']=$all;
    $g['hook1']=array('rw','rwv','admin');
    $g['hookBatch1']=$all;
    $g['import']=array('rw','rwv','admin');
    $g['index']=$list;
    $g['lsSecurity']=array('admin');
    $g['manage']=array('rw','rwv','admin');
    $g['portlets']=$all;
    $g['procEditPrefs']=$all;
    $g['procErasePrefs']=$all;
    $g['procEditProperties']=array('rwv','admin');
    $g['procLsSecurity']=array('admin');
    $g['procSecEdit']=array('admin');
    $g['procSendACopyTo']=$list;
    $g['secEdit']=['admin'];
    $g['secEditSimple']=['admin'];
    $g['sendACopyTo']=$list;
    $g['setPref']=$all;
    $g['showUserManual']=$all;
    $g['sub']=$ro;
    $g['unsub']=$ro;
    $g['viewProperties']=array('admin');
    // ajout vers le menu gauche
    $g['adminProcNewSection']=['rw','rwv','admin'];
    $g['adminNewSection']=['rw','rwv','admin'];
    // trash
    $g['browseTrash']=['admin'];
    $g['moveFromTrash']=['admin'];
    $g['emptyTrash']=['admin'];
    $g['emptyTrashAll']=['admin'];
    $g['emptyTrashAllBatch']=['admin'];
    $g['checkEmail']=$list;
    if(function_exists('\Seolan\Core\Module\Module_modifySecGroups')) \Seolan\Core\Module\Module_modifySecGroups($this, $g);

    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      else return $g[$function];
    }
    return false;
  }
  /// Prepare l'ajout d'un module au menu de gauche
  function adminNewSection($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $newrub=$p->get('newrub');
    $fct=$p->get('fct');
    parse_str($fct,$infos);
    $secOptions =  [];
    $rubOptions = [];
    $adminInfotree =  \Seolan\Module\Management\Management::getAdminXmodInfotree(array());
    $templateOid = 'TEMPLATES:ADMIN-'.$adminInfotree->_moid;
    $templateok = getDB()->fetchOne('select KOID from TEMPLATES where KOID = ?', [$templateOid]);
    if (!$templateok){
      $mess = "Configuration error, template {$templateOid} not found in TEMPLATES. Backoffice item may not work.";
      \Seolan\Core\Shell::alert_admini($mess);
      \Seolan\Core\Logs::critical(__METHOD__, $mess);
      \Seolan\Core\Shell::changeTemplate('Core.message.html');
      return;
    }
    $r=array();
    if(isset($infos['moid'])){
      // (peu probable)
      if ($infos['moid'] != $this->_moid){
	$mod = \Seolan\Core\Module\Module::objectFactory(['moid'=>$infos['moid'], 'tplentry'=>TZR_RETURN_DATA]);
      } else {
	$mod = $this;
      }
      $secOptions['title']['value'] = $mod->group.' / '.$mod->getLabel();
      if ($newrub){
	$rubOptions['title']['value'] = $mod->group.' / '.$mod->getLabel();
	$rubOptions['descr']['value'] = $mod->comment;
      } else {
	$secOptions['comment']['value'] = $mod->comment;
      }
    }
    if($newrub) $adminInfotree->_categories->input(array('tplentry'=>$tplentry.'rub', 'options'=>$rubOptions));
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=CS8SEC');
    $xds->input(array('tplentry'=>$tplentry.'sec', 'options'=>$secOptions));
    $adminInfotree->home(array('tplentry'=>$tplentry.'tree','maxlevel'=>1,'norubric'=>true));
    $fct=\Seolan\Module\User\User::_normalizeBookmark($fct);
    $fct=$this->_normalizeFct($fct, $infos,'newsection');
    $r['fct']=$fct;
    $f=$infos['_function']?$infos['_function']:$infos['function'];
    if($infos['moid'] && ($f=='procQuery' || $f=='browse')){
      if($mod->persistentquery){
	$r['persistent']=1;
      }
    }
    if (isset($infos['moid'])){
      $r['_next'] = $mod->getMainAction();
    } else {
      $r['_next'] = \Seolan\Core\Shell::get_back_ur(-1);
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }
  /// Ajoute une entree de module dans le menu gauche
  function adminProcNewSection($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $adminInfotree =  \Seolan\Module\Management\Management::getAdminXmodInfotree();
    $linkup=$p->get('linkup');
    $createrub=$p->get('createrub');
    if($createrub){
      $rub=$adminInfotree->procInput($ar);
      $linkup=$rub['oid'];
    }
    $ar2=$p->get('sec');
    parse_str($ar2['fct'], $infos);
    $ar2['_options'] = ['local'=>true];
    $ar2['modid'] = $this->_moid;
    $ar2['fct']=\Seolan\Module\User\User::_normalizeBookmark($ar2['fct']);
    $ar2['oidit']=$linkup;
    $ar2['oidtpl']='TEMPLATES:ADMIN-'.$adminInfotree->_moid;
    if(!empty($ar2['persistent'])) $ar2['fct'].='&_persistent=1';
    $adminInfotree->insertsection($ar2);
  }
  // nettoyage des paramètres indésirables lors de la mémorisation d'une url
  protected function _normalizeFct($fct, $infos, $from){
    return $fct;
  }
  /* Webservice du module */
  /// Genere le wsdl du module : fonctions par defaut => auth, close
  function SOAPWSDL($ar=NULL){
    header('Content-type: text/xml');
    $baseUri = $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().'/SeolanService'.$this->_moid.'/';
    $wsdl=new \Zend\Soap\Wsdl('SeolanService'.$this->_moid,$GLOBALS['TZR_SESSION_MANAGER']::makeDomainName(),null);
    $this->_SOAPAddTypes($wsdl,array('authParam'=>array(array('name'=>'login','minOccurs'=>1,'maxOccurs'=>1,'type'=>'xsd:string'),
							array('name'=>'password','minOccurs'=>1,'maxOccurs'=>1,'type'=>'xsd:string')),
				     'contextParam'=>array(array('name'=>'sessid','minOccurs'=>1,'maxOccurs'=>1,'type'=>'xsd:string'),
							   array('name'=>'lang','minOccurs'=>0,'maxOccurs'=>1,'type'=>'xsd:string'))));
    // pour les classes filles
    $this->_SOAPWSDLTypes($wsdl);

    $wsdl->addMessage('authIn',array('param'=>'tns:authParam'));
    $wsdl->addMessage('authOut',array('sessid'=>'xsd:string'));
    $wsdl->addMessage('closeIn',array('context'=>'tns:contextParam'));
    $wsdl->addMessage('closeOut',array('return'=>'xsd:int'));

    // pour les classes filles
    $this->_SOAPWSDLMessages($wsdl);

    $pt=$wsdl->addPortType('SeolanPort'.$this->_moid);
    $wsdl->addPortOperation($pt,'auth','tns:authIn','tns:authOut');
    $wsdl->addPortOperation($pt,'close','tns:closeIn','tns:closeOut');

    // pour les classes filles
    $this->_SOAPWSDLPortOps($wsdl,$pt);

    $b=$wsdl->addBinding('SeolanPortBinding'.$this->_moid,'tns:SeolanPort'.$this->_moid);
    $wsdl->addSoapBinding($b,'rpc');
    $bo=$wsdl->addBindingOperation($b,'auth',array('use'=>'literal'),array('use'=>'literal'));
    $o=$wsdl->addSoapOperation($bo,$baseUri.'auth');
    $o->setAttribute('style','rpc');
    $bo=$wsdl->addBindingOperation($b,'close',array('use'=>'literal'),array('use'=>'literal'));
    $o=$wsdl->addSoapOperation($bo,$baseUri.'close');
    $o->setAttribute('style','rpc');

    // pour les classes filles
    $this->_SOAPWSDLBindingOps($wsdl,$b);

    $wsdl->addService('SeolanService'.$this->_moid,'SeolanPort'.$this->_moid,'tns:SeolanPortBinding'.$this->_moid,
		      $GLOBALS['TZR_SESSION_MANAGER']::complete_self(false,true).'&moid='.$this->_moid.'&function=SOAPRequest');
    $dom=$wsdl->toDomDocument();
    $out = $dom->saveXML(NULL,LIBXML_NOEMPTYTAG);
    header('Content-Length: '.strlen($out));
    die($out);
  }
  /// Ajoute des types complexes au WSDL
  function _SOAPAddTypes(&$wsdl,$types=array()){
    $wsdl->addSchemaTypeSection();
    $dom=$wsdl->toDomDocument();
    $schema=$wsdl->getSchema();
    foreach($types as $name=>$type){
      if(in_array($name,$wsdl->getTypes())) continue;
      $wsdl->addType($name, 'complexType');

      $ct=$dom->createElement('xsd:complexType');
      $ct->setAttribute('name',$name);
      $schema->appendChild($ct);
      $s=$dom->createElement('xsd:sequence');
      $ct->appendChild($s);
      foreach($type as $fields){
	$x=$dom->createElement('xsd:element');
	foreach($fields as $pn=>$pv) $x->setAttribute($pn,$pv);
	$s->appendChild($x);
      }
    }
    return;
  }
  /// Sous fonction chargée d'ajouter les types necessaires
  function _SOAPWSDLTypes(&$wsdl){
    return;
  }
  /// Sous fonction chargée d'ajouter les messages necessaires
  function _SOAPWSDLMessages(&$wsdl){
    return;
  }
  /// Sous fonction chargée d'ajouter les ports necessaires
  function _SOAPWSDLPortOps(&$wsdl,&$pt){
    return;
  }
  /// Sous fonction chargée d'ajouter les operations necessaires
  function _SOAPWSDLBindingOps(&$wsdl,&$b){
    return;
  }
  /// Retourne l'instance qui va être associée au serveur soap
  protected function _SOAPHandler(){
    return new \Seolan\Core\Module\SoapServerHandler($this);
  }
  /// Fonction d'exécution d'une requete SOAP
  function SOAPRequest($ar=NULL){
    // devrait sauter : on a l'instance du module dans le handler de base
    global $soapmod;
    $soapmod=$this;

    ini_set('soap.wsdl_cache_enabled',0);
    $server=new \Zend\Soap\Server($GLOBALS['TZR_SESSION_MANAGER']::complete_self(false,true).'moid='.$this->_moid.'&function=SOAPWSDL');

    //    $server->addFunction(array('auth','close', '\Seolan\Core\Module'));

    $server->setObject($this->_SOAPHandler());

    // on passe par un objet SOAPHandler
    //    $this->_SOAPRequestFunctions($server);

    $server->handle();
    die();
  }
  /// Applique le contexte lors d'une requete SOAP
  function SOAPContext($context,$f=NULL,$oid=''){
    if(empty($context->sessid)) throw new \SoapFault('NOSESSID','No sessid');
    session_commit();
    session_id($context->sessid);
    session_start();
    $GLOBALS['XSHELL']->_load_user();
    if(!empty($context->lang)){
      $_REQUEST['LANG_DATA']=$context->lang;
      \Seolan\Core\Shell::getLangData(NULL,true);
    }
    if(!empty($f)) {
      $ret=$this->secure($oid,$f);
      if(!$ret) throw new \SoapFault('NOAUTH','Unauthorized function');
    }
    return true;
  }
  /// Sous fonction declarant les fonctions du module
  /// a virer
  function _SOAPRequestFunctions(&$server){
    return;
  }
  /**
   * Retourne le chemin d'un gabarit surchargeable
   * @param string $templatename : templates/'module'/'nom'
   * @param string $default : la valeur par defaut de la console
   */
  function getTemplate($tplname,$default=null){
    if (defined('TZR_ALLOW_USER_TEMPLATES') && file_exists($GLOBALS['USER_TEMPLATES_DIR'].$tplname)){
      $file = $GLOBALS['USER_TEMPLATES_DIR'].$tplname;
    } else {
      $file = $default??$tplname;
    }
    return $file;
  }
  /// Rend la liste des fonctions utilisables dans le gestionnaire de rubriques en mode fonction (tableau de paires fonction=>label)
  /// Voir wiki pour un descriptif plus detaillé (fonctions annexes : UIParam_xxx, UIEdit_xxx, UIProcEdit_xxx, UIView_xxx)
  function getUIFunctionList() {
    return array();
  }
  function ajaxGetUIFunctionList($ar){
    $class=get_class($this);
    $list=array();
    $list['f']=$this->getUIFunctionList();
    $c=class_parents($this);
    $c[$class]=$class;
    $list['c']=$c;
    die(json_encode($list));
  }

  function getAccess($ar=NULL) {
    $LANG_DATA = \Seolan\Core\Shell::getLangData();
    list($access,$l) = $GLOBALS['XUSER']->getUserAccess(get_class($this), $this->_moid, $LANG_DATA);
    return array_flip($access);
  }

  // chargement du module d'apres les parametres de la base
  function load($moid){
    if(!isInteger($moid))
      \Seolan\Library\Security::alert('\Seolan\Core\Module\Module::load: Trying to use wrong moid= '.$moid);

    $p1 = \Seolan\Core\Module\Module::findParam($moid);

    $this->toid=$p1['TOID'];
    $this->modulename = $p1['MPARAM']['modulename'];
    $mparam = $p1['MPARAM'];
    // on mémorise les valeurs brutes en provenance de la base pour pouvior les utiliser dans le initOptions
    $this->mparams = $mparam;
    // initialisation des options
    $this->initOptions();
    // chargement des valeurs des options depuis la base de données
    $this->_options->setValues($this,$mparam);
    // on n'a plus besoin de ces valeurs
    unset($this->mparams);
    $this->moduleclass_label = \Seolan\Core\Labels::getTextSysLabel(get_class($this), 'modulename');
    if(empty($this->moduleclass_label) || $this->moduleclass_label == 'modulename')
      $this->moduleclass_label = \Seolan\Core\Labels::getTextSysLabelFromClass(\Seolan\Core\Module\Module::$_modules[$p1['TOID']]['CLASSNAME'], 'modulename').' ('.get_class($this).')';
    \Seolan\Core\Logs::debug(__METHOD__.'('.$moid.'): '.$this->moduleclass_label);
  }

  // rend un tableau (libelle,url) des infos a afficher en haute de l'ecran a droite
  //
  function fastinfo($ar) {
    return NULL;
  }

  function exists($moid) {
    if(!isInteger($moid))
      \Seolan\Library\Security::alert(__METHOD__.' Trying to use wrong moid= '.$moid);

    $cnt=getDB()->count("SELECT COUNT(*) FROM MODULES WHERE MOID=?",array($moid));
    return ($cnt>0);
  }
  // rend la liste des callbacks des moduls
  //
  function modcallback($ar) {
  }

  /// Retourne la corbeilles des taches de l'ensemble des modules et/ou les signets de l'utilisateur courant
  static function &portlets($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ar['templates']=false;
    $ar['withmodules']=true;
    $ret=array();
    if($p->get('allmodules')) {
      $mods=self::modlist($ar);
      $portlet2=array();
      if (!empty($mods['lines_mods'])) {
        foreach($mods['lines_mods'] as $i => &$m1) {
          $tmpportlet2=$m1->portlet2();
          if(!empty($tmpportlet2)) $portlet2[$m1->_moid]=$tmpportlet2;
        }
      }
      $ret['tasks']=$portlet2;
    }
    if($p->get('bookmarks')) {
      $users=\Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
      if(!empty($users)) {
        $bookmarks=$users->getBookMarks($ar);
        ksort($bookmarks);
        $ret['bookmarks']=$bookmarks;
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /**
   * Listes de base de modules et de leur propriétés
   * @note : ne doit pas induire de chargement de classes,
   * en particulier de classes de modules
   */
  static function modsprops($ar=NULL){
    $p = new \Seolan\Core\Param($ar, array('toid'=>null));
    $modsprops = array();
    $cond = array();
    if ($p->is_set('toid')){
      $toid = $p->get('toid');
      $cond[] = is_array($toid) ? "TOID IN (\"".implode("\",\"",$toid)."\")" : "TOID=\"$toid\"";
    }
    $rs=getDB()->fetchAll('select * from MODULES '.(empty($cond) ? '' : 'WHERE '.implode(' AND ',$cond)).' order by TOID');
    foreach($rs as $ors) {
      $ors['MPARAM'] = \Seolan\Core\Options::decode($ors['MPARAM']);
      $modsprops[] = $ors;
    }
    return $modsprops;
  }
  /// Recupere une liste de module
  static function &modlist($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('basic'=>false,'tplentry'=>TZR_SESSION_PREFIX.'modules','templates'=>true,'noauth'=>false,
                            'refresh'=>false,'clearnav'=>false,'toid'=>'','table'=>''));
    $LANG_DATA=\Seolan\Core\Shell::getLangData();
    $tplentry=$p->get('tplentry');
    $toid=$p->get('toid');
    $withmodules=$p->get('withmodules');
    $table=$p->get('table');
    $filterclass=$p->get('filterclass', 'local');
    $noauth=$p->get('noauth');
    $templates=$p->get('templates');
    $refresh=$p->get('refresh');
    $basic=$p->get('basic');
    $lines_mod=array();
    $r=array('groups'=>array(),'lines_name'=>array(),'lines_oid'=>array(),'lines_methods'=>array(),
	     'lines_classname'=>array());
    if(empty($refresh) && empty($toid) && empty($table) && empty($filterclass) && !empty(\Seolan\Core\Module\Module::$_modlist)) {
      $r=\Seolan\Core\Module\Module::$_modlist;
    }else{
      if(issetSessionVar(TZR_SESSION_PREFIX.'modules') && empty($refresh) && empty($toid) && empty($table) && empty($filterclass)) {
	$r=getSessionVar(TZR_SESSION_PREFIX.'modules');
      }else{
        \Seolan\Core\Module\Module::$modlist_loading=true;
        $cond = array();
	if (!empty($toid)) {
	  $cond[] = is_array($toid) ? "TOID IN (\"".implode("\",\"",$toid)."\")" : "TOID=\"$toid\"";
        }
        if (!empty($table)) {
	  // ... boucle ?
	  //echo(__METHOD__."$table");
	  $cond[] = 'MOID IN("'.implode('","',array_keys(\Seolan\Core\Module\Module::modulesUsingTable($table,false,true,true,true))).'")';
        }
	\Seolan\Core\Module\Module::clearDependancies();
	$rs=getDB()->fetchAll('select * from MODULES '.(empty($cond) ? '' : 'WHERE '.implode(' AND ',$cond)).' order by TOID');
	foreach($rs as $ors) {
	  $seen=$noauth;
	  $toid=$ors['TOID'];
	  $mmoid=$ors['MOID'];
	  $classname = \Seolan\Core\Module\Module::$_modules[$toid]['CLASSNAME'];
	  $mod=\Seolan\Core\Module\Module::objectFactory($mmoid);
	  if(!is_object($mod) || (!empty($filterclass) && !is_a($mod, $filterclass))) {
	    continue;
	  }
	  if(!$seen && isset($GLOBALS['XUSER'])){
	    $seen=$mod->secure('','_index',$tmp=null,$LANG_DATA);
	  }
	  if(!$seen && \Seolan\Core\Shell::admini_mode()){
	    $mod->mkDependancy();
	  }
	  if($seen) {
	    if(!empty($mod->group)) {
	      if(!in_array($mod->group, $r['groups'])) $r['groups'][]=$mod->group;
	    }
	    if(\Seolan\Core\Shell::admini_mode()) $mod->mkDependancy();
	    $r['lines_name'][]=$mod->getLabel();
	    $r['lines_oid'][]=$mmoid;
	    $r['lines_group'][]=$mod->group;
	    $r['lines_toid'][]=$toid;
	    $r['lines_classname'][]=$classname;
            $r['lines_insearchengine'][]=$mod->insearchengine;
	    $lines_mod[]=$mod;
	  }
	}
	unset($rs);
	// Tri les groupes, puis tri les noms de modules en reorganisant en meme temps oid/classname/toid
	if(!empty($r['groups'])) sort($r['groups'],SORT_STRING);
	array_multisort($r['lines_group'], $r['lines_name'],$r['lines_oid'],$r['lines_classname'],
			$r['lines_toid'],$r['lines_insearchengine'],$lines_mod,SORT_ASC,SORT_STRING);

	if(empty($cond) && \Seolan\Core\User::authentified()){
	  setSessionVar(TZR_SESSION_PREFIX.'modules',$r);
	  clearSessionVar(TZR_SESSION_PREFIX.'modmenu');
	}
        \Seolan\Core\Module\Module::$modlist_loading=false;
      }
      // Recupere certaines infos du module
      if(!$basic){
	unset($mod);
	foreach($lines_mod as $ii => $mod) {
          $r['lines_dependant'][$ii]=$mod->dependant_module;
          $r['lines_home'][$ii]=!$mod->isDependant() && $mod->home;
          $r['lines_methods'][$ii]=$mod->actionlist($LANG_DATA);
          $r['lines_mainaction'][$ii]=$mod->getMainAction();
	}
	if(empty($cond)) \Seolan\Core\Module\Module::$_modlist=$r;
      }
    }

    // Calcul des données relatives aux templates des modules
    if($templates && !empty(\Seolan\Core\Module\Module::$_modules) && !$basic) {
      $module_builder_list = array();
      unset($mod);
      foreach(\Seolan\Core\Module\Module::$_modules as $toid=>$mod) {
	if(!empty($toid) && is_array($mod) && !self::singletonModuleExists($toid)) {
	  $classname=$mod['CLASSNAME'];
	  $module_builder_list[$mod['GROUP']][] = array(
	    'name' => $mod['MODULE'],
	    'toid' => $toid,
	    'classname' => self::getModuleWizardPath($classname),
	    'class_label' => ($classlabel=\Seolan\Core\Labels::getTextSysLabelFromClass($classname,'modulename'))=='modulename'?$classname:$classlabel);
	}
      }
      \Seolan\Core\Shell::toScreen2('module', 'builder_list', $module_builder_list);
    }

    // construction des modules si demande
    if($withmodules) {
      if(empty($lines_mod)) {
	foreach($r['lines_oid'] as $ii => $mmoid) {
	  $lines_mod[$ii]=\Seolan\Core\Module\Module::objectFactory($mmoid);
	}
      }
      $r['lines_mod']=$lines_mod;
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }
  /// Action principale du menu
  public function getMainAction(){
    return '';
  }
  public function gotoMainAction(){
    header('Location: '.$this->getMainAction());
    exit(0);
  }


  protected function _clearActionlist(&$my) {
    foreach($my as $i=>$o) {
      if(is_object($o) && ($i!='delete') && ($i!='editProperties') && $i!='lsSecurity' && $o->group!='helpitems') {
	unset($my[$i]);
      }
    }
  }

  /// Listes des actions générales du module
  protected function _actionlist(&$my,$alfunction=true) {
    $myclass=get_class($this);
    $moid=$this->_moid;
    $function=\Seolan\Core\Shell::_function();

    $this->setHelpModuleActions($my, $function);

    $this->setBatchActions($my, $alfunction);

    // Suppression module
    if($this->secure('','delete')){
      $o1=new \Seolan\Core\Module\Action($this,'delete',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete'),
			  '&moid='.$moid.'&_function=delete&template=Core.message.html','editprop');
      $o1->needsconfirm=true;
      $my['delete']=$o1;
      // Suppression module + tables
      $o1=new \Seolan\Core\Module\Action($this,'deletewithtable',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','deletewithtable'),
                          '&moid='.$moid.'&_function=delete&template=Core.message.html&withtable=1','editprop');
      $o1->needsconfirm=true;
      $my['deletewithtable']=$o1;
    }
    // duplication module et tables
    if($this->secure('','duplicateModule')){
      $o1=new \Seolan\Core\Module\Action($this,'duplicatemodule',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','duplicatemodule'),
                          '&moid='.$moid.'&_function=duplicateModule&template=Core.message.html&_next=back','editprop');
      $o1->needsconfirm=true;
      $my['duplicatemodule']=$o1;
    }
    // Infos sur le module
    if($this->secure('','getInfos')){
      $o1=new \Seolan\Core\Module\Action($this,'getInfos',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','infos'),
			    '&moid='.$moid.'&_function=getInfos&template=Core/Module.infos.html&tplentry=br','admin');
      $o1->setToolbar('Seolan_Core_Module_Module','infos');
      $my['getInfos']=$o1;
    }
    // Gestion securité du module
    if($this->secure('','lsSecurity')){
      $o1=new \Seolan\Core\Module\Action($this,'lsSecurity',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','security'),
			    '&moid='.$moid.'&_function=lsSecurity&template=Core/Module.lssecurity.html&tplentry=br','admin');
      $o1->setToolbar('Seolan_Core_General','security');
      $my['lsSecurity']=$o1;
    }
    // Edition propriétés module
    if($this->secure('','editProperties')){
      $o1=new \Seolan\Core\Module\Action($this,'editProperties',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','properties'),
			    '&moid='.$moid.'&_function=editProperties&template=Core/Module.admin/editprop.html&tplentry=props','admin');
      $o1->setToolbar('Seolan_Core_General','properties');
      $my['editProperties']=$o1;
    }

    // corbeille du module
    if($this->usetrash &&  $this->secure('','browseTrash')){
      $browseTrashAction=new \Seolan\Core\Module\Action($this,
							'browseTrash',
							\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','trash'),
							'&moid='.$moid.'&_function=browseTrash&template='.static::$browsetrashtemplate.'&tplentry=trash',static::$trashmenugroup);
      
      $browseTrashAction->menuable=true;
      $browseTrashAction->order=static::$trashmenuorder; // positionner les prop. avant setToolbar
      $browseTrashAction->setToolbar('Seolan_Core_General','trash');
      $my['trash']=$browseTrashAction;
    }

    $my['moid']=$this->_moid;
    $my['classname']=$myclass;
    $my['moduleclass']=$this->moduleclass_label;
    if($this->interactive) {
      $my['stack']=array();
      if($alfunction) $this->_actionlistonfunction($my);
    }
    // ajout dans le menu more de toutes les actions de workflow applicable sur ce module
    if(!empty($_REQUEST["oid"]) && \Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID)) {
      $umod=\Seolan\Core\Module\Module::objectFactory(array("toid"=>XMODWORKFLOW_TOID,"moid"=>"","tplentry"=>TZR_RETURN_DATA));
      $workflows=$umod->getWorkflows($this, "user", "edit", $_REQUEST["oid"]);
      foreach($workflows as $i=>$f) {
	$o1=new \Seolan\Core\Module\Action($this,"workflow".$i, $f[1],
			      "&moid={$this->_moid}&_function=newWFCase&template=Module/Workflow.newcase.html&tplentry=br&oid=".
			      $_REQUEST["oid"]."&wfid=".$f[0],"more");
	$o1->menuable=true;
	if($f[2]) $o1->actionable=true;
	$my["wf$i"]=$o1;
      }
    }
  }

  // Helper pour ajouter plus facilement une fonction depuis _actionlist
  // Ex : $this->addMenuFunction($my, 'maFonction', 'Mon label');
  function addMenuFunction(&$my, $function, $label, $parent="more", $options = []) {
    $options = array_merge(array(
      'nosecure' => false,
      'template' => 'Core.message.html',
      'url' => '',
      'popup' => false,
      'noajax' => false,
      'containable' => true,
      'menuable' => true,
      'toolbarDomain' => '',
      'toolbarIcon' => '',
    ), $options);
    $url = $options['url'];
    $myoid = $_REQUEST['oid'] ?? '';
    if($options['nosecure'] || $this->secure($myoid, $function)) {
      if(strpos($url, 'javascript:') === false) {
        $url = strpos($url, 'moid=') === false ? $url . '&moid=' . $this->_moid : $url;
        $url = strpos($url, '_function=') === false ? $url . '&_function=' . $function : $url;
        $url = strpos($url, 'template=') === false ? $url . '&template=' . $options['template'] : $url;
        $url = strpos($url, 'tplentry=') === false ? $url . '&tplentry=br' : $url;
      }

      if($options['noajax']) {
        $self = $GLOBALS['TZR_SESSION_MANAGER']::complete_self();
        $url = 'javascript:document.location.href="' . $self . $url . '";';
      }
      elseif($options['popup']) {
        $self = $GLOBALS['TZR_SESSION_MANAGER']::complete_self();
        $url = 'javascript:TZR.Dialog.openURL("' . $self . $url . '");';
      }

      $o1 = new Action($this, $function, $label, $url, $parent);
      $o1->containable = $options['containable'];
      $o1->menuable = $options['menuable'];
      if($options['toolbarDomain'] && $options['toolbarIcon']) {
        $o1->setToolbar($options['toolbarDomain'], $options['toolbarIcon']);
      }
      $my[$function] = $o1;
    }
  }

  /// Appel la fonction de listage des actions propre à une fonction
  protected function _actionlistonfunction(&$my) {
    // appel d'une fonction de calcul specifique a la fonction en cours
    $func1=\Seolan\Core\Shell::_function();
    if(!empty($func1) && method_exists($this, 'al_'.$func1)) {
      $func1='al_'.$func1;
      $this->$func1($my);
    }
  }

  /// Rend la liste des fonctions disponibles comme point d'entree initial de ce module (obsolete ??)
  function &actionlist($lang=null) {
    $my=array();
    // on va chercher la liste des fonctions disponibles en points d'accès de ce module
    $this->_actionlist($my);

    // pour chaque fonction on verifie si l'utilisateur courant a les droits
    foreach($my as $i => $v) {
      if(is_array($v)) {
	$seen=false;
	if(isset($GLOBALS['XUSER']) && !empty($v['function'])) {
	  $seen=$this->secure('',$v['function'],$user=null,$lang);
	}
	if(!$seen) {
	  unset($my[$i]);
	}
      }
    }
    return $my;
  }

  /// Retourne la liste des actions du module
  public function &actionlist1() {
    $o=&\Seolan\Core\Shell::from_screen('imod','props');
    $o['actions']=array();
    $this->_actionlist($o['actions']);
    \Seolan\Core\Shell::toScreen2('imod','props',$o);
    return $o['actions'];
  }

  /// Récupère la liste des actions "containable" au format ajax
  function ajaxGetContainableActionList($ar){
    $actions=$this->actionlist1();
    $contactions=array();
    foreach($actions as $n=>&$a){
      if($a->containable){
	$contactions[$n]=$a;
      }
    }
    die(json_encode($contactions));
  }

  function viewProperties($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $r = $this->_options->getView($this);
    if($tplentry!=TZR_RETURN_DATA) {
      $a=array();
      \Seolan\Core\Shell::toScreen2($tplentry,'options',$r);
    } else  return $r;
  }

  function editProperties($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $lang_data=\Seolan\Core\Shell::getLangData();
    list($access,$l) = $GLOBALS['XUSER']->getUserAccess(get_class($this), $this->_moid, $lang_data);

    if ($this->insearchengine){
      $this->_options->setComment($this->getInsearchengineFields(), 'insearchengine');
    }

    $r1=array_reverse($access);
    $r = $this->_options->getDialog($this, NULL, 'options', $r1[0]);
    if($tplentry!=TZR_RETURN_DATA) {
      $actions=array();
      $this->_actionlist($actions);
      \Seolan\Core\Shell::toScreen2($tplentry,'functions',$actions);
    }
    if($tplentry!=TZR_RETURN_DATA){
      \Seolan\Core\Shell::toScreen2($tplentry,'options',$r);
      \Seolan\Core\Shell::toScreen2($tplentry,'version',$this->getAncestorsAndVersions());
    }
    return $r;
  }

  /// Retourne sous forme d'une chaine les infos sur la classe et sa version
  function getAncestorsAndVersions($delta=false,$details=false) {
        $parents = getAncestors(get_class($this));
        $upgrades = \Seolan\Core\DbIni::getStatic('upgrades', 'val');
	$list = [];
	$toapply = [];
        foreach ($parents as $cl) {
            $cll = strtolower($cl);
            $mtxt = $cl;
	    if (isset($upgrades['\\'.$cll])){
	      $clupgrades = $upgrades['\\'.$cll];
	    }elseif(isset($upgrades['\\'.$cl])){
	      $clupgrades = $upgrades['\\'.$cl];
	    } else {
	      $clupgrades = [];
	    }
            $ref = new \ReflectionClass($cl);
            if ($ref->hasProperty('upgrades') && $ref->getProperty('upgrades')->class == $cl) {
                $mtxt.= ':' . end($clupgrades);
		if ($delta){

		  $to_apply = array_diff(array_keys($cl::$upgrades), $clupgrades);

		  if (!empty($to_apply)){
                    $mtxt .= '[to apply : ' . implode(',', $to_apply) . ']';
		    if ($details){
		      if (!isset($toapply[$cl]))
			$toapply[$cl] = [];
		      foreach($to_apply as $p)
			$toapply[$cl][] = [$cl::$upgrades[$p]=>$p];
		    }
		  }
		}
            }
	    $list[]=$mtxt;
        }
	if ($details)
	  return [$list, $toapply];
	else
	  return $list;
  }

  /// Recalcul des dependances sur les modules (si on est en mode admin seulement)
  static function clearDependancies() {
    if(\Seolan\Core\Shell::admini_mode()) \Seolan\Core\DbIni::clear('dependant%', false);
  }
  function mkDependancy() {
    for($i=1;$i<=$this->submodmax;$i++) {
      $f1='ssmod'.$i;
      $dep=0;
      if(!empty($this->$f1)) {
	// recherche du module concerné par la sous-fiche
	$mod1=\Seolan\Core\Module\Module::objectFactory($this->$f1);
	if(is_object($mod1)) {
	  try {
	    // recherche de la table qui contient les données
	    // recherche dans la table destination des liens qui pointent vers nous
	    $tab1=$mod1->xset;
	    // recherche des liens qui pointent vers moi
	    $links1=$tab1->getXLinkDefs(NULL,$this->table);
	    if(!empty($links1)) {
	      $linkfield=$links1[0];
	      $f2=$tab1->getField($linkfield);
	      if(!$f2->get_multivalued() && $f2->get_compulsory()) {
		$dep=$this->_moid;
	      }
	    }
	  } catch(\Exception $e) {
	  }
	}
	\Seolan\Core\DbIni::set('dependant:'.$this->$f1,$dep);
      }
    }
  }

  // sauvegarde des nouvelles proprietes d'un module
  //
  function procEditProperties($ar) {
    $ar = $this->triggerCallbacks(self::EVENT_PRE_PROC_EDIT_PROPERTIES, $ar);
    $p=new \Seolan\Core\Param($ar, array());
    $prop=$p->get('options');
    $oldClass = $this->theclass;
    // workaround : le procDialog ne met pas à jour les prop. de type ttext,
    // donc $this->modulename est inchangé ici
    $newModulename = $prop['modulename'][TZR_DEFAULT_LANG]??$this->getLabel();
    $this->_options->procDialog($this,$prop);
    if(!empty($this->theclass)) {
      tzr_autoload($this->theclass);
      if(!is_subclass_of($this->theclass, '\Seolan\Core\Module\Module')) {
        \Seolan\Core\Shell::alert("classe '$this->theclass' non trouvée, ou n'hérite pas de '\Seolan\Core\Module\Module'");
        $this->theclass=$oldClass;
      }
      if (empty($ar['quick'])) {
        $this->mkDependancy();
      }
    }
    // on determine les dependances entre modules
    $json=$this->_options->toJSON($this);
    getDB()->execute('UPDATE MODULES set MODULE=?, MPARAM=? where MOID=? ',array($this->getLabel(),$json,$this->_moid));
    \Seolan\Core\Module\Module::clearCache();
    // suppression du cache de correspondance entre tables et modules
    \Seolan\Core\DbIni::clear('modules%');
    if (empty($ar['quick'])) {
      \Seolan\Core\Integrity::chkLogInfo();
    }
  }

  static function _getProperties($moid) {
    $params=\Seolan\Core\Module\Module::findParam($moid);
    return $params['MPARAM'];
  }


  /// suppression du module
  function delete($ar) {
    $p=new \Seolan\Core\Param($ar, array('tplentry'=>''));
    $tplentry=$p->get('tplentry');
    $withtable=$p->get('withtable');

    // delete acl4 about that module
    $rs=getDB()->select('select * from ACL4 where AMOID=?',array($this->_moid));
    if($ors=$rs->fetch()) {
      $aclgkoid=$ors['AGRP'];
      getDB()->execute("DELETE FROM ACL4 where AMOID=?", [$this->_moid]);
      getDB()->execute("DELETE FROM ACL4_CACHE where AMOID=?", [$this->_moid]);
      \Seolan\Core\Logs::secEvent(__METHOD__,"Delete module ($this->_moid) and related ACL", $moid);
    }
    getDB()->execute("DELETE FROM MODULES where MOID=?",[$this->_moid]);

    // delete module comments
    getDB()->execute("DELETE FROM AMSG where MOID like ?", ["module:{$this->_moid}:%"]); // comments, modulename
    // delete BO menu
    getDB()->execute("DELETE CS8.*, ITCS8.*, CS8SEC.* FROM CS8 join ITCS8 on KOIDSRC=CS8.KOID join CS8SEC on KOIDDST=CS8SEC.KOID where fct like '%moid=$this->_moid&%'");

    $message='Module '.$this->getLabel().' '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','deleted').'<br>';

    // Suppression de la table si necessaire
    if(!empty($withtable)){
      $tables=$this->usedTables();
      foreach($tables as $table) {
        if(!empty($table)) {
          $message.= 'DROP ';
          if(\Seolan\Core\DataSource\DataSource::sourceExists($table,true)) {
            $xbase=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);
            $ret=$xbase->procDeleteDataSource(array('action'=>'OK','tplentry'=>TZR_RETURN_DATA));
            if(!empty($ret['message'])) $message.=$ret['message'];

          } else {
            getDB()->execute('drop table if exists '.$table);
            $message.=$table.' : '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','update_ok').'<br>';
          }
	  if(!\Seolan\Core\DataSource\DataSource::sourceExists($table,true)) {
          //Delete Data if exists
          $dirmsg = 'data/'.$table;
          $dirname = TZR_WWW_DIR.'data/'.$table;
          if (file_exists($dirname)) {
            \Seolan\Library\Dir::clean($dirname, time());
            rmdir($dirname);
            $message.=('suppression du répertoire '.$dirmsg.'<br>');
          }
	  }
        }
      }
    }

    // Suppression des sections fonction utilisant le module supprimé dans tous les gestionnaires de rubrique
    static::clearCache(); // si on vient de supprimer un infotree, pas la peine de le traiter
    foreach(\Seolan\Core\Module\Module::$_mcache as $moid=>&$ors) {
      $mod=\Seolan\Core\Module\Module::objectFactory($moid);
      if(empty($mod)) continue;

      // nettoyage des sections fonction
      if($mod instanceof \Seolan\Module\InfoTree\InfoTree) {
        $sections=getDB()->fetchCol('select KOID from '.$mod->dyntable.' '.
                                    'where module=? or query like "%\"moid\":\"'.$this->_moid.'\"%"',[$this->_moid]);
        foreach($sections as $section){
          $its=getDB()->fetchCol("select ITOID from {$mod->tname} where KOIDDST=?",array($section));
          foreach($its as $it){
	           $mod->delSection(array('oidsection'=>$it));
          }
        }
      }

      // nettoyage des sections de l'infotree d'admin
      if($mod instanceof \Seolan\Module\BackOfficeInfoTree\BackOfficeInfoTree) {
      	$sections=getDB()->fetchCol("select KOID from CS8SEC where modid=?",[$this->_moid]);
      	foreach($sections as $section){
      	  $its=getDB()->fetchCol('select ITOID from '.$mod->tname.' where KOIDDST=?',array($section));
      	  foreach($its as $it){
      	    $mod->delSection(array('oidsection'=>$it));
      	  }
      	}
      }
    }

    // suppression du cache de correspondance entre tables et modules
    \Seolan\Core\DbIni::clear('modules%');
    \Seolan\Core\Module\Module::clearCache();

    setSessionVar('_reloadmods',1);
    setSessionVar('_reloadmenu',1);
    if ($tplentry != TZR_RETURN_DATA){
      $GLOBALS['XSHELL']->rawdata['nonav'] = 1;
      setSessionVar('message', $message);
      \Seolan\Core\Shell::setNext(makeUrl($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true),
					  ['function'=>'portail',
					   'template'=>'Core.message.html',
					   'moid'=>\Seolan\Core\Module\Module::getMoid(XMODADMIN_TOID)]));
    }
    return \Seolan\Core\Shell::toScreen2($tplentry,'message',$message);
  }

  function get_prop($ar=NULL) {
    global $XSHELL;
    $this->display_prop($ar);
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    return $XSHELL->tpldata[$tplentry][$ar['prop']];
  }
  static function moduleSelector($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $toid=$p->get('toid');
    $table=$p->get('table');
    $filterclass=$p->get('filterclass');
    $r=\Seolan\Core\Module\Module::modlist(array('refresh'=>true, 'templates'=>false, 'tplentry'=>TZR_RETURN_DATA,'toid'=>$toid,'table'=>$table,'class'=>$filterclass,'basic'=>true));
    $name=$p->get('fieldname');
    $emptyok=$p->get('emptyok');
    $value=$p->get('value');
    if ($p->get('multivalued')) $txt='<select name="'.$name.'[]" multiple>';
    else $txt='<select name="'.$name.'">';
    if(!empty($emptyok)) $txt.='<option value="0">---</option>';
    $txt.='<optgroup label="'.htmlentities($r['lines_group'][0],ENT_COMPAT,TZR_INTERNAL_CHARSET).'">';
    $cnt=count($r['lines_oid']);
    for($i=0;$i<$cnt;$i++) {
      if($i>0 && $r['lines_group'][$i]!=$r['lines_group'][$i-1]){
	$txt.='<optgroup label="'.htmlentities($r['lines_group'][$i],ENT_COMPAT,TZR_INTERNAL_CHARSET).'">';
      }
      $vi=$r['lines_oid'][$i];
      $selected = $vi == $value || is_array($value) && in_array($vi, $value) ? ' selected' : '';
      $txt.="<option value=\"$vi\"$selected >{$r['lines_name'][$i]}</option>";
      if(($i+1<$cnt) && ($r['lines_group'][$i]!=$r['lines_group'][$i+1])){
	$txt.='</optgroup>';
      }
    }
    $txt.='</optgroup>';
    $txt.='</select>';
    return $txt;
  }
  function status($ar=NULL) {
    $msg1=array();
    \Seolan\Core\Shell::toScreen2('imod','status',$msg1);
  }


  /// generation de l'ecran d'edition de la securite
  function secEdit($ar) {
    $p = new \Seolan\Core\Param($ar,[]);
    $oid=$p->get('oid');
    \Seolan\Core\Labels::loadLabels('Seolan_Core_Security');
    $tplentry=$p->get('tplentry');
    $xuser1=&$GLOBALS['XUSER'];
    $acls = $xuser1->getObjectAccess($this, \Seolan\Core\Shell::getLangData(), $oid);
    $acl=$xuser1->listObjectAccess($this, \Seolan\Core\Shell::getLangData(), $oid, true);
    $withFO=$p->get('withFO');
    $withEmptyGrps=$p->get('withEmptyGrps');

    // recherche des droits sur les groupes
    list($acl_user, $acl_grp)=\Seolan\Core\User::getUsersAndGroups(false,true,NULL,$withFO,$withEmptyGrps);
    if(!empty($oid)) {
      foreach($acl_grp['lines_oid'] as $i=>$uoid) {
        list($l1,$l2) = $xuser1->getObjectAccess($this, TZR_DEFAULT_LANG, $oid, $uoid);
        if(in_array($uoid, $acl['acl_uid'])) $acl_grp['lines_l1'][$i]=array_reverse($l1);
        else $acl_grp['lines_l1'][$i]=array('default');
        $acl_grp['lines_l2'][$i]=$l2;

        // recherche des droits herites
        $l3=array_reverse($l2);
        foreach($l3 as $j=>$ri) {
          if($this->secure($oid,':'.$ri,$uoid)) {
            $acl_grp['lines_l3'][$i][]=$ri;
            break;
          }
        }
      }
    }
    \Seolan\Core\Shell::toScreen1('users',$acl_user);
    \Seolan\Core\Shell::toScreen1('grps',$acl_grp);
    \Seolan\Core\Shell::toScreen2('acls','lines', $acls[0]);
    return \Seolan\Core\Shell::toScreen1($tplentry,$acl);

  }
  /**
   * preparation edition des droits sur un/des objets
   * - droits actuels
   * - selecteur de groupes
   * - selecteur d'utilisateurs
   */
  function secEditSimple($ar) {
    $p=new \Seolan\Core\Param($ar,['withEmptyGrps'=>0]);
    if ($p->is_set('field'))
        $oidsOrField = [$p->get('field')];
    else
        $oidsOrField=\Seolan\Core\Kernel::getSelectedOids($p);
    \Seolan\Core\Labels::loadLabels('Seolan_Core_Security');
    $tplentry=$p->get('tplentry');
    $withEmptyGrps = $p->get('withEmptyGrps');
    $xuser1=&$GLOBALS['XUSER'];
    list($acl_user, $acl_grp)=\Seolan\Core\User::getUsersAndGroups(false,'groups',null,false,$withEmptyGrps);
    $acl=array('lines'=>[]);
    $lang_data = \Seolan\Core\Shell::getLangData();
    if(is_array($oidsOrField)) {
      foreach($oidsOrField as $oid){
        $acl['lines'][]=$xuser1->listObjectAccess($this,$lang_data,$oid,true);
      }
    }
    if ($this->getTranslatable()){
      \Seolan\Core\Shell::toScreen2('current', 'lang', $lang_data);
    }

    // ajout d'un champ user pour sélection des users
    $moduser = Module::objectFactory(['toid'=>XMODUSER2_TOID,'interactive'=>false,'tplentry'=>TZR_RETURN_DATA,'_options'=>['local'=>1]]);
    $userField = $moduser->getUserSelector($this->_moid, ['field'=>'uid',
							  'label'=>\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','users')]);
    \Seolan\Core\Shell::toScreen1('us', ($foo=['userselector'=>$userField]));
    \Seolan\Core\Shell::toScreen1('grps',$acl_grp);
    \Seolan\Core\Shell::toScreen2('acls','lines',static::secList());
    $acl['_withEmptyGrps'] = $withEmptyGrps;
    return \Seolan\Core\Shell::toScreen1($tplentry,$acl);
  }
  /**
   * le module gere les langues, notament sur les droits
   */
  function getTranslatable(){
    return false;
  }
  /**
   * mise à jour des droits sur un/des objets pour un/des groupes ou users
   * destinataires des droits => uid, liste d'oid de groupes ou de users
   * !! si Field\User de base post_edit nécessaire
   */
  function procSecEdit($ar){
    $p=new \Seolan\Core\Param($ar,['userfield'=>null]);
    $uids=$p->get('uid');

    if ($p->is_set('userfield')){
      $moduser = Module::objectFactory(['toid'=>XMODUSER2_TOID,'interactive'=>false,'tplentry'=>TZR_RETURN_DATA,'_options'=>['local'=>1]]);
      $userField = $moduser->getUserSelector($this->_moid, ['field'=>'uid',
							    'label'=>'&nbsp;']);
      $uids = $userField->fielddef->post_edit($uids)->raw;
    }
    $level=$p->get('level');
    $oids=$p->get('oid');
    if(!is_array($oids)) $oids=[$oids];
    $applyalllangs = $p->get('applyalllangs');

    if($applyalllangs) $langs=\Seolan\Core\Lang::getCodes('code');
    else $langs=array(\Seolan\Core\Shell::getLangData());

    $message=[];

    foreach($langs as $lang) {
      foreach($oids as $i=>$oid) {
	$ok=$this->isAuthorizedToSetAccess($lang,$oid,$level);
	if(!$ok) break;
      }
      if($ok){
	if (!is_array($uids))
	  $uids = [$uids];
	foreach(array_filter($uids) as $uid){
	  foreach($oids as $i=>$oid){
	    $GLOBALS['XUSER']->setUserAccess(get_class($this),$this->_moid,$lang,$oid,$level,$uid);
	  }
	}
      }else{
	$message[]=$lang.' : '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Security','noauthtosetsec');
      }
    }
    if(!empty($message))
      setSessionVar('message',implode('<br/>',$message));
  }

  function clearSecEdit($ar) {
    $p = new \Seolan\Core\Param($ar,array());
    $oid = $p->get('oid');
    $lang = \Seolan\Core\Shell::getLangData();
    $GLOBALS['XUSER']->clearUserAccess(get_class($this), $this->_moid, $lang, $oid);
  }

  /// Rend vrai si la table en question est utilisée dans le module en question
  function usesTable($table) {
    return (in_array($table,$this->usedTables()));
  }
  /// Rend vrai si les objets de la table en question sont consultables dans le module en question
  function usesMainTable($table) {
    return (in_array($table,$this->usedMainTables()));
  }
  /// Liste des tables utilisées par le module
  function usedTables() {
    return array();
  }
  /// Liste des tables dont les objects sont consultables dans le module
  function usedMainTables() {
    return array();
  }
  /// Rend vrai si le boid en question est utilisée dans le module en question
  function usesBoid($boid) {
    return (in_array($boid,$this->usedBoids()));
  }
  /// Liste des boid utilisés par le module
  function usedBoids() {
    return array();
  }

  /// Fonction d'import automatisé des données
  function import($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $spec=$p->get('spec');
    if(!empty($spec)) {
      $ors=getDB()->fetchRow('select * from IMPORTS where ID=?',array($spec));
      if($ors) {
	$specs = json_decode($ors['spec'], false); // ici on veut des objets
      }
    }
    $file=$_FILES['file']['tmp_name'];
    $this->_import(array('spec'=>$specs, 'file'=>$file));
  }

  /// Sous fonction pour l'import automatisé
  function _import($ar=NULL) {
    return false;
  }


  function manage($ar=NULL) {
    // recherche des procedures d'import applicables
    $LANG_DATA = \Seolan\Core\Shell::getLangData();
    $timport = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'IMPORTS');
    $importselect=$timport->select_query(array('cond'=>array('modid'=>array('=',$this->_moid))));
    $r=$timport->browse(array('select'=>$importselect,'pagesize'=>20,'selected'=>0,'tplentry'=>TZR_RETURN_DATA));
    \Seolan\Core\Shell::toScreen1('import',$r);
    $subs=\Seolan\Core\Module\Module::objectFactory(array('toid'=>XMODSUB_TOID,'tplentry'=>TZR_RETURN_DATA,'_options'=>array('local'=>1)));
    if(is_object($subs)) {
      list($access,$l) = $GLOBALS['XUSER']->getUserAccess(get_class($this), $this->_moid, $LANG_DATA);
      $r1=$subs->lsSubs(array('tplentry'=>TZR_RETURN_DATA,'amoid'=>$this->_moid));
      \Seolan\Core\Shell::toScreen1('subs',$r1);
    }
    if(\Seolan\Core\Shell::isRoot()) {
      $ul=$GLOBALS['XUSER']->getUserList(true);
    }
    \Seolan\Core\Shell::toScreen2('userlist','list',$ul);
  }
  function sub($ar) {
    $p = new \Seolan\Core\Param($ar, array());
    $users=$p->get('users');
    $moid=$this->_moid;
    $subs=&\Seolan\Core\Module\Module::objectFactory(array('toid'=>XMODSUB_TOID,'tplentry'=>TZR_RETURN_DATA,'_options'=>array('local'=>1)));
    if($subs->_moid!=$moid) {
      if(is_object($subs)) {
	$subs->addSub($users,$moid);
      }
    }
  }

  function unsub($ar) {
    $p = new \Seolan\Core\Param($ar, array());
    $suboid=$p->get('suboid');
    $subs=&\Seolan\Core\Module\Module::objectFactory(array('toid'=>XMODSUB_TOID,'tplentry'=>TZR_RETURN_DATA,'_options'=>array('local'=>1)));
    if(is_object($subs)) {
      $subs->rmSub(array('suboid'=>$suboid));
    }
  }

  protected function _lasttimestamp() {
    return 0;
  }
  protected function _whatsNew($ts, $user, $group=NULL, $specs=NULL,$timestamp) {
    return '';
  }
  function goto1($ar=NULL) {
    return '';
  }

  /// construction d'une entree du mail d'abonnement
  protected function _makeSubEntry($oid, $xset, $details, $ts, $timestamp, $user, $title=NULL) {
    if(empty($xset)) $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('&SPECS='.$oid);
    if(empty($xset)) return '';
    $d1=$xset->display(array('_lastupdate'=>true,'tplentry'=>TZR_RETURN_DATA,'tlink'=>true,'oid'=>$oid,'_options'=>array('error'=>'return')));
    $txt='';
    if(is_array($d1) && ($d1['lst_upd']['user']!=$user)) {
      $oid=$d1['oid'];
      $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'&moid='.$this->_moid.'&function=goto1&oid='.$oid.'&_direct=1';
      $when=$d1['oUPD']->html;
      $what=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General',$d1['lst_upd']['etype']);
      $j=\Seolan\Core\Logs::getJournal($oid,['etype'=>['=','update']],$ts,$timestamp,$xset);
      $whoarray=array();
      $modifs='<br/>';
      foreach($j['lines_journal'] as $entry=>$log) {
	if(!in_array($j['lines_ousernam'][$entry]->text, $whoarray))
	  $whoarray[]=$j['lines_ousernam'][$entry]->text;
	$modifs.=implode('<br/>',$log);
	$modifs.='<br/>';
      }
      // construction du texte
      $who=implode(', ',$whoarray);
      $txt='<li><a href="'.$url.'">'.(empty($title)?$d1['tlink']:$title).'</a> ('.$when.
	($this->trackchanges?(', '.$who.', '.$what):'').')';
      // on ajoute le detail des logs si abonnement avec detail
      if($details && $this->trackchanges) {
	$txt.=$modifs;
      }
      $txt.='</li>';
    }
    return $txt;
  }

  function getSubTitle($ors) {
    return array();
  }
  function runSub($uid, $groups, $timestamp) {
    // recherche de la date de dernière mise a jour
    $lasttimestamp=$this->_lasttimestamp();
    $modid=$this->_moid;
    $txt='';
    // recherche des abonnements concerné, c'est a dire les
    // abonnements pour lesquels la date de dernier examen est
    // anterieure a la date de derniere mise a jour d'un enregistrement
    if(!is_array($groups)) $groups=array();
    $groups[]=$uid;
    $cond=join('","',$groups);
    $rs=getDB()->select("select UPD,specs from OPTS where UPD<? and modid=? and user IN (\"$cond\") AND dtype='sub'",
			array($lasttimestamp,$modid));
    while($ors=$rs->fetch()) {
      $specs = \Seolan\Library\Opts::decodeSpecs($ors['specs']);
      if(empty($specs['oid']) || \Seolan\Core\Kernel::objectExists($specs['oid'])) {
	$txt .= $this->_whatsNew($ors['UPD'], $uid, $groups, $specs, $timestamp);
      }
    }
    return $txt;
  }

  function lsSub($uid, $groups, $aoid=NULL) {
    $modid=$this->_moid;
    if(!is_array($groups)) $groups=array();
    if(empty($uid) && empty($groups)) {
      $rs=getDB()->fetchAll("SELECT * FROM OPTS WHERE modid=? AND dtype='sub'",array($modid));
    } else {
      $groups[]=$uid;
      $cond=join('","',$groups);
      $rs=getDB()->fetchAll("SELECT * FROM OPTS WHERE modid=? AND user IN (\"$cond\") AND dtype='sub'",array($modid));
    }

    $users=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=USERS');
    $groups=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=GRP');
    $subs=array();
    $admin = $this->secure('',':admin',$tmp=null,\Seolan\Core\Shell::getLangData());
    foreach($rs as $ors) {
      if(!\Seolan\Core\Kernel::objectExists($ors['user'])) {
	// on supprime les regles d'abonnement qui correspondent a des utilisateurs
	// ou groupes qui n'existent plus
	getDB()->execute("DELETE FROM OPTS WHERE KOID=?",array($ors['KOID']));
      } else {
	$sub=array();
	$sub['title']=$this->_getSubTitle($ors['KOID']);
	$sub['upd']=\Seolan\Field\DateTime\DateTime::dateFormat($ors['UPD']);
	if(!empty($sub['title'])) {
	  if(\Seolan\Core\Kernel::getTable($ors['user'])=='USERS') {
	    $sub['user']=$users->display(array('oid'=>$ors['user'],'tplentry'=>TZR_RETURN_DATA));
	  } else {
	    $sub['group']=$groups->display(array('oid'=>$ors['user'],'tplentry'=>TZR_RETURN_DATA,'tlink'=>1));
	  }
	  $sub['deletable']=($admin?true:($uid==\Seolan\Core\User::get_current_user_uid())&&($ors['user']==$uid));
	  $subs[$ors['KOID']]=$sub;
	}
      }
    }
    unset($rs);
    return $subs;
  }

  // rend une chaine qui représente l'abonnement
  //
  function _getSubTitle($oid) {
    $ors=getDB()->fetchRow("SELECT * FROM OPTS WHERE KOID=?",array($oid));
    if($ors) {
      if(empty($ors['specs'])) {
        return \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Subscription_Subscription','allupdates');
      }
      $specs=\Seolan\Library\Opts::decodeSpecs($ors['specs']);
      $koid=$specs['oid'];
      if(\Seolan\Core\Kernel::objectExists($koid)) {
        $tab=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$koid);
        $disp=$tab->rDisplay($koid, array(), true);
        return $disp['link'];
      }
      return \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Subscription_Subscription','allupdates');
    }
    return 'Undefined';
  }

  function developer($ar=NULL) {
    $r=array();
  }

  // rend la liste des sous objets d'un objets. Inutile de redefinir si has_subojjects==false
  //
  public function subObjects($oid) {
    return array();
  }

  /// affichage des droits par utilisateur sur le module
  public function lsSecurity($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array('all'=>'groups'));
    $tplentry=$p->get('tplentry');
    $all=$p->get('all');
    $withFO=$p->get('withFO');
    $withEmptyGrps=$p->get('withEmptyGrps');
    \Seolan\Core\Labels::loadLabels('Seolan_Core_Security');
    $r1=\Seolan\Core\User::getModuleAccess($this,$all,$withFO,$withEmptyGrps);
    \Seolan\Core\Shell::toScreen1($tplentry.'u',$r1[0]);
    \Seolan\Core\Shell::toScreen1($tplentry.'g',$r1[1]);
  }

  /// changement des droits pour un ensemble d'utilisateurs sur le module
  public function procLsSecurity($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array());
    $nlevel=$p->get('nlevel');
    $level1=$nlevel[$this->_moid];
    $message=array();
    $noauth=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Security','noauthtosetsec');
    foreach($level1 as $uoid=>$level2) {
      foreach($level2 as $lang=>$level) {
	$xuser=new \Seolan\Core\User(array("UID"=>$uoid));
	$ok=$xuser->setUserAccess(NULL,$this->_moid,$lang,NULL,$level,null,false,false);
	if(!$ok){
	  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$uoid);
	  $d=$xds->rDisplayText($uoid,$lang);
	  $message[]=$d['link'].' ('.$lang.') : '.$noauth;
	}
	unset($xuser);
      }
    }
    if(!empty($message)) setSessionVar('message',implode('<br/>',$message));
  }

  // contenu de la page d'accueil
  //
  public function &portlet() {
    $txt='';
    if(!$this->home) return $txt;
    $a1=$this->actionlist(\Seolan\Core\Shell::getLangData());
    $cnt=0;
    foreach($a1 as $i=>&$v) {
      if(is_array($v) && $v['homepageable']) {
	$cnt++;
      }
    }
    if(method_exists($this,'_portlet'))
      $txt1=$this->_portlet();
    foreach($a1 as $i=>&$v) {
      if(is_object($v) && $v->homepageable) {
	$url1=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(false,true);
	if(method_exists($this,$v->xfunction.'_portlet'))
	  $txt.=$this->{$v->xfunction.'_portlet'}();
	else {
	  $txt.='>&nbsp;<a href="'.$v->xurl.'">'.$v->name.'</a>&nbsp;';
	}
      }
    }
    if(!empty($txt) || !empty($txt1))
      $pic=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','comment');
    $txt = '<p class="tzr-H3">'.$this->getLabel().'</p>'.($this->comment?'<p class="tzr-module-comment">'.$pic.$this->comment.'</p>':'').$txt;
    if(!empty($txt1)) $txt.=$txt1.'<br/>';

    return $txt;
  }

  /// Retourne le texte présent dans la section "taches" de la page d'accueil
  function &portlet2() {
    $txt='';
    return $txt;
  }

  function getTasklet() {
    return NULL;
  }

  function getShortTasklet() {
    return NULL;
  }

  function &getProperties() {
    $p['portlet']=$this->portlet();
    $p['moid']=$this->_moid;
    $p['modulename']=$this->modulename;
    $p['moduleclass']=$this->moduleclass_label;
    return $p;
  }

  /// envoi d'une notification sur une information d'un module
  function sendACopyTo($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $all=$p->get('all');
    $oidsel=$p->get('_selected');
    $oid=$p->get('oid');
    if(count($oidsel)==1) {
      $oidsel=array_keys($oidsel);
      $oid=$oidsel[0];
      $oidsel=array();
    }

    // on récupère les destinataires utilisés la dernière fois pour ce doc
    $registry=\Seolan\Core\Registry::getInstance();
    $lastUsed=(array)$registry->get($this->_moid, \Seolan\Core\User::get_current_user_uid(), $oid, "sendacopyto");
    // Liste des users/groupes
    $users = static::objectFactory(array('tplentry'=>TZR_RETURN_DATA,'toid'=>XMODUSER2_TOID,'_options'=>array('local'=>1)));

    $directoryok = true;
    if (!empty($this->directorymodule)){
      $usersmoids = \Seolan\Core\Module\Module::modulesUsingTable('USERS', true, false, true/*auth*/, true);
      if (!in_array($this->directorymodule, array_keys($usersmoids))){
	$directoryok = false;
	bugWarning("'{$this->_moid}' directory module '{$this->directorymodule}' not usable", false, false);
      }
    }
    if ($users->userselectortreeviewmode && $directoryok){
      $selectors = [];
      $selopts= ['compulsory'=>0,'multivalued'=>1];
      if (!empty($this->directorymodule)){
	$dirmod = static::objectFactory(['moid'=>$this->directorymodule,
					 'interactive'>=false,
					 'tplentry'=>TZR_RETURN_DATA
	]);
	if (!$dirmod instanceof \Seolan\Module\User\User){
	  $selopts['sourcemodule']=$this->directorymodule;
	}
      }
      foreach(['udest'=>'to','ucc'=>'cc','ubcc'=>'bcc'] as $fn=>$luname){
	$selopts['field'] = $fn;
	$selopts['label'] = '&nbsp;&nbsp';
	$lastvalues = $lastUsed[$luname]??null;
	$selectors[$fn] = $users->getTreeviewSelector($this->_moid,$selopts,$lastvalues);
      }
      $selectors['_userfield'] = true;
      $selectors['_treeview'] = true;
      \Seolan\Core\Shell::toScreen1('selector', $selectors);
    } else {

      // voir si ça peut s'enlever à terme, au moins en partie
      // !! brg conditionne des choses le template (confirmSend)
      $list=\Seolan\Core\User::getUsersAndGroups(true,$all,@$this->directorymodule);
      \Seolan\Core\Shell::toScreen1('bru',$list[0]);
      \Seolan\Core\Shell::toScreen1('brus_selected',$lastUsed);
      \Seolan\Core\Shell::toScreen1('brg',$list[1]);
      \Seolan\Core\Shell::toScreen2('brm', 'directory_module', @$this->directorymodule);

      // intégration du champ user : on récupère un champ user en mode standard (avec autocompletion et cie)
      // comme pour le mode treeview
      $selectors = [];
      $selopts= ['compulsory'=>0,'multivalued'=>1];
      if (!empty($this->directorymodule)){
	$dirmod = static::objectFactory(['moid'=>$this->directorymodule,
					 'interactive'>=false,
					 'tplentry'=>TZR_RETURN_DATA
	]);
	if (!$dirmod instanceof \Seolan\Module\User\User){
	  $selopts['sourcemodule']=$this->directorymodule;
	}
      }
      $ulabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module', 'userlist');
      foreach(['udest'=>'to','ucc'=>'cc','ubcc'=>'bcc'] as $fn=>$luname){
	$selopts['field'] = $fn;
	$selopts['label'] = $ulabel; //'&nbsp;&nbsp';
	$lastvalues = $lastUsed[$luname]??null;
	$selectors[$fn] = $users->getUserSelector($this->_moid,$selopts,$lastvalues);
      }
      $selectors['_userfield'] = true;
      \Seolan\Core\Shell::toScreen1('selector', $selectors);
    }

    $user=$users->display(array('oid'=>\Seolan\Core\User::get_current_user_uid(),'tplentry'=>TZR_RETURN_DATA));

    // Recupere les infos des docs à envoyer
    $files=array();
    if(empty($oidsel)){
      if($this->secure($oid, ':sro')) {
        $d=$this->display(array('oid'=>$oid,'tplentry'=>TZR_RETURN_DATA, 'tlink'=>true));
        $donnee['lines_oid'][]=$oid;
        $donnee['lines_link'][]=$d['link'];
        $donnee['lines_tlink'][]=$d['tlink'];
        \Seolan\Core\Shell::toScreen1('donnee',$d);
        $tmp=getFilesDetails($d,true);
        if(!empty($tmp)) $files[]=$tmp;
      }
    }else{
      foreach($oidsel as $oid1=>$foo) {
        if($this->secure($oid1, ':sro')) {
          $xs = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$oid1);
          $d1 = $xs->display(array('oid'=>$oid1,'tplentry'=>TZR_RETURN_DATA, 'tlink'=>true));
          $donnee['lines_oid'][]=$oid1;
          $donnee['lines_link'][]=$d1['link'];
          $donnee['lines_tlink'][]=$d1['tlink'];
          $tmp=getFilesDetails($d1,true);
          if(!empty($tmp)) $files[]=$tmp;
        }
      }
    }
    $mailDefaults['subject'] = $donnee['lines_link'][0];
    // recherche d'un eventuel module projet pour donner le sujet du mail
    $subjectPrefix=$this->getPrefixFromProject();
    if(empty($subjectPrefix))
      $mailDefaults['subject'] = $GLOBALS['TZR']['societe'].' - '.$mailDefaults['subject'];
    else
      $mailDefaults['subject'] = $subjectPrefix.' - '.$mailDefaults['subject'];
    $donnee['moid']=$this->_moid;
    \Seolan\Core\Shell::toScreen1('mailDefaults', $mailDefaults);
    \Seolan\Core\Shell::toScreen1('donnee',$donnee);
    \Seolan\Core\Shell::toScreen2('files','tab',$files);
    // Liste des modèles
    $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'TEMPLATES');
    $select=$x->select_query(array('cond'=>array('gtype'=>array('=','sendacopy'),'modid'=>array('=',$this->_moid))));
    $x->browse(array('tplentry'=>'tpl','select'=>$select,'selectedfields'=>array('title')));
    // Editeur
    $def=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','sendsignature').' '.$user['ofullnam']->raw.' ['.$user['oalias']->raw.'] '.$user['oemail']->raw;
    $fck=\Seolan\Field\RichText\RichText::getCKEditor($def,'amessage',NULL,600,200,'Basic');
    \Seolan\Core\Shell::toScreen2('messagebox','rich',$fck);
  }

  /// si le module est rattaché à un projet du module projet, on rend le prefix du projet
  function getPrefixFromProject() {
    $projects = \Seolan\Core\Module\Module::singletonFactory(XMODPROJECT_TOID);
    if(!empty($projects)) {
      $projectOid=$projects->getProjectsWithModule($this->_moid);
      if(!empty($projectOid)) {
	$project = $projects->xset->rDisplay($projectOid[0]);
	if(!empty($project['oprefix'])) {
	  $prefix=$project['oprefix']->text;
	  return $prefix;
	}
      }
    }
    return NULL;
  }
  /// redirection vers la fonction avertir
  protected function redirectToSendACopyTo($oid){
    $next = $GLOBALS['XSHELL']->captureNext();
    if (empty($next))
      $next = \Seolan\Core\Shell::get_back_url();
    \Seolan\Core\Logs::debug(__METHOD__." ===> $nextnext");
    \Seolan\Core\Shell::setNext(makeurl($GLOBALS['TZR_SESSION_MANAGER']::complete_self(),
					['function'=>'sendACopyTo',
					 'oid'=>$oid,
					 'tplentry'=>'br',
					 '_skip'=>1,
					 'template'=>'Core/Module.sendacopyto.html',
					 'moid'=>$this->_moid,
					 'nextnext'=>$next]));
  }

  /// envoi des copies aux destinataires soit users soit users des groupes
  function procSendACopyTo($ar, $sender=NULL) {
    $p=new \Seolan\Core\Param($ar,['showdest'=>true]);
    $oid=$p->get('oid');
    $tpl=$p->get('tpl');
    $sendinmail=$p->get('sendinmail');

    $users = \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
    $dest1 = $dest2 = $dest3 = null;
    if ($users->userselectortreeviewmode){
      $selected = [];
      $selopts= ['compulsory'=>0,'multivalued'=>1];
      foreach(['udest'=>&$dest1,'ucc'=>&$dest2,'ubcc'=>&$dest3] as $fn=>&$destn){
	$selopts['field'] = $fn;
	$selopts['label'] = '&nbsp;&nbsp';
	$fo = $users->getTreeviewDefaultField($this->_moid,$selopts);
	$fv = $fo->post_edit($p->get($fn));
	if (is_array($fv->raw)){
	  $destn = [];
	  foreach($fv->raw as $val){
	    $destn[] = $val;
	  }
	}
      }
    } else { // doit pouvoir fusionner complètement avec au dessus
      $selected = [];
      $selopts= ['compulsory'=>0,'multivalued'=>1];
      foreach(['udest'=>&$dest1,'ucc'=>&$dest2,'ubcc'=>&$dest3] as $fn=>&$destn){
	$selopts['field'] = $fn;
	$selopts['label'] = '&nbsp;&nbsp';
	$fo = $users->getTreeviewDefaultField($this->_moid,$selopts);
	$fv = $fo->post_edit($p->get($fn));
	if (is_array($fv->raw)){
	  $destn = [];
	  foreach($fv->raw as $val){
	    $destn[] = $val;
	  }
	}
      }
    }
    // Destinataire (TO)
    if ($dest1 == null)
      $dest1=$p->get('dest');
    // Necessaire si la valeur vide du select des dest est sélectionnée
    if($dest1[0]=='') unset($dest1[0]);
    $aemails=$p->get('dest_aemails');
    $grps=$p->get('dest_groups');
    // Destinataire en copie (CC)
    if ($dest2 == null)
      $dest2=$p->get('cc');
    // Necessaire si la valeur vide du select des dest est sélectionnée
    if($dest2[0]=='') unset($dest2[0]);
    $cc_aemails=$p->get('cc_aemails');
    $cc_grps=$p->get('cc_groups');
    // Destinataire en copie (BCC)
    if ($dest3 == null)
      $dest3=$p->get('bcc');
    // Necessaire si la valeur vide du select des dest est sélectionnée
    if($dest3[0]=='') unset($dest3[0]);
    $cc_aemails=$p->get('cc_aemails');
    $cc_grps=$p->get('cc_groups');

    $bcc_aemails=$p->get('bcc_aemails');
    $bcc_grps=$p->get('bcc_groups');


    $message=stripslashes($p->get('amessage'));
    $subject=stripslashes($p->get('asubject'));

    $oidsel=$p->get('_selected');
    $ack=$p->get('ack');

    $user=NULL;

    $registryValue=["to"=>[], "cc"=>[], "bcc"=>[]];
    $registryValue["dest_aemails"]=$aemails;
    $registryValue["cc_aemails"]=$cc_aemails;
    $registryValue["bcc_aemails"]=$bcc_aemails;

    // prendre en compte le cas où l'utilisateur n'existe pas
    if(\Seolan\Core\Kernel::objectExists(\Seolan\Core\User::get_current_user_uid())) {
      $user=$users->display(array('oid'=>\Seolan\Core\User::get_current_user_uid(),
				  'tplentry'=>TZR_RETURN_DATA));
    }

    $groups = \Seolan\Core\Module\Module::objectFactory(array('tplentry'=>TZR_RETURN_DATA,'toid'=>XMODGROUP_TOID,'_options'=>array('local'=>1)));
    // TO : recherche de tous les utilisateurs dans les groupes sélectionnés
    $usersfromgroups=$groups->users($grps);
    if(empty($dest1)) $dest1=array();
    $dest=array_merge($usersfromgroups, $dest1);
    // Calcul des emails de la liste des destinataires
    if(!empty($dest)) {
      foreach($dest as $i=>$oid1) {
	$r1=$users->display(array('oid'=>$oid1,'tplentry'=>TZR_RETURN_DATA));
	if(is_array($r1)) {
	  $emails[]=array($r1['ofullnam']->raw, $r1['oemail']->raw);
	  $registryValue["to"][]=$oid1;
	}
      }
    }
    // CC : recherche de tous les utilisateurs dans les groupes sélectionnés
    $cc_usersfromgroups=$groups->users($cc_grps);
    if(empty($dest2)) $dest2=array();
    $cc = array_merge($cc_usersfromgroups, $dest2);
    // Calcul des emails de la liste des destinataires en copie
    if(!empty($cc)) {
      foreach($cc as $j=>$oid2) {
	$r1=$users->display(array('oid'=>$oid2,'tplentry'=>TZR_RETURN_DATA));
	$cc_emails[]=array($r1['ofullnam']->raw, $r1['oemail']->raw);
	$registryValue["cc"][]=$oid2;
      }
    }
    // BCC : recherche de tous les utilisateurs dans les groupes sélectionnés
    $bcc_usersfromgroups=$groups->users($bcc_grps);
    if(empty($dest3)) $dest3=array();
    $bcc = array_merge($bcc_usersfromgroups, $dest3);
    // Calcul des emails de la liste des destinataires en copie caché
    if(!empty($bcc)) {
      foreach($bcc as $k=>$oid3) {
	$r1=$users->display(array('oid'=>$oid3,'tplentry'=>TZR_RETURN_DATA));
	$bcc_emails[]=array($r1['ofullnam']->raw, $r1['oemail']->raw);
	$registryValue["bcc"][]=$oid3;
      }
    }

    if(empty($emails)) $emails=array();
    if(empty($cc_emails)) $cc_emails=array();
    if(empty($bcc_emails)) $bcc_emails=array();

    // Depiotage des email donnés à la main
    $emails = array_merge($emails, getEmailFromString($aemails));

    $cc_emails = array_merge($cc_emails, getEmailFromString($cc_aemails));
    $bcc_emails = array_merge($bcc_emails, getEmailFromString($bcc_aemails));
    $oid=$p->get('oid');
    if(count($oidsel)==1) {
      $oidsel=array_keys($oidsel);
      $oid=$oidsel[0];
      $oidsel=array();
    }

    // on mémorise dans le registre
    $registry=\Seolan\Core\Registry::getInstance();
    $registry->set($this->_moid, \Seolan\Core\User::get_current_user_uid(), $oid, "sendacopyto", $registryValue);
    // Recupere les infos des docs à envoyer
    $files=array();
    if(empty($oidsel)) {
      $br=$this->display(array('oid'=>$oid,'tplentry'=>TZR_RETURN_DATA,'selectedfields'=>$this->xset->sendacopytofields,'tlink'=>true));
      $files=getFilesDetails($br,true);
      if(!empty($sendinmail[$oid])) $tpldata['br']=$br;
    } else {
      $lines_oid=[];
      $lines_link=[];
      $lines_tlink=[];
      foreach($oidsel as $oid1=>$foo) {
	$xs = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$oid1);
	$d1 = $xs->display(['oid'=>$oid1,'tplentry'=>TZR_RETURN_DATA,'selectedfields'=>$xs->sendacopytofields,'tlink'=>true]);
 	if(!empty($sendinmail[$oid1])){
 	  $lines_oid[]=$oid1;
 	  $lines_link[]=$d1['link'];
          $lines_tlink[]=$d1['tlink'];
 	}
 	$files=array_merge($files,getFilesDetails($d1,true));
      }
      $tpldata['br']['lines_oid']=$lines_oid;
      $tpldata['br']['lines_link']=$lines_link;
      $tpldata['br']['lines_tlink']=$lines_tlink;
    }
    $tpldata['br']['amessage']=$message;
    $tpldata['br']['moid']=$this->_moid;

    $mailp = $this->getMailer();

    if(!empty($user)) {
      $sender=$this->getSenderWithName($user['oemail']->raw, $user['ofullnam']->raw);
      $mailp->From = $sender[0];
      $mailp->FromName = $sender[1];
      if($sender[0]!=$user['oemail']->raw) {
	$mailp->AddReplyTo($user['oemail']->raw);
      }
    } else {
      $mailp->From=$mailp->FromName=$sender;
    }
    $arecept=$p->get('ar');
    if(!empty($arecept)) $mailp->ConfirmReadingTo=$user['oemail']->raw;
    if(empty($subject)) $subject = $GLOBALS['TZR']['societe'] ." - {$this->getLabel()}";
    $sentemails=array();
    foreach($emails as $i=>$email) {
      if(!empty($email)) {
	if(is_array($email) && !array_key_exists($email[1],$sentemails) && !empty($email[1])) {
	  $mailp->AddAddress($email[1], $email[0]);
	  $sentemails[$email[1]]=$email[0].' '.$email[1];
	} elseif (!is_array($email) && !in_array($email,$sentemails)) {
	  $mailp->AddAddress($email);
	  $sentemails[$email]=$email;
	}
      }
    }
    foreach($cc_emails as $i=>$email) {
      if(!empty($email)) {
	if(is_array($email) && !array_key_exists($email[1],$sentemails) && !empty($email[1])) {
	  $mailp->AddCC($email[1], $email[0]);
	  $sentemails[$email[1]]=$email[0].' '.$email[1];
	} elseif (!is_array($email) && !in_array($email,$sentemails)) {
	  $mailp->AddCC($email);
	  $sentemails[$email]=$email;
	}
      }
    }
    foreach($bcc_emails as $i=>$email) {
      if(!empty($email)) {
	if(is_array($email) && !array_key_exists($email[1],$sentemails)  && !empty($email[1])) {
	  $mailp->AddBCC($email[1], $email[0]);
	  $sentemails[$email[1]]=$email[0].' '.$email[1];
	} elseif (!is_array($email) && !in_array($email,$sentemails)) {
	  $mailp->AddBCC($email);
	  $sentemails[$email]=$email;
	}
      }
    }

    $tpldata['br']['filesHeader'] = 'Fichiers / Files';
    $size=0;
    $selectedfiles=$p->get('filestosend');
    $sendfileas=$p->get('sendfileas');
    if(count($selectedfiles)>0){
      if($sendfileas=='attachment'){
	$filesnotsent=array();
	foreach($files as $f){
	  if(isset($selectedfiles[$f['url']]) || isset($selectedfiles[str_replace('&amp;','&',$f['url'])])){
	    $size+=$f['filesize'];
	    if($size < TZR_SENDACOPY_MAXSIZE) $mailp->AddAttachment($f['filename'],$f['name']);
	    else $filesnotsent[]=$f->name;
	  }
	}
	foreach($_FILES['attachments']['tmp_name'] as $i=>$f){
	  if(empty($_FILES['attachments']['tmp_name'][$i])) continue;
	  $size+=$_FILES['attachments']['size'][$i];
	  if($size < TZR_SENDACOPY_MAXSIZE) $mailp->AddAttachment($_FILES['attachments']['tmp_name'][$i],$_FILES['attachments']['name'][$i]);
	  else $filesnotsent[]=$_FILES['attachments']['name'][$i];
	}
	if(!empty($filesnotsent)) $filesnotsent='<br>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','filesnotsent').implode(', ',$filesnotsent);
	else $filesnotsent='';
      }else{
	$tpldata['br']['files']=array();
	if($sendfileas=='link'){
	  foreach($files as $f){
	    if(isset($selectedfiles[$f['url']]) || isset($selectedfiles[str_replace('&amp;','&',$f['url'])])){
	      $f['url']=$f['url'].'&code='.getDownloaderToken($f['shortfilename'],$f['mime']);
	      $tpldata['br']['files'][]=$f;
	    }
	  }
	}elseif($sendfileas=='linkzip'){
	  $tmpfile=uniqid('sendacopyto');
	  $dir=TZR_TMP_DIR.$tmpfile;
	  mkdir($dir);
	  foreach($files as $f){
	    if(isset($selectedfiles[$f['url']]) || isset($selectedfiles[str_replace('&amp;','&',$f['url'])])){
	      copy($f['filename'],$dir.'/'.$f['name']);
	    }
	  }
	  exec('(cd '.$dir.'; zip -r '.$dir.'.zip .)2>&1 > '.TZR_TMP_DIR.'errorlog');
	  $tpldata['br']['files'][]=array('url'=>TZR_DOWNLOADER.'?tmp=1&filename='.$tmpfile.'.zip&originalname=Files.zip&mime=application/zip','name'=>'Files.zip');
	  \Seolan\Library\Dir::unlink($dir);

	  $duration=round(TZR_PAGE_EXPIRES/(60*60));
          $tpldata['br']['filesHeader'] =
            'Pour télécharger les fichiers, cliquer sur le lien suivant (valable seulement '.$duration.' h)'
            . ' / '
            . 'Download the files by clicking the following link (valid only '.$duration.' h )';
	}
      }
    }
    // Generation du contenu du mail, ie de la fiche
    $r3 = array();
    if(!empty($tpl)){
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
      $d=$x->rDisplay($tpl);
      $xt=new \Seolan\Core\Template($d['odisp']->filename);
      $mailp->Body = \Seolan\Library\Mail::normalizeBoLinks($xt->parse($tpldata,$r3,NULL));
      $mailp->initLog(array('modid'=>$this->_moid,'mtype'=>'sendacopyto'));
      $mailp->Subject = $subject;
      $mailp->Send();
    }else{
      $xt=new \Seolan\Core\Template('Core/Module.sendacopyto-viewcore.html');
      $body = $xt->parse($tpldata,$r3,NULL);
      // on pourrait aussi initier le log ici, avec un contenu partiel cependant
      $mailp->sendPrettyMail($subject,
			     $body,
			     null,
			     null,
			     ['sign'=>0,
			      'subjectPrefix'=>false, // cas des projets
			      'moid'=>$this->_moid,
			      'mtype'=>'sendacopyto']);
    }


    $message=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','sent');
    if(!$showdest) $sentemails=array();
    if($mailp->isError) $message.='<br/>'.$mailp->ErrorInfo.'<br/>';
    if(\Seolan\Core\Shell::hasNext()) setSessionVar('message',$message.$filesnotsent.'<br />'.implode('<br />',$sentemails));
    return \Seolan\Core\Shell::toScreen2('','message',$message.$filesnotsent.'<br />'.implode('<br />',$sentemails));

  }

  // rend la liste des templates avec eventuellement un selecteur sur la table destination
  // si subset est un ensemble de koid (tableau), seuLes les reponses
  // deja presente dans ce tableau seront retournees
  //
  protected function &templatesList($filter='page') {
    if(empty($this->_templates))
      $this->_templates=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'TEMPLATES');

    $cond = $this->_templates->select_query(array('cond'=>array('modid'=>array('=',$this->_moid),
								'gtype'=>array('=',$filter))));
    $r=$this->_templates->browse(array('select'=>$cond,'first'=>'0','last'=>'999','pagesize'=>99,
					'order'=>'title',
					'tplentry'=>TZR_RETURN_DATA,
					'selectedfields'=>array('title','tab')));
    return $r;
  }

  /// Rend l'accessibilite du module avec l'oid donne. Doit repondre true si il y a bien un contenu derrière
  function secureNotEmpty(string $oid, string $func, $user=NULL, $lang=TZR_DEFAULT_LANG) {
    return $this->secure($oid, $func, $user, $lang);
  }

  /// Rend l'accessibilite du module avec l'oid donne
  function secure($oid, string $func, $user=NULL, $lang=TZR_DEFAULT_LANG) {
    if(!empty($oid) && !$this->secOidOk($oid)) return false;
    if(\Seolan\Core\Shell::isRoot()) return true;
    $sec=$this->mySecure($oid,$func,$user,$lang);

    return $sec[0];
  }

  /// Fonction retournant les infos relatives au droits d'un oid ou du module si $oid est vide
  /// Doit retourner des infos au même format que le secure 8, cad un tableau avec :
  ///  - [0]bool : définit si l'accès à l'oid+function est autorisé
  ///  - [1]int : indique le nombre de regles parcourus pour trouver le resultat. Si regle applicative, mettre 1
  ///  - [2]string : droit maximum sur l'oid/module (none, ro, rw...)
  ///  - [3]string : défini à de quel uid/gid la regle hérite. Si regle applicative, laisser vide
  ///  - [4]bool : définit si la règle est de type prioritaire. Si regle applicative, mettre true
  function mySecure($oid, string $func, $user=NULL, $lang=TZR_DEFAULT_LANG, $checkparent=true){
    if(\Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID)) {
      $umod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
      if($umod->isPendingCase($oid)) {
      }
    }
    return \Seolan\Core\User::secure8($func,$this,$oid,$user,$lang,$checkparent);
  }

  /// Calcul des droits sur l'object courant
  public function secObjectAccess(string $f, string $lang, string $oid, string $tplentry='imod') {
    $oidacl=$GLOBALS['XUSER']->getObjectAccess($this, $lang, $oid);
    $oidacl=array_flip($oidacl[0]);
    \Seolan\Core\Shell::toScreen2($tplentry,'sec',$oidacl);
  }

  /// Définit si la sécurité sur les objets est activé
  public function objectSecurityEnabled(){
    return !empty($this->object_sec);
  }

  /// Retourne la liste des oids du module avec des regles de sécurité spécifiques.
  /// Si retourne true, alors on considerera que tous les oids on des droits spécifiques.
  public function &getObjectsWithSec($uid=NULL){
    if($this->object_sec){
      if(!$uid) $uid=\Seolan\Core\User::get_current_user_uid();
      $rules=\Seolan\Core\User::getObjectsWithSec($this,$uid);
    }else{
      $rules=array();
    }
    return $rules;
  }

  /// Définit si le cache des droits doit être activé ou pas.
  /// Cela ne vaut le coup que pour les modules avec les droits sur les objets activé et utilisant une arborescence dans les droits, comme la base documentaire ou le gestionnaire de rubrique
  public function rightCacheEnabled(){
    return false;
  }

  /// Retourne le parent direct de chaque oid passé en paramètre
  public function getParentsOids($oids){
    return array('');
  }

  /// Vérifie si un oid est visible par le module (presence de filtre...)
  protected function secOidOk($oid) {
    return true;
  }

  /// Appliquer les droits sur les objets d'une liste resultat issu d'un xset browse/query
  protected function applyObjectsSec(&$r){
    // calcul des droits sur les objets retournés
    $lang_data = \Seolan\Core\Shell::getLangData();
    $r['objects_sec']=$GLOBALS['XUSER']->getObjectsAccess($this, $lang_data, $r['lines_oid']);
    // calcul des droits sur le module
    list($access,$l) = $GLOBALS['XUSER']->getUserAccess(get_class($this), $this->_moid, $lang_data);
    $todel=array();
    $rolist=static::getRoList();
    foreach($r['objects_sec'] as $i => $rights) {
      $intersect=array_intersect($rolist,array_flip($rights));
      if(empty($intersect)) {
	array_push($todel,$i);
      }
    }

    // on vire les objets retournés qui sont interdits en lecture
    if(!empty($todel)) {
      $r2=$r;
      foreach($r2 as $lib => &$tab) {
	if(preg_match('/^lines_(.*)/',$lib) || ($lib=='objects_sec')) {
	  $save=$r[$lib];
	  unset($r[$lib]);
	  $start=0;
	  foreach($todel as $foo => $i) {
	    unset($save[$i]);
	  }
	  foreach($save as $i => $data) {
	    $r[$lib][$start++]=$save[$i];
	  }
	}
      }
    }
  }
  /// Vérifie si l'utilisateur courant peut attribuer un droit spécifique à un objet
  public function isAuthorizedToSetAccess($lang,$oid,$level){
    return \Seolan\Core\User::isAuthorizedToSetAccess(get_class($this),$this->_moid,$lang,$oid,$level);
  }

  // application d'une fonction a tous les objets d'un module
  //
  function apply($f) {}

  /// fonction appellee en cas d'acces avec un niveau de droits insuffisant
  function secFailHandler($f, $oid=NULL, $template=NULL, $next=NULL, $message=NULL) {
    if($message===NULL) $message=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Security','permission_denied');
    $loginurl=$GLOBALS['XSHELL']->getLoginUrl();
    \Seolan\Core\Logs::critical("\Seolan\Core\Module\Module::secFailHandler","access denied $f |".$this->_moid."|$oid| user ".\Seolan\Core\User::get_current_user_uid());
    header('Location: '.$loginurl.'&message='.urlencode($message).'&next='.urlencode($next));
    die();
  }
  /// envoi d'un mail (type BO) à l'utilisateur, ou à $mail si renseigné
  public function sendAdminMail2User($subject,$message,$email=NULL,$from=NULL,$archive=true,$filename=NULL,$filetitle=NULL,$stringattachment=NULL,$mime=NULL,$params=[]){
    $params['forcebo'] = true;
    return $this->sendMail2User($subject,$message,$email,$from,$archive,$filename,$filetitle,$stringattachment,$mime,$params);
  }
  /// envoi d'un mail à l'utilisateur, ou à $mail si renseigné
  public function sendMail2User($subject,$message,$email=NULL,$from=NULL,$archive=true,$filename=NULL,$filetitle=NULL,$stringattachment=NULL,$mime=NULL,$params=[]){
    $params['moid']=$this->_moid;
    $GLOBALS['XUSER']->sendMail2User($subject,$message,$email,$from,$archive,$filename,$filetitle,$stringattachment,$mime,$params);
  }
  /**
   * clear user selection
   * put first non empty batch into selection
   * delete batch from file and update batchfile
   */
  public function addBatchToSelection($ar=null){
    $emptyMess = 'Pas de lot en attente';
    $addedMess = 'Lot ajouté à la sélection';
    $batches = $this->loadBatches();
    if (!$batches || count($batches['list'])==0){
      \Seolan\Core\Shell::setNextData('message',$emptyMess."!");
      \Seolan\Core\Shell::setNext('back');
      return;
    }
    copy($this->batchesFile, $this->batchesFile.'.bak');
    $modu=\Seolan\Core\Module\Module::objectFactory(self::getMoid(XMODUSER2_TOID));
    $modu->clearToSelection($this->_moid);
    $this->_clearSession('user_selection_selected');
    $toadd = [];
    $l = 0;
    // on ignore les items qui ne sont pas sélectionnés
    // et on change pas l'ordre initial des lots quand on les garde
    while(count($toadd)==0 && isset($batches['list'][$l])){
      $items = &$batches['list'][$l];
      if (empty($items))
	break;
      foreach($items as $i=>$oid){
	$action = $this->batchItemAvailableForSelection($oid);
	switch($action){
	  case 'add':
	    $toadd[$oid]=true;
	    unset($items[$i]);
	    break;
	  case 'delete':
	    unset($items[$i]);
	    break;
	  case 'keep';
	    break;
	};
      }
      if (count($items) > 0){
	// reorg de $items
	$items = array_values($items);
      } else {
	unset($batches['list'][$l]);
      }
      // next
      $l++;
    }
    unset($items); // !!!
    // ajout des données à la sélection
    if (count($toadd)>0){
      $modu=\Seolan\Core\Module\Module::objectFactory(self::getMoid(XMODUSER2_TOID));
      $this->_setSession('user_selection_selected', $toadd);
      $modu->addToSelection($this->_moid,$toadd);
    } else {
      \Seolan\Core\Shell::setNextData('message',$emptyMess."");
    }
    // reorg des clés de la liste restante
    $batches['list'] = array_values($batches['list']);
    // à voir on pourrait garder, avec la liste vide
    if (count($batches)>0){
      file_put_contents($this->batchesFile, json_encode($batches, JSON_PRETTY_PRINT));
    } else {
      unlink($this->batchesFile);
    }
  }
  /// Indique si un oid d'un lot peut être ajouté à la sélection
  protected function batchItemAvailableForSelection($oid, $lang=TZR_DEFAULT_LANG){
    if (\Seolan\Core\Kernel::objectExists($oid, $lang)){
      if ($this->secure($oid, 'addToUserSelection'))
	return 'add';
      else
	return 'keep';
    } else {
      return 'delete';
    }
  }
  /// Ajout du menu des lots / paniers
  protected function setBatchActions(&$my, &$alfunction=null){
    if (!($batches = $this->loadBatches()))
      return;
    if (count($batches['list']) == 0)
      return;

    $lang = \Seolan\Core\Shell::getLangData();
    if (isset($batches['label'][$lang]))
      $label = $batches['label'][$lang];
    elseif (isset($batches['label'][TZR_DEFAULT_LANG]))
    $label = $batches['label'][$lang];
    else
      $label = 'Lots à traiter';
    $url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true);
    $url .= "&moid={$this->_moid}&function=addBatchToSelection&template=Core.layout/top/cart.html";
    $js = "TZR.jQueryLoad({url:'$url',target:'#cvx-panier',cb:function(){TZR.SELECTION.Modal.show('{$this->_moid}')}});";
    $o1=new \Seolan\Core\Module\Action($this,'batchesToSelection',$label,"javascript:$js",'edit');
    $o1->containable=false;
    $o1->menuable=true;
    if (isset($batches['shortkey'])){
      $o1->shortKey = $this->$batches['shortkey'];
    }
    $my['batchesToSelection']=$o1;
  }
  /// read batches file
  protected function loadBatches(){
    $file = TZR_TMP_DIR."batchesfile_{$this->_moid}.json";
    if (file_exists($file) && $this->secure('','addBatchToSelection')){
      $batches = json_decode(file_get_contents($file), true);
      if (!$batches){
	\Seolan\Core\Logs::critical(__METHOD__, "decode $file ; ".json_last_error_msg());
	return null;
      }
      return $batches;
    }
  }
  /// Ajoute un/des oid à la selection utilisateur
  function addToUserSelection($ar=NULL){
    if (method_exists($this, 'isThereAQueryActive')){
      $ar['_filter']=$this->getFilter(true,$ar);
      $q = $this->getContextQuery($ar, true);
      $data = getDB()->select(preg_replace('/^select .* from (.*)/i', 'select KOID, 1 from \1', $q))->fetchAll(\PDO::FETCH_KEY_PAIR);
    } else {
      $data = array_fill_keys(\Seolan\Core\Kernel::getSelectedOids($ar), 1);
    }
    if (count($data)>0 && !empty(array_keys($data)[0])){
      $modu=\Seolan\Core\Module\Module::objectFactory(self::getMoid(XMODUSER2_TOID));
      $modu->addToSelection($this->_moid,$data);
    }
  }
  /// Supprime un/des oid de la selection utilisateur
  function delToUserSelection($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oids=$p->get('_selected');
    $selectedok=$p->get('_selectedok');
    if($selectedok && is_array($oids)) $oids=array_keys($oids);
    else $oids=array($p->get('oid'));
    $data=array();
    foreach($oids as $oid){
      $data[$oid]=1;
    }
    $modu=\Seolan\Core\Module\Module::objectFactory(self::getMoid(XMODUSER2_TOID));
    $modu->delToSelection($this->_moid,$data);
  }

  /// Liste des fonctions utilisable sur la selection du module
  function userSelectionActions(){
    return array();
  }

  /// Parcours la selection utilisateur pour ce module
  function browseUserSelection($ar = NULL){
    $p = new \Seolan\Core\Param ($ar, NULL);
    $tplentry = $p->get ('tplentry');
    $result = array (
    'oids' => array (),
    'lines_oid' => array ()
    );
    // Utilistion d'un pointeur afin que la modification de data modifie la session elle meme
    $data=$this->getSelectionFromSession();
    $selected  = $this->_getSession('user_selection_selected');
    foreach($data as $oid=>&$foo){
      $result['lines_selected'][] = !empty($selected) && isset($selected[$oid]);
      if(method_exists($this,'_browseUserSelection')){
	$r=$this->_browseUserSelection($oid,$foo);
	if(is_array($r)){
	  $result['lines'][]=$r;
	  $result['lines_oid'][]=$oid;
	}else{
	  unset($data[$oid]);
          continue;
        }
      } else {
        $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8 ('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=' . $oid);
        if (! $xds->objectExists ($oid)) {
          unset ($data [$oid]);
          continue;
        }
        if (! $this->object_sec || $this->secure ($oid, 'display')) {
          $result ['lines'] [] = $xds->rDisplay ($oid, array (), true);
          $result ['lines_oid'] [] = $oid;
        } else {
          unset ($data [$oid]);
          continue;
        }
      }
    }
    $this->applyObjectsSecUserSelection ($result);
    $this->browseActionsUserSelection ($result);
    $result ['_modulename'] = $this->getLabel();
    $result ['_count'] = count ($result ['lines_oid']);
    $result ['_template'] = 'Core/Module.browseSelection.html';
    $result ['_moid'] = $this->_moid;
    return \Seolan\Core\Shell::toScreen1 ($tplentry, $result);
  }

  /// Liste des actions sur les fiches de la selection
  function browseActionsUserSelection(&$r){
    if(!is_array($r['lines_oid'])) return;
    foreach($r['lines_oid'] as $i =>$oid) {
      $oidlvl=array_keys($r['objects_sec'][$i]);
      $this->browseActionsUserSelectionForLine($r,$i,$oid,$oidlvl);
    }
  }

  /// Ajoute les actions de la selection à une ligne donnée
  function browseActionsUserSelectionForLine(&$r,&$i,&$oid,&$oidlvl){
    $this->browseActionView($r,$i,$oid,$oidlvl,true);
    $this->browseActionEdit($r,$i,$oid,$oidlvl,true);
  }

  /// Ajoute les droits sur les objets de la selection
  function applyObjectsSecUserSelection(&$r){
    $lang_data=\Seolan\Core\Shell::getLangData();
    $r['objects_sec']=$GLOBALS['XUSER']->getObjectsAccess($this,$lang_data,$r['lines_oid']);
  }
  /// Retourne la selection du module
  function getSelectionFromSession(){
    $sel=getSessionVar('user_selection');
    return !empty($sel[$this->_moid])?$sel[$this->_moid]:false;
  }
  /* Fin gestion de la selection */

  /* Gestion des actions */
  /// Mise en forme du lien d'une action
  protected function formatBrowseAction($oid, $txt, $ico, $url, $attr, $urlparms=''){
    $furl=str_replace('<oid>',$oid,$url).$urlparms;
    $fa=str_replace('<oid>',$oid,$attr);
    return array('link'=>'<a '.$fa.' href="'.$furl.'" title="'.$txt.'">'.$ico.'</a>',
		 'url'=>$furl,
		 'label'=>$ico);
  }
  /// Mise en forme du lien d'une action
  protected function formatBrowseDeleteAction($oid, $txt, $ico, $url, $attr, $urlparms=''){
    $furl=str_replace('<oid>',$oid,$url).$urlparms;
    $fa=str_replace('<oid>',$oid,$attr);
    return array('link'=>'<a href="#" '.$fa.' data-action="TZR.Table.deleteItem" data-args="'.$this->_moid.','.$furl.'" title="'.$txt.'" data-toggle="modal" data-target="#cvx-confirm-delete">'.$ico.'</a>',
		 'url'=>$furl,
		 'label'=>$ico);
  }
  /// Ajoute une action de browse à une ligne donnée
  /// $type :
  /// peut être le nom d'un type géré dans le module ou un objet
  ///
  function browseActionForLine($type,&$r,&$i,&$oid,&$oidlvl,&$usersel=false,$cache=true){
    if (!isset($this->_actionsCacheUrl)){
      $this->_actionsCacheUrl = array();
      $this->_actionsCacheLvl = array();
      $this->_actionsCacheIco = array();
      $this->_actionsCacheTxt = array();
      $this->_actionsCacheAttr = array();
    }
    $helper = null;
    if (is_a($type, \Seolan\Core\Module\BrowseActionHelper::class)){
      $helper = $type;
      $type = $helper->getType();
    }
    if(!$cache || !isset($this->_actionsCacheUrl[$type])){
      $linecontext = array('browse'=>$r,
			   'index'=>$i,
			   'oid'=>$oid,
			   'oidlvl'=>$oidlvl,
			   'usersel'=>$usersel
			   );
      if (null != $helper){
	$this->_actionsCacheTxt[$type]=$helper->browseActionText($linecontext);
	$this->_actionsCacheIco[$type]=$helper->browseActionIco($linecontext);
	$this->_actionsCacheLvl[$type]=$helper->browseActionLvl($linecontext);
	$this->_actionsCacheUrl[$type]=$helper->browseActionUrl($usersel, $linecontext);
	$this->_actionsCacheAttr[$type]=$helper->browseActionHtmlAttributes($this->_actionsCacheUrl[$type],$this->_actionsCacheTxt[$type],$this->_actionsCacheIco[$type], $linecontext);
      } else {
	$this->_actionsCacheTxt[$type]=$this->{'browseAction'.$type.'Text'}($linecontext);
	$this->_actionsCacheIco[$type]=$this->{'browseAction'.$type.'Ico'}($linecontext);
	$this->_actionsCacheLvl[$type]=$this->{'browseAction'.$type.'Lvl'}($linecontext);
	$this->_actionsCacheUrl[$type]=$this->{'browseAction'.$type.'Url'}($usersel, $linecontext);
	$this->_actionsCacheAttr[$type]=$this->{'browseAction'.$type.'HtmlAttributes'}($this->_actionsCacheUrl[$type],$this->_actionsCacheTxt[$type],$this->_actionsCacheIco[$type], $linecontext);
      }
    }
    // Verifie les droits
    $inter=array_intersect($this->_actionsCacheLvl[$type],$oidlvl);
    if(empty($inter))     return;

    // Ajout de l'action
    if ($type == 'del') {
      $action = $this->formatBrowseDeleteAction($oid, $this->_actionsCacheTxt[$type], $this->_actionsCacheIco[$type], $this->_actionsCacheUrl[$type], $this->_actionsCacheAttr[$type], @$r['urlparms']);
    } else {
      $action = $this->formatBrowseAction($oid, $this->_actionsCacheTxt[$type], $this->_actionsCacheIco[$type], $this->_actionsCacheUrl[$type], $this->_actionsCacheAttr[$type], @$r['urlparms']);
    }
    $r['actions'][$i][$type]=$action['link'];
    $r['actions_url'][$i][$type]=$action['url'];
    $r['actions_label'][$i][$type]=$action['label'];
    $r['actions_text'][$i][$type]=$this->_actionsCacheTxt[$type];
  }
  /// Retourne les infos de l'action voir du browse
  function browseActionView(&$r,&$i,&$oid,&$oidlvl,$usersel=false){
    $this->browseActionForLine('view',$r,$i,$oid,$oidlvl,$usersel);
  }
  function browseActionViewText($linecontext=null){
    return \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','view');
  }
  function browseActionViewIco($linecontext=null){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','view');
  }
  function browseActionViewLvl($linecontext=null){
    return $this->secGroups('display');
  }
  function browseActionViewHtmlAttributes(&$url,&$text,&$icon, $linecontext){
    return 'class="cv8-ajaxlink cv8-dispaction"';
  }
  function browseActionViewUrl($usersel, $linecontext=null){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().($usersel?'&_bdxnewstack=1':'').'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=display&template=Module/Table.view.html';
  }
  /// Retourne les infos de l'action editer du browse
  function browseActionEdit(&$r,&$i,&$oid,&$oidlvl,$usersel=false){
    $this->browseActionForLine('edit',$r,$i,$oid,$oidlvl,$usersel);
  }

  function browseActionEditText($linecontext=null){
    return \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','edit');
  }
  function browseActionEditIco($linecontext=null){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit');
  }
  function browseActionEditLvl($linecontext=null){
    return $this->secGroups('edit');
  }
  function browseActionEditHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
    return 'class="cv8-ajaxlink cv8-editaction"';
  }

  function browseActionEditUrl($usersel, $linecontext=null){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().($usersel?'&_bdxnewstack=1':'').'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=edit&template=Module/Table.edit.html';
  }
  /* Fin gestion des actions */


  /// Charge la securité sur les champs du module
  function loadFieldsSec(&$place){
    $grps=$GLOBALS['XUSER']->groups();
    $uf=[];
    $rs=getDB()->fetchAll('select SUBSTR(AKOID,8) as AKOID,AFUNCTION,AGRP from ACL4 where ALANG=? and AMOID=? and AGRP in ('.implode(',',array_fill(0, count($grps), '?')).') and AKOID like "_field-%"',
			  array_merge([\Seolan\Core\Shell::getLangData(),
				       $this->_moid],
				      $grps));
    foreach($rs as $ors) {
      $field=$ors['AKOID'];
      $sec=$ors['AFUNCTION'];
      if(substr($ors['AGRP'],0,5)=='USERS'){
	$place[$field]=$sec;
	$uf[]=$field;
      }elseif(!in_array($field,$uf)){
	if(empty($place[$field])) $place[$field]=$sec;
	elseif($place[$field]=='rw') continue;
	elseif($place[$field]=='ro' && $sec=='rw') $place[$field]=$sec;
	elseif($place[$field]=='none') $place[$field]=$sec;
      }
    }
    unset($rs);
  }
  /// Charge la securité sur les champs du module
  /// $secs=array(array('field'=>'Fxxx','sec'=>'ro','group'=>'GRP:xxx'),array(...));
  function loadFieldsSec1($secs){
    $grps=$GLOBALS['XUSER']->groups();
    $uf=array();
    foreach($secs as $sec1) {
      if($GLOBALS['XUSER']->inGroups($sec1['group'],false) || \Seolan\Core\User::get_current_user_uid()==$sec1['group']) {
	$field=$sec1['field'];
	$sec=$sec1['sec'];
	if(substr($sec1['group'],0,5)=='USERS'){
	  $this->fieldssec[$field]=$sec;
	  $uf[]=$field;
	}elseif(!in_array($field,$uf)){
	  if(empty($this->fieldssec[$field])) $this->fieldssec[$field]=$sec;
	  elseif($this->fieldssec[$field]=='rw') continue;
	  elseif($this->fieldssec[$field]=='ro' && $sec=='rw') $this->fieldssec[$field]=$sec;
	  elseif($this->fieldssec[$field]=='none') $this->fieldssec[$field]=$sec;
	}
      }
    }
  }

  /// rend le tableau des champs pour lesquels une regle de securité au moins existe
  function anyFieldsSec(&$place){
    $grps=$GLOBALS['XUSER']->groups();
    $uf=array();
    $rs=getDB()->fetchCol('select SUBSTR(AKOID,8) as AKOID from ACL4 where AMOID=? and '.
			'AKOID like "_field-%"',array($this->_moid));
    foreach($rs as $ors) {
      $f=$ors;
      $place[$f]=1;
    }
    unset($rs);
  }

  /// Liste des préferences du module (tableau de champ) pour l'edition via une interface graphique
  function getParamPrefs(){
    return [];
  }

  /// Prepare l'edition des preferences pour une interface graphique
  function editPrefs($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $uid=$p->get('uid','local');
    if(empty($uid)) $uid=\Seolan\Core\User::get_current_user_uid();
    $ors=getDB()->fetchRow('select KOID,specs from OPTS where modid=? and user=? and dtype="pref" limit 1',array($this->_moid,$uid));
    $prefs=\Seolan\Library\Opts::decodeSpecs($ors['specs']);
    $ret=array('fields' => [], 'eraseButton' => false, 'saveButton' => true);
    $params=$this->getParamPrefs();

    if ($prefs !== false && (empty($params) || count($params) === 0)) {
      $ret['eraseButton'] = true;
      $ret['saveButton'] = false;
      foreach ($prefs as $pref => $value) {
	// à voir ? ce sont des options préférences spécifiques aux ensembles de fiches, pas des préférences communes à tout les modules
        if (!in_array($pref, ['selectedfields', 'selectedqqfields', 'order', 'quick_query_submodsearch', 'quick_query_open', 'pagesize'], true)) {
          continue;
        }

        $o = (object)[
          'field' => $pref,
          'table' => null,
          'fielddef' => (object)[
            'label' => null,
            'readonly' => true,
          ],
          'readonly' => true,
          'html' => null,
        ];

        switch ($pref) {
	case 'selectedfields':
	case 'selectedqqfields':
	  if ($pref=='selectedfields')
	    $o->fielddef->label = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module', 'pref_selectedfields');
	  else
	    $o->fielddef->label= \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','pref_selectedqqfields');
  	  $o->html = '';
	  foreach ($value as $field) {
	    $o->html .= $this->xset->desc[$field]->label.' | ';
	  }
  	  if (!empty($o->html)) {
	    $o->html = substr($o->html, 0, -3);
	  }
  	  break;

          case 'order':
            $o->fielddef->label = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','pref_order');
            $order_explode = explode(',', $value);
            $o->html = '';
	    // $asc et $desc ne sont pas utilisés
            $asc = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','pref_order_asc');
            $desc = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','pref_order_desc');
            foreach ($order_explode as $order) {
              list($field, $way) = explode(' ', $order);
              $field = preg_replace('/[^\.]+\./', '', $field);
              $way = strtolower($way);

              if (strtoupper($field) === 'KOID') {
                continue;
              }

              $o->html .= $this->xset->desc[$field]->label.' '.$$way.', ';
            }

            if (!empty($o->html)) {
              $o->html = substr($o->html, 0, -2);
            }
            break;

          case 'quick_query_submodsearch':
          case 'quick_query_open':
	    if ($pref=='quick_query_submodsearch')
	      $o->fielddef->label = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module', 'pref_quick_query_submodsearch');
	    else
	      $o->fielddef->label = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','pref_quick_query_open');

            if ((int)$value === 1) {
              $o->html = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','yes');
            } else {
              $o->html = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','no');
            }
            break;

          case 'pagesize':
            $o->fielddef->label = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'pagesize');
            $o->html = $value;

            break;
        }

        $ret['fields']['o'.$pref]=$ret['fields']['fields_object'][]=$o;
      }
    } else {
      $desc=&$params['desc'];
      $options=&$params['options'];
      foreach($desc as $fn=>&$f){
        $o=$f->edit($prefs[$fn],$options[$fn]);
        $ret['fields']['o'.$fn]=$ret['fields']['fields_object'][]=$o;
      }
    }
    return $ret;
  }

  /// Enregistre les preferences du module suite à une edition via une interface graphique
  function procEditPrefs($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $params=$this->getParamPrefs();
    $desc=&$params['desc'];
    $prefs=array();
    $options=array('_track'=>false);
    foreach($desc as $fn=>&$f){
      $value=$p->get($fn);
      $value_hid=$p->get($fn.'_HID');
      $options[$fn.'_HID']=$value_hid;
      $o=$desc[$fn]->post_edit($value,$options);
      if($f->multivalued && $f->get_fgender()=='Oid' && is_array($o->raw)){
	$o->raw=implode('||',$o->raw);
      }
      $prefs[$fn]=$o->raw;
    }
    $uid=$p->get('uid','local');
    return $this->savePrefs($uid,$prefs);
  }

  public function procErasePrefs($ar = null) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $uid=$p->get('uid','local');
    if(empty($uid))
      $uid=\Seolan\Core\User::get_current_user_uid();
    $koid = getDB()->fetchOne('select KOID from OPTS where modid=? and user=? and dtype="pref"', [$this->_moid,$uid]);
    if ($koid){
      \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('OPTS')->del(['_options'=>['local'=>true], 'oid'=>$koid]);
    }

  }

  /// Enregistre des preferences pour un user donné
  function savePrefs($uid,$prefs,$reset=false){
    clearSessionVar('upref');
    if(empty($uid)) $uid=\Seolan\Core\User::get_current_user_uid();
    $x1=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=OPTS');
    $rs=getDB()->select('select KOID,specs from OPTS where modid=? and user=? and dtype="pref" limit 1', array($this->_moid,$uid));
    $a1['_options']['local']=true;
    if($rs->rowCount()>0){
      $ors=$rs->fetch();
      if(empty($ors['specs']) || $reset) $specs=[];
      else $specs=\Seolan\Library\Opts::decodeSpecs($ors['specs']);
      $a1['specs']=\Seolan\Library\Opts::encodeSpecs(array_merge($specs,$prefs));
      $a1['oid']=$ors['KOID'];
      $x1->procEdit($a1);
      return $ors['KOID'];
    }else{
      $a1['specs']=\Seolan\Library\Opts::encodeSpecs($prefs);
      $a1['modid']=$this->_moid;
      $a1['user']=$uid;
      $a1['dtype']='pref';
      $ret=$x1->procInput($a1);
      return $ret['oid'];
    }
  }

  /// Modifie une preference. La propriété n'est modifiée que si elle est différente de celle déjà stockée
  public function setPref($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $prop=$p->get('prop');
    $propv=$p->get('propv');
    $uid=$p->get('uid');
    $propoldv=$this->getPref($prop);
    if($propoldv!=$propv) {
      return $this->savePrefs($uid,array($prop=>$propv));
    } else {
      return NULL;
    }
  }

  /// Modifie une preference. La propriété n'est modifiée que si elle est différente de celle déjà stockée
  public function setPref1($prop, $propv, $uid=NULL) {
    $propoldv=$this->getPref($prop);
    if($propoldv!=$propv) {
      return $this->savePrefs($uid,array($prop=>$propv));
    } else {
      return NULL;
    }
  }

  /// Recupere les preferences du module pour l'utilisateur en cours
  public function getPrefs($prop=NULL) {
    // compatibilité avec la signature précedentre de la fonction
    if(is_array($prop)) @$prop=$prop['prop'];
    if(empty($this->prefs)) {
      $this->prefs=\Seolan\Library\Opts::getOpt(\Seolan\Core\User::get_current_user_uid(), $this->_moid, 'pref');
    }
    // si la propriété est à 'null' on rend tout
    if($prop===NULL) return $this->prefs;
    else return $this->prefs[$prop];
  }

  /// Recupere les préferences du module pour l'utilisateur en cours pour une propriété donnée
  public function getPref($prop=NULL) {
    // compatibilité avec la signature précedentre de la fonction
    if(empty($this->prefs)) {
      $this->prefs=\Seolan\Library\Opts::getOpt(\Seolan\Core\User::get_current_user_uid(), $this->_moid, 'pref');
    }
    return $this->prefs[$prop]??null;
  }

  /// Recupere des infos sur le module
  function getInfos($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>TZR_RETURN_DATA));
    $tplentry=$p->get('tplentry');
    $grps=$p->get('grps');
    if(!empty($grps)){
      $users=\Seolan\Module\Group\Group::users($grps);
      $ucond='SGRP IN ("'.implode('","',$users).'")';
    }else $ucond='1';
    $ret['modulename']=$this->getLabel();
    // Nombre de consultation du module
    if(\Seolan\Core\System::tableExists('_STATS')){
      $ret['infos']['stats']=(object)array('label'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','stats'),
					   'html'=>getDB()->fetchOne('select ifnull(sum(cnt),0) from _STATS where SMOID="'.$this->_moid.'" AND '.$ucond));
    }
    // Module statistique
    if($this->interactive){
      $mod=\Seolan\Core\Module\Module::objectFactory(array('toid'=>XMODSTATS_TOID));
      if($mod && $mod->secure('','index')) $ret['statsmodule']=$mod->_moid;
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// Liste les langues demandées et autorisées pour une fonction
  function getAuthorizedLangs($langs,$oid,$func){
    if($langs=='all'){
      $langs=array();
      foreach($GLOBALS['TZR_LANGUAGES'] as $l=>&$v) $langs[]=$l;
    }
    if(is_array($langs)){
      foreach($langs as $i=>$l){
	if(!$this->secure($oid,$func,$user=NULL,$l)) unset($langs[$i]);
      }
      return $langs;
    }
    return NULL;
  }

  /// Renvoi le contenue d'un template dans le contexte du module
  function getTemplateContent($tpl,$tpldata,$rawdata=null){
    $xt=new \Seolan\Core\Template($tpl);
    $labels=$GLOBALS['XSHELL']->labels->get_labels(array('selectors'=>array('global'),'local'=>true));
    $xt->set_glob(array('labels'=>&$labels));
    $tpldata['']['moid']=$this->_moid;
    $tpldata['imod']=&\Seolan\Core\Shell::from_screen('imod');
    return $xt->parse($tpldata,$rawdata,NULL);
  }

  /// Filtre sql utilisé par les \Seolan\Core\Field\Field pour filtrer les valeurs utilisées
  function getUsedValuesFilter(){
    return '';
  }

  /// Ensemble d'actions à effectuer avant qu'une modification de donnée ait lieu (appelé par la source)
  public function preUpdateTasks(&$ar) {
  }

  /// Ensemble d'actions à effectuer après qu'une modification de donnée ait eu lieu (appelé par la source)
  public function updateTasks($ar,$oid, $event=null, $inputs=null) {
    $this->cleanFunctionSections($oid);
    $this->delToUserSelection(); // via _selected
  }
  /**
   * retourne les url et titres des pages de doc du module
   * @param string $function la méthode en cours d'execution
   * @param string $page un filter sur les pages
   * @return array liste de tuples titre, url (avec ancre eventuelles)
   */
  function getUserManualPages($function=null, $page=null){
    $md = self::$_modules[$this->toid];
    $name = \Seolan\Core\Labels::getTextSysLabelFromClass($md['CLASSNAME'],'modulename')??$md['MODULE'];
    $moduleAlias = strtolower(str_replace(['\\Seolan\\','\\'], ['', '_'], $md['CLASSNAME']));
    $links = [[$name,TZR_USERGUIDE_URL."u_{$moduleAlias}.html"]];
    if (\Seolan\Core\Shell::isRoot()){
      $links[] = ['Documentation administrateur', TZR_USERGUIDE_URL."{$moduleAlias}.html"];
    }


    return $links;
  }

  /**
   * ajout des items d'aide du module / fonction en cours
   */
  protected function setHelpModuleActions(&$my, $function){
    // Infos sur le module
    if($this->secure('','showUserManual')){
      foreach($this->getUserManualPages($function) as list($htitle, $hurl)){
	$key = rewriteToAscii($htitle);
	$oh=new \Seolan\Core\Module\Action($this, $key, $htitle, $hurl, 'helpitems');
	$oh->menuable = true;
	$oh->target = 'usermanual';
	$my['help'.$key]=$oh;
      }
    }
  }
  /**
   * recherche des pages des gestionnaires de rubriques dans
   * lesquelles il pourrait y avoir des sections fonction avec des
   * données en provenance de ce module. On marque les pages
   * concernées pour recalcul.
   */
  function cleanFunctionSections($oidupdated=NULL) {
    $mymoid=$this->_moid;
    $tableupdated=\Seolan\Core\Kernel::getTable($oidupdated);
    if(!\Seolan\Core\System::tableExists('_PCACHE') || !($cache=\Seolan\Core\Module\Module::singletonFactory(XMODCACHE_TOID))) return;
    if(is_a($this, '\Seolan\Module\Cache\Cache')) return;
    if(in_array($tableupdated, array('_PCACHE','LOGS'))) return;

    $code='\Seolan\Core\Module\Module::cleanFunctionSectionsBatch("'.$tableupdated.'");';

    $batch=new \Seolan\Core\Batch();
    $batch->addAction('sections fonction',$code);
  }
  static function cleanFunctionSectionsBatch($tableupdated) {
    // On ne peux continuer que si une table à été donnée.
    if (!$tableupdated) return;
    if(!\Seolan\Core\System::tableExists('_PCACHE') || !($cache=\Seolan\Core\Module\Module::singletonFactory(XMODCACHE_TOID))) return;

    // recherche de tous les modules qui peuvent contenir cette donnée
    $modules=\Seolan\Core\Module\Module::modulesUsingTable($tableupdated);
    //Normalement, ça ne devrait pas arriver mais si ça arrive ça évitera une erreur SQL
    if (!count($modules)) return;

    $inclause='('.implode(',',array_keys($modules)).')';
    if(\Seolan\Core\System::tableExists('_PCACHE') && ($cache=\Seolan\Core\Module\Module::singletonFactory(XMODCACHE_TOID))) {
      // recherche de tous les gestionnaires de rubriques
      foreach(\Seolan\Core\Module\Module::$_mcache as $moid=>$ors) {
	if($ors['TOID']==XMODINFOTREE_TOID){
	  $mod=\Seolan\Core\Module\Module::objectFactory($moid);
	  // recherche de toutes les sections fonctions qui utilisent
	  // ce module
	  if ($mod->table == 'CS8' || empty($mod->dyntable) || empty($mod->tname)){
	    continue;
	  }

	  $aliases=getDB()->fetchCol('SELECT distinct '.$mod->table.'.alias from '.$mod->table.', '.$mod->dyntable.', '.$mod->tname.
				   ' WHERE module in '.$inclause.' AND '.$mod->tname.'.KOIDDST='.$mod->dyntable.'.KOID AND '.$mod->table.'.KOID='.$mod->tname.'.KOIDSRC');
	  foreach($aliases as $alias){
	    if(!empty($alias)) {
	      $cache->clean($alias, "mixed");
	    }
	  }
	}
      }
    }
  }
  /**
   * génération de la documentaion, selon les droits d'un utilisateur
   */
  function getDocumentationData(){
    return null;
  }
  /**
   * @author Bastien Sevajol
   * Attacher un callbacks à un évènement.
   * @param $event_name: Utilisé pour identifié les groupes de callbacks
   * @param callable $closure: la fonction à appeler
   */
  public function addCallback($event_name, \Closure $closure) {
    if (!array_key_exists($event_name, $this->callbacks)) {
      $this->callbacks[$event_name] = array();
    }
    $this->callbacks[$event_name][] = $closure;
  }

  /**
   * @author Bastien Sevajol
   * Executer les callbacks liés a un événement.
   * TODO: J'ai essayé avec call_user_func_array pour avoir autant de paramètres à transmettre que voulu mais j'ai
   *     rencontré de drôles de bugs ... à développer donc
   * @param string $event_name
   * @param $ar
   */
  protected function triggerCallbacks($event_name, $ar) {
    if(!empty($this->callbacks[$event_name]) && is_array($this->callbacks[$event_name])) {
    foreach ((array) $this->callbacks[$event_name] as $callback) {
      $ar = $callback($this, $ar);
    }
    }
    return $ar;
  }

  /**
   *  données de configuration pour surveillance
   */
  public function getPublicConfig($ar=NULL){
    list($versionsList, $toApply) = $this->getAncestorsAndVersions(true,true);
    $bloc=array('moid'=>$this->_moid,
		'toid'=>$this->toid,
		'modulename'=>$this->getLabel(),
		'version'=>implode(';', $versionsList));
    if($this->_testmode) $bloc['testmode']=$this->_testmode;
    if(!empty($toApply)) $bloc['upgradesToApply']=$toApply;
    return $bloc;
  }

  /**
   * information sur la/les langues en cour et le mode
   */
  public function languagesInfosFlags($ar=null){
    return '';
  }

  /// recherche de l'activité récente sur le module
  public function activity($ar=NULL) {
    return NULL;
  }
  /// corbeille
  public function browseTrash($ar=null){
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>TZR_RETURN_DATA]);
    $res = ['actions'=>['delAll'=>null]];
    if ($this->secure(null, 'emptyTrashAll')){
      $next = $this->getMainAction();
      $res['actions']['delAll'] = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).http_build_query(['moid'=>$this->_moid, 'function'=>'emptyTrashAll','template'=>'Core.empty.html','_skip'=>1,'_next'=>$next]);
    }
    return \Seolan\Core\Shell::toScreen1($p->get('tplentry'), $res);
  }
  public function moveFromTrash($ar=null){
  }
  public function emptyTrash($ar=null){
  }
  /**
   * Vidage de la corbeille
   * création de la tâche et exécution immédiate si pas trop "lourde"
   */
  public function emptyTrashAll($ar=null){

    $scheduler = \Seolan\Core\Module\Module::singletonFactory(XMODSCHEDULER_TOID);

    list($roid, $now) = $this->createEmptyTrashAllTask($scheduler);
    if ($now){
      $scheduler->executeJob($roid);
      \Seolan\Core\Shell::setNextData('message', 'Corbeille vidée');
    } else {
      \Seolan\Core\Shell::setNextData('message', 'Le vidage de la corbeille est programmé. Il sera bientôt effectif.');
    }

  }
  /**
   * create de la tâche de vidage de la corbeille
   * retourne sont oid et le top executer immadiatement ou pas
   * -> à préciser pour chaque type module
   */
  protected function createEmptyTrashAllTask(\Seolan\Module\Scheduler\Scheduler $scheduler, $more=null){

    if ($more == null){
      $more = ['function'=>'emptyTrashAllBatch'];
    }
    if (!isset($more['function'])){
      $more['function'] = 'emptyTrashAllBatch';
    }
    $r = $scheduler->createJob($this->_moid, null, 'Purge trash '.$this->_moid.' '.$this->getLabel(), $more, '');

    return [$r['oid'], false];

  }
  /**
   * récupéation d'un objet mailer pour envoi de mails
   */
  protected function getMailer($interactive=true):\Seolan\Library\Mail{
    return new static::$mailerClass($interactive);
  }
  /**
   * traitement effectif du vidage de la corbeille : par defaut, rien
   * -> à préciser pour chaque type de module
   * -> synchrone et asynchrone (voir createEmptyTrashAllTask)
   */
  public function emptyTrashAllBatch(\Seolan\Module\Scheduler\Scheduler $scheduler, $o, $more){

  }
  /// ajout des actions sur les lignes de corbeille
  protected function setTrashActions(&$browse){
    $viewArchiveActionHelper = $this->getViewArchiveActionHelper();
    $restoreArchiveActionHelper = $this->getRestoreArchiveActionHelper();
    $delArchiveActionHelper = $this->getDelArchiveActionHelper();

    foreach($browse['lines_oid'] as $i=>$oid){
      $oidlvlro = ['ro']; // lecture pour voir et ecriture pour restaurer ou effacer
      $oidlvlrw = ['admin','rwv','rw'];
      if($viewArchiveActionHelper)
	$this->browseActionForLine($viewArchiveActionHelper,$browse,$i,$oid,$oidlvlro,($usersel=false),false);
      if ($restoreArchiveActionHelper)
	$this->browseActionForLine($restoreArchiveActionHelper,$browse,$i,$oid,$oidlvlrw, ($usersel=false), false);
      if ($delArchiveActionHelper)
	$this->browseActionForLine($delArchiveActionHelper,$browse,$i,$oid,$oidlvlrw, ($usersel=false), false);
    }

  }


  /// check RGPD du module
  public function RGPDCheck(&$report) {
    if($this->RGPD_identity) {
      $report[]='OK Le module '.$this->getLabel().' est un ensemble de personnes';
    }
    if($this->RGPD_personalData) {
      $report[]='OK Le module '.$this->getLabel().' contient des données personnelles de type '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD',$this->RGPD_typeOfData).' avec une durée de rétention de '.$this->RGPD_retention.' jours ('.($this->RGPD_retention/365.25).' années)';
    }
  }
  /**
   * Fonction d'appel du service de contrôle SPF, DKIM et DMARK sur une adresse mail
   * @param email Adresse mail
   * @return 0 si le service est indisponible, sinon 'OK', 'Warning' ou 'Critical'
   */
  public function checkEmail($ar) {
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>'']);
    $tplentry = $p->get('tplentry');
    $email = strtolower(trim($p->get('email')));
    $url = TZR_FROM_URL.$email;
    $s = curl_init();
    $options = array(
      CURLOPT_URL            => $url,
      CURLOPT_RETURNTRANSFER => true,     // return web page
      CURLOPT_HEADER         => false,    // don't return headers
      CURLOPT_FOLLOWLOCATION => true,     // follow redirects
      CURLOPT_ENCODING       => '',       // handle all encodings
      CURLOPT_USERAGENT      => '\Seolan\Module\MailingList', // who am i
      CURLOPT_AUTOREFERER    => true,     // set referer on redirect
      CURLOPT_CONNECTTIMEOUT => 5,      // timeout on connect
      CURLOPT_TIMEOUT        => 5,      // timeout on response
      CURLOPT_MAXREDIRS      => 2,       // stop after 2 redirects
      CURLOPT_SSL_VERIFYPEER => true     // Enabled SSL Cert checks
    );

    curl_setopt_array( $s, $options );
    $result = curl_exec($s);
    $status = curl_getinfo($s,CURLINFO_HTTP_CODE);
    curl_close($s);
    if ($status != 200) {
      \Seolan\Core\Logs::critical(__METHOD__, "unable to check antispam status '$url' '$status'");
      $result = ['value'=>'0'];
    } else {
      $result=json_decode($result, true);
    }
    if ($tplentry == TZR_RETURN_DATA){
      return $result['value'];
    }
    die($result['value']);
  }


  /// rend un expéditeur après avoir vérifié qu'il est bien configuré
  function getSenderWithName($email='', $name='') {
    if($this->checkEmail(['tplentry'=>TZR_RETURN_DATA, 'email'=>$email])=="OK") {
      return [$email, $name];
    } else {
      return ['noreply@' . implode('.', array_slice(explode('.', parse_url($GLOBALS['HOME_ROOT_URL'], PHP_URL_HOST)), -2)), $name];
    }
  }
  /**
   * icone par defaut du module
   */
  public function getIconCssClass(){
    return $this->getConfigurationOption('iconcssclass', static::$iconcssclass);
  }
  /**
   * à mettre en place au cas par cas
   */
  protected function getViewArchiveActionHelper(){
    return null;
  }
  protected function getDelArchiveActionHelper(){
    return null;
  }
  protected function getRestoreArchiveActionHelper(){
    return null;
  }
}

\Seolan\Core\Module\Module::$_modules=array();

// Data
\Seolan\Core\Module\Module::$_modules[XMODTABLE_TOID]=array('MODULE'=>'Table','CLASSNAME'=>'\Seolan\Module\Table\Table','GROUP'=>'Data');
\Seolan\Core\Module\Module::$_modules[XMODRECORD_TOID]=array('MODULE'=>'Record','CLASSNAME'=>'\Seolan\Module\Record\Record','GROUP'=>'Data');
\Seolan\Core\Module\Module::$_modules[XMODMEDIA_TOID]=array('MODULE'=>'Médiathèque','CLASSNAME'=>'\Seolan\Module\Media\Media','GROUP'=>'Data');
\Seolan\Core\Module\Module::$_modules[XMODMULTITABLE_TOID]=array('MODULE'=>'Table avec variantes','CLASSNAME'=>'\Seolan\Module\MultiTable\MultiTable','GROUP'=>'Data');
\Seolan\Core\Module\Module::$_modules[XMODDOCMGT_TOID]=array('MODULE'=>'Document Management','CLASSNAME'=>'\Seolan\Module\DocumentManagement\DocumentManagement','GROUP'=>'Data');
\Seolan\Core\Module\Module::$_modules[XMODDOCSET_TOID]=array('MODULE'=>'Ensemble de documents','CLASSNAME'=>'\Seolan\Module\DocSet\DocSet','GROUP'=>'Data');

// Website
\Seolan\Core\Module\Module::$_modules[XMODINFOTREE_TOID]=array('MODULE'=>'InfoTree','CLASSNAME'=>'\Seolan\Module\InfoTree\InfoTree','GROUP'=>'Website');
\Seolan\Core\Module\Module::$_modules[XMODBLOG_TOID]=array('MODULE'=>'Blog','CLASSNAME'=>'\Seolan\Module\Blog\Blog','GROUP'=>'Website');
\Seolan\Core\Module\Module::$_modules[XMODSOCIAL_TOID]=array('MODULE'=>'Réseaux sociaux','CLASSNAME'=>'\Seolan\Module\Social\Social','GROUP'=>'Website');
\Seolan\Core\Module\Module::$_modules[XMODFORM_TOID]=array('MODULE'=>'Formulaires','CLASSNAME'=>'\Seolan\Module\Form\Form','GROUP'=>'Website');
\Seolan\Core\Module\Module::$_modules[XMODCOMMENT_TOID]=array('MODULE'=>'Comments','CLASSNAME'=>'\Seolan\Module\Comment\Comment','GROUP'=>'Website');
\Seolan\Core\Module\Module::$_modules[XMODMINISITES_TOID]=array('MODULE'=>'Minisites','CLASSNAME'=>'\Seolan\Module\MiniSite\MiniSite','GROUP'=>'Website');
\Seolan\Core\Module\Module::$_modules[XMODFRONTUSERS_TOID]=array('MODULE'=>'Utilisateurs Front-office','CLASSNAME'=>'\Seolan\Module\FrontUsers\FrontUsers','GROUP'=>'Website');

// Communication
\Seolan\Core\Module\Module::$_modules[XMODMAILINGLIST_TOID]=array('MODULE'=>'Mailing Liste','CLASSNAME'=>'\Seolan\Module\MailingList\MailingList','GROUP'=>'Communication');
\Seolan\Core\Module\Module::$_modules[XMODCRM_TOID]=array('MODULE'=>'CRM','CLASSNAME'=>'\Seolan\Module\Contact\Contact','GROUP'=>'Communication');
\Seolan\Core\Module\Module::$_modules[XMODWALL_TOID]=array('MODULE'=>'Mur d\'information','CLASSNAME'=>'\Seolan\Module\Wall\Wall', 'GROUP'=>'Communication');
\Seolan\Core\Module\Module::$_modules[XMODCHAT_TOID]=array('MODULE'=>'Web chat','CLASSNAME'=>'\Seolan\Module\Chat\Chat','GROUP'=>'Communication');
\Seolan\Core\Module\Module::$_modules[XMODCRM2_TOID]=array('MODULE'=>'CRM','CLASSNAME'=>'\Seolan\Module\CRM\CRM', 'GROUP'=>'Communication');

//Mobile
\Seolan\Core\Module\Module::$_modules[XMODPUSHNOTIFICATION_TOID]=array('MODULE'=>'Notification Push','CLASSNAME'=>'\Seolan\Module\PushNotification\PushNotification', 'GROUP'=>'Mobile');
\Seolan\Core\Module\Module::$_modules[XMODPUSHNOTIFICATIONDEVICE_TOID]=array('MODULE'=>'Device','CLASSNAME'=>'\Seolan\Module\PushNotification\Device\Device', 'GROUP'=>'Mobile');
\Seolan\Core\Module\Module::$_modules[XMODCONFIGMOBAPP_TOID]=array('MODULE'=>'Configurateur d\'application mobile','CLASSNAME'=>'\Seolan\Module\ConfigMobApp\ConfigMobApp', 'GROUP'=>'Mobile');

// Statistics
\Seolan\Core\Module\Module::$_modules[XMODSTATS_TOID]=array('MODULE'=>'Statistics','CLASSNAME'=>'\Seolan\Module\BackOfficeStats\BackOfficeStats','GROUP'=>'Statistics');
\Seolan\Core\Module\Module::$_modules[XMODDLSTATS_TOID]=array('MODULE'=>'Statistiques téléchargements','CLASSNAME'=>'\Seolan\Module\DownloadStats\DownloadStats','GROUP'=>'Statistics');
\Seolan\Core\Module\Module::$_modules[XMODREF_TOID]=array('MODULE'=>'Referencement','CLASSNAME'=>'\Seolan\Module\FrontOfficeStats\FrontOfficeStats','GROUP'=>'Statistics');

// Organization
\Seolan\Core\Module\Module::$_modules[XMODCALENDAR_TOID]=array('MODULE'=>'Calendar','CLASSNAME'=>'\Seolan\Module\Calendar\Calendar','GROUP'=>'Organisation');
\Seolan\Core\Module\Module::$_modules[XMODCALENDARADM_TOID]=array('MODULE'=>'Gestion d\'un module calendrier','CLASSNAME'=>'\Seolan\Module\Calendar\Management\Management','GROUP'=>'Organisation');
\Seolan\Core\Module\Module::$_modules[XMODTASKS_TOID]=array('MODULE'=>'Tasks Management','CLASSNAME'=>'\Seolan\Module\Tasks\Tasks','GROUP'=>'Organisation');
\Seolan\Core\Module\Module::$_modules[XMODPROJECT_TOID]=array('MODULE'=>'Projets','CLASSNAME'=>'\Seolan\Module\Project\Project','GROUP'=>'Organisation');

// Flux
\Seolan\Core\Module\Module::$_modules[XMODSITRA_TOID]=array('MODULE'=>'Interface Sitra','CLASSNAME'=>'\Seolan\Module\Sitra\Sitra','GROUP'=>'Flux / Interfaces');
\Seolan\Core\Module\Module::$_modules[XMODTOURINSOFT_TOID]=array('MODULE'=>'Interface Tourinsoft','CLASSNAME'=> '\Seolan\Module\Tourinsoft\Tourinsoft','GROUP'=>'Flux / Interfaces');
\Seolan\Core\Module\Module::$_modules[XMODTIF_TOID]=array('MODULE'=>'Interface TourinFrance','CLASSNAME'=>'\Seolan\Module\Tif\Tif','GROUP'=>'Flux / Interfaces');
\Seolan\Core\Module\Module::$_modules[XMODSKIPLAN_TOID]=array('MODULE'=>'Skiplan','CLASSNAME'=>'\Seolan\Module\SkiPlan\SkiPlan','GROUP'=>'Flux / Interfaces');

// Commerce
\Seolan\Core\Module\Module::$_modules[XMODCART_TOID]=array('MODULE'=>'Shopping Cart','CLASSNAME'=>'\Seolan\Module\Cart\Cart','GROUP'=>'Commerce');
\Seolan\Core\Module\Module::$_modules[XMODWEBRESA_TOID]=array('MODULE'=>'Webresa.fr trip index synchronization','CLASSNAME'=>'\Seolan\Module\WebResa\WebResa','GROUP'=>'Commerce');
\Seolan\Core\Module\Module::$_modules[XMODMONETIQUE_TOID]=array('MODULE'=>'Monetique','CLASSNAME'=>'\Seolan\Module\Monetique\Monetique','GROUP'=>'Commerce');
\Seolan\Core\Module\Module::$_modules[XMODCART2_TOID]=array('MODULE'=>'Shopping Cart V2','CLASSNAME'=>'\Seolan\Module\CartV2\CartV2','GROUP'=>'Commerce');

// Others
\Seolan\Core\Module\Module::$_modules[XMODLOCK_TOID]=array('MODULE'=>'Booking','CLASSNAME'=>'\Seolan\Module\Lock\Lock','GROUP'=>'Others');
\Seolan\Core\Module\Module::$_modules[XMODSHORTCUT_TOID]=array('MODULE'=>'ShortCut','CLASSNAME'=>'\Seolan\Module\Shortcut\Shortcut','GROUP'=>'Others');
\Seolan\Core\Module\Module::$_modules[XMODMAP_TOID]=array('MODULE'=>'Google Maps - 2','CLASSNAME'=>'\Seolan\Module\Map\Map','GROUP'=>'Others');
\Seolan\Core\Module\Module::$_modules[XMODEXTERN_TOID]=array('MODULE'=>'Module Externe','CLASSNAME'=>'\Seolan\Core\Module\Module','GROUP'=>'Others');
\Seolan\Core\Module\Module::$_modules[XMODREDIRECT_TOID]=array('MODULE'=>'Redirections','CLASSNAME'=>'\Seolan\Module\Redirect\Redirect','GROUP'=>'Others');
\Seolan\Core\Module\Module::$_modules[XMODTARTEAUCITRON_TOID]=array('MODULE'=>'Tarte Au Citron','CLASSNAME'=>'\Seolan\Module\TarteAuCitron\TarteAuCitron', 'GROUP'=>'Others');

// System
\Seolan\Core\Module\Module::$_modules[XMODSCHEDULER_TOID]=array('MODULE'=>'Scheduler','CLASSNAME'=>'\Seolan\Module\Scheduler\Scheduler','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODREPLICATION_TOID]=array('MODULE'=>'Replication','CLASSNAME'=>'\Seolan\Module\Replication\Replication','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODGROUP_TOID]=array('MODULE'=>'Gestion des groupes','CLASSNAME'=>'\Seolan\Module\Group\Group','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODUSER2_TOID]=array('MODULE'=>'Gestion d\'utilisateurs','CLASSNAME'=>'\Seolan\Module\User\User','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODADMIN_TOID]=array('MODULE'=>'Administration','CLASSNAME'=>'\Seolan\Module\Management\Management','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODSUB_TOID]=array('MODULE'=>'Abonnements','CLASSNAME'=>'\Seolan\Module\Subscription\Subscription','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODDATASOURCE_TOID]=array('MODULE'=>'Gestion des sources de donnees','CLASSNAME'=>'\Seolan\Module\DataSource\DataSource','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODRULE_TOID]=array('MODULE'=>'Rules','CLASSNAME'=>'\Seolan\Module\Workflow\Rule\Rule','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODWORKFLOW_TOID]=array('MODULE'=>'Workflow','CLASSNAME'=>'\Seolan\Module\Workflow\Workflow','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODMAILLOGS_TOID]=array('MODULE'=>'Logs des emails','CLASSNAME'=>'\Seolan\Module\MailLogs\MailLogs','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODAPP_TOID]=array('MODULE'=>'Applications','CLASSNAME'=>'\Seolan\Module\Application\Application','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODCACHE_TOID]=array('MODULE'=>'Gestion du cache','CLASSNAME'=>'\Seolan\Module\Cache\Cache','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODMEDIACOLLECTION_TOID]=array('MODULE'=>'Gestion des droits de la médiathèque','CLASSNAME'=>'\Seolan\Module\Media\Collection\Collection','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODWAITINGROOM_TOID]=array('MODULE'=>'Salle d\'attente','CLASSNAME'=>'\Seolan\Module\WaitingRoom\WaitingRoom','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODMANUAL_TOID]=array('MODULE'=>'Documentation locale','CLASSNAME'=>'\Seolan\Module\Doc\Doc','GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODSEARCH_TOID]=array('MODULE'=>'Recherche SolR','CLASSNAME'=>'\Seolan\Module\Search\Search','GROUP'=>'System');

// groupes à voir peut-être
\Seolan\Core\Module\Module::$_modules[XMODTAG_TOID]=array('MODULE'=>'Tags','CLASSNAME'=>'\Seolan\Module\Tag\Tag', 'GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODTAGUSER_TOID]=array('MODULE'=>'Tags Utilisateurs','CLASSNAME'=>'\Seolan\Module\TagUser\TagUser', 'GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODMOODLE_TOID]=array('MODULE'=>'Moodle','CLASSNAME'=>'\Seolan\Module\Moodle\Moodle', 'GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODBACKOFFICEINFOTREE_TOID]=array('MODULE'=>'Gestion de Rubriques Backoffice','CLASSNAME'=>'\Seolan\Module\BackOfficeInfoTree\BackOfficeInfoTree', 'GROUP'=>'System');
\Seolan\Core\Module\Module::$_modules[XMODRGPD_TOID]=array('MODULE'=>'RGPD','CLASSNAME'=>'\Seolan\Module\RGPD\RGPD', 'GROUP'=>'System');

// boutiques (à voir ?)
