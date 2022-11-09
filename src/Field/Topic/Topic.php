<?php
//BDOCc* \Seolan\Core\Field\Field\Topic
// NAME
// XTopicDef -- traitement des champs lien
// DESCRIPTION
// Cette classe permet de traiter la visualisation et l'édition des liens vers des objets
//EDOC
//
namespace Seolan\Field\Topic;
class Topic extends \Seolan\Core\Field\Field {
  static private $modsUsingTable=NULL;

  function __construct($obj=NULL) {
    parent::__construct($obj);
  }

  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','default'), 'default', 'text');
    $this->_options->setOpt('Module', 'modid', 'module');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','filter'), 'filter', 'text',array('rows'=>30,'cols'=>60));
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

  function my_export($value, $options=NULL) {
    return $value;
  }
  // Un champ de type Lien n'est en edit que sur la langue par defaut.
  //
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $p = new \Seolan\Core\Param($options,array());
    $lang = \Seolan\Core\Shell::getLangUser();
    $lang_data = \Seolan\Core\Shell::getLangData();
    $r = $this->_newXFieldVal($options);
    $r->raw = $value;
    if($this->target==TZR_DEFAULT_TARGET) {
      $r->html = '';
      return $r;
    }
    $format=$p->get('fmt');
    if(isset($options['intable'])) {
      $o = $options['intable'];
      $fname=$this->field.'['.$o.']';
      $hiddenname=$this->field.'_HID['.$o.']';
    } else {
      $fname=$this->field;
      $hiddenname=$this->field.'_HID';
    }	
    // chercher la liste des label resultats
    if(!\Seolan\Core\DataSource\DataSource::sourceExists($this->target)) return $r;
    $rs=getDB()->fetchAll("select * from DICT where PUBLISHED=1 and DTAB=? ORDER BY FORDER", array($this->target));
    $first='';
    $my_flist='KOID';
    $ors=array();
    $mi=0;
    if(\Seolan\Core\Shell::admini_mode()) $maxmi=XLINKDEF_MAXLINKS;
    else $maxmi=99;
    foreach($rs as $ors) {
      if(empty($first)) $first=$ors['FIELD'];
      $oo=(object)$ors;
      if($mi<$maxmi) {
        $myliste[$ors['FIELD']]=
          \Seolan\Core\Field\Field::objectFactory($oo);
        $my_flist.=','.$ors['FIELD'];
        $mi++;
      }
    }
    
    // construction de la valeur a afficher
    $filter = $this->getFilter();
    
    if(!empty($options['filter'])) $filter='('.$options['filter'].')';
    if(!empty($filter)) $filter.=' AND ';
    
    $baseprops=\Seolan\Core\DataSource\DataSource::getProps($this->target);
    if(empty($baseprops->TRANSLATABLE)) $lang_data=TZR_DEFAULT_LANG;
    $requete = 'select distinct '.$my_flist.' from '.$this->target." where $filter LANG='".$lang_data."'".
      ' '.($first ? "order by $first":'');
    $rs2=getDB()->select($requete);
    $nb = $rs2->rowCount();
    $edit='';
    $field=$this->field;
    $collection=array();
    $oidcollection=array();
    // generation de la boite de saisie
    $opts=0;
    if($options['query_format'] || !$this->compulsory) {
      $edit=$edit. '<option value="">----</option>';
      $opts++;
    }
    $my_previousOid = '';
    $ors2=array();
    while($ors2=$rs2->fetch()) {
      $my_oid=$ors2['KOID'];
      $edit=$edit. '<option value="'.$my_oid.'"';
      if($this->multivalued || $options['query_format']) {
	$edit = $edit.(isset($value[$my_oid])?' selected ':'').'>';
      } else {
	$edit = $edit.($my_oid==$value?' selected ':'').'>';
      }
      $opts++;
      $display='';
      reset($myliste);
      $opts1=array('_published'=>'all');
      foreach($myliste as $k=>$v) {
	$o = $v->display($ors2[$k],$opts1);
	$linkcontent=$o->html;
	if(!empty($o->text)) $linkcontent=$o->text;
	if($display=='') $display.=$linkcontent;
	else $display.=' '.$linkcontent;
      }
      if($this->multivalued || $options['query_format']) {
	$r->text.=(isset($value[$my_oid])?$display:'');
      } else {
	$r->text.=($my_oid==$value?$display:'');
      }
      $oidcollection[]=$my_oid;
      $collection[]=$display;
      $edit.=$display;
    }
      
