<?php
namespace Seolan\Library\SolR;

use \Seolan\Core\{Logs,Ini,DbIni};
use \Seolan\Core\Module\Module;

class SearchV2 extends SearchBase  {
    
  private $solariumAdminClientInstance = null;
  private $solariumCoreClientInstance = null;
  static protected $segmentNumber = 5;
  
  static protected $solr_path;
  static protected $solrAdminUser=null;
  static protected $solrAdminPasswd=null;
  static protected $solrConf=null;
  static protected $solr_request_timeout=1800; // secondes

  protected static $adminAccountType = 'Solr admin account';
  
  function __construct($ar=NULL) {
    $this->defaultEncoding = TZR_INTERNAL_CHARSET;
  }
  /// compatibilité avec la version 1 : opérations directement sur l'index
  public function __get($name){
    if ($name == 'index')
      return $this;
  }
  /// Reset pour re indexation complete
  public function resetCoreIndex(){
    DbIni::clear('lastindexation_%',false);
  }
  /// En V2 on ne fait plus de création auto.
  /// On checke et notifie dans les traitements daily (checkCoreVersion)
  function checkCore(){
    
  }
  /// Commit et optimise l'index
  function optimize() {
    return $this->solariumOptimize();
  }
  /// Commit
  function commit() {
    return $this->solariumCommit();
  }
  /// Lance l'indexation de tous les modules
  function checkIndex(\Seolan\Module\Scheduler\Scheduler $scheduler=NULL,$o=NULL,$more=NULL){
    $conf = static::getSolrConfiguration();
    if (!$conf->active){
      Logs::notice(__METHOD__,"solr not activated");
      return;
    }
    // On autorise que max scheduler - 1  indexations en parallele
    $maxcheckrunning = max(1, TZR_XMODSCHEDULER_RUNNINGPENDING -1);
    if(!$globalLock = \Seolan\Library\Lock::getGlobalLock('checkIndex',$maxcheckrunning,1)) return;
    Logs::notice(__METHOD__,'buid index start');
    \Seolan\Library\ProcessCache::deactivate();
    $check=isset($more->check)?$more->check:true;
    $limit=isset($more->limit)?$more->limit:NULL;
    $cond=isset($more->cond)?$more->cond:NULL;
    $list=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA,'noauth'=>true,'withmodules' => true));
    foreach($list['lines_mod'] as $i=>&$omod) {
      if(is_object($omod)){
	if(!$lck=\Seolan\Library\Lock::getLock('buildSearchIndex'.$omod->_moid)) continue;
	$omod->buildSearchIndex($this,$check,$limit,$cond,false);
	\Seolan\Library\Lock::releaseLock($lck);
      }
    }
    $this->commit();
    \Seolan\Library\ProcessCache::activate();
    \Seolan\Library\Lock::releaseLock($globalLock);
    Logs::notice(get_class($this), get_class().'::buildIndex end');
    if(!empty($scheduler)) $scheduler->setStatusJob($o->KOID, 'finished', $comments);
  }
  
  /// Recherche globale
  function globalSearch($ar=NULL){
    
    $client = $this->solariumGetCoreClientInstance();

    \Seolan\Core\Labels::loadLabels('Seolan_Library_SolR_SolR');

    $p=new \Seolan\Core\Param($ar,['getdetails'=>true,
				   'moidfilter'=>[], // ex : recherche par Module/DocumentManagement
    ]);

    $q=$p->get('query');
    $qs = $q;
    $lang_data = \Seolan\Core\Shell::getLangData();
    /// for tag search
    $q = preg_replace('/\B'.\Seolan\Field\Tag\Tag::$TAG_PREFIX.'/','tags:',$q);
    $q = preg_replace('/\B'.\Seolan\Field\Text\Text::$USERTAG_PREFIX.'/','usertags:',$q);

    $tplentry=$p->get('tplentry');
    $details=$p->get('getdetails');
    $advfilter=$p->get('advfilter');
    $moidfilter=$p->get('moidfilter');
    if (!is_array($moidfilter))
      $moidfilter = [$moidfilter];
    $deepsearch = $p->get('deepsearch');
    // full ou std ou un type prévu par le client
    $queryType=$p->get('qt');

    // qf est un champ "dismax", sinon c'est le df du handler qui est pris en compte
    $queryFields=$p->get('qf');

    // On prepare le tableau de retour. La requete est enregistrée avant ajout du filtre de module
    $ret=['modules'=>[],
	  'query'=>$qs,
	  'moidfilter'=>!empty($moidfilter)?implode(',', $moidfilter):null,
	  'deepsearch'=>$deepsearch];
    if(empty($queryType)){ // voir la conf solr, ce sont des surcharges (historique) 
      if($deepsearch == 1){
	$queryType = 'full';
      } else {
	$queryType = 'standard';
      }
    }

    $modlist = \Seolan\Core\Module\Module::modlist();
    $tocheck = $docs = [];
    $numFound = [];
    foreach ($modlist['lines_insearchengine'] as $i => $insearchengine) {
      $moid = $modlist['lines_oid'][$i];
      if (!$insearchengine || (!empty($moidfilter) && !in_array($moid, $moidfilter))){
	Logs::debug(__METHOD__." skip module moid {$moid} ".implode(',', $moidfilter));
	continue;
      }
      // autres filtres : langue et module surlequel porte la recherche
      $filterQueries = [];
      if ($lang_data !== null){
	$filterQueries[] =  "id:{$lang_data}*";
      }
      $filterQueries[] = "moid:{$moid}";
      $mod = \Seolan\Core\Module\Module::objectFactory($moid);
      if (is_null($mod)||!$mod->secure('', ':list')) continue;
      try {
        $hits = $this->getSearchResponse($q,
					 ['sort' => ['score'=>'desc'],
					  'fq' => $filterQueries, // filterQuery => le moid en cours en général
					  'qf'=>$queryFields, // queryFields => vide en général
					  'qt'=>($mod instanceof \Seolan\Module\InfoTree\InfoTree)?'full':$queryType
					 ],
					 0,
					 TZR_XSEARCH_MAXRESULTS);
        foreach ($hits as $hit) {
          list($dlang, $doid, $dmoid) = explode('|', $hit->id);
          $docs[$dmoid][$doid] = $hit;
	  $tocheck[\Seolan\Core\Kernel::getTable($doid)][]=$doid;
        }

	$numFound[$moid] = $hits->getNumFound();

	\Seolan\Core\Logs::debug(__METHOD__." query : $q, moid : $moid, found {$numFound[$moid]}");
	
      } catch(\Exception $e) {
        Logs::critical(__METHOD__, $e->getMessage());
      }
    }
    // vérification, suppression des objets inexistants
    $deletedOids = [];
    foreach ($tocheck as $table => $oids) {
      $existantOids = getDB()->fetchCol("SELECT KOID FROM $table where LANG=\"$lang_data\" and KOID in (\"".implode('","', $oids).'")');
      $inexistantOids = array_diff($oids, $existantOids);
      // revoir la doc mais on doit pouvoir ne faire qu'une seule requete ici + commit intégré
      foreach ($inexistantOids as $oid) {
	$this->deleteItem($oid,$dmoid,$lang_data,false);
        $deletedOids[] = $oid;
      }
      $numFound[$dmoid] -= count($inexistantOids);
    }
    if (!empty($deletedOids)){
      $this->commit();
    }
    $userid = \Seolan\Core\User::get_current_user_uid();
    foreach ($docs as $dmoid => $moddocs) {
      $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$dmoid,'tplentry'=>TZR_RETURN_DATA));
      if(!is_object($mod)) continue;
      foreach ($moddocs as $doid => $hit) {
	// on ne présente que les oid auxquels on a accès
	if (in_array($doid, $deletedOids)){
	  \Seolan\Core\Logs::debug(__METHOD__." oid '{$doid}' not inexistant, inexistants : '".implode(',',$inexistantOids)."'");
	  continue;
	}
	$sec=$mod->secure($doid,':ro');
        if(!$sec){
	  \Seolan\Core\Logs::debug(__METHOD__." oid '{$doid}' is not authorized, sec : '{$sec}' for user {$userid}");
	  continue;
	}
	// getSearchResponse n'est en place que dans les bases doc
        $obj=$mod->getSearchResult($doid,$advfilter);
        if($obj!==false){
          if(!isset($ret['modules'][$dmoid])){
	    $ret['modules'][$dmoid]=['template'=>$mod->searchtemplate,
				     'name'=>$mod->getLabel(),
				     'lines_oid'=>[],
				     'lines_title'=>[],
                                     'lines_moid'=>[],
				     'lines_score'=>[],
				     'count'=>0,
				     'numFound'=>$numFound[$dmoid]
	    ];
          }
          $ret['modules'][$dmoid]['lines_oid'][]=$doid;
          $ret['modules'][$dmoid]['lines_title'][]=$hit->title;
          $ret['modules'][$dmoid]['lines_score'][]=round($hit->score,2);
          $ret['modules'][$dmoid]['lines_moid'][]=$dmoid;
          $ret['modules'][$dmoid]['lines_obj'][]=$obj;
          $ret['modules'][$dmoid]['count']++;
        }
      }
    }
    // Dans le back-office, si 1 seul module on réexécute \Seolan\Core\Shell->run avec un procQuery sur ce module
    if (false && \Seolan\Core\Shell::admini_mode() && count($ret['modules']) == 1) {
      $moid = key($ret['modules']);
      $mod = \Seolan\Core\Module\Module::objectFactory($moid);
      if ($numFound[$moid] > TZR_XSEARCH_MAXRESULTS)
        setSessionVar('message', sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Library_SolR_SolR', 'previewinmodule'), $numFound[$moid], $ret['modules'][$dmoid]['count']));
      return $mod->showSearchResult($ret['modules'][$moid]['lines_oid']);
    }
    // calcul des données supplémentaire d'affichage du preview
    foreach ($ret['modules'] as $moid => $data) {
      $mod = \Seolan\Core\Module\Module::objectFactory($moid);
      $ret['modules'][$moid]['preview'] = $mod->previewSearchResult($data['lines_oid']);
    }
    $ret['_rawquery'] = $q;
    $ret['_instanceClassname'] = static::instanceClassname();
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /// recherche sur SolR
  public function getSearchResponse($searchString,
				    array $parameters = [],
				    $offset=0,
				    $limit=TZR_XSEARCH_MAXRESULTS,
				    $highlight=false){


    if (!isset($parameters['highlight']))
      $parameters['highlight'] = true;

    $this->logSearch(__METHOD__, $search_string, $parameters);

    $client = $this->solariumGetCoreClientInstance();
    $query = $client->createQuery('select');

    //$this->solariumConfigureQueryForLucene($query, $searchString, $parameters, $offset, $limit);
    $this->solariumConfigureQueryForEDismax($query, $searchString, $parameters, $offset, $limit);

    return $client->execute($query);

  }
  /// log des paramètres
  protected function logSearch($method, $search_string, $parameters){
    Logs::notice($method.' q='.$searchString.' / fq='.implode(',', $parameters['fq']??[]).' / qt='.$parameters['qt']??''.' / qf='.$parameters['qf']??'');
  }

  /// Effacement d'une entree dans le moteur de recherche
  function deleteItem($oid,$moid,$lang=NULL,$commit=true){
    return $this->solariumDeleteITem($oid,$moid,$lang,$commit);
  }

  /// Effacement d'entrées via une requete
  function deleteQuery($q){
    $this->solariumDeleteQuery($q);
  }
  // Ping sdu serveur solr
  public function pingSolr(){
    return $this->solariumPing(static::getSolrConfiguration());
  }
  /// Trace des infos de status
  public function detailCoreStatus(){
    return $this->solariumDetailCoreStatus(static::getSolrConfiguration());
  }
  public function moduleStatus($moid){
    try{
      $resp = $this->getSearchResponse("moid:{$moid}", [], 0, 1);
    }catch(\Throwable $t){
      Logs::critical(__METHOD__,$t->__toString());
      return ['numofdocs'=>'N/A'];
    }
    return ['numofdocs'=>$resp->getNumFound()];
  }
  /// compte admin
  public static function adminCredentials(){
    if (empty(static::$solrAdminUser)){
      $adminAccount = getDB()->fetchRow('select login, passwd from _ACCOUNTS where atype=?',
					[static::$adminAccountType]);
      if (empty($adminAccount)){
	$adminAccount = ['login'=>'solradmin',
			 'passwd'=>'solRIsFun'];
	Logs::critical(__METHOD__,"'solr admin account' not found in table _ACCOUNTS");
      } 
      
      static::$solrAdminUser = $adminAccount['login'];
      static::$solrAdminPasswd = $adminAccount['passwd'];

    }
    return [static::$solrAdminUser, static::$solrAdminPasswd];

  }
  /**
   * recup de la conf dans le module
   */
  public static function getSolrConfiguration($throw=true){
    if (static::$solrConf==null){
      $mod = Module::singletonFactory(XMODSEARCH_TOID);
      if (!isset($mod)){
	if ($throw)
	  throw new \Exception("No Module\\Search configured");
	return null;
      }
      static::$solrConf = $mod->getSolrConfiguration();
    }
    return static::$solrConf;
  }
  /**
   * version récente et accessible de solr
   */
  public static function v2Ready(){
    return  (DbIni::getStatic('solr_v2_ready', 'val') == '1');
  }

  /// requete de suppression
  protected function  solariumDeleteQuery($q){
    $client = $this->solariumGetCoreClientInstance();
    $update = $client->createUpdate();
    // à voir, l'appelant doit échaper la requête
    $update->addDeleteQuery($q);
    $update->addCommit();
    try{
      $result = $client->update($update);
    }catch(\Throwable $t){
      Logs::critical(__METHOD__,"'$q' ".$t->getMessage());
    }
  }
  /// Requete ping sur état du serveur
  protected function solariumPing($conf){
    $client = $this->solariumGetAdminClientInstance();  
    $ping = $client->createPing();
    try {
      $result = $client->ping($ping);
      $data = $result->getData();
      if (isset($data['status']))
	$res = $data['status'];
      else
	$res = 'N/A';

    } catch (\Exception $e) {
      Logs::critical(__METHOD__,$e->getMessage()."\n".$e->__toString());
      $res = 'Error : '.$e->getMessage();
    }
    return $res;
  }
  /// Statut du core, avec le client mais à partir du json
  protected function solariumDetailCoreStatus($conf){
    $core = $conf->core;
    $client = $this->solariumGetAdminClientInstance();
    $coreAdminQuery = $client->createCoreAdmin();
    $statusAction = $coreAdminQuery->createStatus();
    $coreAdminQuery->setAction($statusAction);
    $response = $client->coreAdmin($coreAdminQuery);
    try{
      $body = $response->getResponse()->getBody();
      $statuses = json_decode($body);
      if (empty($body) || empty($statuses))
	throw new \Exception('error loading cores infos');
      if (isset($statuses->initFailures->$core))
	throw new \Exception("init failure(s) for core $core\n".tzr_var_dump($statuses->initFailures->$core));
      
      if (!isset($statuses->status->$core)){
	return ['status'=>"unknown '$core'"];
      }

      $coreStatus = $statuses->status->$core;

    }catch(\Throwable $t){
      Logs::critical(__METHOD__,$t->getMessage());
      return ['status'=>'N/A'];
    }

    $secs = floor($coreStatus->uptime/1000);
    $m = floor(($secs%3600)/60);
    $h = floor(($secs%86400)/3600);
    $d = floor(($secs%2592000)/86400);
    $M = floor($secs/2592000);
    $uptime = "$M month(s), $d day(s), $h hour(s), $m minute(s)";
    $ret = [
      'status'=>'ok',
      'datadir'=>$coreStatus->dataDir,
      'numberofdocs'=>$coreStatus->index->numDocs,
      'size'=>$coreStatus->index->size,
      'startTime'=>$coreStatus->startTime,
      'lastModified'=>$coreStatus->index->lastModified,
      'uptime'=>'about '.$uptime
    ];

    return $ret;

  }
  /// Vérifie si un doc existe avec le client solarium
  protected function solariumDocExists(string $oid, string $moid, string $lang=null){
    $tzrid=$this->tzrid($oid,$moid,$lang);
    $client = $this->solariumGetCoreClientInstance();
    $query = $client->createQuery('select');
    $query->setQuery("id:\"{$tzrid}\"");
    $res = $client->execute($query);
    return ($res!=null && ($res->getNumFound()==1));
  }
  /// Configuration d'un objet requete du client solarium pour recherche avec edismax
  protected function solariumConfigureQueryForEDismax($query,$searchString, $parameters, $offset, $limit){

    $qt = $parameters['qt']??null;
    if ($qt!=null && in_array($qt, ['standard','full'])){
      $edismax = $query->getEDisMax(); // 'defType'
      // champs de recherche + ponderation 'qf', paramètre de dismax et edismax
      if ($qt == 'standard'){
	$queryFields = 'title^1 notice^0.75';
      } else if($parameters['qt'] == 'full'){
	$queryFields = 'title^1 notice^0.75 contents^0.5';
      }
      $edismax->setQueryFields($queryFields);
    }
    // liste des champs en retour ('fl')
    $query->setFields('id,moid,title,score');

    // filter queries (la langue et les modules) 'fq'
    if (!empty($parameters['fq'])){
      foreach((is_array($parameters['fq'])?$parameters['fq']:[$parameters['fq']]) as $i=>$filter){
	$query->createFilterQuery("lang-and-modules {$i}")->setQuery($filter);
      }
    }
    if (isset($parameters['pages'])){
      $query->setStart($parameters['pages']['start']);
      $query->setRows($parameters['pages']['rows']);
    }
    // tri
    foreach($parameters['sort'] as $field=>$order){
      $query->addSort($field, $order);
    }
    
    // hightlight terme dans son contexte
    if ($parameters['highlight'] == true){
      $hl = $query->getHighlighting();
      $hl->setFields('title,notice,contents');
      $hl->setSimplePrefix('<em class="hightlight">');
      $hl->setSimplePostfix('</em>');
    }
    
    if (isset($parameters['debug']))
      $query->getDebug();
    
    // le terme de recherche
    $query->setQuery($searchString);
    
  }
  /// Configuration d'un objet requete du client solarium pour recherche avec lucene
  protected function solariumConfigureQueryForLucene($query, $searchString, $parameters, $offset, $limit){

    // champs de recherche + ponderation 'qf', paramètre de dismax et edismax
    if ($qt == 'standard'){
      $fields = ['title'=>'1', 'notice'=>'0.75'];
    } else {
      $fields = ['title'=>'1', 'notice'=>'0.75', 'contents'=>'0.5'];
    }
    $qterms = [];
    foreach($fields as $fn=>$weight){
      $qterms[]="({$fn}:{$searchString}^{$weight})";
    }

    // liste des champs en retour ('fl')
    $query->setFields('id,moid,title,score');

    // filter queries (la langue et les modules) 'fq'
    if (!empty($parameters['fq'])){
      foreach((is_array($parameters['fq'])?$parameters['fq']:[$parameters['fq']]) as $i=>$filter){
	$query->createFilterQuery("lang-and-modules {$i}")->setQuery($filter);
      }
    }
    // tri
    foreach($parameters['sort'] as $field=>$order){
      $query->addSort($field, $order);
    }
    // hightlight terme dans son contexte
    if ($parameters['highlight'] == true){
      $hl = $query->getHighlighting();
      $hl->setFields('title,notice,contents');
      $hl->setSimplePrefix('<em class="hightlight">');
      $hl->setSimplePostfix('</em>');
    }
    
    if (isset($parameters['debug']))
      $query->getDebug();
    
    // le terme de recherche
    $query->setQuery(implode('or', $qterms));


  }
  /// Suppression vi la client solarium
  protected function solariumDeleteById(array $ids, bool $commit=true){

    $client = $this->solariumGetCoreClientInstance();
    $update = $client->createUpdate();
    foreach($ids as $id){
      $update->addDeleteById($id);
    }
    if ($commit)
      $update->addCommit();
    $result = $client->update($update);
    $status = $result->getStatus();

  }
  /// Commit via le client solarium
  protected function solariumCommit(){

    $client = $this->solariumGetCoreClientInstance();

    $update = $client->createUpdate();

    $update->addCommit();

    $result = $client->update($update);

    $status = $result->getStatus();

  }
  /// Effacement avec le client solarium
  protected function  solariumDeleteItem($oid,$moid,$lang=NULL,$commit=true){
    $tzrid=[];
    if(!$lang){
      foreach($GLOBALS['TZR_LANGUAGES'] as $l=>&$v)
      $tzrid[]=$this->tzrid($oid,$moid,$l);
    }else{
      $tzrid[]=$this->tzrid($oid,$moid,$lang);
    }
    try{
      $this->solariumDeleteById($tzrid, $commit);
    }catch(\Exception $e){
      Logs::critical(__METHOD__,get_class($e).' on id '.implode(',', $tzrid).' deletion error '.$e->getMessage());
    }
  }

  /// optimization avec le client solarium
  protected function solariumOptimize(){

    $client = $this->solariumGetCoreClientInstance();

    $update = $client->createUpdate();
    $update->addOptimize(true, // solft commit
			 false, // block until new search client
			 static::$segmentNumber // number of segment
    );
    $result = $client->update($update);

    $status = $result->getStatus();

  }
  /// identifiant d'un endpoint
  protected static function endPointKey($core){
    return "default endpoint {$core}";
  }
  public static function getAdminClient($core, $port, $host, $user=null){
    $scheme = 'https';
    $endpointkey = static::endPointKey($core);
    $client = static::solariumClientFactory([
      'endpoint' => [
	$endpointkey => [
	  'scheme' =>$scheme,
          'host' => $host,
          'port' => $port,
          'path' => '/',
          'core' => $core
    ]]]);
    if ($user == null){
      $user = ['user'=>null, 'passwd'=>null];
      list($user['user'], $user['passwd']) = static::adminCredentials();
    }
    static::configureAuthentication($client, $endpointkey, $user['user'], $user['passwd']);
    return $client;
  }
  /// Création d'un client solr admin
  protected function solariumGetAdminClientInstance(){
    return $this->solariumGetClientInstance($this->solariumAdminClientInstance, true);
  }
  /// Création du client solr core
  protected function solariumGetCoreClientInstance(){
    return $this->solariumGetClientInstance($this->solariumCoreClientInstance, false);
  }
  /// création d'un client solr
  protected function solariumGetClientInstance(&$instance, $admin){
    $conf = static::getSolrConfiguration();
    if ($instance == null){
      $endpointkey = static::endPointKey($conf->core);
      // le premier endpoint = par defaut
      // on peut avoir plusieurs endpoints, à spécifier alors dans la requête
      $config = [  'endpoint' => [
	$endpointkey => [
	  'scheme' => $conf->scheme,
          'host' => $conf->host,
          'port' => $conf->port,
          'path' => $conf->path,
          'core' => $conf->core
	]
      ]];
      $instance = static::solariumClientFactory($config);
      if ($admin){
	list($auser, $apasswd) = static::adminCredentials();
	static::configureAuthentication($instance,
					$endpointkey, 
					$auser,
					$apasswd);
      } else {
	static::configureAuthentication($instance,
					$endpointkey, 
					$conf->core_user,
					$conf->core_passwd);
      }
    }
    return $instance;
  }
  // core sécurisé ?
  static protected function configureAuthentication(\Solarium\Client $client,string $endpointkey, string $user, string $passwd){
	
    $endpoint = $client->getEndPoint($endpointkey);
    $endpoint->setAuthentication($user, $passwd);
    
  }
  /// création d'un client solarium pour une config donnée
  protected static function solariumClientFactory($config){
    
    $adapter = new SSLClient();
    
    $adapter->setTimeout(static::$solr_request_timeout);
    
    $listenerProvider = new Class() implements \Psr\EventDispatcher\ListenerProviderInterface {
      function __construct(){}
      function getListenersForEvent(object $event) : iterable {
	return [];
      }
    };
    
    $eventDispatcher = new Class($listenerProvider) implements \Psr\EventDispatcher\EventDispatcherInterface{
      private $listenerProvider = null;
      public function __construct(\Psr\EventDispatcher\ListenerProviderInterface $listenerProvider=null){
        $this->listenerProvider = $listenerProvider;
      }
      public function dispatch(object $event): object {
	if ($event->isPropagationStopped() === true)
	  return $event;
	if ($this->listenerProvider === null)
	  return $event;
	foreach($this->listenerProvider->getListenersForEvent($event) as $listener){
	  $listener($event);
	}
	return $event;
      }
    };
    return new \Solarium\Client($adapter, $eventDispatcher, $config);

  }
  /// Ajoute ou met à jour un item
  function addItem($oid,$fields,$moid=NULL,$lang=NULL,$commit=false) {

    $client = $this->solariumGetCoreClientInstance();

    // get an update query instance
    $update = $client->createUpdate();
    // create a new document for the data
    $doc = $update->createDocument();
    $doc->id = $this->tzrid($oid,$moid,$lang);
    $doc->moid=$moid;
    foreach($fields as $fn=>$fv){
      if(!empty($fv)){
        // Enleve les caractères unicode illégaux
        $doc->$fn=cleanStringForXML($fv);
      }
    }

    $update->addDocuments([$doc]);
    $update->addCommit();

    try{
      $result = $client->update($update);
    } catch(\Throwable $t){
      Logs::critical(__METHOD__,$t->getMessage()."\n".$t->getTraceAsString());
    }

  }

  /// Vérifie si un doc existe dans l'index
  function docExists($oid,$moid,$lang){
    return $this->solariumDocExists($oid, $moid, $lang);
  }

}
