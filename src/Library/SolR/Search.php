<?php
namespace Seolan\Library\SolR;
\Seolan\Core\System::loadVendor('SolrPhpClient/Apache/Solr/Service.php');

class Search extends SearchBase {
  public $solr_dir='/solr/';
  public $index=NULL;
  public $solr_server='localhost';
  public $solr_port=8950;
  public $solr_core=NULL;
  private $defaultEncoding=NULL;

   function __construct($ar=NULL) {
    $this->defaultEncoding = TZR_INTERNAL_CHARSET;
    if(\Seolan\Core\Ini::get('solr_port')) $this->solr_port=\Seolan\Core\Ini::get('solr_port');
    if(\Seolan\Core\Ini::get('solr_core')) $this->solr_core=\Seolan\Core\Ini::get('solr_core');
    else $this->solr_core=$GLOBALS['DATABASE_NAME'];
    if(!\Seolan\Core\Ini::get('solr_activated')) return;
    \Seolan\Core\Logs::notice(__METHOD__, " {$this->solr_port}:{$this->solr_dir}/{$this->solr_core}");
    switch($this->defaultEncoding){
    case 'UTF-8':
      $this->index=new \Apache_Solr_Service($this->solr_server,$this->solr_port,$this->solr_dir.$this->solr_core);
      break;
    default:
      \Seolan\Core\Logs::critical(__METHOD__." no analyzer for encoding {$this->encoding}, lucene default");
    }
  }

  /// Controler l'état d'un coeur et retourne son status
  private function checkCoreStatus() {
    
    if(!\Seolan\Core\Ini::get('solr_activated'))
      return true;
      
    // Recupere le status du coeur et du coup  vérifie son fonctionnement 

    $result=$this->load('http://localhost:'.$this->solr_port.'/solr/admin/cores?action=STATUS&core='.$this->solr_core);

    if(!$result) return false;

    $xml=simplexml_load_string($result);
    $initFailures=$xml->xpath('/response/lst[@name="initFailures"]/str[@name="'.$this->solr_core.'"]');
    if (!empty($initFailures)) {
      \Seolan\Core\Logs::debug(__METHOD__.' '.$this->solr_core.': '.$initFailures[0]);
      return false;
    }
    $status=$xml->xpath('/response/lst[@name="status"]/lst[@name="'.$this->solr_core.'"]/str[@name="name"]');
    return $status;
  }

  /// Vérifie que le coeur du projet existe et le créé si ce n'est pas le cas
  function checkCore(){
    // vérifier que le dossier existe
    if(!empty($this->index) && !file_exists(TZR_VAR2_DIR.'solr')){
      $result=$this->load('http://localhost:'.$this->solr_port.'/solr/admin/cores?action=UNLOAD&core='.$this->solr_core);
      if(!$result) return false;
    }
    $status = $this->checkCoreStatus();

    if($status===false) return false;

    // Si il n'existe pas, on le créé
    if(empty($status)){
      // Efface les lastindexation pour relancer une indexation complète
      \Seolan\Core\DbIni::clear('lastindexation_%',false);
      // Copie des fichiers de config et paramétrage des droits
      exec('cp -r '.$GLOBALS['LIBTHEZORRO'].'/Vendor/solr/tzr/default/* '.TZR_VAR2_DIR.'solr');
      exec('chmod g+wx '.TZR_VAR2_DIR.'solr');
      exec('setfacl -d -m g::rwx '.TZR_VAR2_DIR.'solr');

      // Création du coeur
      
      $result=$this->load('http://localhost:'.$this->solr_port.'/solr/admin/cores?persist=true&action=CREATE&name='.$this->solr_core.'&instanceDir='.TZR_VAR2_DIR.'solr');
      if(!$result) {
        // Contrôler l'état du nouveau core
        $status = $this->checkCoreStatus();
        if(empty($status))
          return false;
      } else {
        // Check que la création a fonctionné
        $xml=simplexml_load_string($result);
        if(!$xml) return false;
        $core=$xml->xpath('/response/str[@name="core"]');
        if(empty($core)) return false;
      }
      // Initialise le moteur
      $this->index=new \Apache_Solr_Service($this->solr_server,$this->solr_port,$this->solr_dir.$this->solr_core);
    }
    return true;
  }
  /// Encapsulation de file_get_contents pour checker le serveur solr
  protected static function load($url){
    \Seolan\Core\Logs::debug(__METHOD__." {$url}");
    return file_get_contents($url,
			     false,
			     stream_context_create(['http'=>['method'=>'GET',
							     'ignore_errors' => '1']]));
  }
  /// Commit et optimise l'index
  function optimize() {
    if(empty($this->index)) return;
    $this->index->commit();
    $this->index->optimize();
  }

