<?php
namespace Seolan\Module\SkiPlan;
class Wizard extends \Seolan\Core\Module\Wizard {

  public function istep1() {
    $this->datadir = dirname(__FILE__).'/data/';
    parent::istep1();
    $this->_module->group = 'Skiplan';
    $this->_module->modulename = 'Skiplan - Module applicatif';
    $this->_module->comment['FR'] = 'Skiplan - Module permettant la récupération et l\'affichage des données skiplan';
    $this->_module->comment['GB'] = 'Skiplan - Module for fetching and displaying Skiplan data';
    $this->_options->setOpt('Station', 'station', 'text');
    $this->_options->setOpt('Url', 'url', 'text', ['size' => 80]);
    $this->_module->url = 'http://www.skiplan.com/php/genererXml.php?pays=france&region=alpes&v=2&station=%station%';
  } // istep1

  public function iend() {
    $this->MOIDs = [];
    $this->updateSchema('', $this->MOIDs);

    if($this->_module->url == 'http://www.skiplan.com/php/genererXml.php?pays=france&region=alpes&v=2&station=%station%' && !empty($this->_module->station))
      $this->_module->url = str_replace('%station%', $this->_module->station, $this->_module->url);

    $this->_module->tbpistes = 'skiplanPistes';
    $this->_module->tbliaisons = 'skiplanLiaisons';
    $this->_module->tbrems = 'skiplanRemontees';
    $this->_module->tbmeteo = 'skiplanMeteo';
    $this->_module->tbmeteociel = 'skiplanMeteoCiel';
    $this->_module->tbmeteoavalanche = 'skiplanMeteoAvalanche';
    $this->_module->tbSecteurs = 'skiplanSecteurs';
    $this->_module->tbEtats = 'skiplanEtats';
    $this->_module->tbNiveaux = 'skiplanPistesNiveaux';
    $this->_module->tbStation = 'skiplanStation';
    $this->_module->tbModPistes = 'skiplanStationModules';
    $this->_module->tbStationPistes = 'skiplanStationPistes';
    $this->_module->tbReomnteesTypes = 'skiplanRmtTp';
    $this->_module->tbReference  = 'skiplanEtatsStation';
    $this->_module->tbPistesTypes  = 'skiplanPistesTypes';
    
    $this->_module->Secteur = $this->MOIDs['skiplanSecteurs'];
    $this->_module->Station = $this->MOIDs['skiplanStation'];
    $this->_module->StationPistes = $this->MOIDs['skiplanStationPistes'];
    $this->_module->reomteesListes = $this->MOIDs['skiplanRemontees'];
    $this->_module->modulePistes = $this->MOIDs['skiplanStationModules'];
    $this->_module->moduleRemontees = $this->MOIDs['skiplanRmtTp'];

    $this->createLibelles();

    $this->importData();

    $moid = parent::iend();

    \Seolan\Core\Module\Module::clearCache();
    $mod = \Seolan\Core\Module\Module::objectFactory($moid);

    //add Templates 
    $this->addTemplates($mod);

    return $moid;

  }
  public static function updateSchema($prefix = '', &$MOIDs = null) {
    $prefix = ''; // Laisser vide, bug de importSourcesAndFields depuis excels

    // Augmentation temporaire du temps d'éxecution de php
    ini_set('max_execution_time', 0);

    // Modules utilisés par skiplan et xlsx de crétation des tables, champs et valeurs
    $modules = [
      ['name' => 'Secteur', 'table' => 'skiplanSecteurs'],
      ['name' => 'Pistes - Liste', 'table' => 'skiplanPistes'],
      ['name' => 'Remontées - Liste', 'table' => 'skiplanRemontees'],
      ['name' => 'Météo', 'table' => 'skiplanMeteo'],
      ['name' => 'Station', 'table' => 'skiplanStation'],
      ['name' => 'Station - Pistes', 'table' => 'skiplanStationPistes'],
      ['name' => 'Station - Modules', 'table' => 'skiplanStationModules'],
      ['name' => 'Liaisons - Liste', 'table' => 'skiplanLiaisons'],
      ['name' => 'Références', 'table' => 'skiplanEtatsStation'],
      ['name' => 'Pistes - Niveau', 'table' => 'skiplanPistesNiveaux'],
      ['name' => 'Pistes - Types', 'table' => 'skiplanPistesTypes'],
      ['name' => 'Météo - ciel', 'table' => 'skiplanMeteoCiel'],
      ['name' => 'Météo - avalanche', 'table' => 'skiplanMeteoAvalanche'],
      ['name' => 'État des pistes et remontées', 'table' => 'skiplanEtats'],
      ['name' => 'Remontées - Types', 'table' => 'skiplanRmtTp'],
    ];
    
    $mod_ds = new \Seolan\Module\DataSource\DataSource(array('tplentry'=>TZR_RETURN_DATA));

    $MOIDs = [];
    $datadir = dirname(__FILE__).'/data/';

    foreach($modules as $module) {
      \Seolan\Core\Logs::notice(__METHOD__, "create skiplan data module : '{$module['name']}', '{$module['table']}', '{$datadir}{$module['table']}.xlsx'");
      if (!file_exists($datadir.$module['table'].'.xlsx')){
	\Seolan\Core\Logs::critical(__METHOD__, "file does not exists '{$datadir}{$module['table']}.xlsx'");
      }
	
      // création de la table par import sources champs sets et données éventuelles
      $param = [
        'file' => $datadir.$module['table'].'.xlsx',
        'prefixSQL' => $prefix,
        'endofline' => '\n',
// 	'delallfields'=>true,
	'delotherfields'=>true,
      ];
      $message = $mod_ds->importSourcesAndFields($param);
      $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($module['table']);
      if (isset($ds->desc['PUBLISH'])) {
        $ds->procEditField([
          'field' => 'PUBLISH', 'options' => ['default' => '1']
        ]);
      }
      $MOIDs[$module['table']] = static::addModule($prefix, $module['table'], $module['name'], $message);

    } // foreach

    // restauration des données du wizard en cour
    setSessionVar('ModWd', $mywddata);

    return $message;
  } // updateSchema

