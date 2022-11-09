<?php
namespace Seolan\Module\CartV2;
/**
 * Module de gestion des promotions/coupons d'une boutique générique
 * 
 * @author Camille Descombes
 */
class Offers extends \Seolan\Module\Table\Table {

  /// MOID du module des produits en vente sur le site
  public $moid_products = null;

  function __construct($ar) {
    parent::__construct($ar);
    $this->addCallback(self::EVENT_POST_CRUD, function($module, $resultset, $function) {
      $this->optimizeCrudRendering($resultset, $function);
      return $resultset;
    });
    $this->addCallback(self::EVENT_PRE_LIST, function($module, $ar, $function) {
      $ar['_mode'] = 'both';
      return $ar;
    });
    $this->addCallback(self::EVENT_POST_LIST, function($module, $resultset, $function) {
      $this->optimizeListRendering($resultset, $function);
      return $resultset;
    });
  }

  /**
   * Vérifie qu'un module de produit est bien associé au module des promotions
   */
  function chk(&$message=NULL) {
    parent::chk($message);
    if (!$this->moid_products)
      $message .= 'Module catalogue non renseigné dans les propriétés du module promotion.'.PHP_EOL;
    return true;
  }

  /**
   * Fonction déclenchable manuellement via Administration de la structure de données > Vérifier et réparer
   */
  function adminChk() {
    // Met à jour la structure de la table des promotions/coupons en fonction de ce qui est décrit dans l'installateur (wizard)
    $shop = \Seolan\Core\Module\Module::singletonFactory(XMODCART_TOID);
    $structure = XModCartWd::getStructure([
      'productstable' => $shop->productstable,
      'orderstable' => $shop->table,
      'moid_customers' => $shop->moid_customers,
    ]);
    $this->xset->updateDesc($structure['SHOPOFFERS']['desc']);
  }

  /**
   * Options modifiables à l'installation et après installation du module
   */
  public static function getWizardOptions($mode = 'wizard') {
    $shop = \Seolan\Core\Module\Module::singletonFactory(XMODCART_TOID);
    return [
      'moid_products' => [
        'label' => 'Module catalogue',
        'type' => 'module',
        'comment' => "Lors de la création d'une nouvelle promotion, le champ RÈGLES PRODUITS ira chercher les recherches sauvegardées du module catalogue ici sélectionné et les utilisera en tant que filtre conditionnel pour l'application de la réduction sur le panier.",
        'options' => ['table' => $shop->productstable],
      ],
    ];
  }

  /**
   * Encode en JSON le champ productsRules à chaque soumission de formulaire d'ajout/modification
   */
  function procEditCtrl(&$ar) {
    $p = new \Seolan\Core\Param($ar);
    $ar['productsRules'] = json_encode($p->get('productsRules'));
    $code = $p->get('Code');
    if ($code && 0 < getDB()->count("SELECT COUNT(*) FROM $this->table WHERE Code=?", [$code]))
      throw new Exception(__("Code COUPON déjà existant", '\Seolan\Module\CartV2\CartV2'));
    return parent::procEditCtrl($ar);
  }

  /**
   * Marque les coupons non publiés
   */
  function optimizeListRendering(&$br, $function) {
    $now = strtotime(gmdate('Y-m-d'));
    foreach ($br['lines'] as $i => $offer) {
      if ($offer['odatef']->timestamp() > $now || $now > $offer['odatet']->timestamp())
        $br['lines_trclass'][$i] = 'napp invalid-offer'; // napp => idem que non publié
      $offer['oproductsRules']->html = $this->getProductsRulesHtml($offer, $function);
    }
  }

  /**
   * Rend la saisie du formulaire de promotion plus ergonomique
   *
   * @param BROWSE_RESULTSET $br
   * @return void
   */
  function optimizeCrudRendering(&$br, $function) {

    // Dates de validité sur une seule ligne
    $br['odatef']->fielddef->label = "Dates de validité";
    $br['odatef']->html = "Du ".$br['odatef']->html." au ".$br['odatet']->html;

    $br['omina']->fielddef->label = "Montant de la commande";
    $br['omina']->html = "Entre ".$br['omina']->html.' &euro; '.$br['otargetMina']->html.' et '.$br['omaxa']->html.' &euro; '.$br['otargetMaxa']->html;

    $br['otypeReduc']->fielddef->label = "Remise";
    $br['otypeReduc']->html = $this->getTypeReducHtml($br);

    $br['oproductsRules']->html = $this->getProductsRulesHtml($br, $function);

    // Workaround : pour cacher certains champs du formulaire on utilise le paramètre _linkedfields
    // qui est normalement utilisé pour cacher les champs "Lien vers un objet" dans le cadre de
    // l'ajout/modification d'une fiche via un sous module
    $_REQUEST['_linkedfields'] = array_merge(@$_REQUEST['_linkedfields'] ?: [], ['datet','targetMina','maxa','targetMaxa','disc','perc','targetReduc','cadeau']);
  }

