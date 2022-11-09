<?php
/// Gestion d'une source de donnees de type 'table SQL'

namespace Seolan\Model\DataSource\Table;

use Seolan\Core\Shell;

class Table extends \Seolan\Core\DataSource\DataSource {
  public static $tableNamePattern = '([0-9A-Za-z_]+)'; /// unquoted mysql identifier, - le $
  public $desc = [];
  public $orddesc = [];
  public $published_fields_in_admin=2;
  public $sendacopytofields=[];
  public $oidstruct1='';
  public $oidstruct2='';
  public $oidstruct3='';

  function __construct($boid=0) {
    \Seolan\Core\Logs::debug(get_class($this).'::construct: start '.$boid);
    $p= new \Seolan\Core\Param([],[]);
    parent::__construct($boid);
    $this->title=\Seolan\Core\DataSource\DataSource::$_boid[$boid]->MTXT;
    if(!isset(\Seolan\Core\DataSource\DataSource::$_boid[$boid]))
      \Seolan\Core\Shell::quit($boid.' unknown table ');
    else {
      $this->translatable = \Seolan\Core\DataSource\DataSource::$_boid[$boid]->TRANSLATABLE;
      $this->log = \Seolan\Core\DataSource\DataSource::$_boid[$boid]->LOG;
      $this->autotranslate = \Seolan\Core\DataSource\DataSource::$_boid[$boid]->AUTO_TRANSLATE;
    }
    $this->_getDesc();
    $verb='\Seolan\Core\DataSource\DataSource('.$this->boid.')';
    \Seolan\Core\Audit::plusplus($verb);
    \Seolan\Core\Logs::debug(__METHOD__.'::construct: end '.$boid.', '.$this->title);
  }

