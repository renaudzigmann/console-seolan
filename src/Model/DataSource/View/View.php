<?php
/// Gestion d'une source de donnees de type 'Vue SQL'
namespace Seolan\Model\DataSource\View;

class View extends \Seolan\Model\DataSource\Table\Table{
  public $viewfields = null;
  public $query = null;
  function __construct($boid=0) {
    parent::__construct($boid);
  }

  function initOptions(){
    parent::initOptions();
    $this->_options->delOpt('oidstruct1');
    $this->_options->delOpt('oidstruct2');
    $this->_options->delOpt('oidstruct3');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Model_DataSource_View_View','maintable'),'maintable','table');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Model_DataSource_View_View','query'),'query','text',array('rows'=>10,'cols'=>80));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Model_DataSource_View_View','viewfields'),'viewfields','text',array('rows'=>10,'cols'=>80));
    $this->_options->setComment(\Seolan\Core\Labels::getSysLabel('Seolan_Model_DataSource_View_View','viewfieldscomment'),'viewfields');
  }
  /// pas de chk sur une vue
  public function chk($ar=NULL) {
    return '';
  }
  protected function _getDesc($refresh=false) { 
    $rows = array_filter(explode("\n", str_replace("\r\n", "\n", $this->viewfields)));
    // TAB:FIELD:SQLFIELD:OPTIONS
    $fields = $where = [];
    foreach($rows as $row){
      // sauter les commnetaire
      if (substr($row, 0, 2) == '--'){
          continue;
      }
      $row=explode(':',$row);

      if (isset($row[3])){
	$options = explode(';', str_replace(array('options', ']', '['), '', $row[3]));
	unset($row[3]);
	$row[3] = array();
	foreach($options as $option){
	  list($k, $v) = explode('=', $option);
	  if (!empty($k)) {
	    $row[3][$k] = $v;
          }
	}
      }
      $fields[]=$row;
      $where[]='(DICT.DTAB="'.$row[0].'" and DICT.FIELD="'.$row[1].'")';
    }
    if(!empty($where))
      $where='('.implode(' OR ',$where).') AND ';
    else
      $where='';

    $this->desc=array();
    $this->orddesc=array();
    $lang = \Seolan\Core\Shell::getLangUser();
    if($lang!=TZR_DEFAULT_LANG) $lang_cond='(MLANG=? or MLANG="'.TZR_DEFAULT_LANG.'")';
    else $lang_cond="MLANG=?";
    $dict = getDB()->select(
      'select concat(DTAB, ":", DICT.FIELD), DICT.*, MSGS.* from DICT,MSGS WHERE '.$where.' DTAB=MTAB '
      . 'and '.$lang_cond.' and DICT.FIELD=MSGS.FIELD', [$lang]
    )->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    foreach($fields as $i=>$f){
      $ors=$dict[$f[0].':'.$f[1]];
      if (!isset($dict[$f[0].':'.$f[1]])){
	// erreur de desription ou champ qui n'existe pas
	\Seolan\Core\Logs::critical(get_class($this), '::_getDesc, invalid field '.$f[0].':'.$f[1]);
	continue;
      }
      $ors['FIELD']=$f[2];
      $ors['FORDER']=$i;

      $field=$ors['FIELD'];
      if(empty($this->desc[$field]) || (!empty($this->desc[$field]) && ($ors['MLANG']==$lang))) {
  	if (isset($f[3])){
  	  foreach($f[3] as $ko=>$vo){
  	    if (isset($ors[strtoupper($ko)])){
  	      $ors[strtoupper($ko)]=$vo;
  	    } else {
  	      // passer des options spécifiques au champ dans la vue
  	      $ors[$ko]=$vo;
  	    }
  	  }
  	}
	$o=(object)$ors;
	//recup des options surchargées
	//$o->DTAB = $this->base;  à envisager en toute logique mais casse les stringset entre autre
	$theclass = null;
	if (isset($f[3]['theclass'])){
	  if (\class_exists($f[3]['theclass'])
	      && \is_subclass_of($f[3]['theclass'], '\Seolan\Core\Field\Field')){
	    $theclass=$f[3]['theclass'];
	  } else {
	    \Seolan\Core\Logs::critical(__METHOD__,"{$f[3]['theclass']} invalid class name");
	  }
	}
	$tmpfield = \Seolan\Core\Field\Field::objectFactory($o,$theclass);
	foreach($f[3] as $ko=>$vo){
	  if (!isset($ors[strtoupper($ko)])){
	    $tmpfield->$ko=$vo;
	  }
	}
	$this->desc[$field]=$tmpfield;
        $this->desc[$field]->view = $this->base;
	if(!in_array($field,$this->orddesc)) {
	  $this->orddesc[$i]=$field;
	}
      }
    }
  }

  /// rend vrai s'il y a au moins un champ publie
  function isTherePublishedField() {
    foreach ($this->desc as $field) {
      if ($field->published) {
        return true;
      }
    }
    return false;
  }

  /// Verifie si l'oid peut etre traité par la source
  function checkOID($oid,&$ar){
    if(!empty($this->maintable) && \Seolan\Core\Kernel::getTable($oid)!=$this->maintable){
      if($return) return false;
      else \Seolan\Library\Security::alert(__METHOD__.': Trying to use '.$oid.' with wrong \Seolan\Core\DataSource\DataSource <'.$this->maintable.'>');
    }
    return true;
  }

  /// Sauvegarde des proprietes d'une datasource
  function procEditProperties($ar=NULL) {
    parent::procEditProperties($ar);
    \Seolan\Model\DataSource\View\View::updateView($this->base, $this->query);
  }

  /// Genere un nouvel oid pour la source
  function getNewOID($ar=NULL){
    if (empty($this->maintable)){
      \Seolan\Library\Security::alert(__METHOD__.': empty maintable '.$this->xset->getTable());
    }
    return \Seolan\Core\DataSource\DataSource::getNewSpecificOID($this->maintable,$ar);
  }

  /// Insere une nouvelle vue SQL
  static function procNewSource($ar=NULL) {
    global $XLANG;
    $error=false;
    $p=new \Seolan\Core\Param($ar,array('translatable'=>0,'auto_translate'=>0,'publish'=>1),'local');
    $bname=$p->get('bname');
    $btab=$p->get('btab');
    $translatable=$p->get('translatable');
    $auto_translate=$p->get('auto_translate');
    $bparam=$p->get('bparam');
    $classname=$p->get('classname');
    if(!empty($auto_translate)) $translate=1;
    if(empty($classname)) $classname='\Seolan\Model\DataSource\View\View';

    // Controle des donnees obligatoires
    if(isSQLKeyword($btab)) {
      $message=$btab.' is a SQL keyword';
      $error=true;
      \Seolan\Core\Logs::notice('xbaseadm',$message);
    } elseif (empty($bname[TZR_DEFAULT_LANG])) {
      $message='View Name is compulsory in default language ! Try again ...';
      $error=true;
      \Seolan\Core\Logs::notice('xbaseadm',$message);
    } elseif(empty($btab)) {
      $message='SQL View Name is compulsory ! Try again ...';
      $error=true;
      \Seolan\Core\Logs::notice('xbaseadm',$message);
    } elseif(rewriteToAscii($btab,false)!=$btab) {
      $message='SQL View Name not [A-Za-z0-9_-] checked ! Try again ...';
      $error=true;
      \Seolan\Core\Logs::notice('xbaseadm',$message);
    } else{
      if(self::createView($btab,$bparam['query'])) {
	$boid=\Seolan\Core\DataSource\Wizard::getNewBoid();
	$json=\Seolan\Core\Options::rawToJSON($bparam,TZR_ADMINI_CHARSET);
	getDB()->execute('INSERT INTO BASEBASE(BOID,BNAME,BTAB,AUTO_TRANSLATE,TRANSLATABLE,BCLASS,BPARAM) '.
                         'values(?,?,?,?,?,?,?)', array($boid,$bname[TZR_DEFAULT_LANG],$btab,$auto_translate,
                                                        $translatable,$classname,$json));
	$XLANG->getCodes();
	for($i=0;$i<$XLANG->nbLang;$i++) {
	  $code=$XLANG->codes[$i];
	  $txt=$bname[$code];
	  if($txt!="") getDB()->execute('INSERT INTO AMSG(MOID,MLANG,MTXT) values (?,?,?)',array($boid,$code,$txt));
	}
	$message='New view '.$bname[TZR_DEFAULT_LANG].' ('.$btab.') created.';
      } else {
	$error=true;
	$message='Could not create '.$bname[TZR_DEFAULT_LANG].' ('.$btab.').';
	$message.=$bparam['query'].'-';
      }
    }
    return array('message'=>$message,'error'=>$error,'boid'=>$boid);
  }

  /// Créé une vue SQL et son dictionnaire
  static function createView($table,$query) {
    $txxx=$table;
    if(\Seolan\Core\System::tableExists($txxx)) return false;
    // Creation de la table sql
    if($valid) $publish='PUBLISH tinyint(4) default 1,';
    else $publish='';
    getDB()->execute('CREATE VIEW '.$txxx.' as ('.$query.')');
    if(!\Seolan\Core\System::tableExists($txxx, true)) return false;
    return true;
  }

  /// Remplace la requête d'une vue existante
  static function updateView($viewname, $query) {
    getDB()->execute('CREATE OR REPLACE VIEW '.$viewname.' as ('.$query.')');
    if (!\Seolan\Core\System::tableExists($viewname, true)) return false;
    $error = getDB()->errorInfo();
    if ($error[2]) {
      header('Status: 500 SQL Error');
      die($error[2]);
    }
    return true;
  }

  protected function dropSource($table) {
    getDB()->execute('DROP VIEW ' . $table);
  }

  function del($ar) {
    $p=new \Seolan\Core\Param($ar,array('_selectedok'=>'nok'));
    $oid=$p->get('oid');
    $lang=\Seolan\Core\Shell::getLangData();
    $selected=$p->get('_selected');
    $selectedok=$p->get('_selectedok');
    $k=new \Seolan\Core\Kernel();
    $k->data_forcedDel(array('oid'=>$oid,'tplentry'=>TZR_RETURN_DATA,'action'=>'OK',
			     '_selected'=>$selected,'_selectedok'=>$selectedok, '_mytable'=>$this->maintable));
  }

  public function getInputTable() {
    return $this->maintable;
  }
}
?>
