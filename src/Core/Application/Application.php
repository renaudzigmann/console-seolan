<?php
namespace Seolan\Core\Application;
/**
 * @author Julien Maurel (mis à jours par Bastien Sevajol)
 * Classe mère des "Apps". Une "app" est un objet instancié et rendu disponible (en tant que singleton
 * cf. \Seolan\Module\Application\Application::get) en fonction du domaine (xxx.monsite.com) courant.
 */
use \Seolan\Core\Module\Module;
use \Seolan\Core\Logs;

abstract class Application {
  /**
   * Retourne le nom complet de la table de configuration de ce sous site
   * Utiliser: \Seolan\Core\Application\Wizard::getCompleteTableName(); (remplacer XAppWd par votre class wizard)
   * @return string
   */
  abstract public static function getCompleteTableName();
  abstract public function getObjectForFunctionExecution($function) : ?\Seolan\Core\ISec;
  /**
   * une application peut traiter le rewriting (encode et decode)
   * @return ?\Seolan\Core\IRewriting
   */
  public function getRewriter() : ?\Seolan\Core\IRewriting{
      return null;
  }
  /**
   * liste des protocoles pour les gabarits
   * @return array
   */
  protected function getTemplateProtocols() : array{
    return [];
  }
  /**
   * entêtes spécifiques à l'application
   */
  public function getComplementHeaders() : array {
    return [];
  }
  /**
   * objet ressource pour un protocole donné
   * @param string $protocol
   * @return ?\Smarty_Internal_Resource_File
   */
 protected function getTemplateResource(string $protocol) : ?\Smarty_Internal_Resource_File{
    null;
  }
 /**
  * repertoires spécifiques pour les gabarits
  * @NOTE : table clé valeurs dans l'ordre de préférence
  */
 protected function getApplicationTemplatesDirs() : ?array{
   return null;
 }
 /**
  * liste des protocoles et objets de traitement des gabarits
  * les applications 'doivent' mettre en place les 2 methodes ci-dessus
  */
 final public function getTemplatesResources() : ?array{
   $protocols = $this->getTemplateProtocols();
   $list = [];
   foreach($protocols as $protocolName){
     $resourceObject = $this->getTemplateResource($protocolName);
     if ($resourceObject != null){
       $list[$protocolName] = $resourceObject;
     }
   }
   if (count($list) > 0)
     return $list;
   else
     return null;
 }
 /**
  * répertoires spécifiques des gabarits
  * @NOTE : seront montés après les dir csx et tzr
  */
 final public function getAdditionnalTemplatesDirs() : ?array{
   $dirs = $this->getApplicationTemplatesDirs();
   if ($dirs != null ){
     // controle que ce sont des emplacements acceptables ?
     return array_unique($dirs);
   } else {
     return null;
   }
 }
  /**
   * @author Julien Maurel
   * @param null $params
   */
  public function __construct($params=NULL){
    if($params){
      foreach($params as $k=>$v){
	       $this->$k=$v;
      }
    }
  }

  /**
   * Hook appelé après la création d'un module
   * @author Julien Maurel
   * @param $mod
   */
  public function newModuleHook($mod){}

  /**
   * Retourne les KOIDs de config correspondant aux groupes donnés.
   * @param array $groups (liste de koids)
   * @return array of config koids
   */
  protected static function getConfigsKoidsForGroups(array $groups) {
    return getDB()->fetchCol('SELECT DISTINCT (KOID) FROM '.static::getCompleteTableName()
      .' WHERE app_admin_group IN ("'.implode('","', $groups).'")');
  }

  /**
   * Retourne tous les KOIDs de config.
   * @return array of config koids
   */
  protected static function getConfigsKoids() {
    return getDB()->fetchCol('SELECT DISTINCT (KOID) FROM '.static::getCompleteTableName());
  }

  /**
   * @author Julien Maurel
   * Cette méthode doit contenir les différentes opérations que l'app doit effectuer au début d'un traitement.
   * C'est \Seolan\Core\Shell::run qui déclenche le processus.
   */
  public function run() {}



  /**
   * Retourne une collection de config koid de sites autorisés en fonction du contexte courant (FO, BO)
   * TODO: Nettooyer et update comment
   * @return array of config koids (koids de sous sites)
   */
  public function getAllowedConfigKoids() {
    // Si on est en Front-Office, c'est le domaine qui prime.
    //if (!\Seolan\Core\Shell::admini_mode()) {
    return array($this->getConfigKoid());
    //}
    // Sinon on déduit les "sous-sites" autorisés en fonction du/des groupe(s) de l'utilisateur.
    //return $this->getAppConfigkoidsForCurrentUserGroup();
  }

