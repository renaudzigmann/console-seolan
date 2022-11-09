<?php
namespace Seolan\Module\TarteAuCitron;

use Seolan\Core\DataSource\DataSource;
use \Seolan\Core\Labels;
use Seolan\Core\Lang;
use Seolan\Core\Module\Module;
use Seolan\Core\Param;
use Seolan\Library\Upgrades;
use Seolan\Model\DataSource\Table\Table;

/**
 * Wizard de la class Tarte au citron
 * - Personnalisation des différentes valeurs pour la configuration de base 
 * - création de la table TARTEAUCITRON pour la personnalisation des services (Analytics, AddThis, Youtube, ...)
 */
class Wizard extends \Seolan\Module\Table\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->_module->table = 'TARTEAUCITRON';
    $this->_module->group='Gestion du site';
    $this->_module->modulename=Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"modulename","text");
    $this->_module->defaultispublished=1;
    $this->_module->privacyUrl = 'mentions-legales';
    $this->_module->hashtag = '#tarteaucitron';
    $this->_module->cookieName = 'tarteaucitron';
    $this->_module->orientation = 'bottom';
    $this->_module->showAlertSmall = true;
    $this->_module->cookieslist = true;
    $this->_module->adblocker = false;
    $this->_module->AcceptAllCta = true;
    $this->_module->DenyAllCta = true;
    $this->_module->highPrivacy = false;
    $this->_module->handleBrowserDNTRequest = false;
    $this->_module->removeCredit = false;
    $this->_module->moreInfoLink = false;
    $this->_module->useExternalCss = false;
    $this->_module->readmoreLink = '';
    $this->_module->mandatory = true;
  }

  // Step 1
  function istep1() {
    parent::istep1();
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Core_General',"modulename"), "modulename", "text");
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Core_General',"group"), "group", "text");
    $this->_options->setRO("modulename");
    $this->_options->setRO("table");
    $this->_options->setRO("createstructure");
  }
  
  // Step 2
  function istep2() {
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"privacyUrl","text"), 'privacyUrl', 'text', NULL, 'mentions-legales', $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"hashtag","text"), 'hashtag', 'text', NULL, '#tarteaucitron', $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"cookieName","text"), 'cookieName', 'text', NULL, 'tarteaucitron', $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"orientation","text"), 'orientation', 'text', NULL, 'bottom', $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"showAlertSmall","text"), 'showAlertSmall', 'boolean', NULL, true, $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"cookieslist","text"), 'cookieslist', 'boolean', NULL, true, $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"adblocker","text"), 'adblocker', 'boolean', NULL, false, $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"AcceptAllCta","text"), 'AcceptAllCta', 'boolean', NULL, true, $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"DenyAllCta","text"), 'DenyAllCta', 'boolean', NULL, false, $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"highPrivacy","text"), 'highPrivacy', 'boolean', NULL, false, $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"handleBrowserDNTRequest","text"), 'handleBrowserDNTRequest', 'boolean', NULL, false, $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"removeCredit","text"), 'removeCredit', 'boolean', NULL, false, $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"moreInfoLink","text"), 'moreInfoLink', 'boolean', NULL, false, $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"useExternalCss","text"), 'useExternalCss', 'boolean', NULL, false, $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"readmoreLink","text"), 'readmoreLink', 'text', NULL, '', $this->group);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"mandatory","text"), 'mandatory', 'boolean', NULL, false, $this->group);
  }

  function iend($ar=NULL) {
    $this->createStructure($ar);
    $this->createLabels();
    $ret = parent::iend($ar);
    $this->insertConf();

    return $ret;
  }

  function quickCreate($modulename, $options) {
    $this->createStructure(array('module' => $options));
    $this->createLabels();
    $options = array_merge(get_object_vars($this->_module), $options);
    $ret = parent::quickCreate($modulename, $options);
    $this->insertConf();

    return $ret;
  }

  private function createStructure($ar) {

    // Récupération des valeurs indiquées dans les différents champs pour assigner la valeur par défaut
    $p = new Param($ar,[]);
    $module = $p->get('module') ?: array();
    $module = array_merge(get_object_vars($this->_module), $module);
    $table = $module['table'];
    $group = $module['group'];
    $privacyUrl = $module['privacyUrl'];
    $hashtag = $module['hashtag'];
    $cookieName = $module['cookieName'];
    $orientation = $module['orientation'];
    $showAlertSmall = $module['showAlertSmall'];
    $cookieslist = $module['cookieslist'];
    $adblocker = $module['adblocker'];
    $AcceptAllCta = $module['AcceptAllCta'];
    $DenyAllCta = $module['DenyAllCta'];
    $highPrivacy = $module['highPrivacy'];
    $handleBrowserDNTRequest = $module['handleBrowserDNTRequest'];
    $removeCredit = $module['removeCredit'];
    $moreInfoLink = $module['moreInfoLink'];
    $useExternalCss = $module['useExternalCss'];
    $readmoreLink = $module['readmoreLink'];
    $mandatory = $module['mandatory'];

    // 1. Table principale : TARTEAUCITRON
    // Création de la table
    $res = Table::procNewSource(array(
      'translatable' => true,
      'auto_translate' => true,
      'btab' => $table,
      'bname' => array(TZR_DEFAULT_LANG => $group . ' - Tarte au citron'),
      'publish' => false,
      'own' => false
    ));

    // Création des champs
    $ds = DataSource::objectFactoryHelper8('SPECS='.$table);

    $fields = array(
      array(
        'field' => 'application',
        'label' => [TZR_DEFAULT_LANG => 'Application'],
        'ftype' => '\Seolan\Field\Link\Link',
        'fcount' => '100',
        'target' => 'APP',
        'compulsory' => '1','browsable' => '1','queryable' => '1','published' => '1'
      ),
      array(
        'field' => 'privacyUrl',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"privacyurl","text")],
        'ftype' => '\Seolan\Field\ShortText\ShortText',
        'fcount' => '100',
        'compulsory' => '0','browsable' => '1','queryable' => '1','published' => '0','options' => ['default' => $privacyUrl]
      ),
      array(
        'field' => 'hashtag',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"hashtag","text")],
        'ftype' => '\Seolan\Field\ShortText\ShortText',
        'fcount' => '100',
        'compulsory' => '0','browsable' => '1','queryable' => '1','published' => '0','options' => ['default' => $hashtag]
      ),
      array(
        'field' => 'cookieName',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"cookieName","text")],
        'ftype' => '\Seolan\Field\ShortText\ShortText',
        'fcount' => '255',
        'compulsory' => '1','browsable' => '1','queryable' => '1','published' => '0','options' => ['default' => $cookieName]
      ),
      array(
        'field' => 'orientation',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"orientation","text")],
        'ftype' => '\Seolan\Field\ShortText\ShortText',
        'fcount' => '100',
        'compulsory' => '0','browsable' => '1','queryable' => '1','published' => '0','options' => ['default' => $orientation]
      ),
      array(
        'field' => 'showAlertSmall',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"showAlertSmall","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $showAlertSmall]
      ),
      array(
        'field' => 'cookieslist',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"cookieslist","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $cookieslist]
      ),
      array(
        'field' => 'adblocker',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"adblocker","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $adblocker]
      ),
      array(
        'field' => 'AcceptAllCta',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"AcceptAllCta","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $AcceptAllCta]
      ),
      array(
        'field' => 'DenyAllCta',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"DenyAllCta","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $DenyAllCta]
      ),
      array(
        'field' => 'highPrivacy',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"highPrivacy","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $highPrivacy]
      ),
      array(
        'field' => 'handleBrowserDNTRequest',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"handleBrowserDNTRequest","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $handleBrowserDNTRequest]
      ),
      array(
        'field' => 'removeCredit',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"removeCredit","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $removeCredit]
      ),
      array(
        'field' => 'moreInfoLink',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"moreInfoLink","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $moreInfoLink]
      ),
      array(
        'field' => 'useExternalCss',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"useExternalCss","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $useExternalCss]
      ),
      array(
        'field' => 'readmoreLink',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"readmoreLink","text")],
        'ftype' => '\Seolan\Field\Label\Label',
        'fcount' => '0',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $readmoreLink]
      ),
      array(
        'field' => 'mandatory',
        'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"mandatory","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => $mandatory]
      )
    );
    foreach($fields as $field) {
      $ds->procNewField($field);
    }     

    // 2. Table des services : TARTEAUCITRON_SERVICES
    // Création de la table 
    $table_services = $table . '_SERVICES';
    Table::procNewSource(array(
      'translatable' => true,
      'auto_translate' => true,
      'btab' => $table_services,
      'bname' => array(TZR_DEFAULT_LANG => 'Tarte Au Citron - Services'),
      'publish' => false,
      'own' => false
    ));
    // Création des champs
    $ds = DataSource::objectFactoryHelper8('SPECS='.$table_services);
    $fields = array(
      array(
      'field' => 'service',
      'label' => [TZR_DEFAULT_LANG => 'Service'],
      'ftype' => '\Seolan\Field\StringSet\StringSet',
      'fcount' => '0',
      'compulsory' => '1',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '1',
      ),
      array(
      'field' => 'mainparam',
      'label' => [TZR_DEFAULT_LANG => 'Identifiant'],
      'ftype' => '\Seolan\Field\ShortText\ShortText',
      'fcount' => '100',
      'compulsory' => '0',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '0',
      ),
      array(
      'field' => 'extraparams',
      'label' => [TZR_DEFAULT_LANG => 'Paramètres supplémentaires'],
      'ftype' => '\Seolan\Field\Text\Text',
      'fcount' => '100',
      'compulsory' => '0',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '0',
      ),
      array(
      'field' => 'needConsent',
      'label' => [TZR_DEFAULT_LANG => Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"needconsent","text")],
      'ftype' => '\Seolan\Field\Boolean\Boolean',
      'compulsory' => '0',
      'browsable' => '0',
      'queryable' => '1',
      'published' => '0',
      'options' => ['default' => 1]
      ),
      array(
      'field' => 'configuration',
      'label' => [TZR_DEFAULT_LANG => 'Configuration'],
      'ftype' => '\Seolan\Field\Link\Link',
      'fcount' => '255',
      'compulsory' => '1',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '0',
      'target' => $table
      )
    );
    foreach($fields as $field) {
      $ds->procNewField($field);
    }

    // 2. Table des services peronnalisés : TARTEAUCITRON_CUSTOMSERVICES
    // Création de la table 
    $table_servicespersonnalises = $table . '_CUSTOMSERVICES';
    Table::procNewSource(array(
      'translatable' => true,
      'auto_translate' => true,
      'btab' => $table_servicespersonnalises,
      'bname' => array(TZR_DEFAULT_LANG => 'Tarte Au Citron - Services personnalisés'),
      'publish' => false,
      'own' => false
    ));
    // Création des champs
    $ds = DataSource::objectFactoryHelper8('SPECS='.$table_servicespersonnalises);
    $fields = array(
      array(
      'field' => 'spkey', // Note le champ ne peut être nommé "key" car c'est un nom de champ réservé 
      'label' => [TZR_DEFAULT_LANG => 'Clé'],
      'ftype' => '\Seolan\Field\ShortText\ShortText',
      'fcount' => '100',
      'compulsory' => '1',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '1',
      'options' => ['default' => 'mycustomservice']
      ),
      array(
      'field' => 'type',
      'label' => [TZR_DEFAULT_LANG => 'Type'],
      'ftype' => '\Seolan\Field\StringSet\StringSet',
      'fcount' => '0',
      'compulsory' => '1',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '1'
      ),
      array(
      'field' => 'name',
      'label' => [TZR_DEFAULT_LANG => 'Nom'],
      'ftype' => '\Seolan\Field\ShortText\ShortText',
      'fcount' => '100',
      'compulsory' => '1',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '0',
      'options' => ['default' => 'MyCustomService']
      ),
      array(
      'field' => 'needConsent',
      'label' => [TZR_DEFAULT_LANG => 'Désactivable ?'],
      'ftype' => '\Seolan\Field\Boolean\Boolean',
      'fcount' => '0',
      'compulsory' => '1',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '0',
      'options' => ['default' => 1]
      ),
      array(
      'field' => 'cookies',
      'label' => [TZR_DEFAULT_LANG => 'Liste des cookies'],
      'ftype' => '\Seolan\Field\ShortText\ShortText',
      'fcount' => '255',
      'compulsory' => '1',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '0',
      'options' => ['comment' => [TZR_DEFAULT_LANG => 'Séparer par des virgules']]
      ),
      array(
      'field' => 'readmoreLink',
      'label' => [TZR_DEFAULT_LANG => 'Alias de la page de détail'],
      'ftype' => '\Seolan\Field\ShortText\ShortText',
      'fcount' => '255',
      'compulsory' => '0',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '0',
      ),
      array(
      'field' => 'js',
      'label' => [TZR_DEFAULT_LANG => 'Script s\'affichant si obligatoire ou accepté'],
      'ftype' => '\Seolan\Field\Text\Text',
      'fcount' => '80',
      'compulsory' => '0',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '0',
      ),
      array(
      'field' => 'fallback',
      'label' => [TZR_DEFAULT_LANG => 'Script s\'affichant si non accepté'],
      'ftype' => '\Seolan\Field\Text\Text',
      'fcount' => '80',
      'compulsory' => '0',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '0',
      ),
      array(
      'field' => 'configuration',
      'label' => [TZR_DEFAULT_LANG => 'Configuration'],
      'ftype' => '\Seolan\Field\Link\Link',
      'fcount' => '255',
      'compulsory' => '1',
      'browsable' => '1',
      'queryable' => '1',
      'published' => '0',
      'target' => $table
      )
    );

    foreach($fields as $field) {
      $ds->procNewField($field);
    }

    // 2 sous modules pour tarte au citron
    $this->_module->submodmax = 2;

    // 1. Création du module des services
    $table_services = $table . '_SERVICES';
    $ds = DataSource::objectFactoryHelper8('SPECS='.$table_services);
    $wd = new \Seolan\Module\Table\Wizard();
    $options=array();
    $options['group']=$group;
    $options['table']=$ds->getTable();
    $options['theclass']='Seolan\Module\TarteAuCitron\Service';
    $services_moid = $wd->quickCreate($ds->getSourceName(), $options);

    // 1.a Création des Stringsets pour le champ "service"
    $dir = __DIR__ . '/Services/';
    if (is_dir($dir) && $dh = opendir($dir)){
      $services = [];
      while (($file = readdir($dh)) !== false){
        if($file != '.' && $file != '..'){
          require($dir . $file);
          $classname = 'Seolan\Module\TarteAuCitron\Services\\' . str_replace('.php', '', $file);
          if(class_exists($classname)){
            $class = new $classname();
            if(!empty($class->get('name'))){
              $services[$class->get('name')] = $class->get('title');
            }
          }
        }
      }
      closedir($dh);
      ksort($services);
      foreach($services as $name => $title)
        $ds->desc['service']->newString($title, $name);
    }

    // 1.b Indication du sous-module des services
    $this->_module->ssmodtitle1 = 'Services';
    $this->_module->ssmodfield1 = 'configuration';
    $this->_module->ssmod1 = $services_moid;


    // 2. Création du module des services personnalisés
    $table_servicespersonnalises = $table . '_CUSTOMSERVICES';
    $ds = DataSource::objectFactoryHelper8('SPECS='.$table_servicespersonnalises);
    $wd = new \Seolan\Module\Table\Wizard();
    $options=array();
    $options['group']=$group;
    $options['table']=$ds->getTable();
    $servicespersonnalises_moid = $wd->quickCreate($ds->getSourceName(), $options);

    // 2.a Création des Stringsets pour le champ "service"
    foreach(['social','analytic','ads','video','support'] as $type)
      $ds->desc['type']->newString(ucfirst($type), $type);

    // 2.b Indication du sous-module des services personnalisés
    $this->_module->ssmodtitle2 = 'Services personnalisés';
    $this->_module->ssmodfield2 = 'configuration';
    $this->_module->ssmod2 = $servicespersonnalises_moid;

  }

  function createLabels() {
    $labelsDir = $GLOBALS['LIBTHEZORRO'] . '/VendorJS/node_modules/tarteaucitronjs/lang/';
    $langs = array_keys($GLOBALS['TZR_LANGUAGES']);
    $labelsByVar = [];
    $defaultLabels = file_get_contents($labelsDir . 'tarteaucitron.en.js');
    foreach($langs as $lang) {
      $codeLang = Lang::$locales[$lang]['code'];
      if(file_exists($labelsDir . 'tarteaucitron.' . $codeLang . '.js')) {
        $labels = file_get_contents($labelsDir . 'tarteaucitron.' . $codeLang . '.js');
      }
      else {
        $labels = $defaultLabels;
      }
      $labels = str_replace("/*global tarteaucitron */\n", '', $labels);
      $labels = str_replace('tarteaucitron.lang = ', '', $labels);
      $labels = str_replace(';', '', $labels);
      $labels = json_decode($labels, true);
      foreach($labels as $key => $label) {
        if(is_array($label)) {
          foreach($label as $key2 => $label2) {
            $labelsByVar[$key.'_'.$key2][$lang] = $label2;
          }
        }
        elseif ($key != 'middleBarHead'){
          $labelsByVar[$key][$lang] = $label;
        }
      }
    }

    if(count($labelsByVar)) {
      $labels = [];
      foreach($labelsByVar as $var => $label) {
        $labels[] = array(
          'var' => 'tarteaucitron_'.$var,
          'title' => 'Tarte au citron - ' . $var,
          'selector' => 'global',
          'label' => $label
        );
      }
      Upgrades::addLabels($labels);
    }
  }

  function insertConf() {
    Module::clearCache();

    // Insertion de la configuration pour le site actuel
    // 1. Sélection des Applications enregistrées
    $appMoid = getDb()->fetchOne('SELECT MOID FROM MODULES WHERE TOID=?',[XMODAPP_TOID]);
    $xmodapp = Module::objectFactory($appMoid); // la sélection par toid ne fonctionne pas
    $ret = $xmodapp->browse([
      'selectedfields'=>'all',
      '_options'=>array('local'=>1),
      'tplentry'=>TZR_RETURN_DATA
    ]);
    // 2. Insertion d'une ligne de configuration par application
    $tarteaucitronMoid = getDb()->fetchOne('SELECT MOID FROM MODULES WHERE TOID=?',[XMODTARTEAUCITRON_TOID]);
    $xmodtarteaucitron = Module::objectFactory($tarteaucitronMoid);// la sélection par toid ne fonctionne pas
    foreach($ret['lines_oid'] as $k => $oid){
      $xmodtarteaucitron->procInsert([
        'application' => $oid,
        '_options'=>['local'=>true]
      ]);
    }
    // 3. Si on a pas d'applications, on insère quand même une configuration
    if(!count($ret['lines_oid'])) {
      $xmodtarteaucitron->procInsert([
        '_options'=>['local'=>true]
      ]);
    }
  }
}
