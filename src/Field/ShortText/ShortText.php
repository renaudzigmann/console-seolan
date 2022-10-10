<?php
namespace Seolan\Field\ShortText;
/// Champ texte court
class ShortText extends \Seolan\Core\Field\Field {
  public $listbox=false;
  public $listbox_limit=75;
  public $separator=',';
  public $display_format='';
  public $edit_format='';
  public $edit_format_text='';
  public $boxsize='30';
  public $quicksearchboxsize='';
  public $query_formats=array('classic','input','listbox-one','noop','autocomplete');
  public $default='';
  public $indexable=true;
  public $with_confirm=false;
  public $type='text';
  public $autocomplete=0;
  public $autocompleteRelatedFields;
  protected static $qqmaxsize = 10;
  protected static $boxmaxsize = 30;
  public static $multivaluable=true;

  function __construct($obj=NULL) {
    parent::__construct($obj) ;
  }

  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','separator'),'separator', 'text',array('size'=>1),',');

    $viewgroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','view');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','display_format'),'display_format','text',array(),NULL,$viewgroup);

    $editgroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','edit');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format'),'edit_format','text',array(), NULL, $editgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format_text'),'edit_format_text','ttext',array('compulsory'=>false), NULL, $editgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','listbox'),'listbox','boolean',array(), NULL, $editgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','boxsize'),'boxsize','text',array(), NULL, $editgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','listbox_limit'),'listbox_limit','text',array('size'=>5), NULL, $editgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','with_confirmation'), 'with_confirm', 'boolean', NULL, false, $editgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','type'),'type','text',array(), 'text', $editgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','autocomplete'),'autocomplete','boolean',array(), 0, $editgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','autocomplete_related_fields'), 'autocompleteRelatedFields', 'field',
        array('multivalued' => true, 'type' => array('\Seolan\Field\ShortText\ShortText', '\Seolan\Field\Text\Text', '\Seolan\Field\Real\Real')), null, $editgroup);
    $querygroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','query');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','indexable'), 'indexable', 'boolean', NULL, true, $querygroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','quicksearchboxsize'),'quicksearchboxsize','text',NULL,NULL, $querygroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','quicksearchmulti'),'quicksearchmulti','boolean',NULL,false, $querygroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','quicksearchmultiseparator'),'quicksearchmultiseparator','text',NULL,'newline', $querygroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','quicksearchmulticols'),'quicksearchmulticols','text',NULL, self::$qqmaxsize, $querygroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','quicksearchmultirows'),'quicksearchmultirows','text',NULL, 4, $querygroup);
    $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','quicksearchmulti_help'), 'quicksearchmulti');
    $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','quicksearchmultiseparator_help'), 'quicksearchmultiseparator');
    $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','quicksearchmulticolsrows_help'), 'quicksearchmulticols');
    $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','quicksearchmulticolsrows_help'), 'quicksearchmultirows');
    $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','with_confirmation_help'), 'with_confirm');

    $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','shorttext_display_format_comment'), 'display_format');
    $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format_comment'), 'edit_format');
  }
  function setOptions($dparam) {
    parent::setOptions($dparam);
    if (!empty($this->edit_format)){
      $editgroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','edit');
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format_text'),'edit_format_text','ttext',array('compulsory'=>true), NULL, $editgroup);
    }
    if (!$this->autocomplete) {
      $this->_options->delOpt('autocompleteRelatedFields');
    }
  }
  function my_display(&$value,&$options,$genid=false) {
    $r=$this->_newXFieldValDeferred($value,$options,$genid,'my_display');
    return $r;
  }
  /// Affichage du champ
  function my_display_deferred(&$r){
    $r->html=$r->raw;
    if(strlen(trim($this->display_format))>0) {
      $r->html=sprintf($this->display_format,$r->html,$r->html);
    }
    $r->emails=emailClean($r->html);
    
    return $r;
  }

  /// Traitement apres formulaire sur les champs calculées
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options);
    if(!empty($this->exif_source) && empty($value)) $r->raw=$this->getMetaValue($fields_complement);
    else $r->raw=$value;
    utf8_cp1252_replace($r->raw);
    // suppression des codes html et js eventuel lorsqu'on n'est pas en BO
    if(!\Seolan\Core\Shell::admini_mode() && empty($options['raw']))
      $r->raw=strip_tags($r->raw);
    $this->trace(@$options['old'],$r);
    return $r;
  }

  /// Exporte le champ en XML
  function my_export($value) {
    return '<![CDATA['.$value."]]>\n";
  }
  /*
   *  Récupère la liste des valeurs déjà existantes en base
   */
  function getDataList($query=false,$varid=NULL,$options=array()){
    $datalist = ['list'=>'', 'id'=>$varid.'list'];
    $options['limit'] = $this->listbox_limit + 1;
    $allvalues=$this->_getUsedValues(null,null,$options);

    if(count($allvalues)>$this->listbox_limit){
      $allvalues=array();
    }else{
      $allvalues=array_keys($allvalues);
      sort($allvalues,SORT_STRING);
    }

    if(!empty($allvalues)) {
      $datalist['list'] = '<datalist id="'.$datalist['id'].'">';
      foreach($allvalues as $v) {
        $datalist['list'].='<option value="'.$v.'">'.$v.'</option>';
      }
      $datalist['list'].='</datalist>';
    } else {
      $datalist['list'] = '<datalist id="'.$datalist['id'].'"></datalist>';
    }
    return $datalist;
  }
  /// Construit une select de recherche avec les valeurs en base
  function _getQuerySelectBox($fname, $fid, $value, $options, $op=null){
    $allvalues=$this->_getUsedValues(NULL,($options['select']?$options['select']:null));
    ksort($allvalues);
    $value1=htmlspecialchars($value??'');
    foreach($allvalues as $vori=>$foo){
      $v=htmlspecialchars($vori);
      $t.='<option value="'.$v.'"'.((empty($options['labelin']) && $v==$value1)?' selected':'').'>'.$vori.'</option>';
      if($v==$value1) 
	$first='<option value="'.$v.'">'.$this->label.' : '.$vori.'</option>';
    }
    if(empty($options['labelin'])) 
      $first='<option value="">----</option>';
    elseif(empty($first)) 
      $first='<option value="" selected>'.$this->label.'</option>';
    else 
      $first.='<option value="">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','n/a').'</option>';
    $t='<select '.(!empty($fid)?"id=\"$fid\"":"").' name="'.$fname.'">'.$first.$t.'</select>';
    if ($op){
      $t.='<input type="hidden" name="'.$fname.'_op" value="'.$op.'">';
    }
    return $t;
  }
  /// Recupere la liste des valeurs deja existante en base
  function &_getSelectBox($query=false,$varid=NULL,$options=array()) {
    $options['limit']=$this->listbox_limit;
    $allvalues=$this->_getUsedValues(null,null,$options);

    if(count($allvalues)>$this->listbox_limit){
      $allvalues=array();
    }else{
      $allvalues=array_keys($allvalues);
      sort($allvalues,SORT_STRING);
    }
    if(empty($allvalues)) {
      $opt='';
    }else{
      $js='TZR.addValueToShortText(\''.$varid.'\','.($this->multivalued || $query?'true':'false').',\''.$this->separator[0].'\');';
      $opt='<select ID="'.$varid.'_H" onchange="'.$js.'"><option value="">--</option>';
      foreach($allvalues as $v) {
        $opt.='<option>'.$v.'</option>';
      }
      $opt.='</select>';
    }
    return $opt;
  }

  /// Prepare l'edition du champ
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options,true);
   
    $name=$this->field;
    if(isset($options['intable'])) {
      $o=$options['intable'];
      $fname=$this->field."[$o]";
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
    } else {
      $fname=$this->field;
    }
    if(empty($this->boxsize)) $this->boxsize=static::$boxmaxsize;
    $size = min($this->boxsize, $this->fcount);
    $maxlength=$this->fcount;
    $datalist=null;
    if($this->listbox){
      if ($this->multivalued){
        // dans le cas multivalué, on garde pour le moment la select + le js
        $datalist['id'] = null;
        $datalist['list'] = $this->_getSelectBox(null, $r->varid, $options);
      } else {
        $datalist=$this->getDataList(false,$r->varid,$options);
      }
    }
    if(!isset($options['preselect']) && !\Seolan\Core\Shell::admini_mode()) {
      $datalist = null;
    }
    if(isset($options['size'])) $size=$options['size'];
    if(isset($options['maxlength'])) $size=$options['maxlength'];
    $t=htmlspecialchars($value);
    $r->raw=$value;
    $fmt1=$this->edit_format;
    $fmt='';
    $js='';
    $color=\Seolan\Core\Ini::get('error_color');
    if (! empty ($fmt1) || $this->compulsory) {
    $fmt = ' onblur="TZR.isIdValid(\'' . $r->varid . '\');"';
    if (! empty ($fmt1)) {
      $js .= 'TZR.addValidator(["' . $r->varid . '",/' . $fmt1 . '/,"' . addslashes ($this->label) . '","' . $color . '","\Seolan\Field\ShortText\ShortText"]);';
      $fmt .= static::getHtmlPattern ($this);
    }
    if ($this->compulsory && $fmt1 != '(.+)') {
      $js .= 'TZR.addValidator(["' . $r->varid . '",/(.+)/,"' . addslashes ($this->label) . '","' . $color . '","\Seolan\Field\ShortText\ShortText"]);';
      $fmt .= ' required';
    }
  }
  if (@$options ['labelin']) {
    $placeholder = ' placeholder="' . $this->label . '"';
  }
  if (($this->autocomplete || @$options ['autocomplete']) && @$options ['fmoid']) {
    if (isset ($options ['intable']))
      unset ($this->autocompleteRelatedFields);
    $url = TZR_AJAX8 . '?class=_Seolan_Field_ShortText_ShortText&function=xshorttextdef_autocomplete&_silent=1';
    $js .= 'jQuery("#' . $r->varid . '").data("autocomplete", {url:"' . TZR_AJAX8 . '?class=_Seolan_Field_ShortText_ShortText&function=xshorttextdef_autocomplete&_silent=1", params:{id:"' . $r->varid . '",moid:"' . $options ['fmoid'] . '", table:"' . $this->table . '", field:"' . $this->field . '", prefixSearch:true, emptyOk:true, relatedFields:"' . implode (',', $this->autocompleteRelatedFields) . '"}' . ($this->autocompleteRelatedFields ? ',callback:TZR.autoCompleteMultipleFields' : '') . '});TZR.addAutoComplete("' . $r->varid . '");';
  }
  $class = '';
  if ($this->compulsory)
    $class = "tzr-input-compulsory";
  if (@$this->error)
    $class .= " error_field";
  if ($class)
    $class = " class=\"$class\"";
  $r->html = '<input name="'.$fname.'" type="'.$this->type.'" '.$class.' '.((!isset($datalist['id']))?'':"list=\"{$datalist['id']}\"").' size="'.$size.'" maxlength="'.$maxlength.'" '.'value="'.$t.'" id="' . $r->varid . '" ' . $fmt . @$placeholder . '/>'.((!isset($datalist['list']))?'':$datalist['list']);
  if ($this->with_confirm) {
    $jsreg = ' new RegExp(\'^\'+document.getElementById(\'' . $r->varid . '\').value+\'$\')';
    $fmthid = ' onblur="if(typeof(TZR)!=\'undefined\') {TZR.isShortTextValid(\'' . $r->varid . '_HID\',' . $jsreg . ',\'' . $this->label . '\',\'' . $color . '\');}" ';
    $r->html .= '<br>' . '<input class="csx-input-confirm" type="' . $this->type . '" name="' . $fname . '_HID" size="'.$size.'" maxlength="' . $maxlength . '" value="' . $t . '" id="' . $r->varid . '_HID" ' . $fmthid . '/><label for="' . $r->varid . '_HID">' . \Seolan\Core\Labels::getTextSysLabel ('Seolan_Core_Field_Field', 'field_psswd_confirm') . '</label>';
    $jsreg = '{test:function(toTest){return '.$jsreg.'.test(toTest)}}';
    $js .= 'TZR.addValidator(["' . $r->varid . '_HID",' . $jsreg . ',"' . addslashes ($this->label) . '","' . $color . '","\Seolan\Field\ShortText\ShortText"]);';
  }
  if (! empty ($js)) {
    $js = '<script>' . $js . '</script>';
    $r->html .= $js;
  }
  if (@$this->errorEquals) {
    $r->html .= '<div class="error-field-comment">' . \Seolan\Core\Labels::getTextSysLabel ('Seolan_Core_Field_Field', 'field_equals') . '</div>';
  }
    return $r;
  }

  /// Prepare la recherche rapide sur le champ
  function my_quickquery($value,$options=NULL) {
    if (empty($value) && $this->isFilterCompulsory($options) && !empty(@$options['fields_complement']['query_comp_field_value'])) {
      $value = @$options['fields_complement']['query_comp_field_value'];
    }
    $r=$this->_newXFieldVal($options, true);
    if(is_array($value)) $value=implode($this->separator[0],$value);
    if(isset($value)) $t1=htmlspecialchars($value);
    else $t1='';
    $boxsize=min(static::$qqmaxsize, $this->fcount);
    if(!empty($this->quicksearchboxsize)) $boxsize=$this->quicksearchboxsize;
    $r->html='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    if($this->query_format=='autocomplete'){
      $r->html.='<input autocomplete="off" id="_INPUT'.$this->field.'_id" name="'.$this->field.'" maxlength="'.$this->fcount.'" size="'.$boxsize.'" type="text" class="tzr-link" value="'.$t1.'">';
      $url=TZR_AJAX8.'?class=_Seolan_Field_ShortText_ShortText&function=xshorttextdef_autocomplete&_silent=1';
      $r->html.='<script type="text/javascript" language="javascript">jQuery("#_INPUT'.$this->field.'_id").data("autocomplete", {url:"'.$url.'", params:{moid:"'.$options['fmoid'].'", table:"'.$this->table.'", field:"'.$this->field.'", prefixSearch:true}});TZR.addAutoComplete("'.$this->field.'_id");</script>';
    }elseif($this->query_format == 'listbox-one' && $this->listbox){
      $r->html .= $this->_getQuerySelectBox($this->field, $r->varid, $value, $options);
    }else{
      if ($this->quicksearchmulti && $this->quicksearchmultiseparator === 'newline') {
        $cols = $this->quicksearchmulticols ?? self::$qqmaxsize;
        $rows = $this->quicksearchmultirows ?? 4;
        $t1 = str_replace($this->separator[0],"\n",$t1);
        $r->html.='<textarea '.($this->isFilterCompulsory($options) ? 'required' : '').' name="'.$this->field.'" cols="'.$cols.'" rows="'.$rows.'">'.$t1.'</textarea>';
      } else {
        $r->html .= '<input '.($this->isFilterCompulsory($options) ? 'required' : '').' type="text" name="'.$this->field.'" maxlength="'.$this->fcount.'" size="'.$boxsize.'" value="'.$t1.'">';
      }
    }
    $r->raw=$value;
    return $r;
  }

  /// Prepare la recherche sur le champ
  function my_query($value,$options=NULL) {
    $format=@$options['fmt'];
    if(empty($format)) $format=@$options['qfmt'];
    if(empty($format)) $format=@$this->query_format;
    $labelin=@$options['labelin'];
    $placeholder="";
    $selectquery=@$options['select'];
    $lang=\Seolan\Core\Shell::getLangUser();
    $r=$this->_newXFieldVal($options,true);
    if(is_array($value)) $value=implode($this->separator[0],$value);
    if(isset($value)) $t1=htmlspecialchars($value);
    else $t1='';
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);

    if($format=='classic' || $format=='noop'){
      $opt=$this->_getSelectBox(true,$fname,$options);
      if($format=='classic'){
        $select_op_tpl = new \Seolan\Core\Template('Field/ShortText.select_op.html');
        $theTplData = array();
        $theRawData = $options;
        $theRawData['fname'] = $fname;
        $t = $select_op_tpl->parse($theTplData, $theRawData);
      }
      $t.= '<input type="text" id="'.$fname.'" name="'.$fname.'" maxlength="'.$this->fcount.'" size="'.min($this->fcount,static::$boxmaxsize).'" value="'.$t1.'"/>'.$opt;
    }elseif($format=='listbox-one'){
      $t = $this->_getQuerySelectBox($fname, '', $value, $options, '=');
    }elseif($format=='input'){
      if(!empty($labelin)){
        $placeholder=' placeholder="'.addslashes($this->label).'"';
      }
      $t='<input type="text" id="'.$fname.$r->varid.'" name="'.$fname.'"  maxlength="'.$this->fcount.'" size="'.min($this->fcount,static::$boxmaxsize).'" value="'.$t1.'"'.$placeholder.'/>';
    }elseif($format=='autocomplete'){
      $t='<input autocomplete="off" id="_INPUT'.$fname.$r->varid.'_id" name="'.$fname.'"  maxlength="'.$this->fcount.'" size="'.min($this->fcount,static::$boxmaxsize).'" type="text" class="tzr-link" value="'.$t1.'">';
      $url=TZR_AJAX8.'?class=_Seolan_Field_ShortText_ShortText&function=xshorttextdef_autocomplete&_silent=1';
      $t.='<script type="text/javascript" language="javascript">jQuery("#_INPUT'.$fname.$r->varid.'_id").data("autocomplete", {url:"'.$url.'", params:{moid:"'.$options['fmoid'].'", table:"'.$this->table.'", field:"'.$this->field.'", prefixSearch:true}});TZR.addAutoComplete("_INPUT'.$fname.$r->varid.'_id");</script>';
    }
    $r->html=$t;
    $r->raw=$value;
    return $r;
  }

  /// Traitement après soumission d'une recherche
  function post_query($o,$options=NULL) {
    if ($this->quicksearchmulti) {
      if ($this->quicksearchmultiseparator === 'newline') {
        if (strpos($o->value, "\r\n") === false) {
          if (strpos($o->value, "\r") === false) {
            $o->value = explode("\n", $o->value);
          } else {
            $o->value = explode("\r", $o->value);
          }
        } else {
          $o->value = explode("\r\n", $o->value);
        }
      } else {
        $o->value = explode($this->quicksearchmultiseparator, $o->value);
      }
      
      $o->value = array_map('trim', $o->value);
      $o->value = array_filter($o->value);
      
      if (count($o->value) <= 1) {
        $o->value = array_shift($o->value);
      }
      
      if (is_array($o->value)) {
        $field = str_replace($o->table.'.', '', $o->field);
        $options[$field] = $o->value;
        $options[$field.'_op'] = $o->op;
      }
    }
  
    if(!is_array($o->value)){
      $value=$o->value;
      if(!empty($this->separator)) $o->value=explode($this->separator,$value);
      if(count($o->value)==1) $o->value=$value;
    }
    return parent::post_query($o,$options);
  }

  /// Type SQL du champ
  function sqltype() {
    return 'varchar('.$this->fcount.')';
  }
  public function getDocumentationData(){
    $r = parent::getDocumentationData();
    $r->type = $r->type.' ('.$this->fcount.' char.)';
    if (!empty($this->edit_format)){
      $r->constraints[] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format').' : '.$this->edit_format;
    }
    return $r;
  }
  /// Converti le champ
  function convert($value,$src,$dst) {
    $v1=explode($src,$value);
    $value=implode($dst,$v1);
    return $value;
  }
  public function isEmpty($r){
    $isempty = parent::isEmpty($r);
    if ($isempty && property_exists($r, 'raw')){
      $isempty = ($r->raw !== 0 && $r->raw !== '0');
    }
    return $isempty;
  }
  /**
   * En mode strict, la taille de la valeur par defaut doit être <= au fcount
   */
  public static function fieldDescIsCorrect(&$field,&$ftype,&$fcount,&$forder,&$compulsory,&$queryable,&$browsable,$translatable,&$multivalued,&$published,&$target,&$label,&$options){
    if (empty($options['default']))
      return true;
    if (strlen($options['default']) > $fcount){
      \Seolan\Core\Logs::notice(__METHOD__,"default value ({$options['default']}) larger than field size ($fcount), value truncated");
      $options['default'] = mb_substr($options['default'], 0, $fcount);
    }
    return true;
  }
}

