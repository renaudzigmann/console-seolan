<?php
namespace Seolan\Core;
/****
 * NAME
 *   \Seolan\Core\Options -- gestion d'un ensemble d'options
 * DESCRIPTION
 *   Classe permettant la gestion d'un ensemble d'options dans un bloc de données
 *   Utilisation: options des champs, options des modules, etc...
 * SYNOPSIS
 ****/

class Options {
  const JSON_COMMENT='JSON encoded options, version 2';
  const JSON_META=['@version@','@comment@'];
  private $_s = NULL;
  private $_p = array();
  private $_defaultgroup = 'Specific';
  static public $upgrades=[
    '20200120'=>''
   ,'20200124'=>''
  ];  
  function __construct($id = null) {
    if (!is_null($id)) $this->setId($id);
  }

  public function setId($id) {
    $this->id=$id;
  }

  /// Déclaration d'une nouvelle option
  public function setOpt ($label, $option, $type, $opts=NULL, $def=NULL, $group=NULL, $level='admin') {
    if (is_null($group)) $group = $this->_defaultgroup;
    if(\Seolan\Core\Shell::admini_mode()) $this->_p[$option]=['label'=>$label, 'type'=>$type, 'options'=>$opts, 'def'=>$def, 'group'=>$group, 'level'=>$level];
    else $this->_p[$option]=['type'=>$type, 'options'=>$opts, 'def'=>$def, 'level'=>$level];
  }
  /**
   * Permet d'ajouter rapidement plusieurs options
   *
   * @param array Tableau de plusieurs options au format : [
   *   'group' => [
   *     'option_id' => [
   *       'label'   => (obligatoire) Label à afficher
   *       'type'    => Type de l'option (text=defaut, ttext, boolean, table, object...)
   *       'options' => Tableau des options en fonction du type choisi
   *       'default' => Valeur par défaut
   *       'comment' => Aide pour le développeur/intégrateur
   *     ],
   *     'option_id2' => [ ... ],
   *     'option_id3' => [ ... ],
   *     ...
   *   ],
   *   'group2' => [ ... ],
   *   'group3' => [ ... ],
   *   ...
   * ]
   * ou sans la clé de groupe, au format : [
   *   'option_id' => [
   *     'label'   => (obligatoire) Label à afficher
   *     ...
   *   ],
   *   'option_id2' => [ ... ],
   *   'option_id3' => [ ... ],
   *   ...
   * ]
   */
  public function setOpts(array $options_groups) {
    if (array_key_exists('label', current($options_groups)))
      $options_groups = [$this->_defaultgroup ?: 'General' => $options_groups];
    foreach ($options_groups as $group => $options) {
      foreach ($options as $option_id => $option) {
        $this->setOpt(
          $option['label'],
          $option_id,
          @$option['type'] ?: 'text',
          @$option['options'],
          @$option['default'],
          @$option['group'] ?: $group,
          @$option['level'] ?: 'admin'
        );
        if (@$option['comment']) {
          $this->setComment(@$option['comment'], $option_id);
        }
      }
    }
  }

  /// Applique la valeur d'une option à un objet
  public function set(&$s,$field,$value) {
    // Recupere la valeur par defaut dans l'objet si celle ci n'est pas renseignée dans l'option
    if($this->_p[$field]['def']===NULL && isset($s->$field)) $this->_p[$field]['def']=$s->$field;
    if(@$this->_p[$field]['type']=='boolean') {
      if(!isset($value)) $value=@$this->_p[$field]['def'];
      if($value==1) $s->$field=true;
      else $s->$field=false;
      return;
    }elseif(@$this->_p[$field]['type']=='ttext'){
      $lang=\Seolan\Core\Shell::getLangData();
      $id=$this->id.':'.$field;
      $txts=\Seolan\Core\Labels::getAMsg($id,$lang,false);
      if (empty($txts) && isset($value[TZR_DEFAULT_LANG])){
	$txts = $value[$lang]??$value[TZR_DEFAULT_LANG];
      } elseif (empty($value)
		&& empty($txts)
		&& isset($this->_p[$field]['def'])){
	if (is_array($this->_p[$field]['def'])){
	  $txts = @$this->_p[$field]['def'][$lang];
	}
      }
      if (empty($txts) && !empty($value) && is_string($value) && ($value!=$id)) {
        $txts = $value;
      }
      $s->$field=$txts;
      return;
    } elseif(@$this->_p[$field]['type']=='field') {
      if ($uv = @unserialize($value))  $value = $uv;

      if (isset($this->_p[$field]['compulsory']) && $this->_p[$field]['compulsory']){
	if(empty($value))
	  $value=@$this->_p[$field]['def'];
      } else {
	if(empty($value) && $value===null)
	  $value=@$this->_p[$field]['def'];
      }
      $opts=$this->_p[$field]['options'];
      $table=@$opts['table'];
      if(empty($table)) { // default to current table (\Seolan\Module\Table\Table)
	$table = 'table';
      } 
      if(property_exists($s, $table)) { // option previously set or 'table'
	$table=$s->$table;
      }
      if (is_array($value)){
	foreach($value as $i=>$fn){
	  if (!\Seolan\Core\System::fieldExists($table, $fn)){
	    unset($value[$i]);
	  }
	}
      } elseif (empty($value) || !\Seolan\Core\System::fieldExists($table, $value)){
	$value=null;
      }
      if(empty($table) || empty($value)){
	$s->$field=NULL;
      } else {
	$s->$field=$value;
      }
    } else {
      if (($uv = @unserialize($value)) !== false)
        $value = $uv;
      if($value==='' || $value===NULL) $value=@$this->_p[$field]['def'];
      $s->$field=$value;
    }
  }

  /// Positionne l'option en lecture seule
  public function setRO($field) {
    $this->_p[$field]['options']['read-only']=true;
  }

