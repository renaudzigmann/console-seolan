<?php
namespace Seolan\Module\Sitra;
/**
 * Module d'import des données APIDAE (ex-SITRA)
 *
 * Configuration APIDAE :
 *   Outils de dev     -> http://dev.apidae-tourisme.com/
 *   Accès aux données -> https://base.apidae-tourisme.com/diffuser/projet/
 */
class Sitra extends \Seolan\Module\Table\Table {
  public $idSitra;
  public $dbPrefix = 'SIT_';
  public $importFolder;
  public $schemasFolder;
  public $schemasURL = 'http://dev.apidae-tourisme.com/wp-content/uploads/2017/11/2017-11-20-apidae-schemas-export-v2.zip';
  public $wantSubFiles = false;
  public $wantLessFiles = true;
  public $loginOpenSystem = '';
  public $passwordOpenSystem = '';
  protected $cr = ''; // report
  protected $deletedIds = array(); // les id à supprimer
  private $objects = array(); // container des objets importés
  private $localMedias = array(); // container des medias déjà présent localement
  private $linkedObjects = array(); // container des objets liés
  protected $schemas = array(); // container des schemas
  protected $jsonSchemas = array();
  protected $_fields = array(); // container des champs pour l'analyse schemas
  protected $_subTables = array(); // container des sous fiches pour l'analyse schemas
  protected $_dataSources = array(); // cache des datasouces
  protected $sitraNomenclature = array(
    'Communes'          => 'communes.json',
    'CriteresInternes'  => 'criteres_internes.json',
    'ElementsReference' => 'elements_reference.json',
    'Territoires'       => 'territoires.json',
  );
    protected $sitraSchemas = array(
    'Communes'           => 'exportCommunes.schema',
    'CriteresInternes'   => 'exportCriteresInternes.schema',
    'ElementsReference'  => 'exportElementsReference.schema',
    'ObjetsTouristiques' => 'exportObjetsTouristiques.schema',
    'Selections'         => 'exportSelections.schema',
    'Territoires'        => 'exportTerritoire.schema',
  );
  static protected $mainTables = array('Communes', 'CriteresInternes', 'ElementsReference', 'Territoires', 'ObjetsTouristiques');

  protected $sitra_fiche_xds;
  protected $correspondance;
  protected $openSystemApis = array();
  protected $selections_sync;
  protected $nomenclatureObjects = [];

