<?php
namespace Seolan\Module\PushNotification;

use Seolan\Module\Table\Wizard as WizardTable;
use Seolan\Core\Module\Wizard as WizardModule;
use Seolan\Core\Labels;
use Seolan\Module\PushNotification\Device\Wizard as WizardDevice;
use Seolan\Library\Upgrades;
use Seolan\Core\Module\Module;
use Seolan\Module\Scheduler\Scheduler;
use Seolan\Model\DataSource\Table\Table;
use Seolan\Core\DataSource\DataSource;

class Wizard extends WizardTable {
  public function __construct($ar=NULL) {
    parent::__construct($ar);
    Labels::loadLabels('Seolan_Module_PushNotification_PushNotification');
    $this->_module->group = 'Mobile';
  }
  
  public function istep1() {
    WizardModule::istep1();
    $this->_options->setOpt(
      Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification',"createstructure_push","text"),
      'createstructure_push',
      'boolean');
    
    $this->_options->setOpt(
      Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification',"createstructure_device","text"),
      'createstructure_device',
      'boolean');
    
    $this->_options->setOpt(
      Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification',"createstructure_recipient","text"),
      'createstructure_recipient',
      'boolean');
  }
  
  public function istep2() {
    if(!$this->_module->createstructure_push) {
      $this->_options->setOpt(
        Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification',"push_table","text"),
        'push_table',
        'table');
    }
    
    if(!$this->_module->createstructure_device) {
      $this->_options->setOpt(
        Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification',"device_table","text"),
        'device_table',
        'table');
    }
    
    if(!$this->_module->createstructure_recipient) {
      $this->_options->setOpt(
        Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification',"recipient_table","text"),
        'recipient_table',
        'table');
    }
  }
  
  public function iend($ar=NULL) {
    if($this->_module->createstructure_push) {
      $this->_module->createstructure_push = false;
      $this->_module->push_table = $this->createstructure_push();
    }
  
    $this->_module->table = $this->_module->push_table;
    $moidPush = parent::iend();
  
    $modDevice = new WizardDevice(array('newmoid'=>XMODPUSHNOTIFICATIONDEVICE_TOID));
    $modDevice->_module->modulename='Devices';
    $modDevice->_module->group=$this->_module->group;
    
    if($this->_module->createstructure_device) {
      $modDevice->_module->createstructure_device = true;
      $modDevice->_module->prefix_table = $this->_module->modulename;
      $this->_module->createstructure_device = false;
    } else {
      $modDevice->_module->device_table = $this->_module->device_table;
    }
    $moidDevices = $modDevice->iend();
    
    if($this->_module->createstructure_recipient) {
      $this->_module->createstructure_recipient = false;
      $this->_module->recipient_table = $this->createstructure_recipient();
    }
    
    $modRcpt = new WizardTable(array('newmoid'=>XMODTABLE_TOID));
    $modRcpt->_module->modulename='Destinataires';
    $modRcpt->_module->group=$this->_module->group;
    $modRcpt->_module->table=$this->_module->recipient_table;
    $moidRcpt = $modRcpt->iend();
    
    Upgrades::editModuleOptions($moidPush, 'submodmax', 1);
    Upgrades::editModuleOptions($moidPush, 'ssmodtitle1', 'Destinataires');
    Upgrades::editModuleOptions($moidPush, 'ssmodfield1', 'push');
    Upgrades::editModuleOptions($moidPush, 'ssmod1', $moidRcpt);
    Upgrades::editModuleOptions($moidPush, 'ssmodactivate_additem1', true);
    Upgrades::editModuleOptions($moidPush, 'ssmoddependent1', true);
    
    Upgrades::editModuleOptions($moidPush, 'moid_rcpt', $moidRcpt);
    Upgrades::editModuleOptions($moidPush, 'moid_devices', $moidDevices);
  
    $mod_scheduler = Module::objectFactory(Scheduler::getMoid(XMODSCHEDULER_TOID));
    $more = [
      'class' => static::getModuleClassname(get_class($this)),
      'toid' => \Seolan\Core\Module\Module::getToidFromClassname(static::getModuleClassname(get_class($this))),
      'nb_notification_send_by_task' => PushNotification::MAX_PUSH_SENT_PER_TASK,
    ];
    $mod_scheduler->createSimpleJob('cron', $moidPush, 'sendNotification', null, TZR_USERID_ROOT,
                                    'Envoi des notifications push', 'Cron généré par le wizard.', null,
                                    'hourly', '*/15', $more);
        
    return $moidPush;
  }
  
