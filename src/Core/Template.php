<?php
namespace Seolan\Core;

class Template extends \Smarty {
  public $tplfile;
  public $glob = array();
  protected $rewriter = null;
  function __construct($file, \Seolan\Core\Application\Application $application = null) {

    parent::__construct();
    $this->compile_check = true;
    $this->debugging = false;
    $this->tplfile = $file;
    $this->left_delimiter = "<%";
    $this->right_delimiter = "%>";
    
    $this->registerResource('file', new \Seolan\Core\CustomTemplateResource());

    if (TZR_USE_APP && $application == null) {
      $application = \Seolan\Module\Application\Application::getBootstrapApplication();
    }

    $addResources = $addTemplatesDir = null;
    if (null !== $application){
      $this->rewriter = $application->getRewriter();
      $addResources = $application->getTemplatesResources();
      $addTemplatesDir = $application->getAdditionnalTemplatesDirs();
      foreach($addResources as $protocol=>$resource){
	$this->registerResource($protocol, $resource);
      }
    }
    
    $templatesDir = [$GLOBALS["TEMPLATES_DIR"]]; // csx en pcpe

    // si aucune application, on reconnait le répertoire habituel des templates front office
    if($application == null) {
      $templatesDir[]=$GLOBALS['TZR_WWW_DIR'].'/templates/';
    }

    // ajout de la bib locale (logique namespace.ressource/public/template/gabarits)
    $templatesDir[] = $GLOBALS['LOCALLIBTHEZORRO'];

    /// recupérations (et ajout) des éléments associés aux repositories
    $pluginsDirs = [];
    foreach($GLOBALS['REPOSITORIES'] as $prefix=>$repository){
      // on a au moins src 
      if (isset($repository['smarty_plugins']))
	$pluginsDirs[] = $repository['smarty_plugins'];
      else
	$pluginsDirs[] = $repository['src'].'Library/smarty/plugins';
      if (!in_array($repository['src'], $templatesDir))
	$templatesDir[] = $repository['src'];
      if ($prefix != 'Seolan' && $prefix != 'Local'){
	$rtr = new \Seolan\Core\RepositoryTemplateResource();
	$rtr->setRepository(strtolower($prefix),$repository);
	$this->registerResource(strtolower($prefix), $rtr);
      }
    }
    
    // plugins smarty
    $this->addPluginsDir($pluginsDirs);
    
    // ajout du répertoire pour les templates locaux (façon v8)
    if(defined('TZR_ALLOW_USER_TEMPLATES') && !empty($GLOBALS['USER_TEMPLATES_DIR'])){
      $templatesDir[] = $GLOBALS['USER_TEMPLATES_DIR'];
    }

    // ? ajouter avant ou après les répertoires par defaut (surcharge/remplacement)
    if (isset($addTemplatesDir)){
      foreach($addTemplatesDir as $dir){
	array_push($templatesDir, $dir);
      }
    }
    \Seolan\Core\Logs::debug(__METHOD__.' templates dirs : '.implode(' ', $templatesDir));

    $this->setTemplateDir($templatesDir);
    
    if(!file_exists($GLOBALS["TEMPLATES_CACHE"])) {
      @mkdir($GLOBALS["TEMPLATES_CACHE"]);
    }
    $this->setCompileDir($GLOBALS["TEMPLATES_CACHE"]);

    // tmp for backward compatibility : php tag must be removed
    $this->registerPlugin('block', 'php', '\Seolan\Core\Template::smarty_php_tag');
  }

  function assignRawData(&$theRawData) {
    foreach($theRawData as $k=>&$v) {
      $this->assignByRef($k, $v);
    }
  }

  function assignTplData(&$theTplData) {
    if(!is_array($theTplData)) return;
    foreach($theTplData as $k=>&$v) {
      if(is_array($v)) {
	foreach($v as $kv => &$vv) {
	  $tplkey = $k . "_" . $kv;
	  $this->assignByRef($tplkey, $vv);
	}
      }
    }
  }

