<?php
namespace Seolan\Core;

class Shell implements \Seolan\Core\ISec, \Seolan\Core\IRewriting {
  const QUIT_NOT_FOUND = 404;
  const QUIT_FORBIDDEN = 403;
  const QUIT_FATAL_ERROR = 500;

  static $_log=NULL;
  static $_bdxprefix='0';
  static $_bdx=0;
  static public $upgrades=[
      '20190201'=>'critical'
      ,'20190222'=>''
      ,'20191121'=>''
      ,'20200206'=>''
      ,'20200313'=>''
  ];
  protected static $_authToken = null;
  public $_uniqid = null;
  public $tpldata = array();
  public $rawdata = array();
  public $_debug = false;
  public $_starttime=0.0000;
  public $_cache = true;
  public $_callback = NULL;
  public $_function = NULL;
  public $_skip = false;
  public $fullurl='';
  public $_next=NULL;		/* dans le cas ou on envisage une redirection apres traitement */
  /** @var \Seolan\Core\Labels */
  public $labels = null;

  function __construct($ar='*',$cache=true) {
    $this->fullurl=getCurrentPageUrl();
    $this->_starttime=TZR_START_TIME;
    $this->setCache($cache);
    $this->_loginurl=$GLOBALS['TZR_SELF'].'?';
    self::$_log=\Seolan\Core\Logs::_initLogFile();
    \Seolan\Core\Integrity::chkCriticalRuntimeParameters();
  }

