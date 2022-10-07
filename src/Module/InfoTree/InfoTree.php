<?php
namespace Seolan\Module\InfoTree;
use \Seolan\Core\Logs;

/****c* \Seolan\Core\Module\Module/\Seolan\Module\InfoTree\InfoTree
 * NAME
 *   \Seolan\Module\InfoTree\InfoTree -- gestion d'un ensemble de rubriques structurées
 * DESCRIPTION
 *   Module central de gestion d'un site internet, intégrant la
 *   gestion de rubriques structurées, le rattachement d'informations
 *   à ces rubriques, ainsi que la création de requêtes.
 * SYNOPSIS
 ****/
class InfoTree extends \Seolan\Core\Module\ModuleWithSourceManagement {
  static public $upgrades = ['20220401_sitesearch'=>''];
  public static $pageSearchIndexationTemplate = 'Module/InfoTree.indexationTemplate.html';
  public $table = 'T001';
  protected $tname = NULL;
  protected $zonetable = NULL;
  public $dyntable = NULL;
  public $_rbrowse=NULL;
  public $cattemplate=NULL;
  public $preview=TZR_SHARE_SCRIPTS_FO.'index.php?_cachepolicy=forcecache';
  public $linkin='';
  public $searchtemplate='Module/InfoTree.searchResult.html';

  /** @var bool $section_sec : gestion des droits sur les sections */
  public $section_sec = false;

  /// construction de la classe gestion de rubriques
  function __construct($ar=NULL) {
    parent::__construct($ar);

    \Seolan\Core\Labels::loadLabels('Seolan_Module_InfoTree_InfoTree');
    $this->xset=$this->_categories=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    $this->boid = $this->xset->getBoid(); /* être compatible xmodtable pour \Seolan\Core\Module\ModuleWithSourceManagement, xtset */
    $this->tname='IT'.$this->table;
    $this->zonetable='ZONE'.$this->table;
    if (!empty($this->linkin) && !$this->_categories->fieldExists($this->linkin)){
      $this->linkin = null;
    }
    if(empty($this->_templates))
      $this->_templates=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
    \Seolan\Core\Alias::register($this);
    if (isset($this->linkin) && !$this->xset->fieldExists($this->linkin)){
      $this->linkin = null;
    }
    if ( TZR_USE_APP ){
      $this->updatePreviewDomain();
    }
  }

  /**
   * Modification de $this->preview dans le cas des APP
   */
  protected function updatePreviewDomain(){
    $app = getDB()->fetchRow('SELECT domain, domain_is_regex FROM APP WHERE JSON_EXTRACT(params,"$.infotree")=? limit 1',array($this->_moid));
    if ( !empty($app) ){
      if( $app['domain_is_regex'] == "1" ){
        return;
      }
      $this->_options->delOpt('preview');
      $this->preview = "https://".$app['domain'].'/index.php?';
    }
  }

