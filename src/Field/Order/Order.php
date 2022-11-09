<?php
namespace Seolan\Field\Order;
/// Champ permettant la gestion d'un ordre
class Order extends \Seolan\Core\Field\Field {
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  public $default=0; 

  function __construct($obj=NULL) {
    parent::__construct($obj) ;
  }
  function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('default');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','default'),
			    'default',
			    'integer', 
			    array('compulsory'=>1), 
			    0);
  }
  function my_display(&$value,&$options,$genid=false) {
    $r=parent::my_display($value,$options,$genid);
    $r->html=$value;
    return $r;
  }
  function my_browse(&$value,&$options,$genid=false) {
    $r=parent::my_browse($value,$options,$genid);
    $r->html=$value;
    return $r;
  }
  function my_export($value) {
    return $value;
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $lang = \Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options);
    $opt = '';
    $name=$this->field;
    if(isset($options['intable'])) {
      $o = $options['intable'];
      $fname=$this->field.'['.$o.']';
      $hiddenname=$this->field.'_HID['.$o.']';
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field.'_HID';
    }	
    $size = 4;
    $maxlength=4;
    $t=$value;
    $r->raw = $value;
    $varid=uniqid('v');
    $fmt1=$this->edit_format;
    $fmt='';
    $js='';
    if(!empty($fmt1)) {
      $color = \Seolan\Core\Ini::get('error_color');
      $fmt=" onblur=\"if(typeof(TZR)!='undefined') {TZR.isShortTextValid('$varid',/".$this->edit_format."/,'".$this->label."','$color');}\" ";
      $js="<script type=\"text/javascript\">if(typeof(TZR)!='undefined') {TZR.addValidator(['$varid',/".$this->edit_format."/,'".$this->label."','$color','XShortText']);}</script>";
    }
    $r->html = "<input name=\"$fname\" type=\"text\" maxlength=\"$maxlength\" value=\"$t\" ".
      " id=\"$varid\" $fmt/>$opt$js";
    $r->varid=$varid;
    return $r;
  }
  function my_query($value,$options=NULL) {
    $lang=\Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options);
    if(isset($value)) $t1 = htmlspecialchars($value);
    else $t1='';
    //$opt = $this->_getSelectBox(true);
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $t='<select name=\"'.$fname.'_op">';
    $t.='<option value="">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query_containing').'</option>';
    $t.='<option value="$">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query_ending_with').'</option>';
    $t.='<option value="^">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query_beginning_with').'</option>';
    $t.='<option value=">=">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','upper_than').'</option>';
    $t.='<option value="<=">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','lower_than').'</option>';
    if($options['genempty']!=='false')
      $t.='<option value="is empty">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','is_empty').'</option>';
    $t.='</select>';
    $t.= "<input type=\"text\" name=\"$fname\" maxlength=\"4\" value=\"$t1\"/>$opt";
    $r->html=$t;
    return $r;
  }
  function sqltype() {
    return 'int(11)'; // soit int ?
  }
  function getDefaultValue() {
    return is_int($this->default)?$this->default:'0';
  }
}
?>
