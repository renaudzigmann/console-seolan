<?php
/// Classe representant un utilisateur authentifie

namespace Seolan\Core;

class User  {
  const DB_SESSION_CACHE_PREFIX='cache::';

  public $table='USERS';
  public $_cur=NULL;
  public $_curoid=NULL;
  public $_debug=false;
  public $admin=false;
  static $_cacheml=array();
  static $_C81=array();
  static $_C82=array();
  static $_C8OID = array();
  static $_C8OIDS = array();
  static $_cache_sGroup=array();
  static $_cache_listObjectAccess=array();
  

  static $uscache = array(); // cache for multipurpose user session cache
  
  // creation d'un utilisateur ou d'un object utilisateur a partir d'un alias, d'un uid, d'un ar avec UID
  function __construct($ar=NULL) {
    if(is_string($ar)) {
      if(\Seolan\Core\Kernel::isAKoid($ar)) $this->_curoid=$ar;
      else {
	$uid=getDB()->fetchRow('SELECT KOID FROM USERS WHERE alias=? LIMIT 1',array($ar));
	if(!empty($uid)) $this->_curoid=$uid['KOID'];
      }
    }
    if(empty($this->_curoid)) {
      $p = new \Seolan\Core\Param($ar, array('UID'=>TZR_USERID_NOBODY));
      $this->_curoid=$p->get('UID');
    }
    if($this->_curoid!=TZR_USERID_NOBODY) $this->load();
    else{
      $this->_cur['ldata']='';
      $this->_cur['luser']='';
    }
  }

  static function clearCache() {
    \Seolan\Core\User::$_cacheml = array();
    \Seolan\Core\User::$_C81 = array();
    \Seolan\Core\User::$_C82 = array();
    \Seolan\Core\User::$_cache_sGroup = array();
    \Seolan\Core\User::$_cache_listObjectAccess=array();
    \Seolan\Core\User::clearDbSessionDataAndRightCache();
  }

  /// récupérer l'expéditeur 'anonyme' de cette console
  static function getAnonymousSender($senderName='', $originalEmail=NULL) {
    $sender=(!empty($senderName) ? $senderName : \Seolan\Core\Ini::get('societe'));
    return ['noreply@' . implode('.', array_slice(explode('.', parse_url($GLOBALS['HOME_ROOT_URL'], PHP_URL_HOST)), -2)), $sender];
  }

  static public function authentified() {
    $uid=getSessionVar('UID');
    if(!empty($uid) && ($uid!='USERS:0')) return true;
    return false;
  }

  /// rend l'identifiant (oid) de l'utilisateur connecté
  static function get_current_user_uid() {
    $uid= getSessionVar('UID');
    if(empty($uid)) $uid=TZR_USERID_NOBODY;
    return $uid;
  }
  /// rend vrai si aucun utilisateur connecté
  static function isNobody() {
    $uid= getSessionVar('UID');
    if(empty($uid)) return true;
    if($uid==TZR_USERID_NOBODY) return true;
    return false;
  }
  /// rend l'objet XUser de l'utilisateur courant
  public static function get_user() {
    return $GLOBALS['XUSER'];	
  }
  function uid() {
    return $this->_curoid;
  }
  function isLocal(){
    return empty($this->_cur['directoryname']) || ($this->_cur['directoryname'] == 'local');
  }
  function isRoot() { return(!empty($this->_cur['grp']) && in_array(TZR_GROUPID_ROOT,$this->_cur['grp'])); }

  /// rend le nom en clair de l'utilisateur authentifie
  function fullname() {
    return $this->_cur['fullnam'];
  }
  /// rend le home du backoffice de l'utilisateur
  function bohome() {
    if(!empty($this->_cur['bohome']) && \Seolan\Core\Kernel::objectExists($this->_cur['bohome']))
      return $this->_cur['bohome'];
    else
      return 'home';
  }
  /// rend le top du menu backoffice de l'utilisateur
  function botop() {
    if(!empty($this->_cur['bohome']) && \Seolan\Core\Kernel::objectExists($this->_cur['bohome']))
      return $this->_cur['bohome'];
    else
      return 'top';
  }
  /// rend la photo de l'utilisateur
  function logo(){
    if (\Seolan\Core\User::isNobody()){
      return null;
    }
    if (!isset($this->_cur['_display'])){
      $this->_display();
    }
    return ($this->_cur['_display']['ologo']??null);
  }
  /// rend l'email en clair de l'utilisateur authentifie
  function email() {
    return $this->_cur['email'];
  }
  /// rend la date de derniere connexion en clair de l'utilisateur authentifie
  function lastconnection() {
    return $this->_cur['lastcon'];
  }

