<?php
namespace Seolan\Module\Application;
/**
* Module application :
* -> fiches application
* surcharge des méthodes d'insertion et edition pour basculer sur les Wizard des application_confirm_delete_groups
* ! fait au niveau des action (méthode et gabarit)
* -> gestion des applications (appli disponibles, etc)
*/

use Seolan\Core\Module\Module;
use Seolan\Core\{Logs,Shell,Labels,Param,System,Ini};


class Application extends \Seolan\Module\Table\Table{
  private static $apps_available=array(
      '\Seolan\Application\Corail\Corail'=>'Corail',
      '\Seolan\Application\Site\Site'=>'Site',
      '\Seolan\Application\MiniSite\MiniSite'=>'MiniSite'
  );

  private static $apps_enabled=array();
  private static $apps_run_callbacks=array();
  private static $apps_runned=false;
  public $table='APP';
  public $multipleedit=false;
  public $trackchanges=false;
  public static $singleton = true;

  static public $upgrades=array(
    '20200624'=>'',
    '20200701'=>'',
  );
  private static $bootstrapApplication = null;

function &browse($ar=null){
    if (!defined('TZR_USE_APP') || TZR_USE_APP != 1){
      Shell::alert('⚠  Les applications ne sont pas activées');
    }
    return parent::browse($ar);
  }

  /**
   * @author Bastien Sevajol
   *
   * Contrôle de la présence des champs nécessaire au fonctionnement.
   * (Ajout des champs pour patch)
   */
  public static function checkEnvironnement() {
    $app_xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS=APP');
    if ($app_xds) {
      if (!$app_xds->fieldExists('domain_is_regex')) {
        $app_xds->createField('domain_is_regex', \Seolan\Core\Labels::getSysLabel('Seolan_Module_Application_Application','field_domain_is_regex'), '\Seolan\Field\Boolean\Boolean', '', '', 0, 0, 1, 0, 0, 0, '', array());
      }
    }
  }

