<?php
namespace Seolan\Field\GeodesicCoordinates;
/*
Coordonnees geodesiques ? geographiques
Position en lat lng, dans le systeme ... utilisé (WGS84 pour google) d'un POI (cf http://en.wikipedia.org/wiki/Point_of_Interest)
Structures des données brutes
latitude (float);longitude (float);M/A(type);UPD;accuracy
Avec : 
- accuracy : niveau de precision de la reponse du geocodeur pour les champs automatiques, quand celui ci la fournit
voir la doc google pour le moment et xmodmap locale
*/
class GeodesicCoordinates extends \Seolan\Core\Field\Field{
  function __construct($obj=NULL) {
    parent::__construct($obj) ;
  }
  // converti des coordonnées 
  //
  private function dd2dms($dd, $l1, $l2){
    if (empty($dd))
	return '';
    if ($dd < 0){
      $dd = $dd*-1;
      $ll = $l2;
    } else {
      $ll = $l1;
    }
      
    $d = (int) ($dd);
    $m = (int) (($dd - $d) * 60);
    $s = (((($dd - $d) * 60) - $m) * 60);
    return sprintf("% 3d° %02d' %02.2f'' %s", $d, $m, $s, $ll);
  }

  function my_browse(&$value,&$options,$genid=false) {
    $options['intable'] = 1;
    return parent::my_browse($value,$options,true);
  }
  
  function my_display(&$value,&$options,$genid=false) {
    return parent::my_display($value,$options,true);
  }

