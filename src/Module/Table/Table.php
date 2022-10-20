<?php
/****
 * NAME
 *   \Seolan\Module\Table\Table -- gestion d'un ensemble de fiches
 * DESCRIPTION
 *   Affichage edition et manipulations diverses sur une ensemble de fiches.
 * SYNOPSIS
 *   La creation d'un module est realisee par utilisation de la methode de classe \Seolan\Core\Module\Module::objectFactory.
 * PARAMETERS
 ****/
namespace Seolan\Module\Table;
use Seolan\Core\{Param, Shell, Labels};

class Table extends \Seolan\Core\Module\ModuleWithSourceManagement implements \Seolan\Core\Module\ConnectionInterface, \Seolan\Module\Calendar\ConnectionInterface, \Seolan\Module\Form\ConnectionInterface {
  /// Non défini par défaut => utilisation de __get
  //public $xset;
  //public $boid;
  //public $fieldssec;
  //static $_templates;
  protected static $editTranslationTemplate = 'Module/Table.edit-translations.html';
  protected static $viewTranslationTemplate = 'Module/Table.view-translation.html';
  /**
   *  Evenement déclenché au début des méthodes browse, display, procEdit, etc.
   */
  const EVENT_PRE_CRUD = 'pre_crud';
  public $table='T001';
  public $multipleedit=true;
  public $owner_sec=true;
  public $filter='';
  public $order='UPD DESC';
  public $quickquery=true;
  public $stored_query=false;
  public $pagesize=TZR_XMODTABLE_BROWSE_PAGESIZE;
  public $templates='';
  public $btemplates='';
  public $captcha=false;
  public $honeypot=false;
  public $savenext='standard';
  public $persistentquery=false;
  public $trackaccess=false;
  public $trackchanges=true;
  public $archive=false;
  public $searchtemplate='Module/Table.searchResult.html';
  public $submodsearch=false;
  public $defaultispublished=false;
  public $clonefrombrowse = false;
  public $allowcomments=false;
  private $secOids_cache;
  private $navActions = NULL;
  public $query_comp_field = NULL;
  public $query_comp_field_value = '';
  public $query_comp_field_op = '';
  public $unfoldedgroupsnumber=1;
  public $numberOfColumns=1;

  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Table_Table');
  }

  function __get($name){
    // On teste la valeur car avec empty(), __get peut etre appelé 2 fois à la suite et on ne veut construire l'info qu'une fois
    if(!property_exists($this,$name)){
      switch($name){
      case 'xset':
      case 'boid':
        $this->xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$this->table);
        if (!is_object($this->xset)) {
          throw new \Exception("Non existent table '{$this->table}' {$this->_moid} {$this->getLabel()}");
        }
        $this->boid=$this->xset->getBoid();
        break;
      case 'fieldssec':
        if(!\Seolan\Core\Shell::isRoot() && !empty($GLOBALS['XUSER'])) $this->loadFieldsSec($this->fieldssec);
        else $this->$name=array();
        break;
      case '_templates':
        if(\Seolan\Core\Shell::admini_mode() && (!empty($this->templates) || !empty($this->btemplates)))
          $this->_templates=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
        else
          $this->_templates=null;
        break;
      default:
        $this->$name=null;
        break;
      }
    }
    return $this->$name;
  }
  function __isset($name){
    $this->__get($name);
    return isset($this->$name);
  }


  /// Duplication d'un module, méthode interne
  /// Retour : duplicatetables => liste des tables dupliquées par le module (cle : ancienne table, valeur : nouvelle table))
  /// Retour : duplicatemods => liste des modules dupliqués par le module (cle : ancien moid, valeur : nouveau moid))
  function _duplicateModule($newmoid,&$params,$prefix) {
    if(!isset($params['tables']) || !is_array($params['tables'])) $params['tables']=array();
    if(!isset($params['noduplicatetable']) || !$params['noduplicatetable']){
      if(empty($params['tables'][$this->table]) || is_array($params['tables'][$this->table])){
        // Nom SQL de la table
	if(isset($params['tables'][$this->table]['newtable'])) $ar['newtable']=$params['tables'][$this->table]['newtable'];
        else $ar['newtable']=\Seolan\Model\DataSource\Table\Table::newTableNumber();
        // Libellé de la table
        if(isset($params['tables'][$this->table]['mtxt'])) $ar['mtxt']=$params['tables'][$this->table]['mtxt'];
	elseif(($pos=strpos($this->xset->getLabel(),':'))!==false) $ar['mtxt']=$prefix.substr($this->xset->getLabel(),$pos);
	else $ar['mtxt']=$prefix.' : '.$this->xset->getLabel();
	$ar['data']=true;
	$ar['_options']=array('local'=>1);
	$xset2=$this->xset->procDuplicateDataSource($ar);
	$params['table']=$ar['newtable'];
      }else{
	$params['table']=$params['tables'][$this->table];
      }
    }
    unset($params['noduplicatetable']);
    return array('duplicatetables'=>array($this->table=>$params['table']),'duplicatemods'=>array());
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['browse']=array('list','ro','rw','rwv','admin');
    $g['browseForSpreadsheet']=['list','ro','rw','rwv','admin'];
    $g['browseFiles']=array('list','ro','rw','rwv','admin');
    $g['del']=array('rw','rwv','admin');
    $g['delJSon']=array('rw','rwv','admin');
    $g['delAll']=array('rw','rwv','admin');
    $g['delStoredQuery']=array('rw','rwv','admin');
    $g['delExportProcedure']=array('ro','rw','rwv','admin');
    $g['fullDelete']=['admin'];
    $g['procSaveExportProcedure']=array('ro','rw','rwv','admin');
    $g['procSaveDefaultExportProcedure']=array('ro','rw','rwv','admin');
    $g['display']=array('ro','rw','rwv','admin');
    $g['XMCdisplay']=['ro','rw','rwv','admin'];
    $g['edit']=array('rw','rwv','admin');
    $g['editTranslations']=array('rw','rwv','admin');
    $g['langStatus']=array('ro', 'rw','rwv','admin');
    $g['editSelection']=array('rw','rwv','admin');
    $g['editAll']=array('rw','rwv','admin');
    $g['export']=array('list','ro','rw','rwv','admin');
    $g['exportDisplay']=array('ro','rw','rwv','admin');
    $g['filledReporting']=array('list','ro','rw','rwv','admin');
    $g['filledReporting_export']=array('list','ro','rw','rwv','admin');
    $g['filledReporting_display']=array('list','ro','rw','rwv','admin');
    $g['filledReporting_browse']=array('list','ro','rw','rwv','admin');
    $g['displayJSon']=array('ro','rw','rwv','admin');
    $g['browseJSon']=array('ro','rw','rwv','admin');
    $g['exportBatch']=array('ro','rw','rwv','admin');
    $g['gDisplay']=array('admin');
    $g['insert']=array('rw','rwv','admin');
    $g['journal']=array('ro','rw','rwv','admin');
    $g['prePrintBrowse']=array('list','ro','rw','rwv','admin');
    $g['prePrintDisplay']=array('list','ro','rw','rwv','admin');
    $g['preExportBrowse']=array('list','ro','rw','rwv','admin');
    $g['preExportDisplay']=array('list','ro','rw','rwv','admin');
    $g['preFilledReporting']=array('list','ro','rw','rwv','admin');
    $g['preSubscribe']=array('list','ro','rw','rwv','admin');
    $g['printBrowse']=array('list','ro','rw','rwv','admin');
    $g['printDisplay']=array('ro','rw','rwv','admin');
    $g['procEdit']=array('rw','rwv','admin');
    $g['procEditJSon']=array('rw','rwv','admin');
    $g['ajaxProcEditCtrl']=array('rw','rwv','admin');
    $g['editDup']=array('rw','rwv','admin');
    $g['procEditDup']=array('rw','rwv','admin');
    $g['ajaxProcEditDupCtrl']=array('rw','rwv','admin');
    $g['procEditAllLang']=array('rw','rwv','admin');
    $g['procEditTranslation']=array('rw','rwv','admin');
    $g['procEditSelection']=array('rw','rwv','admin');
    $g['procInsert']=array('rw','rwv','admin');
    $g['procInsertJSon']=array('rw','rwv','admin');
    $g['ajaxProcInsertCtrl']=array('rw','rwv','admin');
    $g['procQuery']=array('list','ro','rw','rwv','admin');
    $g['procQueryFiles']=array('list','ro','rw','rwv','admin');
    $g['publish']=array('rwv','admin');
    $g['query']=array('list', 'ro','rw','rwv','admin');
    $g['quickquery']=array('list','ro','rw','rwv','admin');
    $g['prepareQuickquery']=array('list','ro','rw','rwv','admin');
    $g['adminSubscribe']=array('admin');
    $g['getUnread']=array('list','ro','rw','rwv','admin');
    $g['markAsRead']=array('list','ro','rw','rwv','admin');
    $g['preSubscribe']=array('ro','rw','rwv','admin');
    $g['saveQuery']=array('rw','rwv','admin');
    $g['procSaveQuery']=array('rw','rwv','admin');
    $g['displayJsonData']=array('ro', 'rw','rwv','admin');
    $g['getComments']=array('ro', 'rw', 'rwv', 'admin');
    $g['insertComment']=array('ro', 'rw', 'rwv', 'admin');
    $g['resetAllCommentsFromModule']=array('admin');
    $g['blogcomments']=array('rw','rwv','admin');
    $g['editBrowseProperties'] = ['list','ro','rw','rwv','admin'];
    $g['procEditBrowseProperties'] = ['list','ro','rw','rwv','admin'];
    $g['importBatch'] = ['rw','rwv','admin'];

    // suppression des droits en mise à jour pour une langue synchronisée depuis une autre
    if ($this->getLangRepli(\Seolan\Core\Shell::getLangData())){
      $g['del'] = $g['delAll'] = $g['edit'] = $g['editTranslations'] = $g['editSelection'] = $g['editAll'] = $g['insert'] = null;
      $g['procEdit'] =  $g['procEditAllLang'] = $g['procEditTranslation'] = $g['procEditSelection'] = $g['procInsert'] = null;
    }
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /* Webservice du module */
  /// Sous fonction chargée d'ajouter les types necessaires
  function _SOAPWSDLTypes(&$wsdl){
    $fields=array(array('minOccurs'=>1,'maxOccurs'=>1,'name'=>'oid','type'=>'xsd:string'));
    foreach($this->xset->desc as $n=>&$f){
      $type=$f->getSoapType();
      $fields[]=array('minOccurs'=>0,'maxOccurs'=>1,'name'=>$n,'type'=>$type['name']);
      if(!empty($type['descr'])) $this->_SOAPAddTypes($wsdl,$type['descr']);
    }
    $this->_SOAPAddTypes($wsdl,array('browseParam'=>array(array('name'=>'filter','minOccurs'=>0,'maxOccurs'=>1,'type'=>'xsd:string'),
							  array('name'=>'fields','minOccurs'=>0,'maxOccurs'=>1,'type'=>'xsd:string')),
                                     'displayParam'=>array(array('name'=>'oid','minOccurs'=>1,'maxOccurs'=>1,'type'=>'xsd:string')),
				     'displayResult'=>$fields,
				     'browseResult'=>array(array('name'=>'line','minOccurs'=>0,'maxOccurs'=>'unbounded','type'=>'tns:displayResult'))));
    return;
  }
  /// Sous fonction chargée d'ajouter les messages necessaires
  function _SOAPWSDLMessages(&$wsdl){
    $wsdl->addMessage('browseIn',array('context'=>'tns:contextParam','param'=>'tns:browseParam'));
    $wsdl->addMessage('browseOut',array('return'=>'tns:browseResult'));
    $wsdl->addMessage('displayIn',array('context'=>'tns:contextParam','param'=>'tns:displayParam'));
    $wsdl->addMessage('displayOut',array('return'=>'tns:displayResult'));
    return;
  }
  /// Sous fonction chargée d'ajouter les ports necessaires
  function _SOAPWSDLPortOps(&$wsdl,&$pt){
    $wsdl->addPortOperation($pt,'browse','tns:browseIn','tns:browseOut');
    $wsdl->addPortOperation($pt,'display','tns:displayIn','tns:displayOut');
    return;
  }
  /// Sous fonction chargée d'ajouter les operations necessaires
  function _SOAPWSDLBindingOps(&$wsdl,&$b){
    $baseUri = $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().'/SeolanService'.$this->_moid.'/';
    $bo=$wsdl->addBindingOperation($b,'browse',array('use'=>'literal'),array('use'=>'literal'));
    $o=$wsdl->addSoapOperation($bo,$baseUri.'browse');
    $o->setAttribute('style','rpc');
    $bo=$wsdl->addBindingOperation($b,'display',array('use'=>'literal'),array('use'=>'literal'));
    $o=$wsdl->addSoapOperation($bo,$baseUri.'display');
    $o->setAttribute('style','rpc');
    return;
  }
  /// Retourne l'instance qui va être associée au serveur soap
  protected function _SOAPHandler(){
    return new \Seolan\Module\Table\SoapServerHandler($this);
  }
  /// Sous fonction declarant les fonctions du module
  function _SOAPRequestFunctions(&$server) {
    return;
    function browse($context,$params){
      global $soapmod;
      $LANG_DATA = \Seolan\Core\Shell::getLangData();
      \Seolan\Core\Logs::debug("SOAPRequest function browse LANG:$LANG_DATA filter:".$params->filter." fields:".$params->fields);
      $soapmod->SOAPContext($context,'browse');
      $ar=array('tplentry'=>TZR_RETURN_DATA,'pagesize'=>999999);
      if(!empty($params->fields)){
      	if($params->fields=='all' || $params->fields=='*') $ar['selectedfields']='all';
      	else $ar['selectedfields']=explode(',',$params->fields);
      }
      if(!empty($params->filter)){
      	$translatable = $soapmod->xset->getTranslatable();

      	if(!$translatable) $LANG_DATA=TZR_DEFAULT_LANG;
      	$ar['select']='select * from '.$soapmod->table.' where LANG="'.$LANG_DATA.'" AND ('.str_ireplace(' select ','',$params->filter).')';
      }
      $ar['nocount'] = 1;
      foreach($ar['selectedfields'] as $fieldname){
	$ar['options'][$fieldname]['nofollowlinks']=1;
      }
      $br=$soapmod->browse($ar);
      $lines=array();
      foreach($br['lines_oid'] as $i=>$oid){
      	$line=array('oid'=>$oid);
      	foreach($br['header_fields'] as $j=>&$f){
      	  $line[$f->field]=$br['lines_o'.$f->field][$i]->getSoapValue();
      	}
      	$lines[]=$line;
      }
      return array('line'=>$lines);
    }
    function display($context,$params){
      global $soapmod;
      \Seolan\Core\Logs::debug("SOAPRequest function display oid:".$params->oid);
      $soapmod->SOAPContext($context,'display',$params->oid);
      $ar=array('tplentry'=>TZR_RETURN_DATA,'oid'=>$params->oid);
      $br=$soapmod->display($ar);
      $ret=array('oid'=>$br['oid']);
      foreach($br['fields_object'] as $j=>&$f){
	$ret[$f->field]=$br['o'.$f->field]->getSoapValue();
      }
      return $ret;
    }
    $server->addFunction(array('browse','display'));
  }

  /// Rend la liste des fonctions utilisables dans le gestionnaire de rubriques en mode fonction (tableau de paires fonction=>label)
  function getUIFunctionList() {
    return array('display'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','uidisplay'),
                 'procQuery'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','uiprocquery'),
                 'query'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','uiquery'),
		 'insert'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','uiinsert'));
  }

  /// Suppression du module
  function delete($ar=NULL) {
    return parent::delete($ar);
  }

  /// Initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $genlabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','general');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','multipleedit'),'multipleedit','boolean',NULL,NULL,$genlabel);
    $slabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','security');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','owner_sec'),'owner_sec','boolean',NULL,NULL,$slabel);
    $alabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','modulename');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_DataSource_DataSource','datasource'),'table','table',array('validate'=>true),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','filter'),'filter','text',['cols'=>60,'rows'=>3],NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','order'),'order','text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','quickquery'),'quickquery','boolean',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','stored_query'),'stored_query','boolean',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','pagesize'),'pagesize','text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','unfoldedgroupsnumber'),'unfoldedgroupsnumber','integer', ['default'=>1], null, $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','numberofcolumns'),'numberOfColumns','integer', ['default'=>1], null, $alabel);

    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','templates'),'templates','template',array('moid'=>$this->_moid, 'cond'=>"(gtype like '%')"),
			    NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','btemplates'),'btemplates','template',array('moid'=>$this->_moid, 'cond'=>"(gtype like '%')"),
			    NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','captcha'),'captcha','boolean',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','honeypot'),'honeypot','boolean',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','consent_field'),'consent_field','field',['table'=>'table','type'=>'\Seolan\Field\Boolean\Boolean'],NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','defaultispublished'),'defaultispublished','boolean',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','savenext'),'savenext','list',
			    array('values'=>array('standard','display','edit'),
				  'labels'=>array(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','savenext_std'),
						  \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','savenext_display'),
						  \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','savenext_edit'))),
			    NULL, $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','persistentquery'),'persistentquery','boolean',NULL,NULL,$alabel);
    $slabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','ssmod');
    $tlabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','title');
    $flabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','field');
    $ilabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','activate_additem');
    $dlabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','dependentfiles');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','submodmax'),'submodmax','integer',NULL,NULL,$slabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','submodsearch'),'submodsearch','boolean',NULL,NULL,$slabel);
    if(!empty(\Seolan\Core\Module\Module::$_mcache[$this->_moid]['MPARAM']['submodmax']))
      $this->_options->set($this,'submodmax',\Seolan\Core\Module\Module::$_mcache[$this->_moid]['MPARAM']['submodmax']);
    for($i=1;$i<=$this->submodmax;$i++) {
      $this->_options->setOpt($tlabel.' '.$i,'ssmodtitle'.$i,'ttext',NULL,'',$slabel);
      $this->_options->setOpt($flabel.' '.$i,'ssmodfield'.$i,'text',NULL,'',$slabel);
      $this->_options->setOpt($slabel.' '.$i,'ssmod'.$i,'module',array('validate'=>true),'', $slabel);
      $this->_options->setOpt($ilabel.' '.$i,'ssmodactivate_additem'.$i,'boolean',NULL,true,$slabel);
      $this->_options->setOpt($dlabel.' '.$i,'ssmoddependent'.$i,'boolean',NULL,false,$slabel);
    }
    $tlabel=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','tracking');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','trackchanges'),'trackchanges','boolean',NULL,NULL,$tlabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','trackaccess'),'trackaccess','boolean',NULL,NULL,$tlabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','archive'),'archive','boolean',NULL,NULL,$tlabel);
    if (SUB_SITE_ENABLED) {
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','filtered'), 'app_subsite_filtered', 'boolean', NULL, NULL, 'Applications');
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','filtered_disable_filter'), 'app_subsite_filtered_sql_disabled', 'boolean', NULL, NULL, 'Applications');
    }
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','clonefrombrowse'),'clonefrombrowse','boolean',NULL,false,$alabel);

    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','enable_comments'), 'allowcomments','boolean',NULL,false,$genlabel);

    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','query_comp_field'),'query_comp_field','field', NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','query_comp_field_value'),'query_comp_field_value','text', NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','query_comp_field_op'),'query_comp_field_op','text', NULL,NULL,$alabel);

    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','edit_table_mode_activated'),'EditTableModeActivated','boolean', NULL,false,$alabel);

    if ( TZR_USE_APP ){
      $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','app_context'),'activeAppContext','boolean',NULL,true,$alabel);
    }
  }

  /// Cette fonction est appliquee pour afficher l'ensemble des methodes de ce module
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my,$alfunction);
    $myclass=get_class($this);
    $moid=$this->_moid;
    $oid = $myoid = $_REQUEST['oid'] ?? '';
    $user=\Seolan\Core\User::get_user();
    $f=\Seolan\Core\Shell::_function();
    $submodcontext=NULL;
    $archiveDate = $_REQUEST['_archive']??null;

    // permet de supprimer le module même s'il plante
    try{
      if(empty($this->xset)) return ;
    }catch(\Exception $e){
      return;
    }

    $translatable = $this->xset->getTranslatable();

    // Voir l'activité récente
    if($this->secure('','activity')){
      $o1=new \Seolan\Core\Module\Action($this,'activity',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','activity'),
			    '&moid='.$moid.'&_function=activity&template=Core/Module.activity.html&tplentry=br','display');
      $o1->containable=true;
      $o1->menuable=true;
      $my['activity']=$o1;
    }

    // Parcourir
    if($this->secure('','browse')){
      // recupération du contexte sous module
      $submodcontext = $this->subModuleContext(array(), true);
      // en sous fiche, le browse est un retour à la fiche parent
      if($this->dependant_module && $submodcontext) {
        $o1=new \Seolan\Core\Module\Action($this,'browse',Labels::getTextSysLabel('Seolan_Core_General','browse'),
                                '&moid='.$this->dependant_module.'&_function=display&template=Module/Table.view.html&tplentry=br&oid='.
                                $submodcontext['_parentoids'][0].$submodcontext['urlparms'],'display');
      } else {
	       $o1=new \Seolan\Core\Module\Action($this,'browse',Labels::getTextSysLabel('Seolan_Core_General','browse'),
			      '&moid='.$moid.'&_function=browse&template=Module/Table.browse.html&tplentry=br','display');
      }
      $o1->containable=true;
      $o1->setToolbar('Seolan_Core_General','browse');
      $my['browse']=$o1;

      if ($this->EditTableModeActivated && $this->secure('',':list')){
        $o1=new \Seolan\Core\Module\Action($this,
        		     'browseSpreadsheetJSpreadsheet',
        		     Labels::getTextSysLabel('Seolan_Module_Table_Table','edit_in_table'),
        		     '&'.http_build_query(['moid'=>$moid,
        					   '_function'=>'browseForSpreadsheet',
        					   'template'=>'Module/Table.browseSpreadsheetJSpreadsheetWithVuejs.html',
        					   'tplentry'=>'br']));
        $o1->menuable=true;
        $o1->group='edit';
        $my['browseSpreadsheetJSpreadsheet']=$o1;
      }
    }

    // Recherche
    if((!$this->dependant_module || !$submodcontext) && $this->secure('','query')) {
      $o1=new \Seolan\Core\Module\Action($this,'query',Labels::getTextSysLabel('Seolan_Core_General','query'),
			    '&moid='.$moid.'&_function=query&template=Module/Table.query2.html&tplentry=br&querymode=query2','display');
      $o1->containable=true;
      $o1->setToolbar('Seolan_Core_General','query');
      $my['query']=$o1;
    }

    // Recherche en cours
    if($this->isThereAQueryActive()) {
      $o1=new \Seolan\Core\Module\Action($this,'procQuery',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','currentquery'),
			    '&moid='.$moid.'&_function=procQuery&template=Module/Table.browse.html&tplentry=br','display');
      $o1->setToolbar('Seolan_Core_General','currentquery');
      $my['procquery']=$o1;
    }

    // Insert
    $lang_data=\Seolan\Core\Shell::getLangData();
    if(TZR_LANG_FREELANG==$translatable) $sec=$this->secure($myoid,'insert',($foo=null),$lang_data);
    else if (TZR_LANG_BASEDLANG==$translatable && $lang_data!=TZR_DEFAULT_LANG) $sec=false;
    else $sec=$this->secure('','insert');
    if($sec && (!$this->dependant_module || !$submodcontext)) {
      // ajout de la langue <- si libre ok, si mode_trad, en langue de base
      $o1=new \Seolan\Core\Module\Action($this,'insert',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','new'),
			    '&LANG_DATA='.$lang_data.'&moid='.$moid.'&_function=insert&template=Module/Table.new.html&tplentry=br','edit');
      $o1->order=1;
      $o1->setToolbar('Seolan_Core_General','new');

      $my['insert']=$o1;
    }

    // Actions de navigation de fiche en fiche
    if(!empty($this->navActions)){
      foreach($this->navActions as $ak=>$o){
	$my[$ak] = $o;
      }
    }

    // Avertir
    if($this->sendacopyto && !empty($oid) && (is_array($oid) || $this->secure($oid, 'sendACopyTo'))) {
      $o1=new \Seolan\Core\Module\Action($this,'sendACopy',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','sendacopyto'),
					 '&moid='.$moid.'&tplentry=br&oid='.$oid.'&_function=sendACopyTo&template=Core/Module.sendacopyto.html&tplentry=br');
      $o1->menuable=true;
      $o1->group='more';
      $my['sendacopy']=$o1;
    }

    // Impression et export de fiche
    if(in_array($f,['display','edit'])){
      if ($this->secure($myoid,'printDisplay')) {
	$archive='null';
	if (isset($archiveDate))
	  $archive='\''.htmlspecialchars($archiveDate).'\'';
	$o1=new \Seolan\Core\Module\Action($this,'print',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','print'),
					   "javascript:TZR.Record.printselected('{$moid}','{$myoid}',{$archive});",'display');
	$o1->setToolbar('Seolan_Core_General','print');
	$my['print']=$o1;
      }
      if ($this->secure($myoid,'exportDisplay')){
	$o1=new \Seolan\Core\Module\Action($this,'export',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','export'),"javascript:TZR.Record.exportselected('$moid','$myoid');",'edit');
	$o1->menuable=true;
	$my['sexport']=$o1;
      }
    }
    // Abonnements
    $modsubmoid=\Seolan\Core\Module\Module::getMoid(XMODSUB_TOID);
    if(!empty($modsubmoid)){
      $o1=new \Seolan\Core\Module\Action($this, 'subscribe', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Subscription_Subscription','subadd'),
			    '&amoid='.$this->_moid.'&moid='.$modsubmoid.
			    '&_function=preSubscribe&tplentry=br&template=Module/Subscription.sub.html&aoid='.$myoid);
      $o1->menuable=true;
      $o1->group='more';
      $my['subscribe']=$o1;
    }

    //Import
    if($this->secure('','manage')){
      $o1=new \Seolan\Core\Module\Action($this,'manage',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','import'),
			    $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=manage&template=Core/Module.manage.html','edit');
      $o1->menuable=true;
      $my['import']=$o1;
    }

    // Voir les documents non lus
    if($this->trackaccess && $this->secure('','getUnread')){
      $o1=new \Seolan\Core\Module\Action($this,'lastdoc',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','unread'),
			    '&moid='.$this->_moid.'&_function=getUnread&tplentry=br&template=Module/Table.getUnread.html','more');
      $o1->menuable=true;
      $my['unread']=$o1;
    }

    // Regles workflow
    if($this->stored_query){
      $modrulemoid=\Seolan\Core\Module\Module::getMoid(XMODRULE_TOID);
      if(!empty($modrulemoid)){
	$o1=new \Seolan\Core\Module\Action($this, 'rule', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Workflow_Rule_Rule','addrule'),
			      '&amoid='.$this->_moid.'&moid='.$modrulemoid.
			      '&_function=insertRule&tplentry=br&template=Module/Workflow/Rule.newRule.html&atemplate=Module/Table.editSelection.html');
	$o1->menuable=true;
	$o1->group='more';
	$my['rule']=$o1;
      }
    }
    // Chemin
    if($this->interactive) {
      if($this->dependant_module && $submodcontext) {
        if ($submodcontext['_frommoids'][0] != $this->dependant_module)
          $this->dependant_module = $submodcontext['_frommoids'][0];
	$mod1=\Seolan\Core\Module\Module::objectFactory($this->dependant_module);
	$o1=new \Seolan\Core\Module\Action($this,'browse',$mod1->getLabel(),
			      '&moid='.$this->dependant_module.'&_function=browse&template=Module/Table.browse.html&tplentry=br');
	$my['stack'][]=$o1;
	$d1=$mod1->xset->rDisplayText($submodcontext['_parentoids'][0], array());
	$o1=new \Seolan\Core\Module\Action($this,'d1',$d1['link'],
			      '&moid='.$this->dependant_module.'&_function=display&template=Module/Table.view.html&tplentry=br&oid='.
			      $submodcontext['_parentoids'][0]);
	$my['stack'][]=$o1;
      } else {
	$o1=new \Seolan\Core\Module\Action($this,'browse',$this->getLabel(),
					   '&moid='.$moid.'&_function=browse&template=Module/Table.browse.html&tplentry=br','display');
	$my['stack'][]=$o1;
	if(strpos($f,'admin')===0){
	  $o1=new \Seolan\Core\Module\Action($this,'adminBrowseFields',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','browsefields').' ('.$this->xset->getTable().')',
				'&moid='.$moid.'&_function=adminBrowseFields&template=Core/Module.admin/browseFields.html','display');
	  $my['stack'][]=$o1;
	}
      }

      if(!empty($oid) && !is_array($oid)) {
	$br=\Seolan\Core\Shell::from_screen('br');
	if (!isset($br['link'])){
	  $br=$this->xset->rDisplayText($oid, array());
	}
	if($submodcontext) {
	  $o1=new \Seolan\Core\Module\Action($this,'browse',getTextFromHTML($br['link']),
				'&moid='.$moid.$submodcontext['urlparms'].
				'&_function=display&template=Module/Table.view.html&tplentry=br&oid='.$oid,'display');
	} else {
	  $o1=new \Seolan\Core\Module\Action($this,'browse',getTextFromHTML($br['link']),
				'&moid='.$moid.'&_function=display&template=Module/Table.view.html&tplentry=br&oid='.$oid,'display');
	}
	$my['stack'][]=$o1;
      }
      if ('browseTrash' == $f && isset($my['trash'])){
	$my['stack'][]=$my['trash'];
      }
    }

    if (in_array($f, ['display', 'edit']) && $this->secure($myoid, 'displayJsonData')
      && \Seolan\Core\Json::hasInterfaceConfig() && \Seolan\Core\Json::getModuleAlias($this->_moid)) {
        $o1                    = new \Seolan\Core\Module\Action($this, 'displayJsonData', 'JSON API Object', 'javascript:TZR.Record.displayJsonData('.$this->_moid.', \''.$myoid.'\');');
        $o1->menuable          = true;
        $o1->group             = 'more';
        $my['displayJsonData'] = $o1;
    }

    $this->resetCommentsActionList($my);

  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=browse&tplentry=br&template=Module/Table.browse.html&_persistent=1';
  }

  /**
   * Construit la liste des actions à rendre disponible lors de l'affichage d'une fiche (display)
   * @param &$my array() Liste de \Seolan\Core\Module\Action
   */
  function al_display(&$my){
    $uniqid= \Seolan\Core\Shell::uniqid();
    $moid=$this->_moid;
    $myoid=$_REQUEST['oid']??'';
    if ($this->secure($myoid, 'edit')) {
      // recuperation contexte sous module
      $submodcontext=$this->subModuleContext();
      $o1=new \Seolan\Core\Module\Action($this,'edit',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','edit'),
                                         '&function=edit&moid='.$moid.'&template=Module/Table.edit.html&tplentry=br&oid='.$myoid.($submodcontext['urlparms']??''),'edit');
      $o1->order=2;
      $o1->setToolbar('Seolan_Core_General','edit');
      $o1->type='primary';
      $o1->actionable = true;
      $my['edit']=$o1;
    }
    // duplication en langue de base (voir al_edit et al_browse aussi)
    if ($this->secure('', 'editDup')
	&& TZR_DEFAULT_LANG == \Seolan\Core\Shell::getLangData()
	&& !$this->translationMode(new \Seolan\Core\Param([]))) {
      // duplication en langue de base (voir al_edit et al_browse aussi)
      $o1=new \Seolan\Core\Module\Action($this,'clone',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','clone'),
					 '&LANG_DATA='.TZR_DEFAULT_LANG.'&function=editDup&moid='.$moid.'&template=Module/Table.edit.html&tplentry=br&oid='.$myoid);
	$o1->menuable=true;
	$o1->group='edit';
	$o1->type='default';
	$o1->actionable = true;
	$my['clone']=$o1;
    }
    if ($this->secure($myoid ,'del')) {
      $o1=new \Seolan\Core\Module\Action($this,'del',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete'),'javascript:TZR.Record.delete("'.$uniqid.'","'.$moid.'","del","'.$myoid.'");','edit');
      $o1->order=3;
      $o1->setToolbar('Seolan_Core_General','delete');
      $o1->type='default';
      $o1->actionable = true;
      $my['del']=$o1;
    }
  }

  /**
   * Construit la liste des actions à rendre disponible lors de la préparation à l'édition d'une fiche (edit)
   * @param &$my array() Liste de \Seolan\Core\Module\Action
   */
  function al_edit(&$my){
    $uniqid = \Seolan\Core\Shell::uniqid();
    $myoid=$_REQUEST['oid']??'';
    $moid=$this->_moid;

    $o1=new \Seolan\Core\Module\Action($this,'save',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','save'),'javascript:TZR.Record.save("'.$uniqid.'");','edit');
    $o1->order=1;
    $o1->setToolbar('Seolan_Core_General','save');
    $my['save']=$o1;

    // recuperation contexte sous module
    $submodcontext=$this->subModuleContext();
    $o1=new \Seolan\Core\Module\Action($this,'display',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','display'),
                                       '&function=display&moid='.$this->_moid.'&template=Module/Table.view.html&tplentry=br&oid='.$myoid.($submodcontext['urlparms']??''),
			  'edit');
    $o1->order=2;
    $o1->setToolbar('Seolan_Core_General','display');
    $my['display']=$o1;

    $sec=$this->secure($myoid,'del');
    if($sec){
      $o1=new \Seolan\Core\Module\Action($this,'del',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete'),"javascript:TZR.Record.delete('$uniqid','$moid','del','$myoid');",'edit');
      $o1->order=3;
      $o1->setToolbar('Seolan_Core_General','delete');
      $my['del']=$o1;
    }

    // duplication en langue de base (voir al_edit et al_browse)
    if ($this->secure('', 'editDup')
	&& TZR_DEFAULT_LANG == \Seolan\Core\Shell::getLangData()
	&& !$this->translationMode(new \Seolan\Core\Param([]))){
      $o1=new \Seolan\Core\Module\Action($this,'clone',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','clone'),
					 '&LANG_DATA='.TZR_DEFAULT_LANG.'&function=editDup&moid='.$this->_moid.'&template=Module/Table.edit.html&tplentry=br&oid='.$myoid);
      $o1->menuable=true;
      $o1->group='edit';
      $my['clone']=$o1;
    }

  }
  /**
   * Construit la liste des actions à rendre disponible lors de l'édition d'une fiche (procEdit)
   * @param &$my array() Liste de \Seolan\Core\Module\Action
   */
  function al_procEdit(&$my){
    $this->al_edit($my);
  }

  /**
   * Construit la liste des actions à rendre disponible lors de la duplication d'une fiche (procEditDup)
   * @param &$my array() Liste de \Seolan\Core\Module\Action
   */
  function al_procEditDup(&$my){
    $this->al_insert($my);
  }

  /**
   * Construit la liste des actions à rendre disponible lors de la préparation à l'insertion d'une fiche (insert)
   * @param &$my array() Liste de \Seolan\Core\Module\Action
   */
  function al_insert(&$my){
    $uniqid = \Seolan\Core\Shell::uniqid();
    $o1=new \Seolan\Core\Module\Action($this,'save',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','save'),"javascript:TZR.Record.save('$uniqid','procInsert');",'edit');
    $o1->order=1;
    $o1->setToolbar('Seolan_Core_General','save');
    $my['save']=$o1;

  }

  /**
   * Construit la liste des actions à rendre disponible lors de l'insertion d'une fiche (procInsert)
   * @param &$my array() Liste de \Seolan\Core\Module\Action
   */
  function al_procInsert(&$my){
    $this->al_insert($my);
  }

  /**
   * Construit la liste des actions à rendre disponible lors du listing des fiches du module (browse)
   * @param &$my array() Liste de \Seolan\Core\Module\Action
   */
  function al_browse(&$my){
    $uniqid = \Seolan\Core\Shell::uniqid();
    $moid=$this->_moid;
    $myoid=$_REQUEST['oid'] ?? '';
    if($this->secure($myoid,'del')){
      $o1=new \Seolan\Core\Module\Action($this,'del',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete'),"javascript:TZR.Table.deleteselected('$uniqid','del');",'edit');
      $o1->order=3;
      $o1->setToolbar('Seolan_Core_General','delete');
      $my['del']=$o1;
    }

    if($this->objectSecurityEnabled() && $this->secure($myoid,'secEditSimple')){
      $o1=new \Seolan\Core\Module\Action($this,'seceditsimple',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','security'),
					 'javascript:TZR.editSec("'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true).'","'.$this->_moid.'",null,{uniqid:"'.$uniqid.'"});','edit');
      $o1->order=4;
      $o1->setToolbar('Seolan_Core_General','security');
      $my['secEditSimple']=$o1;
    }

    if(isset($this->xset->desc['PUBLISH'])){
      if($this->secure($myoid,'publish')){
	$o1=new \Seolan\Core\Module\Action($this,'approve',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','approve'),
      'javascript:TZR.Table.applyfunction("'.$uniqid.'","publish","",{value:1});','edit');
	$o1->menuable=true;
	$my['approve']=$o1;
	$o1=new \Seolan\Core\Module\Action($this,'unapprove',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','unapprove'),
      'javascript:TZR.Table.applyfunction("'.$uniqid.'","publish","",{value:2});','edit');
	$o1->menuable=true;
	$my['unapprove']=$o1;
      }
    }

    $o1=new \Seolan\Core\Module\Action($this,'pgmore',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','page_size').' * 2',
			  'javascript:TZR.Table.updateBrowseProperties("'.$uniqid.'","pagesize",{pagesizediff:\'*2\'});','display');
    $o1->menuable=true;
    $my['pgmore']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'pgless',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','page_size').' / 2',
			  'javascript:TZR.Table.updateBrowseProperties("'.$uniqid.'","pagesize",{pagesizediff:\'/2\'});','display');
    $o1->menuable=true;
    $my['pgless']=$o1;

    if($this->multipleedit && $this->secure('','editSelection')){
      $o1=new \Seolan\Core\Module\Action($this,'editselection',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','editselection'),
        'javascript:TZR.Table.applyfunction("'.$uniqid.'","editSelection","",{template:"Module/Table.editSelection.html"},true,true);','edit');
      $o1->order=2;
      $o1->setToolbar('Seolan_Core_General','edit');
      $my['editselection']=$o1;
    }

    // Edition/suppression sur le resultat d'une recherche
    if($this->isThereAQueryActive()){
      if($this->secure('','editAll')){
	$o1=new \Seolan\Core\Module\Action($this,'editall',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','editall'),
			      'javascript:TZR.Table.applyfunction("'.$uniqid.'","editAll","",{template:"Module/Table.editSelection.html"},false,true);','edit');
	$o1->order=2;
	$o1->menuable=true;
	$my['editall']=$o1;
      }
      if($this->secure('','delAll')){
	$o1=new \Seolan\Core\Module\Action($this,'delall',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delall'),
			      "javascript:TZR.Table.deletequeried('$uniqid','delAll');",'edit');
	$o1->order=3;
	$o1->menuable=true;
	$my['delall']=$o1;
      }
    }
    // export d'une liste
    if($this->secure($myoid,'export')){
      $o1=new \Seolan\Core\Module\Action($this,'export',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','export'),"javascript:TZR.Table.exportselected('$uniqid');",
					 'edit');
      $o1->menuable=true;
      $my['sexport']=$o1;
    }
    // reporting
    if($this->secure($myoid,'filledReporting_display')){
      $o1=new \Seolan\Core\Module\Action($this,'filledReporting_display',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','filledreporting'),"javascript:TZR.Table.filledreporting('$uniqid');",'edit');
      $o1->menuable=true;
      $my['sexportrep']=$o1;
    }
    // print d'une liste
    if($this->secure($myoid,'printBrowse')){
      $o1=new \Seolan\Core\Module\Action($this,'print',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','print'),"javascript:TZR.Table.printselected('$uniqid');",'display');
      $o1->setToolbar('Seolan_Core_General','print');
      $my['print']=$o1;
    }
    // avertir
    if ($this->sendacopyto && $this->secure('', 'sendACopyTo')){
      $o1=new \Seolan\Core\Module\Action($this,'sendACopy',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','sendacopyto'),
					 'javascript:TZR.Table.applyfunction("'.$uniqid.'","sendACopyTo","",{template:"Core/Module.sendacopyto.html"},true,true);');
      $o1->menuable=true;
      $o1->group='more';
      $my['sendacopy']=$o1;
    }
    // Suppression complète des objets selectionnés
    if ($this->archive && $this->secure('', 'fullDelete')){
      $label = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','full_delete');
      $o1=new \Seolan\Core\Module\Action($this,
					 'fullDelete',
					 $label,
					 "javascript:TZR.Table.deleteSelectedComplete('{$uniqid}', '{$label}');", 'edit'
      );
      $o1->menuable=true;
      $my['fullDelete']=$o1;
    }
  }

  /**
   * Construit la liste des actions à rendre disponible lors d'une recherche sur les fiches du module (procQuery)
   * @param &$my array() Liste de \Seolan\Core\Module\Action
   */
  function al_procQuery(&$my){
    $this->al_browse($my);
  }

  function al_filledReporting_browse(&$my){
    $this->al_browse($my);
  }

  function nav($ar=NULL){
    parent::nav($ar);
    if(substr(\Seolan\Core\Shell::_function(),0,5)=='admin') {
      $this->pushNav(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','administration'),
		     '&function=adminBrowseFields&moid='.$this->_moid.'&template=Core/Module.admin/browseFields.html&boid='.$this->boid);
    }
  }


  function isDependant() {
    return \Seolan\Core\DbIni::get('dependant:'.$this->_moid,'val');
  }

  /// Preparartion des donnees pour ecran de parametrage d'impression
  public function prePrintBrowse($ar, $unsetLinkfield = true) {
    $p=new \Seolan\Core\Param($ar,[], "all",
                              ['pagesize'=>array(FILTER_VALIDATE_INT,[]),
			       'order'=>[FILTER_CALLBACK,['options'=>'containsNoSQLKeyword']]]);
    $tplentry=$p->get('tplentry');
    $ar['order']=$order=$p->get('order')??$this->order;
    $ar['table']=$this->table;
    // Recherche des templates d'impression
    if(empty($this->_templates)) $this->_templates=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
    if(!empty($this->_templates)) {
      $q1=$this->_templates->select_query(array('cond'=>array('modid'=>array('=',$this->_moid),
							      'gtype'=>array('=','xmodtable_browse_print'))));
      $r=$this->_templates->browse(array('select'=>$q1,'pagesize'=>100,'tplentry'=>TZR_RETURN_DATA));
      \Seolan\Core\Shell::toScreen1($tplentry.'t',$r);
    }
    // recherche des donnees
    $ar['_filter']=$this->getFilter(true,$ar);
    $ar['tplentry']=TZR_RETURN_DATA;
    $ar['fieldssec']=$this->getFieldsSec($ar);
    if (empty($ar['selectedfields']))
      $ar['selectedfields'] = 'all';
    $ar['pagesize']=1;
    $ar['ssmoid']='all';
    for($i=1;$i<=$this->submodmax;$i++) {
      $f='ssmod'.$i;
      $ar['options'][$this->$f]['selectedfields']='all';
    }

    // cas module ayant lui meme en sous modules
    $mycurrentquery = NULL;
    if($this->isThereAQueryActive())
      $mycurrentquery = $this->_getSession('query');

    $comp_filter = $this->getCompulsoryFilter($ar);

    if (!empty($comp_filter['filter'])) {
      if (empty($filter)) {
        $filter = $comp_filter['filter'];
      } else {
        $filter = '('.$filter.') AND ('.$comp_filter['filter'].')';
      }
    }

    $r=$this->xset->browse($ar);

    //Récupération de la stucture des tables liées à la table principale
    foreach ($r['header_fields'] as $i=> $field) {
      // devrait être fait avec Core\Fiel : getgender et cie ...
      if ((is_a($field, \Seolan\Field\Link\Link::class) || is_a($field, \Seolan\Field\DependentLink\DependentLink::class)) && $field->target != '%'){
        $targetfields = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($field->target);
        $r['header_fields'][$i]->targetfields = $targetfields->desc;
	$r['header_fields'][$i]->_tofollow = true;
      }
    }

    $this->setSubModules($ar,$r, $unsetLinkfield);

    //Récupération de la stucture des tables liées aux tables des sous modules
    foreach ($r['__ssmod'] as $i => $submod) {
      foreach ($submod['header_fields'] as $j=>$field) {
	if ((is_a($field, \Seolan\Field\Link\Link::class) || is_a($field, \Seolan\Field\DependentLink\DependentLink::class)) && $field->target != '%'){
          $targetds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($field->target);
          $r['__ssmod'][$i]['header_fields'][$j]->targetfields = $targetds->desc;
          $r['__ssmod'][$i]['header_fields'][$j]->_tofollow = true;
        }
      }
    }

    if ($mycurrentquery != NULL)
       $this->_setSession('query', $mycurrentquery);

    // calcul du nombre d'enregistrements impactes (passage d'oid, browseSelection ou browse/query classique)
    $r['_selected']=$p->get('_selected');
    if(is_array($r['_selected'])) {
      $r['record_count']=count($r['_selected']);
    }elseif($p->get('fromfunction')=='browseSelection') {
      $selection=getSessionVar('selection');
      $r['_selected']=$selection[$this->_moid];
      $r['record_count']=count($r['_selected']);
    }else{
      $context=$this->getContextQuery($ar,false);
      $ar['select']=$context['query'];
      $q=$this->xset->getSelectQuery($ar);
      $r['record_count']=getDB()->count($q[1],array(),true);
      $r['queryfields']=$context['all']['queryfields'];
    }

    if (!empty($r['queryfields'])){
        $r['proposedfilename'] = $this->getLabel().' ';
        $i = 0;
	$l_count=count($r['queryfields']);
        while($i<2 && $i<$l_count && strlen($r['proposedfilename'])<=40){
	    $field=array_values($r['queryfields'])[$i];
            $r['proposedfilename'] .= $field->fielddef->label.' '.$field->getQueryText();
            $i++;
        }
        if (strlen($r['proposedfilename']) >= 40) {
	  $r['proposedfilename'] = $this->getLabel().' ';
        }
        $r['proposedfilename'] .= ' '.date('Ymd His');
        $r['proposedfilename'] = strtoupper(rewriteToFilename($r['proposedfilename'], true));
    } else {
      $r['proposedfilename'] = strtoupper(rewriteToFilename($this->getLabel(), true)).' '.date('Ymd His');
    }

    // Ajout des champs des sous modules multitables
    $j=0;
    for($i=1;$i<=$this->submodmax;$i++) {
      $f='ssmod'.$i;
      $moid=$this->$f;
      if(!empty($moid)) {
        if(!empty($r['__ssmod'][$j])) {
          $submod = \Seolan\Core\Module\Module::objectFactory($moid);
          if(is_a($submod, "\Seolan\Module\MultiTable\MultiTable")) {
            // compteurs par types
            if(is_array($r['_selected'])) {
              $oids = array_keys($r['_selected']);
            }
            elseif($p->get('fromfunction') == 'browseSelection') {
              $selection = getSessionVar('selection');
              $oids = array_keys($selection[$this->_moid]);
            }
            else {
              $oids = getDb()->fetchCol(str_replace($this->table . '.*', $this->table . '.KOID', $ar['select']));
            }
            $filterOids = '';
            if($oids) {
              $filterOids = ' and ' . $this->{'ssmodfield' . $i} . ' in ("' . implode('","', $oids) . '")';
            }
            $countQuery = 'select ' . $submod->typeField . ' as type, count(distinct KOID) as nb from ' . $submod->table . ' where LANG=? ' . $filterOids . ' group by 1';
            $r['__ssmod'][$j]['record_countDetails'] = getDb()->select($countQuery, [\Seolan\Core\Shell::getLangData()])->fetchAll();
            $colon = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'i18ncolon');
            foreach($r['__ssmod'][$j]['record_countDetails'] as &$detail) {
              $detail['html'] = $submod->typeDescs[$detail['type']]->label . $colon . $detail['nb'];
              if($submod->typeDescs[$detail['type']]->datasource) {
                $detail['tablename'] = $submod->typeDescs[$detail['type']]->datasource->getTable();
              }
            }
            // champs des types sélectionnés
            $r['__ssmod'][$j]['header_fields'] = [];
            foreach($submod->allorddescs as $fn) {
	      $modfield = $submod->alldescs[$fn];
              if($modfield->table == $submod->table || in_array($modfield->table, array_column($r['__ssmod'][$j]['record_countDetails'], 'tablename'))) {

                //Récupération de la stucture des sous tables liées
		if ((is_a($modfield, \Seolan\Field\Link\Link::class)
		     || is_a($modfield, \Seolan\Field\DependentLink\DependentLink::class)) && $modfield->target != '%'){
                  $targetfields = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($modfield->target);
                  $modfield->targetfields = $targetfields->desc;
                }
                $r['__ssmod'][$j]['header_fields'][] = $modfield;
              }
            }
          }
        }
        $j++;
      }
    }
    // liste des procedures d'export enregistrées
    $r['_procedures'] = $this->browseExportProcedures();

    // chargement des paramètres si procédure demandée
    if ($p->is_set('storedprocedureid') && $p->get('storedprocedureid')){
      $r['_storedprocedure'] = $this->prepareExportProcedure($p->get('storedprocedureid'));
    }
    else {
      $r['_storedprocedure'] = $this->prepareDefaultExportProcedure();
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  /// Retourne la requete permettant de recuperer tous les objets suivant le contexte (selection, recherche en cours...)
  function getContextQuery($ar,$queryonly=true){
    $p=new \Seolan\Core\Param($ar,array());
    $oidsel=$p->get('_selected');
    $noreg=$p->get('noreg');
    $from=$p->get('fromfunction');
    if(is_array($oidsel)) {
      $oid=array_keys($oidsel);
      $q=$this->xset->select_query(array('cond'=>array('KOID'=>array('=',$oid))));
    }elseif($this->isThereAQueryActive() && ($from=='procQuery')) {
      $_storedquery=$this->_getSession('query');
      $ar1=array_merge($_storedquery,$ar);
      if($queryonly){
	$ar1['getselectonly']=true;
	$q=$this->xset->procQuery($ar1);
      }else{
	$ar1['getselectonly']=false;
	$ar1['pagesize']=1;
	$r=$this->xset->procQuery($ar1);
	$q=$r['select'];
      }
      if(!$noreg)
        $q = preg_replace('@select (.*) from (.*)$@iU','select '.$this->xset->get_sqlSelectFields('*',$this->table).' from $2',$q);
    }else{
      $q=$this->xset->select_query(array("cond"=>array()));
    }
    if($queryonly) return $q;
    else return array('query'=>$q,'all'=>$r);
  }

  /// Impression sur un browse
  public function printBrowse($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $target=$p->get('_target');
    $linkedfield=$p->get('_linkedfield');
    $fmt=$p->get('fmt');
    $context=$this->getContextQuery($ar,false);
    $q=$context['query'];
    $ar['selected']='0';
    $ar['pagesize']='100000';
    $ar['select']=$q;
    $ar['_format']='text/html';
    $ar['tplentry']=TZR_RETURN_DATA;
    $oldinteractive=$this->interactive;
    $this->interactive=false;
    if(!empty($target) && $target!=$this->_moid){
      $mod=\Seolan\Core\Module\Module::objectFactory($target);
      if($mod->secure('','export')){
	$ar2=$ar;
	$ar['selectedfields']=array('xx');
	$ar['order']=$this->order;
	$b=$this->browse($ar);
	$q='select * from '.$mod->table.' where '.$linkedfield.' in ("'.implode('","',$b['lines_oid']).'")';
	$ar2['select']=$q;
	if($fmt=='pdf')	$ar2['_format']='application/prince';
	$res=$mod->browse($ar2);
      }
    }else{
      if($fmt=='pdf') $ar['_format']='application/prince';
      $res=$this->browse($ar);
      $mod=&$this;
    }
    $this->interactive=$oldinteractive;
    if($fmt=='pdf') {
      // Impression PDF par defaut
      if(!empty($this->_templates) && !empty($this->btemplates)) {
        $r=$this->_templates->display(array('oid'=>$this->btemplates,'_options'=>array('error'=>'return'),'tplentry'=>TZR_RETURN_DATA));
        if(!empty($r['oprintp']->filename)) $filename=$r['oprintp']->filename;
      }
      $this->_printBrowsePDF($ar,$filename);
    }elseif(\Seolan\Core\Kernel::isAKoid($fmt)) {
      // Impression via un template d'impressions
      $t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$fmt);
      $dispfmt=$t->rDisplay($fmt);
      $displayformats=explode(',',$dispfmt['odfmt']->raw);
      if(in_array('text/html',$displayformats) || empty($dispfmt['odfmt']->raw)) $this->_printBrowseHTML($ar,$dispfmt['oprint']->filename);
      elseif(in_array('application/prince',$displayformats)) $this->_printBrowsePDF($ar,$dispfmt['oprint']->filename);
    }else{
      // Impression HTML par defaut
      $template=$p->get('template');
      $this->_printBrowseHTML($ar,$template);
    }
  }
  /// Sous fonction d'impression au format HTML
  function _printBrowseHTML($ar=NULL,$templateName=NULL, $filename=null){
    $p=new \Seolan\Core\Param($ar,['filename'=>$this->getLabel().' '.date('Ymd His')]);
    $filename = $p->get('filename');
    $email=$p->get('dest');
    if(empty($templateName))
      $templateName= $this->getTemplate('xmodtable/print.html', 'Module/Table.print.html');
    $content=$this->_printGetContent($ar,$templateName,'browse');
    // send content as mail string attachment
    if(!empty($email))
      $this->sendMail2User($this->getLabel(),
			   '',$email,NULL,false,NULL,
			   rewriteToFilename($filename).'.html',
			   $content);
    header('Content-type: text/html');
    header('Content-disposition: attachment; filename="'.rewriteToFilename($filename).'.html"');
    header('Accept-Ranges: bytes');
    header('Content-Length: '.strlen($content));
    echo $content;
    exit(0);
  }
  /// Sous fonction d'impression au format PDF
  function _printBrowsePDF($ar=NULL,$filename=NULL){
    $p=new \Seolan\Core\Param($ar,array('pdfname'=>'browse.pdf'));
    $email=$p->get('dest');
    if(empty($filename)){
      $filename = $this->getTemplate('xmodtable/print.xml','Module/Table.print.xml');
    }
    $ar['_format']='application/prince';
    $content=$this->_printGetContent($ar,$filename,'browse');
    $tmpname=princeTidyXML2PDF(NULL,$content);
    $pdfname=rewriteToFilename(trim($p->get('pdfname')));
    if(empty($pdfname)){
      $pdfname = rewriteToFilename($this->getLabel().' '.date('Ymd His').'.pdf');
    }
    if (substr(strtolower($pdfname), -4) != '.pdf')
      $pdfname = $pdfname.'.pdf';
    // send content as mail string attachment
    if(!empty($email) && !empty($tmpname)) {
      $content=file_get_contents($tmpname);
      $this->sendMail2User($this->getLabel(),'',$email,NULL,false,NULL,$pdfname,$content,'application/pdf');
    }
    header('Content-type: application/pdf');
    header('Content-disposition: attachment; filename="'.$pdfname);
    $size=filesize($tmpname);
    header('Accept-Ranges: bytes');
    header('Content-Length: '.$size);
    readfile($tmpname);
    unlink($tmpname);
    exit(0);
  }
  /// Impression d'une fiche
  public function printDisplay($ar) {
    $p=new \Seolan\Core\Param($ar,array('pdfname'=>'view.pdf'));
    $ar['tplentry']=TZR_RETURN_DATA;
    $ar['ssmoid']='all';
    $fmt=$p->get('fmt');
    if($fmt=='pdf') {
      // Impression PDF par defaut
      if(!empty($this->_templates) && !empty($this->templates)) {
	$r=$this->_templates->display(array('oid'=>$this->templates,'_options'=>array('error'=>'return'),'tplentry'=>TZR_RETURN_DATA));
	if(!empty($r['oprintp']->filename)) $filename=$r['oprintp']->filename;
      }
      $this->_printDisplayPDF($ar,$filename);
    }elseif(\Seolan\Core\Kernel::isAKoid($fmt)) {
      // Impression via un template d'impressions
      $t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$fmt);
      $dispfmt=$t->display(array('oid'=>$fmt,'tplentry'=>TZR_RETURN_DATA));
      $displayformats=explode(',',$dispfmt['odfmt']->raw);
      if(in_array('text/html',$displayformats) || empty($dispfmt['odfmt']->raw)) $this->_printDisplayHTML($ar,$dispfmt['oprint']->filename);
      elseif(in_array('application/prince',$displayformats)) $this->_printDisplayPDF($ar,$dispfmt['oprint']->filename);
    }else{
      // Impression HTML par defaut
      $template=$p->get('template');
      $this->_printDisplayHTML($ar,$template);
    }
  }
  /// Sous fonction d'impression au format HTML
  function _printDisplayHTML($ar=NULL,$filename=NULL){
    $p=new \Seolan\Core\Param($ar,['htmlname'=>$this->getLabel().'.html']);
    $email=$p->get('dest');
    $htmlname = $p->get('htmlname');
    if (substr(strtolower($htmlname), -5) != '.html')
      $htmlname = $htmlname.'.html';
    if(empty($filename))
      $filename='Module/Table.printdisplay.html';
    $ar['com'] = null;
    if ($this->allowcomments){
      $ar['com']=$this->getComments($ar);
      \Seolan\Core\Labels::loadLabels('Seolan_Module_Comment_Comment');
    }
    \Seolan\Core\Labels::loadLabels('Seolan_Core_General');
    if ( $this->object_sec && $this->secure($p->get('oid'), ':rw')){
      $xuser1=&$GLOBALS['XUSER'];
      $ar['acls'] = $xuser1->listObjectAccess($this,
					      \Seolan\Core\Shell::getLangData(),
					      $p->get('oid'),
					      true);
    } else {
      $ar['acls'] = null;
    }

    $content=$this->_printGetContent($ar,$filename,'display');
    if(!empty($email))
      $this->sendMail2User($this->getLabel(),$htmlname,$email,NULL,false,NULL,rewriteToFilename($htmlname),$content);


    header('Content-type: text/html');
    header('Content-disposition: attachment; filename="'.rewriteToFilename($filename).'.html"');
    header('Accept-Ranges: bytes');
    header('Content-Length: '.strlen($content));
    echo $content;
    exit(0);
  }
  /// Sous fonction d'impression au format PDF
  function _printDisplayPDF($ar=NULL,$filename=NULL){
    $p=new \Seolan\Core\Param($ar,array('pdfname'=>$this->getLabel().'.pdf'));
    $email=$p->get('dest');
    if(empty($filename)){
      $filename = $this->getTemplate('xmodtable/print-view.xml', 'Module/Table.print-view.xml');
    }
    $ar['_format']='application/prince';
    $content=$this->_printGetContent($ar,$filename,'display');
    $tmpname=princeTidyXML2PDF(NULL,$content);
    $pdfname=rewriteToFilename(trim($p->get('pdfname')));
    if(empty($pdfname)){
        $pdfname = rewriteToFilename($this->getLabel().' '.date('Ymd His'));
    }
    if (substr(strtolower($pdfname), -4) != '.pdf')
      $pdfname = $pdfname.'.pdf';
    if(!empty($email) && !empty($tmpname)) {
      $content=file_get_contents($tmpname);
      $this->sendMail2User($this->getLabel(),$pdfname,$email,NULL,false,NULL,$pdfname,$content,'application/pdf');
    }

    header('Content-type: application/pdf');
    header("Content-disposition: attachment; filename=\"$pdfname\"");
    $size=filesize($tmpname);
    header('Accept-Ranges: bytes');
    header('Content-Length: '.$size);
    readfile($tmpname);
    unlink($tmpname);
    exit(0);
  }
  /// Recupere le contenu pour une impression
  function &_printGetContent($ar,$filename,$f,$tpldata=array()){
    $p=new \Seolan\Core\Param($ar,NULL);
    $title=$p->get('title');
    $res=$this->$f($ar);
    $res['com']=$ar['com'];
    $res['acls'] = $ar['acls'];
    $xt=new \Seolan\Core\Template('file:'.$filename);
    $labels=&$GLOBALS['XSHELL']->labels->get_labels(array('selectors'=>array('global'),'local'=>true));
    $xt->set_glob(array('labels'=>&$labels));
    $r3=[];
    $tpldata['param']=array('title'=>$title);
    $tpldata['br']=$res;
    $tpldata['imod']=\Seolan\Core\Shell::from_screen('imod');
    $content=$xt->parse($tpldata,$r3,NULL);
    return $content;
  }

  /// Exporte une fiche
  public function exportDisplay($ar=NULL){
    $p= new \Seolan\Core\Param($ar,array());
    $fmt=$p->get('fmt');
    $ar['norow']=1;
    $ar['nodef']=1;
    $ar['ssmoid']='all';
    $ar['com']=$this->getComments($ar);
    $ar['_options']=array('genpublishtag'=>false);
    if($fmt=='xl' || $fmt=='xl07') $ar['_format']='application/excel';
    $this->display($ar);
    if($fmt=='xl' || $fmt=='xl07')  $this->_exportXLSDisplay($ar);
    if($fmt=='csv')  $this->_exportCSVDisplay($ar);
  }

  /// Exporte une fiche au format excel
  public function _exportXLSDisplay($ar){
    $p=new \Seolan\Core\Param($ar,NULL);
    $fmt=$p->get('fmt');
    $br=\Seolan\Core\Shell::from_screen('br');
    $br['com']=$ar['com'];
    $ss=new \PHPExcel();
    $ss->setActiveSheetIndex(0);
    $ws=$ss->getActiveSheet();
    $ws->setTitle('Main');
    $ws->SetCellValue('A1','OID');
    $ws->SetCellValue('B1',$br['oid']);
    $row=2;
    foreach($br['fields_object'] as $j => &$f) {
      if(!$f->sys){
	$l=$f->fielddef->label;
	$c=$f->field;
	convert_charset($l,TZR_INTERNAL_CHARSET,'UTF-8');
	$ws->setCellValueByColumnAndRow(0,$row,$l);
	$f1=$this->xset->getField($c);
	$f1->writeXLS($ws,$row++,1,$br['o'.$c],0,$ss);
      }
    }
    $ws->setCellValue('A7',$br['com']['lines_oOWN'][0]->fielddef->label);
    $ws->setCellValue('A8',$br['com']['lines_oCREAD'][0]->fielddef->label);
    $ws->setCellValue('A9',$br['com']['lines_oCOMMENTAIRE'][0]->fielddef->label);
    $ws->getStyle('A7:A9')->getFont()->setBold(true);
    $ws->getDefaultRowDimension()->setRowHeight(20); // hauteur de toutes les cellules pour avoir un rendu plus lisible
    foreach(range('A','Z') as $columnID) {
    $ws->getColumnDimension($columnID)
        ->setAutoSize(true);
    }
    foreach(range('A','Z') as $a){$alpha[]=$a;} // tableau contenant les lettres alphabetique
    for($va=1;$va<count($br['com']['lines_oOWN'])+1;$va++){

            $ws->setCellValue($alpha[$va].($var+7),$br['com']['lines_oOWN'][$va-1]->toText());

            $ws->setCellValue($alpha[$va].($var+8),$br['com']['lines_oCREAD'][$va-1]->toText());

            $ws->setCellValue($alpha[$va].($var+9),$br['com']['lines_oCOMMENTAIRE'][$va-1]->toText());

    }

    $ws->getStyle('A1:A'.($row-1))->getFont()->setBold(true);

    foreach($br['__ssmod'] as $i=>&$ssr){
      $l=$br['__ssprops'][$i]['modulename'];
      convert_charset($l,TZR_INTERNAL_CHARSET,'UTF-8');
      $ss->createSheet($i+1);
      $ss->setActiveSheetIndex($i+1);
      $ws=$ss->getActiveSheet();
      $ws->setTitle(mb_substr(preg_replace("@[\[\]\*\?:\\/']@", '', $l), 0, 31));
      foreach($ssr['header_fields'] as $j => &$f) {
	$l=$ssr['header_fields'][$j]->label;
	convert_charset($l,TZR_INTERNAL_CHARSET,'UTF-8');
	$ws->setCellValueByColumnAndRow($j,1,$l);
      }
      foreach($ssr['lines_oid'] as $j=>$oid){
	foreach($ssr['header_fields'] as $k=>&$f) {
	  $f->writeXLS($ws,$j+2,$k+1,$ssr['lines_o'.$f->field][$j],0,$ss);
	}
      }
      $ws->getStyle('A1:'.\PHPExcel_Cell::stringFromColumnIndex($k).'1')->getFont()->setBold(true);
    }
    $ss->setActiveSheetIndex(0);
    sendPHPExcelFile($ss,$fmt,'export');
  }
  /// Exporte une fiche et ses sous fiches au format csv (si sous fiches, un fichier csv par sous modules, le tout dans un zip)
  public function _exportCSVDisplay($ar=NULL){
    $p= new \Seolan\Core\Param($ar,array('fname'=>'Export'));
    $fsep=$p->get('csvfsep');
    $textsep=stripslashes($p->get('csvtextsep'));
    $charset=stripslashes($p->get('csvcharset'));
    $fname=$p->get('fname');
    $br=\Seolan\Core\Shell::from_screen('br');
    $br['com']=$ar['com'];
    $csv=$headers=$row=array();
    foreach($br['fields_object'] as $j => &$f) {
      if(!$f->sys){
	$f1=$this->xset->getField($f->field);
	$headers[]=$textsep.$f->fielddef->label.$textsep;
	$row[]=$f1->writeCSV($br['o'.$f->field],$textsep);
      }
    }
    for($var=0;$var<count($br['com']['lines_oOWN']);$var++){

        $headers[]=$textsep.$br['com']['lines_oOWN'][$var]->fielddef->label.$textsep;
        $row[]=$textsep.$br['com']['lines_oOWN'][$var]->toText().$textsep;

        $headers[]=$textsep.$br['com']['lines_oCREAD'][$var]->fielddef->label.$textsep;
        $row[]=$textsep.$br['com']['lines_oCREAD'][$var]->toText().$textsep;

        $headers[]=$textsep.$br['com']['lines_oCOMMENTAIRE'][$var]->fielddef->label.$textsep;
        $row[]=$textsep.$br['com']['lines_oCOMMENTAIRE'][$var]->toText().$textsep;

    }
    $csv[]=implode($fsep,$headers);
    $csv[]=implode($fsep,$row);
    $csv=implode("\r\n",$csv);
    convert_charset($csv,TZR_INTERNAL_CHARSET,$charset);
    if(empty($br['__ssmod'])){
      ob_clean();
      header('Content-Type: text/csv; charset='.$charset);
      header('Content-Transfer-Encoding:'.$charset);
      header('Content-disposition: attachment; filename='.str_replace(' ','_',removeaccents($fname)).'.csv');
      header('Content-Length: '.strlen($csv));
      echo $csv;
      exit(0);
    }else{
      $dir=TZR_TMP_DIR.'exportdisp'.uniqid();
      @mkdir($dir);
      file_put_contents($dir.'/Main.csv',$csv);
      foreach($br['__ssmod'] as $i=>&$ssr){
	$csv=$headers=$row=array();
	$l=$br['__ssprops'][$i]['modulename'];
	foreach($ssr['header_fields'] as $j=>&$f) {
	  $headers[]=$textsep.$f->label.$textsep;
	}
	$csv[]=implode($fsep,$headers);
	foreach($ssr['lines_oid'] as $j=>$oid){
	  $row=array();
	  foreach($ssr['header_fields'] as $k=>&$f) {
	    $row[]=$f->writeCSV($ssr['lines_o'.$f->field][$j],$textsep);
	  }
	  $csv[]=implode($fsep,$row);
	}
	$csv=implode("\r\n",$csv);
	convert_charset($csv,TZR_INTERNAL_CHARSET,$charset);
	file_put_contents($dir.'/'.removeaccents($l).'.csv',$csv);
      }
      exec('(cd '.$dir.'; zip -r '.$dir.'.zip .)2>&1 > '.TZR_TMP_DIR.'errorlog');
      $size=filesize($dir.'.zip');
      header('Content-type: application/zip');
      header('Content-disposition: attachment; filename='.str_replace(' ','_',removeaccents($fname)).'.zip');
      header('Accept-Ranges: bytes');
      header('Content-Length: '.$size);
      @readfile($dir.'.zip');
      \Seolan\Library\Dir::unlink($dir);
      unlink($dir.'.zip');
      exit(0);
    }
  }

  function filledReporting($ar) {
    ini_set('max_execution_time', 0);
    $p = new Param($ar);
    $langs = $p->get('langs') ?: array(TZR_DEFAULT_LANG);
    $query = $this->_getSession('reporting_query') ?: array();
    $query['fromfunction'] = $_REQUEST['fromfunction'] = 'procQuery';
    $select = $this->getContextQuery($query);
    $select = preg_replace('/^select '.$this->table.'\.\* from/i', 'select count(1) from', $select);
    $countTotal = getDB()->fetchOne($select);
    $desc = $this->alldescs ?: $this->xset->desc;
    $data = array(
      'query' => array(),
      'data' => array()
    );

    foreach($desc as $field_name => $field) {
      if(!$field->isQueryEmpty($query)) {
        $field_name = ($query && count($query['_FIELDS'])) ? array_search($field_name, $query['_FIELDS']) : $field_name;
        $o = $field->_newXFieldQuery();
        $o->value = $query[$field_name];
        $o->empty = $query[$field_name.'_empty'];
        $o->op = $query[$field_name.'_op'];
        $o->hid = $query[$field_name.'_HID'];
        $o->fmt = $query[$field_name.'_FMT'];
        $o->par = $query[$field_name.'_PAR'];
        $field->post_query($o, $query);
        $data['query'][$field->label] = $field->getQueryText($o);
      }
    }

    $selectedfields = $p->get('selectedfields');
    foreach($langs as $lang) {
      Shell::setLang($lang);
      $data['data'][$lang] = array();
      foreach($selectedfields as $selectedfield) {
        $count = $this->filledReporting_getCount($selectedfield, $query);
        $group = $desc[$selectedfield]->fgroup ?: \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','general');
        $label = $desc[$selectedfield]->label;
        $label = $group . ' - ' . $label;
        $data['data'][$lang][] = array(
          'field' => $selectedfield,
          'label' => $label,
          'count' => $count,
          'countTotal' => $countTotal
        );
      }
    }

    return Shell::toScreen2('filled', 'reporting', $data);
  }

  function filledReporting_getCount($field, $query) {
    $fieldObj = $this->xset->desc[$field];
    if($fieldObj && $fieldObj->isQueryEmpty($query)) {
      $query[$field . "_op"] = "is not empty";
      $query['_FIELDS'][$field] = $field;
      $this->_setSession('query', $query);
    }
    $select = $this->getContextQuery($query);
    $select = preg_replace('/^select '.$this->table.'\.\* from/i', 'select count(1) from', $select);

    return getDB()->fetchOne($select);
  }

  function filledReporting_display($ar) {
    $data = $this->filledReporting($ar);

    $p = new Param($ar);
    $this->_setSession('filledReporting_langs', $p->get('langs'));
    $this->_setSession('filledReporting_selectedfields', $p->get('selectedfields'));

    Shell::changeTemplate('Module/Table.filledreporting_display.html');
  }

  function filledReporting_browse($ar) {
    $p = new Param($ar);

    $lang = $p->get('lang');
    if($lang) {
      Shell::setLang($lang);
    }

    $query = $this->_getSession('reporting_query') ?: array();
    $field = $p->get('field');
    $fieldObj = $this->xset->desc[$field];
    if($fieldObj && $fieldObj->isQueryEmpty($query)) {
      $query[$field . "_op"] = "is empty";
      $query['_FIELDS'][$field] = $field;
      $this->_setSession('query', $query);
    }

    $GLOBALS['XSHELL']->_function = $query['fromfunction'] = $_REQUEST['fromfunction'] = 'procQuery';
    return $this->procQuery($query);
  }

  function filledReporting_export($ar) {
    $ar['langs'] = $this->_getSession('filledReporting_langs');
    $ar['selectedfields'] = $this->_getSession('filledReporting_selectedfields');
    $data = $this->filledReporting($ar);

    $ss = new \PHPExcel();
    $sheetNumber = 0;
    $ss->setActiveSheetIndex($sheetNumber);
    $ws = $ss->getActiveSheet();
    $ws->setTitle('Query');
    $ws->SetCellValue('A1', Labels::getTextSysLabel('Seolan_Module_Table_Table','filledreporting_field'));
    $ws->SetCellValue('B1', Labels::getTextSysLabel('Seolan_Module_Table_Table','filledreporting_value'));
    $ws->getStyle('A1:B1')->getFont()->setBold(true);
    $row = 1;
    foreach($data['query'] as $field_name => $field) {
      $ws->setCellValueByColumnAndRow(0, ++$row, $field_name);
      $ws->setCellValueByColumnAndRow(1, $row, $field);
    }

    foreach($data['data'] as $lang => $fields) {
      $ss->createSheet(++$sheetNumber);
      $ss->setActiveSheetIndex($sheetNumber);
      $ws = $ss->getActiveSheet();
      $ws->setTitle($lang);
      $row = 1;
      $ws->setCellValueByColumnAndRow(0, $row, Labels::getTextSysLabel('Seolan_Module_Table_Table','filledreporting_field'));
      $ws->setCellValueByColumnAndRow(1, $row, Labels::getTextSysLabel('Seolan_Module_Table_Table','filledreporting_filled'));
      $ws->setCellValueByColumnAndRow(2, $row, Labels::getTextSysLabel('Seolan_Module_Table_Table','filledreporting_total'));
      $ws->setCellValueByColumnAndRow(3, $row, Labels::getTextSysLabel('Seolan_Module_Table_Table','filledreporting_percent'));
      $ws->getStyle('A1:D1')->getFont()->setBold(true);
      foreach($fields as $field) {
        $ws->setCellValueByColumnAndRow(0, ++$row, $field['label']);
        $ws->setCellValueByColumnAndRow(1, $row, $field['count']);
        $ws->setCellValueByColumnAndRow(2, $row, $field['countTotal']);
        $ws->setCellValueByColumnAndRow(3, $row, "=B$row/C$row");
        $ws->getStyleByColumnAndRow(3, $row)->getNumberFormat()->applyFromArray([
          "code" => \PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE
        ]);
      }
    }
    $ss->setActiveSheetIndex(0);
    sendPHPExcelFile($ss, 'xl07', 'reporting');
  }

  /// Suppression de toutes les entrées correspondant à la recherche en cours
  function delAll($ar) {
    $p=new \Seolan\Core\Param($ar,NULL);
    if($this->isThereAQueryActive() && empty($clearrequest)) {
      $_storedquery=$this->_getSession('query');
      $ar=array_merge($_storedquery,$ar);
      $ar['fmoid']=$this->_moid;
      $ar['tplentry']=TZR_RETURN_DATA;
      $ar['selectedfields']=array('KOID');
      $ar['_filter']=$this->getFilter(true,$ar);
      $ar['pagesize']=999999;
      $r1=$this->xset->procQuery($ar);
      if(count($r1['lines_oid'])){
	$this->del(array('oid'=>$r1['lines_oid']));
      }
    }
  }
  /**
   * Suppression 'physique' / 'complete'
   * -> suppression std + purge immédiate des archives
   * la fonction n'est activée que si $this->achive===true
   */
  function fullDelete($ar){
    $p = new \Seolan\Core\Param($ar, []);
    $oid=\Seolan\Core\Kernel::getSelectedOids($p,true,false);
    if(is_array($oid)){
      foreach($oid as $toid){
        $ar['oid']=$toid;
        $ar['_selectedok']=$ar['_selected']='';
        $this->fullDelete($ar);
      }
      return true;
    }
    $ar['_fullDelete'] = true;
    $this->del($ar);
  }
  /// Suppression d'un objet ou d'un ensemble d'objet
  function del($ar) {
    $p=new \Seolan\Core\Param($ar,array('onlyssm'=>false,'ssmnottodel'=>[],'_fullDelete'=>false));
    $oid=\Seolan\Core\Kernel::getSelectedOids($p,true,false);
    if(is_array($oid)){
      foreach($oid as $toid){
        $ar['oid']=$toid;
        $ar['_selectedok']=$ar['_selected']='';
        $this->del($ar);
      }
      return true;
    }
    $fullDelete = $p->get('_fullDelete', 'local');
    $nolog=$p->get('_nolog','local');
    $onlyssm=$p->get('onlyssm');
    $noworkflow=$p->get('_noworkflow');
    $ssmnottodel=$p->get('ssmnottodel');
    $ar['table']=$this->table;
    $ar['action']='OK';

    // traitement du contexte sous module
    $subMods = $this->getSubModules();
    /// application du workflow
    if(empty($noworkflow) && \Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID)) {
      $umod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
      $umod->checkAndRun($this, $this, $oid, 'delete');
    }
    foreach($subMods as $subMod) {
      // si sous module dépendant, on supprime toutes les sous-fiches en relation avec cette fiche
      // si sous module avec lien obl et multiple (donc pas considéré comme dependant), on edite ou supprime toutes les sous-fiches
      if(in_array($subMod['moid'],$ssmnottodel)) continue;
      $im = $subMod['ssmodindex'];
      if(!empty($subMod['mod']->dependant_module) || $this->{'ssmoddependent'.$im}) {
        $sel=$subMod['xset']->select_query(array('cond'=>array($subMod['linkfield']=>array('=',$oid))));
        $ssRecords=$subMod['xset']->browse(array('select'=>$sel, 'tplentry'=>TZR_RETURN_DATA,
                                                 'selected'=>'0','selectedfields'=>[$subMod['linkfield']]));
        if(!empty($ssRecords)) {
          foreach($ssRecords['lines_oid'] as $i=>$ssoid){
            $subMod['mod']->del(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$ssoid,'_selectedok'=>'','_selected'=>''));
          }
        }
      }else{
        $sel=$subMod['xset']->select_query(array('cond'=>array($subMod['linkfield']=>array('LIKE','%'.$oid.'%'))));
        $ssRecords=$subMod['xset']->browse(array('select'=>$sel,'tplentry'=>TZR_RETURN_DATA,
                                                 'selected'=>'0',
						 'selectedfields'=>[$subMod['linkfield']]));
        if(!empty($ssRecords)) {
          foreach($ssRecords['lines_oid'] as $i=>$ssoid){
            $links=$ssRecords['lines_o'.$subMod['linkfield']][$i]->raw;
            if($subMod['xset']->desc[$subMod['linkfield']]->compulsory && preg_match("/^\|*".$oid."\|*$/",$links)){
              $subMod['mod']->del(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$ssoid,'_selectedok'=>'','_selected'=>'','_nolog'=>$nolog));
            }else{
	      \Seolan\Core\Logs::debug(__METHOD__." mise à jour du champs {$subMod['linkfield']} $oid $links");
              $subMod['mod']->procEdit(array('tplentry'=>TZR_RETURN_DATA,'oid'=>$ssoid,'_nolog'=>$nolog,
                                             $subMod['linkfield']=>preg_replace("/$oid((\|\|)|$)/",'',$links)));
            }
          }
        }
      }
    }
    // corbeille
    if ($this->usetrash && !$fullDelete){
      $trashmoid = $p->get('_trashmoid','local');
      $trashuser = $p->get('_trashuser','local');
      $ar['_movetotrash'] = true;
      $ar['_trashmoid'] = $trashmoid ?? $this->_moid;
      $ar['_trashuser'] = $trashuser ??\Seolan\Core\User::get_current_user_uid();
      $ar['_trashdata'] = $p->get('_trashdata','local');
    }

    if(!$onlyssm) $this->xset->del($ar);
    $this->updateTasks($ar, $oid, 'del');
    return true;
  }

  /// suppression par api, un seul oid possible
  public function delJSon($ar) {
    if (!$this->displayJSon($ar)) {
      $GLOBALS[JSON_START_CLASS]::registerError(404, 'entity not found');
      return;
    }
    $this->del($ar);
    header('HTTP/1.1 204 No Content');
  }

  /// Positionne si necessaire l'oid dans le tri pour dedoublonner
  function checkOrderFields($order){
    $t=$this->xset->getTable();
    if(empty($order)) $order=$this->order;
    if (is_array($order))
      $decorder = $order;
    else
      $decorder=explode(',',$order);
    $allDesc = true;
    $koidFound = false;
    foreach ($decorder as $_order) {
      $allDesc &= (bool)strripos($_order, ' DESC'); // permet d'utiliser les index
      $koidFound |= (stripos($_order, 'KOID') !== false);
    }
    if (!$koidFound)
      $decorder[] = $t.'.KOID' . ($allDesc?' DESC':'');
    return implode(',',$decorder);
  }

  /// Contruction des actions de navigation
  function setNavActions($myoid,$navfunction,$navtemplate, $submodcontext=NULL){
    $moid=$this->_moid;

    // paramètres dans le cas de sous modules
    $cplssmod = '';
    if($submodcontext != NULL){
      $cplssmod = '&'.$submodcontext['urlparms'];
    }

    $o1=new \Seolan\Core\Module\Action($this,'navprev',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','previous'),
			  '&moid='.$moid.'&_function='.$navfunction.'&template='.$navtemplate.'&tplentry=br&oid='.$myoid.'&navdir=prev'.$cplssmod,
			  'display');
    $o1->setToolbar('Seolan_Core_General', 'previous');
    $this->navActions['prev']=$o1;

    $o1=new \Seolan\Core\Module\Action($this,'navnext',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','next'),
			  '&moid='.$moid.'&_function='.$navfunction.'&template='.$navtemplate.'&tplentry=br&oid='.$myoid.'&navdir=next'.$cplssmod,
			  'display');
    $o1->setToolbar('Seolan_Core_General', 'next');
    $this->navActions['next']=$o1;
  }

  /// Recherche des infos sur la position d'un oid dans un browse. Retourn : array(oid precedent,oid suivant, oid actuel, prec le premier, suiv le dernier)
  function mkNavParms($ar, $submodcontext=NULL){
    $p=new \Seolan\Core\Param($ar, array());
    $oid=$p->get('oid');
    // Lecture de l'ordre en cour
    if($this->_issetSession('lastorder')) $order=$this->_getSession('lastorder');
    if(empty($order)) $order=$this->order;

    $ar['order']=$order;
    $ar['table']=$this->table;
    $ar['_filter']=$this->getFilter(true,$ar);

    // contexte sous module : restreindre à ce parent (perfectible ?)
    if ($submodcontext != NULL){
      $ssmfilter = $submodcontext['_linkedfields'][0].' like "%'.$submodcontext['_parentoids'][0].'%"';
      $ar['_filter'] .= empty($ar['_filter'])?$ssmfilter:' and '.$ssmfilter;
    }

    // Cas ou on a une requete
    if($this->isThereAQueryActive()){
      $_storedquery=$this->_getSession('query');
      $ar2=array_merge($ar, $_storedquery);
      $ar2['getselectonly']=true;
      $q=$this->xset->procQuery($ar2);
      // garder les commentaires
      $ar['select']=preg_replace('/([^\/])\*([^\/])/', '$1KOID$2', $q);
    }
    $order=$this->checkOrderFields($order);
    $oids=$this->xset->browseOids($ar);
    // Appliquer les droits si necessaire
    if($this->object_sec) {
      $lang_data=\Seolan\Core\Shell::getLangData();
      $oidsrights=$GLOBALS['XUSER']->getObjectsAccess($this,$lang_data,$oids);
      foreach($oidsrights as $i=>&$rights) {
        if(!array_key_exists('ro',$rights)) unset($oids[$i]);
      }
    }
    if($oid) $myi=array_search($oid,$oids);
    else $myi=0;
    $mylst=count($oids)-1;
    if($myi==0) $navprev=$oids[$mylst];
    else $navprev=$oids[$myi-1];
    if($myi==$mylst) $navnext=$oids[0];
    else $navnext = $oids[$myi+1];
    $navact=$oids[$myi];
    return array($navprev,$navnext,$navact,$myi-1==0,$myi+1==$mylst);
  }
  /// liste des prop. gérées dans les préférences utilisateur pour le browse
  protected static function getBrowsePropertiesNames(){
    return [['selectedfields',null],
	    ['order',null],
	    ['selectedqqfields', null],
	    ['quickquery_open', 'quick_query_open'],
	    ['quickquery_submodsearch', 'quick_query_submodsearch'],
	    ['pagesize',null]];
  }
  /// Lecture des options du browse
  protected function prepareBrowseParameters($ar=[]){
    $p = new \Seolan\Core\Param($ar, []);
    if ($this->_issetSession('browseproperties'))
      $browseProperties = $this->_getSession('browseproperties');
    else
      $browseProperties = [];
    if ($this->saveUserPref)
      $prefs = $this->getBrowseUserPrefs();
    else
      $prefs = [];

    foreach(static::getBrowsePropertiesNames() as list($name,$prefname)){
      // cas du pagesize ... 0
      if ($p->is_set($name) && $p->get($name)!=='0')
	continue;
      if (!isset($browseProperties[$name]) && isset($prefs[$name]))
	$browseProperties[$name] = $prefs[$name];
      if (isset($browseProperties[$name]))
	$ar[$name] = $browseProperties[$name];
    }
    return $ar;
  }
  /**
   * Propriétes associées au browse
   * un champ selectionné quickquery est d'office en liste
   * un champ en liste et queryable peut être enlevé du quickquery
   */
  public function editBrowseProperties($ar=null){
    $p = new Param($ar, ['tplentry'=>'bp']);
    $r = ['fields'=>[],'properties'=>null];

    $browseProperties = $this->prepareBrowseParameters();

    if ($p->is_set('_reset')){
      $browseProperties = [];
    }
    if (!isset($browseProperties['selectedfields'])
	|| !isset($browseProperties['selectedqqfields'])){
      $browsables = $this->xset->getFieldsList(null,true);
      if ($this->quickquery)
	$queryables = $this->xset->getFieldsList(null,false,false,true/*queryables*/);
      else
	$queryables = [];
      $browseProperties['selectedfields']=$browsables;
      $browseProperties['selectedqqfields']=array_intersect($browsables, $queryables);
    }
    if (!isset($browseProperties['quickquery_open']))
      $browseProperties['quickquery_open']=0;
    if (!isset($browseProperties['quickquery_submodsearch']))
      $browseProperties['quickquery_submodsearch']=($this->submodsearch==1);
    if (!isset($browseProperties['pagesize']))
      $browseProperties['pagesize']=$this->pagesize;

    // droits sur les champs : ne pas montrer les champs qui n'apparaitrons pas en browse

    $fieldsnames = $this->xset->getFieldsList(null,false,false,false); //
    // !! voir Core\DataSource::browse
    $order = [];
    $syslabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','properties');
    foreach($this->xset->orddesc as $fn){
      $field = $this->xset->getField($fn);
      if($field->sys)
	$fgroup = $syslabel;
      elseif (empty($field->fgroup))
	$fgroup = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','general');
      else
	$fgroup = $field->fgroup;
      if (!isset($r['fields'][$fgroup])){
	$r['fields'][$fgroup]=[];
	if ($fgroup != $syslabel)
	  $order[] = strtolower($fgroup);
	else
	  $order[] = 'z';
      }
      // quickquery inactif : pas de champs searchable
      if (!$this->quickquery)
	$field->queryable=0;
      $qqcomplusory = false;
      if (isset($this->query_comp_field) && $this->query_comp_field == $fn)
	$qqcomplusory = true;

      $r['fields'][$fgroup][$fn] = ['status'=>[
	'ro'=>$qqcomplusory,
	'compulsory'=>$qqcomplusory,
	'selected'=>$qqcomplusory || in_array($fn, $browseProperties['selectedfields']),
	'qqueryselected'=>$qqcomplusory || in_array($fn, $browseProperties['selectedqqfields']),
      ],
				    'object'=>$field];
    }
    array_multisort($order, SORT_ASC, $r['fields'], SORT_ASC );
    // préférences actuellement sauvegardées pour ce module
    $r['browseid']=$p->get('browseid');
    $r['properties']=$browseProperties;
    if (isset($browseProperties['order']) && $browseProperties['oder'] != $this->order){
      $orderText = [];
      $lab['asc'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','pref_order_asc');
      $lab['desc'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','pref_order_desc');
      foreach(explode(',', $browseProperties['order']) as $oexp){
	list($fn, $forder) = explode(' ', $oexp);
	$fn = preg_replace('/^.+\./', '', trim($fn));
	$forder = trim($forder);
	if ($fn =='KOID')
	  continue;
	$orderText[] = $this->xset->getField($fn)->label.' '.$lab[strtolower($forder)];
      }
      if (!empty($orderText))
	$r['properties']['orderText'] = implode(',', $orderText);
    }
    return Shell::toScreen1($p->get('tplentry'), $r);
  }
  /**
   * mémorise les options du browse, qui sont des "surcharges locales"
   * de prop. du module ou de présentation
   * appele pour le form complet ou une des prop (order, pagesize)
   */
  public function procEditBrowseProperties($ar=null){
    $p = new Param($ar, ['quickquery_open'=>0,
			 'quickquery_submodsearch'=>0,
			 'pagesize'=>0,
			 'propsnames'=>'*'],
		   "all",
		   ['quickquery_open'=>[FILTER_VALIDATE_INT,[]],
		    'quickquery_submodsearch'=>[FILTER_VALIDATE_INT,[]],
		    'pagesize'=>[FILTER_VALIDATE_INT,[]],
		    'order'=>[FILTER_CALLBACK,['options'=>'containsNoSQLKeyword']]
		   ]);
    $propsnames = $p->get('propsnames');
    $statusselector = $p->get('fieldstatus');
    $newBrowseProperties  = [];
    foreach(static::getBrowsePropertiesNames() as list($name)){
      if ($propsnames == '*' || (is_array($propsnames) && in_array($name, $propsnames))){
	$newBrowseProperties[$name] = $p->get($name);
      }
    }
    // props. qui doivent d'abord être activées au niveau module
    foreach(['quickquery','submodsearch'] as $pn){
      if (!$this->$pn)
	$newBrowseProperties["quickquery_$pn"] = 0;
    }
    if ($propsnames == '*'){
      foreach($this->xset->orddesc as $fn){
      	$status = $statusselector[$fn]??null;
      	switch($status){
      	  case 'search':
      	    $newBrowseProperties['selectedqqfields'][]=$fn;
      	  case 'browse':
      	    $newBrowseProperties['selectedfields'][]=$fn;
      	    break;
      	}
      }
    }

    $this->saveBrowseProperties($newBrowseProperties);

    if ($p->is_set('_ajax')){
      die('');
    } else {
      // en tests, en pratique, => ça doit raffraichir la page en cours de la console
      unset($ar);
      $ar['tplentry'] = 'bp';
      Shell::changeTemplate('Module/Table.modalBrowseProperties.html');
      return $this->editBrowseProperties();

      Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true,true).http_build_query(['moid'=>$this->_moid,
												 'tplentry'=>'bp',
												 'function'=>'editBrowseProperties',
												 'template'=>'Module/Table.modalBrowseProperties.html',
												 '_skip'=>1]));
    }
  }
  /// mémorisation des propriétés du browse, en session, en user prefs si actif
  protected function saveBrowseProperties($props){
    if ($this->_issetSession('browseproperties'))
      $browseProperties = $this->_getSession('browseproperties');
    else
      $browseProperties = [];
    foreach(static::getBrowsePropertiesNames() as list($name,$prefname)){
      if (isset($props[$name]))
	$browseProperties[$name] = $props[$name];
    }

    $this->_setSession('browseproperties', $browseProperties);

    if ($this->saveUserPref)
      $this->saveBrowseUserPrefs($browseProperties);

  }
  /// Surcharge du browse pour l'édition en tableau
  public function browseForSpreadsheet($ar){
    $ar['without_actions'] = true;
    $r = static::browse($ar);
    $r['actions'] = [];
    foreach($r['lines_oid'] as $i =>$oid) {
      $oidlvl=array_keys($r['objects_sec'][$i]);
      $this->browseActionsForSpreadsheetLine($r,$i,$oid,$oidlvl,$noeditoids);
    }
    Shell::toScreen2('br','actions', $r['actions']);
    return $r;
  }
  /// Actions pour une edition en tableau d'une ligne
  protected function browseActionsForSpreadsheetLine(&$r,$i,$oid,$oidlvl,$noeditoids){
    static $viewtext, $viewico, $edittext, $editico, $deltext, $delico = null;
    if ($viewtext == null){
      $viewtext = $this->browseActionViewText();
      $viewico = $this->browseActionViewIco();
      $edittext = $this->browseActionEditText();
      $editico = $this->browseActionEditIco();
      $deltext = $this->browseActionDelText();
      $delico = $this->browseActionDelIco();
    }
    $actions = [];
    $uniqid = \Seolan\Core\Shell::uniqid();
    foreach($oidlvl as $level){

      switch ($level){
        case 'ro':
        // display
        $actions[] = "<a href=\"#\"".
        " onclick=\"TZR.JSpreadsheet.displayLineDetails.call(TZR.JSpreadsheet, '{$oid}', '{$uniqid}');return false;\" ".
        " title=\"{$viewtext}\" class=\"btn btn-view-line\">{$viewico}</a>";
        break;
        case 'rw':
          if (!in_array($oid, $noeditoids)){
            // edit
            $actions[] = "<a href=\"#\"".
            " onclick=\"TZR.JSpreadsheet.editLineDetails.call(TZR.JSpreadsheet, '{$oid}', '{$uniqid}');return false;\" ".
            " title=\"{$edittext}\" class=\"btn btn-edit-line\">{$editico}</a>";
            // delete
            $actions[] = "<a href=\"#\"".
            " onclick=\"TZR.JSpreadsheet.deleteLine.call(TZR.JSpreadsheet, '{$oid}', '{$uniqid}');return false;\" ".
            " title=\"{$deltext}\" class=\"btn btn-delete-line\">{$delico}</a>";
          }
          break;
      }
    }
    $r['actions'][$i] = $actions;
  }
  /// Prépare l'ensemble des éléments d'affichage des fiches
  public function browse($ar) {

    if(\Seolan\Core\Shell::admini_mode()){
      $ar = $this->prepareBrowseParameters($ar);
    }
    $p=new \Seolan\Core\Param($ar,['order'=>0],
			      'all',
                              ['pagesize'=>[FILTER_VALIDATE_INT,[]],
			       'order'=>[FILTER_CALLBACK,['options'=>'containsNoSQLKeyword']]]);

    $select=$p->get('select','norequest');
    $tplentry=$p->get('tplentry');
    $editfields=$p->get('editfields');
    $persistent=$p->get('_persistent');
    $assubmodule=$p->get('assubmodule');

    $order=$this->checkOrderFields($p->get('order'));

    if($this->persistentquery && $persistent) clearSessionVar('filterquery'.$this->_moid);
    // pour gestion de la navigation de page/page en display et edit
    if($this->interactive){
      $this->_setSession('lastorder',$order);
      if($this->isThereAQueryActive()) $this->_clearSession('query');
    }

    $ar['order']=$order;
    $ar['table']=$this->table;

    $pagesize = $p->get('pagesize');
    if(empty($pagesize)) $pagesize = $this->pagesize;
    if(empty($pagesize)) $pagesize = TZR_XMODTABLE_BROWSE_MAXPAGESIZE;
    $ar['pagesize'] = $pagesize;

    if(!empty($this->_templates) && !empty($this->btemplates)) {
      $r=$this->_templates->display(array('oid'=>$this->btemplates,'_options'=>array('error'=>'return', 'local'=>true),'tplentry'=>TZR_RETURN_DATA));
      \Seolan\Core\Shell::toScreen1($tplentry.'t',$r);
    }

    // recherche des donnees
    $ar['_filter']=$this->getFilter(true,$ar);

    //S'il y a un filtre obligatoire alors on force l'affichage du champ.
    if ($this->hasCompulsoryFilter()) {
      if(count($p->get('selectedfields')) && empty($ar['selectedfields'])) {
        $ar['selectedfields'] = array_filter($_REQUEST['selectedfields'], function($item) {
          return $item !== 'undefined';
        });
      }

      if (empty($ar['selectedfields'])) {
        foreach ($this->xset->desc as $field_name => $field) {
          if ($field_name === $this->query_comp_field || (int)$field->browsable === 1) {
            $ar['selectedfields'][] = $field_name;
          }
        }
      } elseif (is_array($ar['selectedfields']) && !in_array($this->query_comp_field, $ar['selectedfields'], true)) {
        $selectedfields = [];
        foreach ($this->xset->desc as $field_name => $field) {
          if ($field_name === $this->query_comp_field || in_array($field_name, $ar['selectedfields'], true)) {
            $selectedfields[] = $field_name;
          }
        }
        $ar['selectedfields'] = $selectedfields;
        unset($selectedfields);
      }
    }

    $translation_mode = $this->translationMode($p);
    if($this->object_sec) {
      // rem : browseoids tient compte du mode traduction (getSelectQuery)
      $oids=$this->xset->browseOids($ar);
      $noeditoids=array();
      $rolist=static::getRoList();

      // calcul des droits sur les objets retournés
      $lang_data = \Seolan\Core\Shell::getLangData();
      $oidsrights=$GLOBALS['XUSER']->getObjectsAccess($this, $lang_data, $oids);
      if ($translation_mode){
	$lang_trad_oidsrights = $GLOBALS['XUSER']->getObjectsAccess($this, $translation_mode->LANG_TRAD, $oids);
	$lang_trad_oidsrights2 = array();
      }
      // on ajoute les droits en langue de base aux droits en lang data
      foreach($oidsrights as $i => $rights) {
	$intersect=array_intersect($rolist,array_flip($rights));
	// en mode traduction on combine avec les droits dans les 2 langues
	if (empty($intersect) && $translation_mode){
	  $intersect=array_intersect($rolist,array_flip($lang_trad_oidsrights[$i]));
	}
	if(empty($intersect)){
	  unset($oids[$i]);
	} else {
	  $oidsrights2[$oids[$i]]=$rights;
	  if(!empty($editfields) && !array_key_exists('rw',$rights)) $noeditoids[]=$oids[$i];
	  if ($translation_mode){
	    $lang_trad_oidsrights2[$oids[$i]] = $lang_trad_oidsrights[$i];
	  }
	}
      }
      if(!empty($oids)){
	if(preg_match('/order[ ]+by/i',$select)) $order='field(KOID,"'.implode('","',$oids).'")';
	else $order=$ar['order'];
	// en mode traduction, requete en langue de traduction (lang def)
	if ($translation_mode){
	  $qlang_other = $translation_mode->LANG_TRAD;
	} else {
	  $qlang_other = null;
	}
	$ar['select']=$this->xset->select_query(array('order'=>$order,
						      'cond'=>array('KOID'=>array('=',$oids)),
						      'LANG_OTHER'=>$qlang_other
						      )
						);
	$ar['noeditoids']=$noeditoids;
      } else {
	$ar['select']='SELECT * FROM '.$this->table.' WHERE 0';
      }
    }

    $ar['tplentry']=TZR_RETURN_DATA;
    if(empty($ar['fmoid']))
      $ar['fmoid']=$this->_moid;
    $ar['fieldssec']=$this->getFieldsSec($ar);

    $r=$this->xset->browse($ar);

    $r['function']='browse';
    $r['_fieldssec']=$ar['fieldssec'];
    $r['queryfields']=[];

    //Pour affichage du filtre automatique
    $oFieldQuery = $this->getCompulsoryFilter($ar)['field_query'];
    if (!empty($oFieldQuery) && !empty($this->query_comp_field_value)) {
      $r['queryfields'] = [$this->query_comp_field => $oFieldQuery];
    }

    if ($translation_mode){
      $r['lang_trad'] = $translation_mode->LANG_TRAD;
    }
    if($this->object_sec){
      $r['objects_sec']=array();
      foreach($r['lines_oid'] as $i=>$oid){
	$r['objects_sec'][$i]=$oidsrights2[$oid];
	if ($translation_mode){
	  $r['objects_sec_trad'][$i] = $lang_trad_oidsrights2[$oid];
	}
      }
    }else{
      $lang_data = \Seolan\Core\Shell::getLangData();
      $r['objects_sec']=$GLOBALS['XUSER']->getObjectsAccess($this, $lang_data, $r['lines_oid']);
    }
    if(\Seolan\Core\Shell::admini_mode()) {
      // passage du contexte sous module
      $submodcontext = $this->subModuleContext($ar);
      $r['urlparms'] = @$submodcontext['urlparms'];
      if (!$p->get('without_actions')){
	$this->browse_actions($r, $assubmodule, $ar);
      }
      if ($this->interactive){
	// pour bloquer l'edition multiple
	if (($langsrc = $this->getLangRepli(($lang_data = \Seolan\Core\Shell::getLangData())))){
	  $o=\Seolan\Core\Shell::from_screen('imod','props');
	  $o['multipleedit'] = false;
	  \Seolan\Core\Shell::toScreen2('imod', 'props', $o);
	  setSessionVar('message', sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','content_propagate'),  ($lang_data_text = \Seolan\Core\Lang::get($lang_data)['text']), ($lang_text = \Seolan\Core\Lang::get($langsrc)['text']), $lang_data_text, $lang_text));
	}
      }
    }
    if (!isset($ar['_browsesumfields']) || $ar['_browsesumfields'] != false)
      $this->browseSumFields($ar, $r, true);

    $r['pagesize'] = $ar['pagesize'];
    $r['quickquery_open'] = $p->get('quickquery_open');
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);

  }
  /// Parcours le module pour la selection d'un fichier
  public function &browseFiles($ar) {
    $fields = array();
    //cinq champ publié max et les champ fichier
    foreach($this->xset->desc as $fname=>$fdef){
      if( (count($fields) < 5 && $fdef->get_published()) || ($fdef->get_ftype() == '\Seolan\Field\File\File' || $fdef->get_ftype() == '\Seolan\Field\Image\Image' || $fdef->get_ftype() == '\Seolan\Field\Video\Video')){
	$fields[]=$fname;
      }
    }
    $ar['selectedfields']=$fields;
    return $this->browse($ar);
  }

  /// Preparartion des donnees pour ecran de parametrage d'impression
  public function prePrintDisplay($ar) {
    $p = new \Seolan\Core\Param($ar,array(),'all',
                                array('pagesize'=>array(FILTER_VALIDATE_INT,array()),
                                      'order'=>array(FILTER_CALLBACK,array('options'=>'containsNoSQLKeyword'))));

    $order=$p->get('order');
    $tplentry=$p->get('tplentry');
    if(empty($order)) $order=$this->order;
    $ar['order']=$order;
    $ar['table']=$this->table;

    // recherche des templates d'impression
    if(empty($this->_templates)) $this->_templates=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
    if(!empty($this->_templates)) {
      $q1=$this->_templates->select_query(array('cond'=>array('modid'=>array('=',$this->_moid),
							      'gtype'=>array('=','xmodtable_display_print'))));
      $r=$this->_templates->browse(array('select'=>$q1,'pagesize'=>100,'tplentry'=>TZR_RETURN_DATA));
      \Seolan\Core\Shell::toScreen1($tplentry.'t',$r);
    }
    $ar['ssmoid']='all';
    for($i=1;$i<=$this->submodmax;$i++) {
      $f='ssmod'.$i;
      $ar['options'][$this->$f]['selectedfields']='all';
    }
    $ar['tlink'] = true;
    return $this->display($ar);
  }

  function preExportBrowse($ar=NULL){
    $this->prePrintBrowse($ar, false);
  }

  function preExportDisplay($ar=NULL){
    $this->prePrintDisplay($ar);
  }

  function preFilledReporting($ar=NULL){
    unset($_REQUEST['_selected']);
    $_REQUEST['isReporting'] = true;
    if(!$this->isThereAQueryActive()) {
      $this->_setSession('query', array('_table' => $this->table));
    }
    $_storedquery = $this->_getSession('query') ?: array();
    $query = array_merge($_storedquery, $ar);
    $this->_setSession('reporting_query', $query);

    return $this->prePrintBrowse($ar);
  }

  /// Ajoute les actions du browse
  function browse_actions(&$r, $assubmodule=false, $ar=null) {
    if(!is_array($r['lines_oid'])) return;

    $p=new \Seolan\Core\Param($ar);

    $noeditoids=$p->get('noeditoids');

    if(!is_array($noeditoids)) $noeditoids=array();

    foreach($r['lines_oid'] as $i =>$oid) {
      $oidlvl=array_keys($r['objects_sec'][$i]);
      $this->browseActionsForLine($r,$i,$oid,$oidlvl,$noeditoids);
    }
    // actions sur la langue de base ...
    if ($r['translation_mode'] == 1){
      $old_translation_mode = $r['translation_mode'];
      $r['translation_mode'] = false;
      $this->browse_actions_translation($r, $ar, $noeditoids, ['suffix'=>'2',
							       'lang_trad'=>$r['lang_trad'],
							       'urlcomplements'=>'&LANG_TRAD=&LANG_DATA='.TZR_DEFAULT_LANG]);
      $r['translation_mode'] = $old_translation_mode;
    }

  }
  /// Ajout les actions au browse pour un contexte langue donné
  protected function browse_actions_translation(&$r,
						$ar=null,
						$noeditoids=null,
						$options=['lang_trad'=>TZR_DEFAULT_LANG,
							  'suffix'=>'2',
							  'urlcommplements'=>null]){
    $this->_actionsCacheUrl = [];
    $this->_actionsCacheLvl = [];
    $this->_actionsCacheIco = [];
    $this->_actionsCacheTxt = [];
    $this->_actionsCacheAttr = [];

    // avant les changements de langues
    $r['_del_confirmmessage'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','confirm_delete_object_alllang');

    // langue de base temporairement
    \Seolan\Core\Shell::setLang($options['lang_trad']);
    $sv = ['actions', 'actions_url', 'actions_label', 'actions_text'];
    foreach($sv as $k){
      $r[$k.'sv'] = $r[$k];
      unset($r[$k]);
    }
    if (!isset($r['objects_sec_trad'])){
      $r['objects_sec_trad'] = $GLOBALS['XUSER']->getObjectsAccess($this, $options['lang_trad'], $r['lines_oid']);
    }
    $oldurlparms = null;
    if (!empty($r['urlparms'])){
      $oldurlparms = $r['urlparms'];
    }
    $r['urlparms'] = $r['urlparms'].$options['urlcomplements'];

    foreach($r['lines_oid'] as $i =>$oid) {
      $oidlvl=array_keys($r['objects_sec_trad'][$i]);
      $this->browseActionsForLine($r,$i,$oid,$oidlvl,$noeditoids);
    }

    $r['urlparms'] = $oldurlparms;
    \Seolan\Core\Shell::unsetLang();

    // actions => actions$suffix
    foreach($sv as $k){
      $k0 = str_replace('actions', 'actions'.$options['suffix'], $k);
      $r[$k0] = $r[$k];
    }
    // actions1 => $actions et unset
    foreach($sv as $k){
	$r[$k] = $r[$k.'sv'];
	unset($r[$k.'sv']);
    }
  }
  /// Ajoute les actions du browse à une ligne donnée
  /// -> cas mode traduction
  function browseActionsForLine(&$r,&$i,&$oid,&$oidlvl,&$noeditoids){
    // mode traduction et traduction pas en place
    if (($r['translation_mode'] == 1)){
      if ($r['lines_translation_ok'][$i]){
	$this->browseActionView($r,$i,$oid,$oidlvl);
      }
      if(!in_array($oid,$noeditoids)){
        $this->browseActionEditInsert($r,$i,$oid,$oidlvl, (!$r['lines_translation_ok'][$i]));
        if ($r['lines_translation_ok'][$i]){
          $this->browseActionDel($r,$i,$oid,$oidlvl);
        }
      }
    } else {
      $this->browseActionView($r,$i,$oid,$oidlvl);
      if(!in_array($oid,$noeditoids)){
        $this->browseActionEdit($r,$i,$oid,$oidlvl);
        $this->browseActionDel($r,$i,$oid,$oidlvl);
      }
      if (empty(\Seolan\Core\Shell::getLangTrad())){
        $this->browseActionClone($r,$i,$oid,$oidlvl);
      }
      $this->browseActionSec($r,$i,$oid,$oidlvl);
    }
  }
  /// Action d'initialisation de la duplication d'une fiche
  function browseActionClone(&$r,&$i,&$oid,&$oidlvl){
    if ($this->clonefrombrowse
	&& TZR_DEFAULT_LANG == \Seolan\Core\Shell::getLangData()
	&& !$this->translationMode(new \Seolan\Core\Param([], array()))
	&& !empty(array_intersect($this->secGroups('editDup'), $oidlvl))
	){
      $txt = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','clone');
      $ico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','clone');
      $url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=editDup&template=Module/Table.edit.html';

      $attrs=$this->browseActionEditHtmlAttributes($url,$txt,$ico);
      $action = $this->formatBrowseAction($oid, $txt, $ico, $url, $attrs, @$r['urlparms']);

      $r['actions'][$i]['clone']=$action['link'];
      $r['actions_url'][$i]['clone']=$action['url'];
      $r['actions_label'][$i]['clone']=$action['label'];
    }
  }

  /// Lien en edition dans une langue sans donnée
  /// IE : creation de la donnée dans cette langue
  function browseActionEditInsert(&$r,&$i,&$oid,&$oidlvl,$isnew=false){
    if (empty(array_intersect($this->secGroups('edit'), $oidlvl))){
      return;
    }

    $txt=$this->browseActionEditText();
    if ($isnew){
      $txt = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','new_translation');
      $ico=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','new_translation');
    } else {
      $txt = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','edit_translation');
      $ico = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','edit_translation');
    }
    $url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=editTranslations&template='.static::$editTranslationTemplate;

    $attrs=$this->browseActionEditHtmlAttributes($url,$txt,$ico);

    $action = $this->formatBrowseAction($oid, $txt, $ico, $url, $attrs, @$r['urlparms']);

    $r['actions'][$i]['edit']=$action['link'];
    $r['actions_url'][$i]['edit']=$action['url'];
    $r['actions_label'][$i]['edit']=$action['label'];
  }
  /// Lien en edition dans le browse
  /// -> cas "mode traduction"
  function browseActionEdit(&$r,&$i,&$oid,&$oidlvl,$usersel=false){
    parent::browseActionEdit($r, $i, $oid, $oidlvl, $usersel);
    if (!$usersel
	&& $r['translation_mode'] == 1
	&& !empty($r['actions_url'][$i]['edit'])){ // on a des droits édition
      $this->browseActionEditInsert($r, $i, $oid, $oidlvl, $usersel);
    }
  }
  /// Retourne les onfos de l'action voir du browse
  function browseActionViewIco($linecontext=null){
    if (isset($linecontext) && $linecontext['browse']['translation_mode'] == 1){
      return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','view_translation');
    } else {
      return \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','view');
    }
  }
  /// Retourne les infos de l'action supprimer du browse
  function browseActionDel(&$r,&$i,&$oid,&$oidlvl,$usersel=false){
    $this->browseActionForLine('del',$r,$i,$oid,$oidlvl,$usersel);
  }
  function browseActionDelText($linecontext=null){
    return \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete');
  }

  function browseActionDelIco($linecontext=null){
    if (isset($linecontext) && $linecontext['browse']['translation_mode'] == 1){
      return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','delete_translation');
    } else {
      return \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete');
    }
  }
  function browseActionDelLvl($linecontext=null){
    return $this->secGroups('del');
  }
  function browseActionDelHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
    $attrs = 'class="cv8-delaction"';
    if (isset($linecontext['browse']['_del_confirmmessage'])){
      $attrs .= ' data-message=\''.escapeJavascript($linecontext['browse']['_del_confirmmessage']).'\'';
    }
    return $attrs;
  }

  function browseActionDelUrl($usersel, $linecontext=null){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=del&template=Core.message.html&skip=1';
  }
  /// Retourne les infos de l'action securite du browse
  function browseActionSec(&$r,&$i,&$oid,&$oidlvl,$usersel=false){
    $this->browseActionForLine('sec',$r,$i,$oid,$oidlvl,$usersel);
  }
  function browseActionSecText(){
    return \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','security');
  }
  function browseActionSecIco(){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','security');
  }
  function browseActionSecLvl(){
    return $this->object_sec?$this->secGroups('secEdit'):array();
  }
  function browseActionSecHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
    return 'class="cv8-secaction" '.
      'onclick="TZR.editSec(\''.$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true).'\',\''.$this->_moid.'\',\'<oid>\'); return false;"';
  }
  function browseActionSecUrl($usersel){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=secEditSimple&template=Core/Module.edit-sec.html';
  }
  /// display dans une langue indépendante du contexte langues
  protected function displayTranslation($ar, $oid){
    $params = new \Seolan\Core\Param($ar, array('langtrad'=>$this->_issetSession('displaylangtrad')?$this->_getSession('displaylangtrad'):TZR_DEFAULT_LANG));
    if (empty($LANG_TRAD = $params->get('langtrad')) || !in_array($LANG_TRAD, $this->getAuthorizedLangs('all', $oid, 'display'))){
      \Seolan\Core\Logs::critical(get_class($this), '::displayTranslation lang error '.$LANG_TRAD);
      return null;
    }

    /*
     * force la langue (sans \Seolan\Core\Shell::setLang force aussi lang_user)
     * !! la laisser <- langue au moment du display_defered lors du parse
     */
    $_REQUEST['LANG_DATA'] = $LANG_TRAD;
    \Seolan\Core\Shell::getLangData(NULL, true); // comuted lang

    // nouveau display
    $d = $this->display(array('oid'=>$oid,
			      'requested_submodules'=>array(), // pour ne pas avoir les sous modules eventuels
			      '_options'=>array('local'=>1),
			      'tplentry'=>TZR_RETURN_DATA)
			);


    $d['_edituniqid'] = $params->get('_edituniqid');

    $d['_langdisplay'] = \Seolan\Core\Lang::get($LANG_TRAD);
    /* quand la traduction existe on ne la demande pas en fait
    if ($d['translation_ok'] == 1){
      $d['_langdisplay'] = \Seolan\Core\Lang::get($LANG_TRAD);
    } else {
      $d['_langdisplay'] = \Seolan\Core\Lang::get($d['d']['_lang_data']);
    }
    */
    $this->_setSession('displaylangtrad', $LANG_TRAD);

    $tplentry = $params->get('tplentry');

    return \Seolan\Core\Shell::toScreen1($tplentry, $d);

  }
  /// Staus langue / langue d'une fiche
  function langStatus($ar=null){
    $p = new \Seolan\Core\Param($ar, array());
    $oid = $p->get('oid');
    // tous les status
    $ls = $this->xset->objectLangStatus($oid);

    foreach($this->getAuthorizedLangs('all', $oid, 'display') as $code){
      $items[$code] = $ls[$code];
      $items[$code]['_lang'] = \Seolan\Core\Lang::get($code);
    };
    return \Seolan\Core\Shell::toScreen2($p->get('tplentry'), 'langStatus', $items);
  }
  /// Edition d'une fiche dans le moded traduction
  function editTranslations($ar){
    $params = new \Seolan\Core\Param($ar, array('tplentry'=>'br', 'displaylangtrad'=>NULL)); // a voir
    $oid = $params->get('oid');
    $tplentry = $params->get('tplentry');

    $translationMode = $this->translationMode($params);
    if (!$translationMode){ // vers quelque chose d'independant du mode
      \Seolan\Core\Shell::setNext($this->getMainAction());
      return;
    }

    // demande de lecture la fiche dans une langue (partie gauche)
    if ($params->is_set('displaylangtrad')){
      return $this->displayTranslation($ar, $oid);
    }

    // edit dans la langue des données (lang_data, lang_other)
    // + gestion de la navigation page/page
    $ar['requested_submodules']=array(); // pour ne pas avoir les sous modules eventuels @todo
    $r = $this->edit($ar);
    if ($params->is_set('navdir')){ // voir edit
      $oid = $_REQUEST['oid']; //
    }
    // navigation de fiche en fiche
    $this->setNavActions($oid, 'editTranslations', static::$editTranslationTemplate, null);

    // status langue
    if ($r['translation_ok'] != 1){
      $r['message'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'translation_missing_data').'. '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'translation_missing_data_create').'.';
    } else {

    }
    // onglet langes disabled dans le edittranslation (il est dans le display)
    $r['_langstatus'] = false;
    // langues possibles en view et edit
    $r['_allowedlangs'] = array('view'=>$this->getAuthorizedLangs('all', $oid, 'display'),
				'edit'=>$this->getAuthorizedLangs('all', $oid, 'procEdit'));
    // recuperer les infos completes depuis le code rendu
    // status de l'objet pour les langues qui seront possibles
    array_walk($r['_allowedlangs']['view'], function(&$item) use($oid){
	$item = \Seolan\Core\Lang::get($item);
	$item['translation_ok'] = $this->xset->objectExists($oid, $item['code']);
      });
    array_walk($r['_allowedlangs']['edit'], function(&$item) use($oid){
	$item = \Seolan\Core\Lang::get($item);
	if ($item['code'] == TZR_DEFAULT_LANG){	  // on ne peut pas faire d'edit avec en mode traduction ?
	  $item['inactive'] = true;
	}
	$item['translation_ok'] = $this->xset->objectExists($oid, $item['code']);
      });
    $r['_langedit'] = \Seolan\Core\Lang::get($translationMode->LANG_DATA);

    // display dans la langue de traduction (lang_trad)
    $r['_displayurl'] = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(false, false, false).'&skip=1&moid='.$this->_moid.'&oid='.$oid.'&tplentry=br&function=editTranslations&displaylangtrad=1&template='.static::$viewTranslationTemplate;

    // url edition autre langue (lang_data)
    $r['_editurl'] = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(false, false, false).'&skip=1&moid='.$this->_moid.'&oid='.$oid.'&tplentry=br&function=editTranslations&template='.static::$editTranslationTemplate;

    // action sur enregistrement
    $r['_savenext'] = $this->_getSession('savenext');

    return \Seolan\Core\Shell::toScreen1($tplentry, $r);

  }
  /// Edition d'une fiche
  function edit($ar) {
    $ar = $this->triggerCallbacks(self::EVENT_PRE_CRUD, $ar);
    $p=new \Seolan\Core\Param($ar,array());
    $oid = $p->get('oid');
    /// vérification du contexte (changement de langue en particulier)
    if (!$this->checkLanguageContext($p, ($context = (Object)array('function'=>'edit')))){
      \Seolan\Core\Shell::setNext($context->next);
      return;
    }

    $tplentry=$p->get('tplentry');
    $navdir=$p->get('navdir');
    $ar['table']=$this->table;
    $ar['tplentry']=TZR_RETURN_DATA;
    if(empty($ar['fmoid']))
      $ar['fmoid']=$this->_moid;

    /// application du workflow
    if(\Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID)) {
      $umod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
      if($umod->isPendingCase($oid)) {
	$umod->editDocumentCallback($oid, $this);
      }
    }
    $ar['fieldssec']=$this->getFieldsSec($ar);

    if($this->interactive){
      if($this->trackaccess) $ar['accesslog']=1;
      // Gestion de la nav page/page
      if(!empty($navdir)){
	list($navprev,$navnext,$foo,$isfirst,$islast)=$this->mkNavParms($ar);
	if($navdir=='next') $_REQUEST['oid']=$navnext;
	else $_REQUEST['oid']=$navprev;
      }
    }

    $ar['numberOfColumns']=$this->numberOfColumns;
    $r2=$this->xset->edit($ar);

    if(!empty($navdir)){
      $r2['_isfirst']=$isfirst;
      $r2['_islast']=$islast;
    }
    // Choix du templates d'affichage s'il existe
    if(!empty($this->_templates) && !empty($this->templates)) {
      $this->_templates->display(array('oid'=>$this->templates,'_options'=>array('error'=>'return'),'tplentry'=>$tplentry.'t'));
    }
    $this->setSubModules($ar, $r2);

    $this->prepareComments($r2, $oid);

    if(\Seolan\Core\Shell::admini_mode()) {
      // langues possibles pour enregistrement
      if (($translatable = $this->xset->getTranslatable()) && TZR_DEFAULT_LANG == \Seolan\Core\Shell::getLangData()){
	$r2['authorized_languages'] = $this->getAuthorizedLangs('all', $oid, 'edit');
	$r2['langsort']=\Seolan\Core\Lang::getCodes(NULL,true);
      }
      // langue propagée sur d'autres
      if ($translatable && $this->xset->getAutoTranslate()){
	if (@count(($langsrepli = $this->getLangsRepli(\Seolan\Core\Shell::getLangData($p->get('LANG_DATA')), $oid, 'procEdit')))>0){
	  setSessionVar('message',
			getSessionVar('message').
			'<br>'.
			\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','content_propagated').
			implode(',',
				array_map(function($item){return \Seolan\Core\Lang::get($item)['text'];},$langsrepli)
				)
			);
	}
      }
      // langue propagée sur d'autres
      if ($translatable && $this->xset->getAutoTranslate()){
	if (@count(($langsrepli = $this->getLangsRepli(\Seolan\Core\Shell::getLangData($p->get('LANG_DATA')), $oid, 'procEdit')))>0){
	  setSessionVar('message',
			getSessionVar('message').
			'<br>'.
			\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','content_propagated').
			implode(',',
				array_map(function($item){return \Seolan\Core\Lang::get($item)['text'];},$langsrepli)
				)
			);
	}
      }
      // calcul des droits
      if ($this->object_sec) {
        $acl=$GLOBALS['XUSER']->listObjectAccess($this, \Seolan\Core\Shell::getLangData(), $p->get('oid'));
        $r3=array_merge($r2, $acl);
        $sec=$GLOBALS['XUSER']->getObjectAccess($this, \Seolan\Core\Shell::getLangData(), $r2['oid']);
        $sec=array_flip($sec[0]);
        $r2['object_sec']=$sec;
      }
      // passage du context sous module
      $submodcontext = $this->subModuleContext($ar);
      $r2['urlparms'] = @$submodcontext['urlparms'];
    }
    if($this->captcha && (!\Seolan\Core\Shell::admini_mode() || \Seolan\Core\User::isNobody())) $r2['captcha']=$this->createCaptcha($ar);
    if($this->honeypot && (!\Seolan\Core\Shell::admini_mode() || \Seolan\Core\User::isNobody())) $r2['honeypot']=$this->createHoneypot();
    if($tplentry!=TZR_RETURN_DATA) \Seolan\Core\Shell::toScreen1('iacl',$r3);
    return \Seolan\Core\Shell::toScreen1($tplentry,$r2);
  }

  /// Prepare l'edition par lot
  function editSelection($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $oids=\Seolan\Core\Kernel::getSelectedOids($p);
    $ar['editbatch']=true;
    $ar['fmoid']=$this->_moid;
    $ar['tplentry'] = TZR_RETURN_DATA;
    // ne sélectionner que les champs traduisibles
    if ($this->xset->isTranslatable()){
      $lang=\Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));
      if ($lang!=TZR_DEFAULT_LANG) {
	$translatable= $this->getTranslatable();
	if ($translatable!=3 && $this->xset->isTranslatable()) {
	  $ar['_prepareMultiEdit'] = true;
	  $translatablefields = $this->xset->getFieldsList('',
							   false,
							   false,
							   false,
							   false,
							   true, // translatable
							   false,
							   null,
							   'OR', // op
							   false
	  );
	  if ($p->is_set('selectedfields')){
	    $selectedfields = $p->get('selectedfields');
	    if ($selectedfields == '*' || $selectedfields == 'all')
	      $selectedfields = $translatablefields;
	    else {
	      $selectedfields = array_diff($translatablefields, $selectedfields);
	    }
	  } else {
	    $selectedfields = $translatablefields;
	  }
	  $ar['selectedfields'] = $selectedfields;
	}
      }
    }
    $ar['fieldssec']=$this->getFieldsSec($ar);

    $result=$this->xset->input($ar);
    $result['oids']=$oids;
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }

  /// Prepare l'edition par lot de tout le resultat de la recherche active
  function &editAll($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    if($this->isThereAQueryActive()) {
      $_storedquery=$this->_getSession('query');
      $ar=array_merge($_storedquery,$ar);
      $ar['editbatch']=true;
      $ar['fmoid']=$this->_moid;
      $ar['tplentry']=TZR_RETURN_DATA;
      $ar['selectedfields']=array('KOID');
      $ar['pagesize']=10000;
      $ar['fieldssec']=$this->getFieldsSec($ar);
      $ar['_filter']=$this->getFilter(true,$ar);
      $r1=$this->xset->procQuery($ar);
      $result=$this->editSelection(array('oid'=>$r1['lines_oid']));
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }

  /// Fonction de controle du formulaire via ajax
  function ajaxProcEditCtrl(&$ar){
    return $this->editCtrlResponse($this->procEditCtrl($ar));
  }
  /// Fonction de controle du formulaire
  function procEditCtrl(&$ar) {
    $p = new \Seolan\Core\Param($ar,array());
    $captcha_ok = $p->get('captcha_ok','local');
    if($this->captcha && (!\Seolan\Core\Shell::admini_mode() || \Seolan\Core\User::isNobody()) && !$captcha_ok){
      $captcha_key = md5($p->get('captcha_key'));
      $captcha_id = 'CAPTCHA_'.$p->get('captcha_id');
      $cnt = getDB()->count('SELECT COUNT(*) FROM _VARS WHERE name = ? AND value = ? ', [$captcha_id, $captcha_key]);
      getDB()->execute('DELETE FROM _VARS WHERE name = ? or (UPD<"'.date('YmdHis',strtotime('- 20 minutes')).'" AND '.
      'name LIKE "CAPTCHA_%")', [$captcha_id]);
      if($cnt) {
        return true;
      }else{
        $onerror=$p->get('onerror');
        if(!empty($onerror)) {
	  if(!preg_match('@(^https?://|^/)@',$onerror)) $onerror=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().$onerror;
          header('Location: '.$onerror);
          die();
        }
        return false;
      }
    }
    return true;
  }

  /// Fonction de controle du formulaire avant insertion via ajax
  function ajaxProcInsertCtrl(&$ar) {
    return $this->editCtrlResponse($this->procInsertCtrl($ar));
  }
  /// Fonction de controle du formulaire avant insertion
  function procInsertCtrl(&$ar) {
    return $this->procEditCtrl($ar);
  }
  /// Fonction de controle du formulaire avant duplication via ajax
  function ajaxProcEditDupCtrl(&$ar) {
    return $this->editCtrlResponse($this->procEditDupCtrl($ar));
  }
  /// Fonction de controle du formulaire avant duplication
  function procEditDupCtrl(&$ar) {
    return $this->procEditCtrl($ar);
  }

  /// Mise en forme d'une reponse json sur controle de saisie
  protected function editCtrlResponse($ret) {
    if ($ret === true) {
      returnJson(['status' => 'success']);
    }
    if (is_array($ret)) {
      $ret['status'] = 'error';
    } else {
      $ret = ['status' => 'error'];
    }
    $ret['error'] = [$this->getErrorMessage()];
    returnJson($ret);
  }

  protected function getErrorMessage() {
    if (isset($_REQUEST['message'])) {
      return $_REQUEST['message'];
    }
    if (issetSessionVar('message')) {
      $message = getSessionVar('message');
      clearSessionVar('message');
      return $message;
    }
    $screenmess = Shell::from_screen('', 'message');
    if (!empty($screenmess)) {
      return $screenmess;
    }
  }

  /**
   * Enregistrement des modifications  dans le mode traduction
   * -> gestion du _next spécifique
   */
  function procEditTranslation($ar=null){
    $r = $this->procEdit($ar);
    $p = new \Seolan\Core\Param($ar, array());
    $next = $p->get('_next');
    if (empty($next) || ($next != 'nextlang' && $next != 'nextitem')){
      $this->_clearSession('savenext');
      return $r;
    }
    $this->_setSession('savenext', $next);
    // gestion du next demande et save de celui-ci
    if ($next == 'nextlang'){ // a ce stade complique : setlang + etc
      \Seolan\Core\Shell::setNext($this->getMainAction());
      return;
    }
    if ($next == 'nextitem'){
      $oid = $p->get('oid');
      list($oidprev,$oidnext,$oidactu,$isfirst,$islast)=$this->mkNavParms($ar);
      if (!empty($oidnext)){
	\Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(false, false, false).'&moid='.$this->_moid.'&oid='.$oidnext.'&tplentry=br&function=editTranslations&template='.static::$editTranslationTemplate);
      } else {
	\Seolan\Core\Shell::setNext($this->getMainAction());
      	return;
      }
    }
  }
  /*****
   * NAME
   *   \Seolan\Module\Table\Table::procEdit - traitement d'une formulaire préparé avec la méthode edit.
   * DESCRIPTION
   *   Traitement d'une formulaire préparé avec la méthode edit. Les données sont modifiées
   *   en base de donnée
   * INPUTS
   *   Passage de paramètre indirect via $ar
   ****/
  function procEdit($ar) {
    $ar = $this->triggerCallbacks(self::EVENT_PRE_CRUD, $ar);
    $p=new \Seolan\Core\Param($ar,array('applyrules'=>true,'_new_comment'=>null,'_sendacopyto'=>0));
    if($p->is_set('procEditAllLang')) {
      $this->procEditAllLang($ar);
      $p=new \Seolan\Core\Param($ar,array('applyrules'=>true,'_new_comment'=>null,'_sendacopyto'=>0));
    }

    if($this->honeypot && !$this->checkHoneypot($ar)) {
      return true;
    }

    $ar['table']=$this->table;
    $ar['_track']=$this->trackchanges;
    $ar['_archive']=$this->archive;
    $ar['fieldssec']=$this->getFieldsSec($ar);
    $oid = $p->get('oid');
    $tplentry = $p->get('tplentry');
    $applyrules=$p->get('applyrules');
    $noworkflow=$p->get('_noworkflow');
    $langs=$p->get('_langs');
    if (isset($this->xset->desc['PUBLISH']) && !$this->secure('', ':rwv'))
      unset($_REQUEST['PUBLISH'], $_REQUEST['PUBLISH_HID']);
    if(is_array($oid)) {
      $P1=array();
      $reeditone=$p->get('reeditone');
      $editfields = $p->get('editfields');
      $editbatch=$p->get('editbatch');
      foreach($this->xset->desc as $f => $o) {
	if(($editfields=='all') || in_array($f,$editfields)){
	  $P1[$f]=$p->get($f);
	  $P1[$f.'_HID']=$p->get($f.'_HID');
	}
      }
      foreach($oid as $i=>$oid1) {
	$ar1=array();
	if(!$editbatch){
	  foreach($this->xset->desc as $f => $o) {
	    if(($editfields=='all') || in_array($f,$editfields)){
          if(is_array($P1[$f]) || is_array($P1[$f.'_HID'])) {
            if(isset($P1[$f][$i]) || isset($P1[$f.'_HID'][$i])) {
              $ar1[$f]=$P1[$f][$i];
              $ar1[$f.'_HID']=$P1[$f.'_HID'][$i];
            }
          } else if(isset($P1[$f]) || isset($P1[$f.'_HID'])) {
            $ar1[$f]=$P1[$f];
            $ar1[$f.'_HID']=$P1[$f.'_HID'];
          }
	    }
	  }
	}else{
	  $ar1=$P1;
	}
	$ar1['_options'] = array('local'=>true);
	$ar1['oid']=$oid1;
	$ar1['editfields']=$editfields;
	$ar1['editbatch']=$editbatch;
	$ar1['applyrules']=$applyrules;
	$this->procEdit($ar1);
      }
      if($reeditone){
	$_storedquery=$this->xset->captureQuery(array('_options'=>array('local'=>true),'oids'=>$oid,'order'=>'KOID'));
	$this->_setSession('query',$_storedquery);
	$this->_setSession('lastorder','KOID');
	list($p,$n,$a)=$this->mkNavParms(array('_options'=>array('local'=>true)));
	\Seolan\Core\Shell::setNext('moid='.$this->_moid.'&function=edit&template=Module/Table.edit.html&tplentry=br&oid='.$a.'&usenav=1');
      }
      return;
    }
    if($this->procEditCtrl($ar)===true) {
      // Traitement des cas ou l'on veut sauver dans plusieurs langues
      $ar['_langs']=$this->getAuthorizedLangs($langs,$oid,'procEdit');
      if(empty($ar['fmoid']))
	$ar['fmoid']=$this->_moid;

      /// application du workflow (activer les droits pour les champs modifiables)
      if(empty($noworkflow) && \Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID)) {
        $umod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
        if($umod->isPendingCase($oid)) {
          $umod->editDocumentCallback($oid, $this);
          unset($ar['fieldssec']);
          $ar['fieldssec']=$this->getFieldsSec($ar);
        }
      }

      // ajout des langues de propagation/replication automatique autorisées
      $ar['_langspropagate'] = $this->getLangsRepli(\Seolan\Core\Shell::getLangData(), $oid, 'procEdit');

      $r=$this->xset->procEdit($ar);

      if (isset($r['oid']) && $this->allowcomments)
	$this->processInlineComment($oid, $oid, $p);

      if($applyrules) {
	\Seolan\Module\Workflow\Rule\Rule::applyRules($this,$oid);
      }

      /// traitements à faire systematiquement à chaque mise à jour
      $this->updateTasks($ar, $oid, 'procEdit');

      /// application du workflow
      if(empty($noworkflow) && \Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID)) {
	$umod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
	$umod->checkAndRun($this, $this, $oid, 'edit');
      }
      if($this->savenext && in_array($this->savenext,array('display','edit')) && $tplentry!=TZR_RETURN_DATA){
	$editfields=$p->get('editfields');
	if(empty($editfields) || count($editfields)==0){
	  $submodcontext=$this->subModuleContext($ar);
	  \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&moid='.$this->_moid.
			  '&function='.$this->savenext.'&template=Module/Table.'.($this->savenext == 'display' ? 'view' : 'edit').'.html&tplentry=br&oid='.
			  $oid.@$submodcontext['urlparms']);
	}
      }
      if (\Seolan\Core\Shell::admini_mode()
	  && TZR_RETURN_DATA != $tplentry ){
	$send = $p->get('_sendacopyto');
	if (!empty($send[$this->_moid]))
	  $this->redirectToSendACopyTo($oid);
      }
    } else { // !ctrlProcEdit
      if (\Seolan\Core\Shell::admini_mode()) {
        unset($_REQUEST['skip'], $_REQUEST['_skip']);
        \Seolan\Core\Shell::changeTemplate('Module/Table.edit.html');
        \Seolan\Core\Shell::setNext();
      }
      $ar['options']=$this->xset->prepareReEdit($ar);
      $ar['tplentry']= 'br';
      return $this->edit($ar);
    }
    return $r;
  }
  /**
   * Récupère et traite un commentaire saisi dans le formulaire d'édition/insertion directement
   * @note ; si module tag on a des appels récursifs -> comments par moid
   */
  protected function processInlineComment($pname, $oid, $p){
    $comment = $p->get('_new_comment');
    if (isset($comment[$this->_moid][$pname])){
      $comm = ['_local'=>true];
      $comm['_oid'] = $oid;
      $comm['data'] = $comment[$this->_moid][$pname];
      $comm['upd'] = getDB()->fetchOne('select UPD from '.$this->table.' where KOID=? and LANG=?', [$comm['_oid'],\Seolan\Core\Shell::getLangData()]);
      $this->insertComment($comm);
      unset($_REQUEST[$pname]);
    }
  }
  /// Mise à jour de toutes les langues sélectionnées pour lesquelles l'utilisateur a les droits
  function procEditAllLang(&$ar) {
    $p = new \Seolan\Core\Param($ar, array('_selectedlangs'=>null));
    if (!$p->is_set('_selectedlangs')){
      $ar['_langs']='all';
    } else {
      $ar['_langs']=$p->get('_selectedlangs');
    }

    // Modification uniquement des champs cochés
    $force_editfields_all = $p->get('force_editfields_all');
    $force_editfields_selected = $p->get('force_editfields_selected');
    if(is_array($force_editfields_selected) && count($force_editfields_selected)) {
      foreach($force_editfields_all as $field) {
        if(!in_array($field, $force_editfields_selected)) {
          unset($_REQUEST[$field]);
          unset($_REQUEST[$field.'_HID']);
          unset($_POST[$field]);
          unset($_POST[$field.'_HID']);
          unset($_GET[$field]);
          unset($_GET[$field.'_HID']);
          unset($ar[$field]);
          unset($ar[$field.'_HID']);
          \Seolan\Core\Param::$post = $_POST;
          \Seolan\Core\Param::$get = $_GET;
          \Seolan\Core\Param::$request = $_REQUEST;
        }
      }
    }
  }
   /**
   * enregistrement d'une feul=ille jExcel recue au format json
   */
  public function procEditSpreadsheet($ar=[]){
    $p = new \Seolan\Core\Param($ar, []);
    $newOids = [];
    $jspreadsheet = \Seolan\Library\JSpreadsheet::getInstance();
    list($procEditAr, $procInsertAr) = $jspreadsheet->procEditAnalyse($p, $this);

    var_dump($_REQUEST, $procEditAr, $procInsertAr, "////");

    if ($procEditAr['count']>0){

      $procEditAr['_options'] = ['local'=>1];
      $procEditAr['tplentry'] = TZR_RETURN_DATA;

      $this->procEdit($procEditAr);

    }

    if ($procInsertAr['count']>0){
      for($i=0; $i<$procInsertAr['count']; $i++){
	$lineArg = ['_options'=>['loca'=>1]];
	foreach($procInsertAr['editfields'] as $fn){
	  $lineArg[$fn] = $procInsertAr[$fn][$i];
	}
	$resIns = $this->procInsert($lineArg);
	var_dump($resIns);
	$newOids[] = $resIns['oid'];
      }
    }

    $todel = $p->get('_deleted');
    if (!empty($todel)){
      $selected = array_reduce($todel, function($selected, $oid){
	$selected[$oid] = 'on';
	return $selected;
      }, []);

      $this->del(['_options'=>['local'=>1],
		  '_selectedok'=>'ok',
		  '_selected'=>$selected]);
    }

    die("ok ? updated {$procEditAr['count']}, created(s) : ".implode(',', $newOids));

  }
  public function procEditJSon($ar=[]) {
    // alimente $_REQUEST depuis php://input
    $data = $this->getJSonPostData();
    // Dans le cas d'un champ "alias" (ex table "USERS") s'il est indiqué dans les attributes
    if(isset($data->attributes->alias)&&!empty($data->attributes->alias))
      $ar['aliasfieldval']=(string)$data->attributes->alias;
    // ... s'il n'est pas indiqué dans les attributes : cela évite de voir sa valeur modifiée par "USERS" car $ar['alias'] = 'USERS'
    // @see Core/Json::getJSonParams()
    elseif(!isset($data->attributes->alias) && isset($this->xset->desc['alias']) && isset($ar['alias']))
      unset($ar['alias']);
    if (!$this->procEditCtrlJSon($ar, $data)) {
      return;
    }
    $ar['tplentry'] = TZR_RETURN_DATA;
    $edit = $this->procEdit($ar);
    if ($edit['oid']) {
      $ar['oid'] = $edit['oid'];
      return $this->displayJSon($ar);
    }
    $GLOBALS['JSON_START_CLASS']::registerError(400, $this->getErrorMessage());
  }

  /// contrôle update par api
  function procEditCtrlJSon($ar, $data) {
    $disp = $this->displayJSon($ar);
    $GLOBALS['JSON_START_CLASS']::clearSets();
    if (!$disp) {
      return FALSE;
    }
    $ok = TRUE;
    if (!$this->procCtrlJson($ar, $data, FALSE)) {
      $ok = FALSE;
    }
    if (!$this->procEditCtrl($ar)) {
      $GLOBALS['JSON_START_CLASS']::registerError(400, $this->getErrorMessage());
      $ok = FALSE;
    }
    return $ok;
  }

  /// Prepare la duplication d'une fiche
  function editDup($ar){
    $p = new \Seolan\Core\Param($ar, array('tplentry'=>'br'));
    $oids = \Seolan\Core\Kernel::getSelectedOids($p);
    if (count($oids) != 1){
      \Seolan\Core\Logs::critical(__MEHTOD__, 'we can only clone one record');
      \Seolan\Core\Shell::setNext($this->getMainAction());
      return;
    }
    $oid = $oids[0];
    $values = getDB()->fetchRow('select '.$this->xset->get_sqlSelectFields('*').' from '.$this->xset->getTable().' where LANG=? and KOID=?', array(TZR_DEFAULT_LANG, $oid));
    $ar = [
     'options'=>[],
	   'tplentry'=>'br',
	   'selectedfields'=>'all',
	   '_options'=>array('local'=>1),
     '_xmc'=>@$ar['_xmc'],
	   'tplentry'=>TZR_RETURN_DATA
    ];
    foreach($this->xset->desc as $fn=>$fd){
      if($fd->initFieldIfDuplicate)
	$ar['options'][$fn] = ['value'=>$fd->getDefaultValue()];
      else
	$ar['options'][$fn] = ['value'=>$values[$fn]];
    }
    $r = $this->insert($ar);
    $r['_duplicate']=1;  // changer les boutons submit et l'action
    $r['oid'] = $oid;
    \Seolan\Core\Shell::alert(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'clone_confirm'), 'info');
    return \Seolan\Core\Shell::toScreen1($p->get('tplentry'), $r);
  }
  /// Duplique une fiche à partir d'une edition
  function procEditDup($ar) {
    $ar = $this->triggerCallbacks(self::EVENT_PRE_CRUD, $ar);
    $p = new \Seolan\Core\Param($ar, NULL);

    $tplentry = $p->get('tplentry');
    $oid=$p->get('oid');
    $ar['table']=$this->table;
    if($this->procEditDupCtrl($ar)===true) {
      $ret=$this->xset->procEditDup($ar);
      $this->duplicateSubModules($oid,$ret['oid']);
      $this->updateTasks($ar, $oid, 'procEdit');
      if ($this->savenext && in_array($this->savenext,array('display','edit'))  && $tplentry!=TZR_RETURN_DATA){
	$oid = $ret['oid'];
	$submodcontext = $this->subModuleContext($ar);
	\Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&class='.get_class($this).'&moid='.$this->_moid.'&function='.$this->savenext.'&template=Module/Table.'.($this->savenext == 'display' ? 'view' : 'edit').'.html&tplentry=br&oid='.$oid.@$submodcontext['urlparms']/* vide si pas  sous module */);
      }
      if ($this->xset->isTranslatable() && $this->interactive){
	\Seolan\Core\Shell::alert(
	  sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','duplicate_translatable'),
		  \Seolan\Core\Lang::get(TZR_DEFAULT_LANG)['text']),
	  'warning');
      }
    } else {
      if (\Seolan\Core\Shell::admini_mode()) {
        unset($_REQUEST['skip'], $_REQUEST['_skip']);
        \Seolan\Core\Shell::changeTemplate('Module/Table.edit.html');
        \Seolan\Core\Shell::setNext();
      }
      $ar['options']=$this->xset->prepareReEdit($ar);
      $ar['tplentry']= 'br';
      return $this->edit($ar);
    }
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Traitement d'une edition par lot
  function procEditSelection($ar){
    $ar['editbatch']=true;
    return $this->procEdit($ar);
  }

  /// Duplique une fiche
  function duplicate($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $oid=$p->get('oid');
    $ar['oid']=$oid;
    $noid=$this->xset->duplicate($ar);
    $this->duplicateSubModules($oid,$noid);
    $this->updateTasks($ar, $oid, 'duplicate');
    return $noid;
  }

  /// Duplication des sous fiches d'une fiche
    function duplicateSubModules($oid,$oiddst,$pagesize=NULL) {
    $oids=array();
    $subMods=$this->getSubModules(true);
    foreach($subMods as $subMod) {
      $sel=$subMod['xset']->select_query(array('cond'=>array($subMod['linkfield']=>array('LIKE','%'.$oid.'%'))));
      $ssRecords=$subMod['xset']->browse(array('select'=>$sel,'tplentry'=>TZR_RETURN_DATA,
                                               'pagesize'=>$pagesize,'selected'=>'0',
                                               'selectedfields'=>$subMod['linkfield']));
      foreach($ssRecords['lines_oid'] as $ssoid){
	$oids[]=$n=$subMod['mod']->duplicate(array('oid'=>$ssoid,'_options'=>array('local')));
	getDB()->execute('update '.$subMod['mod']->table.' set '.$subMod['linkfield'].'=? where KOID=?', [$oiddst,$n]);
      }
    }
    return $oids;
  }

  /*****
   * NAME
   *   \Seolan\Module\Table\Table::query - génération du formulaire de requête
   * DESCRIPTION
   *   Calcul des éléments du formalaire de requête pour l'ensemble de fichier. Le traitement
   *   du formulaire est ensuite assuré par \Seolan\Module\Table\Table::procQuery ()
   * INPUTS
   *   Passage de paramètre indirect via $ar
   ****/
  function query($ar1) {
    $p = new \Seolan\Core\Param($ar1,array());
    $tplentry=$p->get('tplentry');
    $ar1['table']=$this->table;
    $ar1['fieldssec']=$this->getFieldsSec($ar1);
    $ar = $ar1;
    // récupération de la recherche en cours
    if($this->_issetSession('query')) {
      $query = $this->_getSession('query');
      $current_search_fields = [];
      foreach($query['_FIELDS'] as $fieldid => $fieldname) {
        if($this->xset->desc[$fieldname] && !$this->xset->desc[$fieldname]->isQueryEmpty($query)) {
          $ar[$fieldname] = $query[$fieldid];
          $ar[$fieldname . '_op'] = $query[$fieldid . '_op'];
          $ar[$fieldname . '_HID'] = $query[$fieldid . '_HID'];
          $ar[$fieldname . '_FMT'] = $query[$fieldid . '_FMT'];
          $ar[$fieldname . '_PAR'] = $query[$fieldid . '_PAR'];
          $ar[$fieldname . '_empty'] = $query[$fieldid . '_empty'];
          $current_search_fields[] = $fieldname;
        }
      }
      $current_search_fields['operator'] = $query['operator'];
      Shell::toScreen2('current_search', 'fields', $current_search_fields);
    }
    if($this->interactive && $this->_issetSession('query')) $this->_clearSession('query');
    if($this->interactive && !$this->stored_query) $ar['searchmode']='simple';
    if(empty($ar['fmoid']))
      $ar['fmoid']=$this->_moid;
    $r=$this->xset->query($ar);
    if($tplentry!=TZR_RETURN_DATA && \Seolan\Core\Shell::admini_mode() && $this->interactive && $this->stored_query) {
      $r1=$this->storedQueries();
      \Seolan\Core\Shell::toScreen1('queries',$r1);
    }
    if ($this->submodsearch && $ar['submodsearch'] !== false){
      $ssmods = $this->getSubModules(false);
      $current_search_fields = [];
      foreach($ssmods as &$ssmod){
        $ar = $ar1;
        $ar['submodsearch'] = false;
        $moid = $ssmod['mod']->_moid;
        if($this->_issetSession('ssmodquery'.$moid)) {
          $current_search_fields[$moid] = [];
          $query = $this->_getSession('ssmodquery'.$moid);
          $this->_clearSession('ssmodquery'.$moid);
          foreach($query['_FIELDS'] as $fieldid => $fieldname) {
            if($ssmod['mod']->xset->desc[$fieldname] && !$ssmod['mod']->xset->desc[$fieldname]->isQueryEmpty($query)) {
              $ar[$fieldname] = $query[$fieldid];
              $ar[$fieldname . '_op'] = $query[$fieldid . '_op'];
              $ar[$fieldname . '_HID'] = $query[$fieldid . '_HID'];
              $ar[$fieldname . '_FMT'] = $query[$fieldid . '_FMT'];
              $ar[$fieldname . '_PAR'] = $query[$fieldid . '_PAR'];
              $ar[$fieldname . '_empty'] = $query[$fieldid . '_empty'];
              $current_search_fields[$moid][] = $fieldname;
            }
          }
          $current_search_fields[$moid]['operator'] = $query['operator'];
        }
        $ar['fmoid'] = $moid;
        $ssmod['query'] = $ssmod['mod']->query($ar);
        $r['ssmodsearch'][] = $ssmod;
      }
      Shell::toScreen2('ssmod_search', 'fields', $current_search_fields);
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  /// Recherches sauvegardées
  function storedQueries() {
    $queries = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=QUERIES');
    $q=$queries->select_query(array('order'=>'grp',
				    'cond'=>array('modid'=>array('=',$this->_moid),'rtype'=>array('=','table'))));
    $r1=$queries->browse(array('select'=>$q,'selectedfields'=>'all', 'selected'=>0,'tplentry'=>TZR_RETURN_DATA,'pagesize'=>'100'));
    return $r1;
  }
  /// sauvegarde la recherche en cours
  function saveQuery() {
    if (!$this->isThereAQueryActive()) {
      setSessionVar('message', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'savequery_nothing'));
      return \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
    }
    \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('QUERIES')->input([
      'tplentry' => 'br',
      'selectedfields' => ['title', 'grp'],
      '_options' => ['local' => true]
    ]);
    \Seolan\Core\Shell::changeTemplate('Module/Table.new.html');
    \Seolan\Core\Shell::toScreen1('_', $r = ['function' => 'procSaveQuery']);
  }

  /// sauvegarde la recherche en cours
  function procSaveQuery($ar) {
    if (!$this->isThereAQueryActive()) {
      setSessionVar('message', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'savequery_nothing'));
      return \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
    }
    $_storedquery = $this->_getSession('query');
    \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('QUERIES')->procInput([
      'rtype' => 'table',
      'modid' => $this->_moid,
      'query' => serialize($_storedquery)
    ]);
    setSessionVar('message', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'savequery_done'));
    return $this->procQuery($ar);
  }

  /****m* XModTable/quickquery
   * NAME
   *   \Seolan\Module\Table\Table::quickquery - génération du formulaire de requête
   * DESCRIPTION
   *   Calcul des éléments du formulaire de requête pour l'ensemble de fiches. Le traitement
   *   du formulaire est ensuite assuré par \Seolan\Module\Table\Table::procQuery ()
   * INPUTS
   *   Passage de paramètre indirect via $ar et $_REQUEST
   ****/
  function &quickquery($ar) {
    $p = new Param($ar, []);
    $ar['table']=$this->table;
    if(empty($ar['fmoid']))
      $ar['fmoid']=$this->_moid;
    $ar['fieldssec']=$this->getFieldsSec($ar);
    $ar = $this->prepareBrowseParameters($ar);
    if (isset($ar['selectedqqfields']))
      $ar['selectedfields']=$ar['selectedqqfields'];
    //S'il y a un filtre obligatoire alors on force le champ en queryable
    if ($this->hasCompulsoryFilter())
      $this->xset->getField($this->query_comp_field)->queryable = true;

    if ($this->hasCompulsoryFilter()){
      $ar['query_comp_field'] = $this->query_comp_field;
      $ar['query_comp_field_value'] = $this->query_comp_field_value;
      $ar['query_comcp_field_op'] = $this->query_comp_field_op;
    }

    $r=$this->xset->quickquery($ar);

    // recherche sur les sous-fiches
    if ($this->submodsearch) {
      // liste id/title
      for($i=1; $i<=$this->submodmax; $i++) {
        $ssmod = 'ssmod'.$i;
        $ssmoid = $this->$ssmod;
        if ($ssmoid) {
          $mod = \Seolan\Core\Module\Module::objectFactory($ssmoid);
          $sec = $mod->getAccess();
          if (isset($sec['list'])) {
            $title = 'ssmodtitle'.$i;
            $ssmod_title = $this->$title;
            if (empty($ssmod_title))
	      $ssmod_title = $mod->getLabel();
            $r['submodules'][$i] = $ssmod_title;
          }
        }
        $r['_submodsearch'] = true;
      }
      // les critères retenus dans la recherche
      if (!empty($this->_submodules_searchselected))
	$r['submodules_searchselected'] = $this->_submodules_searchselected;
      else if ($p->is_set('ssmods_search')){

	$r['submodules_searchselected'] = array_combine($p->get('ssmods_search'), $p->get('ssmods_search'));
      }
    }
    return $r;

  }
  /// quickquery depuis le web
  public function prepareQuickquery($ar){
    $p = new Param($ar, []);

    $tplentry = $p->get('tplentry');

    $ar['tplentry'] = TZR_RETURN_DATA;

    $r = $this->quickquery($ar);

    $options = $p->get('_options');
    $r2 = ['_qq'=>$r];
    // les options du browse initial : function, peristent, pagesize, order, ...
    foreach($options as $n=>$v){
      $r2[$n] = $v;
    }
    if ($this->hasCompulsoryFilter()){
      $r2['query_comp_field'] = $this->query_comp_field;
    }
    $r2['translation_mode'] = $this->translationMode($p);

    return Shell::toScreen1($tplentry, $r2);

  }
  /// procédure d'export memorisée
  protected function prepareExportProcedure($oid){
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_IMPEXP');
    $d = $ds->display(['oid'=>$oid,'error'=>'return']);
    $pparam = json_decode($d['opparam']->raw);
    if (isset($pparam->_accountoid)){
      $account = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_ACCOUNTS')->rdisplay($pparam->_accountoid);
      if (is_array($account)){
	$pparam->ftpserver=$account['ourl']->raw;
	$pparam->ftplogin=$account['ologin']->raw;
	$pparam->ftppassword=$account['opasswd']->raw;
      } else {
	$pparam->_accountoid = null;
      }
    }
    return ['oid'=>$oid,'pparam'=>json_encode($pparam)];
  }
  protected function prepareDefaultExportProcedure(){
    $p = new Param($ar);
    if($p->get('isReporting')) {
      $whereTitle = 'title like "REP_%"';
    }
    else {
      $whereTitle = 'title not like "REP_%"';
    }
    $defaultProcedureOid = getDB()->fetchOne('select KOID from _IMPEXP where isdefault=1 and OWN=? and modid=? and type="export" and '.$whereTitle, array($GLOBALS['XUSER']->_curoid, $this->_moid));
    if($defaultProcedureOid) {
      return $this->prepareExportProcedure($defaultProcedureOid);
    }
    $defaultProcedureOid = getDB()->fetchOne('select KOID from _IMPEXP where isdefault=1 and OWN="USERS:1" and modid=? and type="export" and '.$whereTitle, array($this->_moid));
    if($defaultProcedureOid) {
      return $this->prepareExportProcedure($defaultProcedureOid);
    }
    return null;
  }
  /// Liste des procédures d'export memorisées pour ce module
  protected function browseExportProcedures(){
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_IMPEXP');
    return $ds->browse(['_options'=>['local'=>1],
			'select'=>$ds->select_query(['cond'=>['type'=>['=','export'],'modid'=>['=',$this->_moid]]]),
			'order'=>'title asc',
			'selectedfields'=>['title'],
			'tplentry'=>TZR_RETURN_DATA,
			'pagesize'=>999
			]);
  }
  /// Supprime un export sauvegardé
  function delExportProcedure($ar=null){
    $p = new \Seolan\Core\Param($ar, ['mode'=>'json']);
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_IMPEXP');
    $oid =  $p->get('storedprocedureid');
    $r = $ds->del(['oid'=>$oid,'_options'=>['local'=>1]]);
    if ($p->get('mode') == 'json'){
      if (is_array($r)){
	echo(json_encode(['ok'=>true, 'oid'=>$oid]));
	die();
      } else {
	echo(json_encode(['ok'=>false, 'oid'=>null]));
	die();
      }
    } else {
      return $r;
    }
  }
  /// Enregistre un export sauvegardé
  function procSaveExportProcedure($ar=null){
    $p = new \Seolan\Core\Param($ar, ['mode'=>'json']);
    $jsonp = $_REQUEST;
    // enlever les param. qui ne sont pas nécessaires.
    foreach(['_bdx','fromfunction','function','tplentry','moid','select','template','_linkedfield','_recordcount','_selectedok','statusFileId','storedprocedurename','storedprocedureid','_next','ftpserver','ftplogin','ftppassword','inlineRadioOptions','inlineRadioOptions1','naming_convention','_raw','_ajax'] as $k){
      unset($jsonp[$k]);
    }
    // ftp => lien vers les _ACCOUNTS
    if (!empty($_REQUEST['ftpserver']) && !empty($_REQUEST['ftplogin']) && !empty($_REQUEST['ftppassword'])){
      $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_ACCOUNTS');
      $accountOid = getDb()->fetchOne('select koid from _ACCOUNTS where atype="EXPORT" and modid=? and url=? and login=? and name=?',
				      [$this->_moid,
				       $_REQUEST['ftpserver'],
				       $_REQUEST['ftplogin'],
				       $_REQUEST['storedprocedurename']]);
      $aar = ['modid'=>$this->_moid,
	      'atype'=>'EXPORT',
	      'url'=>$_REQUEST['ftpserver'],
	      'login'=>$_REQUEST['ftplogin'],
	      'passwd'=>$_REQUEST['ftppassword'],
	      'name'=>$_REQUEST['storedprocedurename'],
	      '_options'=>['local'=>1]
	      ];
      if (!$accountOid){
	$r = $ds->procInput($aar);
	$accountOid = $r['oid'];
      } else {
	$aar['oid']=$accountOid;
	$ds->procEdit($aar);
      }
      $jsonp['_accountoid'] = $accountOid;
    }

    $json = json_encode($jsonp);
    $oid =  $p->get('storedprocedureid');
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_IMPEXP');
    $lar = ['_options'=>['local'=>1],
	    'modid'=>$this->_moid,
	    'type'=>'export',
	    'title'=>$p->get('storedprocedurename'),
	    'isdefault'=>$p->get('isdefault'),
	    'pparam'=>$json,
	    'OWN'=>$p->get('OWN'),
	    'ftpaccount'=>null // todo : c'est un lien + gestion de l'enregistrement etc
	    ];

    if (!empty($oid) != '' && \Seolan\Core\Kernel::objectExists($oid)) {
      $lar['oid'] = $oid;
      $r = $ds->procEdit($lar);
    } else {
      $r = $ds->procInput($lar);
    }
    if ($p->get('mode') == 'json'){
      if (is_array($r)){
	echo(json_encode(['ok'=>true, 'oid'=>$r['oid']]));
	die();
      } else {
	echo(json_encode(['ok'=>false, 'oid'=>null]));
      }
    } else {
      return $r;
    }
  }
  /// Enregistre un export sauvegardé et le définit par défaut pour l'utilisateur en cours
  function procSaveDefaultExportProcedure($ar=null){
    $p = new Param($ar);
    $name = $p->get('storedprocedurename');
    if($name && substr($name, 0,4) == 'REP_') {
      $whereTitle = 'title like "REP_%"';
    }
    else {
      $whereTitle = 'title not like "REP_%"';
    }
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_IMPEXP');
    $oids = getDB()->fetchCol('select KOID from _IMPEXP where modid=? and OWN=? and type="export" and isdefault != "2" and '.$whereTitle, array($this->_moid, $GLOBALS['XUSER']->_curoid));
    if(count($oids)) {
      $ds->procEdit(array('oid' => $oids, 'isdefault' => 2, 'editfields' => array('isdefault'), 'editbatch' => true));
    }
    $ar['isdefault'] = true;
    return $this->procSaveExportProcedure($ar);
  }
  /// Enregistre un export sauvegardé et le définit par défaut pour tous les utilisateurs
  function procSaveDefaultExportProcedureForEverybody($ar=null){
    if(!Shell::isRoot()) return false;
    $p = new Param($ar);
    $name = $p->get('storedprocedurename');
    if($name && substr($name, 0,4) == 'REP_') {
      $whereTitle = 'title like "REP_%"';
    }
    else {
      $whereTitle = 'title not like "REP_%"';
    }
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_IMPEXP');
    $oids = getDB()->fetchCol('select KOID from _IMPEXP where modid=? and OWN="USERS:1" and type="export" and isdefault != "2" and '.$whereTitle, array($this->_moid));
    if(count($oids)) {
      $ds->procEdit(array('oid' => $oids, 'isdefault' => 2, 'editfields' => array('isdefault'), 'editbatch' => true));
    }
    $ar['isdefault'] = true;
    $ar['OWN'] = 'USERS:1';
    return $this->procSaveExportProcedure($ar);
  }
  /// Supprime une requete sauvegardée
  function delStoredQuery($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    if(\Seolan\Core\DataSource\DataSource::sourceExists('QUERIES')) {
      $storename=$p->get('oidr');
      if(!empty($storename)) {
	$queries=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=QUERIES');
	$queries->del(array('oid'=>$storename));
      }
    }
  }

  /*
   * Section fonction générant un formulaire de recherche
   */
  /// Paramètres de la section
  public function &UIParam_query($ar=NULL){
    $xmodinfotree=\Seolan\Core\Module\Module::objectFactory($ar['itmoid']);
    $grp=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','result');
    $fs['__filterfields']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__filterfields','FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField','MULTIVALUED'=>1,
                                                                 'COMPULSORY'=>false,'TARGET'=>$this->table,
                                                                 'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','filterfields')));
    $fs['__filterfields']->doublebox=$fs['__filterfields']->onlyqueryable=true;
    $fs['__filterfields']->fgroup=$grp;

    $fs['__filterlabelin']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__filterlabelin','FTYPE'=>'\Seolan\Field\Boolean\Boolean','MULTIVALUED'=>0,
                                                                  'COMPULSORY'=>false,
                                                                  'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','filter') .' : '. \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','label_in')));
    $fs['__filterlabelin']->default=2;
    $fs['__filterlabelin']->fgroup=$grp;

    $fs['linkup']=clone $xmodinfotree->_categories->desc['linkup'];
    $fs['linkup']->fgroup=$grp;
    $fs['linkup']->compulsory=true;
    $fs['linkup']->label=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','uiquery_linktoresult');
    $fs['linkup']->__options=['fmoid'=>$ar['itmoid']];

    $fs['__createsection']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__createsection','FTYPE'=>'\Seolan\Field\Link\Link','MULTIVALUED'=>0,
								 'COMPULSORY'=>false,'TARGET'=>'TEMPLATES',
								 'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','uiquery_createsectionifnotexists')));
    $fs['__createsection']->filter='functions like "%\Seolan\Module\Table\Table::procQuery%"';
    $fs['__createsection']->fgroup=$grp;

    return $fs;
  }
  /// Sauvegarde de la section
  public function UIProcEdit_query($ar=NULL){
    $desc=$this->UIParam_query($ar);
    $ret=\Seolan\Module\InfoTree\InfoTree::postEditUIParam($desc,$ar);
    if($ret['linkup'] && $ret['__createsection']){
      $mod=\Seolan\Core\Module\Module::objectFactory($ar['itmoid']);
      $ok=getDB()->fetchOne('select count(*) from '.$mod->tname.' where KOIDSRC="'.$ret['linkup'].'" and KOIDTPL="'.$ret['__createsection'].'" limit 1');
      if(!$ok){
        $mod->insertfunction(array(
          '_local'=>true,
          'oidit'=>$ret['linkup'],
          'oidtpl'=>$ret['__createsection'],
          'section'=>array(
            'moid'=>$this->_moid,
            'function'=>'procQuery'
          )
        ));
      }
    }
    return $ret;
  }
  /// Visualisation de la section
  public function &UIView_query($ar=NULL){
    // calcul des filtres et récupération des valeurs
    $filterfields = preg_split('/\|\|/', $ar['__filterfields'], 0, PREG_SPLIT_NO_EMPTY);
    $filter['tplentry']=TZR_RETURN_DATA;
    $filter['selectedfields'] = $filterfields;
    if ($ar['__filterlabelin'] == 1)
      foreach ($filterfields as $field)
        $filter['options'][$field]['labelin'] = true;
    $queryfields = $this->query($filter);
    // mise dans l'ordre du select
    $ordered_filterfields = array_flip($filterfields);
    foreach ($queryfields as $ofield) {
      if ($ofield->field)
        $ordered_filterfields[$ofield->field] = $ofield;
    }
    $result['filterfields']=$ordered_filterfields;
    return $result;
  }


  /**
   * Paramètre d'affichage des section fonctions de type "Liste"
   * @param $ar array Paramètres de la section précédemment sauvegardés
   * @return $ret array Ensemble des champs d'édition des paramètres de la section fonction
   */
  protected function &UIParam_procQuery_result($ar=NULL){
    $grp=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','result');
    $fs['___storedquery']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'___storedquery','FTYPE'=>'\Seolan\Field\Link\Link','MULTIVALUED'=>0,
								 'COMPULSORY'=>false,'TARGET'=>'QUERIES',
								 'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','stored_query')));
    $fs['___storedquery']->filter="modid=\"{$this->_moid}\"";
    $fs['___storedquery']->fgroup=$grp;

    $fs['__viewheader']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__viewheader','FTYPE'=>'\Seolan\Field\Boolean\Boolean','MULTIVALUED'=>0,
							       'COMPULSORY'=>false,
							       'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','viewheader')));
    $fs['__viewheader']->default=1;
    $fs['__viewheader']->fgroup=$grp;

    $fs['__viewlabel']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__viewlabel', 'FTYPE'=>'\Seolan\Field\Boolean\Boolean','MULTIVALUED'=>0,
							      'COMPULSORY'=>false,
							      'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','viewlabel')));
    $fs['__viewlabel']->default=2;
    $fs['__viewlabel']->fgroup=$grp;

    $fs['__selectedfields']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__selectedfields','FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField','MULTIVALUED'=>1,
								   'COMPULSORY'=>false,'TARGET'=>$this->table,
								   'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','fields')));
    $fs['__selectedfields']->doublebox=true;
    $fs['__selectedfields']->fgroup=$grp;

    $fs['__order']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__order','FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField','MULTIVALUED'=>1,
							  'COMPULSORY'=>false,'TARGET'=>$this->table,
							  'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','order')));
    $fs['__order']->doublebox=$fs['__order']->withorder=$fs['__order']->allowrandom=true;
    $fs['__order']->fgroup=$grp;

    $fs['__resultonly']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__resultonly','FTYPE'=>'\Seolan\Field\Boolean\Boolean','MULTIVALUED'=>0,
							       'COMPULSORY'=>false,
							       'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','uiprocquery_resultonly')));
    $fs['__resultonly']->fgroup=$grp;

    $fs['__filterfields']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__filterfields','FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField','MULTIVALUED'=>1,
                                                          'COMPULSORY'=>false,'TARGET'=>$this->table,
                                                          'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','filterfields')));
    $fs['__filterfields']->doublebox=$fs['__filterfields']->onlyqueryable=true;
    $fs['__filterfields']->fgroup=$grp;
    $fs['__filterfields']->dependency=array(
      'f'=>'__resultonly',
      'op'=>array('idx'=>'=','idx2'=>'='),
      'dval'=>array('idx'=>'1','idx2'=>'2'),
      'style'=>array('idx'=>'hidden','idx2'=>''),
      'val'=>array('idx'=>'','idx2'=>''),
      'nochange'=>array('idx'=>'0','idx2'=>'1')
    );


    $fs['__filterlabelin']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__filterlabelin','FTYPE'=>'\Seolan\Field\Boolean\Boolean','MULTIVALUED'=>0,
                                                                   'COMPULSORY'=>false,
                                                                   'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','filter') .' : '. \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','label_in')));
    $fs['__filterlabelin']->default=2;
    $fs['__filterlabelin']->fgroup=$grp;
    $fs['__filterlabelin']->dependency=$fs['__filterfields']->dependency;

    $fs['__sortfields']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__sortfields','FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField','MULTIVALUED'=>1,
                                                          'COMPULSORY'=>false,'TARGET'=>$this->table,
                                                          'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','weborderselector')));
    $fs['__sortfields']->doublebox=true;
    $fs['__sortfields']->fgroup=$grp;

    $fs['__sortAscDesc']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__sortAscDesc','FTYPE'=>'\Seolan\Field\Boolean\Boolean','MULTIVALUED'=>0,
                                                          'COMPULSORY'=>false,'TARGET'=>$this->table,
                                                          'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','sortascdesc')));
    $fs['__sortAscDesc']->default=2;
    $fs['__sortAscDesc']->fgroup=$grp;

    $fs['__groupfields']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__groupfields','FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField','MULTIVALUED'=>1,
                                                          'COMPULSORY'=>false,'TARGET'=>$this->table,
                                                          'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','groupfields')));
    $fs['__groupfields']->doublebox=true;
    $fs['__groupfields']->fgroup=$grp;

    $fs['__pagesize']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__pagesize','FTYPE'=>'\Seolan\Field\ShortText\ShortText','COMPULSORY'=>false,
                                                             'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','max_nb')));
    $fs['__pagesize']->default=$this->pagesize;
    $fs['__pagesize']->listbox=false;
    $fs['__pagesize']->fgroup=$grp;

    $fs['__linktodetail']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__linktodetail','FTYPE'=>'\Seolan\Field\ShortText\ShortText','COMPULSORY'=>false,
                                                                 'FCOUNT'=>255, 'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','linktodetail')));
    $fs['__linktodetail']->fgroup=$grp;
    $fs['__linktodetail']->listbox=false;

    $fs['__linktodetaillabel']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__linktodetaillabel','FTYPE'=>'\Seolan\Field\ShortText\ShortText','COMPULSORY'=>false,'TRANSLATABLE'=>true,
                                                                      'FCOUNT'=>255, 'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','linktodetaillabel')));
    $fs['__linktodetaillabel']->fgroup=$grp;
    $fs['__linktodetaillabel']->listbox=false;

    $fs['__globalLinkField']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__globalLinkField','FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField','COMPULSORY'=>false,
                                              'MULTIVALUED'=>false, 'TARGET'=>$this->table, 'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','global_link_field')));
    $fs['__globalLinkField']->fgroup=$grp;
    $fs['__globalLinkSelectedFields']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__globalLinkSelectedFields','FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField','COMPULSORY'=>false,
                                              'MULTIVALUED'=>true, 'TARGET'=>$this->table, 'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','global_link_selectedfields')));
    $fs['__globalLinkSelectedFields']->fgroup=$grp;
    $fs['__globalLinkSelectedFields']->doublebox=true;

    $fs['__viewpagination']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__viewpagination','FTYPE'=>'\Seolan\Field\Boolean\Boolean','MULTIVALUED'=>0,
                                                                   'COMPULSORY'=>false,
                                                                   'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','viewpagination')));
    $fs['__viewpagination']->default=2;
    $fs['__viewpagination']->fgroup=$grp;

    return $fs;
  }
  /**
   * Affiche le formulaire d'édition des sections fonction de type "Liste"
   * @param $ar array Paramètres de la section précédemment sauvegardés
   * @return $ret array Ensemble des champs d'édition des paramètres de la section fonction
   */
  public function &UIEdit_procQuery($ar=NULL){
    // Récupération des champs de filtrage et ajout dans le groupe "Filtre"

    $this->UIx_xCALL()->before();

    $ret = $this->query($ar);


    foreach ($ret['fields_object'] as &$f) {

      if(!\Seolan\Core\Shell::langDataIsDefaultLanguage() && !$f->fielddef->translatable){
	$o=$f->fielddef->_newXFieldQuery(($foo=[]));

	$o->post_query_configure(new Param(array_merge(['_options'=>['local'=>1]],
						       $ar)));

	if (is_array($f->raw) && $f->fielddef->isLink())
	  $o->value=array_keys($f->raw);
	else
	  $o->value=$f->raw;

	$o->fielddef->post_query($o, []);
	$f->html = $o->getQueryText();
      }

      $f->fielddef->fgroup = $f->fgroup = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','filter');
    }

    // Paramtres d'affichage
    $other=$this->UIParam_procQuery_result($ar);
    $ret=array_merge($ret,\Seolan\Module\InfoTree\InfoTree::editUIParam($other,$ar));

    // Définit les champs disponibles uniquement en mode expert (caché par défaut aux non admin)
    $ret['__advancedfields'] = array(
      '__viewheader',
      '__viewlabel',
      '__filterfields',
      '__filterlabelin',
      '__sortfields',
      '__sortAscDesc',
      '__sortAscDesc',
      '__groupfields',
      '__viewpagination',
      '__resultonly',
      '__linktodetail',
      '__linktodetaillabel',
      '__globalLinkField',
      '__globalLinkSelectedFields',
      '___storedquery',
      '__selectedfields',
    );

    return $ret;

    // on remet la session en place ...

    $this->UIx_xCall()->after();

  }
  /**
   * sauvegarde suppression et restauration des paramètre de session qui affectent
   * les appels UIxxxx_xxxxx
   */
  protected function UIx_xCall(){
    if (!isset($this->_uix_xCall)){
      $this->_uix_xcall = new Class($this){
	private $mod = null;
	private $saved = [];
	private $props = ['query'];
	function __construct($mod){
	  $this->mod = $mod;
	}
	function before(){
	  foreach($this->props as $name){
	    if ($this->mod->_issetSession($name)){
	      $this->saved[$name] = $this->mod->_getSession($name);
	      $this->mod->_clearSession($name);
	    }
	  }
	}
	function after(){
	  foreach($this->props as $name){
	    if (isset($this->saved[$name]))
	      $this->mod->_setSession($name,$this->saved[$name]);
	  }
	}
      };
    }

    return $this->_uix_xcall;
  }
  /**
   * Enregistre les paramètres des sections fonction de type "Liste"
   * @param $ar array Récupère les paramètres par défaut
   * @return $params array Liste des paramètres transformés à enregistrer
   */
  public function &UIProcEdit_procQuery($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    // Parametres de la recherche par defaut
    if(!\Seolan\Core\Shell::langDataIsDefaultLanguage()){
      foreach($this->xset->desc as $k=>$v){
        if($v->translatable) $ar['_FIELDS'][$k]=$k;
      }
    }
    $params=$this->xset->captureQuery($ar);
    if(!\Seolan\Core\Shell::langDataIsDefaultLanguage()) unset($params['_FIELDS']);

    // Parametres d'affichage
    $other=$this->UIParam_procQuery_result($ar);
    $params=array_merge($params,\Seolan\Module\InfoTree\InfoTree::postEditUIParam($other,$ar));

    // Alias page détail
    if ($params['__linktodetail']) {
      list($ittable) = preg_split('/:/', $p->get('oidit'));
      $params['__linktodetail_oidit'] = getDB()->select("select koid from $ittable where alias=\"{$params['__linktodetail']}\"")->fetch(\PDO::FETCH_COLUMN);
      // si alias incorect, supprimer
      if (!$params['__linktodetail_oidit']){
      	\Seolan\Core\Shell::alert_admini(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','aliasunknown').' : '.$params['__linktodetail'], 'warning');
        $params['__linktodetail'] = '';
      }
    }
    return $params;
  }

  /**
   * Affiche une section fonction de type "Liste" dans un gestionnaire de rubriques
   * @param $ar array Récupère les paramètres de la section
   * @return $result array Liste des résultats
   */
  public function &UIView_procQuery($ar=NULL){
    //stockage des paramètres de la section
    $sectionFilter = $ar;
    //valeur eposté dans le formulaire
    $params = $_REQUEST['sectionopts'][$ar['itoid']];
    $ar['_storedquery'] = $ar['___storedquery'];
    if(!empty($ar['__selectedfields']))
      $selectedfields = explode('||',$ar['__selectedfields']);
    else
      $selectedfields = $this->xset->browsableFields();
    $groupfields = preg_split('/\|\|/', $ar['__groupfields'], 0, PREG_SPLIT_NO_EMPTY);
    $sortfields = preg_split('/\|\|/', $ar['__sortfields'], 0, PREG_SPLIT_NO_EMPTY);
    $orderfields = preg_split('/\|\|/', $ar['__order'], 0, PREG_SPLIT_NO_EMPTY);
    $ar['order'] = implode(',', $orderfields);
    $forceorder = isset($ar['forceorder'])?$ar['forceorder']:(isset($params['forceorder'])?$params['forceorder']:NULL);

    // nb de réponses
    $pagesizes = explode(',', $ar['__pagesize']);
    if ($params['pagesize']) {
      $pagesize = $params['pagesize'];
      $this->_setSession('pagesize_'.$ar['itoid'], $pagesize, '_TZRSF');
    } else {
      if ($this->_issetSession('pagesize_'.$ar['itoid'], '_TZRSF'))
        $pagesize = $this->_getSession('pagesize_'.$ar['itoid'], '_TZRSF');
      elseif($pagesizes[0])
        $pagesize = $pagesizes[0];
    }
    if (empty($pagesize))
      $pagesize = TZR_XMODTABLE_BROWSE_PAGESIZE;
    // recupération des parametres postés (pagination, ordre, )
    if ($params)
      $ar = array_merge($ar, $params);

    // ajouter les champs de groupage en premier dans l'ordre
    // permettre de trier sur ces champs
    $sqlorder = $ar['order'];
    if ($groupfields) {
      $grpfields = $groupfields;
      $sqlorderfields = preg_split('/\|\|/', $sqlorder, 0, PREG_SPLIT_NO_EMPTY);
      foreach ($sqlorderfields as $i => $orderfield) {
        $field = preg_replace('/( ASC| DESC)/i', '', $orderfield);
        if (($index = array_search($field, $grpfields)) !== false) {
          $grpfields[$index] = $orderfield;
          unset($sqlorderfields[$i]);
        }
      }
      $ar['order'] = implode(',', array_merge($grpfields, $sqlorderfields));
    }

    // retour depuis une fiche
    if (!$this->_issetSession('oids_'.$ar['itoid'], '_TZRSF')) {
      $do_select = true;
      $first = $params['first']? $params['first']:0;
    } else {
      // les oids
      $oids = array_unique($this->_getSession('oids_'.$ar['itoid'], '_TZRSF'));

      if ($params['oid']
          && ($index = array_search($params['oid'], $oids)) !== false) {
        $do_select = false;
        $first = floor($index/$pagesize) * $pagesize;
      }
      // on ne poste pas le filtre (pagination)
      elseif($params && !$params['insidefilter']) {
        $do_select = false;
        $storedfilter = $this->_getSession('filter_'.$ar['itoid'], '_TZRSF');
        $first = $params['first'];
      } else {
        $do_select = true;
	$first = $params['first']? $params['first']:0;
      }
    }

    // calcul des filtres et récupération des valeurs
      $filterfields=null;
    if(!empty($ar['__resultonly']) && $ar['__resultonly']!=2){
      $searchon=array_keys($this->xset->desc);
    }elseif(!empty($ar['__filterfields'])) {
      $filterfields=$searchon=preg_split('/\|\|/', $ar['__filterfields'], 0, PREG_SPLIT_NO_EMPTY);
    }
    if(isset($searchon)){
      $filter = array('tplentry' => TZR_RETURN_DATA);
      if ($params['insidefilter'] || !empty($_REQUEST['initsearch'])) {
        foreach ($searchon as $field) {
          if (!empty($filterfields) && fieldIsInArray($field,$_REQUEST) || !fieldIsInArray($field,$ar) && fieldIsInArray($field,$_REQUEST)){
            $filter[$field] = $_REQUEST[$field];
            $filter[$field.'_empty'] = $_REQUEST[$field.'_empty'];
            $filter[$field.'_op']  = $_REQUEST[$field.'_op'];
            $filter[$field.'_HID'] = $_REQUEST[$field.'_HID'];
            $filter[$field.'_FMT'] = $_REQUEST[$field.'_FMT'];
            $filter[$field.'_PAR'] = $_REQUEST[$field.'_PAR'];
          }
        }
        $ar = array_merge($ar, $filter);
      }
      // pagination
      elseif ($storedfilter) {
        $filter = array_merge($filter, $storedfilter);
        $ar = array_merge($ar, $storedfilter);
      }
    }
    $ordered_filterfields = array();
    if($filterfields){
      // préparation des inputs
      $filter['selectedfields'] = $filterfields;
      $labelin = $ar['__filterlabelin'] == 1;
      // Si filtrage actif sur un champ, les valeurs/options de ce champ ne
      // sont pas impactées par son propre filtrage contrairement aux
      // filterfields voisins. Ex: Si on filtre selon un champ "type", on ne
      // filtre pas les valeurs/options du champ "type" avec "where
      // type='un_type'" dans la query sinon les options/valeurs de ce champ
      // seront restreinte aux valeurs transmises par le filtre lui-même
      foreach ($filterfields as $field) {
        if(isset($ar['options'][$field]['labelin']))
          $filter['options'][$field]['labelin'] = $ar['options'][$field]['labelin'];
        else
          $filter['options'][$field]['labelin'] = $labelin;
        $filter['options'][$field]['searchmode'] = 'simple';
        $actualquery = $ar;
        unset($actualquery[$field]);
        $actualquery[$field] = @$sectionFilter[$field];
        $actualquery[$field.'_op'] = @$sectionFilter[$field.'_op'];
        $actualquery['selectedfield'] = array($field);
        $actualquery['getselectonly'] = true;
        $actualquery['_options']['local'] = true;
        $filter['options'][$field]['select'] = $this->procQuery($actualquery);
        if ( is_array($ar['options'][$field]) ){
          $filter['options'][$field] = array_merge($filter['options'][$field], $ar['options'][$field]);
        }
      }
      $queryfields = $this->query($filter);
      // mise dans l'ordre du select
      $ordered_filterfields = array_flip($filterfields);
      foreach ($queryfields as $ofield) {
        if ($ofield->field)
          $ordered_filterfields[$ofield->field] = $ofield;
      }
    }

    // Mémorise les OID correspondant à la requête (permet de ne pas les recalculer lors d'un changement de page)
    if ($do_select) {
      $quickquery_value = $this->quickquery;
      $this->quickquery = false;
      $ar['getselectonly'] = true;
      if(isset($forceorder)) $ar['order'] = $forceorder;
      $select = $this->procQuery($ar);
      $this->quickquery = $quickquery_value;
      // calcul des oids
      $select = preg_replace('/^select .* from '.$this->table.'/', 'select '.$this->table.'.KOID from '.$this->table, $select);
      if ($rs = getDB()->select($select))
	$oids = array_unique($rs->fetchAll(\PDO::FETCH_COLUMN));
      // verification des droits sur les enregistrements
      if($this->object_sec) {
        $tmp = array('lines_oid' => $oids);
        $this->applyObjectsSec($tmp);
        $oids = $tmp['lines_oid'];
      }
      // sauvegarde des oids en session
      $this->_setSession('oids_'.$ar['itoid'], $oids, '_TZRSF');
    }
    $result = $this->xset->browse(array(
      'tplentry' => TZR_RETURN_DATA,
      'selectedfields' => array_merge($selectedfields, $groupfields),
      'fieldssec'=>$this->fieldssec,
      'nocount' => 1,
      'tlink' => 1,
      'pagesize' => $pagesize,
      'where' => 'KOID in ("'.implode('","', array_slice($oids, $first, $pagesize)).'")',
      'order' => 'field(koid, "'.implode('","', array_slice($oids, $first, $pagesize)).'")',
      'options' => $ar['options'],
      '_options' => array('genpublishtag' => false),
      '_mode' => @$ar['_mode']
    ));
    $result['_select'] = $select;
    // calcul de la pagination
    $result['pagesize'] = $pagesize;
    $result['first'] = $first;
    $result['last'] = $last = count($oids);
    if($last-$pagesize<=0) $result['firstlastpage']=0;
    elseif($last%$pagesize==0) $result['firstlastpage']=$pagesize*((int)($last/$pagesize)-1);
    else $result['firstlastpage']=$pagesize*(int)($last/$pagesize);
    $result['firstnext']=($first+$pagesize);
    $result['firstprev']=($first-$pagesize>=0?($first-$pagesize):$first);


    if ($pagesize>0 && $pagesize < $result['last']) {
      for ($p=0, $i=0; ($i<$result['last']); $p++, $i+=$pagesize) {
        $result['pages'][$p] = $i;
	if ($first == $i) $result['currentpageindex'] = $p;
      }
    }

    // asciify keywords (url detail)
    $l_count=count($result['lines_tlink']);
    for ($i=0; $i<$l_count; $i++)
      $result['lines_tlink'][$i] = rewriteToAscii(trim($result['lines_tlink'][$i]));

    // passer le filtre au résultat
    $result['filterfields'] = $ordered_filterfields;
    // trie
    $result['defaultOrder'] = implode(',', $orderfields);
    foreach ($sortfields as $field) {
      if ($ar['__sortAscDesc'] == 1) {
        $result['sortfields'][$field] = array(
          'label' => $this->xset->desc[$field]->label . ' +',
          'selected' => $field == $sqlorder ? 'selected="selected"' : '');
        $result['sortfields']["$field DESC"] = array(
          'label' => $this->xset->desc[$field]->label . ' -',
          'selected' => "$field DESC" == $sqlorder ? 'selected="selected"' : '');
      } else
        $result['sortfields'][$field] = array(
          'label' => $this->xset->desc[$field]->label,
          'selected' => $field == $sqlorder ? 'selected="selected"' : '');
    }
    if (count($pagesizes) > 1)
      $result['pagesizes'] = $pagesizes;

    // grouper
    if ($groupfields) {
      $result['groups'] = $this->group($groupfields, $selectedfields, $result);
      // supprimer les champs de groupage (dans le header)
      for ($i=count($result['header_fields'])-1; $i>=0; $i--) {
        if (in_array($result['header_fields'][$i]->field, $groupfields))
          unset($result['header_fields'][$i]);
      }
      $result['header_fields'] = array_values($result['header_fields']);
    }

    // stocker le filtre pour la pagination
    if($params['insidefilter']) {
      $filter['order'] = $ar['order'];
      unset($filter['selectedfields']);
      $this->_setSession('filter_'.$ar['itoid'], $filter, '_TZRSF');
    }

    return $result;
  }

  // groupe le résultat d'un browse
  function group($groupfields, $selectedfields, $browse, $limit_characters = 0) {
    $groupfield =  array_shift($groupfields);
    $neededfields = array_unique(array_merge($selectedfields, $groupfields));
    foreach ($browse['lines_oid'] as $i => $oid) {
      $line_groupfield = $browse['lines_o'.$groupfield][$i];
      // Dans le cas d'un champ multivalué
      if ($line_groupfield->collection) {
        foreach ($line_groupfield->oidcollection as $j => $oidgroup) {
          if(empty($oidgroup)) continue;
	  $group_label = $line_groupfield->collection[$j]->html;
          if ($limit_characters) $group_label = substr($group_label, 0, $limit_characters);
          if (in_array($oid, $groups[$group_label]['lines_oid'])) continue;
          $groups[$group_label]['lines_oid'][] = $oid;
          $groups[$group_label]['olabel'] = $line_groupfield->collection[$j];
          foreach ($neededfields as $field) {
            $groups[$group_label]['lines_o'.$field][] = &$browse['lines_o'.$field][$i];
          }
        }
      // Champ non multivalué
      } else {
        $group_label = $line_groupfield->html;
        if ($limit_characters) $group_label = substr($group_label, 0, $limit_characters);
        if (in_array($oid, $groups[$group_label]['lines_oid'])) continue;
        $groups[$group_label]['lines_oid'][] = $oid;
        $groups[$group_label]['olabel'] = $browse['lines_o'.$groupfield][$i];
        foreach ($neededfields as $field) {
          $groups[$group_label]['lines_o'.$field][] = &$browse['lines_o'.$field][$i];
        }
      }
    }
    // appel recursif
    if (count($groupfields)) {
      foreach ($groups as $group_label => $group)
        $groups[$group_label] = $this->group($groupfields, $selectedfields, $group);
    }
    ksort($groups);
    return $groups;
  }
  /// Traitement d'une recherche
  function procQuery($ar) {
    if (\Seolan\Core\Shell::admini_mode()){
      $ar = $this->prepareBrowseParameters($ar);
    }

    $p=new \Seolan\Core\Param($ar,[['_FIELDS'=>[],'first'=>0, '_langstatus'=>null],
				   'all',
				   ['pagesize'=>[FILTER_VALIDATE_INT,[]],
                                    'order'=>[FILTER_CALLBACK,['options'=>'containsNoSQLKeyword']]]]);

    $tplentry=$p->get('tplentry');
    $clearrequest=$p->get('clearrequest');
    $storedquery=$p->get('_storedquery');
    if(!empty($storedquery)) $clearrequest=1;
    $getselectonly=$p->get('getselectonly');
    $persistent=$p->get('_persistent');
    // traitement de la taille des pages
    $pagesize=$p->get('pagesize');
    if(empty($pagesize)) $pagesize=$this->pagesize;
    if(empty($pagesize)) $pagesize=TZR_XMODTABLE_BROWSE_MAXPAGESIZE;
    $ar['pagesize']=$pagesize;
    // Ordre
    $order=$this->checkOrderFields($p->get('order'));
    $ar['order']=$order;
    $ar['table']=$this->table;
    $ar['tplentry']=TZR_RETURN_DATA;
    if(empty($ar['fmoid']))
      $ar['fmoid']=$this->_moid;
    // Filtre
    if($this->persistentquery && $persistent)
      clearSessionVar('filterquery'.$this->_moid);
    $ar['_filter']=$this->getFilter(true,$ar);
    $ar['fieldssec']=$this->getFieldsSec($ar);
    // Pour gestion de la navigation page/page en display et edit
    if($this->interactive) $this->_setSession('lastorder',$p->get('order'));
    // Stockage des requêtes nommées
    if($this->stored_query) {
      $storename=$p->get('_storename');
      $storegroup=$p->get('_storegroup');
      // Sauvegarde éventuelle de la requête
      if(!empty($storename)) {
	$st=$this->xset->captureQuery($ar);
	$queries = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=QUERIES');
	$queries->procInput(array('title'=>$storename, 'rtype'=>'table', 'modid'=>$this->_moid,'grp'=>$storegroup,
				  'query'=>addslashes(serialize($st))));
      }
    }
    // recherche sous fiches
    if ($this->submodsearch){
      if (!isset($ar['_select']) || !is_array($ar['_select'])){
	$ar['_select'] = (is_array($ar['_select'])?array($ar['_select']):array());
      }
      $this->checkSSmodFilter($ar);
      $this->checkSSmodFilterFields($ar);
    }
    // Mode de recherche
    if($this->isThereAQueryActive() && empty($clearrequest) && $this->interactive) {
      // Recuperation de la requete active s'il y en a une
      $_storedquery=$this->_getSession('query');
      $ar=array_merge($_storedquery,$ar);
    } elseif (!empty($storedquery)){
      // Recherche sauvegardée
      $this->xset->prepareQuery($ar, $storedquery);
      $_storedquery=$this->xset->captureQuery($ar);
      $this->_setSession('query',$_storedquery);
      $ar=array_merge($_storedquery,$ar);
      $ar['_storedquery']='';
    } elseif($this->interactive && sessionActive()) {
      $_storedquery=$this->xset->captureQuery($ar);
      // Mode affinage
      if((int)$clearrequest=='2'){
	$_storedquery2=$this->_getSession('query');
	$_storedquery['_FIELDS']=array_merge($_storedquery2['_FIELDS'],$_storedquery['_FIELDS']);
	$_storedquery['oids']=array_merge($_storedquery2['oids'],$_storedquery['oids']);
	$_storedquery=array_merge($_storedquery2,$_storedquery);
      }
      $this->_setSession('query',$_storedquery);
      $ar=array_merge($_storedquery,$ar);
    }
    $r=$this->xset->procQuery($ar);

    if ($this->submodsearch && is_array($r)){
      // on devrait arriver à ne pas utiliser le $this->_submodules_searchselected qui est pas dans la logique des modules/requêtes ? Pour le quickquery il ne sert plus en tout cas
      $r['_submodules_searchselected'] = $ar['_submodules_searchselected'];
    }

    if(!empty($getselectonly)) return $r;
    elseif($this->persistentquery && $persistent) setSessionVar('filterquery'.$this->_moid,$r['select']);
    if($this->object_sec) $this->applyObjectsSec($r);
    else $r['objects_sec']=$GLOBALS['XUSER']->getObjectsAccess($this, \Seolan\Core\Shell::getLangData(), $r['lines_oid']);
    $r['function']='procQuery';
    if(!empty($this->_templates) && !empty($this->btemplates)) {
      $resultDisplay=$this->_templates->display(array('oid'=>$this->btemplates,'_options'=>array('error'=>'return'),'tplentry'=>TZR_RETURN_DATA));
      \Seolan\Core\Shell::toScreen1($tplentry.'t', $resultDisplay);
    }
    if (isset($ar['fieldssec'])){
      $r['_fieldssec']=$ar['fieldssec'];
    }

    if(\Seolan\Core\Shell::admini_mode()) {
      // passage du contexte sous module
      $submodcontext = $this->subModuleContext($ar);
      $r['urlparms'] = @$submodcontext['urlparms'];
      $this->browse_actions($r, false, $ar);
      if ($this->translationMode($p))
	$r['_langstatus'] = $p->get('_langstatus');

      if ($this->quickquery) {
        $ar['tplentry']=TZR_RETURN_DATA;
        $ar['query_comp_field'] = $this->query_comp_field;
        $ar['query_comp_field_value'] = $this->query_comp_field_value;
        $ar['query_comp_field_op'] = $this->query_comp_field_op;
        $r['_qq']=$this->quickquery($ar);
        $r['_qq']['_open'] = $p->get('quickquery_open');
        $r['_qq']['_submodsearch'] = $p->get('quickquery_submodsearch');

      }
    }
    $this->browseSumFields($ar,$r,!$this->object_sec);

    $r['pagesize'] = $ar['pagesize'];

    return \Seolan\Core\Shell::toScreen1($tplentry, $r);

  }
  /// Parcours le module pour la selection d'un fichier
  public function &procQueryFiles($ar) {
    return $this->procQuery($ar);
  }
  /**
   * traite les critères de recherche issus de sous modules
   * -> _ssmodsearch[moid] = array des champs standards de la recherche
   * -> un "ET" est fait entre les modules pour le moment et par defaut
   */
  public function checkSsModFilterFields(&$ar){
    $p = new \Seolan\Core\Param($ar, array());
    $ssmods = $this->getSubModules(false);
    $ssmodsoids = array();
    $nbsearch = 0;
    foreach($ssmods as &$ssmod){
      $ssmodrequest = $p->get('_ssmodsearch'.$ssmod['moid']);
      $ssmodoids = array(); // oid trouvés pour le sous module
      if (count($ssmodrequest) > 1){ // 1 = operator
        $nbsearch++;
        $linkfield = $ssmod['linkfield'];
        $res = $ssmod['mod']->xset->procQuery(array_merge(array('tplentry'=>TZR_RETURN_DATA, 'pagesize'=>9999, '_options'=>array('local'=>1), 'selectedfields'=>array($linkfield)), $ssmodrequest));
        if ($res['lines_o'.$linkfield] && count($res['lines_o'.$linkfield]) > 0) {
          foreach($res['lines_o' . $linkfield] as $o) {
            // explode (approximatif ...) pour les cas multivalués
            $ssmodoids = array_merge($ssmodoids, explode('||', $o->raw));
          }
          // on ajoute une seule fois l'oid par sous module (array_unique)
          $ssmodsoids = array_merge($ssmodsoids, array_unique($ssmodoids));
        }
        // On ajoute les champ recherchés en session pour pouvoir les récupérer plus tard si besoin
        $this->_setSession('ssmodquery'.$ssmod['moid'], $ssmodrequest);
      } else {
	\Seolan\Core\Logs::debug(get_class($this).'::procQuery ssmod empty reqyest for '.$ssmod['mod']->getLabel().' '.$ssmod['moid']);
      }
    }
    if ($nbsearch>0){
      // on compte le nbre de foix qu'un oid a été trouvé et on garde ceux trouvés dans tous les sous modules
      $ssmodsoids = array_count_values($ssmodsoids);
      foreach($ssmodsoids as $poid=>$found){
	if ($found == $nbsearch)
	  continue;
	unset($ssmodsoids[$poid]);
      }
      // on fait la recherche finale restreinte aux oid satisfaisant les conditions sur les sous fiches
      $ar['_select'][] = $this->table.'.KOID in ("'.implode('","' ,array_keys($ssmodsoids)).'")';
    }
  }
  /// traite les filtres sur les sous fiches (présence/absence)
  /// disponible dans le qquery uniquement
  /// complète $ar['_select']
  function checkSSmodFilter(&$ar) {
    $this->_submodules_searchselected = $ar['_submodules_searchselected'] = [];
    $p = new Param($ar);
    $ssmods_search = $p->get('ssmods_search');
    for ($i=1; $i<=$this->submodmax; $i++) {
      if(!is_array($ssmods_search)) continue;
      if (in_array("$i", $ssmods_search)) {
        $op = 'is not null';
        $this->_submodules_searchselected[$i] = $i;
	$ar['_submodules_searchselected'][$i] = $i;
      } elseif (in_array("$i:not", $ssmods_search)) {
        $op = 'is null';
        $this->_submodules_searchselected["$i:not"] = "$i:not";
	$ar['_submodules_searchselected']["$i:not"] = "$i:not";
      } else
        continue;
      $moid = $this->{'ssmod'.$i};
      $linkfield = $this->{'ssmodfield'.$i};
      $ssmod = \Seolan\Core\Module\Module::objectFactory($moid);
      $table = $ssmod->table;
      // recherche des liens qui pointent vers moi
      if (empty($linkfield)){
        $links = $ssmod->xset->getXLinkDefs(NULL, $this->table);
        if (!empty($links))
	  $linkfield = array_values($links)[0];
        else
          continue;
      }
      $cond = array();
      if ($ssmod->xset->isTranslatable())
        $cond[] = "$table.LANG=\"".\Seolan\Core\Shell::getLangData()."\"";
      $filter = $ssmod->getFilter(true,$ar);
      if (!empty($filter))
        $cond[] = $filter;
      if (!\Seolan\Core\Shell::admini_mode() && $this->xset->fieldExists('PUBLISH'))
        $cond[] = 'PUBLISH=1';
      if (!empty($cond))
        $cond = ' and ' . implode(' and ', $cond);
      else
        $cond = '';
      $ar['jointcond'] = $ar['jointcond'] ?: '';
      $ar['jointcond'] .= " left join $table on {$this->xset->getTable()}.KOID = $table.$linkfield $cond";
      $ar['_select'][] = "$table.KOID $op";
    }
  }

  function UIParam_insert(){
    $ret['__selectedfields']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__selectedfields','FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField','MULTIVALUED'=>1,
								    'TARGET'=>$this->table,
								    'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','fields')));
    $ret['__selectedfields']->doublebox=true;
    $ret['__dispfgroup']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__dispfgroup','FTYPE'=>'\Seolan\Field\Boolean\Boolean','COMPULSORY'=>1,
								'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','uiinsert_dispfgroup')));
    $ret['__labelvalidate']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__labelvalidate','FTYPE'=>'\Seolan\Field\ShortText\ShortText','COMPULSORY'=>0,'TRANSLATABLE'=>true,
                                                                   'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','uiinsert_btvalidate')));
    $ret['__labelvalidate']->listbox=false;
    $ret['__nextalias']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__nextalias','FTYPE'=>'\Seolan\Field\ShortText\ShortText','COMPULSORY'=>1,
							       'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','uiinsert_okalias')));
    $ret['__nextalias']->listbox=false;
    return $ret;
  }
  /// Prepare l'insertion d'une fiche
  function insert($ar) {
    $ar = $this->triggerCallbacks(self::EVENT_PRE_CRUD, $ar);
    $p=new \Seolan\Core\Param($ar,array());

    $ar['table']=$this->table;
    $options=$p->get('options');
    $options['PUBLISH']['value']=($this->defaultispublished?'1':'2');
    $ar['options']=$options;
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ar['fieldssec']=$this->getFieldsSec($ar);
    if(empty($ar['fmoid']))
      $ar['fmoid']=$this->_moid;
    $submodcontext=$this->subModuleContext($ar);
    // insertion en sous fiche, remplir le lien
    if($submodcontext)
      $ar['options'][$submodcontext['_linkedfields'][0]]['value']=$submodcontext['_parentoids'][0];
    $r2=$this->xset->input($ar);
    // choix du templates d'affichage s'il existe
    if(!empty($this->_templates) && !empty($this->templates)) {
      $r=$this->_templates->display(array('oid'=>$this->templates,'_options'=>array('error'=>'return'),'tplentry'=>TZR_RETURN_DATA));
      \Seolan\Core\Shell::toScreen1($tplentry.'t',$r);
    }
    if($this->captcha) $r2['captcha']=$this->createCaptcha($ar);
    if($this->honeypot) $r2['honeypot']=$this->createHoneypot();
    if(\Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID)) {
      $umod=\Seolan\Core\Module\Module::objectFactory(array('toid'=>XMODWORKFLOW_TOID,'moid'=>'','tplentry'=>TZR_RETURN_DATA));
      $workflows=$umod->getWorkflows($this, 'user', 'new');
      $r2['wf_id']=$r2['wf_label']=array();
      foreach($workflows as $f) {
	$r2['wf_id'][]=$f[0];
	$r2['wf_label'][]=$f[1];
      }
    }
    // chainage insertion fiche sous-fiches
    if (!$submodcontext &&  \Seolan\Core\Shell::admini_mode()
	&& $this->interactive && $this->hasSubModules()
      && $p->get('tabsmode')!=2){ // cas popinsert
      $ar['_ssinsertmode']=1;
      $this->setSubModules($ar, $r2);
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$r2);
  }

  /// Insere une nouvelle fiche
  function procInsert($ar) {
    $ar = $this->triggerCallbacks(self::EVENT_PRE_CRUD, $ar);
    $p=new \Seolan\Core\Param($ar,array('_applyrules'=>true,'_new_comment'=>null));

    if($this->honeypot && !$this->checkHoneypot($ar)) {
      return true;
    }

    $tplentry=$p->get('tplentry');
    $noworkflow=$p->get('_noworkflow');
    $applyrules=$p->get('_applyrules');

    if($this->procInsertCtrl($ar)===true) {
      $ar['table']=$this->table;
      $ar['tplentry']=TZR_RETURN_DATA;
      $ar['fieldssec']=$this->getFieldsSec($ar);
      if(empty($ar['fmoid']))
	$ar['fmoid']=$this->_moid;
      // recuperation du contexte sous module
      $submodcontext=$this->subModuleContext($ar);
      if($submodcontext) // insertion en sous fiche, autoriser le champ lien
        $ar['fieldssec'][$submodcontext['_linkedfield'][0]]='rw';
      if(!$p->is_set('PUBLISH')) $ar['PUBLISH']=($this->defaultispublished?1:2);

      $r=$this->xset->procInput($ar);

      if (isset($r['oid']) && $this->allowcomments)
	$this->processInlineComment('newoid', $r['oid'], $p);

      /// traitements à faire systematiquement à chaque mise à jour
      $this->updateTasks($ar, $r['oid'], 'procInsert');
      if(empty($noworkflow) && \Seolan\Core\Module\Module::getMoid(XMODWORKFLOW_TOID)) {
        $umod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
        $wf=$p->get('_applywf');
        $umod->checkAndRun($this, $this, $r['oid'], 'new');
        if(!empty($wf)) {
          \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&moid='.$this->_moid.'&function=newWFCase&'.
                          'template=Module/Workflow.newcase.html&tplentry=br&oid='.
                          $r['oid'].'&wfid='.$wf);
          return \Seolan\Core\Shell::toScreen1($tplentry, $r);
        }
      }
      if($applyrules) {
        \Seolan\Module\Workflow\Rule\Rule::applyRules($this,$r['oid']);
      }
      if($this->object_sec && $this->owner_sec)
	$GLOBALS['XUSER']->setUserAccess(get_class($this),$this->_moid,\Seolan\Core\Shell::getLangData(),$r['oid'],'admin');
      if(in_array($this->savenext,array('display','edit')) && $tplentry!=TZR_RETURN_DATA){
        \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&moid='.$this->_moid.'&function='.$this->savenext.'&'.
			'template=Module/Table.'.($this->savenext == 'display' ? 'view' : 'edit').'.html&tplentry=br&oid='.
				    $r['oid'].@$submodcontext['urlparms']/* vide si pas sous module */);

      }elseif($p->get('_nextmode')=='edit') {
        \Seolan\Core\Shell::setTemplates('Module/Table.edit.html');
        \Seolan\Core\Shell::setNext();
        $_REQUEST = array(
          'oid' => $r['oid'],
          'tplentry' => 'br',
          'template' => 'Module/Table.edit.html',
          'moid' => $this->_moid,
          'function' => 'edit',
          '_linkedfields' => $submodcontext['_linkedfields'],
          '_parentoids' => $submodcontext['_parentoids'],
          '_frommoids' => $submodcontext['_frommoids'],
	  '_ssinsertmoid' => $p->get('_ssinsertmoid'),
        );
        return $this->edit([]);
      }
      // send a copy to
      if (\Seolan\Core\Shell::admini_mode()
	  && TZR_RETURN_DATA != $tplentry){
	$send = $p->get('_sendacopyto');
	if (!empty($send[$this->_moid]))
	  $this->redirectToSendACopyTo($r['oid']);
      }

    }else{
      if (\Seolan\Core\Shell::admini_mode()) {
        unset($_REQUEST['skip'], $_REQUEST['_skip']);
        \Seolan\Core\Shell::changeTemplate('Module/Table.new.html');
        \Seolan\Core\Shell::setNext();
      }
      $ar['options']=$this->xset->prepareReEdit($ar);
      $ar['tplentry']='br';
      return $this->insert($ar);
    }
    return \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }

  /// Insere une nouvelle fiche par api
  function procInsertJSon($ar = []) {
    // alimente $_REQUEST depuis php://input
    $data = $this->getJSonPostData();
    if(isset($data->attributes->alias)&&!empty($data->attributes->alias))
      $ar['aliasfieldval']=(string)$data->attributes->alias;
    if (!$this->procInsertCtrlJSon($ar, $data)) {
      return;
    }
    $ar['tplentry'] = TZR_RETURN_DATA;
    $insert = $this->procInsert($ar);
    if ($insert['oid']) {
      $ar['oid'] = $insert['oid'];
      return $this->displayJSon($ar);
    }
    $GLOBALS['JSON_START_CLASS']::registerError(500, $this->getErrorMessage());
  }

  /// alimente $_REQUEST depuis php://input
  protected function getJSonPostData() {
    $rawData = file_get_contents('php://input');
    \Seolan\Core\Logs::debug(__METHOD__ . ' ' . $rawData);
    $postData = json_decode($rawData)->data;
    foreach ($this->xset->desc as $field => $fieldDef) {
      $fieldAlias = $GLOBALS['JSON_START_CLASS']::getFieldAlias($this->_moid, $field);
      if ($fieldDef->sys || $fieldDef->readonly) {
        continue;
      }
      if(!isset($postData->attributes->$fieldAlias) && !isset($postData->relationships->$fieldAlias)) {
        continue;
      }

      $multiValuesMode = false;
      $valCnt = 1;
      if ($fieldDef->multivalued && (is_array($postData->attributes->$fieldAlias) || is_array($postData->relationships->$fieldAlias) || is_array($postData->relationships->$fieldAlias->data))) {
        $multiValuesMode = true;
        if (is_array($postData->attributes->$fieldAlias))
          $valCnt = count($postData->attributes->$fieldAlias);
        elseif(is_array($postData->relationships->$fieldAlias->data))
          $valCnt = count($postData->relationships->$fieldAlias->data);
        else
          $valCnt = count($postData->relationships->$fieldAlias);
      }
      for ($i=0; $i<$valCnt; $i++) {
        if ($multiValuesMode) {
           $jsonValue = (object) [
            'attributeValue' => $postData->attributes->$fieldAlias[$i] ?? null,
            'relationshipValue' => $postData->relationships->$fieldAlias->data[$i] ?? ($postData->relationships->$fieldAlias[$i] ?? null),
          ];
        } else {
          $jsonValue = (object) [
            'attributeValue' => $postData->attributes->$fieldAlias ?? null,
            'relationshipValue' => $postData->relationships->$fieldAlias->data ?? ($postData->relationships->$fieldAlias ?? null),
          ];
        }
        $decodedValue = $this->getJSonFieldValue($fieldDef, $jsonValue);
        if ($multiValuesMode) {
          if (isset($decodedValue->attribute)) {
            $postData->attributes->$field[$i] = $decodedValue->attribute;
          } elseif(isset($decodedValue->relationship)) {
            if (is_object($postData->relationships->$field))
              $postData->relationships->$field->data[$i] = $decodedValue->relationship;
            else
              $postData->relationships->$field[$i] = $decodedValue->relationship;
          }

          $_REQUEST[$field][$i] = $decodedValue->attribute ?? $decodedValue->relationship ?? $decodedValue->other;
          if (isset($decodedValue->_del))
            $_REQUEST[$field.'_del'][$i] = $decodedValue->_del;

        } else {
          if (isset($decodedValue->attribute)) {
            $postData->attributes->$field = $decodedValue->attribute;
          } elseif(isset($decodedValue->relationship)) {
            if (is_object($postData->relationships->$field))
              $postData->relationships->$field->data = $decodedValue->relationship;
            else
              $postData->relationships->$field = $decodedValue->relationship;
          }
          $_REQUEST[$field] = $decodedValue->attribute ?? $decodedValue->relationship ?? $decodedValue->other;
          if (isset($decodedValue->_del))
            $_REQUEST[$field.'_del'] = $decodedValue->_del;
        }
      } //end for
    }
    // traitement des insertions en sous-module
    $p = new \Seolan\Core\Param([]);
    if ($p->is_set('parent_moid') && $p->is_set('parent_oid')) {
      $parent_oid = $p->get('parent_oid');
      $parent_mod = \Seolan\Core\Module\Module::objectFactory($p->get('parent_moid'));
      $ssmods = $parent_mod->getSubModules();
      foreach ($ssmods as $ssmod) {
        if ($ssmod['moid'] == $this->_moid) {
          $_REQUEST[$ssmod['linkfield']] = $parent_oid;
        }
      }
    }
    return $postData;
  }

  protected function getJSonFieldValue($fieldDef, $json) {
    $ret = (object) [
      'attribute' => null,
      'relationship' => null,
      'other' => null,
    ];
    // traitement en fonction du type de champ
    if (is_a($fieldDef, \Seolan\Field\Boolean\Boolean::class)) {
      if (isset($json->attributeValue)) {
        if ($json->attributeValue == true)
          $ret->attribute = $fieldDef->TRUE;
        else
          $ret->attribute = $fieldDef->FALSE;
      }
    } elseif (is_a($fieldDef, \Seolan\Field\Chrono\Chrono::class) || is_a($fieldDef, \Seolan\Field\Order\Order::class) || is_a($fieldDef, \Seolan\Field\Module\Module::class)) {
      $ret->attribute = filter_var($json->attributeValue, FILTER_VALIDATE_INT);
    } elseif(is_a($fieldDef, \Seolan\Field\Real\Real::class)) {
      $ret->attribute = filter_var($json->attributeValue, FILTER_VALIDATE_FLOAT);
    } elseif(is_a($fieldDef, \Seolan\Field\Url\Url::class)) {
      $ret->attribute = filter_var($json->attributeValue, FILTER_VALIDATE_URL);
      if ( $ret->attribute === false ){
        $ret->attribute = filter_var($json->attributeValue, FILTER_VALIDATE_EMAIL);
      }
    } elseif(is_a($fieldDef, \Seolan\Field\File\File::class)) {
      $value = $json->attributeValue;
      if (is_object($value)) {
        $ret->other = [
          'type' => $value->mimetype,
          'name' => $value->originalname,
          'tmp_name' => $value->url
        ];
      }
      // cas data uri scheme
      elseif (preg_match('/^data:(.*);originalname=(.*);base64,(.*)$/', $value, $matches)) {
        $ret->other = [
          'type' => $matches[1],
          'name' => $matches[2],
          'tmp_name' => $matches[3]
        ];
      }
      // Server path
      elseif(is_file($value)) {
        $filepath = realpath($value);
        $paths = explode('/', $value);
        $ret->other = [
          'type' => mime_content_type($filepath),
          'name' => $paths[count($paths)-1],
          'tmp_name' => base64_encode(file_get_contents($filepath))
        ];
      }
      // Remove file
      elseif($value === ''){
        $ret->other = $value;
        $ret->_del = 'on';
      }
      // Bad value
      else {
        $ret->other = null;
      }
    } elseif (is_a($fieldDef, \Seolan\Field\Link\Link::class)) {
      $val = $json->relationshipValue;
      if (\Seolan\Core\Kernel::isAKoid($val->id)) {
        $ret->relationship = $val->id;
      } else {
        $ret->relationship = $GLOBALS['JSON_START_CLASS']::getLinkConf($this->_moid, $fieldDef->field)['objectprefix'] . ':' . $val->id;
      }
    } elseif(is_a($fieldDef, \Seolan\Field\StringSet\StringSet::class)) {
      if ($GLOBALS['JSON_START_CLASS']::getLinkConf($this->_moid, $field)['follow']) {
        $ret->attribute = $json->attributeValue;
      } else {
        $prefix = $GLOBALS['JSON_START_CLASS']::getSetsPrefix($this->_moid, $fieldDef->field);
        $ret->relationship = preg_replace('/^' . $prefix . '/', '', $json->relationshipValue->id);
      }
    } elseif(is_a($fieldDef, \Seolan\Field\Serialize\Serialize::class)) {
      $ret->attribute = $json->attributeValue;
    } else { // autres champs (textes ...)
      // ?? rr
      $ret->attribute = htmlspecialchars($json->attributeValue, ENT_QUOTES, 'UTF-8');
    }
    return $ret;
  }

  /// contrôle insertion par api
  function procInsertCtrlJSon($ar, $data) {
    $ok = $this->procCtrlJson($ar, $data);
    if (!$this->procInsertCtrl($ar)) {
      $GLOBALS['JSON_START_CLASS']::registerError(400, $this->getErrorMessage());
      $ok = false;
    }
    return $ok;
  }

  /// contrôle commun insert/update api
  // $_REQUEST a été alimenté avec les véritables noms de champ (voir getJSonPostData)
  function procCtrlJson($ar, $data, $insert = TRUE) {
    $p = new \Seolan\Core\Param($ar);
    $ok = TRUE;
    if (empty($data)) {
      $GLOBALS['JSON_START_CLASS']::registerError(400, 'no data');
      return FALSE;
    }
    if (empty($data->type)) {
      $GLOBALS['JSON_START_CLASS']::registerError(400, 'no type');
      return FALSE;
    }
    foreach ($this->xset->desc as $field => $fieldDef) {
      $fieldAlias = $GLOBALS['JSON_START_CLASS']::getFieldAlias($this->_moid, $field);
      if ($fieldDef->sys || $fieldDef->readonly || (!$insert && !$p->is_set($field))) {
        continue;
      }
      $value = $p->get($field);
      if ($fieldDef->compulsory && !isset($value)) {
        if (!$fieldDef->isLink())
          $GLOBALS['JSON_START_CLASS']::registerError(400, 'required value for', '/data/attributes/' . $fieldAlias);
        else
          $GLOBALS['JSON_START_CLASS']::registerError(400, 'required relationship for', '/data/relationships/' . $fieldAlias);
        $ok = FALSE;
      } elseif ($value && $fieldDef->edit_format) {
        if(is_a($fieldDef,\Seolan\Field\DateTime\DateTime::class)) {
          $fieldDef->edit_format = '([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})';
        } elseif(is_a($fieldDef,\Seolan\Field\Time\Time::class)) {
          $fieldDef->edit_format = '([0-9]{2}):([0-9]{2}):([0-9]{2})';
        }
        if(!preg_match('@' . str_replace('@', '\@', $fieldDef->edit_format) . '@', $value)) {
          $GLOBALS['JSON_START_CLASS']::registerError(400, "incorrect format ({$fieldDef->edit_format})", '/data/attributes/' . $fieldAlias);
          $ok = FALSE;
        }
      }
      // check lien
      elseif ($value && $fieldDef->isLink()) {
        if (!is_array($value)) {
          $value = [$value];
        }
        foreach ($value as $val) {
          if (!\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($fieldDef->target)->getJSon(['oid' => $val, 'selectedfields' => ['KOID'], '_filter' => $fieldDef->filter])) {
            $GLOBALS['JSON_START_CLASS']::registerError(400, "id ".$GLOBALS['JSON_START_CLASS']::cleanOid($this->_moid, $val)." not found for ", '/data/relationships/' . $fieldAlias);
            $ok = FALSE;
          }
        }
      }
    }
    return $ok;
  }

  public function UIParam_display($ar) {
    $fs['__oid']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'__oid', 'FTYPE'=>'\Seolan\Field\Link\Link', 'COMPULSORY'=>1, 'TARGET'=>$this->table,
      'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','record')));
    $fs['__oid']->autocomplete=0;
    $fs['__selectedfields']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'__selectedfields', 'FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField', 'MULTIVALUED'=>1,
      'TARGET'=>$this->table,
      'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','fields')));
    $fs['__selectedfields']->doublebox=1;
    $fs['__usegroup']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'__usegroup', 'FTYPE'=>'\Seolan\Field\Boolean\Boolean', 'MULTIVALUED'=>0, 'COMPULSORY'=>false,
      'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','uiinsert_dispfgroup')));
    $fs['__usegroup']->default = 2;

    // choix des sous-modules et de leurs champs
    $ssmod_grp = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','ssmod');
    for($i=1; $i<=$this->submodmax; $i++) {
      if ($this->{'ssmod'.$i}) {
        $mod = \Seolan\Core\Module\Module::objectFactory($this->{'ssmod'.$i});
        $fs['__view_'.$this->{'ssmod'.$i}]=\Seolan\Core\Field\Field::objectFactory((object)array(
          'FIELD'=>'__view_'.$this->{'ssmod'.$i}, 'FTYPE'=>'\Seolan\Field\Boolean\Boolean', 'MULTIVALUED'=>0,
          'COMPULSORY'=>false,
          'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','view').' '.$mod->getLabel()));
        $fs['__view_'.$this->{'ssmod'.$i}]->default = 2;
        $fs['__view_'.$this->{'ssmod'.$i}]->fgroup = $ssmod_grp;

        $fs['__selectedfields_'.$this->{'ssmod'.$i}]=\Seolan\Core\Field\Field::objectFactory((object)array(
          'FIELD'=>'__selectedfields_'.$this->{'ssmod'.$i}, 'FTYPE'=>'\Seolan\Field\DataSourceField\DataSourceField','MULTIVALUED'=>1,
          'TARGET'=>$mod->table,
          'LABEL'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','fields').' '.$mod->getLabel()));
        $fs['__selectedfields_'.$this->{'ssmod'.$i}]->doublebox = 1;
        $fs['__selectedfields_'.$this->{'ssmod'.$i}]->fgroup = $ssmod_grp;
      }
    }
    return $fs;
  }
  public function UIView_display($params) {
    $p = new \Seolan\Core\Param($params);
    $showsubmodules = 0	;
    $requested_submodules=array();
    $options = $params['options'] ? $params['options'] : array();
    $oid = $p->get('__oid');
    if (!empty($_REQUEST['oid']) && \Seolan\Core\Kernel::getTable($_REQUEST['oid'])==$this->table) {
      $oid = $_REQUEST['oid'];
    }
    // controle des droits
    if (!$this->secure($oid, 'display'))
      return false;

    // gestion des sous-modules
    for($i=1; $i<=$this->submodmax; $i++) {
      if ($this->{'ssmod'.$i} && $params['__view_'.$this->{'ssmod'.$i}] == 1) {
        $requested_submodules[] = $this->{'ssmod'.$i};
        $options[$this->{'ssmod'.$i}]['selectedfields'] = explode('||', $params['__selectedfields_'.$this->{'ssmod'.$i}]);
        $showsubmodules = 1;
      }
    }

    $result = $this->display(array(
      'tplentry' => TZR_RETURN_DATA,
      'oid' => $oid,
      'reorderfields' => true,
      'selectedfields' => explode('||',$params['__selectedfields']),
      'ssmoid' => 'all',
      'requested_submodules' => $requested_submodules,
      'options' => $options,
      '_options' => array('genpublishtag' => false, 'error'=>'return')
    ));
    if(!is_array($result))
      $result = array('message'=>$result);

    // canonical link
    if (!empty($_REQUEST['alias'])) {
      global $XSHELL, $HOME_ROOT_URL;
      $result['canonical_url'] = $HOME_ROOT_URL.$_REQUEST['alias'].'.html?oid='.$oid.'&keywords='.rewriteToAscii($result['link']);
      header('Link: <'.$result['canonical_url'].'>; rel="canonical"');
      if (is_callable([$XSHELL,'setHeadTitle'])) $XSHELL->setHeadTitle(strip_tags($result['link']));
    }

    // si on vient d'une liste
    if (!empty($_REQUEST['from']) && !empty($_REQUEST['oid'])) {
      // test que l'oid appartient à une liste
      $oids = $this->_getSession('oids_'.$_REQUEST['from']['itoid'], '_TZRSF');
      if (!empty($oids) && ($current_index = array_search($oid, $oids)) !== false) {
        $result['_from']['alias'] = $_REQUEST['from']['alias'];
        $result['_from']['oidit'] = $_REQUEST['from']['oidit'];
        $result['_from']['itoid'] = $_REQUEST['from']['itoid'];
        $result['_prev_oid'] = $oids[$current_index-1];
        $result['_next_oid'] = $oids[$current_index+1];
      }
    }
    // indiquer au template d'afficher les sous-modules
    $result['_showsubmodules'] = $showsubmodules;
    return $result;
  }

  public function browseJSon($ar) {
    $p = new \Seolan\Core\Param($ar);
    $ar['tplentry'] = TZR_RETURN_DATA;
    $brparams = $ar;
    if ($p->is_set('parent_moid') && $p->is_set('parent_oid')) {
      $parent_oid = $p->get('parent_oid');
      $parent_mod = \Seolan\Core\Module\Module::objectFactory($p->get('parent_moid'));
      $disp = $parent_mod->displayJSon(['oid' => $parent_oid]);
      if (empty($disp)) {
        return NULL;
      }
      $ssmods = $parent_mod->getSubModules();
      foreach ($ssmods as $ssmod) {
        if ($ssmod['moid'] == $this->_moid) {
          $brparams['cond'][$ssmod['linkfield']] = ['=', $parent_oid];
        }
      }
    }
    if ($this->interactive) {
      // gestion de la pagination
      if ($_REQUEST['page']['size'] && $_REQUEST['page']['size'] > 0) {
        $brparams['pagesize'] = $_REQUEST['page']['size'];
      }
      if ($_REQUEST['page']['offset'] && $_REQUEST['page']['offset'] >= 0) {
        $brparams['first'] = $_REQUEST['page']['offset'];
      }
      // gestion des filtres
      $where = [];
      foreach ($_REQUEST['filter'] as $key => $values) {
	if(!is_array($values) ){
	  // utilisation possible du séparateur , pour les valeurs multiples
	  $values = explode(',', $values);
	  foreach($values as $iv=>$v){
	    $values[$iv] = urldecode($v);
	  }
	}
	\Seolan\Core\Logs::debug(__METHOD__." filter -> key='$key', value='".implode(',',$values)."'");
        if (empty($values)) {
          continue;
        }
        $field = \Seolan\Core\Json::getFieldFromAlias($this->_moid, $key);
        $fieldObject = $this->xset->getField($field);
        if (!$fieldObject)
          continue;
        $o = $fieldObject->_newXFieldQuery();

	$o->value=[];

	$valkey = 0;
	foreach ($values as $valkey=>$value) {
	  if ($fieldObject && $fieldObject->isLink() && !\Seolan\Core\Kernel::isAKoid($value)) {
	    //champ lien sans prefixe de table
	    $value = $fieldObject->target.':'.$value;
	  }
	  $o->value[] = $value;
	}

	$o->op = $brparams['options'][$key]['op'] ?? $_REQUEST['filter'][$key . '_op'];
	if(is_array($o->op) && $o->op[$valkey]) {
	  $o->op = $o->op[$valkey];
	}

        $fieldObject->post_query($o, $brparams);

        $where[] = $o->rq;
      }
      $op = strtoupper($_REQUEST['filter']['op']);
      if ($op != 'OR')
        $op = 'AND';
      $brparams['where'] = implode(" $op ", array_filter($where));
      $brparams['selectedfields'] = ['KOID'];
    } else {
      $brparams['pagesize'] = -1;
      $brparams['nocount'] = 1;
    }
    $brparams['_local'] = true;
    $browse = $this->browse($brparams);

    $objects = [];

    foreach ($browse['lines_oid'] as $oid) {
      $ar['oid'] = $oid;
      $ar['_local'] = true;
      $objects[] = $this->displayJSon($ar);
    }

    if ($browse['pages']) {
      \Seolan\Core\Json::registerPagination($browse['pagesize'], $browse['first'], $browse['pages']);
    }

    return $objects;
  }
  /// object metadata for JSON format
  /// @todo gestion des alias
  public function getJSonTypeMeta($params){
    $fieldssec = $this->getFieldsSec($params);
    $selectedfields = $params['selectedfields'];
    if (empty($selectedfields) || $selectedfields == 'all') {
      $selectedfields = $this->xset->orddesc;
    }
    if (isset($selectedfields['UPD'])) {
      unset($selectedfields['UPD']);
    }
    $meta = ['type_comment'=>$this->comment,
             'type_title'=>$this->getLabel(),
	     'attributes_descriptions'=>[]];
    foreach($this->xset->orddesc as $k){
      if (!in_array($k, $selectedfields)
	  || (!empty($fieldssec[$k]) && $fieldssec[$k] == 'none')){
	continue;
      }
      $meta['attributes_descriptions'][$k] = "{$this->xset->desc[$k]->label} : {$this->xset->desc[$k]->acomment}";
    }
    return $meta;
  }
  /// display object for JSON format
  public function displayJSon($ar) {
    $ar = $this->triggerCallbacks(self::EVENT_PRE_CRUD, $ar);
    $p = new \Seolan\Core\Param($ar);
    $oid = $p->get('oid');

    if (empty($ar['fmoid']))
      $ar['fmoid'] = $this->_moid;
    $ar['fieldssec'] = $this->getFieldsSec($ar);
    $ar['_filter'] = $this->getFilter(true, $ar);
    $json = $this->xset->getJSon($ar);
    if (empty($json)) {
      $GLOBALS['JSON_START_CLASS']::registerError(404, 'entity not found / not created');
      return NULL;
    }
    $json['type'] = $ar['type'] ?? $ar['alias'];
    $json['id'] = $GLOBALS['JSON_START_CLASS']::cleanOid($this->_moid, $oid);

    //on recupère la date de maj
    $lst_upd = $json['lst_upd'];

    $sub = ['oid' => $oid];
    $ar['pagesize'] = -1;
    $ar['nocount'] = 1;
    $this->setSubModules($ar, $sub);
    foreach ($sub['__ssmod'] as $i => $browse) {
      //pas de droit sur le module on ne voit rien
      if(!$sub['__ssaccess'][$i]['list']) {
        continue;
      }
      $ssmoid = $sub['__ssprops'][$i]['_moid'];
      $ssmodAlias = \Seolan\Core\Json::getSubModuleAlias($this->_moid,$i,$ssmoid);
      $ssmodFields = \Seolan\Core\Json::getSelectFieldsForSubModule($ssmoid);
      if (!$ssmodAlias || empty($browse['lines_oid'])) {
        continue;
      }
      // sous module inclus
      if ($ar['ssmodoptions'][$ssmoid]['selectedfields'] || $ssmodFields) {
        if (isset($ar['ssmodoptions'][$ssmoid]['follow'])) {
          $to = 'attributes';
        } else {
          $to = 'relationships';
        }
        $params = \Seolan\Core\Json::getModuleConf($ssmoid)?\Seolan\Core\Json::getModuleConf($ssmoid):array();
        if(is_array($ssmodFields)){
          $params['selectedfields'] = $ssmodFields;
        }
        $params = array_merge($params,(array) $ar['ssmodoptions'][$ssmoid]);
        $params['cond'] = ['KOID' => ['=', $browse['lines_oid']]];
        $browseData = \Seolan\Core\Module\Module::objectFactory($ssmoid)->browseJSon($params);
        //date de maj des données
        foreach($browseData as $obj){
          if($obj['lst_upd']>$lst_upd)
            $lst_upd = $obj['lst_upd'];
        }
        $json[$to][$ssmodAlias] = ['data'=>$browseData];
      } elseif(!\Seolan\Core\Json::getGlobalParam('hideNullValue') || count($browse['lines_oid'])  ) {
        //date de maj des données
        foreach($browse['lines_oid'] as $objOid){
          $table=\Seolan\Core\Kernel::getTable($objOid);
          //Logs::getLastUpdate se base sur la langue de base, on veux ici le last update dans toutes les langues
          $ors = getDB()->fetchRow('SELECT dateupd as lst_upd FROM LOGS WHERE object=? and etype in ("update") UNION SELECT max(UPD) as lst_upd FROM '.$table.' WHERE KOID=? order by lst_upd desc limit 0,1',
                                   array($objOid,$objOid));
          if($ors['lst_upd']>$lst_upd)
            $lst_upd = $ors['lst_upd'];
        }
        $xtarget = new \Seolan\Field\Link\Link((object) [
          'FTYPE' => '\Seolan\Field\Link\Link', 'TARGET' => $sub['__ssprops'][$i]['table'], 'MULTIVALUED' => true]);
        $json['relationships'][$ssmodAlias] = ['data'=>$xtarget->getJSon(implode('||', $browse['lines_oid']), ['fmoid' => $ssmoid])];
      }

    }
    if (empty($lst_upd)) { // cas non publié
      \Seolan\Core\Json::registerError('404', 'entity not found');
      return null;
    }
    $json['lst_upd'] = $lst_upd;
    return $json;
  }

  /// affichage d'un objet (une ligne) contenu dans le module table
  public function display($ar=NULL) {
    $ar = $this->triggerCallbacks(self::EVENT_PRE_CRUD, $ar);
    $p=new \Seolan\Core\Param($ar,array());

    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $ssmoid=$p->get('ssmoid');
    $datalang=false;

    // On demande l'affiche d'un sous module d'une fiche seulement
    if(!empty($ssmoid) || $ssmoid=='all'){
      $ssmods=array();
      for($i=1;$i<=$this->submodmax;$i++) {
          $f='ssmod'.$i;
          $ssmods[$i]=$this->$f;
      }
      if($ssmoid!='all' && !in_array($ssmoid,$ssmods)) return;
      $r2=array('oid'=>$oid);
    }
    if (\Seolan\Core\Shell::admini_mode()) {
      if($this->trackaccess) $ar['accesslog']=1;
      $submodcontext = $this->subModuleContext($ar);
      if (is_array($submodcontext) && $submodcontext['_frommoids']) $this->dependant_module = $submodcontext['_frommoids'][0];
      // Gestion de la nav page/page
      if($this->interactive){
          if($p->is_set('navdir')){
              list($navprev, $navnext)=$this->mkNavParms($ar, $submodcontext);
              if($p->get('navdir')=='next') $_REQUEST['oid']=$navnext;
              else $_REQUEST['oid']=$navprev;
          }
          $this->setNavActions($p->get('oid'), 'display', 'Module/Table.view.html', $submodcontext);
          if($this->trackaccess) $ar['accesslog']=1;
      }
    }
    if(empty($ssmoid) || $ssmoid=='all'){
      $oid=$p->get('oid');
      $ar['table']=$this->table;
      $ar['tplentry']=TZR_RETURN_DATA;
      if(empty($ar['fmoid']))
	$ar['fmoid']=$this->_moid;
      $ar['fieldssec']=$this->getFieldsSec($ar);
      $ar['_filter'] = $this->getFilter(true,$ar);

	// la donnée n'existe pas toujours (freelang | auto_translate= 0 | deleted)
      if (\Seolan\Core\Shell::admini_mode()) {
          $translatable = $this->xset->getTranslatable();
          if ((TZR_LANG_FREELANG == $translatable)||
          $translatable && TZR_DEFAULT_LANG != \Seolan\Core\Shell::getLangData($p->get('LANG_DATA'))
          ){
              $datalang = true;
              $ar['_options']['error'] = 'return';
          }
      }

      $ar['numberOfColumns']=$this->numberOfColumns;
      $r2=$this->xset->display($ar);

      if ($datalang && !is_array($r2)){
          \Seolan\Core\Shell::setNextData('message', 'Data does not exists.');
          \Seolan\Core\Shell::setNext($this->getMainAction());
          return;
      }

      // choix du templates d'affichage s'il existe
      if(!empty($this->_templates) && !empty($this->templates)) {
          $r=$this->_templates->display(array('oid'=>$this->templates,'_options'=>array('error'=>'return'),'tplentry'=>TZR_RETURN_DATA));
          \Seolan\Core\Shell::toScreen1($tplentry.'t',$r);
      }
      if(\Seolan\Core\Shell::admini_mode() && $this->object_sec) {
          $sec=$GLOBALS['XUSER']->getObjectAccess($this, \Seolan\Core\Shell::getLangData(),$oid);
          $sec=array_flip($sec[0]);
          $r2['object_sec']=$sec;
      }
    }
    // passage du context sous module
    if (\Seolan\Core\Shell::admini_mode()) {
      $r2['urlparms'] = @$submodcontext['urlparms'];
    }
    if(is_array($r2)) $this->setSubModules($ar, $r2);

    $this->prepareComments($r2, $oid);

    return \Seolan\Core\Shell::toScreen1($tplentry,$r2);
  }
  function displayJsonData($ar){
    $p = new \Seolan\Core\Param($ar,array());
    $tplentry = $p->get('tplentry');

    $res = [
      'moduleJsonAlias' => \Seolan\Core\Json::getModuleAlias($this->_moid)
    ];

    $uri = \Seolan\Core\Json::getJsonUri();
    $uri .= '/'.$res['moduleJsonAlias'];

    $res['oid'] = $GLOBALS['JSON_START_CLASS']::cleanOid($this->_moid, $p->get('oid'));
    $uri .= '/'.$res['oid'];
    $res['jsonEndPoint'] = $uri;

    return \Seolan\Core\Shell::toScreen1($tplentry,$res);
  }
  /// Recupere la securité des champs
  function getFieldsSec($ar){
    if (!in_array('rwv', $GLOBALS['XUSER']->getObjectAccess($this, \Seolan\Core\Shell::getLangData())[0])) {
      $ar['fieldssec']['PUBLISH'] = 'ro';
    }
    if(!empty($ar['fieldssec']) && is_array($ar['fieldssec'])) {
      if(!is_array($this->fieldssec)) return $ar['fieldssec'];
      else return array_merge($this->fieldssec,$ar['fieldssec']);
    } else return $this->fieldssec;
  }

  /// Recherche les data (browse) des sous modules
  function getSubModules($dependentOnly = false) {
    static $cache=array();
    $cache_name=$dependentOnly?'1':'0';
    if(isset($cache[$cache_name])) return $cache[$cache_name];

    $ssmods=array();
    for($i=1;$i<=$this->submodmax;$i++) {
      if ($dependentOnly && !$this->{'ssmoddependent'.$i})
        continue;
      $f='ssmod'.$i;
      $ssmods[$i]=$this->$f;
    }
    $r = array();
    foreach($ssmods as $i=>$mymoid) {
      if(!empty($mymoid)) {
	$mod1=\Seolan\Core\Module\Module::objectFactory($mymoid);
	if(!is_object($mod1)) continue;
	$tab1=&$mod1->xset;
	// recherche des liens qui pointent vers moi
	unset($linkfield, $fieldname);
	$fieldname=$this->{'ssmodfield'.$i};
	if(!empty($fieldname)) {
	  $o=$tab1->getField($fieldname);
	  if(!empty($o)) {
	    $linkfield = $fieldname;
	  }
	}
	if(empty($linkfield)) {
	  $links1=$tab1->getXLinkDefs();
	  foreach($links1 as $j=>$field) {
	    $o=$tab1->getField($field);
	    if($o->get_target()==$this->table)
	      $linkfield = $field;
	  }
	}
	if (!empty($linkfield)) {
	  $r[]= array('ssmodindex'=>$i, 'moid'=>$mymoid, 'xset'=>$tab1, 'linkfield'=>$linkfield, 'mod'=>$mod1);
	}
      }
    }
    $cache[$cache_name]=&$r;
    return $cache[$cache_name];
  }

  function hasSubModules($moid=NULL){
    for($i=1;$i<=$this->submodmax;$i++) {
      $f='ssmod'.$i;
      $tmoid=$this->$f;
      if(!empty($tmoid) && (empty($moid) || $tmoid==$moid)) return true;
    }
    return false;
  }

  /// Recherche des données dans les sous-modules, en lecture
  /// ssmoid => all pour calculer tous les sous-modules,
  ///           ou moid du sous-module demandé
  /// requested_submodules => tableau des moid des sous-modules à calculer (défaut tous)
  ///                         l'ordre des sous-modules est conservé
  function setSubModules($ar, &$r, $unsetLinkfield = true) {
    $p=new \Seolan\Core\Param($ar,array());
    $options=$p->get('options');
    $frommoids=$p->get('_frommoids');
    $ssmoid=$p->get('ssmoid');
    // traitement des sous modules
    $ssmods=array();
    $tab = 1;
    for($i=1;$i<=$this->submodmax;$i++) {
      $f='ssmod'.$i;
      $moid=$this->$f;
      if($moid) {
        $this->{'ssmodtab'.$i} = ++$tab;
      }
      if (empty($ssmoid) || $ssmoid == 'all' || $moid == $ssmoid || (is_array($ssmoid) && in_array($moid, $ssmoid))) {
        $ssmods[$i]=$moid;
      }
    }
    $requested_submodules = $p->get('requested_submodules');
    $r['__ssmod']=array();
    $r['__ssaccess']=array();
    $r['__ssinsert']=array();
    $r['__ssprops']=array();
    foreach($ssmods as $i=>$mymoid) {
      if (isset($requested_submodules) && !in_array($mymoid, $requested_submodules)) {
        $r['__ssmod'][]=array();
        $r['__ssaccess'][]=array();
        $r['__ssinsert'][]=false;
        $r['__ssprops'][]=array();
        continue;
      }
      if(!empty($mymoid)) {
	// recherche du module concerné par la sous-fiche
	$mod1=\Seolan\Core\Module\Module::objectFactory($mymoid);
	if(!is_object($mod1)) continue;
	$sec=$mod1->getAccess();
	$ins=$mod1->secure('','insert');
        $linkfield=NULL;
	if(isset($sec['ro']) || (isset($sec['list']) && $mod1->objectSecurityEnabled())){
	  // recherche de la table qui contient les données
	  $tab1=&$mod1->xset;

	  // on change le titre si nécessaire
	  if(!empty($this->{'ssmodtitle'.$i})) $mod1->modulename=$this->{'ssmodtitle'.$i};

          $links1=NULL;

	  // on regarde si le champ a ete fixe
	  $linkfield=$this->{'ssmodfield'.$i};
	  if(!$tab1->fieldExists($linkfield)) $linkfield=NULL;

	  // recherche des liens qui pointent vers moi
	  if(empty($linkfield)) $links1=$tab1->getXLinkDefs(NULL,$this->table);
	  if(!empty($links1)) $linkfield=array_values($links1)[0];

	  // on fait le browse si on a trouve le champ
	  if(!empty($linkfield)) {
	    $order1=$p->get('_order');
	    $order1=$order1[$mod1->_moid];
	    if(empty($order1)) $order1=$mod1->order;
	    if(!empty($ssmoid) || !\Seolan\Core\Shell::admini_mode()){
	      if(!empty($options[$mymoid]['selectedfields'])){
		if($listfield=$options[$mymoid]['selectedfields']=='all') $listfield=$tab1->getFieldsList();
		else $listfield=$options[$mymoid]['selectedfields'];
	      } else {
                $listfield=$tab1->browsableFields();
              }
	    }elseif(!empty($options[$mymoid]['selectedfields'])){
	      $listfield=$options[$mymoid]['selectedfields'];
	    }else{
	      $listfield=array('KOID');
	    }
            $linkfieldIndex = array_search($linkfield, $listfield);
            if ($linkfieldIndex !== false && empty($options[$mymoid]['selectedfields'][$linkfield]) && $unsetLinkfield) {
              unset($listfield[$linkfieldIndex]);
            }
            $selectedfields = $listfield;
	    // donneer une chance a une classe fille d'étendre le browse des sous fiches
	    if (isset($options[$mymoid]['cond'])) {
              $cond = $options[$mymoid]['cond'];
              $cond[$linkfield] = array('=',$r['oid']);
            } else
              $cond = array($linkfield=>array('=',$r['oid']));

            $pagesize=$options[$mymoid]['pagesize'] ?? 200;
            $ar['pagesize'] = $pagesize;

	    if(!empty($r['oid'])){
	      if($tab1->fieldExists('PUBLISH'))
		array_push($listfield, 'PUBLISH');
	      $select=$tab1->select_query(['fields'=>$listfield,'order'=>$order1, 'cond'=>$cond, 'where' => @$options[$mymoid]['where']]);
	      $params=array('ssmodule'=>&$mod1,
			    '_fromtabs'=>$this->{'ssmodtab'.$i},
			    'frommoid'=>$this->_moid,
			    'parentoid'=>$r['oid'],
			    'linkedfield'=>$linkfield,
			    'select'=>$select,
			    'selectedfields'=>$selectedfields,
			    'selectedtypes'=>array(),
			    'selectedprops'=>array(),
                            'order'=>$order1,
			    'options' => $options[$mymoid]);
	      $b1 = $this->browseSubModule($ar, $params, $r);
	    }else{
	      if (isset($ar['_ssinsertmode']))
		$smSelect='select KOID from '.$mod1->table.' where 0 order by KOID';
	      else
		$smSelect='select KOID from '.$mod1->table.' order by KOID';

	      $params=array('ssmodule'=>&$mod1,
			    'frommoid'=>$this->_moid,
			    'parentoid'=>'',
			    'linkedfield'=>$linkfield,
			    'select'=>$smSelect,
			    'pagesize'=>1,
			    'selectedfields'=>$selectedfields,
			    'selectedtypes'=>[],
			    'selectedprops'=>[],
                            'options' => $options[$mymoid]);

	      $b1 = $this->browseSubModule($ar, $params, $r);

	    }

	    // preparation des données pour affichage
	    $r['__ssmod'][]=$b1;
	    $r['__ssaccess'][]=$sec;
	    $r['__ssinsert'][]=$ins;
	    $r['__ssprops'][]=get_object_vars($mod1);
	    $r['__ssprops'][count($r['__ssprops'])-1]['linkedfield']= $linkfield;
	    $r['__ssprops'][count($r['__ssprops'])-1]['dependant_module']= $frommoids[0];
	    $r['__ssprops'][count($r['__ssprops'])-1]['activate_additem']= $this->{'ssmodactivate_additem'.$i};
	  }
	}else{
	  $r['__ssmod'][]=array();
	  $r['__ssaccess'][]=$sec;
	  $r['__ssinsert'][]=$ins;
	  $r['__ssprops'][]=get_object_vars($mod1);
	  $r['__ssprops'][count($r['__ssprops'])-1]['linkedfield']= $linkfield;
	  $r['__ssprops'][count($r['__ssprops'])-1]['dependant_module']= $frommoids[0];
	  $r['__ssprops'][count($r['__ssprops'])-1]['activate_additem']= $this->{'ssmodactivate_additem'.$i};
	}
      }
    }
  }

  /// Appel au browse d'un module dans le cadre de l'edition ou de la visualisation de la fiche parente
  function browseSubModule(&$ar, &$browseparm, &$result){
    $p = new \Seolan\Core\Param($ar, array('pagesize'=>200));
    $pagesize = $p->get('pagesize');
    // champ de tri
    $orderfield = $p->get('orderfield');
    // module trie
    $ordermoid = $p->get('ssmodorder');
    // champ en edition demande(s)
    $editfield = $p->get('editfield');
    // module en edition
    $editmoid = $p->get('ssmoidedit');
    // module en enregistrement
    $savemoid = $p->get('ssmoidsave');
    // faudrait extaire les champs du champ editer pour pas avoir de conflits ...
    $select = $browseparm['select'];
    $selectedfields = $browseparm['selectedfields'];
    $ssmodule = &$browseparm['ssmodule'];
    $order = @$browseparm['order'];
    $editfields = '';
    // traitement de la mise   jour des sous fiches
    if (!empty($editfield) && $savemoid == $browseparm['ssmodule']->_moid) {
      if ($editfield != 'all')
	$editfieldsname = array($editfield);
      else
	$editfieldsname = $browseparm['selectedfields'];

      $lar = array();
      // on construit toutes les valeurs dans un contexte qui sera local
      foreach($editfieldsname as $foo=>$fn){
	$lar[$fn] = $p->get($fn);
	$lar[$fn.'_HID'] = $p->get($fn.'_HID');
	// a completer pour les champs complexes etc ?
	// semble ok texte, oui/non, date, liens liste de valeurs
      }
      $lar['tplentry']=TZR_RETURN_DATA;
      $lar['editfields']=$editfieldsname;
      // Selection des oid uniquement du module concerné
      $lar['oid']=$p->get('ssoid'.$savemoid);
      $lar['_options']=array('local'=>true);
      if($browseparm['ssmodule']->object_sec){
        foreach($lar['oid'] as $i=>$foo){
          $sec=$browseparm['ssmodule']->secure($foo,'procEdit');
          if(!$sec) unset($lar['oid'][$i]);
        }
      }else{
        $sec=$browseparm['ssmodule']->secure('','procEdit');
        if(!$sec) $lar['oid']=array();
      }
      if(!empty($lar['oid'])){
        $browseparm['ssmodule']->procEdit($lar);
      }
      // ajout des prop pour focus
      $browseparm['ssmodule']->focused=true;
    }

    // traitement du passage en mode edit
    if (!empty($editfield) && $editmoid == $browseparm['ssmodule']->_moid) {
      if ($editfield == 'all')
	$editfields = $browseparm['selectedfields'];
      else
	$editfields = array($editfield);
      // ajout des prop
      $browseparm['ssmodule']->focused=true;
      $browseparm['ssmodule']->edited=true;
      $browseparm['ssmodule']->editfield=$editfield;
    }
    // traitement du tri
    if (!empty($orderfield) && $ordermoid == $browseparm['ssmodule']->_moid){
      $order = $orderfield;
      $browseparm['ssmodule']->focused=true;
    }

    // traitement du contexte sous module
    if ($submodcontext = $this->subModuleContext($ar)) {
      $parentoids = $submodcontext['_parentoids'];
      array_unshift($parentoids, $browseparm['parentoid']);
      $linkedfields = $submodcontext['_linkedfields'];
      array_unshift($linkedfields, $browseparm['linkedfield']);
      $frommoids = $submodcontext['_frommoids'];
      array_unshift($frommoids, $browseparm['frommoid']);
    } else {
      $parentoids = array($browseparm['parentoid']);
      $linkedfields = array($browseparm['linkedfield']);
      $frommoids = array($browseparm['frommoid']);
    }
    // browse std du sous module avec les editfields et order
    return $ssmodule->browse(array('_options'=>array('local'=>true),
                                  'select'=>$select,
                                  'tplentry'=>TZR_RETURN_DATA,
                                  'selected'=>'0',
                                  'selectedfields'=>$selectedfields,
                                  'pagesize'=>$pagesize,
                                  'first'=>$p->get('first'),
                                  'editfields'=>$editfields,
                                  'order'=>$order,
                                  'assubmodule'=>true,
                                  'options'=>@$browseparm['options'],
                                  'nocount'=>@$browseparm['options']['nocount'],
                                  '_fromtabs'=>@$browseparm['_fromtabs'],
                                  '_frommoids'=>$frommoids,
                                  '_parentoids'=>$parentoids,
                                  '_linkedfields'=>$linkedfields
                                  )
                            );
  }

  function status($ar=NULL) {
    parent::status($ar);
    $b1=\Seolan\Core\Shell::from_screen('br');
    $nb=0;
    if(!empty($b1['lines_oid']))
      $nb=max(count($b1['lines_oid']),$b1['last']);
    if($nb==1)
      $msg=$nb.' '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','record');
    elseif($nb>1)
      $msg=$nb.' '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','records');
    $msg1=\Seolan\Core\Shell::from_screen('imod','status');
    if(empty($msg)) $msg1=array();
    if(!empty($msg)) $msg1[]=$msg;
    \Seolan\Core\Shell::toScreen2('imod','status',$msg1);
  }

  /// Liste des tables utilisé par le module
  public function usedTables() {
    return array($this->table);
  }
  /// Liste des tables dont les objects sont consultables dans le module
  public function usedMainTables() {
    return array($this->table);
  }
  /// Liste des boid utilisés par le module
  function usedBoids() {
    return array($this->xset->getBoid());
  }

  public function exportForLangs($ar) {
    $p = new \Seolan\Core\Param($ar);
    $tplentry = $p->get('tplentry');
    $langs = $p->get('langs');
    $nozip = $p->get('nozip');
    $select = $p->get('select');
    $_ajax = $p->get('_ajax');
    $_keepStatusFile = $p->get('_keepStatusFile');
    $recordCount = $p->get('_recordcount');

    if(is_array($langs) && count($langs) > 1) {
      $files = array();
      $zipDir = $this->getExportDir($ar);
      $ar2 = $ar;
      $ar2['tplentry'] = TZR_RETURN_DATA;
      $this->total = $recordCount * count($langs);
      foreach($langs as $lang) {
        $ar2['langs'] = array($lang);
        $ar2['nozip'] = true;
        $file = $this->export($ar2);
        $pathinfo = pathinfo($file);
        $newfile = $zipDir.$lang.'_'.$pathinfo['basename'];
        $files[] = $newfile;
        rename($file, $newfile);
      }

      if($nozip) {
        return $files;
      }

      $ar['fmt'] = 'zip';
      $fileName = $this->_exportFileName($ar);
      exec('(cd ' . $zipDir . ';zip -rm ../' . $fileName . ' . )2>&1 > /dev/null');

      if ($_ajax && !$_keepStatusFile) {
        $fileName = str_replace(TZR_TMP_DIR, '', $fileName);
        $this->writeStatusFile(array(
          'done' => 1,
          'url' => TZR_DOWNLOADER_TMP . '?del=1&mime=application/zip&filename=' . $fileName . '&originalname=' . $this->_exportFileName($ar),
        ));
      } else if ($_ajax){
        fclose($this->statusFile);
      }

      if($tplentry == TZR_RETURN_DATA){
        return $fileName;
      }
      else{
        ob_clean();
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header('Content-length: ' . filesize($fileName));
        readfile($fileName);
        register_shutdown_function('unlink',$fileName);
        die;
      }
    }
  }

  /// export d'un browse
  /// _keepStatusFile : enchaîner plusieurs fichiers
  public function export($ar) {
    ini_set('max_execution_time', TZR_MAX_EXECUTION_TIME);
    $p = new \Seolan\Core\Param($ar, ['_ajax'=>false, '_keepStatusFile'=>false, '_multisep'=>'|', 'pagesize' => 100, 'imagesheight' => 100], 'all', ['pagesize' => [FILTER_VALIDATE_INT,[]],
																		     'imagesheight' => [FILTER_VALIDATE_INT,[]]]);
    $target = $p->get('_target');
    $linkedfield = $p->get('_linkedfield');
    $select = $p->get('select');
    $tplentry = $p->get('tplentry');
    $recordCount = $p->get('_recordcount');
    $langs = $p->get('langs');
    $nozip = $p->get('nozip');
    $_ajax = $p->get('_ajax');
    $_keepStatusFile = $p->get('_keepStatusFile');

    // export de plusieurs langues ou langue donnée forcée
    if(is_array($langs) && count($langs) > 1) {
      return $this->exportForLangs($ar);
    } elseif(is_array($langs) && count($langs) == 1) {
      \Seolan\Core\Shell::setLang($langs[0]);
    }

    // en schedulé, on a déjà le query
    if (!$select) {
      $ar['select'] = $select = $this->getContextQuery($ar);
    }

    // status file to communicate with ajax refreshStatus
    // statusFileId allow multiple status file to be in use at a time (case of user abort and retry)
    if ($_ajax) {
      $this->writeStatusFile(array(
        'linesProcessed' => 0,
        'total' => $recordCount
      ), $ar);
    }
    // export d'un sous module
    if (!empty($target) && ($target != $this->_moid)) {
      $mod = \Seolan\Core\Module\Module::objectFactory($target);
      if ($mod->secure('', 'export')) {
        $ar_select = $ar['select'];
        //On ajoute les filtres du module parent sinon recupere tous les oids de la table
        if (!empty($this->getFilter())) {
          $ar_select .= ' AND '.$this->getFilter();
        }
        $ar['select'] = "select * from {$mod->table} where {$p->get('_linkedfield')} in (" .
          preg_replace('/^(select .*) from/Ui', 'select KOID from', $ar_select) . ')';
        $ar['_recordcount'] = getDB()->fetchOne(preg_replace('/^select \*/', 'select count(*)', $ar['select']));
        $ar['_exportDir'] = $this->getExportDir($ar);
        if ($ar['_recordcount'] > TZR_MAX_EXPORT_XLS) {
          $ar['fmt'] = 'csv';
        }
        return $mod->export($ar);
      } else {
        \Seolan\Core\Shell::redirect2auth();
      }
    }

    $exportfiles = $p->get('exportfiles');
    $exportftp = $p->get('exportftp');


    $oldinteractive = $this->interactive;
    $this->interactive = false;

    $ar['nocount'] = true;
    $ar['pagesize'] = $p->get('pagesize');
    $ar['first'] = 0;
    $ar['fmt'] = $p->get('fmt');
    $ar['_multisep'] = $p->get('_multisep');
    $ar['oidisvisible'] = $p->get('oidisvisible');
    $ar['fieldseparator'] = $p->get('csvfsep');
    $ar['textseparator'] = $p->get('csvtextsep');
    $ar['csvcharset'] = $p->get('csvcharset');
    $ar['table'] = $p->get('table');
    $selectedfields = $ar['orderedselectedfields'] = $p->get('selectedfields');
    $ar['optionsfields'] = $p->get('optionsfields');
    $ar['_options'] = $ar['_options']??[];
    $ar['options'] = $ar['options']??[];
    $ar['includeimages'] = $p->get('includeimages');
    $ar['imagesheight'] = $p->get('imagesheight');
    /*
     selectedfiels composés et des options des champs
     _mapping ne permet pas de traiter les cas champs à suivre
     _exportXLS must accept a browse result. See Module/Form for instance
    */
    if ($p->is_set('browse') && empty($selectedfields)){
        $selectedfields = $ar['orderedselectedfields'] = $p->get('browse')['selectedfields'];
    }
    $ar['selectedfields'] = [];
    foreach($selectedfields as $selfn){
      if (strpos($selfn,'#') !== false){
	list($fn,$targetfn) = explode("#", $selfn);
	// les options eventuelles passées ?
	if (!isset($ar['options'][$fn]))
	  $ar['options'][$fn] = ['target_fields'=>[]];
	if (!in_array($fn, $ar['selectedfields']))
	    $ar['selectedfields'][] = $fn;
	if ($targetfn != '.raw'){ // option oid de lien
	  $ar['options'][$fn]['target_fields'][] = $targetfn;
	  $target_ds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->xset->desc[$fn]->target);
	  if($target_ds) {
	    $published_fields = $target_ds->getPublished(false);
	    if(count($published_fields)) {
	      $ar['options'][$fn]['target_fields'] = array_merge($ar['options'][$fn]['target_fields'], $published_fields);
	    }
	  }
	}
      } else {
	if (!in_array($selfn, $ar['selectedfields']))
	  $ar['selectedfields'][] = $selfn;
      }
    }
    $ar['_options']['genpublishtag']=false;
    $ar['_options']['context']='export';
    $ar['exportfiles'] = $exportfiles;
    $ar['_browsesumfields']=false;
    $ar['_fileName'] = $this->_exportFileName($ar);

    // l'export ftp est éxécuté en batch
    if ($exportftp) {
      $scheduler = new \Seolan\Module\Scheduler\Scheduler();
      $o = array();
      $o['function'] = 'exportBatch';
      $o['uid'] = getSessionVar('UID');
      $o['ftpserver'] = $p->get('ftpserver');
      $o['ftplogin'] = $p->get('ftplogin');
      $o['ftppassword'] = $p->get('ftppassword');
      $o['options'] = serialize($ar);
      // $filename ?
      $scheduler->createJob($this->_moid, date('Y-m-d H:i:s'), 'Export with files', $o, '', $fileName, NULL);
      setSessionVar('message', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'exportfilescreated'));
      \Seolan\Core\Shell::changeTemplate('Core.message-dialog.html');
      return;
    }

    // avoid session lock
    if ($_ajax) {
      sessionClose();
      // try to detect user abort, need echoing and flushing to work - ok with apache module
      ignore_user_abort(1);
      echo('°');
      ob_flush();
      flush();
    }
    do {
      // _exportXLS must accept a browse result
      if ($p->is_set('browse')){
	$browse = $p->get('browse');
      } else {
	$browse = $this->browse($ar);
      }
      $this->post_export_browse($browse, $ar);
      if (!isset($init)) {
        $this->setExportHeaders($ar, $browse);
        $this->_initExport($ar, $browse);
        $init = true;
      }
      $this->_addToExport($ar, $browse);
      $ar['first'] += $ar['pagesize'];
      if ($_ajax) {
        $this->writeStatusFile(array(
          'linesProcessed' => count($browse['lines_oid']),
          'total' => $recordCount
        ));
      }
    } while (count($browse['lines_oid']) == $ar['pagesize']);

    $fileName = $this->_closeExport($ar);

    if(is_array($langs) && count($langs) == 1) {
      \Seolan\Core\Shell::unsetLang();
    }

    $this->interactive = $oldinteractive;
    // return to exportBatch
    if (isset($ar['_batch']) || $nozip) {
      return $this->exportToZip($fileName, $ar);
    }
    // avec les datas, on zip
    if ($exportfiles) {
      $zip = $this->exportToZip($fileName, $ar);
      if ($zip['error']) {
        if ($_ajax) {
          $this->writeStatusFile(array(
            'error' => 1,
            'message' => $zip['message']
          ));
          fclose($this->statusFile);
          die;
        } else {
          setSessionVar('message', $zip['message']);
          \Seolan\Core\Shell::changeTemplate('Core.message-popup.html');
          return;
        }
      }
      $fileName = TZR_TMP_DIR . $zip['zipFileName'];
      $exportMimeType = 'application/zip';
      $ar['_fileName'] = $fileName;
    } else {
      $exportMimeType = $this->_exportMimeType($ar);
    }

    if ($_ajax && !$_keepStatusFile) {
      $fileName = str_replace(TZR_TMP_DIR, '', $fileName);
      $this->writeStatusFile(array(
        'done' => 1,
        'url' => TZR_DOWNLOADER_TMP . '?del=1&mime=' . $exportMimeType . '&filename=' . $fileName . '&originalname=' . $ar['_fileName'],
      ));
    } else if ($_ajax){
      fclose($this->statusFile);
    }
    // rgpd
    \Seolan\Core\Logs::update('dataexport', 0, ['module'=>$this->_moid.' '.$this->modulename,
						'selectefields'=>implode(' ', $ar['selectedfields'])]);
    if($tplentry == TZR_RETURN_DATA){
      return $fileName;
    }else{
      ob_clean();
      header('Content-type: ' . $exportMimeType);
      header("Content-Disposition: attachment; filename=\"{$ar['_fileName']}\"");
      header('Content-length: ' . filesize($fileName));
      readfile($fileName);
      register_shutdown_function('unlink',$fileName);
      die;
    }

  }

  // Fonction surchargeable pour enrichir l'export après le browse
  function post_export_browse(&$browse, $ar) {
  }

  // Export en ajax, on écrit dans un fichier de status
  function writeStatusFile($json, $ar=array()) {
    if(!$this->statusFile) {
      $p = new \Seolan\Core\Param($ar);
      $target = $p->get('_target');
      $statusFileId = $p->get('statusFileId');
      $targetmoid = $target ?: $this->_moid;
      $cplt = $statusFileId ? '_'.$statusFileId : '';
      $statusFilePath = TZR_TMP_DIR . 'exportStatus_' . session_id() . '_' . $targetmoid . $cplt;
      \Seolan\Core\Logs::debug(__METHOD__.' writing statusFile : '.$statusFilePath);
      $this->statusFile = fopen($statusFilePath, 'w+');
    }

    $this->linesProcessed += $json['linesProcessed'];
    $this->total = max($this->linesProcessed, $this->total, $json['total']);
    $json['linesProcessed'] = (int)$this->linesProcessed;
    $json['total'] = (int)$this->total;

    rewind($this->statusFile);
    fwrite($this->statusFile, json_encode($json));
    echo('°');
    ob_flush();
    flush();
    \Seolan\Core\Logs::debug(__METHOD__.' connection status '.connection_status());
    if (connection_status() !== 0){
      \Seolan\Core\Logs::notice(__METHOD__.' connection aborted '.connection_status());
      die('export interrupted, connexion aborted ?');
    }
  }

  /// Permet d'exporter via le scheduler, est appelé suite à un export browse avec ftp
  function exportBatch(\Seolan\Module\Scheduler\Scheduler &$scheduler, &$o, &$more) {
    $ar = (array) $more;
    $msg = '';
    $ftpserver = $ar['ftpserver'];
    $ftplogin = $ar['ftplogin'];
    $ftppass = $ar['ftppassword'];
    $zipfile = null;
    $ftp = ftp_connect($ftpserver);
    if (!$ftp) {
      $msg = 'Unable to connect to ' . $ftpserver;
    } else {
      $ftpl = ftp_login($ftp, $ftplogin, $ftppass);
      if (!$ftpl) {
        $msg = 'Error logging into ' . $ftpserver;
      } else {
        $options = unserialize($ar['options']);
        $options['_batch'] = true;
        $zip = $this->export($options);
	$zipfile =  TZR_TMP_DIR . $zip['zipFileName'];
        if ($zip['error']) {
          $msg = $zip['message'];
        } else {
          $ftpp = ftp_put($ftp, $zip['zipFileName'], $zipfile, FTP_BINARY);
          if (!$ftpp) {
            $msg = 'Unable to send file ' . $zipfile . ' on the ftp server ' . $ftpserver;
          } elseif (!empty($GLOBALS['TZR_DLSTATS'][$this->table]) && $GLOBALS['TZR_DLSTATS'][$this->table] == '_all'){
            \Seolan\Module\DownloadStats\DownloadStats::trace($GLOBALS['XUSER']->_curoid, $this->_moid, $zip['zipname'], filesize($zip['zipFileName']));
          }
        }
      }
      ftp_close($ftp);

      \Seolan\Core\Logs::notice(__METHOD__,"removing $zipfile");
      if (!empty($zipfile) && file_exists($zipfile)){
	unlink($zipfile);
      }
    }
    if (!empty($msg))
      $msg = sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'exportfileserror'), $msg);
    else
      $msg = sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'exportfilesok'), $zip['zipFileName']);
    $this->sendMail2User(sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'exportfilessub'), $this->getLabel()), $msg);

    return $msg;

  }

  protected function _exportFileName($ar) {
    $p = new \Seolan\Core\Param($ar);
    $fileName = $p->get('fname');
    if (empty($fileName)) {
      if (!empty($this->getLabel()))
	$fileName = 'Export ' . $this->getLabel();
      else
        $fileName = 'Export ' . $this->xset->table_title;
      $fileName = rewriteToAscii($fileName);
    }
    $fileName = preg_replace('/[^a-zA-Z0-9-_]/', '_', removeaccents($fileName));
    $ml = 120;
    if (strlen($fileName) > $ml){
      $fileName = substr($fileName, 0, $ml).'_'.date('Ydmhis');
    }
    switch ($ar['fmt']) {
      case 'xl07' :
        return $fileName . '.xlsx';
      default :
        return $fileName . '.' . $ar['fmt'];
    }
  }

  protected function _exportMimeType($ar) {
    switch ($ar['fmt']) {
      case 'xl07' :
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
      case 'csv' :
        return 'text/csv';
      default :
        return 'text/html';
    }
  }

  protected function _initExport($ar, $browse) {
    if (isset($ar['exportfiles'])) {
      $this->_initExportFiles($ar, $browse);
    }
    switch ($ar['fmt']) {
      case 'xl07' :
        $this->_initExportXLS($ar, $browse);
        break;
      case 'csv' :
        $this->_initExportCSV($ar, $browse);
        break;
      case 'xl' :
	\Seolan\Core\Logs::critical(__METHOD__,"xl format is deprecated");
	break;
      case 'html' :
	\Seolan\Core\Logs::critical(__METHOD__,"xl format is deprecated");
        break;
    }
  }
  /// tableau des entêtes de champs
  /// prise en compte des champs liens
  /// -> dans les entetes et clone
  protected function setExportHeaders(&$ar, $browse){

    $getField = function($fn, $subfn) use($browse){
      $field = null;
      $subfield = null;

      $l = count($browse['header_fields']);
      for($i=0; $i < $l && $browse['header_fields'][$i]->field != $fn; $i++){}

      if ($browse['header_fields'][$i]->field == $fn){
	$field = $browse['header_fields'][$i];
	// '*' : champ lien std, '.raw' : oid (raw)
	if ($subfn != '*' && $subfn != '.raw' && $field->target != TZR_DEFAULT_TARGET){ // champ de table liée
	  $targetds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($field->target);
	  $subfield = $targetds->getField($subfn);
	  if (!$subfield){
	    throw new \Exception(__METHOD__." Error field, $fn {$field->target} $subfield not found in headers fields");
	  }
	}
      } else {
	throw new \Exception(__METHOD__." Error field, $fn not found in headers fields");
      }
      // comme ensuite on les modifie : clone
      return [clone($field), $subfield?clone($subfield):null];
    };

    $ar['all_headers_fields'] = [];
    foreach($ar['orderedselectedfields'] as $selfn){
      if (strpos($selfn,'#') !== false){
	list($fn,$sfn) = explode("#", $selfn);
      }else{
	$fn = $selfn;
	$sfn = '*';
      }
      // recherche du champ et clonage
      list($field, $subfield) = $getField($fn, $sfn);
      if ($sfn == '*'){
	$field->subfield = false;
	$field->raw = false;
	$ar['all_headers_fields'][] = $field;
      }else{
	if ($sfn == '.raw'){
	  $field->subfield = [$fn, $sfn];
	  $field->label = "{$field->label} - ID";
	  $ar['all_headers_fields'][] = $field;
	} else {
	  $subfield->subfield = [$fn, $sfn];
	  $subfield->label = "{$field->label} - {$subfield->label}";
	  $ar['all_headers_fields'][] = $subfield;
	}
      }
    }
  }

  protected function getExportDir($ar) {
    if(!$this->_exportDir) {
      if($ar['_exportDir'] && file_exists($ar['_exportDir'])) {
        $this->_exportDir = $ar['_exportDir'];
      }
      else {
        $this->_exportDir = TZR_TMP_DIR . uniqid('exportdir') . '/';
        $ret = mkdir($this->_exportDir);
        if (!$ret) {
          \Seolan\Core\Logs::critical(get_class() . '::_initExportFiles', 'Can\'t create export dir ' . $this->_exportDir);
        }
      }
    }
    return $this->_exportDir;
  }

  /**
   * initialisation des répertoires d'export et des formats de noms
   * répertoire général d'export
   * répertoires par champ fichier exporté
   */
  protected function _initExportFiles($ar, $browse) {
    $this->getExportDir($ar);
    $this->_exportFiles = [];
    $this->_naming_conventions_fields = [];
    $this->_exportDirs = [];
    foreach ($ar['all_headers_fields'] as $ahfi=>$xfield) {
      if (is_a($xfield, \Seolan\Field\File\File::class)){
	if ($xfield->subfield){
	  list($fn, $sfn) = $xfield->subfield;
	  $exportdir = "{$this->_exportDir}{$fn}_{$sfn}/";
	  $fieldkey = "$fn#$sfn";
	} else {
	  list($fn, $sfn) = [$xfield->field, ''];
	  $exportdir = "{$this->_exportDir}$fn/";
	  $fieldkey = $fn;
	}
	$this->_exportDirs[] = $exportdir;
        $this->_exportFiles[] = $ahfi;
        $ret = mkdir($exportdir);
        if (!$ret || !file_exists($exportdir)){
          \Seolan\Core\Logs::critical(get_class() . '::_initExportFiles', 'Can\'t create export dir ' . $exportdir);
        }
	// format des noms de champs : extraction des valeurs
	if (isset($ar['optionsfields'][$fieldkey]['naming_convention'])
	    && !empty($ar['optionsfields'][$fieldkey]['naming_convention'])){
	  $r = preg_match_all('/%_([a-z0-9_\.]+)/i',$ar['optionsfields'][$fieldkey]['naming_convention'], $fmtfields);

	  $this->_naming_conventions_fields[$fieldkey] = $fmtfields;
	}
      }
    }
  }
  protected function _initExportXLS($ar, $browse) {

    $this->_currentExport = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $this->_currentExport->setActiveSheetIndex(0);
    $activeSheet = $this->_currentExport->getActiveSheet();
    $activeSheet->setTitle(mb_substr(preg_replace("@[\[\]\*\?:\\/']@", '', $this->getLabel()),0,31));

    if (isset($ar['oidisvisible'])) {
      $activeSheet->SetCellValue('A1', 'OID');
      $pad = 1;
    } else {
      $pad = 0;
    }
    foreach($ar['all_headers_fields'] as $i=>$xfield){
      $colnum = 1+$i+$pad;
      $label = $xfield->get_label();
      if (isset($ar['showfieldsgroup']) && $ar['showfieldsgroup']){
	$label = $xfield->fgroup.' : '.$label;
      }
      convert_charset($label, TZR_INTERNAL_CHARSET, 'UTF-8');
      $activeSheet->setCellValueByColumnAndRow($colnum, 1, $label);
    }
    $activeSheet->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colnum) . '1')->getFont()->setBold(true);
  }
  // php excel methods

  protected function _initExportCSV($ar, $browse) {
    if ($ar['oidisvisible'])
      $headers[] = 'OID';
    foreach($ar['all_headers_fields'] as $i=>$xfield){
      $label = $xfield->get_label();
      if (isset($ar['showfieldsgroup']) && $ar['showfieldsgroup']){
	$label = $xfield->fgroup.' : '.$label;
      }
      $headers[] = $ar['textseparator'].$label.$ar['textseparator'];
    }
    $this->_currentExport = [implode($ar['fieldseparator'], $headers)];
  }

  /// ajout d'un lot des lignes à l'export en cours
  protected function _addToExport($ar, $browse) {
    if (isset($ar['exportfiles'])) {
      // on récupère les noms uniques de fichiers dans les rep d'export
      $ar['exportfilesnames'] = $this->_addFilesToExport($ar, $browse);
    }
    switch ($ar['fmt']) {
      case 'xl07' :
        $this->_addToExportXLS($ar, $browse);
        break;
      case 'csv' :
        $this->_addToExportCSV($ar, $browse);
        break;
    }
  }
  /// prepare files, copy to tmp, zip dir
  protected function _addFilesToExport($ar, $browse) {
    $mimes = \Seolan\Library\MimeTypes::getInstance();

    // copy file with unique name to export dir, manage image file options
    $exportFile = function($ofile, $exportdir, $exportfilename, $fieldkey) use($ar){
      $tofilename = uniqueFileNameInDirectory($exportdir, $exportfilename);
      // calcul du from si contexte multitable car il est faux, à voir si sous champs ...
      $fromfilename = $ofile->filename;
      if (isset($ofile->fielddef->_mttCloneFieldName)){
	$fromfilename = str_replace('/'.$ofile->fielddef->field.'/', '/'.$ofile->fielddef->_mttCloneFieldName.'/', $fromfilename);
      }
      if ($ofile->fielddef->gzipped == 1) {
	$fh = gzopen($fromfilename, 'r');
	$content = gzread($fh, 100000000);
	gzclose($fh);
	$ret = file_put_contents($tofilename, $content);
	unset($content);
      } else {
	// cas des images : dimensions (crop extent), format
	if((is_a($ofile->fielddef, \Seolan\Field\Image\Image::class)
	    || \Seolan\Field\File\File::isImage($ofile->mime)) &&
	   ((isset($ar['optionsfields'][$fieldkey]['format'])
	   && $ar['optionsfields'][$fieldkey]['format'] != 'origin')
	 || (isset($ar['optionsfields'][$fieldkey]['size'])
	     && $ar['optionsfields'][$fieldkey]['size'] != 'origin'))){
	  // mise en forme de l'url resizer
	  $resizer = $ofile->resizer;
	  $rparts = parse_url($resizer);
	  parse_str($rparts['query'], $rquery);
	  $rquery['originalname'] = $exportfilename;
	  if (isset($ar['optionsfields'][$fieldkey]['size'])
	      && $ar['optionsfields'][$fieldkey]['size'] != 'origin'){
	    $rquery['geometry']=$ar['optionsfields'][$fieldkey]['size'];
	    if (isset($ar['optionsfields'][$fieldkey]['crop']) && !empty($ar['optionsfields'][$fieldkey]['crop'])){
	      $rquery['crop']=$ar['optionsfields'][$fieldkey]['size'];
	      $rquery['gravity'] = 'Center';
	      unset($rquery['geometry']);
	    }
	    if (isset($ar['optionsfields'][$fieldkey]['extent']) && !empty($ar['optionsfields'][$fieldkey]['extent'])){
	      $rquery['extent']=$ar['optionsfields'][$fieldkey]['size'];
	      $rquery['gravity'] = 'Center';
	      unset($rquery['geometry']);
	    }
	  }
	  if (isset($ar['optionsfields'][$fieldkey]['format'])
	      && $ar['optionsfields'][$fieldkey]['format'] != 'origin'){
	    $rquery['mime']='image/'.$ar['optionsfields'][$fieldkey]['format'];
	  }
	  $resizer = $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().$rparts['path'].'?'.http_build_query($rquery);
	  if (\Seolan\Core\Shell::admini_mode())
	    $sessionCookie = TZR_BO_SESSION_NAME;
	  else
	    $sessionCookie = TZR_FO_SESSION_NAME;
	  // contexte avec cookie de session pour cas fichiers "secures"
	  if (isset($_COOKIE[$sessionCookie]))
	    $resizerContext = stream_context_create(['http'=>['method'=>'GET',
							      'header'=>['Cookie: '.$sessionCookie.'='.$_COOKIE[$sessionCookie]]]]);
	  else
	    $resizerContext = null;
	  \Seolan\Core\Logs::debug(__METHOD__." resizer ".$resizer);
	  $contents = file_get_contents($resizer, false, $resizerContext);
	  $ret = false;
	  if (!empty($contents)){
	    $ret = file_put_contents($tofilename, $contents);
	    unset($contents);
	  } else {
	    \Seolan\Core\Logs::critical(__METHOD__," resize $resizer to $tofilename error empty contents $resizerContext");
	  }
	} else { // sans resizer
	  \Seolan\Core\Logs::debug(__METHOD__."copy $fromfilename to $tofilename");
	  $ret = copy($fromfilename, $tofilename);
	}
      }
      if (!$ret) {
	\Seolan\Core\Logs::critical(__METHOD__, 'Can\'t copy '.$fromfilename.' '.$tofilename);
	return false;
      }
      return $tofilename;
    };

    // get the file name to be exported from naming conventions and required format
    // required format depend both on file type and field type
    $getExportFileName = function($fieldvalue,$i,$field_name) use($ar,$browse,$mimes){
      if(is_a($fieldvalue->fielddef, \Seolan\Field\Image\Image::class)
	 || \Seolan\Field\File\File::isImage($fieldvalue->mime)){
	$isAnImage = true;
      } else {
	$isAnImage = false;
      }
      $infos = pathinfo($fieldvalue->originalname);
      if ($isAnImage
	  && isset($ar['optionsfields'][$field_name]['format'])
	  && $ar['optionsfields'][$field_name]['format'] != 'origin'){
	$infos['extension'] = $mimes->get_extension('image/'.$ar['optionsfields'][$field_name]['format']);
      } else {
	$infos['extension'] = $mimes->get_extension($fieldvalue->mime);
      }
      if (isset($this->_naming_conventions_fields[$field_name])){
	$fmtfields = $this->_naming_conventions_fields[$field_name];
	$texts = [];
	foreach($fmtfields[1] as $fmtfield){
	  if (strpos($fmtfield,'.') > 0){ // sous champs -> target fields
	    list($ffn, $fsfn) = explode('.', $fmtfield);
	    if (!isset($browse['lines_o'.$ffn][$i])){
	      $texts[] = '%_'.$fmtfield;
	    } else {
	      if (isset($browse['lines_o'.$ffn][$i]->link['o'.$fsfn])){
		$texts[] = $browse['lines_o'.$ffn][$i]->link['o'.$fsfn]->text;
	      } else {
		$texts[] = array_reduce($browse['lines_o'.$ffn][$i]->collection, function($carry, $collection) use($fsfn){
		    return $carry.' '.$collection->link['o'.$fsfn]->text;
		  },'');
	      }
	    }
	  } else { // champ direct du browse
	    if (isset($browse['lines_o'.$fmtfield][$i])){
	      $texts[] = $browse['lines_o'.$fmtfield][$i]->text;
	    } else {
	      $texts[] = '%_'.$fmtfield;
	    }
	  }
	}
	if (isset($fmtfields[0]) && count($fmtfields[0]) > 0){
	  $infos['filename'] = cleanfilename(str_replace($fmtfields[0], $texts, $ar['optionsfields'][$field_name]['naming_convention']));
	} else
	  $infos['filename'] = cleanfilename($ar['optionsfields'][$field_name]['naming_convention']);
      }
      return $infos['filename'].'.'.$infos['extension'];
    };
    // boucle sur les lignes et les champs (sous champs) fichiers à exporter
    // en appliquant les fonctions définies dessus
    $filesnames = [];
    foreach ($browse['lines_oid'] as $i => $oid) {
      foreach ($this->_exportFiles as $ifile=>$ahfi) {
	$ofiles = []; // fichier ou fichiers (sous champs multiples) à exporter
	$mv = false;
	$xfield = $ar['all_headers_fields'][$ahfi];
	if ($xfield->subfield){
	  list($fn, $sfn) = $xfield->subfield;
	  if(!$browse['lines_o'.$fn][$i]->fielddef->multivalued){ // mono valués
	    $ofiles[] = $browse['lines_o'.$fn][$i]->link['o'.$sfn];
	  } else {
	    list(,$mv) = explode(':',$oid);
	    $ofiles = array_reduce($browse['lines_o'.$fn][$i]->collection, function($carry, $collection)use($sfn){
		$carry[] = $collection->link['o'.$sfn];
		return $carry;
	      }, []);
	  }
	  $fieldkey = "$fn#$sfn";
	}else{
	  $ofiles[] = $browse['lines_o'.$xfield->field][$i];
	  $fieldkey = $xfield->field;
	}

	// export du fichier (ou des fichiers si sous champ multivalué)
	foreach($ofiles as $ofile){
	  if (!$ofile->fielddef->multivalued) { // mono valués
	    if (empty($ofile->filename)) {
	      continue;
	    }
	    if (!isset($filesnames[$fieldkey]))
	      $filesnames[$fieldkey] = [];
	    // format du nom / règle de nomage
	    $filename = $getExportFileName($ofile,$i,$fieldkey);
	    // copie du fichier pour le zip
	    $filesnames[$fieldkey][$i] = $exportFile($ofile,$this->_exportDirs[$ifile],$filename,$fieldkey);
	  } else {// multi valués
	    // à voir ou s'appliquent les règles de nomage
	    if (empty($ofile->catalog[0])) {
	      continue;
	    }
	    if (!isset($filesnames[$fieldkey]))
	      $filesnames[$fieldkey] = [];
	    $_ofile = $ofile->catalog[0];
	    // à voir $dir devrait prendre naming_convention ?
	    // si sous champ mv -> répertoire par ligne et par objet lié
	    if (!$mv){
	      $mvdir = $this->_exportDirs[$ifile].basename(dirname($_ofile->filename)).'/';
	      $ret = mkdir($mvdir);
	    } else{
	      $mvdir = $this->_exportDirs[$ifile].$mv.'/'.basename(dirname($_ofile->filename)).'/';
	      $ret = mkdir($mvdir, 0777, true);
	    }
	    if (!file_exists($mvdir)) { // mkdir retourne 0 si rep existe deja
	      \Seolan\Core\Logs::critical(__METHOD__, "Can't create dir $fieldkey $ifile $mvdir {$_ofile->filename}");
	      continue;
	    } else {
	      \Seolan\Core\Logs::notice(__METHOD__,  "File already exists $fieldkey $ifile $mvdir {$_ofile->filename}");
	    }
	    $filesnames[$fieldkey][$i] = ['dir'=>$mvdir,'files'=>[]];
	    foreach ($ofile->catalog as $k => $ofile_inside) {
	      $filename = $getExportFileName($ofile_inside,$i,$fieldkey);
	      $exportedfile = $exportFile($ofile_inside, $mvdir, $filename,$fieldkey);
	      $filesnames[$fieldkey][$i]['files'][] = $exportedfile;
	    }
	  }

	}
      }
    }
    return $filesnames;
  }
  /**
   * export des lignes pour les 2 formats (csv, xls)
   * -> regroupe les traitements de mise en forme et de consolidation
   * -> écriture via les methodes writeXXX, csvValue des champs
   */
  protected function _addLinesToExport($ar, $browse) {
    // fonctions 'banalisées' d'écriture et 'contexte' qui leur est passé
    $context = (Object)['fmt'=>$ar['fmt']];
    if ($context->fmt == 'xl07'){
      $context->workSheet = $this->_currentExport->getActiveSheet();
      $setCellValue = function($rownum,$colnum,$value,$format=null,$options=null,$xfield=null)use($context){
	if ($xfield == null){
	  $context->workSheet->setCellValueByColumnAndRow($colnum,$rownum,$value);
	} else {
	  $xfield->writeXLSPHPOffice($context->workSheet,$rownum,$colnum, $value,$format,$options);
	}
      };
      $closeLine = function(){
	// pass
      };
    } elseif($context->fmt == 'csv'){
      // contexte et fonctions pour du csv
      $context->row = [];
      $context->textsep = $ar['textseparator'];
      $context->fieldsep = $ar['fieldseparator'];
      $setCellValue = function($rownum,$colnum,$value,$format=null,$options=null,$xfield=null)use($context){
	// idealement faudra voir de gérer rown et num
	if ($xfield == null){
	  $context->row[] = $value;
	} else {
	  $context->row[] = $xfield->getCSVValue($value, $context->textsep, $format, $options);
	}
      };
      $closeLine = function()use($context){
	$this->_currentExport[] = implode($context->fieldsep, $context->row);
	$context->row = [];
      };
    };
    // fonction pour extraire la bonne valeur de cellule pour un champ fichier
    $getFileFieldCellValue = function($xfield, $i, $ar, $browse){
      if ($xfield->subfield)
	$fieldkey = $xfield->subfield[0].'#'.$xfield->subfield[1];
      else
	$fieldkey = $xfield->field;
      if (isset($ar['exportfilesnames'][$fieldkey][$i])){
	if ($xfield->multivalued){
	  $filesnames = array_reduce($ar['exportfilesnames'][$fieldkey][$i]['files'], function($carry,$item){
	    return $carry.(empty($carry)?'':',').basename($item);
	  }, '');
	  $cellvalue = str_replace('#', '_', $fieldkey).'/'.basename($ar['exportfilesnames'][$fieldkey][$i]['dir']).' : '.$filesnames;
	}else{
	  $cellvalue = str_replace('#', '_', $fieldkey).'/'.basename($ar['exportfilesnames'][$fieldkey][$i]);
	}
      } else {
	    if ($xfield->subfield) {
          list($fn, $subfn) = $xfield->subfield;
          $xfieldval = $browse['lines_o'.$fn][$i];
          $xfieldval->html; // déclenche le traitement différé
          $cellvalue = $xfieldval->link['o'.$subfn];
        } else {
          $cellvalue = $browse['lines_o'.$fieldkey][$i];
        }
      }
      return $cellvalue;
    };
    // traitement des lignes
    $pad = $ar['oidisvisible'] ? 1 : 0;
    foreach ($browse['lines_oid'] as $i => $oid) {
      $rownum = $ar['first'] + $i + 2;
      if ($pad)
	$setCellValue($rownum, 1, $oid);
      foreach ($ar['all_headers_fields'] as $j => $xfield) {
	$colnum = 1 + $j + $pad;
	if ($xfield->subfield
	    && (!is_a($xfield, \Seolan\Field\File\File::class))){
	  // mise en forme par 'sous champs' (champs liens et dérivés sauf cas Field/File
	  list($fn, $subfn) = $xfield->subfield;
	  if ($subfn == '.raw'){  // option oid de lien par exemple
	    $setCellValue($rownum, $colnum, $browse['lines_o'.$xfield->field][$i]->raw, null, null, null);
	  } else {
	    $xfieldval = $browse['lines_o'.$fn][$i];
	    // recherche du champs dans les collections ou le lien directement
	    $xfieldval->text; // déclenche le traitement différé
	    if (!isset($xfieldval->collection)){
	      // champ mono valué
	      $setCellValue($rownum, $colnum, $xfieldval->link['o'.$subfn], null, null, $xfield);
	    } else {
	      // champ multivalué
	      // concaténation des valeurs, dans une colonne texte, sans passer par le champ
	      // on ne peut pas utiliser l'export du champ qui pose des formats de colonne
	      $vals = [];
	      foreach($xfieldval->collection as $collection){
		$vals[] = addcslashes($collection->link["o$subfn"]->text,"\"");
	      }
	      $setCellValue($rownum, $colnum, '"'.implode('"'.$ar['_multisep'].'"', $vals).'"', null, null, null);
	    }
	  }
	} elseif (is_a($xfield, \Seolan\Field\File\File::class)){
	  $fieldkey = $xfield->field;
	  if ($xfield->subfield) {
	    list($fn, $subfn) = $xfield->subfield;
	    $fieldkey = $fn.'#'.$subfn;
	  }

	  if (isset($ar['includeimages']) || isset($_REQUEST['includeimages'])) {
        $options['includeimages'] = $ar['includeimages'] ?? $_REQUEST['includeimages'];
        $options['imagesheight'] = $ar['imagesheight'] ?? $_REQUEST['imagesheight'] ?? 100;
      } else {
        if (isset($ar['optionsfields'][$fieldkey]['exportfileurl']) && $ar['optionsfields'][$fieldkey]['exportfileurl'] == 1) {
	      $options = ['url' => 1];
	    } else {
	      $options = ['name' => 1];
	    }
	  }
	  $setCellValue($rownum, $colnum, $getFileFieldCellValue($xfield, $i, $ar, $browse), null, $options, $xfield);
	} else { // mise en forme de base
	  $setCellValue($rownum, $colnum, $browse['lines_o'.$xfield->field][$i], null, null, $xfield);
	}
      }
      $closeLine();
    }
  }
  // "deprecated", voir addLinesToExport
  protected function _addToExportXLS($ar, $browse) {
    $this->_addLinesToExport($ar, $browse);
  }
  // "deprecated", voir addLinesToExport
  protected function _addToExportCSV($ar, $browse) {
    $this->_addLinesToExport($ar, $browse);
  }

  protected function _closeExport($ar) {
    switch ($ar['fmt']) {
    case 'xl07' :
      return $this->_closeExportXLS($ar);
    case 'csv' :
      return $this->_closeExportCSV($ar);
    }
  }
  protected function _closeExportXLS($ar) {
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->_currentExport);
    $fileName = TZR_TMP_DIR . uniqid() . '-' . $ar['_fileName'];
    $writer->save($fileName);

    $this->_currentExport->disconnectWorksheets();
    unset($this->_currentExport);

    return $fileName;
  }

  protected function _closeExportCSV($ar) {
    $csv = implode("\r\n", $this->_currentExport);
    convert_charset($csv, TZR_INTERNAL_CHARSET, $ar['csvcharset']);

    $fileName = TZR_TMP_DIR . uniqid() . '-' . $ar['_fileName'];
    file_put_contents($fileName, $csv);
    return $fileName;
  }

  /// Cree une archive zip dans TZR_TMP_DIR contenant le fichier d'export
  /// et les fichiers des champs si demandés (this->_exportDir)
  /// @return ['nom du zip', 'erreur', 'message']
  function exportToZip($file, $ar) {

    $fileName = basename($file);
    $fileName = substr($fileName, strpos($fileName, '-') + 1);
    $fileName = str_replace('.', '_' . date('Y-m-d_H-i-s') . '.', $fileName);

    $zipdir = $this->getExportDir($ar);

    if (empty($fileName)
	|| empty($file) || !file_exists($file)
	|| empty($zipdir) || !file_exists($zipdir)){
      \Seolan\Core\Logs::critical(__METHOD__,"file '$file' '$fileName' and/or export dir '$zipdir' empty or not exist");
      return ['zipFileName'=>null,'error'=>true,'message'=>'erreur interne / paramétrage'];
    }

    $zipFileName = $fileName . '.zip';

    \Seolan\Core\Logs::notice(__METHOD__,"rename/move : $file -> $zipdir/$fileName");
    \Seolan\Core\Logs::notice(__METHOD__,"prepare to zip and delete  : (cd $zipdir;zip -rm ../$zipFileName .) 2>&1 > /dev/null");

    /*
     cd zipdir, zip -rm ../zipfilename .
     fait le zip dans zipdir/..
     qui est supposée (voir export, exportbatch) être TZR_TMP_DIR, -m (move) -> efface les contenus que l'on vient de zipper, y compris le répertoire si vide à l'issue (ici, à priori, non : on est dedans)
    */
    rename($file, $zipdir.'/'.$fileName);

    // Si on veut pas zipper tout de suite on renvoie le fichier d'export directement
    if($ar['nozip']) {
      return $zipdir.'/'.$fileName;
    }

    exec('(cd ' . $zipdir . ';zip -rm ../' . $zipFileName . ' . )2>&1 > /dev/null');

    if (!empty($zipdir)){
      \Seolan\Core\Logs::notice(__METHOD__,"remove $zipdir");
      rmdir($zipdir);
    }

    return array('zipFileName' => $zipFileName, 'error' => false);
  }

  /// Fonction d'import automatisé des données
  function import($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $spec=$p->get('spec');
    $infos=pathinfo($_FILES['file']['name']);
    $size = filesize($_FILES['file']['tmp_name']);

    if($infos['extension']=='xls')
      $format='xl';
    elseif($infos['extension']=='xlsx')
      $format='xl07';
    else
      $format='csv';
    if($spec=='default'){
      $fieldsname=$p->get('fieldsname');
      $linestoskip=$p->get('linestoskip');
      $rawspecs = $this->getDefaultImportSpec($fieldsname, $format, $linestoskip);
      $file=$_FILES['file']['tmp_name'];
    }
    $sizecoeff = ['csv'=>1, 'xl'=>0.75, 'xl07'=>4];
    $sizelimit = $this->getConfigurationOption('tzr_max_import_size', TZR_MAX_IMPORT_SIZE);
    if ((int)$size*$sizecoeff[$format] >= (int)$sizelimit*1024*1024/*octets*/){
      // ajout tâche arrière plan
      $scheduler = \Seolan\Core\Module\Module::singletonFactory(XMODSCHEDULER_TOID);
      // on récupère le fichier en var/tmp  par mv ?  ou on passe par la table ?
      $scheduler->createJob($this->_moid,
			    date('Y-m-d H:i:s'), // asap
			    "File import : {$_FILES['file']['name']}".($spec!='default'?" ($spec)":''),
			    ['specs'=>['id'=>$spec,
				       'contents'=>$rawspecs],
			     'filename'=>$_FILES['file']['name'],
			     'function'=>'importBatch',
			     'recipient'=>"{$GLOBALS['XUSER']->_cur['fullnam']}<{$GLOBALS['XUSER']->_cur['email']}>",
			     'gopage'=>$GLOBALS['TZR_SESSION_MANAGER']::admin_gopage_url($this->getMainAction(), true),
			    ],
			    null,
			    $_FILES['file']['tmp_name']

      );
      Shell::setNext($this->getMainAction().'&'.http_build_query(
	['message'=>sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'batch_import_message'), $GLOBALS['XUSER']->_cur['email'])]));
    } else {
      if($spec=='default'){
	return $this->_import(['spec'=>json_decode($rawspecs, false),'file'=>$file]);
      }else{
	return parent::import($ar);
      }
    }
  }
  /// import depuis une tâche planifiée
  public function importBatch(\Seolan\Module\Scheduler\Scheduler $sched, $o, $omore){
    if ($omore->specs['id'] == 'default'){
      $specs = json_decode($omore->specs['contents'], false);
    } else {
      $ors=getDB()->fetchRow('select * from IMPORTS where ID=?',[$omore->specs['id']]);
      if($ors) {
	$specs = json_decode($ors['spec'], false); // voir Module::import
	if (empty($specs) || !is_object($specs))
	  throw new \Exception("Task {$o['KOID']} : unable to load import spec : '{$omore->specs['id']}'");
      }
    }
    // à voir mettre un code retour ?
    $this->_import(['spec'=>$specs,'file'=>$o->file]);
    $cr = Shell::from_screen('','message');
    // ? voir suppression du fichier ?
    // message à $omore->recipient avec le CR habituellement affiché à l'écran
    $mailer = new \Seolan\Library\Mail();
    $mailer->sendPrettyMail(sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'batch_import_mail_subject'), $this->getLabel(), $omore->filename),
			    sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'batch_import_mail_body'),
				    $omore->gopage,
				    $this->getLabel(),
				    $cr),
			    $omore->recipient);

    $sched->setStatusJob($o->KOID, 'finished', $cr);

  }
  /// Specification pour l'import par defaut (format procédure d'import)
  protected function getDefaultImportSpec($fieldsname, $format, $linestoskip){
    $specs = [
      'general'=>['format'=>$format,
		  'fieldsinheader'=>true,
		  'linestoskip'=>$linestoskip,
		  'location'=>null,
		  'strategy'=>['clearbefore'=>false,
			       'updateifexists'=>true],
		  'keys'=>null,
      ],
      'catalog'=>['fields'=>[
	['tzr'=>'KOID', 'name'=>'KOID'],
	['tzr'=>'KOID', 'name'=>'OID'],
	['tzr'=>'LANG', 'name'=>'LANG'],
      ]
      ]
    ];
    if ($format == 'csv'){
      $specs['general']['separator']=';';
      $specs['general']['quote']='"';
      $specs['general']['endofline']="\n";
    } else {
      $specs['general']['separator']=null;
      $specs['general']['quote']=null;
      $specs['general']['endofline']=null;
    }
    foreach($this->xset->desc as $fn=>$fd){
      if($fieldsname=='label')
	$specs['catalog']['fields'][] = ['tzr'=>$fn, 'name'=>$fd->label];
      else
	$specs['catalog']['fields'][] = ['tzr'=>$fn, 'name'=>$fn];
    }
    return json_encode($specs);
  }
  /// Sous fonction pour l'import automatisé
  function _import($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $spec=$p->get('spec');
    $imported=false;
    $dirpath=(string)$p->get('dir');
    if(empty($dirpath)) $dirpath=(string)$spec->general->location;
    $file=$p->get('file');
    $lockfile=NULL;
    if(empty($file)) {
      $file=(string)$spec->general->file;
      $lockfile=$file.'.ok';
    }
    // Manage actionbefore here
    if(property_exists($spec,'action') && property_exists($spec->action,'before')){
      $moidBefore = property_exists($spec->action->before,'moid')?$spec->action->before->moid:false;
      $functionBefore = property_exists($spec->action->before,'function')?$spec->action->before->function:false;
      if($moidBefore && $functionBefore){
	\Seolan\Core\Logs::notice(__METHOD__,'Try to execute moid: '.$moidBefore.' function:'.$functionBefore);
	$mod = \Seolan\Core\Module\Module::objectFactory($moidBefore);
	$mod->$functionBefore($ar);
      }
    }

    // Import d'un fichier
    if(!empty($file) && (empty($lockfile) || file_exists($lockfile))) {
      $ar['spec']=$spec;
      $ar['file']=$file;
      $imported=$this->_import_data($ar);
      if(!empty($lockfile)) @unlink($lockfile);
    }elseif(in_array($spec->general->format,array('csv','xl','xl07'))){
      // Import de csv depuis un repertoire
      \Seolan\Core\Logs::notice('\Seolan\Module\Table\Table::_import','module '.$this->_moid." $dirpath");
      $dir=opendir($dirpath);
      while($dir!==false && ($file=readdir($dir))!==false) {
	if(preg_match('/(.*).(txt|csv|xml)/i',$file)) {
	  $ar['file']=$dirpath.$file;
	  $ar['spec']=$spec;
	  $imported = $this->_import_data($ar);
	}
      }
    }elseif($spec->general->format=='files'){
      // Import de fichier
      $ar['spec']=$spec;
      $imported=$this->_import_files($ar);
    }

    // Manage actionafter here
    if($imported){
      if(property_exists($spec,'action') && property_exists($spec->action,'after')){
	$moidAfter = property_exists($spec->action->after,'moid')?$spec->action->after->moid:false;
	$functionAfter = property_exists($spec->action->after,'function')?$spec->action->after->function:false;
	if($moidAfter && $functionAfter){
	  \Seolan\Core\Logs::notice(__METHOD__,'Try to execute After function moid: '.$moidAfter.' function:'.$functionAfter);
	  $mod = \Seolan\Core\Module\Module::objectFactory($moidAfter);
	  $mod->$functionAfter($ar);
	}
      }
    }
  }

  /// Import des fichiers d'un dossier et ses sous dossiers (retourne true si au moins un fichier est importé)
  function _import_files($ar=NULL) {
    ini_set('max_execution_time', 600);
    $p=new \Seolan\Core\Param($ar, array());
    $specs=$p->get('spec');
    $location=(string)$specs->general->location;
    @$filefield=(string)$specs->general->filefield;
    if(empty($filefield)){
      $flist=$this->xset->getFieldsList(array('\Seolan\Field\Image\Image'));
      if(!empty($flist)) $filefield=$flist[0];
      else return false;
    }
    if(!empty($specs->general->authext)){
      $authext=explode(',',$specs->general->authext);
      foreach($authext as $i=>$ext) $authext[$i]=strtolower($ext);
    }
    $titlefield=$specs->general->titlefield??null;
    $importedfield=$specs->general->importedfield??null;
    $unique=$specs->general->strategy->unique??false;
    $updateifexist=$specs->general->strategy->updateifexists??false;
    $notdel=$specs->general->strategy->notdeletefiles??false;
    $return=false;
    $xmime=\Seolan\Library\MimeTypes::getInstance();
    $files=\Seolan\Library\Dir::scan($location);
    foreach($files as $i=>$file) {
      $filename=$file;
      $info=pathinfo($filename);
      if(!empty($authext)){
 	if(!in_array(strtolower($info['extension']),$authext)) continue;
      }
      if(filetype($filename)=='file') {
 	$return=true;
 	$ar1=array();
 	$value=array('tmp_name'=>$filename,'type'=>$xmime->getValidMime('',$filename,$filename),'name'=>$info['basename'],'title'=>'',
 		     'size'=>filesize($filename));
 	$ar1[$filefield]=$value;
 	if(!empty($titlefield)){
 	  $ar1[$titlefield]=$info['filename'];
 	  if($unique) $ar1['_unique']=array($titlefield);
 	  if($updateifexist) $ar1['_updateifexists']=1;
 	}
 	if($importedfield){
	  $f1=$this->xset->getField($importedfield);
 	  if($f1->get_ftype()=='\Seolan\Field\Boolean\Boolean') $ar1[$importedfield]=1;
 	  else $ar1[$importedfield]=date('Y-m-d H:i:s');
 	}
 	if($notdel) $ar1['options'][$filefield]['del']=false;
 	$ar1['_allfields']=true;
  	$ret=$this->xset->procInput($ar1);
      }
    }
    return $return;
  }

  /// Importe un fichier (csv/xls/xlsx) dans la base. Renvoie true si au moins une ligne est importée
  function _import_data($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $found=false;
    $file=$p->get('file');
    \Seolan\Core\Logs::notice('\Seolan\Module\Table\Table::_import_data','module '.$this->_moid.' file='.$file);
    $specs=$p->get('spec');
    if(file_exists($file)) {
      $format=(string)$specs->general->format;
      if($format=='csv'){
	$data=file_get_contents($file);
	$charset=(string)$specs->general->charset??null;
	if($charset) convert_charset($data,$charset,TZR_INTERNAL_CHARSET);
	$data=&_getCSVData($data,$specs);
      }elseif($format=='xl' || $format=='xl07'){
	$data=_getXLSData($file);
      }
      if(!empty($data))
	$found = $this->_doImportData($data, $specs);
      @unlink($file);
    } else {
      die("file $file does not exists");
    }
    return $found;
  }

  ///effective import data from array ($data) returned by getXXXdata
  protected function _doImportData(&$data, &$specs) {
    $unique=[];
    $found=false;
    $koid=$specs->general->koid??null;
    $updateifexists=$specs->general->strategy->updateifexists??false;
    if (isset($specs->general->strategy->nolog) && $specs->general->strategy->nolog == 'true')
      $nolog = true;
    else
      $nolog = false;
    $unique=array_filter($specs->general->keys)??[];
    if(!empty($unique))
      $unique[]='LANG';
    // Vide la table si demandé
    $clearbefore=$specs->general->strategy->clearbefore??false;
    if(!empty($clearbefore) && ($clearbefore!=='false')) {
      \Seolan\Core\Logs::notice('\Seolan\Module\Table\Table::_import_data','clearing before importing');
      $deleterequest=$specs->general->strategy->clearrequest??null;
      if(!empty($deleterequest))
	$this->xset->clear($deleterequest);
      else $this->xset->clear();
    }

    // Creation des entetes
    $head=array();
    $fieldsinheader=$specs->general->fieldsinheader??false;
    $fieldsname=$specs->general->fieldsname;
    $nofields = empty($specs->catalog->fields);
    if($fieldsinheader){
      $line=$data[0];
      foreach($line as $i=>$field){
        if($field) {
          $field = trim(preg_replace('/\s+/', ' ', $field));
          $name = mb_strtoupper($field,TZR_INTERNAL_CHARSET);
          $head[$name] = $i;
          if ($nofields) {
            $specs->catalog->fields[] = (object) ['name' => $name,
              'tzr' => array_key_first(array_filter($this->xset->desc, function($e) use ($field, $fieldsname) {
                if($fieldsname == "sql"){
                  return $e->field == $field;
                } else {
                  return $e->label == $field;
                }
              }
            ))];
          }
        }
      }
      unset($data[0]);
    }else{
      $j=0;
      foreach($specs->catalog->fields as $i=>$field){
        $head[$field->tzr]=$j;
        $j++;
      }
    }
    // Supprime les lignes à ne pas importer
    $linestoskip=(string)$specs->general->linestoskip??0;
    if(!empty($linestoskip)) {
      for($i=0;$i<$linestoskip;$i++) unset($data[$i]);
    }

    $message='<dl>';
    $tot=$ok=$nok=$update=0;
    $incompletelines=$emptylines=array();
    foreach($data as $line=>&$tuple) {
      $tot++;
      $message.='<dt><strong>- Line '.($line+1).'</strong></dt>';
      if(count($tuple)<count($head)){
        $message.='<dd>Incomplete line</dd>';
        $incompletelines[]=$line+1;
        continue;
      }
      $isempty=true;
      foreach($tuple as $i=>$value){
        if(!empty($value)){
          $isempty=false;
          break;
        }
      }
      if($isempty){
        $message.='<dd>Empty line</dd>';
        $emptylines[]=$linet+1;
        continue;
      }
      $merges = [];
      $input=array();
      $refsql= array();
      $input['_unique']=$unique;
      $input['_options'] = ['local'=>1];
      $input['options'] = [];
      // Specifie la langue par defaut de l'entrée
      if($specs->general->lang) $input['LANG']=$specs->general->lang;
      else $input['LANG']=TZR_DEFAULT_LANG;
      foreach($specs->catalog->fields as $i=>$field) {
        $tzrfield=$field->tzr;
        $namefield=$field->name;
        $skip=$field->skip??null;
        if(!empty($skip) && $skip!='false') continue;
        if($fieldsinheader && $namefield) $value=$tuple[$head[mb_strtoupper($namefield, TZR_INTERNAL_CHARSET)]];
        else $value=$tuple[$head[$tzrfield]];
        // On passe les champs du catalogue qui ne sont pas dans le fichier
        if($value===NULL) continue;
        $defaultvalue=$field->default??null;
        if(!empty($tzrfield)) {
          if($tzrfield=='KOID'){
            if(empty($value)) break;
            if(strpos($value,$this->table.':')===0) $input[$tzrfield]=$value;
            else $input[$tzrfield]=$this->table.':'.$value;
          }elseif($tzrfield=='LANG') $input[$tzrfield]=$value;
          else{
            $v=$this->xset->getField($tzrfield);
            if(!is_object($v)){
              $message='Error : field "'.$tzrfield.'" doesn\'t exists<br>';
              break 2;
            }
            $ret=$v->import($value,$field);
            // Si un champ obligatoire n'est pas renseigné, on saute la ligne
            if($field->skipempty==true && empty($value)){
              $message.="<dd>Compulsory field: $tzrfield empty</dd>";
              continue 2;
            }
            // Si le format d'édition n'est pas respecté, on saute la ligne
            if($field->skiponbadformat==true && !empty($value) && empty($ret['value'])){
                $message.="<dd>$tzrfield: value doesn't match the edit format constraint. Line skipped</dd>";
                $nok++;
                continue 2;
            }

            $input[$tzrfield]=$ret['value'];
	    if (isset($field->merge)){
	      $merges[] = $tzrfield;
	      $input['options'][$tzrfield] = array_merge($input['options'][$tzrfield]??[], ['merge'=>1,$tzrfield.'_op'=>'+']);
	    }
            if(empty($input[$tzrfield]) && !empty($defaultvalue)) $input[$tzrfield]=$defaultvalue;
            if(!empty($ret['message'])) $message.='<dd>'.$ret['message'].'</dd>';
          }
        }
        if($tzrfield != 'LANG' AND in_array($tzrfield,$unique)){
            $uniquevalue = $input[$tzrfield];
            if(is_array($uniquevalue)) $uniquevalue=$uniquevalue[0];
            $trimedvalue = trim(addslashes($uniquevalue));
            if($trimedvalue !== "") {
                $refsql[] = "$tzrfield = '$trimedvalue'";
            }
            else {
                $refsql[] = "($tzrfield = '' OR $tzrfield is null)";
            }
        }
      }
      // Recupere un eventuel oid
      if(!empty($koid)){
        if(strpos($input[$koid],':')) $input['newoid']=$input[$koid];
        else $input['newoid']=$this->table.':'.rewriteToAscii($input[$koid]);
      }elseif(!empty($input['KOID'])){
        if(strpos($input['KOID'],':')) $input['newoid']=$input['KOID'];
        else $input['newoid']=$this->table.':'.rewriteToAscii($input['KOID']);
      }elseif(!empty($refsql) && !empty($updateifexists)){
	if (empty($merges)){
	  $sele = 'SELECT KOID FROM '.$this->table.' WHERE LANG = \''.TZR_DEFAULT_LANG.'\' AND '.implode(' AND ', $refsql);
	} else {
	  $sele = 'SELECT KOID, '.implode(',', $merges).' FROM '.$this->table.' WHERE LANG = \''.TZR_DEFAULT_LANG.'\' AND '.implode(' AND ', $refsql);
	}
        $rsk = getDB()->select($sele);
        $Koid ='';
        if($rsk && $rsk->rowCount()==1 && $orsk = $rsk->fetch(\PDO::FETCH_ASSOC)){
          $input['newoid'] = $orsk['KOID'];
	  // valeurs actuelles des champs à merger
	  foreach($merges as $mfn){
	    $input['options'][$mfn]['old'] = $orsk[$mfn];
	  }
        }
      }
      $input['_updateifexists']=$updateifexists;
      $input['_delayed']=false;
      $input['_nolog']=$nolog;
      $input['tplentry']=TZR_RETURN_DATA;
      if(empty($input['LANG']) || $input['LANG']==TZR_DEFAULT_LANG || $this->xset->getTranslatable()==3){
        if(!empty($input['LANG']) && $input['LANG']!=TZR_DEFAULT_LANG && !$this->secure('','procInsert',$u=NULL,$input['LANG'])){
          $message.='<dd>Write in '.$input['LANG'].' is not allowed</dd>';
          $nok++;
        }else{
          $input['LANG_DATA']=$input['LANG'];
          if(!empty($input['newoid']))  $input['oid']=$input['newoid'];
	  $ctrlok = $this->procInsertCtrl($input);
	  if (!$ctrlok){
	    $ctrlmessage = \Seolan\Core\Shell::from_screen('', 'message');
	    if (empty($ctrlmessage)){
	      $ctrlmessage = '<dd>Error, invalid data</dd>';
	    } else {
	      $ctrlmessage = '<dd>Error invalid data : "'.$ctrlmessage.'"</dd>';
	    }
	    $message.=$ctrlmessage;
	    $nok++;
	    unset($input);
	    continue;
	  }
          $r=$this->procInsertImport($input);
          if(!empty($r['oid']) && empty($r['updated'])){
            if(!$found) $found=true;
            $message.='<dd>Insert : success -> '.$r['oid'].'</dd>';
            $ok++;
          }elseif(empty($r['error']) && $r['updated']==='noupdate'){
            $message.='<dd>Insert : update an existing entry - no change</dd>';
            $update++;
          }elseif(empty($r['error']) && $r['updated']){
            $message.='<dd>Insert : update an existing entry</dd>';
            $update++;
          }else{
            $message.='<dd>Insert : error ('.$r['message'].')</dd>';
            $nok++;
          }
        }
      }elseif(!empty($input['newoid']) ){
        if(!array_key_exists($input['LANG'],$GLOBALS['TZR_LANGUAGES'])){
          $message.='<dd>Lang '.$input['LANG'].' does not exist</dd>';
          $nok++;
        }elseif($this->secure('','procEdit',$u=NULL,$input['LANG'])){
          $input['LANG_DATA']=$input['LANG'];
          if(!empty($input['newoid']))  $input['oid']=$input['newoid'];
	  $ctrlok = $this->procEditCtrl($input);
	  if (!$ctrlok){
	    $ctrlmessage = \Seolan\Core\Shell::from_screen('', 'message');
	    if (empty($ctrlmessage)){
	      $ctrlmessage = '<dd>Error, invalid data</dd>';
	    } else {
	      $ctrlmessage = '<dd>Error invalid data : "'.$ctrlmessage.'"</dd>';
	    }
	    $message.=$ctrlmessage;
	    $nok++;
	    unset($input);
	    continue;
	  }
          $r=$this->procEditImport($input);
          $message.='<dd>Update '.$input['oid'].' in '.$input['LANG'].'</dd>';
          $update++;
        }else{
          $message.='<dd>Write in '.$input['LANG'].' is not allowed</dd>';
          $nok++;
        }
      }else{
        $message.='<dd>Not default lang and no KOID</dd>';
        $nok++;
      }
      unset($input);
    }
    $message.='</dl>';
    if ($this->secure('', ':rwv')){
      $message='Total : '.$tot.'<br>Insert : '.$ok.'<br>Update : '.$update.'<br>Error : '.$nok.'<br>'.
	       'Empty lines : '.count($emptylines).' ('.implode(', ',$emptylines).')<br>'.
	       'Incomplete line : '.count($incompletelines).' ('.implode(', ',$incompletelines).')<br>'.$message;
      Shell::toScreen2('','message',$message);
    } else {
      $message = sprintf(
	\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','importresultmessage'),
	$tot, $ok, $update, $nok
      ) . $message;
      Shell::alert($message, 'info');
    }
    return $found;
  }

  protected function procInsertImport($input) {
    return $this->xset->procInput($input);
  }

  protected function procEditImport($input) {
    return $this->xset->procEdit($input);
  }

  function sub($ar) {
    return parent::sub($ar);
  }
  protected function _lasttimestamp() {
    $upd=getDB()->fetchOne('select ifnull(MAX(UPD),0) from '.$this->table);
    if($this->trackchanges && !$this->getFilter() && !$this->object_sec)
      $upd2=getDB()->fetchOne('select ifnull(MAX(UPD),0) from LOGS where etype="delete" and comment like "%('.$this->table.':%"');
    else
      $upd2=0;
    return max($upd,$upd2);
  }


  /// rend la liste des fiches modifiees depuis ts et jusqu'a timestamp
  protected function _whatsNew($ts,$user, $group=NULL, $specs=NULL,$timestamp=NULL) {
    if(!is_array($specs)) return;
    $oid=@$specs['oid'];
    $details=@$specs['details'];
    $query="SELECT KOID FROM {$this->table} where UPD >= \"$ts\" and UPD<\"$timestamp\"";
    if($oid) $query.=" AND KOID=\"$oid\"";
    $oids = $this->xset->browseOids(array('select'=>$query, 'pagesize'=>'99', 'tplentry'=>TZR_RETURN_DATA, '_filter'=>$this->getFilter()));
    $txt='';
    foreach ($oids as $oid) {
      $entry=$this->_makeSubEntry($oid, $this->xset, $details, $ts, $timestamp, $user, NULL);
      $txt.=$entry;
    }
    if($this->trackchanges && !$this->getFilter() && !$this->object_sec){
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=LOGS');
      $rs=getDB()->fetchAll("select * from LOGS where UPD>=? and UPD<? and etype in (?,?) and (comment like \"%({$this->table}:%)\" or object like \"{$this->table}%\") order by UPD desc",
			    array($ts, $timestamp, 'delete','movefromtrash'));
      foreach($rs as $ors) {
	$d=$x->rDisplay($ors['KOID'],$ors,false);
	if ($d['oetype']->raw == 'delete'){
	  $what = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','deletion');
	} else {
	  $what = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','undeletion');
	}
	if (empty($d['ocomment']->raw)
	    && substr($d['odetails']->raw,0,5)=='<?xml'){
	  $details = \Seolan\Core\System::xml2array($d['odetails']->raw);
	  $comment = $details['dlink'];
	} else {
	  $comment = $d['ocomment']->text;
	}
	$txt.='<li>'.$comment.' ('.$d['oUPD']->html.', '.$d['ousernam']->html.', '.$what.')</li>';
      }
      unset($rs);
    }
    return $txt;
  }

  function goto1($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(false,true);
    $moid=$this->_moid;
    $right= $this->secure($oid, 'display');
    if(!$right) \Seolan\Library\Security::warning('\Seolan\Module\Table\Table::goto1: could not access to objet '.$oid.' in module '.$moid);
    header("Location: {$url}&moid=$moid&template=Module/Table.view.html&oid=$oid&function=display&tplentry=br");
  }
  function gDisplay($ar=NULL) {
    self::developer($ar);
    $p=new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get('tplentry');
  }
  function developer($ar=NULL) {
    parent::developer($ar);
    $r['lines_title'][]='Display template';
    $r['lines_url'][]='&function=gDisplay';
    \Seolan\Core\Shell::toScreen1('dev',$r);
  }

  // rend le journal des modifs d'un objet, en fonction du profil user connecté
  //
  public function journal($ar) {
    $p = new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $oid=$p->get('oid');
    $etypes = ['create','update','rule'];
    if (\Seolan\Core\Shell::isRoot())
      $etypes[] = 'autoupdate';
    $r=\Seolan\Core\Logs::getJournal($oid,['etype'=>['=',$etypes]],NULL,NULL,$this->xset, $this->fieldssec);
    $this->browseSumFields($ar, $r, true);
    \Seolan\Core\Shell::toScreen2("logs","array",$r);
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }
  /**
   * preparation des données commentaires
   */
  protected function prepareComments(&$r2, $oid){
    if ($this->allowcomments && \Seolan\Core\Shell::admini_mode()){
      $r2['_comments'] = ['nb'=> \Seolan\Model\DataSource\Comments\Comments::factory()->numberOfComments($oid)];
    }
  }
  /**
   * lecture des commentaires
   */
  public function getComments($ar){

    $p = new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $oid=$p->get('oid');
    $r= \Seolan\Model\DataSource\Comments\Comments::factory()->getObjectComments($oid);
    $r['user']=$GLOBALS['XUSER']->_cur['fullnam']; // sert à afficher dans commentaire.html le nom de l'user de la session, voir avec \Seolan\Core\User::get_user()->_cur['fullnam']
    $r['_oid']=$oid;
    $r['navig']=$p->get('navig');   //permet d'afficher la barre de navigation, est alimenté par les modules n'utilisant pas le gabarit view.html
    $r['input_placeholder'] = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General','comments_placeholder');

    return \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }
  public function resetCommentsActionList(&$my){
    //Suppression de tous les commentaires de ce module
    if($this->secure('','resetAllCommentsFromModule')){
      $o1=new \Seolan\Core\Module\Action($this,'resetAllCommentsFromModule',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','reset_comments_mod'),
					 '&moid='.$this->_moid.'&tplentry=br&_function=resetAllCommentsFromModule&template=Core.resetcomment.html&data='.$this->xset->getTable(),'editprop');
      $o1->needsconfirm=true;
      $my['resetAllCommentsFromModule']=$o1;
    }
  }
  // ? A voir : les commentaires du blog
  public function commentActionList($class,&$my,$moid,$oid){
    if($class->allowcomments){
      $o2=new \Seolan\Core\Module\Action($this, 'viewComment', 'Afficher les commentaires du blog',
					 '&moid='.$moid.'&tplentry=br&function=commentaire&template=Module/Table.commentaire.html&oid='.$oid.'&navig=true');
      $o2->group='more';
      $o2->menuable=true;
      $my['blogcomments']=$o2;
    }
  }
  public function insertComment($ar){
    $ar['moid'] = $this->_moid;
    $ar['modulename'] = $this->getLabel();

    \Seolan\Model\DataSource\Comments\Comments::factory()->insertComment($ar);
  }

  public function resetComments($ar){
    return \Seolan\Model\DataSource\Comments\Comments::factory()->resetComments($ar);
  }

  public function resetAllCommentsFromModule($ar){
    return \Seolan\Model\DataSource\Comments\Comments::factory()::resetAllCommentsFromModule($ar);
  }

  /// Validation des oid ou des rubriques
  public function publish($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $oids=\Seolan\Core\Kernel::getSelectedOids($p);
    foreach($oids as $oid) {
      if($this->secure($oid,'publish')){
	$ar1['_selected'] = array();
	$ar1['value'] = $p->get('value');
	$ar1['_local'] = true;
	$ar1['oid']= $oid;
	$this->xset->publish($ar1);
      }
    }
  }


  /// Retourne le filtre du module
  public function getFilter($instanciate=true,$ar=array()) {
    $q=getSessionVar('filterquery'.$this->_moid);
    // Pas très beau,  mais afin de contourner le filtre dans les actions venant de la selection, si on est dans l'admin et ques des oids sont fournis, alors on ne l'applique pas.
    if(\Seolan\Core\Shell::admini_mode()){
      $oids=\Seolan\Core\Kernel::getSelectedOids($ar);
      if(!empty($oids) && \Seolan\Core\Kernel::getTable($oids[0])==$this->table){
	$q=null;
      }
    }
    if(!empty($q)){
      // si pas de jointure on renvoie un filtre simple
      if (preg_match('/select .* from (.*) where (.*)/i', $q, $matches) && $matches[1] == $this->table) {
        $q=preg_replace('/order by .*$/i','',$matches[2]);
        $filter='('.$q.')';
      } else { // sinon un subselect
        $q=preg_replace('/select .* from/i','select '.$this->table.'.KOID from',$q);
        $q=preg_replace('/order by .*$/i','',$q);
        $filter=$this->table.'.KOID in ('.$q.')';
      }
    }else{
      $filter=$this->filter;
      if($instanciate) {
	$context=array();
	$u=\Seolan\Core\User::get_user();
	$context['/(\$\(user\))/']=\Seolan\Core\User::get_current_user_uid();
	if(!empty($u->_cur['alias'])) $context['/(\$\(user\.alias\))/']=$u->_cur['alias'];
	$filter=preg_replace(array_keys($context),array_values($context),$filter);
      }

      $comp_filter = $this->getCompulsoryFilter($ar);

      if (!empty($comp_filter['filter'])) {
        if (empty($filter)) {
          $filter = $comp_filter['filter'];
        } else {
          $filter = '('.$filter.') AND ('.$comp_filter['filter'].')';
        }
      }
    }
    if(!empty($ar['_filter'])){
      if($filter) $filter='('.$filter.') AND ('.$ar['_filter'].')';
      else $filter=$ar['_filter'];
    }

    if (TZR_USE_APP && $this->xset->fieldExists('APP') && $this->activeAppContext != false) {
      $bootstrapApplication = \Seolan\Module\Application\Application::getBootstrapApplication();
      $condApp = $this->table.'.APP='.getDB()->quote($bootstrapApplication->oid);
      $filter = empty($filter) ? $condApp : "(".$filter.") AND ".$condApp;
    }

    return $filter?'('.$filter.')':'';
  }

  public function getCompulsoryFilter(&$ar) {
    $p = new \Seolan\Core\Param($ar);
    $filter = '';
    $oFieldQuery = null;
    $function = $p->get('function') ?? $p->get('_function');

    if (in_array($function, ['browse', 'prePrintBrowse', 'preExportBrowse', 'export'], true) && $this->hasCompulsoryFilter()) {
      $field = $this->xset->desc[$this->query_comp_field];
      if (($field->isLink() || $field->get_ftype() === '\Seolan\Field\StringSet\StringSet') && strpos($this->query_comp_field_value, '||') !== false) {
        $ar[$this->query_comp_field] = explode('||', $this->query_comp_field_value);
      } else {
        $ar[$this->query_comp_field] = $this->query_comp_field_value;
      }

      $ar[$this->query_comp_field.'_op'] = $this->query_comp_field_op;

      $oFieldQuery = $field->_newXFieldQuery();
      $oFieldQuery->value = $ar[$this->query_comp_field];
      $oFieldQuery->op = $this->query_comp_field_op;
      $field->post_query($oFieldQuery, $ar);

      if (!empty($oFieldQuery->rq)) {
        $filter = $oFieldQuery->rq;
      }
    }

    return ['filter' => $filter, 'field_query' => $oFieldQuery];
  }

  /**
   * @author Bastien Sevajol
   *
   * Ajout d'une condition au SQL WHERE dans l'attribut filter du module.
   *
   * @param array|string $new_filter Filtre à ajouter au filtre courant
   *   Si c'est une chaine, on l'ajoute directement aux filtres, sinon le tableau doit être de la forme QUERY_COND
   * @param string $separator AND par défaut
   */
  public function addFilter($new_filter, $separator = 'AND') {
    // On prévient les accidents
    if (empty($new_filter))
      return false;
    // Cas d'un tableau à transformer en chaine
    if (is_array($new_filter)) {
      $this->addCondFilter($new_filter);
    }
    if ($this->getFilter()) {
      $this->filter = $this->getFilter() . " $separator ($new_filter)";
    } else {
      $this->filter = $new_filter;
    }
  }
  /**
   * @author Camille Descombes
   *
   * Ajoute un filtre au module via un paramètre de la forme QUERY_COND
   * cela permet de créer les conditions WHERE en fonction du type de champ
   *
   * @param array $cond Tableau de conditions QUERY_COND ['field1' => ['=', 'value'], 'field2' => ['=', [1,2,3]], ...]
   * @param string $separator AND par défaut
   */
  function addCondFilter($cond, $separator = 'AND') {
    foreach ($cond as $field => $operator_and_values) {
      if (!$this->xset->fieldExists($field)) {
        XLogs::critical("Cannot add module filter: field '$this->table.$field' not exists");
        continue;
      }
      $this->addFilter($this->xset->make_cond($this->xset->desc[$field], $operator_and_values), $separator);
    }
  }

  /// Filtre sql utilisé par les \Seolan\Core\Field\Field pour filtrer les valeurs utilisées
  function getUsedValuesFilter(){
    return $this->getFilter();
  }

  public function isThereAQueryActive() {
    if(sessionActive()) {
      $_storedquery=$this->_getSession('query');
      if($_storedquery && ($_storedquery['_table']==$this->table)) {
	return true;
      }
    }
    return false;
  }
  /// gestion du contexte sous module
  /// $shift dépile le premier élément dans l'url, pour un retour à la fiche parente
  protected function subModuleContext($ar=array(), $shift=false){
    $p = new \Seolan\Core\Param($ar, array());
    $_parentoids = $p->get('_parentoids');
    $_linkedfields = $p->get('_linkedfields');
    $_fromtabs = $p->get('_fromtabs');
    $_frommoids = $p->get('_frommoids');

    $ret = false;
    if (!empty($_parentoids) && !empty($_linkedfields)) {
      $urlparms = '';
      foreach ($_parentoids as $i => $parentoid) {
        if (!$i && $shift)
          continue;
        $urlparms .= '&_parentoids[]='.$parentoid;
      }
      foreach ($_linkedfields as $i => $linkedfield) {
        if (!$i && $shift)
          continue;
        $urlparms .= '&_linkedfields[]='.$linkedfield;
      }
      foreach ($_frommoids as $i => $frommoid) {
        if (!$i && $shift)
          continue;
        $urlparms .= '&_frommoids[]='.$frommoid;
      }
      // deprecated, backward compatibility
      $urlparms .= '&_parentoid='.$_parentoids[0].'&_linkedfield='.$_linkedfields[0];
      if($_frommoids[0])
        $urlparms .= '&_frommoid='.$_frommoids[0];
      if($_fromtabs) {
        $urlparms .= '&_fromtabs='.$_fromtabs;
      }
      $ret = array('_parentoids' => $_parentoids,
                   '_linkedfields' => $_linkedfields,
                   '_fromtabs' => $_fromtabs,
                   '_frommoids' => $_frommoids,
                   'urlparms' => $urlparms);
    }
    return $ret;
  }

  /// Prepare le HTML pour l'affichage d'un captcha dans un formulaire
  function createCaptcha($ar,$force=false){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    if($this->captcha || $force){
      \Seolan\Core\Logs::notice('\Seolan\Module\Table\Table::_createcaptcha','module '.$this->_moid." ");
      $varid=uniqid('c');
      $color = \Seolan\Core\Ini::get('error_color');
      $captcha['html']='<div class="tzrDivCaptcha"><img id="cimg'.$varid.'" alt="Captcha" src="#" />'.
	'<a id="ca'.$varid.'" href="#" onclick="TZR.actualizeCaptcha(\''.TZR_CAPTCHA.'\',\''.$varid.'\'); return false;">'.
	\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','captcha_reload').'</a>'.
	'<input required type="text" id="'.$varid.'" name="captcha_key" maxlength="'.TZR_CAPTCHA_LENGTH.'" size="'.TZR_CAPTCHA_LENGTH.'" />'.
	'<input type="hidden" name="captcha_id" value="'.$varid.'" />'.
	"<script>TZR.actualizeCaptcha('".TZR_CAPTCHA."','$varid'); ".
	"TZR.addValidator(['$varid','','Captcha','$color','Captcha']);</script></div>";
      $captcha['label'] = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Table_Table','captcha_label');
      return \Seolan\Core\Shell::toScreen1($tplentry,$captcha);
    }
  }

  function createHoneypot() {
    $fields = $this->getHoneypotFields();
    $ret = '<div class="seolanhp">';
    foreach($fields as $field) {
      $ret .= '<label>'.$field['label'].'<input name="'.$field['field'].'" type="'.$field['type'].'"></label>';
    }
    $ret .= '</div><script>jQuery(function(){jQuery(".seolanhp").hide();});</script>';

    return $ret;
  }

  function checkHoneypot($ar) {
    $p = new Param($ar);
    if ($this->honeypot) {
      $fields = $this->getHoneypotFields();
      foreach($fields as $field) {
        if($p->get($field['field'])) {
          Shell::setNext(eplRoute('home'));
          return false;
        }
      }
    }

    return true;
  }

  function getHoneypotFields() {
    $fields = array(
      'name' => ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
      'email' => ['field' => 'email', 'label' => 'Email', 'type' => 'email'],
      'address' => ['field' => 'address', 'label' => 'Address', 'type' => 'text'],
    );
    $xset = $this->xset;
    foreach($fields as $i => $param) {
      $field = $param['field'];
      if($xset->fieldExists($field)) {
        $field = 'your_'.$field;
      }
      if($xset->fieldExists($field)) {
        $j = 1;
        $field = $param['field'] . $j;
        while($xset->fieldExists($field)) {
          $j++;
          $field = $param['field'] . $j;
        }
      }
      $fields[$i]['field'] = $field;
    }

    return $fields;
  }

  // implémentation de l'interface des documents
  function XMCbrowseTrash($ar){
    $ar['tplentry']=TZR_RETURN_DATA;
    return $this->browseTrash($ar);
  }
  function XMCemptyTrash($ar){
    $ar['tplentry']=TZR_RETURN_DATA;
    return $this->emptyTrash($ar);
  }
  function XMCmoveFromTrash($ar){
    $ar['tplentry']=TZR_RETURN_DATA;
    return $this->moveFromTrash($ar);
  }
  function XMCinput($ar) {
    $ar['_xmc']= true;
    return $this->insert($ar);
  }
  function XMCprocInput($ar) {
    $ar['_xmc']= true;
    return $this->procInsert($ar);
  }
  function XMCedit($ar) {
    $ar['_xmc']= true;
    return $this->edit($ar);
  }
  function XMCprocEdit($ar) {
    $ar['_xmc']= true;
    return $this->procEdit($ar);
  }
  function XMCeditDup($ar) {
    $ar['_xmc']= true;
    return $this->editDup($ar);
  }
  function XMCprocEditDup($ar) {
    $ar['_xmc']= true;
    return $this->procEditDup($ar);
  }
  function XMCdisplay($ar) {
    $ar['_xmc']= true;
    return $this->display($ar);
  }
  function XMCdel($ar) {
    $ar['_xmc']= true;
    return $this->del($ar);
  }
  function XMCfullDelete($ar){
    $ar['_xmc']=true;
    return $this->fullDelete($ar);
  }
  function XMCduplicate($oidsrc){
    $ar['_xmc']= true;
    if(!is_array($oidsrc)) $oidsrc=array('oid'=>$oidsrc);
    return $this->duplicate($oidsrc);
  }
  function XMCquery($ar){
    $ar['_xmc']= true;
    return $this->query($ar);
  }
  function XMCprocQuery($ar){
    $ar['_xmc']= true;
    return $this->procQuery($ar);
  }
  function XMCallowComments(string $oid=''){
    return $this->allowcomments && $this->secure($oid, 'getComments');
  }
  function XMCcommentsMoid($ar=null){
    return $this->_moid;
  }
  function XMCgetComments($ar){
    return $this->getComments($ar);
  }
  function XMCinsertComment($ar){
    return $this->insertComment($ar);
  }
  function XMCgetLastUpdate(string $oid){
    return \Seolan\Core\Logs::getLastUpdate($oid,NULL);
  }
  function tablesToTrack() {
    if($this->trackchanges) return array($this->table);
  }
  function apply($f) {
    $rs=getDB()->fetchCol('SELECT DISTINCT KOID FROM '.$this->table);
    foreach($rs as $ors) {
      $d=$this->xset->rDisplay($ors);
      $f($this, $d);
    }
    unset($rs);
  }

  // verification qu'un module est bien installé. Si le parametre
  // repair est a oui, on fait les reparations si possible.
  //
  public function chk(&$message=NULL) {
    parent::chk($message);
    // Créer des index sur tous les champs servant de lien dans des sous fiches
    for($i=1;$i<=$this->submodmax;$i++) {
      $l='ssmod'.$i;
      $moid=$this->$l;
      $l='ssmodfield'.$i;
      $f=$this->$l;
      if(!empty($moid) && !empty($f)){
        $mod=\Seolan\Core\Module\Module::objectFactory($moid);
        if(empty($mod->xset->desc[$f])) continue;
        $l=$mod->xset->desc[$f]->multivalued?300:40;
        if(!\Seolan\Core\System::isView($mod->table) and !getDB()->count("SHOW INDEX FROM {$mod->table} where Column_name=\"$f\"")) {
          getDB()->execute("ALTER TABLE {$mod->table} ADD INDEX $f($f($l))");
        }
      }
    }
    return true;
  }

  /// recherche si on oid est autorise dans le module
  protected function secOidOk($oid) {
    if(substr($oid,0,7)=='_field-') return true;
    if(isset($this->secOids_cache[$oid])) return $this->secOids_cache[$oid];

    // Vérifie que l'oid est dans la selection. Si oui, c'est que l'utilisateur a accès à celui ci
    $selection=$this->getSelectionFromSession();
    if($selection && array_key_exists($oid,$selection)) return true;

    // Applique le filtre du module pour vérifier si l'oid est accessible
    $tfilter=$filter=$this->getFilter(true);
    if(!empty($filter)) $filter=" AND $filter";

    $ors=getDB()->fetchOne("SELECT 1 FROM {$this->table} where KOID=? $filter", [$oid]);

    // oid absent en table et présent dans la corbeille
    if ((empty($ors) || $ors === false) && $this->usetrash && $this->xset->archiveExists()){
      if (!empty($tfilter))
	$tfilter = "{$tfilter} AND ";
      $oid = getDB()->quote($oid);
      $rs = getDB()->select($this->xset->browseTrashSelect("{$tfilter} {$this->xset->getTable()}.KOID={$oid}"));
      if ($rs->rowCount() == 1)
	$ors = 1;
    }
    $this->secOids_cache[$oid] = !empty($ors);
    return $this->secOids_cache[$oid];
  }

  /// Calcul des champs totalisés suite à un browse
  function browseSumFields($ar, &$r, $withtotal){
    $p = new \Seolan\Core\Param($ar, array());
    $tpl = $p->get('tplentry');

    // totalisation des champs numériques listés
    $numfields = array();
    $fsums = ""; $sep = '';
    foreach($r['header_fields'] as $if=>$fd){
      if (!$fd->is_summable()) continue;
      $numfield = (object)array('if'=>$if,
				'fc'=>$fd->field,
				'ffc'=>$this->table . '.' . $fd->field,
				'ffa'=>'sumof_'.$this->table . '_' . $fd->field,
				);
      $numfields[$fd->field] = $numfield;
      $fsums = $fsums . $sep . 'ifnull('.$fd->sqlsumfunction().', 0) as '.$numfield->ffa;
      $sep = ',';
    }
    if (count($numfields) == 0)
      return;
    // requete de base
    $selectsum = preg_replace('/select (.*) from (.*)$/Ui','select '.$fsums.' from $2',$r['select']);

    // total de la selection en cours
    if ($withtotal){
      $srs = getDB()->select($selectsum);
      $osrs = $srs->fetch();
    } else {
      $osrs = NULL;
    }

    // total de la page en cours
    // -> liste des oid de la page
    // -> l'ordre n'a pas d'importance
    $pagesize=$p->get('pagesize');
    if(empty($pagesize)) {
      $pagesize=$this->pagesize;
    }
    if(empty($pagesize))
      $pagesize=TZR_XMODTABLE_BROWSE_MAXPAGESIZE;
    $ar['pagesize']=$pagesize;

    $last = $p->get('last');
    if (empty($last)){
      $last = $r['last'];
    }

    if ($last > $pagesize || !$withtotal){
      // calcul du total page par iteration sur les lignes
      $osprs = array();
      foreach($r['header_fields'] as $foo=>&$fd){
	if (isset($numfields[$fd->field])){
	  $osprs[$fd->field]=0;
	  foreach($r['lines_oid'] as $il=>&$oid){
	    $valtmp=$this->xset->browseGetValueFromResult($r,$fd->field,$il);
            $osprs[$numfields[$fd->field]->ffa]+=($valtmp?$valtmp->raw:$valtmp);
	  }
	}
      }
      unset($foo);
    }
    // mise en forme des totaux
    $linetot = $linepage = '';
    $foo=array();
    foreach($r['header_fields'] as $foo=>&$fd){
      if (isset($numfields[$fd->field])){
	if($fd->alignright) $align=' style="text-align:right;"';
	if ($withtotal)
	  $linetot.='<td'.$align.'>'.$fd->my_display($osrs[$numfields[$fd->field]->ffa],$foo)->html.'</td>';
	if ($last > $pagesize || !$withtotal)
	  $linepage.='<td'.$align.'>'.$fd->my_display($osprs[$numfields[$fd->field]->ffa],$foo)->html.'</td>';
	else
	  $linepage.='<td></td>';
      } else {
	if ($withtotal)
	  $linetot.='<td></td>';
	$linepage.='<td></td>';
      }
    }
    if (!$withtotal)
      $linetot = NULL;
    if ($last <= $pagesize && $withtotal)
      $linepage = NULL;
    $sums = array();
    $sums['line_tot'] = $linetot;
    $sums['line_page'] = $linepage;
    if ($tpl != TZR_RETURN_DATA){
      \Seolan\Core\Shell::toScreen2($tpl, 'sums', $sums);
    } else {
      $r['sums']['line_tot'] = $linetot;
      $r['sums']['line_page'] = $linepage;
    }
  }

  /// Sous fonctions de parcours de la selection
  function _browseUserSelection($oid,&$data){
    if(!$this->object_sec || $this->secure($oid,'display')){
      return $this->xset->rDisplay($oid,array(),true,'','',array('fmoid'=>$this->_moid));
    }
    return false;
  }

  /// Liste des fonctions utilisable sur la selection du module
  function userSelectionActions(){
    $actions=array();
    if($this->secure('','procQuery')){
      $viewtxt=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','view');
      $actions['view']='<a href="#" onclick="TZR.SELECTION.viewselected(\''.$this->_moid.'\'); return false;">'.$viewtxt.'</a>';
    }
    if($this->secure('','editSelection')) {
      $editalltxt=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','editselection');
      $actions['editselection']='<a href="#" onclick="TZR.SELECTION.applyToInContentDiv('.$this->_moid.',\'editSelection\',false,{template:\'Module/Table.editSelection.html\',tplentry:\'br\', applyToAll:1}); return false;">'.$editalltxt.'</a>';
    }
    if($this->secure('','del')) {
      $deltxt=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete');
      $actions['del']='<a href="#" onclick="TZR.SELECTION.applyTo('.$this->_moid.',\'del\',null,null,true, null, true); return false;">'.$deltxt.'</a>';
    }
    if($this->secure('','sendACopyTo')){
      $sendtxt=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','sendacopyto');
      $actions['sendacopy']='<a href="#" onclick="TZR.SELECTION.applyToInContentDiv('.$this->_moid.',\'sendACopyTo\',false,{applyToAll:0,template:\'Core/Module.sendacopyto.html\'}); return false;">'.$sendtxt.'</a>';
    }
    if($this->secure('','export')) {
      $exportselectiontext = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','exportselection');
      $actions['exportSelection'] = '<a href="#" onclick="TZR.SELECTION.exportSelection('.$this->_moid.'); return false; ">'.$exportselectiontext.'</a>';
    }
    if($this->object_sec && $this->secure('','secEditSimple')) {
      $sectxt=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','security');
      $actions['secselection']='<a href="#" data-dismiss="modal" onclick="TZR.editSec(\''.$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true).'\',\''.$this->_moid.'\',\'\',\'selectionform'.$this->_moid.'\'); return false;">'.$sectxt.'</a>';
    }
    return $actions;
  }
  /// Indexation du module dans le moteur de recherche
  public function _buildSearchIndex($searchEngine,$checkbefore=true,$limit=NULL,$cond=NULL){
    $done=0;
    if(!empty($cond)){
      if($cond=='UPD'){
	$last=\Seolan\Core\DbIni::get('lastindexation_'.$this->_moid,'val');
	$current=date('Y-m-d H:i:s');
	if(empty($last)) {
	  $last=date('2000-01-01 00:00:00');
	}
	$cond="UPD>=\"$last\"";
      }
      $cond=' where '.$cond;
    }else{
      $cond=' where 1';
    }
    $filter=$this->getFilter(true);
    if($filter) $cond.=' AND '.$filter;
    $rs=getDB()->select('SELECT KOID, LANG, UPD FROM '.$this->table.' '.$cond.' order by UPD ASC');

    \Seolan\Core\Logs::notice(__METHOD__,$rs->rowcount().' lines to re index '.$cond);

    while($rs && ($ors=$rs->fetch())) {
      \Seolan\Core\Logs::debug(__METHOD__.' '.$this->_moid.' testing '.$ors['KOID']);
      // à voir la langue
      if($checkbefore && $searchEngine->docExists($ors['KOID'],$this->_moid,$ors['LANG'])) continue;
      \Seolan\Core\Logs::debug(__METHOD__.' '.$this->_moid.' adding '.$ors['KOID']);
      $this->addToSearchEngine($searchEngine,$ors['KOID'], $ors['LANG']);
      $done++;
      if($done%30==0){
	if(!empty($current)) \Seolan\Core\DbIni::set('lastindexation_'.$this->_moid,$ors['UPD']);
 	\Seolan\Core\Logs::debug(' '.$this->_moid.' commit');
        $searchEngine->index->commit();
      }
      if($limit && $done>$limit){
 	\Seolan\Core\Logs::debug('\Seolan\Module\Table\Table'.$this->_moid.'::buildSearchIndex: break at '.$done);
	break;
      }
    }
    $searchEngine->index->commit();
    if(!empty($current)) \Seolan\Core\DbIni::set('lastindexation_'.$this->_moid,$current);
    return true;
  }

  /// presentation d'un resultat de recherche dans le module
  public function showSearchResult($oids) {
    $_REQUEST = array(
      'function' => 'procQuery',
      'template' => 'Module/Table.browse.html',
      'moid' => $this->_moid,
      'tplentry' => 'br',
      'clearrequest' => 1,
      'oids' => $oids
    );
    $GLOBALS['XSHELL']->run();
    exit;
  }

  /// Ajout d'une fiche au moteur de recherche : données
  function &getSearchEngineData($oid, $lang) {
    // selected fields = published + indexables
    $indexables = $this->xset->getIndexablesFields();
    $publisheds = $this->xset->getPublished(true);
    $tagfields = $this->xset->getTagFields();
    $usertagfields = $this->xset->getUserTagFields();
    $selecteds = array_merge($indexables, $publisheds);
    $innotice = array_diff($indexables, $publisheds);
    $d=$this->xset->display(array('tplentry'=>TZR_RETURN_DATA,
				  'LANG_DATA'=>$lang,
				  'oid'=>$oid,
				  'tlink'=>true,
				  'selectedfields'=>$selecteds,
				  '_lastupdate'=>false));
    $text=getFilesContent($d, $indexables, TZR_INDEXABLE_FILE_MAXSIZE);
    $notice = '';
    $tags = $usertags = null;
    foreach($innotice as $f){
      if (isset($d['o'.$f])){
          $txtval = $d['o'.$f]->toText();
          $notice .= $txtval.' ';
      }
    }
    foreach($indexables as $f){
      if (isset($d['o'.$f])){
          $txtval = $d['o'.$f]->toText();
          $rawval = $d['o'.$f]->raw;
          if (!empty($tagfields[$f])) {
              $tags .= $txtval.' ';
          }
          $matches = array();
          if (!empty($usertagfields[$f]) && preg_match_all('/\B'.\Seolan\Field\Text\Text::$USERTAG_PREFIX.'([^\(]*)\([^\|]*\|[^\)]+\)/', $rawval, $matches)) {
              foreach ($matches[1] as $user) {
                  $usertags .= $user.' ';
              }
          }
      }
    }

    $fields = ['title'=>strip_tags($d['tlink']), 'notice'=>trim($notice), 'contents'=>&$text, 'tags'=>trim($tags), 'usertags'=>trim($usertags)];
    return $fields;
  }

  /// Ajout d'une fiche au moteur de recherche
  function addToSearchEngine($searchEngine,$oid, $lang) {
    $fields = $this->getSearchEngineData($oid, $lang);
    $searchEngine->addItem($oid,$fields,$this->_moid, $lang);
    unset($fields);
    \Seolan\Core\Logs::notice(get_class($this),get_class($this).'::addToSearchEngine lucene');
  }

  /// Action effectuée à chaque suppression d'un oid de la base
  function _removeRegisteredOid($oid) {
    // Suppression du moteur de recherche
    if($this->insearchengine && \Seolan\Core\Kernel::getTable($oid)==$this->table){
      $se=\Seolan\Library\SolR\Search::objectFactory();
      $se->deleteItem($oid,$this->_moid);
    }
  }


  ////////////////////////////////////
  // FONCTIONS INTERFACE XCAL       //
  ////////////////////////////////////
  /// Retourne la liste des champs pouvant servir à la consolidation
  function XCalParamsConsolidation($ar){
    $ar1=$ar2=array();
    $p=new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get('tplentry');
    $datefields=$this->xset->getFieldsList(array('\Seolan\Field\Date\Date','\Seolan\Field\DateTime\DateTime','\Seolan\Field\Timestamp\Timestamp'));
    $stextfields=$this->xset->getFieldsList(array('\Seolan\Field\ShortText\ShortText','\Seolan\Field\Link\Link'));
    $textfields=$this->xset->getFieldsList();
    foreach($datefields as $opt){
      $info=$this->xset->getField($opt);
      $ar1[$opt]=$info->label;
    }
    foreach($stextfields as $opt){
      $info=$this->xset->getField($opt);
      $ar2[$opt]=$info->label;
    }
    foreach($textfields as $opt){
      $info=$this->xset->getField($opt);
      $ar3[$opt]=$info->label;
    }
    \Seolan\Core\Shell::toScreen2($tplentry,'date',$ar1);
    \Seolan\Core\Shell::toScreen2($tplentry,'stexte',$ar2);
    \Seolan\Core\Shell::toScreen2($tplentry,'texte',$ar3);
  }

  function XCalGetConsolidationQuery(&$diary,$params,$fields,$begin,$end,$type='all'){
    $vals=['visib'=>'PU','DKOID'=>$this->_moid,'DNAME'=>$this->getLabel()];
    if($this->xset->desc[$params['begin']]->ftype=="\Seolan\Field\Date\Date" || $this->xset->desc[$params['end']]->ftype=="\Seolan\Field\Date\Date"){
      if($type=='event') return NULL;
      $vals['allday']=1;
    }else{
      $datetime=true;
      if($type=='event'){
        $vals['allday']=0;
        $begin=date('Y-m-d H:i:s',strtotime($begin.' GMT'));
        $end=date('Y-m-d H:i:s',strtotime($end.' GMT'));
      }elseif($type=='note'){
        $vals['allday']=1;
      }
    }
    $filter=$this->getFilter(true);
    if(!empty($filter)) $filter=' AND '.$filter;
    $filter.=" AND (date_format({$params['begin']},\"%Y-%m-%d %H:%i:%s\") BETWEEN \"$begin\" AND \"$end\" OR ".
      "\"$begin\" BETWEEN date_format({$params['begin']},\"%Y-%m-%d %H:%i:%s\") AND ".
      "date_format({$params['end']},\"%Y-%m-%d %H:%i:%s\"))";
    if($datetime){
      if($type=='event'){
	$filter.=' AND (date_format('.$params['begin'].',"%H:%i")!="00:00" OR date_format('.$params['end'].',"%H:%i")!="00:00")';
      }elseif($type=='note'){
	$filter.=' AND date_format('.$params['begin'].',"%H:%i")="00:00" AND date_format('.$params['end'].',"%H:%i")="00:00"';
      }
    }
    $rq=$this->xset->select_query(array('local'=>true,'getselectonly'=>true)).$filter;
    if($this->object_sec){
      $oids=$this->xset->browseOids(array('select'=>$rq,'local'=>true));
      $lang_data=\Seolan\Core\Shell::getLangData();
      $oidsrights=$GLOBALS['XUSER']->getObjectsAccess($this,$lang_data,$oids);
      foreach($oidsrights as $i=>$rights) {
        if(!array_key_exists('ro',$rights)) unset($oids[$i]);
      }
      $rq.=' AND KOID IN("'.implode('","',$oids).'")';
    }

    $select='select KOID,LANG,"'.$diary['KOID'].'" AS KOIDD';
    foreach($fields as $f){
      if($f=='begin' || $f=='end'){
	if($vals['allday']!=1) $select.=',convert_tz(date_format('.$params[$f].',"%Y-%m-%d %H:%i:%s"),"'.date("P").'","+00:00") as '.$f;
	else $select.=',date_format('.$params[$f].',"%Y-%m-%d %H:%i:%s") as '.$f;
      }
      elseif(isset($vals[$f])) $select.=',"'.$vals[$f].'" as '.$f;
      elseif(!empty($params[$f])) $select.=','.$params[$f].' as '.$f;
      else $select.=', NULL as '.$f;
    }
    $select.=',"'.$this->_moid.'" as MOID';
    $rq=preg_replace('/select .+ from /i',$select.' from ',$rq);
    return $rq;
  }

  function XCalGetUrl($type){
    if($type=='display') return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&oid=%s&function=display&tplentry=br&template=Module/Table.view.html';
    else return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=browse&tplentry=br&template=Module/Table.browse.html';
  }

  function XCalRDisplay($oid,$params){
    $opts['selectedfields']=array_values($params);
    $d=$this->xset->rDisplay($oid,array(),false,'','',$opts);
    foreach($params as $evf=>$f){
      if(isset($d['o'.$f])){
	$d['o'.$evf]=&$d['o'.$f];
      }
    }
    return $d;
  }


  ////////////////////////////////////
  // FONCTIONS INTERFACE XFORM      //
  ////////////////////////////////////
  /// Recupération de la source de données
  function XFormGetDataSource(){
    return $this->xset;
  }
  /// Insertion
  function XFormInput($ar){
    return $this->insert($ar);
  }
  /// Edition
  function XFormEdit($ar){
    return $this->edit($ar);
  }
  /// Validation de l'insertion
  function XFormProcInput($ar){
    return $this->procInsert($ar);
  }
  /// Validation de l'édition
  function XFormProcEdit($ar){
    return $this->procEdit($ar);
  }
  /// Parcours
  function XFormBrowse($ar){
    return $this->browse($ar);
  }

  /// activite de l'activité récente sur le module
  function activity($ar=NULL) {
    $query='SELECT LOGS.* FROM '.$this->table.', LOGS WHERE '.$this->table.'.KOID=LOGS.object';
    $logs=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=LOGS');
    $p1=array('select'=>$query,'selected'=>0,'pagesize'=>1000,'order'=>'dateupd DESC','tplentry'=>TZR_RETURN_DATA,'selectedfields'=>'all','_local'=>true, 'tplentry'=>TZR_RETURN_DATA);
    $r=$logs->browse($p1);
    foreach($r['lines_oid'] as &$oid)  {
      if(!$this->secure($oid, 'activity')) {
	unset($oid);
      }
    }

    return \Seolan\Core\Shell::toScreen1('br',$r);
  }

  /// Rend les documents qui n'ont jamais été consultés
  function getUnread($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('nb'=>20));
    $tplentry=$p->get('tplentry');
    $docs=array();
    $rs=getDB()->select('select '.$this->table.'.* from '.$this->table.' left outer join LOGS on '.$this->table.'.KOID=LOGS.object and LOGS.etype="access" and LOGS.user="'.\Seolan\Core\User::get_current_user_uid().'" and LOGS.UPD>'.$this->table.'.UPD where LOGS.KOID is null order by '.$this->table.'.UPD');
    while($rs && $ors=$rs->fetch()){
      $oid=$ors['KOID'];
      if(!$this->secure($oid,'display')) continue;
      $docs[]=$this->xset->rDisplay($oid,array(),false,'','');
    }
    return \Seolan\Core\Shell::toScreen2($tplentry,'docs',$docs);
  }

  /// Marque des documents comme lu
  function markAsRead($ar=NULL){
    $oids = \Seolan\Core\Kernel::getSelectedOids($ar);
    foreach($oids as $oid){
      \Seolan\Core\Logs::uniqueUpdate('access',$oid);
    }
  }

  /// Recupere des infos sur le module
  function getInfos($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>TZR_RETURN_DATA));
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=parent::getInfos($ar);
    // Nombre d'enregistrement dans la table
    $filter=$this->getFilter(true,$ar);
    $ret['infos']['cnt']=(object)array('label'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','records'),
				       'html'=>getDB()->count('select count(distinct KOID) from '.$this->table.($filter?' where '.$filter:'')));
    // Place occupé sur le disque par les data : Appliquer le filtre au calcul...
    $s=\Seolan\Core\DbIni::get('xmodadmin:workspacesize_'.$this->table,'val');
    $sa=\Seolan\Core\DbIni::get('xmodadmin:workspacesize_A_'.$this->table,'val');
    $ret['infos']['size']=(object)['label'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','workspace')];
    $ret['infos']['a_size']=(object)['label'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','archive')];
    if($s!==NULL) $ret['infos']['size']->html=getStringBytes($s*1024);
    else $ret['infos']['size']->html=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','infonotcalculate');
    if($sa!==NULL) $ret['infos']['a_size']->html=getStringBytes($sa*1024);
    else $ret['infos']['a_size']->html=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','infonotcalculate');
    if ($this->usetrash) $ret['infos']['a_size']->label.= ' / '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','trash');

    // mode traduction
    if ($this->_issetSession('mlang_trad')){
      $ret['infos']['translation_mode'] = (Object)['label'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','languages_translation_mode'),
						   'html'=>$this->_getSession('mlang_data').' &lt; '.$this->_getSession('mlang_trad')];
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /**
   * détection du mode traduction
   */
  function translationMode($params){
    if (!($xsettranslatable = $this->xset->getTranslatable())){
      return false;
    }
    $tm = (Object)array();
    $tm->lang_other = NULL;
    $tm->LANG_TRAD=\Seolan\Core\Shell::getLangTrad($params->get('LANG_TRAD'));
    $tm->LANG_DATA=\Seolan\Core\Shell::getLangData($params->get('LANG_DATA'));

    \Seolan\Core\Logs::debug(__METHOD__.' lang trad '.$tm->LANG_TRAD.', lang_data '.$tm->LANG_DATA.', lang other '.$tm->lang_other);

    // pour le moment, table "free" n'a pas de mode traduction
    if(!empty($tm->LANG_TRAD)
       && ($tm->LANG_DATA != $tm->LANG_TRAD)
       && (TZR_LANG_FREELANG != $xsettranslatable)){
      $tm->lang_other=$tm->LANG_DATA;
    }
    // préciser les cas non traduisibles ?
    if ($tm->lang_other == NULL){
      return false;
    }
    return $tm;
  }
  /**
   * liste des langues (_langspropagate) equivalentes
   * -> configuration du datasource
   * -> langues authorisées pour l'oid en edition
   */
  protected function getLangsRepli($langupdated, $oid, $func='procEdit'){
    return $this->xset->getLangsRepli($langupdated, $this->getAuthorizedLangs('all', $oid, $func));
  }
  /**
   * langue des données +/- le mode traduction
   * faire un tag avec un eventuel module en paramètre
   */
  function languagesInfosFlags($ar=null){
    if (\Seolan\Core\Shell::getMonoLang()){
      return '';
    }
    $LANG_DATA = $LANG_TRAD = NULL;
    if (($translationMode = $this->translationMode(new \Seolan\Core\Param($ar)))){
        $LANG_DATA = $translationMode->LANG_DATA;
        $LANG_TRAD = $translationMode->LANG_TRAD;
        if ('display' == \Seolan\Core\Shell::_function()){
            $LANG_TRAD = null;
        }
    } else {
        $translatable = $this->xset->isTranslatable();
        if ( $translatable){
            $LANG_DATA = \Seolan\Core\Shell::getLangData();
        } else {
            $LANG_DATA = TZR_DEFAULT_LANG;
        }
    }
    $lang = \Seolan\Core\Lang::get($LANG_DATA);
    $flags = $lang['long'];
    if (isset($LANG_TRAD)){
      $lang = \Seolan\Core\Lang::get($LANG_TRAD);
      $flags = $lang['long'].'&nbsp;&gt;&nbsp;'.$flags;
    }
    return $flags;
  }
  public function getTranslatable(){
    return $this->xset->getTranslatable();
  }
  /**
   * detection d'une langue synchronisee (langrepli, propagate)
   */
  protected function getLangRepli($lang){
    if ($this->xset->getTranslatable() && ($lang != TZR_DEFAULT_LANG)){
      $prop = 'langrepli_' . $lang;
      if (isset($this->xset->$prop) && in_array($this->xset->$prop, array_keys($GLOBALS['TZR_LANGUAGES']))){
	return $this->xset->$prop;
      }
    }
    return false;
  }
  /**
   * Verifie qu'une fonction est accessible pour cet objet
   * -> en particulier lors des changements de langues
   * -> edit et edit en mode traduction sont <>
   * -> les données n'existent pas toujours selon le mode et ce peut-être normal
   */
  function checkLanguageContext($p, $context){
    if (!\Seolan\Core\Shell::admini_mode() || \Seolan\Core\Shell::getMonoLang()){
      return true;
    }
    switch($context->function){
    case 'edit': // edition demandée
      $context->translationMode = $this->translationMode($p);
      $context->translatable = $this->xset->getTranslatable();
      if (\Seolan\Core\Shell::_function() != 'editTranslations'){
	if ($context->translationMode){ // redirection sur mode traduction
	  $context->next = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, false).'&skip=1&moid='.$this->_moid.'&oid='.$p->get('oid').'&tplentry=br&function=editTranslations&template='.static::$editTranslationTemplate;
	  return false;
	}
	if ($context->translatable){
	  if (!$this->xset->objectExists($p->get('oid'), \Seolan\Core\Shell::getLangData($p->get('LANG_DATA')))){
	    if (TZR_LANG_FREELANG == $context->translatable){
	      \Seolan\Core\Shell::setNextData('message', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'translation_missing_data'));
	      $context->next = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(false, false, false).'&skip=1&moid='.$this->_moid.'&tplentry=br&function=insert&template=Module/Table.new.html';
	    } else {
	      \Seolan\Core\Shell::setNextData('message', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'translation_missing_data'));
	      $context->next = $this->getMainAction();
	    }
	    return false;
	  }
	}
      }
      // vérifier que les contenus sont modifiables dans cette langue <- replication auto de langues (langrepli)
      if ($context->translatable && $this->xset->getAutoTranslate()){
	$lang_data = \Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));
	$langsrc = $this->getLangRepli($lang_data);
	if ($langsrc){
	  \Seolan\Core\Shell::setNextData('message', sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource', 'content_propagate'), ($lang_data_text = \Seolan\Core\Lang::get($lang_data)['text']), ($lang_prop_text = \Seolan\Core\Lang::get($langsrc)['text']), $lang_data_text, $lang_prop_text));
	  $context->next = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(false, false, false).'&oid='.$p->get('oid').'&skip=1&moid='.$this->_moid.'&tplentry=br&function=display&template=Module/Table.view.html';
	  return false;
	}
      }
      break;
    case 'display':
      break;
    }
    return true;
  }
  function subscribe($ar = array()) {
    // TODO: J. Maurel (sur projet refuges ffcam) avait placé un hook sur subscribe
    // pour les sous-sites. Nommer un nouveau event ?
    $ar = $this->triggerCallbacks(self::EVENT_PRE_CRUD, $ar);
    return parent::subscribe($ar);
  }

  /**
   * génération de la documentaion, selon les droits d'un utilisateur
   */
  function getDocumentationData(){
    $doc=$this->xset->getDocumentationData($this->getFieldsSec([]));
    $doc['more']='';

    if(\Seolan\Core\Json::hasInterfaceConfig() && ($alias=\Seolan\Core\Json::getModuleAlias($this->_moid))) {
      $doc['more'].='L\'alias pour ce module est : '.$alias.". Obtenir les détails d\'un objet de ce module : \n".
	"```\nGET /".$alias."/53x1ml04oit6?sessionid=v94o7fvn5tc9g5hshb95m43vb2\n```\n";
    }
    $doc['template']='Module/Table.documentation.md';
    return $doc;
  }
  ///
  public function procErasePrefs($ar = null) {
    $this->_clearSession('selectedfields_and_order');
    $this->_clearSession('browseproperties');
    return parent::procErasePrefs($ar);
  }

  /// mémorise les préférences utilisateur issues du browse/query
  /// =>  reprise avec un terme plus 'générique' de saveSelected...
  protected function saveBrowseUserPrefs($props){
    foreach(static::getBrowsePropertiesNames() as list($name,$prefname)){
      if (isset($props[$name]))
	$this->setPref(['prop' => $prefname??$name,
			'propv' => $props[$name]
	]);
    }
  }
  /// ? préférer saveBrowseUserPrefs
  function saveSelectedfieldsAndOrder($selectedFields, $order, $selectedqqfields, $quick_query_open, $quick_query_submodsearch, $pagesize) {
    \Seolan\Core\Logs::critical(__METHOD__,"deprecated ???? ");
    $selectedfields_and_order = [
				 'selectedfields' => $selectedFields,
				 'order' => $order,
				 'selectedqqfields' => $selectedqqfields,
				 'quick_query_open' => $quick_query_open,
				 'quick_query_submodsearch' => $quick_query_submodsearch,
				 'pagesize' => $pagesize,
				 ];
    $this->_setSession("selectedfields_and_order", $selectedfields_and_order);
    $this->saveBrowseUserPrefs([$selectedfields_and_order]);
  }
  /// récupération des options du browse
  protected function getBrowseUserPrefs(){
    $prefs = [];
    foreach(static::getBrowsePropertiesNames() as list($name,$prefname)){
      $prefs[$name] = $this->getPref($prefname??$name);
    }
    return $prefs;
  }
  /// recherche des paramètre dans la session, lorsque qu'on est admin
  function getSelectedfieldsAndOrder() {
    $selectedfields_and_order = $this->_getSession("selectedfields_and_order");
    if(!$selectedfields_and_order) {
      $selectedfields_and_order = [
				   'selectedfields' => $this->getPrefs(array('prop' => 'selectedfields')),
				   'order' => $this->getPrefs(array('prop' => 'order')),
				   'selectedqqfields' => $this->getPrefs(array('prop' => 'selectedqqfields')),
				   'quick_query_open' => $this->getPrefs(array('prop' => 'quick_query_open')),
				   'quick_query_submodsearch' => $this->getPrefs(array('prop' => 'quick_query_submodsearch')),
				   'pagesize' => (int)$this->getPrefs(array('prop' => 'pagesize')),
				   ];
      return $selectedfields_and_order;
    }
    return false;
  }

  /**
   * trashcan
   * @todo : query, pagination
   */
  public function browseTrash($ar=null){
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>'trash']);
    $ar['tplentry'] = TZR_RETURN_DATA;
    $ar['_filter'] = $this->getFilter(true,$ar);

    $browse = $this->xset->browseTrash($ar);

    $res = parent::browseTrash($ar);

    // ajout des actions
    if ($p->get('tplentry') != TZR_RETURN_DATA){
      $this->setTrashActions($browse);
      $res['browse'] = $browse;
      return \Seolan\Core\Shell::toScreen1($p->get('tplentry'), $res);
    } else {
      return $browse;
    }

  }
  /**
   * restauration d'un objet de la corbeille
   */
  function moveFromTrash($ar=null){
    $p = new \Seolan\Core\Param($ar, ['moid'=>$this->moid, 'user'=>\Seolan\Core\User::get_current_user_uid()]);
    $oid = $p->get('oid');
    $archive = $p->get('_archive');
    list($ok, $tlink) = $this->xset->restoreArchive($oid, $archive, $p->get('moid'), $p->get('user'));
    if ($ok){
      \Seolan\Core\Shell::setNextData('message', sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table', 'restoreok'), $tlink));
    }
  }
  /**
   * vide la corbeille
   */
  public function emptyTrashAllBatch(\Seolan\Module\Scheduler\Scheduler $scheduler, $o, $more){
    $m = $this->xset->delArchiveAll($this->getFilter(), $more['delAllArchives']??false);
    $scheduler->setStatusJob($o->KOID, 'finished', $m);

  }
  protected function createEmptyTrashAllTask(\Seolan\Module\Scheduler\Scheduler $scheduler, $more=null){
    list($res, $now) = parent::createEmptyTrashAllTask($scheduler, $more);
    $nb = getDB()->count($this->xset->browseTrashSelect($this->getFilter()), [], true);
    if ($nb<=500)
      $now = true;
    return [$res['oid'], $now];
  }
  /**
   * vide un item de la corbeille
   */
  public function emptyTrash($ar=null){
    $p = new \Seolan\Core\Param($ar, []);
    $oid = $p->get('oid');
    $archive = $p->get('_archive');
    // todo del all
    if (empty($archive) || empty($oid)){
      return;
    }
    $this->xset->delArchive($oid, $archive);
  }
  protected function getViewArchiveActionHelper(){
    return new Class('viewArchive', $this, 'Seolan_Core_General view','display','class="cv8-ajaxlink cv8-dispaction"') extends \Seolan\Core\Module\BrowseActionHelper{
      function browseActionUrl($usersel, $linecontext=null){
	$dtversion = $linecontext['browse']['lines_oUPD'][$linecontext['index']]->raw;
	return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->module->_moid.'&_trash=1&_archive='.$dtversion.'&oid=<oid>&tplentry=br&_skip=1&function=display&template=Core/Module.view-archive.html';
      }
    };
  }
  protected function getRestoreArchiveActionHelper(){
    return new Class('restoreArchive', $this, 'Seolan_Core_General restore_from_trash','display','class="cv8-ajaxlink cv8-dispaction"') extends \Seolan\Core\Module\BrowseActionHelper{
      function browseActionUrl($usersel, $linecontext=null){
	$dtversion = $linecontext['browse']['lines_oUPD'][$linecontext['index']]->raw;
	$url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self();
	$next= $this->module->getMainAction();
	return $url.'&moid='.$this->module->_moid.'&_archive='.urlencode($dtversion).'&oid=<oid>&tplentry=br&function=moveFromTrash&template=Core.empty.html&_skip=1&_next='.urlencode($next);
      }
    };
  }
  protected function getDelArchiveActionHelper(){
    return new Class('delArchive', $this, 'Seolan_Core_General delete','insert','class="cv8-ajaxlink cv8-dispaction"') extends \Seolan\Core\Module\BrowseActionHelper{
      function browseActionUrl($usersel, $linecontext=null){
	$dtversion = $linecontext['browse']['lines_oUPD'][$linecontext['index']]->raw;
	$url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self();
	$next= $this->module->getMainAction();
	return $url.'&moid='.$this->module->_moid.'&_archive='.urlencode($dtversion).'&oid=<oid>&tplentry=br&function=emptyTrash&template=Core.empty.html&_skip=1&_next='.urlencode($next);
      }
      function browseActionHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
	return 'class="cv8-dispaction" '.
	  ' onclick="TZR.Archives.delArchiveConfirm(\''.$url.'\');'.
	  ' return false;"';
      }
    };
  }

  /// check RGPD du module
  public function RGPDCheck(&$report) {
    parent::RGPDCheck($report);

    $fields=$this->xset->getFieldsList();
    foreach($fields as $fieldname) {
      $field=$this->xset->getField($fieldname);
      $field->RGPDCheck($report);
    }
  }

  public function hasCompulsoryFilter(){
    return !empty($this->query_comp_field) && $this->xset->fieldExists($this->query_comp_field);
  }
}

/// Verifie la validité d'un captcha
function xmodtable_captcha(){
  $value=$_REQUEST['value'];
  $id=$_REQUEST['id'];
  if(!preg_match('/^[a-z0-9A-Z]+$/', $value)) die('0');
  if(!preg_match('/^[a-z0-9A-Z]+$/', $id)) die('0');
  $cnt=getDB()->count('SELECT COUNT(*) from _VARS where name= ? and value= ? ',
		      array('CAPTCHA_'.$id, md5($value)));
  if($cnt) die('1');
  else die('0');
}
