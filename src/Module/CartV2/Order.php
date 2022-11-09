<?php
namespace Seolan\Module\CartV2;
/**
 * Objet commande de la boutique
 */
class Order extends \ArrayObject {
  
  ///{ Champ status
  const STATE_CART      = 'cart';      ///< La commande est un panier enregistré
  const STATE_PAYMENT   = 'payment';   ///< La commande est associée à un utilisateur et peut être payée
  const STATE_ORDERED   = 'ordered';   ///< La commande a été passée et doit être traitée
  const STATE_SENDED    = 'sended';    ///< La commande a été envoyée
  const STATE_CANCELLED = 'cancelled'; ///< La commande a été annulée
  const STATE_RECEIVED  = 'received';  ///< La commande a bien été reçue
  ///}

  ///{ Champ paid
  const PAYMENT_STATE_WAITING = 'waiting'; ///< Le client peux payer sa commande qui est en attente de paiement
  const PAYMENT_STATE_ERROR   = 'error';   ///< Le client ne peux plus payer sa commande car une erreur est survenue
  const PAYMENT_STATE_PAID    = 'paid';    ///< Le paiement a été accepté
  ///}

  ///{ Champ tpaid
  const PAYMENT_TYPE_CB       = 'cb';       ///< Paiement par Carte Bleue via XModMonetique
  const PAYMENT_TYPE_CHECK    = 'check';    ///< Paiement par chèque
  const PAYMENT_TYPE_GIFT     = 'gift';     ///< Paiement inutile car commande offerte (coupon)
  const PAYMENT_TYPE_TRANSFER = 'transfer'; ///< Paiement par virement bancaire
  ///}

  public $shop;
  public $oid;

  /**
   * @param \Seolan\Module\CartV2\CartV2 $shop Module boutique
   * @param mixed $order KOID ou DISPLAY_RESULTSET de la commande
   */
  function __construct(\Seolan\Module\CartV2\CartV2 $shop, $order) {
    $this->shop = $shop;
    if (is_string($order) && \Seolan\Core\Kernel::isAKoid($order, $shop->table)) {
      $this->oid = $order;
      $this->init();
    } elseif (is_array($order)) {
      $this->oid = $order['oid'];
      parent::__construct($order);
    } else {
      parent::__construct([]);
    }
  }

  /// Instancie une propriété non-instanciée avec sa fonction éponyme
  function __get($property) {
    $this->$property = $this->$property();
    return $this->$property;
  }

  /// Initialise l'ArrayObject en fonction du display (rappelé après chaque procEdit)
  function init() {
    $this->resetProperties();
    parent::__construct($this->oid ? $this->shop->getOrder($this->oid) : []);
  }

  /// Réinitialise toutes les propriétés de l'objet (sauf shop et oid)
  function resetProperties() {
    $this->setFlags(\ArrayObject::STD_PROP_LIST);
    foreach (get_object_vars((object) $this) as $key => $value)
      if (!in_array($key, ['shop','oid']))
        unset($this->$key);
    $this->setFlags(0);
  }

  /// Lignes de la commande récupérées via un browse
  function lines() {
    return $this->shop->xset_orderlines->browse([
      '_local' => true,
      '_mode' => 'object',
      'selectedfields' => 'all',
      'pagesize' => 99999,
      'cond' => [
        'orderid' => ['=', $this->oid],
      ],
    ])['lines'];
  }

  /// Transformation des lignes de la commande en tableau de XShopItem
  function items() {
    $items = [];
    foreach ($this->lines as $line) {
      $item = new $this->shop->item_class($this->shop, $line['oproduct']->raw, $line['onb']->raw);
      $item->reference     = $line['oref']->text;
      $item->label         = $line['olabel']->text.
        ($this->is_paid && $line['oEDELIV']->text ? "\n".'<div class="edeliv"><a href="'.$line['oEDELIV']->text.'">'.$line['oEDELIV']->text.'</a></div>' : '');
      $item->originalprice = \Seolan\Core\Lang::floatval($line['ooldpriceht']->raw);
      $item->price         = \Seolan\Core\Lang::floatval($line['oprice']->raw);
      $item->taxes         = \Seolan\Core\Lang::floatval($line['otva']->raw);
      $item->reduction     = \Seolan\Core\Lang::floatval($line['oremise']->raw);
      $item->edeliv_url    = $line['oEDELIV']->text;
      $items[] = $item;
    }
    return $items;
  }

