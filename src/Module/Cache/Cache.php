<?php
namespace Seolan\Module\Cache;
// Gestion du cache
//
class Cache extends \Seolan\Module\Table\Table {
  public $cacheactivated=true;
  public $cachetimeout=3600;
  public $cachegracetimeout=10000;
  public static $singleton = true;
  public $freq="hourly;*/10";

  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Cache_Cache');
  }

  /// suppression du module
  function delete($ar=NULL) {
    parent::delete($ar);
  }

  /// initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Cache_Cache','modulename');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Cache_Cache','cacheactivated'), 'cacheactivated', 'boolean', NULL,false, $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Cache_Cache','cachegracetimeout'), 'cachegracetimeout', 'text', NULL,'10000', $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Cache_Cache','cachetimeout'), 'cachetimeout', 'text', NULL,3600, $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','cronfrequency'),'freq','text',NULL,'hourly;*/10',$alabel);
    $this->_options->delOpt('trackchanges');
    $this->_options->delOpt('trackaccess');
    $this->_options->delOpt('archive');
    $this->trackchanges=$this->trackaccess=$this->archive=false;
  }

  /// Edition des propriétés du module
  function procEditProperties($ar=NULL){
    $ret=parent::procEditProperties($ar);
    $xini=new \Seolan\Core\Ini();
    $xini->delVariable(array('variable'=>'cache_timeout'));
    $xini->addVariable(array('section'=>'\Seolan\Module\Cache\Cache','variable'=>'cache_timeout','value'=>$this->cachetimeout));
    $xini->delVariable(array('variable'=>'cache_activated'));
    $xini->addVariable(array('section'=>'\Seolan\Module\Cache\Cache','variable'=>'cache_activated','value'=>$this->cacheactivated));
    $xini->delVariable(array('variable'=>'cache_gracetimeout'));
    $xini->addVariable(array('section'=>'\Seolan\Module\Cache\Cache','variable'=>'cache_gracetimeout','value'=>$this->cachegracetimeout));
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['refreshCache'] = ['rw','rwv','admin'];
    $g['clear']=array('list','ro','rw','rwv','admin');
    $g['refreshCache']=array('ro','rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// cette fonction est appliquee pour afficher l'ensemble des methodes de ce module
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my);
    if($this->secure('','clear')){
      $o1=new \Seolan\Core\Module\Action($this,'cacheclear',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Cache_Cache','clear'),
			    '&moid='.$this->_moid.'&function=clear&template=Core.message.html&method=immediate','actions');
      $o1->menuable=true;
      $my['cacheclear']=$o1;
      $o1=new \Seolan\Core\Module\Action($this,'cacherefresh',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Cache_Cache','refresh'),
			    '&moid='.$this->_moid.'&function=clear&template=Core.message.html&method=differed','actions');
      $o1->menuable=true;
      $my['cacherefresh']=$o1;
    }
  }

  function getInfos($ar=NULL){
    $ret=parent::getInfos($ar);
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>TZR_RETURN_DATA));
    $tplentry=$p->get('tplentry');
    $ret['infos']['defaults']=(object)array('label'=>'Nombre de pages rendues en periode de grace',
					    'html'=>\Seolan\Core\DbIni::get('xmodcache:defaults:counter','val'));
    $ret['infos']['failure']=(object)array('label'=>'Nombre de pages rendues en defaut',
					   'html'=>\Seolan\Core\DbIni::get('xmodcache:failure:counter','val'));
    $ret['infos']['provisioning']=(object)array('label'=>'Nombre de generations de pages anticipees',
					    'html'=>\Seolan\Core\DbIni::get('xmodcache:provisioning:counter','val'));
    $ret['infos']['queue']=(object)array('label'=>'Pages en attente de recalcul','html'=>getDB()->fetchOne('SELECT COUNT(distinct KOID) FROM _PCACHE WHERE pagedefaults > 0'));
    $ret['infos']['currentdefauts']=(object)array('label'=>'Nombre de demandes non satisfaites en cours','html'=>getDB()->fetchOne('SELECT SUM(pagedefaults) FROM _PCACHE WHERE pagedefaults > 0'));
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// enregistrement d'une page dans le module
  function registerPage($file, $url) {
    $url=self::canonicUrl($url);
    getDB()->execute('DELETE FROM _PCACHE where url = ?',array($url));
    $pagedefaults = ($this->cachetimeout == 0 && (!$_REQUEST['forcecache'] || !$_REQUEST['cacheoid'])) ? 1 : 0;
    $this->xset->procInput(array('file'=>realpath($file), 
				 'url'=>$url,
				 'pagedefaults'=>$pagedefaults,
				 'endcache'=>date('Y-m-d H:i:s', strtotime('+'.$this->cachetimeout.' seconds')),
				 '_nolog'=>1,
				 '_local'=>true));
  }

  /// on indique que la page n'est plus valide  et qu'il faut la recalculer au prochain passage
  function registerPageDefault($url) {
    $url=self::canonicUrl($url);
    getDB()->execute('UPDATE LOW_PRIORITY _PCACHE set pagedefaults=pagedefaults+1 where url = ?',array($url));
    \Seolan\Core\DbIni::inc('xmodcache:defaults:counter');
  }

  /// on indique que les pages ne sont plus valides et qu'il faut les recalculer au prochain passage
  static function registerPagesDefault($pattern) {
    getDB()->execute('UPDATE LOW_PRIORITY _PCACHE set pagedefaults=pagedefaults+1 where url like ?',array($pattern));
    \Seolan\Core\DbIni::inc('xmodcache:defaults:counter');
  }

  /// on indique que l'entree du cache doit etre recalculée
  function registerCacheDefault($oid) {
    getDB()->execute('UPDATE LOW_PRIORITY _PCACHE set pagedefaults=pagedefaults+1 where KOID = ?',array($oid));
    \Seolan\Core\DbIni::inc('xmodcache:update:counter');
  }

  /// on indique que la page n'est plus valide et on la supprime du cache
  static function registerPageFailure($url) {
    $url=self::canonicUrl($url);
    getDB()->execute('DELETE FROM _PCACHE where url = ?',array($url));

    // le nettoyage des fichiers est fait par les taches de nettoyage des fichiers temporaires
    \Seolan\Core\DbIni::inc('xmodcache:failure:counter');
  }

  /// suppression d'une entree et du fichier associe
  function del($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');

    if(is_array($oid))
      return parent::del($ar);

    if(\Seolan\Core\Kernel::isAKoid($oid)) {
      $disp=$this->xset->rDisplay($oid);
      if(file_exists($disp['ofile']->raw)) unlink($disp['ofile']->raw);
      if(file_exists($disp['ofile']->raw.'.headers')) unlink($disp['ofile']->raw.'.headers');
    }
    $ar['_nolog']=1;
    return parent::del($ar);
  }

  static function canonicUrl($url) {
    return \Seolan\Core\Cache::canonicUrl($url);
  }

  /// exploitation des fichiers de traces des pages defaults pour mettre à jour la base. Pages livrées en période de grace 
  protected function updatePageDefaults() {
    $files=\Seolan\Library\Dir::scan(TZR_LOG_DIR,false,true,false,array('(\.log)','(\.gz$)'),false);
    foreach($files as $file) {
      $decode=explode('-',$file);
      if($decode[0]!='pagedefaults') continue;
      $date=$decode[1];
      if($date<date('YmdHi')) {
	// pour chacune des urls dans le fichier on rajoute un sur le nombre de demandes de l'url
	if($handle = fopen(TZR_LOG_DIR.$file, 'r')) {
	  while (!feof($handle))  {
	    $url = substr(fgets($handle),0,-1);
	    if(!empty($url)) {
	      $this->registerPageDefault($url);
	    }
	  }
	  fclose($handle);
	  // suppression du fichier après exploitation
	  unlink(TZR_LOG_DIR.$file);
	}
      }
    }
  }

  /// verification qu'un module est bien installé. Vérification que la tâche existe dans le scheduler, ajout sinon?
  public function chk(&$message=null) {
    if ($message == null)
      $message = '';
    $scheduler=\Seolan\Core\Module\Module::singletonFactory(XMODSCHEDULER_TOID);
    if(!is_object($scheduler)) {
      $m=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Messages','xmodscheduler_missing');
      $message.=$m;
    } else {
      list($period, $freq) = explode(';', $this->freq);
      if (empty($period) || empty($freq)){
	$period = 'hourly';
	$freq = '*/10';
      }
      $scheduler->createSimpleJob("cron", $this->_moid, 'refreshCache', NULL, "root", "Cache refresh", "Created by chk", NULL, $period, $freq);
    }
    return parent::chk($message);
  }

  /// fonction qui recalcule le cache
  public function refreshCache(\Seolan\Module\Scheduler\Scheduler& $s, $o, $arraymore) {
    if($lck=\Seolan\Library\Lock::getLock('\Seolan\Module\Cache\Cache')) {
      // mise à jour des defauts de pages à partir des fichiers pagedefaults-yymmddii qui sont dans les logs
      $this->updatePageDefaults();
      
      // recalcul des pages qui ont ete demandees
      $time=time();
      $rs=getDB()->select("SELECT * FROM _PCACHE WHERE pagedefaults > 0 and url not like '%md5group=%' ORDER BY pagedefaults DESC, endcache DESC");
      $totalpages=0;
      $totalpagescomputed=0;
      $max=getDB()->fetchOne("SELECT MAX(pagedefaults) FROM _PCACHE");
      if ($rs->rowCount() > 0){
	$amplitudemax=$time-\Seolan\Field\DateTime\DateTime::dateToTimestamp(getDB()->fetchOne("SELECT MIN(endcache) FROM _PCACHE WHERE pagedefaults >0"));
      }
      $waitingRoom = \Seolan\Core\Ini::get('wr_queue_class') ?? '\Seolan\Module\WaitingRoom\Queue';
      while($ors=$rs->fetch()) {
        $totalpages++;
        $load = \Seolan\Core\System::uptime();
        $server_is_ok = ($load['procs.r']<=TZR_MAX_LOAD);
        if($server_is_ok && !$waitingRoom::active()) {
          $rate1=(1-(($time - \Seolan\Field\DateTime\DateTime::dateToTimestamp($ors['endcache']))/$amplitudemax));
          $scoring = ($ors['pagedefaults']/$max)+$rate1;
          if($scoring>=1.0) {
            $totalpagescomputed++;
            \Seolan\Core\Logs::debug(__METHOD__.': computing '.$ors['url']." (defaults: ".$ors['pagedefaults'].', end cache:'.$ors['endcache'].', scoring '.$scoring.")");
	    if(!strstr($ors['url'],'?')) {
	      $ors['url'].='?';
	    }
            $ors['url'].='&forcecache=1&cacheoid='.$ors['KOID'];
            file_get_contents($ors['url']);
            \Seolan\Core\DbIni::inc('xmodcache:provisioning:counter');
          } else {
            \Seolan\Core\Logs::debug('\Seolan\Module\Cache\Cache::_daemon(): skipping '.$ors['url']." (page defaults: ".$ors['pagedefaults'].', end cache:'.$ors['endcache'].', scoring '.$scoring.")");
	  }
	}
      }
      // suppression du contenu qui a depasse la duree de vie de grace du cache
      $rs=getDB()->select('SELECT * FROM _PCACHE WHERE endcache < ?',
			  array(date('Y-m-d H:i:s', strtotime('-'.($this->cachegracetimeout).' seconds'))));
      $totalpagesdeleted=0;
      while($ors=$rs->fetch()) {
	$totalpagesdeleted++;
	$this->del(array('oid'=>$ors['KOID'],'_local'=>1));
      }
      \Seolan\Library\Lock::releaseLock($lck);
      $report=$totalpages.' pages scanned '.$totalpagescomputed.' pages computed '.$totalpagesdeleted.' pages deleted';
      $s->setStatusJob($o->KOID,'finished', $report);
      
      \Seolan\Core\Logs::debug('\Seolan\Module\Cache\Cache::_daemon(): '.$report);
    }
  }

  
  /// reinitialisation des stats une fois par jour
  protected function _daemon($period="any") {
    parent::_daemon($period);
    
    // on reinitialise les compteurs une fois par jour
    if($period=="daily") {
      \Seolan\Core\DbIni::clear('xmodcache:provisioning:counter');
      \Seolan\Core\DbIni::clear('xmodcache:failure:counter');
      \Seolan\Core\DbIni::clear('xmodcache:defaults:counter');
      \Seolan\Core\DbIni::clear('xmodcache:update:counter');
    }
  }
  
  /// calcule une condition sur le nom de page ou le nom de fichier à partir d'un alias
  protected function getAliasCondition($alias) {
    return array('%/'.$alias.'%', '%-'.$alias.'-%');
  }

  /// effacement de tout le cache
  public function clear($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array('tplentry'=>TZR_RETURN_DATA));
    $method=$p->get('method');
    switch($method) {
    case 'immediate':
      getDB()->execute('TRUNCATE _PCACHE');
      $c=new \Seolan\Core\Cache();
      $c->clean_cache();
      break;
    case 'differed':
      getDB()->execute('update _PCACHE set endcache=IF(endcache<curdate(),endcache,curdate());');
      break;
    }
    \Seolan\Core\Shell::toScreen2('','message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','cachecleaned','text'));
  }

  /// suppression du cache en relation avec un alias. Si $immediate=true on supprime l'entree dans le cache sinon on la marque pour recalcul
  public function clean($alias, $mode="differed", $priority=false) {
    $rs=getDB()->select('SELECT * FROM _PCACHE WHERE url like ? OR file like ?', $this->getAliasCondition($alias));
    \Seolan\Core\Logs::debug('\Seolan\Module\Cache\Cache::clean(): try to clean alias '.$alias);
    while($ors=$rs->fetch()) {
      switch($mode) {
      case "immediate":
	$this->del(array('oid'=>$ors['KOID'],'_local'=>1));
	break;
      case "differed":
	$this->registerCacheDefault($ors['KOID']);
	break;
      case "mixed":
	if($ors['pagedefaults']>0 || $ors['endcache']>date('Y-m-d H:i:s')) {
	  $this->registerCacheDefault($ors['KOID']);
	} else {
	  $this->del(array('oid'=>$ors['KOID'],'_local'=>1));
	} 
	break;
      }
    }
  }
}