  public function secGroups($function, $group=NULL) {
    $g['newWizard']=array('rw','rwv','admin');
    $g['editWizard']=array('rw','rwv','admin');
    $g['del']=array('rw','rwv','admin');
    $g['setForcedApps']=array('none');
    $g['checklist']=array('admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Initialise les appplis qui doivent l'être
  static function init(){
    static::registerLocalApps();
    if(!empty(self::$apps_available)){
      $rs=static::getAppsFromDB();
      // Instancie les applis
      while($app=$rs->fetch()){
	Logs::notice(__METHOD__.'load '.$app['classname']);
        static::loadApp($app['classname'],$app);
      }
      Logs::notice(__METHOD__,'app enabled : '.implode(' ', array_keys(self::$apps_enabled)));
    }
  }

  /// Défini une appli comme utilisable
  static public function register($classname,$name){
    self::$apps_available[$classname]=$name;
  }

  /// Enregistre la liste des applis locales
  static function registerLocalApps(){
    if(isset($GLOBALS['LOCAL_APPS'])){
      foreach($GLOBALS['LOCAL_APPS'] as $c=>$n){
	static::register($c,$n);
      }
    }
  }

  /**
   * @param bool|False $dont_retry
   * @param bool $ignore_priority
   * @return PDOStatement
   * @throws \Exception
   * @note : Helper/system/checkMinisites detecte les applications aussi
   * @author Bastien Sevajol (modifié depuis Julien Maurel)
   *
   * Récupère la liste des applis à instancier
   *
   */
  public static function getAppsFromDB($dont_retry = False, $ignore_priority = false){

    $domain = $_SERVER['SERVER_NAME']??null;

    $sql_order = 'domain DESC';
    $sql_prioritised = '';
    // Modification de l'ordre SQL si des Apps ont été priorisés
    $prioritised_apps_koids = static::getPrioritisedsAppsKOIDs();
    if ($prioritised_apps_koids && !$ignore_priority) {
      if(!is_array($prioritised_apps_koids)) {
        $prioritised_apps_koids = array($prioritised_apps_koids);
      }
      $sql_prioritised = 'OR KOID IN ("'.implode('", "', $prioritised_apps_koids).'")';
      $sql_order = 'FIELD(KOID, "'.implode('", "', $prioritised_apps_koids).'") DESC';
    }
    // Correspondance avec le domaine
    $sql_domain = 'domain="'.$domain.'" OR ("'.$domain.'" REGEXP(domain) AND (domain_is_regex != "2" AND domain_is_regex !=  "") )';

    $sql = 'SELECT * FROM APP WHERE domain = "" OR ( '.$sql_domain.' ) '.$sql_prioritised. ' ORDER BY ' .$sql_order;
    try {
      return getDB()->select($sql);
    } catch (\Exception $exc) {
      if ($dont_retry) {
        throw $exc;
      }
      // Le patch peut ne pas avoir été appliqué à ce moment la (depuis que les patchs sont au niveau module)
      // Dans ce cas on retente le coups après avoir vérifié les conditions de fonctionnement du module
      static::checkEnvironnement();
      return static::getAppsFromDB(True);
    }
  }

  protected static function getUserAppsKOIDs() {
    $allowed_apps = array();
    foreach (self::$apps_available as $app_class => $app_name) {
      if (\Seolan\Core\System::tableExists($app_class::getCompleteTableName())) {
        $allowed_apps = array_merge($allowed_apps, $app_class::getAllowedAppsKoidsForCurrentUser());
      }
    }

    return $allowed_apps;
  }

  /**
   * @author Bastien Sevajol
   *
   * @return array|null KOIDs des applications priorisés
   */
  public static function getPrioritisedsAppsKOIDs() {
    if (\Seolan\Core\Shell::admini_mode() && ($forceds_apps_koids = getSessionVar('FORCED_APPS_KOIDS'))) {
      return $forceds_apps_koids;
    }

    return Null;
  }

  /// Instancie une appli
  /// A voir : retourne ou pas l'appli ?
  private static function loadApp($classname,$app){
    $classname=strtolower($classname);
    // On ne peux avoir qu'une instance d'une appli
    Logs::notice('\Seolan\Module\Application\Application','Load app '.$classname.' '.$app['params']);
    if(isset(self::$apps_enabled[$classname])){
      return;
    }
    self::$apps_enabled[$classname]=self::getAppByOrs($app);
    return self::$apps_enabled[$classname];
  }

  /// Récupère une instance active d'une appli via le nom de sa classe
  static public function get($classname){
    $classname=strtolower($classname);
    return @self::$apps_enabled[$classname];
  }

  /// Appel le hook suite à la création d'un module
  static public function newModuleHook($mod){
    foreach(self::$apps_enabled as $app){
      $app->newModuleHook($mod);
    }
  }

  /// En cas d'url sans moid ni classe, récupère un eventuel objet à utiliser
  static public function getObjectForFunctionExecution($function) : ?\Seolan\Core\ISec {
    foreach(self::$apps_enabled as $app){
      $ret=$app->getObjectForFunctionExecution($function);
      if($ret) return $ret;
    }
    return null;
  }

  /// Retourne une application par l'oid
  static function getAppByOid($oid,$wd=false){
    $ors=getDB()->fetchRow('select * from APP where KOID=?',array($oid));
    return self::getAppByOrs($ors,$wd);
  }

  /// Retourne une application par une ligne complete de resultset d'une application
  static function getAppByOrs(&$ors,$wd=false){
    // Récupération des params depuis le serveur si le fichier existe dans tzr
    $dir = TZR_WWW_DIR."../tzr/config/";
    $file = $dir.rewriteToAscii($ors['domain']).".json";
    if(file_exists($file)){
      $decoded = true;
      $params = file_get_contents($file);
      if($params && json_decode($params, true)) {
        $ors['params'] = $params;
      }
    }
    $app=$ors['classname'];
    if($wd) $app=self::getAppWizardPath($app);
    $params=$ors['params']?json_decode($ors['params'], true):array();
    $params['oid']=$ors['KOID'];
    $params['domain']=$ors['domain'];
    $params['name']=$ors['name'];
    $params['app_class']=$ors['classname'];
    $params['domain_is_regex'] = null;
    if (isset($ors['domain_is_regex'])){
      $params['domain_is_regex'] = $ors['domain_is_regex'];
    }
    return new $app($params);
  }
  /// Retourne le wizard d'une application via l'oid
  static function getAppWizardByOid($oid){
    return self::getAppByOid($oid,true);
  }

  /// Retourne le chemin d'accès à la classe de wizard
  static private function getAppWizardPath($app){
    $w=explode('\\',$app);
    array_pop($w);
    $w[]='Wizard';
    return implode('\\',$w);
  }
  /**
   * recupération de l'application principale
   * en cron, peut ne pas exister même si applications activées
   * @TODO : à préciser de toute façon ...
   */
  static function getBootstrapApplication() : ?\Seolan\Core\Application\Application {
    if (isset(static::$bootstrapApplication))
      return static::$bootstrapApplication;
    $first = null;
    foreach (static::$apps_enabled as $app) {
      if ($first == null)
	$first = $app;
      $rewriter = $app->getRewriter();
      if ($rewriter != null){
	return $app;
      }
    }
    // la première sinon
    return $first;
  }
  /**
   * Execute la méthode 'run' de chaque APP et ses callbacks.
   */
  public static function runApps() {
    foreach (static::$apps_enabled as $app) {
      $app->run();
      static::executeAppRunCallbacks($app);
    }
    static::$apps_runned = True;
  }

  /**
   * Les callbacks ajoutés ici seront executés juste après le run des applications.
   * Cela permet d'effectuer des opération sur des objets qui nécessite que les applications soient déjà instanciés.
   * (Voir XThesaurusSubSiteDef::__construct() pour exemple)
   *
   * Si les applications ont déjà été lancés, le callback est executé immédiatement.
   *
   * @param $app_class_name
   * @param $callback
   */
  public static function addRunAppCallback($app_class_name, $callback) {
    $classname = strtolower($app_class_name);
    if (!static::$apps_runned) {
      static::$apps_run_callbacks[$classname][] = $callback;
    } else if (($app = static::$apps_enabled[$classname])) {
      // Si les applications ont déjà été lancés, le callback peux être executé tout de suite.
      $callback($app);
    }
  }

  /**
   * Execute les callbacks correspondant à cette app.
   *
   * @param \Seolan\Core\Application\Application $app
   */
  private static function executeAppRunCallbacks(\Seolan\Core\Application\Application $app) {
    $classname = strtolower(get_class($app));
    static::$apps_run_callbacks[$classname] = static::$apps_run_callbacks[$classname]??[];
    foreach (static::$apps_run_callbacks[$classname] as $app_run_callbacks) {
      $app_run_callbacks($app);
    }
    static::$apps_run_callbacks[$classname] = array();
  }
  /**
  * surcharge des actions pour passer dans les wizard des applications
  */
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my,$alfunction);
    if(isset($my['insert'])){
      $my['insert']->setUrl('&moid='.$this->_moid.'&_function=insert&isApp=1&template=Module/Application.new.html&tplentry=br');
    }
    if($this->secure('','checklist')){
      $o1=new \Seolan\Core\Module\Action($this,'checklist','Checklist',
                            '&moid='.$this->_moid.'&function=checklist&template=Module/Application.checklist.html','more');
      $o1->menuable=true;
      $my['checklist']=$o1;
    }
    unset($my['edit']);
  }

