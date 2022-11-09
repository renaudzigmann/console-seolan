<?php
namespace Seolan\Module\Social;
// 27/08/2012
// FB est en cours de suppression du droit offline_access ce qui empeche la publication via cron.
// En attendant une stabilisation de leur systeme, l'ancien code est gardé mais un nouveau fonctionnement est mis en place.
// Si aucun changement n'est effectué par fb dans les prochaines semaines, l'ancien code pourra etre supprimé
// De plus afin de rester compatible avec l'ancienne version, aucun patch sql n'a été executé pour supprimer les champs inutiles, les vieux comptes etc... et sera donc à faire lors du menage
// Code affecté : propriété FBappUrl, methodes__construct/_actionlist/_daemon/edit/insert/procEdit/procInsert et les templates xmodsocial new/edit/editJS/getFBToken
// 25/01/2013
// Twitter a suivi la même logique avec une identification par token
class Social extends \Seolan\Module\Table\Table{

  /// Id de l'application facebook "Console Séolan"
  public $FBappId='110818382299882';
  /// Clé de l'application facebook "Console Séolan"
  public $FBappKey='92253c859ea4e8e74470b36869ebbb43';
  /// Clé secrète de l'application facebook "Console Séolan"
  public $FBappSecret='818cb6b3f3b0917070671d47336a8c12';
  /// Url appelée par facebook une fois l'utilisateur connecté
  public $FBappUrl="http://www.xsalto.com/tzr/scripts/admin.php?function=index&template=Module/Social.getFBToken.html&labels[]=xmodsocial";

  /// Clé de l'application Twitter "Publish with Console Séolan"
  public $twitter_consumer_key = 'jQGFe5zS3a7F3rObjhWpZQ';
  /// Clé secrète de l'application Twitter "Publish with Console Séolan"
  public $twitter_consumer_secret = 'vKyuwbTQ1nxzLYXklQMk25PW6pAUMa22WkDSc7mavPM';

  public $fshortt='shortt';
  public $flongt='longt';
  public $furl='url';
  public $furldescr='urldescr';
  public $fmedia='media';
  public $fpublishon='publishon';
  
