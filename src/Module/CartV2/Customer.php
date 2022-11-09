<?php
namespace Seolan\Module\CartV2;
/**
 * Objet utilisateur de la boutique
 */
class Customer extends \ArrayObject {

  /// @type XModCart
  public $shop;

  /// @type XModFrontUsers
  public $customers;

  /// @type string
  public $oid;

  /// @type boolean
  /// private $ready = false;
  /**
   * @param \Seolan\Module\CartV2\CartV2 $shop
   * @param string $oid KOID de l'utilisateur à construire
   */
  function __construct(\Seolan\Module\CartV2\CartV2 $shop, $oid = '') {
    $this->customers = $shop->customers;
    $this->shop = $shop;
    $this->oid = $oid?: \Seolan\Core\User::get_current_user_uid();
    $customer = [];
    if (!\Seolan\Core\Kernel::objectExists($this->oid))
      $this->oid = null;

    // ? est-ce que 1 rdisplay n'aurait pas été suffisant, au moins en FO ?
    
    if (\Seolan\Core\Kernel::objectExists($this->oid)) {
      $customer = $this->customers->display([
	'_options' => ['error' => 'return', 'local' => true],
	'requested_submodules' => [], // Ne charge pas les sous-modules (si les commandes sont en ssmod ça ferait une boucle infinie)
	'tplentry' => TZR_RETURN_DATA,
	'oid' => $this->oid,
      ]);
      if (!is_array($customer))
        $customer = [];
    }
    parent::__construct($customer);
  }
  /// Récupère le customer en cours
  /// Instancie une propriété public non-instanciée avec sa fonction éponyme
  function __get($property) {
    $this->$property = $this->$property();
    return $this->$property;
  }
  /// Mappage des champs de la table USERS pour la fonction $user->field(nom_standard) : [ nom_standard => champ_sql ]
  function fields_mapping() {
    return [
      'civility'   => 'civilite',
      'name'       => 'nom',
      'forename'   => 'prenom',
      'company'    => 'cie',
      'address'    => 'adresse',
      'postalcode' => 'codp',
      'city'       => 'ville',
      'country'    => 'pays',
      'email'      => 'email',
      'phone'      => 'tel',
      'mobile'     => 'mobile',
      'fax'        => 'fax',
      'delivery_civility'   => 'fcivilite',
      'delivery_name'       => 'fnom',
      'delivery_forename'   => 'fprenom',
      'delivery_company'    => 'fcie',
      'delivery_address'    => 'fadresse',
      'delivery_postalcode' => 'fcodp',
      'delivery_city'       => 'fville',
      'delivery_country'    => 'fpays',
      'delivery_email'      => 'femail',
      'delivery_phone'      => 'ftel',
      'delivery_mobile'     => 'fmobile',
      'delivery_fax'        => 'ffax',
    ];
  }

  /// Champs de création de compte (login/password)
  function account_selectedfields() {
    return $this->customers->account_selectedfields;
  }

  /// Champs d'adresse de facturation + livraison (si 2ème adresse non renseignée)
  function address_selectedfields() {
    return $this->customers->address_selectedfields;
  }

  /// Champs d'adresse de livraison alternative
  function delivery_selectedfields() {
    return $this->customers->delivery_selectedfields;
  }

  /// Permet de récupérer un champ en BDD avec un nom fixe de propriété ou d'exécuter la fonction d'affichage de ce champ
  function field($field, $property = 'text') {
    if (!isset($this->fields_mapping[$field]))
      throw new Exception("Trying to get a non-existent mapped field $field");
    $mapped_field = $this->fields_mapping[$field];
    if (!$mapped_field)
      return '';
    if (!array_key_exists('o'.$mapped_field, $this))
      throw new Exception("Field not exists USERS.$mapped_field");
    return $this['o'.$mapped_field]->$property;
  }

  /// Email du client
  function email() {
    return $this->field('email');
  }

  /// Nom complet du client
  function fullname() {
    return 
      ($this->field('civility') ? $this->field('civility').' ' : '').
      ($this->field('forename') ? $this->field('forename').' ' : '').
      $this->field('name');
  }

  /// Nom complet de la personne à qui adresser la commande
  function delivery_fullname() {
    return $this->field('delivery_civility').' '.$this->field('delivery_name').' '.$this->field('delivery_forename');
  }

  /**
   * @return array DISPLAY_RESULTSET du pays de livraison du client
   */
  function country_display() {
    if (!$this->shop->customer->authentified)
      return [];
    $pays_oid = $this->field('delivery_country','raw') ?: $this->field('country','raw');
    return \Seolan\Core\DataSource\DataSource::objectDisplayHelper($pays_oid);
  }

