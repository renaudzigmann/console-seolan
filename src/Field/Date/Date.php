<?php
namespace Seolan\Field\Date;
/// Gestion des champs date
class Date extends \Seolan\Core\Field\Field {
  public $DATE_SEPARATOR = '-';
  public $datemin='1930-01-01';
  public $datemax='2031-12-31';
  public $inputdate = true;
  public $query_formats=array('classic', 'noop', 'range', 'filled');
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  function __construct($obj=NULL) {
    parent::__construct($obj) ;
  }
  function initOptions() {
    parent::initOptions();
    $dategroup = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', '\seolan\field\date\date');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','datemin'), 'datemin', 'date',array('free'=>true), null, $dategroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','datemax'), 'datemax', 'date',array('free'=>true), null, $dategroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','usehtml5tag'), 'inputdate', 'boolean', null, null, $dategroup);
  }
  function my_export($value) {
    $lang = \Seolan\Core\Shell::getLangUser();
    $fmt=\Seolan\Core\Lang::$locales[$lang]['date_format'];
    return $this->dateFormat($value);
  }
  /// Ecriture dans un fichier excel
  function writeXLSPHPOffice(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet,$rownum,$colnum,$value,$format=0,$options=null) {
    $fmt=$this->convertFormat(NULL,'dd','mm','yyyy','d','m','yy');
    if(empty($value->raw) || ($value->raw==TZR_DATE_EMPTY)) {
      $worksheet->setCellValueByColumnAndRow($colnum, $rownum, '');
    }else{
      $d2 = \PhpOffice\PhpSpreadsheet\Shared\Date::stringToExcel($value->raw);
      $worksheet->setCellValueByColumnAndRow($colnum, $rownum, $d2);
      $worksheet->getStyleByColumnAndRow($colnum,$rownum)->getNumberFormat()->setFormatCode($fmt);
    }
    if(is_array($format)) $worksheet->getStyleByColumnAndRow($colnum,$rownum)->applyFromArray($format);
  }
  /// Ecriture dans un fichier excel (PHPExcel)
  function writeXLS($xl,$i,$j,$value,$format=0,$ss=NULL) {
    $fmt=$this->convertFormat(NULL,'dd','mm','yyyy','d','m','yy');
    if(empty($value->raw) || ($value->raw==TZR_DATE_EMPTY)) {
      $xl->setCellValueByColumnAndRow($j,$i,'');
    }else{
      $d2 = \PhpOffice\PhpSpreadsheet\Shared\Date::stringToExcel($value->raw);
      $xl->setCellValueByColumnAndRow($j,$i,$d2);
      $xl->getStyleByColumnAndRow($j,$i)->getNumberFormat()->setFormatCode($fmt);
    }
    if(is_array($format)) $xl->getStyleByColumnAndRow($j,$i)->applyFromArray($format);
  }

  /// Sous fonction redéfinie pour chaque type de champ pour l'import de données vers une table
  function my_import($value, $specs=null){
    if(empty($value)) $value=TZR_DATE_EMPTY;
    elseif(is_numeric($value) && $value<200000) $value=gmdate('Y-m-d',($value-25569)*60*60*24);
    return array('message'=>'','value'=>$value);
  }

  function my_display_deferred(&$r){
    if(empty($r->raw) || ($r->raw==TZR_DATE_EMPTY)) {
      $r->html='';
    }else{
      $r->html=$this->dateFormat($r->raw);
    }
    return $r;
  }

  /// Rend la valeur par defaut du champ au format Y-m-d
  function getDefaultValue() {
    if($this->default){
      if(preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$this->default)){
	if (static::dateIsValid($this->default, 'Y-m-d'))
	  return $this->default;
	else {
	  \Seolan\Core\Logs::notice(__METHOD__,"invalid default date '{$this->default}'");
	  return TZR_DATE_EMPTY;
	}
      } else {
	return date('Y-m-d',strtotime($this->default));
      }
    }elseif($this->compulsory)
    return date('Y-m-d');
    else return TZR_DATE_EMPTY;
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    if(isset($options['intable'])) {
      $o=$options['intable'];
      $fname=$this->field.'['.$o.']';	
      $hiddenname=$this->field.'_HID['.$o.']';
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field.'_HID';
    }
    $varid=getUniqID('v');
    $color=\Seolan\Core\Ini::get('error_color');
    $r=$this->_newXFieldVal($options);
    if(!isset($value) && $this->compulsory) $value=date('Y-m-d');
    if(!isset($value) && !$this->compulsory) $value=TZR_DATE_EMPTY;
    $t=$this->getJSCode($value,$this->label,$fname,$varid,$this->compulsory,$options);
    $fmt=$this->convertFormat(\Seolan\Field\Date\Date::getEditFormat(),'((0[1-9])|([1-2][0-9])|(3[0-1]))','((0[1-9])|(1[0-2]))','([1-2][0-9]{3})',NULL,NULL,'([0-9]{2})');
    $fmt=str_replace('/','\/',$fmt);
    if($this->compulsory) $fmt='^('.$fmt.')$';
    else $fmt='^(('.$fmt.')?)$';

    $t.='<script type="text/javascript">'
      . 'if (jQuery("#'.$varid.'").prop("type") != "date"){ TZR.addValidator(["'.$varid.'",/'.$fmt.'/,"'.addslashes($this->label).'","'.$color.'","\Seolan\Field\ShortText\ShortText"]);}'
      . '</script>';
    $r->html = $t;
    $datefmt = $this->convertFormat(\Seolan\Field\Date\Date::getEditFormat(),'d', 'm', 'Y', 'j', 'n', 'y');
    $r->raw = $this->convert($value,$datefmt,'Y-m-d');
    $r->varid=$varid;
    return $r;
  }
  function my_query($value,$options=NULL) {
    $p=new \Seolan\Core\Param($options);
    $format=$p->get('qfmt');
    if(empty($format)) $format=$this->query_format;
    $labelin=$p->get('labelin');
    $r=$this->_newXFieldVal($options);
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $formatdate=true;
    $uniq=uniqid('v').'qxidxid';
    if(is_array($value)) $value=implode($value);
    if(empty($value) || $value === TZR_DATE_EMPTY){
      if(empty($labelin)){
	$value='';
      } else {
	$value=$this->label;
	$formatdate=false;
      }
    }
    $txt='';
    if($format=='classic' || ($this->compulsory && $format=='filled')){
      $op=$options['op'];
      if (empty($options['op']) && $this->isFilterCompulsory($options) && !empty(@$options['fields_complement']['query_comp_field_op'])) {
        $op = @$options['fields_complement']['query_comp_field_op'];
      }
      $txt= '<select name="'.$fname.'_op">';
      $txt.= '<option value="regexp">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','date_contains').'</option>';
      $txt.= '<option value="=" '.($op=='='?'selected':'').'>=</option>';
      $txt.= '<option value=">" '.($op=='>'?'selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','date_after').'</option>';
      $txt.= '<option value="<" '.($op=='<'?'selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','date_before').'</option>';
      $txt.= '<option value="<=" '.($op=='<='?'selected':'').'><=</option>';
      $txt.= '<option value=">=" '.($op=='>='?'selected':'').'>>=</option>';
      $txt.= '<option value="now" '.($op=='now'?'selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','today').'</option>';
      $txt.= '<option value="beforenow"'.($op=='beforenow'?'selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','past').'</option>';
      $txt.= '<option value="afternow"'.($op=='afternow'?'selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','future').'</option>';
      $txt.= '<option value="is empty"'.($op=='is empty'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','is_empty').'</option>';
      $txt.= '</select> ';
      $txt.=$this->getJSCode($value,$this->label,$fname,$uniq,false,$options,$formatdate,true);
    } elseif($format=='filled') {
      $this->getFilled($value,$r,$fname);
      return $r;
    } else{
      $txt.=$this->getJSCode($value,$this->label,$fname,$uniq,false,$options,$formatdate,true);
    }
    if(!empty($labelin))
      $txt.='<script type="text/javascript">inputInit("'.$uniq.'","'.$this->label.'");</script>';
    $r->html=$txt;
    $r->raw=$value;
    return $r;
  }
  
  // Edition du champ sous la forme d'une liste rempli/non rempli
  function getFilled($value,&$r,$fname){
    $r->html='<input type="hidden" value="'.$fname.'" name="_FIELDS['.$fname.']">';
    $r->html.='<input type="hidden" name="'.$fname.'_op" value="filled"/>';
    $r->html.='<select name="'.$fname.'" id="'.$fname.'"><option value="">----</option>';
    $r->html.='<option value="is empty"'.(in_array('is empty', $value)?' selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','is_empty').'</option>';
    $r->html.='<option value="is not empty"'.(in_array('is not empty', $value)?' selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','is_not_empty').'</option>';
    $r->html.='</select>';
  }
 
  function my_quickquery($value,$options=NULL){
    $p=new \Seolan\Core\Param($options);
    $format=$p->get('qfmt');
    if(empty($format)) $format=$this->query_format;
    if($format=='filled' && !$this->compulsory) {
      $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
      $r=$this->_newXFieldVal($options);
      $this->getFilled($value,$r,$fname);
      $r->raw=$value;
      return $r;
    }
    if(is_array($value)) $value=implode($value);
    $options['popuponinput'] = true;
    $r=$this->_newXFieldVal($options,true);
    $r->html='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    $r->html.=$this->getJSCode($value,$this->label,$this->field,$r->varid,false,$options,false,true);
    $r->raw=$value;
    return $r;
  }
  function sqltype() {
    return 'date';
  }
  /// Créé un timestamp à partir d'une date internationale (identique à strtotime en plus léger)
  function dateToTimestamp($date) {
    if(strpos($date,$this->DATE_SEPARATOR)) $dateArray=explode($this->DATE_SEPARATOR,$date);
    else $dateArray=array(substr($date,0,4),substr($date,4,2),substr($date,6,2));
    $timestamp=mktime(0,0,0,@$dateArray[1],@$dateArray[2],@$dateArray[0]);
    return $timestamp;
  }
  function dateFormat($date) {
    $lang=\Seolan\Core\Shell::getLangUser();
    $fmt=\Seolan\Core\Lang::$locales[$lang]['date_format'];
    $fmted=date($fmt,$this->dateToTimestamp($date));
    return $fmted;
  }
  static function printDate($date){
    $lang = \Seolan\Core\Shell::getLangUser();
    $fmt=\Seolan\Core\Lang::$locales[$lang]['date_format'];
    $fmted= date($fmt,strtotime($date));
    return $fmted;
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $p=new \Seolan\Core\Param($options,array());
    $r=$this->_newXFieldVal($options);
    $namey=$this->field.'_year';
    $namem=$this->field.'_month';
    $named=$this->field.'_day';
    $year=$p->get($namey);
    $month=$p->get($namem);
    $day=$p->get($named);
    if((strlen($year)>0) && (strlen($month)>0) && (strlen($day)>0)) $value=sprintf('%04d-%02d-%02d',$year,$month,$day);
    elseif(!empty($this->exif_source) && empty($value)){
      $value=$this->getMetaValue($fields_complement);
    }
    if($value=='') $value=TZR_DATE_EMPTY;
    elseif($value=='today') $value=date('Y-m-d');
    elseif($value=='0000-00-00') $value=TZR_DATE_EMPTY;
    elseif($value=='empty') $value=TZR_DATE_EMPTY;
    elseif(preg_match('/^([0-9]{4})[\/-]?([0-9]{2})[\/-]?([0-9]{2}).*$/',$value,$regs)) $value=$regs[1].'-'.$regs[2].'-'.$regs[3];
    else{
      $datefmt=$this->convertFormat(\Seolan\Field\Date\Date::getEditFormat(),'d', 'm', 'Y', 'j', 'n', 'y');
      $value=$this->convert($value,$datefmt,'Y-m-d');
    }
    $r->raw=$value;
    $this->trace(@$options['old'],$r);
    return $r;
  }
  function post_edit_dup($value,$options) {
    $p = new \Seolan\Core\Param($options, array());
    $oidsrc=$p->get('oidsrc');
    $options['oid']=$oidsrc;
    return $this->post_edit($value,$options);
  }
  /// Retourne une chaine de caractere decrivant la recherche en cours sur le champ
  function getQueryText($o){
    if($this->query_format == 'filled' && !$this->compulsory) {
      return $this->getQueryTextOp($o->op,true);
    } elseif (($this->query_format == 'range') && strpos($o->value, '<>') !== false) {
      return $o->value;
    } else {
      return parent::getQueryText($o);
    }
  }
  function post_query($o,$ar){
    if (is_array($o->value)) { // json
      return parent::post_query($o, $ar);
    }
    if ($o->op=='filled') {
      $o->op=$o->value;
      return parent::post_query($o,$ar);
    }
    if($o->op=='now'){
      $o->rq='('.$o->field.'=curdate())';
      return;
    }
    if($o->op=='beforenow'){
      $dateEmpty = TZR_DATE_EMPTY;
      $o->rq="({$o->field}<=curdate() AND {$o->field}!={$o->quote}{$dateEmpty}{$o->quote})";
      return;
    }
    if($o->op=='afternow'){
      $o->rq='('.$o->field.'>=curdate())';
      return;
    }
    if($o->op=='is empty'){
      $o->rq="({$o->field} = '".TZR_DATE_EMPTY."' OR {$o->field} IS NULL OR {$o->field}='')";
      return;
    }
    // traitement interval FO / legacy
    if (($this->query_format == 'range') && strpos($o->value, '<>') !== false) {
      $datefmt = $this->convertFormat($o->fmt,'d', 'm', 'Y', 'j', 'n', 'y');
      $dates = preg_split("/ *<> */", $o->value);
      $start_date = $this->convert($dates[0], $datefmt, 'Y-m-d', true);
      $end_date = $this->convert($dates[1], $datefmt, 'Y-m-d', true);
      $o->rq = '('.$o->field.'>='.$o->quote.$start_date.$o->quote.' AND '.$o->field.'<='.$o->quote.$end_date.$o->quote.')';
      return;
    } else if (($this->query_format == 'range') && strpos($o->value, ' - ') !== false) {
      $datefmt = $this->convertFormat($o->fmt,'d', 'm', 'Y', 'j', 'n', 'y');
      $dates = preg_split("/ \- /", $o->value);
      $o->value = implode('<>', $dates);
      $start_date = $this->convert($dates[0], $datefmt, 'Y-m-d', true);
      $end_date = $this->convert($dates[1], $datefmt, 'Y-m-d', true);
      $o->rq = '('.$o->field.'>='.$o->quote.$start_date.$o->quote.' AND '.$o->field.'<='.$o->quote.$end_date.$o->quote.')';
      return;
    }
    if(preg_match('/^([0-9]{4})[\/-]?([0-9]{2})[\/-]?([0-9]{2}).*$/',$o->value,$regs)) $o->value=$regs[1].'-'.$regs[2].'-'.$regs[3];
    // attention lorsque la valeur commence par = il s'agit d'une formule
    elseif(!empty($o->value) && !empty($o->fmt) && $o->fmt!='Y-m-d' && $o->value[0]!='='){
      $datefmt=$this->convertFormat($o->fmt,'d', 'm', 'Y', 'j', 'n', 'y');
      $o->value=$this->convert($o->value,$datefmt,'Y-m-d',true);
      // Si recherche inférieure, on ne retourne pas les dates vide
      if(($o->op=='<' || $o->op=='<=') && $o->value!=TZR_DATE_EMPTY){
        $o->rq='('.$o->field.$o->op.$o->quote.$o->value.$o->quote.' AND '.$o->field.'!='.$o->quote.TZR_DATE_EMPTY.$o->quote.')';
        return;
      }
    }
    if(empty($o->op)) $o->op='regexp';
    if($o->op=='regexp' && !empty($o->value) && $o->value[0]!='='){
      $val=explode('-',$o->value);
      if(empty($val[0]) && empty($val[1])) $o->value='^.*'.$val[2].'.*$';
      elseif(empty($val[0])) $o->value='^(.*'.$val[1].'-'.$val[2].'.*)|(.*'.$val[2].'-'.$val[1].'.*)$';
      else $o->op='=';
    }elseif(is_array($o->op) && $o->op[0]=='flexi' && !empty($o->op[1])){
      $date=$o->quote.$o->value.$o->quote;
      $o->rq='('.$o->field.'>=SUBDATE('.$date.','.$o->op[1].') AND '.$o->field.'<=ADDDATE('.$date.','.$o->op[1].'))';
      return;
    }

    return parent::post_query($o,$ar);
  }

  /// Retourne le code javascript/html avec jQueryUI
  /// popuponinput : false=>ouvre le popup via le lien "selection" / true=>ouvre le popup quand on clic sur le champ
  /// in_query : true dans le cas d'un query/quickquery
  function getJSCode($value,$label,$fname,$varid,$compulsory=false,$options=array(),$formatdate=true, $in_query=false){
    $labels = null;
    $datefmt=\Seolan\Field\Date\Date::getEditFormat();
    $uidatefmt=$this->convertFormat($datefmt,'dd','mm','yy','dd','m','y');
    $t1='';
    if(!$formatdate || !preg_match('/([0-9]{4})[\/-]?([0-9]{2})[\/-]?([0-9]{2})$/',$value)){
      if($value==TZR_DATE_EMPTY) $value='';
      $fmtvalue=$value;
    }else{
      if($value!=TZR_DATE_EMPTY && !empty($value)) $fmtvalue=date($datefmt,strtotime($value));
      else $fmtvalue='';
    }
    $placeholder = '';
    if (isset($options['labelin']) && $options['labelin']) {
      $placeholder = ' placeholder="'.$this->label.'"';
    }
    if(strpos($fname,'[')!==false) $fmtname=substr($fname,0,strpos($fname,'[')).'_FMT'.substr($fname,strpos($fname,'['));
    else $fmtname=$fname.'_FMT';
    if($compulsory===true || $compulsory=="1") $mborder='class="tzr-input-compulsory" required';
    else $mborder='';
    $datemin = isset($options['datemin']) ? $options['datemin'] : $this->datemin;
    $datemax = isset($options['datemax']) ? $options['datemax'] : $this->datemax;
    $inputdate = ((isset($options['inputdate']) && $options['inputdate']) || $this->inputdate || !$GLOBALS['TZR_PACKS']->packDefined('\Seolan\Pack\DatePicker\DatePicker')) && !($in_query && $this->query_format == 'range');
    if ($inputdate) {
      if ($value == TZR_DATE_EMPTY) {
        $value = $options['datedef'] ?? '';
      }
    $required = (@$options['query_format'] === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '');
    $t='<input type="hidden" name="'.$fmtname.'" value="'.$datefmt.'">'.
        '<input '.$required.' autocomplete="off" type="date" name="'.$fname.'" value="'.$value.'" id="'.$varid.'" size='.(($in_query && $this->query_format == 'range')?"18":"11").' '.$mborder.$placeholder.' onblur="TZR.isIdValid(\''.$varid.'\');" min="'.$datemin.'" max="'.$datemax.'">';
    } else {
      $t='<input type="hidden" name="'.$fmtname.'" value="'.$datefmt.'">'.
        '<input '.$required.' autocomplete="off" type="text" name="'.$fname.'" value="'.$fmtvalue.'" id="'.$varid.'" '.(($in_query && $this->query_format == 'range')?'size="18"':'size="11" onblur="TZR.formatDate(this); TZR.isIdValid(\''.$varid.'\');"').' '.$mborder.$placeholder.'>';
    }

    
    if (\Seolan\Core\Shell::admini_mode() && $in_query && $this->query_format == 'range') {  // traitement interval BO 
      $lang_user = \Seolan\Core\Shell::getLangUser();
      $daterangeopts='applyClass:"btn-primary",autoApply:false,autoUpdateInput:false,ranges:TZR.Daterangepicker.queryRanges(),locale:"'.$lang_user.'"';
      if(strlen($datemin)==10 && strpos($datemin,'-')==4){
        list($yearmin,$mmin,$dmin)=explode('-',$datemin);
        $daterangeopts.=',minDate:new Date("'.$yearmin.'-'.$mmin.'-'.$dmin.'")';
      }else{
        $daterangeopts.=',minDate:"'.$datemin.'"';
      }
      if(strlen($datemax)==10 && strpos($datemax,'-')==4){
        list($yearmax,$mmax,$dmax)=explode('-',$datemax);
        $daterangeopts.=',maxDate:new Date("'.$yearmax.'-'.$mmax.'-'.$dmax.'")';
      }else{
        $daterangeopts.=',maxDate:"'.$datemax.'"';
      }
      if (isset($options['daterangeopts']) && !empty($options['daterangeopts'])){
        $daterangeopts .=  ',' .$options['daterangeopts'];
      }
      $t1.='jQuery(document).ready(function(){if (typeof(TZR)!="undefined"){TZR.Daterangepicker.init(jQuery("#'.$varid.'"), {'.$daterangeopts.'});}});';

      // labels à compléter
      $labels = [[$lang_user, 'Seolan_Field_Date_Date.today', 'Aujourd\'hui'],
		 [$lang_user,'Seolan_Field_Date_Date.yesterday','Hier'],
		 [$lang_user,'Seolan_Field_Date_Date.last7days','Les 7 derniers jours'],
		 [$lang_user,'Seolan_Field_Date_Date.last30days', 'Les 30 derniers jours'],
		 [$lang_user,'Seolan_Field_Date_Date.currentmonth', 'Ce mois']];

    } else if ($in_query && $this->query_format == 'range') {  // traitement interval FO legacy à garder ?
      $uiopts='dateFormat:"'.$uidatefmt.'",changeMonth:true,changeYear:true,showButtonPanel:true,yearRange:"-200:+100"';
      if(strlen($datemin)==10 && strpos($datemin,'-')==4){
        list($yearmin,$mmin,$dmin)=explode('-',$datemin);
        $uiopts.=',minDate:new Date("'.$yearmin.'-'.$mmin.'-'.$dmin.'")';
        $daterangeopts='minDate:new Date("'.$yearmin.'-'.$mmin.'-'.$dmin.'")';
      }else{
        $uiopts.=',minDate:"'.$datemin.'"';
        $daterangeopts='minDate:"'.$datemin.'"';
      }
      if(strlen($datemax)==10 && strpos($datemax,'-')==4){
        list($yearmax,$mmax,$dmax)=explode('-',$datemax);
        $uiopts.=',maxDate:new Date("'.$yearmax.'-'.$mmax.'-'.$dmax.'")';
        $daterangeopts.=',maxDate:new Date("'.$yearmax.'-'.$mmax.'-'.$dmax.'")';
      }else{
        $uiopts.=',maxDate:"'.$datemax.'"';
        $daterangeopts.=',maxDate:"'.$datemax.'"';
      }
      if(!@$options['popuponinput'])
        $uiopts.=',showOn:"button"';
      if ($options['daterangeopts'])
      if (!empty($options['daterangeopts']))
        $daterangeopts .=  ',' .$options['daterangeopts'];
      $t1.='jQuery(document).ready(function(){if(jQuery("#'.$varid.'").length)jQuery("#'.$varid.'").daterangepicker({'.$daterangeopts.',datepickerOptions:{'.$uiopts.'}});});';
    } else { // traitement classique
      $uiopts='constrainInput:false,dateFormat:"'.$uidatefmt.'",changeMonth:true,changeYear:true,showButtonPanel:true,yearRange:"-200:+100",buttonText:"",'.
	'onSelect:function(dateText,inst){this.onblur();}';
      if(strlen($datemin)==10 && strpos($datemin,'-')==4){
        list($yearmin,$mmin,$dmin)=explode('-',$datemin);
        $uiopts.=',minDate:new Date("'.$yearmin.'-'.$mmin.'-'.$dmin.'")';
      }else{
        $uiopts.=',minDate:"'.$datemin.'"';
      }
      if(strlen($datemax)==10 && strpos($datemax,'-')==4){
        list($yearmax,$mmax,$dmax)=explode('-',$datemax);
        $uiopts.=',maxDate:new Date("'.$yearmax.'-'.$mmax.'-'.$dmax.'")';
      }else{
        $uiopts.=',maxDate:"'.$datemax.'"';
      }
      if(!@$options['popuponinput'])
        $uiopts.=',showOn:"button"';
      if ($inputdate) {
        $t1.='jQuery(function(){if (jQuery("#'.$varid.'").prop("type") != "date") {jQuery("#'.$varid.'").datepicker({'.$uiopts.'})'
          . ($value!==TZR_DATE_EMPTY && !empty($value) ? '.datepicker("setDate", new Date("'.$value.'"))' : 
          // bug affichage safari : 0000-00-00 par défaut même si vide
          (empty($value)?';setTimeout(function(){jQuery("#'.$varid.'").val("")}, 500);':'')
          ) . '}});';
      } else {
        $t1.='jQuery(function(){jQuery("#'.$varid.'").datepicker({'.$uiopts.'});});';
      }
    }
    if ($labels != null){
      $t.='<script type="text/javascript">TZR.Locales.addLabels('.json_encode($labels, true).');</script>';
    }
    $t.='<script type="text/javascript">'.$t1.'</script>';
    return $t;
  }
  /// Vérifie si une date est valide
  public static function dateIsValid($date, $format){
    $d = \DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
  }
  /// Recupere le format à utiliser pour l'edition (pour simplifier certain traitement, dates doit etre composé que de chiffre et de /)
  static function getEditFormat($fmt=NULL){
    if(empty($fmt)){
      $lang=\Seolan\Core\Shell::getLangUser();
      if(!empty(\Seolan\Core\Lang::$locales[$lang]['edit_date_format'])) return \Seolan\Core\Lang::$locales[$lang]['edit_date_format'];
      $fmt=\Seolan\Core\Lang::$locales[$lang]['date_format'];
    }
    $fmt=str_replace(array('M','F'),'m',$fmt);
    $fmt=preg_replace('/[^A-Za-z]+/','/',$fmt);
    if($lang) \Seolan\Core\Lang::$locales[$lang]['edit_date_format']=$fmt;
    return $fmt;
  }

  /// Convertit une date d'un format à un autre (le format source doit contenir Y ou y, m ou n et d ou j)
  function convert($value, $src, $dst, $internationalapprox=false) {
    $ssrc=preg_split('@[-/:, ]+@',$src);
    $s2src=array_flip($ssrc);
    $sval=preg_split('@[-/:, ]+@',$value);
    $dval='';

    if(isset($s2src['Y'])) $dval.=$sval[$s2src['Y']];
    else $dval.=$sval[$s2src['y']];
    $dval.='-';
    if(isset($s2src['m'])) $dval.=$sval[$s2src['m']];
    else $dval.=$sval[$s2src['n']];
    $dval.='-';
    if(isset($s2src['d'])) $dval.=$sval[$s2src['d']];
    else $dval.=$sval[$s2src['j']];

    if($internationalapprox) return $dval;
    else return date($dst,strtotime($dval));
  }

  /// Utiliser pour adapter un format de date aux différents outils
  static function convertFormat($fmt,$d,$m,$y,$d2=NULL,$m2=NULL,$y2=NULL){
    if(empty($fmt)){
      $lang=\Seolan\Core\Shell::getLangUser();
      $fmt=\Seolan\Core\Lang::$locales[$lang]['date_format'];
    }
    if(empty($d2)) $d2=$d;
    if(empty($m2)) $m2=$m;
    if(empty($y2)) $y2=$y;
    $fmt=str_replace('d',$d,$fmt);
    $fmt=str_replace('D',$d,$fmt);
    $fmt=str_replace('j',$d2,$fmt);
    $fmt=str_ireplace('m',$m,$fmt);
    $fmt=str_ireplace('f',$m,$fmt);
    $fmt=str_replace('n',$m2,$fmt);
    $fmt=str_replace('y',$y2,$fmt);
    $fmt=str_replace('Y',$y,$fmt);
    return $fmt;
  }

  /// Recupere le texte d'une valeur
  public function &toText($r) {
    if(@$r->text===NULL){
      if($r->raw==TZR_DATE_EMPTY || empty($r->raw)) $r->text='';
      else $r->text=date(\Seolan\Field\Date\Date::getEditFormat(),strtotime($r->raw));
    }
    return $r->text;
  }
  public function isEmpty($r){
    if (property_exists($r, 'raw'))
      return (empty($r->raw) || TZR_DATE_EMPTY == $r->raw);
    return true;
  }
  public function getDocumentationData(){
    $r = parent::getDocumentationData();
    $r->constraints[] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','datemin').' : '.$this->datemin;
    $r->constraints[] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','datemax').' : '.$this->datemax;
    return $r;
  }
  /// vérifier les date entrées en valeur par défaut
  public static function fieldDescIsCorrect(&$field,&$ftype,&$fcount,&$forder,&$compulsory,&$queryable,&$browsable,$translatable,&$multivalued,&$published,&$target,&$label,&$options){
    if (empty($options['default']))
      return true;
    if(preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$options['default']) && !static::dateIsValid($options['default'], 'Y-m-d')){
      \Seolan\Core\Logs::notice(__METHOD__,"{$field} invalid default date {$options['default']}");
      return false;
    }
    return true;
  }
}

