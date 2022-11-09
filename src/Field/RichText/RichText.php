<?php
namespace Seolan\Field\RichText;

/// Champ de gestion de texte enrichi
class RichText extends \Seolan\Field\Text\Text {
  public $toolbar='Basic';
  public $toolbar_custom='';
  public $tidy=true;
  public $indexable=true;
  public $embeddedimages=false;
  public $autoparagraph=true;
  public $sourcemodule=null;
  function __construct($obj=NULL) {
    parent::__construct($obj);
    if($this->sourcemodule){
      \Seolan\Core\Alias::register($this->sourcemodule);
    }
  }

  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','toolbar'), 'toolbar', 'list',
	array('values'=>array('Complete','Accessibility','Basic','Mail'),
    'labels'=>array(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','rtd_complete'),
    \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','rtd_accessibility'),
    \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','rtd_basic'),
    \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','rtd_mail'))));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','toolbar_custom'), 'toolbar_custom', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','sourcemodule'), 'sourcemodule', 'module');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','isourcemodule'), 'isourcemodule', 'module');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','fsourcemodule'), 'fsourcemodule', 'module');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','usetidy'), 'tidy', 'boolean',NULL, true);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','embeddedimages'), 'embeddedimages', 'boolean',NULL, false);
    $querygroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General_General','query');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','indexable'), 'indexable', 'boolean', NULL, true, $querygroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xrichtextdef_auto_paragraph'), 'autoparagraph', 'boolean',NULL, true);
  }
  /// Génére une zone CKEditor
  static function getCKEditor($value,$name,$varid,$w,$h,$tb='Basic',$conf=null,$autoparagraph='true', $field=null){
    if(empty($varid)) $varid=uniqid('v');
    // Le fichier de conf peut être forcé dans le local.php, ici il y est pour le Diocèse en version quasi complète.
    // On fait quand même un exception pour les minisite qui auront eux une version qui sera définie dans les propriétés de chaque champ texte enrichi.
    if(defined("TZR_CKEDITOR_CONFIG"))
      $confFile = TZR_CKEDITOR_CONFIG;
    else if (\Seolan\Core\Shell::admini_mode())
      $confFile = TZR_WWW_CSX.'src/Core/public/tzrckeditorconf.js';
    else
      $confFile = TZR_WWW_CSX.'src/Core/public/tzrfockeditorconf.js';
    $fck='<textarea class="xrichtext" id="'.$varid.'" name="'.$name.'">'.htmlentities($value,ENT_COMPAT,TZR_INTERNAL_CHARSET).'</textarea>';

    if ($tb=='Mail'){
        $fck.="<script type=\"text/javascript\">CKEDITOR.replace('$varid',{customConfig:'$confFile', toolbar_Mail:[['base64image', 'Bold','Italic','Underline','-','Scayt','-','NumberedList','BulletedList','-','Outdent','Indent','-','Link','Unlink','-','RemoveFormat','-','Maximize','Source','-','About']],extraPlugins:'base64image', toolbar:'Mail', height:$h, width:$w".(!empty($conf)?(','.$conf):'')."});</script>";
    } else {
        $fck.="<script type=\"text/javascript\">var editor = CKEDITOR.replace('$varid',{customConfig:'$confFile',autoParagraph:".$autoparagraph.",toolbar:'$tb',height:$h,width:$w".(!empty($conf)?(','.$conf):'')."}); editor.on('change',function(){jQuery('#$varid').change()});</script>";
	// le script fck doit être seul dans son container
	if ($field && $field->enabletags){
	  list($tagsuser, $tagstext) = $field->allowedTags();
	  if ($tagsuser || $tagstext){
	    $url = TZR_AJAX8.'?class=_Seolan_Field_Tag_Tag&function=tag_autocomplete&add_prefix=1';
	    $title = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'delete');
	    $url2 = TZR_AJAX8.'?class=_Seolan_Field_Text_Text&function=user_autocomplete';
	    $url2 .= '&usertag_table='.$field->usertag_table;
	    $url2 .= '&usertag_id_field='.$field->usertag_id_field;
	    $url2 .= '&usertag_name_field='.$field->usertag_name_field;
	    $url2 .= '&usertag_mail_field='.$field->usertag_mail_field;
	    $fck .= '<script type="text/javascript" language="javascript">jQuery("#'.$varid.'").data("autocomplete", {url:"'.$url.'",url2:"'.$url2.'", callback:add_input_tag, params:{id:"'.$varid.'", title:"'.$title.'"}});TZR.addAutoCompleteTagCKE("'.$varid.'",'.json_encode(['users'=>$tagsuser, 'texts'=>$tagstext]).');</script>';
	  }
	}
    }
    return $fck;
  }


  function my_display_deferred(&$r){
    $r->html=$r->raw;
    if($this->arrow2link) $this->_mklinks2($r->html);
    if ($this->interpretsmileys) $this->_interpretSomeSmileys($r->html);
    if ($this->enabletags) $this->_userlinks($r);
    if(\Seolan\Core\Shell::admini_mode()) {
      // en mode admini on supprime les codes js pour qu'ils n'interragissent pas avec ceux du BO
      $r->html= removeJavascript($r->html);
    }
    return $r;
  }
  
  /**
   * surcharge du traitement des tags : les tags sont insérés dans des balises cachées
   */
  function _userlinks($r){
    if (!isset($r->subscollection))
      $r->subscollection = [];
    $r->html = preg_replace_callback('/\B'.\Seolan\Field\Text\Text::$USERTAG_PREFIX.'([^\(]*)\(([^\|]*)\|([^\)]+)\)/', function ($matches) use($r) {return $this->replace_user_link($matches, $r);}, $r->html);
    $r->html = preg_replace_callback('/<span class="cketag" id="cke\S+">'.\Seolan\Field\Tag\Tag::$TAG_PREFIX.'([^\s<]+)/', function ($matches) use($r){return $this->replace_tag_link($matches);}, $r->html);
    
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $p = new \Seolan\Core\Param($options,array());
    $lang = \Seolan\Core\Shell::getLangUser();
    $r = $this->_newXFieldVal($options);
    $FIELD=$this->field;
    $cols=$this->fcount;
    $t=$value;
    $t=$this->_mklinks3($t);
    if ($this->enabletags) $t=$this->_usertexts($t);
    $rows=min(strlen(strip_tags($t))/$cols,50);
    $rows=max(4,round($rows))+5;
    if(isset($options['rows'])) $rows=$options['rows'];
    if(isset($options['cols'])) $cols=$options['cols'];
    $toolbar=$this->toolbar;
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

    if (\Seolan\Core\Shell::admini_mode()) {

      $lparams = $iparams = ['callback'=>'gofck'];

      if(!empty($this->isourcemodule))
	$iparams['ajaxmoid'] =$this->isourcemodule;
      else
	$iparams['toid'] = [XMODMEDIA_TOID, XMODTABLE_TOID];
      $imageBrowserURL = $this->getModulesFilePickerHtml($r, $iparams);
      
      if(!empty($this->fsourcemodule))
	$lparams['ajaxmoid'] = $this->fsourcemodule;
      else
	$lparams['toid'] = [XMODMEDIA_TOID, XMODTABLE_TOID];
      $linkBrowserURL = $this->getModulesFilePickerHtml($r, $iparams);
      
      $aliasBrowserURL=$GLOBALS['TZR_SESSION_MANAGER']::complete_self()
		      .'function=home&template=Module/InfoTree.popaction.html&tplentry=mit&_raw=1&_skip=1&'
		      .'do=showtree&action=fck&maxlevel=1';

      if(!empty($this->sourcemodule))
	$aliasBrowserURL.='&moid='.$this->sourcemodule;
      else
	$aliasBrowserURL.='&moid='.\Seolan\Core\Ini::get('corailv3_xmodinfotree');

      $conf='filebrowserImageBrowseUrl:"'.$imageBrowserURL.'",filebrowserBrowseUrl:"'.$linkBrowserURL.'",filebrowserAliasBrowseUrl:"'.$aliasBrowserURL.'"';
    }
    $autoparagraph = $this->autoparagraph == 1 ? 'true' : 'false';
    $r->html=static::getCKEditor($t,$fname,NULL,($cols*8),($rows*12<200?200:$rows*12),($this->toolbar_custom?:$this->toolbar),$conf,$autoparagraph, $this);
    $r->raw=$value;
    return $r;
  }

  /// Traitement apres formulaire sur les champs calculées
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r = $this->_newXFieldVal($options);
    if($this->arrow2link) $this->_normalizelinks($value);
    if($this->enabletags) $this->_normalizetags($value, $this->tidy);
    if(!empty($this->exif_source) && empty($value)){
      $r->raw=$this->getMetaValue($fields_complement);
    } else {
      if(strpos($value,'||urlencoded||')!==FALSE) $r->raw=unicode_decode(str_replace('||urlencoded||','',$value));
      else $r->raw=$value;
    }
    utf8_cp1252_replace($r->raw);
    if($this->tidy) tidyString($r->raw,array('show-body-only'=>true,'output-xhtml'=>true));
    $this->trace(@$options['old'],$r);
    return $r;
  }

  function post_edit_dup($value,$options) {
    $r = $this->_newXFieldVal($options);
    if(strpos($value,'||urlencoded||')!==FALSE) return $r->raw=unicode_decode(str_replace('||urlencoded||','',$value));
    else $r->raw=$this->_normalizelinks($value);
    return $r;
  }

  /**
   * {@inheritDoc}
   * @see \Seolan\Field\Text\Text::_normalizelinks()
   */
  function _normalizelinks(&$text, $backslashed = false) {
    if (empty($text))
      return $text;
    parent::_normalizearrowlinks($text, $backslashed);
    $this->_normalizehtmllinks($text);
    if ($backslashed)
      $text = addslashes($text);
    return $text;
  }
  /**
   * conserve les attributs de la balise html (class en particulier) 
   * @param string $text
   */
  protected function _normalizehtmllinks(string &$text) {
    // recherche et vérification de l'alias
    $dom = new \DOMDocument();
    // pour pas récupérer d'élément <p> englobant et pas avoir d'entités
    $wrapperid = uniqid();
    $text = "<!doctype html><html><head><meta charset='utf-8'></head><body><div id='{$wrapperid}'>{$text}</div></body></html>";
    
    $dom->loadHTML($text);

    $xpath = new \DomXPath($dom);
    // lien [alias] ou déjà encodé [moid,itoid] ? ou moid,itoid ? : |[0-9]+,[0-9a-zA-Z:]
    $linkexp = '@(https?://)?\[?(?<alias>[_[:alnum:]-]+)\]?$@';
    foreach($xpath->query('//a') as $node){
      if (! $node->hasAttribute('href'))
        continue;
      $href = trim($node->getAttribute('href'));
      if (substr($href, 0, 1) != '[' || substr($href, - 1) != ']')
        continue;
      // url <> (https://)[alias] et récupération de l'alias
      $regs=null;
      if (! preg_match($linkexp, $href, $regs))
        continue;
      $href = $regs['alias'];
      list($moid,$itoid) = \Seolan\Core\Alias::getInternalRep($href, $this->aliasmodule);
      if (empty($moid) || empty($itoid)){
        $tzrclass = "tzr-errorlink";
      }else{
        $tzrclass = "tzr-internallink";
        $node->setAttribute('href', "[$moid,$itoid]");
      }
      if ($node->hasAttribute('class')){
        $cssclass = $node->getAttribute('class');
        $cssclasses = preg_split('/ /', $cssclass, - 1, PREG_SPLIT_NO_EMPTY);
        // on efface les traces d'une ancienne normalisation
        foreach(['tzr-errorlink','tzr-internallink'] as $oldtzrclass){
          if (in_array($oldtzrclass, $cssclasses)){
            $cssclass = str_replace($oldtzrclass, '', $cssclass);
          }
        }
        $node->setAttribute('class', "{$cssclass} {$tzrclass}");
      }else{
        $node->setAttribute('class', $tzrclass);
      }
    }
    $text = "";
    foreach($dom->getElementById($wrapperid)->childNodes as $node){
      $text .= $dom->saveXML($node); // ! saveHTML encode les href ...
    }
  }
}
function xrichtextdef_getFCKEditor(){
  $p=new \Seolan\Core\Param($ar,NULL);
  $oid=$p->get('oid');
  $field=$p->get('field');
  $value=$p->get('value');
  $name=$p->get('name');
  $GLOBALS['XSHELL']=new \Seolan\Core\Shell();
  $GLOBALS['XSHELL']->labels=new \Seolan\Core\Labels();
  $table=\Seolan\Core\Kernel::getTable($oid);
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
  if($name) $xds->desc[$field]->field=$name;
  $e=$xds->edit(array('oid'=>$oid,'selectedfields'=>array($field),'tplentry'=>TZR_RETURN_DATA));
  die($e['o'.$field]->html);
}
?>