  public function insert($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    if(!$p->get('isApp')) {
      return parent::insert($ar);
    }
    $tplentry=$p->get('tplentry');
    return \Seolan\Core\Shell::toScreen2($tplentry,'apps',self::$apps_available);
  }

  public function newWizard($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $app=self::getAppWizardPath($p->get('app'));
    $app=new $app();
    return $app->irun($ar);
  }

  public function editWizard($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $oid=$p->get('oid');
    $app=self::getAppWizardByOid($oid);
    $ar['app']=$app->app_class;
    return $app->irun($ar);
  }

  public function del($ar=NULL){

    $p=new \Seolan\Core\Param($ar);
    $oid=\Seolan\Core\Kernel::getSelectedOids($p,true,false);
    if(is_array($oid)){
      return parent::del($ar);
    }
    // Methode del de l'appli
    $app=self::getAppWizardByOid($oid);
    $app->idel($ar);

    // Suppression des modules liés
    if(System::fieldExists('APP', 'modules')) {
      $moids = getDB()->fetchOne('select modules from APP where KOID=?', array($oid));
      $moids = preg_split('/\|\|/', $moids, null, PREG_SPLIT_NO_EMPTY);
      foreach($moids as $moid) {
        $mod = Module::objectFactory($moid);
        $mod->delete(array(
          'withtable' => true,
          'withsections' => true
        ));
      }
    }

    // Suppression des groupes liés
    if(System::fieldExists('APP', 'groups')) {
      $groups = getDB()->fetchOne('select groups from APP where KOID=?', array($oid));
      $groups = preg_split('/\|\|/', $groups, null, PREG_SPLIT_NO_EMPTY);
      foreach($groups as $group) {
        if($group && !in_array($group, array('GRP:1', 'GRP:2', 'GRP:*'))) {
          getDB()->execute('delete from GRP where KOID=?', array($group));
        }
      }
    }

    // Suppression de la charte
    if($app->_conf['charte']) {
      $charteOid = $app::delCharte($app->_conf['charte'],$app->_conf['oid']);
      if($charteOid){
        \Seolan\Core\Logs::notice(get_class($this),get_class($this).'::del suppression de la charte '.$charteOid);
      }
    }

    return parent::del($ar);
  }