  /// Commit
  function commit() {
    if(empty($this->index)) return;
    $this->index->commit();
  }

  /// Lance l'indexation de tous les modules
  function checkIndex(\Seolan\Module\Scheduler\Scheduler $scheduler=NULL,$o=NULL,$more=NULL){
    if(empty($this->index)) {
      \Seolan\Core\Logs::notice(__METHOD__,"no instance index, return");
      return;
    }

    // On autorise que 2 indexations en parallele
    if(!$globalLock = \Seolan\Library\Lock::getGlobalLock('checkIndex',2,1))
      return;
    
    \Seolan\Core\Logs::notice(get_class($this),get_class($this).'::buildIndex start');
    \Seolan\Library\ProcessCache::deactivate();
    $check=isset($more->check)?$more->check:true;
    $limit=isset($more->limit)?$more->limit:NULL;
    $cond=isset($more->cond)?$more->cond:NULL;
    $list=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA,'noauth'=>true,'withmodules' => true));
    foreach($list['lines_mod'] as $i=>$omod) {
      if(is_object($omod)){
	if(!$lck=\Seolan\Library\Lock::getLock('buildSearchIndex'.$omod->_moid)) continue;
	$omod->buildSearchIndex($this,$check,$limit,$cond,false);
	\Seolan\Library\Lock::releaseLock($lck);
      }
    }
    $this->index->commit();
    \Seolan\Library\ProcessCache::activate();
    \Seolan\Library\Lock::releaseLock($globalLock);
    \Seolan\Core\Logs::notice(get_class($this), get_class().'::buildIndex end');
    if(!empty($scheduler)) $scheduler->setStatusJob($o->KOID, 'finished', $comments);
  }

  /// Recherche globale
  function globalSearch($ar=NULL){
    if(empty($this->index)) return;
    \Seolan\Core\Labels::loadLabels('Seolan_Library_SolR_SolR');
    $p=new \Seolan\Core\Param($ar,['getdetails'=>true]);
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
    $deepsearch = $p->get('deepsearch');
    $qt=$p->get('qt'); // Nom du requestHandler à utiliser
    $qf=$p->get('qf'); // Query fields (si vide, le qf du requestHhandler est utilisé)
    // On prepare le tableau de retour. La requete est enregistrée avant ajout du filtre de module
    $ret=array('modules'=>array(),'query'=>$qs, 'modedouble'=>$modedouble, 'deepsearch'=>$deepsearch);
    // si modedouble, requete sans content sauf si parametre deepsearch
    if(empty($qt)){
      if($modedouble && $deepsearch != 1) $qt='standard';
      else $qt='full';
    }
    // autres filtres, dont filtre de langue (si multilangues ...)
    // à terme devrait être un champ 
    
    if (!\Seolan\Core\Shell::getMonoLang()){
      $fq = 'id:'.$lang_data.'* AND ';
    } else {
      $fq='';
    }
    if(!empty($moidfilter)){
      if(is_array($moidfilter)) $fq.='(moid:'.implode(' OR moid:',$moidfilter).') AND ';
      else $fq.='(moid:'.$moidfilter.') AND ';
    }
    $modlist = \Seolan\Core\Module\Module::modlist();
    $tocheck = $docs = array();
    $numFound = [];
    foreach ($modlist['lines_insearchengine'] as $i => $insearchengine) {
      if (!$insearchengine) continue;
      $moid = $modlist['lines_oid'][$i];
      $mod = \Seolan\Core\Module\Module::objectFactory($moid);
      if (is_null($mod)||!$mod->secure('', ':list')) continue;
      // comme on n'échape pas les requetes ... ce qui permet de faire des requetes natives
      try {
        $hits = $this->getSearchResponse($q, array(), array('sort' => 'score desc', 'fq' => $fq.'moid:'.$moid,'qt'=>$qt,'qf'=>$qf), 0, TZR_XSEARCH_MAXRESULTS);
        foreach ($hits->response->docs as $hit) {
          list($dlang, $doid, $dmoid) = explode('|', $hit->id);
          $docs[$dmoid][$doid] = $hit;
	  $tocheck[\Seolan\Core\Kernel::getTable($doid)][]=$doid;
        }
        $numFound[$moid] = $hits->response->numFound;
      } catch(\Exception $e) {
        \Seolan\Core\Logs::critical(__METHOD__, $e->getMessage());
      }
    }
    // vérification existence, suppression des objets inexistants
    $deletedOids = [];
    foreach ($tocheck as $table => $oids) {
      $existantOids = getDB()->fetchCol("SELECT KOID FROM $table where LANG=\"$lang_data\" and KOID in (\"".implode('","', $oids).'")');
      $inexistantOids = array_diff($oids, $existantOids);
      foreach ($inexistantOids as $oid) {
	$this->deleteItem($oid,$dmoid,$lang_data,false);
        $deleted = true;
	$deletedOids[] = $oid;
      }
      $numFound[$dmoid] -= count($inexistantOids);
    }
    if (!empty($deletedOids))
      $this->index->commit();

    foreach ($docs as $dmoid => $_docs) {
      $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$dmoid,'tplentry'=>TZR_RETURN_DATA));
      if(!is_object($mod)) continue;
      foreach ($_docs as $doid => $hit) {
	// on ne présente que les oid auxquels on a accès
        $sec=$mod->secure($doid,':ro');
        if(!$sec || in_array($doid, $deletedOids)) {
	  continue;
	}
        $obj=$mod->getSearchResult($doid,$advfilter);//RZ
        if($obj!==false){
          if(!isset($ret['modules'][$dmoid])){
	  $ret['modules'][$dmoid]=array('template'=>$mod->searchtemplate,'name'=>$mod->getLabel(),'lines_oid'=>array(),'lines_title'=>array(),
                                          'lines_moid'=>array(),'lines_score'=>array(),'count'=>0, 'numFound' => $numFound[$dmoid]);
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
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  
  public function getSearchResponse($search_string, array $module_ids=[], array $parameters=[], $offset=0, $limit=TZR_XSEARCH_MAXRESULTS){
    $parameters = $this->getSearchParameters($module_ids, $parameters);
    $this->logSearch(__METHOD__, $search_string, $parameters);
    return new \Seolan\Library\SolR\Response($this->index->search($search_string, $offset, $limit, $parameters));
  }
  
  protected function getSearchParameters(array $module_ids, array $parameters=[]){
    $parameters = array_merge(array('sort'=>'score desc',
                                    'qt'  =>'standard',
                                    'qf'  => ''), $parameters);
    $filter_query = $this->getMoidsFilterQuery($module_ids);
    if ($filter_query){
      if (!empty($parameters['fq'])){
        $parameters['fq'] = $parameters['fq'].' AND ('.$filter_query.')';
      } else {
        $parameters['fq'] = $filter_query;
      }
    }
    return $parameters;
  }
  protected function getMoidsFilterQuery(array $module_ids){
    if (!empty($module_ids)){
      return 'moid:'.implode(' OR moid:', $module_ids);
    }
    return null;
  }
  
  protected function logSearch($method, $search_string, $parameters){
    \Seolan\Core\Logs::notice($method.' q="'.$search_string.'" / fq="'.($parameters['fq']??'').'" / qt="'.($parameters['qt']??'').'" / qf="'.($parameters['qf']??''.'"'));
  }

  /// Effacement d'une entree dans le moteur de recherche
  function deleteItem($oid,$moid,$lang=NULL,$commit=true){
    if(empty($this->index)) return;
    $tzrid=array();
    if(!$lang){
      foreach($GLOBALS['TZR_LANGUAGES'] as $l=>&$v) $tzrid[]=$this->tzrid($oid,$moid,$l);
    }else{
      $tzrid[]=$this->tzrid($oid,$moid,$lang);
    }
    try{
      foreach($tzrid as $id){
	\Seolan\Core\Logs::notice(get_class($this),get_class($this).'::_deleteItem delete doc : '.$id);
	$this->index->deleteById($id);
      }
      if($commit) $this->index->commit();
    }catch(\Exception $e){
      \Seolan\Core\Logs::critical(__METHOD__,":: error ".get_class($e)." on id '$id' deletion ".$e->getMessage());
      return;
    }
  }

  /// Effacement d'entrées via une requete
  function deleteQuery($q){
    if(empty($this->index)) return;
    $this->index->deleteByQuery($q);
  }

  /// Ajoute ou met à jour un item
  function addItem($oid,$fields,$moid=NULL,$lang=NULL,$commit=false) {
    if(empty($this->index)) return;
    try {
      $document=new \Apache_Solr_Document();
      $document->id=$this->tzrid($oid,$moid,$lang);
      $document->moid=$moid;
      foreach($fields as $fn=>$fv){
          if(!empty($fv)){
              // Enleve les carateres unicode illégaux
              $document->$fn=cleanStringForXML($fv);
          }
      }
      $this->index->addDocument($document);
      if($commit) $this->index->commit();
    } catch(\Exception $e) {
      \Seolan\Core\Logs::critical(__CLASS__.':'.__METHOD__, $e->getMessage());
    }
  }
  /// Verifie si un doc existe dans l'index
  function docExists($oid,$moid,$lang){
    if(empty($this->index)) return;
    $tzrid=$this->tzrid($oid,$moid,$lang);
    $rep=$this->index->search('id:"'.$tzrid.'"',0,1);
    return $rep->response->numFound;
  }
}

