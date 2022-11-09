<?php

namespace Seolan\Module\PushNotification\Device;

use Seolan\Module\Table\Wizard as WizardTable;
use Seolan\Core\Labels;
use Seolan\Core\Module\Wizard as WizardModule;
use Seolan\Core\Module\Module;
use Seolan\Library\Upgrades;
use Seolan\Model\DataSource\Table\Table;
use Seolan\Core\DataSource\DataSource;

class Wizard extends WizardTable {
  public function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->_module->group=Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
  }
  
  public function istep1() {
    WizardModule::istep1();
    $this->_options->setOpt(
      Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification',"createstructure_device","text"),
      'createstructure_device',
      'boolean');
  }
  
  public function istep2() {
    if(!$this->_module->createstructure_device) {
      $this->_options->setOpt(
        Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification',"device_table","text"),
        'device_table',
        'table');
    }
  }
  
  public function iend($ar=NULL) {
    if ($this->_module->createstructure_device) {
      $this->_module->table = $this->createstructure();
    } else {
      $this->_module->table = $this->_module->device_table;
    }
    
    if(empty($this->_module->group)) $this->_module->group=Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $moid = parent::iend();
  
    $modUser = Module::singletonFactory(XMODUSER2_TOID);
    $addSubModule = true;
    for ($i = 1; $i <= $modUser->submodmax; ++$i) {
      if ((int)$modUser->{'ssmod'.$i} === (int)$moid) {
        $addSubModule = false;
        break;
      }
    }
  
    if ($addSubModule) {
      $submodmax = $modUser->submodmax + 1;
      Upgrades::editModuleOptions($modUser->_moid, 'submodmax', $submodmax);
      Upgrades::editModuleOptions($modUser->_moid, 'ssmodtitle'.$submodmax, 'Périphériques');
      Upgrades::editModuleOptions($modUser->_moid, 'ssmodfield'.$submodmax, 'device');
      Upgrades::editModuleOptions($modUser->_moid, 'ssmod'.$submodmax, $moid);
      Upgrades::editModuleOptions($modUser->_moid, 'ssmodactivate_additem'.$submodmax, false);
      Upgrades::editModuleOptions($modUser->_moid, 'ssmoddependent'.$submodmax, true);
    }
    
    return $moid;
  }
  
  private function createstructure() {
    $newtable=Table::newTableNumber('DEVICES');
    $ar1=array();
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$this->_module->prefix_table ? $this->_module->prefix_table.' - Devices' : 'Devices';
    $ar1['own'] = false;
    Table::procNewSource($ar1);
    $x=DataSource::objectFactoryHelper8($newtable);
    
    $x->createField('user',
                    'Utilisateur',
                    '\Seolan\Field\Link\Link',
                    20,
                    2,
                    true,
                    true,
                    true,
                    false,
                    false,
                    true,
                    'USERS');
    
    $x->createField('name',
                    'Nom',
                    '\Seolan\Field\ShortText\ShortText',
                    255,
                    3,
                    false,
                    false,
                    true,
                    false,
                    false,
                    true);
    
    $x->createField('platform',
                    'Plateforme',
                    '\Seolan\Field\ShortText\ShortText',
                    20,
                    4,
                    true,
                    true,
                    true,
                    false,
                    false,
                    false);
    
    $x->createField('identifier',
                    'Identifiant',
                    '\Seolan\Field\ShortText\ShortText',
                    255,
                    5,
                    true,
                    true,
                    true,
                    false,
                    false,
                    false);
    
    $x->createField('push_token',
                    'Token de notification push',
                    '\Seolan\Field\ShortText\ShortText',
                    255,
                    6,
                    false,
                    true,
                    true,
                    false,
                    false,
                    false);
    
    $x->createField('push_token_type',
                    'Type de token de notification push',
                    '\Seolan\Field\ShortText\ShortText',
                    255,
                    7,
                    false,
                    true,
                    false,
                    false,
                    false,
                    false);
    
    $x->createField('model',
                    'Modèle',
                    '\Seolan\Field\ShortText\ShortText',
                    100,
                    8,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false);
    
    $x->createField('os_version',
                    'Version OS',
                    '\Seolan\Field\ShortText\ShortText',
                    50,
                    9,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false);
    
    $x->createField('app_version',
                    'Version application',
                    '\Seolan\Field\ShortText\ShortText',
                    20,
                    10,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false);
    
    $x->createField('last_activity',
                    'Dernière activité',
                    '\Seolan\Field\DateTime\DateTime',
                    0,
                    11,
                    false,
                    true,
                    true,
                    false,
                    false,
                    false);
    
    $x->createField('nb_successive_errors',
                    'Nombre d\'erreur successive',
                    '\Seolan\Field\Real\Real',
                    20,
                    12,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    '',
                    ['default' => 0, 'decimal' => 0, 'edit_format' => '^[0-9]*$']);
    
    $x->createField('test',
                    'Device de test',
                    '\Seolan\Field\Boolean\Boolean',
                    0,
                    13,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    '',
                    [ 'hidden' => true ]);
  
    return $newtable;
  }
}