  /// Retourne les infos de l'action editer du browse
  function browseActionEditUrl($usersel, $linecontext=null){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().($usersel?'&_bdxnewstack=1':'').'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=editWizard&template=Module/Application.appWizard.html';
  }
  /**
   * Force une application en tant que boostrap
   * Nécessaire en crontab/scheduler quand on boucle sur les applications
   */
  public function setBootstrapApplication($oid){
    static::$bootstrapApplication = $oid;
  }
  /**
   * @author Bastien Sevajol
   *
   * Renseigne la variable de session indiquant que l'on force une série d'APPs
   *
   * @param array $ar
   * @param bool $exit
   */
  public function setForcedApps($ar = array(), $exit = True) {
    $params = new \Seolan\Core\Param($ar);
    $apps_koids = $params->get('apps_koids');
    setSessionVar('FORCED_APPS_KOIDS', $apps_koids);
    if ($exit && empty($params->get('_next'))) {
      exit();
    }
  }
  public function getPublicConfig($ar=NULL){
    $conf = parent::getPublicConfig($ar);
    if (\Seolan\Core\System::tableExists($this->table)){
      $conf['applications'] = getDB()->fetchAll('select name, domain, classname from '.$this->table);
    } else {
      $conf['applications'] = 'pas de table '.$this->table;
    }
    return $conf;
  }
  /// les classes des applications peuvent avoir des upgrades
  public static function getUpgradableClasses(){
    return ['Corail'=>'\Seolan\Application\Corail\Corail'];
    // 20200217 à voir les classes app compilent pas toutes WIP
    if(isset($GLOBALS['LOCAL_APPS']))
      $local = array_keys($GLOBALS['LOCAL_APPS']);
    else
      $local = [];
    return array_merge(array_keys(static::$apps_available),$local);
  }
  function procEditDup($ar) {
    $p = new Param($ar);
    $params = json_decode($p->get('params'), true);
    $classname = $p->get('classname');
    $modules = $p->get('modules');
    $groups = $p->get('groups');
    $name = $p->get('name');

    $wizard = $classname ? self::getAppWizardPath($classname) : '\Seolan\Core\Application\Wizard';

    $newModules = $wizard::dupModules($modules, $name);
    $newGroups = $wizard::dupGroups($groups, $name);

    if($params['charte']) {
      $charteOid = $wizard::dupCharte($params['charte'], $name);
      $params['charte'] = $charteOid;
    }

    foreach($modules as $modkey => $moid) {
      $newoid = $newModules[$modkey];
      foreach($params as $key => $val) {
        if($val == $moid) {
          $params[$key] = "$newoid";
        }
      }
      foreach($newGroups as $group => $on) {
        getDB()->execute('update ACL4 set AMOID=? where AMOID=? and AGRP=?', array($newoid, $moid, $group));
      }
    }

    $ar['params'] = json_encode($params);
    $ar['modules'] = $newModules;
    $ar['groups'] = $newGroups;

    // On écrit la config json dans un fichier
    $dir = TZR_WWW_DIR."../tzr/config/";
    $file = $dir.rewriteToAscii($p->get('domain')).".json";
    $data = json_encode($params, JSON_PRETTY_PRINT);
    if(!is_dir($dir)) {
      mkdir($dir);
    }
    file_put_contents($file, $data);

    return parent::procEditDup($ar);
  }

