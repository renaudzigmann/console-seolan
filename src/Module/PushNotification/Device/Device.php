<?php
namespace Seolan\Module\PushNotification\Device;

use Seolan\Core\Labels;
use Seolan\Core\Module\Module;
use Seolan\Library\Upgrades;
use Seolan\Module\PushNotification\PushNotification;
use Seolan\Module\Table\Table;
use Seolan\Core\Kernel;
use Seolan\Core\Param;
use Seolan\Core\User;

class Device extends Table {
  const DEFAULT_CLEAR_DELAY = 90;
  
  public function __construct($ar = null) {
    parent::__construct($ar);
    Labels::loadLabels('Seolan_Module_PushNotification_Device_Device');
    
    //Seul les personnes en rwv peuvent voir et modifier les champs système
    $admin = $this->secure('',':rwv');
    if(!$admin) {
      $this->fieldssec = [
        'platform' => 'ro',
        'identifier' => 'ro',
        'push_token' => 'ro',
        'push_token_type' => 'ro',
        'nb_successive_errors' => 'none',
        'last_activity' => 'ro',
        'app_version' => 'ro',
        'os_version' => 'ro',
        'model' => 'ro',
      ];
      
      $this->xset->desc['nb_successive_errors']->_options->set($this->xset->desc['nb_successive_errors'], 'hidden', true);
    }
  }
  
  public function secGroups($function, $group = null) {
    $g =[];
    $g['upsertDevice'] = ['none'];
  
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    
    return parent::secGroups($function, $group);
  }
  
  public function upsertDevice($ar = []) : array {
    $json = file_get_contents('php://input');
    $dataInput = [];
    if (!empty($json)) {
      $dataInput = json_decode($json, true);
    }
    
    $p = new Param(array_merge($ar, $dataInput));
    
    $deviceId = $p->get('device_id');
    $name = $p->get('device_name');
    $model = $p->get('device_model');
    $platform = $p->get('platform');
    $os_version = $p->get('device_os_version');
    $push_token = $p->get('push_token');
    $push_token_type = $p->get('push_token_type');
    $app_version = $p->get('app_version');
    
    $deviceOid = $this->getOidDeviceFromId($deviceId);
    
    $data = $this->xset->rDisplay($deviceOid, [], false, '', '', ['selectedfields' => ['push_token', 'PUBLISH', 'nb_successive_errors']]);
    
    $arDevice = [
      'platform' => $platform,
      'identifier' => $deviceId,
      'push_token' => $push_token,
      'push_token_type' => $push_token_type,
      'name' => $name,
      'model' => $model,
      'os_version' => $os_version,
      'app_version' => $app_version,
      'last_activity' => date('Y-m-d H:i:s'),
    ];
  
    //Si le token présent en base est différent de celui envoyé on publie le device automatiquement et on réinitialise le compteur des erreurs successives.
    if ($data['opush_token']->raw !== $push_token) {
      $arDevice['PUBLISH'] = 1;
      $arDevice['nb_successive_errors'] = 0;
    }
    
    $result = 'ko';
    if (Kernel::isAKoid($deviceOid) && Kernel::objectExists($deviceOid)) {
      //on ne met pas à jour le champ user si le device existe déjà. Un seul compte par device.
      $arDevice['oid'] = $deviceOid;
      $r = $this->xset->procEdit($arDevice);
      if ($r['message']=Labels::getSysLabel('Seolan_Core_DataSource_DataSource','update_ok')) {
        $result = 'ok';
      }
    } else {
      $arDevice['user'] = User::get_current_user_uid() === TZR_USERID_NOBODY ? TZR_USERID_ROOT : User::get_current_user_uid();
      $arDevice['PUBLISH'] = 1;
      $r = $this->xset->procInput($arDevice);
      if ($r['message']=Labels::getSysLabel('Seolan_Core_DataSource_DataSource','insert_ok')) {
        $result = 'ok';
      }
    }
    
    if ((int)$p->get('ajax') === 1) {
      die(json_encode(['status' => $result]));
    }
    
    return $r;
  }
  
  public function getOidDeviceFromId($deviceId) : string {
    return (string)getDB()->fetchOne('SELECT KOID FROM `'.$this->table.'` WHERE identifier = ?', [$deviceId]);
  }
  
