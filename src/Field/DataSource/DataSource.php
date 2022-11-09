<?php
namespace Seolan\Field\DataSource;

$XBaseDefMSGs=NULL;

/// Gestion d'un champ base, c'est a dire Source de Donnees
class DataSource extends \Seolan\Core\Field\Field {
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  
  function __construct($obj=NULL) {
    parent::__construct($obj);
    $lang=\Seolan\Core\Shell::getLangData();
    if(!isset($GLOBALS['XBaseDefMSGs'][$lang])) {
      $rs=getDB()->fetchAll('select BASEBASE.BTAB,AMSG.* from BASEBASE,AMSG where AMSG.MOID=BASEBASE.BOID');
      foreach($rs as $ors) {
	$GLOBALS['XBaseDefMSGs'][$ors['BTAB']][$ors['MLANG']]=array('txt'=>$ors['MTXT'],'boid'=>$ors['MOID']);
      }
      unset($rs);
    }
  }
  
  function my_display_deferred(&$r) {
    $value=$r->raw;
    if(trim($value)=='') return;
    $lang=\Seolan\Core\Shell::getLangData();
    $r->html=$r->text=@$GLOBALS['XBaseDefMSGs'][$value][$lang]['txt'];
    $r->boid=@$GLOBALS['XBaseDefMSGs'][$value][$lang]['boid'];
  }

  /**
   * génération du html pour l'édition d'une valeur en cours
   */
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $lang=\Seolan\Core\Shell::getLangData();
    $lang_user=\Seolan\Core\Shell::getLangUser();
    if(isset($options['intable'])) {
      $o = $options['intable'];
      $fname=$this->field."[$o]";
      $hiddenname=$this->field."_HID[$o]";
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field.'_HID';
    }	

    $r=$this->_newXFieldVal($options);
    $r->raw=$value;
    $btab=$value;

    $r->oidcollection=$r->collection=array();
    $rs=getDB()->fetchAll('select BASEBASE.*,AMSG.* from BASEBASE,AMSG where BASEBASE.BOID=AMSG.MOID AND MLANG=? order by MTXT', [$lang]);
    $txt='<select name="'.$fname.'">';
    if(!$this->compulsory) $txt.='<option value="">---</option>';
    foreach($rs as $ors) {
      $txt.="<option value='{$ors['BTAB']}'".($btab==$ors['BTAB']?'selected':'').">{$ors['MTXT']}</option>";
      $r->oidcollection[]=$ors['BTAB'];
      $r->collection[]=$ors['MTXT'];
    }
    unset($rs);
    $txt.='</select>';
    $r->html = $txt;
    return $r;
  }
  function sqltype() {
    return 'varchar(40)';
  }
}
?>