  private function createstructure_push() {
    $module=(array)$this->_module;
    $modulename=$module['modulename'];
  
    $newtable=Table::newTableNumber('PUSH');
    $ar1=array();
    $ar1['translatable']='1';
    $ar1['auto_translate']='1';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Push';
    $ar1['own'] = false;
    Table::procNewSource($ar1);
    $x=DataSource::objectFactoryHelper8($newtable);
    
    $x->createField('title',
                    'Titre',
                    '\Seolan\Field\ShortText\ShortText',
                    40,
                    2,
                    true,
                    true,
                    true,
                    true,
                    false,
                    true,
                    '',
                    ['fgroup' => '1 - Général', 'boxsize' => 45]);
    
    $x->createField('body',
                    'Message',
                    '\Seolan\Field\ShortText\ShortText',
                    180,
                    3,
                    true,
                    false,
                    true,
                    true,
                    false,
                    false,
                    '',
                    ['fgroup' => '1 - Général', 'boxsize' => 100]);
    
    $x->createField('json_data',
                    'Données JSON',
                    '\Seolan\Field\Serialize\Serialize',
                    80,
                    4,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                    '',
                    ['fgroup' => '1 - Général', 'hidden' => true]);
    
    $x->createField('subtitle',
                    'Sous-titre',
                    '\Seolan\Field\ShortText\ShortText',
                    40,
                    5,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                    '',
                    ['fgroup' => '2 - Apple']);
    
    $x->createField('badge_count',
                    'Numéro badge',
                    '\Seolan\Field\Real\Real',
                    2,
                    6,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    '',
                    ['fgroup' => '2 - Apple', 'default' => '0', 'decimal' => 0, 'edit_format' => '^[0-9]?$']);
    
    $x->createField('play_sound',
                    'Son',
                    '\Seolan\Field\Boolean\Boolean',
                    20,
                    7,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    '',
                    ['fgroup' => '2 - Apple']);
    
    $x->createField('channel_id',
                    'ID du Canal',
                    '\Seolan\Field\ShortText\ShortText',
                    80,
                    8,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    '',
                    ['fgroup' => '3 - Android', 'default' => 'default']);
    
    $x->createField('send_date',
                    'Date d\'envoi',
                    '\Seolan\Field\DateTime\DateTime',
                    20,
                    9,
                    false,
                    true,
                    true,
                    false,
                    false,
                    false,
                    '',
                    ['fgroup' => '4 - Envoi']);
    
    $x->createField('send_state',
                    'Etat d\'envoi',
                    '\Seolan\Field\StringSet\StringSet',
                    20,
                    10,
                    true,
                    true,
                    true,
                    false,
                    false,
                    false,
                    '',
                    ['fgroup' => '4 - Envoi', 'default' => 'SCHEDULED', 'readonly' => true]);
    
    $x->createField('detail',
                    'Détail si erreur',
                    '\Seolan\Field\Serialize\Serialize',
                    80,
                    11,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    '',
                    ['fgroup' => '4 - Envoi', 'readonly' => true]);
  
    $states = [
      ['ID' => 'SCHEDULED', 'TEXT' => 'Programmée', 'ORDER' => 1],
      ['ID' => 'PENDING', 'TEXT' => 'Envoi en cours', 'ORDER' => 2],
      ['ID' => 'SENT', 'TEXT' => 'Envoyée', 'ORDER' => 3],
      ['ID' => 'ERROR', 'TEXT' => 'Erreur', 'ORDER' => 4],
    ];
    
    foreach ($states as $state) {
      if(!getDB()->fetchExists('select 1 from SETS where STAB=? and FIELD=? and SOID=?', array($newtable, 'send_state', $state['ID']))) {
        getDB()->execute('insert into SETS (SOID, STAB, FIELD, SLANG, STXT, SORDER) values (?, ?, ?, ?, ?, ?)', [$state['ID'], $newtable, 'send_state', TZR_DEFAULT_LANG, $state['TEXT'], $state['ORDER']]);
      }
    }
    
    return $newtable;
  }
  
  private function createstructure_recipient() {
    $module=(array)$this->_module;
    $modulename=$module['modulename'];
  
    $newtable=Table::newTableNumber('PUSH_RCPT');
    $ar1=array();
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Destinataires';
    $ar1['own'] = false;
    $ar1['publish'] = false;
    Table::procNewSource($ar1);
    $x=DataSource::objectFactoryHelper8($newtable);
  
    $x->createField('push',
                    'Notification',
                    '\Seolan\Field\Link\Link',
                    20,
                    2,
                    true,
                    true,
                    true,
                    false,
                    false,
                    true,
                    $this->_module->push_table);
  
    $x->createField('device',
                    'Périphérique',
                    '\Seolan\Field\Link\Link',
                    20,
                    3,
                    true,
                    true,
                    true,
                    false,
                    false,
                    true,
                    $this->_module->device_table,
                    [
                      'display_format' => '%_user - %_name',
                      'display_text_format' => '%_user - %_name',
                    ]);
  
    $x->createField('status',
                    'Etat',
                    '\Seolan\Field\StringSet\StringSet',
                    20,
                    4,
                    true,
                    true,
                    true,
                    false,
                    false,
                    false,
                    '',
                    ['default' => 'WAITING']);
  
    $x->createField('ticket_id',
                    'ID de reçu',
                    '\Seolan\Field\ShortText\ShortText',
                    255,
                    5,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    '',
                    ['readonly' => true]);
  
    $x->createField('json',
                    'Données brutes',
                    '\Seolan\Field\Serialize\Serialize',
                    100,
                    6,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    '',
                    ['readonly' => true]);
  
    $states = [
      ['ID' => 'WAITING', 'TEXT' => 'En atente', 'ORDER' => 1],
      ['ID' => 'PENDING', 'TEXT' => 'Envoi en cours', 'ORDER' => 2],
      ['ID' => 'TRANSMITTED', 'TEXT' => 'Message transmis', 'ORDER' => 3],
      ['ID' => 'SENT', 'TEXT' => 'Envoyé', 'ORDER' => 4],
      ['ID' => 'ERROR', 'TEXT' => 'Erreur', 'ORDER' => 5],
    ];
  
    foreach ($states as $state) {
      if(!getDB()->fetchExists('select 1 from SETS where STAB=? and FIELD=? and SOID=?', array($newtable, 'status', $state['ID']))) {
        getDB()->execute('insert into SETS (SOID, STAB, FIELD, SLANG, STXT, SORDER) values (?, ?, ?, ?, ?, ?)', [$state['ID'], $newtable, 'status', TZR_DEFAULT_LANG, $state['TEXT'], $state['ORDER']]);
      }
    }
    
    return $newtable;
  }
  
}