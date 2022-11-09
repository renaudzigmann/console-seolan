<?php
namespace Seolan\Module\Sitra;
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }
  function istep1() {
    parent::istep1();
    $this->_options->setOpt('Identifiant Sitra', 'idSitra', 'text');
    $this->_options->setOpt('Clé de l\'API', 'apiKey', 'text');
    $this->_options->setOpt('Prefix des tables', 'dbPrefix', 'text');
    $this->_module->dbPrefix = 'SIT_';
    $this->_options->setOpt('Répertoire de schemas.zip', 'schemasZipFolder', 'text', null, TZR_VAR2_DIR . 'sitra/schemas/');
    $this->_module->schemasZipFolder = TZR_VAR2_DIR . 'sitra/schemas/';
    $this->_options->setOpt('Répertoire des schemas', 'schemasFolder', 'text', null, TZR_VAR2_DIR . 'sitra/schemas/');
    $this->_module->schemasFolder = TZR_VAR2_DIR . 'sitra/schemas/apidae-sit-schemas-main/v002/export/output/';
    $this->_options->setOpt('Répertoire des exports', 'importFolder', 'text', null, TZR_VAR2_DIR . 'sitra/exports/');
    $this->_module->importFolder = TZR_VAR2_DIR . 'sitra/exports/';
    $this->_options->setOpt('Url de récupération des schémas', 'schemasURL', 'text');
    $this->_module->schemasURL = 'https://github.com/apidae-tourisme/apidae-sit-schemas/archive/refs/heads/main.zip';
    $this->_options->setOpt('Préférer les sous fiches', 'wantSubFiles', 'boolean', null, 0);
    $this->_options->setComment('Les sous objets multiples sont traités en liens ou en sous fiches', 'wantSubFiles');
    $this->_module->wantSubFiles = 0;
    $this->_options->setOpt('Moins de tables (si sous fiches)', 'wantLessFiles', 'boolean', null, 1);
    $this->_options->setComment('Les sous objets uniques sont inclus dans la table principale', 'wantLessFiles');
    $this->_module->wantLessFiles = 1;
    $this->_options->setOpt('Ajouter les modules de consolidation', 'addConsolidation', 'boolean');
  }
  function iend() {
    $this->_module->table = $this->_module->dbPrefix.'OBJETSTOURISTIQUES';
    \Seolan\Model\DataSource\Table\Table::procNewSource(array(
      'translatable' => true,
      'auto_translate' => true,
      'btab' => $this->_module->table,
      'bname' => array(TZR_DEFAULT_LANG => $this->_module->group . ' - Objets Touristiques'),
      'publish' => false,
      'own' => false
    ));
    // La table est trop grande pour innodb, passage en MyISAM
    getDB()->execute('ALTER TABLE '.$this->_module->dbPrefix.'OBJETSTOURISTIQUES'.' ENGINE=MYISAM');
    $ret = parent::iend();

    $this->createConsolidationModule();
    return $ret;
  }
  
  function createConsolidationModule() {
    if(!$this->_module->addConsolidation) {
      return false;
    }

    $params = array(
      'SITRA_CONF_TABLE' => array(
        'label' => $this->_module->group . ' - Correspondance de tables',
        'fields' => array(
          array(
            'field' => 'TABLE_NAME',
            'label' => [TZR_DEFAULT_LANG => 'Table'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '128',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
            'published' => '1',
          ),
          array(
            'field' => 'TYPES',
            'label' => [TZR_DEFAULT_LANG => 'Types'],
            'ftype' => '\Seolan\Field\StringSet\StringSet',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
            'multivalued' => '1',
            'published' => '1',
            'options' => ['theclass' => '\Seolan\Module\Sitra\SitraTypeSetDef']
          )
        )
      ),
      'SITRA_LOCAL_TYPES' => array(
        'label' => $this->_module->group . ' - Types de fiches',
        'fields' => array(
          array(
            'field' => 'code_sitra',
            'label' => [TZR_DEFAULT_LANG => 'Code sitra'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '32',
            'browsable' => '1',
            'queryable' => '1',
          ),        
          array(
            'field' => 'title',
            'label' => [TZR_DEFAULT_LANG => 'Titre'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '255',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
            'published' => '1',
          ),
          array(
            'field' => 'marker',
            'label' => [TZR_DEFAULT_LANG => 'Marker'],
            'ftype' => '\Seolan\Field\Image\Image',
            'fcount' => '0',
            'browsable' => '1',
            'queryable' => '1',
          ),  
          array(
            'field' => 'categ',
            'label' => [TZR_DEFAULT_LANG => 'Catégorie'],
            'ftype' => '\Seolan\Field\Text\Text',
            'fcount' => '100',
            'browsable' => '1',
            'queryable' => '2',
          ),
        ),
      ),
      'SITRA_LOCAL_SEARCH_FIELDS' => array(
        'label' => $this->_module->group . ' - Champs de recherche',
        'fields' => array(
          array(
            'field' => 'code',
            'label' => [TZR_DEFAULT_LANG => 'Code'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '20',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
          ),        
          array(
            'field' => 'title',
            'label' => [TZR_DEFAULT_LANG => 'Titre'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '255',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
            'published' => '1',
          ),
          array(
            'field' => 'elements_reference',
            'label' => [TZR_DEFAULT_LANG => 'Éléments de référence associés'],
            'ftype' => '\Seolan\Field\Link\Link',
            'fcount' => '0',
            'browsable' => '1',
            'queryable' => '1',
            'multivalued' => '1',
            'target' => $this->_module->dbPrefix."ELEMENTSREFERENCE",
          ),  
          array(
            'field' => 'file_field',
            'label' => [TZR_DEFAULT_LANG => 'Champ correspondant'],
            'ftype' => '\Seolan\Field\DataSourceField\DataSourceField',
            'fcount' => '100',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
            'target' => $this->_module->dbPrefix."OBJETSTOURISTIQUES",
          ),
        ),
      ),
      'SITRA_CORRESPONDANCE' => array(
        'label' => $this->_module->group . ' - Correspondance de champs',
        'fields' => array(
          array(
            'field' => 'LOCAL_TABLE_NAME',
            'label' => [TZR_DEFAULT_LANG => 'Nom de la table locale'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '128',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
          ),        
          array(
            'field' => 'SITRA_TYPE',
            'label' => [TZR_DEFAULT_LANG => 'Type de fiche sitra'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '128',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
            'published' => '1',
          ),
          array(
            'field' => 'SITRA_TABLE_NAME',
            'label' => [TZR_DEFAULT_LANG => 'Nom de la table sitra'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '128',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
          ),  array(
            'field' => 'SITRA_NAME',
            'label' => [TZR_DEFAULT_LANG => 'Type de fiche sitra'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '128',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
            'published' => '1',
          ),
          array(
            'field' => 'LOCAL_FIELD_NAME',
            'label' => [TZR_DEFAULT_LANG => 'Nom du champ consolidé'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '128',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
            'published' => '1',
          ),
          array(
            'field' => 'SITRA_FIELD_NAME',
            'label' => [TZR_DEFAULT_LANG => 'Nom du champ sitra'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '128',
            'compulsory' => '1',
            'browsable' => '1',
            'queryable' => '1',
            'published' => '1',
          ),
          array(
            'field' => 'SPECIAL_ACTION',
            'label' => [TZR_DEFAULT_LANG => 'Action combiné sur champ consolidé'],
            'ftype' => '\Seolan\Field\StringSet\StringSet',
            'browsable' => '1',
            'queryable' => '1',
          ),
          array(
            'field' => 'ASPECT',
            'label' => [TZR_DEFAULT_LANG => 'Aspect'],
            'ftype' => '\Seolan\Field\ShortText\ShortText',
            'fcount' => '128',
            'browsable' => '1',
            'queryable' => '1',
          ),
          array(
            'field' => 'LINK_TABLE',
            'label' => [TZR_DEFAULT_LANG => 'Transformation du lien vers table'],
            'ftype' => '\Seolan\Field\DataSource\DataSource',
            'fcount' => '20',
            'browsable' => '1',
            'queryable' => '1',
          ),
          array(
           'field' => 'SITRA_ELEMENTREFERENCE_ID',
           'label' => [TZR_DEFAULT_LANG => 'Element référence ID'],
           'ftype' => '\Seolan\Field\Real\Real',
           'fcount' => '20',
           'browsable' => '0',
           'queryable' => '1',
          ),
          array(
           'field' => 'PUBLISH',
           'label' => [TZR_DEFAULT_LANG => ''],
           'ftype' => '\Seolan\Field\Boolean\Boolean',
           'fcount' => '20',
           'browsable' => '1',
           'queryable' => '1',
          )         
        )
      ),
      'SITRA_JSON_OBJECTS_TABLE' => array(
        'label' => $this->_module->group . ' - Correspondance des objets JSON APIDAE',
        'fields' => array(array(
          'field' => 'SITRA_TABLE_NAME',
          'label' => [TZR_DEFAULT_LANG => 'Nom de la table sitra'],
          'ftype' => '\Seolan\Field\ShortText\ShortText',
          'fcount' => '128',
          'compulsory' => '1',
          'browsable' => '1',
          'queryable' => '1',
          ),
          array(
          'field' => 'SITRA_FIELD_NAME',
          'label' => [TZR_DEFAULT_LANG => 'Nom du champ sitra'],
          'ftype' => '\Seolan\Field\ShortText\ShortText',
          'fcount' => '128',
          'compulsory' => '1',
          'browsable' => '1',
          'queryable' => '1',
          'published' => '1',
          ),
          array(
          'field' => 'SITRA_OBJECT_NAME',
          'label' => [TZR_DEFAULT_LANG => 'Table'],
          'ftype' => '\Seolan\Field\ShortText\ShortText',
          'fcount' => '128',
          'compulsory' => '1',
          'browsable' => '1',
          'queryable' => '1',
          'published' => '1',
          ),
          array(
          'field' => 'SITRA_SCHEMA_NAME',
          'label' => [TZR_DEFAULT_LANG => 'Table'],
          'ftype' => '\Seolan\Field\ShortText\ShortText',
          'fcount' => '128',
          'compulsory' => '1',
          'browsable' => '1',
          'queryable' => '1',
          'published' => '1',
          ),
        )
      ),
      'SITRA_EXPORT_CACHE' => array(
       'label' => $this->_module->group . ' - Cache ',
       'fields' => array(
         array(
           'field' => 'SITRA_FORM',
           'label' => [TZR_DEFAULT_LANG => 'Formulaire'],
           'ftype' => '\Seolan\Field\Text\Text',
           'compulsory' => '1',
           'browsable' => '1',
           'queryable' => '1',
           'published' => '1',
         ),
         array(
           'field' => 'STATUS',
           'label' => [TZR_DEFAULT_LANG => 'Status'],
           'ftype' => '\Seolan\Field\ShortText\ShortText',
           'fcount' => '128',
           'compulsory' => '1',
           'browsable' => '1',
           'queryable' => '1',
           'published' => '1',
         ),
         array(
           'field' => 'APIDAE_ERROR',
           'label' => [TZR_DEFAULT_LANG => 'Erreur'],
           'ftype' => '\Seolan\Field\ShortText\ShortText',
           'fcount' => '128',
           'compulsory' => '1',
           'browsable' => '1',
           'queryable' => '1',
           'published' => '1',
         ),
         array(
           'field' => 'APIDAE_MESSAGE',
           'label' => [TZR_DEFAULT_LANG => 'Message APIDAE'],
           'ftype' => '\Seolan\Field\Text\Text',
           'compulsory' => '1',
           'browsable' => '1',
           'queryable' => '1',
           'published' => '1',
         )
       )
      )
    );

    foreach($params as $table => $param) {
      if(!\Seolan\Core\DataSource\DataSource::sourceExists($table)) {
        \Seolan\Model\DataSource\Table\Table::procNewSource(array(
          'btab' => $table,
          'bname' => array('FR' => $param['label']),
          'translatable' => false,
          'auto_translate' => false,
          'publish' => false,
          'own' => false,
          'options' => array()
        ));
        $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$table);
        foreach($param['fields'] as $field) {
          $ds->procNewField($field);
        }
      }
      $modUsingTable = \Seolan\Core\Module\Module::modulesUsingTable($table, true, false, false, true);
      if(empty($modUsingTable)) {
        $mod = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
        $mod->_module->modulename = $param['label'];
        $mod->_module->group = $this->_module->group . ' - Consolidation';
        $mod->_module->table = $table;
        $mod->iend();
      }
    }

    // Mise à jour de la table SITRA_LOCAL_TYPES
    \Seolan\Library\Upgrades::editTableOptions("SITRA_LOCAL_TYPES", "oidstruct1", "title");
    $types = array(
      array('SITRA_LOCAL_TYPES:COMMERCE_ET_SERVICE','FR','COMMERCE_ET_SERVICE', 'Commerce et service', 'Services pratiques'),
      array('SITRA_LOCAL_TYPES:RESTAURATION','FR','RESTAURATION', 'Restauration', 'Manger, déguster'),
      array('SITRA_LOCAL_TYPES:DEGUSTATION','FR','DEGUSTATION', 'Degustation', 'Manger, déguster'),
      array('SITRA_LOCAL_TYPES:HOTELLERIE','FR','HOTELLERIE', 'Hotellerie', 'Dormir'),
      array('SITRA_LOCAL_TYPES:HEBERGEMENT_LOCATIF','FR','HEBERGEMENT_LOCATIF', 'Hebergement locatif', 'Dormir'),
      array('SITRA_LOCAL_TYPES:HEBERGEMENT_COLLECTIF','FR','HEBERGEMENT_COLLECTIF', 'Hebergement collectif', 'Dormir'),
      array('SITRA_LOCAL_TYPES:HOTELLERIE_PLEIN_AIR','FR','HOTELLERIE_PLEIN_AIR', 'Hotellerie plein air', 'Dormir'),
      array('SITRA_LOCAL_TYPES:ACTIVITE','ACTIVITE','FR', 'Activité', 'A voir, à faire'),
      array('SITRA_LOCAL_TYPES:FETE_ET_MANIFESTATION','FR','FETE_ET_MANIFESTATION', 'Fête et manifestation', 'Agenda'),
      array('SITRA_LOCAL_TYPES:SEJOUR_PACKAGE','FR','SEJOUR_PACKAGE', 'Sejour package', 'Séjours'),
      array('SITRA_LOCAL_TYPES:PATRIMOINE_CULTUREL','FR','PATRIMOINE_CULTUREL', 'Patrimoine culturel', 'A voir, à faire'),
      array('SITRA_LOCAL_TYPES:PATRIMOINE_NATUREL','FR','PATRIMOINE_NATUREL', 'Patrimoine naturel', 'A voir, à faire'),
      array('SITRA_LOCAL_TYPES:EQUIPEMENT','EQUIPEMENT','FR', 'Equipement', 'Services pratiques'),
      array('SITRA_LOCAL_TYPES:DOMAINE_SKIABLE','FR','DOMAINE_SKIABLE', 'Domaine skiable', 'A voir, à faire'),
      array('SITRA_LOCAL_TYPES:TERRITOIRE','FR','TERRITOIRE', 'Terriroire', ''),
      array('SITRA_LOCAL_TYPES:STRUCTURE','FR','STRUCTURE', 'Structure', ''),
    );
    foreach($types as $type) {
      getDB()->execute('insert into SITRA_LOCAL_TYPES (KOID,LANG,code_sitra, title, categ) values (?, ?, ?, ?, ?)', $type);
    }

    // Ajout des valeurs de l'ensemble de chaîne pour SITRA_CORRESPONDANCE
    $stringSets = array(
      array('LINKS_FUSION', 'SITRA_CORRESPONDANCE', 'SPECIAL_ACTION', TZR_DEFAULT_LANG, 'Fusion de liens', '1'),
      array('CONSO_KEY_VALUE', 'SITRA_CORRESPONDANCE', 'SPECIAL_ACTION', TZR_DEFAULT_LANG, 'Consolidation label de champs/valeur des champs', '2'),
    );
    foreach($stringSets as $stringSet) {
      getDB()->execute('insert into SETS (SOID, STAB, FIELD, SLANG, STXT, SORDER) values (?, ?, ?, ?, ?, ?)', $stringSet);
    }
  }

}


?>
