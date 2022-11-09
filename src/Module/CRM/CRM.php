<?php

/**
 * Description of CRM
 * Centralisation des contacts
 * Niveau des contacts:
 *  - Marketing : inscrit Newsletter
 *  - Commercial : contact ayant accepté des cgv indiquant l'utilisation possible de leur mail
 *  - Technic: client actuellement sous contrat
 */

namespace Seolan\Module\CRM;

use Seolan\Core\DbIni;
use Seolan\Core\Logs;
use Seolan\Core\Module\Module;
use Seolan\Core\Param;
use Seolan\Core\Shell;
use Seolan\Library\Lock;
use Seolan\Library\ProcessCache;

class CRM extends \Seolan\Module\Table\Table {

  const LASTRUN = 'CRM_lastRun';
  const LOCK = 'CRM_lock';
  public static $singleton = true;
  public $sources;
  private $seenOids = [];

  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Modules sources', 'sources', 'module', ['multivalued' => true, 'filterclass' => 'Seolan\Module\CRM\CRMSourceInterface'], '', 'CRM');
    $this->_options->setComment('Modules implémententants CRMSourceInterface', 'sources');
  }

  public function secGroups($function, $group = NULL) {
    $g = [];
    $g['update'] = ['rw', 'rwv', 'admin'];
    $g['resynchronize'] = ['rw', 'rwv', 'admin'];
    $g['unsubscribe'] = ['none', 'ro', 'rw', 'rwv', 'admin'];
    $g['procUnsubscribe'] = ['none', 'ro', 'rw', 'rwv', 'admin'];
    if (isset($g[$function])) {
      if (!empty($group))
        return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function, $group);
  }

  protected function _actionlist(&$my, $alfunction = true) {
    parent::_actionlist($my, $alfunction);
    $o1 = new \Seolan\Core\Module\Action($this, 'resynchronize', 'Resynchoniser', '&moid=' . $this->_moid . '&_function=resynchronize');
    $o1->menuable = true;
    $my['resynchronize'] = $o1;
  }

  public function browse_actions(&$r, $assubmodule = false, $ar = null) {
    parent::browse_actions($r, $assubmodule, $ar);
    $self = $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . '&moid=' . $this->_moid;
    foreach ($r['lines_oemail'] as $i => $email) {
      $r['actions'][$i][] = '<a class="cv8-ajaxlink cv8-dispaction" href="' . $self . '&function=update&email=' . $email->raw . '" title="Mettre à jour"><span class="glyphicon csico-restore"></span></a>';
    }
  }

  public function update($ar) {
    $p = new Param($ar);
    $email = $p->get('email');
    $this->updateContact($email);
    Shell::setNext(Shell::get_back_url());
  }

  public function resynchronize($ar) {
    Shell::setNext(Shell::get_back_url());
    $lock = Lock::getLock(self::LOCK);
    if (false == $lock) {
      Shell::alert('Consolidation en cours');
      return;
    }
    Lock::releaseLock($lock);
    DbIni::clearStatic(self::LASTRUN);
    Shell::alert('Synchronisation programmée', 'success');
  }

  protected function _daemon($period = 'any') {
    parent::_daemon($period);
    $this->consolidate();
  }

  protected function consolidate() {
    $lock = Lock::getLock(self::LOCK);
    if (false == $lock) {
      Logs::notice(__METHOD__ . ' unable to get lock');
      return;
    }
    try {
      $this->updateDict();
      $this->updateContacts();
    } catch (\exception $e) {
      Logs::notice(__METHOD__ . ' ' . $e->getMessage());
    }
    Lock::releaseLock($lock);
  }

  // @return \Seolan\Module\CRMCRMSourceInterface[]
  private function getSources() {
    $sources = [];
    foreach ($this->sources as $moid) {
      $mod = Module::objectFactory($moid);
      if ($mod instanceof \Seolan\Module\CRM\CRMSourceInterface) {
        $sources[] = Module::objectFactory($moid);
      } else {
        Logs::notice("module $moid is not a CRMSourceInterface");
      }
    }
    return $sources;
  }

  protected function updateDict() {
    $changes = false;
    foreach ($this->getSources() as $source) {
      foreach ($source->getCRMFields() as $name => $field) {
        if (empty($name) || is_numeric($name)) {
          Logs::notice(__METHOD__ . " incorrect field '$name', module $source->getLabel()");
          Shell::alert("incorrect field '$name', module {$source->getLabel()}");
          continue;
        }
        if ($this->xset->desc[$name . '_' . $source->_moid]) {
          continue;
        }
        $options = $field->DPARAM ?? [];
        $options['fgroup'] = '~ ' . $source->getLabel();
        $options['readonly'] = 1;
        unset($options['dependency']);
        $class = get_class($field);
        if (substr($class, 0, 1) != '\\') {
          $class = '\\' . $class;
        }
        $this->xset->createField($name . '_' . $source->_moid, $field->label ?: $name, $class, $field->fcount, '', 0, 0, 0, 0, 0, 0, $field->target, $options);
        $changes = true;
      }
    }
    // si changement on relit tout
    if ($changes) {
      DbIni::clearStatic(self::LASTRUN);
    }
  }

  protected function updateContacts() {
    $lastRun = DbIni::getStatic(self::LASTRUN, 'raw', TZR_DATETIME_EMPTY);
    $start = date('Y-m-d H:i:s');
    $emails = [];
    foreach ($this->getSources() as $source) {
      $emails = array_merge($emails, $source->getCRMEmails($lastRun) ?? []);
    }
    foreach (array_unique($emails) as $email) {
      $this->updateContact($email);
    }
    if ($lastRun == TZR_DATETIME_EMPTY) {
      getDB()->execute('update CRMCONTACTS set Archive=1 where koid not in ("' . implode('","', $this->seenOids) . '")');
    }
    DbIni::setStatic(self::LASTRUN, $start);
  }

  private function updateContact($email) {
    $contact = ['email' => $email, 'Marketing' => false, 'Commercial' => false, 'Technic' => false,
      'Sources' => [], 'Tags' => [], '_options' => ['local' => true], '_nolog' => true];
    foreach ($this->getSources() as $source) {
      $infos = $source->getCRMContactInfos($email);
      if (!$infos) {
        continue;
      }
      foreach ($source->getCRMFields() as $name => $field) {
        $contact[$name . '_' . $source->_moid] = $infos[$name];
      }
      $contact['Marketing'] |= isset($infos['Marketing']) && $infos['Marketing'];
      $contact['Commercial'] |= isset($infos['Commercial']) && $infos['Commercial'];
      $contact['Technic'] |= isset($infos['Technic']) && $infos['Technic'];
      $contact['Sources'] = array_merge($contact['Sources'], is_array($infos['Sources']) ? $infos['Sources'] : [$infos['Sources']]);
      if (!empty($infos['Tags'])) {
        $contact['Tags'] = array_merge($contact['Tags'], is_array($infos['Tags']) ? $infos['Tags'] : [$infos['Tags']]);
      }
    }
    $contact['Sources'] = array_filter(array_unique($contact['Sources']));
    if ($contact['Tags']) {
      $contact['Tags'] = array_filter(array_unique($contact['Tags']));
      $contact['Tags'] = array_map(function($tag) { if (substr($tag, 0, 1) != '#') $tag = "#$tag"; return $tag; }, $contact['Tags']);
      sort($contact['Tags']);
      $contact['Tags'] = implode(' ', $contact['Tags']) . ' ';
    }
    $exists = getDB()->fetchOne('select KOID from CRMCONTACTS where email=?', [$email]);
    $contact['Archive'] = empty($contact['Sources']) ? 1 : 2;
    if ($exists) {
      $contact['oid'] = $exists;
      $ret = $this->xset->procEdit($contact);
    } else {
      $ret = $this->xset->procInput($contact);
    }
    $this->seenOids[] = $ret['oid'];
  }

  public function _removeRegisteredOid($oid) {
    $select = $this->xset->select_query(['fields' => ['email'], 'cond' => ['Sources' => ['=', $oid]]]);
    $rows = getDB()->fetchAll($select);
    foreach ($rows as $row) {
      $this->updateContact($row['email']);
    }
    return parent::_removeRegisteredOid($oid);
  }

  /**
   * Détermine si $level est autorisé pour $email
   * @param type $email
   * @param type $level
   */
  public static function authorized($email, $level) {
    $contact = self::getContact($email);
    if (!$contact) {
      return false;
    }
    switch ($level) {
      case 'marketing':
        return $contact->Marketing == 1 && $contact->MarketingDeny == 2;
      case 'commercial':
        return $contact->Commercial == 1 && $contact->CommercialDeny == 2;
      case 'technic':
        return $contact->Technic == 1;
    }
  }

  protected static function getContact($email) {
    $contact = ProcessCache::get('crmcontact', $email);
    if ($contact) {
      return $contact;
    }
    $contact = getDB()->select('select * from CRMCONTACTS where email=?', [$email])->fetch(\PDO::FETCH_OBJ);
    ProcessCache::set('crmcontact', $email, $contact);
    return $contact;
  }

  public function unsubscribe($ar) {
    $p = new Param($ar);
    $email = trim($p->get('email'));
    $json = $p->get('json');
    $contact = self::getContact($email);
    if (empty($contact)) {
      if ($json) {
        returnJson(['unknowcontact' => 1]);
      }
      return Shell::setNextData('crm_unknowcontact', 1);
    }
    $edit = ['oid' => $contact->KOID, '_options' => ['local' => true]];
    if ($p->get('marketing')) {
      $edit['MarketingDeny'] = 1;
      foreach ($this->getSources() as $source) {
        if ($source instanceof \Seolan\Module\MailingList\MailingList) {
          $source->unsubscribe([$source->key => $email]);
        }
      }
    }
    if ($p->get('commercial')) {
      $edit['CommercialDeny'] = 1;
    }
    $this->xset->procEdit($edit);
    if ($json) {
      returnJson(['prefsaved' => 1]);
    }
    Shell::setNextData('crm_prefsaved', 1);
  }

}
