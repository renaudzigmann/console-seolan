<?php
namespace Seolan\Module\Cart;
/// Gestion d'une boutique et d'un caddie 
class Cart extends \Seolan\Module\Table\Table {
  public $backofficeemail = TZR_DEBUG_ADDRESS;
  public $deliverypolicy = 'deliv';
  public $deliverytable = 'DELIV';
  public $deliverytype = 'simp';
  public $deliveryweight = 'mw';
  public $discfield = 'disc';
  public $acompte;
  public $labelfield = 'F0002';
  public $orderdatefield = 'F0002';
  public $orderlinestable = 'T011';
  public $orderreffield = 'F0001';
  public $productstable = 'T007';
  public $paidfield = 'paid';
  public $alreadypaidfield = 'apaid';
  public $pricefield='prixht';
  public $proddeliverytax = 'deliv';
  public $promofield = 'promo';
  public $referencefield = 'F0001';
  public $tvafield = 'F0007';
  public $userdiscount = 'reduc';
  public $edeliv = 'EDELIV';
  public $edelivdelay = 7;
  public $edelivfield = 'ebook';
  public $coupontable=NULL;
  public $sender='info@xsalto.com';
  public $sendername='info@xsalto.com';

  function __construct($ar=NULL){
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Cart_Cart');
  }

