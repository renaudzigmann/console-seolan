<?php
namespace Seolan\Field\Query;
class Query extends \Seolan\Core\Field\Field {
  public $arrow2link=true;
  function __construct($obj=NULL) {
    parent::__construct($obj);
  }
  function my_display(&$value,&$options,$genid=false) {
    $r=parent::my_display($value,$options,$genid);
    $lang = \Seolan\Core\Shell::getLangUser();
    $r->html = nl2br($r->html);
    $r->raw = $value;
    return $r;
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL){
    $p = new \Seolan\Core\Param($options,array());
    $lang = \Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options);
    if(isset($options['intable'])) {
      $o = $options['intable'];
      $fname=$this->field."[$o]";	
      $hiddenname=$this->field."_HID[$o]";
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field."_HID";
    }

    $cols=$this->fcount;
    $t=$value;
    $rows=10;
    $cols=60;
    $html = "<textarea name=\"$fname\" cols=$cols rows=$rows WRAP=virtual>".$t."</textarea>";
    $r->html = $html;
    $r->raw = $value;
    return $r;
  }
  function my_query($value, $options=NULL) {
    $r = $this->_newXFieldVal($options);
    if(is_array($value)) $value=implode($value);
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $FCOUNT=$this->fcount;
    $t=htmlspecialchars($value);
    $r->html= '<textarea name="'.$fname.'" cols="'.$FCOUNT.'" rows="4" WRAP="virtual">'.
      $t.'</textarea>'; 
    return $r;
  }
  function sqltype() {
    return "text";
  }
  
}
?>
