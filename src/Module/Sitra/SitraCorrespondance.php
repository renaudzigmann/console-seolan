<?php
namespace Seolan\Module\Sitra;

use Seolan\Core\System;

class SitraCorrespondance {

  const ACTION_LINKS_FUSION = 'LINKS_FUSION';
  const ACTION_CONSOLID_KEY_VALUE = 'CONSO_KEY_VALUE';
  const ACTION_NEXT_DATE = 'NEXT_DATE';

  /**
   * Correspondance entre les tables de consolidation et les types de fiche sitra
   * @var array
   */
  protected $types_matches;

  /**
   * @var XDataSource
   */
  protected $table_correspondance_xds;

  /**
   * @var XDataSource
   */
  protected $fields_correspondance_xds;

  /**
   * Ecrire pour 'TABLE' => ['nom_du_champ_consolidé' => function($nom_du_champ_sitra, $objet_sitra) {
   *   return donnée du champ consolidé
   * }, ... ]
   * @var array
   */
  protected $fields_callables = array();

  /**
   * Cache pour éviter de faire trop de requêtes
   * @var array
   */
  protected $cacheTargetOids = array();

  /**
   * Cache pour éviter de faire trop de requêtes
   * @var array
   */
  protected $publishedFields = array();
  
  public function __construct() {
    $this->table_correspondance_xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=SITRA_CONF_TABLE');
    $this->fields_correspondance_xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=SITRA_CORRESPONDANCE');
  }

  /**
   * @return array: Retourne la liste de correspondance entre table de consolidation et type sitra
   */
  protected function getTypesMatches() {
    if ($this->types_matches === Null) {
      $this->types_matches = (array) $this->table_correspondance_xds->browse(array(
        'selectedfields' => 'all',
        '_mode' => 'object'
      ))['lines'];
    }

    return $this->types_matches;
  }

  /**
   * @param $sitra_object
   * @return array: Liste des Tables dans lesquelles l'objet sitra doit être consolidé
   */
  public function getTablesForSitraObject($sitra_object) {
    $types_matches = $this->getTypesMatches();
    $tables = array();
    $sitra_object_type = $sitra_object['otype']->raw;

    foreach ($types_matches as $type_matches) {
      if (in_array($sitra_object_type, $type_matches['oTYPES']->oidcollection)) {
        $tables[] = $type_matches['oTABLE_NAME']->raw;
      }
    }

    return $tables;
  }

  /**
   * Retourne le type (sitra) de l'objet (STRUCTURE, HEBERGEMENT...)
   * @param $sitra_object
   * @return mixed
   * @throws SeolanException
   */
  protected function getSitraObjectType($sitra_object) {
    if (!$sitra_object['otype']->raw) {
      throw new \Seolan\Core\Exception\Exception('Sitra object ('.$sitra_object['oid'].') has no type.');
    }

    return $sitra_object['otype']->raw;
  }

  /**
   * Retourne les données pour la consolidation
   * @param $sitra_object
   * @param $table
   * @return array
   * @throws SeolanException
   */
  public function getLocalSitraDataForTable($sitra_object, $table) {
    $local_data = $this->getLocalDataForFieldsCorrespondance($table, $sitra_object);
    $local_data = array_merge($local_data, $this->getLocalDataForSpecialActions($table, $sitra_object));
    $local_data = array_merge($local_data, $this->getLocalDataForHardcodeFields($table, $sitra_object));

    return $local_data;
  }