  function __construct($ar=NULL){
    parent::__construct($ar);
    /* Voir commentaire au debut de la classe */
    if(@$_REQUEST['template']=='Module/Table.edit.html') $_REQUEST['template']='Module/Social.edit.html';
    if(@$_REQUEST['template']=='Module/Table.new.html') $_REQUEST['template']='Module/Social.new.html';
  }
 
  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['publishOnTwitter']=array('rwv','admin');
    $g['addTwitterAccount']=array('rwv','admin');
    $g['procAddTwitterAccount']=array('rwv','admin');
    $g['addFacebookAccount']=array('rwv','admin');
    $g['procAddFacebookAccount']=array('rwv','admin');
    $g['publishOnFacebook']=array('rwv','admin');
    $g['FBConnect']=array('none');
    $g['TwitterConnect']=array('none');
    $g['callbackTwitterToken']=array('none');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Social_Social','shortt'),'fshortt','field',array('table'=>'table','compulsory'=>true,'type'=>'\Seolan\Field\ShortText\ShortText'),
			    NULL);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Social_Social','longt'),'flongt','field',array('table'=>'table','compulsory'=>true,'type'=>array('\Seolan\Field\RichText\RichText','\Seolan\Field\Text\Text')),
			    NULL);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Social_Social','publishon'),'fpublishon','field',array('table'=>'table','compulsory'=>true,'type'=>'\Seolan\Field\Date\Date'),
			    NULL);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Social_Social','media'),'fmedia','field',array('table'=>'table','compulsory'=>false,'type'=>'\Seolan\Field\Image\Image'),
			    NULL);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Social_Social','file'),'ffile','field',array('table'=>'table','compulsory'=>false,'type'=>'\Seolan\Field\File\File'),
			    NULL);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Social_Social','url'),'furl','field',array('table'=>'table','compulsory'=>false,'type'=>'\Seolan\Field\Url\Url'),
			    NULL);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Social_Social','urldescr'),'furldescr','field',array('table'=>'table','compulsory'=>false,'type'=>'\Seolan\Field\Text\Text'),
			    NULL);
  }

  /**
   * URL appelée par Twitter afin d'établir la connexion
   */
  function getTwitterCallbackUrl() {
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self(false, true).'moid='.$this->_moid.'&function=callbackTwitterToken&template=Module/Social.getTwitterToken.html';
  }

  /**
   * Récupération des token de Twitter
   */
  function TwitterConnect() {
    \Seolan\Core\System::loadVendor('twitteroauth/twitteroauth.php');

    unset($_SESSION['access_token']);
    // Créer une connexion avec Twitter
    $connection = new \TwitterOAuth($this->twitter_consumer_key, $this->twitter_consumer_secret);
    // Url rappelée par Twitter pour enregistrer le token
    $callback_url = $this->getTwitterCallbackUrl();
    // On demande les tokens à Twitter, et on passe notre url de callback 
    $request_token = $connection->getRequestToken($callback_url);
    // On sauvegarde ces informations en session 
    $_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
    $_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
    // On vérifie que notre requête précédente a correctement fonctionné 
    switch ($connection->http_code) {
      case 200 :
        // On construit l'URL de callback avec les tokens en paramètres 
        $callback_url_with_token = $connection->getAuthorizeURL($token);
        header('Location: '.$callback_url_with_token);
        break;
      default :
        die('TwitterConnect failed');
    } 
  }

  /**
   * Fonction appelée par Twitter une fois l'utilisateur connecté
   */
  function callbackTwitterToken() {
    \Seolan\Core\System::loadVendor('twitteroauth/twitteroauth.php');

    $isLoggedOnTwitter = false;
    if (!empty($_SESSION['access_token']) && !empty($_SESSION['access_token']['oauth_token']) && !empty($_SESSION['access_token']['oauth_token_secret'])) {
      // On récupère les tokens, nous sommes identifiés.
      $access_token = $_SESSION['access_token'];
      $isLoggedOnTwitter = true;
      $connection = new \TwitterOAuth($this->twitter_consumer_key, $this->twitter_consumer_secret, $access_token['oauth_token'], $access_token['oauth_token_secret']);
      $twitterInfos = $connection->get('account/verify_credentials');
    } elseif (isset($_REQUEST['oauth_token']) && $_SESSION['oauth_token'] === $_REQUEST['oauth_token']) {
      // Les tokens d'accès ne sont pas encore stockés, il faut vérifier l'authentification
      $connection = new \TwitterOAuth($this->twitter_consumer_key, $this->twitter_consumer_secret, $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
      $access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);	
      $_SESSION['access_token'] = $access_token;
      unset($_SESSION['oauth_token']);
      unset($_SESSION['oauth_token_secret']);
      if (200 == $connection->http_code) {
        $twitterInfos = $connection->get('account/verify_credentials');
        $isLoggedOnTwitter = true;
      }
    }
    $twitter = array(
      'isLoggedOn' => $isLoggedOnTwitter,
      'infos' => $twitterInfos);
    \Seolan\Core\Shell::toScreen1('twitter', $twitter);
  }

  /** 
   * Publie une info sur twitter (nécessite l'acquisition d'un token de connexion)
   * @see #TwitterConnect #callbackTwitterToken
   * @return array() procEdit
   */
  function publishOnTwitter($ar=NULL){
    \Seolan\Core\System::loadVendor('twitteroauth/twitteroauth.php');
    $p = new \Seolan\Core\Param($ar);
    // Test si un compte twitter a été sélectionné
    $twitteraccount = $p->get('twitteraccount');
    if (empty($twitteraccount)) return false;
    // Récupération des infos de la fiche éditée
    $oid = $p->get('oid');
    $d = $this->display(array('oid'=>$oid));
    // Récupération du message à envoyer
    $p = new \Seolan\Core\Param($ar, array('message' => substr($d['o'.$this->fshortt]->text, 0, 140)));
    $message = $p->get('message');
    $edit = array(
      'oid' => $oid,
      'options' => array('twitterstate' => array('toxml' => 1)));
    try {
      // Connexion twitter
      $access_token = $_SESSION['access_token'];
      $connection = new \TwitterOAuth($this->twitter_consumer_key, $this->twitter_consumer_secret, $access_token['oauth_token'], $access_token['oauth_token_secret']);
      $twitterInfos = $connection->get('account/verify_credentials'); 
      if (200 != $connection->http_code) {
        throw new \Exception('HTTP Error '.$connection->http_code);
      }
      // Envoi du message à twitter
      $parameters = array('status' => $message);
      $status = $connection->post('statuses/update', $parameters);
      if (!empty($status->error)) {
        throw new \Exception('Twitter error: '.$status->error);
      }
      // Le tweet a bien été publié
      $edit['twitterok'] = 1;
      $edit['twitterstate'] = array(
        'id' => $status->id,
	'created_at' => $status->created_at);
      \Seolan\Core\Logs::notice('publishOnTwitter','Twitter status ['.$status->id.'] updated at '.$status->created_at.' for '.$twitterInfos->screen_name.' [KOID='.$oid.']');
    } catch(\Exception $e) {
      $edit['twitterok'] = 0;
      $edit['twitterstate'] = array('error' => $e->getMessage());
      \Seolan\Core\Logs::critical('publishOnTwitter', $e->getMessage());
    }
    $edit['_options']['local']=true;
    return $this->xset->procEdit($edit);
  }

  /// Prépare l'ajout d'un compte Twitter
  function addTwitterAccount($ar=NULL){
    $ar['selectedfields']=array('login','passwd','name','descr');
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_ACCOUNTS');
    $x->desc['login']->compulsory=$x->desc['password']->compulsory=true;
    return $x->input($ar);
  }

  /// Ajout d'un compte Twitter
  function procAddTwitterAccount($ar=NULL){
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_ACCOUNTS');
    $ar['atype']='Twitter';
    $ar['modid']=$this->_moid;
    $x->procInput($ar);
    setSessionVar('message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Social_Social','addaccountok','text'));
  }

  /// Publie une info sur Facebook
  function publishOnFacebook($ar=NULL){
    \Seolan\Core\System::loadVendor('facebook/facebook.php');
    $p = new \Seolan\Core\Param($ar);
    $oid = $p->get('oid');
    $d = $this->display(array('oid' => $oid, 'tplentry' => TZR_RETURN_DATA));
    $p = new \Seolan\Core\Param($ar, array(
      'message' => $d['o'.$this->flongt]->text,
      'shortt' => $d['o'.$this->fshortt]->text,
      'longt' => $d['o'.$this->flongt]->text,
      'photo_name' => $d['o'.$this->fmedia]->title,
      'photo_url' => !empty($d['o'.$this->fmedia]->filename) && $d['o'.$this->fmedia]->isImage ? 
        $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().'/'.$GLOBALS['DATA_URL'].'/'.$d['o'.$this->fmedia]->shortfilename : null,
      'link_name' => $d['o'.$this->furl]->title,
      'link_url' => $d['o'.$this->furl]->url,
      'file_name' => empty($d['o'.$this->ffile]->title) ? $d['o'.$this->ffile]->originalname : $d['o'.$this->ffile]->title,
      'file_url' => $d['o'.$this->ffile]->url ? 
        $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().$d['o'.$this->ffile]->url : null,
      'urldescr' => $d['o'.$this->furldescr]->text
    ));
    $message = $p->get('message');
    $shortt = $p->get('shortt');
    $longt = $p->get('longt');
    $photo_name = $p->get('photo_name');
    $photo_url = $p->get('photo_url');
    $link_name = $p->get('link_name');
    $link_url = $p->get('link_url');
    $file_name = $p->get('file_name');
    $file_url = $p->get('file_url');
    $urldescr = $p->get('urldescr');

    $u = array();
    $u['login'] = $p->get('fbaccount');
    $u['token'] = $p->get('fbtoken');
    if (!$u['login'] || !$u['token'] || $d['fbok']->raw == 1) return false;

    $ar2 = array('oid' => $oid, 'options' => array('fbstate' => array('toxml' => 1)));
    $facebook = new \Facebook(array('appId' => $this->FBappId,'secret' => $this->FBappSecret,'cookie' => true));
    $facebook->setAccessToken($u['token']);
    try {
      if (empty($longt) && empty($link_url)) {
	// Photo seule
	if ($photo_url) {
	  $ret = $facebook->api('/'.$u['login'].'/photos','POST',array(
            'name' => $photo_name,
            'url'  => $photo_url));
	  $ret['type'] = 'photo';
	}
      } else {
	// Statut et/ou lien et/ou image
	$fb = array('message' => $message);
	if ($link_url) {
	  $fb['link'] = $link_url;
	  $fb['name'] = $link_name;
	} elseif ($file_url) {
	  $fb['link'] = $file_url;
	  $fb['name'] = $file_name;
	}
	if ($photo_url) {
	  $fb['picture'] = $photo_url;
	}
	if ($urldescr) $fb['description'] = $urldescr;
	$ret = $facebook->api('/'.$u['login'].'/feed','POST',$fb);
	$ret['type'] = 'stream';
      }
      if (!empty($ret)) {
        $ar2['fbok'] = 1;
        $ar2['fbstate'] = $ret;
      } else {
        $ar2['fbok'] = 1;
        $ar2['fbstate'] = array('error' => 'All FB fields are empty');
      }
      \Seolan\Core\Logs::notice('publishOnFacebook', 'POST return: '.var_export($ret, true));
    } catch (\Exception $e) {
      $ar2['fbok'] = 0;
      $ar2['fbstate'] = array('error' => $e->getCode().' : '.$e->getMessage());
      \Seolan\Core\Logs::critical('publishOnFacebook', $e->getMessage());
    }
    $ar2['_options']['local'] = true;
    $this->xset->procEdit($ar2);
    return $ret;
  }

  /// Prépare l'ajout d'un compte/page Facebook
  function addFacebookAccount($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $ispage=$p->get('_ispage');
    $tplentry=$p->get('tplentry');
    $ar['selectedfields']=array('name','descr');
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_ACCOUNTS');
    $r=$x->input($ar);
    $r['_ispage']=$ispage;
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  /// Ajout d'un compte/page Facebook
  function procAddFacebookAccount($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_ACCOUNTS');
    $ar['atype']='Facebook';
    $ar['modid']=$this->_moid;
    $ar['passwd']=$p->get('token');
    $x->procInput($ar);
    setSessionVar('message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Social_Social','addaccountok','text'));
  }

  function FBConnect($ar=NULL){
  }

  /* Fonctions enrichies */
  /* Voir commentaire au debut de la classe */
  function edit($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $tplentry=$p->get('tplentry');
    $ret=parent::edit($ar);
    $actions=array(array('name'=>'publishOnSocial','label'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Social_Social','saveandpublishonsocial'),'action'=>'return v'.\Seolan\Core\Shell::uniqid().'.publishOnSocial();'));
    $ret['_actions']=$actions;
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  function procEdit($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $publishOnSocial=$p->get('publishOnSocial');
    $ret=parent::procEdit($ar);
    if($publishOnSocial){
      $this->publishOnFacebook($ar);
      $this->publishOnTwitter($ar);
    }
    return $ret;
  }
  function insert($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $tplentry=$p->get('tplentry');
    $ret=parent::insert($ar);
    $actions=array(array('name'=>'publishOnSocial','label'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Social_Social','saveandpublishonsocial'),'action'=>'return v'.\Seolan\Core\Shell::uniqid().'.publishOnSocial();'));
    $ret['_actions']=$actions;
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  function procInsert($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $publishOnSocial=$p->get('publishOnSocial');
    $ret=parent::procInsert($ar);
    if($publishOnSocial && $ret['oid']){
      $ar['oid']=$ret['oid'];
      $this->publishOnFacebook($ar);
      $this->publishOnTwitter($ar);
    }
    return $ret;
  }
}
?>
