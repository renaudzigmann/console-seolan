<?php
namespace Seolan\Module\FrontUsers;
/**
 * Gestion des utilisateurs Front Office
 *
 * @author Camille Descombes

 * Permet entre autres de :
 *  - Gérer différentes listes d'utilisateurs en fonction de leurs groupes
 *  - Gère les panels du compte client via un seul alias
 *  - Surcharger facilement les panels, actions et templates front-office
 */
class FrontUsers extends \Seolan\Module\Table\Table {
  private static $XSESSION = null;
  /// Champs autorisés à la création (à noter que les champs alias, fullnam, GRP sont forcés, donc non pris dans le \Seolan\Core\Param)
  public $inscription_form_fields = [
    'email',
    'name',
    'forename',
    'passwd',
  ];

  /// Champs autorisés à la modification (à noter que les champs oid, alias, fullnam, GRP sont forcés, donc non pris dans le \Seolan\Core\Param)
  public $edit_form_fields = [
    'email',
    'name',
    'forename',
    'passwd',
  ];

  /// Champs à afficher dans les différents formulaires
  public $account_selectedfields = ['email','passwd'];
  public $address_selectedfields = ['address','city','country'];
  public $delivery_selectedfields = ['delivery_address','delivery_city','delivery_country'];

  /// Champs dont on récupère les données à partir des formulaires du front-office
  protected $authorized_form_fields = [];

  /// Si on doit envoyer un mail de validation du compte à l'inscription
  public $inscription_require_mail_validation = true;

  /// Si la création de compte oblige la saisie d'un mot de passe
  public $inscription_require_password = false;

  /// Si un mail de notification de création de compte doit être envoyé
  public $send_account_creation_mail = false;

  /// Groupe par défaut des utilisateurs associés à ce module
  public $default_group = TZR_GROUPID_AUTH;

  /// A mettre dans les options du module
  public $alias_home = 'account';

