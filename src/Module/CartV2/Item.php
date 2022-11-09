<?php
namespace Seolan\Module\CartV2;
/**
 * Objet pouvant être commandé dans la boutique
 *
 * Permet de faciliter l'affichage des lignes du panier et surtout
 * de surcharger chaque champ en fonction de la boutique
 */
class Item {

  /// Module XModCart lié au produit
  public $shop;

  /// KOID du produit
  public $oid;

  /// Chaine représentant les variantes sélectionnées par le client
  public $variantes_idx;

  /// Quantité à afficher
  public $quantity = 0;

  /// DISPLAY du produit mis au panier
  public $product;

  /// Tableau des variantes du produit (si c'est un KOID, on calcule le display)
  public $variantes = [];

  /**
   * @param \Seolan\Module\CartV2\CartV2 $shop Module dont est issu le produit
   * @param string $oid KOID du produit
   * @param int $quantity Quantité ajoutée au panier
   * @param int $variantes_idx Quantité ajoutée au panier
   */
  function __construct(\Seolan\Module\CartV2\CartV2 $shop, $oid, $quantity, $variantes_idx = '') {
    $this->shop = $shop;
    $this->oid = $oid;
    $this->quantity = $quantity;
    $this->variantes_idx = $variantes_idx;

    try {
      // Calculs du DISPLAY du produit et ses variantes
      $this->product = \Seolan\Core\DataSource\DataSource::objectDisplayHelper($oid);
    }catch(\Exception $e){
      \Seolan\Core\Logs::critical(__METHOD__,"Erreur produit non publié : {$e->getMessage()}");
    }
    $this->variantes = $shop->_idx2array($variantes_idx);
  }

  /// Instancie une propriété non-instanciée avec sa fonction éponyme
  function __get($property) {
    $this->$property = $this->$property();
    return $this->$property;
  }

  public function label() {
    return $this->product['o'.$this->shop->labelfield]->text;
  }

  public function variantes_label() {
    return $this->shop->_idx2txt($this->variantes_idx);
  }

  public function label_with_variantes() {
    return strip_tags(join(', ', array_filter([$this->label, $this->variantes_label])), '<a>');
  }

  public function remark() {
    return '';
  }

  public function image() {
    return $this->product['oimage']->html_preview;
  }

  public function reference() {
    return $this->product['o'.$this->shop->referencefield]->text;
  }

  public function reference_label() {
    return $this->shop->xset_products->desc[$this->shop->referencefield]->label.'&nbsp;: ';
  }

  public function is_promo() {
    return ($this->product['o'.$this->shop->is_promofield]->raw);
  }

  public function price() {
    if ($this->shop->xset_products->desc[$this->shop->is_promofield]) {
      return round($this->is_promo == 1 ? $this->promo : $this->originalprice * (1 - ($this->reduction / 100)), 2);
    }
    return round($this->promo ? : $this->originalprice * (1 - ($this->reduction / 100)), 2);
  }

  public function priceTTC() {
    return $this->add_taxes($this->price);
  }

  public function originalprice() {
    return round($this->product['o'.$this->shop->pricefield]->raw, 2);
  }

  public function promo() {
    return round($this->product['o'.$this->shop->promofield]->raw, 2);
  }

  public function promoTTC() {
    return $this->add_taxes($this->promoht);
  }

  public function priceTaxes() {
    return $this->priceTTC - $this->price;
  }

  public function reduction() {
    return $this->shop->customer->reduction;
  }

  /// Taxes liées au produit (en fonction du pays du client)
  public function taxes() {
    if (!$this->shop->customer->tva_applicable || !$this->product['o'.$this->shop->tvafield]->raw)
      return 0;
    $tva = \Seolan\Core\DataSource\DataSource::objectDisplayHelper($this->product['o'.$this->shop->tvafield]->raw);
    return \Seolan\Core\Lang::floatval($tva['opourc']->raw);
  }

