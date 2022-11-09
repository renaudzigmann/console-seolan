<?php
namespace Seolan\Module\CartV2;
/**
 * Objet offre/promotion/coupon de la boutique
 *
 * @package Boutique
 * @see XModCart
 */
class Offer extends \ArrayObject {

  public $shop; ///< XModCart Module boutique
  public $oid; ///< string KOID de l'offre

  /**
   * @param \Seolan\Module\CartV2\CartV2 $shop Module boutique
   * @param mixed $offer KOID ou DISPLAY_RESULTSET de la commande
   */
  function __construct(\Seolan\Module\CartV2\CartV2 $shop, $offer) {
    $this->shop = $shop;
    if (\Seolan\Core\Kernel::isAKoid($offer, $shop->coupontable))
      $offer = \Seolan\Core\DataSource\DataSource::objectDisplayHelper($offer);
    if (is_array($offer)) {
      $this->oid = $offer['oid'];
      parent::__construct($offer);
    } else {
      parent::__construct([]);
    }
  }

  /// Instancie une propriété public non-instanciée avec sa fonction éponyme
  function __get($property) {
    $this->$property = $this->$property();
    return $this->$property;
  }

  function title() {
    return $this->coupon_code ?: $this['olibelle']->text;
  }

  function coupon_code() {
    return $this['oCode']->raw;
  }

  function is_coupon() {
    return strlen($this->coupon_code) > 0;
  }

  function is_exclusive() {
    return $this['oexclusive']->valid;
  }

  function priority() {
    return intval($this['opriority']->raw);
  }

  function is_cart_applicable($total) {
    try {
      if ($this->is_coupon && !$this->shop->cart->active_coupon_code)
        throw new Exception(__("Aucun code coupon n'a été saisi par l'utilisateur", '\Seolan\Module\CartV2\CartV2'));
      if ($this->is_coupon && !$this->is_code_valid)
        throw new Exception(__("Le code saisi par l'utilisateur ne correspond pas au code de ce coupon", '\Seolan\Module\CartV2\CartV2'));
      if (!$this->is_period_valid)
        throw new Exception(__("Offre périmée",'\Seolan\Module\CartV2\CartV2'));
      if (!$this->is_min_amount_valid($total))
        throw new Exception(__("Offre valable uniquement pour les commandes au dessus de %amount € %type",'\Seolan\Module\CartV2\CartV2',[
          'amount' => $this['omina']->raw,
          'type' => $this['otargetMina']->html]
        ));
      if (!$this->is_max_amount_valid($total))
        throw new Exception(__("Offre valable seulement pour les commandes au dessous de %amount € %type",'\Seolan\Module\CartV2\CartV2',[
          'amount' => $this['omaxa']->raw,
          'type' => $this['otargetMaxa']->html]
        ));
      if (!$this->is_stock_valid)
        throw new Exception(__("Offre épuisée",'\Seolan\Module\CartV2\CartV2'));
      if (!$this->is_usage_valid)
        throw new Exception(__("Vous avez déjà utilisé cette offre",'\Seolan\Module\CartV2\CartV2'));
      if (!$this->is_user_valid)
        throw new Exception(__("Offre réservée à un particulier autre que vous",'\Seolan\Module\CartV2\CartV2'));
      if (!$this->is_groups_valid)
        throw new Exception(__("Offre réservée à un groupe de personne dont vous ne faites pas parti",'\Seolan\Module\CartV2\CartV2'));
      if (!$this->is_products_rules_valid)
        throw new Exception(__("Les produits situés dans le panier ne permettent pas de valider cette offre",'\Seolan\Module\CartV2\CartV2'));
    } catch (Exception $e) {
      $this->exception = $e;
      return false;
    }
    return true;
  }

  function is_code_valid() {
    return $this->is_coupon && $this->shop->cart->active_coupon_code === $this->coupon_code;
  }