  /**
   * Permet de rajouter le module lié a une table de skiplan
   **/
  private static function addModule($prefix, $table, $name, &$message, $prefixname='SKIPLAN') {
    \Seolan\Core\Logs::notice(__METHOD__,"$prefix, $table, $name, $message, $prefixname");

    // existe dejà ? ou ajout
    $modulesUsingTable = \Seolan\Core\Module\Module::modulesUsingTable($prefix.$table,true,false,false,true);
    if(count($modulesUsingTable)==0){
      $moid = \Seolan\Library\Upgrades::addModule($prefix.$table,
						  $name,
						  'Skiplan',
						  '\Seolan\Module\Table\Table');
      \Seolan\Library\Upgrades::editModuleProperty($moid, 'commment', 
						   ['FR'=>$prefixname.' - '.$name,
						    'GB'=>$prefixname.' - '.$name]);
      $modulesUsingTable = $moid;
      $message .= 'Module '.$name.' créé: moid $modulesUsingTable<br>';
    }else{
      $modulesUsingTable = array_keys($modulesUsingTable);
      $modulesUsingTable =  $modulesUsingTable[0];
    }
    return $modulesUsingTable;
  } // addModule

  /**
  * Créer les libellées utilisées dans les templates
  **/
  private function createLibelles() {
    $XLabels = new \Seolan\Core\Labels();

    $XLabels->set_labels([
      'skiplanNiveau' => ['TITLE' => 'Skiplan niveau', 'FR' => 'Niveau', 'GB' => 'Level'],
      'skiplanPiste' => ['TITLE' => 'Skiplan piste', 'FR' => 'Piste', 'GB' => 'Track'],
      'skiplanRemontee' => ['TITLE' => 'Skiplan Remontée', 'FR' => 'Remontée', 'GB' => 'Lift'],
      'skiplanEtat' => ['TITLE' => 'Skiplan État', 'FR' => 'État', 'GB' => 'State'],
      'skiplanLiaison' => ['TITLE' => 'Skiplan liaison', 'FR' => 'Liaison', 'GB' => 'Connection'],
      'skiplanLiaisons' => ['TITLE' => 'Skiplan liaisons', 'FR' => 'Liaisons', 'GB' => 'Connections'],
      'skiplanWeek' => ['TITLE' => 'Skiplan semaine', 'FR' => 'Semaine', 'GB' => 'Week'],
      'skiplanLegendeetat' => ['TITLE' => 'Skiplan légende état', 'FR' => 'Légende des états', 'GB' => 'Legend of status'],
      'skiplanLegendelvl' => ['TITLE' => 'Skiplan légende niveaux', 'FR' => 'Légende des niveaux', 'GB' => 'Legend of level'],
      'skiplanLegenderemontee' => ['TITLE' => 'Skiplan légende remontées', 'FR' => 'Légende des remontées', 'GB' => 'Legend of ski lifts'],
      'skiplanLegendepiste' => ['TITLE' => 'Skiplan légende piste', 'FR' => 'Légende des pistes', 'GB' => 'Legend of ski tracks'],
      'skiplanWeatherReport' => ['TITLE' => 'Skiplan titre bulletin météo', 'FR' => 'Bulletin météo', 'GB' => 'Weather report'],
      'skiplanMoreinfo' => ['TITLE' => 'Skiplan Plus infos météo', 'FR' => '+ d\'informations', 'GB' => '+ More informations'],
      'skiplanClose' => ['TITLE' => 'Skiplan fermer météo', 'FR' => 'Fermer', 'GB' => 'Close'],
      'skiplanTomorrow' => ['TITLE' => 'Skiplan demain', 'FR' => 'Demain', 'GB' => 'Tomorrow'],
      'skiplanToday' => ['TITLE' => 'Skiplan aujourd\'hui', 'FR' => 'Aujourd\'hui', 'GB' => 'Today'],
    ],'global', false);

    return true;
  } // createLibelles