  /// Poids du produit en grammes (g)
  public function weight() {
    return \Seolan\Core\Lang::floatval($this->product['o'.$this->shop->deliveryweight]->raw);
  }

  public function delivery() {
    return $this->product['odeliv']->raw ?: 0;
  }

  public function is_edeliv() {
    return !empty($this->edeliv_url());
  }

  public function edeliv_url() {
    return $this->product['o'.$this->shop->edelivfield]->url;
  }

  public function sum($property) {
    return $this->$property * $this->quantity;
  }

  protected function add_taxes($price) {
    return round($price * (1 + $this->taxes / 100), 2);
  }

  protected function soustract_taxes($price) {
    return round($price / (1 + $this->taxes / 100), 2);
  }

  /// A surcharger si l'on souhaite gérer un stock par exemple
  public function is_available() {
    return $this->quantity > 0 && \Seolan\Core\Kernel::objectExists2($this->product['oid']);
  }

  /// Affichage d'une propriété du produit en HTML
  public function html($property, $column, $mode = 'display') {
    if ($column['type'] == 'clearfix') {
      return;
    }
    switch ($property) {
      case 'title' :
        if ($mode == 'print')
          return nl2br($this->label_with_variantes);
        $html = $this->label;
        if ($this->variantes_label)
          $html .= "<div class='variantes'>$this->variantes_label</div>";
        if ($this->reference)
          $html .= "<div class='reference'>$this->reference_label<em>$this->reference</em></div>";
        if ($this->remark)
          $html .= "<div class='remark alert alert-warning'>$this->remark</div>";
        return $html;
      case 'quantity' :
        if ($mode != 'edit') return $this->quantity;
        return '<input type="number" name="qty['.$this->oid.']['.$this->variantes_idx.']" size="2" maxlength="3" value="'.$this->quantity.'" />
          <input type="checkbox" name="deloid['.$this->oid.']['.$this->variantes_idx.']" class="hidden" />
          <a href="#" onclick="jQuery(this).prev().prop(\'checked\',true);jQuery(this).closest(\'form\').submit();return false;">
            <span class="glyphicon glyphicon-trash"></span>
          </a>';
      case 'priceht' :
        return \Seolan\Core\Lang::price_format($this->price);
      case 'pricettc' :
        return \Seolan\Core\Lang::price_format($this->priceTTC);
      case 'taxes' :
        return \Seolan\Core\Lang::number_format($this->taxes, 2).'&nbsp;%';
      case 'originalprice' :
        return '<s>'.\Seolan\Core\Lang::price_format($this->sum('originalprice')).'</s>';
      // Pour les XShopItem la reduction est en %
      case 'reduction' :
        return \Seolan\Core\Lang::number_format($this->reduction, 2).'&nbsp;%';
      case 'sumht' :
        return \Seolan\Core\Lang::price_format($this->sum('price'));
      case 'sumttc' :
        return \Seolan\Core\Lang::price_format($this->sum('priceTTC'));
    }
    if ($column['type'] == 'price') {
      return \Seolan\Core\Lang::price_format($this->$property);
    }
    return $this->$property;
  }

  /// Permet de faire les totaux sur différentes propriétés
  public function add_to_total(&$total) {
    $total['articles']       += $this->quantity;
    $total['price']          += $this->quantity * $this->price;
    $total['pricettc']       += $this->quantity * $this->priceTTC;
    $total['weight']         += $this->quantity * $this->weight;
    $total['taxes']          += $this->quantity * $this->priceTaxes;
    $total['delivery']       += $this->quantity * $this->delivery;
    $total['originalamount'] += $this->quantity * $this->originalprice;
    // dans le total la reduction en en €
    $total['reduction']      += $this->quantity * ($this->originalprice - $this->price);
    $total['edeliv']         += $this->is_edeliv ? $this->quantity : 0;
  }
}
