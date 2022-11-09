<?php
/// Classe de base d'un document integre dans la base documentaire (\Seolan\Module\DocumentManagement\DocumentManagement)
namespace Seolan\Module\DocumentManagement\Model\Document;

/// Classe de base d'un document integre dans la base documentaire (\Seolan\Module\DocumentManagement\DocumentManagement)
class Document {
  public $title;
  public $comment;
  public $countdocs=0;
  public $countdirs=0;
  public $node=false;
  public $tpl;
  public $fields;
  public $insearchengine=true;
  public $path=[];
  protected $usetrash = false;
  protected $symbol='';
  static $rep_rep=array();
  /**
   * liste des documents archives
   * @todo : repository archive devrait être définie dans les interfaces XMC
   * @todo : getJournal aussi actuellement ce sont des options/methode de xmodtable
   */
  public function getArchives(){
    // 
    if (!$this->repository->archive){
      return array();
    }
    $loglines = \Seolan\Core\Logs::getJournal($this->oid,
					      array('etype'=>array('=',array('create','update'))),
					      NULL,
					      NULL,
					      NULL, // xset mais icion sait pas ou il est
					      NULL // field sec
    );
    //  type
    $nodeTypeOrs = getDB()->fetchRow('SELECT * FROM '.$this->module->id.',_TYPES WHERE '.$this->module->id.'.KOID=? AND _TYPES.KOID=DTYPE', array($this->oid));
    // on instancie les documents archivés 
    $archives = array();
    for($i=0; $i<count($loglines['lines_odateupd'])-1; $i++){ 
      $archives[$loglines['lines_odateupd'][$i]->raw] = \Seolan\Module\DocumentManagement\Model\Document\Document::archiveObjectsFactory($this->oid, 
																	 $this->module, 
																	 $nodeTypeOrs,
																	 $loglines['lines_odateupd'][$i]->raw);
    }
    return $archives;
  }
  
  static function archiveObjectsFactory($oid, $docmgt, $nodeTypeOrs, $archiveDate) {
    $docclass = static::getDocclass($nodeTypeOrs);
    $archiveDoc =  new $docclass($oid, $nodeTypeOrs, $docmgt, array('_archive'=>$archiveDate));
    if (empty($archiveDoc->fields)){
      return NULL;
    }
    return $archiveDoc;
  }
  
  static function objectFactory($oid, $docmgt, $ors=NULL, $ar=NULL) {
    if(isset($docmgt->doccache[$oid])) return $docmgt->doccache[$oid];
    if(empty($ors)) {
      $ors=getDB()->fetchRow("SELECT * FROM {$docmgt->id},_TYPES WHERE {$docmgt->id}.KOID=? AND ".
			     "_TYPES.KOID=DTYPE",array($oid));
      if(empty($ors)) return null;
    }

    $docclass = static::getDocclass($ors);
    
    $docmgt->doccache[$oid]=new $docclass($oid, $ors, $docmgt, $ar);
    // Si l'objet n'a pas été correctement initialisé, on le déclare vide
    if (empty($docmgt->doccache[$oid]->fields)) $docmgt->doccache[$oid] = '';
    return $docmgt->doccache[$oid];
  }
  /// controle et retourne la classe d'un document à partir des données du type
  static protected function getDocclass($ors){
    if(!empty($ors['docclas'])) {
      if(!class_exists($ors['docclas'])) {
	\Seolan\Core\Logs::critical(__METHOD__, $ors['docclas']." document/directory class");
	if($ors['node']==1) $docclass='\Seolan\Module\DocumentManagement\Model\Document\Directory';
	else $docclass='\Seolan\Module\DocumentManagement\Model\Document\Document';
      } else {
	$docclass = $ors['docclas'];
      }
    } else {
      if($ors['node']==1) $docclass='\Seolan\Module\DocumentManagement\Model\Document\Directory';
      else $docclass='\Seolan\Module\DocumentManagement\Model\Document\Document';
    }
    return $docclass;
  }

