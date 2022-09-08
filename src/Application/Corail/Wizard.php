<?php
namespace Seolan\Application\Corail;
use \Seolan\Core\DataSource\DataSource;
use \Seolan\Model\DataSource\Table\Table as DSTable;
use \Seolan\Core\Module\Module;
use \Seolan\Core\Logs;
use \Seolan\Core\Shell;
use \Seolan\Library\Dir;

class Wizard extends \Seolan\Core\Application\Wizard{
  protected static $chartetab = 'CHARTE';
  protected static $stylestab = 'STYLES';
  protected static function getConfigTableName() {
    return 'CORAIL';
  }
  /**
  *
  */
  function inewstep2($ar){
    return $this->ieditstep2($ar);
  }
  /**
   * champs de l'étape 2
   * pour les modules : module pré-existant ou création d'un nouveau module
   */
  function _istep2(){
    $fields=[];
    $fields['infotree']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotree',
      'FTYPE'=>'\Seolan\Field\Module\Module',
      'COMPULSORY'=>false,
      'LABEL'=>'Module',
      'DPARAM'=>['filter'=>'TOID=4',
		 'fgroup'=>[TZR_DEFAULT_LANG=>'Gestionnaire de rubrique']]
      ));
    $fields['newinfotree']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'newinfotree',
      'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
      'COMPULSORY'=>false,
      'LABEL'=>'Nouveau module',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Gestionnaire de rubrique'])
    ));
    $fields['infotreehome']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotreehome',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>true,
      'LABEL'=>'Alias home',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Gestionnaire de rubrique'])
    ));
    $fields['infotreerror']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotreerror',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>false,
      'LABEL'=>'Alias erreur',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Gestionnaire de rubrique'])
    ));
    $fields['infotreeauth']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotreeauth',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>false,
      'LABEL'=>'Alias login',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Gestionnaire de rubrique'])
    ));
    $fields['infotreetop']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotreetop',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>true,
      'LABEL'=>'Alias menu top',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Gestionnaire de rubrique'])
    ));
    $fields['infotreebottom']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotreebottom',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>true,
      'LABEL'=>'Alias menu bas',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Gestionnaire de rubrique'])
    ));
    $fields['infotreederoule']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotreederoule',
      'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
      'COMPULSORY'=>false,
      'LABEL'=>'Activer menu déroulant',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Gestionnaire de rubrique'])
    ));
    $fields['infotreephotovisio']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotreephotovisio',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>false,
      'LABEL'=>'Oid visionneuse de photo',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Photos'])
    ));
    // autres modules
    $fields['nl']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'nl',
      'FTYPE'=>'\Seolan\Field\Module\Module',
      'COMPULSORY'=>false,
      'LABEL'=>'Module',
      'DPARAM'=>array('filter'=>'TOID=1','fgroup'=>[TZR_DEFAULT_LANG=>'News Letters'])
    ));
    $fields['newnl']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'newnl',
      'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
      'COMPULSORY'=>false,
      'LABEL'=>'Nouveau module',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'News Letters'])
    ));

    $fields['contact']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'contact',
      'FTYPE'=>'\Seolan\Field\Module\Module',
      'COMPULSORY'=>false,
      'LABEL'=>'Contact : module',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Contacts'])
    ));

    $fields['photo']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'photo',
      'FTYPE'=>'\Seolan\Field\Module\Module',
      'COMPULSORY'=>false,
      'LABEL'=>'Module',
      'DPARAM'=>array('filter'=>'TOID=8001 or TOID=25', 'fgroup'=>[TZR_DEFAULT_LANG=>'Photos'])
    ));
    $fields['newphoto']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'newphoto',
      'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
      'COMPULSORY'=>false,
      'LABEL'=>'Nouveau module',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Photos'])
    ));
    $fields['photofields']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'photofields',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>false,
      'LABEL'=>'Champs à utiliser pour la recherche',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Photos'])
    ));
    $fields['photoresult']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'photoresult',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>false,
      'LABEL'=>'Alias résultat recherche',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Photos'])
    ));

    $fields['news']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'news',
      'FTYPE'=>'\Seolan\Field\Module\Module',
      'COMPULSORY'=>false,
      'LABEL'=>'Module',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Actualités'])
    ));
    $fields['newnews']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'newnews',
      'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
      'COMPULSORY'=>false,
      'LABEL'=>'Nouveau module',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Actualités'])
    ));
    $fields['newsphoto']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'newsphoto',
      'FTYPE'=>'\Seolan\Field\Module\Module',
      'COMPULSORY'=>false,
      'LABEL'=>'Photo : module',
      'DPARAM'=>array('filter'=>'TOID=8001 or TOID=25',
		      'fgroup'=>[TZR_DEFAULT_LANG=>'Actualités'])
    ));


    $fields['partenaire']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'partenaire',
      'FTYPE'=>'\Seolan\Field\Module\Module',
      'COMPULSORY'=>false,
      'LABEL'=>'Module',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Partenaires',
				 'filter'=>'TOID=25'])
    ));
    $fields['newpartenaire']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'newpartenaire',
      'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
      'COMPULSORY'=>false,
      'LABEL'=>'Nouveau module',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Partenaires'])
    ));

    $fields['quicklinks']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'quicklinks',
      'FTYPE'=>'\Seolan\Field\Module\Module',
      'COMPULSORY'=>false,
      'LABEL'=>'Module liens rapides',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Divers',
				 'filter'=>'TOID=25'])
    ));
    if (!DataSource::sourceExists(static::$chartetab)){
      $fields['newtablecharte']=\Seolan\Core\Field\Field::objectFactory((object)array(
	'FIELD'=>'newtablecharte',
	'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
	'COMPULSORY'=>false,
	'LABEL'=>'Créer et initialiser une table charte',
	'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Charte/Mise en page'])
      ));
    } else {
      // recherche si un module existe sur la table
      $mods = Module::modulesUsingTable(static::$chartetab);
      if (empty($mods)){
	Shell::alert('La table '.static::$chartetab.' existe, sans module');
      } else {
	Shell::alert('La table charte existe, module(s) : '.$this->formatModlist($mods),'info');
      }
      
      $fields['charte']=\Seolan\Core\Field\Field::objectFactory((object)array(
	'FIELD'=>'charte',
	'FTYPE'=>'\Seolan\Field\Link\Link',
	'COMPULSORY'=>false,
	'LABEL'=>'Charte',
	'TARGET'=>static::$chartetab,
	'DPARAM'=>['fgroup'=>[TZR_DEFAULT_LANG=>'Charte/Mise en page']]
      ));
      $fields['newcharte']=\Seolan\Core\Field\Field::objectFactory((object)array(
	'FIELD'=>'newcharte',
	'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
	'COMPULSORY'=>false,
	'LABEL'=>'Initialiser une nouvelle charte',
	'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Charte/Mise en page'])
      ));
    }
    if (!DataSource::sourceExists(static::$stylestab)){
      $fields['newtablestyle']=\Seolan\Core\Field\Field::objectFactory((object)array(
	'FIELD'=>'newtablestyle',
	'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
	'COMPULSORY'=>false,
	'LABEL'=>'Créer et initialiser une table des mise en page (STYLES)',
	'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Charte/Mise en page'])
      ));
    } else {
      $mods = Module::modulesUsingTable(static::$stylestab);
      if (empty($mods)){
	Shell::alert('La table des mise en page existe, sans module', 'info');
      } else {
	Shell::alert('La table des mises en page existe, module(s) : '.$this->formatModlist($mods),'info');
      }
      $fields['newstyles']=\Seolan\Core\Field\Field::objectFactory((object)array(
	'FIELD'=>'newstyles',
	'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
	'COMPULSORY'=>false,
	'LABEL'=>'Ajouter un jeu de mise en page',
	'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Charte/Mise en page'])
      ));
    }
  
    $fields['bingtag']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'bingtag',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'FCOUNT'=>60,
      'COMPULSORY'=>false,
      'LABEL'=>'Bing Tag',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Divers'])
    ));
    $fields['analytictag']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'analytictag',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'FCOUNT'=>60,
      'COMPULSORY'=>false,
      'LABEL'=>'Analytic Tag',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Divers'])
    ));
    $fields['society']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'society',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'FCOUNT'=>60,
      'COMPULSORY'=>false,
      'LABEL'=>'Société',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Divers'],
		      'acomment'=>[TZR_DEFAULT_LANG=>'Si différente de societé url de la configuration'])
    ));

    // boutique
    $fields['cart']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'cart',
      'FTYPE'=>'\Seolan\Field\Module\Module',
      'COMPULSORY'=>false,
      'LABEL'=>'Module',
      'DPARAM'=>array('filter'=>'TOID=3',
		      'fgroup'=>[TZR_DEFAULT_LANG=>'Boutique'])
    ));
    $fields['cartproduct']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'cartproduct',
      'FTYPE'=>'\Seolan\Field\Module\Module',
      'COMPULSORY'=>false,
      'LABEL'=>'Module produits',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Boutique'])
    ));


    foreach($fields as $fd){
      if ($fd->ftype == '\Seolan\Field\Module\Module' && isset($fields["new{$fd->field}"])){
	       $fd->acomment='Choisir un podule existant ou cocher la case "Nouveau module, dessous"';
      }
    }

    return $fields;
  }
  private function formatModlist($mods){
    $list = [];
    foreach($mods as $moid=>$name){
      $mod = Module::objectFactory(['interactive'=>false,'moid'=>$moid,'tplentry'=>TZR_RETURN_DATA]);
      $list[] = "{$mod->group}>{$name}";
    }
    return implode(',', $list);
  }
  /// valeurs par défaut pour certains champs
  protected function defaultsValues(){
    $newtabs = [];
    if (!DataSource::sourceExists(static::$chartetab))
      $newtabs['newtablecharte'] = 1;
    if (!DataSource::sourceExists(static::$stylestab))
      $newtabs['newtablestyle'] = 1;
    return array_merge($newtabs, ['infotreebottom'=>'bottom',
	    'infotreetop'=>'site',
	    'infotreerror'=>'error404',
	    'infotreehome'=>'home']);
  }

  function ieditstep2($ar=NULL){
    $sfields=$this->_istep2();
    $fields=array();
    $groups = [];

    $defaults = $this->defaultsValues();
    foreach($sfields as $n=>$f){

      $fields[]=$f->edit($this->_conf[$n],
			 ($options=['value'=>$defaults[$n]??null]));


      if (isset($f->fgroup)){
	if (!isset($groups[$f->fgroup])){
	  $groups[$f->fgroup] = [];
	}
	$groups[$f->fgroup][] = $fields[count($fields)-1];
      }

    }
    if (count($groups)>0){
      $fields['_groups'] = $groups;
    }
    return $fields;
  }
  /// Enregistrement des valeurs et création des modules / tables si demandés
  function iend($ar=NULL){

    foreach($this->_istep2() as $n=>$f){
      $this->_app['step1']['params'][$n]=$f->post_edit($this->_app['step2'][$n]??null,$this->_app['step2'])->raw;
    }

    $params = &$this->_app['step1']['params'];
    $mgroup = $this->_app['step1']['name'];

    // charte
    if (empty($params['charte']) && $params['newtablecharte'] == 1){
        static::createModuleAndTableCharte($mgroup);
    } elseif ($params['newcharte']==1){
      static::createCharte($mgroup);
    }
    // styles
    if (isset($params['newtablestyle']) && $params['newtablestyle'] == 1){
      static::createModuleAndTableStyle($mgroup);
    } elseif ($params['newstyles']==1){
      static::createStyles($mgroup);
    }
    unset($this->_app['step1']['params']['newstyles']); 

    // modules
    $this->createModules();

    // ressources
    static::checkLocalAssets();

    if ($params['newtablecharte'] == 1 || $params['newcharte'] == 1){
      $charteoid = getDB()->fetchOne('select koid from '.static::$chartetab.' where meta01=?', [$mgroup]);
      $this->_app['step1']['params']['charte']=$charteoid; //$r;
    }
    unset($this->_app['step1']['params']['newcharte']);

    return parent::iend($ar);

  }
  /**
   * ajout des assets locaux par défaut
   * copie dans l lib locale
   * ajout des liens dans le www pointant sur la lib locale
   */
  
  static protected function checkLocalAssets(){
    $localdir = "{$GLOBALS['LOCALLIBTHEZORRO']}Corail";
    if (!file_exists($localdir)){
      mkdir($localdir);
      Dir::copy("{$GLOBALS['LIBTHEZORRO']}src/Application/Corail/public", "{$localdir}/public", true);
    }

    foreach(['css', 'fonts', 'images', 'js'] as $n){
      if (!file_exists(TZR_WWW_DIR.$n)){
        symlink("{$localdir}/public/{$n}",TZR_WWW_DIR.$n);
      } 
    }

  }
  /// création des modules et mise à jour des moid
  protected function createModules(){
    $linked = [];
    $mgroup = $this->_app['step1']['name'];
    // création d'un infotree
    if (empty($this->_app['step1']['params']['infotree']) && $this->_app['step1']['params']['newinfotree'] == 1){
      $linked[] = $this->_app['step1']['params']['infotree'] = static::createInfoTreeModule($mgroup, $this->_app['step1']['params']);
    }
    unset($this->_app['step1']['params']['newinfotree']); // on veut pas mémoriser ça
    
    // news : lien vers les rubriques;
    if (empty($this->_app['step1']['params']['news']) && $this->_app['step1']['params']['newnews'] == 1){
      // récupération du nom de la table des rubriques
      $itparams = Module::findParam($this->_app['step1']['params']['infotree']);
      $linked[] = $this->_app['step1']['params']['news'] = static::createNewsModule($mgroup, $itparams['MPARAM']['table']);
    }
    unset($this->_app['step1']['params']['newnews']);
    
    // abonnés à la news Letters
    if (empty($this->_app['step1']['params']['nl']) && $this->_app['step1']['params']['newnl'] == 1){
      $linked[] = $this->_app['step1']['params']['nl'] = static::createNewsLetterSubscribersModule($mgroup);
    }
    unset($this->_app['step1']['params']['newnl']);

    // médiatheque : ! modules collection et mots clés associés
    if (empty($this->_app['step1']['params']['photo']) && $this->_app['step1']['params']['newphoto'] == 1){
      list($this->_app['step1']['params']['photo'], $linked[], $linked[]) = static::createMediaModule($mgroup);
      $linked[] = $this->_app['step1']['params']['photo'];
    }
    unset($this->_app['step1']['params']['newphoto']);

    // Partenaires
    if (empty($this->_app['step1']['params']['partenaire']) && $this->_app['step1']['params']['newpartenaire'] == 1){
      $linked[] = $this->_app['step1']['params']['partenaire'] = static::createPartnerModule($mgroup);
    }
    unset($this->_app['step1']['params']['newpartenaire']);

    $this->_app['step1']['modules'] = array_merge($linked, is_array($this->_app['step1']['modules'])?$this->_app['step1']['modules']:[]);

  }

  static function dupCharte($oid) {
    $charte = static::$chartetab;
    if($oid) {
      $datasourceCharte = DataSource::objectFactoryHelper8($charte);
      $newOid = $datasourceCharte->duplicate(['oid'=> $oid]);
      return $newOid;
    }
    return $oid;
  }

  /// module gestionnaire de rubriques
  static protected function createInfoTreeModule(string $mgroup, array $params):int{
    $wd = new \Seolan\Module\InfoTree\Wizard();
    $wd->_module = (Object)[
    "modulename"=>"Rubriques",
    "group"=>$mgroup,
    "comment[TZR_DEFAULT_LANG]"=>"",
    "do_create_structure"=>1,
    "bname"=>"{$mgroup} - Rubriques",
    "btab"=>DSTable::newTableNumber('CTOPICS'),
    "do_create_field_linkin"=>1,
    "do_create_field_urlext"=>1,
    "do_create_field_custtitle"=>1,
    "do_create_field_custdescr"=>1,
    "do_create_field_style"=>1,
    "field_style_table"=>static::$stylestab,
    "do_create_data_structure"=>1,
    "datatablecode"=>DSTable::newTableNumber('CDATA'),
    "datatablename"=> "{$mgroup} - Rubriques, contenus statiques",
    "do_create_default_templates"=>1,
    "do_create_dyn_structure"=>1,
    "dyntablecode"=>DSTable::newTableNumber('CDYNDATA'),
    "dyntablename"=>"{$mgroup} - Rubriques, contenus dynamiques",
    "do_create_default_templates"=>1,
    "linkin"=>'linkin'];

    $moid = $wd->iend();

    $itparams = Module::findParam($moid);

    $dsrub = DataSource::objectFactoryHelper8($itparams['MPARAM']['table']);

    $defpages = [];
    if (!empty($params['infotreetop'])){
      $defpages[] = ['alias'=>$params['infotreetop'],
         'title'=>'Home',
         'PUBLISH'=>1];
        // il y a un home par défaut quand le module vient d'être créé
        $oidhome = getDB()->fetchOne("select koid from {$itparams['MPARAM']['table']} where alias=?",['home']);
        if ($oidhome)
          $dsrub->del(['_options'=>['local'=>true],'oid'=>$oidhome]);
    }
    if (!empty($params['infotreebottom']))
      $defpages[] = ['alias'=>$params['infotreebottom'],
         'title'=>'Menu bas',
         'PUBLISH'=>1];
    if (!empty($params['infotreerror']) && $params['infotreerror'] != 'error404')
      $defpages[] = ['alias'=>$params['infotreerror'],
         'title'=>'Page non trouvée',
         'PUBLISH'=>1];
    if (!empty($params['infotreehome']) && $params['infotreehome'] != 'home')
      $defpages[] = ['alias'=>$params['infotreehome'],
         'title'=>'Accueil',
         'PUBLISH'=>1];

    $defStyle = getDB()->fetchOne('select koid from '.static::$stylestab.' where title = ?', ["Charte Corail {$mgroup} 1 col"]);

    foreach ($defpages as $page) {

      if (isset($defStyle))
	$page['style'] = $defStyle;

      $dsrub->procInput($page);
	  
    }

    return $moid;

  }
  /// module photo / médiathèque
  static protected function createMediaModule(string $group):array{
    $colbtab = $btab = DSTable::newTableNumber('CMDCOLLECTIONS');
    $bname = "{$group} - Médiathèque : collections";
    static::createMediaCollectionTable($btab, $bname);
    $modcol =
    $keywordsbtab = $btab = DSTable::newTableNumber('CMDKEYWORDS');
    $bname = "{$group} - Médiathèque : mots clés";
    static::createMediaKeyWordsTable($btab, $bname);
    $mainbtab = $btab = DSTable::newTableNumber('CMEDIA');
    $bname = "{$group} - Médiathèque : table principale";
    static::createMediaMainTable($btab, $bname, $colbtab, $keywordsbtab);

    // modules collection et mots clés
    $modname = "Collections";
    $moidcollection = (new \Seolan\Module\Table\Wizard())->quickCreate($modname,
    ['table'=>$colbtab,
    'group'=>$group]);
    Logs::notice(__METHOD__,"module : $modname in group $group created");

    $modname = "Mots clés";
    $moidkw = (new \Seolan\Module\Table\Wizard())->quickCreate($modname,
    ['table'=>$keywordsbtab,
    'group'=>$group]);
    Logs::notice(__METHOD__,"module : $modname in group $group created");

    $wd = new \Seolan\Module\Media\Wizard();

    $wd->_module = (Object)[
      'modulename'=>"Médiathèque",
      'group'=>$group,
      'tcol'=>$colbtab,
      'collection'=>$moidcollection,
      'table'=>$mainbtab,
    ];
    $moid = $wd->iend();
    return [$moid, $moidcollection, $moidkw];
  }
  /// module des partenaires
  static protected function createPartnerModule(string $group):int{
    $btab = DSTable::newTableNumber('CPARTNERS');
    $bname = "{$group} - Partenaires";
    $modname = "Partenaires";
    static::createPartnerTable($btab,$bname);
    $moid = (new \Seolan\Module\Table\Wizard())->quickCreate($modname, ['table'=>$btab,
								'group'=>$group]);
    Logs::notice(__METHOD__,"module : $modname in group $group created");
    return $moid;
  }
  /// module abonnés news Letter
  static protected function createNewsLetterSubscribersModule(string $group):int{

    $btab = DSTable::newTableNumber('CNLSUBS');
    $bname = "{$group} - Newsletter : abonnés";
    $modname = "Newsletter : abonnés";
    static::createNewsLetterSubscribersTable($btab,$bname);
    $moid = (new \Seolan\Module\Table\Wizard())->quickCreate($modname, ['table'=>$btab,'group'=>$group]);
    Logs::notice(__METHOD__,"module : $modname in group $group created");
    return $moid;
  }
  /// module news
  static protected function createNewsModule(string $group, string $tabrub):int{
    $btab = DSTable::newTableNumber('CNEWS');
    $bname = "{$group} - News";
    $modname = "News";
    static::createNewsTable($btab,$bname, $tabrub);
    $moid = (new \Seolan\Module\Table\Wizard())->quickCreate($modname, ['table'=>$btab,'group'=>$group]);
    Logs::notice(__METHOD__,"module : $modname in group $group created");
    return $moid;
  }
  /// table des collections
  static protected function createMediaCollectionTable(string $btab, string $bname){
    $result = DSTable::procNewSource(
    ['translatable'=>1,
    'auto_translate'=>1,
    'classname'=>'\Seolan\Model\DataSource\Table\Table',
    'btab'=>$btab,
    'bname'=>[TZR_DEFAULT_LANG=>$bname]
     ]);
    // $boid = $result['boid'];
    // $result['error'])

    // Champs
    DataSource::clearCache(); // référence à la table nouvellement créée
    $ds = DataSource::objectFactoryHelper8($btab);

    $ds->createField('UPD','Dernière mise à jour','\Seolan\Field\Timestamp\Timestamp',0,0,0,0,0,1,0,0,'%');
    $ds->createField('OWN','Propriétaire','\Seolan\Field\Link\Link',0,1,0,1,0,1,0,0,'USERS');
    $ds->createField('title','Titre','\Seolan\Field\ShortText\ShortText',255,2,1,1,1,1,0,1,'%');
    $ds->createField('parent','Parent','\Seolan\Field\Link\Link',0,3,0,1,1,0,0,0,$btab);
  }
  /// table les mots clés
  static protected function createMediaKeyWordsTable(string $btab, string $bname){
    $result = DSTable::procNewSource(
      ['translatable'=>1,
      'auto_translate'=>1,
      'classname'=>'\Seolan\Model\DataSource\Table\Table',
      'btab'=>$btab,
      'bname'=>[TZR_DEFAULT_LANG=>$bname]
       ]);
      // $boid = $result['boid'];
      // $result['error'])

    // Champs
    DataSource::clearCache(); // référence à la table nouvellement créée
    $ds = DataSource::objectFactoryHelper8($btab);

    $ds->createField('UPD','Dernière mise à jour','\Seolan\Field\Timestamp\Timestamp',0,0,0,0,0,1,0,0,'%');
    $ds->createField('OWN','Propriétaire','\Seolan\Field\Link\Link',0,1,0,1,0,1,0,0,'USERS');
    $ds->createField('title','Titre','\Seolan\Field\ShortText\ShortText',255,2,1,1,1,1,0,1,'%');
    $ds->createField('parent','Parent','\Seolan\Field\Link\Link',0,3,0,1,1,0,0,0,$btab);
  }
  /// table des média
  static protected function createMediaMainTable(string $btab, string $bname, $colbtab, $keywordsbtab){
    $result = DSTable::procNewSource(
    ['translatable'=>1,
    'auto_translate'=>1,
    'classname'=>'\Seolan\Model\DataSource\Table\Table',
    'btab'=>$btab,
    'bname'=>[TZR_DEFAULT_LANG=>$bname]
     ]);
    // $boid = $result['boid'];
    // $result['error'])

    // Champs
    DataSource::clearCache();
    $ds = DataSource::objectFactoryHelper8($btab);

    $ds->createField('UPD','Derniere Mise a jour','\Seolan\Field\Timestamp\Timestamp',0,0,0,0,1,0,0,0,'%');
    $ds->createField('PUBLISH','Validé','\Seolan\Field\Boolean\Boolean',0,1,0,1,1,0,0,0,'%');
    $ds->createField('OWN','Propriétaire','\Seolan\Field\Link\Link',0,2,0,0,0,0,0,0,'USERS');
    $ds->createField('media','Media','\Seolan\Field\File\File',0,3,0,0,1,0,0,1,'%');
    $ds->createField('ref','Référence','\Seolan\Field\ShortText\ShortText',60,4,0,0,1,0,0,1,'%');
    $ds->createField('title','Titre','\Seolan\Field\ShortText\ShortText',255,5,0,0,1,1,0,1,'%');

    $ds->createField('collection','Collection','\Seolan\Field\Thesaurus\Thesaurus',0,6,0,1,1,0,1,0,$colbtab);
    $ds->createField('keywords','Mots clé','\Seolan\Field\Thesaurus\Thesaurus',0,7,0,1,0,0,1,0,$keywordsbtab);

    DataSource::clearCache();
    $ds = DataSource::objectFactoryHelper8($btab);

    $ds->procEditField(['field'=>'collection',
			'_todo'=>'save',
			'options'=>['table'=>$colbtab, 'fparent'=>'parent','flabel'=>'title']]);

    $ds->procEditField(['field'=>'keywords',
			'_todo'=>'save',
			'options'=>['table'=>$keywordsbtab, 'fparent'=>'parent','flabel'=>'title']]);


    $ds->createField('urgency','Urgence','\Seolan\Field\Real\Real',2,8,0,0,0,0,0,0,'%');
    $ds->createField('category','Catégorie','\Seolan\Field\ShortText\ShortText',50,9,0,0,0,0,0,0,'%');
    $ds->createField('othercategories','Autres catégories','\Seolan\Field\ShortText\ShortText',255,10,0,0,0,0,0,0,'%');
    $ds->createField('instructions','Instructions','\Seolan\Field\Text\Text',60,11,0,0,0,1,0,0,'%');
    $ds->createField('created','Date de création','\Seolan\Field\DateTime\DateTime',0,12,0,0,0,0,0,0,'%');
    $ds->createField('author','Auteur','\Seolan\Field\ShortText\ShortText',255,13,0,1,1,0,0,0,'%');
    $ds->createField('authortitle','Titre de l\'auteur','\Seolan\Field\ShortText\ShortText',255,14,0,0,0,1,0,0,'%');
    $ds->createField('city','Ville','\Seolan\Field\ShortText\ShortText',255,15,0,0,0,0,0,0,'%');
    $ds->createField('headline','Headline','\Seolan\Field\ShortText\ShortText',255,16,0,0,0,0,0,0,'%');
    $ds->createField('source','Source','\Seolan\Field\ShortText\ShortText',255,17,0,0,0,0,0,0,'%');
    $ds->createField('copyright','Copyright','\Seolan\Field\ShortText\ShortText',255,18,0,0,0,1,0,0,'%');
    $ds->createField('caption','Description','\Seolan\Field\Text\Text',60,19,0,0,0,1,0,0,'%');
    $ds->createField('link','Lien sur l\'image','\Seolan\Field\Url\Url',0,21,0,0,0,0,0,0,'%');


  }
  /// table des partenaires
  static protected function createPartnerTable($btab, $bname){
    $result = DSTable::procNewSource(
    ['translatable'=>0,
    'auto_translate'=>0,
    'classname'=>'\Seolan\Model\DataSource\Table\Table',
    'btab'=>$btab,
    'bname'=>[TZR_DEFAULT_LANG=>$bname]
     ]);
    // $boid = $result['boid'];
    // $result['error'])

    // Champs
    DataSource::clearCache(); // référence à la table nouvellement créée
    $ds = DataSource::objectFactoryHelper8($btab);

    $ds->createField('UPD','Dernière mise à jour','\Seolan\Field\Timestamp\Timestamp',0,0,0,0,0,0,0,0,'%');
    $ds->createField('OWN','Propriétaire','\Seolan\Field\Link\Link',0,2,0,1,0,0,0,0,'USERS');
    $ds->createField('title','Titre','\Seolan\Field\ShortText\ShortText',40,3,1,1,1,0,0,1,'%');
    $ds->createField('PUBLISH','Validé','\Seolan\Field\Boolean\Boolean',0,4,0,0,0,0,0,0,'%');
    $ds->createField('Image','Image','\Seolan\Field\Image\Image',0,5,0,0,1,0,0,0,'%');
    $ds->createField('url','URL','\Seolan\Field\Url\Url',0,6,0,0,1,0,0,0,'%');
    $ds->createField('categorie','Catégorie','\Seolan\Field\ShortText\ShortText',200,7,1,0,1,0,0,0,'%');
    $ds->createField('disporder','Ordre d\'affichage','\Seolan\Field\Order\Order',0,8,0,0,1,0,0,0,'%');
  }
  /// table des news
  static protected function createNewsTable(string $btab, string $bname, string $tabrub){
    $result = DSTable::procNewSource(
    ['translatable'=>1,
    'auto_translate'=>0,
    'classname'=>'\Seolan\Model\DataSource\Table\Table',
    'btab'=>$btab,
    'bname'=>[TZR_DEFAULT_LANG=>$bname]
     ]);
    // $boid = $result['boid'];
    // $result['error'])

    // Champs
    DataSource::clearCache(); // référence à la table nouvellement créée
    $ds = DataSource::objectFactoryHelper8($btab);

    $ds->createField('UPD','Dernière mise à jour','\Seolan\Field\Timestamp\Timestamp',0,0,0,0,0,1,0,0,'%');
    $ds->createField('PUBLISH','Valide','\Seolan\Field\Boolean\Boolean',0,0,0,0,1,1,0,0,'%');
    $ds->createField('F0007','Ordre','\Seolan\Field\Order\Order',0,1,0,0,1,0,0,0,'%');
    $ds->createField('F0001','Titre','\Seolan\Field\ShortText\ShortText',250,2,0,1,1,1,0,1,'%');
    $ds->createField('F0002','Texte','\Seolan\Field\RichText\RichText',80,3,0,0,1,1,0,0,'%');
    $ds->createField('F0003','Image','\Seolan\Field\Image\Image',0,4,0,0,1,1,0,0,'%');
    $ds->createField('link','Lien + d\'infos','\Seolan\Field\Url\Url',0,5,0,0,1,1,0,0,'%');
    $ds->createField('F0004','Début d\'affichage','\Seolan\Field\Date\Date',0,6,0,1,1,0,0,0,'%');
    $ds->createField('F0005','Fin d\'affichage','\Seolan\Field\Date\Date',0,7,0,1,1,0,0,0,'%');
    $ds->createField('rubq','Rubrique','\Seolan\Field\Thesaurus\Thesaurus',0,9,0,1,1,0,1,0,$tabrub);
    $ds->createField('vigncol','Vignette colonne','\Seolan\Field\Boolean\Boolean',0,10,0,1,1,0,0,0,'%');
  }
  /// table abonnés newsletter
  static protected function createNewsLetterSubscribersTable(string $btab, string $bname){
    $result = DSTable::procNewSource(
    ['translatable'=>1,
    'auto_translate'=>0,
    'classname'=>'\Seolan\Model\DataSource\Table\Table',
    'btab'=>$btab,
    'bname'=>[TZR_DEFAULT_LANG=>$bname]
     ]);
    // $boid = $result['boid'];
    // $result['error'])

    // Champs
    DataSource::clearCache(); // référence à la table nouvellement créée
    $ds = DataSource::objectFactoryHelper8($btab);

    $ds->createField('UPD','Dernière mise à jour','\Seolan\Field\Timestamp\Timestamp',0,0,0,0,0,0,0,0,'%');
    $ds->createField('email','Email','\Seolan\Field\ShortText\ShortText',250,1,0,1,1,0,0,0,'%');
    $ds->createField('categ','Catégorie','\Seolan\Field\ShortText\ShortText',200,2,0,1,1,0,0,0,'%');
    $ds->createField('emailok','Email Valide','\Seolan\Field\Boolean\Boolean',0,3,0,1,1,0,0,0,'%');
    $ds->createField('bounce','Retour en erreur','\Seolan\Field\Boolean\Boolean',0,4,0,1,1,0,0,0,'%');
    $ds->createField('RGPD','Consentement RGPG','\Seolan\Field\Boolean\Boolean',0,5,0,1,1,0,0,0,'%');

  }
  /// styles / mises en page
  static protected function createModuleAndTableStyle(string $group){
    $result = DSTable::procNewSource(
    ['translatable'=>0,
    'auto_translate'=>0,
    'classname'=>'\Seolan\Model\DataSource\Table\Table',
    'btab'=>static::$stylestab,
    'bname'=>[TZR_DEFAULT_LANG=>"{$group} - Styles / Mise en page"]
     ]);
    // $boid = $result['boid'];
    // $result['error'])

    // Champs
    $ds = DataSource::objectFactoryHelper8('STYLES');

    $ds->createField('UPD','Dernière mise à jour','\Seolan\Field\Timestamp\Timestamp',0,0,0,0,0,0,0,0,'%');
    $ds->createField('title','Nom du style','\Seolan\Field\ShortText\ShortText',40,1,1,1,1,0,0,1,'%');
    $ds->createField('bandeau','Bandeau image','\Seolan\Field\Image\Image',0,2,0,0,1,0,0,1,'%');
    $ds->createField('style','Feuille de style','\Seolan\Field\ShortText\ShortText',80,3,0,1,1,0,0,0,'%');
    $ds->createField('tpl','Gabarit','\Seolan\Field\ShortText\ShortText',40,4,1,1,1,0,0,0,'%');

    DataSource::clearCache();


    static::createStyles($group);
   
    $moid = (new \Seolan\Module\Table\Wizard())->quickCreate("Mise en page",
    ['table'=>static::$stylestab,
    'group'=>$group]);
    Logs::notice(__METHOD__,"module 'Mise en page' ($moid) created in group $group");

  }
  protected static function createStyles($group){
    $ds = DataSource::objectFactoryHelper8(static::$stylestab);
    foreach([
      ["Charte Corail {$group} 2 cols", 'css/style-2cols.css', 'application:index-2cols.html'],
      ["Charte Corail {$group} 3 cols", 'css/style-3cols.css', 'application:index-3cols.html'],
      ["Charte Corail {$group} 1 col", 'css/style-1col.css', 'application:index-1col.html']
    ] as list($title, $css, $tpl)){
      $ds->procInput(['_options'=>['local'=>1],
		      'title'=>$title,
		      'style'=>$css,
		      'tpl'=>$tpl]);
    }
  }
  /// charte
  static protected function createModuleAndTableCharte(string $group){
    DataSource::clearCache(); // référence à la table nouvellement créée
    $result = DSTable::procNewSource(
      ['translatable'=>1,
       'auto_translate'=>1,
       'classname'=>'\Seolan\Model\DataSource\Table\Table',
       'btab'=>static::$chartetab,
       'bname'=>[TZR_DEFAULT_LANG=>"{$group} - Charte"]
    ]);
    // $boid = $result['boid'];
    // $result['error'])

    // Champs
    DataSource::clearCache(); // référence à la table nouvellement créée
    
    $ds = DataSource::objectFactoryHelper8(static::$chartetab);

    $ds->createField('UPD','Last update','\Seolan\Field\Timestamp\Timestamp',0,0,0,0,0,0,0,0,'%');
    $ds->createField('meta01','Meta : Description','\Seolan\Field\Text\Text',50,1,0,0,1,1,0,0,'%');
    $ds->createField('meta02','Meta : Mots Clés','\Seolan\Field\Text\Text',50,2,0,0,1,1,0,0,'%');
    $ds->createField('icon','Favicon','\Seolan\Field\Image\Image',0,3,0,0,1,0,0,0,'%');
    $ds->createField('appletouchicon','Apple touch icon','\Seolan\Field\Image\Image',0,4,0,1,0,0,0,1,'%');
    $ds->createField('FR','Langue activée : Français','\Seolan\Field\Boolean\Boolean',0,5,1,0,0,0,0,0,'%');
    $ds->createField('GB','Langue activée : Anglais','\Seolan\Field\Boolean\Boolean',0,6,1,0,0,0,0,0,'%');
    $ds->createField('loglg','Logo large','\Seolan\Field\Image\Image',0,7,0,1,0,0,0,1,'%');
    $ds->createField('logmd','Logo medium','\Seolan\Field\Image\Image',0,8,0,0,0,0,0,1,'%');
    $ds->createField('logsm','Logo small','\Seolan\Field\Image\Image',0,9,0,0,0,0,0,1,'%');
    $ds->createField('logxs','Logo extra small','\Seolan\Field\Image\Image',0,10,0,1,0,0,0,1,'%');
    $ds->createField('logtxt','Logo slogan texte','\Seolan\Field\ShortText\ShortText',80,11,0,1,0,1,0,1,'%');
    $ds->createField('adresse','Coordonnées 1','\Seolan\Field\RichText\RichText',60,12,0,1,0,1,0,1,'%');
    $ds->createField('adresse2','Coordonnées 2','\Seolan\Field\RichText\RichText',60,13,0,0,0,0,0,0,'%');
    $ds->createField('social_facebook','Facebook 1','\Seolan\Field\ShortText\ShortText',200,14,0,0,0,0,0,0,'%');
    $ds->createField('social_facebook2','Facebook 2','\Seolan\Field\ShortText\ShortText',200,15,0,0,0,0,0,0,'%');
    $ds->createField('social_twitter','Social - Twitter','\Seolan\Field\ShortText\ShortText',80,16,0,0,0,0,0,0,'%');
    $ds->createField('social_googleplus','Social - Google +','\Seolan\Field\ShortText\ShortText',80,17,0,0,0,0,0,0,'%');
    $ds->createField('social_linkedin','Social - Linkedin','\Seolan\Field\ShortText\ShortText',80,18,0,0,0,0,0,0,'%');
    $ds->createField('social_pinterest','Social - Pinterest','\Seolan\Field\ShortText\ShortText',80,19,0,0,0,0,0,0,'%');
    $ds->createField('social_instagram','Social - Instagram','\Seolan\Field\ShortText\ShortText',80,20,0,0,0,0,0,0,'%');
    $ds->createField('social_youtube','Social - Youtube','\Seolan\Field\ShortText\ShortText',80,21,0,0,0,0,0,0,'%');
    $ds->createField('social_vimeo','Social - Vimeo','\Seolan\Field\ShortText\ShortText',80,22,0,0,0,0,0,0,'%');
    $ds->createField('social_rss','Social - rss','\Seolan\Field\Boolean\Boolean',0,23,0,0,0,0,0,0,'%');
    $ds->createField('social_addthis','Social - Addthis','\Seolan\Field\ShortText\ShortText',80,24,0,0,0,0,0,0,'%');
    $ds->createField('ipgeo','Images : taille info plus','\Seolan\Field\ShortText\ShortText',20,25,0,0,1,0,0,0,'%');
    $ds->createField('gtigeo','Images : txt+img ou 2 cols','\Seolan\Field\ShortText\ShortText',20,26,0,0,1,0,0,0,'%');
    $ds->createField('ggigeo','taille : grande image','\Seolan\Field\ShortText\ShortText',20,27,0,0,1,0,0,0,'%');
    $ds->createField('g4igeo','Images : taille 1,2,3,4','\Seolan\Field\ShortText\ShortText',20,28,0,0,1,0,0,0,'%');
    $ds->createField('ipgeo2','Image partenaire taille','\Seolan\Field\ShortText\ShortText',20,29,0,0,1,0,0,0,'%');
    $ds->createField('analytictag','Tag Google analytics','\Seolan\Field\ShortText\ShortText',50,30,0,0,1,0,0,0,'%');
    $ds->createField('social_stream_network_params','Paramètres du mur des réseaux sociaux','\Seolan\Field\Text\Text',60,31,0,0,0,0,0,0,'%');
    $ds->createField('social_stream_activated','Activation du mur des réseaux sociaux','\Seolan\Field\Boolean\Boolean',0,32,0,0,0,0,0,0,'%');

    DataSource::clearCache();

    static::createCharte($group);
    
    $moid = (new \Seolan\Module\Table\Wizard())->quickCreate("Charte",
    ['table'=>static::$chartetab,
     'group'=>$group]);
    
    Logs::notice(__METHOD__,"module 'Charte' ($moid) created in group $group");
  }
  protected static function createCharte($group){
    // Insertion d'une ligne vide
    $ds = DataSource::objectFactoryHelper8(static::$chartetab);
    $ds->procInput(['_options'=>['local'=>true],
		    'meta01'=>$group]);
  }
}
