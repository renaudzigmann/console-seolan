<?php
namespace Seolan\Module\CartV2;
/**
 * Wizard pour installation d'un module boutique
 *
 * @todo N'a pas encore été testé entièrement !!
 *
 * @author Marie-Anne Paul, Camille Descombes
 */
class Wizard extends \Seolan\Module\Table\Wizard {

  /// Nom des templates de base situés dans public/templates/xmodinfotree/defaulttemplates/
  public static function getDefaultTemplates() {
    return [
      'function' => [
        'shopTunnel.html' => [
          'title' => 'Tunnel de commande',
          'functions' => 'Seolan\Module\CartV2\CartV2::shopTunnel',
        ],
      ]
    ];
  }

  function istep1() {
   
    // Vérifie l'existence d'un module de gestion des utilisateurs front-office indispensable !
    $frontusers = \Seolan\Core\Module\Module::modlist(['tplentry' => TZR_RETURN_DATA, 'toid' => XMODFRONTUSERS_TOID]);
    $moidadmin=\Seolan\Core\Module\Module::getMoid(XMODADMIN_TOID);
    if (!$frontusers['lines_oid']) {
      $a = $GLOBALS['START_CLASS']::buildUrl('moid='.$moidadmin.'&class=\Seolan\Module\FrontUsers\Wizard&function=newModule&template=Module/Management.modWizard.html&step=1');
      $m = "Installation impossible, vous devez tout d'abord ".
           "<a class='cv8-ajaxlink' href='$a'>Créer un module de gestion des Utilisateurs front-office</a> !";
      \Seolan\Core\Shell::alert($m);
      \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url(-1));
      return;
    }

