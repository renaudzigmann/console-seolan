<?php
namespace Seolan\Field\Boolean;

/// Implementation d'un champ booleen
class Boolean extends \Seolan\Core\Field\Field {
  const TRUE = 1;
  const FALSE = 2;

  public $TRUE = 1;
  public $FALSE = 2;

  public $query_formats=array('classic','listbox-one','checkbox');
  public $listbox=false;
  public $consent=false;
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued

  function __construct($obj=NULL) {
    parent::__construct($obj);
  }
  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','listbox'), 'listbox', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','consent'), 'consent', 'boolean', null, false, \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','specific'));
    $this->_options->setOpt('Valeur vrai', 'TRUE', 'text', ['size' => 2], self::TRUE);
    $this->_options->setOpt('Valeur faux', 'FALSE', 'text', ['size' => 2], self::FALSE);
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options);
    $arr = @$options[$this->field.'_HID'];
    if(isset($arr)) {
      $val=@$arr['val'];
      $val=((!empty($val) && (($val==$this->TRUE)||($val=='on')||($val=='true'))) ? $this->TRUE : $this->FALSE);
    } else {
      if(!empty($value) && (($value==$this->TRUE)||($value=='on')||($value=='true'))) $val=$this->TRUE;
      else $val=$this->FALSE;
    }
    $r->raw=$val;
    $this->trace(@$options['old'],$r);
    return $r;
  }
  function getDefaultValue() {
    if(!empty($this->default) && in_array($this->default, [$this->TRUE, $this->FALSE])) return $this->default;
    else return $this->FALSE;
  }
  public static function fieldDescIsCorrect(&$field,&$ftype,&$fcount,&$forder,&$compulsory,&$queryable,&$browsable,$translatable,&$multivalued,&$published,&$target,&$label,&$options){
    
    if (isset($options['FALSE']) && !preg_match('/^[0-9]*$/', $options['FALSE']))
      return false;
    if (isset($options['TRUE']) && !preg_match('/^[0-9]*$/', $options['TRUE']))
      return false;
    if (isset($options['default']) && !preg_match('/^[0-9]*$/', $options['default']))
      return false;
    return true;
  }
  function my_export($value) {
    return $this->getLabelFromValue($value);
  }
  public function isEmpty($r){
    return false;
  }

  function my_getJSon($o, $options) {
    return $o->raw==$this->TRUE;
  }

  function my_display(&$value,&$options,$genid=false) {
    if(!$value) $value=$this->FALSE;
    $r=parent::my_display($value,$options,$genid);
    $r->valid=((int)$r->raw===$this->TRUE);
    return $r;
  }
  function my_display_deferred(&$r){
    $r->html=$this->getLabelFromValue($r->raw);
    return $r;
  }
  function my_browse(&$value,&$options,$genid=false) {
    if(!$value) $value=$this->FALSE;
    return parent::my_browse($value,$options,$genid);
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options);
    $r->varid = 'v'.$this->field.uniqid();
    if (empty($value)) {
      if (!empty($options['default'])) {
        $value=$options['default'];
      } else {
        $value=$this->getDefaultValue();
      }
    }
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
    $edit='';
    $consentMode  = $this->consent && !\Seolan\Core\Shell::admini_mode();
    if($this->listbox && !$consentMode){
      $edit='<select name="'.$fname.'">';
      foreach($this->getBooleanFormat() as $v=>$l)
        $edit .= '<option value="'.$v.'"'.(($v==$value)?' selected':'').'>'.$l.'</option>';
      $edit.='</select>';
    }else{
      // un champ logique + consentement doit être coché donc "required"
      $edit.='<div class="checkbox"><label><input id="'.$r->varid.'" type="checkbox" name="'.$hiddenname.'[val]"'.($value==$this->TRUE?' checked':'').' '.($consentMode?'required':'').'/></label></div>';
      $edit.='<input type="hidden" name="'.$fname.'" value="'.$value.'"/>';
      $edit.='<input id="'.$r->varid.'_pres" type="hidden" name="'.$hiddenname.'[pres]" value="1"/>';
      if ($consentMode){
	$edit.="
	  <script type='text/javascript'>
	  if (typeof(TZR)!='undefined'){
	    TZR.addValidator(['{$r->varid}',null,'".addslashes($this->label)."','color','Consent']);
	    jQuery('#{$r->varid}').on('blur', function(e) {
		TZR.isIdValid('{$r->varid}');
	      });
	  }
	  </script>
	  ";

      }
    }
    $r->html = $edit;
    $r->raw = $value;
    return $r;
  }
  
  /// Prepare la recherche rapide sur le champ
  function my_quickquery($value,$options=NULL) {
    $r=$this->_newXFieldVal($options);
    if(is_array($value)) $value=implode($value);
    $edit='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    $edit.='<select name="'.$this->field.'" '.($this->isFilterCompulsory($options) ? 'required' : '').'>';
    if (!$this->isFilterCompulsory($options)) {
      $edit.='<option value="">---</option>';
    }
    foreach ($this->getBooleanFormat() as $v => $l)
      $edit.='<option value="'.$v.'"'.(((string)$v===$value)?' selected':'').'>'.$l.'</option>';
    $edit.='</select>';
    $r->html=$edit;
    $r->raw=$value;
    return $r;
  }

  protected function getBooleanFormat() {
    $list = \Seolan\Core\Lang::getLocaleProp('boolean_format');
    return [$this->TRUE => $list[static::TRUE], $this->FALSE => $list[static::FALSE]];
  }

  function my_query($value,$options=NULL) {
    $p = new \Seolan\Core\Param($options);
    $format=$p->get('fmt');
    if(empty($format)) $format=$p->get('qfmt');
    if(empty($format)) $format=$this->query_format;
    $labelin=$p->get('labelin');
    $r=$this->_newXFieldVal($options,true);
    $edit="";
    if(is_array($value)) {
      $vs=array_values($value);
      $value=$vs[0];
    }
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    if($format=='classic'){
      $edit='<div class="radio"><label><input name="'.$fname.'" type="radio" class="radio" id="'.$r->varid.'-U" value=""'.((empty($value))?' checked':'').'/>';
      $edit.=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','unspecified').'</label></div> ';
      foreach($this->getBooleanFormat() as $v=>$l){
	$edit.='<div class="radio"><label><input name="'.$fname.'" type="radio" class="radio" id="'.$r->varid.'-'.$v.'"'.(($v==$value)?' checked':'').' '.
	  'value="'.$v.'"/>'.$l.'</label></div>';
      }
    }elseif($format=='listbox-one'){
      foreach($this->getBooleanFormat() as $v=>$l){
	$edit.='<option value="'.$v.'">'.$l.'</option>';
	if($v==$value) $first='<option value="'.$v.'" selected>'.$this->label.' : '.$l.'</option>';
      }
      if(empty($labelin)) $first='<option value="">----</option>';
      elseif(empty($first)) $first='<option value="" selected>'.$this->label.'</option>';
      else $first.='<option value="">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','n/a').'</option>';

      $edit='<select name="'.$fname.'" id="'.$fname.'">'.$first.$edit.'</select>';
    }elseif($format=='checkbox'){
      $edit.='<input id="'.$r->varid.'" type="checkbox" value="'.$this->TRUE.'" name="'.$fname.'"'.($value==$this->TRUE?' checked':'').'>';
      if(!empty($labelin)) $edit.='<label for="'.$r->varid.'">'.$this->label.'</label>';
      $edit='<div class="checkbox">'.$edit."</div>";
    }
    $r->html=$edit;
    $r->raw=$value;
    return $r;
  }

  /// Sous fonction pour l'import de données vers une table
  function my_import($value, $specs=null){
    $yes=array('OUI','YES','Y','O','1','VRAI','TRUE');
    if(in_array(strtoupper($value),$yes) || $value===true) $ret=$this->TRUE;
    else $ret=$this->getDefaultValue();
    return array('value'=>$ret,'message'=>'');
  }

  function sqltype() {
    return 'tinyint'; 
  }

  /// Retourne le label d'une valeur
  function getLabelFromValue($v){
    if(empty($v)) $v=$this->FALSE;
    return $this->getBooleanFormat()[$v];
  }
 
  /// Recupere le type du champ dans un webservice (name : type xml, descr : description du type pour l'ajour d'une type complexe)
  function getSoapType(){
    return array('name'=>'xsd:int');
  }
  /// Recupere la valeur formattée pour le service SOAP
  function getSoapValue($r){
    if($r->raw==$this->TRUE) return 1;
    else return 0;
  }

  /**
   * génération de la documentation pour le champ
   */
  public function getDocumentationData(){
    $r=parent::getDocumentationData();
    $default = $this->getDefaultValue();
    if (isset($default) && !$this->isEmpty($this->_newXFieldValDeferred($default, ($foo=null)))) {
      if($default==$this->TRUE) $default="true";
      elseif($default==$this->FALSE) $default="false";
      else $default="undefined";
      $r->constraints['default'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'default').' : "'.$default.'"';
    }
    if (\Seolan\Core\Json::hasInterfaceConfig()){
      if(($alias=\Seolan\Core\Json::getFieldAlias(NULL, $this->field, $this->table))!=$this->field) {
        $r->constraints[]='Alias JSON : '.$alias;
      }
    }
    return $r;
  }
}

