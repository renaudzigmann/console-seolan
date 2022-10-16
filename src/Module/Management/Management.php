<?php
namespace Seolan\Module\Management;
// Module des gestions des fonctions d'administration.
//
use \Seolan\Library\Upgrades;
use \Seolan\Core\Param;
use \Seolan\Core\Shell;
class Management extends \Seolan\Core\Module\Module {
  public static $singleton = true;
  static protected $iconcssclass='csico-admin';
  function __construct($ar=NULL) {
    $ar['moid']=self::getMoid(XMODADMIN_TOID);
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Management_Management');
    $this->group=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','systemproperties');
    $this->modulename=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Management_Management','modulename');
  }
  /// Chargement de l'infotree du menu admin
  public static function getAdminXmodInfotree(?array $ar=null){
    return \Seolan\Core\Module\Module::singletonFactory(XMODBACKOFFICEINFOTREE_TOID);
  }
  /// Initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('object_sec');
  }

  /// Description des niveaux d'accès aux fonctions
  function secGroups($function, $group=NULL) {
    $g=array('emptyFileCache'=>array('list','ro','rw','rwv','admin'),
             'emptyRightCache'=>array('list','ro','rw','rwv','admin'),
	     'getInfos'=>array('ro','rw','rwv','admin'),
	     'tzrInfo'=>array('admin'),
	     'browseCheckpoints'=>array('admin'),
	     'delCheckpoint'=>array('admin'),
	     'newCheckpoint'=>array('admin'),
	     'newCheckpointBatch'=>array('admin'),
	     'newModule'=>array('admin'),
	     'modulesList'=>array('none','list','ro','rw','rwv','admin'),
	     'restoreCheckpoint'=>array('admin'),
	     'restoreCheckpointBatch'=>array('admin'),
	     'downloadCheckpoint'=>array('admin'),
	     'portail'=>array('list','ro','rw','rwv','admin'),
	     'home'=>array('list','ro','rw','rwv','admin'),
	     'iniEdit'=>array('admin'),
	     'iniProcEdit'=>array('admin'),
	     'iniDel'=>array('admin'),
	     'iniAdd'=>array('admin'),
             'compareBase'=>array('admin'),
             // affiche un visuel complet de l'état du site (utile pour un check de mise en ligne)
	     'checklist'=>array('admin'),
	     // fait un check IP autorisée
	     'publicConfiguration'=>array('none', 'list', 'ro','rw','rwv','admin'),
             'ajaxModuleMenu'=>array('none','list','ro','rw','rwv','admin'),
	     'genDocumentation'=>array('list','ro','rw','rwv','admin'),
	     'procGenDocumentation'=>array('list','ro','rw','rwv','admin'),
	     'preUpdates'=>['admin'],
	     'procApplyUpgrades'=>['admin']
    );
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Liste des actions du module
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my);
    
    if($this->secure('','tzrInfo')){
      $o1=new \Seolan\Core\Module\Action($this,'tzrinfo',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','tzrinfo','text'),
			    '&moid='.$this->_moid.'&_function=tzrInfo&template=Module/Management.tzrInfo.html&tplentry=br','edit');
      $o1->menuable=true;
      $my['tzrinfo']=$o1;
    }
    if($this->secure('','iniEdit')){
      $o1=new \Seolan\Core\Module\Action($this,'iniEdit',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','config'),
			    '&moid='.$this->_moid.'&function=iniEdit&template=Module/Management.iniEdit.html','menu');
      $o1->containable=true;
      $o1->setToolbar('Seolan_Core_General',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','config'));
      $my['config']=$o1;
    }
    if($this->secure('','browseCheckpoints')){
      $o1=new \Seolan\Core\Module\Action($this,'cp',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','checkpoints','text'),
			    '&moid='.$this->_moid.'&function=browseCheckpoints&template=Module/Management.browseCheckpoints.html','edit');
      $o1->menuable=true;
      $my['cp']=$o1;
    }
    if($this->secure('','modulesList')){
      $o1=new \Seolan\Core\Module\Action($this,'mods',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','modules'),
			    '&moid='.$this->_moid.'&function=modulesList&template=Module/Management.modList.html&tplentry=modules&noreload=1&refresh=1','edit');
      $o1->menuable=true;
      $my['mods']=$o1;
    }

    if(\Seolan\Core\Module\Module::moduleExists('', XMODCACHE_TOID) && $this->secure('','emptyFileCache')){
      $o1=new \Seolan\Core\Module\Action($this,'filecacheclean',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','filecacheclean'),
			    '&moid='.$this->_moid.'&function=emptyFileCache&template=Core.message.html','more');
      $o1->menuable=true;
      $my['filecacheclean']=$o1;
    }
    if($this->secure('','emptyRightCache')){
      $o1=new \Seolan\Core\Module\Action($this,'rightcacheclean',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','rightcacheclean'),
			    '&moid='.$this->_moid.'&function=emptyRightCache&template=Core.message.html','more');
      $o1->menuable=true;
      $my['rightcacheclean']=$o1;
    }
    if($this->interactive){
        $o1=new \Seolan\Core\Module\Action($this,'info',$this->getLabel(),
			    '&moid='.$this->_moid.'&_function=getInfos&template=Core/Module.infos.html&tplentry=br');
      $my['stack'][]=$o1;
    }
    if($this->secure('','checklist')){
      $o1=new \Seolan\Core\Module\Action($this,'checklist','Checklist',
                            '&moid='.$this->_moid.'&function=checklist&template=Module/Management.checklist.html','more');
      $o1->menuable=true;
      $my['checklist']=$o1;
    }
    if($this->secure('','compareBase')){
      $o1=new \Seolan\Core\Module\Action($this,'comparebase',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','comparebase'),
          '&moid='.$this->_moid.'&function=compareBase&tplentry=br&template=Module/Management.compareBase.html','more');
      $o1->menuable=true;
      $my['compareBase']=$o1;
    }
    if($this->secure('','genDocumentation')){
      $o1=new \Seolan\Core\Module\Action($this,'genDocumentation',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','documentation'),
          '&moid='.$this->_moid.'&function=genDocumentation&tplentry=br&template=Module/Management.documentation.html','more');
      $o1->menuable=true;
      $my['genDocumentation']=$o1;
    }
    if($this->secure('','preUpdates')){
      $o1=new \Seolan\Core\Module\Action($this,'updates',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','updates'),
          "&moid={$this->_moid}&function=preUpdates&tplentry=upgr&template=Module/Management.updates.html",'actions');
      $o1->menuable=true;
      $my['updates']=$o1;
    }
  }
  /**
   * Upgrades, via Lib./Upgrades 
   */
  public function procApplyUpgrades($ar=null){

    set_time_limit(0);

    ob_start();

    Upgrades::applyUpgrades();

    $messages = ob_get_clean();

    $this->_setSession('procmess', $messages);
    
    Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).
						    http_build_query(['function'=>'preUpdates',
								      'moid'=>$this->_moid,
								      'template'=>'Module/Management.updates.html',
								      'tplentry'=>'upgr']));
    
  }
  /**
   * gestion de la version console et des mises à jour
   */
  public function preUpdates($ar=null){
    $p = new Param($ar, ['tplentry'=>'upgr']);
    list($pending, $critical) = Upgrades::pendingUpgrades();
    $comments = [];
    foreach($pending as $upgradeno=>&$upgrades){
      foreach($upgrades as &$upgrade){
	// mettre ça dans Lib.\Upgrades ?
	list($classname) = $upgrade;
	list($file, $fname, $cname) = Upgrades::getUpgradeParameters($classname, $upgradeno);
	$comment = '';
	try{
	  if ((include_once($file)) && function_exists($cname)){
	    $comment = call_user_func($cname);
	  }
	} catch(\Throwable $t){
	  $comment = '';
	}
	$upgrade[] = $comment;
      }
    }
    $res = ['pending'=>$pending,
	    'critical'=>$critical];

    if ($this->_issetSession('procmess'))
      $res['procmess'] = $this->_getSession('procmess');
    $this->_clearSession('procmess');
    
    return Shell::toScreen1($p->get('tplentry'), $res);
  }
  
  function compareBase($ar = NULL) {
    $p = new \Seolan\Core\Param($ar);
    $config_path = $_SERVER['DOCUMENT_ROOT'] . '../tzr/compareBase.json';

    if ($p->get('config')) {
      $envs = json_decode($p->get('config'), true);
      file_put_contents($config_path, json_encode($envs, JSON_PRETTY_PRINT));

      $this->compareDict($envs);
      exit(1);
    } else {
      if (file_exists($config_path)) {
        $envs = json_decode(file_get_contents($config_path), true);
      } else {
        $envs = [
            'dev' => [
                'DATABASE_USER' => 'x',
                'DATABASE_PASSWORD' => 'x',
                'DATABASE_HOST' => 'x',
                'DATABASE_NAME' => 'x',
                'color' => 'FF9090'
            ],
            'rec' => [
                'DATABASE_USER' => 'y',
                'DATABASE_PASSWORD' => 'y',
                'DATABASE_HOST' => 'y',
                'DATABASE_NAME' => 'y',
                'color' => '90FF90'
            ],
            'prod' => [
                'DATABASE_USER' => 'z',
                'DATABASE_PASSWORD' => 'z',
                'DATABASE_HOST' => 'z',
                'DATABASE_NAME' => 'z',
                'color' => '9090FF'
            ]
        ];
      }
    }

    \Seolan\Core\Shell::toScreen2('config', 'path', $config_path);
    \Seolan\Core\Shell::toScreen2('config', 'content', json_encode($envs, JSON_PRETTY_PRINT));
  }

  function compareDict($envs) {
    set_time_limit(0);

    /*
    $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
    $cacheSettings = array( ' memoryCacheSize ' => '10KB');

    PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
     */

    $tables = [];
    $fprops = [];
    $foptions = [];
    foreach ($envs as $envname => $env) {
      $conn = $this->EnvNewConnection($env);
      $tablesrs = $conn->select('select * from BASEBASE order by btab');
      while ($table = $tablesrs->fetch()) {
        $fieldsrs = $conn->select('select * from DICT where dtab=? order by field', [$table['BTAB']]);
        if (!isset($tables[$table['BTAB']])) {
          $tables[$table['BTAB']] = [];
        }
        while ($field = $fieldsrs->fetch()) {
          $fn = $field['FIELD'];
          if (!isset($tables[$table['BTAB']][$fn])) {
            $tables[$table['BTAB']][$fn] = [];
          }
          $tables[$table['BTAB']][$fn][$envname] = $field;
          $fprops = array_unique(array_merge(array_keys($field), $fprops));
          $tables[$table['BTAB']][$fn][$envname]['_options'] = [];

          if (isset($field['DPARAM'])) {
            //$oxml = simplexml_load_string($field['DPARAM']);
            $oxml = json_decode($field['DPARAM']);
            if ($oxml) {
              foreach ($oxml as $o) {
                if (!in_array((String) $o['name'], $foptions)) {
                  $foptions[] = (String) $o['name'];
                }
                $tables[$table['BTAB']][$fn][$envname]['_options'][(String) $o['name']] = (String) $o->value;
              }
            }
          }
        }
      }
    }

    $row = 2;
    $ss = new \PHPExcel();
    $ss->setActiveSheetIndex(0);
    $ws = $ss->getActiveSheet();
    $ws->setTitle('DICT');
    $ws->SetCellValue('A1', 'env');
    $ws->SetCellValue('B1', 'table');

    $row = 1;
    $col = 2;
    foreach ($fprops as $fprop) {
      if ($fprop == 'DPARAM')
        continue;
      $ws->setCellValueExplicitByColumnAndRow($col++, $row, $fprop);
    }
    foreach ($foptions as $foption) {
      $ws->setCellValueExplicitByColumnAndRow($col++, $row, $foption);
    }

    foreach ($tables as $btab => $table) {
      foreach ($table as $fn => $fenv) {
        foreach ($envs as $env => $db) {
          $row++;
          $col = 0;
          $ws->setCellValueExplicitByColumnAndRow($col++, $row, $env);
          $ws->setCellValueExplicitByColumnAndRow($col++, $row, $btab);
          if (isset($fenv[$env])) {
            foreach ($fprops as $fprop) {
              if ($fprop == 'DPARAM')
                continue;
              $ws->setCellValueExplicitByColumnAndRow($col++, $row, $fenv[$env][$fprop]);
              if ($this->compareValFromBase($fenv, $fprop, $envs)) {
                $ws->getStyle(\PHPExcel_Cell::stringFromColumnIndex($col - 1) . $row)->applyFromArray(
                        array(
                            'fill' => array(
                                'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                                'color' => array('rgb' => $envs[$env]['color'])
                            )
                        )
                );
              }
            }
            foreach ($foptions as $foption) {
              $ws->setCellValueExplicitByColumnAndRow($col++, $row, $fenv[$env]['_options'][$foption]);
              if ($this->compareValFromBase($fenv, $foption, $envs)) {
                $ws->getStyle(\PHPExcel_Cell::stringFromColumnIndex($col - 1) . $row)->applyFromArray(
                        array(
                            'fill' => array(
                                'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                                'color' => array('rgb' => $envs[$env]['color'])
                            )
                        )
                );
              }
            }
          }
        }
      }
    }

    $ws2 = $ss->createSheet(1);
    $ws2->setTitle('Modules');
    $this->compareModules($envs, $ws2);

    $ws2 = $ss->createSheet(2);
    $ws2->setTitle('Labels');
    $this->compareLabels($envs, $ws2);

    sendPHPExcelFile($ss, 'xl', 'export');
  }

  function compareModules($envs, &$ws) {
    $modules = [];
    $mprops = [];
    $moptions = [];
    foreach ($envs as $envname => $env) {
      $conn = $this->EnvNewConnection($env);
      $modrs = $conn->select('select * from MODULES order by moid');
      while ($mod = $modrs->fetch()) {
        if (!isset($modules[$mod['MOID'] . $mod['MODULE']])) {
          $modules[$mod['MOID'] . $mod['MODULE']] = [];
        }
        $modules[$mod['MOID'] . $mod['MODULE']][$envname] = $mod;
        $modules[$mod['MOID'] . $mod['MODULE']][$envname]['_options'] = [];
        $mprops = array_unique(array_merge(array_keys($mod), $mprops));
        if (isset($mod['MPARAM'])) {
            if(substr($mod['MPARAM'],0,5)=='<?xml') $oxml = simplexml_load_string($mod['MPARAM']); //si le mparam est écrit en xml 
            elseif(substr($mod['MPARAM'],0,3)=="[{\"") $oxml = json_decode($mod['MPARAM']); //sinon si le mparam est écrit en json
          if ($oxml) {
            foreach ($oxml as $o) {
              if (!in_array((String) $o['name'], $moptions)) {
                $moptions[] = (String) $o['name'];
              }
              $modules[$mod['MOID'] . $mod['MODULE']][$envname]['_options'][(String) $o['name']] = (String) $o->value;
            }
          }
        }
      }
    }
    $ws->SetCellValue('A1', 'env');
    $ws->SetCellValue('B1', 'table');

    $row = 1;
    $col = 2;
    foreach ($mprops as $mprop) {
      $ws->setCellValueExplicitByColumnAndRow($col++, $row, $mprop);
    }
    foreach ($moptions as $moption) {
      $ws->setCellValueExplicitByColumnAndRow($col++, $row, $moption);
    }

    foreach ($modules as $mname => $menv) {
      foreach ($envs as $env => $db) {
        $row++;
        $col = 0;
        $ws->setCellValueExplicitByColumnAndRow($col++, $row, $env);
        $ws->setCellValueExplicitByColumnAndRow($col++, $row, $mname);
        if (isset($menv[$env])) {
          foreach ($mprops as $mprop) {
            $ws->setCellValueExplicitByColumnAndRow($col++, $row, $menv[$env][$mprop]);
            if ($this->compareValFromBase($menv, $mprop, $envs)) {
              $ws->getStyle(\PHPExcel_Cell::stringFromColumnIndex($col - 1) . $row)->applyFromArray(
                      array(
                          'fill' => array(
                              'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                              'color' => array('rgb' => $envs[$env]['color'])
                          )
                      )
              );
            }
          }
          foreach ($moptions as $moption) {
            $ws->setCellValueExplicitByColumnAndRow($col++, $row, $menv[$env]['_options'][$moption]);
            if ($this->compareValFromBase($menv, $moption, $envs)) {
              $ws->getStyle(\PHPExcel_Cell::stringFromColumnIndex($col - 1) . $row)->applyFromArray(
                      array(
                          'fill' => array(
                              'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                              'color' => array('rgb' => $envs[$env]['color'])
                          )
                      )
              );
            }
          }
        }
      }
    }
  }

  function compareLabels($envs, &$ws) {
    $labels = [];
    $labsFields = [];
    foreach ($envs as $envname => $env) {
      $conn = $this->EnvNewConnection($env);
      $labelsrs = $conn->select('select VARIABL, LANG, TITLE, LABEL, PICTO from LABELS order by VARIABL');
      while ($lab = $labelsrs->fetch()) {
        $labels[$lab['VARIABL']][$lab['LANG']][$envname] = $lab;
        $labsFields = array_keys($lab);
      }
    }

    $ws->SetCellValue('A1', 'env');

    $row = 1;
    $col = 1;
    foreach ($labsFields as $field) {
      $ws->setCellValueExplicitByColumnAndRow($col++, $row, $field);
    }

    foreach ($labels as $var => $lab1) {
      foreach ($lab1 as $lang => $lab2) {
        foreach ($lab2 as $env => $lab3) {
          $row++;
          $col = 0;
          $ws->setCellValueExplicitByColumnAndRow($col++, $row, $env);
          foreach ($lab3 as $fieldName => $val) {
            $ws->setCellValueExplicitByColumnAndRow($col++, $row, $val);
            if ($this->compareValFromBase($labels[$var][$lang], $fieldName, $envs)) {
              $ws->getStyle(\PHPExcel_Cell::stringFromColumnIndex($col - 1) . $row)->applyFromArray(array(
                  'fill' => array(
                      'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                      'color' => array('rgb' => $envs[$env]['color'])
                  )
              ));
            }
          }
        }
      }
    }
  }

  function compareValFromBase($data, $field, $envs) {
    return (
             ($envs['rec'] && $envs['dev']  && $data['rec'][$field] != $data['dev'][$field] ) ||
             ($envs['rec'] && $envs['prod'] && $data['rec'][$field] != $data['prod'][$field] ) ||
             ($envs['dev'] && $envs['prod'] && $data['dev'][$field] != $data['prod'][$field] ) ||
             ($envs['rec']['_options'] && $envs['dev']['_options']  && $data['rec']['_options'][$field] != $data['dev']['_options'][$field] ) ||
             ($envs['rec']['_options'] && $envs['prod']['_options'] && $data['rec']['_options'][$field] != $data['prod']['_options'][$field] ) ||
             ($envs['dev']['_options'] && $envs['prod']['_options'] && $data['dev']['_options'][$field] != $data['prod']['_options'][$field] )
           );
  }

  function EnvNewConnection($db, $dsn = NULL) {
    @list($host, $port) = explode(':', $db['DATABASE_HOST']);
    if (empty($dsn))
      $dsn = 'mysql:host=' . $host . ';dbname=' . $db['DATABASE_NAME'];
    if ($port)
      $dsn .= ';port=' . $port;
    try {
      $conn = new \Seolan\Library\Database($dsn, $db['DATABASE_USER'], $db['DATABASE_PASSWORD'], array(
          \PDO::ATTR_PERSISTENT => false,
          \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
          \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
          \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
      ));
    } catch (exception $e) {
      \Seolan\Core\Logs::critical('DBNewConnection', ' error ' . $e->getMessage());
      die();
    }

    if (TZR_TIMEZONE != 'Europe/Paris') {
      include_once('/locales/timezones.inc');
      if (isset($db['tz_array'][TZR_TIMEZONE])) {
        $offset = $db['tz_array'][TZR_TIMEZONE][date('I')];
        $conn->exec("SET time_zone='$offset'");
      }
    }
    return $conn;
  }

  /// Edition du local.ini
  function iniEdit($ar=NULL){
    $ini=new \Seolan\Core\Ini();
    return $ini->edit($ar);
  }

  /// Sauvegarde les modifications du local.ini
  function iniProcEdit($ar=NULL){
    $ini=new \Seolan\Core\Ini();
    return $ini->procEdit($ar);
  }

  /// Supprime une varaible du local.ini
  function iniDel($ar=NULL){
    $ini=new \Seolan\Core\Ini();
    return $ini->delVariable($ar);
  }

  /// Ajoute une variable au local.ini
  function iniAdd($ar=NULL){
    $ini=new \Seolan\Core\Ini();
    return $ini->addVariable($ar);
  }

  /// Vide le cache fichier (cache des htmls du site)
  function emptyFileCache($ar=NULL) {
    $cache = \Seolan\Core\Module\Module::singletonFactory(XMODCACHE_TOID);
    $cache->clear();
    \Seolan\Core\Shell::toScreen2('','message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','cachecleaned','text'));
  }

  /// Vide le cache des droits
  function emptyRightCache($ar=NULL){
    \Seolan\Core\User::clearDbSessionDataAndRightCache();
    \Seolan\Core\Shell::toScreen2('','message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','cachecleaned','text'));
    clearSessionVar(TZR_SESSION_PREFIX.'modules');
    clearSessionVar(TZR_SESSION_PREFIX.'modmenu');
    setSessionVar('_reloadmods',1);
    setSessionVar('_reloadmenu',1);
  }

  /// Infos générale sur l'état de la console
  /*
  function &info($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get('tplentry');
    $br = parent::getInfos(array('tplentry'=>TZR_RETURN_DATA));
    //$br=array();
    $br['workspacesize']= \Seolan\Core\DbIni::get('xmodadmin:workspacesize',$workspace);
    return \Seolan\Core\Shell::toScreen1($tplentry,$br);
  }
  */
  function getInfos($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get('tplentry');
    $br = parent::getInfos(array('tplentry'=>TZR_RETURN_DATA));
    $workspace = \Seolan\Core\DbIni::get('xmodadmin:workspacesize');
    $size = round($workspace[0]/1000000 ,2);
    $date = \Seolan\Field\Timestamp\Timestamp::dateFormat($workspace[1]);
    $br['infos']['workspacexsize']= (object)array(
      'label'=> \Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','workspacesize','text'),
      'html'=> "$size Go ($date)"
    );
    return \Seolan\Core\Shell::toScreen1($tplentry,$br);
  }

  /// Infos système
  function tzrInfo($ar=NULL){
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=getInfos&template=Core/Module.infos.html&tplentry=br';
  }

  /// Génére la page d'accueil de l'administration
  function portail($ar=NULL) {
    $langdata=\Seolan\Core\Shell::getLangData();
    $mod=\Seolan\Module\Management\Management::getAdminXmodInfotree(array());
    \Seolan\Core\Logs::debug(__METHOD__);
    if($mod->secure('','viewpage')){
      $u=\Seolan\Core\User::get_user();
      $mod->home(array('maxlevel'=>1,'tplentry'=>'menu','do'=>'showtree','aliastop'=>$u->botop(),'norubric'=>true));
      $mod->home(array('maxlevel'=>1,'tplentry'=>'menub','do'=>'showtree','aliastop'=>'bottom','norubric'=>true));
      $mod->viewpage(array('alias'=>$u->bohome(),'tplentry'=>'it','toc'=>false,'_notrad'=>1,'LANG_DATA'=>\Seolan\Core\Shell::getLangUser()));
    }

    $mod->secObjectAccess('',$langdata,'','infotreeadmin');
    $this->secObjectAccess('',$langdata,'','admin');
    // Signets et corbeille des taches
    $portlets = \Seolan\Core\Module\Module::portlets(array('tplentry'=>'home','bookmarks'=>true,'allmodules'=>true,'custom'=>true));
    $justlogged = removeSessionVar('justlogged');
    if ($justlogged == 1){
      foreach($portlets['bookmarks'] as $b){
	if ($b['autostart'] == 'on'){
	  \Seolan\Core\Shell::toScreen1('autostartbookmark',($foo=['num'=>$b['key']]));
	  break;
	}
      }
    }
    // moteur de recherche (v1 v2 ou aucun)
    if (\Seolan\Library\SolR\Search::solrActive()){
      $classname = \Seolan\Library\SolR\Search::instanceClassname();
      $classname::portlet();
    }
    // Application accessibles
    if(TZR_USE_APP) {
      Shell::toScreen2('allowed', 'apps', \Seolan\Core\Application\Application::getAllowedApps());
      Shell::toScreen2('choosed', 'app', \Seolan\Module\Application\Application::getPrioritisedsAppsKOIDs());
    }
  }

  /// Création d'un nouveau module
  function newModule($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $class=$p->get('class');
    $c=new $class();
    return $c->irun($ar);
  }

  /// Liste des modules existants
  function modulesList($ar=NULL){
    $ar['withmodules'] = true;
    return \Seolan\Core\Module\Module::modlist($ar);
  }
  
  /// Parcours les points de sauvegarde
  function browseCheckpoints($ar=NULL){
    $datedef=new \Seolan\Field\DateTime\DateTime();
    $checkdir=TZR_VAR2_DIR.'checkpoints/';
    $dirs=array();
    if($dd=opendir($checkdir)) {
      while(($file=readdir($dd))!==false) {
        if($file!='.' && $file!='..' && is_dir($checkdir.$file)) {
	  $size=0;
	  $db=$data=false;
	  if(file_exists($checkdir.$file.'/database.sql.gz')){
	    $size=filesize($checkdir.$file.'/database.sql.gz');
	    $db=true;
	  }
          if(file_exists($checkdir.$file.'/data.tar')){
	    $size+=filesize($checkdir.$file.'/data.tar');
	    $data=true;
	  }
	  $config=parse_ini_file($checkdir.$file.'/config.ini');
	  @$comment=$config['comment'];
	  $foo=array();
	  $date=$datedef->my_display($config['creation_date'], $foo);
	  $dirs[$file]=array('crea'=>$date->html,
			     'version'=>$config['tzr_version'],
			     'comment'=>$comment,
			     'size'=>round($size/1024/1024,2),
			     'db'=>$db,
			     'data'=>$data);
        }
      }
      closedir($dd);
    }
    \Seolan\Core\Shell::toScreen2('cp','list',$dirs);
  }

  /// Créé un nouveau point de sauvegarde
  function newCheckpoint($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $comment=$p->get('comment');
    $tplentry=$p->get('tplentry');

    $scheduler=new \Seolan\Module\Scheduler\Scheduler();
    $o=[];
    $o['function']='newCheckpointBatch';
    $o['uid']=getSessionVar('UID');
    $o['withdatabase']=$p->get('database');
    $o['withlogs']=$p->get('logs');
    $o['withdatadir']=$p->get('datadir');
    $o['comment']=$comment;
    $roid=$scheduler->createJob($this->_moid,date('Y-m-d H:i:s'),'Create checkpoint',$o,'',NULL,NULL);
    if($tplentry!=TZR_RETURN_DATA){
      setSessionVar('message',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','willdoandmail'));
    }
    return true;
  }

  /// Créé un nouveau point de sauvegarde via le scheduler
  function newCheckpointBatch(\Seolan\Module\Scheduler\Scheduler &$scheduler,&$o,&$more) {
    $ar=(array)$more;
    $options['withdatabase']=$ar['withdatabase']??false;
    $options['withlogs']=$ar['withlogs']??false;
    $options['withdatadir']=$ar['withdatadir']??false;
    $comment = $ar['comment']??'';

    $ret = static::createTZRCheckpoint($options, $comment);

    if($ret) $txt=sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','checkpointcreationbodyok'),$ret);
    else $txt=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','checkpointcreationbodynok');

    $xmail=new \Seolan\Library\Mail();
    $xmail->sendPrettyMail(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','checkpointcreationsub'), $txt, $GLOBALS['XUSER']->email(), null, ['moid'=>$this->_moid]);
    return $ret;
  }

  /// Efface un point de sauvegarde
  function delCheckpoint($ar){
    $p=new \Seolan\Core\Param($ar,NULL);
    $name=$p->get('checkpoint');
    if(!preg_match('/^[0-9]{8}_[0-9]{6}(_iniset)?$/',$name)) return false;
    $checkdir=TZR_VAR2_DIR.'checkpoints/'.$name.'/';
    if($dd=opendir($checkdir)) {
      while(($file=readdir($dd))!==false) {
        if($file!='.' && $file!='..' && is_file($checkdir.$file)) {
	  unlink($checkdir.$file);
	}
      }
      rmdir($checkdir);
      if($tplentry!=TZR_RETURN_DATA){
	setSessionVar('message',sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','checkpointdeleted'),$name));
      }
      return true;
    }
    return false;
  }

  /// Restaure un point de sauvegarde
  function restoreCheckpoint($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $name=$p->get('checkpoint');
    if(!preg_match('/^[0-9]{8}_[0-9]{6}$/',$name)) return false;

    $scheduler=new \Seolan\Module\Scheduler\Scheduler();
    $scheduler->createSimpleJob("scheduled", $this->_moid, 'restoreCheckpointBatch', NULL, "root", "Checkpoint restore", "", NULL, "", "", ['checkpoint'=>$name]);

    if($tplentry!=TZR_RETURN_DATA){
      setSessionVar('message',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','willdoandmail'));
    }
  }

  /// Restaure un point de sauvegarde
  function restoreCheckpointBatch(\Seolan\Module\Scheduler\Scheduler &$scheduler, &$o, &$more) {
    $ar=(array)$more;
    $name=$ar['checkpoint'];
    if(!preg_match('/^[0-9]{8}_[0-9]{6}$/',$name)) return false;

    ini_set('max_execution_time', 6000);
    list($ret, $mess) = static::restoreTZRCheckpoint($name);
    $scheduler->setStatusJob($o->KOID,'finished', $mess);

    $xmail=new \Seolan\Library\Mail();
    $xmail->sendPrettyMail(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Management_Management','checkpointrestored'), $mess, $GLOBALS['XUSER']->email(), null, ['moid'=>$this->_moid]);
  }

  /// vérifie une version de checkpoint / version console
  public static function checkTZRCheckpointVersion($name){
    $dir=TZR_VAR2_DIR.'checkpoints/'.$name.'/';
    $configname=$dir.'config.ini';
    if(!file_exists($dir)){
      return [false, false, "checkpoint {$name} not found"];
    }
    $config=parse_ini_file($configname);
    if(getFullTZRVersion()!=$config['tzr_version']){
      return [true, false, "version mismatch current : ".getFullTZRVersion()." checkpoint : {$config['tzr_version']})"];
    }
    return [true, true, null];
  }

  /// Rétablit un checkpoint
  public static function restoreTZRCheckpoint($name){
    $dir=TZR_VAR2_DIR.'checkpoints/'.$name.'/';
    $sqlname=$dir.'database.sql';
    $dataname=$dir.'data.tar';
    $configname=$dir.'config.ini';

    if(file_exists($sqlname.'.gz')){
      \Seolan\Module\Management\Management::loadSQLDump(['file'=>$sqlname,
							 'zip'=>true,
							 'del'=>true]);
    } 
    if(file_exists($dataname)) {
      system("tar -xf $dataname -P");
    }
    return [true, "Checkpoint {$name} restored"];
  }

  /// Création des fichiers d'un checkpoint
  public static function createTZRCheckpoint($options=[], $comment){

    $p = new \Seolan\Core\Param($options, ['withdatabase'=>true,
					   'withdatadir'=>false,
					   'withlogs'=>true], 'local');

    foreach(['withdatabase','withdatadir', 'withlogs'] as $optname){
      $$optname = $p->get($optname);
    }

    $date=date('Ymd_His');
    $dir=TZR_VAR2_DIR.'checkpoints/'.$date.'/';
    $sqlname=$dir.'database.sql';
    $dataname=$dir.'data.tar';
    $configname=$dir.'config.ini';

    if(!file_exists(TZR_VAR2_DIR.'checkpoints')) 
      mkdir(TZR_VAR2_DIR.'checkpoints');

    mkdir($dir);

    if($withdatabase){
      \Seolan\Module\Management\Management::createSQLDump(['file'=>$sqlname,
							   'zip'=>true,
							   'no_logs'=>!$withlogs,
							   'drop_table'=>true]);
    }
    if($withdatadir) 
      system('tar -cf '.$dataname.' -P '.$GLOBALS['DATA_DIR']);
    $config='tzr_version = "'.getFullTZRVersion().'"'."\n"; 
    $config.='creation_date = "'.date('Y-m-d H:i:s').'"'."\n";
    if($comment) 
      $config.='comment = "'.str_replace('"','\'',stripslashes($comment)).'"';
    file_put_contents($configname,$config);
    return $date;
  }

  /// Télécharge le dump sql ou les data d'un point de sauvegarde
  function downloadCheckpoint($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $name=$p->get('checkpoint');
    $db=$p->get('db');
    $data=$p->get('data');
    $checkdir=TZR_VAR2_DIR.'checkpoints/';
    ob_clean();
    if($db){
      $file='database.sql.gz';
      header('Content-Type: multipart/x-gzip');
    }else{
      $file='data.tar';
      header('Content-Type: application/x-tar');
    }
    header('Content-disposition: attachment; filename='.$name.'_'.$file);
    header('Content-Length: '.filesize($checkdir.$name.'/'.$file));
    readfile($checkdir.$name.'/'.$file);
    exit(0);
  }

  /// Applique un dump SQL sur la base
  static function loadSQLDump($opts){
    \Seolan\Core\Logs::notice(__METHOD__,"{$opts['file']}");
    if(!empty($opts['del'])){
      \Seolan\Core\Logs::notice(__METHOD__,"drop tables, procedures and functions");
      // enlever d'abord les tables de liens avec clés étrangères
      // sans quoi  => erreur d'intégrité
      foreach(getTablesWithForeignKeys() as $tableName){
	getDB()->execute("DROP TABLE `{$tableName}`",[],false);
      }
      // enlever les autres tables
      foreach(getMetaTables() as $table){
        // On ne supprime pas LOGS car le dump ne la contient pas forcement
        if($table['table']!='LOGS' && $table['type'] != 'VIEW')
	  getDB()->execute("DROP TABLE IF EXISTS `{$table['table']}`",[],false);
	else
	  if ($table['type'] != 'VIEW')
	    getDB()->execute("DROP VIEW IF EXISTS `{$table['table']}`",[],false);
      }
      // enlever les procedures et fonctions (les triggers sont détruits avec leur table associée)
      foreach(getRoutines('PROCEDURE') as $pname){
	getDB()->execute("DROP PROCEDURE IF EXISTS `{$pname}`",[],false);
      }
      foreach(getRoutines('FUNCTION') as $fname){
	getDB()->execute("DROP FUNCTION IF EXISTS `{$fname}`",[],false);
      }
    }
    if(!empty($opts['zip'])){
      system('gunzip '.$opts['file'].'.gz');
    }
    $foo=explode(':',$GLOBALS['DATABASE_HOST']);
    system('mysql -u'.$GLOBALS['DATABASE_USER'].' -p"'.$GLOBALS['DATABASE_PASSWORD'].'" '.
           '-h'.$foo[0].(!empty($foo[1])?' -P'.$foo[1]:'').' '.$GLOBALS['DATABASE_NAME'].'<'.$opts['file']);
    if(!empty($opts['zip'])){
      system('gzip '.$opts['file']);
    }
  }

  /// Créé un dump SQL de la base
  static function createSQLDump($opts) {

    $foo=explode(':',$GLOBALS['DATABASE_HOST']);
    if(empty($foo[1])) $foo[1]='3306';

    $cmd=TZR_MYSQLDUMP_PATH." --routines ";
    // Pas de dump des data
    if(!empty($opts['no_data'])) $cmd.=' --no-data';
    // Ajout drop table
    if(!empty($opts['drop_table'])) $cmd.=' --add-drop-table';
    // Pas de create table
    if(!empty($opts['no_create'])) $cmd.=' --no-create-info';
    // Ignore les logs
    if(!empty($opts['no_logs'])) $cmd.=' --ignore-table '.$GLOBALS['DATABASE_NAME'].'.LOGS';
    // Ignore d'autres tables
    if(!empty($opts['ignore'])) $cmd.=' --ignore-table '.$GLOBALS['DATABASE_NAME'].'.'.implode(' --ignore-table '.$GLOBALS['DATABASE_NAME'].'.',$opts['ignore']);
    // Users, db...
    $cmd.=' -u'.$GLOBALS['DATABASE_USER'].' -p"'.$GLOBALS['DATABASE_PASSWORD'].'" '.
      '-h'.$foo[0].' -P'.$foo[1].' '.$GLOBALS['DATABASE_NAME'];
    // Tables spécifiques
    if(!empty($opts['tables'])) $cmd.=' '.implode(' ',$opts['tables']); 
    // Suppressiondu 'definer'

    $cmd.=" | sed -E -e 's/CREATE DEFINER[^ ]+ (VIEW|PROCEDURE|FUNCTION|TRIGGER)/CREATE \\1/' -e 's/DEFINER[ ]*=[ ]*[^*]*\*/\*/'";
    // Sortie

    $cmd.=' > '.$opts['file'];

    \Seolan\Core\Logs::debug(__METHOD__."$cmd");

    system($cmd);

    // Zip le dump
    if(!empty($opts['zip'])){
      system('gzip '.$opts['file']);
    }
    
  }


  /// Recupere la liste des modules pour la création du menu
  function ajaxModuleMenu($ar=NULL) {
    \Seolan\Core\Logs::debug(__METHOD__.'start');
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>TZR_SESSION_PREFIX.'modules','refresh'=>false));
    $tplentry=$p->get('tplentry');
    $refresh=$p->get('refresh');

    $refresh=true;
    
    if(empty($refresh) && issetSessionVar(TZR_SESSION_PREFIX.'modmenu')) {
      $r=getSessionVar(TZR_SESSION_PREFIX.'modmenu');
    }else{
      $r=['testmenu'=>false,
	  'othermenu'=>false,
	  'modsbygroup'=>[]];
      \Seolan\Core\Module\Module::clearDependancies();
      $rs=getDB()->fetchAll('select * from MODULES order by TOID');
      foreach($rs as $ors) {
	$seen=false;
	$mmoid=$ors['MOID'];
	$mod=\Seolan\Core\Module\Module::objectFactory($mmoid);
	if(!is_object($mod)) continue;
	if(!$seen && isset($GLOBALS['XUSER'])) $seen=$mod->secure('','_index');
	$mod->mkDependancy();
	if($seen && is_object($mod)) {
	  $action=$mod->getMainAction();
	  if(empty($action)) continue;
	  if(!in_array($mod->group, $r['groups'])){
	    $r['groups'][]=$mod->group;
	    $i=count($r['groups'])-1;
	    $r['modsbygroup'][$i]=['lines_name'=>[],
				   'lines_oid'=>[],
				   'lines_method'=>[],
				   'lines_comment'=>[],
				   'lines_testmode'=>[],
				   'lines_home'=>[],
				   'lines_inmainmenu'=>[],
				   'lines_iconcssclass'=>[],
				   'hasMainItem'=>false,
				   'hasOtherItem'=>false
				   ];
	  }else{
	    $i=array_search($mod->group,$r['groups']);
	  }
	  $r['modsbygroup'][$i]['lines_name'][]=$mod->getLabel();
	  $r['modsbygroup'][$i]['lines_oid'][]=$mmoid;
	  $r['modsbygroup'][$i]['lines_home'][]=!$mod->isDependant() && $mod->home;
	  $r['modsbygroup'][$i]['lines_method'][]=$action;
	  $r['modsbygroup'][$i]['lines_comment'][]=$mod->comment;
	  $r['modsbygroup'][$i]['lines_testmode'][]=$mod->testMode(true);
	  $r['modsbygroup'][$i]['lines_iconcssclass'][]=$mod->getIconCssClass();
	  if($mod->testMode(true)) $r['testmenu']=true;
	  if(!$mod->isDependant() && $mod->home) {
	    $r['modsbygroup'][$i]['hasMainItem']=true;
	  } elseif(!$mod->isDependant && !$mod->home){
	    $r['modsbygroup'][$i]['hasOtherItem']=true;
	    $r['othermenu']=true;
	  } else {
	    $r['modsbygroup'][$i]['lines_inmainmenu'][]=false;
	  }
	}
      }
      unset($rs);
      // Tri les groupes, puis tri les noms de modules en reorganisant en meme temps oid/classname/toid
      if(!empty($r['groups'])){
	array_multisort($r['groups'],$r['modsbygroup']);
      }
      foreach($r['groups'] as $i=>$grp){
	array_multisort($r['modsbygroup'][$i]['lines_name'],
			$r['modsbygroup'][$i]['lines_oid'],
			$r['modsbygroup'][$i]['lines_method'],
			$r['modsbygroup'][$i]['lines_home'],
			$r['modsbygroup'][$i]['lines_method'],
			$r['modsbygroup'][$i]['lines_comment'],
			$r['modsbygroup'][$i]['lines_iconcssclass'],
			$r['modsbygroup'][$i]['lines_testmode']);
      }
      setSessionVar(TZR_SESSION_PREFIX.'modmenu',$r);
    }
      
    \Seolan\Core\Logs::debug(__METHOD__.' end');

    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  /** vérification du fonctionnement de la crontab : en raison de la
      protection de apparmor, on ne peut pas utiliser la commande
      crontab. Si le scheduler fonctionne, elle est appelée pendant le
      scheduler.
      On récupère le timestamp (date de dernière modif) du fichier
      ~/../var/tmp/crontab-ok. S'il est trop vieux ou que le fichier
      n'est pas présent, la crontab n'est pas bonne.
      La vérification est faite dans le démon du module management.
  */
  /// verifie si le fichier qui dit que la crontab est ok est présent et
  function isCrontabOK() {
    if ($GLOBALS['IS_VHOST']) return true;
    $okFilename=TZR_TMP_DIR."crontab-ok";
    if(!file_exists($okFilename)) return false;
    $timestamp=filemtime($okFilename);
    if((time()-(int)$timestamp)>60*60*4) return false;
    return true;
  }
  /// vérification que la crontab est ok, appelable seulement depuis le scheduler car sinon bloqué par apparmor
  /// création d un fichier de nom crontab-ok dans les fichiers temporaires si c'est bon
  function checkIfCrontabOK() {
    if (!empty($GLOBALS["IS_VHOST"])) return true;
    $res=[];
    if(!empty($GLOBALS['HOME']))
      exec('crontab -l -u '.$GLOBALS['HOME'].'| grep -v "#"| grep '.PHP_SEOLAN.' | grep cli', $res);
    else
      exec('crontab -l | grep -v "#"| grep '.PHP_SEOLAN.' | grep cli', $res);
    $crontabok=(count($res)>0);
    
    $okFilename=TZR_TMP_DIR.'crontab-ok';
    if($crontabok) {
      file_put_contents($okFilename, '1');
    } elseif(file_exists($okFilename)) {
      unlink($okFilename);
    }
    return $crontabok;
  }
  function _daemon($period="any") {
    $this->checkIfCrontabOK();
  }
    
  /// lecture de la configuration 
  function publicConfiguration($ar=NULL){
    $modecheck = !empty($_REQUEST['modecheck']);
    try{
      $metaconf_ip_adresses = explode(',', TZR_METACONF_IP);
      if (!is_array($metaconf_ip_adresses) || !in_array(getRemoteAddr(), $metaconf_ip_adresses)){
	header('HTTP/1.1 403 Seolan Server Forbidden');
	exit(0);
      }

      // pour pouvoir instancier tous les modules
      $GLOBALS['XUSER']=new \Seolan\Core\User('root');
      setSessionVar('UID', $GLOBALS['XUSER']->_curoid);

      $config = array('console_release'=>TZR_CONSOLE_RELEASE.'.'.TZR_CONSOLE_SUB_RELEASE,
		      'console_branch'=>TZR_CONSOLE_RELEASE."-".strtoupper(TZR_STATUS),
		      'console_path'=>$GLOBALS['LIBTHEZORRO'],
		      'libthezorro'=>'',
		      'libthezorro_link'=>'',
		      'upgrades'=>\Seolan\Core\DbIni::getStatic('upgrades', 'val'),
		      'pending_upgrades'=>'console is up to date',
		      'languages'=>array(),
		      'default_lang'=>NULL,
		      'modules'=>array(),
		      'config'=>loadIni(),
		      'error'=>[],
		      'phpversion'=>phpversion(),
		      'system'=>array('hostname'=>gethostname(),
				      'user'=>get_current_user(),
				      'osrelease'=>explode("\t", exec('lsb_release -r'), 1)[1], // recherche de la version de debian
				      'home'=>$GLOBALS['TZR_WWW_DIR'])
		      );

      // verification de la version de php
      $version=phpversion();
      if(version_compare($version, TZR_PHP_RELEASE)<0) {
	$config["error"][]="PHP Release is $version instead of ".TZR_PHP_RELEASE."\n";
      }
      // upgrades
      list($allupgrades, $criticalupgrades) = Upgrades::pendingUpgrades();
      if (count($allupgrades)>0){
	$config['pending_upgrades'] = count($allupgrades).' pending upgrade(s)';
	if (count($criticalupgrades)>0)
	  $config['pending_upgrades'] .= ' including '.count($criticalupgrades).' critical upgrade(s)';
      }

      // verification de la crontab
      if(!$this->isCrontabOK()) {
	$config['error'][]="le scheduler n'utilise pas le php standard en crontab (".PHP_SEOLAN." par exemple) ou le scheduler est desactivé ou il faut modifier la crontab pour prendre en compte le déplacement du scheduler dans cli";
      }

      // vérification connexion Redis
      if (isset($GLOBALS['REDIS_PASSWORD']) && !\Seolan\Library\Redis::isOnline()) {
        $config['error'][] = "Le serveur Redis ne répond pas";
      }
      if (\Seolan\Module\WaitingRoom\Queue::active()) {
        $config['error'][] = "La salle d'attente est active";
      }

      // verification que la mise à jour auto fonctionne
      if(file_exists(TZR_STATUSFILES_DIR.'auto-upgrade-status'))
	$config['auto_upgrade_status'] = @file_get_contents(TZR_STATUSFILES_DIR.'auto-upgrade-status');
	// DEL X4
      else
	$config['auto_upgrade_status'] = @file_get_contents(TZR_TMP_DIR.'auto-upgrade-status');
      //END DEL X4

      // les modules
      $modlist = \Seolan\Core\Module\Module::modlist(array('refresh'=>1,
					'basic'=>true, 
					'withmodules'=>false, 
					'_options'=>array('local'=>true)));
      
      foreach($modlist['lines_oid'] as $i=>$moid){
	$mod = \Seolan\Core\Module\Module::objectFactory(array('moid'=>$moid, 'interactive'=>false, 'tplentry'=>TZR_RETURN_DATA));
	if (empty($mod) || !is_object($mod)){
	  $config['modules'][] = 'Error, could not build module '.$moid;
	  $config['error'][] = 'Could not build module '.$moid;
	  continue;
	}
	$config['modules'][] = $mod->getPublicConfig($modecheck);
      }
      // langues
      $config['default_lang'] = TZR_DEFAULT_LANG;
      $config['languages'] = $GLOBALS['TZR_LANGUAGES'];
      // biblio tzr
      $config['libthezorro'] = $GLOBALS['LIBTHEZORRO'];
      $config['libthezorro_link'] = @readlink($config['libthezorro']);
      // classes spécifiques ? (xshell?)

      // remontée des erreurs de securité
      $secErrs = getDB()->fetchAll('select name, value, UPD from _VARS where name like ?', 
			      ['securityerror::%']);
      foreach($secErrs as $secErr){
	$config['error'][] = $secErr['UPD'].' : '.unserialize($secErr['value']);
      }

    } catch(Exception $e){
      header('HTTP/1.1 500 Seolan Error accessing configuration parameters '.$e->getMessage());
      exit(0);
    }
    
    if ($modecheck){
      die('ok');
    }

    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate'); 
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    die(json_encode($config));
  }

  /**
   * Affiche un résumé des principaux points à vérifier avant la mise ne ligne du site
   * @author Camille Descombes
   * @todo pour les site avec plusieurs IT, NL, Contacts, faire les tests avec les différents modules via modlist :
   *       \Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA,'basic'=>true, 'toid' => XMODINFOTREE_TOID))
   */
  function checklist() {

    try {

      $error = '#FAA';
      $valid = '#AFA';
      $todo = '#FF8';

      $moid_it = \Seolan\Core\Ini::get('corailv3_xmodinfotree');
      $moid_nl = \Seolan\Core\Ini::get('CorailNewsLetter');
      $moid_contacts = \Seolan\Core\Ini::get('CorailContact');
      $moid_cart = \Seolan\Core\Ini::get('corailv3_cart');

      $xds_charte = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=CHARTE');
      $br_charte = $xds_charte->browse(array('selectedfields'=>'all', '_mode' => 'object'));
      $favicons = $descriptions = $keywords = '';
      foreach ($br_charte['lines'] as $i => $charte) {
        $favicons .= '<div>Favicon: '.$charte['oicon']->html.'</div><div>AppleTouchIcon: '.$charte['oappletouchicon']->html.'</div>';
        $descriptions .= '<div><b>'.$charte['ometa01']->raw.'</div>';
        $keywords     .= '<div><b>'.$charte['ometa02']->raw.'</div>';
      }

      $xmodinfotree = \Seolan\Core\Module\Module::objectFactory($moid_it);
      if ($moid_nl != null) $xmod_nl = \Seolan\Core\Module\Module::objectFactory($moid_nl);


      // PARAMETRAGE
      $parametrage['Librairie SEOLAN utilisée'] = $GLOBALS['LIBTHEZORRO'];
      $liste = '';
      $edf = getDb()->select('SELECT * FROM MODULES WHERE TOID=25')->fetchAll();
      foreach ($edf as $i => $mod) {

	$options = \Seolan\Core\Options::decode($mod['MPARAM']);
      
        $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$options['table']);
        $browsable = $translatable = $published = array();
        foreach ($xds->desc as $k => &$field) {
          if ($field->get_browsable())    $browsable[]    = $field->label;
          if ($field->get_translatable()) $translatable[] = $field->label;
          if ($field->get_published())    $published[]    = $field->label;
        }
        $liste.= '<div>'.$this->ajaxLinkProperties($mod['MOID']).$this->ajaxLinkAdmin($mod['MOID']).' '
         .($options['quickquery'] !== '' ? '<span style="background:'.$valid.'">OK' : '<span style="background:'.$error.'">KO').' : '.$mod['MODULE'].' [moid='.$mod['MOID'].']</span>'
         .' Champs : <u title="'.implode("\n",$browsable).'">Listés</u>'
         .' <u title="'.implode("\n",$translatable).'">Traduits</u>'
         .' <u title="'.implode("\n",$published).'">Publiés</u>'
         .'</div>';
      }
      $parametrage['Ensembles de fiches :<br> - Recherche rapide activée<br> - Champs listés<br> - Champs traduits<br> - Champs publiés'] = $liste;
      $checklist['Paramétrage des tables et des modules'] = $parametrage;


      // GRAPHISME
      $graphisme['Validé par RZ PMA LD'] = 'Non automatisé';
      $graphisme['Favicon(s)'] = $favicons;
      $checklist['Charte graphique'] = $graphisme;


      // GESTION DU SITE
      $liste = '';
      $mep = getDb()->select('SELECT distinct title FROM TEMPLATES WHERE gtype="page" ORDER BY title')->fetchAll();
      foreach ($mep as $i => $m) {
        if ($i>0 && $i%round((count($mep)+1)/3) == 0) $liste.= '</div><div style="float:left;margin-right:10px;">';
        $liste.= '<div>'.$m['title'].'</div>';
      }
      $gestion['Mises en page statiques'] = '<div style="float:left;margin-right:10px;">'.$liste.'</div>';
      $liste = '';
      $mep = getDb()->select('SELECT distinct title FROM TEMPLATES WHERE gtype!="page" ORDER BY title')->fetchAll();
      foreach ($mep as $i => $m) {
        if ($i>0 && $i%round((count($mep)+1)/3) == 0) $liste.= '</div><div style="float:left;margin-right:10px;">';
        $liste.= '<div>'.$m['title'].'</div>';
      }
      $gestion['Mises en page dynamiques'] = '<div style="float:left;margin-right:10px;">'.$liste.'</div>';

      if (isset($_REQUEST['grub']) && isset($_REQUEST['oiddelcat'])) {
        $_selected = array();
        $_selected[$_REQUEST['oiddelcat']] = 1;
        $it = \Seolan\Core\Module\Module::objectFactory($_REQUEST['grub']);
        $it->moveToTrash(array('_selected'=>$_selected));
      }
      $liste = '';
      $moid_infotrees[] = $moid_it;
      foreach ($moid_infotrees as $moid_infotree) {
        $xmodit = \Seolan\Core\Module\Module::objectFactory($moid_infotree);
        $liste.= '<div><b>'.$xmodit->table.'</b></div>';
        $pages_test = getDb()->select('SELECT KOID,title,alias FROM '.$xmodit->table.' WHERE (title like "%test%" or alias like "%test%") and alias != "" ORDER BY title')->fetchAll();
        foreach ($pages_test as $i => $page) {
          $liste.= '<div>'.$this->ajaxLink($this->_moid,'<span class="glyphicon csico-delete"></span>','&function=checklist&template=Module/Management.checklist.html&tplentry=br&grub='.$moid_infotree.'&oiddelcat='.$page['KOID']).' '.$page['title'].' <em>['.$page['alias'].']</em></div>';
        }
      }
      $gestion['Supprimer les pages de test'] = empty($liste) ? 'Aucune page de test trouvée' : $liste;
      $gestion['Vérifier les URL absolues'] = 'local.php : '.$GLOBALS['HOME_ROOT_URL'].'<br>Preview rubriques : '.$xmodinfotree->preview.($moid_nl != null ? '<br>Génération NL : '.$xmod_nl->newsletterurl : '');
      $gestion['Vérifier la page de test'] = '<a href="/test.html" target="_blank">GO</a>';
      if (isset($_REQUEST['emptytable'])) {
        getDb()->execute('DELETE FROM '.$_REQUEST['emptytable']);
      }
      $liste = '';
      $tables = array('LOGS','_MLOGS','_MLOGSD','_PLACES','_MARKS');
      foreach ($tables as $table) {
        $count = getDb()->count('SELECT count(*) FROM '.$table);
        $liste.= '<div>'.$this->ajaxLink($this->_moid,'<span class="glyphicon csico-delete"></span>','&function=checklist&template=Module/Management.checklist.html&tplentry=br&emptytable='.$table).' <span style="background:'.($count > 0 ? $error : $valid).'">'.$table.' = '.$count.' enregistrement(s)</span></div>';
      }
      $gestion['Nettoyage des tables système'] = $liste;
      $checklist['Gestion du site']  = $gestion;


      // DIVERS
      $exec = exec('crontab -l',$output);
      $divers['$ crontab -l'] = empty($output) ? array($error,'Aucun cron paramétré') : array($valid,implode('<br>',$output));
      $divers['Activation du cache'] = \Seolan\Core\Ini::get('cache_activated') == '1' ? array($valid,'Oui') : array($error,'Non');
      $divers['Vider le cache'] = $this->ajaxLink($this->_moid,'GO','&function=emptyCache&template=Core.message.html');
      $divers['Paramètres de DEBUG'] = 'Voir local.php :<br>TZR_DEBUG_MODE (var_export) = '.var_export(TZR_DEBUG_MODE,true).'<br>TZR_LOG_LEVEL (var_export) ='.var_export(TZR_LOG_LEVEL,true);
      $divers['Vérifier les mentions légales'] = '<a href="/mentions-legales.html" target="_blank">GO</a>';
      $checklist['Divers'] = $divers;


      // SECURITE
      $liste = '<div>'.$this->ajaxLink(18,'GO','&function=editSec&oid=GRP:*&template=Module/User.secedit.html&tplentry=br').'</div>';
      $securite['Vérification qu\'aucun module n\'est accessible<br>en lecture/écriture au groupe « Tout le monde »'] = $liste;
      $securite['Vérification de la base des utilisateurs :<br> - Suppression des inutiles<br> - Positionner la fin de droit sur les utilisateurs au 31/12/AA+1<br> - Tester le compte webmaster<br> - Vérifier le nombre (Corail = 1)'] = $this->ajaxLink(19,'GO','&function=browse&tplentry=br&template=Module/Table.browse.html');
      $checklist['Sécurité']  = $securite;


      // HEBERGEMENT
      $hebergement['Vérification des stats (installation + reprise)'] = '<a href="http://stats.xsalto.net/" target="_blank">http://stats.xsalto.net/</a>';
      $hebergement['Valider que le site est présent dans private.xsalto.com/admin<br> + cocher la surveillance'] = '<a href="http://private.xsalto.com/admin/" target="_blank">http://private.xsalto.com/admin/</a>';
      $checklist['Hébergement']  = $hebergement;

      // REFERENCEMENT
      $referencement['META description'] = $descriptions;
      $referencement['META keywords'] = $keywords;
      $referencement['Vérifier le plan du site'] = '<a href="/sitemap.html">sitemap.html</a> et <a href="/sitemap.xml">Application/MiniSite/public/templates/sitemap.xml</a>';
      $referencement['Bing Tag'] = $this->checkIniVar('bingtag');
      $referencement['Google Analytics Tag'] = $this->checkIniVar('analytictag');
      $referencement['Google Map API Key'] = $this->checkIniVar('gmap_api_key');
      $referencement['Compte Addthis'] = $this->checkIniVar('addthis_account');
      $referencement['Activation de l\'URL rewriting'] = \Seolan\Core\Ini::get('url_rewriting') == '1' ? array($valid,'Oui') : array($error,'Non');
      $droits = getDb()->select('SELECT AFUNCTION FROM ACL4 WHERE AGRP="GRP:78cdc4ff5091e3371ad9e47447472da6" AND AMOID=20')->fetchAll();
      $referencement['Accès client au module de Référencement'] = array(preg_match('/r|admin/',$droits[0]['AFUNCTION']) ? $valid : $error,'Droits des Gestionnaires de site => <b>'.$droits[0]['AFUNCTION'].'</b>');
      $referencement['TZR.referer'] = preg_match('/TZR\.referer\(/',file_get_contents('http://'.$_SERVER['SERVER_NAME'].'/index.php')) ? array($valid,'Oui') : array($error,'Non');;
      $checklist['Référencement']  = $referencement;


      // MAILING LIST
      if ($moid_nl != null) {
        $mailing_list['Test d\'envoi (xsalto et non xsalto)'] = $this->ajaxLink($moid_nl,'GO','&_function=genSendPre&template=Module/MailingList.xmodmaillistpre.html&tplentry=br');
        $mailing_list['sender'] = $xmod_nl->sender;
        $mailing_list['sendername'] = $xmod_nl->sendername;
        $mailing_list['Email compte rendu d\'envoi'] = $xmod_nl->reportto;
        $mailing_list['Email expéditeur'] = $xmod_nl->from;
        $mailing_list['Préfix du sujet'] = $xmod_nl->prefix;
        $mailing_list['URL de génération'] = $xmod_nl->newsletterurl;
        $checklist['Mailing List [moid='.$moid_nl.'] '.$this->ajaxLinkProperties($moid_nl)] = $mailing_list;
      }


      // CONTACTS
      if ($moid_contacts != null) {
        $xmod_contacts = \Seolan\Core\Module\Module::objectFactory($moid_contacts);
        $contacts['Liste des contacts'] = $this->ajaxLink($moid_contacts,'GO','&function=browse&template=Module/Table.browse.html&tplentry=br&all=1');
        $contacts['Email compte rendu d\'envoi'] = $xmod_contacts->reportto;
        $contacts['Email de l\'expéditeur<br>+ avertissement nouveau contact'] = $xmod_contacts->sender;
        $contacts['Nom de l\'expéditeur'] = $xmod_contacts->sendername;
        $contacts['Sujet'] = $xmod_contacts->subject;
        $checklist['Gestion des contacts [moid='.$moid_contacts.'] '.$this->ajaxLinkProperties($moid_contacts)] = $contacts;
      }


      // BOUTIQUE
      if ($moid_cart != null) {
        $xmod_cart = \Seolan\Core\Module\Module::objectFactory($moid_cart);
        $boutique['TODO'] = 'TODO';
        $checklist['Gestion de boutique [moid='.$moid_cart.'] '.$this->ajaxLinkProperties($moid_cart)] = $boutique;
      }
    } catch (Exception $e) {
      $checklist['Erreur'] = var_export($e,true);
    }

    \Seolan\Core\Shell::toScreen2('checklist','results',$checklist);
  }

  /**
   * Retourne le code HTML d'un lien AJAX
   * @author Camille Descombes
   */
  private function ajaxLinkAdmin($moid, $text = '') {
    return '<a class="cv8-ajaxlink" href="'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$moid.'&function=adminBrowseFields&template=Core/Module.admin/browseFields.html">'
      .(empty($text) ? '<span class="glyphicon csico-admin"></span>' : $text).'</a>';
  }
  private function ajaxLinkProperties($moid, $text = '') {
    return '<a class="cv8-ajaxlink" href="'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$moid.'&_function=editProperties&template=Core/Module.admin/editprop.html&tplentry=props">'
      .(empty($text) ? '<span class="glyphicon csico-property"></span>' : $text).'</a>';
  }
  private function ajaxLinkSecurity($moid, $text = '') {
    return '<a class="cv8-ajaxlink" href="'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$moid.'&_function=lsSecurity&template=Core/Module.lssecurity.html&tplentry=br">'
      .(empty($text) ? '<span class="glyphicon csico-lock"></span>' : $text).'</a>';
  }
  private function ajaxLink($moid, $text, $param = '') {
    return '<a class="cv8-ajaxlink" href="'.$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$moid.$param.'">'.$text.'</a>';
  }
  private function checkIniVar($ini_var) {
    $var = \Seolan\Core\Ini::get($ini_var);
    return empty($var) ?
      array('#FAA', $this->ajaxLink($this->_moid, 'Variable '.$ini_vat.' non renseignée', 'function=iniEdit&template=Module/Management.iniEdit.html#'.$ini_var)) :
      array('#AFA', $var);
  }
  /**
   * 
   */
  public function genDocumentation($ar=null){
    $p = new \Seolan\Core\Param($ar, ['current'=>null]);
    // json ?
    $r = ['jsoninterface'=>false];
    if (\Seolan\Core\Json::hasInterfaceConfig()) {
      // a ton des droits sur des modules définis
      $r['jsoninterface'] = true;
    }
    // modules 
    $r['modlist'] = \Seolan\Core\Module\Module::modlist();

    // on marque les modules pour lesquels une interface json existe
    foreach($r['modlist']['lines_oid'] as $i=>$mod) {
      if($r['jsoninterface'] === true && \Seolan\Core\Json::getModuleConf($mod)) {
	$r['modlist']['lines_json'][$i]=true;
      } else {
	$r['modlist']['lines_json'][$i]=false;
      }
    }
    if ($p->is_set('current')){
      $r['current'] = $p->get('current');
    }
    \Seolan\Core\Shell::toScreen1('d', $r);
  }
  /**
   * genere la doc pour les modules sélectionnes
   * ajoute en entête le pavé json si json coché
   */
  public function procGenDocumentation($ar=null){
    $p = new \Seolan\Core\Param($ar, ['format'=>'markdown','level'=>1,'tplentry'=>'doc', 'title'=>null,'toc'=>null]);
    $tplentry = $p->get('tplentry');
    $moids = $p->get('moids');
    $level = $p->get('level');
    $format = $p->get('format');
    if ($p->get('json') == 1){
      
    }
    $title = $p->get('title');
    if ($title==null){
        $title = "Documentation\nData et API\nConsole SEOLAN\n".$GLOBALS['HOME_ROOT_URL'];
    }
    $r = ['mods'=>[], 'level'=>implode(array_fill(0, $level, '#')), 'title'=>$title,
	  'json_uri'=>\Seolan\Core\Json::getJsonUri()];

    $modlist = \Seolan\Core\Module\Module::modlist();
    foreach($modlist['lines_oid'] as $i=>$moid){
      if (!in_array($moid, $moids)) continue;
      
      $mod = \Seolan\Core\Module\Module::objectFactory(['interactive'=>false,'tplentry'=>TZR_RETURN_DATA,'moid'=>$moid]);
      $rgpd = [];
      if($mod->RGPD_identity) {
	$rgpd[]='Le module est un ensemble de personnes.';
      }
      if($mod->RGPD_personalData) {
	$rgpd[]='Le module contient des données personnelles de type "'.strtolower(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD',$mod->RGPD_typeOfData)).'" avec une durée de rétention de '.floor($mod->RGPD_retention).' jours ('.floor(($mod->RGPD_retention/365.25)).' années).';
	$rgpd[] = 'Méthode de suppression des données : "'.strtolower(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_RGPD',$mod->RGPD_deleteDataMethod)).'".';
      }
      $r['mods'][] = ['name'=>$mod->getLabel(),
		      'group'=>$mod->group,
		      'comment'=>$mod->comment,
		      'rgpd'=>$rgpd,
		      'doc'=>$mod->getDocumentationData(),
		      'documentation'=>''
		      ];
    }
    
    // if tpl ... ?
    foreach($r['mods'] as $i=>&$data){
      if (empty($data['doc']['template']))
        continue;
      $xt = new \Seolan\Core\Template($data['doc']['template']);
      $data['documentation_title'] = $data['doc']['title'];
      $data['documentation_chapeau'] = $data['doc']['chapeau'];
      $data['documentation_more'] = $data['doc']['more'];
      $databloc=['doc'=>$data['doc']['data']];
      $databloc2=['level'=>$r['level']];
      $data['documentation'] = $xt->parse($databloc, $databloc2, null, false);
      unset($data['doc']['data']);
    }

    $format = $p->get('format');
    $mime = \Seolan\Library\MimeTypes::getInstance();

  
    $options = '--variable=title:"'.$r['title'].'" ';
    // toc ne fonctionne pas en odt
    if ($p->is_set('toc')){
      $options .= ' --toc --toc-depth=2 ';
    }
    switch($format){
    case 'markdown':
      $file = ['ext'=>'.md','mime'=>$mime->get_type('.md'), 'options'=>''];
      break;
    case 'html5':
      $file = ['ext'=>'.html','mime'=>$mime->get_type('.html'), 
	       'options'=>$options.'--variable=website:'.$GLOBALS['HOME_ROOT_URL'].' --template='.$GLOBALS['LIBTHEZORRO'].'src/Core/public/templates/documentation/template.html'];
      break;
    case 'rtf':
      $file = ['ext'=>'.rtf','mime'=>$mime->get_type('.rtf'), 'options'=>$options];
      break;
    case 'odt':
// ??	$reference = "{$GLOBALS['LIBTHEZORRO']}src/Module/Management/documentation/feuille_style.odt";
//	$options .= " --reference-doc=$reference ";
	$file = ['ext'=>'.odt','mime'=>$mime->get_type('.odt'), 'options'=>$options];
      break;
    }

    $xt = new \Seolan\Core\Template('Core.documentation/documentation.md');
    $contents = $xt->parse(($foo=['doc'=>$r]), ($foo=null), null, false);

    if ($p->is_set('file')){ // tests ?
      file_put_contents($p->get('file'), $contents);
    }

    $tmpfile = TZR_TMP_DIR.uniqid().'documentation.md';
    file_put_contents($tmpfile, $contents);
    if ($format != 'markdown'){
      // todo : styles et entêtes 
      $tmpfilein = $tmpfile;
      $tmpfile = TZR_TMP_DIR.uniqid().'documentation.md';

      //      $file['options'] = escapeshellarg($file['options']);
      \Seolan\Core\Logs::debug(__METHOD__."\npandoc --verbose {$file['options']} --standalone --from=markdown --to={$format} --output={$tmpfile} {$tmpfilein}");
      exec("pandoc --verbose {$file['options']} --standalone --from=markdown --to={$format} --output={$tmpfile} {$tmpfilein}", $res);
      \Seolan\Core\Logs::debug(__METHOD__."\npandoc output {$file['options']} \n $res \n");
      unlink($tmpfilein);
    }

    \Seolan\Core\Shell::setNextFile($tmpfile, cleanFilename($title).$file['ext'], $file['mime']);  

    \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self().
				http_build_query(['moid'=>$this->_moid,
						  'function'=>'genDocumentation',
						  'tplentry'=>'br',
						  'template'=>'Module/Management.documentation.html',
						  'current'=>[
						    'moids'=>$moids,
						    'level'=>$level,
						    'format'=>$format,
						    'toc'=>$p->is_set('toc')?'1':null]]));
    return $r;

  }
}
