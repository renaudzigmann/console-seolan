<?php
namespace Seolan\Core\Directory\Authentication;
/**
 * Zend Authentication Adapter base class for consoles authentication
 * try to authenticate user using associated directory authenticate method
 * https://framework.zend.com/apidoc/2.4/classes/[namespacesparts.]*classes.html
 * Note : the only method required for AdapterInterface is 'authenticate'
 */
class Adapter implements  \Zend\Authentication\Adapter\AdapterInterface{
  protected $alias = null;
  protected $password = null;
  protected $userObject = null;
  protected $directory = null;
  function __construct(\Seolan\Core\Directory\Directory $directory, $login=null, $password=null){
    $this->directory = $directory;
    $this->password = $password;
    $this->alias = $login;
  }
  public function setPassword($password){
    $this->password = $password;
  }
  public function setAlias($alias){
    $this->alias = $alias;
  }
  /**
   * authenticate current using directory method
   */
  public function authenticate(){
    if ($this->directory == null){
      throw new Class('configuration error : empty directory') extends \Exception implements \Zend\Authentication\Adapter\Exception\ExceptionInterface{};
    }
    try{
      list($ok, $errmess, $userObject) = $this->directory->authenticate($this->alias, $this->password);
    } catch(\Exception $e){
      throw new \Zend\Authentication\Adapter\Exception\InvalidArgumentException($e->getMessage());
    }
    switch($ok){
    case true:
      $code = \Zend\Authentication\Result::SUCCESS;
      $messages = [];
      $this->userObject = $userObject;
      break;
    default:
      if (!is_array($errmess))
	$errmess = [$errmess];
      $messages = $errmess;
      $code = \Zend\Authentication\Result::FAILURE;
    }
    return new \Zend\Authentication\Result($code, $this->alias, $messages);
  }
  /**
   * return authenticated user
   * voir Zend\Authentication\Adapter\Ldap::getAccountObject
   * et Zend\Authentication\Adapter\DbTable::getResultRowObject
   */
  public function getAccountObject(){
      if ($this->userObject == null)
          throw new \Seolan\Core\Exception\Exception('no user authenticated');
      return $this->userObject;
  }
}

