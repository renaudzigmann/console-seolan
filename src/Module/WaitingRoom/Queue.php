<?php

namespace Seolan\Module\WaitingRoom;

/**
 * Queue, gestion file d'attente utilisateur
 *
 */
use PDO;
use Seolan\Core\Ini;
use Seolan\Core\Logs;
use Seolan\Core\Shell;
use Seolan\Core\System;
use Seolan\Library\Lock;
use Seolan\Library\Redis;

class Queue {

  // la charge courante
  private static $loadAvg;
  // les longueurs de la file, par status
  private static $lengths;
  // la conf
  private static $_conf = null;
  // facteur d'accélération
  private static $accelFactor = 1;
  // timestamp lecture conf
  private static $confReadAt;

  public static function useRedis() {
    return Ini::get('wr_use_redis') && Redis::isOnline();
  }

  // controle de la session (Shell::run)
  public static function check() {
    if (Shell::isRoot()) {
      return;
    }
    // permettre la connexion root
    if (defined('TZR_ADMINI') && TZR_ADMINI && (!Ini::get('wr_activeBO') || !\Seolan\Core\User::authentified())) {
      return;
    }
    if (getSessionVar('WR', 'WR')['status'] == 'active') {
      self::updateActivity();
      Logs::debug(__METHOD__ . ' user status is active ' . session_id());
      return;
    }
    if (!self::active()) {
      setSessionVar('WR', ['status' => 'active', 'origin' => 'noqueue', 'cread' => date('Y-m-d H:i:s'), 'sessid' => session_id()], 'WR');
      Logs::debug(__METHOD__ . ' WR not active, set user status to active ' . session_id());
      return;
    }
    Logs::debug(__METHOD__ . ' WR active, set user in queue ' . session_id());
    self::addInQueue();
    self::showWaitingRoom();
  }

  public static function active($refresh = false) {
    if ($refresh) {
      loadIni($refresh);
    }
    if (empty(Ini::get('wr_active'))) {
      return false;
    }
    $active = self::getConf('active');
    if ($active) {
      Logs::debug(__METHOD__ . ' conf::active true');
      return true;
    }
    $needed = self::loadAvg() > self::loadLimit() || !self::empty('waiting');
    if ($needed && !$active) {
      Logs::critical(__METHOD__ . ' activate WaitingRoom ');
      self::setConf('active', true);
    }
    return $needed;
  }

  // enregistre l'activité d'une session
  protected static function updateActivity() {
    if (!self::active()) {
      return;
    }
    Logs::debug(__METHOD__ . ' ' . session_id());
    if (self::useRedis()) {
      self::updateRedisActivity();
    } else {
      self::updateDBActivity();
    }
  }

  protected static function updateRedisActivity() {
    $haskKey = 'wr:' . session_id();
    $exists = Redis::client()->hget($haskKey, 'UPD');
    if ($exists) {
      $transac = Redis::client()->transaction()
        ->hset($haskKey, 'UPD', time())
        ->hset($haskKey, 'lasturl', $_SERVER['SCRIPT_URL'])
        ->expire($haskKey, TZR_SESSION_DURATION);
      if (getSessionVar('WR', 'WR')['status'] == 'active') {
        $transac->zadd('wr_recentActivity', time(), session_id());
      }
      $transac->execute();
    } else {
      Logs::debug(__METHOD__ . ' unknow session ' . session_id());
      self::addInQueue();
    }
  }

  protected static function updateDBActivity() {
    $updated = getDB()->execute('UPDATE WAITINGROOM set UPD=now(), lasturl=? where koid=?',
      [$_SERVER['SCRIPT_URL'], 'WAITINGROOM:' . session_id()]);
    if ($updated) {
      return;
    }
    // check unknown session, $updated can be false (same second update)
    if (!$updated && !getDB()->fetchOne('select koid from WAITINGROOM where koid=?', ['WAITINGROOM:' . session_id()])) {
      Logs::debug(__METHOD__ . ' unknow session ' . session_id());
      self::addInQueue();
    }
  }

