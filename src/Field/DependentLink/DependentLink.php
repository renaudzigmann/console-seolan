<?php
namespace Seolan\Field\DependentLink;

define('XLINK2DEF_MAXLINKS',3);
define('XLINK2DEF_MAXPOPUPLINKS',100);
define('XLINK2DEF_MAXLINKEDFIELD',1);

/// Gestion des champs Lien entre les objets des tables
class DependentLink extends \Seolan\Core\Field\Field {
  public $filter='';
  public $query='';
  public $boxsize=6;
  public $usedefault=true;
  public $checkbox=false;
  public $doublebox=false;
  public $checkbox_limit=0;
  public $autocomplete=false;
  public $autocomplete_limit=9999;
  public $generate_link = true;
  public $checkbox_cols=3;
  protected $modsUsingTable=NULL;
  public $query_formats=array('classic','listbox-one','listbox','autocomplete');
  public $advanceeditbatch=true;
  public $display_format='';
  public $linkedfields1=NULL;
  public $grouplist=false;
  public $autocomplete_minlength=3;
  public $edit_format='';
  protected $fieldlist=NULL;
  
  function __construct($obj=NULL) {
    parent::__construct($obj);
    // dans cette version, on ne génère que des selects (simples ou doubles)
  }

  function initOptions() {
    parent::initOptions();
    foreach(['dependency','aliasmodule','checkbox','checkbox_limit','checkbox_cols','autocomplete','autocomplete_limit','grouplist'] as $optname){
      $this->_options->delOpt($optname);
    }
    $this->_options->delOpt('aliasmodule');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','sourcemodule'),'sourcemodule','module',array('emptyok'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','usedefault'), "usedefault", "boolean", array(),true);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','filter'), 'filter', 'text',array('rows'=>2,'cols'=>60));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','query'), 'query', 'text',array('rows'=>2,'cols'=>60));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','display_format'), 'display_format', 'text',['size'=>50,'default'=>'']);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format'),'edit_format','text',['size'=>50, 'default'=>'']);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','doublebox'), 'doublebox', 'boolean',NULL,false);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','boxsize'), 'boxsize', 'text', NULL, 6);

    for($i=1; $i<=XLINK2DEF_MAXLINKEDFIELD; $i++) {
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','linkedfield').' '.$i, 
			      'linkedfields'.$i, 
			      'field', 
			      array('table'=>$this->table, 'compulsory'=>false, 'type'=>array('\Seolan\Field\DependentLink\DependentLink')), 
			      '', 
			      \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', '\seolan\field\dependentlink\dependentlink'));
    }
  }

  /**
   * Génère le formulaire d'édition des options du champs
   * @see \Seolan\Core\Options::getDialog()
   */
  function getOptions($block='opt', $get_edit=true) {
    if ($get_edit && $this->target != '%') {
      // Ajoute la liste des champs utilisables pour l'affichage des données
      $fields = $textFields = [];
      $targetDS = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->target);
      foreach ($targetDS->desc as $field => $ofield) {
        $fields[] = $field;
        if (!in_array($ofield->ftype, $this->display_text_unwanted_ftypes)) {
          $textFields[] = $field;
        }
      }
      $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','tokens_available').' :<br>%_'.implode('<br>%_',$fields),'display_format');
      $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','tokens_available').' :<br>%_'.implode('<br>%_',$textFields),'edit_format');
    }
    return parent::getOptions($block, $get_edit);
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

  function getValues($filter2, $options=array()) {
    $mod=&$options['mod'];
    $max=$options['max'];
    $lang=\Seolan\Core\Shell::getLangUser();
    $lang_data=\Seolan\Core\Shell::getLangData();
    $result=array();
    if($this->target==TZR_DEFAULT_TARGET || !\Seolan\Core\DataSource\DataSource::sourceExists($this->target)) return $result;

    // Liste des champs à recuperer
    list($myliste,$my_flist,$first)=$this->getFieldList($options,false);

    // Création de la requete
    $textfilter2=(count($myliste)>0)?'concat(':'';
    $fromcomp='';
    $i=0;
    foreach($myliste as $fn=>$obj){
      $i++;
      if($filter2){
	if(in_array($obj->get_ftype(),array('\Seolan\Field\Text\Text','\Seolan\Field\ShortText\ShortText','\Seolan\Field\RichText\RichText')))
	  $textfilter2.='ifnull('.$this->target.'.'.$fn.',""),\' \',';
	elseif($obj->isLink() && !empty($obj->target) && $obj->target!='%'){
	  $tablealias=$obj->target.'_'.$i;
	  $fromcomp.=' left outer join '.$obj->target.' as '.$tablealias.' on '.$tablealias.'.KOID='.$this->target.'.'.$fn.' and '.$tablealias.'.LANG="'.TZR_DEFAULT_LANG.'" ';
	  $rslink=getDB()->select('select * from DICT where PUBLISHED=1 and DTAB="'.$obj->target.'" and FTYPE="\\Seolan\\Field\\ShortText\\ShortText" ORDER BY FORDER');
	  while($rslink && ($orslink=$rslink->fetch())) $textfilter2.='ifnull('.$tablealias.'.'.$orslink['FIELD'].',""),\' \',';
	  $rslink->CloseCursor();
	}elseif($obj->get_ftype()=='\Seolan\Field\StringSet\StringSet'){
	  $tablealias='SETS_'.$i;
	  $fromcomp.=' LEFT OUTER JOIN SETS AS '.$tablealias.' ON '.$tablealias.'.SOID='.$this->target.'.'.$fn.' AND '.
	    $tablealias.'.SLANG="'.TZR_DEFAULT_LANG.'" AND '.$tablealias.'.STAB="'.$this->target.'" AND '.$tablealias.'.FIELD="'.$fn.'" ';
	  $textfilter2.='IFNULL('.$tablealias.'.STXT,""),\' \',';
	}
      }
    }
    if($textfilter2) $textfilter2 ='('.substr($textfilter2,0,-5).') LIKE ' . getDB()->quote("%$filter2%") . ') AND ';
    if (\Seolan\Core\Kernel::isAKoid($filter2)) {
      $textfilter2 = "{$this->target}.KOID = " . getDB()->quote($filter2) . ' and ';
    }
    $filter=$this->getFilter();
    if(!empty($options['filter'])) $filter='('.$options['filter'].')';
    if(!empty($filter)) $filter.=' AND ';
    $rs2=getDB()->select('select distinct '.$my_flist.' FROM '.$this->target.' '.$fromcomp.
		     ' WHERE '.$textfilter2.' '.$filter.' '.$this->target.'.LANG="'.TZR_DEFAULT_LANG.'" '.
		     (!empty($myliste)?'order by '.$this->target.'.'.implode(','.$this->target.'.',array_keys($myliste)):''));

    // Generation de la boite de saisie
    $state='ok';
    $tot=0;
    $opts=array('_published'=>'all', '_charset'=>$options['_charset']);
    while($ors2=$rs2->fetch()) {
      if(!empty($max) && $tot>$max){
	$state='toomuch';
	break;
      }
      $my_oid=$ors2['KOID'];
      if(!empty($mod) && $mod->object_sec && \Seolan\Core\Kernel::getTable($my_oid)==$mod->table && !$mod->secure($my_oid,':ro')) continue;
      $result[$my_oid]=trim($this->format_display($myliste,$ors2,$opts,null,'text'));
      $tot++;
    }
    return array('values'=>$result,'state'=>$state);
  }

  function sqltype() {
    return 'text';
  }

 /**
  * Affichage du champ lien, appel rDisplay sur la cible
  */
  function my_display_deferred(&$r){
    $options=&$r->options;
    $value=&$r->raw;

    $_format = @$options['_format'];
    $LANG_DATA = \Seolan\Core\Shell::getLangData();
    $lang = \Seolan\Core\Shell::getLangUser();
    $olddisplayformat=NULL;

    // OID valide
    if(empty($value) || strpos($value,'VOID:')) return $r;
    // l'oid sans la cible
    if(!empty($options['nofollowlinks'])) {
      $r->raw=$value;
      $r->html='';
      return $r;
    }
    // Table existe
    $target=\Seolan\Core\Kernel::getTable($value);
    if(!\Seolan\Core\System::tableExists($target)) return $r;
    // Droit sur le module source de données
    if($this->sourcemodule){
      $mod=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      if($mod->object_sec && !$mod->secure($value,':ro') || !$mod->object_sec && !$mod->secure('',':ro')) return $r;
    }

    $target_ds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$target);
    if(!empty($options['target_options'])) $targetopts=$options['target_options'];
    else $targetopts=array();
    if(!empty($options['_charset'])) $targetopts['_charset']=$options['_charset'];
    else $targetopts['_charset'] = \Seolan\Core\Lang::getCharset();

    // Liste des champs à recuperer
    if(@$options['display_format']){
      $olddisplayformat=$this->display_format;
      $this->display_format=$options['display_format'];
    }
    if(strpos($this->display_format,'%_')!==false){
      preg_match_all('/%_([a-z0-9_]+)/i',$this->display_format,$fmtfields);
      $fmtfields=$fmtfields[1];
    }else{
      $fmtfields=array();
    }
    if(isset($options['target_fields'])) $targetopts['selectedfields']=$options['target_fields'];
    else $targetopts['selectedfields']=$target_ds->getPublished(false);
    $targetopts['selectedfields']=array_merge($fmtfields,$targetopts['selectedfields']);

    // Recupere les champs de l'objet cible
    $values=$target_ds->rDisplay($value, array(), false, $LANG_DATA, $lang, $targetopts);
    $cnt=count($values['fields_object']);
    if (\Seolan\Core\Shell::admini_mode())
      $cnt = max($cnt, $this->getNbPublishedFields());

    // Si l'oid n'existe plus, on met à jour la table pour supprimer l'oid partout ou il est present et on retourne un objet sans valeur
    if(!is_array($values) && $values!='UNPUBLISHED' && \Seolan\Core\DbIni::get('disable_xlinkclean','val')!=1) {
      // Si la table est traduisible et qu'on est pas dans la langue par defaut, on verifie si l'oid existe dans au moins une langue
      if($target_ds->isTranslatable() && $LANG_DATA!=TZR_DEFAULT_LANG){
	$nb=getDB()->count("SELECT count(*) FROM {$this->target} WHERE KOID=?", [$value]);
	if($nb) return $r;
      }
      // Multivalué, traite tous les cas possibles (%||oid||%, oid||% et %||oid)
      $tolog=($target_ds->toLog() && $this->table!='LOGS');
      if($this->multivalued){
	$l=strlen($value);
	// %||oid||%
	if($tolog) {
	  $rs=getDB()->select("select KOID from {$this->table} where {$this->field} like ?", ["%||$value||%"]);
	  while($rs && ($ors=$rs->fetch())) \Seolan\Core\Logs::update('update',$ors['KOID'],"remove oid $value from {$this->field}");
	}
	if(!$tolog || $rs->rowCount()>0) {
	  getDB()->execute("update {$this->table} set UPD=UPD,{$this->field}=REPLACE({$this->field},?,?) ".
			   "where {$this->field} like ?",["||$value||","||", "%||$value||%"]);
	}
	// oid||%
	if($tolog) {
	  $rs=getDB()->select("select KOID from {$this->table} where {$this->field} like ?", ["$value||%"]);
	  while($rs && ($ors=$rs->fetch())) \Seolan\Core\Logs::update('update',$ors['KOID'],"remove oid $value from {$this->field}");
	}
	if(!$tolog || $rs->rowCount()>0) {
	  getDB()->execute("update {$this->table} set UPD=UPD,{$this->field}=SUBSTR({$this->field},".($l+3).") ".
			   "where {$this->field} like ?", ["$value||%"]);
	}
	// %||oid
	if($tolog) {
	  $rs=getDB()->select("select KOID from {$this->table} where {$this->field} like ?", ["%||$value||%"]);
	  while($rs && ($ors=$rs->fetch())) \Seolan\Core\Logs::update('update',$ors['KOID'],"remove oid $value from {$this->field}");
	}
	if(!$tolog || $rs->rowCount()>0) {
	  getDB()->execute("update {$this->table} set UPD=UPD,{$this->field}=SUBSTR({$this->field},1,LENGTH({$this->field})-".($l+2).") ".
			   "where {$this->field} like ?", ["%||$value"]);
	}
      }
      if($tolog) {
	$rs=getDB()->select("select KOID from {$this->table} where {$this->field}=?",[$value]);
	while($rs && ($ors=$rs->fetch())) \Seolan\Core\Logs::update('update',$ors['KOID'],"remove oid $value from {$this->field}");
      }
      if(!$tolog || $rs->rowCount()>0) getDB()->execute("update {$this->table} set UPD=UPD, {$this->field}=? where {$this->field}=?",["",$value]);

      $r->raw='';
      return $r;
    }elseif($values=='UNPUBLISHED'){
      $r->raw='';
      return $r;
    }

    // Prepare le html
    $display='';
    if(!empty($this->display_format)){
      if(strpos($this->display_format,'%_')!==false){
	$display=$this->display_format;
	foreach($fmtfields as $f) $display=str_replace('%_'.$f,$values['o'.$f]->html,$display);
      }else{
	$htmls=array();
	foreach($values['fields_object'] as $f) $htmls[]=$f->html;
	$display=@vsprintf($this->display_format,array_pad($htmls,substr_count($this->display_format,'%'),''));
      }
    }
    if(empty($this->display_format) || $display===false){
      for($i=0;$i<$this->getNbPublishedFields();$i++) {
	if(!isset($values['fields_object'][$i])) break;
	if($i==0) $display.=$values['fields_object'][$i]->html;
	else $display.=' '.$values['fields_object'][$i]->html;
      }
    }

    // Ajoute les liens vers l'objet dans le html si necessaire
    $r->html=$display;
    if(\Seolan\Core\Shell::admini_mode() && $this->generate_link){
      if($this->modsUsingTable===NULL){
	if($this->sourcemodule) $this->modsUsingTable=array($mod->_moid=>$mod->getLabel());
	else $this->modsUsingTable=\Seolan\Core\Module\Module::modulesUsingTable($target,false,true,true,true);
      }
      if(!empty($this->modsUsingTable)) {
	$url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(false,true);
	if($_format!='application/prince' && $_format!='application/excel') {
	  if(count($this->modsUsingTable)>1) {
	    $mi=0;
	    foreach($this->modsUsingTable as $mod=>&$label) {
	      $mi++;
	      if($mi<=XLINK2DEF_MAXLINKS) {
		if($mi==1) $r->html.=' [';
		else $r->html.='|';
		$r->html.='<a class="cv8-ajaxlink" href="'.$url.'&moid='.$mod.'&function=goto1&oid='.$value.'" title="'.$label.'">'.$mi.'</a>';
	      }
	    }
	    if($mi>0) $r->html.=']';
	  } else {
	    $v1['value']=array_values($this->modsUsingTable)[0];
	    $v1['key']=array_keys($this->modsUsingTable)[0];
	    $r->html='<a class="cv8-ajaxlink" href="'.$url.'&moid='.$v1['key'].'&function=goto1&oid='.$value.'" title="'.$v1['value'].'">'
	      .preg_replace('@<a([^>]*)>([^<]*)</a>@i','$2',$r->html).'</a>';
	  }
	}
      }
    }
    $r->title=$display;
    $r->link=$values;
    $r->raw=$value;
    if($olddisplayformat) $this->display_format=$olddisplayformat;
    return $r;
  }

  /// mise en forme des valeurs (selon options)
  /// a partir des raw values, formatte le texte pour l'oid associé
  function formatItems(&$value,&$rs, $myliste, $sourcemodule=NULL) {
    // est ce que l'on doit ajouter les groupes de champs
    // la liste est groupée sur le premier champ du lien
    if ($this->grouplist) {
      if (count($myliste) <= 1)
        $this->grouplist = false;
      else {
        $fields = array_keys($myliste);
        $groupField = $fields[0];
	list($groupListe[$groupField]) = $myliste;
      }
    }
    $items = array();
    // ???
    $opts=array('_published'=>'all');
    $i=0;

    while($rs && $ors=$rs->fetch()) {
      $i++;
      $koid=$ors['KOID'];
      
      if(isset($sourcemodule) && $sourcemodule->object_sec && !$sourcemodule->secure($koid,':ro')) continue;
      
      if ($this->grouplist && $currentGroup != $ors[$groupField]) {
        $currentGroup = $ors[$groupField];
        $groupLabel = $this->format_display($groupListe,$ors,$opts,null,'text');
	$items[] = array('koid'=>NULL, 'label'=>$grouplabel, 'type'=>'group');
	$i++;
      }
      $display=$this->format_display($myliste,$ors,$opts,null,'text');
      $item = array('koid'=>$koid, 'label'=>$display, 'type'=>'item', 'selected'=>false); 
      if((is_array($value) && isset($value[$koid])) || ($koid==$value)) 
	$item['selected']=true;
      $items[] = $item;
    }
    return $items;
  }
  /// construit la requete de chargement des valeurs 
  /// paramètres optionnels : target_fields, filter, _published
  function getRawValues(&$value, &$options){

    $p=new \Seolan\Core\Param($options,array());
    $lang_data=\Seolan\Core\Shell::getLangData();
    $queryfilter = NULL;
    $queryorder = NULL;
    // cas ou il y a une requete qui donne l'ensemble des valeurs
    if(!empty($this->query)) {
      preg_match("/^SELECT (.+) FROM .+( WHERE (.*))?( ORDER BY .*)?\$/Ui", $this->query, $matches);
      if(!isset($options['target_fields']) && $matches[1]!='*') $options['target_fields'] = explode(',', $matches[1]);
      $queryfilter=$matches[3];
      $queryorder=$matches[4];
    }
    // liste des champs à lire
    list($myliste,$my_flist,$first)=$this->getFieldList($options);

    // Construction de la requete
    $dstarget = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->target);
    if (!$dstarget->isTranslatable())
      $lang_data=TZR_DEFAULT_LANG;
    if(!empty($options['filter'])) $filter='('.$options['filter'].')';
    else $filter=$this->getFilter();
    if(!empty($queryfilter)) {
      if(!empty($filter)) $filter.=' AND ';
      $filter.=$queryfilter;
    }
    if($dstarget->publishedMode($p) == 'public'){ 
      if(!empty($filter)) $filter .= ' AND ';
      $filter .= ' PUBLISH = 1 ';
    }

    if(!empty($filter)) $filter.=' AND ';

    $maxrecords=max($this->checkbox_limit, $this->autocomplete_limit)+1;
    $rs2=getDB()->select("SELECT DISTINCT {$my_flist} FROM {$this->target} WHERE $filter LANG=? ".
			 ($queryorder?$queryorder:($first?" ORDER BY $first":""))." LIMIT $maxrecords", [$lang_data]);
    return $rs2;
  }
  /**
   * Un champ de type Lien n'est en edit que sur la langue par defaut (?)
   * Liens dépendants : restriction des mises en forme 
   * -> select, 
   * -> ? double select
   */
  
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    /* controles affichage des valeurs possibles */
    if($this->target==TZR_DEFAULT_TARGET){
      return $this->my_display($value,$options);
    }
    $sourcemodule = NULL;
    if($this->sourcemodule){
      $sourcemodule=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      if($sourcemodule->object_sec && !$sourcemodule->secure('',':list') || !$sourcemodule->object_sec && !$sourcemodule->secure('',':ro')) return;
    }
    // valeur en retour
    $r=$this->_newXFieldVal($options);
    $r->raw=$value;
    if(!\Seolan\Core\DataSource\DataSource::sourceExists($this->target)) {
      $r->html='';
      return $r;
    }
    $r->varid=\Seolan\Core\Shell::uniqId().$this->table.$this->field;
    $format=@$options['fmt'];
    // Noms des champs de formulaire
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
    // Liste des champs de la cible
    if(isset($options['display_format'])){
      $olddisplayformat=$this->display_format;
      $this->display_format=$options['display_format'];
    }

    list($myliste,$my_flist,$first)=$this->getFieldList($options);

    $rs = $this->getRawValues($value, $options);

    $doublebox=$this->isDoubleBoxed($options);
    
    if($doublebox) $this->getDoubleSelect($value,$options,$r,$rs,$fname,$hiddenname,$myliste);
    else $this->getSelect($value,$options,$r,$rs,$fname,$hiddenname,$myliste);

    if(!empty($olddisplayformat)) $this->display_format=$olddisplayformat;

    return $r;

  } 
  protected function isDoubleBoxed($options=[]){
    return ($this->multivalued && $this->doublebox && empty(@$options['simple']));
  }
  /**
   * version simplifiée et adaptée au cas 'avec dependances' du getSelect Object
   * mise à niveau à voir
   */ 
  
  function getSelect(&$value,&$options,&$r,&$rs,$fname,$hiddenname,$myliste, $sourcemodule=null) {

    $qf=@$options['query_format'];
    $items = $this->formatItems($value, $rs, $myliste, $sourcemodule);
    $r->html = '';
    $i = 0;
    // on transforme en options et on ajoute le select ... plus le reste
    foreach($items as $item){
      $i++;
      if ($item['type'] == 'group')
	$r->html .= '<optgroup label="'.$item['label'].'">';
      else{
	$r->html.='<option value="'.$item['koid'].'"'.($item['selected']?' selected' :'').'>'.$item['label'].'</option>';
	$r->collection = $item['label'];
	$r->oidcollection = $item['koid'];
	if ($item['selected']){
	  $r->text = $item['label'];
	}
      }
    }

    if($qf){
      // cas des recherches ?
      $labelin=@$options['labelin'];
      $format=@$options['fmt'];
      if(empty($format)) $format=@$options['qfmt'];
      if(empty($format)) $format=$this->query_format;
      if(empty($labelin)) $first='<option value="">----</option>';
      elseif(empty($first) || $format=='listbox') $first='<option value="">'.$this->label.'</option>';
      else{
	$first.='<option value="">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','n/a').'</option>';
	$r->html=str_replace('" selected>','">',$r->html);
      }
      if (($qf === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options))){
        $first = '';
      }
      $i++;
      if($i<2 || $format=='listbox-one') $r->html='<select '.($qf === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' name="'.$fname.'" id="'.$fname.'">'.$first.$r->html.'</select>';
      else $r->html='<select '.($qf === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' name="'.$fname.'[]" id="'.$fname.'" size="'.min($i,$this->boxsize).'" multiple>'.$first.$r->html.'</select>';
    }else{
     // cas std
      if(!$this->compulsory || !$this->usedefault) {
	$r->html='<option value="">----</option>'.$r->html;
	$i++;
      }
      if($this->multivalued) $cplt='name="'.$fname.'[]" size="'.min($i,$this->boxsize).'" multiple';
      else $cplt='name="'.$fname.'"';
      $cplt .= ' data-value="'.(is_array($value)?implode('||', array_keys($value)):$value).'" '; 
      
      $class = '';
      if ($this->compulsory)
        $class = 'tzr-input-compulsory';
      if (@$this->error)
        $class .= ' error_field';
      if ($class)
        $class = " class=\"$class\"";

      $r->html='<select '.$cplt.' '.$class.' id="'.$r->varid.'" onblur="TZR.isIdValid(\''.$r->varid.'\');">'.$r->html.'</select>';
      if($this->compulsory) {
	$r->html.='<script type="text/javascript">TZR.addValidator(["'.$r->varid.'","compselect","'.addslashes($this->label).'","'.\Seolan\Core\Ini::get('error_color').'",'.'"\Seolan\Field\Link\Link"]);</script>';
      }
      // paramétrage de la partie lien
      \Seolan\Core\Logs::notice(__METHOD__, " recherche des champs liens pour {$this->field}");
      $this->completeLinkedField($fname, $r, $options);
    }
  }
  function getDoubleSelect(&$value,&$options,&$r,&$rs,$fname,$hiddenname,$myliste){
   $this->_getDoubleSelect($value,$options,$r,$rs,$fname,$hiddenname,$myliste);
    $this->completeLinkedField($fname, $r, $options);
			       
  }
  /// script d'activation d'un champ lien
  protected function completeLinkedField($fname,
					 $r, /* result en cours de construction*/
					 $options
					 ){
    $url=TZR_AJAX8.'?class=_Seolan_Field_DependentLink_DependentLink&function=xlink2def_getValues&_silent=1';
    // recherche des paramètres des champs liens (dans cette version : un seul)
    $linkedfields = $this->getLinkedfields();
    if (!empty($linkedfields)){
      $uniqid = \Seolan\Core\Shell::uniqId();
      $revs = [];
      foreach($linkedfields as &$linkedfield){
	$linkedfield->fmoid = $options['fmoid'];
	$linkedfield->url = $url;
	$linkedfield->uniqid = $uniqid; 
	$linkedfield->varid = $uniqid.$this->table.$linkedfield->field;
	$linkedfield->table = $this->table;
	\Seolan\Core\Logs::notice(__METHOD__, " {$this->field} <= {$linkedfield->ofield->field}");
	$revs[] = (object)['ofield'=>$linkedfield->ofield,
			   'uitype'=>$linkedfield->uitype,
			   'linkedfields'=>$linkedfield->ofield->getLinkedFields($this->field)];
	unset($linkedfield->ofield);
      }
      $linkedfields = json_encode($linkedfields);
      $linkdesc = '{field:"'.$fname.'", uitype:"'.($this->isDoubleBoxed()?'double':'select').'", fmoid:'.$options['fmoid'].', url:"'.$url.'", uniqid:"'.$uniqid.'", varid:"'.$r->varid.'", table:"'.$this->table.'"}, '.$linkedfields.'';	
      $r->html.="\n".'<script type="text/javascript">TZR.linkedfields.add('.$linkdesc.');</script>';
      // pour chaque champs lié ajout recherche et ajout de la "reciproque"
      $linkedfields2 = array();
      foreach($revs as $rev){
	$ofield = $rev->ofield;
	$ofield->varid = $uniqid.$this->table.$ofield->field;
	$linkedfield2 = $rev->linkedfields[0];
	$linkedfield2->fmoid = $options['fmoid'];
	$linkedfield2->url = $url;
	$linkedfield2->uniqid = $uniqid; // verifier ne sert pas ...
	$linkedfield2->varid = $uniqid.$this->table.$linkedfield2->field;
	$linkedfield2->table = $this->table;
	unset($linkedfield2->ofield);
	$linkedfields2[] = $linkedfield2;
	$linkedfields2 = json_encode($linkedfields2);
	$linkdesc2 = '{field:"'.$ofield->field.'", uitype:"'.$rev->uitype.'", fmoid:'.$options['fmoid'].', url:"'.$url.'", uniqid:"'.$uniqid.'", varid:"'.$ofield->varid.'", table:"'.$this->table.'"}, '.$linkedfields2.'';	
	$r->html.="\n".'<script type="text/javascript">TZR.linkedfields.add('.$linkdesc2.');</script>';
      }
      
    }
  }
  /**
   * récuperation des paramètres sur les champs liés
   */
  function getLinkedfields($linkedfield=NULL){
    $linkedfields = [];
    if ($linkedfield == NULL){
      $linkedfield = $this->linkedfields1;
    }
    $myds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    if (empty($linkedfield) || !$myds->fieldExists($linkedfield)){
      return [];
    }
    $fd = $myds->desc[$linkedfield];
    // champ de this->target qui pointe sur fd(=champ liee)->target
    // ou champ de fd->target qui pointe sur this->target
    // -> avoir les paramètres pour faire les requetes
    $linkdesc = self::getLinkedFieldDesc($fd, $this->target);
    if ($linkdesc != NULL){
      $linkedfields[] = (object)['ofield'=>$fd,
				 'uitype'=>$fd->isDoubleBoxed()?'double':'select',
				 'field'=>$fd->field,
				 'query'=>(object)['queryfield'=>$linkdesc->queryfield]];
    }else{
      $linkdesc = self::getLinkedFieldDesc($this, $fd->target);
      if ($linkdesc == NULL)
	return [];
      else
	$linkedfields[] = (object)['ofield'=>$fd, 
				   'field'=>$fd->field, 
				   'uitype'=>$fd->isDoubleBoxed()?'double':'select',
				   'query'=>(object)['table'=>$linkdesc->target, 
						     'queryfield'=>$linkdesc->queryfield]];
    }
    return $linkedfields;
  }
  /**
   * props de configuration d'un champ lien sur une table donnée
   * -> champs de target qui pointent sur target de fd
   */
  protected static function getLinkedFieldDesc($fd, $target){
    $linkdesc = NULL;
    if (!empty($fd->target)){
      $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$fd->target);
      $links = $ds->getFieldsList(\Seolan\Core\Field\Field::getLinkTypes());
      foreach($links as $fn){
	if ($ds->desc[$fn]->target == $target){
	 return(object)['field'=>$fd->field, 
			'target'=>$fd->target,
			'queryfield'=>$fn,
			'fieldobject'=>$fd];
	}
      }
    }
    return NULL;
  }
  /// Recherche
  function my_query($value,$options=NULL) {
    $format=@$options['fmt'];
    if(empty($format)) $format=@$options['qfmt'];
    if(empty($format)) $format=$this->query_format;
    $searchmode=@$options['searchmode'];
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $hiddenname=$fname.'_HID';
    $r=$this->_newXFieldVal($options,true);
    $r->raw=$value;
    if($this->target==TZR_DEFAULT_TARGET) {
      $r->html.='<input name="'.$fname.'" size="30" type="text" value="'.$value.'">';
      return $r;
    }

    // Liste des champs de la cible
    if($options['display_format']){
      $olddisplayformat=$this->display_format;
      $this->display_format=$options['display_format'];
    }
    list($myliste,$my_flist,$first)=$this->getFieldList($options);

    if($format=='autocomplete'){
      $this->getAutocomplete($value,$options,$r,$rs,$fname,$hiddenname,$myliste);
      return $r;
    }

    // Construction de la requete
    $filter=array();
    if(!empty($options['filter'])) $filter[]='('.$options['filter'].')';
    elseif(($tmp=$this->getFilter())) $filter[]=$tmp;
    if(!empty($this->query)) {
      $inclause=array();
      $rs=getDB()->select($this->query);
      while($rs && ($ors=$rs->fetch())) $inclause[]="'".$ors['KOID']."'";
      $filter[]='(KOID IN ('.implode(',',$inclause).'))';
    }
    if($searchmode=='simple') {
      $allvalues=array();
      $selectquery=@$options['select'];
      if(!empty($selectquery)) $allvalues=$this->_getUsedValues(NULL,$selectquery);
      else $allvalues=$this->_getUsedValues("LANG='".TZR_DEFAULT_LANG."'");
      $filter[]='(KOID IN ("'.implode('","',array_keys($allvalues)).'"))';
    }
    if(!empty($filter)) $filter=implode(' AND ',$filter).' AND ';
    else $filter='';
    $rs2=getDB()->select("select distinct $my_flist from ".$this->target." where $filter LANG='".TZR_DEFAULT_LANG."'".
			  ' '.($first ? "order by $first":""));

    // Format de la saisie
    if($rs2) $nb=$rs2->rowCount();
    if ($nb >= $this->autocomplete_limit && $this->autocomplete && !$this->multivalue) {
      $this->getAutocomplete($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
      return $r;
    }
    $checkbox=(($nb<=$this->checkbox_limit || $format=='checkbox') && $format!='listbox' && $format!='listbox-one' && $this->checkbox);
    if($this->get_multivalued() && $format!='listbox-one'){
      $varid=$r->varid;
      $op=$options['op'];
      $medit='<select name="'.$fname.'_op">
            <option value="AND"'.($op==='AND'?' selected':'').'>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','xlinkdef_allterms').'</option>
            <option value="OR"'.($op==='OR'?' selected':'').'>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','xlinkdef_leastaterm').'</option>
            <option value="NONE"'.($op==='NONE'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_noterm').'</option>
            <option value="EXCLUSIVE"'.($op==='EXCLUSIVE'?' selected':'').'>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','xlinkdef_onlyterms').'</option>
        </select><br>';
    }else{
      $medit='';
    }
    if($checkbox) $this->getCheckboxes($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
    else $this->getSelect($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
    $r->html=$medit.$r->html;
    if($olddisplayformat) $this->display_format=$olddisplayformat;
    return $r;
  }

  function my_quickquery($value,$options=NULL) {
    $oldc=$this->checkbox;
    $oldd=$this->doublebox;
    $olda=$this->autocomplete;
    $this->checkbox=false;
    $this->doublebox=false;
    $this->autocomplete=true;
    $ret=$this->query($value, $options);
    $ret->html.='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    $this->checkbox=$oldc;
    $this->doublebox=$oldd;
    $this->autocomplete=$olda;
    return $ret;
  }

  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options);
    $oid=$options['oid'];
    $ischeckbox=(@$options[$this->field.'_HID']=='checkbox')||(@$options[$this->field.'_FMT']=='checkbox');
    if($ischeckbox) {
      $nvalue=array();
      // Attention éviter les liens vers lui-même
      foreach($value as $soid => $set) {
	if($soid!='Foo' && $soid!=$oid) $nvalue[]=$soid;
      }
      $value=$nvalue;
    }else{
      if(is_array($value)){
	foreach($value as $i=>$foo){
	  if(empty($foo)) unset($value[$i]);
	}
      }
    }
    if(!empty($this->exif_source) && empty($value)){
      $value=array();
      $meta=$this->getMetaValue($fields_complement,array('IPTC','EXIF','XMP'),true);
      if(!is_array($meta->raw)) $meta->raw=array($meta->raw);
      $opt=array('srcField'=>$this->flabel);
      if($this->isAutorizedToAdd($options)) $opt['create']=true;
      foreach($meta->raw as $i=>$v){
	if(!is_string($v)) continue;
	$ret=$this->my_import($v,$opt);
	$value[]=$ret['value'][0];
      }
    }
    if($value==$oid) $value=NULL;
    $r->raw=$value;
    // Edition par lot sur champ multivalué
    if(!empty($options['editbatch']) && $this->multivalued){
      $p=new \Seolan\Core\Param($ar,NULL);
      $op=$p->get($this->field.'_op');
      $old=explode('||',$options['old']->raw);
      if($op=='+') $r->raw=array_unique(array_merge($r->raw,$old));
      elseif($op=='-') $r->raw=array_diff($old,$r->raw);
    }
    // Trace
    $old=@$options['old'];
    if(!empty($old)){
      if(is_array($r->raw)) $v=implode('||',$r->raw);
      else $v=$r->raw;
      $r1=$this->display($v,$options);
      if($r1->html!=$old->html) $this->trace($options['old'],$r, '['.$old->html.'] -> ['.$r1->html.']');
    }
    return $r;
  }

  /// Autorise l'ajout d'une entrée à la volée
  public function isAutorizedToAdd(&$options){
    if($this->sourcemodule){
      if(empty($this->cache->modules[$this->sourcemodule])){
	$this->cache->modules[$this->sourcemodule]=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      }
      return $this->cache->modules[$this->sourcemodule]->secure('',':rw');
    }elseif(!empty($options['fmoid'])){
      if(empty($this->cache->modules[$options['fmoid']])){
	$this->cache->modules[$options['fmoid']]=\Seolan\Core\Module\Module::objectFactory($options['fmoid']);
      }
      return $this->cache->modules[$options['fmoid']]->secure($options['oid'],':rwv');
    }
    return false;
  }

  function post_query($o,$options=NULL) {
    $p=new \Seolan\Core\Param($options,array());
    $ischeckbox=($o->hid=='checkbox') || ($o->fmt=='checkbox');
    if($ischeckbox && is_array($o->value)) {
      $nvalue=array();
      foreach($o->value as $soid => $set) {
	$nvalue[]=$soid;
      }
      $o->value=$nvalue;
    }
    return parent::post_query($o,$options);
  }

  function search($value,$options=NULL){
    if($this->target!='%'){
      if(!is_array($value)) $value=array($value);
      $cond=array();
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->target);
      if(!empty($options['target_fields'])) $pub=$options['target_fields'];
      else $pub=$x->getPublished(false);
      foreach($value as $v) $cond[]='concat('.implode('," ",',$pub).') like "%'.addslashes($v).'%"';
      if(!empty($cond)) return $this->field.' in (select KOID from '.$this->target.' where '.implode(' or ', $cond).')';
      else return '';
    }
  }

  function getNbPublishedFields(){
    if(!\Seolan\Core\Shell::admini_mode()) return 99;
    if($this->target=='%' || empty($this->target)) return 2;
    if(strpos($this->display_format,'%')!==false) return 99;
    if(empty($this->published_fields_in_admin)){
      $tablexset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->target);
      $this->published_fields_in_admin=$tablexset->published_fields_in_admin;
    }
    return $this->published_fields_in_admin;
  }

  /// Formate l'affichage d'un lien
  function format_display(&$myliste,&$ors,&$opt,$nbpublishedfields=NULL,$prop='html'){
    reset($myliste);
    $display='';
    $htmls=array();
    $replace=false;
    if(!empty($this->display_format) && strpos($this->display_format,'%_')!==false){
      $replace=true;
      $display=$this->display_format;
    }
    foreach($myliste as $k=>$f){
      $o=$f->display($ors[$k],$opt);
      $t=$o->$prop;
      if($replace) $display=str_replace('%_'.$k,$t,$display);
      else $htmls[]=$t;
    }
    if(!$replace){
      if(!empty($this->display_format)) $display=@vsprintf($this->display_format,array_pad($htmls,substr_count($this->display_format,'%'),''));
      else $display=implode(' ',$htmls);
    }
    return $display;
  }

  /// Ecriture dans un fichier excel
  function writeXLS($xl,$i,$j,$value,$format=0,$ss=NULL) {
    $t=str_replace('&nbsp;', ' ', $value->toText());
    $t=preg_replace('/\[.*\]/', '', $t);
    convert_charset($t,TZR_INTERNAL_CHARSET,'UTF-8');
    $xl->setCellValueByColumnAndRow($j,$i,$t);
    if(is_array($format)) $xl->getStyleByColumnAndRow($j,$i)->applyFromArray($format);
  }
  /// Fonction de vérification de l'import
  protected function my_checkImport($value, $specs=null){
    if ($specs == null)
      return parent::my_checkImport($value, $specs);
    // on veut juste vérifier et avoir le message
    $specs->create = false;
    return $this->my_import($value, $specs);
  }
  /// Sous fonction pour l'import de données vers une table
  /// Options : srcField (string) => champ à utiliser dans la table cible pour creer le lien (si vide la valeur doit etre un oid)
  ///           create (true/false) => creation automatique des fiches cible non existante (false par defaut)
  ///           separator (string) => séparateur pour des valeurs multiples (['|', ','] par défaut dans TZR_IMPORT_SEPARATOR)
  ///           forcekoid (true/false) => force la génération de l'oid à partir de la valeur (false par defaut)
  function my_import($value,$specs=null){
    $separator=$specs->separator;
    if(empty($separator))
      $separator = TZR_IMPORT_SEPARATOR; 
    $create=$specs->create;
    $forcekoid=$specs->forcekoid;
    $srcField=$specs->srcField;
    $message='';
    if($value!=''){
      if(empty($srcField) && !\Seolan\Core\Kernel::isAMultipleKoid($value)){
	$ors=getDB()->fetchRow('select FIELD from DICT where DTAB="'.$this->target.'" and PUBLISHED=1 order by forder limit 1');
	@$srcField=$ors['FIELD'];
      }
      if($srcField){
	$ret=array();
	cp1252_replace($value);
        if(is_array($separator)) {
          $value = str_replace($separator, $separator[0], $value);
          $valueslist = explode($separator[0], $value);
        }
        else {
          $valueslist = explode($separator, $value);
        }

	foreach($valueslist as $v){
	  $v=trim($v);
	  if($v!=''){

	    $filter=$this->getFilter();
	    if(!empty($specs->filter))
	      $filter='('.$specs->filter.')';
	    if(!empty($filter)) $filter.=' AND ';

	    $rs=getDB()->select('SELECT KOID FROM '.$this->target.' WHERE '.$filter.' UPPER('.$srcField.')="'.strtoupper(addslashes($v)).'"');
	    if($rs && $ors=$rs->fetch()){
	      $ret[]=$ors['KOID'];
	    }else{ // la target n'existe pas
	      if($create){
		$toinsert=array();
		if(!empty($forcekoid) && $forcekoid!='false'){
		  $toinsert['newoid']=$this->target.':'.strtoupper(preg_replace('/([^a-z0-9]+)/','',rewriteToAscii($v)));
		}
		$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->target);
		$toinsert['tplentry']=TZR_RETURN_DATA;
		$toinsert['PUBLISH']=1;
		$toinsert[$srcField]=$v;
		$mess=$xset->procInput($toinsert);
		$message.=$this->field.' : "'.$v.'" created<br/>';
		$ret[]=$mess['oid'];
	      }else{
		$message.='<u>Warning</u> : '.$this->field.' : "'.$v.'" doesn\'t exist<br/>';
	      }
	    }
	  }
	}
      }else{
	$ret=$value;
      }
    }
    return array('message'=>$message,'value'=>$ret);
  }

  /// creation d'un index si c'est un champ mono value
  function chk(&$msg) {
    parent::chk($msg);
    if(!$this->get_multivalued()) {
      // ajout d'un index sur les champs non multivalues
      if (!getDB()->count('SHOW INDEX FROM '.$this->table.' where Column_name="'.$this->field.'"')) {
	$desc = getDB()->fetchRow("show columns from {$this->table} where field='{$this->field}'");
	if ($desc['Type'] == 'varchar(20)' || $desc['Type'] == 'varchar(40)'){
	  getDB()->execute('ALTER TABLE '.$this->table.' ADD INDEX '.$this->field.'('.$this->field.')');
	} elseif($desc['Type'] == 'text'){
	  getDB()->execute('ALTER TABLE '.$this->table.' ADD INDEX '.$this->field.'('.$this->field.'(40))');
	}
      }
      // verification que le champ n'inclue pas de ||
      $rs=getDB()->select('SELECT KOID,LANG,'.$this->field.' FROM '.$this->table.' WHERE '.$this->field.' like "%||%"');
      while($ors=$rs->fetch()) {
	$oid='';
	$value=$ors[$this->field];
	$values=explode('||',$value);
	foreach($values as $value){
	  if(!empty($value) && \Seolan\Core\Kernel::objectExists($value)){
	    $oid=$value;
	    break;
	  }
	}
	getDB()->execute('UPDATE '.$this->table.' SET UPD=UPD,'.$this->field.'= ? WHERE KOID=? AND LANG=?', 
			 array($oid,$ors['KOID'],$ors['LANG']));
      }
    }
    return true;
  }

  // Edition du champ sous la forme d'une double liste déroulante
  function _getDoubleSelect(&$value,&$options,&$r,&$rs,$fname,$hiddenname,$myliste){
    $mod=NULL;
    if($this->sourcemodule){
      $mod=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      if($mod->object_sec && !$mod->secure('',':list') || !$mod->object_sec && !$mod->secure('',':ro')) return;
    }
    $oidcollection=$collection=$opts=array();
    $unselectedname=preg_replace('/^([^\[]+)/','$1_unselected',$fname);
    $unselectedid = 'unselected'.$r->varid;
    $selectedid = $r->varid;
    $cplt = 'data-value="'.(is_array($value)?implode('||', array_keys($value)):$value).'" '; 
    $edit1='<select name="'.$unselectedname.'" size="'.$this->boxsize.'" multiple id="'.$unselectedid.'" ondblclick="TZR.doubleAdd('.
      'document.getElementById(\''.$unselectedid.'\'),document.getElementById(\''.$selectedid.'\'),true)" class="doublebox">';
    $edit2='<select name="'.$fname.'[]" size="'.$this->boxsize.'" multiple id="'.$selectedid.'" ondblclick="TZR.doubleAdd(document.getElementById(\''.$selectedid.'\'),'.'document.getElementById(\''.$unselectedid.'\'),true)" class="doublebox" '.$cplt.'>';
    $order = 0;
    if ($this->grouplist) { // la liste est groupée sur le premier champ du lien
      if (count($myliste) <= 1)
        $this->grouplist = false;
      else {
        $fields = array_keys($myliste);
        $groupField = $fields[0];
        list($groupListe[$groupField]) = $myliste;
        $groupid = 0;
      }
    }

    while($ors=$rs->fetch()) {
      $order++;
      $koid=$ors['KOID'];
      $datavalues[] = $koid;
      if($mod && $mod->object_sec && !$mod->secure($koid,':ro')) continue;
      $selected=isset($value[$koid]);
      if ($this->grouplist && $currentGroup != $ors[$groupField]) {
        $currentGroup = $ors[$groupField];
        $groupid++;
        $groupLabel = $this->format_display($groupListe,$ors,$opts,null,'text');
        $edit1 .= '<optgroup label="'.$groupLabel.'" id="unselected_'.$fname.'_'.$groupid.'">';
        $edit2 .= '<optgroup label="'.$groupLabel.'" id="'.$fname.'_'.$groupid.'">';
      }
      $display=$this->format_display($myliste,$ors,$opts,null,'text');
      if(!$selected) $edit1.='<option value="'.$koid.'" order="'.$order.'">'.$display.'</option>';
      else $edit2.='<option value="'.$koid.'" order="'.$order.'">'.$display.'</option>';
      if($selected) $r->text.=$display;
      $oidcollection[]=$koid;
      $collection[]=$display;
    }
    $edit1.='</select>';
    $edit2.='</select>';

    $buttons='<button type="button" onclick="TZR.doubleAdd(document.getElementById(\''.$unselectedid.'\'),'.
      'document.getElementById(\''.$selectedid.'\'), true)" class="btn btn-default">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','next').'</button><br>'.
      '<button type="button" onclick="TZR.doubleAdd(document.getElementById(\''.$selectedid.'\'),'.
      'document.getElementById(\''.$unselectedid.'\'),true)" class="btn btn-default">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','previous').'</button>';

    $hidd='<input type="hidden" name="'.$hiddenname.'" value="doublebox"/>';
    $color=\Seolan\Core\Ini::get('error_color');
    if($this->compulsory) $t1="TZR.addValidator(['{$r->varid}',/(.+)/,'".addslashes($this->label)."','$color','\Seolan\Field\Link\Link']);";
    else $t1="TZR.addValidator(['{$r->varid}','','".addslashes($this->label)."','$color','\Seolan\Field\Link\Link']);";
    $js="<script type=\"text/javascript\">if(typeof(TZR)!='undefined') { $t1 }</script>";
    $edit='<table class="doublebox"><tr><td>'.$edit1.'</td><td class="button">'.$buttons.$hidd.'</td><td>'.$edit2.'</td></tr></table>'.$js;
    $r->oidcollection=$oidcollection;
    $r->collection=$collection;
    $r->html=$edit;
  }
  /// retourne la liste des champs de la cible à utliser + le premier champ 
  function &getFieldList(&$options,$addpublished=true){
    $maxmi=$this->getNbPublishedFields();
    $order='FORDER';
    $sup='';
    $idx='';
    if(strpos($this->display_format,'%_')!==false){
      preg_match_all('/%_([a-z0-9_]+)/i',$this->display_format,$fmtfields);
      $fmtfields=$fmtfields[1];
      $fmtfields=array_reverse($fmtfields);
      $sql=implode('","',$fmtfields);
      $order='FIELD(FIELD,"'.$sql.'") desc';
      $sup=' or FIELD in("'.$sql.'")';
      $maxmi=99;
    }
    $idx='';
    if(!$addpublished && !empty($sup)){
      $rs=getDB()->select('select * from DICT where '.substr($sup,3).' and DTAB="'.$this->target.'" ORDER BY '.$order);
    }elseif(!isset($options['target_fields'])){
      $rs=getDB()->select('select * from DICT where (PUBLISHED=1'.$sup.') and DTAB="'.$this->target.'" ORDER BY '.$order);
    }else{
      $idx=implode('","',$options['target_fields']);
      if(isset($this->fieldlist[$idx]))	return $this->fieldlist[$idx];
      $fieldlist='"'.implode('","',$options['target_fields']).'"';
      $rs=getDB()->select('select * from DICT where (FIELD in ('.$fieldlist.')'.$sup.') and DTAB="'.$this->target.'" ORDER BY '.$order);
      if($rs->rowCount()==0) $rs=getDB()->select('select * from DICT where (PUBLISHED=1'.$sup.') and DTAB="'.$this->target.'" ORDER BY '.$order);
      $maxmi=99;
    }
    $first='';
    $my_flist=$this->target.'.KOID';
    $mi=0;
    while($ors=$rs->fetch()) {
      if(empty($first)) $first=$ors['FIELD'];
      $oo=(object)$ors;
      if($mi<$maxmi) {
	$myliste[$ors['FIELD']]=\Seolan\Core\Field\Field::objectFactory($oo);
	$my_flist.=','.$this->target.'.'.$ors['FIELD'];
	$mi++;
      }
    }
    $this->fieldlist[$idx]=array($myliste,$my_flist,$first);
    return $this->fieldlist[$idx];
  }

  /// Recupere le type du champ dans un webservice (name : type xml, descr : description du type pour l'ajour d'une type complexe)
  function getSoapType(){
    if($this->multivalued)
      return array('name'=>'tns:stringArray',
		   'descr'=>array('stringArray'=>array(array('name'=>'value','minOccurs'=>0,'maxOccurs'=>'unbounded','type'=>'xsd:string'))));
    else
      return array('name'=>'xsd:string');
  }
  /// Recupere la valeur formattée pour le service SOAP
  function getSoapValue($r){
    if($this->multivalued) return $r->oidcollection;
    else return $r->raw;
  }

  /// Recupere le texte d'une valeur
  public function &toText($r) {
    if(!property_exists($r, 'text') || $r->text===NULL){
      $r->text='';
      if(is_array($r->link)) {
	foreach($r->link as $i=>$o){
	  if(isset($r->text)) $r->text.=' ';
	  if(is_object($o)) $r->text.=$o->text;
	}
      } else {
	$r->text=getTextFromHTML($r->html);
      }
    }
    $m1=trim($r->text);
    return $m1;
  }
  // lien d'insertion
  private function addInsertLink($varid, $qf, $options) {
    if (\Seolan\Core\Shell::admini_mode() && !$qf && ($this->sourcemodule && $this->isAutorizedToAdd($options))){
      $urlnew = $GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,true).'&moid='.$this->sourcemodule.'&function=insert&template=Module/Table.popinsert.html&tplentry=br&tabsmode=2&varid='.$varid.'&field='.$this->field;
      $newico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','new');
      return '<a  href="#" data-toggledialog="dialog", data-options=\'{"initCallback":{"_function":"TZR.Record.newRecordInit", "_param":"'.$varid.'"}}\' data-url="'.$urlnew.'">'.$newico.'</a>';
    }
    return '';
  }

  // Edition du champ sous la forme d'une zone d'autocomplétion
  function getAutocomplete(&$value,&$options,&$r,$rs,$fname,$hiddenname,$myliste){
    if(is_array($value)){
      $v=array_keys($value);
      $v=@$v[0];
    }else $v=$value;
    $qf=@$options['query_format'];
    $lang=\Seolan\Core\Shell::getLangUser();
    $lang_data=\Seolan\Core\Shell::getLangData();
    if(isset($options['target_fields'])){
      $fieldslist=implode(',',$options['target_fields']);
      $pubonly=false;
    }else{
      $fieldslist='';
      $pubonly=true;
    }
    if($qf) $varid=$fname.'_id';
    else $varid=getUniqID('v');
    $textid='_INPUT'.$varid;
    $edit=$mborder=$js=$fmt='';
    if(!$qf && $this->compulsory) {
      $mborder='tzr-input-compulsory';
      if(!$this->multivalued) $fmt=' onblur="TZR.isIdValid(\''.$varid.'\')" ';
      $js='<script type="text/javascript">'.
	'TZR.addValidator(["'.$varid.'",/.+:.+/,"'.addslashes($this->label).'","'.\Seolan\Core\Ini::get('error_color').'","\Seolan\Field\Link\Link","'.$textid.'"]);</script>';
    }
    if($this->multivalued){
      if(is_array($value)) $v=array_keys($value);
      else $v=$value;
      $delico=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete');
      $edit.='<input id="'.$varid.'" autocomplete="off" name="foo" value="" type="hidden">';
      $edit.='<input autocomplete="off" id="'.$textid.'" name="_INPUT'.$fname.'" size="30" type="text" '.$fmt.' class="tzr-link tzr-autocomplete '.$mborder.'">';
      $edit.=$this->addInsertLink($varid, $qf, $options);
      if ($qf && \Seolan\Core\Shell::admini_mode()){
	$op=$options['op'];
          $querypart='<div class="radio"><label><input type="radio" name="'.$fname.'_op" value="AND" id="'.$varid.'-AND" checked>'.
            \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','xlinkdef_allterms').'</label></div><br>'.
            '<div class="radio"><label><input type="radio" name="'.$fname.'_op" value="OR" id="'.$varid.'-OR"'.($op=='OR'?' checked':'').'>'.
            \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','xlinkdef_leastaterm').'</label></div>';
	  if( $options['genempty'] !== false ){
	    $querypart .= '&nbsp;<div class="radio"><label><input type="radio" name="'.$fname.'_op" value="is empty" id="'.$varid.'-EMPTY"'.($op=='is empty'?' checked':'').'>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','is_empty').'</label></div>';
	  }
      }else{
	$querypart='';
      }
      $edit.='<table id="table'.$varid.'"><tr style="display:none;"><td><a href="#" onclick="TZR.delLine(this);return false;">'.$delico.'</a><input type="hidden" name="'.$fname.'[]" value=""></td><td></td></tr>';
      $r->text='';
      if(!empty($v)){
	if(!is_array($v)) $v=array($v);
	$oidcollection = $v;
	foreach($v as $oid){
	  $target=\Seolan\Core\Kernel::getTable($oid);
	  if(\Seolan\Core\System::tableExists($target)) {
	    $t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$target);
	    if($t->isTranslatable())
	      $rs2=getDB()->select('select * from '.$target.' where KOID=? AND LANG = ?', array($oid,$lang_data));
	    else
	      $rs2=getDB()->select('select * from '.$target.' where KOID= ?', array($oid));
	    $ors2=$rs2->fetch();
	    if($ors2) {
	      $myopts=array();
	      if(!empty($options['_charset'])) $myopts['_charset']=$options['_charset'];
	      $display=$this->format_display($myliste,$ors2,$myopts,NULL,'text');
	    }
	  }
	  $display=trim($display);
	  $r->text.=$display.' ';
	  $edit.='<tr><td><a href="#" onclick="TZR.delLine(this);return false;">'.$delico.'</a><input type="hidden" name="'.$fname.'[]" value="'.$oid.'" '.
	    '</td><td>'.$display.'</td></tr>';
	}
	$r->text=substr($r->text,0,-1);
      }
      $edit.='</table>';
      if ($querypart != '')
        $edit = $querypart.'<br>'.$edit;
    }else{
      $display='';
      if($v){
	$target=\Seolan\Core\Kernel::getTable($v);
	if(\Seolan\Core\System::tableExists($target)) {
	  $t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$target);
	  $rs2=getDB()->select('select * from '.$target.' where KOID=?', array($v));
	  $ors2=$rs2->fetch();
	  if($ors2) {
	    $myopts=array();
	    if(!empty($options['_charset'])) $myopts['_charset']=$options['_charset'];
	    $display=$this->format_display($myliste,$ors2,$myopts,NULL,'text');
	  }
	}
	$display=trim($display);
      }
      $r->text=$display;
      $edit.='<input id="'.$varid.'" autocomplete="off" name="'.$fname.'" value="'.$v.'" type="hidden">';
      $edit.='<input autocomplete="off" id="'.$textid.'" name="_INPUT'.$fname.'" size="30" type="text" value="'.$display.'" '.$fmt.
	' class="tzr-link '.$mborder.'">';
      $edit.=$this->addInsertLink($varid, $qf, $options);
      if( $qf && \Seolan\Core\Shell::admini_mode() && $options['genempty'] !== false ){
	$querypart .= '<div class="checkbox"><label><input type="checkbox" name="'.$fname.'_op" value="is empty" id="'.$textid.'-EMPTY"'.($op=='is empty'?' checked':'').'>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','is_empty').'</label></div>';
      } else {
	$querypart = '';
      }
      if ($querypart != '')
        $edit = $querypart.'<br>'.$edit;
    }
    if (!empty($options['function'])) {
      $function = $options['function'];
    } else {
      $function = "xlinkdef_autocomplete";
    }
    if(!empty($options['oid']))
      $url=TZR_AJAX8.'?class='.urlencode('\Seolan\Field\Link\Link').'&function=xlinkdef_autocomplete&_silent=1&oid='.$options['oid'].'&query_format='.$qf.($this->display_format?'&options[display_format]='.urlencode($this->display_format):'');
    else
      $url=TZR_AJAX8.'?class='.urlencode('\Seolan\Field\Link\Link').'&function=xlinkdef_autocomplete&_silent=1&query_format='.$qf.($this->display_format?'&options[display_format]='.urlencode($this->display_format):'');

    $edit.='<script type="text/javascript" language="javascript">jQuery("#'.$textid.'").data("autocomplete", {url:"'.$url.'", params:{moid:"'.$options['fmoid'].'", table:"'.$this->table.'", field:"'.$this->field.'",fieldslist:"'.$fieldslist.'", id:"'.$varid.'"}'.($this->multivalued?',callback:TZR.autoCompleteMultipleValue' : '').',minlength:'.$this->autocomplete_minlength.'});TZR.addAutoComplete("'.$varid.'");</script>'.$js;
    $r->oidcollection=$r->collection=array();
    $r->varid=$varid;
    $r->html=$edit;
  }
  // surcharge du getLink pour différencier des liens
  function getLink(){
    $text = parent::getLink();
    return $text.' ('.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'linkedfield').')';
  }
}
function xlink2def_getValues($ar=NULL) {
  activeSec();
  if (empty($_REQUEST['options']))
    return NULL;
  $sourceModuleRights = false;
  $options = $_REQUEST['options'];
  $table=$_REQUEST['table']; 
  $field=$_REQUEST['field'];
  // Verifie que l'on peut utiliser l'autocomplete depuis le module (droit list/ro sur le module, table utilisée par le module)
  $mod=\Seolan\Core\Module\Module::objectFactory($options['fmoid']);
  if($mod->object_sec) $ok=$mod->secure('',':list');
  else $ok=$mod->secure('',':ro');
  if(!$ok) {
    $sourceModuleRights = true; // Si droit none sur la function du module ex: myAccount dans la console
  }
  // Recupere les valeurs
  $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
  $ofield=$xset->getField($field);
  if (!empty($ofield->edit_format))
    $ofield->display_format = $ofield->edit_format;
  
  $sourcemodule = NULL;
  // Si le champ utilise un module particulier, on verifie les droits
  if($ofield->sourcemodule){
    $sourcemodule=\Seolan\Core\Module\Module::objectFactory($ofield->sourcemodule);
    if($sourcemodule->object_sec) $ok=$sourcemodule->secure('',':list');
    else $ok=$sourcemodule->secure('',':ro');
    if(!$ok) return null;
  }elseif($sourceModuleRights){
return null;
}
  // lecture des resultats, ajout du filtre options['filter'] dynamique
  if (!empty($_REQUEST['linkqueries'])){
    $filters = array();
    foreach($_REQUEST['linkqueries'] as $aquery){
      if (empty($aquery['value']))
	$values = array();
      else
	$values = $aquery['value'];
      if (!is_array($values))
	$values = array($values);
      if (empty($aquery['query']['table'])){
	$v = array('=', $values);
	// make_cond permet de gerer les cas multi et mono etc  sur le champ qui sert de filtre
	$dstarget = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ofield->target);
	$filters[] = $dstarget->make_cond($dstarget->desc[$aquery['query']['queryfield']], $v);
	//	$filters2[$aquery['query']['queryfield']] = $dstarget->make_cond($dstarget->desc[$aquery['query']['queryfield']], $v);
      } else {
	$v = implode('\',\'', $values);
	$oids = getDB()->select('select distinct '.$aquery['query']['queryfield'].' from '.$aquery['query']['table'].' where KOID in (\''.$v.'\')')->fetchAll(\PDO::FETCH_COLUMN);
	$oids = implode('||', $oids);
	$filters[] = '\''.$oids.'\' like concat(\'%\', KOID, \'%\')';
      }
    }
    $options['filter'] = '('.implode(') and (', $filters).')';
    //    die($options['filter']);
    // cas champ deja filtré ajouter le lien dans les options
  }
  $value = NULL;
  $rs = $ofield->getRawValues($value, $options);
  list($myliste,$my_flist,$first)=$ofield->getFieldList($options,false);
  $items = $ofield->formatItems($value, $rs, $myliste, $sourcemodule);
  $res = array(array('field'=>$field, 'items'=>$items, 'filter'=>($options['filter']??NULL)));
  //$res = array(array('field'=>$field, 'items'=>$items));
  die(json_encode($res));
}