  function language($ar=NULL) {
    return array(@$this->_cur['ldata'],@$this->_cur['luser']);
  }
  /**
   * display complet (différé!) du user. on ne peut pas faire de display lors du load
   */
  function _display(){
    $this->_cur['_display'] = \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID)->xset->rdisplay($this->_curoid,
													   $this->_cur,
													   false,
													   \Seolan\Core\Shell::getLangData(),
													   $this->_cur['luser']
													   );
  }
  /**
   * chargement et traitement des données brutes
   * @note : pas de display : initialisation en cours
   */
  function load() {
    $this->_cur = getDB()->fetchRow('SELECT * FROM '.$this->table .' WHERE KOID=?',array($this->_curoid));
    $this->_cur['grp'] = explode('||', $this->_cur['GRP']);
    if(empty($this->_cur['luser']))
      $this->_cur['luser'] = TZR_DEFAULT_LANG;
    if(\Seolan\Core\Shell::admini_mode() && !\Seolan\Core\User::isNobody()) {
      if(!in_array(TZR_GROUPID_AUTH,$this->_cur['grp']))
        $this->_cur['grp'][]=TZR_GROUPID_AUTH;
    } elseif(!in_array(TZR_GROUPID_NOBODY,$this->_cur['grp']))
      $this->_cur['grp'][]=TZR_GROUPID_NOBODY;
  }

  function getUserList($myselfIncluded = true) {
    $LANG_USER = \Seolan\Core\Shell::getLangUser();
    $cond='';
    if ( !$myselfIncluded ) {
      $cond.=' KOID != "'.$this->_curoid.'" ';
    }
    $rs=getDB()->fetchAll("select * from {$this->table} $cond order by fullnam");
    $liste=array();
    foreach($rs as $ors) {
      $fullnam=$ors['fullnam'];
      $alias=$ors['alias'];
      $liste[$ors['KOID']]="$fullnam [$alias]";
    }
    unset($rs);
    return $liste;
  }

  function setGroups($groups) {
    $this->_cur['groups']=$groups;
  }

  function groups($refresh=false) {
    if(!$refresh && !empty($this->_cur['groups'])) return $this->_cur['groups'];
    $limits=array();
    $limits['USERS']=array('GRP');
    $limits['GRP']=array('GRPS');
    $g1=\Seolan\Core\Kernel::followLinks(array($this->_curoid),$limits);
    // Si l'utilisateur est authentifié, il fait parti du groupe "Utilisateurs authentifié" automatiquement
    if(!\Seolan\Core\User::isNobody() && !in_array(TZR_GROUPID_AUTH, $g1)) $g1[]=TZR_GROUPID_AUTH;
    // Si l'utilisateur fait parti du groupe "Utilisateurs authentifiés", il ne fera pas parti du groupe "Tout le monde"
    if(!in_array(TZR_GROUPID_AUTH, $g1) && !in_array(TZR_GROUPID_NOBODY, $g1)) $g1[]=TZR_GROUPID_NOBODY;
    $this->_cur['groups']=$g1;
    return $g1;
  }

  static function sGroups($uoid) {
    if(!is_array($uoid)) $uoid=array($uoid);
    $idx=implode('-',$uoid);
    if(empty(\Seolan\Core\User::$_cache_sGroup[$idx])){
      $limits=array();
      $limits['USERS']=array('GRP');
      $limits['GRP']=array('GRPS');
      $g1=\Seolan\Core\Kernel::followLinks($uoid,$limits);
      if(!\Seolan\Core\User::isNobody() && !in_array(TZR_GROUPID_AUTH, $g1)) $g1[]=TZR_GROUPID_AUTH;
      if(!in_array(TZR_GROUPID_AUTH, $g1) && !in_array(TZR_GROUPID_NOBODY, $g1)) $g1[]=TZR_GROUPID_NOBODY;
      \Seolan\Core\User::$_cache_sGroup[$idx]=array_unique($g1);
    }
    return \Seolan\Core\User::$_cache_sGroup[$idx];
  }

  // Rend vrai si l'utiliser uid est dans les groupes $grps (attention si $parents==true, en ajoute dans grps les groupes parents et surtout GRP:0 ou GRP:2 selon si authentifié ou pas)
  function inGroups($grps,$parents=true) {
    if(!is_array($grps)) $grps=array($grps);
    $mygroups = $this->groups();
    if($parents) $otgroups = \Seolan\Core\User::sGroups($grps);
    else $otgroups = $grps;
    $r1=array_intersect($mygroups, $otgroups);
    if(empty($r1)) {
      return false;
    }
    return true;
  }

  /// Compare 2 niveaux de sécurité pour une classe donnée. rend vrai si inférieur ou égal
  static public function compareSecLevelsLte($moidOrClass,$lvl1,$lvl2){
    if(is_numeric($moidOrClass)){
      $mod=\Seolan\Core\Module\Module::objectFactory($moidOrClass);
      $secs=$mod->secList();
    } else {
      $secs=call_user_func(array($moidOrClass,'secList'),NULL);
    }
    
    $res1=array_search($lvl1,$secs);
    $res2=array_search($lvl2,$secs);
    return ($res1 <= $res2);
  }

  /// Vérifie si l'utilisateur courant peut attribuer un droit spécifique à un objet
  static public function isAuthorizedToSetAccess($class,$moid,$lang,$oid,$lvl){
    if($lvl=='default') return true;
    $actlvl=\Seolan\Core\User::secure8maxlevel($moid,$oid,null,$lang);
    return \Seolan\Core\User::compareSecLevelsLte($moid,$lvl,$actlvl);
  }

  /// Positionnement du niveau d'accés dans la base de donnéees
  public function setUserAccess($class,$moid,$lang,$oid,$level,$uoid=NULL,$cache=false,$force=true,$comment='') {
    if(empty($uoid)) $uoid=$this->_curoid;
    if(!$force && !$cache && !\Seolan\Core\Shell::isRoot()){
      if(!\Seolan\Core\User::isAuthorizedToSetAccess($class,$moid,$lang,$oid,$level)) return false;
    }
    $table=($cache?'ACL4_CACHE':'ACL4');
    $aoid=self::getNewAoid();
    if(!empty($moid)) {
      $q="DELETE FROM $table where AMOID=\"$moid\" and ALANG=\"$lang\" and AKOID=\"$oid\" and AGRP=\"$uoid\"";
      $q2="INSERT INTO $table(AOID,UPD,AGRP,AFUNCTION,ALANG,AMOID,AKOID,OK,ACOMMENT) ".
	"values (\"$aoid\",NULL,\"$uoid\",\"$level\",\"$lang\",\"$moid\",\"$oid\",1,\"$comment\")";
      $q3="DELETE FROM ACL4_CACHE where AMOID=\"$moid\" and ALANG=\"$lang\"";
      if(!$cache)
	\Seolan\Core\Logs::secEvent(__METHOD__, "Delete security rules for moid=$moid oid=$oid user=$uoid lang=$lang", $uoid);
    } else {
      $q="DELETE FROM $table where ACLASS=\"$class\" and ALANG=\"$lang\" and AKOID=\"$oid\" and AGRP=\"$uoid\"";
      $q2="INSERT INTO '.$table.'(AOID,UPD,AGRP,AFUNCTION,ACLASS,ALANG,AKOID,OK,ACOMMENT) ".
	"values (\"$aoid\",NULL,\"$uoid\",\"$level\",\"$class\",\"$lang\",\"$oid\",1,\"\")";
      $q3="DELETE FROM ACL4_CACHE where ACLASS=\"$class\" and ALANG=\"$lang\"";
      if(!$cache)
	\Seolan\Core\Logs::secEvent(__METHOD__,"Delete security rules for class=$class oid=$oid user=$uoid lang=$lang",$uoid);
    }
    if($lang=='all'){
      $q=str_replace('and ALANG="all"','',$q);
      $q3=str_replace('and ALANG="all"','',$q3);
    }
    // On efface d'eventuels drois deja presents
    getDB()->execute($q);
    // On efface le cache du module
    if(!$cache) getDB()->execute($q3);

    if($level!='default') {
      if($lang=='all'){
	foreach($GLOBALS['TZR_LANGUAGES'] as $lg=>$locale){
	  getDB()->execute(str_replace("\"all\"","\"$lg\"",$q2));
	  \Seolan\Core\User::$_cacheml[$class.$moid.$lg.$uoid.$oid]=$level;
	}
      }else{
	getDB()->execute($q2);
	\Seolan\Core\User::$_cacheml[$class.$moid.$lang.$uoid.$oid]=$level;
      }
      if(!$cache)
	\Seolan\Core\Logs::secEvent(__METHOD__, "New security rules for '$uoid',1,'$level','$class','$lang','$moid','$oid',1",$uoid);
    }else{
      unset(\Seolan\Core\User::$_cacheml[$class.$moid.$lang.$uoid.$oid]);
    }
    return true;
  }
  
  /// Copie des acces sur un oid vers un autre oid
  public function copyUserAccess($oidsrc, $oiddst, $moid) {
    $rs=getDB()->select("select * FROM ACL4 where AMOID=? and AKOID=?",array($moid,$oidsrc));
    while($ors=$rs->fetch()) {
      // on verifie que l'acl n'existe pas
      $n=getDB()->count("select count(*) from ACL4 where AGRP=? and AFUNCTION=? and ACLASS=? ".
                        "and ALANG=? and AMOID=? and AKOID=? and OK=?", 
			[$ors['AGRP'], $ors['AFUNCTION'], $ors['ACLASS'], $ors['ALANG'], $ors['AMOID'], $oiddst, $ors['OK']]);
      if(!$n){
        $aoid=self::getNewAoid();
        getDB()->execute("INSERT INTO ACL4(AOID,AGRP,AFUNCTION,ACLASS,ALANG,AMOID,AKOID,OK) values (?,?,?,?,?,?,?,?)",
			 [$aoid, $ors['AGRP'], $ors['AFUNCTION'], $ors['ACLASS'], $ors['ALANG'], $ors['AMOID'], $oiddst, $ors['OK']]);

      }
    }
    \Seolan\Core\Logs::secEvent(__METHOD__, "Copy security rules from $oidsrc to $oiddst", $oiddst);
  }

  /// Copie tous les droits d'un utilisateur/groupe vers un autre
  function copyAllUserAccess($suid,$duid,$comment=null){
    $rs=getDB()->select('select * FROM ACL4 where AGRP=? and not (AKOID like ?)', array($suid, '_TYPES:%'));
    while($rs && $ors=$rs->fetch()) {
      $aoid=self::getNewAoid();
      // attention il ne faut pas copier tels quels les droits sur les
      // types documents, d'où le "not like _types%"
      getDB()->execute('INSERT INTO ACL4(AOID,AGRP,AFUNCTION,ACLASS,ALANG,AMOID,AKOID,OK,ACOMMENT) values (?,?,?,?,?,?,?,?,?)',
		       [$aoid, $duid, $ors['AFUNCTION'], $ors['ACLASS'], $ors['ALANG'], $ors['AMOID'], $ors['AKOID'], $ors['OK'], ($comment??$ors['ACOMMENT']??__METHOD__)]);
    }
    \Seolan\Core\Logs::secEvent(__METHOD__,"Copy all security rules from $suid", $duid);
  }

  /// Copie tous les droits d'un utilisateur/groupe vers un autre
  function copyAllUserAccessOnDocumentTypes($suid,$duid, $documenttypes){
    $this->copyAllUserAccessOnObjects($suid,$duid, $documenttypes);
    \Seolan\Core\Logs::update('security',$duid,'Copy all security on document types rules from '.$suid);
  }
  /// Copie tous les droits d'un utilisateur/groupe vers un autre pour une liste données d'objet source / destination
  function copyAllUserAccessOnObjects($suid,$duid, $objects){
    foreach($objects as $oid=>$newoid) {
      $rs=getDB()->fetchAll("select * FROM ACL4 where AGRP=? AND AKOID=?", [$suid, $oid]);
      foreach($rs as $ors) {
	$aoid=self::getNewAoid();
	getDB()->execute("INSERT INTO ACL4(AOID,AGRP,AFUNCTION,ACLASS,ALANG,AMOID,AKOID,OK) values (?,?,?,?,?,?,?,?)",
			 [$aoid, $duid, $ors['AFUNCTION'], $ors['ACLASS'], $ors['ALANG'], $ors['AMOID'], $newoid, $ors['OK']]);
      }
    }
    \Seolan\Core\Logs::secEvent(__METHOD__,"Copy all security on object rules from $suid", $duid);
  }
  /// Copie tous les droits d'un module sur un autre
  static function copyModuleAccess($smoid,$dmoid,$with_objects=false){
    $cond='AMOID=?';
    if(!$with_objects) $cond.=' and AKOID=""';
    $rs=getDB()->fetchAll("SELECT AOID,AGRP,AFUNCTION,ACLASS,ALANG,AMOID,AKOID,OK FROM ACL4 where $cond",array($smoid));
    foreach($rs as $ors) {
      $ors['AOID']=self::getNewAoid();
      $ors['AMOID']=$dmoid;
      getDB()->execute("INSERT INTO ACL4(AOID,AGRP,AFUNCTION,ACLASS,ALANG,AMOID,AKOID,OK) values (:AOID,:AGRP,:AFUNCTION,:ACLASS,:ALANG,:AMOID,:AKOID,:OK)",
                       $ors);
      \Seolan\Core\Logs::secEvent(__METHOD__,"Copy module rules from $smoid to $dmoid (with object : [ $with_objects ]) from $smoid to $dmoid", $dmoid);
    }
  }
  
  /// Effacement des droits correspondant a un module, une langue, un oid, des utilisateurs donnes
  static function clearUserAccess($class, $moid, $lang, $oid, $uoid=NULL, $comment=NULL) {
    $rq=[];
    $params=[];
    if(!empty($uoid)) {
      $params[]=$uoid;
      $rq[]="AGRP=?";
    } 
    if(!empty($moid)) {
      $params[]=$moid;
      $rq[]="AMOID=?";
    } 
    if(!empty($lang)) {
      $params[]=$lang;
      $rq[]="ALANG=?";
    } 
    if(!empty($oid)) {
      $params[]=$oid;
      $rq[]="AKOID=?";
    }
    if(!empty($comment)) {
      $params[]=$comment;
      $rq[]="ACOMMENT=?";
    }
    $request=implode(" AND ", $rq);
    if(!empty($request)) {
      getDB()->execute("DELETE FROM ACL4 where $request", $params);
      \Seolan\Core\Logs::secEvent(__METHOD__,"Clear security rules for $class, $moid, $lang, $oid or $uoid, $request", $oid);
    }
  }
  
  /// Rend la liste des droits pour un moid/oid
  public function getObjectAccess($mod, $lang, string $oid='', $grp=NULL) {
    if(!$mod) return array();
    if(is_numeric($mod)) $mod=\Seolan\Core\Module\Module::objectFactory($mod);
    if(!$mod) return array(array(),array());
    $seclistfull = $seclist = $mod->secList();
    $level=self::secure8maxlevel($mod,$oid,$grp,$lang);
    $cnt=count($seclist);
    $i=$cnt-1;
    while($i>=0) {
      if(!($level==$seclist[$i])) {
	unset($seclist[$i]);
	$i--;
      } else break;
    }
    if(count($seclist)<=0) $seclist=array("none");
    return array($seclist,$seclistfull);
  }
  /// Alias de getObjectAccess