  // ajoute une session en queue
  protected static function addInQueue() {
    $session = getSessionVar('WR', 'WR');
    if (empty($session['status'])) {
      $session['status'] = 'waiting';
    }
    if (empty($session['cread']) || $session['status'] == 'waiting') {
      $session['cread'] = date('Y-m-d H:i:s');
    }
    $retroDelay = Ini::getWithDefault('wr_retro_delay', 0);
    if ($session['cread'] > date('Y-m-d H:i:s', strtotime("- $retroDelay sec"))) {
      $session['status'] = 'waiting';
    }
    if (self::useRedis()) {
      $haskKey = 'wr:' . session_id();
      Redis::client()->transaction()
        ->hset($haskKey, 'CREAD', $session['cread'])
        ->hset($haskKey, 'UPD', time())
        ->hset($haskKey, 'status', $session['status'])
        ->hset($haskKey, 'lasturl', $_SERVER['SCRIPT_URL'])
        ->expire($haskKey, TZR_SESSION_DURATION)
        ->zadd('wr_' . $session['status'], time(), session_id())
        ->execute();
    } else {
      getDB()->execute('replace into WAITINGROOM (KOID, CREAD, sessid, status) values(?, ?, ?, ?)',
        ['WAITINGROOM:' . session_id(), $session['cread'], session_id(), $session['status']]);
    }
    Logs::debug(__METHOD__ . " {$session['cread']} {$session['status']} {$session['origin']} " . session_id());
    setSessionVar('WR', $session, 'WR');
  }

  public static function remove() {
    clearSessionVar('WR', 'WR');
  }

  // appel ajax depuis la page d'attente
  public static function getStatus() {
    $session = getSessionVar('WR', 'WR');
    // devrait avoir une session, reload => retour en attente
    if (empty($session) || empty($session['status'])) {
      Logs::debug(__METHOD__ . ' user as no session ' . session_id());
      returnJson(['status' => 'active']);
    }
    self::updateActivity();
    if ($session['status'] == 'active') {
      Logs::debug(__METHOD__ . ' user session status is active ' . session_id());
      returnJson(['status' => 'active']);
    }
    $session['status'] = self::_getStatus();
    Logs::debug(__METHOD__ . " status is {$session['status']} " . session_id());
    setSessionVar('WR', $session, 'WR');
    if ($session['status'] == 'active') {
      returnJson(['status' => 'active']);
    }
    returnJson(['rank' => self::getRank(), 'status' => 'waiting', 'message' => nl2br(self::getMessage())]);
  }

  protected static function _getStatus() {
    if (self::useRedis()) {
      return Redis::client()->hget('wr:' . session_id(), 'status');
    }
    return getDB()->fetchOne('select status from WAITINGROOM where KOID=?', ['WAITINGROOM:' . session_id()]);
  }

  protected static function getRank() {
    if (self::useRedis()) {
      $rank = 1 + Redis::client()->zrank('wr_waiting', session_id());
    } else {
      $rank = getDB()->fetchOne('select count(*) from WAITINGROOM where status="waiting" and CREAD<=?',
        [getSessionVar('WR', 'WR')['cread']]);
    }
    Logs::debug(__METHOD__ . " rank is $rank " . session_id());
    return $rank;
  }

  public static function getMessage() {
    return self::getConf('message');
  }

  public static function setMessage($message) {
    self::setConf('message', $message);
  }

  public static function setActive($status) {
    self::setConf('active', $status);
  }

