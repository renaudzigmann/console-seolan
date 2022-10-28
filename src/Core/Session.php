<?php
namespace Seolan\Core;
class Session { // ? implements \Seolan\Core\ISecDeprecated {
  const CS8_EMPTY_CHECK = 'CS8_EmptyCheck';
  const LOGIN_ATTEMPTS_COUNT = 'session::login::attempts::';
  const LOGIN_BANISHED = 'session::login::banished::';
  const TOKEN_VALIDITY = 86400;   // token validity in second
  function __construct($ar=NULL) {}
  /// securite des fonctions accessibles par le web
  static function secGroups($function, $group=NULL) {
    $g=array('auth'=>array('none','ro','rw','rwv','admin'));
    $g['procAuth']=array('none','ro','rw','rwv','admin');
    $g['ajaxProcAuth']=array('none','ro','rw','rwv','admin');
    $g['procRestAuth']=array('none','ro','rw','rwv','admin');
    $g['newPassword'] = ['none','ro','rw','rwv','admin'];
    $g['procNewPassword'] = ['none','ro','rw','rwv','admin'];
    // voir Module/Ssl et supprimer
    $g['procForgotten']=array('none','ro','rw','rwv','admin');
    $g['procForgottenGenerate']=array('none','ro','rw','rwv','admin');
    $g['close']=array('none','ro','rw','rwv','admin');
    $g['goHistory']=array('none','ro','rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      else return $g[$function];
    }
    return false;
  }

  static function secList() {
    return array('none','ro','rw','rwv','admin');
  }
  /// Retourne l'url d'accès externe à une page
  public static function admin_gopage_url($pageUrl, $fq=true){
    return makeUrl(static::admin_url($fq, false),
		   ['moid'=>Module\Module::getMoid(XMODADMIN_TOID),
		    'template'=>'Core.layout/main.html',
		    'function'=>'portail',
		    'gopage'=>$pageUrl]);
  }
  /// Retourne l'url de l'administration (fq : avec nom de domaine, sess : avec session et bdx)
  public static function admin_url($fq=false, $sess=false) {
    $session="";
    $sid=session_id();
    if($sess && sessionActive() && !empty($sid)) $session='_bdx='.\Seolan\Core\Shell::$_bdxprefix.'_'.\Seolan\Core\Shell::$_bdx;
    if(!$fq) $url=TZR_SHARE_ADMIN_PHP.'?';
    else $url=$GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().TZR_SHARE_ADMIN_PHP.'?';

    if($sess) $url.=$session;
    $url.='&';
    return $url;
  }

  /// Retourne l'url complete du script en cours (sess : ajout des session (non utilise!), withdn : ajout du nom de domaine
  public static function complete_self($sess=true,$withdn=false) {
    $session='';
    $cachepolicy='';
    $sid=session_id();
    if($sess && sessionActive() && !empty($sid) && (\Seolan\Core\Shell::admini_mode() || !empty($GLOBALS['XSHELL']->activeHistory)))
      $session='_bdx='.\Seolan\Core\Shell::$_bdxprefix.'_'.\Seolan\Core\Shell::$_bdx;
    if(issetSessionVar('cachepolicy')) $cachepolicy=getSessionVar('cachepolicy');

    $url=$session;
    if(!empty($cachepolicy)) $url.='&amp;'.$cachepolicy.'=1';
    $lg=\Seolan\Core\Shell::getLangData();
    if(!\Seolan\Core\Shell::admini_mode() && ($lg!=TZR_DEFAULT_LANG || (!empty($_SESSION['LANG_DATA']) && $lg!=$_SESSION['LANG_DATA'])))
      $url.='&amp;_lang='.\Seolan\Core\Shell::getLangData();
    if (!empty($url))
      $url=$GLOBALS['TZR_SELF'].'?'.$url.'&amp;';
    else
      $url=$GLOBALS['TZR_SELF'].'?';

    if($withdn) {
      if(substr($url,0,1)!='/') $url='/'.$url;
      $url=$GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().$url;
    }

    return $url;
  }