  /**
   * Retourne les données consolidé de l'objet sitra pour les champs possédant une relation simple de champs sitra =>
   * champs consolidé.
   * @param $table
   * @param $sitra_object
   * @return array
   * @throws SeolanException
   */
  protected function getLocalDataForFieldsCorrespondance($table, $sitra_object) {
    $local_sitra_data = array();
    $sitra_type = $this->getSitraObjectType($sitra_object);
    $fields_callables = $this->getFieldsCallables($table);
    $table_fields_correspondance = $this->getFieldCorrespondanceForTableAndType($table, $sitra_type);
    foreach ($table_fields_correspondance as $table_field_correspondance) {
      $consolidate_field_name = $table_field_correspondance['LOCAL_FIELD_NAME'];
      $sitra_field_name = $table_field_correspondance['SITRA_FIELD_NAME'];
      $link_table = $table_field_correspondance['LINK_TABLE'];
      $aspect = $table_field_correspondance['ASPECT'];

      $object = $sitra_object;
      if($aspect) {
        foreach($sitra_object['oaspects']->collection as $col) {
          if($col->link['oaspect']->raw == $aspect) {
            $object = $col->link;
            break;
          }
        }
      }

      $local_sitra_data[$consolidate_field_name] = $this->getLocalFieldValue(
        $consolidate_field_name,
        $sitra_field_name,
        $fields_callables,
        $object,
        $table,
        array(
          'link_table' => $link_table
        ));
    }

    return $local_sitra_data;
  }

  /**
   * Retourne les données consolidé de l'objet sitra pour les champs qui doivent être traité par les actions spéciales.
   * @param $table
   * @param $sitra_object
   * @return array
   * @throws SeolanException
   */
  protected function getLocalDataForSpecialActions($table, $sitra_object) {
    $local_sitra_data = array();
    $sitra_type = $this->getSitraObjectType($sitra_object);
    $special_actions = $this->getSpecialActionsForTableAndType($table, $sitra_type);

    foreach ($special_actions as $action => $field_correspondance) {
      foreach ($field_correspondance as $local_field_name => $sitra_fields) {

        switch ($action) {
          case self::ACTION_CONSOLID_KEY_VALUE:

            if (($local_field_value = $this->getConsolidedKeyValue($sitra_object, $sitra_fields))) {
              $local_sitra_data[$local_field_name] = json_encode($local_field_value);
            } else {
              $local_sitra_data[$local_field_name] = '';
            }

            break;
          case self::ACTION_LINKS_FUSION:

            $local_sitra_data[$local_field_name] = $this->getLinksFusionValue($sitra_object, $sitra_fields);

            break;
          default:

            \Seolan\Core\Logs::notice(__METHOD__, 'Action "' . $special_actions . '" inconnue.');

            break;
        }

      }
    }

    return $local_sitra_data;
  }

  /**
   * Retourne la valeur de champs adéquate
   * @param $consolidate_field_name
   * @param $sitra_field_name
   * @param $fields_callables
   * @param $sitra_object
   * @param $consolidate_table
   * @param array $options
   * @return mixed
   */
  protected function getLocalFieldValue($consolidate_field_name, $sitra_field_name, $fields_callables, $sitra_object, $consolidate_table, $options=array()) {
      if (isset($fields_callables[$consolidate_field_name])) {
      $sitra_value = $fields_callables[$consolidate_field_name]($sitra_field_name, $sitra_object);
    } else {
      $descConsolidateField = getDB()->fetchRow('select * from DICT where DTAB=? and FIELD=?', array($consolidate_table, $consolidate_field_name));
      $descSitraField = $sitra_object['o'.$sitra_field_name]->fielddef;

      // On prend la premiere valeur si on essaie de mettre un champ multivalué dans un champ non multivalué
      if($descConsolidateField['MULTIVALUED'] != '1' && count($sitra_object['o'.$sitra_field_name]->collection)) {
        $sitra_field = $sitra_object['o'.$sitra_field_name]->collection[0];
      }
      else {
        $sitra_field = $sitra_object['o'.$sitra_field_name];
      }

      // On prend la premiere valeur du même type dans le target si on essaie de mettre un champ lien dans autre chose
      if($descSitraField->ftype == '\Seolan\Field\Link\Link' && $descConsolidateField['FTYPE'] != $descSitraField->ftype) {
        foreach($sitra_field->link['fields_object'] as $target_field) {
          if($target_field->fielddef->ftype == $descConsolidateField['FTYPE'] || is_subclass_of($descConsolidateField['FTYPE'], $target_field->fielddef->ftype)) {
            $sitra_field = $sitra_field->link['o'.$target_field->field];
            break;
          }
        }
      }

      if(is_a($sitra_field->fielddef, '\Seolan\Field\File\File')) {
        $sitra_value = $sitra_field->filename;
      }
      else {
        $sitra_value = $sitra_field->raw;
      }
    }

    $local_value = $this->applyOptionsToLocalValue($sitra_value, $options);

    return $local_value;
  }

