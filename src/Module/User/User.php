<?php
namespace Seolan\Module\User;
use Seolan\Core\Labels;
use Seolan\Core\Module\Module;

class User extends \Seolan\Module\User\AbstractUser implements \Seolan\Core\Directory\UserDirectoryInterface {
  public $lost_password=true;
  public $account_request=false;
  public $choose_lang_on_login = true;
  public $sendccount_request_to_email=NULL;
  public $passwordshistorysize = 12;
  public $passwordexpiration = 60;
  public $remoteauthenticationactive = 0;
  public $passwordEmailSender;
  public const PASSWORDS_LIST_OPT = 'passwordslist';
  public const PASSWORD_EXPIRE_OPT = 'passwordupdate';
  protected static $accountMailTemplate = 'Module/User.accountMailTemplate.html';
  public static $singleton = true;
  public $userselectortreeviewmode = false;
  public $userselectortreeviewgroup1=null;
  public $userselectortreeviewgroup2=null;
  public $userselectortreeviewgroup3=null;
  static public $upgrades = ['20200127'=>''];
  private $_condactiveuser = null;
  function __construct($ar=NULL) {
    $ar['moid']=self::getMoid(XMODUSER2_TOID);
    parent::__construct($ar);
    if(!empty($GLOBALS['XUSER']) && !empty($this->xset->desc['BO']) && empty($this->fieldssec['BO'])){
      $rwv=$this->secure('',':rwv');
      if(!$rwv) $this->fieldssec['BO']='ro';
    }
    \Seolan\Core\Labels::loadLabels('Seolan_Module_User_User');
    if(!$this->group){
      $this->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    }
    if(!$this->getLabel()){
      $this->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User',"modulename","text");
    }
    if(!$this->xset->fieldExists('PUBLISH')) $this->account_request=false;
    if (empty($this->passwordEmailSender)) {
      $this->passwordEmailSender = $this->getSenderWithName()[0];
    }
  }