  /// Retourne le nom de domaine complet sur lequel on est (pas de "/" à la fin)
  public static function makeDomainName(){
    if(!empty($_SERVER['SERVER_NAME'])){
      $protocol=(!empty($_SERVER['HTTPS'])?'https':'http');
      if(@$_SERVER['SERVER_PORT']!='80')
        $url=$protocol.'://'.$_SERVER['SERVER_NAME'].':'.@$_SERVER['SERVER_PORT'];
      else
        $url=$protocol.'://'.$_SERVER['SERVER_NAME'];
    }else{
      //cas de l'execution en cli on prend le param du local.php
      if(substr($GLOBALS['HOME_ROOT_URL'],-1,1)=='/')
        $url=substr($GLOBALS['HOME_ROOT_URL'],0,strlen($GLOBALS['HOME_ROOT_URL'])-1);
      else
        $url=$GLOBALS['HOME_ROOT_URL'];
    }
    return $url;
  }

  /// Prepare l'authentification
  public static function auth() {
    if (\Seolan\Core\Shell::admini_mode() && \Seolan\Core\User::authentified()) {
      if (isset($_REQUEST['id']) && preg_match('/^PASSWORD/', $_REQUEST['id'])){
	list($tokenmess, $token) = static::getToken($_REQUEST['id']);
	if ($token){
	  // close + redirect ici
	  (new $GLOBALS['TZR_SESSION_MANAGER']())->close(['_next'=>static::makeDomainName().$_SERVER['REQUEST_URI']]);
	  
	}
      }
      header('Location: '.$GLOBALS['TZR_SESSION_MANAGER']::admin_url().$GLOBALS['XSHELL']->getAuthenticatedHomeParams());
      die();
    }
    if (\Seolan\Library\CasAuthentification::active()){
      \Seolan\Library\CasAuthentification::getAuthCas()->forceAuthentication();
    }

    $um=\Seolan\Core\Module\Module::objectFactory(array('toid'=>XMODUSER2_TOID));
    \Seolan\Core\Shell::toScreen2('user','mod',get_object_vars($um));
    
    // saisie de mot de passe sur token
    if (isset($_REQUEST['id']) && preg_match('/^PASSWORD/', $_REQUEST['id'])){
      list($tokenmess, $token) = static::getToken($_REQUEST['id']);
      if (!$token){
	if (\Seolan\Library\CasAuthentification::active()){
	  unset($_REQUEST['id']);
	  \Seolan\Library\CasAuthentification::getAuthCas()->forceAuthentication();
	} else {
	  $_REQUEST['message'] = $tokenmess;
	}
      } else {
	// parametres pour la saisie du token : via la directory de l'alias, en pcpe
	$token['message'] = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_SessionMessages','fillin_password_please');
	list($passwdInputOk, $passwdInputData)  = $um->newPasswordInput($token['directoryname'],
						      $token['useroid'],
						      $token['which'],
						      $token);
	if ($passwdInputOk){
	  $token['passwdInput'] = $passwdInputData;
	}
	\Seolan\Core\Shell::toScreen1('token', $token);
      }
    }

    // données pour les directories gérant la saisie
    $um->prepareAuth('directories');


    $lang = strtolower($GLOBALS['LANG_USER']);
    \Seolan\Core\Labels::loadLabels('Seolan_Core_Session');
    setSessionVar('CSS',TZR_DEFAULT_CSS);
    return NULL;
  }
  // Connexion automatique
  public function procAuthAuto($ar=null) {
    $p = new \Seolan\Core\Param($ar);
    $autolog_user = @$_COOKIE['autolog_user'] ?: $p->get('autolog_user', 'local');
    $autolog_token = @$_COOKIE['autolog_token'] ?: $p->get('autolog_token', 'local');
    if(!\Seolan\Core\Shell::admini_mode() && User::isNobody() && !empty($autolog_user) && !empty($autolog_token)) {
      $base_token = \Seolan\Core\DbIni::get('autolog:'.$autolog_user, 'val');
      if($base_token && $base_token === $autolog_token) {
        try {
          $uid = getDB()->fetchOne('select KOID from USERS where LANG=? and alias=? and GRP not like "%GRP:1%"', [TZR_DEFAULT_LANG, $autolog_user]);
          if($uid) {
            $this->setUserFromUid($uid, 'procAuthAuto');
            return true;
          }
          \Seolan\Core\Logs::critical(__METHOD__,"procAuthAuto : '$autolog_user' inconnu ou appartenant au groupe admin");
          return false;
        }
        catch(\Exception $e) {
          \Seolan\Core\Logs::critical(__METHOD__,"procAuthAuto : '$autolog_user' - error  : ".$e->getMessage());
          return false;
        }
      }
    }
  }
  /**
   * Traitement de l'autentification
   * nopassword ne doit pas venir de la requete et est appelé à disparaitre
   */
  public function procAuth($ar=NULL) {
    if (defined('TZR_JSON_MODE') && in_array($_SERVER['CONTENT_TYPE'], ['application/json', 'application/vnd.api+json'])) {
      $ar = array_replace($ar, json_decode(file_get_contents('php://input'), 1) ?? []);
    }
    $p=new \Seolan\Core\Param($ar,['admini'=>0,'admin'=>0,'exception'=>false,'nopassword'=>null]);
    $countAttemptOnError = true;
    $tplentry=$p->get('tplentry');
    $onerror=$p->get('onerror');
    $exception = $p->get('exception');
    $alias = trim($p->get('login'));
    $admin=$p->get('admin');
    $admini=$p->get('admini');
    $arError = ['onerror'=>$onerror,'tplentry'=>$tplentry,'exception'=>$exception];

    $modUser = \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);