  /**
   * Renvoie le montant des frais de port
   * @return DISPLAY_RESULTSET Règle de gestion des frais de port
   */
  function delivery_rules() {
    if (!$this->delivery_rules_oid())
      return false;
    return \Seolan\Core\DataSource\DataSource::objectDisplayHelper($this->delivery_rules_oid);
  }

  /// Renvoie le KOID des règles de calcul des frais de livraison
  function delivery_rules_oid() {
    if ($this->country_display['odeliv']->raw)
      return $this->country_display['odeliv']->raw;
    $shop = $this->shop;
    return getDB()->fetchOne("SELECT KOID FROM $shop->deliverytable WHERE code='standard' AND LANG=?", [ TZR_DEFAULT_LANG ]);
  }

  /**
   * Renvoie la réduction totale liée à l'utilisateur en pourcentage (0 - 100)
   * @return float Pourcentage entre 0 et 100
   */
  function reduction() {
    return \Seolan\Core\Lang::floatval($this['o'.$this->shop->userdiscount]->raw);
  }

  /**
   * Renvoie les taxes applicables à l'utilisateur sur les frais de port en pourcentage (0 - 100)
   * @return float Pourcentage entre 0 et 100
   */
  function delivery_taxes() {
    if (!$this->tva_applicable || !$this->delivery_rules || !$this->delivery_rules['otva']->html)
      return 0;
    $tva = \Seolan\Core\DataSource\DataSource::objectDisplayHelper($this->delivery_rules['otva']->raw);
    return floatval($tva['opourc']->raw);
  }

  /**
   * Renvoie vrai si on peut appliquer la TVA sur la vente des produits
   * @return boolean
   */
  function tva_applicable() {
    if (!$this->shop->customer->authentified)
      return true;
    return $this->country_display ? $this->country_display['otzone']->valid : true;
  }

  /// @todo Pour les pays qui appliqueraint d'autres taxes que la tva ?
  function country_taxes() {
  }

  /// Teste si l'adresse de l'utilisateur est valide
  function is_address_valid() {
    if ($this->fullname && $this->field('address') && $this->field('city') && $this->field('country'))
      return true;
    if ($this->delivery_fullname && $this->field('delivery_address') && $this->field('delivery_city') && $this->field('delivery_country'))
      return true;
    return false;
  }

  /// Teste si l'utilisateur a renseigné une adresse de livraison alternative
  function is_delivery_address_filled() {
    if ($this->delivery_fullname && $this->field('delivery_address') && $this->field('delivery_city') && $this->field('delivery_country'))
      return true;
    return false;
  }

  /// Rend le formulaire d'édition ou d'ajout d'utilisateur
  function form() {
    if (!$this->authentified) {
      return $this->customers->insert([
        '_local' => true,
        'selectedfields' => $this->account_selectedfields,
      ]);
    }
    return $this->customers->edit([
      '_local' => true,
      'oid' => $this['oid'],
      'selectedfields' => array_merge($this->address_selectedfields, $this->delivery_selectedfields),
    ]);
  }

  /// Rend le formulaire d'édition du compte sans le champ password
  function insert_form_without_passwd() {
    return $this->customers->insert([
      '_local' => true,
      'selectedfields' => array_diff($this->account_selectedfields, ['passwd']),
    ]);
  }

  /**
   * @return boolean Vrai si le client est l'utilisateur connecté
   */
  function authentified() {
    return $this->oid && \Seolan\Core\User::authentified() && $this->oid == \Seolan\Core\User::get_current_user_uid();
  }

  /**
   * Liste des commandes passées par ce client
   * @return array Tableau de XShopOrder
   */
  function orders() {
    $browse = $this->shop->browse([
      '_local' => true,
      'selectedfields' => ['KOID'],
      // Les Oids sont déjà filtrés par le module
    ]);
    $orders = [];
    foreach ($browse['lines_oid'] as $order_oid) {
      $orders[] = new $this->shop->order_class($this->shop, $order_oid);
    }
    return $orders;
  }

  /**
   * Transforme les données du client en un objet exploitable par XModMonetique
   * @return MCustomerInfos Objet client XModMonetique
   */
  function getMCustomerInfos() {
    $mcustomer = new \Seolan\Module\Monetique\Model\Customer();
    $mcustomer->oid = $this['oid'];
    $mcustomer->lang = \Seolan\Core\Shell::getLangData();
    $mcustomer->email = $this['oemail']->text;
    return $mcustomer;
  }
}