  /// Initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','modulename');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','composed_fullnam'),'composed_fullnam','text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','lost_password'),'lost_password','boolean',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','account_request'),'account_request','boolean',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','choose_lang_on_login'),'choose_lang_on_login','boolean',NULL,NULL,$alabel);
    $this->_options->setComment(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','account_request_comment'),'account_request');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','send_account_request_to_email'),'send_account_request_to_email','text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','passwords_history_size'),'passwordshistorysize','text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','password_expiration'),'passwordexpiration','text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','remoteauthenticationactive'),'remoteauthenticationactive','boolean',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','requestaccountdefaultgroup'),'requestaccountdefaultgroup','object',['table'=>'GRP'],NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','password_email_sender'),'passwordEmailSender','text',[],'',$alabel);

    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','userselectortreeviewmode'),'userselectortreeviewmode','boolean',['default'=>false],'',$alabel);
    if(!empty(static::$_mcache[$this->_moid]['MPARAM']['userselectortreeviewmode'])
    && !empty(static::$_mcache[$this->_moid]['MPARAM']['table'])){
      $table = static::$_mcache[$this->_moid]['MPARAM']['table'];
      $usglabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'treeviewgroupfield');
      for($i=1; $i<=3; $i++){
	$this->_options->setOpt(sprintf($usglabel, $i),"treeviewgroup{$i}", 'field',
				['compulsory'=>0,
				 'table'=>static::$_mcache[$this->_moid]['MPARAM']['table']],
				null, $alabel);
      }
    }
    
    $rgpdgroup=\Seolan\Core\Labels::getSysLabel('Seolan_Core_RGPD','RGPD','text');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_RGPD','identity'), 'RGPD_identity', 'boolean', null, true, $rgpdgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_RGPD','personaldata'), 'RGPD_personalData', 'boolean', null, true, $rgpdgroup);
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array(
	     'setbackuid'=>array('none','admin'),
	     'editPref'=>array('admin'),
	     'setuid'=>array('admin'),
	     'getBookmarks'=>array('none','admin'),
	     'getBookmark'=>array('none','admin'),
	     'insertBookmark'=>array('none','admin'),
	     'procInsertBookmark'=>array('none','admin'),
	     'editBookmark'=>array('none','admin'),
	     'delBookmark'=>array('none','admin'),
	     'procEditBookmark'=>array('none','admin'),
	     'procEditPref'=>array('admin'),
         'sendPasswords'=>array('rw','rwv','admin'),
	     'browseSelection'=>array('none','list','ro','rw','rwv','admin'),
	     'myAccount'=>array('none','list','ro','rw','rwv','admin'),
	     'procEditMyAccount'=>array('none','list','ro','rw','rwv','admin'),
	     'getPreferences'=>array('none','list','ro','rw','rwv','admin'),
	     'emptySelection'=>array('none','list','ro','rw','rwv','admin'),
	     'refreshSelection'=>array('none','list','ro','rw','rwv','admin'),
	     'requestAnAccount'=>array('none'),
	     'procRequestAnAccount'=>array('none'),
	     'procRequestAnAccount2'=>['none'],
	     'newPasswordRequest2'=>['none'],
	     'manageLogin'=>['none'],
	     'ajaxProcRequestAnAccount2Ctrl'=>['none','list','ro','rw','rwv','admin'],
	     'procNewPasswordRequest2'=>['none'],
	     'remoteAuthentication'=>array('none','list','ro','rw','rwv','admin'),
	     'directorySynchronization'=>['admin'],
	     'getMyDevices'=>['ro','rw','rwv','admin'],
         'synchroRC'=>['admin'],
         'rocketchatLogin'=>['none','list','ro','rw','rwv','admin'],
         'rocketchatLogout'=>['none','list','ro','rw','rwv','admin'],
         'RCcreateDM'=>['none','list','ro','rw','rwv','admin'],
         'checkUnreadMsg'=>['none','list','ro','rw','rwv','admin'],
         'checkConvExist'=>['none','list','ro','rw','rwv','admin'],
    );
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Edition des propriétés du module
  function procEditProperties($ar=NULL){
    $ret=parent::procEditProperties($ar);

    if($this->account_request && !$this->xset->fieldExists('PUBLISH')){
      $this->xset->createField('PUBLISH','Actif','\Seolan\Field\Boolean\Boolean','0','1','0','1','0','1','0','0');
      getDB()->execute('update USERS set UPD=UPD,PUBLISH=1');
    }

    $this->remoteAuthenticationConfig();

    return $ret;
  }
  /**
   * Rend la liste des fonctions utilisables dans le gestionnaire de rubriques en mode fonction
   * Ajout des fonctions 'demande de compte' et login aux fonctions standard des ensembles de fiches
   */
  function getUIFunctionList() {
    return parent::getUIFunctionList() + ['requestAnAccount'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User', 'account_request'),
					  'manageLogin'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User', 'manage_login')];
  }

  /**
   * Create or update group and user for remote authentication according to property
   */
  protected function remoteAuthenticationConfig(){
    // groupe et compte pour les authentifications distantes
    if ($this->remoteauthenticationactive){
      $remotegrp = getDB()->fetchOne('select 1 from GRP where KOID=?', [TZR_GROUPID_REMOTEUSE]);
      if (!$remotegrp){
	$dsgrp = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('GRP');
	$dsgrp->procInput(['_options'=>['local'=>1],
			   'newoid'=>TZR_GROUPID_REMOTEUSE,
			   'GRP'=>'System : utilisation distante autorisée',
			   'DESCR'=>'Groupe des utilisateur autorisés à utiliser leur compte sur cette console pour se connecter depuis d\'autres consoles.']);
      }
    }
  }
  /**
   * section fonction page de login
   */
  function UIParam_manageLogin(){

    $myLabel = function($name){
      return \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_User_UserSectionFunction', $name);
    };

    $grpmails = 'Mails';
    $grplogged = $myLabel('mngtlgn_grplogged');
    
    $replacementTags = $myLabel('mngtlgn_replacementtags');

    $fn = 'requestanaccountalias';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								   'COMPULSORY'=>0,
								   'FCOUNT'=>64,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);
    $fn = 'requestanaccountlabel';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>70,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);

    $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','account_request');

    $fn = 'nextalias';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								   'COMPULSORY'=>0,
								   'FCOUNT'=>64,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);

    $fn = 'closeaccountalias';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								   'COMPULSORY'=>0,
								   'FCOUNT'=>64,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);
    $fn = 'closeaccountlabel';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>64,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);

    $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','account_close_request');

    $fn = 'lostpasswordlabel';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>64,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);

    $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','lost_password');

    $fn = 'passwordfillinmessage';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>70,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);

    $ret["__$fn"]->defaultText = $GLOBALS['XSHELL']->labels->getSysLabel('Seolan_Core_SessionMessages', 'fillin_password_please');

    $fn = 'showpassword';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
								   'COMPULSORY'=>0,
								   'FCOUNT'=>64,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);

    $fn = 'passwordstrengthhelp';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>70,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);

    $ret["__$fn"]->defaultText = $myLabel("mngtlgn_field_{$fn}_default");
  
    $fn = 'labelbtnlogin';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
                                                                   'FTYPE'=>'\Seolan\Field\Label\Label',
                                                                   'COMPULSORY'=>1,
                                                                   'FCOUNT'=>70,
                                                                   'TRANSLATABLE'=>true,
                                                                   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);
  
    $ret["__$fn"]->defaultText = $myLabel("mngtlgn_field_{$fn}_default");
    
    $fn = 'labelbtnpassword';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
                                                                   'FTYPE'=>'\Seolan\Field\Label\Label',
                                                                   'COMPULSORY'=>1,
                                                                   'FCOUNT'=>70,
                                                                   'TRANSLATABLE'=>true,
                                                                   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);
  
    $ret["__$fn"]->defaultText = $myLabel("mngtlgn_field_{$fn}_default");

    $fn = "emailadmin";
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>250,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);
    $ret["__$fn"]->comment = $myLabel("mngtlgn_field_{$fn}_comment");
    $ret["__$fn"]->fgroup =$grpmails;

    $fn = "mailtemplate";
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>64,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);
    $ret["__$fn"]->comment = $myLabel("mngtlgn_field_{$fn}_comment");
    $ret["__$fn"]->default = $this->mailLayoutTemplate;
    $ret["__$fn"]->fgroup =$grpmails;

    $fn = "mailalias"; // alias de la page de fond de mail
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>64,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);
    $ret["__$fn"]->comment = $myLabel("mngtlgn_field_{$fn}_comment");
    $ret["__$fn"]->default = $this->mailLayoutAlias;
    $ret["__$fn"]->fgroup =$grpmails;

    $fn = "mailpasswdsubject";
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>70,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);

    $ret["__$fn"]->comment = $myLabel("mngtlgn_field_{$fn}_comment");
    $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_UserMessages','account_password_mailsubject');
    $ret["__$fn"]->fgroup =$grpmails;
    $ret["__$fn"]->replacementTags = $replacementTags;

    $fn = "mailpasswdbody";
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>70,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);

    $ret["__$fn"]->comment = $myLabel("mngtlgn_field_{$fn}_comment");
    $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_UserMessages','account_password_mailbody');
    $ret["__$fn"]->fgroup =$grpmails;

    $fn = "mailnewpasswdbody";
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>70,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);
    $ret["__$fn"]->replacementTags = $replacementTags;

    $ret["__$fn"]->comment = $myLabel("mngtlgn_field_{$fn}_comment");
    $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_UserMessages','account_forgot_password_mailbody');
    $ret["__$fn"]->fgroup =$grpmails;

    $fn = "mailpasswdsign";
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>70,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);
    $ret["__$fn"]->replacementTags = $replacementTags;

    $ret["__$fn"]->comment = $myLabel("mngtlgn_field_{$fn}_comment");
    $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_UserMessages','account_password_mailsign');
    $ret["__$fn"]->fgroup =$grpmails;
    $ret["__$fn"]->replacementTags = $replacementTags;

    // page quand connecté
    $fn = 'logoutlabel';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>70,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);

    $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','logout', 'text');
    $ret["__$fn"]->fgroup =$grplogged;

    // page après déconnexion (défault : connexion)
    $fn = 'disconnectnextalias';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								   'COMPULSORY'=>0,
								   'FCOUNT'=>64,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);
    $ret["__$fn"]->fgroup =$grplogged;
    
    // champs
    $fn = 'loggedfields';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								    'FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField',
								    'MULTIVALUED'=>1,
								    'TARGET'=>$this->table,
								    'LABEL'=>$myLabel("mngtlgn_field_{$fn}")]);

    $ret["__$fn"]->doublebox=true;
    $ret["__$fn"]->fgroup = $grplogged;

    // callback pour filtre des champs séléctionnables (
    $fieldsFilter2 = ['field'=>[function($v,$prop,$vals){
	  return in_array($v->$prop, $vals);
	}, []]];
    $protected = $this->requestAccountProtectedFields(false);
    foreach($this->xset->desc as $dfn=>$fd){
      if (in_array($dfn, $protected))
	continue;
      $fieldsFilter2['field'][1][] =$dfn;
    }
    $ret["__$fn"]->__options = ['filter'=>$fieldsFilter2];

    $ret["__$fn"]->default = '||'.implode('||', ['alias','fullnam','email']).'||';

    // plus tard, voir si bouton "rester connecté" ?

    return $ret;
  }
  /**
   * formulaire de demande de compte (section fonction)
   * $ar :
   * oidit, _options, tplentry,
   * requestanaccountalias, requestanaccountlabel, nextalias, lostpasswordlabel, passwordfillinmessage,
   * showpassword,
   * emailadmin, mailtemplate, mailpasswdsubject, mailpasswdbody, mailnewpasswdbody, mailpasswdsign,
   * logoutlabel, displayfields
   */
  function manageLogin($ar){

    $p = new \Seolan\Core\Param($ar, $_REQUEST); // sections fonctions !
    if (!\Seolan\Core\User::isNobody()){
      $ret = ['mode'=>'logged', 'ok'=>'true', 'message'=>null];
      $ret['du'] = $this->xset->display(['_options'=>['local'=>1],
					 'oid'=>\Seolan\Core\User::get_current_user_uid(),
					 'selectedfields'=>$ar['displayfields']
					 ]);
    } else {
      \Seolan\Core\Labels::loadLabels('Seolan_Core_Session');
      $ret = ['mode'=>'login','ok'=>true, 'message'=>null];
      if ($p->is_set('id')){
	$id = $p->get('id');
	$ret['mode'] = 'token';
	list($mess, $token) = $GLOBALS['TZR_SESSION_MANAGER']::getToken($id);

	if ($token == null){
	  $ret['ok'] = false;
	  $ret['message'] = $GLOBALS['XSHELL']->labels->getSysLabel('Seolan_Module_User_UserMessages', 'lost_password_noid');
	} else {
	  $labels = $GLOBALS['XSHELL']->labels;
	  $ret['token'] = $token;
	  $ret['message'] = $ar['passwordfillinmessage'];
	  $ret['pswstrength'] = [];

    $field = \Seolan\Core\Field\Field::objectFactory2('USERS', 'passwd');
    $ret['field_passwd'] = $field->edit("");

	  foreach(['weak','normal','medium','strong','verystrong'] as $q){
	    $ret['pswstrength'][$q] = $labels->getSysLabel('Seolan_Module_User_UserMessages', 'pswstrength_'.$q);
	  }
	}
      }
    }
    return $ret;
  }
  /**
   * Section fonction demande de compte
   * Certains champs sont "totalement interdits"
   */
  function UIParam_requestAnAccount(){

    $myLabel = function($name){
      return \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_User_UserSectionFunction', $name);
    };

    $groupe1 = $myLabel('rqacnt_fieldgroup1');
    $groupe2 = $myLabel('rqacnt_fieldgroup2');
    $groupe3 = $myLabel('rqacnt_fieldgroup3');
    $groupe4 = $myLabel('rqacnt_fieldgroup4');
    $groupe5 = $myLabel('rqacnt_fieldgroup5');

    // champs
    $fn = 'selectedfields';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField',
								   'MULTIVALUED'=>1,
								   'TARGET'=>$this->table,
								   'LABEL'=>$myLabel("rqacnt_field_$fn")]);

    $ret["__$fn"]->doublebox=true;
    $ret["__$fn"]->fgroup = $groupe1;
    
    // callback pour filtre des champs sélectionnables (
    $fieldsFilter = ['field'=>[function($v,$prop,$vals){
	return in_array($v->$prop, $vals);
      }, []]];

    $protected = $this->requestAccountProtectedFields(false);
    foreach($this->xset->desc as $pfn=>$fd){
      if (in_array($pfn, $protected))
	continue;
      $fieldsFilter['field'][1][] =$pfn;
    }
    $ret["__$fn"]->__options = ['filter'=>$fieldsFilter];

    $ret["__$fn"]->default = '||'.implode('||', ['alias','fullnam','email']).'||';
    
    // options d'initialisation des comptes
    // groupes du user
    $groups = [TZR_GROUPID_NOBODY,TZR_GROUPID_BACKOFFICE,TZR_GROUPID_ROOT,TZR_GROUPID_AUTH,TZR_GROUPID_REMOTEUSE];

    $fn = 'grps';
    $ret["__$fn"] = \Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								      'FTYPE'=>'\Seolan\Field\Link\Link',
								      'MULTIVALUED'=>1,
								      'TARGET'=>'GRP',
								     'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->fgroup = $groupe2;
    $ret["__$fn"]->comment = $myLabel("rqacnt_field_$fn_comment");
    if ($this->secure('', ':admin')){
      $ret["__$fn"]->filter = '(GRP.KOID not in("'.implode('","',$groups).'"))';
    } else {
      $ret["__$fn"]->filter = '(GRP.KOID not in("'.implode('","',$groups).'") and KOID="'.$this->requestaccountdefaultgroup.'")';
    }

    // valeurs par defaut pour initialiser les autres champs
    $fn = 'BO';
    if ($this->xset->fieldExists($fn)){
      $fd = $this->xset->getField($fn);
      $fk = "__{$fn}_val";
      $ret[$fk] = \Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>$fk,
								   'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
								   'MULTIVALUED'=>0,
								   'COMPULSORY'=>0,
								   'TARGET'=>null,
								   'LABEL'=>$myLabel("rqacnt_field_{$fn}_val")." \"{$fd->label}\""]);
      $ret[$fk]->default = 1;
      $ret[$fk]->fgroup =$groupe2;
    }
    // valeurs par defaut pour initialiser les autres champs
    $fn = 'PUBLISH';
    if ($this->xset->fieldExists($fn)){
      $fd = $this->xset->getField($fn);
      $fk = "__{$fn}_val";
      $ret[$fk] = \Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>$fk,
								   'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
								   'MULTIVALUED'=>0,
								   'COMPULSORY'=>0,
								   'TARGET'=>null,
								   'LABEL'=>$myLabel("rqacnt_field_{$fn}_val")." \"{$fd->label}\""]);
      $ret[$fk]->default = 2;
      $ret[$fk]->comment = $myLabel("rqacnt_field_{$fn}_val_comment");
      $ret[$fk]->fgroup =$groupe2;
    }
    $fn = 'accountduration';
    if ($this->xset->fieldExists('DATET')){
      $fk = "__{$fn}_val";
      $ret[$fk] = \Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>$fk,
								   'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								   'MULTIVALUED'=>0,
								   'COMPULSORY'=>0,
								   'TARGET'=>null,
								   'LABEL'=>$myLabel("rqacnt_field_{$fn}_val")]);
      $ret[$fk]->comment = $myLabel("rqacnt_field_{$fn}_val_comment");
      $ret[$fk]->default = 3650;
      $ret[$fk]->fgroup =$groupe2;
    }

    $fn = 'aliasfield';
    // champ alias (si le champ n'est pas saisi par l'utilisateur)
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
									  'FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField',
									  'MULTIVALUED'=>0,
									  'COMPULSORY'=>0,
									  'TARGET'=>$this->table,
									  'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment");
    $ret["__$fn"]->doublebox=0;
    $ret["__$fn"]->fgroup = $groupe2;
    $fieldsFilter2 = ['field'=>[function($v,$prop,$vals){
	  return in_array($v->$prop, $vals);
	}, []]];
    foreach($this->xset->desc as $dfn=>$fd){
      if ($dfn == 'alias' || in_array($dfn, $protected) || !is_a($fd,\Seolan\Field\ShortText\ShortText::class))
	continue;
      $fieldsFilter2['field'][1][] =$dfn;
    }
    $ret["__$fn"]->__options = ['filter'=>$fieldsFilter2];

    // bouton valider
    $fn = 'labelvalidate';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
									     'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
									     'COMPULSORY'=>0,
									     'TRANSLATABLE'=>true,
									     'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->listbox=false;
    $ret["__$fn"]->fgroup =$groupe3;

    // alias suite
    $fn = 'nextalias';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
									 'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
									 'COMPULSORY'=>0,
									 'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment");
    $ret["__$fn"]->listbox=false;
    $ret["__$fn"]->fgroup =$groupe3;

    // notifications / emails
    // alias de la page de login/saisie mot de passe
    $fn = 'loginalias';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
									  'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
									  'COMPULSORY'=>0,
									  'FCOUNT'=>64,
									  'TRANSLATABLE'=>true,
									  'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment");
    $ret["__$fn"]->fgroup =$groupe4;

    // email des personnes à notifier lors de l'enregistrement d'une demande (xx@tuc.com<Mr dupont>; ....)
    $fn = "emailadmin";
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								   'COMPULSORY'=>0,
								   'FCOUNT'=>250,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment");
    $ret["__$fn"]->fgroup =$groupe4;

    // sujet du mail d'initialisation de compte
    $fn = "emailsubject";
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>0,
								   'FCOUNT'=>70,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment");
    $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_UserMessages','account_request_mailsubject');
    $ret["__$fn"]->fgroup =$groupe4;

    // coprs du mail d'initialisation de compte
    $fn = "emailbody";
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>0,
								   'FCOUNT'=>70,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment");
    $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_UserMessages','account_request_mailbody');
    $ret["__$fn"]->fgroup =$groupe4;

    $fn = "emailsign"; // signature du mail d'initialisation
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\Label\Label',
								   'COMPULSORY'=>0,
								   'FCOUNT'=>70,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment");
    $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_UserMessages','account_request_mailsign');
    $ret["__$fn"]->fgroup =$groupe4;


    $fn = "mailtemplate"; // gabarit fond de mail
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>64,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment");
    $ret["__$fn"]->default = $this->mailLayoutTemplate;
    $ret["__$fn"]->fgroup =$groupe4;

    $fn = "mailalias"; // alias de la page de fond de mail
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
								   'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>64,
								   'TRANSLATABLE'=>true,
								   'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment");
    $ret["__$fn"]->default = $this->mailLayoutAlias;
    $ret["__$fn"]->fgroup =$groupe4;

    // RGPD (le champ consentement du module est pris en compte)
    $fn = 'overloadrgpd';
    $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
									    'FTYPE'=>'\Seolan\Field\Label\Label',
									    'FCOUNT'=>70,
									    'COMPULSORY'=>0,
									    'LABEL'=>$myLabel("rqacnt_field_$fn")]);
    $ret["__$fn"]->fgroup =$groupe5;
    $ret["__$fn"]->listbox=false;
    if (empty($this->consent_field) || !$this->xset->fieldExists($this->consent_field)){
      $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment");
      $ret["__$fn"]->default = '';
    } else {
      $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment")." ({$this->xset->getField($this->consent_field)->label})";
      $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_UserMessages','account_request_rgpd_text');

      // texte séparé pour l'acceptation
      $fn = 'rgpdcheckboxlabel';
      $ret["__$fn"]=\Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>"__$fn",
										   'FTYPE'=>'\Seolan\Field\Label\Label',
										   'FCOUNT'=>70,
										   'COMPULSORY'=>0,
										   'LABEL'=>$myLabel("rqacnt_field_$fn")]);
      $ret["__$fn"]->defaultText = \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_UserMessages','account_request_rgpd_label');
      $ret["__$fn"]->comment = $myLabel("rqacnt_field_{$fn}_comment");
      $ret["__$fn"]->listbox=false;
      $ret["__$fn"]->fgroup = $groupe5;
    }

    return $ret;
  }
  /**
   * Prépare une demande de compte
   * -> depuis une section fonction (FO)
   * -> depuis le lien en page de login (BO)
   */
  function requestAnAccount($ar=NULL){
    // champ consentement
    if (!empty($this->consent_field) && $this->xset->fieldExists($this->consent_field)){
      $this->xset->getField($this->consent_field)->compulsory = true;
      if (!empty($ar['overloadrgpd'])){
	$this->xset->getField($this->consent_field)->comment = \Seolan\Field\Label\label::getLabelFromId($ar['overloadrgpd']);
      }
    }
    $this->captcha=true;
    if(empty($ar['selectedfields'])){
      $protected = $this->requestAccountProtectedFields(true);
      $ar['selectedfields']=array_diff(array_keys($this->xset->desc),$protected);
    }
    // Désactive toutes les listbox
    foreach($this->xset->desc as $f){
      $f->listbox=false;
    }
    return $this->insert($ar);
  }
  /**
   * Controle ajax avant validation demande de compte
   */
  public function ajaxProcRequestAnAccount2Ctrl($ar=null){
    return $this->ajaxProcEditCtrl($ar);
  }
  /**
   * Enregistrement d'un compte depuis une section fonction 'requestAnAccount'
   * -> lire la configuration de la section fonction
   * -> controler les données
   * -> créer le compte en fonction de la conf. et des données saisies
   * -> avertir
   *    . le user avec le token de saisie de mot de passe
   *    . l'admin éventuel
   *
   * à voir : est-ce que le compte est immédatement actif etc
   * si non : à l'admin de faire l'envoi du mot du mail de mot de passe
   *
   * !!! insert directe via le datasource pour avoir accès à PUBLISH
   */
  function procRequestAnAccount2($ar=null){
    $this->captcha = true;
    $p = new \Seolan\Core\Param($ar,null);
    list($itmoid, $itoid) = explode(',', $p->get('_section'));

    // lecture du paramétrage de la section d'origine
    $itModule = \Seolan\Core\Module\Module::objectFactory(['tplentry'=>TZR_RETURN_DATA,
							 'moid'=>$itmoid,
							 'interactive'=>false]);
    $oids = $itModule->_getOids($itoid);
    list($oidit,$oiddest,$oidtemplate,$zone) = $oids;
    $funcDetails = $itModule->getFullFunctionDetails($oiddest);
    if (empty($funcDetails)){
      \Seolan\Core\Logs::critical(__METHOD__,"unable to load parameters $oiddest");
      return;
    }
    $grps = preg_split('/\|\|/', $funcDetails['_fullquery']['__grps'], -1, PREG_SPLIT_NO_EMPTY);
    $aliasfield = $funcDetails['_fullquery']['__aliasfield'];
    $nexturl = $p->get('_next'); // = nextalias transformé  $funcDetails['_fullquery']['__nextalias'];
    $onerror = $p->get('_onerror'); // en pcpe la page de saisie
    $selectedfields = preg_split('/\|\|/', $funcDetails['_fullquery']['__selectedfields'], -1, PREG_SPLIT_NO_EMPTY);
    $boval = $funcDetails['_fullquery']['__BO_val']??2;
    $publishval = $funcDetails['_fullquery']['__PUBLISH_val']??2;
    $accountduration = $funcDetails['_fullquery']['__accountduration_val'];

    // contrôles et mises en forme
    foreach($this->requestAccountProtectedFields(false) as $fn){
      unset($_REQUEST[$fn]);
      unset($_REQUEST[$fn.'_HID']);
    }
    $lar = ['tplentry'=>TZR_RETURN_DATA];
    $lar['GRP'] = $grps;
    if (!empty($aliasfield)){
      $lar['alias'] =trim($p->get($aliasfield));
    } else {
      $lar['alias'] = $p->get('alias');
    }

    unset($_REQUEST['alias']);
    if (strtolower($lar['alias']) == 'root'){
      unset($lar['alias']);
    }
    // autres valeurs par defaut
    $lar['DATEF'] = date('Y-m-d');
    if (!empty($accountduration))
      $lar['DATET'] = date('Y-m-d', strtotime(date('Y-m-d')."+ $accountduration days"));
    else
      $lar['DATET'] = date('Y-m-d', strtotime(date('Y-m-d').' +10 year'));

    if ($this->xset->fieldExists('BO'))
      $lar['BO'] = $boval;
    if ($this->xset->fieldExists('PUBLISH'))
      $lar['PUBLISH'] = $publishval;
    $lar['ldata']=\Seolan\Core\Shell::getLangUser();
    $lar['luser']=\Seolan\Core\Shell::getLangUser();
    $lar['directoryname'] = 'local';

    if (!in_array('fullnam', $selectedfields) && $this->xset->fieldExists('fullnam')){
      $this->getComposedFullnam($lar);
    }
    /// retour à l'alias + force cache + message
    $requestAnAccountGoNext = function($alias, $message){
      $url = makeUrl($alias, ['_'=>date('ymdhis'),'_message'=>$message]);
      \Seolan\Core\Shell::setNext($url);
    };

    // contrôles std + re captcha
    $r = $this->procInsertCtrl($lar);

    if ($r){

      /*
       création de la ligne en base avec le datasource car en 'none' on ne peut pas insérer et publier le compte
       voir  Module\Table::getFieldsSec. On n'aura pas OPTS du user
      */
      
      $r2 = $this->xset->procInput($lar);

      if (!isset($r2['oid'])){
	\Seolan\Core\Logs::critical(__METHOD__, 'Erreur lors de l\'insertion du compte');
	$requestAnAccountGoNext($onerror,'Something went wrong');
	return;
      }
      $du = $this->xset->rdisplay($r2['oid']);
      // page de fond de mail par defaut ou dédiée
      $mailoidit=$itModule->getOidFromAlias($funcDetails['_fullquery']['__mailalias']);
      if ($mailoidit == null){
	\Seolan\Core\Logs::critical(__METHOD__,"no maillayout '{$funcDetails['_fullquery']['__mailalias']}' found for module {$itModule->getLabel()} {$itModule->_moid}");
	return;
      }
      if (empty(trim($funcDetails['_fullquery']['__mailtemplate']))){
	\Seolan\Core\Logs::critical(__METHOD__,"no maillayout found for module {$itModule->getLabel()} {$itModule->_moid}");
	return;
      }
      $page = [$funcDetails['_fullquery']['__mailalias'], $funcDetails['_fullquery']['__mailtemplate'], $itModule->_moid];
      // compte immédiatment actif : notification et token
      if (!$this->xset->fieldExists('PUBLISH') || $du['oPUBLISH']->raw == 1){
	$this->requestAnAccountInitialize($du, $funcDetails['_fullquery'], true, $page);
      } else {
	$this->requestAnAccountInitialize($du, $funcDetails['_fullquery'], false, $page);
      }
      // alias next spécifié
      $requestAnAccountGoNext($nexturl, '');
    } else {
      $message = \Seolan\Core\Shell::from_screen('','message');
      $requestAnAccountGoNext($onerror, $message);
    }
  }
  /**
   * demande de compte : initialisation d'un compte
   * -> "redirect" utilisateurs BO
   * -> actif : génération du token
   * -> envoi d'un mail initialisation / mot de passe ou de prise en compte de demande
   */
   protected function requestAnAccountInitialize($du, $functionFullQuery, $active, $page){

    // cas si utilisateur BO : fonctionnement BO de la console
    if (isset($du['oBO']) && $du['oBO']->raw == 1){
      list($ok) = $this->prepareNewPassword(null, $du['oid'], 'forgotten');
      return;
    }
    if ($active){
      $tokenid = static::createPasswordToken($du['oalias']->raw, 'initaccount', $du['oid']);
    } else {
      $tokenid = $null;
    }
    if ($active && $tokenid){
      $siteTokenUrl = makeUrl($GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().'/index.php?',
			      ['alias'=>$functionFullQuery['__loginalias'],
			       'id'=>$tokenid,
			       '_'=>date('ymdhis')]);
    } else {
      $siteUrl = $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName();
    }

    $mail = $this->getMailer();
    $admins = explode(',', $functionFullQuery['__emailadmin']);

    $mail->sendPrettyMail(\Seolan\Field\Label\Label::getLabelFromId($functionFullQuery['__emailsubject']),
			  \Seolan\Field\Label\Label::getLabelFromId($functionFullQuery['__emailbody']),
			  array_merge([['mail'=>$du['oemail']->raw, 'name'=>$du['ofullnam']->raw]], $admins),
			  $admins[0],
			  [
			   'sign'=>0,
			   'footer'=>\Seolan\Field\Label\Label::getLabelFromId($functionFullQuery['__emailsign']),
			   'page'=>$page,
			   'mtype'=>'useraccountrequest',
			   'tags'=>['oid'=>$du['oid'],
				    'sitetokenurl'=>$siteTokenUrl]
			   ]
			  );
  }
  /// Enregistre une demande de compte
  function procRequestAnAccount($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $this->captcha=true;
    $ar['PUBLISH']=2;
    $ar['alias']='user'.uniqid();
    $ar['ldata']=\Seolan\Core\Shell::getLangUser();
    $ar['luser']=\Seolan\Core\Shell::getLangUser();
    $ret=$this->procInsert($ar);

    if(!empty($ret) && !empty($ret['oid']) && !empty($this->send_account_request_to_email)) {
      $olduser=$GLOBALS['XUSER'];
      $GLOBALS['XUSER']=new \Seolan\Core\User(array("UID"=>'root'));
      setSessionVar("UID",$GLOBALS['XUSER']->_curoid);
      $this->procSendACopyTo(array('oid'=>$ret['oid'],
				   'sendinmail' => array($ret['oid']=>true),
				   'showdest'=>false,
				   'dest_aemails' => $this->send_account_request_to_email,
				   'asubject' => 'Account request : '.$p->get('fullnam'),
				   'amessage' => 'You have received a new account request',
				   'tplentry' => TZR_RETURN_DATA, '_local'=>true), TZR_SENDER_ADDRESS);
      if(!empty($olduser)) {
	setSessionVar("UID",$olduser->uid());
	$GLOBALS["XUSER"]=$olduser;
      }

    }
    return $r;
  }
  /// account protected fields
  protected function requestAccountProtectedFields($complete=false){
    $fields = ['alias','GRP','GRPA','ldata','luser','passwd','DATEF','DATET','BO', 'bohome','UPD','OWN','CREAD','directoryname','PUBLISH'];
    if (!$complete)
      unset($fields[0]);
    if (!empty(trim($this->composed_fullnam)))
      $fields[] = 'fullnam';
    return $fields;
  }
  /// myaccount protected, masqued fields
  protected function myAccountFieldsSec($user){
    if (!\Seolan\Core\Shell::getMonoLang())
      $lg = 'ro';
    else
      $lg = 'none';
    return ['DATET'=>'ro',
	    'DATEF'=>'ro',
	    'alias'=>'ro',
	    'GRP'=>'ro',
	    'GRPA'=>'none',
	    'BO'=>'none',
	    'bohome'=>'none',
	    'luser'=>$lg,
	    'ldata'=>$lg];
  }
  /// Edite le compte de l'utilisateur courant
  function myAccount($ar=NULL){
    $p = new \Seolan\Core\Param($ar, []);
    $user = \Seolan\Core\User::get_user();
    $ar['oid']=$user->_curoid;
    if(!is_array($ar['fieldssec'])){
      foreach($this->myAccountFieldsSec($user)  as $f=>$fs){
	if (!isset($this->fieldssec[$f]) || $this->fieldssec[$f] != 'none')
	  $ar['fieldssec'][$f]=$fs;
      }
      $ar['fieldssec']['directoryname']='none';
    }

    $fieldssec = \Seolan\Core\Directory\Directory::objectFactory($user->_cur['directoryname'])->getUserManager()->getAccountFieldssec();

    if ($fieldssec != null){
      foreach($fieldssec as $fn=>$fa){
	if ($fn == '*'){
	  foreach($this->xset->desc as $fn=>$fn){
	    if (@$ar['fieldssec'][$fn] != 'none')
	      $ar['fieldssec'][$fn]=$fa;
	  }
	} else if($this->xset->fieldExists($fn) && @$ar['fieldssec'][$fn] != 'none'){
	  $ar['fieldssec'][$fn]=$fa;
	}
      }
    }

    $ar['tplentry'] = TZR_RETURN_DATA;

    $r = $this->edit($ar);

    // some directory disallow edit on (all) fields
    $r['_hasEditableFields'] = false;
    foreach($r['fields_object'] as $fv){
      $fd = $fv->fielddef;
      $fn =  $fd->field;
      if (!$fd->sys
	  && (!isset($ar['fieldssec'][$fn])
	      || !in_array($ar['fieldssec'][$fn], ['none','ro']))){
	$r['_hasEditableFields'] = true;
	break;
      }
    }

    $expire_date = $this->getPasswordExpirationDate($ar['oid']);
    if ($expire_date){
      $fddate = \Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>'expriredate',
								 'FTYPE'=>'\Seolan\Field\Date\Date',
								 'MULTIVALUED'=>0,
								 'COMPULSORY'=>false,
								 'LABEL'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','password_expiration_date'),
								 'TARGET'=>TZR_DEFAULT_TARGET
								 ]);
      $fddate->readonly = true;
      \Seolan\Core\Shell::alert($fddate->label." : ".$fddate->display($expire_date)->html, 'info');

    }
  
    Labels::loadLabels('Seolan_Module_PushNotification_Device_Device');
    \Seolan\Core\Shell::toScreen2('moid', 'device', \Seolan\Core\Module\Module::getMoid(XMODPUSHNOTIFICATIONDEVICE_TOID));

    return \Seolan\Core\Shell::toScreen1($p->get('tplentry'), $r);

  }
  /**
   * Valide l'édition du compte de l'utilisateur courant
   * controle prélable du mot de passe
   * enregistrement et notification pour le nouveau mot de passe
   * // todo     if (!$user->isLocal()){ notif directory
   */
  function procEditMyAccount($ar=NULL){

    $p=new \Seolan\Core\Param($ar,NULL);
    $ar['oid']=\Seolan\Core\User::get_current_user_uid();
    if(!is_array($ar['fieldssec'])){
      foreach(array('DATET','DATEF','alias','GRP','GRPA','BO','bohome') as $f){
	$ar['fieldssec'][$f]='ro';
      }
    }
    $ar['_ctrlpassword'] = true;
    $ok = $this->procEditCtrl($ar);
    if (!$ok){
      unset($_REQUEST['skip'], $_REQUEST['_skip']);
      \Seolan\Core\Shell::changeTemplate('Module/User.myAccount.html');
      \Seolan\Core\Shell::setNext();
      $ar['options']=$this->xset->prepareReEdit($ar);
      $ar['tplentry']= 'br';
      $ar['fieldssec']= null;
      $r = $this->myAccount($ar);
      $r['_procEditCtrlError'] = true;
      return $r;
    }
    unset($ar['_ctrlpassword']);
    $ret=$this->procEdit($ar);
    $d=$this->display(array('oid'=>$ar['oid'],'tplentry'=>TZR_RETURN_DATA,'selectedfields'=>array('fullnam','email')));

    setSessionVar('FullName',$d['ofullnam']->raw);
    setSessionVar('Email',$d['oemail']->raw);

    // mise à jour des props. des mots de passe
    // rem : si on est dans cette fonction, c'est que le mot de passe est correct ?
    $passwd = trim($p->get('passwd'));
    if (!empty($passwd) && TZR_UNCHANGED != $passwd){
      $this->changePassword($ar['oid'], $passwd, $passwd);
    }

    return $ret;
  }
  /// Recupère les préferences de l'utilisateur sur chaque module
  function getPreferences($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $modlist=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA));
    foreach($modlist['lines_oid'] as $i=>$moid){
      $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid,'tplentry'=>TZR_RETURN_DATA));
      $prefs=$mod->editPrefs();
      if(!empty($prefs['fields'])) $modlist['lines_prefs'][$i]=$prefs['fields'];
      $modlist['lines_prefs_btn'][$i] = [
        'eraseButton' => $prefs['eraseButton'],
        'saveButton' => $prefs['saveButton'],
      ];
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$modlist);
  }
  /// Rempli le fullname dans le cas ou il est composé d'autres champs
  function getComposedFullnam(&$ar){
    $p = new \Seolan\Core\Param($ar,array());
    $oid = $p->get('oid');
    if(!is_array($oid) && !empty($this->composed_fullnam)){
      $tab=explode(',',$this->composed_fullnam);
      $ok=false;
      // On verifie qu'au moins un des champs qui composent le nom existe
      foreach($tab as $f){
	if($p->is_set($f)){
	  $ok=true;
	}
      }
      if($ok){
	$fullnam='';
	foreach($tab as $f){
	  if($p->is_set($f)){
	    $v=$p->get($f);
	  }else{
	    if(empty($d)) $d=$this->display(array('tplentry'=>TZR_RETURN_DATA,'oid'=>$oid,'selectedfields'=>$tab));
	    $v=$d['o'.$f]->raw;
	  }
	  $fullnam.=$v;
	  if(!empty($v)) $fullnam.=' ';
	}
	if(substr($fullnam,-1)==' ') $fullnam=substr($fullnam,0,-1);
	if(!empty($fullnam)) $ar['fullnam']=$fullnam;
      }
    }
  }

  /// Sauvegarde un compte utilisateur
  function procEdit($ar=NULL){
    $p = new \Seolan\Core\Param($ar,array());
    $oid = $p->get('oid');
    $ors = getDB()->fetchOne('select alias, passwd from USERS where KOID=?', array($oid));
    $this->getComposedFullnam($ar);
    $res = parent::procEdit($ar);

    $passwd = trim($p->get('passwd'));
    if($oid && !is_array($oid) && !empty($passwd) && $passwd != TZR_UNCHANGED) {
      $this->changePassword($oid, $passwd, $passwd, false);
    }

    return $res;
  }


  /// Duplication d'un compte utilisateur
  function procEditDup($ar){
    $r=parent::procEditDup($ar);
    if(!empty($r['oid'])){
      $d1=$this->display(array('oid'=>$r['oid'], 'tplentry'=>TZR_RETURN_DATA));
      $groups=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'GRP');
      foreach($d1['oGRP']->oidcollection as $groupoid) {
	$d2=$groups->rDisplay($groupoid);
	if(!empty($d2['oprefs']->raw)) {
	  // on duplique le contenu des préférences stockées dans la table OPTS
	  $templateoid=$d2['oprefs']->raw;
	  $useroid=$r['oid'];
	  $rs2=getDB()->select("select * from OPTS where user=?", [$templateoid]);
	  $opts=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'OPTS');
	  while($rs2 && ($ors2=$rs2->fetch())) {
	    $opts->procInput(array('user'=>$useroid, 'specs'=>$ors2['specs'], 'modid'=>$ors2['modid'],
				   'dtype'=>$ors2['dtype']));
	  }
	}
      }
    }
    return $r;
  }

  /**
   * création d'un nouvel utilisateur
   * - mot de passe, directory par défaut, préférences
   */
  function procInsert($ar) {
    $this->getComposedFullnam($ar);
    $p=new \Seolan\Core\Param($ar, []);
    $passwd=$p->get('passwd');
    if($passwd==TZR_UNCHANGED || !$passwd) {
      $ar['passwd']=newPassword();
    }
    if (!$p->is_set('directoryname')){
      $ar['directoryname'] = 'local';
    }
    $r=parent::procInsert($ar);

    if(!empty($r['oid'])) {
      $d1=$this->display(array('oid'=>$r['oid'], 'tplentry'=>TZR_RETURN_DATA));
      $groups=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'GRP');
      foreach($d1['oGRP']->oidcollection as $groupoid) {
	$d2=$groups->rDisplay($groupoid);
        if(!empty($d2['oprefs']->raw)) {
          // on duplique le contenu des préférences stockées dans la table OPTS
          $templateoid=$d2['oprefs']->raw;
          $useroid=$r['oid'];
          $rs2=getDB()->select('select * from OPTS where user="'.$templateoid.'"');
          $opts=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'OPTS');
          while($rs2 && ($ors2=$rs2->fetch())) {
            $opts->procInput(array('user'=>$useroid, 'specs'=>$ors2['specs'], 'modid'=>$ors2['modid'],
                                   'dtype'=>$ors2['dtype']));
          }
          return $r;
        }
      }
    }
    return $r;
  }

  /// Ajoute les actions du browse à une ligne donnée
  function browseActionsForLine(&$r,&$i,&$oid,&$oidlvl,&$noeditoids){
    if(!\Seolan\Core\Shell::isRoot() && $oid==TZR_USERID_ROOT){
      $this->browseActionView($r,$i,$oid,$oidlvl);
    }else{
      parent::browseActionsForLine($r,$i,$oid,$oidlvl,$noeditoids);
      $this->browseActionSwitch($r,$i,$oid,$oidlvl);
      if(\Seolan\Core\Ini::get('RCisActive')){
          $this->browseActionDM($r,$i,$oid,$oidlvl);
      }
    }
  }

  /// Retourne les infos de l'action changer d'utilisateur du browse
  function browseActionSwitch(&$r,&$i,&$oid,&$oidlvl,$usersel=false){
    $this->browseActionForLine('switch',$r,$i,$oid,$oidlvl,$usersel);
  }
  function browseActionSwitchText(){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','logas','text');
  }
  function browseActionSwitchIco(){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','logas');
  }
  function browseActionSwitchLvl(){
    return $this->secGroups('setuid');
  }
  function browseActionSwitchHtmlAttributes(&$url,&$text,&$icon){
    if(\Seolan\Core\Ini::get('RCisActive')){
        return 'onclick="return RClogout();"';
    } else {
        return '';
    }
  }
  function browseActionSwitchUrl($usersel){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=setuid&template=Module/User.secedit.html&_next='.urlencode('&function=portail&template=Core.layout/main.html&moid='.\Seolan\Core\Module\Module::getMoid(XMODADMIN_TOID));
  }
  /**
   * Contrôle si une insertion est valide
   */
  function procInsertCtrl(&$ar){
    $p=new \Seolan\Core\Param($ar,['_ctrlpassword'=>false]);
    $ok = true;
    $invalids = [];
    foreach($this->xset->desc as $fn=>$fd){
      if ($fd->compulsory){
        $v = $p->get($fn);
        if ((!is_array($v) && empty(trim($v))) || (is_array($v) && (!count($v) || ($v[0]==='Foo' && count($v)===1)))){
	  $ok = false;
	  $invalids[] = $fn.' '.$fd->label;
	}
      }
    }
    if (!$ok){
      \Seolan\Core\Shell::toScreen2('', 'message', sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','some_data_are_invalid'), implode(',', $invalids)));
      return false;
    }
    // autres controles std dont captcha si activé
    return parent::procInsertCtrl($ar);
  }
  /// Controle si une édition est valide
  function procEditCtrl(&$ar) {
    if(parent::procEditCtrl($ar)!==true) return false;
    $p=new \Seolan\Core\Param($ar,['_ctrlpassword'=>false]);
    $ctrlpassword = $p->get('_ctrlpassword');
    $alias=$p->get('alias');
    if (!empty($alias)) {
      $aliasLength = $this->xset->desc['alias']->fcount;
      if (strlen($alias) < TZR_ALIAS_MINLEN || strlen($alias) > $aliasLength) {
        \Seolan\Core\Shell::toScreen2('', 'message', "$alias $aliasLength ".strlen($alias).' '.sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','alias_length_error'), TZR_ALIAS_MINLEN, $aliasLength));
        return false;
      }
      if (!isEmail($alias) && !preg_match('/^([a-z0-9@\._-]*)$/i',$alias)) {
        \Seolan\Core\Shell::toScreen2('', 'message', \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','alias_format_error'));
        return false;
      }
    }
    $cnt=0;
    $oid=$p->get('oid');
    if(!empty($oid) && !empty($alias)) $cnt=getDB()->count('select COUNT(*) from '.$this->table.' where alias=? and KOID!=?', [$alias, $oid]);
    elseif(!empty($alias)) $cnt=getDB()->count('select COUNT(*) from '.$this->table.' where alias=?',[$alias]);
    if($cnt) {
      \Seolan\Core\Shell::toScreen2('','message','"'.$alias.'" : '.\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','existing_user','text'));
      return false;
    }
    // Vérifier le mot de passe si nécessaire
    if ($ctrlpassword){
      $passwd = trim($p->get('passwd'));
      if (!empty($passwd) && TZR_UNCHANGED != $passwd){
	$passwdfd = $this->xset->getField('passwd');
	if ($passwdfd->with_confirm){
	  $confirm = $p->get('passwd_HID');
	} else {
	  $confirm = $passwd;
	}
	list($ok, $mess) = $this->checkPassword($ar['oid'], $passwd, $confirm);
	if (!$ok){
	  \Seolan\Core\Shell::toScreen2('','message', $mess);
	  return false;
	}
      }
    }
    // Verifie que les nouveaux droits ne sont pas supérieurs aux droits de l'utilisateur actuel
    $grp=$p->get('GRP');
    if(false && !empty($grp) && !\Seolan\Core\Shell::isRoot()){
      $grp=$this->xset->desc['GRP']->post_edit($grp,array('GRP_HID'=>$p->get('GRP_HID'),'GRP_FMT'=>$p->get('GRP_FMT')));
      if(!empty($grp)){
	$rs=getDB()->select('select * from MODULES');
	while($rs && $ors=$rs->fetch()){
	  foreach($GLOBALS['TZR_LANGUAGES'] as $lang=>$foo){
	    $nlvl=\Seolan\Core\User::secure8maxlevel($ors['MOID'],'',$grp->raw,$lang);
	    $alvl=\Seolan\Core\User::secure8maxlevel($ors['MOID'],'',null,$lang);
	    if(!\Seolan\Core\User::compareSecLevelsLte($ors['MOID'],$nlvl,$alvl)){
	      \Seolan\Core\Shell::toScreen2('','message',\Seolan\Core\Labels::getSysLabel('Seolan_Core_Security','noauthtosetsec'));;
	      return false;
	    }
	  }
	}
      }
    }
    return true;
  }

  /// suppression d'un utilisateur
  function del($ar) {
    $p = new \Seolan\Core\Param($ar, array());
    $oid = $p->get('oid');

    if($ret=parent::del($ar) && !is_array($oid)) {
      // nettoyage divers suite à suppression d'un user
      $this->cleanUserOrGroup($oid);

      // archivage, sans suppression des logs si corbeille
      $del = !$this->usetrash;
      \Seolan\Core\Archive::appendOid($oid, 'LOGS.user', $del);
      \Seolan\Core\Archive::appendOid($oid, 'LOGS.object', $del);
    }
    return $ret;
  }
  /// Effacement définitif : on finalise / aux traitements de appendOid du del
  public function moveFromTrash($ar=null){
    $p=new \Seolan\Core\Param($ar,[]);
    $oid = $p->get('oid');
    parent::moveFromTrash($ar);
    if (!empty($oid)){
      // suppression des logs du user, l'archivage a eu lieu lors du del
      getDB()->execute('DELETE FROM LOGS WHERE object=? /*'.__METHOD__.'*/',[$oid]);
      // on ne supprime pas les logs relatifs à la corbeille
      getDB()->execute('DELETE FROM LOGS WHERE etype not in ("delete", "movefromtrash") and user=? /*'.__METHOD__.'*/',[$oid]);
    }
  }
  /// Obtenir la liste des bookmarks de l'utilisateur connecté ou de l'utilisateur dont l'oid est passé dans le paramètre
  function getBookmarks($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid','norequest');
    if(empty($oid)) $oid=\Seolan\Core\User::get_current_user_uid();
    $tplentry=$p->get('tplentry');
    $r1=\Seolan\Library\Opts::getOpt($oid, $this->_moid, 'book');
    $sortarray=array();
    foreach($r1 as $k=>&$v1) {
      $sortarray[$k]=$v1['group'].$v1['title'];
      $v1['key']=$k;
      $v1['text']=nl2br(htmlspecialchars(strip_tags($v1['text'])));
      $v1['group']=htmlspecialchars(strip_tags($v1['group']));
      $v1['title']=htmlspecialchars(strip_tags($v1['title']));
      $v1['autostart']=@$v1['autostart'];
      $v1['viewhome']=@$v1['viewhome'];
    }
    array_multisort($sortarray,SORT_ASC,$r1);
    if($tplentry==TZR_RETURN_DATA) return $r1;
    else{
      $r2['bks']=$r1;
      return \Seolan\Core\Shell::toScreen1($tplentry,$r2);
    }
  }
  /// Recupere les parametres d'un bookmark
  function getBookmark($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid','norequest');
    if(empty($oid)) $oid=\Seolan\Core\User::get_current_user_uid();
    $tplentry=$p->get('tplentry');
    $key=$p->get('key');
    $r1=\Seolan\Library\Opts::getOpt($oid, $this->_moid, 'book');
    $v1=$r1[$key];
    // Assure compatibilité <V8
    if(!is_array($v1['urls'])) $v1['urls']=array($v1['url']);
    $v1['key']=$key;
    $v1['text']=htmlspecialchars(strip_tags($v1['text']));
    $v1['group']=htmlspecialchars(strip_tags($v1['group']));
    $v1['title']=htmlspecialchars(strip_tags($v1['title']));
    $v1['autostart']=@$v1['autostart'];
    $v1['viewhome']=@$v1['viewhome'];
    return \Seolan\Core\Shell::toScreen1($tplentry,$v1);
  }

  /// Modifie l'intégralité des bookmarks d'un utilisateur
  function &setBookmarks($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid','norequest');
    if(empty($oid)) $oid=\Seolan\Core\User::get_current_user_uid();
    $specs=$p->get('specs');
    \Seolan\Library\Opts::setOpt($oid, $this->_moid, 'book', $specs);
  }

  /// Suppression d'un bookmark
  function delBookmark($ar) {
    $p=new \Seolan\Core\Param($ar,array('oid'=>\Seolan\Core\User::get_current_user_uid()));
    $oid=$p->get('oid','norequest');
    if(!($oid==\Seolan\Core\User::get_current_user_uid() || \Seolan\Core\Shell::isRoot()))
      \Seolan\Library\Security::warning('\Seolan\Module\User\User::delBookmark: user '.$oid.' cannot apply');
    $tplentry=$p->get('tplentry');
    $key=$p->get('key');
    $r1=\Seolan\Library\Opts::unsetSubOpt($oid, $this->_moid, 'book', $key);
  }

  /// Prépare l'insertion d'un nouveau bookmark
  function insertBookmark($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $urls=\Seolan\Module\User\User::_normalizeBookmark($p->get('urls'));

    foreach($urls as $key => $url) {
      if(preg_match('/^mod(\d+)query$/', $url, $matches)) {
        $query = getSessionVar($url, null);
        if($query) {
          $urls[$key] = 'tplentry=br&template=Module/Table.browse.html&function=procQuery&moid=' . $matches[1] . '&' . http_build_query(array_filter($query));
        }
      }
    }

    $titles=$p->get('titles');
    $comments=$p->get('comments');
    $tplentry=$p->get('tplentry');
    $ret=array('urls'=>$urls,'titles'=>$titles,'comments'=>$comments);
    \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// Enregistre un nouveau bookmark
  function procInsertBookmark($ar=NULL){
    return $this->procEditBookmark($ar);
  }

  /// Modification d'un bookmark, préparation de l'écran
  function editBookmark($ar) {
    $p=new \Seolan\Core\Param($ar,array('oid'=>\Seolan\Core\User::get_current_user_uid()));
    $oid=$p->get('oid','norequest');
    if(!($oid==\Seolan\Core\User::get_current_user_uid() || \Seolan\Core\Shell::isRoot()))
      \Seolan\Library\Security::warning('\Seolan\Module\User\User::editBookmark: user '.$oid.' cannot apply');
    $tplentry=$p->get('tplentry');
    $key=$p->get('key');
    $r1=\Seolan\Library\Opts::getOpt($oid, $this->_moid, 'book');
    $r2=$r1[$key];
    // Assure compatibilité <V8
    if(!is_array($r2['urls'])) $r2['urls']=array($r2['url']);
    $r2['key']=$key;
    return \Seolan\Core\Shell::toScreen1($tplentry, $r2);
  }

  /// Normalisation des bookmarks: on essaie de transformer les bookmarks en url generiques par defaut
  static public function _normalizeBookmark($urls) {
    if(!is_array($urls)){
      $urls=array($urls);
      $one=true;
    }
    foreach($urls as &$url){
      $url=strip_tags(trim($url));
      $url=preg_replace('/^(javascript[^&]*)/','',$url);
      $url=preg_replace('/^(http[^&]*)/','',$url);
      $url=preg_replace('/^(https[^&]*)/','',$url);
      $url=preg_replace('@^(/[^&]*)@','',$url);
      $url=preg_replace('/'.session_name().'=[a-z0-9]+/i','',$url);
      $url=preg_replace('/_bdx=[a-z0-9_]+/i','',$url);
      $url=preg_replace('/&_nohistory=./i','',$url);
      $url=preg_replace('/&_raw=./i','',$url);
      $url=preg_replace('/&_ajax=./i','',$url);
      $url=preg_replace('/&_bdxnewstack=./i','',$url);
      $url=preg_replace('/&_=[^&]+/i','',$url);
      $url=str_replace('&&','&',$url);
    }
    if($one) return $urls[0];
    else return $urls;
  }

  /// Enregistres les modifications d'un bookmark
  function procEditBookmark($ar) {
    $p=new \Seolan\Core\Param($ar,array('oid'=>\Seolan\Core\User::get_current_user_uid()));
    $oid=$p->get('oid','norequest');
    if(!($oid==\Seolan\Core\User::get_current_user_uid() || \Seolan\Core\Shell::isRoot()))
      \Seolan\Library\Security::warning('\Seolan\Module\User\User::procEditBookmark: user '.$oid.' cannot apply');
    $tplentry=$p->get('tplentry');
    $key=$p->get('key');
    $group=strip_tags($p->get('group'));
    $title=strip_tags($p->get('title'));
    $text=strip_tags($p->get('text'));
    $autostart=$p->get('autostart');
    $viewhome=$p->get('viewhome');
    $titles=$p->get('titles');
    $comms=$p->get('comments');
    $urls=\Seolan\Module\User\User::_normalizeBookmark($p->get('urls'));
    $r1=\Seolan\Library\Opts::getOpt($oid, $this->_moid, 'book');
    $new=array('title'=>$title,'text'=>$text,'group'=>$group,'urls'=>$urls,'titles'=>$titles,'comments'=>$comms,'autostart'=>$autostart,
	       'viewhome'=>$viewhome);
    if(isset($key)) $r1[$key]=$new;
    else $r1[]=$new;
    \Seolan\Library\Opts::setOpt($oid, $this->_moid, 'book', $r1);
  }
  /**
   * controle de validité d'un mot de passe pour un user donné
   */
  function checkPassword($useroid, $password, $confirm){
    $password = trim($password);
    $confirm = trim($confirm);
    if ($password != $confirm){
      return [false, $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_SessionMessages', 'fillin_password_error_equals')];
    }
    $userPasswdField = $this->getPasswordField();
    if (empty($password) || strtolower($password) == 'tzr_unchanged' || !preg_match("/{$userPasswdField->edit_format}/", $password)){
      return [false,
	      $userPasswdField->edit_format_text.' <br> '.
	      sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_SessionMessages','console_password_error_complement'), $this->passwordshistorysize)];
    }
    // contrôle des anciens mots de passe
    $passwords = \Seolan\Library\Opts::getOpt($useroid, $this->_moid, static::PASSWORDS_LIST_OPT);
    $hpassword = \Seolan\Field\Password\Password::hash($password);
    if(in_array($hpassword, $passwords)){
      return [false,sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_SessionMessages', 'console_password_error_complement'), $this->passwordshistorysize)];
    }
    return [true, null];
  }
  /* Fin gestion des bookmarks */
  /**
   * date d'expiration d'un mot de passe si elle existe
   */
  public function getPasswordExpirationDate($useroid, $lifetime=null){
    $expire_date = null;
    $update_date = \Seolan\Library\Opts::getOpt($useroid, $this->_moid, static::PASSWORD_EXPIRE_OPT);
    if (!empty($update_date[0])){
      if ($lifetime == null)
	$lifetime = $this->getPasswordLifeTime($useroid);
      $expire_date = date('Y-m-d', strtotime($update_date[0].' + '.$lifetime.' days'));
    }
    return $expire_date;
  }
  /**
   * duree du mot de passe en fonction du user
   * -> prise en comtpe du groupe
   */
  public function getPasswordLifeTime($useroid){
    $durations  = [];
    $dsgrp = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS=GRP');
    if ($dsgrp->fieldExists('passwordexpiration')){
      $user = new \Seolan\Core\User($useroid);
      $grps = $user->groups();
      $durations = getDB()->fetchCol('select ifnull(passwordexpiration,0) from GRP where ifnull(passwordexpiration,0)>0 and KOID in ('.implode(',', array_fill(0,count($grps),'?')).')', $grps);
    }
    $durations[] = $this->passwordexpiration;
    return min($durations);
  }
  /**
   * enregistrement d'un nouveau mot de passe
   * -> contrôle du mot de passe
   * -> mise à jour (password, prefs : dernier mot de passe, date)
   * -> notification (mail date expiration)
   */
  function changePassword($useroid, $password, $confirm,$sendmail=true){
    list ($ok, $message) = $this->checkPassword($useroid, $password, $confirm);
    if (!$ok){
      return [false, $message];
    }
    // mise à jour du USERS
    $this->xset->procEdit(['_options'=>['local'=>1],
			   'oid'=>$useroid,
			   'passwd_HID'=>$confirm,
			   'passwd'=>$password]);
    $passwords = \Seolan\Library\Opts::getOpt($useroid, $this->_moid, static::PASSWORDS_LIST_OPT);
    $hpassword = \Seolan\Field\Password\Password::hash($password);
    // memorisation mot de passe et date d'expiration
    while(count($passwords) > $this->passwordshistorysize -1){
      array_shift($passwords);
    }
    array_push($passwords, $hpassword);
    // mise à jour
    $opts = new \Seolan\Library\Opts();
    $opts->setOpt($useroid, $this->_moid, \Seolan\Module\User\User::PASSWORDS_LIST_OPT, $passwords);
    $opts->setOpt($useroid, $this->_moid, \Seolan\Module\User\User::PASSWORD_EXPIRE_OPT, date('Y-m-d'));
    \Seolan\Core\Logs::update('security', $useroid, "new password set");

    // calcul dates etc pour le mail
    $passwordexpiration = $this->getPasswordLifeTime($useroid);
    $dateexpire = $this->getPasswordExpirationDate($useroid, $passwordexpiration);
    // mail recap au user, normalement dans sa langue ?
    // "select *" pour avoir ou pas luser. alias, email, fullnam supposés présents
    $ors = getDB()->fetchRow('select * from '.$this->table.' where KOID=?', [$useroid]);
    if (isset($ors['luser'])){
      $_REQUEST['LANG_USER']=$ors['luser'];
      \Seolan\Core\Labels::reloadLabels();
    }
    $lang=\Seolan\Core\Shell::getLangUser();
    $fmt=\Seolan\Core\Lang::$locales[$lang]['date_format'];
    $dateexpireLocalized = date(\Seolan\Core\Lang::$locales[$lang]['date_format'], strtotime($dateexpire));
    $text=$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_SessionMessages','new_password_registration_text','mail');
    $subject=$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_SessionMessages','new_password_registration_subject', 'mail');
    $text=sprintf($text,$ors['fullnam'], $ors['alias'],$passwordexpiration,$dateexpireLocalized);
    if ($sendmail){
      $this->sendMail2User($subject,$text,$ors['email'],$this->passwordEmailSender,true);
      return [true, ''];
    } else {
      return [true, '', [$subject, $text, $this->sender], $ors];
    }
  }
  /**
   * Retourne la directory qui gère l'utlisateur ou null
   */
  public function getExclusiveUserDirectory($login){
    $dirsConf = static::getDirectoriesConfigurations();
    $dirsId = $dirsConf->directoriesId->toArray();
    foreach($dirsId as $dirid){
      $dir = \Seolan\Core\Directory\Directory::objectFactory($dirid, $dirsConf->$dirid);
      if (!$dir->isQualified($login) || !$dir->exclusiveUser($login))
	continue;
      return $dir;
    }
    return null;
  }
  /**
   * Paramètres pour la saisie d'un nouveau mot de passe (perte, changement)
   * en dehors de mon compte et des sections fonctions
   */
  function newPasswordInput($dirid, $useroid, $which){
    $dirsConf = static::getDirectoriesConfigurations();
    $dir = \Seolan\Core\Directory\Directory::objectFactory($dirid, $dirsConf->$dirid);
    if ($dir != null){
      return $dir->getUserManager()->prepareNewPasswordInput($useroid, $which);
    } 
    \Seolan\Core\Logs::notice(__METHOD__,"new password input rejected : no directory match '$alias'");
    return [false,'unable_to_reset_password'];
  }
  /**
   * Pour la saisie d'un changement de mot de passe 
   * -> chargement des infos du champ passwd de USERS
   */
  public function prepareNewPasswordInput(string $useroid, string $which, ?array $options=null):array{
    $fd = $this->getPasswordField();
    $fo=$fd->input(($default=null), ($foo=['labelin'=>true]));
    return [true, ['data'=>['opasswd'=>$fo],
		   'template'=>'Module/User.newpassword.html']];
  }
  /**
   * Récupération du champ passwd 
   */
  protected function getPasswordField(){
    $fd = $this->xset->getField('passwd');
    $fd->with_confirm=true;
    $fd->compulsory=true;
    if (empty($fd->edit_format)){
      $fd->edit_format = \Seolan\Field\Password\Password::PASSWORD_FORMAT;
      $fd->edit_format_text = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_SessionMessages', 'rgpd_password_format','text');
    }
    return $fd;
  }
  /**
   * Demande de nouveau mot de passe, à partir de l'annuaire du user
   * @param $login : user alias
   * @param $which : forgotten|expired
   * la demande n'est pas traitée si aucun annuaire exclusif n'est trouvé
   * des options peuvent être configurées au niveau directory.
   * par défaut (voir prepareNewPassword), options['mail']=true
   */
  function newPasswordRequest($login, $which):array{
    if (empty($login))
      return [false, null];
    $dir = $this->getExclusiveUserDirectory($login);
    if ($dir != null){
      $options = ['mail'=>true];
      if ($dir->getConfiguration()->get('config')->get('prepareNewPassword')) {
        $options = $dir->getConfiguration()->get('config')->get('prepareNewPassword')->toArray();
        return $dir->getUserManager()->prepareNewPassword($login, null, $which, $options);
      } else {
        // pour avoir les options par défaut
        return $dir->getUserManager()->prepareNewPassword($login, null, $which); 
      }
    } else {
      \Seolan\Core\Logs::notice(__METHOD__,"new password request rejected : no directory match '$alias'");
      return [false,'unable_to_reset_password'];
    }
  }
  /**
   * Traitement nouveau mot de passe (perdu, expiré)
   * -> existance du token + validité
   * -> tentative et mise à jour du mot de passe
   * -> nettoyage
   */
  function procNewPasswordRequest($ar=null):array{
    $p = new \Seolan\Core\Param($ar, []);
    $id = $p->get('id');
    list($mess, $token) = $GLOBALS['TZR_SESSION_MANAGER']::getToken($id);
    if ($token == null){
      return ['ok'=>false, 'message'=>$mess, 'params'=>['id'=>$token['id']]];
    }
    $alias = $token['alias'];
    $dir = $this->getExclusiveUserDirectory($alias);
    if ($dir == null){
      \Seolan\Core\Logs::critical(__METHOD__,"new password request rejected : no directory match '$alias'");
      return ['ok'=>false, 'message'=>'unable_to_reset_password'];
    }
    $ar['token'] = $token;
    $res = $dir->getUserManager()->procNewPassword($ar);
    $res['params']['id'] = $token['id'];
    return $res;
  }
  /**
   * demande de mot de passe depuis le FO (utilisateurs FO)
   * -> accessible par le web sur module directement
   * -> dediée au users FO (SF de gestion de comptes)
   * -> token + mail defini dans la SF (manageLogin)
   */
  function newPasswordRequest2($ar=null){
    $p = new \Seolan\Core\Param($ar, ['which' => 'forgotten']);
    $login = $p->get('login'); // = l'alias du user
    $onerror = $p->get('onerror');
    $loginalias = $p->get('loginalias'); // pour l'url du mail et next
    if (empty($loginalias))
      $loginalias=$onerror;
    if (!isset($login) || empty($login)){
      \Seolan\Core\Logs::notice(__METHOD__," no login provided");
      \Seolan\Core\Shell::setNext(makeUrl($onerror, []));
      return [false, '', ''];
    }
    $dir = $this->getExclusiveUserDirectory($login);
    $ret  = false;
    if ($dir != null){
      list($ok, $message, $tokenid) = $dir->getUserManager()->prepareNewPassword($login, null, $p->get('which'), ['mail'=>false]);
      // send email from SF parameters
      if ($ok && $p->is_set('_section')){
	list($token, $fooupd) = \Seolan\Core\DbIni::get($tokenid);
	list($itmoid, $itoid) = explode(',', $p->get('_section'));
	try{
	  $itModule = \Seolan\Core\Module\Module::objectFactory(['tplentry'=>TZR_RETURN_DATA,
								 'moid'=>$itmoid,
								 'interactive'=>false]);
	  if (!isset($itModule))
	    throw new \Seolan\Core\Exception\Exception("error moid $itmoid");
	  $oids = $itModule->_getOids($itoid);
	  list($oidit,$oiddest,$oidtemplate,$zone) = $oids;
	  $funcDetails = $itModule->getFullFunctionDetails($oiddest);
	  $functionFullQuery = $funcDetails['_fullquery'];
	  if (!isset($funcDetails))
	    throw new \Seolan\Core\Exception\Exception("error moid $itmoid $itoid");
	  // page de fond de mail par defaut ou dédiée
	  $mailoidit=$itModule->getOidFromAlias($functionFullQuery['__mailalias']);
	  if ($mailoidit == null){
	    throw new \Seolan\Core\Exception\Exception("no maillayout alias '{$functionFullQuery['__mailalias']}' found for module {$itModule->getLabel()} {$itModule->_moid}");
	  }
	  if (empty(trim($functionFullQuery['__mailtemplate']))){
	    throw new \Seolan\Core\Exception\Exception("no maillayout found for module {$itModule->getLabel()} {$itModule->_moid}");
	  }
	  $page = [$functionFullQuery['__mailalias'], $functionFullQuery['__mailtemplate'], $itModule->_moid];

	  // send mail with sf parameters
	  $du = $this->xset->rdisplay($token['oid']);
	  $sitetokenurl = makeUrl($GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().'/index.php?',
				  ['alias'=>$loginalias,
				   'id'=>$tokenid,
				   '_'=>date('ymdhis')]);

	  $mail = new \Seolan\Library\Mail();
	  $admins = explode(',', $functionFullQuery['__emailadmin']);

	  $mail->sendPrettyMail(\Seolan\Field\Label\Label::getLabelFromId($functionFullQuery['__mailpasswdsubject']),
				\Seolan\Field\Label\Label::getLabelFromId($functionFullQuery['__mailnewpasswdbody']),
				array_merge([['mail'=>$du['oemail']->raw, 'name'=>$du['ofullnam']->raw]], $admins),
				$admins[0],
				['sign'=>0,
				 'footer'=>\Seolan\Field\Label\Label::getLabelFromId($functionFullQuery['__mailpasswdsign']),
				 'page'=>$page,
				 'mtype'=>'useraccountrequest',
				 'tags'=>['oid'=>$du['oid'],
					  'sitetokenurl'=>$sitetokenurl]
				 ]
			    );

	  $message = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session','passwordrequest_sent');
    $ret  = true;
	} catch(\Exception $e){
	  \Seolan\Core\Logs::critical(__METHOD__,"new password request rejected : error loadind SF {$e->getMessage()}");
	  \Seolan\Core\Shell::setNext(makeUrl($onerror, ['message'=>$message]));
	  $message  = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session', 'unable_to_reset_password');
	}
      } else {
	\Seolan\Core\Logs::notice(__METHOD__,"unable to prepare for new password for '$login' : $message");
      }
    } else {
      \Seolan\Core\Logs::notice(__METHOD__,"new password request rejected : no directory match '$login'");
      $message = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session', 'unable_to_reset_password');
    }
    if (empty($message))
      $message  = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session', 'unable_to_reset_password');
    \Seolan\Core\Logs::notice(__METHOD__, "$login $message");
    \Seolan\Core\Shell::setNext(makeUrl($onerror, ['message'=>$message]));
    
    return [$ret, $message, $tokenid];
  }
  /**
   * reset mot de passe depuis le FO (utilisateurs FO)
   * -> accessible sur le module directement depuis une SF
   * -> encapsulation mot de passe user FO et local
   * -> sans utilisation des directories
   * -> surcharge du mail
   */
  function procNewPasswordRequest2($ar=null){
    $p = new \Seolan\Core\Param($ar, []);
    $next = $p->get('_next');
    $onerror = $p->get('onerror');
    list($itmoid, $itoid) = explode(',', $p->get('_section'));
    // lecture du paramétrage de la section d'origine
    $itModule = \Seolan\Core\Module\Module::objectFactory(['tplentry'=>TZR_RETURN_DATA,
							 'moid'=>$itmoid,
							 'interactive'=>false]);
    $oids = $itModule->_getOids($itoid);
    list($oidit,$oiddest,$oidtemplate,$zone) = $oids;
    $funcDetails = $itModule->getFullFunctionDetails($oiddest);
    if (empty($funcDetails)){
      \Seolan\Core\Logs::critical(__METHOD__,"unable to load parameters $oiddest");
      \Seolan\Core\Shell::setNext('');
      return;
    }
    // page de fond de mail par defaut ou dédiée
    $mailoidit=$itModule->getOidFromAlias($funcDetails['_fullquery']['__mailalias']);
    if ($mailoidit == null){
      \Seolan\Core\Logs::critical(__METHOD__,"no maillayout '{$funcDetails['_fullquery']['__mailalias']}' found for module {$itModule->getLabel()} {$itModule->_moid}");
      return;
    }
    if (empty(trim($funcDetails['_fullquery']['__mailtemplate']))){
      \Seolan\Core\Logs::critical(__METHOD__,"no maillayout found for module {$itModule->getLabel()} {$itModule->_moid}");
      return;
    }
    $page = [$funcDetails['_fullquery']['__mailalias'], $funcDetails['_fullquery']['__mailtemplate'], $itModule->_moid];

    list($mess, $token) = $GLOBALS['TZR_SESSION_MANAGER']::getToken($p->get('id'));
    
    if ($token == null){
      \Seolan\Core\Shell::setNext(makeUrl($onerror, ['message'=>$mess,
						     '_'=>date('ymdhis')]));
      return;
    }

    // controle et enregistrement du mot de passe
    $passwd = $p->get('passwd');
    $passwdConfirm = $p->get('passwd_HID');
    if( $passwd == null ){ // Pour la compatibilité avec les anciens noms de champ
      $passwd = $p->get('password');
      $passwdConfirm = $p->get('password_confirm');
    }
    $fieldPasswd = \Seolan\Core\Field\Field::objectFactory2($this->table, 'passwd');
    if ( !$fieldPasswd->with_confirm){
      $passwdConfirm = $passwd;
    }
    list($ok,$message,$mail,$userors) = $this->changePassword($token['useroid'],
							       $passwd,
							       $passwdConfirm,
							       false
			       );
    
    if ($ok){

      // effacer le token
      \Seolan\Core\DbIni::clear($token['id'], false);
      
      $functionFullQuery = $funcDetails['_fullquery'];
      
      \Seolan\Core\Shell::setNext(makeUrl($next, ['login'=>$token['alias'],
						  'message'=>$message,
						  '_'=>date('ymdhis')]));
      
      $du = $this->xset->rdisplay($token['useroid']);

      $mail = $this->getMailer();
      $admins = explode(',', $functionFullQuery['__emailadmin']);

      $mail->sendPrettyMail(\Seolan\Field\Label\Label::getLabelFromId($functionFullQuery['__mailpasswdsubject']),
			    \Seolan\Field\Label\Label::getLabelFromId($functionFullQuery['__mailpasswdbody']),
			    array_merge([['mail'=>$du['oemail']->raw, 'name'=>$du['ofullnam']->raw]], $admins),
			    $admins[0],
			    ['sign'=>0,
			     'footer'=>\Seolan\Field\Label\Label::getLabelFromId($functionFullQuery['__mailpasswdsign']),
			     'page'=>$page,
			     'mtype'=>'useraccountrequest',
			     'tags'=>['oid'=>$du['oid']]]
			    );

    } else {
      \Seolan\Core\Shell::setNext(makeUrl($onerror, ['message'=>$message,
						     '_'=>date('ymdhis')]));
    }
  }
  /**
   * enregistrement d'un mot de passe
   */
  function procNewPassword($ar=null):array{
    $p = new \Seolan\Core\Param($ar, ['sendmail'=>true]);
    $token = $p->get('token','local');
    if ($p->is_set('password') || $p->is_set('password_confirm')){ // legacy auth.html
      $password = $p->get('password');
      $confirm = $p->get('password_confirm');
      \Seolan\Core\Logs::notice(__METHOD__,'use of password and password confirm instead of passwd in USERS DataSource');
    } else {
      $fd = $this->getPasswordField();
      $password = $p->get($fd->field);
      $confirm = $p->get("{$fd->field}_HID");
    }
    list($ok, $message) = $this->changePassword($token['useroid'],
						$password,
						$confirm,
						$p->get('sendmail','local')
						);
    if ($ok){
      \Seolan\Core\DbIni::clear($token['id'], false);
      $message = 'password_registered_login';
    }
    return ['ok'=>$ok, 'message'=>$message, 'alias'=>$token['alias']];
  }
  /// champs editable de la dir locale
  function getAccountFieldssec():?array{
    return null;
  }
  /**
   * send token to reset password.
   * @param $alias string
   * @param $useroid string
   * @param $which : 'forgotten', 'exprired', 'initaccount'
   * @param $options : mail=>true : send mail anyway, mail=>false : no mail
   * @return array true/false, message/label, token
   */
  function prepareNewPassword($alias=null,$useroid=null, $which, $options=['mail'=>true]):array{
    $ok = false;
    $message = null;
    if ($useroid != null){
      $ors=getDB()->fetchRow('select * from USERS where KOID=?',[$useroid]);
      $alias = $ors['alias'];
    } else {
      $ors=getDB()->fetchRow('select * from USERS where alias=?',[$alias]);
    }
    if ($ors && $ors['directoryname'] !== 'local' && !$options['nodircheck']) {
      return [false, \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session','remote_password_request_message')];
    }
    if(!$ors || empty($ors['email'])) {
      return [false, \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session','unknown_alias'), null];
    }
    $conds = $this->activeUsersConds();
    if (count($conds)>=1){
      $active = getDB()->fetchOne('select 1 from USERS where KOID=? AND '.implode(' AND ', $conds), [$ors['KOID']]);
      if (!$active)
	return [false, \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session','invalid_period')];
    }
    if ($options['mail']){
      if($ors && !empty($ors['email'])) {
        if(array_key_exists('luser',$ors)){
          \Seolan\core\Shell::setLang($ors['luser']);
          \Seolan\Core\Labels::reloadLabels();
        }
      }
      if ($which == 'forgotten'){
        $messok = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session','passwordrequest_sent');
        $messko = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session','passwordrequest_nosent');
        $mailtext = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_SessionMessages','lost_password_msg','mail');
        $mailsubject = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_SessionMessages','lost_password_sub','mail');
      } else if($which == 'expired'){
        list($sendSF, $msg, $token) = $this->newPasswordRequest2(['which' => $which]);
        if ($sendSF) {
          return [$sendSF, $msg, $token];
        }
        
        $messok = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session','passwordexpire_sent');
        $messko = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Session','passwordrequest_nosent');
        $mailtext = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_SessionMessages','expire_password_msg','mail');
        $mailsubject = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_SessionMessages','expire_password_sub','mail');
      } else {
        throw new \Exception("unknown newPassword mode");
        return [false, null, null];
      }
      if(!empty($ors['email'])) {
	$id = static::createPasswordToken($alias, $which, $ors['KOID']);
	
   	if (!isset($options['loginurl'])){
      	  $url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(false,true).http_build_query(['class'=>$GLOBALS['TZR_SESSION_MANAGER'],
      											      'function'=>'auth',
      											      'id'=>$id,
      											      '_lang'=>\Seolan\Core\Shell::getLangUser()]);
        } else {
          $url = makeUrl($options['loginurl'], ['id'=>$id]);
        }
        $text=sprintf($mailtext,$alias,$url);
        $subject=$mailsubject;
        $GLOBALS['XUSER']->sendMail2User($subject, $text,$ors['email'],$this->passwordEmailSender,true);
        if(array_key_exists('luser',$ors)){
          \Seolan\Core\Shell::unsetLang();
          \Seolan\Core\Labels::reloadLabels();
        }
        $ok=true;
      }
    } else { // sans envoi de mail
      $id = static::createPasswordToken($alias, $which, $ors['KOID']);
      $ok = true;
    }
    if($ok){
      $message = $messok;
    }else{
      $message = $messko;
    }
    return [$ok, $message, $id];
  }
  /**
   * enregistrement d'un token de mot de passe
   */
  protected static function createPasswordToken($alias, $which, $oid){
    $id=uniqid('PASSWORD');
    \Seolan\Core\Logs::notice(__METHOD__,"$id : $alias, $which, $oid");
    \Seolan\Core\DbIni::set($id, ['alias'=>$alias, 'which'=>$which, 'oid'=>$oid]);
    return $id;
  }
  /**
   * Initialise le processus de saisie du nouveau mot de passe pour les utilisateurs sélectionnés
   */
  function sendPasswords($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $_selected=$p->get('_selected');
    if(empty($_selected)) $_selected=array($p->get('oid')=>1);
    $fields=array('KOID','alias','fullnam','email');

    $actlangdata=\Seolan\Core\Shell::getLangData();
    $actreqlangdata=$_REQUEST['LANG_DATA'];
    $actreqlanguser=$_REQUEST['LANG_USER'];

    $sessionManager = new $GLOBALS['TZR_SESSION_MANAGER'];
    $nb = $nbsent = 0;
    $invalids = [] ;
    foreach($_selected as $oid=>$foo) {
      $nb++;
      if ($this->isUserValid($oid)){
	$alias=$ors['alias'];
	list($ok,$message) = $this->prepareNewPassword(null,$oid, 'forgotten');
	if ($ok){
	  $nbsent ++;
	  }
      } else {
	$invalids[] = $oid;
      }
    }

    // repositionne les langues qui ont pu changer lors de l'envoi des mails
    \Seolan\Core\Shell::getLangData($actlangdata,true);
    $_REQUEST['LANG_DATA']=$actreqlangdata;
    $_REQUEST['LANG_USER']=$actreqlanguser;
    \Seolan\Core\Labels::reloadLabels();
    $message = "$nb user(s) selected, $nbsent mail(s) sent.";
    if (!empty($invalids)){
      $aliases = getDB()->fetchCol('select distinct alias from USERS where KOID in ('.implode(',',array_fill(0, count($invalids),'?')).')',$invalids);
      $message .= '<br>"'.implode('","', $aliases).'" ignored (invalid user).';
    }
    setSessionVar('message', $message);
  }
  /// is user valid according to table fields configuration
  public function isUserValid($oid){
    $condsactifs = $this->activeUsersConds(false);
    if (!empty($condactifs)){
      $condactiveuser = ' AND ('.implode(' AND ', $condsactifs).')';
    } else {
	$condactiveuser = '';
    }
    return (getDB()->fetchOne("select 1 from {$this->xset->getTable()} where KOID=? {$condactiveuser}", [$oid])=='1');
  }
  protected function _actionlist(&$my, $alfunction=true) {
    parent::_actionlist($my);
    $moid=$this->_moid;
    $oid=@$_REQUEST['oid'];
    $uniqid=\Seolan\Core\Shell::uniqid();
    if($this->secure('','sendPasswords')) {
      if(in_array(\Seolan\Core\Shell::_function(),array('edit','display'))) {
        $o1=new \Seolan\Core\Module\Action($this,'sendaccount',\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','sendaccount','text'),
			      '&moid='.$moid.'&oid='.$oid.'&_function=sendPasswords&template=Core.message.html&tplentry=br','edit');
        $o1->menuable=true;
        $my['sendaccount']=$o1;
      }elseif(in_array(\Seolan\Core\Shell::_function(),array('browse','procQuery'))) {
        $message=addslashes(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','operation_succeeded','text'));
        $o1=new \Seolan\Core\Module\Action($this,'sendaccount',\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','sendaccount','text'),
			      'javascript:TZR.applySelected("sendPasswords",document.browse'.$uniqid.',"'.$message.'",'.
			      '"Core.message.html",0,"'.addslashes(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','error_select_object','text')).'");',
			      'edit');
        $o1->menuable=true;
        $my['sendaccount']=$o1;
      }
    }
    $goid=\Seolan\Core\Module\Module::getMoid(XMODGROUP_TOID);
    $ri=\Seolan\Core\User::secure8maxlevel($goid);
    if(in_array($ri, array('admin', 'rwv', 'rw', 'ro'))){
      $o1=new \Seolan\Core\Module\Action($this,'groups',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Group_Group','groups','text'),
			    '&function=browse&moid='.$goid.'&template=Module/Table.browse.html&tplentry=br','display');
      $o1->menuable=true;
      $o1->setToolbar('Seolan_Module_Group_Group','groups');
      $my['groups']=$o1;
    }
  }

  function al_browse(&$my){
    parent::al_browse($my);
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    if($this->secure('','editSec')){
      $o1=new \Seolan\Core\Module\Action($this,'editsec',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','security','text'),
			    'javascript:'.$uniqid.'.applyfunction("editSec","",{template:"Module/User.secedit.html"},true,true);','edit');
      $o1->order=4;
      $o1->setToolbar('Seolan_Core_General','security');
      $my['editsec']=$o1;
    }
  }
  /**
   * reset du user substitué
   */
  function setbackuid($ar) {
    $this->setuid($ar);

  }
  /**
   * substitution du user en cours
   */
  function setuid($ar) {
    $p=new \Seolan\Core\Param($ar,['oid'=>null]);
    $oid=$p->get('oid');
    $c = $GLOBALS['TZR_SESSION_MANAGER'];
    $sess = new $c();
    $sess->substituteUser($oid);
  }
  /* Gestion de la sélection */
  /**
   * élements dans la sélection
   */
  protected function consolidateSelection(){
    $sel=getSessionVar('user_selection');
    $nbitems = 0;
    if(is_array($sel))
      array_walk($sel,  function($item, $key) use(&$nbitems){
	  $nbitems += @count($item);
	});
    setSessionVar('user_selection_items', $nbitems);
  }
  /// Ajoute des données à la sélection
  function addToSelection($moid,$data){
    \Seolan\Core\Logs::debug(__METHOD__.' '.$moid);
    $sel=getSessionVar('user_selection');
    if(empty($sel[$moid])) $sel[$moid]=array();
    $sel[$moid]=array_merge($sel[$moid],$data);
    $selModInfo=getSessionVar('user_selection_modnames');
    if(empty($selModInfo)) $selModInfo=array();
    if (empty($selModInfo[$moid])) {
      $selModInfo[$moid] = \Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid,'tplentry'=>TZR_RETURN_DATA))->getLabel();
    }
    setSessionVar('user_selection',$sel);
    setSessionVar('user_selection_modnames',$selModInfo);
    $this->consolidateSelection();
  }

  //Vide la sélection
  function emptySelection($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $moid = $p->get('selmoid',false);
    if (empty($moid)) {
      setSessionVar('user_selection',array());
      setSessionVar('user_selection_modnames',array());
      $this->consolidateSelection();
      return;
    }
    $sel=getSessionVar('user_selection');
    $selModNames=getSessionVar('user_selection_modnames');
    if (!is_array($moid)){
      $moid = [$moid];
    }
    foreach($moid as $amoid){
      if (!empty($sel[$amoid])) unset($sel[$amoid]);
      if (!empty($selModNames[$amoid])) unset($selModNames[$amoid]);
    }
    if (empty($sel)) $sel=array();
    if (empty($selModNames)) $selModNames = [];
    $nbitems = 0;
    array_walk($sel,  function($item, $key) use(&$nbitems){
      $nbitems += @count($item);
    });
    setSessionVar('user_selection',$sel);
    setSessionVar('user_selection_modnames',$selModNames);
    $this->consolidateSelection();
  }
  /// Supprime l'intégralité des données d'un module de la sélection
  function clearToSelection($moid){
    $sel=getSessionVar('user_selection');
    unset($sel[$moid]);
    setSessionVar('user_selection',$sel);
  }
  /// Supprime des données de la selection
  function delToSelection($moid,$data){
    $sel=getSessionVar('user_selection');
    foreach($data as $oid=>&$foo){
      unset($sel[$moid][$oid]);
    }
    if(empty($sel[$moid])) unset($sel[$moid]);
    setSessionVar('user_selection',$sel);
    $this->consolidateSelection();
  }
  /**
   * Vérifier que les objets en session sont toujours accessibles ?
   */
  function refreshSelection(){
    $this->consolidateSelection();
  }
  function browseSelection($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $moid=$p->get('_moid');
    $tplentry=$p->get('tplentry');
    $details=$p->get('details');
    $sel=getSessionVar('user_selection');

    if(!is_array($sel)) {
      if (!is_array($sel[$moid])) {
        unset($sel[$moid]);
        setSessionVar('user_selection',$sel);
	$this->consolidateSelection();
      }
      return;
    }
    $mod=\Seolan\Core\Module\Module::objectFactory(['moid'=>$moid,'tplentry'=>TZR_RETURN_DATA, 'interactive'=>false]);
    $br = $mod->browseUserSelection(['tplentry'=>TZR_RETURN_DATA]);
    if(empty($br['_count'])){
      unset($sel[$moid]);
      setSessionVar('user_selection',$sel);
      $this->consolidateSelection();
      return;
    }
    $br['_actions']=$mod->userSelectionActions();
    $result=$br;
    return \Seolan\Core\Shell::toScreen2($tplentry,'selection',$result);
  }

  /// Rend l'accessibilite du module avec l'oid donne
  function secure($oid, string $func, $user=NULL, $lang=TZR_DEFAULT_LANG) {
    if(($func=='requestAnAccount' || $func=='procRequestAnAccount') && !$this->account_request) return false;
    if($oid==TZR_USERID_ROOT && !\Seolan\Core\Shell::isRoot() && !$this->secGroups($func,'ro')) return false;
    return parent::secure($oid,$func,$user,$lang);
  }
  /**
   * daemon
   */
  protected function _daemon($period='any'){
    parent::_daemon($period);
    if ($period == 'any'){
      $this->processUserNotifications();
      $this->cleanToken();
      $this->checkDirectoriesSynchro();
    }
    if ($period == 'daily'){
      $this->checkDirectoriesSynchro();
    }
  }
  /**
   * vérification que les tâches de synchro existent
   */
  protected function checkDirectoriesSynchro(){
    $dirsConf = static::getDirectoriesConfigurations();
    $cron = json_encode(['period'=>'daily','freq'=>'*/4']);
    $status = 'cron';
    $syncfunc = 'directorySynchronization';
    foreach($dirsConf->directoriesId as $dirid){
      $conf = $dirsConf->$dirid;
      if (is_subclass_of($conf->classname, \Seolan\Core\Directory\LocalDirectory::class) || $conf->classname == '\Seolan\Core\Directory\LocalDirectory')
	continue;

      if ($conf->disableautosync)
	continue;

      $ok = getDB()->fetchOne('select 1 from TASKS where status=? and amoid=? and json_value(more, \'$.function\')=? and json_value(more, \'$.directoryname\')=?',[$status,$this->_moid,$syncfunc,$dirid]);
      if (!$ok){
	\Seolan\Core\Logs::notice(__METHOD__,' add synch. task or directory '.$dirid);
	$more = json_encode(['uid'=>TZR_USERID_ROOT,
		     'function'=>$syncfunc,
		     'directoryname'=>$dirid]);

	$scheduler = \Seolan\Core\Module\Module::objectFactory(['toid'=>XMODSCHEDULER_TOID]);
	$scheduler->xset->procInput(['amoid'=>$this->_moid,
				     'ptime'=>date('Y-m-d'),
				     'title'=>"System - synchro for {$conf->label} ($dirid)",
				     'status'=>$status,
				     'more'=>$more,
				     'cron'=>$cron,
				     "tplentry"=>TZR_RETURN_DATA]);
      }
    }
  }
  /**
   * synchronisation d'un annuaire
   */
  public function directorySynchronization($scheduler=null, $o, $more){
    $dirid = $more->directoryname;
    if (empty($dirid))
      return;
    $dir = \Seolan\Core\Directory\Directory::objectFactory($dirid);

    $comment = $dir->synchronize();

    if ($scheduler != null)
      $scheduler->setStatusJob($o->KOID, 'finished', $comment);

  }
  /**
   * nettoyage des token de mot de passe périmés
   */
  function cleanToken(){
    $dlim=date('Y-m-d H:i:s',strtotime('-2 day')); // petite marge, délai de 1 jour dans Session::getToken
    $names = getDB()->fetchCol('select name from _VARS where upd<? and name like "PASSWORD%"', [$dlim]);
    foreach($names as $name){
      list($value, $upd) = \Seolan\Core\DbIni::get($name);
      if (isset($value['which']) && in_array($value['which'], ['forgotten','expired'])){
	\Seolan\Core\DbIni::clear($name);
      }
    }

  }
  /**
   * process user notifications
   */
  protected function processUserNotifications(){
    $varnames = getDB()->fetchCol('SELECT name FROM _VARS WHERE ACTIVE=1 and name LIKE "objectupdateusernotification::%"');
    $recipients = [];
    \Seolan\Core\Logs::debug(__METHOD__.'object update notifications '.count($varnames));
    foreach($varnames as $vname){
      list($var, $upd) = \Seolan\Core\DbIni::get($vname);

      \Seolan\Core\DbIni::clear($vname, false);

      if (!\Seolan\Core\Kernel::objectExists($var['oid'])){
	continue;
      }

      $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$var['table']);
      $do = $ds->rdisplay($var['oid'], null, false);

      if (isset($do['o'.$var['field']]->subscollection)){
	$fieldvalue = $do['o'.$var['field']];
	$subscollection = array_unique($fieldvalue->subscollection);

	if (count($subscollection)>0){
	  foreach($subscollection as $uid){
	    if (\Seolan\Core\User::secure8('goto1', $var['moid'], $var['oid'], $uid)){
	      if (!isset($recipients[$uid])){
		$recipients[$uid] = getDB()->fetchRow('select fullnam, email from '.$this->table.' where KOID=?', [$uid]);
	      }
	      if (isset($recipients[$uid])){
		$this->sendUserNotification($var['oid'], $do['link'], $fieldvalue->html, $var['from'], $recipients[$uid], $var['moid']);
	      } else {
		\Seolan\Core\Logs::notice(__METHOD__." unknown user uid : $uid");
	      }
	    }
	  }
	}
      }
    }
  }
  /**
   * send user notification
   */
  function sendUserNotification($oid, $title, $message, $from, $recipient, $moid){

    $tpldata = ['recipient'=>['email'=>$recipient['email'],
			      'name'=>$recipient['fullnam']],
		'document'=>['text'=>$message,'title'=>$title],
		'br'=>['moid'=>$moid,
		       'gotourl'=>$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'&moid='.$moid.'&function=goto1&oid='.$oid.'&_direct=1',
		       'oid'=>$oid]
		];
    $rawdata = [];

    $xt = new \Seolan\Core\Template('Core/Module.sendusernotification.html');

    $message = \Seolan\Library\Mail::normalizeBoLinks($xt->parse($tpldata, $rawdata), true);

    $this->sendMail2User(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','usernotifications_subject'),
			 $message,
			 ['mail'=>$recipient['email'], 'name'=>$recipient['fullnam']],
			 ['mail'=>$from['email'], 'name'=>$from['name']],
			 true, // archive
			 null, // filename
			 null, // filetitle
			 null, // stringattachment
			 null, // mime
			 ['mtype'=>'objectupdateusernotification']
			 );
  }
  /**
   * register a user notification to be processed
   */
  public static function addUserNotification($from, $recipients, $moid, $table, $field, $oid, $event){
    \Seolan\Core\DbIni::set('objectupdateusernotification::'.$moid.'::'.$field.'::'.$oid,
			    ['oid'=>$oid,
			     'recipients'=>$recipients,
			     'from'=>$from,
			     'table'=>$table,
			     'moid'=>$moid,
			     'event'=>$event,
			     'field'=>$field]
			    );
  }
  /**
   * add information about directories configuration
   */
  function getInfos($ar=null){
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>TZR_RETURN_DATA));
    $tplentry=$p->get('tplentry');
    $ret = parent::getInfos($ar);
    $conf = static::getDirectoriesConfigurations();
    $ret['infos']['directories'] = (object)['label'=>'Users directories',
					    'html'=>count($conf->directoriesId)];
    foreach($conf->directoriesId as $dirid){
      $nb = getDB()->fetchOne('select count(*) from USERS where directoryname=?', [$dirid]);
      $html = '<ul><li>users : '.$nb.
	'</li><li>class : '.$conf->$dirid->classname.
	'</li><li>login filter : '.$conf->$dirid->config->loginFilter.
	'</li><li>exclusive filter :'.$conf->$dirid->config->exclusiveFilter.
	'</li>';
      $ret['infos']['directories'.$dirid] = (object)['label'=>$conf->$dirid->label." ($dirid)",
						     'html'=>$html];
    }

    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /**
   * Directories credentials input configuration, when exists
   * @todo : filtrer selon la prop de conf logintype BO FO both et admini
   */
  public static function prepareAuth($tplentry){
    $dirsConf = static::getDirectoriesConfigurations();
    $dirsId = $dirsConf->directoriesId->toArray();
    $dirs = ['list'=>[]];
    foreach($dirsId as $dirid){
      $dir = \Seolan\Core\Directory\Directory::objectFactory($dirid, $dirsConf->$dirid);
      $data = $dir->manageLoginCredentialUI();
      if ($data != null){
	$dirs['list'][] = ['id'=>md5($dirid), 'data'=>$data];
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry, $dirs);
  }
  /**
   * ordered list of user directories (local, remote, ...)
   * used for user authentication
   * skip directories with false order
   */
  public static function getDirectoriesConfigurations(){
    return \Seolan\Core\Directory\Directory::getConfigurations();
  }
  /**
   * main authentication method for console usage
   * authenticate user using configured directories
   * verify and create/update shadow user associated
   */
  public function procAuth($ar=[]){
    $p=new \Seolan\Core\Param($ar, ['oauth'=>['directoryid'=>''],'login'=>'']);
    $alias=trim($p->get('login'));
    $password=$p->get('password');
    $oauth = $p->get('oauth');

    // iterate through configured direcories to authenticate the login
    $dirsConf = static::getDirectoriesConfigurations();
    $dirsId = $dirsConf->directoriesId->toArray();
    $authenticated = $rejected = false;
    $authres = null;
    $useddir = null;
    $debug = [];
    $user = null;
    while(!$authenticated && !$rejected){

      $dirid = array_shift($dirsId);

      if ($dirid == null){
	$rejected = true;
	continue;
      }

      $dir = \Seolan\Core\Directory\Directory::objectFactory($dirid, $dirsConf->$dirid);
      // !! directoriesId must ensure OAuth directories are first iterated
      if ($dir instanceof \Seolan\Core\Directory\OAuth){

	$qualifiedCrit = $oauth['directoryid'];
	if (!$dir->isQualified($qualifiedCrit)){
	  $debug[] = $dir->id.':not qualified';
	  continue;
	}
	$authadapter = $dir->getOAuthAuthenticateAdapter($oauth);
      } else {
	$qualifiedCrit = $alias;
	if (!$dir->isQualified($qualifiedCrit)){
	  $debug[] = $dir->id.':not qualified';
	  continue;
	}
	$authadapter = $dir->getAuthenticateAdapter($alias, $password);
      }

      $authres = $authadapter->authenticate();

      if (!$authres->isValid()){
	$messages = $authres->getMessages()??[];
	$debug[] = $dir->id.':login invalid ('.implode(',',$messages).')';
	if ($dir->exclusiveUser($qualifiedCrit)){
	  $rejected = true;
	  $useddir = $dir;
	  $debug[] = $dir->id.':exclusive';
	  continue;
	}
      } else{
	$debug[] = $dir->id.':login ok';
	$authenticated = true;
	$messages = [];
	$useddir = $dir;
      }
    }
    \Seolan\Core\Logs::debug(__METHOD__.' '.implode(',', $debug));
    if ($rejected && $dirid)
      $messages[] = 'login_rejected';
    if ($rejected && !$dirid)
      $messages[] = 'login_unqualifed';

    if ($authenticated){
      $user = $this->getAuthenticatedUser($authres, $authadapter, $dir);
      if ($user == null){
	$authenticated = false;
	$debug[] = implode(',', $messages);
	$debug[] = 'shadow user error';
	$messages = ['login_not_accepted'];
      } else {
        $user->_directory = $dir;
        $user->_authenticateAdapter = $authadapter;
      }

      // traitement postlogin ok (exemple : mot de passe expiré)
      list($ok, $message) = $dir->postAuthenticationCheck($user);
      if (!$ok){
	$authenticated = false;
	$debug[] = implode(',', $messages);
	$messages = [$message];
      }

      \Seolan\Core\Logs::update('security', $user->uid(), $dir->id.'/'.$alias.'/'.implode(',',$debug));

    } else {
      // à voir si le tracage des login error est une bonne chose
      \Seolan\Core\Logs::update('security', 0, '"'.$alias.'" login error : '.implode(',', $messages).", details : ".implode(',', $debug));
    }
    
    if (strpos($_SERVER['HTTP_USER_AGENT'], TZR_USER_AGENT_MOBILE_APP) !== false && $authenticated) {
      $deviceId = null;
      if ($p->is_set('device_id')) {
        $deviceId = $p->get('device_id');
      } else {
        if (function_exists('apache_request_headers')) {
          $headers = apache_request_headers();
          if (!empty($headers['X-Seolan-Device-Id'])) {
            $deviceId = $headers['X-Seolan-Device-Id'];
          }
        }
      }
      
      $mod = \Seolan\Core\Module\Module::singletonFactory(XMODPUSHNOTIFICATIONDEVICE_TOID);
      if ($mod) {
        $mod->associateDeviceToUser($user->_curoid, $deviceId);
      }
    }
    
    return ['ok'=>$authenticated, 'messages'=>$messages, 'directory'=>$dir, 'user'=>$user, 'debug'=>implode(',', $debug)];

  }
  /**
   * verify shadow user configuration using directory, authentication adapter, authentication result
   * authentication adapter may provide authenticated user properties / data
   * if not directory method must be used (todo ?)
   * if $directory is an instance of \Seolan\Core\Directory\LocalDirectory the authenticated user is returned
   */
  protected function getAuthenticatedUser(\Zend\Authentication\Result $authres,
					  \Zend\Authentication\Adapter\AdapterInterface $authadapter,
					  \Seolan\Core\Directory\Directory $directory):?\Seolan\Core\User{

    if (is_a($directory, \Seolan\Core\Directory\LocalDirectory::class)){
      \Seolan\Core\Logs::notice(__METHOD__, get_class($directory).' is local');
      return $authadapter->getAccountObject();
    }
    $accountObject = null;
    $userData = null;
    if (method_exists($authadapter, 'getAccountObject')){
      $accountObject = $authadapter->getAccountObject();
    }
    if ($accountObject != null){
      $userData = $directory->formatAccountData($accountObject);
      // search with user oid if directory provide it otherwhise with alias
      if (isset($userData['oid'])){ // directory set uid,
	$userOrs = getDB()->fetchRow('select * from '.$this->xset->getTable().' where KOID=? and LANG=? and directoryname=?', [$userData['oid'],TZR_DEFAULT_LANG,$directory->id]);
      } else {
	$identity = $authres->getIdentity(); // = alias, login
	$userOrs = getDB()->fetchRow('select * from '.$this->xset->getTable().' where alias=? and LANG=? and directoryname=?', [$identity,TZR_DEFAULT_LANG, $directory->id]);
      }
      if (!$directory->isUptoDate($userData, $userOrs)){
	\Seolan\Core\Logs::notice(__METHOD__,"create/update shadow user {$directory->id} {$userData['alias']}");
	list($status, $uid, $messages) = $directory->updateUser($userData);
      } else {
	$uid = $userOrs['KOID'];
      }
      return new \Seolan\Core\User($uid);
    }
    // unable to instanciate user
    return null;
  }
  /**
   * conditions that define actives users according to users fields
   */
  public function activeUsersConds($bo=true) : array {
    if (!isset($this->_condactiveuser)){
      $this->_condactiveuser = [];
      if($this->xset->fieldExists('PUBLISH'))
	$this->_condactiveuser[] = "PUBLISH='1'";
      if($bo && \Seolan\Core\Shell::admini_mode() && $this->xset->fieldExists('BO'))
	$this->_condactiveuser[] = "BO='1' ";
      if ($this->xset->fieldExists('DATEF') && $this->xset->fieldExists('DATET'))
	$this->_condactiveuser[] = 'DATEF<=NOW() AND DATET>=NOW()';
    }
    return $this->_condactiveuser;
  }
  /**
   * raw pseudo authentication for known user (shadow or local)
   */
  public function getActiveUser($uid){
    if ($uid !== TZR_USERID_ROOT)
      $conds = $this->activeUsersConds();
    else
      $conds = [];
    $conds[] = 'KOID=?';
    $cond = implode(' AND ', $conds);
    return getDB()->fetchRow('select * from '.$this->xset->getTable().' where '.$cond, [$uid]);
  }
  /**
   * raw pseudo authentication for known user (shadow or local)
   */
  public function getActiveUsers($field='alias'){
    $conds = $this->activeUsersConds();
    $cond = implode(' AND ', $conds);
    return getDB()->fetchCol('select distinct '.$field.' from '.$this->xset->getTable().' where '.$cond, [$uid]);
  }
  /**
   * method to authenticate users against locals users
   * both used by remote authentication, local authentication
   * @todo : withcas (encodepassword),
   */
  public function authenticate($alias, $password,$email=false){
    if ($email)
      $rs=getDB()->select('select * from USERS where LANG=? and (alias=? or email=?)', [TZR_DEFAULT_LANG, $alias,$alias]);
    else
      $rs=getDB()->select('select * from USERS where LANG=? and alias=? ', [TZR_DEFAULT_LANG, $alias]);


    if ($rs && $rs->rowCount() == 1){
      $messagecodes = [];
      $ok = true;
      $ors = $rs->fetch();
      if($this->xset->fieldExists('PUBLISH') && ((int)$ors['PUBLISH']) !== 1){
	$ok = false;
	$messagecodes[] = 'unpublished_user';
      }
      if(\Seolan\Core\Shell::admini_mode() && $this->xset->fieldExists('BO')
	 && ((int)$ors['BO']) !== 1){
	$ok = false;
	$messagecodes[] = 'bo_access_forbiden';
      }
      if ($this->xset->fieldExists('DATEF') && $this->xset->fieldExists('DATET')){
	$now = date('Y-m-d');
	if ($ors['DATEF'] > $now || $ors['DATET'] < $now){
	  $ok = false;
	  $messagecodes[] = 'invalid_period';
	}
      }
      $password_verify=NULL;
      // à terme : $password_verify = password_verify($password, $ors['password']);
      if (!$password_verify &&
	  !hash_equals($ors['passwd'], hash('sha256', $password))){
	$ok = false;
	$messagecodes[] = 'invalid_credential';
      }
      if ($ok){
	$user = new \Seolan\Core\User($ors['KOID']);
      } else {
	$user = null;
      }
    } else {
      if ($rs->rowCount() > 1){
	\Seolan\Core\Logs::critical(__METHOD__," authentication error $alias not not unique");
      }
      $ok = false;
      $messagecodes[] = 'unknown_user';
      $user = null;
    }
    \Seolan\Core\Logs::notice(__METHOD__,$ok.' '.implode(',', $messagecodes));
    return [$ok, $messagecodes, $user];
  }
  /**
   * remote authentication used by Core\Directory\RemoteDirectory for authentication
   * must be actiated by module conf.
   * users in TZR_REMOTEACCESS_GRP group are the only allowed to use the service
   */
  public function remoteAuthentication($ar=null){
    if (!$this->remoteauthenticationactive){
      \Seolan\Core\Logs::critical(__METHOD__, 'remote authentication attempt but not activated');
      throw new \Exception('remote authentication not allowed');
    }
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS']!='on'){
      \Seolan\Core\Logs::critical(__METHOD__, 'remote authentication attempt over http');
      throw new \Exception('remote authentication attempt over http');
    }

    $rawData = file_get_contents('php://input');

    $data = json_decode($rawData, false)->data;

    $login = $data->login;
    $password = $data->password;

    list($ok, $messages, $user) = $this->authenticate($login, $password, true);
    if ($ok){
      // check user groups, compute security groups only if necessary
      if (!in_array(TZR_GROUPID_REMOTEUSE, $user->_cur['grp'])){
	$grps = \Seolan\Core\Kernel::followLinks([$user->uid()],['USERS'=>['GRP'],'GRP'=>['GRPS']]);
      } else {
	$grps = $user->_cur['grp'];
      }
    }
    if ($ok && in_array(TZR_GROUPID_REMOTEUSE, $grps)){
      $data = [];
      $data['uid'] = $user->_cur['KOID'];
      foreach(['alias','email', 'fullnam','DATEF','DATET'] as $k){
	if ($this->xset->fieldExists($k))
	  $data[$k] = $user->_cur[$k];
      }
      \Seolan\Core\Logs::notice(__METHOD__," ok '$login' ".TZR_GROUPID_REMOTEUSE.' '.implode(',', $grps));
      return ['ok'=>true, 'message'=>null,  'user'=>$data];
    } else {
      if ($ok){
	$messages = ['remote_authentication_notallowed'];
      }
      \Seolan\Core\Logs::notice(__METHOD__," error $login - ".implode(',',$messages).' - '.TZR_GROUPID_REMOTEUSE.'/'.implode(',', $user->_cur['grp']));
      return ['ok'=>false, 'message'=>$messages, null];
    }
  }
  
  public function getMyDevices($ar) : void {
    $this->display(array_merge($ar, ['oid' => \Seolan\Core\User::get_current_user_uid()]));
  }

  /// retourne un champ selecteur de user en tenant compte de la conf.
  public function getUserDefaultField($fmoid, $fieldOptions){
    
    if ($this->userselectortreeviewmode)
      return $this->getTreeviewDefaultField($fmoid, $fieldOptions);

    // à voir : identique à getTreeviewDefaultField ?
    $field = \Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>$fieldOptions['field'],
							      'DTAB'=>$fieldOptions['table'],
							      'FTYPE'=>'\Seolan\Field\User\User',
							      'MULTIVALUED'=>$fieldOptions['multivalued'],
							      'COMPULSORY'=>$fieldOptions['compulsory'],
							      'TARGET'=>$this->table,
							      'LABEL'=>[TZR_DEFAULT_LANG=>$fieldOptions['label']]]);
    $field->fgroup=$fieldOptions['group'];
    $field->target = $this->table;
    $field->sourcemodule = $fieldOptions['sourcemodule']??$this->_moid;
    $field->treeview = false;
    return $field;
  }
  ///
  public function getTreeviewDefaultField($fmoid, $fieldOptions) : \Seolan\Core\Field\Field {
    $field = \Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>$fieldOptions['field'],
							      'DTAB'=>$fieldOptions['table'],
							      'FTYPE'=>'\Seolan\Field\User\User',
							      'MULTIVALUED'=>$fieldOptions['multivalued'],
							      'COMPULSORY'=>$fieldOptions['compulsory'],
							      'TARGET'=>$this->table,
							      'LABEL'=>$fieldOptions['label']]);
    $field->fgroup=$fieldOptions['group'];
    $field->target = $this->table;
    $field->sourcemodule = $fieldOptions['sourcemodule']??$this->_moid;
    $field->treeview = true;
    for($i=1; $i<=3; $i++){
      $fn = "treeviewgroup{$i}";
      $field->$fn = $this->$fn;
    }
    return $field;
  }
  
  /// retourne un fieldValue sur un champ lien vers users en mode treeview  
  public function getTreeviewSelector($fmoid, $fieldOptions=['field'=>'users',
							     'table'=>'',
							     'label'=>'Utilisateur',
							     'multivalued'=>1,
							     'compulsory'=>0,
							     'group'=>'',
							     'sourcemodule'=>null], ?array $values=null){

    $field = $this->getTreeviewDefaultField($fmoid, $fieldOptions);
    
    $editOpts = ['fmoid'=>$fmoid,'ffm'=>['moid'=>$this->_moid,
					 'f'=>'getTreeviewDefaultField',
					 'o'=>$fieldOptions]];
    
    if (is_array($values))
      $values = '||'.implode('||', $values).'||';
    
    return $field->edit($values, $editOpts);
  }
  /// retourne un champ user pour sélection d'un user en tenant compte de la conf (treeview ou std)
  public function getUserSelector($fmoid, $fieldOptions=['field'=>'users',
							 'table'=>'',
							 'label'=>'Utilisateur',
							 'multivalued'=>1,
							 'compulsory'=>0,
							 'group'=>'',
							 'sourcemodule'=>null], ?array $values=null){

    if ($this->userselectortreeviewmode)
      return $this->getTreeviewSelector($fmoid, $fieldOptions, $values);
    
    $field = \Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>$fieldOptions['field'],
							      'DTAB'=>$fieldOptions['table'],
							      'FTYPE'=>'\Seolan\Field\User\User',
							      'MULTIVALUED'=>$fieldOptions['multivalued'],
							      'COMPULSORY'=>$fieldOptions['compulsory'],
							      'TARGET'=>$this->table,
							      'LABEL'=>$fieldOptions['label']]);
    $field->fgroup=$fieldOptions['group'];
    $field->target = $this->table;
    $field->sourcemodule = $fieldOptions['sourcemodule']??$this->_moid;
    $field->treeview = false;
    $editOpts = ['fmoid'=>$fmoid,
		 'ffm'=>['moid'=>$this->_moid,
			 'f'=>'getUserDefaultField',
			 'o'=>$fieldOptions]];
    if (is_array($values))
      $values = '||'.implode('||', $values).'||';
    return $field->edit($values, $editOpts);
  }

  /**
    Synchronise les données des utilisateurs consoles sur Rocketchat
  */
  function synchroRC(\Seolan\Module\Scheduler\Scheduler &$scheduler, $o, $more,$params){
      $detail = "\n";
      $rc = new \Seolan\Core\Rocketchat\Rocketchat([]);
      $cleanAll = intval($more->cleanAll);
      if($cleanAll){
          $rc->cleanAll();
          $detail .= "cleanAll OK !\n";
      } else {
          $detail .= "no cleanAll\n";
      }

      $cleanCur = intval($more->cleanCur);
      if($cleanCur){
          $rc->cleanCurrentSiteUser();
          $detail .= "clean current site rocketchat user OK !\n";
          $scheduler->procEdit([
              'oid'=>$o->KOID,
              'status' => 'finished',
              'rem' => $detail,
          ]);
          return 'finished';
      } else {
          $detail .= "no cleanCur\n";
      }
      $fullSelectedFields = ["alias","fullnam","email","logo","GRP","UPD"];
      $selectedFields = [];
      foreach($fullSelectedfields as $field){
          if($this->fieldExists($field)){
              $selectedFields[] = $field;
          }
      }
      $users = $this->browse(["tplentry"=>TZR_RETURN_DATA,"pagesize"=>10000,"selectedfields"=>$selectedFields]);
      
      $lastSynchro = \DateTime::createFromFormat('Y-m-j H:i:s', getDB()->fetchCol("select etime from TASKS where status = 'finished' and title = 'Synchro RocketChat' order by etime DESC",[])[0]);
      // On récupère les emails qui sont utilisés sur plusieurs compte pour les ignorer
      $doublons = getDB()->fetchCol("select email from USERS group by email having count(email) > 1",[]);
      $error = false;
      $nb_users = count($users["lines_oid"]);
      $detail .= $nb_users." utilisateurs ont été trouvés.\n\n";
      
      $nb_created = 0;
      $nb_changed = 0;
      $nb_updated = 0;
      $nb_error = 0;
      for($i=0;$i<$nb_users;$i++){
          //On fait un trim sur l'alias pour enlever les possibles espaces
          $email = $params["email"][$users["lines_oid"][$i]];
          if(empty($email)){
              $email = trim($users["lines_oalias"][$i]->raw);
              if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                  $email .= "@rocketchat.fr";
                  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                      $detail .= "/!\ L'adresse email '".$email."' de l'utilisateur '".$users["lines_oid"][$i]."' est invalide. Le compte rocketchat n'a pas pu être créé.\n";
                      $error = true;
                      $nb_error++;
                      continue;
                  }
              }
          }
          
          //On vérifie que l'utilisateur n'a pas de compte rocketchat
          if($rc->isUserExist($users["lines_oid"][$i]) == 0){
              //Il n'en a pas => on en créer un
              $created = $rc->checkUser($users['lines_oid'][$i]);
              if($created['create']){
                  $nb_created++;
                  $detail .= "- Le compte de ".$users['lines_oalias'][$i]->raw." a été créé sur Rocketchat\n";
                  continue;
              }
          }
          
          //On vérifie qu'il y a de nouvelle chose à synchroniser
          $date = \DateTime::createFromFormat('Y-m-j H:i:s',$users["lines_oUPD"][$i]->raw);
          if($date < $lastSynchro){
              continue;
          }   
          
          //on controle les groupes
          $groupOids = $users["lines_oGRP"][$i]->oidcollection;
          $groups = [];
          foreach($groupOids as $gp){
              $groups[] = getDB()->fetchCol("select GRP from GRP where KOID=?",[$gp])[0];
          }

          $avatar = $params["avatar"][$users["lines_oid"][$i]];
          if(empty($avatar)){
              $avatar_url = $users['lines_ologo'][$i]->resizer;
              if($avatar_url){
                  $avatar = \Seolan\Core\Ini::get("societe_url").$avatar_url;
              } else {
                  $avatar = "";
              }
          }
          $name = $params["name"][$users["lines_oid"][$i]];
          if(empty($name)){
              $name = $users["lines_ofullnam"][$i]->raw;
              if(!strlen($name)){
                  $name = $users["lines_oalias"][$i]->raw;
              }
          }
          
          if(empty($users['lines_oid'][$i]) || empty($email) || empty($name)){
              $detail .= "/!\ La synchro du compte de ".$users['lines_oid'][$i]." n'a pas pu se faire car il manque des informations. Un des éléments suivant est vide : ".print_r(["koid"=>$users['lines_oid'][$i],"email"=>$users["lines_oemail"][$i]->raw,"name"=>$name],true)."\n";
              $error = true; 
          } else {
              $response = $rc->synchroUser($users['lines_oid'][$i],$email,$name,$avatar,$groups);
              $msg = $response["msg"];
              $changed = $response["changed"];
              if(strlen($msg) > 0){
                  $detail .= "/!\ ".$msg."\n";
                  $error = true;
              }
              if($changed != "no change"){
                  $nb_changed++;
                  $detail .= "- Les groupes de ".$users['lines_oalias'][$i]->raw." ont été changés sur Rocketchat\n";
              }
              $nb_updated++;
              $detail .= "- Le compte de ".$users['lines_oalias'][$i]->raw." a été mis à jour\n";
          }
      }
      $detail .= "\n\n";
      $detail .= "Nombres de compte Rocketchat créé : ".$nb_created."\n";
      $detail .= "Nombres de modification de groupe : ".$nb_changed."\n";
      $detail .= "Nombres de compte mis à jour : ".$nb_updated."\n";
      $detail .= "Nombres de compte avec une erreur : ".$nb_error."\n";
      if($scheduler !== null){
          $scheduler->procEdit([
              'oid'=>$o->KOID,
              'status' => $error ? 'warning' : 'finished',
              'rem' => $detail,
          ]);
          return $error;
      }
  }

  /// Permet de se connecter à rocketchat automatiquement
  function rocketchatLogin($ar=NULL){
      $p = new \Seolan\Core\Param([$ar,"uid" => getSessionVar('UID')]);
      $uid = $p->get("uid");
      if(\Seolan\Core\Kernel::isAKoid($uid)){
          $rc = new \Seolan\Core\Rocketchat\Rocketchat([]);
          $token = $rc->checkUser($uid);
          if(is_array($token)){
              $token = $token["token"];
          }
          if(empty($token)){
              die(json_encode(["koid"=>$uid]));
          }
          $url = $rc->server."/home";
          $id = $rc->getUserId($uid);
          $rc->switchStatus("online",$uid);
          die(json_encode(["token"=>$token,"url"=>$url,"uid"=>$id]));
      } else {
          die(json_encode(["error"=>true,"msg"=>"getSessionVar('UID') est vide..."]));
      }
  }

  ///Permet de connaitre le nombre de message non lu de l'utilisateur connecté
  function checkUnreadMsg($ar=NULL){
      $p = new \Seolan\Core\Param([$ar,"uid"=>getSessionVar('UID')]);
      $uid = $p->get("uid");
      if(\Seolan\Core\Kernel::isAKoid($uid)){
          $rc = new \Seolan\Core\Rocketchat\Rocketchat([]);
          die(json_encode($rc->hasUnreadMsg($uid)));
      }
  }

  ///Permet de se déconnecter automatiquement de rocketchat
  function rocketchatLogout($ar=NULL){
      $p = new \Seolan\Core\Param([$ar,"uid"=>getSessionVar('UID')]);
      $uid = $p->get("uid");
      if(\Seolan\Core\Kernel::isAKoid($uid)){
          $rc = new \Seolan\Core\Rocketchat\Rocketchat([]);
          //On se met offline
          $rc->switchStatus("offline",$uid);
      }
  }

  /// Retourne les infos de l'action changer d'utilisateur du browse
  function browseActionDM(&$r,&$i,&$oid,&$oidlvl,$usersel=false){
      $this->browseActionForLine('DM',$r,$i,$oid,$oidlvl,$usersel);
  }
  
  function browseActionDMText($linecontext=null){
      return \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','chat_with');
  }
  function browseActionDMIco($linecontext=null){
      return \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','send','csico');
  }
  function browseActionDMLvl($linecontext=null){
      return $this->secGroups('display');
  }
  function browseActionDMHtmlAttributes(){
      return "target='_self'";
  }
  function browseActionDMUrl($usersel, $linecontext=null){
      return "javascript:createDM('<oid>','".getSessionVar('UID')."')";
  }

  //Permet de créer une conversation privée entre user et uid
  function RCcreateDM($ar=NULL){
      $p = new \Seolan\Core\Param([$ar,"uid"=>getSessionVar('UID')]);
      $user = $p->get('user');
      $uid = $p->get('uid');
      if(\Seolan\Core\Kernel::isAKoid($uid) && \Seolan\Core\Kernel::isAKoid($user)){
          $rc = new \Seolan\Core\Rocketchat\Rocketchat([]);
          $username = $rc->getRocketchatUsername($user)[0]; // return 0 => username, 1 => compteur
          $url = $rc->createDM($username,$uid,$user);
          die(json_encode($url));
      }
  }

  /**
     Permet de vérifier que la conversation existe et la créer sinon
  */
  function checkConvExist($ar=NULL){
      $p = new \Seolan\Core\Param([$ar,"uid"=>getSessionVar('UID')]);
      $uid = $p->get('uid');
      $id = $p->get('id');
      if(\Seolan\Core\Kernel::isAKoid($uid)){
          $rc = new \Seolan\Core\Rocketchat\Rocketchat([]);
          $roomExist = $rc->checkRoomExist($id,$uid);
          if($roomExist){
              $url = ["url"=>$roomExist];
          } else {
              $infos = ["username","customFields"=>"koid"];
              $userInfos = $rc->getUserInfosFromId($id,$infos);
              $username = $userInfos["username"];
              $user = $userInfos["koid"];
              $url = $rc->createDM($username,$uid,$user);
          }
          die(json_encode($url));
      }
  }

  /**
    Prépare et envoie des mails au utilisateur déconnecté qui ont des messages non lus récent
  */
  function offlineMailNotifRC(\Seolan\Module\Scheduler\Scheduler &$scheduler, $o, $more,$params=[]){
      $subject = $params["subject"];
      if(empty($subject)){
          $subject = "Message(s) non lus"; 
      }
      $from = $params["from"];
      if(empty($from)){
          $from = ["mail"=>TZR_NOREPLY_ADDRESS,"name"=>\Seolan\Core\Ini::get("societe")]; 
      }
      $template = $params["template"];
      if(empty($template)){
          $template = "Core/Rocketchat.offlineMailNotification.html"; 
      }
      $USER = $params["USER"];
      if(empty($USER)){
          $USER = $GLOBALS["XUSER"]; 
      }
      $link = $params["link"];
      if(empty($link)){
          $link = ["url"=>\Seolan\Core\Ini::get("societe_url")."admin","text"=>\Seolan\Core\Ini::get("societe")];
      }
      
      $users = $this->browse(["tplentry"=>TZR_RETURN_DATA,"pagesize"=>10000,"selectedfields"=>["fullnam","email"]]);
      $lastSynchro = \DateTime::createFromFormat('Y-m-j H:i:s', getDB()->fetchCol("select UPD from _VARS where name = ?",["xmodscheduler:lastchkOfflineMailNotification"])[0]);
      $lastSynchro->setTimezone(new \DateTimeZone('UTC'));
      $lastSynchro = $lastSynchro->format('Y-m-d H:i:s');
      $lastSynchro = explode(' ',$lastSynchro);
      $lastSynchro = $lastSynchro[0]."T".$lastSynchro[1]."Z";
      $detail = "\n";
      $nb_users = count($users["lines_oid"]);
      $nb_mails = 0;
      $rc = new \Seolan\Core\Rocketchat\Rocketchat([]);
      for($i=0;$i<$nb_users;$i++){
          //On vérifie que l'utilisateur a un compte rocketchat
          if($rc->isUserExist($users["lines_oid"][$i]) == 0){
              continue;
          }
          //On vérifie que l'utilisateur est déconnecté
          if($rc->isUserOnline($users["lines_oid"][$i])){
              continue;
          }
          
          $conv = $rc->getUnread($users["lines_oid"][$i],$lastSynchro);
          if(!empty($conv)){
              $nb_mails += 1;
              $xt = new \Seolan\Core\Template($template);
              $tpldata = [
                  "user"=>[
                      "fullnam"=>$users["lines_ofullnam"][$i]->raw,
                  ],
                  "conv"=>[
                      "list"=>$conv,
                  ],
                  "link"=>[
                      "url"=>$link["url"],
                      "text"=>$link["text"],
                  ]
              ];
              $rawdata = [];
              $msg = $xt->parse($tpldata,$rawdata);
              $xmodcontrib = \Seolan\Core\Module\Module::objectFactory(['moid'=>19]);
              $USER->sendMail2User($subject,$msg,$users["lines_oemail"][$i]->raw,$from);
              $detail .= "Un mail a été envoyé à l'utilisateur ".$users["lines_oid"][$i]."\n";
          }
      }
      $detail .= "\nNombre de mail envoyé : ".$nb_mails."\n";
      if(empty($lastSynchro)){
          getDB()->execute("insert into _VARS (name,value) VALUES (?,?)",["xmodscheduler:lastchkOfflineMailNotification",strtotime("now")]);
      } else {
          getDB()->execute("update _VARS SET value=? WHERE name = ?",[strtotime("now"),"xmodscheduler:lastchkOfflineMailNotification"]);
      }
      if($scheduler !== null){
          $scheduler->procEdit([
              'oid'=>$o->KOID,
              'status' => $error ? 'error' : 'finished',
              'rem' => $detail,
          ]);
          return $error;
      }
  }
}
