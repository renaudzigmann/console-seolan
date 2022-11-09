<?php

namespace Seolan\Module\WaitingRoom;

/**
 * WaitingRoom, Module de gestion
 */

use Seolan\Core\Ini;
use Seolan\Core\Kernel;
use Seolan\Core\Labels;
use Seolan\Core\Param;
use Seolan\Core\Shell;
use Seolan\Core\Module\Action;
use Seolan\Library\Redis;

class WaitingRoom extends \Seolan\Module\Table\Table {

  public static $singleton = true;

  public $active;
  public $activeBO;
  public $loadLimit;
  public $queueClass;
  public $retroDelay;
  public $logo;
  public $useRedis;
  public $accelCoef;

  protected static $iniVars = [
      'active' => 'wr_active', 'activeBO' => 'wr_activeBO', 'loadLimit' => 'load_limit',
      'queueClass' => 'wr_queue_class', 'retroDelay' => 'wr_retro_delay', 'logo' => 'wr_logo',
      'useRedis' => 'wr_use_redis', 'accelCoef' => 'wr_accel_coef'];

  public function initOptions() {
    parent::initOptions();
    $group = Labels::getSysLabel('Seolan_Module_WaitingRoom_WaitingRoom', 'modulename');
    $this->_options->setOpt('Activer', 'active', 'boolean', [], 1, $group);
    $this->_options->setOpt('Activer en Back-Office', 'activeBO', 'boolean', [], 0, $group);
    $this->_options->setOpt('Charge limite', 'loadLimit', 'text', [], '', $group);
    $this->_options->setComment('Facultatif, si non renseigné, nproc + 1', 'loadLimit');
    $this->_options->setOpt('Classe de surcharge de WaitingRoom\Queue', 'queueClass', 'text', [], '', $group);
    $this->_options->setOpt('Délai de rattrapage (sec)', 'retroDelay', 'text', [], '300', $group);
    $this->_options->setOpt('Logo (tag html)', 'logo', 'text', ['rows' => 3, 'cols' => 80], '', $group);
    $this->_options->setOpt('Utiliser Redis', 'useRedis', 'boolean', [], 0, $group);
    $this->_options->setOpt('Coefficient d\'accélération', 'accelCoef', 'text', [], '1.5', $group);
  }

  public function editProperties($ar) {
    foreach (self::$iniVars as $prop => $var) {
      $this->$prop = Ini::get($var);
    }
    return parent::editProperties($ar);
  }

  public function procEditProperties($ar) {
    parent::procEditProperties($ar);
    $ini = new Ini();
    foreach (self::$iniVars as $prop => $var) {
      if (Ini::get($var) != $this->$prop) {
        if (empty($this->$prop)) {
          $ini->delVariable(['variable' => $var]);
        } else {
          $ini->addVariable(['section' => 'WaitingRoom', 'variable' => $var, 'value' => $this->$prop]);
        }
      }
    }
  }

  public function secGroups($function, $group = NULL) {
    $g = [
      'activate' => ['rwv', 'admin'],
      'deactivate' => ['rwv', 'admin'],
      'readRedis' => ['rwv', 'admin'],
      'setStatus' => ['rwv', 'admin'],
      'setMessage' => ['rw', 'rwv', 'admin'],
      'procSetMessage' => ['rw', 'rwv', 'admin'],
    ];
    if (isset($g[$function])) {
      if (!empty($group))
        return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function, $group);
  }


