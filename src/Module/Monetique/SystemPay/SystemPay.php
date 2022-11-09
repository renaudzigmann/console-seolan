<?php

namespace Seolan\Module\Monetique\SystemPay;

use Seolan\Core\Logs;

/**
 * Gestion des transactions SystemPay.
 * API Web V2
 * API WebService REST v4
 */
class SystemPay extends \Seolan\Module\Monetique\Monetique {

  const URL = 'https://api.payzen.eu/api-payment'; // URL du service Web Payzen

  protected $needTransId = true;
  protected $oneClickPossible = true;
  // Options spécifiques à SystemPay
  public $formUrl = 'https://paiement.systempay.fr/vads-payment/'; ///< Url de soumission principale
  public $version = 'V2'; ///< Version de l'API SystemPay Paiement
  public $certificatTest = NULL; ///< Certificat de test fournis par SystePay
  public $certificatProd = NULL; ///< Certificat de production fournis par SystePay
  public $urlReferral = NULL; ///< Url où sera redirigé le client en cas de refus (Code 02: Contacter l'emmeteur de la carte )
  public $defaultTemplate = 'Module/Monetique.systempay.html';
  public $cardTypes = [];
  public $algohash; // algo hashage signature
  public $prodPassword = '';
  public $testUsername = '';
  public $testPassword = '';
  public $prodUsername = '';
  static $cancelableStatus = ['AUTHORISED', 'AUTHORISED_TO_VALIDATE', 'WAITING_AUTHORISATION', 'WAITING_AUTHORISATION_TO_VALIDATE'];
  private $cardTypesSystemPay = [
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
    'VISA_ELECTRON' => 'Vise Electron',
    'COF3XCB' => '3x CB Cofinoga ',
    'COF3XCB_SB' => '3x CB Cofinoga SANDBOX',
  ];

  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Monetique_SystemPay_SystemPay', 'modulename');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_SystemPay_SystemPay', 'certificattest'),
      'certificatTest', 'text', NULL, NULL, $alabel);
    $this->_options->setOpt('Api User  test', 'testUsername', 'text', [], '', $alabel);
    $this->_options->setOpt('Api password test', 'testPassword', 'text', ['size' => 60], '', $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_SystemPay_SystemPay', 'certificatprod'),
      'certificatProd', 'text', NULL, NULL, $alabel);
    $this->_options->setOpt('Api User prod', 'prodUsername', 'text', [], '', $alabel);
    $this->_options->setOpt('Api password prod', 'prodPassword', 'text', ['size' => 60], '', $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_SystemPay_SystemPay', 'urlreferral'),
      'urlReferral', 'text', NULL, NULL, $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Monetique', 'cardtype'), 'cardTypes', 'multiplelist',
      ['values' => array_keys($this->cardTypesSystemPay), 'labels' => array_values($this->cardTypesSystemPay)], NULL, $alabel);
    $this->_options->setOpt('Paiement par téléphone', 'allowPhonePayment', 'boolean', [], false, $alabel);
    $this->_options->setComment('Active le paiement par sms/whatsapp', 'allowPhonePayment', 'boolean', [], NULL, $alabel);
    $this->_options->setOpt('Hachage signature', 'algohash', 'list', ['labels' => ['sha1', 'sha256'], 'values' => ['sha1', 'sha256']], 'sha1', $alabel);
    $this->_options->setOpt('Url du formulaire de paiement', 'formUrl', 'text', ['size' => 60], 'https://paiement.systempay.fr/vads-payment/', $alabel);
  }

  /* Fonctions de paiement version WEB */

  /**
   * \brief Méthode de génération des données de paiement.
   * Cette fonction permet de générer les données d'un paiement SystemPay.
   * \param \Seolan\Module\Monetique\Model\Order $order :  Objet des données issues de la commande.
   * \param \Seolan\Module\Monetique\Model\Customer $customer : Objet des données issues du client.
   * \param \Seolan\Module\Monetique\Model\Shop $shop : Objet comportant les paramètres de la boutique.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction en cours de paramètrage.
   * \return Array :
   * - \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction contenant tous les paramètres d'appel.
   * - Array $systemPayForm : Contient le formulaire envoyé en banque.
   * - String $template : Le template correspondant correspondant un module de traitement (TZR_SHARE_DIR.$this->defaultTemplate).
   * - String $tplentry : L'entrée smarty du template : 'sytemPayForm'.
   * \note
   * - Crée le/les formulaire à envoyer en banque.
   */
  protected function webPaymentHandling(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    if(!empty($transaction->phonePaymentType)) {
      return $this->handlePhonePayment($transaction);
    }
    $systemPayForm = [
      'url' => $this->formUrl,
      'forms' => [],
      'cardTypes' => $this->cardTypesSystemPay
    ];
    foreach ($transaction->cardTypes as $cardType) {
      $callParms = $this->systemPayForm($transaction, $cardType);
      // les parametres d'appel sont les mêmes sauf le type carte
      if (!isset($transaction->callParms)) {
        $transaction->callParms = $callParms;
        $transaction->callParms['url'] = $systemPayForm['url'];
      }
      // Création du formulaire à envoyer en banque
      $fields = '';
      foreach ($callParms as $key => $value) {
        $fields .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
      }
      $systemPayForm['forms'][$cardType] = $fields;
    }
    return [$transaction, $systemPayForm, TZR_SHARE_DIR . $this->defaultTemplate, 'sytemPayForm'];
  }

  protected function handlePhonePayment($transaction) {
    $payload = (object) [
      'amount' => $this->formatOrderAmount($transaction->amount),
      'orderId' => $transaction->orderReference,
      'currency' => $this->defaultCurrency,
      'channelOptions' => [
        'channelType' => $transaction->phonePaymentType,
        'smsOptions' => [
          'phoneNumber' => $transaction->phone,
        ],
        'whatsAppOptions' => [
          'phoneNumber' => $transaction->phone,
        ]
      ],
      'customer' => [
        'email' => $transaction->customerInfos->email,
      ],
    ];
    if(!empty($message = $GLOBALS['XSHELL']->labels->get_label(['variable' => 'payzen_custom_sms']))) {
      $payload->channelOptions['smsOptions']['message'] = $message['payzen_custom_sms'];
    }
    $transaction->callParms = $payload;
    $response = $this->do_post('sendPaymentLink', $payload);
    Logs::critical(__METHOD__ . ' Phone paymentOrder creation response after calling api : ' . var_export($response, 1));
    return [
      $transaction,
      ['linkSended' => $response->status === 'SUCCESS', 'phonePaymentType' => $transaction->phonePaymentType],
      TZR_SHARE_DIR . $this->defaultTemplate,
      'phonePayment'
    ];
  }

  /**
   * \brief Méthode de génération du formulaire de paiement.
   * Cette fonction permet de générer le formulaire de paiement SystemPay.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction est cours de paramètrage.
   * \return Array :
   * - Array $parms : Contient le formulaire envoyé en banque.
   * \note
   * - Mise en forme du montant (centimes). \link \Seolan\Module\Monetique\Monetique::formatOrderAmount($amount); \endlink
   * - Paiement en euros par défaut.
   * - Gestion du paiement multiple si la commande indique plusieurs échéances. \link \Seolan\Module\Monetique\Model\Order::$options \endlink
   * - Gestion du mode TEST ou PRODUCTION.
   * - Gestion des abonnement.
   * - Gestion des délais de capture.
   * - Génération de la signature.
   */
  private function systemPayForm(\Seolan\Module\Monetique\Model\Transaction $transaction, $cardType) {
    $params = [];
    if (!empty($cardType) && $cardType != 'CB') {
      $params['vads_payment_cards'] = $cardType;
    }
    $params['vads_site_id'] = $this->siteId;
    // Paramètres du custommer
    $params['vads_cust_email'] = $transaction->customerEmail;
    $params['vads_order_id'] = $transaction->orderReference;
    $params['vads_order_info'] = $transaction->oid;
    // Paramètres de paiement
    $params['vads_amount'] = $this->formatOrderAmount($transaction->amount);
    $params['vads_currency'] = $this->defaultCurrencyCode;
    // Gestion des paiements à échéances multiples
    if ($transaction->nbDeadLine > 1) {
      // Préparation des paramètres de multi paiements (calcul de la 1ere échéance)
      $reste = $this->formatOrderAmount($transaction->amount) % $transaction->nbDeadLine;
      // Mise en centimes
      $montantDivise = $this->formatOrderAmount($transaction->amount) / $transaction->nbDeadLine;
      $montantDivise = explode('.', $montantDivise);
      $montant = $montantDivise[0];
      $montant1 = $montant+$reste;
      $params['vads_payment_config']= 'MULTI:first='.$montant1.';count='.$transaction->nbDeadLine.';period='.$transaction->frequencyDuplicate;
    }else{
      $params['vads_payment_config']= 'SINGLE';
    }
    // Si la commande indique que la capture doit être différé
    if(isset($transaction->captureDelay)){
      // Nombre de jours pour la capture différée
      $params['vads_capture_delay'] = $transaction->captureDelay;
    }else{
      $params['vads_capture_delay'] = 0;
    }
    // Gestion du mode de capture (Capture par défaut, sinon autorisation seulement)
    if( $transaction->captureMode == self::AUTHORIZATION_ONLY) {
      $params['vads_validation_mode'] = '1';
    }
    else{
      $params['vads_validation_mode'] = '0';
    }
    // Date au format GMT
    $params['vads_trans_date'] = gmdate("YmdHis");
    $params['vads_trans_id'] = $transaction->transId;
    // Version de l'API SystemPay
    $params['vads_version'] = $this->version;
    $params['vads_ctx_mode'] = $this->getMode();
    // type de paiement
    if ($transaction->enrollement == true) {
      // identifier géré par systempay pour partage multiboutique
      if ($transaction->captureMode == self::AUTHORIZATION_ONLY) {
        $params['vads_page_action'] = 'REGISTER';
        unset($params['vads_amount']);
      } else {
        $params['vads_page_action'] = 'REGISTER_PAY';
      }
    } else {
      $params['vads_page_action'] = 'PAYMENT';
    }
    $params['vads_action_mode'] = 'INTERACTIVE';
    // 'fr' par défaut
    $params['vads_language'] = 'fr';
    // Paramètres de gestion des retours
    $params['vads_url_cancel'] = $this->urlCancelled;
    $params['vads_url_error'] = !empty($this->urlError) ? $this->urlError : $this->urlCancelled;
    $params['vads_url_return'] = !empty($this->urlError) ? $this->urlError : $this->urlCancelled;
    $params['vads_url_referral'] = !empty($this->urlReferral) ? $this->urlReferral : $this->urlCancelled;
    $params['vads_url_refused'] = !empty($this->urlError) ? $this->urlError : $this->urlCancelled;
    $params['vads_url_success'] = $this->urlPayed;
    // Surcharge de l'url de traitement de la réponse fournis par le serveur bancaire
    $params['vads_url_check'] = $this->urlAutoResponse;
    // TimeOut de redirection (en secondes)
    $params['vads_redirect_success_timeout'] = 0;
    $params['vads_redirect_error_timeout'] = 0;
    // 3D Secure, on ne peux que le désactiver par le formulaire
    if (isset($transaction->threeDS) && $transaction->threeDS == false) {
      $params['vads_threeds_mpi'] = 2;
    }
    if (!empty($transaction->shop->contracts)) {
      $params['vads_contracts'] = $transaction->shop->contracts;
    }
    $this->extendSystemPayForm($params);
    
    // Tri des paramètres par ordre alphabétique
    ksort($params);
    // Génération du contenu permettant le calcul de la signature
    $contenu_signature = "";
    foreach ($params as $value) {
      $contenu_signature .= $value."+";
    }
    // Récupération du certificat (Gestion des modes TEST et PRODUCTION)
    $certif = $this->getCertif();
    // Calcul de la signature
    $contenu_signature .= $certif;
    // Mémorisation de la signature
    if ($this->algohash == 'sha1') {
      $params['signature'] = sha1($contenu_signature);
    } else {
      $params['signature'] = base64_encode(hash_hmac('sha256', $contenu_signature, $certif, true));
    }
    return $params;
  }

  /**
   * \brief Méthode de surcharge du formulaire de paiement
   * Méthode permettant d'ajouter des paramètres au formulaire envoyé à la banque.
   * \note
   * - params passé par référence
   * - permet par exemple d'ajouter le session_id au formulaire, pour le récupérer au retour banque et vider le panier en session correctement
   */
  protected function extendSystemPayForm(&$params) {
  }

  /**
   * \brief Méthode de traitement du retour banque SystemPay.
   * Méthode de traitement du retour banque SystemPay, afin de transmettre des paramètres standards à \link \Seolan\Module\Monetique\Monetique::autoresponse() \endlink
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le retour automatique.
   * \note
   * - Mémorisation dans $transaction->responseParms des informations transmises par la banque.
   * - Vérifie qu'on ne récupère que des paramètres renvoyés par sytemPay.
   * - Récupération du certificat (Gère le mode TEST et PRODUCTION).
   * - Récupération de la signature du formulaire.
   * - Vérifie la signature.
   * - Affecte le status de la transaction.
   */
  protected function webPaymentUnFoldReponse() {
    // Mémorisation dans $transaction->responseParms des informations transmises par la banque
    $reponse = $_POST;
    // Trie par ordre alphabétique des paramètres
    ksort($reponse);
    $contenu_signature = '';
    foreach ($reponse as $key => $value) {
      // On vérifie qu'on ne récupère que des paramètres renvoyés par sytemPay
      if (substr($key,0,5)=='vads_') {
        $contenu_signature .= $value . '+';
        $params[$key] = $value;
      }
    }
    $transaction = $this->getReturnTransaction($params);

    // Vérification signature
    $certif = $this->getCertif();
    $contenu_signature .= $certif;
    if ($this->algohash == 'sha1') {
      $signature_shop = sha1($contenu_signature);
    } else {
      $signature_shop = base64_encode(hash_hmac('sha256', $contenu_signature, $certif, true));
    }
    if ($_POST['signature'] != $signature_shop) {
      $transaction->status = self::INVALID; ///< Définition de status invalide
      $transaction->statusComplement = 'Erreur à la vérification de la signature, risque de fraude. Chaine à vérifier : '.$contenu_signature;
    } else {
      $transaction->status = $params['vads_result'] == '00' ? self::SUCCESS : self::ERROR;
    }
    return $transaction;
  }

  /**
   * Initialise une transaction avec les paramètres de retour banque
   * @param array $params
   * @return \Seolan\Module\Monetique\Model\Transaction
   */
  private function getReturnTransaction($params) {
    $transaction = new \Seolan\Module\Monetique\Model\Transaction();
    $transaction->oid = array_key_exists('vads_order_info', $params)
      ? $params['vads_order_info']
      : $this->getIdTransactionWithOrderRef($params['vads_order_id'], self::RUNNING);
    $transaction->amount = sprintf('%.2f', $params['vads_amount'] / 100);
    $transaction->responseCode = $params['vads_result'];
    $transaction->authResult = $params['vads_auth_result'];
    $transaction->dateVal = sprintf('%02d',  $params['vads_expiry_month']) . substr( $params['vads_expiry_year'], 2, 4);
    $transaction->numCarte = chunk_split($params['vads_card_number'], 4, ' ');
    $transaction->nbDeadLine = $params['vads_sequence_number'];
    $transaction->transId = $params['vads_trans_id'];
    $transaction->captureDelay = $params['vads_capture_delay'];
    $transaction->captureMode = $params['vads_validation_mode'] == '0' ? self::CATCH_PAYMENT : self::AUTHORIZATION_ONLY;
    if ($params['vads_payment_config'] == 'SINGLE') {
      $transaction->nbDeadLine = 1;
    } else {
      preg_match('/count=(\d*)/', $params['vads_payment_config'], $matches);
      $transaction->nbDeadLine = $params['vads_payment_config'] = $matches[1];
    }
    $transaction->cvv = 'N/A';
    $transaction->porteur = 'N/A';
    $transaction->refAbonneBoutique = $params['vads_identifier'];
    $transaction->responseParms = $params;
    return $transaction;
  }

  protected function checkResponseAmount($transactionResponse, $transactionOrigin) {
    if ($transactionOrigin->captureMode == self::AUTHORIZATION_ONLY) {
      return true;
    }
    return parent::checkResponseAmount($transactionResponse, $transactionOrigin);
  }

  public function getPaymentMethods() {
    if (!$this->checkConf()) {
      return [];
    }
    return ['cards' => $this->getCards(), 'oneClick' => $this->oneClick, 'phone' => $this->allowPhonePayment];
  }

  /**
   * Traitement d'un remboursement
   * @param \Seolan\Module\Monetique\Model\Transaction $transaction
   * @return \Seolan\Module\Monetique\Model\Transaction
   * @throws \Exception
   */
  protected function refundHandling($transaction) {
    $status = $this->getTransactionStatus($transaction->transactionOrigin);

    if ($status == 'CAPTURED') {
      $action = 'refund';
      $payload = (object) [
          'amount' => $this->formatOrderAmount($transaction->amount),
          'currency' => $this->defaultCurrency,
          'orderId' => $transaction->orderReference,
          'uuid' => $this->getTransactionUuid($transaction->transactionOrigin),
          'resolutionMode' => 'REFUND_ONLY'
      ];
    } elseif (in_array($status, static::$cancelableStatus)) {
      if ($transaction->amount == $transaction->transactionOrigin->amount) {
        // remboursement total demandé, annulation
        $action = 'refund';
        $payload = (object) [
            'amount' => $this->formatOrderAmount($transaction->amount),
            'currency' => $this->defaultCurrency,
            'orderId' => $transaction->orderReference,
            'uuid' => $this->getTransactionUuid($transaction->transactionOrigin),
            'resolutionMode' => 'CANCELLATION_ONLY'
        ];
      } else { // remboursement partiel impossible
        $action = 'update';
        $payload = (object) [
            'cardUpdate' => (object) [
              'amount' => $this->formatOrderAmount($transaction->transactionOrigin->amount - $transaction->amount),
              'currency' => $this->defaultCurrency,
              'manualValidation' => 'NO'
            ],
            'orderId' => $transaction->orderReference,
            'uuid' => $this->getTransactionUuid($transaction->transactionOrigin),
        ];
      }
    } else {
      $transaction->status = self::ERROR;
      $transaction->statusComplement = "Le status de la transaction ($status) ne permet pas un remboursement.";
      return $transaction;
    }

    $response = $this->do_post($action, $payload);
    $transaction->callParms = $payload;
    $transaction->responseParms = $response;
    $transaction->status = ($response->status == "SUCCESS" && empty($response->answer->errorCode)) ? self::SUCCESS : self::ERROR;
    if (isset($response->answer->errorCode)) {
      $transaction->responseCode = $response->answer->errorCode;
      $transaction->statusComplement = $response->answer->errorMessage;
    }
    return $transaction;
  }

  // Appeler les actions du service Web Payzen 
  private function do_post($action, $payload) {
    // Set Options for CURL
    $url = $this->getUrl($action);
    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Return Response to Application

    $header = "Authorization: Basic " . base64_encode($this->getCredentials()); // Set Content-Headers to JSON

    curl_setopt($curl, CURLOPT_HTTPHEADER,
      [$header,
        "Content-type: application/json",
        "Accept: application/json"
      ]
    );
    curl_setopt($curl, CURLOPT_POST, true); // Execute call via http-POST
    // Set POST-Body
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload)); // Convert DATA-Array into a JSON-Object
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_USERPWD, $header);

    Logs::notice(__METHOD__ . " call $url, payload=" . json_encode($payload));
    $jsonResponse = curl_exec($curl);
    Logs::notice(__METHOD__ . " response : $jsonResponse");

    if ($jsonResponse === FALSE) { // Erreur d'authentification 
      throw new \Exception("call to $url failed curl_error: " . curl_error($curl), curl_errno($curl));
    }

    $response = json_decode($jsonResponse, FALSE); // Convert response into a multidimensional Array
    $response->_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $response->_success = $response->_status == 200; // Vérifiez s'il y a eu une erreur dans l'appel HTTP
    curl_close($curl);

    return $response;
  }

  // Charger l'url de l'action spécifique du service web
  private function getUrl($action) {
    $url = self::URL;

    switch ($action) {
      case 'refund':
        $url .= '/V4/Transaction/CancelOrRefund';
        break;
      case 'transaction':
        $url .= '/V4/Transaction/Get';
        break;
      case 'duplicate':
        $url .= '/V4/Charge/CreatePayment';
        break;
      case 'sendPaymentLink':
        $url .= '/V4/Charge/CreatePaymentOrder';
        break;
      case 'payment':
        $url .= '/V4/Order/Get';
        break;
      case 'update':
        $url .= '/V4/Transaction/Update';
        break;
      default:
        break;
    }
    return $url;
  }

  private function getCredentials() {
    if ($this->testMode(TRUE)) {
      return $this->testUsername . ':' . $this->testPassword; // Identifiants TEST
    }
    return $this->prodUsername . ':' . $this->prodPassword; // Identifiants PROD
  }

  // Renvoie le statut de la transaction
  protected function getTransactionStatus($transaction) {
    $payment = $this->getPaymentDetails($transaction);

    return $payment->answer->detailedStatus; // Payment status
  }

  protected function getPaymentDetails($transaction) {
    $uuid = $this->getTransactionUuid($transaction);

    if (empty($uuid)) {
      throw new \Exception(__METHOD__ . ' empty uuid');
    }
    $payload = (object) ['uuid' => $uuid];
    $response = $this->do_post('transaction', $payload);

    if (isset($response->answer->errorCode)) {
      throw new \Exception(__METHOD__ . ' error : ' . $response->answer->errorMessage);
    }
    return $response;
  }

  protected function getTransactionUuid($transaction) {
    // Depuis un paiement web
    if (isset($transaction->_responseParms['vads_trans_uuid'])) {
      $uuid = $transaction->_responseParms['vads_trans_uuid'];
    } elseif (isset($transaction->_responseParms['answer']['transactions'][0]['uuid'])) {  // Duplication rest
      $uuid = $transaction->_responseParms['answer']['transactions'][0]['uuid'];
    } elseif (isset($transaction->_responseParms['createPaymentResult']['paymentResponse']['transactionUuid'])) { // XXX: Duplication soap
      $uuid = $transaction->_responseParms['createPaymentResult']['paymentResponse']['transactionUuid'];
    }
    return $uuid;
  }

  /* Fonctions de duplications */

  /**
   * \brief Méthode de traitement d'un paiement sur enrollement
   * @param  \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par la duplication.
   * @return \Seolan\Module\Monetique\Model\Transaction La transaction concernée par la duplication.
   */
  protected function duplicateHandling($transaction) {
    $payload = (object) [
        'amount' => $this->formatOrderAmount($transaction->amount),
        'currency' => $this->defaultCurrency,
        'paymentMethodToken' => $transaction->refAbonneBoutique,
        'formAction' => 'SILENT',
        'orderId' => $transaction->orderReference
    ];

    // Mémorisation des paramètres d'appel
    $transaction->callParms = $payload;
    $transaction->dateTimeOut = date('Y-m-d H:i:s');
    $this->procEditTransaction($transaction);

    $response = $this->do_post('duplicate', $payload);
    $transaction->responseParms = $response;
    $transaction->status = $response->answer->orderStatus == "PAID" ? self::SUCCESS : self::ERROR;

    if (strlen($response->answer->transactions[0]->errorCode)) {
      $transaction->responseCode = $response->answer->transactions[0]->errorCode;
      $transaction->statusComplement = $response->answer->transactions[0]->errorMessage;
      if ($response->answer->transactions[0]->detailedErrorMessage) {
        $transaction->statusComplement .= ' / ' . $response->answer->transactions[0]->detailedErrorMessage;
      }
      if ($response->answer->transactions[0]->detailedErrorCode) {
        $transaction->responseCode .= ' / ' . $response->answer->transactions[0]->detailedErrorCode;
        $transaction->statusComplement .= ' / ' . $this->getAuthorizationResponseCodeDetails($response->answer->transactions[0]->detailedErrorCode);
      }
    }
    return $transaction;
  }

  // Rechercher des transactions associées à une commande
  public function findPayments($orderReference, $operationType = NULL) {
    $payload = (object) [
        'orderId' => $orderReference,
        'operationType' => $operationType
    ];
    $response = $this->do_post('payment', $payload);

    if (isset($response->answer->errorCode)) {
      throw new \Exception(__METHOD__ . ' exeception ' . $response->answer->errorCode);
    }
    return $response->answer->transactions; // Renvoyer la liste des transactions
  }

  /* Fonctions utilitaires */

  protected function getMode() {
    if ($this->testMode(true))
      return 'TEST';
    return 'PRODUCTION';
  }

  // Récupération du certificat de TEST ou PRODUCTION
  public function getCertif() {
    if ($this->testMode(true)) {
      return $this->certificatTest;
    }
    return $this->certificatProd;
  }

  protected function getAuthorizationResponseCodeDetails($authorisationResponseCode) {
    static $authorizationResponseCodeDetails = [
      0 => 'Transaction approuvée ou traitée avec succès.',
      2 => 'Contacter l’émetteur de carte.',
      3 => 'Accepteur invalide.',
      4 => 'Conserver la carte.',
      5 => 'Ne pas honorer.',
      7 => 'Conserver la carte, conditions spéciales.',
      8 => 'Approuver après identification.',
      12 => 'Transaction invalide.',
      13 => 'Montant invalide',
      14 => 'Numéro de porteur invalide.',
      15 => 'Emetteur de carte inconnu.',
      17 => 'Annulation acheteur.',
      19 => 'Répéter la transaction ultérieurement.',
      20 => 'Réponse erronée (erreur dans le domaine serveur).',
      24 => 'Mise à jour de fichier non supportée',
      25 => 'Impossible de localiser l’enregistrement dans le fichier.',
      26 => 'Enregistrement dupliqué, ancien enregistrement remplacé.',
      27 => 'Erreur en « edit » sur champ de liste à jour fichier.',
      28 => 'Accès interdit au fichier.',
      29 => 'Mise à jour impossible.',
      30 => 'Erreur de format.',
      31 => 'Identifiant de l’organisme acquéreur inconnu.',
      33 => 'Date de validité de la carte dépassée.',
      34 => 'Suspicion de fraude.',
      38 => 'Date de validité de la carte dépassée.',
      41 => 'Carte perdue.',
      43 => 'Carte volée.',
      51 => 'Provision insuffisante ou crédit dépassé.',
      54 => 'Date de validité de la carte dépassée.',
      55 => 'Code confidentiel erroné.',
      56 => 'Carte absente du fichier.',
      57 => 'Transaction non permise à ce porteur.',
      58 => 'Transaction non permise à ce porteur.',
      59 => 'Suspicion de fraude.',
      60 => 'L’accepteur de carte doit contacter l’acquéreur.',
      61 => 'Montant de retrait hors limite.',
      63 => 'Règles de sécurité non respectées.',
      68 => 'Réponse non parvenue ou reçue trop tard.',
      75 => 'Nombre d’essais code confidentiel dépassé.',
      76 => 'Porteur déjà en opposition, ancien enregistrement conservé.',
      90 => 'Arrêt momentané du système.',
      91 => 'Émetteur de cartes inaccessible.',
      94 => 'Transaction dupliquée.',
      96 => 'Mauvais fonctionnement du système.',
      97 => 'Échéance de la temporisation de surveillance globale.',
      98 => 'Serveur indisponible routage réseau demandé à nouveau.',
      99 => 'Incident domaine initiateur.',
    ];
    return $authorizationResponseCodeDetails[(int) $authorisationResponseCode];
  }

}
