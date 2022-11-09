<?php
namespace Seolan\Core\Directory;
/**
 * Interface avec un 'annuaire' OAuth (google, facebook)
 * Comme pour les autres RemoteDirectory de base :  pas de synchronisation
 */
abstract class OAuth extends \Seolan\Core\Directory\Directory 
implements \Seolan\Core\Directory\UserDirectoryInterface{
  protected abstract function oAuthAuthenticationAdapterFactory($oauth):\Zend\Authentication\Adapter\AdapterInterface;
  protected abstract function oAuthAuthenticate($oauth):?array;
  protected function authenticationAdapterFactory($a, $b):\Zend\Authentication\Adapter\AdapterInterface{
    return $this->oAuthAuthenticationAdapterFactory($a);
  }
  public function authenticate($a, $b):?array{
    return $this->oAuthAuthenticate($a);
  }
  public function isQualified(string $dirid) : bool{
    return (md5($this->id) == $dirid);
  }
  /**
   * the directory is the only one enable to manage "login"
   */
  public function exclusiveUser(string $dirid):bool{
    return $this->isQualified($dirid);
  }
  public function prepareNewPassword($login=null, $oid=null, $which):array{
    return [true,'remote_password_request_message'];
  }
  public function prepareNewPasswordInput(string $useroid, string $which, ?array $options=null):array{
    return [false];
  }
  public function procNewPassword($ar=null):array{
    return [false, null];
  }
  function getAccountFieldssec():?array{
    return ['*'=>'ro'];
  }
  protected function getItems():\Iterator{
        return new \EmptyIterator(); // pas de synchronisation
  }
  /**
   * return service  named fields and mapping 
   */
  protected function getFields(){
    if (empty($this->config['userfields'])){
	$fields = static::$userfields;
    } else {
      $fields = $this->config['userfields']->toArray();
    }
    return $fields;
  }
}