  function my_display_deferred(&$r){
    $options=&$r->options;
    $value=&$r->raw;
    $uniqid = $r->varid;
    if (!empty($value)){
      list($lat, $lng, $type, $accuracy, $upd, $srs, $srsvalue) = self::explode($value);
      $evalue = $lat.';'.$lng;
      $vvalue = $this->dd2dms($lat, 'N', 'S').'  '.$this->dd2dms($lng, 'E', 'W');
      if (!empty($srsvalue)){
        $vvaluewgs = $vvalue;
        $vvalue = $srsvalue;
      }
    } else {
      $srs = $srsvalue = NULL;
      $vvaluewgs = '';
      $evalue = '';
      $vvalue = '';
    }
    // module de geocodage associe
    if (!isset($this->gmoid) || empty($this->gmoid)){
      $r->html = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'empty');
      return $r;
    }
    $modmap = \Seolan\Core\Module\Module::objectFactory($this->gmoid);
    $fs = $modmap->getFieldSetup(array('fname'=>$this->field, 'ftable'=>$this->table));
    $viewLabel=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','view', 'text');
    $updLabel=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','last_update', 'text');
    $viewUrl = $modmap->getGeoViewUrl(array($this->table, $this->field));
    $viewUrl2 = addslashes($viewUrl);
    $accuracyerrorcolor='#ff0000';
    $accuraciesLevels = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'accuracieslevels');
    $errorstyle = '';
    if ($fs['autogc'] && $type != 'M' && isset($accuraciesLevels[$accuracy])){
      if ($accuracy <= $fs['minaccuracy']){
        $accuracyLevel = '<span style="color:'.$accuracyerrorcolor.'">'.$accuraciesLevels[$accuracy].'</span>'; 
        $errorstyle='color:'.$accuracyerrorcolor.';';
      }  else {
        $accuracyLevel =  $accuraciesLevels[$accuracy];
      }
      $accuracyHtml  = '<br><table class="list2"><tr><th colspan="2" style="text-align:center">'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'geocodageauto', 'text').'</th></tr><tr><th>'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'accuracy', 'text').'</th><td>'.$accuracyLevel.'</td></tr>';
      $accuracyHtml .= '<tr><th>'.$updLabel.'</th><td>'.$upd.'</td></tr></table>';
    } else {
      $accuracyHtml = '';
    }
    $srs = $this->getSrs($value, $fs);
    $r->srsvalue = $srsvalue;
    $r->srs = $srs;
    $r->textwgs = $vvaluewgs;
    $r->text = $vvalue;
    $r->upd = $upd;
    $r->accuracy = $accuracy;
    $r->type = $type;
    $r->lat = $lat;
    $r->lng = $lng;
    if ('' != $vvaluewgs){
      $vvaluewgshtml = ' ('.$vvaluewgs.')';
    }
    $jfieldoptions = "{srs:'{$r->srs}'}";
    if ($vvalue != ''){
      if (isset($options['intable'])){
	// display simple
$html=<<<EOT
  <div><a style="{$errorstyle}" title="{$viewLabel}{$vvaluewgshtml}" href="javascript:void(0);" onclick="TZR.geodesic.openGeoView('{$evalue}', '{$viewUrl}', '{$this->field}', '{$uniqid}', '{$r->table}', {$jfieldoptions})">{$vvalue}</a></div>
EOT;
      } else {
      // display complet
$html=<<<EOT
  <div style=""><a title="{$viewLabel}{$vvaluewgshtml}" href="javascript:void(0);" onclick="TZR.geodesic.openGeoView('{$evalue}', '{$viewUrl}', '{$this->field}', '{$uniqid}', '{$r->table}', {$jfieldoptions})">{$vvalue}</a>{$accuracyHtml}</div>
EOT;
      }
    }else{
      $html=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'empty');
    }
    $r->html = $html;
    return $r;
  }
  // gerer le unchanged / upd 
  // en particuler pour les champs geocodés automatiquement
  //
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options,true);
    $upd = '';
    if (is_array($value)){
      $latlng2 = $value['dms'];
      $unchanged = $value['unchanged'];
      if ($unchanged == 'NO'){
	$upd = $options['old']->upd;
      }
      if (isset($value['autogc'])){
	$autogc = 'A';
      } else {
	$autogc = 'M';
      }
      $accuracy = $value['accuracy'];
      $latlng = $value['latlng'];
    } else {
      $latlng = $value;
      $accuracy='N/A';
      $autogc = 'M';
    }
    if (empty($latlng)) $latlng=';';
    if ($latlng==';') $accuracy='';
    if (empty($upd)) $upd = date('Y-m-d H:i:s');

    $r->raw=$latlng.';'.$autogc.';'.$accuracy.';'.$upd;
    return $r;
  }
  // edition du champ
  //
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options, true);
    $uniqid = $r->varid;
    if (isset($options['intable'])){
      $fname=$this->field."[{$options['intable']}]";
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
    } else {
      $fname=$this->field;
    }
    // module de geocodage associe
    if (!isset($this->gmoid) ||empty($this->gmoid)){
      $r->html = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'empty');
      return $r;
    }
    $modmap = \Seolan\Core\Module\Module::objectFactory($this->gmoid);
    $fs = $modmap->getFieldSetup(array('fname'=>$this->field, 'ftable'=>$this->table));      
    $searchUrl = addslashes($modmap->getGeoSearchUrl(array($this->table, $this->field)));

    $editLabel=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','edit', 'text');
    $selectLabel=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','select', 'text'); 
    $clearLabel=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','clear', 'text');
    $autoLabel = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map','geocodageauto', 'text');
    $accuracyLabel = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'accuracy', 'text');
    $updLabel = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'last_update', 'text');
    $ecolor = \Seolan\Core\Ini::get('error_color');
    $accuracyerrorcolor = '#ff0000';

    if (!empty($value)){
      list($lat, $lng, $type, $accuracy, $upd, $srs, $srsvalue) = self::explode($value);
      $evalue = $lat.';'.$lng;
      $vvalue = $this->dd2dms($lat, 'N', 'S').' '.$this->dd2dms($lng, 'E', 'W');
      if (!empty($srsvalue)){
        $vvaluewgs = $vvalue;
        // a venir $vvalue = $srsvalue;
      }
    } else {
      $evalue='';
      $accuracy = 'N/A';
      $type = $fs['autogc']?'A':'M';
      $vvalue = '';
      $accuracy = 'N/A';
      $lat = $lng = '';
      $upd = '';
      $srs = $srsvalue = NULL;
      $vvaluewgs = '';
    }
    if ($type == 'A'){
      $autochecked = 'CHECKED';
    } else {
      $autochecked = '';
    }
    if ($accuracy == 'N/A')
      $upd = '';
    $srs = $this->getSrs($value, $fs);
    $r->srsvalue = $srsvalue;
    $r->srs = $srs;
    $r->textwgs = $vvaluewgs;
    $r->upd = $upd;
    $r->text = $vvalue;
    $r->accuracy = $accuracy;
    $r->type = $type;
    $r->lat = $lat;
    $r->lng = $lng;
    $r->raw=$lat.';'.$lng.';'.$type.';'.$accuracy.';'.$upd;
    $r->varid='dms'.$uniqid;
    $accuraciesLevels = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'accuracieslevels');
    if ($fs['autogc'] && $type != 'M' && isset($accuraciesLevels[$accuracy])){
      if ($accuracy <= $fs['minaccuracy']){
        $accuracyLevel = '<span style="color:'.$accuracyerrorcolor.'">'.$accuraciesLevels[$accuracy].'</span>'; 
      }  else {
        $accuracyLevel =  $accuraciesLevels[$accuracy];
      }
    } else {
      $accuracyLevel = '';
      $upd = '';
    }
    $jfieldoptions = "{srs:'{$r->srs}'}";
    $fmt="[ ]*[0-9]{1,2}° [0-9]{1,2}' [0-9]{1,2}\.[0-9]{0,3}'' [NS]{1}";
    $fmt.="[ ]+[0-9]{1,3}° [0-9]{1,2}' [0-9]{1,2}\.[0-9]{0,3}'' [WOE]{1}";
    $blur="TZR.isIdValid('dms{$uniqid}');";
    $blur="onblur=\"if(typeof(TZR)!='undefined') { TZR.geodesic.dms2dd('dms{$uniqid}', '$uniqid'); $blur }\" ";
    if($this->compulsory){
      $t1.="TZR.addValidator(['dms{$uniqid}',/^{$fmt}$/,'".addslashes($this->label)."','$ecolor','\Seolan\Field\ShortText\ShortText']);";
      $jsval="<script type=\"text/javascript\">if(typeof(TZR)!='undefined') { $t1 }</script>";
    } else {
      $t1="TZR.addValidator(['dms{$uniqid}',/^[ ]*$|^{$fmt}$/,'".addslashes($this->label)."','$ecolor','\Seolan\Field\ShortText\ShortText']);";
      $jsval="<script type=\"text/javascript\">if(typeof(TZR)!='undefined') { $t1 }</script>";
    }
