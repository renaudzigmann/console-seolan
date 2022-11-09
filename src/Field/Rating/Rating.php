<?php
namespace Seolan\Field\Rating;

class Rating extends \Seolan\Field\Real\Real {
  public $default = '0';
  public $edit_format = '^([0-9]+)$';
  public $boxsize='3';
  
  public $min = '0';
  public $max = '10';
  public $split = '1';
  public $picto_width=16;
  public $class_css='';
  public $alignright=false;
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued

  function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('alignright');
    $this->_options->delOpt('boxsize');
    $this->_options->delOpt('decimal');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'default'), 'default', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'max'), 'max', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'split'), 'split', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'class_css'), 'class_css', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'picto_width'), 'picto_width', 'text');
  }

  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    return $this->_edit($value, $options, $fields_complement);
  }
  function my_input(&$value,&$options,&$fields_complement=NULL) {
    return $this->_edit($value, $options, $fields_complement, true);
  }
  protected function _edit(&$value,&$options,&$fields_complement=NULL, $inputMode=false) {
    $r = $this->_newXFieldVal($options,true);
    $name = $this->field;
    if(isset($options['intable'])) {
      $fname = $this->field.'['.$options['intable'].']';
    } elseif(!empty($options['fieldname'])) {
      $fname = $options['fieldname'];
    } else {
      $fname = $this->field;
    }
    if (empty($this->max))
      $this->max = 10;
    if (empty($this->split))
      $this->split = 1;
    else
      $this->split = intval($this->split);
    $r->raw = $value;
    $js = '';
    $class_css = $this->class_css?$this->class_css:'';
    if ($this->compulsory && $inputMode){
      $js = 'if(typeof(TZR)!="undefined"){TZR.addValidator(["'.$r->varid.'",/(.+)/,"'.addslashes($this->label).'","'.\Seolan\Core\Ini::get('error_color').'","\Seolan\Field\Rating\Rating"]);}';
      $class_css .= ' tzr-input-compulsory';
    }
    $r->html = '<div id="container'.$r->varid.'" style="width:' . ($this->picto_width+1) * (1+ceil($this->max / $this->split)) . 'px" class="'.$class_css.'"><input id="'.$r->varid.'" name="'.$fname.'" value="0" type="hidden">';
    for ($i = 1; $i <= $this->max; $i++) {
      $r->html .= '<input name="'.$fname.'" value="'.$i.'" type="radio" class="'.$r->varid.'" '.($i==$value?' checked="checked"':'').' style="display:none" />';
    }
    $r->html .= '</div>
    <script type="text/javascript">
      jQuery(document).ready(function(){
         jQuery(".'.$r->varid.'").rating({
            cancel: \'0\',
            cancelValue: \'0\',
            split:'.$this->split.',
            callback:function(){
               var o = jQuery("#container'.$r->varid.'").data("clicked", 1);
               if (typeof(TZR)!="undefined"){
                  TZR.setElementErrorState(o.parent(), true, "'.\Seolan\Core\Ini::get('error_color').'");
               }
            }});
      });
    </script>';
    $r->html.='<script type="text/javascript">'.$js.'</script>';
    return $r;
  }
  
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options);
    $r->raw = $value;
    $this->trace(@$options['old'],$r);
    return $r;
  }

  function my_display_deferred(&$r){
    $r->min = $this->min;
    $r->max = $this->max;
    if (empty($r->max))
      $r->max = 10;
    if (empty($this->split))
      $this->split = 1;
    else
      $this->split = intval($this->split);
    $r->html = '<div style="width:' . ($this->picto_width+1) * ceil($this->max / $this->split) . 'px"'.($this->class_css?' class="'.$this->class_css.'"':'').'>';
    for ($i = 1; $i <= $r->max; $i++) {
      $current = $i == $r->raw ? ' star-rating-current' : ''; // print
      $on = $i <= $r->raw ? ' star-rating-on' : '';        
      if ($this->split > 1) {
        $spi = ($i-1) % $this->split;
        $spw = $this->picto_width / $this->split;
        $r->html .= '<div class="star-rating' . $on . $current . '" style="width:' . $spw . 'px"><a title="' . $r->raw . '" style="margin-left:-' . $spi*$spw . 'px">' . $i . '</a></div>';
      }
      else
        $r->html .= '<div class="star-rating' . $on . $current . '"><a title="' . $r->raw . '">' . $i . '</a></div>';
    }
    $r->html .= '</div>';
    return $r;
  }
  
  function getQueryText($o){
    $ret=$this->getQueryTextOp($o->op,true);
    $ret.=$o->value;
    
    return $ret;
  }

  function my_quickquery($value,$options=NULL) {
    $p=new \Seolan\Core\Param($options);
    $lang=\Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options);
    if(is_array($value)) $value=implode($this->separator,$value);
    if(isset($value)) $t1 = htmlspecialchars($value);
    else $t1='';

    $name=$this->field;
    $op=!empty($this->op)?$this->op:$p->get($name.'_op');

    if (empty($op) && $this->isFilterCompulsory($options) && !empty(@$options['fields_complement']['query_comp_field_op'])) {
      $op = @$options['fields_complement']['query_comp_field_op'];
    }

    $js=' onchange="document.quicksearch.submit();" ';
    $t='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    $t.='<select name="'.$name.'_op">';
    $t.='<option value="">-</option>';
    $t.='<option value=">"'.($op=='>'?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','more_than').'</option>';
    $t.='<option value=">="'.($op=='>='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','upper_than').'</option>';
    $t.='<option value="="'.($op=='='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','equal_to').'</option>';
    $t.='<option value="<"'.($op=='<'?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','less_than').'</option>';
    $t.='<option value="<="'.($op=='<='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','lower_than').'</option>';
    $t.='</select>';
    $boxsize=min($this->fcount, static::$boxmaxsize);

    //Dans le cas d'un champ Rating, fcount est à 0
    if ($this->fcount == 0) {
      $this->fcount = 10;
    }
    if(!empty($this->quicksearchboxsize)) $boxsize=$this->quicksearchboxsize;
    if (empty($value)) $value=0;

    $result = $this->_edit($value, $options, $options['fields_complement'], false);
    $t.= $result->html;
    $r->html=$t;
    $r->raw=$value;

    return $r;
  }
  function post_query($o,$options=NULL) {
    if(empty($o->value) && $o->value!=="0") return;
    $o->quote='';
    if(strpos($o->value,'>=')===0){
      $o->op='>=';
      $o->value=substr($o->value,2);
    }elseif(strpos($o->value,'<=')===0){
      $o->op='<=';
      $o->value=substr($o->value,2);
    }elseif(strpos($o->value,'<')===0){
      $o->op='<';
      $o->value=substr($o->value,1);
    }elseif(strpos($o->value,'>')===0){
      $o->op='>';
      $o->value=substr($o->value,1);
    }elseif(empty($o->op)){
      return;
    }
    $o->value = preg_replace('/[^0-9.]/', '', $o->value);

    parent::post_query($o, $options);
  }


  function my_query($value,$options=NULL) {
    $p=new \Seolan\Core\Param($options);
    $format=$p->get('fmt');

    if(empty($format)) $format=$p->get('qfmt');
    if(empty($format)) $format=$this->query_format;

    $labelin=$p->get('labelin');
    $lang=\Seolan\Core\Shell::getLangUser();
    $selectquery=$p->get('select','norequest');
    $r = $this->_newXFieldVal($options);
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $op=!empty($this->op)?$this->op:$p->get($fname.'_op');

    if(empty($op)) $op=$p->get('op');
    if(is_array($value)) $value=implode($this->separator,$value);
    if(isset($value)) $t1=$value;
    else $t1='';

    $r->collection = array();
    $size=min($this->fcount, static::$boxmaxsize);

    if($format=='classic') {
      $t='<select name="'.$fname.'_op">';
      $t.='<option value="">---</option>';
      $t.='<option value=">"'.($op=='>'?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','more_than').'</option>';
      $t.='<option value=">="'.($op=='>='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','upper_than').'</option>';
      $t.='<option value="="'.($op=='='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','equal_to').'</option>';
      $t.='<option value="<"'.($op=='<'?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','less_than').'</option>';
      $t.='<option value="<="'.($op=='<='?'selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','lower_than').'</option>';
      $t.='</select>';

      if (empty($value)) $value=0;

      $result = $this->my_input($value, $options);
      $t.= $result->html;
    } elseif($format=='listbox-one' || $format=='listboxwithop') {
      $lower=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','lower_than');
      $upper=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','upper_than');
      $t='';

      if(!empty($selectquery)) $allvalues=$this->_getUsedValues(NULL,$selectquery);
      else $allvalues= $this->_getUsedValues("LANG='$lang'",NULL);

      ksort ($allvalues);

      foreach($allvalues as $v=>$foo) {
	$r->collection[] = $v;
	$t.='<option value="'.$v.'">'.$v.'</option>';
	if($v==$t1) $first='<option value="'.$v.'" selected>'.$this->label.' : '.$v.'</option>';
      }

      if($format=='listboxwithop') {
	foreach($allvalues as $v=>$foo) {
	  $t.='<option value="<='.$v.'">'.$lower.' '.$v.'</option>';
	  if(('<='.$v)==$t1) $first='<option value="<='.$v.'" selected>'.$this->label.' : '.$lower.' '.$v.'</option>';
	}
	foreach($allvalues as $v=>$foo) {
	  $t.='<option value=">='.$v.'">'.$upper.' '.$v.'</option>';
	  if(('>='.$v)==$t1) $first='<option value=">='.$v.'" selected>'.$this->label.' : '.$upper.' '.$v.'</option>';
	}
      }
      if(empty($labelin)) $first='<option value="">----</option>';
      elseif(empty($first)) $first='<option value="" selected>'.$this->label.'</option>';
      else $first.='<option value="">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','n/a').'</option>';

      $t='<select id="'.$fname.'" name="'.$fname.'">'.$first.$t.'</select>';

      if($format=='listbox-one') $t.='<input type="hidden" name="'.$fname.'_op" value="=">';
    } elseif($format=='input') {
      $t.='<input type="text" id="'.$fname.'" name="'.$fname.'" size="'.$size.'" value="'.$t1.'" maxlength="'.$this->fcount.'">';

      if(!empty($labelin)) {
	$t.='<script type="text/javascript">inputInit("'.$fname.'","'.$this->label.'");</script>';
      }
    }

    $r->html=$t;
    $r->raw=$value;

    return $r;
  }
}
