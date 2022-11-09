<?php
namespace Seolan\Field\DateTime;
/// Gestion des champs date et heure
class DateTime extends \Seolan\Core\Field\Field {

  use \Seolan\Field\Date\DateTrait;
  
  public static $DATE_SEPARATORS = '[-: ]';
  public $display_format='H:M:S';
  public $edit_format='H:M:S';
  public $query_formats=array('classic','noop','nohour','nohournoop');
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  public $html5tag = true;
  function __construct($obj=NULL) {
    parent::__construct($obj) ;
  }
  function initOptions() {
    parent::initOptions();
    $dategroup = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', '\seolan\field\datetime\datetime');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','display_format'), 'display_format', 'text',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format'), 'edit_format', 'text',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','usehtml5tag'), 'html5tag', 'boolean', null, null, $dategroup);
  }
  function my_export($value) {
    return $this->dateFormat($value);
  }
  /// Sous fonction redefinie pour chaque type de champ pour l'import de données vers une table
  function my_import($value, $specs=null){
    if(empty($value)) $value=TZR_DATETIME_EMPTY;
    elseif(is_numeric($value) && $value<200000) $value=gmdate('Y-m-d H:i:s',($value-25569)*60*60*24);
    return array('message'=>'','value'=>$value);
  }

  function my_browse(&$value,&$options,$genid=false) {
    if(!empty($options['tz'])) $value=date('Y-m-d H:i:s',strtotime($value.' '.$options['tz']));
    return parent::my_browse($value,$options,$genid);
  }
  function my_display(&$value,&$options,$genid=false) {
    if(!empty($options['tz']))
      $value=date('Y-m-d H:i:s',strtotime($value.' '.$options['tz']));
    return parent::my_display($value,$options,$genid);
  }

  function my_display_deferred(&$r){
    if(empty($r->raw) || $r->raw==TZR_DATETIME_EMPTY)
      $r->html='';
    else 
      $this->formatDateTime($r);
    return $r;
  }
  /**
   * formatte une date heure, en tenant compte de la présence/absence des heures 
   */
  protected function formatDateTime($r){
    $datedef=new \Seolan\Field\Date\Date();
    $datedef->field=$this->field.'[date]';
    $timedef=new \Seolan\Field\Time\Time();
    $timedef->field=$this->field.'[hour]';
    $datedef->compulsory=$timedef->compulsory=$this->compulsory;
    $datedef->translatable=$timedef->translatable=$this->translatable;
    $timedef->display_format=$this->display_format;
    $timedef->edit_format=$this->edit_format;
    $d=substr($r->raw,0,10);
    $h=substr($r->raw,11);
    $r->date=$datedef->my_display($d, $r->options);
    if (!empty($h)){
      $r->hour=!empty(trim($h))?$timedef->my_display($h, $r->options):null;
      $r->html=$r->date->html.' '.$r->hour->html;
    } else {
      $r->hour = null;
      $r->html=$r->date->html;
    }
  }

  /// Rend la valeur par defaut du champ au format Y-m-d H:i:s
  function getDefaultValue() {
    if(!empty($this->default)
       && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/',$this->default)
       && static::dateIsValid($this->default, 'Y-m-d H:i:s'))
    return $this->default;

    if($this->compulsory) return date('Y-m-d H:i:s');
    else return TZR_DATETIME_EMPTY;
  }
  function dateFormat($date) {
    $lang = \Seolan\Core\Shell::getLangData();
    $fmt=\Seolan\Core\Lang::$locales[$lang]['date_format'];
    $fmted = date($fmt,\Seolan\Field\DateTime\DateTime::dateToTimestamp($date));
    return $fmted;
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
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
    if(isset($options['tz'])) $value=date('Y-m-d H:i:s',strtotime($value.' '.@$options['tz']));
    $r=$this->_newXFieldVal($options);
    $o=(object)array('FIELD'=>$fname.'[date]','COMPULSORY'=>$this->compulsory,'TRANSLATABLE'=>$this->translatable);
    $datedef=new \Seolan\Field\Date\Date($o);
    $datedef->inputdate=$this->html5tag;
    $v=substr($value,0,10);
    if (isset($options['datemax']))
      $datedef->datemax = $options['datemax'];
    if (isset($options['datemin']))
      $datedef->datemin = $options['datemin'];
    $date=$datedef->edit($v,$opt);
    $o=(object)array('FIELD'=>$fname.'[hour]','COMPULSORY'=>$this->compulsory,'TRANSLATABLE'=>$this->translatable);
    $hourdef=new \Seolan\Field\Time\Time($o);
    $hourdef->html5tag=$this->html5tag;
    $hourdef->display_format=$this->display_format;
    $hourdef->edit_format=$this->edit_format;
    if($value==TZR_DATETIME_EMPTY) $v='';
    else $v=substr($value,11);
    $optionsfoo=array();
    $hour=$hourdef->edit($v,$optionsfoo);
    $html=$date->html.' '.$hour->html;
    $r->date=$date;
    $r->hour=$hour;
    $r->raw=$value;
    $r->html=$html;
    return $r;
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options);
    if(!empty($this->exif_source) && (empty($value) || is_array($value) && empty($value['date']) && empty($value['hour']))){
      $value=$this->getMetaValue($fields_complement);
    }
    if($value=='' || $value == TZR_DATETIME_EMPTY) $r->raw=TZR_DATETIME_EMPTY;
    elseif(is_array($value)){
      $datedef=new \Seolan\Field\Date\Date();
      $hourdef=new \Seolan\Field\Time\Time();
      $hourdef->display_format=$this->display_format;
      $hourdef->edit_format=$this->edit_format;
      $date=$datedef->post_edit($value['date']);
      $hour=$hourdef->post_edit($value['hour']);
      if(empty($hour->raw)) $hour->raw='00:00:00';
      $r->raw=$date->raw.' '.$hour->raw;
    }else{
      if(strlen($value)>=19){ // Date et heure avec fuseau
	$r->raw=date('Y-m-d H:i:s',strtotime($value));
      }elseif(strlen($value)>10){ // Date et heure
	$r->raw=$value;
      }else{ // Seulement date : dans ce cas, on utilise xdatedef
	$datedef=new \Seolan\Field\Date\Date();
	$date=$datedef->post_edit($value);
	$r->raw=$date->raw.' 00:00:00';
      }
    }
    if(!empty($options['togmt'])) $r->raw=gmdate('Y-m-d H:i:s',strtotime($r->raw));
    $this->trace(@$options['old'],$r);
    return $r;
  }
  function my_query($value,$options=NULL) {
    if (is_array($value) && count($value) === 1 && array_key_exists(0, $value)) {
      $value = $value[0];
    }
    
    $p=new \Seolan\Core\Param($options,array());
    $format=$p->get('qfmt');
    if(empty($format)) $format=$this->query_format;

    $labelin=$p->get('labelin');
    $r = $this->_newXFieldVal($options);
    if(is_string($value)){
      $ovalue=$value;
      $value = [];
      $value['date']=substr($ovalue,0,10);
      $value['hour']=substr($ovalue,11);
    }
    
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    unset($options['fieldname']); // on doit pas le passer au my_query de Field/Date
    $op=$options['op'];
    if (empty($options['op']) && $this->isFilterCompulsory($options) && !empty(@$options['fields_complement']['query_comp_field_op'])) {
      $options['op'] = @$options['fields_complement']['query_comp_field_op'];
    }
    if($format=='classic' || $format=='nohour'){
      $txt= '<select name="'.$fname.'_op">';
      $txt.= '<option value="">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','date_contains').'</option>';
      $txt.= '<option value="=" '.($op=='='?'selected':'').'>=</option>';
      $txt.= '<option value=">" '.($op=='>'?'selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','date_after').'</option>';
      $txt.= '<option value="<" '.($op=='<'?'selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','date_before').'</option> ';
      $txt.= '<option value="now" '.($op=='now'?'selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','today').'</option>';
      $txt.= '<option value="is empty"'.($op=='is empty'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','is_empty').'</option>';
      $txt.= '</select>';
    }
    
    $o=(object)array('FIELD'=>$fname.'[date]','COMPULSORY'=>$this->compulsory,'TRANSLATABLE'=>$this->translatable);
    $datedef=new \Seolan\Field\Date\Date($o);
    $date=$datedef->my_query(@$value['date'],array_merge($options, array('qfmt'=>'noop')));

    $txt.=$date->html;
    if($format=='classic' || $format=='noop'){
      $o=(object)array('FIELD'=>$fname.'[hour]','COMPULSORY'=>$this->compulsory,'TRANSLATABLE'=>$this->translatable);
      $hourdef=new \Seolan\Field\Time\Time($o);
      $hourdef->display_format=$this->display_format;
      $hourdef->edit_format=$this->edit_format;
      $hour=$hourdef->my_query(@$value['hour'],array_merge($options, array('qfmt'=>'noop')));
      $txt.=' '.$hour->html;
    }
    $r->html=$txt;
    return $r;
  }
  function my_quickquery($value,$options=NULL) {
    $ar['query_format']=\Seolan\Core\Field\Field::QUICKQUERY_FORMAT;
    $r=$this->query($value,$options);
    $r->html='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">'.$r->html;
    return $r;
  }
  function post_query($o,$ar){
    if($o->op=='now'){
      $o->rq='(DATE('.$o->field.') = curdate())';
      return;
    }
    if($o->op=='yesterday'){
      $o->rq='(DATE('.$o->field.')=DATE_SUB(now(), INTERVAL 1 DAY)';
      return;
    }
    if($o->op=='is empty'){
      $f1='DATE('.$o->field.')';
      $o->rq="($f1 = '0000-00-00' OR $f1 IS NULL OR $f1='')";
      return;
    }
    if(is_array($o->value) && (!empty($o->value['date']) || !empty($o->value['hour']))){
      $datedef=new \Seolan\Field\Date\Date();
      $hourdef=new \Seolan\Field\Time\Time();
      $hourdef->display_format=$this->display_format;
      $hourdef->edit_format=$this->edit_format;
      $newval=array();
      if(!empty($o->value['date'])){
	$date=$datedef->post_edit($o->value['date']);
	$newval[]=$date->raw;
      }
      if(!empty($o->value['hour'])){
	$hour=$hourdef->post_edit($o->value['hour']);
	$newval[]=$hour->raw;
      } elseif ($o->op == '='){
	$o->op = '';
      }
      $o->value=implode(' ',$newval);
    }
    return parent::post_query($o,$ar);
  }
  function sqltype() {
    return 'datetime';
  }

  /// Ecriture dans un fichier excel
  function writeXLS($xl,$i,$j,$value,$format=0,$ss=null) {
    if(empty($value->raw) || ($value->raw==TZR_DATETIME_EMPTY)) {
      $xl->setCellValueByColumnAndRow($j,$i,'');
    }else{
      $xdate=new \Seolan\Field\Date\Date();
      $fmt=$xdate->convertFormat(NULL,'dd','mm','yyyy','d','m','yy').' hh:mm:ss';
      $v=strtotime($value->raw.' GMT');
      $v=($v/(60*60*24))+25569;
      $xl->setCellValueByColumnAndRow($j,$i,$v);
      $xl->getStyleByColumnAndRow($j,$i)->getNumberFormat()->setFormatCode($fmt);
    }
    if(is_array($format)) $xl->getStyleByColumnAndRow($j,$i)->applyFromArray($format);
  }

  static function dateToTimestamp($date)  {
    // Un timestamp mySQL n'est pas un timestamp UNIX, donc il faut convertir.
    $expl = preg_split('@[\:\ -]@',$date);
    if(count($expl)>3) {
      $timestamp = mktime($expl[3], $expl[4], $expl[5], $expl[1], $expl[2], $expl[0]);
    } else {
      $timestamp = mktime(substr($date,8,2),substr($date,10,2),substr($date,12,2),
			  substr($date,4,2),substr($date,6,2),substr($date,0,4));
    }
    return $timestamp;
  }
  /// Recupere le texte d'une valeur
  public function &toText($r) {
    if($r->text===NULL){
      if($this->isEmpty($r))
	$r->text='';
      else {
	$this->formatDateTime($r);
	$r->text = $r->html;
      }
    }
    return $r->text;
  }
  public function isEmpty($r){
    if (property_exists($r, 'raw'))
      return (empty($r->raw) || TZR_DATETIME_EMPTY == $r->raw);
    return true;
  }

  /**
   * Function isQueryEmpty
   * @return true si il n'y a pas de recherche en cours sur le champ
   */
  public function isQueryEmpty($query=array(), $isValueEmpty=false){
    $p = new \Seolan\Core\Param($query);
    $field = ($query && count($query['_FIELDS'])) ? array_search($this->field, $query['_FIELDS']) : $this->field;
    $fieldVal = $p->get($field);
    $isValueEmpty = (is_array($fieldVal) && empty($fieldVal['date']) && empty($fieldVal['hour']));

    return parent::isQueryEmpty($query, $isValueEmpty);
  }
}

