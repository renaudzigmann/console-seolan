<?php
namespace Seolan\Core\Directory;

// pour les accès rest api json en attendant
use \Seolan\Core\JsonClient\Request;
use \Seolan\Core\JsonClient\Response;

/**
 * Authentification sur une console distante
 */
class RemoteDirectory extends \Seolan\Core\Directory\Directory
implements \Seolan\Core\Directory\UserDirectoryInterface{
  protected function authenticationAdapterFactory($login, $password) : \Zend\Authentication\Adapter\AdapterInterface {
    return new \Seolan\Core\Directory\Authentication\Adapter($this, $login, $password);
  }
  // Authentification via le web service configuré
  public function authenticate($login, $password):?array{
    
    $requester = new \Seolan\Core\JsonClient\Request([], false);
    $headers = [\Seolan\Core\JsonClient\Request::HEADER_JSON];
    $url = $this->config->url;
    
    \Seolan\Core\Logs::debug(__METHOD__." $login");

    $response = $requester->doRequest('POST', 
				      $url, 
				      ['data'=>['login'=>$login, 'password'=>$password]],
				      $headers);

    if ($response->ok()){
      $responseJson = $response->getData()['data'];
      if ($responseJson['ok']){
	return [true, null, $responseJson['user']];
      } else {
	return [false, $responseJson['message'], null];
      }
    } else {
      return [false, 'unexpected_error', null]; // @todo 
    }
    return null;
  }
  /**
   * pas de synchro par defaut : pass assez d'informations disponibles
   */
  protected function getItems():\Iterator{
        return new \EmptyIterator(); // pas de synchronisation
  }
  /**
   * données minimales pour avoir un user opérationnel
   * l'uid, ici : unique identifier
   */
  protected function formatUserData($item){
    if (isset($item['uid'])){
      $uid = 'USERS:'.md5($this->dirid.$item['uid']);
    } else {
      $uid = null; 
    }
    return ['alias'=>$item['alias'],
	    'email'=>$item['email'],
	    'fullnam'=>isset($item['fullnam'])?$item['fullnam']:$item['alias'],
	    'GRP'=>$this->config->localusergroup->toArray(),
	    'BO'=>1,
	    'PUBLISH'=>1,
	    'DATEF'=>isset($item['DATEF'])?$item['DATEF']:date('Y-m-d'),
	    'DATET'=>isset($item['DATET'])?$item['DATET']:date('Y-m-d'),
	    'UPD'=>date('Y-m-d'),
	    'oid'=>$uid
	    ];
  }
  /**
   * gestion d'un utilisateur
   */
  public function prepareNewPassword($login=null, $oid=null, $which):array{
    return [true,'remote_password_request_message'];
  }
  public function procNewPassword($ar=null):array{
    return [false, null];
  }
  public function prepareNewPasswordInput(string $useroid, string $which, ?array $options=null):array{
    return [false];
  }
  function getAccountFieldssec():?array{
    return ['*'=>'ro'];
  }
}