  /// Calcul des lignes de total de la commande à partir des infos en BDD
  function total() {
    $shop = $this->shop;
    $total = [
      'amountHT' => $this["o$shop->orderamounthtfield"]->raw,
      'amount'   => $this["o$shop->orderamountfield"]->raw,
    ];
    $total['delivery'] = $this["o$shop->orderdeliveryfield"]->raw > 0 ? \Seolan\Core\Lang::floatval($this["o$shop->orderdeliveryfield"]->raw) : __('OFFERTS','\Seolan\Module\CartV2\CartV2');
    if ($this["o$shop->ordercouponfield"]->raw > 0)
      $total['coupon'] = $this["o$shop->ordercouponfield"]->text;
    if (\Seolan\Core\Lang::floatval($this["o$shop->ordertvafield"]->raw) > 0)
      $total['taxes'] = \Seolan\Core\Lang::floatval($this["o$shop->ordertvafield"]->raw);
    $total['reduction'] = \Seolan\Core\Lang::floatval($this["o$shop->orderremisefield"]->raw);
    $total['originalamount'] = $total['amountHT'] + $total['reduction'] - $total['delivery'];
    $total['edeliv'] = 0;
    foreach ($this->items as $item)
      $total['edeliv'] += $item->is_edeliv ? $item->quantity : 0;
    $total['labels'] = $shop->getTotalLabels($total);
    return $total;
  }

  /// URL de la commande
  function url() {
    return $this->shop->getStepUrl('payment').'&amp;order='.$this->oid;
  }

  /// URL d'impression de la commande
  function print_url() {
    return $this->shop->getStepUrl('print').'&amp;order='.$this->oid;
  }

  /// URL complète avec nom de domaine de la commande
  function full_url() {
    return \Seolan\Core\Session::makeDomainName().$this->shop->getStepUrl('payment', false).'&order='.$this->oid;
  }

  function date() {
    return $this['o'.$this->shop->orderdatefield]->html;
  }

  /// Renvoie le montant TTC de la commande
  function amount() {
    return $this['o'.$this->shop->orderamountfield]->raw;
  }

  /// Etat du traitement de la commande (en attente, livraison en cours...)
  function status() {
    return $this['o'.$this->shop->orderstatusfield]->html;
  }

  /// Etat du paiement
  function payment_status() {
    return $this['o'.$this->shop->orderpaidfield]->html;
  }

  /**
   * Teste si la commande  doit être payée par chèque et n'a pas encore été payée
   * @return boolean Vrai si la commande doit être payée par chèque
   */
  function is_waiting_check() {
    return $this['o'.$this->shop->orderpaidfield]->raw === $this::PAYMENT_STATE_WAITING
        && $this['o'.$this->shop->ordertpaidfield]->raw === $this::PAYMENT_TYPE_CHECK;
  }

  /**
   * Teste si la commande est un cadeau et ne fait pas l'office d'un paiement
   * @return boolean Vrai si la commande est un cadeau
   */
  function is_gift() {
    return $this['o'.$this->shop->ordertpaidfield]->raw === $this::PAYMENT_TYPE_GIFT;
  }

  /**
   * Teste si la commande est payée
   * @return boolean Vrai si le client a payé la commande
   */
  function is_paid() {
    return $this['o'.$this->shop->orderpaidfield]->raw === $this::PAYMENT_STATE_PAID;
  }

  /**
   * Teste si la commande est payable
   * @return boolean Vrai si le client peut payer ou re-payer la commande
   */
  function is_payable() {
    if ($this->is_paid || $this->is_waiting_check || $this->is_gift)
      return false;
    foreach ($this->items as $item) {
      if (!$item->is_available) {
        return false;
      }
    }
    return true;
  }

  /// Vérfie si la commande possède des téléchargements payants
  function has_edeliv() {
    return $this->total['edeliv'] > 0;
  }

  /// A partir du moment ou une commande en cours est en attente de chèque ou payée, le panier doit être vidé de cette même commande
  function cannot_be_in_cart() {
    return $this->reference === $this->shop->cart->reference && $this->is_paid;
  }

  /// Vérifie la concordance entre le panier et l'order en cours d'affichage
  function checkCartCompatibility() {
    if ($this->cannot_be_in_cart()) {
      $this->shop->cart->clear();
      $GLOBALS['START_CLASS']::redirectTo($this->full_url.'&paymentStatus='.$_GET['paymentStatus']);
    }
  }