function xlink2def_autocomplete($php = false) {
  activeSec();
  $moid = $_REQUEST['moid'];    // Module depuis lequel on fait l'autocomplete
  $table = $_REQUEST['table'];  // Table contenant le champ
  $field = $_REQUEST['field'];
  $q = $_REQUEST['q'];
  if (!empty($_REQUEST['target_fields']) && !is_array($_REQUEST['target_fields']))
    $target_fields = explode(',', $_REQUEST['target_fields']);
  else
    $target_fields = NULL;
  if (empty($moid) || empty($q)) {
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  // Verifie que l'on peut utiliser l'autocomplete depuis le module (droit list/ro sur le module, table utilisée par le module)
  $mod = \Seolan\Core\Module\Module::objectFactory($moid);
  if ($mod->object_sec)
    $ok = $mod->secure('', ':list');
  else
    $ok = $mod->secure('', ':ro');
  if (!$ok) {
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  if (empty($table))
    $table = $mod->table;
  if (!$mod->usesTable($table)) {
    header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  // Recupere les valeurs
  $xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
  $ofield = $xset->getField($field);
  if ($options['display_format'])
    $ofield->display_format = $options['display_format'];
  // Si le champ utilise un module particulier, on verifie les droits
  if ($ofield->sourcemodule) {
    $mod = \Seolan\Core\Module\Module::objectFactory($ofield->sourcemodule);
    if ($mod->object_sec)
      $ok = $mod->secure('', ':list');
    else
      $ok = $mod->secure('', ':ro');
    if (!$ok) {
      header("HTTP/1.1 500 Seolan Server Error");
      return null;
    }
  }
  $q = trim($q);
  $charset = \Seolan\Core\Lang::$locales[$lang_data]['charset'];
  if (empty($target_fields))
    $target_fields = NULL;
  $ret = $ofield->getValues($q, array('_charset' => 'utf-8', 'target_fields' => $target_fields, 'max' => XLINKDEF_MAXPOPUPLINKS, 'mod' => $mod));
  $suggestions = &$ret['values'];

  if ($php)
    return array('field' => $ofield, 'suggestions' => $suggestions);

  header('Content-Type:application/json; charset=UTF-8');
  if (count($suggestions) == 0)
    die(json_encode(array(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'no_result'))));
  foreach ($suggestions as $koid => $value) {
    $data[] = array('value' => $koid, 'label' => $value);
  }
  if ($ret['state'] == 'toomuch')
    $data[] = array('value' => '', 'label' => \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field', 'too_many_results'));
  die(json_encode($data));
}

?>
