<?php
namespace Seolan\Module\Replication;
/****c* tzr-5/\Seolan\Module\Replication\Replication
 * NAME
 *   \Seolan\Module\Replication\Replication -- module gérer une réplication complète entre serveurs
 * DESCRIPTION
 * Le système de réplication autorise le fonctionnement en mode
 * réparti, avec la certitude de conserver des information a jour sur
 * plusieurs serveurs.
 ****/
/// Module de gestion de la replication entre deux consoles Seolan
class Replication extends \Seolan\Module\Table\Table {
//  static $singleton=true;
  public $suspended = false;
  public $table="REPLI";
  public $group=0;
  protected $_journal=array();
  public $packetSize = 500;
  public $mailWarning = false;
  protected $soapclients = array();
  // tables incluses sytémétatiquement dans un jeu d'initialisation
  // todo voir \Seolan\Core\DataSource\DataSource systable
  protected $systtables = array('ACL4','AMSG','BASEBASE','DICT','MODULES','MSGS','SETS', 'TEMPLATES', 'USERS', 'GRP', 'OPTS');
  // tables jamais répliquées
  // idem voir \Seolan\Core\DataSource\DataSource 
  protected $notToReplicateTables = array('REPLI'=>1, 'TASKS'=>1, 'JOURNAL'=>1,
                                          'ACL4_CACHE'=>1, '_STATICVARS'=>1, '_VARS'=>1, 
                                          '_TMP'=>1, '_CACHE'=>1, 
                                          '_MLOGS'=>1, '_MLOGSD'=>1
                                        );
  public $identifybyHostName = 0;
  public $connectionTimeOut = 10;
  public $packetack = false;
  private function newChrono() {
    $rs=getDB()->select("select max(CHRONO)+1 from JOURNAL",array(),false,\PDO::FETCH_NUM);
    $nchrono=1;
    if($o1=$rs->fetch()) {
      $nchrono = $o1[0];
    }
    $rs->closeCursor();
    if($nchrono<=0) $nchrono=1;
    return $nchrono;
  }
  function __construct($ar=NULL) {
    $ar['moid']=self::getMoid(XMODREPLICATION_TOID);
    if(!\Seolan\Core\System::tableExists('REPLI')) \Seolan\Module\Replication\Replication::createRepli();
    if(!\Seolan\Core\System::tableExists('JOURNAL')) \Seolan\Module\Replication\Replication::createRepli();
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Replication_Replication');
    $this->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties");
    $this->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Replication_Replication','modulename');
    $this->_journal=array();
    $rs = getDB()->select('select * from BASEBASE');
    while($rs && $ors = $rs->fetch()){
      if(isset($ors['NOTTOREPLI']) && $ors['NOTTOREPLI'] == 1)
        $this->notToReplicateTables[$ors['BTAB']] = 1;
    }
  }
  /// enregistrement dans la table SQL du journal 
  function __destruct() {
    $this->sendToJournal();
    $this->closeSoapClients();
  }
  /// termine les clients soap 
  protected function closeSoapClients(){
    foreach($this->soapclients as $oid=>$client){
      \Seolan\Core\Logs::notice('repli', 'close client '.$client->sessid);
      if (!empty($client->soapclient))
        $client->soapclient->close(array('sessid'=>$client->sessid));
    }
  }
  ///initialisation des propriétés du module
  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Replication_Replication','enable'), "enable", 'boolean');
    $this->_options->setOpt('Délai de connexion (s)', 'connectionTimeOut', 'text', array('compulsory'=>false), '10');
    $this->_options->setOpt('Identification par nom des serveurs', 'identifybyHostName', 'boolean', array('compulsory'=>false));
    $this->_options->setOpt('Acquittement par paquet', 'packetack', 'boolean', array('compulsory'=>false));
    $this->_options->setOpt('Taille des paquets', 'packetSize', 'text', array('compulsory'=>true), 500);
    $this->_options->setOpt('Avertissement par mail', 'mailWarning', 'boolean', array('compulsory'=>false), false);
  }
  /// affichage de quelques propriétés du module
  public function getInfos($ar=null){
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>TZR_RETURN_DATA));
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=parent::getInfos($ar);
    // dernier numéro de chrono
    $rs=getDB()->select('select ifnull(min(chrono), 0) as pchrono,  ifnull(max(CHRONO), 0) as lchrono, ifnull(count(*), 0) as nb from JOURNAL');
    $o1=$rs->fetch();
    $rs->closeCursor();
    $ret['infos']['lchrno']=(object)array('label'=>'Dernier numéro de chrono','html'=>$o1['lchrono']);
    $ret['infos']['pchrno']=(object)array('label'=>'Premier numéro de chrono','html'=>$o1['pchrono']);
    $ret['infos']['nblines']=(object)array('label'=>'Nombre de lignes','html'=>$o1['nb']);
    $ret['infos']['activate']=(object)array('label'=>'Module activé','html'=>$this->enable);
    $ret['infos']['connectionTimeOut']=(object)array('label'=>'Délai connexion','html'=>$this->connectionTimeOut);
    $ret['infos']['identifybyHostName']=(object)array('label'=>'Identification des clients par nom du serveur','html'=>$this->identifybyHostName);
    $nottotables = array();
    foreach($this->notToReplicateTables as $k=>$r){
      if ($r == 1)
        $nottotables[]=$k;
    }
    $ret['infos']['nottotables']=(object)array('label'=>'Tables non repliquées','html'=>implode(', ', $nottotables));
    $rs = getDB()->fetchAll('select name, value from _STATICVARS where name like \'repli%\'');
    $chronos = array();
    foreach($rs as $ors){
      $chronos[] = $ors['name'].' &rArr; '.$ors['value'];
    }
    unset($rs);
    $ret['infos']['chronos']=(object)array('label'=>'Chronos mémorisés','html'=>implode('<br>', $chronos));
    $ret['infos']['wsdlcache']=(object)array('label'=>'soap.wsdl_cache_enabled', ini_get('soap.wsdl_cache_enabled'));

    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);

  }
  /// purge des logs de replication
  protected function purgeLogs(){
    // liste des slaves / full / master
    $rs = getDB()->select('select * from REPLI');
    $delay = ' 2 DAY ';
    while($rs && $ors = $rs->fetch()){
      $nb = getDB()->select("select count(*) as nb from LOGS where etype in  ('synchronizeServer', 'synchronizeServ', 'getchangeset', 'syncAck','getinitset','applyinitset') and object = '".$ors['KOID']."'")->fetch(\PDO::FETCH_COLUMN);
      if ($nb <= 100)
        continue;
      $rs1 = getDB()->select("select * from LOGS where etype in  ('synchronizeServer', 'synchronizeServ', 'getchangeset', 'syncAck','getinitset','applyinitset') and  object='".$ors['KOID']."' and dateupd < DATE_SUB(NOW(), INTERVAL ".$delay.") ");
      \Seolan\Core\Logs::notice('repli', 'archiving '.$rs1->rowCount().' for '.$ors['KOID']);
      while($rs1 && $ors1 = $rs1->fetch()){
        \Seolan\Core\Archive::appendData('LOGS',$ors1);
        getDB()->execute('delete from LOGS where KOID=\''.$ors1['KOID'].'\'',array(),false);
      }
    }
  }
  /// nettoyage du journal
  protected function purgeJournal(){
    // plus petit chrono acquitté des slave ou full
    $rs = getDB()->fetchCol('select KOID from REPLI where trepli in (\'full\', \'slave\')');
    $minchrono = -1;
    foreach($rs as $ors){
      $sinfos = $this->getServerInfos($ors);
      $lastackchrono = \Seolan\Core\DbIni::getStatic('replication:'.$sinfos['_ident'].':chrono','val');
      if (empty($lastackchrono))
        $lastackchrono = -1;
      if ($minchrono == -1 || $lastackchrono < $minchrono)
        $minchrono = $lastackchrono;
    }
    unset($rs);
    if ($minchrono != -1){
      $delay = ' 24 HOUR ';
      \Seolan\Core\Logs::notice('repli', 'purge du journal, minchrono : '.$minchrono);
      $rs = getDB()->select('select * from JOURNAL where CHRONO<'.$minchrono.' and UPD < DATE_SUB(NOW(), INTERVAL '.$delay.')');
      \Seolan\Core\Logs::notice('repli', $rs->rowCount().' lignes à purger');
      while($ors = $rs->fetch()){
        \Seolan\Core\Archive::appendData('JOURNAL',$ors);
        getDB()->execute('delete from JOURNAL where CHRONO=?',array($ors['CHRONO']),false);
      }
    }
    
  }
  /// nettoyage des initSets qui sont dans les points de sauvegarde
  protected function purgeInitSet(){
    $modadmin = \Seolan\Core\Module\Module::objectFactory(self::getMoid(XMODADMIN_TOID));
    $modadmin->browseCheckpoints();
    $cp = \Seolan\Core\Shell::from_screen('cp');
    $now = date('Ymd');
    $initsets = array();
    foreach($cp['list'] as $n=>$data){
      $res = array();
      if (preg_match('/^([0-9]{8})_[0-9]{6}_iniset$/', $n, $res)){
        if ($res[1] < $now)
          $initsets[] = $n;
      }
    $res = array();
      if (preg_match('/^Avant ([0-9]{8})_[0-9]{6}_iniset initialisation$/', $data['comment'], $res)){
        if ($res[1] < $now)
          $initsets[] = $n;
      }
    }
    foreach($initsets as $n){
      $r = $modadmin->delCheckpoint(array('checkpoint'=>$n));
      \Seolan\Core\Logs::notice('repli','suppression des initset : '.$n);
    }
  }
  /// securite des fonctions accessibles par le web
  public function secGroups($function,$group=NULL) {
    $g=array();
    /* ce sont des fonctions interactives +/- de tests */
    $g['applyChangeSet']=array('admin');
    $g['showChangeSet']=array('admin');
    $g['preShowChangeSet']=array('admin');
    $g["showInitSet"]=array("admin");
    $g['downloadInitSetFile'] = array("none", "ro", "rw", "rwv", "admin");
    $g['forceInitset'] = array('rwv', 'admin');

    // re initialisation de l'env : appelle un getInitSet et l'applique
    $g["applyinitset"]=array("admin");

    // fonction initiales (devrait devenir ro voire privées si soap activé)
    $g["getinitset"]=array("none","ro","rw","rwv","admin");
    $g["getchangeset"]=array("none","ro","rw","rwv","admin");
    $g["syncAck"]=array("none","ro","rw","rwv","admin");
    $g["getfile"]=array("ro","rw","rwv","admin");

    // fonctions du module soap
    $g['wgetChangeSet']=array("ro","rw","rwv","admin");
    $g['wgetInitSet']=array("ro","rw","rwv","admin");
    $g['wgetInitSetFiles']=array("ro","rw","rwv","admin");
    $g['wsyncAck']=array("ro","rw","rwv","admin");
    $g['wgetFile']=array("ro","rw","rwv","admin");

    // liste des tables et statuts divers de replication
    $g['tablesStatus'] = array('admin');
    $g['procEditTableStatus'] = array('admin');


    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }
  // liste des catégories reconnues dans cette classe
  public function secList() {
    return array('none','ro','rw','rwv', 'admin');
  }
  /// fonction qui reinitialise l'env de replication
  /// !!! incompatible avec un mode ou le client gère son chrono !
  public function initSynchro($ar=NULL) {
    getDB()->execute("delete from JOURNAL",array(),false);
    \Seolan\Core\DbIni::clearStatic("replication:%:chrono");
    getDB()->execute('update REPLI set dtack=NULL, chrack=NULL where trepli=\'full\' or trepli=\'slave\'',array(),false);
    \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true)."&moid={$this->_moid}&function=browse&template=Module/Table.browse.html&tplentry=br");
  }
  /// ??? methode pas finie
  public function synchronizeFull() {
    $rs=getDB()->select("select distinct async from REPLI where trepli='full' OR trepli='master'");
    while($ors=$rs->fetch()) {
      \Seolan\Core\Logs::notice('repli-info',"full synchro to $url");
      $url=$ors['async'];
      \Seolan\Core\Logs::notice('repli-info',"full synchro to $url");
      $file64=implode(file($url."tzr/scripts/admin.php?class=\Seolan\Module\Replication\Replication&function=getbaselist"));
      $baselist=explode(';',base64_decode($file64));
      foreach($baselist as $i => $b) {
        \Seolan\Core\Logs::notice('repli-info',"full synchro to $url for table $b");
        $file64=implode(file($url."tzr/scripts/admin.php?class=\Seolan\Module\Replication\Replication&function=getinitset&table=$b"));
      }
    }
    $rs->closeCursor();
  }
  /// rend la liste des tables
  public function getbaselist($ar) {
    $r = \Seolan\Core\DataSource\DataSource::getBaseList();
    $l=array_keys($r);
    $content = base64_encode(implode(";",$l));
    header('Content-type: text/plain');
    echo chunk_split($content);
    exit();
  }
  /// Ecriture des entrées mémorisées dans la table JOURNAL
  public function sendToJournal() {
    if(!empty($this->_journal)) {
      $chro=self::newChrono();
      $sth = NULL;
      foreach($this->_journal as $i => $line) {
        getDB()->execute("INSERT INTO JOURNAL (RQ,EX,CHRONO) values('$line','1','$chro')",array(),false);
        $chro++;
      }
      $this->_journal=array();
    }
  }
  //BDOCm* \Seolan\Module\Replication\Replication/journalize
  // NAME
  //   \Seolan\Module\Replication\Replication::journalize -- journalisation des actions
  // DESCRIPTION
  // Cette fonction permet de conserver la trace des actions réalisées
  // sur les données et la structure de l'instance du serveur.
  // INPUTS
  // type - donne le type de modification des données
  // rq - donne le contenu de la modification
  // SYNOPSIS
  // si type vaut sql, alors rq est une requête sql qui doit être
  // examinée pour journalisation. Les règles de journalisation sont: si
  // la requête est un select, elle n'est pas conservée ; si la requête
  // est préfixée par / *-X* /, elle n'est pas journalisée. Toutes les
  // autres requêtes sont journalisées.
  // Si type vaut upd, il s'agit de la mise à jour ou de la création
  // d'un fichier de données. Si type vaut del, il s'agit de la
  // suppression d'un fichier, et rq est le nom du fichier.
  //EDOC
  public function journalize($type, $rq) {
    \Seolan\Core\Logs::debug('repli-info -> '.$type.' '.$rq);
    if(!\Seolan\Core\System::tableExists('JOURNAL')||!\Seolan\Core\System::tableExists('REPLI')) return false;
    if(empty($this->enable) || $this->suspended) return false;
    if (empty($type))
      return false;
    if($type=="sql") {
      if(0==strncasecmp("SELECT",$rq,6)) return false;
      if(0==strncasecmp("/*-X*/",$rq,6)) return false;
      $r = preg_match('/ *(update) +(low_priority |ignore ){0,2} *([a-z0-9._-]+) +set/i', $rq, $res);
      if ($r && !empty($res[3]) && !$this->replicableTable($res[3]))
        return false;
      $r = preg_match('/^ *(insert|replace) +(low_priority |delayed |ignore ){0,2} *(into){0,1} *([a-z0-9._-]+).*/i', $rq, $res);
      if ($r && !empty($res[4]) && !$this->replicableTable($res[4]))
        return false;
      $r = preg_match('/^ *(delete) +(low_priority |quick |ignore ){0,3} *(from) *([a-z0-9._-]+).*/i', $rq, $res);
      if ($r && !empty($res[4]) && !$this->replicableTable($res[4]))
        return false;
      $r = preg_match('/^ *(truncate) +(low_priority |quick |ignore ){0,3} *([A-Za-z0-9._-]+).*/i', $rq, $res);
      if ($r && !empty($res[3]) && !$this->replicableTable($res[3]))
        return false;
      $rq=addslashes($rq);
    }
    if($type=="upd") {
      $filesize=filesize($rq);
      $rq=str_replace($GLOBALS["DATA_DIR"],"",$rq);
      list( $btab, $fn) = explode('/', $rq);
      if (!$this->replicableTable($btab))
        return false;
      $rq=$filesize.":".$rq;
    }
    if($type=="del") {
      $rq=str_replace($GLOBALS["DATA_DIR"],"",$rq);
      list( $btab, $fn) = explode('/', $rq);
      if (!$this->replicableTable($btab))
        return false;
    }
    if(!empty($rq)) {
      $this->_journal[]=$type.":".$rq;
    }
    return true;
  }
  /// vérifie qu'un serveur (client) à le droit aux données
  protected function checkServer($infos, $rights){
    $trepli = '(\''.implode('\',\'', $rights).'\')';
    if ($this->identifybyHostName)
      $rs = getDB()->select('select KOID from REPLI where serv = \''.$infos['hostname'].'\' and trepli in '.$trepli);
     else 
      $rs=getDB()->select('select KOID from REPLI where ip = \''.$infos['ip'].'\' and trepli in '.$trepli);
    
    if ($rs->rowCount() == 0)
      return false;
  
    $ors = $rs->fetch();

    return $ors['KOID'];

  }
  /// retourne les paramètres d'un server (ou client)
  protected function getServerInfos($oid){
    $rs = getDB()->select('select * from REPLI where KOID = \''.$oid.'\'');
    if ($rs->rowCount() == 0)
      return NULL;

    $ors = $rs->fetch();

    if ($this->identifybyHostName)
      $ors['_ident']=$ors['serv'];
    else 
      $ors['_ident']=$ors['ip'];

    return $ors;
  }
  //BDOCm* \Seolan\Module\Replication\Replication/getchangeset
  // NAME
  //   \Seolan\Module\Replication\Replication::getchangeset -- rend le différentiel par rapport à la dernière réplication
  // DESCRIPTION
  // La fonction rend les opérations différentielles à réaliser depuis la dernière synchro
  // INPUTS
  // SYNOPSIS
  //EDOC
  public function getchangeset($ar) {
    $p = new \Seolan\Core\Param($ar, array('mode'=>'soap', 'view'=>0));
    $hostname = $p->get('hostname');
    $ip = $_SERVER['REMOTE_ADDR'];
    $mode = $p->get('mode');
    $view = $p->get('view');

    // vérification que ce serveur a les droits de venir chercher des infos
    $servoid = $this->checkServer(array('ip'=>$ip, 'hostname'=>$hostname), array('full', 'slave'));
    if($servoid == false) {
      \Seolan\Core\Logs::critical('repli-error', 'unknown ip or master configuration: '.$ip.' '.$hostname);
      if ($mode == 'soap'){
        return array('mess'=>'unknown ip or master configuration: '.$ip.' '.$hostname, 'data'=>NULL);
      }
      die();
    }
    $sinfos = $this->getServerInfos($servoid);
    $lastchrono = \Seolan\Core\DbIni::getStatic('replication:'.$sinfos['_ident'].':chrono','val');
    if (empty($lastchrono))
      $lastchrono = -1;
    $newstatus = 'GCSet : '.$lastchrono;
    if (!$view)
      getDB()->execute('update REPLI set status=\''.$newstatus.'\' where KOID=\''.$sinfos['KOID'].'\'');
    \Seolan\Core\Logs::notice('repli-info',"chrono $lastchrono");
    if ($lastchrono == 'forceInitset')
      return array('mess' => 'forceInitset', 'trepli' => $sinfos['trepli']);

    $rs=getDB()->select("select * from JOURNAL where CHRONO > $lastchrono order by CHRONO limit ".$this->packetSize);
  
    $rsm=getDB()->fetchAll("select max(CHRONO) as maxchrono from JOURNAL");
    $a=array();
    $i=0;
    $pfirst = $plast = NULL;
    while(($o=$rs->fetch()) && ($i<$this->packetSize)) {
      if (empty($pfirst))
        $pfirst = $o['CHRONO'];
      $plast = $o['CHRONO'];
      $a[]=array("UPD"=>$o['UPD'],"RQ"=>$o['RQ'],"CHRONO"=>$o['CHRONO']);
      $i++;
    }
    $z = serialize($a);
    $rs->closeCursor();
    self::lognojournal('getchangeset', $servoid, $hostname.',view ='.$view.',firstChrono = '.$pfirst.',lastChrono = '.$plast.', maxChrono = '.$rsm[0]['maxchrono'], NULL, true);
    if ($mode == 'soap'){
        return array('mess'=>'ok', 'data'=>$z, 'firstChrono'=>$pfirst, 'lastChrono'=>$plast, 'maxChrono'=>$rsm[0]['maxchrono'] );
    }
  }
  /// récupération d'un jeu d'initialisation complet
  /// depuis le serveur donné
  public function getinitset($ar){
    $p = new \Seolan\Core\Param($ar, array('mode'=>'soap', 'view'=>0));
    $ip = $_SERVER['REMOTE_ADDR'];
    $hostname = $p->get('hostname');
    $mode = $p->get('mode');  // soap, file
    $view = $p->get('view');
    // vérification que ce serveur a les droits de venir chercher des infos
    $servoid = $this->checkServer(array('ip'=>$ip, 'hostname'=>$hostname), array('full', 'slave'));
    if($servoid == false) {
      if ($mode == 'soap'){
        return array('mess'=>'Client non identifiable', 'data'=>NULL);
      }
      die('nok');
    }
    $sinfos = $this->getServerInfos($servoid);

    self::lognojournal('getinitset', $servoid, $hostname.',view ='.$view.', mode = '.$mode, NULL);

    ini_set('max_execution_time', 600);
    set_time_limit (600);
    
    /// lecture des data à reprendre
    /// les fichiers pour chaque table à repliquer - si mode file on fait juste un tar
    if ($mode == 'soap'){
      /// recuperation de la liste des externals des tables repliquées dans la table TMP_DATA
      $sqldata = "\n--\n-- LISTE DES DATA A REPRENDRE \n--";
      $sqldata .= "\nDROP TABLE IF EXISTS TMP_DATA;";
      $sqldata .= "\nCREATE TABLE TMP_DATA (dtab varchar(64), dfield varchar(64), ddata varchar(128));";
    } else {
      $extfiles = " ";
    }
    
    $tables = getMetaTables();
    foreach($tables as $tableentry){
        $atable=$tableentry['table'];
        if (!$this->replicableTable($atable))
            continue;
        if (!\Seolan\Core\DataSource\DataSource::sourceExists($atable))
            continue;
        // la table est replicable
        $q = "select * from $atable";
        $ds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$atable);
        $fields = array();
        foreach($ds->desc as $fn=>$fo){
            if ($fo->hasExternals())
                $fields[] = $fn;
        }
        if (count($fields) == 0)
            continue;
        // verifier données publiées ?

        // recuperation de chaque nom de fichier pour chaque champ
        unset($browse);
        $browse=$ds->browse(array("select"=>$q, "selectedfields"=>$fields,"tplentry"=>TZR_RETURN_DATA,"pagesize"=>"999999"));
        \Seolan\Core\Logs::notice('repli_info', 'scan externals for table '.$atable.'');
        foreach($fields as $i => $f1) {
            $f1o=$ds->desc[$f1];
            if($f1o->hasExternals()) {
                \Seolan\Core\Logs::notice('repli-info',"field $f1 has files");
                foreach($browse['lines_oid'] as $j => $oid) {
                    \Seolan\Core\Logs::notice('repli_info', 'scan externals for oid '.$oid.' value = '.$browse['lines_o'.$f1][$j]->raw);
                    // on recherche la liste des fichiers a repliquer
                    $ex=$f1o->externals($browse['lines_o'.$f1][$j]->raw);
                    if(!empty($ex)) {
                        foreach($ex as $v1i => $f1i) {
                            \Seolan\Core\Logs::notice('repli-info', 'inserting upd query for oid'.$oid.' '.$f1i);
                            // journalisation du transfert de fichier
                            if ($mode == 'soap')
                              $sqldata .= "\nINSERT INTO TMP_DATA (dtab, dfield, ddata) values('$atable', '{$f1o->field}', '$f1i');";
                            elseif ($mode == 'file'){
                              $extfiles .= "$f1i ";
                            }
            
                        }
                    } else {
                        \Seolan\Core\Logs::notice('repli', 'no file for oid'.$oid);
                    }
                }
            }
        }

    }
    /// to do : lock 

    /// lecture du dernier chrono pour acquittement à réception
    $newchrono = getDB()->select('select ifnull(max(chrono), 0) as newchrono from JOURNAL')->fetch(\PDO::FETCH_COLUMN);

    /// récupération de toutes les strutures (ddl)
    $fullddl=TZR_TMP_DIR.uniqid().'full.ddl.sql';
    \Seolan\Module\Management\Management::createSQLDump(array('file'=>$fullddl,'no_data'=>true));

    /// récupération du dictionaire complet et des données répliquées
    $datafile=TZR_TMP_DIR.uniqid().'data.sql';
    /// récupération de toutes les données replicables
    $tables = getMetaTables();
    $tablelist = $this->systtables;
    foreach($tables as $tableentry){
      $atable=$tableentry['table'];
      if (in_array($atable, $tablelist))
        continue;
      if ($this->replicableTable($atable))
        $tablelist[] = $atable;
    }
    \Seolan\Module\Management\Management::createSQLDump(array('file'=>$datafile,'no_create'=>true,'tables'=>$tablelist));

    /// ajout du dernier numéro de chrono qui sera acquité à réception
    $sql  = "\n--\n-- NOUVEAU CHRONO À PRENDRE EN COMPTE\n--";
    $sql .= "\nDROP TABLE IF EXISTS TMP_NEWCHRONO;";
    $sql .= "\nCREATE TABLE TMP_NEWCHRONO (CHRONO bigint);";
    $sql .= "\nINSERT INTO TMP_NEWCHRONO (CHRONO) values ($newchrono);";
    /// ajout des variables statics à passer
    $sql  .= "\n--\n-- _STATICVARS À PRENDRE EN COMPTE\n--";
    $sql .= "\nDROP TABLE IF EXISTS TMP_STATICVARS;";
    $sql .= "\nCREATE TABLE TMP_STATICVARS (UPD timestamp, name varchar(120), value text);";
    $sql .= "\nINSERT INTO TMP_STATICVARS values(now(), 'upgrades', '".\Seolan\Core\DbIni::getStatic('upgrades','raw')."');";
    
    /// écriture des contenus dans le flux en retour
    $all = $sql."\n--\n-- DDL \n--\n".file_get_contents($fullddl)."\n--\n-- DATA\n--\n-- ".implode(' ',$tablelist)."\n".file_get_contents($datafile)."\n".$sqldata;
    unlink($fullddl);
    unlink($datafile);
    if ($mode == 'soap'){
      return array('mess'=>'ok', 'data'=>$all);
    } elseif ($mode == 'file'){
      // partie texte + sql
      $tmpdir = TZR_TMP_DIR.uniqid();
      mkdir($tmpdir);
      $sqlfile = $tmpdir.'/dump_sql.sql';
      file_put_contents($sqlfile, $all);
      $exttarfile = $tmpdir.'/data_ext.tgz';
      system("(cd {$GLOBALS['TZR_WWW_DIR']}data; tar --exclude=*-cache -czf $exttarfile $extfiles )");
      $tmpname = TZR_TMP_DIR.'initset_'.$hostname.('_').uniqid().'.data';
      system("(cd $tmpdir;tar -cf $tmpname dump_sql.sql data_ext.tgz)");
      system('gzip '.$tmpname);
      system("rm -rf $tmpdir");
      // ajout d'un jeton ... 
      \Seolan\Core\DbIni::setStatic('repli::initset::'.$hostname, $tmpname.'.gz');
      // on retourne l'url pour lire le fichier
      $file = md5($tmpname.'.gz');
      $url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true)."&moid={$this->_moid}&hostname=$hostname&function=downloadInitSetFile&file=$file";
      return array('mess'=>'ok', 'data'=>$url);
    }
  }
  /// recuperation d'un fichier initset
  function downloadInitSetFile($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $hostname = $p->get('hostname');
    $file = $p->get('file');
    $rs = getDB()->select("select value from _STATICVARS where name='repli::initset::$hostname' and md5(value)='$file'");
    if (!$rs || $rs->rowCount() != 1){
      header("HTTP/1.0 404 Not Found");
      exit(0);
    } else {
      $ors = $rs->fetch();
      $size = @filesize($ors['value']);
      $mime = '';
      header("Content-type: $mime");
      header("Content-Length: $size");
      @readfile($ors['value']);
      unlink($ors['value']);
    }
  }
  /// acquittement d'un chrono sur le serveur distant
  protected function ackChrono($client, $chrono, $sinfos){
    \Seolan\Core\Logs::debug('repli-info : synchro packet '.$chrono);
    $ret = $client->soapclient->wsyncAck(array('sessid'=>$client->sessid), array('hostname'=>$sinfos['hostname'], 'chrono'=>$chrono));
    if ($ret->mess != 'ok'){
      \Seolan\Core\Logs::critical('repli-error', 'synchro packet error: '.$chrono.' : '.$ret->mess);
      return false;
    }
    return true;
  }
  /// traitement d'une demande d'acquittement par un client
  public function syncAck($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array('mode'=>'soap'));
    $chrono=$p->get("chrono");
    $ip = $_SERVER['REMOTE_ADDR'];
    $hostname = $p->get('hostname');
    $mode = $p->get('mode');
    // vérification que ce serveur a les droits de venir chercher des infos
    $servoid = $this->checkServer(array('ip'=>$ip,'hostname'=>$hostname), array('full', 'slave'));
    if ($servoid == false){
      if ($mode == 'soap'){
        return array('mess'=>'Unknown client ', 'data'=>NULL);
      }
      exit();
    }
    $sinfos = $this->getServerInfos($servoid);

    $oldchrono=\Seolan\Core\DbIni::getStatic('replication:'.$sinfos['_ident'].':chrono','val');
    if (empty($oldchrono) || $oldchrono == 'forceInitset' || ($oldchrono <= $chrono)) {
      \Seolan\Core\DbIni::setStatic('replication:'.$sinfos['_ident'].':chrono',$chrono);
      $this->updateStatus(array('lastackchrono'=>$chrono), $sinfos);
      self::lognojournal('syncAck', $servoid, $chrono, NULL, true);
    } else {
      if ($mode == 'soap'){
        return array('mess'=>"$ip $hostname tried to ack $chrono, cannot get back in time now is $oldchrono", 'data'=>NULL);
      }
      \Seolan\Core\Logs::notice('repli-error',"$ip $hostname tried to ack $chrono, cannot get back in time now is $oldchrono");
    }
    if ($mode == 'soap')
      return array('mess'=>'ok', 'data'=>NULL);
    exit();
  }
  /// mise à jour du status d'une entree de replication
  /// dernier chrono acquité +/- completion atteinte
  private function updateStatus($ar, $ors){
    if (isset($ar['lastackchrono'])){
      $upd = date('Y-m-d H:i:s');
      $u = "update REPLI set dtack='{$upd}', chrack = '{$ar['lastackchrono']}' where KOID='{$ors['KOID']}'";
      $lj = getDB()->fetchOne("select ifnull(max(chrono), 0) as mc from JOURNAL;");
      if ($ar['lastackchrono'] >= $lj){
        $u = "update REPLI set status='', dtcompl='{$upd}', dtack='{$upd}', chrack = '{$ar['lastackchrono']}' where KOID='{$ors['KOID']}'";;
      } 
      getDB()->execute($u,array(),false);
    }
  }
  /// est ce que la table doit être repliquée ou pas
  function replicableTable($btab){
      return !isset($this->notToReplicateTables[$btab]);
  }
  /// mise à jour de puis la page etat des tables
  /// du statut replication de la table
  function procEditTableStatus($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $btab = $p->get('btab');
    $newstatus = $p->get('newstatus');
    
    $cnt = getDB()->count("select COUNT(*) from BASEBASE where btab='$btab'");
    if ($cnt == 1){
      \Seolan\Core\Logs::notice('repli-info', "procEditTableStatus $btab => $newstatus");
      getDB()->execute("update BASEBASE set NOTTOREPLI=$newstatus where btab='$btab'");
    } else {
      // table inconnue ...
      \Seolan\Core\Logs::critical('repli-error', "procEditTableStatus $btab not found in BASEBASE");
    }
    \Seolan\Core\Shell::setNext($p->get('_next'));
  }
  /// liste des tables et de leur status replication
  /// -> le status est dans BASEBASE +/- status de replication des modules qui utilisent 
  /// la table
  public function tablesStatus($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $tplentry = $p->get('tplentry');
    $tlist = \Seolan\Core\DataSource\DataSource::getBaseList();
    $tlistyes = array();
    $tlistno = array();
    foreach($tlist as $tname=>$tlabel){
      // lecture du statut actuel dans BASEBASE
      $rs = getDB()->select("select * from BASEBASE where btab='{$tname}'");
      $ors = $rs->fetch();
      if((isset($ors['NOTTOREPLI']) && $ors['NOTTOREPLI'] == 1) || (isset($this->notToReplicateTables[$tname]) && $this->notToReplicateTables[$tname] == 1))
        $bbrepli = false;
      else
        $bbrepli = true;
      unset($ors);
      $rs->closeCursor();
      unset($rs);
      $rs = getDB()->select("select ifnull(count(*), 0) as nb from $tname");
      if (!$ors = $rs->fetch()){
        \Seolan\Core\Logs::critical('repli-error', "unknown table $tname");
      }
      $tbcount = $ors['nb'];
      unset($ors);
      $rs->closeCursor();
      unset($rs);
      // calcul du statut à partir des modules
      $moids = \Seolan\Core\Module\Module::modulesUsingTable($tname, false, false, false);
      $moids2 = array();
      if (count($moids)>0){
        $treplicate = false;
        foreach($moids as $moid=>$modname){
          $mod = \Seolan\Core\Module\Module::objectFactory($moid);
          if ($mod->replicate){
            $treplicate = true;
            $moid2 = array('moid'=>$moid, 'name'=>$modname, 'replicate'=>true);
          }else{
            $moid2 = array('moid'=>$moid, 'name'=>$modname, 'replicate'=>false);
          }
          $moids2[$moid] = $moid2;
        }
        if ($treplicate){
          $tlistyes[$tname] = array('count'=>$tbcount, 'bbrepli'=>$bbrepli, 'replicate'=>true, 'modules'=>$moids2, 'table'=>array('tname'=>$tname, 'tlabel'=>$tlabel));
        }else{
          $tlistno[$tname] = array('count'=>$tbcount, 'bbrepli'=>$bbrepli, 'replicate'=>false, 'modules'=>$moids2, 'table'=>array('tname'=>$tname, 'tlabel'=>$tlabel));
        }
      } else {
        $tlistyes[$tname] = array('count'=>$tbcount, 'bbrepli'=>$bbrepli, 'replicate'=>true, 'modules'=>$moids2, 'table'=>array('tname'=>$tname, 'tlabel'=>$tlabel));
      }
    }
    unset($tlist);
    if ($p->is_set('order')){
      foreach($tlistyes as $k=>$v){
        $bnamesy[$k]=$k;
        $bcountsy[$k]=$v['count'];
        $blabelsy[$k]=$v['table']['tlabel'];
        $breplisy[$k]=$v['bbrepli'];
      }
      foreach($tlistno as $k=>$v){
        $bnamesn[$k]=$k;
        $bcountsn[$k]=$v['count'];
        $blabelsn[$k]=$v['table']['tlabel'];
        $breplisn[$k]=$v['bbrepli'];
      }
      $order = $p->get('order');
      if ($order=='brepli'){
        array_multisort($breplisy, SORT_DESC, $tlistyes);
        array_multisort($breplisn, SORT_DESC, $tlistno);
      }
      if ($order=='bname'){
        array_multisort($bnamesy, SORT_ASC, $tlistyes);
        array_multisort($bnamesn, SORT_ASC, $tlistno);
      }
      if ($order=='blabel'){
        array_multisort($blabelsy, SORT_ASC, $tlistyes);
        array_multisort($blabelsn, SORT_ASC, $tlistno);
      }
      if ($order=='bcount'){
        array_multisort($bcountsy, SORT_DESC, $tlistyes);
        array_multisort($bcountsn, SORT_DESC, $tlistno);
      }
    }
    $res = array('linesyes'=>$tlistyes, 'linesno'=>$tlistno);
    if ($tplentry == TZR_RETURN_DATA){
      return $res;
    } else {
      return \Seolan\Core\Shell::toScreen1($tplentry, $res);
    }
  }
  /// synchronisation depuis toutes les sources définies
  public function synchronize($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array());
    $rs=getDB()->select("select * from REPLI where trepli='full' OR trepli='master'");
    while($ors=$rs->fetch()) {
      $this->synchronizeServer($ors);
    }
    $rs->closeCursor();
  }
  /// instance de client soap pour une source donnée
  protected function getSoapClient($ors){
    // ? default_socket_timeout
    ini_set('default_socket_timeout', $this->connectionTimeOut);
    if (!isset($this->soapclients[$ors['KOID']])){
      list ($userpassword, $wsdl) = explode('@', $ors['async']);
      list ($user, $password) = explode(':', $userpassword);
      try{
        if(!@file_get_contents($wsdl)) {
          throw new \SoapFault('Server', 'No WSDL found at ' . $wsdl);
        }
        $client = new \SoapClient($wsdl,array('exceptions'=>true, 'compression' => SOAP_COMPRESSION_ACCEPT, 'connection_timeout'=>$this->connectionTimeOut));
        $sessid = $client->auth(array('login'=>$user,'password'=>$password));
        $this->soapclients[$ors['KOID']] = (object)array('soapclient'=>$client, 'sessid'=>$sessid, 'hostname'=>$ors['hostname']);
      } catch(\SoapFault $e){
        \Seolan\Core\Logs::critical('repli-error', 'Erreur connexion soap '.$e->getMessage());
        throw $e;
      }
    }
    return $this->soapclients[$ors['KOID']];
  }
  /// synchronisation depuis une source donnée
  public function synchronizeServer($ors){
    
    $sinfos = $this->getServerInfos($ors['KOID']);
    if (!isset($ors['_interactive_mode']) && $this->synchroRunning($ors['KOID'])){
      \Seolan\Core\Logs::notice('repli-info', 'synchronizeServer autre synchro en cours');
      $this->lognojournal('synchronizeServer', $ors['KOID'], 'autre synchro en cours', NULL);
      return;
    }
    // lecture du prochain paquet à traiter
    try{
      $client = $this->getSoapClient($sinfos);
      $ret = $client->soapclient->wgetChangeSet(array('sessid'=>$client->sessid), array('hostname'=>$client->hostname, 'view'=>0));
      if ($ret->mess == 'forceInitset') {
        \Seolan\Core\Logs::notice('repli-info', 'synchronizeServer need reset');
        $this->unlockSynchro($ors['KOID']);
        $lastChrono = getDB()->select('select max(CHRONO) from JOURNAL')->fetch(\PDO::FETCH_COLUMN);
        // si le distant est full (et que le local n'est pas slave sur le distant), on vérifie qu'il a pris tout les changements locaux
        if ($lastChrono == $ors['chrack'] || $ors['trepli'] == 'master' || $ret->trepli == 'slave')
          return $this->applyinitset(array('oid' => $ors['KOID']));
        else
          return \Seolan\Core\Logs::notice('repli-info', 'synchronizeServer waiting completion');
      } elseif ($ret->mess == 'ok'){
        $file = unserialize($ret->data);
        \Seolan\Core\Logs::notice('repli-info', "synchronizeServer lastChrono {$ret->lastChrono} firstChronno {$ret->firstChrono} maxChrono {$ret->maxChrono}");
      } else {
        \Seolan\Core\Logs::critical('repli-error', 'synchronizeServer error : '.$ret->mess);
        return;
      }
    }catch(\SoapFault $e){
      \Seolan\Core\Logs::critical('repli-error', 'Erreur soap wgetmessage '.$e->getMessage());
      return;
    }
    // traitement du paquet
    if(!is_array($file)) {
      \Seolan\Core\Logs::critical('repli-error',var_export($file,true));
      return;
    } else {
      \Seolan\Core\Logs::debug('repli-info '.var_export($file,true));
    }
    self::lognojournal('synchronizeServer', $ors['KOID'], "lastChrono {$ret->lastChrono} firstChronno {$ret->firstChrono} maxChrono {$ret->maxChrono}", NULL, true);
    foreach($file as $li=>$oo) {
      \Seolan\Core\Logs::debug('repli-info: '.var_export($oo,true));
      if(!$this->doChange($oo, $client)){
        // peut générer une erreur si on est au premier du paquet
        $error = getDB()->errorInfo();
        \Seolan\Core\Logs::critical('repli-error','synchronizeServer doChange error : '.$oo['CHRONO'].' '.$oo['RQ'].' => '.$error[2]);
        $this->lognojournal('doChange error', $ors['KOID'], 'doChange error '.$oo['CHRONO'].' '.$oo['RQ'].' => '.$error[2], NULL); // log local avec l'objet REPLI
        \Seolan\Core\Logs::update('doChange error', NULL, 'on '.$sinfos['hostname'].' '.$oo['CHRONO'].' '.$oo['RQ'].' => '.$error[2]); // log repliqué, host dans le comment
      }
      // accuse de reception de la mise a jour
      
      if (!$this->packetack){
        $this->ackChrono($client, $oo['CHRONO'], $sinfos);
      }
    }
    
    if ($this->packetack){
      $this->ackChrono($client, $oo['CHRONO'],$sinfos);
    }
    return;
  }
  
  /// traitement d'une mise à jour de replication
  private function doChange($a, $client) {
    list($protocol,$command)=explode(':',$a['RQ'],2);
    if($protocol=='sql') {
      if(empty($command) || ($command[0]=='-')) return true;
      $ret = getDB()->execute($command,array(),false);
      if ($ret === false){
        \Seolan\Core\Logs::critical('repli error',$a['UPD'].':'.$a['CHRONO'].':'.$command);
        return false;
      } else {
        \Seolan\Core\Logs::notice('repli-info',$a['UPD'].':'.$a['CHRONO'].':'.$command);
        return true;
      }
    }
    if($protocol=='upd') {
      // on recupère la taille du fichier attendu
      list($fsize,$fname)=explode(':',$command);
      $command=$fname;

      // réception d'un fichier
      // construction du répertoire
      \Seolan\Library\Dir::mkdir($GLOBALS['DATA_DIR'].$fname,true);
      $tmpfile=TZR_TMP_DIR.uniqid();
      $ret = $client->soapclient->wgetFile(array('sessid'=>$client->sessid), array('hostname'=>$client->hostname, 'filename'=>$command));
      if ($ret->mess != 'ok'){
        \Seolan\Core\Logs::critical('repli error getfile',$a['UPD'].':'.$a['CHRONO'].' '.$ret->mess);
        return false;
      }
      $myfile = $GLOBALS['DATA_DIR'].$command;
      if (file_exists($myfile)){
        $mtime = filemtime($myfile);
        if ($mtime>$ret->mtime){
          \Seolan\Core\Logs::notice('repli warning', $a['UPD'].':'.$a['CHRONO'].' latest local file  '.$GLOBALS['DATA_DIR'].$command);
          return true;
        }
      }
      file_put_contents($tmpfile, base64_decode($ret->data));
      $bytes=filesize($tmpfile);
      if($bytes>0 && $bytes == $ret->bytes) {
        copy($tmpfile, $GLOBALS['DATA_DIR'].$command);
        unlink($tmpfile);
        \Seolan\Core\Logs::notice('repli-ok',$a['UPD'].':'.$a['CHRONO'].' -> '.$GLOBALS['DATA_DIR'].$command);
        return true;
      } else {
        \Seolan\Core\Logs::critical('repli-error ','empty file or size error '.$ret->bytes.'/'.$bytes.' '.$a['CHRONO'].' -> '.$GLOBALS['DATA_DIR'].$command);
        unlink($tmpfile);
        return false;
      }
    }
    if($protocol=='del') {
      \Seolan\Core\Logs::notice('repli-ok',$a['UPD'].':'.$a['CHRONO'].':del'.$command);
      \Seolan\Core\Logs::debug("[\Seolan\Module\Replication\Replication::doChange]unlink(".$GLOBALS['DATA_DIR'].$command.")");
      @unlink($GLOBALS['DATA_DIR'].$command);
      return true;
    }
    return true;
  }
  /// creation de la structure de la table de replication
  static function createRepli() {
    if(!\Seolan\Core\System::tableExists('REPLI')) {
      $lg = TZR_DEFAULT_LANG;
      $ar1["translatable"]="0";
      $ar1["auto_translate"]="0";
      $ar1["btab"]='REPLI';
      $ar1["bname"][$lg]='System - '.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Replication_Replication','modulename');
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'REPLI');
      //                                                                       ord obl que bro tra mul pub tar
      $x->createField('serv','Serveur','\Seolan\Field\Text\Text',                         '60','3', '1','1','1','0','0','1');
      $x->createField('ip','Serveur (ip)','\Seolan\Field\ShortText\ShortText',                 '20','4', '1','1','1','0','0','0');
      $x->createField('status','Status','\Seolan\Field\ShortText\ShortText',                   '20','5', '0','1','1','0','0','0');
      $x->createField('async','Url de Synchro','\Seolan\Field\Text\Text',                 '60','6', '0','1','0','0','0','0');
      $x->createField('hostname','Nom de connexion distant','\Seolan\Field\ShortText\ShortText',    '60','7', '0','1','0','0','0','0');
      $x->createField('trepli','Type','\Seolan\Field\ShortText\ShortText',                     '20','8', '0','1','0','0','0','0');
      $x->createField('dtack', 'Dernier acquittement', '\Seolan\Field\DateTime\DateTime',      '20','9', '0','1','0','0','0','0');
      $x->createField('chrack', 'Dernier chrono', '\Seolan\Field\Real\Real',              '20','10', '0','1','0','0','0','0');
      $x->createField('dtcompl', 'Dernière complétion', '\Seolan\Field\DateTime\DateTime',    '20','11','0','1','0','0','0','0');
    }
    if(!\Seolan\Core\System::tableExists('JOURNAL')) {
      getDB()->execute("CREATE TABLE JOURNAL (  UPD TIMESTAMP NOT NULL,  PRIO int(11) default '1',  ".
		  "EX tinyint(4) NOT NULL default '1',  RQ text, CHRONO bigint) ;",array(),false);
    }
  }
  /// jobs periodique : la replication est executee pendant le demon
  protected function _daemon($period='any') {
    parent::_daemon($period);
    \Seolan\Core\Logs::notice('repli','lauching synchro');
    $GLOBALS['XREPLI']->synchronize();
    $GLOBALS['XREPLI']->checkCompletion();
    if ($period == 'daily'){
      $this->purgeInitSet();
      $this->purgeJournal();
      $this->purgeLogs();
    }
  }
  /// liste des actions accessibles en mode interactif
  protected function _actionlist(&$my, $alfunction=true){
    parent::_actionlist($my);
    $moid = $this->_moid;
    $title = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Replication_Replication','tablesadmin','text');
    $o1=new \Seolan\Core\Module\Action($this,'admintables',$title,
			  '&amp;moid='.$moid.
			  '&amp;_function=tablesStatus&amp;template=Module/Replication.tablesstatus.html&amp;tplentry=br',
			  'display');
    $o1->homepageable=$o1->menuable= true;
    $o1->group='actions';
    $my['admintables']=$o1;
  }
  function al_display(&$my) {
    parent::al_display($my);
    $oid = $_REQUEST['oid'];
    if ($this->secure($myoid, 'showChangeSet')) {
    }
      $o1=new \Seolan\Core\Module\Action($this, 'showChangeSet', 'Manipuler les changesets',
              '&amp;moid='.$this->_moid.
              '&amp;_function=preShowChangeSet;template=Module/Replication.showChangeSet.html&amp;tplentry=br&oid='.$oid,
              'edit');
      $o1->homepageable=$o1->menuable= true;
      $o1->group='edit';
      $my['showChangeSet']=$o1;
    if ($this->secure($myoid, 'forceInitset')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'forceInitset', 'Forcer un reset',
                            '&moid='.$this->_moid.'&function=forceInitset&oid='.$oid, 'edit');
      $o1->menuable = true;
      $my['forceInitset'] = $o1;
    }
  }
  
  /// affichage ecran info + saisie eventuelle chrono pour voir un changeset
  function preShowChangeSet($ar){
      $r = $this->display($ar);
      $sinfos = array();
      foreach($this->xset->desc as $fn=>$fo)
        $sinfos[$fn]=$r['o'.$fn]->raw;
      \Seolan\Core\Shell::toScreen2('', 'url', $foo=$r['oasync']->raw);
      return $r;
  }
  /// montre un set d'initialisation
  function showInitSet($ar){
    $ar['tplentry']='br';
    $r = $this->preShowChangeSet($ar);

    $sinfos = $this->getServerInfos($r['oid']);
    try{
      $client = $this->getSoapClient($sinfos);
      $ret = $client->soapclient->wgetInitSet(array('sessid'=>$client->sessid), array('hostname'=>$client->hostname, 'view'=>1));
    } catch(\SoapFault $e){
      $_REQUEST['message'] = $e->getMessage();
      return;
    }
    $file = NULL;
    if ($ret->mess == 'ok'){
      $file = $ret->data;
      // a voir $file = str_replace(array('>', '<'), array('&gt;', '&lt;'), $file);
      \Seolan\Core\Shell::toScreen2('', 'initset', $file);
      
    } else {
      $_REQUEST['message'] = $ret->mess;
    }
    return;
  }
  /// montre un changeset - on peut demande à partir d'un chrono donné
  /// si chrono, dernier acquittement n'est pas pris en compte
  function showChangeSet($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $ar['tplentry']='br';
    $r = $this->preShowChangeSet($ar);
    $sinfos = $this->getServerInfos($r['oid']);
    try{
      $client = $this->getSoapClient($sinfos);
      $ret = $client->soapclient->wgetChangeSet(array('sessid'=>$client->sessid), array('hostname'=>$client->hostname, 'view'=>1));
      if ($ret->mess == 'ok'){
        $file = unserialize($ret->data);
        // a voir $file = str_replace(array('>', '<'), array('&gt;', '&lt;'), $file);
        \Seolan\Core\Shell::toScreen2('', 'changeset', $file);
        $_REQUEST['message'] = "lastCrhono {$ret->lastChrono} firstChronno {$ret->firstChrono} maxChrono {$ret->maxChrono}";
      } else {
        $_REQUEST['message'] = $ret->mess;
      }
    }catch(\SoapFault $e){
      $_REQUEST['message'] = 'Erreur accès serveur '.$e->getMessage();
      \Seolan\Core\Logs::critical('repli-error', 'Erreur soap wgetmessage '.$e->getMessage());
    }
    return;
  }
  /// applique un changeset 
  function applyChangeSet($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $oid = $p->get('oid');
    $rs=getDB()->select("select * from REPLI where KOID='$oid' and trepli='full' OR trepli='master'");
    $ors = $rs->fetch();
    $ors['_interactive_mode'] = 1;
    $this->synchronizeServer($ors);
    $rs->closeCursor();
    \Seolan\Core\Shell::setNext($this->getMainAction().'&amp;message=done');
  }
  /// applique un jeu d'initialisation
  function applyinitset($ar){

    $p = new \Seolan\Core\Param($ar, array('tplentry'=>TZR_RETURN_DATA));
    $tplentry = $p->get('tplentry');

    $oid = $p->get('oid');
    
    $sinfos = $this->getServerInfos($oid);

    if ($this->synchroRunning($oid)){
      \Seolan\Core\Logs::notice('repli-info', 'applyinitset synchro en cours');
      $this->lognojournal('applyinitset', $oid, 'synchro en cours', NULL);
      if ($tplentry == TZR_RETURN_DATA)
        return 'synchro en cours';
      $_REQUEST['message'] = 'synchro en cours';
      return;
    }
    register_shutdown_function(array($this, 'unlockSynchro'), $oid);
    if ($this->synchroRunning('initset')){
      \Seolan\Core\Logs::notice('repli-info', 'applyinitset initset en cours');
      $this->lognojournal('applyinitset', $oid, 'initset en cours', NULL);
      if ($tplentry == TZR_RETURN_DATA)
        return 'initset en cours';
      $_REQUEST['message'] = 'initset en cours';
      return;
    }
    register_shutdown_function(array($this, 'unlockSynchro'), 'initset');
    ini_set('max_execution_time', 600);
    set_time_limit (600);
    ini_set('soap.wsdl_cache_enabled', 1);
    try{
      $client = $this->getSoapClient($sinfos);
      $ret = $client->soapclient->wgetInitSet(array('sessid'=>$client->sessid), array('hostname'=>$client->hostname, 'view'=>0));
      if ($ret->mess != 'ok'){
        \Seolan\Core\Logs::critical('repli-error', 'applyinitset erreur '.$ret->mess);
        if ($tplentry == TZR_RETURN_DATA)
          return $ret->mess;
        $_REQUEST['message'] = $ret->mess;
        return;
      }
    } catch(\SoapFault $e){
      if ($tplentry == TZR_RETURN_DATA)
        return $e->getMessage();
      $_REQUEST['message'] = $e->getMessage();
      return;
    }
    
    // les données 
    $file = $ret->data;
    // injection du jeu dans les points de sauvegarde
    $name = $date = date('Ymd_His').'_iniset';
    $dir=TZR_VAR2_DIR.'checkpoints/'.$date.'/';
    $sqlname=$dir.'database.sql';
    $configname=$dir.'config.ini';
    if(!file_exists(TZR_VAR2_DIR.'checkpoints')) mkdir(TZR_VAR2_DIR.'checkpoints');
    mkdir($dir);
    
    $config='tzr_version = "'.getFullTZRVersion().'"'."\n"; 
    $config.='creation_date = "'.date('Y-m-d H:i:s').'"'."\n";
    $comment = 'Jeu d\'initialisation, version est la version actuelle de la console. Jeu non réutilisable !';
    $config.='comment = "'.str_replace('"','\'',stripslashes($comment)).'"';

    // save de certaines tables (REPLI, JOURNAL, _STATICVARS, _VARS) dont on garde la version locale
    // et ajout au jeu reçu
    $mytablesname=TZR_VAR2_DIR.'checkpoints/'.$date.'/mytables.sql';
    $foo=explode(':',$GLOBALS['DATABASE_HOST']);
    system('mysqldump --complete-insert -u'.$GLOBALS['DATABASE_USER'].' -p'.$GLOBALS['DATABASE_PASSWORD'].' '.'-h'.$foo[0].(!empty($foo[1])?' -P'.$foo[1]:'').' '.$GLOBALS['DATABASE_NAME'].' REPLI JOURNAL _STATICVARS _VARS >'.$mytablesname);
    $file = $file."\n--\n-- TABLES LOCALES RECHARGEES \n--\n".file_get_contents($mytablesname);

    file_put_contents($configname,$config);
    file_put_contents($sqlname, $file);

    system('gzip '.$sqlname);

    // sauvegarde standard et mise en place du jeux via la restauration standard
    $db=true;
    $comment='Avant '.$name.' initialisation';
    include($GLOBALS['LIBTHEZORRO'].'scripts/createTZRCheckpoint.php');
    $ret2=include($GLOBALS['LIBTHEZORRO'].'scripts/restoreTZRCheckpoint.php');

    // récupération des externes
    $this->loadExternals($client);

    // staticvars récupérées
    getDB()->execute('REPLACE into _STATICVARS (select * from TMP_STATICVARS)',array(),false);
    // mise en place du nouveau numéro de chrono
    $newchrono = getDB()->select('select CHRONO FROM TMP_NEWCHRONO')->fetch(\PDO::FETCH_COLUMN);
    $this->ackChrono($client, $newchrono, $sinfos);

    getDB()->execute('DROP table TMP_STATICVARS',array(),false);
    getDB()->execute('DROP table TMP_DATA',array(),false);
    getDB()->execute('DROP table TMP_NEWCHRONO',array(),false);

    if ($tplentry == TZR_RETURN_DATA)
      return $ret2;

    if (!$p->is_set('_next'))
      \Seolan\Core\Shell::setNext($this->getMainAction().'&amp;message=done ? '.$ret2);

  }
  /// chargement des 'data' (via soap std)
  function loadExternals($client){
    // client soap
    $rs = getDB()->select('select * from TMP_DATA');
    \Seolan\Core\Logs::notice('repli-info', $rs->rowCount().' files to load');
    while($ors = $rs->fetch()){
        $fname = $ors['ddata'];

      // réception d'un fichier
      // construction du répertoire
      \Seolan\Library\Dir::mkdir($GLOBALS['DATA_DIR'].$fname,true);
      $tmpfile=TZR_TMP_DIR.uniqid();
      $ret2 = $client->soapclient->wgetFile(array('sessid'=>$client->sessid), array('hostname'=>$client->hostname, 'filename'=>$fname));
      if ($ret2->mess != 'ok'){
        \Seolan\Core\Logs::critical('repli error getfile',' '.$ret2->mess);
        continue;
      }
      $myfile = $GLOBALS['DATA_DIR'].$fname;
      file_put_contents($tmpfile, base64_decode($ret2->data));
      $bytes=filesize($tmpfile);
      if($bytes>0 && $bytes == $ret2->bytes) {
        copy($tmpfile, $GLOBALS['DATA_DIR'].$fname);
        unlink($tmpfile);
        \Seolan\Core\Logs::notice('repli-ok',$fname.' -> '.$GLOBALS['DATA_DIR'].$fname);
      } else {
        \Seolan\Core\Logs::critical('repli-error ','empty file or size error '.$ret2->bytes.'/'.$bytes.' '.$fname.' -> '.$GLOBALS['DATA_DIR'].$fname);
        unlink($tmpfile);
      }
        unset($ret2);
    }
  }
  // ajouter le dernier chrono 
  //
  function status($ar=NULL){
    parent::status($ar);
    $mc = getDB()->fetchOne("select ifnull(max(chrono), 0) as mc from JOURNAL;");
    if ($mc > 0)
      $msg = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Replication_Replication', 'lastchrono', 'text').' : '.$mc;
    else
      $msg = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Replication_Replication', 'emptyjournal', 'text');
    $msg1=\Seolan\Core\Shell::from_screen('imod','status');
    if(empty($msg)) $msg1=array();
    if(!empty($msg)) $msg1[]=$msg;
    \Seolan\Core\Shell::toScreen2('imod','status',$msg1);
  }
  // verifier les derniers aquittements 
  //
  private function checkCompletion(){
    $interval = "12 HOUR";
    $rs=getDB()->select("select * from REPLI where (trepli='full' OR trepli='slave') and (dtcompl < DATE_SUB(NOW(), INTERVAL {$interval}))");
    while($rs && $ors = $rs->fetch()){
      $m = 'xmodreplication => oid : '.$ors['KOID'].' ip : '.$ors['ip'].' serv : '.$ors['serv'].' is out of date, last complete sync : '.$ors['dtcompl'].' interval : '.$interval;
      \Seolan\Core\Logs::critical('repli-error', $m);
      if ($this->mailWarning)
        bugWarning($m, false, false);
    }
    unset($rs, $ors);
  }
  /// traitement d'une requete preparée
  function marshallQuery($qs, $values){
    $nb = 0; $cnt = 0;
    // cas ? tableau
    $qm = preg_replace_callback('/\?/', function($matches) use (&$values, &$nb){return getDB()->quote($values[$nb++]);}, $qs, -1, $cnt);
    // cas :paramname + tableau associatif
    if ($cnt == 0 ){
        $qm = preg_replace_callback('/:([a-zA-Z0-9_]+)/', function($matches) use (&$values){
        if (isset($values[$matches[1]]))
          return getDB()->quote($values[$matches[1]]);
        else
          return $matches[0];
        }, $qs);
        \Seolan\Core\Logs::debug('replicas2'.$qs.$qm);
        
    } 
    return $qm;
  }
  /* 
    WEBSERVICES 
    -> getChangeSet
    -> getInitSet
    -> syncAck
  */
  function _SOAPWSDLTypes(&$wsdl){
    parent::_SOAPWSDLTypes($wsdl);
    $this->_SOAPAddTypes($wsdl,array('synchroParams'=>array(
                   array('name'=>'hostname','minOccurs'=>0,'maxOccurs'=>1,'type'=>'xsd:string'),
                   array('name'=>'chrono', 'minOccurs'=>0, 'maxOccurs'=>1, 'type'=>'xsd:string'),
                   array('name'=>'view', 'minOccurs'=>0, 'maxOccurs'=>1, 'type'=>'xsd:string'),
                   array('name'=>'filename', 'minOccurs'=>0, 'maxOccurs'=>1, 'type'=>'xsd:string'),
    )));
    $this->_SOAPAddTypes($wsdl,array('setResult'=>array(
                    array('name'=>'data','minOccurs'=>0,'maxOccurs'=>1,'type'=>'xsd:string'),
                    array('name'=>'mess', 'minOccurs'=>0, 'maxOccurs'=>1, 'type'=>'xsd:string'),
                    array('name'=>'firstChrono', 'minOccurs'=>0, 'maxOccurs'=>1, 'type'=>'xsd:string'),
                    array('name'=>'lastChrono', 'minOccurs'=>0, 'maxOccurs'=>1, 'type'=>'xsd:string'),
                    array('name'=>'maxChrono', 'minOccurs'=>0, 'maxOccurs'=>1, 'type'=>'xsd:string')
    )));
    $this->_SOAPAddTypes($wsdl,array('fileResult'=>array(
                    array('name'=>'data','minOccurs'=>0,'maxOccurs'=>1,'type'=>'xsd:base64Binary'),
                    array('name'=>'mess', 'minOccurs'=>0, 'maxOccurs'=>1, 'type'=>'xsd:string'),
                    array('name'=>'bytes', 'minOccurs'=>0, 'maxOccurs'=>1, 'type'=>'xsd:string'),
                    array('name'=>'mtime', 'minOccurs'=>0, 'maxOccurs'=>1, 'type'=>'xsd:string')
    )));
    
    return;
  }
  /// Sous fonction chargée d'ajouter les messages necessaires
  function _SOAPWSDLMessages(&$wsdl){
    parent:: _SOAPWSDLMessages($wsdl);
    $wsdl->addMessage('changeSetIn',array('context'=>'tns:contextParam','param'=>'tns:synchroParams'));
    $wsdl->addMessage('changeSetOut',array('return'=>'tns:setResult'));
    $wsdl->addMessage('initSetIn',array('context'=>'tns:contextParam','param'=>'tns:synchroParams'));
    $wsdl->addMessage('initSetOut',array('return'=>'tns:setResult'));
    $wsdl->addMessage('syncAckIn',array('context'=>'tns:contextParam','param'=>'tns:synchroParams'));
    $wsdl->addMessage('syncAckOut',array('return'=>'tns:setResult'));
    $wsdl->addMessage('getFileIn',array('context'=>'tns:contextParam','param'=>'tns:synchroParams'));
    $wsdl->addMessage('getFileOut',array('return'=>'tns:fileResult'));
    return;
  }
  /// Sous fonction chargée d'ajouter les ports necessaires
  function _SOAPWSDLPortOps(&$wsdl,&$pt){
    parent::_SOAPWSDLPortOps($wsdl, $pt);
    $wsdl->addPortOperation($pt,'wgetChangeSet','tns:changeSetIn','tns:changeSetOut');
    $wsdl->addPortOperation($pt,'wgetInitSet','tns:initSetIn','tns:initSetOut');
    $wsdl->addPortOperation($pt,'wgetInitSetFiles','tns:initSetIn','tns:initSetOut');
    $wsdl->addPortOperation($pt,'wsyncAck','tns:syncAckIn','tns:syncAckOut');
    $wsdl->addPortOperation($pt,'wgetFile','tns:getFileIn','tns:getFileOut');
    return;
  }
  /// Sous fonction chargée d'ajouter les operations necessaires
  function _SOAPWSDLBindingOps(&$wsdl,&$b){
    parent::_SOAPWSDLBindingOps($wsdl, $b);
    $bo=$wsdl->addBindingOperation($b,'wgetChangeSet',array('use'=>'literal'),array('use'=>'literal'));
    $o=$wsdl->addSoapOperation($bo,'');
    $o->setAttribute('style','rpc');
    $bo=$wsdl->addBindingOperation($b,'wgetInitSet',array('use'=>'literal'),array('use'=>'literal'));
    $o=$wsdl->addSoapOperation($bo,'');
    $bo=$wsdl->addBindingOperation($b,'wgetInitSetFiles',array('use'=>'literal'),array('use'=>'literal'));
    $o=$wsdl->addSoapOperation($bo,'');
    $o->setAttribute('style','rpc');
    $bo=$wsdl->addBindingOperation($b,'wsyncAck',array('use'=>'literal'),array('use'=>'literal'));
    $o=$wsdl->addSoapOperation($bo,'');
    $o->setAttribute('style','rpc');
    $bo=$wsdl->addBindingOperation($b,'wgetFile',array('use'=>'literal'),array('use'=>'literal'));
    $o=$wsdl->addSoapOperation($bo,'');
    $o->setAttribute('style','rpc');
    return;
  }
  /// Sous fonction declarant les fonctions du module
  function _SOAPRequestFunctions(&$server) {
    parent::_SOAPRequestFunctions($server);
    function wgetChangeSet($context,$params){
      global $soapmod;
      $soapmod->SOAPContext($context,'wgetChangeSet');
      if (isset($params->view))
        $view = $params->view;
      else
        $view = 0;
      if (isset($params->hostname))
        $hostname = $params->hostname;
      else
        $hostname = NULL;
      $myparams = array('_options'=>array('local'=>1), 'mode'=>'soap', 'hostname'=>$hostname, 'view'=>$view);

      $res = $soapmod->getchangeset($myparams);

      return $res;

    }
    function wgetInitSet($context,$params){
      global $soapmod;
      $soapmod->SOAPContext($context,'wgetInitSet');
      if (isset($params->hostname))
        $hostname = $params->hostname;
      else
        $hostname = NULL;
      $myparams = array('_options'=>array('local'=>1), 'mode'=>'soap', 'hostname'=>$hostname, 'view'=>$params->view);

      $res = $soapmod->getinitset($myparams);

      return $res;

    }
    function wgetInitSetFiles($context,$params){
      global $soapmod;
      $soapmod->SOAPContext($context,'wgetInitSetFiles');
      if (isset($params->hostname))
        $hostname = $params->hostname;
      else
        $hostname = NULL;
      $myparams = array('_options'=>array('local'=>1), 'mode'=>'file', 'hostname'=>$hostname, 'view'=>$params->view);

      $res = $soapmod->getinitset($myparams);

      return $res;

    }
    function wsyncAck($context,$params){
      global $soapmod;
      $soapmod->SOAPContext($context,'wsyncAck');
      if (isset($params->hostname))
        $hostname = $params->hostname;
      else
        $hostname = NULL;
      $myparams = array('_options'=>array('local'=>1), 'mode'=>'soap', 'chrono'=>$params->chrono, 'hostname'=>$hostname);

      $res = $soapmod->syncAck($myparams);

      return $res;

    }
    function wgetFile($context, $params){
      global $soapmod;
      $soapmod->SOAPContext($context,'wgetFile');
      if (isset($params->hostname))
        $hostname = $params->hostname;
      else
        $hostname = NULL;
      $myparams = array('_options'=>array('local'=>1), 'mode'=>'soap', 'filename'=>$params->filename, 'hostname'=>$hostname);

      $res = $soapmod->getfile($myparams);

      return $res;    
    }
    $server->addFunction(array('wgetChangeSet', 'wgetInitSet', 'wgetInitSetFiles', 'wsyncAck', 'wgetFile'));
  }
  /// recuperation d'un fichier (via soap uniquement)
  function getfile($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $filename = $p->get('filename');
    $hostname = $p->get('hostname');
    $ip = $_SERVER['REMOTE_ADDR'];

    // vérification que ce serveur a les droits de venir chercher des infos
    $servoid = $this->checkServer(array('ip'=>$ip, 'hostname'=>$hostname), array('full', 'slave'));
    if($servoid == false) {
      \Seolan\Core\Logs::critical('repli-error', 'unknown ip or master configuration: '.$ip.' '.$hostname);
      return array('mess'=>'unknown ip or master configuration: '.$ip.' '.$hostname, 'data'=>NULL);
    }
    $file = $GLOBALS['DATA_DIR'].$filename;
    if (!file_exists($file) || is_dir($file)){
      \Seolan\Core\Logs::critical('repli-error', 'demande fichier inconnu '.$ip.' '.$hostname.' '.$file);
      return array('mess'=>'file does not exists '.$file, 'data'=>NULL);
    }
    $bytes=filesize($file);
    if ($bytes == 0){
      \Seolan\Core\Logs::critical('repli-error', 'erreur lecture fichier local '.$ip.' '.$hostname.' '.$file);
      return array('mess'=>'file does not exists 2', 'data'=>NULL);
    }
    return array('mess'=>'ok', 'data'=>base64_encode(file_get_contents($file)), 'bytes'=>$bytes, 'mtime'=>filemtime($file));
  }
  // rend le journal des modifs d'un objet
  //
  public function &journal($ar) {
    $p = new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $oid=$p->get('oid');
    $r=\Seolan\Core\Logs::getJournal($oid,array('etype'=>array('=',array('create','update','rule', 'synchronizeServer', 'synchronizeServ', 'getchangeset', 'syncAck','getinitset','applyinitset'))),NULL,NULL,$this->xset);
    $this->browseSumFields($ar, $r, true);
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }
  static function lognojournal($event,$object,$comment='',$dateeve=NULL){
    if(empty($dateeve)) $dateeve=date('Y-m-d H:i:s');
    $uoid=(!empty($GLOBALS['XUSER'])?$GLOBALS['XUSER']->_curoid:'');
    $newoid = \Seolan\Core\DataSource\DataSource::getNewBasicOID('LOGS');
    $values = array($newoid, 'FR', $uoid, date('Y-md-d H:i:s'), date('Y-m-d H:i:s'), $event, $comment, $object, @$_SERVER['REMOTE_ADDR']);
    getDB()->execute('insert into LOGS (KOID, LANG, user, datecre, dateupd, etype, comment, object, ip) values(?, ?, ?, ?, ?, ?, ?, ?, ?)', $values,false);
  }
  /// verification et lock d'une synchro sur un serveur
  protected function synchroRunning($serveroid){
    return !\Seolan\Library\Lock::getLock('replication'.$serveroid);
  }
  protected function unlockSynchro($serveroid){
   $file = TZR_LOCK_DIR.'replication'.$serveroid.'1';
   \Seolan\Core\Logs::notice(get_class($this),'unlock repli '.$file); 
   if (file_exists($file))
   unlink($file);
  }
  /// verification d'une initialisation en cours
  static function initsetRunning() {
    return (false != ($pid = file_get_contents(TZR_LOCK_DIR.'replicationinitset1')) && file_exists('/proc/' .$pid) );
  }
  /// forcer un reset sur un distant
  public function forceInitset($ar) {
    $p = new \Seolan\Core\Param($ar);
    $oid = $p->get('oid');
    $sinfos = $this->getServerInfos($oid);
    \Seolan\Core\DbIni::setStatic('replication:'.$sinfos['_ident'].':chrono', 'forceInitset');
    \Seolan\Core\Logs::update('update', $oid, 'forceInitset');
    getDB()->execute("update REPLI set status='initset required' where koid='$oid'");
    setSessionVar('message', 'Initset forcé');
    \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
  }
}

?>