  protected static function showWaitingRoom() {
    if (file_exists($GLOBALS['USER_TEMPLATES_DIR'] . 'waitingRoom.html')) {
      $content = file_get_contents($GLOBALS['USER_TEMPLATES_DIR'] . 'waitingRoom.html');
    } else {
      $content = file_get_contents(__DIR__ . '/public/templates/waitingRoom.html');
    }
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    echo preg_replace_callback('/<%(\$tzr\.)?(.*)%>/', function($matches) {
      switch ($matches[2]) {
        case 'custom_css':
          if (file_exists(TZR_WWW_DIR . 'css/waiting-room.css')) {
            return '<link href="/css/waiting-room.css" rel="stylesheet">';
          }
          break;
        case 'statusUrl':
          return TZR_SHARE_SCRIPTS . 'waiting-status.php';
        default:
          return Ini::get($matches[2]);
      }
    }, $content);
    die;
  }

  public static function empty($which = null) {
    return self::length($which) == 0;
  }

  public static function length($which = null) {
    if ($which) {
      return self::lengths()[$which] ?? 0;
    }
    return array_sum(self::lengths());
  }

  public static function lengths() {
    if (!isset(self::$lengths)) {
      if (self::useRedis()) {
        self::$lengths = [
          'waiting' => Redis::client()->zcount('wr_waiting', '-inf', '+inf'),
          'active' => Redis::client()->zcount('wr_active', '-inf', '+inf')
        ];
      } else {
        self::$lengths = getDB()->select(
            'select status, count(*) from WAITINGROOM group by status')->fetchAll(PDO::FETCH_KEY_PAIR);
      }
    }
    return self::$lengths;
  }

  protected static function loadLimit() {
    return Ini::get('load_limit') ?? self::nproc() + 1;
  }

  protected static function loadAvg() {
    if (self::$loadAvg == null) {
      self::$loadAvg = System::uptime()['procs.r'];
    }
    return self::$loadAvg;
  }

  protected static function nproc() {
    if (Ini::get('nproc')) {
      return Ini::get('nproc');
    }
    return self::setNProc();
  }

  public static function setNProc() {
    $iniValue = Ini::get('nproc');
    $nproc = getCPUCores();
    if ($iniValue != $nproc) {
      (new Ini())->addVariable(['section' => 'WaitingRoom', 'variable' => 'nproc', 'value' => $nproc]);
    }
    return $nproc;
  }

  public static function getConf($key = null) {
    if (self::$_conf === null || self::$confReadAt < (time() - 1)) {
      self::$_conf = json_decode(file_get_contents(TZR_VAR2_DIR . 'waitingRoom.json')) ?? (object) [];
      self::$confReadAt = time();
    }
    if ($key) {
      return self::$_conf->$key;
    }
    return self::$_conf;
  }

  protected static function setConf($key, $value) {
    if (self::$_conf === null) {
      self::getConf();
    }
    self::$_conf->$key = $value;
    if (!file_put_contents(TZR_VAR2_DIR . 'waitingRoom.json', json_encode(self::$_conf))) {
      Logs::critical(__METHOD__ . " error writing file " . TZR_VAR2_DIR . 'waitingRoom.json ');
    }
  }

  // verification de la queue (fastdaemon) libération sessions
  public static function checkQueue() {
    $lock = Lock::getLock(__METHOD__);
    if (!$lock) {
      Logs::debug(__METHOD__ . ' unable to get lock');
      return;
    }
    $deleted = self::purge();
    Logs::debug(__METHOD__ . " $deleted sesions deleted");
    if (self::empty('waiting')) {
      Logs::debug(__METHOD__ . " no sessions waiting");
      Lock::releaseLock($lock);
      return;
    }
    $loadAvg = self::loadAvg();
    $limit = self::loadLimit();
    $waiting = self::length('waiting');
    if ($loadAvg > $limit) {
      Logs::debug(__METHOD__ . " load average exceeds limit $loadAvg > $limit");
      $lastRun = self::getConf('lastrun');
      self::$accelFactor = $lastRun->accelFactor ?? 1;
      if (self::$accelFactor > 1) {
        self::$accelFactor = max(1, round(self::$accelFactor / Ini::getWithDefault('wr_accel_coef', 1.5), 2));
      }
    } else {
      Logs::debug(__METHOD__ . " load average under limit $loadAvg < $limit");
      $toRelease = self::toRelease();
      $released = self::release($toRelease);
      Logs::debug(__METHOD__ . " waiting: $waiting, to release: $toRelease, released: $released");
      if ($toRelease > $released && self::getConf('active')) {
        Logs::critical(__METHOD__ . ' deactivate WaitingRoom ');
        self::setConf('active', false);
      }
    }
    $active = self::lastMinuteActiveSession();
    $run = [
      'loadAvg' => $loadAvg,
      'active' => $active,
      'released' => $released ?? 0,
      'date' => date('Y-m-d H:i:s'),
      'accelFactor' => self::$accelFactor,
    ];
    Logs::debug(__METHOD__ . " run infos " . json_encode($run));
    self::setConf('lastrun', $run);
    Lock::releaseLock($lock);
  }

