<?php
/**
 * installation du module solr dans une console
 * - vérification que le module n'existe pas déjà
 * - création d'une entrée dans les comptes externes
 * - recherche de l'ip du serveur
 * - vérification que le solr tourne ?
 * - création du module
 * - paramétrage du module  :
 *   host = ip trouvée
 *   active = false
 *   port = 8983
 *   core = database name
 *   les autres paramètres sont à renseigner quand la création du cœeur est effective
 *   il faut compléter le user / mot de passe dans les comptes externes
 *
 * php7.4 scripts/cli/upgrades.php --config ~/consoles/consoleX2-1/tzr/local.php  --class \\Seolan\\Module\\Search\\Search --upgrade 20220228_install
 *
 */
use \Seolan\Core\Ini;
use \Seolan\Library\Upgrades;
use \Seolan\Core\Module\Module;
use \Seolan\Core\Labels;
use \Seolan\Module\Search\Wizard as SearchWiz;
use \Seolan\Module\Search\Search as SearchMod;
use \Seolan\Library\SolR\SearchV2 as Search2;
use \Seolan\Library\SolR\Helper as SearchHelper;
use \Seolan\Core\DataSource\DataSource;

function Search_20220228_install(){
  
  $exists = getDB()->fetchOne('select 1 from MODULES where toid=?', [200]);
  if ($exists){
    $mod = Module::singletonFactory(200);
    $replace = Upgrades::readline("Module Search '{$mod->getLabel()}' already exists. Replace ? (y/n)", 'y');
    if (strtolower($replace) == 'y'){
      $mod->delete([]);
      Module::clearCache();
    } else {
      echo("\n module already exists : {$mod->getLabel()} {$mod->_moid}\n");
      return;
    }
  }
  if (!DataSource::sourceExists('_ACCOUNTS')){
    echo("\n not _ACCOUNTS table");
    return;
  }

  // compte core
  $account = getDb()->select('select koid, name, login, upd from _ACCOUNTS where atype=?', [SearchMod::$accountType]);
  if ($account->rowCount() != 0){
    echo(Upgrades::sqldump($account));
    unset($replace);
    $replace = Upgrades::readline("Somme SolR accounts exists in table _ACCOUNTS, clear accounts (Y/n) ?", "y");
    if (strtolower($replace) == 'y'){
      getDb()->execute("delete from _ACCOUNTS where atype=?", [SearchMod::$accountType]);
    } else {
      if ($account->rowCount() != 1){
	echo("\n multiples solr _ACCOUNTS founds, only 1 required");
	return;
      }
    }
  }
  // compte admin
  $aaccount = getDb()->select('select koid, name, login, upd from _ACCOUNTS where atype=?', [SearchMod::$adminAccountType]);
  if ($aaccount->rowCount() != 0){
    echo(Upgrades::sqldump($aaccount));
    unset($replace);
    $replace = Upgrades::readline("Somme SolR Admin Accounts exists in table _ACCOUNTS, clear accounts (y/n) ?", "y");
    if (strtolower($replace) == 'y'){
      getDb()->execute("delete from _ACCOUNTS where atype=?", [SearchMod::$adminAccountType]);
    } else {
      if ($aaccount->rowCount() != 1){
	echo("\n multiples solr admin _ACCOUNTS founds, only 1 required");
	return;
      }
    }
  }
  
  $host = SearchHelper::solrIp();
  if (empty($host))
    $host="127.0.0.1";
  
  $host = Upgrades::readline("Server IP address", $host); 
  
  $port = Upgrades::readline("Server port", '8983');
  $core = Upgrades::readline("Core name", $GLOBALS['DATABASE_NAME']);
  $user = Upgrades::readline("Core User", $GLOBALS['DATABASE_NAME']);
  $passwd = Upgrades::readline("Core Password", '');
  $auser = Upgrades::readline("Admin User", 'solradmin');
  $apasswd = Upgrades::readline("Admin Password", 'solRIsFun');
  $activate = Upgrades::readline("Activate indexation (y/n)", 'Y');
  $reset = Upgrades::readline("Force indexation (y/n)", 'Y');

  $activate = strtolower($activate)=='y'?1:0;
  $reset = strtolower($reset)=='y'?true:false;
  
  Upgrades::readlineShow([
    " Création du module Search, préconfiguration : "=>"\n",
    "  host "=>$host,
    "  port "=>$port,
    "  core "=>$core,
    "  user "=>$user,
    "  passwd"=>$passwd,
    "  admin user"=>$auser,
    "  admin pawword"=>$apasswd
  ]);

  // check : solr est accessible, le oceur existe
  if (!SearchHelper::v2CoreReady($core, $port, $host, ['user'=>$auser,'passwd'=>$apasswd])){
    echo("\n Error,host or core not found");
    exit();
  }
  echo("\n\nCore '{$core}' found on '{$host}:{$port}'\n\n");
  $confirm = Upgrades::readline("Confirm module insertion (y/n) ?", "Y");
  if (strtolower($confirm) != 'y'){
    exit();
  }  
  
  $wiz = new SearchWiz();
  $group = Labels::getTextSysLabel("Seolan_Core_General", "systemproperties");
  $moid = $wiz->quickcreate("Recherche SolR", ['group'=>$group,
					       'solr_port'=>$port,
					       'solr_host'=>$host,
					       'solr_core'=>$core,
					       'solr_active'=>$activate,
					       ]);
  
  echo(Upgrades::sqldump('select toid, moid, module from MODULES where moid=?',[$moid]));
  echo(Upgrades::sqldump('select * from MODULES where toid=200'));

  $ds = DataSource::objectFactoryHelper8('_ACCOUNTS');

  $ds->procInput(['_options'=>[],
		  'login'=>$user,
		  'passwd'=>$passwd,
		  'modid'=>$moid,
		  'name'=>SearchMod::$accountType,
		  'atype'=>SearchMod::$accountType
  ]);
  
  $ds->procInput(['_options'=>[],
		  'login'=>$auser,
		  'passwd'=>$apasswd,
		  'modid'=>$moid,
		  'name'=>SearchMod::$adminAccountType,
		  'atype'=>SearchMod::$adminAccountType
  ]);

  echo(Upgrades::sqldump('select * from _ACCOUNTS where atype=? or atype=?', [SearchMod::$accountType,SearchMod::$adminAccountType]));

  echo("\n\n Module Search added in '$group'\n\n");

  if ($reset){
    $nb = getDB()->fetchOne('select count(*) from _VARS where name like "lastindexation\_%"');
    echo($nb);
    getDb()->execute('delete from _VARS where name like "lastindexation\_%"');
    echo(Upgrades::sqldump('select count(*) from _VARS where name like "lastindexation\_%"'));
    echo("\indexation reset for : $nb module(s)");
  }

  Module::clearCache();
  SearchHelper::checkVersion();
  $mod = Module::singletonFactory(XMODSEARCH_TOID);
  $i = $mod->getInfos();
  var_dump($i['infos']['solrstatus']);


  // ancienne conf 

  $old = [];
  foreach(['host','port','core','activated'] as $n){
    $old[$n] = Ini::get("solr_{$n}");
  }

  if (isset($old['port']) || isset($old['host']) || isset($old['core']) || isset($old['activated'])){
    $ini = new Ini();
    $rep = Upgrades::readline("Effacer la conf. solr du local.ini ? (y/n)", "y");
    if (strtolower($rep)=='y'){
      foreach(['host','port','core','activated'] as $n){
	if (isset($old[$n])){
	  $ini->delVariable(['variable'=>"solr_{$n}"]);
	  $ini->addVariable(['section'=>'solrold',
			     'variable'=>"solr_old_{$n}",
			     'value'=>$old[$n]]);
	}
      }
    }
  }

  return;

}


