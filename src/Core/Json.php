<?php
namespace Seolan\Core;

class Json extends \Seolan\Core\Shell {

  protected static $interface = [];
  protected static $aliases = [];
  protected static $modules = [];
  protected static $includes = [];
  protected static $sets = [];
  protected static $pagination = [];
  protected static $errors = [];
  public static $scriptname = 'json.php';
  public static $classname = null;
  protected static $confLoaded= false;

  function __construct($ar = '*', $cache = true) {
    parent::__construct($ar, $cache);
    try {
      static::loadConfigFromInterface();
    }catch(\Exception | \Seolan\Core\Exception\Exception $e){
      \Seolan\Core\Logs::critical('XJSon no interface found');
      header('HTTP/1.1 500 Seolan Server Error');
      die();
    }
  }
  public static function getJsonUri(){
    $url  = @( $_SERVER['HTTPS'] != 'on' ) ? 'http://'.$_SERVER['SERVER_NAME'] :  'https://'.$_SERVER['SERVER_NAME'];
    $url .= in_array($_SERVER['SERVER_PORT'], array( '80' , '443' )) ? ':' . $_SERVER['SERVER_PORT'] : '';

    //A amélioré voir : route /api ...
    if (defined('TZR_ADMINI') && TZR_ADMINI ==  1){
      $path = TZR_WWW_CSX.'scripts-admin/';
    }else
      $path = TZR_WWW_CSX.'scripts/';
    
    $jsonUri = $url.$path.'json.php';
    return $jsonUri;
  }
// ? à virer
  public static function getCoreJson() {
    return static::$classname??__CLASS__ ;
  }
// ? à virer 	
  public static function getUri() {
    return TZR_SHARE_SCRIPTS.static::$scriptname;
  }

  protected static function getStatic($propertiesName, $key=null) {
    static::loadConfigFromInterface();
    if(isset(static::$$propertiesName)){
      if ($key == null){
	return static::$$propertiesName;
      } else {
	if (isset(static::$$propertiesName[$key]))
	  return static::$$propertiesName[$key];
      }
    }
    return null;
  }
  // par défaut la conf est dans la global $JSON_INTERFACE
  protected static function getInterfaceConfig() {
    return $GLOBALS['JSON_INTERFACE'];
  }
  // par défaut la conf est dans la global $JSON_INTERFACE
  public static function hasInterfaceConfig() {
    return !empty($GLOBALS['JSON_INTERFACE']) ;
  }

  // par défaut la conf est dans la global $JSON_INTERFACE
  protected static function loadConfigFromInterface() {
    static::$interface = static::getInterfaceConfig();
    if(static::$confLoaded)
      return;
    if (empty(static::$interface)) {
      throw new \Seolan\Core\Exception\Exception('Bad request parameter',0);
    }

    // lecture conf module, stockage par moid [et alias]
    foreach (static::$interface['modules'] as $moid => $conf) {
      $conf['moid'] = $moid;
      $conf['cleanoid'] = isset($conf['alias'], $conf['objectprefix']);
      if (empty($conf['alias'])) {
        $conf['alias'] = $moid;
      }
      if (!empty($conf['fields'])) {
        foreach ($conf['fields'] as $fieldName => $field) {
          if ($field['alias']) {
            $conf['reverseFields'][$field['alias']] = $fieldName;
          }
        }
      }
      if(static::$interface['tables'][$conf['objectprefix']]){
	$tableConf = static::$interface['tables'][$conf['objectprefix']];
	foreach ( $tableConf['fields'] as $fieldName => $fieldConf ) {
	  if( !isset($conf['reverseFields'][$tableConf['alias']]) ){
	    $conf['reverseFields'][$fieldConf['alias']] = $fieldName;
	  }
	  
	}
      }
      static::$modules[$moid] = $conf;
      if ($conf['alias']) {
        static::$modules[$conf['alias']] = $conf;
      }
    }
    static::$aliases = static::$interface['aliases'];
    static::$sets = static::$interface['sets'];

    // champs spécifié dans la requête (include=field,module.field...)
    if (!empty($_REQUEST['include'])) {
      $includes = explode(',', $_REQUEST['include']);
      foreach ($includes as $include) {
        if (false !== strpos($include, '.')) {
          list($module, $field) = explode('.', $include);
          static::$includes['main'][] = $module;
          static::$includes[$module][] = $field;
        } else {
          static::$includes['main'][] = $include;
        }
      }
    }
    // les options non supportées
    if (isset($_REQUEST['sort'])) {
      throw new \Seolan\Core\Exception\Exception('Bad request parameter',400);
    }
    static::$confLoaded = true;
  }

