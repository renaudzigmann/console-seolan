<?php
namespace Seolan\Field\Time;
// \Seolan\Core\Field\Field/\Seolan\Field\Time\Time
// NAME
// \Seolan\Field\Time\Time -- traitement des champs heure/durée
// DESCRIPTION
class Time extends \Seolan\Core\Field\Field {
  public $display_format='H:M:S';
  public $edit_format='H:M:S';
  public $query_formats=array('classic','noop');
  public $default='00:00:00';
  public $display_sum=false;
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  public $type='text';
  public $html5tag = true;
  function __construct($obj=NULL) {
    parent::__construct($obj) ;
  }

  function initOptions() {
    parent::initOptions();
    $dategroup = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', '\seolan\field\datetime\datetime');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','display_format'), 'display_format', 'text',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','edit_format'), 'edit_format', 'text',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','display-sum'), 'display_sum', 'boolean',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','type'),'type','text',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','usehtml5tag'), 'html5tag', 'boolean', null, null, $dategroup);
  }

  function my_display_deferred(&$r){
    if(!empty($r->raw)){
      list($hour,$minute,$second)=explode(':',$r->raw);
      $r->hour=(int)$hour;
      $r->minute=(int)$minute;
      $r->second=(int)$second;
      $r->html=$this->getFormatedString($this->display_format,array($hour,$minute,$second));
    }else{
      $r->hour=$r->minute=$r->second=0;
      $r->html='';
    }
    return $r;
  }

  function my_edit(&$value,&$options,&$fields_complement=NULL){
    $r=$this->_newXFieldVal($options);
    $name=$this->field;
    if(isset($options['intable'])) {
      $o=$options['intable'];
      $fname=$this->field."[$o]";
      $hiddenname=$this->field."_HID[$o]";
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field.'_HID';
    }	
    $varid=uniqid('v');
    if(!empty($value)){
      list($hour,$minute,$second)=explode(':',$value);
      $r->hour=(int)$hour;
      $r->minute=(int)$minute;
      $r->second=(int)$second;
      $disp=$this->getFormatedString($this->edit_format,array($hour,$minute,$second));
    }else{
      $r->hour=$r->minute=$r->second=0;
      $disp='';
    }
    $t=htmlspecialchars($disp);
    $r->raw=$value;
    $r->varid=$varid;

    $fmt1=$this->getFormatedString($this->edit_format,array('([0-1][0-9]|2[0-3])','[0-5][0-9](','[0-5][0-9])?'));
    $color=\Seolan\Core\Ini::get('error_color');
    if($this->html5tag) {
      $this->type="time";
      if (count(explode(':',$this->edit_format))==2){
	$step = '';
	$size='5';
      } else {
	$step = '1'; // force l'affichage et la saisie en seconde
	$size='8';
      }
      $attrs = " step=\"{$step}\" min=\"00:00\" max=\"24:00\""; 
      $attrs .= " size=\"{$size}\" onblur=\"TZR.isIdValid('{$r->varid}');\"";
    } else {
      $attrs = ' size="8" onblur="TZR.formatHour(this,'.count(explode(':',$this->edit_format)).'); TZR.isIdValid(\''.$varid.'\');"';
      if($this->compulsory)
	$t1='TZR.addValidator(["'.$varid.'",/^'.$fmt1.'$/,"'.addslashes($this->label).'","'.$color.'","\Seolan\Field\ShortText\ShortText"]);';
      else
	$t1='TZR.addValidator(["'.$varid.'",/^('.$fmt1.')$/,"'.addslashes($this->label).'","'.$color.'","\Seolan\Field\ShortText\ShortText"]);';
    }
    
    $js='<script type="text/javascript">'.$t1.'</script>';
    $class = '';
    if ($this->compulsory) {
      $class = 'tzr-input-compulsory';
      $attrs .= ' required ';
    }
    if (@$this->error)
      $class .= " {$color}";
    if ($class)
      $class = " class=\"{$class}\"";
    $parameters = '';
    $r->html = "<input name=\"{$fname}\" type=\"{$this->type}\" {$class} value=\"{$t}\" id=\"{$varid}\" {$attrs}/> {$js}";
    return $r;
  }

  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options);
    if(!empty($value)){
      if(is_numeric($value)) $value=$this->convertSecondToTime($value);
      $r->raw=$this->getSQLValueFromEditValue($value);
    }else{
      $r->raw=NULL;
      $r->forcenull=true;
    }
    $this->trace($options['old']??null,$r);
    return $r;
  }

  function my_query($value,$options=NULL) {
    $r=$this->_newXFieldVal($options);
    $p=new \Seolan\Core\Param($options);
    $format=$p->get('fmt');
    if(empty($format)) $format=$p->get('qfmt');
    if(empty($format)) $format=$this->query_format;
    if(!empty($value)){
      list($hour,$minute,$second)=explode(':',$value);
      $disp=$this->getFormatedString($this->display_format,array($hour,$minute,$second));
    }else{
      $disp='';
    }
    $t1=htmlspecialchars($disp);
    $name=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $cnt=count(explode(':',$this->edit_format));
    $js=' onchange="TZR.formatHour(this,'.$cnt.');" ';
    $t='';
    if($format=='classic'){
      $t='<select name="'.$name.'_op">';
      $t.='<option value="">-</option>';
      $t.='<option value=">=">>=</option>';
      $t.='<option value="=">=</option>';
      $t.='<option value="<="><=</option>';
      $t.='</select>';
    }
    $t.='<input '.($options['query_format'] === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' name="'.$name.'" size="'.($cnt*3).'" type="'.$this->type.'" value="'.$t1.'"'.$js.'/>';
    $r->html=$t;
    return $r;
  }

  function my_quickquery($value,$options=NULL) {
    $op=@$options['op'];
    
    if ($this->isFilterCompulsory($options) && empty($op) && !empty(@$options['fields_complement']['query_comp_field_op'])) {
      $op = @$options['fields_complement']['query_comp_field_op'];
    }
    
    $r=$this->_newXFieldVal($options);
    $cnt=count(explode(':',$this->edit_format));
    $r->html='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    $r->html.='<select name="'.$this->field.'_op">';
    $r->html.='<option value=""'.($op==''?' selected':'').'></option>';
    $r->html.='<option value="="'.($op=='='?' selected':'').'>=</option>';
    $r->html.='<option value=">="'.($op=='>='?' selected':'').'>>=</option>';
    $r->html.='<option value="<="'.($op=='<='?' selected':'').'><=</option>';
    $r->html.='</select>';
    $r->html.='<input '.($this->isFilterCompulsory($options) ? 'required' : '').' name="'.$this->field.'" size="'.($cnt*3).'" type="'.$this->type.'" value="'.$value.'" onchange="TZR.formatHour(this,'.$cnt.');">';
    return $r;
  }

  function post_query($o,$options=NULL) {
    if(empty($o->op)) return;
    $o->value=$this->getSQLValueFromEditValue($o->value);
    parent::post_query($o,$options);
  }

  function sqltype() {
    return 'time';
  }

  function is_summable() {
    return $this->display_sum;
  }

  function sqlsumfunction() {
    return 'SEC_TO_TIME(SUM(TIME_TO_SEC('.$this->field.')))';
  }

  function getSQLValueFromEditValue($value){
    $ar=array('H'=>'00','M'=>'00','S'=>'00');
    $fmt1=$this->getFormatedString($this->edit_format,array('(?<H>[0-1][0-9]|2[0-3])','(?<M>[0-5][0-9])','(?<S>[0-5][0-9])'));
    preg_match('/^'.$fmt1.'/',$value,$values);
    if (empty($values)) {
      return NULL;
    }
    foreach($ar as $i=>$f){
      if(isset($values[$i])) $ar[$i]=$values[$i];
    }
    return implode(':',$ar);
  }

  function convertSecondToTime($secondes){
     $tmp=$secondes%3600;
     $time=array();
     $time[0]=str_pad(($secondes-$tmp)/3600,2,'0',STR_PAD_LEFT);
     $time[2]=str_pad($tmp%60,2,'0',STR_PAD_LEFT) ;
     $time[1]=str_pad(($tmp-$time[2])/60,2,'0',STR_PAD_LEFT);
     ksort($time);
     return $this->getFormatedString($this->edit_format,$time);
  }

  function getFormatedString($format,$values){
    $ret=str_ireplace(array('%H','%M','%S'),$values,$format,$ok);
    if(!$ok) $ret=str_ireplace(array('H','M','S'),$values,$format);
    return $ret;
  }
}
?>