  function set_glob($ar) {
    foreach($ar as $k=>&$v) {
      $this->glob[$k]=$v;
    }
  }

  /// parse le template pour remplacer les tags Smarty. theTplData =
  /// données format console, theRawData = données format classique
  /// smarty, $cache = non utilisé, $sessionhack = vrai si on fait un
  /// close et start de la session pour gérer correctement l'accès aux
  /// données dans les inclusiions spécifiques. Dans certains cas,
  /// cela peut provoquer des bugs, repasser à false dans ces cas là
  function parse(&$theTplData,&$theRawData, $cache=NULL, $sessionhack=true) {
    global $XSHELL;
    // recuperation des libelles localises
    $syslabels = &\Seolan\Core\Labels::getSysLabels();
    $this->set_glob(array('syslabels'=>&$syslabels));

    $charset = \Seolan\Core\Lang::getCharset();
    if(!empty($charset)) {
      $header = '<meta http-equiv="Content-Type" content="text/html; charset='.$charset.'"';
      if(OutputIsXHTML()) $header.='/>';
      if(OutputIsHTML()) $header.='>';
    }

    $url = '';
    if(isset($GLOBALS['XUSER'])) {
      $this->assign('uid',\Seolan\Core\User::get_current_user_uid());
    }
    if(isset($GLOBALS['XUSER']) && \Seolan\Core\Shell::admini_mode()) {
      // recherche des preferences utilisateur
      if (!issetSessionVar('upref')) {
        $us=\Seolan\Core\Module\Module::objectFactory(array('toid'=>XMODUSER2_TOID, 'moid'=>'', 'tplentry'=>TZR_RETURN_DATA));
        $prefs=$us->getPrefs(array());
        setSessionVar('upref', $prefs);
      }
      $this->assign('upref', getSessionVar('upref'));
    }
    $this->assign('session_name',session_name());
    $this->assign('session_id',session_id());
    $self=$GLOBALS['TZR_SESSION_MANAGER']::complete_self();
    $fullself=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true,true);
    $this->assign('self', $self);
    $lang_data = \Seolan\Core\Shell::getLangData();
    $this->assign('locale',\Seolan\Core\Lang::$locales[$lang_data]);
    $this->assign('fullself', $fullself);
    $this->assign('captcha',TZR_CAPTCHA);
    $ambient=array();
    if(preg_match('@\/([a-z0-9]+).php@i', $self, $eregs)) {
      $ambient['c1']=$eregs[1];
    }
    $this->assign('ambient', $ambient);
    $this->assign('site_url',\Seolan\Core\Ini::get('site_url'));
    $this->assign('admin',getSessionVar('ADMIN'));
    $this->assign('admini',getSessionVar('ADMINI'));
    $this->assign('root',getSessionVar('root'));
    $this->assign('nobody',\Seolan\Core\User::isNobody());
    $this->assign('setuid',$GLOBALS['TZR_SESSION_MANAGER']::setuid());
    $this->assign('sessiontag',session_name().'='.session_id().'&');
    if(\Seolan\Core\Shell::admini_mode() || !empty($XSHELL->activeHistory)) {
      $this->assign('back', \Seolan\Core\Shell::get_back_url());
      $this->assign('here', \Seolan\Core\Shell::get_back_url(0));
      $this->assign('bdxprefix',\Seolan\Core\Shell::$_bdxprefix);
      $this->assign('bdx',\Seolan\Core\Shell::$_bdx);
    }
    \Seolan\Core\System::loadVendor('mdetect/mdetect.php');
    $uagent_obj = new \uagent_info();
    $this->assign('useragent',$uagent_obj);
    $this->assign('userhome',$GLOBALS['TZR_SESSION_MANAGER']::complete_self());
    $TZR['share']=TZR_SHARE_URL;
    if(!empty($GLOBALS['USER_TEMPLATES_URL']))
      $TZR['user_templates_url']=$GLOBALS['USER_TEMPLATES_URL'];
    if(!empty($GLOBALS['USER_TEMPLATES_DIR']))
      $TZR['user_templates_dir']=$GLOBALS['USER_TEMPLATES_DIR'];
    $this->assignByRef('home', $GLOBALS['HOME_URL']);
    $this->assignByRef('website', $GLOBALS['HOME_ROOT_URL']);
    $this->assign('domainname',$GLOBALS['TZR_SESSION_MANAGER']::makeDomainName());
    $this->assign('full_home',@$_SERVER['SERVER_NAME'].$GLOBALS['HOME_URL']);
    $this->assign('full_serverhome',(empty($_SERVER['HTTPS'])?'http://':'https://').@$_SERVER['SERVER_NAME'].$GLOBALS['HOME_URL']);
    $this->assign('currency', CURRENCY);
    $this->assign('lang_def',TZR_DEFAULT_LANG);
    $this->assign('lang_data', $lang_data);
    if(!empty($GLOBALS['XUSER'])) $this->assign('xuser', $GLOBALS['XUSER']);
    if(\Seolan\Core\Shell::admini_mode()) {
      $r2=\Seolan\Core\Lang::getCodes();
      \Seolan\Core\Shell::toScreen1('lang',$r2);
      if(!\Seolan\Core\Shell::_ajax()){
        $r2=\Seolan\Core\Lang::getCodes(NULL,true);
        \Seolan\Core\Shell::toScreen1('langsort',$r2);
      }
      $TZR['lang_data']=\Seolan\Core\Lang::get(\Seolan\Core\Shell::getLangData());
      $TZR['lang_user']=\Seolan\Core\Lang::get(\Seolan\Core\Shell::getLangUser());
      $TZR['lang_def']=\Seolan\Core\Lang::get(TZR_DEFAULT_LANG);
      $lt=\Seolan\Core\Shell::getLangTrad();
      if($lt) {
        $TZR['lang_trad']=\Seolan\Core\Lang::get($lt);
        $this->assign('lang_trad', $lt);
      }
      $mods1=array();
      $mods1['xmoduser2']=\Seolan\Core\Module\Module::getMoid(XMODUSER2_TOID);
      $mods1['xmodgroup']=\Seolan\Core\Module\Module::getMoid(XMODGROUP_TOID);
      $mods1['xmodadmin']=\Seolan\Core\Module\Module::getMoid(XMODADMIN_TOID);
      $mods1['xmodworkflow']=\Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID);

