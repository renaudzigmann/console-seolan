<?php

namespace Seolan\Module\Monetique\Axepta;

/**
 * Classe de paiement BNP Axepta (basé sur CompuTop)
 *
 * the transmitted urls should not contain parameters and response is encrypted except user defined 'Plain' parameter 
 * => use TZR_MONETIQUE_DEFAULT_MOID
 * User defined Parameters :
 *   - RefNr (ns 30) contient le koid de la transaction sans le nom de la table
 *   - UserData (ans 256) contient le moid, orderOid, trOid
 *   - Plain (ans 50) non encrypté (devrait donc être urlencodé) - non utilisé dans cette implémentation 
 * the key (e.g. MerchantId, RefNr) should not be checked case-sentive
 * le code retour succès ne contient que des 0 - les codes retour erreur sont spécifiques à Axepta
 *
 * Intégration pour le paiement : page hébergée chez bnp
 * Autres solutions : PopUp (blocages!) / Iframe
 * customisation page paiement : Ref commande + montant (disponibles aussi : logo + texte libre)
 *
 * Axepta Docs
 * https://docs.axepta.bnpparibas/
 * Page de paiement hébergée chez axepta
 *   https://docs.axepta.bnpparibas/display/DOCBNP/Hosted+Payment+Page+%28HPP%29+-+paymentpage.aspx
 * Formulaire de paiement hébergée chez axepta
 *   https://docs.axepta.bnpparibas/pages/viewpage.action?pageId=33128860
 * Intégration de moyens de paiement alternatifs
 *   https://docs.axepta.bnpparibas/pages/viewpage.action?pageId=6914326
 *
 * Available Operations ( + Batch through sFTP)
 *   https://docs.axepta.bnpparibas/display/DOCBNP/Card+payments+operations
 * Capture modes
 *   https://docs.axepta.bnpparibas/display/DOCBNP/Card+payments+operations#Cardpaymentsoperations-Capture : Manual / Auto / customized Delay
 * Recurring card payments (Subscription) -- not tested
 *   https://docs.axepta.bnpparibas/pages/viewpage.action?pageId=41585166
 * Diagnostic / Demande de statut -- not Tested
 *   https://docs.axepta.bnpparibas/pages/viewpage.action?pageId=4653161
 *   
 * Cancel / Refund -- not Tested
 *   https://docs.axepta.bnpparibas/display/DOCBNP/Card+payments+operations
 *
 * Test environnement : https://docs.axepta.bnpparibas/display/DOCBNP/Test+modes
 * 1) BNP_XXXXX_t Test card protocol : 
 * - pas de 3DSv2 
 * - inquire operation non supportée
 * - SchemeReferenceID (chainage) simulé
 * - one click / subscription non supporté
 * 2) BNP_DEMO_AXEPTA supporte 3DSv2
 *  - SchemeReferenceID (chainage) non supporté
 *  - one click / subscription non supporté
 *  - pas de rapport back office
 * TEST MID : BNP_DEMO_AXEPTA
 * HMAC : 4n!BmF3_?9oJ2Q*z(iD7q6[RSb5)a]A8
 * Blowfish :Tc5*2D_xs7B[6E?w
 * cartes : https://docs.axepta.bnpparibas/display/DOCBNP/Test+Cards+-+Authentication
 *
 * 3) Simulation mode avec production MID + orderDesc = '0000'
 *  - SchemeReferenceID (chainage) non supporté
 */

use \Seolan\Module\Monetique\Axepta\Library\PayPage as PayPageLib;
use \Seolan\Module\Monetique\Axepta\Library\Server as ServerLib;
use \Seolan\Module\Monetique\Axepta\Library\ErrorCodes as ErrorCodes;

class Axepta extends \Seolan\Module\Monetique\Monetique {

  protected $debug = false;
  protected $needTransId = true;
  protected $oneClickPossible = true; // seulement avec SIPS Office Server
  public $merchantId = null;
  public $hmacKey = null;
  public $blowfishKey = null;
  public $defaultCurrencyCode = 'EUR';
  public $captureMode = 'AUTO';
  
  protected $paypageLib = null;
  protected $serverLib = null;

  //constantes (surchargeables)
  const VERSION = '1.0'; //xsalto
  const MSG_VER = '2.0'; //axepta (paiement uniquement)
  
  const DATA_FIELD = 'Data';
  const SHASIGN_FIELD = 'MAC';
  const USER_FIELD = 'UserData';
  const USER_FIELD2 = 'Plain';
  
