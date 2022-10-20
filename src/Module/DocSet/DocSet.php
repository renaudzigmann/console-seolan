<?php
namespace Seolan\Module\DocSet;

/**
 * Ensemble de documents :
 * fonction spécifiques aux ensembles de fiches servant de support à un type de document
 * - délégation des droits sur une base de donnée associée
 * - interface avec la base doc : insertion (noeuds et arcs, mise à jour : udp)
 * - surcharge des droits sur les fonctions d'insertion (@todo) : on peut insérer / dupliquer 
 * si il existe un dossier de la base doc où on peut écrire
 */
use \Seolan\Core\{Shell,
		  Labels,
		  Logs,
		  Param,
		  Field\Field,
		  Field\Field\Value,
		  Module\Module,
		  DataSource\DataSource};

class DocSet extends \Seolan\Module\Table\Table {
  public $objects_sec = true;
  public $daclMoid = null;      // moid du module ensemble de fiches associé
  protected $docmngt = null;    // module base doc construit dynamiquement avec daclMoid
  protected static $parentfieldname = '__parentdirectory__';
  protected $insertACLLevel = null;
  
  /// type de document correspondant, instance du module associé et modification du desc
  function __construct($ar=null){
    parent::__construct($ar);
    if (!empty($this->daclMoid)){
      // module base doc associé
      $this->docmngt = Module::objectFactory(['moid'=>$this->daclMoid,
					      'interactive'=>false,
					      'tplentry'=>TZR_RETURN_DATA]);
      
    } else {
      Logs::critical(__METHOD__,"module {$this->_moid} without DaclMoid");
    }
    if (!is_int($this->unfoldedgroupsnumber) || $this->unfoldedgroupsnumber < 2)
      $this->unfoldedgroupsnumber = 2;
  }
  /**
   * sélection du dossier destination et restauration via la base doc associée
   */
  protected function getRestoreArchiveActionHelper(){
    return new Class('restoreArchive', $this, 'Seolan_Core_General restore_from_trash','display','class="cv8-ajaxlink cv8-dispaction"') extends \Seolan\Core\Module\BrowseActionHelper{
      function browseActionUrl($usersel, $linecontext=null){
	$dtversion = $linecontext['browse']['lines_oUPD'][$linecontext['index']]->raw;
	$oid = $linecontext['browse']['lines_oid'][$linecontext['index']];
	$url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self();
	$next= $this->module->getMainAction();
	return $url.'&moid='.$this->module->_moid.'&_dtype='.urlencode($dtype).'&_archive='.urlencode($dtversion).'&oid=<oid>&tplentry=br&function=moveFromTrash&template=Core.empty.html&_skip=1&_next='.urlencode($next);
      }
      function browseActionHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
	$dtversion = $linecontext['browse']['lines_oUPD'][$linecontext['index']]->raw;
	$oid = $linecontext['browse']['lines_oid'][$linecontext['index']];
	return 'class="cv8-dispaction" '.
	       ' onclick="TZR.Archives.restoreArchiveTarget(\''.$url.'\', '. $this->module->daclMoid .');'.
	       ' return false;"';
      }
    };
  }
  protected function getViewArchiveActionHelper(){
    return new Class('viewArchive', $this, 'Seolan_Core_General view','display','class="cv8-ajaxlink cv8-dispaction"') extends \Seolan\Core\Module\BrowseActionHelper{
      function browseActionUrl($usersel, $linecontext=null){
	$dtversion = $linecontext['browse']['lines_oUPD'][$linecontext['index']]->raw;
	$dtype = $linecontext['browse']['lines__doc'][$linecontext['index']]->tpl['oid'];
	return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->module->_moid.'&_dtype='.urlencode($dtype).'&_trash=1&_archive='.$dtversion.'&oid=<oid>&tplentry=br&function=displayTrash&template=Core/Module.view-archive.html';
      }
    };
  }
  /// restauration d'un document : traiter la base doc associée
  public function moveFromTrash($ar=null){

    parent::moveFromTrash($ar);

    $p = new Param($ar, []);
    $oid = $p->get('oid');
    $parentoid = $p->get('_parentoid');

    // création du noeud à l'emplacement choisi
    $this->createNodesAndLinks($oid, $parentoid);
    
  }
  /// Insertion : dossier parent
  function insert($ar=null){
    list($p, $tpl, $xmc) = $this->prepareAr('insert',$ar);
    $r = parent::insert($ar);
    if (!$xmc)
      $this->prepareFieldFor('insert', $r, $p);
    return Shell::toScreen1($tpl, $r);
  }
  
  /// Insertion : créer le noeud et les arcs
  /// ! en import, $ar est forcé local
  function procInsert($ar=null){
    $r = parent::procInsert($ar);
    $lang= Shell::getLangData();
    // mise à jour de la base doc
    if (isset($r['oid'])){
      $p = new Param($ar, ['_xmc'=>false]);
      if ($p->get('_xmc') !== true){ // quand on est en dehors de la base doc
	$this->createNodesAndLinks($r['oid'], $p->get(static::$parentfieldname));
	getDB()->execute("UPDATE {$this->docmngt->id} node SET node.UPD=(SELECT d.UPD FROM {$this->table} d WHERE d.LANG=? AND d.KOID=node.KOID) WHERE node.KOID=?",[$lang, $r['oid']]);
      }
    }
    return $r;
  }

  /// Ajout d'un document dans la base doc associée
  protected function createNodesAndLinks($newoid, $parentoid){
    // recherche du type de document associé à cet ensemble de fiches
    $dtypeoid = $this->getDocumentType();
    if($dtypeoid) {
      $this->docmngt->addDocument($parentoid, $newoid, $dtypeoid);
      Logs::debug(__METHOD__,"add {$newoid} to {$parentoid}");
    } else {
      Logs::critical(__METHOD__," could not find Document Type for this module ");
    }
  }
  /// récupération du document type associé 
  public function getDocumentType(){
    return  getDB()->fetchOne('select KOID from _TYPES WHERE modid=? AND modidd=?',
			      [$this->daclMoid,$this->_moid]);
  }
  /// Display : dossier parent
  function display($ar=null){
    list($p, $tpl, $xmc) = $this->prepareAr('display', $ar);
    $r = parent::display($ar);
    if (!$xmc && !$p->is_set('ssmoid'))
      $this->prepareFieldFor('display', $r, $p);
    return Shell::toScreen1($tpl, $r);
  }
  
  /// Édition : dossier parent en lecture
  function edit($ar=null){
    list($p, $tpl, $xmc) = $this->prepareAr('edit', $ar);
    $r = parent::edit($ar);
    if (!$xmc)
      $this->prepareFieldFor('edit', $r, $p);
    return Shell::toScreen1($tpl, $r);
  }

  /// lors de la duplication, une fois qu'on connait tous les numeros de modules,
  /// il faut changer le paramétrage de la base documentaire associée à ce module
  /// cette méthode s'applique sur le nouveau module dupliqué
  function postDuplicateModule($modules) {
    parent::postDuplicateModule($modules);
    $newmoid=$modules[(int)$this->daclMoid];
    if (empty($newmoid)){
      \Seolan\Core\Logs::notice(__METHOD__, "{$this->daclMoid} not in new modules, return");
      return;
    }
    $this->procEditProperties(['options' => ['daclMoid' => $newmoid]]);

    // ajout du modèle de document s'il n'existe pas
    $exists = getDB()->fetchOne("select KOID from _TYPES where modidd=? and modid=?", [$this->_moid, $newmoid]);
    if(!$exists) {
      $ds = DataSource::objectFactoryHelper8('_TYPES');
      $ret=$ds->procInput(['_options'=>['local'=>true],
                           'title'=>"Document",
                           'modid'=>$newmoid,
                           'node'=>'2',
                           'modidd'=>$this->_moid]);
    }
  }
  
  /// Duplication : noeuds et arcs
  function editDup($ar=null){
    list($p, $tpl, $xmc) = $this->prepareAr('insert', $ar);
    $r = parent::editDup($ar);
    if (!$xmc)
      $this->prepareFieldFor('insert', $r, $p);
    return Shell::toScreen1($tpl, $r);
  }
  
  /// Duplication : création du noeud et arc
  function procEditDup($ar=null){
    $r = parent::procEditDup($ar);
    // mise à jour de la base doc
    if (isset($r['oid']) && $r['duplicated']==true){
      $p = new Param($ar, ['_xmc'=>false]);
      if ($p->get('_xmc') !== true){
	$this->createNodesAndLinks($r['oid'], $p->get(static::$parentfieldname));
      }
    }
    return $r;
  }
  /// surcharge de la fonction d'import
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my, $alfunction);
    unset($my['import']);
    if($this->secure('','import')){
      $o1=new \Seolan\Core\Module\Action($this,
					 'import',
					 \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','import'),
					 $this->importUrl(),
					 'edit');
      $o1->menuable=true;
      $my['import']=$o1;
    }
  }
  private function importUrl(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().
	   http_build_query(['moid'=>$this->_moid,
			     'function'=>'preImport',
			     'template'=>'Module/DocSet.import.html']);
  }
  /**
   * dossier parent doit être renseigné
   * taille < à la limite import arrière plan
  */
  function import($ar=NULL){
    $p = new Param($ar, []);
    if (!$p->is_set(static::$parentfieldname)){
      Shell::alert('Renseigner le champ dossier parent');
      Shell::setNext($this->importUrl());
      return;
    }
    $infos=pathinfo($_FILES['file']['name']);
    $size = filesize($_FILES['file']['tmp_name']);
    if($infos['extension']=='xls') 
      $format='xl';
    elseif($infos['extension']=='xlsx') 
      $format='xl07';
    else 
      $format='csv';
    // on ne gère pas l'import en arrière plan : mémorisation impossible du dossier parent
    if (empty($_FILES['file']['tmp_name']) || !$size){
      Shell::alert('Renseigner un fichier');
      Shell::setNext($this->importUrl());
      return;
    }
    $sizecoeff = ['csv'=>1, 'xl'=>0.75, 'xl07'=>4];
    $sizelimit = $this->getConfigurationOption('tzr_max_import_size', TZR_MAX_IMPORT_SIZE);
    if ((int)$size*$sizecoeff[$format] >= (int)$sizelimit*1024*1024/*octets*/){
      Shell::alert('Fichier trop volumineux');
      Shell::setNext($this->importUrl());
      return;
    }
    return parent::import($ar);
  }
  /// import : appel de procInsert pour traiter la basedoc
  protected function procInsertImport($input){
    $p = new Param($input, $_REQUEST);
    
    if (!$p->is_set(static::$parentfieldname))
      throw new \Exception("Trying to import doc without parent folder");
    // input porte '_local'=>1, donc on renseigne ici)
    $input[static::$parentfieldname] = $p->get(static::$parentfieldname);
    return $this->procInsert($input);
  }
  /// pre import : liste des proc. éventuelles + dossier cible
  function  preImport($ar=null){
    $p = new Param($ar, []);
    $timport = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('IMPORTS');
    $r=$timport->browse(['select'=>$timport->select_query(['cond'=>['modid'=>['=',$this->_moid]]]),
			 'pagesize'=>20,
			 'selected'=>0,
			 'tplentry'=>TZR_RETURN_DATA]);
    $r['fields_object'] = [];
    $r['_groups'] = [];
    $this->prepareFieldFor('insert', $r, $p);
    $r['_field'] = array_shift($r['fields_object']);
    unset($r['fields_object']);
    unset($r['_groups']);
    
    Shell::toScreen1('import',$r);
  }
  /// import : dossier cible
  
  /// Mise à jour : répercution de l'UPD
  function procEdit($ar=null){
    $r = parent::procEdit($ar);

    $p = new Param($ar, ['_xmc'=>false]);

    if (is_array($p->get('oid')))
      return;

    $lang = Shell::getLangData();
    
    if ($p->get('_xmc') != true){
      getDB()->execute("update {$this->docmngt->id} node set node.UPD=(select d.UPD from {$this->table} d where d.LANG=? AND d.koid=node.koid) where node.koid=?",[$lang, $p->get('oid')]);
    }
    return $r;
  }
  /// Suppression : document, noeuds et arcs
  function del($ar=null){
    $p = new Param($ar, []);
    $r= parent::del($ar);
    if ($p->get('_xmc') != true && $r){
      $oid = $p->get('oid');
      $this->docmngt->del(['_options'=>['local'=>1],
			   'oid'=>$p->get('oid'),
			   'tplentry'=>TZR_RETURN_DATA,
			   'physical'=>1,
			   'deldoc'=>false
      ]);
    } 
    return $r;
  }
  /// Préparation données formulaire
  protected function prepareAr(string $method, array &$ar):array{
    $p = new Param($ar, ['_xmc'=>false]);
    $xmc = $p->get('_xmc');
    $tpl = $p->get('tplentry');
    if ($xmc !== true){
      $ar['tplentry'] = TZR_RETURN_DATA;
    }
    return [$p, $tpl, $xmc];
  }
  /**
   * Edition (insert, dup, edit)
   * ajout du champ parentdirectory, dans son groupe, groupe callé en première position
   */
  protected function prepareFieldFor(string $method, array &$r, Param $p){
    if (empty($this->docmngt)){
      return;
    }
    $field = Field::objectFactory((object)['FIELD'=>static::$parentfieldname,
					   'FTYPE'=>'\Seolan\Field\Document\Document',
					   'DTAB'=>$this->xset->getTable(),
					   'FCOUNT'=>0,
					   'COMPULSORY'=>1,
					   'QUERYABLE'=>0,
					   'BROWSABLE'=>0,
					   'TRANSLATABLE'=>0,
					   'MULTIVALUED'=>0,
					   'PUBLISHED'=>0,
					   'TARGET'=>null,
					   'FORDER'=>1]);
    $languser = Shell::getLangUser();
    $fieldlabel = Labels::getTextSysLabel('Seolan_Module_DocSet_DocSet', 'parentfieldlabel');
    $field->set_labels([$languser=>$fieldlabel]);
    $fgroup = $this->docmngt->getLabel();
    $options = ['bdocmodule'=>$this->docmngt->_moid,'fgroup'=>[$languser=>$fgroup]];
    $field->_options->procDialog($field, $options);
    switch($method){
      case 'insert':
	$parentoid = null;
	$insertOptions= ['directoriesOnly'=>1,
			 'acl'=>'rw',
			 'title'=>$fieldlabel];
	$ofield = $field->input($parentoid, $insertOptions);
	break;
      case 'edit':
      case 'display':
	$editOptions = [];
	$ofield = $field->_newXFieldVal($editOptions);

	// cas de visualisation d'un doc en corbeille : il n'est plus dans la base doc non plus
	if ($this->docmngt->docExists($p->get('oid'))){
	  $dd = $this->docmngt->display(['oid'=>$p->get('oid'),
					 'tplentry'=>TZR_RETURN_DATA]);
	  $ofield->html = '<div class="csx-docmgt-path csx-docset-parent-path">';
	  $self = $GLOBALS['TZR_SESSION_MANAGER']::complete_self();
	  if (count($dd['path'])>1){
	    $field->label = Labels::getTextSysLabel('Seolan_Module_DocSet_DocSet', 'parentsfieldlabel');
	  }
	  $ico = '';
	  foreach($dd['path'] as $apath){
	    $poid = '';
	    $html = [];
	    foreach($apath as $dir){
	      $url = makeurl($self,
			     ['parentoid'=>$poid,
			      'oid'=>$dir->oid,
			      'moid'=>$this->docmngt->_moid,
			      'function'=>'index',
			      'template'=>'Module/DocumentManagement.index2.html',
			      'tplentry'=>'br']);
	      $poid = $dir->oid;
	      $html[] = "<li><a class=\"cv8-ajaxlink\" href=\"{$url}\">{$dir->title}</a></li>";
	    }
	    $ofield->html .= "<span class='glyicon csico-folder'></span><ul>".implode('', $html).'</ul>';
	  }
	  $ofield->html .= '</div>';
	} else {
	  $ofield->html = '<div class="csx-docmgt-path csx-docset-parent-path">???</div>';
	}

	break;
    }

    // Ajout du groupe base doc / champ parent en première position
    array_unshift($r['fields_object'], $ofield);
    $r['_groups'] = [$fgroup=>[$ofield]]+$r['_groups'];
  }
  /// Edition props : check configuration ok
  function editProperties($ar){
    $r = parent::editProperties($ar);
    // recherche du type associé
    if (!empty($this->daclMoid)){
      $dtypeoid = getDB()->fetchOne('select KOID from _TYPES WHERE modid=? AND modidd=?',
				    [$this->daclMoid,$this->_moid]);
      if (empty($dtypeoid)){
	Shell::alert(
		     sprintf(
			      \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_DocSet_DocSet','undefinedtype'),
			      $this->docmngt->getLabel())
		     );
      }
    }
    
    return $r;
  }
  /// Ajout de la prop. base documentaire associée
  function initOptions(){
    parent::initOptions();
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_DocSet_DocSet','docmngt'),
			    'daclMoid',
			    'module',
			    ['toid'=>XMODDOCMGT_TOID,
			     'compulsory'=>1],
    );
  }
  /**
   * Appels du secure sur le module associé, sauf sur certains appels
   * - accès général au module
   */
  function mySecure($oid, string $func, $user=NULL, $lang=\TZR_DEFAULT_LANG, $checkparent=true){
    if (empty($this->docmngt))
      Logs::critical(__METHOD__,"no base doc set, parent call $func $oid");
    if (empty($this->docmngt) || empty($oid) || !($secList = $this->secGroups($func))){
      Logs::notice(__METHOD__,"parent call $func, $oid ");
      $r = parent::mySecure($oid, $func, $user, $lang, $checkparent);
    } else {
      // transformation de la fonction en droits génériques minima nécessaires
      // par exemple : insert => ':rw'
      // <= EF et BaseDoc n'ont pas les mêmes fonctions
      $nfunc = (strpos($func, ':') !== 0)?':'.$secList[0]:$func; 
      Logs::notice(__METHOD__,"basedoc call $func $nfunc, $oid ");
      $r = $this->docmngt->mySecure($oid, $nfunc, $user, $lang, $checkparent);
    }
    return $r;
  }
  /**
   * il est dit "si retourne true, alors on considerera que tous les oids on des droits spécifiques"
   */
  public function &getObjectsWithSec($uid=null){
    if($this->object_sec){
      return true;
    } else {
      return parent::getObjectsWithSec($uid);
    }
  }
  /// surcharge droits : calcul des droits en insertion/import en fonction des dossiers accessibles
  function secGroups($function, $group=NULL) {
    $insertFunctions = ['preImport', 'import','insert', 'editDup', 'ajaxProcInsertCtrl', 'procInsert', 'procEditDup'];
    if (in_array($function, $insertFunctions)){
      if (!isset($this->insertACLLevel)){
	if( (isset($this->docmngt) &&  $this->docmngt->hasSubObject(null, 99, null, ':rw'))){
	  $insertACLLevel = $this->insertACLLevel = ['list', 'ro', 'rw', 'rwv', 'admin'];
	} else {
	  $insertACLLevel = $this->insertACLLevel = ['admin'];
	}
      }
      foreach($insertFunctions as $name){
	$g[$name] = $insertACLLevel;
      }
    }
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }
}