  public static function restAPI() {
    return !\Seolan\Core\Shell::admini_mode() && !empty(static::$interface['restAPI']) && static::$interface['restAPI'];
  }

  public static function getApiPaths() {
    $APIPath = [TZR_WWW_CSX.'scripts/json.php',TZR_WWW_CSX.'scripts-admin/json.php','/scripts/json.php','/scripts-admin/json.php'];
    if (!empty(static::$interface['APIPath'])) {
      $APIPath[] = static::$interface['APIPath'];
    }
    return $APIPath;
  }

  /**
   * decodage de l'url
   */
  public function decodeRewriting($url) {
    checkIfUrlIsSecure($url);
    $nurl = static::$scriptname.'?';

    $_url = parse_url(preg_replace('@^' . implode('|^', static::getApiPaths()) . '@', '', $url));
    $path = preg_split('@/@', $_url['path'], 0, PREG_SPLIT_NO_EMPTY);
    $alias = $path[0];


    if ($alias == 'call') { 
      // cas /call/module/function ou  /call/class/function
      $module = $path[1];
      if (static::getStatic('modules',$module)) {
        $_REQUEST['moid'] = static::getStatic('modules', $module)['moid'];
        $nurl .= '&moid=' . $_REQUEST['moid'];
      } else {
        $_REQUEST['class'] = $module;
        $nurl .= '&class=' . $_REQUEST['class'];
      }
      $_REQUEST['_function'] = $path[2];
      $nurl .= '&_function=' . $_REQUEST['_function'];
      
      if ($path[3]) {
        $_REQUEST['oid'] = static::makeOid($module, $path[3]);
        $nurl .= '&oid=' . $_REQUEST['oid'];
      }
      $nurl .= '&' . $_url['query'];
    } elseif ($aliasInterface = static::getStatic('aliases',$alias)){
      // par alias
      $_REQUEST = array_merge($_REQUEST, $aliasInterface);
      $nurl .= http_build_query($aliasInterface);
    } elseif (($moduleInterface = static::getStatic('modules', $alias))
	      && isset($moduleInterface['functionalias'][$path[1]])){
      // accès au module par moid ou alias module ret fonction : cas /moid|alias/function
      $_REQUEST['moid'] = $moduleInterface['moid'];
      $_REQUEST = array_merge($_REQUEST, $moduleInterface['functionalias'][$path[1]]);
      $nurl .= http_build_query(static::getStatic('aliases', $alias));
    } elseif (static::getStatic('modules', $alias)) {
      // cas /moid|alias[/id[/submodule[/id]]
      $id = $path[1];
      $subModule = $path[2];
      if (!empty($subModule)) {
        $_REQUEST['moid'] = static::getStatic('modules', $subModule)['moid'];
        $_REQUEST['oid'] = static::makeOid($subModule, $path[3]);
        $_REQUEST['parent_moid'] = static::getStatic('modules', $alias)['moid'];
        $_REQUEST['parent_oid'] = static::makeOid($alias, $id);
      } else {
        $_REQUEST['moid'] = static::getStatic('modules', $alias)['moid'];
        $_REQUEST['oid'] = static::makeOid($alias, $id);
      }
      switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
          $_REQUEST['_function'] = 'procInsertJSon';
          break;
        case 'PUT':
        case 'PATCH':
          $_REQUEST['_function'] = 'procEditJSon';
          break;
        case 'DELETE':
          $_REQUEST['_function'] = 'delJSon';
          break;
        default:
          if (empty($_REQUEST['oid'])) {
            $_REQUEST['_function'] = 'browseJSon';
          } else {
            $_REQUEST['_function'] = 'displayJSon';
          }
          break;
      }
      $nurl .= 'moid=' . $_REQUEST['moid'] . '&_function=' . $_REQUEST['_function'];
      if (!empty($_REQUEST['oid'])) {
        $nurl .= '&oid=' . $_REQUEST['oid'];
      }
      if (!empty($_url['query'])) {
        $nurl .= '&' . $_url['query'];
      }
    }
    \Seolan\Core\Logs::debug(__METHOD__.' <' . $url . '>-><' . $nurl . '>');
  }
  public static function makeOid($moid, $id) {
    if (empty($id)) {
      return NULL;
    }
    if (\Seolan\Core\Kernel::isAKoid($id)) {
      return $id;
    }
    return static::$modules[$moid]['objectprefix'] . ':' . $id;
  }
  public static function cleanOid($moid, $oid) {
    if (empty($oid)) {
      return NULL;
    }
    if (static::$modules[$moid]['cleanoid']) {
      return preg_replace('@([^:]*:)@', '', $oid);
    }
    return $oid;
  }
  // renvoie les paramètres de l'appel pour la fonction lancée par le run
  public function getJSonParams($mod, $function = '') {
    $moid = $mod->_moid;
    $conf = static::getStatic('modules')[$moid];
    $params = $conf['params'] ? $conf['params'] : [];
    if (!is_array($params['options'])) $params['options'] = [];
    if (!is_array($conf['fields'])) $conf['fields'] = [];
    $moduleAlias = static::getModuleAlias($moid);
    $params['alias'] = $moduleAlias;
    $params['cleanoid'] = $conf['cleanoid'];
    if (isset($_REQUEST['_META']) && $_REQUEST['_META'] == 1){
      $params['_meta'] = true;
    }
    // traitement des champs demandés dans l'url
    if ($moid == $_REQUEST['moid'] && static::getStatic('includes')['main']) {
      $includes = static::getStatic('includes')['main'];
    } elseif (static::getStatic('includes')[$moduleAlias]) {
      $includes = static::getStatic('includes')[$moduleAlias];
      }
    if ($includes) {
      for ($i=0, $c = count($includes); $i < $c; $i++) {
        $includes[$i] = static::getFieldFromAlias($moid, $includes[$i]);
      }
    }
    if ($includes) {
      $selectedfields = $includes;
    } else {
      $selectedfields = $params['selectedfields'];
    }
    if (!$selectedfields || $selectedfields == 'all') {
      $selectedfields = $mod->xset->orddesc;
    }
    // le cas échéant, interdire de demander des champs non exposés
    if ($conf['hiddenfields']) {
      $selectedfields = array_diff($selectedfields, $conf['hiddenfields']);
    }
    // si erreur dans les include
    if (empty($selectedfields)) {
      $selectedfields = 'none';
    }
    $params['selectedfields'] = $selectedfields;
    // les champs liens
    static::setLinkConf($moid, $params);
    $params['options'] = array_merge_recursive($params['options'], $conf['fields']);
    // les sous modules
    if ($params['ssmoid']) {
      for ($i = 1; $i <= $mod->submodmax; $i++) {
        $ssmoid = $mod->{'ssmod' . $i};
        if (empty($ssmoid))
          continue;
        if (($params['ssmoid'] == 'all' || in_array($ssmoid, $params['ssmoid']))) {
          $subparams = [];
          if (isset(static::getStatic('modules')[$ssmoid])) {
            $subparams = static::getStatic('modules')[$ssmoid];
          }
          if (isset($params['ssmodoptions'][$ssmoid])) {
            $subparams = array_replace_recursive($subparams, $params['ssmodoptions'][$ssmoid]);
          }
          $params['options'][$ssmoid]['nocount'] = 1;
          $params['options'][$ssmoid]['pagesize'] = -1;
          $ssmodAlias = static::getModuleAlias($ssmoid);
          // les champs depuis la requête
          if (isset(static::getStatic('includes')[$ssmodAlias])) {
            $subparams['selectedfields'] = static::getStatic('includes')[$ssmodAlias];
            if (isset(static::getStatic('modules')[$ssmoid]['hiddenfields'])) {
              $subparams['selectedfields'] = array_diff($subparams['selectedfields'], static::getStatic('modules')[$ssmoid]['hiddenfields']);
            }
            // si erreur dans les include
            if (empty($subparams['selectedfields'])) {
              $subparams['selectedfields'] = 'none';
            }
          }
          static::setLinkConf($ssmoid, $subparams);
          $params['ssmodoptions'][$ssmoid] = $subparams;
        }
      }
    }
    return $params;
  }

  //
  public static function setLinkConf($moid, &$params) {
    foreach ($params['selectedfields'] as $field) {
      $targetMoid = static::getStatic('modules')[$moid]['fields'][$field]['sourcemodule'];
      if (empty($targetMoid))
        continue;
      $targetModAlias = static::getModuleAlias($targetMoid);
      if (isset(static::getStatic('includes')[$targetModAlias])) {
        $params['options'][$field]['target_fields'] = [];
        foreach (static::getStatic('includes')[$targetModAlias] as $targetField) {
          $params['options'][$field]['target_fields'][] = static::getFieldAlias($targetMoid,$targetField,null);
        }
      }
    }
  }
  public static function getFullOidMoid($moid,$id){
    if($prefix = static::getObjectPrefixForMoid($moid)){
      $oid = $prefix.':'.$id;
      if(\Seolan\Core\Kernel::isAKoid($oid))
        return $oid;
    }
    return $id;
  }
  public static function getObjectPrefixForMoid($moid){
    if(array_key_exists($moid,static::getStatic('modules')) && array_key_exists('objectprefix',static::getStatic('modules')[$moid]))  
      return static::getStatic('modules')[$moid]['objectprefix'];
  }
  // param globals
  public static function getGlobalParam($key){
    return static::getStatic('interface')['globals'][$key]??NULL;
    
  }
  /// rend l'alias d'un module
  public static function getModuleAlias($moid) {
    return static::getStatic('modules')[$moid]['alias']??NULL;
  }
  /// rend l'alias d'une table
  public static function getTableAlias($table) {
    return static::getStatic('tables')[$table]['alias']??NULL;
  }
  /// rend la configuration complete d'un module
  public static function getModuleConf($moid) {
    return static::getStatic('modules')[$moid]??NULL;
  }
  
  /// rend les selected fields d'un sous module, tableau
  public static function getSelectFieldsForSubModule($moid) {
    return static::getStatic('modules')[$moid]['subModuleSelectfield'];
  }
  /// l'alias d'un sous module
  public static function getSubModuleAlias($moid,$ssmodkey,$ssmoid) {
    if (isset(static::getStatic('modules')[$moid]['ssmod'][$ssmoid]['alias']))
      return static::getStatic('modules')[$moid]['ssmod'][$ssmoid]['alias'];
    if (static::getModuleAlias($ssmoid))
      return static::getModuleAlias($ssmoid);
    return 'ssmod'.$ssmodkey;
  }
  // renvoie l'alias d'un champ
  public static function getFieldAlias($moid, $field, $table=null) {
      if (isset(static::getStatic('modules')[$moid]['fields'][$field]['alias'])) {
      return static::getStatic('modules')[$moid]['fields'][$field]['alias'];
    }elseif($table){
      if(\Seolan\Core\Kernel::isAKoid($table))
         $table = \Seolan\Core\Kernel::getTable($table);
      
      if (isset(static::getStatic('interface')['tables'][$table]['fields'][$field]['alias'])) {
        return static::getStatic('interface')['tables'][$table]['fields'][$field]['alias'];
      }
    
    }
    return $field;
  }

  // renvoie le champ par son alias
  public static function getFieldFromAlias($moid, $alias) {
    if (isset(static::getStatic('modules')[$moid]['reverseFields'][$alias])) {
      return static::getStatic('modules')[$moid]['reverseFields'][$alias];
    }
    if ($alias == 'lst_upd') {
      return 'UPD';
    }
    return $alias;
  }

  // renvoi la conf pour un champ lien
  public static function getLinkConf($moid, $field) {
    // cas d'un lien
    if (isset($field)) {
      $modConf = static::getStatic('modules')[$moid];
      $fieldConf = $modConf['fields'] === 'all' ? ['alias' => $field] : $modConf['fields'][$field];
      if ($fieldConf['sourcemodule'] && isset(static::getStatic('modules')[$fieldConf['sourcemodule']])) {
        $targetModuleConf = static::getStatic('modules')[$fieldConf['sourcemodule']];
        $fieldConf = array_merge($targetModuleConf, $fieldConf);
      }
    }
    // cas d'une sous fiche
    else {
      // si include est renseigné et ne demande pas ce module
      $moduleAlias = static::getModuleAlias($moid);
      if (isset($_REQUEST['include']) && !isset(static::getStatic('includes')[$moduleAlias])) {
        return NULL;
      }
      $fieldConf = static::getStatic('modules')[$moid];
    }
    if (isset($fieldConf['alias']) && isset(static::getStatic('includes')[$fieldConf['alias']])) {
      $fieldConf['include'] = static::getStatic('includes')[$fieldConf['alias']];
    }
    if (isset($fieldConf['hiddenfields'])) {
      $fieldConf['include'] = array_diff($fieldConf['include'], $fieldConf['hiddenfields']);
    }
    return $fieldConf;
  }

  public static function getSetsPrefix($moid, $field) {
    $moduleAlias = static::getModuleAlias($moid);
    $fieldAlias = static::getFieldAlias($moid, $field);
    return $moduleAlias . '_' . $fieldAlias . '_';
  }

  // enregistrement d'un stringset
  public static function registerSet($table, $field, $soid, $prefix) {
    static::$sets["$table-$field-$soid-$prefix"] = [$table, $field, $soid, $prefix];
  }
  // raz des stringset included
  public static function clearSets() {
    static::$sets = [];
  }
  // type des stringset inclus
  public static function getSetsAlias() {
    if (isset(static::getStatic('interface')['setsAlias'])) {
      return static::getStatic('interface')['setsAlias'];
    }
    return 'stringSet';
  }
  public static function getTableMoid($table, $oid){
    
    if (!$table && $oid){
      $table = \Seolan\Core\Kernel::getTable($oid);
    }
    if (!$table) return;
    $modules = (array) array_keys(\Seolan\Core\Module\Module::modulesUsingTable($table, false, false, true/* Module permettant l'affichage uniquement */, true));


    foreach($modules as $module) {
      if (static::getStatic('modules')[$module])
        return static::getStatic('modules')[$module];
      elseif($module) {
        return ['alias'=>$module,'cleanoid'=> false];
      }
    }
    return NULL;
  }

  // inclusion des stringSets en référence
  protected function addIncludedSets(&$json) {
    if (empty(static::$sets)) {
      return;
    }
    $result = [];
    $stringSets = \Seolan\Field\StringSet\StringSet::getSets();
    $setsAlias = $this->getSetsAlias();
    
    foreach (static::$sets as $set) {
      list($table, $field, $soid, $prefix) = $set;
      $value = [];
      foreach ($stringSets as $lang => $_sets) {
        $localeCode = \Seolan\Core\Lang::getLocaleProp('locale_code', $lang);
        $value[$localeCode] = $_sets[$table][$field][$soid];
      }
      $result[] = ['type' => $setsAlias, 'id' => $prefix . $soid, 'attributes' => ['value' => $value]];
    }
    if (!empty($result)) {
      $json[$setsAlias] = $result;
    }
  }

  // enregistrement de la pagination
  public static function registerPagination($pagesize, $first, $pages) {
    static::$pagination = ['pagesize' => $pagesize, 'first' => $first, 'pages' => $pages];
  }

  // generation de l'objet links avec la pagination
  // self [/ first / last / next / prev]
  protected function addLinks(&$json) {
    $pagination = static::$pagination;
    $json['links'] = array();
    ini_set('arg_separator.output', '&');
    $selfuri = new \Zend\Uri\Http(str_replace('_pretty=1', '', $this->fullurl));
    //$selfuri->makeRelative('');
    $query = $selfuri->getQueryAsArray();
    if (empty($pagination)) {
      $json['links']['self'] = urldecode($selfuri->normalize());
      return;
    }
    if (!isset($query['page']['offset']))
      $query['page']['offset'] = 0;
    if (!isset($query['page']['size']))
      $query['page']['size'] = $pagination['pagesize'];

    $currentoffset = $query['page']['offset'];
    $selfuri->setQuery($query);
    $json['links']['self'] = urldecode($selfuri->normalize());

    if (is_array($pagination['pages'])) {


      $query['page']['offset'] = $pagination['pages'][0];
      $selfuri->setQuery($query);
      $json['links']['first'] = urldecode($selfuri->normalize());

      foreach ($pagination['pages'] as $offset) {
        if ($offset < $currentoffset) {
          $query['page']['offset'] = $offset;
          $selfuri->setQuery($query);
          $json['links']['prev'] = urldecode($selfuri->normalize());
        }
        if ($offset > $currentoffset) {
          $query['page']['offset'] = $offset;
          $selfuri->setQuery($query);
          $json['links']['next'] = urldecode($selfuri->normalize());
          break;
        }
      }

      $query['page']['offset'] = $pagination['pages'][count($pagination['pages']) - 1];
      $selfuri->setQuery($query);
      $json['links']['last'] = urldecode($selfuri->normalize());
    }
  }

  // enregistrement d'une erreur
  public static function registerError($status, $detail, $source = NULL) {
    $error = ['status' => $status, 'detail' => $detail];
    if ($source) {
      $error['source'] = ['pointer' => $source];
    }
    static::$errors[] = $error;
  }

  protected function addErrors(&$json) {
    if (empty(static::$errors))
      return;
    $codes = [];
    foreach (static::$errors as $error) {
      $codes[$error['status']] = $error['status'];
    }
    if (count($codes) == 1) {
      header('HTTP/1.1 ' . reset($codes));
    } else {
      header('HTTP/1.1 500 Seolan Server Error');
    }
    $json['errors'] = static::$errors;
    \Seolan\Core\Logs::debug('XJSon Errors : ' . json_encode(static::$errors));
  }

  function run($ar = '*') {
    \Seolan\Core\Logs::debug('\Seolan\Core\Json::run: start');

    // chargement de la classe
    $class = '';
    // acces des singletons via leur toid 
    if (empty($_REQUEST['moid']) && !empty($_REQUEST['toid']) && !is_array($_REQUEST['toid'])) {
      $_REQUEST['moid'] = \Seolan\Core\Module\Module::getMoid($_REQUEST['toid']);
    }
    $moid = (empty($_REQUEST['moid']) ? NULL : $_REQUEST['moid']);
    if (!empty($_REQUEST['_class']))
      $class = $_REQUEST['_class'];
    elseif (!empty($_REQUEST['class']))
      $class = $_REQUEST['class'];

    $mime = 'application/vnd.api+json';
    $template = 'json.json';
    $_disp = 'inline';
    $charset = \Seolan\Core\Lang::getCharset();

    try {
      // chargement de la function
      if (!empty($_REQUEST['_function']))
        $this->_function = $f = $_REQUEST['_function'];
      elseif (!empty($_REQUEST['function']))
        $this->_function = $f = $_REQUEST['function'];
      if (empty($this->_function)) {
        throw new \Seolan\Core\Exception\EntityNotFound('entity '.$_SERVER['REQUEST_URI'].' not found');
      }


    // si le cache est utilisable (Front)

      if ($this->_cache) {
        $cache = new \Seolan\Core\Cache();
        $cache->setCachePolicy();
        $_SERVER['QUERY_STRING'].='&_uid=' . \Seolan\Core\User::get_current_user_uid();

        // essayer de servir la page depuis le cache
        if ($cache->delivery($template, $mime, $_disp, $ar)) {
          \Seolan\Core\Logs::debug('\Seolan\Core\Json::run: page delivered by cache');
          $this->exit_tzr();
        }
        if (\Seolan\Core\Cache::putPageInServerCache() && \Seolan\Core\System::tableExists('_PCACHE') && ($cachemodule = \Seolan\Core\Module\Module::singletonFactory(XMODCACHE_TOID))) {
          $cachemodule->registerPageFailure($this->fullurl);
        }
      }
      // chargement de l'utilisateur
      $this->_load_user();
      
      // Activation des Apps
      if (TZR_USE_APP) {
        \Seolan\Module\Application\Application::runApps();
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
      \Seolan\Core\Labels::loadLabels('\Seolan\Core\Field\Field');
      
      // cas ou il y a une classe
      if (!empty($moid) && empty($class)) {
        $ob = \Seolan\Core\Module\Module::objectFactory(array('moid' => $moid, 'interactive' => true));
        \Seolan\Core\Logs::debug('\Seolan\Core\Json::run: class is empty, moid=' . $moid);
      } elseif (!empty($class) && (strtolower($class) != strtolower(get_class($this)))) {
	// dans le cas ou la classe n'existe pas encore, on essaie d'include
	// le fichier qui correspond a la classe
        if (!class_exists($class)) {
          \Seolan\Core\Logs::critical("XJSon class $class not found");
          header('HTTP/1.1 400 Bad Request');
	  exit;
	}
        \Seolan\Core\Logs::debug('\Seolan\Core\Json::run: class is ' . $class);
        $ob = new $class(array('interactive' => true));
      } else {
        \Seolan\Core\Logs::debug('\Seolan\Core\Json::run: class is empty and moid is empty');
        $ob = $this;
        $class = get_class($this);
      }

      // cas ou il y a une fonction
      $LANG_DATA = \Seolan\Core\Shell::getLangData();
      if (!empty($f)) {
        // verification des droits : on créé un tableau avec tout les elements succeptibles d'etre utilisés
        if (!empty($_REQUEST['oid']))
          $oid = $_REQUEST['oid'];
        else
          $oid = '';
	
        \Seolan\Core\Logs::notice(__METHOD__.' uri_decode', $_SERVER['REQUEST_URI'] . "->class=$class&function=$f&oid=" .
				  (is_array($oid)?implode(',', $oid):$oid) . "&moid=$moid&lang=$LANG_DATA");
        if (!$this->security_check($class, $f, $moid, $LANG_DATA, $oid, true)) {
          throw new \Seolan\Core\Exception\Forbidden();
	}
	
        if (method_exists($ob, 'secObjectAccess') && !is_array($oid)) {
          $ob->secObjectAccess($f, $LANG_DATA, $oid);
	}
      }
      
      // appel de la fonction de la page en cours
      if (!empty($f)) {
        $params = $this->getJSonParams($ob, $f);
        $result = $ob->$f($params);
	if ($params['_meta']){
	  $meta = $ob->getJSonTypeMeta($params);
	} else {
	  $meta = null;
	}
      }
      $json = ['data' => $result];
      if (!$this->restAPI()) {
        $json['sessionid'] = session_id();
      }
      if ($meta != null){
	$json['meta'] = $meta;
      }
      $this->addIncludedSets($json);
      $this->addErrors($json);
      $this->addLinks($json);

      $flags = null;
      if ($_REQUEST['_pretty']) {
        $flags = JSON_PRETTY_PRINT;
      }
      $display = json_encode($json, $flags);
      $headers = [];
      $headers[] = 'Content-type: ' . $mime . '; charset=' . strtolower($charset);
      $headers[] = 'Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT';
      $headers[] = 'Content-disposition: ' . $_disp;
      
      // mettre en cache et envoyer le contenu
      if ($this->_cache) {
	$cache->storeAndDeliver($display, $template, $ar, $headers);
      } else {
        foreach ($headers as $header)
          header($header);
        if ((empty($_SERVER['HTTP_USER_AGENT']) || substr($_SERVER['HTTP_USER_AGENT'], 0, 6) != "Smarty") && $charset != TZR_INTERNAL_CHARSET) {
	  convert_charset($display,  TZR_INTERNAL_CHARSET, $charset);
	}
	
	echo $display;
      }
    } catch (\Seolan\Core\Exception\Forbidden $e) {
      \Seolan\Core\Logs::critical(__METHOD__,"{$e->getMessage()} at line {$e->getLine()} in {$e->getFile()}");
      \Seolan\Core\Logs::critical(__METHOD__."\n".backtrace2());
      header('Content-type: ' . $mime . '; charset=' . strtolower($charset));
      header('HTTP/1.1 403 Forbidden');
      echo json_encode(['errors' => [[
          'status' => $e->getCode() ? $e->getCode() : 403,
          'detail' => "{$e->getMessage()}"
          ]]
      ]);
    } catch (\Exception | \Seolan\Core\Exception\Exception $e) {
      \Seolan\Core\Logs::critical(__METHOD__,"{$e->getMessage()} at line {$e->getLine()} in {$e->getFile()}");
      \Seolan\Core\Logs::critical(__METHOD__."\n".backtrace2());
      header('Content-type: ' . $mime . '; charset=' . strtolower($charset));
      header('HTTP/1.1 ' . ($e->getCode() ? $e->getCode() . ' ' . $e->getMessage() : '500 Seolan Server Error'));
      echo json_encode(['errors' => [[
          'status' => $e->getCode() ? $e->getCode() : 500,
          'detail' => "{$e->getMessage()}"
          ]]
      ]);
      \Seolan\Core\Logs::debug(__METHOD__.' '. $e->getMessage() . PHP_EOL . $e->getTraceAsString());
    }
    \Seolan\Core\Logs::debug(__METHOD__.' end');

    // log audit infos
    \Seolan\Core\Logs::notice(__METHOD__, \Seolan\Core\Audit::show());
    
    return true;
  }

  function _load_user($ar = NULL) {
    if ($this->restAPI() && static::_function() != 'procRestAuth' && static::_function() != 'remoteAuthentication') {
      $GLOBALS['TZR_SESSION_MANAGER']::checkRestAuth();
    }
    return parent::_load_user($ar);
  }

  static function redirect2error($ar = NULL) {
    throw new \Seolan\Core\Exception\Exception($ar['message'], '403');
  }

}