  function is_period_valid() {
    $now = strtotime(gmdate('Y-m-d'));
    return $this['odatef']->timestamp() <= $now && $now <= $this['odatet']->timestamp();
  }

  /// Le stock est valide si le nombre de commandes payées ayant utilisé cette offre n'est pas dépassé
  function is_stock_valid() {
    return !$this['ostock']->raw > 0 || $this['ostock']->raw >= $this->usesCount;
  }

  /// L'usage de l'offre est autorisée si l'offre n'est pas à usage unique ou si le client connecté ne l'a pas encore utilisée
  function is_usage_valid() {
    return !$this['ouniqusage']->valid || $this->usesCountForCurrentUser == 0;
  }

  function is_user_valid() {
    return !$this['oUNIQUSER']->raw || $this['oUNIQUSER']->raw === $this->shop->customer->oid;
  }

  function is_groups_valid() {
    return !$this['ogroups']->html || \Seolan\Core\User::getInstance()->inGroups($this['ogroups']->oidcollection, false);
  }

  function is_amount_valid($total) {
    return $this->is_min_amount_valid($total) && $this->is_max_amount_valid($total);
  }

  function is_min_amount_valid($total) {
    $min_amount = \Seolan\Core\Lang::floatval($this['omina']->raw);
    if ($min_amount <= 0)
      return true;
    $cart_amount = $total['price'];
    if ($this['otargetMina']->raw === 'ttc')
      $cart_amount += $total['taxes'];
    return $cart_amount >= $min_amount;
  }

  function is_max_amount_valid($total) {
    $max_amount = \Seolan\Core\Lang::floatval($this['omaxa']->raw);
    if ($max_amount <= 0)
      return true;
    $cart_amount = $total['price'];
    if ($this['otargetMaxa']->raw === 'ttc')
      $cart_amount += $total['taxes'];
    return $cart_amount <= $max_amount;
  }

  function is_products_rules_valid() {
    $productsRules = json_decode($this['oproductsRules']->raw);
    foreach ($productsRules->quantities as $i => $quantity) {
      $cart_items_in_rule = 0;
      $query_oid = $productsRules->queries[$i];
      $query = \Seolan\Core\DataSource\DataSource::objectDisplayHelper($query_oid);
      $mod_catalogue = \Seolan\Core\Module\Module::objectFactory($query['omodid']->raw);
      $products_oids = $mod_catalogue->procQuery([
        '_local' => true,
        'selectedfields' => ['KOID'],
        '_storedquery' => $query_oid,
      ])['lines_oid'];
      foreach ($this->shop->cart->items as $item)
        if (in_array($item->oid, $products_oids))
          $cart_items_in_rule += $item->quantity;
      if ($cart_items_in_rule < $quantity) {
        \Seolan\Core\Logs::debug("$quantity items from the stored query '$query[otitle]' are required in cart ($cart_items_in_rule counted in current cart)");
        return false;
      }
    }
    return true;
  }

  function usesCount() {
    $orders = $this->shop->table;
    $paid = $this->shop->orderpaidfield;
    $liencoupon = $this->shop->orderoffersfield;
    return getDB()->count("SELECT COUNT(*) FROM $orders WHERE $paid=? AND ($liencoupon=? OR instr($liencoupon,?))", [
      $this->shop->order_class::PAYMENT_STATE_PAID,
      $offer['oid'],
      "||$offer[oid]||",
    ]);
  }

  function usesCountForCurrentUser() {
    $orders = $this->shop->table;
    $paid = $this->shop->orderpaidfield;
    $user = $this->shop->orderuserfield;
    $uid = $this->shop->customer->oid;
    $liencoupon = $this->shop->orderoffersfield;
    return getDB()->count("SELECT COUNT(*) FROM $orders WHERE $paid=? $user=? AND ($liencoupon=? OR instr($liencoupon,?))", [
      $this->shop->order_class::PAYMENT_STATE_PAID,
      $uid,
      $offer['oid'],
      "||$offer[oid]||",
    ]);
  }
}
