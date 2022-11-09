<?php
namespace Seolan\Module\CartV2;
/**
 * Objet panier de la boutique
 */
class ShopCart extends \ArrayObject {

  /// Nom de la variable de session contenant le panier
  public $session_var           = 'cart';
  public $session_reference_var = 'cmdref';
  public $session_coupon_var    = 'coupon';
  public $shop;

  function __construct(\Seolan\Module\CartV2\CartV2 $shop) {
    parent::__construct(getSessionVar($this->session_var) ?: []);
    $this->shop = $shop;
  }

  /// Instancie une propriété non-instanciée avec sa fonction éponyme
  function __get($property) {
    $this->$property = $this->$property();
    return $this->$property;
  }

  function items() {
    $items = [];
    foreach ($this as $product_oid => $params) {
      foreach ($params as $variantes_idx => $quantity) {
        $items[] = new $this->shop->item_class($this->shop, $product_oid, $quantity, $variantes_idx);
      }
    }
    return $items;
  }

  function is_empty() {
    return count($this->items) <= 0;
  }

  /// Vérifie que tous les items du panier sont valides
  function check() {
    foreach ($this->items as $item) {
      if (!$item->is_available) {
        unset($this[$item->oid][$item->variantes_idx]);
      }
    }
  }

  /// Enregistre le panier en session et en BDD si paramétré dans le module
  function save() {
    $this->check();
    setSessionVar($this->session_var, (array) $this);
    if ($this->shop->always_save_cart_in_database)
      $this->databaseSave();
  }

  /// Efface le panier de la session
  function clear() {
    clearSessionVar($this->session_var);
    clearSessionVar($this->session_reference_var);
    clearSessionVar($this->session_coupon_var);
  }

  /// Référence du panier en cours qui sera la même référence que pour la commande
  function reference() {
    if (!issetSessionVar($this->session_reference_var))
      $this->setNewReference();
    return getSessionVar($this->session_reference_var);
  }

  /// Change de numéro de référence pour un panier
  function setNewReference() {
    $new_reference = $this->getNewReference();
    $this->setReference($new_reference);
    return $new_reference;
  }

  /// Renvoie un numéro de référence non utilisé
  function getNewReference() {
    $shop = $this->shop;
    $new_reference = date('YmdHis');
    while (getDB()->fetchExists("SELECT KOID FROM $shop->table WHERE $shop->referencefield=?", [$new_reference]))
      $new_reference++; // incrémente le numéro de référence
    return $new_reference;
  }

  /// Change le numéro de référence de la commande en cours
  function setReference($reference) {
    $this->reference = $reference;
    setSessionVar($this->session_reference_var, $this->reference);
  }

