<?php
namespace Seolan\Directory\OKTA;
/**
 * pour avoir les infos :
 * https://%s/oauth2/default/.well-known/oauth-authorization-server
 * OKTA Management API et exemples : https://developer.okta.com/code/php/
 * exemples code login : https://developer.okta.com/blog/2018/07/09/five-minute-php-app-auth
 */

use \Seolan\Core\{Logs,Module\Module};

class OpenIdConnect extends \Seolan\Core\Directory\OpenIdConnect{
  private static $metaUrl = "https://%s/oauth2/default/.well-known/oauth-authorization-server";
  protected static $defaultLoginCredentialsTemplate = 'Directory/OKTA.credentials.html';
  function __construct($id, \Zend\Config\Config $config){
    parent::__construct($id, $config);
    try{
      $url = file_get_contents(sprintf(static::$metaUrl, $config['okta_domain']));
      $this->meta = json_decode($url);
    } catch(\Exception $e){
      Logs::critical(__METHOD__, "unable to load meta conf at {$url}");
    }
  }
  protected function getEndPoint(string $name):string{
    switch($name) {
    case 'issuer':
      return $this->meta->issuer;
    case 'authorization':
      return $this->meta->authorization_endpoint;
    case 'token':
      return $this->meta->token_endpoint;
    case 'introspection':
      return $this->meta->introspection_endpoint;

    case 'userinfo': 
      return "https://{$this->config['okta_domain']}/oauth2/default/v1/userinfo";
    }
    return null;
  }
  function httpCall($url, $params=false, $headers=[]) {
    $ch = curl_init($url);
    if (count($headers)>0)
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if($params)
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, null, '&'));
    $json = curl_exec($ch);
    return json_decode($json);
  }
  function getUri(string $name):string{
    $domain = str_replace(':443', '', $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName());
    switch($name){
    case 'bohome':
      return $domain
	.TZR_SHARE_ADMIN_PHP
	.'?'
	.http_build_query(['moid'=>Module::getMoid(XMODADMIN_TOID),
			   'template'=>'Core.layout/main.html',
			   'function'=>'portail',
			   'oauthreturn'=>date('ymdHis')
			   ],null,'&');
    Case 'login':
      return "{$domain}/oauth-return.php?id={$this->id}";
    }
    return null;
  }
  protected function getScopes():array{
    return ['profile email'];
  }
  /// avec OKTA on ne doit pas encoder les paramètres
  protected function buildQuery(array $parameters):string {
    $vals = [];
    foreach($parameters as $k=>$v){
      $vals[] = "{$k}={$v}";
    }
    return implode('&', $vals);
  }
  /**
   * 2 appels avec des types différents : à la connexion, lors de la synchronisation
   */
  function formatUserData($item){
    Logs::debug(__METHOD__."\n".var_export($item, true));
    if ($item instanceof  \Okta\Users\User){
      // from synchro via SDK
      return $this->formatFromOktaUser($item);
    } else {
      return $this->formatFromLoginToken($item);
    }
  }
  /**
   * mise en forme à partir des token issus du login
   */
  protected function formatFromLoginToken($item){

    $oktausergroups = $item['accessToken']->groups??null;
    $usergroups = null;
    if (isset($oktausergroups)){
      $usergroups = [];
      $mapping =  $this->config->localusergroup->toArray();
      foreach($mapping as $oktagroup=>$tzrgroup){
	if (in_array($oktagroup, $oktausergroups))
	  $usergroups[] = $tzrgroup;
      }
    }
    $fields = [];
    if (isset($this->config->localfieldsmapping)){
      foreach($this->config->localfieldsmapping->toArray() as $oktaname=>$tzrname){
	if (isset($item['userInfo']->$oktaname))
	  $fields[$tzrname] = $item['userInfo']->$oktaname;
      }
    }
    return array_merge(
      ['oid'=>'USERS:'.md5($this->id.$item['accessToken']->uid),
       'GRP'=>$usergroups,
       'PUBLISH'=>1,
       'DATEF'=>date('Y-m-d'),
       'DATET'=>date('Y-m-d'),
       'BO'=>in_array($this->config->logintype, ['BO', 'both'])?1:2,
       'email'=>$item['userInfo']->email,
       'alias'=>$item['userInfo']->email,
       'fullnam'=>"{$item['userInfo']->family_name} {$item['userInfo']->given_name}", 
      ],
      $fields);
  }
  /**
   * mise en forme pour la synchro d'un User et de ses groupes de l'api okta
   * les user sans groupe (okta ou tzr) sont ignorés : ils n'auraient aucun
   * accès sur la console (serait ? à mettre au niveau général ?)
   */
  protected function formatFromOktaUser($oktauser){
    
    Logs::debug(__METHOD__."\n".var_export($oktauser, true));
    if ($oktauser->status != 'ACTIVE')
      return null;
    
    $usergroups = [];
    $oktausergroups = [];
    foreach($oktauser->getGroups() as $group){
      $oktausergroups[] = $group->getProfile()->getName();
    }
    if (empty($oktausergroups))
      return null;
    if (!empty($oktausergroups)){
      $mapping =  $this->config->localusergroup->toArray();
      foreach($mapping as $oktagroup=>$tzrgroup){
	if (in_array($oktagroup, $oktausergroups))
	  $usergroups[] = $tzrgroup;
      }
    }
    if (empty($usergroups))
      return null;
    
    // les prop. du profile disponibles semblent être :
    // firstName, lastName, mobilePhone, secondEmail, login, email
    $fields = [];
    if (isset($this->config->localsynchrofieldsmapping)){
      foreach($this->config->localsynchrofieldsmapping->toArray() as $oktaname=>$tzrname){
	$value = $oktauser->getProfile()->getProperty($oktaname);
	if (isset($value)){
	  $fields[$tzrname] = $value;
	}
      }
    }
    return array_merge(
      ['oid'=>'USERS:'.md5($this->id.$oktauser->id),
       'GRP'=>$usergroups,
       'PUBLISH'=>1,
       'DATEF'=>date('Y-m-d'),
       'DATET'=>date('Y-m-d'),
       'BO'=>in_array($this->config->logintype, ['BO', 'both'])?1:2,
       'email'=>$oktauser->getProfile()->getEmail(),
       'alias'=>$oktauser->getProfile()->getLogin(),  // à voir que c'est bien identique a formatUserData
       'fullnam'=>"{$oktauser->getProfile()->getLastName()} {$oktauser->getProfile()->getFirstName()}",
      ],
      $fields);
  }
  protected function getSDKClient(){
    return (new \Okta\ClientBuilder())
      ->setToken($this->config->api_token)
      ->setOrganizationUrl("https://{$this->config->okta_domain}")
      ->build();
  }
  protected function getItems():\Iterator{
    
    $client = $this->getSDKClient();
    return   (new \Okta\Okta)->getUsers()->getIterator();
    
  } 
}