  /// Génère les formulaires de paiement des modules Monétique
  function paymentForms() {
    $forms = [];
    if (!$this->is_payable())
      return $forms;
    foreach ($this->shop->getMonetiqueMoids() as $moid) {
      $mod_monetique = \Seolan\Core\Module\Module::objectFactory($moid);
      if ($mod_monetique->testMode(true) && !$mod_monetique->testMode()) {
        \Seolan\Core\Shell::alert("Module Monétique $mod_monetique->_moid en mode de TEST");
        \Seolan\Core\Shell::alert("Pour générer les formulaires de paiement, ajoutez votre adresse IP au local.ini : testmode_ips = \"$_SERVER[REMOTE_ADDR]\"");
        continue;
      }
      $mod_monetique->urlCancelled = $this->full_url.'&paymentStatus=cancelled';
      $mod_monetique->urlError = $this->full_url.'&paymentStatus=error';
      $mod_monetique->urlPayed = $this->full_url.'&paymentStatus=success';
      try{
	list($status, $formulaireAppel, $template, $tplentry, $transaction_oid) = $form =
	  $mod_monetique->paymentCall($this->getMOrderInfos(),
				      $this->customer->getMCustomerInfos(),
				      $this->shop->getMShopInfos());
      }catch(\Exception $e){
	\Seolan\Core\Logs::critical(__METHOD__,"Erreur lors de l'initialisation du paiement : {$e->getMessage()}");
	critical_exit();
      }
      // Le tplentry est utilisé dans les templates de base xmodmonetique
      \Seolan\Core\Shell::toScreen1($tplentry, $formulaireAppel);
      $forms[] = $form;
    }
    return $forms;
  }

  /// Retourne la référence de la commande enregistrée en base de données
  function reference() {
    return $this['o'.$this->shop->orderreffield]->text;
  }

  /**
   * Retourne le client associé à la commande
   * @return XShopCustomer Acheteur
   */
  function customer() {
    return new $this->shop->customer_class($this->shop, $this['o'.$this->shop->orderuserfield]->raw);
  }

  /// Réinitialisation des lignes de la commande
  function resetLines() {
    getDB()->execute("DELETE FROM ".$this->shop->orderlinestable." WHERE orderid=?", [$this->oid]);
  }

  /// Réinitialisation des articles numériques de la commande
  function resetEdeliv() {
    if (!\Seolan\Core\System::tableExists($this->shop->edelivtable))
      return;
    getDB()->execute("DELETE FROM ".$this->shop->edelivtable." WHERE O1=?", [$this->oid]);
  }

  /// Enregistrement d'une commande à partir du panier
  function save($params, $is_new = false) {
    $params['_local'] = true;
    $params['tplentry'] = TZR_RETURN_DATA;
    if (!$is_new) {
      $params['_updateifexists'] = true;
      $params['newoid'] = $this->oid;
    }
    $res = $this->shop->procInsert($params);
    $this->oid = $res['oid'];
    $this->init();
    return $res;
  }

  /// Enregistrement d'une ligne de commande (1 ligne par produit)
  function saveLine($params) {
    $params['_local'] = true;
    $params['tplentry'] = TZR_RETURN_DATA;
    $params['orderid'] = $this->oid;
    return $this->shop->xset_orderlines->procInput($params);
  }

  /// Enregistrement d'un article numérique
  function saveEdeliv($params) {
    $params['_local'] = true;
    $params['tplentry'] = TZR_RETURN_DATA;
    $params['O1'] = $this->oid;
    return $this->shop->xset_edeliv->procInput($params);
  }

  /// Total de la commande affiché en bas à droite du tableau de la commande
  function total_html($property) {
    switch ($property) {
      case 'coupon':
        // Nom du coupon saisi
        return $this->total['coupon'];
      case 'delivery':
        if ($this->total['delivery'] > 0)
          return \Seolan\Core\Lang::price_format($this->total[$property]);
        return __('OFFERTS','\Seolan\Module\CartV2\CartV2');
      case 'reduction':
        return \Seolan\Core\Lang::floatval($this->total['reduction']) > 0 ? '-&nbsp;'.\Seolan\Core\Lang::price_format($this->total['reduction']) : '';
    }
    return \Seolan\Core\Lang::price_format($this->total[$property]);
  }

  /// Colonnes affichées dans le tableau de la commande par produits (image, prix, label, quantité...)
  function columns() {
    return $this->shop->cart->columns();
  }

  /// Colonnes affichées dans le tableau de la commande à imprimer
  function print_columns() {
    return [
      'reference' => [
        'label' => __('Référence', '\Seolan\Module\CartV2\CartV2'),
      ],
      'title' => [
        'label' => __('Libellé', '\Seolan\Module\CartV2\CartV2'),
      ],
      'priceht' => [
        'label' => __('Prix unitaire HT', '\Seolan\Module\CartV2\CartV2'),
        'type' => 'price',
      ],
      'quantity' => [
        'label' => __('Quantité', '\Seolan\Module\CartV2\CartV2'),
      ],
      'sumht' => [
        'label' => __('Prix total HT', '\Seolan\Module\CartV2\CartV2'),
        'type' => 'price',
      ],
    ]; 
  }

