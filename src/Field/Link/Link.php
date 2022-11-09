<?php
namespace Seolan\Field\Link;

define('XLINKDEF_MAXLINKS',3);
define('XLINKDEF_MAXPOPUPLINKS',100);

/// Gestion des champs Lien entre les objets des tables
class Link extends \Seolan\Core\Field\Field {
  public $filter='';
  public $query='';
  public $edit_query='';
  public $boxsize=6;
  public $usedefault=true;
  public $checkbox=true;
  public $doublebox=false;
  public $doubleboxorder=false;
  public $checkbox_limit=30;
  public $autocomplete=true;
  public $autocomplete_limit=200;
  public $autocomplete_minlength=3;
  public $generate_link = true;
  public $checkbox_cols=3;
  protected $modsUsingTable=NULL;
  public $query_formats=array('classic','listbox-one','listbox','autocomplete','filled');
  public $advanceeditbatch=true;
  public $display_format='';
  public $display_text_format='';
  public $nodeletevalue=false;
  public $normalized=false;
  public $treeview=false;
  public $treeviewgroup1=null;
  public $treeviewgroup2=null;
  public $treeviewgroup3=null;
  protected $fieldlist=NULL;
  public static $upgrades = ['20220629'=>''];
  /// Types de champs à ignorer lors de l'affichage de l'objet au format texte
  public $display_text_unwanted_ftypes = [
    '\Seolan\Field\Folder\Folder',
    '\Seolan\Field\File\File',
    '\Seolan\Field\Image\Image',
    '\Seolan\Field\Video\Video',
    '\Seolan\Field\Thesaurus\Thesaurus',
  ];
  function __construct($obj=NULL) {
    parent::__construct($obj);
  }
  function getRelationTableName(){
    return Normalizer::getRelationTableName($this);
  }
  function initOptions() {
    parent::initOptions();
    $this->_options->setDefaultGroup(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','specific').' : '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','\Seolan\Field\Link\Link'));
    $this->_options->delOpt('aliasmodule');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','sourcemodule'),'sourcemodule','module',array('emptyok'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','browsesourcemodule'), 'browsesourcemodule', 'boolean',
			    array('emptyok'=>true, 'default'=>'0'));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','usedefault'), "usedefault", "boolean", null, true);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','filter'), 'filter', 'text',array('rows'=>2,'cols'=>60));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query'), 'query', 'text',array('rows'=>2,'cols'=>60));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_query'), 'edit_query', 'text',array('rows'=>2,'cols'=>60));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','nodeletevalue'), 'nodeletevalue', 'boolean',null,false);
    $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','nodeletevalue_comment'), 'nodeletevalue');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','normalized'), 'normalized', 'boolean', null, false);
    $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','normalized_comment'), 'normalized');
    $this->_options->setRO('normalized');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','display_format'), 'display_format', 'text', ['rows'=>3,'cols'=>50]);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','display_text_format'), 'display_text_format', 'text', ['size'=>50]);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','edit_format'),'edit_format','text',['size'=>50,'default'=>'']);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','checkbox'), 'checkbox', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','checkbox_limit'), 'checkbox_limit', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','checkbox_cols'), 'checkbox_cols', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','autocomplete'), 'autocomplete', 'boolean',NULL,true);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','autocomplete_limit'), 'autocomplete_limit', 'text',NULL,200);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','autocomplete_minlength'), 'autocomplete_minlength', 'text',NULL,3);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','doublebox'), 'doublebox', 'boolean',NULL,false);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','doubleboxorder'), 'doubleboxorder', 'boolean',NULL,false);
    $treeviewlabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','treeviewMode');
    $this->_options->setOpt($treeviewlabel,'treeview','boolean',NULL,false, $treeviewlabel);
    if (isset($this->target) && !empty($this->target) && $this->target != TZR_DEFAULT_TARGET){
      $this->_options->setOpt(sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','treeviewgroupfield'), 1),
			      'treeviewgroup1', 'field',['compulsory'=>0,'table'=>$this->target],false,$treeviewlabel);
      $this->_options->setOpt(sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','treeviewgroupfield'), 2),
			      'treeviewgroup2', 'field',['compulsory'=>0,'table'=>$this->target],false,$treeviewlabel);
      $this->_options->setOpt(sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','treeviewgroupfield'), 3),
			      'treeviewgroup3', 'field',['compulsory'=>0,'table'=>$this->target],false,$treeviewlabel);
    } else {
      $this->_options->setComment('Finaliser la configuration du champ puis activer l\'option et compléter les champs de regroupement', 'treeview');
    }
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','boxsize'), 'boxsize', 'text', NULL, 6);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','grouplist'), 'grouplist', 'boolean', NULL, false);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','generate_link'), 'generate_link', 'boolean',NULL, true);
  }

  public function getFilter(){
    if($this->sourcemodule && @$this->mfilter===NULL){
      $mod=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      if($mod && $mod->getFilter()){
      	$this->mfilter=$mod->getFilter();
      }else{
        $this->mfilter='';
      }
    }
    $filter=parent::getFilter();
    if($filter && !empty($this->mfilter)) $filter.=' and ';
    if(!empty($this->mfilter)) $filter.='('.$this->mfilter.')';
    if($filter) return '('.$filter.')';
    else return '';
  }

  public function variableExpansion($str) {
    // si aucune variable dans la chaine pas la peine de faire des calculs
    if(strpos($str, '$')===false) return $str;

    $context=array();
    $u=\Seolan\Core\User::get_user();
    $context['/(\$\(user\))/']=\Seolan\Core\User::get_current_user_uid();
    // expansion de variable sur les données de l'utilisateur connecté... s'il y en a un
    if(!empty($u->_cur['alias'])) {
      $context['/(\$\(user\.alias\))/']=$u->_cur['alias'];
      $context['/(_%user)/']=$u->_cur['alias'];
    }
    $str=preg_replace(array_keys($context),array_values($context),$str);
    return $str;
  }

  public function processQuery($query, $options) {
    $queryfilter=$queryorder=NULL;
    if(!empty($query)) {
      $query = $this->variableExpansion($query);
      preg_match('/^SELECT (.+) FROM .+( WHERE (.*))?( ORDER BY .*)?$/Ui', $query, $matches);
      if(!isset($options['target_fields']) && $matches[-1]!='*') $options['target_fields'] = explode(',', $matches[1]);
      $queryfilter=$matches[3];
      $queryorder=$matches[4];
    }
    return [$query, $queryfilter, $queryorder, $options];
  }

  /**
   * Génère le formulaire d'édition des options du champs
   * @see \Seolan\Core\Options::getDialog()
   */
  function getOptions($block='opt', $get_edit=true) {
    if ($get_edit && !in_array($this->target, ['%',''])) {
      // Ajoute la liste des champs utilisable pour l'affichage des données
      $fields = $textFields = [];
      $targetDS = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->target);
      foreach ($targetDS->desc as $field => $ofield) {
        $fields[] = $field;
        if (!in_array($ofield->ftype, $this->display_text_unwanted_ftypes)) {
          $textFields[] = $field;
        }
      }
      $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','exif_source_help'),'exif_source');
      $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','tokens_available').' :<br>%_'.implode('<br>%_',$fields),'display_format');
      $this->_options->setComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','tokens_available').' :<br>%_'.implode('<br>%_',$textFields),'display_text_format');
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

  function getTypeStringAnnotation() {
    if ($this->sourcemodule) {
      $link = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'function=adminBrowseFields&template=Core/Module.admin/browseFields.html&moid='.$this->sourcemodule;
      $mod=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      $label = "module ";
      return "$label<a class=\"cv8-ajaxlink\" href=\"$link\">{$mod->getLabel()}</a>";
    } else if (\Seolan\Core\DataSource\DataSource::sourceExists($this->target)) {
      $moid = \Seolan\Core\Module\Module::getMoid(XMODDATASOURCE_TOID);
      $boid = \Seolan\Core\DataSource\DataSource::getBoidFromSpecs('\Seolan\Model\DataSource\Table\Table', $this->target);
      $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
      $link = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'function=XDSBrowseFields&template=Module/DataSource.browseFields.html&moid='.$moid.'&boid='.$boid;
      $label = "table ";
      return "$label<a class=\"cv8-ajaxlink\" href=\"$link\">{$xds->getSourceName()}</a>";
    }
    return $this->target;

  }

  function my_export($value, $options=NULL) {
    return $value;
  }

  function getValues($filter2, $options=[]) {
    $mod=&$options['mod'];
    $max=$options['max'];
    $lang=$options['lang']??TZR_DEFAULT_LANG;
    $result=[];
    if($this->target==TZR_DEFAULT_TARGET || !\Seolan\Core\DataSource\DataSource::sourceExists($this->target)) return $result;

    // Liste des champs à recuperer
    list($fielddefs, $fields_coma_sep, $first)=$this->getFieldList($options,false);

    // Création de la requete
    $fromcomp='';
    $filterarray=array();
    $i=0;
    foreach($fielddefs as $fieldname => $fielddef){
      $i++;
      if($filter2){
	if($fielddef->isLink() && !empty($fielddef->target) && $fielddef->target!='%'){
	  $rslink=getDB()->select('select * from DICT where PUBLISHED=1 and DTAB=? and FTYPE=? ORDER BY FORDER', [$fielddef->target, '\\Seolan\\Field\\ShortText\\ShortText']);
	  if ($rslink->rowCount() > 0){
	    $tablealias=$fielddef->target.'_'.$i;
	    $fromcomp.=' left outer join '.$fielddef->target.' as '.$tablealias.' on '.$tablealias.'.KOID='.$this->target.'.'.$fieldname.' and '.$tablealias.'.LANG="'.$lang.'" ';
	    $tmp='';
	    while($rslink && ($orslink=$rslink->fetch()))
	      $tmp.='ifnull('.$tablealias.'.'.$orslink['FIELD'].',""),\' \',';
	    $filterarray[]='",'.substr($tmp,0,-5).',"';
	  }
	  $rslink->CloseCursor();
	}elseif($fielddef->get_ftype()=='\Seolan\Field\StringSet\StringSet'){
	  $tablealias='SETS_'.$i;
	  $fromcomp.=' LEFT OUTER JOIN SETS AS '.$tablealias.' ON '.$tablealias.'.SOID='.$this->target.'.'.$fieldname.' AND '.
		     $tablealias.'.SLANG="'.$lang.'" AND '.$tablealias.'.STAB="'.$this->target.'" AND '.$tablealias.'.FIELD="'.$fieldname.'" ';
	  $filterarray[]='",IFNULL('.$tablealias.'.STXT,""),"';
	} else { // les autres types de champs : recherche brute
	  $filterarray[]='",ifnull('.$this->target.'.'.$fieldname.',""),"';
	}
      }
    }
    if($this->display_format) $df=$this->display_format;
    else $df=str_repeat('%s ',count($filterarray));
    $textfilter2 = (count($filterarray)>0) ? 'concat("'.preg_replace('/%[_a-zA-Z0-9]+/','%s',$df).'")' : '';
    $textfilter2 = '('.substr(vsprintf($textfilter2,$filterarray),0).' LIKE "%'.$filter2.'%") AND ';
    if (\Seolan\Core\Kernel::isAKoid($filter2)) {
      $textfilter2 = "{$this->target}.KOID like '$filter2%' and ";
    }

    $filter=$this->getFilter();
    if(!empty($options['filter'])) $filter='('.$options['filter'].')';
    if(!empty($filter)) $filter.=' AND ';

    // Dans le cas d'une recherche, on récupère les valeurs déjà utilisé pour n'afficher qu'elles
    $used_values=null;
    if($options['query_format']==\Seolan\Core\Field\Field::QUICKQUERY_FORMAT && !\Seolan\Core\Shell::admini_mode()){
      $used_values=$this->_getUsedValues(null,null,$options);
    }
    // cas des champs thesaurus optimisés
    if (isset($this->optimizewith) && isset($options['fmoid'])) {
      $owFilter = \Seolan\Core\User::getDbCacheData($this->optimizewith . 'Filter' . $options['fmoid']);
      if ($owFilter) {
        $used_values = $this->_getUsedValues($owFilter, null, $options);
      }
    }

    $targetTranslatable = (bool)getDB()->fetchOne('SELECT TRANSLATABLE FROM BASEBASE WHERE BTAB = ?', [$this->target]);

    $rs2=getDB()->select('select distinct '.$fields_coma_sep.' FROM '.$this->target.' '.$fromcomp.
		     ' WHERE '.$textfilter2.' '.$filter.' '.($targetTranslatable ? $this->target.'.LANG="'.$lang.'" ' : '1 ').
		     (!empty($fielddefs)?'order by '.$this->target.'.'.implode(','.$this->target.'.',array_keys($fielddefs)):''));

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
      // Filtre sur les valeurs deja utilisées
      if($used_values!==null && !$used_values[$my_oid]) continue;
      if(!empty($mod) && $mod->object_sec && \Seolan\Core\Kernel::getTable($my_oid)==$mod->table && !$mod->secure($my_oid,':ro')) continue;
      $result[$my_oid]=trim($this->format_display($fielddefs,$ors2,$opts,null,'text'));
      $tot++;
    }
    return array('values'=>$result,'state'=>$state);;
  }

  function sqltype() {
    return 'text';
  }


  /// suppression dans la base de donnees de toutes les references à l'oid $oid
  /// sachant qu'il n'existe plus
  function _removeOidInLink($target, $oid) {
    if (empty($this->table) || is_a(\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->table), '\Seolan\Model\DataSource\View\View'))
      return;
    $tolog=false;
    if(!empty($target))
      $tolog=($target->toLog() && $this->table!='LOGS');

    $rs=getDB()->select('select KOID from '.$this->table.' where '.$this->field.' like ? OR '.$this->field.' = ?', ['%|'.$oid.'|%', $oid]);
    if($tolog) {
      while($rs && ($ors=$rs->fetch())){
	\Seolan\Core\Logs::update('autoupdate',$ors['KOID'],"remove oid {$oid} from {$this->field}");
      }
    }
    if($rs->rowCount()>0) {
      getDB()->execute('update '.$this->table.' set UPD=UPD,'.$this->field.'=REPLACE('.$this->field.',"||'.$oid.'||","||") '.
		       'where '.$this->field.' like "%|'.$oid.'|%"');
      getDB()->execute('update '.$this->table.' set UPD=UPD,'.$this->field.'="" '.
		       'where '.$this->field.' =?',[$oid]);
    }
  }
  /**
   * Function isEmpty
   * @return true si le champ n'est pas remplit
   */
  public function isEmpty($r){
    return (empty($r->oidcollection) && empty($r->raw));
  }

  function my_getJSon($o, $options) {
    if (empty($o->oidcollection) && empty($o->raw))
      return null;

    $conf = \Seolan\Core\Json::getLinkConf($options['fmoid'], $this->field);
    $ret = [];
    // lien inclus dans les attributs
    if ($options['follow']) {
      if (!$this->multivalued) {
        if (!$conf) {
          return $o->html;
        }
        $r = [];
        foreach ($o->link['fields_object'] as $f) {
          $fieldAlias = \Seolan\Core\Json::getFieldAlias($conf['moid'], $f->fielddef->field, $o->raw);
          $r[$fieldAlias] = $f->fielddef->getJSon($f->raw, $options['target_options'][$f->fielddef->field]);
        }
        if (in_array('oid', $options['target_fields'])) {
          $r['id'] = \Seolan\Core\Json::cleanOid($options['fmoid'], $o->link['oid']);
        }
        return $r;
      }
      foreach ($o->collection as $_o) {
        if (!$conf) {
          $ret[] = $_o->html;
          continue;
        }
        $r = [];
        foreach ($_o->link['fields_object'] as $ofield) {
          $fieldAlias = \Seolan\Core\Json::getFieldAlias($conf['moid'],$ofield->fielddef->field,$_o->raw);
          $r[$fieldAlias] = $ofield->fielddef->getJSon($ofield->raw, $options['target_options'][$f->fielddef->field]);
        }
        if (!empty($r)) { // les droits
          if (in_array('oid', $options['target_fields'])) {
            $r['id'] = \Seolan\Core\Json::cleanOid($options['fmoid'], $o->link['oid']);
          }
          $ret[] = $r;
        }
      }
      return $ret;
    }
    // lien en relationships
    if (!$this->multivalued) {
      $oids = [$o->raw];
    } else {
      $oids = $o->oidcollection;
    }
    foreach ($oids as $oid) {
      if(\Seolan\Core\Json::getGlobalParam('useModuleRightsForLinks'))
        $conf = \Seolan\Core\Json::getTableMoid($this->target, $oid);
      if(!$conf)
        continue;
      $object = (Object) [
        'type' => $conf['alias'],
        'id' => \Seolan\Core\Json::cleanOid($conf['moid'], $oid)
      ];
      $selectedfields = $conf['subModuleSelectfield'];
      if ($selectedfields) {
        $linkObject = $this->my_display($oid, $o = ['target_fields' => $selectedfields]);
        foreach ($selectedfields as $includeField) {
          if (isset($linkObject->link['o'.$includeField])) {
            $object->attributes->$includeField = $linkObject->link['o'.$includeField]->text;
          }
	}
      }
      $ret[] = $object;
    }
    if (!$this->multivalued)
      $ret = $ret[0];

    return $ret?$ret:null;
  }

 /**
  * Affichage du champ lien, appel rDisplay sur la cible
  * (traitement multivalue dans \Seolan\Core\Field\Field)
  * ! effet de bord : si l'objet est introuvable, supprime tous les liens vers cet objet
  *   sauf si \Seolan\Core\DbIni[disable_xlinkclean] est positionné à 1
  * @param $value string koid  unique dans la table cible ou 'UNPUBLISHED',
  * @param array $options :
  *    string  _format        application/excel, application/prince
  *    string  _charset       charset passé au rDisplay
  *    mixed   target_fields  tableau des champs ramenés ou 'all' (rDisplay selectedfields)
  *    boolean nocache        n'utilise pas le cache
  *    boolean nofollowlinks  retourne juste l'oid sans appel au rdisplay
  * ]
  * @return \Seolan\Core\Field\Value
  */
  function my_display_deferred(&$r){
    $options=&$r->options;
    $value=&$r->raw;
    $_format = @$options['_format'];
    $LANG_DATA = \Seolan\Core\Shell::getLangData(@$options['lang_list']);
    $lang = \Seolan\Core\Shell::getLangUser();
    $olddisplayformat=NULL;

    if(empty($value)) return $r;
    // l'oid sans la cible
    if(!empty($options['nofollowlinks'])) {
      $r->raw=$value;
      $r->html='';
      return $r;
    }
    // Table existe (cas target = DEFAULT)
    $target=($this->target == TZR_DEFAULT_TARGET)?\Seolan\Core\Kernel::getTable($value):$this->target;
    if(!\Seolan\Core\System::tableExists($target)) {
      $this->_removeOidInLink(NULL, $value);
      return $r;
    }
    // Droit sur le module source de données
    if($this->sourcemodule){
      $mod=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      if($mod->object_sec && !$mod->secure($value,':ro') || !$mod->object_sec && !$mod->secure('',':ro')) return $r;
    }
    $target_ds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($target);
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
    if ($targetopts['selectedfields'] != 'all')
      $targetopts['selectedfields']=array_merge($fmtfields,$targetopts['selectedfields']);
    $targetopts['fmoid'] = @$options['fmoid'];
    // Recupere les champs de l'objet cible
    $values=$target_ds->rDisplay($value, array(), false, $LANG_DATA, $lang, $targetopts);
    $cnt=count($values['fields_object']);
    if (\Seolan\Core\Shell::admini_mode())
      $cnt = max($cnt, $this->getNbPublishedFields());

    // Si l'oid n'existe plus, on met à jour la table pour supprimer l'oid partout ou il est present et on retourne un objet sans valeur sauf si l'option nodeletevalue est active (champs ou un veut garder la valeurs, ex : LOGS::object)
    if(!is_array($values) && !in_array($values, array('UNPUBLISHED','is empty','is not empty')) && \Seolan\Core\DbIni::get('disable_xlinkclean','val')!=1) {
      // Si la table est traduisible et qu'on est pas dans la langue par defaut, on verifie si l'oid existe dans au moins une langue
      if($target_ds->isTranslatable() && $LANG_DATA!=TZR_DEFAULT_LANG){
	if($target_ds->objectExists($value, '%')) return $r;
      }
      // suppression des valeurs qui n'existent plus
      if ($this->nodeletevalue){
	$r->html = "[{$r->raw}]";
      } else {
	$oldraw = $value;
	$this->_removeOidInLink($target_ds, $value);
	// on corrige la donnée brute du multivalué associé
	if (isset($r->_masterValue)){
	  $r->_masterValue->raw = $this->sanitizeRawFormat(str_replace($oldraw, '', $r->_masterValue->raw));
	  $r->_masterValue->fielddef->multiOidPurgeCollections($oldraw, $r->_masterValue);
	}
	$r->raw='';
      }
      return $r;
    }elseif(in_array($values, array('UNPUBLISHED','is empty','is not empty'))){
      $r->raw='';
      return $r;
    }
    // Prépare le html
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
    if (empty(trim($display)) && \Seolan\Core\Shell::admini_mode()) {
      $r->html = '[' . \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','not_displayable') . ']';
    } else {
      $r->html = $display;
    }
    if(\Seolan\Core\Shell::admini_mode() && $this->generate_link && !\Seolan\Core\Module\Module::$modlist_loading){
      if($this->modsUsingTable===NULL){
	if($this->sourcemodule) $this->modsUsingTable=array($mod->_moid=>$mod->getLabel());
	else $this->modsUsingTable=\Seolan\Core\Module\Module::modulesUsingTable($target,false,'only',true,true,false);
      }
      if(!empty($this->modsUsingTable)) {
	$url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(false,true);
	if($_format!='application/prince' && $_format!='application/excel') {
	  if(count($this->modsUsingTable)>1) {
	    $mi=0;
	    foreach($this->modsUsingTable as $mod=>&$label) {
	      $mi++;
	      if($mi<=XLINKDEF_MAXLINKS) {
		if($mi==1) $r->html.=' [';
		else $r->html.='|';
		$r->html.='<a class="cv8-ajaxlink" href="'.$url.'&moid='.$mod.'&function=goto1&oid='.$value.'" title="'.$label.'">'.$mi.'</a>';
	      }
	    }
	    if($mi>0) $r->html.=']';
	  } else {
	    $label=array_values($this->modsUsingTable)[0];
	    $modid=array_keys($this->modsUsingTable)[0];
	    $r->html='<a class="cv8-ajaxlink" href="'.$url.'&moid='.$modid.'&function=goto1&oid='.$value.'" title="'.$label.'">'
              .preg_replace('@\[<a.*</a>\]|<a .*>|<\/a>@U', '', $r->html).'</a>';
	  }
	}
        if ($this->target == '%') {
          $this->modsUsingTable = null;
        }
      }
    }
    $r->title=$display;
    $r->link=$values;
    $r->raw=$value;
    if($olddisplayformat) $this->display_format=$olddisplayformat;
    return $r;
  }
  /// Un champ de type Lien n'est en edit que sur la langue par defaut.
  function my_edit(&$value,&$options,&$fields_complement=NULL) {
    $queryorder='';
    $queryfilter='';
    if($this->target==TZR_DEFAULT_TARGET){
      if (!$this->multivalued){
	return $this->my_display($value,$options);
      }
    }
    $p=new \Seolan\Core\Param($options,[]);
    $lang_data=\Seolan\Core\Shell::getLangData();
    $r=$this->_newXFieldVal($options);
    $r->raw=$value;
    if(!\Seolan\Core\DataSource\DataSource::sourceExists($this->target)) {
      $r->html='';
      return $r;
    }
    $format=@$options['fmt'];
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
    // cas ou il y a une requete qui donne l'ensemble des valeurs
    list($query, $queryfilter, $queryorder, $options)=$this->processQuery($this->edit_query?:$this->query, $options);

    // Liste des champs de la cible
    if(!empty($options['display_format'])){
      $olddisplayformat=$this->display_format;
      $this->display_format=$options['display_format'];
    } elseif (isset($this->edit_format) && strpos($this->edit_format, '%_') !== false){
      $this->display_format = $this->edit_format;
      $olddisplayformat=$this->display_format;
    }
    list($myliste,$my_flist,$first)=$this->getFieldList($options);

    if ($this->autocomplete && $this->autocomplete_limit == 0) {
      $this->getAutocomplete($value,$options,$r,null,$fname,$hiddenname,$myliste);
      return $r;
    }elseif($format=='filled' && !$this->compulsory) {
      $this->getFilled($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
      return $r;
    }elseif($this->treeview){
      $this->getTreeview($value,$options,$r,$rs2,$hiddenname,$myliste);
      return $r;
    }
    // parcours du module pour selection dans une popup
    if (!empty($this->sourcemodule) && $this->browsesourcemodule){
      $this->getBrowseSourceModule($value,$options,$r,null,$fname,$hiddenname,$myliste);
      return $r;
    }
    // Construction de la requete
    // est ce que l'on veux les objets publies
    $dstarget=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->target);
    $dspublishedMode = $dstarget->publishedMode($p);

    if(!empty($options['filter'])) $filter='('.$options['filter'].')';
    else $filter=$this->getFilter();
    if(!empty($queryfilter)) {
      if(!empty($filter)) $filter.=' AND ';
      $filter.=$queryfilter;
    }
    if($dspublishedMode == 'public'){
      if(!empty($filter)) $filter .= ' AND ';
      $filter .= ' PUBLISH = 1 ';
    }
    if(!empty($filter)) $filter.=' AND ';

    $maxrecords=max($this->checkbox_limit, $this->autocomplete_limit)+1;
    if($dstarget->isTranslatable())
      $rs2=getDB()->select('SELECT DISTINCT '.$my_flist.' FROM '.$this->target.' WHERE '.$filter." LANG='$lang_data'".
                      ($queryorder?$queryorder:($first?' ORDER BY '.$first:'')).' LIMIT '.$maxrecords);
    else
      $rs2=getDB()->select('SELECT DISTINCT '.$my_flist.' FROM '.$this->target.' WHERE '.$filter.' 1 '.
                      ($queryorder?$queryorder:($first?' ORDER BY '.$first:'')).' LIMIT '.$maxrecords);

    // Format de la saisie
    $nb=($rs2?$rs2->rowCount():0);
    $checkbox=($nb<=$this->checkbox_limit || $format=='checkbox') && $format!='listbox' && $this->checkbox && empty($options['simple']);
    $autocomplete=($nb>=$this->autocomplete_limit && $this->autocomplete);
    $doublebox=($this->multivalued && $this->doublebox && empty($options['simple']));
    if($checkbox) $this->getCheckboxes($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
    elseif($autocomplete) {
      $this->getAutocomplete($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
    }
    elseif($doublebox) $this->getDoubleSelect($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
    else $this->getSelect($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
    if(!empty($olddisplayformat)) $this->display_format=$olddisplayformat;
    return $r;
  }
  /// Edition par un treeView
  protected function getTreeview($value,$options,$r,$rs2,$hiddenname,$myliste){
    $varid = $r->varid = $this->field.uniqid();
    $querymode = $options['querymode']??false;
    // valeurs actuellement sélectionnées
    $selectedOids = array_filter((is_array($r->raw))?array_keys($r->raw):[$r->raw]);

    // niveau 1 de l'arbo
    $n1 = $this->treeviewLevel('');

    $tree = $this->formatSimpleTreeLines($n1, $options);

    $selectedLabels = [];
    $dispOptions = [];
    foreach($selectedOids as $oid){
      $selectedLabels[] = htmlspecialchars($this->display($oid, $dispOptions)->text, ENT_QUOTES);
    }

    if (count($selectedOids)>0){
      $seljs = "['".implode("','", $selectedOids)."']";
      $seljsLabels = "['".implode("','", $selectedLabels)."']";
    } else {
      $seljs = "[]";
      $seljsLabels = "[]";
    }
    $varidtree = "{$varid}tree";
    $js = [];
    $js[] = "TZR.xlinkdef_treeview.init.call(TZR.xlinkdef_treeview, '#{$varidtree}', '{$this->field}', {$seljs}, {$seljsLabels}, {$n1['maxdepth']}, {multivalued:'{$this->multivalued}'})";
    if ($this->compulsory && !$querymode){
      $label = addslashes($this->label);
      $errcolor = \Seolan\Core\Ini::get('error_color');
      $js[]=<<<EOF
      TZR.addValidator(["{$r->varid}",
			/(.+)/,
			"{$label}",
			"$errcolor",
                        "\Seolan\Field\Link\Link",
                        "{$r->varid}",
                        {treeviewmode:true,multivalued:"{$this->multivalued}",id:"{$r->varid}"}
                        ]);
EOF;
    }
    // configuration de l'autocompletion
    $urlautocomplete=TZR_AJAX8
		    .'?'
		    .http_build_query(['class'=>$options['_autocomplete']['class']??'_Seolan_Field_Link_Link',
				       'function'=>$options['_autocomplete']['method']??'xlinkdef_autocomplete',
				       '_silent'=>1,
				       'target_fields'=>'fullnam',
				       'query_format'=>$options['query_format']??null,
				       'oid'=>$options['oid'],
				       'ffm'=>$options['ffm']??null,
		    ]);
    $varidautocomplete = "_INPUT{$varid}autocomplete";
    $js[] = <<<EOF
jQuery("#{$varidautocomplete}").data(
"autocomplete",
{url:"{$urlautocomplete}",
params:{multivalued:"{$this->multivalued}",
moid:"{$options['fmoid']}",
table:"{$this->table}",
field:"{$this->field}",
varid:"{$varidautocomplete}",
id:"{$varidautocomplete}"},
callback:TZR.xlinkdef_treeview.autoComplete}).data(
"tree",{varid:"{$varidtree}"}
);
TZR.xlinkdef_treeview.addAutoComplete("{$varidautocomplete}");
EOF;
    $r->html = <<<EOF
<div id="{$varid}search"><input autocomplete="off" id="{$varidautocomplete}" size="30" type="text" value="" class="tzr-link ui-autocomplete-input"></div>
<div id="{$varid}selected"><input id="{$varid}" name="{$this->field}" type="hidden" value=""></div>
<ul class="simpleTree" id="{$varidtree}">
<li class="root">
<span>{$this->label}</span>
<ul>{$tree}</ul></ul>
EOF;
    $r->html .=  '<script>'.implode(";\n", $js).'</script>';
  }
  /**
   * met en forme les lignes de resultat pour simpleTree
   */
  public function formatSimpleTreeLines($level, $options){
    $tree = '';
    $nb = count($level['lines']);
    foreach($level['lines'] as $i=>$line){
      $path = htmlspecialchars($line['path']);

      $id = str_replace([':','/'],'-', "{$line['path']}/{$line['raw']}");
      if ($line['node']=='node'){
	$loadurl = makeUrl(TZR_AJAX8, ['class'=>'_Seolan_Field_Link_Link',
				       'function'=>'xlinkdef_treeview',
				       'moid'=>$options['fmoid'],
				       'table'=>$this->table,
				       'field'=>$this->field,
				       'path'=>$line['path'],
				       'dataType'=>'simpletree',
				       'ffm'=>$options['ffm']??null]);
	$last = ($i<$nb-1)?'':'last';
	$pathfields = implode(",", $line['pathfields']);
	$tree.=<<<EOF
        <li id="{$id}" class="folder close {$last}" data-value="{$line['path']}"
                                                  data-path="{$line['path']}"
                                                  data-type="node",
                                                  data-level="{$line['depth']}"
                                                  data-name="{$this->field}[{$pathfields}][]">
        <span><span class="unselected">{$line['label']}</span></span>
        <ul class="ajax"><li>{url:"{$loadurl}"}</li></ul>
        </li>
EOF;
      } else {
	$tree.=<<<EOF
      <li class="doc" data-value="{$line['raw']}"
                      data-type="leaf",
                      data-name="{$this->field}[]"><span class="text"><span class="unselected">{$line['label']}</span></span></li>
EOF;
      }
    }
    return $tree;
  }
  /// le contenu d'un noeud identifié par $path
  public function treeviewLevel(string $path, ?bool $labels=true){

    $targetds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->target);
    for($i=1; $i<=3; $i++){
      $on = "treeviewgroup{$i}";
      $fn = $this->$on;
      if ($targetds->fieldExists($fn))
	$gfields[] = $fn;
    }
    $maxdepth = count($gfields);
    $gfieldscount = count($gfields);
    $qfields = $pfields = $cond = [];

    // $qfields = champs à lire (un seule en fait)
    // $pfields = champs de recherche : le groupe ou rien : le path
    $emptypath = '*default*'; // si un champ de groupe est vide

    // à voir de passer en path[] pb encodage etc
    $pathValues = preg_split('@/@', $path, -1, PREG_SPLIT_NO_EMPTY);
    $level = count($pathValues)+1;
    for($i=0; $i<$level; $i++){
      if ($i < $gfieldscount){
	$fn = $gfields[$i];
	if (isset($pathValues[$i])){
	  $pfields[] = "$fn";
	  $cond[$fn] = ['=', $pathValues[$i]==str_replace('*', $fn, $emptypath)?'':$pathValues[$i]];
	} else {
	  $qfields[] = "$fn";

	}
      }
    }

    if (empty($qfields) || $i>$gfieldscount){
      $qfields[] = "KOID";
      $node = 'leaf';
    } else {
      $node = 'node';
    }

    $where = null;
    // filtre du module source des données
    if (!empty($this->sourcemodule)){
      $smod = \Seolan\Core\Module\Module::objectFactory(['moid'=>$this->sourcemodule,
							 'interactive'=>false,
							 'tplentry'=>TZR_RETURN_DATA]);
      $where = $smod->getFilter(true);

    }

    \Seolan\Core\Logs::debug(__METHOD__." {$this->field} pfields : ".implode(',', $pfields)." qfields : ".implode(',', $qfields));

    $select = " DISTINCT ifnull({$qfields[0]},'') fvalue, '{$qfields[0]}' fname, '{$node}' node, '{$path}' linepath";

    $query = $targetds->select_query(['cond'=>$cond,
				      'select'=>$select,
				      'where'=>$where]);

    $lines = [];
    $dispOptions = [];
    if (isset($this->edit_format) && strpos($this->edit_format, '%_') !== false){
      $dispOptions['display_format'] = $this->edit_format; // pour être cohérent avec l'edition
    }
    $slabels = [];

    // ajout des requêtes pour éléments vides
    if ($node == 'node'){

      $iqfield = array_search($qfields[0], $gfields);

      \Seolan\Core\Logs::debug(__METHOD__." empty group field for path : '{$path}' group fields :'".implode(',',$gfields)."'");

      if ($iqfield<($gfieldscount)){
	$ucond = [];
	$upath = [];
	for($j=$iqfield; $j<$gfieldscount; $j++){
	  $ucond = $cond+$ucond;
	  $upath = $pathValues+$upath;
	  $uqfield = $gfields[$j+1]??'KOID';
	  $ucond[$gfields[$j]] = ['=', ''];
	  $upath[] = str_replace('*', $gfields[$j], $emptypath);
	  $unode = ($uqfield == 'KOID')?'leaf':'node';
	  $linepath = implode('/', $upath);
	  $uselect = " DISTINCT ifnull({$uqfield},'') fvalue, '{$uqfield}' fname, '$unode' node, '$linepath' linepath";
    	  $uquery = $targetds->select_query(['cond'=>$ucond,
					     'select'=>$uselect,
					     'where'=>$where]);
	  $query .= "\n union all \n $uquery";
	  \Seolan\Core\Logs::debug(__METHOD__." union all query {$uquery}");
	}
      }
    }

    \Seolan\Core\Logs::debug(__METHOD__." final query $query ");

    foreach(getDB()->select($query)->fetchAll(\PDO::FETCH_NUM) as list($fv, $fn, $linetype, $linepath)){

      $fv = trim($fv);
      $flabel = null;
      if ($labels){
	if (empty($fv)){
	  continue;
	  $fv = str_replace('*', $qfields[0],$emptypath);
	  $flabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','treeviewemptygroup');
	} else {
	  if ($fn == 'KOID'){
	    $flabel = $this->display($fv, $dispOptions)->text;
	  } else {
	    $flabel = $targetds->getField($fn)->display($fv, $dispOptions)->text;
	  }
	}
      }

      $lines[] = [
	'node'=>$linetype,
	'raw' =>$fv,
	'path'=>"{$linepath}/$fv",
	'depth'=>$level,
	'label'=>$flabel,
	'lqfield'=>$fn
      ];
      $slabels[] = $flabel;
    }
    array_multisort($slabels,SORT_STRING | SORT_FLAG_CASE, $lines);

    return ['lines'=>$lines,
	    'pathfields'=>$pfields+array($qfields[0]),
	    'query'=>$query,
	    'path'=>$path,
	    'maxdepth'=>$maxdepth,
	    'qfields'=>implode(',', $qfields),
	    'pfields'=>implode(',', $pfields)];
  }
  /// Recherche
  function my_query($value,$options=NULL) {
    $queryorder='';
    $queryfilter='';
    $format=@$options['fmt'];
    if(empty($format)) $format=@$options['qfmt'];
    if(empty($format)) $format=$this->query_format;
    $searchmode=@$options['searchmode'];
    $fname=(isset($options['fieldname'])?$options['fieldname']:$this->field);
    $hiddenname=$fname.'_HID';
    $r=$this->_newXFieldVal($options,true);
    $r->raw=$value;
    if($this->target==TZR_DEFAULT_TARGET) {
      $r->html.='<input name="'.$fname.'" maxlength="30" size="30" type="text" value="'.(is_string($value)?$value:'').'">';
      return $r;
    }

    // Liste des champs de la cible
    if(!empty($options['display_format'])){
      $olddisplayformat=$this->display_format;
      $this->display_format=$options['display_format'];
    }
    list($myliste,$my_flist,$first)=$this->getFieldList($options);

    if ($format == 'autocomplete' || ($this->autocomplete && $this->autocomplete_limit == 0)) {
      $this->getAutocomplete($value,$options,$r,null,$fname,$hiddenname,$myliste);
      return $r;
    }

    // Construction de la requete
    $filter=[];

    if(\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->target)->isTranslatable()){
      $lang = \Seolan\Core\Shell::getLangData();
    } else {
      $lang = TZR_DEFAULT_LANG;
    }

    // cas ou il y a une requete qui donne l'ensemble des valeurs
    $foo=[];
    list($query, $queryfilter, $queryorder, $foo)=$this->processQuery($this->query, []);

    if(!empty($queryfilter)) {
      $filter[] = $queryfilter;
    }

    if(!empty($options['filter'])) $filter[]='('.$options['filter'].')';
    elseif(($tmp=$this->getFilter())) $filter[]=$tmp;

    if(!empty(rtrim($query))) {
      $inclause=array();
      $rs=getDB()->select($query);
      while($rs && ($ors=$rs->fetch())) $inclause[]="'".$ors['KOID']."'";
      $filter[]='(KOID IN ('.implode(',',$inclause).'))';
    }
    if($searchmode=='simple') {
      $allvalues=array();
      $selectquery=@$options['select'];
      if(!empty($selectquery)) $allvalues=$this->_getUsedValues(NULL,$selectquery);
      else $allvalues=$this->_getUsedValues("LANG='".$lang."'",null,$options);
      $filter[]='(KOID IN ("'.implode('","',array_keys($allvalues)).'"))';
    }
    if(!empty($filter)) $filter=implode(' AND ',$filter).' AND ';
    else $filter='';

    if(!\Seolan\Core\System::tableExists($this->target)) return $r;

    $rs2=getDB()->select("SELECT DISTINCT $my_flist FROM {$this->target} WHERE $filter LANG=\"$lang\" ".
                         ($queryorder?$queryorder:($first?" ORDER BY $first":"")));
    if($format=='filled' && !$this->compulsory) {
      $this->getFilled($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
      return $r;
    }
    // Format de la saisie
    if($rs2) $nb=$rs2->rowCount();
    if ($nb >= $this->autocomplete_limit && $this->autocomplete) {
      $this->getAutocomplete($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
      return $r;
    }
    $checkbox=(($nb<=$this->checkbox_limit || $format=='checkbox') && $format!='listbox' && $format!='listbox-one' && $this->checkbox);
    $op='';
    if($this->get_multivalued() && $format!='listbox-one'){
      $textid = $fname.'_id';
      $op=$options['op'];
      $medit='<select name="'.$fname.'_op">
        <option value="AND"'.($op==='AND'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_allterms').'</option>
        <option value="OR"'.($op==='OR'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_leastaterm').'</option>
        <option value="NONE"'.($op==='NONE'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_noterm').'</option>
        <option value="EXCLUSIVE"'.($op==='EXCLUSIVE'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_onlyterms').'</option>
        </select><br>';
    }else{
      $medit='';
    }
    if(@$options['genempty'] !== false && \Seolan\Core\Shell::admini_mode()){
      $textid = $fname.'_id';
      $op = $options['op'];
      if($this->get_multivalued()) {
	$medit='<select name="'.$fname.'_op">
        <option value=""'.(!$op?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_selecterm').'</option>
        <option value="!="'.($op==='!='?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','xlinkdef_diffterm').'</option>
        <option value="is empty"'.($op==='is empty'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','is_empty').'</option>
        <option value="is not empty"'.($op==='is not empty'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','is_not_empty').'</option>
        </select><br>';
      } else {
	$medit='<select name="'.$fname.'_op">
        <option value=""'.(!$op?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','equal_to').'</option>
        <option value="!="'.($op==='!='?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query_different').'</option>
	<option disabled>___________</option>
        <option value="is empty"'.($op==='is empty'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','is_empty').'</option>
        <option value="is not empty"'.($op==='is not empty'?' selected':'').'>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','is_not_empty').'</option>
        </select><br>';
      }
    }
    if($checkbox) $this->getCheckboxes($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
    else $this->getSelect($value,$options,$r,$rs2,$fname,$hiddenname,$myliste);
    $r->html=$medit.$r->html;
    if(!empty($olddisplayformat)) $this->display_format=$olddisplayformat;
    return $r;
  }

  function my_quickquery($value,$options=NULL) {
    $oldc=$this->checkbox;
    $oldd=$this->doublebox;
    $olda=$this->autocomplete;
    $this->checkbox=false;
    $this->doublebox=false;
    $this->autocomplete=true;
    $options['genempty'] = false;
    $ret=$this->query($value, $options);
    $ret->html.='<input type="hidden" value="'.$this->field.'" name="_FIELDS['.$this->field.']">';
    $this->checkbox=$oldc;
    $this->doublebox=$oldd;
    $this->autocomplete=$olda;
    return $ret;
  }
  private static function treeViewGetValues($path, $field){
    $values = [];
    $level = $field->treeviewLevel($path, true);
    foreach($level['lines'] as $line){
      if ($line['node'] == 'node'){
	$values = array_merge($values, static::treeViewGetValues($line['path'], $field));
      } else {
	$values[] = $line['raw'];
      }
    }
    return $values;
  }
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options);
    $oid=$options['oid'];
    // édition par treeview : remplacement des groupes par les oid
    // des items du groupe
    if ($this->treeview && is_array($value)){
      $pathsValues = [];
      foreach($value as $k=>$v){
	if (!is_int($k)){
	  foreach($v as $path){
	    $pathsValues = array_merge($pathsValues, static::treeViewGetValues($path, $this));
	  }
	  unset($value[$k]);
	}
      }
      $value = array_unique(array_merge($value, $pathsValues));
      if (!$this->multivalued)
	$value = $value[0];
    }
    if(is_array($value)) {
      $nvalue=array();
      foreach($value as $v1=>$v2) {
	if(\Seolan\Core\Kernel::isAKoid($v1) && ($v1!=$oid) && !empty($v2)) $nvalue[]=$v1;
	elseif(\Seolan\Core\Kernel::isAKoid($v2) && ($v2!=$oid)) $nvalue[]=$v2;
      }
      $value=array_unique($nvalue);
    }
    if(!empty($this->exif_source) && empty($value)){
      $value=array();
      $meta=$this->getMetaValue($fields_complement,array('IPTC','EXIF','XMP'),true);
      if(!is_array($meta->raw)) $meta->raw=array($meta->raw);
      if($meta->schema->dest) $opt=array('srcField'=>$meta->schema->dest);
      else $opt=array('srcField'=>$this->flabel);
      if(($meta->schema->create===null || $meta->schema->create) && $this->isAutorizedToAdd($options)) $opt['create']=true;
      foreach($meta->raw as $i=>$v){
	if(!is_string($v)) continue;
	$ret=$this->my_import($v,$opt);
	$value[]=$ret['value'][0];
      }
    }
    if(!$this->multivalued && is_array($value) && count($value)===1)
      $value=array_shift($value);
    // Empêche le lien vers un objet de pointer vers l'objet en cours d'édition
    if($value==$oid) $value=NULL;
    $r->raw=$value;
    // Edition par lot sur champ multivalué
    if((!empty($options['editbatch']) || !empty($options['merge'])) && $this->multivalued){
      $p=new \Seolan\Core\Param($options);
      $op=$p->get($this->field.'_op');
      if (is_object($options['old'])){ // on dispose en pcpe de oid collection
	$old=explode('||',$options['old']->raw);
      } else { // données brutes seulement
	$old=explode('||',$options['old']);
      }
      if($op=='+') $r->raw=array_unique(array_merge($r->raw,$old));
      elseif($op=='-') $r->raw=array_diff($old,$r->raw);
      $r->raw = array_values(array_filter($r->raw));
    }
    // Trace
    $old_fieldval = @$options['old'];
    if(!empty($old_fieldval)){
      if(is_array($r->raw)) $v=implode('||',$r->raw);
      else $v=$r->raw;
      $new_fieldval = $this->display($v, $options);
      $new_value = $this->sanitizeRawFormat($new_fieldval->raw);
      $old_value = $this->sanitizeRawFormat($old_fieldval->raw);
      if ($new_value != $old_value) {
        // Trace les modification au format HTML si la chaine n'est pas trop longue (si trop long les données ne s'insèrent pas correctement dans les LOGS)
        if (strlen($new_fieldval->html) < 1000 && strlen($old_fieldval->html) < 1000) {
          $this->trace($old_fieldval, $r, '['.$old_fieldval->html.'] -> ['.$new_fieldval->html.']');
        } else {
          $this->trace($options['old'], $r, '['.$old_value.'] -> ['.$new_value.']');
        }
      }
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
  /**
   * mise en forme des données de recherche
   * si normalisé, on place les sous requêtes et stop là :
   * AND [A, B] => koiddst in (A,B) AND group_concat(koiddst) = AB
   * OR [A, B] => koiddst in (A,B)
   * si dans une fiche on a
   * A,B => OR et AND
   * A,B,C => OR et AND aussi : toutes les valeurs soit A et B, C n'est pas concerné
   * A,C => OR uniquement, n'a pas B
   */
  function post_query($o,$options=NULL) {
    $p=new \Seolan\Core\Param($options,array());
    if ($o->op == 'is empty'){
      $o->value = '';
    } else {
      if (is_array($o->value) && (($o->hid=='checkbox') || ($o->fmt=='checkbox')))
	$o->value = array_keys($o->value);
      elseif ($o->hid=='filled' || $o->fmt=='filled') {
	$o->op=$o->value;
      }
    }
    // lien multivalué et normalisé
    if($this->get_multivalued() &&!empty($o->value) && $this->normalized && (empty($o->op) || in_array($o->op, ['AND','OR']))) {
      if (empty($o->op))
	$o->op = 'OR';
      if (!is_array($o->value))
	$values [$o->value];
      else {
	$values = array_filter($o->value, function($val){
	  return !empty($val);
	});
	$quotedValues = array_map(
	  function($val){
	    return getDB()->quote($val);
	  },
	  $values);
      }
      if (!empty($values)){
	$reltable = $this->getRelationTableName();
	$lang = \Seolan\Core\Shell::getLangData();
	if(!$this->get_translatable())
	  $lang=TZR_DEFAULT_LANG;
	$o->rq = "({$this->table}.KOID IN (SELECT {$reltable}.KOIDSRC FROM {$reltable} WHERE {$reltable}.KOIDDST IN (".implode(',',$quotedValues).") AND {$reltable}.LANGSRC='{$lang}'";
	// comme pour OR (doit permettre de filter les lignes) + koidsrc ayant toutes les valeurs seulement
	if ($o->op == 'AND'){
	  asort($values);
	  $o->rq .= " /*OPERATOR 'AND' */ GROUP BY KOIDSRC, LANGSRC HAVING GROUP_CONCAT(KOIDDST ORDER BY KOIDDST ASC SEPARATOR '')=".getDB()->quote(implode('', $values))."";
	}
	$o->rq .= '))';
	return; // no further treatment required
      }
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
    $verify_ftypes=($prop=='text');
    $opt['_options']['error']='return';
    if ($prop == 'text' && !empty($this->display_text_format) && strpos($this->display_text_format,'%_')!==false) {
      $replace=true;
      $display=$this->display_text_format;
    } elseif (!empty($this->display_format) && strpos($this->display_format,'%_')!==false) {
      $replace=true;
      $display=$this->display_format;
    }
    foreach($myliste as $k=>$f) {
      // Types de champs à ne pas afficher dans les listes <select> et <input autocomplete>
      if ($verify_ftypes && in_array($f->ftype, $this->display_text_unwanted_ftypes)) continue;
      $o=$f->display($ors[$k],$opt);
      if(is_object($o)) {
	// AR20200303 : Si la prop est vide on affiche pas le raw (pour éviter d'avoir des KOID en front)
	// $t=(!empty($o->$prop)?$o->$prop:$o->raw);
	$t=$o->$prop;
	if($replace) $display=str_replace('%_'.$k,$t,$display);
	else $htmls[]=$t;
      }
    }
    if(!$replace){
      if($prop == 'text' && !empty($this->display_text_format))
        $display=@vsprintf($this->display_text_format,array_pad($htmls,substr_count($this->display_text_format,'%'),''));
      elseif(!empty($this->display_format))
        $display=@vsprintf($this->display_format,array_pad($htmls,substr_count($this->display_format,'%'),''));
      else $display=implode(' ',$htmls);
    }
    if (empty(trim($display)) && \Seolan\Core\Shell::admini_mode()) {
      $display = '[' . \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','not_displayable') . ']';
    }
    return preg_replace('/%_\w*/', '', $display);
  }
  /// Valeur pour une cellule feuille de calcul
  function getSpreadSheetCellValue($value, $options=null){
    if (is_a($value, \Seolan\Core\Field\Value::class)){
      $generate_link = $this->generate_link;
      $this->multiOidSet($value, 'generate_link', "0");
      $text=str_replace('&nbsp;', ' ', $value->text);
      $this->multiOidSet($value, 'generate_link', $generate_link);
    } elseif(is_string($value)) {
      $text = $value;
    } else {
      $text = '';
    }
    return $text;
  }
  /// Ecriture dans un fichier excel
  function writeXLSPHPOffice(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet,$rownum,$colnum,$value,$format=0,$options=null) {
    $text = $this->getSpreadSheetCellValue($value, $options);
    convert_charset($text,TZR_INTERNAL_CHARSET,'UTF-8');
    $worksheet->setCellValueByColumnAndRow($colnum,$rownum,$text);
    if (is_array($format))
      $worksheet->getStyleByColumnAndRow($colnum, $rownum)->applyFromArray($format);
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
  ///           separator (string) => séparateur pour des valeurs multiples (['|',','] dans TZR_IMPORT_SEPARATOR)
  ///           forcekoid (true/false) => force la génération de l'oid à partir de la valeur (false par defaut)
  function my_import($value,$specs=null){
    $separator=$specs->separator;
    if(empty($separator))
      $separator = TZR_IMPORT_SEPARATOR;
    $create=$specs->create;
    $forcekoid=$specs->forcekoid;
    $srcField=$specs->srcField;
    if(!empty($specs->input) && is_array($specs->input)){
      $inputar=$specs->input;
    }else{
      $inputar=[];
    }
    $message='';
    if($value!=''){
      if(empty($srcField) && !\Seolan\Core\Kernel::isAMultipleKoid($value)){
	$srcField=array();
	$rs=getDB()->select('select FIELD, FTYPE from DICT where DTAB="'.$this->target.'" and PUBLISHED=1 order by forder limit '.$this->getNbPublishedFields());
	while($rs && $ors=$rs->fetch()) {
	  if (!in_array($ors['FTYPE'], $this->display_text_unwanted_ftypes))
	   $srcField[]=$ors['FIELD'];
	}
      }else{
	$srcField=array($srcField);
      }
      if(!empty($srcField)){
	$ret=array();
	utf8_cp1252_replace($value);
        if(is_array($separator)) {
          $value = str_replace($separator, $separator[0], $value);
          $valueslist = explode($separator[0], $value);
        }
        else {
          $valueslist = explode($separator, $value);
        }

	$concatfields='CONCAT('.implode('," ",', $srcField).')';
	foreach($valueslist as $v){
	  $v=mb_trim($v);
	  if($v!=''){
	    $filter=$this->getFilter();
	    if(!empty($specs->filter))
	      $filter='('.$specs->filter.')';
	    if(!empty($filter)) $filter.=' AND ';
	    if($concatfields != "CONCAT()" ){
	      $rs=getDB()->select('SELECT KOID FROM '.$this->target.' WHERE '.$filter.' TRIM(UPPER('.$concatfields.'))=?',array(strtoupper($v)));
	    }else{
	      $rs=getDB()->select('SELECT KOID FROM '.$this->target.' WHERE '.$filter.' KOID=?',array(strtoupper($v)));
	    }
	    if($rs && $ors=$rs->fetch()){
	      $ret[]=$ors['KOID'];
	    }else{ // la target n'existe pas
	      if($create){
		$toinsert=$inputar;
		if(!empty($forcekoid) && $forcekoid!='false'){
		  $toinsert['newoid']=$this->target.':'.strtoupper(preg_replace('/([^a-z0-9]+)/','',rewriteToAscii($v)));
		}
		$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->target);
		$toinsert['tplentry']=TZR_RETURN_DATA;
		$toinsert['PUBLISH']=1;
		$toinsert[$srcField[0]]=$v;
		$mess=$xset->procInput($toinsert);
		$message.=$this->field.' : "'.$v.'" created<br/>';
		$ret[]=$mess['oid'];
	      }else{
		$message.='<u>Warning</u> : '.$this->field.' : "'.$v.'" doesn\'t exist (search field(s) : '.implode(',', $srcField).')<br/>';
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
      if ($this->get_queryable() && !getDB()->count('SHOW INDEX FROM '.$this->table.' where Column_name=?',[$this->field])) {
	$desc = getDB()->fetchRow("show columns from {$this->table} where field=?",[$this->field]);
	if ($desc['Type'] == 'varchar(20)' || $desc['Type'] == 'varchar(40)'){
	  getDB()->execute('ALTER TABLE '.$this->table.' ADD INDEX '.$this->field.'('.$this->field.')');
	} elseif($desc['Type'] == 'text'){
	  getDB()->execute('ALTER TABLE '.$this->table.' ADD INDEX '.$this->field.'('.$this->field.'(40))');
	}
      }
      // verification que le champ n'inclue pas de ||
      $rs=getDB()->fetchAll('SELECT KOID,LANG,'.$this->field.' FROM '.$this->table.' WHERE '.$this->field.' like "%||%"');
      foreach($rs as $ors)  {
	$oid='';
	$value=$ors[$this->field];
	$values=explode('||',$value);
	foreach($values as $value){
	  if(!empty($value) && \Seolan\Core\Kernel::objectExists($value)){
	    $oid=$value;
	    break;
	  }
	}
	getDB()->execute('UPDATE '.$this->table.' SET UPD=UPD,'.$this->field.'=? WHERE KOID=? AND LANG=?',
			 array($oid, $ors['KOID'], $ors['LANG']));
      }
      if(!$this->get_queryable()){
	try{
	  getDb()->execute('DROP INDEX IF EXISTS '.$this->field.' ON '.$this->table);
	}catch(\Exception $err){
	  \Seolan\Core\Logs::notice(__METHOD__, $err->getMessage());
	}
      }
      // cas d'un 'ancien' champ multivalué (probable?)
      if ($this->normalized){
	$this->normalized = false;
	\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->table)->procEditField([
	  '_options'=>['local'=>true],
	  'tplentry'=>TZR_RETURN_DATA,
	  'field'=>$this->field,
	  'options'=>['normalized'=>0]
	]);
	Normalizer::dropComponents($this);
      }
    }
    if ($this->get_multivalued() && !empty($this->target) && $this->target != TZR_DEFAULT_TARGET && !\Seolan\Core\System::isView($this->table)) {
      if ($this->normalized){
	$componentsVersionOk = Normalizer::checkComponentsVersion($this);
	if ($componentsVersionOk && !Normalizer::checkComponents($this)){
	  \Seolan\Core\Logs::critical(__METHOD__," <<<<<<<<<<<<<<<<<<<<>>>>>>>>>>>>>>>>>");
	  \Seolan\Core\Logs::critical(__METHOD__," field {$this->table} {$this->field} normalized components error ({$GLOBALS['DATABASE_NAME']})");
	  \Seolan\Core\Logs::critical(__METHOD__," <<<<<<<<<<<<<<<<<<<<>>>>>>>>>>>>>>>>>");
	  $msg .= "field {$this->table} {$this->field} normalized with components error";
	} else {
	  // controle de version
	  if (!$componentsVersionOk){
	    $msg .= "field {$this->table} {$this->field} version sql components upgrade";
	    \Seolan\Core\Logs::notice(__METHOD__," upgrade /re-normalize field {$this->table} {$this->field}");
	    Normalizer::normalize($this);
	  } else {
	    \Seolan\Core\Logs::notice(__METHOD__," field {$this->table} {$this->field} $normalized ok");
	  }
	}
      } else {
	$targetNb = getDB()->fetchOne("select count(*) from {$this->target}");
	$srcNb = getDB()->fetchOne("select count(*) from {$this->table}");
	$limit = Normalizer::$targetLimit;
	if ($srcNb > $limit){
	  if (Normalizer::versionOK()){
	    try{
              if (!\Seolan\Core\Ini::get('nocheck_normalize')) {
                $msg .=  "\ncheck/normalize field '{$this->field}' in table '{$this->table}' ({$srcNb} {$targetNb})";
              }
	      \Seolan\Core\Logs::notice(__METHOD__," trying to normalize field {$this->table} {$this->field} {$srcNb} > {$limit}");
	      Normalizer::normalize($this);
	      $this->normalized = true;
	      \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->table)->procEditField([
												     '_options'=>['local'=>true],
												     'tplentry'=>TZR_RETURN_DATA,
												     'field'=>$this->field,
												     'options'=>['normalized'=>1]
												     ]);
	    }catch(\Exception $e){
	      \Seolan\Core\Logs::critical(__METHOD__," <<<<<<<<<<<<<<<<<<<<>>>>>>>>>>>>>>>>>");
	      \Seolan\Core\Logs::critical(__METHOD__," '{$this->table}', '{$this->field}' ({$GLOBALS['DATABASE_NAME']})".$e->getMessage());
	      \Seolan\Core\Logs::critical(__METHOD__," <<<<<<<<<<<<<<<<<<<<>>>>>>>>>>>>>>>>>");
	    }
	  } else {
	    \Seolan\Core\Logs::critical(__METHOD__, " normalize field {$this->table} {$this->field} {$srcNb} <= {$limit} should be fine but db upgrade required ({$GLOBALS['DATABASE_NAME']})");
	  }
	} else {
	  \Seolan\Core\Logs::debug(__METHOD__." normalize field {$this->table} {$this->field} {$srcNb} <= {$limit} not necessary (target nb : {$targetNb}) ({$GLOBALS['DATABASE_NAME']})");
	}
      }
    }
    return true;
  }

  // Edition du champ sous la forme d'une double liste déroulante
  function getDoubleSelect(&$value,&$options,&$r,&$rs,$fname,$hiddenname,$myliste){
    $mod=NULL;
    if($this->sourcemodule){
      $mod=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      if($mod->object_sec && !$mod->secure('',':list') || !$mod->object_sec && !$mod->secure('',':ro')) return;
    }
    $oidcollection=$collection=$opts=[];
    $varid=$r->varid=getUniqID('v'.$this->field);
    $unselectedname=preg_replace('/^([^\[]+)/','$1_unselected',$fname);
    $morder = $this->doubleboxorder==1 ? 'true' : 'false';
    $edit1='<select name="'.$unselectedname.'" size="'.$this->boxsize.'" multiple ondblclick="TZR.doubleAdd('.
      'this.form.elements[\''.$unselectedname.'\'],this.form.elements[\''.$fname.'[]\'],'.$morder.')" class="doublebox">';
    $edit2='<select name="'.$fname.'[]" size="'.$this->boxsize.'" multiple id="'.$varid.'"  ondblclick="TZR.doubleAdd(this.form.elements[\''.$fname.'[]\'],'.
      'this.form.elements[\''.$unselectedname.'\'],true)" class="doublebox">';
    $order = 0;
    if ($this->grouplist) { // la liste est groupée sur le premier champ du lien
      if (count($myliste) <= 1)
        $this->grouplist = false;
      else {
        $fields = array_keys($myliste);
        $groupField = $fields[0];
        $groupListe = [$groupField => array_shift($myliste)];
        $groupid = 0;
      }
    }
    $used_values=null;
    if(!empty($options['query_format']) && $options['query_format']==\Seolan\Core\Field\Field::QUICKQUERY_FORMAT && !\Seolan\Core\Shell::admini_mode()){
      $used_values=$this->_getUsedValues(null,null,$options);
    }
    while($ors=$rs->fetch()) {
      $koid=$ors['KOID'];
      if($used_values!==null && !$used_values[$koid]) continue;
      if(!empty($mod) && $mod->object_sec && !$mod->secure($koid,':ro')) continue;
      $order++;
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
    $buttons=
      '<button class="btn btn-default btn-md btn-inverse" type="button" onclick="TZR.doubleAdd(this.form.elements[\''.$unselectedname.'\'],'
      .'this.form.elements[\''.$fname.'[]\'],'.$morder.')">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','next').'</button><br>'
      .'<button class="btn btn-default btn-md btn-inverse" type="button" onclick="TZR.doubleAdd(this.form.elements[\''.$fname.'[]\'],'
      .'this.form.elements[\''.$unselectedname.'\'],true)">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','previous').'</button>';
    $hidd='<input type="hidden" name="'.$hiddenname.'" value="doublebox"/>';
    $color=\Seolan\Core\Ini::get('error_color');
    if($this->compulsory) $t1="TZR.addValidator(['$varid',/(.+)/,'".addslashes($this->label)."','$color','\Seolan\Field\Link\Link']);";
    else $t1="TZR.addValidator(['$varid','','".addslashes($this->label)."','$color','\Seolan\Field\Link\Link']);";
    $js="<script type=\"text/javascript\">if(typeof(TZR)!='undefined') { $t1 }</script>";
    $edit='<table class="doublebox width-auto"><tr><td>'.$edit1.'</td><td>'.$buttons.$hidd.'</td><td>'.$edit2.'</td></tr></table>'.$js;
    $r->oidcollection=$oidcollection;
    $r->collection=$collection;
    $r->html=$edit;
  }

  /// Edition du champ sous la forme de boite à cocher (checkbox/radio)
  function getCheckboxes(&$value,&$options,&$r,&$rs,$fname,$hiddenname,$myliste){
    $mod=null;
    if($this->sourcemodule){
      $mod=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      if($mod->object_sec && !$mod->secure('',':list') || !$mod->object_sec && !$mod->secure('',':ro')) return;
    }
    $qf=@$options['query_format'];
    if($this->multivalued || $qf) $typebox='checkbox';
    else $typebox='radio';
    $my_compulsory=($this->compulsory || @$options['compulsory']) && !$qf;
    $cols=0;
    $edit='<input type="hidden" name="'.$hiddenname.'" value="'.$typebox.'"/>';

    // Ajout du bouton pour inverser la sélection
    $nb=($rs?$rs->rowCount():0);
    if($typebox=='checkbox' && $nb > TZR_CHECKBOX_CHECKALL_LIMIT) {
      $onclickInvertsel = "jQuery(this).next().find('input').trigger('click'); return false;";
      $edit.='<button class="btn btn-default" onclick="'.$onclickInvertsel.'">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','select_unselect_all').'</button>';
    }

    $edit.='<table class="tzr-checkboxtable">';
    $edit.='<tr>';
    if($typebox!='radio' && !isset($qf))
      $edit.='<input type="hidden" name="'.$fname.'[0]" value="Foo"/>';
    if(!$my_compulsory && ($typebox=='radio')) {
      $varid=getUniqID('v');
      $edit.='<td><div class="'.$typebox.'"><label><input type="'.$typebox.'" name="'.$fname.'" value="" id="'.$varid.'"/>'.
	\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','empty_menu').'</label></div></td>';
      $cols++;
    }
    $listvarid=[];
    $opts=array('_published'=>'all');
    if ($this->grouplist) { // la liste est groupée sur le premier champ du lien
      if (count($myliste) <= 1)
        $this->grouplist = false;
      else {
        $fields = array_keys($myliste);
        $groupField = $fields[0];
        $groupListe = [$groupField => array_shift($myliste)];
      }
    }
    if($cols>=$this->checkbox_cols) {
      $edit.='</tr><tr>';
      $cols=0;
    }
    $nb = 0;
    $r->text='';
    $used_values=null;
    if($qf==\Seolan\Core\Field\Field::QUICKQUERY_FORMAT && !\Seolan\Core\Shell::admini_mode()){
      $used_values=$this->_getUsedValues(null,null,$options);
    }
    $oidcollection=[];
    $collection=[];
    while($rs && $ors=$rs->fetch()) {
      $koid=$ors['KOID'];
      if($used_values!==null && !$used_values[$koid]) continue;
      $nb++;
      if($mod && $mod->object_sec && !$mod->secure($koid,':ro')) continue;
      if ($this->grouplist && $currentGroup != $ors[$groupField]) {
        $currentGroup = $ors[$groupField];
        $edit .= '</td></tr><tr><td colspan="'.$this->checkbox_cols.'">';
        $edit .= $this->format_display($groupListe,$ors,$opts,null,'html');
        $edit .= '</td></tr><tr>';
        $cols=0;
      }
      $edit.='<td>';
      $koid=$ors['KOID'];
      $varid=uniqid('v');
      $listvarid[]=$varid;
      $display=$this->format_display($myliste,$ors,$opts);
      if($this->multivalued || $qf){
        $checked=isset($value[$koid]);
      }elseif($my_compulsory && empty($value) && $this->usedefault) {
        if (!empty($options['default']))
          $value=$options['default'];
        else
          $value=$koid;
        $checked=($koid==$value);
      }else{
        $checked=($koid==$value);
      }
      $class = '';
      if (@$this->error)
        $class .= ' class="error_field"';
      if($typebox=='checkbox')
	$edit.='<div class="checkbox"><label><input type="checkbox" '.$class.' id="'.$varid.'" name="'.$fname.'['.$koid.']" '.($checked?' checked ':'').
	  '/>'.$display.'</label></div></td>';
      else
	$edit.='<div class="radio"><label><input type="radio" '.$class.' id="'.$varid.'" name="'.$fname.'" value="'.$koid.'" '.
	  ($checked?' checked ':'').'/>'.$display.'</label></div></td>';
      $cols++;
      if($checked) $r->text.=$display;
      if($cols>=$this->checkbox_cols) {
	$edit.='</tr><tr>';
	$cols=0;
      }
      $oidcollection[]=$koid;
      $collection[]=$display;
    }
    $edit.='</tr></table>';
    if(!empty($my_compulsory) && !empty($listvarid)) {
      $color=\Seolan\Core\Ini::get('error_color');
      $edit.="<script type=\"text/javascript\">if(typeof(TZR)!='undefined') { TZR.addValidator(['".$listvarid[0]."','','".
	addslashes($this->label)."','$color','\Seolan\Field\Link\Link','',['".implode("','",$listvarid)."']]); }</script>";
    }
    $r->oidcollection=$oidcollection;
    $r->collection=$collection;
    $r->varid=$listvarid[0];
    $r->html=$edit;
  }
  // Edition par selection d'une vignette dans une pop up
  function getBrowseSourceModule($value,$options,$r,$rs,$fname,$hiddenname,$myliste){
    $mod=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
    if($mod->object_sec && !$mod->secure('',':list') || !$mod->object_sec && !$mod->secure('',':ro')){
      return;
    }

    $r->varid = uniqid();
    // valeur actuelle pour affichage
    $rd = $this->display($value, $options);

    $paramchoose = ['moid'=>$this->sourcemodule,
		    'function'=>'browse',
		    'template'=>'Core.linkedobjectselection.html',
		    'tplentry'=>'br',
		    'skip'=>1,
		    'tlink'=>'1'];

    $chooseico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit');
    $chooselabel = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','selection');
    $fieldlabel = escapeJavascript($this->label);
    $delico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete');
    if ($this->compulsory){
      $empty = '';
    } else {
      $unselectStyle = '';
      if (empty($rd->raw)){
	$unselectStyle = 'style="display:none"';
      }
      $empty = "<button type='button' class='btn btn-default btn-md btn-inverse' title=\"".\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','unselect')."\" onclick=\"TZR.xlinkdefCancelValue('{$r->varid}'); return false;\">{$delico}</button>";
    }
    // sélection des champs via display format
    $selectedfields = false;
    if(strpos($this->display_format,'%_')!==false){
      preg_match_all('/%_([a-z0-9_]+)/i',$this->display_format,$selectedfields);
      $selectedfields = $selectedfields[1];
    }
    if ($selectedfields){
      $paramchoose['selectedfields']=$selectedfields;
    }
    $urlchoose = $GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,true).http_build_query($paramchoose);
    // si multuvalué il faut construire les lignes par oid (comme dans le cas avec completions ?
    $r->html = <<<EOT
      <span class="" id="browsesourcelink{$r->varid}">
      <button
      type="button" class="btn btn-default btn-md btn-inverse"
      title="{$chooselabel}"
      onclick="TZR.xlinkdefSelectionPopup({field:'{$this->field}',table:'{$this->table}', moid:'{$mod->_moid}', multivalued:{$this->multivalued}, url:'{$urlchoose}', varid:'{$r->varid}', fieldlabel:'{$fieldlabel}'});return false;">{$chooseico}</button>
EOT;
    if ($this->multivalued){
      list($edit, $text) = $this->selectedList($fname, array_keys($value), $r, $myliste, $options);
      $r->html .= "<br>{$edit}";
    } else {
      $field = "<input id=\"{$r->varid}\" name=\"{$this->field}[]\" type=\"hidden\" value=\"{$value}\">";
      $r->html.="<span {$unselectStyle} class=\"cancel\">{$empty}</span>";
      $r->html.="<span class=\"title\">{$rd->text}</span>{$field}";
    }
    $r->html .= '</span>';
  }
  // Edition du champ sous la forme d'une zone d'autocomplétion
  function getAutocomplete(&$value,&$options,&$r,$rs,$fname,$hiddenname,$myliste){
    if(is_array($value)){
      $v=array_keys($value);
      $v=@$v[0];
    } else {
      $v=$value;
    }
    $qf=@$options['query_format'];
    $lang_data=\Seolan\Core\Shell::getLangData();
    if(isset($options['target_fields'])){
      $fieldslist=implode(',',$options['target_fields']);
      $pubonly=false;
    }else{
      $fieldslist='';
      $pubonly=true;
    }
    $r->varid=getUniqID('v');
    $textid='_INPUT'.$r->varid;
    $edit=$mborder=$js=$fmt='';
    if((!$qf && $this->compulsory) || ($qf === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options))) {
      $mborder='tzr-input-compulsory';
      if(!$this->multivalued) $fmt=' onblur="TZR.isIdValid(\''.$r->varid.'\')" ';
      $js='<script type="text/javascript">'.
	  'TZR.addValidator(["'.$r->varid.'",/.+:.+/,"'.addslashes($this->label).'","'.\Seolan\Core\Ini::get('error_color').'","\Seolan\Field\Link\Link","'.$textid.'"]);</script>';
    }
    if($this->multivalued){
      if(is_array($value))
	$v=array_keys($value);
      $edit.='<input id="'.$r->varid.'" autocomplete="off" name="foo" value="" type="hidden">';
      $edit.='<input autocomplete="off" id="'.$textid.'" name="_INPUT'.$fname.'" size="30" type="text" '.$fmt.' class="tzr-link tzr-autocomplete '.$mborder.'">';
      $edit.=$this->addInsertLink($r->varid, $qf, $options);
      if ($qf && \Seolan\Core\Shell::admini_mode()){
	$op=$options['op'];
	$querypart='<div class="radio"><label><input type="radio" name="'.$fname.'_op" value="AND" id="'.$r->varid.'-AND" checked>'.
		   \Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','xlinkdef_allterms').'</label></div><br>'.
					'<div class="radio"><label><input type="radio" name="'.$fname.'_op" value="OR" id="'.$r->varid.'-OR"'.($op=='OR'?' checked':'').'>'.
					\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','xlinkdef_leastaterm').'</label></div>';
	if( $options['genempty'] !== false ){
	  $querypart .= '&nbsp;<div class="radio"><label><input type="radio" name="'.$fname.'_op" value="is empty" id="'.$r->varid.'-EMPTY"'.($op=='is empty'?' checked':'').'>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','is_empty').'</label></div>';
	}
      }else{
	$querypart='';
      }
      list($editlist, $textlist) = $this->selectedList($fname, $v, $r, $myliste, $options);
      $edit.=$editlist;
      $r->text = $textlist;
      if ($querypart != '')
        $edit = $querypart.'<br>'.$edit;
    }else{
      $display='';
      if($v){
	$target=\Seolan\Core\Kernel::getTable($v);
	if(\Seolan\Core\System::tableExists($target)) {
	  $t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$target);
	  if($t->isTranslatable())
	    $rs2=getDB()->select('select * from '.$target.' where KOID=? AND LANG=?', array($v, $lang_data));
	  else
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
      $edit.='<input id="'.$r->varid.'" autocomplete="off" name="'.$fname.'" value="'.$v.'" type="hidden">';
      $edit.='<input autocomplete="off" id="'.$textid.'" name="_INPUT'.$fname.'" size="30" type="text" value="'.$display.'" '.$fmt.
	' class="tzr-link '.$mborder.'">';
      $edit.=$this->addInsertLink($r->varid, $qf, $options);
      if( $qf && \Seolan\Core\Shell::admini_mode() && $options['genempty'] !== false ){
	$querypart .= '<div class="checkbox"><label><input type="checkbox" name="'.$fname.'_op" value="is empty" id="'.$textid.'-EMPTY"'.($options['op']=='is empty'?' checked':'').'>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','is_empty').'</label></div>';
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
      $url=TZR_AJAX8.'?class='.urlencode(get_class($this)).'&function=xlinkdef_autocomplete&_silent=1&oid='.$options['oid'].'&query_format='.$qf.($this->display_format?'&options[display_format]='.urlencode($this->display_format):'');
    else
      $url=TZR_AJAX8.'?class='.urlencode(get_class($this)).'&function=xlinkdef_autocomplete&_silent=1&query_format='.$qf.($this->display_format?'&options[display_format]='.urlencode($this->display_format):'');

    $edit.='<script type="text/javascript" language="javascript">jQuery("#'.$textid.'").data("autocomplete", {url:"'.$url.'", params:{moid:"'.$options['fmoid'].'", table:"'.$this->table.'", field:"'.$this->field.'",fieldslist:"'.$fieldslist.'", id:"'.$r->varid.'"}'.($this->multivalued?',callback:TZR.autoCompleteMultipleValue' : '').',minlength:'.$this->autocomplete_minlength.'});TZR.addAutoComplete("'.$r->varid.'");</script>'.$js;
    $r->oidcollection=$r->collection=[];
    $r->html=$edit;
  }
  /**
   * liste des objects actuellement séléctionnés
   */
  protected function selectedList($fname, $oidcollection, $r, $fieldList, $options){
    $lang_data=\Seolan\Core\Shell::getLangData();
    $delico='<button class="btn btn-default btn-md btn-inverse" type="button">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete').'</button>';
    $edit='<table class="linkedobjectslist" id="table'.$r->varid.'"><tr style="display:none;"><td><a href="#" onclick="TZR.delLine(this);return false;">'.$delico.'</a><input type="hidden" name="'.$fname.'[]" value=""></td><td></td></tr>';
    $text = '';
    if(!empty($oidcollection)){
      if (!is_array($oidcollection))
	$oidcollection = [$oidcollection];
      foreach($oidcollection as $oid){
	// target et table ? à sortir de la boucle, ce sont toujours les mêmes ?
	$target=\Seolan\Core\Kernel::getTable($oid);
	if(\Seolan\Core\System::tableExists($target)) {
	  $t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$target);
	  if($t->isTranslatable())
	    $rs2=getDB()->select('select * from '.$target.' where KOID=? AND LANG = ?', array($oid,$lang_data));
	  else
	    $rs2=getDB()->select('select * from '.$target.' where KOID= ?', array($oid));
	  $ors2=$rs2->fetch();
	  if($ors2) {
	    $myopts=[];
	    if(!empty($options['_charset']))
	      $myopts['_charset']=$options['_charset'];
	    $display=$this->format_display($fieldList,$ors2,$myopts,NULL,'text');
	  }
	}
	$display=trim($display);
	$text.=$display.' ';
	$edit.='<tr><td><a href="#" onclick="TZR.delLine(this);return false;">'.$delico.'</a><input type="hidden" name="'.$fname.'[]" value="'.$oid.'"></td><td>'.$display.'</td></tr>';
      }
      $text=substr($text,0,-1);
    }
    $edit.='</table>';
    return [$edit, $text];
  }
  // Edition du champ sous la forme d'une liste rempli/non rempli
  function getFilled(&$value,&$options,&$r,$rs,$fname,$hiddenname,$myliste) {
    $r->html ='<input type="hidden" name="'.$hiddenname.'" value="filled"/>';
    $r->html.='<select name="'.$fname.'" id="'.$fname.'"><option value="">----</option>';
    $r->html.='<option value="is empty"'.(array_key_exists('is empty', $value)?' selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','is_empty').'</option>';
    $r->html.='<option value="is not empty"'.(array_key_exists('is not empty', $value)?' selected':'').'>'.$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_Field_Field','is_not_empty').'</option>';
    $r->html.='</select>';
  }
  // Edition du champ sous la forme d'une liste déroulante
  function getSelect(&$value,&$options,&$r,&$rs,$fname,$hiddenname,$myliste) {
    $varid='';
    if($this->sourcemodule){
      $mod=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      if($mod->object_sec && !$mod->secure('',':list') || !$mod->object_sec && !$mod->secure('',':ro')) return;
    }
    $qf=@$options['query_format'];
    $oidcollection=$collection=array();
    $edit=$my_previousOid='';
    $opts=array('_published'=>'all');
    $i=0;

    if ($this->grouplist) { // la liste est groupée sur le premier champ du lien
      if (count($myliste) <= 1)
        $this->grouplist = false;
      else {
        $fields = array_keys($myliste);
        $groupField = $fields[0];
        $groupListe = [$groupField => array_shift($myliste)];
      }
    }
    $used_values=null;
    if($qf==\Seolan\Core\Field\Field::QUICKQUERY_FORMAT && !\Seolan\Core\Shell::admini_mode()){
      $used_values=$this->_getUsedValues(null,null,$options);
    }
    if (!$value && !empty($options['default']))
      $value=$options['default'];
    while($rs && $ors=$rs->fetch()) {
      $i++;
      $koid=$ors['KOID'];
      if($used_values!==null && !$used_values[$koid]) continue;
      if(!empty($mod) && $mod->object_sec && !$mod->secure($koid,':ro')) continue;
      if((is_array($value) && isset($value[$koid])) || ($koid==$value)) $selected=' selected';
      else $selected='';
      if ($this->grouplist && $currentGroup != $ors[$groupField]) {
        $currentGroup = $ors[$groupField];
        $groupLabel = $this->format_display($groupListe,$ors,$opts,null,'text');
        $edit .= '<optgroup label="'.$groupLabel.'">';
	$i++;
      }
      $display=$this->format_display($myliste,$ors,$opts,null,'text');
      $edit.='<option value="'.$koid.'"'.$selected.'>'.$display.'</option>';
      if($selected) $r->text.=$display;
      $oidcollection[]=$koid;
      $collection[]=$display;
      if(!empty($selected) && empty($first)) $first='<option value="'.$koid.'">'.$this->label.' : '.$display.'</option>';
    }
    $labelin=@$options['labelin'];
    if($qf){
      $format=@$options['fmt'];
      if(empty($format)) $format=@$options['qfmt'];
      if(empty($format)) $format=$this->query_format;
      if(empty($labelin)) $first='<option value="">----</option>';
      elseif(empty($first) || $format=='listbox') $first='<option value="">'.$this->label.'</option>';
      else{
	$first.='<option value="">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','n/a').'</option>';
	$edit=str_replace('" selected>','">',$edit);
      }
      if (($qf === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options))){
        $first = '';
      }
      $i++;
      if($i<2 || $format=='listbox-one') $edit='<select '.($qf === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' name="'.$fname.'" id="'.$fname.'">'.$first.$edit.'</select>';
      else $edit='<select '.($qf === \Seolan\Core\Field\Field::QUICKQUERY_FORMAT && $this->isFilterCompulsory($options) ? 'required' : '').' name="'.$fname.'[]" id="'.$fname.'" size="'.min($i,$this->boxsize).'" multiple>'.$first.$edit.'</select>';
    }else{
      if(!empty($labelin)) {
        $edit='<option value="">'.$this->label.'</option>'.$edit;
        $i++;
      } elseif(!$this->compulsory || !$this->usedefault) {
        $edit='<option value="">----</option>'.$edit;
        $i++;
      }
      $varid=getUniqID('v');
      if ($this->multivalued) $cplt='name="'.$fname.'[]" size="'.min($i,$this->boxsize).'" multiple';
      else $cplt='name="'.$fname.'"';

      $class = '';
      if ($this->compulsory) {
	$class = "tzr-input-compulsory";
      }
      if (@$this->error)
	$class .= " error_field";
      if ($class)
	$class = " class=\"$class\"";
      $edit='<select '.$cplt.' '.$class.' id="'.$varid.'" onblur="TZR.isIdValid(\''.$varid.'\');">'.$edit.'</select>';
      if ($this->compulsory) {
	$edit.='<script>TZR.addValidator(["'.$varid.'","compselect","'.addslashes($this->label).'","'.\Seolan\Core\Ini::get('error_color').'",'.
	  '"\Seolan\Field\Link\Link"]);</script>';
      }
    }
    $edit.=$this->addInsertLink($varid, $qf, $options);
    $r->oidcollection=$oidcollection;
    $r->collection=$collection;
    $r->varid=$varid;
    $r->html=$edit;
  }

  private function addInsertLink($varid, $qf, $options) {
    if (\Seolan\Core\Shell::admini_mode() && !$qf && ($this->sourcemodule && $this->isAutorizedToAdd($options))){
      $urlnew = $GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,true).'&moid='.$this->sourcemodule.'&function=insert&template=Module/Table.popinsert.html&tplentry=br&tabsmode=2&varid='.$varid.'&field='.$this->field;
      $newico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','new');
      return '<a type="button" class="btn btn-default btn-md btn-inverse" data-toggledialog="dialog", data-options=\'{"initCallback":{"_function":"TZR.Record.newRecordInit", "_param":"'.$varid.'"}}\' data-url="'.$urlnew.'">'.$newico.'</a>';
    }
    return '';
  }

  function getFieldList($options,$addpublished=true){
    $maxmi=$this->getNbPublishedFields();
    $order='FORDER';
    $sup='';
    $idx='';
    $display_format = null;
    if(strpos($this->display_format,'%_')!==false){
      $display_format = $this->display_format;
    } elseif (strpos($this->display_text_format,'%_')!==false){
      $display_format = $this->display_text_format;
    }
    if(null !== $display_format){
      preg_match_all('/%_([a-z0-9_]+)/i', $display_format, $fmtfields);
      $fmtfields=$fmtfields[1];
      $fmtfields=array_reverse($fmtfields);
      $sql=implode('","',$fmtfields);
      $order='FIELD(FIELD,"'.$sql.'") desc';
      $sup=' or FIELD in("'.$sql.'")';
      $maxmi=99;
    }
    $idx='';
    if(!$addpublished && !empty($sup)){
      $rs=getDB()->fetchAll('select * from DICT where '.substr($sup,3).' and DTAB=? ORDER BY '.$order, array($this->target));
    }elseif(!isset($options['target_fields'])){
      $rs=getDB()->fetchAll('select * from DICT where (PUBLISHED=1'.$sup.') and DTAB=? ORDER BY '.$order, array($this->target));
    }else{
      $idx=implode('","',$options['target_fields']);
      if(isset($this->fieldlist[$idx]))	return $this->fieldlist[$idx];
      $fieldlist='"'.implode('","',$options['target_fields']).'"';
      $rs=getDB()->fetchAll('select * from DICT where (FIELD in ('.$fieldlist.')'.$sup.') and DTAB=? ORDER BY '.$order, array($this->target));
      if(count($rs)==0) $rs=getDB()->fetchAll('select * from DICT where (PUBLISHED=1'.$sup.') and DTAB=? ORDER BY '.$order,
					       array($this->target));
      $maxmi=99;
      }
    $first='';
    $fields=$this->target.'.KOID';
    $mi=0;
    foreach($rs as $ors) {
      if(empty($first)) $first=$ors['FIELD'];
      elseif($this->grouplist && !strpos($first, ',')) $first.=','.$ors['FIELD'];
      $oo=(object)$ors;
      if($mi<$maxmi) {
	$fielddefs[$ors['FIELD']]=\Seolan\Core\Field\Field::objectFactory($oo);
	$fields.=','.$fielddefs[$ors['FIELD']]->get_sqlSelectExpr();
	$mi++;
      }
    }
    unset($rs);
    $this->fieldlist[$idx]=array($fielddefs, $fields, $first);
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

  /**
   * Modifie les propriétés de l'objet de façon à cibler le module passé en paramètre
   * @param $target_module_moid int : identifiant du module à cibler
   */
  protected function changeTargetWithModuleMoid($target_module_moid) {
    $xmodinfotree_params = \Seolan\Core\Module\Module::findParam($target_module_moid);
    $this->target = $xmodinfotree_params['MPARAM']['table'];
    $this->sourcemodule = $target_module_moid;
  }

  /**
   * Retourne la valeur uniformisée du champ au format brut (RAW)
   * @param string/array $value Valeur du champ: "TABLE:oid" ou "||TABLE:oid1||[TABLE:oid2||..."
   * @return string Valeur du champ au format brut (RAW)
   */
  public function sanitizeRawFormat($value) {
    if (is_string($value))
      $value = array_filter(explode('||', $value));
    if (is_array($value)) {
      if (count($value) == 0)
        return '';
      if (count($value) == 1)
        return array_shift($value);
      return '||'.implode('||', $value).'||';
    }
    return $value;
  }
  public function getDocumentationData(){
    $r = parent::getDocumentationData();
    if (!empty($this->target) && $this->target != TZR_DEFAULT_TARGET){
      $r->type .= ', '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','target').' : ';
      if (!empty($this->sourcemodule)){
	$mod = \Seolan\Core\Module\Module::ObjectFactory(['moid'=>$this->sourcemodule,'interactive'=>false,'tplentry'=>TZR_RETURN_DATA]);
	$r->type .= "\"{$mod->getLabel()}\"";
      } else {
	$ds = \Seolan\Core\DataSource\DataSource::objectfactoryhelper8('SPECS='.$this->target);
	$r->type .= "\"{$ds->getLabel()}\"";
      }
    }
    return $r;
  }
  /// delfield : clear associated resources
  function delfield(){
    // may be conditionned to adhoc prop.
    \Seolan\Field\Link\Normalizer::dropComponents($this);
    return true;
  }

  /// paramètres commun aux fonctions type autocompletion
  static function parseRequest(){

  }
  static function checkModuleAndTable($moid, $table, $checktable=true){
    // Vérifie que l'on peut utiliser l'autocomplete depuis le module (droit list/ro sur le module, table utilisée par le module)
    $mod = \Seolan\Core\Module\Module::objectFactory($moid);
    if ($mod->object_sec || is_a($mod, '\Seolan\Module\Media\Media'))
      $ok = $mod->secure('', ':list');
    else
      $ok = $mod->secure('', ':ro');
    if (!$ok) {
      throw new \Exception(__METHOD__." moid '{$moid}' '{$table}' invalid acl");

    }
    if ($checktable){
      if (empty($table))
	$table = $mod->table;
      if (!$mod->usesTable($table) && $mod->xset->getInputTable()!=$table) {
	throw new \Exception("'{$moid}' '{$table}' invalid table");
      }
    }
    return $mod;
  }
}
function xlinkdef_autocomplete($php=false, $ofield=null, $checked=false) {
  activeSec();
  $moid = $_REQUEST['moid'];    // Module depuis lequel on fait l'autocomplete
  $table = $_REQUEST['table'];  // Table contenant le champ
  $field = $_REQUEST['field'];
  $oid = @$_REQUEST['oid'];
  $query_format = @$_REQUEST['query_format'];
  $q = $_REQUEST['q'];
  $options = $_REQUEST['options'];
  if (!empty($_REQUEST['target_fields']) && !is_array($_REQUEST['target_fields']))
    $target_fields = explode(',', $_REQUEST['target_fields']);
  else
    $target_fields = NULL;
  if (empty($moid) || empty($q))
    return null;
  if (!$php || !$checked){ // charge à l'appelant de vérifier les droits
    try{
      $mod = \Seolan\Field\Link\Link::checkModuleAndTable($moid, $table);
    }catch(\Throwable $e){
      if (!$php)
	header("HTTP/1.1 500 Seolan Server Error");
      \Seolan\Core\Logs::critical(__FUNCTION__, $e->__toString()."\n".var_export($_REQUEST,true));
      return null;
    }
  }
  // Recupere les valeurs
  if ($php && $ofield !== null){
    // on prend le champ tel quel
  } else {
    $xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);
    $ofield = $xset->getField($field);
    if (!$ofield) { // cas option type 'object'
      $class = $_REQUEST['class'] ?? '\Seolan\Field\Link\Link';
      $ofield = new $class((object) [
	'TARGET' => $table
      ]);
    }
    if ($options['display_format'])
      $ofield->display_format = $options['display_format'];
  }
  // Si le champ utilise un module particulier, on vérifie les droits
  $modtarget = NULL;
  if ($ofield->sourcemodule) {
    try{
      $modtarget = \Seolan\Field\Link\Link::checkModuleAndTable($ofield->sourcemodule, $table, false);
    }catch(Throwable $e){
      if (!$php)
	header("HTTP/1.1 500 Seolan Server Error");
      \Seolan\Core\Logs::critical(__FUNCTION__, $e->getMessage());
      return null;
    }
  }

  $q = trim($q);
  if (empty($target_fields))
    $target_fields = NULL;
  $ret = $ofield->getValues($q, ['_charset'=>'utf-8',
				 'target_fields'=>$target_fields,
				 'max'=>XLINKDEF_MAXPOPUPLINKS,
				 'mod'=>$modtarget,
				 'query_format'=>$query_format,
				 'fmoid'=>$moid,
				 'oid'=>$oid,
				 'lang'=>\Seolan\Core\Shell::getLangData(null, true)]);

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
    $data[] = array('value' => '', 'label' => \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'too_many_results'));
  die(json_encode($data));
}

