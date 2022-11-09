<?php
namespace Seolan\Field\Chrono;
/// Gestion des champs chrono
class Chrono extends \Seolan\Core\Field\Field {
  protected static $boxmaxsize = 10;
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  
  public $default = '0';
  function __construct($obj=NULL) {
    parent::__construct($obj) ;
  }
  function my_display(&$value,&$options,$genid=false) {
    $r=parent::my_display($value,$options,$genid);
    $r->html=$r->raw;
    return $r;
  }
  function my_browse(&$value,&$options,$genid=false) {
    $r=parent::my_browse($value,$options,$genid);
    $r->html=$r->raw;
    return $r;
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options);
    if(isset($options['intable'])) {
      $o = $options['intable'];
      $fname=$this->field.'['.$o.']';
      $hiddenname=$this->field.'_HID['.$o.']';
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field.'_HID';
    }	
    $lang = \Seolan\Core\Shell::getLangUser();
    if(!isset($value) || ($value=='')) {
      $val='new';
    } else $val=$value;
    $size = strlen($val);
    if($val=='new') $r->html='-';
    else $r->html=$val;
    $r->html.='<input type="hidden" name="'.$fname.'" value="'.$val.'" READONLY/>';
    $r->raw=$val;
    return $r;
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options);
    $lang = \Seolan\Core\Shell::getLangUser();
    if(empty($value) || !isInteger($value)) {
      $chrono=\Seolan\Core\DbIni::get('Chrono::'.$this->table.'::'.$this->field);
      $chrono=$chrono[0];
      if(!isset($chrono)) {
	// calcul d'un nouveau chrono si nécessaire
	$rs=getDB()->select('select max('.$this->field.') from '.$this->table,array(),false,\PDO::FETCH_NUM);
	if($ors=$rs->fetch()) {
	  $chrono = $ors[0];
	}
	else $chrono=0;
	$rs->closeCursor();
      }
      $chrono++;
      \Seolan\Core\DbIni::set('Chrono::'.$this->table.'::'.$this->field,$chrono);
      $val=$chrono;
    } else $val=$value;
    $r->raw=$val;
    $this->trace(@$options['old'],$r);
    return $r;
  }
  function my_query($value, $ar=null){
    $ar['_query'] = 1;
    return $this->my_quickquery($value, $ar);
  }
  function my_quickquery($value, $options=null){
    $p=new \Seolan\Core\Param($options);
    $lang=\Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options);
    if(is_array($value)) $value=implode($this->separator,$value);
    if(isset($value)) $t1 = htmlspecialchars($value);
    else $t1='';
    $name=$this->field;
    $name=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $op=!empty($this->op)?$this->op:$p->get($name.'_op');
    if (isset($options['_query'])){
      $js = $t = '';
    } else {
      $js = '';
      $t='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    } 
    $t.='<select name="'.$name.'_op">';
    $t.='<option value="">-</option>';
    $t.='<option value=">="'.($op=='>='?'selected':'').'>>=</option>';
    $t.='<option value="="'.($op=='='?'selected':'').'>=</option>';
    $t.='<option value="<="'.($op=='<='?'selected':'').'><=</option>';
    $t.='</select>';
    $boxsize=static::$boxmaxsize;
    $t.= "<input ".($this->isFilterCompulsory($options) ? 'required' : '')." type=\"text\" name=\"$name\" size=\"$boxsize\" maxlength=\"$boxsize\" value=\"$t1\" $js/>";
    $r->html=$t;
    $r->raw=$value;
    return $r;
  }
  function sqltype() {
    return 'int';
  }
  
  
  public function post_edit_dup($value, $options) {
    $p = new \Seolan\Core\Param($options,array());
    $oidsrc = $p->get('oidsrc');
    $options['oid']=$oidsrc;
    
    return $this->post_edit(null, $options);
  }
}
?>