  function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->table = $this->getTableForSchema('ObjetsTouristiques');
    foreach (array('schemasFolder', 'importFolder') as $opt) {
      if (substr($this->$opt, -1) != '/')
        $this->$opt .= '/';
    }
    $this->correspondance = new \Seolan\Module\Sitra\SitraCorrespondance();
    if($this->loginOpenSystem && $this->passwordOpenSystem) {
      $logins = explode('||', $this->loginOpenSystem);
      $passwords = explode('||', $this->passwordOpenSystem);
      foreach($logins as $key => $val) {
        $this->openSystemApis[] = new \Seolan\Module\Sitra\OpenSystemApis($logins[$key], $passwords[$key]);
      }
    }
    $module_local_sitra_files = \Seolan\Core\Ini::get('module_local_sitra_files');
    if($module_local_sitra_files) {
      $this->selections_sync = new \Seolan\Module\Sitra\SelectionsSync(array(\Seolan\Core\Module\Module::objectFactory($module_local_sitra_files)));
    }

  }

  public function initOptions() {
    parent::initOptions();
    $group = 'Sitra';
    $this->_options->setOpt('Identifiant Sitra', 'idSitra', 'text', null, null, $group);
    $this->_options->setOpt('Clé de l\'API', 'apiKey', 'text', null, null,  $group);
    $this->_options->setOpt('Prefix des tables', 'dbPrefix', 'text', null, null,  $group);
    $this->_options->setRO('dbPrefix');
    $this->_options->setOpt('Répertoire de schema.zip', 'schemasZipFolder', 'text', null, TZR_VAR2_DIR . 'sitra/schemas/', $group);
    $this->_options->setOpt('Répertoire des schemas', 'schemasFolder', 'text', null, TZR_VAR2_DIR . 'sitra/schemas/apidae-sit-schemas-main/v002/export/output/', $group);
    $this->_options->setOpt('Répertoire des exports', 'importFolder', 'text', null, TZR_VAR2_DIR . 'sitra/exports/', $group);
    $this->_options->setOpt('Url de récupération des schémas', 'schemasURL', 'text', null, null, $group);
    $this->_options->setOpt('Préférer les sous fiches', 'wantSubFiles', 'boolean', null, null, $group);
    $this->_options->setRO('wantSubFiles');
    $this->_options->setOpt('Moins de tables (si sous fiches)', 'wantLessFiles', 'boolean', null, null, $group);
    $this->_options->setRO('wantLessFiles');
    $group = 'OpenSystem';
    $this->_options->setOpt('Login OpenSystem', 'loginOpenSystem', 'text', null, null, $group);
    $this->_options->setOpt('Password OpenSystem', 'passwordOpenSystem', 'text', null, null, $group);
  }

  /// Appelé après le chargement des options du module
  public function load($moid) {
    parent::load($moid);
    $this->_options->setLabel('<a href="http://base.apidae-tourisme.com/diffuser/projet/'.$this->idSitra.'" target="_blank">Clé de l\'API</a>', 'apiKey');
    $this->_options->setComment('Allez sur SITRA puis cliquez sur le projet concerné pour trouver votre clef d\'API (Onglet Informations générales / Description)', 'apiKey');
  }

  function secGroups($function, $group=NULL) {
    $g = array(
      'viewStructure' => array('ro', 'rw', 'rwv','admin'),
      'notifyExport' => array('none', 'ro', 'rw', 'rwv', 'admin'),
      'import' => array('rwv','admin'),
      'initDb' => array('admin'),
      'importNomenclature' => array('admin'),
      'importFromArchive' => array('admin'),
      'procImportFromArchive' => array('admin'),
      'getImportStatus' => array('admin'),
      'getSchemasFiles' => array('admin'),
      'displayObject' => array('ro', 'rw', 'rwv','admin'),
      'displayOpenSystemId' => array('rwv','admin'),
      'consolidateOne' => array('rwv','admin'),
      'consolidateAll' => array('rwv','admin'),
      'runImport' => array('rwv','admin'),
      'postImportTask' => array('rwv','admin'),
    );
    if(isset($g[$function])) {
      if(!empty($group))
        return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  function getInfos($ar=NULL) {
    $ret['infos']['idSitra'] = (object)array(
      'label' => 'Identifiant Sitra',
      'html' => $this->idSitra
    );
    $ret['infos']['notifyURL'] = (object)array(
      'label' => "URL de notification d'export",
      'html' => $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'moid='.$this->_moid.'&function=notifyExport'
    );
    $stmt = getDB()->select('select count(distinct koid) from '.$this->table.' group by linkedObject order by linkedObject');
    if ($stmt && $nbObjects = $stmt->fetchAll(\PDO::FETCH_COLUMN)) {
      $ret['infos']['ot'] = (object)array(
        'label' => 'Objets Touristiques',
        'html' => $nbObjects[0]
      );
      $ret['infos']['otl'] = (object)array(
        'label' => 'Objets Touristiques Liés',
        'html' => $nbObjects[1]
      );
    }
    return \Seolan\Core\Shell::toScreen1('br',$ret);
  }

  protected function _actionlist(&$my, $alfunction=true) {
    parent::_actionlist($my);
    //
    if ($this->secure('','getSchemasFiles')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'getSchemasFiles', 'Récupérer les schémas',
                            '&moid='.$this->_moid.'&_function=getSchemasFiles&_next=refresh', 'getSchemasFiles');
      $o1->group = 'edit';
      $o1->menuable = true;
      $my['getSchemasFiles'] = $o1;
    }
    if ($this->secure('','viewStructure')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'viewStructure', 'Voir la structure',
                            '&moid='.$this->_moid.'&_function=viewStructure', 'viewStructure');
      $o1->group = 'edit';
      $o1->menuable = true;
      $my['viewStructure'] = $o1;
    }
    if ($this->secure('','initDb')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'initDb', 'Vérification structure base',
                            '&moid='.$this->_moid.'&_function=initDb&_next=refresh', 'initDb');
      $o1->group = 'edit';
      $o1->menuable = true;
      $my['initDb'] = $o1;
    }
    if ($this->secure('','importNomenclature')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'import', 'Importer la nomenclature',
                            '&moid='.$this->_moid.'&_function=importNomenclature&_next=refresh', 'import');
      $o1->group = 'edit';
      $o1->menuable = true;
      $my['importNomenclature'] = $o1;
    }
    if ($this->secure('','import')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'import', 'Lancer l\'import',
                            '&moid='.$this->_moid.'&_function=import&_next=refresh', 'import');
      $o1->group = 'edit';
      $o1->menuable = true;
      $my['import'] = $o1;
    }
    if ($this->secure('','importFromArchive')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'importFromArchive', "Ré-importer une archive",
                            '&moid='.$this->_moid.'&_function=importFromArchive', 'importFromArchive');
      $o1->group = 'edit';
      $o1->menuable = true;
      $my['importFromArchive'] = $o1;
    }
    if ($this->secure('','consolidateAll')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'consolidateAll', 'Tout consolider',
        '&moid='.$this->_moid.'&_function=consolidateAll&template=Core.message.html', 'consolidateAll');
      $o1->group = 'edit';
      $o1->menuable = true;
      $my['consolidateAll'] = $o1;
    }
    $oid = @$_REQUEST['oid'];
    if (is_string($oid) && $this->secure($oid,'consolidateOne')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'consolidateOne', 'Consolider cette fiche',
        '&moid='.$this->_moid.'&_function=consolidateOne&oid='.$oid.'&template=Core.message.html', 'consolidateOne');
      $o1->group = 'edit';
      $o1->menuable = true;
      $my['consolidateOne'] = $o1;
    }
    if (is_string($oid) && $this->secure($oid,'displayOpenSystemId')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'displayOpenSystemId', 'Voir l\'identifiant OpenSystem de cette fiche',
        '&moid='.$this->_moid.'&_function=displayOpenSystemId&oid='.$oid.'&template=Core.message.html', 'displayOpenSystemId');
      $o1->group = 'edit';
      $o1->menuable = true;
      $my['displayOpenSystemId'] = $o1;
    }
  }

  function browse_actions(&$r,$assubmodule=false, $ar=NULL) {
    parent::browse_actions($r,$assubmodule, $ar);
    foreach($r['lines_o_id'] as $i => $id) {
      $r['actions'][$i][] = '<a class="cv8-editaction" target="_sitra" href="http://base.apidae-tourisme.com/diffuser/dev-tools/serialisation/json-export/?id='.$id->raw.'&project='.$this->idSitra.'" title="Voir le json"><span class="glyphicon csico-arrow_right"></span></a>';
      $r['actions'][$i][] = '<a class="cv8-editaction" target="_sitraFile" href="http://base.apidae-tourisme.com/consulter/objet-touristique/'.$id->raw.'" title="Voir la fiche Sitra"><span class="glyphicon csico-log-in"></span></a>';
    }
  }

  /// fonction appelée par Sitra pour indiquer qu'un export est disponible
  // http://www.sitra-rhonealpes.com/wiki/index.php/Exports_Sitra2#Notification_par_web_service
  public function notifyExport() {
    \Seolan\Core\Logs::critical(get_class()."::notifyExport ".var_export($_POST, 1));
    if ($_POST['siteWebId'] != $this->idSitra && $_POST['projetId'] != $this->idSitra)  {
      \Seolan\Core\Logs::critical(get_class()."::notifyExport siteWebId error :".$_POST['siteWebId'].'!='.$this->idSitra.' | '.$_POST['projetId'].'!='.$this->idSitra);
      die;
    }
    if ($_POST['statut'] == 'ERROR') {
      // notifier ?
      \Seolan\Core\Logs::critical(get_class()."::notifyExport error failed");
      die;
    }
    $scheduler = new \Seolan\Module\Scheduler\Scheduler();
    $rootUser = new \Seolan\Core\User('root');
    $more = array(
      'uid' => $rootUser->uid(),
      'function' => 'runImport',
      'ponctuel' => $_POST['ponctuel'],
      'reinitialisation' => $_POST['reinitialisation'],
      'urlRecuperation' => $_POST['urlRecuperation'],
      'urlConfirmation' => $_POST['urlConfirmation'],
    );
    $scheduler->createJob($this->_moid, date('Y-m-d H:i:s'), 'Import Sitra2', $more, '');
    die;
  }

  /// vérification de la structure / schema
  public function initDb() {
    ini_set('max_execution_time', 0);
    global $TZR_LANGUAGES;
    // FIXME Directives dangereuses si jamais la structure de la BDD de la Console Séolan change pour des valeurs plus grandes...
    getDB()->execute("alter table DICT modify FIELD varchar(64)");
    getDB()->execute("alter table MSGS modify FIELD varchar(64)");
    getDB()->execute("alter table AMSG modify MOID varchar(100)");
    $this->log("Verifying structure");
    $this->analyzeSchemas();


    $params = array(
		    'SITRA_EXPORT_CACHE' => array(
						  'label' => $this->_module->group . 'Cache APIDAE',
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
									  'field' => 'STATUS_MESSAGE',
									  'label' => [TZR_DEFAULT_LANG => 'Message APIDAE'],
									  'ftype' => '\Seolan\Field\ShortText\ShortText',
									  'fcount' => '128',
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
    }


    /*if (\Seolan\Core\DataSource\DataSource::sourceExists('SITRA_CORRESPONDANCE')) {
      $fields_correspondance_xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=SITRA_CORRESPONDANCE');
      if (!$fields_correspondance_xds->fieldExists('SITRA_CORRESPONDANCE')) {
	$ret = $fields_correspondance_xds->procNewField(array(
							      'field' => 'SITRA_OBJECT',
							      'label' => [TZR_DEFAULT_LANG => 'Nom complet de l\'objet sitra'],
							      'ftype' => '\Seolan\Field\ShortText\ShortText',
							      'fcount' => '128',
							      'compulsory' => '1',
							      'browsable' => '1',
							      'queryable' => '1',
							      'published' => '1',
							      ));
	$this->log($ret['message']);
      }
      }*/
    //s'assurer qu'on a au moins un champ browsable et un champ publié (liens)
    foreach ($this->_fields as $schema => $_fields) {
      $published = $browsable = 0;
      unset($firstField);
      foreach ($_fields as $access => $field) {
        if (!isset($firstField))
          $firstField = $access;
        $published |= $field['published'];
        $browsable |= $field['browsable'];
      }
      if (!$published)
        $this->_fields[$schema][$access]['published'] = 1;
      if (!$browsable)
        $this->_fields[$schema][$access]['browsable'] = 1;
    }

    // creation des tables /champs
    foreach ($this->_fields as $schema => $_fields) {
      $table = $this->getTableForSchema($schema);
      $translatable = false;
      foreach ($_fields as $access => $field)
        $translatable |= $field['translatable'];
      if (!\Seolan\Core\DataSource\DataSource::sourceExists($table)) {
        $ret = \Seolan\Model\DataSource\Table\Table::procNewSource(array(
          'btab' => $table,
          'bname' => array('FR' => $this->group . ' - ' . $schema),
          'translatable' => $translatable,
          'auto_translate' => true,
          'publish' => false,
          'own' => false,
          'options' => array('oidstruct1' => '_id')
        ));
        if( $table == $this->dbPrefix.'ASPECTS'){
          getDB()->execute('ALTER TABLE '.$this->dbPrefix.'ASPECTS'.' ENGINE=MYISAM');
        }
        $this->log($ret['message']);
      }

      $ds = $this->getDataSource($table);
      $fields_used = array();
      foreach ($_fields as $access => $field) {
        if ($access == 'id') { // id impossible en console
          $field['field'] = '_id';
          $field['ftype'] = '\Seolan\Field\ShortText\ShortText';
          $field['fcount'] = 8;
          $field['browsable'] = 1;
        }
        $this->sanitizeSqlFieldName($field['field']);
        if ($ds->fieldExists($field['field']) && $ds->desc[$field['field']]->ftype != $field['ftype']) {
          $ds->delField(array('field' => $field['field']));
        }
        if (!$ds->fieldExists($field['field'])) {
          if ($field['target'])
            $field['target'] = $this->getTableForSchema($this->shorten($field['target']));
          foreach ($TZR_LANGUAGES as $codeTzr => $codeIso) {
            $field['options']['fgroup'][$codeTzr] = str_replace(' Capacite', '', $field['fgroup']);
          }
          $ret = $ds->procNewField($field);
          $this->log($ret['message']);
        }
        $fields_used[] = $field['field'];
	$corresp = getDb()->fetchRow("SELECT KOID FROM SITRA_JSON_OBJECTS_TABLE WHERE SITRA_FIELD_NAME = ? AND SITRA_TABLE_NAME = ?", array($field['field'], $table));
	if(!$corresp) {
	  $newKoid = 'SITRA_JSON_OBJECTS_TABLE:'.uniqid();
	  getDb()->execute("INSERT INTO SITRA_JSON_OBJECTS_TABLE(KOID, LANG, SITRA_FIELD_NAME, SITRA_TABLE_NAME, SITRA_OBJECT_NAME, SITRA_SCHEMA_NAME) VALUES(?,?,?,?,?,?)", array($newKoid, 'FR', $field['field'],  $table, str_replace('_', '.', $access), $schema ));
	}
      }

      // au moins un module
      $modUsingTable = \Seolan\Core\Module\Module::modulesUsingTable($table, true, false, false, true);
      if (empty($modUsingTable)) {
        $mod = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
        $mod->_module->modulename = $this->group . ' - ' . ucwords(str_replace('_', ' ', preg_replace('/([a-z])([A-Z])/', '\1 \2', $schema)));
        $mod->_module->group = $this->group;
        $mod->_module->table = $table;
        $mod->_module->home = 1;
        $mod->_module->trackchanges = 0;
        $mod->_module->order = isset($fields['ordre']) ? 'ordre' : '';
        $_mods[$schema] = $mod->iend();
      } else
        $_mods[$schema] = key($modUsingTable);
      $fields_used[] = 'UPD';
      $allfields = array_keys($ds->desc);
      $diff_fields = array_diff($allfields, $fields_used);
      if ($diff_fields) {
        // A vérifier en local et dans la doc SITRA : http://dev.apidae-tourisme.com/fr/documentation-technique/migration-v001-a-v002/migration-v1-a-v2
        $this->log("Verify if fields are used for table $table : ".implode(', ', $diff_fields));
      }
    }
    // les modules en sous fiches
    foreach ($this->_subTables as $table => $subTables) {
      $mod = \Seolan\Core\Module\Module::objectFactory($_mods[$table]);
      $mod->procEditProperties(array('options' => array('submodmax' => count($subTables))));
      \Seolan\Core\Module\Module::clearCache();
      $mod = \Seolan\Core\Module\Module::objectFactory($_mods[$table]);
      $i = 1;
      $options = array();
      foreach ($subTables as $_table) {
        $options['ssmod'.$i] = $_mods[$_table];
        $options['ssmodtitle'.$i] = $_table;
        $options['ssmodfield'.$i] = $table;
        $options['ssmoddependent'.$i] = ($_table == 'Selections') ? 0 : 1;
        $i++;
      }
      $mod->procEditProperties(array('options' => $options));
    }
    $this->log("done.");
    setSessionVar('message', nl2br($this->cr));
    \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
  }

  // racourci les nom de champ/table
  private function shorten($value) {
    $value = str_ireplace(array('informationsStructure_', 'informations_', 'geolocalisation_', 'localisation_', 'adresse_', 'presentation_', 'gestion_'), '', $value);
    return preg_replace_callback('/informations([A-Z])/', function($matches) {
      return strtolower($matches[1]);
    } , $value);
  }

  // calcul le group d'un champ
  private function makeGroup($value) {
    return ucwords(str_replace('_', ' ', preg_replace('/([a-z])([A-Z])/', '\1 \2', $value)));
  }

  // les champs texte court
  private function isShortText($key, $value) {
    return in_array($key, array('identifierSitra1', 'identifier', 'formatVersion', 'prenom', 'code', 'siret', 'codeApeNaf', 'rcs', 'numeroAgrementLicence', 'coordonnee', 'departement', 'adresse1', 'adresse2', 'adresse3', 'codePostal', 'bureauDistribution', 'cedex', 'reperePlan', 'copyright')) || 'nom' == substr($key, 0, 3) || 'numero' == substr($key, 0, 6)/* || $value->enum*/;
  }

  // chargement des schemas
  private function loadSchemas() {
    if (!empty($this->schemas))
      return;
    foreach ($this->sitraSchemas as $schemaName => $filename) {
      $filename = $this->schemasFolder . $filename;
      \Seolan\Core\Logs::notice(get_class()."::loadSchema $schemaName from $filename");
      $schema = file_get_contents( $filename );
      $json = json_decode( $schema );
      // En v002 ->items->type n'est plus renseigné et est placé directement dans ->items
      $this->jsonSchemas[$schemaName] = isset($json->items->type) ? $json->items->type : $json->items;
      $this->schemas[$schemaName] = json_encode( $this->jsonSchemas[$schemaName] );
    }
  }

  function analyzeSchemas() {

    $this->loadSchemas();
    // champ de distinction entre les objects des sélections et les objets liés aux premiers
    $this->_fields['ObjetsTouristiques']['linkedObject'] = array('field' => 'linkedObject', 'label' => array('FR' => 'Objet lié'), 'ftype' => '\Seolan\Field\Boolean\Boolean', 'compulsory' => 1, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0, 'fgroup' => '* General');
    foreach ($this->jsonSchemas as $schemaName => $schema){
      foreach ($schema as $object) {
        $this->analyzeSchemaObject($schemaName, $object);
      }
    }
    // les sélections en sous-fiche
    $this->_subTables['ObjetsTouristiques']['Selections'] = 'Selections';
  }

  function analyzeSchemaObject($table, $object, $prefix=null) {
    $table = ucFirst($table);
    foreach ($object->properties as $key => $value) {
      // traduisible
      if ('libelle' == substr($key, 0, 7) && 9 == strlen($key)) {
        $key = 'libelle';
        $translatable = 1;
      } elseif ('traduction' == substr($key, 0, 10)) {
        $key = substr($key, 10);
        $translatable = 1;
      } else
        $translatable = 0;
       $label = ucFirst(preg_replace('/([A-Z])/', ' \1', $key));

      $fgroup = !$prefix ? '* General' : $this->makeGroup($prefix);
      $field = array('label' => array('FR' => $label), 'compulsory' => 0, 'queryable' => 0, 'browsable' => 0, 'translatable' => $translatable, 'multivalued' => 0, 'published' => 0, 'fgroup' => $fgroup);
      switch ($value->type) {
        case 'number' :
          $field['ftype'] = '\Seolan\Field\Real\Real';
          $field['fcount'] = 8;
          $field['options']['decimal'] = 0;
          break;
       case 'integer' :
          $field['ftype'] = '\Seolan\Field\ShortText\ShortText';
          $field['fcount'] = 8;
          $field['options']['edit_format'] = '\d*';
          break;
        case 'string' :
          if ('date' == substr($key, 0, 4) && !@$value->enum) {
            $field['ftype'] = '\Seolan\Field\Date\Date';
          } elseif ($this->isShortText($key, $value)) {
            $field['ftype'] = '\Seolan\Field\ShortText\ShortText';
            $field['fcount'] = 100;
          } else {
            $field['ftype'] = '\Seolan\Field\Text\Text';
            $field['fcount'] = 100;
            $field['options']['arrow2link'] = 0;
          }
          break;
        case 'boolean' :
          $field['ftype'] = '\Seolan\Field\Boolean\Boolean';
          $field['options']['default'] = '2';
          break;
        case 'any' :
        case 'array' :
          // En v002 on peut demander à obtenir toutes les dates d'ouverture dans un champ
          if ($key == 'datesOuverture') {
            $field['ftype'] = '\Seolan\Field\Interval\Interval';
            break;
          }
          // tableau de chaine => shorttext multivalué
          if (count($value->items) == 1 && $value->items->type == 'string') {
            $field['ftype'] = '\Seolan\Field\ShortText\ShortText';
            $field['fcount'] = 255;
            $field['multivalued'] = 1;
            break;
          }
          $field['ftype'] = '\Seolan\Field\Link\Link';
          $field['multivalued'] = 1;
          if (@$value->items->properties->elementReferenceType) { // lien element Reference
            $field['target'] = 'ElementsReference';
          } elseif ($key == 'objetsLies' || $key == 'objetsTouristiques' || @$value->items->id == 'domaineSkiableLien') {
            $field['target'] = 'ObjetsTouristiques';
          } elseif ($key == 'perimetreGeographique') {
            $field['target'] = 'Communes';
          } elseif ($key == 'territoires') {
            $field['target'] = 'Territoires';
          } elseif ($key == 'criteresInternes') {
            $field['target'] = 'CriteresInternes';
          } elseif ($key == 'Fichiers') { // multimedia
            $field['multivalued'] = 0;
            // on garde l'url pour les liens
            $url = $field;
            $url['ftype'] = '\Seolan\Field\Url\Url';
            $url['label']['FR'] = 'Url';
            $this->addField($table, ($prefix?$prefix.'_':'').'url', $url);
            $field['ftype'] = '\Seolan\Field\File\File';
            $field['options']['usealt'] = false;
            $field['options']['usemimehtml'] = true;
            $field['options']['image_geometry'] = '150x150%3E';
          } else {
            $value = $value->items;
            if ($key == 'contactsExternes')
              $value = $value->properties->contact;
            if ($this->wantSubFiles) {
              $this->analyseSubTable($table, ($prefix?$prefix.'_':'').$key, $key, $value);
              continue 2;
            }
            // si on travaille avec des liens, on peux regrouper les medias, les contacts et les moyensCommunication
            if ($value->id == 'multimedia')
              $target = 'Medias';
            else
              $target = str_replace('Externes', '', $key); // contact, moyensCommunication
            if ($key == 'metadonnees' || $key == 'metadonnee')
              $field['target'] = 'MetaDonnees'; // Obligatoire car 2 majuscules et pas simplement ucfirst...
            $target = $this->shorten(ucfirst($target));
            $field['target'] = $target;
            $this->analyzeSchemaObject($target, $value, null);
          }
          break;
        case 'object' :
          if ('geoJson' == $value->id) {
            $field['ftype'] = '\Seolan\Field\GmapPoint\GmapPoint';
            $field['label']['FR'] = 'Géolocalisation';
            $key = 'geoJson';
            break;
          }
          // imagePrincipale n'existe plus en v002
          if ('imagePrincipale' == substr($key, 0, 15)) {
            $field['ftype'] = '\Seolan\Field\File\File';
            $field['translatable'] = 1;
            $fgroup = $this->makeGroup($key);
            $field['fgroup'] = $fgroup;
            $field['options']['usealt'] = false;
            $field['options']['usemimehtml'] = true;
            unset($value->properties->link, $value->properties->traductionFichiers);
            $this->analyzeSchemaObject($table , $value, ($prefix?$prefix.'_':'').$key);
          } elseif (@$value->properties->elementReferenceType) { // lien element Reference
            $field['ftype'] = '\Seolan\Field\Link\Link';
            $field['fcount'] = 0;
            $field['target'] = 'ElementsReference';
          } elseif ('Link' == substr($value->id, -4)) {
            $field['ftype'] = '\Seolan\Field\Link\Link';
            $field['fcount'] = 0;
            $field['target'] = substr($value->id, 0, -4);//'ElementsReference';
            if ('commune' == $field['target'])
              $field['target'] = 'Communes';
            elseif ('objetTouristique' == $field['target'])
              $field['target'] = 'ObjetsTouristiques';
          } elseif ('traductionLibelle' == $value->id) {
            if ($this->isShortText($key, $value))
              $field['ftype'] = '\Seolan\Field\ShortText\ShortText';
            else
              $field['ftype'] = '\Seolan\Field\Text\Text';
            $field['fcount'] = 100;
            $field['translatable'] = 1;
          } elseif (isset($value->properties)) {
            if ($this->wantSubFiles && !$this->wantLessFiles) {
              $this->analyseSubTable($table, ($prefix?$prefix.'_':'').$key, $key, $value);
            } else { // on reste dans la même table
              if ($table == 'Contacts' && $key == 'contact') // contactsExternes
                $value = $value->properties;
              $this->analyzeSchemaObject($table , $value, ($prefix?$prefix.'_':'').$key);
            }
            continue 2;
          } else {
            echo "$table $key unknow object<br>";
          }
          break;
        default:
            echo "$table $key {$value->type} found<br>";
            print_r($value);
      }

      $this->addField($table, ($prefix?$prefix.'_':'').$key, $field);
    }
  }

  function analyseSubTable($table, $key, $target, $value) {
    $target = ucFirst($this->shorten($target));
    $table  = $this->shorten($table);
    $this->analyzeSchemaObject($target , $value);
    // ajout du champ lien vers parent
    $this->addField($target, $table, array('ftype' => '\Seolan\Field\Link\Link', 'target' => $table, 'label' => array('FR' => ucFirst(preg_replace('/([A-Z])/', ' \1', $table))), 'compulsory' => 1, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0, 'fgroup' => '* General', 'isLink' => 1));
    $this->_subTables[$table][$key] = $this->shorten($target);
  }

  function addField($table, $key, $field) {
    static $_fieldNames = array(); // nom de champs utilisés
    $table = $this->shorten($table);
    if (!isset($this->_fields[$table]))
      $this->_fields[$table] = array();
    if (in_array($key, array('nom', 'code', 'libelle', 'coordonnee')) || $field['ftype'] == '\Seolan\Field\File\File')
      $field = array_merge($field, array('queryable' => 1, 'browsable' => 1, 'published' => 1));
    $field['options']['listbox'] = 0;

    $field['field'] = $this->shorten($key);
    if (isset($this->_fields[$table][$key]) && $this->_fields[$table][$key] != $field)
      $this->log("conflict for $table [$key]\n".print_r($this->_fields[$table][$key],1)."\n".print_r($field,1));
    if (@$field['isLink']) // lien parent
      $this->_fields[$table] = array_merge(array($key => $field), $this->_fields[$table]);
    else
      $this->_fields[$table][$key] = $field;
  }


  function viewStructure($ar) {
    $this->loadSchemas();
    foreach ($this->jsonSchemas as $schemaName =>  $json) {
      $this->schemas[$schemaName.'_simple'] = json_encode ( $this->simplify($json) );
    }
    \Seolan\Core\Shell::toScreen1('sit', $r = array('schemas' => $this->schemas));
    \Seolan\Core\Shell::setTemplates('Module/Sitra.viewStructure.html');
  }

  private function simplify($json) {
    if (is_array($json)) {
      foreach ($json as $elem)
        $ret[$elem->id] = $this->simplify($elem);
      return $ret;
    }
    if (is_object($json) && isset($json->properties)) {
      foreach ($json->properties as $key => $value)
        $ret->$key = $this->simplify($value);
      return $ret;
    }
    if (is_object($json)) {
      $keys = get_object_vars($json);
      unset($keys['required']);
      if (count($keys) == 1 && $keys['type'])
        return $json->type;
    }
    return $json;
  }

  // chargement des éléments de référence
  function importNomenclature() {
    ini_set('max_execution_time', 0);
    $this->analyzeSchemas();
    $this->loadNomenclatureObjects();
    foreach ($this->nomenclatureObjects as $nomenclature => $objects) {
      \Seolan\Core\Logs::notice(get_class()."::importNomenclature $nomenclature");
      foreach ($objects as $object) {
        $this->importObject($nomenclature, $object);
      }
    }
  }

  // Trace les évènements de l'import
  function log($message, $put_in_cr = true) {
    if ($put_in_cr) {
      $this->cr.= "$message\n";
      \Seolan\Core\Logs::notice('SITRA_IMPORT', $message);
    } else {
      \Seolan\Core\Logs::debug('SITRA_IMPORT '.$message);
    }
  }

  /// Met à jour le status d'import
  public function setImportStatus($status, $details = []) {
    $this->_setSession('importStatus', [
      'status' => $status,
      'details' => $details,
    ]);
    session_write_close();
    sessionStart();
  }

  /// Retourne le statut de l'import au format JSON (appel AJAX)
  public function getImportStatus($ar) {
    returnJson($this->_getSession('importStatus'));
  }

  // fonction appelée par le scheduler
  function runImport($scheduler, $o, $more) {
    $this->setImportStatus('running');
    // Vérification des paramètres de traçabilité du module SITRA
    if ($more->reinitialisation == 'true' && $this->trackchanges) {
      $this->trackchanges = false;
      $this->log("Le traçage des changements sur les fiches SITRA a été désactivé pour accélérer la réinitiatisation de toutes les fiches SITRA.");
    } elseif ($this->trackchanges) {
      $this->log("AVERTISSEMENT: Les modifications des fiches SITRA sont actuellement enregistrées dans la table Séolan des LOGS. ".
        "Celà réduit considérablement les performances de l'import ! Pour changer ce paramètre, allez dans les propriétés ".
        "du module SITRA et mettez à \"Non\" l'option \"GARDER TRACE DES MODIFICATIONS DE VALEURS\".");
    }
    $this->log("Import files:");

    try {
      if ( $this->getImportFiles($more) ) {
        $this->log("Running Import:");
        // On importe la nomenclature dans tous les cas car la structure des territoires peut changer
        $this->importNomenclature();
        if ($more->reinitialisation == 'true') {
          $this->clearAll();
        }
        $this->import();
        $this->archiveImportFiles();
        // Confirme à SITRA que l'import s'est bien déroulé une fois tous les fichiers importés
        if($more->urlConfirmation) {
          file_get_contents($more->urlConfirmation);
        }
      }
    } catch(Exception $e) {
      $this->log('EXCEPTION: '.$e->getMessage().' => '.$e->getTraceAsString());
      $this->setImportStatus('error', $e->getMessage());
    }
    if ($scheduler instanceof \Seolan\Module\Scheduler\Scheduler)
      return $this->cr;
    else
      die(nl2br($this->cr));
  }

  // suppresion de toutes les données, sur import 'reinitialisation'
  private function clearAll() {
    $this->log("Import 'reinitialisation', suppression des données");
    $this->analyzeSchemas();
    foreach ($this->_fields as $schema => $_fields) {
      if (in_array($schema, self::$mainTables))
        continue;
      $table = $this->getTableForSchema($schema);
      getDB()->execute("DELETE FROM $table");
    }
    foreach ($this->_subTables as $schema => $subtables) {
      foreach ($subtables as $access => $subTable) {
        $table = $this->getTableForSchema($subTable);
        getDB()->execute("DELETE FROM $table");
      }
    }
    getDB()->execute("DELETE FROM {$this->table}");
  }

  // récuperation du zip sitra
  function getImportFiles($more) {
    @mkdir($this->importFolder, 0755, true);
    \Seolan\Core\Logs::debug('IMPORT= cp '.$more->urlRecuperation.' '.$this->importFolder.'export.zip');
    if (false === copy($more->urlRecuperation, $this->importFolder.'export.zip')) {
      $this->log('Unable to copy ' . $more->urlRecuperation . ' to ' .$this->importFolder . 'export.zip');
      return false;
    }

    $zip = new \ZipArchive;
    $res = $zip->open($this->importFolder . 'export.zip');
    if ($res !== TRUE) {
      $this->log('Unable to open ' . $this->importFolder . 'export.zip => ' . $res);
      return false;
    }
    if (!$zip->extractTo($this->importFolder)) {
      $this->log('Unable to extract to ' . $this->importFolder);
      return false;
    }
    $zip->close();
    return true;
  }

  // archive
  function archiveImportFiles() {
    @mkdir($this->importFolder . '/../archives', 0755, true);
    rename($this->importFolder . '/export.zip', $this->importFolder . '/../archives/export-'.date('Y-m-d_H-i-s').'.zip');
    system('find '.$this->importFolder.' -name "*json" -delete');
  }

  // récuperation des schemas, fonction interactive
  function getSchemasFiles() {
    \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
    @mkdir($this->schemasZipFolder, 0755, true);
    if (false === copy($this->schemasURL, $this->schemasZipFolder.'schemas.zip')) {
      setSessionVar('message', 'Unable to copy ' . $this->schemasURL . ' to ' .$this->schemasZipFolder . 'schemas.zip');
      return false;
    }
    $zip = new \ZipArchive;
    $res = $zip->open($this->schemasZipFolder . 'schemas.zip');
    if ($res !== TRUE) {
      setSessionVar('message', 'Unable to open ' . $this->schemasZipFolder . 'schemas.zip => ' . $res);
      return false;
    }
    if (!$zip->extractTo($this->schemasZipFolder))
      setSessionVar('message', 'Unable to extract to ' . $this->schemasZipFolder);
    $zip->close();
    setSessionVar('message', 'Schémas mis à jour');
  }

  // chargement
  function import($ar=NULL) {

    ini_set('max_execution_time', 0);
    ini_set('memory_limit', '1024M');
    $this->setImportStatus('running', "Initialisation de l'import...");
    $this->analyzeSchemas();
    $this->loadLocalMedias();

    // Suppression des objets
    $step_time = microtime(true);
    $this->deletedOids = array();
    $this->loadDeletedIds();
    $this->setImportStatus('running', 'Suppression des IDs: '.implode(', ', $this->deletedIds));
    foreach ($this->deletedIds as $id) {
      $this->log("deleting object $id");
      $this->deletedOids[] = $this->delObject('ObjetsTouristiques', $id, true, true);
    }
    $this->log(count($this->deletedIds) . " objects deleted in ".(microtime(true) - $step_time)." seconds");

    // Objets ($this->objects)
    $step_time = $start_time = microtime(true);
    $objects = $this->loadObjectsSpecific('objets_modifies/objets_modifies-*.json');
    $nbObjects = count($objects);
    $this->newOids = array();
    foreach ($objects as $i => $object) {
      $object_name = (string) $object->nom->libelleFr;
      $this->setImportStatus('running', [
        'current' => "$object_name [ID=$object->id]",
        'number' => $i+1,
        'total' => $nbObjects,
        'percent' => round((($i+1) / $nbObjects) * 100),
      ]);
      $this->log("importing object {$object->id} (".($i+1)."/$nbObjects) === ".intval(($i+1) / $nbObjects * 100).'%');
      $this->delObject('ObjetsTouristiques', $object, false, false);
      $object->linkedObject = 2;
      $this->newOids[] = $this->importObject('ObjetsTouristiques', $object);
    }
    $this->log(count($objects) . " objects imported in ".(microtime(true) - $step_time)." seconds");
    $objects = NULL;

    // Objets liés ($this->linkedObjects)
    $step_time = microtime(true);
    $objects = $this->loadObjectsSpecific('objets_lies/objets_lies_modifies-*.json');
    $nbObjects = count($objects);
    $this->newLinkedOids = array();
    foreach ($objects as $i => $object) {
      $object_name = (string) $object->nom->libelleFr;
      $this->setImportStatus('running', [
        'current' => "Objet lié : $object_name [ID=$object->id]",
        'number' => $i+1,
        'total' => $nbObjects,
        'percent' => round((($i+1) / $nbObjects) * 100),
      ]);
      $this->log("importing linked objects {$object->id} (".($i+1)."/$nbObjects) === ".intval(($i+1) / $nbObjects * 100).'%');
      $this->delObject('ObjetsTouristiques', $object, false, false);
      $object->linkedObject = 1;
      $this->newLinkedOids[] = $this->importObject('ObjetsTouristiques', $object, null, null);
    }
    $this->log(count($objects) . " linked objects imported in ".(microtime(true) - $step_time)." seconds");
    $objects = NULL;

    // Sélections
    $step_time = microtime(true);
    $this->loadObjectsSelection();
    $this->importSelections();
    $total_time = microtime(true) - $start_time;
    $total_objects = count($this->newOids) + count($this->newLinkedOids);
    $this->log("$total_time seconds for $total_objects objects imported (".($total_time / $total_objects)." seconds per object)");
    $this->setImportStatus('finished');
    try {
      $this->log("postImport begin");
      $this->postImport($ar);
      $this->log("postImport done");
    } catch(Exception $e) {
      $this->log('postImport error '.$e->getMessage());
    }
    if ($_REQUEST['template'] == 'Application/MiniSite/public/templates/index.html') {
      setSessionVar('message', nl2br($this->cr));
      \Seolan\Core\Shell::setTemplates('Core.message.html');
    }
    if ($this->interactive) {
      setSessionVar('message', nl2br($this->cr));
      \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
    }
  }

  /**
   * Fonction pour consolidation
   *
   * @param $ar array Paramètres passés à la procédure d'import
   * @throws Exception
   */
  function postImport($ar) {
    $this->log("create task for postImport");
    $scheduler = new \Seolan\Module\Scheduler\Scheduler();
    $rootUser = new \Seolan\Core\User('root');
    $more = array(
      'uid' => $rootUser->uid(),
      'function' => 'postImportTask',
      'newOids' => $this->encodeOids($this->newOids),
      'newLinkedOids' => $this->encodeOids($this->newLinkedOids),
      'deletedIds' => json_encode($this->deletedIds),
    );
    $scheduler->createJob($this->_moid, date('Y-m-d H:i:s'), 'Consolidation Sitra', $more, '');
  }

  /**
   * Procéssus de consolidation en données locales appelée par le scheduler à la fin de l'import
   */
  public function postImportTask($scheduler, $o, $more) {
    $newOids       = $this->decodeOids($more->newOids);
    $newLinkedOids = $this->decodeOids($more->newLinkedOids);
    $deletedIds    = json_decode($more->deletedIds);

    $this->cr = '';

    // On consolide tout car parfois seuls les identifiants opensystems sont mis à jour et pas la fiche SITRA, l'ID n'est donc pas dans $newOids
    $this->log('Consolidation de toutes les fiches !');
    $all_oids = getDB()->select("select distinct koid from {$this->table} where linkedObject=2 AND type IS NOT NULL")->fetchAll(\PDO::FETCH_COLUMN);
    $this->log('Consolidation de '. count($all_oids) . ' fiches en cours');
    $this->consolidate($all_oids, false);

    if ($deletedIds) {
      $this->unpublishConsolided($deletedIds);
    }

    // A chaque synchro on remet à jour les données liées aux sélections
    $this->updateSelections();

    // A chaque synchro on remet à jour les champs de recherches du module des fiches locales
    if ($newOids) {
      $this->updateSearchFields($newOids);
    }

    // On met à jour les KOID des fiches locales
    $this->updatePeriodesOuverturesKOID();

    $this->log('Consolidation terminée');

    if ($scheduler instanceof \Seolan\Module\Scheduler\Scheduler)
      return $this->cr;
    else
      die(nl2br($this->cr));
  }

  protected function unpublishConsolided($sitra_ids) {
    $consolidation_tables = $this->correspondance->getTables();
    foreach ($consolidation_tables as $table) {
      getDB()->execute('UPDATE '.$table.' SET PUBLISH="2" WHERE KOID IN ("'.$table.':'.implode('","'.$table.':', $sitra_ids).'")');
    }
  }

  /**
   * Consolider toutes les fiches sitra.
   */
  function consolidateAll() {
    $this->deleteOrphans();
    $all_oids = getDB()->select("select distinct koid from {$this->table} where linkedObject=2 AND type IS NOT NULL")
      ->fetchAll(\PDO::FETCH_COLUMN);
    foreach($this->openSystemApis as $openSystemApi) {
      $openSystemApi->enableCacheUsageOnly();
    }
    $this->consolidate($all_oids);
    $this->updateSelections();
    $this->updateSearchFields($all_oids);
  }

  /**
   * Supprime les fiches consolidées qui ne sont plus dans la table source.
   */
  protected function deleteOrphans() {
    $sitra_table = $this->getTableForSchema('ObjetsTouristiques');
    $sitra_ids = getDb()->fetchCol('SELECT DISTINCT _id FROM '.$sitra_table);
    $tables_to_consolidate = $this->correspondance->getTables();
    foreach ($tables_to_consolidate as $table) {
      $consolidate_ids = getDb()->fetchCol('SELECT DISTINCT REPLACE(KOID,"'.$table.':","") as oid FROM '.$table);
      if (($deleted_ids = array_diff($consolidate_ids, $sitra_ids))) {
        $this->log('Suppression des fiches orphelines '.implode(', ', $deleted_ids));
        getDB()->execute('DELETE FROM '.$table.' WHERE KOID IN ("'.$table.':'.implode('","'.$table.':', $deleted_ids).'")');
      }
    }
  }

  /**
   * @param $oids: KOID des fiches à consolider
   */
  protected function consolidate($oids = array(), $log = true) {
    ini_set('max_execution_time', 0);
    \Seolan\Library\ProcessCache::deactivate();
    \Seolan\Library\Database::deactivateCache();

    foreach ($GLOBALS['TZR_LANGUAGES'] as $lang_tzr => $lang_iso) {
      $this->consolidateForLang($oids, $lang_tzr, $log);
    }
  }

  /**
   * Consolide la fiche en cours d'affichage
   */
  public function consolidateOne() {
    if (isset($_REQUEST['oid']) && is_string($_REQUEST['oid'])) {
      $this->consolidate(array($_REQUEST['oid']));

      try {
        $sitra_object = $this->getSitraObject($_REQUEST['oid']);
        $tables_to_consolidate = $this->correspondance->getTablesForSitraObject($sitra_object);
        $table = $tables_to_consolidate[0];

        $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
        $sitra_id = str_replace($this->table . ':', '', $_REQUEST['oid']);
        $local_oid = $this->getLocalSitraObjectKoid($xds, $sitra_id);
        $this->log('Voir la fiche locale : '.\Seolan\Field\Link\xlinkdef_display_html($local_oid));
      }
      catch (\Seolan\Core\Exception\NotFound $ex) {
        $this->log("Fiche liée introuvable (table : $table, oid : ".$_REQUEST['oid'].", sitra_id : $sitra_id) ===> local_oid : $local_oid");
      }
    }

    setSessionVar('message', nl2br($this->cr));
  }

  /**
   * @param $oids : KOID des fiches à consolider
   * @param $lang : Langue à consolider
   * @throws Exception
   */
  protected function consolidateForLang($oids, $lang, $log = true) {
    \Seolan\Core\Shell::setLang($lang);
    foreach ($oids as $oid) {
      try {
        $sitra_object = $this->getSitraObject($oid);
        if ($log) $this->log("consolidate ".$sitra_object['o_id']->raw." ($lang)");

        $tables_to_consolidate = $this->correspondance->getTablesForSitraObject($sitra_object);
        foreach ($tables_to_consolidate as $table_to_consolidate) {
          $local_sitra_object_data = $this->correspondance->getLocalSitraDataForTable($sitra_object, $table_to_consolidate);
          $open_system_ids = $this->getOpenSystemIds($local_sitra_object_data);
          $local_sitra_object_data = array_merge($local_sitra_object_data, $open_system_ids);

          $this->updateLocalSitraObject($local_sitra_object_data, $sitra_object['o_id']->raw, $table_to_consolidate);
        }
      } catch (Exception $exc) {
        // On attrape comme on peux l'exception si l'objet est introuvable. Afin de palier à un bug que JC doit corriger
        // (absence de type).
        if (strpos($exc->getMessage(), 'could not find object with oid=SIT_OBJETSTOURISTIQUES')) {
          $this->log("ERROR: Unable to find sitra object \"$oid\" ($lang). Consolidation de cet objet annulé.");
        } else {
          throw $exc;
        }
      }

    }
    \Seolan\Core\Shell::unsetLang();
  }

  /**
   * @param $oid: KOID de la fiche sitra
   * @return array: Données de la fiche sitra
   */
  public function getSitraObject($oid) {
    return $this->displayObject(array(
      'oid' => $oid,
      'options' => array(
        'structureInformation' => array('nofollowlinks' => true),
      )
    ));
  }

  /**
   * @param $object_data
   * @param $sitra_id
   * @param $table
   * @internal param $stitra_id2
   */
  protected function updateLocalSitraObject($object_data, $sitra_id, $table) {
    $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
    try {
      $object_data['oid'] = $this->getLocalSitraObjectKoid($xds, $sitra_id);
      $object_data['_nolog'] = true;
      $xds->procEdit($object_data);
    } catch (\Seolan\Core\Exception\NotFound $ex) {
      $object_data['newoid'] = $xds->getTable().':'.$object_data['external_id'];
      if($xds->fieldExists('ID_APIDAE')) {
        $object_data['ID_APIDAE'] = $object_data['oid'];
      }
      $object_data['PUBLISH'] = 1;
      $object_data['_nolog'] = true;
      $xds->procInput($object_data);
    }
  }

  /**
   * @param \Seolan\Core\DataSource\DataSource $xds
   * @param $sitra_id
   * @return array
   * @throws NotFoundException
   * @internal param $stitra_id2
   */
  protected function getLocalSitraObjectKoid(\Seolan\Core\DataSource\DataSource $xds, $sitra_id) {
    $local_table = $xds->getTable();
    if($xds->fieldExists('ID_APIDAE')) {
      $local_sitra_koid = getDB()->select("select KOID from $local_table where ID_APIDAE=?", array($this->table.':'.$sitra_id))->fetch(\PDO::FETCH_COLUMN);
    }
    else {
      $local_sitra_koid = getDB()->select("select KOID from $local_table where KOID=?", array($local_table.':'.$sitra_id))->fetch(\PDO::FETCH_COLUMN);
    }

    if (!$local_sitra_koid) {
      throw new \Seolan\Core\Exception\NotFound();
    }
    return $local_sitra_koid;
  }

  protected function getOpenSystemIds($local_sitra_data) {
    if (!($sitra2_id = $local_sitra_data['external_id'])) {
      return array();
    }

    $open_system_id = false;
    foreach($this->openSystemApis as $openSystemApi) {
      try {
        $open_system_id = $openSystemApi->getOpenSystemId($sitra2_id);
      }
      catch (\Seolan\Core\Exception\NotFound $exc) {
      }
    }

    if(!$open_system_id) {
      $open_system_id = '';
      \Seolan\Core\Logs::critical("Sitra::getOpenSystemIds($local_sitra_data): open_system_id not found (sitra2_id : $sitra2_id) ");
    }

    return array(
      'open_system_id' => $open_system_id
    );
  }

  /**
   *  Affiche l'identifiant OpenSystem dans le back-office à la demande
   */
  public function displayOpenSystemId($ar) {
    $d = $this->display($ar);

    $open_system_id = false;
    foreach($this->openSystemApis as $openSystemApi) {
      try {
        $open_system_id = $openSystemApi->getOpenSystemId($d['o_id']->raw);
        setSessionVar('message', 'Identifiant trouvé dans l\'Open Concentrateur '.$openSystemApi->getLogin().' : '.$open_system_id);
      }
      catch (\Seolan\Core\Exception\NotFound $exc) {
      }
    }

    if(!$open_system_id) {
      setSessionVar('message', 'Aucun identifiant OpenSystem lié !');
    }
  }

  /**
   *  Met à jour les champs sélections des tables consolidés.
   */
  protected function updateSelections() {
    if($this->selections_sync) {
      \Seolan\Library\ProcessCache::deactivate();
      \Seolan\Library\Database::deactivateCache();
      $this->selections_sync->synchronize();
    }
  }

  /**
   *  Met à jour les champs de recherches des fiches sitra (wifi, piscine, nombre de personnes...)
   */
  protected function updateSearchFields($local_files_oids) {
    if (empty($local_files_oids) || !is_array($local_files_oids)) {
      $this->log('updateSearchFields: no oids passed in parameters');
      return false;
    }

    \Seolan\Library\ProcessCache::deactivate();
    \Seolan\Library\Database::deactivateCache();
    set_time_limit(0);
    $nb_edit = 0;
    $mod_local_files = \Seolan\Core\Module\Module::objectFactory(\Seolan\Core\Ini::get('module_local_sitra_files'));

    if(!$mod_local_files->table) return false;

    // Conversion des ID vers la table des Fiches consolidées
    foreach ($local_files_oids as &$oid) {
      $oid = $mod_local_files->table.':'.end(explode(':',$oid));
    }


    //Mise en commentaire de cette instruction ci-dessous car elle génère une erreur dans le CRON : méthode (et classe) inconnue :
    //$search_fields = Helper::xset_browse('SITRA_LOCAL_SEARCH_FIELDS',array('pagesize' => -1, 'selectedfields' => 'all'))['lines'];
    $lsf_xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=SITRA_LOCAL_SEARCH_FIELDS');
    $search_fields = (array)$lsf_xds->browse(array(
        'pagesize' => -1,
        'selectedfields' => 'all',
        '_mode' => 'both'
      ))['lines'];
    $search_field_uniq_name = array();
    foreach ($search_fields as $search_field) {
      if (!in_array($search_field['ofile_field']->raw,$search_field_uniq_name)){
        $search_field_uniq_name[] = $search_field['ofile_field']->raw;
      }
      foreach ($search_field['oelements_reference']->oidcollection as $i => $oid_elem_ref) {
        $elements_reference[$oid_elem_ref][] = $search_field;
        $elements_reference_names[$oid_elem_ref][] = strip_tags($search_field['oelements_reference']->collection[$i]->html);
      }
    }
    foreach ($mod_local_files->xset->desc as $fieldname => $ofield) {
      if ($ofield->get_target() == 'SIT_ELEMENTSREFERENCE') {
        $SIT_ELEMENTSREFERENCE_fields[] = $fieldname;
        $options[$fieldname]['nofollowlinks'] = 1;
      }
    }

    // Réinitialisation des champs à consolider
    // getDb()->execute('UPDATE '.$mod_local_files->table.' SET classementLabel="",capacite="",equipements="" WHERE KOID IN ("'.implode('","', $local_files_oids).'")');
    $toSet = implode('="",',$search_field_uniq_name).'="",capacite=""';
    getDb()->execute('UPDATE '.$mod_local_files->table.' SET '.$toSet.' WHERE KOID IN ("'.implode('","', $local_files_oids).'")');

    // Décommenter cette ligne pour ne prendre en compte que les champs affichés dans la fiche détail en front-office (= + rapide mais peut etre moins efficace ?)
    // $SIT_ELEMENTSREFERENCE_fields = array('prestations_activites','prestations_equipements','prestations_services','prestations_conforts','classement','labels','hebergementLocatif_typeLabel');

    $fiches = $mod_local_files->xset->browse(array(
      'selectedfields'=>array_merge($SIT_ELEMENTSREFERENCE_fields, array('nom','hebergementCollectif_capacites','hebergementLocatif_capacite')),
      'pagesize'=>-1,
      'options' => $options,
      'cond' => array('KOID' => array('=', $local_files_oids)),
      '_mode' => 'both'
    ))['lines'];

    foreach ($fiches as $i => $fiche) {
      $edit = array();

      // Prestations et classement (wifi, piscine, animaux... + classement, labels...)
      foreach ($SIT_ELEMENTSREFERENCE_fields as $SIT_ELEMENTSREFERENCE_field) {
        $refs = $fiche['o'.$SIT_ELEMENTSREFERENCE_field]->oidcollection;

        if (empty($refs))
          $refs = array($fiche['o'.$SIT_ELEMENTSREFERENCE_field]->raw);
        foreach ($elements_reference as $oid_elem_ref => $search_fields) {
          foreach ($search_fields as $search_field) {
            if (in_array($oid_elem_ref, $refs)) {
              $edit[$search_field['ofile_field']->raw][] = $search_field['oid'];
              $this->log('L\'élément de référence <b>'.$elements_reference_names[$oid_elem_ref].'</b> ajoute la propriété <b>'.$search_field['otitle']->text.'</b> à la fiche <b>'.$fiche['onom']->text, false);
            }
          }
        }
      }

      // Capacités
      if ($fiche['ohebergementCollectif_capacites']->html) {
        $capacites = json_decode($fiche['ohebergementCollectif_capacites']->raw);
        $edit['capacite'] = $capacites->hebergementCollectif_capacite_capaciteTotale->value;
        $this->log('Capacité de <b>'.$capacites->hebergementCollectif_capacite_capaciteTotale->value.'</b> personnes pour la fiche <b>'.$fiche['onom']->text, false);
      }
      if ($fiche['hebergementLocatif_capacite']->html) {
        $capacites = json_decode($fiche['ohebergementLocatif_capacite']->raw);
        $edit['capacite'] = $capacites->hebergementLocatif_capacite_capaciteHebergement->value;
        $this->log('Capacité de <b>'.$capacites->hebergementLocatif_capacite_capaciteHebergement->value.'</b> personnes pour la fiche <b>'.$fiche['onom']->text, false);
      }

      if (empty($edit)) continue;
      $edit['oid'] = $fiche['oid'];
      $edit['_nolog'] = true;
      foreach ($edit as $key => $value){
        if ( is_array($value)){
          $edit[$key] = '||'.implode('||', array_unique($value)).'||';
        }
      }
      $nb_edit++;
      $mod_local_files->xset->procEdit($edit);
    }
    $this->log('updateSearchFields: '.$nb_edit.' fiches mises à jour');
  }

  /// Mets à jour les KOID des fiches locales liées aux périodes d'ouverture
  /**
  * @TODO : voir à quoi elle sert cette fonction ... !!!
  */
  private function updatePeriodesOuverturesKOID() {
    //\Seolan\Core\Module\Module::objectFactory(\Seolan\Core\Ini::get('module_local_sitra_files'))->updatePeriodesOuverturesKOID();
  }

  /// Encode les OID Séolan pour réduire leur taille dans les options de la tache planifiée de postImport
  private function encodeOids($oids) {
    if (!is_array($oids)) $oids = array($oids);
    $ids = array();
    foreach ($oids as $oid) {
      if (empty($oid)) continue;
      $ids[] = end(explode(':',$oid));
    }
    return implode(',',$ids);
  }

  /// Décode les IDS SITRA placés en option de la tache planifiée pour les convertir en OID Séolan
  private function decodeOids($encoded_ids) {
    $ids = explode(',', $encoded_ids);
    $oids = array();
    foreach ($ids as $id) {
      if (empty($id)) continue;
      $oids[] = $this->table.':'.$id;
    }
    return $oids;
  }

  /**
   * Import des sélections d'objets SITRA
   *
   * @param $ar array Paramètres passés à la procédure d'import
   * @throws Exception
   */
  function importSelections() {
    if (empty($this->selections)) return false;
    // Si les sélections sont présentes dans l'import on réinitialise la table
    $this->log("importing selections");
    getDb()->execute('DELETE FROM '.$this->getTableForSchema('Selections'));
    foreach ($this->selections as $selection) {
      $this->importObject('Selections', $selection);
    }
    unset($trackchanges);
  }

  // suppresion d'un objet touristique
  protected function delObject($schema, $object, $full = true, $force_delete_linked_media = false) {
    if (is_numeric($object))
      $oid = $this->getTableForSchema($schema).':'.$object;
    elseif (is_object($object))
      $oid = $object->id;
    else
      $oid = $object;
    return $this->_delObject($schema, $oid, $full, $force_delete_linked_media);
  }
  // suppresion d'un objet
  protected function _delObject($schema, $oid, $full = true, $force_delete_linked_media = false) {
    // Les médias non utilisés seront supprimés en fin d'import
    if (!$force_delete_linked_media && $schema == 'Medias')
      $full = false;
    $this->log("delObject $oid", false);
    $table = $this->getTableForSchema($schema);
    $row = getDB()->select("select * from $table where koid='$oid'")->fetch();
    // les liens
    foreach ($this->_fields[$schema] as $def) {
      $fieldName = $def['field'];
      if ($def['ftype'] == '\Seolan\Field\Link\Link' && !$def['isLink'] && !in_array($def['target'], self::$mainTables)) {
        $oids = preg_split('/\|\|/', $row[$fieldName], 0, PREG_SPLIT_NO_EMPTY);
        foreach ($oids as $_oid)
          $this->_delObject($def['target'], $_oid, true, $force_delete_linked_media);
      }
    }
    // les sous fiches
    foreach ($this->_subTables[$schema] as $access => $subTable) {
      $_table = $this->getTableForSchema($subTable);
      $oids = getDB()->select("select distinct koid from $_table where $schema='$oid'")->fetchAll(\PDO::FETCH_COLUMN);
      foreach ($oids as $_oid)
        $this->_delObject($subTable, $_oid, true, $force_delete_linked_media);
    }
    if ($full) {
      $this->getDataSource($table)->delObject($oid);
    }
    return $oid;
  }

  function importObject($schema, $object, $parentoid=null) {
    global $TZR_LANGUAGES;
    $this->log("importObject $schema {$object->id}", false);
    \Seolan\Core\Logs::debug("importObject $schema {$object->id}");
    $object->_id = $object->id;
    foreach ($this->_fields[$schema] as $access => $def) {
      $fieldName = $def['field'];
      $this->sanitizeSqlFieldName($fieldName);
      $inputs->fullFieldName = $access;
      $value = $this->getValue($access, $def, $object);
      //if (!isset($value))
        //continue;
      if ($def['ftype'] == '\Seolan\Field\File\File') {
        foreach ($value->traductionFichiers as $item) {
          $inputs->_sitra_fichiers[$fieldName] = $item;
          if ($item->locale == 'fr') {
            $inputs->url = $item->url;
            $inputs->$fieldName = $item->url;
          } elseif ($codeTzr = array_search($item->locale, $TZR_LANGUAGES)) {
            $inputs->trad[$codeTzr]['url'] = $item->url;
            $inputs->trad[$codeTzr][$fieldName] = $item->url;
          }
        }
      } elseif ($def['translatable']) {
        $inputs->$fieldName = $value->libelleFr;
        foreach ($TZR_LANGUAGES as $codeTzr => $codeIso) {
          if ($codeIso == 'fr')
            continue;
          $property = 'libelle' . ucfirst($codeIso);
          if ($value->$property)
            $inputs->trad[$codeTzr][$fieldName] = $value->$property;
        }
      } elseif ($def['ftype'] == '\Seolan\Field\Link\Link') {
        $inputs->$fieldName = $this->getLink($def, $value);
      } elseif ($def['multivalued'] == 1) {
        $inputs->$fieldName = implode(',', $value);
      } else {
        $inputs->$fieldName = $value;
      }
      // Permet de supprimer les valeurs effacées dans APIDAE en local (car NULL n'est pas pris en compte dans le procInsert)
      $inputs->$fieldName = is_null($inputs->$fieldName) ? '' : $inputs->$fieldName;
    }
    $oid = $this->insertObject($schema, $inputs);
    // les sous fiches
    foreach ($this->_subTables[$schema] as $access => $subTable) {
      $field = lcFirst($subTable);
      $_object = $object;
      $path = explode('_', $access);
      foreach ($path as $step)
        $_object = $_object->$step;
      if (!$_object)
        continue;
      if (is_array($_object)) { // cas d'une liste
        foreach ($_object as $__object) {
          $__object->$schema = $oid;
          $this->importObject($subTable, $__object, $oid);
        }
      } else {
        $_object->$schema = $oid;
      }
    }
    $this->log("importObject OK $schema OID=$oid", false);
    return $oid;
  }

    protected function getValue($access, $def, $object) {
    if ($def['translatable'] && isset($object->libelleFr)) // nomenclature
      return $object;
    if ($access == 'contactsExternes')
      return $object->contactsExternes->contact;
    if ($access == 'Fichiers')
      return $object;
    $path = explode('_', $access);
    foreach ($path as $step)
      $object = $object->$step;
    if ($def['field'] == 'geoJson')
      return $object->coordinates[1] .';'. $object->coordinates[0];
    // Permet de supprimer les valeurs effacées dans APIDAE en local (car NULL n'est pas pris en compte dans le procInsert)
    return is_null($object) ? '' : $object;
  }
    protected function getLink($def, $value) {
    if (empty($value))
      return null;
    if ($def['isLink']) // lien vers un parent
      return $value;
    if (in_array($def['target'], self::$mainTables)) // lien nomenclature
      return $this->makeLink($def, $value);
    return $this->processLink($def, $value);
  }
  // retourne un lien vers un element de la nomenclature ou un ot lié
  protected function makeLink($def, $value) {
    if ($def['multivalued'] && is_array($value)) {
      foreach ($value as $_value)
        $_values[] = $this->getTableForSchema($def['target']) . ':' . $_value->id;
      return '||' . implode('||', $_values) . '||';
    }
    if ($value->id)
      return $this->getTableForSchema($def['target']) . ':' . $value->id;
    return null;
  }
  // importe un sous-objet lié
    protected function processLink($def, $value) {
    if ($def['multivalued'] && is_array($value)) {
      foreach ($value as $_value) {
        $_values[] = $this->importObject($def['target'], $_value);
      }
      return '||' . implode('||', $_values) . '||';
    }
    if ($value->id) {
      return $this->importObject($def['target'], $value);
    }
    return null;
  }


  function insertObject($schema, $inputs) {
    $this->log("insertObject $schema {$inputs->id}", false);
    \Seolan\Core\Logs::debug("insertObject $schema {$inputs->id}");
    ob_start();
    var_dump($inputs);
    \Seolan\Core\Logs::debug(ob_get_contents());
    ob_end_clean();

    $table = $this->getTableForSchema($schema);
    $this->checkObjectMedias($schema, $inputs, $table);
    if ($inputs->id) {
      $inputs->_id = $inputs->id;
      $inputs->newoid = $table . ':' . $inputs->id;
      $inputs->_unique = array('KOID');
      $inputs->_updateifexists = true;
    }
    $inputs->_local = true;
    if (!$this->trackchanges) {
      $inputs->_nolog = true;
      $inputs->_lastupdate = false;
    }
    $inputs = (array) $inputs;
    $ret = $this->getDataSource($table)->procInput($inputs);
    $this->updateLangs($table, $ret['oid'], $inputs);
    return $ret['oid'];
  }

  /**
   * Permet lors de l'import d'un objet SITRA de vérifier si un média a déjà été téléchargé.
   * On ignore sa mise à jour en fonction de la date de dernière modification envoyée par
   * SITRA dans la propriété lastModifiedDate.
   * @todo Tester avec des images traduisibles
   * @return bool False si rien à faire
   */
  function checkObjectMedias($schema, &$inputs, $table) {

    if (!isset($inputs->_sitra_fichiers))
      return false;

    if ($schema == 'Medias') {
      // On ne télécharge pas certains types de médias qui sont des liens vers des pages web
      if (in_array($inputs->type, array('VISITE_VIRTUELLE', 'VIDEO', 'CHAINE_YOUTUBE', 'CHAINE_DAILYMOTION', 'BROCHURE_VIRTUELLE'))) {
        unset($inputs->Fichiers);
        return true;
      }
      // On va chercher le KOID du média via son URL (format SQL Séolan)
      $url = (new \Seolan\Field\Url\Url())->post_edit($inputs->url)->raw;
      if (isset($this->localMedias[$url])) {
        $oid = $this->localMedias[$url]['KOID'];
        $inputs->id = end(explode(':',$this->localMedias[$url]['KOID']));
      }
    } elseif (isset($inputs->id) && !empty($inputs->id)) {
      $oid = $table.':'.$inputs->id;
    }

    if (!isset($oid))
      return false;

    $display = $this->getDataSource($table)->display(array(
      '_options' => array('error' => 'return'),
      '_local' => true,
      '_lastupdate' => false,
      'oid' => $oid,
      'selectedfields' => array_keys($inputs->_sitra_fichiers)
    ));

    if (!is_array($display))
      return false;

    // Compare la dernière MAJ du fichier en local avec la dernière MAJ sitra pour chaque champs
    foreach ($inputs->_sitra_fichiers as $fieldname => $_sitra_fichier) {
      $local_filename = $display["o$fieldname"]->filename;
      $local_mtime = filemtime($display["o$fieldname"]->filename);
      if ($local_mtime !== false && isset($_sitra_fichier->lastModifiedDate) && $local_mtime > strtotime($_sitra_fichier->lastModifiedDate)) {
        $this->log("Média local à jour [$fieldname] : ".$inputs->$fieldname.' sitra_mtime='.date('c',$sitra_mtime).' local_mtime='.date('c',$local_mtime), false);
        unset($inputs->$fieldname);
      } else {
        $this->log("Téléchargement du média [$fieldname] : ".$inputs->$fieldname.
          ' sitra_lastModifiedDate='.var_export($_sitra_fichier->lastModifiedDate, true).
          ' sitra_type='.var_export($_sitra_fichier->type, true).
          ' local_filename='.var_export($local_filename, true).
          ' local_oid='.$oid);
      }
    }
    return true;
  }

  function updateLangs($table, $oid, $inputs) {
    $lang_save = \Seolan\Core\Shell::getLangUser();
    foreach ($inputs['trad'] as $lang => $fields) {
      $fields['oid'] = $oid;
      if (!$this->trackchanges) {
        $fields['_nolog'] = true;
        $fields['_lastupdate'] = false;
      }
      $_REQUEST['LANG_USER'] = $lang;
      $_REQUEST['LANG_DATA'] = $lang;
      \Seolan\Core\Shell::getLangUser();
      \Seolan\Core\Shell::getLangData(NULL, true);
      $this->getDataSource($table)->procEdit($fields);
    }
    $_REQUEST['LANG_USER'] = $lang_save;
    $_REQUEST['LANG_DATA'] = $lang_save;
    \Seolan\Core\Shell::getLangUser(NULL, true);
    \Seolan\Core\Shell::getLangData(NULL, true);
  }

  function getDataSource($table) {
    if (!isset($this->_dataSources[$table]))
      $this->_dataSources[$table] = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$table);
    return $this->_dataSources[$table];
  }

  // chargement des objets
  function loadNomenclatureObjects() {
    foreach ($this->sitraNomenclature as $nomenclature => $filename) {
      \Seolan\Core\Logs::notice(get_class()."::loadNomenclatureObjects nomenclature objects $filename");
      $nomenclature_file = $this->importFolder . $filename;
      if (!file_exists($nomenclature_file)) {
        $this->log("Fichier de nomenclature inexistant : $nomenclature_file");
        continue;
      }
      $objects = json_decode( file_get_contents( $nomenclature_file ) );
      $this->nomenclatureObjects[$nomenclature] = $objects;
    }
  }

  // Ancienne méthode de chargement des objets de l'export. S'il y a trop d'objets, cela peut saturer la RAM.
  function loadObjects() {
    \Seolan\Core\Logs::debug("========> LOAD OBJECTS FROM ".$this->importFolder);
    $this->objects = array();
    foreach (glob($this->importFolder . "objets_modifies/objets_modifies-*.json") as $filename) {
      \Seolan\Core\Logs::notice(get_class()."::loadObjects objects $filename");
      $objects = json_decode( file_get_contents( $filename ) );
      $this->objects = array_merge($this->objects, $objects);
    }
    $this->linkedObjects = array();
    foreach (glob($this->importFolder . "objets_lies/objets_lies_modifies-*.json") as $filename) {
      \Seolan\Core\Logs::notice(get_class()."::loadObjects linked objects $filename");
      $objects = json_decode( file_get_contents( $filename ) );
      $this->linkedObjects = array_merge($this->linkedObjects, $objects);
    }
    $filename = $this->importFolder . 'selections.json';
    \Seolan\Core\Logs::notice(get_class()."::loadObjects selection $filename");
    $this->selections = json_decode( file_get_contents( $filename ) );
  }

  /**
   * Chargement des objets de l'export de manière spécifique (ce qui permet de ne pas surcharger la RAM s'il y a beaucoup d'objets à importer)
   */
  function loadObjectsSpecific($path){
    \Seolan\Core\Logs::debug("========> LOAD OBJECTS FROM ".$this->importFolder . $path);
    $allObjects = array();
    foreach (glob($this->importFolder . $path) as $filename) {
      \Seolan\Core\Logs::notice(get_class()."::loadObjects $type $filename");
      $objects = json_decode( file_get_contents( $filename ) );
      $allObjects = array_merge($allObjects, $objects);
    }
    return $allObjects;
  }

  /**
   * Chargement des élections
   */
  function loadObjectsSelection(){
    \Seolan\Core\Logs::debug("========> LOAD OBJECTS FROM ".$this->importFolder .'selections.json');
    $filename = $this->importFolder . 'selections.json';
    \Seolan\Core\Logs::notice(get_class()."::loadObjects selection $filename");
    $this->selections = json_decode( file_get_contents( $filename ) );
  }

  

  // Chargement des medias locaux avec leur KOID et leur dernière date de téléchargement
  // sous la forme {url1: [KOID1,UPD1], url2: [KOID2,UPD2], ...}
  function loadLocalMedias() {
    $this->localMedias = getDb()->select(
      'SELECT DISTINCT url,KOID,UPD '.
      'FROM '.$this->getTableForSchema('Medias').' '.
      'ORDER BY UPD')->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
  }

  // chargement des id des objets supprimés
  function loadDeletedIds() {
    foreach (array('objets_supprimes.json', 'objets_lies_supprimes.json') as $filename) {
      \Seolan\Core\Logs::notice(get_class()."::import linked objects $filename");
      $ids = json_decode( file_get_contents( $this->importFolder . $filename ) );
      $this->deletedIds = array_merge($this->deletedIds, $ids);
    }
  }

  public function getTableForSchema($schema) {
    return $this->dbPrefix.strtoupper( $schema );
  }
  
  // display complet d'un objet pour consolidation
  // à tester en mode sousfiche
  function displayObject($ar) {
    static $conf, $cache;
    $p = new \Seolan\Core\Param($ar);
    if (!isset($conf)) {
      $this->analyzeSchemas();
      $conf = $this->getDisplayConf('ObjetsTouristiques');
    }
    return $this->display( $conf + $ar );
    $oid = $p->get('oid');
    $lang = $_REQUEST['LANG_DATA'];
    if (!isset($cache[$lang][$oid]))
      $cache[$lang][$oid] = $this->display( $conf + $ar );
    return $cache[$lang][$oid];
  }

  private $_dispConf = array();
  static private $_conf = array(
    'selectedfields' => 'all',
    'ssmoid' => 'all',
    'nocount' => 1,
    'target_fields' => 'all',
    '_lastupdate' => false,
    'tlink' => false,
    'nocache' => true, // nocache pour les langues
  );
  private function getDisplayConf($schema) {
    if (isset($this->_dispConf[$schema]))
      return $this->_dispConf[$schema];
    $conf = self::$_conf;
    $conf['target_options'] = $conf;
    if (in_array($schema, array_keys($this->sitraNomenclature))) {
      return $this->_dispConf[$schema] = $conf;
    }
    foreach ($this->_fields[$schema] as $def) {
      if ($def['ftype'] == '\Seolan\Field\Link\Link' && !@$def['isLink'] && $def['target'] != 'ObjetsTouristiques') {
        $field = $def['field'];
        $conf['options'][$field] = $this->getDisplayConf($def['target']);
        $conf['target_options'][$field] = $conf['options'][$field];
      }
    }
    if (@$this->_subTables[$schema])
      foreach ($this->_subTables[$schema] as $subTable) {
        $table = $this->getTableForSchema($subTable);
        $modUsingTable = \Seolan\Core\Module\Module::modulesUsingTable($table);
        $moid = key($modUsingTable);
        $conf[$moid] = $this->getDisplayConf(ucFirst($subTable));
      }
    return $this->_dispConf[$schema] = $conf;
  }

  /// Empêche les nom de champs SQL trop long
  protected function sanitizeSqlFieldName(&$fieldname) {
    $fieldname = substr($fieldname, 0, 64);
  }

  /**
   * Affiche la liste des archives présentes dans le dossier var/sitra/archives/
   * à l'utilisateur afin qu'il puisse ré-importer une de ces archives dans la BDD
   * et lui donne aussi la possibilité d'importer une archive externe via URL
   */
  public function importFromArchive($ar) {
    \Seolan\Core\Shell::setTemplates('Module/Sitra.importFromArchive.html');
    \Seolan\Core\Shell::alert("Cette page permet de ré-éxécuter n'importe quel export à partir d'une archive ZIP (utile notamment si un import a échoué).");
    $options = [];
    @mkdir($this->importFolder. '/../archives', 0755, true);
    foreach (new \DirectoryIterator($this->importFolder . '/../archives') as $fileInfo) {
      if ($fileInfo->isDot()) continue;
      $options[$fileInfo->getFilename()] = $fileInfo->getFilename().'  => Archivé le '.date('d/m/Y à H:i:s', $fileInfo->getMTime());
    }
    $options[''] = '---';
    \Seolan\Core\Shell::toScreen2('archive', 'files',  array_reverse($options));
  }

  /**
   * Procédure manuelle d'import d'une archive
   */
  public function procImportFromArchive($ar) {
    \Seolan\Core\Shell::setTemplates('Core.message.html');
    $this->setImportStatus('running');
    $p = new \Seolan\Core\Param($ar);
    $archiveUrl = $p->get('archiveUrl');
    $archiveFile = $p->get('archiveFile');
    $file = $archiveUrl ?: $this->importFolder . '/../archives/'.$archiveFile;

    $scheduler = new \Seolan\Module\Scheduler\Scheduler();
    $rootUser = new \Seolan\Core\User('root');
    $more = array(
      'uid' => $rootUser->uid(),
      'function' => 'runImport',
      'reinitialisation' => $p->get('reinitialisation'),
      'urlRecuperation' => $file,
    );
    $scheduler->createJob($this->_moid, date('Y-m-d H:i:s'), 'Import Sitra2', $more, '');

    \Seolan\Core\Shell::alert("Une tache a été créée dans le scheduler pour lancer l'import");
  }

}