  function browseActionDelHtmlAttributes(&$url, &$text, &$icon, $linecontext = null) {
    $label = Labels::getTextSysLabel('Seolan_Module_Application_Application','application_confirm_delete');
    $sublabel = "<br />";

    $index = $linecontext['index'];
    $modules = $linecontext['browse']['lines_omodules'][$index];
    $groups = $linecontext['browse']['lines_ogroups'][$index];

    if($modules && count($modules->collection)) {
      $sublabel .= Labels::getTextSysLabel('Seolan_Module_Application_Application','application_confirm_delete_modules').' : <ul>';
      foreach($modules->collection as $module) {
        $sublabel .= '<li>'.str_replace("'", "", $module->text).'</li>';
      }
      $sublabel .= '</ul><br />';
    }

    if($groups && count($groups->collection)) {
      $sublabel .= Labels::getTextSysLabel('Seolan_Module_Application_Application','application_confirm_delete_groups').' : <ul>';
      foreach($groups->collection as $group) {
        $grptext = $group->title ?: $group->text;
        $sublabel .= '<li>'.str_replace("'", "", $grptext).'</li>';
      }
      $sublabel .= '</ul><br />';
    }

    $linecontext['browse']['_del_confirmmessage'] = $label = sprintf($label, $sublabel);
    $attrs = 'class="cv8-delaction"';

    if (isset($label)){
      $attrs .= ' data-message=\''.$label.'\'';
    }

    return $attrs;
  }
  /**
   * app daemon : dispatch sur les applications en les forçant boostrap + NDD (toutes)
   * consolidation d'une app à l'autre des modules traités par une application
   */
  public function app_daemon($period='any'){
    $modApp = $this;
    // à voir l'ordre comme pour les prioritizedapplication
    $appDaemon = \Seolan\Core\Application\Daemon::getInstance();

    foreach(getDB()->select('select * from APP') as $ors){
      try{
	       if ($ors['domain_is_regex'] == 1 && !in_array($ors['domain'], ['.*','.+'])){
	          Logs::notice(__METHOD__,"no domain for app {$ors['name']}");
	           continue;
	          }
	          // nom de domaine
          	if (empty($ors['domain']) || in_array($ors['domain'], ['.*','.+'])){
          	  $infos = parse_url($GLOBALS['TZR_SESSION_MANAGER']::makeDomainName());
          	  if (!empty($infos['host']))
          	    $ors['domain'] = $infos['host'];
          	}

          	$app = $modApp->getAppByOrs($ors);
          	$modApp->setBootstrapApplication($app);

	          Logs::notice(__METHOD__,"daemon call {$period} {$ors['name']} {$ors['classname']}");

	          $app->daemon($period, $appDaemon);

      } catch(\Exception $t){
      	Logs::critical(__METHOD__,"error during app daemon {$period} call app : {$ors['KOID']} {$ors['name']}");
      	Logs::critical(__METHOD__,$t->getMessage().' '.$t->getTraceAsString());
      }
    }

  }