  /// Initialise les options de la source
  public function initOptions() {
    parent::initOptions();
    $group=\Seolan\Core\Labels::getTextSysLabel('Seolan_Model_DataSource_Table_Table','oidstruct');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Model_DataSource_Table_Table','oidstruct1'),'oidstruct1','field',array('table'=>$this->base,'compulsory'=>false),null,$group);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Model_DataSource_Table_Table','oidstruct2'),'oidstruct2','field',array('table'=>$this->base,'compulsory'=>false),null,$group);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Model_DataSource_Table_Table','oidstruct3'),'oidstruct3','field',array('table'=>$this->base,'compulsory'=>false),null,$group);
    $this->_options->setGroupComment(\Seolan\Core\Labels::getTextSysLabel('Seolan_Model_DataSource_Table_Table','oidstructcomment'),'oidstruct1');

    if (\Seolan\Core\Shell::getMonoLang()) {
      $this->_options->delOpt('translate');
      $this->_options->delOpt('auto_translate');
    }
  }

  /// Vide la table
  public function clear($rq=NULL) {
    if(empty($rq) || is_array($rq)) {
      // truncate en premier des tables normalisées si il y en a
      $verifKey = false;
      foreach($this->desc as $field) {
        if($field->get_multivalued() && $field->get_fgender()=='Oid' && $field->normalized){
          $reltable = $field->getRelationTableName();
          if(\Seolan\Core\System::tableExists($reltable)) {
            getDB()->execute('truncate '.$reltable);
            $verifKey = true;
          }
        }
      }
      // Si on a une table normalisée il faut désactiver la vérification des clés étrangères avant truncate
      if($verifKey) {
        $foreign_key_check = getDB()->fetchRow("SHOW session variables like 'foreign_key_checks'");
        getDB()->execute('SET SESSION foreign_key_checks=0');
        getDB()->execute('truncate '.$this->base);
        getDB()->execute('SET SESSION foreign_key_checks=?', array($foreign_key_check['Value']));
      }
      else {
        getDB()->execute('truncate '.$this->base);
      }
      if(\Seolan\Core\System::tableExists('A_'.$this->base)) getDB()->execute('truncate A_'.$this->base);
    }
    else getDB()->execute('delete from '.$this->base.' where '.$rq);
    return array('message'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','update_ok'));
  }

  /// generation d'une condition mysql à partir des parametres
  function make_cond($def, $v, $lang_data=null) {
    $cond='';
    if (!is_array($v)) {
      throw new \Exception('Parameter must be an array with an operator (index 0) and values (index 1)');
    }
    $op=$v[0];
    if(is_array($op)){
      $opfield=$op[0];
      $opmultiple=$op[1];
    }else{
      $opfield=$op;
      $opmultiple='or';
    }
    $k=$def->get_field();

    if(count($v)>2) {
      array_shift($v);
      foreach($v as $v1=>&$v2) {
        if($cond!='') $cond.=' '.$opmultiple.' ';
	$cond.=$this->make_cond($def,array($v[0],$v1));
      }
      return empty($cond) ? '1' : '('.$cond.')';
    }
    if($def->get_multivalued() && $def->get_fgender()=='Oid' && $opfield=='='){
      if ($def->normalized){
	$reltable = $def->getRelationTableName();
	$v2=is_array($v[1])?$v[1]:[$v[1]];
	$v=array_map(function($v) { return getDB()->quote($v); }, $v2);

	$datasource=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($def->table);
	$lang = \Seolan\Core\Shell::getLangData();
	if(!$datasource->isTranslatable()) 
	  $lang=TZR_DEFAULT_LANG;
	$langsrc=getDB()->quote($lang);
	
	$cond = "{$def->table}.KOID in (SELECT {$reltable}.KOIDSRC FROM {$reltable} WHERE $reltable.LANGSRC={$langsrc} AND {$reltable}.KOIDDST IN (".implode(",",$v)."))";
	return empty($cond) ? '1' : '('.$cond.')';
      }
    }
    
    if(is_array($v[1])) {
      foreach($v[1] as $v1=>$v2) {
	if($cond!='') $cond.=' '.$opmultiple.' ';
	$cond.=$this->make_cond($def,array($v[0],$v2));
      }
      return empty($cond) ? '1' : '('.$cond.')';
    }
    // dans le cas ou le parametre d'un link n'est pas un link on rajoute la table en tete
    if($def->isLink() && !empty($v[1]) && ($v[1]!='NULL') && !\Seolan\Core\Kernel::isAKoid($v[1]) && strstr($v[1],'%')==false) {
      $v[1]=$def->get_target().':'.$v[1];
    }
    // traitement sur oid vide
    if($def->get_fgender()=='Oid' && $opfield=='=' && ($v[1]=='NULL' || empty($v[1])))
      $cond=' ('.$this->base.'.'.$k.'="" or '.$this->base.'.'.$k.' is NULL) ';
    // traitement sur oid non vide
    elseif($def->get_fgender()=='Oid' && $opfield=='!=' && ($v[1]=='NULL' || empty($v[1])))
      $cond='  ('.$this->base.'.'.$k.'!="" and '.$this->base.'.'.$k.' is not NULL) ';
    // traitement des valeurs multiples et operateur =
    elseif($def->get_multivalued() && $def->get_fgender()=='Oid' && $v[0]=='='){
      $cond=' ('.$this->base.'.'.$k.'='.getDb()->quote($v[1]).' or INSTR('.$this->base.'.'.$k.','.getDb()->quote('||'.$v[1].'||').')) ';
    }
    // traitement des valeurs avec operateur ==
    elseif($def->get_fgender()=='Oid' && $v[0]=='==') {
      $v1=$def->get_soid_from_set($v[1]);
      $cond=' ('.$this->base.'.'.$k.'='.getDb()->quote($v1).' or INSTR('.$this->base.'.'.$k.','.getDb()->quote('||'.$v1.'||').')) ';
    // traitement des valeurs multiples et operateur !==
    } elseif($def->get_multivalued() && $def->get_fgender()=='Oid' && $v[0]=='!==') {
      $v1=$def->get_soid_from_set($v[1]);
      $cond='  (not('.$this->base.'.'.$k.'='.getDb()->quote($v1).' or INSTR('.$this->base.'.'.$k.','.getDb()->quote('||'.$v1.'||').'))) ';
    // traitement des valeurs non oid
    }else{
      if (!isSQLOperator(trim($opfield))){
	throw new \Exception('Operator "'.$opfield.'" is not a valid SQL operator');
      }
      if($v[1][0]=='=') $cond=' '.$this->base.'.'.$k.' '.$opfield.' '.substr($v[1],1).' ';
      else $cond='  '.$this->base.'.'.$k.' '.$opfield.' '.getDb()->quote($v[1]).' ';
    }
    return $cond;
  }

  function make_simple_cond($k,$v) {
    $cond='';
    $op=$v[0];
    if(is_array($op)){
      $opfield=$op[0];
      $opmultiple=$op[1];
    }else{
      $opfield=$op;
      $opmultiple='or';
    }
    if(count($v)>2) {
      array_shift($v);
      foreach($v as $v1=>&$v2) {
	if($cond!='') $cond.=' '.$opmultiple.' ';
	$cond.=$this->make_simple_cond($k,array($op,$v2));
      }
      return $cond ? '('.$cond.')' : '1';
    }
    if(is_array($v[1])) {
      foreach($v[1] as $v1=>$v2) {
	if($cond!='') $cond.=' '.$opmultiple.' ';
	$cond.=$this->make_simple_cond($k,array($op,$v2));
      }
      return $cond ? '('.$cond.')' : '1';
    }
    if (!isSQLOperator(trim($opfield))){
      throw new \Exception('Operator "'.$opfield.'" is not a valid SQL operator');
    }
    return ' '.$this->base.'.'.$k.' '.$opfield.' '.getDb()->quote($v[1]).' ';
  }

  /**
   * generation d'une condition utilisable sur le container
   * par default, ->quote prend \PDO::PARAM_STR en 2me param
   * jointcond, order, groupby, fields a voir / ->quote
   */
  function select_query($args=NULL) {
    $params=new \Seolan\Core\Param($args, array('cond'=>[]));
    $LANG_DATA = \Seolan\Core\Shell::getLangData($params->get('LANG_DATA'));
    if (array_key_exists('cond', $args))
      $cond = $args['cond'];
    if(empty($cond)) $cond=[];
    $fields = @$args['fields'];
    $select = @$args['select'];
    if(empty($select) && !is_null($fields)) {
      if (is_array($fields)) {
        $fields = array_unique(array_filter($fields));
        $select = $this->base.'.KOID';
        foreach ($fields as $field) {
          if(isset($this->desc[$field])) {
            $field = $this->desc[$field]->get_sqlSelectExpr($this->base);
          }
          if (false == strpos($field, '.')) {
            $select .= ',' . $this->base . '.' . $field;
          } else {
            $select .= ',' . $field;
          }
        }
      } else {
        $select = $this->base.'.KOID '.$fields;
      }
    }
    if(empty($select)) $select = $this->base.'.*';
    $where = @$args['where'];
    $tr=$this->getTranslatable();
    if($tr==TZR_LANG_BASEDLANG) {
      if ($params->is_set('LANG_OTHER')){
	$txt=$this->base.".LANG = ".getDb()->quote($params->get('LANG_OTHER'))." ";
      } else {
	$txt=$this->base.".LANG = ".getDb()->quote($LANG_DATA)." ";
      }
    } elseif($tr==TZR_LANG_FREELANG) {
      $txt=$this->base.".LANG = ".getDb()->quote($LANG_DATA)." ";
    } else {
      $txt=$this->base.".LANG = '".TZR_DEFAULT_LANG."' ";
    }

    if(!empty($where)) $txt.=' AND ('.$where.') ';

    $pu=$this->publishedMode($params);
    if($pu=='public') {
      $txt .= ' AND '.$this->base.".PUBLISH = '1' ";
    }
    foreach($cond as $k => $v) {
      $k1 = trim($k);
      if(!empty($this->desc[$k1])) {
	$def = $this->desc[$k1];
	$txt = $txt.' AND '.$this->make_cond($def, $v, $LANG_DATA);
      } else {
	$txt = $txt.' AND '.$this->make_simple_cond($k1, $v);
      }
    }
    if(isset($args['groupby'])) $txt.=' GROUP BY '.$args['groupby'];
    $jointcond = $params->get('jointcond', 'norequest');
    $order=@$args['order'];
    if(!empty($order)) {
      $torder=explode(',',$order);
      $order=[];
      $order=$this->makeOrder($torder,$order,$jointcond);
      if(!empty($order)) $txt.=' ORDER BY '.$order;
    }
    if(!empty($jointcond))  $query = 'SELECT distinct '.$select.' FROM '.$this->base.' '.$jointcond.' WHERE '.$txt;
    else $query = 'SELECT '.$select.' FROM '.$this->base.' WHERE '.$txt;
    \Seolan\Core\Logs::debug(__METHOD__.' '.$query);
    return $query;
  }

  function random_select_query($args=NULL) {
    $args['order']='RAND(RAND()*20)';
    return $this->select_query($args);
  }


  protected function _getDesc($refresh=false) {
    if(!$refresh && !empty($this->desc)) return;

    $this->desc = [];
    $this->orddesc = [];
    $orderby=' FORDER '	;
    $lang = \Seolan\Core\Shell::getLangUser();
    if($lang!=TZR_DEFAULT_LANG) $lang_cond='(MLANG=? or MLANG="'.TZR_DEFAULT_LANG.'")';
    else $lang_cond="MLANG=? ";
    $rs=getDB()->fetchAll('SELECT * FROM MSGS LEFT JOIN DICT ON DICT.DTAB=MSGS.MTAB AND DICT.FIELD=MSGS.FIELD '.
    			  'WHERE '.$lang_cond.' and MTAB=? and DICT.FIELD is not NULL order by FORDER',array($lang, $this->base));
    $i=0;
    foreach($rs as $ors) {
      $field = $ors['FIELD'];
      if(empty($this->desc[$field]) || (!empty($this->desc[$field]) && ($ors['MLANG']==$lang))) {
	$o = (object)$ors;
        // si la table n'est pas traduisible, aucun champ ne peut etre traduisible, donc on force cette propriete
        if(empty($this->translatable)) $o->TRANSLATABLE=0;
        $this->desc[$field] = \Seolan\Core\Field\Field::objectFactory($o);
        if(!in_array($field, $this->orddesc)) {
          $this->orddesc[$i]=$field;
          $i++;
        }
      }
    }
    unset($rs);
  }

  /// verification que la table des archive existe et construction si elle n'existe pas.
  /// tester sans cache <= lors d'une mise à jour par lot par exemple ?
  protected function checkArchiveTable($createifneeded=true) {
    if(\Seolan\Core\System::tableExists('A_'.$this->base, true)) return true;
    if(!$createifneeded) return false;
    getDB()->execute("CREATE TABLE A_{$this->base} AS SELECT * FROM {$this->base} LIMIT 0");
    getDB()->execute("ALTER TABLE A_{$this->base} ADD PRIMARY KEY (KOID,LANG,UPD) ");
    return true;
  }

  /// Insère une nouvelle table SQL
  static function procNewSource($ar=NULL) {
    global $XLANG;
    $error=false;
    $p=new \Seolan\Core\Param($ar,['translatable'=>0,'auto_translate'=>0,'publish'=>1,'own'=>1,'tag'=>0,'cread'=>0,'bparam'=>[]],'local');
    $bname=$p->get('bname');
    $btab=$p->get('btab');
    $translatable=$p->get('translatable');
    $auto_translate=$p->get('auto_translate');
    $publish=$p->get('publish');
    $own=$p->get('own');
    $tag=$p->get('tag');
    $cread=$p->get('cread');
    $bparam=$p->get('bparam');
    $classname=$p->get('classname');
    if(!empty($auto_translate)) $translatable=1;
    if(empty($classname)) $classname='\Seolan\Model\DataSource\Table\Table';
    // Controle des donnees obligatoires
    if(isSQLKeyword($btab)) {
      $message=$btab.' is a SQL keyword';
      $error=true;
      \Seolan\Core\Logs::notice(__METHOD__,$message);
    } elseif (empty($bname[TZR_DEFAULT_LANG])) {
      $message='Table Name is compulsory in default language ! Try again ...';
      $error=true;
      \Seolan\Core\Logs::notice(__METHOD__,$message);
    } elseif(empty($btab)) {
      $message='SQL Table Name is compulsory ! Try again ...';
      $error=true;
      \Seolan\Core\Logs::notice(__METHOD__,$message);
    } elseif(!preg_match('/^'.\Seolan\Model\DataSource\Table\Table::$tableNamePattern.'$/', $btab)) {
      $message='SQL Table Name not '.\Seolan\Model\DataSource\Table\Table::$tableNamePattern.' checked ! Try again ...';
      $error=true;
      \Seolan\Core\Logs::notice(__METHOD__,$message);
    } else{
      if(self::createTable($btab,$publish,$own,$tag,$cread)) {
	$boid=\Seolan\Core\DataSource\Wizard::getNewBoid();
        if (is_array($bparam)) {
          $json=\Seolan\Core\Options::rawToJSON($bparam,TZR_ADMINI_CHARSET);
        } else { // importSourcesAndFields
          $json=$bparam;
        }

	getDB()->execute('INSERT INTO BASEBASE(BOID,BNAME,BTAB,AUTO_TRANSLATE,TRANSLATABLE,BCLASS,LOG,BPARAM) '.
			 'values(?,?,?,?,?,?,?,?)', [$boid,$bname[TZR_DEFAULT_LANG],$btab,(int)$auto_translate,
						     (int)$translatable,$classname,1,$json]);          
	$XLANG->getCodes();
	for($i=0;$i<$XLANG->nbLang;$i++) {
	  $code=$XLANG->codes[$i];
	  if (isset($bname[$code]) && $bname[$code]!=''){
	    getDB()->execute('INSERT INTO AMSG(MOID,MLANG,MTXT) values (?,?,?)',array($boid,$code,$bname[$code]
));
	  }
	}
	$message='New table '.$bname[TZR_DEFAULT_LANG].' ('.$btab.') created.';
      } else {
	$error=true;
	$message='Could not create '.$bname[TZR_DEFAULT_LANG].' ('.$btab.').';
      }
    }
    return array('message'=>$message,'error'=>$error,'boid'=>$boid??'');
  }

  /// Créé une table SQL et son dictionnaire
  static function createTable($table,$valid=true,$own=true,$tag=false,$cread=false) {
    if(\Seolan\Core\System::tableExists($table)){
      \Seolan\Core\Logs::notice(__METHOD__,"$table '$table' already exists");
      return false;
    }

    $q='CREATE TABLE '.$table.' (KOID varchar(40) DEFAULT \'0\' NOT NULL, LANG char(2) NOT NULL, UPD TIMESTAMP,'.
      ($tag  ?'TAG text,':'').
      ($cread  ?'CREAD timestamp,':'').
      ($own  ?'OWN text,':'').
      ($valid?'PUBLISH tinyint(4) default 1,':'').' PRIMARY KEY (KOID, LANG))';
    getDB()->execute($q);
    if(!\Seolan\Core\System::tableExists($table, true)) return false;
    // Creation du dictionnaire et de msgs
    $order=0;
    getDB()->execute("INSERT INTO DICT values(?,'UPD',?,0,0,0,0,0,1,0,0,'%','')",array($table,'\Seolan\Field\Timestamp\Timestamp'));
    getDB()->execute("INSERT INTO MSGS values('$table','UPD','".TZR_DEFAULT_LANG."','".\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','last_update')."')");
    $order++;
    if($tag) {
      getDB()->execute("INSERT INTO DICT values(?,'TAG',?,0,$order,0,0,0,1,0,0,'%','')",array($table,'\Seolan\Field\Tag\Tag'));
      getDB()->execute("INSERT INTO MSGS values('$table','TAG','".TZR_DEFAULT_LANG."','".\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','tags')."')");
    }
    $order++;
    if($cread) {
      getDB()->execute("INSERT INTO DICT values('$table','CREAD',?,0,0,0,0,0,1,0,0,'%','')",['\Seolan\Field\Timestamp\Timestamp']);
      getDB()->execute("INSERT INTO MSGS values('$table','CREAD','".TZR_DEFAULT_LANG."','".\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','created_date')."' )");
      $order++;
    }
    if($own) {
      getDB()->execute("INSERT INTO DICT values(?,'OWN',?,0,$order,0,1,0,1,0,0,'USERS','')",array($table,'\Seolan\Field\Link\Link'));
      getDB()->execute("INSERT INTO MSGS values('$table','OWN','".TZR_DEFAULT_LANG."','".\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','owner')."' )");
      $order++;
    }
    if($valid) {
      getDB()->execute("INSERT INTO DICT values (?,'PUBLISH',?,0,$order,0,0,0,1,0,0,'%','')",array($table,'\Seolan\Field\Boolean\Boolean'));
      getDB()->execute("INSERT INTO MSGS values ('$table','PUBLISH','".TZR_DEFAULT_LANG."','".\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','approved')."')");
      $order++;
    }
    return true;
  }

  protected function dropSource($table) {
    getDB()->execute('DROP TABLE ' . $table);
  }

  /// Parcours les champs de la table
  function browseFields($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $refresh=$p->get('refresh');
    $addOption = substr($p->get('addOption'), 2); //option additionelle prefixée par __
    $this->_getDesc($refresh);
    $groups=[];
    $allOpts = [];
    if(is_array($this->orddesc)) {
      foreach($this->orddesc as $o => $field) {
        $v=&$this->desc[$field];
        $i=array_search($v->fgroup,$groups);
        if($i===false){
          $i=array_push($groups,$v->fgroup)-1;
        }
        $tableGroup[$field]='group_'.$i;
        //option additionelle
        $optUI = '';
        $get_edit = $addOption !== false;
        foreach ($v->getOptions('addOptions', $get_edit) as $opt) {
          $allOpts[$opt['group']][$opt['field']] = $opt['label'];
          if ($opt['field'] == $addOption) {
            $optUI = $opt['edit'];
          }
        }
        $tableAddOption[$field] = $optUI;
      }
    }
    $result=[];
    $result['tableObject']=$this->desc;
    $result['tableGroup']=$tableGroup;
    $result['groups']=$groups;
    $result['table']=$this->base;
    $result['boid']=$this->boid;
    ksort($allOpts);
    $result['allOpts']=$allOpts;
    $result['addOption']=$tableAddOption;
    $result['translatable'] = $this->getTranslatable(); // Valeur de l'option "Traduire"

    list($types, $labels)=\Seolan\Core\Field\Field::getTypes();
    foreach ($types as $i => $type)
      $type_labels[$type] = $labels[$i];
    $result['type_labels']=$type_labels;
    return $result;
  }

  /// Prepare la création d'un nouveau champ
  function newField($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $ftype=$p->get('ftype');

    if(!empty($ftype)) {
      // on arrive avec le type du champ au moins
      $field=$p->get('field');
      $field=preg_replace('/[^a-zA-Z0-9_]/s','',$field);
      $labels=$p->get('label');
      if(empty($field)) {
	$field=substr(rewriteToAscii($labels[TZR_DEFAULT_LANG],true,true),0,20);
      }
      // creation du champ en memoire
      $v = \Seolan\Core\Field\Field::objectFactory3($ftype, $field, 20);
      $v->set_labels($labels);
      // on ajoute le champ après les autres par defaut
      $v->set_forder($this->newFieldOrder());
      $result=$this->_editField($v);
    } else {
      $result=NULL;
      // Type
      $types=\Seolan\Core\Field\Field::getTypes();
      $type=$types[0];// liste des types
      $type_labels=$types[1];// liste des libelles
      foreach($type as $i=>$ft) {
	if($ftype==$ft ) $type_selectionFlag[]='selected';
	else $type_selectionFlag[]='';
      }
      $result['type']=$type;
      $result['type_labels']=$type_labels;
    }
    return $result;
  }

  /// Enregistre un nouveau champ
  public function procNewField($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('target'=>''));
    $field=$p->get('field');
    $ftype=$p->get('ftype');
    $fcount=$p->get('fcount');
    $forder=$p->get('forder');
    $compulsory=$p->get('compulsory');
    $queryable=$p->get('queryable');
    $browsable=$p->get('browsable');
    $translatable=$p->get('translatable');
    $multivalued=$p->get('multivalued');
    $published=$p->get('published');
    $label=$p->get('label');
    $target=$p->get('target');
    $options=$p->get('options');
    $error=false;
    $table=$this->getTable();
  
    // Controle des données obligatoires
    if(!$ftype::fieldDescIsCorrect($field,$ftype,$fcount,$forder,$compulsory,$queryable,$browsable,$translatable,$multivalued,$published,$target,$label,$options) || 
       !$this->newFieldDescIsCorrect($field,$ftype,$fcount,$forder,$compulsory,$queryable,$browsable,$translatable,$multivalued,$published,$target,$label)       
) {
      $message='Incorrect new field parameters for '.$label[TZR_DEFAULT_LANG].' ! Try again.<br/>';
      $error=true;
    }elseif($this->fieldExists($field)){
      $message='Field '.$field.' already exists ! Try again ...<br/>';
      $error=true;
    }else{
      if(!$this->newDesc($field,$ftype,$fcount,$forder,$compulsory,$queryable,$browsable,$translatable,$multivalued,$published,$target,$label,$options)){
        $message = 'Field '.$field.' error creating desc'.$ftype.'<br/>';
        $error = true;
      }else{
        $this->sql_newFieldDesc($field);
        $this->majUpOtherFieldOrder($field,$forder);
        $this->sql_newField($field);
        $message = 'Field '.$label[TZR_DEFAULT_LANG].' created<br/>';
      }
    }
    \Seolan\Core\Logs::update('newfield',NULL,$table.':'.$field.' '.$message);
    $result=array('message'=>$message,'error'=>$error);
    return $result;
  }

  /// Ajoute un champ du desc dans le dictionnaire
  function sql_newFieldDesc($field) {
    if(!is_array($this->desc)) return false;
    if(empty($this->desc[$field])) return false;

    // Insertion dans DICT
    $def=$this->desc[$field];
    $ftype=$def->get_ftype();
    $fcount=$def->get_fcount();
    $forder=$def->get_forder();
    $compulsory=($def->get_compulsory()?"1":"0");
    $queryable=($def->get_queryable()?"1":"0");
    $browsable=($def->get_browsable()?"1":"0");
    $translatable=($def->get_translatable()?"1":"0");
    $multivalued=($def->get_multivalued()?"1":"0");
    $published=($def->get_published()?"1":"0");
    $target=$def->get_target();
    $dparam=$def->_options->toJSON($def);
    //$dparam=$def->_options->toXML($def); ancience code laissé en commentaire au cas ou aurait besoin de repaser dparam en xml.
    getDB()->execute('INSERT INTO DICT (DTAB,FIELD,FTYPE,FCOUNT,FORDER,COMPULSORY,QUERYABLE,BROWSABLE,TRANSLATABLE,MULTIVALUED,PUBLISHED,'.
		     'TARGET,DPARAM) values (?,?,?,?,?,?,?,?,?,?,?,?,?)',
		     array($this->base,$field, $ftype, $fcount, $forder, $compulsory, $queryable, $browsable, $translatable, $multivalued,
			   $published,$target,$dparam));

    // Insertion des labels dans MSGS
    $msg=$def->get_labels();
    foreach($msg as $lg=>$m) {
      // on fait le ménage au cas où avant
      getDB()->execute('DELETE FROM MSGS WHERE MTAB=? AND FIELD=? and MLANG=?', array($this->base,$field,$lg));
      // insertion du libellé en base
      getDB()->execute('INSERT INTO MSGS (MTAB,FIELD,MLANG,MTXT) values (?,?,?,?)',
		       array($this->base,$field,$lg,$m));
    }
    return true;
  }

  /// Ajout du champ dans la table SQL
  function sql_newField($field) {
    if(!is_array($this->desc)) return false;
    if(empty($this->desc[$field])) return false;
    $def=$this->desc[$field];
    $sqltype=$def->sqltype();

    getDB()->execute('alter table '.$this->base.' add `'.$field.'` '.$sqltype.' DEFAULT '.$def->getDefaultValueSqlExpression());

    // Ajoute le champ à la table des archives si elle existe
    if(\Seolan\Core\System::tableExists('A_'.$this->base)) getDB()->execute('alter table A_'.$this->base.' add '.$field.' '.$sqltype);
  }

  /// Prepare la création / l'édition d'un champ
  public function editField($ar=NULL) {
    GLOBAL $XLANG;
    $p=new \Seolan\Core\Param($ar,NULL);
    $field=$p->get("field");
    $XLANG->getCodes();
    $result=NULL;
    if(is_array($XLANG->codes) && is_array($this->desc)) {
      $v=$this->desc[$field];
      $result=$this->_editField($v);
    }
    return $result;
  }

  public function _editField($v) {
    $field=$v->get_field();
    // Labels
    GLOBAL $XLANG;
    $XLANG->getCodes();
    $labels=$v->get_labels();
    for($myi=0;$myi<$XLANG->nbLang;$myi++) {
      $fnames[$myi]=@$labels[$XLANG->codes[$myi]];
    }

    // Number
    $fnumber=$field;
      // Type
    $types=\Seolan\Core\Field\Field::getTypes();
    $type=$types[0];// liste des types
    $type_labels=$types[1];// liste des libelles
    foreach($type as $i=>$ft) {
      if($v->get_ftype()==$ft ) $type_selectionFlag[]='selected';
      else $type_selectionFlag[]='';
    }
    $fcount=$v->get_fcount();
    $compulsory=$v->get_compulsory();
    $queryable=$v->get_queryable();
    $browsable=$v->get_browsable();
    $btranslatable=$this->isTranslatable();
    $translatable=$v->get_translatable();
    $multivalued=$v->get_multivalued();
    $published=$v->get_published();
    $liste=$this->getBaseList();
    reset($liste);
    $target_key=array(TZR_DEFAULT_TARGET);
    $target_val=array('---');
    $target_selectionFlag=array('');
    foreach($liste as $k=>$my) {
      $my_xst=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($k);
      if($my_xst->isTherePublishedField()) {
        $target_key[]=$k;
        $target_val[]=$my;
        if($v->get_target()==$k ) $target_selectionFlag[]="selected";
        else $target_selectionFlag[]="";
      }
    }
    $forder=$v->get_forder();
    $opts=$v->getOptions('options');
    $result['options']=$opts;
    $result['table']=$this->base;
    $result['boid']=$this->boid;
    $result['field']=$field;
    $result['fnames']=$fnames;
    $result['fnumber']=$fnumber;
    $result['type_selectionFlag']=$type_selectionFlag;
    $result['type']=$type;
    $result['type_labels']=$type_labels;
    $result['fcount']=$fcount;
    $result['ftype']=$v->get_ftype();
    $result['compulsory']=$compulsory;
    $result['queryable']=$queryable;
    $result['browsable']=$browsable;
    $result['btranslatable']=$btranslatable;
    $result['translatable']=$translatable;
    if($v->isMultiValuable()) {
      $result['multivalued']=$multivalued; 
      $result['multivaluable']=true; //multivaluable défini si on affiche la checkbox "Valeurs multiples" ou pas 
    }
    else{
      $result['multivalued']=false; //on multivalued a false car on va pas l'uttilisé
      $result['multivaluable']=false; //multivaluable défini si on affiche la checkbox "Valeurs multiples" ou pas 
    }  
    
    $result['published']=$published;
    $result['target_key']=$target_key;
    $result['target_val']=$target_val;
    $result['target_selectionFlag']=$target_selectionFlag;
    $result['forder']=$forder;
    return $result;
  }

  /// Enregistre les modifications sur un champ
  function procEditField($ar=NULL){
    global $XLANG;
    $XLANG->getCodes();
    $p=new \Seolan\Core\Param($ar,array('batch'=>'0','options'=>NULL,'updateotherorder'=>true));
    $updateotherorder=$p->get('updateotherorder');
    $field=$p->get('field');
    $def=$this->desc[$field];
    // Pour chaque parametre, on verifie si une nouvelle valeur est spécifiée, sinon on garde l'ancienne
    $ftype=$p->get('ftype');
    if(!$ftype) $ftype=$def->get_ftype();
    $fcount=$p->get('fcount');
    if(!$fcount) $fcount=$def->get_fcount();
    $forder=$p->get('forder');
    if(!empty($forder)) {
      $forder = $this->_guessFieldOrder($forder);
    } else {
      $forder = $def->get_forder();
    }
    $compulsory=$p->get('compulsory');
    $compulsory_hid=$p->get('compulsory_HID');
    if(!$compulsory && $compulsory!==false && $compulsory_hid!=2) $compulsory=$def->get_compulsory();
    else $compulsory=($compulsory=='on'?1:0);
    $queryable=$p->get('queryable');
    $queryable_hid=$p->get('queryable_HID');
    if(!$queryable && $queryable!==false && $queryable_hid!=2) $queryable=$def->get_queryable();
    else $queryable=($queryable=='on'?1:0);
    $browsable=$p->get('browsable');
    $browsable_hid=$p->get('browsable_HID');
    if(!$browsable && $browsable!==false && $browsable_hid!=2) $browsable=$def->get_browsable();
    else $browsable=($browsable=='on'?1:0);
    $translatable=$p->get('translatable');
    $translatable_hid=$p->get('translatable_HID');
    if(!$translatable && $translatable!==false && $translatable_hid!=2) $translatable=$def->get_translatable();
    else $translatable=($translatable=='on'?1:0);
    $multivalued=$p->get('multivalued');
    $multivalued_hid=$p->get('multivalued_HID');
    if(!$multivalued && $multivalued!==false && $multivalued_hid!=2)
      $multivalued=$def->get_multivalued();
    else
      $multivalued=($multivalued=='on'?1:0);
    $published=$p->get('published');
    $published_hid=$p->get('published_HID');
    if(!$published && $published!==false && $published_hid!=2) $published=$def->get_published();
    else $published=($published=='on'?1:0);
    $label=$p->get('label');
    $target=$p->get('target');
    if(empty($target)) $target=$def->get_target();
    $batch=$p->get('batch');
    $options=$p->get('options');
    $error=false;

    // Quand on passe un champ fichier de multivalué à monovalué on lance une alerte
    if(is_a($def, "\Seolan\Field\File\File") && !$multivalued && $def->get_multivalued()) {
      Shell::alert(sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Model_DataSource_Table_Table','multifilewarning'), $field));
    }

    // Remplissage des options non definies
    if(!$ftype::fieldDescIsCorrect($field,$ftype,$fcount,$forder,$compulsory,$queryable,$browsable,$translatable,$multivalued,$published,$target,$label,$options) 
       || !$this->fieldDescIsCorrect($field,$ftype,$fcount,$forder,$compulsory,$queryable,$browsable,$translatable,$multivalued,$published,$target,$label)) {
      $message='Incorrect description ! Try again.<br/>';
      $error=true;
    }else{
      // pour prendre en compte les modifications d'options éventuelles
      $def->_options->procDialog($def,$options);
      $this->sql_changeField($field,$ftype,$fcount, $options);
      $this->sql_changeFieldDesc($field,$ftype,$fcount,$forder,$compulsory,$queryable,$browsable,$translatable,$multivalued,$published,$target,$label,$XLANG->codes,$options);
      if($updateotherorder) $this->majOtherFieldOrder($field,$def->get_forder(),$forder);
      $this->changeDesc($field,$ftype,$fcount,$forder,$compulsory,$queryable,$browsable,$translatable,$multivalued,$published,$target,
			$label);
      $message='Field '.$field.' modified.<br/>';
      // Convertit les données présentes en base si necessaire
      $this->desc[$field]->convertValues($def->get_ftype());
      // Efface le cache
      \Seolan\Core\DbIni::clear('modules'.$this->base);
      // efface le cache des chronos
      \Seolan\Core\DbIni::clear('Chrono%'.$this->base.'%');
    }
    return array('message'=>$message,'error'=>$error);
  }

  /// Modifie un champ du desc dans le dictionnaire
  function sql_changeFieldDesc($field,$ftype,$fcount,$forder,$compulsory,$queryable,$browsable,$translatable,$multivalued,$published,$target,$label,$langs,$options=NULL) {
    // mise en forme des valeurs logiques pour sql
    foreach(['compulsory','queryable','browsable','translatable','multivalued','published'] as $pname){
      if (!isset($$pname) || $$pname === false)
	$$pname = '0';
    }
    
    $def=$this->desc[$field];
    $t1=$def->_options->toJSON($def);
    getDB()->execute('UPDATE DICT set FTYPE=?,FCOUNT=?,FORDER=?,COMPULSORY=?,'.
                     'QUERYABLE=?,BROWSABLE=?,TRANSLATABLE=?,MULTIVALUED=?,'.
                     'PUBLISHED=?,TARGET=?,DPARAM=? where FIELD=? and DTAB=?',
                     array($ftype, $fcount, $forder, $compulsory, $queryable, $browsable, $translatable, $multivalued, $published, $target, $t1,
			   $field, $this->base));

    $oldlabel=$def->get_labels();
    foreach($langs as $l){
      if(!isset($oldlabel[$l])
	    || $label[$l]!=$oldlabel[$l]){
	if($label[$l]!==''){
	  if(array_key_exists($l, $oldlabel))
	    getDB()->execute('UPDATE MSGS set MTXT=? where MTAB=? and FIELD=? and MLANG=?', array($label[$l], $this->base, $field, $l));
	  else
	    getDB()->execute('INSERT IGNORE INTO MSGS (MTAB,FIELD,MLANG,MTXT) values (?,?,?,?)', array($this->base, $field, $l, $label[$l]));
	}else{
	  getDB()->execute('DELETE FROM MSGS where MTAB=? and FIELD=? and MLANG=?', array($this->base, $field, $l));
	}
      }
    }
  }

  /// Modifie un champ dans la table SQL
  function sql_changeField($field,$ftype,$fcount, $options) {
    $def=$this->desc[$field];
    $fielddesc=getColumnDesc($this->base, $field);
    $defaultvalue=$fielddesc['Default'];
    if($def->get_ftype()!=$ftype || $def->get_fcount()!=$fcount || $defaultvalue!==@$options['default']) {
      $t['FTYPE']=$ftype;
      $t['FCOUNT']=$fcount;
      $t['FIELD']='bidon';
      $obj=(object)$t;
      $new=\Seolan\Core\Field\Field::objectFactory($obj);
      $new->set_fcount($fcount);
      $new->multivalued=$def->multivalued;
      $sqltype=$new->sqltype();
      if (isset($options['default']) && $options['default'] !== '') {
	$new->default=$options['default'];
	getDB()->execute('alter table '.$this->base.' modify `'.$field.'` '.$sqltype.' DEFAULT '. $new->getDefaultValueSqlExpression());
	// ? avec les version récentes mdb, ne sert peut-être plus ?
	getDB()->execute('UPDATE '.$this->base.' SET UPD=UPD, `'.$field.'` = ? WHERE '.$field.' IS NULL', [$options['default']]);
      } else {
	getDB()->execute('alter table '.$this->base.' modify `'.$field.'` '.$sqltype);
      }

      // Modifie la table des archives si elle existe
      if(\Seolan\Core\System::tableExists('A_'.$this->base)) getDB()->execute('ALTER TABLE A_'.$this->base.' MODIFY '.$field.' '.$sqltype);
    }
  }

  /// Suppression d'un champ
  function delField($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $field=$p->get('field');
    $message='';
    $error=false;
    if(!$this->deleteIsSafe($message,$field)) {
      $message.="<br/>Field $field not deleted, $message<br/>";
      $error=true;
    }else{
      $def=$this->desc[$field];
      // Action specifique sur suppression d'un champ, par exemple suppression des fichier
      $def->delfield();
      // Suppression du tuple dans les tables
      getDB()->execute('DELETE FROM SETS WHERE STAB=? AND FIELD=?', array($this->base,$field));
      $this->sql_delFieldDesc($field);
      $this->majDownOtherFieldOrder($field,$def->get_forder());
      $this->sql_delField($field);
      $this->delDesc($field);
      // Suppression des droits sur le champ
      $mods=\Seolan\Core\Module\Module::modulesUsingTable($this->base,true,false,false);
      if(!empty($mods)) {
	foreach($mods as $moid => $name) {
	  getDB()->execute('delete from ACL4 where AMOID=? and AKOID=?',array($moid, '_field-'.$field));
	}
      }
      // Il n'y a plus de champ de description. Suppression des tuples de data dans Txxx et \Seolan\Core\Kernel et les annexes
      if(!count($this->desc)) $this->clear();
      $message.='Field '.$field.' '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','deleted').'.<br/>';
    }
    \Seolan\Core\Logs::update('delfield', NULL, $this->base.':'.$field.' '.$message);
    \Seolan\Core\DbIni::clear('modules'.$this->base);
    return array('message'=>$message,'error'=>$error);
  }

  /// Verifie si un champ peut etre supprimé
  function deleteIsSafe(&$out,$field) {
    // Verifie que ce n'est pas un champ systeme
    if(in_array($field,array('UPD','KOID','LANG'))) {
      $out='Champ obligatoire : impossible de supprimer';
      return false;
    }
    if(!fieldExists($this->base, $field)) return false;
    $def=$this->desc[$field];
    $fieldLabel=$def->get_label();
    // Le champ est publié
    if($def->get_published()) {
      // Comptage du nombre de champs published sur la table
      if($this->getPublishedFieldCount()==1) {
	$links=\Seolan\Model\DataSource\Table\Table::fieldsUsingTable($this->base);
	// Des champs utilisent cette table comme source de données
        if(count($links)) {
          if(count($this->desc)==1) {
	    // Le champ est le dernier de la table, il ne peut etre supprimé
            $out.='Field '.$fieldLabel.' ('.$field.') is the only published field !<br/>';
	    $out.='No other field can be set published instead ...<br/>';
	    $out.='Delete is not safe.<br/>';
	    return false;
          }else{
	    // Le champ n'est pas le dernier de la table, on met d'office le premier field a published
            $ors=getDB()->fetchRow('select FIELD from DICT where DTAB=? and FORDER!=? order by FORDER', array($this->base,$def->get_forder()));
	    if($ors){
	      $f=$ors['FIELD'];
              $fdef=$this->desc[$f];
              $fLabel=$fdef->get_label();
	      $fdef->set_published(true);
	      getDB()->execute('UPDATE DICT set DTAB=? and PUBLISHED=1 where FIELD=?',array($this->base,$f));
              $out.='Field '.$fieldLabel.' ('.$field.') was the only published field !<br/>';
              $out.='Field '.$fLabel.' ('.$f.') is automatically set published instead ...<br/>';
	    }
	    $out.='Delete is now safe.<br/>';
          }
        }
      }
    }
    return true;
  }

  /// Supprime un champ du desc du dictionnaire
  function sql_delFieldDesc($field) {
    getDB()->execute('DELETE FROM DICT where FIELD=? and DTAB=?', [$field, $this->base]);
    getDB()->execute('DELETE FROM MSGS where MTAB=? and FIELD=?', [$this->base, $field]);
    getDB()->execute('DELETE FROM AMSG where MOID LIKE ?', [$this->base.':'.$field.':%']);
  }

  // maj des la structure de la table Txxx pour suppression de la colonne Fxxxx
  //
  function sql_delField($field) {
    $def=$this->desc[$field];
    // drop column col_name
    $requete = "alter table " . $this->base . " drop $field";
    getDB()->execute($requete);
    if(\Seolan\Core\System::tableExists('A_'.$this->base)) {
      $requete = "alter table A_" . $this->base . " drop $field";
      getDB()->execute($requete);
    }
  }

  /// Met à jour l'ordre des champs dans DICT par le haut
  function majUpOtherFieldOrder($field,$forder) {
    getDB()->execute('UPDATE DICT SET FORDER=FORDER+1 where DTAB=? and FIELD!=? and FORDER>=?', [$this->base, $field, $forder]);
  }

  /// Met à jour l'ordre des champs dans DICT par le bas
  function majDownOtherFieldOrder($field,$forder) {
    getDB()->execute('UPDATE DICT SET FORDER=FORDER-1 where DTAB=? and FIELD!=? and FORDER>?',[$this->base, $field, $forder]);
  }

  /// Met à jour l'ordre des champs dans DICT
  function majOtherFieldOrder($field,$oldOrder,$newOrder) {
    // Si augmente, -- sur les order > old et <= new
    if($newOrder>$oldOrder ){
      getDB()->execute('UPDATE DICT set FORDER=FORDER-1 where DTAB=? and FIELD!=? and FORDER>? and '.
		       'FORDER<=?', [$this->base, $field, $oldOrder, $newOrder]);
    }
    // Si diminue, ++ sur les order >= new et < old
    if($newOrder<$oldOrder) {
      getDB()->execute('UPDATE DICT set FORDER=FORDER+1 where DTAB=? and FIELD!=? and FORDER>=? and '.
		       'FORDER<?', [$this->base, $field, $newOrder, $oldOrder]);
    }
  }

  /// Récupère le nombre de champ publiés
  function getPublishedFieldCount() {
    return getDB()->fetchOne("select count(*) from DICT where DTAB=? and PUBLISHED=1",
			     [$this->base],
			     false,
			     \PDO::FETCH_NUM);
  }

  /// Genere un nouveau nom de table
  static function newTableNumber($prefix='T',$add=0) {
    // Force la mise à jour du cache
    \Seolan\Core\System::tableExists('',true);
    $tmax=$prefix.'001';
    while(1){
      if(\Seolan\Core\System::tableExists($tmax) ||
         \Seolan\Core\DataSource\DataSource::sourceExists($tmax) ||
         getDB()->fetchOne('select count(*) from DICT where DTAB=?',array($tmax)) ||
         getDB()->fetchOne('select count(*) from MSGS where MTAB=?',array($tmax))
      ){
        $tmax++;
      } elseif($add){
	$tmax++;
	$add--;
      } else break;
    }
    return $tmax;
  }

  /// Prepare la duplication d'une source
  public function duplicateDataSource($ar=NULL){
    return array('tablenamepattern'=>\Seolan\Model\DataSource\Table\Table::$tableNamePattern, 'newtable'=>\Seolan\Model\DataSource\Table\Table::newTableNumber(),'mtxt'=>'- '.$this->getSourceName(),'boid'=>$this->boid);
  }

  /// Duplique la source
  public function procDuplicateDataSource($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>''));
    $tplentry = $p->get('tplentry');
    $bnewtab=$p->get('newtable');
    $bnewoid=\Seolan\Core\DataSource\Wizard::getNewBoid();
    $bnewlib=$p->get('mtxt');
    $data=$p->get('data');
    $btab=$this->base;
    $boid=$this->boid;
    if(empty($bnewtab) || (!empty($bnewtab) && \Seolan\Core\System::tableExists($bnewtab))) $bnewtab = \Seolan\Model\DataSource\Table\Table::newTableNumber();
    if(empty($bnewlib)){
      $ors=getDB()->fetchRow('SELECT * FROM AMSG WHERE MOID=? LIMIT 0,1', array($boid));
      $bnewlib='- '.$ors['MTXT'];
    }

    // Duplication du dictionnaire
    getDB()->execute("CREATE TEMPORARY TABLE tmpdict AS SELECT * FROM DICT where DTAB=?",array($btab));
    getDB()->execute("UPDATE tmpdict set DTAB=?",array($bnewtab));
    // Duplication du nom de la table et des commentaires de champ
    getDB()->execute('CREATE TEMPORARY TABLE tmpmsg as select * from AMSG where MOID="'.$boid.'" or MOID REGEXP "^'.$btab.':.+$"');
    getDB()->execute('UPDATE tmpmsg set MOID=?,MTXT=? where MOID=?',array($bnewoid,$bnewlib,$boid));
    getDB()->execute('UPDATE tmpmsg set MOID=REPLACE(MOID,"'.$btab.'","'.$bnewtab.'") where MOID REGEXP "^'.$btab.':.+$"');
    // Duplications des libelles des champs
    getDB()->execute("CREATE TEMPORARY TABLE tmpmsgs AS SELECT * FROM MSGS WHERE MTAB='$btab'");
    getDB()->execute("UPDATE tmpmsgs SET MTAB='".$bnewtab."'");
    getDB()->execute("CREATE TEMPORARY TABLE tmpsets AS SELECT * FROM SETS WHERE STAB='$btab'");
    getDB()->execute("UPDATE tmpsets set STAB='".$bnewtab."'");
    // Duplication de l'entree dans basebase
    getDB()->execute('CREATE TEMPORARY TABLE tmpbase AS SELECT * FROM BASEBASE where BOID=?',array($boid));
    getDB()->execute("UPDATE tmpbase set BTAB='".$bnewtab."',BOID='$bnewoid'");
    // Create de la table
    getDB()->execute('INSERT INTO DICT select * from tmpdict');
    getDB()->execute('INSERT INTO AMSG select * from tmpmsg');
    getDB()->execute('INSERT INTO MSGS select * from tmpmsgs');
    getDB()->execute("INSERT INTO SETS select SOID, STAB, FIELD, SLANG, STXT, SORDER from tmpsets");
    getDB()->execute('INSERT INTO BASEBASE select * from tmpbase');
    getDB()->execute('CREATE TABLE '.$bnewtab.' like '.$btab);
    if($data){
      getDB()->execute('INSERT INTO '.$bnewtab.' (select * from '.$btab.')');
      getDB()->execute('UPDATE '.$bnewtab.' SET UPD=UPD,KOID=substr(replace(KOID,"'.$btab.':","'.$bnewtab.':"), 1, 40)');
      // copie des donnees
      Global $DATA_DIR;
      \Seolan\Library\Dir::copy($DATA_DIR.$btab, $DATA_DIR.$bnewtab, true);
    }
    getDB()->execute('DROP TEMPORARY TABLE tmpbase');
    getDB()->execute('DROP TEMPORARY TABLE tmpdict');
    getDB()->execute('DROP TEMPORARY TABLE tmpmsg');
    getDB()->execute('DROP TEMPORARY TABLE tmpmsgs');
    getDB()->execute('DROP TEMPORARY TABLE tmpsets');
    return array('message'=>'Duplication finished','boid'=>$bnewoid,'table'=>$bnewtab);
  }

  /// Check/repare la source de donnée
  public function chk($ar=NULL) {
    $msg='';

    // verification de la presence des champs LANG et KOID
    if(!fieldExists($this->base, 'KOID')) {
      $msg.=$this->base.': KOID field must exist<br>';
      return array('message'=>$msg);
    }
    if(!fieldExists($this->base, 'LANG')) {
      $msg.=$this->base.': LANG field must exist<br>';
      return array('message'=>$msg);
    }

    // Verification des champs
    $repair=false;
    foreach($this->desc as $k=>$v) {
      if(!fieldExists($this->base, $k)) $msg.=$this->base.':'.$k.' fields exist in catalog not in table<br>';
      else{
	$v->chk($msg);
	if($repair) $v->repair($msg);
      }
    }

    // Verifications des langues
    $translatable=$this->getTranslatable();
    if(!$translatable) {
      $toclean = getDB()->select('select count(*) from '.$this->base.' where LANG!=?',[TZR_DEFAULT_LANG])->fetch(\PDO::FETCH_COLUMN);
      if ($toclean)
        getDB()->execute('delete from '.$this->base.' where LANG!=?',array(TZR_DEFAULT_LANG));
    }elseif($translatable!=TZR_LANG_FREELANG) {
      // propgation complete des langues équivalentes si elles sont configurées
      $langsPropagate = $this->getAllLangsRepli();
      if (count($langsPropagate) > 0){
	$this->repairLangPropagate($langsPropagate);
      }
      // dans tous les cas :
      // propagation des champs non traduisibles de la langue de base vers les autres langues
      $oids=getDB()->fetchCol('SELECT KOID FROM '.$this->base.' where LANG=?',
			      array(TZR_DEFAULT_LANG));
      foreach($oids as $oid) $this->propagateOnOtherLangs($oid);
      unset($oids);

    }

    // Vérification des donnees: suppression des données qui ne sont pas dans les langues referencées
    $toclean = getDB()->select('select count(*) from '.$this->base.' where LANG not in ("'.implode('","', array_keys($GLOBALS['TZR_LANGUAGES'])).'")')->fetch(\PDO::FETCH_COLUMN);
    if ($toclean)
      getDB()->execute('delete from '.$this->base.' where LANG not in ("'.implode('","', array_keys($GLOBALS['TZR_LANGUAGES'])).'")');
    return array('message'=>$msg);
  }

  /**
   * Propage le contenu d'une langue sur les autres, pour tous les champs
   * -> equivalence de langues
   * -> on ne propage pas les champs de gestion des langues, qu'ils soient console(langrepli) ou sql pur (lang)
   * @param String $oid : oid de la fiche à de base (donnnées à propager)
   * @param String $lang : code langue des données à propager
   * @param array $otherlangs : liste des codes langues destinataires
   */
  function propagateLangOnOtherLangs($oid, $lang, $otherlangs) {
    if (!$this->isTranslatable())
      return;
    // Recuperation des valeurs de la langue donnée
    if ($ors = getDB()->fetchRow('SELECT * FROM ' . $this->base . ' WHERE KOID=? AND LANG=?', array($oid, $lang))) {
      // mises à jour ou insertions
      $updatablesLangs = getDB()->fetchCol('SELECT LANG FROM ' . $this->base . ' WHERE KOID=? and FIND_IN_SET(LANG,?)', array($oid, implode(',', $otherlangs)));
      $insertLangs = array_diff($otherlangs, $updatablesLangs);

      if (count($updatablesLangs) > 0){
	$rq = 'UPDATE ' . $this->base . ' set KOID=? '; // pour avoir un champ
	$inputvalues = array($oid);
	foreach ($this->desc as $k => $v) {
	  // on ne replique pas les champs langues
	  if (!in_array($k, static::$langfields)) {
	    $rq .= ', ' . $k . ' = ?';
	    $inputvalues[] = $ors[$k];
	  }
	}
	$inputvalues[] = $oid;
	$inputvalues[] = implode(',', $otherlangs);
	$rq .= ' where KOID=? and FIND_IN_SET(lang,?)';
	getDB()->execute($rq, $inputvalues);
      }

      if (count($insertLangs)>0){
	$dk = new \Seolan\Core\Kernel();
	// creation des données dans les langues cibles
	foreach($insertLangs as $destCode){
	  $dk->data_duplicate($oid, $lang, $destCode);
	}
      }

    }
  }
  /// reparation en asynchrone d'une table donnée
  static function bacthRepairLangPropagate($table){
    \Seolan\Core\DataSource\DataSource::objectFactory($table)->repairLangPropagate();
  }
  /// Propagation des données des langues identiques
  function repairLangPropagate($langsPropagate=null) {
    if ($langsPropagate == null){
      $langsPropagate = $this->getAllLangsRepli();
    }
    foreach ($langsPropagate as $ocode => $dcodes) {
      // lecture des oid dans la langue source
      foreach (getDB()->fetchCol('SELECT KOID FROM ' . $this->getTable() . ' where LANG=?', array($ocode)) as $oid) {
	\Seolan\Core\Logs::notice(__METHOD__, 'propagate '.$oid.' in '.$ocode.' on ' . implode(',', $dcodes));
	$this->propagateLangOnOtherLangs($oid, $ocode, $dcodes);
      }
    }
  }
  /**
   * tableau global des langues équivalentes
   */
  function getAllLangsRepli(){
    $langsPropagate = [];
    $codes = array_keys($GLOBALS['TZR_LANGUAGES']);
    foreach ($codes as $code) {
      $prop = 'langrepli_' . $code;
      if (isset($this->$prop) && in_array($this->$prop, $codes)) {
	$ocode = $this->$prop;
	if (!isset($langsPropagate[$ocode])) {
	  $langsPropagate[$ocode] = [];
	}
	// note : l'édition fait en sorte que  la langue de base n'est jamais dans les destinataires
	$langsPropagate[$ocode][] = $code;
      }
    }
    return $langsPropagate;
  }
  /**
   * liste des langues de replication / propagation
   * @param Array $authorizedLangs : liste des langues autorisées
   * @param String $langupdated : langue qui est mise à jour
   * @return Array : la liste des langues équivalentes
   */
  function getLangsRepli($langupdated, $authorizedLangs=[]){
    if (!$this->getTranslatable() && !$this->getAutoTranslate()){
      return [];
    }
    $langreplis = [];
    foreach(array_keys($GLOBALS['TZR_LANGUAGES']) as $code){
      if ($code == TZR_DEFAULT_LANG){
	continue;
      }
      $prop = 'langrepli_'.$code;
      if (isset($this->$prop) && $this->$prop == $langupdated
	  && in_array($code, $authorizedLangs)){
	$langreplis[] = $code;
      }
    }
    return $langreplis;
  }
  /// Création des traductions manquantes
  function repairTranslations($ar=null){
    if(TZR_LANG_BASEDLANG != $this->getTranslatable() || !$this->getAutoTranslate()) {
      return array('message'=>'Invalid table translation properties : translations not created.');
    }
    ini_set('max_execution_time', 0); // la duplication des fichiers 
    $xk=new \Seolan\Core\Kernel();
    $oids = getDB()->fetchCol('SELECT KOID FROM '.$this->getTable().' where LANG=?',
			      array(TZR_DEFAULT_LANG));
    foreach($oids as $oid){
      $xk->data_autoTranslate($oid);
    }
    
    return array('message'=>'ok,'.count($oids).' row(s) checked');
  }

  /**
   * Après mise à jour des propriétés de base :
   * - langues équivalentes si table traduisible
   */
  function procEditProperties($ar=null) {
    $update = false;

    if (TZR_LANG_BASEDLANG == $this->translatable){
      $p=new \Seolan\Core\Param($ar,[]);
      $options=$p->get('options');
      foreach (array_keys($GLOBALS['TZR_LANGUAGES']) as $langcode){
	$propName = 'langrepli_'.$langcode;
	if ($this->$propName != $options[$propName]){
	  $update = true;
	  break;
	}
      }
    }

    parent::procEditProperties($ar);

    // mise à jour des valeurs en base pour les langues "identiques" quand la conf. change
    // report par xbatch si trop de lignes à traiter
    if ((TZR_LANG_BASEDLANG == $this->translatable) && $update) {
      $allLangReplis = $this->getAllLangsRepli();
      if (($nb=count($allLangReplis)) > 0){
	$nrq = getDb()->fetchOne('select count(*) from '.$this->getTable().' where  (LANG='.implode(' OR LANG=', array_fill(0, $nb, '?')).')', array_keys($allLangReplis));
	\Seolan\Core\Logs::notice(__METHOD__, 'update table '.$this->getTable().' apply langrepli about '.$nrq.' queries');
	if ($nrq <= 1000){
	  \Seolan\Core\Shell::setNextData('message', 'Data updated according to equivalent languages configuration.');
	  $this->repairLangPropagate();
	} else {
	  \Seolan\Core\Shell::setNextData('message', 'Data update for equivalent languages defered in batch mode.');
	  (new \Seolan\Core\Batch())->addAction('Update LangPropagate '.$this->getTable(), '\Seolan\Core\DataSource\DataSource::objectFactoryHelper8("'.$this->getTable().'")->repairLangPropagate();');
	}
      }
    } else if (TZR_LANG_BASEDLANG == $this->translatable) {
      \Seolan\Core\Logs::notice(__METHOD__, 'Not need to update data for equivalent languages');
    }
  }
  /// Exporte les champs de la source dans une feuille d'un objet PHPExcel
  function exportSpec(&$sheet){
    $headers=array('table','forder');
    $langs=array_keys($GLOBALS['TZR_LANGUAGES']);
    foreach($langs as $lg) $headers[]='label['.$lg.']';
    $headers=array_merge($headers,array('field','ftype','fcount','compulsory','queryable','translatable','browsable','multivalued','published','target'));
    $rows=[];
    $table=$this->getTable();
    foreach($this->desc as $fn=>&$f){
      $row=[];
      $row['table']=$table;
      $row['forder']=$f->forder;
      $labels=[];
      $rs=getDB()->fetchAll('select * from MSGS where MTAB=? and FIELD=? and MLANG in ("'.implode('","',$langs).'")',
			    array($table,$fn));
      foreach($rs as $ors) $labels[$ors['MLANG']]=$ors['MTXT'];
      unset($rs);
      foreach($langs as $lg) $row['label['.$lg.']']=$labels[$lg];
      $row['field']=$f->field;
      $row['ftype']=$f->ftype;
      $row['fcount']=$f->fcount;
      $row['compulsory']=$f->compulsory;
      $row['queryable']=$f->queryable;
      $row['translatable']=$f->translatable;
      $row['browsable']=$f->browsable;
      $row['multivalued']=$f->multivalued;
      $row['published']=$f->published;
      $row['target']=$f->target;
      if($row['target']=='%') $row['target']='';
      $opts=$f->_options->getAllValues($f, array('for_export' => 1));
      $row=array_merge($row,$opts);
      foreach($opts as $optn=>$opt) if(!in_array($optn,$headers)) $headers[]=$optn;
      $rows[]=$row;
    }
    foreach($headers as $i=>$h) {
      $sheet->setCellValueByColumnAndRow($i, 1, $h);
    }
    $line=1;
    foreach($rows as $row){
      $line++;
      foreach($headers as $i=>$h){
	if(array_key_exists($h,$row)) $sheet->setCellValueByColumnAndRow($i, $line, $row[$h]);
      }
    }
  }

  /// Exporte les valeurs de la source au format raw
  function exportValues(&$sheet){
    $row=1;
    $rs=getDB()->select('select * from '.$this->base);
    while($ors=$rs->fetch()){
      if($row===1){
	foreach($ors as $field=>$value){
	  $sheet->setCellValueByColumnAndRow($col++,$row,$field);
	}
	$row++;
      }
      $col=0;
      foreach($ors as $field=>$value){
	$sheet->setCellValueByColumnAndRow($col++,$row,$value);
      }
      $row++;
    }
    $rs->closeCursor();
  }

  /// Importe les champs de la source via une feuille d'un objet PHPExcel
  function importSpec(&$sheet,&$log,$param=[]){
    $tables=[];
    $reorder=[];
    $table=$this->getTable();
    $fields=[];
    $prefixSQL= $param['prefixSQL'] ?? '';
    $delallfields=$param['delallfields'];
    $delotherfields=$param['delotherfields'];
    $oo=array('compulsory','queryable','browsable','translatable','multivalued','published'); // Champs booleen de base

    // Suppression de tous les champs non systeme
    if(!empty($delallfields)){
      foreach($this->desc as $fn=>&$f){
	if(!$this->sys) $this->delField(array('field'=>$fn,'action'=>'OK'));
      }
      $log.='<dd>Suppression de tous les champs non système de la table.</dd>';
    }

    // Recherche de l'entete
    $col=0;
    $h=$sheet->getCellByColumnAndRow($col,1)->getValue();
    while(!empty($h)) {
      $head[$col]=$h;
      $col++;
      $h=$sheet->getCellByColumnAndRow($col,1)->getValue();
    }
    unset($h);
    $it=$sheet->getRowIterator();
    //si 10 line vide de suite on sort
    $emptyline = 0;

    foreach ($it as $ii) {
      $i=$ii->getRowIndex();
      if($i==1) continue;
      $row=[];
      foreach($head as $j=>$h) {
	$value = $sheet->getCellByColumnAndRow($j,$i)->getValue();
        if($value==='') continue;
        $pos=strpos($h,'[');
        if($pos) $tmp='['.substr($h,0,$pos).']'.substr($h,$pos);
        else $tmp='['.$h.']';
        $tmp=str_replace(array('[',']'),array("['","']"),$tmp);
	utf8_cp1252_replace($value);
        if($value==='_empty_') eval('$row'.$tmp.'="";');
        else eval('$row'.$tmp.'="'.str_replace('"','\\"',trim($value)).'";');

     }

      if(empty($row['field'])){
        $log.='<dd>Ligne '.$i.' : champ manquant</dd>';
        if($emptyline++>10)
          break;
        else
          continue;
      } else {
        $emptyline = 0;
      }
      if(!empty($row['table']) && $table!=$prefixSQL.$row['table']) {
	continue;
      }
      $field=$row['field'];
      $fields[]=$field;
      $ftype =  $row['ftype'];
      if(!\Seolan\Core\Field\Field::isValidType($ftype) ){
        $row['ftype'] = getFieldTypeFromV8($ftype);
        if($row['ftype'] == $ftype){
          $log.='<dd>Erreur Création du champ/Creating field "'.$field.'"</dd>';
          $log.='<dd>Invalide type "'.$ftype.'"</dd>';
          continue;

        }else{
          $log.='<dd>Champ '.$field.' Invalide type "'.$ftype.'" transformé en "'.$row['ftype'].'"</dd>';
        }
      }

      // Création du champ si il n'existe pas
      $row['target']=str_replace('tzrprefix_',$prefixSQL,$row['target']);
      if(!fieldExists($table,$field)) {
        foreach($oo as $o)
          if(empty($row[$o])) $row[$o]=0;
        $createField = $this->createField($field,'Tmpname',$row['ftype'],$row['fcount'],$row['forder'],$row['compulsory'],$row['queryable'],
			   $row['browsable'],$row['translatable'],$row['multivalued'],$row['published'],$row['target']);
        if($createField['error']){
          $log.='<dd>Erreur Création du champ/Creating field "'.$field.'"</dd>';
          $log.=$createField['message'];
          continue;
        }
	$log.='<dd>Création du champ/Creating field "'.$field.'"</dd>';
      }

      // Traitement pour l'edition des différentes options
      foreach($row['options'] as $n=>$o){
	if($o=='O') $row['options'][$n]=1;
	elseif($o=='N') $row['options'][$n]=0;
      }
      foreach($oo as $o){
        if(!empty($row[$o]) && ($row[$o]==1 || $row[$o]=='O')) $row[$o]='on';
        else $row[$o]='off';
      }
      $row['_todo']='save';
      if(empty($row['label'][TZR_DEFAULT_LANG])){
	$t1=reset($row['label']);
	$row['label'][TZR_DEFAULT_LANG]=$t1;
      }
      try{
        $this->procEditField($row);
      }catch(\Exception $e){
        $log .= '<dd>Erreur lors de l\'edition du champ '.$row['table'].'.'.$field.'</dd>';
      }
        $reorder[]=array('table'=>$table,'field'=>$field,'order'=>$row['forder']);
      $log.='<dd>Modification du champ/Updating field "'.$field.'"</dd>';
    }
    // Suppression des champs qui ne sont dans la feuille
    if(!empty($delotherfields)){
      foreach($this->desc as $fn=>&$f){
	if(!in_array($fn,$fields) && !$f->sys){
	  $this->delField(array('field'=>$fn,'action'=>'OK'));
	  $log.='<dd>Suppression du champ "'.$fn.'"</dd>';
	}
      }
    }

    // Reordonne
    foreach($reorder as $info){
      getDB()->execute('UPDATE DICT set FORDER=? where DTAB=? and FIELD=?',
		       array($info['order'] ?? 'FORDER',$info['table'],$info['field']));
    }
  }

  /// Importe les valeurs raw de la source via une feuille d'un objet PHPExcel
  function importValues(&$sheet,&$log,$param=[]){
    // Recherche de l'entete
    $col=0;
    $h=$sheet->getCellByColumnAndRow($col,1)->getValue();
    while(!empty($h)) {
      $head[$col]=$h;
      $col++;
      $h=$sheet->getCellByColumnAndRow($col,1)->getValue();
    }
    unset($h);
    $it=$sheet->getRowIterator();
    foreach ($it as $ii) {
      $i=$ii->getRowIndex();
      if($i==1) continue;
      $row=[];
      foreach($head as $j=>$h) {
	$row[]=getDB()->quote($sheet->getCellByColumnAndRow($j,$i)->getValue());
      }
      $nb=getDB()->execute('insert ignore into '.$this->base.'('.implode(',',$head).') values('.implode(',',$row).')');
      $log.='<dd>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Ligne '.$i.' : '.($nb?'OK':'Erreur').'</dd>';
    }
  }

  /// Ensemble d'actions à effectuer après qu'une modification de donnée ait eu lieu
  protected function updateTasks($ar, $oid, $event, $inputs=null) {
    if (parent::updateTasks($ar, $oid, $event, $inputs)) {
      if ($this->base=='LOGS') return true;
      foreach ($this->desc as $fn => $field) {
        if ($field instanceof \Seolan\Field\Link\Link || ($field instanceof \Seolan\Field\ShortText\ShortText && ($field->autocomplete || $field->listbox || $field->query_format == 'listbox-one')) || $field instanceof \Seolan\Field\StringSet\StringSet) {
          if (isset($inputs[$fn]) && is_object($inputs[$fn]) && is_array($inputs[$fn]->raw))
            if (count($inputs[$fn]->raw) == 1)
              $rawValue = $inputs[$fn]->raw[0];
            else
              $rawValue = '||' . implode('||', $inputs[$fn]->raw) . '||'; // todo separator (shorttext)
          else
            $rawValue = (isset($inputs[$fn]) && is_object($inputs[$fn])?$inputs[$fn]->raw:NULL);
          if ($event == 'del' || ($event == 'procInput' && !empty($rawValue)) ||
             ($event == 'procEdit' && (!isset($inputs[$fn]->old) ? true : $rawValue != $inputs[$fn]->old))) {
            if ($field instanceof \Seolan\Field\Link\Link)
              \Seolan\Core\DbIni::clear($this->base.'|'.$field->target.':usedValues:'.$fn.':%');
            else
              \Seolan\Core\DbIni::clear($this->base.':usedValues:'.$fn.':%');
          }
        }
        // les liens vers cette table
        if ($event == 'procEdit') // la ligne peux ne plus passer les select
          \Seolan\Core\DbIni::clear('%|'.$this->base.':usedValues:%');
      }
    }
    return true;
  }

  /// Génère un oid pour la source
  function getNewOID($ar = NULL) {
    if (empty($this->oidstruct1)) {
      return parent::getNewOID($ar);
    }
    // Si l'oid doit être généré à partir des infos d'autres champs
    $p=new \Seolan\Core\Param($ar);
    $oid = $this->desc[$this->oidstruct1]->post_edit($p->get($this->oidstruct1))->raw;
    if ($this->desc[$this->oidstruct1] instanceof \Seolan\Field\Link\Link) {
      $oid = str_replace($this->desc[$this->oidstruct1]->target, '', $oid);
    }
    if (!empty($this->oidstruct2)) {
      $_oid = $this->desc[$this->oidstruct2]->post_edit($p->get($this->oidstruct2))->raw;
      if ($this->desc[$this->oidstruct2] instanceof \Seolan\Field\Link\Link) {
        $_oid = str_replace($this->desc[$this->oidstruct2]->target, '', $_oid);
      }
      $oid .= '-' . $_oid;
    }
    if (!empty($this->oidstruct3)) {
      $_oid = $this->desc[$this->oidstruct3]->post_edit($p->get($this->oidstruct3))->raw;
      if ($this->desc[$this->oidstruct3] instanceof \Seolan\Field\Link\Link) {
        $_oid = str_replace($this->desc[$this->oidstruct3]->target, '', $_oid);
      }
      $oid .= '-' . $_oid;
    }
    $oid = rewriteToAscii($oid);
    if (empty($oid))
      return parent::getNewOID($ar);
    $oid = substr($this->base . ':' . $oid, 0, 40);
    $cnt = getDB()->count('select COUNT(KOID) from ' . $this->base . ' where KOID=?', [$oid], false);
    if ($cnt) {
      $i = 0;
      do {
        $i++;
        $tmpoid = substr($oid, 0, 37) . '-' . $i;
        $cnt = getDB()->count('select COUNT(KOID) from ' . $this->base . ' where KOID=?', [$tmpoid], false);
      } while ($cnt > 0);
      $oid = $tmpoid;
    }
    return $oid;
  }
  /// generation de la doc
  function getDocumentationData($fieldssec=[]){
    $r = ['data'=>['desc'=>[]],
	  'template'=>NULL, 
	  'title'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource', 'data_dictionary'),
	  'chapeau'=>'Table : '.$this->getLabel()."\n"];
    if(\Seolan\Core\Json::hasInterfaceConfig() && ($alias=\Seolan\Core\Json::getTableAlias($this->base))) {
	$r['chapeau'] .= "\nAlias JSON : ".$alias;
    }
    if ($this->translatable == 1){
      $r['chapeau'] .= "\n".\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field', 'translatable');
    }
    $selectedfields = $this->orddesc;
    
    foreach($this->desc as $fn=>$fd){
      // on n'affiche pas les champs cachés, par les droits ou l'attribut caché
      if (!in_array($fn, $selectedfields) || (!empty($fieldssec[$fn]) && $fieldssec[$fn] == 'none') || $fd->hiddent)
          continue;
      $r['data']['desc'][] = $fd->getDocumentationData();
    }

    // ajout des champs système LANG et KOID
    $r['data']['desc'][] = (Object)['field'=>'KOID',
				    'label'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource', 'oid'),
				    'description'=>'Identifiant système d\'un objet.',
				    'type'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource', 'oid'),
				    'constraints'=>['Alias JSON : id']];
    if ($this->translatable == 1){
      $r['data']['desc'][] = (Object)['field'=>'LANG',
				      'label'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'language'),
				      'description'=>'Langue des données.',
				      'type'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'language'),
				      'constraints'=>[]];
    }
    return $r;
  }
  /**
   * archives la plus récente dont le KOID n'est plus en table
   */
  public function browseTrashSelect(?string $filter=null, ?string $trashfilter=null){
    $lang = TZR_DEFAULT_LANG;
    $aselect = <<<EOF
select STRAIGHT_JOIN 
LOGS.usernam,
A_{$this->base}.* 
from LOGS 
left outer join A_{$this->base} on LOGS.object = A_{$this->base}.KOID and LOGS.dateupd = A_{$this->base}.UPD  and A_{$this->base}.LANG="{$lang}" 
left outer join {$this->base} on LOGS.object = {$this->base}.KOID and {$this->base}.LANG="{$lang}" 
where LOGS.etype='delete' 
and isnull({$this->base}.KOID)
and !isnull(A_{$this->base}.KOID)
and A_{$this->base}.UPD = (select max(MA_{$this->base}.UPD) from A_{$this->base} MA_{$this->base} where MA_{$this->base}.KOID=A_{$this->base}.KOID)
/*myfilter*/
/*trashfilter*/
order by A_{$this->base}.UPD desc
EOF;
    // vérifcation du filter qui peut ne pas fonctionner ?
    if (!empty($filter)){
      $filter = preg_replace("/{$this->base}\./","A_{$this->base}.",$filter);
      $filter = " and ($filter)";
      $select = str_replace('/*myfilter*/', $filter, $aselect);
      try{
	$t = getDB()->fetchAll('explain '.$select);
      }catch(\Throwable $e){
	\Seolan\Core\Logs::critical(__METHOD__," error applying module filter $filter");
	$select = str_replace('/*myfilter*/', '', $aselect);
      }
    } else {
      $select = str_replace('/*myfilter*/', '', $aselect);
    }
    // filtre dedié, ex : type de document
    
    if (!empty($trashfilter)){
      $select = str_replace('/*trashfilter*/', $trashfilter, $select);
    }
    return str_replace("\n", ' ', $select); // geDB()->count du browse ne gere pas le multiligne 
  }
}