  public function getOidDeviceFromPushToken($token) : string {
    return (string)getDB()->fetchOne('SELECT KOID FROM `'.$this->table.'` WHERE push_token = ?', [$token]);
  }
  
  public function associateDeviceToUser($userOid, $deviceID) : void {
    if ($userOid === TZR_USERID_NOBODY) {
      return;
    }
    
    $device = getDB()->fetchRow('SELECT KOID, user FROM `'.$this->table.'` WHERE identifier = ?', [$deviceID]);
    
    if (empty($device['KOID'])) {
      $this->xset->procInput(['identifier' => $deviceID, 'user' => $userOid]);
    } else {
      //on ne met pas à jour le champ user si le device existe déjà pour un autre utilisateur. Un seul compte par device.
      if (empty($device['user']) || $device['user'] === TZR_USERID_ROOT) {
        $this->xset->procEdit(['oid' => $device['KOID'], 'user' => $userOid]);
      }
    }
  }
  
  public function delete($ar = null) {
    $modUser = Module::singletonFactory(XMODUSER2_TOID);
    $removeSubModule = false;
    for ($i = 1; $i <= $modUser->submodmax; ++$i) {
      if ((int)$modUser->{'ssmod'.$i} === (int)$this->_moid) {
        $removeSubModule = $i;
        break;
      }
    }
    
    if ($removeSubModule !== false) {
      Upgrades::editModuleOptions($modUser->_moid, 'ssmodtitle'.$removeSubModule, null);
      Upgrades::editModuleOptions($modUser->_moid, 'ssmodfield'.$removeSubModule, null);
      Upgrades::editModuleOptions($modUser->_moid, 'ssmod'.$removeSubModule, null);
      Upgrades::editModuleOptions($modUser->_moid, 'ssmodactivate_additem'.$removeSubModule, null);
      Upgrades::editModuleOptions($modUser->_moid, 'ssmoddependent'.$removeSubModule, null);
  
      if ($removeSubModule === $modUser->submodmax) {
        Upgrades::editModuleOptions($modUser->_moid, 'submodmax', $removeSubModule - 1);
      }
    }
    
    return parent::delete($ar);
  }
  
  public function unregisterPushDevice($token) : void {
    $oidDevice = $this->getOidDeviceFromPushToken($token);
  
    if (!empty($oidDevice)) {
      $this->procEdit(['oid' => $oidDevice, 'push_token' => '']);
    }
  }
  
  public function unPublishToken($token) : void {
    $oidDevice = $this->getOidDeviceFromPushToken($token);
  
    if (!empty($oidDevice)) {
      $this->publish(['oid' => $oidDevice, 'value' => 2]);
    }
  }
  
  public function updateNbError($moid_rcpt, $token) : void {
    $modRcpt = Module::objectFactory($moid_rcpt);
    $lastStatus = getDB()->fetchOne('SELECT r.status FROM `'.$modRcpt->table.'` r INNER JOIN `'.$this->table.'` d ON d.KOID = r.device WHERE d.push_token = ? ORDER BY r.UPD DESC', [$token]);
    
    if ($lastStatus === PushNotification::RCPT_ERROR_STATUS) {
      getDB()->execute('UPDATE `'.$this->table.'` SET nb_successive_errors = nb_successive_errors + 1 WHERE push_token = ?', [$token]);
    }
  }
  
  public function updateLastActivity($deviceID) : void {
    getDB()->execute('UPDATE `'.$this->table.'` SET last_activity = now() WHERE identifier = ?', [$deviceID]);
  }
  
  public function initOptions() : void {
    parent::initOptions();
    $group = Labels::getSysLabel('Seolan_Module_PushNotification_Device_Device','modulename');
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_PushNotification_Device_Device','clear_delay'), 'clear_delay', 'text', [], self::DEFAULT_CLEAR_DELAY, $group);
  }
  
  public function _daemon($period = 'any') : bool {
    if ($period === 'daily') {
      $this->cleanOldDevices();
    }
    
    return parent::_daemon($period);
  }
  
  protected function cleanOldDevices() {
    getDB()->execute('DELETE FROM `'.$this->table.'` WHERE date_add(last_activity, interval ? day) <= now() OR `user` NOT IN (SELECT KOID FROM `USERS`)', [(int)$this->clear_delay ?? self::DEFAULT_CLEAR_DELAY]);
  }
}