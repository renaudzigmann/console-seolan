<?php
namespace Seolan\Field\Interval;
/// Gestion des champs intervalles de dates
/// codage : les dates sont au format mysql (yyyy-mm-dd)
/// valeur unique yyyy-mm-dd
/// valeur multiple : valeur unique;valeur unique; etc
class Interval extends \Seolan\Core\Field\Field {
  public $typeOfInterval = 'dateAndInterval';
  public $datemin='1930-01-01';
  public $datemax='2031-12-31';
  public $nbMonth = 3;
  private $lstMonths = [1, 2, 3, 4, 8, 12, 16, 20, 24];
  public $query_formats=array('range');

  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Field_Interval_Interval','typeofinterval'),'typeOfInterval','list',
                            array('values'=>array('dateAndInterval','dateOnly','intervalOnly'),
                                  'labels'=>array(\Seolan\Core\Labels::getTextSysLabel('Seolan_Field_Interval_Interval','dateandinterval'),
                                                  \Seolan\Core\Labels::getTextSysLabel('Seolan_Field_Interval_Interval','dateonly'),
                                                  \Seolan\Core\Labels::getTextSysLabel('Seolan_Field_Interval_Interval','intervalonly'))),
                            NULL);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','datemin'), 'datemin', 'date',array('free'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','datemax'), 'datemax', 'date',array('free'=>true));
    $this->_options->setOpt('Nombre de mois affichés', 'nbMonth', 'list', ['values' => $this->lstMonths, 'labels' => $this->lstMonths], 1);
  }

  /// Ecriture dans un fichier excel
  function writeXLS($xl,$i,$j,$value,$format=0,$ss=NULL) {
    if (is_object($value)) {
      $v = $value->raw;
    } else {
      $v = '';
    }
    convert_charset($v, TZR_INTERNAL_CHARSET, 'UTF-8');
    $xl->setCellValue(\PHPExcel_Cell::stringFromColumnIndex($j-1) . $i, $v);
    if (is_array($format))
      $xl->getStyleByColumnAndRow($j, $i)->applyFromArray($format);
  }

  /** 
   Affichage des dates
   $this->formatValues($value) met en forme les intervales
   Picto : affiche un info-bulle contenant les dates, et un picto permettant d'afficher le calendrier
   Extract : Idem + ajout d'un extrait de l'info-bulle
   Full : affiche toutes les dates séléctionnées + un picto calendrier 
  **/
  function my_display_deferred(&$field) {
    $field->varid = getUniqID('v');
    $value = $this->formatValues($field->raw);
    if (empty($value)) {
      $field->html = '';
      return;
    }
    $field->html = $this->displayHtml($field);
    $picto = '<a data-html="true" data-toggle="popover" data-content="'.htmlspecialchars($value).'">'. \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'more', 'csico').'</a>';
    switch ($this->browse_format) {
      case 'picto':
        $field->html .= $picto;
        break;
      case 'extract':
        if (strlen($value) < 50) {
          $field->html .= $value;
          break;
	}
        $field->html .= substr($value, 0, 50) . ' ...';
        $field->html .= $picto;
        break;
      default: // 'full'
        $field->html .= $value;
    }
    return $field;
  }

  function my_edit(&$value,&$options,&$fields_complement=NULL){
    // Construction du champs à partir des otions
    $field = $this->_newXFieldVal($options);
    $field->varid = getUniqID('v');
    // Calcule le nom de l'input hidden de date en fonction du mode d'édition (Edition fiche ou colonne)
    if (isset($options['intable'])) {
      $o = $options['intable'];
      $field->field = $this->field . '[' . $o . ']';
    } elseif (!empty($options['fieldname'])) {
      $field->field = $options['fieldname'];
    }
    // Affecte la valeur précédente
    $field->raw = $value;
    // Génère le html du champ
    $field->html = $this->editHtml($field);
    if ($field->fielddef->compulsory) {
      $color=\Seolan\Core\Ini::get('error_color');
      $field->html .='<script type="text/javascript">TZR.addValidator(["'.$field->varid.'",/(.+)/,"'.addslashes($this->label).'","'.$color.'","\Seolan\Field\Interval\Interval"]);</script>';
    }

    return $field;
  }
  function editHtml($field){
    return $this->getHtml($field, true);
  }
  function getHtml($field, $edit) {
    if (!isset($field->varid))
      $field->varid = uniqid();
    // !! $field est un Field\Value 
    $calIco = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'calendar', 'csico');
    $datefmt = \Seolan\Field\Date\Date::convertFormat(\Seolan\Field\Date\Date::getEditFormat(), 'dd', 'mm', 'yy', 'dd', 'm', 'y');
    if ($edit){
      $delIco = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'delete', 'csico');
      $refreshIco = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'refresh', 'csico');
      $delete = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'delete', 'text');
      $edit = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'edit', 'text');
    }
    $from = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar', 'at1');
    $to = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar', 'to1');
    $html = '<input type="hidden" id="' . $field->varid . '" name="' . $field->field . '" value="' . $field->raw . '" data-datemin="' . $this->datemin . '" data-datemax="' . $this->datemax . '" data-format="' . $datefmt . '" ';
    if (!$edit)
      $html .= 'data-readonly="1"';
    $html .='/>';
    $html .= '<button  title="'.$edit.'" class="btn btn-default btn-md btn-inverse" onclick="TZR.Xinterval.show(this, \'' . $field->varid . '\');" id="pictoCalendar' . $field->varid . '" type="button">'.$calIco.'</button>';
    if ($edit){
       $html .= '<button title="'.$delete.'" class="btn btn-default btn-md btn-inverse" onclick="TZR.Xinterval.clear(\'' . $field->varid . '\')" type="button">'.$delIco.'</button>';
    }
    //< tpl pour la modal
    $divid = 'intervaldiv'.$field->varid;
    $html .= '<div class="" id="'.$divid.'_" style="display:none"><ul class="nav nav-pills module-tool" role="tablist">';
    $html .= str_replace('nbMonths'.$field->varid, 'nbMonths'.$field->varid.'_',$this->getSelectNbMonth($field));
    if ($edit){
      $html .= '<li><button class="btn btn-default" onclick="TZR.Xinterval.clear(\'' . $field->varid . '\')" type="button">'.$delIco.'</button></li>';
      $html .= '<li><button class="btn btn-default" onclick="TZR.Xinterval.reset(\'' . $field->varid . '\')" type="button">'.$refreshIco.'</button></li>';
      $html .= '<li>(Shift -> période)</li>';
    }
    $html .= '</ul>';
    $html .= '<div id="datePicker'.$field->varid.'_"></div>';
    $html .= '</div>';
    $html .= '<div id="recap'.$field->varid.'"></div>';
    $label = addslashes($this->label);
    if ($edit){
      $html .= <<<EOD
      <script type="text/javascript">
      jQuery(document).ready( function () {
	  TZR.Xinterval.init('{$field->varid}', {title:'{$label}'});
	  TZR.Xinterval.fromLabel = '{$from}';
	  TZR.Xinterval.toLabel = '{$to}';
	  TZR.Xinterval.updateRecap('{$field->varid}', true);
	});
    </script>
EOD;
    } else {
      $html .= <<<EOD
      <script type="text/javascript">
	jQuery(document).ready( function () {
	    TZR.Xinterval.init('{$field->varid}', {title:'{label}'});
	    TZR.Xinterval.fromLabel = '{$from}';
	    TZR.Xinterval.toLabel = '{$to}';
	  });
      </script>
EOD;
    }
    return $html;
  }

  function displayHtml($field) {
    return $this->getHtml($field, false);
  }

  function post_edit ($value,$options=NULL,&$computed_fields=NULL) {
    $p = new \Seolan\Core\Param($options,array());
    $field = $this->_newXFieldVal($options);
    if (is_array($value)) {
      $value = implode(';', $value);
    }
    $field->raw = $value;
    $this->trace($options['old'], $field);
    return $field;
  }

  function sqltype() {
    return 'text';
  }

  /// Va chercher les dates correspondant à la requete
  function post_query($o,$ar){
    $now = gmdate('Y-m-d');
    if($o->op=='now'){
      $o->rq='('.$o->field.' LIKE "%'.$now.'%")';
      return;
    }
    if($o->op=='is empty'){
      $f1=$o->field;
      $o->rq="($f1 = '0000-00-00' OR $f1 IS NULL OR $f1='')";
      return;
    }
    // traitement interval
    if (strpos($o->value, '<>') !== false) {
      $dates = preg_split("/ *<> */", $o->value);
      $datefmt = \Seolan\Field\Date\Date::convertFormat($o->fmt,'d', 'm', 'Y', 'j', 'n', 'y');
      if (preg_match('/^[0-9\/-]+$/', $dates[0]))
        $dates[0] = \Seolan\Field\Date\Date::convert($dates[0], $datefmt, 'Y-m-d', true);
      if (preg_match('/^[0-9\/-]+$/', $dates[1]))
        $dates[1] = \Seolan\Field\Date\Date::convert($dates[1], $datefmt, 'Y-m-d', true);
      $date_to_request = $start_date = date('Y-m-d', strtotime($dates[0]));
      $end_date = date('Y-m-d', strtotime($dates[1]));
      if ($start_date === false || $end_date === false) return;
      if (strtotime($start_date) > strtotime($end_date)) {
        $start_date = $end_date;
        $end_date = $date_to_request;
        $date_to_request = $start_date;
      }
      $conditions = array();
      while (strtotime($date_to_request) <= strtotime($end_date)) {
        $conditions[] = '('.$o->field.' LIKE "%'.$date_to_request.'%")';
        $date_to_request = date('Y-m-d', strtotime($date_to_request.' +1day'));
        if ($pas_plus_que_un_an++ > 365) break;
      }
      $o->rq = '('.implode(' OR ', $conditions).')';
      return;
    }

    // Par défaut lorsqu'une recherche est saisie
    if (!empty($o->value)){
      $datefmt = \Seolan\Field\Date\Date::convertFormat($o->fmt,'d', 'm', 'Y', 'j', 'n', 'y');
      $date = $this->convert($o->value, $datefmt, 'Y-m-d', true);
      $o->rq='('.$o->field.' LIKE "%'.$date.'%")';
      return;
    }
    return \Seolan\Core\Field\Field::post_query($o, $ar);
  }

  function my_query($value,$options=NULL) {
    if(is_array($value)) $value=implode('',$value);
    $options['popuponinput'] = true;
    $r=$this->_newXFieldVal($options,true);
    if(\Seolan\Core\Shell::admini_mode() && \Seolan\Core\Shell::_function() !== 'editfunction'){
      $r->html='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    }
    $r->html.= $this->getJSCode($value,$this->label,$this->field,$r->varid,false,$options,false,true);
    $r->raw=$value;
    return $r;
  }

  function my_quickquery($value,$options=NULL){
    return $this->my_query($value,$options);
  }

/// Retourne le code javascript/html avec jQueryUI
  /// popuponinput : false=>ouvre le popup via le lien "selection" / true=>ouvre le popup quand on clic sur le champ
  /// in_query : true dans le cas d'un query/quickquery
  function getJSCode($value,$label,$fname,$varid,$compulsory=false,$options=array(),$formatdate=true, $in_query=false){
    $labels = null;
    $datefmt=\Seolan\Field\Date\Date::getEditFormat();
    $uidatefmt=\Seolan\Field\Date\Date::convertFormat($datefmt,'dd','mm','yy','dd','m','y');
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
    $inputdate = ((isset($options['inputdate']) && $options['inputdate']) || $this->inputdate) && !($in_query && $this->query_format == 'range');
    if ($inputdate) {
      if ($value == TZR_DATE_EMPTY) {
        $value = $options['datedef'] ?? '';
      }
    $t='<input type="hidden" name="'.$fmtname.'" value="'.$datefmt.'">'.
        '<input '.($options['query_format'] === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' autocomplete="off" type="date" name="'.$fname.'" value="'.$value.'" id="'.$varid.'" size='.(($in_query && $this->query_format == 'range')?"18":"11").' '.$mborder.$placeholder.' onblur="TZR.isIdValid(\''.$varid.'\');" min="'.$datemin.'" max="'.$datemax.'">';
    } else {
      $t='<input type="hidden" name="'.$fmtname.'" value="'.$datefmt.'">'.
        '<input '.($options['query_format'] === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' autocomplete="off" type="text" name="'.$fname.'" value="'.$fmtvalue.'" id="'.$varid.'" '.(($in_query && $this->query_format == 'range')?'size="21"':'size="11" onblur="TZR.formatDate(this); TZR.isIdValid(\''.$varid.'\');"').' '.$mborder.$placeholder.'>';
    }


    if (\Seolan\Core\Shell::admini_mode() && $in_query && $this->query_format == 'range') {  // traitement interval BO 
      $lang_user = \Seolan\Core\Shell::getLangUser();
      $daterangeopts='applyClass:"btn-primary",autoApply:false,autoUpdateInput:false,ranges:TZR.Daterangepicker.queryRanges(),locale:"'.$lang_user.'"';
      if(strlen($datemin)==10 && strpos($datemin,'-')==4){
        list($yearmin,$mmin,$dmin)=explode('-',$datemin);
        $daterangeopts.=',minDate:new Date("'.$yearmin.'","'.($mmin-1).'","'.$dmin.'")';
      }else{
        $daterangeopts.=',minDate:"'.$datemin.'"';
      }
      if(strlen($datemax)==10 && strpos($datemax,'-')==4){
        list($yearmax,$mmax,$dmax)=explode('-',$datemax);
        $daterangeopts.=',maxDate:new Date("'.$yearmax.'","'.($mmax-1).'","'.$dmax.'")';
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
        $uiopts.=',minDate:new Date("'.$yearmin.'","'.($mmin-1).'","'.$dmin.'")';
        $daterangeopts='minDate:new Date("'.$yearmin.'","'.($mmin-1).'","'.$dmin.'")';
      }else{
        $uiopts.=',minDate:"'.$datemin.'"';
        $daterangeopts='minDate:"'.$datemin.'"';
      }
      if(strlen($datemax)==10 && strpos($datemax,'-')==4){
        list($yearmax,$mmax,$dmax)=explode('-',$datemax);
        $uiopts.=',maxDate:new Date("'.$yearmax.'","'.($mmax-1).'","'.$dmax.'")';
        $daterangeopts.=',maxDate:new Date("'.$yearmax.'","'.($mmax-1).'","'.$dmax.'")';
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
        $uiopts.=',minDate:new Date("'.$yearmin.'","'.($mmin-1).'","'.$dmin.'")';
      }else{
        $uiopts.=',minDate:"'.$datemin.'"';
      }
      if(strlen($datemax)==10 && strpos($datemax,'-')==4){
        list($yearmax,$mmax,$dmax)=explode('-',$datemax);
        $uiopts.=',maxDate:new Date("'.$yearmax.'","'.($mmax-1).'","'.$dmax.'")';
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

  /// Recupere le texte d'une valeur
  public function &toText($field) {
    if ($field->text === NULL) {
      if (empty($field->raw))
        $field->text = '';
      else
        $field->text = $this->formatValues($field->raw);
    }
    return $field->text;
  }

  function dateFormat($date) {
    $lang=\Seolan\Core\Shell::getLangUser();
    $fmt=\Seolan\Core\Lang::$locales[$lang]['date_format'];
    $fmted=date($fmt,$this->dateToTimestamp($date));
    return $fmted;
  }

  /// Créé un timestamp à partir d'une date internationale (identique à strtotime en plus léger)
  function dateToTimestamp($date) {
    $dateArray = explode('-', $date);
    $timestamp = mktime(0, 0, 0, @$dateArray[1], @$dateArray[2], @$dateArray[0]);
    return $timestamp;
  }

  function formatValues($value) {
    $dates = array_filter(explode(';', $value));
    $from = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar', 'at1');
    $to = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar', 'to1');
    $html = '';
    foreach (self::datesToPeriods($dates) as $period) {
      if ($period[0] == $period[1]) {
        $html .= $this->dateFormat($period[0]) . '<br />';
      } else {
        $html .= "$from " . $this->dateFormat($period[0]) . " $to " . $this->dateFormat($period[1]) . '<br />';
      }
    }
    return $html;
  }

  function getModeDateOnly($field) {
    $id = 'selectMode'.$field->varid;
    $select = "
      <select class=\"xintervalHidden\" id=\"$id\" name=\"$id\">
        <option value=\"selectDates\" selected></option>
      </select>
    ";
    return $select;
  }

  function getModeIntervalOnly($field) {
    $id = 'selectMode'.$field->varid;
    $select = "
      <select class=\"xintervalHidden\" id=\"$id\" name=\"$id\">
        <option value=\"selectInterval\" selected></option>
      </select>
    ";
    return $select;
  }


  function getSelectNbMonth($field) {
    $select = '<li><strong>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'view', 'text').' </strong>
      <select id="nbMonths' . $field->varid . '" onchange="TZR.Xinterval.datePicker(\'' . $field->varid . '\')">';
    foreach ($this->lstMonths as $nb) {
      if ($nb == $this->nbMonth) {
        $select .= "<option value=\"$nb\" selected>$nb</option>";
      } else {
        $select .= "<option value=\"$nb\">$nb</option>";
      }
    }
    $select .= '</select><strong>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'month').'</strong></li>';
    return $select;
  }

  /// ? utilisee 
  function getPictoReset($field){
    $inputDate = 'inputDatePicker'.$field->varid;
    $inputRecap = 'recap'.$field->varid;
    $imagePath = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General', 'delete', 'global', 'url');
    return "<img src=\"$imagePath\" onclick=\"TZR.Xinterval.reset('{$field->varid}')\" alt=\"\" />";
  }
  
  function getDefaultValue() {
    return '';
  }

  function getQueryText($o){
    $dates = explode('<>',str_replace(" ","",$o->value));

    if ($dates[0] == $dates[1]){
      return sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Field_Interval_Interval', 'prefix_date'), $dates[0]);
    }else{
      return sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Field_Interval_Interval', 'date_from_to'), $dates[0], $dates[1]);
    }
  }

  /**
   * Convertit une liste de dates en liste de périodes
   * @param array $dates liste de dates
   * @return array [[startDate, endDate], ...]
   */
  static public function datesToPeriods($dates) {
    $dates= array_unique($dates);
    sort($dates);
    $periods = [];
    $startDate = $dates[0];
    for ($i = 0, $count = count($dates); $i < $count; $i++) {
      if ($dates[$i + 1] != date('Y-m-d', strtotime($dates[$i]) + 25 * 60 * 60)) {
        $periods[] = [$startDate, $dates[$i]];
        $startDate = $dates[$i + 1];
      }
    }
    return $periods;
  }
  /**
   * Convertit une liste de périodes en liste de dates
   * @param array $periods [[startDate, endDate], ...]
   * @return array of dates
   */
  static public function periodsToDates($periods) {
    if (empty($periods)) {
      return [];
    }
    $dates = [];
    $oneDay = new \DateInterval('P1D');
    $periods = array_values($periods);
    if (!is_array($periods[0])) {
      $periods = [$periods];
    }
    for ($i = 0, $count = count($periods); $i < $count; $i++) {
      $date = new \DateTime($periods[$i][0]);
      $endDate = new \DateTime($periods[$i][1]);
      while ($date <= $endDate) {
        $dates[] = $date->format('Y-m-d');
        $date->add($oneDay);
      }
    }
    sort($dates);
    return $dates;
  }

  /**
   * retourne une liste de periods
   * @param string $startDate
   * @param string $endDate
   * @param mixed $weekDays tableau ou chaine des jours de la semaine ISO-8601 / date('N')
   * @return array [[startDate, endDate], ...]
   */
  static public function periodsFromDatesAndDays($startDate, $endDate, $weekDays) {
    $dates = static::periodsToDates([[$startDate, $endDate]]);
    if (!is_array($weekDays)) {
      $weekDays = preg_split('/[,; ]+/', $weekDays, 0, PREG_SPLIT_NO_EMPTY);
    }
    foreach ($dates as $i => $date) {
      if (!in_array(date('N', strtotime($date)), $weekDays)) {
        unset($dates[$i]);
      }
    }
    return static::datesToPeriods(array_values($dates));
  }
}
