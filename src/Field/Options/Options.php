<?php
namespace Seolan\Field\Options;
class Options extends \Seolan\Core\Field\Field {
  public $separator="\n";
  public $indexable=false;

  function __construct($obj=NULL) {
    parent::__construct($obj);
    $this->fcount=50;
  }
  function initOptions() {
    parent::initOptions();
    $editgroup=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit','text');
  }
  function my_export($value) {
    return '<![CDATA['.nl2br($value)."]]>\n";
  }
  function my_display_deferred(&$r){
    $lang = \Seolan\Core\Shell::getLangUser();
    $fields=$this->createFieldsFromText($r->raw);
    $r->text=$this->getDisplayFromFields($fields);
    $r->html=nl2br($r->text);
    return $r;
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $p=new \Seolan\Core\Param($options,array());
    $lang=\Seolan\Core\Shell::getLangUser();
    $r=$this->_newXFieldVal($options,true);
    $r->varid=uniqid('v');
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
    $cols=$this->fcount;
    $t=$value;
    if(isset($options['rows'])) $rows=$options['rows'];
    if(isset($options['cols'])) $cols=$options['cols'];
    $rows=min(strlen($t)/$cols,50);
    $rows=floor(max(4,$rows));
    $html='<textarea name="'.$fname.'" cols="'.$cols.'" rows="'.$rows.'" WRAP="virtual">'.$t.'</textarea>';
    $r->html=$html;
    $r->raw=$value;
    return $r;
  }

  function my_query($value, $options=NULL) {
  }

  /// Traitement apres formulaire sur les champs calculées
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options);
    $r->raw=$value;
    utf8_cp1252_replace($r->raw);
    if(@$options['toxml']){
      \Seolan\Core\System::array2xml($r->raw,$xml);
      $r->raw=$xml;
    }
    $this->trace(@$options['old'],$r);
    return $r;
  }
  function post_edit_dup($value,$options) {
    return post_edit($value, $options);
  }
  function sqltype() {
    return 'text';
  }
  
  /// chargement des options depuis un texte
  public function createFieldsFromText($text) {
    $lines=explode("\n",$text);
    $o=array();
    $fields=array();
    // une option parline
    foreach($lines as $line) {
      $field=(object)NULL;
      $opts = explode(';',$line);
      // on traite les attributs qui permettent la création de base du champ
      foreach($opts as $opt) {
	// au format attribut=valeur
	if(!empty($opt)) {
	  list($label,$value)=explode('=',$opt,2);
	  if(!empty($label) && isset($value) && strtoupper($label)==$label) {
	    $field->$label=trim($value);
	  }
	}
      }
      if(!empty($field->FIELD)) {
	$fieldname=$field->FIELD;
	try {
	  $classe=$field->FTYPE;
	  if(class_exists($classe)) {
	    $ofield=new $classe($field);
	    // on traite les attributs qui permettent l'initialisation du champ
	    foreach($opts as $opt) {
	      // au format attribut=valeur
	      if(!empty($opt)) {
		list($label,$value)=explode('=',$opt,2);
		if(!empty($label) && isset($value) && strtoupper($label)!=$label) {
		  $ofield->$label=trim($value);
		}
	      }
	    }
	    $fields[]=$ofield;
	  }
	} catch(\Exception $e) {
	  continue;
	}
      }
    }
    return $fields;
  }

  function getEditFromFields($fields) {
    $edit='';
    foreach($fields as $field) {
      $r=$field->edit($field->getDefaultValue(),array());
      $edit.=$r->html;
    }
    return $edit;
  }
  function getDisplayFromFields($fields) {
    $text='';
    if(is_array($fields)) {
      foreach($fields as $field) {
	$text.='Champ '.$field->get_label().' type '.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field',$field->get_ftype())."\n";
      }
    }
    return $text;
  }
    
}
?>
