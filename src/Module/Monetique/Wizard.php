<?php
namespace Seolan\Module\Monetique;
use \Seolan\Core\Labels;
/**
 * Classe du wizard d'installation des modules héritant de \Seolan\Module\Monetique\Monetique.
 */

class Wizard extends \Seolan\Core\Module\Wizard {

  const BACKENDS = [
    'Atos' =>  '\\\Seolan\\\Module\\\Monetique\\\Atos\\\Atos',
    'AtosV2' =>  '\\\Seolan\\\Module\\\Monetique\\\Atos\\\Atosv2',
    'Axepta' => '\\\Seolan\\\Module\\\Monetique\\\Axepta\\\Axepta',
    'Monetico' => '\\\Seolan\\\Module\\\Monetique\\\Monetico\\\Monetico',
    'Paybox' => '\\\Seolan\\\Module\\\Monetique\\\Paybox\\\Paybox',
    'PayFip' => '\\\Seolan\\\Module\\\Monetique\\\PayFip\\\PayFip',
    'Paypal' => '\\\Seolan\\\Module\\\Monetique\\\Paypal\\\Paypal',
    'Paypal Braintree' => '\\\Seolan\\\Module\\\Monetique\\\PaypalBraintree\\\PaypalBraintree',
    'PayPlug' => '\\\Seolan\\\Module\\\Monetique\\\PayPlug\\\PayPlug',
    'SaferPay' => '\\\Seolan\\\Module\\\Monetique\\\SaferPay\\\SaferPay',
    'ShareGroop' => '\\\Seolan\\\Module\\\Monetique\\\ShareGroop\\\ShareGroop',
    'SystemPay' => '\\\Seolan\\\Module\\\Monetique\\\SystemPay\\\SystemPay'
  ];