    /// fix utilisation 'nopassword', qui devrait être abandonné
    if ($p->is_set('nopassword','local')){
      \Seolan\Core\Logs::critical(__METHOD__,"'nopassword' $alias");
      try{
	$uid = getDB()->fetchOne('select KOID from USERS where LANG=? and alias=?', [TZR_DEFAULT_LANG,
$alias]);
	$this->setUserFromUid($uid, 'procAuth with \'nopassword\' argument');
	return true;
      }catch(\Exception $e){ 
	\Seolan\Core\Logs::critical(__METHOD__,"'nopassword' alias : '$alias' - error  : ".$e->getMessage());
	return $this->processError(array_merge($arError, ['message'=>'login_not_accepted']));
      }
    } /// </
    
    // contrôle des paramètres : login obligatoire (password aussi ?)
    // contrôle du bannissement
    if(static::userIsFailedToBan($alias)){
      return $this->processError(array_merge($arError, ['message'=>'user_failedtoban']));
    }
  
    // authentification
    $authResult = $modUser->procAuth($ar);

    if (!$authResult['ok']){
      // tentatives de connexions
      if($countAttemptOnError && !empty($alias)){
	list($attemptsOk, $attemptsMessage) = static::checkLoginAttempts($alias);
	if (!$attemptsOk && isset($attemptsMessage)){
	  $ar['message'] = $attemptsMessage;
	}
      }

      \Seolan\Core\Logs::notice(__METHOD__, implode(',', $authResult['messages']));

      if (count($authResult['messages']) == 1)
	$message = $authResult['messages'][0];
      else {
	// utlisateur ok, passwd ok : on précise
	if (!in_array('invalid_credential', $authResult['messages']) 
	    && !in_array('unknown_user', $authResult['messages'])
	    && in_array('invalid_period',$authResult['messages'])){
	  $message = 'invalid_period';
	} elseif (in_array('mail_is_empty', $authResult['messages'])) {
	  $message = 'mail_is_empty';
	} elseif (in_array('local_account_exists', $authResult['messages'])) {
	  $message = 'local_account_exists';
	} else {
	  $message = 'login_not_accepted';
	}
      }

      return $this->processError(array_merge($arError, ['message'=>$message]));
    }

    $user = $authResult['user'];
    $uid=$user->uid();
  
    // Utilisé pour la connexion auto
    if($p->is_set('rememberme') && $p->is_set('login') && $p->is_set('password')) {
      $autolog_user = $alias;
      $autolog_token = hash('sha256', $alias . hash('sha256', $p->get('password')));
      setcookie("autolog_user", $autolog_user, strtotime('+30 days'));
      setcookie("autolog_token", $autolog_token, strtotime('+30 days'));
      \Seolan\Core\DbIni::set('autolog:'.$autolog_user, $autolog_token);
    }