  static function &repositoryFactory($typeraw=NULL, $typedisp=NULL) {
    if(!empty($typeraw)) {
      $module=$typeraw['modidd'];
      $tab=$typeraw['dtab'];
    } else {
      $module=$typedisp['omodidd']->raw;
      $tab=$typedisp['odtab']->raw;
    }

    if(!empty($tab)) {
      if(empty(self::$rep_rep[$tab])) {
	self::$rep_rep[$tab]=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($tab);
      }
      $mod=&self::$rep_rep[$tab];
    } else {
      if(empty(self::$rep_rep[$module])) {
	self::$rep_rep[$module]=\Seolan\Core\Module\Module::objectFactory($module);
      }
      $mod=&self::$rep_rep[$module];
    }
    return $mod;
  }
  /**
   * Construction d'un document. 
   * $ors : joint _TYPES, DMnID utilisés : DTYPE(=_TYPES[KOID]), symbol, DPARAM (options d'affichage)
   */
  function __construct($oid, $ors=NULL, $docmgt, $ar=NULL) {
    $mod=\Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory($ors);
    if(is_object($mod)) {
      // decodage des options du type de document
      $this->tpl=&$docmgt->getTypes($ors['DTYPE']);
      $lines = explode("\n", stripslashes($this->tpl['oopts']->toText()));
      $this->tploptions=array();
      foreach($lines as &$line) {
	@list($var,$val)=explode('=',$line);
	if(!empty($var) && isset($val)) {
	  $var=trim($var);$val=trim($val);
	  $this->tploptions[$var]=$val;
	}
      }
      // trash O/N fonction du docmngt, du conteneur : ds -> docmngt, module true+true
      if ($docmgt->usetrash){
	if (!property_exists($mod, 'usetrash') || $mod->usetrash){
	  $this->usetrash = true;
	}
      }
      // recherche du document lui même
      $dar=array('oid'=>$oid,'_lastupdate'=>false,'_options'=>array('local'=>1,'error'=>'return'),'fmoid'=>$docmgt->_moid,'tplentry'=>TZR_RETURN_DATA);
      $link_separator=$this->getOption('link_separator');
      if(!empty($link_separator)) $dar['options']['link_separator']=$link_separator;
      if ($ar) $dar=array_merge($dar,$ar);
      $result=$mod->XMCdisplay($dar,false);
      // S'il y a eu une erreur lors de la tentative de récupération des données de l'objet (dans ce cas $result contient le message d'erreur)
      if (!is_array($result)) return $result;
      $this->fields=&$result;
      $this->oid=$oid;
      $this->repository=$mod;
      $this->module=$docmgt;
      $this->options=unserialize(stripslashes($ors['DPARAM']));

      $this->icon=(Object)['url'=>null,'html'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','defaultdoc','csico')];

      $this->smallicon=\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','defaultsdoc');
      // à voir - jtree / images versus css icon (index3 par exemple)
      $this->smalliconurl = '/csx/public/jtree/doc.png';

      $this->title = $this->getTitle();
      $this->docs=$this->getFiles();
      $this->short=$this->title;
      $this->symbol = $ors['symbol'];
      $this->node=false;
    } 
  }
  function saveOptions() {
    $dparam=addslashes(serialize($this->options));
    getDB()->execute("UPDATE {$this->module->id} SET DPARAM=? WHERE KOID=?",array($dparam,$this->oid));
  }
  function setOption($option, &$value) {
    $this->options[$option]=$value;
  }
  /// recherche des options d'un document, puis dans le type de document, NULL sinon
  function getOption($option) {
    if(!empty($this->options[$option])) return $this->options[$option];
    else return $this->tploptions[$option]??NULL;
  }
  /// consulter une option du type de document associé au document
  function getDocumentTypeOption($option) {
    return $this->tploptions[$option]??NULL;
  }
  function getDocumentsDetails($oids=NULL) {
  }

  function getDirectoriesDetails($level=1) {
  }
  function getParents($level=1) {
    $this->parents=array();
    $this->parentsoid=array();
    if($this->oid==$this->module->rootOid()) return;
    $set=$this->module->father($this->oid);
    foreach($set as $ors) {
      $oiddst=$ors['KOIDDST'];
      if(!empty($oiddst) && !in_array($oiddst,$this->parentsoid)) {
	if($this->module->secure1($oiddst,'index',$ors['node'])) {
	  $d1=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oiddst,$this->module,$ors);
	  if($level>1) $d1->getParents($level-1);
	  $this->parents[]=$d1;
	  $this->parentsoid[]=$oiddst;
	}
      }
    }
  }

