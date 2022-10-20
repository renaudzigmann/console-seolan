<?php
/// Module de gestion d'une base documentaire
namespace Seolan\Module\DocumentManagement;
class DocumentManagement extends \Seolan\Core\Module\Module {
  static protected $iconcssclass='csico-library-folder';
  public static $browsetrashtemplate='Module/DocumentManagement.browseTrash.html';
  public $prefix='DM';
  // suffixe de l'oid du dosssier qui contient les modeles
  var $patternSuffix = 'TEMPLATES';
  // table (xset) de description par defaut des dossiers
  public $defaultFolderTable = 'REP1';
  var $doccache=array();
  var $documentssec=true;
  public $trackchanges=true;
  public $trackaccess=false;
  public $searchtemplate='Module/DocumentManagement.searchResult.html'; // Recherhe generale (Search)

  var $_s=array();

  function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->idx = $this->prefix.'IDX';
    $this->id = $this->prefix.'ID';
    $this->lang = \Seolan\Core\Shell::getLangData();
    $this->_f=array();
    $this->opts=\Seolan\Library\Opts::getOpt(\Seolan\Core\User::get_current_user_uid(), $this->_moid, 'opts');
    \Seolan\Core\Labels::loadLabels('Seolan_Module_DocumentManagement_DocumentManagement');
  }
  /// surcharge pour initialisation des préférences
  function load($moid){
    parent::load($moid);
    if ($this->saveUserPref){
      $docmode = $this->getPref('docmode');
      if (!empty($docmode))
        $this->_setSession('docmode', $this->getPref('docmode'));
    } else {
      $this->_clearSession('docmode');
    }
  }

  /// Liste des tables utilisées par le module
  public function usedTables(){
    $ret=$this->usedMainTables();
    $ret[]=$this->idx;
    $ret[]=$this->id;
    return $ret;
  }
  public function usedMainTables() {
    $rs=getDB()->select('select modidd,dtab from _TYPES where modid=?', array($this->_moid));
    $ret=array();
    while($ors=$rs->fetch()){
      if(!empty($ors['dtab'])) $ret[]=$ors['dtab'];
      else{
	$mod=\Seolan\Core\Module\Module::objectFactory($ors['modidd']);
	if(is_object($mod)){
	  $t=$mod->usedTables();
	  if($t) $ret=array_merge($ret,$t);
	}
      }
    }
    return $ret;
  }

  /// rend la liste des types de doc ou le type de doc d'oid $oid
  public function &getTypes($oid=NULL) {
    $foooid=NULL;
    if(empty($this->doctypes) || empty($this->doctypes[$oid])) {
      // recherche des types de document
      $mods=array_keys(\Seolan\Core\Module\Module::modulesUsingTable('_TYPES',false,false,false));
      if(!count($mods)) {
        \Seolan\Core\Module\Module::clearCache();
        $mods=array_keys(\Seolan\Core\Module\Module::modulesUsingTable('_TYPES',false,false,false));
        if(!count($mods)) return false;
      }
      $moid=$mods[0];
      $mod=\Seolan\Core\Module\Module::objectFactory($moid);
      $oids=$mod->xset->browseOids(array('_filter'=>'(modid="'.$this->_moid.'" OR KOID="'.$oid.'")','_options'=>array('local'=>true)));

      if($mod->object_sec) $oidsrights=$GLOBALS['XUSER']->getObjectsAccess($mod,\Seolan\Core\Shell::getLangData(),$oids);
      foreach($oids as $i=>$toid){
	$foo1=$mod->xset->rDisplay($toid);
	if(!$mod->object_sec || array_key_exists('ro',$oidsrights[$i])) $foo1['_rwsecure']=true;
	if($foo1['omodid']->raw==$this->_moid) {
	  $this->doctypes[$toid]=$foo1;
	} else {
	  $foo1['otitle']->html.=' / '.\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','source').' '.$foo1['omodid']->html;
	}
	if($foo1['oid']==$oid) $foooid=$foo1;
      }
    }
    if(empty($oid)) return $this->doctypes;
    elseif(!empty($foooid)) return $foooid;
    else return $this->doctypes[$oid];
  }

  function protectedOids() {
    $oidtoprotect=array('_TYPES:root-'.$this->_moid,
			'_TYPES:lostandfound-'.$this->_moid,
			'_TYPES:trash-'.$this->_moid,
			'_TYPES:archive-'.$this->_moid);
    return $oidtoprotect;
  }


  /// Recupere un repository en fonction du type de document
  function getRepositoryByType($doid){
    $auth=$this->getTypes($doid);
    if(empty($auth)) return false;
    $xt=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_TYPES');
    $d1=$xt->display(array('oid'=>$doid,'tplentry'=>TZR_RETURN_DATA));
    $mod=\Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(NULL,$d1);
    return $mod;
  }

  /// Suppression du module
  function delete($ar=NULL) {
    parent::delete($ar);
    // Suppression des types de docs du module
    if(\Seolan\Core\System::tableExists('_TYPES'))
      getDB()->execute("delete from _TYPES where modid=?", [$this->_moid]);
  }

  /// Initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $slabel=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','security','text');
    $this->_options->setOpt('Gerer la securite sur les documents', 'documentssec', 'boolean', NULL, true, $slabel);
    $tlabel=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','tracking');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','trackaccess'),'trackaccess','boolean',NULL,NULL,$tlabel);
    $this->_options->setOpt('Préfixe','prefix','text');
    $this->_options->setOpt('Table par défaut des repertoires','defaultFolderTable','table');
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['index']=array('list','ro','rw','rwv','admin');
    $g['index2']=array('list','ro','rw','rwv','admin');
    $g['index2Light']=array('list','ro','rw','rwv','admin');
    $g['preDel'] = array('rw','rwv','admin');
    $g['preFullDelete'] = ['admin'];
    $g['viewDir']=array('list', 'ro','rw','rwv','admin');
    $g['ajaxLoadDir']=array('list','ro','rw','rwv','admin');
    $g['ajaxLoadDirTree']=array('list','ro','rw','rwv','admin');
    $g['input']=array('rw','rwv','admin');
    $g['procInput']=array('rw','rwv','admin');
    $g['ajaxProcInputCtrl']=array('rw','rwv','admin');
    $g['journal']=array('ro','rw','rwv','admin');
    $g['display']=array('ro','rw','rwv','admin');
    $g['editDup']=array('rw','rwv','admin');
    $g['procEditDup']=array('rw','rwv','admin');
    $g['edit']=array('rw','rwv','admin');
    $g['procEdit']=array('rw','rwv','admin');
    $g['ajaxProcEditCtrl']=array('rw','rwv','admin');
    $g['ajaxProcEditDupCtrl']=array('rw','rwv','admin');
    $g['del']=array('rw','rwv','admin');
    $g['fullDelete'] = ['admin'];
    $g['search']=array('list','ro','rw','rwv','admin');
    $g['advsearch']=array('list','ro','rw','rwv','admin');
    $g['linkTo']=array('list', 'ro', 'rw','rwv','admin');
    $g['getLast']=array('list','ro','rw','rwv','admin');
    $g['getUnread']=array('list','ro','rw','rwv','admin');
    $g['markAsRead']=array('list','ro','rw','rwv','admin');
    $g['subscribe']=array('list','ro','rw','rwv','admin');
    $g['preSubscribe']=array('list','ro','rw','rwv','admin');
    $g['admin']=array('admin');
    $g['documentsDownload']=array('ro','rw','rwv','admin');
    $g['export']=array('ro','rw','rwv','admin');
    $g['exportFTP']=array('ro','rw','rwv','admin');
    $g['exportBatch']=array('ro','rw','rwv','admin');
    $g['procSendACopyTo']=array('list', 'ro','rw','rwv','admin');
    $g['prePrintDisplay']=array('list','ro','rw','rwv','admin');
    $g['printDisplay']=array('ro','rw','rwv','admin');
    $g['share']=array('rw','rwv','admin');
    $g['procShare']=array('rw','rwv','admin');
    $g['setRootOid']=array('ro','rw','rwv','admin');
    $g['clearRootOid']=array('ro','rw','rwv','admin');
    $g['getComments']=array('ro', 'rw', 'rwv', 'admin');
    $g['insertComment']=array('ro', 'rw', 'rwv', 'admin');
    $g['cleanFoldersDocuments'] = array('ro', 'rw', 'rwv', 'admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }
  /**
   * traitement de documents périmés
   * -> refus du user root (on veut tracer la tâche par son user)
   * -> dossiers à traiter (passer l'oid racine pour tout traiter)
   * ? corbeille, lost and found ?
   * -> delai en jours
   */ 
  function cleanFoldersDocuments(\Seolan\Module\Scheduler\Scheduler $scheduler=null, $o, $more){
    $taskEnd = function($status, $message) use($scheduler, $o) {
      if ($scheduler != null){
        $scheduler->setStatusJob($o->KOID, $status, $message);
        return null;  
      } else {
        return [$status=='finished'?true:false, "$status $message"];
      }
    };
    if (empty($more->uid) || $more->uid == TZR_USERID_ROOT || $more->uid == TZR_ROOTAUTH_ALIAS){
      return $taskEnd('refused', 'wrong uid');
    }
    if (empty($more->folders)){
      return $taskEnd('refused', 'no folders');
    }
    if (empty($more->delay) || $more->delay <= 0){
      return $taskEnd('refused', 'no delay');
    }
    $more->folders = explode(',', $more->folders);
    $myProtected = [$this->defaultFolderTable.':lostandfound-'.$this->_moid];
    foreach($more->folders as $folderoid){
      if (in_array($folderoid, $myProtected)){
        return $taskEnd('refused', $folderoid.' is protected ');
      }
      $ok = getDB()->fetchOne("SELECT 1 FROM ".$this->id." WHERE KOID=?", [$folderoid]);
      if (!$ok){
        return $taskEnd('error', $folderoid.' do no exists');
      }
    }
    $datepurge = date('Y-m-d H:i:s', strtotime(date('Y-m-d 00:00:00')." - {$more->delay} days"));
    $cr = "\nclean documents folders, delay : {$more->delay} days {$datepurge}";
    foreach(array_unique($more->folders) as $folderoid){
      $this->cleanFolderDocuments($folderoid,$datepurge,true,$cr);
    }
    return $taskEnd('finished', $cr);
  }
  /// Nettoie les documents d'un dossier
  protected function cleanFolderDocuments($folderoid, $datepurge, $subdir=true, &$cr=''){
    $folder = $this->index(['oid'=>$folderoid,'tplentry'=>TZR_RETURN_DATA,'maxlevel'=>9999]);
    $node=&$folder['here'];
    $nbdel=0;
    $cr .= "\n{$node->title}, $folderoid :";
    // sous dossiers
    if ($subdir){
      foreach($node->dirsoids as $i=>$oidf) {
        $this->cleanFolderDocuments($oidf, $datepurge, $subdir, $cr);
      }
    }
    // documents
    foreach($node->documents as $doid=>$doc){
      if ($doc->fields['oUPD']->raw < $datepurge){
        $this->del(['physical'=>true,'oid'=>$doid]);
        $nbdel++; 
      }
    }
    if ($nbdel>0)
      $cr .= "\n\t $nbdel delete";
  }
  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&_function=index&tplentry=br&template=Module/DocumentManagement.index2.html&maxlevel=2&'.
      'clear=0&oid='.$this->rootOid();
  }

  /// Cette fonction est appliquee pour afficher l'ensemble des methodes de ce module
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my);
    $myclass=get_class($this);
    $moid=$this->_moid;
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $dir='Module/DocumentManagement.';
    $trash = $_REQUEST['_trash']??NULL;
    $myoid = $_REQUEST['oid']??NULL;
    $mypoid = $_REQUEST['_parentoid']??NULL;

    if(empty($myoid)) $myoid=$this->rootOid();

    $ri=\Seolan\Core\User::secure8maxlevel($this);
    if($ri=='admin'){
      $o1=new \Seolan\Core\Module\Action($this, 'exportfs', \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','export','text'),
			    '&moid='.$moid.
			    '&_function=index&tplentry=br&'.
			    'template='.$dir.'export.html&_parentoid='.$mypoid.'&oid='.$myoid);
      $o1->homepageable=false;
      $o1->menuable=true;
      $o1->quicklinkable=true;
      $o1->group='edit';
      $my['exportfs']=$o1;
    }

    $suid=getSessionVar('SUID');
    if(!empty($suid)) {
      if(!empty($this->opts['home'])) {
	$o1=new \Seolan\Core\Module\Action($this, 'clearrootoid', \Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','clearrootoid','text'),
			      '&moid='.$moid.'&_function=clearRootOid&tplentry=br&'.'template='.$dir.'index2.html&maxlevel=2&clear=1',
			      'more');
	$o1->menuable=true;
	$my['setrootoid']=$o1;
      } elseif(!empty($myoid)) {
	$o1=new \Seolan\Core\Module\Action($this, 'setrootoid', \Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','setrootoid','text'),
			      '&moid='.$moid.'&_function=setRootOid&tplentry=br&'.'template='.$dir.'index2.html&maxlevel=2&clear=1&'.
			      '&oid='.$myoid,'more');
	$o1->menuable=true;
	$my['setrootoid']=$o1;
      }
    }

    $rw=$this->secure($myoid,'edit');
    $admin=$this->secure($myoid,'secEdit');
    $function = \Seolan\Core\Shell::_function();
    $ofunction = $this->_functionOverride(); 
    if (($function != 'index2' && $ofunction != 'index2')
	||($ofunction == 'index')){
      // actions pour fiche document (plate)
      // Arbo a plat
      $o1=new \Seolan\Core\Module\Action($this, 'index1', \Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','index','text'),
					 '&moid='.$moid.'&_function=index&tplentry=br&'.'template='.$dir.'index2.html&maxlevel=2&clear=1&'.
			    '_parentoid='.$mypoid.'&oid='.$myoid,'display');
      $o1->order=1;
      $o1->setToolbar('Seolan_Core_General','browse');
      $my['index1']=$o1;
      // Arborescence
      $o1=new \Seolan\Core\Module\Action($this, 'index2', \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','tree','text'),
			    '&moid='.$moid.'&clear=1&_function=index2&tplentry=br&template='.$dir.'index3.html&maxlevel=10&'.
			    '_parentoid='.$mypoid.'&oid='.$myoid,'display');
      $o1->order=2;
      $o1->setToolbar('Seolan_Core_General','tree');
      $my['index2']=$o1;
      // Recherche avancée
      if($this->secure('','advsearch')){
	$o1=new \Seolan\Core\Module\Action($this,'advsearch', \Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','advsearch','text'),
			      '&moid='.$moid.'&_function=advsearch&tplentry=br&template='.$dir.'advsearch.html','display');
	$o1->order=3;
	$o1->setToolbar('Seolan_Module_DocumentManagement_DocumentManagement','advsearch');
	$my['advsearch']=$o1;
      }
      if(!empty($myoid) && \Seolan\Core\Kernel::isAKoid($myoid)) {
	$o1=new \Seolan\Core\Module\Action($this,'display',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'view','text'),
			      '&moid='.$moid.'&_function=display&tplentry=br&'.'template='.$dir.'display.html&oid='.$myoid,'edit');
	$o1->order=1;
	$o1->setToolbar('Seolan_Core_General','display');
	$my['display']=$o1;
	if($myoid!=$this->rootOid()) {
	  if($rw){
	    // Edition
	    $o1=new \Seolan\Core\Module\Action($this,'edit',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit','text'),
				  '&moid='.$moid.'&_function=edit&tplentry=br&template='.$dir.'edit.html&_parentoid='.$mypoid.
				  '&oid='.$myoid,'edit');
	    $o1->order=2;
	    $o1->setToolbar('Seolan_Core_General','edit');
	    // duplication
	    $my['edit']=$o1;
	    $o1=new \Seolan\Core\Module\Action($this,'edit',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','clone'),
				  '&moid='.$moid.'&_function=editDup&tplentry=br&template='.$dir.'edit.html&_parentoid='.$mypoid.
				  '&oid='.$myoid,'edit');
	    $o1->order=3;
	    $o1->menuable=1;
	    $my['clone']=$o1;
	    // Réservation
	    if(($xlock=\Seolan\Core\Shell::getXModLock()) && !$xlock->locked($myoid,TZR_DEFAULT_LANG)) {
	      if(!empty($myoid)) $dmyoid=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($myoid, $this);
	      if(is_object($dmyoid) && !($dmyoid instanceof \Seolan\Module\DocumentManagement\Model\Document\Directory)) {
		$o1=new \Seolan\Core\Module\Action($this,'lock', \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','lock','text'),
				      '&moid='.$moid.'&_function=edit&tplentry=br&_mode=lock&'.
				      'template='.$dir.'edit.html&_parentoid='.$mypoid.'&oid='.$myoid,'edit');
		$o1->order=3;
		$o1->setToolbar('Seolan_Core_General','lock');
		$my['lock']=$o1;
	      }
	    }
	  }
	  // Droits
	  if($admin) {
	    $self=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true);
	    $o1=new \Seolan\Core\Module\Action($this,'security',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','security','text'),
				  'javascript:TZR.editSec("'.$self.'","'.$moid.'","'.$myoid.'",'.$uniqid.');','edit');
	    $o1->menuable=true;
	    $o1->setToolbar('Seolan_Core_General','security');
	    $my['security2']=$o1;
	  }
	  // Pour ces deux actions les droits dependent de la destination
	  // Copier
	  $o1=new \Seolan\Core\Module\Action($this,'linkTo',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','copy','text'),
				'javascript:'.$uniqid.'.copyselected("'.$myoid.'");','edit');
	  $o1->menuable=true;
	  $my['copy']=$o1;
	  // Liaison
	  $o1=new \Seolan\Core\Module\Action($this,'linkTo',\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','link','text'),
				'javascript:'.$uniqid.'.linkselected("'.$myoid.'");','edit');
	  $o1->menuable=true;
	  $my['link']=$o1;
	  // Déplacement
	  if($rw){
	    $o1=new \Seolan\Core\Module\Action($this,'linkTo',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','moveto','text'),
				  'javascript:'.$uniqid.'.moveselected("'.$myoid.'");','edit');
	    $o1->setToolbar('Seolan_Core_General','moveto');
	    $my['move']=$o1;
	  }
	} else { // sur rootoid
	  // Pour ces deux actions les droits dependent de la destination
	  // Copier
	  $o1=new \Seolan\Core\Module\Action($this,'linkTo',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','copy','text'),
				'javascript:'.$uniqid.'.copyselected("'.$myoid.'", true);','edit');
	  $o1->menuable=true;
	  $my['copy']=$o1;
	  // Liaison
	  $o1=new \Seolan\Core\Module\Action($this,'linkTo',\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','link','text'),
				'javascript:'.$uniqid.'.linkselected("'.$myoid.'", 0, 0, true);','edit');
	  $o1->menuable=true;
	  $my['link']=$o1;

	  // Déplacement
	  if($rw){
	    $o1=new \Seolan\Core\Module\Action($this,'linkTo',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','moveto','text'),
				  'javascript:'.$uniqid.'.moveselected("'.$myoid.'");','edit');
	    $o1->setToolbar('Seolan_Core_General','moveto');
	    $my['move']=$o1;
	  }
	  // déplacement/copie/lien des documents posés dans le dossier racine
	  if($rw){
	    $o1=new \Seolan\Core\Module\Action($this,'linkTo',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','moveto','text'),
				  'javascript:'.$uniqid.'.moveselected("'.$myoid.'", true);','edit');
	    $o1->setToolbar('Seolan_Core_General','moveto');
	    $my['move']=$o1;
	  }
	}
	
	// Suppression
	if($this->secure($myoid,'del')){
	  $o1=new \Seolan\Core\Module\Action($this,'del',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'delete','text'),'javascript:'.$uniqid.'.deleteselected('.($myoid!=$this->rootOid()?'':'true').');',
				'edit');
	  $o1->order=4;
	  $o1->setToolbar('Seolan_Core_General','delete');
	  $my['delete']=$o1;
	}
      }
    } else { // quand index2 (vue arbo)
      // action vue a plat du dossier en cours
      $o1=new \Seolan\Core\Module\Action($this, 'index1', \Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','index','text'),'javascript:'.$uniqid.'.gotonode();');
      $o1->homepageable=$o1->menuable=true;
      $o1->quicklinkable=true;
      $o1->group='display';
      $o1->setToolbar('Seolan_Core_General','browse');
      $my['index1']=$o1;
      // Suppression
      if($this->secure($myoid,'del')){
	$o1=new \Seolan\Core\Module\Action($this,'del',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete','text'),'javascript:'.$uniqid.'.deleteselected();',
			      'edit');
	$o1->menuable=true;
	$o1->setToolbar('Seolan_Core_General','delete');
	$my['delete']=$o1;
      }
    }
    $fromFunction = \Seolan\Core\Shell::_function();
    if (in_array($fromFunction, ['edit','display', 'index', 'index2']) &&  $this->secure($myoid,'fullDelete')){
      // Suppression complète
      
      $fullDeleteSelectedOnly = ($myoid!=$this->rootOid())?'false':'true';
      $uniqidv = \Seolan\Core\Shell::uniqid();
      $o1=new \Seolan\Core\Module\Action($this,
					 'fullDelete',
					 \Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table',
									  'full_delete','text'),
					 "javascript:TZR.DocMngt.preFullDelete('{$uniqidv}','{$myoid}',{moid:'{$moid}',fromFunction:'{$fromFunction}', onlySelected:{$fullDeleteSelectedOnly}});",
					 'edit');
      $o1->menuable=true;
      $my['fulldelete']=$o1;
    }
    if(!empty($myoid) && \Seolan\Core\Kernel::isAKoid($myoid)){
      // Abonnement
      $xmodsubmoid=\Seolan\Core\Module\Module::getMoid(XMODSUB_TOID);
      if(!empty($xmodsubmoid) && $this->secure($myoid,'preSubscribe')){
	$o1=new \Seolan\Core\Module\Action($this,'subscribe',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Subscription_Subscription', 'subscribe', 'text'),
			      '&moid='.$this->_moid.'&oid='.$myoid.'&_function=preSubscribe&tplentry=br&template=Module/DocumentManagement.sub.html',
			      'more');
	$o1->menuable=true;
	$my['subscribe']=$o1;
      }
      if ($this->sendacopyto &&  $this->secure($myoid,'sendACopyTo')){
	// Avertir
	if(\Seolan\Core\Shell::_function()=='display' || \Seolan\Core\Shell::_function()=='edit'){
	  $o1=new \Seolan\Core\Module\Action($this,'sendACopy',\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','sendacopyto','text'),
				'&moid='.$this->_moid.'&tplentry=br&oid='.$myoid.
				'&_function=sendACopyTo&template=Core/Module.sendacopyto.html&tplentry=br','more');
	}else{
	  $o1=new \Seolan\Core\Module\Action($this,'sendACopy',\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','sendacopyto','text'),
				'javascript:TZR.applySelected("sendACopyTo",document.forms["browse'.\Seolan\Core\Shell::uniqid().'"],"","Core/Module.sendacopyto.html",'.
				'"br","'.addslashes(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','error_select_object','text')).'");','more');
	}
	$o1->menuable=true;
	$my['sendacopy']=$o1;
      }
    }

    // Voir les derniers documents
    $o1=new \Seolan\Core\Module\Action($this,'lastdoc',\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','lastdoc','text'),
			  '&moid='.$this->_moid.'&_function=getLast&tplentry=br&template=Module/DocumentManagement.getlast.html','display');
    $o1->menuable=true;
    $my['lastdoc']=$o1;
    // Voir les documents non lus
    if($this->trackaccess && $this->secure('','getUnread')){
      $o1=new \Seolan\Core\Module\Action($this,'lastdoc',\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','unread','text'),
			    '&moid='.$this->_moid.'&_function=getUnread&tplentry=br&template=Module/DocumentManagement.getUnread.html','display');
      $o1->menuable=true;
      $my['unread']=$o1;
    }

    // Création du contexte si en mode interactif
    if($this->interactive && !$trash)  $this->mkContext2($my, $myoid);

    
    if ('browseTrash' == $function && isset($my['trash'])){
      $my['stack'][]=$my['trash'];
    }
  }

  /// Menu spécifique au display
  function al_display(&$my){
    $this->getSSMAl($my);
    $br=\Seolan\Core\Shell::from_screen('br');
    if(method_exists($br['here']->repository,'printDisplay') && $br['here']->repository->secure($oid,'printDisplay')){
      $o1=new \Seolan\Core\Module\Action($this,'print',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','print','text'),
      'javascript:TZR.Dialog.openURL("'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&function=prePrintDisplay&moid='.$this->_moid.'&template=Module/Table.preprintview.html&tplentry=br&oid='.$br['oid'].'");','display');
      $o1->order=3;
      $o1->setToolbar('Seolan_Core_General','print');
      $my['print']=$o1;
    }
    if(method_exists($br['here']->repository,'') && $br['here']->repository->secure($oid,'printDisplay')){
      $o1=new \Seolan\Core\Module\Action($this,'print',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','print','text'),
      'javascript:TZR.Dialog.openURL("'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&function=prePrintDisplay&moid='.$this->_moid.'&template=Module/Table.preprintview.html&tplentry=br&oid='.$br['oid'].'");','display');
      $o1->order=3;
      $o1->setToolbar('Seolan_Core_General','print');
      $my['print']=$o1;
    }
  }
  function al_edit(&$my){
    $this->al_display($my);
  }

  /// duplication d'un module, méthode interne (retour : tables => liste des tables dupliquées par le module (cle : ancienne table, valeur : nouvelle table))
  function _duplicateModule($newmoid,&$params,$prefix) {
    if(!empty($params['mods']) && is_array($params['mods'])) $mods=$params['mods'];
    else $mods=array();
    if(!empty($params['tables']) && is_array($params['tables'])) $tables=$params['tables'];
    else $tables=array();
    if(!empty($params['documenttypes']) && is_array($params['documenttypes'])) $documenttypes=$params['documenttypes'];
    else $documenttypes=[];

    // création des tables dmid et dmidx
    $newprefix = \Seolan\Module\DocumentManagement\Wizard::newPrefix();
    getDB()->execute('CREATE TABLE '.$newprefix.'ID like '.$this->id);
    getDB()->execute('CREATE TABLE '.$newprefix.'IDX like '.$this->idx);
    \Seolan\Module\DocumentManagement\Wizard::createStructure($newprefix);
    $params['prefix']=$newprefix;
    $tables[$this->id]=$newprefix.'ID';
    $tables[$this->idx]=$newprefix.'IDX';
    
    // copie des donnees dans la structure
    getDB()->execute('INSERT INTO '.$newprefix.'ID select * from '.$this->id);
    getDB()->execute('INSERT INTO '.$newprefix.'IDX select * from '.$this->idx);

    // Duplication de toutes les tables de documents et des types de doc
    $moid=$this->_moid;
    $all=getDB()->select('select KOID,dtab,modidd from _TYPES where modid=?', array($this->_moid));
    $dstype=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_TYPES');
    while($data=$all->fetch()) {
      $tab=$ntab=NULL;
      $koid=$data['KOID'];
      if(empty($data['modidd']) && !empty($data['dtab'])) {
	// duplication du module dans le cas ou le doc est gere par une source de donnees
	// Etape 1 : duplication de la table
	$tab=$data['dtab'];
	if(empty($tables[$tab])){
	  $ntab=$ar['newtable']=\Seolan\Model\DataSource\Table\Table::newTableNumber();
	  $tables[$tab]=$ntab;
	  $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$data['dtab']);
	  if(($pos=strpos($xset->getLabel(),':'))!==false) $ar['mtxt']=$prefix.substr($xset->getLabel(),$pos);
	  else $ar['mtxt']=$prefix.':'.$xset->getLabel();
	  $ar['data']=true;
	  $ar['_options']=array('local'=>1);
	  $xset2=$xset->procDuplicateDataSource($ar);
	}else{
	  $ntab=$tables[$tab];
	}
        \Seolan\Core\Logs::debug('\Seolan\Module\DocumentManagement\DocumentManagement::_duplicateModule: cloning table '.$tab.' to '.$ntab);
	// etape 2 :duplication de l'entree dans la liste des documents type
	$ardup=array('_options'=>array('local'=>1));
	$ardup['oid']=$koid;
	$nkoid=$dstype->duplicate($ardup);
	getDB()->execute('UPDATE _TYPES set dtab=?,modid=? where KOID=?',array($ntab,$newmoid,$nkoid));
	$documenttypes[$koid]=$nkoid;
      } elseif(!empty($data['modidd'])) {
	// duplication de la table quand les doc sont gere par un un module
	if(empty($mods[$data['modidd']])){ /* si la table n'a pas été encore dupliquée */
	  $module=\Seolan\Core\Module\Module::objectFactory($data['modidd']);
	  // etape 1 : duplication du module
	  $ret=$module->duplicateModule(array('tables'=>$tables,'group'=>(!empty($params['group'])?$params['group']:$this->group)));
	  $thenewmoid=$ret['moid'];
	  $localnewmodule=\Seolan\Core\Module\Module::objectFactory($thenewmoid);
	  // le travail après duplication
	  $localnewmodule->postDuplicateModule([$this->_moid=>$newmoid]);
	  unset($localnewmodule);
	  $tables=$tables+$ret['duplicatetables'];
	  $mods=$mods+$ret['duplicatemods'];
	}else{
	  $thenewmoid=$mods[$data['modidd']];
	}
	// etape 2 : duplication de l'entree dans la liste des documents type
	$exists=getDB()->fetchOne("SELECT KOID FROM _TYPES WHERE modidd=? AND modid=?", [$thenewmoid, $newmoid]);
	if(!$exists) {
	  $ardup=array('_options'=>array('local'=>1));
	  $ardup['oid']=$koid;
	  $nkoid=$dstype->duplicate($ardup);
	  $documenttypes[$koid]=$nkoid;
	  $newmodule=\Seolan\Core\Module\Module::objectFactory($thenewmoid);
	  $ntab=$newmodule->table;
	  $tab=$module->table;
	  // maj des modules (et ev. table dans le nouveau type)
	  if (!empty($data['dtab'])){
	    getDB()->execute('UPDATE _TYPES set dtab=?, modidd=?,modid=? where KOID=?',array($ntab, $thenewmoid,$newmoid,$nkoid));
	  } else {
	    getDB()->execute('UPDATE _TYPES set modidd=?,modid=? where KOID=?',array($thenewmoid,$newmoid,$nkoid));
	  }
	  \Seolan\Core\Logs::debug('\Seolan\Module\DocumentManagement\DocumentManagement::_duplicateModule: cloning module '.$data['modidd'].' to '.$thenewmoid.' table '.$ntab);
	} else { // on doit reporter le nouveau type, pour la gestion des droits, dans le cas des docset
	  $newmodule=\Seolan\Core\Module\Module::objectFactory($thenewmoid);
	  if ($newmodule instanceof \Seolan\Module\DocSet\DocSet)
	    $documenttypes[$koid]=$exists;
	}
      }
      // on renomme la racine // à voir ? ou est la duplication ?
      if(preg_match('@_TYPES\:(root|lostandfound)-'.$moid.'@i',$koid, $matches)) {
	$nnkoid='_TYPES:'.$matches[1].'-'.$newmoid;
	getDB()->execute("UPDATE _TYPES set KOID=? where KOID=?",array($nnkoid,$nkoid));
	$nkoid=$nnkoid;

	// on renseigne la table des dossiers par defaut
	if($oroot=getDB()->fetchRow('SELECT * FROM _TYPES WHERE KOID= ?', array($nkoid)))
	  if(!empty($oroot['dtab'])) $params['defaultFolderTable']=$oroot['dtab'];
      }
      if(!empty($ntab) && !empty($tab)) {
	// on renomme les oids un peu partout
	getDB()->execute("update {$newprefix}ID set UPD=UPD,KOID=? where KOID=?", array($ntab.':lostandfound-'.$newmoid,$tab.':lostandfound-'.$moid));
	getDB()->execute("update {$newprefix}ID set UPD=UPD,KOID=? where KOID=?", array($ntab.':root-'.$newmoid,$tab.':root-'.$moid));
	getDB()->execute("update {$newprefix}ID set UPD=UPD,KOID=replace(KOID,?,?)",[$tab.':', $ntab.':']);
	getDB()->execute("update {$newprefix}ID set UPD=UPD,DTYPE=? WHERE DTYPE=?",[$nkoid, $koid]);
	getDB()->execute("update {$newprefix}IDX set KOIDDST=? WHERE KOIDDST=?",[$ntab.':root-'.$newmoid,$tab.':root-'.$moid]);
	getDB()->execute("update {$newprefix}IDX set KOIDSRC=? WHERE KOIDSRC=?",[$ntab.':root-'.$newmoid,$tab.':root-'.$moid]);
	getDB()->execute("update {$newprefix}IDX set KOIDDST='$ntab:lostandfound-$newmoid' WHERE KOIDDST='$tab:lostandfound-$moid'");
	getDB()->execute("update {$newprefix}IDX set KOIDSRC='$ntab:lostandfound-$newmoid' WHERE KOIDSRC='$tab:lostandfound-$moid'");
	getDB()->execute("update {$newprefix}IDX set KOIDSRC=replace(KOIDSRC,?,?)", ["$tab:", "$ntab:"]);
	getDB()->execute("update {$newprefix}IDX set KOIDDST=replace(KOIDDST,?,?)", ["$tab:", "$ntab:"]);
      }
    }
    return array('duplicatetables'=>$tables,
		 'duplicatemods'=>$mods,
		 'duplicatedocumenttypes'=>$documenttypes);
  }


  function getSSMAl(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $br=\Seolan\Core\Shell::from_screen('br');
    $myoid=$br['oid'];
    $max=count($br['__ssmod']);
    $ssmmenu=array();
    for($i=0;$i<$max;$i++) {
      $f='ssmod'.$i;
      $ft=$br['__ssprops'][$i]['modulename'];
      $ff=$br['__ssprops'][$i]['linkedfield'];
      $fa=$br['__ssprops'][$i]['activate_additem'];
      $fmoid=$br['__ssprops'][$i]['_moid'];
      if($ff && ($ff!='*none*' && $fa && $br['__ssinsert'][$i])){
	$o1=new \Seolan\Core\Module\Action($this,$f,$br['__ssprops'][$i]['modulename'],
					   'javascript:'.$uniqid.'.addTabs("'.$fmoid.'","'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$fmoid.'&_linkedfield='.$ff.
			      "&_parentoid=$myoid&function=insert&template=Module/Table.new.html&tplentry=br&_raw=1&_ajax=1&skip=1".
			      '&tabsmode=1","'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','add','text').' : '.$ft.'");');
	$o1->menuable=true;
	$ssmmenu[]=$o1;
      }
    }
    if(count($ssmmenu)>1){
      $o1=new \Seolan\Core\Module\Action($this,'alladd',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','add','text'),'#');
      $o1->menuable=true;
      $o1->newgroup='ssm';
      $my['ssm']=$o1;
      foreach($ssmmenu as &$o1){
	$o1->group='ssm';
	$my[$o1->xfunction]=$o1;
      }
    }else{
      foreach($ssmmenu as &$o1){
	$o1->name=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','add','text').' : '.$o1->name;
	$my[$o1->xfunction]=$o1;
      }
    }
  }
  /// Existance d'un document dans la base doc
  public function docExists($oid){
    return getDB()->fetchOne('select 1 from '.$this->id.' where KOID=?', [$oid]);
  }
  /// Suppression dans le module des documents qui sont effaces depuis d'autres modules
  function _removeRegisteredOid($oid) {
    \Seolan\Core\Logs::debug(__METHOD__.' '.$oid);
    $docexists = getDB()->fetchOne('select 1 from '.$this->id.' where KOID=?', [$oid]);
    if ($docexists == '1'){
      getDB()->execute('delete from '.$this->idx.' where KOIDSRC=?',array($oid));
      getDB()->execute('delete from '.$this->id.' where KOID=?',array($oid));
      $this->delDocFromSearchEngine($oid);
    }
  }

  function status($ar=NULL) {
    // inserez votre code personnalise ici
  }

  /**
   * Affichage sur deux niveaux des dossiers et des fichiers
   * parametres :
   * - nosess : ne pas mémoriser le mode 
   * - clear : forcer le mode (appel depuis les icones de menus)
   */
  function index($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('up'=>1,'down'=>1,'oid'=>'','maxlevel'=>2, 'showfiles'=>1, 'clear'=>0, 'nosess'=>0));
    $tplentry=$p->get('tplentry');
    $oid=$p->get('oid');
    $nosess=$p->get('nosess');
    // Si pas d'oid, on prend la racine
    if(empty($oid)) $_REQUEST['oid']=$oid=$this->rootOid();
    // mode mémorisé : appel par main action ou depuis un menu
    if($this->saveUserPref  && $GLOBALS['XSHELL']::_function() == 'index'
       && $tplentry != 'TZR_RETURN_DATA'){
      if($p->get('clear')==1) {
	$this->_clearSession('docmode');
      }  elseif ($this->_getSession('docmode')=='index2') {
	if ($p->is_set('template')){ 
	  $GLOBALS['XSHELL']->changeTemplate('Module/DocumentManagement.index3.html');
	}
	return $this->index2(['maxlevel'=>10,'oid'=>$oid]);
      }
    }
    if (empty($nosess)){
      $this->setPref1('docmode','index');
      $this->_setSession('docmode', 'index');
    }
    $maxlevel=$p->get('maxlevel');
    $showfiles=$p->get('showfiles');
    //$fileorder=$p->get('fileorder');
    $directoryorder=$p->get('directoryorder');
    if (!empty($directoryorder)) {
      $fileorder=$directoryorder;
    }

    // Recherche du dossier parent si le document que l'on essaie d'afficher n'est pas un dossier
    $rs=getDB()->select('select * from '.$this->id.' left outer join _TYPES on '.$this->id.'.DTYPE=_TYPES.KOID '.
		    'where '.$this->id.'.KOID=?',array($oid));
    if($rs && ($ors=$rs->fetch())) {
      if($ors['node']==2) {
	$rs=getDB()->select('select * from '.$this->idx.' where KOIDSRC=?',array($oid));
	if($rs && ($ors=$rs->fetch())) $_REQUEST['oid']=$oid=$ors['KOIDDST'];
      }
    }
    $r['here']=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    // Si l'ordre de tri a changé, on le sauvegarde
    if(!empty($fileorder)) {
      $r['here']->setOption('fileorder',str_replace('default','', $fileorder));
      $r['here']->saveOptions();
    }
    if(!empty($directoryorder)) {
      $r['here']->setOption('directoryorder',str_replace('default', '', $directoryorder));
      $r['here']->saveOptions();
    }
    // Recupere les details du document
    $r['here']->getContent(true);
    $r['here']->getDocumentsDetails();
    $r['here']->getDirectoriesDetails($maxlevel-1);
    $r['here']->getActions();
    $path=$this->mkContext($oid);
    if($showfiles==2) {
      foreach($r['here']->directories as &$dir) {
	$dir->getDocumentsDetails();
      }
    }
    $r['path']=$path;
    $r['oidcurrent']=$r['here']->oid;

    // Liste des types qui peuvent etre ajouté dans le dossier en court
    if(count($path) && ($this->getPatternsFolderOid()==$path[0]->oid)) $r['here']->getNewTypesM(true,true);
    else $r['here']->getNewTypes(true, true);

    // Liste des tris possible sur les dossiers
    $sorting=@$r['here']->getOption('dirsorting');
    if(!empty($sorting)){
      $sortinglist=array();
      $list=explode(';',$sorting);
      foreach($list as $i=>$f){
	$param=explode(',',$f);
	if(count($param)==2) $sortinglist[]=array('name'=>$param[0],'order'=>$param[1]);
      }
      $r['dirsorting']=$sortinglist;
    }
    // Liste des tris possible sur les documents
    $sorting=@$r['here']->getOption('sorting');
    if(!empty($sorting)){
      $sortinglist=array();
      $list=explode(';',$sorting);
      foreach($list as $i=>$f){
	$param=explode(',',$f);
	if(count($param)==2) $sortinglist[]=array('name'=>$param[0],'order'=>$param[1]);
      }
      $r['sorting']=$sortinglist;
    }
    if($this->interactive && $this->trackaccess) \Seolan\Core\Logs::uniqueUpdate('access',$oid);
    return \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }

  /**
   * visualisation arborescente partielle
   * = index2 mais sur le premier niveau et les dossier du path en cours seulement
   * parametres : 
   * - nosess : pas de mémorisation du mode. Utilisé par les fenêtres déplacement par exemple
   */
  public function index2Light($ar){
    $p=new \Seolan\Core\Param($ar,array('up'=>1,'down'=>1,'oid'=>'','maxlevel'=>2, 'showfiles'=>2, 'nosess'=>0,'clear'=>0));
    $nosess=$p->get('nosess');
    if(empty($nosess)) {
      $this->setPref1('docmode','index2');
      $this->_setSession('docmode','index2');
    }
    $oid=$p->get('oid');
    if(empty($oid)){
      $oid=$this->rootOid();
      $_REQUEST['oid'] = $oid;   // templates au moins index2 utilisent ceci
    }
    $tplentry=$p->get('tplentry');
    $maxlevel=$p->get('maxlevel');
    $actiontotest=$p->get('action');
    if(!in_array($actiontotest, array('linkTo','display'))) $actiontotest='index';

    $showfiles=$p->get('showfiles');
    if($showfiles==1) $docstolist=2;
    else $docstolist=1;

    // Les fils directs de root
    $rets = $this->subObjects4($this->rootOid(), 1, false, 1, $docstolist);
    // Creation du contexte
    $path=$this->mkContext($oid);

    $oidcurrent=NULL;
    // Construction des noeuds du chemin
    foreach($path[0] as $i=>&$o2) {
      $oidcurrent=$o2->oid;
      if(($oidcurrent != $this->rootOid()) && !in_array($oidcurrent, $rets['1'])){
	$prets = $this->subObjects4($path[0][$i-1]->oid, 1, false, $i, 1);
	$dx=array_search($path[0][$i-1]->oid, $rets['1']);
	$rets['1'] = array_merge(array_slice($rets['1'], 0, $dx+1), $prets['1'], array_slice($rets['1'], $dx+1));
	$rets['ors']=array_merge($rets['ors'],$prets['ors']);
      }
    }
    // Recupere les dossiers
    foreach($rets['1'] as $i=>&$myoid) {
      $ors=&$rets['ors'][$myoid];
      if(isset($ors)) {
	$rets['docs'][$i]=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($myoid, $this);
	$rets['docs'][$i]->getContent();
	// nolinkto est utilisé pour cacher certains dossier pour la fonction de copie, lien...
	// il n'est donc pas necessaire de la calculer si on doit retourner aussi les docs car on ne sera pas dans ce cas
	if($docstolist==1) $rets['ors'][$myoid]['noLinkTo']=($this->secure($myoid, 'edit')?0:1);
      }
    }
    // Recupere les documents
    if($docstolist==2){
      foreach($rets['2'] as $j=>&$myoid) {
	$ors=&$rets['ors'][$myoid];
	if(isset($ors)) {
	  $rets['docs'][$i+1+$j]=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($myoid, $this);
	  $rets['docs'][$i+1+$j]->getContent();
	}
      }
    }
    $order=array();
    $prev=0;
    $prevlabels=array();
    foreach($rets['1'] as $i=>$oid) {
      $o1=&$rets['ors'][$oid];
      $cur=$o1['level'];
      $prevlabels[$cur]=$rets['docs'][$i]->title;
      if($cur<$prev) {
	foreach($prevlabels as $j=>$v) {
	  if($j>$cur) unset($prevlabels[$j]);
	}
      }
      $order[$i]=$prevlabels;
      $prev=$o1['level'];
    }
    $orderstring=array();
    foreach($order as $i=>&$o) {
      // en cas d'égalite il faut un discriminant pour que multisort n'aille pas dans le tableau des docs ?
      // to lower ?
      $orderstring[$i]=strtolower(implode('>',$o)).$i;
    }
    array_multisort($orderstring, SORT_ASC, SORT_LOCALE_STRING, $rets['docs']);
    $sors=$rets['ors'];
    $rets['ors']=array();
    foreach($rets['docs'] as $i=>&$o) {
      $rets['ors'][$i]=$sors[$o->oid];
    }
    unset($sors);
    $orderstring=array_values($orderstring);
    $oidcurrent=NULL;
    foreach($path[0] as $i=>&$ob) {
      $oidcurrent=$ob->oid;
      foreach($rets['docs'] as &$doc) {
	if($ob->oid==$doc->oid) {
	  $foo = 'current find';
	  $doc->current=true;
	}
      }
    }
    $rets['docs']=array_values($rets['docs']);
    $rets['ors']=array_values($rets['ors']);
    $rets['path']=&$path;
    $rets['oidcurrent']=$oidcurrent;
    if($this->interactive && $this->trackaccess) \Seolan\Core\Logs::uniqueUpdate('access',$oid);
    return \Seolan\Core\Shell::toScreen1($tplentry, $rets);
  }

  /**
   * visualisation arborescente des repertoires
   * parametres :
   * - nosess : pas de memorisation du mode. Par exemple vue arbo en fenêtre
   * - clear : changement de mode. Appel depuis les icones de menu
   */
  function index2($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('up'=>1,'down'=>1,'oid'=>'','maxlevel'=>2, 'showfiles'=>2, 'nosess'=>0,'clear'=>0));
    $tpl=$p->get('tplentry');
    $oid = $p->get('oid');
    $nosess=$p->get('nosess');
    if($this->saveUserPref 
       && $GLOBALS['XSHELL']::_function() == 'index2'
       && $tplentry != 'TZR_RETURN_DATA'){
      if($p->get('clear')==1) {
	$this->_clearSession('docmode');
      }  elseif ($this->_getSession('docmode')=='index') {
	if ($p->is_set('template')){ 
	  $GLOBALS['XSHELL']->changeTemplate('Module/DocumentManagement.index2.html');
	}
	return $this->index(['oid'=>$oid]);
      }
    }
    // Affichage en arbo, on change de fonction
    if($tpl!=TZR_RETURN_DATA && \Seolan\Core\Shell::getTemplate()=='Module/DocumentManagement.index3.html'){
      return $this->index2Light($ar);
    }
    if(empty($nosess)) {
      $this->setPref1('docmode','index2');
      $this->_setSession('docmode','index2');
    }
    $oid=$p->get('oid');
    if(empty($oid)){
      $oid=$this->rootOid();
      $_REQUEST['oid']=$oid;
    }

    $tplentry=$p->get('tplentry');
    $maxlevel=$p->get('maxlevel');
    $actiontotest=$p->get('action');
    $move=$p->get('_move');
    $_selected=$p->get('_selected');
    $except=!empty($move) && empty($_selected) ? array($oid):array();
    if(!in_array($actiontotest, array('linkTo','display'))) $actiontotest='display';

    $showfiles=$p->get('showfiles');
    if($showfiles==1)
      $docstolist=array(1,2);
    else
      $docstolist=array(1);

    $rets=$this->subObjects2($this->rootOid(),$docstolist,100,true,$actiontotest,$except);
    $todel_level=NULL;

    $cnt=count($rets['ors']);
    $lvlprev=0;
    foreach($rets['ors'] as $i=>&$o){
      $rets['docs'][$i]=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($o['KOIDSRC'], $this);
      $rets['docs'][$i]->getContent();
    }
    $order=array();
    $prev=0;
    $prevlabels=array();
    foreach($rets['ors'] as $i=>&$o) {
      $cur=$o['level'];
      $prevlabels[$cur]=$rets['docs'][$i]->title;
      if($cur<$prev) {
	foreach($prevlabels as $j=>$v) {
	  if($j>$cur) unset($prevlabels[$j]);
	}
      }
      $order[$i]=$prevlabels;
      $prev=$o['level'];
    }
    $orderstring=array();
    foreach($order as $i=>&$o) {
      $orderstring[$i]=implode('>',$o).$i;
      // en cas d'egalite il faut un discriminant pour que multisort n'aille pas dans le tableau des docs ?
    }
    $docs=&$rets['docs'];
    $ors=&$rets['ors'];
    array_multisort($orderstring, SORT_ASC, SORT_LOCALE_STRING, $docs, $ors);

    $path=$this->mkContext($oid);

    $oidcurrent=NULL;
    foreach($path[0] as $i=>&$ob) {
      $oidcurrent=$ob->oid;
      foreach($rets['docs'] as $j=>&$doc) {
	if($ob->oid==$doc->oid) {
	  $doc->current=true;
	}
      }
    }
    $rets['path']=&$path;
    $rets['oidcurrent']=$oidcurrent;
    if($this->interactive && $this->trackaccess) \Seolan\Core\Logs::uniqueUpdate('access',$oid);
    return \Seolan\Core\Shell::toScreen1($tplentry, $rets);
  }

  /// Retourne la liste des dossiers fils d'un dossier
  function &viewDirFolders($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('up'=>1,'down'=>1,'oid'=>'','maxlevel'=>2, 'showfiles'=>1));
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    $doc->getContent();
    $fileorder=$p->get('fileorder');
    if(!empty($fileorder)) $doc->setOption('fileorder',$fileorder);
    $doc->getDirectoriesDetails(1);
    $r['doc']=$doc;
    return \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }

  /// Retourne la liste des dossiers/fichiers fils d'un dossier au format JSON allégé
  function ajaxLoadDirTree(){
    $p=new \Seolan\Core\Param($ar, array('showfiles'=>0));
    $ar['tplentry']=TZR_RETURN_DATA;
    $r=$this->viewDirFolders($ar);
    $showfiles=$p->get('showfiles');
    // l'objet en reponse JSON
    $ret=(object)array('doc'=>(object)array('oid'=>$p->get('oid')),
		       'directories'=>array(),'documents'=>array());
    foreach($r['doc']->directories as $something => &$dir){
      if(!$showfiles) $noLinkTo=($this->secure($dir->oid, 'edit')?0:1);
      else $noLinkTo=0;
      $ret->directories[]=(object)array('oid'=>$dir->oid,'title'=>htmlspecialchars($dir->title),'countdirs'=>$dir->countdirs,'countdocs'=>$dir->countdocs,
					'smalliconurl'=>$dir->smalliconurl,'noLinkTo'=>$noLinkTo);
    }
    if($showfiles){
      $r=$this->viewDir($ar);
      foreach($r['doc']->documents as $something => &$dir){
	$ret->documents[]=(object)array('oid'=>$dir->oid,'title'=>htmlspecialchars($dir->title),
					'smalliconurl'=>$dir->smalliconurl,'noLinkTo'=>0);
      }
    }
    header('Content-Type: application/json');
    die(json_encode((object)$ret));
  }

  /// Retourne le contenu d'un repertoire
  function viewDir($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('up'=>1,'down'=>1,'oid'=>'','maxlevel'=>2, 'showfiles'=>1));
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    $doc->getContent();
    $doc->getActions();
    $fileorder=$p->get('fileorder');
    if(!empty($fileorder)) $doc->setOption('fileorder',$fileorder);
    $doc->getDocumentsDetails();
    $r['doc']=$doc;
    // dossier modele ou descendant
    $path=$this->mkContext($oid);
    if ($this->getPatternsFolderOid() == $path[0]->oid) {
      $r['doc']->getNewTypesM(true, true);
    } else {
      $r['doc']->getNewTypes(true, true);
    }
    return \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }

  /// Retourne le contenu d'un repertoire au format JSON allégé
  function ajaxLoadDir(){
    $p=new \Seolan\Core\Param([], array());
    $oid=$p->get('oid');
    $r=$this->viewDir(array());
    // Simplifier la reponse
    $paths=$mpaths=array();
    $parent='';
    $dpaths=$this->mkContext($oid);
    $nbpaths=count($dpaths);
    $self=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true,false);
    // Créé le fil d'arianne
    foreach($dpaths as $foo=>$path){
      foreach($path as $foo2=>$node){
	if($foo2>0) $parentoid='&_parentoid='.$path[$foo2-1]->oid;
	else $parentoid='';
	$link='<a class="cv8-ajaxlink" href="'.$self.'&oid='.$node->oid.$parentoid.'&moid='.$this->_moid.'&function=index2&'.
	  'template=Module/DocumentManagement.index3.html&tplentry=br">'.$node->title.'</a>';
	if($foo2>0 && $foo==0){
	  $paths[]=$link;
	  $parent=$path[$foo2-1]->oid;
	}
	if($nbpaths>1) $mpaths[$foo][]=$link;
      }
    }
    // Prepare la liste
    $sorting=@$r['doc']->getDocumentTypeOption(['sorting']);
    $sortinglist=array();
    if(!empty($sorting)){
      $list=explode(';',$sorting);
      foreach($list as $i=>$f){
	$param=explode(',',$f);
	if(count($param)==2) $sortinglist[]=array('name'=>$param[0],'order'=>$param[1]);
      }
    }
    $ret=(object)array('tpl'=>(object)array('title'=>$r['doc']->tpl['otitle']->raw),
		       'doc'=>(object)array('title'=>html_entity_decode($r['doc']->title),'parentoid'=>$parent,
					    'comment'=>html_entity_decode($r['doc']->comment),'oid'=>$p->get('oid'),'paths'=>$paths,
					    'mpaths'=>$mpaths,'newdirs'=>array(),'newdocs'=>array(),'documents'=>array(),
					    'sorting'=>$sortinglist,'actions'=>','.implode(',',$r['doc']->actions).',')
		       );
    foreach($r['doc']->newdirs as $it=>&$tpl){
      $url=$self.'&moid='.$this->_moid.'&function=input&template=Module/DocumentManagement.input.html&doid='.$tpl['oid'].'&tplentry=br&_parentoid='.$oid.
	'&oid='.$oid;
      $ret->doc->newdirs[]=(object)array('title'=>$tpl['otitle']->raw,'url'=>$url);
    }
    foreach($r['doc']->newdocs as $it=>&$tpl){
      $url=$self.'&moid='.$this->_moid.'&function=input&template=Module/DocumentManagement.input.html&doid='.$tpl['oid'].'&tplentry=br&_parentoid='.$oid.
	'&oid='.$oid;
      $ret->doc->newdocs[]=(object)array('title'=>$tpl['otitle']->raw,'url'=>$url);
    }
    if(is_array($r['doc']->documents)) {
      foreach($r['doc']->documents as $it=>&$doc){
	$url1=$self.'&moid='.$this->_moid.'&function=edit&template=Module/DocumentManagement.edit.html&oid='.$doc->oid.'&tplentry=br&findex2=index2';
	$url2=$self.'&moid='.$this->_moid.'&function=display&template=Module/DocumentManagement.display.html&oid='.$doc->oid.'&tplentry=br&findex2=index2';
	$o=(object)array('oid'=>$doc->oid,'tpltitle'=>$doc->tpl['otitle']->raw,'title'=>html_entity_decode($doc->title),
			 'urledit'=>$url1,'urlview'=>$url2,
			 'countfiles'=>$doc->countfiles,
			 'docs'=>$doc->countfiles>0?$doc->docs:'',
			 'upd'=>$doc->fields['oUPD']->toText(),
			 'actions'=>','.implode(',',$doc->actions).',','lock'=>$doc->fields['_lock_user']
			 );
	if(isset($doc->fields['oOWN'])) $o->own=$doc->fields['oOWN']->toText();
	$ret->doc->documents[]=$o;
      }
    }
    header('Content-Type: application/json');
    die(json_encode((object)$ret));
  }

  // creation des informations sur le chemin d'acces
  //
  protected function &mkContext($doc) {
    $doco = \Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($doc,$this);
    if(empty($doco)) return null;
    $doco->getParents(10);
    $doco->getAllPaths();
    return $doco->paths;
  }
  protected function mkContext2(&$my, $doc) {
    $docmode=$this->_getSession('docmode');
    $doco = \Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($doc,$this);
    $doco->getParents(10);
    $doco->getAllPaths();
    foreach($doco->paths[0] as $i=>&$o) {
      if($i==0){
	$title=$this->getLabel(); // on met le titre du module en avant
	$parentoid = '';
      }else{
	$title=$o->title;
	$parentoid = '_parentoid='.$doco->paths[0][$i-1]->oid.'&';
      }
      if($docmode=='index2'){
	$o1=new \Seolan\Core\Module\Action($this, 'i'.$i, $title,'&moid='.$this->_moid.'&_function=index2&tplentry=br&'.$parentoid.
			      'template=Module/DocumentManagement.index3.html&tplentry=br&oid='.$o->oid);
      }else{
	$o1=new \Seolan\Core\Module\Action($this, 'i'.$i, $title,'&moid='.$this->_moid.'&_function=index&tplentry=br&'.$parentoid.
			      'template=Module/DocumentManagement.index2.html&tplentry=br&oid='.$o->oid);
      }
      $my['stack'][]=$o1;
    }
  }
  // creation d'un nouveau document
  //
  function input($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $doid=$p->get('doid');
    $poid=$p->get('_parentoid');
    $tplentry=$p->get('tplentry');

    // lecture du type de noeud nouveau
    $xt = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'_TYPES');
    $d1 = $xt->display(array('oid'=>$doid, 'tplentry'=>TZR_RETURN_DATA));
    \Seolan\Core\Shell::toScreen1('brt',$d1);
    $lines = explode("\n", stripslashes($d1['oopts']->toText()));
    $tploptions=array();
    foreach($lines as &$line) {
      @list($var,$val)=explode('=',$line);
      if(!empty($var) && !empty($val)) {
	$var=trim($var);$val=trim($val);
	$tploptions[$var]=$val;
      }
    }
    $d1['oopts']->decoded=$tploptions;

    $path = $this->mkContext($poid);
    $ar['path'] = &$path; // contexte basedoc pour le repository
    // traitement d'insertion
    $mod=\Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(NULL, $d1);
    if(!empty($tploptions['peremption'])) {
      $ts=time();
      $ts=$ts+$tploptions['peremption']*24*60*60;
      $ar['options']['PRP']['value']=DATE('Y-m-d',$ts);
    }
    $ar['fmoid']=$this->_moid;
    $br = $mod->XMCinput($ar);
    $br['repository'] = $mod;
    \Seolan\Core\Shell::toScreen1($tplentry, $br);
    \Seolan\Core\Shell::toScreen2($tplentry, 'path', $path);
  }

  function registerDoc($oid, $doctype, $own=true) {
    $q="SELECT COUNT(KOID) FROM ".$this->id." WHERE KOID=?";
    $rs=getDB()->select($q, array($oid));
    if($rs && $ors=$rs->fetch()) {
      if($ors['COUNT(KOID)']<=0) {
	if($own) $q='INSERT INTO '.$this->id.' SET KOID=?, DTYPE=?, ENDO=1, DPARAM=""';
	else $q='INSERT INTO '.$this->id.' SET KOID=?, DTYPE=?, ENDO=2, DPARAM=""';
	getDB()->execute($q, [$oid, $doctype]);
      }
    }
  }

  function newDoc($fields, $typeoid) {
    $xt = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'_TYPES');
    $d1 = $xt->display(array('oid'=>$typeoid, 'tplentry'=>TZR_RETURN_DATA));

    $mod = \Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(NULL,$d1);
    $fields['_options']=array('local'=>1);
    $fields['tplentry']=TZR_RETURN_DATA;
    $r1=$mod->XMCprocInput($fields);
    $oidres=$r1['oid'];
    $this->registerDoc($oidres, $typeoid);
    return $oidres;
  }
  // validation de la creation
  //
  function procInput($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $doid=$p->get('doid');
    $auth=$this->getTypes($doid);
    $tplentry = $p->get('tplentry');
    $noworkflow=$p->get('_noworkflow');
    if(empty($auth)){
      \Seolan\Core\Logs::critical('security',"access type denied |".get_class($this)."|procInput|".$this->_moid."|TYPE ".$doid.
		      "| user ".\Seolan\Core\User::get_current_user_uid());
      \Seolan\Core\Shell::redirect2auth();
    }

    // recherche du parent en dessous duquel on supprime le nouveau noeud
    $parentoid=$p->get('_parentoid');
    if(empty($parentoid)) $parentoid=$this->rootOid();

    // recherche du modèle de document
    $xt = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_TYPES');
    $d1 = $xt->display(array('oid'=>$doid, 'tplentry'=>TZR_RETURN_DATA));

    $ar['tplentry']=TZR_RETURN_DATA;

    // creation des fils par clone du modele
    if (!empty($d1['opattern']->raw)){
      \Seolan\Core\Logs::notice(get_class($this), "Pattern");
      $oidres=$this->copyTo($d1['opattern']->raw, $parentoid);
      // une fois qu'on a cloné, on prend les infos du dossier saisies dans la demande de création
      $mod=\Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(NULL,$d1);
      $ar['oid']=$oidres;
      $r1=$mod->XMCprocEdit($ar);
      return $oidres;
    } else  {
      // sauvegarde du document
      $mod=\Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(NULL,$d1);
      $r1=$mod->XMCprocInput($ar);
      $oidres=$r1['oid'];

      // rattachement au dossier parent
      $docparent = \Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($parentoid, $this);
      $docparent->addChild($oidres);
    }
    
    // enregistrement du document en base documentaire dans l'index
    $this->registerDoc($oidres, $doid);
    
    // déclenchement des éventuels workflow auto
    if(\Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID) && empty($noworkflow)) {
      $umod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
      $umod->checkAndRun($this, $mod, $oidres, 'new');
    }

    // send a copy to
    if (\Seolan\Core\Shell::admini_mode() 
	&& TZR_RETURN_DATA != $tplentry){
      $send = $p->get('_sendacopyto');
      if (!empty($send[$this->_moid]))
	$this->redirectToSendACopyTo($oidres);
    }

    return ['oid'=>$oidres];
    
  }

  /// Fonction de controle du formulaire d'insertion via ajax
  function ajaxProcInputCtrl(&$ar){
    $p=new \Seolan\Core\Param($ar,array());
    $doid=$p->get('doid');
    $repo=$this->getRepositoryByType($doid);
    if(method_exists($repo,'ajaxProcInsertCtrl')) $repo->ajaxProcInsertCtrl($ar);
    else returnJson(array('status'=>'success'));
  }

  /// Edition d'un document
  function edit($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    \Seolan\Core\Shell::toScreen1('brt',$doc->tpl);
    $ar['fmoid']=$this->_moid;
    if($this->interactive && $this->trackaccess) $ar['accesslog']=1;
    $d=$doc->repository->XMCedit($ar);  
    $d['path']=$this->mkContext($oid);
    $d['here']=$doc;
    if($doc->tpl['oshared']->raw==1) {
      $m1=\Seolan\Core\Module\Module::modlist(array('basic'=>true,'toid'=>XMODDOCMGT_TOID,'tplentry'=>TZR_RETURN_DATA));
      // s'il y a un autre module autorise que le module courant
      if(count($m1['lines_oid'])>1)  {
	$d['modlist']=&$m1;
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$d);
  }

  /// Edition d'un document
  function editDup($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    \Seolan\Core\Shell::toScreen1('brt',$doc->tpl);
    $ar['fmoid']=$this->_moid;
    if($this->interactive && $this->trackaccess) $ar['accesslog']=1;
    $d=$doc->repository->XMCeditDup($ar);  
    $d['path']=$this->mkContext($oid);
    $d['here']=$doc;
    if($doc->tpl['oshared']->raw==1) {
      $m1=\Seolan\Core\Module\Module::modlist(array('basic'=>true,'toid'=>XMODDOCMGT_TOID,'tplentry'=>TZR_RETURN_DATA));
      // s'il y a un autre module autorise que le module courant
      if(count($m1['lines_oid'])>1)  {
	$d['modlist']=&$m1;
      }
    }
    \Seolan\Core\Shell::toScreen2('','_function', 'procEditDup');
    $d["_duplicate"]="1";
    return \Seolan\Core\Shell::toScreen1($tplentry,$d);
  }
  
  /// Prépare le partage d'un document entre plusieurs bases documentaires
  function share($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $ret=array();
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    if($doc->tpl['oshared']->raw==1) {
      $ret['modlist']=\Seolan\Core\Module\Module::modlist(array('basic'=>true,'toid'=>XMODDOCMGT_TOID,'tplentry'=>TZR_RETURN_DATA));
      foreach($ret['modlist']['lines_oid'] as $i=>$moid){
	$mod=\Seolan\Core\Module\Module::objectFactory($moid);
	$f=$mod->father1($oid);
	foreach($f as $foid){
	  if($mod->secure($foid,'input')) $dest[$moid][]=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($foid,$mod);
	}
      }
    }
    $ret['doc']=$doc;
    $ret['dest']=$dest;
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// Partage un document avec d'autres bases documentaires
  function procShare($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    $share=$p->get('_share');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    foreach($share as $moid=>$dests){
      $mod=\Seolan\Core\Module\Module::objectFactory($moid);
      if(is_object($mod)){
	foreach($dests as $old=>$dest){
	  if(empty($dest)){
	    if(\Seolan\Core\Kernel::isAKoid($old)){
	      // Suppression d'un partage
	      if(!$mod->secure($old,'del')) continue;
	      $mod->del(array('_options'=>array('local'=>true),'physical'=>0,'oid'=>$oid,'_parentoid'=>$old));
	    }
	  }else{
	    if(\Seolan\Core\Kernel::isAKoid($old) && ($old!=$dest)){
	      // Modification d'un partage
	      if(!$mod->secure($dest,'input') || !$mod->secure($old,'del')) continue;
	      $doc2=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($dest,$mod);
	      $doc2->addChild($oid);
	      $mod->del(array('_options'=>array('local'=>true),'physical'=>0,'oid'=>$oid,'_parentoid'=>$old));
	    }else{
	      // Insertion d'un partage
	      if(!$mod->secure($dest,'input')) continue;
	      $mod->registerDoc($oid,$doc->tpl['oid']);
	      $doc2=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($dest,$mod);
	      $doc2->addChild($oid);
	    }
	  }
	}
      }
    }
  }
  /// Ajoute un commentaire sur l'objet via son repository
  function insertComment($ar=null){
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    $re = $doc->repository->XMCinsertComment($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry,$res);
  }
  /// Lire les commentaires d'un objet via son repository
  function getComments($ar=null){
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    $comments = $doc->repository->XMCgetComments($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry,$comments);
  }
  /// Visualiser un objet de la corbeille
  function displayTrash($ar){
    $p = new \Seolan\Core\Param($ar, []);
    $dtypeoid = $p->get('_dtype');
    $dtype = $this->getTypes($dtypeoid);
    if (empty($dtype)){
      \Seolan\Core\Logs::critical('security',"access type denied |".get_class($this)."|displayTrash|".$this->_moid."|TYPE ".$dtypeoid.
				  "| user ".\Seolan\Core\User::get_current_user_uid());
      \Seolan\Core\Shell::redirect2auth();
    }
    $docrepo = \Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(null,$dtype);
    return $docrepo->XMCdisplay($ar, false); // pas de rdisplay, pour avoir accès à l'archive
  }
  /// Visualiser un document ou un repertoire
  function &display($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    \Seolan\Core\Shell::toScreen1('brt',$doc->tpl);
    $ar['fmoid']=$this->_moid;
    if($this->interactive && $this->trackaccess) $ar['accesslog']=1;
    $d=$doc->repository->XMCdisplay($ar,false);
    $d['path']=$this->mkContext($oid);
    $d['here']=$doc;
    return \Seolan\Core\Shell::toScreen1($tplentry,$d);
  }

  /// Validation de la creation
  function procEdit($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $share=$p->get('_share');
    $noworkflow=$p->get('_noworkflow');
    $tplentry = $p->get('tplentry');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    \Seolan\Core\Shell::toScreen1('brt',$doc->tpl);
    $update = $doc->repository->XMCprocEdit($ar);

    getDB()->execute('UPDATE '.$this->id.' SET UPD=NULL WHERE KOID=?',array($oid));
    if(\Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID) && empty($noworkflow)) {
      $umod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
      $umod->checkAndRun($this, $doc->repository, $oid, 'edit');
    }
    if (\Seolan\Core\Shell::admini_mode() 
	&& TZR_RETURN_DATA != $tplentry){
      $send = $p->get('_sendacopyto');
      if (!empty($send[$this->_moid]))
	$this->redirectToSendACopyTo($oid);
    }
    return $update;
  }

  /// Validation de la creation
  function procEditDup($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $share=$p->get('_share');
    $noworkflow=$p->get("_noworkflow");
    $tplentry = $p->get("tplentry");
    $parentoid=$p->get("_parentoid");
    $oidsrc=$p->get("oid");

    \Seolan\Library\SecurityCheck::assertIsKOID($parentoid, __METHOD__.": isKoid");
    \Seolan\Library\SecurityCheck::assertIsKOID($oidsrc, __METHOD__.": isKoid");

    $olddoc = \Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oidsrc, $this);
    $r = $olddoc->repository->XMCprocEditDup($ar);
    $newoid=$r['oid'];

    $this->addDocument($parentoid, $newoid, $olddoc->getDocumentType());

    if(\Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID) && empty($noworkflow)) {
      $umod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
      $umod->checkAndRun($this, $doc->repository, $oid, 'edit');
    }
    return $update;
  }

  /// Fonction de controle du formulaire d'insertion via ajax
  function ajaxProcEditCtrl(&$ar){
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid,$this);
    if(method_exists($doc->repository,'ajaxProcEditCtrl')) $doc->repository->ajaxProcEditCtrl($ar);
    else returnJson(array('status'=>'success'));
  }

  /// Fonction de controle du formulaire d'insertion via ajax
  function ajaxProcEditDupCtrl(&$ar){
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid,$this);
    if(method_exists($doc->repository,'ajaxProcEditCtrl')) $doc->repository->ajaxProcEditCtrl($ar);
    else returnJson(array('status'=>'success'));
  }

  /// Journal d'un document
  function journal($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    \Seolan\Core\Shell::toScreen1('brt',$doc->tpl);
    $journal=$doc->journal();
    $journal['_repository']['archive']=$doc->repository->archive;
    $journal['_repository']['trackchanges']=$doc->repository->trackchanges;
    return \Seolan\Core\Shell::toScreen1($tplentry,$journal);
  }
  /**
   * surcouche suppression dans le cas suppression compelte
   */
  function preFullDelete($ar){
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>'br']);
    $ar['physical'] = 1;
    $tplentry = $p->get('tplentry');
    $ar['tplentry'] = TZR_RETURN_DATA;
    $ret = $this->preDel($ar);
    $ret['_messageComplement'] = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','full_delete','text') .
				\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','i18ncolon','text') .
						     \Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','full_delete_explain','text');
    
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }
  /**
   * préparation des suppressions
   * -> selection, document lié
   * index2 : moid,template,targetoid,message,_function,_next,physical,_move,_copy,oid,parentoid,tplentry,_parentoid,_selectedok,marker
   * index3 : moid,template,message,_function,_next,oid,tplentry,physical,_move,_copy,targetoid,marker,fileorder,_selectedok,newdocs,newdirs
   * si element lié : suppression physique à confirmer
   */
  function preDel($ar=null){
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>'br','_selected'=>null]);
    $selected = $p->get('_selected');
    $oid = $p->get('oid');
    $parentoid = $p->get('_parentoid');
    $tplentry = $p->get('tplentry');
    $checkElement = function($oid){
      $linked = getDB()->fetchOne('select count(*) from '.$this->idx.' where KOIDSRC=?', [$oid]);
      return ($linked>1);
    };
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    $ret = ['ok'=>true,
	    'title'=>'',
	    'linked'=>false,
	    'messages'=>[],
	    'doc'=>$doc,
	    'parentoid'=>$parentoid];
    if (empty($selected)){
      $ret['title'] = $doc->title;
      $isadmin = $this->secure($oid, ':admin');
      $descendants = $this->subObjects1($doc->oid, 99, false);
      $descendantsOids = array_merge($descendants[1], $descendants[2]);
      if (count($descendantsOids)>0){
	if (!$isadmin){
	  $ret['ok'] = false;
	  $ret['messages'][] = \Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','deletednotempty','text');
	  return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
	} else {
	  $ret['messages'][] = \Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','nodenotempty','text');
	}
	// avoir les droits sur tous les descendants
	foreach($descendantsOids as $doid){
	  if (!$this->secure($doid, 'del')){
	    $ret['messages'] = [\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','deleteforbidden','text')];
	    $ret['ok'] = false;
	    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
	  }
	}
      }

      $ret['linked'] = $checkElement($oid);

      // cas suppression depuis edit/display de la fiche d'un noeud
      if ($ret['linked'] && empty($parentoid)){
	$ret['forcephysical'] = 1;
      } 

    } else { // suppression d'une sélection de documents
      $ret['title'] = sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','selected_elements'), 
			      $doc->title,
			      count($selected));
      // suppression multiples élements
      $oids = array_keys($selected);
      $nb = count($oid);
      for($i=0; $i<$nb && !$linked; $i++){
	$linked = $checkElement($oids[$i]);
      }
      if ($linked){
	$ret['linked'] = true;
      }
    }

    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);

  }
  /**
   * surcouche de la suppression complete
   * selon le repository du document, suppression simple ou suppression complete
   * récursif quand la demande porte sur un répertoire
   */
  function fullDelete($ar){
    $p = new \Seolan\Core\Param($ar, []);
    $ar['physical'] = true;
    $ar['_fullDelete'] = true;

    $ret = $this->del($ar);

    if ($p->get('tplentry') != TZR_ERTURN_DATA){
      // à voir retour sur le parent oid ? si jouable
      \Seolan\Core\Shell::setNext($this->getMainAction());
    }
  }
  // suppression
  // si physical on supprime partout y compris physiquement
  // sinon on supprime physiquement si c'est le dernier lien
  //
  // suppression std avec secu
  // si physical on supprime partout y compris physiquement
  // sinon on supprime physiquement si c'est le dernier lien
  //
  // si fullDelete transmis on doit le répercuter
  //
  function del($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('deldoc'=>true,'_fullDelete'=>false));
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $selected=$p->get('_selected');
    $physical=$p->get('physical');
    $delshare=$p->get('delshare');
    $deldoc=$p->get('deldoc');
    $parentoid=$p->get('_parentoid');
    $fullDelete=$p->get('_fullDelete', 'local');
    if ($fullDelete)
      $delDocFunction = $childDelFunction = $delShareFunction = 'fullDelete';
    else
      $delDocFunction = $childDelFunction = $delShareFunction = 'del';
    $deletedoids = [];
    if(empty($selected)) {
        $cnt = getDB()->count('select COUNT(KOIDDST) from '.$this->idx.' where KOIDSRC=?', [$oid]);
      if($cnt<=1) {
	$physical=1;
      } else {
	$q='delete from '.$this->idx.' where KOIDSRC=? AND KOIDDST=?';
	getDB()->execute($q,[$oid, $parentoid]);
      }
      if(!empty($physical) && $physical==1) {
	$doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
	// traiter les fils s'il s'agit de répertoire
	if ($doc->node == true){
	  
	  $isadmin = $this->secure($oid, ':admin');
	  
	  // dossier vide ou droits d'admin
	  $v = $this->subObjects1($doc->oid, 99, false);
	  $coids = array_merge($v[1], $v[2]);
	  if (count($coids)>0 && !$isadmin){ // on n'efface que les dossiers vides
	    if($tplentry!=TZR_RETURN_DATA) {
	      $message=\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','deletednotempty','text');
	      \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self()."&oid={$oid}&moid={$this->_moid}&class=".get_class($this).
			      "&tplentry=br&template=Module/DocumentManagement.index2.html&function=index&message=".urlencode($message));
	      \Seolan\Core\Shell::toScreen2('', 'message', $message);
	    }
	    return false;
	  }
	  
	  // avoir les droits sur tous les descendants
	  foreach($coids as $foo=>$coid){
	    if (!$this->secure($coid, 'del')){
	      if($tplentry!=TZR_RETURN_DATA) {
		$message=\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','deleteforbidden','text');
		\Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self()."&oid={$oid}&moid={$this->_moid}&class=".get_class($this).
				"&tplentry=br&template=Module/DocumentManagement.index2.html&function=index&message=".urlencode($message));
		\Seolan\Core\Shell::toScreen2('', 'message', $message);
	      }
	      return false; // stop ici
	    }
	  }
	  // on enleve les fils
	  $v = $this->subObjects1($doc->oid, 2, false);
	  $coids = array_merge($v[1], $v[2]);
	  if ($coids){
	    foreach($coids as $foo=>$coid){
	      $physical = 0;
	      // avec physical = 0, seul les noeuds n'ayant qu'un parent (donc ce noeud) seront enleves
	      // rem : on a, au dessus, verifie qu'on a les droits sur tous les descendants
	      $this->$childDelFunction(['oid'=>$coid, 
					'_options'=>['local'=>1],
					'physical'=>$physical, 
					'_parentoid'=>$oid,
					'_selected'=>[], 
					'tplentry'=>TZR_RETURN_DATA]);
	    }
	  }
	} // node == 1 (dossier)

	// On supprime la fiche du document
	if($deldoc){
	  // Si le document est de type commun, on parcourt toutes les bases doc pour savoir si le document doit etre supprimé ou non
	  if($doc->tpl['oshared']->raw==1) { 
	    $del=true;
	    $mods=\Seolan\Core\Module\Module::modlist(array('basic'=>true,'toid'=>XMODDOCMGT_TOID,'noauth'=>true));
	    foreach($mods['lines_oid'] as $moid) {
	      $mod=\Seolan\Core\Module\Module::objectFactory($moid);
	      $nb=getDB()->count('select count(*) from '.$mod->id.' where KOID=?',array($oid));
	      if($nb>0){
		if(!$delshare){
		  // On ne veut effacer que dans la base en cours et le document est présent dans une autre base documentaire, on ne l'efface pas
		  $del=false;
		  break;
		}else{
		  // On ne veut effacer partout et le document est présent dans une autre base documentaire, on tente de l'effacer
		  if($mod->secure($oid,$delShareFunction)){
		    // ?? à voir fullDelete
		    $ok=$mod->$delShareFunction(['_options'=>array('local'=>true),'tplentry'=>TZR_RETURN_DATA,'oid'=>$oid,'deldoc'=>false,'physical'=>1]);
		    if($del) $del=$ok;
		  }else{
		    $del=false;
		  }
		}
	      }
	    }
	    if($del)
	      $doc->$delDocFunction();
	  }else{
	    $doc->$delDocFunction();
	  }
	}
	// On supprime le noeud de la base doc
	getDB()->execute('delete from '.$this->idx.' where KOIDSRC=?', array($oid));
	getDB()->execute('delete from '.$this->id.' where KOID=?',array($oid));
	$this->delDocFromSearchEngine($oid);
	if ($tplentry == TZR_RETURN_DATA){
	  return true;
	}
      } // physical == 1
    } else {
      // selected (documents) transmis 
      // RZ un document est supprimable si le pere est en ecriture et si lui-meme est en ecriture
      $parentoid = $oid;
      if($this->secure($oid,':rw')) {
	foreach($selected as $koid=>$foo) {
	  if($this->secure($koid,':rw')) {
	    $this->del([
	      '_fullDelete'=>$fullDelete?true:false,
	      'oid'=>$koid,
	      'physical'=>$physical,
	      '_parentoid'=>$oid,
	      '_selected'=>[],
	      'tplentry'=>TZR_RETURN_DATA]);
	    $deletedoids[$koid] = 1;
	  } else {
	    $mess=\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','deleteforbidden','text');
	  }
	}
      } else {
	$mess=\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','deleteforbidden','text');
      }
    }
    
    if($tplentry!=TZR_RETURN_DATA && !\Seolan\Core\Shell::hasNext()){
      \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self()."&oid={$parentoid}&moid={$this->_moid}&tplentry=br&template=Module/DocumentManagement.index2.html&function=index&message=".urlencode($mess));
    } elseif(!\Seolan\Core\Shell::hasNext()) {
      return ['deletedoids'=>$deletedoids];
    }
    
  }
  /**
   * appel suppression de documents depuis la sélection
   * suppression de base puis mise à jour de la sélection en session
   */
  function delWithSelection($ar){
    $ar['tplentry'] = TZR_RETURN_DATA;
    $res = $this->del($ar);
    if (count($res['deletedoids']))
      $modu=\Seolan\Core\Module\Module::objectFactory(self::getMoid(XMODUSER2_TOID))->delToSelection($this->_moid,$res['deletedoids']);
    return true;
  }
  
  function &father($oid) {
    if(!isset($this->_f[$oid])) {
      $set=getDB()->fetchAll('SELECT * FROM '.$this->idx.','.$this->id.',_TYPES WHERE KOIDSRC=? and KOIDDST='.$this->id.'.KOID and '.
			     $this->id.'.DTYPE=_TYPES.KOID', array($oid));
      $this->_f[$oid]=array();
      foreach($set as &$f1) $this->_f[$oid][$f1['KOIDDST']]=$f1;
      unset($set);
    }
    return $this->_f[$oid];
  }
  function &father1($oid) {
    if(!isset($this->_f1[$oid])) {
      $set=getDB()->fetchCol('SELECT KOIDDST FROM '.$this->idx.' WHERE KOIDSRC=?', array($oid));
      $this->_f1[$oid]=array();
      foreach($set as $f1) $this->_f1[$oid][]=$f1;
      unset($set);
    }
    return $this->_f1[$oid];
  }
  protected function getPath($oid) {
    // recherche des parents
    $next=$oid;
    $path=array();
    $i=0;
    while(!empty($next)) {
      $path[$i++]=$next;
      $rs=getDB()->select('select * from '.$this->idx.' where KOIDSRC=?',array($next));
      $next='';
      if($rs) {
	$ors=$rs->fetch();
	$next=$ors['KOIDDST'];
      }
    }

    $path=array_reverse($path);
    foreach($path as $i=>$koid) {
      $path[$i]=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($koid, $this);
    }
    return $path;
  }

  // id du dossier qui contient les modele
  //
  protected function getPatternsFolderOid(){
    return $this->defaultFolderTable.':'.$this->patternSuffix;
  }

  // liste des modeles definis pour cette base documentaire
  // -> oid du dossier
  // -> description
  //
  public function getPatterns() {
    $patternOid = $this->getPatternsFolderOid();
    // verif dossier modele existe
    $cnt=getDB()->select('select COUNT(*) from '.$this->id.' where KOID=?', array($patternOid));
    if($cnt<=0) return array();
    // lecture du rep des modeles
    $pf = $this->index(array('tplentry'=>TZR_RETURN_DATA,
			     'oid'=>$patternOid,
			     'maxlevel'=>2,
			     'up'=>1,
			     'down'=>1
			     )
		       );
    // menu contient les dossiers fils
    if (count($pf['menu']) == 0)
      return array();
    $docs = &$pf['docs'];
    $r = array();
    foreach($pf['menu'] as $foo=>&$menu){
      $r[] = &$docs[$menu[0]];
    }
    return $r;
  }

  /// Recupere les infos d'un objet par l'affichage du résultat d'une recherche
  public function getSearchResult($oid,$filter=NULL){
    $d1=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid,$this);
    $type=@$filter['type'];
    if($d1 !=null && (empty($type) || in_array($d1->tpl['oid'],$type))) {
      $d1->getParents(2);
      return $d1;
    }else return false;
  }

  /// presentation d'un resultat de recherche dans le module
  public function showSearchResult($oids) {
    $_REQUEST = array(
      'function' => 'advsearch',
      'template' => 'Module/DocumentManagement.advsearch2.html',
      'moid' => $this->_moid,
      'tplentry' => 'br',
      'clearrequest' => 1,
      'oids' => $oids
    );
    $GLOBALS['XSHELL']->run();
    exit;
  }
  /// Recherche avancée
  function advsearch($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $oids=$p->get('oids');
    $doctype=$p->get('type');
    $dosearch=$p->get('dosearch');
    $structsearch=$p->get("structsearch");
    
    // Recherche des types de documents
    $types=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_TYPES');
    $doctypes=$types->browse(array('order'=>'title','pagesize'=>100, 'where'=>"modid = '".$this->_moid."'", '_local'=>true));

    // Si un type est selectionné, on prepare le formulaire de recherche et on effectue la recherche si necessaire
    if(!empty($doctype)) {
      $disp=$types->display(array('tplentry'=>TZR_RETURN_DATA,'oid'=>$doctype));
      $mod=\Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(NULL,$disp);
      if(!empty($structsearch)) $mod->XMCquery($ar);
      if(!empty($dosearch)) {
	if(empty($structsearch)) {
	  $r1=$this->search(array('tplentry'=>TZR_RETURN_DATA,'type'=>$doctype));
	  \Seolan\Core\Shell::toScreen2('br','docs',$r1['modules'][$this->_moid]['lines_obj']);
	} else {
	  $ar['tplentry']=TZR_RETURN_DATA;
 	  $ar['pagesize']=9999;
	  $r1=$mod->XMCprocQuery($ar);
	}
      }
    }elseif(!empty($oids)){
      if(!$oids[0]) $r1['lines_oid']=array_keys($oids);
      else $r1['lines_oid']=$oids;
    }
    if(!empty($r1['lines_oid'])){
      $idx=0;
      foreach($r1['lines_oid'] as $oid) {
	$d1=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid,$this);
	if(is_object($d1) && ($d1->tpl['oid']==$doctype || empty($doctype)) && $this->secure($d1->oid, 'display')) {
	  $docs[$idx]=$d1;
	  $docs[$idx]->getParents(2);
	  $sort[$idx]=$d1->parents[0]->parents[0]->short.$d1->parents[0]->short.$oid;
	  $idx++;
	}
      }
      array_multisort($sort, $docs);
      \Seolan\Core\Shell::toScreen2('br','docs',$docs);
    }
    \Seolan\Core\Shell::toScreen1('doctypes',$doctypes);
  }

  /// Recherche libre
  function search($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $type=$p->get('type');
    if(!is_array($type)){
      if(!empty($type)) $type=array($type);
      else $type=array();
    }
    $tplentry=$p->get('tplentry');
    $ar['moidfilter']=$this->_moid;
    $ar['advfilter']['type']=$type;
    $searchEngine=\Seolan\Library\SolR\Search::objectFactory();
    return $searchEngine->globalSearch($ar);
  }

  /// Indexation du module dans le moteur de recherche
  public function _buildSearchIndex($searchEngine,$checkbefore=true,$limit=NULL,$cond=NULL){
    $done=0;
    $params=[];
    if(!empty($cond)){
      if($cond=='UPD'){
	$last=\Seolan\Core\DbIni::get('lastindexation_'.$this->_moid,'val');
	$current=date('Y-m-d H:i:s');
	if(empty($last)) $last=date('2000-01-01 00:00:00');
	$cond=$this->id.".UPD>=? ";
	$params[]=$last;
      }
      $cond=" AND ($cond)";
    }else{
      $cond=''; 
    }
    $rs=getDB()->select("SELECT *,{$this->id}.UPD as LASTINDEXATION FROM {$this->id} ".
                        "left outer join {$this->idx} on {$this->id}.KOID=KOIDSRC ".
                        "left outer join  _TYPES on {$this->id}.DTYPE=_TYPES.KOID ".
                        "where KOIDSRC is not null $cond order by {$this->id}.UPD ASC", 
			$params);
    \Seolan\Core\Logs::notice(__METHOD__,$rs->rowcount().' documents to re index '.$cond);
    while($rs && ($ors=$rs->fetch())) {
      \Seolan\Core\Logs::debug('\Seolan\Module\DocumentManagement\DocumentManagement::buildSearchIndex: testing '.$ors['KOIDSRC']);
      if($checkbefore && $searchEngine->docExists($ors['KOIDSRC'],$this->_moid,NULL)) continue;
      \Seolan\Core\Logs::debug('\Seolan\Module\DocumentManagement\DocumentManagement::buildSearchIndex: adding '.$ors['KOIDSRC']);
      $mydoc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($ors['KOIDSRC'],$this,$ors);
      if ($mydoc != ''){
	$mydoc->addToSearchEngine($searchEngine);
      } else {
	\Seolan\Core\Logs::critical(__METHOD__, ' unable to load document : '.$ors['KOIDSRC']);
	continue;
      }
      $done++;
      if($done%100==0){
 	\Seolan\Core\Logs::debug('\Seolan\Module\DocumentManagement\DocumentManagement::buildSearchIndex: commit');
        $searchEngine->index->commit();
	if(!empty($current)) \Seolan\Core\DbIni::set('lastindexation_'.$this->_moid,$ors['LASTINDEXATION']);
      }
      if($limit && $done>$limit){
 	\Seolan\Core\Logs::debug('\Seolan\Module\DocumentManagement\DocumentManagement::buildSearchIndex: break at '.$done);
	break;
      }
    }
    $searchEngine->index->commit();
    if(!empty($current)) \Seolan\Core\DbIni::set('lastindexation_'.$this->_moid,$current);
    return true;
  }

  /// Daemon qui lance en particulier l'indexation des champs
  protected function _daemon($at='any') {
    if($at=="daily") {
      // Verifie que les liens de la base doc pointent toujours sur un objet
      $rs=getDB()->select('SELECT * FROM '.$this->id);
      while($rs && ($ors=$rs->fetch())) {
	$table=\Seolan\Core\Kernel::getTable($ors['KOID']);
	$cnt=getDB()->count('SELECT COUNT(*) FROM '.$table.' WHERE KOID=?',array($ors['KOID']));
	if($cnt<=0) {
	  getDB()->execute('DELETE FROM '.$this->id.' WHERE KOID=?',array($ors['KOID']));
	  getDB()->execute('DELETE FROM '.$this->idx.' WHERE KOIDSRC=?',array($ors['KOID']));
	  getDB()->execute('UPDATE '.$this->idx.' SET KOIDSRC="" WHERE KOIDSRC=?',array($ors['KOID']));
	}
      }
      // Supression de toutes les aretes dont les departs sont inexistants
      $rs=getDB()->select('SELECT KOIDSRC,KOID  FROM '.$this->idx.' LEFT OUTER JOIN '.$this->id.' ON KOID=KOIDSRC WHERE ISNULL(KOID);');
      while($rs && ($ors=$rs->fetch())) getDB()->execute('DELETE FROM '.$this->idx.' WHERE KOIDSRC=?',array($ors['KOIDSRC']));
    }
  }

  /// Supprime un document de l'index
  function delDocFromSearchEngine($oid) {
    $se=\Seolan\Library\SolR\Search::objectFactory();
    $se->deleteItem($oid,$this->_moid);
  }

  function goto1($ar=NULL) {
    $p = new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');


    $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,true);
    $moid=$this->_moid;
    $rs=getDB()->select('select * from '.$this->idx.','.$this->id.',_TYPES where KOIDSRC=? and '.
		     'KOIDSRC='.$this->id.'.KOID and '.$this->id.'.DTYPE=_TYPES.KOID',array($oid));
    if($ors=$rs->fetch()) {
      if($ors['node']==1) {
	$right= $this->secure($oid, 'index');
	if(!$right) \Seolan\Library\Security::warning('\Seolan\Module\DocumentManagement\DocumentManagement (1)::goto1: could not access to objet '.$oid.' in module '.$moid);
	header("Location: {$url}&moid=$moid&template=Module/DocumentManagement.index2.html&oid=$oid&function=index&tplentry=br&skip=1");
      } else {
	$right= $this->secure($oid, 'display');
	if(!$right) \Seolan\Library\Security::warning('\Seolan\Module\DocumentManagement\DocumentManagement (2)::goto1: could not access to objet '.$oid.' in module '.$moid);
	header("Location: {$url}&moid=$moid&function=display&template=Module/DocumentManagement.display.html&oid=$oid&tplentry=br&skip=1");
      }
    }
  }

  // rend la liste des objets qui se trouvent en dessous de cet objet,
  // independamment des niveaux
  //
  public function &subObjects($oid) {
    $subs=$this->subObjects1($oid);
    $all=array_merge($subs[1], $subs[2]);
    return $all;
  }

  /// changement du root d'un user
  public function setRootOid($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $oid=$p->get('oid');
    $uid=\Seolan\Core\User::get_current_user_uid();
    \Seolan\Library\Opts::setSubOpt($uid, $this->_moid, 'opts', 'home', $oid);
  }

  /// effacement du root d'un user
  public function clearRootOid($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $uid=\Seolan\Core\User::get_current_user_uid();
    \Seolan\Library\Opts::unsetSubOpt($uid, $this->_moid, 'opts', 'home');
  }

  public function rootOid() {
    if(!empty($this->opts['home'])) return $this->opts['home'];
    return $this->defaultFolderTable.':root-'.$this->_moid;
  }
  // recherche de tous les oids fils d'un objet y compris lui-meme
  // si types=array(1) on ne rend que les repertoires
  // si types=array(1,2) on rend tous les objets
  // si types=array(2) on rend les documents seulements
  //
  public function &subObjects1($oid, $levels=99, $myself=true) {
    if(empty($oid)) $oid=$this->rootOid();
    $open=array($oid);
    $node=array(1);
    $level=array(1);
    $oids=array('1'=>array(),'2'=>array());
    $ooid=$oid;
    $done=array();   /* liste des noeuds examines pour ne pas repasser dessus */

    while(!empty($open)) {
      $oid=array_pop($open);
      $n=array_pop($node);
      $l=array_pop($level);
      $done[]=$oid;
      if(!empty($oid)) {
	if(!$this->secure($oid, 'index'))  continue;
	$oids[$n][]=$oid;
      }
      if(($n==1) && ($l<$levels)) {
	if(!isset($this->adjacence[$oid]))
	  $this->adjacence[$oid]=getDB()->fetchAll('SELECT DISTINCT KOIDSRC,node,DTYPE FROM '.$this->idx.','.$this->id.
					      ',_TYPES WHERE KOIDDST=? '.
					      ' AND '.$this->id.'.KOID=KOIDSRC AND '.$this->id.'.DTYPE=_TYPES.KOID', array($oid));
	foreach($this->adjacence[$oid] as $oid=>&$ors) {
	  $oid=$ors['KOIDSRC'];
	  if(!in_array($oid,$done) && !in_array($oid,$open)) {
	    $open[]=$oid;
	    $node[]=$ors['node'];
	    $level[]=$l+1;
	  }
	}
      }
    }
    if(!$myself) {		/* on vire l'oid d'origine s'il n'est pas demande */
      $dx=array_search($ooid,$oids['1']);
      if(!($dx===FALSE)) {
	unset($oids['1'][$dx]);
      }
      $dx=array_search($ooid,$oids['2']);
      if(!($dx===FALSE)) {
	unset($oids['2'][$dx]);
      }
    }
    return $oids;
  }

  // recherche de tous les oids fils direct d'un objet (types: 1=>seulement dossier, autre=>tous)
  // $myself inutile
  public function &subObjects3($oid, $levels=99, $myself=true,$level=1,$types=0) {
    if(empty($oid)) $oid=$this->rootOid();
    if(isset($this->_s[$oid])) return $this->_s[$oid];
    \Seolan\Core\Logs::debug("\Seolan\Module\DocumentManagement\DocumentManagement::subObjects3: $oid");
    $open=array($oid);
    $node=array(1);
    $oids=array('1'=>array(),'2'=>array());
    if($levels==0) return $oids;
    $ooid=$oid;
    $done=array();   /* liste des noeuds examines pour ne pas repasser dessus */

    if(!isset($this->adjacence[$oid]))
      $this->adjacence[$oid]=getDB()->fetchAll('SELECT DISTINCT KOIDSRC,node,DTYPE FROM '.$this->idx.' '.
					       'left outer join '.$this->id.' on '.$this->id.'.KOID=KOIDSRC '.
					       'left outer join _TYPES on '.$this->id.'.DTYPE=_TYPES.KOID WHERE KOIDDST=? /*41*/',
					       array($oid));
    foreach($this->adjacence[$oid] as $oid2=>&$ors) {
      $oid2=$ors['KOIDSRC'];
      if(empty($ors['node']) || empty($ors['DTYPE'])) continue;
      if(($types==1) && ($ors['node']==2)) continue;
      if(($ors['node']==1) && !$this->secure($oid2, 'index')) continue;
      if(($ors['node']==2) && !$this->secure($oid2, 'display')) continue;

      // on supprime les fils dossier qui sont vides et sur lesquels on n'a pas le droit de lecture
      if($ors['node']==1){
	$oidssub=$this->subObjects3($oid2, $levels-1, true, $level+1, $types);
	if(empty($oidssub['1']) && empty($oidssub['2']) && !$this->secure($oid2, 'display')) {
	  continue;
	}
      }

      $oids[$ors['node']][]=$oid2;
      $ors['level']=$level;
      $oids['ors'][$oid2]=$ors;
    }
    $oids['1']=array_unique($oids['1']);
    $oids['2']=array_unique($oids['2']);
    $this->_s[$oid]=$oids;
    if(!$myself) {
      $dx=array_search($ooid,$oids['1']);
      if(!($dx===FALSE)) {
	unset($oids['1'][$dx]);
      }
      $dx=array_search($ooid,$oids['2']);
      if(!($dx===FALSE)) {
	unset($oids['2'][$dx]);
      }
    }
    return $oids;
  }

  /// recherche de tous les oids fils lisibles d'un objet y compris lui-meme, sans tenir compte de l'action
  public function &subObjects4($oid, $levels=99, $myself=true, $level=1, $types=0) {
    if(empty($oid)) $oid=$this->rootOid();
    if(isset($this->_s[$oid])) return $this->_s[$oid];
    \Seolan\Core\Logs::debug("\Seolan\Module\DocumentManagement\DocumentManagement::subObjects4: $oid");
    $open=array($oid);
    $node=array(1);
    $oids=array('1'=>array(),'2'=>array());
    if($levels==0) return $oids;
    $ooid=$oid;
    $done=array();   /* liste des noeuds examines pour ne pas repasser dessus */

    $adj=getDB()->fetchAll('SELECT DISTINCT KOIDSRC,node,DTYPE FROM '.$this->idx.' '.
			   'left outer join '.$this->id.' on '.$this->id.'.KOID=KOIDSRC '.
			   'left outer join _TYPES on '.$this->id.'.DTYPE=_TYPES.KOID WHERE KOIDDST=? /*42*/',
			   array($oid));
    foreach($adj as $ii=>&$ors) {
      $oid2=$ors['KOIDSRC'];
      //      var_dump($oid2);
      if(empty($ors['node']) || empty($ors['DTYPE'])) continue;
      if(($types==1) && ($ors['node']==2)) continue;
      if(($ors['node']==1) && !$this->secure($oid2, 'index')) continue;
      //if($oid2=="REP001:fqy5u85qr520") echo "*";
      if(($ors['node']==2) && !$this->secure($oid2, 'display')) continue;
      if(($ors['node']==1) && !$this->secure($oid2, 'display')) {
	$any=$this->subObjects4_1($oid2);

	// on supprime les fils qui sont vides et sur lesquels on n'a pas le droit de lecture
	if(!$any) continue;
      }

      $oids[$ors['node']][]=$oid2;
      $ors['level']=$level;
      $oids['ors'][$oid2]=$ors;
    }
    unset($adj);
    $oids['1']=array_unique($oids['1']);
    $oids['2']=array_unique($oids['2']);
    $this->_s[$oid]=$oids;
    if(!$myself) {
      $dx=array_search($ooid,$oids['1']);
      if(!($dx===FALSE)) {
	unset($oids['1'][$dx]);
      }
      $dx=array_search($ooid,$oids['2']);
      if(!($dx===FALSE)) {
	unset($oids['2'][$dx]);
      }
    }
    return $oids;
  }

  // recherche de tous les oids fils d'un objet y compris lui-meme
  //
  public function subObjects4_1($oid,$actiontotest=':ro',$types=array(1,2)) {
    if(isset($this->_s1[$oid])) return $this->_s1[$oid];
    \Seolan\Core\Logs::debug("\Seolan\Module\DocumentManagement\DocumentManagement::subObjects4_1: $oid");
    $adj=getDB()->fetchAll('SELECT DISTINCT KOIDSRC,node,DTYPE FROM '.$this->idx.' '.
			   'left outer join '.$this->id.' on '.$this->id.'.KOID=KOIDSRC '.
			   'left outer join _TYPES on '.$this->id.'.DTYPE=_TYPES.KOID WHERE KOIDDST=? /*43*/',
			   array($oid));
    foreach($adj as $ii=>&$ors) {
      $oid2=$ors['KOIDSRC'];
      if(empty($ors['node']) || empty($ors['DTYPE'])) continue;
      if(!in_array($ors['node'],$types)) continue;
      // si repertoire et pas de traversee.. on saute
      if(($ors['node']==1) && !$this->secure($oid2, ':list')) continue;
      // si fichier et pas de lecture.. on saute
      if(($ors['node']==2) && !$this->secure($oid2, $actiontotest)) continue;

      // si repertoire et pas de lecture, on regarde ce qu'il y a en dessous
      if(($ors['node']==1) && !$this->secure($oid2, $actiontotest)) {
	if($this->subObjects4_1($oid2,$actiontotest,$types)) {
	  $this->_s1[$oid]=true;
	  return true;
	}
	continue;
      }
      unset($adj);

      $this->_s1[$oid]=true;
      return true;
    }
    $this->_s1[$oid]=false;
    return false;
  }

  // recherche de tous les oids et le ors fils d'un objet y compris lui-meme
  // si types=array(1) on ne rend que les repertoires
  // si types=array(1,2) on rend tous les objets
  // si types=array(2) on rend les documents seulements
  // except : tableau d'oid a exclure
  //
  public function &subObjects2($oid, $types=array(1,2), $levels=99, $myself=true, $actiontotest="display", $except=array()) {
    $open=array($oid);
    $node=array(array('node'=>1, 'KOIDSRC'=>$oid));
    //$node=array();
    $level=array(1);
    $oids=array();
    $orss=array();
    $ooid=$oid;
    $done=array();		/* liste des noeuds examines pour ne pas repasser dessus */
    while(!empty($open)) {
      $oid=array_pop($open);
      $done[]=$oid;
      $n=array_pop($node);
      $l=array_pop($level);
      if(!empty($oid)) {
	if(!$this->secure($oid,$actiontotest)){
	  if($this->subObjects4_1($oid,':rw',array(1))) {
	    $this->_s1[$oid]=true;
	    $n['noLinkTo']=false;
	  } else {
	    $n['noLinkTo']=true;
	    continue;
	  }
	}
	if(in_array($n['node'],$types)) {
	  $oids[]=$oid;
	  $n['level']=$l;
	  $orss[]=$n;
	}
      }
      if($l<$levels) {
	if(!isset($this->adjacence[$oid]))
	  $this->adjacence[$oid]=getDB()->fetchAll('SELECT DISTINCT KOIDSRC,node,DTYPE FROM '.$this->idx.','.$this->id.
					      ',_TYPES WHERE KOIDDST=?'.
					      ' AND '.$this->id.'.KOID=KOIDSRC AND '.$this->id.'.DTYPE=_TYPES.KOID',
					      array($oid));
	foreach($this->adjacence[$oid] as $oid=>&$ors) {
	  $oid=$ors['KOIDSRC'];
	  if(in_array($oid,$except)) continue;
	  if(!in_array($oid,$open) && !in_array($oid,$done)) {
	    $open[]=$oid;
	    $node[]=$ors;
	    $level[]=$l+1;
	  }
	}
      }
    }
    if(!$myself) {		/* on vire l'oid d'origine s'il n'est pas demande */
      $dx=array_search($ooid,$oids);
      if(!($dx===FALSE)) {
	unset($oids[$dx]);
	unset($orss[$dx]);
      }
    }
    $r=array('oids'=>$oids, 'ors'=>$orss);
    return $r;
  }

  /// Rend les x derniers documents deposés dans la base documentaire
  function getLast($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('nb'=>20));
    $nb=$p->get('nb');
    $type=$p->get('type');
    $tplentry=$p->get('tplentry');

    $docs=array();
    $params=[];
    if(!empty($type)) {
      $cond="DTYPE=? AND";
      $params[]=$type;
    }
    $params[]=$this->rootOid();
    $rs=getDB()->select("SELECT T1.KOID FROM {$this->id} as T1,_TYPES WHERE $cond T1.DTYPE=_TYPES.KOID AND ".
			"T1.KOID!=? ORDER BY T1.UPD DESC LIMIT 0,2000", $params);
    $idx=0;
    while($nb-- && $rs && ($ors=$rs->fetch())){
      $oid=$ors['KOID'];
      if(!$this->secure($oid, 'display')){
	$nb++;
	continue;
      }
      $d1=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid,$this);
      $d1->getParents(2);
      $sort[$idx]=$d1->parents[0]->parents[0]->short.$d1->parents[0]->short.$idx;
      $docs[$idx]=$d1;
      $idx++;
    }
    $rs->closeCursor();
    array_multisort($sort,$docs);

    // Liste des types et creation de la liste de pagesize disponible
    $types=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_TYPES');
    $doctypes=$types->browse(array('order'=>'title','pagesize'=>100, 'tplentry'=>TZR_RETURN_DATA));
    for($i=20;$i<=100;$i+=20) $pagesize[]=$i;
    \Seolan\Core\Shell::toScreen1('doctypes',$doctypes);
    \Seolan\Core\Shell::toScreen2('docs','pagesize',$pagesize);
    
    if($tplentry==TZR_RETURN_DATA) return $docs;
    else \Seolan\Core\Shell::toScreen2($tplentry,'docs',$docs);
  }

  /// Rend les documents qui n'ont jamais été consultés
  function getUnread($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('nb'=>20));
    $tplentry=$p->get('tplentry');
    $docs=array();
    $rs=getDB()->select('select '.$this->id.'.* from '.$this->id.' left outer join _TYPES on _TYPES.KOID='.$this->id.'.DTYPE left outer join LOGS on '.$this->id.'.KOID=LOGS.object and LOGS.etype="access" and LOGS.user=? and LOGS.UPD>'.$this->id.'.UPD where _TYPES.node=2 and LOGS.KOID is null order by '.$this->id.'.UPD',
			array(\Seolan\Core\User::get_current_user_uid()));
    while($rs && $ors=$rs->fetch()){
      $oid=$ors['KOID'];
      if(!$this->secure($oid,'display')) continue;
      $docs[]=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid,$this);
    }
    return \Seolan\Core\Shell::toScreen2($tplentry,'docs',$docs);
  }
  
  /// Marque des documents comme lu
  function markAsRead($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oids=$p->get('_selected');
    $selectedok=$p->get('_selectedok');
    if($selectedok!='ok' || empty($oids)) $oids=$p->get('oid');
    else $oids=array_keys($oids);
    if(!is_array($oids)) $oids=array($oids);
    foreach($oids as $i=>$oid){
      \Seolan\Core\Logs::uniqueUpdate('access',$oid);
    }
  }

  function preSubscribe($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get("oid");
    $tplentry=$p->get("tplentry");
    $subdir=$p->get("subdir");
    $here=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    $here->getDocumentsDetails();
    $br['here']=$here;

    if($this->secure($here->oid, 'admin')) {
      list($acl_user, $acl_grp)=\Seolan\Core\User::getUsersAndGroups();
      \Seolan\Core\Shell::toScreen1('users',$acl_user);
      \Seolan\Core\Shell::toScreen1('grps',$acl_grp);
    }
    return \Seolan\Core\Shell::toScreen1($tplentry, $br);
  }

  /// Preparation de l'impression d'un document
  function prePrintDisplay($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    $doc->repository->prePrintDisplay($ar);
  }

  /// Impression d'un document
  function printDisplay($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    $doc->repository->printDisplay($ar);
  }

  /// Abonnement à un document
  function subscribe($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get("oid");
    $subdir=$p->get("subdir");
    $uid=$p->get("uid");
    if(empty($uid)) $uid=\Seolan\Core\User::get_current_user_uid();
    if(empty($subdir)) $subdir="0";else $subdir="1";
    $xmodsub = \Seolan\Core\Module\Module::objectFactory(['_options'=>['local'=>1],'interactive'=>false,'tplentry'=>TZR_RETURN_DATA,'toid'=>XMODSUB_TOID]);
    $xmodsub->addSub(array($uid), $this->_moid, 
		     array('oid'=>$oid,'recursive'=>$subdir, 'details'=>($p->get('details')==1)));
  }
  /// copy ou lien d'un dossier vers un autre
  /// si _copy est à true, copie du document
  /// si _move est à true, déplacement du document
  /// sinon création d'un lien hard sur le doc dans le nouveau dossier
  function linkTo($ar=NULL) {
    $p = new \Seolan\Core\Param($ar,array());
    $move=$p->get('_move');
    $copy=$p->get('_copy');
    $copyrights=$p->get('_copyrights');
    $oids=array_keys($p->get('_selected'));
    $oid=$p->get('oid');
    if(empty($oids)) {
      if(!empty($oid)) $oids=array($oid);
      $parentoid=$p->get('_parentoid');
      if(empty($parentoid)){
	$ors=getDB()->fetchRow('SELECT KOIDDST FROM '.$this->idx.' WHERE KOIDSRC=?',array($oid));
	if($ors) $parentoid=$ors['KOIDDST'];
      }
    } else {
      $parentoid=$oid;
    }
    if(empty($parentoid)) $parentoid=$this->rootOid();
    $oiddst=$p->get('targetoid');
    // droits en ecriture sur la destination ... quel que soit le cas
    if (!$this->secure($oiddst, 'edit')){
      \Seolan\Core\Logs::critical(get_class($this), get_class($this).'::linkTo trying to move|copy|link to ro target '.$oiddst);
      if (!empty($oid))
	\Seolan\Core\Shell::setNextData('oid', $oid);
      \Seolan\Core\Shell::setNextData('message', \Seolan\Core\Labels::getSysLabel('Seolan_Core_Security','default'));
    } else {
      if(!empty($move)) {
	// droits en ecriture sur le parent ... dans le cas du move
	if (!$this->secure($parentoid, 'edit')){
	  \Seolan\Core\Logs::critical(get_class($this), get_class($this).'::linkTo trying to move|copy|link to ro target '.$oiddst);
	  if (!empty($oid))
	    \Seolan\Core\Shell::setNextData('oid', $oid);
	  \Seolan\Core\Shell::setNextData('message', \Seolan\Core\Labels::getSysLabel("Seolan_Core_Security","default"));
	} else {
	  foreach($oids as $i=>$oid) {
	    $notok=$this->subObjects2($oid,array(1,2));
	    if(!in_array($oiddst,$notok['oids']) && $oid!=$oiddst)
	      getDB()->execute('UPDATE '.$this->idx.' SET KOIDDST=? WHERE KOIDSRC=? AND KOIDDST=?', [$oiddst, $oid, $parentoid]);
	  }
	}
      } elseif(!empty($copy)) {
	foreach($oids as $i=>$oid) {
	  // droits en lecture sur la source : ne se produira que pour des dossiers
	  if (!$this->secure($oid, 'display')){
	    \Seolan\Core\Logs::critical(get_class($this), get_class($this).'::linkTo trying to move|copy|link not readable source '.$oid);
	    \Seolan\Core\Shell::setNextData('message', \Seolan\Core\Labels::getSysLabel('Seolan_Core_Security','default'));
	    continue;
	  }
	  if($oid!=$oiddst){
	    $this->copyTo($oid,$oiddst,$copyrights);
	  }
	}
      } else {
	foreach($oids as $i=>$oid) {
	  // droits en lecture sur la source : ne se produira que pour des dossiers
	  if (!$this->secure($oid, 'display')){
	    \Seolan\Core\Logs::critical(get_class($this), get_class($this).'::linkTo trying to move|copy|link not readable source '.$oid);
	    \Seolan\Core\Shell::setNextData('message', \Seolan\Core\Labels::getSysLabel('Seolan_Core_Security','default'));
	    continue;
	  }
	  if(($oid!=$oiddst) && !$this->linkExists($oiddst, $oid)) getDB()->execute('INSERT INTO '.$this->idx.' SET KOIDDST=?, KOIDSRC=?',[$oiddst, $oid]);
	}
      }
    }
  }

  /// rend vrai si un lien hard existe
  function linkExists($dst, $src) {
    return getDB()->fetchOne('SELECT 1 FROM '.$this->idx.' WHERE KOIDDST=? AND KOIDSRC=? ',[$dst, $src]);
  }

  /// suppression d'un document de l'arbo. Le document en tant que tel n'est aps supprimé
  function delDocument($docoid) {
    getDB()->execute("delete from {$this->idx} where KOIDSRC=?", [$docoid]);
    getDB()->execute("delete from {$this->id} where KOID=?", [$docoid]);
  }

  /// ajout d'un document existant dans l'arborescence
  /// création dans la table ID, création du rattachement au conteneur, synchro de la date entre ID et l
  function addDocument($parentoid, $oid, $documenttype) {
    $this->registerDoc($oid, $documenttype);
    $doc = \Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    $doc->addParent($parentoid);
    $doc->syncLastUpdate();
  }
  
  /// Copie d'un noeud et des sous noeuds
  function copyTo($oid,$oiddst,$duplicaterights=false){
    // Duplication du noeud
    $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this, NULL);
    // l'option copy_allowed permet d'éviter la copie pour les documents qui ne doivent pas être copiés pour x raisons
    $copy_allowed=$doc->getOption('copy_allowed');
    if($copy_allowed!="0") {
      $oidres=$doc->repository->XMCduplicate(array('oid'=>$oid,'lastonly'=>false,'changeown'=>true));
      $this->addDocument($oiddst, $oidres, $doc->getDocumentType());
      if($duplicaterights && $this->secure($oid,'secEdit')) $GLOBALS['XUSER']->copyUserAccess($oid, $oidres, $this->_moid);
      
      // Duplication des fils
      $subobj=$this->subObjects3($oid);
      foreach($subobj['ors'] as $oiddoc=>$ors) $this->copyTo($oiddoc,$oidres,$duplicaterights);
    } else {
    }
    return $oidres;
  }

  protected function _lasttimestamp() {
    $rs=getDB()->select('select MAX(UPD) from '.$this->id,array(),false,\PDO::FETCH_NUM);
    if($ors=$rs->fetch()) {
      $rs->closeCursor();
      return $ors[0];
    }
    return 0;
  }

  protected function _whatsNew($ts,$user, $group=NULL, $specs=NULL,$timestamp=NULL) {
    $koid=$specs['oid'];
    $subdir=$specs['recursive'];
    $details=$specs['details'];
    if($subdir) {
      $subobjects=$this->subobjects1($koid,10);
      $oids=$subobjects[1];
    } else {
      $oids=array($koid);
    }
    $condoid=join('","',$oids);
    $rs=getDB()->select('select distinct KOID from '.$this->id.','.$this->idx.' where UPD>= ? and UPD< ? '.
		    'and KOID=KOIDSRC and (KOIDDST IN ("'.$condoid.'") OR KOIDSRC=?)',array($ts,$timestamp,$koid));
    $txt='';
    while($rs && ($ors=$rs->fetch())) {
      $oid=$ors['KOID'];
      $d2=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid,$this);
      if(!$this->secure($d2->oid,'display')) continue;
      $entry=$this->_makeSubEntry($oid, NULL, $details, $ts, $timestamp, $user, $d2->title);
      $txt.=$entry;
    }
    return $txt;
  }

  // rend une chaine qui représente l'abonnement
  //
  function _getSubTitle($oid) {
    $ors=getDB()->fetchRow('SELECT * FROM OPTS WHERE KOID=?',array($oid));
    if(!empty($ors)) {
      $specs=\Seolan\Library\Opts::decodeSpecs($ors['specs']);
      $koid=$specs['oid'];
      $subdir=$specs['recursive'];
      if(\Seolan\Core\Kernel::objectExists($koid)) {
	$d1=&\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($koid,$this);
	$d1->getParents(10);
	$path='';
	foreach($d1->parents as &$doc) {
	  $path.=$doc->title.' > ';
	}
	$url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&_function=goto1&oid='.$koid.'&tplentry=br';
	if(!empty($subdir)) {
	  $subdir=' '.\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','andsubdirectories','text');
	} else $subdir='';
	return \Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','defaultsdir').' <A HREF="'.$url.'">'.$path.$d1->title.'</A> '.$subdir;
      } else {
	// l'abonnement concerne une donnee qui n'existe plus. on le supprimer
	getDB()->execute('delete from OPTS where KOID=?', array($oid));
      }
    }
    return NULL;
  }

  function getTasklet() {
    // recherche de tous les documents qui ont été verrouillés et qui sont en attente
    $now=date("YmdHis",time());
    $rs=getDB()->select('select * from '.$this->id.',_LOCKS WHERE '.$this->id.'.KOID=_LOCKS.KOID AND '.
		     '_LOCKS.OWN=? AND _LOCKS.DEND<? order by _LOCKS.DSTART ASC', array(\Seolan\Core\User::get_current_user_uid(),$now));
    $lines=array();
    $users=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.'USERS');
    $i=0;
    $txt='';
    while($ors=$rs->fetch()) {
      $oid=$ors['KOID'];
      $t=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid,$this);
      if($this->secure($t->oid, 'display')) {
	$url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&_function=goto1&oid='.$oid.'&tplentry=br';
	$txt.='<p>'.$t->smallicon.' <a class="cv8-ajaxlink" href="'.$url.'">'.$t->short.'</A> réservé le '.\Seolan\Field\DateTime\DateTime::dateFormat($t->fields['_lock']['DSTART']).' jusqu\'au '.\Seolan\Field\DateTime\DateTime::dateFormat($t->fields['_lock']['DEND']).'</p>';
      }
    }
    if(!empty($txt)) $txt='<h2>Vos réservations de documents en cours</h2><div>'.$txt.'</div>';
    return $txt;
  }
  function chk(&$message=NULL) {
    // vérification des index
    $idprim = getMetaPrimaryKeys($this->id);
    $idxkeys = getMetaKeys($this->idx, 'KOIDSRC');

    if (!in_array('KOID', $idprim)){
      getDB()->execute("ALTER TABLE {$this->id} ADD PRIMARY KEY (KOID)");
      $message .= "\n repair primary key for {$this->id}";
    }
    if (!in_array('KOIDSRC', $idxkeys)){
      getDB()->execute("ALTER TABLE {$this->idx} ADD KEY KOIDSRC (KOIDSRC)");
      $message  .= "\n repair key  KOIDSRC for {$this->idx}";
    }

    $rootoid = $this->rootOid();
    $lostfoundoid = $this->defaultFolderTable.':lostandfound-'.$this->_moid;
    $defaultFolderXset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$this->defaultFolderTable);
    $sysTypes = array();
    // OID,OID type,Nom type,Nom dossier,OID destination,Module,Node type
    $idtocheck=array(
      array($rootoid,
	    '_TYPES:root-'.$this->_moid,
	    'Root',
	    'Dossiers',
	    NULL,
	    $this->_moid,
	    1),
      array($lostfoundoid,
	    '_TYPES:lostandfound-'.$this->_moid,
	    'Lost and found',
	    'Perdus ou retrouvés', 
	    $rootoid,
	    $this->_moid,
	    1)
    );
    /*
    array($this->defaultFolderTable.':trash','_TYPES:trash','Trash','Poubelle',$rootoid),
    array($this->defaultFolderTable.':archives','_TYPES:archives','Archives','Archives',$rootoid));
    */
    // vérification des noeuds et types spécifiés
    foreach($idtocheck as $idsbis) {
      list($nodeid, $nodetypeoid, $nodetypename,$nodetitle,$parentoid,$nodemoid,$node) = $idsbis;
      $sysTypes[] = $nodetypeoid;
      // Création type
      $cnt=getDB()->count('SELECT COUNT(*) FROM _TYPES WHERE KOID=?', array($nodetypeoid));
      if(!$cnt) {
	getDB()->execute('INSERT INTO _TYPES SET KOID=?, LANG=?, modid=?,'.
			 'UPD=NULL, dtab=?, node=?, title=?',
			 array($nodetypeoid,TZR_DEFAULT_LANG,$nodemoid,$this->defaultFolderTable,$node,$nodetypename));
      }
      // Creation dans $this->id
      $cnt=getDB()->count('SELECT COUNT(*) FROM '.$this->id.' WHERE KOID=?', array($nodeid));
      if(!$cnt) {
	getDB()->execute('INSERT INTO '.$this->id.' SET KOID=?, DTYPE=?, ENDO=0, UPD=NULL', array($nodeid,$nodetypeoid));
      }
      // Création dans la table des repertoires
      $cnt=getDB()->count('SELECT COUNT(*) FROM '.$this->defaultFolderTable.' WHERE KOID=?', array($nodeid));
      if(!$cnt) {
	// title ... supposé présent + traduction
	//getDB()->execute('INSERT INTO '.$this->defaultFolderTable.' SET KOID=?, UPD=NULL, LANG=?,'.'title=?', array($nodeid, TZR_DEFAULT_LANG,$nodetitle));
	$defaultFolderXset->procInput(array('newoid'=>$nodeid,
					     'title'=>$nodetitle));
      }
      // Création dans $this->idx
      if(!empty($parentoid)) {
	$cnt=getDB()->count('SELECT COUNT(*) FROM '.$this->idx.' WHERE KOIDSRC=?', array($nodeid));
	if(!$cnt) {
	  getDB()->execute('INSERT INTO '.$this->idx.' SET KOIDSRC=?, KOIDDST=?', array($nodeid,$parentoid));
	}
      }
    }
    // rattachement des documents (? dossiers) 'orphelins' dans trouvé perdu
    $donesTables = array();
    foreach($this->getTypes() as $type){
      if ($type['onode']->raw == 1 || in_array($type['oid'], $sysTypes)){
	continue;
      } 
      $table = null; // xset, issu du module ou directement du type
      if (empty($type['odtab']->raw)){
	if (isset($type['omodidd']->raw) && \Seolan\Core\Module\Module::moduleExists($type['omodidd']->raw)){
	  $mod = \Seolan\Core\Module\Module::objectFactory(array('interactive'=>false, 'tplentry'=>TZR_RETURN_DATA, 'moid'=>$type['omodidd']->raw));
	  $table = $mod->xset->getTable();
	}
      } else {
	$table = $type['odtab']->raw;
      }
      if (!isset($table) || in_array($table, $donesTables)){
	continue;
      }
      $donesTables[] = $table;
      $totoids = getDB()->fetchOne("select count(distinct koid) from $table"); 
      $oids = getDB()->select("select koid from $table as data where LANG=? and not exists(select 1 from {$this->id} where {$this->id}.KOID=data.KOID)", array(TZR_DEFAULT_LANG))->fetchAll(\PDO::FETCH_COLUMN);
      \Seolan\Core\Logs::debug(__METHOD__." $table document(s) non rattaché(s) : ".count($oids)." sur ".$totoids);
      if (count($oids)>0){
	foreach($oids as $lostoid){
	  // ajout du noeud fils et de l'arc
	  $this->addDocument($lostfoundoid, $lostoid, $type['oid']);
	}
      }
    }
    //rattachement des arcs cassés 
    getDB()->execute('update '.$this->idx.' set KOIDDST=? where KOIDDST is NULL or KOIDDST=""',array($this->rootOid()));
    getDB()->execute('update '.$this->idx.' set KOIDDST=? where KOIDDST =?',array($this->rootOid(),$this->defaultFolderTable.':0'));
    // suppression de lost and found si il est vide
    $nblaf = getDB()->fetchOne("select count(*) from {$this->idx} where KOIDDST=?", [$lostfoundoid]);
    if ((int)$nblaf == 0){
      $this->delDocument($lostfoundoid);
    } 
    return true;
  }

  /// recherche fils de niveau d'accès demandé, parent supposé accessible
  /// parcours de toute l'arborescence pour l'élaguer en plaçant des règles dans le cache des acl
  public function hasSubObject($oid, $levels=99, $user=null, $right=':ro') {
    if(empty($oid)) $oid=$this->rootOid();
    $open=array($oid);
    $node=array(1);
    $level=array(1);
    $ooid=$oid;
    $done=array();   /* liste des noeuds examines pour ne pas repasser dessus */
    $return = false;
    $this->adjacence = getDB()->select('SELECT DISTINCT KOIDDST,KOIDDST,KOIDSRC,node,DTYPE FROM '.$this->idx.','.$this->id.',_TYPES '.
                                                   'WHERE '.$this->id.'.KOID=KOIDSRC AND '.$this->id.'.DTYPE=_TYPES.KOID')->fetchAll(\PDO::FETCH_GROUP);
    while(!empty($open)) {
      $oid=array_pop($open);
      $n=array_pop($node);
      $l=array_pop($level);
      $done[]=$oid;
      $acls[$oid] = array(
        $right => \Seolan\Core\User::secure8oid($right, $this, $oid, $user, null, true),
        ':rw' => \Seolan\Core\User::secure8oid(':rw', $this, $oid, $user, null, true),
      );

      if($n==1 && $l<$levels && \Seolan\Core\User::secure8oid('index', $this, $oid, $user, null, true)) {
        $acls[$oid]['index'] = true;
	if(isset($this->adjacence[$oid]) && is_array($this->adjacence[$oid])) {
        foreach($this->adjacence[$oid] as $ors) {
          $childOid=$ors['KOIDSRC'];
          $this->_f1[$childOid][] = $oid; // _f1 cache des parents
          if(!in_array($childOid,$done) && !in_array($childOid,$open)){
            $open[]=$childOid;
            $node[]=$ors['node'];
            $level[]=$l+1;
          }
        }
      }
      }
    }
    $acls = $this->_acls($ooid, $acls, $right);
    foreach ($acls as $oid => $acl) {
      $return |= @$acl['visible'];
      if (!@$acl['visible'] && !@$acl[$right] && !@$acl[':rw'] && isset($this->_f1[$oid])) {
        $need = false;
        foreach ($this->_f1[$oid] as $parentOid) // si un parent est visible, on a besoin de la règle
          $need |= $acls[$parentOid]['visible'];
        if ($need) {
          \Seolan\Core\User::setCache(null, 'index', null, $this->_moid, $oid, 0, 'hasSubObject', 'none');
          \Seolan\Core\User::setCache(null, ':list', null, $this->_moid, $oid, 0, 'hasSubObject', 'none');
        }
      }
    }
    return $return;
  }
  
  /// calcul la visibilité
  private function _acls($oid, $acls, $right) {
    if(!empty($this->adjacence[$oid]) && is_array($this->adjacence[$oid])) {
      foreach ($this->adjacence[$oid] as $node) {
	$nodeoid = $node['KOIDSRC'];
	if ($node['node'] == 1 && !isset($acls[$nodeoid]['visible']))
	  $acls = $this->_acls($nodeoid, $acls, $right);
	// visible si un fils visible ou lecture ou plus
	@$acls[$oid]['visible'] |= ($acls[$nodeoid][$right] || $acls[$nodeoid][':rw']
				    || @$acls[$nodeoid]['visible']); 
      }
    }
    return $acls;
  }

  // Alias de secure pour garder la compatibilité d'avant la refonte de droits
  function secure1($oid, $function, $node=0, &$user=NULL) {
    return parent::secure($oid,$function,$user);
  }

  /// ok si accessible et non vide (cas des dossier)
  function secureNotEmpty(string $oid, string $function, $user=null, $lang=TZR_DEFAULT_LANG) {
    if (\Seolan\Core\Shell::isRoot())
      return true;
    return $this->hasSubObject($oid, 99, $user, ':ro');
  }

  /// Définit si la sécurité sur les objets est activé
  public function objectSecurityEnabled(){
    if($this->object_sec || $this->documentssec) return true;
    return false;
  }

  /// Définit si le cache des droits doit être activé ou pas.
  public function rightCacheEnabled(){
    return $this->objectSecurityEnabled();
  }

  /// Retourne le parent direct de chaque oid passé en paramètre
  public function getParentsOids($oids){
    $ret=array();
    foreach($oids as $oid){
      if(!$oid) continue;
      $parents=$this->father1($oid);
      if(!$parents) $ret[]='';
      else $ret=array_merge($ret,$parents);
    }
    return $ret;
  }

  // telechargement dans un zip d'un ensembl de documents
  function documentsDownload($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $selected=$p->get('_selected');
    if(empty($selected)) $selected=array($p->get('oid')=>1);

    $dir=TZR_TMP_DIR.'download-'.uniqid();
    @mkdir($dir);
    $nbfiles=0;
    foreach($selected as $koid=>$foo) {
      $doc=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($koid, $this);
      $details=$doc->getFilesDetails();
      $asciify=rewriteToAscii($doc->title);
      $path=$dir.'/'.$asciify;
      @mkdir($path);
      foreach($details as &$e) {
	copy($e['filename'], $path.'/'.$e['name']);
	$nbfiles++;
      }
    }
    exec('(cd '.$dir.';zip -r '.$dir.'.zip .)2>&1 > '.TZR_TMP_DIR.'errorlog');
    $size=filesize($dir.'.zip');
    header('Content-type: application/zip');
    header('Content-disposition: attachment; filename=download.zip');
    header('Accept-Ranges: bytes');
    header('Content-Length: '.$size);
    @readfile($dir.'.zip');
    \Seolan\Library\Dir::unlink($dir);
    unlink($dir.'.zip');
    exit(0);
  }
  function &portlet2() {
    $txt ='<h1>'.$this->getLabel().'</h1>';
    $txt.='<p><form action="'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'" method="post" onsubmit="return TZR.ajaxSubmitForm(this,\'#cv8-content\');">';
    $txt.='<input type="hidden" name="moid" value="'.$this->_moid.'">';
    $txt.='<input type="hidden" name="function" value="search">';
    $txt.='<input type="hidden" name="template" value="Module/DocumentManagement.query2.html">';
    $txt.='<input type="hidden" name="tplentry" value="br">';
    $txt.='<input type="text" name="query">';
    $txt.='<input type="submit" value="'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','search','text').'">';
    $txt.='</form></p>';
    return $txt;
  }
  function &export($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array('archives'=>0));
    $o['ftphost']=$p->get('ftphost');
    $o['ftpuser']=$p->get('ftpuser');
    $o['ftppass']=$p->get('ftppass');
    $o['ftproot']=$p->get('ftproot');
    $o['archives']=$p->get('archives');
    $o['trimnames']=$p->get('trimnames');
    $usernotification=$p->get('usernotification');
    if ($usernotification){
      $o['finished_notification'] = ['recipients'=>[$GLOBALS['XUSER']->_cur['email'].';'.$GLOBALS['XUSER']->_cur['fullnam']]];
    }
    $o['oid']=$p->get('oid');
    $o['function']='exportBatch';
    $scheduler= new \Seolan\Module\Scheduler\Scheduler(array('tplentry'=>TZR_RETURN_DATA));
    $roid=$scheduler->createJob($this->_moid, NULL,'Export FTP '.$this->getLabel(),$o,'', NULL, NULL);
    \Seolan\Core\Shell::toScreen2('br','message','L\'export de données a été programmé et sera rapidement disponible');
  }

  function exportBatch(\Seolan\Module\Scheduler\Scheduler &$scheduler, &$o, &$more) {
    $ftphost=$more->ftphost;
    $ftpuser=$more->ftpuser;
    $ftppass=$more->ftppass;
    $ftproot=$more->ftproot;
    $oid=$more->oid;

    $cr = $this->exportFTP(array('ftphost'=>$ftphost, 'ftpuser'=>$ftpuser, 'ftppass'=>$ftppass, 'ftproot'=>$ftproot,
				 'oid'=>$oid,'tplentry'=>TZR_RETURN_DATA, 
				 'archives'=>(isset($more->archives) && $more->archives == 1),
				 'trimnames'=>(isset($more->trimnames) && $more->trimnames == 1),
				 'scheduler'=>$scheduler,
				 'jobid'=>$o->KOID
    ));

    $scheduler->setStatusJob($o->KOID, 'finished', $cr, 'finished');
    
    return null;
    
  }

  function exportFTP($ar=NULL) {
    $trim = 0; // pas de trim
    $pathlength = 256; // longueur max windows
    if (defined('TZR_DOCMGT_EXPORT_CHECKPATHLENGTH')){
      $pathlength = TZR_DOCMGT_EXPORT_CHECKPATHLENGTH;
    }
    $p=new \Seolan\Core\Param($ar, ['trimnames'=>0, 'checkpathlength'=>$pathlength]);

    $ftphost = $p->get('ftphost');
    $ftpuser = $p->get('ftpuser');
    $ftppass = $p->get('ftppass');
    $ftproot = $p->get('ftproot');
    $archives = $p->get('archives');

    $oid = $p->get('oid');

    if (1 == $p->get('trimnames')){
      $trimlength = 5;
    } else {
      $trimlength = 0;
    }
    $this->_export = (Object)['trimnames'=>$trimlength,
			      'checkpathlength'=>$p->get('checkpathlength'),
			      'path'=>[],
			      'cr'=>date('Y-m-d h:i:s')." - Export $oid to $ftpuser@$ftphost/$ftproot/",
			      'nbdoc'=>0,
			      'nbfold'=>0,
			      'nbarch'=>0,
			      'longpaths'=>[]
			      ];
    
    if (isset($ar['scheduler'])){
      $this->_export->scheduler=$ar['scheduler'];
      $this->_export->jobid=$ar['jobid'];
    } else {
      $this->_export->scheduler=null;
      $this->_export->jobid=null;
    }
   
    if(empty($oid)) $oid=$this->rootOid();

    $ftphost=str_replace('ftp://','',$ftphost);
    $conn_id = ftp_connect($ftphost);
    if (!$conn_id) return 'Unable to connect to '.$ftphost;
    $login_result = ftp_login($conn_id, $ftpuser, $ftppass);
    if(!$login_result) return 'Error logging into '.$ftphost;
    if(!empty($ftproot)) {
      ftp_mkdir($conn_id, $ftproot);
      ftp_chdir($conn_id, $ftproot);
    }

    // on mémorise le chemin absolue car on sait pas trop ou on arrive lors de la connexion
    $exportdir = ftp_pwd($conn_id); 

    set_time_limit(0);

    $this->exportFTP1($conn_id, $oid, $archives);

    // tentative de checks des contenus exportés
    try{
      $nbexport=0;
      $res = ftp_rawlist($conn_id, $exportdir, true);
      foreach($res as $resline){
	if (preg_match('/.*\.zip$/', $resline)){
	  $nbexport++;
	}
      }
      $check .= "\n Fichiers zip sur le dossier distant après export : $nbexport";
    }catch(Exception $e){
      $check .= "\n Impossible de vérifier les fichiers sur le serveur ".$e->getMessage();
    }

    ftp_close($conn_id);

    $this->_export->cr .= "\nDossiers  : {$this->_export->nbfold}";
    $this->_export->cr .= "\nDocuments : {$this->_export->nbdoc}";
    if ($archives){
      $this->_export->cr .= "\n\tDont archives : {$this->_export->nbarch}";
    }

    $this->_export->cr .= $check;

    if (count($this->_export->longpaths)>0){
      $this->_export->cr .= "\n".count($this->_export->longpaths).' chemin(s) trop long(s) : '.implode("\n\t", $this->_export->longpaths);
    } 

    $this->_export->cr .= "\n Export end : ".date('Y-m-d h:i:s');

    \Seolan\Core\Logs::notice(__METHOD__, $this->_export->cr);

    return $this->_export->cr;

  }
  function cleanFilename($filename){
    return preg_replace('/\'[_\-]+\//', '_', cleanFilename($filename));
  }
  function exportFTP1($conn_id, $oid, $archives=false, &$siblings = array()) {

    $br=$this->index(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$oid, 'maxlevel'=>1000));

    $node=&$br['here'];
    $title=$node->title;
    if (isset($this->_export->trimnames) && $this->_export->trimnames > 0){
      $title = $this->trimName($this->cleanFilename($title), $this->_export->trimnames);
    } else {
      $title=$this->cleanFilename($title);
    }
    if (empty($title)){
      $title = cleanFilename($oid);
    }
    $title = $this->getDocUniqueName($siblings, $title);
    $this->_export->path[] = $title;
    $currentpath = implode('/', $this->_export->path);
    if (strlen($currentpath) > $this->_export->checkpathlength){
      \Seolan\Core\Logs::notice(__METHOD__, "$currentpath $oid pathlength=".strlen($currentpath)." > ".$this->_export->checkpathlength);
      $this->_export->longpaths[] = $currentpath;
    }
    $this->_export->nbfold++;

    // maj trace status
    if ($this->_export->scheduler != null){
      $statusjob = '';
      $this->_export->scheduler->statusJob($this->_export->jobid, $statusjob, $currentpath);
    }

    $newdir = ftp_mkdir($conn_id, $title);
    if ($newdir === false){ // retourne le path complet 
      \Seolan\Core\Logs::critical(__METHOD__, 'Error create new dir '.$currentpath);
    } 
    
    ftp_chdir($conn_id, $title);
    $childrennames = array();
    foreach($node->dirsoids as $i=>$oid1) {
      $this->exportFTP1($conn_id, $oid1, $archives, $childrennames);
    }
    $childrendocnames = [];
    foreach($node->docsoids as $i=>$oid1) {
      $this->exportFTP2($conn_id, $oid1, $archives, $childrendocnames);
    }
    

    // depiler le path en cours
    array_pop($this->_export->path);
    
    ftp_cdup($conn_id);
  }
  /// export d'un document de type document
  function exportFTP2($conn_id, $oid, $archives=false, &$childrendocnames=[]) {
    /* @var $node XDocumentDT */
    $node=\Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);
    $docname = $this->getDocUniqueName($childrendocnames, $this->cleanFilename($node->title));
    $this->exportDocumentNode($node, $conn_id, $docname);
    $this->_export->nbdoc++;
    if ($archives){
      foreach($node->getArchives() as $archiveDate=>$archiveNode) {
          if ($archiveNode != NULL){
              // on garde le titre du dernier document (numérotation eventuelle)
              $this->exportDocumentNode($archiveNode, $conn_id, $docname.'_A'.str_replace(['-', ':', ' '], '', $archiveDate), $archiveDate);
              $this->_export->nbdoc++;
              $this->_export->nbarch++;
          }
      }
    }
  }
  function exportDocumentNode($node, $conn_id, $docname, $archiveDate=NULL){
    $details=$node->getFilesDetails();
    $dir=TZR_TMP_DIR.'download-'.uniqid();
    @mkdir($dir);
    $path=$dir.'/'.$docname;
    @mkdir($path);
    $xt = new \Seolan\Core\Template(TZR_SHARE_DIR.'Module/DocumentManagement.template-display.txt');
    // !! archives 
    if ($archiveDate == NULL){
      $tpldata['br']=$node->repository->XMCdisplay(array('oid'=>$node->oid, 'tplentry'=>TZR_RETURN_DATA));
    } else {
      $tpldata['br']=$node->repository->XMCdisplay(array('oid'=>$node->oid, 'tplentry'=>TZR_RETURN_DATA, '_archive'=>$archiveDate));
    }
    $r3=array();
    $notice=$xt->parse($tpldata,$r3,NULL);
    $this->normalizeDetails($details);
    foreach($details as &$e) {
      copy($e['filename'], $path.'/'.$e['name']);
    }
    // la notice
    if ($archiveDate != NULL){
      $notice = "\nArchive document : $archiveDate\n\n".$notice;
    }
    file_put_contents($path.'/notice.txt', $notice);
    exec('(cd '.$dir.';zip -r '.$dir.'.zip .)2>&1 > '.TZR_TMP_DIR.'errorlog');

    
    $currentpath = implode('/', $this->_export->path);
    $currentpath .= '/'.$docname.'.zip';
    if (strlen($currentpath) > $this->_export->checkpathlength){
      \Seolan\Core\Logs::notice(__METHOD__, "$currentpath {$node->oid} pathlength=".strlen($currentpath)." > ".$this->_export->checkpathlength);
      $this->_export->longpaths[] = $currentpath;
    }
    $putok = ftp_put ($conn_id, $docname.'.zip', $dir.'.zip', FTP_BINARY);
    if (!$putok){
      \Seolan\Core\Logs::critical(__METHOD__, "Error put file $docname");
    }
    ftp_put ($conn_id, $docname.'.zip', $dir.'.zip', FTP_BINARY);
    \Seolan\Library\Dir::unlink($dir);
    unlink($dir.'.zip');
    unset($tpldata['br']);
  }
  /// contrôle les noms des documents 
  function normalizeDetails(&$details) {
    $files = array('notice.txt'); // on reserve le nom de la notice
    // ajouter des extensions aux fichiers n'en disposant pas
    foreach ($details as &$e) {
      if (!preg_match('/(\.[^\.]+$)/', $e['name'])) {
	$e['name'].='.' . \Seolan\Library\MimeTypes::getInstance()->get_extension($e['mime']);
      }
      // eviter d'ecraser des fichiers qui portent le même nom
      $e['name'] = $this->getDocUniqueName($files, $e['name']);
    }
  }
  /// nom unique parmis les siblings et mémorisation
  function getDocUniqueName(&$siblings, $name){
    if (!isset($siblings[$name])){
      $siblings[$name]=-1;
    }
    $siblings[$name]++;
    if ($siblings[$name] != 0){
      $name = $name.'('.$siblings[$name].')';
    }
    return $name;
  }
  /// "trim" d'un titre
  function trimName($title, $len=9999){
    $words = preg_split('/[  _-]/', $title, -1, PREG_SPLIT_NO_EMPTY);
    unset($word);
    if (count($words)>1){
      foreach($words as &$word){
	if (strlen($word)>=$len){
	  if (!is_numeric($word)){ // garder les dates
	    $word = ucfirst(strtolower(substr($word, 0, ($len-1))));
	  }
	}
      }
      $title = implode($words);
    }
    return $title;
  }
  /// liste des tables utilisees par ce module
  function tablesToTrack() {
    $rs=getDB()->select('SELECT distinct dtab FROM _TYPES where modid=?', [$this->_moid]);
    $res=array();
    while($rs && $ors=$rs->fetch()) {
      if(!empty($ors['dtab'])) $res[]=$ors['dtab'];
    }
    return $res;
  }

  /// Recupere des infos sur le module : nom (modulename), nombre d'enregistrement (cnt), espace occupé (size)
  function getInfos($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>TZR_RETURN_DATA));
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=parent::getInfos($ar);
    // Nombre de documents
    $ret['infos']['cnt']=(object)array('label'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','docs','text'),
				       'html'=>$ret['cnt']=getDB()->count('select count(*) from '.$this->id));
    // Espace occupé
    $tabs=$this->usedMainTables();
    $tot=0;
    foreach($tabs as $t){
      $s=\Seolan\Core\DbIni::get('xmodadmin:workspacesize_'.$t,'val');
      if($s===NULL){
       	$tot=NULL;
	continue;
      }
      $tot+=$s*1024;
    }
    $ret['infos']['size']->label=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','workspace','text');
    if($tot!==NULL){
      $ret['size'] = $tot;
      $ret['infos']['size']->html=getStringBytes($ret['size']);
    }
    else $ret['infos']['size']->html=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','infonotcalculate','text');
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /// lors de l'ajout au menu ne pas mémoriser le clear
  protected function _normalizeFct($fct, $infos, $from){
    if ($from == 'newsection'){
      $fct = preg_replace('/clear=1/', '', $fct);
    }
    return $fct;
  }
  /// fonction réelement activée / saveUserPref pour construire le bon menu par exemple
  protected function _functionOverride(){

    $f = $GLOBALS['XSHELL']::_function();
    $docmode = $this->_getSession('docmode');
    if ($this->saveUserPref && in_array($f, ['index','index2']))
      return $docmode;
    else 
      return $f;
  }
  /**
   * parcours de la corbeille
   * liste des éléments dans la corbeille par types
   * instanciation du document en tant qu'archive
   * ajout des actions (consultation, restauration, effacement)
   */
  public function browseTrash($ar=null){
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>'trash']);
    $res = parent::browseTrash($ar);
    $res['nbtot'] = 0;
    $res['repos'] = [];
    $types = $this->getTypes();
    $oidtoprotect=$this->protectedOids();
    foreach($types as $type){
      if (in_array($type['oid'], $oidtoprotect))
	continue;
      $nodeTypeOrs = getDb()->fetchRow('select * from _TYPES where KOID=?', [$type['oid']]);
      if ($nodeTypeOrs['node'] == 1){ // on ne traite pas les dossiers en restauration (? à voir ne pas poubelliser en fait)
	continue;
      }
      $repo = \Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(null,$type);
      // browseTrash Datasource pour avoir oid et UPD 
      $typefilter = ' and extractValue(LOGS.details,\'//tr[th[text()="_trashtype"]]/td/text()\')="'.$type['oid'].'"';
      $trashbrowse = $repo->XMCbrowseTrash(['_trashmoid'=>$this->_moid,'_trashfilter'=>$typefilter,'selectedfields'=>['UPD','usernam']]);
      if (count($trashbrowse['lines_oid'])==0){
	continue;
      }
      $res['nbtot'] += $trashbrowse['last'];
      $res['repos'][$type['oid']] = ['type'=>$type, 'browse'=>$trashbrowse];
      // instanciation des doc archive 
      $res['repos'][$type['oid']]['browse']['lines__doc'] = [];
      foreach($res['repos'][$type['oid']]['browse']['lines_oid'] as $l=>$docoid){
	// ajout des champs issu normalement de DM_ID
	$nodeTypeOrs['DTYPE'] = $type['oid'];
	$nodeTypeOrs['DPARAM'] = null;
	$res['repos'][$type['oid']]['browse']['lines__doc'][$l] = \Seolan\Module\DocumentManagement\Model\Document\Document::archiveObjectsFactory($docoid, $this, $nodeTypeOrs, $res['repos'][$type['oid']]['browse']['lines_oUPD'][$l]->raw); 
	$this->setTrashActions($res['repos'][$type['oid']]['browse']);
      }

    }
    //    
    return \Seolan\Core\Shell::toScreen1($p->get('tplentry'), $res);
  }
  /// create de la tâche de purge. calcul du Synchrone ou pas
  protected function createEmptyTrashAllTask(\Seolan\Module\Scheduler\Scheduler $scheduler, $more=null){
    list($roid, $now) = parent::createEmptyTrashAllTask($scheduler, $more);
    $bt = $this->browseTrash(['tplentry'=>TZR_RETURN_DATA]);
    if ($bt['nbtot'] <= 500){
      $now = true;
    }
    return [$roid, $now];
  }
  /**
   * traitement de vidage de la corbeille (sync et async)
   * -> effacement de chaque type via le repo
   */
  public function emptyTrashAllBatch(\Seolan\Module\Scheduler\Scheduler $scheduler, $o, $more){

    // boucle par type (repo) comme pour le browse mais on traite aussi les dossiers
    $types = $this->getTypes();
    $nbrepos = [];
    $oidtoprotect=$this->protectedOids();
    foreach($types as $type){
      if (in_array($type['oid'], $oidtoprotect))
	continue;
      $nodeTypeOrs = getDb()->fetchRow('select * from _TYPES where KOID=?', [$type['oid']]);
      $repo = \Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(null,$type);
      // browseTrash Datasource pour avoir oid et UPD 
      $typefilter = ' and extractValue(LOGS.details,\'//tr[th[text()="_trashtype"]]/td/text()\')="'.$type['oid'].'"';
      $trashbrowse = $repo->XMCbrowseTrash(['_trashmoid'=>$this->_moid,'_trashfilter'=>$typefilter,'selectedfields'=>['UPD'],'pagesize'=>9999]);
      if (count($trashbrowse['lines_oid'])==0){
	continue;
      }
      $nbrepos[$type['otitle']->html] = $trashbrowse['last'];
      foreach($trashbrowse['lines_oid'] as $l=>$oid){
	$repo->XMCemptyTrash(['oid'=>$oid,'_archive'=>$trashbrowse['lines_oUPD'][$l]->raw]);
      }
    }
    $m = 'Eléments supprimés : '.array_sum($nbrepos);
    foreach($nbrepos as $name=>$nb){
      $m .= "\n\t $name : $nb";
    }
    $scheduler->setStatusJob($o->KOID, 'finished', $m);

  }
  protected function getDelArchiveActionHelper(){
    return new Class('delArchive', $this, 'Seolan_Core_General delete','edit','class="cv8-ajaxlink cv8-dispaction"') extends \Seolan\Core\Module\BrowseActionHelper{
      function browseActionUrl($usersel, $linecontext=null){
	$dtversion = $linecontext['browse']['lines_oUPD'][$linecontext['index']]->raw;
	$dtype = $linecontext['browse']['lines__doc'][$linecontext['index']]->tpl['oid'];
	$url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self();
	$next= $this->module->getMainAction();
	return $url.'&moid='.$this->module->_moid.'&_dtype='.urlencode($dtype).'&_archive='.urlencode($dtversion).'&oid=<oid>&tplentry=br&function=emptyTrash&template=Core.empty.html&_skip=1&_next='.urlencode($next);
      }
      function browseActionHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
	return 'class="cv8-dispaction" '.
	  ' onclick="TZR.Archives.delArchiveConfirm(\''.$url.'\');'.
	  ' return false;"';
      }
    };
  }
  protected function getRestoreArchiveActionHelper(){
    return new Class('restoreArchive', $this, 'Seolan_Core_General restore_from_trash','display','class="cv8-ajaxlink cv8-dispaction"') extends \Seolan\Core\Module\BrowseActionHelper{
      function browseActionUrl($usersel, $linecontext=null){
	$dtype = $linecontext['browse']['lines__doc'][$linecontext['index']]->tpl['oid'];
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
	       ' onclick="TZR.Archives.restoreArchiveTarget(\''.$url.'\');'.
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
  /**
   * @todo / finalize : droits (ecrirute parent, accès type)
   * enregistrer l'oid
   * instancier le parent et ajouter le fils
   * restaurer sur le repository
   */
  public function moveFromTrash($ar=null){
    $p = new \Seolan\Core\Param($ar, []);
    $oid = $p->get('oid');
    $dtypeoid = $p->get('_dtype');
    $archive = $p->get('_archive');
    $parentoid = $p->get('_parentoid');
    // contrôles type accessible
    $dtype = $this->getTypes($dtypeoid);
    if(empty($dtype)){
      \Seolan\Core\Logs::critical('security',"access type denied |".get_class($this)."|moveFromTrash|".$this->_moid."|TYPE ".$dtypeoid.
				  "| user ".\Seolan\Core\User::get_current_user_uid());
      \Seolan\Core\Shell::redirect2auth();
    }
    if(empty($parentoid)) 
      $parentoid=$this->rootOid();
    // controle écriture parent ... [todo voir procInput ?]
    $this->registerDoc($oid, $dtypeoid);
    $docparent = \Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($parentoid, $this);
    $docparent->addChild($oid);
    $docrepo = \Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(null,$dtype);
    $docrepo->XMCmoveFromTrash(['oid'=>$oid,'_archive'=>$archive, 'moid'=>$this->_moid, 'user'=>\Seolan\Core\User::get_current_user_uid()]);
  }
  /**
   * suppression d'un document (ou plusieurs) de la corbeille
   * dans cette version -> XMCemptyTrash
   */
  public function emptyTrash($ar=null){
    $p = new \Seolan\Core\Param($ar, []);
    $oid = $p->get('oid');
    $upd = $p->get('_archive');
    $typeoid = $p->get('_dtype');
    $dtype = $this->getTypes($typeoid);
    $repo = \Seolan\Module\DocumentManagement\Model\Document\Document::repositoryFactory(null,$dtype);
    if (!$repo){
      \Seolan\Core\Logs::critical(__METHOD__," unable to get repository for type $typeoid");
      return false;
    } 
    $repo->XMCemptyTrash(['oid'=>$oid,'_archive'=>$upd]);
  }
  /// actions par lots sur les documents de la sélection
  public function userSelectionActions(){
    $actions = [];
    // Supprimer les documents
    if ($this->secure('', 'del')){
      $deltxt=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete');
      $actions['del']="<a href=\"#\" onclick=\"TZR.SELECTION.applyTo({$this->_moid},'delWithSelection',null,null,true, null, true); return false;\">{$deltxt}</a>";
    }
    // Avertir
    if ($this->secure('', 'sendACopyTo')){
      $sendtxt=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','sendacopyto');
      $actions['sendacopy']="<a href=\"#\" onclick=\"TZR.SELECTION.applyToInContentDiv({$this->_moid},'sendACopyTo',false,{applyToAll:0,template:'Core/Module.sendacopyto.html'}); return false;\">{$sendtxt}</a>";
    }
    // Télécharger les documents
    if ($this->secure('', 'documentsDownload')){
      $downloadtxt=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','download');
      $actions['documentsdownload']="<a href=\"#\" onclick=\"TZR.SELECTION.submitTo({$this->_moid},'documentsDownload',null,null,false, null,false); return false;\">{$downloadtxt}</a>";
    }
    // Sécurité ?
    return $actions;
  }
  /// détail d'un document (les dossiers ne sont pas mis en sélection)
  public function _browseUserSelection($oid) {
    if(!$this->object_sec || $this->secure($oid,'display')){
      $document = \Seolan\Module\DocumentManagement\Model\Document\Document::objectFactory($oid, $this);

      if ($document) {
        $r = [$document->title];
        $res['link'] = $r[0];
        $res['oid'] = $oid;

        return $res;
      }
    }
    return false;
  }
}

