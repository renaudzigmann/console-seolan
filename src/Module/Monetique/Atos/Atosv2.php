<?php

namespace Seolan\Module\Monetique\Atos;

/**
 * \brief Classe \Seolan\Module\Monetique\Atos\Atosv2.
 * Classe de paiement ATOS/SIPS v2 - (utilisé aussi par BNP Mercanet)
 *
 * https://documentation.sips.worldline.com/fr
 * https://documentation.sips.worldline.com/fr/WLSIPS.003-GD-Presentation-Fonctionnelle.html
 * https://documentation.sips.worldline.com/fr/WLSIPS.001-GD-Dictionnaire-des-donnees.html
 * https://documentation.sips.worldline.com/fr/WLSIPS.804-MG-Guide-de-correspondance-des-donnees-1.0-2.0.html
 * https://documentation.sips.worldline.com/fr/WLSIPS.317-UG-Sips-Paypage-POST.html
 * https://documentation.sips.worldline.com/fr/WLSIPS.310-UG-Sips-Office-JSON.html
 * https://documentation.sips.worldline.com/en/WLSIPS.001-GD-Data-dictionary.html#Sips.001_DD_en-Value-acquirerResponseCode_
 * https://documentation.sips.worldline.com/en/WLSIPS.001-GD-Data-dictionary.html#Sips.001_DD_en-Value-responseCode_
 */
 /*
 * !!Attention en mode Test le transID peut être déjà utilisé par la boutique d'un autre site de test!!
 * https://documentation.sips.worldline.com/fr/cartes-de-test.html
 * exemple Carte pour la plateforme de TEST
 * 5017679110380400  : CB retour OK
 * 5017679110380905  : CB retour 05
 *
 * https://documentation.sips.worldline.com/fr/WLSIPS.326-UG-Guide-de-demarrage-rapide.html
 * exemple Cartes pour la plateforme de Recette
 * 4200000000000000   : CB retour OK
 * 4100000000000005   : VISA with error 05
 */
 /* Payment Pattern
 * ONE_SHOT 	Paiement à l'acte (valeur par défaut)
 * INSTALMENT 	paiement plusieurs fois
 * RECURRING_1 	paiement récurrent 1er paiement
 * RECURRING_N 	paiement récurrent Nème paiement
 */
 /* Différences avec sipsv1
 * - le serveur Sips xsalto n'est plus utile
 * - il n'y a plus de parametre pour l'url de retour Boutique en erreur
 * - le merchantId est associé à une clé secrète et à une version de cette clé (cas où elle doit être regénérée)
 * - un type d'erreur supplémentaire à gérer : cas où le seal de la réponse n'est pas égal au seal calculé
 * Différence d'implémentation
 * - le transactionOid est transféré via merchantSessionId afin de libérer returnContext (utile à RefugIT)
 * - Le diagnostique n'est pas systématiquement utilisé avant un remboursement (économie facturation de services)
 * - le choix des cartes pour le formulaire de paiement n'est pas affiché avant l'accès au formulaire de paiement par défaut (getFormCardTypes)
 */
use \Seolan\Module\Monetique\Atos\Library\OfficeJson;
use \Seolan\Module\Monetique\Atos\Library\PayPagePost;

class Atosv2 extends \Seolan\Module\Monetique\Monetique {

  protected $debug = false;
  protected $debug_autoresponse = false;
  protected $needTransId = true;
  protected $oneClickPossible = true; // seulement avec SIPS Office Server
  protected $currentMerchantId = null;
  
  protected $paypageLib = null;
  protected $officeJsonLib = null;
  //constantes (surchargeables)
  const VERSION = '2.0';
  const SEAL_ALGORITHM = 'HMAC-SHA-256';
  const PAYPOST_URLS = [
    'TEST' => 'https://payment-webinit.test.sips-services.com/paymentInit',
    'RECETTE' => 'https://payment-webinit.simu.sips-services.com/paymentInit',
    'PROD' => 'https://payment-webinit.sips-services.com/paymentInit',
  ];
  const OFFICEJSON_PREFIXURLS = [
    'TEST' => 'https://office-server.test.sips-services.com/rs-services/v2/',
    'RECETTE' => 'https://office-server.test.sips-services.com/rs-services/v2/',
    'PROD' => 'https://office-server.sips-services.com/rs-services/v2/',
  ];
  const OFFICE_FUNCTIONS = [
    'diagnostic' => 'diagnostic',
    'duplicate' => 'duplicate',
    'cancel' => 'cancel',
    'refund' => 'refund',
  ];
  
  // Options spécifiques au module
  public $version = self::VERSION;
  public $urlShopResponse = '/csx/scripts/monetique-retour-boutique.php?moid=';
  public $urlAutoResponse = '/csx/scripts/monetique-retour-auto.php?moid=';
  //Les Interfaces Versions sont susceptibles d'être parametrées (dans le futur il ne serait peut être plus obligatoire de les spécifier...?)
  public $payPostInterfaceVersion = 'HP_2.24'; //'HP_2.39'
  public $officeJsonInterfaceVersion = 'CR_WS_2.39';
  public $sipsv1TransIdCompatible = true; // cas d'une migration de boutique v1 vers v2 : conserver la compatibilité des id de transaction : utilisation des champs s10Transaction*
  protected $merchantId_prod = [
    'merchantId' => '034327737200029',//034327737200029 loth
    'key' => 'key',
    'keyVersion' => '1',
  ];
  public $merchantId_prod_merchantId;
  public $merchantId_prod_key;
  public $merchantId_prod_keyVersion;
  // sipsv1TransIdCompatible test merchandIds
  //// merchantId de test compatible transId v1 (migration simplifiée), accepte les duplications
  protected $merchantIdv1_test = [
    'merchantId' => '011122211100080',
    'key' => 'c-vaJ3SLVic62sptc2BHKF_TQvCAoo6ASKffb9HfT0s',
    'keyVersion' => '1',
    'platform' => 'TEST',
  ];
  //// merchantId de test compatible transId v1 (paiement direct seulement?) 3DS Plateforme RECETTE!
  //permet de tester les erreurs 3DS
  protected $merchantIdv1Direct_recette = [
    'merchantId' => '002001000000003',
    'key' => '002001000000003_KEY1',
    'keyVersion' => '1',
    'platform' => 'RECETTE',
  ];
  //sipsv2 test merchantId (accepte les duplications étendues)
  protected $merchantIdv2_test = [
    'merchantId' => '201040027330001',
    'key' => 'TAz6IsirkSIfrI6EEAynLpDWB-80TFlOJdRF8b9rzUs',
    'keyVersion' => '1',
    'platform' => 'TEST',
  ];
  