  /// Sauvegarde le panier en base (remplace le procOrder)
  function databaseSave() {
    $shop = $this->shop;
    $cart = $shop->cart;
    $customer = $shop->customer;
    $is_new = !$this->linked_order_oid();

    if (!$cart->total['articles'])
      return;

    // On envoie un mail si c'est la commande va être insérée ou si le client est tout juste renseigné (car pas de mail sans client renseigné)
    $send_new_order_mail = $shop->send_new_order_mail && ($is_new || ($this->shop->always_save_cart_in_database && !$order->customer['oid'] && $customer['oid']));

    // Si $this->linked_order_oid ne renvoie rien l'order sera tout de même instancié
    $order = new $shop->order_class($shop, $this->linked_order_oid);

    // Vérification à faire si le panier est déjà enregistré en base
    if (!$is_new) {

      // Si un ordre de paiement a déjà été effectué sur cette commande qui est au panier, on vide le panier
      $order->checkCartCompatibility();

      // Si jamais 2 utilisateurs différents ont obtenu le même numéro de commande on réinitialise ce numéro
      if ($order->customer['oid'] != $customer['oid']) {
        \Seolan\Core\Logs::critical(__CLASS__, "Order reference [$cart->reference] allready exists for another user");
        $cart->setNewReference();
        $is_new = true;
      }
    }

    $params = [
      $shop->orderreffield        => $cart->reference,
      $shop->orderdatefield       => date('Y-m-d H:i:s'),
      $shop->orderuserfield       => $customer['oid'],
      $shop->orderpaidfield       => $customer['oid'] ? $order::PAYMENT_STATE_WAITING : '',
      $shop->orderstatusfield     => $customer['oid'] ? $order::STATE_PAYMENT : $order::STATE_CART,
      $shop->orderamounthtfield   => round(\Seolan\Core\Lang::floatval($cart->total['amountHT']), 2),
      $shop->orderamountfield     => round(\Seolan\Core\Lang::floatval($cart->total['amount']), 2),
      $shop->orderdeliveryfield   => round(\Seolan\Core\Lang::floatval($cart->total['delivery']), 2),
      $shop->ordertvafield        => round(\Seolan\Core\Lang::floatval($cart->total['taxes']), 2),
      $shop->orderremisefield     => round(\Seolan\Core\Lang::floatval($cart->total['reduction']), 2),
      $shop->ordercouponfield     => $cart->total['coupon']['code'],
      $shop->orderliencouponfield => $cart->total['coupon']['oid'],
    ];

    // Champs supplémentaire proposés au client à propos de la commande (remarques, emballage cadeau...)
    foreach ($shop->getStepValidateFields() as $fieldname => $label)
      if (array_key_exists($fieldname, $_REQUEST))
        $params[$fieldname] = $_REQUEST[$fieldname];

    // FIXME Vérifier si ça marche !!?? mais dans l'état ça n'a pas l'air possible...
    if (!empty($shop->acompte) && !empty($shop->alreadypaidfield)) {
      $params[$shop->alreadypaidfield] = $cart->acompte;
    }

    // Sauvegarde la commande en base
    $order->save($params, $is_new);

    // Mise à jour des lignes de commande
    $order->resetLines();
    $order->resetEdeliv();
    foreach ($cart->items as $item) {
      $newline = $order->saveLine([
        'ref'        => $item->reference,
        'price'      => $item->price,
        'oldpriceht' => $item->originalprice,
        'tva'        => $item->taxes,
        'label'      => $item->label_with_variantes,
        'totalp'     => $item->sum('price'),
        'nb'         => $item->quantity,
        'product'    => $item->oid,
        'remise'     => $item->reduction,
        'rem'        => $item->remark,
      ]);
      // Gestion de la livraison e-delivery (téléchargement)
      if ($item->edeliv_url) {
        $newedeliv = $order->saveEdeliv([
          'DATET' => date('Y-m-d', strtotime('+'.$shop->edelivdelay.' days')),
          'DATEF' => 'today',
          'EPROD' => $item->oid,
          'MAXDWN' => $shop->edeliv_download_limit,
        ]);
        $this->shop->xset_orderlines->procEdit([
          '_local' => true,
          'oid' => $newline['oid'],
          'EDELIV' => $GLOBALS['TZR_SESSION_MANAGER']::complete_self(false,true)."moid=$shop->_moid&function=edeliv&edeliv=$newedeliv[oid]&eorder=$order->oid",
        ]);
      }
    }

    // Pour récupérer les nouvelles lignes de commande
    $order->init();

    // Alerte l'administrateur qu'une nouvelle commande est en cours
    if ($send_new_order_mail) {
      $order->sendByMail($shop->backofficeemail, __('Une commande est en cours sur votre boutique','\Seolan\Module\CartV2\CartV2'));
    }

    $this->linked_order = $order;
    $this->linked_order_oid = $order->oid;

    return $order->oid;
  }

  /**
   * @return KOID de la commande liée en base
   */ 
  function linked_order_oid() {
    $shop = $this->shop;
    $order_oid = getDB()->fetchOne("SELECT KOID FROM $shop->table WHERE $shop->referencefield=?", [ $this->reference ]);
    return \Seolan\Core\Kernel::isAKoid($order_oid) ? $order_oid : false;
  }

  /**
   * @return XShopOrder
   */ 
  function linked_order() {
    $oid = $this->linked_order_oid;
    if (!$oid)
      return false;
    return new $this->shop->order_class($this->shop, $oid);
  }

  /**
   * Calcule le total du panier (remplace la fonction view())
   * @param array &$total Total en cours de calcul
   * @return array Totaux calculés pour le panier
   */
  function total() {
    $total = [
      'articles'      => 0,
      'price'         => 0,
      'priceTTC'      => 0,
      'weight'        => 0,
      'taxes'         => 0,
      'delivery'      => 0,
      'edeliv'        => 0,
      'reduction'     => 0,
      'originalprice' => 0,
    ];
    $items = $this->items;
    foreach ($items as $item) {
      $item->add_to_total($total);
    }
    $this->compute_delivery($total);
    $this->compute_reduction($total);
    $this->compute_total($total);
    return $total;
  }