  // purge les sessions expirées
  protected static function purge() {
    if (!self::useRedis()) {
      return getDB()->execute(
        'delete from WAITINGROOM where TIMESTAMPDIFF(SECOND, UPD, now())>?', [TZR_SESSION_DURATION]);
    }
    $count = 0;
    while ($sessids = Redis::client()->zrange('wr_active', 0, 1)) {
      foreach ($sessids as $sessid) {
        $hashKey = "wr:$sessid";
        $upd = Redis::client()->hget($hashKey, 'UPD');
        if (time() - $upd < TZR_SESSION_DURATION) {
          break 2;
        }
        Redis::client()->zrem('wr_active', $sessid);
        Redis::client()->del($hashKey);
        $count ++;
      }
    }
    return $count;
  }

  // libère $count sessions
  protected static function release($count) {
    if (!$count) {
      return 0;
    }
    if (!self::useRedis()) {
      return getDB()->execute(
        'update WAITINGROOM set status="active" where status="waiting" order by CREAD limit ' . $count);
    }
    $c = 0;
    while ($c < $count && $sessids = Redis::client()->zrange('wr_waiting', 0, 1)) {
      foreach ($sessids as $sessid) {
        $hashKey = "wr:$sessid";
        Redis::client()->transaction()
          ->hset($hashKey, 'UPD', time())
          ->hset($hashKey, 'status', 'active')
          ->expire($hashKey, TZR_SESSION_DURATION)
          ->zrem('wr_waiting', $sessid)
          ->zadd('wr_active', time(), $sessid)
          ->execute();
        $c ++;
      }
    }
    return $c;
  }

  // nombre de sessions actives
  protected static function lastMinuteActiveSession() {
    if (!self::useRedis()) {
      return getDB()->fetchOne(
      'select count(*) from WAITINGROOM where status="active" and TIMESTAMPDIFF(SECOND, UPD, now())<60');
    }
    $count = Redis::client()->zcount('wr_recentActivity', '-inf', '+inf');
    Logs::debug(__METHOD__ . " recentActivity: $count");
    Redis::client()->del('wr_recentActivity');
    return $count;
  }

  // calcul nombre de sessions à libérer
  protected static function toRelease() {
    $toRelease = 4 * self::nproc();
    $lastRun = self::getConf('lastrun');
    self::$accelFactor = $lastRun->accelFactor ?? 1;
    // si on a libéré significativement au dernier passage et que la charge baisse, on augmente le facteur d'accélération
    if ($lastRun->released > 20 && time() - strtotime($lastRun->date) < 90 && self::loadAvg() < $lastRun->loadAvg) {
      self::$accelFactor = round(self::$accelFactor * Ini::getWithDefault('wr_accel_coef', 1.5), 2);
    } elseif (self::loadAvg() < self::loadLimit() / 2) {
      self::$accelFactor = round(self::$accelFactor * Ini::getWithDefault('wr_accel_coef', 1.5), 2);
    }
    Logs::debug(__METHOD__ . " accelFactor: " . self::$accelFactor);
    return ceil($toRelease * self::$accelFactor);
  }

}
