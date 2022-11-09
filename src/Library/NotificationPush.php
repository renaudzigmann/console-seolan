<?php
/**
 * Classe de gestion de base des notification push
 * @author : Vincent Castille
 * @date: 20/01/2017
 */
namespace Seolan\Library;
class NotificationPush {
  const TYPE_ANDROID = 'android';
  const TYPE_WINDOWS = 'windows';
  const TYPE_APPLE = 'apple';
  
  const WINDOWS_DELAY_IMMEDIATE = 2;
  const WINDOWS_DELAY_450SEC = 12;
  const WINDOWS_DELAY_900SEC = 22;
  
  public static $AVAILABLE_TYPE = [self::TYPE_ANDROID, self::TYPE_WINDOWS, self::TYPE_APPLE];
  public static $AVAILABLE_WINDOWS_DELAY = [self::WINDOWS_DELAY_IMMEDIATE, self::WINDOWS_DELAY_450SEC, self::WINDOWS_DELAY_900SEC];
  
  protected $devices = [];
  protected $moid;
  protected $loid;
  protected $accountsByType = [];
  
  protected $title;
  protected $message;
  protected $data;
  protected $link;
  protected $query;

  protected $windowsDelay = self::WINDOWS_DELAY_IMMEDIATE;
  
  protected $nbDestOK = 0;
  protected $nbDestKO = 0;
  
  protected $detailResult = [];
  
  protected $hasError = false;
  
  /** @var \Seolan\Model\DataSource\Table\Table $xSetLog  */
  protected static $xSetLog = null;
  
  /** @var \Seolan\Model\DataSource\Table\Table $xSetLogDetail  */
  protected static $xSetLogDetail = null;
  
  /**
   * XNotificationPush constructor.
   *
   * @param int $moid
   */
  public function __construct($moid, $loid = null) {
    $this->moid = (int)$moid;
  
    if (!(self::$xSetLog instanceof \Seolan\Model\DataSource\Table\Table)) {
      self::$xSetLog = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_MLOGS');
    }
  
    if (!(self::$xSetLogDetail instanceof \Seolan\Model\DataSource\Table\Table)) {
      self::$xSetLogDetail = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_MLOGSD');
    }
    
    if (!is_null($loid)) {
      $this->loid = $loid;
    }
    
    $this->loadAccounts();
  }
  
  
  /**
   * initialise le log
   */
  protected function initLog(){
    $ar = [
      'subject' => $this->title,
      'body' => $this->message,
      'data' => $this->data,
      'link' => $this->link,
      'sender' => \Seolan\Core\User::get_current_user_uid(),
      'modid' => $this->moid,
      'nbdest' => array_sum(array_map("count", $this->devices)),
      'mtype' => 'Notification Push',
      'html' => false,
      'comment' => $this->query,
      'size' => round(strlen($this->message) / 1024, 2),
      'datep' => date('Y-m-d H:i:s'),
      'dest' => '',
      '_nolog' => true,
      '_options' => ['local' => true],
      'tplentry' => TZR_RETURN_DATA,
    ];
    
    if (!empty($this->loid)) {
      $ar['oid'] = $this->loid;
    }
  
    if (!empty($this->loid)) {
      self::$xSetLog->procEdit($ar);
    } else {
      $r          = self::$xSetLog->procInput($ar);
      $this->loid = $r['oid'];
    }
  }
  
  
  /**
   * @param array $data
   */
  protected function editLog($data = null){
    $ar = [
      'oid' => $this->loid,
      '_nolog' => true,
      'tplentry' => TZR_RETURN_DATA,
      '_options' => ['local' => true],
    ];
    
    if (is_array($data)) {
      $ar = array_merge($ar, $data);
    }
    
    self::$xSetLog->procEdit($ar);
  }
  
  
  /**
   * @param array $dest : [ registration_data => '', application_type => '' ]
   * @param string $status : success | warning | error
   * @param string $errmess : detail du status
   *
   * @return bool
   */
  public function addDetailLog($dest, $status, $errmess = ''){
    if(empty($this->loid)){
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'add report not initialized');
      return false;
    }
    