  /**
   * Renvoie le code HTML d'édition du type de réduction à appliquer
   *
   * @param BROWSE_RESULTSET $br
   * @return string
   */
  function getTypeReducHtml($br) {
    return $br['otypeReduc']->html." 
      <div class='typeReduc-dependant typeReduc-disc'>".$br['odisc']->html." &euro;</div>
      <div class='typeReduc-dependant typeReduc-perc'>".$br['operc']->html." %</div>
      <div class='typeReduc-dependant typeReduc-perc typeReduc-disc'>A appliquer sur ".$br['otargetReduc']->html."</div>
      <script>
        (function($){
          $(':input[name=\"typeReduc\"]').on('change',function(){
            $('.typeReduc-dependant').hide();
            $('.typeReduc-' + $(this).val()).show();
          }).trigger('change');
        }(jQuery));
      </script>";
  }

  /**
   * Renvoie le code HTML calculé pour les règles panier en fonction des produits s'y trouvant
   *
   * @param BROWSE_RESULTSET $br
   * @return string
   */
  function getProductsRulesHtml($br, $function) {

    // Vérification de la configuration
    if (!$this->moid_products) {
      $editPropertiesLink = $this->actionlist1()['editProperties']->xurl;
      return 'Vous devez <a href="'.$editPropertiesLink.'" class="cv8-ajaxlink">sélectionner un module catalogue dans les propriétés de ce module</a> (option MODULE CATALOGUE) avant de pouvoir utiliser cette condition.';
    }

    // Vérification de l'activation des recherches sauvegardées sur le module des produits sélectionné
    $mod_products = \Seolan\Core\Module\Module::objectFactory($this->moid_products);
    if (!$mod_products->stored_query) {
      $link = $mod_products->actionlist1()['editProperties']->xurl;
      return 'Vous devez <a href="'.$link.'" class="cv8-ajaxlink">activer les recherches sauvegardées du module catalogue</a> avant de pouvoir utiliser cette condition.';
    }

    // Vérification de l'existence de recherches sauvegardées sur le module des produits sélectionné
    $queries_options = '';
    foreach ($mod_products->storedQueries()['lines'] as $storedQuery)
      $queries_options.= '<option value="'.$storedQuery['oid'].'">'.$storedQuery['otitle']->text.'</option>';
    $createStoredQueryLink = $mod_products->actionlist1()['query']->xurl;
    if (!$queries_options) {
      return 'Vous devez <a href="'.$createStoredQueryLink.'" class="cv8-ajaxlink">créer des sélections de produits</a> à l\'aide des RECHERCHES SAUVEGARDÉES dans le module catalogue avant de pouvoir utiliser cette condition.';
    }

    $productsRules = json_decode($br['oproductsRules']->raw);
    $code = "<div id='productRulesWrapper'></div><script>
      (function($){
        var addProductRule = function(quantity, query){
          if (!$('#productRulesWrapper').text().length) $('#productRulesWrapper').html('<div>Le panier doit contenir au moins :</div>');
          var div = $(\"<div class='productsRule'></div>\");
          div.append(\"<input name='productsRules[quantities][]' type='number' value='\"+quantity+\"' style='width:30px;'> produit(s) de la sélection \");
          div.append($('<select name=\"productsRules[queries][]\">$queries_options</select>').val(query));
          div.append($(\"<input type='button' value='Supprimer'>\").on('click', function(){
            $(this).closest('div').remove();
          }));
          $('#productRulesWrapper').append(div);
        };
        $(\"<a href='$createStoredQueryLink' class='cv8-ajaxlink' title='Une sélection de produit correspond à une recherche sauvegardée dans le module catalogue'>Créer une nouvelle sélection de produits dans le catalogue</a>\").on('click', function(){
          return confirm('Attention, vous allez quitter cette page et perdre tous les paramètres saisis en effectuant cette action, êtes-vous sur de vouloir continuer ?');
        }).insertAfter('#productRulesWrapper');
        $(\"<input type='button' value='Ajouter une règle'>\").on('click', function(){
          addProductRule(1, '');
        }).insertAfter('#productRulesWrapper');";
    foreach ($productsRules->quantities as $i => $quantity) {
      $query = $productsRules->queries[$i];
      $code.= "addProductRule('$quantity','$query');";
    }
    if (!in_array($function, ['insert', 'edit'])) {
      $code.= "$('#productRulesWrapper :input').prop('disabled', true);";
      $code.= "$('#productRulesWrapper').parent().find('input[type=button], a').remove();";
    }
    $code.= "
      }(jQuery));
    </script>";
    return $code;
  }
}