      $mods1['xmodchat']=\Seolan\Core\Module\Module::getMoid(XMODCHAT_TOID);
      $mods1['xmodbackofficeinfotree']=\Seolan\Core\Module\Module::getMoid(XMODBACKOFFICEINFOTREE_TOID);
      $this->assign('sysmods', $mods1);

      // Liste des js et css personnalisés
      $js=$css=array();
      if(defined('TZR_USER_JS_FILE')){
        $js=explode(',',TZR_USER_JS_FILE);
      }
      if(defined('TZR_USER_CSS')){
        $css=explode(',',TZR_USER_CSS);
      }
      if(defined('TZR_JQUERYUI_CSS')) $css[]=TZR_JQUERYUI_CSS;

      $this->assign('tzr_css',$css);
      $this->assign('tzr_js',$js);
    }
    if (!Shell::_ajax()) $TZR['packs']=$GLOBALS['TZR_PACKS']->getStubs();
    $this->assign('TZR', $TZR);
    $this->assign('lang_user', \Seolan\Core\Shell::getLangUser());
    $this->assign('server_name', @$_SERVER['SERVER_NAME']);

    $this->assignByRef('tzr', $GLOBALS['TZR']);
    $this->assign('header', $header);
    $this->assign('charset', $charset);

    $this->assign('xmodsubmoid', \Seolan\Core\Module\Module::getMoid(XMODSUB_TOID));

    $this->assign('uniqid',\Seolan\Core\Shell::uniqid());
    $this->assignByRef('template', $this->tplfile);
    $this->assign('function',\Seolan\Core\Shell::_function());
    $this->assign('marker',TZR_SHARE_MARKER_PHP);

    $elapsed=\Seolan\Core\System::getmicrotime()-TZR_START_TIME;
    $elapsed*=1000;
    $elapsed=(int)$elapsed;
    $selectqueries=\Seolan\Core\Audit::get('select-queries');
    $this->assign('processtime', $elapsed.'ms/'.$selectqueries.'selects-queries');
    $this->assign('memory', (int)(memory_get_usage()/1000));
    $this->assign('maxmemory', (int)(memory_get_peak_usage()/1000));

    $this->assign($this->glob);
    $this->assignTplData($theTplData);
    $this->assignRawData($theRawData);
    \Seolan\Core\Logs::debug(__METHOD__.' fetch templates dir : '.implode(' ', $this->getTemplateDir()).', file :  '.$this->tplfile);
    // big bug fix!! la session doit etre close avant le fetch car
    // sinon on bloque en cas de fetch dans le template verrouillage
    // de la session recursive
    if($sessionhack) sessionClose();
    try{
      $res = $this->fetch($this->tplfile,null,null);
    } catch(\Throwable $t){
      \Seolan\Core\Logs::critical(__METHOD__, $t->getMessage());
      \Seolan\Core\Logs::critical(__METHOD__, $t->getFile().' '.$t->getLine());
      \Seolan\Core\Logs::critical(__METHOD__, $t->getTraceAsString());
      \Seolan\Core\Logs::critical(__METHOD__, implode(',', $this->getTemplateDir()));
      throw $t;
    }
    \Seolan\Core\Logs::debug(__METHOD__.' fetched size='.strlen($res));
    $this->_cleanUp($res);
    if($sessionhack) sessionStart();
    return $res;
  }

  private function _cleanUp(&$html) {
    \Seolan\Core\Logs::debug('\Seolan\Core\Template::_cleanUp: start');
    if(\Seolan\Core\Ini::get('url_rewriting')) {
      $self=$GLOBALS['TZR_SESSION_MANAGER']::complete_self();
      $sself=$self;
      $self=preg_replace('@(\?\&amp\;)@','?', $self);
      $from=array(
        $sself,	/* 1 */
        '&amp;&amp;function=', /* 2 */
        '&amp;&amp;alias=',	/* 3 */
        $self.'&amp;', /* 4 */
        $self.'&amp;&amp;', /* 5 */
        $self.'&amp;&amp;', /* 6 */
        $self.'&amp;'); /* 7 */
      $to=array(
        $self,	/* 1 */
        '&amp;function=', /* 2 */
        '&amp;alias=', /* 3 */
        $self, 	/* 4 */
        $self,	/* 5 */
        $self,	/* 6 */
        $self);	/* 7 */
      $html= str_replace($from, $to, $html);
      if ($this->rewriter == null){
		\Seolan\Core\Logs::debug('\Seolan\Core\Template::_cleanUp: url rewriting activated, starting encoding');
        $GLOBALS['XSHELL']->encodeRewriting($html);
        \Seolan\Core\Logs::debug('\Seolan\Core\Template::_cleanUp: url rewriting activated, ending encoding');
	  } else{
		  $this->rewriter->encodeRewriting($html);
	  }

    }
    \Seolan\Core\Logs::debug('\Seolan\Core\Template::_cleanUp: end');
  }

  // reecriture de toutes les formes d'alias vers [OID]
  function normaliseAlias($content) {
  }

  /**
   * Smarty {php}{/php} block function
   *
   * @param array   $params   parameter list
   * @param string  $content  contents of the block
   * @param object  $template template object
   * @param boolean &$repeat  repeat flag
   *
   * @return string content re-formatted
   */
  function smarty_php_tag($params, $content, $template, &$repeat)
  {
    eval($content);

    return '';
  }

}