$html=<<<EOT
  <input type="hidden" value="NO" id="{$uniqid}_unchanged" name="{$fname}[unchanged]"/>
  <input type="hidden" value="{$evalue}" id="{$uniqid}" name="{$fname}[latlng]"/>
  <input type="text" style="" size="35" value="{$vvalue}" id="dms{$uniqid}" name="{$fname}[dms]" $blur/>&nbsp;
  <a href="javascript:void(0)" title="{$editLabel}" onclick="TZR.geodesic.openGeoSearch('{$searchUrl}', '{$this->field}', '{$uniqid}', '{$r->table}', {$jfieldoptions})">{$selectLabel}</a>&nbsp;
  <a href="javascript:void(0)" title="{$clearLabel}" onclick="TZR.geodesic.clear('{$uniqid}');/*document.getElementById('{$uniqid}').value='';document.getElementById('dms{$uniqid}').value=''*/">{$clearLabel}</a><br>
EOT;
  if ($fs['autogc']){
$html.=<<<EOT
  <table class="compact"><tr><td>{$autoLabel}</td><td><input class="checkbox" type="checkbox" value="2" {$autochecked} id="{$uniqid}_autogc" onclick="if (typeof(TZR) != 'undefined'){TZR.geodesic.geocodeauto(this, '{$uniqid}')}" name="{$fname}[autogc]"/></td></tr>
EOT;
  if ($type != 'M'){
$html.=<<<EOT
<tr id="{$uniqid}_b1"><td>{$accuracyLabel}</td><td><span id="{$uniqid}_accuracy1">{$accuracyLevel}</span><input type="hidden" value="{$accuracy}" name="{$fname}[accuracy]" id="{$uniqid}_accuracy2"/></td></tr>
<tr id="{$uniqid}_b2"><td>{$updLabel}</td><td><span id="{$uniqid}_upd">{$upd}</span></td></tr>
EOT;
  } else {
$html.=<<<EOT
<tr style="display:none" id="{$uniqid}_b1"><td>{$accuracyLabel}</td><td><span id="{$uniqid}_accuracy1">{$accuracyLevel}</span><input type="hidden" value="{$accuracy}" name="{$fname}[accuracy]" id="{$uniqid}_accuracy2"/></td></tr>
<tr style="display:none" id="{$uniqid}_b2"><td>{$updLabel}</td><td><span id="{$uniqid}_upd">{$upd}</span></td></tr>
EOT;
  }
  $html.= '</table>';
  }
  if (!$fs['autogc']){
    $html .= "<input type=\"hidden\" value=\"2\" id=\"{$uniqid}_autogc\" name=\"{$fname}[autogc]\"/>";
    $html.="<span style=\"display:none\" id=\"{$uniqid}_accuracy1\"></span><input type=\"hidden\" name=\"{$fname}[accuracy]\" value=\"{$accuracy}\" id=\"{$uniqid}_accuracy2\"/>";
  }