  /// Transforme la commande en un objet utilisable par XModMonetique
  function getMOrderInfos() {
    $morder = new \Seolan\Module\Monetique\Model\Order();
    $morder->oid = $this->oid;
    $morder->reference = $this->reference;
    $morder->amount = round(\Seolan\Core\Lang::floatval($this->amount), 2);
    $morder->traceId = $this->reference;
    $morder->date = $this->date;
    return $morder;
  }

  /**
   * Change le statut de la commande (en cours, en attente de traitement, expédiée...)
   * @param mixed Valeur à insérer dans le champ orderstatusfield
   * @return Résultat de \Seolan\Module\CartV2\CartV2::procEdit()
   */
  function changeStatus($state) {
    return $this->save([
      $this->shop->orderstatusfield => $state,
    ]);
  }

  /**
   * Change le statut du paiement (en attente, accepté, erreur)
   * @param mixed Valeur à insérer dans le champ orderpaidfield
   * @return Résultat de \Seolan\Module\CartV2\CartV2::procEdit()
   */
  function changePaymentState($state) {
    return $this->save([
      $this->shop->orderpaidfield => $state,
    ]);
  }

  /**
   * Change le type du paiement (CB, chèque...)
   * @param mixed Valeur à insérer dans le champ ordertpaidfield
   * @return Résultat de \Seolan\Module\CartV2\CartV2::procEdit()
   */
  function changePaymentType($state) {
    return $this->save([
      $this->shop->ordertpaidfield => $state,
    ]);
  }

  /**
   * Change le statut de paiement de la commande (payée, non payée...)
   * @param mixed Valeur à insérer dans le champ orderpaidfield
   * @return Résultat de \Seolan\Module\CartV2\CartV2::procEdit()
   */
  function changePaymentStatus($state) {
    $edit = $this->shop->procEdit([
      '_local' => true,
      'oid' => $this->oid,
      $this->shop->orderpaidfield => $state,
    ]);
    $this->init();
    return $edit;
  }

  /// Liste des actions disponibles dans la liste et le display de la commande
  function actions() {
    if ($this->is_paid || $this->is_waiting_check)
      return "<a href='$this->url' class='btn btn-default view-order'>".__('Voir','\Seolan\Module\CartV2\CartV2')."</a> ".
        "<a href='$this->print_url' class='btn btn-default print-order' target='print'>".__('Imprimer','\Seolan\Module\CartV2\CartV2')."</a>";
    if ($this->is_payable)
      return "<a href='$this->url' class='btn btn-success'>".__('Payer','\Seolan\Module\CartV2\CartV2')."</a>";
    return __('Commande expirée','\Seolan\Module\CartV2\CartV2');
  }

  /// Appelée lors du retour automatique de la banque par XModMonetique
  function paymentCallback($transaction) {

    // Commande à mettre à jour
    $this->changeStatus($this::STATE_ORDERED);
    $this->changePaymentType($this::PAYMENT_TYPE_CB);
    $this->changePaymentState($transaction->status === \Seolan\Module\Monetique\Monetique::SUCCESS ? $this::PAYMENT_STATE_PAID : $this::PAYMENT_STATE_ERROR);

    // Envoi des emails en cas de succès de paiement
    if ($transaction->status == \Seolan\Module\Monetique\Monetique::SUCCESS) {
      $this->sendByMail($transaction->customerEmail, _v('xmodcart.yourorder').' '.$_SERVER['SERVER_NAME']);
      $this->sendByMail($this->shop->backofficeemail, _v('xmodcart.paimentreturn').' '.$_SERVER['SERVER_NAME']);
      $this->shop->cart->clear();
    }
  }

  /// Envoi un email contenant le détail de la commande
  function sendByMail($email, $subject, $tpldata = []) {
    if (!$this->customer || !$this->customer['oid'])
      return false;
    $tpldata = array_replace_recursive([
      'shop' => [
        'order' => $this,
        'mail' => true,
      ]
    ], $tpldata);
    $xmail = new \Seolan\Library\Mail();
    $xmail->AddAddress($email);
    $xmail->From = $this->shop->sender;
    $xmail->FromName = $this->shop->sendername;
    $xmail->Subject = $subject;
    $xmail->_modid = $this->shop->_moid;
    $xmail->initLog([
      'modid' => $this->shop->_moid,
      'mtype' => 'order'
    ]);
    return $xmail->sendMailWithTemplate('Module/CartV2.shop-order-print.html', $tpldata);
  }
}
