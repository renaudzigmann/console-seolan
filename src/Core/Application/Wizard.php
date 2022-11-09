<?php

namespace Seolan\Core\Application;
use Seolan\Core\DataSource\DataSource;
use Seolan\Core\ISec;
use Seolan\Core\Labels;
use Seolan\Core\Module\Module;
use Seolan\Core\Param;
use Seolan\Core\Shell;
use Seolan\Model\DataSource\Table\Table;
use Seolan\Module\InfoTree\InfoTree;

/**
 * Class XAppWd
 * Wizard des Apps.
 *
 * * Gére les écrans de configuration du site
 * * S'assure de l'existence de la table de configuration de l'app
 * * Gestion du groupe d'administration de l'app
 *
 * @author: Julien Maurel, puis repris pas Bastien Sevajol.
 */
abstract class Wizard implements ISec {

  /**
   * Identifie le numéro de l'étape en cours.
   * @author Julien Maurel
   * @var array|null|void
   */
  public $_step;

  /**
   * Contient différentes saisies, organisés par step.
   * @author Julien Maurel
   * @var array
   */
  public $_app = array();

  /**
   * Contient la configuration qui sera stockée dans le champ params
   * @author Julien Maurel
   * @var array
   */
  public $_conf = array();

  /**
   *
   * @param null $params
   * @author Julien Maurel
   */
  function __construct($params = NULL) {
    $p = new Param(array(), array('step' => 1));
    $this->_step = $p->get('step');
    if($params) {
      foreach($params as $k => $v) {
        $this->_conf[$k] = $v;
      }
    }
    Labels::loadLabels('\Seolan\Core\Application\Wizard');
  }

