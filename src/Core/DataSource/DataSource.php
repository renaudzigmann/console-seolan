<?php
  /// Classe de base pour la gestion des sources de donnees
namespace Seolan\Core\DataSource;

class DataSource implements \Seolan\Core\Module\ConnectionInterface {
  static $_sources;
  static $langfields = ['LANGREPLI', 'LANG'];
  protected $base = NULL;
  protected $boid = NULL;
  protected $title=NULL;
  protected $translatable=false;
  protected $log=1;
  /** @var \Seolan\Core\Field\Field[] */
  public $desc = [];
  public $orddesc = [];
  private static $_factory=[];
  private static $_factory_by_sourcepath=[];
  protected static $_basebase=NULL;
  protected static $_boid=[];
  public $published_fields_in_admin=2;
  public $sendacopytofields=[];
  protected $_published_fields=[];
  
  /// construction d'une source de donnee avec un nom de table (legacy) ou un boid
  function __construct($boid=0) {
    \Seolan\Core\DataSource\DataSource::preLoadBaseBase();
    if(!empty($boid)) {
      $this->boid=$boid;
      $this->base=\Seolan\Core\DataSource\DataSource::$_boid[$boid]->BTAB;
      $this->initOptions();

      $param=\Seolan\Core\Options::decode(\Seolan\Core\DataSource\DataSource::$_boid[$this->boid]->BPARAM); 
      if (TZR_LANG_BASEDLANG == \Seolan\Core\DataSource\DataSource::$_boid[$this->boid]->TRANSLATABLE && 1 == \Seolan\Core\DataSource\DataSource::$_boid[$this->boid]->AUTO_TRANSLATE){
	$this->setLangRepliOpts();
      }
      $this->_options->setValues($this,$param);
    } else {
      \Seolan\Core\Logs::critical('\Seolan\Core\DataSource\DataSource::construct', '<'.$boid.'> is empty');
      return NULL;
    }
  }
  function __wakeup(){
    \Seolan\Core\Labels::loadLabels('Seolan_Core_DataSource_DataSource');
  }

  /// Initialise les options de la source
  public function initOptions() {
    if(empty($this->_options)) $this->_options=new \Seolan\Core\Options();
    $this->_options->setId($this->boid);
  }

  /// Supprime le cache des datasources
  static public function clearCache(){
    \Seolan\Core\DataSource\DataSource::preLoadBaseBase(true);
    \Seolan\Core\DataSource\DataSource::$_factory=[];
    \Seolan\Core\DataSource\DataSource::$_factory_by_sourcepath=[];
  }

  /// chargement en mémoire de la liste des tables et les libellés associés
  static function preLoadBaseBase($refresh=false) {
    if(empty(\Seolan\Core\DataSource\DataSource::$_basebase) || $refresh) {
      \Seolan\Core\Logs::debug('\Seolan\Core\Shell::loadBaseBase: start');
      $lang = \Seolan\Core\Shell::getLangUser();
      $rs=getDB()->fetchAll('select BASEBASE.*,AMSG.* from BASEBASE,AMSG where BASEBASE.BOID=AMSG.MOID and MLANG=? AND MTXT != ?',
			    [$lang,'']);
      \Seolan\Core\DataSource\DataSource::$_basebase=[];
      \Seolan\Core\DataSource\DataSource::$_boid=[];

      $monolang = \Seolan\Core\Shell::getMonoLang();

      foreach($rs as $idx => $o1) {
	if ($monolang)
	  $o1['TRANSLATABLE'] = 0;
	\Seolan\Core\DataSource\DataSource::$_basebase[$o1['BTAB']]=(object)$o1;
	\Seolan\Core\DataSource\DataSource::$_boid[$o1['BOID']]=&\Seolan\Core\DataSource\DataSource::$_basebase[$o1['BTAB']];
      }
      unset($rs);
      // Chargement des tables qui ne sont pas traduites
      if($lang!=TZR_DEFAULT_LANG) {
	$rs=getDB()->fetchAll('select BASEBASE.*,AMSG.* from BASEBASE,AMSG where BASEBASE.BOID=AMSG.MOID and MLANG=?',
			      [TZR_DEFAULT_LANG]);
	foreach($rs as $ors) {
	  if(empty(\Seolan\Core\DataSource\DataSource::$_basebase[$ors['BTAB']])) {
	    \Seolan\Core\DataSource\DataSource::$_basebase[$ors['BTAB']]=(object)$ors;
	    \Seolan\Core\DataSource\DataSource::$_boid[$ors['BOID']]=&\Seolan\Core\DataSource\DataSource::$_basebase[$ors['BTAB']];
	  }
	}
	unset($rs);
      }
      \Seolan\Core\Logs::debug('\Seolan\Core\Shell::loadBaseBase: end');
    }
  }

  /**
   * Creation d'un objet de type xdatasource via le boid
   * @param string $boid
   * @return \Seolan\Core\DataSource\DataSource
   */
  public static function objectFactory8($boid, ?string $bclass=null) {
    \Seolan\Core\Logs::debug('\Seolan\Core\DataSource\DataSource::objectfactory8 boid='.$boid.' bclass='.$bclass);
    if(!preg_match('/^[0-9a-f]+$/',$boid)) \Seolan\Library\Security::alert('\Seolan\Core\DataSource\DataSource::objectFactory8: Trying to get unauthorized <'.$boid.'>');

    \Seolan\Core\DataSource\DataSource::preLoadBaseBase();
    if(empty(\Seolan\Core\DataSource\DataSource::$_boid[$boid])) \Seolan\Core\DataSource\DataSource::preLoadBaseBase(true);
    if(empty(\Seolan\Core\DataSource\DataSource::$_boid[$boid])) return NULL;
    if ($bclass != null){
      $c = $bclass;
    } else {
      $c=\Seolan\Core\DataSource\DataSource::$_boid[$boid]->BCLASS;
    }
    if(!class_exists($c)) {
      \Seolan\Core\Logs::notice('\Seolan\Core\DataSource\DataSource::objectfactory8','class '.$c.' doesn\'t exist');
      $c='\Seolan\Model\DataSource\Table\Table';
    }
    $cacheid = $c.$boid;
    if(empty(\Seolan\Core\DataSource\DataSource::$_factory[$cacheid])) {
      \Seolan\Core\Logs::debug($c.'::objectFactory8 read from memcached '.$cacheid);
      \Seolan\Core\DataSource\DataSource::$_factory[$cacheid]=\Seolan\Library\ProcessCache::getFromMemcached('datasource-'.$cacheid);
      if(!\Seolan\Core\DataSource\DataSource::$_factory[$cacheid]){
        \Seolan\Core\Logs::debug($c.'::objectFactory8 creating '.$c.' '.$boid);
        \Seolan\Core\DataSource\DataSource::$_factory[$cacheid]=new $c($boid);
        \Seolan\Library\ProcessCache::setToMemcached('datasource-'.$cacheid,\Seolan\Core\DataSource\DataSource::$_factory[$cacheid]);
      }
    }
    return \Seolan\Core\DataSource\DataSource::$_factory[$cacheid];
  }

  /**
   * Creation d'un objet de type xdatasource via ses caratéristiques
   * Ex de sourcepath : BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_MLOGS pour recuperer la source de type XDSTable sur la table _MLOGS
   * @return DataSource
   */
  public static function objectFactoryHelper8($sourcepath) {
    if(isset(\Seolan\Core\DataSource\DataSource::$_factory_by_sourcepath[$sourcepath]))
      return \Seolan\Core\DataSource\DataSource::$_factory_by_sourcepath[$sourcepath];
    \Seolan\Core\DataSource\DataSource::preLoadBaseBase();
    parse_str($sourcepath,$output);
    $c=@$output['BCLASS'];
    $specs=@$output['SPECS'];
    if (empty($specs))
      $specs = $sourcepath;
    if(\Seolan\Core\Kernel::isAKoid($specs)) $specs=\Seolan\Core\Kernel::getTable($specs);

    $boid=\Seolan\Core\DataSource\DataSource::getBoidFromSpecs($c, $specs);

    if(empty($boid)) {
      \Seolan\Core\Logs::critical('XDataSource "'.$specs.'" not exists ' . backtrace2());
      return NULL;
    }
    return \Seolan\Core\DataSource\DataSource::$_factory_by_sourcepath[$sourcepath] = \Seolan\Core\DataSource\DataSource::objectFactory8($boid, $c);
  }
  static function getBoidFromSpecs($class, $spec) {
    if(empty(\Seolan\Core\DataSource\DataSource::$_basebase[$spec])) \Seolan\Core\DataSource\DataSource::preLoadBaseBase(true);
    if(empty(\Seolan\Core\DataSource\DataSource::$_basebase[$spec])) return NULL;
    return \Seolan\Core\DataSource\DataSource::$_basebase[$spec]->BOID;
  }
  static function sysTable($table) {
    return in_array($table,['USERS','GRP','TEMPLATES','LOGS','TASKS','LETTERS','REPLI','JOURNAL','OPTS', '_BATCH', TZR_TABLE_COMMENT_NAME]);
  }
  static function notToReplicate($table) {
    return in_array($table,['TASKS','REPLI','JOURNAL']);
  }


  static function tablesLogStatus() {
    \Seolan\Core\DataSource\DataSource::preLoadBaseBase(true);
    $tablesLogStatus = [];
    foreach (\Seolan\Core\DataSource\DataSource::$_basebase as $tableName => $tableObject)
      $tablesLogStatus[$tableName] = ($tableObject->LOG == 1 && $tableName != 'LOGS');
    return $tablesLogStatus;
  }

  public function &actionlist1() {
    $actions = [];
    $this->_actionlist($actions);
    return $actions;
  }

  // rend la liste des champs browsables
  //
  function browsableFields($selectedfields=NULL) {
    $fields=[];
    foreach($this->desc as $k => &$v) {
      if(is_object($v) && $v->get_browsable() || ($selectedfields=='all')) {
	$fields[]=$k;
      }
    }
    return $fields;
  }