    // on efface le compteur de tentatives, sauf cas suid
    \Seolan\Core\DbIni::clear(static::LOGIN_ATTEMPTS_COUNT.$uid);
    \Seolan\Core\DbIni::clear(static::LOGIN_BANISHED.$uid);

    $this->initSession($user, ['admin'=>$admin, 'admini'=>$admini]);

    \Seolan\Core\Logs::update('login',0,session_id()); 
    
    return true;

  }
  /**
   * connecte après vérification un user donné
   * sans controle de mot de passe 
   * ex : ar[nopassword], gestion du cache json
   */
  public final function setUserFromUid($uid, $comment){
    if (empty(trim($comment)) || empty(trim($uid)))
      throw new \Seolan\Core\Exception\Exception("Empty uid or comment '$uid' '$comment'");
    $modUser = \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
    $ors = $modUser->getActiveUser($uid);
    if ($ors){
      \Seolan\Core\Logs::update('security',0,"set authenticated user $uid $comment");
      $user = new \Seolan\Core\User($uid);
      $this->initSession($user, ['admin'=>0,'admini'=>0]);
  
      if (strpos($_SERVER['HTTP_USER_AGENT'], TZR_USER_AGENT_MOBILE_APP) !== false && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (!empty($headers['X-Seolan-Device-Id'])) {
          $mod = \Seolan\Core\Module\Module::singletonFactory(XMODPUSHNOTIFICATIONDEVICE_TOID);
          if ($mod) {
            $mod->associateDeviceToUser($uid, $headers['X-Seolan-Device-Id']);
          }
        }
      }
    } else {
      throw new \Seolan\Core\Exception\Exception("uid '$uid' must exists and user must be active");
    }
  }
  /**
   * connecte un utilisateur authentifié
   * ex : CAS.
   * fonction qui ne doit pas être accessible par le web !
   */
  public final function setUserFromAuthenticatedAlias($alias, $comment){

    if (empty(trim($comment)) || empty(trim($alias)))
      throw new \Seolan\Core\Exception\Exception("Empty alias or comment '$alias' '$comment'");

    // recherche du user à partir de l'alias
    // \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID)->getAuthenticatedAlias($alias);
    $uid = getDB()->fetchOne('select KOID from USERS where alias=? and lang=?',
			     [$alias,TZR_DEFAULT_LANG]);

    if (!$uid || empty($uid)){
      throw new \Seolan\Core\Exception\Exception("Unknown authenticated alias : '$alias' '$comment'");
    }

    \Seolan\Core\Logs::update('security',0,"set authenticated user $alias > $uid $comment"); 

    $user = new \Seolan\Core\User($uid);
    
    $this->initSession($user, ['admin'=>0,'admini'=>0]);

  }
  /**
   * substitue le user identifié par son oid ($suid) 
   * ou remet en place le user initial (dont l'oid est stocké dans SUID)
   */
  public function substituteUser($suid=null){
    $uid = getSessionVar('UID');
    if (!issetSessionVar('SUID')){ // UID vers SUID
      if (!\Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID)->secure('','setuid')){
	\Seolan\Core\Logs::critical(__METHOD__,' not allowed ');
	return false;
      }
      \Seolan\Core\Logs::update('security',0,"substitute user setuid '$uid'>'$suid'"); 
      setSessionVar('SUID',$uid);
    } else {
      $suid = getSessionVar('SUID');
      \Seolan\Core\Logs::update('security',0,"substitute user reset uid '$uid'>'$suid'"); 
      clearSessionVar('SUID');
    } 

    $user = new \Seolan\Core\User($suid);
    $options = ['admin'=>getSessionVar('ADMIN'), 'admini'=>getSessionVar('ADMINI')];
    
    $this->initSession($user, $options);
    
  }
  /// initialisation de la session pour un user donné
  protected function initSession($user, $options=['admin'=>0,'admini'=>0]){

    $GLOBALS['XUSER']=$user;
    $uid = $user->uid();
    
    // on change la langue courante en fonction de ce qui est choisi dans l'utilisateur courant
    $lang = $GLOBALS['XUSER']->language();
    
    // On désactive le cache par groupe pour la connection
    // (sauf si c'est le scheduler qui est en train de recalculer une page)
    if(empty($_REQUEST['forcecache']) && !isset($_REQUEST['cacheoid'])) {
      $_REQUEST['TZR_USE_GROUP_CACHE'] = false;
    }

    if(isset($_REQUEST["LANG_USER"])) $lang[1]=$_REQUEST["LANG_USER"];
    if(isset($lang[0]) && ($lang[0]!='')) {
      $_SESSION['LANG_DATA']=$lang[0];
    }
    if(isset($lang[1]) && ($lang[1]!='')) {
      $_SESSION['LANG_USER']=$lang[1];
    }
    \Seolan\Core\Labels::reloadLabels();
    setSessionVar('authOptions', static::authOptions());
    setSessionVar('UID',$uid);
    setSessionVar('Groups',array_diff($user->groups(), [$uid]));
    setSessionVar('FullName',$user->fullname());
    setSessionVar('Email',$user->email());
    setSessionVar('ADMIN',$options['admin']);
    setSessionVar('ADMINI',$options['admini']);
    setSessionVar('CLIENT_INFO',array('IP'=>$_SERVER['REMOTE_ADDR'],'AGENT'=>$_SERVER['HTTP_USER_AGENT']));
    if(in_array(TZR_GROUPID_ROOT,explode('||',$user->_cur['GRP']))) setSessionVar('root','1');
    else clearSessionVar('root');
    clearSessionVar(TZR_SESSION_PREFIX.'modules');
    clearSessionVar('upref');
    setSessionVar('CSS',TZR_DEFAULT_CSS);
    \Seolan\Core\User::setDbSessionData('last_activity',1);
    loadIni();
    
    $GLOBALS['XSHELL']->clear_navbar(); // à voir c'est du BO uniquement
    if(\Seolan\Core\Shell::admini_mode() && !defined('TZR_JSON_MODE')) {
      // Ajout d'une trace de login dans les stats bo
      \Seolan\Module\BackOfficeStats\BackOfficeStats::count('','','',$user,'procAuth');
      // On génère les droits sur l'arborescence en fonction des sections
      $mod=\Seolan\Core\Module\Module::singletonFactory(XMODBACKOFFICEINFOTREE_TOID);
      $mod->prepareBOTree($user);
      
      // Verifie si l'utilisateur fait parti d'un projet pour la personnalisation du logo
      $mod=\Seolan\Core\Module\Module::singletonFactory(XMODPROJECT_TOID);
      if($mod && $mod->projectfield){
	$poid=getDB()->fetchOne("select {$mod->projectfield} from USERS where KOID=?", array($uid));
	if(!empty($poid)){
	  $d=$mod->display(array('oid'=>$poid,'tpelntry'=>TZR_RETURN_DATA,'selectefields'=>array('logo'),'_options'=>array('error'=>'return'),'tlink'=>false));
	  if (is_array($d))
	    setSessionVar('PROJECT',['logo'=>$d['ologo']->resizer,'title'=>$d['tlink']]);
	}
      }
    } // </BO
    // flag pour taches post connexion
    setSessionVar('justlogged', 1);

  }
  /**
   * depassement du nb max de tentatives de connexion
   * banissement temporaire du compte
   */
  static function checkLoginAttempts($alias){
    $ret = [true, ''];
    
    if ($alias == TZR_ROOTAUTH_ALIAS)
      return $ret;
    $uid = getDB()->fetchOne('select KOID from USERS where alias=?', [$alias]);
    if (!$uid)
      return $ret;
    
    $attempts = \Seolan\Core\DbIni::get(static::LOGIN_ATTEMPTS_COUNT.$uid, 'val') ?? 0;
    $attempts ++;
    \Seolan\Core\DbIni::set(static::LOGIN_ATTEMPTS_COUNT.$uid,$attempts);
    if ($attempts >= TZR_MAX_LOGIN_ATTEMPTS){
      $uf = static::userFailToBan($uid, $attempts);
      if ($uf)
	$ret = [false, 'max_login_attempts_error'];
      else
	$ret = [false, null];
    }
    return $ret;
  }
  /// banissement temporaire d'un user en fonction du nbre de tentatives en cours
  static function userFailToBan($uid, $attempts){
    if ($attempts%TZR_MAX_LOGIN_ATTEMPTS == 0){ // toutes les n tentatives on augmente le délai
      $nbban = intdiv($attempts, TZR_MAX_LOGIN_ATTEMPTS);
      $delay = min(TZR_LOGIN_BANISH_MAXTIME, rand($nbban*TZR_LOGIN_BANISH_TIME, ($nbban+1)*TZR_LOGIN_BANISH_TIME)); #secondes
      $dateto = date('Y-m-d H:i:s', strtotime(Date('Y-m-d H:i:s').' + '.$delay.' seconds'));
      \Seolan\Core\Logs::critical(__METHOD__,"user banishment $uid $nbban $delay (s) $dateto");
      \Seolan\Core\Logs::update('security', $uid, "user banishment $uid $nbban $delay (s) $dateto");
      \Seolan\Core\DbIni::set(static::LOGIN_BANISHED.$uid, $dateto);
      return true;
    } 
    return false;
  }
  /// user banished ?
  static function userIsFailedToBan($alias){
    $value = getDB()->fetchOne('select _VARS.value from _VARS,USERS where _VARS.name like "'.static::LOGIN_BANISHED.'%" and _VARS.name = CONCAT("'.static::LOGIN_BANISHED.'",USERS.KOID) and USERS.alias=?', [$alias]);
    return ($value && (date('Y-m-d H:i:s') <= unserialize($value)));
  }
  function ajaxProcAuth($ar){
    $ar['tplentry']=TZR_RETURN_DATA;
    if (isset($_POST['admin']) && $_POST['admin'] == 'true'
      && isset($_POST['admini']) && $_POST['admini'] == 'true')
      $ar['exception'] = true;
    try{
      $ret=$this->procAuth($ar);
      if($ret) die('ok');
      else die('nok');
    }catch(\Seolan\Core\Exception\Exception $e){
      die ("nok[[{$e->getMessage()}]]");
    }
  }

  /// Verifie l'exsitence de la variable SUID en session
  public static function setuid() {
    if(issetSessionVar('SUID')) return true;
    return false;
  }
  /// Vérification d'un token de changement de mot de passe
  static function getToken($tokenid){
    $tokenduration = (int)\Seolan\Core\Ini::get('passwordtokendduration')??24;
    $date=date('Y-m-d H:i:s',strtotime("-{$tokenduration} hours"));
    list($value, $tokenupd) = \Seolan\Core\DbIni::get($tokenid);
    if(!empty($value) && $tokenupd>=$date){
      $ors2=getDB()->fetchRow('select KOID, directoryname from USERS where alias=? LIMIT 1', [$value['alias']]);
      if(!empty($ors2['KOID'])){
	$value['id'] = $tokenid;
	$value['useroid'] = $ors2['KOID'];
	$value['directoryname'] = $ors2['directoryname'];
	$ret = [null, $value];
      } else {
	$ret = [static::getSysLabel('lost_password_noid'), null];
      }
    } else {
      $ret = [static::getSysLabel('lost_password_noid'), null];
    }
    return $ret;
  }
  /**
   * Initialisation du processus de changement de passe (expiré, perdu )
   * -> directory via mod user
   */
  function newPassword($ar=null){
    $p = new \Seolan\Core\Param($ar, ['login'=>null,'which'=>'forgotten']);
    list($ok, $message) = \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID)->newPasswordRequest($p->get('login'), $p->get('which'));
    if (!$ok){
      return $this->processError(['message'=>$message]);
    } else {
      // si admini et pas de next fourni
      $url = $p->get('_next');
      if (empty(trim($url))){
	$url = static::complete_self(true,true).http_build_query(['class'=>static::class, 'function'=>'auth',
								  'template'=>'Core.layout/auth.html',
								  'message'=>static::getSysLabel($message)]);
      } else {
	parse_str(@parse_url($url)['query'], $urlParams);
	if (!isset($urlParams['message']))
	  $url = makeUrl($url, ['message'=>static::getSysLabel($message)]);
      }
      \Seolan\Core\Shell::setNext($url);
    }
  }
  /**
   * traitement de validation d'un mot de passe
   */
  function procNewPassword($ar=null){
    $res = \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID)->procNewPasswordRequest($ar);
    if (!$res['ok']){
      $this->processError(['message'=>$res['message'],
			   'params'=>isset($res['params'])?$res['params']:[]]);
    } else {
      $p = new \Seolan\Core\Param($ar, []);
      if ($p->is_set('next')){
	$url = makeUrl($p->get('next'), 
		       ['login'=>$res['alias'], 
			'message'=>static::getSysLabel($res['message']),
			'_'=>date('ymdhis')
			]);
      } else {
	$url = static::complete_self(true,true).http_build_query(['class'=>static::class, 'function'=>'auth',
								  'alias'=>$res['alias']??$res['alias'],
								  'template'=>'Core.layout/auth.html',
								  'message'=>static::getSysLabel($res['message'])]);
      }
      \Seolan\Core\Shell::setNext($url);
    }
  }
  /// Envoie un mot de passe perdu
  /// @deprecated (voir module Ssl et supprimer)
  function procForgotten($ar=NULL) {
  }

  /// Fermeture de la session ouverte pour ce user
  function close($ar=NULL){
    $p=new \Seolan\Core\Param($ar, array());
    $next=$p->get('_next');
    $id=session_id();
    if(empty($next)) $next=$GLOBALS['TZR_SESSION_MANAGER']::complete_self();
    \Seolan\Core\User::clearDbSessionDataAndRightCache();
    \Seolan\Core\Logs::update('logout',0,'User logout');
    if(\Seolan\Core\Shell::admini_mode()){
      // Ajout d'une trace de login dans les stats bo
      \Seolan\Module\BackOfficeStats\BackOfficeStats::count('','','',NULL,'close');
    }
    $params=session_get_cookie_params();
    // Suppression du cookie de connexion automatique
    if($_COOKIE['autolog_user']) {
      \Seolan\Core\DbIni::clear('autolog:'.$_COOKIE['autolog_user'], false);
      setcookie("autolog_user", NULL, 1);
      setcookie("autolog_token", NULL, 1);
    }
    setcookie(session_name(),'',time()-3600,$params["path"], $params["domain"]);
    session_destroy();
    if (\Seolan\Library\CasAuthentification::active()){
      \Seolan\Library\CasAuthentification::getAuthCas()->logout();
    }
    header('Location: '.$next);
    exit;
  }
  /// Retour en erreur en tenant compte du onerror et du tplentry
  public function processError($ar){
    $p = new \Seolan\Core\Param($ar, ['params'=>[],'exception'=>false]);
    $tplentry=$p->get('tplentry');
    $onerror = $p->get('onerror');
    $message = $p->get('message','local');
    $exception = $p->get('exception','local');
    $params = $p->get('params', 'local');
    // fonctionne (à la casse près) que l'on ait un label ou un code de label ...
    $message = static::getSysLabel($message);
    if ($tplentry == TZR_RETURN_DATA || $exception){
      \Seolan\Core\Logs::critical(__METHOD__,$message);
      if ($exception)
	throw new \Seolan\Core\Exception\Exception($message);
      return false;
    }
    if (!empty($onerror)){
      if(!preg_match('@(^https?://|^/)@',$onerror))
        $onerror=static::complete_self().$onerror;
      if(!preg_match('/(message=)/',$onerror) && !empty($message)){
	$params['message'] = $message;
      }
      if (!empty($params)){
	$onerror = makeUrl($onerror, $params);
      }
      header('Location: '.$onerror);
      die();
    } else {
      $GLOBALS['XSHELL']->redirect2error(['message'=>(static::getSysLabel('login_not_accepted'))]);
    }
  }
  function goHistory($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $hid=$p->get('hid');
    if($_SESSION[HISTORY_SESSION_VAR][$hid]['url']){
      header('Location:'.$_SESSION[HISTORY_SESSION_VAR][$hid]['url'].'&gohistory='.$hid.'&_nohistory=1');
      die();
    }elseif(!empty($_SESSION[HISTORY_SESSION_VAR][$hid])){
      \Seolan\Core\Shell::toScreen1('br',$_SESSION[HISTORY_SESSION_VAR][$hid]);
    }
  }
  /// authentification API Rest JWT (pour Json)
  /// pas d'appel à procAuth car pas de session !
  public function procRestAuth($ar = NULL) {
    $GLOBALS['XSHELL']->setCache(false);
    if ($_SERVER['CONTENT_TYPE'] == 'application/json' || $_SERVER['CONTENT_TYPE'] == 'application/vnd.api+json') {
      $p = new \Seolan\Core\Param(json_decode(file_get_contents('php://input'), 1));
    } else {
      $p = new \Seolan\Core\Param($ar);
    }
    $login = $p->get('login');
    $password = $p->get('password'); // clear text or hash
    if (empty($login) || empty($password)) {
      $GLOBALS['JSON_START_CLASS']::registerError(400, 'Bad Request');
      return;
    }
    $user = getDB()->fetchRow(
      "select * from USERS where alias=? and (passwd=? OR passwd=?)",
      [$login, $password, \Seolan\Field\Password\Password::hash($password)]
    );
    if (!$user) {
      $GLOBALS['JSON_START_CLASS']::registerError(401, 'invalid credentials');
      return;
    }
    if (isset($user['PUBLISH']) && $user['PUBLISH'] != 1) {
      $GLOBALS['JSON_START_CLASS']::registerError(401, 'disabled user account');
      return;
    }
    if (isset($user['DATEF']) && isset($user['DATET']) && ($user['DATEF'] > date('Y-m-d') || date('Y-m-d') > $user['DATET'])) {
      $GLOBALS['JSON_START_CLASS']::registerError(401, 'expired user account');
      return;
    }
    // validité du mot de passe, banissement ? 
    \Seolan\Core\Logs::debug(__METHOD__ . ' login ' . $user['KOID'] . ' ' . $user['fullnam']);
    $ts = time();
    $token = [
      'id' => str_replace('USERS:', '', $user['KOID']),
      'name' => $user['fullnam'],
      'exp' => $ts + static::TOKEN_VALIDITY,
    ];
    $jwt = \Firebase\JWT\JWT::encode($token, static::getKey());
    return [
      'token' => $jwt,
      'token_type' => 'Bearer',
      'exp' => $ts + static::TOKEN_VALIDITY,
      'iat' => $ts,
    ];
  }

  /// controle du token d'identification JWT, header "Authorization: Bearer {token}"
  /// RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
  public static function checkRestAuth() {
    $headers = apache_request_headers();
    $authorization = $headers['Authorization'] ?? $headers['authorization'];
    if (!isset($authorization)) {
      throw new \Seolan\Core\Exception\Exception('credentials required', 401);
    }
    $token = explode(' ', $authorization)[1];
    if (!$token) {
      throw new \Seolan\Core\Exception\Exception('credentials required', 401);
    }
    try {
      $decoded = \Firebase\JWT\JWT::decode($token, static::getKey(), ['HS256']);
    } catch (\Exception $e) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' JWT::decode exception ' . $e->getMessage());
      throw new \Seolan\Core\Exception\Exception('invalid token', 401);
    }
    $user = new \Seolan\Core\User('USERS:' . $decoded->id);
    self::initSession($user, $options = ['admin' => 0, 'admini' => TZR_ADMINI]);
  }

  protected static function getKey() {
    $key = \Seolan\Core\Ini::get('jwt_key');
    if (!$key) {
      header('HTTP/1.1 500 Seolan Server Error');
      \Seolan\Core\Logs::critical('missing xini jwt_key');
      die;
    }
    return $key;
  }
  // service de login / autorisations (CAS)
  public static function authOptions(){
    if (\Seolan\Library\CasAuthentification::active()){
      $cas=\Seolan\Library\CasAuthentification::getAuthCas();
      return (Object)['mngt'=>'TZR.AuthCAS','options'=>json_encode(['url'=>$cas->loginUrl()])];
    } else {
      return false;
    }
  }
  // récupère le label ou retourne le contenu original si getSyLabel ne 
  // le trouve pas (cad retourne strotolower de label)
  private static function getSysLabel($label){
    $olabel = $label;
    $label = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session', $label);
    return  ($label == strtolower($olabel))?$olabel:$label;
  }
}
?>