    if($this->multivalued || $options['query_format']) {
      $edit= '<select name="'.$fname.'[]" size="'.min($opts,6).'" multiple >'.$edit;
    } else {
      $edit= '<select name="'.$fname.'" >'.$edit;
    }
    $edit=$edit. '</select>';
    $edit.='<input type="text" name="'.$fname.'" value="'.$r->text.'" readonly onclick="TZR.selectTopic(\''.
      $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'\','.$this->modid.',document.editform.'.$hiddenname.', document.editform.'.$fname.')">';
    $r->oidcollection=$oidcollection;
    $r->collection=$collection;
    $r->html=$edit;
    return $r;
  }

  // Un champ de type Lien n'est en edit que sur la langue par defaut.
  //
  function my_query($value,$options=NULL) {
    $p = new \Seolan\Core\Param($options,array());
    $lang = \Seolan\Core\Shell::getLangUser();
    $lang_data = \Seolan\Core\Shell::getLangData();
    $searchmode=$p->get('searchmode');
    $selectquery=$p->get('select','norequest');
    $r = $this->_newXFieldVal($options);
    $name=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $r->raw = $value;
    if($this->target==TZR_DEFAULT_TARGET) {
      $r->html = '';
      return $r;
    }
    $format=$p->get('fmt');
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $hiddenname=$fname."_HID[$o]";

    // chercher la liste des label resultats
    if(!\Seolan\Core\DataSource\DataSource::sourceExists($this->target)) return $r;
    $rs=getDB()->select("select * from DICT where PUBLISHED=1 and DTAB='$this->target' ORDER BY FORDER");
    $first='';
    $my_flist='KOID';
    $ors=array();
    while($ors=$rs->fetch()) {
      if(empty($first)) $first=$ors['FIELD'];
      $oo=(object)$ors;
      $myliste[$ors['FIELD']]=
        \Seolan\Core\Field\Field::objectFactory($oo);
      $my_flist.=','.$ors['FIELD'];
    }

    // construction de la valeur a afficher
    $filter = $this->getFilter();
    if(!empty($options['filter'])) $filter='('.$options['filter'].')';
    if(!empty($filter)) $filter.=' AND ';
    $requete = "select distinct $my_flist from ".$this->target." where $filter LANG='".TZR_DEFAULT_LANG."'".
      ' '.($first ? "order by $first":"");
    $rs2=getDB()->select($requete);
    $nb = $rs2->rowCount();
    $checkbox_limit=$this->checkbox_limit;
    $checkbox_active=$this->checkbox;
    $checkbox = (($nb<=$checkbox_limit) || ($format=='checkbox'))&&($format!='listbox')&&($checkbox_active);
    $edit='';
    $field=$this->field;
    $collection=array();
    $oidcollection=array();
    $allvalues=NULL;
    if($searchmode=='simple') {
      $allvalues=array();
      if(!empty($selectquery)) {
	$allvalues=$this->_getUsedValues(NULL,$selectquery);
      } else {
	$allvalues=$this->_getUsedValues("LANG='".TZR_DEFAULT_LANG."'");
      }
    }
    if($checkbox) {
      $my_cols=$this->checkbox_cols;
      $edit='<table>';
      $edit.="<input type=\"hidden\" name=\"".$hiddenname."\" value=\"checkbox\"/>";
      $edit.='<tr>';$i=0;
      $edit.="<input type=\"hidden\" name=\"".$fname."[0]\" value=\"Foo\"/>";
      $ors2=array();
      $opts1=array('_published'=>'all');
      while($ors2=$rs2->fetch()) {
        $edit .='<td>';
        $koid=$ors2['KOID'];
	if(($allvalues!==NULL) && empty($allvalues[$koid])) continue;
	$display='';
	reset($myliste);
	$hi=0;
	// dans le cas ou on est en mode admin, on ne publie que les deux premiers champs
	foreach($myliste as $k => $v) {
	  if(($hi >= 2) && \Seolan\Core\Shell::admini_mode()) break;
	  $o = $v->display($ors2[$k],$opts1);
	  if($display=='') $display.=$o->html;
	  else $display.=' '.$o->html;
	  $hi++;
	}
	$checked = isset($value[$koid]);

	$edit .='<input type="checkbox" class="radio" name="'.
	  $fname.'['.$koid.']" '.($checked?' checked ':'').
	  '/>&nbsp;'.$display.'</td>';
        $i++;
        if($i>=$my_cols) {$edit.='</tr><tr>';$i=0;}
	$oidcollection[]=$koid;
	$collection[]=$display;
      }
      $edit.='</tr></table>';
    } else {
      // generation de la boite de saisie
      $opts=1;
      $edit=$edit. '<option value="">----</option>';
      $my_previousOid = '';
      $ors2=array();
      $opts1=array('_published'=>'all');
      while($ors2=$rs2->fetch()) {
	$my_oid=$ors2['KOID'];
	if(($allvalues!==NULL) && empty($allvalues[$my_oid])) continue;
	$edit=$edit. '<option value="'.$my_oid.'"';
	$edit .= (isset($value[$my_oid])?' selected ':'').'>';
	$opts++;
	$display='';
	foreach($myliste as $k => $v) {
	  $o = $v->display($ors2[$k],$opts1);
	  if($display=='') $display.=$o->html;
	  else $display.=' '.$o->html;
	}
	$oidcollection[]=$my_oid;
	$collection[]=$display;
	$edit=$edit. $display.'</option>';
      }
      $edit= '<select name="'.$fname.'[]" size="'.min(6,$opts).'" multiple >'.$edit;
      $edit=$edit. '</select>';
    }
    $r->oidcollection=$oidcollection;
    $r->collection=$collection;
    $r->html=$edit;
    return $r;
  }
  function sqltype() {
    return 'text'; 
  }
  function my_display_deferred(&$r){
    $options=&$r->options;
    $value=&$r->raw;
    $LANG_DATA = \Seolan\Core\Shell::getLangData();
    $lang = \Seolan\Core\Shell::getLangUser();
    if(empty($value)) {
      return $r;
    }
    $target=\Seolan\Core\Kernel::getTable($value);
    if(!\Seolan\Core\System::tableExists($target)) {
      return $r;
    }
    $t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$target);
    $values=$t->rDisplay($value, true, $LANG_DATA, $lang);
    if(!is_array($values)) {
      $r->raw=$value;
      return $r;
    }
    $display='';
    $cnt = count($values['fields_object']);
    if(($cnt>=2) && \Seolan\Core\Shell::admini_mode()) $cnt=2;
    for($i=0;$i<$cnt;$i++) {
      if($i==0) $display.=$values['fields_object'][$i]->html;
      else $display.='&nbsp;'.$values['fields_object'][$i]->html;
    } 
    if($this->modsUsingTable===NULL) $this->modsUsingTable=\Seolan\Core\Module\Module::modulesUsingTable($target);

    $r->text=$display;
    if(\Seolan\Core\Shell::admini_mode() && !empty($this->modsUsingTable)) {
      $r->html=$display;
      $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false);
      if($_format!='application/prince') {
	$mi=0;
	foreach($this->modsUsingTable as $mod => &$label) {
	  $mi++;
	  if($mi<=XLINKDEF_MAXLINKS) {
	    if($mi==1) $r->html.='&nbsp;[';
	    else $r->html.='|';
	    $r->html.='<a href="'.$url.'.&moid='.$mod.'&function=goto1&oid='.$value.'" title="'.$label.'">'.$mi.'</a>';
	  }
	}
	if($mi>0) $r->html.=']';
      }
      $r->title=$display;
      $r->link=$values;
    } else {
      $r->html=$display;
      $r->title=&$r->html;
      $r->link=$values;
    }
    return $r;
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL){
    $r = $this->_newXFieldVal($options);
    if(\Seolan\Core\Kernel::isAKoid($value)) $r->raw=$value;
    $koid=$options[$this->field.'_HID'];
    if(\Seolan\Core\Kernel::isAKoid($koid)) $r->raw=$koid;
    $this->trace($options['old'],$r);
    return $r;
  }
  function post_query($o,$options=NULL) {
    $p=new \Seolan\Core\Param($options,array());
    $ischeckbox=($o->hid=='checkbox')||($o->fmt=='checkbox');
    if($ischeckbox&&is_array($o->value)) {
      $nvalue=array();
      foreach($o->value as $soid => $set) {
	$nvalue[]=$soid;
      }
      $o->value=$nvalue;
    }
    return parent::post_query($o,$options);
  }

  /// Ecriture dans un fichier excel
  function writeXLS($xl,$i,$j,$value,$format=0,$ss=null) {
    $t=str_replace('&nbsp;',' ',$value->text);
    $t=preg_replace('/\[.*\]/','',$t);
    convert_charset($t,TZR_INTERNAL_CHARSET,'UTF-8');
    $xl->setCellValueByColumnAndRow($j,$i,$t);
    if(is_array($format)) $xl->getStyleByColumnAndRow($j,$i)->applyFromArray($format);
  }
}
?>