  public function applyOptionsToLocalValue($local_value, $options) {
    $link_table = $options['link_table'];

    if ($link_table) {
      if(\Seolan\Core\System::fieldExists($link_table, 'ID_APIDAE')) {
        if(strpos($local_value, '||') !== false) {
          $explodedValue = explode('||', $local_value);
          $array_value = [];
          foreach($explodedValue as $val) {
            $array_value[] = $this->getCachedOid($val, $link_table);
          }
          $local_value = '||'.implode('||', $array_value).'||';
        }
        else {
          $local_value = $this->getCachedOid($local_value, $link_table);
        }
      }
      else {
        $local_value = preg_replace('/[^:]*:([^|]*\|?\|?)/', $options['link_table'].':$1', $local_value);
      }
    }

    return $local_value;
  }

  public function getCachedOid($value, $table) {
    if($value && $table) {
      if(!$this->cacheTargetOids[$table][$value]) {
        $this->cacheTargetOids[$table][$value] = getDB()->fetchOne("select KOID from $table where ID_APIDAE=?", array($value));
        if(!$this->cacheTargetOids[$table][$value]) {
          if(!$this->publishedFields) {
            $this->publishedFields = getDb()->select('select DTAB, FIELD from DICT where PUBLISHED=1 group by DTAB order by FORDER')->fetchAll(\PDO::FETCH_KEY_PAIR);
          }
          $tableSitra = \Seolan\Core\Kernel::getTable($value);
          $publishedFieldSitra = $this->publishedFields[$tableSitra];
          $publishedFieldConso = $this->publishedFields[$table];
          if($publishedFieldSitra && $publishedFieldConso) {
            $valueSitra = getDB()->fetchOne("select $publishedFieldSitra from $tableSitra where KOID=?", array($value));
            $newKoid = preg_replace('/[^:]*:/', $table.':', $value);
            getDB()->execute("insert ignore into $table (KOID, LANG, ID_APIDAE, $publishedFieldConso) values (?, ?, ?, ?)", array($newKoid, TZR_DEFAULT_LANG, $value, $valueSitra));
            $this->cacheTargetOids[$table][$value] = $newKoid;
          }
        }
      }
      $value = $this->cacheTargetOids[$table][$value];
    }

    return $value;
  }

  /**
   * @return array: Liste des type sitra consolidés
   */
  public function getManagedSitraTypes() {
    $types_matches = $this->getTypesMatches();
    $types = array();

    foreach ($types_matches as $type_matches) {
      foreach ($type_matches['oTYPES']->oidcollection as $type) {
        $types[] = $type;
      }
    }

    return array_unique($types);
  }

  /**
   * Retourne la correspondance champs pour la table et le type demandé.
   * Ne retourne pas les champs configuré avec une actions spéciale ( SPECIAL_ACTION )
   * @param $table
   * @param $type
   * @return array
   */
  public function getFieldCorrespondanceForTableAndType($table, $type) {
    // TODO: Mettre un cache sur le couple de paramètres (Le cache Général de XDb peut etre desactivé)
    return getDb()->fetchAll("SELECT LOCAL_FIELD_NAME, SITRA_FIELD_NAME, LINK_TABLE, ASPECT FROM SITRA_CORRESPONDANCE WHERE"
      ."  (LOCAL_TABLE_NAME = '*' OR LOCAL_TABLE_NAME = :table)"
      ."  AND (SITRA_TYPE = '*' OR SITRA_TYPE = :type)"
      ."  AND (SPECIAL_ACTION IS NULL OR SPECIAL_ACTION = '')"
      ."  AND PUBLISH= :publish"  ,
      array(
        ':table' => $table,
        ':type' => $type,
        ':publish' => 1
      ));
  }

