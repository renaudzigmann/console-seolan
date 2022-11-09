<?php
namespace Seolan\Field\Real;

/// Champ permettant le stockage de valeurs numeriques
class Real extends \Seolan\Core\Field\Field {
  public $separator=',';
  public $display_format='';
  public $default='0.00';
  public $edit_format='^([+-]*[0-9]+[\.]{0,1}[0-9]{0,2})$';
  public $edit_format_text='';
  public $boxsize='10';
  public $decimal='2';
  public $alignright=true;
  public $quicksearchboxsize='';
  public $query_formats=array('classic','input','listbox-one','listboxwithop');
  public $display_sum=false;
  public $replacezerowith='';
  protected static $boxmaxsize=10;
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  function __construct($obj=NULL) {
    parent::__construct($obj) ;
  }
  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','separator'), 'separator', 'text', array('size'=>1),',');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','display_format'), 'display_format', 'text',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'edit_format_text'),'edit_format_text','ttext',array(), NULL);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','edit_format'), 'edit_format', 'text',array(), $this->edit_format);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','quicksearchboxsize'), 'quicksearchboxsize', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','boxsize'), 'boxsize', 'text',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','default'), 'default', 'text',array(), $this->default);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','display-sum'), 'display_sum', 'boolean',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','decimals'), 'decimal', 'text', NULL, $this->decimal);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','alignright'), 'alignright', 'boolean', NULL, $this->alignright);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','replacezerowith'), 'replacezerowith', 'text', NULL, $this->replacezerowith);
    $this->_options->setComment(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','replacezerowith_comment'), 'replacezerowith');
    $this->_options->setComment(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','edit_format_comment'), 'edit_format');
    $this->_options->setComment(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','xrealdef_display_format_comment'), 'display_format');
  }
  // Contenu d'une cellule pour feuille de calcul
  function getSpreadSheetCellValue($value, $options=null){
    if (is_a($value, \Seolan\Core\Field\Value::class)){
      $v=$value->raw;
    } elseif (!$this->isEmpty($value)){
      $v = $value;
    } else {
      $v = '';
    }
    return $v;
  }
  /// Ecriture dans un fichier excel (PHPExcel)
  function writeXLS($xl,$i,$j,$value,$format=0,$ss=null) {
    $xl->setCellValueByColumnAndRow($j,$i,$value->raw);
    if(is_array($format)) $xl->getStyleByColumnAndRow($j,$i)->applyFromArray($format);
  }

  // Ecriture dans un csv
  function writeCSV($o,$textsep){
    return $o->toText();
  }

  /// affichage d'une valeur numerique
  function my_display_deferred(&$r){
    if ($r->raw == 0 && !empty($this->replacezerowith)) {
      $r->html = $this->replacezerowith;
    } else {
      $r->html = empty($this->display_format) 
               ? number_format($r->raw, $this->decimal, \Seolan\Core\Lang::getLocaleProp('decimal_point'), \Seolan\Core\Lang::getLocaleProp('thousands_sep'))
               : sprintf($this->display_format, $r->raw);
    }
    return $r;
  }

  function my_getJSon($o, $options) {
    return round($o->raw, $this->decimal);
  }

  function my_browse_deferred(&$r){
    $r=parent::my_browse_deferred($r);
    if(is_object($r) && $this->alignright) $r->html='<div align="right">'.$r->html.'</div>';
    return $r;
  }

  function my_export($value) {
    return '<![CDATA['.$value."]]>\n";
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $lang = \Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options);
    $opt = '';
    $name=$this->field;
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
    if(empty($this->boxsize)) $this->boxsize=static::$boxmaxsize;
    $size = min($this->fcount,$this->boxsize);
    $maxlength=$this->fcount;
    if(isset($options['size'])) $size=$options['size'];
    if(isset($options['maxlength'])) $maxlength=$options['maxlength'];
    $t=sprintf('%.'.$this->decimal.'f',$value);
    if($t=='') $t=$this->default;
    $r->raw = $value;
    $varid=uniqid('v');
    $r->varid=$varid;
    $fmt1=$this->edit_format;
    $fmt='';
    if(!empty($fmt1) || $this->compulsory) {
      $color = \Seolan\Core\Ini::get('error_color');
      $t1="TZR.isIdValid('$varid');";
      $fmt=" onblur=\"if(typeof(TZR)!='undefined') { $t1 }\" ";
      $fmt.=static::getHtmlPattern($this);
      $t1='';
      if(!empty($fmt1)) 
	$t1="TZR.addValidator(['$varid',/$fmt1/,'".addslashes($this->label)."','$color','\Seolan\Field\Real\Real']);";
      if($this->compulsory && ($fmt1!='(.+)'))
	$t1.="TZR.addValidator(['$varid',/(.+)/,'".addslashes($this->label)."','$color','\Seolan\Field\Real\Real']);";
      $js="<script type=\"text/javascript\">if(typeof(TZR)!='undefined') { $t1 }</script>";
    }
    if (isset($options['tabindex'])) $fmt.= ' tabindex="'.$options['tabindex'].'" ';
    
    $class = '';
    if ($this->compulsory)
      $class = "tzr-input-compulsory";
    if (@$this->error)
      $class .= " error_field";
    if ($class)
      $class = " class=\"$class\"";
      
    $r->html = "<input $class name=\"$fname\" type=\"text\" size=\"$size\" maxlength=\"$maxlength\" value=\"$t\" ".
	" id=\"$varid\" style=\"text-align:right;\" $fmt/>$opt$js";
    return $r;
  }

  function my_quickquery($value,$options=NULL) {
    $p=new \Seolan\Core\Param($options);
    $lang=\Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options);
    if(is_array($value)) $value=implode($this->separator,$value);
    if(isset($value)) $t1 = htmlspecialchars($value);
    else $t1='';
    $name=$this->field;
    $op=!empty($this->op)?$this->op:$p->get($name.'_op');
    if (empty($op) && $this->isFilterCompulsory($options) && !empty(@$options['fields_complement']['query_comp_field_op'])) {
      $op = @$options['fields_complement']['query_comp_field_op'];
    }
    $js=' onchange="document.quicksearch.submit();" ';
    $t='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    $t.='<select name="'.$name.'_op">';
    $t.='<option value="">-</option>';
    $t.='<option value=">"'.($op=='>'?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','more_than').'</option>';
    $t.='<option value=">="'.($op=='>='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','upper_than').'</option>';
    $t.='<option value="="'.($op=='='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','equal_to').'</option>';
    $t.='<option value="<"'.($op=='<'?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','less_than').'</option>';
    $t.='<option value="<="'.($op=='<='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','lower_than').'</option>';
    $t.='</select>';
    $boxsize=min($this->fcount, static::$boxmaxsize);
    //Dans le cas d'un champ Rating, fcount est à 0
    if ($this->fcount == 0) {
      $this->fcount = 10;
    }
    if(!empty($this->quicksearchboxsize)) $boxsize=$this->quicksearchboxsize;
    $t.= "<input type=\"text\" ".($this->isFilterCompulsory($options) ? 'required' : '')." name=\"$name\" size=\"$boxsize\" maxlength=\"{$this->fcount}\" value=\"$t1\" $js/>";
    $r->html=$t;
    $r->raw=$value;
    return $r;
  }
  function my_query($value,$options=NULL) {
    $p=new \Seolan\Core\Param($options);
    $format=$p->get('fmt');
    if(empty($format)) $format=$p->get('qfmt');
    if(empty($format)) $format=$this->query_format;
    $labelin=$p->get('labelin');
    $lang=\Seolan\Core\Shell::getLangUser();
    $selectquery=$p->get('select','norequest');
    $r = $this->_newXFieldVal($options);
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $op=!empty($this->op)?$this->op:$p->get($fname.'_op');
    if(empty($op)) $op=$p->get('op');
    if(is_array($value)) $value=implode($this->separator,$value);
    if(isset($value)) $t1=$value;
    else $t1='';
    $r->collection = array();
    $size=min($this->fcount, static::$boxmaxsize);
    if($format=='classic'){
      $t='<select name="'.$fname.'_op">';
      $t.='<option value="">---</option>';
      $t.='<option value=">"'.($op=='>'?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','more_than').'</option>';
      $t.='<option value=">="'.($op=='>='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','upper_than').'</option>';
      $t.='<option value="="'.($op=='='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','equal_to').'</option>';
      $t.='<option value="<"'.($op=='<'?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','less_than').'</option>';
      $t.='<option value="<="'.($op=='<='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','lower_than').'</option>';
      $t.='</select>';
      $t.= '<input type="text" id="'.$fname.'" name="'.$fname.'" size="'.$size.'" value="'.$t1.'" maxlength="'.$this->fcount.'">';
    }elseif($format=='listbox-one' || $format=='listboxwithop'){
      $lower=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','lower_than');
      $upper=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','upper_than');
      $t='';
      if(!empty($selectquery)) $allvalues=$this->_getUsedValues(NULL,$selectquery);
      else $allvalues= $this->_getUsedValues("LANG='$lang'",NULL);
      ksort ($allvalues);
      foreach($allvalues as $v=>$foo){
        $r->collection[] = $v;
	$t.='<option value="'.$v.'">'.$v.'</option>';
	if($v==$t1) $first='<option value="'.$v.'" selected>'.$this->label.' : '.$v.'</option>';
      }

      if($format=='listboxwithop'){
	foreach($allvalues as $v=>$foo){
	  $t.='<option value="<='.$v.'">'.$lower.' '.$v.'</option>';
	  if(('<='.$v)==$t1) $first='<option value="<='.$v.'" selected>'.$this->label.' : '.$lower.' '.$v.'</option>';
	}
	foreach($allvalues as $v=>$foo){
	  $t.='<option value=">='.$v.'">'.$upper.' '.$v.'</option>';
	  if(('>='.$v)==$t1) $first='<option value=">='.$v.'" selected>'.$this->label.' : '.$upper.' '.$v.'</option>';
	}
      }
      if(empty($labelin)) $first='<option value="">----</option>';
      elseif(empty($first)) $first='<option value="" selected>'.$this->label.'</option>';
      else $first.='<option value="">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','n/a').'</option>';

      $t='<select id="'.$fname.'" name="'.$fname.'">'.$first.$t.'</select>';
      if($format=='listbox-one') $t.='<input type="hidden" name="'.$fname.'_op" value="=">';
    }elseif($format=='input'){
      $t.='<input type="text" id="'.$fname.'" name="'.$fname.'" size="'.$size.'" value="'.$t1.'" maxlength="'.$this->fcount.'">';
      if(!empty($labelin)){
	$t.='<script type="text/javascript">inputInit("'.$fname.'","'.$this->label.'");</script>';
      }
    }

    $r->html=$t;
    $r->raw=$value;
    return $r;
  }
  function is_summable() {
    return $this->display_sum;
  }
  function sqltype() {
    return 'double';
  }
  function convert($value, $src, $dst) {
    $v1=explode($src,$value);
    $value=implode($dst,$v1);
    return $value;
  }
  function post_query($o,$options=NULL) {
    if(empty($o->value) && $o->value!=="0") return;
    $o->quote='';
    if(strpos($o->value,'>=')===0){
      $o->op='>=';
      $o->value=substr($o->value,2);
    }elseif(strpos($o->value,'<=')===0){
      $o->op='<=';
      $o->value=substr($o->value,2);
    }elseif(strpos($o->value,'<')===0){
      $o->op='<';
      $o->value=substr($o->value,1);
    }elseif(strpos($o->value,'>')===0){
      $o->op='>';
      $o->value=substr($o->value,1);
    }elseif(empty($o->op)){
      $o->op = '=';
    }
    // traitement les caractères locaux : milliers et décimales et non numériques restants
    $locale = \Seolan\Core\Lang::getLocale(\Seolan\Core\Shell::getLangUser());
    if (strpos($o->value, $locale['decimal_point'])!==false){
      $o->value = str_replace($locale['decimal_point'],'.', $o->value);
    }
    if (strpos($o->value, $locale['thousands_sep'])!==false){
      $o->value = str_replace($locale['thousands_sep'],'', $o->value);
    }
    $o->value = preg_replace('/[^0-9.]/', '', $o->value);

    parent::post_query($o, $options);
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    if(!empty($this->exif_source) && empty($value)){
      $value=$this->getMetaValue($fields_complement);
      $value=$value->raw;
    }
    if($value==='' || $value===NULL){
      $r = $this->_newXFieldVal($options);
      $r->forcenull=true;
      $r->raw='';
      $this->trace($options['old'],$r);
      return $r;
    }
    return parent::post_edit($value,$options,$fields_complement);
  }

  /**
   * Retourne la valeur par défaut du champ
   * @return mixed Valeur par défaut du champ
   */
  public function getDefaultValue() {
    return (float)parent::getDefaultValue();
  }
  public function isEmpty($r){
    return parent::isEmpty($r) && ((int)$r->raw !== 0);
  }
}