  const SUCCESS_CODE = '00000000';
  const PAYPAGEURL = "https://paymentpage.axepta.bnpparibas/paymentPage.aspx"; //cartes et autres paiements
  const PAYSSL = "https://paymentpage.axepta.bnpparibas/payssl.aspx"; //cartes seulement
  const DIRECT = "https://paymentpage.axepta.bnpparibas/direct.aspx";
  const DIRECT3D = "https://paymentpage.axepta.bnpparibas/direct3d.aspx";
  const CAPTURE = "https://paymentpage.axepta.bnpparibas/capture.aspx";
  const INQUIRE = "https://paymentpage.axepta.bnpparibas/inquire.aspx";
  const CREDIT = "https://paymentpage.axepta.bnpparibas/credit.aspx";
  const CANCEL = "https://paymentpage.axepta.bnpparibas/reverse.aspx";
  
  const STATUS_CANCELED = 'canceled';
  const STATUS_ERRORCANCEL = 'CANCEL-FAILED';

  
  // Options spécifiques au module
  public $version = self::VERSION;
  public $urlNotify = '/csx/scripts/monetique-retour-auto.php';
  public $urlShopResponse = '/csx/scripts/monetique-retour-boutique.php';
  public $urlPayed = '/order-ok.html';
  public $urlCancelled = '/order-nok.html';
  public $transIdMask = '%064d'; //id transaction générée
  //Permet de différencier les transId pour la boutique de test partagée avec d'autres sites
  public $testMode_TransIdPaddingReplace = '1';
  public $defaultTemplate = 'Module/Monetique.axepta.html'; ///< Template par défaut
  public $templatefile = NULL; ///< Template
  public $cardTypes = [];
  protected $cardTypesAxepta = [
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
  }

  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Axepta_Axepta','modulename');
    
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Axepta_Axepta', 'URLNotify'),
      'urlAutoResponse', 'text', ['size' => 120], $this->urlAutoResponse,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Axepta_Axepta', 'urlShopResponse'),
      'urlShopResponse', 'text', ['size' => 120], $this->urlShopResponse,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Axepta_Axepta', 'URLSuccess'),
      'urlPayed', 'text', ['size' => 120], $this->urlPayed,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Axepta_Axepta', 'URLFailure'),
      'urlCancelled', 'text', ['size' => 120], $this->urlCancelled, $alabel);
    
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Axepta_Axepta', 'version'), 'version', 'text', NULL, $this->version, $alabel);
    $this->_options->setRO('version');
    $this->_options->setOpt('Prefixe ID Transaction pour la boutique de test (partagée avec d\'autres sites)',
     'testMode_TransIdPaddingReplace', 'text', NULL, $this->testMode_TransIdPaddingReplace, $alabel);
    $this->_options->setOpt('Template Axepta', 'templatefile', 'text', NULL, NULL, $alabel);
    
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Axepta_Axepta', 'merchantId'),
      'merchantId', 'text', NULL, $this->merchantId, $alabel);
    $this->_options->setOpt('Merchant secret / HMAC key', 'hmacKey', 'text', NULL, $this->hmacKey, $alabel);
    $this->_options->setOpt('Merchant crypt Blowfish key', 'blowfishKey', 'text', NULL, $this->blowfishKey, $alabel);
    $this->_options->setOpt('Paiement Default Capture Mode (AUTO/MANUAL/xxx Hours)', 'captureMode', 'text', NULL, $this->captureMode, $alabel);
    
    $this->_options->delOpt('siteId');
    
    /*TODO?
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Monetique', 'cardtype'), 'cardTypes', 'multiplelist',
      ['values' => array_keys($this->cardTypesAxepta), 'labels' => array_values($this->cardTypesAxepta)], NULL, $alabel);
    */
  }
  
  protected function formatOrderAmount($amount) {
    return intval($amount * 100);
  }
  
  /* Fonctions de paiement version WEB */

  /**
   * \brief Méthode de génération des données de paiement.
   * Cette fonction permet de générer les données d'un paiement Axepta.
   * \param \Seolan\Module\Monetique\Model\Order $order :  Objet des données issues de la commande.
   * \param \Seolan\Module\Monetique\Model\Customer $customer : Objet des données issues du client.
   * \param \Seolan\Module\Monetique\Model\Shop $shop : Objet comportant les paramètres de la boutique.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction en cours de paramètrage.
   * \return Array :
   * - \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction contenant tous les paramètres d'appel.
   * - Array $webPaymentAxeptaForm : Contient le formulaire envoyé en banque.
   * - String $template : Le template correspondant correspondant un module de traitement (TZR_SHARE_DIR.$this->defaultTemplate).
   * - String $tplentry : L'entrée smarty du template : 'webPaymentAxeptaForm'.
   * \note
   * - Crée le formulaire à envoyer en banque.
   * - Retourne la transaction en cours, le formulaire envoyé en banque ainsi que le template et son entrée.
   */
  protected function webPaymentHandling(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    $webPaymentAxeptaForm['fields'] = $this->webPaymentAxeptaForm($transaction);
    return [$transaction, $webPaymentAxeptaForm, TZR_SHARE_DIR . $this->defaultTemplate, 'axeptaForm'];
  }
  
  protected function buildWebPaymentParams(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    $requestData = [
      'MerchantID' => $this->merchantId,
      'MsgVer' => self::MSG_VER,
      'TransID' => $transaction->transId, // max length : 64
      'RefNr' => preg_replace('@^(\w+:)@', '', $transaction->oid), // max length : 30
      'Amount' => $this->formatOrderAmount($transaction->amount),
      'Currency' => $this->defaultCurrencyCode,
      'Capture' => $this->captureMode,
      'OrderDesc' => $transaction->orderReference, // max length : 768
      'Response' => 'encrypt',
      'accountInfo' => $transaction->customerEmail,
      
      self::USER_FIELD => 'moid='.$this->_moid.'|orderOid='.$transaction->orderOid.'|trOid='.$transaction->oid,
      self::USER_FIELD2 => urlencode('moid='.$this->_moid),
      'URLNotify' => $this->urlAutoResponse,
      'URLSuccess' => $this->urlShopResponse,
      'URLFailure' => $this->urlShopResponse,
    ];
    //référence envoyée dans le flux de remise en banque, apparaissant sur le compte du porteur (client) (?)
    if ($transaction->statementReference)
      $requestData['billingDesbillingDescriptorcriptor'] = $transaction->statementReference;
    
    if ($this->templatefile)
      $requestData['templatefile'] = $this->templatefile;
    
    // Préparation des paramètres de paiement à multiples échéances
    // https://docs.axepta.bnpparibas/pages/viewpage.action?pageId=41585166#Recurringcardpayments(Subscription)-SubscriptionforafixedamountandfrequencySubscriptionforafixedamountandfrequency
    if ($transaction->nbDeadLine > 1) {
      // ** NOTICE : non testé **
      /*
      $startDate = date('Y-m-d');
      $recurringDays = $transaction->nbDeadLine * $transaction->frequencyDuplicate;
      $endDate = date('Y-m-d', strtotime("+$recurringDays DAYS"));
      $recurringParams = [
        "type" => [
          "recurring" => [
            "recurringFrequency" => $transaction->frequencyDuplicate,
            "recurringStartDate" => $startDate,
            "recurringExpiryDate" => $endDate
          ],
        ],
        "initialPayment" => true,
        "useCase" => "fixed"
      ];
      $requestData['credentialOnFile'] = json_encode($recurringParams);
      
      $_3DSParams = ["challengePreference" => 'mandateChallenge'];
      $requestData['threeDSPolicy'] = json_encode($_3DSParams);
      */
    }
    return $requestData;
  }
  
  protected function getFormCardTypes() {
    return '';
  }
  
   /**
   * \brief Méthode de génération du formulaire de paiement.
   * Cette fonction permet de générer le formulaire de paiement Axepta.
   * \param \Seolan\Module\Monetique\Model\Order $order :  Objet des données issues de la commande.
   * \param \Seolan\Module\Monetique\Model\Customer $customer : Objet des données issues du client.
   * \param \Seolan\Module\Monetique\Model\Shop $shop : Objet comportant les paramètres de la boutique.
   * \param String $transactionOid : L'oid de la transaction est cours de paramètrage.
   * \return Array :
   * - Array $parms : Contient le formulaire envoyé en banque.
   * \note
   * - Mise en forme du montant (centimes). \link \self::formatOrderAmount($amount); \endlink
   * - Paiement en euros par défaut.
   * - Paiement capture : Manual / Auto / customized Delay selon param captureMode du module
   * - Gestion du paiement multiple si la commande indique plusieurs échéances (non implémenté). \link \Seolan\Module\Monetique\Model\Order::$options \endlink
   * - Gestion du mode TEST ou PRODUCTION.
   * - Gestion des abonnement (non implémenté).
   * - Gestion des délais de capture : voir captureMode du module
   * - Génération de la signature hMac + encryption blow fish
   */
  protected function webPaymentAxeptaForm(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__ . ' Transaction Oid: ' . $transaction->oid);
    if (empty($this->paypageLib))
      $this->paypageLib = new PayPageLib(['hmacKey' => $this->hmacKey, 'cryptKey' => $this->blowfishKey]);
    
    if ($this->debug) \Seolan\Core\Logs::debug(__METHOD__ . ' transaction ', var_export($transaction, 1));
    
    if ($this->testMode()) {

    }
    $requestData = $this->buildWebPaymentParams($transaction);
    $requestData[self::SHASIGN_FIELD] = $this->paypageLib->getShaSign($requestData);
    $requestData['URLBack'] = $_SERVER['SCRIPT_URI'] . '?' . $_SERVER['QUERY_STRING'];
    $transaction->callParms = $requestData;
    
    list($DATA, $len, $debug) = $this->paypageLib->getBfishCrypt($requestData);
    
    $submitLabel = $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Monetique_Monetique','pay');
    //https://docs.axepta.bnpparibas/pages/viewpage.action?pageId=33128860
    //** Les custom fields ne sont pris en compte qu'avec la méthode GET - https://docs.axepta.bnpparibas/display/DOCBNP/Customize+checkout+experience
    $form = '<form id="axepta_form" method="GET" action="'.self::PAYSSL.'" target="_top">
        <input type="hidden" name="'.self::DATA_FIELD.'" value="'.$DATA.'"/>
        <input type="hidden" name="Len" value="'.$len.'"/>
        <input type="hidden" name="MerchantID" value="'.$this->merchantId.'"/>
        <input type="hidden" name="URLBack" value="'.$requestData['URLBack'].'"/>
        <input type="hidden" name="CustomField1" value="'. round($requestData['Amount']/100,2) . " {$requestData['Currency']}" . '">
        <input type="hidden" name="CustomField2" value="'. $transaction->orderReference . '">
        <input type="hidden" name="Language" value="'. strtolower($transaction->lang) . '">' . $this->getFormCardTypes() .
        '<input type = "submit" value = "'.$submitLabel.'">
      </form>';
      /*
        "<input type=\"hidden\" name=\"CustomField3\" value=\"". $Your_logo_img . "\">" .
        "<input type=\"hidden\" name=\"CustomField8\" value=\"". $Your_miscelaneous text . "\">" .
      */
    
    if ($this->debug) \Seolan\Core\Logs::debug(__METHOD__ . ' params= '. var_export($requestData, 1));
    return $form;
  }


  /**
   * \brief Méthode de traitement du retour banque Axepta.
   * Méthode de traitement du retour banque Axepta, afin de transamettre des paramètres standards à \link \Seolan\Module\Monetique\Monetique::autoresponse() \endlink
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le retour automatique.
   * \note
   * - Récupère de toutes les valeurs attendu par Axepta, présentes dans $_REQUEST.
   * - Mémorise les paramètres de retour de la transaction sous une forme normalisée pour faciliter le traitement en aval et dans Xmodmonetique.
   * - Vérifie la signature de la reponse. \link \Seolan\Module\Monetique\Paybox\Paybox::verificationSignature($retour, $signature); \endlink
   * - Affecte le status de la transaction.
   * - Récupère l'oid de la transaction grâce à urldecode($params['return_context']);
   */
  protected function webPaymentUnFoldReponse() {
    // Extraction des paramètres
    $params = $this->extractAutoresponse();
    
    $transaction = $this->getReturnTransaction($params);
    $params = $this->getResponseParmsCaseInsentive($params);

    //NOTICE: cas d'erreur de comparaison des seal traité comme une erreur standard, or l'opération peut avoir été traitée
    if ($params['code'] != self::SUCCESS_CODE) {
      $transaction->status = self::INVALID;
      $transaction->statusComplement = 'Error Messages : ' . $params['description'] .' : '. $this->getErrorCode($params['code']);
    } else {
      $transaction->status = $transaction->responseCode === self::SUCCESS_CODE ? self::SUCCESS : self::ERROR;
    }
    return $transaction;
  }

  protected function extractAutoresponse() {
    if (empty($this->paypageLib))
      $this->paypageLib = new PayPageLib(['hmacKey' => $this->hmacKey, 'cryptKey' => $this->blowfishKey]);
    
    if (isset($_REQUEST[self::DATA_FIELD]))
      $responseData = $this->paypageLib->getResponse($_REQUEST); //autoresponse
    else {
      $responseData = $_REQUEST; //shop response if not encrypted
      $responseData['MerchantID'] = $_REQUEST['mid'];
    }
    if (!empty($responseData[self::USER_FIELD])) {
      $_userFields = array_filter(explode('|', $responseData[self::USER_FIELD]));
      if (!count($_userFields))
        $_userFields = [$responseData[self::USER_FIELD]];
      foreach($_userFields as $_v) {
        if (empty($_v) || strpos($_v, '=')===false)
          continue;
        $_keyval = explode('=', $_v);
        if (empty($_keyval[0]) || !empty($responseData[$_keyval[0]]))
          continue;
        $responseData[$_keyval[0]] = $_keyval[1];
        if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." --- ".self::USER_FIELD." decode : {$_keyval[0]} => {$_keyval[1]}");
      }
    }
    if (!empty($_REQUEST[self::USER_FIELD2])) { //url encodé car non encrypté
      $_userFields = array_filter(explode('|', urldecode($_REQUEST[self::USER_FIELD2])));
      if (!count($_userFields))
        $_userFields = [urldecode($_REQUEST[self::USER_FIELD2])];
      foreach($_userFields as $_v) {
        if (empty($_v) || strpos($_v, '=')===false)
          continue;
        $_keyval = explode('=', $_v);
        if (empty($_keyval[0]) || !empty($responseData[$_keyval[0]]))
          continue;
        $responseData[$_keyval[0]] = $_keyval[1];
        if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." --- ".self::USER_FIELD2." decode : {$_keyval[0]} => {$_keyval[1]}");
      }
    }
    $computedResponseMAC = $this->paypageLib->computeResponseHMAC($responseData);
    if ($this->debug) {
      \Seolan\Core\Logs::critical(__METHOD__." response=".print_r($responseData,true));
      \Seolan\Core\Logs::critical(__METHOD__." computedHmac=".$computedResponseMAC);
      \Seolan\Core\Logs::critical(__METHOD__." Hmac compare=". (strtoupper($computedResponseMAC) == strtoupper($responseData[self::SHASIGN_FIELD])));
    }
    if (strtoupper($computedResponseMAC) != strtoupper(trim($responseData[self::SHASIGN_FIELD]))) {
      \Seolan\Core\Logs::critical(__METHOD__.' erreur traitement réponse : hMAC incorrect : ' . $responseData[self::SHASIGN_FIELD] . ' / ' . $computedResponseMAC);
      $responseData['Code'] = 'sealNOK';
    }
    
    return $responseData;
  }

  /**
   * Lecture des données de l'autoresponse
   */
  protected function getReturnTransaction($params) {
    $transaction = new \Seolan\Module\Monetique\Model\Transaction();
    //the key (e.g. MerchantId, RefNr) should not be checked case-sentive (!!!) 
    //see https://docs.axepta.bnpparibas/pages/viewpage.action?pageId=33128860
    $responseParms = $this->getResponseParmsCaseInsentive($params);
    $RefNr = $responseParms['refnr'];
    $transaction->oid = $this->table.':'.urldecode($RefNr);
    $transaction->responseCode = $responseParms['code'];
    //MaskedPan can be received??
    //If you want to receive the parameter MaskedPan, please contact Axepta Helpdesk, which can activate the return.
    $transaction->numCarte = $responseParms['maskedpan'] ?? null;
    $transaction->transId = $responseParms['transid'];
    $transaction->captureDelay = 0;
    $transaction->statusComplement = $responseParms['description'];
    $transaction->responseParms = $responseParms;
    
    return $transaction;
  }
  
  protected function getResponseParmsCaseInsentive($params) {
    $paramsToLower = [];
    foreach($params as $key => $param)
      $paramsToLower[strtolower($key)] = $param;
    return $paramsToLower;
  }

  //La réponse Axepta ne contient pas de montant
  protected function checkResponseAmount(&$transactionResponse, $transactionOrigin, $from='autoresponse') {
    //get Shop Order Amount
    $order_oid = $transactionResponse->responseParms['orderoid'];
    if ($transactionResponse->status != self::SUCCESS)
      return false;
    if (in_array($transactionOrigin->status, [self::STATUS_CANCELED, self::STATUS_ERRORCANCEL]))
      return false;
    
    if (!empty($order_oid) && !empty($transactionOrigin->shopMoid)) {
      $shopMod = \Seolan\Core\Module\Module::objectFactory($transactionOrigin->shopMoid);
      if (method_exists($shopMod, 'getOrderAmount')) {
        $transactionResponse->checkAmount = $shopMod->getOrderAmount($transactionOrigin->orderOid);
      } else {
        $transactionResponse->checkAmount = $transactionOrigin->amount;
      }
      $orderAmount = $transactionResponse->checkAmount;
      if ($orderAmount != $transactionOrigin->amount) {
        \Seolan\Core\Logs::critical(__METHOD__ . ' Order amount= ' . $orderAmount);
        \Seolan\Core\Logs::critical(__METHOD__ . ' TR amount= ' . $transactionOrigin->amount);
        \Seolan\Core\Logs::critical(__METHOD__ . ' Transaction Oid: ' . $transactionOrigin->oid);
        \Seolan\Core\Logs::critical(__METHOD__ . ' Transaction orderOid: ' . $order_oid);
        $transactionResponse->statusComplement = "Erreur de montant sur le retour banque (panier:{$orderAmount}e)";
        //NOTICE : annuler le montant incorrect :
        // - avec boutique de test BNP_XXXXX_t => PAS sur l'autoresponse (erreur "Brand not active")
        // - en PROD : mieux vaut utiliser le shopresponse aussi - le fonctionnement sur l'autoresponse n'est pas prouvé ni confirmé par le support Axepta
        if ($from == 'shopresponse') {
          $responseParms = $this->getResponseParmsCaseInsentive($transactionResponse->responseParms);
          $transactionOrigin->PayID = $responseParms['payid'];
          $transactionOrigin->statusComplement = $transactionResponse->statusComplement;
          $this->cancelTransaction($transactionOrigin, $from);
          $transactionResponse->status = $transactionOrigin->status;
          $transactionResponse->statusComplement = $transactionOrigin->statusComplement;
        }
        return false;
      }
    }
    return true;
  }
  
  //déterminer si il y a eu une erreur de paiement ou non
  //Afficher la page boutique qui convient
  public function shopResponse() {
    try {
      $transaction = $this->webPaymentUnFoldReponse();
      if ($this->debug)
        \Seolan\Core\Logs::critical(__METHOD__ . ' Transaction: ' . var_export($transaction, 1));
    } catch (\Exception $e) {
      return $this->shopResponseError('Exception : ' . $e->getMessage() . ' Code : ' . $e->getCode());
    }
    // Si l'oid de la transaction n'a pas été trouvé par le module approprié, on crée une nouvelle transaction pour mémoriser les paramètres reçus.
    if ($transaction->oid == null) {
      $transaction->statusComplement .= ' Transaction d\'origine non trouvée';
      return $this->shopResponseError('unknown transaction');
    }
    
    $transactionOrigin = $this->getTransaction($transaction->oid);
    
    if ($transaction->status != self::SUCCESS) {
      return $this->shopResponseError($transaction->statusComplement);
    }
    if (!$this->checkResponseAmount($transaction, $transactionOrigin, 'shopresponse')) {
      return $this->shopResponseError('montant incorrect - transaction annulée');
    }
    else {
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
  
  protected function getErrorCode($errorCode) {
    //8 digits and chars : x xxx xxxx
    $_error = sprintf('%08s', $errorCode);
    $digit_1 = substr($_error, 0, 1);
    $digit_2_4 = substr($_error, 1, 3);
    $chars_5_8 = substr($_error, 4, 4);
    
    $msg = [
      ErrorCodes::DIGIT_1[$digit_1] ?? '',
      ErrorCodes::DIGIT_2_4[$digit_2_4] ?? '',
      ErrorCodes::CHARS_5_8[$chars_5_8] ?? ''
    ];
    return implode('*',array_filter($msg));
  }
  //-------------------------------------------------------------------------------------
  
  public function refundCall($transactionOriginOid, $order, $shop) {
    
  }
  
  protected function refundHandling($transaction, $replay = false) {
    //Inquire to find out status?
    // Inquire ne fonctionne pas sur boutique de test
    //https://docs.axepta.bnpparibas/pages/viewpage.action?pageId=4653161
    //=> montant capturé
    
    //si pas de montant capturé => CANCEL
    // sinon => CREDIT
  
  }
  
  /* Annulation d'une transaction avant remise
  *
  * Doc : https://docs.axepta.bnpparibas/display/DOCBNP/Card+payment+integration#Cardpaymentintegration-Cardpaymentsmanagement
  * NOTICE : avec boutique de test BNP_XXXXX_t => ne PAS appeler sur l'autoresponse (erreur "Brand not active")
  *
  * Utilisation générique : annulation d'une transaction dont le montant ne correspond pas à celui du panier : effectué sur retour boutique
  */
  public function cancelTransaction(&$transaction, $from='autoresponse') {
    $url = self::CANCEL;
    $requestData = [
      'MerchantID' => $this->merchantId,
      'PayID' => $transaction->PayID,
      'TransID' => $transaction->transId,
      'Amount' => $this->formatOrderAmount($transaction->amount),
      'Currency' => $this->defaultCurrencyCode,
      'ReqID' => preg_replace('@^(\w+:)@', '', $transaction->oid), // max length : 30 / 32
    ];
    
    if (empty($this->serverLib))
      $this->serverLib = new ServerLib(['hmacKey' => $this->hmacKey, 'cryptKey' => $this->blowfishKey]);
    
    $requestData[self::SHASIGN_FIELD] = $this->serverLib->getShaSign($requestData);
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__ . ' transaction ', print_r($requestData, 1));
    
    list($DATA, $len, $debug) = $this->serverLib->getBfishCrypt($requestData);
    $request = [
      'MerchantID' => $this->merchantId,
      'Data' => $DATA,
      'Len' => $len
    ];
    $resp = $this->getServerRequest($url, $request);
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__ . ' rawresp= ', print_r($resp, 1));
    
    $responseData = $this->serverLib->getResponse($resp);
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__ . ' respData= '. print_r($responseData, 1));
    /* example of success - responseData =
    [MerchantID] => BNP_LOTHANTIQUE_ECOM_t
    [PayID] => 33ae0d3bf365497a97fc1e73e7e05d54
    [XID] => 8c2fa34ab07a4ee68b9c20f9511265d7
    [TransID] => 100002
    [refnr] => kae5uz7b9xe0
    [Status] => OK
    [Code] => 00000000
    [Description] => success
    */
    //process with case insensitive
    $r = $this->getResponseParmsCaseInsentive($responseData);
    if (isset($r['code']) && $r['code'] == '00000000') {
      $transaction->status = self::STATUS_CANCELED;
      $transaction->statusComplement .= ' -- cancel (reverse.aspx pid='.getmypid().') success='.json_encode($responseData);
      if ($from != 'autoresponse') {
        $this->xset->procEdit([
          'oid' => $transaction->oid,
          'status' => self::STATUS_CANCELED,
          'statusComplement' => $transaction->statusComplement,
          '_options' => ['local' => true]
        ]);
      }
      return true;
    
    } else {
      $transaction->status = self::STATUS_ERRORCANCEL;
      $transaction->statusComplement .= ' -- cancel (reverse.aspx pid='.getmypid().') erreur='.json_encode($responseData);
      if ($from != 'autoresponse') {
        $this->xset->procEdit([
          'oid' => $transaction->oid,
          'status' => $transaction->status,
          'statusComplement' => $transaction->statusComplement,
          '_options' => ['local' => true]
        ]);
      }
    }
    return false;
  }
  
  /* Remboursement d'une transaction après remise -- non testé
  *
  * Doc : https://docs.axepta.bnpparibas/display/DOCBNP/Card+payment+integration#Cardpaymentintegration-Cardpaymentsmanagement
  */
  public function refundTransaction($transaction) {
    $url = self::CREDIT;
    $requestData = [
      'MerchantID' => $this->merchantId,
      'PayID' => $transaction->PayID,
      'TransID' => $transaction->transId,
      'Amount' => $this->formatOrderAmount($transaction->amount),
      'Currency' => $this->defaultCurrencyCode,
      'ReqID' => preg_replace('@^(\w+:)@', '', $transaction->oid), // max length : 30 / 32
      'OrderDesc' => $transaction->orderReference, // max length : 768
      //'Textfeld1' => 'customer name',
      //'Textfeld2' => 'customer city',
    ];
    
    if (empty($this->serverLib))
      $this->serverLib = new ServerLib(['hmacKey' => $this->hmacKey, 'cryptKey' => $this->blowfishKey]);
    
    if ($this->debug) \Seolan\Core\Logs::debug(__METHOD__ . ' transaction ', var_export($requestData, 1));
    
    $requestData[self::SHASIGN_FIELD] = $this->serverLib->getShaSign($requestData);
    list($DATA, $len, $debug) = $this->serverLib->getBfishCrypt($requestData);
    $request = [
      'MerchantID' => $this->merchantId,
      'Data' => $DATA,
      'Len' => $len
    ];
    $resp = $this->getServerRequest($url, $request);
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__ . ' rawresp= ', print_r($resp, 1));
    
    $responseData = $this->serverLib->getResponse($resp);
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__ . ' respData= '. print_r($responseData, 1));
    //process with case insensitive
    $r = $this->getResponseParmsCaseInsentive($responseData);
    return $r;
  }
  
  /* Dignostique d'une transaction (enquire.aspx) -- non testé
  *
  * Doc : https://docs.axepta.bnpparibas/pages/viewpage.action?pageId=4653161
  * NOTICE : le diagnostique n'est pas disponible sur la boutique de test BNP_XXXXXX_t, seulement sur la boutique de test générique BNP_DEMO_AXEPTA
  */
  public function inquireTransaction($transaction) {
    $url = self::INQUIRE;
    $requestData = [
      'MerchantID' => $this->merchantId,
      'PayID' => $transaction->PayID,
      'TransID' => $transaction->transId,
    ];
    
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__ . ' transaction ', var_export($requestData, 1));
    if (empty($this->serverLib))
      $this->serverLib = new ServerLib(['hmacKey' => $this->hmacKey, 'cryptKey' => $this->blowfishKey]);
    
    $requestData[self::SHASIGN_FIELD] = $this->serverLib->getShaSign($requestData);
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__ . ' MAC= ', $requestData[self::SHASIGN_FIELD]);
    list($DATA, $len, $debug) = $this->serverLib->getBfishCrypt($requestData);
    $request = [
      'MerchantID' => $this->merchantId,
      'Data' => $DATA,
      'Len' => $len
    ];
    $resp = $this->getServerRequest($url, $request);
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__ . ' rawresp= ', print_r($resp, 1));
    
    $responseData = $this->serverLib->getResponse($resp);
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__ . ' respData= '. print_r($responseData, 1));
    //process with case insensitive
    $r = $this->getResponseParmsCaseInsentive($responseData);
    return $r;
  }
  
  // ** NOTICE : non implémenté **
  protected function duplicateHandling($transaction) {
    list($transaction->card, $transaction->schemeReferenceID, $transaction->_oriCallParms) = $this->getInfoTransOri($transaction);
    $transaction->callParms = $this->buildDuplicateParams($transaction);
    // Mémorisation des paramètres d\'appel
    $appel['oid'] = $transaction->oid;
    $appel['callParms'] = $transaction->callParms;
    $appel['dateTimeOut'] = date('Y-m-d H:i:s');
    $appel['options'] = ['callParms' => ['raw' => true, 'toxml' => true]];
    $this->xset->procEdit($appel);
    $result = $this->sendServerRequest(self::DIRECT, $transaction->callParms); //Direct
    
    if ($result == '-1') {
      $transaction->status = self::WAITTING;
      $transaction->statusComplement = 'Serveur indisponible le ' . date('d-m-Y') . ' à : ' . date('H:i:s');
      return $transaction;
    }
    if ($result == '0') {
      $transaction->status = self::WAITTING;
      $transaction->statusComplement = 'Services du serveur indisponible le ' . date('d-m-Y') . ' à : ' . date('H:i:s');
      return $transaction;
    }
    // La reponse est async sur URLNotify
    
    //... ?
    
    // Mise à jour des paramètres de la transaction
    return $transaction;
  }
  protected function buildDuplicateParams($transaction) {
    $credentialOnFile = json_decode($transaction->_oriCallParms['credentialOnFile'], true);
    $credentialOnFile['initialPayment'] = false;
    
    $request = [
      'card' => $transaction->card,
      'schemeReferenceID' => $transaction->schemeReferenceID,
      'credentialOnFile' => json_encode($credentialOnFile),
    ];
    //Recopier certains params de la tr originale
    //Calculer le MAC
    // bfish encrypt ???
    //URLNotify => nouveau script monetique ??
    return $request;
  }
  
  
  protected function getInfoTransOri($transaction) {
    $rs = getDB()->select('select responseParms, callParms from ' . $this->xset->getTable() . ' where KOID=? ', [$transaction->transOri]);
    $res = null;
    if ($rs->rowCount() == 1) {
      $res = $rs->fetch();
      $respparams = \Seolan\Core\System::xml2array($res['responseParms']);
      $callparams = \Seolan\Core\System::xml2array($res['callParms']);
      /// Récupération de card et schemeReferenceID et credentialOnFile
      return [$respparams['card'], $respparams['schemeReferenceID'] ?? $respparams['schemereferenceid'], $callparams];
    } else {
      \Seolan\Core\Logs::critical(__METHOD__,
        'responseParms, amount de la transaction d\'origine ayant pour KOID ' . $transaction->transOri . ' non trouvé!');
      throw new \Exception(__METHOD__ . ' responseParms, amount de la transaction d\'origine ayant pour KOID ' . $transaction->transOri . ' non trouvé!');
    }
  }
  
  protected function getServerRequest($url, $request) {
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." request=".var_export($request, 1));
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." url=".$url);
    $i = 0;
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." urlParams=".$url);
    $ar = ['debug' => false];
    if ($this->debug) $ar = ['debug' => true];
    $rq = new \Seolan\Module\Monetique\Axepta\Library\HttpClient($ar);
    $resp = $rq->get($url, $request, null, false, false);
    if ($resp == -1)
      return $resp->getCurrentError();
    
    $respTab = [];
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." raw resp=".var_export($resp, 1));
    $resp = array_values(array_filter(explode('&',$resp)));
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." raw resp2=".var_export($resp, 1));
    foreach($resp as $s) {
      $keyval = array_values(array_filter(explode('=',$s)));
      if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." ***raw resp3=".print_r($keyval, 1));
      if (count($keyval)==2) {
        $respTab[$keyval[0]] = $keyval[1];
      } else {
        $respTab[$s] = $s;
      }
    }

    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." respTab=".var_export($respTab, 1));
    return $respTab;
  }
  
  protected function postServerRequest($url, $request) {
    $requestJson = json_encode($request, JSON_UNESCAPED_UNICODE, 512);
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." requestJson=".var_export($requestJson, 1));
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." url=".$url);
   
    //SENDING REQUEST
    $option = array(
      'http' => array(
         'method' => 'POST',
         'header' => "content-type: application/json;charset=utf-8", // "content-type: multipart/form-data;", //
         'content' => $requestJson
      ),
    );
    $context = stream_context_create($option);
    $responseJson = file_get_contents($url, false, $context);
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." respJson=".var_export($responseJson, 1));
    if (!$responseJson)
      return false;
    $resp = json_decode($responseJson, true);
    if ($this->debug) \Seolan\Core\Logs::critical(__METHOD__." respTab=".var_export($resp, 1));
    return $resp;
  }
  //-------------------------------------------------------------------------------------
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

  public function editProperties($ar) {
    parent::editProperties($ar);
  }

}
