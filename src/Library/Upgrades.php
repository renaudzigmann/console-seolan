<?php
namespace Seolan\Library;
/**
 * fonctions de traitement des upgrades
 * - traitemet à proprement parler : détecter, appliquer
 * - outils de manipulation utilisables dans les scripts 
 * d'upgrades
 */

use Seolan\Core\DataSource\DataSource;
use Seolan\Core\Ini;
use Seolan\Core\Logs;
use Seolan\Core\Module\Module;
use Seolan\Module\InfoTree\InfoTree;
use Seolan\Module\Application\Application;
class Upgrades {
  
  static function editModuleProperty($moid, $prop_name, $prop_value) {
    $mod = Module::objectFactory($moid);
    $values = [$prop_name => $prop_value];
    $mod->_options->procDialog($mod, $values);
    $json = $mod->_options->toJSON($mod);
    if ($prop_name === 'modulename') {
      getDB()->execute('UPDATE MODULES set MODULE = ?, MPARAM=? where MOID=? ',array($prop_value, $json, $moid));
    } else {
      getDB()->execute('UPDATE MODULES set MPARAM=? where MOID=? ',array($json, $moid));
    }
    self::clearCaches();
    \Seolan\Core\Integrity::chkLogInfo();
    Logs::upgradeLog('Set property '.$prop_name.' for module '.$moid);
  }

  static function getModuleProperty($moid, $prop_name) {
    $mod = Module::objectFactory($moid);
    $allOpts = $mod->_options->getAllValues($mod);
    $options = [];
    foreach ($allOpts as $opt => $value) {
      $cleanValue = null;
      if (empty($value)) {
	$cleanValue = 'null';
      } elseif (filter_var($value, FILTER_VALIDATE_INT) || filter_var($value, FILTER_VALIDATE_FLOAT)) {
	$cleanValue = $value;
      } else {
	$cleanValue = '\''.str_replace("'", "\'",$value).'\'';
      }

      $toEval = '$'.str_replace('[', '[\'', str_replace(']', '\']', $opt)).' = '.$cleanValue.';';
      eval($toEval);
    }

    $return = [];
    foreach ($options as $opt => $value) {
      if (preg_match('/'.str_replace('/', '\\/', $prop_name).'/', $opt)) {
	$return[$opt] = $value;
      }
    }

    if (count($return) === 1) {
      return array_shift($return);
    } elseif (count($return) > 1) {
      return $return;
    }

    return null;
  }

