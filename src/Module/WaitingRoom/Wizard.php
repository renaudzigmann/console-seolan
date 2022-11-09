<?php

namespace Seolan\Module\WaitingRoom;

class Wizard extends \Seolan\Core\Module\Wizard {

  function istep1($ar = null) {
    $this->_module->group = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'systemproperties');
    $this->_module->modulename = \Seolan\Core\Labels::getSysLabel('Seolan_Module_WaitingRoom_WaitingRoom', 'modulename');
    parent::istep1($ar);
  }

  function iend($ar = NULL) {
    if (!\Seolan\Core\System::tableExists('WAITINGROOM')) {
      \Seolan\Model\DataSource\Table\Table::procNewSource([
        'translatable' => 0,
        'btab' => 'WAITINGROOM',
        'bname' => [TZR_DEFAULT_LANG => 'WAITINGROOM'],
        'publish' => false,
        'own' => false,
        'cread' => true,
      ]);
      getDB()->execute('alter table WAITINGROOM modify KOID varchar(60)');
      getDB()->execute('alter table WAITINGROOM modify LANG char(2) default ?', [TZR_DEFAULT_LANG]);
      getDB()->execute('update DICT set BROWSABLE=1 where DTAB="WAITINGROOM" and FIELD in ("CREAD", "UPD")');
    }
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('WAITINGROOM');
    $ds->createField('sessid', 'Identifiant', '\Seolan\Field\ShortText\ShortText', 40, '', 1, 1, 1, 0, 0, 1, '', []);
    $ds->createField('status', 'Statut', '\Seolan\Field\ShortText\ShortText', 10, '', 1, 1, 1, 0, 0, 0, '', []);
    $ds->createField('lasturl', 'Dernière page', '\Seolan\Field\ShortText\ShortText', 120, '', 0, 1, 1, 0, 0, 0, '', []);
    if (!getDB()->fetchRow('show index from WAITINGROOM where Column_name="status"')) {
      getDB()->execute('alter table WAITINGROOM add key (status, CREAD)');
    }
    $this->_module->table = 'WAITINGROOM';
    $crontab = shell_exec('crontab -l');
    if (!strpos($crontab, 'fastdaemon')) {
      $crontab .= "\n* * * * * nice -n 19 ".PHP_SEOLAN." ~/csx/scripts/cli/fastdaemon.php -C ~/../tzr/local.php > /dev/null\n";
      $tempFile = TZR_TMP_DIR . 'crontab';
      file_put_contents($tempFile, $crontab);
      system("crontab $tempFile");
    }
    \Seolan\Core\Shell::alert(nl2br("Vérifier que la crontab contient :\n"
        . "* * * * * nice -n 19 ".PHP_SEOLAN." ~/csx/scripts/cli/fastdaemon.php -C ~/../tzr/local.php > /dev/null"),
      'info');
    $ini = new \Seolan\Core\Ini();
    $ini->addVariable(['section' => 'WaitingRoom', 'variable' => 'wr_active', 'value' => 1]);
    $ini->addVariable(['section' => 'WaitingRoom', 'variable' => 'wr_activeBO', 'value' => 0]);
    $ini->addVariable(['section' => 'WaitingRoom', 'variable' => 'load_limit', 'value' => getCPUCores()]);
    $ini->addVariable(['section' => 'WaitingRoom', 'variable' => 'wr_retro_delay', 'value' => 60]);
    $ini->addVariable(['section' => 'WaitingRoom', 'variable' => 'nproc', 'value' => getCPUCores()]);
    $ini->addVariable(['section' => 'WaitingRoom', 'variable' => 'wr_accel_coef', 'value' => 1.5]);
    return parent::iend();
  }

}