  /**
   * Retourne le code HTML d'un lien AJAX
   * @author Camille Descombes
   */
  private function ajaxLinkAdmin($moid, $text = '') {
    return '<a class="cv8-ajaxlink" href="'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$moid.'&function=adminBrowseFields&template=Core/Module.admin/browseFields.html">'
      .(empty($text) ? '<span class="glyphicon csico-admin"></span>' : $text).'</a>';
  }
  private function ajaxLinkProperties($moid, $text = '') {
    return '<a class="cv8-ajaxlink" href="'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$moid.'&_function=editProperties&template=Core/Module.admin/editprop.html&tplentry=props">'
      .(empty($text) ? '<span class="glyphicon csico-property"></span>' : $text).'</a>';
  }
  private function ajaxLinkSecurity($moid, $text = '') {
    return '<a class="cv8-ajaxlink" href="'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$moid.'&_function=lsSecurity&template=Core/Module.lssecurity.html&tplentry=br">'
      .(empty($text) ? '<span class="glyphicon csico-lock"></span>' : $text).'</a>';
  }
  private function ajaxLink($moid, $text, $param = '') {
    return '<a class="cv8-ajaxlink" href="'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$moid.$param.'">'.$text.'</a>';
  }
  private function checkIniVar($ini_var) {
    $var = \Seolan\Core\Ini::get($ini_var);
    return empty($var) ?
      array('#FAA', $this->ajaxLink($this->_moid, 'Variable '.$ini_vat.' non renseignée', 'function=iniEdit&template=Module/Management.iniEdit.html#'.$ini_var)) :
      array('#AFA', $var);
  }
  /**
   * Affiche un résumé des principaux points à vérifier avant la mise ne ligne du site
   * @author Camille Descombes
   * @todo pour les site avec plusieurs IT, NL, Contacts, faire les tests avec les différents modules via modlist :
   *       \Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA,'basic'=>true, 'toid' => XMODINFOTREE_TOID))
   */
  function checklist() {

    try {

      $error = '#FAA';
      $valid = '#AFA';
      $todo = '#FF8';

      // Récupération de l'APP
      $application = \Seolan\Module\Application\Application::getBootstrapApplication();
      // spécifique appli
      $moid_it = $application->infotree ?? \Seolan\Core\Ini::get('corailv3_xmodinfotree');
      $moid_nl = $application->nl ?? \Seolan\Core\Ini::get('CorailNewsLetter');
      $moid_cart = $application->cart ?? \Seolan\Core\Ini::get('CorailContact');
      $moid_contacts = $application->contact ?? \Seolan\Core\Ini::get('corailv3_cart');

      $charteTab = explode(':', $application->charte)[0];
      $xds_charte = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8(charteTab);
      $br_charte = $xds_charte->browse(array('selectedfields'=>'all', '_mode' => 'object'));
      $favicons = $descriptions = $keywords = '';
      foreach ($br_charte['lines'] as $i => $charte) {
        $favicons .= '<div>Favicon: '.$charte['oicon']->html.'</div><div>AppleTouchIcon: '.$charte['oappletouchicon']->html.'</div>';
        $descriptions .= '<div><b>'.$charte['ometa01']->raw.'</div>';
        $keywords     .= '<div><b>'.$charte['ometa02']->raw.'</div>';
      }

      $xmodinfotree = \Seolan\Core\Module\Module::objectFactory($moid_it);
      if ($moid_nl != null) $xmod_nl = \Seolan\Core\Module\Module::objectFactory($moid_nl);


      // PARAMETRAGE
      $parametrage['Librairie SEOLAN utilisée'] = $GLOBALS['LIBTHEZORRO'];
      $liste = '';
      $edf = getDb()->select('SELECT * FROM MODULES WHERE TOID=25')->fetchAll();
      foreach ($edf as $i => $mod) {

	$options = \Seolan\Core\Options::decode($mod['MPARAM']);

        $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$options['table']);
        $browsable = $translatable = $published = array();
        foreach ($xds->desc as $k => &$field) {
          if ($field->get_browsable())    $browsable[]    = $field->label;
          if ($field->get_translatable()) $translatable[] = $field->label;
          if ($field->get_published())    $published[]    = $field->label;
        }
        $liste.= '<div>'.$this->ajaxLinkProperties($mod['MOID']).$this->ajaxLinkAdmin($mod['MOID']).' '
         .($options['quickquery'] !== '' ? '<span style="background:'.$valid.'">OK' : '<span style="background:'.$error.'">KO').' : '.$mod['MODULE'].' [moid='.$mod['MOID'].']</span>'
         .' Champs : <u title="'.implode("\n",$browsable).'">Listés</u>'
         .' <u title="'.implode("\n",$translatable).'">Traduits</u>'
         .' <u title="'.implode("\n",$published).'">Publiés</u>'
         .'</div>';
      }
      $parametrage['Ensembles de fiches :<br> - Recherche rapide activée<br> - Champs listés<br> - Champs traduits<br> - Champs publiés'] = $liste;
      $checklist['Paramétrage des tables et des modules'] = $parametrage;


      // GRAPHISME
      $graphisme['Validé par RZ PMA LD'] = 'Non automatisé';
      $graphisme['Favicon(s)'] = $favicons;
      $checklist['Charte graphique'] = $graphisme;


      // GESTION DU SITE
      $liste = '';
      $mep = getDb()->select('SELECT distinct title FROM TEMPLATES WHERE gtype="page" ORDER BY title')->fetchAll();
      foreach ($mep as $i => $m) {
        if ($i>0 && $i%round((count($mep)+1)/3) == 0) $liste.= '</div><div style="float:left;margin-right:10px;">';
        $liste.= '<div>'.$m['title'].'</div>';
      }
      $gestion['Mises en page statiques'] = '<div style="float:left;margin-right:10px;">'.$liste.'</div>';
      $liste = '';
      $mep = getDb()->select('SELECT distinct title FROM TEMPLATES WHERE gtype!="page" ORDER BY title')->fetchAll();
      foreach ($mep as $i => $m) {
        if ($i>0 && $i%round((count($mep)+1)/3) == 0) $liste.= '</div><div style="float:left;margin-right:10px;">';
        $liste.= '<div>'.$m['title'].'</div>';
      }
      $gestion['Mises en page dynamiques'] = '<div style="float:left;margin-right:10px;">'.$liste.'</div>';

      if (isset($_REQUEST['grub']) && isset($_REQUEST['oiddelcat'])) {
        $_selected = array();
        $_selected[$_REQUEST['oiddelcat']] = 1;
        $it = \Seolan\Core\Module\Module::objectFactory($_REQUEST['grub']);
        $it->moveToTrash(array('_selected'=>$_selected));
      }
      $liste = '';
      $moid_infotrees[] = $moid_it;
      foreach ($moid_infotrees as $moid_infotree) {
        $xmodit = \Seolan\Core\Module\Module::objectFactory($moid_infotree);
        $liste.= '<div><b>'.$xmodit->table.'</b></div>';
        $pages_test = getDb()->select('SELECT KOID,title,alias FROM '.$xmodit->table.' WHERE (title like "%test%" or alias like "%test%") and alias != "" ORDER BY title')->fetchAll();
        foreach ($pages_test as $i => $page) {
          $liste.= '<div>'.$this->ajaxLink($this->_moid,'<span class="glyphicon csico-delete"></span>','&function=checklist&template=Module/Management.checklist.html&tplentry=br&grub='.$moid_infotree.'&oiddelcat='.$page['KOID']).' '.$page['title'].' <em>['.$page['alias'].']</em></div>';
        }
      }
      $gestion['Supprimer les pages de test'] = empty($liste) ? 'Aucune page de test trouvée' : $liste;
      $gestion['Vérifier les URL absolues'] = 'local.php : '.$GLOBALS['HOME_ROOT_URL'].'<br>Preview rubriques : '.$xmodinfotree->preview.($moid_nl != null ? '<br>Génération NL : '.$xmod_nl->newsletterurl : '');
      $gestion['Vérifier la page de test'] = '<a href="/test.html" target="_blank">GO</a>';
      if (isset($_REQUEST['emptytable'])) {
        getDb()->execute('DELETE FROM '.$_REQUEST['emptytable']);
      }
      $liste = '';
      $tables = array('LOGS','_MLOGS','_MLOGSD','_PLACES','_MARKS');
      foreach ($tables as $table) {
        $count = getDb()->count('SELECT count(*) FROM '.$table);
        $liste.= '<div>'.$this->ajaxLink($this->_moid,'<span class="glyphicon csico-delete"></span>','&function=checklist&template=Module/Management.checklist.html&tplentry=br&emptytable='.$table).' <span style="background:'.($count > 0 ? $error : $valid).'">'.$table.' = '.$count.' enregistrement(s)</span></div>';
      }
      $gestion['Nettoyage des tables système'] = $liste;
      $checklist['Gestion du site']  = $gestion;


      // DIVERS
      $exec = exec('crontab -l',$output);
      $divers['$ crontab -l'] = empty($output) ? array($error,'Aucun cron paramétré') : array($valid,implode('<br>',$output));
      $divers['Activation du cache'] = \Seolan\Core\Ini::get('cache_activated') == '1' ? array($valid,'Oui') : array($error,'Non');
      $divers['Vider le cache'] = $this->ajaxLink($this->_moid,'GO','&function=emptyCache&template=Core.message.html');
      $divers['Paramètres de DEBUG'] = 'Voir local.php :<br>TZR_DEBUG_MODE (var_export) = '.var_export(TZR_DEBUG_MODE,true).'<br>TZR_LOG_LEVEL (var_export) ='.var_export(TZR_LOG_LEVEL,true);
      $divers['Vérifier les mentions légales'] = '<a href="/mentions-legales.html" target="_blank">GO</a>';
      $checklist['Divers'] = $divers;


      // SECURITE
      $liste = '<div>'.$this->ajaxLink(18,'GO','&function=editSec&oid=GRP:*&template=Module/User.secedit.html&tplentry=br').'</div>';
      $securite['Vérification qu\'aucun module n\'est accessible<br>en lecture/écriture au groupe « Tout le monde »'] = $liste;
      $securite['Vérification de la base des utilisateurs :<br> - Suppression des inutiles<br> - Positionner la fin de droit sur les utilisateurs au 31/12/AA+1<br> - Tester le compte webmaster<br> - Vérifier le nombre (Corail = 1)'] = $this->ajaxLink(19,'GO','&function=browse&tplentry=br&template=Module/Table.browse.html');
      $checklist['Sécurité']  = $securite;


      // HEBERGEMENT
      $hebergement['Vérification des stats (installation + reprise)'] = '<a href="http://stats.xsalto.net/" target="_blank">http://stats.xsalto.net/</a>';
      $hebergement['Valider que le site est présent dans private.xsalto.com/admin<br> + cocher la surveillance'] = '<a href="http://private.xsalto.com/admin/" target="_blank">http://private.xsalto.com/admin/</a>';
      $checklist['Hébergement']  = $hebergement;

      // REFERENCEMENT
      $referencement['META description'] = $descriptions;
      $referencement['META keywords'] = $keywords;
      $referencement['Vérifier le plan du site'] = '<a href="/sitemap.html">sitemap.html</a> et <a href="/sitemap.xml">Application/MiniSite/public/templates/sitemap.xml</a>';
      $referencement['Bing Tag'] = $this->checkIniVar('bingtag');
      $referencement['Google Analytics Tag'] = $this->checkIniVar('analytictag');
      $referencement['Google Map API Key'] = $this->checkIniVar('gmap_api_key');
      $referencement['Compte Addthis'] = $this->checkIniVar('addthis_account');
      $referencement['Activation de l\'URL rewriting'] = \Seolan\Core\Ini::get('url_rewriting') == '1' ? array($valid,'Oui') : array($error,'Non');
      $droits = getDb()->select('SELECT AFUNCTION FROM ACL4 WHERE AGRP="GRP:78cdc4ff5091e3371ad9e47447472da6" AND AMOID=20')->fetchAll();
      $referencement['Accès client au module de Référencement'] = array(preg_match('/r|admin/',$droits[0]['AFUNCTION']) ? $valid : $error,'Droits des Gestionnaires de site => <b>'.$droits[0]['AFUNCTION'].'</b>');
      $referencement['TZR.referer'] = preg_match('/TZR\.referer\(/',file_get_contents('http://'.$_SERVER['SERVER_NAME'].'/index.php')) ? array($valid,'Oui') : array($error,'Non');;
      $checklist['Référencement']  = $referencement;


      // MAILING LIST
      if ($moid_nl != null) {
        $mailing_list['Test d\'envoi (xsalto et non xsalto)'] = $this->ajaxLink($moid_nl,'GO','&_function=genSendPre&template=Module/MailingList.xmodmaillistpre.html&tplentry=br');
        $mailing_list['sender'] = $xmod_nl->sender;
        $mailing_list['sendername'] = $xmod_nl->sendername;
        $mailing_list['Email compte rendu d\'envoi'] = $xmod_nl->reportto;
        $mailing_list['Email expéditeur'] = $xmod_nl->from;
        $mailing_list['Préfix du sujet'] = $xmod_nl->prefix;
        $mailing_list['URL de génération'] = $xmod_nl->newsletterurl;
        $checklist['Mailing List [moid='.$moid_nl.'] '.$this->ajaxLinkProperties($moid_nl)] = $mailing_list;
      }


      // CONTACTS
      if ($moid_contacts != null) {
        $xmod_contacts = \Seolan\Core\Module\Module::objectFactory($moid_contacts);
        $contacts['Liste des contacts'] = $this->ajaxLink($moid_contacts,'GO','&function=browse&template=Module/Table.browse.html&tplentry=br&all=1');
        $contacts['Email compte rendu d\'envoi'] = $xmod_contacts->reportto;
        $contacts['Email de l\'expéditeur<br>+ avertissement nouveau contact'] = $xmod_contacts->sender;
        $contacts['Nom de l\'expéditeur'] = $xmod_contacts->sendername;
        $contacts['Sujet'] = $xmod_contacts->subject;
        $checklist['Gestion des contacts [moid='.$moid_contacts.'] '.$this->ajaxLinkProperties($moid_contacts)] = $contacts;
      }


      // BOUTIQUE
      if ($moid_cart != null) {
        $xmod_cart = \Seolan\Core\Module\Module::objectFactory($moid_cart);
        $boutique['TODO'] = 'TODO';
        $checklist['Gestion de boutique [moid='.$moid_cart.'] '.$this->ajaxLinkProperties($moid_cart)] = $boutique;
      }
    } catch (Exception $e) {
      $checklist['Erreur'] = var_export($e,true);
    }

    \Seolan\Core\Shell::toScreen2('checklist','results',$checklist);
  }
}
