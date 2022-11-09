<?php
namespace Seolan\Core\Directory;

class LdapDirectory extends \Seolan\Core\Directory\Directory implements \Seolan\Core\Directory\UserDirectoryInterface{
  private $zendldap = null;
  function __desctruct(){
    // connexion ldap éventuelles ?
    if ($this->zendldap == null){
      $this->zendldap->close(); // ?
    }
  }
  protected function authenticationAdapterFactory($login, $password) : \Zend\Authentication\Adapter\AdapterInterface {
    $server = $this->config->defaultserverid;
    $params = [];
    $params['server'] = $this->config->$server->toArray(); // un tableau de serveurs voir doc Zend
    return new \Zend\Authentication\Adapter\Ldap($params,
						 $login,
						 $password
    );
  }
  /// done by \Zend\Authentication\Adapter\Ldap directly
  public function authenticate($login, $password) : array{ throw new \Seolan\Core\Exception('not implemented, use Adapter/Ldap');}
  /// retourne un objet ldap pour faire des recherches etc ...
  protected function getLdap($serverid=null){
    if (!isset($this->zenldap)){
      if ($serverid == null)
	$serverid = $this->config->defaultserverid;
      $this->zendldap = new \Zend\Ldap\Ldap();
      $this->zendldap->setOptions($this->config->$serverid->toArray());
      $this->zendldap->bind();
    }
    return $this->zendldap;
  }
  /**
   * iterator sur les items ldap 
   */
  protected function getItems() : \Iterator{
    $ldap = $this->getLdap();
    $filter = $this->config->sync['filter']??'(objectclass=*)';
    $scope = \Zend\Ldap\Ldap::SEARCH_SCOPE_SUB;
    $searchdn = $this->config->sync['baseDn']??$ldap->getBaseDn();
    $results = $ldap->search($filter,
			     $searchdn,
			     $scope
    );
    return $results;
  }
  /**
   * format user data provide by LdapAdapter::getAccountObject
   */
  public function formatAccountData($item){
    return $this->formatUserData($item);
  }
  /**
   * basic user data from ldap search result item
   * depending on the schema, provide required fields for users
   * oid should be composed with dn (and directory id ?)
   */
  protected function formatUserData($item){
    return null;
  }
  public static function authenticateResultToString(\Zend\Authentication\Result $res){
    return 'Code : "'.$res->getCode().'", identity : "'.$res->getIdentity().'", messages : "'.implode(',', $res->getMessages()).'"';
  }
  // selon l'origine (formatUserData formatAccountData, on a un tableau ou une stdclass)
  protected function getAttribute($item, $name){
    if (is_object($item)){
      if (!isset($item->$name) || empty($item->$name))
	return null;
      if (is_array($item->$name)){
	return $item->$name[0];
      }
      return $item->$name;
    } else {
      if (!isset($item[$name]) || empty($item[$name]))
	return null;
      if (is_array($item[$name])){
	return $item[$name][0];
      }
      return $item[$name];
    }
  }
  /**
   * gestion d'un utilisateur
   */
  public function prepareNewPassword($login=null, $oid=null, $which):array{
    return [true,'ldap_password_request_message'];
  }
  public function procNewPassword($ar=null):array{
    return [false,null];
  }
  public function prepareNewPasswordInput(string $useroid, string $which, ?array $options=null):array{
    return [false];
  }
  function getAccountFieldssec():?array{
    return ['*'=>'ro'];
  }
}
