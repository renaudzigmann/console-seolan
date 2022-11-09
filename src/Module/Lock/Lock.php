<?php
namespace Seolan\Module\Lock;
/**
 * Booking
 * Gestion des réservations
 * table _LOCKS, une entrée par objet (koid), voir initLocks
 * @TODO : ajouter une clé primaire sur koid ?
 *
 */
use \Seolan\Core\Logs;
use \Seolan\Core\Module\Module;
class Lock extends Module {
  protected $expirationNotification = 3; // délai en jours avant notification d'expiration du verrou

  static public $upgrades=['20211028'=>''];

  function __construct($ar=NULL) {
    $ar["moid"]=self::getMoid(XMODLOCK_TOID);
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Lock_Lock');
    $this->initLocks();
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array('index'=>array('ro','rwv','admin'));
    $g['forceUnlock']=array('rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&amp;_function=index&amp;tplentry=br&amp;template=Module/Lock.index.html';
  }

  function index($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('user'=>\Seolan\Core\User::get_current_user_uid()));
    $tplentry=$p->get("tplentry");
    $r=[];
    $user=$p->get('user');
    $oids=$this->ls($user);
    $lines=[];
    $i=0;
    
    foreach($oids as $oid=>$obj) {
      if(\Seolan\Core\Kernel::objectExists($oid)) {
	$t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$oid);
	$lines[$i]=$t->display(['oid'=>$oid,
				'tplentry'=>TZR_RETURN_DATA]);
	$lines[$i]['_lock']['OWN']=$this->getUser($oid,'');
	
	if (!empty($obj['MOID'])){
	  $mod = Module::objectFactory(['_options'=>['local'=>1],
					'moid'=>$obj['MOID'],
					'interactive'=>false,
					'tplentry'=>TZR_RETURN_DATA]);
	  if ($mod)
	    $lines[$i]['_module'] = $mod->getLabel();
	}
	$i++;
      }
    }