$html.=<<<EOT
  {$jsval}
EOT;
    $r->html = $html;
    return $r;
  }
  function post_query($o, $options){
    $value=$o->value;
    if ($value == 'empty'){
      $fn = $o->field;
      $o->rq = "isnull($fn) or  $fn='' or $fn like ';;_;%;%'";
      return;
    } else if($value == 'manual'){
      $o->op = 'like';
      $o->value = '%;%;M;%;%';
    } else if($value == 'auto'){
      $o->op = 'like';
      $o->value = '%;%;A;%;%';
    } else if (!empty($value)){ // accuracy
      $o->op = 'like';
      $o->value = '%;%;_;'.$o->value.';%';
    } 
    return parent::post_query($o, $options);
  }
  function my_query($value,$options=NULL) {
    $lang=\Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options,true);
    if(is_array($value)) $value=implode($this->separator[0],$value);
    if(isset($value)) $t1 = htmlspecialchars($value);
    else $t1=NULL;
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $accuraciesLevels = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'accuracieslevels');
    $t='<input type="hidden" name="'.$fname.'_op" value=""/><select '.($options['query_format'] === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' id="'.$fname.'" name="'.$fname.'">';
    if ($options['query_format'] !== \Seolan\Core\Field\Field::QUICKQUERY_FORMAT || !$this->isFilterCompulsory($options)) {
      $t.='<option '.($t1==NULL?'SELECTED':'').' value=""></option>';
    }
    $t.='<optgroup label="'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'general').'">';
    $t.='<option '.($t1=='empty'?'SELECTED':'').' value="empty">'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'empty').'</option>';
    $t.='<option '.($t1=='manual'?'SELECTED':'').' value="manual">'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'manual').'</option>';
    $t.='<option '.($t1=='auto'?'SELECTED':'').' value="auto">'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'geocodageauto').'</option>';
    $t.='</optgroup>';
    $t.='<optgroup label="'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Map_Map', 'accuracy').'">';
    foreach($accuraciesLevels as $ac=>$al){
      $t.='<option '.($t1===$ac?'SELECTED':'').' value="'.$ac.'">'.$al.'</option>';
    }
    $t.='<optgroup></SELECT>';
    $r->html=$t;
    return $r;
  }
  function my_quickquery($value, $options=NULL){
    $r=$this->my_query($value, $options);
    $r->html.='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    return $r;
  }
  // Ecriture dans un csv
  function writeCSV($o,$textsep){
    return $textsep.$o->raw.$textsep;
  }
  // Ecriture dans un xls
  function writeXLS($xl,$i,$j,$value,$format=0,$ss=null) {
    $v=$value->raw;
    convert_charset($v,TZR_INTERNAL_CHARSET,'UTF-8');
    $xl->setCellValueByColumnAndRow($j,$i,$v);
    if(is_array($format)) $xl->getStyleByColumnAndRow($j,$i)->applyFromArray($format);
  }

  function my_import($value,$specs=null){
    $r=array();
    list($lat,$lng,$r['autogc'],$r['accuracy'],$upd,$srs,$srsvalue) = \Seolan\Field\GeodesicCoordinates\GeodesicCoordinates::explode($value);
    $srs = $this->getSrs($value);
    if ($srs != \Seolan\Module\Map\Map::$defaultSRS){
      // ... module contient les conversions ...
      //      \Seolan\Module\Map\Map::convertArray($fromSyst, $toSyst, $in);
    }
    // ... ajouter le srs et la données importée ... voir mydisplay
    $r['latlng']=$lat.';'.$lng;
    return array('message'=>'','value'=>$r);
  }
  /**
   * lat, lng, autogc, accuracy, upd, srs, originalvalue
   */
  static function explode($value){
    return array_pad(explode(';', $value), 7, NULL);
  }
  /**
   * SRS du champ : dans la donnée, dans le champ, dans la couche
   */
  function getSrs($value, $fieldsetup=NULL){
    $vals = explode(';', $value);
    if (!empty($vals[5])){
      return $vals[5];
    }
    if (!empty($this->srsoid)){
      return $this->srsoid;
    }
    if ($fieldsetup != NULL && !empty($fieldsetup['coordsrs'])){
      return $fieldsetup['coordsrs'];
    }
    return \Seolan\Module\Map\Map::$defaultSRS;
  }
  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Module geocodage', 'gmoid', 'module');
  }
  function sqltype() {
    return 'varchar(124)';
  }
}
?>