  public $transIdMask = '%06d'; //id transaction générée
  //Permet de différencier les transId pour la boutique de test partagée avec d'autres sites
  public $testMode_TransIdPaddingReplace = '1';
  public $defaultTemplate = 'Module/Monetique.atos.html'; ///< Template par défaut
  public $templatefile = NULL; ///< Template
  public $cardTypes = [];
  protected $cardTypesAtos = [
    'AMEX' => 'American Express',
    'AURORE' => 'Aurore',
    'CB' => 'CB', ///< On accepte les CB par défaut
    'COFINOGA' => 'Cofinoga',
    'E-CARTEBLEUE' => 'e-carte bleue',
    'MASTERCARD' => 'Eurocard / MasterCard', ///< On accepte les MASTERCARD par défaut
    'JCB' => 'JCB',
    'MAESTRO' => 'Maestro',
    'ONEY' => 'ONEY',
    'ONEY_SANDBOX' => 'ONEY mode SANDBOX',
    'PAYPAL' => 'Paypal',
    'PAYPAL_SB' => 'PAYPAL mode SANDBOX',
    'PAYSAFECARD' => 'PAYSAFECARD',
    'VISA' => 'Visa', ///< On accepte les VISA par défaut
    'VISA_ELECTRON' => 'Visa Electron',
    'COF3XCB' => '3x CB Cofinoga ',
    'COF3XCB_SB' => '3x CB Cofinoga SANDBOX',
  ];
  
