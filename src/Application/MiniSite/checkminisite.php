<?php
namespace Seolan\Application\MiniSite;
/**
  * Vérification de l'appel d'un minisite et gestion redirections
  * si minisite non publié, redirection sur Application/MiniSite/public/templates/index.html
  * si appel d'un alias, redirection sur nom d'hote principal
  * Définition GLOBALS / Constantes
  * pas de \Seolan\Core\Logs possible -> utilisation de query
  */
function checkMinisite() {
  global $TZR_LANGUAGES, $DATABASE_NAME, $MASTER_DB_NAME, $HOME_ROOT_URL, $TZR_WWW_DIR, $LOCALLIBTHEZORRO, $START_CLASS, $IS_VHOST, $VHOST_TEMPLATE_OID, $minisite;
  // home_root et equivalents
  if (empty($_SERVER['SERVER_NAME']) || preg_match('@'.$_SERVER['SERVER_NAME'].'@', $HOME_ROOT_URL)) {
    return;
  }
  if (isset($GLOBALS['HOME_ROOT_ALIAS']) && in_array($_SERVER['SERVER_NAME'],$GLOBALS['HOME_ROOT_ALIAS'])){
    return;
  }

  $DB = new \PDO('mysql:host='.$GLOBALS['DATABASE_HOST'].';dbname='.$GLOBALS['DATABASE_NAME'], $GLOBALS['DATABASE_USER'], $GLOBALS['DATABASE_PASSWORD'], array(\PDO::ATTR_PERSISTENT=>false, \PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES utf8', \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true, \PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC));

  // domaines autres que home_root et alias qui seraient des app et pas des minisites
  if (defined('TZR_USE_APP') && TZR_USE_APP == 1){
    $app = $DB->query('SELECT domain FROM APP WHERE domain = "" OR (domain="'.$_SERVER['SERVER_NAME'].'") OR ("'.$_SERVER['SERVER_NAME'].'" REGEXP(domain) AND (domain_is_regex != "2" AND domain_is_regex !=  ""))')->fetchAll(\PDO::FETCH_COLUMN);
    if ($app && in_array($_SERVER['SERVER_NAME'], $app)){
      return;
    }
  }

  $minisite_stmt = $DB->query("SELECT * FROM VHOSTS ".
                          "WHERE vhost='{$_SERVER['SERVER_NAME']}'");
  if (!$minisite_stmt) // pas de table
    return;
  $minisite = $minisite_stmt->fetch(\PDO::FETCH_ASSOC);

  if ($minisite === false) {
    // pas de site, check alias
    $alias = $DB->query("SELECT vhost FROM VHOSTS ".
                          "WHERE aliases REGEXP '{$_SERVER['SERVER_NAME']}'")->fetch();
    if ($alias) {
      header('HTTP/1.1 301 Moved Permanently');
      header('Location: http://'.$alias['vhost'].'/');
      exit;
    }
    // pas de minisite, pas d'alias, pas concerné
    if (function_exists('\ms_notexist')){
      \ms_notexist();
    }else{
      header('Location: '.$HOME_ROOT_URL);
    }
    exit;
  }

  // site non publié, redirection sur site principal (sauf admin et preview)
  if ($minisite['PUBLISH'] == 2 && !isset($_REQUEST['nocache']) && TZR_ADMINI != 1) {
    if (preg_match('@^/([a-z]*.php|[^.]*\.html)?$@', $_SERVER['SCRIPT_URL'])) {
      if (function_exists('\ms_notpublihed'))
        \ms_notpublihed($minisite);
      elseif (defined('MS_NOTPUBLIHED'))
        readfile(MS_NOTPUBLIHED);
      else
        header('Location: '.$HOME_ROOT_URL);
      exit;
    }
    return;
  }

  // gestion des langues du minisite
  if ($minisite['langues']) {
    $TZR_LANGUAGES = $DB->query("select codetzr,codeiso from VHOSTS_LANG where '{$minisite['langues']}' regexp koid")->fetchAll(\PDO::FETCH_COLUMN|\PDO::FETCH_UNIQUE|\PDO::FETCH_GROUP);
  }
  $DB = null;
  $MASTER_DB_NAME = $DATABASE_NAME;
  $DATABASE_NAME = $minisite['db'];
  $VHOST_TEMPLATE_OID = $minisite['ismaster'] == 1 ? $minisite['KOID'] : $minisite['templat'];
  $protocol=!empty($_SERVER['HTTPS'])?'https':'http';
  $HOME_ROOT_URL = $protocol.'://' . $minisite['vhost'];
  $TZR_WWW_DIR = MS_WWW_DIR.$_SERVER['SERVER_NAME'].'/';
  $LOCALLIBTHEZORRO = MS_TZR_DIR.$_SERVER['SERVER_NAME'].'/';
  if ($minisite['startclass'])
    $START_CLASS = $minisite['startclass'];
  elseif(defined('MS_START_CLASS')){
      $START_CLASS = MS_START_CLASS;
  }
  define('CONFIG_INI', MS_TZR_DIR . $minisite['vhost'] . '/local.ini');
  define('TZR_VAR2_DIR', MS_VAR_DIR.$_SERVER['SERVER_NAME'].'/');
  define('TZR_SESSION_PREFIX', str_replace(':', '', $minisite['KOID']));
  $IS_VHOST = true;
  $srcsets = @include_once($LOCALLIBTHEZORRO.'/localsrcsets.php');
  if (isset($srcsets)){
    if (!isset($GLOBALS['TZR_SRCSETS'])){
      $GLOBALS['TZR_SRCSETS'] = [];
    }
    foreach($srcsets as $img_size=>$img_params){
      $GLOBALS['TZR_SRCSETS'][$img_size] = $img_params;
    }
  }
  return;
}