  static function addTable($table_name, $table_libelle, $options = NULL) {
    if (!\Seolan\Core\System::tableExists($table_name)) {
      //Création de la table
      $ar1['translatable']            = $options['translatable'] ?: 0;
      $ar1['auto_translate']          = $options['auto_translate'] ?: 0;
      $ar1['publish']                 = $options['publish'] ?: 0;
      $ar1['own']                     = $options['own'] ?: 0;
      $ar1['tag']                     = $options['tag'] ?: 0;
      $ar1['cread']                   = $options['cread'] ?: 0;
      $ar1['bparam']                  = $options['bparam'] ?: [];
      $ar1['classname']               = $options['classname'] ?: '\Seolan\Model\DataSource\Table\Table';
      $ar1['btab']                    = $table_name;
      if(is_array($table_libelle))
	$ar1['bname'] = $table_libelle;
      else
	$ar1['bname'][TZR_DEFAULT_LANG] = $table_libelle;

      $result = \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);

      //Mise à jour du BOID de la table avec le md5 de la table
      $boid = $result['boid'];
      if ($result['error']) {
	Logs::upgradeLog('[CRITIQUE] '.$result['message']);
	return;
      } else {
	$boid = md5($table_name);
	getDB()->execute('UPDATE `BASEBASE` SET `LOG` = 1, `BOID` = ? WHERE `BOID` = ?', [$boid, $result['boid']]);
	getDB()->execute('UPDATE `AMSG` SET `MOID` = ? WHERE `MOID` = ?', [$boid, $result['boid']]);
      }
    }
  }
  /**
   * champs liens vers la table utilisant le champ 
   */
  private static function getFieldUsageInLinks($table, $fname){
    $res = [];
    $usingFields = \Seolan\Core\DataSource\DataSource::fieldsUsingTable($table); // ! tiens pas compte des srucharges
    foreach($usingFields as $line){
      list($utab, $ufn) = explode(' ', $line);
      $uds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($utab);
      $ufd = $uds->getField($ufn);
      foreach(['display_format', 'display_text_format', 'query', 'filter'] as $pname){
	if (strpos($ufd->$pname, $fname) !== false){
	  $res[] = $line;
	}
      }
    }
    return $res;
  }
  /**
   * renome un champ 
   */
  
  static function renameField($table, $oldname, $newname){
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);
    if (!$ds->fieldExists($oldname) || $ds->fieldExists($newname)){
      throw new \Exception("$oldname must exists, $newname must not exists in  table $table");
    }
    $oldField = $ds->getField($oldname);
    if ($oldField instanceof \Seolan\Field\Label\Label){
      throw new \Exception("method not available for field $oldname type");
    }
    $usingFields = static::getFieldUsageInLinks($table, $oldname);
    if (!empty($usingFields)){
      throw new \Exception("method not available for field $oldname used in link to $table configuration ".implode(',', $usingFields));
    }
    // dict + MSGS + alter table
    $dictValues = getDB()->fetchRow('select * from DICT where DTAB=? and FIELD=?', [$table, $oldname]);
    $fields = array_keys($dictValues);
    $dictValues['FIELD'] = $newname;

    $sql = 'INSERT INTO `DICT` (`'.implode('`,`', $fields).'`) values (:'.implode(',:',$fields).')';

    $args = [];
    foreach($dictValues as $k=>$v){
      $args[":$k"]=$v;
    }

    getDB()->execute($sql, $args);
    
    foreach(getDB()->select('SELECT `MTAB` as `tab`, `FIELD` as `field`, `MLANG` as `lang`, `MTXT` as `texte` FROM `MSGS` WHERE `MTAB`=:tab AND `FIELD`=:field',[':tab'=>$table, ':field'=>$oldname]) as $line){
      $line['field'] = $newname;
      $args = [];
      foreach($line as $k=>$v){
	$args[":$k"]=$v;
      }
      getDB()->execute('REPLACE INTO `MSGS` (`MTAB`,`FIELD`,`MLANG`,`MTXT`) VALUES (:tab,:field,:lang,:texte)',$args);
    }
    $sqltype = $oldField->sqltype();
    if(!in_array(strtolower($sqltype), array('text','longtext'))) {
      getDB()->execute('ALTER TABLE `'.$table.'` ADD `'.$newname.'` '.$sqltype.' DEFAULT '.$oldField->getDefaultValueSqlExpression());
    } else {
      getDB()->execute('ALTER TABLE `'.$table.'` add `'.$newname.'` '.$sqltype);
    }

    // pb des indexs => au prochaine check
   
    
    // on copie les valeurs
    getDB()->execute("UPDATE `$table` SET `$newname`=`$oldname`");

    if ($oldField instanceof \Seolan\Field\StringSet\StringSet){
      getDB()->execute('update SETS set field=? where stab=? and field=?', [$newname, $table,$oldname]);
    } elseif ($oldField instanceof \Seolan\Field\File\File){
      $olddir = $GLOBALS['DATA_DIR']."$table/$oldname";
      $newdir = $GLOBALS['DATA_DIR']."$table/$newname";
      rename($olddir, $newdir);
    }
    
    if(\Seolan\Core\System::tableExists('A_'.$table)){
      if(!in_array(strtolower($sqltype), array('text','longtext'))) {
	getDB()->execute('alter table A_'.$table.' add `'.$newname.'` '.$sqltype.' DEFAULT '.$oldField->getDefaultValueSqlExpression());
      } else {
	getDB()->execute('alter table A_'.$table.' add `'.$newname.'` '.$sqltype);
      }
      getDB()->execute("UPDATE `A_$table` SET `$newname`=`$oldname`");
      getDB()->execute("ALTER TABLE `A_$table` DROP `$oldname`");
    }

								       
    \Seolan\Core\DataSource\DataSource::clearCache();
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);;
    // on efface l'ancien
    $ds->delField(['field' => $oldname]);
    
    \Seolan\Core\DataSource\DataSource::clearCache();
    
  }
  static function addFields($table, $fields) {
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);

    foreach ($fields as $field_desc) {
      if(!$field_desc['field'] && count($field_desc) == 13 && $field_desc[0]) {
        $field_spec = [
          'field'        => $field_desc[0],
          'label'        => $field_desc[1],
          'ftype'        => $field_desc[2],
          'fcount'       => $field_desc[3],
          'forder'       => $field_desc[4],
          'compulsory'   => $field_desc[5],
          'queryable'    => $field_desc[6],
          'browsable'    => $field_desc[7],
          'translatable' => $field_desc[8],
          'multi'        => $field_desc[9],
          'published'    => $field_desc[10],
          'target'       => $field_desc[11],
          'options'      => $field_desc[12],
        ];
      } else {
	$field_spec = $field_desc;
      }
      $field_name = $field_spec['field'];
      $field_label = $field_spec['label'];
      if(is_array($field_label)) {
	$field_label = $field_label[key($field_label)];
      }
      if (!fieldExists($table, $field_name)) {
	$ds->createField(
	  $field_name,
	  $field_label,
	  $field_spec['ftype'],
	  $field_spec['fcount'],
	  $field_spec['forder'],
	  $field_spec['compulsory'],
	  $field_spec['queryable'],
	  $field_spec['browsable'],
	  $field_spec['translatable'],
	  $field_spec['multi'],
	  $field_spec['published'],
	  $field_spec['target'],
	  $field_spec['options']
	);
	Logs::upgradeLog("Champ $field_name ajouté dans la table $table");
      }
      else {
	Logs::upgradeLog("Champ $field_name déjà présent dans la table $table");
      }
    }
  }

  static function editFields($table, $fields) {
    /** @var \Seolan\Model\DataSource\Table\Table $ds */
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);

    foreach ($fields as $field_desc) {
      $field_name = $field_desc['field'];
      if(fieldExists($table, $field_name)) {
	$ds->procEditField($field_desc);
	Logs::upgradeLog("Champ $field_name modifié dans la table $table");
      }
      else {
	Logs::upgradeLog("Champ $field_name non présent dans la table $table, appel à addFields");
	self::addFields($table, array($field_desc));
      }
    }
  }

  static function deleteFields($table, $fields) {
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);

    foreach ($fields as $field_name) {
      if ($ds && fieldExists($table, $field_name)) {
	$ds->delField(['field' => $field_name]);
	Logs::upgradeLog("Champ $field_name supprimé de la table $table");
      }
      else {
	Logs::upgradeLog("Champ $field_name non présent dans la table $table");
      }
    }
  }
  static function addModule($table_name, $name, $group, $class = NULL, $typeWizard = NULL, $returnBool = false, $noCheck = false) {
    Module::clearCache();
    $modules = Module::modulesUsingTable($table_name, true, false, false);
    if (isset($class) && !isset($typeWizard)){
      $typewizard = Module::getModuleWizardPathFromParents($class);
    }
    if ($noCheck || !is_array($modules) || (is_array($modules) && count($modules) === 0)) {
      if($typeWizard) {
	$wd               = new $typeWizard();
      } else {
	$wd               = new \Seolan\Core\Module\Wizard();
      }
      $options          = [];
      $options['group'] = $group;
      $options['table'] = $table_name;
      if($class) $options['theclass'] = $class;
      $moid = $wd->quickCreate($name, $options);
      Module::clearCache();
      Logs::upgradeLog('Module '.$name.' '.$moid.' created.');
      if($returnBool) return true;
    } else {
      $moid = array_shift(array_keys($modules));
      Logs::upgradeLog('Module '.$name.' already exists ('.$moid.').');
      if($returnBool) return false;
    }
    return $moid;
  }

  static function deleteModule($moid, $deleteTable = false) {
    $ar = array();
    if($deleteTable) {
      $ar['withtable'] = 1;
    }
    Module::clearCache();
    if (Module::moduleExists($moid)) {
      $xmod = Module::objectFactory($moid);
      $xmod->delete($ar);
      Logs::upgradeLog("Module $moid supprimé");
    } else {
      Logs::upgradeLog('Module inexistant.');
    }
  }

  static function addModuleWithoutTable($name, $group, $class) {
    $moid = Module::getMoidFromClassname($class);

    if ($moid === null) {
      $wd               = new \Seolan\Core\Module\Wizard();
      $options          = [];
      $options['group'] = $group;
      if($class) $options['theclass'] = $class;
      $wd->quickCreate($name, $options);
      Module::clearCache();
      $moid = Module::getMoidFromClassname($class);
    } else {
      Logs::upgradeLog('Un module existe déjà sur la classe '.$class);
    }

    return $moid;
  }

  static function editModuleOptions($moid, $opts, $valeur = '') {
    $ar = array();
    Module::clearCache();
    if (Module::moduleExists($moid)) {
      $props = Module::_getProperties($moid);
      if(!is_array($opts)) {
        $opts = [$opts => $valeur];
      }
      foreach($opts as $opt => $valeur) {
        $optVal = $props[$opt];
        if(is_array($optVal)) {
          if(!in_array($valeur, $optVal)) {
            array_push($optVal, $valeur);
          }
        }
        else {
          $optVal = $valeur;
        }
        $ar['options'][$opt] = $optVal;
        Logs::upgradeLog('Module ' . $moid . ' option modifiée : ' . $opt . ' : ' . $valeur);
      }
      $xmod = Module::objectFactory($moid);
      $xmod->procEditProperties($ar);
    } else {
      Logs::upgradeLog('Module inexistant.');
    }
  }

  static function editTableOptions($table_name, $opt, $valeur) {
    $ar = array();
    if(\Seolan\Core\System::tableExists($table_name)) {
      $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table_name);
      \Seolan\Core\DataSource\DataSource::preLoadBaseBase(true);

      $ar['options']['bname'][TZR_DEFAULT_LANG] = \Seolan\Core\DataSource\DataSource::getTableProp($table_name, 'BNAME');
      $ar['options']['translate'] = \Seolan\Core\DataSource\DataSource::getTableProp($table_name, 'TRANSLATABLE');
      $ar['options']['auto_translate'] = \Seolan\Core\DataSource\DataSource::getTableProp($table_name, 'AUTO_TRANSLATE');
      $ar['options']['classname'] = \Seolan\Core\DataSource\DataSource::getTableProp($table_name, 'BCLASS');
      $ar['options'][$opt] = $valeur;
      $ds->procEditProperties($ar);
      Logs::upgradeLog('Table option modifiée : ' . $opt . ' : ' . $valeur);
    }
    else {
      Logs::upgradeLog('Table inexistante.');
    }
  }

  static function addMenu($moid, $alias, $title, $options = []){
    $adminInfotree =  \Seolan\Module\Management\Management::getAdminXmodInfotree(array());
    $oid_parent = $adminInfotree->getOidFromAlias($alias);

    $alias = isset($options['alias']) ? $options['alias'] : rewriteToAscii($title);
    $exists = $adminInfotree->getOidFromAlias($alias);
    if (empty($exists)) {

      $function = $options['function'] ?: 'browse';
      $tplentry = $options['tplentry'] ?: 'br';
      $template = $options['template'] ?: 'Module/Table.browse.html';

      $params = [
	'title'     => $title,
	'alias'     => $alias,
	'descr'     => ' ',
	'icon'      => null,
	'picto'     => null,
	'corder'    => $options['order'] ?: 0,
	'linkup'    => $oid_parent,
	'moid'      => $moid,
	'createrub' => 1,
	'sec'       => [
	  'fct'     => '&moid='.$moid.'&function='.$function.'&tplentry='.$tplentry.'&template='.$template.'&_persistent=1',
	  'title'   => $title,
	  'comment' => ' ',
	],
      ];
      $adminInfotree->adminProcNewSection($params);
      Logs::upgradeLog('Item menu "'.$alias.'" ajouté.');
    } else {
      Logs::upgradeLog('L\'item menu "'.$alias.'" existe déjà.');
    }
  }

  static function addRubriqueAdmin($parent_alias, $title, $options = []) {
    $adminInfotree =  \Seolan\Module\Management\Management::getAdminXmodInfotree(array());
    $oid_parent = $adminInfotree->getOidFromAlias($parent_alias);

    $alias = isset($options['alias']) ? $options['alias'] : rewriteToAscii($title);
    $exists = $adminInfotree->getOidFromAlias($alias);
      $params = [
	'title' => $title,
	'linkup' => $oid_parent,
		 ];
    if (empty($exists)) {
      $params['alias'] = $alias;
      $params['linkup'] = $oid_parent;
      $params['corder'] = isset($options['order']) ? $options['order'] : 0;      
      $adminInfotree->procInput($params);

      Logs::upgradeLog('Rubrique "'.$alias.'" ajouté.');
    } elseif($exists && isset($options['alias'])) {
      $params['oid'] = $adminInfotree->getOidFromAlias($options['alias']);
      $params['title'] = $title;
      $params['linkup'] = $oid_parent;
      if(isset($options['order'])) $params['corder'] = $options['order'];      
      $adminInfotree->procEdit($params);

      Logs::upgradeLog('L\'item menu "'.$alias.'" a été modifié.');
    } else {
      Logs::upgradeLog('L\'item menu "'.$alias.'" existe déjà.');
    }
  }

  static function updateSec($moid, $grp, $level, $langs = false, $comment = '', $oid = '', $class = null) {
    if(!$langs) {
      $langs = array_keys($GLOBALS['TZR_LANGUAGES']);
    }
    $who = '';
    if (substr($grp, 0, 4) === 'GRP:') {
      $sql = 'SELECT `GRP` AS `name` FROM `GRP` WHERE `KOID` = ?';
      $who = 'group';
    } else {
      $sql = 'SELECT `fullnam` AS `name` FROM `USERS` WHERE `KOID` = ?';
      $who = 'user';
    }

    $name = getDB()->fetchOne($sql, [$grp]);

    foreach ($langs as $lang) {
      $GLOBALS['XUSER']->setUserAccess($class, $moid, $lang, $oid, $level, $grp, false, true, $comment);
    }

    Logs::upgradeLog('Add security level "'.$level.'" to '.$who.' "'.$name.'" on moid '.$moid.($oid ? ' and OID '.$oid : ''));
  }

  static function addRubrique($title, $alias, $rubriqueMere, $ordre) {
    $xmodinfotree = Module::objectFactory(Ini::get('corailv3_xmodinfotree'));
    if(!$xmodinfotree->getOidFromAlias($alias) && $oidRubriqueMere = $xmodinfotree->getOidFromAlias($rubriqueMere)) {
      $ret = $xmodinfotree->procInput( array( 'title' => $title,
					      'alias' => $alias,
					      'linkup' => $oidRubriqueMere,
					      'corder' => $ordre,
					      'PUBLISH' => 1
      ));
      if($ret['oid'])
	Logs::upgradeLog("Rubrique $alias ajoutée");
      else
	Logs::upgradeLog("! Erreur dans l'ajout de la rubrique $alias !");
    } else {
      Logs::upgradeLog("Rubrique déjà existante ou rubrique mère inexistante");
    }
  }

  static function updateFieldSec($moid, $fields, $uid, $level, $applyalllangs = true) {
    Module::clearCache();
    $xmod = Module::objectFactory($moid);
    if(!is_array($fields)) $fields = array($fields);
    $ko = false;
    foreach($fields as $field) {
      if(!$xmod->xset->fieldExists($field))
	$ko = true;
    }
    $fieldsinstring = implode(",",$fields);

    if(!$ko) {
      array_walk($fields, function(&$value, $key) { $value = '_field-'.$value; } );
      $xmod->procSecEdit(array('oid' => $fields,
			       'uid' => $uid,
			       'level' => $level));
      Logs::upgradeLog('Add security level "'.$level.'" to '.$uid.' on fields '.$fieldsinstring.' moid '.$moid);

    } else {
      Logs::upgradeLog("Module $moid : champ(s) $fieldsinstring inexistant(s)");
    }
  }

  static function editRubriqueProp($alias, $prop, $valeur, $langs) {
    $xmodinfotree = Module::objectFactory(Ini::get('corailv3_xmodinfotree'));
    if($koid = $xmodinfotree->getOidFromAlias($alias)) {
      if(fieldExists($xmodinfotree->table, $prop)) {
	$ret = $xmodinfotree->procEdit( array ( 'oid' => $koid,
						'alias' => $alias,
						'_langs' => $langs,
						$prop => $valeur
	));
	Logs::upgradeLog("Rubrique $alias : $prop = $valeur");
      } else {
	Logs::upgradeLog("Rubrique $alias : champ $prop inexistant");
      }
    } else {
      Logs::upgradeLog("Rubrique $alias inexistante");
    }
  }
  
  static function delRubriqueAdmin($alias) {
    /** @var Seolan\Module\InfoTree $adminInfotree */
    $adminInfotree =  \Seolan\Module\Management\Management::getAdminXmodInfotree(array());
    if($koid = $adminInfotree->getOidFromAlias($alias)) {
      $adminInfotree->delCat(['oid' => $koid]);
      Logs::upgradeLog("La rubrique $alias a été supprimée avec ses sections.");
    } else {
      Logs::upgradeLog("Rubrique $alias inexistante");
    }
  }

  static function addLabels($labels) {
    foreach ($labels as $label) {
      $sql = 'SELECT count(1) FROM `LABELS` WHERE `VARIABL` = \''.$label['var'].'\'';
      if (!(bool)getDB()->fetchOne($sql)) {
	$koid = \Seolan\Core\DataSource\DataSource::getNewBasicOID('LABELS');
	$sql = 'INSERT INTO `LABELS` (`KOID`, `LANG`, `TITLE`, `SELECTO`, `LABEL`, `VARIABL`) VALUES ';

	$sql_lang = '';

	foreach ($label['label'] as $lang => $text) {
	  $sql_lang .= '(\''.$koid.'\', \''.$lang.'\', \''.str_replace("'", "\'", $label['title']).'\', \''.$label['selector'].'\', \''.str_replace("'", "\'", $text).'\', \''.$label['var'].'\'),';
	}

	$sql_lang = substr($sql_lang, 0, -1);

	$sql .= $sql_lang;

	getDB()->execute($sql);

	Logs::upgradeLog('Ajout du label '.$label['var'].'.');
      } else {
	Logs::upgradeLog('Le label '.$label['var'].' existe déjà.');
      }
    }
  }

  static function editLabels($labels) {
    foreach ($labels as $label) {
      $sql = 'SELECT count(1) FROM `LABELS` WHERE `VARIABL` = \''.$label['var'].'\'';
      if ((bool)getDB()->fetchOne($sql)) {

	foreach ($label['label'] as $lang => $text) {
	  $sql = 'UPDATE `LABELS` SET `LABEL` = \''.str_replace("'", "\'", $text).'\' WHERE `VARIABL` = \''.$label['var'].'\' AND `LANG` = \''.$lang.'\'';

	  getDB()->execute($sql);

	  Logs::upgradeLog('Mise à jour du label '.$label['var'].' en '.$lang.'.');
	}
      } else {
	Logs::upgradeLog('Le label '.$label['var'].' n\'existe pas !');
      }
    }
  }

  static function addView($view_name, $options) {
    if (!\Seolan\Core\System::isView($view_name, true)) {
      //Création de la vue

      $ar['translate']               = $options['translate'] ?: 0;
      $ar['auto_translate']          = $options['auto_translate'] ?: 0;
      $ar['bname'][TZR_DEFAULT_LANG] = $options['bname'];
      $ar['btab']                    = $view_name;
      $ar['classname']               = $options['classname'] ?: '\Seolan\Model\DataSource\View\View';

      $ar['bparam'] = [
	'btab' => $view_name,
	'classname' => $options['classname'] ?: '\Seolan\Model\DataSource\View\View',
	'auto_translate' => $options['auto_translate'] ?: 0,
	'bname' => [
	  TZR_DEFAULT_LANG => $options['bname'],
	],
	'publish' => 1,
	'translate' => $options['translate'] ?: 0,
	'maintable' => $options['maintable'],
	'query' => $options['query'],
	'viewfields' => $options['viewfields'],
      ];

      $result = \Seolan\Model\DataSource\View\View::procNewSource($ar);

      //Mise à jour du BOID de la table avec le md5 de la table
      $boid = $result['boid'];
      if ($result['error']) {
	Logs::upgradeLog('[CRITIQUE] '.$result['message']);
	return;
      } else {
	$boid = md5($view_name);
	getDB()->execute('UPDATE `BASEBASE` SET `LOG` = 1, `BOID` = ? WHERE `BOID` = ?', [$boid, $result['boid']]);
	getDB()->execute('UPDATE `AMSG` SET `MOID` = ? WHERE `MOID` = ?', [$boid, $result['boid']]);
	Logs::upgradeLog('Création de la vue '.$view_name.'.');
      }
    } else {
      Logs::upgradeLog('La vue '.$view_name.' existe déjà !');
    }
  }

  static function addImportProc($moid, $reference, $comment, $spec, $auto = false) {
    /** @var \Seolan\Module\Table\Table $modImport */
    $modImport = &Module::objectFactory(['tplentry' => TZR_RETURN_DATA, 'moid' => MOID_IMPORT]);

    $sql = 'SELECT count(1) FROM `IMPORTS` WHERE ID = ? AND modid = ?';
    if (!(bool)getDB()->fetchOne($sql, [$reference, $moid])) {
      $ar = [
	'ID'     => $reference,
	'modid'  => $moid,
	'remark' => $comment,
	'spec'   => $spec,
	'auto'   => ($auto === true ? 1 : 2)
      ];

      $modImport->procInsert($ar);
      Logs::upgradeLog('Add import procedure '.$reference);
    } else {
      Logs::upgradeLog('Procedure '.$reference.' already exist on moid '.$moid);
    }
  }

  static function refreshModulesList() {
    $rs=getDB()->fetchAll('select * from MODULES');
    foreach($rs as $ors) {
      $toid=$ors['TOID'];
      $moid=$ors['MOID'];
      $ors['MPARAM']=\Seolan\Core\Options::decode($ors['MPARAM']);
      $c=@Module::$_modules[$toid]['CLASSNAME'];
      Module::$_modules[$toid]['_modules'][$moid]=true;
      if(!empty($ors['MPARAM']['theclass']) && class_exists($ors['MPARAM']['theclass'])) $c=$ors['MPARAM']['theclass'];
      $ors['CLASSNAME']=$c;
      Module::$_mcache[$ors['MOID']]=$ors;
    }
    unset($rs);
  }

  static function addStringsetVal($table, $field, $val, $txt, $order) {
    if(!getDB()->fetchExists('select 1 from SETS where STAB=? and FIELD=? and SOID=?', array($table, $field, $val))) {
      if(!is_array($txt)) {
	$txt = array(TZR_DEFAULT_LANG => $txt);
      }
      foreach($txt as $lang => $txttrad) {
	getDB()->execute('insert into SETS (SOID, STAB, FIELD, SLANG, STXT, SORDER) values (?, ?, ?, ?, ?, ?)', array($val, $table, $field, $lang, $txttrad, $order));
      }
      Logs::upgradeLog("Valeur $val ajouté pour le champ $field");
    }
    else {
      Logs::upgradeLog("Valeur $val déjà présente pour le champ $field");
    }
  }

  /**
   * @param int $moid
   * @param string $field : nom SQL du champ
   * @param string $uid : OID d'un GROUP ou d'un USER
   * @param string $level : default / none / ro / rw
   */
  static function editSecField($moid, $field, $uid, $level) {
    /** @var Module $mod */
    $mod = Module::objectFactory($moid);

    if ($mod === null) {
      Logs::upgradeLog(__METHOD__.' : MOID '.$moid.' not exists !');
    } else {
      $ar = [
	'uid'           => $uid,
	'level'         => $level,
	'oid'           => '_field-'.$field,
	'applyalllangs' => true,
      ];
      $mod->procSecEdit($ar);
      Logs::upgradeLog("Update security for field $field on module $moid for $uid to $level");
    }
  }

  /// suppression d'une source de données
  static function deleteDataSource($table_name) {
    getDB()->execute('DELETE FROM AMSG WHERE MOID IN (SELECT BOID FROM BASEBASE WHERE BASEBASE.BTAB = ?)', [$table_name]);
    Logs::upgradeLog('Suppression des enregistrements liés à la table '.$table_name.' dans AMSG');

    getDB()->execute('DELETE FROM BASEBASE WHERE BASEBASE.BTAB = ?', [$table_name]);
    Logs::upgradeLog('Suppression de '.$table_name.' dans BASEBASE');

    getDB()->execute('DELETE FROM MSGS WHERE MTAB = ?', [$table_name]);
    Logs::upgradeLog('Suppression des enregistrements liés à la table '.$table_name.' dans MSGS');

    getDB()->execute('DELETE FROM DICT WHERE DTAB = ?', [$table_name]);
    Logs::upgradeLog('Suppression des enregistrements liés à la table '.$table_name.' dans DICT');

    getDB()->execute('DROP TABLE IF EXISTS '.$table_name);
    Logs::upgradeLog('Suppression de la table '.$table_name.' dans DICT');
  }
  /// Nettoyage des caches (entre les patchs par exemple)
  public static function clearCaches(){
    Module::clearCache();
    \Seolan\Core\DbIni::clear('modules%');
    \Seolan\Core\DataSource\DataSource::clearCache();
    \Seolan\Core\System::clearCache();
  }
  /**
   * affichage d'une liste d'upgrades 
   */
  public static function pendingToString(array $list):string{
    $txt = '';
    foreach($list as $date=>$infos){
      $txt.=("\n $date :");
      foreach($infos as list($classname, $level)){
	$txt.=("\n\t-$classname $level");
      }
    }
    $txt.="\n";
    return $txt;
  }
  /**
   * Récupère les classes utilisées ou définies
   * dans les champs et modules
   */
  private static function usedClasses(string $query):array{
    $list = getDB()->fetchAll($query);
    $used = [];
    $classes = null;
    $usedFieldClasses=[];
    foreach($list as $line) {
      $class = null;
      $decoded = \Seolan\Core\Options::decode($line['param']);
      if (isset($decoded['theclass']) && !empty($decoded['theclass'])){
	$fclass = $decoded['theclass'];
	$usedFieldClasses[] = trim($fclass);
      }
    }
    return array_unique(array_filter($usedFieldClasses));
  }
  /**
   * Liste des classes "upgradable"
   * - classes des modules et champs
   * - classes utilisées (modules et champs)
   * - classes définies dans la conf du repository
   * qui ne sont pas des modules ou des champs
   */
  private static function upgradableClasses(){
    // classes des champs + champs définis (cas de surcharges portant des upgrades)
    $fieldClasses = self::usedClasses('select DPARAM as param from DICT');
    foreach(\Seolan\Core\Field\Field::$_fields as $classname=>$infos){
      if (!in_array($classname, $fieldClasses))
	$fieldClasses[] = $classname;
    }
    // classes des modules + modules existants (cas de surcharge portant des upgrades)
    $modClasses = self::usedClasses('select MPARAM as param from MODULES');
    foreach(Module::$_modules as $toid=>$infos){
      if (!in_array($infos['CLASSNAME'], $modClasses))
	$modClasses[] = $infos['CLASSNAME'];
    }
    // repositories
    $reposClasses = [];
    foreach($GLOBALS['REPOSITORIES'] as $prefix=>$repo){
      $f = $prefix."_upgradableClasses";
      if ($prefix == 'Seolan'){
	$c = "{$repo['src']}../config/upgradableclasses.php";
      } else {
	$c = "{$repo['src']}upgradableclasses.php";
      }
      if ((@include_once($c)) && function_exists($f)){
	Logs::notice(__METHOD__, "repository classes : $f ");
	$repoClasses = call_user_func($f);
	$reposClasses = array_merge($reposClasses, $repoClasses);
      } else {
	Logs::notice(__METHOD__, "repository without config / upgradable classes : $prefix $c $f");
      }
    }
    // applications
    $appClasses = [];
    if (defined('TZR_USE_APP')){
      $appClasses = Application::getUpgradableClasses();
    }

    // ajout des classes parentes à l'ensemble
    $classes = [];
    foreach(array_merge([$GLOBALS['START_CLASS']], $reposClasses, $modClasses, $fieldClasses, $appClasses) as $classname){
      unset($pclasses);
      $pclasses = class_parents($classname);
      if (!is_array($pclasses))
	$pclasses = [];
      else
	$pclasses = array_filter(array_values($pclasses));
      // cadrage des noms des espaces de noms ...
      foreach($pclasses as &$pclass){
	if (substr($pclass, 0, 1) != '\\')
	  $pclass = '\\'.$pclass;
      }
      $classes = array_merge($classes,[$classname],$pclasses);
    }
    return array_unique($classes);
  }
    /**
   * Liste ordonnée des patchs à traiter pour les classes passées
   * Liste des patchs 'critiques' en plus
   */
  public static function pendingUpgrades(?array $classes=null):array{
    if ($classes == null){
      $classes = self::upgradableClasses();
    }
    $upgrades=\Seolan\Core\DbIni::getStatic('upgrades','val');
    $pending = [];
    $critical = [];
    foreach($classes as $classname){
      foreach(self::classPendingUpgradenos($classname, $upgrades) as $upgradeno){
	foreach(['pending'=>['','critical'], 'critical'=>['critical']] as $var=>$cats){
	  if (in_array($classname::$upgrades[$upgradeno], $cats)){
	    if (!isset($$var[$upgradeno]))
	      $$var[$upgradeno] = [];
	    $$var[$upgradeno][] = [$classname, $classname::$upgrades[$upgradeno]];
	  }
	}
      }
    }
    ksort($critical);
    ksort($pending);
    return [$pending, $critical];
  }
    /**
   * Récupère les numéro d'upgrades upgrades à appliquer pour une classe donnée
   */
  public static function classPendingUpgradenos($classname, $upgrades=null){
    // on stocke un namepsace absolu dans les upgrades
    if (substr($classname, 0, 1) != '\\')
      $classname = '\\'.$classname;
    // Vérifie si la classe a des upgrades
    if (!class_exists($classname)){
      Logs::critical(__METHOD__,"$classname doesn't exist");
      return [];
    }
    if(!isset($classname::$upgrades)){
      return [];
    }
    // $classname::$upgrades étant héritée, ceux des classes mères n'existent pas nécessairement
    $ref=new \ReflectionClass($classname);
    $propClass = $ref->getProperty('upgrades')->class;
    // pour les classes locales au moins, tester '' et '\\'
    if(!in_array($classname, ['\\'.$propClass, $propClass])){
      return [];
    } 
    //Upgrades déjà effectués en base et diff avec les upgrades de la classe
    if ($upgrades == null)
      $upgrades=\Seolan\Core\DbIni::getStatic('upgrades','val');
    
    // on teste $classname et '\\'.$classname
    if(!isset($upgrades[$classname]) && !isset($upgrades['\\'.$classname]))
      $upgrades[$classname]=[];
    return array_diff(array_keys($classname::$upgrades),$upgrades[$classname]);
  }
  /**
   * Méthode de base d'application des upgrades
   * - de tous les upgrades des classes (chemin) prévues 
   * - des upgrades d'une classe (et parentes ? non ?)
   * - d'un upgrade identifié par classe + numéro
   * Dans ce cas seulement, l'upgrade est exécuté même si déjà passé
   */
  static function applyUpgrades(string $classname=null, string $upgradeno=null) {
    if(empty($classname) && empty($upgradeno)) {
      $classes = self::upgradableClasses();
      // calcul des upgrades en attente pour l'ensemble des classes
      list($pending,$critical) = self::pendingUpgrades($classes);
      if (defined('TZR_UPGRADE_DRYRUN')){
	echo("\nupgrades : \n".self::pendingToString($pending));
      }
      
      foreach($pending as $upgradeno=>$classesupgrades){
	self::applyClassesUpgrades($upgradeno, $classesupgrades);
      }
      
    } else if(empty($upgradeno)) {
      // recherche des upgrades de la classe
      list($pending,$critical) = self::pendingUpgrades([$classname]);
      if (defined('TZR_UPGRADE_DRYRUN')){
	echo("\nupgrades : \n".self::pendingToString($pending));
      }
    } else {
      self::applyClassUpgrade($classname, $upgradeno);
    }
  }
  /**
   * Application d'un groupe d'upgrades de même numero
   */
  private static function applyClassesUpgrades(string $upgradeno, array $classesupgrades){
    foreach($classesupgrades as list($classname, $info)){
      Logs::debug(__METHOD__." $classname '$info' $upgradeno");
      self::applyClassUpgrade($classname, $upgradeno);
    }
  }
  /**
   * application d'un patch donné
   * à voir si le lock echoue ?
   */
  static function applyClassUpgrade($classname, $upgradeno) {
    $verbose = defined('TZR_BATCH');
    $dryrun = defined('TZR_UPGRADE_DRYRUN');

    if(!($lock=\Seolan\Library\Lock::getExclusiveLock('upgrade')))
      critical_exit(__METHOD__." Unable to getLock during $classname upgrade $upgradeno",500,true);
    
    Logs::upgradeLog(__METHOD__." Upgrade $classname with $upgradeno start", $verbose);

    // Applique l'upgrade
    list($upgradefile, $functionName, $commentFunctionName) = self::getUpgradeParameters($classname, $upgradeno);

    Logs::debug(__METHOD__." $functionName, $upgradefile");
    try{
      $ok = include_once($upgradefile);
      if ($ok && function_exists($functionName)){
	if (!$dryrun){

	  //application
	  $functionName();

	  // on met à jour la base pour indiquer que cet upgrade est fait
	  $upgrades=\Seolan\Core\DbIni::getStatic('upgrades','val');
	  if(empty($upgrades[$classname]) || !in_array($upgradeno, $upgrades[$classname])) {
	    $upgrades[$classname][]=$upgradeno;
	    \Seolan\Core\DbIni::setStatic('upgrades',$upgrades);
	  }
	  
	  if (isset($GLOBALS['HAS_VHOSTS']) && $GLOBALS['HAS_VHOSTS']) {
	    //launch parallel process to upgrade packs minisites
	    \Seolan\Module\MiniSite\MiniSite::applyClassUpgrade($classname, $upgradeno);
	    sleep(1);
	  }
	} else {
	  Logs::notice(__METHOD__, "dryrun $upgradefile $functionName");
	  
	  if (isset($GLOBALS['HAS_VHOSTS']) && $GLOBALS['HAS_VHOSTS']) {
	    //launch parallel process to upgrade packs minisites avec option dryrun
	    \Seolan\Module\MiniSite\MiniSite::applyClassUpgrade($classname, $upgradeno, true);
	    sleep(1);
	  }
	}
      } else {
	throw new \Exception("check upgrade, invalid file patch or function name  : $upgradefile $functionName");
      }
    }catch(\Throwable $t){
      Logs::upgradeLog(__METHOD__."{$t->getMessage()} \n\t line : {$t->getLine()} \n\t file : {$t->getFile()}");
      Logs::upgradeLog(__METHOD__.$t->getTraceAsString());
    }

    // Libère le verrou
    \Seolan\Library\Lock::releaseLock($lock);

    Logs::upgradeLog('Upgrade '.$classname.' to '.$upgradeno.' end', $verbose);

    self::clearCaches();
    
  }
  /**
   * A partir de la classe et du numéro d'upgrade retourne:
   * - le chemin du fichier
   * - le nom de la function
   * - le nom de la méthode pour les commentaires
   */
  public static function getUpgradeParameters(string $classname, string $upgradeNo):array{
    $path = explode('\\', $classname);
    array_shift($path);
    $prefix = array_shift($path);
    $cn = array_pop($path);
    if (strpos($classname, '\Local') === false){
      if (strpos($classname, '\Seolan') === false && isset($GLOBALS['REPOSITORIES'][$prefix]) ){
	$file = $GLOBALS['REPOSITORIES'][$prefix]['src'].implode('/', $path).'/upgrades/'.$cn.'/'.$upgradeNo.'.php';
      } else {
	$file = 'src/'.implode('/', $path).'/upgrades/'.$cn.'/'.$upgradeNo.'.php';
      }
    } else {
      if(count($path)) {
        $file = implode('/', $path).'/upgrades/'.$cn.'/'.$upgradeNo.'.php';
      } else {
          $file = 'upgrades/'.$cn.'/'.$upgradeNo.'.php';
      }
    }
    return [$file,
	    $cn.'_'.$upgradeNo,
	    $cn.'_comment_'.$upgradeNo];
  }
  /**
   * checke la version d'un objet upgradable
   */
  public static function hasUpgrade($objectOrClassname, string $upgradeno) : bool {
    $upgrades=\Seolan\Core\DbIni::getStatic('upgrades','val');
    $has = false;
    $classname = is_string($objectOrClassname)?$objectOrClassname:get_class($objectOrClassname);
    while($classname != null && !$has){
      if (strpos($classname, '\\') != 0)
	$classname='\\'.$classname;
      if (isset($upgrades[$classname]) && in_array($upgradeno, $upgrades[$classname]))
	$has = true;
      else
	$classname = get_parent_class($classname);
    }
    return $has;
  }
  /**
   * @param string $alias : alias de la page dans laquelle ajouter une section
   * @param string $oidTpl : OID du gabarit
   * @param int $position : Position de la section dans la rubrique
   * @param string $zone : Zone de destination
   * @param array $infos : Tableau avec en clé les champs de la table T004 et leur valeur
   *
   * @return array|bool : tableau avec les clés oid et itoid en cas de succès ou false sinon
   */
  public static function addSectionStatic($alias, $oidTpl, $position, $zone = 'default', $infos = []){
    /** @var InfoTree $xmodinfotree */
    $xmodinfotree = Module::objectFactory(Ini::get('corailv3_xmodinfotree'));
    
    $params = [
      'tplentry' => TZR_RETURN_DATA,
      'oidit' => $xmodinfotree->getOidFromAlias($alias),
      'oidtpl' => $oidTpl,
      'position' => $position,
      'zone' => $zone,
    ];
    $ret = $xmodinfotree->insertsection(array_merge($params, $infos));
    
    if ($ret) {
      Logs::upgradeLog('Section ajouté dans la page '.$alias);
      return $ret;
    }
    return false;
  }
  /**
   * @param string $alias : alias de la page
   * @param string $oidSection : OID de la section à publier
   * @param bool $publish : true pour publier, false sinon.
   */
  public static function publishSection($alias, $oidSection, $publish = true){
    /** @var InfoTree $xmodinfotree */
    $xmodinfotree = Module::objectFactory(Ini::get('corailv3_xmodinfotree'));
    $oidRub = $xmodinfotree->getOidFromAlias($alias);
    
    if ($oidRub) {
      $xmodinfotree->publish(['oidsection' => $oidSection, 'oidit' => $oidRub, '_pub' => (int)$publish]);
      Logs::upgradeLog('Section '.$oidSection.' de la page '.$alias.' publiée');
    } else {
      Logs::upgradeLog('[ERR] : Impossible de publier la section l\'alias de la rubrique ('.$alias.') n\'existe pas !');
    }
  }
  
  /**
   * Ajoute une section fonction dans une page
   *
   * @param string $rubrique : alias ou oid de la rubrique
   * @param string $tplOid : oid du gabarit
   * @param int    $moid : oid du module
   * @param string $query : chaine json de la section
   * @param string $lang : code lang par defaut TZR_DEFAULT_LANG
   * @param int    $publish : par défaut 1
   * @param bool   $checkExist : par defaut true pour éviter d'ajouter plusieurs fois la même section
   * @param string $zone : zone de la section
   */
  public static function addFunctionSection ($rubrique, $tplOid, $moid, $query, $lang = TZR_DEFAULT_LANG, $publish = 1, $checkExist = true, $zone = 'default') {
    $xmodinfotree = Module::objectFactory(Ini::get('corailv3_xmodinfotree'));
    
    if (!\Seolan\Core\Kernel::isAKoid($rubrique)) {
      $rubrique = $xmodinfotree->getOidFromAlias($rubrique);
    }
    
    $queryExists = 'SELECT count(1) FROM `'.$xmodinfotree->tname.'` WHERE KOIDSRC = ? AND KOIDTPL = ? AND ZONE = ?';
    
    if ($checkExist && (int)getDB()->fetchOne($queryExists, [$rubrique, $tplOid, $zone]) === 0) {
      $dynAr = [
        'oid' => \Seolan\Core\DataSource\DataSource::getNewBasicOID($xmodinfotree->dyntable),
        'LANG' => $lang,
        'OWN' => 'USERS:1',
        'PUBLISH' => $publish,
        'tpl' => $tplOid,
        'module' => $moid,
        'query' => $query,
      ];
      
      /** @var \Seolan\Core\DataSource\DataSource $dsDyn */
      $dsDyn = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$xmodinfotree->dyntable);
      $r = $dsDyn->procInput($dynAr);
      
      if (!\Seolan\Core\Kernel::isAKoid($r['oid'])) {
        \Seolan\Core\Logs::upgradeLog('ERREUR lors de l\'insertion dans la table dynamique.');
      } else {
        \Seolan\Core\Logs::upgradeLog('Insertion dans '.$xmodinfotree->dyntable.' effectué ('.$r['oid'].').');
        
        $order = (int)getDB()->fetchOne('SELECT max(ORDER1) + 1 FROM `'.$xmodinfotree->tname.'` WHERE KOIDSRC = ?', [$rubrique]);
        $itOid = substr(md5(uniqid(mt_rand(), true)),0,40);
        
        $r = getDB()->execute('INSERT INTO `'.$xmodinfotree->tname.'` SET KOIDSRC=?,KOIDDST=?,KOIDTPL=?,ORDER1=?,ITOID=?,ZONE=?', [$rubrique, $r['oid'], $tplOid, $order, $itOid, $zone]);
        
        if ($r === 1) {
          \Seolan\Core\Logs::upgradeLog('Section ajouté à la fin de la page "'.$xmodinfotree->getAliasFromOid($rubrique).'" ('.$itOid.')');
        } else {
          \Seolan\Core\Logs::upgradeLog('ERREUR lors de l\'ajout de la section fonction à la page "'.$xmodinfotree->getAliasFromOid($rubrique).'" ('.$itOid.')');
        }
      }
    } else {
      \Seolan\Core\Logs::upgradeLog('La section existe déjà pour la page "'.$xmodinfotree->getAliasFromOid($rubrique).'"');
    }
  }

  public static function addTemplate($title, $disp, $edit='', $options=[]) {
    $options = array_merge(array(
      'modid' => '',
      'gtype' => 'page',
      'functions' => '',
      'tab' => '',
      'modidd' => '',
      'dfmt' => '',
      'opts' => '',
      'packs' => '',
      'zones' => '',
    ), $options);

    $ds_tpl = DataSource::objectFactoryHelper8('TEMPLATES');

    $oidTpl = getDB()->fetchOne('select KOID from TEMPLATES where title=?', array($title));
    if (empty($oidTpl)) {
      $options['title'] = $title;
      $options['disp'] = base64_encode("<%include file='$disp'%>");
      $options['disp_title'] = strpos($disp, '/') !== false ? substr(strrchr($disp, "/"), 1) : preg_replace('/^\w+:/', '', $disp);
      if($edit) {
        $options['edit'] = base64_encode("<%include file='$edit'%>");
        $options['edit_title'] = strpos($edit, '/') !== false ? substr(strrchr($edit, "/"), 1) : preg_replace('/^\w+:/', '', $edit);
      }

      $ret = $ds_tpl->procInput($options);

      $oidTpl = $ret['oid'];
      if (!\Seolan\Core\Kernel::isAKoid($oidTpl)) {
        throw new \Exception("Erreur lors de l'insertion du gabarit '$title'.");
      }
      \Seolan\Core\Logs::upgradeLog("Gabarit '$title' ajouté.");
    }
    else {
      \Seolan\Core\Logs::upgradeLog("Le gabarit '$title' existe déjà.");
    }

    return $oidTpl;
  }
  /// affichage d'une liste utilisé par readline
  static function readlineShow($set) {
    if(!empty($set)) {
      foreach($set as $t=>$lis) {
	if($t!=$lis)
	  echo "[$t] $lis\n";
	else 
	  echo $lis.",";
      }
      echo "\n";
    }
  }
  /// surcharge du readline php qui boucle sur la saisie
  static function readline($prompt, $default=NULL,$set=NULL) {
    if(empty($default)) {
      static::readlineShow($set);
      $answer=readline("$prompt > ");
      while(empty($answer) || (!empty($set)&&!(isset($set[$answer])))) {
	static::readlineShow($set);
	$answer=readline("$prompt > ");
      }
    } else {
      static::readlineShow($set);
      $answer=readline("$prompt (default: $default) > ");
      while(!empty($answer) && !empty($set) && !isset($set[$answer]) ) {
	static::readlineShow($set);
	$answer=readline("$prompt (default: $default) > ");
      }
      if(empty($answer)) $answer=$default;
    }
    if(!empty($set)) return $answer;
    return $answer;
  }
  /// confirmation d'action en ligne de commande
  static function confirm($prompt, $default="Y") {
    $answer=readline("$prompt (default: $default) > ");
    while(!empty($answer) && !stristr('yn',$answer)) {
      $answer=readline("$prompt (default: $default) > ");
    }
    if(empty($answer)) $answer=$default;
    return ($answer=="Y")||($answer=="y");
  }
  /// affichage formatté 'façon' mysql d'un résultat sql
  static function sqldump($q,$values=[]){
    if (is_string($q)){
      $trace = "$q".implode(",",$values);
      $res = getDB()->select($q,$values)->fetchAll();
    } else {
      $trace = $q->queryString;
      $res = $q->fetchAll();
    }
    $mecho = function($m) use(&$trace){
      $trace .= $m;
    };
    $cols = [];
    foreach($res as $line){
      foreach($line as $k=>$v){
	if (!isset($cols[$k]))
	$cols[$k] = strlen($k);
	$cols[$k] = max($cols[$k], strlen($v));
      }
    }
    $mecho("\n");
    foreach(array_keys($cols) as $k){
      $mecho('+--'.str_pad(str_pad('',strlen($k),'-'), $cols[$k],'-').'--');
    }
    $mecho('+');
    $mecho("\n");
    foreach(array_keys($cols) as $k){
      $mecho('|  '.str_pad($k, $cols[$k]).'  ');
    }
    $mecho("|\n");
    foreach(array_keys($cols) as $k){
    $mecho('+--'.str_pad(str_pad('',strlen($k),'-'), $cols[$k],'-').'--');
    }
    $mecho('+');
    foreach($res as $line){
      $mecho("\n");
      foreach(array_keys($cols) as $k){
      $mecho('|  '.str_pad($line[$k], $cols[$k]).'  ');
      }
      $mecho('|');
    }
    $mecho("\n");
    foreach(array_keys($cols) as $k){
      $mecho('+--'.str_pad(str_pad('',strlen($k),'-'), $cols[$k],'-').'--');
    }
    $mecho('+');
    $mecho("\n");
    return $trace;
  }
}