    //TODO: gerer la reprise sur erreur en tantant de renvoyer les notifications
    $reex = 3;
    
    if ($status === 'success') {
      $reex = 3;
    }
    
    $ar = [
      'subject' => $this->title,
      'body' => $this->message,
      'mlogh' => $this->loid,
      'tplentry' => TZR_RETURN_DATA,
      '_nolog' => true,
      '_options' => ['local' => true],
      'sstatus'=>$status,
      'errmess' => $errmess,
      'mails' => json_encode($dest),
      'reex' => $reex,
    ];
    
    $r = self::$xSetLogDetail->procInput($ar);
    return $r['oid'];
  }
  
  
  /**
   * @param int $delay
   *
   * @return bool
   */
  public function setWindowsDelay ($delay) {
    if (in_array($delay, self::$AVAILABLE_WINDOWS_DELAY, true)) {
      $this->windowsDelay = $delay;
    } else {
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'Erreur, mauvais delai Windows !');
      return false;
    }
    
    return true;
  }
  
  
  /**
   * @return array : un tableau avec en cle le registration_data et en valeur un tableau de la forme : [success => bool, message => string]
   */
  public function getDetailResult() {
    return $this->detailResult;
  }
  
  
  /**
   * @return bool
   */
  public function getHasError() {
    return $this->hasError;
  }
  
  
  /**
   * Methode qui charge tous les comptes par type (android, apple, windows)
   */
  protected function loadAccounts() {
    foreach (self::$AVAILABLE_TYPE as $type) {
      $recordSet = getDB()->select('SELECT * FROM `_ACCOUNTS` WHERE `atype` = "PUSH" AND `modid` = ? AND `login` = ?', [$this->moid, $type]);
      
      if ($recordSet->rowCount() > 0) {
        $accouts = $recordSet->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->accountsByType[$type] = [
          'url' => $accouts[0]['url'],
          'passwd' => $accouts[0]['passwd'],
          'certificate' => $accouts[0]['cplt'],
        ];
      } else {
        $this->accountsByType[$type] = false;
      }
    }
  }
  
  
  /**
   * Methode qui renvoie la liste des comptes configures pour envoyer des notifications
   * @param int $moid
   * @param string $name
   *
   * @return array|null
   */
  public static function getNotificationAccounts($moid = null, $name = null) {
    $pushAccounts = null;
    if (\Seolan\Core\System::tableExists('_ACCOUNTS')) {
      if (!is_null($moid)) {
        $recordSet = getDB()->select('SELECT * FROM `_ACCOUNTS` WHERE `atype` = "PUSH" AND `modid` = ?', [$moid]);
      } else {
        $recordSet = getDB()->select('SELECT * FROM `_ACCOUNTS` WHERE `atype` = "PUSH" AND `name` = ?', [$name]);
      }
      
      if ($recordSet->rowCount() > 0){
        $pushAccounts = [];
      }
      
      while($recordSet && ($line = $recordSet->fetch())){
        $pushAccounts[$line['KOID']]['type'] = $line['login'];
        $pushAccounts[$line['KOID']]['url'] = $line['url'];
        $pushAccounts[$line['KOID']]['passwd'] = $line['passwd'];
        $pushAccounts[$line['KOID']]['certificate'] = $line['cplt'];
      }
    }
    
    return $pushAccounts;
  }
  
  
  /**
   * @param string $registrationData : donnee d'enregistrement (ID, device token ou URI)
   * @param string $type : Type de device (android, windows, apple)
   *
   * @return bool
   */
  public function addDevices ($registrationData, $type) {
    if (!in_array($type, self::$AVAILABLE_TYPE)) {
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'Mauvais type de device ! Uniquement un type definit dans '.__CLASS__.'::$AVAILABLE_TYPE .');
      return false;
    }
    
    if (!$this->alreadyAddDevice($registrationData, $type)) {
      $this->devices[$type][] = $registrationData;
    }
    
    return true;
  }
  
  
  /**
   * @param $registrationData
   * @param $type
   *
   * @return bool
   */
  protected function alreadyAddDevice ($registrationData, $type) {
    return in_array($registrationData, $this->devices[$type], true);
  }


  /**
   * Envoie les notifications aux devices ajoutes
   * @param string $title : titre du message a envoyer
   * @param string $message : message a envoyer
   * @param string $data : Données supplémentaires au format json
   * @param string $link : Lien vers l'objet envoyé
   * @param string $query : Requête pour sélectionner les utilisateurs
   *
   * @return bool
   */
  public function send($title, $message, $data='', $link='', $query='') {
    $this->title   = trim($title);
    $this->message = trim($message);
    $this->data = trim($data);
    $this->link = trim($link);
    $this->query = trim($query);

    if (count($this->devices) === 0) {
      \Seolan\Core\Logs::critical(__METHOD__.' '.' ', 'Aucun devices !');
      return false;
    }
    
    if (empty($title)) {
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'Le titre du message ne peut pas être vide !');
      return false;
    }
  
    if (empty($message)) {
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'Le message ne peut pas être vide !');
      return false;
    }
  
    if(empty($this->loid)) {
      $this->initLog();
    }
  
    $result = true;
    foreach (self::$AVAILABLE_TYPE as $type) {
      if (method_exists($this, 'send'.ucfirst($type))) {
        if (count($this->devices[$type])) {
          $result = $result && $this->{'send'.ucfirst($type)}($this->devices[$type]);
        } else {
          \Seolan\Core\Logs::debug(__METHOD__.' moid '.$this->moid.' Aucun destinataire pour la plateforme '.$type);
        }
      } else {
        \Seolan\Core\Logs::critical(__METHOD__.' ', 'Methode '.__CLASS__.'::send'.ucfirst($type).' inexistante !');
        return false;
      }
    }
  
    if (!empty($this->loid)) {
      $this->editLog(['datee' => date('Y-m-d H:i:s')]);
      $this->agregateLog();
    }
    
    return $result;
  }
  
  
  /**
   * @param array | string $regID : ID du/des device(s)
   *
   * @return bool
   */
  protected function sendAndroid($regID) {
    if ($this->accountsByType[self::TYPE_ANDROID]) {
      $headers = [
        'Authorization: key='.$this->accountsByType[self::TYPE_ANDROID]['passwd'],
        'Content-Type: application/json'
      ];
      
      if (!is_array($regID)) {
        $regID = [$regID];
      }

      $postDatas = [
        'registration_ids' => $regID,
        'data' => [
          'title' => $this->title,
          'body' => $this->message
        ]
      ];

      $customData = json_decode($this->data, true);
      if($customData) {
        $postDatas['data'] = array_merge($postDatas['data'], $customData);
      }

      $dataReturn = $this->useCurl($this->accountsByType[self::TYPE_ANDROID]['url'], $headers, json_encode($postDatas));
      
      if ($dataReturn === false) {
        return false;
      } else {
        $result = json_decode($dataReturn, true);
        
        $this->processResultFirebase($result, self::TYPE_ANDROID);
        
        return true;
      }
      
    } else {
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'Pas de compte externe android configure.');
    }
    
    return false;
  }
  
  
  /**
   * @param array | string $URI : URI du/des device(s)
   *
   * @return bool
   */
  protected function sendWindows($URI){
    //if ($this->accountsByType[self::TYPE_WINDOWS]) {
      $postDatas = '<?xml version="1.0" encoding="utf-8"?>'.'<wp:Notification xmlns:wp="WPNotification">
        <wp:Toast>
        <wp:Text1>'.htmlspecialchars($this->title).'</wp:Text1>
        <wp:Text2>'.htmlspecialchars($this->message).'</wp:Text2>
        </wp:Toast>
        </wp:Notification>';
  
      $headers = [
        'Content-Type: text/xml',
        'Accept: application/*',
        'X-WindowsPhone-Target: toast',
        'X-NotificationClass: '.$this->windowsDelay,
      ];
  
      $result = [];
      if (is_array($URI)) {
        foreach ($URI as $item) {
          $response = $this->useCurl($item, $headers, $postDatas);
          
          if ($response === false) {
            return false;
          } else {
            foreach (explode("\n", $response) as $line) {
              $tab = explode(':', $line, 2);
              if (count($tab)===2) {
                $result[$item][$tab[0]] = trim($tab[1]);
              }
            }
          }
        }
      } else {
        $response = $this->useCurl($URI, $headers, $postDatas);
        
        if ($response === false) {
          return false;
        } else {
          foreach (explode("\n", $response) as $line) {
            $tab = explode(':', $line, 2);
            if (count($tab)===2) {
              $result[$URI][$tab[0]] = trim($tab[1]);
            }
          }
        }
      }
  
      $this->processResultWindows($result);
  
      return true;
    //} else {
    //  \Seolan\Core\Logs::critical(__METHOD__.' ', 'Pas de compte externe Windows configure.');
    //}
    //
    //return false;
  }
  
  
  
  /**
   * @param string | array $devicesToken : token du/des device(s)
   *
   * @return bool
   */
  protected function sendApple ($devicesToken) {
    if ($this->accountsByType[self::TYPE_APPLE]) {
      if ($this->accountsByType[self::TYPE_APPLE]['certificate'] === 'firebase') {
        return $this->sendAppleFirebase($devicesToken);
      } else {
        return $this->sendAppleCloud($devicesToken);
      }
    } else {
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'Pas de compte externe Apple (iOS) configure.');
    }
    
    return false;
  }
  
  /**
   * @param string | array $regID : token du/des device(s)
   *
   * @return bool
   */
  protected function sendAppleFirebase ($regID) {
    $headers = [
      'Authorization: key='.$this->accountsByType[self::TYPE_APPLE]['passwd'],
      'Content-Type: application/json'
    ];
  
    if (!is_array($regID)) {
      $regID = [$regID];
    }
  
    $postDatas = [
      'registration_ids' => $regID,
      'mutable_content' => true,
      'notification' => [
        'title' => $this->title,
        'body' => $this->message,
        'sound' => 'default',
      ],
      'data' => [],
    ];
  
    $customData = json_decode($this->data, true);
    if($customData) {
      $postDatas['data'] = array_merge($postDatas['data'], $customData);
    }
  
    
    $dataReturn = $this->useCurl($this->accountsByType[self::TYPE_APPLE]['url'], $headers, json_encode($postDatas));
  
    if ($dataReturn === false) {
      return false;
    } else {
      $result = json_decode($dataReturn, true);
    
      $this->processResultFirebase($result, self::TYPE_APPLE);
    
      return true;
    }
  }
  
  /**
   * @param string | array $devicesToken : token du/des device(s)
   *
   * @return bool
   */
  protected function sendAppleCloud ($devicesToken) {
    $ctx = stream_context_create();
  
    if (!stream_context_set_option($ctx, 'ssl', 'local_cert', $this->accountsByType[self::TYPE_APPLE]['certificate'])) {
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'Erreur lors de l\'ajout du certificat !');
      return false;
    }
  
    if (!stream_context_set_option($ctx, 'ssl', 'passphrase', $this->accountsByType[self::TYPE_APPLE]['passwd'])) {
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'Erreur lors de l\'ajout de la passphrase !');
      return false;
    }
  
    $errno = null;
    $errstr = null;
    $fp = stream_socket_client($this->accountsByType[self::TYPE_APPLE]['url'], $errno, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
  
    if ($fp === false) {
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'Failed to connect : ('.$errno.') '.$errstr);
      return false;
    }
  
    $body['aps'] = [
      'alert' => [
        'title' => $this->title,
        'body'  => $this->message,
      ],
      'sound' => 'default'
    ];
  
    $customData = json_decode($this->data, true);
    if($customData) {
      $body['aps'] = array_merge($body['aps'], $customData);
    }
  
    $payload = json_encode($body);
  
    $result = [];
    if (is_array($devicesToken)) {
      foreach ($devicesToken as $deviceToken) {
        $msg = chr(0).pack('n', 32).pack('H*', $deviceToken).pack('n', strlen($payload)).$payload;
      
        $result[$deviceToken] = fwrite($fp, $msg, strlen($msg)) > 0 ? true : false;
      }
    } else {
      $msg = chr(0).pack('n', 32).pack('H*', $devicesToken).pack('n', strlen($payload)).$payload;
    
      $result[$devicesToken] = fwrite($fp, $msg, strlen($msg)) > 0 ? true : false;
    }
  
    fclose($fp);
  
    $this->processResultApple($result);
  
    return true;
  }
  
  /**
   * @param array $result
   * @param string $type : constante TYPE_ANDROID, TYPE_APPLE
   */
  protected function processResultFirebase($result, $type) {
    if (array_key_exists('failure', $result)) {
      $this->nbDestKO = (int)$result['failure'];
    }
    
    if (array_key_exists('success', $result)) {
      $this->nbDestOK = (int)$result['success'];
    }
    
    if (array_key_exists('results', $result) && is_array($result['results'])) {
      foreach ($result['results'] as $i => $item) {
        $message = 'Notification successfully sent';
        $success = true;
        if (array_key_exists('error', $item)) {
          $message = $item['error'];
          $success = false;
        }
        
        $this->detailResult[$this->devices[$type][$i]] = [
          'success' => $success,
          'message' => $message,
        ];
        
        if (array_key_exists('registration_id', $item)) {
          $this->detailResult[$this->devices[$type][$i]]['updateToken'] = $item['registration_id'];
        }
      }
    }
    
    if ($this->nbDestKO > 0) {
      $this->hasError = true;
    }
  }
  
  /**
   * @param array $result
   */
  protected function processResultWindows($result) {
    ppp($result);
  }
  
  
  /**
   * @param array $result
   */
  protected function processResultApple($result) {
    foreach ($result as $key => $item) {
      $this->detailResult[$key] = [
        'success' => $item,
        'message' => $item ? 'Notification successfully sent' : 'Notification not sent',
      ];
      
      if ($item === true) {
        $this->nbDestOK++;
      } else {
        $this->nbDestKO++;
      }
    }
  
    if ($this->nbDestKO > 0) {
      $this->hasError = true;
    }
  }
  
  protected function agregateLog() {
    getDB()->execute('UPDATE `_MLOGS` SET `nbdest` = ?, `nberr` = ? WHERE `KOID` = ?', [
      count($this->devices),
      $this->nbDestKO,
      $this->loid
    ]);
  }
  
  
  /**
   * @param string $url : url a appeler
   * @param array $headers : entetesa ajouter a la requette (cf : curl_setopt([channel], CURLOPT_HTTPHEADER, $headers))
   * @param array $fields : donnees a envoyer
   *
   * @return string | bool : renvoye false en cas d'erreur. Si OK, renvoie le resultat
   */
  protected function useCurl($url, $headers, $fields = null) {
    $ch = curl_init();
    $url = trim($url);
    
    if (!empty($url)) {
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      if (!is_null($fields)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
      }
      
      $result = curl_exec($ch);
      if ($result === false) {
        \Seolan\Core\Logs::critical(__METHOD__.' ', 'Curl failed: '.curl_error($ch));
        return false;
      }
      
      if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
        \Seolan\Core\Logs::critical(__METHOD__.' ', 'Curl HTTP CODE Error : '.curl_getinfo($ch, CURLINFO_HTTP_CODE));
        return false;
      }
      
      curl_close($ch);
      
      return $result;
    } else {
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'URL vide !');
    }
    
    return false;
  }
}
