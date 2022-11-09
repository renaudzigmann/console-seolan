<?php
namespace Seolan\Library;
/// authentification par CAS. Pour des raisons de sécurité, la classe est un singleton. Accessible par la fonction getAuthCas
class CasAuthentification {
  private $version;
  private $server;
  private $authurl;
  private $checkurl;
  private $logouturl;
  private $service;
  private $ticket;
  private $user;
  private static $instance;

  public static function getAuthCas($ar=NULL) {
    if (!isset(self::$instance)) {
      $c=__CLASS__;
      self::$instance = new $c($ar);
    }
    return self::$instance;
  }

  public function __clone() {
    return NULL;
  }
  /**
   * pour les ipd xsalto, on n'active pas le cas : connexion admin avec compte xsalto
   */
  public static function active(){
    $active = isset($GLOBALS['CAS_SERVER_URL']) &&  isset($GLOBALS['CAS_SERVER_VERSION']);
    if ($active && isset($GLOBALS['CAS_EXCEPTIONS_ADDRESSES']) && in_array($_SERVER['REMOTE_ADDR'],$GLOBALS['CAS_EXCEPTIONS_ADDRESSES'])){
      $active = false;
      \Seolan\Core\Logs::update('security', null, "Deactivate CAS, CAS exception IP {$_SERVER['REMOTE_ADDR']}");
    }
    return $active;
  }
  private function __construct($ar=NULL){
    if (!isset($ar['version']) && isset($GLOBALS['CAS_SERVER_VERSION']))
      $ar['version'] = $GLOBALS['CAS_SERVER_VERSION'];
    if (!isset($ar['server']) && isset($GLOBALS['CAS_SERVER_URL']))
      $ar['server'] = $GLOBALS['CAS_SERVER_URL'];
    $p=new \Seolan\Core\Param($ar,[]);
    $this->version=$p->get('version');
    if(empty($this->version)){
      echo 'Demande d\'authentification par CAS mais pas de version renseignée';
      die();
    }
    $this->server=$p->get('server');
    if(empty($this->server)){
      echo 'Demande d\'authentification par CAS mais pas de server renseigné';
      die();
    }
    if($this->version>="3.0"){
      $this->authurl=$this->server.'login';
      $this->checkurl=$this->server.'serviceValidate';
      $this->logouturl=$this->server.'logout';
    }

    $next=$p->get('next');
    if(empty($next)){
      $moidadmin=\Seolan\Core\Module\Module::getMoid(XMODADMIN_TOID);
      $next=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(false,false).'&template=Core.layout/main.html&function=portail&moid='.$moidadmin;
    }
    $arurl=parse_url($next);
    if(empty($arurl['host'])){
      if(substr($next,0,1)!='/') $next='/'.$next;
      $protocol=(!empty($_SERVER['HTTPS'])?'https':'http');
      if($_SERVER['SERVER_PORT']!='80')
	$url=$protocol.'://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'];
      else
	$url=$protocol.'://'.$_SERVER['SERVER_NAME'];
      $next=$url.$next;
    }
    $this->service=$next;
    $this->ticket=$_GET['ticket'];
  }

  // Force une authentification. Renvoie true si authentifié et redirige vers la page d'authentification sinon
  function forceAuthentication(){
    $isauth=$this->checkAuth();
    if(!$isauth && !$this->publicDelagatedRequest()){
      $this->redirectToAuth();
    }
    return true;
  }
  /**
   * Détection des pages publiques 'déléguées' à la console
   * -> par exemple : saisie nouveau mot de passe, demande en cas de perte
   */
  function publicDelagatedRequest(){
    // méthodes de la session qui sont accessibles hors connexion au cas à définir localement
    if ((new \ReflectionClass($GLOBALS['TZR_SESSION_MANAGER']))->hasMethod('isDelegablePublicRequest')){
      return $GLOBALS['TZR_SESSION_MANAGER']::isDelegablePublicRequest();
    }
    return false;
  }
  // Verification du ticket. Renvoie true si OK, false sinon
  function checkAuth(){
    if(!empty($this->ticket)){
      debug('check cas authentification has ticket');
      $this->service=preg_replace("#&ticket=.+#",'',$this->service);
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $this->checkurl.'?service='.urlencode($this->service).'&ticket='.$this->ticket);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
      $xml=curl_exec($curl);
      $dom = new \DOMDocument();
      $dom->preserveWhiteSpace=false;
      $dom->validateOnParse = true;
      $dom->loadXML($xml);
      $xpath=new \DOMXpath($dom);
      if($this->version>="3.0"){
        $user=$xpath->query('/cas:serviceResponse/cas:authenticationSuccess/cas:user');
        if(!empty($user->item(0)->textContent)){
          debug('check cas authentification OK');
          $this->user=$user->item(0)->textContent;
          return true;
        }else{
          $error=$xpath->query('/cas:serviceResponse/cas:authenticationFailure');
          $errorcode=$error->item(0)->getAttribute('code');
          $errormessage=$error->item(0)->textContent;
          debug('check cas authentification error : '.$errormessage.' ('.$errorcode.')');
        }
      }
    }
    return false;
  }
  function getTicket(){
    return $this->ticket;
  }
  function getUser(){
    return $this->user;
  }
  // Url d'authentification
  function loginUrl(){
    return $this->authurl.'?service='.urlencode($this->service);
  }
  // Redirige sur la page d'authentification
  function redirectToAuth(){
    debug('redirect to cas authentification');
    header('Location: '.$this->authurl.'?service='.urlencode($this->service));
    die();
  }

  // Affiche un message d'erreur ou redirige vers une page (utilisée pour les authentification cas réussi mais pas de user en base
  function redirectToError(){
    if(isset($GLOBALS['CAS_SERVER_ERROR'])){
      header('Location: '.$GLOBALS['CAS_SERVER_ERROR']);
      die();
    }
    echo \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session','adminauthcaserror');
    die();
  }

  // Se deconnecte du serveur CAS
  function logout(){
    header('Location: '.$this->logouturl);
    die();
  }
}
?>
