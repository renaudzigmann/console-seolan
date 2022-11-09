<?php
namespace Seolan\Module\PushNotification;

use Seolan\Core\DbIni;
use Seolan\Core\Exception\Exception;
use Seolan\Core\Logs;
use Seolan\Core\Module\Module;
use Seolan\Core\System;
use Seolan\Module\Table\Table;
use Seolan\Core\Labels;
use Seolan\Core\Param;
use Seolan\Core\Shell;
use Seolan\Module\Scheduler\Scheduler;
use Seolan\Library\Lock;

class PushNotification extends Table {
  const DEFAULT_DELAY = 30; // en jours
  const DEFAULT_TTL = null;
  const MAX_PUSH_SENT_PER_TASK = 100;
  const MAX_UNREACHABLE_ERROR_BEFORE_ALERT = 5;
  const RCPT_PENDING_STATUS = 'PENDING';
  const RCPT_WAITING_STATUS = 'WAITING';
  const RCPT_TRANSMITTED_STATUS = 'TRANSMITTED';
  const RCPT_SENT_STATUS = 'SENT';
  const RCPT_ERROR_STATUS = 'ERROR';
  
  const PUSH_PENDING_STATUS = 'PENDING';
  const PUSH_SCHEDULED_STATUS = 'SCHEDULED';
  const PUSH_SENT_STATUS = 'SENT';
  const PUSH_ERROR_STATUS = 'ERROR';
  
  const CLEAN_PENDING_DELAY = 2; // en jours
  
  const TOKEN_TYPE_CLASS = [
    'expo' => 'Seolan\Module\PushNotification\Expo',
  ];
  
  static public $upgrades = [
    '20210906' => ''
  ];
  
  public function __construct($ar = null) {
    parent::__construct($ar);
    Labels::loadLabels('Seolan_Module_PushNotification_PushNotification');
  }
  