public function getUserAccess($class, string $moid, $lang=NULL, string $oid='', $grp=NULL) {
    return $this->getObjectAccess($moid, $lang, $oid, $grp);
  }

  /// recherche des acces pour tous les utilisateurs sur module donne.
  static function getModuleAccess(\Seolan\Core\Module\Module &$mod,$all=true,$withFO=false,$withEmptyGrps=0) {
    global $TZR_LANGUAGES;
    list($users, $groups) = \Seolan\Core\User::getUsersAndGroups(true,$all,null,$withFO,$withEmptyGrps);
    $x1=self::get_user();
    foreach($users['lines_oid'] as $i=>$uoid) {
      $users['lines_sec'][$i]=array();
      foreach($TZR_LANGUAGES as $lang=>$v){
	$users['lines_sec'][$i][$lang]=$x1->getObjectAccess($mod->_moid, $lang, '', $uoid);
      }
    }

    foreach($groups['lines_oid'] as $i=>$uoid) {
      $groups['lines_sec'][$i]=array();
      foreach($TZR_LANGUAGES as $lang=>$v){
	$groups['lines_sec'][$i][$lang]=$x1->getObjectAccess($mod->_moid, $lang, '', $uoid);
      }
    }
	
    return array($users, $groups);
  }

  public function &getObjectsAccess($mod, $lang, &$oids) {
    $l=$this->getObjectAccess($mod, $lang);
    $l=array_flip($l[0]);
    $hasrules=$mod->getObjectsWithSec();
    $levs=array();
    if(empty($hasrules)) {
      foreach($oids as $i => $oid) {
        $levs[$i]=$l;
      }
    } else {
      if(is_array($hasrules)) $hasrules=array_flip($hasrules);
      
      foreach($oids as $i => $oid) {
        if($hasrules===true || array_key_exists($oid,$hasrules)){
          $l2 = $this->getObjectAccess($mod, $lang, $oid);
          $l2=array_flip($l2[0]);
          $levs[$i]=$l2;
        }else{
          $levs[$i]=$l;
        }
      }
    }
    return $levs;
  }

  /// Pour un user/group donné, retourne les oids pour lesquels des droits spécifique ont été posé
  static function getObjectsWithSec($mod,$uid){
    $uids=\Seolan\Core\User::sGroups($uid);
    $hasrules=getDB()->fetchCol('(select AKOID from ACL4 where AGRP in ("'.implode('","',$uids).'") and AMOID="'.$mod->_moid.'" and AKOID!="" AND AKOID NOT LIKE "_field%") UNION DISTINCT '.
				   '(select AKOID from ACL4_CACHE where AGRP in ("'.implode('","',$uids).'") and AMOID="'.$mod->_moid.'" and AKOID!="" and ACOMMENT="'.\Seolan\Core\Session::CS8_EMPTY_CHECK.'")');
    return $hasrules;
  }

  public function listObjectAccess(\Seolan\Core\Module\Module &$mod, $lang, $oid, $details=false) {
    if(!empty($lang)) {
      $rs=getDB()->fetchAll("select * from ACL4 where AMOID=? and ALANG=? and AKOID=? order by AGRP,ALANG", array($mod->_moid, $lang, $oid));
    } else {
      $rs=getDB()->fetchAll("select * from ACL4 where AMOID=? and AKOID=? order by AGRP,ALANG", array($mod->_moid, $oid));
    }
    $r=array();
    $k = new \Seolan\Core\Kernel();
    $ors=array();
    foreach($rs as $ors) {
      if($details) {
	$d1=&\Seolan\Core\User::$_cache_listObjectAccess[$ors['AGRP']];
	if(!$d1) $d1=\Seolan\Core\DataSource\DataSource::objectDisplayHelper($ors['AGRP']);
	if(is_array($d1)) {
	  if($d1['ofullnam']->raw) $names[]='B-'.$d1['ofullnam']->raw;
          else $names[]='A-'.$d1['oGRP']->raw;
	  $r['acl_uid'][]=$ors['AGRP'];
	  $r['acl_own'][]=$d1;
	  $r['acl_level'][]=$ors['AFUNCTION'];
	  $r['acl_lang'][]=$ors['ALANG'];
	  $r['acl_longlevel'][]=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Security',$ors['AFUNCTION']);
	} else {
	  getDB()->execute('delete from ACL4 where AGRP = ?', array($ors['AGRP']));
	}
      } else $r['acl_uid'][]=$ors['AGRP'];
    }
    unset($rs);
    if($details) array_multisort($names,$r['acl_uid'],$r['acl_own'],$r['acl_level'],$r['acl_lang'],$r['acl_longlevel']);
    $r["oid"]=$oid;
    if(\Seolan\Core\Shell::admini_mode() && ($lang==TZR_DEFAULT_LANG)) {
      if(!$oid){
	$r['title']=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','module','text').' '.$mod->getLabel();
      }elseif((substr($oid,0,7)!='_field-') && \Seolan\Core\Kernel::objectExists($oid)){
	$otab=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$oid);
	// displaytext optimiserait ?
	$ro = $otab->display(['_publishedonly'=>true,'tplentry'=>TZR_RETURN_DATA,'oid'=>$oid,'_archive'=>'','_lastupdate'=>false]);
	$r["title"]=$ro['link'];
      }else{
	$r['title']=$mod->xset->desc[substr($oid,7)]->label;
      }
    }
    $r["lang"]=$lang;
    $r["moid"]=$mod->_moid;
    $r["classname"]=get_class($mod);
    return $r;
  }

  public function editObjectAccess(\Seolan\Core\Module\Module &$mod, $lang, $oid) {
    $rs=getDB()->fetchAll("select * from ACL4 where AMOID=? and ALANG=? and AKOID=?",array($mod->_moid,$lang,$oid));
    $r=array();
    $k = new \Seolan\Core\Kernel();
    $ls = $this->getObjectAccess($mod,$lang,$oid);
    $ors=array();
    foreach($rs as $ors) {
      $d1=$k->data_display(array("tplentry"=>TZR_RETURN_DATA, "oid"=>$ors['AGRP'],
				 '_options'=>array('error'=>'return')));
      if(is_array($d1)) {
	$r['acl_uid'][]=$ors['AGRP'];
	$r['acl_own'][]=$d1;
	$r['acl_level'][]=$ors['AFUNCTION'];
	$r['acl_longlevel'][]=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Security',$ors['AFUNCTION']);
      } else {
	getDB()->execute('delete from ACL4 where AGRP = ?',array($ors['AGRP']));
      }
    }
    unset($rs);
    return $r;
  }

  /// envoi d'un mail à l'utilisateur en cours, ou à $mail si $mail est renseigné
  public function sendMail2User($subject,$message,$email=NULL,$from=NULL,$archive=true,$filename=NULL,$filetitle=NULL,$stringattachment=NULL,$mime=NULL,$params=[]) {
    if(!\Seolan\Core\User::isNobody() && empty($email)) {
      $ors=getDB()->fetchRow('select * from USERS where KOID=?',array($this->_curoid));
      if($ors) {
	$email = $ors['email'];
      }
    }
    if(empty($email)) return;
    if(empty($from)) $from=TZR_SENDER_ADDRESS;
    $xmail=$this->getMail();
    if(isset($params['avoidCSXPrefix'])){
      unset($params['avoidCSXPrefix']);
      $params['sign'] = false;
    }
    $attachments = [];
    if (isset($filename)){
      $attachments = ['filename'=>$filename,'title'=>$filetitle];
    }
    if (isset($stringattachment)){
      $attachments = array_merge($attachments, ['string'=>$stringattachment,'title'=>$filetitle]);
    }
    $xmail->sendPrettyMail($subject,$message,$email,$from,$params,$attachments);
  }
  
  /*
   * Permettre la surcharge de Mail
   */
  protected function getMail():\Seolan\Library\Mail {
    global $TZR_MAIL_CLASS;
    if (!empty($TZR_MAIL_CLASS) && class_exists($TZR_MAIL_CLASS))
      return $TZR_MAIL_CLASS::objectFactory();
    else return \Seolan\Library\Mail::objectFactory();
  }
  
  // creation d'un nouvel utilisateur
  // fonction non autorisée depuis internet
  //
  function newUser($ar=NULL) {
    $p = new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get("tplentry");

    $login = $p->get("login");
    $passwd = $p->get("passwd");
    $thenlog = $p->get("_thenlog");
    $login=preg_replace('/([^[A-Za-z0-9]]+)/','',$login);
    $status=false;
    if(empty($login) && ($login!="root")) {
      $message=\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','aliasnotaccepted');
    } else {
      $cnt=getDB()->count('select COUNT(*) from USERS where alias=?',array($login));
      if($cnt>0) {
	$status=false;
	$message=\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','existing_user');
      } else {
	$xuser=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'USERS');
	$ar["alias"]=$login;
	$ar["passwd"]=$passwd;
	$ar["GRP"]=$p->get("GRP","local");
	$xuser->procInput($ar);
	if($thenlog) {
	  $xsession=new \Seolan\Core\Session();
	  $xsession->procAuth(array("login"=>$login, "password"=>$passwd));
	  $status=true;
	}
      }
    }
    $r=array("status"=>$status, "message"=>$message);
    if($tplentry==TZR_RETURN_DATA) {
      return $r;
    }
    return \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }

  function &getUserName($user) {
    static $names=array();
    if(!empty($names[$user])) return $names[$user];
    $ors=getDB()->fetchRow('SELECT * FROM USERS WHERE KOID=?',array($user));    
    if(!empty($ors)) {
      $names[$user]=array(1=>$ors['fullnam'],2=>$user);
    } else {
      $names[$user]=array(1=>'',2=>'');
    }
    return $names[$user];
  }

  /// recherche de la liste des utilisateurs et groupes
  static function getUsersAndGroups($valid=false,$all=true,$directory=NULL,$withFO=false,$withEmptyGrps=0) {
    if(empty($directory)) {
      $xuser = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=USERS');
      $cond=array();
      if(!$all || $all==='groups') $cond['GRP']=array('!=','');
      if($valid){
	if($xuser->fieldExists('DATEF') && $xuser->fieldExists('DATET')) {
	  $cond['DATET']=array('>=',date('Y-m-d'));
	  $cond['DATEF']=array('<=',date('Y-m-d'));
	}
	if($xuser->fieldExists('PUBLISH')) {
	  $cond['PUBLISH']=array('=',1);
	}
      }
      // filtre utilisateur FO
      if (!$withFO && $xuser->fieldExists('BO')) {
        $cond['BO'] = array('=', 1);
      }
      $select=(!empty($cond)?$xuser->select_query(array('cond'=>$cond)):'');;
      $acl_user=$xuser->browse(array('tplentry'=>TZR_RETURN_DATA,'order'=>'fullnam','select'=>$select,'pagesize'=>'9999','selectedfields'=>array('fullnam','GRP','alias')));
    } else {
      $xuser = \Seolan\Core\Module\Module::objectFactory($directory);
      if(empty($xuser)) return array();
      
      $cond=array();

      if($valid && $xuser->xset->fieldExists('DATEF') && $xuser->xset->fieldExists('DATET')) {
          $cond['DATET']=array('>=',date('Y-m-d'));
          $cond['DATEF']=array('<=',date('Y-m-d'));
      }
      $select=(!empty($cond)?$xuser->xset->select_query(array('cond'=>$cond)):'');;

      $acl_user=$xuser->browse(array('_local'=>1, 'select'=>$select, 'pagesize'=>9999, 'order'=>'fullnam', 'selectedfields'=>array('fullnam','GRP','alias')));
    }
    // On récupère tous les groupes et sous-groupes des utilisateurs actifs
    $grps = self::sGroups($acl_user['lines_oid']);
    // Si le paramètre "all" est activé, on ajoute les groupes "Tout le monde" et "Utilisateurs authentifiés" à la liste
    if($all && $all!=='users') $grps = array_merge($grps, array(TZR_GROUPID_NOBODY,TZR_GROUPID_AUTH));
    $xgrp=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=GRP');
    if ($withEmptyGrps)
      $grps = array_merge($grps, getDb()->fetchCol('select distinct KOID from '.$xgrp->getTable()));

    if(!$all) 
      $select=$xgrp->select_query(array('cond'=>array('KOID'=>array('=',$grps),
						      ' KOID'=>array(array('!=','and'),array(TZR_GROUPID_NOBODY,TZR_GROUPID_AUTH))
						      )
					)
				  );
    else 
      $select=$xgrp->select_query(array('cond'=>array(' KOID'=>array('=',$grps))));
    $acl_grp=$xgrp->browse(array('tplentry'=>TZR_RETURN_DATA, 'order'=>'GRP','pagesize'=>'1000','select'=>$select));
    $ar=array(&$acl_user,&$acl_grp);
    return $ar;
  }

  /// Vérifie le niveau de droit d'une fonction sur un module pour un ensemble d'oid
  /// $function : nom de la fonction à tester
  /// $mod : module à tester
  /// $oid : oid ou tableau d'oid à tester
  /// $user : uid du user à tester
  /// $lang : langue à tester
  /// @return : [ fonction accessible (bool), nb de regle parcourue (int), niveau max de droit (string) ]
  //VINCENT : le typage du parametre oid provoque une erreur lors des exports. Comme indiqué plus haut ce param est soit une string soit un array.
  static function secure8(string $function, $mod, $oid='', $user=null, $lang=null, $checkparent=true) {
    // Liste des droits nécessaires pour l'execution de la fonction

    // Récupération du module
    if(is_numeric($mod)){
      $mod=\Seolan\Core\Module\Module::objectFactory($mod);
    }
    // toute fonction "sécurisée" doit être définie dans secGroups
    $needed = $mod->secGroups($function);
    if(!is_array($needed)) {
      \Seolan\Library\Security::alert(__METHOD__." undefined sec levels for method '$function', module '{$mod->_moid} - {$mod->getLabel()}' - '".get_class($mod)."'");
    }

    // Recherche de la liste des groupes/users a tester
    if(!$user) $user=\Seolan\Core\User::get_current_user_uid();
    elseif(is_object($user)) $user=$user->_curoid;
    $g1 = \Seolan\Core\User::sGroups($user);

    // Si on est dans le groupe des administrateurs on a tous les droits 
    // (à condition que l'accessibilité web de la méthode soit définie, voir au dessus)
    if(in_array(TZR_GROUPID_ROOT, $g1)) 
      return array(true,1,'admin','',true);
    $g1=array_unique($g1); // déjà fait, voir sGroups

    // Nettoyage des oids
    if(!$lang) $lang=TZR_DEFAULT_LANG;
    if(!$mod->objectSecurityEnabled() || !$oid){
      $oids=array('');
    }elseif(!is_array($oid)){
      $oids=array($oid);
    }else{
      $oids=array_unique($oid,SORT_REGULAR);
    }
    $ooids=$oids;
    list($minlvl)=$needed;
    if($minlvl=='none') return array(true,1,'none','',true);
    // Condition SQL
    $cond='"'.implode('","',$g1).'"';
    // Vérifie le cache statique
    $idx=$cond.$minlvl.$mod->_moid.implode('',$oids).$lang;
    if(isset(\Seolan\Core\User::$_C81[$idx])){
      return \Seolan\Core\User::$_C81[$idx];
    }

    // Nombre de requete effectuée pour trouver un droit. Si à la fin du while celui ci vaut 0, c'est qu'aucune regle n'a été trouvé en base et qu'il faut donc faire jouer l'heritage
    $rqs=0;
    $found=false;
    // Défini si la valeur de retour a été forcée ou non.
    // Si à la fin, forced vaut false, c'est que tout le traitement a été fait, il n'y a pas eu de break
    // Cela permet de savoir si le retour provient d'un droit prioritaire (droit sur user, lu en cache) ou d'un droit normal
    $forced=false;
    $maxlvl=null;
    $maxlvluid='';
    $cache=true;
    $nboids=count($oids);
    $firstoid=$oids[0];
    // Si on ne demande le droit que d'un oid sur un user, on vérifié le cache SQL
    // Ce cas étant le plus courant, c'est le seul cache SQL disponible pour l'instant
    $cacheenabled=($mod->rightCacheEnabled() && $nboids==1 && self::oidIsUser($user)) && false;

    while(($oid=array_shift($oids))!==null) {
      // Vérifie si l'objet est locké ou pas
      if(($xlock=\Seolan\Core\Shell::getXModLock()) && !empty($oid) && !in_array('ro',$needed)) {
	$locked=$xlock->locked($oid, $lang);
	if(!empty($locked) && $user!=$locked['OWN']){
	  return array(false,1,'none','',true);
	}
      }

      // Cache SQL
      if($cacheenabled){
	\Seolan\Core\Audit::plusplus('secure8-cachesql');
	$cache=getDB()->fetchRow('select OK,AMAX from ACL4_CACHE where AFUNCTION=? AND AGRP=? and ALANG=? and AMOID=? AND AKOID=?', array($function, $user, $lang, $mod->_moid, $oid));
	if($cache){
	  \Seolan\Core\Audit::plusplus('secure8-cachesql-ok');
	  $rqs++;
	  $found=(bool)$cache['OK'];
          $maxlvl=$maxlvluid=NULL;
	  if(!empty($cache['AMAX'])) list($maxlvl,$maxlvluid)=explode(';',$cache['AMAX']);
          $forced=true;
	  break;
	}
      }

      // On recupere toutes les regles en base ou dans le cache statique
      $idx2=$cond.$mod->_moid.$oid.$lang;
      if(!isset(\Seolan\Core\User::$_C82[$idx2])){
	\Seolan\Core\Audit::plusplus('secure8-query');
	\Seolan\Core\User::$_C82[$idx2]=getDB()->fetchAll('SELECT * FROM ACL4 WHERE AGRP IN ('.$cond.')  AND AKOID=? AND AMOID=? AND ALANG=? /* secure8 */', array($oid, $mod->_moid, $lang));
      }
      $rs=\Seolan\Core\User::$_C82[$idx2];
      foreach($rs as $acl){
	$rqs++;
	$lvl=array_search($acl['AFUNCTION'], $needed);
	// Si on trouve une regle sur le user, elle a la priorité, on recupere le resultat et on stoppe la boucle
	if(self::oidIsUser($acl['AGRP'])){
	  $maxlvl=$lvl;
	  $found=($lvl!==false);
          $forced=true;
	  break;
	}
	if($lvl!==false) {
	  $found=true;
	}
	// Si le niveau detecté est supérieur ou qu'il est egal sur la personne demandée (cette derniere condition est necessaire pour etre sur qu'il s'agit d'un heritage ou pas)
	if(is_numeric($lvl) && ($lvl>$maxlvl || $maxlvl===null || ($lvl==$maxlvl && $acl['AGRP']==$user))){
	  $maxlvl=$lvl;
	  $maxlvluid=$acl['AGRP'];
	}
      }
      // Si une regle positive a été trouvée, pas la peine de passer à l'oid suivant
      if($found){
        $forced=true;
        break;
      }
    }
    // Si aucune règle n'a été trouvé en base et si on est pas déjà au niveau du module (oid vide), on va tester d'enventuels parents
    if($checkparent && !$rqs && !empty($firstoid) && $mod->objectSecurityEnabled()){
      $parents=$mod->getParentsOids($ooids);
      if(!empty($parents)){
        // Vérifie le cache statique
        $pidx=$cond.$minlvl.$mod->_moid.implode('',$parents).$lang;
        if(isset(\Seolan\Core\User::$_C81[$pidx])) return \Seolan\Core\User::$_C81[$pidx];
        // Si pas de cache statique, on verifie tous les parents
        foreach($parents as $parent){
          $res=$mod->mySecure($parent,$function,$user,$lang);
          $rqs+=$res[1];
          // Si les droit est ok ou que le retour est un resultat prioritaire, on stoppe la boucle
          if($res[0] || $res[4]) break;
        }
	$found=$res[0];
	$rqs+=$res[1];
        $res[1]=$rqs;
	$maxlvl=$res[2];
	$maxlvluid=$res[3];
        $forced=$res[4];
        // Enregistre le cache statique
        \Seolan\Core\User::$_C81[$pidx]=$res;
      }
    }

    // Recupere le niveau max de droit
    if(is_numeric($maxlvl)) $maxlvl=$needed[$maxlvl];
    elseif($maxlvl===NULL) $maxlvl='none';
    // Si la personne avec les droits les plus élevé est celle demandée à l'origine, on retourne une chaine vide pour siginifier que ce n'est pas de l'heritage
    if($maxlvluid==$user) $maxlvluid='';

    // Si on ne demande le droit que d'un oid sur un user, on met le résultat en cache SQL
    // Va de paire avec le select ACL4_CACHE quelques lignes au dessus
    if($cacheenabled && !$cache){
      \Seolan\Core\User::setCache($user, $function, $lang, $mod->_moid, $firstoid, $found, 'secure8', $maxlvl, $maxlvluid);
    }
    \Seolan\Core\User::$_C81[$idx]=array($found,$rqs,$maxlvl,$maxlvluid,$forced);
    return \Seolan\Core\User::$_C81[$idx];
  }
  static function setCache($user=null, $function, $lang=null, $moid, $oid, $ok, $comment, $maxlevel, $maxleveluid='') {
    if (empty($user))
      $user = \Seolan\Core\User::get_current_user_uid();
    if (empty($lang))
      $lang = TZR_DEFAULT_LANG;
    if (empty($comment))
      $comment = '';
    $aoid=self::getNewAoid();
    getDB()->execute("insert into ACL4_CACHE(AOID,ACOMMENT,AGRP,AFUNCTION,ALANG,AMOID,AKOID,OK,AMAX) ".
		     "values(?, ?, ?, ?, ?, ?, ?, ?, ?)",
		     [$aoid, $comment, $user, $function, $lang, $moid, $oid, (int)$ok, "$maxlevel;$maxleveluid"]);
  }
  
  /// retourne les oids pour lesquels des droits sont positionnées et ceux du module(clef vide)
  static function secure8oids($mod, $user=null, $lang=TZR_DEFAULT_LANG) {
    if (!$lang)
      $lang = TZR_DEFAULT_LANG;
    // Recherche de la liste des groupes/users a tester
    if (!$user) $user=\Seolan\Core\User::get_current_user_uid();
    elseif (is_object($user)) $user=$user->_curoid;
    $g1 = \Seolan\Core\User::sGroups($user);
    if (is_object($mod)) 
      $mod = $mod->_moid;
    $idx = "$user-$mod-$lang";
    // 
    if (!isset(\Seolan\Core\User::$_C8OIDS[$idx])) {
      $cond="";
      foreach($g1 as $goid) {
	if($cond=="") $cond="?";
	else $cond.=",?";
      }
      $rqa=$g1;
      $rqa[]=$mod;
      $rqa[]=$lang;
      $acls = getDB()->select("SELECT AKOID, AFUNCTION, AGRP FROM ACL4 WHERE AGRP IN ($cond)  AND AMOID=? AND ALANG=? order by AGRP desc /* secure8oids */", $rqa)->fetchAll(\PDO::FETCH_GROUP); // users before group
      \Seolan\Core\User::$_C8OIDS[$idx] = $acls;
    }
    return \Seolan\Core\User::$_C8OIDS[$idx];
  }
  
  /// Vérifie les droits sur un module/oid/user
  static function secure8oid(string $function, $mod, string $oid='', $user=null, $lang=TZR_DEFAULT_LANG, $checkparent=false) {
    if (!$lang)
      $lang = TZR_DEFAULT_LANG;
    if (\Seolan\Core\Shell::isRoot())
      return true;
    if (is_numeric($mod)) 
      $mod = \Seolan\Core\Module\Module::objectFactory($mod);
    $needed = $mod->secGroups($function);
    if (!is_array($needed)) 
      return false;
    list($minlvl) = $needed;
    if ($minlvl=='none') 
      return true;
    // Vérifie si l'objet est locké ou pas
    if(($xlock=\Seolan\Core\Shell::getXModLock())  && !empty($oid) && !in_array('ro', $needed)) {
      $locked=$xlock->locked($oid, $lang);
      if(!empty($locked) && $user!=$locked['OWN']){
        return false;
      }
    }
    $idx = "$function-".$mod->_moid."-$oid-$user-$lang-$checkparent";
    if (isset(\Seolan\Core\User::$_C8OID[$idx])) 
      return \Seolan\Core\User::$_C8OID[$idx];
    $acls = \Seolan\Core\User::secure8oids($mod, $user, $lang);
    // les règles utilisateur viennent avant celle des groupes
    if(!empty($acls[$oid]) && is_array($acls[$oid])) {
      foreach ($acls[$oid] as $acl) {
	$level = $acl['AFUNCTION'];
	if (in_array($level, $needed))
	  return \Seolan\Core\User::$_C8OID[$idx] = true;
	// si une règle est positionné sur l'utilisateur, elle a priorité
	if ($acl['AGRP'] == $user) 
	  break;
      }
    }
    if (!isset($acls[$oid]) && $checkparent) {
      $parents = $mod->getParentsOids(array($oid));
      foreach ($parents as $parentOid) {
        if (\Seolan\Core\User::secure8oid($function, $mod, $parentOid, $user, $lang, $checkparent)) {
          return \Seolan\Core\User::$_C8OID[$idx] = true;
        }
      }
    }
    if(isset($acls[''])) {
      // default le module
      foreach ($acls[''] as $acl) {
	$level = $acl['AFUNCTION'];
	if (in_array($level, $needed))
	  return \Seolan\Core\User::$_C8OID[$idx] = true;
	if ($acl['AGRP'] == $user)
	  break;
      }
    }
    return \Seolan\Core\User::$_C8OID[$idx] = false;
  }

  /// Vérifie si une methode sur une classe est accessible. Cela n'est possible que si la methode est acessible par tout le monde
  static function secure8class($class, $function){
    if(\Seolan\Core\Shell::isRoot()) return true;
    $secs=forward_static_call(array($class,"secGroups"),$function);
    if (!is_array($secs)) return false;
    return in_array('none',$secs);
  }

  /// Retourne le niveau max d'accès pour un module/oid
  static function secure8maxlevel($mod, ?string $oid='', $user=NULL, $lang=TZR_DEFAULT_LANG) {
    if(!$mod) return 'none';
    if(is_numeric($mod)) $mod=\Seolan\Core\Module\Module::objectFactory($mod);
    if(!$mod) return 'none';
    $sec=$mod->mySecure($oid,':list',$user,$lang);
    return $sec[2];
  }

  /// Vérifie si un oid représente un user
  static function oidIsUser($oid){
    return (strpos($oid,'USERS:')===0);
  }

  /// Teste si le user USERS:self existe
  static function selfExists() {
    static $done=false;
    static $exists=false;
    if(!$done) {
      $exists=getDB()->count('SELECT COUNT(KOID) FROM USERS WHERE KOID=?',array(TZR_SELF_USER));
      $done=true;
    }
    return $exists;
  }

  /// Génère un oid pour la table des droits
  static function getNewAoid(){
    return md5(uniqid('', true));
  }

  /// Récupere une variable de session en base de données
  static function getDbSessionData($varname){
    static $cache=array();

    $sessid=session_id();
    if(!$sessid) return null;
    if(!array_key_exists($varname,$cache)){
      $row=getDB()->fetchRow('SELECT value FROM _VARS WHERE sessid = ? AND name = ?',array($sessid,$varname));
      if($row) $cache[$varname]=unserialize($row['value']);
      else return null;
    }
    return $cache[$varname];
  }
  /// Récupere une variable de cache en base de données
  static function getDbCacheData($varname){
    return self::getDBSessionData(self::DB_SESSION_CACHE_PREFIX.$varname);
  }

  /// Ajoute/modifie une variable de session en base de données
  static function setDbSessionData($varname,$value){
    $sessid=session_id();
    if(!$sessid) return null;
    getDB()->execute('replace into _VARS (sessid,user,name,value) values (?,?,?,?)',
                array($sessid,\Seolan\Core\User::get_current_user_uid(),$varname,serialize($value)));
  }
  /// Ajoute/modifie une variable de cache en base de données
  static function setDbCacheData($varname,$value){
    return self::setDbSessionData(self::DB_SESSION_CACHE_PREFIX.$varname,$value);
  }

  /// Efface une variable de session en base de données
  static function deleteDbSessionDataUPD($varname){
    $sessid=session_id();
    if(!$sessid) return null;
    getDB()->execute('DELETE FROM _VARS WHERE sessid=? and name=?',array($sessid, $varname));
  }
  /// Efface une variable de cache en base de données
  static function deleteDbCacheDataUPD($varname){
    return self::deleteDbSessionDataUPD(self::DB_SESSION_CACHE_PREFIX.$varname);
  }

  /// Met à jour l'upd d'une variable de session en base de données
  static function updateDbSessionDataUPD($varname){
    $sessid=session_id();
    if(!$sessid) return null;
    getDB()->execute('update ignore _VARS set UPD=NOW() where sessid=? and name=?',array($sessid, $varname));
  }
  /// Met à jour l'upd d'une variable de cache en base de données
  static function updateDbCacheDataUPD($varname){
    return self::updateDbSessionDataUPD(self::DB_SESSION_CACHE_PREFIX.$varname);
  }

  /// Efface toutes les données d'une session en base de données
  static function clearDbSessionDataAndRightCache(){
    $sessid=session_id();
    if(!$sessid) return null;
    getDB()->execute('delete from _VARS where sessid = ?', array($sessid));
    getDB()->execute('delete from ACL4_CACHE where AGRP = ?', array(\Seolan\Core\User::get_current_user_uid()));
  }

  /// Efface toutes les données de session/cache périmées en base de données
  static function clearOldDbSessionDataAndRightCache(){
    // Efface le cache
    getDB()->execute('delete from _VARS where sessid is not null and name like "'.self::DB_SESSION_CACHE_PREFIX.'%" and UPD<="'.date('Y-m-d H:i:s',strtotime('-10 minutes')).'"');
    
    // Efface les sessions
    $rs=getDB()->fetchAll('select sessid from _VARS where name="last_activity" and UPD<=?',array(date('Y-m-d H:i:s',strtotime('-'.TZR_SESSION_DURATION.' seconds'))));
    foreach($rs as $ors) {
      getDB()->execute('DELETE FROM _VARS WHERE sessid=?',array($ors['sessid']));
    }
    unset($rs);

    // Efface le cache des droits des users qui n'ont plus de session actives
    $rs=getDB()->select('select distinct(AGRP) as AGRP from ACL4_CACHE');
    while($ors=$rs->fetch()) {
      $nb=getDB()->count('select count(*) from _VARS where name="last_activity" and user = ?', array($ors['AGRP']));
      if(!$nb) getDB()->execute('delete from ACL4_CACHE where AGRP = ?', array($ors['AGRP']));
    }
  }

  /// init user session cache for a certain category
  static function initUSCache($category){
    \Seolan\Core\User::$uscache[\Seolan\Core\User::get_current_user_uid()][$category] = array();
  }
  /// set user session cache for a certain category key to value
  static function setUSCache($category, $key, $value){
    \Seolan\Core\User::$uscache[\Seolan\Core\User::get_current_user_uid()][$category][$key] = $value;
  }
  /// get user cache for a certain category key
  static function getUSCache($category, $key=null){
    if ($key)
      return \Seolan\Core\User::$uscache[\Seolan\Core\User::get_current_user_uid()][$category][$key];
    else
      return \Seolan\Core\User::$uscache[\Seolan\Core\User::get_current_user_uid()][$category];
  }
}
?>