  /**
   * Filtre en fonction du groupe d'utilisateur du module en backoffice
   * Restriction de l'accès à son propre compte en frontoffice
   */
  function __construct($ar) {
    parent::__construct($ar);
    $this->addCondFilter([
      'GRP' => ['=', $this->getFilterGroups()],
    ]);
  }
  /// options du module
  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpts(static::getCommonOptions());
  }
  /// options communes au wizard (installateur) et aux options du module
  public static function getCommonOptions() {
    return [
      'default_group' => [
        'label' => "Groupe par défaut associé aux utilisateurs du module",
        'comment' => "Les utilisateurs des groupes héritants du groupe sélectionné seront également disponibles dans ce module",
        'type' => 'object',
        'options' => ['table' => 'GRP'],
      ],
      'inscription_require_mail_validation' => [
        'label' => "Demander une validation du compte par mail à la création",
        'comment' => "Enverra un email avec un lien de validation du compte à l'utilisateur avant de rendre son compte accessible (mesure anti-robots)",
        'type' => 'boolean',
      ],
      'inscription_require_password' => [
        'label' => "Demander un mot de passe à la création du compte",
        'comment' => "Si le mot de passe n'est pas requis, alors l'alias du compte sera généré aléatoirement",
        'type' => 'boolean',
      ],
      'alias_home' => [
        'label' => "Alias de la page d'accueil du compte",
        'comment' => "Alias du gestionnaire de rubrique vers lequel sera redirigé l'utilisateur après identification",
      ],
      'account_selectedfields' => [
        'label' => "Champs : Création de compte",
        'comment' => "Champs login et mot de passe (email et passwd par défaut)",
        'type' => 'field',
        'options' => ['table' => 'USERS', 'multivalued' => true, 'compulsory' => true],
      ],
      'address_selectedfields' => [
        'label' => "Champs : Adresse de facturation",
        'comment' => "Champs permettant à l'utilisateur de renseigner son adresse",
        'type' => 'field',
        'options' => ['table' => 'USERS', 'multivalued' => true],
      ],
      'delivery_selectedfields' => [
        'label' => "Champs : Adresse de livraison",
        'comment' => "Champs permettant à l'utilisateur de définir une adresse de livraison différente de son adresse de facturation",
        'type' => 'field',
        'options' => ['table' => 'USERS', 'multivalued' => true],
      ],
    ];
  }

  /// Groupe par défaut des utilisateurs associés à ce module
  public function getDefaultGroupOid() {
    return $this->default_group;
  }

  /// Retourne tous les groupes/sous-groupes d'utilisateurs authentifiés
  public function getFilterGroups() {
    return \Seolan\Core\Kernel::followLinksUp([ $this->getDefaultGroupOid() ], ['GRP' => ['GRPS']]);
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g = array();
    $g['procAuth'] = array('none');
    $g['procPasswordForgotten'] = array('none');
    $g['procNewPasswordRequest'] = array('none');
    $g['procEditMyAccount'] = array('none');
    $g['procCreateMyAccount'] = array('none');
    $g['procValidateMyAccount'] = array('none');
    $g['logasFrontOffice'] = array('admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function, $group);
  }

  /// Liste des fonctions disponibles dans la création de section fonction
  function getUIFunctionList() {
    $list = parent::getUIFunctionList();
    $list['account'] = __('Gestion du compte utilisateur','\Seolan\Module\FrontUsers');
    return $list;
  }

  /**
   * Fonctions appelées lors du paramétrage/affichage de la section connexion utilisateur
   *
   * Stratégie de navigation de cette section :
   * - on utilise une URL panel=XXX pour afficher un formulaire ou un contenu dans cette section
   * - on utilise les fonctions CRUD du module (procEdit, procInsert, procMyAction, ...) pour effectuer des actions **soumises aux droits des modules**
   */
  function UIParam_account($ar) { return []; }
  function UIView_account($ar) {

    if (\Seolan\Core\Shell::admini_mode()) return;

    // Si aucun 'next' n'est précisé, alors on estime que la prochaine destination
    // (par exemple pour les formulaires) sera la page d'où l'on provient
    if (!isset($_REQUEST['next']))
      $_REQUEST['next'] = $_SERVER['HTTP_REFERER'];

    $section = [];

    // Si aucun panel n'est spécifié, on affiche la homepage par défaut
    $panel = @$_REQUEST['panel'] ?: 'home';

    // Si aucune fonction ne porte le nom du panel demandé, on affiche la homepage par défaut
    if (!method_exists($this, "panel_$panel")) {
      $panel = 'home';
    }

    $section = $this->{"panel_$panel"}($ar);
    $section['panel'] = $panel;

    $section['labels'] = $this->getLabels();
    return $section;
  }
  /// Labels du module
  public function getLabels(){
    return $GLOBALS['XSHELL']->labels->get_labels(['selectors'=>['\Seolan\Module\FrontUsers'],
					    'local'=>true]);
  }
  /// Page d'accueil du compte
  protected function panel_home($ar) {
    $section = $this->requireLogin();
    return $section;
  }

  /// Fonctions appelées lors de l'ajout/édition de la section edit
  protected function panel_inscription($ar) {
    $p = new \Seolan\Core\Param($ar);
    if (!\Seolan\Core\Shell::admini_mode() && \Seolan\Core\User::authentified()) {
      \Seolan\Core\Shell::alert(__("Vous êtes déjà inscrit ! Si vous souhaitez créer un nouveau compte, déconnectez-vous en premier lieu, puis retournez sur la page d'inscription",'\Seolan\Module\FrontUsers'));
      $this->gotoPanel('home');
    }
    $section['form'] = $this->insert(array(
      'tplentry' => TZR_RETURN_DATA,
      'selectedfields' => $this->inscription_form_fields,
      '_options' => array('genpublishtag' => false, 'error'=>'return')
    ));
    return $section;
  }

  /// Fonction appelée lors du paramétrage/affichage de la section connexion utilisateur
  protected function panel_login($ar) {
    // si authentifié, redirection vers la home
    if (!\Seolan\Core\Shell::admini_mode() && \Seolan\Core\User::authentified() && \Seolan\Core\User::get_user()->inGroups($this->getDefaultGroupOid(), false)) {
      if (@$_REQUEST['message'])
        \Seolan\Core\Shell::alert($_REQUEST['message']);
      $this->gotoPanel('home');
    }
    // sinon détection d'un token via la session 
    if (!\Seolan\Core\Shell::admini_mode() && !\Seolan\Core\User::authentified()){
      static::sessionInstance()::auth();

      $field = \Seolan\Core\Field\Field::objectFactory2($this->table, 'passwd');
      $fieldPasswd = $field->edit("");
      \Seolan\Core\Shell::toScreen2('form', 'password', $fieldPasswd);

    }
  }
  
  /// Fonction appelée lors du paramétrage/affichage de la section connexion utilisateur
  protected function panel_logout($ar) {
    static::sessionInstance()->close([
      '_next' => $GLOBALS['START_CLASS']::buildUrl(['message' => __("Vous avez été déconnecté",'\Seolan\Module\FrontUsers')])
    ]);
  }

  /// Fonction appelée lors du paramétrage/affichage de la section perte du mot de passe
  protected function panel_passwordForgotten($ar) {
  }

  /// Edition du compte
  protected function panel_edit($ar) {
    $section = $this->requireLogin();
    $section['form'] = $this->edit(array(
      '_local' => true,
      '_options' => ['genpublishtag' => false, 'error'=>'return'],
      'tplentry' => TZR_RETURN_DATA,
      'oid' => \Seolan\Core\User::get_current_user_uid(),
      'selectedfields' => $this->account_selectedfields,
    ));
    $section['onerror']=$this->getPanelUrl('edit');
    return $section;
  }

  /// Edition de l'adresse
  protected function panel_editaddress($ar) {
    $section = $this->requireLogin();
    $section['form'] = $this->edit(array(
      '_local' => true,
      '_options' => ['genpublishtag' => false, 'error'=>'return'],
      'tplentry' => TZR_RETURN_DATA,
      'oid' => \Seolan\Core\User::get_current_user_uid(),
      'selectedfields' => array_merge($this->address_selectedfields, $this->delivery_selectedfields),
    ));
    return $section;
  }

  /// Edition de l'adresse
  protected function panel_orders($ar) {
    $section = $this->requireLogin();
    $shop = \Seolan\Core\Module\Module::singletonFactory(XMODCART2_TOID);
    if (!$shop) {
      throw new \Exception("No XModCart found for this website");
    }
    $section['orders'] = $shop->customer->orders;
    $section['shop'] = $shop;
    return $section;
  }

  /// Vérifie que l'utilisateur est logué avant de continuer
  protected function requireLogin() {

    if (@$_REQUEST['panel'] == 'login')
      throw new Exception("Prevent infinite login redirection");

    $GLOBALS['XSHELL']->setLoginUrl($this->getPanelUrl('login'));

    // Si l'utilisateur n'est pas authentifié, on le redirige vers le login (évite de positionner des droits sur les Rubriques)
    if (!\Seolan\Core\User::authentified()) {
      static::shellInstance()->redirect2auth(
        __("Vous devez vous authentifier pour accéder à cette page",'\Seolan\Module\FrontUsers'),
        static::shellInstance()->getCurrentUri());
    }

    // Si l'utilisateur n'appartient pas au groupe requis, on lui signale
    if (!\Seolan\Core\User::get_user()->inGroups($this->getDefaultGroupOid(), false)) {
      static::shellInstance()->redirect2auth(
        __("Vous n'avez pas accès à cette fonctionnalité avec votre compte",'\Seolan\Module\FrontUsers'));
    }
    // Si l'utilisateur est bien autentifié, alors on ajoute le display avec les sous-modules au résultat de la section
    $section['user'] = $this->display([
      '_options' => ['local' => true],
      'oid' => \Seolan\Core\User::get_current_user_uid()
    ]);
    return $section;
  }

  /// Procédure de création du compte
  public function procCreateMyAccount($ar = array()) {
    try {
      $p = new \Seolan\Core\Param($ar);
      $alias = $this->getAccountAlias($ar);
      $passwd  = $p->get('passwd');
      $passwd2 = empty($p->get('passwd_HID')) ? $p->get('passwd2') : $p->get('passwd_HID');
      $_REQUEST['passwd'] = null;
      $_REQUEST['passwd2'] = null;
      $_REQUEST['passwd_HID'] = null;
      $this->authorized_form_fields = $this->inscription_form_fields;
      $insert_params = $this->getUserFormData($ar, array(
        '_local' => true,
        'GRP' => $this->getDefaultGroupOid(),
        'PUBLISH' => !$this->inscription_require_mail_validation,
        'BO' => false, // Accès au back-office
        'alias' => $alias,
        'passwd' => $passwd,
        'fullnam' => $this->getAccountFullName($ar),
        'DATEF' => date('Y-m-d'),
        'DATET' => date('Y-m-d', strtotime("+30 year"))
      ));

      if ($this->aliasExists($alias))
        throw new Exception(__("L'adresse email saisie est déjà utilisée par un autre utilisateur",'\Seolan\Module\FrontUsers'));

      if ($this->inscription_require_password && empty($passwd))
        throw new Exception(__('Vous devez saisir un mot de passe','\Seolan\Module\FrontUsers'));

      if ($passwd != $passwd2)
        throw new Exception(__('Les mots de passe saisis sont différents','\Seolan\Module\FrontUsers'));

      $this->triggerCallbacks('pre_create_account', $insert_params);
      $insert = $this->procInsert($insert_params);
      $this->triggerCallbacks('post_create_account', $insert);

      if (!$insert['oid'])
        throw new Exception(__("Le compte n'a pas pu être créé",'\Seolan\Module\FrontUsers'));


      if (!$this->inscription_require_password && !empty($passwd)) {
        \Seolan\Core\Shell::alert(__("Compte créé avec succès",'\Seolan\Module\FrontUsers'), 'success');
        if ($this->send_account_creation_mail)
          $this->sendCreationMail($insert['oid'], $passwd);
      }

      if ($this->inscription_require_mail_validation) {
        $this->sendValidationMail($insert['oid']);
        if (!\Seolan\Core\Shell::hasNext())
          $this->gotoPanel('login');
      } else {
        $ok = $this->autoLogin($insert['oid']);
	if (!$ok)
	  throw new Exception(__("Impossible de connecter le compte",'\Seolan\Module\FrontUsers'));
        if (!\Seolan\Core\Shell::hasNext())
          $this->gotoPanel('home');
      }
    } catch (Exception $e) {
      \Seolan\Core\Shell::alert($e->getMessage());
      \Seolan\Core\Shell::setNext($p->get('onerror') ?: static::shellInstance()->getCurrentUri());
      unset($insert_params['passwd']);
      $this->storeFormData($insert_params);
    }
  }

  /// Procédure d'édition du compte
  public function procEditMyAccount($ar = array()) {
    try {
      $p = new \Seolan\Core\Param($ar);
      $uid = \Seolan\Core\User::get_current_user_uid();
      $user = \Seolan\Core\User::get_user();
      $_REQUEST['alias'] = null;
      $passwd = $_REQUEST['passwd'];
      $_REQUEST['passwd'] = null;
      $oldpass  = $p->get('oldpass');
      $newpass  = !empty($passwd) ? $passwd : $p->get('newpass');
      $newpass2 = !empty($p->get('passwd_HID')) ? $p->get('passwd_HID') : $p->get('newpass2');
      $alias = $this->getAccountAlias($ar);
      $this->authorized_form_fields = $this->edit_form_fields;
      $edit_params = $this->getUserFormData($ar, array(
        //'_local' => True,
        'GRP' => $this->getDefaultGroupOid(),
        'oid' => $uid,
        'alias' => $alias,
        'fullnam' => $this->getAccountFullName($ar),
      ));
      if ($alias != $user->_cur['alias'] && $this->aliasExists($alias))
	throw new Exception(__("'{$alias}' '{$user->_cur['oalias']->raw}' L'adresse email saisie est déjà utilisée par un autre utilisateur",'\Seolan\Module\FrontUsers'));
      //throw new Exception(__("L'adresse email saisie est déjà utilisée par un autre utilisateur",'\Seolan\Module\FrontUsers'));
      if (!empty($newpass)) {
        $fieldPasswd = \Seolan\Core\Field\Field::objectFactory2($this->table, 'passwd');
        if (!$fieldPasswd->with_confirm){
          $newpass2 = $newpass;
        }
        if ($newpass != $newpass2)
          throw new Exception(__('Les mots de passe saisis sont différents','\Seolan\Module\FrontUsers'));
	$currentPasswd = getDb()->fetchOne("SELECT passwd FROM {$this->table} WHERE KOID=?", [$uid]);
	if (!hash_equals($currentPasswd, hash('sha256', $oldpass)))
          throw new Exception(__('Le mot de passe actuel saisi est incorrect','\Seolan\Module\FrontUsers'));
	$edit_params['passwd'] = $_REQUEST['passwd'] = $newpass;
	
      }
      $this->triggerCallbacks('pre_edit_account', $edit_params);
      $this->procEdit($edit_params);
      $this->triggerCallbacks('post_edit_account', $insert);
      \Seolan\Core\Shell::alert(__('Modifications enregistrées','\Seolan\Module\FrontUsers'), 'success');
    } catch (Exception $e) {
      \Seolan\Core\Shell::alert($e->getMessage());
      \Seolan\Core\Shell::setNext($p->get('onerror') ?: static::shellInstance()->getCurrentUri());
      $this->storeFormData($edit_params);
    }
  }

  /**
   * Procédure de validation du compte
   */
  public function procValidateMyAccount($ar) {
    $p = new \Seolan\Core\Param($ar);
    $user = $p->get('oid');
    $this->getXSetUserDisplay($user);
    if (empty($user)) {
      \Seolan\Core\Shell::alert(__('La validation de votre compte a échoué','\Seolan\Module\FrontUsers'));
      $this->gotoPanel('login');
    }
    if ($this->getAccountValidationKey($user) != $p->get('key')) {
      \Seolan\Core\Shell::alert(__('La clé de validation de votre compte est incorrecte','\Seolan\Module\FrontUsers'));
      $this->gotoPanel('login');
    }
    $this->procEdit([
      'oid' => $user['oid'],
      'PUBLISH' => true,
    ]);
    \Seolan\Core\Shell::alert(__('Votre compte a bien été validé','\Seolan\Module\FrontUsers'), 'success');
    $this->autoLogin($user['oid']);
    $this->gotoPanel('home');
  }

  /// Appelée une fois le login saisi
  public function procAuth() {
    $ar['onerror'] = $this->getPanelUrl('login');
    $ar['_next'] = $this->getPanelUrl('home');
    return static::sessionInstance()->procAuth($ar);
  }
  /**
   * Une fois le login saisi
   * _next contient l'url de login (alias+panel)
   */
  public function procPasswordForgotten() {
    $p = new \Seolan\Core\Param([],[]);
    $passwordurl = $onerror = $this->getPanelUrl('passwordForgotten');
    $loginurl = $this->getPanelUrl('login');
    $login=$p->get('login');
    $moduser = \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
    // création du token via l'annuaire associé au login
    $dir = $moduser->getExclusiveUserDirectory($login);
    if ($dir != null){
      // génération du token et envoie du mail
      list($ok, $message, $tokenid) = $dir->getUserManager()->prepareNewPassword($login, null, 'forgotten', ['mail'=>true,'loginurl'=>$loginurl]);
      // ajout du message confirmation
      $passwordurl = makeUrl($passwordurl, ['message'=>$message]);
      \Seolan\Core\Shell::setNext($passwordurl);
    } else {
      // ajout du message d'erreur
      $onerror = makeUrl($onerror, ['message'=>$message]);
      \Seolan\Core\Shell::setNext($onerror);
      return false;
    }
  }
  /**
   * contrôle et enregistrement d'un nouveau mot de passe
   * -> 'j'ai perdu mon mot de passe'
   * voir Module/User::procNewPasswwordRequest2 
   */
  public function procNewPasswordRequest(){
    $p = new \Seolan\Core\Param($ar, []);
    $next = $p->get('next');
    $onerror = $p->get('onerror');
    list($message, $token) = $GLOBALS['TZR_SESSION_MANAGER']::getToken($p->get('id'));
    if ($token == null){
      \Seolan\Core\Logs::notice(__METHOD__,'invalid token "'.$p->get('id').'"');
      \Seolan\Core\Shell::setNext(makeUrl($onerror, ['message'=>$message,
						     '_'=>date('ymdhis')]));
    } else {
      \Seolan\Core\Logs::notice(__METHOD__,"{$token['alias']} {$token['id']}");
      $moduser = \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
      
      // contrôle et enregistrement du mot de passe
      $password = $p->get('passwd');
      $passwordConfirm = $p->get('passwd_HID');
      if ( $password == null ){ 
        $password = $p->get('password'); 
        $passwordConfirm = $p->get('password_HID'); 
      }
      $fieldPasswd = \Seolan\Core\Field\Field::objectFactory2($this->table, 'passwd');
      if ( !$fieldPasswd->with_confirm){
        $passwordConfirm = $password;
      }
      list($ok,$message,$mailComponents, $userors)  = $moduser->changePassword($token['useroid'],
                    $password,
                    $passwordConfirm,
                    false // no mail
                    );
      if ($ok){
	$message = __('Votre mot de passe a bien été enregistré. Vous pouvez vous connecter');
	// effacer le token
	\Seolan\Core\DbIni::clear($token['id'], false);
	// former l'url pour le login
	\Seolan\Core\Shell::setNext(makeUrl($next, ['message'=>$message,
						    'login'=>$token['alias'],
						       '_'=>date('ymdhis')]));
      } else {
	\Seolan\Core\Shell::setNext(makeUrl($onerror, ['message'=>$message,
						       '_'=>date('ymdhis')]));
      }
    }
  }
  /**
   * Fonction permettant de loguer automatiquement l'utilisateur
   * @param KOID $user_oid Identifiant de l'utilisateur (Ex: USERS:1)
   * @return @see \Seolan\Core\Session::procAuth()
   */
  private function autoLogin($user_oid) {
    try{
      $this->sessionInstance()->setUserFromUid($user_oid, __METHOD__);
    } catch(\Exception $e){
      \Seolan\Core\Logs::critical(__METHOD__,$e->getMessage());
      return false;
    }
    return true;
  }

  /// Ajoute un logas sur le front-office
  public function browse_actions(&$browse_results, $assubmodule = false, $ar = NULL) {
    if(!is_array($browse_results['lines_oid'])) return;
    parent::browse_actions($browse_results);
    $self = $GLOBALS['TZR_SESSION_MANAGER']::complete_self()."&moid=".$this->_moid."&oid=<oid>&tplentry=br&function=";
    $moveico=\Seolan\Core\Labels::getSysLabel('general','move');
    $sec2lvl=$this->secGroups('logasFrontOffice');
    foreach ($browse_results['lines_oid'] as $i => $oid) {
      $oidlvl = array_keys($browse_results['objects_sec'][$i]);
      $self1 = str_replace('<oid>',$oid,$self);
      // changer user
      $inter = array_intersect($sec2lvl,$oidlvl);
      if(!empty($inter)){
        $url = $self1.'logasFrontOffice&_next='.urlencode("/index.php?moid=$this->_moid&function=gotoHome");
        $browse_results['actions'][$i][4]='<a href="'.$url.'" title="'.__('Se connecter en tant que cet utilisateur en front-office','\Seolan\Module\FrontUsers').'" target="front">'.$moveico.'</a>';
        $browse_results['actions_url'][$i][4]=$url;
      }
    }
  }

  /// Permet de se connecter au front-office en tant qu'un utilisateur en 1 clic
  public function logasFrontOffice($ar) {
    $p = new \Seolan\Core\Param($ar,array());
    $oid = $p->get('oid');
    session_write_close();
    session_name(TZR_FO_SESSION_NAME);
    session_id(@$_COOKIE[TZR_FO_SESSION_NAME]);
    session_start();
    $this->autoLogin($oid);
  }

  /**
   * Envoi un email de création du compte
   */
  public function sendCreationMail($user, $passwd = '') {
    $this->getXSetUserDisplay($user);
    $subject = __('Création de votre compte','mail');
    $text = __(
      "Vous venez de créer un compte sur notre site %website dont voici les identifiants :\n".
        "Identifiant : %login\n".
        "Mot de passe : %password\n\n".
        "Pour accéder à votre compte, rendez-vous sur ce lien : %link",
      'mail', [
        'website' => \Seolan\Core\Session::makeDomainName(),
        'login' => $user['oemail']->text,
        'password' => $passwd,
        'link' => \Seolan\Core\Session::makeDomainName().$this->getPanelUrl('login'),
    ]);
    $this->sendMail2User($subject, $text, $user['oemail']->text);
  }

  /**
   * Envoi un email avec un lien de vérification du compte
   */
  public function sendValidationMail($user) {
    $this->getXSetUserDisplay($user);
    $key = $this->getAccountValidationKey($user);
    $subject = __('Validation de votre compte','mail');
    $text = __('Pour valider votre compte sur notre site %website, merci de bien vouloir cliquer sur le lien suivant : %link', 'mail', [
      'website' => $GLOBALS['HOME_ROOT_URL'],
      'link' => $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'moid='.$this->_moid.'&function=procValidateMyAccount&oid='.$user['oid'].'&key='.$key,
    ]);
    $this->sendMail2User($subject, $text, $user['oemail']->text);
    \Seolan\Core\Shell::alert(__('Un email contenant un lien vous a été envoyé afin que vous puissiez valider votre compte','\Seolan\Module\FrontUsers'), 'success');
  }

  /**
   * Renvoi le lien de vérification spécifique au compte (valable 1 jour)
   * @param DISPLAY_OBJECT $user
   * @todo Mettre une date de validité de cette clé dans XDbIni
   */
  function getAccountValidationKey($user) {
    $this->getXSetUserDisplay($user);
    return hash('sha256', $user['oid'].$user['oemail']->text.date('ymd'));
  }

  /**
   * Transforme l'user en DISPLAY_OBJECT si le paramètre est un KOID
   * @param mixed &$user
   */
  function getXSetUserDisplay(&$user) {
    if (\Seolan\Core\Kernel::isAKoid($user)) {
      $user = $this->xset->rDisplay($user, [], false, '', '', [ '_published' => false ]);
    }
  }

  /// Check la validité du compte
  public function emailExists($email) {
    return $this->valueExists('email', $email);
  }

  /// Check la validité du compte
  public function aliasExists($alias) {
    return $this->valueExists('alias', $alias);
  }

  /**
   * Vérifie si la valeur pour un champ n'existe pas déjà en BDD
   * @param {string} $field Champ SQL à checker
   * @param {string} $value Valeur du champ à checker
   */
  public function valueExists($field, $value) {
    return getDB()->count("SELECT COUNT(*) FROM {$this->table} WHERE $field=?", [$value]) > 0;
  }

  /// Permet de récupérer l'alias dans un des champ du formulaire
  protected function getAccountAlias($params) {
    $p = new \Seolan\Core\Param($params);
    // Si aucun password n'est renseigné, c'est qu'il s'agit d'une création de compte temporaire (sans password)
    if (!$this->inscription_require_password && \Seolan\Core\Shell::_function() == 'procCreateMyAccount' && !$p->get('passwd'))
      return uniqid();
    return $p->get('email');
  }

  /// Génère le fullname à partir des champs nom et prénom
  protected function getAccountFullName($params) {
    $p = new \Seolan\Core\Param($params);
    if (!$p->is_set('forename') || !$p->is_set('name'))
      return null;
    return $p->get('forename').' '.$p->get('name');
  }

  /**
   * Récupère seulement les champs spécifiés dans la propriété $user_form_fields
   * en les surchargeant avec le 2ème paramètre de la fonction
   * @param array $ar
   * @param array $override_with Surcharge des champs à retourner
   * @return array Données du formulaire à enregistrer
   */
  protected function getUserFormData(array $ar, $override_with = array()) {
    $form_data = array();
    $p = new \Seolan\Core\Param($ar);
    foreach ($this->authorized_form_fields as $user_form_field) {
      $form_data[$user_form_field] = $p->get($user_form_field);
    }
    return array_merge($form_data, $override_with);
  }

  /**
   * Retourne des suggestions d'alias à partir d'un ou plusieurs termes
   * @param mixed $aliases Chaine ou tableau de termes à partir desquels trouver un alias alternatif
   * @return array Tableau de suggestions
   */
   public function getAliasSuggestions($aliases) {
    if (is_string($aliases)) $aliases = [$aliases];
    $suggestions = [];
    foreach ($aliases as $alias) {
      $alias = strtolower(preg_replace('@[^\w]+@','',removeaccents($alias)));
      if (empty($alias)) continue;
      $suggestions[] = $this->getFirstValidAlias($alias);
    }
    return array_filter($suggestions);
  }

  /**
   * Retourne le premier alias valide à partir d'une suggestion et en ajoutant un suffixe
   * @param string $alias Alias à tester puis transformer
   * @param int $pad_length Longueur du suffixe
   * @param string $pad_string Chaine à répéter dans le suffixe ('0' par défaut)
   * @return string Alias valide
   */
  public function getFirstValidAlias($alias, $pad_length = 3, $pad_string = '0') {
    $alias_mod = $alias;
    while ($this->aliasExists($alias_mod)) {
      $alias_mod = $alias.str_pad(++$i, $pad_length, $pad_string, STR_PAD_LEFT);
    }
    return $alias_mod;
  }

  /**
   * Redirige vers la page correspondant au panel passé en paramètre
   * @param string $panel
   */
  public function gotoPanel($panel) {
    if (\Seolan\Core\Shell::admini_mode())
      throw new Exception("gotoPanel $panel");
    return $GLOBALS['START_CLASS']::redirectTo($this->getPanelUrl($panel));
  }

  /// Redirige vers la page d'accueil
  public function gotoHome() {
    $this->gotoPanel('home');
  }

  /**
   * Renvoie l'URL de la page correspondant au panel passé en paramètre
   * @param string $panel
   */
  public function getPanelUrl($panel) {
    return $GLOBALS['START_CLASS']::buildUrl([
      'alias' => $this->alias_home,
      'panel' => $panel,
    ], true, false);
  }

  protected static function sessionInstance(){
    if (empty(static::$XSESSION))
      static::$XSESSION = new $GLOBALS['TZR_SESSION_MANAGER'];
    return static::$XSESSION;
  }
  protected static function shellInstance(){
    return $GLOBALS['XSHELL'];
  }
  // à intégrer dans Module\Table ?
  /**
   * Re-remplit le formulaire avec les informations insérées précédemment dans le cas d'une erreur de saisie
   * @param {array} $ar
   * @return {array} Options d'affichage des champs
   */
  function retrieveStoredFormData(&$ar) {
    if (!$this->_issetSession('form_data')) return false;
    $options = @$ar['options'] ?: [];
    $ar['options'] = array_replace_recursive($options, $this->_getSession('form_data'));
    return $ar['options'];
  }

  /**
   * Enregistre les données d'édition en session pour le prochain affichage
   * du formulaire tant que le procXxxCtrl n'est pas passé
   * @param {array} $ar
   * @param {string} $reedit_prepare_function Fonction de ré-édition du XSet
   */
  function storeFormData($ar, $reedit_prepare_function = 'prepareReEdit') {
    $this->_setSession('form_data', $this->xset->$reedit_prepare_function($ar));
  }
  /**
   * Supprime les données d'édition en session
   */
  function clearStoredFormData() {
    $this->_clearSession('form_data');
  }



}