    $r['lines']=&$lines;
    $r['user']=$user;
    if($this->secure('',':admin')){
      list($acl_user, $acl_grp)=\Seolan\Core\User::getUsersAndGroups();
      \Seolan\Core\Shell::toScreen1('users',$acl_user);
      \Seolan\Core\Shell::toScreen1('grps',$acl_grp);
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  // cette fonction est appliquee pour afficher l'ensemble des methodes
  // de ce module
  //
  protected function _actionlist(&$my=NULL, $alfunction=true) {
    parent::_actionlist($my);
    $myclass=get_class($this);
    $moid=$this->_moid;
    
    $o1=new \Seolan\Core\Module\Action($this, 'index', \Seolan\Core\Labels::getSysLabel('Seolan_Module_Lock_Lock','index','text'),
			  'class='.$myclass.'&amp;moid='.$moid.
			  '&amp;_function=index&amp;tplentry=br&amp;'.
			  'template=Module/Lock.index.html');
    $o1->homepageable=$o1->menuable=$o1->quicklinkable=true;
    $o1->group='edit';
    $o1->setToolbar('Seolan_Core_General','browse');
    $my['index']=$o1;
    $my['default']='index';
  }

  function status($ar=NULL) {
  }
  
  static $_locks=[];
  function initLocks() {
    self::$_locks=[];
    $rs=getDB()->select('SELECT * FROM _LOCKS');
    while($ors=$rs->fetch()) self::$_locks[$ors['KOID']]=$ors;
  }

  /// recherche de tous les lock d'un utilisateur
  function ls($owner='%') {
    $rs=getDB()->select('SELECT * FROM _LOCKS WHERE OWN like ? ORDER BY DSTART',array($owner));
    $oids=[];
    while($ors=$rs->fetch()) {
      $oids[$ors['KOID']]=$ors;
    }
    return $oids;
  }

  /**
   * verrouiller un document dont l'identifiant est $oid, dans la
   * langue $lang, au nom de $own, jusqu'à la date $upto. Si la date
   * est vide, réservation pendant 7 j
   * le module à l'origine est mémorisé dans 'MOID' : paramètre omoid
   * utilisable pour modifier la date d'expiration d'un verrou
   */
  function lock($oid, $lang, $own=NULL, $upto=NULL, $omoid=null) {
    $ok=true;
    if(!empty(self::$_locks[$oid])) {
      // si on cherche à modifier un lock qui ne nous appartient pas, c'est refusé
      if($own!=self::$_locks[$oid]['OWN']) return false;

      // si c'est la même date on ne refait pas
      if(substr($upto, 0, 10)!=substr(self::$_locks[$oid]['DEND'],0,10)) {
	getDB()->execute("UPDATE _LOCKS SET DEND=? WHERE KOID=?", [$upto, $oid]);
	Logs::update('lock', $oid, 'locked up to '.$upto, $timestamp);
      }
      return $ok;
    } 
    if($ok) {
      $timestamp=date("YmdHis",time());
      $timestamp2=date("YmdHis",time()+60*60*24*7);
      getDB()->execute("INSERT INTO _LOCKS SET KOID=?, LANG=?, STATUS=?, OWN=?, ".
		       "DSTART=?,DEND=?,MOID=?",
		       [$oid, $lang, 'locked', $own, $timestamp, $timestamp2, $omoid]);
      Logs::update('lock', $oid, 'locked up to '.$timestamp2, $timestamp);
    }
    return $ok;
  }
  function forceUnlock($ar) {
    $p=new \Seolan\Core\Param($ar,[]);
    $tplentry=$p->get("tplentry");
    $oid=$p->get('oid');
    $block=self::locked($oid, $lang, $own);
    $user=$block['OWN'];
    $ok=($user==\Seolan\Core\User::get_current_user_uid()) || $this->secure('',':admin');
    if($ok) {
      $this->unlock($oid, TZR_DEFAULT_LANG);
    }
  }

  // suppression de tous les lock pour un user donne
  //
  function cleanLocksForUser($own) {
    $rs=getDB()->select('select KOID from _LOCKS where OWN=?',[$own]);
    while($ors=$rs->fetch()) 
      $this->unlock($ors['KOID'], TZR_DEFAULT_LANG);
  }
  function unlock($oid, $lang, $own=NULL) {
    if(self::locked($oid, $lang, $own)) {
      getDB()->execute("DELETE FROM _LOCKS WHERE KOID=? AND LANG=?",[$oid,$lang]);
      Logs::update('unlock', $oid);
      unset(self::$_locks[$oid]);
    }
    return true;
  }

  function locked($oid, $lang, $own=NULL) {
    if(!empty(self::$_locks[$oid])) return self::$_locks[$oid];
    else return NULL;
  }

  // rendre un tableau 'user'->nom du user en clair, 'oid'-> oid qui a locke', date de but et de fin de verrou
  //
  function getUser($oid, $lang) {
    if(!empty(self::$_locks[$oid])){
      $ors=getDB()->fetchRow('SELECT * FROM USERS WHERE KOID = ?',array(self::$_locks[$oid]['OWN']));
      if($ors) {
	return array('user'=>$ors['fullnam'],
		     'oid'=>$ors['KOID'],
		     'dstart'=>\Seolan\Field\Date\Date::printDate(self::$_locks[$oid]['DSTART']),
		     'dend'=>\Seolan\Field\Date\Date::printDate(self::$_locks[$oid]['DEND'])
		     );
      }else return NULL;
    }else return NULL;
  }
  function _daemon($period='any'){
    if ($period == 'daily'){
      $this->notifyExpirations(date('Y-m-d'));
      $this->purgeExpired(date('Y-m-d'));
    }
  }
  /**
   * notification des expirations de réservations à leurs auteurs,
   * 3 jours avant la date de fin. Les réservations de moins de 3 jours
   * ajoutées ce jour ne sont pas notifiées
   */
  protected function notifyExpirations($now){
    $rs = getDB()->select("select * from _LOCKS where date_format(?, '%Y-%m-%d') = date_format(date_sub(DEND, interval ? day), '%Y-%m-%d')",
			  [$now,
			   $this->expirationNotification]);
    Logs::notice(__METHOD__, $rs->rowCount()." lock(s) to notify query '{$rs->queryString}'" );
    foreach($rs as $lock){
      // user existe bien
      list($userEmail, $userFullnam) = $line = getDB()->select('select email, fullnam from USERS where KOID=?', [$lock['OWN']])->fetch(\PDO::FETCH_NUM);
      // pour le moment on n'a pas le module, donc récupération d'un titre de l'objet
      $disp = \Seolan\Core\DataSource\DataSource::objectDisplayHelper($lock['KOID']);
      if (!$disp){
	Logs::critical(__METHOD__,"{$lock['KOID']} not exists in datasource");
	// objet de la réservation n'existe plus, ...
	continue;
      }
      Logs::notice(__METHOD__,"notifying '{$userFullnam}<{$userEmail}>', lock : '{$lock['KOID']}, '{$disp['tlink']}'");
      $subject = "Votre réservation";
      $docurl = $this->docurl($lock);
      $body = "Nous vous rappelons que votre réservation du document <a href='{$docurl}'>{$disp['tlink']}</a> se termine dans 3 jours.";
      if (!empty($userEmail)){
	$this->sendAdminMail2User($subject,
				$body,
				"{$userFullnam}<{$userEmail}>"
	);
      } // else => le lock sera détruit à exipiration
    }
    Logs::notice(__METHOD__, "end");
  }
  /// url d'accès en adminà un doc réservé
  protected function docurl($lock){
    return $GLOBALS['TZR_SESSION_MANAGER']::admin_gopage_url(
							     makeUrl($GLOBALS['TZR_SESSION_MANAGER']::admin_url(),
								     ['moid'=>$lock['MOID'],
								      'function'=>'goto1',
								      'oid'=>$lock['KOID']
								      ])
							     ,true);
  }
  /**
   * purge des verrous expirés et notification des auteurs
   * le lendemain de dend
   */
  protected function purgeExpired($now){

    $rs = getDB()->select("select * from _LOCKS where date_format(?, '%Y-%m-%d') >= date_format(date_add(DEND, interval 1 day), '%Y-%m-%d')",
			  [$now]);
    Logs::notice(__METHOD__, 'start '.getDB()->fetchOne('select count(*) from _LOCKS'));        
    Logs::notice(__METHOD__, $rs->rowCount()." lock(s) to purge query '{$rs->queryString}'" );

    foreach($rs as $lock){
      // user 
      list($userEmail, $userFullnam) = $line = getDB()->select('select email, fullnam from USERS where KOID=?', [$lock['OWN']])->fetch(\PDO::FETCH_NUM);
      // pour le moment on n'a pas le module, donc récupération d'un titre de l'objet
      $disp = \Seolan\Core\DataSource\DataSource::objectDisplayHelper($lock['KOID']);
      if (!$disp){
	// l'objet n'existe plus, on efface directement
	Logs::critical(__METHOD__,"{$lock['KOID']} not exists in datasource");
	getDB()->execute('delete from _LOCKS where koid=?', [$lock['KOID']]);
	continue;
      }
      Logs::notice(__METHOD__,"purge of '{$userFullnam}<{$userEmail}>', lock : '{$lock['KOID']}, '{$disp['tlink']}'");
      $subject = "Votre réservation";
      $docurl = $this->docurl($lock);
      $body = "Votre réservation du document <a href='{$docurl}'>{$disp['tlink']}</a> est terminée. Le document vient d'être libéré.";
      if (!empty($userEmail)){
	$this->sendAdminMail2User($subject,
				  $body,
				  "{$userFullnam}<{$userEmail}>"
	);
      }
      getDB()->execute('delete from _LOCKS where LANG=? and KOID=?', [$lock['LANG'], $lock['KOID']]);
    }
    Logs::notice(__METHOD__, 'end '.getDB()->fetchOne('select count(*) from _LOCKS'));    
  }
  // suppression dans le module des documents qui sont effaces depuis d'autres modules
  //
  function _removeRegisteredOid($oid) {
    getDB()->execute('delete from _LOCKS where KOID=?', array($oid));
  }
  // suppression de la table lors de la suppression du module
  //
  function delete($ar){
    parent::delete($ar);
    if (isset($_REQUEST['withtable']) && $_REQUEST['withtable'] == 1)
      getDB()->execute("DROP TABLE IF EXISTS _LOCKS");
  }
}
?>