  protected function _actionlist(&$my, $alfunction = true) {
    parent::_actionlist($my, $alfunction);
    if (Ini::get('wr_use_redis') && $this->secure('', 'readRedis')) {
      $o1 = new Action($this, 'readRedis', Labels::getSysLabel('Seolan_Module_WaitingRoom_WaitingRoom', 'readredis'),
        $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . 'moid=' . $this->_moid . '&function=readRedis');
      $o1->menuable = true;
      $my['readRedis'] = $o1;
    }
    if (in_array(Shell::_function(), ['browse', 'procQuery'])) {
      if ($this->secure('', 'setStatus')) {
        $o1 = new Action($this, 'release', Labels::getSysLabel('Seolan_Module_WaitingRoom_WaitingRoom', 'release'),
          'javascript:TZR.applyFunction("browse' . Shell::uniqid() . '", {_function:"setStatus", status: "active", fromfunction:"' . Shell::_function() . '"}, true, false, false);');
        $o1->menuable = true;
        $my['release'] = $o1;
        $o1 = new Action($this, 'confine', Labels::getSysLabel('Seolan_Module_WaitingRoom_WaitingRoom', 'confine'),
          'javascript:TZR.applyFunction("browse' . Shell::uniqid() . '", {_function:"setStatus", status: "waiting", fromfunction:"' . Shell::_function() . '"}, true, false, false);');
        $o1->menuable = true;
        $my['confine'] = $o1;
      }
      Shell::alert((Queue::active() ? 'Active, ' : 'Non active, ')
        . Queue::length('active') . ' session(s) active(s), ' . Queue::length('waiting') . ' session(s) en attente', 'info');
    }
    if ($this->secure('', 'setMessage')) {
      $o1 = new Action($this, 'setMessage', Labels::getSysLabel('Seolan_Module_WaitingRoom_WaitingRoom', 'setmessage'),
        $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . 'moid=' . $this->_moid . '&function=setMessage');
      $o1->menuable = true;
      $my['setMessage'] = $o1;
    }
    if ($this->secure('', 'activate')) {
      if (Queue::active()){
        $o1 = new Action($this, 'deactivate', Labels::getSysLabel('Seolan_Module_WaitingRoom_WaitingRoom', 'deactivate'),
          $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . 'moid=' . $this->_moid . '&function=deactivate');
        $o1->menuable = true;
        $my['deactivate'] = $o1;
      } else {
        $o1 = new Action($this, 'activate', Labels::getSysLabel('Seolan_Module_WaitingRoom_WaitingRoom', 'activate'),
          $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . 'moid=' . $this->_moid . '&function=activate');
        $o1->menuable = true;
        $my['activate'] = $o1;
      }
    }
  }

  public function browse_actions(&$r, $assubmodule = false, $ar = null) {
    $url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . '&moid=' . $this->_moid;
    foreach ($r['lines_oid'] as $i => $oid) {
      if ($this->secure('', 'setStatus') && $r['lines_ostatus'][$i]->raw == 'waiting') {
        $r['actions'][$i][] = '<a href="' . $url . '&function=setStatus&status=active&oid=' . $oid
          . '" class="cv8-ajaxlink" title="' . Labels::getSysLabel('Seolan_Module_WaitingRoom_WaitingRoom', 'release') . '"><span class="glyphicon csico-lightbulb"></span></a>';
      }
      if ($this->secure('', 'confine') && $r['lines_ostatus'][$i]->raw == 'active') {
        $r['actions'][$i][] = '<a href="' . $url . '&function=setStatus&status=waiting&oid=' . $oid
          . '" class="cv8-ajaxlink" title="' . Labels::getSysLabel('Seolan_Module_WaitingRoom_WaitingRoom', 'confine') . '"><span class="glyphicon csico-lightbulb-outline"></span></a>';
      }
    }
    parent::browse_actions($r, $assubmodule, $ar);
  }