  /**
   * Retourne une les correspondances Sitra pour une table et un champ donné
   */
  public function getSitraFieldCorrespondanceForTable($table, $field) {
    return getDb()->fetchRow("SELECT SITRA_FIELD_NAME, SITRA_FIELD_NAME, LINK_TABLE, ASPECT, SITRA_ELEMENTREFERENCE_ID FROM SITRA_CORRESPONDANCE WHERE"
			     ."  (LOCAL_TABLE_NAME = '*' OR LOCAL_TABLE_NAME = :table)"
			     ."  AND (LOCAL_FIELD_NAME = '*' OR LOCAL_FIELD_NAME = :field)",
			     array(
				   ':table' => $table,
				   ':field' => $field));
  }
  
  /**
   * Retourne la liste des actions spéciales à effectuer pour cette table/type
   * @param $table
   * @param $type
   * @return array: {'ACTION': {'LOCAL_FIELD': ['SITRA_FIELD', 'SITRA_FIELD', [...], [...]}, [...]}
   */
  protected function getSpecialActionsForTableAndType($table, $type) {
    // TODO: Mettre un cache sur le couple de paramètres (Le cache Général de XDb peut etre desactivé)
    $fields_correspondance_records = getDb()->fetchAll(
       "SELECT LOCAL_FIELD_NAME, SITRA_FIELD_NAME, SPECIAL_ACTION"
      ."  FROM SITRA_CORRESPONDANCE WHERE"
      ."    (LOCAL_TABLE_NAME = '*' OR LOCAL_TABLE_NAME = :table)"
      ."    AND (SITRA_TYPE = '*' OR SITRA_TYPE = :type)"
      ."    AND (SPECIAL_ACTION > '')",
      array(
        ':table' => $table,
        ':type' => $type));
    $special_actions = array();

    foreach ($fields_correspondance_records as $field_correspondance_record) {
      $action_name = $field_correspondance_record['SPECIAL_ACTION'];
      $local_field_name = $field_correspondance_record['LOCAL_FIELD_NAME'];
      $sitra_field_name = $field_correspondance_record['SITRA_FIELD_NAME'];

      if (!isset($special_actions[$action_name])) {
        $special_actions[$action_name] = array();
      }

      if (!isset($special_actions[$action_name][$local_field_name])) {
        $special_actions[$action_name][$local_field_name] = array();
      }

      $special_actions[$action_name][$local_field_name][] = $sitra_field_name;
    }

    return $special_actions;
  }

  /**
   *
   * @param $table
   * @return array: Liste des 'field_name' => Callable
   */
  protected function getFieldsCallables($table) {
    if (isset($this->fields_callables[$table])) {
      return $this->fields_callables[$table];
    }

    return array();
  }

  /**
   * Retourne les nom de table qui recoivent les consolidations
   * @return array: Liste des nom de tables
   */
  public function getTables() {
    $types_matches = $this->getTypesMatches();
    $tables = [];
    foreach ($types_matches as $type_matches) {
      $tables[] = $type_matches['oTABLE_NAME']->raw;
    }
    return $tables;
  }

  /**
   * Retourne le format suivant:
   * {SITRA_FIELD_NAME: {'label': xxx, 'value: xxx}, ...}
   * @param $sitra_object
   * @param $sitra_fields
   * @return array
   */
  protected function getConsolidedKeyValue($sitra_object, $sitra_fields) {
    $consolided_keys_values = array();
    foreach ($sitra_fields as $sitra_field) {
      if (($sitra_field_value = $this->getDisplayValueOfField($sitra_object['o'.$sitra_field]))) {

        $consolided_keys_values[$sitra_field] = array(
          'label' => $sitra_object['o'.$sitra_field]->fielddef->label,
          'value' => $sitra_field_value
        );

      }
    }

    // Sur demande de PMA: On retourne Null si la collection est vide.
    if (!$consolided_keys_values) {
      return Null;
    }

    return $consolided_keys_values;
  }

  protected function getDisplayValueOfField($field) {

    if ($field->fielddef instanceof \Seolan\Field\Boolean\Boolean) {
       return $field->text;
    }

    if ($field->fielddef instanceof \Seolan\Field\Link\Link) {
      return $field->text;
    }

    return $field->raw;
  }

