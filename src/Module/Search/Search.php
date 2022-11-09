<?php
namespace Seolan\Module\Search;
/**
 * Module recherche
 * dans cette version :
 * - paramètres de configuration
 * - status
 * - relance d'indexation
 */

use \Seolan\Core\Module\{Module,Action};
use \Seolan\Core\{Logs,Shell,DbIni,Param};
use \Seolan\Library\SolR\SearchV2;

class Search extends Module {

  public  static $singleton=true;
  public  $solr_port='8983';
  public  $solr_host='localhost';
  public  $solr_core=null;
  public  $solr_active=0;

  public static $accountType = 'Solr core account';
  public static $adminAccountType = 'Solr admin account';

  protected $solr_path='/';
  protected $solr_core_user=null;
  protected $solr_core_passwd = null;
  protected $solr_admin_user=null;
  protected $solr_admin_passwd = null;

  protected $solr_scheme='https';

  function __construct($ar=null){
    parent::__construct($ar);
  }
  /**
  * affiche l'état du coeur configuré
  */
  public function getInfos($ar=null){

    $br = parent::getInfos($ar);

    $conf = $this->getSolrConfiguration();

    if ($this->solr_active){

      $br['infos']['solrconf'] = (object)['label'=>'Configuration Solr',
					  'html'=>"Active, Host : '{$conf->host}', Core : {$conf->core}, User : '{$conf->core_user}', Admin user : '{$conf->admin_user}'"];

      try{

	if (\Seolan\Library\SolR\SearchV2::v2Ready()){
	  $search = \Seolan\Library\SolR\SearchV2::objectFactory();
	  $ping = $search->pingSolr();
	  $details = ["core : {$conf->core} {$conf->port} {$conf->core_user}"];
	  $details[] = "ping : {$ping}";
	  if ($ping == 'OK'){
	    $status = $search->detailCoreStatus();
	    if ($status['status'] == 'N/A')
	      $details[] = "<strong>Status not recheable</strong>";
	    if ($status['status'] == "unknown '{$conf->core}'")
	      $details[] = "<strong>Core not found</strong>";
	    foreach(["datadir", "numberofdocs", "size", "startTime", "lastModified", "uptime"] as $k){
	      $details[] = "$k : {$status[$k]}";
	    }
	  }
	  $br['infos']['solrstatus']=(object)[
	    'label'=>'Statut SolR', 'html'=>implode('<br>', $details)
	  ];

	} else {
	  $br['infos']['solrstatus']=(object)[
	    'label'=>'Statut SolR', 'html'=>"SolR 8 not available"
	  ];
	}
      } catch(\Throwable $t){
	$br['infos']['solrstatus']=(object)[
	  'label'=>'SolR', 'html'=>"Core : {$search->solr_core}<br>Error cheking core status : <pre>{$t->getMessage()}\n{$t->getTraceAsString()}}</pre>"
	];
      }
    } else {
      $br['infos']['solrstatus']=(object)['label'=>'SolR', 'html'=>'No active'];
    }
    return Shell::toScreen1('br',$br);
  }
  /**
   * liste des modules indexés (+/- infos)
   * -> cas des InfoTree (ou autres) indexés par application
   */
  function indexedModules(?array $ar=null):array{
    $on = true;
    if (!$this->solrActive()){
      Shell::alert('SolR n\'est pas activé');
      $on = false;
    }
    // le last indexation / application
    $apps = $this->applicationsList();
    $mods = static::modlist(['tplentry'=>TZR_RETURN_DATA,'basic'=>true]);
    $insearchengine = [];
    $search = SearchV2::objectFactory();
    foreach($mods['lines_oid'] as $i=>$moid){
      $mparam = static::findParam($moid);
      if (isset($mparam['MPARAM']['insearchengine'])
	         && $mparam['MPARAM']['insearchengine']){

      	$last = DbIni::get("lastindexation_{$moid}",'val');

      	$lastapp = [];
      	if (!empty($apps)){
      	  foreach($apps as $app){

      	    if (in_array($moid, $app->getUsedModules())){
      	      $lastapp[$app->oid] = "{$app->name} ({$app->domain}) ".DbIni::get("lastindexation_{$moid}_{$app->oid}",'val');
            }
      	  }
      	}

	      $status = $search->moduleStatus($moid);

      	$insearchengine[$moid] = [
      	  'name'=>$mods['lines_name'][$i],
      	  'lastindexation'=>$last,
      	  'numofdocs'=>$status['numofdocs']??'N/A',
      	  'lastapp'=>$lastapp
      	];

      }
    }
    return Shell::toScreen1('br', ($foo=['list'=>$insearchengine,'on'=>$on]));
  }
  protected function applicationsList(){
    $apps = [];
    if (defined('TZR_USE_APP')){
      $modapp = Module::singletonFactory(XMODAPP_TOID);
      foreach(getDB()->select('select * from APP') as $ors){
	       $apps[$ors['KOID']] = $modapp->getAppByOrs($ors);
      }
    }
    return $apps;
  }
  protected function solrActive(){
    return $this->solr_active;
  }
  public function getSolrConfiguration(){

    if (empty($this->solr_core_user)){
      $account = getDB()->fetchRow('select login, passwd from _ACCOUNTS where atype=?',
				   [static::$accountType]);
      if (empty($account))
        Logs::critical(__METHOD__,"'solr core account' not found in table _ACCOUNTS");

      $this->solr_core_user = $account['login'];
      $this->solr_core_passwd = $account['passwd'];

    }

    if (empty($this->solr_admin_user)){
      $adminAccount = getDB()->fetchRow('select login, passwd from _ACCOUNTS where atype=?',
				   [static::$adminAccountType]);

      if (empty($adminAccount)){
	       $adminAccount = ['login'=>'solradmin',
			       'passwd'=>'solRIsFun'];
	      Logs::critical(__METHOD__,"'solr admin account' not found in table _ACCOUNTS");
      }

      $this->solr_admin_user = $adminAccount['login'];
      $this->solr_admin_passwd = $adminAccount['passwd'];

    }
    return (Object)[
      'active'=>$this->solr_active,
      'scheme'=>$this->solr_scheme,
      'host'=>$this->solr_host,
      'port'=>$this->solr_port,
      'core'=>$this->solr_core,
      'path'=>$this->solr_path,
      'admin_user'=>$this->solr_admin_user,
      'admin_passwd'=>$this->solr_admin_passwd,
      'core_user'=>$this->solr_core_user,
      'core_passwd'=>$this->solr_core_passwd];
  }
  /**
   * met à jour les dates de dernière indexation pour les modules sélectionnés
   * - concernne la date d'indexation du module ou du module dans les applications
   * qui l'utilisent
   */
  function forceIndexation(?array $ar=null){
    $p = new Param($ar, []);

    if ($p->is_set('newindexdate')){
      $date = $p->get('newindexdate');
      $withapp = $p->get('withapp');

      foreach($date as $moid=>$date){
      	if (!empty($date) && $date != TZR_DATE_EMPTY){
      	  DbIni::set("lastindexation_{$moid}", "{$date} 00:00:00");
      	  if (isset($withapp[$moid])){
      	    foreach(explode(',', $withapp[$moid]) as $appoid){
      	      DbIni::set("lastindexation_{$moid}_{$appoid}", "{$date} 00:00:00");
      	    }
      	  }
      	}
      }
    }

    Shell::setNext($this->getMainAction());

  }
  /**
   * les options de configuration : host, port core, on/off
   */
  public function initOptions(){
    parent::initOptions();
    foreach(['usetrash','sendacopyto','sendacopytofiles','directorymodule','object_sec','inxlink','insearchengine','available_in_display_modules','defaultispublished'] as $name){
      $this->_options->delOpt($name);
    }
    $this->_options->setOpt('Actif',
			    'solr_active',
			    'boolean',
			    [],
			    '0',
			    'Solr'
    );
    $this->_options->setOpt('Host/IP',
			    'solr_host',
			    'text',
			    [],
			    'localhost',
			    'Solr'
    );
    $this->_options->setOpt('Port',
			    'solr_port',
			    'text',
			    [],
			    '8983',
			    'Solr'
    );
    $this->_options->setOpt('Core',
			    'solr_core',
			    'text',
			    [],
			    $GLOBALS['DATABASE_NAME'],
			    'Solr'
    );
  }
  /**
   * ajout du menu liste des modules indexés
   */
  protected function _actionlist(&$my, $alfunction=true){

    parent::_actionlist($my);

    if ($this->secure('', 'indexedModules')){
      $o1 = new Action($this,'modlist',
		       null,
		       '&'.http_build_query(['moid'=>$this->_moid,
					 'function'=>'indexedModules',
					 'template'=>'Module/Search.modlist.html']),
		       'display');
      $o1->containable=true;
      $o1->setToolbar('Seolan_Core_General','browse');
      $my['query']=$o1;
    }

    return $my;
  }
  function secGroups($function, $group=NULL) {

    $g=[
      'indexedModules'=>['admin'],
      'forceIndexation'=>['admin']
    ];

    if (!isset($g[$function]))
      return parent::secGroups($function, $group);

    if(!empty($group))
      return in_array($group, $g[$function]);
    return $g[$function];

  }
  function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).
	   http_build_query(
	     ['moid'=>$this->_moid,
	      'function'=>'indexedModules',
	      'template'=>'Module/Search.modlist.html',
	      'tplentry'=>'br'
	   ]);
  }
  function delete($ar){
    parent::delete($ar);
    DbIni::clearStatic('solr_v2_ready');
  }
}
