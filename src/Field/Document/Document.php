<?php
namespace Seolan\Field\Document;
use \Seolan\Core\{Labels};
/// Champ 'Document', lien vers un document d'une base documentaire
class Document extends \Seolan\Core\Field\Field {

  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued

  function __construct($obj=NULL){
    parent::__construct($obj) ;
  }

  protected function initOptions() {
    parent::initOptions();
    $this->_options->delOpt("aliasmodule");
    $this->_options->delOpt("exif_source");
    $this->_options->setOpt(Labels::getTextSyslabel('Seolan_Module_DocumentManagement_DocumentManagement', 'modulename'),
			    'bdocmodule',
			    'module', array('toid'=>XMODDOCMGT_TOID));
  }

  function my_display_deferred(&$r){
    $options=&$r->options;
    $value=&$r->raw;

    if(empty($value)) {
      return $r;
    }
    $target=\Seolan\Core\Kernel::getTable($value);
    if(!\Seolan\Core\System::tableExists($target)) {
      return $r;
    }

    $moid=$this->bdocmodule;
    $poptions=array_merge(array('published'=>'*'),$options);
    $length = @$options['maxsize'];
    $published = @$poptions['published'];
    $_format = @$options['_format'];
    $LANG_DATA = \Seolan\Core\Shell::getLangData();
    $lang = \Seolan\Core\Shell::getLangUser();
    $t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$target);
    $myopts=array();

    if(!empty($options['_charset'])){
      $myopts['_charset']=$options['_charset'];
    }else{
      $myopts['_charset'] = \Seolan\Core\Lang::getCharset();
    }
    $myopts['fmoid']=$this->bdocmodule;
    if (isset($options['target_fields'])){
      $nvalues = array();
      $values= $t->rDisplay($value, array(), false, $LANG_DATA, $lang, $myopts);
      foreach($values['fields_object'] as $i=>$fo){
	if (in_array($fo->field, $options['target_fields'])){
	  $nvalues[] = $fo;
	}
      }
      unset($values['fields_object']);
      $values['fields_object']=&$nvalues;
      $cntm = count($values['fields_object']);
    } else {
      $values=$t->rDisplay($value, array(), true, $LANG_DATA, $lang, $myopts);
      $cntm = count($values['fields_object']);
      if($cntm>=$this->getNbPublishedFields($t) && \Seolan\Core\Shell::admini_mode()) $cntm=$this->getNbPublishedFields($t);
    }    
    if(!is_array($values)) {
      return $r;
    }
    $display='';
    $tdisplay='';
    $cnt = count($values['fields_object']);
    if(!isset($options['target_fields']) && ($cnt>=2) && \Seolan\Core\Shell::admini_mode()) $cnt=$cntm;
    for($i=0;$i<$cnt;$i++) {
      if($i==0) {
	$display.=$values['fields_object'][$i]->html;
      } else {
	$display.='&nbsp;'.$values['fields_object'][$i]->html;
      }
    } 
    if(\Seolan\Core\Shell::admini_mode())
      $r->html='<a class="cv8-ajaxlink" href="'.$GLOBALS['TZR_SESSION_MANAGER']::admin_url(false,true).'&moid='.$moid.'&function=display&template=Module/DocumentManagement.display.html&'.
	'oid='.$value.'&tplentry=br">'.$display.'</a>';
    else
      $r->html=$display;
    $r->title=$display;
    $r->link=$values;
    // Donne accès aux propriétés du document de la base doc (title, comment, icon, countdocs, countdirs...)
    $xmodbasedoc = \Seolan\Core\Module\Module::objectFactory($this->bdocmodule);
    $r->doclink = \Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($value, $xmodbasedoc);
    return $r;
  }

  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options, true);
    $opt='';
    $name=$this->field;
    $url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self();
    $moid=$this->bdocmodule;
    $r->raw=$value;
    $odisplay='';
    if(!empty($value)){
      $table=\Seolan\Core\Kernel::getTable($value);
      $t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
      $rs=getDB()->select('select * from DICT where PUBLISHED=1 and DTAB="'.$table.'" ORDER BY FORDER');
      $first='';
      $my_flist='KOID';
      $ors=array();
      $mi=0;
      $maxmi=$this->getNbPublishedFields($t);
      while($ors=$rs->fetch()) {
	if(empty($first)) $first=$ors['FIELD'];
	$oo=(object)$ors;
	if($mi<$maxmi) {
	  $myliste[$ors['FIELD']]=\Seolan\Core\Field\Field::objectFactory($oo);
	  $my_flist.=','.$ors['FIELD'];
	  $mi++;
	}
      }
      $values=getDB()->fetchRow('SELECT '.$my_flist.' FROM '.$table.' WHERE KOID="'.$value.'" LIMIT 0,1');
      if($values){
	foreach($myliste as $k=>&$v) {
	  $o=$v->display($values[$k],$p1);
	  if($display=='') $display.=$o->toText();
	  else $display.=' '.$o->toText();
	}
	unset($values);
      }else
	$value='';
    }
    // varid et textid sont imposés par modaltree
    $varid='id_'.$r->varid;
    $textid='id_INPUT'.$r->varid;
    $htmlcomp='';
    $js='';
    if($this->compulsory){
      $fmt='\w.+';
      $color = \Seolan\Core\Ini::get('error_color');
      $t1="TZR.addValidator(['$varid', /$fmt/, '".addslashes($this->label)."','$color','\Seolan\Field\Document\Document','$textid']);";
      $js="<script type=\"text/javascript\">if(typeof(TZR)!='undefined') { $t1 }</script>";
    }else{
      $htmlcomp='<button class="btn btn-default btn-md btn-inverse" type="button" onclick="TZR.setDocumentEmpty(\''.$name.'\');">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete').'</button>';
    }
    $showfiles = isset($options['directoriesOnly'])?'0':'1';
    $modalTitle = $options['title']??$this->label;
    $r->html='<input type="hidden" name="'.$name.'" value="'.$value.'" id="'.$varid.'">';
    $r->html.='<input type="text" onclick="jQuery(this).siblings(\'button\').trigger(\'click\');" value="'.$display.'" name="_INPUT'.$name.'" id="'.$textid.'" readonly="1">&nbsp;';
    $r->html.="<button type='button' class='btn btn-default btn-md btn-inverse' onclick='TZR.selectDocument(\"{$url}\",\"{$moid}\",\"{$r->varid}\",{$showfiles},\"{$modalTitle}\");'>".
	      \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit').
	      "</button>";
    $r->html.=$htmlcomp;
    if(\Seolan\Core\Shell::admini_mode()){
      $r->html.='<br><a  class="cv8-ajaxlink" href="'.$GLOBALS['TZR_SESSION_MANAGER']::admin_url(false,true).'&moid='.$moid.'&function=display&template=Module/DocumentManagement.display.html&'.
        'oid='.$value.'&tplentry=br">'.$display.'</a>';
    }else{
      $r->html.=$display;
    }
    $r->html.=$js;
    return $r;
  }

  function getNbPublishedFields(&$t){
    if(!\Seolan\Core\Shell::admini_mode()) return 99;
    return $t->published_fields_in_admin;
  }

  function sqltype() {
    return 'text';
  }

  function my_export($value, $options=NULL) {
    return $value;
  }
}
?>
