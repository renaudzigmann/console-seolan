<?php
namespace Seolan\Field\Lang;
  /**
   * une langue de la console
   * la liste provient de TZR_LANGUAGES par defaut (voir langlist())
   * la valeur stockée en base est le 'code langue', qui doit rester compatible
   * avec le champ LANG et la gestion des langues en général
   */
class Lang extends \Seolan\Core\Field\Field{
  public $displayMode = 'text';
  protected $separator = null;
  function __construct($ar){
    parent::__construct($ar);
    if ($this->multivalued){
      $this->separator = ',';
    }
  }
  function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('multiseparator');
    $this->_options->delOpt('multiseparatortext');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','display_format'),'displayMode','list',
                            array('values'=>array('text','code','icon'),
                                  'labels'=>array(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','label'),
						  \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','code'), 
						  \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','icon')
						  )
				  ),
                                    NULL);
  }
  function quickquery($value, $options=NULL){
    if (empty($value) && $this->isFilterCompulsory($options) && !empty(@$options['fields_complement']['query_comp_field_value'])) {
      $value = @$options['fields_complement']['query_comp_field_value'];
    }
    if(empty($options['query_format'])) $options['query_format']=\Seolan\Core\Field\Field::QUICKQUERY_FORMAT;
    $r=$this->my_query($value, $options);
    $r->html.='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    return $r;
  }
  function my_query($value,$options=NULL) {
    return $this->my_edit($value, $options);
  }
  function my_display_deferred(&$r){
    $langDesc = \Seolan\Core\Lang::get($r->raw);
    if (!empty($langDesc)){
      $r->html = $this->formatDisplay($langDesc, $this->displayMode);
    } else {
      $r->html = '...';
    }
  }
  function my_edit(&$value,&$options,&$fields_complement=null){
    $p=new \Seolan\Core\Param($options,array());
    $r=$this->_newXFieldVal($options, true);
    $r->varid=uniqid('v');
    if(isset($options['intable'])){
      $o=$options['intable'];
      $fname=$this->field.'['.$o.']';	
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
    } elseif($this->multivalued){
      $fname=$this->field.'[]';	
    } else {
      $fname=$this->field;
    }	
    if ($this->multivalued){
      $multivalued = " multiple size=\"{$this->fcount}\" ";
    } else {
      $multivalued = '';
    }
    if ($this->multivalued){
      $r->raw = preg_split('@['.$this->separator.']@',trim($value));
    } else {
      $r->raw=  $value;
    }
    $r->html = "<select ".(@$options['query_format'] === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '')." name=\"$fname\" id=\"{$r->varid}\" {$multivalued}>";
    if (!$this->compulsory){
      if (@$options['query_format'] !== \Seolan\Core\Field\Field::QUICKQUERY_FORMAT || !$this->isFilterCompulsory($options)) {
        $r->html .= "<option value=\"\">----</option>";
      }
    }
    foreach($this->langList($options) as $langDesc){
      if ((is_array($r->raw) && in_array($langDesc['code'], $r->raw)) || $langDesc['code'] == $value){
	$selected = ' selected ';
      } else {
	$selected = '';
      }
      $format = $this->displayMode!='icon'?$this->displayMode:'text';
      $r->html .= "<option $selected value=\"{$langDesc['code']}\">{$this->formatDisplay($langDesc, $format)}</option>";
    }
    $r->html .= '</select>';
    return $r;
  }
  /// on doit pas retourner de tableau aux fonctions procInput et procEdit
  function post_edit($value, $options=null, &$fields_complement=null) {
    $r=$this->_newXFieldVal($options);
    if (is_array($value)){
      $r->raw = implode(',', $value);
    } else {
      $r->raw = $value;
    }
    return $r;
  }
  /**
   * code, libelle ou icone + libelllé
   * a partir du 'langdesc' (locales)
   */
    protected  function formatDisplay($langDesc, $displayMode){
      switch($displayMode){
          case 'text':
              $f = $langDesc['text'];
              break;
          case 'code':
              $f = $langDesc['code'];
              break;
          case 'icon':
              $f = str_replace('ALT', 'title', $langDesc['long']);
              break;
      }
      return $f;
  }
  /**
   * liste des langues possibles
   * -> restriction possible (alternate_list) 
   * -> la valeur en cours reste valide
   */
    protected function langList($options){
    $langs = [];
    $altlist = [];
    if (isset($options['alternate_list'])){
      $altlist = $options['alternate_list'];
    }
    foreach($GLOBALS['TZR_LANGUAGES'] as $code=>$lang){
      if (@count($altlist)>0 && !in_array($code, $altlist)){
	continue;
      }
      $langs[] = \Seolan\Core\Lang::get($code);
    }
    return $langs;
  }
  /// cas multivalués
  function sqltype() {
    return 'text';  
  }  
}
