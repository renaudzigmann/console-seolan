<?php
namespace Seolan\Module\FrontUsers;
/**
 * Gestion des utilisateurs Front Office
 * 
 * @author Camille Descombes
 *
 * @todo Paramètres à ajouter
 *  - Liste des champs du formulaire d'inscription + champs obligatoires
 *  - Liste des champs du formulaire d'édition + champs obligatoires
 *  - Champ nom
 *  - Champ prénom
 */
class Wizard extends \Seolan\Core\Module\Wizard {
  
  /// Nom des templates de base situés dans public/templates/xmodfrontusers/defaulttemplates/
  public static function getDefaultTemplates() {
    return array(
      'function' => array(
        'account.html' => ['title' => 'Compte utilisateur', 'functions' => 'Seolan\Module\FrontUsers\FrontUsers::account'],
      )
    );
  }

  public function istep1($ar = []) {

    // Valeurs par défaut
    $this->_module->modulename = 'Utilisateurs front-office';
    $this->_module->group = 'Boutique';
    foreach ($GLOBALS['TZR_LANGUAGES'] as $tzrlang => $lang)
      $this->_module->comment[$tzrlang] = "Gestion des comptes utilisateurs du site web public (clients d'une boutique par exemple)";
    $this->_module->alias_home = 'account';
    $this->_module->default_group = TZR_GROUPID_AUTH;
    $this->_module->inscription_require_mail_validation = 1;
    $this->_module->inscription_require_password = 1;
    $this->_module->table = 'USERS';
    $this->_module->do_create_default_templates = 1;
    $this->_module->field_name = \Seolan\Core\System::fieldExists('USERS','nom') ? 'nom' : 'name';
    $this->_module->field_forename = \Seolan\Core\System::fieldExists('USERS','prenom') ? 'prenom' : 'forename';

    \Seolan\Core\Shell::alert("Ce module est destiné à gérer des comptes d'utilisateur du front-office. Il n'est nécessaire que pour une boutique V2 <b>(et uniquement)</b>", 'warning');

    parent::istep1($ar);

    // Options spécifiques au wizard
    $this->_options->setOpts([
      '_modinfotree' => [
        'type' => 'module',
        'label' => "Gestionnaire de rubriques où créer la page de gestion du compte",
        'options' => ['toid' => XMODINFOTREE_TOID],
      ],
      'field_name' => [
        'label' => 'Champ Nom',
        'type' => 'field',
        'options' => ['table' => 'USERS', 'compulsory' => false],
        'comment' => "Laisser vide pour créer le champ s'il n'existe pas",
      ],
      'field_forename' => [
        'label' => 'Champ Prénom',
        'type' => 'field',
        'options' => ['table' => 'USERS', 'compulsory' => false],
        'comment' => "Laisser vide pour créer le champ s'il n'existe pas",
      ],
    ]);
    // les options partagées avec le module
    $this->_options->setOpts(FrontUsers::getCommonOptions());
  }

  public function iend($ar = []) {
    $new_moid = parent::iend($ar);
    $alias = $this->_module->alias_home;
    if ($this->_module->_modinfotree && $alias) {
      $modinfotree = \Seolan\Core\Module\Module::objectFactory($this->_module->_modinfotree);
      $oid_rub = $modinfotree->getOidFromAlias($alias);
      if ($modinfotree->getOidFromAlias($alias)) {
        \Seolan\Core\Shell::alert("Alias $alias déjà existant dans le module ".$modinfotree->getLabel());
      } else {
        $new = $modinfotree->_categories->procInput([
          '_local' => true,
          'alias' => $alias,
          'title' => 'Compte utilisateur',
        ]);
        $oid_rub = $new['oid'];
      }
      // Insertion de la section de gestion du compte
      $modinfotree->insertfunction([
        '_local' => true,
        'oidit' => $oid_rub,
        'oidtpl' => getDB()->fetchOne('SELECT KOID FROM TEMPLATES WHERE functions LIKE ?', ['%::account']),
        'section' => [
          'moid' => $new_moid,
          'function' => 'account',
        ],
      ]);
    }

    // Table des utilisateurs
    $USERS = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('USERS');

    // Création du champ CIVILITE
    if (!$USERS->fieldExists('civilite')) {
      $USERS->updateDesc([
        'civilite' => [
          'label' => 'Civilité',
          'ftype' => '\Seolan\Field\StringSet\StringSet',
          'forder' => 7,
        ],
      ]);
      $USERS->desc['civilite']->newString('Mme', 'mme');
      $USERS->desc['civilite']->newString('Mlle', 'mlle');
      $USERS->desc['civilite']->newString('Mr', 'mr');
    }

    // Création du champ NOM
    if (!$this->_module->field_name) {
      $USERS->updateDesc([
        'name' => [
          'label' => 'Nom',
          'ftype' => '\Seolan\Field\ShortText\ShortText',
          'fcount' => 100,
        ],
      ]);
    }

    // Création du champ PRENOM
    if (!$this->_module->field_forename) {
      $USERS->updateDesc([
        'forename' => [
          'label' => 'Prénom',
          'ftype' => '\Seolan\Field\ShortText\ShortText',
          'fcount' => 100,
        ],
      ]);
    }

    // Création du champ système de date de création de l'utilisateur
    if (!$USERS->fieldExists('CREAD')) {
      $USERS->updateDesc([
        'CREAD' => [
          'label' => 'Date de création',
          'ftype' => '\Seolan\Field\DateTime\DateTime',
          'forder' => 2,
        ],
      ]);
    }

    // Création du champ CIVILITE livraison
    if (!$USERS->fieldExists('fcivilite')) {
      $USERS->updateDesc([
        'fcivilite' => [
          'label' => 'Civilité (livraison)',
          'ftype' => '\Seolan\Field\StringSet\StringSet',
          'forder' => 7,
        ],
      ]);
      $USERS->desc['fcivilite']->newString('Mme', 'mme');
      $USERS->desc['fcivilite']->newString('Mlle', 'mlle');
      $USERS->desc['fcivilite']->newString('Mr', 'mr');
    }

    // Création du champ NOM livraison
    if (!$USERS->fieldExists('fnom')) {
      $USERS->updateDesc([
        'fnom' => [
          'label' => 'Nom (livraison)',
          'ftype' => '\Seolan\Field\ShortText\ShortText',
          'fcount' => 100,
        ],
      ]);
    }

    // Création du champ PRENOM livraison
    if (!$USERS->fieldExists('fprenom')) {
      $USERS->updateDesc([
        'fprenom' => [
          'label' => 'Prénom (livraison)',
          'ftype' => '\Seolan\Field\ShortText\ShortText',
          'fcount' => 100,
        ],
      ]);
    }

    return $new_moid;
  }

  public function quickCreate($ar = []) {
    \Seolan\Core\Shell::alert("Pas de création rapide pour ce module");
    return;
  }

}