  /**
   * Ajoute les frais de livraison au total du panier
   * @param array &$total Total en cours de calcul
   */
  function compute_delivery(&$total) {

    // Calcul des frais de livraison, si au moins 1 article à expédier (edeliv = téléchargement payant)
    if (!$this->shop->customer->authentified() || !$total['articles'] || $total['edeliv'] == $total['articles'])
      return;

    $delivery_rules = $this->shop->customer->delivery_rules;

    // Calcul des frais par unité
    $unit_rules = $delivery_rules['outab']->alltable;
    foreach ($unit_rules as $unit_rule) {
      list($pmin, $pmax, $flat_price, $price_per_unit) = $unit_rule;
      if ($pmin <= $total['articles'] && $total['articles'] <= $pmax) {
        $total['delivery'] += $flat_price + $price_per_unit * $total['articles'];
        break;
      }
    }

    // Calcul des frais par poids
    $weight_rules = $delivery_rules['owtab']->alltable;
    foreach ($weight_rules as $weight_rule) {
      list($pmin, $pmax, $flat_price, $price_per_unit) = $weight_rule;
      if ($pmin <= $total['weight'] && $total['weight'] <= $pmax) {
        $total['delivery'] += $flat_price + $price_per_unit * $total['weight'];
        break;
      }
    }
  }

  /**
   * Ajoute les réductions au total du panier
   * @param array &$total Total en cours de calcul
   */
  function compute_reduction(&$total) {

    if (!$this->active_coupon_code)
      return;

    try {

      $coupon['valid'] = false;
      $coupon['code'] = $this->active_coupon_code;

      if (!$this->active_coupon)
        throw new Exception(__("Le coupon <b>%code</b> n'existe pas ou est périmé", '\Seolan\Module\CartV2\CartV2', ['code' => $this->active_coupon_code]));

      if (!$this->isCouponValid($this->active_coupon, $this->shop->customer))
        throw new Exception(__("Ce coupon a déjà été utilisé", '\Seolan\Module\CartV2\CartV2'));

      $coupon['valid'] = true;
      $coupon['display'] = $this->active_coupon;
      $coupon['oid']     = $this->active_coupon['oid'];
      $coupon['message'] = $this->active_coupon['olibelle']->html;

      $sommemin = $this->active_coupon['omina']->raw;
      $sommedisc = $this->active_coupon['odisc']->raw;
      $perc = $this->active_coupon['operc']->raw;

      // Cas d'une réduction en focntion du montant de la commande
      if (($total['price'] + $total['taxes']) >= $sommemin) {
        $coupon['reduction'] = $sommedisc + ($total['price'] + $total['taxes']) * $perc / 100;
        $total['reduction'] += $coupon['reduction'];
        if ($this->active_coupon['ofraisportsoffert']->valid)
          $total['delivery'] = __('OFFERTS','\Seolan\Module\CartV2\CartV2');

      // Sinon, il n'est pas possible d'utiliser le coupon
      } else {
        throw new Exception(__("Ce coupon n'est utilisable que pour les commandes au dessus de %amount €",'\Seolan\Module\CartV2\CartV2',['amount' => $sommemin]));
      }
    } catch (Exception $e) {
      $coupon['message'] = $e->getMessage();
    }

    $total['coupon'] = $coupon;
  }

  /**
   * Recalcule les sommes du total et ajoute les libellés
   * @param array &$total Total en cours de calcul
   */
  function compute_total(&$total) {
    $total['deliveryTaxes'] = $total['delivery'] * ($this->shop->customer->delivery_taxes / 100);
    $total['taxes']        += $total['deliveryTaxes'];
    $total['amountHT']      = $total['originalamount'] + $total['delivery'] - $total['reduction'];
    $total['amount']        = $total['amountHT'] + $total['taxes'];
    $total['edelivonly']    = $total['edeliv'] == $total['articles'];
    $total['labels']        = $this->shop->getTotalLabels($total);
    // Si la réduction est plus importante que le prix total
    if ($total['amountHT'] < 0) $total['amountHT'] = 0;
    if ($total['amount']   < 0) $total['amount']   = 0;
  }

  function active_coupon_code() {
    return getSessionVar($this->session_coupon_var);
  }

  function active_coupon() {
    foreach ($this->coupons_availables as $coupon) {
      if ($coupon['oCode']->raw === $this->active_coupon_code)
        return $coupon;
    }
    return [];
  }

  function coupons_availables() {
    if (!$this->shop->coupontable || !$this->shop->xset_coupon || !$this->shop->coupon_enabled)
      return [];
    return $this->shop->xset_coupon->browse([
      '_local' => true,
      '_mode' => 'object',
      'selectedfields' => 'all',
      'cond' => [
        'datet' => ['>=', date('Y-m-d')],
        'datef' => ['<=', date('Y-m-d')],
      ],
    ])['lines'];
  }

