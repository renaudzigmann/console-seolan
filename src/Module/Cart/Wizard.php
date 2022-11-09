<?php
namespace Seolan\Module\Cart;
/// Wizard de creation d'un module de gestion de caddie
class Wizard extends \Seolan\Module\Table\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }

  function istep1() {
    $this->_module->backofficeemail="you@email.com";
    $this->_module->sendername="Your full name";
    $this->_module->createstructure=false;
    \Seolan\Core\Module\Wizard::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','createstructure'), "createstructure", "boolean");
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','sendername'), "sendername", "text");
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','backofficeemail'), "backofficeemail", "text");
  }
  private function createStructure() {
    $this->_module->createstructure = false;
    $newtable=\Seolan\Model\DataSource\Table\Table::newTableNumber();
    $cmdtable=$newtable;
    $lg = TZR_DEFAULT_LANG;
    $ar1=array();
    $ar1["translatable"]="0";
    $ar1["publish"]="0";
    $ar1["auto_translate"]="0";
    $ar1["btab"]=$newtable;
    $ar1["bname"][$lg]="Gestion de boutique - Table des commandes";
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);

    $x->createField('F0001',                                                               // ord obl que bro tra mul pub tar
		    \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','ref'),'\Seolan\Field\ShortText\ShortText',               '100','1','1','1','1','0','0','1');
    $x->createField('F0002',                                                           // ord obl que bro tra mul pub tar
		    \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','date'),'\Seolan\Field\DateTime\DateTime',               '','2','1','1','1','0','0','0');
    $x->createField('F0003',                                                           // ord obl que bro tra mul pub tar
		    \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','orderstatus'),'\Seolan\Field\StringSet\StringSet',  '','3','1','1','1','0','0','0');
    $x->createField('F0004',                             // ord obl que bro tra mul pub tar
		    'Montant total commande','\Seolan\Field\Real\Real', '15','4','1','1','1','0','0','1');
    $x->createField('F0005',                             // ord obl que bro tra mul pub tar
		    'Montant livraison','\Seolan\Field\Real\Real',      '15','5','1','1','1','0','0','0');
    $x->createField('F0006',                             // ord obl que bro tra mul pub tar
		    'Client','\Seolan\Field\Link\Link',                     '','6','1','1','1','0','0','1','USERS');
    $x->createField('totalht',                           // ord obl que bro tra mul pub tar
		    'Montant total produits','\Seolan\Field\Real\Real', '15','7','1','1','0','0','0','0');
    $x->createField('tva',                          // ord obl que bro tra mul pub tar
		    'Montant TVA','\Seolan\Field\Real\Real',            '15','8','1','1','1','0','0','0');
    $x->createField('rem',                               // ord obl que bro tra mul pub tar
		    'Observations','\Seolan\Field\Text\Text',           '60','9','1','1','1','0','0','0');
    $x->createField('paid',                              // ord obl que bro tra mul pub tar
		    'Mode de paiement','\Seolan\Field\ShortText\ShortText', '20','10','1','1','1','0','0','0');
    $x->createField('disc',                              // ord obl que bro tra mul pub tar
		    'Total remise','\Seolan\Field\Real\Real',           '15','11','1','1','1','0','0','0');
    $x->createField('coupon',                            // ord obl que bro tra mul pub tar
		    'Coupon','\Seolan\Field\ShortText\ShortText',           '20','12','1','1','1','0','0','0');
    $this->_module->table=$newtable;

    $newtable=\Seolan\Model\DataSource\Table\Table::newTableNumber();
    $orderlinestable=$newtable;
    $lg = TZR_DEFAULT_LANG;
    $ar1=array();
    $ar1["translatable"]="0";
    $ar1["publish"]="0";
    $ar1["auto_translate"]="0";
    $ar1["btab"]=$newtable;
    $ar1["bname"][$lg]="Gestion de boutique - Lignes des commandes";
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
                                                                 
    $x->createField('F0001',                          //ord obl que bro tra mul pub tar
		    'Commande','\Seolan\Field\Link\Link',               '','1','1','1','1','0','0','1',$cmdtable);
    $x->createField('ref',                           //ord obl que bro tra mul pub tar
		    'Reference','\Seolan\Field\ShortText\ShortText',     '20','2','1','1','1','0','0','1');
    $x->createField('label',                         //ord obl que bro tra mul pub tar
		    'Libelle','\Seolan\Field\ShortText\ShortText',      '100','3','1','1','1','0','0','1');
    $x->createField('price',                         //ord obl que bro tra mul pub tar
		    'Prix HT','\Seolan\Field\Real\Real',            '15','4','1','1','1','0','0','1');
    $x->createField('tva',                           //ord obl que bro tra mul pub tar
		    'TVA','\Seolan\Field\Real\Real',                '15','5','1','1','1','0','0','0');
    $x->createField('totalp',                        //ord obl que bro tra mul pub tar
		    'TVA','\Seolan\Field\Real\Real',                '15','6','1','1','1','0','0','0');
    $x->createField('nb',                            //ord obl que bro tra mul pub tar
		    'Nombre','\Seolan\Field\Real\Real',             '15','7','1','1','1','0','0','0');
    $x->createField('rem',                            //ord obl que bro tra mul pub tar
		    'Remarque','\Seolan\Field\ShortText\ShortText',      '100','8','1','1','1','0','0','0');

    $tvatable=\Seolan\Model\DataSource\Table\Table::newTableNumber();
    $lg = TZR_DEFAULT_LANG;
    $ar1=array();
    $ar1["translatable"]="0";
    $ar1["publish"]="0";
    $ar1["auto_translate"]="0";
    $ar1["btab"]=$tvatable;;
    $ar1["bname"][$lg]="Gestion de boutique - Code TVA";
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$tvatable);
    
    $x->createField('tva',                              //ord obl que bro tra mul pub tar
		    'Code TVA','\Seolan\Field\ShortText\ShortText',        '20','1','1','1','1','0','0','1');
    $x->createField('pourc',                          //ord obl que bro tra mul pub tar
		    'Pourcentage TVA','\Seolan\Field\Real\Real',    '15','2','1','1','1','0','0','0');
    $x->createField('label',                         //ord obl que bro tra mul pub tar
		    'Libelle TVA','\Seolan\Field\ShortText\ShortText',    '20','3','1','1','1','0','0','0');
    
    $newtable=\Seolan\Model\DataSource\Table\Table::newTableNumber();
    $lg = TZR_DEFAULT_LANG;
    $ar1=array();
    $ar1["translatable"]="0";
    $ar1["publish"]="0";
    $ar1["auto_translate"]="0";
    $ar1["btab"]=$newtable;
    $ar1["bname"][$lg]="Gestion de boutique - Pays";
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    
    $x->createField('state',                          //ord obl que bro tra mul pub tar
		    'Pays','\Seolan\Field\ShortText\ShortText',         '20','1','1','1','1','0','0','1');
    $x->createField('tzone',                          //ord obl que bro tra mul pub tar
		    'TVA Applicable','\Seolan\Field\Boolean\Boolean',     '','2','1','1','1','0','0','0');
    $x->createField('deliv',                         //ord obl que bro tra mul pub tar
		    'Zone de livraison','\Seolan\Field\Link\Link',   '','3','1','1','1','0','0','0','DELIV');
    
    if(!\Seolan\Core\System::tableExists('DELIV')) {
      $newtable="DELIV";
      $lg = TZR_DEFAULT_LANG;
      $ar1=array();
      $ar1["translatable"]="0";
      $ar1["publish"]="0";
      $ar1["auto_translate"]="0";
      $ar1["btab"]=$newtable;
      $ar1["bname"][$lg]="Gestion de boutique - Frais de livraison";
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
      
      $x->createField('name',                          //ord obl que bro tra mul pub tar
		      'Nom','\Seolan\Field\ShortText\ShortText',          '30','1','1','1','1','0','0','1');
      $x->createField('wtab',                          //ord obl que bro tra mul pub tar
		      'Table par poids','\Seolan\Field\Table\Table',    '20','2','1','1','1','0','0','0');
      $x->createField('utab',                         //ord obl que bro tra mul pub tar
		      'Table par unite','\Seolan\Field\Table\Table',   '20','3','1','1','1','0','0','0');
      $x->createField('tva',                         //ord obl que bro tra mul pub tar
		      'Code TVA','\Seolan\Field\Link\Link',              '','4','1','1','1','0','0','0', $tvatable);
      $x->createField('code',                           //ord obl que bro tra mul pub tar
		      'Code Interne','\Seolan\Field\ShortText\ShortText', '20','5','1','1','1','0','0','0');
    }
    $this->_module->orderlinestable=$orderlinestable;
    $this->_module->deliverytable='DELIV';
  }
  function istep2() {
    if($this->_module->createstructure) {
      $this->createStructure();
    } else {
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','table'), "table", "table");
    }
  }
  function istep3() {
      $this->_options->setOpt("Table des produits", "productstable", "table");
  }
  function iend($ar=NULL) {
    return parent::iend();
  }
}

?>
