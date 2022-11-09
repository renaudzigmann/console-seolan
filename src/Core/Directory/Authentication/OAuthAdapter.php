<?php
namespace Seolan\Core\Directory\Authentication;
/**
 * Adapter for OAuth directories
 * As credentials check is done by service provider backend, authentication 
 * consist in oauth data check (token validity check)
*/
class OAuthAdapter implements \Zend\Authentication\Adapter\AdapterInterface{
  protected $directory = null;
  protected $oauthData = null;
  protected $userObject = null;
  function __construct(\Seolan\Core\Directory\Directory $directory, $oauthData){
    $this->directory = $directory;
    $this->oauthData = $oauthData;
  }
  /**
   * 
   */
  public function authenticate(){
    if ($this->directory == null){
      throw new Class('configuration error : empty directory') extends \Exception implements \Zend\Authentication\Adapter\Exception\ExceptionInterface{};
    }
    try{
      list($ok, $errmess, $userObject) = $this->directory->oAuthAuthenticate($this->oauthData);
    } catch(\Exception $e){
      throw new \Zend\Authentication\Adapter\Exception\InvalidArgumentException($e->getMessage());
    }
    if ($ok) {
      $localAccountExists = getDB()->fetchOne(
        'select koid from USERS where directoryname!=? and alias=?',
        [$this->directory->id, $this->directory->formatAccountData($userObject)['alias']]);
      if ($localAccountExists) {
        $ok = false;
        $errmess = 'local_account_exists';
      }
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