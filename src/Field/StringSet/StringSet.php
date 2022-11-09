<?php
namespace Seolan\Field\StringSet;
/// champ ensemble de valeurs
class StringSet extends \Seolan\Core\Field\Field {
  public $usedefault=true;
  public $checkbox=true;
  public $checkbox_limit=30;
  public $checkbox_cols=3;
  public $doublebox=false;
  public $query_formats=array('classic','listbox-one','listbox');
  public $value_set = array();
  public $advanceeditbatch=true;
  public $queryInitialLabel = '';

  function __construct($obj=NULL) {
    parent::__construct($obj);
    $this->value_set = $this->getChoices();
  }
  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','usedefault'), "usedefault", "boolean",array());
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','checkbox'), "checkbox", "boolean",array());
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','checkbox_limit'), "checkbox_limit", "text",array());
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','checkbox_cols'), "checkbox_cols", "text",array());
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','doublebox'), 'doublebox', 'boolean',NULL,false);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query_initial_label'), 'queryInitialLabel', 'ttext',array(), '', 'Rechercher');
  }
  function get_sqlValue($value) {
    // Fabrique une valeur de colonne pour SQL à partir d'une valeur issue de PHP
    // $value est ici un tableau. 1 seul valeur si champ single value, n valeurs si multi value

    if ( !is_array($value) ) return $value;
    $nb = count($value);
    if ( $nb == 0 ) return '';
    if ( (!$this->get_multivalued()) || ($nb==1) ) {
      return $value;
    }
    else {
      $val = '||';
      for ($i=0;$i<$nb;$i++) {
	$val .= $value[$i] . '||';
      }
      return $val;
    }
  }

  function my_getJSon($o, $options) {
    $ret = [];
    // inclusion directe
    if ($options['follow']) {
      $property = $options['property'] ?? 'html';
      if (!$this->multivalued) {
        return $o->$property;
      }
      foreach ($o->collection as $_o) {
        $ret[] = $_o->$property;
      }
      return $ret;
    }
    // liste de référence
    $prefix = \Seolan\Core\Json::getSetsPrefix($options['fmoid'], $this->field);
    $setAlias = \Seolan\Core\Json::getSetsAlias();
    if ($this->multivalued) {
      foreach ($o->collection as $_o) {
        $ret[] = ['type' => $setAlias, 'id' => $prefix . $_o->raw];
        \Seolan\Core\Json::registerSet($this->table, $this->field, $_o->raw, $prefix);
      }
    } else {
      $ret = ['type' => $setAlias, 'id' => $prefix . $o->raw];
      \Seolan\Core\Json::registerSet($this->table, $this->field, $o->raw, $prefix);
    }
    return $ret;
  }

  function my_display_deferred(&$r){
    if(!empty($r->options['nofollowlinks']))
      return $r;
    if($r->raw=='') return $r;
    if($r->raw=='Foo') return $r;
    $v1=addslashes($r->raw);
    $text=$this->getTextFromSoid($v1);
    // erreur pas de chaine trouvee pour ce numero
    if(!$text) {
      return $r;
    }
    $r->html=$text;
    return $r;
  }

  function my_export($value) {
    if($value=='') return '';
    $flang=$_SESSION['LANG_DATA'];
    $v1=addslashes($value);
    $text=$this->getTextFromSoid($v1,$flang);
    return $text;
  }

  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $query_format=@$options['query_format'];
    $fmt=@$options['fmt'];
    if($query_format){
      if(empty($fmt)) $fmt=@$options['qfmt'];
      if(empty($fmt)) $fmt=$this->query_format;
    }
    if(isset($options['intable'])) {
      $o=$options['intable'];
      $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field).'['.$o.']';
      $hiddenname=(isset($options['fieldname'])?$options['fieldname']:$this->field).'_HID['.$o.']';
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
      $hiddenname=(isset($options['fieldname'])?$options['fieldname']:$this->field).'_HID';
    }
    // Recupere les options
    $msg = $this->getChoices();
    $soids = $this->getChoicesOids($options, $msg);
    $nb = count($soids);

    $r=$this->_newXFieldVal($options);
    $r->raw=$value;
    $checkbox=(($nb<=$this->checkbox_limit || $fmt=='checkbox') && $fmt!='listbox' && $fmt!='listbox-one' && $this->checkbox && empty($options['simple']));
    $doublebox=($this->multivalued && $this->doublebox && !isset($options['qfmt']));

    if($checkbox) $this->getCheckboxes($value,$options,$r,$soids,$fname,$hiddenname,$msg);
    elseif($doublebox) $this->getDoubleSelect($value,$options,$r,$soids,$fname,$hiddenname,$msg);
    else $this->getSelect($value,$options,$r,$soids,$fname,$hiddenname,$msg);

    // Ajout de l'operateur pour la recherche multiple
    if($query_format && $this->get_multivalued() && $fmt!='listbox-one'){
      $varid=uniqid('v');
      $op=$options['op'];
      $r->html='<select name="'.$fname.'_op">
        <option value="AND"'.($op==='AND'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_allterms').'</option>
        <option value="OR"'.($op==='OR'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_leastaterm').'</option>
        <option value="NONE"'.($op==='NONE'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_noterm').'</option>
        <option value="EXCLUSIVE"'.($op==='EXCLUSIVE'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_onlyterms').'</option>
        </select><br>'.$r->html;
    }
    return $r;
  }

  /**
   * @return array of oid: label
   */
  protected function getChoices() {
    $flang = \Seolan\Core\Shell::getLangData();
    return self::_getCache()[$flang][$this->table][$this->field]??null;
  }

  /**
   * @param $options
   * @param $msg
   * @return array: Liste des valeurs possibles
   */
  protected function getChoicesOids($options, $msg) {
    if (isset($options['select']))  {
      $usedValues = $this->_getUsedValues(null,$options['select']);
      //ordonnées selon SORDER
      $usedValues = array_keys($usedValues);
      if($msg)
          $usedValues = array_intersect(array_keys($msg), $usedValues);
      return $usedValues;
    }
    if (isset($options['filter']))  {
      $soids = getDB()->fetchCol('SELECT DISTINCT SOID FROM SETS WHERE STAB=? AND FIELD=? AND ('.$options['filter'].') ORDER BY SORDER', array($this->table,$this->field));
    } else {
      $soids = array_keys($msg);
    }
    return $soids;
  }

  // Edition du champ sous la forme d'une double liste déroulante
  function getDoubleSelect(&$value,&$options,&$r,&$soids,$fname,$hiddenname,$msg){
    $oidcollection=$collection=$opts=array();
    $varid=getUniqID('v');
    $unselectedname=preg_replace('/^([^\[]+)/','$1_unselected',$fname);
    $unselected='<select name="'.$unselectedname.'" size="5" multiple ondblclick="TZR.doubleAdd('.
      'this.form.elements[\''.$unselectedname.'\'],this.form.elements[\''.$fname.'[]\'],false)" class="doublebox">';
    $selected='<select name="'.$fname.'[]" size="5" multiple id="'.$varid.'"  ondblclick="TZR.doubleAdd(this.form.elements[\''.$fname.'[]\'],'.
      'this.form.elements[\''.$unselectedname.'\'],false)" class="doublebox">';
    foreach ($soids as $koid) {
      $txt=$msg[$koid];
      if((is_array($value) && (isset($value[$koid]) || in_array($koid, $value))) || ($koid==$value)) {
        $selected .= '<option value="'.$koid.'">'.$txt.'</option>';
        $r->text.=$txt;
      } else {
        $unselected .= '<option value="'.$koid.'">'.$txt.'</option>';
      }
    }
    $selected .= '</select>';
    $unselected .= '</select>';
    $buttons='<input type="button" value=">>" onclick="TZR.doubleAdd(this.form.elements[\''.$unselectedname.'\'],'.
      'this.form.elements[\''.$fname.'[]\'],true)"><br/>'.
      '<input type="button" value="<<" onclick="TZR.doubleAdd(this.form.elements[\''.$fname.'[]\'],'.
      'this.form.elements[\''.$unselectedname.'\'],'.$morder.')">';
    $hidd='<input type="hidden" name="'.$hiddenname.'" value="doublebox"/>';
    $color=\Seolan\Core\Ini::get('error_color');
    if($this->compulsory) $t1="TZR.addValidator(['$varid',/(.+)/,'".addslashes($this->label)."','$color','\Seolan\Field\Link\Link']);";
    else $t1="TZR.addValidator(['$varid','','".addslashes($this->label)."','$color','\Seolan\Field\Link\Link']);";
    $js="<script type=\"text/javascript\">if(typeof(TZR)!='undefined') { $t1 }</script>";
    $r->html='<table class="doublebox"><tr><td>'.$unselected.'</td><td class="button">'.$buttons.$hidd.'</td><td>'.$selected.'</td></tr></table>'.$js;
    $r->oidcollection=$oidcollection;
    $r->collection=$collection;
    $r->varid=$varid;
  }

  /// Edition du champ sous la forme de boite à cocher (checkbox/radio)
  function getCheckboxes(&$value,&$options,&$r,$soids,$fname,$hiddenname,$msg){
    $qf=@$options['query_format'];
    if($this->multivalued || ($qf==1)) $typebox='checkbox';
    else $typebox='radio';
    $my_compulsory=($this->compulsory || @$options['compulsory']) && !$qf;

    $cols=0;
    $edit='<input type="hidden" name="'.$hiddenname.'" value="'.$typebox.'"/>';

    // Ajout du bouton pour inverser la sélection
    if(\Seolan\Core\Shell::admini_mode() && $typebox=='checkbox' && count($soids)>7) {
      $onclickInvertsel = "jQuery(this).next().find('input').trigger('click'); return false;";
      $edit.='<a onclick="'.$onclickInvertsel.'" href="#">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','checkbox_invertsel').'</a>';
    }

    $edit.='<table class="tzr-checkboxtable">';
    $edit.='<tr>';
    if(!$my_compulsory && ($typebox=='radio')) {
      $varid=uniqid('v');
      $edit.='<td><div class="radio"><label><input type="'.$typebox.'" name="'.$fname.'" value="" id="'.$varid.'"/>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','empty_menu').'</label></div></td>';
      $cols++;
    }
    $listvarid=array();
    foreach ($soids as $koid) {
      $edit.='<td>';
      $display=$msg[$koid];
      $varid=uniqid('v');
      $listvarid[]=$varid;
      $checked=false;
      if($this->multivalued || ($qf==1)){
	$checked=isset($value[$koid]);
      }elseif($my_compulsory && empty($value) && $this->usedefault) {
        if ($options['default'])
          $value=$options['default'];
        else
          $value=$koid;
      }else{
        $checked=($koid==$value);
      }
      $class='';
      if (isset($this->error)) $class = 'class="error_field"';
      if($typebox=='checkbox')
	$edit.='<div class="checkbox"><label><input type="checkbox" '.$class.' id="'.$varid.'" name="'.$fname.'['.$koid.']" '.($checked?' checked ':'').
	  '/>'.$display.'</label></div></td>';
      else
	$edit.='<div class="radio"><label><input type="radio" '.$class.' id="'.$varid.'" name="'.$fname.'" value="'.$koid.'" '.
	  ($checked?' checked ':'').($my_compulsory?' required ':'').'/>'.$display.'</label></div></td>';
      $cols++;
      if($checked) $r->text.=$display;
      if($cols>=$this->checkbox_cols) {
	$edit.='</tr><tr>';
	$cols=0;
      }
      $oidcollection[]=$koid;
      $collection[]=$display;
    }
    $edit.='</tr></table>';
    if(!empty($my_compulsory) && !empty($listvarid)) {
      $color=\Seolan\Core\Ini::get('error_color');
      $edit.='<script type="text/javascript">TZR.addValidator(["'.$listvarid[0].'","","'.addslashes($this->label).'","'.$color.'","\Seolan\Field\StringSet\StringSet","",'.
	'["'.implode('","',$listvarid).'"]]);</script>';
    }
    $r->oidcollection=$oidcollection;
    $r->collection=$collection;
    $r->varid=$listvarid[0];
    $r->html=$edit;
  }

  // Edition du champ sous la forme d'une liste déroulante
  function getSelect(&$value,&$options,&$r,$soids,$fname,$hiddenname,$msg){
    $qf=@$options['query_format'];
    $i=0;
    $edit='';
    foreach ($soids as $koid) {
      $i++;
      $txt=$msg[$koid];
      if((is_array($value) && (isset($value[$koid]) ||in_array($koid, $value))) || ($koid==$value)) $selected=' selected';
      else $selected='';
      $edit.='<option value="'.$koid.'"'.$selected.'>'.$txt.'</option>';
      if($selected) $r->text.=$txt;
      $oidcollection[]=$koid;
      $collection[]=$txt;
      if(!empty($selected) && empty($first)) $first='<option value="'.$koid.'">'.$this->label.' : '.$txt.'</option>';
    }
    $varid=uniqid('v');
    $labelin=@$options['labelin'];
    if($qf){
      $format=@$options['fmt'];
      if(empty($format)) $format=@$options['qfmt'];
      if(empty($format)) $format=$this->query_format;
      if($this->queryInitialLabel != '') $first='<option value="">'.$this->queryInitialLabel.'</option>';
      elseif(empty($labelin)) $first='<option value="">----</option>';
      elseif(empty($first) || $format=='listbox' || $format=='listbox-one') $first='<option value="">'.$this->label.'</option>';
      else{
        $first.='<option value="">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','n/a').'</option>';
        $edit=str_replace('" selected>','">',$edit);
      }
      if ($qf === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options)) {
        $first = '';
      }
      $i++;
      if($i<2 || $format=='listbox-one') $edit='<select name="'.$fname.'" id="'.$fname.'">'.$first.$edit.'</select>';
      else $edit='<select '.(@$options['query_format'] === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' name="'.$fname.'[]" id="'.$fname.'" size="'.min($i,6).'" multiple>'.$first.$edit.'</select>';
    }else{
      if(!$this->compulsory || !$this->usedefault) {
        if(empty($labelin))
          $edit='<option value="">----</option>'.$edit;
        else
          $edit='<option value="">'.$this->label.'</option>'.$edit;
        $i++;
      }
      if($this->multivalued) $cplt='name="'.$fname.'[]" size="'.min($i,6).'" multiple';
      else $cplt='name="'.$fname.'"';
      if($this->compulsory) $cplt.=" required";
      $edit='<select '.$cplt.' id="'.$varid.'" onblur="TZR.isIdValid(\''.$varid.'\');">'.$edit.'</select>';
      if($this->compulsory) {
        $edit.='<script type="text/javascript">TZR.addValidator(["'.$varid.'","compselect","'.addslashes($this->label).'","'.\Seolan\Core\Ini::get('error_color').'",'.
          '"\Seolan\Field\StringSet\StringSet"]);</script>';
      }
    }
    $r->oidcollection=$oidcollection;
    $r->collection=$collection;
    $r->varid=$varid;
    $r->html=$edit;
  }

  function getSoidTable($lang) {
    return self::_getCache()[$lang][$this->table][$this->field];
  }
  function my_query($value,$options=NULL) {
    $options['query_format']=1;
    $r=$this->my_edit($value,$options);
    $r->raw=$value;
    return $r;
  }
  function my_quickquery($value,$options=NULL) {
    $options['qfmt']='listbox';
    $r=$this->query($value,$options);
    $r->html.='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    $r->raw=$value;
    return $r;
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL){
    $p=new \Seolan\Core\Param($options,array());
    $r=$this->_newXFieldVal($options);
    $ischeckbox=($p->get($this->field.'_HID')=='checkbox') || ($p->get($this->field.'_FMT')=='checkbox');
    if($ischeckbox && is_array($value)) {
      $nvalue=array();
      foreach($value as $soid => $set) {
	$nvalue[]=$soid;
      }
      $value=$nvalue;
    }
    $r->raw=$value;
    // Edition par lot sur champ multivalué
    if(!empty($options['editbatch']) && $this->multivalued){
      $op=$p->get($this->field.'_op');
      $old=explode('||',$options['old']->raw);
      if($op=='+') $r->raw=array_unique(array_merge($r->raw,$old));
      elseif($op=='-') $r->raw=array_diff($old,$r->raw);
    }
    // Trace
    if(!empty($options['old'])){
      $old=$options['old'];
      if(is_array($r->raw)) $v=implode('||',$r->raw);
      else $v=$r->raw;
      $r1=$this->display($v,$options);
      if($r1->html!=$old->html) $this->trace($options['old'],$r, '['.$old->html.'] -> ['.$r1->html.']');
    }
    return $r;
  }

  function post_query($o,$options=NULL) {
    $p=new \Seolan\Core\Param($options,array());
    $ischeckbox=($o->hid=="checkbox")||($o->fmt=="checkbox");
    if($ischeckbox && is_array($o->value)) {
      $nvalue=array();
      foreach($o->value as $soid => $set) {
	if(!empty($soid))
	  $nvalue[]=$soid;
      }
      $o->value=$nvalue;
    }
    return parent::post_query($o,$options);
  }
  /// Vérification de l'import = import sans création
  protected function my_checkImport($value, $specs=null){
    if ($specs == null)
      return parent::my_checkImport($value, $specs);
    $specs->create = false;
    return $this->my_import($value, $specs);
  }
  /// Sous fonction pour l'import de données vers une table
  /// Options : create (true/false) => creation automatique des fiches cible non existante (false par defaut)
  /// separator (string) => séparateur pour des valeurs multiples (| par defaut)
  function my_import($value,$specs=null){
    $separator=$specs->separator;
    $srcField=$specs->srcField;
    if(empty($separator))
      $separator = TZR_IMPORT_SEPARATOR;
    $create=$specs->create;
    $message='';
    $lang=\Seolan\Core\Shell::getLangData();
    if(empty($this->cache['soids'][$lang])){
      $tmp=$this->getSoidTable($lang);
      $this->cache['soids'][$lang]=array_flip($tmp);
    }
    if($srcField!='raw' && !empty($value)){
      $ret=array();
      cp1252_replace($value);
      if(is_array($separator)) {
        $value = str_replace($separator, $separator[0], $value);
        $valueslist = explode($separator[0], $value);
      }
      else {
        $valueslist = explode($separator, $value);
      }

      foreach($valueslist as $v){
	$v = trim($v);
	if(!empty($this->cache['soids'][$lang][$v]))
	  $ret[]=$this->cache['soids'][$lang][$v];
	else{
	  if(!empty($create) && $create != false){
	    list($newsoid, $neworder)=$this->newSOID();
	    getDB()->execute('insert into SETS(SOID,STAB,FIELD,SLANG,STXT,SORDER) VALUES(?,?,?,?,?,?)',
			     array($newsoid,$this->table,$this->field,$lang,$v,$neworder));
	    $this->cache['soids'][$lang][$v]=$newsoid;
	    $message.=$this->field.' : "'.$v.'" created<br/>';
	    $ret[]=$newsoid;
            self::clearCache();
          }else{
	    $message.='<u>Warning</u> : '.$this->field.' : "'.$v.'" doesn\'t exist<br/>';
	  }
	}
      }
    }else{
      $ret=$value;
    }
    return array('message'=>$message,'value'=>$ret);
  }

  function sqltype() {
    return "text";
  }

  /// Recupere le type du champ dans un webservice (name : type xml, descr : description du type pour l'ajour d'une type complexe)
  function getSoapType(){
    if($this->multivalued)
      return array('name'=>'tns:stringArray',
		   'descr'=>array('stringArray'=>array(array('name'=>'value','minOccurs'=>0,'maxOccurs'=>'unbounded','type'=>'xsd:string'))));
    else
      return array('name'=>'xsd:string');
  }
  /// Recupere la valeur formattée pour le service SOAP
  function getSoapValue($r){
    if($this->multivalued) return $r->collection;
    else return $r->html;
  }

  private static function _getCache() {
    if ($_cache = \Seolan\Library\ProcessCache::get('sets', 'sets')) {
      return $_cache;
    }
    global $TZR_LANGUAGES;
    $_sets = getDB()->select(
        'select * from SETS order by STAB, FIELD, SORDER, FIELD(SLANG,"' . implode('","', array_keys($TZR_LANGUAGES)) . '")'
      )->fetchAll(\PDO::FETCH_OBJ);
    $sets = array();
    foreach ($_sets as $set) {
      // initialiser toutes les langues avec la langue par défaut
      if ($set->SLANG == TZR_DEFAULT_LANG) {
        foreach ($TZR_LANGUAGES as $codeTzr => $codeIso) {
          $sets[$codeTzr][$set->STAB][$set->FIELD][$set->SOID] = $set->STXT;
        }
      } else {
        $sets[$set->SLANG][$set->STAB][$set->FIELD][$set->SOID] = $set->STXT;
      }
    }
    \Seolan\Library\ProcessCache::set('sets', 'sets', $sets);
    return $sets;
  }

  static function clearCache() {
    \Seolan\Library\ProcessCache::delete('sets/sets');
  }

  public static function getSets() {
    return self::_getCache();
  }

  /// rend le libelle du soid dans la langue $lang;
  function getTextFromSoid($soid,$lang=NULL) {
    $sets = self::_getCache();
    if (empty($lang))
      $lang = \Seolan\Core\Shell::getLangData();
    if (isset($sets[$lang][$this->table][$this->field][$soid])) {
      return $sets[$lang][$this->table][$this->field][$soid];
    }
    return '';
  }

  /// rend le soid a partir d'un libelle + champ
  function get_soid_from_set($str) {
    if (empty($str))
      return 0;
    $sets = self::_getCache();
    $lang = \Seolan\Core\Shell::getLangData();
    foreach ($sets[$lang][$this->table][$this->field] as $soid => $stxt) {
      if ($stxt == $str) {
        return $soid;
      }
    }
    \Seolan\Core\Logs::critical('\Seolan\Field\StringSet\StringSet::get_soid_from_set',
      $str . ' non trouve pour ' . $this->table . '::' . $this->field);
    return 0;
  }

  /// nouveau SOID et nouvel ordre
  function newSOID() {
    $nb=getDB()->fetchOne('SELECT MAX(SOID)+1 FROM SETS');
    if(empty($nb)) $r1=1;
    else $r1=$nb;
    $nb=getDB()->fetchOne('SELECT MAX(SORDER)+1 FROM SETS WHERE STAB=? AND FIELD=?', array($this->table, $this->field));
    if(empty($nb)) $r2=1;
    else $r2=$nb;

    return array($r1,$r2);
  }

  /// creation d'une nouvelle valeur
  function newString($label, $soid=NULL, $sorder=NULL) {
    global $XLANG;
    if (!is_array($label))
      $label = array(TZR_DEFAULT_LANG=>$label);
    list($soid1,$sorder1)=$this->newSOID();
    if(empty($soid)) $soid=$soid1;
    else $soid=rewriteToAscii($soid);
    if(empty($sorder)) $sorder=$sorder1;

    $cnt=getDB()->count('SELECT COUNT(DISTINCT SOID) FROM SETS WHERE STAB=? AND FIELD=? AND SOID=?', array($this->table,$this->field,$soid));
    if($cnt>0) return array();
    // Insertion d'un tuple par langue, tous avec le meme oid
    \Seolan\Core\Lang::getCodes();
    for($i=0;$i<count($XLANG->codes);$i++) {
      $theLang=$XLANG->codes[$i];
      if (!empty($label[$theLang]))
        $theTxt=$label[$theLang];
      else
        $theTxt=$label[TZR_DEFAULT_LANG];
      if($theTxt!='') {
        getDB()->execute('INSERT INTO SETS (SOID,STAB,FIELD,SLANG,STXT,SORDER) values (?,?,?,?,?,?)',
                         array($soid,$this->table,$this->field,$theLang,$theTxt,$sorder));
      }
    }
    self::clearCache();
    return array($soid, $sorder);
  }

  /// creation d'un index si c'est un champ mono value
  function chk(&$msg) {
    parent::chk($msg);
    if(!$this->get_multivalued()) {
      // ajout d'un index sur les champs non multivalues
      if (!getDB()->count('SHOW INDEX FROM '.$this->table.' where Column_name="'.$this->field.'"')) {
        getDB()->execute('ALTER TABLE '.$this->table.' ADD INDEX `'.$this->field.'`(`'.$this->field.'`(10))');
      }
    }
    return true;
  }
  
  /// génération de la documentation
  public function getDocumentationData(){
    $doc = parent::getDocumentationData();
    $options = [];
    // on ajoute les valeurs possibles
    $msg = $this->getChoices();
    $soids = $this->getChoicesOids($options, $msg);
    $values=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','value').'/'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','label');
    foreach($msg as $soid=>$m) {
      $values.=', '.$soid.'/'.$m;
    }
    $doc->annex[] = $values;
    
    
    return $doc;
  }
}