function xlinkdef_display($oid, $options = null) {
  return \Seolan\Core\Field\Field::objectFactory((object)array('FTYPE'=>'\Seolan\Field\Link\Link','TARGET'=>TZR_DEFAULT_TARGET))->display($oid, $options);
}
function xlinkdef_display_html($oid, $options = null) {
  return \Seolan\Field\Link\xlinkdef_display($oid, $options)->html;
}
/**
 * chargement de l'arbo partielle ou complete
 */
function xlinkdef_treeview($php=false){
  activeSec();
  $moid = $_REQUEST['moid']??null;    // Module depuis lequel on fait l'autocomplete
  $table = $_REQUEST['table']??null;  // Table contenant le champ
  $field = $_REQUEST['field']??null;
  $path = $_REQUEST['path']??null;
  $dataType = $_REQUEST['dataType']??'html';
  $ffm = $_REQUEST['ffm']??null; // lire le champ dans le module
  if (empty($moid)){
    \Seolan\Core\Logs::critical(__FUNCTION__,"empty moid");
    if (!$php)
      header("HTTP/1.1 500 Seolan Server Error");
    return null;
  }
  $mod=null;
  \Seolan\Core\Logs::debug(__METHOD__." ".var_export($_REQUEST, true));
  try{
    if (empty($ffm))
      $mod = \Seolan\Field\Link\Link::checkModuleAndTable($moid, $table);
    else
      $mod = \Seolan\Field\Link\Link::checkModuleAndTable($moid, null, false);
  }catch(\Throwable $e){
    if (!$php)
      header("HTTP/1.1 500 Seolan Server Error");
    \Seolan\Core\Logs::critical(__FUNCTION__, $e->getMessage());
    return null;
  }
  if (empty($ffm)){
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);
    $ofield = $ds->getField($field);
    if (empty($ofield) || $ofield->treeview == false){
      if (!$php)
	header("HTTP/1.1 500 Seolan Server Error");
      \Seolan\Core\Logs::critical(__FUNCTION__, "Invalid configuration $moid $table $field");
      return null;
    }
  } else {
    $mod = \Seolan\Core\Module\Module::objectFactory(['moid'=>$ffm['moid'],
                                                     'interactive'=>false,
                                                      'tplentry'=>TZR_RETURN_DATA]);
    $m = $ffm['f'];
    $ofield = $mod->$m($moid, $ffm['o']);
  }
  $modtarget = null;
  if ($ofield->sourcemodule) {
    try{
      if (empty($ofield->target) || $ofield->target == TZR_DEFAULT_TARGET)
	throw new \Exception("{$field} {$table} default target configured");

      $modtarget = \Seolan\Field\Link\Link::checkModuleAndTable($ofield->sourcemodule, $ofield->target);

    }catch(Throwable $e){
      if (!$php)
	header("HTTP/1.1 500 Seolan Server Error");
      \Seolan\Core\Logs::critical(__FUNCTION__, $e->getMessage());
      return null;
    }
  }
  $result = $ofield->treeviewLevel($path);
  if ($php){
    return $result;
  } else if ($dataType == 'simpletree'){
    header('Content-Type:text/html; charset=UTF-8');
    die($ofield->formatSimpleTreeLines($result, ['fmoid'=>$moid,'ffm'=>$ffm??null]));
  } else {
    header('Content-Type:application/json; charset=UTF-8');
    die(json_encode($result));
  }
}
