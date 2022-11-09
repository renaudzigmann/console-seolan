<?php
/**
 Cette classe decrit l'interface de toutes les classes de
 manipulation des donnees atomiques. Elle inclut l'affichage,
 l'edition, la mise a jour d'un champ. Chaque type de donnees est
 manipule par une classe specifiques.
*/

namespace Seolan\Core\Field;

abstract class Field  {
  const QUERY_FORMAT='query';
  const QUICKQUERY_FORMAT='quick';

  // Liste des classes de champ
  // Type de donnee, fcount personnalisable, utilise le target, absent de toutes listes, absent en mode simplifié
  static public $_fields = array(
    '\Seolan\Field\Chrono\Chrono'                              => array('Data', false, false, false, true),
    '\Seolan\Field\Chrono2\Chrono2'                            => array('Data', true,  false, false, true),
    '\Seolan\Field\Boolean\Boolean'                            => array('Oid',  false, false, false, false),
    '\Seolan\Field\Color\Color'                                => array('Data', false, false, false, false),
    '\Seolan\Field\Date\Date'                                  => array('Data', false, false, false, false),
    '\Seolan\Field\Time\Time'                                  => array('Data', false, false, false, true),
    '\Seolan\Field\Url\Url'                                    => array('Data', false, false, false, false),
    '\Seolan\Field\Link\Link'                                  => array('Oid',  false, true,  false, true),
    '\Seolan\Field\ApplicationLink\ApplicationLink'            => array('Oid',  false, true,  false, true),
    '\Seolan\Field\User\User'                                  => array('Oid',  false, true,  false, true),
    '\Seolan\Field\Thesaurus\Thesaurus'                        => array('Oid',  false, true,  false, true),
    '\Seolan\Field\Password\Password'                          => array('Data', true,  false, false, true),
    '\Seolan\Field\StringSet\StringSet'                        => array('Oid',  false, false, false, false),
    '\Seolan\Field\File\File'                                  => array('Data', false, false, false, false),
    '\Seolan\Field\File\ConfidentialData'                      => array('Data', false, false, false, false),
    '\Seolan\Field\Document\Document'                          => array('Oid',  false, false, false, true),
    '\Seolan\Field\ExternalImage\ExternalImage'                => array('Data', false, false, false, true),
    '\Seolan\Field\Image\Image'                                => array('Data', false, false, false, false),
    '\Seolan\Field\DateTime\DateTime'                          => array('Data', false, false, false, true),
    '\Seolan\Field\Timestamp\Timestamp'                        => array('Data', false, false, false, true),
    '\Seolan\Field\Order\Order'                                => array('Data', false, false, false, false),
    '\Seolan\Field\Real\Real'                                  => array('Data', true,  true,  false, false),
    '\Seolan\Field\Text\Text'                                  => array('Data', true,  false, false, false),
    '\Seolan\Field\Expression\Expression'                      => array('Data', true,  false, false, false),
    '\Seolan\Field\ShortText\ShortText'                        => array('Data', true,  false, false, false),
    '\Seolan\Field\RichText\RichText'                          => array('Data', true,  false, false, false),
    '\Seolan\Field\DataSource\DataSource'                      => array('Data', true,  false, false, true),
    '\Seolan\Field\Table\Table'                                => array('Data', true,  false, false, true),
    '\Seolan\Field\Query\Query'                                => array('Data', false, false, false, true),
    '\Seolan\Field\Module\Module'                              => array('Oid',  false, false, false, true),
    '\Seolan\Field\GeodesicCoordinates\GeodesicCoordinates'    => array('Data', false, false, false, false),
    '\Seolan\Field\DataSourceField\DataSourceField'            => array('Oid',  false, true,  false, true),
    '\Seolan\Field\Rating\Rating'                              => array('Data', false, false, false, true),
    '\Seolan\Field\Options\Options'                            => array('Data', false, false, false, true),
    '\Seolan\Field\DependentLink\DependentLink'                => array('Oid',  false, true,  false, true),
    '\Seolan\Field\GmapPoint\GmapPoint'                        => array('Data', false, false, false, false),
    '\Seolan\Field\GmapPoint2\GmapPoint2'                      => array('Data', false, false, false, false),
    '\Seolan\Field\Serialize\Serialize'                        => array('Data', true,  false, false, false),
    '\Seolan\Field\Country\Country'                            => array('Oid',  false, true,  false, true),
    '\Seolan\Field\Interval\Interval'                          => array('Data', false, false, false, false),
    '\Seolan\Field\Tag\Tag'                                    => array('Data', true,  false, false, false),
    '\Seolan\Field\Lang\Lang'                                  => array('Data', true, false, false, false),
    '\Seolan\Field\Icon\Icon'                                  => array('Data', true,  false, false, false),
    '\Seolan\Field\Label\Label'                                => array('Oid', true, true, false, false),
    '\Seolan\Field\Phone\Phone'                                => array('Data', true,  false, false, false),
    '\Seolan\Field\MarkdownText\MarkdownText'                  => array('Data', true,  false, false, false),
    '\Seolan\Field\Video\Video'                                => array('Data', false,  false, false, false),
  );

  public $exif_source='';
  /** @var \Seolan\Core\Options $_options */
  public $_options=NULL;
  public $readonly=false;
  public $hidden=false;
  public $aliasmodule=NULL;
  public $query_format='classic';
  public $default='';
  public $indexable = false;
  public $onlyqueryable=false;
  public $advanceeditbatch=false;
  public $edit_format=NULL;
  public $label=NULL;
  public $theclass = NULL;
  public $multiseparator='<br/>';
  public $multiseparatortext=', ';
  public $add_browse_class = false;
  public $RGPD_personalData = false;
  public $browse_format='full';
  public $initFieldIfDuplicate=false;

  private $_browseCssClass = null;

  static function isValidType($str){
    return array_key_exists($str,self::$_fields);
  }

  // Recupere une valeur dans le cache
  public function getCache($cache_name,$key){
    return \Seolan\Library\ProcessCache::get('xfielddef/'.
			  ($this->table?$this->table:'table'.spl_object_hash($this)).'/'.
			  ($this->field?$this->field:'field'.spl_object_hash($this)).'/'.
			  $cache_name,$key);
  }
  // Ajoute une valeur dans le cache
  public function setCache($cache_name,$key,&$value){
    return \Seolan\Library\ProcessCache::set('xfielddef/'.
			  ($this->table?$this->table:'table'.spl_object_hash($this)).'/'.
			  ($this->field?$this->field:'field'.spl_object_hash($this)).'/'.
			  $cache_name,$key,$value);
  }

  public static function objectFactory($obj, $theclass=null) {
    $obj->DPARAM=\Seolan\Core\Options::decode($obj->DPARAM ?? []);
    if ($theclass != null){
      $obj->DPARAM['theclass'] = $theclass;
    }
    $classname = \Seolan\Core\Field\Field::_getClass($obj->FTYPE,@$obj->DPARAM['theclass']);
    if($classname) $c1=new $classname($obj);
    else \Seolan\Core\Logs::critical("\Seolan\Core\Field\Field::objectFactory ",$classname.'-'.$obj->FTYPE.'-'.@$obj->DPARAM['theclass']." unknown");
    return $c1;
  }
  public static function objectFactory2($table, $field, $classname=null) {
    if ($classname != null && !is_subclass_of($classname, \Seolan\Core\Field\Field::class)){
      throw new \Seolan\Core\Exception\Exception(__METHOD__." $classname not subclass of \Seolan\Core\Field\Field");
    }
    $lang = \Seolan\Core\Shell::getLangUser();
    $o=getDB()->select('select * from DICT,MSGS where DTAB = ? and MTAB=DTAB '.
		       ' and (MLANG= ? or MLANG= ? ) and DICT.FIELD=MSGS.FIELD and DICT.FIELD= ? order by FORDER',
		       [$table,$lang,TZR_DEFAULT_LANG,$field])->fetch(\PDO::FETCH_OBJ);
    if ($o) $field = \Seolan\Core\Field\Field::objectFactory($o,$classname);
    return $field;
  }
  /// creation d'un champ en memoire a partir de son type, son nom et sa taille
  public static function objectFactory3($type, $name, $count) {
    $obj['FTYPE']=$type;
    $obj['FCOUNT']=$count;
    $obj['FIELD']=$name;
    return \Seolan\Core\Field\Field::objectFactory((object)$obj);
  }
  public function trace($old, $r, $msg='') {
    if(empty($msg) && is_object($r) && is_object($old)){
      if($r->raw!=$old->raw)
	$msg="[".
	  (is_array($old->raw)?implode(',',$old->raw??[]):($old->raw??""))."] -> [".
	  (is_array($r->raw)?implode(',',$r->raw??[]):($r->raw??""))."]";
    }
    if($msg){
      if(isset($r->trace[$this->field])) $r->trace[$this->field].="<br>".$msg;
      else $r->trace[$this->field]=$msg;
    }
  }
  public function chk(&$message) {
  }
  public function repair(&$message){
  }
  public static function typeExists($t) {
    return is_subclass_of($t,'\Seolan\Core\Field\Field');
  }
  public static function getGender($t) {
    if(!class_exists($t)) return false;
    return \Seolan\Core\Field\Field::$_fields[$t][0];
  }
  public static function getUseTarget($t) {
    if(!class_exists($t)) return false;
    return \Seolan\Core\Field\Field::$_fields[$t][2];
  }
  public static function getFCount($t) {
    if(!class_exists($t)) return false;
    return \Seolan\Core\Field\Field::$_fields[$t][1];
  }
  public static function _getClass($type,$classname=NULL) {
    if(!empty($classname) && class_exists($classname)) return $classname;
    if(class_exists($type)) return $type;
    return false;
  }
  public static function getTypes() {
    $ty=array();
    $la=array();
    foreach(\Seolan\Core\Field\Field::$_fields as $t => $typeprops) {
      if(!$typeprops[3]) { // absent des listes
	$ty[]=$t;
	$la[]=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field',$t);
      }
    }
    array_multisort($la, $ty);
    return array($ty, $la);
  }