  /// Transforme le résultat d'un formulaire en object requête
  function captureQuery($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, [], "all",
                              ['pagesize'=>[FILTER_VALIDATE_INT,[]],
			       '_langstatus'=>null,
			       'order'=>[FILTER_CALLBACK,['options'=>'containsNoSQLKeyword']]]);
    $options=$p->get('options');
    $_FIELDS=$p->get('_FIELDS');
    $st=[];
    $st['_date']=time();
    $st['_table']=$this->base;
    $st['options']=$p->get('options');
    $st['oids']=$p->get('oids');
    if (!is_array($st['oids'])) {
      $st['oids'] = preg_split("/[ ,;'\"\|\n\r]+/", $st['oids'], 0, PREG_SPLIT_NO_EMPTY);
    }
    $st['operator']=$p->get('operator');
    $st['_select']=$p->get('_select');
    $st['pagesize']=$p->get('pagesize');
    $st['_langstatus'] = $p->get('_langstatus');
    $st['order']=[];
    if(!empty($_FIELDS)) {
      $st['_querymode']='query2';
      $st['_FIELDS']=$_FIELDS;
    }else{
      $st['_FIELDS']=[];
      foreach($this->desc as $k=>&$v) $_FIELDS[$k]=$k;
    }
    foreach($_FIELDS as $field=>$k) {
      $v=&$this->desc[$k];
      if(is_object($v)) {
	if(!$v->get_queryable()) continue;
	$v1=$p->get($field);
	$st[$field]=$v1;
	$st[$field.'_op']=$p->get($field.'_op');
	$st[$field.'_HID']=$p->get($field.'_HID');
	$st[$field.'_FMT']=$p->get($field.'_FMT');
	$st[$field.'_PAR']=$p->get($field.'_PAR');
	$st[$field.'_empty']=$p->get($field.'_empty');
      }
    }
    $order=$p->get('order');
    $_order=$p->get('_order');
    if(is_array($order)) {
      foreach($order as $i=>$f) {
	if(!empty($f)) $st['order'][]=trim($order[$i].' '.$_order[$i]);
      }
    }
    return $st;
  }

  /// prend en entrée une requête stockée ($qinit) et génére les paramètres d'entrée
  public function prepareQuery(&$qinit, $storedname=NULL) {
    // table dans laquelle se trouvent les donnees
    if(!empty($storedname)) {
      $ors=getDB()->fetchRow('select * from QUERIES where KOID = ? and LANG=?',[$storedname,TZR_DEFAULT_LANG]);
      if($ors) {
	// fusion du contenu de la recherche sauevardee et de la recherche en cours.
	// on prend en priorite le contenu de la requete sauvegardee, qinit sinon
	$q1 = unserialize(stripslashes($ors['query']));
	$q2=$q1;
	foreach($qinit as $k=>$v) {
	  if(!empty($qinit[$k])) $q2[$k]=$v;
	}
	$qinit=$q2;
      }
    }
    $selectedfields=[];
    $r=NULL;
    $r['operator']=@$qinit['operator'];
    if(empty($r['operator'])) $r['operator']='and';
    $parametrized=false;
    $labelin=@$qinit['labelin'];
    if(is_array($qinit)) {
      foreach($this->desc as $k => &$v) {
	if(!empty($qinit[$k.'_PAR'])) {
	  $parametrized=true;
	  $selectedfields[]=$k;
	  unset($qinit[$k]);
	  unset($qinit[$k.'_op']);
	  unset($qinit[$k.'_HID']);
	  unset($qinit[$k.'_PAR']);
	  unset($qinit[$k.'_FMT']);
	  if(!empty($labelin)) $qinit['options'][$k]['labelin']=true;
	}
      }

      if(!empty($selectedfields)) {
	$r=$this->query(['tplentry'=>TZR_RETURN_DATA,'selectedfields'=>$selectedfields,
			 'searchmode'=>'simple','_preparedquery'=>$qinit, 'fmoid' => $qinit['fmoid']]);
      }
    }
    $r['parametrized']=$parametrized;
    return $r;
  }



  /// rend vrai si la base existe
  public static function sourceExists($t,$refresh=false) {
    \Seolan\Core\DataSource\DataSource::preLoadBaseBase($refresh);
    return isset( \Seolan\Core\DataSource\DataSource::$_basebase[$t] );
  }

  /// rend le libelle de la table
  public function getLabel() {
    return $this->title;
  }

  /// Rend la table de la source
  public function getTable() {
    return $this->base;
  }
  /// Rend le boid de la source
  function getBoid(){
    return $this->boid;
  }
  /// Rend le nom de la source
  function getSourceName(){
    return \Seolan\Core\DataSource\DataSource::$_boid[$this->boid]->MTXT;
  }
  /// Rend un propriété d'une source via la BOID
  static function getBoidProp($boid,$prop){
    return \Seolan\Core\DataSource\DataSource::$_boid[$boid]->$prop;
  }
  /// Rend un propriété d'une source via le nom de la table
  static function getTableProp($table,$prop) {
    return \Seolan\Core\DataSource\DataSource::$_basebase[$table]->$prop;
  }

  /// rend la liste des champs publié. Attention ils sont gardés en cache dans l'objet pour ne pas les recalculer à chaque fois
  /// si $field==true on rend les champs, sinon les noms de champs
  public function getPublished($field=true) {
    if(!isset($this->_published_fields[$field])) {
      $links=[];
      foreach($this->desc as $i => $v) {
	if(is_object($v) && $v->get_published()) {
	  if($v->get_published()){
	    if($field) $links[]=$v->get_field();
	    else $links[]=$i;
	  }
	}
      }
      $this->_published_fields[$field]=$links;
    }
    return $this->_published_fields[$field];
  }

  /// return all tag fields
  public function &getTagFields() {
    $tagfields = [];
    foreach($this->desc as $i => &$v) {
      if ($v->ftype == '\Seolan\Field\Tag\Tag') {
        $tagfields[$v->field] = 1;
      }
    }
    return $tagfields;
  }

  /// return all fields with user tags enabled
  public function getUserTagFields($fields=NULL){
    $fields=$this->orddesc;
    $tagfields=[];
    foreach($fields as $i=>$f) {
      if(!isset($this->desc[$f])) continue;
      if (isset($this->desc[$f]->enabletags) && $this->desc[$f]->enabletags)
        $tagfields[$f] = 1;
    }
    return $tagfields;
  }

  public function isTranslatable() {
    return ($this->translatable>0);
  }
  public function getTranslatable() {
    return $this->translatable;
  }
  public function toLog() {
    return ($this->base!='LOGS' && $this->log==1);
  }

  public function getAutoTranslate() {
    return $this->autotranslate;
  }

  /// genere les traductions dans toutes les langues
  function autoTranslate() {
    if($this->isTranslatable()) {
      $xk=new \Seolan\Core\Kernel;
      $rs=getDB()->fetchCol('SELECT DISTINCT KOID FROM '.$this->base.' where LANG=?',[TZR_DEFAULT_LANG]);
      foreach($rs as $oid) $xk->data_autoTranslate($oid);
      unset($rs);
    }else{
      $toclean = getDB()->select('select count(*) from '.$this->base.' where LANG!=?',[TZR_DEFAULT_LANG])->fetch(\PDO::FETCH_COLUMN);
      if ($toclean)
        getDB()->execute('delete from '.$this->base.' where LANG!=?',[TZR_DEFAULT_LANG]);
    }
  }

  public static function &getBaseList($myselfIncluded=true,$refresh=false) {
    \Seolan\Core\DataSource\DataSource::preLoadBaseBase($refresh);
    $liste = [];
    foreach(\Seolan\Core\DataSource\DataSource::$_basebase as $b=>$o) {
      $liste[$b]=$o->MTXT;
    }
    asort($liste);
    return $liste;
  }

  public static function &getBaseList8($myself=true){
    \Seolan\Core\DataSource\DataSource::preLoadBaseBase();
    $liste=[];
    foreach(\Seolan\Core\DataSource\DataSource::$_boid as $b=>$o){
      if($myself || (!$myself && (empty($this) || $this->boid!=$b)))
        $liste[$b]=$o->MTXT;
    }
    asort($liste);
    return $liste;
  }

  /// Créer une boite de selection de champ
  function fieldSelector($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, ['tplentry'=>'','compulsory'=>true,'filter'=>[]]);
    $fieldname=$p->get('fieldname');
    $value=$p->get('value');
    $type=$p->get('type');
    if(!is_array($type) && !empty($type)) $type=[$type];
    $compulsory=$p->get('compulsory');
    $multivalued=$p->get('multivalued');
    $filter=$p->get('filter','local');
    if($multivalued) $retval='<select name="'.$fieldname.'[]" size="6" multiple style="resize:both">';
    else $retval='<select name="'.$fieldname.'">';
    if(!$compulsory) $retval.='<option value="">----</option>';
    foreach($this->desc as $k => &$v) {
      if(empty($type) || in_array($v->get_ftype(),$type)){
        $ok=true;
        foreach($filter as $prop=>$val){
	  if(is_array($val)){
	    if (is_callable($val[0])){
	      $f = $val[0];
	      if (!$f($v, $prop, $val[1])){
		$ok = false;
		break;
	      }
	    } else {
	      if(!eval('return (\''.$val[1].'\''.$val[0].'\''.$v->$prop.'\');' ) ){
		$ok=false;
		break;
	      }
	    }
	  }else{
            if($v->$prop!=$val){
              $ok=false;
              break;
            }
          }
        }
        if($ok) $retval.='<option value="'.$k.'" '.(isset($value) && ($value==$k || is_array($value) && in_array($k,$value))?'selected':'')." >". $v->get_label().' ('.$k.')</option>';
      }
    }
    $retval.='</select>';
    return $retval;
  }

  function order_selector($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>'','compulsory'=>true,'random'=>true,'multiple'=>false]);
    $fieldname=$p->get('fieldname');
    $value=$p->get('value');
    if(isset($value) && !is_array($value)) $value=[$value];
    $random=$p->get('random');
    $compulsory=$p->get('compulsory');
    $multiple=$p->get('multiple');
    $retval="<select name=\"$fieldname\"".($multiple?' multiple size="6"':'').'>';
    if(!$compulsory) $retval.='<option value="">---</option>';
    if($random) $retval.='<option value="RAND()">'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','random').'</option>';

    foreach($this->desc as $k => $v) {
      $retval.='<option value="'.$k.'" '.(isset($value) && in_array($k,$value)?'selected':'').
	' >'. $v->get_label().'</option>';
    }
    $retval.='</select>';
    return $retval;
  }


  public function generateOrderRegexp() {
    $fields = $this->getFieldsList();
    $regexp='@^('.implode('|',$fields).'|DESC|ASC|RAND|FIELD|[\(\) 0-9]+)*$@i';
    return $regexp;
  }

  /// rend le descriptif d'un champ
  public function getField($f) {
    return $this->desc[$f];
  }

  /// Rend la liste des champs de la table filtres par type et par propriétés
  public function &getFieldsList($type=NULL,$browsable=false,$published=false,$queryable=false,$compulsory=false,$translatable=false,
				 $multivalued=false,$fields=null,$op='AND',$except=NULL) {
    if(!is_array($fields) || empty($fields)) $fields=$this->orddesc;
    $list=[];
    $checktype=is_array($type) && !empty($type);
    
    foreach($fields as $f) {
      if(empty($this->desc[$f]) || ($except && in_array($f,$except)) ) continue;
      $v= $this->desc[$f];
      if($v->hidden && !\Seolan\Core\Shell::isRoot()) continue;
      if($op=='AND' && (!$checktype || in_array($v->get_ftype(),$type)) && (!$browsable || $v->get_browsable()) && (!$published || $v->get_published()) && (!$queryable || $v->get_queryable()) && (!$compulsory || $v->get_compulsory()) && (!$translatable || $v->get_translatable()) && (!$multivalued || $v->get_multivalued()))
	$list[]=$f;
      elseif($op=='OR' && (($checktype && in_array($v->get_ftype(),$type)) || ($browsable && $v->get_browsable()) || ($published && $v->get_published()) || ($queryable && $v->get_queryable()) || ($compulsory && $v->get_compulsory()) || ($translatable && $v->get_translatable()) || ($multivalued && $v->get_multivalued())))
	$list[]=$f;
    }
    return $list;
  }
  /// Rend les champs indexables
  function getIndexablesFields($fields=NULL){
    if(!is_array($fields) || empty($fields)) $fields=$this->orddesc;
    $list=[];
    foreach($fields as $f) {
      if(!isset($this->desc[$f])) continue;
      if (isset($this->desc[$f]->indexable) && $this->desc[$f]->indexable == 1)
	$list[] = $f;
    }
    return $list;
  }
  /// Rend le nombre de champ non traduisible
  function getNonTranslatableFieldCount() {
    if (!isset($this->_NonTranslatableFieldCount))
      $this->_NonTranslatableFieldCount = getDB()->count('select count(*) from DICT where DTAB=? and TRANSLATABLE!=?', [$this->base,1]);
    return $this->_NonTranslatableFieldCount;
  }

  /// Fabrique une condition pour une recherche
  function make_cond($def, $v){
    return '';
  }
  function make_simple_cond($k, $v) {
    return '';
  }

  /// generation d'une condition utilisable sur le container
  function select_query($args=NULL) {
    return '';
  }

  /// Retourne une requete pour retourner des objets aléatoires
  function random_select_query($args=NULL) {
    return '';
  }

  // Fonction reservee a la creation d'une data pour la langue par defaut.
  // La creation d'une data dans tout autre code langue se fait par \Seolan\Core\Kernel::data_edit() et \Seolan\Core\Kernel::proc_data_edit()
  // apres avoir cree une data en l'initialisant avec la data correspondante dans le code langue par defaut.
  //
  public function input($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>$this->base,'hiddenfields'=>[],'options'=>[],'fieldssec'=>[]]);
    $tplentry = $p->get('tplentry');
    $_mapping = $p->get('_mapping','local');
    $options = $p->get('options');
    $translatable= $this->getTranslatable();
    $fieldssec=$p->get('fieldssec','local');
    $moid=$p->get('fmoid','local');
    $selectedfields=$p->get('selectedfields');
    $editbatch=$p->get('editbatch');
    if(empty($selectedfields) || (is_string($selectedfields) && ($selectedfields=='all'))) $all=true;
    else $all=false;
    if(empty($selectedfields)) $selectedfields=[];

    $lang=\Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));

    if(!isset($ar['_prepareMultiEdit'])
       && ($translatable!=3) && $this->isTranslatable() && ($lang!=TZR_DEFAULT_LANG)) {
      if($tplentry!=TZR_RETURN_DATA)
        \Seolan\Core\Shell::redirect2error(['message'=>'Security violation']);
      return;
    }
    
    $ofieldvalue=[];
    $fieldequiv = [];
    foreach($this->desc as $k => $v) {
      if(!empty($fieldssec[$k]) && $fieldssec[$k]!='rw') continue;
      if(!\Seolan\Core\Shell::isRoot() && ($v->readonly || $v->hidden)) continue;
      if(!($all || in_array($k,$selectedfields))) continue;
      $origk=$k;
      if(!empty($_mapping[$k])) $k=$_mapping[$k];
      $options[$k]['fmoid']=$moid;
      $options[$k]['editbatch']=$editbatch;
      $defval=$v->getDefaultValue();
      if(!$v->sys) {
	$ofieldvalue[]=$result['o'.$k]=$v->input($defval, $options[$k]);
        $fieldequiv[]=$origk;
      } else {
	if($k=='PUBLISH' || $k=='PRP' || $k == 'LANGREPLI' || $k=='APP'){
	  $value=$v->input($defval, $options[$k]);
	  $result['o'.$k]=$value;
	  $fieldequiv[]=$origk;
	}
      }
    }
    $result['fields_object']= $ofieldvalue;
    $result['tablelabel']=$this->getLabel();

    // recherche des groupes de champs
    $result['_groups'] = $this->fieldsGroups($result, $fieldequiv, $_mapping);

    // Information sur le caractere 'translatable' ou non de toute la table
    $result['translatable'] = $this->isTranslatable();

    return \Seolan\Core\Shell::toScreen1($tplentry, $result);
  }

  /// génération de l'écran de recherche
  public function query($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, [	'tplentry'=>$this->base,'selectedfields'=>'','searchmode'=>'advanced','querymode'=>'query',
					'operator'=>'AND','genoptions'=>[],'fieldssec'=>[]]);
    $tplentry = $p->get('tplentry');
    $fieldssec=$p->get('fieldssec','local');
    $fmoid = $p->get('fmoid','local');
    $persistent=$p->get('_persistent');
    $selectedfields=$p->get('selectedfields');
    if(empty($selectedfields) || (is_string($selectedfields) && ($selectedfields=='all'))) $all=true;
    else $all=false;
    if(empty($selectedfields)) $selectedfields=[];
    $genoptions=[];
    $selectquery='';

    // on a une requete preparee qui contraint le formulaire
    $preparedquery=$p->get('_preparedquery');
    if(!empty($preparedquery)) {
      $preparedquery['getselectonly']=true;
      $preparedquery['_options']=['local'=>1];
      // on construit la requete sql contraignante
      $selectquery=self::procQuery($preparedquery);
      if (isset($preparedquery['options']))
        $genoptions=$preparedquery['options'];
      elseif (isset($preparedquery['genoptions']))
        $genoptions=$preparedquery['genoptions'];
    }
    if ($p->is_set('options'))
      $genoptions=array_merge($genoptions,$p->get('options'));
    elseif ($p->is_set('genoptions'))
      $genoptions=array_merge($genoptions,$p->get('genoptions'));
    $searchmode=$p->get('searchmode');
    $mode=$p->get('querymode');
    $myi=0;
    $result=[];
    $fieldpar=[];
    foreach($this->orddesc as $field){
      if(!empty($fieldssec[$field]) && $fieldssec[$field]=='none') continue;
      $v=&$this->desc[$field];
      if(!($v->get_queryable() && ($all || in_array($field,$selectedfields)))) continue;
      if(!$persistent){
	if(!empty($genoptions[$field]['value'])) {
	  $initval=$genoptions[$field]['value'];
	  if(!is_array($initval)) $initval=[$initval];
	} else {
	  $initval=$p->get($field);
	  if(!is_array($initval)){
	    $initval=trim($initval);
	    if(!empty($initval)) $initval=[$initval];
	  }
	}
      }else{
	$initval=null;
      }
      if(!empty($genoptions[$field])) $opts1=$genoptions[$field];
      else $opts1=[];
      // Mode de recherche simple ou avancé
      if (empty($opts1['searchmode'])) $opts1['searchmode']=$searchmode;
      // Requete restrictive sur les valeurs du filtre
      if (empty($opts1['select']) && $selectquery) $opts1['select']=$selectquery;
      $opts1['fmoid']=$fmoid;
      if(empty($opts1['op'])) $opts1['op']=$p->get($field.'_op');
      if($mode=='query2') $opts1['fieldname']='fieldxidxid';
      if($mode=='pquery') $ofieldvalue[$myi]=$v->pquery($initval, $opts1);
      else $ofieldvalue[$myi]=$v->query($initval, $opts1);
      if($mode=='query2') $ofieldvalue[$myi]->html.='<input type="hidden" name="_FIELDS[fieldxidxid]" value="'.$field.'">';
      $result['o'.$field]=&$ofieldvalue[$myi];
      $fieldpar[$myi]=@$genoptions[$field]['par'];
      $fieldname[$myi]=$v->get_label();
      $myi++;
    }
    if($mode=='query2') array_multisort($fieldname,SORT_STRING,$fieldpar,$ofieldvalue);
    $result['operator']=$p->get('operator');
    $result['fields_object']=&$ofieldvalue;
    $result['fields_par']=$fieldpar;
    return \Seolan\Core\Shell::toScreen1($tplentry, $result);
  }

  /// Génération de l'écran de recherche rapide
  public function quickquery($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,['tplentry'=>$this->base,'selectedfields'=>'','genoptions'=>[], '_langstatus'=>null]);
    $tplentry=$p->get('tplentry');
    $fields=$p->get('selectedfields');
    $fieldssec = $p->get('fieldssec','local');
    $fmoid=$p->get('fmoid','local');
    $persistent=$p->get('_persistent');
    if(!empty($fields) && is_array($fields)) $selectedfields=$fields;
    else $selectedfields=self::browsableFields($fields);
    $genoptions=$p->get('genoptions');
    if($p->is_set('options')) $genoptions=$p->get('options');
    $myi=0;
    $result=[];
    $ofieldvalue=[];
    $fieldpar=[];
    $fieldname=[];
    foreach($this->orddesc as $field){
      $v=$this->desc[$field];
      if($v->get_queryable() && in_array($field,$selectedfields) && !(@$fieldssec[$field]=='none')) {
	$fieldname[$myi]=$v->get_label();
	if(!$persistent){
	  if(!empty($genoptions[$field]['value'])) {
	    $initval=$genoptions[$field]['value'];
	    if(!is_array($initval)) $initval=[$initval];
	  } else {
	    $initval=$p->get($field);
	    if(!is_array($initval)){
	      $initval=trim($initval);
	      if(!empty($initval)) $initval=[$initval];
	    }
	  }
	}else{
	  $initval=NULL;
	}
	if(empty($genoptions[$field])) $genoptions[$field]=[];
	$genoptions[$field]['fmoid']=$fmoid;
	$genoptions[$field]['op']=$p->get($field.'_op');
	$genoptions[$field]['fields_complement'] = $ar;
	$ofieldvalue[$myi]=$v->quickquery($initval, $genoptions[$field]);
	$result['o'.$field]=$ofieldvalue[$myi];
	$ftable['o'.$field]=$ofieldvalue[$myi];
	$fieldpar[$myi]=@$genoptions[$field]['par'];
	$myi++;
      }
    }
    if (\Seolan\Core\Shell::isRoot()) {
      $oids = $p->get('oids')??[];
      if (!is_array($oids))
	$oids =   preg_split("/[ ,;'\"\|\n\r]+/", $oids, 0, PREG_SPLIT_NO_EMPTY);
      $ftable[] = (object) [
        'fielddef' => (object) ['label' => \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','koid_label')],
        'field' => 'oids',
        'html' => '<textarea name="oids">' . implode(',', $oids) . '</textarea>'];
      $result['_oids']=$oids;
    }
    $result['operator']=$p->get('operator');
    $result['fields_object']=$ofieldvalue;
    $result['fields_par']=$fieldpar;
    $result['_langstatus'] = $p->get('_langstatus');
    if(!empty($ftable)) $result['fields_ftable']=$ftable;
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }

  /// Verifie si l'oid peut etre traité par la source
  function checkOID($oid,&$ar,$f,$return=false){
    if (!$oid)
      \Seolan\Library\Security::alert('\Seolan\Core\DataSource\DataSource::'.$f.': an oid ('.$oid.') is required');

    if(\Seolan\Core\Kernel::getTable($oid)!=$this->base){
      if($return) return false;
      else \Seolan\Library\Security::alert('\Seolan\Core\DataSource\DataSource::'.$f.': Trying to use '.$oid.' with wrong \Seolan\Core\DataSource\DataSource <'.$this->base.'>');
    }
    return true;
  }

  /// Génère un oid pour la source
  function getNewOID($ar=NULL){
    return self::getNewBasicOID($this->base);
  }
  /// Génère un oid au format par defaut pour une table donnée (généré à partir du timestamp avec les µs et d'un nombre aléatoire, chacun converti en base 36)
  static function getNewBasicOID($t){
    $i = 0;
    while ($i++ < 100) {
      $addr=base_convert(rand(1,32000),10,36);
      $addr.= base_convert(str_replace('.','',microtime(true)),10,36);
      $newoid=substr($t.':'.$addr,0,40);
      /* on verifie si le koid n'existe pas deja */
      $cnt=getDB()->count('select COUNT(KOID) from '.$t.' where KOID=?', [$newoid], false);
      if(!$cnt) return $newoid;
    }
    bugWarning("\Seolan\Core\DataSource\DataSource::getNewBasicOID($t) cannot find new oid");
  }
  /// Génère un oid au format spécifique pour une table donnée (simple raccourci pour ne pas instancier soit meme la source pour générer un oid)
  static function getNewSpecificOID($t,$ar=NULL){
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$t);
    return $xset->getNewOID($ar);
  }

  /// Generation d'un ecran d'edition
  function edit($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,['tplentry'=>$this->base,'options'=>[],'selectedfields'=>[],'fieldssec'=>[],'accesslog'=>0, 'numberOfColumns'=>1]);
    $_mapping = $p->get('_mapping','local');
    $accesslog=$p->get('accesslog');
    $tplentry = $p->get('tplentry');
    $fieldssec = $p->get('fieldssec','local');
    $moid=$p->get('fmoid','local');
    $maxNbOfCols=$p->get('numberOfColumns','local');
    $oid=$p->get('oid');
    \Seolan\Core\Logs::debug('\Seolan\Core\DataSource\DataSource::edit('.$oid.')');
    $this->checkOID($oid,$ar,'edit');
    $options=$p->get('options');
    if($xlock=\Seolan\Core\Shell::getXModLock()) {
      $mode=$p->get('_mode');
      if($mode=='lock') {
	$moidlock = $p->get('_moidlock');
	$xlock->lock($oid, TZR_DEFAULT_LANG, \Seolan\Core\User::get_current_user_uid(), null, $moidlock);
	$xlock->initLocks();
      }
    }
    $selectedfields=$p->get('selectedfields');
    if(!is_array($selectedfields)) {
      unset($selectedfields);
      $selectedfields=[];
    }
    $translatable=$this->getTranslatable();
    $LANG_DATA=$p->get('LANG_DATA');
    if(!$translatable) $LANG_DATA=TZR_DEFAULT_LANG;
    else $LANG_DATA=\Seolan\Core\Shell::getLangData($LANG_DATA,true);
    $LANG_TRAD = \Seolan\Core\Shell::getLangTrad($p->get('LANG_TRAD'));

    $result=[];
    if (!empty($LANG_TRAD) && ($LANG_TRAD!=$LANG_DATA)){
      $result['translation_ok'] = 1;
    }
    if(($translatable!=TZR_LANG_FREELANG) && !empty($LANG_TRAD) && ($LANG_TRAD!=$LANG_DATA)) {
      $ar1=$ar;
      $ar1['LANG_DATA']=$LANG_TRAD;
      $ar1['tplentry']=TZR_RETURN_DATA;
      $result['d']=self::display($ar1);
    }
    $ors=getDB()->fetchRow('select '.$this->get_sqlSelectFields('*').' from '.$this->base.
			   ' where KOID=? and LANG=?',[$oid,$LANG_DATA]);
    // si on ne trouve pas dans la langue en cours on regarde dans la langue de base
    if(!$ors) {
      $ors=getDB()->fetchRow('select '.$this->get_sqlSelectFields('*').' from '.$this->base.
			     ' where KOID=? and LANG=?',[$oid, TZR_DEFAULT_LANG]);
      $result['translation_ok'] = 0;
    }


    $myi=0;
    foreach($this->desc as $k=>$v) {
      if(!empty($selectedfields) && !in_array($k, $selectedfields)) continue;
      if($v->hidden && !\Seolan\Core\Shell::isRoot()) continue;
      $origk=$k;
      if(!empty($_mapping[$k])) $k=$_mapping[$k];
      $options[$k]['oid']=$oid;
      $options[$k]['fmoid']=$moid;
      if(empty($fieldssec[$k])) {
	if(@$fieldssec['*']=='ro') $f='display';
	else $f='edit';
      }
      elseif($fieldssec[$k]=='rw') $f='edit';
      elseif($fieldssec[$k]=='ro') $f='display';
      else continue;
      if($LANG_DATA!=TZR_DEFAULT_LANG && $translatable!=TZR_LANG_FREELANG && !$v->get_translatable() && $this->isTranslatable()) $f='display';
      $ofieldvalue[$myi]=$v->$f($ors[$origk],$options[$k],$ors);
      $fieldequiv[$myi]=$origk;
      $result['o'.$k]=&$ofieldvalue[$myi];
      $myi++;
    }
    $result['oid']= $oid;
    $result['fields_object']=&$ofieldvalue;
    $result['tablelabel']=$this->getLabel();
    if($xlock=\Seolan\Core\Shell::getXModLock()) {
      $result['_lock']=$xlock->locked($oid, TZR_DEFAULT_LANG);
      if(!empty($result['_lock'])) {
	$result['_lock_editable']=(empty($result['_lock'])||
				   (\Seolan\Core\User::get_current_user_uid()==$result['_lock']['OWN']));
      }
    }

    // recherche des groupes de champs
    $result['_groups'] = $this->fieldsGroups($result, $fieldequiv, $_mapping);
    $result['_cols'] = $this->groupsCols($fieldequiv, $result['_groups'], $maxNbOfCols);

    // Log
    if($accesslog) \Seolan\Core\Logs::uniqueUpdate('access',$oid);
    // Information sur le caractere 'translatable' ou non de toute la table
    $result['translatable'] = $this->isTranslatable();
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }

  /// publication d'une donnée (oid) ou d'un ensemble de données (_selected)
  public function publish($ar) {
    $p=new \Seolan\Core\Param($ar, ['value'=>1, '_selected'=>NULL,'key'=>true]);
    $oid=$p->get('oid');
    $value=$p->get('value');
    $lang=$p->get('LANG_DATA');
    $sel = $p->get('_selected');
    $key=$p->get('key');

    if(is_array($sel) && !$key) $sel=array_flip($sel);
    if(empty($sel) && !empty($oid)) $sel=[$oid=>$value];
    // traitement de la valeur pour se ramener à 1 (oui) ou 2 (non)
    if(empty($value)) $value=2;
    if(empty($sel)) return;
    foreach($sel as $oid => $val) {
      if(\Seolan\Core\Kernel::objectExists($oid)) {
	$this->procEdit(['oid'=>$oid,
			 'LANG_DATA'=>$lang,
			 'PUBLISH'=>$value,
			 '_langspropagate'=>$p->is_set('_langspropagate')?$p->get('_langspropagate'):null,
			 '_options'=>['local'=>true]]);
      }
    }
  }

  /// Affichage d'un objet
  public function display($ar) {
    $p=new \Seolan\Core\Param($ar,['tplentry'=>$this->base,'_lastupdate'=>\Seolan\Core\Shell::admini_mode()&& !\Seolan\Core\Shell::scheduler_mode(),'genempty'=>1,'selectedfields'=>[],
				   'fieldssec'=>[],'accesslog'=>0, 'reorderfields'=>false]);
    $tplentry = $p->get('tplentry');
    $fallback = $p->get('fallback');
    $publishedonly = $p->get('_publishedonly');
    $maxNbOfCols=$p->get('numberOfColumns','local');
    $selectedfields=$p->get('selectedfields');
    if(!is_array($selectedfields)) {
      unset($selectedfields);
      $selectedfields=[];
    }
    $genempty = $p->get('genempty');
    $moid = $p->get('fmoid','local');
    $fieldssec = $p->get('fieldssec','local');
    $archive = $p->get('_archive'); /* date de l'archive à visualiser */
    $accesslog=$p->get('accesslog');
    $reorderfields = $p->get('reorderfields', 'local');
    $charset = $p->get('_charset');
    $table = $this->base;

    // on enleve tous les caracteres non chiffres
    $LANG_DATA = \Seolan\Core\Shell::getLangData($p->get('LANG_DATA','local'));
    if(!$this->isTranslatable()) $LANG_DATA=TZR_DEFAULT_LANG;
    $LANG_TRAD = \Seolan\Core\Shell::getLangTrad($p->get('LANG_TRAD'));
    $oid = $p->get('oid');
    if(empty($oid)) {
      $r= '\Seolan\Core\DataSource\DataSource::display: no oid';
      return $r;
    }
    $this->checkOID($oid,$ar,'display');
    \Seolan\Core\Logs::debug('\Seolan\Core\DataSource\DataSource::display('.$oid.')');

    $filter='';
    if(!empty($archive)){
      list($table, $filter) = $this->checkArchiveArgs($oid, $LANG_DATA, $LANG_TRAD, $archive);
    }
    $sqlSelectedFields = $table.'.KOID, '.$this->get_sqlSelectFields('*', $table);

    $_filter = $p->get('_filter', 'local');
    if (!empty($_filter))
      $filter = ' AND '.$_filter;

    $options = $p->get('options');
    $_format=$p->get('_format');
    $_mapping = $p->get('_mapping','local');
    $_options = $p->get('_options');
    $lastupdate = $p->get('_lastupdate');
    $genpublishtag= @$_options['genpublishtag'];
    $error = @$_options['error'];
    $published=$this->publishedMode($p);
    if(!isset($genpublishtag)) $genpublishtag=true;
    \Seolan\Core\Audit::plusplus('display('.$table.')');
    $result=[];

    // Recherche de l'objet en base et génération d'une erreur si introuvable
    $ors=getDB()->fetchRow('SELECT '.$sqlSelectedFields.' FROM '.$table.' WHERE KOID=? AND LANG=? '.$filter.' ORDER BY UPD ASC',[$oid,$LANG_DATA]);
    if(!$ors) {
      if($this->isTranslatable() || $fallback) {
       $ors=getDB()->fetchRow('SELECT '.$sqlSelectedFields.' FROM '.$table.' WHERE KOID=? AND LANG=? '.$filter.' ORDER BY UPD ASC', [$oid,$LANG_TRAD]);
	if(!$ors) {
	  if($error=='return') return $r='\Seolan\Core\DataSource\DataSource('.$table.')->display: could not find object with oid='.$oid.' and lang='.$LANG_DATA.'<br/>';
	  else \Seolan\Core\Shell::quit('\Seolan\Core\DataSource\DataSource('.$table.')->display: could not find object with oid='.$oid.' and lang='.$LANG_DATA.'<br/>', \Seolan\Core\Shell::QUIT_NOT_FOUND);
	}
      } else {
	if($error=='return') return $r='\Seolan\Core\DataSource\DataSource('.$table.')->display: could not find object with oid='.$oid.' and lang='.$LANG_DATA.'<br/>';
	else \Seolan\Core\Shell::quit('\Seolan\Core\DataSource\DataSource('.$table.')->display: could not find object with oid='.$oid.' and lang='.$LANG_DATA.'<br/>', \Seolan\Core\Shell::QUIT_NOT_FOUND);
      }
    }
    // dans le cas où il y a une demande d'archive, on se réaligne sur la dernière archive présente
    if(!empty($archive)) {
      $result['_archive']=$archive=$ors['UPD'];
    }
    // Fiche non publié
    if($published=='public' && $ors['PUBLISH']!=1) {
      if($error=='return') return $r='\Seolan\Core\DataSource\DataSource('.$table.')->display: data '.$oid.' has not been published has public<br/>';
      else \Seolan\Core\Shell::quit('\Seolan\Core\DataSource\DataSource('.$table.')->display: data '.$oid.' has not been published has public<br/>', \Seolan\Core\Shell::QUIT_FORBIDDEN);
    }
    // Si on est dans l'admin, vérification de la génération du champ publié ou non
    if($genpublishtag && \Seolan\Core\Shell::admini_mode() && ($published=='marked')) {
      $mark=$ors['PUBLISH'];
      if($mark=='1') $pubval='checked';
      else $pubval='';
      $result['_PUBLISH_tag']='<input type="checkbox" class="checkbox" name="_PUBLISH['.$oid.']" '.$pubval.'/>';
      $result['_PUBLISH_tag'].='<input type="hidden" name="_PUBLISH_H['.$oid.']" value="'.$mark.'"/>';
    }
    // Display sur chaque champ
    $result['fields_object']=[];
    $result['link']='';
    $link_separator=(!empty($options['link_separator'])?$options['link_separator']:' ');
    $result['tlink']='';

    // liste des champs reordonnée ou pas
    if (!empty($selectedfields) && $reorderfields) {
      $fieldlist = $selectedfields;
    } else {
      $fieldlist = $this->orddesc;
    }
    $myi=0;
    foreach($fieldlist as $k) {
      if (!isset($this->desc[$k])){
        continue;
      }
      $v = $this->desc[$k];
      if((!empty($selectedfields) && !in_array($k, $selectedfields)) || (!empty($fieldssec[$k]) && $fieldssec[$k]=='none')){
        continue;
      }
      if($v->hidden && !\Seolan\Core\Shell::isRoot())
        continue;
      if (\Seolan\Core\Shell::admini_mode() && $v->dependencyHidden($ors)){
        continue;
      }

      if(($v->get_published() && $publishedonly) || !isset($publishedonly)) {
        $origk=$k;
        if(!empty($_mapping[$k])) $k=$_mapping[$origk];
        $val=$ors[$origk];
        // Calcul des options d'affichage
        $opt=@$options[$k];
        // Si des options sont passées au champ, on génère un nom de cache unique
        if($opt && @!$opt['cache_name']) $opt['cache_name']=$options[$k]['cache_name']=uniqid('display');
        if(!empty($moid)) $opt['fmoid']=$moid;
        if($published=='marked' && $ors['PUBLISH']=='2') $opt['_published']=false;
        else $opt['_published']=true;
        if(!empty($archive)) $opt['_archive']=$archive;
        if(!empty($charset)) $opt['_charset']=$charset;
        $opt['_format']=$_format;
        $opt['oid']=$oid;
        // Nouvel objet d'affichage
        $o=$v->display($val,$opt);
        $result['o'.$k]=$o;
        $fieldequiv[$myi]=$origk;
        $myi++;
      	// Construction de fields_object et du lien avec les champs publié
        if($genempty || $o->html!='') {
          $result['fields_object'][]=$o;
          if($v->get_published()) {
            if(!$o->fielddef->isEmpty($o)) {
              if(!empty($result['link'])) $result['link'].=$link_separator;
              $result['link'].=$o->html;
            }
            if(!$o->fielddef->isEmpty($o)){
              if(!empty($result['tlink'])) $result['tlink'].=$link_separator;
              $result['tlink'].=$o->text;
            }
          }
        }
      }
    }
    $result['oid']= $oid;

    // Information sur le caractere 'translatable' ou non de toute la table
    $result['translatable']=$this->isTranslatable();
    if($result['translatable'] && isset($LANG_TRAD)) {
      $cnt=getDB()->count("select COUNT(KOID) from $table where KOID=? and LANG=? $filter",[$oid,$LANG_TRAD]);
      if($cnt<=0) $result['_translation_ok']='0';
      else $result['_translation_ok']='1';
    }

    // information sur la langue générée
    $result['_lang_data']=$LANG_DATA;
    // @todo ? specificité de xmodinfotree en admin (lecture de la langue de base/trad)
    if(!empty($LANG_TRAD) && ($LANG_TRAD!=$LANG_DATA)) {
      $ar['tplentry']=TZR_RETURN_DATA;
      $ar['LANG_DATA']=$LANG_TRAD;
      if (!isset($ar['_options'])){
	$ar['_options'] = [];
      }
      // la donnée n'existe pas toujours (freelang | auto_translate = 0)
      $ar['_options']['error'] = 'return';
      $r2=self::display($ar);
      $result['d']=$r2;
    }
    // Etat du verouillage de la fiche
    if($xlock=\Seolan\Core\Shell::getXModLock()) {
      $result['_lock']=$xlock->locked($oid,$LANG_DATA);
      $result['_lock_user'] = $xlock->getUser($oid,$LANG_DATA);
      $result['_lock_editable']=(empty($result['_lock']) || (\Seolan\Core\User::get_current_user_uid()==$result['_lock']['OWN']));
    }
    // Derniere mise à jour
    if(!empty($lastupdate)) {
      $result['lst_upd']=\Seolan\Core\Logs::getLastUpdate($oid,(!empty($result['oUPD'])?$result['oUPD']->raw:NULL));
    }
    // Recherche des groupes de champs
    $result['_groups'] = $this->fieldsGroups($result, $fieldequiv, $_mapping);
    $result['_cols'] = $this->groupsCols($fieldequiv, $result['_groups'], $maxNbOfCols);
    // Log

    if($accesslog) \Seolan\Core\Logs::uniqueUpdate('access',$oid);
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }



  protected function groupsCols($fieldequiv, $groups, $maxNbOfCols=1) {
    $nbOfFields=count($fieldequiv);
    $minFieldsInCol1=ceil($nbOfFields/$maxNbOfCols);
    $colNum=1;
    $fieldsInCol1=0;
    $cols=[];			/* numero de colonne pour chaque groupe */
    foreach($groups as $group=>$fields) {
      $fieldsInCol1+=count($fields);
      $cols[$group]=$colNum;
      if($fieldsInCol1>$minFieldsInCol1 && $colNum<$maxNbOfCols) $colNum++;
    }
    return $cols;
  }			     
  /// groupes de champs
  protected function fieldsGroups($result, $fieldequiv, $_mapping){
    $groups=['_systemproperties'=>[]];
    foreach($fieldequiv as $fn) {
      $v=$this->desc[$fn];
      // autoriser la surcharge du groupe des champs systèmes
      if(!$v->sysField() || !empty($v->fgroup)) {
	if(!empty($_mapping[$fn]))
	  $fn=$_mapping[$fn];
	if(empty($v->fgroup))
	  $v->fgroup=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','general');
	if(!empty($v->fgroup) && !empty($result['o'.$fn]))
	  $groups[$v->fgroup][]=$result['o'.$fn]; 
      } else {
	$groups['_systemproperties'][]=$result['o'.$fn]; 
      }
    }
    
    ksort($groups);

    return count($groups)>1?$groups:null;
  }

  /// Affichage d'un objet au format JSON
  public function getJSon($ar) {
    $p = new \Seolan\Core\Param($ar, ['selectedfields' => [], 'fieldssec' => []]);
    $oid = $p->get('oid');
    if (!$this->checkOID($oid, $ar, 'getJSon', true)) {
      return NULL;
    }
    $selectedfields = $p->get('selectedfields');
    if (empty($selectedfields) || $selectedfields == 'all') {
      $selectedfields = $this->orddesc;
    }
    if (isset($selectedfields['UPD'])) { // calcul lst_upd
      unset($selectedfields['UPD']);
    }

    $moid = $p->get('fmoid','local');
    $fieldssec = $p->get('fieldssec','local');
    $options = $p->get('options');
    $table = $this->base;
    $filter='';
    $_filter = $p->get('_filter', 'local');
    if (!empty($_filter))
      $filter = ' AND '.$_filter;
    $rows = getDB()->fetchAll('SELECT '.$this->get_sqlSelectFields('*').' FROM '.$table.' WHERE KOID=? ' . $filter, [$oid]);
    if (empty($rows)) {
      return NULL;
    }
    $published = $this->publishedMode($p);
    $json = [
	     'lst_upd' => NULL,
	     'attributes' => [],
	     'relationships' => []
	     ];
  
    $done=[];
    foreach ($this->orddesc as $k) {
      $done[$k]=false;
    }
    
    foreach ($rows as $row) {
      $lang = $row['LANG'];
      if ($published == 'public' && $row['PUBLISH'] != 1) {
        \Seolan\Core\Logs::critical("\Seolan\Core\DataSource\DataSource($table)->getJSon: $oid/$lang has not been published has public");
        continue;
      }
      $localeCode = \Seolan\Core\Lang::getLocaleProp('locale_code', $lang);
      \Seolan\Core\Shell::setLang($lang);
      if (!$json['lst_upd'] || $json['lst_upd'] < $row['UPD']) {
        $json['lst_upd'] = $row['UPD'];
      }
      foreach ($this->orddesc as $k) {
	// on vire les champs non sélectionnés ou sans les droits
        if (!in_array($k, $selectedfields) || $this->desc[$k]->hidden || (!empty($fieldssec[$k]) && $fieldssec[$k] == 'none')) {
          continue;
        }

	// ? $to
        if (!$this->desc[$k]->get_translatable() && $lang != TZR_DEFAULT_LANG && $to == 'attributes' && $done[$k]) {
          continue;
        }
        $done[$k] = true;

        if (!isset($options[$k]))
          $options[$k] = [];
        $options[$k] = array_merge($options[$k],
                                   ['oid' => $oid, 'fmoid' => $moid, '_published' => ($published != 'marked' || $row['PUBLISH'] != '2')]);
        $ojson = $this->desc[$k]->getJSon($row[$k], $options[$k]);
        $fieldAlias = \Seolan\Core\Json::getFieldAlias($moid, $k, $table);
        if(\Seolan\Core\Json::getGlobalParam('hideNullValue') && $ojson === null){
          continue;
        }
        if (empty($options[$k]['follow']) && ($this->desc[$k]->isLink() || $this->desc[$k]->ftype == '\Seolan\Field\StringSet\StringSet')) {
          $to = 'relationships';
          $ojson = ['data'=>$ojson];
        } else {
          $to = 'attributes';
        }
        if ($this->desc[$k]->get_translatable()) {
          $json[$to][$fieldAlias][$localeCode] = $ojson;
        } else {
          $json[$to][$fieldAlias] = $ojson;
	}
      }
      \Seolan\Core\Shell::unsetLang();
    }
    return $json;
  }


  /// affichage simplifié d'un objet en n'implémentant que l'affichage texte
  public function rDisplayText(string $oid, $LANG_DATA='', $LANG_USER='', $opts=[]) {
    if(empty($LANG_USER)) $LANG_USER=\Seolan\Core\Shell::getLangUser();
    if(empty($LANG_DATA)) $LANG_DATA=\Seolan\Core\Shell::getLangData();
    $table = $this->base;
    $charset=@$opts['_charset'];
    $_format=@$opts['_format'];
    $fmoid=@$opts['fmoid'];
    $selectedfields=@$opts['selectedfields'];

    if(!$this->isTranslatable()) $LANG_DATA=TZR_DEFAULT_LANG;
    \Seolan\Core\Audit::plusplus('rDisplayText('.$table.')');
    if(is_array($oid)) {
      $ors=$oid;
      $oid=$ors['KOID'];
    } else {
      $p=new \Seolan\Core\Param([],NULL);
      $ors=getDB()->fetchRow('SELECT '.$this->get_sqlSelectFields('*').' FROM '.$table.' WHERE KOID=? AND LANG=?',[$oid,$LANG_DATA]);
      if(!$ors) {
	$ret='\Seolan\Core\DataSource\DataSource('.$table.')::rDisplayText: could not find object with oid='.$oid.' and lang='.$LANG_DATA.'<br/>';
	\Seolan\Core\Logs::critical('rDisplayText',$ret);
	return $ret;
      }elseif($this->publishedMode($p)=='public' && array_key_exists('PUBLISH',$ors) && $ors['PUBLISH']!=1){
	\Seolan\Core\Logs::critical('rDisplayText', '\Seolan\Core\DataSource\DataSource('.$table.')::rDisplayText: Unpublished object with oid='.$oid.' and lang='.$LANG_DATA);
	return $ret='UNPUBLISHED';
      }
    }

    $result=[];
    $tlink='';
    foreach($this->desc as $k => &$v) {
      if($v->hidden && !\Seolan\Core\Shell::isRoot()) continue;
      if($selectedfields == 'all' || $v->get_published() || (!empty($selectedfields) && in_array($k,$selectedfields))) {
	$val = $ors[$k];

	// calcul des options d'affichage
	if(isset($options[$k])) $opt = $options[$k];
	else $opt=[];
	if(!empty($fmoid)) $opt['fmoid']=$fmoid;
	$opt['_published']=true;
	$opt['_format']=$_format;
 	if(!empty($charset)) {
 	  $opt['_charset']=$charset;
 	}
	// nouvel objet d'affichage
	$o=$v->display($val,$opt);

	if(!empty($tlink)) $tlink.=' ';
	$tlink.=$o->toText();
      }
    }
    $result['link']=$tlink;
    $result['oid']= $oid;
    return $result;
  }

  /**
   * generation d'infos d'affichage simplifiees a partir d'un oid
   * utilisé en particulier pour le display des champs liens
   * @param KOID          $oid
   * @param PDO_STATEMENT $ors si renseigné écrase l'oid par $ors['KOID'], (utilisé ?)
   * @param bool          $publishedonly retourner uniquement les champs publiés
   * @param string        $LANG_DATA
   * @param string        $LANG_USER
   * @param $opts [       tableau d'options indirectes
   *    selectedfields : tableau des champs sql à calculer (ou 'all')
   *    ...
   * ]
   * @return array [
   *    array fields_object : array of \Seolan\Core\Field\Value Object, integer key
   *    \Seolan\Core\Field\Value Object ofield : pour chaque champ retourné
   *    string link : représentation html
   *    KOID oid
   *    bool translatable
   * ]
   */
  public function rDisplay(?string $oid,$ors=[],$publishedonly=false,$LANG_DATA='',$LANG_USER='',$opts=[]) {
    if(empty($LANG_USER)) $LANG_USER=\Seolan\Core\Shell::getLangUser();
    if(empty($LANG_DATA)) $LANG_DATA=\Seolan\Core\Shell::getLangData();
    // Récupération en cache
    $opts['_PARAMS_']=$LANG_USER.$LANG_DATA.$publishedonly;
    $cache_name='xdatasource/rDisplay/'.\Seolan\Library\ProcessCache::generateHash($opts);
    if(($cache=\Seolan\Library\ProcessCache::get($cache_name,$oid))) return $cache;

    $table=$this->base;
    $charset=@$opts['_charset'];
    $_format=@$opts['_format'];
    $fmoid=@$opts['fmoid'];
    $lastupdate=@$opts['_lastupdate'];
    $selectedfields=@$opts['selectedfields'];

    if(!$this->isTranslatable()) $LANG_DATA=TZR_DEFAULT_LANG;
    \Seolan\Core\Logs::debug('\Seolan\Core\DataSource\DataSource::rDisplay('.$oid.')');
    \Seolan\Core\Audit::plusplus('rDisplay('.$table.')');
    if(!empty($ors)) {
      $oid=$ors['KOID'];
    } else {
      $p=new \Seolan\Core\Param($opts,NULL);
      $ors=getDB()->fetchRow('SELECT '.$this->get_sqlSelectFields('*').' FROM '.$table.' WHERE KOID=? AND LANG=?',[$oid,$LANG_DATA]);
      if(!$ors){
	$ret='\Seolan\Core\DataSource\DataSource('.$table.')::rDisplay: could not find object with oid='.$oid.' and lang='.$LANG_DATA.'<br/>';
	\Seolan\Core\Logs::critical('rDisplay',$ret);
	return $ret;
      }elseif($this->publishedMode($p)=='public' && array_key_exists('PUBLISH',$ors) && $ors['PUBLISH']!=1){
	\Seolan\Core\Logs::critical('rDisplay', '\Seolan\Core\DataSource\DataSource('.$table.')::rDisplay: Unpublished object with oid='.$oid.' and lang='.$LANG_DATA);
        return $ret='UNPUBLISHED';
      }
    }
    $link='';
    $result=['fields_object'=>[]];
    foreach($this->desc as $k => &$v) {
      if($selectedfields == 'all' || (($v->get_published() && $publishedonly) || !$publishedonly) && (empty($selectedfields) || in_array($k,$selectedfields))) {
	$val = $ors[$k];
	// calcul des options d'affichage
	if(isset($opts[$k])) $opt = $opts[$k];
	else $opt=[];
	if(!empty($fmoid)) $opt['fmoid']=$fmoid;
        if (empty($opt['selectedfields']))
          $opt['_published']=true;
	$opt['_format']=$_format;
	$opt['oid']=$oid;
 	if(!empty($charset)) $opt['_charset']=$charset;
	$opt['oid'] = $oid;
	// Nouvel objet d'affichage
	$o=$v->display($val,$opt);
	$result['o'.$k]=$o;
	$result['fields_object'][]=$o;
	if($v->get_published()) {
	  if(!empty($link)) $link.=' ';
	  $link.=$o->html;
	}
      }
    }
    $result['link']=$link;
    $result['oid']=$oid;
    // Derniere mise à jour
    if(!empty($lastupdate)) $result['lst_upd']=\Seolan\Core\Logs::getLastUpdate($oid,$result['oUPD']->raw);
    // Information sur le caractere 'translatable' ou non de toute la table
    $result['translatable']=$this->isTranslatable();

    // Mise en cache
    \Seolan\Library\ProcessCache::set($cache_name,$oid,$result);
    return $result;
  }

  /// Affichage d'une donnee dont l'oid est $oid, via la methode rdisplay
  public static function objectDisplayHelper($oid) {
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$oid);
    if(!empty($x) && \Seolan\Core\Kernel::objectExists($oid)) return $x->display(['oid'=>$oid]);
    else return NULL;
  }

  /****m* \Seolan\Core\DataSource\DataSource/fdisplay
   * NAME
   *   \Seolan\Core\DataSource\DataSource::fdisplay - affichage detaille d'un champ
   * DESCRIPTION
   *   Permet d'afficher le contenu detaille d'un champ, fonction utilisée
   *   principalement pour l'affichage detaille.
   ****/
  public function fdisplay($ar) {
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>$this->base,
				      // liste des champs qui seront affichés sur des liens
				      // si on ne veut pas prendre les published par defaut
				      'genempty'=>1]);
    $tplentry = $p->get('tplentry');
    $field = $p->get('field');
    $LANG_DATA = \Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));
    if(!$this->isTranslatable()) $LANG_DATA=TZR_DEFAULT_LANG;
    \Seolan\Core\Audit::plusplus('fdisplay('.$this->base.')');
    $ar['tplentry']=TZR_RETURN_DATA;
    $r=self::display($ar);
    \Seolan\Core\Shell::toScreen2($tplentry, 'field', $r['o'.$field]);
  }
  /*****
	Fonction : \Seolan\Core\DataSource\DataSource::gen_random_display_mask()
	Description : Display d'1 tuple pris aléatoirement.
  *****/
  function gen_random_display_mask($ar=NULL) {
    $query = $this->random_select_query($ar);
    $ors=getDB()->fetchRow($query);
    if ( $ors ) $oid = $ors['KOID'];
    $ar['oid'] = $oid;
    $this->display($ar);
  }

  // Fonction reservee a la creation d'une data pour la langue par defaut.
  // La creation d'une data dans tout autre code langue se fait par \Seolan\Core\Kernel::data_edit() et \Seolan\Core\Kernel::proc_data_edit()
  // apres avoir cree une data en l'initialisant avec la data correspondante dans le code langue par defaut.
  // @param array
  //        _unique array => Permet de vérifier si une ligne n'existe pas déjà avec les valeurs des champs passées en paramètre via ce tableau. Ex: array('alias','title')
  //        _updateifexists boolean => Permet de mettre un jour une ligne si le KOID est passé en paramètre
  public function procInput($ar=NULL) {
    $this->preUpdateTasks($ar);
    $p=new \Seolan\Core\Param($ar, ['tplentry'=>$this->base,'_inputs'=>[],'options'=>[], '_unique'=>[]]);
    $tplentry = $p->get('tplentry');
    $j=$p->get('_nojournal');
    $journal=empty($j);
    $moid=$p->get('fmoid','local');
    $nolog=$p->get('_nolog','local');
    $all=$p->get('_allfields');
    $fieldssec=$p->get('fieldssec','local');
    $delayed=$p->get('_delayed');
    if(!empty($delayed)) $delayed='LOW_PRIORITY ';
    else $delayed='';
    $options=$p->get('options');
    // Si option est un champ
    if(is_string($options)) $options=[];
    $unique=$p->get('_unique');
    $updateifexists = $p->get('_updateifexists');
    $unique_val = [];
    $unique_req = [];

    $insert = true;
    // Nouvel oid puisqu'on cree une nouvelle data en langue par defaut
    $oid=$p->get('newoid'); // permet d'imposer le KOID
    if(!empty($oid) && empty($updateifexists) && $this->objectExists($oid)) {
      \Seolan\Core\Logs::notice('\Seolan\Core\DataSource\DataSource::procInput', $oid.' already exist');
      return ['error'=>true,'message'=>$oid.' already exist'];
    }
    
    if(empty($oid)) $oid=$this->getNewOID($ar);
    else $this->checkOID($oid,$ar,'procInput');

    // traitement des langues
    $translatable = $this->getTranslatable();
    if(!$this->isTranslatable()) $lang=TZR_DEFAULT_LANG;
    elseif($translatable==3)
      $lang=\Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));
    else {
      $lang=\Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));
      if($lang!=TZR_DEFAULT_LANG) return ['error'=>true,'message'=>'Lang error'];
    }

    $fields='KOID,LANG';
    $values = '?,?';
    $inputvalues = [$oid, $lang];
    $aupd=$p->get('UPD', 'local');

    /*
     Si l'ancien UPD n'a pas été récupéré, calculer un nouveau UPD
     */
    if(isset($aupd)){
	$nottorepeat = [];
      } else {
	$nottorepeat = ['UPD'];
    }
    if($this->fieldExists('OWN') && !$p->get('OWN')) {
      $fields.=',OWN';
      $values.=',?';
      $inputvalues[] = \Seolan\Core\User::get_current_user_uid();
      $nottorepeat[]='OWN';
    }
    if($this->fieldExists('CREAD')) {
      $fields.=',CREAD';
      $values.=',?';
      $inputvalues[] = date('Y-m-d H:i:s');
      $nottorepeat[]='CREAD';
      }
    if($this->fieldExists('PUBLISH') && !$p->is_set('PUBLISH')) {
      $fields.=',PUBLISH';
      $values.=',?';
      $inputvalues[] = $this->desc['PUBLISH']->getDefaultValue();
      $nottorepeat[]='PUBLISH';
    }
    if($this->fieldExists('APP') && !$p->is_set('APP') && TZR_USE_APP) {
      $fields.=',APP';
      $values.=',?';
      $bootstrapApplication = \Seolan\Module\Application\Application::getBootstrapApplication();
      $inputvalues[] = $bootstrapApplication->oid;
      $nottorepeat[]='APP';
    }
    $inputs=$p->get('_inputs','local');
    $notifs = [];
    foreach($this->desc as $k => &$v) {
      if(!empty($fieldssec[$k]) && $fieldssec[$k]!='rw') continue;

      $issetk = $p->is_set($k) || $p->is_set($k.'_HID') || !empty($all);
      $isreadonly = !\Seolan\Core\Shell::isRoot() && $v->readonly==1;

      if((($issetk || $isreadonly) && !in_array($k, $nottorepeat)) || in_array($k, $unique)) {
        if ($isreadonly && !$issetk){
          if(isset($v->default) && $v->default!="") {
            $value = $v->default;
          } else {
            continue;
          }
        } else {
          $value = $p->get($k);
        }
	$value_hid = $p->get($k.'_HID');
	// traitement en post edit dans les cas simples
        if(!is_object($v)) \Seolan\Core\Shell::quit(['message'=>'\Seolan\Core\DataSource\DataSource::procInput: '.$this->base.':'.$k.' is not a valid field']);
        $options[$k]['oid']=$oid;
        $options[$k][$k.'_HID']=$value_hid;
        $options[$k][$k.'_title']=$p->get($k.'_title');
        $options[$k]['fmoid']=$moid;
        $r1=$v->post_input($value,$options[$k],$inputs);
        $inputs[$k]=$r1;
        $nvalue=$r1->raw;
        $fields .= ','.$k;
        if(!empty($unique) && in_array($k, $unique)){
          $trimedvalue = mb_trim($nvalue);
          if($trimedvalue !== "") {
            $unique_req[] = "$k = ?";
            $unique_val[] = $trimedvalue;
          }
          else {
            $unique_req[] = "($k = '' OR $k is null)";
          }
        }
        // cas ou on garde la valeur
        $value=$nvalue;
        if(!empty($r1->func)) {
          $values.= ','.$r1->func;
          if(is_array($value)) {
            foreach($value as $o1=>$o2)
              $inputvalues[]=$o2;
          } else {
            $inputvalues[]=$value;
          }
        } elseif(is_array($value) && (count($value)>1))  {
          $finalval='||';
          foreach($value as $o1=>$o2)
            $finalval.=$o2.'||';
          $values.=",'".$finalval."'";
        } elseif(is_array($value))  {
          $values.=',?';
          $inputvalues[]=reset($value);
        } else {
          if(!empty($r1->forcenull)) {
            $values.=",NULL";
          } else {
            $values.=',?';
            if($k=='alias' && isset($ar['aliasfieldval']))
              $value=$ar['aliasfieldval'];
            $inputvalues[]=$value;
          }
        }
      }
      // Notifications management
      if (is_a($v, '\Seolan\Field\Text\Text') && !empty($value) && $v->enabletags){
	$notifs[] = [$v,$r1];
      }
      // Tag management
      if ($v->get_ftype() == '\Seolan\Field\Tag\Tag' && !empty($value)) {
        \Seolan\Module\Tag\Tag::updateTags($k, $value, $this->base, $oid, $moid, \Seolan\Core\User::get_current_user_uid());
      } 
    }
    $query = 'INSERT '.$delayed.' IGNORE INTO '.$this->getInputTable().'('.$fields.') values ('.$values.')';
    // verification que l'enregistrement n'est pas existant dans le cas où on gère l'unicité
    // et traitement de mise à jour fiche existante
    if(!empty($unique) || !empty($updateifexists)) {
      if(!empty($unique) && !empty($unique_val)){
        $ors=getDB()->fetchRow('SELECT '.$this->get_sqlSelectFields('*').' FROM '.$this->base.' WHERE '.implode(' and ',$unique_req),$unique_val);
      }else{
        $ors=getDB()->fetchRow('SELECT '.$this->get_sqlSelectFields('*').' FROM '.$this->base.' WHERE KOID=?',[$oid]);
      }
      if($ors) {
        $insert = false;
        $oid = $ors['KOID'];
        if(!empty($updateifexists)) {
          $ar['oid'] = $oid;
          if ($this->isTranslatable())
            $ar['_langs'] = 'all';
	  // mise à jour fiche existante
          $ret = $this->procEdit($ar);
          if(!$ret['updated']) $ret['updated'] = 'noupdate';
          return $ret;
        }
      }
    }
    if(!$insert) {
      \Seolan\Core\Logs::notice('\Seolan\Core\DataSource\DataSource::procInput', $values.' not unique, similar existing record : '.$oid);
      return ['error'=>true,'message'=>'Values not unique','similarOid'=>$oid];
    }
    getDB()->execute($query,$inputvalues,$journal);

    // Propagation dans toutes les langues
    if($this->isTranslatable() && $this->getAutoTranslate()) {
      $xk=new \Seolan\Core\Kernel;
      $xk->data_autoTranslate($oid);
    }

    // Tag les metas des champs xfile
    $ar['oid']=$oid;
    $this->setFilesMeta($ar);

    // On met une ligne dans les logs pour dire qu'il y a eu modification de cet objet
    if($this->toLog() && empty($nolog)) \Seolan\Core\Logs::update('create',$oid, '('.$oid.')');
    // Actions annexes
    $this->updateTasks($ar, $oid, 'procInput', $inputs);
    if (isset($ar['interactive']) && isset($moid) && count($notifs)>0){
      $this->setUserNotifications($notifs, $oid, $moid, 'procInput');
    }

    // Préparation des retours de résultats
    $result=[];
    $result['oid']=$oid;
    $result['inputs']=$inputs;
    $result['message']=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','insert_ok');

    return \Seolan\Core\Shell::toScreen1($tplentry, $result);

  }

  public function getInputTable() {
    return $this->base;
  }

  public function &prepareReInput($ar=NULL) {
    return $this->_prepareReEdit($ar, 'input');
  }
  public function &prepareReEdit($ar=NULL) {
    return $this->_prepareReEdit($ar, 'edit');
  }
  protected function &_prepareReEdit($ar=NULL, $mode='edit') {
    $p = new \Seolan\Core\Param($ar);
    $all = $p->get('_allfields');

    $options=[];
    foreach($this->desc as $k => $v) {
      if ((isset($_REQUEST[$k]) || isset($_REQUEST[$k.'_HID'])) && empty($all) && is_array($_REQUEST['selectedfields']))
        $_REQUEST['selectedfields'][] = $k;
      if(($p->is_set($k)||$p->is_set($k.'_HID')||!empty($all))) {
	$value = $p->get($k);
	$value_hid = $p->get($k.'_HID');
	// traitement en post edit dans les cas simples
	if(!is_object($v)) {
	  \Seolan\Core\Shell::quit(['message'=>'\Seolan\Core\DataSource\DataSource::prepareReEdit: '.$this->base.":$k is not a valid field"]);
	}
	if ('input' == $mode){
	  $r1=$v->post_input($value,[$k.'_HID'=>$value_hid],$inputs);
	}else{
	  $r1=$v->post_edit($value,['oid'=>$oid,$k.'_HID'=>$value_hid],$inputs);
	}
	$nvalue=$r1->raw;
	// cas ou on garde la valeur
	$value=$nvalue;
	if(is_array($value) && (count($value)>1))  {
	  $finalval='||';
	  foreach($value as $o1=>$o2)
	    $finalval=$finalval.$o2.'||';
	  $options[$k]['value']=$finalval;
	} elseif(is_array($value))  {
	  $options[$k]['value']=array_values($value)[0];
	} else {
	  $options[$k]['value']=$value;
	}
      }
    }
    return $options;
  }
  /// Recherche sur la source
  public function &procQuery($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, ['tplentry'=>$this->base,'_storedquery'=>NULL,'getselectonly'=>false,'fieldssec'=>[]]);
    $storedquery=$p->get('_storedquery');
    $fieldssec=$p->get('fieldssec','local');
    $persistent=$p->get('_persistent');
    $fmoid=$p->get('fmoid');
    if(!empty($storedquery)) {
      if(empty($ar)) $ar=[];
      self::prepareQuery($ar, $storedquery);
      $p=new \Seolan\Core\Param($ar,['tplentry'=>$this->base,'_storedquery'=>NULL,'operator'=>'AND','getselectonly'=>false]);
    }
    $getselectonly=$p->get('getselectonly');
    $fulltext=$p->get('fulltext');
    $filter=$p->get('_filter','norequest');
    $_select=$p->get('_select');
    if(!is_array($_select) && !empty($_select)) $_select=[$_select];
    $operator=$p->get('operator');
    if(empty($operator)) $operator='AND';
    $queryobject=$queryfields=[];
    if($fulltext=='1') {
      $query=$this->fulltext_query($ar);
      $ar['select']=$query;
      $rq='';
      $queryobject['fulltext']=1;
      $queryobject['keyword']=$p->get('keyword');
    } else {
      if(!empty($filter)) $rq=$filter;
      else $rq='1';
      if(!empty($_select)) {
	foreach($_select as $c1=>$c2) {
	  if(empty($c2)) unset($_select[$c1]);
	}
	if(!empty($_select)) $rq.=' AND (('.implode(') '.$operator.' (',$_select).'))';
      }

      // Recherche de la liste des champs
      $first=true;
      $_FIELDS=$p->get('_FIELDS');
      if(empty($_FIELDS)) {
	foreach($this->desc as $k => $foo) $_FIELDS[$k]=$k;
      }
      foreach($_FIELDS as $field => $k) {
	// en recherche rapide $field = $k = field name
	// en recherche avancée $field = "idx", $k = field name
	if((!empty($fieldssec) && @$fieldssec[$k]=='none') || empty($this->desc[$k])) continue;
	$v=$this->desc[$k];
	$v1=$p->get($field);
	$queryobject[$field]=$v1;
	$o=$v->_newXFieldQuery();
	$o->value=$v1;
	$o->post_query_configure($p, $field);
	$v->post_query($o,$ar);
	if(!empty($o->empty)) $queryobject[$field.'_empty']=$o->empty;
	if(!empty($o->op)) $queryobject[$field.'_op']=$o->op;
	if(!empty($o->hid)) $queryobject[$field.'_HID']=$o->hid;
	if(!empty($o->fmt)) $queryobject[$field.'_FMT']=$o->fmt;
	if(!empty($o->par)) $queryobject[$field.'_PAR']=$o->par;
	if(!empty($o->rq)) {
	  if(!$persistent) $queryfields[$field]=$o;
	  if($first) {
	    $rq.=' AND ( '.$o->rq;
	    $first=false;
	  }else{
	    $rq.=' '.$operator.' '.$o->rq;
	  }
	}
      }
      if(!$first) $rq.=')';
    }
    $oids=$p->get('oids');
    if(!empty($oids)){
      // Si les clés sont les oids
      if(empty($oids[0])) $oids=array_keys($oids);
      $rq.=" AND {$this->base}.KOID in (\"".implode('","', $oids)."\")";
    }
    if ($p->is_set('_langstatus')){
      $langstatus =  $p->get('_langstatus');
      $langstatusoids = $this->langstatusOids(['_options'=>['local'=>1],
					       '_langstatus'=>$langstatus]);
      if (false !== $langstatusoids){
	$rq.=' /*filtre langstatus*/ AND '.$this->base.'.KOID in ("'.implode('","',$langstatusoids).'")';
      }
    }
    $ar['_filter']=$rq;
    $ar['fields'] = ','.$this->get_sqlSelectFields('*');
    list($select,$query)=$this->getSelectQuery($ar);
    if(empty($getselectonly)) {
      if($ar==NULL) $ar=[];
      $ar['queryobject']=$queryobject;
      $ar['selected']=0;
      $ar['_filter']='';
      $ar['select']=$query;
      $ar['fmoid']=$fmoid;
      $r=$this->browse($ar);
      $r['operator']=$operator;
      $r['queryfields']=$queryfields;
      return $r;
    }else{
      return $query;
    }
  }

  /// Verification que la table des archive existe et construction si elle n'existe pas.
  protected function checkArchiveTable($createifneeded=true) {
    return true;
  }
  /// recherche a table à consulter ainsi que la sélection pour les archives
  protected function checkArchiveArgs($oid, $lang_data, $lang_trad, $archive) {

    $table='A_'.$this->base;
    $filter=" AND UPD >= '$archive' ";

    // si on en dans le haut des lignes de logs, il n'y a peut être
    // pas d'archive et la dernière valeur est celle qui est dans la
    // table principale. 
    if(!getDB()->fetchOne('SELECT 1 FROM '.$table.' WHERE KOID=? AND LANG=? AND UPD>=? ORDER BY UPD ASC LIMIT 1',[$oid, $lang_data, $archive])) {
      if($this->isTranslatable()) {
	if(!getDB()->fetchOne('SELECT 1 FROM '.$table.' WHERE KOID=? AND LANG=? AND UPD>=? ORDER BY UPD ASC LIMIT 1', [$oid, $lang_trad, $archive])) {
	  $table = $this->base;
	  $filter='';
	}
      } else {
	$table = $this->base;
	$filter='';
      }
    }
    return [$table, $filter];
  }
  /// Modification d'une donnée ou d'un ensemble de données
  public function procEdit($ar=NULL) {
    // RZ je vois pas ce que ça fait là, la ligne suivante
    $this->preUpdateTasks($ar);

    $p = new \Seolan\Core\Param($ar, ['tplentry'=>$this->base,'_inputs'=>[],'fieldssec'=>[],'options'=>[],'_logname'=>'update']);
    if(!$this->isTranslatable()) $LANG_DATA=TZR_DEFAULT_LANG;
    else $LANG_DATA=\Seolan\Core\Shell::getLangData(@$ar['LANG_DATA']);
    $tplentry = $p->get('tplentry');
    $_noupdateupd=$p->get('_noupdateupd', "local");
    $fieldssec = $p->get('fieldssec','local');
    $delayed = $p->get('_delayed');
    $nolog = $p->get('_nolog','local');
    $track = $this->toLog();
    $options = $p->get('options');
    $langs = $p->get('_langs','local'); // En local car le module doit vérifier les droits auparavent
    // Si option est un champ
    if(is_string($options)) $options=[];

    $moid=$p->get('fmoid','local');
    if(!empty($delayed)) $delayed='LOW_PRIORITY ';
    else $delayed='';
    $oid=$p->get('oid');
    $editfields=$p->get('editfields');
    $editbatch=$p->get('editbatch');
    if(is_array($oid)) {
      $P1=[];
      $oids = [];
      foreach($this->desc as $f => $o) {
        if(($editfields != null) && (($editfields=='all') || in_array($f,$editfields))){
          $P1[$f]=$p->get($f);
          $P1[$f.'_HID']=$p->get($f.'_HID');
        }
      }
      foreach($oid as $i => $oid1) {
        if(!$editbatch){
          $ar1=[];
          foreach($this->desc as $f => $o) {
            if(($editfields != null)
	       && (($editfields=='all') || in_array($f,$editfields))
	    ){
              if(isset($P1[$f][$i]) || isset($P1[$f.'_HID'][$i])) {
                $ar1[$f]=$P1[$f][$i];
                $ar1[$f.'_HID']=$P1[$f.'_HID'][$i];
              }
            }
          }
        }else{
          $ar1=$P1;
        }
        $ar1['editfields']=$editfields;
        $ar1['editbatch']=$editbatch;
        $ar1['fieldssec']=$fieldssec;
        $ar1['oid']=$oid1;
        $ar1['options']=$options;
        $ret = $this->procEdit($ar1);
        $oids[] = $ret['oid'];
      }
      return ['oid' => $oids];
    }
    if($xlock=\Seolan\Core\Shell::getXModLock()) {
      $mode = $p->get('_mode');
      $locked=$xlock->locked($oid, TZR_DEFAULT_LANG);
      $procok=(empty($locked) ||
               \Seolan\Core\Shell::admini_mode()||
               ($locked && (\Seolan\Core\User::get_current_user_uid()==$locked['OWN'])));
    }

    $this->checkOID($oid,$ar,'procEdit');

    // Si la donnée n'existe pas dans la langue voulue, on la crée
    if ($this->isTranslatable() && !$this->objectExists($oid, $LANG_DATA)) {
      $k = new \Seolan\Core\Kernel;
      $k->data_autoTranslate($oid, $LANG_DATA);
    }

    // on genere la donnee en affichage pour calculer les differences
    // et pour les liens (editbatch, import + merge) faire des merge/diff au lieu de remplacement
    if((empty($nolog) && $track) || $editbatch){
      $dispors=getDb()->fetchRow('select '.$this->get_sqlSelectFields('*').' from '.$this->base.' where KOID=? and LANG=?',[$oid,$LANG_DATA]);
      if($dispors) $disp=$this->rDisplay($oid,$dispors,false,$LANG_DATA,'',['_lastupdate'=>0]);
    }

    // archivage de l'ancienne donnée si nécessaire
    $archive = $p->get('_archive');
    $aupd=$p->get('UPD', 'local');
    if($archive && $this->checkArchiveTable(true)) {
      $this->duplicate(['oid'=>$oid,'changeown'=>false,'lastonly'=>true,'nolog'=>true],'A_'.$this->base,$aupd);
    }

    // Sauver dans toutes les langues
    if($langs=='all'){
      $langs=[];
      foreach($GLOBALS['TZR_LANGUAGES'] as $l=>&$v) $langs[]=$l;
    }

    $logname=$p->get('_logname');
    $rq='';
    $inputs=$p->get('_inputs','local');
    $inputvalues=[];
    $trace=[];
    $notifs = [];
    foreach($this->orddesc as $k) {
      // Cerification des droits sur le champ
      if(!empty($fieldssec[$k]) && $fieldssec[$k]!='rw') continue;
      // Si on est dans une edition par lot, ne traiter que les champs concernés
      if(!empty($editbatch) && !in_array($k,$editfields)) continue;
      $v=&$this->desc[$k];
      if($LANG_DATA!=TZR_DEFAULT_LANG && !$v->get_translatable()) continue;
      if($p->is_set($k)||$p->is_set($k.'_HID')) {
        $value=$p->get($k);
        $value_hid=$p->get($k.'_HID');
        $options[$k]['oid']=$oid;
        $options[$k][$k.'_HID']=$value_hid;
        $options[$k][$k.'_title']=$p->get($k.'_title');
	// cas des merge / editbatch : options[...
	if ($p->is_set($k.'_op') && empty($options[$k][$k.'_op'])){
	  $options[$k][$k.'_op']=$p->get($k.'_op');
	}
	if (empty($options[$k]['old'])){
	  $options[$k]['old']=@$disp['o'.$k];
	}
        $options[$k]['_track']=$track;
        $options[$k]['fmoid']=$moid;
        $options[$k]['editbatch']=$editbatch;
        // Si on sauve dans plusieurs langues, les fichiers ne doivent pas etre effacés
        if($langs!==NULL && $v->get_translatable() && ($v->get_ftype()=='\Seolan\Field\File\File' || $v->get_ftype()=='\Seolan\Field\Image\Image' || $v->get_ftype()=='\Seolan\Field\Video\Video')) $options[$k]['del']=false;
        $r1=$v->post_edit($value,$options[$k],$inputs);
        // Si on sauve dans plusieurs langues, on recupere les dates au format internationnal
        if($langs!==NULL && $v->get_translatable() && $v->get_ftype()=='\Seolan\Field\Date\Date' && $LANG_DATA==TZR_DEFAULT_LANG) $ar[$k]=$r1->raw;
        $nvalue=$r1->raw;
        $inputs[$k]=$r1;
        if (isset($disp))
          $inputs[$k]->old = $disp['o'.$k]->raw;
        if($track && !empty($r1->trace)) {
          $trace=array_merge($trace,$r1->trace);
        }
        // Génération des infos pour la requete d'update
        if($nvalue!==TZR_UNCHANGED){
          if(!empty($r1->func)) {
            $rq.=$k.'='.$r1->func.', ';
            if(is_array($nvalue)) {
              foreach($nvalue as $o1=>$o2){
                $inputvalues[]=$o2;
              }
            }
          } else {
            if(is_array($nvalue) && (count($nvalue)>1)){
              $finalvalue='||';
              foreach($nvalue as $o1 => $o2)
                $finalvalue.=$o2.'||';
            }elseif(is_array($nvalue)){
              $finalvalue=array_values($nvalue)[0];
            }else{
              if($k=='alias' && isset($ar['aliasfieldval']))
                $nvalue=$ar['aliasfieldval'];
              $finalvalue=$nvalue;
            }
            if($finalvalue===NULL && empty($r1->forcenull)){
              $finalvalue='';
            }elseif($finalvalue!==NULL){
              $finalvalue=(string)$finalvalue;
            }

            if(isset($dispors) ? (!$dispors || $dispors[$k]!==$finalvalue) : true){
              if($finalvalue!==NULL) {
                $rq.=$k.'=?, ';
                $inputvalues[]=$finalvalue;
              }else{
                $rq.=$k.'=NULL, ';
              }
            }
          }
	  // Notifications management
	  if (is_a($v, '\Seolan\Field\Text\Text') && !empty($value) && $v->enabletags){
	    $notifs[] = [$v,$r1];
	  }
          // Tag management
          if ($v->get_ftype() == '\Seolan\Field\Tag\Tag') {
            \Seolan\Module\Tag\Tag::updateTags($k, $finalvalue, $this->base, $oid, $moid, \Seolan\Core\User::get_current_user_uid());
          }
        }
      }
    }

    // Exécution de l'update
    // attention, il peut y avoir une trace et le besoin de mettre a jour l'UPD même si aucune modification de champ
    // exemple: le champ fichier en multivalué
    if ((!empty($trace) || $rq) && $this->isProcEditContextLanguage($langs, $LANG_DATA)){
      // Edition de la fiche
      if(empty($aupd)) $UPD='NULL';
      else $UPD='"'.$aupd.'"';
      $rq.='UPD='.($_noupdateupd?'UPD':$UPD).', KOID=?, LANG=?';
      getDB()->execute('UPDATE IGNORE '.$delayed.$this->base.' set '.$rq.' where KOID=? and LANG=?', array_merge($inputvalues, [$oid, $LANG_DATA, $oid, $LANG_DATA]));
      // Propagation des champs non traduisibles
      if($LANG_DATA==TZR_DEFAULT_LANG){
        $this->propagateOnOtherLangs($oid);
      }
      $result['updated']=true;
    }

    // replication arbitraire d'une langue vers d'autres langues arbitraires
    if ($p->is_set('_langspropagate', 'local')){
      $this->propagateLangOnOtherLangs($oid, $LANG_DATA, $p->get('_langspropagate', 'local'));
    }

    // Debloquage de l'objet
    if($xlock=\Seolan\Core\Shell::getXModLock()) {
      if($mode=='unlock') $xlock->unlock($oid, TZR_DEFAULT_LANG);
      if($mode=='lock') {
	$dend = $p->get('_lock')['DEND'];
	$xlock->lock($oid, TZR_DEFAULT_LANG,  \Seolan\Core\User::get_current_user_uid(), $dend.' 00:00:00', $omoid);
      }
    }
    // Si demande de sauvegarde dans d'autres langues, propagation
    if($LANG_DATA==TZR_DEFAULT_LANG && !empty($langs)){
      $ar1=$ar;
      $ar1['_nolog']=true;
      $ar1['_langs']=[];
      foreach($langs as $l){
        if($l==TZR_DEFAULT_LANG) continue;
        $_REQUEST['LANG_DATA']=$l;
        \Seolan\Core\Shell::getLangData(NULL,true);
        $this->procEdit($ar1);
      }
      $_REQUEST['LANG_DATA']=$LANG_DATA;
      \Seolan\Core\Shell::getLangData(NULL,true);
    }
    if($rq){
      // Tag les metas des champs xfile
      if (!@$ar['_options']['nometa'])
        $this->setFilesMeta($ar);
      // On met une ligne dans les logs pour dire qu'il y a eu modification de cet objet
      if($track && empty($nolog)) {
        if (empty($langs)){
          $trace['LANG'] = $LANG_DATA;
        } else {
          $trace['LANG'] = implode(',', $langs);
        }
        \Seolan\Core\Logs::update($logname,$oid,$trace,$aupd);
      }
      // Actions annexes
      $this->updateTasks($ar, $oid, 'procEdit', $inputs);
      // Notifications après mises à jour
      if (isset($ar['interactive']) && isset($moid) && count($notifs)>0){
	$this->setUserNotifications($notifs, $oid, $moid, 'procEdit');
      }

    }

    // Message OK
    if(!empty($GLOBALS['XSHELL']->labels)){
      $result['message']=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','update_ok');
    }
    $result['oid']=$oid;
    $result['inputs']=$inputs;
    return \Seolan\Core\Shell::toScreen1($tplentry, $result);
  }

  /**
   * @author Bastien Sevajol <bastien.sevajol@xsalto.com>
   * @desc On vérifie ici que la $LANG_DATA (qui sera utilisé par la suite pour
   * faire l'UPDATE) est bien celui correspondant aux données transmises. En effet
   * Lorsque un tableau de langue est précisé procEdit se relance de façon récursive
   * pour traiter chaques lagues demandé.
   * @param array $langs Tableau contenant des langues
   * @param string $LANG_DATA La langue actuelle (DATA)
   * @return boolean
   */
  protected function isProcEditContextLanguage($langs, $LANG_DATA)
  {
    // Notre exception ne peut se produre que si la $LANG_DATA est la langue par défaut (cf. "Propagation dans toutes les langues")
    if ($LANG_DATA == TZR_DEFAULT_LANG && !empty($langs))
      // Lorsque "Propagation dans toutes les langues" relance procEdit il ne le fait pas pour la langue
      // par défaut. Si la langue en cours est une des langues spécifié alors oui on peut éxécuter sinon non.
      if (!in_array($LANG_DATA, $langs))
	return False;

    return True;
  }

  /// Duplication d'un objet (toar=>table d'archivage)
  /// @todo : fonction de Model\DataSource\Table ?
  public function duplicate($ar=NULL,$toar='',&$upd='') {
    $p=new \Seolan\Core\Param($ar,['changeown'=>true,'lastonly'=>false, 'fromArchive'=>null]);
    $oid=$p->get('oid');
    $nolog=$p->get('nolog');
    $lastonly=$p->get('lastonly');
    $changeown=$p->get('changeown');
    $tdest=(empty($toar)?$this->base:$toar);
    $nkoid=$p->get('newoid');
    if(empty($nkoid)) $nkoid=(($tdest==$this->base)?$this->getNewOID($ar):$oid);
    $fromArchive = $p->get('fromArchive');
    // RZ je pense qu'il faudrait un lock pour éviter les soucis
    if(empty($upd)) $upd=\Seolan\Field\Timestamp\Timestamp::default_timestamp();
    if ($fromArchive){
      getDB()->execute('CREATE TEMPORARY TABLE tmp1 AS SELECT * FROM A_'.$this->base.' WHERE KOID=? AND UPD=?',
		       [$oid, $fromArchive]);
    } else {
      getDB()->execute('CREATE TEMPORARY TABLE tmp1 AS SELECT * FROM '.$this->base.' WHERE KOID=?', [$oid]);
    }
    if($changeown && $this->fieldExists('OWN')) {
      getDB()->execute('UPDATE tmp1 SET KOID=?,UPD=?,OWN=?',[$nkoid,$upd,\Seolan\Core\User::get_current_user_uid()]);
    } else {
      getDB()->execute('UPDATE tmp1 SET KOID=?,UPD=?',[$nkoid,$upd]);
    }
    getDB()->execute('INSERT INTO '.$tdest.' SELECT * FROM tmp1');
    getDB()->execute('DROP TEMPORARY TABLE tmp1');

    $inputvalues=[];
    $sql='UPD=UPD';
    foreach($this->desc as $f=>$v) {
      if($v->hasExternals()) $sql.=','.$f.'=:'.$f;
    }
    if ($fromArchive){
      $archiveTable='A_'.$this->base;
      $rs=getDB()->select('SELECT '.$this->get_sqlSelectFields('*', $archiveTable).' FROM '.$archiveTable.' WHERE KOID=? AND UPD=? order by field(LANG,"'.TZR_DEFAULT_LANG.'") desc', [$oid, $fromArchive]);
    } else {
      $rs=getDB()->select('SELECT '.$this->get_sqlSelectFields('*').' FROM '.$this->base.' WHERE KOID=? order by '.($lastonly?'UPD DESC,':'').'field(LANG,"'.TZR_DEFAULT_LANG.'") desc',[$oid]);
    }
    while($ors=$rs->fetch()){
      if($lastonly && !empty($oupd) && $oupd!=$ors['UPD']) break;
      $oupd=$ors['UPD'];
      $lang=$ors['LANG'];
      foreach($this->desc as $fname=>$fielddef) {
	// tous champs (avec externals) en langue de base ou traduisibles ou quelque soit la langue si lastonly
	if($fielddef->hasExternals() && ($lastonly || $lang==TZR_DEFAULT_LANG || $fielddef->get_translatable())) {
	  if ($fromArchive){
	    $nv=$fielddef->restoreExternals($ors[$fname],$oid,$nkoid,$fromArchive);
	  } else {
	    if($tdest!=$this->base) $nv=$fielddef->copyExternalsTo($ors[$fname],$oid,$nkoid,preg_replace('/([^0-9])/','',$upd));
	    else $nv=$fielddef->copyExternalsTo($ors[$fname],$oid,$nkoid,'');
	  }
	  $inputvalues[$fname]=$nv;
	}
      }
      if(!empty($inputvalues)) getDB()->execute("UPDATE $tdest SET $sql where KOID=\"$nkoid\" and LANG=\"$lang\" AND UPD=\"$upd\"",$inputvalues);
    }
    if(!$nolog && $this->toLog()) \Seolan\Core\Logs::update('create',$nkoid, '', $upd);
    return $nkoid;
  }

  /// Duplication d'un enregistrement à partir d'une page écran
  public function procEditDup($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>$this->base]);

    if(!$this->isTranslatable()) $LANG_DATA=TZR_DEFAULT_LANG;
    else $LANG_DATA=\Seolan\Core\Shell::getLangData(@$ar['LANG_DATA']);

    $tplentry=$p->get('tplentry');
    $oidsrc=$p->get('oid');
    $oiddst=$this->getNewOID($ar);
    $inputvalues=[$oiddst, $LANG_DATA];
    $nottorepeat=[];
    $rq = "UPD=NULL, KOID=?, LANG=?";
    if($this->fieldExists('CREAD')) {
      $rq.=", CREAD=?";
      $inputvalues[]=date('Y-m-d H:i:s');
      $nottorepeat[]='CREAD';
    }
    if($this->fieldExists('OWN') && !($p->is_set('OWN') || $p->is_set('OWN_HID'))) {
      $rq .= ", OWN=?";
      $inputvalues[]=\Seolan\Core\User::get_current_user_uid();
    }
    // champ publish ?
    if ($this->fieldExists('PUBLISH')){
      $rq .= ", PUBLISH=2";
      $nottorepeat[]='PUBLISH';
    }

    foreach($this->desc as $k => $v) {
      $k_del = $p->get("{$k}_del}");
      if(($p->is_set($k) || $p->is_set($k.'_HID')) && (empty($k_del) || is_array($k_del)) && !in_array($k,$nottorepeat)) {
	$value = $p->get($k);
	$value_hid = $p->get($k.'_HID');
	// traitement en post edit dans les cas simples
	$r1=$v->post_edit_dup($value,['oidsrc'=>$oidsrc,
				      'oiddst'=>$oiddst,
				      $k.'_HID'=>$value_hid]);
	$nvalue=$r1->raw;
	// cas ou on garde la valeur
	if ( $nvalue != TZR_UNCHANGED ) {
          if(!empty($r1->func)) {
            $rq.=",$k={$r1->func}";
            if(is_array($nvalue)) {
              foreach($nvalue as $o1=>$o2){
                $inputvalues[]=$o2;
              }
            }
	  } else {
	    if(is_array($nvalue) || ($nvalue!=NULL)) {
	      $value=$nvalue;
	      if(is_array($value) && (count($value)>1))  {
		$finalval='||';
		foreach($value as $o1 => $o2)
		  $finalval=$finalval.$o2.'||';
		$rq=$rq.' ,'.$k.'= ?';
		$inputvalues[]=$finalval;
	      } elseif(is_array($value))  {
		$rq=$rq.' ,'.$k.'= ?';
		$inputvalues[]=array_values($value)[0];
	      } else {
		$rq=$rq.' ,'.$k.'= ?';
		$inputvalues[]=$value;
	      }
	    }
	    else {
	      $rq=$rq.' ,'.$k."= ''";
	    }
	  }
	}
      }
    }
    getDB()->execute('INSERT INTO '.$this->base.' set '.$rq, $inputvalues);
    // Creation automatique des tuples des autres langues à l'identique
    if ($this->isTranslatable()){
      $this->dataDuplicate($oidsrc, $oiddst);
    }

    // on met une ligne dans les logs pour dire qu'il y a eu modification de cet objet
    if($this->toLog()) \Seolan\Core\Logs::update('create',$oiddst);

    // message ok
    $result = ['message'=>'Mise à jour réussie.','oid'=>$oiddst];

    $result['oid']=$oiddst;
    $result['duplicated']=true;
    return \Seolan\Core\Shell::toScreen1($tplentry, $result);
  }
  /**
   * creation des données dans les langues autres
   * -> langue à langue
   * -> si la donnée n'existe pas dans une langue pour la source
   * elle n'existe pas non plus dans le clone
   */
  protected function dataDuplicate($oidsrc, $oiddst){
    $k = new \Seolan\Core\Kernel();
    foreach(array_keys($GLOBALS['TZR_LANGUAGES']) as $langcode){
      if (TZR_DEFAULT_LANG != $langcode){
	$k->data_duplicate($oidsrc, $langcode, $langcode, $oiddst);
      }
    }
    if ($oiddst != null)
      getDB()->execute("UPDATE {$this->base} SET UPD=NOW() WHERE KOID=? AND LANG=?",[$oiddst, TZR_DEFAULT_LANG]);
  }
  // les valeurs de retour possible sont
  // all, marked, public (ou false !)
  // all = le champ pulished n'existe pas
  // marked = le champs published existe et on est en mode authentifié
  // public = le champs existe et on ne montre que ce qui est valide
  function publishedMode(\Seolan\Core\Param $p) {
    if(!isset($this->desc['PUBLISH'])) {
      return 'all';
    }
    if(\Seolan\Core\Shell::admini_mode()){
      $published = $p->get('_published');
      if(!isset($published) || $published!==false) $published='marked';
    } else {
      if (\Seolan\Core\Shell::previewMode())
	$published = false;
      else	  
	$published = $p->get('_published');
      if(!isset($published) || $published!==false) $published='public';
    }
    return $published;
  }

  /// Parcours la source
  public function &browse($ar=NULL) {
    $params = new \Seolan\Core\Param(
				     $ar,
				     [
				      'tplentry'=>$this->base,
				      // la requete qui va servir a faire le select en base
				      'select'=>'',
				      // tableau des champs qu'on veut browser. Par defaut on prend l'attribut browsable dans la base
				      'selectedfields'=>'*',
				      // tableau de type de champ qu'on veut browser.
				      'selectedtypes'=>[],
				      // tableau propriété=>true/false (browsable, queryable...) qu'on veut browser (ex : array('browsable'=>true))
				      'selectedprops'=>[],
				      // opératuer à appliquer entre les critères de sélection
				      'selectedop'=>'AND',
				      // complément pour la requete de selection : ordre de tri des data selectionnees
				      'order'=>'',
				      // nombre de lignes par page
				      'pagesize'=>TZR_XMODTABLE_BROWSE_PAGESIZE,
				      // Mode de formatage des données retournées (object|both|lines)
				      '_mode' => TZR_BROWSE_DEFAULT_MODE,
				      // cas des affichages en plusieurs pages (next/previous),
				      //    pour savoir quel est le first de la page
				      'first'=>'0', 'nocount'=>'0', 'editfields'=>[], 'fieldssec'=>[], 'noeditoids'=>[],
				      // Mapping des champs pour retrourner le resultat d'un champs dans un autre nom (key : champ d'origine, value : nom dans le resultat)
				      '_mapping'=>[],
				      // lecture de données archivées
				      '_archives'=>0
				      ]
				     );
    \Seolan\Core\Logs::debug('\Seolan\Core\DataSource\DataSource::browse('.$this->base.')');
    \Seolan\Core\Labels::loadLabels('browse');
    $tplentry = $params->get('tplentry');
    $selectedop = $params->get('selectedop');
    $selectedfields = $params->get('selectedfields');
    $selectedqqfields = $params->get('selectedqqfields');
    $selectedtypes = $params->get('selectedtypes');
    $selectedprops = $params->get('selectedprops');
    $options = $params->get('options');
    $fieldssec = $params->get('fieldssec','local');
    $fmoid = $params->get('fmoid');
    $first = $params->get('first');
    $_mode=$params->get('_mode','local');
    $_format = $params->get('_format');
    $charset = $params->get('_charset');
    $last = $params->get('last');
    $pagesize = $params->get('pagesize');
    if(!isInteger($pagesize) || empty($pagesize)) $pagesize=TZR_XMODTABLE_BROWSE_PAGESIZE;
    $nocount=$params->get('nocount');
    $tlink=$params->get('tlink');
    $published=$this->publishedMode($params);
    $translatable = $this->getTranslatable();
    $_options = $params->get('_options');
    $genpublishtag=@$_options['genpublishtag'];
    $editfields = $params->get('editfields');
    $noeditoids = $params->get('noeditoids');
    $_mapping = $params->get('_mapping','local');
    $_archives = $params->get('_archives','local');
    if(!isset($genpublishtag)) $genpublishtag=true;

    $LANG_TRAD=\Seolan\Core\Shell::getLangTrad($params->get('LANG_TRAD'));
    $LANG_DATA=\Seolan\Core\Shell::getLangData($params->get('LANG_DATA'));
    if(!$translatable) $LANG_DATA=TZR_DEFAULT_LANG;
    $lang_list = null;
    if(!empty($LANG_TRAD) && ($LANG_DATA!=$LANG_TRAD) && ($translatable!=TZR_LANG_FREELANG)){
      $lang_other=$LANG_DATA;
      $lang_list = $LANG_TRAD;
    }

    $result=[];
    $result['header_fields']=[];
    $result['lines_oid']=[];
    $result['translation_mode'] = 0;
    if (isset($lang_other)){
      $result['translation_mode'] = 1;
    }
    // Construction des titres des colonnes
    // - Demande les champs d'un certain type (on prend aussi les champs publié et browsable)
    // - Demande des champs prédéfinies
    // - Demande tout
    // - Demande les champs browsable (defaut)
    $fields = '';
    if($selectedfields=='all'){
      $fieldlist=$this->orddesc;
    }else{
      if(!is_array($selectedfields) && empty($selectedprops) && empty($selectedtypes)) $selectedprops['browsable']=true;
      $fieldlist=$this->getFieldsList($selectedtypes,@$selectedprops['browsable'],@$selectedprops['published'],@$selectedprops['queryable'],@$selectedprops['compulsory'],@$selectedprops['translatable'],@$selectedprops['multivalued'],$selectedfields,$selectedop);
    }
    $result['header_fields'] = $result['selectedfields'] = [];
    foreach($fieldlist as $fieldname) {
      if(!empty($fieldssec[$fieldname]) && $fieldssec[$fieldname]=='none')
        continue;
      $field = $this->desc[$fieldname];
      if(isset($this->desc[$fieldname])) {
	$result['header_fields'][] = $field;
        $fields .= ','.$this->desc[$fieldname]->get_sqlSelectExpr($this->base);
        $result['selectedfields'][] = $fieldname;
        
        if ((empty($selectedqqfields) && (int)$this->desc[$fieldname]->queryable === 1) || ((int)$this->desc[$fieldname]->queryable === 1 && in_array($fieldname, $selectedqqfields, true))) {
          $result['selectedqqfields'][] = $fieldname;
        }
      }
    }

    if($published!='all') $fields.=','.$this->base.'.PUBLISH';

    $ar['fields']=$fields;
    list($select,$requete,$order,$oorder)=$this->getSelectQuery($ar);

    if($nocount!='1') $requete = preg_replace('/^select /i', 'select SQL_CALC_FOUND_ROWS ', $requete);
    // Execution de la requete
    if(!empty($pagesize) && $pagesize != -1) $rs=getDB()->select($requete.sprintf(' LIMIT %d, %d', $first, $pagesize));
    else $rs=getDB()->select($requete);

    // Calcul des pages
    if($nocount!='1') $last = getDB()->fetchOne('SELECT FOUND_ROWS()');
    if(isset($last) && $pagesize < $last && $pagesize != -1) {
      for($p=0,$i=0;($i<$last);$i+=$pagesize) $pages[$p++]=$i;
    }

    // Création du resultat
    $i=0;
    $anyedit=false;
    $adminmode=\Seolan\Core\Shell::admin_mode();
    $editfieldsres=[];
    // Construction des data
    $pub=NULL;
    while($ors = $rs->fetch()) {
      $oid=$ors['KOID'];
      $result['lines_oid'][$i]=$oid;
      if($_mode=='object'||$_mode=='both') $result['lines'][$i]['oid']=$oid;
      if($tlink) $this->browseAddValueToResult($result,$_mode,'tlink',$i,$fv='');
      if($genpublishtag && ($published=='marked')) {
	$mark=$ors['PUBLISH'];
	if($mark=='1') $pubval='checked';
	else $pubval='';
	$pub[$i]='<input type="checkbox" class="checkbox" name="_PUBLISH['.$oid.']" '.$pubval.'/>';
	$pub[$i].='<input type="hidden" name="_PUBLISH_H['.$oid.']" value="'.$mark.'"/>';
      }
      // on regarde si l'info existe dans le cas ou il y a une langue de traduction
      // @todo ? cas FREELANG
      if($translatable && !empty($lang_other)) {
	$langStatus = $this->objectLangStatus($oid);
	$this->browseAddValueToResult($result,$_mode,'translation_status',$i, $fv=$langStatus);
	if (isset($langStatus[$lang_other]) && $langStatus[$lang_other]['langstatus'] !== 0){
	  $this->browseAddValueToResult($result,$_mode,'translation_ok',$i,$fv='1');
	} else {
          $this->browseAddValueToResult($result,$_mode,'translation_ok',$i,$fv='0');
	}
      }
      $tabindex = 1;
      foreach($result['header_fields'] as $def) {
	$origk=$k=$def->get_field();
        if(!empty($_mapping[$k])) $k=$_mapping[$k];
	$value = $ors[$origk];
	$opt=@$options[$k];

        // Si des options sont passées au champ, on génère un nom de cache unique
        if($opt && @!$opt['cache_name']) $opt['cache_name']=$options[$k]['cache_name']=uniqid('browse');

	$opt['_format']=$_format;
	if(empty($opt)) $opt=[];
	$opt['_published']=true;
	if($published=='marked') {
	  if($ors['PUBLISH']=='2')
	    $opt['_published']=false;
	}
 	if(!empty($charset)) {
 	  $opt['_charset']=$charset;
 	}
	if(($editfields=='all' || is_array($editfields) && in_array($k,$editfields)) && (empty($fieldssec[$origk]) || $fieldssec[$origk]=='rw') &&
	   !in_array($oid,$noeditoids)) {
	  if(!in_array($k,$editfieldsres)) $editfieldsres[]=$k;
	  $method='edit';
	  $opt['intable']=$opt['intable']??$i;
	  $anyedit=true;
	} else $method='browse';
	$opt['fmoid']=$fmoid;
	$opt['admin']=$adminmode;
	$opt['tabindex']=$tabindex++;
	$opt['oid']=$oid;
        $opt['context']=@$_options['context'];
	$opt['lang_list'] = $lang_list;
	if ($_archives){ // Field/File : dirname include archive date
	  $opt['_archive'] = preg_replace('/([^0-9]+)/','',$ors['UPD']);
	}
	$o=$def->$method($value,$opt,$ors);
        $this->browseAddValueToResult($result,$_mode,'o'.$k,$i,$o);
	if($tlink && $def->get_published()) $this->browseAddValueToResult($result,$_mode,'tlink',$i,$fv=$o->text.' ',true);
      }
      if($published=='marked') $this->browseAddValueToResult($result,$_mode,'published',$i,$ors['PUBLISH']);
      $i++;
    }

    $result['pagesize']=$pagesize;
    if($published=='marked') $result['lines_PUBLISH_tag']=$pub;
    if(isset($editfields)) $result['editfields']=$editfieldsres;
    $result['table']=$this->base;
    $result['first']=$first;
    $result['last']=$last;
    if($last-$pagesize<=0) $result['firstlastpage']=0;
    elseif($last%$pagesize==0) $result['firstlastpage']=$pagesize*((int)($last/$pagesize)-1);
    else $result['firstlastpage']=$pagesize*(int)($last/$pagesize);
    $result['firstnext']=($first+$pagesize);
    $result['firstprev']=($first-$pagesize>=0?($first-$pagesize):$first);
    $result['select']=$select;
    $result['order']=$oorder;
    if(isset($pages)) $result['pages']=$pages;

    // Information sur le caractere "translatable" ou non de toute la table
    $result['translatable'] = $translatable;
    $result['anyedit'] = $anyedit;
    $result['queryobject']=$params->get('queryobject');
    return \Seolan\Core\Shell::toScreen1($tplentry, $result);
  }
  /// Ajoute une valeur dans le resultat d'un browse en fonction du mode de retour
  static function browseAddValueToResult(&$result,&$mode,$field,&$i,$value,$concat=false){
    if ($mode == 'object' || $mode=='both') {
      if($concat) $result['lines'][$i][$field].=$value;
      else $result['lines'][$i][$field]=$value;
    }
    if ($mode != 'object' || $mode=='both') {
      if($concat) $result['lines_'.$field][$i].=$value;
      else $result['lines_'.$field][$i]=$value;
    }
  }
  /// Retourne une valeur du resultat d'un browse en fonction du mode (detecté automatiquement)
  static function &browseGetValueFromResult(&$result,$field,&$i){
    if(isset($result['lines_o'.$field])) return $result['lines_o'.$field][$i];
    else return $result['lines'][$i]['o'.$field];
  }

  /// Retourne les oids correspondant à une requête
  public function &browseOids($ar=NULL) {
    list($select,$requete,$order)=$this->getSelectQuery($ar);
    $rs=getDB()->fetchAll($requete);
    $result=[];
    foreach($rs as $ors) {
      $result[]=$ors['KOID'];
    }
    unset($rs);
    return $result;
  }

  /// Construit une requete select selon les parametres fournis
  public function getSelectQuery($ar){
    $p=new \Seolan\Core\Param($ar,['select'=>'','fields'=>'','order'=>'','cond'=>null], "all",
                              ['pagesize'=>[FILTER_VALIDATE_INT,[]],
			       'order'=>[FILTER_CALLBACK,['options'=>'containsNoSQLKeyword']]]);
    $select=$p->get('select','norequest');
    $order=$p->get('order');
    if(is_array($order)) $order=implode(',',$order);
    $oorder=$order;
    if(!empty($order)) $ar['order']=$order;

    if (!$select && ($p->is_set('cond') || $p->is_set('where')))
      $select = $this->select_query($ar);

    $fields=$p->get('fields');
    if(is_array($fields)) $fields = ','.implode(',',$fields);
    if(empty($fields)) $fields=$this->base.'.KOID';
    elseif($fields!='*' && $fields!=$this->base.'.*') $fields=$this->base.'.KOID'.$fields;

    // Langue
    $translatable=$this->getTranslatable();
    $LANG_TRAD=\Seolan\Core\Shell::getLangTrad($p->get('LANG_TRAD'));
    $LANG_DATA=\Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));
    if(!$translatable) $LANG_DATA=TZR_DEFAULT_LANG;
    $lang_list=$LANG_DATA;
    if(!empty($LANG_TRAD) && ($LANG_DATA!=$LANG_TRAD) && ($translatable!=TZR_LANG_FREELANG)) {
      $lang_list=$LANG_TRAD;
    }
    if($this->getTranslatable())
      $cond=$this->base.".LANG=\"$lang_list\"";
    else $cond='1';

    // Filtre
    $filter=$p->get('_filter','norequest');
    if(!empty($filter)) {
      $context=[];
      $context['/(\$\(user\))/']=\Seolan\Core\User::get_current_user_uid();
      $filter=preg_replace(array_keys($context),array_values($context),$filter);
      $cond.=' AND '.$filter;
      if(!empty($select) && preg_match('/(where)/i',$select)) $select=preg_replace('/(where)/i','where '.$filter.' and ',$select);
      elseif(!empty($select)) $select=preg_replace('/(from [a-z0-1]+ )/i','$1 where '.$filter.' ', $select);
    }

    // Publication
    $published=$this->publishedMode($p);
    if($published!='all' && $fields!='*' && $fields!=$this->base.'.*') $fields.=','.$this->base.'.PUBLISH';
    if($published=='public') $cond.=' AND '.$this->base.'.PUBLISH="1" ';

    // Ordre
    $jointcond = $p->get('jointcond', 'norequest');
    if(!empty($order) && empty($select)) {
      // serait plus cohérent ?
      // if(!empty($order) && !preg_match('/(order[ ]+by)/i',$select)) { 
      $torder=explode(',',$order);
      $order=[];
      $order=$this->makeOrder($torder,$order,$jointcond);
    }
    // Construction de la requete de selection des data
    if(!empty($select) && !empty($order) && !preg_match('/(order[ ]+by)/i',$select)) 
      $requete=$select.' order by '.$order;
    elseif(!empty($select)) $requete=$select;
    else{
      if(!empty($jointcond)) $select='select distinct '.$fields.' from '.$this->base.' '.$jointcond.' where '.$cond;
      else $select='select '.$fields.' from '.$this->base.' where '.$cond;
      if(!empty($order)) $requete=$select.' order by '.$order;
      else $requete=$select;
    }
    return [$select,$requete,$order,$oorder];
  }

  /// Construction de l'ordre
  function makeOrder($torder,&$order,&$jointcond){
    $tmp=implode(',',$torder);
    if(!preg_match('/^[ a-z0-9_,;.\[\]-]+$/i',$tmp)){
      $order=$tmp;
      return $order;
    }
    foreach($torder as $i=>$actorder){
      $decorder=explode(' ',trim($actorder));
      $orderfield=$decorder[0];
      if(($pos=strpos($orderfield,'['))!==false){
        $ssorderfields=explode(';',substr($orderfield,$pos+1,-1));
        $orderfield=substr($orderfield,0,$pos);
      }else{
        $ssorderfields=NULL;
      }
      if(!empty($orderfield) && isset($this->desc[$orderfield])) {
        $ftype=$this->desc[$orderfield]->get_ftype();
        if ((\Seolan\Core\Field\Field::typeIsLink($ftype) && !$this->desc[$orderfield]->get_multivalued())
	    || $ftype=='\Seolan\Field\Document\Document') {
          $grouporder=[];
          $sens = @$decorder[1];
          // comme on veut tout on fait un left outer join
          if (\Seolan\Core\Field\Field::typeIsLink($ftype)) {
            $rtables[0]['t'] = $this->desc[$orderfield]->get_target();
	    if($rtables[0]['t']=='%'){
	      $order[]=implode(' ',$decorder);
	      continue;
	    }
	  }else{
            $rtables=getDB()->fetchAll('select DISTINCT SUBSTRING_INDEX('.$orderfield.',":",1) as t from '.$this->base);
          }

          foreach($rtables as $z){
            $rtable=$z['t'];
            $xt=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$rtable);
            $jointcond.=" left outer join $rtable as {$rtable}_{$i} on {$this->base}.$orderfield = {$rtable}_{$i}.KOID and ".
	      $rtable."_{$i}.LANG=".getDB()->quote(($xt->isTranslatable()?\Seolan\Core\Shell::getLangData():TZR_DEFAULT_LANG));
            if(!empty($ssorderfields)) $links=$ssorderfields;
            else $links=$xt->getPublished();
            foreach($links as $kl=>$vl){
              if(fieldExists($rtable,$vl)) {
                // Le type ("0" ou 0 part exemple) influt sur la façon d'ordonner de mysql (10 avant 2 par exemple)
                $default_expression=$xt->getField($vl)->getDefaultValueSqlExpression();
                $grouporder[$rtable][]='ifnull('.$rtable.'_'.$i.'.'.$vl.','.$default_expression.')';
              }
            }
          }

          foreach($grouporder as $kl=>$vl){
            // Pas de CONCAT si il n'y as qu'un champ
            if (is_array($vl) && count($vl) == 1) {
              $fields = $vl[0];
            } else {
              $fields = 'CONCAT(' . implode(',', $vl) . ')';
            }
            $decorder[0]=$fields;
            $decorder[1]=$sens;
            $order[]=implode(' ',$decorder);
          }
        } else {
          $decorder[0] = $this->base.'.'.$decorder[0];
          $order[]=implode(' ',$decorder);
        }
      }else{
	$order[]=implode(' ',$decorder);
      }
    }
    $order=implode(',',$order);
    return $order;
  }


  function getFirstFieldName() {
    // Fisrt field = selon FORDER

    // Dans orddesc, on a les fields tries par FORDER
    $f=$this->orddesc[0];
    $od = $this->desc[$f];
    if(!is_object($od)) {
      \Seolan\Core\Shell::quit('could not find published field in table '.$this->base, \Seolan\Core\Shell::QUIT_FORBIDDEN);
    }
    $of = $od->sqlfields();
    return $of;
  }
  /// Propage le contenu des champs non traduits de la langue maitre sur toutes les autres langues
  function propagateOnOtherLangs($oid) {
    if(!$this->isTranslatable() || $this->getNonTranslatableFieldCount()==0) return;
    // Recuperation des valeurs de la langue maitre
    $rq1 = 'SELECT * FROM '.$this->base.' WHERE KOID=? AND LANG=?';
    if($ors=getDB()->fetchRow($rq1, [$oid, TZR_DEFAULT_LANG])) {
      // Update des champs non translatables uniquement
      $rq2 = 'UPDATE IGNORE '.$this->base." set UPD=UPD, KOID=?";
      $inputvalues=[$oid];
      foreach ($this->desc as $k => $v) {
	if(!$v->get_translatable() && $k!='UPD') {
	  $rq2 .= ', ' . $k . ' = ?';
	  $inputvalues[]=$ors[$k];
	}
      }
      $inputvalues[]=$oid;
      $inputvalues[]=TZR_DEFAULT_LANG;
      // ne pas changer l'upd dans ce cas
      $rq2 .= ' where KOID=? and LANG!=? /* propagateOnOtherLangs */';
      getDB()->execute($rq2,$inputvalues);
    }
  }
  /// Recherche sur status de traduction
  function langstatusOids($ar=null){
    $p = new \Seolan\Core\Param($ar, ['lang_data'=>\Seolan\Core\Shell::getLangData(),
				      'lang_trad'=>\Seolan\Core\Shell::getLangTrad()]);
    if (!$p->is_set('_langstatus')){
      return false;
    }
    $lang_data = $p->get('lang_data');
    $lang_trad = $p->get('lang_trad');
    if (empty($lang_trad) || $lang_data == TZR_DEFAULT_LANG){
      return false;
    }
    $langstatus = $p->get('_langstatus');

    $select ='select distinct BASE.KOID from '.$this->getTable().' as BASE left join '.$this->getTable().' as LG on BASE.KOID=LG.KOID and LG.LANG=:langdata where ';
    $rq = [];
    $parms = ['langdata'=>$lang_data];
    if (isset($langstatus['v0'])){ // absent
      $rq[] = '(BASE.LANG=:langtrad1 AND isnull(LG.LANG))';
      $parms['langtrad1']=$lang_trad;
    }
    if (isset($langstatus['v1'])){ // en retard
      $rq[] = '(BASE.LANG=:langtrad2 AND !isnull(LG.LANG) and LG.LANG=:langdata2 and LG.UPD < BASE.UPD)';
      $parms['langtrad2']=$lang_trad;
      $parms['langdata2']=$lang_data;
    }
    if (isset($langstatus['v2'])){ // a jour
      $rq[] = '(BASE.LANG=:langtrad3 AND !isnull(LG.LANG) and LG.LANG=:langdata3 and LG.UPD >= BASE.UPD)';
      $parms['langtrad3']=$lang_trad;
      $parms['langdata3']=$lang_data;
    }
    // tous : pas filtre à faire
    if (count($rq) == 3){
      return false;
    }
    return getDb()->select($select.implode('OR', $rq), $parms)->fetchAll(\PDO::FETCH_COLUMN);
  }
  /// Recherche fulltext sur la source
  function fulltext_query($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,['keyseparator'=>',']);
    $published=$this->publishedMode($p);
    $keys=$p->get('keyword');
    $fields=$p->get('keyfields');
    $sep=$p->get('keyseparator');
    $options=$p->get('options');
    if(empty($fields)) $fields=array_keys($this->desc);
    if($this->isTranslatable()) $lang=\Seolan\Core\Shell::getLangData();
    else $lang=TZR_DEFAULT_LANG;
    if(!is_array($keys)) $keys=[$keys];
    foreach($keys as $i => $str) {
      $ks=explode($sep,$str);
      foreach($ks as $kt) {
	if($kt!='') $keywords[]=trim($kt);
      }
    }
    $cond='';
    foreach($keywords as $i=>$keyword) {
      if($cond!='') $cond.=' AND ';
      $scond='';
      foreach($fields as $k) {
	$def=$this->desc[$k];
	$s1=$def->search($keyword,$options[$k]);
	if($scond!='' && $s1!='') $scond.=' OR ';
	if($s1!='') $scond.=$s1;
      }
      if($scond!='') $cond.='('.$scond.')';
    }
    if($published=='public') {
      if(empty($cond)) $cond=' PUBLISH="1" ';
      else $cond.=' AND PUBLISH="1" ';
    }
    if(TZR_USE_APP && $this->fieldExists('APP') && !\Seolan\Core\Shell::isRoot()) {
      $bootstrapApplication = \Seolan\Module\Application\Application::getBootstrapApplication();
      if($bootstrapApplication && $bootstrapApplication->oid) {
        $condapp .= $this->getTable().'.APP="'.$bootstrapApplication->oid.'" ';
        $cond = $cond ? $cond.' AND '.$condapp : $condapp;
      }
    }
    return 'select '.$this->get_sqlSelectFields('*').' from '.$this->base.' where LANG="'.$lang.'" AND '.$cond;
  }


  /**
   * suppression de l'objet $oid dans la langue $lang
   * 'trash' = information pour la mise à la corbeille
   * si 'fullDelete' == true, suppression des archives
   */
  function delObject($oid, $lang=TZR_DEFAULT_LANG, $nolog=false,?Array $trash=null, $fullDelete=false) {
    $tolog= $this->toLog() && empty($nolog);
    
    $table=\Seolan\Core\Kernel::getTable($oid);
    if($table != $this->base) {
      \Seolan\Library\Security::alert('\Seolan\Core\DataSource\DataSource::delObject: Trying to delete object '.$oid.' in table '.$this->base);
    }

    // generation d'une valeur et des donnes d'affichage
    $d=$this->rDisplay($oid,[], false, $lang);

    // methode de destruction sur chaque champ
    foreach($this->desc as $k => &$v) {
      $v->deleteVal($d['o'.$k], $oid);
    }

    // destruction complete si langue par defaut ou table non traduisible
    if($lang==TZR_DEFAULT_LANG || !$this->isTranslatable()) {
      getDB()->execute('DELETE FROM '.$table.' where KOID=?', [$oid]);
      getDB()->execute('DELETE FROM ACL4 where AKOID=?', [$oid]);
      if($tolog) \Seolan\Core\Logs::secEvent(__METHOD__,"Delete object ($oid) related ACL", $oid);

      // on enleve les chaines de droits si c'est un utilisateur ou un groupe
      if($table=='USERS') getDB()->execute('DELETE FROM ACL4_CACHE where AGRP=?', [$oid]);
      if($table=='GRP') getDB()->execute('DELETE FROM ACL4_CACHE where AGRP=?',[$oid]);

      // on vire les préférences de l'utilisateur et les abonnements sur cet objet
      getDB()->execute('DELETE FROM OPTS WHERE user=? OR (specs like ? and dtype = "sub")',[$oid,'%'.$oid.'%']);

      // suppression des oid enregistrés
      \Seolan\Core\Module\Module::removeRegisteredOid($oid);
    } else {
      getDB()->execute('DELETE FROM '.$table.' where KOID=? and LANG=?', [$oid, $lang]);
      getDB()->execute('DELETE FROM ACL4 where AKOID=? and ALANG=?', [$oid, $lang]);
      if($tolog) \Seolan\Core\Logs::secEvent(__METHOD__,"Delete object ($oid, $lang) and related ACL", $oid);
    }
    if($tolog) {
      if ($trash) {
        list($aupd, $comments) = $trash;
	$comments['dlink'] = $d['link'];
	\Seolan\Core\Logs::update('delete',$oid,$comments,$aupd,true);
      } else {
	\Seolan\Core\Logs::update('delete',$oid,$d['link']);
	if($fullDelete) {
	  \Seolan\Core\Logs::update('delete', $oid, $d['link']. " full delete");
	}
      }
    }
    
    return $d;
  }
  /// suppression d'un objet
  function del($ar) {
    $this->preUpdateTasks($ar);
    $p=new \Seolan\Core\Param($ar,['_selectedok'=>'nok','_movetotrash'=>false, '_fullDelete'=>false]);
    $oid=\Seolan\Core\Kernel::getSelectedOids($p,true,false);
    if(is_array($oid)){
      $ret=[];
      foreach($oid as $toid){
        $ar['oid']=$toid;
        $ar['_selectedok']=$ar['_selected']='';
        $ret=array_merge_recursive($ret,$this->del($ar));
      }
      return $ret;
    }
    $fullDelete = $p->get("_fullDelete", "local");
    $nolog=$p->get('_nolog','local');
    $lang = \Seolan\Core\Shell::getLangData();
    if (!$fullDelete && $p->is_set('_movetotrash', 'local') && true === $p->get('_movetotrash')){
      // recopie de l'objet avant suppression 
      $trash = $this->copyToArchive($oid, $ar['_trashmoid'], $ar['_trashuser'], @$ar['_trashdata']);
      if (!$trash){
	\Seolan\Core\Logs::critical(__METHOD__, 'error checking trash table');
	return false;
      }
    } else {
      $trash=null;
      if ($fullDelete && $this->archiveExists()) $this->fullDelArchive($oid);
    }
    $ret=$this->delObject($oid,$lang,$nolog,$trash, $fullDelete);
    // Actions annexes
    $this->updateTasks($ar, $oid, 'del');
    return $ret;
  }

  /// rend la liste des emails dans un enregistrement en explorant tous les champs
  public function emails(&$ors) {
    $emails=[];
    $options=[];
    foreach($this->desc as $k => $v) {
      $o=$v->display($ors[$k],$options);
      if(!empty($o->emails)) {
	if(is_string($o->emails))
	  $emails[]=$o->emails;
	else {
	  foreach($o->emails as $email) {
	    $emails[]=$email;
	  }
	}
      }
    }
    return $emails;
  }

  /// rend la liste des emails dans un enregistrement
  public function emailsFromDisplay(&$disp) {
    $emails=[];
    foreach($this->desc as $k => $v) {
      $o=&$disp['o'.$k];
      if(!empty($o->emails)) {
	if(is_string($o->emails))
	  $emails[]=$o->emails;
	else {
	  foreach($o->emails as $email) {
	    $emails[]=$email;
	  }
	}
      }
    }
    return $emails;
  }

  /// rend vrai s'il y a au moins un champ publie
  function isTherePublishedField() {
    $requete = "select count(*) from DICT where DTAB='".$this->base."' and PUBLISHED=1";
    return (getDB()->count($requete)>0);
  }

  /// Recherche du premier ordre de champ disponible
  function newFieldOrder() {
    $forder = 1;
    $tmp=getDB()->fetchOne('SELECT MAX(FORDER)+1 FROM DICT WHERE DTAB=?',[$this->base]);
    if($tmp) $forder=$tmp;
    return $forder;
  }
  function objectLangStatus($koid){
    $r = getDB()->select('SELECT LANG, UPD FROM '.$this->base.' WHERE KOID=?', [$koid], false)->fetchAll(\PDO::FETCH_KEY_PAIR);
    $status = [];
    $dtbase = $r[TZR_DEFAULT_LANG];
    foreach($GLOBALS['TZR_LANGUAGES'] as $code=>$lang){
      if (isset($r[$code])){
	$status[$code] = ['UPD'=>$r[$code], 'langstatus'=>($dtbase >= $r[$code])?1:2];
      } else {
	$status[$code] = ['UPD'=>null, 'langstatus'=>0];
      }
      $status[$code]['message'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','translation_status_'.$status[$code]['langstatus']);
      $status[$code]['html'] = '<span title="'.escapeJavascript(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','translation_status_'.$status[$code]['langstatus'])).'" class="langstatus code-'.$status[$code]['langstatus'].'"></span>';
    }
    return $status;
  }

  function infoTreeObjectLangStatus($koid){
    $table = explode(':',$koid)[0];
    $r = getDB()->select('SELECT LANG, UPD FROM '.$table.' WHERE KOID=?', [$koid], false)->fetchAll(\PDO::FETCH_KEY_PAIR);
    $status = [];
    $dtbase = $r[TZR_DEFAULT_LANG];
    foreach($GLOBALS['TZR_LANGUAGES'] as $code=>$lang){
      if (isset($r[$code])){
	$status[$code] = ['UPD'=>$r[$code], 'langstatus'=>($dtbase >= $r[$code])?1:2];
      } else {
	$status[$code] = ['UPD'=>null, 'langstatus'=>0];
      }
      $status[$code]['message'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','translation_status_'.$status[$code]['langstatus']);
      $status[$code]['html'] = '<span title="'.escapeJavascript(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','translation_status_'.$status[$code]['langstatus'])).'" class="langstatus code-'.$status[$code]['langstatus'].'"></span>';
    }
    return $status;
  }
  /// vérifie si un objet existe ou non. Si $checkArchives est à vrai, on vérifie aussi dans les archives
  function objectExists($koid, $lang=NULL, $checkArchives=false) {
    if(empty($lang)) $lang=\Seolan\Core\Shell::getLangData();
    
    $found=false;
    if($lang!='%')
      $found=(getDB()->count('SELECT COUNT(KOID) FROM '.$this->base.' WHERE KOID=? AND LANG=?', array($koid,$lang), false)>0);
    else
      $found=(getDB()->count('SELECT COUNT(KOID) FROM '.$this->base.' WHERE KOID=?', array($koid), false)>0);

    // verification dans les archives
    if($checkArchives && !$found && $this->archiveExists()) {
      $found=(getDB()->fetchOne('SELECT 1 FROM A_'.$this->base.' WHERE KOID=? limit 1', array($koid), false)>0);
    }
    return $found;
  }

  /// rend vrai si une table d'archive existe pour cette source de données
  function archiveExists() {
      return \Seolan\Core\System::tableExists('A_'.$this->base);
  }

  /// Suppression d'une source de donnees
  function procDeleteDataSource($ar=NULL) {
    if(empty($this->base)) \Seolan\Library\Security::alert('\Seolan\Core\DataSource\DataSource::procDeleteDataSource: trying to procDeleteBase with empty table');
    // on verifie si c'est une table syteme
    if($this->sysTable($this->base)) {
      $txt=$this->base.' : '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','cannot_delete').'</br>';
      return ['message'=>$txt,'error'=>true];
    }
    // on verifie s'il y a des modules qui utilisent cette table
    $mods=\Seolan\Core\Module\Module::modulesUsingTable($this->base,true,false,false);
    if(!empty($mods)) {
      $txt=$this->base.' : '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','table_in_use').'<br/><ul>';
      foreach($mods as $moid => $name) {
        $txt.='<li>'.$name.' ('.$moid.')</li>';
      }
      $txt.='</ul><br/>'.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','cannot_delete').'</br>';
      \Seolan\Core\Logs::notice(__METHOD__, "datasource ({$this->base} with modules, delete prohibited");
      return ['message'=>$txt,'error'=>true];
    }
    $table = $this->base;

    // suppressions champ par champ : suppression des ressources associées
    foreach($this->getFieldsList(['\Seolan\Field\Link\Link']) as $fname){
      $this->getField($fname)->delfield();
    }
    // suppression des repertoires de donnees (si ! vue)
    \Seolan\Library\Dir::unlink($GLOBALS['DATA_DIR'].$table,true,true);

    // suppression dans BASEBASE et AMSG - recuperation du MOID
    $requete = 'delete AMSG from AMSG,BASEBASE where AMSG.MOID=BASEBASE.BOID and BTAB=?';
    getDB()->execute($requete, [$table]);
    $requete = 'DELETE FROM BASEBASE where BTAB = ?';
    getDB()->execute($requete, [$table]);

    // suppression de l'ensemble de tables Txxx
    // Drop table TXXX
    if(\Seolan\Core\System::tableExists($table, true)) {
      $this->dropSource($table);
    }
    if(\Seolan\Core\System::tableExists('A_'.$table, true)) {
      $this->dropSource('A_'.$table);
    }
    // suppression de tous les champs de la table desc
    $requete =	'DELETE FROM DICT where DTAB=?';
    getDB()->execute($requete, [$table]);

    // suppression de tous les libelles qui concernant cette table
    $requete =	'DELETE FROM MSGS where MTAB=?';
    getDB()->execute($requete, [$table]);

    // suppression de tous les messages qui concernant cette table
    $requete =	'DELETE FROM SETS where STAB=?';
    getDB()->execute($requete, [$table]);

    // suppression de toutes les options texte long des champs (commentaires...) de la table
    $requete = 'DELETE FROM AMSG where MOID LIKE "'.$table.':%"';
    $rs=getDB()->execute($requete);

    $txt=$this->base.' : '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','update_ok').'</br>';
    return ['message'=>$txt,'error'=>false];
  }

  protected function dropSource($table) {}

  static function createLetters() {
    if(\Seolan\Core\System::tableExists('LETTERS')) {
      return;
    }
    $lg = TZR_DEFAULT_LANG;
    $ar1['translatable']='1';
    $ar1['auto_translate']='1';
    $ar1['btab']='LETTERS';
    $ar1["bname"][$lg]='System - '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','letters');
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=LETTERS');
    //                                                    ord obl que bro tra mul pub tar
    $x->createField('subject','Sujet','\Seolan\Field\ShortText\ShortText','120','3','1','1','1','1','0','0');
    $x->createField('name','Nom','\Seolan\Field\Text\Text',      '40','4','1','1','1','0','0','1');
    $x->createField('modid','Module','\Seolan\Field\Module\Module',         '','5','1','1','1','0','0','0');
    $x->createField('letter','Corps','\Seolan\Field\Text\Text',        '60','6','0','0','0','1','0','0');
    $x->createField('disp','Mise en page','\Seolan\Field\Link\Link',      '','8','0','1','1','0','0','0','TEMPLATES');
  }
  function genImport($ar=NULL) {
    global $XSHELL;
    global $XLANG;
    global $FILE_LINE_TERMINATOR_HTML;
    global $TEXT_LINE_TERMINATOR_HTML;
    global $FIELD_SEPARATOR_HTML;
    global $FIELD_ENCLOSED_BY_HTML;
    global $ESCAPE_CHAR_HTML;

    $p = new \Seolan\Core\Param($ar, ['tplentry' => '']);
    $tplentry = $p->get('tplentry');
    $LANG_DATA = \Seolan\Core\Shell::getLangData();

    // Recuperation des codes langue, pour le choix de la langue dans le fichier import
    $XLANG->getCodes();
    $lang = [];
    $langSelectionFlag = [];
    for ( $myi=0; $myi<$XLANG->nbLang; $myi++ ) {
      $lang[$myi] = $XLANG->codes[$myi];
      if ( $XLANG->codes[$myi] == $LANG_DATA ) $langSelectionFlag[$myi] = 'selected';
      else $langSelectionFlag[$myi] = '';
    }

    // Liste des champs de la table, pour le choix des champs a importer et leur valeur par defaut
    $xsd = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->base);
    if ( is_array($xsd->desc) ) {
      $i = 0;
      $order = 2;
      foreach($xsd->desc as $k => $v) {
	$fieldCodes[$i] = $k;
	$fieldLabels[$i] = $v->get_label();
	$fieldTypes[$i] = $v->get_ftype();
	$fieldOrders[$i] = $order;
	$fieldDefaults[$i] = $v->edit([]);
	$i++;
      }
    }

    $result = [];
    $result['langs'] = $lang;
    $result['langSelectionFlags'] = $langSelectionFlag;
    $result['fieldCodes']=$fieldCodes;
    $result['fieldLabels']=$fieldLabels;
    $result['fieldTypes']=$fieldTypes;
    $result['fieldOrders']=$fieldOrders;
    $result['fieldDefaults']=$fieldDefaults;
    $result['fileLineTerminator'] = $FILE_LINE_TERMINATOR_HTML;
    $result['textLineTerminator'] = $TEXT_LINE_TERMINATOR_HTML;
    $result['fieldSeparator'] = $FIELD_SEPARATOR_HTML;
    $result['fieldEnclosedBy'] = $FIELD_ENCLOSED_BY_HTML;
    $result['escapeChar'] = $ESCAPE_CHAR_HTML;

    $XSHELL->tpldata["$tplentry"] = $result;
  }

  /// Ensemble d'actions à effectuer avant qu'une modification de donnée ait lieu
  protected function preUpdateTasks(&$ar) {
    if(!empty($ar['noupdatetasks'])) return false;
    if(array_key_exists('fmoid',$ar)){
      $mod=\Seolan\Core\Module\Module::objectFactory($ar['fmoid']);
      $mod->preUpdateTasks($ar);
    }
    return true;
  }

  /// Ensemble d'actions à effectuer après qu'une modification de donnée ait eu lieu
  protected function updateTasks($ar, $oid, $event, $inputs=null) {
    if(!empty($ar['noupdatetasks'])) return false;
    if(array_key_exists('fmoid',$ar)){
      $mod=\Seolan\Core\Module\Module::objectFactory($ar['fmoid']);
      $mod->updateTasks($ar, $oid, $event, $inputs);
    }
    return true;
  }

  /// Tague les metas des champs fichiers à partir des données de la fiche
  function setFilesMeta($ar){
    $p=new \Seolan\Core\Param($ar);
    $oid=$p->get('oid');
    if(is_array($oid)){
      foreach($oid as $tmpoid){
	$ar1=$ar;
	$ar1['oid']=$tmpoid;
	$this->setFilesMeta($ar1);
      }
      return;
    }

    if(!$this->isTranslatable()) $LANG_DATA=TZR_DEFAULT_LANG;
    else $LANG_DATA=\Seolan\Core\Shell::getLangData(@$ar['LANG_DATA']);
    $langs=$p->get('_langs');
    if($LANG_DATA==TZR_DEFAULT_LANG && !empty($langs)){
      // Si langue par defaut et langs spécifié, on ne tague que la langue par defaut, les autres langues le seront via le procEdit qui sera executé pour chaque langue
      $langs=[$LANG_DATA];
    }elseif($LANG_DATA==TZR_DEFAULT_LANG){
      // Si langue par defaut et langs non spécifié, on tague toutes les langues
      $langs=\Seolan\Core\Lang::getCodes('code');
    }else{
      // SI pas langue par défaut, on ne tague que la langue en cours
      $langs=[$LANG_DATA];
    }
    $xfiles=$this->getFieldsList(['\Seolan\Field\File\File','\Seolan\Field\Image\Image','\Seolan\Field\Video\Video']);
    if(empty($xfiles)) return;
    foreach($langs as $lang){
      $d=null;
      foreach($xfiles as $fname){
	$field=$this->desc[$fname];
	/// erreur sur procInsert et champ fichier traduisible, on a pas encore la donnée traduite !
	if($field->auto_write_meta && ($lang==TZR_DEFAULT_LANG || $this->isTranslatable() && $field->get_translatable())){
	  if(empty($d)) $d=$this->display(['tplentry'=>TZR_RETURN_DATA,'LANG_DATA'=>$lang,'oid'=>$oid,'_lastupdate'=>0]);
	  $this->_setFilesMeta($field,$d);
	}
      }
    }
  }
  // Sous méthode de setFilesMeta pour enrichissement simple des données à taguer
  function _setFilesMeta($field,$d){
    \Seolan\Field\File\File::setFileMetaWithDisplay($d,$d['o'.$field->field]->filename, null, ['IPTC','XMP'], true);
  }

  // implementation de l'interface des documents
  function XMCbrowseTrash($ar){
    $ar['tplentry']=TZR_RETURN_DATA;
    return $this->browseTrash($ar);
  }
  function XMCemptyTrash($ar){
    $ar['tplentry']=TZR_RETURN_DATA;
    return $this->delArchive($ar['oid'], $ar['_archive']);
  }
  function XMCmoveFromTrash($ar){
    $ar['tplentry']=TZR_RETURN_DATA;
    return $this->restoreArchive($ar['oid'], $ar['_archive'], $ar['moid'], $ar['user']);
  }
  function XMCinput($ar) {
    return $this->input($ar);
  }
  function XMCprocInput($ar) {
    return $this->procInput($ar);
  }
  function XMCedit($ar) {
    return $this->edit($ar);
  }
  function XMCprocEdit($ar) {
    return $this->procEdit($ar);
  }
  function XMCeditDup($ar) {
    return $this->edit($ar);
  }
  function XMCprocEditDup($ar) {
    return $this->procEditDup($ar);
  }
  function XMCdisplay($ar,$rdisplay=true) {
    if($rdisplay) return $this->rDisplay($ar['oid'],[],false,'','',$ar);
    else return $this->display($ar);
  }
  function XMCdel($ar) {
    return $this->del($ar);
  }
  function XMCfullDelete($ar) {
    $ar['_fullDelete'] = true;
    return $this->del($ar);
  }
  function XMCduplicate($oidsrc){
    if(!is_array($oidsrc)) $oidsrc=['oid'=>$oidsrc];
    return $this->duplicate($oidsrc);
  }
  function XMCquery($ar){
    return $this->query($ar);
  }
  function XMCprocQuery($ar){
    return $this->procQuery($ar);
  }
  function XMCallowComments(string $oid=''){
    return false;
  }
  function XMCcommentsMoid(){
    return null;
  }
  function XMCgetLastUpdate(string $oid){
    return \Seolan\Core\Logs::getLastUpdate($oid,NULL);
  }

  ////////////////////////////////////
  // FONCTIONS INTERFACE XFORM      //
  ////////////////////////////////////
  /// Recupération de la source de données
  function XFormGetDataSource(){
    return $this;
  }
  /// Insertion
  function XFormInput($ar){
    return $this->input($ar);
  }
  /// Edition
  function XFormEdit($ar){
    return $this->edit($ar);
  }
  /// Validation de l'insertion
  function XFormProcInput($ar){
    return $this->procInput($ar);
  }
  /// Validation de l'édition
  function XFormProcEdit($ar){
    return $this->procEdit($ar);
  }
  /// Parcours
  function XFormBrowse($ar){
    return $this->browse($ar);
  }

  /// Debut v8
  /// Fonction checker pour v8
  static function procNewSource($ar){
    return ['message'=>'','error'=>false];
  }
  function browseFields($ar=NULL){
  }
  function newField($ar=NULL){
  }
  function procNewField($ar=NULL){
  }

  /// imaginer le nouvel ordre a partir du parametre forder, numero ou nom de champ
  protected function _guessFieldOrder($forder) {
    // cas ou forder est le nom du champ apres lequel on veut se placer
    if(!is_numeric($forder) && !empty($forder)) {
      $max=getDB()->fetchRow('select FORDER FROM DICT WHERE DTAB=? AND FIELD=?', [$this->base, $forder]);
      if(!empty($max)) $forder=$max['FORDER']+1;
    }
    // on sait pas ou se placer on se met a la fin
    if(empty($forder)) {
      $max=getDB()->fetchRow('select FORDER FROM DICT WHERE DTAB=? ORDER BY FORDER DESC', [$this->base]);
      $forder=$max['FORDER']+1;
    }
    return $forder;
  }

  /// publication de certains fields seulement
  // on depublie tous les champs sauf ceux qui sont dans le tableau en parametre
  function publishOnlyFields($fieldsarray) {
    $fields=implode('","',$fieldsarray);
    getDB()->execute('UPDATE DICT SET PUBLISHED=0 WHERE DTAB=?', [$this->base]);
    getDB()->execute('UPDATE DICT SET PUBLISHED=1 WHERE DTAB=? AND FIELD IN ("'.$fields.'")', [$this->base]);
  }
  /**
   * Permet de créer/mettre à jour les champs du DESC
   * @param $desc_params array [
   *   'fieldname' => [
   *     'label' => 'Mon champ',
   *     'ftype' => 'XFieldDef',
   *     'options' => [ ... ],
   *   ],
   *   'fieldname2' => [
   *      ...
   *   ]
   * ]
   * @return array Résultats où les clés sont les noms des champs
   */
  function updateDesc($desc_params) {
    $results = [];
    foreach ($desc_params as $fieldname => $params) {
      $params['field'] = $fieldname;
      $results[$fieldname] = $this->fieldExists($fieldname)?$this->procEditField($params):$this->procNewField($params);
    }
    return $results;
  }
  /// Création d'un champ avec les paramètres en liste plutot que dans un tableau
  function createField($field,$label,$ftype,$fcount,$forder,$compulsory,$queryable=true,$browsable=true,$translatable=true,$multi=false,
		       $published=false,$target='',$options=[]) {
    if ($this->fieldExists($field))
      return ['error' => true, 'message' => 'Field '.$field.' already exist'];
    $ar=[];
    $ar['field']=$field;
    $ar['ftype']=$ftype;
    $ar['fcount']=$fcount;

    $ar['forder'] = $this->_guessFieldOrder($forder);

    $ar['compulsory']=$compulsory;
    $ar['queryable']=$queryable;
    $ar['browsable']=$browsable;
    $ar['translatable']=$translatable;
    $ar['multivalued']=$multi;
    $ar['published']=$published;
    if(empty($label)) $label=$field;
    $ar['label'][TZR_DEFAULT_LANG]=$label;
    $ar['target']=$target;
    $ar['options']=$options;

    return $this->procNewField($ar);
  }

  function editField($ar=NULL){
  }

  function procEditField($ar=NULL){
  }

  /// sauvegarde des changements sur la liste des champs
  function procEditFields($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,['batch'=>0]);
    $message='';
    $error=false;
    $fields=$p->get('field');
    $compulsory=$p->get('compulsory');
    $queryable=$p->get('queryable');
    $browsable=$p->get('browsable');
    $translatable=$p->get('translatable');
    $multivalued=$p->get('multivalued');
    $published=$p->get('published');
    $forder=$p->get('forder');
    $fcount=$p->get('fcount');
    $addOption=substr($p->get('addOption'), 2); // option additionnelle prefixée par __
    $addOptions=$p->get('addOptions'); // valeurs de l'option additionnelle
    foreach($fields as $field=>$v) {
      $label=$this->desc[$field]->get_labels();
      $ar=['field'=>$field,
	   'ftype'=>$this->desc[$field]->get_ftype(),
	   'forder'=>$this->desc[$field]->get_forder(),
	   'browsable'=>($browsable[$field]?$browsable[$field]:false),
	   'fcount'=>($fcount[$field]?$fcount[$field]:false),
	   'forder'=>($forder[$field]?$forder[$field]:0),
	   'queryable'=>($queryable[$field]?$queryable[$field]:false),
	   'compulsory'=>($compulsory[$field]?$compulsory[$field]:false),
	   'translatable'=>($translatable[$field]?$translatable[$field]:false),
	   'multivalued'=>($multivalued[$field]?$multivalued[$field]:false),
	   'published'=>($published[$field]?$published[$field]:false),
	   'label'=>$label,
	   'target'=>$this->desc[$field]->get_target(),
	   'options'=>(isset($addOptions[$field][$addOption])?[$addOption=>$addOptions[$field][$addOption]]:null)];
      $tmp=$this->procEditField($ar);
      $message.=$tmp['message'];
      if($tmp['error']) $error=true;
    }
    return ['message'=>$message,'error'=>$error];
  }

  /// Supprime un champ
  function delField($ar=NULL){
  }

  /// Cherche le premier nom de champo automatique disponible
  function newFieldName() {
    $no=1;
    $found=false;
    while(!$found) {
      $fname=sprintf('F%04d',$no);
      if(!$this->fieldExists($fname)) $found=true;
      else $no++;
    }
    return $fname;
  }

  /// Verifie la présence des infos obligatoires avant création d'un champ et les renseigne si necessaire
  function newFieldDescIsCorrect(&$field,&$ftype,&$fcount,&$forder,&$compulsory,&$queryable,&$browsable,$translatable,&$multivalued,
				 &$published,&$target,&$label) {
    if(empty($field)) $field=$this->newFieldName();
    if(isSQLKeyword($field)) {
      \Seolan\Core\Logs::critical("$field is sqlKeyword");
      return false;
    }
    if(isTZRKeyword($field)) {
      \Seolan\Core\Logs::critical("$field is tzr keyword");
      return false;
    }
    if(is_numeric($field)) {
      \Seolan\Core\Logs::critical("$field is tzr numeric");
      return false;
    }
    $field=str_replace(' ','',$field);
    if (strlen($field) > 64) {
      \Seolan\Core\Logs::critical("Fieldname '$field' is too long for MySQL: 64 characters max.");
      return false;
    }
    if($label[TZR_DEFAULT_LANG]=='') $label[TZR_DEFAULT_LANG]=$field;
    if(\Seolan\Core\Field\Field::getFCount($ftype)){
      if($fcount<=0 ) $fcount=1;
    } else $fcount=0;
    if(!is_numeric($forder) || $forder<=0) $forder=$this->newFieldOrder();
    if(!\Seolan\Core\Field\Field::getUseTarget($ftype)) $target=TZR_DEFAULT_TARGET;
    if(!\Seolan\Core\Field\Field::typeExists($ftype)) {
      \Seolan\Core\Logs::critical("$field type $ftype unknown");
      return false;
    }
    return true;
  }

  /// Ajoute un champ dans le desc
  function newDesc($field,$ftype,$fcount,$forder,$compulsory,$queryable,$browsable,$translatable,$multivalued,$published,$target,$labels,$options=[]) {
    \Seolan\Library\ProcessCache::deleteFromMemcached('datasource-'.$this->boid);
    $r1=['FIELD'=>$field,'FTYPE'=>$ftype,'DTAB'=>$this->base,'FCOUNT'=>$fcount,'FORDER'=>$forder];
    $obj=(object)$r1;
    $def=\Seolan\Core\Field\Field::objectFactory($obj);
    if(is_object($def)) {
      $def->set_compulsory($compulsory);
      $def->set_queryable($queryable);
      $def->set_browsable($browsable);
      $def->set_translatable($translatable);
      $def->set_multivalued($multivalued);
      $def->set_published($published);
      $def->set_labels($labels);
      $def->set_target($target);
      $def->_options->procDialog($def,$options);
      $this->desc[$field]=$def;
      return true;
    }
    return false;
  }

  /// Modifie un champ dans le desc
  function changeDesc($field, $ftype,$fcount,$forder,$compulsory,$queryable,$browsable,$translatable,$multivalued,$published,$target,$label) {
    $this->delDesc($field);
    return $this->newDesc($field,$ftype,$fcount,$forder,$compulsory,$queryable,$browsable,$translatable,$multivalued,$published,$target,$label);
  }

  /// Supprime un champ du desc
  function delDesc($field) {
    \Seolan\Library\ProcessCache::deleteFromMemcached('datasource-'.$this->boid);
    unset($this->desc[$field]);
    $key = array_search ( $field, $this->orddesc);
    if($key !== false)
      unset($this->orddesc[$key]);
  }

  /// Check la validité d'un champ dans le desc
  function fieldDescIsCorrect($field,&$ftype,&$fcount,&$forder,&$compulsory,&$queryable,&$browsable,&$translatable,&$multivalued,&$published,&$target,&$label) {
    $def=$this->desc[$field];
    if(!isset($label[TZR_DEFAULT_LANG]) || $label[TZR_DEFAULT_LANG]=='') 
      $label[TZR_DEFAULT_LANG]=$def->get_label();
    if(\Seolan\Core\Field\Field::getFCount($ftype)) {
      if($fcount<1) {
	if($ftype==$def->get_ftype()) $fcount=$def->get_fcount();
	else $fcount=1;
      }
    }else $fcount=0;
    if(!is_numeric($forder) || $forder<1) $forder=$def->get_forder();
    if(!\Seolan\Core\Field\Field::getUseTarget($ftype)) $target=TZR_DEFAULT_TARGET;
    
    return true;
  }

  /// Test d'existence d'un champ
  public function fieldExists($f) {
    return isset($this->desc[$f]);
  }

  /// Retourne la liste de champ de type \Seolan\Field\Link\Link
  public function getXLinkDefs($limits=NULL,$target=NULL) {
    $links=[];
    foreach($this->desc as $f=>&$v) {
      if($v->isLink() && ($target == $v->get_target() || empty($target))) {
	if(empty($limits) || in_array($f,$limits)) $links[]=$f;
      }
    }
    return $links;
  }

  /// Retourne la liste des champs de type oid utilisant la table. Ne tiens pas compte des surcharges
  static function fieldsUsingTable($table,$oid=NULL) {
    $ret=[];
    $linkTypes = \Seolan\Core\Field\Field::getLinkTypes();
    $q ='select * from DICT where FTYPE IN ('.implode(',', array_fill(0, count($linkTypes), '?')).') AND TARGET=?';
    array_push($linkTypes, $table);
    $rs=getDB()->fetchAll($q, $linkTypes);
    foreach($rs as $ors) {
      $ret[]=$ors['DTAB'].' '.$ors['FIELD'];
    }
    return $ret;
  }
  

  /// Vide la table
  public function clear($rq=NULL) {
  }
  /// Duplique la source (sans les données)
  public function procDuplicateDataSource($ar=NULL){
  }
  /// Check/repare la source de donnée
  public function chk($ar=NULL) {
  }
  /// Creation des traductions manquantes
  public function repairTranslations($ar=null){
  }
  /// Edition des proprietes d'une source
  function editProperties($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, ['tplentry'=>'']);
    $tplentry=$p->get('tplentry');
    $values=(object)['translate'=>$this->getTranslatable(),
		     'auto_translate'=>$this->getAutoTranslate(),
		     // workaround ? la spécficitaiton de classe dans l'interface est en absolu, classname non
		     'classname'=>'\\'.get_class($this)
    ];
    $rs=getDB()->fetchAll('select MLANG,MTXT from AMSG where MOID=?',[$this->boid]);
    foreach($rs as $ors) $values->bname[$ors['MLANG']]=$ors['MTXT'];
    unset($rs);
    $options=new \Seolan\Core\Options();
    $options->setId($this->boid);
    $options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','table_name'),'bname','ttext');

    if (!\Seolan\Core\Shell::getMonoLang()) { // S'il existe plusieurs langues, activer les options de traductibilité
      $options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','translatable'),'translate','list',
		     ['values'=>['1','0',TZR_LANG_FREELANG],
		      'labels'=>[\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','yes'),
				 \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','no'),
				 \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','freelang')]]);
      $options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','automatic_translation'),'auto_translate','boolean');
    }
    $options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','theclass'),'classname', 'text');
    $optsglob=$options->getDialog($values,NULL,'options');
    $opts=$this->_options->getDialog($this,NULL,'options');
    $result=['options'=>$opts,'optionsglob'=>$optsglob,'boid'=>$this->boid,'table'=>$this->base];
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }
  /// ajout des options de propagation automatique de langue
  protected function setLangRepliOpts(){
      $langlabels = array();
      $langcodes = array();
      foreach($GLOBALS['TZR_LANGUAGES'] as $code=>$iso){
	$langlabels[] = \Seolan\Core\Lang::get($code)['text'];
	$langcodes[] = $code;
      };
      foreach($langcodes as $i=>$code){
	if ($code == TZR_DEFAULT_LANG){
	  continue;
	}
	$labels = $langlabels;
	$codes  = $langcodes;
	unset($labels[$i]);
	unset($codes[$i]);

	$this->_options->setOpt($langlabels[$i].' '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','lang_propagated'),
				'langrepli_'.$code,
				'list',
				array('values'=>array_merge(array(''), $codes),
				      'labels'=>array_merge(array('---'), $labels)),
				'',
				'Langues');
      }
  }
  /// Sauvegarde des proprietes d'une datasource
  function procEditProperties($ar=NULL) {

    $p=new \Seolan\Core\Param($ar,['tplentry'=>'']);
    $options=$p->get('options');
    $bname=$options['bname'];
    $translate=$options['translate'];
    $auto_translate=$options['auto_translate'];
    $class=$options['classname'];
    if(!empty($auto_translate)) $translate=1;
    if(empty($class)) $class='\Seolan\Model\DataSource\Table\Table';
    // langrepli_xx non transmis car "disabled" + modif ou pas
    if (TZR_LANG_BASEDLANG == $this->translatable) {
      foreach (array_keys($GLOBALS['TZR_LANGUAGES']) as $langcode){
	$propName = 'langrepli_'.$langcode;
	if (!isset($options[$propName])){
	  $options[$propName]='';
	}
      }
    }
    $this->_options->procDialog($this, $options);
    //$xml=$this->_options->toXML($this);
    $json=$this->_options->toJSON($this);
    if ($class == '\Seolan\Model\DataSource\View\View') {
      \Seolan\Model\DataSource\View\View::updateView($this->base, $this->query);
    }
    getDB()->execute('update BASEBASE set BNAME=?, TRANSLATABLE=?, AUTO_TRANSLATE=?, BCLASS=?,BPARAM=? where BOID=?',
        //[$bname[TZR_DEFAULT_LANG],$translate,$auto_translate,$class,$xml,$this->boid]);
        [$bname[TZR_DEFAULT_LANG],$translate,$auto_translate,$class,$json,$this->boid]);
    \Seolan\Core\Labels::updateAMsg($this->boid,$bname);

    static::clearCache();

    \Seolan\Library\ProcessCache::deleteFromMemcached('datasource-'.$this->boid);
  }

  /// store user notification on document insert or change
  function setUserNotifications($notifs, $oid, $moid, $event=null){
    $from = \Seolan\Core\User::get_user();
    foreach($notifs as list($fd, $value)){

      // check value contains has tags
      $fv = $fd->display($value->raw, ($options=[]));

      if (isset($fv->subscollection) && count($fv->subscollection)>0){
	\Seolan\Module\User\User::addUserNotification(['name'=>$from->_cur['fullnam'],
						       'email'=>$from->_cur['email']],
						      $fv->subscollection,
						      $moid,
						      $this->getTable(),
						      $fd->field,
						      $oid,
						      $event);
      }
    }
  }
  /// generation de la doc
  function getDocumentationData($fieldssec=[]){
    return null;
  }
  /// generation du fichier source
  function exportSpec(&$sheet){
  }
  function exportValues(&$sheet){
  }
  function importSpec(&$sheet,&$message,$param=[]){
  }
  function importValues(&$sheet,&$message,$param=[]){
  }
  /**
   * mise en corbeille d'un objet
   * = archivage de la donnée, toutes langues confondues
   * event modtotrash
   * droits originaux ?
   */
  protected function copyToArchive($oid, $trashmoid, $trashuser, $trashdata=null){
    if($this->checkArchiveTable(true)) { // vérifer dans DataSource/Table
      $aupd = null;
      // archivage des lignes
      $this->duplicate(['oid'=>$oid,'changeown'=>false,'lastonly'=>false,'nolog'=>true],'A_'.$this->base, $aupd);
      // trace dans les logs : fmoid, fuser
      $comments = ['oid'=>$oid,'moid'=>$trashmoid,'user'=>$trashuser];
      if ($trashdata != null){
	foreach($trashdata as $k=>$v){
	  if (!isset($comments[$k])){
	    $comments[$k] = $v;
	  }
	}
      }
      //\Seolan\Core\Logs::update('movetotrash',$oid,$comments,$aupd,true);
      return [$aupd, $comments];
    } else { // critical exit (todo)
      return false;
    }
  }
  /**
   * restaure une archive
   * -> issue d'une mise à la corbeille
   * -> à terme ? version d'une fiche
   */
  public function restoreArchive(string $oid, string $archive, string $restoremoid, string $restoreuser){
    if (!preg_match('/^([0-9]+)$/',$archive))
      $archive=preg_replace('/([^0-9]+)/','',$archive);

    $ok = getDB()->fetchOne('select 1 from A_'.$this->base.' where KOID=? and UPD=?', [$oid, $archive]);

    if (!$ok){
      return false;
    }

    $aupd = null;
    $nkoid = $this->duplicate(['oid'=>$oid,
			       'newoid'=>$oid,
			       'fromArchive'=>$archive, // force source = A_
			       'nolog'=>true], 
			      $this->base,
			      $aupd);

    $d = $this->display(['oid'=>$oid, 'tlink'=>true]);
    
    \Seolan\Core\Logs::update('movefromtrash',$oid, ['oid'=>$oid,'moid'=>$restoremoid,'user'=>$restoreuser, 'dlink'=>$d['tlink']], $aupd, true);

    // retourne ok + tlink
    return [true, $d['tlink']];
    
  }
  /**
   * suppression complète de la poubelle
   * -> liste des archives ! movetotrash 
   * -> suppressions individuelles des fiches archivées
   * on garde les archives liées aux modifications des objets en table
   */
  public function delArchiveAll(?string $filter=null){
    $select = $this->browseTrashSelect($filter);
    $rs = getDB()->select($select);
    \Seolan\Core\Logs::notice(__METHOD__, $this->base.' empty trash '.$rs->rowcount().' lines ');
    // @TODO : au dela de ... 500 lignes -> tâche idle + message etc 
    while($ors = $rs->fetch()){
      $this->delArchive($ors['KOID'], $ors['UPD']);
    }
    return $rs->rowCount().' lines deleted from trash';
  }
  /// suppression de toutes les archives pour un oid donné 
  public function fullDelArchive(string $oid) {
    foreach(getDB()->fetchCol("select UPD from A_{$this->base} where KOID=?", [$oid]) as $lineupd){
      $this->delArchive($oid, $lineupd);
    }
  }
  /**
   * suppression d'une archive
   * -> sql, externals
   */
  public function delArchive(string $oid, string $upd, $log=true){

    if (!preg_match('/^([0-9]+)$/',$upd)){
      $upd = preg_replace('/([^0-9])/','',$upd);
    }

    // generation d'une valeur et des donnes d'affichage
    $d = $this->display(['oid'=>$oid, '_archive'=>$upd, 'tlink'=>true]);

    \Seolan\Core\Logs::update('delete',$oid,"{$d['link']} {$upd} deleted from archives");
    
    foreach($this->desc as $fn=>$fd) {
      $fd->deleteVal($d['o'.$fn], $oid);
    }

    getDB()->execute("delete from A_{$this->base} where KOID=? and UPD=?", [$oid, $upd]);

    return [true, $d['tlink']];
    
  }
  /**
   * corbeille
   * liste des fiches archivées les plus récentes / oid correspondant à un event movetotrash 
   * et qui ne sont plus en base 
   */
  public function browseTrash($ar){
    $p = new \Seolan\Core\Param($ar, ['_filter'=>null,'tplentry'=>TZR_RETURN_DATA,'selectedfields'=>null,'_trashfilter'=>null, 'pagesize'=>TZR_XMODTABLE_BROWSE_PAGESIZE, 'first'=>0]);
    $filter = $p->get('_filter');
    $trashfilter = $p->get('_trashfilter');
    $selectedfields = $p->get('selectedfields');

    $select = $this->browseTrashSelect($filter, $trashfilter);

    $this->desc['usernam'] = new \Seolan\Field\Text\Text((object)['FIELD'=>'usernam','FTYPE'=>'\Seolan\Field\ShortText\ShortText',
								  'COMPULSORY'=>0,'TRANSLATABLE'=>0, 'FCOUNT'=>120, 'FORDER'=>0, 'QUERYABLE'=>0, 'MULTIVALUED'=>0,
								  'BROWSABLE'=>1, 'DTAB'=>$this->base, 'PUBLISHED'=>1, 'TARGET'=>'%', 'DPARAM'=>NULL]);
    $this->desc['usernam']->label = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'user');
    array_unshift($this->orddesc,'usernam');
    
    $fdUPD = $this->getField('UPD');
    $fdUPD->label = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'trashon');
    $fdUPD->browsable = 1;
    if (is_array($selectedfields) && !in_array('UPD', $selectedfields)){
      $selectedfields[] = 'UPD';
    }

    $res = $this->browse(['tplentry'=>TZR_RETURN_DATA,
			  'select'=>$select,
			  'selectedfields'=>$selectedfields,
			  'first'=>$p->get('first'),
			  'pagesize'=>$p->get('pagesize'),
			  'order'=>'UPD desc',
			  '_archives'=>1 // pour les champs fichiers pcpalemet : secondary root
			  ]);

    array_shift($this->orddesc);
    unset($this->desc['usernam']);
    
    return \Seolan\Core\Shell::toScreen1($p->get('tplentry'), $res);

  }
  /**
   * requete permettant de faire un browse sur la corbeille
   */
  public function browseTrashSelect(?string $filter=null, ?string $trashfilter=null){
    return null;
  }


  /// Contruction de la liste de champ d'un requete
  public function get_sqlSelectFields($selected, $aTable=null){
    $tfields = [];
    if ($selected == '*') {
      $tfields[] = ($aTable??$this->base).'.KOID';
      $tfields[] = ($aTable??$this->base).'.LANG';
      $selected = array_keys($this->desc);
    }
    foreach($selected as $fn){
      $tfields[] = $this->desc[$fn]->get_sqlSelectExpr($aTable??$this->base);
    }
    return implode(',', $tfields);
  }
}

\Seolan\Core\DataSource\DataSource::$_sources=[];
\Seolan\Core\DataSource\DataSource::$_sources['\Seolan\Model\DataSource\Table\Table']=['SOURCE'=>'Table SQL','CLASSNAME'=>'\Seolan\Model\DataSource\Table\Table', 'WIZARD'=>'\Seolan\Model\DataSource\Table\Wizard'];
\Seolan\Core\DataSource\DataSource::$_sources['\Seolan\Model\DataSource\View\View']=['SOURCE'=>'Vue SQL','CLASSNAME'=>'\Seolan\Model\DataSource\View\View', 'WIZARD'=>'\Seolan\Model\DataSource\View\Wizard'];
