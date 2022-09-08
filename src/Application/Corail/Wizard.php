<?php
namespace Seolan\Application\Corail;
use \Seolan\Core\DataSource\DataSource;
use \Seolan\Model\DataSource\Table\Table as DSTable;
use \Seolan\Core\Module\Module;
use \Seolan\Core\{Logs,Shell,Dir,System,Kernel};

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
    // page contact
    $fields['infotreecontact']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotreecontact',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>false,
      'LABEL'=>'Alias page contact',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Gestionnaire de rubrique'])
    ));
    $fields['infotreementions']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotreementions',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>false,
      'LABEL'=>'Alias page mentions légales',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Gestionnaire de rubrique'])
    ));
    $fields['infotresubnl']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotreesubnl',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>false,
      'LABEL'=>'Alias inscription/désinscription NL',
      'DPARAM'=>array('fgroup'=>[TZR_DEFAULT_LANG=>'Gestionnaire de rubrique'])
    ));
    $fields['infotreeplansite']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'infotreeplansite',
      'FTYPE'=>'\Seolan\Field\ShortText\ShortText',
      'COMPULSORY'=>false,
      'LABEL'=>'Alias page plan du site',
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
    // module contact existant
    $fields['contact']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'contact',
      'FTYPE'=>'\Seolan\Field\Module\Module',
      'COMPULSORY'=>false,
      'LABEL'=>'Module',
      'DPARAM'=>array('filter'=>'toid='.XMODCRM_TOID,'fgroup'=>[TZR_DEFAULT_LANG=>'Contacts'])
    ));
    // création d'un nouveau module contact
    $fields['newcontact']=\Seolan\Core\Field\Field::objectFactory((object)array(
      'FIELD'=>'newcontact',
      'FTYPE'=>'\Seolan\Field\Boolean\Boolean',
      'COMPULSORY'=>false,
      'LABEL'=>'Nouveau module',
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
    return array_merge($newtabs, [
      'infotreebottom'=>'bottom',
      'infotreetop'=>'site',
      'infotreerror'=>'page-non-trouvee',
      'infotreehome'=>'home',
      'infotreecontact'=>'contact',
      'infotreementions'=>'mentions',
      'infotreeplansite'=>'plansite',
      'infotreesubnl'=>'newsletter_OK',
    ]);
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
  /// dans le cas de l'infotree, création des pages par défaut
  protected function createModules(){
    $linked = [];
    $mgroup = $this->_app['step1']['name'];

    // module contact (il en faut le moid pour les pages par defaut éventuelles)
    $contactmoid = null;
    if (empty($this->_app['step1']['params']['contact']) && $this->_app['step1']['params']['newcontact'] == 1){
      $contactmoid = $linked[] = $this->_app['step1']['params']['contact'] = static::createContactModule($mgroup);
    } elseif (!empty($this->_app['step1']['params']['contact'])) {
      $contactmoid = $this->_app['step1']['params']['contact'];
    } else {
      unset($this->app['step1']['params']['infotreecontact']);
    }
    unset($this->_app['step1']['params']['newcontact']);
    
    // création d'un infotree
    if (empty($this->_app['step1']['params']['infotree']) && $this->_app['step1']['params']['newinfotree'] == 1){
      $itmoid = $linked[] = $this->_app['step1']['params']['infotree'] = static::createInfoTreeModule($mgroup,
												      $this->_app['step1']['params'],
												      ['contactmoid'=>$contactmoid]);
    }
    unset($this->_app['step1']['params']['newinfotree']);
    
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

  static function dupCharte($oid, $name) {
    $charte = static::$chartetab;
    if($oid) {
      $datasourceCharte = DataSource::objectFactoryHelper8($charte);
      $newOid = $datasourceCharte->duplicate(['oid'=> $oid]);
      $datasourceCharte->procEdit([
          'oid'=>$newOid,
          'nom'=>$name,
      ]);
      return $newOid;
    }
    return $oid;
  }
  /*
  * Suppression de la charte si elle n'est pas utilisé dans une autre application
  *
  * @param string $charteOid oid de la charte à supprimer
  * @param string $appOid application
  * @return oid de la charte si supprimer || false sinon
  */
  static function delCharte($charteOid, $appOid) {
    $chartetab = static::$chartetab;
    $appList = getDB()->fetchCol('SELECT KOID FROM APP WHERE JSON_EXTRACT(params,"$.charte")=? AND KOID!=?',array($charteOid, $appOid));
    if (empty($appList)){
      $datasourceCharte = DataSource::objectFactoryHelper8($chartetab);
      $datasourceCharte->del(['oid'=>$charteOid]);
      return $charteOid;
    }
    return false;
  }
  /// suppression des alias crées par defaut dans le gestionnaire de rubrique de base
  private static function delDefaultPage($dsrub, $alias){
    $oid = getDB()->fetchOne("select koid from {$dsrub->getTable()} where alias=?",[$alias]);
    if ($oid)
      $dsrub->del(['_options'=>['local'=>true],
		   'oid'=>$oid]);
  }
  /// module gestionnaire de rubriques
  static protected function createInfoTreeModule(string $mgroup, array $params, $options=[]):int{
    $wd = new \Seolan\Module\InfoTree\Wizard();
    $wd->_module = (Object)[
    "modulename"=>$params['modulename']??'Rubriques',
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

    static::delDefaultPage($dsrub, 'home');
    static::delDefaultPage($dsrub, 'error404');
    
    $defpages = [];
    if (!empty($params['infotreetop'])){
      $defpages[] = ['alias'=>$params['infotreetop'], 'title'=>'Menu haut'];
    }

    if (!empty($params['infotreebottom'])){
      if (!empty($options['contactmoid'])){
	$tplinsert = getDB()->fetchOne('select koid from TEMPLATES where gtype=? and title=? and functions like ? and (ifnull(modid,"")="" or modid=?)',
				       ['function',
					'Insertion d\'une fiche',
					'%Table::insert%',
					$moid]
	);
	if (!empty($tplinsert))
	  $contactPage = ['tpl'=>$tplinsert,
			  'fields'=>[
			    '__labelvalidate'=>'OK',
			    '__nextalias'=>($params['infotreecontact']??'demande').'ok',
			    '__selectedfields'=>['body','email']
			  ],
			  'section'=>['function'=>'insert',
				      'moid'=>$options['contactmoid']
			  ]
	  ];
	else {
	  $contactPage = null;
	  Logs::critical(__METHOD__,"function Table::insert not found in TEMPLATES for module $moid");
	}
      }
      
      $pages = [];
      if (!empty($params['infotreecontact'])){
	$pages[] = ['alias'=>$params['infotreecontact'],
		    'title'=>'Contact',
		    'fonctions'=>$contactPage??null];
      }
      if (!empty($params['infotreementions'])){
	$txt1 = <<<EOF
<p><strong>Copyright photo :</strong><br />
Ce site appartient à la ......&nbsp; qui dispose de tous les droits de propri&eacute;t&eacute; sur tous ses &eacute;l&eacute;ments, affich&eacute;s ou en bases de donn&eacute;es, sur son architecture technique, son mode de navigation, sa mise en page et sa charte graphique.<br />
<br />
<strong>H&eacute;bergement du site :</strong> <a href="http://www.xsalto.com"><strong>Xsalto</strong></a><br />
<br />
<strong>Photos et autres illustrations</strong> pr&eacute;sents sur ce site ne sont pas libres de droit. Leur reproduction ou leur transmission n&#39;est permise qu&#39;avec l&#39;accord &eacute;crit pr&eacute;alable de l&#39;auteur, m&ecirc;me quand le nom de celui-ci n&#39;apparait pas. Le Code de la propri&eacute;t&eacute; intellectuelle n&#39;autorise, aux termes des alin&eacute;as 1 et 2 de l&#39;article L. 122-5, que &quot;les copies ou reproductions strictement r&eacute;serv&eacute;es &agrave; l&#39;usage priv&eacute; du copiste et non destin&eacute;es &agrave; une utilisation collective&quot;.<br />
<br />
<strong>R&eacute;alisation</strong> <strong>:&nbsp;</strong><a href="http://www.xsalto.com"><strong>Xsalto</strong></a></p>
EOF;
	$pages[] = ['alias'=>$params['infotreementions'],
		    'title'=>'Mentions légales',
		    'contents'=>[['tpl'=>'01 - Texte seul',
				  'titsec'=>'© Copyright',
				  'txt1'=>$txt1]]];
      }
      if (!empty($params['infotreeplansite'])){
	$pages[] = ['alias'=>$params['infotreeplansite'],
		    'title'=>'Plan du site',
	'contents'=>['tpl'=>'15 - Plan du site']];
      }
      
      $defpages[] = ['alias'=>$params['infotreebottom'],
		     'title'=>'Menu bas',
		     'pages'=>$pages
		     ];
    }
    
    if (!empty($params['infotreehome']))
      $defpages[] = ['alias'=>$params['infotreehome'],'title'=>'Accueil'];

    // pages distantes
    $pagesd = [
      ['alias'=>'newsletter_ok',
       'title'=>'Inscription/Desinscription newsletter',
       'contents'=>['tpl'=>'01 - Texte seul',
		    'txt1'=>'Votre inscription à la lettre d\'information a bien été prise en compte.']],
      ['alias'=>'cookies',
       'title'=>'Cookies',
       'contents'=>[
	 ['tpl'=>'06 - Texte mis en valeur ', // ?? titre de page
	  'titsec'=>'Utilisation des cookies'],
	 ['tpl'=>'01 - Texte seul',
	  'titsec'=>'Que sont les cookies',
	  'txt1'=><<<EOF
<p>Un cookie est un ensemble de données stock&ées sur votre terminal (ordinateur, tablette, mobile) par le biais de votre navigateur lors de la consultation d'un service en ligne.<br />
Les cookies enregistrés permettent de reconnaître seulement l'appareil que vous êtes en train d'utiliser. Les cookies ne stockent aucune donnée personnelle sensible mais donnent simplement une information sur votre navigation de fa&çon à ce que votre terminal puisse être reconnu plus tard.</p><p>Prenez en compte que les cookies ne peuvent endommager votre terminal et que, en retour, votre disponibilité nous aide à identifier et à résoudre les possibles erreurs qui pourraient surgir. Les cookies permettent d'identifier une session de navigation, et recueillir ainsi, vos préférences. Les cookies ne peuvent lire des informations stockées dans votre ordinateur.</p>
EOF
	  ],
	  ['tpl'=>'01 - Texte seul',
	   'titsec'=>'Quels types de cookies utilise ce site Web ?',
	   'txt1'=><<<EOF
<p><strong>1/ Les cookies de session</strong><br />
Ces cookies sont nécessaires pour le bon usage des pages web, la gestion de la session des utilisateurs, la navigation ininterrompue en rappelant les options de langue ou pays. Ils sont utilisés pour identifier l'utilisateur une fois que celui-ci s'est authentifié. Sans ces cookies, plusieurs des fonctionnalités disponibles ne seraient pas opérationnelles. Ces cookies périment lorsque le navigateur est fermé ou après un mois.</p><p><strong>2/ Les cookies de statistiques</strong><br />
Les cookies analytiques nous permettent de mieux connaître nos internautes et d'améliorer nos services en établissant des statistiques et volumes de fréquentation et d'utilisation.</p>
EOF
	  ]
       ]
      ]
    ];
    
    if (isset($params['infotreecontact'])) // voir aussi contactmoid contact et newcontact
      $pagesd[] = ['alias'=>$params['infotreecontact'].'_ok',
		   'title'=>'Votre demande de renseignement',
		   'contents'=>['tpl'=>'01 - Texte seul',
				'txt1'=>'Votre demande a bien été prise en compte.<br>Nous vous répondons dans les plus brefs délais.']];
    
    if (!empty($params['infotreeerror']))
      $pagesd[] = ['alias'=>$params['infotreeerror'],
		   'title'=>'Page non trouvée',
		   'contents'=>[['tpl'=>'01 - Texte seul',
				 'txt1'=>'<strong>La page que vous cherchez n\'existe pas ou a été supprimée.</strong>']]];
    
    $defpages[] = ['alias'=>'pages_distantes', 'title'=>'Pages distantes','pages'=>$pagesd];

    // liste des gabarits section statitques pages
    $sectionsStatiques = getDB()->fetchAll('select koid as tpl, title as titsec, tab from TEMPLATES where modid=? and gtype=? and title not like "NL - %" and title not like "21%"',
					   [$moid,
					    'page']);
    foreach($sectionsStatiques as &$section){
      foreach(['txt1','txt2','txt3'] as $fn){
	if (empty($section[$fn]))
	  $section[$fn] = static::ipsum($fn);
      }
    }

    // liste des gabarits section statitques pages
    $sectionsNL = getDB()->fetchAll('select koid as tpl, title as titsec from TEMPLATES where modid=? and gtype=? and title like "NL - %"',
			       [$moid,
				'page']);
    
    $defpages[] = ['alias'=>'tests_xsalto',
		   'title'=>'Tests XSALTO - ne pas supprimer',
		   'pages'=>[
		     ['alias'=>'composants-bootstrap',
		      'title'=>'Composants boostrap',
		      'contents'=>['tpl'=>'21 - Composants bootstrap',
				   'titsec'=>'Composants']
		     ],
		     
		     ['alias'=>'tests-des-sections',
		      'title'=>'Page de tests des sections',
		      'contents'=>$sectionsStatiques],
		     
		     ['alias'=>'page-test-newsletter',
		      'title'=>'Page de test de la newsletter',
		      'contents'=>$sectionsNL]
    ]];

    // mise en page par défaut, si elle existe
    $defStyle = getDB()->fetchOne('select koid from '.static::$stylestab.' where title = ?', ["Charte Corail {$mgroup} 1 col"]);

    Module::clearCache();
    
    $itmod = Module::objectFactory($moid);

    static::createPages($itmod, $dsrub, $defpages, $defStyle, null);
    
    return $moid;
    
  }
  /// ipsum
  private static function ipsum($label){
    $ipsum = <<<EOF
Lorem Ipsum è un testo segnaposto utilizzato nel settore della tipografia e della stampa. Lorem Ipsum è considerato il testo segnaposto standard sin dal sedicesimo secolo, quando un anonimo tipografo prese una cassetta di caratteri e li assemblò per preparare un testo campione. È sopravvissuto non solo a più di cinque secoli, ma anche al passaggio alla videoimpaginazione, pervenendoci sostanzialmente inalterato. Fu reso popolare, negli anni ’60, con la diffusione dei fogli di caratteri trasferibili “Letraset”, che contenevano passaggi del Lorem Ipsum, e più recentemente da software di impaginazione come Aldus PageMaker, che includeva versioni del Lorem Ipsum.
Perchè lo utilizziamo?

È universalmente riconosciuto che un lettore che osserva il layout di una pagina viene distratto dal contenuto testuale se questo è leggibile. Lo scopo dell’utilizzo del Lorem Ipsum è che offre una normale distribuzione delle lettere (al contrario di quanto avviene se si utilizzano brevi frasi ripetute, ad esempio “testo qui”), apparendo come un normale blocco di testo leggibile. Molti software di impaginazione e di web design utilizzano Lorem Ipsum come testo modello. Molte versioni del testo sono state prodotte negli anni, a volte casualmente, a volte di proposito (ad esempio inserendo passaggi ironici).
EOF;
    return "$label $ipsum";
  }
  /// création d'un jeux de pages
  protected static function createPages($itmod, $dsrub, $pages, $defStyle, $linkup=null){
    $i=0;
    foreach ($pages as $page) {
      $ret = $dsrub->procInput(['_options'=>['local'=>1],
				'alias'=>$page['alias'],
				'linkup'=>$linkup??null,
				'title'=>$page['title'],
				'style'=>$defStyle,
				'corder'=>$i++,
				'PUBLISH'=>1
      ]);
      if (isset($page['pages'])){
	static::createPages($itmod, $dsrub, $page['pages'], $defStyle, $ret['oid']);
      }
      if (isset($page['contents'])){
	static::addContent($itmod, $page['contents'], $ret['oid']);
      }
      if (isset($page['fonctions'])){
	static::addSectionFunctions($itmod, $page['fonctions'], $ret['oid']);
      }
    }
  }
  /// ajout d'une section fonction à une rubrique
  protected static function addSectionFunctions($itmod, $funcs, $oidit){
    if (!isset($funcs[0]))
      $funcs = [$funcs];
    $i=0;
    foreach($funcs as $func){
      $oidtpl = null;
      if (Kernel::isAKoid($func['tpl'])){
	$oidtpl = $func['tpl'];
      }
      if (!empty($oidtpl)){
	$arInsert = ['oidit'=>$oidit,
		     'oidtpl'=>$oidtpl,
		     'position'=>$i++,
		     'zone'=>null,
		     'section'=>['function'=>$func['section']['function'],
				 'moid'=>$func['section']['moid']]];
	$arInsert = array_merge($arInsert, $func['fields']);
	$ret = $itmod->insertfunction($arInsert);

	list($dyntable) = explode(':',$ret['oid']);
	getDB()->execute("update $dyntable set PUBLISH=1 where koid=?", [$ret['oid']]);
	
      } else {
	Logs::critical(__METHOD__,"tpl not found : {$func['tpl']}");
      }
    }
  }
  /// ajout de section à une rubrique
  protected static function addContent($itmod, $contents, $oidit){
    if (!isset($contents[0]))
      $contents = [$contents];
    $i=0;
    $oidtpl = null;
    foreach($contents as $content){
      $oidtpl = null;
      if (Kernel::isAKoid($content['tpl'])){
	$oidtpl = $content['tpl'];
      } else {
	$oidtpl = getDB()->fetchOne('select koid from TEMPLATES where modid=? and title=?',
				    [$itmod->_moid,$content['tpl']]);
      }
      if (!empty($oidtpl)){
	$itmod->insertSection(['oidit'=>$oidit,
			       'oidtpl'=>$oidtpl,
			       'position'=>$i++,
			       'zone'=>null,
			       'PUBLISH'=>1,
			       'txt1'=>$content['txt1']??null,
			       'txt2'=>$content['txt2']??null,
			       'txt3'=>$content['txt3']??null,
			       'titsec'=>$content['titsec']??null]);
      } else {
	Logs::critical(__METHOD__,"tpl not found : $tpl");
      }
    }    
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
  /// module contacts
  static protected function createContactModule(string $group):int{

    $btab = DSTable::newTableNumber('CCRM');
    $bname = "{$group} - Contacts";
    $modname = "Contacts";
    
    static::createContactTable($btab,$bname);

    if(!System::tableExists('LETTERS'))
      DataSource::createLetters();

    $wd = new \Seolan\Module\Contact\Wizard();
    $moid = $wd->quickCreate($modname,
			     [
			       'table'=>$btab,
			       'group'=>$group,

			       'mailingokfield'=>'emailok',
			       'emailfield'=>'email',
			       'processedfield'=>'pok',
			       'archivefield'=>'arch',
			       
			     ]
    );

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
      ['translatable'=>0,
       'auto_translate'=>0,
       'classname'=>'\Seolan\Model\DataSource\Table\Table',
       'btab'=>$btab,
       'bname'=>[TZR_DEFAULT_LANG=>$bname],
       'own'=>0,
       'publish'=>0
    ]);

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
      ['translatable'=>0,
       'auto_translate'=>0,
       'classname'=>'\Seolan\Model\DataSource\Table\Table',
       'btab'=>$btab,
       'bname'=>[TZR_DEFAULT_LANG=>$bname],
       'own'=>0,
       'publish'=>0
    ]);

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
       'bname'=>[TZR_DEFAULT_LANG=>$bname],
       'own'=>0,
       'publish'=>1
    ]);

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
       'bname'=>[TZR_DEFAULT_LANG=>$bname],
       'own'=>0,
       'publish'=>1
     ]);
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
       'bname'=>[TZR_DEFAULT_LANG=>$bname],
       'own'=>0,
       'publish'=>1
    ]);
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
  /// table du module contact
  static protected function createContactTable(string $btab, string $bname){

    $result = DSTable::procNewSource(
      ['translatable'=>0,
       'auto_translate'=>0,
       'classname'=>'\Seolan\Model\DataSource\Table\Table',
       'btab'=>$btab,
       'bname'=>[TZR_DEFAULT_LANG=>$bname],
       'own'=>0,
       'publish'=>0
    ]);

    // Champs
    DataSource::clearCache(); // référence à la table nouvellement créée
    $ds = DataSource::objectFactoryHelper8($btab);

    $ds->createField('UPD','Dernière mise à jour','\Seolan\Field\Timestamp\Timestamp',0,0,0,0,0,1,0,0,'%');
    $ds->createField('title','Titre','\Seolan\Field\ShortText\ShortText',124,2,0,1,1,1,0,1,'%');
    $ds->createField('body','Question','\Seolan\Field\Text\Text',70,2,0,1,1,1,0,1,'%');
    $ds->createField('email','EMail','\Seolan\Field\ShortText\ShortText',124,2,0,1,1,1,0,1,'%');
    $ds->createField('pok','Traité','\Seolan\Field\Boolean\Boolean',124,2,0,1,1,1,0,1,'%');
    $ds->createField('arch','Archivé','\Seolan\Field\Boolean\Boolean',124,2,0,1,1,1,0,1,'%');
    $ds->createField('emailok','Mail OK','\Seolan\Field\Boolean\Boolean',124,2,0,1,1,1,0,1,'%');
    
  }
  /// table abonnés newsletter
  static protected function createNewsLetterSubscribersTable(string $btab, string $bname){
    $result = DSTable::procNewSource(
      ['translatable'=>1,
       'auto_translate'=>0,
       'classname'=>'\Seolan\Model\DataSource\Table\Table',
       'btab'=>$btab,
       'bname'=>[TZR_DEFAULT_LANG=>$bname],
       'own'=>0,
       'publish'=>0
    ]);

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
       'bname'=>[TZR_DEFAULT_LANG=>"{$group} - Styles / Mises en page"],
       'own'=>0,
       'publish'=>0
    ]);

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
      ['translatable'=>0,
       'auto_translate'=>0,
       'classname'=>'\Seolan\Model\DataSource\Table\Table',
       'btab'=>static::$chartetab,
       'bname'=>[TZR_DEFAULT_LANG=>"{$group} - Charte"],
       'own'=>0,
       'publish'=>0
    ]);
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
