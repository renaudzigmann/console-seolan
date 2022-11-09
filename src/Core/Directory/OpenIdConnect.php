<?php
namespace Seolan\Core\Directory;
/**
 * classe de base OpenIdConnect 
 * à  voir : synchronisation (OKTA supporte peut-être)
 */
use \Seolan\Core\{DbIni,Param,Logs};

abstract class OpenIdConnect extends \Seolan\Core\Directory\OAuth { 
// ? implements, si on fait de la synchro en arrière plan \Seolan\Core\Directory\UserDirectoryInterface{ 

  protected static $defaultLoginCredentialsTemplate = null;
  /**
   * dans cette version en tout cas on fonctionne avec le OAuthAdapter, pas nécessaure de surcharger
   * OpenIdConnect = OAuth2 
   */
  protected function oAuthAuthenticationAdapterFactory($oauth):\Zend\Authentication\Adapter\AdapterInterface {
    return new \Seolan\Core\Directory\Authentication\OAuthAdapter($this, $oauth);
  }
  /**
   * validate code according to openid spec
   * state value regisered
   * code value valide
   */
  public function oAuthAuthenticate($oauth):?array{
    Logs::debug(__METHOD__."\n".var_export($_REQUEST, true));
    $p = new Param([], ['code'=>null,'state'=>null]);
    if (!$p->is_set('code'))
      throw new \Exception("no code parameter in request ".$_REQUEST['error']??''." ".$_REQUEST['error_description']??'');
    if (!$p->is_set('state'))
      throw new \Exception("no state parameter in request");

    // state est bien connu et mémorisé
    $state = $p->get('state');
    $stateok = DbIni::get(get_class($this).'authorization_state'.$state, 'val');
    if (empty($stateok) || $stateok != $state)
      throw new \Exception("invalid state parameter '{$_REQUEST['state']}'");
    DbIni::clear($p->get('state'));
    
    // contrôle du code / access token
    // https://developer.okta.com/docs/guides/implement-auth-code/exchange-code-token/
    $responseCode = $this->httpCall($this->getEndPoint('token'), [
      'grant_type' => 'authorization_code',
      'code' => $p->get('code'),
      'redirect_uri' => $this->getUri('login'), // must match the URI that was used to get the authorization code
      'client_id' => $this->config['client_id'],
      'client_secret' => $this->config['client_secret'],
    ]);
    if (!isset($responseCode->access_token)){
      if (isset($responseCode->errorCode)){
	throw new \Exception("invalid code, no token received {$responseCode->errorCode} {$responseCode->errorSummary}");
      }elseif(isset($responseCode->error)){
	throw new \Exception("invalid code, no token received {$responseCode->error} {$responseCode->error_description}");
      } else {
	throw new \Exception("invalid code, no token received unknown error");
      }
    }
    // vérification du token @todo : faire une validation locale ?
    // voir https://developer.okta.com/docs/guides/validate-access-tokens/dotnet/overview/#validating-a-token-remotely-with-okta
    // https://developer.okta.com/docs/reference/api/oidc/#request-parameters-3
    $accessTokenDecoded = $this->httpCall($this->getEndPoint('introspection'),
					  [
					    'client_id' => $this->config['client_id'],
					    'client_secret' => $this->config['client_secret'],
					    'token'=>$responseCode->access_token,
					    'token_type_hint'=>'access_token'
					  ]);
    if (!isset($accessTokenDecoded->uid) || !isset($accessTokenDecoded->username)){
      return [false, 'invalid_token_service_unavailable', null];
    }
    $idTokenDecoded = $this->httpCall($this->getEndPoint('introspection'),
			       [
				 'client_id' => $this->config['client_id'],
				 'client_secret' => $this->config['client_secret'],
				 'token'=>$responseCode->id_token,
				 'token_type_hint'=>'id_token'
			       ]);

    $userData = ['accessToken'=>$accessTokenDecoded, 'idToken'=>$idTokenDecoded??null];


    // user info fait partie des endpoints standard OpenIdConnect et retourne l'info en fonction
    // des scopes passés initialement 
    $userData['userInfo'] = $this->httpCall($this->getEndPoint('userinfo'),
					    ['client_id' => $this->config['client_id'],
					     'client_secret' => $this->config['client_secret'],
					    ],
					    [
					      "Content-Type: application/json",
					      "Authorization: Bearer {$responseCode->access_token}",
					    ]);

    Logs::debug(__METHOD__." user infos and token \n".var_export($userData));
    
    return [true, null, $userData];
    
  }
  /**
   * Informations pour la redirection en page de login
   */
  public function manageLoginCredentialUI(){
    $state = uniqid('OIDCSTate');
    DbIni::set(get_class($this).'authorization_state'.$state, $state);
    // https://developer.okta.com/docs/reference/api/oidc/#parameter-details
    // !! tous les serveurs ne gèrent pas &amp; comme séparateur
    $redirect_uri = $this->getUri('login');
    Logs::notice(__METHOD__,$redirect_uri);
    return [
      'authurl'=>$this->getEndPoint('authorization').'?'.
		$this->buildQuery([
		  'client_id'=>$this->config['client_id'],
		  'response_type'=>'code',
		  'scope'=>implode(' ', array_merge(['openid'], $this->getScopes())), 
		  'redirect_uri'=>$redirect_uri,
		  'state'=>$state
		]),
      'template'=>$this->config['template']??static::$defaultLoginCredentialsTemplate,
      'login'=>$this->config['login'],
      'id'=>md5($this->id)
    ];
  }
  /**
   * contruction d'une requete sans encodage (serveur okta uniquement ?)
   */
  protected function buildQuery(array $parameters):string{
    return http_build_query($parameters, null, '&');
  }
  /// liste des scopes requis en retour de l'authentification
  abstract protected function getScopes():array;
  /// liste des points d'entrée : authorization, verif code, etc
  abstract protected function getEndPoint(string $name):string;
  abstract protected function getUri(string $name):string;
  ////////////    DEV abstracts à faire (ou differé) ///////////////////////////
  protected function authenticationAdapterFactory($login, $password):\Zend\Authentication\Adapter\AdapterInterface{
    throw new \Exception("method not implemented");
  }
  public function authenticate($login, $password):?array{
    throw new \Exception("method not implemented for OAuthDirectories");
  }
  protected function getItems():\Iterator{
  }
  //////////////////// 
}