  /**
   * \brief Méthode de génération des champs de la table du module : TRANSACMONETIQUE
   */
  static function getFieldsDefinitionsTRANSACMONETIQUE() {
    $fieldsDefinitions = [];
    $forder = 2;
    // Champs orderOid
    $fieldsDefinitions['orderOid'] = [
      'field' => 'orderOid', 'label' => 'Commande', 'ftype' => '\Seolan\Field\Link\Link', 'fcount' => 0, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0,
      'target' => '%',
      'options' => ['autocomplete' => 1, 'autocomplete_limit' => 0]];

    // Champs customerOid
    $fieldsDefinitions['customerOid'] = [
      'field' => 'customerOid', 'label' => 'Client', 'ftype' => '\Seolan\Field\Link\Link', 'fcount' => 0, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0,
      'target' => '%', 'options' => ['autocomplete' => 1, 'autocomplete_limit' => 0]];

    // Champs customerEmail
    $fieldsDefinitions['customerEmail'] = [
      'field' => 'customerEmail', 'label' => 'Customer Email', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 64, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs orderReference
    $fieldsDefinitions['orderReference'] = [
      'field' => 'orderReference', 'label' => 'Référence commande', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 64, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 1];

    // Champs dateCreated
    $fieldsDefinitions['dateCreated'] = [
      'field' => 'dateCreated', 'label' => 'Date de création', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs responseCode
    $fieldsDefinitions['responseCode'] = [
      'field' => 'responseCode', 'label' => 'Code réponse de la banque', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs amount
    $fieldsDefinitions['amount'] = [
      'field' => 'amount', 'label' => 'Montant', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs status
    $fieldsDefinitions['status'] = [
      'field' => 'status', 'label' => 'Statut de la transaction', 'ftype' => '\Seolan\Field\StringSet\StringSet', 'fcount' => 0, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs type
    $fieldsDefinitions['type'] = [
      'field' => 'type', 'label' => 'Type de la transaction', 'ftype' => '\Seolan\Field\StringSet\StringSet', 'fcount' => 0, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    $fieldsDefinitions['nbDeadLine'] = [
      'field' => 'nbDeadLine', 'label' => 'Nombres d\'échéances', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs statusComplement
    $fieldsDefinitions['statusComplement'] = [
      'field' => 'statusComplement', 'label' => 'Complément de statut', 'ftype' => '\Seolan\Field\Text\Text', 'fcount' => 80, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs callParms
    $fieldsDefinitions['callParms'] = [
      'field' => 'callParms', 'label' => 'Paramètre appel', 'ftype' => '\Seolan\Field\Text\Text', 'fcount' => 80, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 0, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs responseParms
    $fieldsDefinitions['responseParms'] = [
      'field' => 'responseParms', 'label' => 'Paramètre retour', 'ftype' => '\Seolan\Field\Text\Text', 'fcount' => 80, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 0, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs responseStatus
    $fieldsDefinitions['responseStatus'] = [
      'field' => 'responseStatus', 'label' => 'Status notfication réponse à la boutique', 'ftype' => '\Seolan\Field\StringSet\StringSet', 'fcount' => 0, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs dateTimeOut
    $fieldsDefinitions['dateTimeOut'] = [
      'field' => 'dateTimeOut', 'label' => 'Date et heure de l\'appel en banque', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs dateTimeIn
    $fieldsDefinitions['dateTimeIn'] = [
      'field' => 'dateTimeIn', 'label' => 'Date et heure du retour banque', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs captureMode
    $fieldsDefinitions['captureMode'] = [
      'field' => 'captureMode', 'label' => 'Mode de Capture', 'ftype' => '\Seolan\Field\StringSet\StringSet', 'fcount' => 0, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs captureDelay
    $fieldsDefinitions['captureDelay'] = [
      'field' => 'captureDelay', 'label' => 'Délais de la capture', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs transOri
    $fieldsDefinitions['transOri'] = [
      'field' => 'transOri', 'label' => 'Transaction d\'origine', 'ftype' => '\Seolan\Field\Link\Link', 'fcount' => 0, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0,
      'target' => 'TRANSACMONETIQUE', 'options' => ['autocomplete' => 1, 'autocomplete_limit' => 0]];

    // Champs refAbonneBoutique
    $fieldsDefinitions['refAbonneBoutique'] = [
      'field' => 'refAbonneBoutique', 'label' => 'Référence abonné boutique', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 64, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs shopMoid
    $fieldsDefinitions['shopMoid'] = [
      'field' => 'shopMoid', 'label' => 'Module boutique', 'ftype' => '\Seolan\Field\Module\Module', 'fcount' => 5, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs shopClass
    $fieldsDefinitions['shopClass'] = [
      'field' => 'shopClass', 'label' => 'Classe de la boutique', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 64, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs shopName
    $fieldsDefinitions['shopName'] = [
      'field' => 'shopName', 'label' => 'Nom de la boutique', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 64, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs autoResponseMode
    $fieldsDefinitions['autoResponseMode'] = [
      'field' => 'autoResponseMode', 'label' => 'Mode de reponse', 'ftype' => '\Seolan\Field\StringSet\StringSet', 'fcount' => 0, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs shopCallBack
    $fieldsDefinitions['shopCallBack'] = [
      'field' => 'shopCallBack', 'label' => 'CallBack boutique', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 64, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs monetiqueMoid
    $fieldsDefinitions['monetiqueMoid'] = [
      'field' => 'monetiqueMoid', 'label' => 'Module monétique', 'ftype' => '\Seolan\Field\Module\Module', 'fcount' => 5, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs transId
    $fieldsDefinitions['transId'] = [
      'field' => 'transId', 'label' => 'Identifiant de la transaction', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 64, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs nbReturn
    $fieldsDefinitions['nbReturn'] = [
      'field' => 'nbReturn', 'label' => 'Nombre de retour banque', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];

    // Champs ip
    $fieldsDefinitions['ip'] = [
      'field' => 'ip', 'label' => 'Adresse IP banque', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];
    // Champs userAgent
    $fieldsDefinitions['userAgent'] = [
      'field' => 'userAgent', 'label' => 'User Agent', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 120, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 0, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];
    // Infos customer
    $fieldsDefinitions['customerInfos'] = [
      'field' => 'customerInfos', 'label' => 'Infos client', 'ftype' => '\Seolan\Field\Text\Text', 'fcount' => 80, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 0, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];
    return $fieldsDefinitions;
  }

  /**
   * \brief Méthode de génération des champs de la table du module : ENROMONETIQUE
   */
  static function getFieldsDefinitionsENROMONETIQUE() {
    $fieldsDefinitions = [];
    $forder = 2;
    // Champs refAbonne
    $fieldsDefinitions['refAbonne'] = [
      'field' => 'refAbonne', 'label' => 'Référence de l\'abonné', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 64, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];
    // Champs dateVal
    $fieldsDefinitions['dateVal'] = [
      'field' => 'dateVal', 'label' => 'Date de validité de la carte de l\'abonné', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];
    // Champs porteur
    $fieldsDefinitions['porteur'] = [
      'field' => 'porteur', 'label' => 'Numéro de porteur de la carte de l\'abonnée', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 30, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0,];
    // Champs cvv
    $fieldsDefinitions['cvv'] = [
      'field' => 'cvv', 'label' => 'Code cvv de la carte de l\'abonné', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];
    // Champs transOri
    $fieldsDefinitions['transOri'] = [
      'field' => 'transOri', 'label' => 'Transaction d\'origine de l\'abonnement', 'ftype' => '\Seolan\Field\Link\Link', 'fcount' => 0, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0,
      'target' => 'TRANSACMONETIQUE', 'options' => ['autocomplete' => 1, 'autocomplete_limit' => 0]];
    // Champs numCarte
    $fieldsDefinitions['numCarte'] = [
      'field' => 'numCarte', 'label' => 'Numéro de carte du porteur', 'ftype' => '\Seolan\Field\ShortText\ShortText', 'fcount' => 20, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];
    // Champs customerOid
    $fieldsDefinitions['customerOid'] = [
      'field' => 'customerOid', 'label' => 'Client', 'ftype' => '\Seolan\Field\Link\Link', 'fcount' => 0, 'forder' => $forder++,
      'compulsory' => 0, 'queryable' => 1, 'browsable' => 1, 'translatable' => 0, 'multivalued' => 0, 'published' => 0,
      'target' => '%', 'options' => ['autocomplete' => 1, 'autocomplete_limit' => 0]];
    // Champs monetiqueMoid
    $fieldsDefinitions['monetiqueMoid'] = [
      'field' => 'monetiqueMoid', 'label' => 'Module monétique', 'ftype' => '\Seolan\Field\Module\Module', 'fcount' => 5, 'forder' => $forder++,
      'compulsory' => 1, 'queryable' => 1, 'browsable' => 0, 'translatable' => 0, 'multivalued' => 0, 'published' => 0];
    return $fieldsDefinitions;
  }

  /**
   * \brief Méthode de création de la table TRANSACMONETIQUE
   */
  static function createStructureTRANSACMONETIQUE() {
    /// Si la table n'existe pas, on la crée
    if (!\Seolan\Core\System::tableExists('TRANSACMONETIQUE')) {
      \Seolan\Model\DataSource\Table\Table::procNewSource([
        'translatable' => 0,
        'auto_translate' => 0,
        'btab' => 'TRANSACMONETIQUE',
        'bname' => [TZR_DEFAULT_LANG => 'MNT - Transaction'],
        'own' => false,
        'publish' => false,
        'cread' => true
      ]);
    }
    $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('TRANSACMONETIQUE');
    // Récupération de la liste des champs qui doivent constituer la table
    $fieldsDefinitions = self::getFieldsDefinitionsTRANSACMONETIQUE();
    foreach ($fieldsDefinitions as $fn => $fielddefinition) {
      $x->createField($fn, $fielddefinition['label'], $fielddefinition['ftype'], $fielddefinition['fcount'],
        $fielddefinition['forder'], $fielddefinition['compulsory'], $fielddefinition['queryable'],
        $fielddefinition['browsable'], $fielddefinition['translatable'], $fielddefinition['multivalued'],
        $fielddefinition['published'], $fielddefinition['target'], $fielddefinition['options']);
    }
    /* Définition des ensembles de chaines présents dans la table */
    // Champs status
    $x->desc['status']->newString('En cours de traitement', 'running');
    $x->desc['status']->newString('Opération traitée avec succés', 'success');
    $x->desc['status']->newString('Opération en attente', 'waitting');
    $x->desc['status']->newString('Error', 'error');
    $x->desc['status']->newString('Opération invalide', 'invalid');
    // Champs type
    $x->desc['type']->newString('Paiement web', 'webpayment');
    $x->desc['type']->newString('Paiement MPOS', 'mpos');
    $x->desc['type']->newString('Remboursement', 'refund');
    $x->desc['type']->newString('Mise à jour carte', 'update');
    $x->desc['type']->newString('Prélèvement sur un "abonné/enrôlé"', 'subscdebit');
    // Champs responseStatus
    $x->desc['responseStatus']->newString('A notifier', 'tosend');
    $x->desc['responseStatus']->newString('Notifié', 'sent');
    $x->desc['responseStatus']->newString('En attente de réponse banque, pour notification', 'none');
    $x->desc['responseStatus']->newString('N/A', 'nottosend');
    // Champs captureMode
    $x->desc['captureMode']->newString('Autorisation + débit', 'capture');
    $x->desc['captureMode']->newString('Autorisation seule', 'autorisation');
    // Champs autoResponseMode
    $x->desc['autoResponseMode']->newString('Notifier la boutique en arrière plan', 'async');
    $x->desc['autoResponseMode']->newString('Notifier la boutique dès réception de la réponse', 'sync');
    $x->desc['autoResponseMode']->newString('Ne pas notifier la boutique', 'none');
  }

  /**
   * \brief Méthode de création de la table ENROMONETIQUE
   */
  static function createStructureENROMONETIQUE() {
    if (!\Seolan\Core\System::tableExists('ENROMONETIQUE')) {
      \Seolan\Model\DataSource\Table\Table::procNewSource([
        'translatable' => 0,
        'auto_translate' => 0,
        'btab' => 'ENROMONETIQUE',
        'bname' => [TZR_DEFAULT_LANG => 'MNT - Enrollement'],
        'own' => false,
        'publish' => false,
        'cread' => true
      ]);
    }
    $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('ENROMONETIQUE');
    // Récupération de la liste des champs qui doivent constituer la table
    $fieldsDefinitions = self::getFieldsDefinitionsENROMONETIQUE();
    foreach ($fieldsDefinitions as $fn => $fielddefinition) {
      // ajout du champ
      $x->createField($fn, $fielddefinition['label'], $fielddefinition['ftype'], $fielddefinition['fcount'],
        $fielddefinition['forder'], $fielddefinition['compulsory'], $fielddefinition['queryable'],
        $fielddefinition['browsable'], $fielddefinition['translatable'], $fielddefinition['multivalued'],
        $fielddefinition['published'], $fielddefinition['target'], $fielddefinition['options']);
    }
  }

  /**
   * \brief Crée le module du groupe monétique sur la table donnée.
   */
  function createEnrollementModule() {
    // On vérifie si un module utilise cette table
    $exist = \Seolan\Core\Module\Module::modulesUsingTable('ENROMONETIQUE', true);
    if ($exist) {
      return key($exist);
    }
    
    $mod = new \Seolan\Module\Table\Wizard(['newmoid' => 25]);
    $mod->_module->modulename = 'Enrollement';
    $mod->_module->group = $this->_module->group;
    $mod->_module->theclass = '';
    $mod->_module->table = 'ENROMONETIQUE';
    $mod->_module->trackchanges = 1;
    $mod->_module->available_in_display_modules = 0;
    
    return  $mod->iend();

  }

  /**
   * \brief Première étape de l'installation : quel kit (backend=>theclass)
   */
  public function istep1() {
    parent::istep1();
    $this->_options->setOpt('BackEnd', 'theclass', 'list',
			    ['labels' => array_keys(static::BACKENDS), 
			     'values' => array_values(static::BACKENDS)]);
  }

  public function istep2() {
    // Identifiant du site fournis par la banque
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Monetique','siteId'), 'siteId', 'text');
    // Url de retour pour paiement accepté
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Monetique','urlPayed'), 'urlPayed', 'text');
    $this->_module->urlPayed = '/paiement-carte-ok.html';
    // Url de retour pour paiement refusé
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Monetique', 'urlCancelled'), 'urlCancelled', 'text');
    $this->_module->urlCancelled = '/paiement-carte-ko.html';
    // Url de retour automatique
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Monetique', 'urlAutoResponse'), 'urlAutoResponse', 'text');
    $this->_module->urlAutoResponse = '/csx/scripts/monetique-retour-auto.php?moid='.$this->_moid;
    // Insertion des propriétées spécifique à chaque module
    $this->insertSpecificProperties();
    // On mémorise les évolutions
    $this->_module->trackchanges = true; 
    // Par défaut le module est en mode test
    $this->_module->_testmode = true;
    $this->_module->btab = 'TRANSACMONETIQUE';
    $this->_module->table = $this->_module->btab;
  }

  /**
   * \brief Étape finale de l'installation.
   */
  function iend($ar = NULL) {
    // Création de la table TRANSACMONETIQUE
    $this->createStructureTRANSACMONETIQUE();
    // Création table et module ENROMONETIQUE
    $this->createStructureENROMONETIQUE();

    // save / restaure du contexte ModWd du wizard en cours
    // et traitment du module enrollements
    $mywddata = getSessionVar('ModWd');
    clearSessionVar('ModWd');

    $moidEnrollement = $this->createEnrollementModule();

    setSessionVar('ModWd', $mywddata);

    // On mémorise les évolutions
    $this->_module->trackchanges = true;
    // Par défaut le module est en mode test
    $this->_module->_testmode = true;
    $this->_module->ssmodtitle1 = 'Enrollements';
    $this->_module->ssmodfield1 = 'transOri';
    $this->_module->ssmod1 = $moidEnrollement;
    // On récupère le moid du module que l'on vient de créer
    $moid = parent::iend();
    // On l'instancie
    $mod = \Seolan\Core\Module\Module::ObjectFactory($moid);
    $mod->available_in_display_modules = false;
    // On affecte ce moid en paramètre de l'url de retour auto
    $mod->procEditProperties(['options' => ['urlAutoResponse' => '/csx/scripts/monetique-retour-auto.php?moid=' . $moid]]);
    $this->iendSpecific();
    return $moid;
  }

  function insertSpecificProperties() {
    switch ($this->_module->theclass) {
      case '\Seolan\Module\Monetique\Atos\Atos':
        // Identifiant du site fournis par ATOS (Les valeurs renseignées correspondent aux valeurs de tests)
        $this->_module->siteId = '040136211600000';
        // Logo de paiement ok
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Atos_Atos', 'logoRetourOk'), 'logoRetourOk', 'text');
        // Logo de paiement ko
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Atos_Atos', 'logoRetourKo'), 'logoRetourKo', 'text');
        // Chemin d'installation de l'api ATOS
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Atos_Atos', 'path'), 'path', 'text');
        $this->_module->path = '/home/' . $GLOBALS['HOME'] . '/sips/'; ///< Par défaut '/home/site/sips/'
        break;
      case '\Seolan\Module\Monetique\Monetico\Monetico':
        $this->_options->setOpt('Code société', 'companyCode', 'text', [], '');
        // Clé publique pour la vérification de signature
        $this->_options->setOpt('Clé', 'hashKey', 'text', ['size' => 80], '');
        break;
      case '\Seolan\Module\Monetique\Paybox\Paybox':
        // Identifiant du site fournis par Paybox (Les valeurs renseignées correspondent aux valeurs de tests)
        $this->_module->siteId = '1999888';
        // Rang du site (fournis par Paybox)
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'rang'), 'rang', 'text');
        $this->_module->rang = '32';
        // Identifiant du commerçant
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'identifiant'), 'identifiant', 'text');
        $this->_module->identifiant = '107975626';
        // Clé publique pour la vérification de signature
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'hashKey'), 'hashKey', 'text');
        $this->_module->hashKey = '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF';
        // Algorithme utilisé pour le calcul de la signature
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'algoHash'), 'algoHash', 'list',
          ['values' => Paybox\Paybox::codeHash, 'labels' => Paybox\Paybox::libelleHash]);
        // Url principale du serveur Paybox
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'formurlprincipale'), 'formFirstUrl', 'text');
        $this->_module->formFirstUrl = '';
        // Url du serveur de pré-production Paybox
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'formUrlPreProd'), 'formUrlPreProd', 'text');
        $this->_module->formUrlPreProd = 'https://preprod-tpeweb.paybox.com/cgi/MYchoix_pagepaiement.cgi';
        // Chemin du fichier contenant la clé publique
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'keyFile'), 'keyFile', 'text');
        $this->_module->keyFile = 'tzr/pubkey.pem';
        // Url de retour pour paiement accepté
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'urlPppsTest'), 'urlPPPStest', 'text');
        $this->_module->urlPPPStest = 'https://preprod-ppps.paybox.com/PPPS.php';
        // Url principal de dial de serveur à serveur
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'urlPpps1'), 'urlPPPS1', 'text');
        $this->_module->urlPPPS1 = '';
        // Url secondaire de dial de serveur à serveur
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'urlPpps2'), 'urlPPPS2', 'text');
        $this->_module->urlPPPS2 = '';
        // Clé pour le dial de serveur à serveur
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'clePpps'), 'clePPPS', 'text');
        $this->_module->clePPPS = '1999888I';
        break;
      case '\Seolan\Module\Monetique\Paypal\Paypal':
        // Mail du client
        $this->_module->siteId = 'sample@hotmail.fr';
        // Nom d'utilisateur
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'user'), 'user', 'text');
        $this->_module->user = 'sample_api1.hotmail.fr';
        // Password
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'password'), 'password', 'text');
        $this->_module->password = '';
        // Signature
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'signature'), 'signature', 'text');
        $this->_module->signature = '';
        // Url du serveur de pré-production Paypal
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'formurlpreprod'), 'formUrlPreProd', 'text');
        $this->_module->formUrlPreProd = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        // Url du serveur de production Paypal
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'formurlprod'), 'formUrlProd', 'text');
        $this->_module->formUrlProd = 'https://www.paypal.com/cgi-bin/webscr';
        // Url de l'API de pré-production Paypal
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'urlapipaypalpreprod'), 'urlApiPaypalPreProd', 'text');
        $this->_module->urlApiPaypalPreProd = 'https://api-3t.sandbox.paypal.com/nvp';
        // Url de l'API de production Paypal
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'urlapipaypalprod'), 'urlApiPaypalProd', 'text');
        $this->_module->urlApiPaypalProd = 'https://api-3t.paypal.com/nvp';
        break;
      case '\Seolan\Module\Monetique\SystemPay\SystemPay':
        // Certificat de test (fournis par SystemPay)
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_SystemPay_SystemPay', 'certificatTest'), 'certificatTest', 'text');
        // Certificat de production (fournis par SystemPay)
        $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_SystemPay_SystemPay', 'certificatProd'), 'certificatProd', 'text');
        break;
      case '\Seolan\Module\Monetique\PayFip\PayFip':
        $this->_options->delOpt('urlPayed');
        $this->_options->delOpt('urlCancelled');
        $this->_options->setOpt('Url de retour client', 'urlBack', 'text', ['size' => 60]);
        break;
      case '\Seolan\Module\Monetique\ShareGroop\ShareGroop':
        $this->_options->delOpt('siteId');
        $this->_options->setOpt('Clé publique de test', 'publicKeyTest', 'text', ['size' => 40], null, 'ShareGroop');
        $this->_options->setOpt('Clé secrète de test', 'secretKeyTest', 'text', ['size' => 40], null, 'ShareGroop');
        $this->_options->setOpt('Clé secrète webhook de test', 'webhookSecretKeyTest', 'text', ['size' => 40], null, 'ShareGroop');
        $this->_options->setOpt('Clé publique de prod', 'publicKeyProd', 'text', ['size' => 40], null, 'ShareGroop');
        $this->_options->setOpt('Clé secrète de prod', 'secretKeyProd', 'text', ['size' => 40], null, 'ShareGroop');
        $this->_options->setOpt('Clé secrète webhook de prod', 'webhookSecretKeyProd', 'text', ['size' => 40], null, 'ShareGroop');
        $this->_options->setOpt('Limite de temps de partage (en jours, max 6)', 'sharingLimit', 'integer', null, 5, 'ShareGroop');
        break;
    }
  }

  function iendSpecific() {
    switch ($this->_module->theclass) {
      case '\Seolan\Module\Monetique\PayFip\PayFip':
        if (!getDB()->count('SHOW INDEX FROM TRANSACMONETIQUE where Column_name="transId"')) {
          getDB()->execute("alter table TRANSACMONETIQUE add index(transId)");
        }
        break;
    }
  }
}