  /// rend une chaine de caractère en clair donnant le type du champ
  public function getTypeString() {
    $class_name = get_class($this);
    return \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field',"\\$class_name");
  }
  /// rend des détails associés au type du champ
  public function getTypeStringAnnotation() {
    return "";
  }
  


  /**
   * Renvoie les types de champs "lien vers un objet"
   * @return Array Liste des types de champs "lien vers un objet"
   */
  public static function getLinkTypes() {
    $link_types = array();
    foreach(\Seolan\Core\Field\Field::$_fields as $type => $params) {
      if(self::typeIsLink($type)) {
        $link_types[] = $type;
      }
    }
    return $link_types;
  }

  /**
   * Teste si le type du champ correspond à un "lien vers un objet"
   * @param string $type Type de champ à renseigner dans un contexte static
   * @return boolean Vrai si le type correspond à un "lien vers un objet"
   */
  function isLink() {
    return self::typeIsLink($this->ftype);
  }
  static function typeIsLink($type = null) {
    return self::getGender($type) == 'Oid' && self::getUseTarget($type);
  }

  function getLink() {
    if ($this->sourcemodule) {
      $link = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'function=adminBrowseFields&template=Core/Module.admin/browseFields.html&moid='.$this->sourcemodule;
      $mod=\Seolan\Core\Module\Module::objectFactory($this->sourcemodule);
      $label = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','linktomodule').' ';
      return "$label<a class=\"cv8-ajaxlink\" href=\"$link\">{$mod->getLabel()}</a>";
    } else if (\Seolan\Core\DataSource\DataSource::sourceExists($this->target)) {
      $moid = \Seolan\Core\Module\Module::getMoid(XMODDATASOURCE_TOID);
      $boid = \Seolan\Core\DataSource\DataSource::getBoidFromSpecs('\Seolan\Model\DataSource\Table\Table', $this->target);
      $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
      $link = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'function=XDSBrowseFields&template=Module/DataSource.browseFields.html&moid='.$moid.'&boid='.$boid;
      $label = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','linktotable').' ';
      return "$label<a class=\"cv8-ajaxlink\" href=\"$link\">{$xds->getSourceName()}</a>";
    }
    return $this->target;
  }

  function __construct($obj=NULL) {
    if(empty($obj)) {
      $obj=(object)array('FIELD'=>'anonymous','FTYPE'=>get_class($this),
			 'COMPULSORY'=>0,'TRANSLATABLE'=>0, 'FCOUNT'=>20, 'FORDER'=>0, 'QUERYABLE'=>1, 'MULTIVALUED'=>0,
			 'BROWSABLE'=>1, 'DTAB'=>'%', 'PUBLISHED'=>1, 'TARGET'=>'%', 'DPARAM'=>NULL);
    }
    bugWarning('\Seolan\Core\Field\Field::__construct: bad parameter', !empty($obj), true);
    $field = @$obj->FIELD;
    $ftype = @$obj->FTYPE ?? ('\\' . get_called_class());
    $table = @$obj->DTAB;

    $this->_options = new \Seolan\Core\Options();
    $this->ftype = $ftype;
    $this->field = $field;
    $this->table = $table;
    $this->sys = $this->_sysField();
    $this->fcount = @$obj->FCOUNT;
    $this->forder = @$obj->FORDER;
    $this->compulsory = @$obj->COMPULSORY;
    $this->queryable = @$obj->QUERYABLE;
    $this->browsable = @$obj->BROWSABLE;
    $this->translatable = @$obj->TRANSLATABLE;
    $this->multivalued = @$obj->MULTIVALUED;
    $this->published = @$obj->PUBLISHED;
    $this->set_target(@$obj->TARGET);
    $this->DPARAM = @$obj->DPARAM;
    $this->initOptions();
    $this->setOptions(@$obj->DPARAM);
    $this->readonly = $this->readonly||$this->_sysRO();
    if(!empty($obj->MTXT)) $this->label = $obj->MTXT;
    elseif(!empty($obj->LABEL)) $this->label = $obj->LABEL;
    else $this->label=$field;
    if(!empty($this->aliasmodule)) {
      \Seolan\Core\Alias::register($this->aliasmodule);
    }
    $parts = explode('\\', get_class($this));
    $this->_browseCssClass = strtolower('browse-field browse-field-'.array_pop($parts).' browse-field-'.$this->field);
  }