  /// sections fonctions
  function getUIFunctionList() {
    $funcs = [];
    if ($this->insearchengine)
      $funcs['siteSearch'] = 'Recherche sur le site';
    return $funcs;
  }
  /**
   * recherche solr sur les pages du site
   * mode "widget" : sans paramètre resultalias
   */
  public function &UIParam_siteSearch($ar=null){

    $myLabel = function($name, $module='Seolan_Module_InfoTree'){
      if (is_array($name)){
	list($name, $module) = $name;
	if (empty($module))
	  $module = 'Seolan_Module_InfoTree';
      }
      return \Seolan\Core\Labels::getTextSysLabel($module, $name);
    };

    $myFieldFactory = function($name,
			       $label,
			       $ftype='\Seolan\Field\Label\Label',
			       $compulsory=1,
			       $fcount=64,
			       $translatable=1){

      if (is_array($label))
	list($lmodule, $lname) = $label;
      if(empty($lmodule))
	$lmodule = 'Seolan_Module_InfoTree';
      $flabel =  \Seolan\Core\Labels::getTextSysLabel($lmodule, $lname);

      return \Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>$name,
							      'FTYPE'=>$ftype,
							      'COMPULSORY'=>$compulsory,
							      'FCOUNT'=>$fcount,
							      'TRANSLATABLE'=>$translatable,
							      'LABEL'=>$flabel]);
    };

    $fn = 'golabel';
    $ret["__$fn"] = $myFieldFactory("__$fn", 'sitesearch_validate_button');
    $fn = 'termsplaceholder';
    $ret["__$fn"] = $myFieldFactory("__$fn", 'sitesearch_termsplaceholder');
    $fn = 'resultstitle';
    $ret["__$fn"] = $myFieldFactory("__$fn", 'sitesearch_resultstitle');
    $fn = 'pagecountlabel';
    $ret["__$fn"] = $myFieldFactory("__$fn", 'sitesearch_pagecountlabel');
    $fn = 'resultalias';
    $ret["__$fn"] = $myFieldFactory("__$fn", 'sitesearch_resultalias', '\Seolan\Field\ShortText\ShortText', 0);
    $fn = 'pagesize';
    $ret["__$fn"] = $myFieldFactory("__$fn", 'sitesearch_pagesize', '\Seolan\Field\ShortText\ShortText', 0);


    return $ret;

  }
  /**
   * recherche solr sur les pages du site
   * recherche sur tous les modules
   * filtrage des résultats par recherche de page correspondante
   * 2 modes :
   * sans paramètre resultalias : pleine page + chargement des résultat en ajax
   * avec paramèrte : widget rechercher et affichage résulat sur alias spécifié
   */
  public function siteSearch($ar=null){
    $ar = array_merge($_REQUEST, $ar); // soumission par appel resultalias
    $p = new \Seolan\Core\Param($ar, ['terms'=>null,'first'=>0,'pagesize'=>50]);
    $first = filter_var(trim($p->get('first')), FILTER_SANITIZE_STRING);
    $pagesize = $ar['pagesize'];
    $terms = filter_var(trim($p->get('terms')), FILTER_SANITIZE_STRING);

    if (empty($terms)){
      Logs::debug(__METHOD__." empty term");
      return \Seolan\Core\Shell::toScreen1('siteSearch', $res);
    }

    $res= ['terms'=>$terms, 'pages'=>[]];
    $moids = [];
    $modToids = getDB()->select('select moid, toid from MODULES')->fetchAll(\PDO::FETCH_KEY_PAIR);

    // liste des modules accessibles en recherche
    // faudrait complter (voir l'indexation : si application getUsedModules de la boostrap application)
    $modlist = \Seolan\Core\Module\Module::modlist();
    foreach ($modlist['lines_insearchengine'] as $i => $insearchengine) {
      if (!$insearchengine) continue;
      $mod = \Seolan\Core\Module\Module::objectFactory($modlist['lines_oid'][$i]);
      if (is_null($mod) || !$mod->secure('', ':list')) continue;
      $moids[]=$mod->_moid;
    }
    // pour le moment, ça suppose que ce module est accessible
    if (empty($moids)){
      Logs::notice(__METHOD__," no accessible module for public site search");
      return $res;
    }


    try{

      $search = \Seolan\Library\SolR\Search::objectFactory();

      $lang = \Seolan\Core\Shell::getLangData();
      $filterQueries = [
          	"id:{$lang}*",
          	"moid:{$this->_moid}" // faudra compléter avec les modules accessibles
      ];
      $queryFieldsAndBoost = 'title notice contents';

      $hits = $search->getSearchResponse($terms,
					 ['sort'=>['score'=>'desc'],
					  'fq'=>$filterQueries,
					  'qf'=>$queryFieldsAndBoost,
					  'qt'=>'full',
					  'debug'=>true,
					  'pages'=>[
					    'start'=>0,
					    'rows'=>50, //$pagesize
					  ]
					 ], // le texte de la page est dans contents
					 0,
					 TZR_XSEARCH_MAXRESULTS,
					 true);

      Logs::debug(__METHOD__.tzr_var_dump($hits->getDebug()));

      $highlighting = $hits->getHighlighting();
      $minscore = $maxscore = $hits->getMaxscore();
      foreach($hits as $hit){
	$minscore = min($hit->score, $minscore);
      }
      $deltascore = $maxscore-$minscore;
      foreach($hits as $hit){
	       $rrate = ceil(4 * (($hit->score-$minscore)/$deltascore))+1;
         list($dlang, $doid, $dmoid) = explode('|', $hit->id);
         if ($modToids[$dmoid] == XMODINFOTREE_TOID){
           // recherche des infos de la page + check publication
           // est-ce que publish n'est pas obligatoire ?
           $alias = getDB()->fetchOne("select alias from {$this->table} where koid=? and lang=? and publish=1", [$doid, $dlang]);
           if ($alias && $this->secure($doid,':ro')){
             $res['pages'][] = ['title'=>$hit->title,
        			'alias'=>$alias,
        			'oid'=>$doid,
        			'score'=>$rrate,
        			'rawscore'=>$hit->score,
        			'highlights'=>$highlighting->getResult($hit->id)
             ];
	     Logs::debug(__METHOD__." page {$alias} found");
           }
         } elseif($modToids[$dmoid] == XMODTABLE_TOID) {
	   // recherche d'une liste, d'une section fonction détail correpondant à ce module/oid
         }
      }
      $found = count($res['pages']);
      Logs::debug(__METHOD__." search '{$terms}' : found {$found} pages");
    }catch(\Throwable $t){
      Logs::critical(__METHOD__,$t->getMessage());
      Logs::critical(__METHOD__,$t->getTraceAsString());
    }
    return \Seolan\Core\Shell::toScreen1('siteSearch', $res);
  }
  /// mise en forme BO des résultats
  public function getSearchResult($doid, $advfilter){
    $path = $this->getPath($doid);
    
    return ['path'=>implode(' > ', $path['labeldown'])];
  }
  /// liste des champs indexés (ou message explicatif ?)
  protected function getInSearchengineFields():?string{
    $list = array_reduce($this->_categories->getIndexablesFields(), function($list, $fn){
	echo($fn);
	$list[] = $this->_categories->getField($fn)->label;
	return $list;
      },[]);
    return "Les champs indexés de la table ({$this->_categories->getLabel()}) : <br>".implode(', ',$list);
  }
  /// initialisation des options
  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','table'), 'table', 'table',array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','dyn_table_name'), 'dyntable', 'table',array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','cattemplate'), 'cattemplate', 'template',
			    array('moid'=>$this->_moid,
				  'cond'=>"(gtype like 'categorie')"));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','preview','text'), 'preview', 'text',NULL,'index.php?');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','linkinfield','text'), 'linkin', 'field',
			    array('compulsory'=>false),'linkin');

    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','odt_texttpl','text'), 'odttpltxt', 'template',
			    array('compulsory'=>false),NULL,'ODT');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','odt_texttitlefield','text'), 'odttxttitlefield', 'text', NULL, 'title','ODT');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','odt_textfield','text'), 'odttxtfield', 'text', NULL, 'txt1','ODT');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','odt_imagetpl','text'), 'odttplimg', 'template',
			    array('compulsory'=>false),NULL,'ODT');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','odt_imagefield','text'), 'odtimgfield', 'text', NULL,'img1','ODT');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','pdf_enabled','text'), 'pdf_enabled', 'boolean', NULL,false,'PDF');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','pdf_css','text'), 'pdf_css', 'text', NULL,'css/pdf.css','PDF');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','pdf_css_content','text'), 'pdf_css_content', 'text', NULL,'css/page-content.css','PDF');
    $tlabel=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','tracking');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Field_Field','trackchanges'),'trackchanges','boolean',NULL,NULL,$tlabel);
    // Ajout d'une option pour gérer les droits sur les sections
    $slabel=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','security','text');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_sec'),'section_sec','boolean',NULL,NULL,$slabel);
  }

  function __get($name){
    if($name=='_dyn'){
      $this->$name=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$this->dyntable);
    }elseif($name=='_zone'){
      $this->$name=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$this->zonetable);
    }
    return $this->$name;
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array('home'=>array('list','ro','rw','rwv','admin'),
	     'procHome'=>array('rw','rwv','admin'),
	     'rss'=>array('ro','rw','rwv','admin'),
	     'viewpage'=>array('ro','rw','rwv','admin'),
	     'viewsection'=>array('ro','rw','rwv','admin'),
	     'procQuery'=>array('ro','rw','rwv','admin'),
	     'query'=>array('ro','rw','rwv','admin'),
	     'browse'=>array('ro','rw','rwv','admin'),
	     'input'=>array('rw','rwv','admin'),
	     'procInput'=>array('rw','rwv','admin'),
	     'edit'=>array('rw','rwv','admin'),
	     'procEdit'=>array('rw','rwv','admin'),
	     'addSection'=>array('rw','rwv','admin'),
	     'newsection'=>array('rw','rwv','admin'),
	     'insertsection'=>array('rw','rwv','admin'),
	     'editsection'=>array('rw','rwv','admin'),
	     'savesection'=>array('rw','rwv','admin'),
	     'newfunction'=>array('rw','rwv','admin'),
	     'insertfunction'=>array('rw','rwv','admin'),
	     'editfunction'=>array('rw','rwv','admin'),
	     'savefunction'=>array('rw','rwv','admin'),
	     'insertquery'=>array('rw','rwv','admin'),
	     'editquery'=>array('rw','rwv','admin'),
	     'savequery'=>array('rw','rwv','admin'),
	     'delSection'=>array('rw','rwv','admin'),
	     'editpage'=>array('rw','rwv','admin'),
	     'delCat'=>array('rw','rwv','admin'),
	     'dupCat'=>array('rw','rwv','admin'),
	     'procEdit'=>array('rw','rwv','admin'),
	     'procEditAllLang'=>array('rw','rwv','admin'),
	     'editCat'=>array('rw','rwv','admin'),
	     'moveCat'=>array('rw','rwv','admin'),
	     'moveSection'=>array('rw','rwv','admin'),
	     'moveSelectedCat'=>array('rw','rwv','admin'),
	     'moveToTrash'=>array('rw','rwv','admin'),
	     'displayImage'=>array('rw','rwv','admin'),
	     'displaysection'=>array('ro','rw','rwv','admin'),
	     'dupsection'=>array('rw','rwv','admin'),
	     'publishCat'=>array('rwv','admin'),
	     'subscribe'=>array('ro','rw','rwv','admin'),
	     'preSubscribe'=>array('ro','rw','rwv','admin'),
	     'export'=>array('ro','rw','rwv','admin'),
	     'exportPdf'=>array('ro','rw','rwv','admin'),
	     'exportPdfs'=>array('ro','rw','rwv','admin'),
	     'publish'=>array('rwv','admin'),
	     'preImportFromODT'=>array('rw','rwv','admin'),
	     'importFromODT'=>array('rw','rwv','admin'),
             'convertToMultiZone'=>array('admin'),
             'viewzone'=>array('ro','rw','rwv','admin'),
             'procEditZoneConfig'=>array('rw','rwv','admin'),
             'pagesUsingModel'=>array('ro','rw','rwv','admin'),
	     'mergeDefaultTemplates'=>array('admin'),
	     'secEditSection' => array('admin'),
	     'previewToken'=>array('list','ro','rw','rwv','admin'),
	     'siteSearch'=>['list','ro','rw','rwv','admin']
    );
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      else return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=home&tplentry=mit&template=Module/InfoTree.index.html';
  }
  public function previewToken($ar=null){
    $p = new \Seolan\Core\Param($ar,[]);
    $itoid = $p->get('itoid');
    $tokenid = \Seolan\Core\Token::factory()->create('preview', false, 60, $this->_moid, ['itoid'=>$itoid]);
    die(json_encode(['ok'=>true,
		     'previewUrl'=>makeUrl($this->preview,
					   ['_lang'=>\Seolan\Core\Shell::getLangData(),
					    'oidit'=>$itoid,
					    'nocache'=>1,
					    '_'=>microtime(),
					    TZR_AUTHTOKEN_NAME=>$tokenid])]));
  }
  /// list des tables dont on souhaite tracer les modifications
  function tablesToTrack() {
    $tabs=array();
    if($this->trackchanges) {
      $tabs=getDB()->fetchCol('SELECT DISTINCT tab FROM TEMPLATES WHERE modid=?',array($this->_moid));
      $tabs[]=$this->table;
    }
    return $tabs;
  }

  /// Prepare l'ajout d'une nouvelle section function
  public function newfunction($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $oidtpl=$p->get('oidtpl');
    $lang=\Seolan\Core\Shell::getLangData();
    $ors=getDB()->fetchRow('select * from TEMPLATES where KOID=? AND LANG=?',array($oidtpl,$lang));
    if($ors) {
      // Infos d'affichage du template
      $template = $this->_disp2Template($oidtpl);
      $s['_tp'] = $template;

      $s['section']=$p->get('section');
      $s['position']=$p->get('position');
      $s['oidit']=$p->get('oidit');
      $s['oidtpl']=$p->get('oidtpl');
      $s['zone']=$p->get('zone');
      $moid=$s['section']['moid'];
      $f=$s['section']['function'];
      $pf='UIParam_'.$f;
      $ef='UIEdit_'.$f;
      $params=['itmoid'=>$this->_moid];
      $m=\Seolan\Core\Module\Module::objectFactory($moid);
      if(method_exists($m,$ef)) $ret=$m->$ef($params);
      else{
	$desc=$m->$pf($params);
        $ret=self::editUIParam($desc,$params);
      }
      $this->editTemplateOptions($ret, $oidtpl, $s['oidit']);
      $groups=array();
      foreach($ret as $n=>&$v) {
	if(!is_object($v) || !is_object($v->fielddef)) continue;
	if(empty($v->fielddef->fgroup)) $v->fielddef->fgroup='General';
	$groups[$v->fielddef->fgroup][]=&$v;
      }
      ksort($groups);
      if(count($groups)>1) $ret['_groups']=$groups;
      $s['olines'][]=$ret;
      \Seolan\Core\Shell::toScreen1($tplentry,$s);
    }
  }

  /// Enregistre une section de type fonction
  public function insertfunction($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $oidit=$p->get('oidit');
    $oidtpl=$p->get('oidtpl');
    $position=$p->get('position')+1;
    $section=$p->get('section');
    $zone=$p->get('zone');
    $moid=$section['moid'];
    $ors=getDB()->fetchRow('select * from TEMPLATES where KOID=?',array($oidtpl));
    if($ors) {
      $f=$section['function'];
      $pf='UIParam_'.$f;
      $ef='UIProcEdit_'.$f;
      $ar['itmoid']=$this->_moid;
      $m=\Seolan\Core\Module\Module::objectFactory($moid);
      if(method_exists($m,$ef)){
        $ret=$m->$ef($ar);
      }else{
        $desc=$m->$pf($ar);
        $ret=self::postEditUIParam($desc,$ar);
      }
      if($ret) $section=array_merge($section,$ret);
      $section['_tploptions'] = $p->get('_tploptions');

      $ret=$this->_dyn->procInput(array(
        '_local'=>true,
        'module'=>$moid,
        'query'=>$section,
        'tpl'=>$oidtpl,
        'tplentry'=>TZR_RETURN_DATA
      ));

      // Efface query dans les langues autre que celle par défaut car le query de ces langues ne doit contenir que les infos de la langue en question
      foreach($GLOBALS['TZR_LANGUAGES'] as $lang=>&$foo){
        if($lang!=TZR_DEFAULT_LANG) getDB()->execute('update '.$this->dyntable.' set query="" where KOID=? and LANG=?',array($ret['oid'],$lang));
      }

      $nitoid=$this->insertSectionInIt(array('oidit'=>$oidit,'oidsection'=>$ret['oid'],'oidtpl'=>$oidtpl,'position'=>$position,'zone'=>$zone));
      return array('oid'=>$ret['oid'],'itoid'=>$nitoid);
    }
  }

  /// Prépare l'edition d'une section fonction
  function editfunction($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $oidsection=$p->get('oidsection');
    list($oidit,$oiddest,$oidtemplate)=$this->_getOids($oidsection);

    $d=$this->getFullFunctionDetails($oiddest);
    foreach ( $d['_fullquery']['_FIELDS'] as $k => $field ){
      if( !empty($d['_fullquery'][$field])){
        $tmp = array_filter($d['_fullquery'][$field], function($v) { return $v == 'on'; });
        if( count($tmp) > 0 ){
          $d['_fullquery'][$field] = array_keys($tmp);
        }
      }
    }
    $params=&$d['_fullquery'];
    // casse l'autocomplétion si mis mais casse uiparam_query si pas mis ...
    $params['itmoid']=$this->_moid;
    $s=array();
    $template = $this->_disp2Template($oidtemplate);
    $s['_tp'] = $template;
    $s['oidit']=$oidit;
    $s['oidsection']=$oidsection;
    $s['zone']=$zone;
    $s['section']=$params;
    $moid=$params['moid'];
    $f=$params['function'];
    $pf='UIParam_'.$f;
    $ef='UIEdit_'.$f;
    $m=\Seolan\Core\Module\Module::objectFactory($moid);
    if(method_exists($m,$ef)) {
      $ret=$m->$ef($params);
    } else {
      $desc=$m->$pf($params);
      $ret=self::editUIParam($desc,$params);
    }
    $this->editTemplateOptions($ret, $oidtemplate, $s['oidsection'], $params);
    $groups=array();
    foreach($ret as $n=>&$v) {
      if(!is_object($v) || !is_object($v->fielddef)) continue;
      if(empty($v->fielddef->fgroup)) $v->fielddef->fgroup='General';
      $groups[$v->fielddef->fgroup][] =&$v;
    }
    ksort($groups);
    if(count($groups)>1) $ret['_groups']=$groups;
    $s['olines'][]=$ret;
    return \Seolan\Core\Shell::toScreen1($tplentry,$s);
  }

  /**
   * Ajoute les options du template à l'interface d'édition de la section fonction
   * @param &$ret array Tableau de valeurs disponible dans le template
   * @param $oid_template string KOID du template
   * @param $id_section string Identifiant unique de la section
   * @param $params array Tableau de valeurs des options du template
   */
  public function editTemplateOptions(&$ret, $oid_template, $id_section, $params = null) {
    // Récupère les options liées au TEMPLATE de mise en page
    $options_values = (object) null;
    $xoptions = $this->getTemplateOptions($oid_template, $id_section, $options_values);
    if (!is_null($xoptions)) {
      $template_options = $xoptions->getValues($options_values);
      foreach ($template_options as $option_id => $option) {
	$xoptions->set($options_values, $option_id, $params['_tploptions'][$option_id]);
      }
      $user_module_access_levels = current($GLOBALS['XUSER']->getObjectAccess($this, \Seolan\Core\Shell::getLangData()));
      $level = in_array('admin', $user_module_access_levels) ? 'admin' : 'none';
      $ret['_tploptions'] = $xoptions->getDialog($options_values, $params['_tploptions'], '_tploptions', $level);
    }
  }

  /**
   * Envoi un tableau des options disponibles pour le template
   * @param $oid_template string KOID du template
   * @param $id_section string Identifiant unique de la section
   * @param $options_values array Tableau de valeurs des options du template
   * @return \Seolan\Core\Options Objet contenant les options du template / null si inexistant
   */
  public function getTemplateOptions($oid_template, $id_section, &$options_values) {
    $template = $this->_disp2Template($oid_template);
    if (empty($template['oopts']->raw)) {
      return null;
    }
    $xopts = new \Seolan\Core\Options($id_section);
    $xopts->setOptsFromXMLOrJSON($options_values, $template['oopts']->raw);
    return $xopts;
  }

  /// Enregistre une section de type fonction
  public function savefunction($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oidit=$p->get('oidit');
    $oidtpl=$p->get('oidtpl');
    $oidsection=$p->get('oidsection');
    $section=$p->get('section');
    $moid=$section['moid'];
    $f=$section['function'];
    $pf='UIParam_'.$f;
    $ef='UIProcEdit_'.$f;
    $ar['itmoid']=$this->_moid;
    $m=\Seolan\Core\Module\Module::objectFactory($moid);
    if(method_exists($m,$ef)){
      $ret=$m->$ef($ar);
    }else{
      $desc=$m->$pf($ar);
      $ret=self::postEditUIParam($desc,$ar);
    }
    if($ret) $section=array_merge($section,$ret);
    $section['_tploptions'] = $p->get('_tploptions');

    $langs=$p->get('_langs');
    $langs=$this->getAuthorizedLangs($langs,$oidit,'savefunction');

    list($oidit,$oiddest,$oidtemplate)=$this->_getOids($oidsection);
    $this->_dyn->procEdit(array(
      '_local'=>true,
      'oid'=>$oiddest,
      'module'=>$moid,
      'query'=>$section,
      'tpl'=>$oidtpl,
      '_langs'=>$langs,
      'tplentry'=>TZR_RETURN_DATA
    ));

    if(!($this->getTranslatable() && $this->_categories->getAutoTranslate())) {
      // Efface query dans les langues autre que celle par défaut car le query de ces langues ne doit contenir que les infos de la langue en question
      getDB()->execute('update '.$this->dyntable.' set query="" where KOID=? and LANG!=?',array($oiddest,TZR_DEFAULT_LANG));
    }
    return $oidsection;
  }

  /// Liste des actions générale du module
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my);
    $f=\Seolan\Core\Shell::_function();
    $myoid=@$_REQUEST['oidit'];
    $moid=$this->_moid;
    // Arbo
    $o1=new \Seolan\Core\Module\Action($this,'home',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','see_categories'),
			  '&moid='.$moid.'&_function=home&template=Module/InfoTree.index.html&tplentry=mit','display');
    $o1->setToolbar('Seolan_Core_General','browse');
    $my['home']=$o1;
    // Poubelle
    $o1=new \Seolan\Core\Module\Action($this,'trash',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','see_trash'),
			  '&moid='.$moid.'&_function=home&template=Module/InfoTree.index.html&tplentry=mit&aliastop=trash',static::$trashmenugroup);
    $o1->setToolbar('Seolan_Core_General','trash');
    $o1->order=static::$trashmenuorder;
    $my['trash']=$o1;
    // Recherche
    $o1=new \Seolan\Core\Module\Action($this,'home',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','query','text'),
			  '&moid='.$moid.'&_function=query&template=Module/InfoTree.query.html&tplentry=br','display');
    $o1->setToolbar('Seolan_Core_General','query');
    $my['query']=$o1;
    // Suppression module
    if(isset($my['delete'])){
      // Suppression module + tables + sections
      $o1=new \Seolan\Core\Module\Action($this,'deletewithtableandsections',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','deletewithtableandsections','text'),
                            '&moid='.$moid.'&_function=delete&template=Core.message.html&withtable=1&withsections=1');
      $o1->needsconfirm=true;
      array_insert_after($my,'deletewithtable','deletewithtableandsections',$o1);
    }
    // Passage en mode multizone
    if(!$this->isReadyForMultiZone() && $this->secure('','convertToMultiZone')){
      $o1=new \Seolan\Core\Module\Action($this,'multizone',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','converttomultizone','text'),
                            '&moid='.$this->_moid.'&function=convertToMultiZone&_next=refresh',
                            'more');
      $o1->menuable=true;
      $my['multizone']=$o1;
    }
    // Pile
    if($this->interactive)  {
      $o1=new \Seolan\Core\Module\Action($this,'in',$this->getLabel(),
			    '&moid='.$this->_moid.'&_function=home&template=Module/InfoTree.index.html&tplentry=mit');
      $my['stack'][]=$o1;
      if (!empty($myoid)) {
        $nav=$this->getPath($myoid);
        foreach($nav['oiddown'] as $i=>$oid) {
          $o1=new \Seolan\Core\Module\Action($this,'i'.$i,$nav['labeldown'][$i],
                                '&moid='.$this->_moid.'&_function=editpage&template=Module/InfoTree.viewpage.html&tplentry=it&oidit='.$oid);
          $my['stack'][]=$o1;
        }
      }
      if(strpos($f,'admin')===0 && $f!='adminNewSection'){
        $o1=new \Seolan\Core\Module\Action($this,'adminBrowseFields',\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','browsefields','text').' ('.$this->xset->getTable().')',
                              '&moid='.$moid.'&_function=adminBrowseFields&template=Core/Module.admin/browseFields.html','display');
        $my['stack'][]=$o1;
      }elseif(\Seolan\Core\Shell::_function()=='editCat'){
	$br=\Seolan\Core\Shell::from_screen('editcat');
	$br=$this->_categories->rDisplayText($br['oid'],[]);
	$o1=new \Seolan\Core\Module\Action($this,'view',$br['link'],'&moid='.$this->_moid.'&_function=editpage&template=Module/InfoTree.viewpage.html&tplentry=it&oidit='.$br['oid']);
	$my['stack'][]=$o1;

      }
      $modsubmoid=\Seolan\Core\Module\Module::getMoid(XMODSUB_TOID);
      if(!empty($modsubmoid)){
	$o1=new \Seolan\Core\Module\Action($this,'subscribe',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Subscription_Subscription','subadd','text'),
			      '&moid='.$this->_moid.'&oid='.$myoid.'&_function=preSubscribe&tplentry=br&template=Module/InfoTree.sub.html',
			      'more');
	$o1->menuable=true;
	$my['subscribe']=$o1;
      }
      if($this->odttpltxt && $this->odttplimg && $this->secure('','importFromODT')){
	$o1=new \Seolan\Core\Module\Action($this,'odtimport',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','odt_import','text'),
			      '&moid='.$this->_moid.'&function=preImportFromODT&template=Module/InfoTree.preImportFromODT.html&oidit='.$oid,'edit');
	$o1->menuable=true;
	$my['odtimport']=$o1;
      }
    }
  }
  /// Actions spécifique à "edition categorie"
  function al_editcat(&$my){
    $br = \Seolan\Core\Shell::from_screen('editcat');
    if ($this->secure($br['oid'], 'editpage')){
      $o2=new \Seolan\Core\Module\Action($this,'view','Page','&moid='.$this->_moid.'&_function=editpage&template=Module/InfoTree.viewpage.html&tplentry=it&oidit='.$br['oid']);
      $o2->setToolbar('Seolan_Core_General','display');
      $o2->menuable=true;
      $my['editpage']=$o2;
    }
  }
  /// Actions spécifique à "home"
  function al_home(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $o1=new \Seolan\Core\Module\Action($this,'foldall',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','fold_all'),
			  '&function=home&template=Module/InfoTree.index.html&moid='.$this->_moid.'&do=foldall&tplentry=mit','display');
    $o1->menuable=true;
    $my['foldall']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'unfoldall',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','unfold_all'),
			  '&function=home&template=Module/InfoTree.index.html&moid='.$this->_moid.'&do=unfoldall&tplentry=mit','display');
    $o1->menuable=true;
    $my['unfoldall']=$o1;
    if($this->secure('','input')){
      $o1=new \Seolan\Core\Module\Action($this,'input',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','add_subtree'),
			    '&moid='.$this->_moid.'&function=input&template=Module/InfoTree.new.html&tplentry=&linkup=','edit');
      $o1->setToolbar('Seolan_Core_General','new');
      $my['input']=$o1;
    }
    // Publier/dépublier
    if($this->_categories->fieldExists('PUBLISH') && $this->secure('',':rwv')){
      $o1=new \Seolan\Core\Module\Action($this,'publish',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','approve'),
			    'javascript:'.$uniqid.'.publishSelected(true);','edit');
      $o1->menuable=true;
      $my['publish']=$o1;
      $o1=new \Seolan\Core\Module\Action($this,'publish',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','unapprove'),
			    'javascript:'.$uniqid.'.publishSelected(false);','edit');
      $o1->menuable=true;
      $my['unpublish']=$o1;
    }
    // Supprimer
    if($this->secure('','moveToTrash')){
      $o1=new \Seolan\Core\Module\Action($this,'del',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete'),
			    'javascript:'.$uniqid.'.deleteselected();','edit');
      $o1->setToolbar('Seolan_Core_General','delete');
      $my['del']=$o1;
    }
    // Exporter en Pdf
    if($this->pdf_enabled && $this->secure($oid,'exportPdfs')) {
      $o1=new \Seolan\Core\Module\Action($this,'exportPdfs',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','export_pdf'),
				 'javascript:'.$uniqid.'.exportSelected();','edit');
      $o1->target='_blank';
      $o1->ico='<span class="glyphicon csico-file-pdf">';
      $o1->setToolbar();
      $my['exportPdfs']=$o1;
    }
    // Deplacer
    if($this->secure('','moveCat')){
      $o1=new \Seolan\Core\Module\Action($this,'move',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','move'),
					 'javascript:TZR.Infotree.moveselected.call(TZR.Infotree,'.$uniqid.'.uniqid);','edit');
      $o1->menuable=true;
      $my['move']=$o1;
    }
    // Dupliquer
    if($this->secure('','dupCat')){
      $o1=new \Seolan\Core\Module\Action($this,'dup',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','clone'),
			    'javascript:'.$uniqid.'.duplicateselected();','edit');
      $o1->menuable=true;
      $my['dup']=$o1;
    }
    // Exporter
    if($this->secure('','export')){
      $o1=new \Seolan\Core\Module\Action($this,'export',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','export'),
			    'javascript:'.$uniqid.'.exportselected();','edit');
      $o1->menuable=true;
      $my['exp']=$o1;
    }
    // Gabarits par defaut
    if (\Seolan\Core\Shell::isRoot()) {
      // Insertion dans la table des TEMPLATES de tous les templates de base
      $o1=new \Seolan\Core\Module\Action($this,'createDefaultTemplates',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','create_default_templates'),
        '&moid='.$this->_moid.'&_function=createDefaultTemplates&tplentry=br&template=Module/InfoTree.createDefaultTemplates.html',
        'more');
      $o1->menuable=true;
      $my['createDefaultTemplates']=$o1;
      $o1=new \Seolan\Core\Module\Action($this,'mergedefaulttemplates',"Gabarits standards",
			    '&moid='.$this->_moid.'&function=mergeDefaultTemplates&tplentry=br&template=Module/InfoTree.mergedefaulttemplates.html','more');
      $o1->menuable=true;
      $my['executesql']=$o1;
    }
  }
  /// Actions spécifique à "viewpage"
  function al_viewpage(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $oid=$_REQUEST['oidit'];
    // Navigation
    $o1=new \Seolan\Core\Module\Action($this,'nav',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','navigation'),'#');
    $o1->menuable=true;
    $o1->newgroup='nav';
    $my['nav']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'navall',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','treebrowser'),
			  'javascript:TZR.Dialog.openURL("'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'function=home&template=Module/InfoTree.popaction.html'.
			  '&_skip=1&moid='.$this->_moid.'&_raw=1&maxlevel=1&oid='.$oid.'&tplentry=mit&do=add&'.
			  '&norubric=1&action=go&moduleid='.\Seolan\Core\Shell::uniqid().'");','nav');
    $o1->menuable=true;
    $my['navall']=$o1;
    $it=\Seolan\Core\Shell::from_screen('it');
    $nav=&$it['nav'];
    if($nav && array_key_exists('up',$nav) && $nav['up']){
      $o1=new \Seolan\Core\Module\Action($this,'navup',$nav['up']['otitle']->html,'javascript:'.$uniqid.'.gopage("'.$nav['up']['oid'].'");','nav');
      $o1->menuable=true;
      $my['navup']=$o1;
    }
    if(!empty($nav['prev'])){
      $o1=new \Seolan\Core\Module\Action($this,'navprev',$nav['prev']['otitle']->html,'javascript:'.$uniqid.'.gopage("'.$nav['prev']['oid'].'");', 'nav');
      $o1->menuable=true;
      $my['navprev']=$o1;
    }
    if(!empty($nav['next'])){
      $o1=new \Seolan\Core\Module\Action($this,'navnext',$nav['next']['otitle']->html,'javascript:'.$uniqid.'.gopage("'.$nav['next']['oid'].'");', 'nav');
      $o1->menuable=true;
      $my['navnext']=$o1;
    }
    $rubs=\Seolan\Core\Shell::from_screen('ssrubs');
    foreach($rubs['lines_oid'] as $i=>$roid){
      $o1=new \Seolan\Core\Module\Action($this,'navssrub'.$i,$rubs['lines_otitle'][$i]->html,'javascript:'.$uniqid.'.gopage("'.$roid.'");','nav');
      $o1->menuable=true;
      $my['navssrub'.$i]=$o1;
    }

    // Prévisualisation
    if($this->preview){
      $o1=new \Seolan\Core\Module\Action($this,
					 'view',
					 \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','view'),
					 "javascript:TZR.Infotree.preview('{$this->_moid}', '{$oid}');",
					 'edit');
      $o1->target='_blank';
      $o1->setToolbar('Seolan_Core_General','view');
      $my['view']=$o1;
    }
    // Publier/dépulier
    if($this->secure($oid,':rwv')){
      $it=\Seolan\Core\Shell::from_screen('it');
      $publish=false;
      foreach($it['zones'] as &$z){
        foreach($z['olines'] as $i=>&$s){
          if(isset($s['oPUBLISH'])){
            $publish=true;
            break;
          }
        }
      }
      if($publish){
	$o1=new \Seolan\Core\Module\Action($this,'publish',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','approve'),
			      'javascript:'.$uniqid.'.publishSelected(true);','edit');
	$o1->menuable=true;
	$my['publish']=$o1;
	$o1=new \Seolan\Core\Module\Action($this,'publish',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','unapprove'),
			      'javascript:'.$uniqid.'.publishSelected(false);','edit');
	$o1->menuable=true;
	$my['unpublish']=$o1;
      }
    }
    // Supprimer
    if($this->secure($oid,'delSection')){
      $o1=new \Seolan\Core\Module\Action($this,'del',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete'),
			    'javascript:'.$uniqid.'.deleteselected();','edit');
      $o1->setToolbar('Seolan_Core_General','delete');
      $my['del']=$o1;
    }
    // Deplacer
    if($this->secure($oid,'moveSection')){
      $o1=new \Seolan\Core\Module\Action($this,'move',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','move'),
			    'javascript:'.$uniqid.'.moveselected(\'to\');','edit');
      $o1->menuable=true;
      $my['move']=$o1;
      $o1=new \Seolan\Core\Module\Action($this,'moveup',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','up'),
			    'javascript:'.$uniqid.'.moveselected(\'up\');','edit');
      $o1->menuable=true;
      $my['moveup']=$o1;
      $o1=new \Seolan\Core\Module\Action($this,'movedown',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','down'),
			    'javascript:'.$uniqid.'.moveselected(\'down\');','edit');
      $o1->menuable=true;
      $my['movedown']=$o1;
    }
    // Export PDF
    if($this->pdf_enabled && $this->secure($oid,'exportPdf')) {
      $o1=new \Seolan\Core\Module\Action($this,'exportPdf',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_InfoTree_InfoTree','export_pdf'),'&moid='.$this->_moid.'&_function=exportPdf&oidit='.$oid,'edit');
      $o1->target='_blank';
      $o1->ico='<span class="glyphicon csico-file-pdf">';
      $o1->setToolbar();
      $my['exportPdf']=$o1;
    }
  }
  /// Actions spécifique à "editpage"
  function al_editpage(&$my){
    $this->al_viewpage($my);
  }

  /// Refait la numérotation de l'ordre des sections d'une catégorie
  protected function _reorderSections($oid) {
    $zones=getDB()->select('select ZONE,ITOID,ORDER1 from '.$this->tname.' where KOIDSRC=? order by ZONE,ORDER1,ITOID',
                           array($oid))->fetchAll(\PDO::FETCH_GROUP);
    foreach($zones as $zone){
      $i=0;
      foreach($zone as $section){
        $i++;
        if($i==(int)$section['ORDER1']) continue;
        getDB()->execute('UPDATE '.$this->tname.' set ORDER1=? where ITOID=?',array($i,$section['ITOID']));
      }
    }
  }

  /// Refait la numérotation de l'ordre des catégories d'un noeud
  protected function _reorderCat($oid) {
    $lang=TZR_DEFAULT_LANG;
    if($oid) $linkupw='linkup=?';
    else{
      $oid='';
      $linkupw='(linkup=? or linkup is null)';
    }

    $i=0;
    $rs=getDB()->select('select distinct KOID,corder from '.$this->table.' where '.$linkupw.' and LANG=? order by corder',
			array($oid,$lang));
    while($ors=$rs->fetch()) {
      $i++;
      if($i==$ors['corder']) continue;
      getDB()->execute('UPDATE '.$this->table.' set corder=?,UPD=UPD where KOID=?',array($i,$ors['KOID']));
    }
  }

  /// Change le parent d'une ou plusieurs catégories
  public function moveSelectedCat($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $dest=$p->get('_dest');
    if(!empty($dest) && !\Seolan\Core\Kernel::isAKoid($dest)) {
      $dest=$this->getOidFromAlias($dest);
    }
    $sel=$p->get('_selected');

    $i=-count($sel);
    foreach($sel as $oid => $bid) {
      if($oid!=$dest) {
	getDB()->execute('UPDATE '.$this->table.' set linkup=?,corder=? where KOID=?',array($dest,$i,$oid));
      }
      $i++;
    }
    $this->_reorderCat($dest);
  }
  protected function oidInTrash($oid) {
    $oidtrash = $this->getTrashOid();
    if(empty($oidtrash)) return false;
    $pathoids=$this->_getPathOids($oid);
    return in_array($oidtrash, $pathoids);
  }

  /// teste si une rubrique est dans la poubelle
  /// rend l'oid de la poubelle
  public function getTrashOid($createit = false) {
    $trashOid = $this->table . ':TRASH';
    if ($createit) {
      $exist = getDB()->fetchOne("SELECT KOID FROM {$this->table} WHERE KOID=?",[$trashOid]);
      if (!$exist) {
        $this->procInput([
          'newoid' => $trashOid,
          'title' => \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','trash','text'),
          'alias' => 'trash',
          'corder' => '0',
          '_options' => ['local' => true]
        ]);
      }
    }
    return $trashOid;
  }

  /// deplacement de la selection vers la poubelle, rubrique d'alias trash
  public function moveToTrash($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $oidtrash = $this->getTrashOid(true);
    $sel=$p->get('_selected');

    // si les elements sont deja dans la poubelle on les supprime
    $delnb=0;

    foreach($sel as $oid => $bid) {
      if($this->oidInTrash($oid)) {
	$ar1=array();
	$ar1['oid']=$oid;
	$ar1['_selected']=array();
	$ar1['_selectedok']='nok';
	$this->delCat($ar1);
	$delnb++;
      }
    }
    // si on a supprime tous les elements selectionnes pas la peine de
    // rentrer dans un traitement de la poubelle
    if($delnb==count($sel)) return;

    // creation d'une rubrique liee a la date de suppression
    $ar1=array('title'=>'-- '.date('c'),
	       'linkup'=>$oidtrash,
	       'corder'=>'0');
    $r1=$this->procInput($ar1);
    $dest = $r1['oid'];

    foreach($sel as $oid => $bid) {
      if($oid!=$dest) {
	getDB()->execute('UPDATE '.$this->table." set linkup=? where KOID=?",
			 array($dest,$oid));
      }
    }

    // on parcourt la poubelle et on supprime dans la poubelle toutes les rubriques validees
    // ainsi que tous les alias
    $oids=$this->getSubObjects($oidtrash);
    foreach($oids as $oids1) {
      if($this->_categories->fieldExists('PUBLISH')) {
	getDB()->execute('UPDATE '.$this->table." set PUBLISH=? WHERE KOID=?",array('2',$oids1));
      }
      getDB()->execute('UPDATE '.$this->table." set alias=? WHERE KOID=?",array('',$oids1));
    }

    // on traite les liens internes. Ceux qui sont dans la poubelle sont supprimes
    // ceux qui vont vers la poubelle sont supprimes
    if (isset($this->xset->desc[$this->linkin])) {
      $rs=getDB()->select('SELECT KOID,LANG,'.$this->linkin.' FROM '.$this->table.' WHERE '.$this->linkin.'!=\'\'');
      while($rs && ($ors=$rs->fetch())) {
        if($this->oidInTrash($ors['KOID'])) {
          getDB()->execute('UPDATE '.$this->table.' SET '.$this->linkin."=?,UPD=UPD WHERE KOID=?",
                          array('', $ors['KOID']));
        } elseif($this->oidInTrash($ors[$this->linkin])) {
          getDB()->execute('UPDATE '.$this->table.' SET '.$this->linkin.'=?,UPD=UPD WHERE KOID=? AND '.$this->linkin.'=?',
                          array('',$ors['KOID'],$ors[$this->linkin]));
        }
      }
    }
  }

  /// Change l'ordre et/ou le parent d'une catégorie
  public function moveCat($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $oid=$p->get('oid');
    $dir=$p->get('dir');
    $to=$p->get('to');

    // recherche de la rubrique mere
    $poid=$new_p=getDB()->fetchOne('select distinct linkup from '.$this->table.' where KOID=?', array($oid));

    // on verifie et corrige l'ordre
    $this->_reorderCat($poid);
    if($to){
      $new_p=($to!='root'?$to:'');
      $this->_reorderCat($new_p);
    }

    // recherche de son ordre
    $o=getDB()->fetchOne('select distinct corder from '.$this->table.' where KOID=?', array($oid));
    if($dir=='up'){
      $new_o=$o-1;
      getDB()->execute('UPDATE '.$this->table.' set corder=?,UPD=UPD where corder=? and linkup=?',
		       array($o,$new_o,$poid));
    }elseif($dir=='down'){
      $new_o=$o+1;
      getDB()->execute('UPDATE '.$this->table.' set corder=?,UPD=UPD where corder=? and linkup=?',
		       array($o,$new_o,$poid));
    }elseif(\Seolan\Core\Kernel::isAKoid($dir)){
      $new_o=getDB()->fetchOne('select distinct corder from '.$this->table.' where KOID=?', array($dir))+1;
      getDB()->execute('UPDATE '.$this->table.' set corder=corder+1,UPD=UPD where corder>=? and linkup=?',
		       array($new_o,$new_p));
    }else{
      $new_o=0;
    }
    getDB()->execute('UPDATE '.$this->table.' set corder=?,linkup=? where KOID=?',
		     array($new_o,$new_p,$oid));

    $this->_reorderCat($poid);
    if($new_p!=$poid) $this->_reorderCat($new_p);
  }

  /// suppression d'une rubrique et de toutes les sections associees
  function delCat($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('_selectedok'=>'nok'));
    $lang_data=\Seolan\Core\Shell::getLangData();
    if($lang_data==TZR_DEFAULT_LANG) $lang='';
    elseif(!$this->_categories->isTranslatable()) $lang='';
    else $lang="AND LANG='$lang_data'";

    $selected =   $p->get('_selected');
    $selectedok = $p->get('_selectedok');
    if($selectedok=='ok') {
      foreach($selected as $oid => $bidon) {
	$ar['oid']=$oid;
	$ar['_selected']=array();
	$ar['_selectedok']='nok';
	$this->delCat($ar);
      }
      return;
    }
    $oid=$p->get('oid');
    $oids[$oid]=1;
    $kernel = new \Seolan\Core\Kernel;

    while(count($oids)>0) {
      unset($oids2);
      foreach($oids as $k => $v) {
	$kernel->data_forcedDel(array('oid'=>$k,'action'=>'OK','_selectedok'=>'nok'));
	// suppression des sections
	$rs=getDB()->select('select * from '.$this->tname." where KOIDSRC=?", array($k));
	while($ors=$rs->fetch()) {
	  $itoid = $ors['ITOID'];
	  $koiddst = $ors['KOIDDST'];
	  if(!empty($koiddst)) {
	    $tabledst = \Seolan\Core\Kernel::getTable($koiddst);
	    if(!empty($tabledst)) {
	      if($lang_data == TZR_DEFAULT_LANG) {
		getDB()->execute('DELETE FROM '.$this->tname." where ITOID = ?", array($itoid));
	      }
	      $kernel->data_forcedDel(array('oid'=>$koiddst,'action'=>'OK','_selectedok'=>'nok'));
	    }
	  }
	}
	$rs=getDB()->fetchCol('select distinct KOID from '.$this->table." where linkup=?",array($k));
	foreach($rs as $ors) {
	  $oids2[$ors]=1;
	}
	unset($rs);
      }
      $oids=$oids2;
    }
  }

  /// suppression d'une categorie et de toutes les sections associees
  function dupCat($ar=NULL) {
    $copy_of=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','copy_of');
    $p=new \Seolan\Core\Param($ar,array('_selectedok'=>'nok', 'prefix' => $copy_of.' '));
      $lang_data=\Seolan\Core\Shell::getLangData();
    if($lang_data==TZR_DEFAULT_LANG) $lang='';
    elseif(!$this->_categories->isTranslatable()) $lang='';
    else $lang="AND LANG='$lang_data'";

    $selected =   $p->get('_selected');
    $selectedok = $p->get('_selectedok');

    if($selectedok=='ok') {
      foreach($selected as $oid => $bidon) {
	$ar['oid']=$oid;
	$ar['_selected']=array();
	$ar['_selectedok']='nok';
	$this->dupCat($ar);
      }
      return;
    }

    // duplication de la rubrique
    $oid=$p->get('oid');
    $nkoid=$this->_categories->duplicate(array('oid'=>$oid));
    getDB()->execute('UPDATE '.$this->_categories->getTable().' set title=CONCAT(?, title) where KOID=?',
		     array($copy_of,$nkoid));
    getDB()->execute('UPDATE '.$this->_categories->getTable().' set alias=CONCAT(alias,?) where KOID=? and alias !=?',
		     array('1',$nkoid,''));
    if($this->_categories->fieldExists('PUBLISH')) {
      getDB()->execute('UPDATE '.$this->_categories->getTable().' set PUBLISH=? where KOID=?',
		       array('2', $nkoid));
    }

    // duplication des sections
    $rs=getDB()->select('select * from '.$this->tname.' where KOIDSRC=?', array($oid));
    if($rs->rowCount()>0) {
      $oids=array();
      $ors=array();
      while($ors=$rs->fetch()) {
	$oids[]=$ors['KOIDDST'];
	$itoids[]=$ors['ITOID'];
      }

      // duplication de chacune des sections
      $noids=array();
      foreach($oids as $i=>$oid1) {
	if(!empty($oid1)) {
	  $xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$oid1);
	  $noids[$i]=$xset->duplicate(array('oid'=>$oid1));
	}
      }

      // duplication des liens
      getDB()->execute('CREATE TEMPORARY TABLE tmpo SELECT * FROM '.$this->tname." where KOIDSRC=?", array($oid));
      getDB()->execute("UPDATE tmpo set KOIDSRC=?", array($nkoid));
      foreach($oids as $i=>$oid1) {
	if(!empty($oid1)) {
	  getDB()->execute("UPDATE tmpo set KOIDDST=? where KOIDDST=? and KOIDSRC=?",
			   array($noids[$i],$oid1,$nkoid));
	}
      }
      $rs=getDB()->select('select * from tmpo');
      $ors=array();
      while($rs && ($ors=$rs->fetch())) {
	$olditoid=$ors['ITOID'];
	$n=self::newItOid();
	getDB()->execute("UPDATE tmpo set ITOID=? where ITOID=?",
			 array($n, $olditoid));
      }

      getDB()->execute('INSERT INTO '.$this->tname.' select * from tmpo');
      getDB()->execute('DROP TABLE tmpo');
    }
    $rs=getDB()->fetchCol('SELECT DISTINCT KOID FROM '.$this->table." WHERE linkup= ?/* 1 */",array($oid));
    foreach($rs as $ors) {
      $ar1=array();
      $ar1['_selected']=array();
      $ar1['_selectedok']='nok';
      $ar1['oid']=$ors;
      $nkoid2=$this->dupCat($ar1);
      \Seolan\Core\Logs::debug("recursive duplicate $ors to $nkoid2");
      getDB()->execute("UPDATE {$this->table} set linkup= ? where KOID= ?",array($nkoid,$nkoid2));
    }
    unset($rs);
    return $nkoid;
  }
  /// Publication de section
  public function publish($ar) {
    $p = new \Seolan\Core\Param($ar,array('_pub'=>false));
    $selected = $p->get('_itoidselected');
    $pub = $p->get('_pub');
    $oidit = $p->get('oidit');
    $selectedoid = array();
    $oidsection=$p->get("oidsection");
    if(!empty($oidsection)) {
      $selected[$oidsection]=1;
    }
    if(is_array($selected)) {
      foreach($selected as $itoid => $foo) {
	$rs=getDB()->select('select distinct KOIDDST from '.$this->tname." where ITOID='$itoid'");
	if($o=$rs->fetch()) {
	  if(!empty($o['KOIDDST']))
	    $selectedoid[]=$o['KOIDDST'];
	}
      }
    }
    $_selected=$p->get('_selected');
    if(!empty($_selected)) {
      $copy_of_selectedoid=$selectedoid;
      $selectedoid=array_merge(array_keys($_selected), $copy_of_selectedoid);
    }
    if(!empty($selectedoid)) {
      $ar['_selected']=$selectedoid;
      $ar['key']=false;
      $ar['value']=$pub;
      foreach($selectedoid as $oid) {
	$xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$oid);
	$ar1=array('oid'=>$oid, 'value'=>$pub, '_selected'=>array());
	// propagation
	if (($langsrepli = $this->getLangsRepli($oidit, \Seolan\Core\Shell::getLangData()))){
	  $ar1['_langspropagate'] = $langsrepli;
	}
	$xset->publish($ar1);
      }
    }
  }

  //// Plublication de rubrique
  public function publishCat($ar) {
    $p=new \Seolan\Core\Param($ar,array('_pub'=>false));
    $xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
    $xset->publish($ar);
  }

  // Affichage de l'arboresnce
  //
  function homeedit($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array('level'=>'1','deroule'=>'1','sections'=>'1','tplentry'=>'mit','maxlevel'=>99));
    $this->home($ar);
  }


  // rend la liste des objets qui se trouvent en dessous de cet objet,
  // independamment des niveaux
  //
  public function &subObjects($oid) {
    return $this->getSubCats($oid, TZR_DEFAULT_LANG, 1, 20);
  }

  // rend vrai si le noeud oidit as des fils
  //
  protected function hasChildren($oidit) {
    $cnt=getDB()->count('select count(*) from '.$this->table." where linkup='$oidit'");
    return $cntt;
  }

  // rend l'url conseillee pour un noeud donné
  //
  protected function _selfUrl($oid, $alias=NULL) {
    if(!empty($alias)) {
      $html="alias=$alias";;
    } else {
      $html="oidit=$oid";
    }
    return $html;
  }

  /**
   * Permet d'éditer en masse les alias et les title des pages à partir de l'arborescence
   * @param multiple_edit_fields array Champs à éditer au format {oidXXX: {alias: XXX, title: XXX}, ...}
   */
  function &procHome($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array());
    $multiple_edit_fields=$p->get('multiple_edit_fields');
    foreach($multiple_edit_fields as $oid => $edit_fields) {
      $this->_checkAlias($edit_fields['alias'], '', $oid, $message = '', NULL);
      if (!empty($message)) {
        \Seolan\Core\Shell::alert($message);
        continue;
      }
      $edit_fields['_options'] = array('local' => true);
      $edit_fields['oid'] = $oid;
      $this->_categories->procEdit($edit_fields);
    }
    \Seolan\Core\Shell::setNextData('message', implode(', ',$allmessages));
  }
  /****m* \Seolan\Module\InfoTree\InfoTree/home
   * NAME
   *   \Seolan\Module\InfoTree\InfoTree::home - creation de l'arborescence
   * DESCRIPTION
   *   Preparation d'une structure de donnees pour affichage de la
   *   hierarchie de rubriques
   * INPUTS
   *   Passage de parametre indirect via $ar
   *   level - numero du premier niveau, les niveaux sont numerotes a
   *   partir de level, la valeur par defaut est 1 ;
   *   maxlevel - nombre maximum de niveaux a explorer, valeur par defaut 99 ;
   *   do - action a effectuer. showtree: exploration de toutes les branches ;
   *   oidtop - oid de l'item pere de l'arboresence ;
   *   aliastop - alias de l'item pere de l'arboresence ;
   ****/
  function &home($ar=NULL) {

    \Seolan\Core\Logs::debug('\Seolan\Module\InfoTree\InfoTree::home: start');
    $p = new \Seolan\Core\Param($ar, array('level'=>'1', 'sections'=>'1','myself'=>false,'tplentry'=>'mit','maxlevel'=>99,
			       'lastupd'=>false,'optimized'=>false,'cond_categ'=>[],'norubric'=>false,'where_categ'=>false));
    $do = $p->get('do');
    $myself=$p->get('myself');
    $niv = $p->get('level');
    $linkinfollow=$p->get('linkinfollow');
    $optimized = $p->get('optimized');
    $aliastop = $p->get('aliastop');
    $published = $p->get('_published');
    if(!empty($aliastop)) {
      if(\Seolan\Core\Kernel::isAKoid($aliastop)) $oidtop=$aliastop;
      else $oidtop=$this->getOidFromAlias($aliastop);
    }
    if(empty($oidtop))
      $oidtop = $p->get('oidtop');

    $computesections=$p->get('sections');
    $norubric=$p->get('norubric');
    $oidselected = $p->get('oid');
    $tplentry=$p->get('tplentry');
    $LANG_TRAD = \Seolan\Core\Shell::getLangData($p->get('LANG_TRAD'));
    if(!empty($LANG_TRAD)) {
      $lang_tree=$LANG_TRAD;
    }

    $maxlevel = $p->get('maxlevel');
    $selectedfields=$p->get('selectedfields');
    if(\Seolan\Core\Shell::admini_mode()) setSessionVar('module_name',$this->getLabel());

    if($norubric) $rubric=[];
    else $rubric = getSessionVar('rubric');
    if($do=='add') {
      $st1=$this->getPath($oidselected);
      foreach($st1['oiddown'] as $i=>$oid1) $rubric[$oid1]='1';
    } elseif($do=='del') {
      unset($rubric[$oidselected]);
    } elseif($do=='foldall') {
      clearSessionVar('rubric');
      $rubric=array();
    } elseif($do=='unfoldall') {
      $deroul=true;
    } elseif($do=='showtree') {
      $deroul=true;
    }

    if(empty($this->_rbrowse)) {
      $options=array();
      if(\Seolan\Core\Shell::admini_mode() || $optimized) {
	$options['linkup']=array('nofollowlinks'=>1);
      }
      if(empty($selectedfields)){
	if(\Seolan\Core\Shell::admini_mode()) $selectedfields = array('UPD','PUBLISH','title','corder','linkup','alias','picto','icon', 'descr',$this->linkin);
	else $selectedfields='all';
      }elseif(is_array($selectedfields)){
	$selectedfields=array_merge($selectedfields,array('UPD','PUBLISH','title','corder','linkup','alias','picto','descr',$this->linkin));
      }
      $cond_categ = $p->get('cond_categ');
      $where_categ = $p->get('where_categ');

      $query = $this->_categories->select_query(['cond'=>$cond_categ,
						 'where'=>$where_categ,
						 'selectedfields'=>$selectedfields,
						 'LANG_DATA'=>$lang_tree]);
      $this->_rbrowse=$this->_categories->browse(['select'=>$query,
						  'pagesize'=>9999,
						  'selectedfields'=>$selectedfields,
						  '_charset'=>@$_REQUEST['_charset'],
						  'order'=>'corder',
						  'options'=>$options,
						  'tplentry'=>TZR_RETURN_DATA,
						  'last'=>'9999','nocount'=>'1',
						  '_options'=>['local'=>1]]);
      // appliquer les droits sur les objets
      if ($this->object_sec || $this->interactive) {
      $this->_rbrowse['objects_sec']=$GLOBALS['XUSER']->getObjectsAccess($this,
									 \Seolan\Core\Shell::getLangData(),
									 $this->_rbrowse['lines_oid']);
      }
      // appliquer les droits sur les objets
      if ($this->object_sec) {
        $levelMax = $GLOBALS['XUSER']->secure8maxlevel($this, '', null, \Seolan\Core\Shell::getLangData());
        // construction de l'arbre descendant pour l'héritage des droits
        foreach ($this->_rbrowse['lines_oid'] as $i => $oid) {
          $up = $this->_rbrowse['lines_olinkup'][$i]->raw;
          if (empty($this->_rbrowse['treedown'][$up]))
            $this->_rbrowse['treedown'][$up] = array();
          $this->_rbrowse['treedown'][$up][] = $oid;
        }
        $this->_rbrowse['oid_index'] = array_flip($this->_rbrowse['lines_oid']);
        // calcul des droits cumulés héritables et des enfants
        foreach ($this->_rbrowse['lines_oid'] as $i => $oid) {
          $this->_rbrowse['upobjects_sec'][$i] = $this->_upRights($i);
          $this->_rbrowse['downobjects_sec'][$i] = $this->_downRights($i, $oid);
        }
        foreach ($this->_rbrowse['lines_oid'] as $i => $oid) {
          // si pas de droits spécifiques sur ce noeud, hériter
	  $keystmp=array_keys($this->_rbrowse['objects_sec'][$i]);
	  if (array_pop($keystmp) == $levelMax  && !empty($this->_rbrowse['upobjects_sec'][$i])) {
	    $this->_rbrowse['objects_sec'][$i] = $this->_rbrowse['upobjects_sec'][$i];
	  }
        }
        // suppression des noeuds non lisibles et sans enfants lisibles
        foreach ($this->_rbrowse['lines_oid'] as $i => $oid) {
	  if (!isset($this->_rbrowse['objects_sec'][$i]['ro']) ||
	      (!isset($this->_rbrowse['downobjects_sec'][$i]['ro']) && !isset($this->_rbrowse['objects_sec'][$i]['list']))) {
            unset($this->_rbrowse['lines_oid'][$i]);
           }
         }
       \Seolan\Core\Logs::debug('\Seolan\Module\InfoTree\InfoTree: after security sort');
      }
    }
    $rbrowse=&$this->_rbrowse;
    if(!empty($published)) {
      foreach($this->_rbrowse['lines_oid'] as $i => $oid) {
	$pub1=$this->_rbrowse['lines_published'][$i];
	if(empty($pub1)||($pub1==2)) {
	  unset($this->_rbrowse['lines_oid'][$i]);
	}
      }
    }

    // Contrôle de la présence de l'alias racine demandé
    if (isset($ar["returnEmptyArrayIfMenuIsDisabledOrDoesntExist"])) {
      $found = false;
      foreach($this->_rbrowse['lines_oalias'] as $i => $oid) {
          if ($this->_rbrowse['lines_oalias'][$i]->raw==$aliastop) {
            $found = true;
            break;
          }
      }

      if (!$found) {
        return array();
      }
    }

    // on affiche la poubelle que si c'est l'alias qui est explicitement demandé
    if($aliastop!='trash' && $this->interactive) {
      $trashoid=$this->getTrashOid();
	foreach($this->_rbrowse['lines_oid'] as $i => $oid) {
	  if($oid==$trashoid) {
	    unset($this->_rbrowse['lines_oid'][$i]);
	    break;
	  }
	}
      }


    // on recherche les sommets a parcourir
    $stack=array();
    $koids=array_flip($rbrowse['lines_oid']);
    $koidtoprocess=array_reverse($rbrowse['lines_oid'],TRUE);
    $todel=array();
    foreach($koidtoprocess as $i => $oid) {
      $curi=$koids[$oid];
      $linkup = $rbrowse['lines_olinkup'][$curi]->raw;
      if($myself){
	if($oid==$oidtop){
	  array_push($stack, array($oid,$niv,$curi,1,$linkup));
	  array_push($todel, $i);
	  break;
	}
      }else{
	if((empty($oidtop) && empty($linkup)) || ($oidtop==$linkup)) {
	  array_push($stack, array($oid,$niv,$curi,1,$linkup));
	  array_push($todel, $i);
	}
      }
    }
    while(count($todel)>0) unset($koidtoprocess[array_pop($todel)]);

    // construction de l'arbre descendant
    $treedown = array();
    foreach($rbrowse['lines_oid'] as $i => $oid) {
      $up=$rbrowse['lines_olinkup'][$i]->raw;
      if(empty($treedown[$up])) $treedown[$up]=array();
      $treedown[$up][]=$oid;
    }

    // on parcourt ce qui nous interesse
    $r=array();
    $r['aliastop']=$aliastop;
    $r['self']=$this->_categories->display(['tplentry'=>TZR_RETURN_DATA,
					    'oid'=>$oidtop,
					    '_options'=>['local'=>true]]);;
    $r['lines_oid']=array();
    $lines_oid_cnt=0;
    $r['lines_level']=array();
    $r['lines_deroule']=array();
    $r['lines_ssrub']=array();
    $r['lines_sections']=array();
    $r['lines_lvl1pagenum']=array();
    $lvl1pagenum=0;
    $newkoids=array();
    while(!empty($stack)) {
      // on depile l'element a traiter
      list($curoid,$curlevel,$curi,$curderoul,$linkup)=array_pop($stack);
      if($curderoul && ($curlevel<=$maxlevel || isset($rubric[$curoid]) || isset($rubric[$linkup]))) {
	$r['lines_oid'][]=$curoid;
	$newkoids[$curoid]=$lines_oid_cnt;
	$lines_oid_cnt++;
	$r['lines_level'][]=$curlevel;
	$r['lines_ssrub'][]=0;
	if($computesections=='1') {
          $r['lines_sections'][] = $this->getSectionNb($curoid);
	}
	if($do=='unfoldall') {
	  $rubric[$curoid]='1';
	}
	$deroul=($do=='showtree'?'1':'0');
	$deroul=$deroul || (empty($rubric[$curoid])?'0':'1');
	$r['lines_deroule'][]=$deroul;
	if($curlevel>1)	$lvl1pagenum++;
	else $lvl1pagenum=0;
	$r['lines_lvl1pagenum'][]=$lvl1pagenum;
      }
      $tmp1=@$newkoids[$rbrowse['lines_olinkup'][$curi]->raw];
      if(empty($r['lines_ssrub'][$tmp1])) $r['lines_ssrub'][$tmp1]=1;
      else $r['lines_ssrub'][$tmp1]++;
      if($this->linkin && $linkinfollow && $linkin && $linkin!=$rbrowse['lines_olinkup'][$curi]->raw){
	$tmp1=@$newkoids[$linkin];
	if(empty($r['lines_ssrub'][$tmp1])) $r['lines_ssrub'][$tmp1]=1;
	else $r['lines_ssrub'][$tmp1]++;
      }
      $ocuroid=$curoid;
      if($this->linkin && $linkinfollow && $rbrowse['lines_o'.$this->linkin][$curi]->raw)
	$curoid=$rbrowse['lines_o'.$this->linkin][$curi]->raw;
      if(!empty($treedown[$curoid]) && is_array($treedown[$curoid])) {
	$todel=array();
	foreach(array_reverse($treedown[$curoid]) as $i => $oid) {
	  $oldi=$koids[$oid];
	  $stack[]=array($oid,$curlevel+1,$oldi,$deroul,$ocuroid);
	  $todel[]=$i;
	}
	foreach($todel as $t1=>$toid) unset($koidtoprocess[$toid]);
      }
      $curoid=$ocuroid;
    }
    if(!issetSessionVar('rubric')) {
      if(!empty($rubric)) {
	setSessionVar('rubric',$rubric);
      }
    } else {
      if(!empty($rubric)) {
	setSessionVar('rubric',$rubric);
      } else {
	clearSessionVar('rubric');
      }
    }

    // recopie des donnees du browse
    $norw=array('lines_all_labels','lines_all_codes');
    $rwall=array('objects_sec'=>true,'upobjects_sec'=>true);
    foreach($r['lines_oid'] as $i => $oid) {
      $oldi = $koids[$oid];
      foreach($rbrowse as $k => $v) {
	if(((substr($k, 0,6)=='lines_')||!empty($rwall[$k])) && !in_array($k,$norw)) {
	  $r[$k][$i]=(empty($v[$oldi])?NULL:$v[$oldi]);
	}
	}
      if($computesections && !empty($r['lines_o'.$this->linkin][$i]->raw)){
	$r['lines_sections'][$i] = $this->getSectionNb($r['lines_o'.$this->linkin][$i]->raw);
      }
    }
    foreach($r['lines_oid'] as $i => $oid) {
      $r['lines_selfurl'][$i]=$this->_selfUrl($oid,$r['lines_oalias'][$i]->toText());
    }

    if(!empty($this->linkin) && $this->_categories->fieldExists($this->linkin)) {
      $r['lines_olinkin']=&$r['lines_o'.$this->linkin];
    }
    $r['moid']=$this->_moid;
    if (\Seolan\Core\Shell::admini_mode()) {
      list($r['_editLevel']) = $this->secGroups('editpage');
      list($r['_inputLevel']) = $this->secGroups('input');
      list($r['_viewLevel']) = $this->secGroups('viewpage');
    }
    \Seolan\Core\Logs::debug('\Seolan\Module\InfoTree\InfoTree::home: end');
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }
 /**
  * treeMenu
  * @param array ar: minimal options are
  *    'tplentry' => published returned array is $[tplentry]_tree
  *    'aliastop' => starting point (excluded) for the tree
  *    'maxlevel' => last level taken into account in tree
  * @return array: tree of pages information starting from 'aliastop' option and up to 'maxlevel' option
  *         published in templates as $[tplentry]_tree
  */
  public function getTreeMenu($ar) {
    $tplentry = $ar['tplentry'];
    $ar['tplentry'] = TZR_RETURN_DATA;
    $ar['level'] = 1;
    $ar['do'] = 'showtree';
    $ar['optimized'] = 1;
    if (!isset($ar['sections']))
      $ar['sections'] = 0;
    if (!isset($ar['linkinfollow']))
    $ar['linkinfollow'] = 1;

    $menu = $this->home($ar);
    $tree = $this->menuToTree($menu, 0, 1, $ar['currentAlias']);
    return \Seolan\Core\Shell::toScreen2($tplentry, 'tree', $tree);
  }

  /**
  * menuToTree: recursive function
  * @param array menu: flat menu ordered with the 'showtree' option of 'home' method
  * optionnal:
  * @param int currentIndex: start index of param menu for current recursion
  * @param int level: level value of param menu (as returned by 'home') for current recursion
  * @param string currentAlias: enable to flag current page
  * @return array: convert flat array $menu to tree according to $menu['level'] value
  */
  public function menuToTree($menu, $currentIndex=0, $level=1, $currentAlias='home', $aliasStack=[]) {
    $tree = null;
    for ($i=$currentIndex, $size=count($menu['lines_oid']); $i<$size && $menu['lines_level'][$i] >= $level; $i++) {
      if ($menu['lines_level'][$i] == $level) {
        $item = array();
        $item['oid'] = $menu['lines_oid'][$i];
        $item['selfurl'] = $menu['lines_selfurl'][$i];
        $item['current'] = $currentAlias && $menu['lines_oalias'][$i]->raw == $currentAlias;
        $item['sections'] = $menu['lines_sections'][$i];
        foreach($this->xset->desc as $fname=>$fdef) {
          $item["o$fname"] = $menu["lines_o$fname"][$i];
        }
        $item['aliasStack'] = array_merge($aliasStack, [$menu['lines_oalias'][$i]->raw]);

        if (isset($menu['lines_level'][$i+1]) && $menu['lines_level'][$i+1] > $level) {
          $item['submenu'] = $this->menuToTree($menu, $i+1, $level+1, $currentAlias, $item['aliasStack']);
          foreach ($item['submenu'] as $subitem) { // buble up current
            $item['current'] |= $subitem['current'];
          }
        }
        $tree[] = $item;
        unset($item);
      }
    }
    return $tree;
  }

  // calcul les droits héritables
  private function _upRights($i, $first=true) {
    if ($this->_rbrowse['lines_olinkup'][$i]->raw) {
      // calcul des droits du parent
      $upRights = $this->_upRights($this->_rbrowse['oid_index'][$this->_rbrowse['lines_olinkup'][$i]->raw], false);
      if ($first) {
        return $upRights;
      } else {
        return array_merge($this->_rbrowse['objects_sec'][$i], $upRights);
      }
    } else {
      return array();
    }
  }
  // calcul les droits sur les enfants (+ droit sur l'objet)
  private function _downRights($i, $oid) {
    $downRights = $this->_rbrowse['objects_sec'][$i];
    if(@is_array($this->_rbrowse['treedown'][$oid])) {
      foreach ($this->_rbrowse['treedown'][$oid] as $_oid) {
	$_i = $this->_rbrowse['oid_index'][$_oid];
	if (!isset($this->_rbrowse['downobjects_sec'][$_i]))
	  $this->_rbrowse['downobjects_sec'][$_i] = $this->_downRights($_i, $_oid);
	$downRights = array_merge($downRights, $this->_rbrowse['downobjects_sec'][$_i]);
      }
    }
    return $downRights;
  }

  private function getSectionNb(string $oid) {
    static $sectionNb = NULL;
    if (!$sectionNb) {
      $sectionNb = getDB()->select('SELECT KOIDSRC,count(KOIDSRC) from '.$this->tname.' group by KOIDSRC')->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
    if (isset($sectionNb[$oid])) {
      return (int) $sectionNb[$oid];
    }
    return 0;
  }

  /// Rend les infos sur les rubriques mere de la rubrique oid
  function getPath($ar) {
    $_published = NULL;
    if(is_array($ar)){
      $oid=$ar['oid'];
      $tplentry=$ar['tplentry'];
      $selectedfields = @$ar['selectedfields'];
      $computesections = @$ar['sections'];
      // Ajoute au path les rubriques dépubliées si === false
      $_published = $ar['_published'];
      // Permet de substituer le path de l'alias courant par le path du from (voir affichage d'une fiche détail)
      $from=@$ar['from'];
    }else{
      $oid=$ar;
    }
    if(empty($selectedfields)) $selectedfields = array('title','alias','linkup','style');
    // Browse de la categorie
    $v1=array();
    $stack=array();
    $nav_alias=$nav_cat=$nav_oid=$nav_url=$nav_sections=array();
    $base_oid = $oid;
    do{
      $categ=$this->_categories->rDisplay($oid, [], false,'','',['selectedfields' => $selectedfields,'_published' => $_published]);
      if(!is_array($categ)) break;
      $stack[]=$categ;
      $nav_cat[]= $categ['otitle']->toText();
      $nav_oid[]= $oid;
      $nav_alias[]=$categ['oalias']->toText();
      $nav_url[]='&moid='.$this->_moid.'&function=viewpage&template=Module/InfoTree.viewpage.html&oidit='.$oid.'&tplentry=it';
      if (!empty($computesections)) {
        $nav_sections[] = $this->getSectionNb($oid);
      }
      if (!empty($from) && $oid == $base_oid) {
        if (is_array($from) && isset($from['alias'])) $from = $from['alias'];
        $oid = $this->getOidFromAlias($from);
        unset($from);
      } else {
        $oid = $categ['olinkup']->raw;
      }
      unset($categ);
    } while($oid);
    $v1=array('labelup'=>$nav_cat,'urlup'=>$nav_url,'oidup'=>$nav_oid,'aliasup'=>$nav_alias,
	      'labeldown'=>array_reverse($nav_cat),'urldown'=>array_reverse($nav_url),
	      'aliasdown'=>array_reverse($nav_alias),
	      'oiddown'=>array_reverse($nav_oid),
	      'stack'=>array_reverse($stack));
    if (!empty($computesections)) {
      $v1['sections']=array_reverse($nav_sections);
    }
    if(!empty($tplentry)) \Seolan\Core\Shell::toScreen1($tplentry,$v1);
    return $v1;
  }

  /// Retourne l'oid de tous les parents d'une page
  function _getPathOids($oid) {
    // Vérification du cahce
    $cache=\Seolan\Library\ProcessCache::get('xmodinfotree/_getPathOids',$oid);
    if($cache) return $cache;
    // Calcul et mise en cache
    $stack=array();
    $linkup=$oid;
    do{
      $stack[]=$linkup;
      $linkup=getDB()->fetchOne('SELECT linkup FROM '.$this->table.' WHERE KOID=?',array($linkup));
    }while($linkup);
    \Seolan\Library\ProcessCache::set('xmodinfotree/_getPathOids',$oid,$stack);
    return $stack;
  }

  /// recherche du modele dans le cas ou il n'est pas defini, par heritage des parents
  //@return array('oidRubrique','oidModel')
  protected function _getInheritModelAndRubrique($oid) {
     $oids=$this->_getPathOids($oid);
     foreach($oids as $oid) {
       $model=getDB()->fetchOne('SELECT model FROM '.$this->table.' WHERE KOID=?',array($oid));
       if(!empty($model)) return array('oidModel'=>$model,'oidRubrique'=>$oid);
     }
     return array();
  }

  function getPathString($koid) {
    $nav=$this->getPath($koid);
    $title='';
    foreach($nav['labeldown'] as $t) $title.='> '.$t;
    return $title;
  }

  protected function _followLinkIn($oid) {
    $links=array();
    if($this->_categories->fieldExists($this->linkin)) {
      $found=false;
      while(!$found && !in_array($oid, $links)) {
        $links[] = $oid;
        $query = 'SELECT KOID,'.$this->linkin.' FROM '.$this->_categories->getTable().
          ' WHERE LANG="'.TZR_DEFAULT_LANG.'" AND KOID= ?';
        // En BO le champ CS8.redirmethod n'existe pas !
        if ($this->_categories->fieldExists('redirmethod')) {
          $query.= ' and redirmethod="content"';
        }
	$ors=getDB()->fetchRow($query,array($oid));
	if(!$ors) {
	  $found=true;
	} else {
	  if(!\Seolan\Core\Kernel::isAKoid($ors[$this->linkin])) {
	    $found=true;
	  } else {
	    $oid=$ors[$this->linkin];
	  }
	}
      }
    }
    return $oid;
  }
  /****m* \Seolan\Module\InfoTree\InfoTree/procInput
   * NAME
   *   \Seolan\Module\InfoTree\InfoTree::procInput - creation d'une nouvelle rubrique
   * DESCRIPTION
   *   Fonction d'ajout d'une rubrique dans l'admin en tenant compte du chgement d'ordre d'affichage.
   * INPUTS
   ****/
  function &procInput($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array());
    $title = $p->get('title');
    $linkup = $p->get('linkup');
    $order = $p->get('corder');
    $alias = $p->get('alias');
    if(empty($order)) $order='0';
    $ar['tplentry']=TZR_RETURN_DATA;
    $message='';
    $this->_checkAlias($alias, '', NULL, $message, $title);
    $ar['alias']=$alias;
    if(!empty($message)) {
      \Seolan\Core\Shell::setNextData('message', $message);
    }

    $r=$this->_categories->procInput($ar);

    if(!empty($r['oid'])) {
      // positionnement des droits
      if(!empty($this->object_sec)) {
        $GLOBALS['XUSER']->setUserAccess(get_class($this),$this->_moid,\Seolan\Core\Shell::getLangData(),$r['oid'],'admin');
      }
      // maj de l'order d'affichage de la rubrique.
      $this->majUpOtherOrder($r['oid'],$linkup,$order);
    }
    return $r;
  }

  /****m* \Seolan\Module\InfoTree\InfoTree/edit
   * NAME
   *   \Seolan\Module\InfoTree\InfoTree::edit - creation d'une nouvelle rubrique
   * DESCRIPTION
   *   Fonction d'edition d'une rubrique dans l'admin en tenant compte du chgement d'ordre d'affichage.
   * INPUTS
   ****/
  //
  //
  function edit($ar=NULL) {
    // ? a voir, appelée ?
    $ar['options']['LANGREPLI'] = $this->_langRepliEditOptions();
    $this->_categories->edit($ar);
    $GLOBALS['XSHELL']->tpldata['']['moid']=$this->_moid;
  }
  /// préparation du formulaire de création d'une nouvelle rubrique
  function input($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array());
    $linkup=$ar['options']['linkup']['value']=$p->get('linkup');
    $ar['options']['corder']['value']=$p->get('order');

    if($this->isReadyForMultiZone()){
      if($linkup && $this->isAModel($linkup)) $ar['fieldssec']['model']='none';
      else $ar['fieldssec']['tpl']='none';
    }
    $ar['options']['LANGREPLI'] = $this->_langRepliEditOptions();
    $ar['fmoid'] = $this->_moid;

    $this->_categories->input($ar);

    // nécessaire mais à voir : $_moid est normalement affecté dans la factory ?
    $GLOBALS['XSHELL']->tpldata['']['moid']=$this->_moid;

  }
  /// recherce des langues de replication d'une page de contenu
  function getLangsRepli($oidit, $lang_updated){
    if (\Seolan\Core\Shell::getMonoLang() ||
	!$this->_categories->fieldExists('LANGREPLI')){
      return false;
    }
    // a porter sur xdatasource ?
    $langrepli = getDb()->fetchCol('select LANG from '.$this->_categories->getTable().' where KOID=? and LANGREPLI=?', array($oidit, $lang_updated), 'LANG');
    // verifier les authorized languages
    return $this->getAuthorizedLangs($langrepli, $oidit,'savesection');
  }
  /**
   * configuration de la replication d'une rubrique/section/catégorie
   * et champs en editions si demandés
   * @todo : vérifier les droits : sur l'oid, sur le champ LANGREPLI
   */
  public function categoryLangrepliList($oidit, $edit=false){
    if (!$this->_categories->fieldExists('LANGREPLI')){
      return null;
    }
    // liste des langues possibles (droits)
    $l = array('rawlist'=>null,
	       'authlist'=>array()
	       );
    $authlangs = $this->getAuthorizedLangs('all', $oidit,'savesection');
    $langreplis = array();
    // liste des choix de langues identiques
    $l['rawlist'] = getDB()->fetchAll('select t.lang as tlang, t.LANGREPLI as tlangrepli, src.lang as slang from '.$this->_categories->getTable().' as t left outer join '.$this->_categories->getTable().' as src on src.KOID=t.KOID and t.LANGREPLI=src.LANG where ifnull(t.LANGREPLI, "")!="" and t.koid=?', array($oidit));

    $doptions = array();
    foreach($l['rawlist'] as &$item){
      $langreplis[$item['tlang']] = $item['slang'];
      foreach(array('tlang', 'tlangrepli', 'slang') as $fn){
	$item['o'.$fn] = $this->_categories->desc['LANGREPLI']->display($item[$fn], $doptions);
      }
    }

    // liste des langues et choix
    $flang = new \Seolan\Field\Lang\Lang((object)(array('FIELD'=>'langs[]','FTYPE'=>'\Seolan\Field\Lang\Lang','MULTIVALUED'=>false)));

    foreach($authlangs as $lang){
      $doptions = array();
      $eoptions = array('alternate_list'=>$authlangs);
      // eviter xx=>xx
      unset($eoptions['alternate_list'][array_search($lang, $eoptions['alternate_list'])]);
      $elang = isset($langreplis[$lang])?$langreplis[$lang]:null;
      $flang->field = '_langrepli['.$lang.']';
      $l['authlist'][] = array('raw'=>$lang,
			       'olang'=>$flang->display($lang, $doptions),
			       'olangrepli'=>($edit)?$flang->edit($elang, $eoptions):$flang->display($elang, $eoptions)
			       );
    }
    $l['edit'] = $edit;
    $l['fieldlabel'] = $this->_categories->desc['LANGREPLI']->label;

    // messages la langue / aux autres
    if (count($l['rawlist'])>0){
      $lang_data = \Seolan\Core\Shell::getLangData();
      $message1 = \Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','content_propagated');
      $message2 = \Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','content_propagate');
      $slang = null;
      $rlangs = array();
      foreach($l['rawlist'] as $langrepli){
	if ($langrepli['slang'] == $lang_data && $langrepli['tlang'] != $lang_data){
	  $rlangs[] = $langrepli['otlang']->html;
	}
	if ($langrepli['tlang'] == $lang_data){
	  $slang = $langrepli['oslang']->html;
	}
      }
      // soit une langue est mise à jour par une autre soit elle met à jour 1 ou plusieurs autres
      if (!empty($rlangs)){
	$l['langdata_updateothers'] = $rlangs;
	$l['langdata_updateothers_message'] = $message1.implode(', ', $rlangs).'.';
      }
      if (!empty($slang)){
	$l['langdata_updatedbyother'] = $slang;
	$l['langdata_updatedbyother_message'] = sprintf($message2, ($lang_data_text = \Seolan\Core\Lang::get($lang_data)['text']), $slang, $lang_data_text, $slang);
      }
    }
    return $l;
  }
  /// mise à jour des langues de replication d'une rubrique
  public function updateLangrepli($langrepli, $oid){
    if (!is_array($langrepli) || ($l=$this->categoryLangrepliList($oid, true/* post edit*/)) == null){
      return false;
    }
    foreach($l['authlist'] as $alang){
      $tlang = $alang['raw'];
      $newlang = $alang['olangrepli']->fielddef->post_edit(isset($langrepli[$tlang])?$langrepli[$tlang]:null,
							   null);
      if (empty($newlang->raw)){
	$newlang->raw = '';
      }

      \Seolan\Core\Logs::debug(get_class($this)."::update langrepli  $oid lang $tlang langrepli {$newlang->raw}");
      getDb()->execute("update {$this->_categories->getTable()} set LANGREPLI=? where KOID=? and LANG=?",
		       array($newlang->raw, $oid, $tlang));
    }
  }
  /// mise en forme des options pour le champs langrepli
  private function _langRepliEditOptions($oidit=null){
    $o = null;
    if ($this->_categories->fieldExists('LANGREPLI')){
      if (!\Seolan\Core\Shell::getMonoLang()){
	// on restreint la liste des langues aux "autres langues"
	$o = array('alternate_list'=>array_diff(array_keys($GLOBALS['TZR_LANGUAGES']),
						array(\Seolan\Core\Shell::getLangData())));
	// une langue ayant une source ne peut être src à nouveau (cas oidit connu)
	// sauf dans la langue en cours d'edition
	if ($oidit != null){
	  $used = getDB()->fetchCol('select LANG from '.$this->_categories->getTable().' where koid=? and ifnull(LANGREPLI, "") != "" and LANG!=?', array($oidit, \Seolan\Core\Shell::getLangData()));
	  $o['alternate_list'] = array_diff($o['alternate_list'], $used);
	}
      }
    }
    return $o;
  }

  /// mise a jour d'une rubrique
  function procEdit($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array());
    if($p->is_set('procEditAllLang')) {
      $this->procEditAllLang($ar);
      $p = new \Seolan\Core\Param($ar, array());
    }
    $oid = $p->get('oid');
    $alias = $p->get('alias');
    $title = $p->get('title');
    $langs = $p->get('_langs');
    $lang_data = \Seolan\Core\Shell::getLangData();
    // Traitement des cas ou l'on veut sauver dans plusieurs langues
    $ar['_langs']=$this->getAuthorizedLangs($langs,$oid,'procEdit');

    $disp_cat=$this->_categories->display(array('oid'=>$oid,'tplentry'=>TZR_RETURN_DATA));

    // mise a jour des equivalences de langues si il y en a
    $this->updateLangrepli($p->get('_langrepli'), $oid);

    if($lang_data == TZR_DEFAULT_LANG) {
      $message='';
      $oldalias= $disp_cat['oalias']->raw;
      // vérification alias
      $this->_checkAlias($alias, $oldalias, $oid, $message, $title);
      $ar['alias'] = $alias; // peut-être modifié par _checkAlias

      $this->updatePageCache(NULL, $alias);

      // vérification de boucle sur linkin
      if ($this->linkin) {
        $linkin = $p->get($this->linkin);
        if ($linkin && !$this->_checkLinkin($oid, $linkin)) {
          $message .= '<br>' . $this->_categories->desc[$this->linkin]->label . ' : ' . \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','circularity');
        }
      }
      //Boucle Parent / enfant : on vérifie que la page courante ne fait pas partie des parents de linkup
      $linkup=$p->get('linkup');
      $stack = $this->_getPathOids($linkup);
      if (in_array($oid, $stack)) {
        $message .= '<br>' . $this->_categories->desc['linkup']->label . ' : ' . \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','circularity');
      }
      if (!empty($message)) {
        unset($_REQUEST['skip'], $_REQUEST['_skip']);
        \Seolan\Core\Shell::changeTemplate('Module/InfoTree.editcat.html');
        \Seolan\Core\Shell::setNext();
        \Seolan\Core\Shell::toScreen2('', 'message', $message);
        $ar['options'] = $this->_categories->prepareReEdit($ar);
        $ar['tplentry'] = 'editcat';
        return $this->editcat($ar);
      }
      $ar['tplentry']='addrub';

    }

    // ajout des paramètres de replication selective de langues
    if (false !== ($langrepli = $this->getLangsRepli($oid, $lang_data))){
      \Seolan\Core\Logs::critical(get_class($this), '::savesection propagate '.$lang.' on '.implode(',', $langrepli));
      $ar['_langspropagate'] = $langrepli;
    }

    $this->_categories->procEdit($ar);

    // maj de l'order d'affichage de la rubrique.
    $linkup=$p->get('linkup');
    $corder=$p->get('corder');
    if($corder) {
      $this->majOtherOrder($oid,$linkup,$disp_cat['ocorder']->raw,$corder);
    }
    $this->_reorderCat($linkup);
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

  // verification que l'alias est valide et generation eventuelle d'un alias
  //
  function _checkAlias(&$alias, $oldalias, $oid, &$message, $title=NULL) {

    if(empty($alias) && !empty($title)) {
      $alias=rewriteToAscii($title);
    }
    /* verification que les alias sont bien constitués de 2 à 40 chiffres, lettres _ et - */
    $aliasfield=$this->_categories->getField('alias');
    $aliaslength=$aliasfield->get_fcount();
    if(!preg_match('/^([a-zA-Z0-9_-]{1,'.$aliaslength.'})$/',$alias)) {
      $message=\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','aliasnotcorrect').' '.$alias;
      $alias=$oldalias;
    }
    /* verification de l'unicite des alias */
     if(!empty($alias) ) {
      $req = "select count(*) from ".$this->_categories->getTable()." where alias=? ";
      if(!empty($oid)) $req .= "and  KOID != '".$oid."' ";
      $cnt=getDB()->count($req, [$alias]);
      if($cnt>0) {
	$message=\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','aliasunique').' '.$alias;
	$alias=$oldalias;
      }
    }
    /* verification si l'alias est protege */
    if(($oldalias!=$alias) && !empty($oldalias) && $this->aliasIsProtected($oldalias)) {
      $message=\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','aliasprotected').' '.$alias;
      $alias=$oldalias;
      $modif = 1;
    }
  }

  // test de circularité des redirections
  function _checkLinkin($oid, $linkin) {
    $path = $this->_getPathOids($oid);
    // test la cible
    if (in_array($linkin, $path))
      return false;
    // test le lien de la cible
    $target_linkin = getDB()->select("select {$this->linkin} from {$this->table} where koid='$linkin'")->fetch(\PDO::FETCH_COLUMN);
    if ($target_linkin && in_array($target_linkin, $path))
      return false;
    // test les sous-noeuds de la cible
    return $this->_checkLinkinChild($linkin, $path);
  }

  // test de circularité des redirections pour les descendants
  function _checkLinkinChild($oid, $path) {
    $children = getDB()->fetchAll("SELECT KOID, {$this->linkin} FROM {$this->table} WHERE linkup=?",array($oid));
    foreach ($children as $child) {
      if ($child[$this->linkin] && in_array($child[$this->linkin], $path))
        return false;
      if (!$this->_checkLinkinChild($child['KOID'], $path))
        return false;
    }
    unset($children);
    return true;
  }

  /// Met à jour l'ordre des rubriques d'un noeud après l'insertion d'une rubrique
  function majUpOtherOrder($oid,$linkup,$forder) {
    if($linkup) $linkupw='linkup=?';
    else{
      $linkup='';
      $linkupw='(linkup=? or linkup is null)';
    }
    $q='UPDATE '.$this->table .' set corder=corder+1 where KOID!=? and '.$linkupw.' and corder>=?';
    getDB()->execute($q,array($oid,$linkup,$forder));
  }

  /// Met à jour l'ordre des rubriques d'un noeud après le changement d'ordre d'une rubrique
  function majOtherOrder($oid,$linkup,$oldOrder,$newOrder) {
    if($newOrder==$oldOrder) return;
    if($linkup) $linkupw='linkup=?';
    else{
      $linkup='';
      $linkupw='(linkup=? or linkup is null)';
    }
    if($newOrder>$oldOrder) {
      $q='UPDATE '.$this->table .' set corder=corder-1 where KOID!=? and '.$linkupw.' and corder>'.$oldOrder.' and corder<='.$newOrder;
    }else{
      $q='UPDATE '.$this->table .' set corder=corder+1 where KOID!=? and '.$linkupw.' and corder<'.$oldOrder.' and corder>='.$newOrder;
    }
    getDB()->execute($q,array($oid,$linkup));
  }

  //
  function confirmDel($ar=NULL) {
    global $XSHELL;

    $p = new \Seolan\Core\Param($ar, array(	"tplentry"=>$this->table));
    $oid=$p->get("oid");
    $lang=\Seolan\Core\Shell::getLangData();

    $oidContenu = array();
    $this->browse(array("oid"=>$oid,"level"=>$niv), $oidContenu);

    $result = array();
    $result["oid"] = $oid;
    $result["level"] = $niv;
    $XSHELL->tpldata["$tplentry"] = $result;
  }

  // rend la liste des templates avec eventuellement un selecteur sur la table destination
  // si subset est un ensemble de koid (tableau), seules les reponses
  // deja presente dans ce tableau seront retournees
  //
  protected function &_templatesList($tabledst=NULL,$filter="page",$oidit=NULL) {
    if(!empty($oidit) && $this->_categories->fieldExists('alayout')) {
      $r2=$this->_disp2Cat($oidit);
    }
    if(!empty($r2['oalayout']->oidcollection)) {
      $subset=$r2['oalayout']->oidcollection;
      if(!empty($tabledst)) {
	$cond = $this->_templates->select_query(array("cond"=>array('tab'=>array("=",$tabledst),
								    "modid"=>array("=",array($this->_moid,'')),
								    "KOID"=>array("=",$subset))));
      } else {
	$cond = $this->_templates->select_query(array("cond"=>array("modid"=>array("=",array($this->_moid,'')),
								    "gtype"=>array("=",$filter),
								    "KOID"=>array("=",$subset))));
      }
    } else {
      if(!empty($tabledst)) {
	$cond = $this->_templates->select_query(array("cond"=>array('tab'=>array("=",$tabledst),
								    "modid"=>array("=",array($this->_moid,'')))));
      } else {
	$cond = $this->_templates->select_query(array("cond"=>array("modid"=>array("=",array($this->_moid,'')),
								    "gtype"=>array("=",$filter))));
      }
    }
    $r=$this->_templates->browse(array("select"=>$cond,"first"=>"0","last"=>"999","pagesize"=>99,
					"order"=>'title',
					"tplentry"=>TZR_RETURN_DATA,
					"selectedfields"=>array("title","tab",'functions', 'modidd')));
    return $r;
  }

  /// Rend les donnees concernant un template d'oid fourni
  function &_disp2Template($oidt=NULL) {
    static $cache=array();

    if(!isset($cache[$oidt])){
      $opt=array();
      $opt['oid']=$oidt;
      $opt['tplentry']=TZR_RETURN_DATA;
      $opt['fallback']='true';
      $opt['_options']=array('error'=>'return','local'=>true);
      $opt['_lastupdate']=false;
      $cache[$oidt]=$this->_templates->display($opt);
    }
    return $cache[$oidt];
  }

  /// modification du titre de la page en fonction de diverses éléments. Le titre doit être dans le champ title
  function getPageTitle(&$display) {
    // cette fonction peut être surchargée pour un calcul du titre de page personnalisé
  }

  /// Rend les données d'une rubrique avec le détails des zones, du template d'affichage...
  function &_disp2Cat($oid, $ar = []){
    if (empty($oid))
      return false;
    static $cache=array();
    $display_options = array(
        'redirmethod'=>array('nofollowlinks'=>!\Seolan\Core\Shell::admini_mode()),
        'tpl'=>array('target_fields'=>array('title','disp','zones')),
        'model'=>array(
            'target_fields'=>array('tpl'),
            'target_options'=>array(
                'tpl'=>array(
                    'target_fields'=>array('title','disp','zones')
                )
            )
        )
    );
    $display_options = array_replace_recursive($display_options, (array) @$ar['options']);

    if(!isset($cache[$oid])){
      // Display de la page
      $cache[$oid]=$this->_categories->display([
        'oid'=>$oid,
        'tplentry'=>TZR_RETURN_DATA,
        'fallback'=>true,
        'options'=>$display_options,
        '_options'=>['error'=>'return','local'=>true],
      ]);
      $this->getPageTitle($cache[$oid]);
      $cache[$oid]['_model']=false;
      // Liste des zones
      $zones=array();
      if($this->isReadyForMultiZone()){
        //si pas de model on cherche l'heritage
        if(!$cache[$oid]['omodel']->raw){
          $modelAndRubrique = $this->_getInheritModelAndRubrique($oid) ;
          $options= array('target_fields'=>array('tpl'),
                          'target_options'=>
                          array('tpl'=>array('target_fields'=>array('title','disp','zones'))));
          if($modelAndRubrique['oidModel']){
            $cache[$oid]['omodel']=$cache[$oid]['omodel']->fielddef->display($modelAndRubrique['oidModel'],$options);
            $cache[$oid]['inheritCategoryOid']=$modelAndRubrique['oidRubrique'];
          }
        }

        // On récupère les zones à partir du template du modèle
        // Dans le cas d'une page modèle, on les recupère directement par le template
        if($this->isAModel($oid)){
          $cache[$oid]['_model']=true;
          $zones=$cache[$oid]['otpl']->link['ozones']->rawcollection;
        }elseif($cache[$oid]['omodel']->raw && $cache[$oid]['omodel']->link['otpl']->raw){
          $cache[$oid]['otpl']=$cache[$oid]['omodel']->link['otpl'];
          $zones=$cache[$oid]['omodel']->link['otpl']->link['ozones']->rawcollection;
        }
      }else{
        // Code pour compatibilité (écraser otpl avec otpl.odisp n'est pas top mais c'était comme ca dans version antérieure, donc on le garde tel quel)
        if(!empty($cache[$oid]['otpl'])){
          $cache[$oid]['otpl']=$cache[$oid]['otpl']->link['odisp'];
        }
      }
      if(empty($zones)) $zones[]='default';
      $cache[$oid]['zones']=$zones;
    }
    return $cache[$oid];
  }

  /// Vérifie si le module est configuré pour du multizone ou pas
  function isReadyForMultiZone(){
    return ($this->_categories->fieldExists('tpl') && $this->_categories->fieldExists('model'));
  }

  function editsection($ar) {
    global $XSHELL;
    $p = new \Seolan\Core\Param($ar, array("level"=>1,"tplentry"=>"it","toc"=>"1","maxlevel"=>1));
    $tplentry = $p->get("tplentry");
    $oidit = $p->get("oidit");
    $lang = \Seolan\Core\Shell::getLangData();

    $oidsection=$p->get("oidsection");
    list($oidit,$oiddest,$oidtemplate,$zone)=$this->_getOids($oidsection);
    $tableofoid=\Seolan\Core\Kernel::getTable($oiddest);
    // Browse des modeles de mise en page
    \Seolan\Core\Shell::toScreen1('tple', $this->_templatesList($tableofoid,'page', $oidit));

    // infos d'affichage du template
    $r1=$this->_disp2Template($oidtemplate);
    \Seolan\Core\Shell::toScreen1('tp',$r1);

    $xst = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$tableofoid);
    $ar["tplentry"]=TZR_RETURN_DATA;
    $ar['oid']=$oiddest;
    $ar['fallback']=true;
    if(empty($ar['fmoid']))
      $ar['fmoid']=$this->_moid;
    $r=$xst->edit($ar);
    $s['olines'][]=$r;
    $s['olines_oid'][]=$oiddest;
    $s['zone']=$zone;

    // langues possibles pour enregistrement
    if (($translatable = $xst->getTranslatable()) && TZR_DEFAULT_LANG == \Seolan\Core\Shell::getLangData()){
      $s['authorized_languages'] = $this->getAuthorizedLangs('all', $oiddest, 'edit');
      $s['langsort']=\Seolan\Core\Lang::getCodes(NULL,true);
    }

    // langue de replication et messages
    $langreplis = $this->categoryLangrepliList($oidit);
    if (isset($langreplis['langdata_updateothers_message'])){
      setSessionVar('message', getSessionVar('message').' '.$langreplis['langdata_updateothers_message']);
    }

    \Seolan\Core\Shell::toScreen1('it',$s);
    \Seolan\Core\Shell::toScreen1($tplentry,$r);
    \Seolan\Core\Shell::toScreen2('section','oidit',$oidit);
    \Seolan\Core\Shell::toScreen2('section','zone',$zone);
    \Seolan\Core\Shell::toScreen2('section','oidsection',$oidsection);
    \Seolan\Core\Shell::toScreen2('section', 'langrepli', $langreplis);

    //On récupère le display de la section pour l'afficher en bas du edit si on est en mode traduction
    if($p->get("LANG_TRAD") && !empty(TZR_DEEPL_WEBSERVICE_KEY)){
        $ors = $this->getORS($zone,$oidsection);
        $disp = $this->viewSectionTrad($p,$oidit,$ors);
        \Seolan\Core\Shell::toScreen2('view','disp',$disp);
    }
  }

    function getORS($zone,$oidsection){
        $ors = getDB()->fetchAll("select * from {$this->tname} where ZONE=? AND ITOID=?",[$zone,$oidsection])[0];
        return $ors;
    }
    function viewSectionTrad($p,$oidit,$ors){
        $type = $this->_disp2Template($ors['KOIDTPL'])['ogtype']->raw;
        switch($type){
        case 'function':
            $ots=$this->viewFunction($p,$oidit,$ors['KOIDTPL'],$ors['ITOID'],$ors['KOIDDST']);
            // Si pas de droit et qu'on est sur la page d'edition des sections, on remplace le template par le template affichant un message d'information
            if($ots===false && \Seolan\Core\Shell::admini_mode() && \Seolan\Core\Shell::getTemplate()=='Module/InfoTree.viewpage.html'){
                $this->viewpageLineManager('add',$oidit,$ors['ITOID'],array('_functionparams'=>1),$this->_disp2Template('TEMPLATES:UNAUTH'));
            }
            break;
        case 'query':
            $ots=$this->viewQuery($p,$oidit,$ors['KOIDTPL'],$ors['ITOID'],$ors['KOIDDST']);
            break;
        default:
            $ots=$this->viewStatic($p,$oidit,$ors['KOIDTPL'],$ors['ITOID'],$ors['KOIDDST']);
            break;
        }
        return $ots;
    }

  // affichage des donnees d'un objet particulier d'une page
  //
  function displaysection($ar) {
    $p = new \Seolan\Core\Param($ar, array("level"=>1,"tplentry"=>"it",
			       "toc"=>"1","maxlevel"=>1));
    $tplentry = $p->get("tplentry");
    $oidsection = $p->get("oidsection");

    $lang = \Seolan\Core\Shell::getLangData();
    $tab= \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$oidsection);
    if($tplentry==TZR_RETURN_DATA)
      return $tab->display(array("oid"=>$oidsection, "tplentry"=>TZR_RETURN_DATA));
    else
      $tab->display(array("oid"=>$oidsection, "tplentry"=>$tplentry));
  }

  /// Liste les types de sections qui peuvent etre créées
  public function addSection($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $oidit=$p->get('oidit');
    $r['cat_mit']=$this->_disp2Cat($oidit);
    $r['page']=$this->_templatesList(NULL, 'page', $oidit);
    $r['query']=$this->_templatesList(NULL, 'query', $oidit);
    $r['function']=$this->_templatesList(NULL, 'function', $oidit);
    $r['position'] = getDB()->fetchAll('select ZONE,MAX(ORDER1) POS from '.$this->tname.' WHERE KOIDSRC=? group by ZONE order by ORDER1',
                                       array($oidit));
    $r['oidit']=$oidit;

    if(!empty($r['cat_mit']['omodel']->raw)){
      $r['cat_mit']['zones_not_editable']=array();
      foreach($r['cat_mit']['zones'] as $i=>$zone){
        $not_editable=getDB()->fetchOne('select _not_editable from '.$this->zonetable.' where zone=? and cat=?',array($zone,$r['cat_mit']['omodel']->raw));
        if((int)$not_editable===1) $r['cat_mit']['zones_not_editable'][$zone]=true;
      }
    }
    $modlist = \Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA, 'basic' => 1, 'withmodules'=>1, 'refresh'=>1));
    for ($i=0; $i<count($modlist['lines_oid']); $i++) {
      if(is_object($modlist['lines_mod'][$i])) {
	$uiFunctions = $modlist['lines_mod'][$i]->getUIFunctionList();
	if ($modlist['lines_mod'][$i]->available_in_display_modules && !empty($uiFunctions)) {
	  $r['modlist']['lines_oid'][] = $modlist['lines_oid'][$i];
	  $r['modlist']['lines_mod'][] = $modlist['lines_mod'][$i];
	  $r['modlist']['lines_name'][] = $modlist['lines_name'][$i];
	  $r['modlist']['lines_group'][] = $modlist['lines_group'][$i];
	  $r['modlist']['lines_toid'][] = $modlist['lines_toid'][$i];
	  $r['modlist']['lines_classname'][] = $modlist['lines_classname'][$i];
	  $r['modlist']['lines_functions'][] = $uiFunctions;
	  $class = get_class($modlist['lines_mod'][$i]);
	  $r['modlist']['lines_classes'][] = array_merge(class_parents($modlist['lines_mod'][$i]), array($class => $class));
	}
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  /// preparation de l'ecran de saisie d'une nouvelle section
  function newsection($ar) {
    global $XSHELL;
    $p = new \Seolan\Core\Param($ar, array("level"=>1,"tplentry"=>"it","toc"=>"1","maxlevel"=>1));
    $tplentry = $p->get("tplentry");
    $oidit = $p->get("oidit");
    $oidtpl = $p->get("oidtpl");
    $lang = \Seolan\Core\Shell::getLangData();

    $tabletpl=\Seolan\Core\Kernel::getTable($oidtpl);
    $rs=getDB()->select('select * from '.$tabletpl.' where KOID=? AND LANG=?', array($oidtpl,$lang));
    if($o1=$rs->fetch()) {
      $tabledst = $o1['tab'];

      // Browse des modeles de mise en page
      \Seolan\Core\Shell::toScreen1('tple', $this->_templatesList($tabledst,"page",$oidit));

      // infos d'affichage du template
      $r1=$this->_disp2Template($oidtpl);
      \Seolan\Core\Shell::toScreen1('tp',$r1);

      $xst = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$tabledst);
      if ($xst == null) {
        \Seolan\Core\Logs::critical('No destination table associated with template "'.$r1['otitle']->text.'" ['.$r1['oid'].']');
        return false;
      }

      $ar["tplentry"]=TZR_RETURN_DATA;
      if(empty($ar['fmoid']))
	$ar['fmoid']=$this->_moid;
      $r=$xst->input($ar);
      $s['position']=$p->get('position');
      $s['oidit']=$p->get('oidit');
      $s['oidtpl']=$p->get('oidtpl');
      $s['olines'][]=$r;
      $s['olines_oid'][]=$xst->getNewOID($ar);
      \Seolan\Core\Shell::toScreen1($tplentry,$s);
    }
  }

  function editquery($ar) {
    $p = new \Seolan\Core\Param($ar, array("level"=>1,"tplentry"=>"it","toc"=>"1","maxlevel"=>1));
    $tplentry = $p->get("tplentry");
    $oidsection = $p->get("oidsection");
    list($oidsrc,$oiddest,$oidtpl)=$this->_getOids($oidsection);
    // recherche de l'item
    $d=$this->_dyn->display(array('oid'=>$oiddest));
    $qinit=$d['oquery']->decodeRaw(true);
    // table des templates
    $rtpl=$this->_disp2Template($oidtpl);
    $tabledst = $rtpl['otab']->raw;
    \Seolan\Core\Shell::toScreen1('tple',$this->_templatesList($tabledst,'query',$oidit));

    // table dans laquelle se trouvenet les donnes
    $table = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$tabledst);
    $query=array();
    if(is_array($qinit)) {
      $query['operator']=$qinit['operator'];
      foreach($table->desc as $k => $v) {
	if(isset($qinit[$k]) || !empty($qinit[$k.'_PAR'])) {
	  if($table->fieldExists($k)) {
	    $query[$k]=$qinit['options'][$k];
	    $query[$k]['value']=$qinit[$k];
	    $query[$k]['op']=$qinit[$k.'_op'];
	    $query[$k]['par']=$qinit[$k.'_PAR'];
	    $query[$k]['fmt']=(isset($qinit[$k.'_FMT'])?$qinit[$k.'_FMT']:$qinit[$k.'_HID']);
	    $query[$k]['notapplyqfmt']=true;
	  } else {
	    $query[$k]=$qinit[$k];
	  }
	}
      }
    }
    $ar['tplentry']=TZR_RETURN_DATA;
    $ar['options']=$query;
    $ar['querymode']='pquery';
    if(empty($ar['fmoid']))
      $ar['fmoid']=$this->_moid;
    $r=$table->query($ar);
    $r['oidit']=$oidsrc;
    $r['_select']=$qinit['_select'];
    $r['oidsection']=$oidsection;
    $r['pagesize']=$qinit['pagesize'];
    $r['setup']=$oidtpl;
    $r['labelin']=$qinit['labelin'];
    if(is_array($qinit['order'])) {
      foreach($qinit['order'] as $ki=>$vi){
	$torder=explode(" ",$qinit['order'][$ki]);
	$r['order'][$ki]=$torder[0];
	$r['_order'][$ki]=$torder[1];
      }
    } else {
      $torder=explode(" ",$qinit['order']);
      $r['order'][0]=$torder[0];
      $r['_order'][0]=$torder[1];
    }

    foreach($r['order'] as $ki=>$vi){
      $r['fieldselector'][$ki]=$table->order_selector(array('fieldname'=>'order[]','value'=>$r['order'][$ki],'random'=>($ki==0),
							    'compulsory'=>($ki==0)
							    )
						      );
    }
    if(empty($r['fieldselector'][0])) $r['fieldselector'][0] = $table->order_selector(array('fieldname'=>'order[]',
											    'value'=>'','compulsory'=>false));
    $r['emptyfieldselector'] = $table->order_selector(array('fieldname'=>'order[]','value'=>'','compulsory'=>false));
    $r['weborderselector'] = $table->order_selector(array('fieldname'=>'weborderselector[]','value'=>$qinit['weborderselector'],
							  'compulsory'=>false,'random'=>false,'multiple'=>true));

    $oidit=$oidsrc;
    return \Seolan\Core\Shell::toScreen1('section',$r);
  }
  function savequery($ar) {
    $p = new \Seolan\Core\Param($ar, array('level'=>1,'tplentry'=>'it','toc'=>'1','maxlevel'=>1));
    $setup = $p->get("setup");
    $tplentry = $p->get("tplentry");
    $oidsection = $p->get("oidsection");
    $weborderselector=$p->get('weborderselector');
    $hasselect=$p->get('hasselect');  // true si la section utilise le mode expert et que l'on sauve sans les droits root
    $labelin=$p->get('labelin');
    // recherche de l'item
    list($oidsrc,$oiddest,$oidtpl)=$this->_getOids($oidsection);
    // table des templates
    $rtpl=$this->_disp2Template($oidtpl);
    // table dans laquelle se trouvenet les donnes
    $table = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$rtpl['otab']->raw);
    if($hasselect){
      $d=$this->_dyn->display(array('oid'=>$oiddest));
      $qinit=$d['oquery']->decodeRaw(true);
      $ar['_select']=$qinit['_select'];
    }
    $st=$table->captureQuery($ar);
    $st['weborderselector']=$weborderselector;
    $st['labelin']=$labelin;
    $langs=$p->get('_langs');
    $langs=$this->getAuthorizedLangs($langs,$oidit,'savequery');
    $this->_dyn->procEdit(array(
      '_local'=>true,
      'oid'=>$oiddest,
      'module'=>'',
      'query'=>$st,
      'tpl'=>$setup,
      '_langs'=>$langs,
      'tplentry'=>TZR_RETURN_DATA
    ));
    getDB()->execute("UPDATE ".$this->tname." set KOIDTPL= ? where ITOID= ?",array($setup,$oidsection));
    $this->updatePageCache($oidsrc);
    $ar["oidit"]=$oidsrc;
  }

  /// Insertion d'une nouvelle section
  function insertsection($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array('level'=>1,'tplentry'=>'it','toc'=>'1','maxlevel'=>1));
    $tplentry = $p->get("tplentry");
    $oidit = $p->get("oidit");
    $lang = \Seolan\Core\Shell::getLangData();
    $zone = $p->get('zone');
    $oidtpl = $p->get("oidtpl");
    $position = $p->get("position")+1;
    $ors=getDB()->fetchRow('select * from TEMPLATES where KOID=?',array($oidtpl));
    if($ors) {
      $tabledst=$ors['tab'];
      $xst=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$tabledst);
      $ar2=$ar;
      $ar2['tplentry']=TZR_RETURN_DATA;
      $ar2['LANG_DATA']=$lang;
      $ret=$xst->procInput($ar2);
      $nitoid=$this->insertSectionInIt(array('oidit'=>$oidit,'oidsection'=>$ret['oid'],'oidtpl'=>$oidtpl,'position'=>$position,'zone'=>$zone));
      $this->updatePageCache($oidit);
      return array('oid'=>$ret['oid'],'itoid'=>$nitoid);
    }
  }

  /// Generation d'une nouvelle cle dans la table de liaison
  protected function newItOid() {
    $id= substr(md5(uniqid(rand(), true)),0,40);
    return $id;
  }

  /// Insere les données d'une section dans la table IT
  protected function insertSectionInIt($ar){
    if(empty($ar['nitoid'])) $ar['nitoid']=self::newItOid();
    if(empty($ar['zone']))$ar['zone']='default';
    getDB()->execute('UPDATE '.$this->tname.' SET ORDER1=ORDER1+1 where KOIDSRC=? AND ORDER1>=? AND ZONE=?',
                     array($ar['oidit'],$ar['position'],$ar['zone']));
    getDB()->execute('INSERT INTO '.$this->tname.' SET KOIDSRC=?,KOIDDST=?,KOIDTPL=?,ORDER1=?,ITOID=?,ZONE=?',
                     array($ar['oidit'],$ar['oidsection'],$ar['oidtpl'],$ar['position'],$ar['nitoid'],$ar['zone']));
    $this->_reorderSections($ar['oidit']);
    return $ar['nitoid'];
  }

  /// Insertion d'une section dynamique dans la page
  function insertquery($ar) {
    $p = new \Seolan\Core\Param($ar, array('level'=>1,'tplentry'=>'it','toc'=>'1','maxlevel'=>1));
    $oidtpl = $p->get("oidtpl");
    $lang = \Seolan\Core\Shell::getLangData();
    $oidit = $p->get("oidit");
    $zone = $p->get('zone');
    $position = $p->get("position")+1;
    $ors=getDB()->fetchRow('select * from TEMPLATES where KOID=?',array($oidtpl));
    if($ors) {
      $ret=$this->_dyn->procInput(array(
        '_local'=>true,
        'module'=>'',
        'query'=>array('pagesize'=>20,'UPD'=>'xxxx'),
        'tpl'=>$oidtpl,
        'tplentry'=>TZR_RETURN_DATA
      ));
      $nitoid=$this->insertSectionInIt(array('oidit'=>$oidit,'oidsection'=>$ret['oid'],'oidtpl'=>$oidtpl,'position'=>$position,'zone'=>$zone));
      $this->updatePageCache($oidit);
      return array('oid'=>$ret['oid'],'itoid'=>$nitoid);
    }
  }
  function _getOids($oidsection) {
    if($ors=getDB()->fetchRow("SELECT * FROM ".$this->tname." WHERE ITOID=?", array($oidsection))) {
      $oidtemplate=$ors['KOIDTPL'];
      $oiddest=$ors['KOIDDST'];
      $oidit=$ors['KOIDSRC'];
      $zone=$ors['ZONE'];
      return array($oidit,$oiddest,$oidtemplate,$zone);
    } else {
      return NULL;
    }
  }

  /// Change l'ordre et/ou le parent d'une section
  public function moveSection($ar) {
    $p = new \Seolan\Core\Param($ar, array());
    $oidsection=$p->get('oidsection');
    $oidit=$p->get('oidit');
    $dir=$p->get('dir');
    $new_zone=$p->get('zone');
    $new_p=$p->get('_dest');

    if(!isset($oidsection)) {
      $oidsection=$p->get('_itoidselected');
      foreach($oidsection as $oid => $foo) {
	$ar['oidsection']=$oid;
	$this->moveSection($ar);
      }
      return;
    }

    if(!empty($new_p)){
      if(!\Seolan\Core\Kernel::isAKoid($new_p)) $new_p=$this->getOidFromAlias($new_p);
    }else{
      $new_p=$oidit;
    }

    // securite: dans tous les cas, si on a pas les droits d'ecriture sur la source
    // on ne peut ni modifier l'ordre des sections, ni changer une section de page
    if(!$this->secure($oidit, ':rw')) return;
    if(($new_p!=$oidit ) && !$this->secure($new_p, ':rw')) return;

    $this->_reorderSections($oidit);
    if($new_p!=$oidit) $this->_reorderSections($new_p);

    $row=getDB()->fetchRow("SELECT ZONE, ORDER1 FROM {$this->tname} WHERE ITOID=?", [$oidsection]);
    $o=$row['ORDER1'];
    $zone=$row['ZONE'];
    if(empty($new_zone)) $new_zone=$zone;
    if($dir=="top") {
      $new_o=0;
    } elseif($dir=="bottom") {
      $new_o=999;
    } elseif($dir=="up") {
      $new_o=$o-1;
      getDB()->execute("UPDATE {$this->tname} SET ORDER1=? WHERE ORDER1=? AND KOIDSRC=? AND ZONE=?", [$o, $new_o, $oidit, $zone]);
    } elseif($dir=="down") {
      $new_o=$o+1;
      getDB()->execute("UPDATE {$this->tname} SET ORDER1=? WHERE ORDER1=? AND KOIDSRC=? AND ZONE=?", [$o, $new_o, $oidit, $zone]);
    } elseif($dir=="to") {
      $new_o=0;
    } elseif(!empty($dir)) {
      $new_o=getDB()->fetchOne("SELECT DISTINCT ORDER1 FROM {$this->tname} WHERE ITOID=?", [$dir])+1;
      getDB()->execute("UPDATE {$this->tname} SET ORDER1=ORDER1+1 WHERE ORDER1>=? AND KOIDSRC=? AND ZONE=?", [$new_o, $oidit, $new_zone]);
    } else{
      $new_o=0;
    }
    getDB()->execute("UPDATE {$this->tname} SET ORDER1=?,KOIDSRC=?,ZONE=? WHERE ITOID=?", [$new_o, $new_p, $new_zone, $oidsection]);

    $this->_reorderSections($oidit);
    if($new_p!=$oidit) $this->_reorderSections($new_p);
    $this->updatePageCache($oidit);
  }

  public function delSection($ar) {
    $p = new \Seolan\Core\Param($ar, array());
    $oidsection=$p->get("oidsection");
    if(!isset($oidsection)) {
      $oidsection=$p->get("_itoidselected");
      if(is_array($oidsection)) {
	foreach($oidsection as $oidit => $foo) {
	  $this->delSection(array("oidsection"=>$oidit));
	}
      }
      return;
    }
    list($oidit,$oiddest,$oidtemplate)=$this->_getOids($oidsection);
    $lang = $p->get('LANG_DATA');
    if(!empty($oiddest)) {
      $k = new \Seolan\Core\Kernel;
      $k->data_forcedDel(array("oid"=>$oiddest, "action"=>"OK","_selectedok"=>"nok"));
      if($lang == TZR_DEFAULT_LANG) {
	getDB()->execute("delete FROM ".$this->tname." WHERE ITOID=?", array($oidsection));
      }
    } else {
      getDB()->execute("delete FROM ".$this->tname." WHERE ITOID=?",array($oidsection));
    }
    $this->_reorderSections($oidit);
    $this->updatePageCache($oidit);
  }
  /// sauvegarde du contenu d'une section
  function savesection($ar) {
    $p = new \Seolan\Core\Param($ar, array("level"=>1,"tplentry"=>"it","toc"=>"1","maxlevel"=>1));
    if($p->is_set('procEditAllLang')) {
      $this->procEditAllLang($ar);
      $p = new \Seolan\Core\Param($ar, array("level"=>1,"tplentry"=>"it","toc"=>"1","maxlevel"=>1));
    }
    $langs=$p->get('_langs');
    $oidsection=$p->get("oidsection");
    list($oidit,$oiddest,$oidtemplate)=$this->_getOids($oidsection);

    // verification des droits sur la page
    if(!$this->secure($oidit, ':rw')) return;

    $lang = $p->get('LANG_DATA');
    // Traitement des cas ou l'on veut sauver dans plusieurs langues
    // _langs == 'all' => bouton sauver dans toutes les langues
    $ar['_langs'] = $this->getAuthorizedLangs($langs,$oidit,'savesection');
    // ajout des paramètres de replication selective de langues
    if (false !== ($langrepli = $this->getLangsRepli($oidit, $lang))){
      $ar['_langspropagate'] = $langrepli;
    }

    $xst = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$oiddest);
    $ar2=$ar;
    $ar2['tplentry']=TZR_RETURN_DATA;
    $ar2['oid']=$oiddest;
    $xst->procEdit($ar2);
    $setup=$p->get("setup");
    getDB()->execute("UPDATE ".$this->tname." set KOIDTPL=? where ITOID=?",array($setup,$oidsection));
    $this->updatePageCache($oidit);
  }

  /// signale au cache de page que la page n'est plus valide
  function updatePageCache($oidit=NULL, $alias=NULL) {
    // traitement du cache de pages
    if(\Seolan\Core\System::tableExists('_PCACHE') && ($cache=\Seolan\Core\Module\Module::singletonFactory(XMODCACHE_TOID))) {
      if(empty($alias) && !empty($oidit)) $alias = $this->getAliasFromOid($oidit);
      $aliases=array();
      if(!empty($oidit) && !empty($this->linkin)) {
	$aliases=getDB()->fetchCol('SELECT distinct alias FROM '.$this->table.' WHERE '.$this->linkin.' = ?',array($oidit));
      }
      if(!empty($alias)) $aliases[]=$alias;
      foreach($aliases as $alias) {
	$cache->clean($alias);
      }
    }
  }

  function dupsection($ar) {
    $p = new \Seolan\Core\Param($ar, array("level"=>1,"tplentry"=>"it","toc"=>"1","maxlevel"=>1));
    $oidsection=$p->get("oidsection");
    list($oidit,$oiddest,$oidtemplate)=$this->_getOids($oidsection);
    $lang = $p->get('LANG_DATA');

    $xst = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$oiddest);
    $r=$xst->procEditDup(array("oid"=>$oiddest,"tplentry"=>TZR_RETURN_DATA));
    $setup=$p->get("setup");
    $koiddup=$r['oid'];
    $n=self::newItOid();
    getDB()->execute("INSERT INTO ".$this->tname." set KOIDTPL=?,KOIDSRC=?,KOIDDST=?,ITOID=?",array($setup,$oidit,$koiddup,$n));
    $this->updatePageCache($oidit);
  }

  function editCat($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    if(empty($ar['fmoid']))
      $ar['fmoid']=$this->_moid;
    if($this->isReadyForMultiZone()){
      if($this->isAModel($oid)) $ar['fieldssec']['model']='none';
      else $ar['fieldssec']['tpl']='none';
    }
    $r2=$this->_categories->edit($ar);
    if(!empty($this->cattemplate)) {
      $r1=$this->_templates->display(array('tplentry'=>TZR_RETURN_DATA,'_options'=>array('error'=>'return'),'oid'=>$this->cattemplate));
      \Seolan\Core\Shell::toScreen1('cat_mitt',$r1);
    }
    if($this->_categories->fieldExists('tpl')) \Seolan\Core\Shell::toScreen1('tple',$this->_templatesList(NULL,'page_site',$oidit));
    if(\Seolan\Core\Shell::admini_mode() && $this->object_sec) {
      $acl=$GLOBALS['XUSER']->listObjectAccess($this, \Seolan\Core\Shell::getLangData(),$oid);
      $r2=array_merge($r2, $acl);
      $sec=$GLOBALS['XUSER']->getObjectAccess($this, \Seolan\Core\Shell::getLangData(),$oid);
      $sec=array_flip($sec[0]);
      $r2['object_sec']=$sec;
    }
    if ($this->linkin)
      $r2['linkin']=$this->linkin;
    if (\Seolan\Core\Shell::admini_mode()) {
      list($r2['_publishLevel']) = $this->secGroups('publish');
      // langues possibles pour enregistrement
      if (($translatable = $this->xset->getTranslatable()) && TZR_DEFAULT_LANG == \Seolan\Core\Shell::getLangData()){
        $r2['authorized_languages'] = $this->getAuthorizedLangs('all', $oid, 'edit');
        $r2['langsort']=\Seolan\Core\Lang::getCodes(NULL,true);
      }
    }

    // gestion des langues si configurées
    $r2['_langrepli'] = $this->categoryLangrepliList($oid, true);
    if (isset($r2['_langrepli']['langdata_updateothers_message'])){
      setSessionVar('message', getSessionVar('message').' '.$r2['_langrepli']['langdata_updateothers_message']);
    }
    if (isset($r2['_langrepli']['langdata_updatedbyother_message'])){
      setSessionVar('message', getSessionVar('message').' '.$r2['_langrepli']['langdata_updatedbyother_message']);
    }

    return \Seolan\Core\Shell::toScreen1($tplentry, $r2);
  }

  function editpage($ar=NULL) {
    return $this->viewpage($ar);
  }

  /**
   Genère une liste des section classer par date de maj pour génération d'un rss
   oids : Array : tableau d'oid des rubrique a traiter
   return : Array :tableau d'element rss title,txt,image et date
  */
  public function &rss($ar=NULL)  {
    $p = new \Seolan\Core\Param($ar);
    $tplentry = $p->get('tplentry');
    if( empty($tplentry)) $tplentry = 'rss';
    $oids = $p->get('oids');
    if(!is_array($oids)){
      $oids = array($oids);
    }
    $res = array();
    $res['descr'] = '';
    $res['title'] = '';
    $res['items'] = array();
    foreach($oids as $k=>$v){
      $rub = $this->viewpage(array('oidit'=>$v,'alias'=>''));
      //dissocier le faite qu'il y ai 1 ou plusieurs rubriques
      if(count($oids) == 1) {
	$res['descr'] = strip_tags($rub['cat_mit']['odescr']->html);
	$res['title'] = $rub['cat_mit']['otitle']->html;
      }else{
	$res['descr'] .= $rub['cat_mit']['title']." ";
	$res['title'] = \Seolan\Core\Ini::get('societe');
      }
      $res['UPD'] = '';
      foreach($rub['olines'] as $key => $section){
	$tk = $section['oUPD']->raw.'_'.$key;

	$res['items'][$tk] = array();
	$res['items'][$tk]['pubDate'] = date("D, d M Y H:i:s O", strtotime($section['oUPD']->raw));
	$res['items'][$tk]['UPD'] =  $section['UPD'];
	if($section['oUPD']->raw > $res['UPD']) $res['UPD'] = $section['oUPD']->raw;
	$res['items'][$tk]['oid'] = $section['oid'];
	$res['items'][$tk]['rub'] = $rub['cat_mit']['oid'];
	if($section['odescription']->raw){
	  $res['items'][$tk]['description'] = $section['description'];
	  $res['items'][$tk]['odescription'] = $section['odescription'];
	}
	foreach($section['fields_object'] as $obj){
	  if($obj->fielddef->ftype == '\Seolan\Field\ShortText\ShortText' && empty($res['items'][$tk]['title']) && !empty($section['o'.$obj->field]->raw)){
	    $res['items'][$tk]['title'] = $section['o'.$obj->field]->html;
	    $res['items'][$tk]['otitle'] = $section['o'.$obj->field];
	  }elseif(($obj->fielddef->ftype == '\Seolan\Field\Text\Text' || $obj->fielddef->ftype == '\Seolan\Field\RichText\RichText') && empty($res['items'][$tk]['description']) && !empty($section['o'.$obj->field]->raw) ){
	    $res['items'][$tk]['description'] = $section['o'.$obj->field]->html;
	    $res['items'][$tk]['odescription'] = $section['o'.$obj->field];
	  }elseif($obj->fielddef->ftype == '\Seolan\Field\Image\Image' && empty($res['items'][$tk]['img']) && !empty($section['o'.$obj->field]->raw) ){
	    $res['items'][$tk]['img'] = $section['o'.$obj->field]->html;
	    $res['items'][$tk]['oimg'] = $section['o'.$obj->field];
	  }
	};
      }
    }
    rsort($res['items']);

    return \Seolan\Core\Shell::toScreen1($tplentry,$res);
  }

  /** Browse les paragraphes d'une rubrique, tries selon leur numero d'ordre.
   Pour chaque paragraphe, display son contenu (document).
   Produit les structures suivantes :
   olines[indice paragraphe] = array produit par le display du contenu (document/thumb/photo/fichier).
   tlines[indice paragraphe] = template de visualisation du contenu (document/thumb/photo/fichier).
  */
  public function &viewpage($ar=NULL)  {
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>'it','toc'=>true,'maxlevel'=>1,'nodecontent'=>true,'_nav'=>true,'_linkin'=>true));
    $tplentry=$p->get('tplentry');
    $alias=$p->get('alias');
    $erroralias=$ar['erroralias']=$p->get('erroralias');
    $defaultalias=$p->get('defaultalias');
    $authalias=$p->get('authalias');
    if(empty($erroralias)) $erroralias=$ar['erroralias']=$defaultalias;
    if(empty($authalias)) $authalias=$erroralias;
    $oidit=$p->get('oidit');
    $linkin=$p->get('_linkin');
    $maxlevel=$p->get('maxlevel');
    $toc=$p->get('toc');

    \Seolan\Core\Logs::debug('\Seolan\Module\InfoTree\InfoTree::viewpage: start '.$oidit.' ('.$alias.')');

    $result=\Seolan\Core\Shell::from_screen($tplentry);
    $result['total']=0;
    $result['can_add_section']=false;

    // Si pas d'alias ni d'oidit, on prend la valeur par défaut
    if(empty($alias) && empty($oidit)) {
      $alias=$defaultalias;
      \Seolan\Core\Logs::debug('\Seolan\Module\InfoTree\InfoTree::viewpage: falling back to '.$defaultalias);
    }
    // Si pas tjs pas d'alias et que oidit n'est pas un oid, on considère qu'il s'agit d'un alias
    if(empty($alias) && !\Seolan\Core\Kernel::isAKoid($oidit)) {
      $alias=$oidit;
      $oidit='';
    }
    // Récupère l'oidit correspondant à l'alias
    if(!empty($alias)) {
      $oidit=$this->getOidFromAlias($alias);
    }
    // Dans le cas ou le contenu de la rubrique est celui d'une autre, recherche du lien
    if($linkin) {
      $result['baseoidit'] = $oidit;
      $oidit=$this->_followLinkIn($oidit);
    }
    // Vérification dans le cas ou il y a un aliastop
    $aliastop=$p->get('pagetop');
    if(!empty($aliastop)) {
      $navig=$this->getPath($oidit);
      $aliasdown=$navig['aliasdown'];
      if(!in_array($aliastop,$aliasdown)) return $this->redirect404($ar);
    }
    // Récupération de la langue en cours
    $LANG_DATA = \Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));
    // Vérification des droits sur la rubrique
    $moid=$this->_moid;
    if(empty($this->object_sec) || $this->secure($oidit,'viewpage',null,$LANG_DATA)) {
      debug('XModinfoTree:auth:ok |viewpage|'.$this->_moid.'|'.$LANG_DATA.'|'.$oidit.'| user'.\Seolan\Core\User::get_current_user_uid());
    } else {
      \Seolan\Core\Logs::critical('XModinfoTree:auth:access denied |viewpage|'.$moid.'|'.$LANG_DATA.'|'.$oidit.'| user '.\Seolan\Core\User::get_current_user_uid().' redirect : '.$authalias);
      \Seolan\Core\Logs::notice(__METHOD__, "alias : $alias $erroralias $authalias");
      // Si on as pas d'oidit c'est une 404
      if (!$oidit) {
        return $this->redirect404($ar);
      }
      $ar['alias']=$authalias;
      return $this->viewpage($ar);
    }
    // Informations de la page
    $ar['oidit']=$oidit;
    $page=$this->_disp2Cat($oidit, $ar);
    $result['cat_mit']=&$page;
    // Redirection 404 si la page n'existe pas
    if(!\Seolan\Core\Shell::admini_mode() && $alias!='error404' && !is_array($page))
      return $this->redirect404($ar);
    // Redirection autre
    if(!\Seolan\Core\Shell::admini_mode() && !empty($page['oredirmethod']->raw) && $page['oredirmethod']->raw!='content'){
      if($this->linkin && $page['o'.$this->linkin]->raw){
        $this->internalRedirect($page['o'.$this->linkin]->raw,$page['oredirmethod']->raw);
      }elseif(!empty($page['oredirext']->raw)){
        $external_url = $page['oredirext']->raw;
        if (!preg_match("~^(?:f|ht)tps?://~i", $external_url)) {
          $external_url = "http://" . $external_url;
        }
        redirecTo($external_url,$page['oredirmethod']->raw);
      }
    }

    // Template d'affichage du module
    if(!empty($this->cattemplate)) {
      $r1=$this->_disp2Template($this->cattemplate);
      if(is_array($r1)) \Seolan\Core\Shell::toScreen1('cat_mitt',$r1);
    }
    // Menu
    if($toc) {
      $this->home(['oidtop'=>$oidit,'tplentry'=>'ssrubs','do'=>'showtree','maxlevel'=>$maxlevel]);
    }

    // Récupère les sections de chaque zone
    $zones=$page['zones'];
    $lastpageupdate=(object)array('raw'=>0);
    foreach($zones as $zone){
      $ar['zone']=$zone;
      $ar['tplentry']=TZR_RETURN_DATA;
      $result['zones'][$zone]=$this->viewzone($ar);
      $result['total']+=$result['zones'][$zone]['total'];
      if(!empty($result['zones'][$zone]['lastpageupdate']) && $result['zones'][$zone]['lastpageupdate']->raw>$lastpageupdate->raw)
	$lastpageupdate=$result['zones'][$zone]['lastpageupdate'];
      if(@!$result['zones'][$zone]['model_config']['o_not_editable']->valid) $result['can_add_section']=true;
    }
    // Dans l'admin, on recupère aussi les zones qui ne sont pas dans le modèle en cours de la page
    if(\Seolan\Core\Shell::admini_mode()) {
      $uzones=getDB()->fetchCol('select ZONE from '.$this->tname.' where KOIDSRC=?',array($oidit));
      foreach($uzones as $uzone){
        if(in_array($uzone,$zones)) continue;
        $ar['zone']=$uzone;
        $ar['tplentry']=TZR_RETURN_DATA;
        $ar['is_unkown']=true;
        $result['uzones'][$uzone]=$this->viewzone($ar);
      }
    }
    // Si une seul zone et que c'est celle par défaut, on ajoute les infos de la zone directement dans le résultat pour la compatibilité des versions antérieures
    if(count($zones)==1 && isset($result['zones']['default'])){
      $result=array_merge($result,$result['zones']['default']);
    }

    // Met à jour la date de dernière révision si besoin
    if(!empty($result['cat_mit']['olastrevision']) && $lastpageupdate->raw>$result['cat_mit']['olastrevision']->raw){
        getDB()->execute('update '.$this->table.' set UPD=UPD, lastrevision=? where KOID=? and LANG=?',
                         [$lastpageupdate->raw, $oidit, $LANG_DATA]);
    }

    if(\Seolan\Core\Shell::admini_mode()) {
      // Récupération de quelques informations sur les droits
      list($result['_editCat']) = $this->secGroups('editCat');
      list($result['_editLevel']) = $this->secGroups('editpage');
      list($result['_publishLevel']) = $this->secGroups('publish');
      // langues équivalentes et données associées
      $result['_langrepli'] = $this->categoryLangrepliList($oidit, false);
      if (isset($result['_langrepli']['langdata_updatedbyother_message'])){
	  setSessionVar('message', $result['_langrepli']['langdata_updatedbyother_message']);
      }
    }

    $result['is_multi_zone']=$this->isReadyForMultiZone();
    $result['pages_mode']=static::getPageMode();
    $result['moid']=$this->_moid;
    $result['oidit']=$oidit;
    if($p->get('_nav')) $result['nav']=$this->_getNavigInfo($oidit);
    if($p->get('_path')) $result['path']=$this->getPath($oidit);

    \Seolan\Core\Logs::debug('\Seolan\Module\InfoTree\InfoTree::viewpage: end '.$oidit.' ('.$alias.')');
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }

  private function cacheViewZone($topic_id, $zone_id, $viewzone_content) {
    \Seolan\Library\ProcessCache::set('xmodinfotree/viewzone/'.strtolower($topic_id), strtolower($zone_id), $viewzone_content);
  }

  private function getCachedZone($topic_id, $zone_id) {
    return \Seolan\Library\ProcessCache::get('xmodinfotree/viewzone/'.strtolower($topic_id), strtolower($zone_id));
  }

  public function viewzone($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('nodecontent'=>true,'tplentry'=>'zone'));
    $tplentry=$p->get('tplentry');
    $zone=$p->get('zone');
    $oidit=$p->get('oidit');
    $nodecontent=$p->get('nodecontent');
    $is_unkown=$p->get('is_unkown');

    /*
     * L'usage de ->viewpageLineManager, qui travaille avec des variables statiques
     * pose problème: lorsque l'on effectue un ->viewzone à dans un niveau inférieur situé
     * a l'intérieur de ce même viewzone, les variables statiques de ->viewpageLineManager
     * sont manipulés. Ce qui fait que, lorsque le ->viewzone "parent" continue sont travail
     * ces fameuse variables statiques contienne des données qui n'ont rien à voir
     * du tout.
     * Cette partie de code (use_viewzone_independently) est donc un "hack" pour
     * permettre l'utilisation de InfoTree::viewzone sans manipuler les variables
     * statiques utilisés.
     */
    $use_viewzone_independently=$p->get('use_viewzone_independently');
    if ($use_viewzone_independently) {
      $cached_viewpageLineManager = $this->viewpageLineManager('get');
      $cached_o = $cached_viewpageLineManager['o'];
      $cached_t = $cached_viewpageLineManager['t'];
      $cached_itoid = $cached_viewpageLineManager['itoid'];
      $cached_catoid = $cached_viewpageLineManager['catoid'];
      $cached_lastpageupdate = $cached_viewpageLineManager['lastpageupdate'];
    }

    if (($cached_viewzone = $this->getCachedZone($oidit, $zone))) {
      \Seolan\Core\Shell::toScreen2('',$tplentry,$cached_viewzone);
      return $cached_viewzone;
    }

    $LANG_DATA=\Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));
    $total=0;
    $page=$this->_disp2Cat($oidit);
    $result=array();

    \Seolan\Core\Logs::debug('\Seolan\Module\InfoTree\InfoTree::viewzone: start '.$oidit.'->'.$zone);

    // Récupération des config des zones en multizone
    $model=@$page['omodel']->raw;
    if(!$is_unkown && $this->isReadyForMultiZone()) {
      $row=getDB()->fetchRow('select * from '.$this->zonetable.' where zone=? and cat=?',array($zone,$oidit));
      //si pas de config on recup celle de la rubrique donnant le model
      if(!$row && $page['inheritCategoryOid'])
        $row=getDB()->fetchRow('select * from '.$this->zonetable.' where zone=? and cat=?',array($zone,$page['inheritCategoryOid']));
      $zoneoid=@$row['KOID'];
      // Pour l'admin, on prépare un input/edit/display de la config de la zone de la page courante
      if(\Seolan\Core\Shell::admini_mode()) {
        if($zoneoid && $LANG_DATA==TZR_DEFAULT_LANG) $result['config']=$this->_zone->edit(array('oid'=>$zoneoid,'_local'=>true));
        elseif($zoneoid) $result['config']=$this->_zone->display(array('oid'=>$zoneoid,'_local'=>true));
        elseif($LANG_DATA==TZR_DEFAULT_LANG) $result['config']=$this->_zone->input(array('_local'=>true));
      }else{
        if($row) $result['config']=$this->_zone->rDisplay($row['KOID'],$row);
        else $result['config']=array();
      }
      // Config du modele
      if($model){
        $row=getDB()->fetchRow('select * from '.$this->zonetable.' where zone=? and cat=?',array($zone,$model));
        if($row) $result['model_config']=$this->_zone->rDisplay($row['KOID'],$row);
        else $result['model_config']=array();
      }
    }

    // Recupère la liste des sections
    // Si nodecontent (défaut), on récupère les sections de la rubrique en cours, sinon celles de tous ses enfant
    $params=[];
    $params[]=$zone;
    $cond="";
    if(!$this->isReadyForMultiZone() || empty($result['model_config']) || !$result['model_config']['o_not_editable']->valid){
      if($nodecontent) {
        $cond=" AND KOIDSRC=? ";
	$params[]=$oidit;
      }else{
        $oidits=$this->getSubCats($oidit,$LANG_DATA);
        if($oidits){
	  foreach($oidits as $oi) {
	    if($cond!="") $cond.=" OR ";
	    $cond.=" KOIDSRC=? ";
	    $params[]=$oi;
	  }
	  $cond=" AND ( $cond ) ";
	}
      }
      $rs=getDB()->select("SELECT * FROM {$this->tname} WHERE ZONE=? $cond ORDER BY ORDER1", $params);
    }else{
      $rs=NULL;
    }

    // Parcours les sections
    $this->viewpageLineManager('init');
    while($rs && $ors=$rs->fetch()){
      // Gestion des droits par section
      if ($this->section_sec) {
        $langData = \Seolan\Core\Shell::getLangData();

        $hasReadOnly = $this->secure($ors['ITOID'], ':ro', $GLOBALS['XUSER']->_curoid, $langData);

        if (!$hasReadOnly && !\Seolan\Core\Shell::admini_mode()) {
          \Seolan\Core\Logs::debug(__METHOD__.' no rights on section '.$ors['ITOID']);
          continue;
        }
      }
      \Seolan\Core\Logs::debug(__METHOD__.' section '.$ors['ITOID']);

      // Récupère les infos du template
      $tpldata=$this->_disp2Template($ors['KOIDTPL']);
      if(empty($tpldata)){
        $this->delSection(array('oidsection'=>$ors['ITOID'],'_local'=>true));
        return NULL;
      }

      // Teste la source
      if(!empty($tpldata['otab']->raw)){
        $source=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$tpldata['otab']->raw);
        if(!is_object($source)){
          $this->delSection(array('oidsection'=>$ors['ITOID'],'_local'=>true));
          return NULL;
        }
      }

      $omodidd = $tpldata['omodidd']->raw;
      if(!empty($omodidd)){
        $source=\Seolan\Core\Module\Module::objectFactory($omodidd);
        if(!is_object($source)){
          $this->delSection(array('oidsection'=>$ors['ITOID'],'_local'=>true));
          return NULL;
        }
      }
      // Si le template n'est pas affichable dans cette zone on ne l'affiche pas en front-office
      if(!\Seolan\Core\Shell::admini_mode() && !empty($tpldata['ozones']->rawcollection) && !in_array($zone, $tpldata['ozones']->rawcollection)){
        continue;
      }

      // Traitement de la section
      switch($tpldata['ogtype']->raw){
        case 'function':
          $ots=$this->viewFunction($p,$oidit,$ors['KOIDTPL'],$ors['ITOID'],$ors['KOIDDST']);
          // Si pas de droit et qu'on est sur la page d'edition des sections, on remplace le template par le template affichant un message d'information
          if($ots===false && \Seolan\Core\Shell::admini_mode() && \Seolan\Core\Shell::getTemplate()=='Module/InfoTree.viewpage.html'){
            $this->viewpageLineManager('add',$oidit,$ors['ITOID'],array('_functionparams'=>1),$this->_disp2Template('TEMPLATES:UNAUTH'));
          }
          break;
        case 'query':
          $ots=$this->viewQuery($p,$oidit,$ors['KOIDTPL'],$ors['ITOID'],$ors['KOIDDST']);
          break;
        default:
          $ots=$this->viewStatic($p,$oidit,$ors['KOIDTPL'],$ors['ITOID'],$ors['KOIDDST']);
          break;
      }

      // Ajouts des sections dans la liste
      if(!is_array($ots)) continue; // La condition précédente vérifie NULL, mais $ots peux aussi valoir false (voir viewSection)
      foreach($ots as &$ot){
        $this->viewpageLineManager('add',$oidit,$ors['ITOID'],$ot,$tpldata);
        $total++;
      }
    }

    // Récupère les lignes dans le contexte courant
    extract($this->viewpageLineManager('get'));

    // Ajout des packs requis par les TEMPLATES au header
    foreach ($t as $template) {
      if (empty($template['opacks']->raw)) continue;
      $packs_to_autoload = preg_split('/[^\w\\\\]+/', $template['opacks']->raw);
      foreach ($packs_to_autoload as $packname) {
        $GLOBALS['TZR_PACKS']->addNamedPack($packname);
      }
    }

    // Héritage de contenu
    if($this->isReadyForMultiZone() && $model){
      if(!empty($result['model_config']) && $result['model_config']['o_not_editable']->valid) $inherit='add_before';
      else if(!empty($result['config'])) $inherit=$result['config']['oinherit']->raw;
      if($inherit && $inherit!='none'){
        $ar2=$ar;
        $ar2['oidit']=$model;
        $ar2['parent_oidit']=$oidit;
        $inherit_data=$this->viewzone($ar2);
        $total+=$inherit_data['total'];
        if($inherit=='add_before'){
          $o=array_merge($inherit_data['olines'],$o);
          $t=array_merge($inherit_data['tlines'],$t);
          $itoid=array_merge($inherit_data['itoid'],$itoid);
          $catoid=array_merge($inherit_data['catoid'],$catoid);
        }elseif($inherit=='add_after'){
          $o=array_merge($o,$inherit_data['olines']);
          $t=array_merge($t,$inherit_data['tlines']);
          $itoid=array_merge($itoid,$inherit_data['itoid']);
          $catoid=array_merge($catoid,$inherit_data['catoid']);
        }
      }
    }
    $langstatus = [];
    if (!\Seolan\Core\Shell::getMonoLang()){
      $sections = getDB()->fetchAll("select koiddst from ".$this->tname." where koidsrc = ?",[$oidit]);
      foreach($sections as $k => $s){
        $langstatus[$k] = $this->xset->infoTreeObjectLangStatus($s["koiddst"]);
      }
    }
    // Resultat final
    $result['olines']=$o;
    $result['tlines']=$t;
    $result['itoid']=$itoid;
    $result['catoid']=$catoid;
    $result['oidit']=$oidit;
    $result['name']=$zone;
    $result['total']=$total;
    $result['pages_mode']=static::getPageMode();
    $result['moid']=$this->_moid;
    $result['lastpageupdate']=$lastpageupdate;
    $result['fieldobject']=new \Seolan\Field\Link\Link();
    $result['langstatus']=$langstatus;

    \Seolan\Core\Logs::debug('\Seolan\Module\InfoTree\InfoTree::viewzone: end '.$oidit.'->'.$zone);

    /**
     * Voir le bloc de commentaire utilisant "use_viewzone_independently" plus haut.
     */
    if ($use_viewzone_independently) {
      $this->viewpageLineManager('set',$cached_catoid,$cached_itoid,$cached_o,$cached_t);
    }

    $this->cacheViewZone($oidit, $zone, $result);
    return \Seolan\Core\Shell::toScreen2('',$tplentry,$result);
  }

  protected function viewpageLineManager($action,$catoid=NULL,$itoid=NULL,$ot=NULL,$tpl=NULL){
    static $_o;
    static $_t;
    static $_itoid;
    static $_catoid;
    static $_lastpageupdate;

    if($action=='add'){
      $_o[]=&$ot;
      $_t[]=&$tpl;
      $_itoid[]=$itoid;
      $_catoid[]=$catoid;
      if(!$_lastpageupdate) $_lastpageupdate=$ot['oUPD'];
      elseif(isset($ot['oUPD']) && $ot['oUPD']->raw>$_lastpageupdate->raw) $_lastpageupdate=$ot['oUPD'];
      elseif($ot['_infos']['oUPD']->raw>$_lastpageupdate->raw) $_lastpageupdate=$ot['oUPD'];
    }elseif($action=='get'){
      return array('o'=>&$_o,'t'=>&$_t,'itoid'=>&$_itoid,'catoid'=>&$_catoid,'lastpageupdate'=>$_lastpageupdate);
    }elseif($action=='init'){
      $_o=$_t=$_itoid=$_catoid=array();
      $_lastpageupdate=NULL;
    }elseif($action=='set'){
      $_o=$ot;
      $_t=$tpl;
      $_itoid=$itoid;
      $_catoid=$catoid;
      $_lastpageupdate=$ot['oUPD'];
    }
  }

  static function getPageMode(){
    if(@$_SERVER['REQUEST_METHOD']=='POST' || @$_REQUEST['nocache']=='1'){
      return '&nocache=1';
    }else{
      return '';
    }
  }

  /// Retourne les données d'une section dans une rubrique
  function viewsection($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $tplentry=$p->get('tplentry');
    $oidit=$p->get('oidit');
    $alias=$p->get('alias');
    $oidsection=$p->get('oidsection');
    $LANG_DATA = \Seolan\Core\Shell::getLangData($p->get("LANG_DATA"));
    if(!empty($alias)) $oidit=$this->getOidFromAlias($alias);
    $rs=getDB()->select("SELECT * FROM {$this->tname} WHERE ITOID=? AND KOIDSRC=?", [$oidsection, $oidit]);
    // Verifie que la section est bien dans la page demandée
    if(!$rs->rowCount()) \Seolan\Library\Security::warning("\Seolan\Module\InfoTree\InfoTree::viewsection: trying to display section $oidsection not in page $oidit");
    // Verifie les droits de la page
    if(!$this->secure($oidit,'viewsection',$u=NULL,$LANG_DATA)) {
      \Seolan\Core\Logs::critical('\Seolan\Module\InfoTree\InfoTree::viewsection:access denied viewsection|'.$this->_moid.'|'.$LANG_DATA.'|'.$oidit.'| user '.\Seolan\Core\User::get_current_user_uid());
      die();
    }

    $o=$itoid=$t=$result=array();
    // Infos de la page
    $result['cat_mit']=$this->_disp2Cat($oidit);
    // Infos de la section
    $ors=$rs->fetch();
    $oiditd=$ors['KOIDDST'];
    $oidittpl=$ors['KOIDTPL'];
    $tpl=$this->_disp2Template($oidittpl);
    if(!$tpl) die();
    // Cas d'une section fonction
    if($tpl['ogtype']->raw=='function'){
      $ot=$this->viewFunction($p,$oidit,$oidittpl,$ors['ITOID'],$oiditd);
      if(!$ot){
        \Seolan\Core\Logs::critical('\Seolan\Module\InfoTree\InfoTree::viewsection: section '.$moid.'|'.$LANG_DATA.'|'.$oidsection.'|'.\Seolan\Core\User::get_current_user_uid().' is not viewable');
        die();
      }
      $o[]=$ot[0];
      $itoid[]=$ors['ITOID'];
      $t[]=$tpl;
    }
    $result['oidit']=$oidit;
    $result['itoid']=$itoid;
    $result['oidsection']=$oidsection;
    $result['olines']=$o;
    $result['tlines']=$t;
    $result['moid']=$this->_moid;
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }

  /// Retourne les données d'une section statique
  function viewStatic($p,$oidit,$oidittpl,$itoid,$oiddst){
    $LANG_DATA = \Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));
    $LANG_TRAD = \Seolan\Core\Shell::getLangTrad($p->get('LANG_TRAD'),$p->get('_notrad'));
    $_format = $p->get('_format');
    $ret=array();

    // Récupère la fiche dans sa langue courante et dans sa version traduite si besoin
    $tpldata=$this->_disp2Template($oidittpl);
    if(empty($tpldata)) {
      $this->delSection(array('oidsection'=>$itoid,'_local'=>true));
      return NULL;
    }

    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$tpldata['otab']->raw);
    if(!is_object($xds)) {
      return NULL;
    }
    $ot2=null;
    if(!empty($LANG_TRAD) && ($LANG_TRAD!=$LANG_DATA)) {
      $ot=$xds->display(array('oid'=>$oiddst, 'tplentry'=>TZR_RETURN_DATA,'_format'=>$_format,
                              'LANG_DATA'=>$LANG_TRAD,
                              '_options'=>array('error'=>'return')));
      $ot2=$xds->display(array('oid'=>$oiddst, 'tplentry'=>TZR_RETURN_DATA,
                               'LANG_TRAD'=>'',
                               '_options'=>array('error'=>'return')));
    } else {
      $ot=$xds->display(array('oid'=>$oiddst, 'tplentry'=>TZR_RETURN_DATA,'_format'=>$_format,
                              '_options'=>array('error'=>'return')));
    }
    if(!is_array($ot)) return NULL;

    $ot['_infos']=&$ot;
    $ot['_type']='section';
    $ret[]=&$ot;
    if($ot2){
      $ot2['_infos']=&$ot2;
      $ot2['_type']='section';
      $ret[]=&$ot2;
    }
    // Permet d'appeler une fonction pour surcharger les données statiques
    if (!empty($tpldata['ofunctions']->raw)) {
      $functions = preg_split('/(,|\s)+/',$tpldata['ofunctions']->raw);
      foreach ($functions as $function) {
        if (function_exists($function)) {
          $function($ret, $p, $oidit, $oidittpl, $itoid, $oiddst);
        } else {
          list($classname, $method) = explode('::', $function);
          if (method_exists($classname, $method))
            $classname::$method($ret, $p, $oidit, $oidittpl, $itoid, $oiddst);
        }
      }
    }
    return $ret;
  }

  /// Retourne les données d'une section statique
  function viewQuery($p,$oidit,$oidittpl,$itoid,$oiddst){
    $d=$this->_dyn->display(array('_local'=>true,'oid'=>$oiddst,'tplentry'=>TZR_RETURN_DATA,'_options'=>array('error'=>'return')));
    // Si l'oid n'existe pas, on saute
    if(!is_array($d)) return NULL;
    $query2=$d['oquery']->decodeRaw(true);

    // Récupération de la source
    $tpldata=$this->_disp2Template($oidittpl);
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$tpldata['otab']->raw);
    if(!empty($tpldata['omodidd']->raw)){
      $source=\Seolan\Core\Module\Module::objectFactory($tpldata['omodidd']->raw);
      if($source){
        $mymodfilter=$source->getFilter();
        if($mymodfilter) $query2['_filter']=$mymodfilter;
      }
    }else{
      $source=$xds;
    }

    // Gestion de l'ordre : soit order = champ asc/desc, soit order=champ et _order=asc/desc
    $order=$p->get('order');
    $_order=$p->get('_order');
    $forder=explode(' ',$order);
    $forder=$forder[0];
    if(!empty($forder)){
      if(in_array($forder,$query2['weborderselector'])){
        $query2['order']=$order;
        if(!empty($_order)) $query2['order'].=' '.$_order;
      }
    }
    if(substr($query2['order'],0,6)=='RAND()') $query2['order']='RAND('.rand(1,100000).')';

    // Préparation du formulaire de recherche et execution de la recherche
    $query2['tplentry']=TZR_RETURN_DATA;
    $query2['LANG_DATA']=$LANG_DATA;
    $query2['selectedfields']=array('KOID');
    $query2['first']=0;
    if (empty($query2['pagesize'])) $query2['pagesize']=9999;
    $query2['searchmode']='simple';
    $query2['fmoid'] = $tpldata['omodidd']->raw;
    $dynquery=$xds->prepareQuery($query2);
    $queryres=$xds->procQuery($query2);
    $total=count($queryres['lines_oid']);

    // Préparation des données sur l'ordre
    if(!empty($queryres['order'])) $order=$queryres['order'];
    foreach($query2['weborderselector'] as $f){
      if(!empty($f)) $dynquery['weborderselector'][]=array('field'=>$f,'label'=>&$xds->desc[$f]->label,'selected'=>($f==$order));
    }
    $dynquery['order']=$order;

    // Pas de ligne de résultat
    if(!$total){
      $ot=array('_type'=>'query','_infos'=>$d,'_dynquery'=>$dynquery,'_q'=>0,'_empty'=>true,'_total'=>0);
      return array($ot);
    }

    // Parcours et création du résultat
    $ret=array();
    $boq=true;
    foreach($queryres['lines_oid'] as $q=>$oidt1){
      $ot=$source->display(array(
        'oid'=>$oidt1, 'tplentry'=>TZR_RETURN_DATA,'_format'=>$_format,
        '_options'=>array('error'=>'return'),'_dynquerymode'=>true,
        'ssmoid'=>'all'
      ));
      if(is_array($ot)){
        if($q==$total-1){
          $ot['_eoq']=true;
          $ot['_total']=$total;
          $ot['_dynquery']=$dynquery;
        }
        if($boq) {
          $ot['_boq']=true;
          $ot['_dynquery']=$dynquery;
          $ot['_total']=$total;
          $boq=false;
        }
        $ot['_type']='query';
        $ot['_infos']=$d;
        $ot['_q']=$q;
        $ret[]=$ot;
      }
    }
    return $ret;
  }

  /// Retourne les données d'une section de type fonction
  function viewFunction($p, $oidit,$oidittpl,$itoid,$oiddst){
    $functionDetails=$this->getFullFunctionDetails($oiddst);
    if(!is_array($functionDetails)) return NULL;

    $query=&$functionDetails['_fullquery'];
    $moid=$query['moid'];
    $function=$query['function'];
    $UIParam_function='UIParam_'.$function;
    $UIView_function='UIView_'.$function;
    $module=\Seolan\Core\Module\Module::objectFactory($moid);

    if(method_exists($module, $UIView_function)){
      $query['oidit']=$oidit;
      $query['parent_oidit']=$p->get('parent_oidit');
      $query['itoid']=$itoid;
      $query['zone']=$p->get('zone');
      $query['_options']=array('local'=>1);
      $query['tplentry']=TZR_RETURN_DATA;
      $query["cond"] = $p->get("cond");
      $query['clearrequest'] = $p->get('clearrequest');
      $ot=$module->$UIView_function($query);
      $params=$query;
    }else{
      if(!method_exists($module, $function) || !method_exists($module, $UIParam_function)) return NULL;
      $params=array('oidit'=>$oidit, '_options'=>array('local'=>1),'tplentry'=>true);
      $desc=$module->$UIParam_function();
      foreach($desc as $fn=>&$field){
	if(strpos($fn,'__')===0) $fn2=substr($fn,2);
	else $fn2=$fn;
	if(empty($query[$fn])) continue;
	$params[$fn2]=$query[$fn];
	if($field->multivalued && $field->get_fgender()=='Oid'){
	  $params[$fn2]=explode('||',$params[$fn2]);
	}
      }
      $ot=$module->$function($params);
    }

    // Récupère les options de mise en page liées au TEMPLATE
    $options_values = (object) null;
    $xoptions = $this->getTemplateOptions($oidittpl, $itoid, $options_values);
    if (!is_null($xoptions)) {
      $xoptions->procDialog($options_values, $params['_tploptions']);
      $ot['_tploptions'] = (array) $options_values;
    }

    $ot['_functionparams']=array('function'=>$function,'moid'=>$query['moid'],'params'=>$params,'modulename'=>$module->getLabel(),'module'=>$module);
    $ot['_infos']=$functionDetails;
    $ot['_type']='function';
    return array($ot);
  }

  /// Récupère les infos completes d'une section fonction, c'est à dire un display + le query formé à partir de la langue en cours et de celle par défaut
  function getFullFunctionDetails($oid){
    $d=$this->_dyn->display(array('_local'=>true,'oid'=>$oid,'tplentry'=>TZR_RETURN_DATA,'_options'=>array('error'=>'return')));
    if(!is_array($d)) return NULL;

    $query=$d['oquery']->decodeRaw(true);
    if(!\Seolan\Core\Shell::langDataIsDefaultLanguage()){
      $dt=$this->_dyn->display(array('_local'=>true,'oid'=>$oid,'LANG_DATA'=>TZR_DEFAULT_LANG,'tplentry'=>TZR_RETURN_DATA,'options'=>array('selectedfields'=>array('query'))));
      $queryt=$dt['oquery']->decodeRaw(true);
      if(is_array($queryt)){
        $query=array_merge($queryt,$query);
      }
    }
    $d['_fullquery']=$query;
    return $d;
  }

  // recherche des info rubrique prec, suivant, top, etc...
  //
  protected function &_getNavigInfo($oidit) {
    $result=array();
    $published='';
    if(fieldExists($this->table, 'PUBLISH') && !\Seolan\Core\Shell::admini_mode()) {
      $published=' AND PUBLISH="1" ';
    }
    $q = 'select distinct KOID,linkup,corder from '.$this->table.' where KOID=? '.$published;
    $rs=getDB()->select($q, array($oidit));
    if($ors=$rs->fetch()) {
      $linkup=$ors['linkup'];
      $order=$ors['corder'];

      // recherche du next au meme niveau
      $q = 'select distinct KOID,linkup,corder from '.$this->table.' where '.
	"linkup='$linkup' and corder>'$order' $published order by corder";
      $rs=getDB()->select($q);
      if($ors=$rs->fetch()) {
	$koidnext=$ors['KOID'];
	$r1=$this->_categories->display(array('oid'=>$koidnext,'tplentry'=>TZR_RETURN_DATA,'_options'=>array('error'=>'return'), 'fallback'=>1));
	if(is_array($r1)) $result['next']=$r1;
      }

      // recherche du prev au meme niveau
      $q = 'select distinct KOID,linkup,corder from '.$this->table.' where '.
	"linkup='$linkup' and corder<'$order' $published order by corder";
      $rs=getDB()->select($q);
      if($ors=$rs->fetch()) {
	$koidprev=$ors['KOID'];
	$r1=$this->_categories->display(array('oid'=>$koidprev,'tplentry'=>TZR_RETURN_DATA,
					       'fallback'=>1,
					       '_options'=>array('error'=>'return')));
	if(is_array($r1)) $result['prev']=$r1;
      }

      // recherche du up
      if(strlen($linkup)>1)
	$result['up']=$this->_categories->display(array('oid'=>$linkup,'tplentry'=>TZR_RETURN_DATA,'fallback'=>1,'_options'=>array('error'=>'return')));
    }
    return $result;
  }

  // rend la liste des oid ss categories
  //
  public function &getSubObjects($oid) {
    $stack=array($oid);
    $res=array();
    while(!empty($stack)) {
      $poped=array_pop($stack);
      if($poped!=$oid) array_push($res, $poped);
      $rs=getDB()->fetchCol('SELECT DISTINCT KOID,alias FROM '.$this->table." WHERE linkup=?",array($poped));
      foreach($rs as $ors) array_push($stack, $ors);
      unset($rs);
    }
    return $res;
  }

  // rend la liste des ss categroies
  //
  public function getSubCats($oid, $lang, $level=1, $depth=9, $maxlevel=9) {
    if(!isset($oid)) {
      $query = $this->_categories->select_query(array('cond'=>array('linkup'=>array('=','NULL'))));
    } else {
      $query = $this->_categories->select_query(array('cond'=>array('linkup'=>array('=',$oid))));
    }
    $rcat=$this->_categories->browse(array('selected'=>0,
					    'first'=>'0',
					    'select'=>$query,
					    'pagesize'=>1000,
					    'selectedfields'=>array('title','linkup'),
					    'order'=>'corder',
					    'tplentry'=>TZR_RETURN_DATA,
					    'header'=>'0','nocount'=>'1'));
    $OID[]=$oid;
    foreach($rcat['lines_oid'] as $i => $o) {
      $OID[]=$o;
      if($level < $depth) {
	$subOID = $this->getSubCats($o, $lang, $level+1, $depth);
	$OID=array_merge($OID,$subOID);
      }
    }
    return $OID;
  }
  /// rend la liste des aliases de ce module
  function &getAliases() {
    $lang=\Seolan\Core\Shell::getLangData();
    $set = getDB()->fetchAll('SELECT DISTINCT KOID,alias FROM '.$this->table." WHERE LANG=?", array($lang));
    $setall=array();
    foreach($set as $i=>&$al) {
      if(!empty($al['alias'])) $setall[$al['KOID']]=$al['alias'];
    }
    unset($set);
    return $setall;
  }


  /// rend l'oid correspondant à l'alias
  public function getOidFromAlias($alias) {
    if(\Seolan\Core\Kernel::isAKoid($alias) && \Seolan\Core\Kernel::objectExists($alias)) return $alias;
    if(isset($alias)) {
      $query=$this->_categories->select_query(array('cond'=>array('alias'=>array('=',$alias))));
      $ors=getDB()->fetchRow($query);
      if($ors) return $ors['KOID'];
      else return NULL;
    }
    return NULL;
  }

  /// rend l'oid correspondant à l'alias
  public function getAliasFromOid($oid) {
    if(!\Seolan\Core\Kernel::isAKoid($oid) || !\Seolan\Core\Kernel::objectExists($oid)) return NULL;
    $query=$this->_categories->select_query(array('cond'=>array('KOID'=>array('=',$oid))));
    $ors=getDB()->fetchRow($query);
    if($ors) return $ors['alias'];
    else return NULL;
  }

  /// Duplication d'un module, méthode interne
  /// Retour : duplicatetables => liste des tables dupliquées par le module (cle : ancienne table, valeur : nouvelle table))
  /// Retour : duplicatemods => liste des modules dupliqués par le module (cle : ancien moid, valeur : nouveau moid))
  function _duplicateModule($newmoid,&$params,$prefix) {
    if(!is_array($params['tables'])) $params['tables']=[];
    if(!$params['noduplicatetable']){
      // duplication de la table des rubriques
      if(empty($params['tables'][$this->table]) || is_array($params['tables'][$this->table])){
        if(isset($params['tables'][$this->table]['newtable'])) $ar['newtable']=$params['tables'][$this->table]['newtable'];
	else $ar['newtable']=\Seolan\Model\DataSource\Table\Table::newTableNumber();
	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->table);
        if(isset($params['tables'][$this->table]['mtxt'])) $ar['mtxt']=$params['tables'][$this->table]['mtxt'];
        else $ar['mtxt']=$this->getDuplicateModuleGenerateName(isset($params['table_prefix'])?$params['table_prefix']:$prefix,$xset->getLabel());
	if(isset($params['duplicatetopicsdata'])) $ar['data']=$params['duplicatetopicsdata'];
        else $ar['data']=false;
	$ar['_options']=array('local'=>1);
	$xset->procDuplicateDataSource($ar);
	// correction des champs utilisant la table (rubrique mère, linkin...)
	$xset21=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ar['newtable']);
        foreach($xset21->desc as $fn=>$f){
          if($f->target!=$this->table) continue;
          $noptions=array('field'=>$fn,'_todo'=>'save','target'=>$ar['newtable']);
          if($f->sourcemodule && $f->sourcemodule==$this->moid) $noptions['options']['sourcemodule']=$newmoid;
          $xset21->procEditField($noptions);
          getDB()->execute('update '.$ar['newtable'].' set '.$fn.'=substr(replace('.$fn.',"'.$this->table.':","'.$ar['newtable'].':"), 1, 40)');
        }
        // correction chp redirection
	$params['table']=$ar['newtable'];
      }else{
	$params['table']=$params['tables'][$this->table];
      }

      $new_xds_table_name = $ar['newtable'];

      // duplication de la table des sections dynamiques
      if(empty($params['tables'][$this->dyntable])){
        $ar['newtable']=\Seolan\Model\DataSource\Table\Table::newTableNumber('DYNDATA');
	$xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->dyntable);
        $ar['mtxt']=$this->getDuplicateModuleGenerateName($prefix,$xset->getLabel().'('.$ar['newtable'].')');
	if(isset($params['duplicatetopicsdata'])) $ar['data']=$params['duplicatetopicsdata'];
        else $ar['data']=false;
	$ar['_options']=array('local'=>1);
	$xset->procDuplicateDataSource($ar);
	$params['dyntable']=$ar['newtable'];
      }else{
	$params['dyntable']=$params['tables'][$this->dyntable];
      }
      // duplication des contenus statiques ?
      // duplication de la table des zones
      if ($this->isReadyForMultiZone()) {
        if (empty($params['tables']['ZONE' . $new_xds_table_name]) && $new_xds_table_name) {
          $ar['newtable'] = 'ZONE' . $new_xds_table_name;
          $xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=' . $this->zonetable);
          $ar['mtxt'] = $this->getDuplicateModuleGenerateName($prefix, $xset->getLabel());
          $ar['_options'] = array('local' => 1);
          $xset->procDuplicateDataSource($ar);
          $params['zones'] = $ar['newtable'];
          $xset21=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ar['newtable']);
          foreach($xset21->desc as $fn=>$f){
            if($f->target!=$this->table) continue;
            $noptions=array('field'=>$fn,'_todo'=>'save','target'=>$params['table']);
            if($f->sourcemodule && $f->sourcemodule==$this->moid) $noptions['options']['sourcemodule']=$newmoid;
            $xset21->procEditField($noptions);
            getDB()->execute('update '.$ar['newtable'].' set '.$fn.'=substr(replace('.$fn.',"'.$this->table.':","'.$params['table'].':"), 1, 40)');
          }
        } else {
          $params['zones'] = $params['tables']['ZONE' . $new_xds_table_name];
        }
      }

      // creation de la table des liens
      $ittable=\Seolan\Module\InfoTree\Wizard::createLinkStructure($params['table']);
      // Duplication des sections
      if(!empty($params['duplicatetopicsdata']) && !empty($params['duplicatesectionsdata'])){
        $rs=getDB()->select('select * from '.$this->tname);
        while($ors=$rs->fetch()){
          if($ors['KOIDDST']){
            if(\Seolan\Core\Kernel::getTable($ors['KOIDDST'])!=$this->dyntable){
              $ors['KOIDDST']=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$ors['KOIDDST'])->duplicate(array(
                'oid'=>$ors['KOIDDST'],
                'nolog'=>true,
                'changeown'=>true
              ));
            }else{
              $ors['KOIDDST']=str_replace($this->dyntable.':',$params['dyntable'].':',$ors['KOIDDST']);
            }
          }
          $ors['KOIDSRC']=substr(str_replace($this->table.':',$params['table'].':',$ors['KOIDSRC']), 0, 40);
          getDB()->execute('insert into '.$ittable.' values('.substr(str_repeat('?,',count($ors)),0,-1).')',array_values($ors));
        }
      }
      // duplication des mises en page/gabarits
      $rs=getDB()->select('SELECT KOID FROM TEMPLATES WHERE modid =?',array($this->_moid));
      $xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
      while($rs && $ors=$rs->fetch()) {
	$ardup=array('_options'=>array('local'=>1));
	$ardup['oid']=$ors['KOID'];
	$nkoid=$xset->duplicate($ardup);
	getDB()->execute('UPDATE TEMPLATES set modid=? where KOID=?',array($newmoid,$nkoid));
      }
    }
    return array('duplicatetables'=>array($this->table=>$params['table']),'duplicatemods'=>array());
  }

  /// suppression du module
  function delete($ar) {
    $p=new \Seolan\Core\Param($ar, array('tplentry'=>''));
    $withsections=$p->get('withsections');
    if(!empty($withsections)){
      $rs=getDB()->select('select * from '.$this->tname.' group by KOIDDST');
      while($ors=$rs->fetch()){
        if($ors['KOIDDST']){
          \Seolan\Core\Kernel::data_forcedDel(array('oid'=>$ors['KOIDDST'],'_nolog'=>true));
        }
      }
    }
    // Suppression des templates directement liés au module supprimé
    $nb = getDB()->execute('DELETE FROM TEMPLATES WHERE modid='.$this->_moid);
    if ($nb) {
      \Seolan\Core\Shell::alert(sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','deleted_templates'),$nb));
    }
    // Suppression de la table de zone si elle existe
    if (!empty($this->zonetable)){
      $xbase=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->zonetable);
      $ret=$xbase->procDeleteDataSource();
      \Seolan\Core\Logs::notice(__METHOD__, $ret['message']);
    }
    return parent::delete($ar);
  }

  public function usedTables(){
    return array($this->table,$this->tname,$this->dyntable,'TEMPLATES');
  }
  public function usedMainTables(){
    return array($this->table);
  }
  public function usedBoids(){
    return array($this->_categories->getBoid());
  }
  function goto1($ar=NULL) {
    $p = new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false);
    $moid=$this->_moid;
    header("Location: {$url}moid=$moid&template=Module/InfoTree.viewpage.html&oidit=$oid&function=editpage&tplentry=it");
  }

  function _mkInline(&$r) {
  }

  /// rend vrai si l'alias pass	 en parametre est protege
  public function aliasIsProtected($alias) {
    return false;
  }

  public function export($ar) {
    $r1=$this->home(array('tplentry'=>TZR_RETURN_DATA,'do'=>'showtree'));

    foreach($r1['lines_oid'] as $i=>$oid) {
      $R2=$this->viewpage(array('tplentry'=>TZR_RETURN_DATA, 'oidit'=>$oid));
      $r1['lines__it'][$i]=$R2['olines'];
      $r1['lines__tt'][$i]=$R2['tlines'];
    }
    \Seolan\Core\Shell::toScreen1('mit', $r1);
  }
  public function apply($fsection) {
    $r1=$this->home(array('tplentry'=>TZR_RETURN_DATA,'do'=>'showtree'));

    foreach($r1['lines_oid'] as $oid) {
      $R2=$this->viewpage(array('tplentry'=>TZR_RETURN_DATA, 'oidit'=>$oid));
      $fsection($this, $R2['cat_mit']);	/* conversion de la rubrique */

      foreach($R2['olines'] as $section) {
	$fsection($this, $section);
      }
      unset($R2);
    }
  }

  /**
   * Exporte la page au format PDF
   * @param ar Options de la fonction viewpage avec un paramètre 'action'
   *           supplémentaire qui vaut 'display' par défaut pour renvoyer
   *           directement un fichier à l'utilisateur ou 'generate' pour
   *           seulement générer le fichier et renvoyer son nom.
   * @return String Nom du fichier généré
   */
  public function exportPdf($ar) {
    $p = new \Seolan\Core\Param($ar,array(
      'pagesize' => 999,
      'action'   => 'display',    // 'generate' ou 'display'
      'pdfname'  => 'export.pdf', // Nom du fichier PDF généré
      'tpldata'  => array(),      // Données à transmettre au template
    ));
    $ar['pagesize'] = $p->get('pagesize');
    $ar['tplentry'] = 'it';
    $tpldata = $p->get('tpldata');
    $pdfname = $p->get('pdfname');
    if (!isset($tpldata['it'])) {
      $tpldata['it'] = $this->viewpage($ar);
    }
    if (isset($tpldata['it']) && $pdfname == 'export.pdf' && !empty($tpldata['it']['cat_mit']['oalias']->html)) {
      $pdfname = $tpldata['it']['cat_mit']['oalias']->html.'.pdf';
    }
    $xt = $this->retrieveTemplate($ar);
    $labels = $GLOBALS['XSHELL']->labels->get_labels(array('selectors'=>array('global'),'local'=>true));
    $xt->set_glob(array('labels'=>&$labels));
    $rawData = array('it_prop'=>$this);
    $content = $xt->parse($tpldata,$rawData,NULL);
    $tmpname = princeTidyXML2PDF(null,$content);
    switch ($p->get('action')) {
      case 'generate' :
        $newname = dirname($tmpname).'/'.$pdfname;
        return @rename($tmpname,$newname) ? $newname : $tmpname;
      case 'display' :
	header('Content-type: application/pdf');
	header('Content-disposition: attachment; filename='.$pdfname);
	$size = filesize($tmpname);
	header('Accept-Ranges: bytes');
	header('Content-Length: '.$size);
	readfile($tmpname);
	unlink($tmpname);
	exit(0);
    }
  }

    public function exploreNode($oid, $lang, &$oids, &$all_oids) {
      $rs=getDB()->select("select KOID from ".$this->table." where linkup=? and LANG=? order by corder", array($oid,$lang));
      $ors=$rs->fetchAll();

      if (!in_array($oid, $all_oids)) {
	array_push($oids, $oid);
	array_push($all_oids, $oid);
      }
      if ($ors) foreach($ors as $oidi) $this->exploreNode($oidi['KOID'], $lang, $oids, $all_oids);
    }

  public function exportPdfs($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $p = new \Seolan\Core\Param($ar,array(
      'pagesize' => 999,
      'action'   => 'display',    // 'generate' ou 'display'
      'pdfname'  => 'export.pdf', // Nom du fichier PDF généré
      'tpldata'  => array()      // Données à transmettre au template
    ));
    $action = $p->get('action');
    $tpldata = $p->get('tpldata');
    $sel=$p->get('_selected'); // files selected
    $i = 0;
    $lang = \Seolan\Core\Shell::getLangUser();
    $all_oids = [];
    foreach($sel as $oid => $bid) {
      $oids =[];
      $this->exploreNode($oid, $lang, $oids, $all_oids);
      foreach($oids as $oidi) {
	if (!isset($tpldata['it'])) {
	  $ar['tplentry'] = TZR_RETURN_DATA;
	  $ar['oidit'] = $oidi;
	  $tpldata[$i]['it'] = $this->viewpage($ar);
	  $i++;
	}
      }
    }
    $ar['pagesize'] = $p->get('pagesize');
    $ar['tplentry'] = 'it';
    $pdfname = $p->get('pdfname');
    if (isset($tpldata[0]['it']) && $pdfname == 'export.pdf' && !empty($tpldata[0]['it']['cat_mit']['oalias']->html)) {
      $pdfname = $tpldata[0]['it']['cat_mit']['oalias']->html.'.pdf';
    }
    $content = [];
    for ($j = 0; $j < $i; $j++) {
      $xt = $this->retrieveTemplate($ar);
      $labels = $GLOBALS['XSHELL']->labels->get_labels(array('selectors'=>array('global'),'local'=>true));
      $xt->set_glob(array('labels'=>&$labels));
      $rawData = array('it_prop'=>$this);
      $contenti= $xt->parse($tpldata[$j],$rawData,NULL);
      array_push($content,$contenti);
    }
    $tmpname = princeTidyXML2PDF(null,$content, NULL, NULL, true);
    switch ($action) {
    case 'generate' :
      $newname = dirname($tmpname).'/'.$pdfname;

      return @rename($tmpname,$newname) ? $newname : $tmpname;
    case 'display' :
      header('Content-type: application/pdf');
      header('Content-disposition: attachment; filename='.$pdfname);
      $size = filesize($tmpname);
      header('Accept-Ranges: bytes');
      header('Content-Length: '.$size);
      readfile($tmpname);
      unlink($tmpname);
      exit(0);
    }
  }

  private function retrieveTemplate($ar) {
    if (isset($ar["template"]) && $ar["template"] != null) {
      return new \Seolan\Core\Template($ar["template"]);
    } else {
      return new \Seolan\Core\Template(TZR_SHARE_DIR.'Module/InfoTree.exportPdf.html');
    }
  }

  public function &browse($ar) {
    return $this->_categories->browse($ar);
  }
  public function &query($ar) {
    return $this->_categories->query($ar);
  }
  public function &procQuery($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $ar1=$ar;
    $ar1['tplentry']=TZR_RETURN_DATA;
    $ar1['selectedfields']=array('UPD','PUBLISH','title','alias');
    $r=$this->_categories->procQuery($ar1);
    $r['objects_sec']=$GLOBALS['XUSER']->getObjectsAccess($this, \Seolan\Core\Shell::getLangData(), $r['lines_oid']);
    if($tplentry!=TZR_RETURN_DATA) {
      $this->browse_actions($r);
    }
    return \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }

  public function browse_actions(&$r) {
    $self=$GLOBALS['TZR_SESSION_MANAGER']::complete_self();
    $self.='&moid='.$this->_moid.'&oidit=<oid>&tplentry=it&function=';
    if(!is_array($r['lines_oid'])) return;
    $approved=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','approved');
    $not_approved=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','not_approved');
    $viewico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','view');
    $viewtxt = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','view','text');
    $editico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit');
    $edittxt = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit','text');
    $browseico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','browse');
    $browsetxt = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','browse','text');
    foreach($r['lines_oid'] as $i =>$oid) {
      $self1=str_replace('<oid>',$oid,$self);
      $self1=str_replace('=it','=mit',$self1);
      $self1=str_replace('oidit','oid',$self1);
      $r['actions'][$i][0]='<a class="cv8-ajaxlink" href="'.$self1.'home&'.
	'template=Module/InfoTree.index.html&do=add" title="'.$browsetxt.'">'.$browseico.'</a>';
      $r['actions_label'][$i][0]=$viewico;
      $r['actions_url'][$i][0]=$self1.'home&template=Module/InfoTree.index.html&do=add';
      $self1=str_replace('<oid>',$oid,$self);
      $r['actions'][$i][1]='<a class="cv8-ajaxlink" href="'.$self1.'viewpage&'.
	'template=Module/InfoTree.viewpage.html" title="'.$viewtxt.'">'.$viewico.'</a>';
      $r['actions_label'][$i][1]=$viewico;
      $r['actions_url'][$i][1]=$self1.'viewpage&template=Module/InfoTree.viewpage.html';
      if(!empty($r['objects_sec'][$i]['rw'])) {
	$r['actions'][$i][2]='<a class="cv8-ajaxlink" href="'.$self1.'editpage&'.
	  'template=Module/InfoTree.viewpage.html" title="'.$edittxt.'">'.$editico.'</a>';
	$r['actions_url'][$i][2]=$self1.'editpage&template=Module/InfoTree.editpage.html';
	$r['actions_label'][$i][2]=$editico;
      }
    }
  }

  /// rend la date de derniere mise a jour de la page dont l'oid est fourni
  protected function lastUpdate($oid) {
    $ors=getDB()->fetchRow('SELECT UPD,KOID FROM '.$this->table.' WHERE KOID="'.$oid.'" ORDER BY UPD DESC LIMIT 1');
    if(!$ors) return NULL;
    $lastupd=$ors['UPD'];
    $lastoid=$ors['KOID'];

    // recherche de toutes les sections de la page
    $all=getDB()->fetchCol('SELECT KOIDDST FROM '.$this->tname.' where KOIDSRC=? AND KOIDDST != ?',
			   array($oid,''));
    foreach($all as $koiddst) {
      // recherche de la derniere mise a jour pour cette section
      $upd=\Seolan\Core\Logs::getLastUpdate($koiddst, NULL, true);

      if(!empty($upd) && ($upd['dateupd']>$lastupd)) {
	$lastupd=$upd['dateupd'];
	$lastoid=$koiddst;
      }
    }
    unset($all);

    $update=\Seolan\Core\Logs::getLastUpdate($lastoid, NULL, true);
    return $update;
  }

  protected function _lasttimestamp() {
    $rs=getDB()->select('SELECT MAX(UPD) AS UPD FROM '.$this->table.'');
    $ors=$rs->fetch();
    $lastupd=$ors['UPD'];
    $rs=getDB()->select('SELECT DISTINCT KOIDDST FROM '.$this->tname.' WHERE KOIDDST != ""');
    $tables=array();
    while($rs && ($ors=$rs->fetch())) {
      $tables[]=\Seolan\Core\Kernel::getTable($ors['KOIDDST']);
    }
    $tables_u=array_unique($tables);
    foreach($tables_u as $table) {
      if(empty($table)) continue;
      $ors=getDB()->fetchRow('SELECT MAX('.$table.'.UPD) AS UPD FROM '.$table.','.$this->tname.' WHERE '.
			     $table.'.KOID='.$this->tname.'.KOIDDST');
      if(!empty($ors) && ($lastupd < $ors['UPD'])) $lastupd=$ors['UPD'];
    }
    return $lastupd;
  }

  /// rend la liste des rubriques modifiees depuis ts et jusqu'a timestamp
  protected function _whatsNew($ts,$user, $group=NULL, $specs=NULL,$timestamp=NULL) {
    $koid=$specs['oid'];
    $subdir=$specs['recursive'];

    // recherche dans lines_oid de la liste des rubriques concernees par l'abonnement
    if($subdir) $r1=$this->home(array('tplentry'=>TZR_RETURN_DATA,'do'=>'showtree','myself'=>true,'oidtop'=>$koid));
    else  $r1=array('lines_oid'=>array($koid));
    // recherche de la date de derniere mise a jour pour cahcune des rubriques cocnernees
    $r1['lines_upd']=array();
    foreach($r1['lines_oid'] as $i=>$oid) {
      $r1['lines_upd'][$i]=$this->lastUpdate($oid);
    }
    $txt='';
    foreach($r1['lines_oid'] as $i => $oid) {
      if(($r1['lines_upd'][$i]['dateupd']>$ts) && ($r1['lines_upd'][$i]['dateupd']<$timestamp)) {
	$url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'&moid='.$this->_moid.'&function=goto1&oid='.$oid.'&tplentry=br&template=Module/Table.view.html&_direct=1';
	$when=$r1['lines_upd'][$i]['dateupd'];
	$who=$r1['lines_upd'][$i]['usernam'];
	$title=$this->getPathString($oid);
	$txt.='<li><a href="'.$url.'">'.$title.'</a> ('.$when.','.$who.')</li>';
      }
    }
    return $txt;
  }
  function preSubscribe($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get("oid");
    $tplentry=$p->get("tplentry");
    $subdir=$p->get("subdir");
    $br=$this->viewpage(array('tplentry'=>TZR_RETURN_DATA, 'oidit'=>$oid));

    list($acl_user, $acl_grp)=\Seolan\Core\User::getUsersAndGroups();
    \Seolan\Core\Shell::toScreen1('users',$acl_user);
    \Seolan\Core\Shell::toScreen1('grps',$acl_grp);
    return \Seolan\Core\Shell::toScreen1($tplentry, $br);
  }
  function subscribe($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get("oid");
    $subdir=$p->get("subdir");
    $uid=$p->get("uid");

    if(empty($uid)) $uid=\Seolan\Core\User::get_current_user_uid();
    if(empty($subdir)) $subdir="0";else $subdir="1";
    $xmodsub = new \Seolan\Module\Subscription\Subscription(array('interactive'=>false));
    $xmodsub->addSub(array($uid), $this->_moid, array('oid'=>$oid, 'recursive'=>$subdir));
  }

  // rend une chaine qui représente l'abonnement
  //
  function _getSubTitle($oid) {
    $ors=getDB()->fetchRow('select * from OPTS where KOID=?', array($oid));
    if(!empty($ors)) {
      $specs=\Seolan\Library\Opts::json_decode($ors['specs']);
      $koid=$specs['oid'];
      $subdir=$specs['recursive'];
      if(\Seolan\Core\Kernel::objectExists($koid)) {
	// generation du libelle pour la rubrique
	$title=$this->getPathString($koid);
	$url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&_function=goto1&oid='.$koid.'&tplentry=br';
	if(!empty($subdir)) {
	  $subdir=' et sous rubriques';
	} else $subdir='';
	return '<A HREF="'.$url.'">'.$title.'</A> '.$subdir;
      } else {
	// l'abonnement concerne une donnee qui n'existe plus. on le supprimer
	getDB()->execute('delete from OPTS where KOID=?',array($oid));
      }
    }
    return NULL;
  }

  /// Prepare l'importation d'un fichier odt
  function preImportFromODT($ar=NULL){
  }

  /// importe un fichier odt
  function importFromODT($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $linkup=$p->get('linkup');
    $rep=TZR_TMP_DIR.'odt_'.uniqid().'/';
    mkdir($rep);
    mkdir($rep.'in/');
    mkdir($rep.'out/');
    rename($_FILES['odtfile']['tmp_name'],$rep.'in/odtfile.odt');

    \Seolan\Core\System::loadVendor('odt2xhtml/odt2seolan.php');
    $xhtml2seolan = new \xhtml2seolan(
      $this,
      $rep, 'odtfile.odt',
      array('txt' => array(
        'tploid' => $this->odttpltxt,
        'txtfield' => $this->odttxtfield,
        'titlefield' => $this->odttxttitlefield),
            'img' => array(
              'tploid' => $this->odttplimg,
              'imgfield' => $this->odtimgfield)),
      $linkup
    );
    $xhtml2seolan->import();
    \Seolan\Library\Dir::unlink($rep);
    \Seolan\Core\Shell::toScreen2('','message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','odt_importok','text'));
  }

  /// Définit si le cache des droits doit être activé ou pas.
  public function rightCacheEnabled(){
    if($this->object_sec) return true;
    return false;
  }

  /// Retourne le parent direct de chaque oid passé en paramètre
  public function getParentsOids($oids){
    $ret=array();
    foreach($oids as $oid){
      if(!$oid) continue;
      $parent=getDB()->fetchRow('select linkup from '.$this->table.' WHERE KOID=? limit 1', array($oid));
      if(!$parent || !$parent['linkup']) $ret[]='';
      else $ret[]=$parent['linkup'];
    }
    return $ret;
  }

  /// Redirige vers une page d'erreur 404
  function redirect404($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $erroralias=$p->get('erroralias');
    $alias=$p->get('alias');
    header("HTTP/1.1 404 Not Found");
    if(empty($erroralias) || ($alias==$erroralias)) {
      exit(0);
    } else {
      $ar['alias']=$erroralias;
      $ar['oidit']=NULL;
      return $this->viewpage($ar);
    }
  }

  /// Redirection sur une page du module
  function internalRedirect($oid,$code='302') {
    if(\Seolan\Core\Kernel::isAKoid($oid)){
      $alias=getDB()->fetchOne('select alias from '.$this->table.' where lang=? and KOID=?', array(TZR_DEFAULT_LANG, $oid));
    }

    $lang = \Seolan\Core\Shell::getLangUser();
    $url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'alias='.$alias.'&_lang='.$lang;

    $rewriter = $GLOBALS['XSHELL'];
    if (TZR_USE_APP) {
      $bootstrapApplication = \Seolan\Module\Application\Application::getBootstrapApplication();
      if ($bootstrapApplication != null){
        $rewriter = $bootstrapApplication->getRewriter()??$rewriter;
      }
    }

    if(!\Seolan\Core\Shell::admini_mode() && \Seolan\Core\Ini::get('url_rewriting')) {
      $rewriter->encodeRewriting($url);
    }

    redirecTo($url, $code);
  }

  public function pagesUsingModel($ar){
    $p=new \Seolan\Core\Param($ar);
    $model=$p->get('model');
    $ar['_filter']="model=\"$model\"";
    $this->_categories->browse($ar);
  }

  /// Retourne l'oid de la rubrique des modeles
  public function getModelsOid(){
    return $this->table.':MODELS';
  }

  /// Retourne vrai si la page est un modele
  public function isAModel($oid){
    return in_array($this->getModelsOid(),$this->_getPathOids($oid));
  }

  /// Permet au module d'être en multizone
  function convertToMultiZone(){
    \Seolan\Module\InfoTree\Wizard::convertToMultiZone($this);
    setSessionVar('message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','multizoneactivated'));
  }

  /// Sauve la config d'une zone
  function procEditZoneConfig($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $oid=$p->get('oid');
    if($oid){
      $this->_zone->procEdit($ar);
    }else{
      $this->_zone->procInput($ar);
    }
  }

  /// Traite les paramètres pour édition d'une fonction UI
  static function editUIParam(&$desc,&$ar){
    $ret=array();
    foreach($desc as $fn=>$f){
      if(empty($ar[$fn])){
        $value = $f->default;
      }elseif(is_array($ar[$fn])){
        $value = implode('||',$ar[$fn]);
      }else{
        $value = $ar[$fn];
      }
      $f->__options['fmoid'] = $ar['fmoid'];
      if($f->translatable || \Seolan\Core\Shell::langDataIsDefaultLanguage())
	$o = $f->edit($value, $f->__options);
      else
	$o = $f->display($value, $f->__options);
      $ret['o'.$fn] = $ret['fields_object'][] = $o;
    }
    return $ret;
  }

  /// Traite les paramètres pour sauvegarde d'une fonction UI
  static function postEditUIParam(&$desc,&$ar){
    $p=new \Seolan\Core\Param($ar);
    $section=array();
    foreach($desc as $fn=>&$f){
      if(!\Seolan\Core\Shell::langDataIsDefaultLanguage() && !$f->get_translatable()) continue;
      $value=$p->get($fn);
      $value_hid=$p->get($fn.'_HID');
      $options=isset($f->__options)?$f->__options:array();
      $options['_track']=false;
      $options[$fn.'_HID']=$value_hid;
      $o=$desc[$fn]->post_edit($value,$options);
      if($f->multivalued && $f->get_fgender()=='Oid' && is_array($o->raw)){
        $o->raw=implode('||',$o->raw);
      }
      $section[$fn]=$o->raw;
    }
    return $section;
  }
  /**
   * Import des templates de base d'un gestionnaire de rubrique via le module wizard
   * @param $ar array(
   *   'tab' => nom SQL de la table des contenus
   * )
   */
  function createDefaultTemplates($ar) {
    $p = new \Seolan\Core\Param($ar);
    $tab = $p->get('tab');
    if (empty($tab)) {
      $br['choosetab'] = $this->_templates->desc['tab']->edit($v = '');
    } else {
      $br = \Seolan\Module\InfoTree\Wizard::createDefaultTemplates($ar);
    }
    return \Seolan\Core\Shell::toScreen1($p->get('tplentry'), $br);
  }
  /**
   *  Merge des gabarits par default
   * - voir upgrades/xshell/20140601
   */
  function mergeDefaultTemplates($ar=NULL){
    $p = new \Seolan\Core\Param($ar, array('step'=>1, 'tplentry'=>'br'));
       $step = $p->get('step');
       $tpl = $p->get('tplentry');
       $data = [];
       $data['tablesdonnees'] = array();
       /* preparation de la liste */
      if ($step == 1){
	$modprops = array();
	array_walk(\Seolan\Core\Module\Module::modsprops(), function($item, $i) use (&$modprops){
	    $modprops[$item['MOID']] = $item;
          });
        $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS=TEMPLATES');
        $xds->desc['tab']->compulsory=false; // a voir faut un vide au debut et obliger a saisir
        $data['tabledonnees'] = $xds->desc['tab']->edit(($foo=[]));
        $stm = getDb()->prepare('select BNAME from BASEBASE where BTAB=?');
        $select= '<select class="tplselection" name="files[%type%][%file%]"><option value="">---</option><option value="new">Créer un nouveau gabarit</option><optgroup label="Remplacer le gabarit : ">';
        $tpls=getDB()->fetchAll('select * from TEMPLATES  where ifnull(modid, "") in ("", "'.$this->_moid.'") and gtype in ("page", "mail", "newsletter") and ifnull(tab, "")  != "CS8SEC" order by title');
	foreach($tpls as $t){
	  $stm->execute(array($t['tab']));
	  $data['tablesdonnees'][] = $t['tab'];
	  $mod = $modprops[$t['modid']]['MODULE'].' - '.$modprops[$t['modid']]['MPARAM']['group'];
	  $select .='<option value="'.$t['KOID'].'">'.$t['title'].' ['.$t['tab'].' - '.$stm->fetch(\PDO::FETCH_COLUMN).'  '.$mod. ']</option>';
	}

        $select  .=' </select>';
        $data['defaulttemplates']  = \Seolan\Module\InfoTree\Wizard::getDefaultTemplates();
	foreach($data['defaulttemplates'] as $type=>&$tpls){
	  foreach($tpls as $file=>&$item){
	    $sel =  str_replace(array('%type%','%file%','>'.$item),array($type,$file,' data-preselect=1>'.$item),$select);
	    $item = ['name'=>$item, 'select'=>$sel];
	  }
	}
	$data['tablesdonnees'] = array_unique($data['tablesdonnees']);
	return \Seolan\Core\Shell::toScreen1($tpl, $data);

      /* traitement */
      } else if ($step == 2){

        $txds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS=TEMPLATES');
        $files=$p->get('files');
        $tab=$p->get('tab');
	$setmodid = $p->get('setmodid');
	if (empty($tab)){
	  setSessionVar('message', 'Sélectionner une table des données. Merci!');
	  return;
	}

        $defaults=\Seolan\Module\InfoTree\Wizard::getDefaultTemplates();
        $created=false;
        foreach($defaults as $type=>$tpls){
	  foreach($tpls as $file=>$name){
	    if(empty($files[$type][$file])) continue; // rien de demandé
	    $oid=$files[$type][$file];
	    // @todo quand on duplique on devrait prendre la table définie dans le gabarit
	    if($oid!='new'){
	      $modid = '';
	      \Seolan\Core\Logs::critical(get_class($this), 'Delete old template before insert new ('.$name.'/'.$file.'/'.$oid.')');

	      $txds->del(array('oid'=>$oid,'_local'=>true));

	    }else{
	      $oid=null;
	      if ($setmodid){
		$modid = $this->_moid;
	      }
	    }

	    $resnewtpl = \Seolan\Module\InfoTree\Wizard::createDefaultTemplate('Module/InfoTree/public/templates/defaulttemplates',$type,$name,$file,$modid,$tab,$oid);

	    if($resnewtpl){
	      \Seolan\Core\Logs::critical(get_class($this), 'New template created ('.$name.'/'.$file.'/'.$oid.')');
	      $created=true;
	    }else{
	      \Seolan\Core\Logs::critical(get_class($this),'New template error ('.$name.'/'.$file.'/'.$oid.')');
	    }
	  }
        }

        // Mise à jour de la table des contenus seulement si des gabarits ont été mis à jour/insérés

        if($created){ //

	  // Change le nom des champs flashw et flashh
	  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$tab);
	  if($xds->desc['flashw']){
	    \Seolan\Core\Logs::critical(get_class($this), 'Change flashw field to width');
	    getDB()->execute('update DICT set FIELD="width" where FIELD="flashw" and DTAB="'.$tab.'"');
	    getDB()->execute('update MSGS set FIELD="width" where FIELD="flashw" and MTAB="'.$tab.'"');
	    getDB()->execute('update AMSG set MOID=REPLACE(MOID,":flashw:",":width:") where MOID LIKE "'.$tab.':flashw:%"');
	    getDB()->execute('alter table '.$tab.' change flashw width varchar(5) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL');
	  }
	  if($xds->desc['flashh']){
	    \Seolan\Core\Logs::critical(get_class($this), 'Change flashh field to height');
	    getDB()->execute('update DICT set FIELD="height" where FIELD="flashh" and DTAB="'.$tab.'"');
	    getDB()->execute('update MSGS set FIELD="height" where FIELD="flashh" and MTAB="'.$tab.'"');
	    getDB()->execute('update AMSG set MOID=REPLACE(MOID,":flashh:",":height:") where MOID LIKE "'.$tab.':flashh:%"');
	    getDB()->execute('alter table '.$tab.' change flashh height varchar(5) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL');
	  }
	  \Seolan\Core\DataSource\DataSource::clearCache();

	  // Création des champs inexistants (aucun des appels dans createDataStructure ne provoque d'erreur/exceptions dans le cas ou l'objet existe déjà)
	  \Seolan\Core\Logs::critical(get_class($this), 'Update data table');
	  \Seolan\Module\InfoTree\Wizard::createDataStructure($tab);

        } // created

      } // step 2

  } // function

  /**
   * module gérant ou pas les langues
   */
  public function getTranslatable(){
    return $this->_categories->isTranslatable();
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
    if (false && /* todo */ ($translationMode = $this->translationMode(new \Seolan\Core\Param($ar)))){
      $LANG_DATA = $translationMode->LANG_DATA;
      $LANG_TRAD = $translationMode->LANG_TRAD;
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
      $flags .= '&nbsp;&gt;&nbsp;'.$lang['long'];
    }
    return $flags;
  }

  function getInfos($ar=NULL){
  	$p=new \Seolan\Core\Param($ar,array('tplentry'=>TZR_RETURN_DATA));

  	$ret = parent::getInfos($ar);
  	if (\Seolan\Core\Shell::isRoot()){
  	$ret['infos']['tables']=(object)['label'=>'Tables',
  			'html'=>implode('<br>', ['Topics '.$this->table, 'Dyn. Data '.$this->dyntable, 'Sections '.$this->tname, 'Zones '.$this->zonetable])];
  	// les différents gabaris et tables des données
  	$ret['infos']['staticSsections']=(object)['label'=>'Données statiques',
  											  'html'=>implode('<br>', getDb()->fetchCol('select concat(ifnull(tpl.modid, "N/A"),",",tpl.gtype,",",tpl.tab,",",tpl.title,",",count(*)) from '.$this->tname.' it left outer join TEMPLATES tpl on tpl.koid=it.koidtpl'))];
  	}
  	return \Seolan\Core\Shell::toScreen1($p->get('tplentry'),$ret);
  }
  /**
   * Gestion des droits sur les sections
   * = secEditSimple, oid = ITxxx.ITOID
   * faut ensuite fournir un titre
   *
   */
  public function secEditSection($ar) {
    $acl = $this->secEditSimple($ar);
    $p = new \Seolan\Core\Param($ar, []);
    $tplentry = $p->get('tplentry');
    $lang_data = \Seolan\Core\Shell::getLangData();

    //mise en forme d'un titre pour la section : ordre, nom
    //si section statique, on précise ...
    foreach($acl['lines'] as &$line){
      $itoid = $line['oid'];
      $query = "SELECT T.title AS title_page, TPL.tab AS data_table, TPL.title as section_type, IT.KOIDDST, IT.ORDER1 as section_order";
      $query .= " FROM {$this->tname} IT INNER JOIN {$this->table} T ON T.KOID = IT.KOIDSRC";
      $query .= " LEFT OUTER JOIN TEMPLATES TPL on TPL.KOID=IT.KOIDTPL";
      $query .= " WHERE T.LANG = ? AND IT.ITOID = ?";

      $linejoin = getDB()->fetchRow($query, array($lang_data, $itoid));

      $line['title'] = $linejoin['title_page'];
      $sectionTitle = "Section #{$linejoin['section_order']}, '{$linejoin['section_type']}'";

      if (!empty($linejoin['data_table'])){
	$dataDS = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($linejoin['data_table']);
	$fields = [];
	foreach(['title', 'titsec', 'subtit'] as $fn){
	  if ($dataDS->fieldExists($fn)){
	    $fields[] = $fn;
	  }
	}
	$lineFields = getDB()->fetchRow("select ".implode(',', $fields)." from {$linejoin['data_table']} where koid=? and lang=?", [$linejoin['KOIDDST'], $lang_data]);
	foreach($fields as $fn){
	  if (!empty($fieldsLine[$n])){
	    $sectionTitle = $fieldsLine[$n];
	    break;
	  }
	}
      }
      if (trim($sectionTitle) != '') {
        $line['title'] .= " > $sectionTitle";
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$acl);
  }
  /**
   * surcharge pour indexation dans le contexte des applications
   */
  protected function _daemon($period){

    parent::_daemon($period);

    if (\Seolan\Core\Application\Daemon::running()){
      if ($this->insearchengine
      && \Seolan\Library\SolR\Search::solrActive()){
        $appDaemon = \Seolan\Core\Application\Daemon::getInstance();
        $this->appBuildSearchIndex($appDaemon->getApplication());
      }
    }
  }
  /// Indexation, eventuellement dans le contexte d'une application
  public function appBuildSearchIndex(?\Seolan\Core\Application\Application $app=null){
    $indexationKey = "lastindexation_{$this->_moid}".($app!=null?"_{$app->oid}":'');
    $lastIndexation = null;
    $lastIndexation=\Seolan\Core\DbIni::get($indexationKey,'val');
    $current=date('Y-m-d H:i:s');
    if(empty($lastIndexation)) {
      $lastIndexation=date('2000-01-01 00:00:00');
    }

    if (method_exists($app, 'getPageSearchIndexationTemplate'))
      $indexationTemplate = $app->getPageSearchIndexationTemplate();
    if (empty($indexationTemplate))
      $indexationTemplate = static::$pageSearchIndexationTemplate;

    \Seolan\Core\Logs::notice(__METHOD__," module : '{$this->_moid}' '{$this->getLabel()}' app : '{$app->name}', last indexation : '{$indexationKey}'=>'{$lastIndexation}', template : '{$indexationTemplate}'");

    $template = new \Seolan\Core\Template($indexationTemplate, $app);

    $shell = $GLOBALS['XSHELL'];
    $searchEngine = \Seolan\Library\SolR\Search::objectFactory();

    // @todo : liste des pages à indexer (plan, racines paramétrées, ...)
    $nbpages = $nberr = $nbEmptyPage = $newis = $oldis = 0;

    // liste des pages à indexer à partir des rubrqiues
    $pageToScan = function($rs) use($lastIndexation){
      foreach($rs as $line){
	if (!$line)
	  return;
	// vérifier qu'il y a des contenus
	$nb = getDB()->fetchOne("SELECT count(*) FROM {$this->tname}  where KOIDSRC=? AND KOIDDST != ?",[$line['KOID'],'']);
	if ($nb == 0){
	  Logs::debug(get_class($this)."::appBuildSearchIndex : page empty {$line['alias']}");
	  continue;
	}
	// vérifier les dates de mise à jour (! ne tiens pas compte des SF ! @todo)
	$lastupdate = $this->lastUpdate($line['KOID']);
	if (!empty($lastupdate) && $lastupdate['dateupd'] <= $lastIndexation){
	  Logs::debug(get_class($this)."::appBuildSearchIndex : skip page {$line['alias']} {$lastupdate['dateupd']}<{$lastIndexation}");
	  continue;
	}
	yield [$line, $lastupdate];
      }
    };

    // todo : la corbeille ! pas grave en FO : les pages y sont invalidées
    $rs = getDB()->select("select * from {$this->table}");
    $lastIndexed = $lastIndexation;

    foreach($pageToScan($rs) as list($line, $lastupdate)){

      Logs::debug(__METHOD__." {$line['KOID']} {$line['alias']} {$lastupdate['dateupd']}");

      $lastIndexed = max($lastupdate['dateupd'], $lastIndexed);
      $nbpages++;

      $shell->tpldata = [];
      $shell->rawdata = [];

      // on fait un viewpage et on parse avec le gabarit d'indexation
      try{

	$res = $this->viewpage(['_options'=>['local'=>true],
				'tplentry'=>'it',
				'toc'=>false,
				'_nav'=>false,
				'_linkin'=>false,
				'_path'=>false,
				'oidit'=>$line['KOID']
	]);

	// champs de la rubrique (alimentent la notice)
	$drub = $this->_categories->rdisplay($line['KOID']);
	$rubNotice = [];
	foreach($this->_categories->getIndexablesFields() as $fn){
	  $rubNotice[]=$drub["o{$fn}"]->text;
	}

	$pageContent = $template->parse($shell->tpldata, $shell->rawdata);
	if (empty($pageContent))
	  $nbEmptyPage++;

	$textContent = (new \Docxpresso\HTML2TEXT\HTML2TEXT($pageContent,
							    ['titles'=>'uppercase',
							     'cellSeparator'=>'']))->plainText();
	if (!empty($textContent)){
	  if ($searchEngine->docExists($line['KOID'],
				       $this->_moid,
				       $line['LANG'])){
	    $oldis++;
	  } else {
	    $newis++;
	  }
	  $fields = ['title'=>!empty($line['title2'])?$line['title2']:$line['title'],
		     'notice'=>implode(' ', $rubNotice),
		     'contents'=>$textContent];
	  $searchEngine->addItem($line['KOID'], $fields, $this->_moid, $line['LANG']);
	}

      } catch(\Throwable $t){
	Logs::critical(__METHOD__,"{$line['KOID']} {$line['alias']} {$t->getMessage()}");
	$nberr++;
      }
    }
    \Seolan\Core\DbIni::set($indexationKey,$lastIndexed);
    Logs::notice(__METHOD__," {$nbpages} page(s) parsed, {$nberr} error(s) during page parsing, {$nbEmptyPage} empty page(s), {$newis} page(s) added, {$oldis} page(s) updated");
  }
}
function xmodinfotree_procEditInPlace(){
  activeSec();
  $p=new \Seolan\Core\Param($ar,NULL);
  $moid=$p->get('moid');
  $oid=$p->get('oid');
  $oidit=$p->get('oidit');
  $field=$p->get('field');
  $value=$p->get('value');
  $GLOBALS['XSHELL']=new \Seolan\Core\Shell();
  $GLOBALS['XSHELL']->labels=new \Seolan\Core\Labels();
  $mod=\Seolan\Core\Module\Module::objectFactory($moid);
  if(!$mod->secure($oidit,'savesection')) die('secerror');
  $table=\Seolan\Core\Kernel::getTable($oid);
  $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
  $ar2['_options']['local']=true;
  $ar2['tplentry']=TZR_RETURN_DATA;
  $ar2['oid']=$oid;
  $ar2[$field]=$value;
  $ret=$xds->procEdit($ar2);
  die($value);
}
?>
