<?php
namespace Seolan\Core\Directory;
/**
 * Directory of users
 * Authenticate and synchronize users
 * User module use a sets of directories for user Authentication
 * the setinclude the localdirectory (console user module) 
 * and may include other directory
 */
abstract class Directory {
  public const CONF_FILE = 'directories-configuration';
  public $id = null;
  protected $config = null;
  protected $modUser;
  protected static $directoriesConfigurations = null;
  /**
   * instanciate a directory defined by config
   * config entries : 
   * - classname of the directory
   * - config, specific for each directory
   */
  public static function ObjectFactory($id, \Zend\Config\Config $config=null){
    if ($config == null){
      $configs = \Seolan\Core\Config::load(self::CONF_FILE);
      if (!isset($configs->$id)){
	throw new \Seolan\Core\Exception\Exception("$id is not a configured directory");
      }
      $config = $configs->$id;
    }
    if (!isset($config->classname) || !isset($config->config))
      throw new \Seolan\Core\Exception\Exception(__METHOD__.': no classname specified');
    $classname = $config->classname;
    return new $classname($id, $config->config);
  }
  function __construct($id, \Zend\Config\Config $config){
    $this->id = $id;
    $this->config = $config;
  }
  /**
   * read configurations (default and local)
   * manage disabled directories
   */
  public static function getConfigurations(){
    if (!isset(self::$directoriesConfigurations)){
      self::$directoriesConfigurations = \Seolan\Core\Config::load(\Seolan\Core\Directory\Directory::CONF_FILE,true);
      $ids = self::$directoriesConfigurations->directoriesId->toArray();
      asort($ids);
      self::$directoriesConfigurations->directoriesId = array_keys(array_filter($ids));
    }
    return self::$directoriesConfigurations;
  }
  public function getConfiguration(){
    return self::getConfigurations()[$this->id];
  }
  /**
   * if directory is qualified for "login", return an Authenticate Adapter
   */
  final public function getAuthenticateAdapter($login,$password):\Zend\Authentication\Adapter\AdapterInterface{
    if ($this->isQualified($login))
      return $this->authenticationAdapterFactory($login,$password);
    throw new \Exception('wrong directory for alias');
  }
  /**
   * if directory is qualified for "login", return an Authenticate Adapter
   */
  final public function getOAuthAuthenticateAdapter($oauth):\Zend\Authentication\Adapter\AdapterInterface{
    if ($this->isQualified($oauth['directoryid']))
      return $this->oAuthAuthenticationAdapterFactory($oauth);
    throw new \Exception('wrong directory for alias');
  }
  /**
   * is the directory qualified for "login"
   */
  public function isQualified(string $login) : bool{
    if (isset($this->config->loginFilter))
        return preg_match($this->config->loginFilter, $login);
    
    return false;
  }
  /**
   * the directory is the only one enable to manage "login"
   */
  public function exclusiveUser(string $login):bool{
    if (isset($this->config->exclusiveFilter)){
      return preg_match($this->config->exclusiveFilter, $login);
    }
    return $this->isQualified($login);
  }
  /**
   * synchronize directory on local database
   * necessary to be used as a cache and to offer complete list of users
   */
  public function synchronize(){
    \Seolan\Core\Logs::notice(__METHOD__." start");
    $this->preSynchronize();
    $list = $this->getItems();
    $userOids = [];
    $cpt = (Object)['tot'=>0,'upd'=>0,'ins'=>0, 'err'=>0];
    
    foreach($list as $item){
      $cpt->tot++;
      
      $userData = $this->formatUserData($item);
      
      list($status, $userOid, $message) = $this->updateUser($userData);
      switch($status){
	case 'inserted':
	  $cpt->ins++;
	case 'updated':
	  $cpt->upd++;
	  // memorisation des ajouts / mises à jours (user actuellement actifs)
	  $userOids[] = $userOid;
	  break;
	default;
	  $cpt->err++;
	  // trace des erreurs
	  \Seolan\Core\Logs::critical(__METHOD__," error : $status, $userOid, $message");
      }
    }

    $this->purgeUsers($userOids);

    $this->postSynchronize($userOids);

    \Seolan\Core\Logs::notice(__METHOD__." end total : {$cpt->tot}, ok : {$cpt->upd} including inserted : {$cpt->ins}, error : {$cpt->err} ");

    return " total : {$cpt->tot}, ok : {$cpt->upd} including inserted : {$cpt->ins}, error : {$cpt->err} ";
    
  }
  /**
   * append / update user in local database
   * return inserted|updated|error, user oid, message
   */
  public function updateUser($userData):?array{

    if (empty($userData))
      return ['error',null,null];
    
    $modUser = $this->getModUser();

    if (!isset($userData['oid'])){
      $userData['oid'] = $modUser->xset->getTable().':'.md5($this->id.$userData['alias']);
    }
    
    $userExists = getDB()->fetchOne($modUser->xset->select_query(['fields'=>'',
								  'cond'=>['KOID '=>['=',$userData['oid']]]]));
    $userData['_options']=['local'=>1];
    $userData['directoryname']=$this->id;
    if ($userExists){
      $r = $modUser->xset->procEdit($userData);
      if (empty($r['oid'])){
	$status = 'error insert';
      } else {
	$status = 'updated';
      }
    } else {
      $userData['newoid'] = $userData['oid'];
      unset($userData['oid']);
      $r = $modUser->xset->procInput($userData);
      if (empty($r['oid'])){
	$status = 'error insert';
      } else {
	$status = 'inserted';
      }
    }
    return [$status,$r['oid'],null];
  }
  /**
   *     

   */
  protected function getModUser():\Seolan\Module\User\User{
    if (!isset($this->modUser)){
      $this->modUser = \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
    }
    return $this->modUser;
  }
  /**
   * delete / deactivate obsolete users -> archive user
   * - search directory users (field directoryname == this->id)
   * - delete old users (module\user::del)
   */
  protected function purgeUsers($currentUsersOids){

    $modUser = $this->getModUser();
    $cond = implode(' AND ',$modUser->activeUsersConds());

    // active user managed by this directory 
    $all = getDb()->fetchCol($modUser->xset->select_query(['fields'=>'',
							   'where'=>$cond,
							   'cond'=>['directoryname'=>['=',$this->id]]]));
    foreach($all as $useroid){
      if (in_array($useroid, $currentUsersOids)){
	// keep 
      } else {
	\Seolan\Core\Logs::notice(__METHOD__,"invalidate/delete user $useroid");
	if ($modUser->xset->fieldExists('PUBLISH')
	    || ($modUser->xset->fieldExists('DATET') && $modUser->xset->fieldExists('DATEF'))){
	  $modUser->procEdit(['_options'=>['local'=>1],'oid'=>$useroid,'PUBLISH'=>2,'DATEF'=>'2000-01-01','DATET'=>'2000-01-01']);
	} else {
	  \Seolan\Core\Logs::update('security', 0, 'user '.$useroid.' should be invalidated, no more existes in directory');
	  //$modUser->del(['_options'=>['local'=>1],'oid'=>$useroid]);
	}
      }
    }
  }
  // __toString
  public function __toString(){
    try{
      $m  = $this->id;
      $m .= " ".get_class($this);
      $m .= "\n\t".$this->config->loginFilter;
      $m .= "\n\t".$this->config->ownFilter;
      $m .= "\n";
      // comme peut servir en debug, ne doit pas faire planter
    } catch(\Throwable $e){}
    return $m;
  }
  /**
   * format user data from result returned by Adapter::getAccountObject
   * intended in case of difference with object returned by getItems
  */
  public function formatAccountData(/*mixed*/$item){
    return $this->formatUserData($item);
  }
  /**
   * check if userOrs is up to date and don't need update
   */
  public function isUptoDate($userData, $userOrs):bool{
    // si user existant, on vérifie le mail et les groupes ...
    if (!$userOrs) {
      return false;
    }
    $userOrs['GRP']=preg_split('/\|\|/', $userOrs['GRP'], null, PREG_SPLIT_NO_EMPTY);
    if ($userOrs['email'] != $userData['email'])
      return false;
    if($userOrs['fullnam'] != $userData['fullnam'])
      return false;
    if ($userOrs['GRP'] != $userData['GRP']) // array
      return false;
    return true;
  }
  /**
   * check before login for authenticated user
   * return [true|false, $message]
   * if false, login process if canceled
   */
  public function postAuthenticationCheck(\Seolan\Core\User $user){
    return [true, null];
  }
  // convenience method for subclasses to implement pre sync processing
  protected function preSynchronize(){}
  // convenience method for subclasses to implement post sync processing
  protected function postSynchronize(array $userOids){}
  /**
   * Instance which manage user update 
   */
  public function getUserManager() :\Seolan\Core\Directory\UserDirectoryInterface{
    return $this;
  }
  /**
   * login credential input 
   */
  public function manageLoginCredentialUI(){
    return null;
  }
  /**
   * return and Authentication Adapter
   */
  protected abstract function authenticationAdapterFactory($login, $password):\Zend\Authentication\Adapter\AdapterInterface;
  /**
   * return the an iterator of associative array representing user to process during synchronisation
   */
  protected abstract function getItems():\Iterator;
  /**
   * create and associative array of data for user insert / updatetrans
   * at least : [oid], alias, [passwd], email, fullnam, [GRP], [DATET], [DATEF], [BO], [PUBLISH], [ldata], [luser], [UPD] - oid can be computed by directory ?
   */
  protected abstract function formatUserData(/*mixed*/ $item)/*:?mixed*/;
  /**
   * method used by adapter check alias/password to be authenticated
   * return object to be processed by calling Adapter
   */
  public abstract function authenticate($login, $password):?array;
}
