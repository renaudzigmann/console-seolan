<?php


namespace Seolan\Module\PushNotification;

use Seolan\Core\Exception\Exception;
use Seolan\Core\JsonClient\Request;
use Seolan\Core\Kernel;
use Seolan\Core\Module\Module;

class Expo implements PushProvider {
  const MAX_MESSAGE_PER_REQUEST = 100;
  const MAX_RECIEPT_PER_REQUEST = 1000;
  const MAX_MESSAGE_SIZE = 4096;
  const URL_SENT = 'https://exp.host/--/api/v2/push/send';
  const URL_RECEIPTS = 'https://exp.host/--/api/v2/push/getReceipts';
  
  protected $ttl = null;
  protected $module;
  protected $messages;
  protected $allRecepients = [];
  
  public function __construct(&$module, $ttl = null) {
    $this->setTTl($ttl);
    $this->setModule($module);
  }
  
  public function addMessage(array $message) : void {
    if (!$this->checkMessageSize($message)) {
      throw new Exception($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_PushNotification_PushNotification','message_to_big'));
    }

    $idMessage = $message['LANG'].'.'.$message['KOID'];
    if (!array_key_exists($idMessage, $this->messages) && count($this->messages) < self::MAX_MESSAGE_PER_REQUEST) {
      $this->messages[$idMessage] = new Message($this);
      $this->messages[$idMessage]->setID($idMessage);
      $this->messages[$idMessage]->setTitle((string)$message['title']);
      $this->messages[$idMessage]->setSubtitle((string)$message['subtitle']);
      $this->messages[$idMessage]->setJson((string)$message['json_data']);
      $this->messages[$idMessage]->setBody((string)$message['body']);
      $this->messages[$idMessage]->setBadgeCount((int)$message['badge_count']);
      $this->messages[$idMessage]->setPlaySound((int)$message['play_sound'] === 1);
      $this->messages[$idMessage]->setChannelId((string)$message['channel_id']);
      $this->messages[$idMessage]->setTTl($this->ttl);
    }
  }
  
  private function checkMessageSize(array $message) : bool {
    $fullMessage = $message['title'].$message['subtitle'].$message['json_data'].$message['body'];
    
    return strlen($fullMessage) < self::MAX_MESSAGE_SIZE;
  }
  
  public function addRecipientsToMessage(string $oid, string $lang, array $recipients) : void {
    foreach ($recipients as $token => $recipient) {
      if (count($this->allRecepients, COUNT_RECURSIVE) < self::MAX_RECIEPT_PER_REQUEST) {
        $this->allRecepients[] = ['push_oid' => $oid, 'token' => $token, 'oid_receipt' => $recipient['oid'], 'lang' => $recipient['lang'] ?? TZR_DEFAULT_LANG];
        $this->messages[$lang.'.'.$oid]->addRecipient($token, $recipient);
      }
    }
  }
  
  public function clearAll() : void {
    $this->messages = [];
    $this->allRecepients = [];
  }
  
  public function sendAllMessages() : array {
    $request = new Request([Request::HEADER_JSON]);
    
    $data = [];
    foreach ($this->messages as $message) {
      $msg = (object)$message;
      unset($msg->provider);
      
      if ($message->hasRecipient()) {
        $tmp = $message->getRequestData();
        
        if (count($tmp) > 0) {
          $data[] = $tmp;
        }
        unset($tmp);
      }
    }
    
    if (!count($data)) {
      return ['status' => PushNotification::PUSH_ERROR_STATUS, 'detail' => json_encode(['message' => $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_PushNotification_PushNotification','message_no_data')])];
    }
    
    $headers = [
      Request::HEADER_JSON,
    ];
    if (!empty($this->module->access_token)) {
      $headers[] = 'Authorization: Bearer '.$this->module->access_token;
    }
    
    $response = $request->doRequest(Request::POST, self::URL_SENT, $data, $headers);
    
    if ($response->getStatus() === 0 || $response->getStatus() === 404) {
      throw new Exception('Impossible de joindre l\'URL '.self::URL_SENT, 'SERVER_UNREACHABLE');
    }
    
    if ($response->getStatus() === 200) {
      if (!empty($response->data)) {
        $data = json_decode($response->data, true)['data'];
        $modDevice = Module::objectFactory($this->module->moid_devices);
        
        foreach ($data as $i => $item) {
          if (is_array($this->allRecepients[$i])) {
            $table = Kernel::getTable($this->allRecepients[$i]['oid_receipt']);
            $status = $item['status'] === 'ok' ? PushNotification::RCPT_TRANSMITTED_STATUS : PushNotification::RCPT_ERROR_STATUS;
            $detail = $item['status'] === 'ok' ? [] : $item;
            getDB()->execute('UPDATE `'.$table.'` SET `status` = ?, ticket_id = ?, json = ? WHERE KOID = ?',
                             [$status, $item['id'], json_encode($detail), $this->allRecepients[$i]['oid_receipt']]);
            
            if ($item['status'] !== 'ok') {
              $modDevice->updateNbError($this->module->moid_rcpt, $this->allRecepients[$i]['token']);
            }
            
            if ($item['details']['error'] === 'DeviceNotRegistered') {
              $modDevice->unregisterPushDevice($this->allRecepients[$i]['token']);
            }
          }
        }
      }
  
      return ['status' => PushNotification::PUSH_SENT_STATUS];
    } else {
      $errors = [];
      if (!empty($response->data)) {
        $errors = json_decode($response->data)->errors;
      }
      
      return ['status' => PushNotification::PUSH_ERROR_STATUS, 'detail' => json_encode($errors)];
    }
  }
  
  public function updatePushReceipt() : void {
    $modRcpt = Module::objectFactory($this->module->moid_rcpt);
    $modDevice = Module::objectFactory($this->module->moid_devices);
    $recepients = getDB()->fetchAll('SELECT r.*, d.push_token FROM `'.$modRcpt->table.'` r INNER JOIN `'.$modDevice->table.'` d ON r.device = d.KOID WHERE trim(ticket_id) <> \'\' AND ticket_id IS NOT NULL AND `status` NOT IN (?,?)',
                      [PushNotification::RCPT_SENT_STATUS, PushNotification::RCPT_ERROR_STATUS]);
    
    $ids = [];
    $pushs = [];
    foreach ($recepients as $recepient) {
      if (!array_key_exists($recepient['ticket_id'], $ids)) {
        $ids[$recepient['ticket_id']] = $recepient['push_token'];
      }
  
      if (!in_array($recepient['push'], $ids, true)) {
        $pushs[] = $recepient['push'];
      }
    }
    
    if(count($ids)) {
      $request = new Request([Request::HEADER_JSON]);
      
      $response = $request->doRequest(Request::POST, self::URL_RECEIPTS, ['ids' => array_keys($ids)], []);
      
      if ($response->getStatus() === 0 || $response->getStatus() === 404) {
        throw new Exception('Impossible de joindre l\'URL '.self::URL_SENT, 'SERVER_UNREACHABLE');
      }
      
      if ($response->getStatus() === 200 && !empty($response->data)) {
        $data = json_decode($response->data, true)['data'];
        
        foreach ($data as $ticket_id => $detail) {
          if ($detail['status'] === 'ok') {
            getDB()->execute('UPDATE `'.$modRcpt->table.'` SET `status`= ? WHERE `ticket_id` = ?', [PushNotification::RCPT_SENT_STATUS, $ticket_id]);
          } else {
            getDB()->execute('UPDATE `'.$modRcpt->table.'` SET `status`= ?, `json` = ? WHERE `ticket_id` = ?', [PushNotification::RCPT_ERROR_STATUS, json_encode($detail), $ticket_id]);
            
            switch ($detail['details']['error']) {
              case 'DeviceNotRegistered' :
                $modDevice->unregisterPushDevice($ids[$ticket_id]);
                break;
                
              case 'MessageRateExceeded' :
                $modDevice->unPublishToken($ids[$ticket_id]);
                break;
            }
  
            $modDevice->updateNbError($this->module->moid_rcpt, $ids[$ticket_id]);
          }
        }
      }
    }
    
    if (count($pushs)) {
      foreach ($pushs as $push){
        $arPush = [
          'oid' => $push,
        ];
        
        $status = $this->module->getCalcStatus($push);
        $arPush['send_state'] = $status['status'];
        $arPush['detail'] = $status['message'];
        $this->module->xset->procEdit($arPush);
      }
    }
  }
  
  public function setTTl($ttl) : void {
    if ($ttl !== null && $ttl !== '' && (int)$ttl >= 0) {
      $this->ttl = (int)$ttl;
    } else {
      $this->ttl = null;
    }
  }
  
  public function setModule(&$module) : void {
    $this->module = &$module;
  }
}

class Message {
  public $provider;
  protected $ID;
  protected $title;
  protected $subtitle;
  protected $json;
  protected $body;
  protected $badge_count;
  protected $play_sound;
  protected $channel_id;
  protected $recipients = [];
  protected $ttl = null;
  
  public function __construct(Expo &$provider) {
    $this->provider = &$provider;
  }
  
  public function getRequestData() : array {
    $return = ['priority' => 'high'];
    
    if (!empty($this->title)) {
      $return['title'] = $this->title;
    }
    
    if (!empty($this->subtitle)) {
      $return['subtitle'] = $this->subtitle;
    }
    
    if (!empty($this->body)) {
      $return['body'] = $this->body;
    }
    
    if (!empty($this->json)) {
      $return['data'] = $this->json;
    }
    
    if ($this->ttl !== null && $this->ttl >= 0) {
      $return['ttl'] = $this->ttl;
    }
    
    $return['sound'] = $this->play_sound === true ? 'default' : null;
    
    if ($this->badge_count !== null && $this->badge_count > 0) {
      $return['badge'] = $this->badge_count;
    }
    
    if (!empty($this->channel_id) && $this->channel_id !== 'default') {
      $return['channelId'] = $this->channel_id;
    }
  
    $return['to'] = array_keys($this->recipients);
    
    if (count($return['to']) === 1) {
      $return['to'] = array_shift($return['to']);
    }
    
    if (count($return['to']) === 0) {
      return [];
    }
    
    return $return;
  }
  
  public function setID(string $ID) : void {
    $this->ID = $ID;
  }
  
  public function setTitle(string $title) : void {
    $this->title = trim($title);
  }
  
  public function setSubtitle(string $subtitle) : void {
    $this->subtitle = trim($subtitle);
  }
  
  public function setJson(string $json = null) : void {
    json_decode($json);
    
    if (json_last_error() === JSON_ERROR_NONE) {
      $this->json = $json;
    }
  }
  
  public function setBody(string $body) : void {
    $this->body = trim($body);
  }
  
  public function setBadgeCount(int $badge_count) : void {
    $this->badge_count = $badge_count;
  }
  
  public function setPlaySound(bool $play_sound) : void {
    $this->play_sound = $play_sound;
  }
  
  public function setChannelId(string $channel_id) : void {
    $channel_id = trim($channel_id);
    if (empty($channel_id)) {
      $this->channel_id = 'default';
    } else {
      $this->channel_id = $channel_id;
    }
  }
  
  public function addRecipient(string $token, array $recipient) : void {
    $table = Kernel::getTable($recipient['oid']);
    if ($recipient['lang'] === $this->getLang()) {
      $this->recipients[$token] = $recipient;
      getDB()->execute('UPDATE `'.$table.'` SET `status` = ? WHERE KOID = ?', [PushNotification::RCPT_PENDING_STATUS, $recipient['oid']]);
    }else if ($recipient['lang'] === "") {
      $error = array('error'=>'the recipient does not have a defined language for the environment.');
      getDB()->execute('UPDATE `'.$table.'` SET `status` = ?, `json` = ? WHERE KOID = ?', [PushNotification::RCPT_ERROR_STATUS, json_encode($error),$recipient['oid']]);
    }
  }
  
  public function setTTl($ttl) : void {
    if ($ttl !== null && $ttl !== '' && (int)$ttl >= 0) {
      $this->ttl = (int)$ttl;
    } else {
      $this->ttl = null;
    }
  }
  
  public function getLang() : string {
    return substr($this->ID, 0, 2);
  }
  
  public function hasRecipient() : bool {
    return count($this->recipients) > 0;
  }
}