  // initialisation des propriétés
  //
  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','modulename');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','productstable'), 'productstable', 'table', 
			    array('validate'=>true),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','table'), 'table', 'table', 
			    array('validate'=>true),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','orderlinestable'), 'orderlinestable', 'table', 
			    array('validate'=>true),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','deliverytable'), 'deliverytable', 'table', 
			    array('validate'=>true),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','coupontable'), 'coupontable','table',array('emptyok'=>false),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','backofficeemail'), 'backofficeemail', 'text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','acompte'), 'acompte', 'text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','productlabel'), 'labelfield', 
			    'field', array('table'=>'productstable'),NULL,$alabel);
    $this->_options->setOpt('Reference','referencefield','field',array('table'=>'productstable'),NULL,$alabel);
    $this->_options->setOpt('Prix','pricefield','field',array('table'=>'productstable'),NULL,$alabel);
    $this->_options->setOpt('Champ TVA','tvafield','field',array('compulsory'=>false,'table'=>'productstable'),NULL,$alabel);
    $this->_options->setOpt('Promo','promofield','field',array('compulsory'=>false,'table'=>'productstable','compulsory'=>false),NULL,$alabel);
    $this->_options->setOpt('Poids','deliveryweight','field',array('compulsory'=>false,'table'=>'productstable'),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','sender'),'sender','text',NULL,'info@xsalto.com',$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','sendername'),'sendername','text',NULL,'XSALTO',$alabel);
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
    if(isset($g[$function])) return $g[$function];
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Retourne les infos de l'action voir du browse
  function browseActionViewUrl($usersel, $linecontext=null){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=viewOrder&template=Module/Cart.print.html';
  }
  function browseActionViewHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
    return 'target="out"';
  }

  function sendOrder($ar=NULL) {
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

  function shop1($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $ar[selectedfields]=array($this->labelfield, $this->labelfield2, $this->labelfield3, $this->pricefield);
    $ar[order]=$this->$orderfield;
    $ar[pagesize]='20';
    $ar[selected]='0';
    $ar[tplentry]='';
    $this->xset->browse($ar);
    global $XSHELL;
    $XSHELL->tpldata[$tplentry][lines_labelfield]=$XSHELL->tpldata[$tplentry]['lines_'.$this->labelfield];
    $XSHELL->tpldata[$tplentry][lines_labelfield2]=$XSHELL->tpldata[$tplentry]['lines_'.$this->labelfield2];	
    $XSHELL->tpldata[$tplentry][lines_labelfield3]=$XSHELL->tpldata[$tplentry]['lines_'.$this->labelfield3];	
    $XSHELL->tpldata[$tplentry][lines_pricefield]=$XSHELL->tpldata[$tplentry]['lines_'.$this->pricefield];
    $XSHELL->tpldata[$tplentry][detail]=$this->detail;
    $ar[tplentry]=cart;
    $this->view($ar);
  }

  function _computeProdDelivery($prod, $deliv) {
    $delivery = $prod['odeliv']->raw;
    return $delivery;
  }
  function _computeOrderDelivery($nb, $deliv, $total_weight=0) {
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
    $coupon = getSessionVar('coupon');
    $freedeliv = 0;
    if(!empty($this->coupontable)) {
      $result['coupon_active']=true;
      $ct = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->coupontable);
      $q = $ct->select_query(array("cond"=>array("Code"=>array("=",$coupon),
                                                 "datet"=>array(">=",date('Y-m-d')),
                                                 "datef"=>array("<=",date('Y-m-d')))));
      $rs = getDB()->select($q);
      if ($ors = $rs->fetch()) {
        $coupoid = $ors['KOID'];
        $rcoupon = $ct->display(array("oid"=>$coupoid,"tplentry"=>TZR_RETURN_DATA));
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
          $result['coupon_message']=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','coupon_order_too_low');
          $result['total_coupon']=0;
          if ($rcoupon['cadeau']->raw == 1) $result['coupon_cadeau'] = 2;
        }
        if ($freedeliv == 1) $total_delivery = 0;
      } elseif (!empty($coupon)) {
        $result['coupon_message'] = 'Ce coupon n\'existe pas ou est périmé';
      }
    }
  }

  /**
   * Détermine la validité d'un coupon en fonction de l'utilisateur et de l'unicité du coupon
   * @todo Uniformiser les noms des champs indiquant le mode et l'état du paiement (XModMonetique ?)
   */
  function isCouponValid(&$coupon, &$user) {
    // Le coupon est invalide s'il est nominatif et qu'il n'appartient pas à l'utilisateur courant
    if (!empty($coupon['oUNIQUSER']->html) && $coupon['oUNIQUSER']->raw != $user->uid()) return false;
    // Le coupon est valide s'il n'est pas à usage unique
    if ($coupon['ouniqusage']->raw != 1) return true;
    // Récupération du type et de l'état du paiement de toutes les commande utilisant ce coupon
    $br = $this->xset->browse(array(
      '_options' => array('local'=>true),
      'nocount' => true,
      'first' => 0,
      'pagesize' => 9999,
      'selectedfields' => array('tpaid','paid'),
      'cond' => array(
        'LIENCOUPON' => array('=',$coupon['oid']))
    ));
    foreach ($br['lines_oid'] as $i => $oid) {
      // Le coupon à usage unique est invalide si :
      //  - une commande a été gratuite grâce à ce coupon
      //  - un paiement par chèque est en attente ou accepté (soit non refusé)
      //  - un paiement bancaire est accepté
      if (($br['lines_otpaid'][$i]->raw == 'ZERO')
       || ($br['lines_otpaid'][$i]->raw == 'CHQ' && $br['lines_opaid'][$i]->raw != 'Autorisation refusee')
       || ($br['lines_otpaid'][$i]->raw != 'CHQ' && $br['lines_opaid'][$i]->raw == 'Autorisation acceptee')) return false;
    }
    return true;
  }
  
  function modifyCart($ar) {
    $p=new \Seolan\Core\Param($ar, array('tplentry'=>'cart'));
    $charset=\Seolan\Core\Lang::getCharset();
    $deloid=$p->get('deloid');
    $qty=$p->get('qty');
    // suppression d'un item dans le caddie
    if(isset($qty)) {
      $cart=getSessionVar('cart');
      foreach($qty as $i => $var1) {
	foreach($var1 as $j => $q) {
 	  if($charset!=TZR_INTERNAL_CHARSET) convert_charset($j,$charset,TZR_INTERNAL_CHARSET);
 	  if(preg_match('@^([0-9]+)$@',$q)) $cart[$i][stripslashes($j)]=$q;
	}
      }
    }
    if(!empty($deloid)) {
      foreach($deloid as $i => $var1) {
	if(is_array($var1)) {
	  foreach($var1 as $j => $q) {
	    if($charset!=TZR_INTERNAL_CHARSET) convert_charset($j,$charset,TZR_INTERNAL_CHARSET);
	    unset($cart[$i][stripslashes($j)]);
	  }
	} else {
	  unset($cart[$i]);
	}
      }
    }
    $coupon=$p->get("coupon");
    setSessionVar("cart",$cart);
    setSessionVar("coupon",$coupon);
  }

  function view($ar=NULL) {
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

    // recherche d'un coupon
    $coupon=getSessionVar('coupon');
    if(!empty($this->coupontable)) {
      $result['coupon_active']=true;
      $ct = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->coupontable);
      $q = $ct->select_query(array("cond"=>array("Code"=>array("=",$coupon),
						 "datet"=>array(">=",date('Y-m-d')),
						 "datef"=>array("<=",date('Y-m-d')))));
      $rs=getDB()->select($q);
      if($ors=$rs->fetch()) {
	$coupoid = $ors['KOID'];
	$rcoupon = $ct->display(array("oid"=>$coupoid,"tplentry"=>TZR_RETURN_DATA));
	$sommemin = $rcoupon["omina"]->raw;
	$sommedisc = $rcoupon['odisc']->raw;
	if($total_amount>=$sommemin) {
	  $result['coupon']=$coupon;
	  $result['coupon_message']=$rcoupon['omessage']->raw;
	  $result['total_coupon'] = $sommedisc + ($total_amount+$total_tva)*$rcoupon["operc"]->raw/100;
	  $total_remise += $result['total_coupon'];
	  //	  $total_remise += ($total_amount+$total_tva)*$rcoupon["operc"]->raw/100;
	} else {
	  $result['coupon_message']=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','coupon_order_too_low');
	  $result['coupon']=$coupon;
	  $result['total_coupon']=0;
	}
      }
    }

    // calcul des frais de livraison
    if($total_articles>0 && !$edelivonly) {
      // il n'y a des frais de livraison que si au moins un article
      $total_delivery+=$this->_computeOrderDelivery($total_articles, $rdeliv, $total_weight);
    }
    if($tva_is_applicable && !empty($rdeliv['tva'])) {
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
    $ar1['F0006']=$user->uid();
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
    \Seolan\Core\Logs::debug('\Seolan\Module\Cart\Cart::procOrder: order oid is '.$oid);
    // insertion des lignes de commande
    $li = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->orderlinestable);
    $lines=count($result['lines_referencefield']);
    for($i=0;$i<$lines;$i++) {
      $ar1=array();
      $ar1['F0001']=$oid;
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
      \Seolan\Core\Logs::debug('\Seolan\Module\Cart\Cart::procOrder: trying to line validate in order '.$oid
                   .' reference '.$result['lines_referencefield'][$i]);
      $ooid=$li->procInput($ar1);
      \Seolan\Core\Logs::debug('\Seolan\Module\Cart\Cart::procOrder: added '.var_export($ooid["oid"],true));

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
    // recuperation du contenu
    $url = $GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'&function=viewOrder&oid='.$oid.
      '&tplentry=br&nocache=1&template=Module/Cart.inmail.html&moid='.$this->_moid.'&class='.get_class($this);
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

  // calcul d'un id à partir de 
  //
  protected function _idx2txt($varoid) {
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
  //
  function addItem($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get("tplentry");
    $oid=$p->get("oid");
    $varoid=$p->get("varoid");
    $q=$p->get("q");
    if(empty($q)) $q=1;
    if(empty($varoid))$varoid="";
    $idx = $this->_varoid2Idx($varoid);
    if(!issetSessionVar('cmdref')) {
      $cmdref=$p->get('cmdref');
      if(empty($cmdref)) $cmdref=date('YmdHis');
      setSessionVar('cmdref',$cmdref);
    }
    $cart=getSessionVar("cart");
    if(empty($cart[$oid][$idx]))
      $cart[$oid][$idx]=$q;
    else
      $cart[$oid][$idx]+=$q;
    setSessionVar("cart",$cart);
  }

  function delItem($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get("tplentry");
    $oid=$p->get("oid");
    $cart=getSessionVar("cart");
    if(!empty($cart[$oid])) {
      unset($cart[$oid]);
      setSessionVar("cart",$cart);
    }
  }

  function emptyCart($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get("tplentry");
    clearSessionVar("cart");
    clearSessionVar("cmdref");
    clearSessionVar("coupon");
  }
  
  /// Enregistre un nouveau compte pour une commande
  function saveUser($ar=NULL) {
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
    if(!empty($oid) && $oid != TZR_USERID_NOBODY) {
      $user=$users->procEdit($ar);
    } else {
      if(!preg_match('/^([-_\.a-z0-9\+]+@[-_\.a-z0-9]+\.[a-z]{1,3})$/i',$email))  {
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
        $ar['DATEF'] = date('Y-m-d');
        $ar['DATET'] = date('Y-m-d', strtotime(date('Y-m-d').' +10 year'));
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
        $sess->procAuth(array("login"=>$alias, "password"=>$passwd));
      }
    }
    return $oid;
  }

  // visualisation d'un bon de commande dans l'admin
  function viewOrder($ar=NULL) {
    $p = new \Seolan\Core\Param($ar,array());
    $oid=$p->get("oid");
    $us = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='."USERS");
    $or = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->orderlinestable);
    $rot=$this->xset->display(array("oid"=>$oid,"tplentry"=>TZR_RETURN_DATA));
    $us->display(array("oid"=>$rot['oF0006']->raw,"tplentry"=>"cust"));
    $q=$or->select_query(array("cond"=>array("F0001"=>array("=",$oid))));
    $or->browse(array("tplentry"=>"ordl","selected"=>0,"select"=>$q,"selectedfields"=>"all"));
    return  \Seolan\Core\Shell::toScreen1('ord',$rot);
  }
  function paid($ar=NULL) {
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
    $p = new \Seolan\Core\Param($ar,array());
    $ot = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    $reference= $p->get("reference");
    $montant = $p->get("montant");
    $retour  = $p->get("retour");
    \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::ciccheck"," order $reference amount $montant");
    // verification que la commande existe
    if(isset($reference)) {
      $rs=getDB()->select("select * from ".$this->table." where ".$this->orderreffield." ='$reference'");
      if(!$ors=$rs->fetch()) {
	\Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::ciccheck"," order $reference amount $montant reference not found");
	die("Commande non existante");
      }
      $rs->closeCursor();
      $oid=$ors['KOID'];
      // verification qu'il y a bien un montant affiché
      if(!isset($montant)) {
	\Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::ciccheck"," order $reference amount $montant probleme dans les montants");
	die("Montant non renseigne");
      }
      $nmontant=printf("%.2f",$ors['F0004']);
      $cmontant=printf("%.2f",str_replace('EUR','',$montant));
      if($cmontant != $nmontant) {
	\Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::ciccheck"," order $reference amount $montant probleme dans les montants (2)");
	die("Montant non exact");
      }
      $val=$retour;
      $ot->procEdit(array($this->paidfield=>$val,"oid"=>$oid));
      \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::ciccheck"," order $reference oid $oid amount $montant status $val");
      $this->_sendOrderEmail($oid,$this->backofficeemail);	
    }
    die("cicheckok");
  }

  // verification du paiement CA, etransactions, solution de paiement du CA
  // VERSIOn API 500
  // ATOS origine - CA, BPOP, SOciete Gen 
  //
  function cacheck($ar=NULL){
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
      \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::cacheck"," executable response non trouve $path_bin");
    } else if ( $code != 0 ){
      \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::cacheck"," API call error.");
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
	  \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::cacheck"," order $reference amount $montant reference not found");
	  die("Commande non existante");
	}
	$rs->closeCursor();
	$oid=$ors['KOID'];
	// verification qu'il y a bien un montant affiché
	if(empty($montant)) {
	  \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::cacheck"," order $reference amount $montant probleme dans les montants");
	  die("Montant non renseigne");
	}
	if(!empty($this->acompte) && !empty($this->alreadypaidfield)) {
	  $paidfield=$this->alreadypaidfield;
	  $nmontant =  $ors[$paidfield];
	} else {
	  $nmontant=sprintf("%.2f",$ors['F0004']);	  
	}
	if($montant != $nmontant) {
	  \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::cacheck"," order $reference amount $montant<>$nmontant probleme dans les montants (2)");
	  die("Montant non exact");
	}
	$etat=$response_code;
	$val=$ret[$etat];
	$ot->procEdit(array($this->paidfield=>$val,"oid"=>$oid));
	\Seolan\Core\Logs::notice("cart","order $reference oid $oid amount $montant status $val");
	$label = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','paimentreturn');
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
      \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::wacheck"," executable response non trouve $path_bin");
    } else if ( $code != 0 ){
      \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::wacheck"," API call error.");
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
	  \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::wacheck"," order $reference amount $montant reference not found");
	  die("Commande non existante");
	}
	$rs->closeCursor();
	$oid=$ors['KOID'];
	// verification qu'il y a bien un montant affiché
	if(empty($montant)) {
	  \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::wacheck"," order $reference amount $montant probleme dans les montants");
	  die("Montant non renseigne");
	}
	if(!empty($this->acompte) && !empty($this->alreadypaidfield)) {
	  $paidfield=$this->alreadypaidfield;
	  $nmontant =  $ors[$paidfield];
	} else {
	  $nmontant=sprintf("%.2f",$ors['F0004']);	  
	}
	if($montant != $nmontant) {
	  \Seolan\Core\Logs::critical("\Seolan\Module\Cart\Cart::wacheck"," order $reference amount $montant<>$nmontant probleme dans les montants (2)");
	  die("Montant non exact");
	}
	$etat=$response_code;
	$val=$ret[$etat];
	$ot->procEdit(array($this->paidfield=>$val,"oid"=>$oid));
	\Seolan\Core\Logs::notice("cart","order $reference oid $oid amount $montant status $val");
	$label = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','paimentreturn');
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
      $label = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','paimentreturn');
      $this->_sendOrderEmail($oid,$this->backofficeemail,$label);
      if ($etat == '1' ){
	// envoi d'un mail de confirmation au client	
	$tableusers = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ors['F0006']);
	$displayuser = $tableusers->display(array('oid'=>$ors['F0006'], 'tplentry'=>'*return*'));
	$label = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','yourorder');
	$this->_sendOrderEmail($oid,$displayuser['oemail']->raw, $label);
      }
    }
    die("spcheckok");
  }

  function paybox($ar=NULL) {
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
	$tableusers = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ors['F0006']);
	$displayuser = $tableusers->display(array('oid'=>$ors['F0006'], 'tplentry'=>TZR_RETURN_DATA));
	$label = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','yourorder');
	$this->_sendOrderEmail($oid,$displayuser['oemail']->raw,$label);
	// envoi d'un mail au proprio de la boutique
	$label = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Cart_Cart','paimentreturn');
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
    
    $q=$or->select_query(array("cond"=>array("F0001"=>array("=",$parentoid))));
    $rl=$or->browse(array("tplentry"=>TZR_RETURN_DATA,"selected"=>0,"select"=>$q,"selectedfields"=>"all"));
    foreach($rl['lines_oid'] as $k=>$loid){
      $or->del(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$loid,'_selectedok'=>'','_selected'=>''));    
    }
  }

  function edeliv($ar) {
    $p = new \Seolan\Core\Param($ar,array());
    $edeliv = $p->get('edeliv');
    $eorder = $p->get('eorder');
    $byemail = $p->get('byemail');
    $now = date("Y-m-d");
    $edelivtable = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->edeliv);
    $edelivdisplay = $edelivtable->display(array('oid'=>$edeliv,'tplentry'=>'*return*'));
    if($now >=  $edelivdisplay['oDATET']->raw) {
      \Seolan\Core\Shell::toScreen2('','message','Date de livraison dépassée');
      return;
    }

    if($eorder == $edelivdisplay['oO1']->raw) {
      // verification que la commande est dans un etat validé
      $ordertable = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$edelivdisplay['oO1']->raw);
      $orderdisplay  = $ordertable->display(array("oid"=>$edelivdisplay['oO1']->raw,"tplentry"=>"*return*"));

      // 
      if(($orderdisplay['o'.$this->paidfield]->raw=='Paiement autorisé') && !empty($edelivdisplay['oEPROD']->raw)) {
	$productstable = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$edelivdisplay['oEPROD']->raw);
	$product =$productstable->display(array('oid'=>$edelivdisplay['oEPROD']->raw,'tplentry'=>TZR_RETURN_DATA));
	$filename=$product['o'.$this->edelivfield]->filename;
	$originalname=$product['o'.$this->edelivfield]->originalname;
	if(empty($byemail)) {
	  $ecnt=$edelivdisplay['oecnt']->raw;
	  $ecnt+=1;
	  $edelivtable->procEdit(array("oid"=>$edeliv,'tplentry'=>'*return*','ecnt'=>$ecnt));
          header("Expires: 0");
          header("Cache-Control: private, post-check=0, pre-check=0");
	  header("Content-type: ".$product['o'.$this->edelivfield]->mime);
	  header("Content-disposition: attachment; filename=\"$originalname\"");
	  $size=filesize($filename);
	  header('Accept-Ranges: bytes');
	  header("Content-Length: $size");
	  @readfile($filename);
	  exit;
	} else {
	  // TODO!
	}
      }
    }
    \Seolan\Core\Shell::toScreen2('','message','Commande non valide ou non payée');
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