  public function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->merchantId_prod = [
      'merchantId' => $this->merchantId_prod_merchantId,
      'key' => $this->merchantId_prod_key,
      'keyVersion' => $this->merchantId_prod_keyVersion,
    ];
  }
  /**
   * \brief Fonction d'initialisation des options spécifiques à ATOS.
   * \note
   * Crée les champs:
   * - path \link \Seolan\Module\Monetique\Atos\Atos::$path \endlink
   */
  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Atos_Atos','modulename');
    
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Monetique', 'urlAutoResponse'), 'urlAutoResponse', 'text',
      ['size' => 120], $this->urlAutoResponse, $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Atos_Atos', 'urlShopResponse'),
      'urlShopResponse', 'text', ['size' => 120], $this->urlShopResponse,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Atos_Atos', 'urlPayed'),
      'urlPayed', 'text', ['size' => 120], $this->urlPayed,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Atos_Atos', 'urlCancelled'),
      'urlCancelled', 'text', ['size' => 120], $this->urlCancelled, $alabel);
    
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Atos_Atos', 'version'), 'version', 'text', NULL, $this->version, $alabel);
    $this->_options->setRO('version');
    $this->_options->setOpt('Prefixe ID Transaction pour la boutique de test (partagée avec d\'autres sites)',
     'testMode_TransIdPaddingReplace', 'text', NULL, $this->testMode_TransIdPaddingReplace, $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Atos_Atos', 'sipsv1TransIdCompatible'), 'sipsv1TransIdCompatible', 'boolean', NULL, true, $alabel);
    $this->_options->setOpt('PayPost Interface Version', 'payPostInterfaceVersion', 'text', NULL,
      $this->payPostInterfaceVersion, $alabel);
    $this->_options->setOpt('OfficeJson Interface Version', 'officeJsonInterfaceVersion', 'text', NULL,
      $this->officeJsonInterfaceVersion, $alabel);
    
    $this->_options->setOpt('Template atos', 'templatefile', 'text', NULL, NULL, $alabel);
    
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Atos_Atos', 'merchantId_prod'),
      'merchantId_prod_merchantId', 'text', NULL, $this->merchantId_prod['merchantId'], $alabel);
    $this->_options->setOpt('Production key', 'merchantId_prod_key', 'text', NULL, $this->merchantId_prod['key'], $alabel);
    $this->_options->setOpt('Production key version', 'merchantId_prod_keyVersion', 'text', NULL, $this->merchantId_prod['keyVersion'], $alabel);
    
    $this->_options->delOpt('siteId');
    
    /*TODO?
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Monetique', 'cardtype'), 'cardTypes', 'multiplelist',
      ['values' => array_keys($this->cardTypesAtos), 'labels' => array_values($this->cardTypesAtos)], NULL, $alabel);
    */
  }
  
  /* Fonctions de paiement version WEB */

  /**
   * \brief Méthode de génération des données de paiement.
   * Cette fonction permet de générer les données d'un paiement ATOS.
   * \param \Seolan\Module\Monetique\Model\Order $order :  Objet des données issues de la commande.
   * \param \Seolan\Module\Monetique\Model\Customer $customer : Objet des données issues du client.
   * \param \Seolan\Module\Monetique\Model\Shop $shop : Objet comportant les paramètres de la boutique.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction en cours de paramètrage.
   * \return Array :
   * - \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction contenant tous les paramètres d'appel.
   * - Array $webPaymentAtosForm : Contient le formulaire envoyé en banque.
   * - String $template : Le template correspondant correspondant un module de traitement (TZR_SHARE_DIR.$this->defaultTemplate).
   * - String $tplentry : L'entrée smarty du template : 'webPaymentAtosForm'.
   * \note
   * - Crée le formulaire à envoyer en banque.
   * - Retourne la transaction en cours, le formulaire envoyé en banque ainsi que le template et son entrée.
   */
  protected function webPaymentHandling(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    $webPaymentAtosForm['fields'] = $this->webPaymentAtosForm($transaction);
    return [$transaction, $webPaymentAtosForm, TZR_SHARE_DIR . $this->defaultTemplate, 'atosForm'];
  }
  
  protected function buildWebPaymentParams(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    if ($transaction->captureMode == self::AUTHORIZATION_ONLY) {
      $capture_mode = 'VALIDATION';
    } else {
      $capture_mode = 'AUTHOR_CAPTURE';
    }
    $requestData = [
      //'cancelReturnUrl' => $this->urlCancelled, //non implémenté
      'amount' => $this->formatOrderAmount($transaction->amount),
      'currencyCode' => $this->defaultCurrencyCode,
      'orderChannel' => 'INTERNET',
      'responseEncoding' => 'base64',
      'captureMode' => $capture_mode,
      'captureDay' => $transaction->captureDelay,
      'orderId' => $transaction->orderReference,
      'customerId' =>  preg_replace('@^(\w+:)@', '', $transaction->customerOid),
      'customerEmail' => $transaction->customerEmail,
      'customerLanguage' => $transaction->lang,
      'returnContext' => $transaction->returnContext,
    ];
    //référence envoyée dans le flux de remise en banque, apparaissant sur le compte du porteur (client) (?)
    if ($transaction->statementReference)
      $requestData['statementReference'] = $transaction->statementReference;
    //$transaction->oid (encoder si plusieurs ':')
    $requestData['merchantSessionId'] = urlencode(preg_replace('@^(\w+:)@', '', $transaction->oid));
    if ($this->debug_autoresponse) {
      $requestData['normalReturnUrl'] = $this->urlAutoResponse;
    } else {
      $requestData['normalReturnUrl'] = $this->urlShopResponse ? $this->urlShopResponse : $this->urlAutoResponse;
      $requestData['automaticResponseUrl'] = $this->urlShopResponse ? $this->urlAutoResponse : null;
    }
    if ($this->needTransId) {
      if ($this->sipsv1TransIdCompatible) {
        $requestData['s10TransactionReference'] = ["s10TransactionId" => $transaction->transId];
      } else {
        $requestData['transactionReference'] = date('Ymd').date('His').$transaction->transId; //15 char min - 35 char max
      }
    }
    
    // Préparation des paramètres de paiement à multiples échéances
    //TODO!! : check and test
    if ($transaction->nbDeadLine > 1) {
      $requestData['paymentPattern'] = 'INSTALMENT';
      $reste = $this->formatOrderAmount($transaction->amount) % $transaction->nbDeadLine;
      $montantDivise = $this->formatOrderAmount($transaction->amount) / $transaction->nbDeadLine;
      $montantDivise = explode('.', $montantDivise);
      $montant = $montantDivise[0];
      $montant1 = $montant + $reste;
      $dates = [date('Ymd')];
      $diffDays = new DateInterval('P'.$transaction->frequencyDuplicate.'D');
      for($i=0; $i<$transaction->nbDeadLine-1; $i++) {
        $_date = new \DateTime($dates[$i]);
        $_date->add($diffDays);
        $dates[] = $_date->format('Ymd');
      }
      $requestData['instalmentData'] = [
        'number' => $transaction->nbDeadLine,
        'datesList' => implode(',',$dates),
        'amountsList' => "$montant1," . implode(',', array_fill(0, $transaction->nbDeadLine-1, "$montant")),
      ];
      if ($this->sipsv1TransIdCompatible) {
        //$requestData['instalmentData']['s10TransactionIdsList'] = "$transaction->transId,";//??
      } else {
        //$requestData['instalmentData']['transactionReferencesList'] = "";//??
      }
    }
    if ($this->templatefile) {
      $requestData['templatefile'] = $this->templatefile;
    }
    $this->setCurrentMerchantId($transaction);
    $requestData['merchantId'] = $this->currentMerchantId['merchantId'];
    $requestData['keyVersion'] = $this->currentMerchantId['keyVersion'];
    return $requestData;
  }
  
  protected function getFormCardTypes() {
    return '';
  }
  
   /**
   * \brief Méthode de génération du formulaire de paiement.
   * Cette fonction permet de générer le formulaire de paiement ATOS.
   * \param \Seolan\Module\Monetique\Model\Order $order :  Objet des données issues de la commande.
   * \param \Seolan\Module\Monetique\Model\Customer $customer : Objet des données issues du client.
   * \param \Seolan\Module\Monetique\Model\Shop $shop : Objet comportant les paramètres de la boutique.
   * \param String $transactionOid : L'oid de la transaction est cours de paramètrage.
   * \return Array :
   * - Array $parms : Contient le formulaire envoyé en banque.
   * \note
   * - Mise en forme du montant (centimes). \link \Seolan\Module\Monetique\Monetique::formatOrderAmount($amount); \endlink
   * - Paiement en euros par défaut.
   * - Si $order->options['noCapture'] est valorisé à true alors on ne fait qu'une demande d'autorisation, sinon capture par défaut.
   * - Gestion du paiement multiple si la commande indique plusieurs échéances. \link \Seolan\Module\Monetique\Model\Order::$options \endlink
   * - Gestion du mode TEST ou PRODUCTION.
   * - Gestion des abonnement.
   * - Gestion des délais de capture.
   * - Génération de la signature.
   */
  protected function webPaymentAtosForm(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    if (empty($this->paypageLib))
      $this->paypageLib = new \Seolan\Module\Monetique\Atos\Library\PayPagePost();
    
    if ($this->debug) \Seolan\Core\Logs::debug(__METHOD__ . ' transaction ', var_export($transaction, 1));
    
    // Gestion du mode de capture (Capture par défaut, sinon autorisation seulement)
    $interfaceVersion = $this->payPostInterfaceVersion;
    if ($this->testMode()) {
      if ($transaction->captureMode == self::AUTHORIZATION_ONLY)
        $interfaceVersion = 'HP_2.39';
      else
        $interfaceVersion = 'HP_2.24';
    }
    $requestData = $this->buildWebPaymentParams($transaction);
    $transaction->callParms = $requestData;
    
    $dataStr = $this->paypageLib->flatten_to_sips_payload($requestData);
    $dataStrEncode = base64_encode($dataStr);
    $seal = $this->paypageLib->compute_seal_from_string(self::SEAL_ALGORITHM, $dataStrEncode, $this->currentMerchantId['key']);
    if ($this->debug) \Seolan\Core\Logs::debug(__METHOD__." seal=".$seal);
    $data = $dataStrEncode;
    $submitLabel = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Monetique_Monetique','pay');
    $form = '<form id="sips_form" method="POST" action="'.$this->currentMerchantId['paypostUrl'].'" target="_top">
        <input type="hidden" name="Data" value="'.$data.'"/>
        <input type="hidden" name="InterfaceVersion" value="'.$interfaceVersion.'"/>
        <input type="hidden" name="Seal" value="'.$seal.'"/>
        <input type="hidden" name="SealAlgorithm" value="'.self::SEAL_ALGORITHM.'"/>
        <input type="hidden" name="Encode" value="base64"/>'.
        $this->getFormCardTypes().
        '<input type = "submit" value = "'.$submitLabel.'">
      </form>';
    
    if ($this->debug) \Seolan\Core\Logs::debug(__METHOD__ . ' params= '. var_export($requestData, 1));
    if ($this->debug_autoresponse) {
      echo "REQUEST";
       echo '<style>
       table{
          font-family: arial, sans-serif;
          border-collapse: collapse;
          width: 75%;
       }
       td, th{
          border: 1px solid #dddddd;
          text-align: left;
          padding: 8px;
       }
       tr:nth-child(even){
          background-color: #dddddd;
       }
       </style>
       <table>
       <tr>
          <th><h3>Field Name</h3></th>
          <th><h3>Value</h3></th>
       </tr>';
       foreach($requestData as $key => $value){
          $val = (is_array($value) && count($value)) ? implode(',',array_keys($value)).'--'.implode(',',array_values($value)) : $value;
          echo '<tr>
          <td>'.$key.'</td>
          <td>'.$val.'</td>
          </tr>';
       }
        echo '<tr>
          <td>Computed seal</td>
          <td>'.$seal.'</td>
          </tr>';
        echo '<tr>
          <td>Use Interface Version</td>
          <td>'.$interfaceVersion.'</td>
          </tr>';
       echo '</table>';
     }
    return $form;
  }
  
  protected function setCurrentMerchantId(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    if ($this->testMode()) {
      if ($this->sipsv1TransIdCompatible) {
        $this->currentMerchantId = $this->merchantIdv1_test;
      } else {
         $this->currentMerchantId = $this->merchantIdv2_test;
      }
      $this->currentMerchantId['paypostUrl'] = self::PAYPOST_URLS[$this->currentMerchantId['platform']];
    } else {
      $this->currentMerchantId = $this->merchantId_prod;
      $this->currentMerchantId['paypostUrl'] = self::PAYPOST_URLS['PROD'];
    }
  }

  /**
   * \brief Méthode de traitement du retour banque ATOS.
   * Méthode de traitement du retour banque ATOS, afin de transamettre des paramètres standards à \link \Seolan\Module\Monetique\Monetique::autoresponse() \endlink
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le retour automatique.
   * \note
   * - Récupère de toutes les valeurs attendu par ATOS, présentes dans $_REQUEST.
   * - Mémorise les paramètres de retour de la transaction sous une forme normalisée pour faciliter le traitement en aval et dans Xmodmonetique.
   * - Vérifie la signature de la reponse. \link \Seolan\Module\Monetique\Paybox\Paybox::verificationSignature($retour, $signature); \endlink
   * - Affecte le status de la transaction.
   * - Récupère l'oid de la transaction grâce à urldecode($params['return_context']);
   */
  protected function webPaymentUnFoldReponse() {
    // Extraction des paramètres
    $params = $this->extractAutoresponse();
    $transaction = $this->getReturnTransaction($params);

    //NOTICE: cas d'erreur de comparaison des seal traité comme une erreur standard, or l'opération peut avoir été traitée
    if ($params['code'] != 0 || $params['code'] != '00') {
      $transaction->status = self::INVALID;
      $transaction->statusComplement = 'Error message : ' . $params['code'] . ' : ' . $params['error'];
    } else {
      $transaction->status = $transaction->responseCode === '00' ? self::SUCCESS : self::ERROR;
    }
    return $transaction;
  }
  
  protected function getCurrentMerchantIdData($merchantId) {
     switch($merchantId) {
       case $this->merchantId_prod['merchantId']:
        $this->currentMerchantId = $this->merchantId_prod;
        break;
       case $this->merchantIdv1_test['merchantId']:
        $this->currentMerchantId = $this->merchantIdv1_test;
        $this->sipsv1TransIdCompatible = true;
        break;
       case $this->merchantIdv1Direct_recette['merchantId']:
        $this->currentMerchantId = $this->merchantIdv1Direct_recette;
        $this->sipsv1TransIdCompatible = true;
        break;
       case $this->merchantIdv2_test['merchantId']:
        $this->currentMerchantId = $this->merchantIdv2_test;
        $this->sipsv1TransIdCompatible = false;
        break;
       default:
        throw new Exception(__METHOD__ . ' erreur traitement réponse : erreur merchantId= ' . $merchantId);
        break;
    }
  }

  protected function extractAutoresponse() {
    if (empty($this->paypageLib))
      $this->paypageLib = new \Seolan\Module\Monetique\Atos\Library\PayPagePost();
    if(isset($_POST['Data'])) $rawData = $_POST['Data'];
    if(isset($_POST['Encode'])) $encode = $_POST['Encode'];
    if(isset($_POST['Seal'])) $seal = $_POST['Seal'];
    if(isset($_POST['InterfaceVersion'])) $interfaceVersion = $_POST['InterfaceVersion'];
    if(strcmp($encode, "base64") == 0) {
      $data = base64_decode($rawData);
    } else {
      $data = $rawData;
    }
    $singleDimArray = explode("|", $data);
    foreach($singleDimArray as $value) {
      $fieldTable = explode("=", $value);
      $key = $fieldTable[0];
      $value = $fieldTable[1];
      $responseData[$key] = $value;
      unset($fieldTable);
    }
    
    $this->getCurrentMerchantIdData($responseData['merchantId']);
    $computedResponseSeal = $this->paypageLib->compute_seal_from_string(self::SEAL_ALGORITHM, $_POST['Data'], $this->currentMerchantId['key']);
    if ($this->debug_autoresponse) {
        echo "RESPONSE";
        echo '<style>
         table{
            font-family: arial, sans-serif;
            border-collapse: collapse;
            width: 75%;
         }
         td, th{
            border: 1px solid #dddddd;
            text-align: left;
            padding: 8px;
         }
         tr:nth-child(even){
            background-color: #dddddd;
         }
         </style>
         <table>
         <tr>
            <th><h3>Field Name</h3></th>
            <th><h3>Value</h3></th>
         </tr>';
        foreach($responseData as $key => $value){
          echo '<tr>
          <td>'.$key.'</td>
          <td>'.$value.'</td>
          </tr>';
        }
        echo '<tr>
          <td>Got seal</td>
          <td>'.$seal.'</td>
          </tr>';
        echo '<tr>
          <td>Computed seal</td>
          <td>'.$computedResponseSeal.'</td>
          </tr>';
        echo '<tr>
          <td>Got Interface Version</td>
          <td>'.$interfaceVersion.'</td>
          </tr>';
        echo '</table>';
    }
    if(strcmp($computedResponseSeal, $seal) !== 0) {
      if ($this->debug_autoresponse) {
        echo "Error : Seals are not equal\n\n".PHP_EOL;
        echo "Got seal=$seal".PHP_EOL;
        echo "Got InterfaceVersion=$interfaceVersion".PHP_EOL;
        echo "Class sealAlgo=".self::SEAL_ALGORITHM.PHP_EOL;
        echo "secretKey=".$this->currentMerchantId['key'].PHP_EOL;
        echo "_____________________________computed seal=$computedResponseSeal".PHP_EOL;
      }
      \Seolan\Core\Logs::critical(__METHOD__.' erreur traitement réponse : seal incorrect : ' . $seal . ' / ' . $computedResponseSeal);
      $responseData['code'] = 'sealNOK';
    } else {
      $responseData['code'] = $responseData['responseCode'];
    }
    return $responseData;
  }

  /**
   * Lecture des données de l'autoresponse
   */
  protected function getReturnTransaction($params) {
    $transaction = new \Seolan\Module\Monetique\Model\Transaction();
    $transaction->oid = $this->table.':'.urldecode($params['merchantSessionId']);
    $transaction->responseCode = isset($params['acquirerResponseCode']) ? $params['acquirerResponseCode'] : $params['responseCode'];
    $transaction->dateVal = substr($params['panExpiryDate'], 4, 2) . substr($params['panExpiryDate'], 2, 2);
    $transaction->amount = sprintf('%.02f', $params['amount'] / 100);
    $numCarte = explode('.', $params['maskedPan']);
    $transaction->numCarte = chunk_split($numCarte[0] . 'XXXXXXXXXX' . $numCarte[1], 4, ' ');
    if ($this->sipsv1TransIdCompatible) {
      $transaction_id = $params['s10TransactionId'];
      //$date = $params['s10TransactionIdDate']; see getInfoTransOri
    } else {
      $transaction_id = $params['transactionReference'];
      //$o = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $params['transactionDateTime']);
      //$datetime = $o ? $o->format('Y-m-d H:i:s') : null;
    }
    
    $transaction->transId = $transaction_id;
    $transaction->captureDelay = $params['captureDay'];
    if ($params['captureMode'] == 'AUTHOR_CAPTURE' || $params['captureMode'] == 'PAYMENT_N') {
      $transaction->captureMode = self::CATCH_PAYMENT;
    } else {
      $transaction->captureMode = self::AUTHORIZATION_ONLY;
    }

    if (!empty($params['complementaryInfo'])) {
      $transaction->statusComplement = $params['complementaryInfo'];
    } else {
      $transaction->statusComplement = $this->getErrorCode($transaction->responseCode);
    }
    // un seul retour, pas d'appel supplementaire sur paiement fractionné
    $transaction->nbDeadLine = 1;
    if (isset($params['number'])) {
      $transaction->nbDeadLine = $params['number'];
    }
    $transaction->cvv = 'N/A';
    $transaction->porteur = 'N/A';
    $transaction->responseParms = $params;
    return $transaction;
  }

  //déterminer si il y a eu une erreur de paiement ou non
  //Afficher la page boutique qui convient
  public function shopResponse() {
     try {
      $transaction = $this->webPaymentUnFoldReponse();
      \Seolan\Core\Logs::critical(__METHOD__ . ' Transaction: ' . var_export($transaction, 1));
    } catch (\Exception $e) {
      return $this->shopResponseError('Exception : ' . $e->getMessage() . ' Code : ' . $e->getCode());
    }
    // Si l'oid de la transaction n'a pas été trouvé par le module approprié, on crée une nouvelle transaction pour mémoriser les paramètres reçus.
    if ($transaction->oid == null) {
      $transaction->statusComplement .= ' Transaction d\'origine non trouvée';
      return $this->shopResponseError('unknown transaction');
    }
    
    if ($transaction->status != self::SUCCESS) {
      return $this->shopResponseError($transaction->statusComplement);
    } else {
      return $this->shopResponseOk();
    }
  }
  // Retour Boutique réponse client
  protected function shopResponseError($error) {
    \Seolan\Core\Logs::critical(__METHOD__ , $error);
    header('Location: '.$this->urlCancelled);
    exit(0);
  }

  protected function shopResponseOk() {
    header('Location: '.$this->urlPayed);
    exit(0);
  }
  //-------------------------------------------------------------------------------------
  /* Fonctions de remboursement d'un abonné */

  /**
   * \brief Méthode de traitement d'un remboursement ATOS.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La nouvelle transaction correspondant au remboursement.
   * Doit contenir dans responseParms les paramètre de retour de la transaction à l'origine du remboursement.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le remboursement après l'appel banque.
   * \note
   * - Récupération des informations nécéssaires au remboursement (renseigne $transaction->numTransOri(avec le numero de transaction et $transaction->fromDate).
   * - Création du formulaire de remboursement.
   * - Mémorisation des paramètres d'appel.
   * - Traitement du retour.
   * - Si le serveur n'a pas répondu:
   *  - $transaction->status = \link \Seolan\Module\Monetique\Monetique::WAITTING \endlink, afin qu'elle puisse être rejouée.
   * - Sinon traitement habituel avec mise en forme des paramètres.
   */
  protected function refundHandling($transaction, $replay = false) {
    if (empty($this->officeJsonLib))
      $this->officeJsonLib = new \Seolan\Module\Monetique\Atos\Library\OfficeJson();
    $url = self::OFFICEJSON_PREFIXURLS['PROD'].'cashManagement/refund';
    $secretKey = $this->merchantId_prod['key'];
    $keyVersion = $this->merchantId_prod['keyVersion'];
    list($numTransOri, $fromDate, $amountOri, $merchantId) = $this->getInfoTransOri($transaction);
    if ($this->testMode(true)) {
      if (!$replay)
        $this->getCurrentMerchantIdData($merchantId);
      else
        $this->getCurrentMerchantIdData($transaction->_callParms['merchantId']);
      $url = self::OFFICEJSON_PREFIXURLS[$this->currentMerchantId['platform']].'cashManagement/refund';
      $secretKey = $this->currentMerchantId['key'];
      $keyVersion = $this->currentMerchantId['keyVersion'];
    }
    
    if (empty($replay)) {
      $transaction->numTransOri = $numTransOri;
      $transaction->fromDate = $fromDate;
      $transaction->merchantId = $merchantId;
      // Création du formulaire de remboursement
      $transaction->callParms = $this->refundAtosForm($transaction);
      $seal = $this->officeJsonLib->compute_payment_init_seal(self::SEAL_ALGORITHM, $transaction->callParms, $secretKey);
      $transaction->callParms['seal'] = $seal;
      $transaction->callParms['keyVersion'] = $keyVersion;
      
      // Mémorisation des paramètres d'appel
      $appel['oid'] = $transaction->oid;
      $appel['callParms'] = $transaction->callParms;
      $appel['options'] = ['callParms' => ['raw' => true, 'toxml' => true]];
      $this->xset->procEdit($appel);
    }
    $result = $this->sendOfficeRequest($url, $transaction->callParms, $secretKey);
    if ($result['responseCode'] == '24' && $result['newStatus'] == 'TO_CAPTURE') {
      //Transaction non envoyée en banque => utiliser cancel à la place de refund
      $url = str_replace('refund', 'cancel', $url);
      $result = $this->sendOfficeRequest($url, $transaction->callParms, $secretKey);
    }
    
    if ($result == false) {
      $transaction->status = self::WAITTING;
      $transaction->statusComplement = 'Serveur indisponible le ' . date('d-m-Y') . ' à : ' . date('H:i:s');
      return $transaction;
    } else if ($result['sealOK'] == false) {
      $transaction->status = self::WAITTING;
      $transaction->statusComplement = 'Erreur de comparaison des seal';
      return $transaction;
    }
    $transaction->responseParms = $result;
    if ($transaction->responseParms['response_code'] == '00') {
      $transaction->status = self::SUCCESS;
    } elseif(in_array($transaction->responseParms['responseCode'], ['90','99','91','97','98'])) {
      //https://documentation.sips.worldline.com/en/WLSIPS.001-GD-Data-dictionary.html#Sips.001_DD_en-Value-acquirerResponseCode_
      //https://documentation.sips.worldline.com/en/WLSIPS.001-GD-Data-dictionary.html#Sips.001_DD_en-Value-responseCode_
      $transaction->status = self::WAITTING;
    } else {
      $transaction->status = self::ERROR;
    }
    $transaction->statusComplement = $this->getErrorCode($transaction->responseParms['response_code']);
    return $transaction;
  }

  /**
   * \brief Méthode de génération du formulaire de remboursement qui sera envoyé à la banque.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction correspondant au remboursement.
   * \return Array $params : Tableau contenant les paramètres à transamettre en banque pour éfféctuer le remboursement.
   */
  protected function refundAtosForm($transaction) {
    // Construction du formulaire
    $merchantId = $this->merchantId_prod['merchantId'];
    if ($this->testMode(true)) {
      $merchantId = $transaction->merchantId;
    }
    $params = [
      'merchantId' => $merchantId,
      'currencyCode' => $this->defaultCurrencyCode,
      'operationOrigin' => 'console seolan',
      'orderChannel' => 'INTERNET',
      'interfaceVersion' => $this->officeJsonInterfaceVersion,
      'operationAmount' => $this->formatOrderAmount($transaction->amount)
    ];
    if ($this->sipsv1TransIdCompatible) {
      $params['s10TransactionReference'] = ['s10TransactionId' => $transaction->numTransOri, 's10TransactionIdDate'=> $transaction->fromDate];
    } else {
      $params['transactionReference'] = $transaction->numTransOri;
    }
    return $params;
  }
  
  /**
   * \brief Méthode de ré-émissions des remboursement en attente (Serveur indisponible).
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction correspondant au remboursement qui sera mise à jour pendant la ré-émission.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le remboursement après sa ré-émission.
   */
  protected function refundReplay($transaction) {
    $replay = true;
    return $this->refundHandling($transaction, $replay);
  }

  /* Fonctions de débit forcé d'un abonné */

  /**
   * \brief Méthode de traitement d'une duplication ATOS.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La nouvelle transaction correspondant à la duplication.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par la duplication après l'appel banque.
   * \note
   * - Récupération des informations nécéssaires à la duplication (renseigne $transaction->transOri(avec le numero de transaction à la place du KOID de la transaction et $transaction->fromDate).
   * - Création du formulaire envoyer.
   * - Mémorise les paramètres d'appel.
   * - Appel de duplication.
   * - Traitement du retour.
   */
  protected function duplicateHandling($transaction, $replay = false) {
    if (empty($this->officeJsonLib))
      $this->officeJsonLib = new \Seolan\Module\Monetique\Atos\Library\OfficeJson();
    $url = self::OFFICEJSON_PREFIXURLS['PROD'].'cashManagement/duplicate';
    $secretKey = $this->merchantId_prod['key'];
    $keyVersion = $this->merchantId_prod['keyVersion'];
    list($numTransOri, $fromDate, $amountOri, $merchantId) = $this->getInfoTransOri($transaction);
    if ($this->testMode(true)) {
      if (!$replay)
        $this->getCurrentMerchantIdData($merchantId);
      else
        $this->getCurrentMerchantIdData($transaction->_callParms['merchantId']);
      $url = self::OFFICEJSON_PREFIXURLS[$this->currentMerchantId['platform']].'cashManagement/duplicate';
      $secretKey = $this->currentMerchantId['key'];
      $keyVersion = $this->currentMerchantId['keyVersion'];
    }
    if ($replay) {
      $result = $this->sendOfficeRequest($url, $transaction->_callParms, $secretKey);
    } else {
      $transaction->numTransOri = $numTransOri;
      $transaction->fromDate = $fromDate;
      $transaction->merchantId = $merchantId;
      // Création du formulaire envoyer
      $transaction->callParms = $this->duplicateAtosForm($transaction);
      
      $seal = $this->officeJsonLib->compute_payment_init_seal(self::SEAL_ALGORITHM, $transaction->callParms, $secretKey);
      $transaction->callParms['seal'] = $seal;
      $transaction->callParms['keyVersion'] = $keyVersion;
      // Mémorisation des paramètres d\'appel
      $appel['oid'] = $transaction->oid;
      $appel['callParms'] = $transaction->callParms;
      $appel['dateTimeOut'] = date('Y-m-d H:i:s');
      $appel['options'] = ['callParms' => ['raw' => true, 'toxml' => true]];
      $this->xset->procEdit($appel);
      $result = $this->sendOfficeRequest($url, $transaction->callParms, $secretKey);
    }
    if ($result == false) {
      $transaction->status = self::WAITTING;
      $transaction->statusComplement = 'Serveur indisponible le ' . date('d-m-Y') . ' à : ' . date('H:i:s');
      return $transaction;
    }
    if ($result['sealOK'] == false) {
      //Malgré cette erreur l'opération peut avoir été traitée
      $transaction->status = self::WAITTING;
      $transaction->statusComplement = 'Erreur de comparaison des seal';
      return $transaction;
    }
    $transaction->responseParms = $result;
    \Seolan\Core\Logs::critical(__METHOD__, print_r($result, true));
    $transaction->responseCode = $transaction->responseParms['acquirerResponseCode'];
    if ($this->sipsv1TransIdCompatible) {
      $transaction->transId = $transaction->responseParms['s10TransactionId'];
    } else {
      $transaction->transId = $transaction->responseParms['transactionReference'];
    }
    
    if ($transaction->responseParms['acquirerResponseCode'] == '00') {
      $transaction->status = self::SUCCESS;
    } elseif(in_array($transaction->responseParms['responseCode'], ['90','99','91','97','98'])) {
      //https://documentation.sips.worldline.com/en/WLSIPS.001-GD-Data-dictionary.html#Sips.001_DD_en-Value-acquirerResponseCode_
      //https://documentation.sips.worldline.com/en/WLSIPS.001-GD-Data-dictionary.html#Sips.001_DD_en-Value-responseCode_
      $transaction->status = self::WAITTING;
    } else {
      $transaction->status = self::ERROR;
    }
    $transaction->statusComplement = $this->getErrorCode($transaction->responseParms['responseCode']);
    // Mise à jour des paramètres de la transaction
    return $transaction;
  }

  /**
   * \brief Méthode de génération du formulaire de duplication qui sera envoyé à la banque.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction à dupliquer.
   * \return Array $param : Tableau contenant les paramètres à transamettre en banque pour éfféctuer la duplication.
   */
  protected function duplicateAtosForm($transaction) {
    if ($transaction->captureMode == self::CATCH_PAYMENT) {
      $captureMode = 'AUTHOR_CAPTURE';
    } else {
      $captureMode = 'VALIDATION';
    }
    $merchantId = $this->merchantId_prod['merchantId'];
    if ($this->testMode(true)) {
      $merchantId = $transaction->merchantId;
    }
    
    $requestData = [
      'transactionOrigin' => 'console seolan',
      'merchantId' => $merchantId,
      'amount' => $this->formatOrderAmount($transaction->amount),
      'currencyCode' => $this->defaultCurrencyCode,
      'captureMode' => $captureMode,
      'captureDay' => $transaction->captureDelay,
      //'expirationDate' => '',
      'orderId' => $transaction->orderReference,
      'orderChannel' => 'INTERNET',
      'interfaceVersion' => $this->officeJsonInterfaceVersion,
    ];
    if ($this->sipsv1TransIdCompatible) {
      $requestData['s10TransactionReference'] = ['s10TransactionId' => $transaction->transId];
      $requestData['s10FromTransactionReference'] = ['s10FromTransactionId'=> $transaction->numTransOri, 's10FromTransactionIdDate' => $transaction->fromDate];
    } else {
      $requestData['transactionReference'] = date('Ymd').date('His').$transaction->transId; //15 char min - 35 char max
      $requestData['fromTransactionReference'] = $transaction->numTransOri;
    }
    return $requestData;
  }

  /**
   * \brief Méthode de ré-émissions des duplications en attente (Serveur indisponible).
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction correspondant au remboursement qui sera mise à jour pendant la ré-émission.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le remboursement après sa ré-émission.
   */
  protected function duplicateReplay($transaction) {
    return duplicateHandling($transaction, TRUE);
  }
  
    /* Fonctions de diagnotique d'une transaction (si fonctionnalité activée sur la boutique) */

  /**
   * \brief Méthode de diagnotique d'une transaction ATOS. (si fonctionnalité activée)
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction à dignostiquer.
   * \return Array:
   * - String $result : 'serveurHS', 'reponseEmpty' ou le résultat en XML
   * - Array $retourDiag : Un tableau associatif contenant le résultat du diagnotique ou null (si le serveur est HS par exemple).
   * \note
   * - Création du formulaire de diagnostique.
   * - Execution de la requête.
   */
  protected function diagnosticHandling($transaction) {
    if (empty($this->officeJsonLib))
      $this->officeJsonLib = new \Seolan\Module\Monetique\Atos\Library\OfficeJson();
    $url = self::OFFICEJSON_PREFIXURLS['PROD'].'diagnostic/getTransactionData';
    $secretKey = $this->merchantId_prod['key'];
    $keyVersion = $this->merchantId_prod['keyVersion'];
    list($transaction->numTransOri, $transaction->fromDate, $amountOri, $transaction->merchantId) = $this->getInfoTransOri($transaction);
    if ($this->testMode(true)) {
      $this->getCurrentMerchantIdData($transaction->merchantId);
      $url = self::OFFICEJSON_PREFIXURLS[$this->currentMerchantId['platform']].'diagnostic/getTransactionData';
      $secretKey = $this->currentMerchantId['key'];
      $keyVersion = $this->currentMerchantId['keyVersion'];
    }
    
    // Création du formulaire de diagnostique
    $atosParams = $this->diagnosticAtosForm($transaction);
    $seal = $this->officeJsonLib->compute_payment_init_seal(self::SEAL_ALGORITHM, $atosParams, $secretKey);
    $atosParams['seal'] = $seal;
    $atosParams['keyVersion'] = $keyVersion;
    
    // Execution de la requête
    return $this->sendOfficeRequest($url, $atosParams, $secretKey);
  }

  /**
   * \brief Méthode de génération du formulaire de diagnostique qui sera envoyé à la banque.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction à diagnostiquer.
   * \return Array $params : Tableau contenant les paramètres à transmettre en banque pour effectuer le diagnostique.
   */
  protected function diagnosticAtosForm($transaction) {
    $params = [
      'merchantId' => $transaction->merchantId,
      'orderChannel' => 'INTERNET',
      'interfaceVersion' => str_replace('CR_','DR_',$this->officeJsonInterfaceVersion),
    ];
    if ($this->sipsv1TransIdCompatible) {
      $params['s10TransactionReference'] = ['s10TransactionId' => $transaction->numTransOri, 's10TransactionIdDate'=> $transaction->fromDate];
    } else {
      $params['transactionReference'] = $transaction->numTransOri;
    }
    return $params;
  }


  /* Fonctions utilitaires */
  protected function sendOfficeRequest($url, $data, $secretKey) {
    if (empty($this->officeJsonLib))
      $this->officeJsonLib = new \Seolan\Module\Monetique\Atos\Library\OfficeJson();
    $requestJson = json_encode($data, JSON_UNESCAPED_UNICODE, 512);
    if ($this->debug) \Seolan\Core\Logs::debug(__METHOD__." requestJson=".var_export($requestJson, 1));
    if ($this->debug) \Seolan\Core\Logs::debug(__METHOD__." url=".$url);
   
    //SENDING REQUEST
    $option = array(
      'http' => array(
         'method' => 'POST',
         'header' => "content-type: application/json;charset=utf-8",
         'content' => $requestJson
      ),
    );
    $context = stream_context_create($option);
    $responseJson = file_get_contents($url, false, $context);
    if ($this->debug) \Seolan\Core\Logs::debug(__METHOD__." respJson=".var_export($responseJson, 1));
    if (!$responseJson)
      return false;
    $resp = json_decode($responseJson, true);
    if ($this->debug) \Seolan\Core\Logs::debug(__METHOD__." respTab=".var_export($resp, 1));
   
    //RECALCULATION OF SEAL
    $responseData = [];
    foreach($resp as $key => $value) {
      if (strcasecmp($key, "seal") != 0) {
         $responseData[$key] = $value;
      }
    }
    $computedResponseSeal = $this->officeJsonLib->compute_payment_init_seal(self::SEAL_ALGORITHM, $responseData, $secretKey);
    if ($this->debug) \Seolan\Core\Logs::debug(__METHOD__." computedResponseSeal=".$computedResponseSeal);
    
    if (strcmp($computedResponseSeal, $resp['seal']) == 0){
      $resp['sealOK'] = true;
    } else {
      $resp['sealOK'] = false;
      \Seolan\Core\Logs::critical(__METHOD__." ERROR: Seal NOK");
    }
    return $resp;
  }
  
  /**
   * \brief Fonction de séléction des paramètres de reponse d'une transaction.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction pour laquelle on veut renseigné :
   * - $transaction->numTransOri : L'identifiant de la transaction renvoyé par ATOS.
   * - $transaction->fromDate : La date d'émission du paiement.
   *  - 'credit'.
   *  - 'cancel'.
   *  - 'duplicate'.
   * \param String $service : Le service appelé :
   * - 'credit' : Correspond à un remboursement.
   * - 'cancel' : Corresspond à une annulation.
   * - 'duplicate' : Correspond à une duplication.
   * - 'diagnostic' : Correspond à un diagnotique de la transaction.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction avec les paramètres renseignés.
   */
  protected function getInfoTransOri($transaction) {
    $rs = getDB()->select('select responseParms, amount from ' . $this->xset->getTable() . ' where `KOID`=?', [$transaction->transOri]);
    $res = null;
    if ($rs->rowCount() == 1) {
      $res = $rs->fetch();
      $params = \Seolan\Core\System::xml2array($res['responseParms']);
      /// Récupération du numéro de transaction
      if ($this->sipsv1TransIdCompatible) {
        if (isset($params['s10TransactionReference'])) {
          //Office Json
          $transactionId = $params['s10TransactionReference']['s10TransactionId'];
          $transactionDate = $params['s10TransactionReference']['s10TransactionIdDate'];
        } else {
          //PayPost
          $transactionId = $params['s10TransactionId'];
          $transactionDate = $params['s10TransactionIdDate'];
        }
        $ret = [$transactionId, $transactionDate];
      } else {
        $datetimeObj = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $params['transactionDateTime']);
        $ret = [$params['transactionReference'], ($datetimeObj ? $datetimeObj->format('Ymd') : null)];
      }
      $ret[] = $res['amount'];
      $ret[] = $res['merchantId'];
      return $ret;
    
    } else {
      \Seolan\Core\Logs::critical(__METHOD__,
        'responseParms, amount de la transaction d\'origine ayant pour KOID ' . $transaction->transOri . ' non trouvé!');
      throw new \Exception(__METHOD__ . ' responseParms, amount de la transaction d\'origine ayant pour KOID ' . $transaction->transOri . ' non trouvé!');
    }
  }
  
  protected function genTransId() {
    if (!$this->needTransId) {
      return '';
    }
    $trid = parent::genTransId();
    //La boutique de test est partagée avec d'autres sites
    if ($this->testMode(true))
      $trid = preg_replace('/^0/',$this->testMode_TransIdPaddingReplace,$trid);
    return $trid;
  }

  //NOTICE : non utilisée dans la classe
  /* sipsv2 : hors CB, Visa et Mastercard, les demandes d'annulation ne sont pas traitées entre 22h et ? tous les jours qqsoit capture_day
  * -> Pour tout type de carte le remboursement ou annulation dépend du statut de remise, donc on évite entre 22h et 3h afin d'éviter les erreurs liées au statut de remise
  * -> Pour les duplications, on ne vérifie que les horaires de maintenance afin de limiter les erreurs
  * Si il est souhaité de pouvoir annuler une duplication juste après son execution, utiliser capture_day>0 ?
  */
  public function sipsOfficeOnline($func) {
    if ($func == self::OFFICE_FUNCTIONS['diagnostic'])
      return true;
    
    //Prise en compte des Maintenances SIPS programmées et paramétrées dans local.ini
    $sipsOffStart = \Seolan\Core\Ini::get('sips_maintenance_start');
    if (empty($sipsOffStart))
      $sipsOffStart = '201606120500';
    $sipsOffEnd = \Seolan\Core\Ini::get('sips_maintenance_end');
    if (empty($sipsOffEnd))
      $sipsOffEnd = '201606120800';

    $today = date('YmdHi');
    if (($today >= $sipsOffStart) && ($today <= $sipsOffEnd)) {
      \Seolan\Core\Logs::critical(__METHOD__, 'SIPS set in maintenance mode');
      return false;
    }
    if (in_array($func, [self::OFFICE_FUNCTIONS['duplicate']]))
      return true;

    //Horaires des remises en banque, conséquences possibles sur SIPS Cancel / Refund
    $h = date('Hi');
    return ($h > '0320' && $h < '2150');
  }

  public function editProperties($ar) {
    parent::editProperties($ar);
  }

}