  /// Creation d'une valeur prete a etre retournee
  function _newXFieldVal(&$options, $genid=false, $method=null) {
    $r=new \Seolan\Core\Field\Value($options,$method);
    $r->field=$this->field;
    $r->table=$this->table;
    $r->sys=$this->sys;
    $r->readonly=$this->readonly;
    $r->fielddef=$this;
    if($genid) $r->varid=getUniqID('v'.$r->field);
    if (isset($options['_masterValue']))
	$r->_masterValue = $options['_masterValue'];
    return $r;
  }
  /// Creation d'une valeur prete a etre retournee avec utilisation du cache et du calcul différé
  function _newXFieldValWithCacheAndDeferred($value,&$options, $genid=false, $method=null) {
    // Nom du chache
    $cache=$method.'/'.\Seolan\Library\ProcessCache::generateHash($options);
    if(!@$options['nocache'] && ($r=$this->getCache($cache,$value))) return $r;
    // Creation d'un \Seolan\Core\Field\Value à remplissage différé
    $r=$this->_newXFieldVal($options,$genid,$method);
    $r->raw=$value;
    if(!@$options['nocache']) $this->setCache($cache,$r->raw,$r);
    return $r;
  }
  /// Creation d'une valeur prete a etre retournee avec utilisation du cache et du calcul différé
  function _newXFieldValDeferred($value,&$options, $genid=false, $method=null) {
    // Creation d'un xfieldval à remplissage différé
    $r=$this->_newXFieldVal($options,$genid,$method);
    $r->raw=$value;
    return $r;
  }
  /// creation d'une valeur prete a etre retournee pour la recherche
  function _newXFieldQuery($option=NULL, $genid=false) {
    $r=new \Seolan\Core\Field\Query();
    if (isset($this->view)) {
      $r->table = $this->view;
      $r->field = $this->view . '.' . $this->field;
    } else {
      $r->table = $this->table;
      $r->field = $this->table . '.' . $this->field;;
    }
    $r->sys=$this->sys;
    $r->readonly=$this->readonly;
    $r->fielddef=$this;
    if($genid) $r->varid=getUniqID('q'.$r->field);
    return $r;
  }
  protected function initOptions() {
    $helpgroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','help');
    $querygroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','query');
    $this->_options->setId($this->table.':'.$this->field);
    $this->_options->setDefaultGroup(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','common_options'));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','theclass'), 'theclass', 'text', null, '');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','readonly'), 'readonly', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','hidden'), 'hidden', 'boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','comment'), 'comment', 'ttext', array('rows'=>2, 'cols'=>60),NULL, $helpgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','comment').' Backoffice', 'acomment', 'ttext', array('rows'=>2, 'cols'=>60), NULL,$helpgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','comment').' query', 'qcomment', 'ttext', array('rows'=>2, 'cols'=>60), NULL,$helpgroup);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','group'), 'fgroup', 'ttext');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','initfieldifduplicate'), 'initFieldIfDuplicate', 'boolean', false);

    if($this->multivalued) {
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','multiseparator'),'multiseparator','text',NULL,'<br/>');
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','multiseparatortext'),'multiseparatortext','text',NULL,', ');
    }
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','default'),'default','text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','aliasmodule'),'aliasmodule','module',array('toid'=>4,'emptyok'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','meta_source'),'exif_source','text',array('rows'=>3,'cols'=>40));

    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','add_browse_class'),'add_browse_class','boolean', null, false);

    $this->_options->setOpt(
      \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','browse_format'),
      'browse_format',
      'list',
      array(
        'values' => array(
          'extract',
          'full',
          'picto'
        ),
        'labels' => array(
          \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','extract'),
          \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','full'),
          \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','picto')
        )
      )
    );

    if(!empty($this->query_formats)){
      $class=strtolower(get_class($this));
      $labels=$optionsValues=[];
      foreach($this->query_formats as $qf){
	if($this->compulsory && $qf == 'filled')
          continue;
	$optionsValues[] = $qf;
	$tt='qf-'.$class.'-'.$qf;
	$label=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field',$tt);
	if($tt==$label) $label=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','qf-'.$qf);
	$labels[]=$label;
      }
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query_formats'),
			      'query_format',
			      'list',
			      ['values'=>$optionsValues,'labels'=>$labels],
			      NULL,
			      $querygroup);
    }
    $this->_options->setOpt('Dépendance', 'dependency', 'dependency');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','indexable'), 'indexable', 'boolean', NULL, false,$querygroup);
  
    $rgpdgroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','RGPD');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD','personaldata'), 'RGPD_personalData', 'boolean', null, false, $rgpdgroup);
  }
  function get_sqlValue($value) {
    // Fabrique une valeur de colonne pour SQL a partir d'une valeur issue de PHP
    return $value; // Dans la majorite des cas.
  }

  public function hasExternals() { return false; }
  public function externals($value) { return NULL; }
  public function set_target($t) {
    if (!empty($t) && $t != TZR_DEFAULT_TARGET && !\Seolan\Core\DataSource\DataSource::sourceExists($t)) {
      \Seolan\Core\Logs::critical('Target `'.$t.'` of field `'.$this->table.'.'.$this->field.'` not exists');
      $this->target = TZR_DEFAULT_TARGET;
    } else {
      $this->target = $t;
    }
  }
  public function get_target() { return $this->target; }
  public function set_readonly($t) { $this->readonly=$t;}
  public function set_hidden($t) { $this->hidden=$t;}
  public function use_fcount() { return \Seolan\Core\Field\Field::$_fields[$this->ftype][1];}
  public function get_readonly() { return $this->readonly;}
  public function get_hidden() { return $this->hidden;}
  public function get_fcount() { return $this->fcount;}
  public function set_fcount($p) { $this->fcount=$p;}
  public function get_ftype() { return $this->ftype; }
  public function set_ftype($p) { $this->ftype=$p; }
  public function set_forder($p) { $this->forder=$p;}
  public function get_forder() { return $this->forder;}
  public function get_use_target() { return \Seolan\Core\Field\Field::$_fields[$this->ftype][2];}
  public function get_fgender() { return \Seolan\Core\Field\Field::$_fields[$this->ftype][0]; }
  public function get_field() { return $this->field; }
  public function set_field($p) { $this->field=$p; }
  public function get_compulsory() { return $this->compulsory; }
  public function set_compulsory($p) { $this->compulsory=$p; }
  public function get_queryable() { return $this->queryable; }
  public function set_queryable($p) { $this->queryable=$p; }
  public function get_browsable() { return $this->browsable; }
  public function set_browsable($p) { $this->browsable=$p; }
  public function get_translatable() { return $this->translatable; }
  public function set_translatable($p) { $this->translatable=$p; }
  public function get_multivalued() { return $this->multivalued; }
  public function set_multivalued($p) { $this->multivalued=$p; }
  public function get_published() { return $this->published; }
  public function set_published($p) { $this->published=$p; }
  private function _sysField() { return in_array($this->field, array('UPD','PUBLISH','PRIV','OWN','PRP','CREAD','LANGREPLI','APP')); }
  private function _sysRO() { return in_array($this->field, array('UPD','OWN','CREAD')); }
  public function sysField() { return $this->sys; }
  public function get_options() { return $this->_options->getView();}
  public function is_summable() { return false; }
  public static function  isMultiValuable() {
    if(isset(static::$multivaluable)) { 
      if(static::$multivaluable==false) { //$multivaluable différent de $multivalued Static::$multivaluable
        return false; //si le champ n'est pas multivaluable on renvoi false
      } else {
        return true; //si le champs est multivaluable on renvoi true
      }
    } else { //si $multivaluable n'est pas une propriété de l'objet
      return true; //on retoune true le champ peut être multivalué par défaut.
    }  
  }

  function getOptions($block='opt', $get_edit=true) {
    $this->_options->setComment($this->getBrowseCssClass(), 'add_browse_class');
    return $this->_options->getDialog($this, array(), $block, 'admin', $get_edit);
  }
  function serializeOptions() {
    return $this->_options->serialize($this);
  }
  function jsonOptions() {
    return $this->_options->json_encode($this);
  }

  function resetOptions() {
    $this->setOptions($this->DPARAM);
  }
  function setOptions($dparam) {
    $this->_options->setValues($this,\Seolan\Core\Options::decode($dparam));
  }

  function get_label() {
    return $this->label;
  }
  /// la classe qui sera positionnée si add_browse_class à oui
  public function getBrowseCssClass(){
    return $this->_browseCssClass;
  }
  /// rend les libelles d'un champ dans toutes les langues
  function get_labels() {
    if(isset($this->labels)) return $this->labels;
    $rs=getDB()->fetchAll("select MLANG, MTXT from MSGS where MTAB=? and FIELD=?",
			  array($this->table, $this->field));
    $r=array();
    foreach($rs as $ors) $r[$ors['MLANG']]=$ors['MTXT'];
    unset($rs);
    $this->labels = $r;
    return $r;
  }

  /// rend la valeur d'une option, avec toutes les lkangues s'il s'agit d'un libelle
  function get_option($o) {
    $txts=\Seolan\Core\Labels::getAMsg($this->_options->id.':'.$o,NULL,false);
    return $txts;
  }
  function set_label($v) { die('\Seolan\Core\Field\Field::get_labels'); }
  function set_labels($vs) {
    unset($this->labels);
    foreach($vs as $codeLang => $v) {
      if ( $v != '' ) $this->labels[$codeLang] = $v;
    }
    $lg = \Seolan\Core\Shell::getLangUser();
    $this->label=$this->labels[$lg];
  }
  // renvoie un label html - usage formulaire FO
  // ajoute la classe "tzr-label-compulsory"
  // et le marqueur $compulsory_mark aux champs obligatoires
  function get_htmllabel($compulsory_mark = '*', $htmlID = '') {
    if ($this->compulsory)
      $label = '<label for="'.($htmlID ?? $this->field).'" class="tzr-label-compulsory">'.$this->label.'&nbsp;'.$compulsory_mark.'</label>';
    else
      $label = '<label for="'.($htmlID ?? $this->field).'">'.$this->label.'</label>';
    if ($this->comment)
      $label .= '<div class="cv8d-comment" >'.$this->comment.'</div>';
    return $label;
  }
  function get_browse_query($value) {
    if($this->multivalued) {
      $globdisp='';
      $machaine = $value;
      $val = explode('||',trim($machaine));
      $count = count($val);
      $cond = '';
      for($i=0;$i<$count; $i++) {
	if($val[$i]!='') {
	  if($cond!='') $cond=$cond.' or ';
	  $cond = $cond." KOID='$val[$i]' ";
	}
      }
      $xst = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->target);
      $query=$xst->select_query(array()).' and ('.$cond.')';
      return $query;
    }
  }
  function raw_oids($value,$options=NULL) {
    $r=array();
    if($this->multivalued) {
      $machaine = $value;
      $val = explode('||',trim($machaine));
      $count = count($val);
      for($i=0;$i<$count; $i++) {
	if($val[$i]!='') {
	  $r[] = $val[$i];
	}
      }
    } else {
      $pos = strstr($value[0],'||');
      if($pos!='') {
	$val = explode('||',trim($value[0]));
	$count = count($val);
	for($i=0;$i<$count; $i++) {
	  if($val[$i]!='') {
	    $r[] = $val[$i];
	  }
	}
      } else {
	$r[]=$value;
      }
    }
    return $r;
  }
  function export($value,$options=NULL) {
    $format=$options['format'];
    if(!isset($format)) $format='xml';
    $func='export_'.$format;
    return $this->$func($value,$options);
  }
  function export_xml($value,$options) {
    $labels=$options['labels'];
    $txt='';
    $lang = $GLOBALS['LANG_DATA'];
    foreach($value as $k => $v) {
      if(($v!='')) {
	$val = $this->my_export(array($v),$options);
	$l=htmlspecialchars($this->label);
	$l=str_replace(' ','_',$l);
	$txt.="<$l>$val</$l>\n";
      }
    }
    return $txt;
  }

  function multi_oid_display($value, $options=NULL) {
    $r = $this->_newXFieldVal($options);
    $r->raw=$value;
    $val = explode('||',trim($value));
    $count = count($val);
    if($count>0)     {
      $r->collection = array();
      $r->oidcollection=array();
    }
    $options['_masterValue'] = $r;
    for($i=0;$i<$count; $i++) {
      if(($val[$i]!='') && ($val[$i]!='Foo')) {
	$value = $val[$i];
	$o=$this->my_display($value, $options);
	if(is_object($o) && !empty($o->raw)) {
	  $r->collection[] = $o;
	  $r->oidcollection[] = $val[$i];
	}
      }
    }
    return $r;
  }
  function multi_data_display(&$value,&$options) {
    $r = $this->_newXFieldVal($options);
    $separator=$this->separator;
    $r->raw=$value;
    $r->collection = array();
    $r->rawcollection = array();
    $val = preg_split('@['.$separator.']@',trim($r->raw));
    $count=count($val);
    for($i=0; $i<$count; $i++) {
      if($val[$i]!='') {
        $value = trim($val[$i]);
        $o=$this->my_display($value, $options);
        if(is_object($o) && isset($o->raw)) {
          $r->collection[] = $o;
          $r->rawcollection[] = $o->raw;
          if(($r->html!='')&&(strlen($o->html)>0)) $r->html .= $this->separator[0].' '.$o->html;
          else $r->html .= $o->html;
        }
      }
    }
    return $r;
  }

  function my_getJSon($o, $options) {
    if($this->isEmpty($o)) return null;
    if (isset($options['property']))
      return $o->{$options['property']};
    return $o->raw;
  }

  function getJSon($value, $options=[]) {
    $o = $this->display($value, $options);
    if ($this->isEmpty($o)) {
      return NULL;
    }
    return $this->my_getJSon($o, $options);
  }

  function display(&$value,$options=array()) {
    if(!empty($options['value'])) $value=$options['value'];
    if($this->multivalued && ($this->get_fgender()=='Oid')) {
      $o = $this->multi_oid_display($value,$options);
    } elseif($this->multivalued && !empty($this->separator)) {
      $o = $this->multi_data_display($value,$options);
    } else {
      $o = $this->my_display($value, $options);
    }
    if(@$options['_format']=='xhtml') {
      $o->xhtml = '<'.$this->field.'>'.$o->html.'</'.$this->field.'>';
    }
    return $o;
  }
  function browse(&$value, &$options = []) {
    $r = $this->_newXFieldVal($options);
    if($this->multivalued && ($this->get_fgender()=='Oid')) {
      $r->raw=$value;
      $machaine = $value;
      $val = explode('||',trim($machaine));
      $count = count($val);
      if($count>0)     {
	$r->collection = array();
	$r->oidcollection=array();
      }
      $options['_masterValue'] = $r;
      for($i=0;$i<$count; $i++) {
        if($val[$i]!='' && $val[$i]!='Foo') {
	  $value1 = $val[$i];
	  $o=$this->my_browse($value1, $options);
	  if(is_object($o) && isset($o->raw)) {
	    $r->collection[] = $o;
	    $r->oidcollection[] = $value1;
	  }
	}
      }
      $o=$r;
    } elseif($this->multivalued && !empty($this->separator)) {
      $separator=$this->separator;
      $r->raw=$value;
      $val = preg_split('@['.$separator.']@',trim($r->raw));
      $count = count($val);
      if($count>0)     {
        $r->collection = array();
	for($i=0;$i<$count; $i++) {
	  if($val[$i]!='') {
	    $value1 = trim($val[$i]);
	    $o=$this->my_browse($value1, $options);
	    if(is_object($o) && isset($o->raw)) {
	      $r->collection[] = $o;
	      if(($r->html!='')&&(strlen($o->html)>0)) $r->html .= $separator[0].' '.$o->html;
	      else $r->html .= $o->html;
	    }
	  }
	}
      }
      $o=$r;
    } else {
      $o=$this->my_browse($value, $options);
    }
    return $o;
  }
  function input(&$value,&$options=array(),$fields_complement=null) {
    if(isset($options['value'])) $value=$options['value'];
    if((!\Seolan\Core\Shell::isRoot() && ($this->readonly || @$options['readonly'])) || @$options['readonly']==2) {
      $r=$this->display($value,$options);
      $this->setHtmlDependency($r);
      return $r;
    }

    $value = $this->multiplesValues($value);

    $r=$this->my_input($value,$options,$fields_complement);
    $this->editHtmlError($r);

    if($this->multivalued && $this->advanceeditbatch && !empty($options['editbatch'])){
      $id=getUniqID('v'.$r->field);
      $r->html="<select name=\"{$this->field}_op\" id=\"$id_1\">".
	"<option value=\"+\">".\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','editbatch_add')."</option>".
	"<option value=\"=\">".\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','editbatch_replace')."</option>".
	"<option value=\"-\">".\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','editbatch_remove')."</option>".
	"</select><br/>".$r->html;
    }

    // finalisations diverses
    if(empty($r->varid)) $r->varid=getUniqID('v'.$r->field);
    $this->setHtmlDependency($r);
    $r->edit=true;
    if($this->compulsory) $this->indicator=' *';
    else $this->indicator='';
    return $r;
  }
  function edit($value,&$options=array(),$fields_complement=null) {
    if(isset($options['value'])) $value=$options['value'];
    if((!\Seolan\Core\Shell::isRoot() && ($this->readonly || @$options['readonly'])) || @$options['readonly']==2) {
      $r=$this->display($value,$options);
      $this->setHtmlDependency($r);
      return $r;
    }

    $value = $this->multiplesValues($value);

    $r=$this->my_edit($value,$options,$fields_complement);

    $this->editHtmlError($r);

    if($this->multivalued && $this->advanceeditbatch && !empty($options['editbatch'])){
      $id=getUniqID('v'.$r->field);
      $r->html=	'<input id="'.$id.'-1" class="radio" type="radio" name="'.$this->field.'_op" value="+" checked>'.
	'<label class="tzr-st-label" for="'.$id.'-1">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','editbatch_add').'</label> '.
	'<input id="'.$id.'-2" class="radio" type="radio" name="'.$this->field.'_op" value="=">'.
	'<label class="tzr-st-label" for="'.$id.'-2">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','editbatch_replace').'</label> '.
	'<input id="'.$id.'-3" class="radio" type="radio" name="'.$this->field.'_op" value="-">'.
	'<label class="tzr-st-label" for="'.$id.'-3">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','editbatch_remove').'</label><br>'.
	$r->html;
    }
    // finalisation
    if(empty($r->varid)) $r->varid=getUniqID('v'.$r->field);
    $this->setHtmlDependency($r);
    $r->edit=true;
    if($this->compulsory) $this->indicator=' *';
    else $this->indicator='';
    return $r;
  }
  /**
   * mise en forme d'une valeur brute multiple
   */
  private function multiplesValues($value){
    if($this->multivalued && ($this->get_fgender()=='Oid')) {
      $val = explode('||',trim($value));
      $count = count($val);
      $value=array();
      for($i=0;$i<$count;$i++) {
	if($val[$i]!='') {
	  $value[$val[$i]]=1;
	}
      }
    } elseif (strpos($value,'||')!==false && ($this->get_fgender()=='Oid') ) {
      $val = explode('||',trim($value));
      $count = count($val);
      unset($value);
      $stop=0;
      for($i=0;($i<$count) && ($stop!=1); $i++) {
	if(trim($val[$i])!='') {
	  $value=$val[$i];
	  $stop=1;
	}
      }
    }
    return $value;
  }
  /**
   * element de visualisation des erreurs sur un champ en creation/edition
   */
  protected function editHtmlError($r){
    if (@$this->errorIsEmpty){
      $r->html .= '<div class="error-field-comment">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','field_empty').'</div>';
    }
    if (@$this->errorFormat){
      $r->html .= '<div class="error-field-comment">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','field_format_error').'</div>';
    }
  }
  /**
   *  champ visible ou pas compte tenu des dependances
   */
  public function dependencyHidden($fieldsvalues){
    if (!$this->isDependent()){
      return false;
    }
    $filterfield = $this->dependency['f'];
    $filterfieldvalue = $fieldsvalues[$filterfield];
    foreach($this->dependency['style'] as $i=>$dstyle){
      if ($dstyle != 'hidden'){
	continue;
      }
      if ($this->dependency['op'][$i] == '=' && $this->dependency['dval'][$i] == $filterfieldvalue){
	return true;
      }
      if ($this->dependency['op'][$i] == '!=' && $this->dependency['dval'][$i] != $filterfieldvalue){
	return true;
      }
    }
    return false;
  }
  /// champ dependant d'un autre
  public function isDependent(){
    return !empty($this->dependency['f']);
  }
  /// code de gestion de la dependance entre champs
  protected function setHtmlDependency(&$r){
    if(!empty($this->dependency['f'])){
      $r->dependency='<script type="text/javascript">';
      $r->dependency.='if(typeof(TZR)!="undefined"){';
      foreach($this->dependency['dval'] as $i=>$foo){
	  $r->dependency.='TZR.addDependency("'.$this->ftype.'","'.$this->dependency['f'].'","'.$this->field.'","'.$this->dependency['dval'][$i].
	    '","'.addslashes($this->dependency['val'][$i]).'","'.$this->dependency['op'][$i].'","'.$this->dependency['style'][$i].'",'.
	    '"'.$this->dependency['nochange'][$i].'");'."\n";
      }
      $r->dependency.='}</script>';
      $r->html.=$r->dependency;
    }
  }
  /// Recupere la liste des valeurs presente dans une table pour un filtre donné
  public function _getUsedValues($filter = NULL, $select = NULL, $options = array()) {
    // Valeur données en options
    if (isset($options['select_box_values'])) {
      $values = array_flip($options['select_box_values']);
      return $values;
    }
    // Sinon valeur en base
    $table = $this->view ?? $this->table;
    $select = preg_replace('/^select .*?(from|) from /i','select '.$table.'.'.$this->field.' from ',$select);
    $this->_getUsedValuesParams($filter, $select, $options, $cachename);
    $values = \Seolan\Core\DbIni::get($cachename, 'val');
    if ($values === null || $values['f'] != $filter || $values['s'] != $select) {
      $usedValues = self::_getUsedValuesFromDB($select, $this->multivalued, $this->separator);
      $values = array('f' => $filter, 's' => $select, 'v' => $usedValues, 'multivalued' => $this->multivalued, 'separator' => $this->separator);
      \Seolan\Core\DbIni::set($cachename, $values);
    }
    return $values['v'];
  }

  /// Recupere la liste des valeurs presentes
  static function _getUsedValuesFromDB($select, $multivalued=false, $separator) {
    $values = getDB()->fetchCol($select);
    if ($multivalued) {
      if (empty($separator))
        $separator = '||';
      $values = implode($separator, $values);
      $values = array_filter(array_unique(explode($separator, $values)));
    }
    return array_fill_keys($values, true);
  }

  /// Met on forme les parametres utilisés pour récupérer les valeurs utilisées
  public function _getUsedValuesParams(&$filter,&$select,&$options,&$cachename) {
    $lang_data = TZR_DEFAULT_LANG;
  
    $table = $this->view ?? $this->table;
    if (!empty($table)) {
      $xst = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$table);
      if (is_object($xst) && $xst->isTranslatable()) {
        $lang_data = \Seolan\Core\Shell::getLangData();
      }
      $condpublish = '';
      if (is_object($xst)){
	if($xst->publishedMode(new \Seolan\Core\Param(NULL, array())) == 'public')
	  $condpublish.=' AND '.$xst->getTable().'.PUBLISH="1" ';
      }
      $condapp = '';
      if(TZR_USE_APP && $xst->fieldExists('APP') && !\Seolan\Core\Shell::isRoot()) {
        $bootstrapApplication = \Seolan\Module\Application\Application::getBootstrapApplication();
        if($bootstrapApplication && $bootstrapApplication->oid) {
          $condapp .= ' AND '.$xst->getTable().'.APP="'.$bootstrapApplication->oid.'" ';
        }
      }
    }
    // on ne prend pas les valeurs vides d'autant plus quelles sont supprimees ensuite
    $filter = (empty($filter) ? '' : "($filter) AND ") . '('.$this->field.' != \'\')';
    if(!empty($table) && !$select){
      $where=$filter;
      if(!empty($options['fmoid'])){
        $mod=\Seolan\Core\Module\Module::objectFactory($options['fmoid']);
	$modfilter=$mod->getUsedValuesFilter();
	if($modfilter) $where.=' AND '.$mod->getUsedValuesFilter();
      }
      $where .= ' AND LANG="'.$lang_data.'"' . $condpublish . $condapp;

      $what = $this->field;
      if (!empty($options['relatedFields'])) {
        $what = 'concat_ws(", ",' . $what . ',' . $options['relatedFields'] .')';
      }
      $select="SELECT DISTINCT $what FROM {$table} WHERE $where";
      if(!empty($options['order'])) $select.=' ORDER BY '.$options['order'];
      if(!empty($options['limit'])) $select.=' LIMIT '.$options['limit'];
    }
    $code=md5($filter.'-'.$select);

    if($this->target==TZR_DEFAULT_TARGET)
      $cachename=$table.':usedValues:'.$this->field.':'.$lang_data.':'.$code;
    else
      $cachename=$table.'|'.$this->target.':usedValues:'.$this->field.':'.$lang_data.':'.$code;

  }


  function quickquery($value,$ar=NULL) {
    if (empty($value) && $this->isFilterCompulsory($ar) && !empty(@$ar['fields_complement']['query_comp_field_value'])) {
      if (strpos(@$ar['fields_complement']['query_comp_field_value'], '||') !== false) {
        $value = explode('||', @$ar['fields_complement']['query_comp_field_value']);
      } else {
        $value = @$ar['fields_complement']['query_comp_field_value'];
      }
    }
    if(empty($ar['query_format'])) $ar['query_format']=\Seolan\Core\Field\Field::QUICKQUERY_FORMAT;
    return $this->my_quickquery($value,$ar);
  }

  function my_quickquery($value,$ar=NULL) {
    return $this->_newXFieldVal($ar);
  }

  /**
   * Génère le code HTML des paramètres de recherche spécifiques à un champ
   * @param $value mixed Valeur renseignée par défaut ou précédemment soumise
   * @param $ar array Options d'affichage ou de contexte
   * @return Value Champ avec les propriétés html, text, raw... renseignées
   */
  function query($value,$ar=NULL) {
    /// @todo Expliquer dans quel cas cette substitution est utile pour les tableaux !
    if(is_array($value) && !in_array('Foo',$value) && $this->isLink()) {
      foreach($value as $i => $val1) {
	if(!empty($val1)) {
	  $va[$val1]=1;
	}
      }
      $value=$va;
    } elseif(!is_array($value)) {
      if($this->multivalued || @$ar['query_format']) {
	if($value!='Foo') {
	  $val = explode('||',trim($value));
	  unset($value);
	  $value=array();
	  foreach($val as $i => $val1) {
	    if(!empty($val1)) {
	      $value[$val1]=1;
	    }
	  }
	}
      } elseif (strstr($value,'||')!='') {
	$val = explode('||',trim($value));
	$count = count($val);
	unset($value);
	$stop=0;
	for($i=0;($i<$count) && ($stop!=1); $i++) {
	  if(trim($val[$i])!='') {
	    $value=$val[$i];
	    $stop=1;
	  }
	}
      }
    }
    if(empty($ar['query_format'])) $ar['query_format']=\Seolan\Core\Field\Field::QUERY_FORMAT;

    $r=$this->my_query($value,$ar);

    if($this->qcomment) {
      $r->html .= '<label class="fieldHelp" tabindex="0" role="button" data-html="true" data-toggle="popover" data-trigger="" data-content="<p>'.$this->qcomment.'</p>"><span class="glyphicon csico-infos" aria-hidden="true"></span></label>';
    }

    return $r;
  }
  /// Equivalent de query, mais prepare en plus les options sur le format de champs de recherche
  function pquery($value,$ar=NULL) {
    $qfmt=$ar['qfmt'];
    if($ar['notapplyqfmt']) unset($ar['qfmt']);
    $r=$this->query($value,$ar);
    if(empty($this->query_formats)) return $r;
    $fname=(isset($ar['fieldname'])?$ar['fieldname']:$this->field);
    $formats='<select name="options['.$fname.'][qfmt]">';
    $class=strtolower(get_class($this));
    foreach($this->query_formats as $qf){
      if($this->compulsory && $qf == 'filled')
	continue;
      $label=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','qf-'.$class.'-'.$qf);
      if(empty($label)) $label=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','qf-'.$qf);
      $formats.="<option value=\"$qf\"".($qf==$qfmt?'selected':'').">$label</option>";
    }
    $formats.='</select>';
    $r->query_formats=$formats;
    return $r;
  }
  // action a effectuer apres edition et avant stockage des données
  // lors de la creation de la donnée
  function post_input($value,$options=NULL,&$fields_complement=NULL) {
    return $this->post_edit($value,$options, $fields_complement);
  }
  // action a effectuer apres edition et avant stockage des donnees
  // lors de la modification de la donnéee
  // on rend un tableau de valeur traitees
  function post_edit($value,$options=NULL,&$fields_complement=NULL) {
    $r=$this->_newXFieldVal($options);
    $r->raw=$value;
    $this->trace(@$options['old'],$r);
    return $r;
  }
  /**
   * récupère les paramètres de configuration de la recherche
   * positionnés en général dans le html de my_query et nécessaires
   * au post_query pour formuler la requete
   */
  function post_query_configure(\Seolan\Core\Field\Query $o, \Seolan\Core\Param $p, ?string $idx=null){
    if ($idx == null)
      $idx = $this->field;
    $o->empty=$p->get($idx.'_empty');
    $o->op = $p->get($idx.'_op');
    $o->hid=$p->get($idx.'_HID');
    $o->fmt=$p->get($idx.'_FMT');
    $o->par=$p->get($idx.'_PAR');
  }
  /**
   * action effectuée apres une soumission de recherche
   * on rend un tableau de valeur traitees
   */
  function post_query($o,$ar) {

    $v1=$o->value;
    $v1op=$o->op;
    $k1=$o->field;
    $quotes=@$o->quote;
    $o->rq='';

    // cas de champs json : restreindre au path fourni
    if (!empty($ar['jsonQueryPath']))
      $k1 = "JSON_VALUE({$k1}, \"{$ar['jsonQueryPath']}\")";
    
    // cas d'une formule
    if(!empty($v1) && is_string($v1) && ($v1[0]=='=')) {
      $quotes='';
      $v1=substr($v1,1);
    }

    // cas d'un oid sans le prefixe table
    if (!is_array($v1) && !empty($v1) && !\Seolan\Core\Kernel::isAKoid($v1) && $this->isLink()) {
      $v1=$this->target.':'.$v1;
    }

    // cas ou la valeur est un tableau et que le champ est multivalue
    // les liens normalisés sont traités dans Field/Link
    if($this->get_multivalued() && is_array($v1) && !empty($v1)
       && (!$this->isLink() || !$this->normalized)) {
        if($v1op!='OR' && $v1op !== 'EXCLUSIVE' && $v1op !== 'NONE') $v1op='AND';
      $rq=array();
      if ($v1op === 'EXCLUSIVE') {
        if (count($v1) === 1) {
          $o->rq = '('.$k1.'='.getDB()->quote($v1[0]).' OR '.$k1.'='.getDB()->quote('||'.$v1[0].'||').')';
        } else {
          $rq = [];
	  $v1 = array_filter($v1);
          foreach ($v1 as $value) {
            $rq[] = $k1.' LIKE '.getDB()->quote('%'.$value.'%');
          }

          $o->rq = '('.implode(' AND ', $rq).' AND LENGTH('.$k1.') = '.(strlen(str_replace('||||', '||', implode('||',$v1)))+4).')';
        }
      } else {
        foreach($v1 as $i=>$v){
          $o1=clone($o);
          $o1->rq='';
          $o1->value=$v;
          $this->post_query($o1,$ar);
          if(!empty($o1->rq)) $rq[]=$o1->rq;
        }

        if(empty($rq)) $o->rq=NULL;
        elseif ($v1op === 'NONE') {
          $o->rq='(!'.implode(' AND !',$rq).')';
        } else {
          $o->rq='('.implode($v1op,$rq).')';
        }
      }
    }
    // cas ou la valeur multiple est un tableau et le champ n'est pas multivalué
    elseif(!$this->get_multivalued() && is_array($v1) && !empty($v1)) {
      $count=count($v1);
      $rq='(';
      for($i=0;$i<$count;$i++) {
	$o1=clone($o);
	$o1->rq='';
	$o1->value=$v1[$i];
	$this->post_query($o1,$ar);
	//dans le cas d'une recherche ne contenant pas les valeurs le separateur est un AND
	$sep = 'OR';
	if($o1->op == "!=") $sep = 'AND';
	if(!empty($o1->rq)) {
	  if($rq=='(') $rq.=$o1->rq;
	  else $rq.=' '.$sep.' '.$o1->rq;
	}
      }
      $rq.=')';
      if($rq=='()')
	$o->rq=NULL;
      else
	$o->rq=$rq;
    }
    // traitement de tous les cas possible
    elseif(is_string($v1) && $v1op=='$') {
      $v1=getDB()->quote(".*$v1\$");
      $o->rq="($k1 rlike $v1)";
    }
    elseif(is_string($v1) && $v1op=='!~') {
      $v1=getDB()->quote(".*$v1.*");
      $o->rq="not ($k1 rlike $v1)";
    }
    elseif(is_string($v1) && $v1op=='^') {
      $v1=getDB()->quote("^$v1.*");
      $o->rq =" ($k1 rlike $v1)";
    }
    elseif(is_string($v1) && $v1op=='regex') {
      $v1=getDB()->quote("$v1");
      if($this->testRegexSql($v1)) {
        $o->rq =" ($k1 REGEXP $v1)";
      }
    }
    elseif(is_string($v1) && $v1op=='noregex') {
      $v1=getDB()->quote("$v1");
      if($this->testRegexSql($v1)) {
        $o->rq =" ($k1 NOT REGEXP $v1)";
      }
    }
    elseif($v1op=='is empty') {
      $o->rq = " (($k1 is NULL) or ($k1 ='')) ";
    }
    elseif($v1op=='is not empty') {
      $o->rq = " (($k1 is not NULL) and ($k1 !='')) ";
    }
    elseif($v1op=='=') {
      $o->rq = " ($k1=" . getDB()->quote($v1) . ") ";
    }
    elseif(is_array($v1) && !empty($v1) && $this->get_multivalued() && ($this->get_fgender()=='Oid')) {
      // traitement du multivalue avec separateur ||
      $count = count($v1);
      for($j=0;$j<$count; $j++) {
	if(!empty($v1[$j])) {
	  $o->rq=" (($k1 = ".getDb()->quote($v1[$j]).") OR INSTR(".$k1.", ".getDb()->quote('||'.$v1[$j].'||')."))";
	  if(($j!=0) && ($j!=($count-1))) $o->rq.='AND';
	}
      }
      if(!empty($o->rq)) $o->rq='('.$o->rq.')';
    }
    elseif(!empty($v1) && ($this->get_fgender()=='Oid') && (strstr($v1,'||')!=false)) {
      // traitement du multivalue avec separateur ||
      $v1=explode('||',$v1);
      $count = count($v1);
      for($j=0;$j<$count; $j++) {
	if(!empty($v1[$j])) {
	  $o->rq=" (($k1 = ".getDb()->quote($v1[$j]).") OR INSTR(".$k1.",".getDb()->quote('||'.$v1[$j].'||')."))";
	  if(($j!=0) && ($j!=($count-1))) $o->rq.='AND';
	}
      }
      if(!empty($o->rq)) $o->rq='('.$o->rq.')';
    }
    elseif(is_array($v1) && ($this->get_fgender()=='Oid')) {
      // traitement du multivalue avec separateur ||
      $count = count($v1);
      for($j=0;$j<$count; $j++) {
	if(!empty($v1[$j])) {
	  $o->rq.=' ('.$k1." like ".getDb()->quote($v1[$j]).") ";
	  if(($j!=0) && ($j!=($count-1))) $o->rq.='AND';
	}
      }
      if(!empty($o->rq)) $o->rq='('.$o->rq.')';
    }
    elseif((!empty($v1) || $v1==="0") && ($this->get_fgender()=='Oid')) {
      if($this->get_multivalued()) {
	$o->rq="( ($k1=".getDb()->quote($v1).") OR instr(".$k1.", ".getDb()->quote('||'.$v1.'||')."))";
      }
      elseif($v1op=='!=') {
        $o->rq=' ('.$k1." != ".getDb()->quote($v1).")";
      }
      else {
        $o->rq=' ('.$k1." = ".getDb()->quote($v1).")";
      }
    } elseif ((!empty($v1) || $v1==="0") && !empty($v1op) && (is_string($v1) || is_numeric($v1))) {
      if(!empty($quotes)) $v1=getDB()->quote($v1);
      $o->rq=' ('.$k1." $v1op $v1)";
    } elseif ((!empty($v1) || $v1==="0") && !is_array($v1)) {
      if(empty($quotes)) {
	$o->rq=" (".$k1." like $v1)";
      } else {
	$v1=getDB()->quote("%$v1%");
	$o->rq=' ('.$k1." like $v1) ";
      }
    }
  }

  function testRegexSql($regexp) {
    try {
      if(!is_string($regexp) || @preg_match('/'.str_replace('/', '\/', $regexp).'/', null) === false) {
        return false;
      }
      return true;
    }
    catch(\Exception $e) {
      return false;
    }
  }

  // traduction d'une valeur 
  function data_duplicate($value, $langSrc, $langDest){
    return $value;
  }
  // rend une expression pour requêtage
  //
  // action a effectuer apres edition et duplication d'une donnee
  function post_edit_dup($value,$options) {
    $p = new \Seolan\Core\Param($options,array());
    $oidsrc=$p->get('oidsrc');
    $options['oid']=$oidsrc;
    return $this->post_edit($value,$options);
  }
  function sqltype() {
    return NULL;
  }

  function sqlfields() {
    return $this->field;
  }
  function sqlsumfunction() {
    return 'SUM('.$this->table.'.'.$this->field.')';
  }
  function delfield() {
    return true;
  }

  // suppression à partir d'un objet xfieldval
  function deleteVal($value,$oid) {
    return 1;
  }

  function my_display(&$value,&$options,$genid=false) {
    $r=$this->_newXFieldValWithCacheAndDeferred($value,$options,$genid,'my_display');
    return $r;
  }
  function my_display_deferred(&$r){
  }
  /// Ecriture dans un fichier excel
  function writeXLSPHPOffice(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet,$rownum,$colnum,$value,$format=0,$options=null) {
    $v = $this->getSpreadSheetCellValue($value,$options);
    convert_charset($v,TZR_INTERNAL_CHARSET,'UTF-8');
    $worksheet->getCellBycolumnAndRow($colnum,$rownum)->setValue($v);
    if(is_array($format)) $worksheet->getStyleByColumnAndRow($colnum,$rownum)->applyFromArray($format);
  }
  // Contenu d'une cellule pour feuille de calcul
  function getSpreadSheetCellValue($value, $options=null){
    if (is_a($value, \Seolan\Core\Field\Value::class)){
      $v=$value->toText();
      if(empty($v) && !$this->isEmpty($value))
	$v=$value->raw; 
    } elseif (is_string($value) && !$this->isEmpty($value)){
      $v = $value;
    } else {
      $v = '';
    }
    return static::escapeFormula($v);
  }
  // Encodate d'un valeur de cellule : '=
  public static function escapeFormula($value){
    $value = trim($value);
    if (mb_substr($value, 0, 1) == '='){
      return "'$value'";
    } else {
      return $value;
    }
  }
  // Valeur pour une cellule de CSV
  function getCSVValue($value, $textsep, $format=null, $options=null){
    $v = $this->getSpreadSheetCellValue($value,$options);
    $v=str_replace(array($textsep,"\x0d"),array($textsep.$textsep,""),$v);
    return $textsep.$v.$textsep;
  }
  // Ecriture dans un csv (deprecated)
  function writeCSV($o,$textsep){
    return $this->getCSVValue($o, $textsep);
  }
  /// Ecriture dans un fichier excel
  /// deprecated
  function writeXLS($xl,$i,$j,$value,$format=0,$ss=null) {
    if (is_object($value)){
      $v=$value->toText();
      if(empty($v)) $v=$value->raw;
    } else {
      $v = '';
    }
    convert_charset($v,TZR_INTERNAL_CHARSET,'UTF-8');
    $xl->setCellValue(\PHPExcel_Cell::stringFromColumnIndex($j-1) . $i, $v);
    if(is_array($format)) $xl->getStyleByColumnAndRow($j,$i)->applyFromArray($format);
  }
  /// Test de l'import des données vers une table
  public function checkImport($value, $specs){
    return $this->my_checkImport($value, $specs);
  }
  /// Import de données vers une table
  function import($value,$specs=NULL) {
    $value=trim($value);
    $defaultvalue=$specs->default??null;
    // conversion eventuelle de format
    if(!empty($specs->format) && !empty($value)) {
      $value=$this->convert($value,$specs->format->source,$specs->format->destination);
    }
    if(empty($value) && !empty($defaultvalue)) $value=$defaultvalue;
    $ret=$this->my_import($value,$specs);
    return array('message'=>$ret['message'],'value'=>$ret['value']);
  }
  /// Implémentation par champ de la vérification d'import
  protected function my_checkImport($value, $specs){
    return array('message'=>'','value'=>$value);
  }
  /// Sous fonction redefinie pour chaque type de champ pour l'import de données vers une table
  function my_import($value,$specs=null){
    return array('message'=>'','value'=>$value);
  }

  function my_browse(&$value,&$options,$genid=false) {
    $r=$this->_newXFieldValWithCacheAndDeferred($value,$options,$genid,'my_browse');
    return $r;
  }
  function my_browse_deferred(&$r){
    return $this->my_display_deferred($r);
  }

  function my_export($value) {
    return '';
  }
  function my_edit(&$value,&$options,&$fields_complement=NULL){
    $r = $this->_newXFieldVal($options,true);
    $r->value = $value;
    return $r;
  }
  function my_input(&$value,&$options,&$fields_complement=NULL){
    return $this->my_edit($value, $options, $fields_complement);
  }
  function my_query($value,$options=NULL){
    return $this->_newXFieldVal($options);
  }
  function filename($value) {
    return NULL;
  }
  function search($value,$options=NULL) {
    $rq='';
    if(is_array($value)) {
      foreach($value as $v) {
	if($rq!='') $rq .=' OR ';
	$rq .= $this->my_search($v,$options);
      }
    } else {
      $rq .= $this->my_search($value,$options);
    }
    return $rq;
  }
  function my_search($value,$options) {
    return $this->field.' like "%'.$value.'%" ';
  }
  function convert($value, $src, $dst) {
    return $value;
  }

  // Retourne le filtre du champ
  function getFilter(){
    if($this->filter) return '('.$this->filter.')';
    else return '';
  }

  // generation d'une url complete et partielle pour la consultation d'un fichier
  //
  protected function getDownloader($short_filename, $mime='application/x-octet-stream',
				   $originalname=NULL, $title=NULL, $moid=NULL) {
    if(empty($title)) $title=$originalname;
    if (empty($mime)) $mime='application/x-octet-stream';
    $mimelabel=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Mime',$mime, 'both', 'Seolan_Core_Mime', 'default');
    if(!empty($mimelabel)) $mimelabel.=' ';
    $originalname=removeaccents($originalname);

    $url=TZR_DOWNLOADER.'?filename='.$short_filename.'&amp;mime='.rawurlencode($mime).
      '&amp;originalname='.rawurlencode(preg_replace('/([^A-Za-z0-9\._-]+)/','_',$originalname)).(empty($moid)?'':'&amp;moid='.$moid).($this->gzipped==1?'&amp;gzip=1':'');
    if (defined('TZR_JSON_MODE') && !\Seolan\Core\Json::restAPI()) {
      $url .= '&amp;json_mode=1&amp;sessionid='.session_id();
    }
   
    
    $fullurl='<a class="tzr-file" href="'.$url.'">'.$mimelabel.$title.'</a>';
    return array($url, $fullurl);
  }

  /// Convertit le contenu de la base de données suite à un changement de format du champ
  public function convertValues($oldftype){
    return;
  }

  function setMetaFromValue($metaanalyser,&$r,$filter=array('IPTC','XMP')) {
    $isok=array();
    $metas=explode("\n",$this->exif_source);
    foreach($metas as &$meta){
      @list($from,$to)=@explode('=>',$meta);
      $list=explode('.',$from);
      $count=count($list);
      if($count<2) continue;
      if($count<3) array_unshift($list,'IPTC');
      if($this->multivalued){
        $v=array();
        foreach($r->collection as $c) {
          $v[] = $c->toText();
        }
      } else {
        $v = $r->toText();
      }
      if($list[0]=='XMP' && in_array('XMP',$filter) && !in_array('XMP',$isok)) {
        $metaanalyser->setXMPProperty($list[2],$v,@$list[3]);
        $isok[]='XMP';
      }
      if($list[0]=='IPTC' && in_array('IPTC',$filter) && !in_array('IPTC',$isok)){
        $metaanalyser->setIPTCProperty($list[2],$v);
        $isok[]='IPTC';
      }
    }
  }
  /// Recupere la valeur d'un champ depuis les meta d'un champ fichier
  function getMetaValue(&$computed_fields,$filter=array('IPTC','EXIF','XMP'),$getall=false){
    $value='';
    $metas=explode("\n",$this->exif_source);
    foreach($metas as &$meta){
      list($from,$to)=explode('=>',$meta);
      $list=explode('.',$from);
      $listto=explode('.',$to);
      $count=count($list);
      if($count<2) continue;
      if($count<3) array_unshift($list,'IPTC');
      if(!is_object(@$computed_fields[$list[1]])) return;
      if(in_array('IPTC',$filter) && $list[0]=='IPTC'){
	$value=$computed_fields[$list[1]]->getIPTC($list[2]);
	if(!$getall) $value=(is_object($value)?$value->raw:NULL);
	if(!empty($value)) break;
      }elseif(in_array('EXIF',$filter) && $list[0]=='EXIF'){
	$value=$computed_fields[$list[1]]->getEXIF($list[2]);
	if(!$getall) $value=(is_object($value)?$value->raw:NULL);
	if(!empty($value)) break;
      }elseif(in_array('XMP',$filter) && $list[0]=='XMP'){
	$value=$computed_fields[$list[1]]->getXMP($list[2]);
	if(!$getall) $value=(is_object($value)?$value->text:NULL);
	if(!empty($value)) break;
      }
    }
    if(is_object($value)){
      $value->schema=(object)array('format'=>$list[0],'field'=>$list[1],'prop'=>$list[2],'dest'=>$listto[0],'create'=>$listto[1]);
    }
    return $value;
  }

  /// Recupere le type du champ dans un webservice (name : type xml, descr : description du type pour l'ajour d'une type complexe)
  function getSoapType(){
    return array('name'=>'xsd:string');
  }
  /// Recupere la valeur formattée pour le service SOAP
  function getSoapValue($r){
    return $r->raw;
  }

  /// Retourne une chaine de caractere decrivant la recherche en cours sur le champ
  function getQueryText($o){
    $options=array();
    if($o->op == 'is empty' || $o->op == 'is not empty') {
      $ret=$this->getQueryTextOp($o->op,true);
    } else if(is_array($o->value) and !empty($o->value)){
      $vals=array();
      foreach($o->value as $v){
	$d=$this->display($v, $options);
	$vals[]=$d->text;
      }
      if($o->op=='OR') $sep=' '.strtolower(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','or')).' ';
      elseif(empty($o->op) && !$this->get_multivalued()) $sep=' '.strtolower(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','or')).' ';
      else $sep=' '.strtolower(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','and')).' ';
      $vals = array_filter($vals);
      $ret=implode($sep,$vals);
      if (empty($ret)) {
        $ret=$this->getQueryTextOp($o->op,true);
      }
      elseif($o->op == '!=') {
        $ret = $this->getQueryTextOp($o->op,true).$ret;
      }
    }else{
      $d=$this->display($o->value, $options);
      $ret=$this->getQueryTextOp($o->op,true);
      $ret.=$d->text;
    }
    return $ret;
  }
  function getQueryTextOp($op,$addscape=false){
    $ret='';
    if(!empty($op)){
      switch($op){
      case '>':
	$ret=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','more_than').' ';
	break;
      case '>=':
	$ret=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','upper_than').' ';
	break;
      case '<':
	$ret=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','less_than').' ';
	break;
      case '<=':
	$ret=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','lower_than').' ';
	break;
      case '$':
	$ret=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query_ending_with').' ';
	break;
      case '!~':
	$ret=\Seolan\Core\Labels::getTextSysLabel('\Seolan\Core\Field\Field','query_notcontaining').' ';
	break;
      case '^':
	$ret=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query_beginning_with').' ';
	break;
      case '!=':
	$ret=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query_different').' ';
	break;
      case 'regex':
	$ret=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query_regex').' ';
	break;
      case 'noregex':
	$ret=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','query_noregex').' ';
	break;
      case 'is empty':
	$ret=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','is_empty').' ';
	break;
      case 'is not empty':
        $ret=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','is_not_empty').' ';
          break;
	case 'beforenow':
	  $ret = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','past');
	  break;
	case 'afternow':
	  $ret = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','future');
	  break;
      }
    }
    if($addscape && $ret) $ret.=' ';
    return $ret;
  }

  /// Recupere le texte d'une valeur
  public function &toText($r) {
    // Force la creation du html (et potentiellement du text aussi)
    $r->html;
    // Si text n'existe toujours pas, on le créé à partir du HTML
    if(!property_exists($r, 'text') || $r->text===NULL) $r->text=getTextFromHTML($r->html);
    return $r->text;
  }
  // Nettoyage des collections
  public function multiOidPurgeCollections(string $purgedValue, \Seolan\Core\Field\Value $fv){
    if (isset($fv->oidcollection)){
      $i = array_search($purgedValue, $fv->oidcollection);
      array_splice($fv->oidcollection, $i, 1);
      array_splice($fv->collection, $i, 1);
    }
  }
  // Créé le HTML du'un objet de type oid multiple
  public function multiOidHtml($r){
    $r->html='';
    if(is_array($r->collection)) {
      foreach($r->collection as $i=>$o){
	if($i>0 && !empty($o->html)) $r->html.=$this->multiseparator;
	$r->html.=$o->html;
      }
      if(\Seolan\Core\Shell::admini_mode() && !empty($_REQUEST['function']) && $_REQUEST['function'] != 'display') {
        $picto = '<a data-html="true" tabindex="0" role="button" title="'.htmlspecialchars($this->label).'" data-trigger="" data-toggle="popover" data-content="'.htmlspecialchars($r->html).'">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'more', 'csico').'</a>';
        switch($this->browse_format) {
          case 'extract' :
            if(count($r->collection) > 5) {
              $r->html = '';
              foreach($r->collection as $i => $o) {
                if($i < 5) {
                  if($i > 0 && !empty($o->html)) $r->html .= $this->multiseparator;
                  $r->html .= $o->html;
                }
              }
              $r->html = empty($r->html) ? '' : $r->html . $this->multiseparator . $picto;
            }
            break;
          case 'picto' :
            $r->html = empty($r->html) ? '' : $picto;
            break;
        }
      }
    }
    return $r->html;
  }

  // Créé le HTML du'un objet de type oid multiple
  public function multiOidText($r){
    $r->text='';
    if(is_array($r->collection)) {
      foreach($r->collection as $i=>$o){
        $txt=$o->toText();
	if($i>0 && !empty($txt)) $r->text.=$this->multiseparatortext;
        $o->html; // call deferred
	$r->text .= $txt;
      }
    }
    return $r->text;
  }
  /// Répercute une option sur les collections
  protected function multiOidSet($fval, $pname, $pvalue){
    $this->$pname = $pvalue;
    if (is_array($fval->collection))
      foreach($fval->collection as $c){
	$c->fielddef->$pname = $pvalue;
      }
  }
  /// formatte les attributs liés aux patterns html5
  static function getHtmlPattern($field){
    if (empty($field->edit_format))
      return '';
    $fmt = " pattern=\"".htmlentities($field->edit_format, ENT_COMPAT,TZR_INTERNAL_CHARSET)."\"";
    if (!empty($field->edit_format_text))
      $fmt .= " data-pattern-error-message=\"".htmlentities($field->edit_format_text,ENT_COMPAT,TZR_INTERNAL_CHARSET)."\" ";
    return $fmt;
  }
  /**
   * Function isEmpty
   * @return true si le champ n'est pas rempli
   */
  public function isEmpty($r){
    if(property_exists($r, 'raw')) return empty($r->raw);
    else return true;
  }

  /**
   * Function isQueryEmpty
   * @return true si il n'y a pas de recherche en cours sur le champ
   */
  public function isQueryEmpty($query=array(), $isValueEmpty=false){
    $p = new \Seolan\Core\Param($query);
    $field = ($query && count($query['_FIELDS'])) ? array_search($this->field, $query['_FIELDS']) : $this->field;
    $fieldVal = $p->get($field);
    $op = $p->get($field.'_op');
    $hid = $p->get($field.'_HID');
    $empty = $p->get($field.'_empty');

    $isValueArrayEmpty = (is_array($fieldVal) && (!count($fieldVal) || (count($fieldVal) == 1 && (empty($fieldVal[0]) || $fieldVal[0] == 'Foo'))));
    $isValueEmpty = $isValueEmpty || ((empty($fieldVal) || $isValueArrayEmpty) && empty($hid) && empty($empty));
    $isOpEmpty = (empty($op) || !in_array($op, array('is empty', 'now', 'beforenow', 'afternow')));

    return $isValueEmpty && $isOpEmpty;
  }

  /**
   * Retourne la valeur par défaut du champ
   * @return mixed Valeur par défaut du champ
   */
  public function getDefaultValue() {
    return $this->default;
  }

  /**
   * Retourne la chaine à utiliser dans une reqûete SQL représentant par défaut du champ
   * Les string seront entourés de double quotes par exemple
   * @return mixed
   */
  public function getDefaultValueSqlExpression() {
    if (is_string(($default = $this->getDefaultValue()))) {
      if(!empty($default) && $default[0]=='(') return $default; /* si la valeur commence par une ( alors c'est une expression */
      return getDb()->quote($default);
    }
    return $default;
  }
  /**
   * génération de la documentation pour le champ
   */
  public function getDocumentationData(){
    $r = (Object)['field'=>$this->field,
		  'label'=>$this->label,
		  'description'=>$this->acomment,
		  'type'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field',strtolower($this->ftype)),
		  'constraints'=>[],
    ];
    foreach(['RGPD_personalData', 'multivalued','compulsory','published','browsable','queryable','acomment'] as $an){
      $r->{$an} = $this->{$an};
    }
    if($this->multivalued) {
      $r->constraints[] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource', 'multivalued');
    }
    if ($this->compulsory){
      $r->constraints[] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource', 'compulsory');
    }
    if ($this->translatable){
      $r->constraints[] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'translatable');
    }
    $default = $this->getDefaultValue();
    if (isset($default) && !$this->isEmpty($this->_newXFieldValDeferred($default, ($foo=null)))){
      $r->constraints['default'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'default')." : \"$default\"";
    }
    if (\Seolan\Core\Json::hasInterfaceConfig()){
      if(($alias=\Seolan\Core\Json::getFieldAlias(NULL, $this->field, $this->table))!=$this->field) {
        $r->constraints[]='Alias JSON : '.$alias;
      }
    }
    return $r;
  }

  function RGPDCheck(&$report) {
    if($this->RGPD_personalData) {
      $report[]=':) le champ '.$this->label.' contient des données personnelles';
    }
  }
  
  public function isFilterCompulsory($options) {
    if (array_key_exists('fields_complement', $options) && array_key_exists('query_comp_field', $options['fields_complement'])) {
      $clean_field_name = preg_replace('/\[[^\]]*\]/', '', $this->field);
      return $options['fields_complement']['query_comp_field'] === $clean_field_name;
    }
    
    return false;
  }

  public function get_sqlSelectExpr($table=null) {
    return ($table??$this->table).'.'.$this->field;
  }

  /**
   * Vérifie que les propriétes envisagées du champ sont correctes et cohérentes
   * Fait des corrections ou rejette
   */
  public static function fieldDescIsCorrect(&$field,&$ftype,&$fcount,&$forder,&$compulsory,&$queryable,&$browsable,$translatable,&$multivalued,&$published,&$target,&$label,&$options){
    return true;
  }
  /**
   * url de base pour ouvrir la fenêtre de sélection d'un fichier via les modules
   * @param \Seolan\Core\Field\Value $r
   * @param array $contextParameters
   */
  protected function getModulesFilePickerHtml(Value $r, array $addparamspicker=[], array $addparamsbrowse=[]):string{
    
    $paramsbrowse = array_merge(['function'=>'browseFiles',
				 'tplentry'=>'br',
				 'template'=>'Core.source-file-ajax.html',
				 'pagesize'=>20,
				 '_skip'=>1,
				 'selectedprops[published]'=>1,
				 'selectedtypes[]'=>'\Seolan\Field\File\File',
				 'selectedop'=>'OR',
				 '_nohistory'=>1
				 
    ], $addparamsbrowse);
    
    $browsebaseurl =$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,true).http_build_query($paramsbrowse);
    
    $paramspicker = array_merge(['moid'=>\Seolan\Core\Module\Module::getMoid(XMODADMIN_TOID),
				 'function'=>'modulesList',
				 'tplentry'=>'mod',
				 '_skip'=>1,
				 '_modaltitle'=>$this->label,
				 'template'=>'Core.source-file.html',
				 'recipientid'=>$r->varid,
				 '_nohistory'=>1,
				 'browsebaseurl'=>$browsebaseurl
      
    ], $addparamspicker);
    return $GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,true).http_build_query($paramspicker);
  }
}
