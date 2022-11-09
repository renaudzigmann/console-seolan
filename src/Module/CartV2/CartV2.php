<?php
namespace Seolan\Module\CartV2;
/**
 * Module de gestion d'une boutique et d'un panier
 *
 * Devrait plutôt se dénommer XModShop mais pour des raisons historiques, on garde XModCart
 *
 * @author Marie-Anne Paul, Camille Descombes
 *
 * @todo Mettre en générique et options tous les champs des tables ORDERS, ORDERSLINES, TVA/TAXES, PAYS, CLIENT
 * @todo Table de gestion de plusieurs adresses postales pour les clients
 * @todo Vérifier le fonctionnement des acomptes
 */
class CartV2 extends \Seolan\Module\Table\Table {
  public $table;			// orders
  public $backofficeemail = TZR_DEBUG_ADDRESS;
  public $deliverypolicy = 'deliv';
  public $deliverytype = 'simp';
  public $deliveryweight = 'mw';
  public $discfield = 'disc';
  public $acompte = null;
  public $labelfield = 'F0002';
  public $orderdatefield = 'CREAD';
  public $orderlinestable = 'SHOPORDERSLINES';
  public $orderreffield = 'ref';
  public $productstable = 'T007';
  public $coupontable=NULL;
  public $countriestable=null;
  public $deliverytable = 'SHOPDELIVERY';
  public $paidfield = 'paid';
  public $alreadypaidfield = 'apaid';
  public $pricefield='prixht';
  public $proddeliverytax = 'deliv';
  public $is_promofield = 'is_promo';
  public $promofield = 'promo';
  public $referencefield = 'F0001';
  public $tvafield = 'F0007';
  public $userdiscount = 'reduc';
  public $edeliv = 'EDELIV';
  public $edelivdelay = 7;
  public $edelivfield = 'ebook';
  public $sender='info@xsalto.com';
  public $sendername='info@xsalto.com';
  protected $_filter_active = true;
  
  /**
   * Nouveaux champs 2017
   *
   * @todo Il reste beaucoup de travail d'homogénéisation des noms de champs à faire
   *       entre le wizard et le module en gardant la rétro-compatibilité !
   *
   * La récupération du nom des champs serait surement mieux sous forme de tableau retourné par une fonction qui par défaut récupère les valeurs dans les propriétés du module et qui peut être surchargeable facilement par une classe fille. Exemple :
   * function getShopFields() {
   *   return [
   *     order => [
   *       'amount' => F0001,
   *       'taxes' => F0001,
   *       ...
   *     ],
   *     orderlines => [
   *       'product' => F0004,
   *       ...
   *     ]
   *   ]
   * }
   */
  public $ordertvafield = 'tva';
  public $orderamounthtfield = 'totalht';
  public $orderamountfield = 'total';
  public $orderdeliveryfield = 'delivery';
  public $orderuserfield = 'customer';
  public $orderremisefield = 'disc';
  public $ordercouponfield = 'coupon';
  public $orderliencouponfield = 'LIENCOUPON';
  public $ordertpaidfield = 'tpaid';
  public $orderstatusfield = 'orderstatus';

  /// Nom de la classe de gestion d'un panier en session
  protected $cart_class = '\Seolan\Module\CartV2\ShopCart';
  // public $cart non défini, voir __get
  
  /// Nom de la classe de gestion des items d'un panier ou d'une commande
  protected $item_class = '\Seolan\Module\CartV2\Item';
  
  /// Nom de la classe de gestion du client
  protected $customer_class = '\Seolan\Module\CartV2\Customer';
  // public $customer non défini, voir __get
  
  /// Nom de la classe de gestion d'une commande
  protected $order_class = '\Seolan\Module\CartV2\Order';
  
  /// MOID du module des utilisateurs front-office lié
  public $moid_customers;

  /// XModFrontUsers module des utilisateurs front-office
  public $customers;

  /// Alias des pages du tunnel de commande
  public $alias_order = 'order';

  /// Alias de la page d'accueil de la boutique
  public $alias_shop = 'home';

  /// Définit si les réductions sont activées sur le site
  public $coupon_enabled = false;

  /// Définit si le paiement par chèque est activé sur le site
  public $payment_by_check_enabled = false;

  /// Texte apparaissant à l'utilisateur après une demande de paiement par chèque
  public $check_order_txt = 'ORDRE A COMPLETER';

  /// Adresse à laquelle le client doit envoyer son chèque
  public $check_address_txt = 'ADRESSE A COMPLETER';

  /// Définit si tous les paniers sont enregistrés à partir de l'ajout d'un article par un utilisateur (connecté ou non)
  public $always_save_cart_in_database = true;

  /// Définit si un mail est envoyé à l'administrateur à chaque nouvelle commande
  public $send_new_order_mail = false;

  /// Limite de téléchargement pour les articles numériques (dématérialisés)
  public $edeliv_download_limit = 3;

  /// Surcharges de gabarits et gabarits de champs
  protected static $_fieldsTemplates = ['customer'=>['passwd'=>'Module/FrontUsers.form-field-passwd.html']];
  
