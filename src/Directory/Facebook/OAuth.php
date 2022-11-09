<?php
namespace Seolan\Directory\Facebook;
/**
 * Facebook connect directory
 */
class OAuth extends \Seolan\Core\Directory\OAuth {
  // available graph fields https://developers.facebook.com/docs/graph-api/reference/user
  protected static $userfields = ['email'=>['email','alias'],'name'=>'fullnam','picture'=>'logo'];
  private static $defaultLoginCredentialsTemplate = 'Directory/Facebook.credentials.html';
  /**
   * Informations for credential input
   */
  public function manageLoginCredentialUI(){
    $scope = $this->config['scope']??['public profile', 'email'];
    return ['template'=>(isset($this->config['template']))?$this->config['template']:static::$defaultLoginCredentialsTemplate
	    ,'button_label'=>'Facebook'
	    ,'id'=>md5($this->id)
	    ,'scope'=>$scope
	    ,'app_id'=>$this->config['app_id']
	    ,'hidden_fields'=>['accesstoken']
	    ];
  }
  protected function oAuthAuthenticationAdapterFactory($oauth):\Zend\Authentication\Adapter\AdapterInterface {
    return new \Seolan\Core\Directory\Authentication\OAuthAdapter($this, $oauth);
  }
  /**
   * Validate tokenid provide by facebook backend
   * Retrieve user data according to directory configuration
   * https://github.com/facebook/php-graph-sdk/blob/5.x/README.md
   * https://developers.facebook.com/docs/graph-api/using-graph-api/#fields
   */
  public function oAuthAuthenticate($oauth):?array{

    $fb = new \Facebook\Facebook(['app_id' => $this->config['app_id']
				 ,'app_secret' => $this->config['app_secret']
				 ,'default_graph_version' => 'v2.2',
				 ]);
    // on laisse fb faire ... (données issues du cookies reçu lors de la réponse login web)
    $helper = $fb->getJavaScriptHelper();
    
    try {
      $accessToken = $helper->getAccessToken();
    } catch(\Facebook\Exceptions\FacebookResponseException $e) {
      // When Graph returns an error
      \Seolan\Core\Logs::critical(__METHOD__, 'Graph returned an error: ' . $e->getMessage());
      return [false, 'invalid_token_service_unavailable', null];
    } catch(\Facebook\Exceptions\FacebookSDKException $e) {
      // When validation fails or other local issues
      \Seolan\Core\Logs::critical(__METHOD__, 'Facebook SDK returned an error: ' . $e->getMessage());
      return [false, 'invalid_token_service_unavailable', null];
    }
    if (! isset($accessToken)) {
      \Seolan\Core\Logs::critical(__METHOD__, 'No cookie set or no OAuth data could be obtained from cookie.' . $e->getMessage());
      return [false, 'invalid_token_service_unavailable', null];
    }

    $fields = $this->getFields();
    $fields['id']='id'; // actualy always returned by API
    $fbfields = implode(',', array_unique(array_keys($fields)));

    try {
      // Returns a `Facebook\FacebookResponse` object
      $response = $fb->get("/me?fields=$fbfields", $accessToken->getValue());
    } catch(Facebook\Exceptions\FacebookResponseException $e) {
      \Seolan\Core\Logs::critical(__METHOD__, 'Graph returned an error: ' . $e->getMessage());
      return [false, 'invalid_token_service_unavailable', null];
    } catch(Facebook\Exceptions\FacebookSDKException $e) {
      \Seolan\Core\Logs::critical(__METHOD__, 'Facebook SDK returned an error: ' . $e->getMessage());
      return [false, 'invalid_token_service_unavailable', null];
    } catch(\Throwable $e){
      \Seolan\Core\Logs::critical(__METHOD__, 'Facebook SDK returned an error: ' . $e->getMessage());
      return [false, 'invalid_token_service_unavailable', null];
    }

    $fbuser = $response->getGraphUser();

    if (empty($fbuser['email'])) {
      return [false, 'mail_is_empty', null];
    }
    return [true, null, $fbuser];

  }
  /**
   * données minimales pour avoir un user opérationnel
   * l'uid, ici : unique identifier
   */
  protected function formatUserData($item){
    $userd = ['oid'=>'USERS:'.md5($this->id.$item['id']),
	     'GRP'=>$this->config->localusergroup->toArray(),
	     'BO'=>in_array($this->config['logintype'], ['both','BO'])?'1':'2',
	     'PUBLISH'=>isset($this->config['defaultPublished'])?$this->config['defaultPublished']:'2',
	     'UPD'=>date('Y-m-d')];

    $fields = $this->getFields();
    foreach($fields as $fbfn=>$fns){
      if (!is_array($fns)) {
        $fns = [$fns];
      }
      foreach ($fns as $fn) {
        if ($item[$fbfn] instanceof \Facebook\GraphNodes\GraphPicture)
          $userd[$fn] = $item[$fbfn]->getUrl();
        else
          $userd[$fn] = $item[$fbfn];
      }
    }

    if(empty($userd['alias'])){
      $userd['alias'] = $userd['email']??$userd['fullnam'];
    }
    return $userd;
  }
}