  function disableCache(){
    $this->setCache(false);
  }
  function setCache($cachevalue = true){
      $this->_cache = $cachevalue;
  }
  /// Recupere le bdx en cours
  function getBdx(){
    if(@$_REQUEST['_bdxnewstack']==1){
      if(!empty($_SESSION['BACK'])) $mx=max(array_keys($_SESSION['BACK']))+1;
      else $mx=1;
      \Seolan\Core\Shell::$_bdxprefix=$mx;
      \Seolan\Core\Shell::$_bdx=0;
    } elseif(!empty($_REQUEST['_bdx']) && preg_match('/^([0-9]+)_([0-9]+)$/i',$_REQUEST['_bdx'],$matches)){
      if(!empty($matches[2]) || $matches[2]==='0'){
	\Seolan\Core\Shell::$_bdxprefix=$matches[1];
	\Seolan\Core\Shell::$_bdx=$matches[2];
      }else{
	\Seolan\Core\Shell::$_bdxprefix='0';
	\Seolan\Core\Shell::$_bdx=$matches[1];
      }
    }
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['index']=array('none','ro','rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return NULL;
  }
  function secList() {
    return array('none','ro','rw','rwv','admin');
  }

  static public function _function() {
    return $GLOBALS['XSHELL']->_function;
  }
  // Retourne le niveau de traitement à effectuer (0 => tout, 1 => desactive les callbacks, 2 => desactive les menus)
  static public function _raw() {
    return $GLOBALS['XSHELL']->_raw;
  }
  // Retourne vrai si la requete exécuté est en mode ajax
  static public function _ajax() {
    return $GLOBALS['XSHELL']->_ajax;
  }
  // Retourne vrai si la requete exécuté doit etre traité pour une iframe
  static public function _iframeencode() {
    return $GLOBALS['XSHELL']->_iframeencode;
  }
  static public function uniqid(){
    if(empty($GLOBALS['XSHELL']->_uniqid)){
      $GLOBALS['XSHELL']->_uniqid=$_REQUEST['_uniqid']??uniqid();
    }
    return $GLOBALS['XSHELL']->_uniqid;
  }
  public function setLoginUrl($url) {
    $this->_loginurl=$url;
  }
  public function getLoginUrl() {
    return $this->_loginurl;
  }
  public static function isRoot() { return getSessionVar('root'); }
  public static function admin_mode() { return getSessionVar('ADMIN');  }

  /// rend vrai si cette console n'implémente pas le mode traduction : monolingue.
  public static function getMonoLang() {
    return count($GLOBALS['TZR_LANGUAGES'])<=1;
  }
  public static function getLangData($l=NULL,$redo=false,$unsetcache=false) {
    static $computed_lang=NULL;
    if(empty($l) && !$redo && !empty($computed_lang)){
      return $computed_lang;
    }
    $lg=TZR_DEFAULT_LANG;
    if(!empty($l)) $lg=$l;
    elseif(\Seolan\Core\Shell::admini_mode()) {
      if(!empty($_REQUEST['LANG_DATA'])) $lg=$_REQUEST['LANG_DATA'];
      elseif(!empty($_SESSION['LANG_DATA'])) $lg=$_SESSION['LANG_DATA'];
      elseif(!empty($_REQUEST['_lang']))     $lg=$_REQUEST['_lang'];
      elseif(!empty($GLOBALS['LANG_DATA']))  $lg=$GLOBALS['LANG_DATA'];
    } else {
      if(!empty($_REQUEST['LANG_DATA'])) $lg=$_REQUEST['LANG_DATA'];
      elseif(!empty($_REQUEST['_lang'])) $lg=$_REQUEST['_lang'];
      elseif(!empty($_SESSION['LANG_DATA'])) $lg=$_SESSION['LANG_DATA'];
    }
    if(!array_key_exists($lg,$GLOBALS['TZR_LANGUAGES'])) $lg=TZR_DEFAULT_LANG;
    if(empty($l)) $computed_lang=$lg;
    if($unsetcache) $computed_lang=NULL;
    return $lg;
  }
  // Changement de langue par programme 
  static $langDataSave = null;
  static $langUserSave = null;
  public static function setLang($lang) {
    self::$langDataSave = self::getLangData();
    self::$langUserSave = self::getLangUser();
    $_REQUEST['LANG_DATA'] = $lang;
    $_REQUEST['LANG_USER'] = $lang;
    self::getLangData(NULL, true);
  }
  // retour à la langue d'origine
  public static function unsetLang() {
    $_REQUEST['LANG_DATA'] = self::$langDataSave;
    $_REQUEST['LANG_USER'] = self::$langUserSave;
    self::getLangData(NULL, true);
    self::$langDataSave = null;
    self::$langUserSave = null;
  }
  public static function getLangTrad($l=NULL,$notrad=NULL) {
    if(\Seolan\Core\Shell::admini_mode() && empty($_REQUEST['_notrad']) && empty($notrad)) {
      $lg=NULL;
      if(!empty($l)) $lg=TZR_DEFAULT_LANG;
      elseif(!empty($_REQUEST['LANG_TRAD'])) $lg=TZR_DEFAULT_LANG;
      elseif(!empty($_SESSION['LANG_TRAD'])) $lg=TZR_DEFAULT_LANG;
      if(array_key_exists($lg, $GLOBALS['TZR_LANGUAGES'])) return $lg;
    }
    return NULL;
  }
  public static function getLangUser($l=NULL) {
    if(empty($l)) {
      if(!empty($_REQUEST['LANG_USER'])) $l=$_REQUEST['LANG_USER'];
      elseif(!empty($_SESSION['LANG_USER'])) $l=$_SESSION['LANG_USER'];
      elseif(!empty($_REQUEST['_lang'])) $l=$_REQUEST['_lang'];
      elseif(!empty($GLOBALS['LANG_USER'])) $l=$GLOBALS['LANG_USER'];
    }
    if(array_key_exists($l, $GLOBALS['TZR_ADMIN_LANGUAGES'])) return $l;
    else return TZR_DEFAULT_LANG;
  }
  public static function langDataIsDefaultLanguage($ar=NULL){
    return \Seolan\Core\Shell::getLangData()==TZR_DEFAULT_LANG;
  }
  /// Calcule et retourne le next
  public function captureNext(){
    $next = static::getNext();
    if (empty($next) && !empty($_REQUEST['_next']))	
      $next = $_REQUEST['_next'];
    $more='';
    if(!empty($this->_nextData))
      $more=(strpos($next,'?')===false?'?':'&').http_build_query($this->_nextData);
    if(!empty($this->_nextHash))
      $more.='#'.$this->_nextHash;
    return $next.$more;
  }
  /// Recupere le _next en cours
  public static function getNext(){
    return $GLOBALS['XSHELL']->_next;
  }
  /// Change le _next
  public static function setNext($url=NULL) {
    $_REQUEST['_next']='';
    // Raccourcis, le calcul se fait par rapport à la page qui fait l'appel et non par rapport au script en cours
    if($url=='back') $url=\Seolan\Core\Shell::get_back_url($GLOBALS['XSHELL']->_skip?-1:-2);
    elseif($url=='refresh') $url=\Seolan\Core\Shell::get_back_url($GLOBALS['XSHELL']->_skip?0:-1);

    if(empty($url)) $GLOBALS['XSHELL']->_next=NULL;
    elseif(preg_match('@(^https?://|^/)@',$url)) $GLOBALS['XSHELL']->_next=$url;
    else $GLOBALS['XSHELL']->_next=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().$url;
  }
  /// Ajoute des données au _next
  public static function setNextData($var, $value) {
    $GLOBALS['XSHELL']->_nextData[$var]=$value;
  }
  /// Ajoute un fichier au _next
  public static function setNextFile($file, $name, $mime, $inline=false) {
    $GLOBALS['XSHELL']->_nextData['filename'] = $_REQUEST['filename'] = $file;
    $GLOBALS['XSHELL']->_nextData['fileoriginalname'] = $_REQUEST['fileoriginalname'] = $name;
    $GLOBALS['XSHELL']->_nextData['filemime'] = $_REQUEST['filemime'] = $mime;
    if ($inline) {
      $GLOBALS['XSHELL']->_nextData['fileinline'] = $_REQUEST['fileinline'] = $inline;
    }
  }
  /// Ajoute une ancre au _next
  public static function setNextHash($hash) {
    $GLOBALS['XSHELL']->_nextHash=$hash;
  }
  /// Vérifie si un _next est positionné
  public static function hasNext(){
    if(!empty($GLOBALS['XSHELL']->next) || !empty($_REQUEST['_next'])) return true;
    return false;
  }

  static function admini_mode() {
    if(defined('TZR_ADMINI')){
      return TZR_ADMINI;
    }
    return false;
  }

  static function scheduler_mode() {
    return defined('TZR_SCHEDULER') && TZR_SCHEDULER;
  }

  function showStack($ar) {
    $ar2=debug_backtrace() ;
    VarDump($ar2,false,0,2,1);
  }

  /**
   * @desc FIXME: Termine l'éxecution de PHP. Met à jour les logs, puis apelle
   * critical_exit().
   * @param array $ar paramètres
   * @param int $quit_mode Renseigne le type de motif de fin d'execution,
   * voir \Seolan\Core\Shell::QUIT_*
   */
  function quit($ar, $quit_mode = self::QUIT_FATAL_ERROR) {
    if(is_string($ar)) $message = $ar;
    if(is_array($ar)) $message = $ar['message'];
    \Seolan\Core\Logs::critical("\Seolan\Core\Shell::quit",' panic '.$message.' '.$_SERVER['QUERY_STRING'].' '.backtrace2());
    $exception = new \Seolan\Core\Exception\Exception($message,$quit_mode);
    throw $exception;
    exit(); // ? (RR)
  }
  /// Affecte une variable smarty $prefix_ (ecrase la valeur existantes)
  static function &toScreen1($prefix,&$p1) {
    if($prefix!=TZR_RETURN_DATA) $GLOBALS['XSHELL']->tpldata[$prefix]=$p1;
    return $p1;
  }
  /// Affecte une variable smarty $prefix_$p1 (ecrase la valeur existantes)
  static function &toScreen2($prefix,$p1,$p2) {
    if($prefix!=TZR_RETURN_DATA){
      if(!@is_array($GLOBALS['XSHELL']->tpldata[$prefix][$p1])) $GLOBALS['XSHELL']->tpldata[$prefix][$p1]=array();
      $GLOBALS['XSHELL']->tpldata[$prefix][$p1]=$p2;
    }
    return $p2;
  }
  /// Affecte une variable smarty $prefix_ (merge avec la valeur existantes)
  static function toScreen1Merge($prefix,&$p1){
    if($prefix!=TZR_RETURN_DATA){
      if(is_array($GLOBALS['XSHELL']->tpldata[$prefix]))
	$GLOBALS['XSHELL']->tpldata[$prefix]=array_merge_recursive($GLOBALS['XSHELL']->tpldata[$prefix],$p1);
      else
	$GLOBALS['XSHELL']->tpldata[$prefix]=$p1;
    }
    return $p1;
  }
  /// Supprime la variable smarty $prefix_ ou $prefix_$p1
  static function clearScreen($prefix,$p1=NULL){
    if(!empty($p1)) unset($GLOBALS['XSHELL']->tpldata[$prefix][$p1]);
    else unset($GLOBALS['XSHELL']->tpldata[$prefix]);
  }


  static function &from_screen($prefix,$var=NULL) {
    if($prefix==TZR_RETURN_DATA) {
      $nul = NULL;
      return $nul;
    }
    if(isset($var)) return $GLOBALS['XSHELL']->tpldata[$prefix][$var];
    else return $GLOBALS['XSHELL']->tpldata[$prefix];
  }
  static function exit_tzr($message=NULL) {
    exit();
  }


  // empile une info dans la pile de la barre de navig
  //
  function push_navbar($label, $url) {
    if(!is_array($this->tpldata['nav'])) $this->tpldata['nav']=array();
    if(!is_array($this->tpldata['nav']['url'])) {
      $this->tpldata['nav']['url']=array();
      $this->tpldata['nav']['label']=array();
    }
    array_push($this->tpldata['nav']['url'],$url);
    array_push($this->tpldata['nav']['label'],$label);
    if(empty($this->tpldata['nav']['lastlabel'])) {
      $this->tpldata['nav']['lasturl']=$url;
      $this->tpldata['nav']['lastlabel']=$label;
    }
  }

  // depile une info dans la pile de la barre de navig
  //
  function pop_navbar() {
    if(is_array($this->tpldata['nav'])) {
      $i=count($this->tpldata['nav']['url']);
      unset($this->tpldata['nav']['url'][$i-1]);
      unset($this->tpldata['nav']['label'][$i-1]);
    }
  }

  function title_navbar($title) {
    $this->tpldata['nav']['title']=$title;
  }

  // nettoyage de la barre de navig
  //
  function clear_navbar() {
    if(@is_array($this->tpldata['nav'])) {
      $this->tpldata['nav']['label']=array();
      $this->tpldata['nav']['url']=array();
    } else {
      $this->tpldata['nav']=array();
    }
  }

  public function set_navbar_pagetitle($label, $url) {
    $this->tpldata['nav']['lasturl']=$url;
    $this->tpldata['nav']['lastlabel']=$label;
  }

  // appel d'une fonction a chaque affichage de page
  function set_callback($f) {
    unset($this->_callback);
    $this->_callback=array();
    $this->_callback[]=$f;
  }

  function add_callback($f) {
    $this->_callback[]=$f;
  }

  function _load_user($ar=NULL) {
    if (!isset($GLOBALS['XUSER'])) {
      if(issetSessionVar('UID'))
        $GLOBALS['XUSER'] = new \Seolan\Core\User(array('UID' => getSessionVar('UID')));
      else
        $GLOBALS['XUSER'] = new \Seolan\Core\User();
    }
    $lang = $GLOBALS['XUSER']->language();
    if(empty($_SESSION['LANG_DATA'])) {
      if(!empty($lang[0])) {
	$GLOBALS['LANG_DATA']=$lang[0];
      }
      if(empty($GLOBALS['LANG_DATA'])) $GLOBALS['LANG_DATA']=TZR_DEFAULT_LANG;
    }
    if(empty($_SESSION['LANG_USER'])) {
      if(!empty($lang[1])) {
	$GLOBALS['LANG_USER']=$lang[1];
      }
      if(empty($GLOBALS['LANG_USER'])) $GLOBALS['LANG_USER']=TZR_DEFAULT_LANG;
    }
  }
  /// token authentifié ?
  protected function checkAuthToken(){
    static::$_authToken = null;
    if (!isset($_REQUEST[TZR_AUTHTOKEN_NAME]))
      return;
    $tokenMngt = \Seolan\Core\Token::factory();
    list($ret, $token) = $tokenMngt->check($_REQUEST[TZR_AUTHTOKEN_NAME]);
    if ($ret != 'ok')
      return;
    static::$_authToken = $token; 
  }
  /// tests ?
  public static function previewMode(){
    return (isset(static::$_authToken) && static::$_authToken['type'] === 'preview');
  }
  protected function security_check($class, $function, $moid, $lang, &$koid, $interactive=false) {
    if(!empty($moid)) \Seolan\Library\SecurityCheck::assertIsNumeric($moid, __METHOD__.' bad moid');
    if(!empty($class)) \Seolan\Library\SecurityCheck::assertIsClass($class, __METHOD__.' bad class '.$class);
    if(!empty($lang)) \Seolan\Library\SecurityCheck::assertIsLang($lang, __METHOD__.' bad lang');
    if(!empty($koid) && !is_array($koid)){
      \Seolan\Library\SecurityCheck::assertIsExtendedKOID($koid, __METHOD__." bad koid '{$koid}'");
    }
    if(!empty($function)) \Seolan\Library\SecurityCheck::assertIsSimpleString($function, __METHOD__.' bad function');

    if(is_array($koid)){
      $all=$koid;
      foreach($all as $k=>&$v){
        if(\Seolan\Core\Kernel::isAKoid($k)){
          $ok=$this->security_check($class,$function,$moid,$lang,$k,true);
        }else{
          $ok=$this->security_check($class,$function,$moid,$lang,$v,true);
        }
        if(!$ok){
          unset($koid[$k]);
        }
      }
      unset($v);
      if(!empty($koid)) return true;
      else{
        $ok=false;
        $koid=$all;
      }
      if(!empty($moid)) {
        if(!empty($class)) $mod=new $class(array('moid'=>$moid, 'tplentry'=>TZR_RETURN_DATA));
        else $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid, 'tplentry'=>TZR_RETURN_DATA));
      }
    }else{
      // Assure la compatibilité avec d'ancienne url sans moid. Ne fonctionne que si la methode est publique ou en root.
      // Si d'autres cas sont rencontrés, il faut faire en sorte de tjs passer par un module..
      if(empty($moid) && \Seolan\Core\User::secure8class($class,$function)) {
        $ok=true;
      }else{
        $ok=false;
        if(!empty($moid)) {
          $props=\Seolan\Core\Module\Module::findParam($moid);
          if(!empty($class)) {
            $mod=new $class(array('moid'=>$moid, 'tplentry'=>TZR_RETURN_DATA));
            $ok=$mod->secure($koid,$function,$GLOBALS['XUSER'],$lang);
          } else {
            $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid, 'tplentry'=>TZR_RETURN_DATA));
            if(!empty($mod)) $ok=$mod->secure($koid,$function,$GLOBALS['XUSER'],$lang);
          }
        }
      }
    }
    if($ok) {
      debug("access ok |$class|$function|$moid|$lang|$koid|");
      if(!\Seolan\Core\User::isNobody()){
        $suid=getSessionVar('SUID');
        if(!empty($suid)){
          $xuser=new \Seolan\Core\User(array('UID'=>$suid));
          \Seolan\Module\BackOfficeStats\BackOfficeStats::count($koid, $lang, $moid, $xuser, $function);
        }else{
          \Seolan\Module\BackOfficeStats\BackOfficeStats::count($koid, $lang, $moid, $GLOBALS['XUSER'], $function);
        }
      }
      return true;
    }else{
      if(!is_array($koid)){
        $message=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Security','permission_denied');
        \Seolan\Core\Logs::update('security',$koid, $message.'<!-- |'.$class.'|'.$function.'|'.$moid.'|'.$lang.'|'.$koid.'| failed -->');
        \Seolan\Core\Logs::critical('security', "access denied |$class|$function|$moid|$lang|$koid| user ".\Seolan\Core\User::get_current_user_uid());
      }
      \Seolan\Core\Logs::debug(__METHOD__.' '.implode('', array_merge(['interactive'=>$interactive],$_REQUEST)));
      if(!$interactive) {
        // Si mode ajax, on renvoie une erreur 401
        if(\Seolan\Core\Shell::_ajax()){
          header("HTTP/1.1 401 Unauthorized");
          exit(0);
        }elseif(\Seolan\Core\Shell::_iframeencode()){
          header("HTTP/1.1 401 Unauthorized");
          echo '401 Unauthorized';
          exit(0);
        }
        if($_SERVER['REQUEST_METHOD']=='GET') $next=$_SERVER["REQUEST_URI"];

        // Authentification CAS
	if (\Seolan\Library\CasAuthentification::active()){
          debug("cas authentification (access denied |$class|$function|$moid|$lang|$koid|)");
          if(empty($next)) {
            $moidadmin=\Seolan\Core\Module\Module::getMoid(XMODADMIN_TOID);
            $next=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(false,false).'&template=Core.layout/main.html&function=portail&moid='.$moidadmin.
              '&message='.urlencode(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Session','reauth_lost_post'));
          }
	  // gère le redirect vers l'auth cas si plus de session CAS/SSO
	  $cas = \Seolan\Library\CasAuthentification::getAuthCas(['next'=>$next]);
	  $cas->forceAuthentication();

          debug("cas authentification OK (access |$class|$function|$moid|$lang|$koid|)");
          $sessionclass=$GLOBALS['TZR_SESSION_MANAGER'];
          $session=new $sessionclass();
	  // connection de l'utilisateur
	  try{
	    $session->setUserFromAuthenticatedAlias($cas->getUser(), 'CAS; ticket : "'.$cas->getTicket().'" user : "'.$cas->getUser().'"');
	    // verification que tout ok cependant et pas accès à une ressource autorisée
	    $sec=$this->security_check($class, $function, $moid, $lang, $koid, true);
	    if(!$sec){
	      throw new \Seolan\Core\Exception\Exception('cas authentication error '.$cas->getUser());
	    }
	  }catch(\Exception $e){
	    \Seolan\Core\Logs::critical(__METHOD__, "error setting authenticated cas alias : $alias $comment");
	    debug("cas authentication error");
	    $cas->redirectToError();
	  }

          return $sec;
        }
        if(empty($next)) {
	  $next=self::getAuthenticatedHomeParams();
        }
        // on utilise la gestion specifique d'erreur du module si elle existe
        if(isset($mod) && is_object($mod)) $mod->secFailHandler($function,$koid,NULL,$next,NULL);
        // dans tous les cas on ne doit pas aller plus loin
        \Seolan\Core\Shell::redirect2auth($message,$next);
      } else {
        return false;
      }
    }
    return true;
  }
  /// Renvoie les paramètres PHP nécessaires à l'affichage de la page d'accueil authentifié
  function getAuthenticatedHomeParams() {
    $moidadmin=\Seolan\Core\Module\Module::getMoid(XMODADMIN_TOID);
    return '&template=Core.layout/main.html&function=portail&moid='.$moidadmin;
  }
  static function redirect2auth($message=NULL,$next=NULL){
    if(empty($next)) {
      $next=$GLOBALS['XSHELL']->getAuthenticatedHomeParams();
    }
    if(empty($message)) $message=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Security','permission_denied');
    header('Location: '.$GLOBALS['XSHELL']->getLoginUrl().'&message='.urlencode($message).'&next='.urlencode($next));
    die();
  }
  static function redirect2error($ar=NULL) {
    header('Location: '.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&skip=1&template=Core.message.html&message='.rawurlencode($ar['message']));
    die();
  }

  // traitement du changement de langue courante
  //
  protected function _changeLang(){
    if(isset($_REQUEST['_lang'])) {
      $_SESSION['LANG_DATA'] = $_REQUEST['_lang'];
      $_SESSION['LANG_USER'] = $_REQUEST['_lang'];
    }
    if(isset($_REQUEST['_lang_data'])) {
      $_SESSION['LANG_DATA'] = $_REQUEST['_lang_data'];
    }
    if(isset($_REQUEST['_lang_user'])) {
      $_SESSION['LANG_USERS'] = $_REQUEST['_lang_user'];
    }
    if(isset($_REQUEST['_lang_trad'])) {
      $_SESSION['LANG_TRAD'] = $_REQUEST['_lang_trad'];
    }
    if(isset($_REQUEST['LANG_DATA'])) $_SESSION['LANG_DATA']=$_REQUEST['LANG_DATA'];
    if(isset($_REQUEST['LANG_USER'])) $_SESSION['LANG_USER']=$_REQUEST['LANG_USER'];
    if(isset($_REQUEST['LANG_TRAD'])) $_SESSION['LANG_TRAD']=$_REQUEST['LANG_TRAD'];
  }
  function error($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array("message"=>"Unexpected error"));
    $GLOBALS['XSHELL']->tpldata[""]["message"]=$p->get("message");
  }

  static function getTemplate(){
    if(isset($_REQUEST['_template'])){
     return $_REQUEST['_template'];
    } elseif(isset($_REQUEST['template'])){
     return $_REQUEST['template'];
    }
    return null;
  }

  /// Définit les templates à utiliser
  function setTemplates($templates=NULL,$check_if_public=false) {
    static $insidefile_checked=false;

    if(empty($templates)) $templates=\Seolan\Core\Shell::getTemplate();
    else $_REQUEST['_template']=$templates;

    // Test pour eviter que le template ne serve de cross scripting
    checkIfTemplateIsSecure($templates,$check_if_public);

    // Test sur insidefile s'il existe
    if(!$insidefile_checked && !empty($_REQUEST['insidefile'])){
      checkIfTemplateIsSecure($_REQUEST['insidefile'],true);
      if(defined('TZR_ALLOW_USER_TEMPLATES') && file_exists($GLOBALS['USER_TEMPLATES_DIR'].$_REQUEST['insidefile'])){
        $_REQUEST['insidefile']=$GLOBALS['USER_TEMPLATES_DIR'].$_REQUEST['insidefile'];
      }
      $insidefile_checked=true;
    }

    // Vérifie si on doit utiliser un template local
    if(is_array($templates)) $loop=&$templates;
    else $loop=array($templates);
    foreach($loop as &$template){
      if(defined('TZR_ALLOW_USER_TEMPLATES') && !file_exists($GLOBALS['TEMPLATES_DIR'].$templates) &&
	 (isset($GLOBALS['USER_TEMPLATES_DIR']) && file_exists($GLOBALS['USER_TEMPLATES_DIR'].$templates))){
        $template=$GLOBALS['USER_TEMPLATES_DIR'].$template;
      }
    }

    if(is_array($templates)) $template=$templates[0];
    else $template=$templates;
    return array($template,$templates);
  }

  function run($ar='*') {
    \Seolan\Core\Logs::debug('\Seolan\Core\Shell::run: start');

    // Chargement d'une page via l'url du contenu
    if(!empty($_REQUEST['_direct'])){
      header('Location: '.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.\Seolan\Core\Module\Module::getMoid(XMODADMIN_TOID).
	     '&template=Core.layout/main.html&function=portail&gopage='.
	     urlencode(str_replace('&_direct=1','',$_SERVER['REQUEST_URI'])));
      exit(0);
    }

    // on verifie que le back est enregistre
    if(\Seolan\Core\Shell::admini_mode() && empty($_SESSION['BACK'])) $_SESSION['BACK']=array();

    // chargement des parametres classe, moid, function ...
    list($moid, $class, $f, $template, $templates, $_disp, $mime) = $this->runTimeParameters();

    // Connexion auto
    $xsession = new $GLOBALS['TZR_SESSION_MANAGER']();
    $arAuth = [];
    if (strpos($_SERVER['HTTP_USER_AGENT'], TZR_USER_AGENT_MOBILE_APP) !== false && function_exists('apache_request_headers')) {
      $headers = apache_request_headers();
      if (!empty($headers['X-App-Data'])) {
        $appData = json_decode($headers['X-App-Data']);
        
        if ($appData !== null && isset($appData->autolog_user) && isset($appData->autolog_token)) {
          $arAuth['autolog_user'] = $appData->autolog_user;
          $arAuth['autolog_token'] = $appData->autolog_token;
        }
      }
    }
    
    $xsession->procAuthAuto($arAuth);

    // insertion et traitement du back
    if((\Seolan\Core\Shell::admini_mode() || !empty($this->activeHistory)) && ($f!='back')) {
      $this->getBdx();
      $this->_skip=!empty($_REQUEST['_skip']) || !empty($_REQUEST['skip']) || $f=='goto1';
      if(!$this->_skip) $this->insert_back();
    } elseif($f=='back') {

      $this->_load_user();

      if (TZR_USE_APP)
	static::processApplications();
      
      $this->back();
      $this->run($ar);
      return;
    }

    // test pour eviter que le template ne serve de cross scripting
    if(!preg_match('@^([_a-z0-9\./-\\\\]*)$@i',$class))
      \Seolan\Library\Security::alert("(e3) class <$class> is not secure");

    // dans le cas ou il y a une demande de changement de langue
    if(!empty($_REQUEST['_setlang'])) {
      $this->_changeLang();
    }

    if(!\Seolan\Core\Shell::admini_mode() && \Seolan\Core\Ini::get('site_closed') && ($f!='error') &&
       ($template!='Core.layout/auth.html') && ($f!='procAuth'))
      $this->redirect2error(array('message'=>'Sorry, at this point the site is closed'));

    // si le cache est utilisable (Front)
    if($this->_cache) {
      $cache = new \Seolan\Core\Cache();
      $cache->setCachePolicy();
      // essayer de servir la page depuis le cache
      if ($cache->delivery($template, $mime, $_disp, $ar)) {
        \Seolan\Core\Logs::debug(__METHOD__.' page delivered by cache');
        $this->exit_tzr();
      }
      if(\Seolan\Core\Cache::putPageInServerCache() && \Seolan\Core\System::tableExists('_PCACHE') && ($cachemodule=\Seolan\Core\Module\Module::singletonFactory(XMODCACHE_TOID))) {
        $cachemodule->registerPageFailure($this->fullurl);
      }
    }

    // vérification de version
    checkUpgradeVersion();
    
    // chargement de l'utilisateur
    $this->_load_user();
    if (Ini::get('wr_active')) {
      $waitingRoom = Ini::get('wr_queue_class') ?? '\Seolan\Module\WaitingRoom\Queue';
      $waitingRoom::check();
    }
    // chargement des tokens authentifiés
    $this->checkAuthToken();
    
    if (strpos($_SERVER['HTTP_USER_AGENT'], TZR_USER_AGENT_MOBILE_APP) !== false && function_exists('apache_request_headers')) {
      $headers = apache_request_headers();
      if (!empty($headers['X-Seolan-Device-Id'])) {
        $device = \Seolan\Core\Module\Module::singletonFactory(XMODPUSHNOTIFICATIONDEVICE_TOID);
        if (!empty($device)) {
          $device->associateDeviceToUser(\Seolan\Core\User::get_current_user_uid(), $headers['X-Seolan-Device-Id']);
          $device->updateLastActivity($headers['X-Seolan-Device-Id']);
        } else {
          Logs::notice(__METHOD__, 'MOBILE APP : aucun module devices trouvé.');
        }
      } else {
        Logs::notice(__METHOD__, 'MOBILE APP : entête HTTP X-Seolan-Device-Id manquante');
      }
    }
    
    // application 'principale' : rewriting, gabarits et plus
    $bootstrapApplication = null;
    $rewriter = $this;
    $applicationHeaders = null;
    // Initialise les applications
    if (TZR_USE_APP) {
       static::processApplications();
      // rewriter specifique eventuel (remplace le defaut)
      $bootstrapApplication = \Seolan\Module\Application\Application::getBootstrapApplication();
      if ($bootstrapApplication != null){
        $rewriter = $bootstrapApplication->getRewriter()??$rewriter;
        if( !empty($bootstrapApplication->infotreeauth)){
          $this->setLoginUrl($GLOBALS['TZR_SELF'].'?alias='.$bootstrapApplication->infotreeauth);
        }
      }
    }
    // decodage de la requete
    if(!\Seolan\Core\Shell::admini_mode() && \Seolan\Core\Ini::get('url_rewriting')){
      $rewriter->decodeRewriting($_SERVER['REQUEST_URI']);
      // mise à jour des paramètres d'execution
      list($moid, $class, $f, $template, $templates, $_disp, $mime) = $this->runTimeParameters();
    }

    // Redirections
    if (!TZR_ADMINI && $modredirect_moid = \Seolan\Core\Module\Module::getMoid(XMODREDIRECT_TOID)) {
      $modredirect = \Seolan\Core\Module\Module::objectFactory($modredirect_moid);
      $modredirect->doIfHaveMatch($_SERVER['REQUEST_URI']);
    }

    // creation de l'object de replication si necessaire
    if ($replication_moid = \Seolan\Core\Module\Module::getMoid(XMODREPLICATION_TOID)) {
      if (\Seolan\Module\Replication\Replication::initsetRunning())
        die('Initialisation en cours, patientez ...');
      $GLOBALS['XREPLI'] = \Seolan\Core\Module\Module::objectFactory($replication_moid);
    }

    // creation d'un objet qui permet de charger les labels fonction de la langue
    $this->labels = new \Seolan\Core\Labels();

    \Seolan\Core\Labels::loadLabels('Seolan_Core_General');
    \Seolan\Core\Labels::loadLabels('Seolan_Core_Field_Field');
    \Seolan\Core\Labels::loadLabels('Seolan_Module_User_User');
    // cas ou il y a une classe
    if(!empty($moid) && empty($class)) {
      $ob = \Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid,'interactive'=>true));
      \Seolan\Core\Logs::debug(__METHOD__.' class is empty, moid='.$moid);
    } elseif(!empty($class) && !is_a($this, $class)){
      // dans le cas ou la classe n'existe pas encore, on essaie d'inclure
      // le fichier qui correspond a la classe
      if(!class_exists($class)) {
  	   header('Location: /index.php');
  	   exit;
      }
      \Seolan\Core\Logs::debug(__METHOD__.' run class is '.$class);
      $ob = new $class(array('interactive'=>true));
    } else {
      \Seolan\Core\Logs::debug(__METHOD__.' class is empty and moid is empty, search applications');

      // recherche d'un objet pour appliquer la fonction
      // ? lien avec l'application boostrap ?
      $ob=\Seolan\Module\Application\Application::getObjectForFunctionExecution($f);
      if ($ob){
          \Seolan\Core\Logs::debug(__METHOD__.' "run object" for function "'.$f.'" set from application(s) : '.spl_object_hash($ob).',class : '.get_class($ob));
      }
      if(!$ob) $ob=$this;
      $class=get_class($ob);
    }

    // cas ou il y a une fonction
    $LANG_DATA=\Seolan\Core\Shell::getLangData();
    \Seolan\Core\Lang::setLocale($LANG_DATA);
    if(!empty($f)) {
      // verification des droits : on créé un tableau avec tout les elements succeptibles d'etre utilisés
      if(isset($_REQUEST['oidit'])) $oid['oidit']=$_REQUEST['oidit'];
      if(!empty($_REQUEST['_selectedok']) && $_REQUEST['_selectedok']=='ok' && !empty($_REQUEST['_selected']))
	$oid['_selected']=&$_REQUEST['_selected'];
      if(!empty($_REQUEST['oid'])) $oid['oids']=&$_REQUEST['oid'];
      else $oid['oids']='';

      \Seolan\Core\Logs::notice('uri_decode',@$_SERVER['REQUEST_URI']."->class=$class&function=$f&oid=".(is_array($oid)?var_export($oid,true):$oid)."&template=".is_array($template)?var_export($template,true):$template."&moid=$moid&lang=$LANG_DATA");
      $this->security_check($class, $f, $moid, $LANG_DATA, $oid);
      $oid2='';
      if(isset($oid['oidit'])) $oid2=$oid['oidit'];
      elseif(!empty($oid['oids'])) $oid2=$oid['oids'];
      if(method_exists($ob, 'secObjectAccess') && !is_array($oid2)) {
	$ob->secObjectAccess($f, $LANG_DATA, $oid2);
      }

      // mecanisme permettant d'eviter les doubles insert en gerant deux etats
      if(isset($_REQUEST["uniqid"])) {
	$uniqid=$_REQUEST["uniqid"];
	if($_SESSION["LASTCOMMITEDFORM"]==$uniqid){
	  \Seolan\Core\Logs::notice('\Seolan\Core\Shell::run','Form already submitted');
	  \Seolan\Core\Shell::redirect2error(array('message'=>\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','operation_duplicated','text')));
	} else {
	  $_SESSION["LASTCOMMITEDFORM"]=$uniqid;
	}
      }
    }
    $ar2=array();
    if(!isset($_REQUEST['tplentry'])) $ar2['tplentry']='';
    $ar2['interactive']=true;

    // appel de la fonction de la page en cours
    if(!empty($f)) {
      $ob->$f($ar2);
    }

    // redirection sur la page next. calcul du next eventuel.
    // le next positionne par l'application avec la methode setNext est prioritaire sur
    // la query string (_next)
    if (empty($this->_next) && !empty($_REQUEST['_next']))
      $this->setNext($_REQUEST['_next']);
    if(!empty($this->_next)) {
      if(\Seolan\Core\Shell::_iframeencode())
        \Seolan\Core\Shell::setNextData('_iframeencode',1);
      $more='';
      if(!empty($this->_nextData))
        $more=(strpos($this->_next,'?')===false?'?':'&').http_build_query($this->_nextData);
      if(!empty($this->_nextHash))
	$more.='#'.$this->_nextHash;
      \Seolan\Core\Logs::debug('redirect to '.$this->_next.$more);
      // envoyer le résultat immediadement
      sessionClose();
      header('Location: '.$this->_next.$more);
      header('Connection: close');
      header('Content-Encoding: none');
      header('Content-Length: 1');
      echo ' ';
      ob_end_flush();
      flush();
      exit(0);
    }
    if(\Seolan\Core\Shell::admini_mode() && \Seolan\Core\Shell::_raw()<2) {
      // generation des menus specifiques des modules
      if(method_exists($ob, 'nav')) {
	$ar2['_function']=$f;
	$ob->nav($ar2);
      }
      if(method_exists($ob,'actionlist')) {
	$navig=$ob->actionlist1();
	\Seolan\Core\Shell::toScreen1('inav', $navig);
      }
    }

    // appel des callback
    if(!\Seolan\Core\Shell::_raw() && !empty($this->_callback)) {
      for($i=0;$i<count($this->_callback);$i++) {
        $func=$this->_callback[$i];
        if(!empty($func))
          $this->$func();
      }
    }

    // si la réponse est pas déjà caculéé
    if (!empty($this->response) && !empty($this->response->complete)) {
      $display = $this->response->content;
    } else {

      list($template,$templates)=$this->setTemplates(NULL,true);

      // par defaut instanciation de templetisation
      // par defaut template en parametre
      if(is_array($templates)) {
        $template=$templates[0];
      } else $template=$templates;

      $tplApplication = null;
      if (TZR_USE_APP && $bootstrapApplication != null) {
	$tplApplication = $bootstrapApplication;
      }
      $xtemplate = new \Seolan\Core\Template($template, $tplApplication);
      $xtemplate->set_glob(array("application" => $tplApplication));

      // recherche des libelles en fonction de la langue lorsqu'on est en mode admnistration.
      if(issetSessionVar('ADMIN') || ($template=='proc_auth.html')||($template=='Core.layout/auth.html')) {
        \Seolan\Core\Labels::loadLabels('admini');
      }
      $labels = $this->labels->get_labels(array('selectors'=>array('global'),'local'=>true));

      // application du template
      // recherche des donnees a transmettre en auto
      $xtemplate->set_glob(array('templates'=>&$templates));
      if(is_array($ar)) {
	   $xtemplate->set_glob($ar);
      }
      if(isset($labels) && is_array($labels)) {
	   $xtemplate->set_glob(array('labels'=>&$labels));
      }

      \Seolan\Core\Shell::toScreen2('post', 'param_url', urlencode('&'.http_build_query($_REQUEST)));

      \Seolan\Core\Logs::debug('\Seolan\Core\Shell::run: before parse file');

      $display = $xtemplate->parse($this->tpldata,$this->rawdata);

    }

    // Suppression des parametress contextuel en session
    if(issetSessionVar('message')) clearSessionVar('message');
    if(issetSessionVar('alerts')) clearSessionVar('alerts');
    if(issetSessionVar('_reloadmenu')) clearSessionVar('_reloadmenu');
    if(issetSessionVar('_reloadmods')) clearSessionVar('_reloadmods');

    // Met à jour le token d'activité
    if(!\Seolan\Core\User::isNobody()){
      \Seolan\Core\User::updateDbSessionDataUPD('last_activity');
    }

    $charset = \Seolan\Core\Lang::getCharset();
    $headers=array();
    $headers[]='Content-type: '.$mime.'; charset='.strtolower($charset);
    $headers[]='Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT';
    if (TZR_USE_APP && $bootstrapApplication != null){
      $applicationHeaders = $bootstrapApplication->getComplementHeaders();
    }
    if ($applicationHeaders != null){
      $headers = array_merge($headers,$applicationHeaders);
    }
    \Seolan\Core\Logs::debug(__METHOD__." -> headers : ".implode('|', $headers));
    if(!empty($_disp)) {
      $filename = is_array($templates) ? $templates[0] : $templates;
      $headers[]='Content-disposition: '.$_disp.'; filename='.$filename;
    }

    \Seolan\Core\Logs::debug('\Seolan\Core\Shell::run: start sending file');

    // mettre en cache et envoyer le contenu
    if ($this->_cache){
      $cache->storeAndDeliver($display, $template, $ar, $headers);
    } else {
      foreach($headers as $header){
	header($header,true);
      }
      if( (empty($_SERVER['HTTP_USER_AGENT']) || substr($_SERVER['HTTP_USER_AGENT'],0,6) != "Smarty")
	  && $charset != TZR_INTERNAL_CHARSET){
	convert_charset($display,  TZR_INTERNAL_CHARSET, $charset);
      }

      // Dans le cas d'une soumission d'un formulaire via iframe caché, on me le réultat dans un textarea pour empecher l'execution des scripts dans l'iframe
      if(\Seolan\Core\Shell::_iframeencode())
	echo '<textarea id="_iframeencode">'.@htmlentities($display,ENT_COMPAT,$charset).'</textarea>';
      else
	echo $display;
    }
    \Seolan\Core\Logs::debug('\Seolan\Core\Shell::run: end sending file');

    \Seolan\Core\Logs::debug('\Seolan\Core\Shell::run: end');

    // log audit infos
    \Seolan\Core\Logs::notice('\Seolan\Core\Shell::run',\Seolan\Core\Audit::show());
    \Seolan\Core\Logs::notice('\Seolan\Core\Shell::run',\Seolan\Core\Audit::autoloadLog());

    return true;
  }
  /**
   * initialisation et execution des applications
   */
  protected static function processApplications(){
    \Seolan\Module\Application\Application::init();
    \Seolan\Module\Application\Application::runApps();
  }
  /**
   */
  protected function responseMime($template){
    // determination du type mime de la reponse
    // par défaut text/html
    $mime='text/html';
    $mimes['html']='text/html';
    $mimes['xml']='text/xml';
    $mimes['svg']='image/svg+xml';
    $mimes['css']='text/css';
    $mimes['js']='application/x-javascript';
    $mimes['downl']='application/x-octet-stream';
    $mimes['txt']='text/plain';
    $mimes['js']='application/x-javascript';
    $mimes['png']='image/png';
    $mimes['kml']='application/vnd.google-earth.kml+xml';
    $mimes['json']='application/json';
    if(empty($_REQUEST['_mime']) && preg_match('/\.([a-z0-9]{1,6})$/i',$template,$eregs)) {
      $extension=$eregs[1];
      if(!empty($mimes[$extension]))
	$mime=$mimes[$extension];
    }
    if(!empty($_REQUEST['_mime']) && in_array($_REQUEST['_mime'], $mimes)) {
      $mime=$_REQUEST['_mime'];
    }
    return $mime;
  }
  /**
   * paramètres du run et de l'env. d'execution qui dependent de la requete
   * -> classe/moid/toid/function etc
   */
  protected function runTimeParameters(){
    $class = $moid = $template = $templates = $disp = $mime = null;
    // access des singletons via leur toid
    if (empty($_REQUEST['moid']) && !empty($_REQUEST['toid']) && !is_array($_REQUEST['toid'])){
      $newmoid = \Seolan\Core\Module\Module::getMoid($_REQUEST['toid']);
      if (!empty($newmoid)){
	$_REQUEST['moid'] = $newmoid;
      }
    }
    $moid = (empty($_REQUEST['moid'])?NULL:$_REQUEST['moid']);
    if(\Seolan\Core\Shell::admini_mode()){
      if(empty($moid)){
	if(!empty($_REQUEST['_class'])) $class=$_REQUEST['_class'];
	elseif(!empty($_REQUEST['class'])) $class=$_REQUEST['class'];
      }
    }else{
      if(!empty($_REQUEST['_class'])) $class=$_REQUEST['_class'];
      elseif(!empty($_REQUEST['class'])) $class=$_REQUEST['class'];
    }

    // chargement de la function
    if(!empty($_REQUEST['_function'])) $this->_function=$_REQUEST['_function'];
    elseif(!empty($_REQUEST['function'])) $this->_function=$_REQUEST['function'];

    // on regarde si il s'agit d'une url 'simple' c'est a dire qu'on
    // ne calcule rien dans les callback etc.
    $this->_raw=!empty($_REQUEST['_raw'])?$_REQUEST['_raw']:0;
    $this->_ajax=!empty($_REQUEST['_ajax']);
    $this->_iframeencode=!empty($_REQUEST['_iframeencode']);

    // gabarits
    list($template, $templates)=$this->setTemplates();

    // attachment/disposition
    $disps=array('attachment','inline');
    if(!empty($_REQUEST['_disp']) && in_array($_REQUEST['_disp'], $disps)) {
      $disp=$_REQUEST['_disp'];
    }

    // dans le cas ou on veut des chemins absolus pour les donnes
    if(!empty($_REQUEST['_fqn'])) {
      $GLOBALS['SELF_PREFIX']=$GLOBALS['HOME_ROOT_URL'].$GLOBALS['SELF_PREFIX'];
    }

    // type mime de la réponse
    $mime = $this->responseMime($template);

    return [$moid, $class, $this->_function, $template, $templates, $disp, $mime];

  }
  static function changeTemplate($t) {
    $_REQUEST["_template"]=$t;
  }

  /// Insere les données de la page dans la pile historique
  function insert_back() {
    if(!is_array($_SESSION['BACK'])) $_SESSION['BACK']=array();
    $CTXT=array('_REQUEST'=>$_REQUEST,
		'_SERVER'=>array('REQUEST_URI'=>@$_SERVER['REQUEST_URI'],'REQUEST_METHOD'=>@$_SERVER['REQUEST_METHOD']));
    $_SESSION['BACK'][\Seolan\Core\Shell::$_bdxprefix][\Seolan\Core\Shell::$_bdx]=$CTXT;
    \Seolan\Core\Shell::$_bdx++;
    // On supprime ce qui est trop vieux dans l'historique pour éviter de faire exploser la pile
    unset($_SESSION['BACK'][\Seolan\Core\Shell::$_bdxprefix-TZR_BACK_STACK_SIZE-3]);
    unset($_SESSION['BACK'][\Seolan\Core\Shell::$_bdxprefix][\Seolan\Core\Shell::$_bdx-TZR_BACK_STACK_SIZE-3]);
  }

  function back() {
    list($p,$n)=explode('_',$_REQUEST['n']);
    $tokeep=array('_iframeencode','_nohistory','_bdxnewstack','LANG_DATA','LANG_TRAD','skip','_skip','_reloadmods','_reloadmenu','message','_tabs','filename','fileoriginalname','filemime','fileinline');
    $tokeepvalues=array();
    if(!empty($_SESSION['BACK'][$p][$n])) {
      foreach($tokeep as $f) $tokeepvalues[$f]=@$_REQUEST[$f];
      $_REQUEST=$_SESSION['BACK'][$p][$n]['_REQUEST'];
      $_REQUEST['_bdx']=$p.'_'.$n;
      $_SERVER=array_merge($_SERVER,$_SESSION['BACK'][$p][$n]['_SERVER']);
      foreach($tokeepvalues as $f=>$v){
	if($v!==NULL) $_REQUEST[$f]=$v;
	else unset($_REQUEST[$f]);
      }
    } else {
      $ar["message"]=\Seolan\Core\Labels::getSysLabel('Seolan_Core_SessionMessages','noback');
      $this->redirect2error($ar);
      die();
    }
  }

  static function get_back_url($delta=-1) {
    $topback=\Seolan\Core\Shell::$_bdx+$delta-1;
    if(isset($_SESSION['BACK'][\Seolan\Core\Shell::$_bdxprefix][$topback]) && is_array($_SESSION['BACK'][\Seolan\Core\Shell::$_bdxprefix][$topback]['_REQUEST'])) {
      return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&function=back&n='.\Seolan\Core\Shell::$_bdxprefix.'_'.$topback;
    } else {
      return NULL;
    }
  }

  function redirect($ar) {
    if(\Seolan\Core\Shell::admini_mode()) {
      $this->_bdx--;
      unset($_SESSION['BACK'][\Seolan\Core\Shell::$_bdxprefix][\Seolan\Core\Shell::$_bdx]);
    }
    if(is_array($ar)) {
      foreach($ar as $a => $b) {
	$_REQUEST[$a]=$b;
      }
    }
    $this->run($ar);
    $this->exit_tzr();
  }

  /**
   * \Seolan\Core\IRewriting::decodeRewriting
   */
  public function decodeRewriting($url) {
    \Seolan\Library\SecurityCheck::assertIsUrl($url);
    $nurl="index.php?";
    if(preg_match('@^/([^/\.]+)\.html$@i',$url) && file_exists(TZR_WWW_DIR.$url)) {
      header('Content-type: text/html');
      @readfile(TZR_WWW_DIR.$url);
      die();
    }
    if(preg_match('@^/([^/\.]+)\.xml$@i',$url) && file_exists(TZR_WWW_DIR.$url)) {
      header('Content-type: text/xml');
      @readfile(TZR_WWW_DIR.$url);
      die();
    }
    if(preg_match('@^/GOOGLE([A-Za-z0-9]+)\.html$@',$url)) {
      header("HTTP/1.1 404 Not Found");
      exit(0);
    }
    if(preg_match('@^/noexist_([A-Za-z0-9]+)\.html$@',$url)) {
      header("HTTP/1.1 404 Not Found");
      exit(0);
    }
    $matches=array();
    if(preg_match('/^\/google([A-Za-z0-9]+)\.html$/',$url,$matches)) {
      echo 'google-site-verification: google'.$matches[1].'.html';
      header("HTTP/1.1 200 OK");
      exit(0);
    }
    if(preg_match('@^/'.TZR_REWRITING_PREFIX.'([^\.]*).(html|xml)@i',$url,$eregs)) {
      $rw=&$GLOBALS['TZR_REWRITING'];
      foreach($rw as $src => $dst) {
	$src=preg_replace('/(%%[0-9]+)/','',$src);
	$dst=preg_replace('/\(%%([0-9]+)[^\)]+\)/','{$eregs[$1]}',$dst);
	if(preg_match('@'.$src.'@i',$url,$eregs)) {
	  eval("\$vars=\"$dst\";");
	  break;
	}
      }
      if(!empty($vars)) {
	parse_str($vars, $nvars);
	$_REQUEST=array_merge($_REQUEST,$nvars);
	$nurl.='&'.$vars;
      }
    } else {
      /* decodage des alias */
      $ks = array_keys($GLOBALS['TZR_LANGUAGES']);
      $ks1='('.implode('|',$ks).')';
      if(preg_match('@^/'.$ks1.'_([^\./]+)\.html@',$url)) {
	if(preg_match('@^/'.$ks1.'_oidit_([^_]+)_([^/\._]+)\.html(.*)$@',$url,$eregs)) {
	  $params=parse_url($eregs[4]);
	  $_REQUEST['_lang']=$eregs[1];
	  $_REQUEST['oidit']=$eregs[2].":".$eregs[3];
	  $nurl.="_lang=".$eregs[1]."&oidit=".$eregs[2].":".$eregs[3];
	}  elseif(preg_match('@^/'.$ks1.'_([^_]{1}[^/\.]+)\.html(.*)$@',$url,$eregs)) {
	  $params=parse_url($eregs[3]);
	  $_REQUEST['_lang']=$eregs[1];
	  $_REQUEST['alias']=$eregs[2];
	  $nurl.="_lang=".$eregs[1]."&alias=".$eregs[2];
	}
      } else {
	if(preg_match('@^/oidit_([^/\._]+)_([^/\._]+)\.html(.*)$@',$url,$eregs)) {
	  $params=parse_url($eregs[3]);
	  $_REQUEST['oidit']=$eregs[1].":".$eregs[2];
	  $nurl.="oidit=".$eregs[1].":".$eregs[2];
	} elseif(preg_match('@^/([^/\.]+)\.html(.*)$@',$url,$eregs)) {
	  $rw = &$GLOBALS['TZR_REWRITING'];
	  if (array_key_exists($eregs[1], $rw)) {
	    $params = explode('&', $rw[$eregs[1]]);
	    foreach ($params as $p) {
	      list($k, $v) = explode('=', $p);
	      $_REQUEST[$k] = $v;
	    }
	    $params = parse_url($eregs[2]);
	  } else {
	    $params=parse_url($eregs[2]);
	    $_REQUEST['alias']=$eregs[1];
	    $nurl.="alias=".$eregs[1]."&".$eregs[2];
	  }
	}
      }
    }
    if(!empty($params)) $_REQUEST=array_merge($_REQUEST,$params);
    if(!empty($_REQUEST['alias']) && TZR_USE_REWRITE){
      $lg=\Seolan\Core\Shell::getLangData();
      $ors=getDB()->fetchRow('select * from _REWRITE where alias=? and LANG=? limit 1', [$_REQUEST['alias'], $lg]);
      if(!empty($ors)){
	unset($_REQUEST['alias']);
	$_REQUEST['oidit']=$ors['rub'];
	parse_str($ors['cplt'],$params);
	if(!empty($params)) $_REQUEST=array_merge($_REQUEST,$params);
      }
    }
    if($nurl!="index.php?") {
      $_SERVER['REQUEST_URI']="/".$nurl;
      $GLOBALS['TZR_SELF']='/index.php';
      $_SERVER['SCRIPT_NAME']='/index.php';
    }
    \Seolan\Core\Shell::_changeLang();
    \Seolan\Core\Shell::getLangData(NULL,true);
    \Seolan\Core\Logs::debug(__METHOD__.' : <'.$url.'> -> <'.$nurl.'>');
  }

  /**
   * \Seolan\Core\IRewriting::encodeRewriting
   */
  public function encodeRewriting(&$html) {
    $scriptname=$GLOBALS["TZR_SELF"];
    if(substr($scriptname,0,1)=='/') $scriptname=substr($scriptname,1);
    $todst='';
    if(strpos($scriptname,'index.php')===false && strpos($scriptname,'mobile.php')===false) {
      if(preg_match('@^([a-z0-9]+)\.php$@i',$scriptname,$eregs1)) {
	$todst=$eregs1[1].'_';
      }
    }
    $limiter='("|;|#)';
    $rw=&$GLOBALS['TZR_REWRITING'];
    foreach($rw as $src => $dst) {
      $dst=preg_replace('/(%%[0-9]+)/','',$dst);
      $src=preg_replace('@\(%%([0-9]+)([^\)]+)\)@','\$\\1',$src);
      $dst='index.php?&*'.$dst;
      $dst=str_replace('?','\?', $dst);
      if($GLOBALS['TZR_REWRITING_CASESENSITIVE']) {
	$html=preg_replace('@'.$dst.'@', TZR_REWRITING_PREFIX.$src, $html);
	\Seolan\Core\Logs::debug("\Seolan\Core\Shell::encodeRewritingCaseSensitive: $dst -> $src");
      } else {
	$html=preg_replace('@'.$dst.'@i', TZR_REWRITING_PREFIX.$src, $html);
	\Seolan\Core\Logs::debug("\Seolan\Core\Shell::encodeRewriting: $dst -> $src");
      }
    }
    /* rewriting avec les alias */
    if(!\Seolan\Core\Shell::getMonoLang()) {
      $html=preg_replace('@'.$scriptname.'\?&*_lang=([A-Z]{2})&amp;alias=([A-Za-z0-9_-]{2,80})("|;|`|\#|<)'.'@',
			 $todst.'$1_$2.html$3',$html);
      $html=preg_replace('@'.$scriptname.'\?&*_lang=([A-Z]{2})&amp;oidit=([A-Za-z0-9:]{2,10}):([A-Za-z0-9]{2,40})("|;|\#|`|<)@',
			 $todst.'$1_oidit_$2_$3.html$4',$html);
    }

    $html=preg_replace('@'.$scriptname.'\?&*(amp;)?alias=([A-Za-z0-9_-]{2,80})("|;|`|\#|<)@',$todst.'$2.html$3',$html);
    $html=preg_replace('@'.$scriptname.'\?&*(amp;)?oidit=([A-Za-z0-9]{2,10}):([A-Za-z0-9]{2,40})("|`|;|#)@',
		       $todst.'oidit_$2_$3.html$4',$html);
    $html=preg_replace('@'.$scriptname.'\?&*(amp;)?oidit=([A-Za-z0-9:]{2,40})("|`|;|#)@',$todst.'$2.html$3',$html);
  }

  function index() {
    if(array_key_exists('labels', $_REQUEST) && is_array($_REQUEST['labels'])){
      foreach($_REQUEST['labels'] as $l){
	\Seolan\Core\Labels::loadLabels($l);
      }
    }
  }
  function dummy() {
    return array(0=>"toto");
  }

  /**
   * Enregistre un message d'alerte dans le but de l'afficher à l'utilisateur
   *
   * @param $message string|array Message(s) à afficher (peut être au format HTML)
   * @param $type string Type de l'alerte. Il est recommandé d'utiliser les types bootstrap : danger|warning|info|success
   */
  public static function alert($message, $type = 'danger') {
    mergeSessionVar('alerts', array($type => is_array($message) ? $message : array($message)));
  }

  /**
   * Enregistre un message d'alerte dans le but de l'afficher à l'administrateur
   *
   * @param $message string|array Message(s) à afficher (peut être au format HTML)
   * @param $type string Type de l'alerte. Il est recommandé d'utiliser les types bootstrap : danger|warning|info|success
   */
  public static function alert_root($message, $type = 'danger') {
    if (!\Seolan\Core\Shell::isRoot()) return;
    else return self::alert($message, $type);
  }

  /**
   * Enregistre un message d'alerte dans le but de l'afficher dans le backend
   *
   * @param $message string|array Message(s) à afficher (peut être au format HTML)
   * @param $type string Type de l'alerte. Il est recommandé d'utiliser les types bootstrap : danger|warning|info|success
   */
  public static function alert_admini($message, $type = 'danger') {
    if (!\Seolan\Core\Shell::admini_mode()) return;
    else return self::alert($message, $type);
  }

  /// recherche le module de gestion de lock documentaire
  static function &getXModLock() {
    static $XLOCK=false;
    // Creation de l'objet reservation si necessaire
    if($XLOCK===false) {
      if(\Seolan\Core\Module\Module::getMoid(XMODLOCK_TOID))
	$XLOCK=new \Seolan\Module\Lock\Lock();
      else
	$XLOCK=NULL;
    }
    return $XLOCK;
  }
  // *******************************************************************
  // à voir Shell / APPlications <- boutique CDe
  // -> il y a du rewriting
  // *******************************************************************
  public function setCurrentUri($uri) {
    $this->_current_uri = self::buildUrl($uri);
  }
  public function getCurrentUri() {
    return $this->_current_uri;
  }
  /**
   * Redirige vers la page désirée
   * @param {mixed} $param URL, URI, QUERY_STRING ou tableau de paramètres pour la redirection
   * @param {boolean} $encode_url Vrai si  l'on souhaite que l'URL soit encodée
   * @param {int} $http_code Code de redirection HTTP (301=permanent|302=found=default|303=others|307=temporary)
   * @todo Voir si on ne peut pas remplacer la fonction XShell::redirect() par celle-ci...
   */
  static function redirectTo($param, $encode_url = true, $http_code = 302) {
    $url = static::buildUrl($param, $encode_url);
    header('Location: '.$url, true, $http_code);
    die();
  }

  /**
   * Construit une URL à partir d'une URL, URI, QUERY_STRING ou Tableau de paramètres
   * @param {string} $param URL, URI, QUERY_STRING ou Tableau de paramètres
   * @param {boolean} $encode_url Vrai si  l'on souhaite que l'URL soit encodée
   * @param {boolean} $htmlentities Vrai si  l'on souhaite conserver les &amp; au lieu des &
   */
  static function buildUrl($param, $encode_url = false, $htmlentities = true) {
    $self = $GLOBALS['TZR_SESSION_MANAGER']::complete_self();
    if (is_array($param)) {
      $url = $self.http_build_query($param);
    } elseif (strpos($param, $self) === false && !preg_match('@^(https?|/)@', $param)) {
      $url = $self.$param;
    } else {
      $url = $param;
    }
    if ($encode_url) {
      $rewriter = $GLOBALS['XSHELL'];
      if (TZR_USE_APP) {
	// rewriter specifique eventuel (remplace le defaut)
	$bootstrapApplication = \Seolan\Module\Application\Application::getBootstrapApplication();
	if ($bootstrapApplication != null)
	  $rewriter = $bootstrapApplication->getRewriter()??$rewriter;
      }
      $rewriter->encodeRewriting($url);
    }
    if (!$htmlentities) {
      $url = str_replace('&amp;', '&', $url);
    }
    return $url;
  }
  // *******************************************************************
}