  // recherche de tous les chemins possibles qui sont stockés dans $this->paths
  //
  function getAllPaths() {
    $this->paths=array();
    $this->_getAllPaths1($this->paths, array($this->oid));
    foreach($this->paths as $i=>&$path) {
      $path=array_reverse($path);
      foreach($path as $j=>$oidi) {
	$path[$j]=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oidi, $this->module);
	if($path[$j]->node!=1) unset($path[$j]);
      }
    }
  }
  private function _getAllPaths1(&$global, $currentpath) {
    if(empty($this->parents)) {
      if(!empty($currentpath)) {
	$global[]=$currentpath;
      }
    } else {
      foreach($this->parents as $i => $p) {
	if(!in_array($p->oid, $currentpath)) {
	  $copy=$currentpath;
	  $copy[]=$this->parents[$i]->oid;
	  $p->_getAllPaths1($global, $copy);
	}
      }
    }
  }

  function getContent($final=true,$update=true) {
    $oids=$this->module->subObjects4($this->oid, 1, false);
    if(!empty($this->oid)) {
      $this->docsoids = array_values($oids['2']);
      $this->countdocs = count($this->docsoids);
    }
    $this->dirsoids = array_values($oids['1']);
    $this->countdirs = count($this->dirsoids);
  }

  function getTitle() {
    return strip_tags($this->fields['link']);
  }

  /// rend le texte associe au document
  function getDescription() {
    return (!empty($this->fields['oremark'])?$this->fields['oremark']->html:'');
  }

  /// Retourne au format html la liste des fichiers du document
  function getFiles() {
    $this->countfiles=0;
    $txt='';
    foreach($this->fields['fields_object'] as $i=>$v) {
      if(!empty($v->html)){
	if($v->fielddef instanceof \Seolan\Field\File\File) {
          if(!$v->fielddef->multivalued){
            $txt.="<div class=\"tzr-xdirdef-dir browse text-overflow\""
	    ." data-field=\"{$v->fielddef->field}\" data-oid=\"{$this->oid}\"><div>{$v->html}</div></div>";
            $this->countfiles++;
          }else{
            $txt.=$v->html;
            if(isset($v->catalog)) $this->countfiles+=count($v->catalog);
          }
        }
      }
    }
    return $txt;
  }

  /// rend liste des fichiers d'un document
  function getFilesDetails() {
    return getFilesDetails($this->fields);
  }

  /// liste les actions possible sur un document
  function getActions() {
    static $m1=array('ro','rw','unlock');
    static $m2=array('ro');
    static $m3=array('ro','rw');
    static $m4=array();
    static $m5=array('ro','rw','lock');
    if($this->module->secure1($this->oid, 'edit',($this->node?"1":"2"))) {
      if($xlock=\Seolan\Core\Shell::getXModLock()) {
	if(!empty($this->fields['_lock'])) {
	  if(!empty($this->fields['_lock_editable'])) {
	    $this->actions=&$m1;
	  } else {
	    $this->actions=&$m2;
	  }
	} else $this->actions=&$m5;
      } elseif($this->tpl['omodid']->raw!=$this->module->_moid) {
	// si c'est un document partage et qu'on n'est pas dans son module d'origine
	$this->actions=&$m2;
      } else {
	$this->actions=&$m3;
      }
    } elseif($this->module->secure1($this->oid, 'display',($this->node?"1":"2"))) {
      $this->actions=&$m2;
    } else {
      $this->actions=&$m4;
    }
  }
  /**
   * surcouche de la suppression complete
   */
  function fullDelete($ar=null){
    $mod = static::repositoryFactory(NULL,$this->tpl);
    $mod->XMCfullDelete(['oid'=>$this->oid,
			     'tplentry'=>TZR_RETURN_DATA,
			     '_local'=>true
    ]);
  }
  /**
   * suppression d'un document dans la base documentaire
   * ajout des infos corbeille : options, type, module 
   */
  function del($ar=null) {
    $mod = static::repositoryFactory(NULL,$this->tpl);
    $mod->XMCdel(['oid'=>$this->oid,
		  'tplentry'=>TZR_RETURN_DATA,
		  '_local'=>true,
		  '_trashuser'=>\Seolan\Core\User::get_current_user_uid(),
		  '_trashmoid'=>$this->module->_moid,
		  '_trashdata'=>['_trashtype'=>$this->tpl['oid'],'_trashoptions'=>empty($this->options)?[]:$this->options]]);
  }

  function journal() {
    $mod = \Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(NULL,$this->tpl);
    if(method_exists($mod,'journal')) {
      return $mod->journal(array('oid'=>$this->oid,'tplentry'=>TZR_RETURN_DATA));
    } else return NULL;
  }

  /// Ajout d'un document au moteur de recherche
  function addToSearchEngine(&$searchEngine) {
    $fields = $this->getSearchEngineData();
    $searchEngine->addItem($this->oid,$fields,$this->module->_moid,NULL);
    \Seolan\Core\Logs::notice(get_class($this),get_class($this).'::addToSearchEngine lucene');
  }

  function &getSearchEngineData(){
    $fields = array('notice'=>'', 'contents'=>'', 'title'=>'');
    $fields['contents']=getFilesContent($this->fields, null, TZR_INDEXABLE_FILE_MAXSIZE);
    $fields['title'] = $this->title;
    $fields['notice'] = $this->getSearchEngineNotice();
    // ? tags voir Module/Table
    return $fields;
  }

  function getSearchEngineNotice(){
    $notice = '';
    // les chps indexables et non publiés
    foreach($this->fields['fields_object'] as $i=>&$v) {
      $field = $v->fielddef;
      if ($field->indexable && !$field->get_published()){ 
	$notice .= $v->text.' ';
      }
    }
    return trim($notice);
  }

  /// synchronize doc update et Id update times
  public function syncLastUpdate() {
    // recherche de la dernière mise à jour dans la source de données
    $mod = \Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(NULL,$this->tpl);
    $dates=$mod->XMCgetLastUpdate($this->oid);
    // mise à jour de la table ID
    getDB()->execute("UPDATE {$this->module->id} node SET node.UPD=? WHERE node.KOID=?",[$date["dateupd"], $this->oid]);
  }

  /// rend le l'oid du type de doc
  public function getDocumentType() {
    return $this->tpl['oid'];
  }

  /// ajout d'un ascendant (un parent)
  function addParent($parentoid) {
    $q="SELECT 1 FROM {$this->module->idx} WHERE KOIDSRC=? AND KOIDDST=?";
    $ors=getDB()->fetchOne($q, [$this->oid, $parentoid]);
    if(!$ors) {
      $q="INSERT INTO {$this->module->idx} SET KOIDSRC=?, KOIDDST=?";
      getDB()->execute($q, [$this->oid, $parentoid]);
    }
  }

}