  function secGroups($function, $group = NULL) {
    $g = array('irun' => array('admin'));
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      else return $g[$function];
    }
    return false;
  }

  function secList() {
    return array('admin');
  }

  // Fonction executee pour le wizard @author Julien Maurel
  function irun($ar = NULL) {
    $p = new Param($ar);
    if(issetSessionVar("appWd") && $this->_step > 1) {
      $this->_app = unserialize(getSessionVar("appWd"));
    }
    if($this->_conf) $mode = 'edit';
    else $mode = 'new';
    $fname = 'i'.$mode.'step'.$this->_step;
    if(method_exists($this, $fname)) {
      $this->istep($ar);
      $fields =& $this->$fname($ar);
      Shell::toScreen2('wd', 'fields', $fields);
      Shell::toScreen2('wd', 'app', $this->_conf['app_class'] ? $this->_conf['app_class'] : $p->get('app'));
      Shell::toScreen2('wd', 'step', $this->_step + 1);
      Shell::toScreen2('wd', 'mode', $mode);
      if($this->_conf) Shell::toScreen2('wd', 'oid', $this->_conf['oid']);
      setSessionvar('appWd', serialize($this->_app));
    }
    else {
      if(!issetSessionVar("appWd")) {
        $this->_step = 1;
        return $this->irun($ar);
      }
      $this->istep();
      $fname = 'i'.$mode.'end';
      $this->$fname();
    }
  }

  // Fonction appelée à chaque étape pour enregistrer la saisie @author Julien Maurel
  function istep($ar = null) {
    if($this->_step < 2) return;
    $step = $this->_step - 1;
    $p = new Param($ar, array('fields' => array()));
    // Enregistre les valeurs postées
    $fields = $p->get('fields');
    foreach($fields as $f) {
      $this->_app['step'.$step][$f] = $p->get($f) ?: '';
      if($p->is_set($f.'_HID')) $this->_app['step'.$step][$f.'_HID'] = $p->get($f.'_HID');
      if($p->is_set($f.'_dup')) $this->_app['step'.$step][$f.'_dup'] = $p->get($f.'_dup');
    }
    if($step == 1) $this->_app['step'.$step]['classname'] = $p->get('app');
  }

  // @author Julien Maurel
  // La step1 est privée car on ne doit récupérer que des champs de la table APP
  // La méthode _istep1 permet de définir quels champs précisemment
  private function inewstep1($ar = NULL) {
    $xds = DataSource::objectFactoryHelper8('SPECS=APP');
    $sfields = $this->_istep1();
    $i = $xds->input(   ['selectedfields' => $sfields,
                        'options'=>['modules'=>['filterCallback'=>function($mod){
                          $classname = get_class($mod);
                          return $classname::$singleton!=true;
                        }]]]);
    $fields = [];
    foreach($sfields as $f) {
      $fields[] = $i['o'.$f];
    }
    return $fields;
  }

  private function ieditstep1($ar = NULL) {
    $xds = DataSource::objectFactoryHelper8('SPECS=APP');
    $sfields = $this->_istep1();
    $i = $xds->edit(['oid'=>$this->_conf['oid'],
      'selectedfields'=>$sfields,
      'options'=>['modules'=>['filterCallback'=>function($mod){
        $classname = get_class($mod);
        return $classname::$singleton!=true;
      }]]]);
    $fields = array();
    foreach($sfields as $f) {
      $fields[] = $i['o'.$f];
    }
    return $fields;
  }

  // @author Julien Maurel
  public function _istep1() {
    return array('name', 'domain', 'domain_is_regex', 'modules', 'groups');
  }

  /**
   * @author Julien Maurel
   */
  function ieditstep2($opts) {
    $edit_config = $this->getStep2EditConfig();
    return $this->getConfigDataSource()->edit($edit_config);
  }

  /**
   * Retourne la configuration de l'edit de l'étape 2.
   * @return array
   */
  protected function getStep2EditConfig() {
    return array(
      'fieldssec' => array(
        'security_group' => 'none'
      )
    );
  }

  // Suppression @author Julien Maurel
  function idel($ar = NULL) {
    $dir = TZR_WWW_DIR."../tzr/config/";
    $file = $dir.rewriteToAscii($this->_conf['domain']).'.json';
    if (file_exists($file)){
      unlink($file);
    }
    clearSessionVar(TZR_SESSION_PREFIX.'modules');
    clearSessionVar(TZR_SESSION_PREFIX.'modmenu');
    setSessionVar('_reloadmods', 1);
    setSessionVar('_reloadmenu', 1);
  }

  static function dupModules($modules, $name) {
    $tables = array();
    $newTables = array();
    $newModules = array();
    foreach($modules as $modkey => $moid) {
      $module = Module::objectFactory($moid);
      $table = preg_replace('/_APP\d+$/', '', $module->table);
      $newtable = Table::newTableNumber($table.'_APP');
      $ret = $module->duplicateModule(array(
        'prefix' => $name,
        'group' => $name,
        'duplicatetopicsdata' => true,
        'duplicatesectionsdata' => true,
        'tables' => array($module->table => array('newtable' => $newtable))
      ));
      $newoid = $ret['moid'];
      $newModules[$modkey] = "$newoid";
      $tables[$modkey] = $module->table;
      $newTables[$modkey] = $newtable;
      $fieldsFrom = '(AOID, AGRP, AFUNCTION, ACLASS, ALANG, AMOID, AKOID, OK, ACOMMENT)';
      $fieldsTo = 'md5(rand()), AGRP, AFUNCTION, ACLASS, ALANG, ?, AKOID, OK, ACOMMENT';
      getDB()->execute("insert into ACL4 $fieldsFrom select $fieldsTo from ACL4 where AMOID=?", array($newoid, $moid));
    }

    // Modification des liens vers objet qui pointent vers un module dupliqué
    foreach($newTables as $modkey => $table) {
      foreach($tables as $j => $oldTable) {
        $newTable = $newTables[$j];
        $linkFields = getDB()->fetchCol('select FIELD from DICT where DTAB=? and TARGET=?', array($table, $oldTable));
        foreach($linkFields as $linkField) {
          getDB()->execute('update DICT set target=? where DTAB=? and FIELD=?', array($newTable, $table, $linkField));
          getDB()->execute("update $table set $linkField = replace($linkField, '$oldTable', '$newTable')");
        }
      }
    }

    // Même chose pour les section dynamiques si on a dupliqué un infotree
    foreach($newModules as $moid) {
      $newModule = Module::objectFactory($moid);
      if($newModule instanceof InfoTree) {
        $dyntable = $newModule->dyntable;
        foreach($modules as $modkey => $oldMoid) {
          $newMoid = $newModules[$modkey];
          $dyndatas = getDB()->fetchAll("select * from $dyntable where module=?", array($oldMoid));
          foreach($dyndatas as $dyndata) {
            $json = json_decode($dyndata['query'], true);
            $json['moid'] = $newMoid;
            $json['_table'] = $newTables[$modkey];
            // On remplace les filtres sur KOID
            foreach($tables as $j => $oldTable) {
              $newTable = $newTables[$j];
              foreach($json as $jsonkey => $jsonval) {
                if(strpos($jsonval, "$oldTable:") !== false) {
                  $json[$jsonkey] = str_replace("$oldTable:", "$newTable:", $jsonval);
                }
              }
            }
            $json = json_encode($json);
            getDB()->execute("update $dyntable set module=?, query=? where KOID=?", array($newMoid, $json, $dyndata['KOID']));
          }
        }
      }
    }

    return $newModules;
  }

  static function dupGroups($groups, $name) {
    $groupMoid = Module::modulesUsingTable('GRP');
    $groupMod = Module::objectFactory(key($groupMoid));
    $newGroups = [];
    foreach($groups as $oid => $on) {
      $groupname = getDB()->fetchOne('select GRP from GRP where KOID=? and LANG=?', array($oid, TZR_DEFAULT_LANG));
      if(!$groupname) continue;
      $groupname = preg_replace('/^[^-]+ - /', '', $groupname);
      $ret = $groupMod->procEditDupWithACL(array(
        'oid' => $oid,
        'GRP' => $name." - ".$groupname
      ));
      $newoid = $ret['oid'];
      $newGroups[$newoid] = "on";
    }

    return $newGroups;
  }

  static function dupCharte($oid) {
    return $oid;
  }

  // Fonctions de fin aurel
  function inewend($ar = NULL) {
    // Duplication des groupes et modules si nécessaire
    $name = $this->_app['step1']['name'];
    $modules = $this->_app['step1']['modules'];
    $groups = $this->_app['step1']['groups'];

    if($this->_app['step1']['modules_dup']=='on') {
      $this->_app['step1']['modules'] = self::dupModules($modules, $name);
    }

    if($this->_app['step1']['groups_dup']=='on') {
      $this->_app['step1']['groups'] = self::dupGroups($groups, $name);
    }

    return $this->iend($ar);
  }

  /// @author Julien Maurel
  function ieditend($ar = NULL) {
    return $this->iend($ar);
  }

  /// @author Julien Maurel
  function iend($r = NULL) {
    $p = new Param($ar);
    $message = $p->get('message');
    $xds = DataSource::objectFactoryHelper8('SPECS=APP');
    if($this->_conf) {
      $this->_app['step1']['oid'] = $this->_conf['oid'];
      $ret = $xds->procEdit($this->_app['step1']);
    }
    else {
      $ret = $xds->procInput($this->_app['step1']);
    }

    // On écrit la config json dans un fichier
    ///@note : domain peut-être vide => fichier ".json"
    $dir = TZR_WWW_DIR."../tzr/config/";
    $file = $dir.rewriteToAscii($this->_app['step1']['domain']).'.json';
    $data = json_encode($this->_app['step1']['params'], JSON_PRETTY_PRINT);
    if(!is_dir($dir)) {
      mkdir($dir);
    }
    file_put_contents($file, $data);

    Shell::toScreen2('wd', 'message', $message.'<br/>Installation end');
    Shell::toScreen2('wd', 'isend', 1);
    clearSessionVar('appWd');
    clearSessionVar(TZR_SESSION_PREFIX.'modules');
    clearSessionVar(TZR_SESSION_PREFIX.'modmenu');
    setSessionVar('_reloadmods', 1);
    setSessionVar('_reloadmenu', 1);
    return $ret;
  }

}
