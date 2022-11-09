<?php
namespace Seolan\Field\Color;
/// Gestion des champs de type Couleur
class Color extends \Seolan\Core\Field\Field {
  var $fcount=7;
  public static $emptied_value='#';
  function __construct($obj=NULL) {
    parent::__construct($obj) ;
  }
  private function coloredSquare(?string $color, $size="16") {
    return '<div data-toggle="tooltip" title="'.$color.'" style="display:inline-block;margin-right:10px;background-color: '.$color.';width:'.$size.'px;height:'.$size.'px;"></div>';
  }
  function my_display_deferred(&$r) {
    parent::my_display_deferred($r);
    $r->html=$this->coloredSquare($r->raw, "16");
    return $r;
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options,true);
    $name=$this->field;
    if(isset($options['intable'])) {
      $o=$options['intable'];
      $fname=$this->field."[$o]";
      $hiddenname=$this->field."_HID[$o]";
    } elseif(!empty($options['fieldname'])) {
      $fname=$options['fieldname'];
      $hiddenname=$options['fieldname'].'_HID';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field.'_HID';
    }
    $size=7;
    $maxlength=7;
    $t=htmlspecialchars($value);
    $r->raw=$value;
    $color=\Seolan\Core\Ini::get('error_color');
    $fmt=' onblur="TZR.isIdValid(\''.$r->varid.'\');"';
    if($this->compulsory) $fmt1='^(\#[0-9ABCDEFabcdef]{6})$';
    else $fmt1='^(\#[0-9ABCDEFabcdef]{6}|)$';
    $js='<script type="text/javascript">TZR.addValidator(["'.$r->varid.'",/'.$fmt1.'/,"'.addslashes($this->label).'","'.$color.'","\Seolan\Field\ShortText\ShortText"]);
    document.getElementById("'.$r->varid.'").onchange=function(evt){
    document.getElementById("'.$r->varid.'h").value=this.value;
    jQuery("#'.$r->varid.'").css("visibility","")};';
    if(!$this->compulsory)
      $js.='document.getElementById("'.$r->varid.'e").onclick=function(evt){document.getElementById("'.$r->varid.'h").value="'.static::$emptied_value.'";jQuery("#'.$r->varid.'").val("").css("visibility","hidden").change();return false;}';
    $js.='</script>';
    $tooltip =  'data-toggle="tooltip" title="'.$r->raw.'"';
    $r->html = '<span class="bordered-input">'; // pour avoir un élement clickable quand effacé / vide
    if($this->compulsory) {
      $r->html.='<input style="visibility:'.(empty($r->raw)?'hidden':'').'" name="'.$fname.'" type="color"  class="tzr-input-compulsory" maxlength="'.$maxlength.'"'.' value="'.$t.'" id="'.$r->varid.'" '.$fmt.'/>';
    }else{
      $r->html.='<input style="visibility:'.(empty($r->raw)?'hidden':'').'" name="'.$fname.'" type="color" maxlength="'.$maxlength.'" value="'.$t.'"'.' id="'.$r->varid.'" '.$fmt.'/>';
    }
    $r->html .= '</span>';
    $r->html .= '<button type="button" onclick="jQuery(\'#'.$r->varid.'\').trigger(\'click\');" class="btn btn-default btn-md btn-inverse" id="'.$r->varid.'d">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit').'</button>';
    if (!$this->compulsory){
      $r->html .= '<button type="button" class="btn btn-default btn-md btn-inverse" id="'.$r->varid.'e">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete').'</button>';
    }
    $r->html .= '<input id="'.$r->varid.'h" name="'.$hiddenname.'" type="hidden" value="'.$r->raw.'">';
    $r->html .= $js;
    return $r;
  }
  function my_query($value,$option=NULL) {
    $r = $this->_newXFieldVal($options);
    return $r;
  }
  function sqltype() {
    return 'varchar(7)';
  }
  // la "vraie" est dans le _HID
  public function post_edit($value,$options=null,&$fields_complement=NULL) {
    $r = parent::post_edit($value,$options,$fields_complement);
    if ($options !== null && isset($options[$this->field.'_HID']) && $options[$this->field.'_HID'] == static::$emptied_value){
      $r->raw = '';
    }
    return $r;
  }
  /// Recupere le texte correspondant au code couleur
  public function &toText($r) {
    if(!property_exists($r, 'text') || ($r->text===NULL)){
      $r->text=$r->raw;
    }
    return $r->text;
  }
  /// Ecriture dans un fichier excel
  function writeXLS($xl,$i,$j,$value,$format=0,$ss=NULL) {
    $xl->setCellValueByColumnAndRow($j,$i, trim($this->getSpreadSheetCellValue($value)));
  }

}
?>