    $this->_module->modulename = 'Boutique';
    $this->_module->group = 'Boutique';
    foreach ($GLOBALS['TZR_LANGUAGES'] as $tzrlang => $lang)
      $this->_module->comment[$tzrlang] = 'Module de gestion des commandes et de la boutique en général';
    $this->_module->createstructure = false;
    $this->_module->do_create_default_templates = true;
    \Seolan\Core\Module\Wizard::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel("Seolan_Core_General","createstructure"), "createstructure", "boolean");
    // Options spécifiques au wizard
    $this->_options->setOpts([
      '_modinfotree' => [
        'type' => 'module',
        'label' => "Gestionnaire de rubriques où créer la page du tunnel de vente",
        'options' => ['toid' => XMODINFOTREE_TOID],
      ],
    ]);

   $this->_options->setOpts(CartV2::getCommonOptions()); 

  }
  /// Etape 2 : Création de la structure OU sélection des tables existantes
  function istep2() {
    if(!$this->_module->createstructure) {
      $this->_options->setOpt('Table des commandes', "table", "table");
      $this->_options->setOpt('Table des lignes de commande', "orderlinestable", "table");
      $this->_options->setOpt('Table des coupons', "coupontable", "table");
      $this->_options->setOpt('Table des frais de port', "deliverytable", "table");
      $this->_options->setOpt('Table des pays', "countriestable", "table");
      $this->_options->setOpt("Table des taux de TVA", "tvatable", "table");
    }
  }

  /// Etape 3 :Sélection de la table des produits
  function istep3() {
    $this->_options->setOpt("Table des produits", "productstable", "table");
  }

  /// Création du module des structures et mises à jour des champs
  function iend($ar=NULL) {

    $this->createstructure();

    $new_moid = parent::iend($ar);

    // création, vérification des structures de table demandées ou manquantes
    $this->createstructure();
    
    // mise à jour des champs liens
    if (isset($this->_module->productstable) && !empty($this->_module->productstable))
      \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->_module->table)->updateDesc([
	'LIENCOUPON' => [
          'target' => $this->_module->coupontable,
	],
      ]);
    else
      \Seolan\Core\Shell::alert("La cible du champ coupon des commandes devra être complétée dans les prop. du du champ");
    if (isset($this->_module->productstable) && !empty($this->_module->productstable))
      \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->_module->orderlinestable)->updateDesc([
	'product' => [
          'target' => $this->_module->productstable,
	],
      ]);
    else
      \Seolan\Core\Shell::alert("La cible du champ produit des lignes de commandes devra être complétée dans les prop. du du champ");
    $alias = $this->_module->alias_order;
    if ($this->_module->_modinfotree && $alias) {
      $modinfotree = \Seolan\Core\Module\Module::objectFactory($this->_module->_modinfotree);
      $oid_rub = $modinfotree->getOidFromAlias($alias);
      if ($modinfotree->getOidFromAlias($alias)) {
        \Seolan\Core\Shell::alert("Alias $alias déjà existant dans le module $modinfotree");
      } else {
        $new = $modinfotree->_categories->procInput([
          '_local' => true,
          'alias' => $alias,
          'title' => 'Tunnel de vente',
        ]);
        $oid_rub = $new['oid'];
      }
      // Insertion de la section de tunnel de vente
      $modinfotree->insertfunction([
        '_local' => true,
        'oidit' => $oid_rub,
        'oidtpl' => getDB()->fetchOne('SELECT KOID FROM TEMPLATES WHERE functions LIKE ?', ['%::shopTunnel']),
        'section' => [
          'moid' => $this->_module->_modinfotree,
          'moidd' => $new_moid,
          'function' => 'shopTunnel',
        ],
      ]);
    }
    return $new_moid;
  }

  public function quickCreate($ar = []) {
    \Seolan\Core\Shell::alert("Pas de création rapide pour ce module");
    return;
  }

  /// Création des tables de la boutique
  private function createStructure() {

    $this->_module->createstructure = false;
    
    // Créé toutes les tables en fonction de la structure et ajoute ou met à jour les champs
    $structure = self::getStructure([
      'orderstable'    => $this->_module->table,
      'productstable'  => $this->_module->productstable,
      'tvatable'       => $this->_module->tvatable,
      'deliverytable'  => $this->_module->deliverytable,
      'moid_customers' => $this->_module->moid_customers,
    ]);
    $map = ['SHOPORDERS'=>'table','SHOPORDERSLINES'=>'orderlinestable',
	    'SHOPTVA'=>'tvatable','SHOPDELIVERY'=>'deliverytable',
	    'SHOPCOUNTRIES'=>'countriestable', 'SHOPCOUPONS'=>'coupontable'];

    // la table tva n'est pas en prop. du module cepandant
    foreach ($structure as $table => $params) {
      $mprop = $map[$table];
      if ((isset($this->_module->$mprop) || empty($this->_module->$mprop))
	&& !\Seolan\Core\System::tableExists($table)){
	\Seolan\Core\Logs::notice(__METHOD__,"procNewSourc '$table' '{$params['label']}'");
	\Seolan\Model\DataSource\Table\Table::procNewSource([
	  'btab' => $table,
          'bname'          => [TZR_DEFAULT_LANG=>$params['label']],
          'publish'        => $params['publish']        ?: 0,
          'auto_translate' => $params['auto_translate'] ?: 0,
          'translatable'   => $params['translatable']   ?: 0,
        ]);
	$this->_module->$mprop = $table;
      }
      \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table)->updateDesc($params['desc']);
    }

  }
  /**
   * Super fonction qui renvoie toute les structures des tables composant le module !
   *
   * @param array $params Liste de paramètre à éventuellement passer pour la définition des champs
   */
  public static function getStructure($params = []) {
    $dl = TZR_DEFAULT_LANG;
    $structure = [
      'SHOPTVA' => [
        'label' => 'Boutique - TVA',
        'desc' => [
          'tva' => [
	    'label' => [$dl=>'Code TVA'],
	    'ftype' => '\Seolan\Field\ShortText\ShortText',
	    'fcount' => 20,
	    'compulsory' => true,
	    'browsable' => true,
	    'queryable' => true,
	  ],
	  'pourc' => [
	    'label' => [$dl=>'Pourcentage TVA'],
	    'ftype' => '\Seolan\Field\Real\Real',
	    'compulsory' => true,
	    'browsable' => true,
	    'queryable' => true,
	  ],
          'label' => [
	    'label' => [$dl=>'Libellé'],
	    'ftype' => '\Seolan\Field\ShortText\ShortText',
	    'fcount' => 20,
	    'compulsory' => true,
	    'browsable' => true,
	    'queryable' => true,
		      ],
		   ],
		    ],
      
      'SHOPDELIVERY' => [
        'label' => 'Boutique - Frais de port',
        'desc' => [
          'nom' => [
            'label' => [$dl=>'Nom'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => 50,
            'compulsory' => true,
            'browsable' => true,
            'queryable' => true,
            'published' => true,
          ],
          'wtab' => [
            'label' => [$dl=>'Table par poids'],
            'ftype' => '\Seolan\Field\Table\Table',
            'fcount' => 20,
            'compulsory' => true,
            'browsable' => true,
            'queryable' => true,
          ],
          'wtab' => [
            'label' => [$dl=>'Table par unité'],
            'ftype' => '\Seolan\Field\Table\Table',
            'fcount' => 20,
            'compulsory' => true,
            'browsable' => true,
            'queryable' => true,
          ],
          'tva' => [
            'label' => [$dl=>'TVA'],
            'ftype' => '\Seolan\Field\Link\Link',
            'compulsory' => true,
            'browsable' => true,
            'queryable' => true,
            'target' => $params['tvatable'],
          ],
          'code' => [
            'label' => [$dl=>'Code interne'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => 20,
            'browsable' => true,
            'queryable' => true,
          ],
        ],
      ],

      'SHOPCOUNTRIES' => [
        'label' => 'Boutique - Pays',
        'desc' => [
          'state' => [
            'label' => [$dl=>'Nom'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => 100,
            'compulsory' => true,
            'browsable' => true,
            'queryable' => true,
          ],
          'tzone' => [
            'label' => [$dl=>'TVA Applicable'],
            'ftype' => '\Seolan\Field\Boolean\Boolean',
            'compulsory' => true,
            'browsable' => true,
            'queryable' => true,
          ],
          'deliv' => [
            'label' => [$dl=>'Zone de livraison'],
            'ftype' => '\Seolan\Field\Link\Link',
            'compulsory' => true,
            'browsable' => true,
            'queryable' => true,
            'target' => $params['deliverytable'],
          ],
        ],
      ],
      'SHOPCOUPONS' => [
        'label' => 'Boutique - Coupons',
        'desc' => [
          'Code' => [
            'label' => [$dl=>'Code'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => 50,
            'browsable' => true,
            'queryable' => true,
          ],
          'mina' => [
            'label' => [$dl=>'Montant minimal commande'],
            'ftype' => '\Seolan\Field\Real\Real',
          ],
          'disc' => [
            'label' => [$dl=>'Réduction (en €)'],
            'ftype' => '\Seolan\Field\Real\Real',
          ],
          'perc' => [
            'label' => [$dl=>'Réduction (en %)'],
            'ftype' => '\Seolan\Field\Real\Real',
          ],
          'datet' => [
            'label' => [$dl=>"Date d'activation"],
            'ftype' => '\Seolan\Field\Date\Date',
            'browsable' => true,
            'queryable' => true,
          ],
          'datef' => [
            'label' => [$dl=>"Date de désactivation"],
            'ftype' => '\Seolan\Field\Date\Date',
            'browsable' => true,
            'queryable' => true,
          ],
          'UNIQUSER' => [
            'label' => [$dl=>'Utilisateur unique'],
            'ftype' => '\Seolan\Field\Link\Link',
            'target' => 'USERS',
            'options' => ['sourcemodule' => $params['moid_customers']],
          ],
          'fraisportsoffert' => [
            'label' => [$dl=>'Frais de port offerts'],
            'ftype' => '\Seolan\Field\Boolean\Boolean',
          ],
          'uniqusage' => [
            'label' => [$dl=>'Usage unique'],
            'ftype' => '\Seolan\Field\Boolean\Boolean',
          ],
        ],
      ],
      'SHOPORDERSLINES' => [
        'label' => 'Boutique - Lignes de commande',
        'desc' => [
          'orderid' => [
            'label' => [$dl=>'Commande'],
            'ftype' => '\Seolan\Field\Link\Link',
            'compulsory' => true,
            'browsable' => true,
            'target' => $params['orderstable'],
          ],
          'ref' => [
            'label' => [$dl=>'Référence produit'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '100',
            'compulsory' => true,
            'browsable' => true,
          ],
          'label' => [
            'label' => [$dl=>'Libellé'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '100',
            'browsable' => true,
          ],
          'nb' => [
            'label' => [$dl=>'Quantité'],
            'ftype' => '\Seolan\Field\Real\Real',
            'compulsory' => true,
            'browsable' => true,
          ],
          'price' => [
            'label' => [$dl=>'Prix HT'],
            'ftype' => '\Seolan\Field\Real\Real',
            'compulsory' => true,
            'browsable' => true,
          ],
          'tva' => [
            'label' => [$dl=>'TVA'],
            'ftype' => '\Seolan\Field\Real\Real',
            'browsable' => true,
          ],
          'totalp' => [
            'label' => [$dl=>'Total'],
            'ftype' => '\Seolan\Field\Real\Real',
            'compulsory' => true,
            'browsable' => true,
          ],
          'rem' => [
            'label' => [$dl=>'Remarque'],
            'ftype' => '\Seolan\Field\Text\Text',
          ],
          'product' => [
            'label' => [$dl=>'Produit'],
            'ftype' => '\Seolan\Field\Link\Link',
            'target' => $params['productstable'],
          ],
        ],
      ],
      'SHOPORDERS' => [
        'label' => 'Boutique - Commandes', 
        'desc' => [
          'CREAD' => [
            'label' => [$dl=>'Date'],
            'ftype' => '\Seolan\Field\DateTime\DateTime',
            'compulsory' => true,
            'queryable' => true,
            'browsable' => true,
          ],
          'ref' => [
            'label' => [$dl=>'Référence'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => 100,
            'compulsory' => true,
            'queryable' => true,
            'browsable' => true,
            'published' => true,
          ],
          'orderstatus' => [
            'label' => [$dl=>'Statut'],
            'ftype' => '\Seolan\Field\StringSet\StringSet',
            'compulsory' => true,
            'queryable' => true,
            'browsable' => true,
          ],
          'customer' => [
            'label' => [$dl=>'Client'],
            'ftype' => '\Seolan\Field\Link\Link',
            'target' => 'USERS',
            'compulsory' => true,
            'queryable' => true,
            'browsable' => true,
          ],
          'total' => [
            'label' => [$dl=>'Montant total'],
            'ftype' => '\Seolan\Field\Real\Real',
            'compulsory' => true,
            'queryable' => true,
            'browsable' => true,
            'published' => true,
          ],
          'totalht' => [
            'label' => [$dl=>'Montant total HT'],
            'ftype' => '\Seolan\Field\Real\Real',
            'compulsory' => true,
            'queryable' => true,
            'browsable' => true,
          ],
          'delivery' => [
            'label' => [$dl=>'Frais de port'],
            'ftype' => '\Seolan\Field\Real\Real',
            'compulsory' => true,
            'queryable' => true,
            'browsable' => true,
          ],
          'tva' => [
            'label' => [$dl=>'TVA'],
            'ftype' => '\Seolan\Field\Real\Real',
            'compulsory' => true,
            'queryable' => true,
            'browsable' => true,
          ],
          'disc' => [
            'label' => [$dl=>'Remise'],
            'ftype' => '\Seolan\Field\Real\Real',
            'compulsory' => true,
            'queryable' => true,
            'browsable' => true,
          ],
          'paid' => [
            'label' => [$dl=>'État du paiement'],
            'ftype' => '\Seolan\Field\Real\Real',
            'queryable' => true,
            'browsable' => true,
          ],
          'tpaid' => [
            'label' => [$dl=>'Type de paiement'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => 32,
          ],
          'coupon' => [
            'label' => [$dl=>'Code coupon'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '64',
            'queryable' => true,
          ],
          'LIENCOUPON' => [
            'label' => [$dl=>'Coupon utilisé'],
            'ftype' => '\Seolan\Field\Link\Link',
            'queryable' => true,
            'target' => 'SHOPCOUPONS',
          ],
          'giftwrap' => [
            'label' => [$dl=>'Emballage cadeau'],
            'ftype' => '\Seolan\Field\Boolean\Boolean',
            'queryable' => true,
          ],
          'rem' => [
            'label' => [$dl=>'Observations'],
            'ftype' => '\Seolan\Field\Text\Text',
          ],
        ],
      ],
    ];
    // ajout des traductions des labels de champs
    if ($dl != 'GB' && !\Seolan\Core\Shell::getMonoLang()){
      foreach($structure as $tbname=>&$xsetDDL){
	foreach($xsetDDL['desc'] as $fn=>&$fieldDDL){
	  foreach ($GLOBALS['TZR_LANGUAGES'] as $tzrlang => $lang){
	    if ($tzrlang == $dl)
	      continue;
	    echo("\n$lang $tzrlang");
	    if (isset(static::$translations[$fieldDDL['label'][$dl]]))
	      $fieldDDL['label'][$tzrlang] = static::$translations[$fieldDDL['label'][$dl]];
	    else
	      $fieldDDL['label'][$tzrlang] = $fieldDDL['label'][$dl];
	  }
	}
      }
    }
    return $structure;
  }
  static protected $translations = [
  //TVA
  'Code TVA'=>'VAT Code',
  'Pourcentage TVA'=>'VAT Percentage',
  'Libellé'=>'Label',
  //Frais de port
  'Nom'=>'Name',
  'Table par poids'=>'Table by weight',
  'Table par unité'=>'Table by unit',
  'TVA'=>'VAT',
  'Code interne'=>'Internal code',
  //Pays
  'Nom'=>'Name',
  'TVA Applicable'=>'Applicable VAT',
  'Zone de livraison'=>'Delivery area',
  //Coupons
  'Code'=>'Code',
  'Montant minimal commande'=>'Minimal order amount',
  'Réduction (en €)'=>'Reduction (€)',
  'Réduction (en %)'=>'Reduction (%)',
  "Date d'activation"=>'Activation date',
  "Date de désactivation"=>'Deactivation date',
  'Utilisateur unique'=>'Single user',
  'Frais de port offerts'=>'Free shipping',
  'Usage unique'=>'Single user',
  //Lignes de commande
  'Commande'=>'Order',
  'Référence produit'=>'Product reference',
  'Libellé'=>'Label',
  'Quantité'=>'Quantity',
  'Prix HT'=>'Price HT',
  'TVA'=>'VAT',
  'Total'=>'Total',
  'Remarque'=>'Note',
  'Produit'=>'Product',
  //Commandes, 
  'Date'=>'Date',
  'Référence'=>'Reference',
  'Statut'=>'Order status',
  'Client'=>'Customer',
  'Montant total'=>'Total amount',
  'Montant total HT'=>'Total amount HT',
  'Frais de port'=>'Shipping',
  'TVA'=>'VAT',
  'Remise'=>'Discount',
  'État du paiement'=>'Payment status',
  'Type de paiement'=>'Payment type',
  'Code coupon'=>'Coupon code',
  'Coupon utilisé'=>'Coupon used',
  'Emballage cadeau'=>'Gift wrap',
  'Observations'=>'Obsservations'
];
}
