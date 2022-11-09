<?php

namespace Seolan\Module\RGPD;

use Seolan\Core\DataSource\DataSource;
use Seolan\Core\Labels;
use Seolan\Core\Module\Action;
use Seolan\Core\Param;
use Seolan\Core\Shell;

class RGPD extends \Seolan\Module\Table\Table {

  public static $singleton = true;

  /// initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('trackchanges');
    $this->_options->delOpt('trackaccess');
    $this->_options->delOpt('archive');
    $this->trackchanges = $this->trackaccess = $this->archive = false;
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group = NULL) {
    $g = [];
    $g['fullCheck'] = ['ro', 'rw', 'rwv', 'admin'];
    $g['editModules'] = ['rw', 'rwv', 'admin'];
    $g['procEditModules'] = ['rw', 'rwv', 'admin'];
    $g['editFields'] = ['rw', 'rwv', 'admin'];
    $g['procEditFields'] = ['rw', 'rwv', 'admin'];
    if (isset($g[$function])) {
      if (!empty($group))
        return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function, $group);
  }

  /// cette fonction est appliquee pour afficher l'ensemble des methodes de ce module
  protected function _actionlist(&$my, $alfunction = true) {
    parent::_actionlist($my, $alfunction);
    $moid = $this->_moid;
    if ($this->secure('', 'fullCheck')) {
      $o1 = new Action($this, 'fullCheck', Labels::getSysLabel('Seolan_Module_RGPD_RGPD', 'check', 'text'),
        '&moid=' . $moid . '&_function=fullCheck&template=Core.message.html&tplentry=br');
      $o1->menuable = true;
      $my['fullCheck'] = $o1;
    }
    if ($this->secure('', 'editModules')) {
      $o1 = new Action($this, null, 'Modules', '&moid=' . $moid . '&_function=editModules');
      $o1->menuable = true;
      $my['editModules'] = $o1;
    }
    if ($this->secure('', 'editFields')) {
      $o1 = new Action($this, null, Labels::getSysLabel('Seolan_Core_General', 'fields', 'text'), '&moid=' . $moid . '&_function=editFields');
      $o1->menuable = true;
      $my['editFields'] = $o1;
    }
  }

  /// recherche des modules qui contiennent des identités ou des données personnelles et liste des champs avec des données personnelles
  public function checkPersonalData(&$report) {
    $list = self::modlist(['tplentry' => TZR_RETURN_DATA, 'withmodules' => true]);
    $report[] = 'Vérification de la présence de données personnelles';
    foreach ($list['lines_mod'] as $mod => $module) {
      $module->RGPDCheck($report);
    }
  }

  /// check
  public function fullCheck($ar = NULL) {
    $report = [];

    // verification qu'on est sur du https
    if (substr($GLOBALS['HOME_ROOT_URL'], 0, 5) != 'https')
      $report[] = ':( protocole http non sécurisé';
    else
      $report[] = ':) protocole https sécurisé';

    $this->checkPersonalData($report);

    $this->xset->procInput(['title' => ' Check RGPD ',
      'report' => implode("<br/>\n", $report)]);
  }

  static function getRetentionFromDataType($datatype) {
    switch ($datatype) {
      case 'legal':
        return 10 * 365.25;  /* 10 ans */
      case 'commercial':
        return 3 * 365.25;  /* 3 ans */
      case 'other':
        return NULL;
    }
  }

  public function editModules($ar) {
    $modlist = self::modlist(['tplentry' => TZR_RETURN_DATA, 'withmodules' => true]);
    $list = [];
    foreach ($modlist['lines_oid'] as $i => $moid) {
      $mod = $modlist['lines_mod'][$i];
      if (empty($mod->usedMainTables())) {
        continue;
      }
      $mod->rgpdOpts = $mod->_options->getDialog($mod, ['groups' => [Labels::getTextSysLabel('Seolan_Core_RGPD', 'RGPD')]],
        'options[' . $moid . ']');
      $list[$modlist['lines_group'][$i]][] = $mod;
    }
    Shell::toScreen2('br', 'mods', $list);
    Shell::changeTemplate('Module/RGPD.edit-modules.html');
  }

  public function procEditModules($ar) {
    $p = new Param($ar);
    $options = $p->get('options');
    foreach ($options as $moid => $opts) {
      $changes = false;
      $mod = self::objectFactory($moid);
      foreach ($opts as $field => $value) {
        $changes |= $mod->$field != $value;
        $mod->$field = $value;
      }
      if ($changes) {
        $mod->procEditProperties(['quick' => true]);
      }
    }
    Shell::setNext(Shell::get_back_url());
  }

  public function editFields($ar) {
    $modlist = self::modlist(['tplentry' => TZR_RETURN_DATA, 'withmodules' => true]);
    $list = [];
    foreach ($modlist['lines_oid'] as $i => $moid) {
      $mod = $modlist['lines_mod'][$i];
      if (!$mod->RGPD_personalData) {
        continue;
      }
      foreach ($mod->usedTables() as $table) {
        if (!isset($list[$table])) {
          $ds = DataSource::objectFactoryHelper8($table);
          $list[$table] = ['ds' => $ds, 'mods' => [], 'groups' => []];
          foreach ($ds->orddesc as $field) {
            $ofield = $ds->desc[$field];
            if ($ofield->sys) {
              continue;
            }
            $opts = $ofield->_options->getDialog($ofield, ['groups' => [Labels::getTextSysLabel('Seolan_Core_RGPD', 'RGPD')]],
              'options[' . $table . '][' . $field . ']');
            $list[$table]['groups'][$ofield->fgroup ?: 'Général'][$ofield->label] = $opts[0];
          }
        }
        $list[$table]['mods'][] = $mod;
      }
    }
    usort($list, function ($a, $b) { return $a['ds']->getLabel() > $b['ds']->getLabel(); });
    Shell::toScreen2('br', 'tables', $list);
    Shell::changeTemplate('Module/RGPD.edit-fields.html');
  }

  public function procEditFields($ar) {
    $p = new Param($ar);
    $options = $p->get('options');
    foreach ($options as $table => $opts) {
      $ds = DataSource::objectFactoryHelper8($table);
      foreach ($opts as $field => $value) {
        if ($ds->desc[$field]->RGPD_personalData != $value['RGPD_personalData']) {
          $ds->procEditField(['field' => $field, 'options' => $value]);
        }
      }
    }
    Shell::setNext(Shell::get_back_url());
  }

}