  /**
   * Lors de la construction du module Boutique :
   *  - on implémente les objets XSET les plus utilisés
   *  - on charge le module des utilisateurs front-office
   *  - on construit les objets XShopCart et XShopCustomer
   *  - on filtre le module selon l'interface et l'utilisateur connecté
   */
  function __construct($ar=NULL){
    parent::__construct($ar);
    // Quand on est dans le contexte du retour banque, on force la création du $GLOBALS['XUSER']
    if($_SERVER['SCRIPT_NAME'] == '/csx/scripts/monetique-retour-auto.php')
      $GLOBALS['XSHELL']->_load_user();
    \Seolan\Core\Labels::loadLabels('\Seolan\Module\CartV2\CartV2');
    // Rend plus logique le nommage des propriétés de la classe
    $this->edelivtable      = $this->edeliv;
    $this->orderpaidfield   = $this->paidfield;

    $this->xset_orderlines = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->orderlinestable);
    $this->xset_products   = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->productstable);
    $this->xset_delivery   = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->deliverytable);
    $this->xset_coupon     = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->coupontable);
    if (\Seolan\Core\System::tableExists($this->edelivtable))
      $this->xset_edeliv   = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->edelivtable);
  
    if (!$this->xset_orderlines->fieldExists('product')) {
      \Seolan\Core\Shell::alert("Le champ $this->orderlinestable.product liant les articles aux lignes de commande n'existe pas !");
    }

    if (!$this->moid_customers) {
      \Seolan\Core\Logs::critical(__METHOD__, "Aucun module utilisateur lié à la boutique, vérifiez les propriétés du module");
    } else {
      $this->customers = \Seolan\Core\Module\Module::objectFactory($this->moid_customers);
    }
  }
  // lecture de quelques propriétes (cart et customer)
  public function __get($property){
    if ($property == 'cart'){
      if (!isset($this->cart))
	$this->cart = new $this->cart_class($this);
      return $this->cart;
    }
    if ($property == 'customer'){
      if (!isset($this->customer))
	$this->customer = new $this->customer_class($this);
      return $this->customer;
    }
    return parent::__get($property);
  }
  // initialisation des propriétés
  //
  public function initOptions() {
    parent::initOptions();
    $labpath = 'Seolan_Module_Cart_Cart';
    $group_shop = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Cart_Cart','modulename');
    $this->_options->setDefaultGroup("$group_shop : Base de données");
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel($labpath,'productstable'), 'productstable', 'table', array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel($labpath,'table'), 'table', 'table', array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel($labpath,'orderlinestable'), 'orderlinestable', 'table', array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel($labpath,'deliverytable'), 'deliverytable', 'table', array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel($labpath,'coupontable'), 'coupontable','table',array('emptyok'=>false));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel($labpath, 'productlabel'), 'labelfield', 'field', array('table'=>'productstable'));
    $this->_options->setOpt('Reference','referencefield','field',array('table'=>'productstable'));
    $this->_options->setOpt('Prix','pricefield','field',array('table'=>'productstable'));
    $this->_options->setOpt('Champ TVA','tvafield','field',array('compulsory'=>false,'table'=>'productstable'));
    $this->_options->setOpt('Activation Promo','is_promofield','field',array('compulsory'=>false,'table'=>'productstable','compulsory'=>false));
    $this->_options->setOpt('Promo','promofield','field',array('compulsory'=>false,'table'=>'productstable','compulsory'=>false));
    $this->_options->setOpt('Poids','deliveryweight','field',array('compulsory'=>false,'table'=>'productstable'));
    $this->_options->setOpts([
      "$group_shop : Paramètres" => [
        'edeliv_download_limit' => [
          'label' => "Limite de téléchargements des articles virtuels",
          'comment' => "Nombre de téléchargement limité des articles achetés dématérialisés",
          'type' => 'text',
          'level' => 'rvw',
        ],
        'acompte' => [
	  'label' => \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Cart_Cart','acompte'),
          'comment' => "Paramétrage NON testé !!",
          'type' => 'text',
        ],
      ],
      "$group_shop : Paiement par chèque" => [
        'check_order_txt' => [
          'label' => "Ordre à inscrire sur le chèque",
          'comment' => "Ordre auquel le client doit adresser son chèque",
          'type' => 'text',
          'level' => 'rvw',
        ],
        'check_address_txt' => [
          'label' => "Adresse pour le paiement par chèque",
          'type' => 'text',
          'options' => [
            'cols' => 40,
            'rows' => 4,
          ],
          'level' => 'rvw',
        ],
      ],
    ]);
    $this->_options->setOpts(static::getCommonOptions());
  }

  /// Options communes au wizard (installateur) et aux propriétés du module
  public static function getCommonOptions() {
    $labpath= 'Seolan_Module_Cart_Cart';
    $labpathgen = 'Seolan_Core_General';
    $group_shop = \Seolan\Core\Labels::getTextSysLabel($labpath,'modulename');
    return [
      "$group_shop : Emails" => [
        'sender' => [
          'label' => \Seolan\Core\Labels::getTextSysLabel($labpathgen,'sender'),
          'comment' => 'Adresse email à laquelle les clients pourront répondre',
          'default' => 'boutique@'.$_SERVER['SERVER_NAME'],
          'level' => 'rwv',
        ],
        'sendername' => [
          'label' => \Seolan\Core\Labels::getTextSysLabel($labpathgen,'sendername'),
          'comment' => "Nom de l'émetteur des emails de la boutique",
          'default' => 'Boutique '.\Seolan\Core\Ini::get('societe'),
          'level' => 'rwv',
        ],
        'send_new_order_mail' => [
          'label' => 'Alerter l\'administrateur des commandes en cours',
          'comment' => 'Envoi d\'un mail à l\'administrateur à chaque nouvelle commande en cours sur la boutique',
          'type' => 'boolean',
          'default' => true,
          'level' => 'rwv',
        ],
        'backofficeemail' => [
          'label' => \Seolan\Core\Labels::getTextSysLabel($labpath,'backofficeemail'),
          'comment' => "Adresse(s) mail(s) du ou des administrateur(s) boutique",
          'default' => 'commandes@'.$_SERVER['SERVER_NAME'],
          'level' => 'rwv',
        ],
      ],
      "$group_shop : Paramètres" => [
        'moid_customers' => [
          'label' => "Module des utilisateurs boutique",
          'comment' => "Ce module sera utilisé pour générer les formulaires client (login, adresse...)",
          'type' => 'module',
          'options' => ['toid' => XMODFRONTUSERS_TOID, 'compulsory' => true],
        ],
        'alias_order' => [
          'label' => "Alias du tunnel de vente",
          'comment' => "Alias qui sera créé dans le gestionnaire de rubrique lié au module boutique",
          'type' => 'text',
          'default' => 'order',
        ],
        'alias_shop' => [
          'label' => "Alias de la boutique",
          'comment' => "Alias qui sera créé dans le gestionnaire de rubrique lié au module boutique",
          'type' => 'text',
          'default' => 'home',
          'level' => 'rwv',
        ],
        'coupon_enabled' => [
          'label' => "Activation des coupons",
          'comment' => "Si l'on souhaite que les clients aient accès à des réductions paramétrées dans le module des coupons",
          'type' => 'boolean',
          'default' => false,
        ],
        'always_save_cart_in_database' => [
          'label' => "Sauvegarder tous les paniers",
          'comment' => "Tous les paniers seront sauvegardés à partir du moment où un client ajoute un article au panier",
          'type' => 'boolean',
          'default' => false,
        ],
      ],
      "$group_shop : Paiement par chèque" => [
        'payment_by_check_enabled' => [
          'label' => "Activation du paiement par chèque",
          'comment' => "Si l'on souhaite que les clients puissent payer leur commande par chèque",
          'type' => 'boolean',
          'default' => false,
        ],
      ],
    ];
  }
  
  function __load($moid) {
    parent::load($moid);
    //global $TZR_REWRITING;
    //foreach (['cart','address','validate','payment'] as $step) {
      //$TZR_REWRITING["order/$step.html"] = "alias=$this->alias_order&step=$step";
    //}
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['shop1']=array('none','ro','rw','rwv','admin');
    $g['sendOrder']=array('none','ro','rw','rwv','admin');
    $g['edeliv']=array('none','ro','rw','rwv','admin');
    $g['view'] =array('none','ro','rw','rwv','admin');
    $g['viewShort']=array('none','ro','rw','rwv','admin');
    $g['viewOrder']=array('none','ro','rw','rwv','admin');
    $g['procOrder']=array('none','ro','rw','rwv','admin');
    $g['del']=array('rwv','admin');
    $g['paid']=array('none','ro','rw','rwv','admin');
    $g['spcheck']=array('none','ro','rw','rwv','admin');
    $g['ciccheck']=array('none','ro','rw','rwv','admin');
    $g['cacheck']=array('none','ro','rw','rwv','admin');
    $g['wacheck']=array('none','ro','rw','rwv','admin');
    $g['delItem']=array('none','ro','rw','rwv','admin');
    $g['addItem']=array('none','ro','rw','rwv','admin');
    $g['modifyCart']=array('none','ro','rw','rwv','admin');
    $g['saveUser']=array('none','ro','rw','rwv','admin');
    $g['paybox']=array('none','ro','rw','rwv','admin');
    $g['step_print']=array('ro','rw','rwv','admin');
    $g['paymentReturn']=array('none','ro','rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function, $group);
  }

  /// Ajoute l'action d'impression à chaque commande passée
  function browseActionsForLine(&$r,&$i,&$oid,&$oidlvl,&$noeditoids){
    parent::browseActionsForLine($r, $i, $oid, $oidlvl, $noeditoids);
    if ($r["lines_o$this->orderuserfield"][$i]->html)
      $this->browseActionForLine('print', $r, $i, $oid, $oidlvl, $edit=false);
  }
  function browseActionPrintText($linecontext=null){
    return \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','print');
  }
  function browseActionPrintIco($linecontext=null){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','print','csico');
  }
  function browseActionPrintLvl($linecontext=null){
    return $this->secGroups('display');
  }
  function browseActionPrintHtmlAttributes(&$url,&$text,&$icon, $linecontext){
    return 'class="cv8-dispaction" target="print"';
  }
  function browseActionPrintUrl($usersel, $linecontext=null){
    if ($this->moid_customers) // refonte 2017
      return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().($usersel?'&_bdxnewstack=1':'').'&moid='.$this->_moid.'&function=step_print&order=<oid>';
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().($usersel?'&_bdxnewstack=1':'').'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=viewOrder&template=xmodcart/print.html';
  }


  /***************************************************************************/
  /*               SECTIONS FONCTIONS 2017 DE LA BOUTIQUE                    */
  /***************************************************************************/

  /// Rend la liste des fonctions utilisables dans le gestionnaire de rubriques en mode fonction (tableau de paires fonction=>label)
  function getUIFunctionList() {
    return [
      'shopTunnel' => 'Tunnel de commande',
    ];
  }

  /// Paramètre des pages de boutique accessibles au client
  function UIEdit_shopTunnel($ar = []) {
    // TODO Mettre tous les paramètres réglables par l'utilisateur ici
    //
    // Paramètres :
    // - Page d'accueil de retour boutique (pour le lien CONTINUER VOS ACHATS)
    // - Colonnes du panier à afficher (prix ht, ttc)
    //
    //$params = [
      //'__shopHomePage' => [
        //'label' => 'Retour à la boutique / continuer vos achats',
        //'ptype' => '\Seolan\Field\Link\Link',
        //'default' => 'home',
      //],
      //'__cartColumns' => [
        //'label' => 'Colonnes du panier à afficher',
        //'ptype' => '\Seolan\Field\Link\Link',
        //'default' => 'home',
      //],
      //'__orderColumns' => [
        //'label' => 'Colonnes de la commande à afficher',
        //'ptype' => '\Seolan\Field\Link\Link',
        //'default' => 'home',
      //],
      //'__printColumns' => [
        //'label' => 'Colonnes de la commande à imprimer',
        //'ptype' => '\Seolan\Field\Link\Link',
        //'default' => 'home',
      //],
    //];
    return ['_comment' => 'Rien à configurer pour le tunnel de vente'];
  }

  function UIProcEdit_shopTunnel() {
  }

  //function UIParam_shopTunnel($ar = []) {
  //}

  /**
   * Section fonction par laquelle passe toutes les étapes de commande
   *
   * FIXME le top serait en fait de pouvoir surcharger les alias d'un site afin de pouvoir afficher une section dynamique en fonction de l'alias
   *   Exemple : alias=order/cart ou alias=order/payment ...
   */
  function UIView_shopTunnel($ar = []) {
    //if (\Seolan\Core\Shell::admini_mode()) return;
    $p = new \Seolan\Core\Param($_GET, ['step' => 'cart']);
    $step = $p->get('step');
    if (!method_exists($this, "step_$step")) {
      $step = 'cart';
    }
    // Appel de la fonction step_XXX()
    try {
      $section = $this->{"step_$step"}($ar);
    } catch (Exception $e) {
      \Seolan\Core\Logs::critical(__METHOD__, $e->getMessage(), true);
    }
    $section['step'] = $step;
    $section['shop'] = $this;
    // labels FrontUser pour les étapes de création de compte
    $section['labels'] = $this->customers->getLabels();
    return $section;
  }

  public function isStepAccessible($step = 'cart') {
    switch ($step) {
      case 'payment':
        return false;
      case 'validate':
        if (!$this->customer->authentified || !$this->customer->is_address_valid)
          return false;
      case 'address':
        if ($this->cart->is_empty)
          return false;
    }
    return true;
  }

  /// Etape d'affichage du panier dans la section fonction
  protected function step_cart($ar) {
    $this->cart->save();
    return [];
  }

  /// Etape d'identification/saisi de l'utilisateur dans la section fonction
  protected function step_address($ar) {
    $this->requireCart();
    // Si l'utilisateur est connecté mais que l'adresse n'est pas valide, alors il est redirigé vers la page de saisie de son adresse
    if ($this->customer->authentified && !$this->customer->is_address_valid) {
      \Seolan\Core\Shell::alert(__("Merci de renseigner une adresse valide avant de pouvoir poursuivre votre commande",'\Seolan\Module\CartV2\CartV2'),'warning');
      $GLOBALS['START_CLASS']::redirectTo($this->customers->getPanelUrl('editaddress').'&next='.urlencode($this->getStepUrl('address')));
    }
    return [];
  }

  /// Etape d'acceptation des CGV dans la section fonction
  protected function step_validate($ar) {
    $this->requireCart();
    $this->requireUser();
    $selectedfields = array_keys($this->getStepValidateFields());
    return [
      'form' => $this->cart->linked_order_oid() ?
        $this->edit(['selectedfields' => $selectedfields, 'oid' => $this->cart->linked_order_oid]) :
        $this->insert(['selectedfields' => $selectedfields]),
    ];
  }

  /// Etape enregistrant la commande en base et affichant les boutons de paiement dans la section fonction
  protected function step_payment($ar) {
    $p = new \Seolan\Core\Param([]);
    $this->requireUser();
    $order = $this->requireOrder();
    // Si un message en provenance du paiement est en attente
    if ($p->is_set('paymentStatus')) {
      switch ($p->get('paymentStatus')) {
        case 'success':
          \Seolan\Core\Shell::alert(__("Merci pour votre commande !",'\Seolan\Module\CartV2\CartV2'), 'success');
          \Seolan\Core\Shell::alert(__("Votre paiement a bien été accepté.",'\Seolan\Module\CartV2\CartV2'), 'success');
          return [
            'order' => $order,
          ];
        case 'check':
          $order->changePaymentType($order::PAYMENT_TYPE_CHECK);
          $order->sendByMail($order->customer->email, _v('xmodcart.yourorder').' '.$_SERVER['SERVER_NAME']);
          $order->sendByMail($this->backofficeemail, _v('xmodcart.paimentreturn').' '.$_SERVER['SERVER_NAME']);
          \Seolan\Core\Shell::alert(__("Votre paiement par chèque a bien été pris en compte",'\Seolan\Module\CartV2\CartV2'), 'success');
          \Seolan\Core\Shell::alert(__("Afin de valider votre paiement, merci d'envoyer votre chèque d'un montant de %amount à l'ordre de :".
            "<br><br>%order<br><br> et à l'adresse : ".
            "<br><br>%address", '\Seolan\Module\CartV2\CartV2', [
            'amount' => '<b>'.\Seolan\Core\Lang::price_format($order->amount).'</b>',
            'order' => '<b>'.$this->check_order_txt.'</b>',
            'address' => '<b>'.$this->check_address_txt.'</b>',
          ]), 'warning');
          $this->cart->clear();
          return [
            'order' => $order,
          ];
        default:
          \Seolan\Core\Shell::alert(__("Opération de paiement annulée",'\Seolan\Module\CartV2\CartV2'));
          break;
      }
    }
    // Si la commande a bénéficié d'une réduction pour le total
    if ($order->amount == 0) {
      $order->changePaymentType($order::PAYMENT_TYPE_GIFT);
      $order->changePaymentStatus($order::PAYMENT_STATE_PAID);
      return [
        'order' => $order,
      ];
    }
    return [
      'order' => $order,
      'payment_forms' => $order->paymentForms(),
    ];
  }

  /// Etape d'impression de la commande dans la section fonction
  public function step_print($ar) {
    $order = $this->requireOrder();
    if (!$order->customer->oid)
      die("Cette commande n'appartient à aucun utilisateur");
    \Seolan\Core\Shell::toScreen1('shop', $shop = ['order' => $order]);
    \Seolan\Core\Shell::setTemplates('Module/CartV2.shop-order-print.html');
  }

  /// Si le panier est vide on retourne à l'affichage du panier
  /// FIXME pas mieux de retourner à la boutique avec un message d'erreur ?
  protected function requireCart() {
    if ($this->cart->is_empty) {
      $this->gotoStep('cart');
    }
  }

  /// Si l'utilisateur n'est pas connecté on lui demande de saisir son adresse
  protected function requireUser() {
    if (!$this->customer->authentified) {
      $this->gotoStep('address');
    }
  }

  /**
   * Va chercher la commande demandée via URL ou bien celle en cours dans le panier
   * @return XShopOrder Objet commande demandé via URL ou panier
   */
  protected function requireOrder() {
    $p = new \Seolan\Core\Param([]);
    // Cas d'un re-paiement de commande
    if ($p->is_set('order')) {
      $order_oid = $p->get('order');
    // Cas d'une commande avec un panier en cours
    } elseif (!$this->cart->is_empty) {
      $order_oid = $this->cart->databaseSave();
    } else {
      $this->gotoStep('cart');
    }
    $order = $this->display($order_oid);
    $order->checkCartCompatibility();
    return $order;
  }

  /// Champs proposés au client lors de la validation de la commande
  public function getStepValidateFields() {
    return [
      'rem' => __("Précisions éventuelles à nous donner par rapport à votre commande"),
    ];
  }

  /// Retourne tous les MOID des modules monétiques actifs sur le site
  public function getMonetiqueMoids() {
    return \Seolan\Core\Module\Module::modlist(['toid' => $this->getMonetiqueToids(), 'noauth' => true])['lines_oid'];
  }

  /// Retourne tous les TOID des modules monétiques actifs sur le site
  public function getMonetiqueToids() {
    $monetique_toids = [];
    foreach (\Seolan\Core\Module\Module::$_modules as $toid => $module) {
      if (in_array('\Seolan\Module\Monetique\Monetique', getAncestors($module['CLASSNAME']))) {
        $monetique_toids[] = $toid;
      }
    }
    return $monetique_toids;
  }

  /// Appelée lors du retour automatique de la banque par XModMonetique
  public function paymentCallback($order_oid, $transaction) {
    // Quand on arrive ici, le certificat de la banque est vérifié
    // on peut donc donner libre accès à toutes les commandes sans que l'utilisateur soit logué
    $this->getOrder($order_oid)->paymentCallback($transaction);
  }

  /// retour banque utilisateur
  public function paymentReturn() {
    $orderOid = $this->afterPayment();
    if($orderOid) {
      $status = getDB()->fetchCol('select distinct status from TRANSACMONETIQUE where orderOid=?', [$orderOid]);
      if (in_array('success', $status)) {
          $this->cart->clear();
          \Seolan\Core\Shell::toScreen2('', 'payment', true);
          \Seolan\Core\Shell::setNext('alias='.$this->alias_shop);
      } else {
        // TODO : gestion du message d'erreur dans le template cart
        \Seolan\Core\Shell::toScreen2('', 'payment', false);
        $this->gotoStep('cart');
      }
    }
  }

  protected function afterPayment() {
    $cmdref = getSessionVar('cmdref');
    if (empty($cmdref)) {
      \Seolan\Core\Shell::setNext('alias='.$this->alias_shop);
    } else {
      return getDB()->fetchOne('select KOID from '.$this->table.' where '.$this->orderreffield.'=?', [$cmdref]);
    }
  }

    
  /// Objet de boutique utilisé par XModMonetique
  public function getMShopInfos() {
    $mshop = new \Seolan\Module\Monetique\Model\Shop();
    $mshop->moid = $this->_moid;
    $mshop->autoResponseMode = \Seolan\Module\Monetique\Monetique::SYNC_RESPONSE;
    $mshop->autoResponseCallBack = 'paymentCallback';
    return $mshop;
  }

  /// Redirige l'utilisateur vers une autre étape de la commande
  public function gotoStep($step = 'cart') {
    $GLOBALS['START_CLASS']::redirectTo($this->getStepUrl($step, false));
  }

  /// Renvoie le titre d'une étape de commande
  public function getStepLabel($step) {
    $step_labels = [
      'cart'     => __('Etape 1 : Panier', '\Seolan\Module\CartV2\CartV2'),
      'address'  => __('Etape 2 : Coordonnées', '\Seolan\Module\CartV2\CartV2'),
      'validate' => __('Etape 3 : Validation', '\Seolan\Module\CartV2\CartV2'),
      'payment'  => __('Etape 4 : Paiement', '\Seolan\Module\CartV2\CartV2'),
    ];
    return $step_labels[$step];
  }

  /// Renvoie l'URL d'une étape de commande
  public function getStepUrl($step = 'cart', $htmlentities = true) {
    return $GLOBALS['START_CLASS']::buildUrl([
      'alias' => $this->alias_order,
      'step' => $step,
    ], true, $htmlentities);
  }

  /// Renvoie le DISPLAY_OBJECT des frais de ports standard ou liés au pays de l'utilisateur
  public function getDefaultDeliveryRulesOid() {
    return getDB()->fetchOne("SELECT KOID FROM $this->deliverytable WHERE code='standard' AND LANG=?", [ TZR_DEFAULT_LANG ]);
  }

  /// TODO Retournerait la liste des variantes d'un produit
  public function getProductVariantes($product) {
    if (\Seolan\Core\Kernel::isAKoid($product, $this->productstable))
      $product = \Seolan\Core\DataSource\DataSource::objectDisplayHelper($product);
    return [
      //'varoid' => ['values'],
      //'varoid' => ['values'],
    ];
  }

  /**
   * Lignes affichées en bas de panier pour résumer le total de la commande
   */
  public function getTotalLabels($total) {
    return [
      'originalamount' => __('Montant du panier', '\Seolan\Module\CartV2\CartV2'),
      'coupon'         => __('Coupon', '\Seolan\Module\CartV2\CartV2'),
      'delivery'       => __('Frais de port', '\Seolan\Module\CartV2\CartV2'),
      'reduction'      => __('Remise', '\Seolan\Module\CartV2\CartV2'),
      'amountHT'       => __('Montant total HT', '\Seolan\Module\CartV2\CartV2'),
      'taxes'          => __('TVA', '\Seolan\Module\CartV2\CartV2'),
      'amount'         => __('Montant total TTC', '\Seolan\Module\CartV2\CartV2'),
    ];
  }

  /**
   * @param string KOID de la commande à retourner
   * @return XShopOrder
   */
  public function display($ar=null) {
    if (is_string($ar))
      $ar = ['oid'=>$ar];
    $ar['_options'] = ['error'=>'return'];
    return new $this->order_class($this, parent::display($ar));
  }

  /**
   * Renvoie une commande sans filtrer selon l'utilisateur en cours
   * @param string KOID de la commande à retourner
   * @return XShopOrder
   */
  public function getOrder($oid) {
    $this->disableFilter();
    $order = $this->display($oid);
    $this->enableFilter();
    return $order;
  }
  
  
  /* Cas absence session utilisateur
  * utilisé par le retour boutique Monetique par exemple
  */
  public function getOrder_anonymousSession($oid) {
    $ar = [
      'oid' => $oid,
      '_options' => ['local'=>true, 'error'=>'return'],
    ];
    $order = $this->xset->display($ar);
    return $order;
  }
  public function getOrderAmount($oid) {
    $order = $this->getOrder_anonymousSession($oid);
    $amount = $order["o{$this->orderamountfield}"]->raw;
    \Seolan\Core\Logs::critical(__METHOD__ . ' Order amount= ' . $amount);
    return $amount;
  }
  
  /**
   * Le filtre est désactivale (selon le contexte ?)
   */
  public function getFilter($instanciate=true,$ar=[]) {
    if (!$this->_filter_active)
      return '';
    if(\Seolan\Core\Shell::admini_mode()){
      return parent::getFilter($instanciate,$ar);
    }
    // Ne donne accès qu'aux commandes des autres  (?) utilisateurs ou bien à la commande en cours dans le panier
    $pubFilter = "$this->orderuserfield='".$this->customer['oid']."' OR $this->orderreffield='".$this->cart->reference."'";
    if (isset($this->filter) && !empty($this->filter)) {
      $this->filter .= " AND ($pubFilter)";
    } else {
      $this->filter = $pubFilter;
    }
    \Seolan\Core\Logs::debug(__METHOD__,$this->filter);
    return parent::getFilter($instanciate,$ar);
  }
  /// Permet d'activer le filtrage sur les objets du module (actif par défaut)
  function enableFilter() {
    $this->_filter_active = true;
  }
  /// Permet de désactiver le filtrage sur les objets du module
  function disableFilter() {
    $this->_filter_active = false;
  }
  /***************************************************************************/
  /*            FIN SECTIONS FONCTIONS 2017 DE LA BOUTIQUE                   */
  /***************************************************************************/

  /// FIXME cette fonction n'a pas l'air générique du tout et où est-elle utilisée ?
  function sendOrder($ar=NULL) {
    \Seolan\Core\Logs::deprecated("Fonction non générique");
    global $XSHELL;
    global $cde;
    $p=new \Seolan\Core\Param($ar, array());

    // pour l'instant la TVA est la meme pour tous les produits... ensuite on ira la cherche dans la table article.
    // dans la boucle foreach en dessous
    $tva = str_replace(',','.',$p->get('tva'));
    $tva = $tva/100;
    if(issetSessionVar('cart')) {
      $k = getSessionVar('cart');
      foreach($k as $i=>$qte) {
	if(substr($i, 0, 4) == $this->productstable) {
	  $this->xset->display( array( 'tplentry'=>'tarifcde',
                                       'oid'=>$i,
                                       'genauto'=>0,
                                       'genempty'=>0,
                                       'genraw'=>1
                                ));
	  
	  $cde = $XSHELL->tpldata['tarifcde'];
	  $cl=$this->orderClassName;
	  $n = new $cl();
	  $mth = $this->orderMethodName;
	  $n->$mth(array(oidcomm=>$p->get('oidcomm')));
	  $corpsMsg .= 'Produit : '.$cde[$p->get('ChpArticle')]."\n";
          $corpsMsg .= 'Prix unitaire HT : '.$cde[$p->get('ChpPrixUnit')]."\n";
          $corpsMsg .= 'Quantité : '.$qte."\n";
          $corpsMsg .= 'Prix total HT : '.str_replace(',','.',$cde[$p->get('ChpPrixUnit')])*$qte."\n";
          $corpsMsg .= 'Prix total TTC : '.str_replace(',','.',$cde[$p->get('ChpPrixUnit')])*$qte*(1+$tva)."\n";
	  $corpsMsg .= "----------------------------------------------\n";
	  // vidage du panier pour l'article correspondant 
	  clearSessionVar('cart',$i);
	}
      }
      $corpsMsg = $p->get('infosUtil')."\nNous vous remercions pour votre commande:\n\n".$corpsMsg."\n\n" ;
      // MAIL a l'administrateur
      mail($this->email,'Commande du '.date('d/m/Y'), $corpsMsg.$p->get('corpsMsgSuppl'), 'From: '.$p->get('emailUtil'));
      // MAIL a Renaud
      mail(TZR_DEBUG_ADDRESS,'Commande du '.date('d/m/Y'), $corpsMsg.$p->get('corpsMsgSuppl'), 'From: '.$p->get('emailUtil'));      
      // MAIL a l'utilisateur
      if(preg_match('/[a-z0-9_\-\.]+@[a-z0-9_\-\.]+/i',$p->get('emailUtil'))) {
        mail($p->get('emailUtil'),'Votre commande du '.date('d/m/Y'), 
             $corpsMsg.$p->get('corpsMsgSuppl'), 'From: '.$this->email);
      }
    }
  }

  function _computeProdDelivery($prod, $deliv) {
    \Seolan\Core\Logs::deprecated('Utiliser plutôt la méthode XShopItem->delivery()');
    $delivery = $prod['odeliv']->raw;
    return $delivery;
  }

  function _computeOrderDelivery($nb, $deliv, $total_weight=0) {
    \Seolan\Core\Logs::deprecated('Utiliser plutôt la méthode XShopCart->delivery()');
    $utable=$deliv['outab']->alltable;
    $found=false;
    $i=0;
    $delivery=0;
    // calcul des frais par unité
    while(!$found && ($i<count($utable))) {
      $pmin=$utable[$i][0];
      $pmax=$utable[$i][1];
      $pforf=   $utable[$i][2];
      $pperunit=   $utable[$i][3];
      if(($pmin<=$nb) && ($pmax>=$nb)) $found=true;
      else $i++;
    }
    if($found) {
      $delivery+=$pforf+$pperunit*$nb;
    }
    // calcul des frais par poids
    $weighttable=$deliv['owtab']->alltable;
    $found=false;
    $i=0;
    while(!$found && ($i<count($weighttable))) {
      $pmin=$weighttable[$i][0];
      $pmax=$weighttable[$i][1];
      $p=   $weighttable[$i][2];
      $p2=   $weighttable[$i][3];
      if(($pmin<=$total_weight) && ($pmax>=$total_weight)) $found=true;
      else $i++;
    }
    if($found) {
      $delivery+=$p+$p2*$total_weight;
    }
    return $delivery;
  }

  /**
   * Modifie la commande en fonction des réductions renseignées par l'utilisateur
   */
  function _computeOrderReduction(&$result, &$total_remise, &$total_delivery, &$total_amount, &$total_tva) {
    \Seolan\Core\Logs::deprecated('Utiliser plutôt la méthode XShopCart->reduction()');
    $coupon = getSessionVar('coupon');
    $freedeliv = 0;
    if(!empty($this->coupontable)) {
      $result['coupon_active']=true;
      $q = $this->xset_coupon->select_query(array("cond"=>array("Code"=>array("=",$coupon),
                                                 "datet"=>array(">=",date('Y-m-d')),
                                                 "datef"=>array("<=",date('Y-m-d')))));
      $rs = getDB()->select($q);
      if ($ors = $rs->fetch()) {
        $coupoid = $ors['KOID'];
        $rcoupon = $this->xset_coupon->display(array("oid"=>$coupoid,"tplentry"=>TZR_RETURN_DATA));
        // Modif CD 2012-11-21 pour les coupons à utilisateur unique
        if (!$this->isCouponValid($rcoupon, $user)) {
          $result['coupon_message'] = 'Ce coupon a déjà été utilisé';
          return;
        }
        $sommemin = $rcoupon["omina"]->raw;
        $sommedisc = $rcoupon['odisc']->raw;
        $freedeliv = $rcoupon['ofraisportsoffert']->raw ;
        $result['coupon'] = $coupon;
        $result['coupon_message'] = $rcoupon['olibelle']->raw;
        if (($total_amount+$total_tva)>=$sommemin) {
          $result['coupon_oid']=$rcoupon['oid'];
          if ($rcoupon['ocadeau']->raw == 1) $result['coupon_cadeau'] = 1;
          $result['total_coupon'] = $sommedisc + ($total_amount+$total_tva)*$rcoupon["operc"]->raw/100;
          $total_remise += $result['total_coupon'];
        } else {
          $result['coupon_message']=\Seolan\Core\Labels::getTextSysLabel('\Seolan\Module\CartV2\CartV2','coupon_order_too_low');
          $result['total_coupon']=0;
          if ($rcoupon['cadeau']->raw == 1) $result['coupon_cadeau'] = 2;
        }
        if ($freedeliv == 1) $total_delivery = 0;
      } elseif (!empty($coupon)) {
        $result['coupon_message'] = 'Ce coupon n\'existe pas ou est périmé';
      }
    }
  }

  
  function modifyCart($ar) {
    $p=new \Seolan\Core\Param($ar, array('tplentry'=>'cart'));
    $charset=\Seolan\Core\Lang::getCharset();
    $deloid=$p->get('deloid');
    $qty=$p->get('qty');
    // suppression d'un item dans le caddie
    if(isset($qty)) {
      foreach($qty as $i => $var1) {
       foreach($var1 as $j => $q) {
         if($charset!=TZR_INTERNAL_CHARSET) convert_charset($j,$charset,TZR_INTERNAL_CHARSET);
         if(preg_match('@^([0-9]+)$@',$q)) $this->cart[$i][stripslashes($j)]=$q;
       }
      }
    }
    if(!empty($deloid)) {
      foreach($deloid as $i => $var1) {
       if(is_array($var1)) {
         foreach($var1 as $j => $q) {
           if($charset!=TZR_INTERNAL_CHARSET) convert_charset($j,$charset,TZR_INTERNAL_CHARSET);
           unset($this->cart[$i][stripslashes($j)]);
         }
       } else {
           unset($this->cart[$i]);
       }
      }
    }
    $coupon=$p->get("coupon");
    setSessionVar("coupon",$coupon);
    $this->cart->save();

    // Affiche le message du coupon si disponible
    if ($this->cart->total['coupon']['valid'])
      \Seolan\Core\Shell::alert(__("Coupon <b>%code</b> activé : <em>%message</em>",'\Seolan\Module\CartV2\CartV2',[
        'code' => $this->cart->active_coupon_code,
        'message' => $this->cart->total['coupon']['message']]), 'success');
    elseif ($this->cart->total['coupon']['message'])
      \Seolan\Core\Shell::alert($this->cart->total['coupon']['message']);
  }

  /// On garde cette fonction pour la rétro compatibilité
  function view($ar=NULL) {
    \Seolan\Core\Logs::deprecated('Utiliser la section fonction XModCart->shopTunnel()');
    global $XSHELL;
    $p=new \Seolan\Core\Param($ar, array("tplentry"=>"cart"));
    $tplentry=$p->get("tplentry");
    $remise=0;
    $lang=\Seolan\Core\Shell::getLangData();
    $kernel = new \Seolan\Core\Kernel();
    $products = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->productstable);
    // recherche utilisateur
    $user=\Seolan\Core\User::get_user();
    $cust = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='."USERS");
    // sauvegarde des preferences utilisateur
    $xdeliv = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->deliverytable);
    $tva_is_applicable=true;
    $redeliv=array();		// frais de livraison
    if($user->uid()!=TZR_USERID_NOBODY) {
      $cust->edit(array("tplentry"=>"cust","oid"=>$user->uid()));
      $ruser=$cust->display(array("tplentry"=>TZR_RETURN_DATA,"oid"=>$user->uid()));
      if($ruser['ofpays']->raw) $paysoid=$ruser['ofpays']->raw; 
      else $paysoid=$ruser['opays']->raw;
      $tmp1=\Seolan\Core\Kernel::getTable($paysoid);
      if(\Seolan\Core\System::tableExists($tmp1)) {
	$xpays=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$tmp1);
	$pays=$xpays->display(array("oid"=>$paysoid,"tplentry"=>TZR_RETURN_DATA));
	$tva_is_applicable=($pays['otzone']->raw == 1);
	$rdeliv=$xdeliv->display(array('oid'=>$pays['odeliv']->raw,'tplentry'=>TZR_RETURN_DATA,"_options"=>array("error"=>"return")));
	if(!is_array($rdeliv)) $rdeliv=array();
      } 

      // recherche des frais de livraison
      $remise = $ruser['o'.$this->userdiscount]->raw;

      \Seolan\Core\Shell::toScreen1('dcust',$ruser);
      if(empty($rdeliv)) {
	$tmp1=getDB()->fetchRow("select * from ".$this->deliverytable." where code ='standard' and LANG=?",array($lang));
	if($tmp1) {
	  $rdeliv=$xdeliv->display(array('oid'=>$tmp1['KOID'],
					 'tplentry'=>TZR_RETURN_DATA));
	} else $rdeliv=array();
      }
    } else {
      $cust->input(array("tplentry"=>"cust"));
    }
    
    $total_articles=0;
    $total_amount=0;
    $nb_miss_art=0;
    $total_remise=0;
    $total_tva=0;
    $total_delivery=0;
    $total_weight=0;
    $line=0;
    if(issetSessionVar('cart')) {
      $cart=getSessionVar("cart");
      $edelivonly=true;
      $anyedeliv=false;
      foreach($cart as $k => $vari) {
	foreach($vari as $rvar => $q) {
	  if($q>0) {
	    if(!$kernel->objectExists($k)) {
	      $nb_miss_art++;
	    } else {
	      $product=$products->display(array('oid'=>$k,tplentry=>TZR_RETURN_DATA));
	      $result['lines_oid'][$line]=$k;
	      $result['lines_object'][$line]=$product;
	      $result['lines_referencefield'][$line]=$product['o'.$this->referencefield]->html;
	      $result['lines_labelfield'][$line]=$product['o'.$this->labelfield]->html;
	      if($tva_is_applicable && !empty($product['o'.$this->tvafield]->raw)) {
		// recherche du taux de tva
		$tvatable=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$product['o'.$this->tvafield]->raw);
		$tvarate=$tvatable->display(array("oid"=>$product['o'.$this->tvafield]->raw,"tplentry"=>TZR_RETURN_DATA));
		// calcul des divers taux de tva
		$result['lines_tvafield'][$line]=$tvarate['opourc']->raw;
		$result['lines_otvafield'][$line]=$tvarate['opourc'];
		$result['lines_tvarate'][$line]=$tvarate['opourc']->raw/100.00;
		$tva=$result['lines_tvarate'][$line];
	      } else {
		$product['o'.$this->tvafield]->link['opourc']->raw="0";
		$product['o'.$this->tvafield]->link['pourc']="0.00 %";
		$result['lines_tvafield'][$line]="0.00";
                $result['lines_otvafield'][$line]=$product['o'.$this->tvafield];
		$result['lines_tvarate'][$line]=0;
		$tva=0;
	      }
	      $result['lines_variantfield'][$line]=$this->_idx2txt($rvar);
	      $result['lines_variantoid'][$line]=$rvar;
	      // cas des revendeurs
	      if($ruser['odistr']->raw == 1) {
		$result['lines_oldpricefield'][$line]=$product['o'.$this->pricefield]->raw;
		$result['lines_pricefield'][$line]=$product['o'.$this->discfield]->raw*(1-($remise/100));
		$result['lines_pricefieldttc'][$line]=$result['lines_pricefield'][$line]*(1.0+$tva);
		$total_remise+=($result['lines_oldpricefield'][$line]-$result['lines_pricefield'][$line])*$q;
	      // cas du grand public
	      } else {
		$result['lines_oldpricefield'][$line]=$product['o'.$this->pricefield]->raw;
		if(!empty($product['o'.$this->promofield]->raw)) {
		  $result['lines_pricefield'][$line]=$product['o'.$this->promofield]->raw;
		} else {
		  $result['lines_pricefield'][$line]=$product['o'.$this->pricefield]->raw;
		}
		$result['lines_pricefieldttc'][$line]=$result['lines_pricefield'][$line]*(1.0000+$tva);
	      }
	      $total_weight+=$product['o'.$this->deliveryweight]->raw*$q;
	      $prod_deliv = $this->_computeProdDelivery($product,$rdeliv);
	      $total_delivery+=$prod_deliv*$q;
	      $result['lines_totalfield'][$line]=$result['lines_pricefield'][$line]*$q;
	      $result['lines_totalfieldttc'][$line]=$result['lines_pricefield'][$line]*$q*(1+$tva);
	      $total_tva+=$result['lines_totalfield'][$line]*$tva;
	      $result['lines_oo'][$line]=$product;
	      if(empty($product['o'.$this->edelivfield]->url)) $edelivonly=false;
	      else $anyedeliv=true;
	      $result['lines_qty'][$line]=$q;
	      $total_articles+=$q;
	      $total_amount+=$result['lines_totalfield'][$line];
	      $line++;
	    }
	  }
	}
      }
    }

    // calcul des frais de livraison
    if($total_articles>0 && !$edelivonly) {
      // il n'y a des frais de livraison que si au moins un article
      $total_delivery+=$this->_computeOrderDelivery($total_articles, $rdeliv, $total_weight);
    }
    if($tva_is_applicable && !empty($rdeliv['otva']->raw)) {
      $tvatable=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$rdeliv['otva']->raw);
      $tvarate=$tvatable->display(array("oid"=>$rdeliv['otva']->raw,"tplentry"=>TZR_RETURN_DATA));
      $delivery_applicable_tva = $tvarate['opourc']->raw;
      $tva = $delivery_applicable_tva/100.00;
    } else
      $delivery_applicable_tva = 0;

    $this->_computeOrderReduction($result, $total_remise, $total_delivery, $total_amount, $total_tva);

    $total_delivery_tva = $tva*$total_delivery;
    $total_delivery_ttc = $total_delivery_tva+$total_delivery;
    $result['total_delivery']=sprintf("%.2f",$total_delivery);
    $result['total_delivery_tva']=sprintf("%.2f",$total_delivery_tva);
    $result['total_delivery_ttc']=sprintf("%.2f",$total_delivery_ttc);

    $result['total_articles']=$total_articles;
    $result['total_remise']=sprintf("%.2f",$total_remise);

    // totaux généraux
    $total_tva+=$total_delivery_tva;
    $result['total_cart']=sprintf("%.2f",$total_amount);
    // Donne des résultats incohérents...
    $total_amount+=$total_delivery-$total_remise;//$total_remise/(1+$tva);
    $result['total_amount']=sprintf("%.2f",$total_amount);
    $total_ttc=$total_amount+$total_tva;
    $result['total_ttc']=sprintf("%.2f",$total_ttc);
    $result['nb_miss_art'] = $nb_miss_art;
    $result['cmdref']=getSessionVar("cmdref");
    $result['anyedeliv']=$anyedeliv;
    $result['edelivonly']=$edelivonly;
    $result['total_tva']=sprintf("%.2f",$total_tva);
    if(isset($this->xset->desc['rem'])) $result['remark_active']=true;
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }

  // prise en compte de la commande en base des commandes. A l'issu de
  // cette fonction la commande n'est pas validee, mais elle est
  // renseignee en base de donnees
  //
  function procOrder($ar=NULL) {
    global $XSHELL;
    $p=new \Seolan\Core\Param($ar, array("tplentry"=>"cart"));
    $remise=0;
    $tplentry=$p->get("tplentry");

    $this->view($ar);
    $user=\Seolan\Core\User::get_user();
    $result=\Seolan\Core\Shell::from_screen($tplentry);
    if(empty($result['total_articles'])) return "nocaddie";

    // insertion dans la table maitre
    $ma = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    if(empty($result['cmdref'])) return;
    if($result['total_ttc']==0) return;
    $ar1[$this->orderreffield]=$result['cmdref'];
    $ar1[$this->orderdatefield]=date('Y-m-d H:i:s');
    $ar1['F0004']=$result['total_ttc'];
    $ar1['F0005']=$result['total_delivery'];
    $ar1[$this->orderuserfield]=$user->uid();
    if(!empty($this->acompte) && !empty($this->alreadypaidfield)) {
      $ar1[$this->alreadypaidfield]=$result['acompte'];
    }
    $ar1[$this->paidfield]='N/A';
    $ar1['totalht']=$result['total_amount'];
    $ar1['tva']=$result["total_tva"];
    $ar1['disc']=$result["total_remise"];
    $ar1['coupon']=$result["coupon"];
    $ar1['tplentry']=TZR_RETURN_DATA;
    if(is_array($result['misc']))
      $ar1=array_merge($result['misc'],$ar1);
    $misc = $p->get('misc');
    if (is_array($misc))
      $ar1 = array_merge($ar1, $misc);
    $ra=$ma->procInput($ar1);
    $oid=$ra['oid'];
    \Seolan\Core\Logs::debug('\Seolan\Module\CartV2\CartV2::procOrder: order oid is '.$oid);
    // insertion des lignes de commande
    $li = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->orderlinestable);
    $lines=count($result['lines_referencefield']);
    for($i=0;$i<$lines;$i++) {
      $ar1=array();
      $ar1['orderid']=$oid;
      $ar1['ref']=$result['lines_referencefield'][$i];
      $ar1['price']= $result['lines_pricefield'][$i];
      $ar1['tva']=$result['lines_tvafield'][$i];
      $ar1['label']=$result['lines_labelfield'][$i];
      if(isset($result['lines_variantfield'][$i])) $ar1['label'].=",".$result['lines_variantfield'][$i];
      $ar1['label']=$ar1['label'];
      $ar1['totalp']=$result['lines_totalfield'][$i];
      $ar1['nb']=$result['lines_qty'][$i];
      $ar1['rem']='';
      $ar1['_options']=array("local"=>true);
      $ar1['tplentry']=TZR_RETURN_DATA;
      \Seolan\Core\Logs::debug('\Seolan\Module\CartV2\CartV2::procOrder: trying to line validate in order '.$oid
                   .' reference '.$result['lines_referencefield'][$i]);
      $ooid=$li->procInput($ar1);
      \Seolan\Core\Logs::debug('\Seolan\Module\CartV2\CartV2::procOrder: added '.var_export($ooid["oid"],true));

      $oo=&$result['lines_oo'][$i];

      // gestion de la livraison e-delivery
      if(!empty($oo['o'.$this->edelivfield]->url)) {
        $myebook=array();
        $myebook['DATET']=date('Y-m-d',strtotime('+'.$this->edelivdelay.' days'));
        $myebook['DATEF']='today';
        $myebook['_options']=array("local"=>true);
        $myebook['tplentry']='*return*';
        $myebook['EPROD']=$oo['oid'];
        $myebook['O1']=$oid;
        $edeliv = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->edeliv);
        $retoid=$edeliv->procInput($myebook);
	$downloadurl=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(false,true).'moid='.$this->_moid.'&function=edeliv&edeliv='.$retoid['oid'].'&eorder='.$oid.'&tplentry=br';
	$li->procEdit(array('EDELIV'=>$downloadurl,'oid'=>$ooid['oid'],'tplentry'=>TZR_RETURN_DATA));
      }
    }

    $postProc = $p->get("postProcOrder");
    if(!empty($postProc)) {
      if(in_array($postProc,array("postProcOrderPaybox"))) {
	$this->$postProc($result);
      }
    }
    $this->viewOrder(array("oid"=>$oid));
    clearSessionVar("cart");
    clearSessionVar("coupon");
    clearSessionVar("cmdref");
    sessionClose();
    // envoi d'un email de confirmation
    if($p->get("sendconfirm")=="1") {
      $this->_sendOrderEmail($oid, $this->backofficeemail,"Commande Boutique");
    }
    \Seolan\Core\Logs::debug("unsetting caddie");
    return $oid;
  }

  protected function postProcOrderPaybox($ar1) {
    $pbx_identifiant = \Seolan\Core\Ini::get("PBX_IDENTIFIANT");
    if(!empty($pbx_identifiant)) {
      $pbx_site = \Seolan\Core\Ini::get("PBX_SITE");
      $pbx_rang = \Seolan\Core\Ini::get("PBX_RANG");
      $total=$ar1['total_ttc']*100;
      $ref=$ar1['cmdref'];
      $user=\Seolan\Core\Shell::from_screen('dcust');
      $email=$user['oemail']->raw;
      $home=$GLOBALS['HOME_ROOT_URL'];
      $res=syscall(TZR_WWW_DIR."../cgi-bin/modulev2.cgi PBX_MODE=4 ".
		   "PBX_SITE=$pbx_site PBX_RANG=$pbx_rang PBX_IDENTIFIANT=$pbx_identifiant ".
		   "PBX_TOTAL=$total PBX_DEVISE=978 PBX_CMD=$ref PBX_LANGUE=FRA ".
		   "PBX_PAYBOX=https://tpeweb.paybox.com/cgi/MYchoix_pagepaiement.cgi ".
		   "PBX_ANNULE=$home PBX_REFUSE=$home PBX_EFFECTUE=$home \"PBX_RETOUR=ref:R;auto:A;tarif:M\" ".
		   "PBX_PORTEUR=$email PBX_OUTPUT=C");
      \Seolan\Core\Shell::toScreen2('paybox','form',$res);
    } else {
      $ibs_site = \Seolan\Core\Ini::get("IBS_SITE");
      $ibs_rang = \Seolan\Core\Ini::get("IBS_RANG");
      $total=$ar1['total_ttc']*100;
      $ref=$ar1['cmdref'];
      $user=\Seolan\Core\Shell::from_screen('dcust');
      $email=$user['oemail']->raw;
      $home=$GLOBALS['HOME_ROOT_URL'];
      $res=syscall($GLOBALS["TZR_WWW_DIR"]."../cgi-bin/module.cgi IBS_MODE=4 ".
		   "IBS_SITE=$ibs_site IBS_RANG=$ibs_rang ".
		   "IBS_TOTAL=$total IBS_DEVISE=978 IBS_CMD=$ref IBS_LANGUE=FRA ".
		   "IBS_PAYBOX=https://www.paybox.com/run/paiement3.cgi ".
		   "IBS_ANNULE=$home IBS_REFUSE=$home IBS_EFFECTUE=$home \"IBS_RETOUR=ref:R;auto:A;tarif:M\" ".
		   "IBS_PORTEUR=$email IBS_OUTPUT=C");
      \Seolan\Core\Shell::toScreen2('paybox','form',$res);
    }
  }

  protected function _sendOrderEmail($oid,$email,$sub) {
    \Seolan\Core\Logs::deprecated('Utiliser XShopOrder->sendByMail()');
    // recuperation du contenu
    $url = $GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'&function=viewOrder&oid='.$oid.
      '&tplentry=br&nocache=1&template=xmodcart/inmail.html&moid='.$this->_moid.'&class='.get_class($this);
    $body = file_get_contents($url);
    $mailClient = new \Seolan\Library\Mail();
    $mailClient->_modid=$this->_moid;
    // recherche des adresses administrateurs et envoi
    $tosend=false;
    $emails=explode(';',$email); 
    foreach($emails as $i=>$email1) {
      $email1=trim($email1);
      if(isEmail($email1)) {
	$mailClient->AddAddress($email1);
	\Seolan\Core\Logs::notice('cart','sending order email to '.$email1);
	$tosend=true;
      }
    }
    if($tosend){
      $mailClient->From = $this->sender;
      $mailClient->FromName = $this->sendername;
      $mailClient->Subject = $sub;
      $mailClient->AddBCC(TZR_DEBUG_ADDRESS,"Admin");
      $mailClient->Body = $body;
      $mailClient->initLog(array('modid'=>$this->_moid,'mtype'=>'order'));
      $mailClient->Send();
    }

    // envoi eventuel par fax
    $tosend=false;
    foreach($emails as $i=>$email) {
      if(preg_match('/^([0-9]+)$/',$email)) {
	$mailClient->AddAddress($email."%M2F@nfax.xpedite.fr");
	\Seolan\Core\Logs::notice("cart","sending order fax to $email");
	$tosend=true;
      }
    }
    if($tosend){
      $mailClient->From = TZR_FAX_SENDER;
      $mailClient->FromName = "Fax Sender";
      $faxsubject = "//CODE1=".getBillingCode()."//STD";
      $mailClient->Subject=$faxsubject;
      $mailClient->AddBCC(TZR_DEBUG_ADDRESS,"Admin");
      $mailClient->Body = $body;
      $mailClient->initLog(array('modid'=>$this->_moid,'mtype'=>'fax order'));
      $mailClient->Send();
    }
  }

  function viewShort($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array("tplentry"=>"cart"));
    $tplentry=$p->get("tplentry");
    $total_articles=0;
    $cart=getSessionVar("cart");
    if(is_array($cart)) {
      foreach($cart as $k => $v) {
        if(is_array($v)) {
          foreach($v as $o => $q) {
            $total_articles+=$q;
          }
        }	       
      }
    }
    return \Seolan\Core\Shell::toScreen2($tplentry,'total_articles',$total_articles);
  }

  // calcul d'un id à partir de 
  protected function _varoid2Idx($varoid) {
    $retval=$varoid;
    if(is_array($varoid)) {
      $product="";
      foreach($varoid as $f => $v) {
	$product.=$f."|".stripslashes($v)."|";
      }
      $retval=$product;
    }
    if(empty($retval)) return "0";
    return $retval;
  }

  public function _idx2array($variantes_idx) {
    $vars = explode('|', $variantes_idx);
    $lang = \Seolan\Core\Shell::getLangData();
    $variantes = [];
    for ($vi = 0; $vi < count($vars); $vi += 2) {
      $oid = $vars[$vi+1];
      $field = $vars[$vi];
      if (preg_match('/(_comment.*)/', $field)) {
        $variantes[] = [
          'type' => 'comment',
          'text' => $oid,
        ];
      } elseif(!\Seolan\Core\Kernel::isAKoid($oid)) {
	$ors = getDB()->fetchRow("select * from SETS where SOID=? and SLANG=?", [$oid, $lang]);
	if ($ors) {
          $variantes[] = [
            'type' => 'stringset',
            'soid' => $oid,
            'text' => $ors['STXT'],
          ];
	}
      } elseif(\Seolan\Core\Kernel::objectExists($oid)) {
        $display = \Seolan\Core\DataSource\DataSource::objectDisplayHelper($oid);
        $variantes[] = [
          'type' => 'object',
          'oid' => $oid,
          'text' => $display['tlink'],
        ];
      }
    }
    return $variantes;
  }

  // calcul d'un id à partir de 
  // FIXME fonction qui ne fonctionne pas très bien...
  public function _idx2txt($varoid) {
    $vars = explode("|",$varoid);
    if(!is_array($vars)) {
      return "";
    }
    $k=new \Seolan\Core\Kernel();
    $txt="";
    $lang=\Seolan\Core\Shell::getLangData();
    for($vi=0;$vi<count($vars);$vi+=2) {
      $oid=$vars[$vi+1];
      $field=$vars[$vi];
      if(preg_match('/(_comment.*)/',$field)) {
	$txt=" $oid".$txt;
      } elseif(!\Seolan\Core\Kernel::isAKoid($oid)) {
	$requete = "select * from SETS where SOID='$oid' and SLANG='$lang'";
	$rs=getDB()->select($requete);
	if($ors=$rs->fetch()) {
	  $txt.=" /".$ors['STXT'];
	}
      }
      elseif($k->objectExists($oid)) {
	$t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.\Seolan\Core\Kernel::getTable($oid));
	$values=$t->display
	  (array("oid"=>$oid, "tplentry"=>TZR_RETURN_DATA, "_publishedonly"=>"1",
		 "LANG_DATA"=>$LANG_DATA,
		 "_options"=>array("local"=>true,"error"=>"return")));
	$cnt = count($values['fields_object']);
	if(($cnt>=2) && \Seolan\Core\Shell::admini_mode()) $cnt=2;
	for($i=0;$i<$cnt;$i++) {
	  $lib1=$values['fields_object'][$i]->fielddef->label;
	  if($i==0) $txt.=$lib1." ".$values['fields_object'][$i]->html;
	  else $txt.="&nbsp;$lib1 ".$values['fields_object'][$i]->html;
	}
	$txt.=" ";
      }
    }
    return $txt;
  }

  // ajout d'un item dans le caddie
  // TODO à mettre dans XShopCart
  function addItem($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array());
    $tplentry = $p->get("tplentry");
    $oid = $p->get("oid");
    $varoid = $p->get("varoid");
    $q = $p->get("q");
    if (empty($q)) $q = 1;
    if (empty($varoid)) $varoid = "";
    $idx = $this->_varoid2Idx($varoid);
    if (!$q)
      unset($this->cart[$oid][$idx]);
    elseif (empty($this->cart[$oid][$idx]))
      $this->cart[$oid][$idx] = $q;
    else
      $this->cart[$oid][$idx] += $q;
    $this->cart->save();
  }

  // TODO à mettre dans XShopCart
  function delItem($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get("tplentry");
    $oid=$p->get("oid");
    if (!empty($this->cart[$oid])) {
      unset($this->cart[$oid]);
      $this->cart->save();
    }
  }

  /// Autorise les modifications de panier pour les utilisateurs connectés
  function secOidOk($koid) {
    if (\Seolan\Core\Kernel::isAKoid($koid, $this->productstable))
      return true;
    return parent::secOidOk($koid);
  }

  // TODO à mettre dans XShopCart
  function emptyCart($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get("tplentry");
    clearSessionVar("cart");
    clearSessionVar("cmdref");
    clearSessionVar("coupon");
  }
  
  /// Enregistre un nouveau compte pour une commande
  function saveUser($ar=NULL) {
    \Seolan\Core\Logs::deprecated("Utiliser plutôt les formulaires générés par XShopCustomer->form()");
    $p=new \Seolan\Core\Param($ar,array());
    $users=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=USERS');
    $oid=$p->get("oid");
    $email=$p->get('email');
    $passwd=$p->get('passwd');
    $nopasswd=$p->get('nopasswd');
    $autolog=$p->get('autolog');
    $ar["tplentry"]=TZR_RETURN_DATA;
    $alias=$p->get('_alias');
    if(!isset($alias)) $alias=$p->get('alias');
    if(empty($alias) && empty($oid)) $ar['alias']=uniqid();

    // Interdit d'insérer/modifier un administrateur
    if ($oid == TZR_USERID_ROOT || $p->get('GRP') == TZR_GROUPID_ROOT) {
      throw new \Seolan\Core\Exception\Exception("Permission denied");
    }

    if(!empty($oid) && $oid != TZR_USERID_NOBODY) {
      $user=$users->procEdit($ar);
    } else {
      if(!preg_match('/^([-_\.a-z0-9]+@[-_\.a-z0-9]+\.[a-z]{1,3})$/i',$email))  {
        \Seolan\Core\Shell::toScreen2('','message','Email incorrect');
        return;
      }
      if(empty($nopasswd) && !preg_match('/^(.{5,40})$/',$passwd))  {
        \Seolan\Core\Shell::toScreen2('','message','Mot de passe insuffisant');
        return;
      }
      $rs=getDB()->select("select * from USERS where alias like '".$alias."'");
      if($rs->rowCount()>0) {
	\Seolan\Core\Shell::toScreen2('','message','Utilisateur déjà enregistré');
	return;
      } else {
	$ar['PUBLISH']=1;
        if (empty($passwd)) $ar['passwd'] = $passwd = rand();
	$user=$users->procInput($ar);
	$oid=$user['oid'];
      }
    }
    \Seolan\Core\Shell::toScreen2('','message','Vos données ont été correctement enregistrées');

    if(!empty($autolog)){
      $user=\Seolan\Core\User::get_user();
      if($user->uid()==TZR_USERID_NOBODY) {
	$sess=new \Seolan\Core\Session();
	setSessionVar('root',1);
	$sess->procAuth(array('uid'=>$oid,'suid'=>true,'admin'=>1,'password'=>$passwd));
	clearSessionVar('root');
      }
    }
    return $oid;
  }

  // visualisation d'un bon de commande dans l'admin
  function viewOrder($ar=NULL) {
    \Seolan\Core\Logs::deprecated("Utiliser XModCart->getOrder()");
    $p = new \Seolan\Core\Param($ar,array());
    $oid=$p->get("oid");
    $us = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='."USERS");
    $or = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->orderlinestable);
    $rot=$this->xset->display(array("oid"=>$oid,"tplentry"=>TZR_RETURN_DATA));
    $us->display(array("oid"=>$rot['oF0006']->raw,"tplentry"=>"cust"));
    $q=$or->select_query(array("cond"=>array("orderid"=>array("=",$oid))));
    $or->browse(array("tplentry"=>"ordl","selected"=>0,"select"=>$q,"selectedfields"=>"all"));
    return  \Seolan\Core\Shell::toScreen1('ord',$rot);
  }
  function paid($ar=NULL) {
    \Seolan\Core\Logs::deprecated("Utiliser XModMonetique");
    $p = new \Seolan\Core\Param($ar,array());
    $ot = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    $oid=$p->get("oid");
    if(!isset($oid)) {
      $cmdref=$p->get("cmdref");
      if(!isset($cmdref)) $cmdref=$p->get("reference");
      $rs=getDB()->select("select KOID from ".$this->table." where ".$this->orderreffield." ='$cmdref'");
      $ors=$rs->fetch();
      if(empty($ors)) return;
      $rs->closeCursor();
      $oid=$ors['KOID'];
    }
    $ot->procEdit(array($this->paidfield=>"CARD","oid"=>$oid));
  }

  // verification du paiement CIC
  //
  function ciccheck($ar=NULL){
    \Seolan\Core\Logs::deprecated("Utiliser XModMonetique");
    $p = new \Seolan\Core\Param($ar,array());
    $ot = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    $reference= $p->get("reference");
    $montant = $p->get("montant");
    $retour  = $p->get("retour");
    \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::ciccheck"," order $reference amount $montant");
    // verification que la commande existe
    if(isset($reference)) {
      $rs=getDB()->select("select * from ".$this->table." where ".$this->orderreffield." ='$reference'");
      if(!$ors=$rs->fetch()) {
	\Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::ciccheck"," order $reference amount $montant reference not found");
	die("Commande non existante");
      }
      $rs->closeCursor();
      $oid=$ors['KOID'];
      // verification qu'il y a bien un montant affiché
      if(!isset($montant)) {
	\Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::ciccheck"," order $reference amount $montant probleme dans les montants");
	die("Montant non renseigne");
      }
      $nmontant=printf("%.2f",$ors['F0004']);
      $cmontant=printf("%.2f",str_replace('EUR','',$montant));
      if($cmontant != $nmontant) {
	\Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::ciccheck"," order $reference amount $montant probleme dans les montants (2)");
	die("Montant non exact");
      }
      $val=$retour;
      $ot->procEdit(array($this->paidfield=>$val,"oid"=>$oid));
      \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::ciccheck"," order $reference oid $oid amount $montant status $val");
      $this->_sendOrderEmail($oid,$this->backofficeemail);	
    }
    die("cicheckok");
  }

  // verification du paiement CA, etransactions, solution de paiement du CA
  // VERSIOn API 500
  // ATOS origine - CA, BPOP, SOciete Gen 
  //
  function cacheck($ar=NULL){
    \Seolan\Core\Logs::deprecated("Utiliser XModMonetique");
    $p = new \Seolan\Core\Param($ar,array());
    $ot = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    $tableau = $p->get("tableau");
    $code = $tableau[1];
    $error = $tableau[2];
    $merchant_id = $tableau[3];
    $merchant_country = $tableau[4];
    $montant = $tableau[5]/100;
    $transaction_id = $tableau[6];
    $payment_means = $tableau[7];
    $transmission_date= $tableau[8];
    $payment_time = $tableau[9];
    $payment_date = $tableau[10];
    $response_code = $tableau[11];
    $payment_certificate = $tableau[12];
    $authorisation_id = $tableau[13];
    $currency_code = $tableau[14];
    $card_number = $tableau[15];
    $cvv_flag = $tableau[16];
    $cvv_response_code = $tableau[17];
    $bank_response_code = $tableau[18];
    $complementary_code = $tableau[19];
    $return_context = $tableau[20];
    $reference = $tableau[21];
    $receipt_complement = $tableau[22];
    $merchant_language = $tableau[23];
    $language = $tableau[24];
    $customer_id = $tableau[25];
    $order_id = $tableau[26];
    $customer_email = $tableau[27];
    $customer_ip_address = $tableau[28];
    $capture_day = $tableau[29];
    $capture_mode = $tableau[30];
    $data = $tableau[31];
    \Seolan\Core\Logs::debug($tableau);
    // code response banque
    $ret['00'] = 'Autorisation acceptee';
    $ret['02'] = 'demande autorisation par tel, depasst plafond';
    $ret['03'] = 'merchant_id invalide, voir contrat banque';
    $ret['05'] = 'Autorisation refusee';
    $ret['12'] = 'Transaction invalide';
    $ret['13'] = 'Montant invalide';
    $ret['17'] = 'Annulation de internaute';
    $ret['30'] = 'Erreur de format';
    $ret['63'] = 'Regles de securite non respectees';
    $ret['75'] = 'Nombre de tentatives trop importantes';
    $ret['90'] = 'Service indisponible';
    // test des codes erreurs de transactions
    if (( $code == "" ) && ( $error == "" ) ){
      \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::cacheck"," executable response non trouve $path_bin");
    } else if ( $code != 0 ){
      \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::cacheck"," API call error.");
    } else {
      $msglog  =  "transaction_id : $transaction_id ";
      $msglog .= "transmission_date: $transmission_date - ";
      $msglog .= "payment_time : $payment_time - ";
      $msglog .= "payment_date : $payment_date - ";
      $msglog .= "payment_amount : $montant - ";
      $msglog .= "response_code : $response_code - ";
      $msglog .= "payment_certificate : $payment_certificate - ";
      $msglog .= "authorisation_id : $authorisation_id - ";
      $msglog .= "currency_code : $currency_code - ";
      $msglog .= "card_number : $card_number - ";
      $msglog .= "cvv_flag: $cvv_flag - ";
      $msglog .= "cvv_response_code: $cvv_response_code - ";
      $msglog .= "bank_response_code: $bank_response_code - ";
      $msglog .= "complementary_code: $complementary_code - ";
      $msglog .= "return_context: $return_context - ";
      $msglog .= "reference : $reference - ";
      $msglog .= "receipt_complement: $receipt_complement - ";
      $msglog .= "merchant_language: $merchant_language - ";
      $msglog .= "language: $language - ";
      $msglog .= "customer_id: $customer_id - ";
      $msglog .= "order_id: $order_id - ";
      $msglog .= "customer_email: $customer_email - ";
      $msglog .= "customer_ip_address: $customer_ip_address - ";
      $msglog .= "capture_day: $capture_day - ";
      $msglog .= "capture_mode: $capture_mode - ";
      $msglog .= "data: $data - ";
      \Seolan\Core\Logs::notice("cart","order $msglog ");
      // verification que la commande existe
      if(isset($reference)) {
	$rs=getDB()->select("select * from ".$this->table." where ".$this->orderreffield." ='$reference'");
	if(!$ors=$rs->fetch()) {
	  \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::cacheck"," order $reference amount $montant reference not found");
	  die("Commande non existante");
	}
	$rs->closeCursor();
	$oid=$ors['KOID'];
	// verification qu'il y a bien un montant affiché
	if(empty($montant)) {
	  \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::cacheck"," order $reference amount $montant probleme dans les montants");
	  die("Montant non renseigne");
	}
	if(!empty($this->acompte) && !empty($this->alreadypaidfield)) {
	  $paidfield=$this->alreadypaidfield;
	  $nmontant =  $ors[$paidfield];
	} else {
	  $nmontant=sprintf("%.2f",$ors['F0004']);	  
	}
	if($montant != $nmontant) {
	  \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::cacheck"," order $reference amount $montant<>$nmontant probleme dans les montants (2)");
	  die("Montant non exact");
	}
	$etat=$response_code;
	$val=$ret[$etat];
	$ot->procEdit(array($this->paidfield=>$val,"oid"=>$oid));
	\Seolan\Core\Logs::notice("cart","order $reference oid $oid amount $montant status $val");
	$label = \Seolan\Core\Labels::getTextSysLabel('\Seolan\Module\CartV2\CartV2','paimentreturn');
	$this->_sendOrderEmail($oid,$this->backofficeemail,$label);
	if($etat=="00")
	  $this->_sendOrderEmail($oid,$customer_email,"Votre commande");

      }
      die();
    }
  }

  // produit ATOS : webaffaires du crédit du nord
  // function wacheck : produit ATOS également, cahmps supplementaire dans la réponse
  // champ complementary_info non present dans cacheck
  // API version 600
  //
  function wacheck($ar=NULL){
    \Seolan\Core\Logs::deprecated("Utiliser XModMonetique");
    $p = new \Seolan\Core\Param($ar,array());
    $ot = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    $tableau = $p->get("tableau");
    $code = $tableau[1];
    $error = $tableau[2];
    $merchant_id = $tableau[3];
    $merchant_country = $tableau[4];
    $montant = $tableau[5]/100;
    $transaction_id = $tableau[6];
    $payment_means = $tableau[7];
    $transmission_date= $tableau[8];
    $payment_time = $tableau[9];
    $payment_date = $tableau[10];
    $response_code = $tableau[11];
    $payment_certificate = $tableau[12];
    $authorisation_id = $tableau[13];
    $currency_code = $tableau[14];
    $card_number = $tableau[15];
    $cvv_flag = $tableau[16];
    $cvv_response_code = $tableau[17];
    $bank_response_code = $tableau[18];
    $complementary_code = $tableau[19];
    $complementary_info = $tableau[20];
    $return_context = $tableau[21];
    $reference = $tableau[22];
    $receipt_complement = $tableau[23];
    $merchant_language = $tableau[24];
    $language = $tableau[25];
    $customer_id = $tableau[26];
    $order_id = $tableau[27];
    $customer_email = $tableau[28];
    $customer_ip_address = $tableau[29];
    $capture_day = $tableau[30];
    $capture_mode = $tableau[31];
    $data = $tableau[32];
    \Seolan\Core\Logs::debug($tableau);
    // code response banque
    $ret['00'] = 'Autorisation acceptee';
    $ret['02'] = 'demande autorisation par tel, depasst plafond';
    $ret['03'] = 'merchant_id invalide, voir contrat banque';
    $ret['05'] = 'Autorisation refusee';
    $ret['12'] = 'Transaction invalide';
    $ret['13'] = 'Montant invalide';
    $ret['17'] = 'Annulation de internaute';
    $ret['30'] = 'Erreur de format';
    $ret['63'] = 'Regles de securite non respectees';
    $ret['75'] = 'Nombre de tentatives trop importantes';
    $ret['90'] = 'Service indisponible';
    // test des codes erreurs de transactions
    if (( $code == "" ) && ( $error == "" ) ){
      \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::wacheck"," executable response non trouve $path_bin");
    } else if ( $code != 0 ){
      \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::wacheck"," API call error.");
    } else {
      $msglog  =  "transaction_id : $transaction_id ";
      $msglog .= "transmission_date: $transmission_date - ";
      $msglog .= "payment_time : $payment_time - ";
      $msglog .= "payment_date : $payment_date - ";
      $msglog .= "payment_amount : $montant - ";
      $msglog .= "response_code : $response_code - ";
      $msglog .= "payment_certificate : $payment_certificate - ";
      $msglog .= "authorisation_id : $authorisation_id - ";
      $msglog .= "currency_code : $currency_code - ";
      $msglog .= "card_number : $card_number - ";
      $msglog .= "cvv_flag: $cvv_flag - ";
      $msglog .= "cvv_response_code: $cvv_response_code - ";
      $msglog .= "bank_response_code: $bank_response_code - ";
      $msglog .= "complementary_code: $complementary_code - ";
      $msglog .= "return_context: $return_context - ";
      $msglog .= "reference : $reference - ";
      $msglog .= "receipt_complement: $receipt_complement - ";
      $msglog .= "merchant_language: $merchant_language - ";
      $msglog .= "language: $language - ";
      $msglog .= "customer_id: $customer_id - ";
      $msglog .= "order_id: $order_id - ";
      $msglog .= "customer_email: $customer_email - ";
      $msglog .= "customer_ip_address: $customer_ip_address - ";
      $msglog .= "capture_day: $capture_day - ";
      $msglog .= "capture_mode: $capture_mode - ";
      $msglog .= "data: $data - ";
      \Seolan\Core\Logs::notice("cart","order $msglog ");
      // verification que la commande existe
      if(isset($reference)) {
	$rs=getDB()->select("select * from ".$this->table." where ".$this->orderreffield." ='$reference'");
	if(!$ors=$rs->fetch()) {
	  \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::wacheck"," order $reference amount $montant reference not found");
	  die("Commande non existante");
	}
	$rs->closeCursor();
	$oid=$ors['KOID'];
	// verification qu'il y a bien un montant affiché
	if(empty($montant)) {
	  \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::wacheck"," order $reference amount $montant probleme dans les montants");
	  die("Montant non renseigne");
	}
	if(!empty($this->acompte) && !empty($this->alreadypaidfield)) {
	  $paidfield=$this->alreadypaidfield;
	  $nmontant =  $ors[$paidfield];
	} else {
	  $nmontant=sprintf("%.2f",$ors['F0004']);	  
	}
	if($montant != $nmontant) {
	  \Seolan\Core\Logs::critical("\Seolan\Module\CartV2\CartV2::wacheck"," order $reference amount $montant<>$nmontant probleme dans les montants (2)");
	  die("Montant non exact");
	}
	$etat=$response_code;
	$val=$ret[$etat];
	$ot->procEdit(array($this->paidfield=>$val,"oid"=>$oid));
	\Seolan\Core\Logs::notice("cart","order $reference oid $oid amount $montant status $val");
	$label = \Seolan\Core\Labels::getTextSysLabel('\Seolan\Module\CartV2\CartV2','paimentreturn');
	$this->_sendOrderEmail($oid,$this->backofficeemail,$label);
	if($etat=="00")
	  $this->_sendOrderEmail($oid,$customer_email,"Votre commande");

      }
      die();
    }
  }

  // verification du paiement spplus, solution de paiement de la ciasse d'épargne
  // 
  function spcheck($ar=NULL) {
    \Seolan\Core\Logs::deprecated("Utiliser XModMonetique");
    $ret[1]='Autorisation carte acceptée';
    $ret[2]='Autorisation carte refusée';
    $ret[4]='Paiement par carte accepté';
    $ret[5]='Paiement par carte refusé par la banque';
    $ret[6]='Paiement par cheque accepté';
    $ret[8]='Chèque encaissé';
    $ret[10]='Transaction terminée';
    $ret[11]='Paiement annulé par le commerçant';
    $ret[12]='Abandon de l\'internaute';
    $ret[15]='Remboursement';
    $ret[99]='Paiement de test en production';
    $p = new \Seolan\Core\Param($ar,array());
    $ot = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    $reference= $p->get("reference");
    $montant = $p->get("montant");
    \Seolan\Core\Logs::notice("cart","order $reference amount $montant");
    // verification que la commande existe
    if(isset($reference)) {
      $rs=getDB()->select("select * from ".$this->table." where ".$this->orderreffield." ='$reference'");
      if(!$ors=$rs->fetch()) {
	\Seolan\Core\Logs::notice("warning","order $reference amount $montant reference not found");
	die("Commande non existante");
      }
      $rs->closeCursor();
      $oid=$ors['KOID'];
      // verification qu'il y a bien un montant affiché
      if(!isset($montant)) {
	\Seolan\Core\Logs::notice("warning","order $reference amount $montant probleme dans les montants");
	die("Montant non renseigne");
      }
      $nmontant=sprintf("%.2f",$ors['F0004']);
      if($montant != $nmontant) {
	\Seolan\Core\Logs::notice("warning","order $reference amount $montant probleme dans les montants (2)");
	die("Montant non exact");
      }
      $etat=$p->get("etat");
      $val=$ret[$etat];
      $ot->procEdit(array($this->paidfield=>$val,"oid"=>$oid));
      \Seolan\Core\Logs::notice("cart","order $reference oid $oid amount $montant status $val");
      $label = \Seolan\Core\Labels::getTextSysLabel('\Seolan\Module\CartV2\CartV2','paimentreturn');
      $this->_sendOrderEmail($oid,$this->backofficeemail,$label);
      if ($etat == '1' ){
	// envoi d'un mail de confirmation au client	
	$tableusers = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ors[$this->orderuserfield]);
	$displayuser = $tableusers->display(array('oid'=>$ors[$this->orderuserfield], 'tplentry'=>'*return*'));
	$label = \Seolan\Core\Labels::getTextSysLabel('\Seolan\Module\CartV2\CartV2','yourorder');
	$this->_sendOrderEmail($oid,$displayuser['oemail']->raw, $label);
      }
    }
    die("spcheckok");
  }

  function paybox($ar=NULL) {
    \Seolan\Core\Logs::deprecated("Utiliser XModMonetique");
    $p=new \Seolan\Core\Param($ar,array());
    $ot=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    $cpcb=$p->get("pays");
    $montant=$p->get("tarif");
    $reference=$p->get("ref");
    $tplentry=$p->get('tplentry');
    $autorisation=$p->get("auto");
    \Seolan\Core\Logs::notice("cart","order $reference amount $montant");
    // verification que la commande existe
    $message="";
    if(!empty($reference)) {
      $rs=getDB()->select("select * from ".$this->table." where ".$this->orderreffield." ='$reference'");
      if(!$ors=$rs->fetch()) {
	\Seolan\Core\Logs::notice("warning","order $reference amount $montant reference not found");
	die("Commande non existante");
      }
      $rs->closeCursor();
      $oid=$ors['KOID'];
      // verification qu'il y a bien un montant affiché
      if(empty($montant)) {
	\Seolan\Core\Logs::notice("warning","order $reference amount $montant probleme dans les montants");
	die("Montant non renseigne");
      }
      $fmontant = sprintf("%.2f",$ors['F0004'])*100;
      $nmontant = (string) $fmontant;
      $montant = (string) $montant;
      if($montant != $nmontant) {
	\Seolan\Core\Logs::critical("warning","order $reference amount $montant probleme dans les montants ($nmontant attendu) (2)");
	die("Montant non exact");
      }
      $paidfield=$this->paidfield;
      $val=(empty($autorisation)?"Attente autorisation":"Paiement autorisé");
      $ok=(empty($autorisation)?0:1);
      if(!preg_match('@(N/A)@i',$ors[$paidfield]) && !preg_match('@Attente autorisation@i',$ors[$paidfield])) {
	$message="Paiement déjà effectué";
	$val="N/A Incident de paiement inconnu";
	$ok=0;
      }
      $ot->procEdit(array($this->paidfield=>$val,'cpcb'=>$cpcb,"oid"=>$oid));
      \Seolan\Core\Logs::notice("cart","order $reference oid $oid amount $montant status $val");

      if($ok) {
	// envoi d'un mail de confirmation au client
	$tableusers = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ors[$this->orderuserfield]);
	$displayuser = $tableusers->display(array('oid'=>$ors[$this->orderuserfield], 'tplentry'=>TZR_RETURN_DATA));
	$label = \Seolan\Core\Labels::getTextSysLabel('\Seolan\Module\CartV2\CartV2','yourorder');
	$this->_sendOrderEmail($oid,$displayuser['oemail']->raw,$label);
	// envoi d'un mail au proprio de la boutique
	$label = \Seolan\Core\Labels::getTextSysLabel('\Seolan\Module\CartV2\CartV2','paimentreturn');
	$this->_sendOrderEmail($oid,$this->backofficeemail,$label);
      }
    }
    if($tplentry==TZR_RETURN_DATA) return array('ok'=>$ok,'message'=>$message,'oid'=>$oid);
    else die($message);
  }

  function del($ar=NULL) {
    $p = new \Seolan\Core\Param($ar,array());
    parent::del($ar);
    //effacer les ligne de commande si le sous module n'est pas definit
    //dans le cas contraire la classe parent a du les effacer
    $parentoid=$p->get('_selected');
    $selectedok=$p->get('_selectedok');
    $parentoid=array_keys($parentoid);
    if(($selectedok!='ok')||empty($parentoid)) $parentoid=$p->get('oid');
    
    $ot = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    $or = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->orderlinestable);
    
    $q=$or->select_query(array("cond"=>array("orderid"=>array("=",$parentoid))));
    $rl=$or->browse(array("tplentry"=>TZR_RETURN_DATA,"selected"=>0,"select"=>$q,"selectedfields"=>"all"));
    foreach($rl['lines_oid'] as $k=>$loid){
      $or->del(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$loid,'_selectedok'=>'','_selected'=>''));    
    }
  }
  /**
   * surcharge et gabarits d'édition des champs
   * éventuellement pour contexte donné
   */
  public function getFieldsTemplates($what, $where=null){
    if ($what == 'frontusersCustomer')
      return static::$_fieldsTemplates['customer'];
    else
      return [];
  }
  /**
   * Tente de télécharger un produit numérique acheté
   */
  function edeliv($ar = []) {
    try {
      $this->downloadEdeliv($ar);
    } catch (Exception $e) {
      \Seolan\Core\Shell::alert("Une erreur est survenue lors du téléchargement : ".$e->getMessage());
      // En cas d'erreur, retourne à la page d'accueil
      if (!\Seolan\Core\Shell::hasNext()) \Seolan\Core\Shell::setNext('/');
    }
  }

  /**
   * Télécharge un contenu numérique à partir d'une commande
   * @throws Exception
   */
  function downloadEdeliv($ar = []) {
    $p = new \Seolan\Core\Param($ar,array());
    $edeliv = $p->get('edeliv');
    $eorder = $p->get('eorder');
    $byemail = $p->get('byemail');
    $now = date("Y-m-d");
    $edelivdisplay = \Seolan\Core\DataSource\DataSource::objectDisplayHelper($edeliv);

    // Vérifie que le nombre maximal de téléchargements n'est pas atteint
    if ($this->xset_edeliv->fieldExists('MAXDWN') && ($edelivdisplay['oecnt']->raw >= $edelivdisplay['oMAXDWN']->raw))
      throw new Exception("Nombre maximal de téléchargements (".$edelivdisplay['oMAXDWN']->raw.") atteint", 1);

    // Vérifie la date de livraison max
    if ($now >=  $edelivdisplay['oDATET']->raw)
      throw new Exception("Date de livraison dépassée", 2);

    // Vérifie la compatibilité des paramètres de l'URL entre la commande et le téléchargement demandé
    if ($eorder != $edelivdisplay['oO1']->raw)
      throw new Exception("Commande non valide ou non payée", 3);

    // Récupère la commande liée au téléchargement (sans restriction sur l'utilisateur logué)
    $order = $this->getOrder($edelivdisplay['oO1']->raw);

    // Vérifie que la commande est bien payée
    if (!$order->is_paid)
      throw new Exception("Commande non payée ($order->payment_status)", 4);

    // Vérifie que le produit existe encore en base
    if (empty($edelivdisplay['oEPROD']->raw) || !\Seolan\Core\Kernel::objectExists2($edelivdisplay['oEPROD']->raw))
      throw new Exception('Téléchargement introuvable', 5);

    $product = \Seolan\Core\DataSource\DataSource::objectDisplayHelper($edelivdisplay['oEPROD']->raw);
    $filename = $product['o'.$this->edelivfield]->filename;
    $originalname = $product['o'.$this->edelivfield]->originalname;
    if (!empty($byemail))
      throw new Exception("Commande non valide", 6);
    $this->xset_edeliv->procEdit([
      '_local' => true,
      'oid'    => $edeliv,
      'tplentry' => TZR_RETURN_DATA,
      'ecnt' => intval($edelivdisplay['oecnt']->raw + 1),
    ]);
    header("Expires: 0");
    header("Cache-Control: private, post-check=0, pre-check=0");
    header("Content-type: ".$product['o'.$this->edelivfield]->mime);
    header("Content-disposition: attachment; filename=\"$originalname\"");
    $size=filesize($filename);
    header('Accept-Ranges: bytes');
    header("Content-Length: $size");
    readfile($filename);
    exit;
  }

  /**
   * Retourne les paramètres à renseigner dans le formulaire de paiement SYSTEMPAY
   * Documentation officielle : https://systempay.cyberpluspaiement.com/html/documentation.html
   * Back-office du marchant : https://paiement.systempay.fr/vads-merchant/
   * Constantes PHP à définir :
   *   SYSTEMPAYURL = URL de post du formulaire : https://paiement.systempay.fr/vads-payment/
   *   SYSTEMPAYMODE = TEST ou PRODUCTION
   *   SYSTEMPAYSITEID = Identifiant du vendeur à 8 chiffres
   *   SYSTEMPAYKEY = Certificat à 16 chiffres différent en TEST et en PROD
   * @param $amount Montant de la transaction en cents
   * @param $ref Référence de la commande
   * @param $email Email du client
   * @return array Paramètres à transmettre à la banque pour paiement
   */
  function getSystemPayParams($amount, $ref, $email='', $urlretourok='', $urlretourko='') {
    \Seolan\Core\Logs::deprecated("Utiliser XModMonetique");
    $key = SYSTEMPAYKEY;
    // Initialisation des paramètres
    $params = array(); // tableau des paramètres du formulaire
    $params['vads_site_id'] = SYSTEMPAYSITEID;
    $montant_en_euro = $amount;
    $params['vads_amount'] = 100*$montant_en_euro; // en cents
    $params['vads_cust_email'] = $email;
    $params['vads_order_id'] = $ref;
    $params['vads_currency'] = "978"; // norme ISO 4217
    $params['vads_ctx_mode'] = SYSTEMPAYMODE;
    $params['vads_page_action'] = "PAYMENT";
    $params['vads_action_mode'] = "INTERACTIVE"; // saisie de carte réalisée par la plateforme
    $params['vads_payment_config']= "SINGLE";
    $params['vads_version'] = "V2";
    $params['vads_language'] = "fr";//"en";
    $params['vads_return_mode']= 'POST';
    // ATTENTION au rewriting des URL qui peut fausser la signature SHA1 !!!
    $params['vads_url_cancel'] = $params['vads_url_error'] = $params['vads_url_referral'] = $params['vads_url_refused'] = $urlretourko;
    $params['vads_url_success'] = $params['vads_url_return'] = $urlretourok;
    // Exemple de génération de trans_id basé sur l'horodatage UTC (suppression du décalage horaire)
    $params['vads_trans_date'] = gmdate("YmdHis",time());
    $params['vads_trans_id'] = gmdate("His");
    // Génération de la signature
    ksort($params); // tri des paramètres par ordre alphabétique
    $contenu_signature = "";
    foreach ($params as $nom => $valeur) {
      $contenu_signature .= $valeur."+";
    }
    $contenu_signature .= $key; // On ajoute le certificat à la fin
    $params['signature'] = sha1($contenu_signature);
    return $params;
  }

  /**
   * Traduit le code retour de SYSTEMPAY
   * @author Camille Descombes,Julien Guillaume
   * @return string Message correspondant au code retour de la banque
   */
  public static function getSystemPayCodeLabel($code = null) {
    \Seolan\Core\Logs::deprecated("Utiliser XModMonetique");
    $response_messages = array(
      '00' => 'Paiement autorisé',
      '02' => 'Contacter l’émetteur de carte',
      '03' => 'Accepteur invalide',
      '04' => 'Conserver la carte',
      '05' => 'Ne pas honorer',
      '07' => 'Conserver la carte, conditions spéciales',
      '08' => 'Approuver après identification',
      '12' => 'Transaction invalide',
      '13' => 'Montant invalide',
      '14' => 'Numéro de porteur invalide',
      '17' => 'Annulation du client',
      '30' => 'Erreur de format',
      '31' => 'Identifiant de l\'organisme acquéreur inconnu',
      '33' => 'Date de validité de la carte dépassée',
      '34' => 'Suspicion de fraude',
      '41' => 'Carte perdue',
      '43' => 'Carte volée',
      '51' => 'Provision insuffisante ou crédit dépassé',
      '54' => 'Date de validité de la carte dépassée',
      '56' => 'Carte absente du fichier',
      '57' => 'Transaction non permise à ce porteur',
      '58' => 'Transaction interdite au terminal',
      '59' => 'Suspicion de fraude',
      '60' => 'L\'accepteur de carte doit contacter l’acquéreur',
      '61' => 'Montant de retrait hors limite',
      '63' => 'Règles de sécurité non respectées',
      '68' => 'Réponse non parvenue ou reçue trop tard',
      '90' => 'Arret momentané du système',
      '91' => 'Emetteur de cartes inaccessible',
      '94' => 'Transaction dupliquée',
      '96' => 'Mauvais fonctionnement du système',
      '97' => 'Echéance de la temporisation de surveillance globale',
      '98' => 'Serveur indisponible routage réseau demandé à nouveau',
      '99' => 'Incident domaine initiateur'
    );
    return (SYSTEMPAYMODE == 'TEST' ? '[TEST] ' : '')
      .(isset($response_messages[$code]) ? $response_messages[$code] : $code.' Code de retour inconnu');
  }
}