  /**
   * Détermine la validité d'un coupon en fonction de l'utilisateur et de l'unicité du coupon
   */
  function isCouponValid(&$coupon, &$user) {
    // Le coupon est invalide s'il est nominatif et qu'il n'appartient pas à l'utilisateur courant
    if (!empty($coupon['oUNIQUSER']->html) && $coupon['oUNIQUSER']->raw != $user['oid']) return false;
    // Le coupon est valide s'il n'est pas à usage unique
    if ($coupon['ouniqusage']->raw != 1) return true;
    // Récupération du type et de l'état du paiement de toutes les commande utilisant ce coupon
    $orders_oids = $this->shop->xset->browseOids(array(
      '_options' => array('local'=>true),
      'nocount' => true,
      'first' => 0,
      'pagesize' => -1,
      'cond' => array(
        'LIENCOUPON' => array('=',$coupon['oid']))
    ));
    foreach ($orders_oids as $order_oid) {
      // Le coupon à usage unique est invalide si :
      //  - une commande a été gratuite grâce à ce coupon
      //  - un paiement par chèque est en attente ou accepté (soit non refusé)
      //  - un paiement bancaire est accepté
      $order = $this->shop->getOrder($order_oid);
      if ($order->is_gift || $order->is_waiting_check || $order->is_paid)
        return false;
    }
    return true;
  }

  /// Colonnes affichées dans le tableau récap du panier par produits (image, prix, label, quantité...)
  function columns() {
    return [
      'title' => [
        'label' => __('Libellé', '\Seolan\Module\CartV2\CartV2'),
        'css' => 'col-md-3 col-sm-6 col-xs-12',
      ],
      'priceht' => [
        'label' => __('Prix unitaire HT', '\Seolan\Module\CartV2\CartV2'),
        'type' => 'price',
        'css' => 'col-md-1 col-sm-2 col-xs-12',
      ],
      'taxes' => [
        'label' => __('TVA', '\Seolan\Module\CartV2\CartV2'),
        'type' => 'percent',
        'css' => 'col-md-1 col-sm-2 col-xs-12',
      ],
      'pricettc' => [
        'label' => __('Prix unitaire TTC', '\Seolan\Module\CartV2\CartV2'),
        'type' => 'price',
        'css' => 'col-md-1 col-sm-2 col-xs-12',
      ],
      'clearfix-sm' => [
        'type' => 'clearfix',
        'css' => 'hidden-lg hidden-md hidden-xs',
      ],
      'quantity' => [
        'label' => __('Quantité', '\Seolan\Module\CartV2\CartV2'),
        'css' => 'col-md-2 col-sm-4 col-xs-12',
      ],
      'originalprice' => [
        'label' => __('Prix non remisé', '\Seolan\Module\CartV2\CartV2'),
        'type' => 'price',
        'css' => 'col-md-1 col-sm-2 col-xs-12',
      ],
      'reduction' => [
        'label' => __('Remise', '\Seolan\Module\CartV2\CartV2'),
        'type' => 'percent',
        'css' => 'col-md-1 col-sm-2 col-xs-12',
      ],
      'sumht' => [
        'label' => __('Prix HT', '\Seolan\Module\CartV2\CartV2'),
        'type' => 'price',
        'css' => 'col-md-1 col-sm-2 col-xs-12',
      ],
      'sumttc' => [
        'label' => __('Prix TTC', '\Seolan\Module\CartV2\CartV2'),
        'type' => 'price',
        'css' => 'col-md-1 col-sm-2 col-xs-12',
      ],
    ];
  }

  /// Retourne les valeurs des totaux affichés en bas à droite du panier
  function total_html($property, $mode = 'display') {
    switch ($property) {
      case 'coupon':
        return $this->total['reduction'] > 0 ? $this->total['coupon']['code'] : '';
      case 'delivery':
        if ($this->total['delivery'] > 0)
          return \Seolan\Core\Lang::price_format($this->total[$property]);
        if (is_string($this->total['delivery']) && 0 < strlen($this->total['delivery']))
          return $this->total['delivery'];
        return '';
      case 'reduction':
        return \Seolan\Core\Lang::floatval($this->total['reduction']) > 0 ? '-&nbsp;'.\Seolan\Core\Lang::price_format($this->total['reduction']) : '';
      case 'originalamount':
        if (\Seolan\Core\Lang::floatval($this->total['originalamount']) == \Seolan\Core\Lang::floatval($this->total['amountHT']))
          return 0;
    }
    return \Seolan\Core\Lang::price_format($this->total[$property]);
  }
}