function xshorttextdef_autocomplete() {
  activeSec();
  $moid = $_REQUEST['moid'];    // Module depuis lequel on fait l'autocomplete
  $table = $_REQUEST['table'];  // Table contenant le champ
  $field = $_REQUEST['field'];
  $q = $_REQUEST['q'];
  if (empty($moid) || empty($q)) {
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  // Verifie que l'on peut utiliser l'autocomplete depuis le module (droit list/ro sur le module, table utilisée par le module)
  $mod = &\Seolan\Core\Module\Module::objectFactory(array('moid' => $moid, 'tplentry' => TZR_RETURN_DATA));
  $ok=$mod->secure('',':list') || $mod->secure('',':ro');

  if (!$ok) {
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  if (empty($table))
    $table = $mod->table;
  if (!$mod->usesTable($table)) {
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  // Recupere les valeurs
  $xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=' . $table);
  $ofield = $xset->getField($field);
  // Si le champ utilise un module particulier, on verifie les droits
  $q = trim($q);
  $suggestions = $ofield->_getUsedValues($field . ' like '.getDB()->quote('%'.$q.'%'), null, array('limit' => 10, 'order' => $field, 'relatedFields' => @$_REQUEST['relatedFields']));

  header('Content-Type:application/json; charset=UTF-8');
  if (count($suggestions) == 0) {
    if (@$_REQUEST['emptyOk']){
      die();
    }
    die(json_encode(array(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'no_result'))));
  }
  if (count($suggestions) > 50)
    die(json_encode(array(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'too_many_results'))));
  die(json_encode(array_keys($suggestions)));
}