  // Fonction récupéré sur stackoverflow, fait le taf
  private function recurse_copy($src, $dst) { 
      $dir = opendir($src); 
      @mkdir($dst); 
      while(false !== ( $file = readdir($dir)) ) { 
          if (( $file != '.' ) && ( $file != '..' )) { 
              if ( is_dir($src . '/' . $file) ) { 
                  $this->recurse_copy($src . '/' . $file,$dst . '/' . $file); 
              } 
              else { 
                  copy($src . '/' . $file,$dst . '/' . $file); 
              } 
          } 
      } 
      closedir($dir); 
  }  // recurse_copy

  // Permet de copier les dossiers contenant les images de la météo
  private function importData() {
    $datadir = $this->datadir.'data/';
    $www_data = TZR_WWW_DIR.'data/';
    $dirtocopy = ['skiplanMeteoCiel', 'skiplanMeteoAvalanche'];
    
    foreach($dirtocopy as $dir) {
      if(!file_exists($www_data.$dir)) {
	\Seolan\Core\Logs::notice(__METHOD__, $dir);
        $this->recurse_copy($datadir.$dir, $www_data.$dir);
      } // if
    } // foreach
  } // importData

  // Copié de xmodwebresawd
  static function addTemplates($module){
    $message = 'Ajout des templates\n';
    $tpl = [
      'meteo' => ['title'=>'Skiplan Météo', 'name'=>'meteo.html','content'=>'<%include file="Module/SkiPlan.meteo.html"%>','function'=>'\Seolan\Module\SkiPlan\SkiPlan::meteo'],
      'piste' => ['title'=>'Skiplan Pistes', 'name'=>'pistes.html','content'=>'<%include file="Module/SkiPlan.pistes.html"%>','function'=>'\Seolan\Module\SkiPlan\SkiPlan::pistes']
    ];
    
    $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
    foreach($tpl as $name=>$value){
      $file = TZR_TMP_DIR.$value['name'];
      file_put_contents($file,$value['content']); 
      
      $insert = $x->procInput([
        'title'=>$value['title'],
        'modid'=>'',
        'gtype'=>'function',
        'tab'=>'',
        'functions'=>$value['function'],
        'disp'=>$file,
        'modidd'=>$module->_moid,
        'options'=> [
          'disp'=>['del'=>false],
          'edit'=>['del'=>false]
        ],
        '_updateifexists'=>1,
        'newoid'=>'TEMPLATES:'.$name.$module->_moid
      ]);
      $message .= $insert['message'].'\n';
    }
    return $message; 
  } // addTemplates

}