  public function getInfos($ar = NULL) {
    $ret = [];
    $ret['infos']['enable'] = (object) ['label' => 'Active', 'html' => Ini::get('wr_active')];
    $ret['infos']['enable'] = (object) ['label' => 'Active en BO', 'html' => Ini::get('wr_activeBO')];
    $ret['infos']['nproc'] = (object) ['label' => 'Nb de processeurs', 'html' => Ini::get('nproc')];
    $ret['infos']['active'] = (object) ['label' => 'En cours de fonctionnement', 'html' => Queue::getConf('active')];
    $ret['infos']['sactive'] = (object) ['label' => 'Sessions active', 'html' => Queue::length('active')];
    $ret['infos']['swaiting'] = (object) ['label' => 'Sessions en attente', 'html' => Queue::length('waiting')];
    return \Seolan\Core\Shell::toScreen1('br', $ret);
  }

  public function setStatus($ar) {
    $p = new Param($ar);
    $oids = Kernel::getSelectedOids($p);
    $status = $p->get('status');
    $count = getDB()->execute('update WAITINGROOM set status=? where koid in ("' . implode('","', $oids) . '")', [$status]);
    if (Queue::useRedis()) {
      $from = $status == 'waiting' ? 'active' : 'waiting';
      foreach ($oids as $oid) {
        $sessid = explode(':', $oid)[1];
        if (Redis::client()->hget("wr:$sessid", 'status') == $status) {
          continue;
        }
        Redis::client()->transaction()
          ->zrem("wr_$from", $sessid)
          ->hset("wr:$sessid", 'UPD', time())
          ->hset("wr:$sessid", 'status', $status)
          ->zadd("wr_$status", time(), $sessid)
          ->execute();
      }
    }
    Shell::alert("$count session(s) modifiée(s)", 'info');
    Shell::setNext(Shell::get_back_url());
  }

  public function activate($ar = null) {
    Queue::setActive(true);
    Shell::setNext(Shell::get_back_url());
  }

  public function deactivate($ar = null) {
    Queue::setActive(false);
    Shell::setNext(Shell::get_back_url());
  }

  public function readRedis($ar = null) {
    getDB()->execute('truncate WAITINGROOM');
    foreach (['wr_active', 'wr_waiting'] as $queue) {
      foreach (Redis::client()->zrange($queue, 0, -1) as $sessid) {
        $hash = Redis::client()->hgetall("wr:$sessid");
        getDB()->execute(
          'insert ignore into WAITINGROOM (KOID, CREAD, UPD, sessid, status, lasturl) '
          . 'values (?, ?, FROM_UNIXTIME(?), ?, ?, ?)',
          ["WAITINGROOM:$sessid", $hash['CREAD'], $hash['UPD'], $sessid, $hash['status'], $hash['lasturl']]);
      }
    }
    if ($this->interactive) {
      Shell::setNext(Shell::get_back_url());
    }
  }

  public function del($ar) {
    $p = new Param($ar);
    $oid = Kernel::getSelectedOids($p, true, false);
    if (is_array($oid)) {
      return parent::del($ar);
    }
    if (Queue::useRedis()) {
      $sessid = explode(':', $oid)[1];
      Redis::client()->transaction()
        ->zrem("wr_active", $sessid)
        ->zrem("wr_waiting", $sessid)
        ->del("wr:$sessid")
        ->execute();
    }
    return parent::del($ar);
  }

  public function setMessage($ar) {
    $message = new \Seolan\Field\Text\Text((object) ['FIELD' => 'wrmessage', 'LABEL' => 'Texte à afficher sur la page d\'attente', 'FCOUNT' => 80]);
    Shell::toScreen1('br', $i = ['fields_object' => [$message->edit(Queue::getMessage())]]);
    Shell::toScreen1('_', $a = ['function' => 'procSetMessage']);
    Shell::changeTemplate('Module/WaitingRoom.new.html');
  }

  public function procSetMessage($ar) {
    $p = new Param($ar);
    $message = $p->get('wrmessage');
    Queue::setMessage($message);
    Shell::setNext(Shell::get_back_url());
  }

  protected function _daemon() {
    Queue::setNProc();
    if (Queue::useRedis()) {
      $this->readRedis();
    }
  }

  protected function _fastDaemon() {
    Queue::checkQueue();
  }

}