  /**
   * Retourne l'identifiant de la configuration du site en cours (qui est dans le champs des enregistrements lié à un
   * site)
   * @return String
   */
  public function getConfigKoid() {
    return $this->config_oid;
  }

  /**
   * Retourne les KOIDs de config correspondant aux groupes de l'user actuellement connecté.
   * @return array of config koids
   */
  public static function getAppConfigkoidsForCurrentUserGroup() {
    if (\Seolan\Core\Shell::admini_mode() && \Seolan\Core\Shell::isRoot()) {
      return static::getConfigsKoids();
    }

    $user = \Seolan\Core\User::get_user();
    if ($user instanceof \Seolan\Core\User) { // A clarifier: pendant formation PMA $user n'était pas un objet ...
      if (($user_groups = $user->groups())) {
        return static::getConfigsKoidsForGroups($user_groups);
      }
    }

    return array();
  }

  /**
   * Retourne un display de l'enregistrement de configuration de l'app
   * @param array $display_ar
   * @return array
   * @throws Exception
   */
  public function getConfig($display_ar = array()) {
    $display_ar = array_merge(array(
      'oid' => $this->getConfigKoid(),
      'selectedfields' => 'all',
      'tplentry' => TZR_RETURN_DATA
    ), $display_ar);

    return $this->getConfigDataSource()->display($display_ar);
  }

  /**
   * Retourne le XDataSource de la table de configuration.
   *
   * @param bool $instanciate Force instantation of config_xds (use it just after created it)
   * @return XDataSource
   * @throws Exception
   */
  public function getConfigDataSource($instanciate = False) {
    if ($instanciate) {
      $this->config_xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.static::getCompleteTableName());
    }

    if (!$this->config_xds) {
      throw new Exception("Can't use config_xds before prepared it !");
    }
    return $this->config_xds;
  }

  /**
   * Retourne une liste de APP.KOID sur lesquels l'utilisateur courant à accès.
   * @return array: Apps koids
   */
  public static function getAllowedAppsKoidsForCurrentUser() {
    $apps_oids = array();
    $user_groups = array();
    $groupCond = array();

    $appFromDb = \Seolan\Module\Application\Application::getAppsFromDB(true, true);
    $appFromDomain = $appFromDb ? $appFromDb->fetch() : false;
    if($appFromDomain) {
      $apps_oids = array($appFromDomain['KOID']);
    }

    $user = \Seolan\Core\User::get_user();
    if ($user instanceof \Seolan\Core\User) {
      $user_groups = $user->groups();
      foreach($user_groups as $group) {
        $groupCond[] = "(groups like '%$group||%')";
      }
    }

    // Le groupe admin a accès à tout
    if(in_array('GRP:1', $user_groups)) {
      $apps_oids = array_merge($apps_oids, (array) getDB()->fetchCol('select KOID from APP'));
    }
    elseif(count($groupCond)) {
      $groupCond[] = "(groups in ('".implode("','", $user_groups)."'))";
      $groupCond = implode(" OR ", $groupCond);
      $apps_oids = array_merge($apps_oids, (array) getDb()->fetchCol("SELECT KOID FROM APP where $groupCond"));
    }

    return array_unique($apps_oids);
  }

  public static function getAllowedApps() {
    $apps = array();
    $appOids = self::getAllowedAppsKoidsForCurrentUser();
    foreach($appOids as $appOid) {
     $apps[$appOid] = getDB()->fetchOne('select name from APP where KOID=?', array($appOid));
    }

    return $apps;
  }

  public function prioritizeUserApps() {
    $user_apps_koids = static::getAllowedAppsKoidsForCurrentUser();
    $xmodapp = new \Seolan\Module\Application\Application();
    $xmodapp->setForcedApps(array('apps_koids' => $user_apps_koids), False);
  }
  /**
   * Liste des modules qu'utilise l'application
   * @note : pourrait passer en abstract ?
   * @note : voir attached modules ?
   */
  public function getUsedModules():array{
    return [];
  }
  /**
   * traitements automatiques spécifiques à l'application
   */
  public /*final ?*/ function daemon(string $period='any', Daemon $appDaemon){

    $appDaemon->setApplication($this);

    foreach($this->getUsedModules() as $moid){

      $mod = Module::objectFactory(['moid'=>$moid, 'tplentry'=>TZR_RETURN_DATA, 'interactive'=>false]);

      Logs::notice(__METHOD__,"running application '{$this->name}' daemon  on '{$mod->getLabel()}'");

      $appDaemon->setModule($mod);
      $appDaemon->run($period);

    }
  }
}
