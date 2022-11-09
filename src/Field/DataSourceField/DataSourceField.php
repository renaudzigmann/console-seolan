<?php
namespace Seolan\Field\DataSourceField;
class DataSourceField extends \Seolan\Core\Field\Field{
  public $doublebox=false;
  public $withorder=false;
  public $allowrandom=false;

  function __construct($obj=NULL) {
    parent::__construct($obj) ;
  }

  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','doublebox'), 'doublebox', 'boolean',NULL,false);
  }

  /// Type SQL du champ
  function sqltype() {
    return 'text';
  }

  /// Afichage de la valeur
  function my_display_deferred(&$r){
    if($this->target==TZR_DEFAULT_TARGET) return $r;
    if(!\Seolan\Core\System::tableExists($this->target)) return $r;
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->target);
    if(isset($x->desc[$r->raw])) $r->html=$x->desc[$r->raw]->label;
    else $r->html='';
    return $r;
  }

  /// Edition du champ
  function my_edit(&$value,&$options,&$fields_complement=NULL){
    $r=$this->_newXFieldVal($options);
    $r->raw=$value;
    if($this->target==TZR_DEFAULT_TARGET) return $r;
    if(!\Seolan\Core\System::tableExists($this->target)) return $r;
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
    if($this->multivalued && $this->doublebox)
      $this->getDoubleSelect($value,$options,$r,$fname,$hiddenname);
    else{
      if(is_array($value)) 
	$value=array_keys($value);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->target);
      $txt=$x->fieldSelector(['fieldname'=>$fname,
			      'compulsory'=>$this->compulsory,
			      'multivalued'=>$this->multivalued,
			      'value'=>$value,
			      'filter'=>$options['filter']??null
      ]);
      $r->html=$txt;
    }
    return $r;
  }

  // Edition du champ sous la forme d'une double liste déroulante
  function getDoubleSelect(&$value,&$options,&$r,$fname,$hiddenname){
    if (!isset($r->varid)){
      $r->varid = uniqid();
    }
    $filters = $options['filter']??null;
    $asc=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','asc');
    $desc=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','desc');
    $search1='<input id="'.$r->varid.'_filter_unselected" type="search" class="doublebox-filter" data-doublebox-selector=".doublebox-'.$fname.'-unselected" />';
    $edit1='<select name="'.$fname.'_unselected" size="6" ondblclick="TZR.doubleAdd(this.form.elements[\''.$fname.'_unselected\'],'.
      'this.form.elements[\''.$fname.'[]\'])" class="doublebox doublebox-'.$fname.'-unselected doublebox-with-filter" multiple>';
    $varid=$r->varid.'_id';
    $search2='<input id="'.$r->varid.'_filter_selected" type="search" class="doublebox-filter" data-doublebox-selector=".doublebox-'.$fname.'-selected" />';
    $edit2='<select name="'.$fname.'[]" size="6" multiple id="'.$varid.'" ondblclick="TZR.doubleAdd(this.form.elements[\''.$fname.'[]\'],'.
      'this.form.elements[\''.$fname.'_unselected\'],true)" class="doublebox doublebox-'.$fname.'-selected doublebox-with-filter">';
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->target);
    $order=array();
    $i=0;
    if ($this->withorder && $this->allowrandom && !in_array('rand()',array_keys($value))) {
      $edit1.='<option value="rand()" order="0">('.\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','random').')</option>';
    }
    foreach($x->desc as $k=>&$v) {
      if(!empty($options['except']) && is_array($options['except']) && in_array($k,$options['except'])) continue;
      if ($filters != null){ // similaire aux filtres de DataSource::fieldSelector
	$ok = true;
        foreach($filters as $prop=>$val){
	  if(is_array($val)){
	    if (is_callable($val[0])){
	      $f = $val[0];
	      if (!$f($v, $prop, $val[1])){
		$ok = false;
		break;
	      }
	    } else {
	      if(!eval('return (\''.$val[1].'\''.$val[0].'\''.$v->$prop.'\');' ) ){
		$ok=false;
		break;
	      }
	    }
	  }else{
            if($v->$prop!=$val){
              $ok=false;
              break;
            }
          }
        }
	if (!$ok){
	  continue;
	}
      }
      if($this->withorder){
        $i++;
        $checked=(isset($value[$k]) || isset($value[$k.' ASC']));
        if(!$checked) $edit1.='<option value="'.$k.'" order="'.$i.'">'.$v->get_label().' ('.$asc.')</option>';
        else $order[$k]=$i;
        $i++;
        $checked=(isset($value[$k.' DESC']));
        if(!$checked) $edit1.='<option value="'.$k.' DESC" order="'.$i.'">'.$v->get_label().' ('.$desc.')</option>';
        else $order[$k.' DESC']=$i;
      } elseif ($this->onlyqueryable) {
        if ($v->queryable) {
          $i++;
          $checked=isset($value[$k]);
          if(!$checked) 
            $edit1.='<option value="'.$k.'" order="'.$i.'">'.$v->get_label().'</option>';
          else 
            $order[$k]=$i;
        }
      }else{
        $i++;
        $checked=isset($value[$k]);
        if(!$checked) $edit1.='<option value="'.$k.'" order="'.$i.'">'.$v->get_label().'</option>';
        else $order[$k]=$i;
      }
    }
    unset($v);
    foreach($value as $v=>$foo){
      if(is_array($options['except']) && in_array($v,$options['except'])) continue; 
      if(!$this->withorder){
	if(isset($x->desc[$v])) $edit2.='<option value="'.$v.'" order="'.$order[$v].'">'.$x->desc[$v]->get_label().'</option>';
      }else{
	list($f,$o)=explode(' ',$v);
	if(isset($x->desc[$f])){ 
	  if($o=='DESC') $edit2.='<option value="'.$f.' DESC" order="'.$order[$f.' DESC'].'">'.$x->desc[$f]->get_label().' ('.$desc.')</option>';
	  else $edit2.='<option value="'.$f.'" order="'.$order[$f].'">'.$x->desc[$f]->get_label().' ('.$asc.')</option>';
	} elseif ($f == 'rand()' && $this->allowrandom) {
	  $edit2.='<option value="'.$f.'" order="'.$order[$f].'">('.\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','random').')</option>';
	}
      }
    }
    $edit1.='</select>';
    $edit2.='</select>';
    $buttons='<button class="btn btn-default" type="button" onclick="TZR.doubleAdd(this.form.elements[\''.$fname.'_unselected\'],this.form.elements[\''.$fname.'[]\']);return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','next').'</button>';
    $buttons.='<br/>';
    $buttons.='<button class="btn btn-default" type="button" onclick="TZR.doubleAdd(this.form.elements[\''.$fname.'[]\'],this.form.elements[\''.$fname.'_unselected\'],true);return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','previous').'</button>';
    $buttons2='<button class="btn btn-default" type="button" onclick="TZR.doubleSelectOptionUp(this.form.elements[\''.$fname.'[]\']);return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','up').'</button>';
    $buttons2.='<br/>';
    $buttons2.='<button class="btn btn-default" type="button" onclick="TZR.doubleSelectOptionDown(this.form.elements[\''.$fname.'[]\']);return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','down').'</button>';
    $hidd='<input type="hidden" name="'.$hiddenname.'" value="doublebox"/>';
    $color=\Seolan\Core\Ini::get('error_color');
    if($this->compulsory) $t1="TZR.addValidator(['$varid',/(.+)/,'".addslashes($this->label)."','$color','\Seolan\Field\Link\Link']);";
    else $t1="TZR.addValidator(['$varid','','".addslashes($this->label)."','$color','\Seolan\Field\Link\Link']);";
    $t2 = 'TZR.DoubleBox.initFilter(jQuery("#'.$r->varid.'_filter_selected"));';
    $t2 .= 'TZR.DoubleBox.initFilter(jQuery("#'.$r->varid.'_filter_unselected"))';
    $js="<script type=\"text/javascript\">if(typeof(TZR)!='undefined') { $t1 $t2 }</script>";
    $edit='<table class="doublebox width-auto"><tr><td colspan="2">'.$search1.'</td><td colspan="2">'.$search2.'</td></tr><tr><td>'.$edit1.'</td><td>'.$buttons.$hidd.'</td><td>'.$edit2.'</td><td>'.$buttons2.'</td></tr></table>'.$js;
    $r->html=$edit;
  }

  /// Recherche sur le champ
  function my_query($value,$options=NULL){
    return $this->my_edit($value,$options);
  }
}
?>
