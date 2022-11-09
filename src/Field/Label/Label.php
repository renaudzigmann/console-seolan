<?php
namespace Seolan\Field\Label;
/**
 * gestion d'un texte traduisible mémorisé dans la table des labels
 * lors de la création, le contenu est crée enregistré dans la table des labels
 * sous un oid / varname / label donné
 * en edition, l'ensmble des langues est proposé
 */
//class Label extends \Seolan\Field\Text\Text {
class Label extends \Seolan\Field\RichText\RichText {
  private const LABELS = 'LABELS';
  public $arrow2link=0;
  public $enabletags=0;
  public $tagglobalsearch=0;
  public $edit_format=0;
  public $selector='GLOBAL';
  public $replacementTags=null;
  public $defaultText = null; 
  protected static $_cacheidvar = null;
  protected static $_cacheid = null;
  public static $multivaluable=false; //propriété pour définir si c'est multivaluable a ne pas confondre avec $multivalued
  function initOptions() {
    parent::initOptions();
    
    foreach(['arrow2link','enabletags','tagglobalsearch','edit_format',
	     'aliasmodule','exif_source','sourcemodule',
	     'isourcemodule','fsourcemodule','usetidy','embeddedimages','xrichtextdef_auto_paragraph',
	     ] as $n){
      $this->_options->delOpt($n);
    }
    $this->_options->setOpt('[Sélecteur]','selector', 'text', null, null, 'Specific');
    $this->_options->setOpt('[Contenu initial]','defaultText', 'ttext', null, null, 'Specific');
  }
  /**
   * = display + format
   */
  function my_browse_deferred(&$r){
    $this->my_display_deferred($r);
    if(@$r->options['context']=='export') $browse_format='full';
    else $browse_format=$this->browse_format;
    $picto = '<a data-html="true" data-trigger="" tabindex="0" title="'.htmlspecialchars($this->field).'" role="button" data-toggle="popover" data-content="'.htmlspecialchars($r->html).'">'. \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'more', 'csico').'</a>';
    switch ($browse_format) {
      case 'extract':
        $lines = explode("\n", wordwrap(trim(strip_tags($r->html)), 50));
        $r->html = $lines[0];
        if (isset($lines[1])) {
          $r->html .= ' ... ' . $picto;
        }
        break;
      case 'picto':
        $r->html = empty($r->html) ? '' : $picto;
      default:
        // full
    }
  }
  /**
   * récupération du label
   * voir : tags, removejavascript 
   */
  function my_display_deferred(&$r){
    $langData = \Seolan\Core\Shell::getLangData();
    $label = getDB()->fetchOne('select LABEL from '.static::LABELS.' where KOID=? and LANG=?', [$r->raw, $langData]);
    if (!isset($label)){
      $label = '';
      \Seolan\Core\Logs::critical(__METHOD__,"unabled to find LABEL oid  '{$r->raw}' lang '$langData'");
    }
    $r->html = $label;
  }
  /**
   * boite d'édition + zones d'aide (balises de remplacement)
   */
  function my_input(&$value,&$options,&$fields_complement=NULL) {
    $r = parent::my_edit($value,$options,$fields_complement);
    if (!empty($this->replacementTags))
      $r->html .= "<br><div class='alert alert-success'><small>{$this->replacementTags}</small></div>";
    return $r;
  }
  /**
   * recupération du texte pour affichage
   * ajout des boutons d'édition 
   */
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    if (isset($options['intable'])){
      $o=$options['intable'];
      $fname=$this->field."[$o]";
      $hiddenname=$this->field."_HID[$o]";
    } else {
      $fname = $this->field;
      $hiddenname = $this->field."_HID";
    }
    $r = $this->my_display($value,$options,$fields_complement);
    $r->varid = "v{$this->field}".uniqid();
    if(defined("TZR_CKEDITOR_CONFIG"))
      $confFile = TZR_CKEDITOR_CONFIG;
    else if (\Seolan\Core\Shell::admini_mode())
      $confFile = TZR_WWW_CSX.'src/Core/public/tzrckeditorconf.js';
    else
      $confFile = TZR_WWW_CSX.'src/Core/public/tzrfockeditorconf.js';
    $cols = $this->fcount;
    $rows=min(strlen(strip_tags($t))/$cols,50);
    $rows=max(4,round($rows))+5;
    $width = ($cols*8);
    $height = ($rows*12<200?200:$rows*12);
    $toolbar = $this->toolbar_custom ?: $this->toolbar;
    $config = "{customConfig:'$confFile',entities:false,basicEntities:false,autoParagraph:false,toolbar:'$toolbar',height:$height,width:$width}";
    $complements  = "<input name='{$hiddenname}' type='hidden' value='$value'>";
    $complements .= "<div class=\"\"><button id=\"edit{$r->varid}\" onclick=\"TZR.FieldLabel.ckeditor('{$r->varid}', '{$fname}', {$config});\" type=\"button\" class=\"btn btn-default btn-md btn-inverse\">".
      \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'edit', 'csico')."</div>";
    // hiddden TZR_UNCHANGED pour les champs non edités
    $complements .= "<input id='{$r->varid}_unchanged' name='{$fname}' type='hidden' value='".TZR_UNCHANGED."'>";
    $r->html = "<div id='{$r->varid}'>{$r->html}</div> {$complements}";
    if (!empty($this->replacementTags))
      $r->html .= "<br><div class='alert alert-success'><small>{$this->replacementTags}</small></div>";
    return $r;
  }
  /**
   * création/maj du label en base
   * nom de la variable à partir de la ligne si fiche (option oid)
   */
  function post_edit($value,$options=NULL,&$fields_complement=NULL){
    if (empty(trim($value)) && $this->compulsory && !empty($this->defaultText))
      $value = $this->defaultText;
    $fieldval = $this->_newXFieldVal($options);
    $lineoid = $options['oid']??null;
    $labeloid = $options[$this->field.'_HID']??null;
    if ($labeloid != null){ // vérification label toujours en base
      $inbase = getDB()->fetchOne('select 1 from '.static::LABELS.' where koid=? and lang=?', [$labeloid, \Seolan\Core\Shell::getLangData()]);
    } 
    if ($lineoid == null){
      $u = uniqid();
      if (!empty($this->table))
	$varname = md5("$u{$this->table}{$this->field}");
      else 
	$varname = md5("$u{$this->field}");
    } else {
      $varname = md5($lineoid);
    }
    $title = "CSX_{$this->table}_{$this->field}";

    if ($value == TZR_UNCHANGED)
      $value = null;

    // passer options['LABEL']['raw'] pour les cas (le plus fréquent)
    // où 
    if ($labeloid == null || !$inbase){
      $r = static::getLabelDS()->procInput(['_options'=>['local'=>1],
					    'options'=>['LABEL'=>['raw'=>true]],
					    'LABEL'=>$value,
					    'TITLE'=>$title,
					    'SELECTO'=>$this->selector??'GLOBAL',
					    'VARIABL'=>$varname,
					    'PUBLISH'=>1
					    ]);
      $labeloid = $r['oid'];
    } else {
      $r = static::getLabelDS()->procEdit(['_options'=>['local'=>1],
					   'options'=>['LABEL'=>['raw'=>true]],
					   'oid'=>$labeloid,
					   'LABEL'=>$value,
					   'TITLE'=>$title,
					   'SELECTO'=>$this->selector??'GLOBAL',
					   'VARIABL'=>$varname,
					   'PUBLISH'=>1
					   ]);
    }
    $fieldval->raw = $labeloid;

    return $fieldval;
  }
  /// lors de la suppression de la donnée, on efface aussi le label
  function deleteVal($fieldval, $oid){
    getDB()->execute('delete from '.static::LABELS.' where koid=? and lang=?',[$fieldval->raw, \Seolan\Core\Shell::getLangData()]);
  }
  /// datasource des lables
  private static function getLabelDS(){
    return \Seolan\Core\DataSource\DataSource::objectFactoryHelper8(static::LABELS);
  }
  public static function getVariablFromId($id){
    if (static::$_cacheidvar == null){
      static::$_cacheidvar = getDB()->select('select distinct KOID, VARIABL from '.static::LABELS.' where LANG=? and TITLE like "CSX_%"', [\Seolan\Core\Shell::getLangData()])->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
    if (isset(static::$_cacheidvar[$id]))
      return static::$_cacheidvar[$id];
    else 
      return '';
  }
  public static function getLabelFromId($id){
    if (static::$_cacheid == null){
      static::$_cacheid = getDB()->select('select KOID, LABEL from '.static::LABELS.' where LANG=? and TITLE like "CSX_%"', [\Seolan\Core\Shell::getLangData()])->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
    if (isset(static::$_cacheid[$id]))
      return static::$_cacheid[$id];
    else 
      return '';
  }
}
