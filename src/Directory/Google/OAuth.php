<?php
namespace Seolan\Directory\Google;
/**
 * Google users directory
 */
class OAuth extends \Seolan\Core\Directory\OAuth {
  protected static $userfields = ['email'=>['email','alias'],'name'=>'name','picture'=>'logo'];
  private static $defaultLoginCredentialsTemplate = 'Directory/Google.credentials.html';
  /**
   * Informations for credential input
   */
  public function manageLoginCredentialUI(){
    $scope = $this->config['scope']??'profile email';
    $prompt = $this->config['prompt']??'select_account consent';
    return ['template'=>(isset($this->config['template']))?$this->config['template']:static::$defaultLoginCredentialsTemplate,
	    'client_id'=>$this->config['client_id']
	    ,'button_label'=>'Google'
	    ,'id'=>md5($this->id)
	    ,'hidden_fields'=>['idtoken']
	    ,'scope'=>$scope
	    ,'prompt'=>$prompt
	    ];
  }
  protected function oAuthAuthenticationAdapterFactory($oauth):\Zend\Authentication\Adapter\AdapterInterface {
    return new \Seolan\Core\Directory\Authentication\OAuthAdapter($this, $oauth);
  }
  /**
   * Validate tokenid provide by google backend
   * Retrieve user data according to directory configuration
   * // https://developers.google.com/identity/sign-in/web/backend-auth
   */
  public function oAuthAuthenticate($oauth):?array{
    $id = md5($this->id);
    $client_id  = $this->config['client_id'];
    $client = new \Google_Client(['client_id' => $client_id]);
    $res = $client->verifyIdToken($oauth[$id]['idtoken']);
    if ($res && $res['aud'] == $client_id){
      // may define and check mandatory fields
      return [true, null, $res];
    } else {
      return [false, 'invalid_token_service_unavailable', null];
    }
  }
  /**
   * données minimales pour avoir un user opérationnel
   * l'uid, ici : unique identifier
   */
  protected function formatUserData($item){
    $userd = ['oid'=>'USERS:'.md5($this->id.$item['sub']),
	     'GRP'=>$this->config->localusergroup->toArray(),
	     'BO'=>in_array($this->config['logintype'], ['both','BO'])?'1':'2',
	     'PUBLISH'=>isset($this->config['defaultPublished'])?$this->config['defaultPublished']:'2',
	     'UPD'=>date('Y-m-d'),
             'luser'=>array_search($item['locale'],$GLOBALS['TZR_LANGUAGES'])
      ];

    $fields = $this->getFields();
    foreach($fields as $fbfn=>$fns){
      if (!is_array($fns)) {
        $fns = [$fns];
      }
      foreach ($fns as $fn) {
        $userd[$fn] = $item[$fbfn];
      }
    }
    if(empty($userd['alias'])){
      $userd['alias'] = $userd['email']??$userd['fullnam'];
    }
    return $userd;
  }
}