  public function initOptions() : void {
    parent::initOptions();
    $group = Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification','modulename');
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification','rcpt_mod'), 'moid_rcpt', 'module', [], null, $group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification','device_mod'), 'moid_devices', 'module', [], null, $group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification','delay'), 'delay', 'text', null, self::DEFAULT_DELAY, $group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification','ttl'), 'ttl', 'text', [], self::DEFAULT_TTL, $group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_PushNotification_PushNotification','access_token'), 'access_token', 'text', [], null, $group);
  }

  /// Cette fonction est appliquee pour afficher l'ensemble des methodes de ce module
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my,$alfunction);
    $moid=$this->_moid;

    // Envoi des notifications push
    if($this->secure('','sendNotification2')){
      $o1=new \Seolan\Core\Module\Action($this,'sendNotification2',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_PushNotification_PushNotification','send_notification'),
			    '&moid='.$moid.'&_function=sendNotification2&template=Module/Table.browse.html&tplentry=br','sendNotification2');
      $o1->group = 'edit';
      $o1->containable=true;
      $o1->menuable=true;
      $o1->needsconfirm = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','confirm');
      $my['sendNotification2']=$o1;
    }
  }
  
  public function delete($ar = null) {
    $p=new Param($ar, array('tplentry'=>''));
    $tplentry=$p->get('tplentry');
    $ar['tplentry'] = TZR_RETURN_DATA;
    $_REQUEST['tplentry'] = TZR_RETURN_DATA;
    $message = '';
    
    $rcpt = Module::objectFactory($this->moid_rcpt);
    if (is_object($rcpt)) {
      $message .= $rcpt->delete($ar);
    }
    
    $devices = Module::objectFactory($this->moid_devices);
    if (is_object($devices)) {
      $message = $message.'<br>'.$devices->delete($ar);
    }
  
    $message = $message.'<br>'.parent::delete($ar);
  
    $mod_scheduler = Module::objectFactory(Scheduler::getMoid(XMODSCHEDULER_TOID));
    if ($mod_scheduler->isThereACronJob($this->_moid, 'sendNotification', 'cron')) {
      getDB()->execute("DELETE FROM TASKS WHERE amoid = ? AND JSON_VALUE(more, '$.function') = ? AND status = ?",[$this->_moid, 'sendNotification', 'cron']);
    }
    
    return Shell::toScreen2($tplentry,'message',$message);
  }
  
  public function _daemon($period = 'any') : bool {
    if ($period === 'daily') {
      $this->cleanOldNotifications();
      $this->cleanOldPendingNotifications();
    }
  
    $this->updatePushReceipt();
    
    return parent::_daemon($period);
  }
  
  private function cleanOldNotifications() : void {
    getDB()->execute('DELETE FROM `'.$this->table.'` WHERE send_state IN (?, ?) AND date_add(UPD, interval ? day) <= now()', [self::PUSH_SENT_STATUS, self::PUSH_ERROR_STATUS, $this->delay]);
    $modRcpt = Module::objectFactory($this->moid_rcpt);
    $modDevice = Module::objectFactory($this->moid_devices);
    getDB()->execute('DELETE FROM `'.$modRcpt->table.'` WHERE `push` NOT IN (SELECT KOID FROM `'.$this->table.'`) OR `device` NOT IN (SELECT KOID FROM `'.$modDevice->table.'`)');
  }
  
  private function cleanOldPendingNotifications() : void {
    $modRcpt = Module::objectFactory($this->moid_rcpt);
    $detail = json_encode(['message' => 'En cours d\'envoi depuis plus de 2 jours.']);
    //Les destinataires au statut "en cours d'envoi" de plus de 2 jours sont considérés comme étant en erreur.
    getDB()->execute('UPDATE `'.$modRcpt->table.'` SET status = ?, json = ? WHERE status = ? AND date_add(UPD, interval ? day) <= now()',
                     [self::RCPT_ERROR_STATUS, $detail, self::RCPT_PENDING_STATUS, self::CLEAN_PENDING_DELAY]);
    //Les destinataires au statut "Message transmis" depuis plus de 2 jours sont considérés comme étant envoyé.
    getDB()->execute('UPDATE `'.$modRcpt->table.'` SET status = ?, json = \'\' WHERE status = ? AND date_add(UPD, interval ? day) <= now()',
                     [self::RCPT_SENT_STATUS, self::RCPT_TRANSMITTED_STATUS, self::CLEAN_PENDING_DELAY]);
    
    $data = getDB()->fetchCol('SELECT DISTINCT KOID FROM `'.$this->table.'` WHERE send_state = ?', [self::PUSH_PENDING_STATUS]);
    foreach ($data as $oid) {
      $status = $this->getCalcStatus($oid);
      
      $arPush = [
        'oid' => $oid,
        'send_state' => $status['status'],
        'detail' => $status['message'],
      ];
  
      $this->xset->procEdit($arPush);
    }
  }
  
  protected function getAllRecipientsForPush($push_oid) : array {
    $modDevice = Module::objectFactory($this->moid_devices);
    $modRcpt = Module::objectFactory($this->moid_rcpt);
    $sql = 'SELECT r.KOID as oid_receipt, d.*, u.luser FROM `'.$modDevice->table.'` d INNER JOIN `'.$modRcpt->table.'` r ON d.KOID = r.device ';
    $sql .= 'INNER JOIN `USERS` u ON u.KOID = d.user ';
    $sql .= 'WHERE r.push = ? AND (trim(r.status) = \'\' OR r.status is null OR r.status = ?) AND trim(d.push_token) <> \'\' AND d.push_token is not null';
    $sql .= ' AND d.PUBLISH = 1';
    
    if (System::fieldExists('USERS', 'PUBLISH')) {
      $sql .= ' AND u.PUBLISH = 1';
    }
    
    if ($this->testMode()) {
      $sql .= ' AND d.test = 1';
    }
    
    $sql .= ' ORDER BY d.push_token';
    
    $recipients = [];
    $data = getDB()->fetchAll($sql, [$push_oid, self::RCPT_WAITING_STATUS]);
    
    foreach ($data as $item) {
      if (!array_key_exists($item['push_token_type'], $recipients)) {
        $recipients[$item['push_token_type']] = [];
      }
      
      if (!array_key_exists($item['push_token'], $recipients[$item['push_token_type']])) {
        $recipients[$item['push_token_type']][$item['push_token']] = [
          'platform' => $item['platform'],
          'oid' => $item['oid_receipt'],
          'lang' => $item['luser']
        ];
      }
    }
    
    return $recipients;
  }

  public function sendNotification2($more, $from = null) {
    $limit = $more->nb_notification_send_by_task ?? self::MAX_PUSH_SENT_PER_TASK;
      
    if ($limit > self::MAX_PUSH_SENT_PER_TASK) {
      $limit = self::MAX_PUSH_SENT_PER_TASK;
    }

    $pushs = getDB()->fetchAll('SELECT * FROM `'.$this->table.'` WHERE send_date <= now() AND PUBLISH = 1 AND send_state IN (?, ?) ORDER BY FIELD(send_state, ?, ?), send_date, UPD LIMIT '.$limit,
                               [self::PUSH_PENDING_STATUS, self::PUSH_SCHEDULED_STATUS, self::PUSH_PENDING_STATUS, self::PUSH_SCHEDULED_STATUS]);
    
    $objectProvider = [];
    foreach ($pushs as $push) {
      $this->xset->procEdit(['oid' => $push['KOID'], 'send_state' => self::PUSH_PENDING_STATUS]);
      
      $all = $this->getAllRecipientsForPush($push['KOID']);
      foreach ($all as $type => $recipients) {
        if (array_key_exists($type, self::TOKEN_TYPE_CLASS) && class_exists(self::TOKEN_TYPE_CLASS[$type]) && !array_key_exists($type, $objectProvider)) {
          $class = self::TOKEN_TYPE_CLASS[$type];
          $objectProvider[$type] = new $class($this, trim($this->ttl));
        }
        
        try {
          $objectProvider[$type]->addMessage($push);
        } catch (Exception $e) {
          $this->xset->procEdit(['oid' => $push['KOID'], 'send_state' => self::PUSH_ERROR_STATUS, 'detail' => json_encode($e)]);
        }
        
        $objectProvider[$type]->addRecipientsToMessage($push['KOID'], $push['LANG'], $recipients);
      }
    }

    $errors = [];
    foreach ($objectProvider as $provider) {
      try {            
        $r = $provider->sendAllMessages();
        DbIni::set('xmodpushnotification:providerunreachable', 0);
        
        if ($r['status'] === self::PUSH_ERROR_STATUS) {
          if (!array_key_exists($provider, $errors)) {
            $errors[$provider] = [];
          }
  
          $errors[$provider][] = $r['detail'];
        }

        foreach ($pushs as $push) {
          $arPush = [
            'send_state' => $r['status'],
            'oid' => $push['KOID'],
          ];
  
          if ($r['status'] !== self::PUSH_SENT_STATUS) {
            $arPush['detail'] = $r['detail'];
          } else {
            $status = $this->getCalcStatus($push['KOID']);
            $arPush['send_state'] = $status['status'];
            $arPush['detail'] = $status['message'];
          }
  
          $this->xset->procEdit($arPush);
        }
      } catch (Exception $e) {
        if ($e->getCode() === 'SERVER_UNREACHABLE') {
          $nbError = (int)DbIni::get('xmodpushnotification:providerunreachable','val');
          DbIni::set('xmodpushnotification:providerunreachable', $nbError+1);
        }
      }
    }
    if ($from == null){
      return $this->browse(["tplentry" => "br"]);
    }
  }

  public function sendNotification(Scheduler &$scheduler, $o, $more) : string {
    if(Lock::getExclusiveLock(__METHOD__)) {
      $schedulerStatus = 'finished';
      
      $this->sendNotification2($more, "scheduler");

      if ($scheduler !== null) {
        if (count($errors)) {
          $schedulerStatus = 'crashed';
          $detail = json_encode($errors);
        } else {
          $detail = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_PushNotification_PushNotification', 'push_sent');
        }
        
        $scheduler->statusJob($o->KOID, $schedulerStatus, $detail);
      } else {
        return $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_PushNotification_PushNotification', 'push_sent');
      }
    } else {
      Logs::debug(__METHOD__.': lock active');
      
      if ($scheduler !== null) {
        $scheduler->statusJob($o->KOID, $schedulerStatus, $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_PushNotification_PushNotification','task_already_running'));
      } else {
        return $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_PushNotification_PushNotification','task_already_running');
      }
    }
    
    return '';
  }
  
  protected function getStatusForAllRecepients($oid) : array {
    $modRcpt = Module::objectFactory($this->moid_rcpt);
    
    $data = getDB()->fetchAll('SELECT `status`, count(1) as nb FROM `'.$modRcpt->table.'` WHERE push = ? GROUP BY `status`', [$oid]);
    
    $return = [];
    foreach ($data as $item) {
      $return[$item['status']] = $item['nb'];
    }
    
    return $return;
  }
  
  protected function updatePushReceipt() : void {
    foreach (self::TOKEN_TYPE_CLASS as $type => $class) {
      try {
        $obj = new $class($this, trim($this->ttl));
        $obj->updatePushReceipt();
      } catch (Exception $e) {
        if ($e->getCode() === 'SERVER_UNREACHABLE') {
          $nbError = (int)DbIni::get('xmodpushnotification:providerunreachable','val');
          DbIni::set('xmodpushnotification:providerunreachable', $nbError+1);
        }
      }
    }
  }
  
  public function getCalcStatus($oid) : array {
    $status = $this->getStatusForAllRecepients($oid);
  
    $return = ['status' => '', 'message' => ''];
    
    if ($status[self::RCPT_ERROR_STATUS] > 0) {
      $return['status']  = self::PUSH_ERROR_STATUS;
      $return['message'] = json_encode(['message' => 'Voir le détail dans les destinataires.']);
    } elseif ($status[self::RCPT_WAITING_STATUS] && $this->testmode()) {
      $return['status'] = self::PUSH_SENT_STATUS;
    } elseif ($status[self::RCPT_WAITING_STATUS] > 0 || $status[self::RCPT_PENDING_STATUS] > 0) {
      $return['status'] = self::PUSH_PENDING_STATUS;
    } else {
      $return['status'] = self::PUSH_SENT_STATUS;
    }
    
    return $return;
  }
  
  public function secGroups($function, $group = null) {
    $g = [];
    $g['sendNotification'] = ['admin'];  // Methode pour le scheduler
    $g['sendNotification2'] = ['admin']; // Utilisé par le scheduler et dans les actions du module
  
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    
    return parent::secGroups($function, $group);
  }
  
  public function getPublicConfig($ar=NULL) {
    $ret = parent::getPublicConfig($ar);
    
    if((int)DbIni::get('xmodpushnotification:providerunreachable','val') > self::MAX_UNREACHABLE_ERROR_BEFORE_ALERT) {
      $ret['errors'][] = 'Provider (Expo) inaccessible lors des '.self::MAX_UNREACHABLE_ERROR_BEFORE_ALERT.' tentatives.';
    }
    
    return $ret;
  }
}