  /**
   * Retourne une collection de KOID (KOIDS des champs fournis mis à la suite avec unicité)
   * @param $sitra_object
   * @param $sitra_fields
   * @return array
   */
  protected function getLinksFusionValue($sitra_object, $sitra_fields) {
    $links = array();

    foreach ($sitra_fields as $sitra_field) {
      if ($sitra_object['o'.$sitra_field]->fielddef->multivalued == 1) {
        $links = array_merge($links, $sitra_object['o' . $sitra_field]->oidcollection);
      } elseif (!in_array($sitra_object['o'.$sitra_field]->raw, $links)) {
        $links[] = $sitra_object['o' . $sitra_field]->raw;
      }
    }

    return array_unique( array_filter( $links ) );
  }

  /**
   * Retourne la date la plus proche d'aujourd'hui dans le futur (aujourd'hui compris) dans un champ de type XIntervalDef
   * @param $sitra_object
   * @param $sitra_fields
   * @return string
   */
  protected function getNextDateValue($sitra_object) {
    $dates = explode(';', $sitra_object['odatesOuverture']->raw);
    foreach ($dates as $date) {
      if (strtotime($date) >= strtotime(date('Y-m-d')))
        return $date;
    }
    return '';
  }

  /**
   * Retourne les valeurs locales des champs dont la logique de traitement est spécifique.
   * @param $table
   * @param $sitra_object
   * @return array
   */
  protected function getLocalDataForHardcodeFields($table, $sitra_object) {
    $local_data = array();

    try {
      $local_data['image'] = $this->getSummerImage($sitra_object);
      $local_data['options']['image']['image_del'] = 'off';
      $local_data['options']['image']['del'] = false;
    } catch (\Seolan\Core\Exception\NotFound $exc) {
      $local_data['image'] = '';
      $local_data['options']['image']['image_del'] = 'on';
    }

    // Champs type lien
    $local_data['sitra_type_link'] = 'SITRA_LOCAL_TYPES:'.$sitra_object['otype']->raw;

    // Coordonnées géodésiques
    if ($sitra_object['ogeoJson']->raw) {
      $geo_datas = explode(';',$sitra_object['ogeoJson']->raw);
      if ($geo_datas[0] && $geo_datas[1]) {
        $local_data['latitude'] = $geo_datas[0];
        $local_data['longitude'] = $geo_datas[1];
      }
    }

    // Prochaine date d'ouverture
    if (!empty($sitra_object['odatesOuverture']->raw)) {
      $local_data['prochaineDateOuverture'] = $this->getNextDateValue($sitra_object);
    }

    return $local_data;
  }

  /**
   * Retourne une image pour l'été
   * @param $sitra_object
   * @return string: filename
   * @throws NotFoundException
   */
  protected function getSummerImage($sitra_object) {
    if (($oimagePrincipale_filename = $sitra_object['oimagePrincipale']->filename)) {
      if (file_exists($oimagePrincipale_filename)) {
        return $oimagePrincipale_filename;
      }
    }
    return $this->getFirstIllustration($sitra_object);
  }

  /**
   * Retourne la première image de la liste d'images du champs "illustrations"
   * @param $sitra_object
   * @return string: filename
   * @throws NotFoundException
   */
  protected function getFirstIllustration($sitra_object) {
    foreach ($sitra_object['oillustrations']->collection as $media_field) {
      $filename = $media_field->link['oFichiers']->filename;
      if (file_exists($filename)) {
        return $filename;
      }
    }

    throw new \Seolan\Core\Exception\NotFound();
  }

  public function getElementRefForLocalTableAndField($table, $field) {
    // TODO: Mettre un cache sur le couple de paramètres (Le cache Général de XDb peut etre desactivé)
    return getDb()->fetchCol("SELECT SITRA_ELEMENTREFERENCE_ID FROM SITRA_CORRESPONDANCE WHERE"
			     ."  (LOCAL_TABLE_NAME = '*' OR LOCAL_TABLE_NAME = :table)"
			     ."  AND (SITRA_TYPE = '*' OR LOCAL_FIELD_NAME = :field)"
			     ."  AND (SPECIAL_ACTION IS NULL OR SPECIAL_ACTION = '')",
			     array(
				   ':table' => $table,
				   ':field' => $field));
  }

}