  public function delOpt($option) {
    unset($this->_p[$option]);
  }
  public function clearOpts() {
    $this->_p=array();
  }
  public function get($s, $field) {
    return @$s->$field;
  }
  public function names(){
    return array_keys($this->_p);
  }
  public function getValues($s) {
    foreach($this->_p as $n => $p) {
      $r[$n]=$this->get($s, $n);
    }
    return $r;
  }
  public function setValues(&$s1, $s=NULL) {
    if(!is_array($s)) $s=array();
    foreach($this->_p as $n =>$v) {
      if(!isset($s[$n])) $s[$n]=NULL;
      $this->set($s1, $n, $s[$n]);
    }
  }
  public function setLabel($label,$option) {
    if($this->_p[$option])
      $this->_p[$option]['label']=$label;
  }
  public function setComment($comment,$option) {
    if($this->_p[$option])
      $this->_p[$option]['comment']=$comment;
  }
  public function setGroup($group, $option) {
    if (is_array($option)) {
      foreach ($option as $opt) $this->setGroup($group, $opt);
    } elseif ($this->_p[$option]) {
      $this->_p[$option]['group']=$group;
    }
  }
  public function setDefaultGroup($group) {
    $this->_defaultgroup = $group;
  }
  public function setGroupComment($comment, $option) {
    if($this->_p[$option])
      $this->_p[$option]['groupcomment']=$comment;
  }
  public function getDialog(&$o, $ar=NULL,$block='options',$level='admin',$get_edit=true) {
    $ui=array();
    $groups=array();
    foreach($this->_p as $n =>$v) {
      if(!empty($v['group']) && !in_array($v['group'],$groups)) {
	$groups[]=$v['group'];
      }
    }
    if(empty($groups)) $groups[]='Specific';
    foreach($groups as $i1=>$g) {
      if (!empty($ar['groups']) && !in_array($g,$ar['groups'])) continue;
      foreach($this->_p as $n =>$v) {
	if(empty($v['group'])) $v['group']='Specific';
        if($v['group']==$g) {
          $field_ui_to_add = array(
            'field'        => $n,
            'group'        => @$v['group'],
            'label'        => @$v['label'],
            'options'      => @$v['options'],
            'type'         => @$v['type'],
            'comment'      => @$v['comment'],
            'groupcomment' => @$v['groupcomment'],
          );
          if ($get_edit) {
            if((@$v['options']['read-only']) || (($level!='admin') && ($v['level']=='admin'))) {
              $field_ui_to_add['edit'] = $this->_getView($block, $n,$this->get($o, $n));
            } else {
              $field_ui_to_add['edit'] = $this->_getUI($block, $n, $o);
            }
          }
          $ui[] = $field_ui_to_add;
	}
      }
    }
    return $ui;
  }
  /**
   * Enregistre les propriétés passées en paramètre
   * @param &$obj Objet dont les propriétés vont être modifiées
   * @param &$values Nouvelles valeurs à insérer dans l'objet passé en paramètre précédent
   */
  public function procDialog(&$obj,&$values) {
    $lang = \Seolan\Core\Shell::getLangUser();
    $ui=array();
    foreach($this->_p as $n =>$v) {
      if(isset($values[$n])){
	$val=$values[$n];
        if(method_exists($this, '_proc'.$this->_p[$n]['type'])) {
          $m='_proc'.$this->_p[$n]['type'];
          $this->$m($obj, $n, $val);
        }
        $this->set($obj, $n, $val);
        $values[$n]=$val;
      }
    }
  }
  private function _procdependency($s, $n, &$v) {
    // suppression des quotes protégeant l'opérateur pour export excel
    $v['op'] = preg_replace('/"(.*)"/', '$1', $v['op']);
  }
  private function _procttext($s, $n, &$v) {
    \Seolan\Core\Labels::updateAMsg($this->id.':'.$n, $v);
    $v=$this->id.':'.$n;
  }
  private function _proctemplate($s, $n, &$v) {
    if(is_array($v)) {
      $v=implode('',$v);
    }
  }
  private function _procdate($s, $n, &$v) {
    if(empty($this->_p[$n]['options']['free'])){
      $xdate=new \Seolan\Field\Date\Date();
      $ret=$xdate->post_edit($v);
      $v=$ret->raw;
    }
  }
  private function _procModule($s, $n, &$v) {
    if ($v == ['0']) $v = 0;
  }
  private function _procObject($s, $n, &$v) {
    // cas lien doublebox vide
    if ($v == 'doublebox') $v = '';
    // cas autocompletion vide
    if (is_array($v)) $v = array_filter($v);
    if (empty($v)) $v = null;
  }
  public function getView(&$o, $ar=NULL,$block='opt') {
    $lang = \Seolan\Core\Shell::getLangUser();
    $ui=array();
    $groups=array();
    foreach($this->_p as $n =>$v) {
      if(!empty($v['group']) && !in_array($v['group'],$groups)) {
	$groups[]=$v['group'];
      }
    }
    if(empty($groups)) $groups[]='Specific';
    foreach($groups as $i1=>$g) {
      foreach($this->_p as $n =>$v) {
	if(empty($v['group'])) $v['group']='Specific';
	if($v['group']==$g) {
	  $ui[]=array('field'=>$n,
		      'group'=>$v['group'],
		      'options'=>$v['options'],
		      'label'=>$v['label'],
		      'view'=>$this->_getView($block, $n,$this->get($o, $n)));
	}
      }
    }
    return $ui;
  }
  private function _getUI($block,$n,&$o) {
    $v=$this->get($o,$n);
    $type=$this->_p[$n]['type'];
    $opts=$this->_p[$n]['options'];
    $txt='';
    if($block) $name=$block.'['.$n.']';
    else $name=$n;
    $onchange = (!empty($opts['validate'])?'onchange="if(this.form.onsubmit()) this.form.submit();"':'');
    switch($type) {
    case 'ttext':
      if(is_array($v)) $txts=$v;
      else{
	$txts=\Seolan\Core\Labels::getAMsg($this->id.':'.$n,NULL,false);
      }
      if ($txts === null && is_string($o->$n) && !empty($o->$n)) {
        foreach($GLOBALS['TZR_LANGUAGES'] as $lang=>$iso) {
          $txts[$lang] = $o->$n;
        }
      }
      $txt="<table class=\"table\">";
      $size=(!empty($opts['size'])?$opts['size']:30);
      if(!empty($opts['rows'])) {
	$cols=$opts['cols'];
	$rows=$opts['rows'];
      }
      $varid=uniqid('v');
      $js='';
      foreach($GLOBALS['TZR_LANGUAGES'] as $lang=>$iso) {
	$txt.='<tr><th>'.$lang.'</th><td>';
	$v=str_replace('<br />','',$txts[$lang]);
	if($opts['rows']) {
	  if($lang == TZR_DEFAULT_LANG){
	    $js .= "jQuery('#".$varid.$lang."').blur(function(){var val = jQuery(this).val();var oldval= jQuery(this).attr('oldval'); jQuery('textarea[id^=$varid]').each(function(){if(jQuery(this).val()==oldval) jQuery(this).val(val)})}).focus(function(){jQuery(this).attr('oldval',jQuery(this).val());});";
	  }
	  $txt.='<textarea id="'.$varid.$lang.'" name="'.$block.'['.$n.']['.$lang.']" cols="'.$cols.'" rows="'.$rows.'" '.$onchange.'>'.$v.'</textarea>';
	} else {
	  $txt.='<input id="'.$varid.$lang.'" type="text" name="'.$block.'['.$n.']['.$lang.']" value="'.htmlentities($v,ENT_COMPAT,TZR_INTERNAL_CHARSET).'" size="'.$size.'" '.$onchange.'/>';
	  if($lang == TZR_DEFAULT_LANG){
	    $js .= "jQuery('#".$varid.$lang."').blur(function(){var val = jQuery(this).val();var oldval= jQuery(this).attr('oldval'); jQuery('input[id^=$varid]').each(function(){if(jQuery(this).val()==oldval) jQuery(this).val(val)})}).focus(function(){jQuery(this).attr('oldval',jQuery(this).val());});";
	  }
	}
	$txt.='</td></tr>';
	if(!empty($opts['compulsory'])){
	  $js.='TZR.addValidator(["'.$varid.$lang.'",/(.+)/,"","'.\Seolan\Core\Ini::get('error_color').'","\Seolan\Field\ShortText\ShortText"]);';
	}
      }
      $txt.='</table>';
      if($js !='' ) $txt.='<script type="text/javascript">'.$js.'</script>';
      break;
    case 'text':
      $varid=uniqid('v');
      $size=(!empty($opts['size'])?$opts['size']:30);
      if(@$opts['rows']) {
	$cols=$opts['cols'];
	$rows=$opts['rows'];
	$txt='<textarea id="'.$varid.'" name="'.$block.'['.$n.']" cols="'.$cols.'" rows="'.$rows.'" '.$onchange.'>'.$v.'</textarea>';
      } else {
	$txt='<input id="'.$varid.'" type="text" name="'.$block.'['.$n.']" value="'.htmlentities($v,ENT_COMPAT,TZR_INTERNAL_CHARSET).'" size="'.$size.'" '.@$onchange.'/>';
      }
      if(!empty($opts['compulsory'])){
	$txt.='<script type="text/javascript">TZR.addValidator(["'.$varid.'",/(.+)/,"","'.\Seolan\Core\Ini::get('error_color').'","\Seolan\Field\ShortText\ShortText"]);</script>';
      }
      break;
    case 'integer':
      // type="number"
      $varid=uniqid('v');
      $size=(!empty($opts['size'])?$opts['size']:10);
      $pattern=empty($opts['compulsory'])?'[0-9]*':'[0-9]+';
      if (!empty($opts['pattern'])){
	$pattern = htmlentities($opts['pattern'],ENT_COMPAT,TZR_INTERNAL_CHARSET);
      }
      $txt="<input  id=\"$varid\" pattern=\"$pattern\" type=\"number\" name=\"{$block}[{$n}]\" value=\"$v\" size=\"$size\" $onchange />";
      if(!empty($opts['compulsory'])){
	$txt.='<script type="text/javascript">TZR.addValidator(["'.$varid.'",/^[0-9]+$/,"","'.\Seolan\Core\Ini::get('error_color').'","XShortTextDef"]);</script>';
      }
      break;
    case 'label':
      $size=(!empty($opts['size'])?$opts['size']:30);
      if(!empty($opts['rows'])) {
	$cols=$opts['cols'];
	$rows=$opts['rows'];
	$txt="<textarea name=\"".$block."[$n]\" cols=\"$cols\" rows=\"$rows\" $onchange >$v</textarea>";
      } else {
	$txt="<input type=\"text\" name=\"".$block."[$n]\" value=\"$v\" size=\"$size\" $onchange />";
      }
      break;
    case 'boolean':
      $txt='<select name="'.$block."[$n]\"><option value=\"1\" ".($v?'selected':'').'>'.
	\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','yes').'</option>'.
	'<option value="0" '.(!$v?'selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','no').'</option>';
      $txt.='</select>';
      break;
    case 'date':
      $txt=$this->_getDateUI($block.'['.$n.']',$v,$opts);
      break;
    case 'object':
      if(empty($opts['table'])) return '';
      $txt=$this->_getLinkUI($block.'['.$n.']',$v,$opts,$opts['table']);
      break;
    case 'template':
      if(!empty($opts['moid']) && !preg_match('/([0-9]+)/',$opts['moid'])) {
	$f=$opts['moid'];
	$opts['moid']=$o->$f;
      }
      $txt=$this->_getLinkUI($block.'['.$n.']',$v,$opts,'TEMPLATES');
      break;
    case 'letter':
      $txt=$this->_getLinkUI($block."[$n]",$v,$opts,'LETTERS');
      break;
    case 'table':
      $k = new \Seolan\Core\Kernel();
      $txt=$k->baseSelector(array('fieldname'=>$name,'value'=>$v,'misc'=>$onchange,'emptyok'=>$opts['emptyok']??false));
      break;
    case 'module':
      if(!array_key_exists('emptyok',(array)$opts)) $opts['emptyok']=true;
      if(is_object($v)) $v=$v->_moid;
      $opts['fieldname'] = $name;
      $opts['value'] = $v;
      $txt=\Seolan\Core\Module\Module::moduleSelector($opts);
      break;
    case 'list':
      $txt =$this->_getListUI($block."[$n]",$v,$opts['labels'],$opts['values'],$onchange);
      break;
    case 'multiplelist':
      $txt =$this->_getMultipleListUI($block."[$n]",$v,$opts['labels'],$opts['values'],$onchange);
      break;
    case 'doublelist':
      $txt =$this->_getDoubleListUI($block."[$n]",$v,$opts['labels'],$opts['values'],$onchange);
      break;
    case 'field':
      $t=$opts['table'];
      if(empty($t)) $t='table';
      $table=$this->get($o,$t);
      if(empty($table)) $table=$t;
      if(empty($table) || $table=='%') {
	$txt='';
      } else {
	$x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8("SPECS={$table}");

        if(!$x) $txt='';
	else $txt=$x->fieldSelector(array('fieldname'=>$block."[$n]",
                                          'compulsory'=>$opts['compulsory']??false, 'multivalued'=>$opts['multivalued']??false,
                                          'value'=>$v,'type'=>$opts['type']??''));
      }
      break;
    case 'dependency':
      $t=$opts['table'];
      if(empty($t)) {
	$t='table';
      }
      $table=$this->get($o,$t);
      if(empty($table)) {
	$txt='';
	break;
      }
      $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
      list($foo,$field)=explode(':',$this->id);
      if(!isset($x->desc[$field])){
	$txt='';
	break;
      }
      $txt=$x->fieldSelector(array('fieldname'=>$block."[$n][f]",'compulsory'=>false,'value'=>$v['f'],
				   'type'=>array('\Seolan\Field\Boolean\Boolean','\Seolan\Field\Link\Link','\Seolan\Field\StringSet\StringSet'),
				   'filter'=>array('multivalued'=>false,'field'=>array('!=',$o->field))));
      $txt=str_replace('<select ','<select onchange="document.getElementById(\'ddep_'.$block.'_'.$n.'\').innerHTML=\'\';" ',$txt);
      if(strpos($txt,$x->desc[$v['f']]->field)===false) return $txt;
      if(!empty($v['f'])){
	$xd=clone($x->desc[$v['f']]);
	$xd->multivalued=false;
	$xf=clone($x->desc[$field]);
	$xf->compulsory=false;
	if($xd->ftype=='\Seolan\Field\Boolean\Boolean'){
	  $xd->listbox=true;
	}
	$txt.='<div class="table-responsive" id="ddep_'.$block.'_'.$n.'">';
	$txt.='<table class="table table-condensend" id="tdep_'.$block.'_'.$n.'"><tr><td></td><th>Valeur</th><th>Style</th><th>Nouvelle valeur</th></tr>';
	$txt.='<tr><td></td><td></td><td></td><td></td><td></td></tr>';
	foreach($v['dval'] as $i=>$foo){
	  $uniq=uniqid();
	  $ui=$this->_getDependencyUI($block."[$n][xxx][$uniq]",$table,$field,$v['f'],
				      $v['op'][$i],$v['dval'][$i],$v['style'][$i],$v['val'][$i],$v['nochange'][$i]);
	  $txt.='<tr>';
	  $txt.='<td><a onclick="TZR.delLine(this); return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete').'</a></td>';
	  $txt.='<td>'.$ui[0].'</td>';
	  $txt.='<td>'.$ui[1].'</td>';
	  $txt.='<td>'.$ui[2].'</td>';
	}
	$txt.='</table>';
	$txt.='<a href="#" onclick="TZR.addTableLine(\'tdep_'.$block.'_'.$n.'\','.$n.'tds,1); return false;">Ajouter</a>';

	$ui=$this->_getDependencyUI($block."[$n][xxx][xidxid]",$table,$field,$v['f']);
	$txt.='<script type="text/javascript">';
	$txt.='var '.$n.'tds=new Array();';
	$txt.=$n.'tds[0]="'.escapeJavascript('<a onclick="TZR.delLine(this); return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete').
					     '</a>').'";';
	$txt.=$n.'tds[1]="'.escapeJavascript($ui[0]).'";';
	$txt.=$n.'tds[2]="'.escapeJavascript($ui[1]).'";';
	$txt.=$n.'tds[3]="'.escapeJavascript($ui[2]).'";';
	$txt.='</script>';
	$txt.='</div>';
      }
      break;
    case 'stringset':
      $t = $opts['table'];
      if (empty($t))
        $t = 'table'; // default to current table (\Seolan\Module\Table\Table)
      $table = $this->get($o, $t);
      if (empty($table))
        $table = $t;
      $f = $opts['field'];
      if (!empty($f))
        $field = $this->get($o, $f); // option previously set
      if (empty($field))
        $field = $f;
      if(empty($table) || empty($field)) {
	$txt='';
      } else {
        $xStringSet = new \Seolan\Field\StringSet\StringSet((object)['FTYPE'  => '\Seolan\Field\StringSet\StringSet', 'DTAB' => $table, 'FIELD' => $field,
          'MULTIVALUED' => $opts['multivalued'], 'DPARAM' => $opts]);
        $opts['fieldname'] = $block."[$n]";
        $txt = $xStringSet->my_edit($v, $opts)->html;
      }
      break;
    }
    return $txt;
  }
  private function _getView($block, $n,$v) {
    $type = $this->_p[$n]['type'];
    $opts = $this->_p[$n]['options'];
    $txt=$v;
    switch($type) {
    case 'boolean':
      $txt = ($v?\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','yes'):\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','no'));
      break;
    case 'date':
      $txt = $v;
      break;
    case 'object':
      if(empty($opts['table'])) return '';
      $txt = $this->_getLinkView($block."[$n]",$v,$opts['table']);
      break;
    case 'template':
      $txt = $this->_getLinkView($block."[$n]",$v,'TEMPLATES');
      break;
    case 'letter':
      $txt = $this->_getLinkView($block."[$n]",$v,'LETTERS');
      break;
    case 'list':
      $txt = $v;
      break;
    case 'table':
      if(!empty($v)) {
	$x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$v);
	$txt=$x->getLabel();
      }
      break;
    case 'module':
      $txt='( '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','empty').' )';
      if(!empty($v)) {
	$x = \Seolan\Core\Module\Module::objectFactory(array('moid'=>$v,'tplentry'=>TZR_RETURN_DATA));
	if(!empty($x)) $txt=$x->getLabel();
      }
      break;
    }
    return $txt;
  }
  public function serialize(&$o) {
    $a=$this->getValues($o);
    return serialize($a);
  }

  private function _getDependencyUI($name,$table,$f,$d,$op=NULL,$valued=NULL,$style=NULL,$value=NULL,$nochange=NULL){
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
    $xd=clone($x->desc[$d]);
    $xf=clone($x->desc[$f]);
    $xf->compulsory=false;
    $xd->multivalued=$xf->multivalued=false;
    $xd->dependency=$xf->dependency=NULL;

    $txt=array();
    $opt=array('fieldname'=>str_replace('[xxx]','[dval]',$name),'simple'=>true);
    $o=$xd->edit($valued,$opt);
    $txt[0]='<select name="'.str_replace('[xxx]','[op]',$name).'">'.
      '<option value="="'.($op=='='?' selected':'').'>=</option>'.
      '<option value="!="'.($op=='!='?' selected':'').'>!=</option>'.
      '</select> '.$o->html;

    $txt[1]=$this->_getDependencyStyleUI($name,$style);

    $valname=str_replace('[xxx]','[val]',$name);
    $opt=array('fieldname'=>$valname,'simple'=>true);
    $o=$xf->edit($value,$opt);
    $txt[2]='<input type="hidden" name="'.str_replace('[xxx]','[nochange_HID]',$name).'" value="0">'.
      '<input type="checkbox" value="1" name="'.str_replace('[xxx]','[nochange]',$name).'" '.($nochange?'checked ':'').
      'onclick="if(this.checked) document.getElementById(\''.$valname.'\').style.display=\'none\'; else '.
      'document.getElementById(\''.$valname.'\').style.display=\'inline\';">no change';
    $txt[2].='<br><span id="'.$valname.'"'.($nochange?'style="display:none" ':'').
      '>'.$o->html.'</span>';

    unset($xd);
    unset($xf);
    return $txt;
  }
  private function _getDependencyStyleUI($name,$style){
    $txt='<select name="'.str_replace('[xxx]','[style]',$name).'">';
    $txt.='<option value="">----</option>';
    $txt.='<option value="hidden"'.($style=='hidden'?' selected':'').'>Cacher</option>';
    $txt.='<option value="invalid"'.($style=='invalid'?' selected':'').'>Invalider</option>';
    $txt.='</select>';
    return $txt;
  }
  private function _getDateUI($name, $value, $opts=NULL) {
    if(!empty($opts['free'])){
      $t='<input type="text" name="'.$name.'_free" value="'.$value.'">';
    }else{
      list($year,$month,$day)=explode('-',$value);
      $xdate=new \Seolan\Field\Date\Date();
      $t=$xdate->getJSCode($value,$name,$name,uniqid(),2);
    }
    return $t;
  }
  private function _getLinkUI($name,$value,$options,$table) {
    $r['FIELD']=$name;
    $r['FTYPE']='\Seolan\Field\Link\Link';
    $r['DTAB']='';
    $r['COMPULSORY']=false;
    $r['MULTIVALUED']=false;
    $r['TARGET']=$table;
    $o=(object)$r;
    $n=\Seolan\Core\Field\Field::objectFactory($o);
    $cond=array();
    if(!empty($options['cond'])) {
      $cond[] = $options['cond'];
    }
    if(!empty($options['moid'])) {
      $cond[] = "(modid='".$options['moid']."')";
    }
    if (!empty($cond)) {
      $n->filter = implode(' and ', $cond);
    }
    $n->checkbox=false;
    foreach ($n as $key => $v) {
      if (isset($options[$key])) {
        $n->$key = $options[$key];
      }
    }
    // pour autocompletion
    $opts = ['fmoid' => key(\Seolan\Core\Module\Module::modulesUsingTable($table))];
    if($n->multivalued) $value=implode('||',$value);
    if(is_array($value)) $value=implode('',$value);
    $e=$n->edit($value, $opts);
    $boid=\Seolan\Core\DataSource\DataSource::getBoidFromSpecs('\Seolan\Model\DataSource\Table\Table',$table);
    $url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.\Seolan\Core\Module\Module::getMoid(XMODDATASOURCE_TOID).'&function=XDSContentInput&tplentry=br&boid='.$boid.
      '&template=Module/DataSource.XDSContentInput.html&modid='.@$options['moid'];
    $e->html.=' <a class="cv8-ajaxlink" href="'.$url.'">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','new').'</a>';
    return $e->html;
  }
  private function _getLinkView($name, $value,$table) {
    $r['FIELD']=$name;
    $r['FTYPE']='\Seolan\Field\Link\Link';
    $r['DTAB']='';
    $r['COMPULSORY']=false;
    $r['MULTIVALUED']=false;
    $r['TARGET']=$table;
    $o=(object)$r;
    $n = \Seolan\Core\Field\Field::objectFactory($o);
    $e = $n->display($value);
    return $e->html;
  }
  private function _getListUI($name, $value, $labels, $values,$onchange) {
    $t = "<select name=\"$name\" $onchange >";
    foreach($labels as $i => $l) {
      if($value==$values[$i])
	$t.="<option value=\"".$values[$i]."\" selected>".$labels[$i]."</option>";
      else
	$t.="<option value=\"".$values[$i]."\">".$labels[$i].'</option>';
    }
    $t .= '</select>';
    return $t;
  }
  private function _getMultipleListUI($name, $value, $labels, $values, $onchange) {
    $size = min(count($values)+1,10);
    $t = "<select name=\"{$name}[]\" multiple $onchange size='$size'>";
    $t.='<option value="">----</option>';
    foreach($labels as $i => $l) {
      if(in_array($values[$i],$value))
	$t.="<option value=\"".$values[$i]."\" selected>".$labels[$i]."</option>";
      else
	$t.="<option value=\"".$values[$i]."\">".$labels[$i].'</option>';
    }
    return $t;
  }
  private function _getDoubleListUI($fname, $value, $labels, $values, $onchange) {
    $name1 = preg_replace('/^([^\[]+)/','$1_unselected',$fname);
    $name2 = $fname.'[]';
    $varid=getUniqID('v');

    $edit1="<select               name=\"$name1\" size=\"10\" multiple ondblclick=\"TZR.doubleAdd(this.form.elements['$name1'],this.form.elements['$name2'],false);\" class=\"doublebox\">";
    $edit2="<select id=\"$varid\" name=\"$name2\" size=\"10\" multiple ondblclick=\"TZR.doubleAdd(this.form.elements['$name2'],this.form.elements['$name1'],false);\" class=\"doublebox\">";

    $edit2.="<option value=\"\">----</option>";

    foreach($labels as $i => $l) {
      if(!in_array($values[$i],$value))
        $edit1.="<option value=\"".$values[$i]."\">".$labels[$i]."</option>";
      else
        $edit2.="<option value=\"".$values[$i]."\">".$labels[$i].'</option>';
    }

    $edit1.='</select>';
    $edit2.='</select>';

    $buttons='<button type="button" onclick="TZR.doubleAdd(this.form.elements[\''.$name1.'\'],this.form.elements[\''.$name2.'\']);return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','next').'</button>';
    $buttons.='<button type="button" onclick="TZR.doubleAdd(this.form.elements[\''.$name2.'\'],this.form.elements[\''.$name1.'\'],true);return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','previous').'</button>';
    $buttons2='<button type="button" onclick="TZR.doubleSelectOptionUp(this.form.elements[\''.$name2.'\']);return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','up','url').'</button>';
    $buttons2.='<button type="button" onclick="TZR.doubleSelectOptionDown(this.form.elements[\''.$name2.'\']);return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','down').'</button>';

    $t1="TZR.addValidator(['$varid','','','','\Seolan\Field\Link\Link']);";
    $js="<script type=\"text/javascript\">if(typeof(TZR)!='undefined') { $t1 }</script>";

    return '<table class="doublebox"><tr><td>'.$edit1.'</td><td>'.$buttons.'</td><td>'.$edit2.'</td><td>'.$buttons2.'</td></tr></table>'.$js;
  }

  /// Retourne le tableau des options avec leur valeur complète
  public function getAllValues(&$o,$ar=NULL,$block='options') {
    $options=array();
    foreach($this->_p as $n=>$v) {
      switch($v['type']){
      case 'ttext':
        $txts=\Seolan\Core\Labels::getAMsg($this->id.':'.$n);
        foreach($GLOBALS['TZR_LANGUAGES'] as $lang=>$iso) {
          if(!empty($txts[$lang])) $options[$block.'['.$n.']['.$lang.']']=str_replace("\r\n","\n",$txts[$lang]);
        }
        break;
      case 'module':
        $v=$this->get($o,$n);
        if(!empty($v)) $options[$block.'['.$n.']']=$v;
        break;
      case 'boolean':
        $v=$this->get($o,$n);
        if($v) $options[$block.'['.$n.']']=1;
        else $options[$block.'['.$n.']']=0;
        break;
      case 'table':
        $v=$this->get($o,$n);
        if($v && $v!='%') $options[$block.'['.$n.']']=$v;
        break;
      case 'dependency':
        $v=$this->get($o,$n);
        if($v['f']){
          $options[$block.'[dependency][f]']=$v['f'];
          foreach($v['dval'] as $i=>$dv){
            if ($ar['for_export']) // protéger l'operateur pour excel
              $options[$block.'[dependency][op]['.$i.']'] = '"' . $v['op'][$i] . '"';
            else
              $options[$block.'[dependency][op]['.$i.']']=$v['op'][$i];
	    $options[$block.'[dependency][dval]['.$i.']']=$v['dval'][$i];
	    $options[$block.'[dependency][style]['.$i.']']=$v['style'][$i];
	    $options[$block.'[dependency][nochange]['.$i.']']=$v['nochange'][$i];
	    $options[$block.'[dependency][val]['.$i.']']=$v['val'][$i];
	  }
	}
	break;
      default:
        $v=$this->get($o,$n);
        if($v!==NULL) $options[$block.'['.$n.']']=$v;
        break;
      }
    }
    return $options;
  }
  /**
   * NAME
   *   \Seolan\Core\Options::toJSON - serialisation vers JSON
   * DESCRIPTION
   *   Serialisation d'un ensemble d'options vers JSON
   * INPUTS
   * $s - objet contenant portant les options (Module, DataSource, Champ)
   * RETURN
   * chaîne JSON au format "nom option"=>{caractéristiques de l'option}
   ****/
  public function toJSON($s) {
    if(is_array($s)) {
      $s = json_decode(json_encode($s));
    }
    $ojson = ['@comment@'=>self::JSON_COMMENT];
    foreach($this->_p as $n =>$v) {
      $typ=$v['type'];
      $val=$this->get($s, $n);
      if($typ=='module' && is_object($val)){
        $val=$val->_moid;
      }elseif(is_array($val)){
        if($v['type']=='dependency' && $val['f']){
          // Fait un post_edit sur les valeurs et tri les regles par opérateurs
          $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->get($s,'table'));
          $defined=$orgval=array();
          foreach($val['dval'] as $i=>$foo){
	    $tmp=$x->desc[$val['f']]->post_edit($val['dval'][$i]);
            if(in_array($val['op'][$i].'-'.$tmp->raw,$defined)) continue;
            $tmp2=$s->post_edit($val['val'][$i]);
            $orgval[$val['op'][$i]]['op'][]=$val['op'][$i];
            $orgval[$val['op'][$i]]['val'][]=$tmp2->raw;
            $orgval[$val['op'][$i]]['dval'][]=$tmp->raw;
            $orgval[$val['op'][$i]]['style'][]=$val['style'][$i];
            $orgval[$val['op'][$i]]['nochange'][]=$val['nochange'][$i];
            $defined[]=$val['op'][$i].'-'.$tmp->raw;
          }
          $val['op']=array_merge((array)$orgval['=']['op'],(array)$orgval['!=']['op']);
          $val['val']=array_merge((array)$orgval['=']['val'],(array)$orgval['!=']['val']);
          $val['dval']=array_merge((array)$orgval['=']['dval'],(array)$orgval['!=']['dval']);
          $val['style']=array_merge((array)$orgval['=']['style'],(array)$orgval['!=']['style']);
          $val['nochange']=array_merge((array)$orgval['=']['nochange'],(array)$orgval['!=']['nochange']);
          $val=serialize($val);
        }else{
          foreach($val as &$v) convert_charset($v,TZR_INTERNAL_CHARSET,"UTF-8");
          $val=serialize($val);
        }
      }else{
        convert_charset($val, TZR_INTERNAL_CHARSET,"UTF-8");
      }
      $ojson[$n] = ["type"=>$typ, "value"=>$val];
    }
    return json_encode($ojson);
  }
  /**
   * NAME
   *   \Seolan\Core\Options::rawToJSON - transformation d'un tableau en bloc JSON
   * DESCRIPTION
   *   création d'un bloc json depuis un tableau
   * INPUTS
   * $ar - le tableau
   * $ch - encodage des valeurs du tableau (à voir ?)
   * RETURN
   * chaîne json
   */
  static public function rawToJSON(array $ar,$ch = TZR_INTERNAL_CHARSET) : string {
    $ojson = ['@comment@'=>self::JSON_COMMENT];
    if (!is_array($ar))
      $ar = [];
    foreach($ar as $n =>$v) {
      if($ch != "UTF-8"){
        if(is_array($val)){
          foreach($val as &$v){
	    convert_charset($v,$ch,"UTF-8");
	  }
          $val=serialize($val);
        }else{
          convert_charset($val, $ch,"UTF-8");
        }
      }      
      $ojson[$n] = ["type"=>"", "value"=>$v];                                                          }           
    return json_encode($ojson);
  }
  /**
   * NAME
   *  \Seolan\Core\Options::rawFromJSON - recupération des donnees depuis un bloc JSON
   *  DESCRIPTION
   *   Recuperation des données des options depuis un bloc xml
   * INPUTS
   * $json - chaine contenant la representation xml
   * OUTPUTS
   * return - les valeurs sont mises à jour
   */
  static public function rawFromJSON(string $json) : ?array {
    $ojson=json_decode($json, true);
    $raw=[];
    if(empty($ojson)){ 
      \Seolan\Core\Logs::critical(__METHOD__,json_last_error_msg().' "'.mb_substr($json,0,10).' ..."');
      return $raw;
    }  
    foreach($ojson as $name=>$props){
      if (in_array($name, self::JSON_META))
	continue;
      if($props['type']=='multiplelist' || $props['type']=='doublelist' || $props['type']=='dependency'){
        $uvalue=unserialize($props['value']);
	// ? charset ? 
	if(TZR_INTERNAL_CHARSET != "UTF-8"){
	  if(is_array($uvalue)){
	    foreach($uvalue as &$v) {
	      convert_charset($v,"UTF-8",TZR_INTERNAL_CHARSET);
	    }
	  }else{
	    convert_charset($uvalue,"UTF-8",TZR_INTERNAL_CHARSET);
	  }
	}
	$raw[$name] = $uvalue;
      } else {
	$raw[$name] = $props['value'];
      }
    }
    return $raw;
  }
  /** 
   * Décode des données d'option selon le format
   * Après patch Module 20190411, 
   * seul doit exister le format json + { (tableau associatif)
   */
  static function decode($options){
    if(!$options) $return=array();
    elseif(is_array($options)) $return=$options;
    elseif(substr($options,0,2)=='a:') $return=unserialize($options);
    elseif(substr($options,0,5)=='<?xml') $return=self::rawFromXML($options);
    elseif(substr($options,0,3)=='[{"') $return=self::rawFromJSONList($options);
    elseif(substr($options,0,2)=='{"') $return=self::rawFromJSON($options);
    return $return;
  }
  /**
   * Liste d'options construite par XML ou JSON
   * @param $values object Objet qui récupère les valeurs par défaut des options
   * @param $xmlorjson string chaine XML ou JSON  contenant les options
   */
  function setOptsFromXMLOrJSON(&$values, $xmlorjson) {
    if(substr($xmlorjson,0,5)=='<?xml') {
      return $this->setOptsFromXML($values, $xmlorjson);
    } elseif(substr($xmlorjson,0,3) != '[{"'){
      return $this->setOptsFromJSON($value, $xmlorjson);
    } else {
      \Seolan\Core\Logs::critical(__METHOD__,'format error');
      return;
    }
  }
  /**
   * 
   */
  function setOptsFromJSON(&$values, $json){
    $specs = json_decode($json, true);
    if (empty($specs)){
      return;
    }
    foreach ($specs['fields'] as $field) {
      $comment = $field['comment'];
      $default = $field['default'];
      $name = $field['name'];
      $this->setOpt($field['label'],
		    $field['name'],
		    $field['type'],
		    $field['options'],
		    $field['default'],
		    empty($field['group']) ? 'Specific' : $field['group'],
		    empty($field['level']) ? 'none' : $field['level']);
      if (!empty($comment)) $this->setComment($comment, $name);
      if (!empty($default)) $this->set($values, $name, $default);
    }
  }
  /**
   */
  public function __toString(){
    return sprintf("%s, options : %s", get_class($this), implode(',', array_keys($this->_p)));
  }
  /**
   * @deprecated
   */
  function setOptsFromXML(&$values, &$xml){
    $oxml = simplexml_load_string($xml);
    if (empty($oxml->field))
      return;
    foreach ($oxml->field as $field) {
      $name = (string) $field['name'];
      $type = (string) $field['type'];
      $label   = (string) $field->label;
      //$default = may be a lang indexed array
      if ($field['type'] == 'ttext'){
	$default = (array) $field->default;
      } else {
	$default = (string)$field->default;
      }
      $comment = (string) $field->comment;
      $options = (array) $field->options;
      $group = (string) $field->group;
      $level = (string) $field->level;
      $this->setOpt($label, $name, $type, $options, $default, empty($group) ? 'Specific' : $group, empty($level) ? 'none' : $level);
      if (!empty($comment)) $this->setComment($comment, $name);
      if (!empty($default)) $this->set($values, $name, $default);
    }
  }

  /**
   * @deprecated
   */
  static public function rawFromJSONList($json) {
    $ojson=json_decode($json, true);
    $r=array();
    if(empty($ojson)) { 
      \Seolan\Core\Logs::critical(__METHOD__,json_last_error_msg().' "'.mb_substr($json,0,10).' ..."');
      
       return $r;
    }  
    for($i=0; $i < sizeof($ojson); $i++) {
      $name=(string)$ojson[$i]['name'];
      $type=(string)$ojson[$i]['type'];
      $value=(string)$ojson[$i]['value'];
      if($type=='multiplelist' || $type=='doublelist' || $type=='dependency'){
          $value=unserialize($value);
      }
      if(TZR_INTERNAL_CHARSET != "UTF-8"){
	if(is_array($value)){
	  foreach($value as &$v) convert_charset($v,"UTF-8",TZR_INTERNAL_CHARSET);
	}else{
	  convert_charset($value,"UTF-8",TZR_INTERNAL_CHARSET);
	}
      }
      $r[$name]=$value;
    }
    return $r;
  }
  /**
   * @deprecated
   * NAME
   *   \Seolan\Core\Options::toXML - serialisation vers XML
   * DESCRIPTION
   *   Serialisation d'un ensemble d'options vers XML
   * INPUTS
   * $s - bloc contenant les valeurs des options
   * RETURN
   * chaine representant les options en XML
   */
  public function toXML(&$s) {
    $txt="<?xml version=\"1.0\" encoding=\"utf-8\" ?".">";
    $txt.='<properties>';
    foreach($this->_p as $n =>$v) {
      $typ=$v['type'];
      $txt.= "<field name=\"$n\" type=\"$typ\">";
      $val=$this->get($s, $n);
      if($typ=='module' && is_object($val)){
	$val=$val->_moid;
      }elseif(is_array($val)){
	if($v['type']=='dependency' && $val['f']){
	  // Fait un post_edit sur les valeurs et tri les regles par opérateurs
	  $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->get($s,'table'));
	  $defined=$orgval=array();
	  foreach($val['dval'] as $i=>$foo){
	    $tmp=$x->desc[$val['f']]->post_edit($val['dval'][$i]);
	    if(in_array($val['op'][$i].'-'.$tmp->raw,$defined)) continue;
	    $tmp2=$s->post_edit($val['val'][$i]);
	    $orgval[$val['op'][$i]]['op'][]=$val['op'][$i];
	    $orgval[$val['op'][$i]]['val'][]=$tmp2->raw;
	    $orgval[$val['op'][$i]]['dval'][]=$tmp->raw;
	    $orgval[$val['op'][$i]]['style'][]=$val['style'][$i];
	    $orgval[$val['op'][$i]]['nochange'][]=$val['nochange'][$i];
	    $defined[]=$val['op'][$i].'-'.$tmp->raw;
	  }
	  $val['op']=array_merge((array)$orgval['=']['op'],(array)$orgval['!=']['op']);
	  $val['val']=array_merge((array)$orgval['=']['val'],(array)$orgval['!=']['val']);
	  $val['dval']=array_merge((array)$orgval['=']['dval'],(array)$orgval['!=']['dval']);
	  $val['style']=array_merge((array)$orgval['=']['style'],(array)$orgval['!=']['style']);
	  $val['nochange']=array_merge((array)$orgval['=']['nochange'],(array)$orgval['!=']['nochange']);
	  $val=serialize($val);
	}else{
	  foreach($val as &$v) convert_charset($v,TZR_INTERNAL_CHARSET,"UTF-8");
	  $val=serialize($val);
	}
      }else{
	convert_charset($val, TZR_INTERNAL_CHARSET,"UTF-8");
      }
      if(isset($val)) $txt.='<value><![CDATA['.$val.']]></value>';
      else $txt.='<value />';
      $txt.='</field>';
    }
    $txt.='</properties>';
    return $txt;
  }
  /**
   * @deprecated
   * NAME
   *   \Seolan\Core\Options::rawToXML - transformation d'un tableau en bloc XML
   * DESCRIPTION
   *   création d'un bloc xml depuis un tableau
   * INPUTS
   * $ar - le tableau
   * $ch - encodage des valeurs du tableau
   * RETURN
   * chaine bloc XML
   */
  static public function rawToXML($ar,$ch = TZR_INTERNAL_CHARSET){
    $txt="<?xml version=\"1.0\" encoding=\"utf-8\" ?".">";
    $txt.='<properties>';
    if(is_array($ar)) {
      foreach($ar as $n =>$v) {
	$txt.= "<field name=\"$n\" type=\"\">";
	$val=$v;

	if($ch != "UTF-8"){
	  if(is_array($val)){
	    foreach($val as &$v) convert_charset($v,$ch,"UTF-8");
	    $val=serialize($val);
	  }else{
	    convert_charset($val, $ch,"UTF-8");
	  }
	}
	if(isset($val))
	  $txt.='<value><![CDATA['.$val.']]></value>';
	else
	  $txt.='<value />';
	$txt.='</field>';
      }
    }
    $txt.='</properties>';
    return $txt;
  }

  /**
   * @deprecated
   * NAME
   *   \Seolan\Core\Options::fromXML - recupération des donnees depuis un bloc xml
   * DESCRIPTION
   *   Recuperation des données des options depuis un bloc xml
   * INPUTS
   * $s - objet de stockage des données
   * $xml - chaine contenant la representation xml
   * OUTPUTS
   * $s - les valeurs sont mises à jour
   */
  public function fromXML(&$s, &$xml) {
    $r=self::rawFromXML($xml);
    $this->setValues($s,$r);
  }
  /**
   * @deprecated
   * NAME
   *   \Seolan\Core\Options::rawFromXML - recupération des donnees depuis un bloc xml
   * DESCRIPTION
   *   Recuperation des données des options depuis un bloc xml
   * INPUTS
   * $xml - chaine contenant la representation xml
   * OUTPUTS
   * return - les valeurs sont mises à jour
   */
  static public function rawFromXML($xml) {
    $oxml=simplexml_load_string($xml);
    $r=array();
    if(empty($oxml->field)) return $r;
    foreach($oxml->field as $o) {
      $name=(string)$o['name'];
      $type=(string)$o['type'];
      $value=(string)$o->value;
      if($type=='multiplelist' || $type=='doublelist' || $type=='dependency') $value=unserialize($value);
      $r[$name]=$value;
    }
    unset($oxml);
    return $r;
  }
}
?>
