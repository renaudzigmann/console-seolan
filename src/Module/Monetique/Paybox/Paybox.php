<?php
namespace Seolan\Module\Monetique\Paybox;

use Seolan\Core\Labels;
use Seolan\Core\Logs;

/**
 * Classe \Seolan\Module\Monetique\Paybox\Paybox.
 * module banque paybox et e-transaction du CA
 * \note
 * les valeurs par defaut des options sont celles de paybox
 */
class Paybox extends \Seolan\Module\Monetique\Monetique {

  /// Tableaux permettant la séléction de l'algorithme dans la console
  const libelleHash = ['Sha512', 'RIPEMD160', 'Sha256', 'Sha384', 'Sha224', 'MDC2'];
  const codeHash = ['Sha512', 'RIPEMD160', 'Sha256', 'Sha384', 'Sha224', 'MDC2'];

  // Options spécifiques au module
  protected $oneClickPossible = true;
  public $rang = NULL; ///< Rang du site, fournis par la banque.
  public $identifiant = NULL; ///< Identifiant, fournis par la banque.
  public $keyFile = NULL; ///< Fichier contenant la clé permettant de verifiée la signature.
  public $formMethode = 'POST'; ///< méthode de soumission.
  public $formFirstUrl = NULL; ///< url de soumission
  public $formUrlPreProd = NULL; ///< url de soumission de pré-production.
  public $mobileUrl = '';
  public $mobileUrlPreProd = '';
  public $allowCVCpayment = false;
  public $cardTypes;
  /// Gestion des paramètres de retour (C'est le maximum d'infos que paybox nous retourne).
  protected $champsRetour = [
    'P_M' => 'M', // Montant de la transaction
    'P_R' => 'R', // Référence commande (Passée par PBX_CMD)
    'P_A' => 'A', // Numéro d'autorisation
    'P_T' => 'T', // Identifiant de la transaction
    'P_Q' => 'Q', // Heure du traitement de la transaction
    'P_W' => 'W', // Date du traitement de la transaction
    'P_B' => 'B', // Numéro d'abonnement (fournis par Paybox Service)
    'P_P' => 'P', // Type de paiement
    'P_C' => 'C', // Type de carte
    'P_S' => 'S', // Numéro de transaction
    'P_Y' => 'Y', // Code pays de la banque éméttrice de la carte
    'P_E' => 'E', // Code erreure de la transaction
    'P_F' => 'F', // Etat d'authentification 3D secure
    'P_G' => 'G', // Garantie de paiement 3D secure
    'P_O' => 'O', // Enrolement du porteur 3D secure
    'P_D' => 'D', // Date de fin de validitée
    'P_N' => 'N', // 6 premier numéros de carte
    'P_J' => 'J', // 2 derniers numéros de carte
//    'P_H' => 'H', // Empreinte de la carte // imcompatible avec N & J
    'P_Z' => 'Z', // Index lors de l'utilisation de paiement mixtes
    'P_K' => 'K'  // Signature du message
  ];
  protected $cardTypesPaybox = [
    'CB' => 'Carte Bleue',
    'VISA' => 'Visa',
    'EUROCARD_MASTERCARD' => 'Eurocard/Mastercard',
    'AMEX' => 'American Express',
    'AURORE' => 'Aurore',
    'BCMC' => 'BCMC',
    'CDGP' => 'CDGP',
    'COFINOGA' => 'Cofinoga',
    'DINERS' => 'Diners',
    'JCB' => 'JCB',
    'MAESTRO' => 'Maestro',
    'SOFINCO' => 'Sofinco',
    '24H00' => '24H00',
    'CVCONNECT' => 'Chèque-vacances',
  ];
  // Variables relatives à la signature
  public $hashKey = NULL; ///< Signature de l'envoi en banque
  public $hashKeyPreProd = NULL; ///< Signature de l'envoi en banque
  public $algoHash = NULL; ///< Algorithme de hachage
  // Urls de serveurs PPPS (Serveur à serveur)
  public $urlPPPStest = 'https://preprod-ppps.paybox.com/PPPS.php'; ///< Url d'appel pour le mode TEST.
  public $urlPPPS1 = 'https://ppps.paybox.com/PPPS.php'; ///< Url principale d'appel pour le mode PRODUCTION.
  public $urlPPPS2 = 'https://ppps1.paybox.com/PPPS.php'; ///< Url secondaire d'appel pour le mode PRODUCTION.
  public $clePPPS = '1999888I'; ///< Clé de vérification pour le dial de serveur à serveur (PPPS).
  public $defaultTemplate = 'Module/Monetique.paybox.html'; ///< Template Paybox par défaut.

  // vérifie que le module est correctement configuré.
  protected function checkConf() {
    // si pas de clef, impossible de traiter le retour
    return false !== $this->loadKeyFile();
  }

  /* Fonctions de paiement version HMAC */

  /**
   * \brief Méthode de génération des données de paiement.
   * Cette fonction permet de générer les données d'un paiement Paybox.
   * \param \Seolan\Module\Monetique\Model\Order $order :  Objet des données issues de la commande.
   * \param \Seolan\Module\Monetique\Model\Customer $customer : Objet des données issues du client.
   * \param \Seolan\Module\Monetique\Model\Shop $shop : Objet comportant les paramètres de la boutique.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction en cours de paramètrage.
   * \return Array :
   * - String 'ok' : S.
   * - Array $payboxForm : Contient le formulaire envoyé en banque.
   * - String $template : Le template correspondant correspondant un module de traitement (TZR_SHARE_DIR.$this->defaultTemplate).
   * - String $tplentry : L'entrée smarty du template : 'pbf'.
   * \note
   * - Initialise les paramètres d'appel.
   * - Crée le formulaire à envoyer en banque.
   * - Recherche le serveur Paybox disponible. \link \Seolan\Module\Monetique\Paybox\Paybox::serveurDispo() \endlink
   * - Retourne la transaction en cours, le formulaire envoyé en banque ainsi que le template et son entrée
   * \note
   * Définition des types de cartes proposés:
   * - $params['PBX_TYPEPAIEMENT'] = 'CARTE';
   * - $params['PBX_TYPECARTE'] = 'NOM DE LA CARTE VOULUE';
   */
  protected function webPaymentHandling(\Seolan\Module\Monetique\Model\Transaction $transaction){
    // Initialisation des paramètres d'appel
    $cardTypes = $this->cardTypesPaybox;
    if (!$this->allowCVCpayment) {
      unset($cardTypes['CVCONNECT']);
    }
    $payboxForm = [
      'method' => $this->formMethode,
      'url' => $this->serveurDispo(),
      'forms' => [],
      'cardTypes' => $this->cardTypesPaybox
    ];
    foreach ($transaction->cardTypes as $cardType) {
      $callParms = $this->payboxForm($transaction, $cardType);
      // les parametres d'appel sont les mêmes sauf le type carte
      if (!isset($transaction->callParms)) {
        $transaction->callParms = $callParms;
        $transaction->callParms['method'] = $payboxForm['method'];
        $transaction->callParms['url'] = $payboxForm['url'];
      }
      // Création du formulaire à envoyer en banque
      $fields = '';
      foreach ($callParms as $key => $value) {
        $fields .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
      }
      $payboxForm['forms'][$cardType] = $fields;
    }

    // Retourne la transaction en cours, le formulaire envoyé en banque ainsi que le template et son entrée
    return [$transaction, $payboxForm, $this->defaultTemplate, 'pbf'];
  }

  public function getCards() {
    if (empty($this->cardTypes) || empty($this->cardTypes[0]))
      return [''];
    if (!$this->allowCVCpayment) {
      $cardTypes = $this->cardTypes;
      unset($cardTypes['CVCONNECT']);
      return $cardTypes;
    }
    return $this->cardTypes;
  }

  /**
   * \brief Méthode de génération du formulaire de paiement.
   * Cette fonction permet de générer le formulaire de paiement Paybox.
   * \param \Seolan\Module\Monetique\Model\Order $order :  Objet des données issues de la commande.
   * \param \Seolan\Module\Monetique\Model\Customer $customer : Objet des données issues du client.
   * \param \Seolan\Module\Monetique\Model\Shop $shop : Objet comportant les paramètres de la boutique.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction en cours de paramètrage.
   * \return Array :
   * - Array $payboxForm : Contient le formulaire envoyé en banque.
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
  private function payboxForm($transaction, $cardType) {
    $params = [];
    if ($cardType) {
      $params['PBX_TYPEPAIEMENT'] = $cardType == 'CVCONNECT' ? 'LIMONETIK' : 'CARTE';
      $params['PBX_TYPECARTE'] = $cardType;// Si ce champ est présent, renvoie le type de carte dans la réponse
    }
    if (isset($transaction->lang)) {
      if ($transaction->lang == 'GB' || $transaction->lang == 'en') {
	$params['PBX_LANGUE'] = 'GBR';
      }
      if ($transaction->lang == 'SP' || $transaction->lang == 'sp') {
	$params['PBX_LANGUE'] = 'ESP';
      } else {
	$params['PBX_LANGUE'] = 'FRA';
      }
    }
    // Identifiant du site fournis par la banque
    $params['PBX_SITE'] = $this->siteId;
    // Rang du site fournis par la banque
    $params['PBX_RANG'] = $this->rang;
    // Identifiant du commerçant
    $params['PBX_IDENTIFIANT'] = $this->identifiant;
    // Mise en forme du montant (centimes)
    $params['PBX_TOTAL'] = $this->formatOrderAmount($transaction->amount);
    // Paiement en euros par défaut
    $params['PBX_DEVISE'] = $this->defaultCurrencyCode;
    // Si $order->options['noCapture'] est valorisé à true alors on ne fait qu'une demande d'autorisation
    if ($transaction->captureMode == self::AUTHORIZATION_ONLY) {
      $params['PBX_AUTOSEULE'] = 'O';
    }
    // Sinon capture par défaut
    else {
      $params['PBX_AUTOSEULE'] = 'N';
    }
    // Gestion du paiement multiple si la commande indique plusieurs échéances
    if ($transaction->nbDeadLine > 1) {
      if ($transaction->nbDeadLine > 4) {
        throw new \Exception(__METHOD__ . " paiement multiple, échéance trop nombreuses $transaction->nbDeadLine > 4");
      }
      list($units, $cents) = explode('.', $transaction->amount);
      $reste = $units % $transaction->nbDeadLine . ".$cents";
      // Mise en centimes
      $montantEcheance = $this->formatOrderAmount(($transaction->amount - $reste) / $transaction->nbDeadLine);
      $params['PBX_TOTAL'] = $montantEcheance + $this->formatOrderAmount($reste);
      for ($i = 1; $i < $transaction->nbDeadLine; $i++) {
        $params["PBX_2MONT$i"] = $montantEcheance;
        $params["PBX_DATE$i"] = date('d/m/Y', strtotime('+ ' . ($i * $transaction->frequencyDuplicate) . ' days'));
      }
    }
    $params['PBX_CMD'] = $transaction->orderReference;
    $params['PBX_PORTEUR'] = $transaction->customerEmail;
    $params['PBX_EFFECTUE'] = $this->urlPayed;
    $params['PBX_REFUSE'] = $this->urlCancelled;
    $params['PBX_ANNULE'] = $this->urlCancelled;
    // Gestion du mode TEST ou PRODUCTION
    $params['PBX_REPONDRE_A'] = $this->urlAutoResponse;
    $params['PBX_TIME'] = date('c');
    $params['PBX_HASH'] = $this->algoHash; ///< Algorithme utilisé pour le calcul de la clé
    $params['PBX_RUF1'] =  $this->formMethode;
    if ($transaction->traceId)
      $params['PBX_ARCHIVAGE'] = $transaction->traceId;
    if ($this->groupPPPS)
      $params['PBX_GROUPE'] = $this->groupPPPS;

    // Si la commande nécessite l'abonnement du client
    if (isset($transaction->refAbonneBoutique) && $transaction->enrollement == true) {
      $params['PBX_REFABONNE'] = $transaction->refAbonneBoutique;
      $this->champsRetour['P_U'] = 'U'; // Référence donner par Paybox Direct Plus
      if ($params['PBX_TOTAL'] == 0) {
        $params['PBX_TOTAL'] = 100;
      }
    }

    // Si la commande indique que la capture doit être différé
    if (isset($transaction->captureDelay)) {
      $params['PBX_DIFF'] = $transaction->captureDelay; ///< Nombre de jours pour la capture différée
    } else {
      $params['PBX_DIFF'] = 0;
    }
    $params['PBX_SOURCE'] = $this->onMobile() ? 'XHTML' : 'HTML';
    // variables attendus en retour (Toutes celles fournis par paybox)
    $retour = '';
    foreach ($this->champsRetour as $key => $value) {
      $retour .= $key . ':' . $value . ';';
    }
    $retour = substr($retour, 0, -1); ///< Suppression du dernier ';'
    $params['PBX_RETOUR'] = $retour;
    $params['PBX_HMAC'] = $this->hashParams($params);
    return $params;

  }

  /**
   * \brief Méthode de traitement du retour banque paybox.
   * Méthode de traitement du retour banque paybox, afin de transamettre des paramètres standards à \link \Seolan\Module\Monetique\Monetique::autoresponse() \endlink
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le retour automatique.
   * \note
   * - Récupère de toutes les valeurs attendu par PayBox, présentes dans $_REQUEST.
   * - Mémorise les paramètres de retour de la transaction sous une forme normalisée pour faciliter le traitement en aval et dans Xmodmonetique. \link \Seolan\Module\Monetique\Paybox\Paybox::formatParams(& $transaction); \endlink
   * - Vérifie la signature de la reponse. \link \Seolan\Module\Monetique\Paybox\Paybox::verificationSignature($retour, $signature); \endlink
   * - Affecte le status de la transaction.
   * - Récupère l'oid de la transaction grâce à la référence commande. \link \Seolan\Module\Monetique\Monetique::getIdTransactionWithOrderRef($orderReference); \endlink
   * - Incrémente le nombre de retour.
   */
  protected function webPaymentUnFoldReponse() {
    // Récupération de toutes les valeurs retournés par PayBox et présentes dans $_REQUEST
    $this->champsRetour['P_U'] = 'U';
    $params = array_intersect_key($_REQUEST, $this->champsRetour);
    //si complément chèque vacance par CB on pause pour que la transaction du chèque vacance puisse se terminer en premier (2 IPNs en même temps)
    if($params['P_C'] == 'LIMOCB') {
      sleep(1);
    }
    $transaction = $this->getReturnTransaction($params);

    // Vérification de la signature
    if (!$this->verificationSignature($this->getSeal($params), $transaction->signature)) {
      $transaction->status = self::INVALID; ///< Définition de status invalide
      $transaction->statusComplement = 'Erreur à la vérification de la signature, risque de fraude. Chaine à vérifier : ' . $transaction->stringToVerify;
    } else {
      $transaction->status = $transaction->responseCode == '00' ? self::SUCCESS : self::ERROR;
      if($params['P_P'] == "LIMONETIK") {
        $transactionOrigin = $this->getTransaction($transaction->oid);
        if($params['P_C'] == "CVCONNECT" && $this->formatOrderAmount($transaction->amount) != $transactionOrigin->_callParms['PBX_TOTAL']) {
          $transaction->status = self::RUNNING;
        }
      }
    }
    return $transaction;
  }

  /**
   * Initialise une transaction avec les paramètres de retour banque
   * @param array $params
   * @return \Seolan\Module\Monetique\Model\MTransaction
   */
  private function getReturnTransaction($params) {
    $transaction = new \Seolan\Module\Monetique\Model\Transaction();
    $infoCarte = explode('  ', $params['P_U']); // porteur(crypté) dateval cvv
    // P_R refCommande[PBX_2MONTxxxxxxxxxxPBX_NBPAIExxPBX_FREQxxPBX_QUANDxxPBX_DELAISxxx]
    preg_match('/^(.*)(PBX_2MONT(\d+)PBX_NBPAIE(\d*)PBX_FREQ.*)?$/U', $params['P_R'], $matches);
    $reference = $matches[1];
    $nbDeadLine = isset($matches[4]) ? $matches[4] : 1;
    $transaction->oid = $this->getIdTransactionWithOrderRef($reference, self::RUNNING);
    $transaction->orderReference = $reference;
    $transaction->amount = sprintf('%.2f', $params['P_M'] / 100);
    $transaction->responseCode = $params['P_E'] === '00000' ? '00' : $params['P_E'];
    $transaction->transId = $params['P_T'];
    $transaction->authId = $params['P_A'];
    $transaction->numTrans = $params['P_S'];
    $transaction->numCarte = trim(chunk_split($params['P_N'] . 'XXXXXXXX' . $params['P_J'], 4, ' ')); // numcarte 6 + 2
    $transaction->porteur = $infoCarte[0];
    $transaction->dateVal = substr($infoCarte[1], 2, 4) . substr($infoCarte[1], 0, 2);
    $transaction->cvv = $infoCarte[2];
    $transaction->nbDeadLine = $nbDeadLine;
    $transaction->refAbonne = $params['P_B'];
    $transaction->signature = $params['P_K'];
    $transaction->responseParms = $params;
    // Dans le cas d'un paiement Chèque Vacances avec complément CB (2 retours), si retour complément CB on récupère la
    // Transaction précédente pour la compléter.
    if ($params['P_C'] == 'LIMOCB') {
      $transactionOrigin = $this->getTransaction($transaction->oid);
      $transaction->amount = sprintf('%.2f', $transaction->amount + $transactionOrigin->amount);
      $transaction->numTrans = $transactionOrigin->numTrans . " | " . $transaction->numTrans;
      foreach ($params as $key => $param) {
        $params[$key.'-2'] = $params[$key];
        unset($params[$key]);
      }
      $transaction->responseParms = array_merge($transactionOrigin->_responseParms, $params);
    }
    return $transaction;
  }

  protected function checkResponseAmount($transactionResponse, $transactionOrigin) {
    $shop = self::objectFactory($transactionOrigin->shopMoid);
    if (method_exists($shop, 'getOrderAmount')) {
      $transactionResponse->checkAmount = $shop->getOrderAmount($transactionOrigin->orderOid);
    } else {
      $transactionResponse->checkAmount = $transactionResponse->_callParms['PBX_TOTAL'] / 100;
    }
    if ($this->formatOrderAmount($transactionResponse->checkAmount) == $this->formatOrderAmount($transactionResponse->amount)) {
      return true;
    }
    return false;
  }

  protected function isPartialTransaction($transaction, $transactionOrigin) {
    $responseParms = $transaction->responseParms;
    return $responseParms['P_P'] == "LIMONETIK"
            && $responseParms['P_C'] == "CVCONNECT"
            && $this->formatOrderAmount($transaction->amount) != $transactionOrigin->_callParms['PBX_TOTAL'];
  }

  // calcule le sceau à vérifier
  private function getSeal($params) {
    unset($params['P_K'], $params['P_U']); // signature
    $_params = [];
    foreach ($params as $key => $value) {
      $_params[] = "$key=" . rawurlencode($value);
    }
    return implode('&', $_params);
  }

  /* Appel de serveur à serveur (PPPS) */
  /* Fonctions de remboursement d'un abonné */

  /**
   * \brief Méthode de traitement d'un remboursement Paybox.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La nouvelle transaction correspondant au remboursement.
   * Doit contenir dans responseParms les paramètre de retour de la transaction à l'origine du remboursement.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le remboursement après l'appel banque.
   * \note
   * - Mémorise les paramètres nécéssaire au remboursement.
   * - Ré-initialise les champs de retour
   * - Création du formulaire de remboursement (Mise à jour de l'attribut $transaction->callParms)
   * - Mémorise les paramètres d'appel.
   * - Prépare le formulaire curl. \link \Seolan\Module\Monetique\Paybox\Paybox::\Seolan\Module\Monetique\Paybox\Paybox::prepareCurlForm ($payboxParams) \endlink
   * - Envoi le formulaire curl. \link \Seolan\Module\Monetique\Paybox\Paybox::sendCurlForm ($payboxForm, &$transaction) \endlink
   * - Traite le retour curl. \link \Seolan\Module\Monetique\Paybox\Paybox::handlingCurlReturn (&$transaction) \endlink
   */
  protected function refundHandling($transaction) {
    // Création du formulaire de remboursement (Mise à jour de l'attribut $transaction->callParms)
    $transaction->callParms = $this->refundPayboxForm($transaction);
    // Mémorisation des paramètres d'appel
    $this->xset->procEdit([
      'oid' => $transaction->oid,
      'callParms' => $transaction->callParms,
      'options' => ['callParms' => ['raw' => true, 'toxml' => true]]
    ]);
    // Préparation du formulaire de remboursement
    $payboxForm = $this->prepareCurlForm($transaction->callParms);
    // Envoi du formulaire et mise à jour du retour dans $transaction
    $this->sendCurlForm($payboxForm, $transaction);
    // Traitement de la réponse contenu dans $transaction
    $this->handlingCurlReturn($transaction);
    return $transaction;
  }
  /**
   * \brief Méthode de génération du formulaire de remboursement qui sera envoyé à la banque.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction correspondant au remboursement.
   * \return array la liste des champs pour l'appel remboursement
   */
  private function refundPayboxForm($transaction) {
    $originResponseParms = $transaction->transactionOrigin->_responseParms;
    $callParms = [];
    // Construction du formulaire
    $callParms['DATEQ'] = date('dmYHis');
    $callParms['TYPE'] = '00014';
    $callParms['NUMQUESTION'] = date('His');
    $callParms['MONTANT'] = $this->formatOrderAmount($transaction->amount);
    $callParms['REFERENCE'] = $transaction->orderReference;
    $callParms['SITE'] = $this->siteId;
    $callParms['RANG'] = $this->rang;
    $callParms['VERSION'] = '00104';
    $callParms['CLE'] = $this->clePPPS;
    $callParms['IDENTIFIANT'] = $this->identifiant;
    $callParms['DEVISE'] = $this->defaultCurrencyCode;
    $callParms['NUMAPPEL'] = $originResponseParms['P_T'] ? $originResponseParms['P_T'] : $originResponseParms['NUMAPPEL'];
    $callParms['NUMTRANS'] = $originResponseParms['P_S'] ? $originResponseParms['P_S'] : $originResponseParms['NUMTRANS'];
    if ($transaction->traceId)
      $callParms['ARCHIVAGE'] = $transaction->traceId;
    $group = trim($this->groupPPPS);
    if (!empty($group))
      $callParms['GROUPE'] = $group;
    // ajout de la signature
    $callParms['HMAC'] = $this->hashParams($callParms);
    return $callParms;
  }

  /**
   * \brief Méthode de ré-émissions des remboursement en attente (serveur non disponible lors de l'appel).
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction correspondant au remboursement qui sera mise à jour pendant la ré-émission.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le remboursement après sa ré-émission.
   * \see
   * - Prépare le formulaire curl. \link \Seolan\Module\Monetique\Paybox\Paybox::\Seolan\Module\Monetique\Paybox\Paybox::prepareCurlForm ($payboxParams) \endlink
   * - Envoi le formulaire curl. \link \Seolan\Module\Monetique\Paybox\Paybox::sendCurlForm ($payboxForm, &$transaction) \endlink
   * - Traite le retour curl. \link \Seolan\Module\Monetique\Paybox\Paybox::handlingCurlReturn (&$transaction) \endlink

   */
  protected function refundReplay($transaction) {
    /// Mise à jour du numéro de question
    $transaction->_callParms['NUMQUESTION'] = date('Hisd');
    /// Préparation du formulaire de remboursement
    $payboxForm = $this->prepareCurlForm($transaction->_callParms);
    /// Envoi du formulaire et mise à jour du retour dans $transaction
    $this->sendCurlForm($payboxForm, $transaction);
    /// Traitement de la réponse contenu dans $transaction
    $this->handlingCurlReturn($transaction);
    return $transaction;
  }

  /* Fonctions de débit forcé d'un abonné */

  /**
   * \brief Méthode de traitement d'une duplication Paybox.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La nouvelle transaction correspondant à la duplication.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par la duplication après l'appel banque.
   * \note
   * - Création du formulaire envoyé pour le débit (Mise à jour de l'attribut $transaction->callParms).
   * - Mémorise les paramètres d'appel.
   * - Prépare le formulaire curl. \link \Seolan\Module\Monetique\Paybox\Paybox::\Seolan\Module\Monetique\Paybox\Paybox::prepareCurlForm ($payboxParams) \endlink
   * - Envoi le formulaire curl. \link \Seolan\Module\Monetique\Paybox\Paybox::sendCurlForm ($payboxForm, &$transaction) \endlink
   * - Traite le retour curl. \link \Seolan\Module\Monetique\Paybox\Paybox::handlingCurlReturn (&$transaction) \endlink
   */
  protected function duplicateHandling($transaction) {
    $transaction->callParms = $this->duplicatePayboxForm($transaction);
    // Mémorisation des paramètres d'appel
    $this->xset->procEdit([
      'oid' => $transaction->oid,
      'callParms' => $transaction->callParms,
      'options' => ['callParms' => ['raw' => true, 'toxml' => true]]
    ]);
    // Préparation du formulaire de remboursement
    $payboxForm = $this->prepareCurlForm($transaction->callParms);
    // Envoi du formulaire et mise à jour du retour dans $transaction
    $this->sendCurlForm($payboxForm, $transaction);
    // Traitement de la réponse contenu dans $transaction
    $this->handlingCurlReturn($transaction);
    return $transaction;
  }

  /**
   * \brief Méthode de génération du formulaire de duplication.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction correspondant au remboursement.
   * \param \Seolan\Module\Monetique\Model\Transaction $paramsRetourOrigin : Les paramètres de retour de la transaction à l'origine du remboursement.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le remboursement après la mise à jour du formulaire, mémorisé dans $transaction->callParms.
   */
  private function duplicatePayboxForm($transaction) {
    $callParms = [];
    $callParms['DATEQ'] = date('dmYHis');
    $callParms['TYPE'] = '00053';
    $callParms['NUMQUESTION'] = date('Hisd');
    $callParms['MONTANT'] = $this->formatOrderAmount($transaction->amount);
    $callParms['SITE'] = $this->siteId;
    $callParms['RANG'] = $this->rang;
    $callParms['REFERENCE'] = $transaction->orderReference;
    $callParms['REFABONNE'] = $transaction->refAbonneBoutique;
    $callParms['VERSION'] = '00104';
    $callParms['CLE'] = $this->clePPPS;
    $callParms['DEVISE'] = $this->defaultCurrencyCode;
    $callParms['PORTEUR'] = $transaction->porteur;
    $callParms['DATEVAL'] = $transaction->dateVal;
    $callParms['ACTIVITE'] = '027';
    if ($transaction->traceId)
      $callParms['ARCHIVAGE'] = $transaction->traceId;
    $group = trim($this->groupPPPS);
    if (!empty($group))
      $callParms['GROUPE'] = $group;
    $callParms['HASH'] = strtoupper($this->algoHash);
    // ajout de la signature
    $callParms['HMAC'] = $this->hashParams($callParms);
    return $callParms;
  }
  /**
   * \brief Méthode de ré-émissions des duplications en attente (serveur non disponible lors de l'appel).
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction correspondant à la duplication qui sera mise à jour pendant la ré-émission.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par la duplication après sa ré-émission.
   * \see
   * - Prépare le formulaire curl. \link \Seolan\Module\Monetique\Paybox\Paybox::\Seolan\Module\Monetique\Paybox\Paybox::prepareCurlForm ($payboxParams) \endlink
   * - Envoi le formulaire curl. \link \Seolan\Module\Monetique\Paybox\Paybox::sendCurlForm ($payboxForm, &$transaction) \endlink
   * - Traite le retour curl. \link \Seolan\Module\Monetique\Paybox\Paybox::handlingCurlReturn (&$transaction) \endlink
   */
  protected function duplicateReplay($transaction) {
    // Mise à jour du numéro de question (doit être unique chaque jours)
    $transaction->_callParms['NUMQUESTION'] = date('Hisd');
    // Préparation du formulaire de remboursement
    $payboxForm = $this->prepareCurlForm($transaction->_callParms);
    // Envoi du formulaire et mise à jour du retour dans $transaction
    $this->sendCurlForm($payboxForm, $transaction);
    // Traitement de la réponse contenu dans $transaction
    $this->handlingCurlReturn($transaction);
    return $transaction;
  }

  /* Fonctions utilitaires */
  /**
   * signature d'un lot de paramètres
   */
  protected function hashParams($params) {
    /// La clé est en ASCII, On la transforme en binaire
    if ($this->testMode(true)) {
      $binKey = pack("H*", $this->hashKeyPreProd);
    } else {
      $binKey = pack("H*", $this->hashKey);
    }
    /* On calcule l'empreinte (à renseigner dans le paramètre HMAC) grâce à la fonction hash_hmac et la clé binaire.
     * On envoie via la variable HASH l'algorithme de hachage qui a été utilisé. */
    foreach ($params as $key => $value) {
      $contenu_signature .= $key . "=" . $value . "&";
    }
    $contenu_signature = substr($contenu_signature, 0, -1); ///< Suppression du dernier '&'

    // La chaîne sera envoyée en majuscules, d'oû l'utilisation de strtoupper()
    $signature = strtoupper(hash_hmac($this->algoHash, $contenu_signature, $binKey));
    return $signature;
  }

  /**
   * \brief Méthode qui retourne une url d'appel disponible pour un paiement web PayBox.
   * - Gère le mode TEST et PRODUCTION.
   */
  protected function serveurDispo () {
    if ($this->testMode(true)) {
      if (!empty($this->mobileUrlPreProd) && $this->onMobile()) {
        return $this->mobileUrlPreProd;
      }
      return $this->formUrlPreProd;
    }
    if (!empty($this->mobileUrl) && $this->onMobile()) {
      return $this->mobileUrl;
    }
    return $this->formFirstUrl;
  }

  private function onMobile() {
    static $onMobile = NULL;
    if ($onMobile === NULL) {
      \Seolan\Core\System::loadVendor('mdetect/mdetect.php');
      $onMobile = (new \uagent_info())->DetectMobileQuick();
    }
    return $onMobile;
  }

  /**
   * \brief Fonction de vérication de la signature Paybox.
   * \param String $retour : La reponse du serveur Paybox.
   * \param String $signature : La signature encodée, attachée à la réponse Paybox.
   * \return boulean
   * \note
   * - Charge la clé de vérification Paybox. \link \Seolan\Module\Monetique\Paybox\Paybox::loadKey \endlink
   * - Log en critical si le chargement de la clé échoue.
   */
  private function verificationSignature($retour, $signature) {
    // Chargement de la clé
    $key = $this->loadKey();
    // Si le chargement de la clé échoue
    if (!$key) {
      return false;
    }
    Logs::debug(__METHOD__ . "chaine à vérifier : '$retour', signature : '$signature'");
    // Retour = 1 si valide, 0 si invalide, -1 si erreur
    $verify = openssl_verify($retour, base64_decode($signature), $key);
    openssl_free_key($key);
    return $verify == 1;
  }

  /**
   * \brief  Fonction de récupération de la clé de décryptage  (chargement de la clé publique par défaut).
   * \param Bool $pub : Valorisé à True (par défaut, charge la clé publique).
   * \param String $pass : Le mot de passe permettant d'accéder à la clé privée.
   * \return String $key : La clé ou False.
   */
  private function loadKey($pub = true, $pass = '') {
    $filedata = $this->loadKeyFile();
    if (!$filedata) {
      return false;
    }
    // Récuperation de la clé publique
    if ($pub) {
      return openssl_pkey_get_public($filedata);
    }
    // ou de la clé privée
    return openssl_pkey_get_private($filedata, $pass);
  }

  private function loadKeyFile() {
    // Vérifie que le fichier contenant la clé existe
    if (substr($this->keyFile, 0, 1) != '/') {
      $keyFile = realpath(TZR_WWW_DIR . '../') . '/'. $this->keyFile;
    } else {
      $keyFile = $this->keyFile;
    }
    $filedata = file_get_contents($keyFile);
    if (!$filedata) {
      Logs::critical(__METHOD__, 'chargement de la clé échoué ' . $keyFile);
      return false;
    }
    return $filedata;
  }

  // controle le status de la transaction d'origine
  // paybox envoie un retour à chaque tentative de paiement
  protected function checkStatus($transaction, $transactionOrigin) {
    return true;
  }

  /**
   * \brief Méthode qui retourne l'url pour un dial PPPS (De serveur à serveur).
   */
  protected function getPPPSURL() {
    if ($this->testMode(true)) {
      $urls = [$this->urlPPPStest];
    } else {
      $urls = [$this->urlPPPS1, $this->urlPPPS2];
    }
    foreach ($urls as $url) {
      $host = parse_url($url, PHP_URL_HOST);
      if (file_get_contents("https://$host/load.html")) {
        return $url;
      }
    }
    return NULL;
  }

  /**
   * \brief Fonction de préparation du formulaire Curls à envoyer à Paybox.
   * Concatène les champs du formulaire Paybox à soumettre.
   * \param Array $payboxParams : Le tableau contenant les paramètres à envoyer.
   * \return Sring $payboxForm : La chaine contenant la requête à envoyer.
   */
  private function prepareCurlForm($payboxParams) {
    $payboxForm = '';
    foreach ($payboxParams as $key => $value) {
      $payboxForm .= $key . '=' . $value . '&';
    }
    // On enlève le dernier '&'
    $payboxForm = substr($payboxForm, 0, -1);
    return $payboxForm;
  }

  /**
   * \brief Fonction d'émission d'une requête à Paybox.
   * Envoi une requête CURL à Paybox.
   * \param String $payboxForm : La chaine contenant les paramètres de la transaction.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction en cours qui va être mise à jour.
   * \return \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction passé en paramètre et mise à jour.
   * \note
   * - Recherche du serveur disponible (Gestion des mode tests et production).
   * - Si l'url du serveur est accéssible :
   *  - Initialisation d'une session CURL.
   *  - La réponse du serveur sera dans $result.
   *  - Les erreur (s'il y en a) seront dans $error.
   *  - S'il y a une erreur de certificat, $testSSL est valorisé à SSL.
   *  - S'il n'y pas d'erreur et que le résultat n'est pas faux:
   *   - Traitement du retour.
   *  - Sinon :
   *   - $transaction->responseParms =  $result.$error;
   *   - $transaction->dial = 'ko';
   * \note complémentaire:
   * - Test pour générer une erreur de certificat:
   *  - curl_setopt($ch, CURLOPT_CAPATH, 'test');
   */
  private function sendCurlForm($payboxForm, &$transaction) {
    $url = $this->getPPPSURL();
    if (!$url) {
      $transaction->dial = 'ko';
      $transaction->responseParms = 'pas de serveur disponible';
      Logs::critical(__METHOD__, 'pas de serveur disponible');
      return;
    }
    // Initialisation d'une session CURL
    $ch = curl_init($url);
    // Définition de la méthode POST
    curl_setopt($ch, CURLOPT_POST, 1);
    // Insertion des champs du formulaire
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payboxForm);
    // Définit que le retour doit être mis dans une variable
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // La réponse du serveur sera dans $result
    $result = curl_exec($ch);
    // Les erreur (s'il y en a) seront dans $error
    $error = curl_error($ch);
    curl_close ($ch);
    // S'il n'y pas d'erreur et que le résultat n'est pas faux
    if ( ($error == 0) && ($result !== false) ) {
      // Traitement du retour
      $champs = explode('&', $result);
      $transaction->statusComplement = '';
      foreach($champs as $kv){
        $keyVal = explode('=', $kv);
        if(!empty($keyVal[1])){
          $transaction->responseParms[$keyVal[0]] = $keyVal[1];
        }
      }
      $transaction->dial = 'ok';
    } else {
      $transaction->responseParms = $result . ' error ' . $error;
      $transaction->dial = 'ko';
      Logs::critical(__METHOD__, 'Error curl: ' . $error);
    }
  }

  /* Fonctions de traitement du retour Curl */

  /**
   * \brief Méthode de traitement d'un retour curl Paybox.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction à emettre.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction émise, mise à jour.
   * \note
   * - Extraction du code réponse (retourné par la banque et déffinisant le status de la transaction).
   * - Extraction du commentaire (encodage en utf8, pour une insertion correcte en base).
   * - Si le dial de serveur à serveur s'est déroulé correctement et que la transaction en un succés:
   *  - Mise à jour du status SUCCESS.
   *  - Mémorisation du commentaire dans la complément de status.
   * - Si le dial de serveur à serveur s'est déroulé correctement et que la transaction n'est pas un succés:
   *  - Mise à jour du status ERROR.
   *  - Mémorisation du type d'erreur dans la complément de status.
   * - Si le dial de serveur à serveur indique une erreur de certificat HTTPS:
   *  - $transaction->status = ERROR.
   *  - Il n'y a pas de code bancaire correspondant.
   *  - Mémorisation du message d'erreur retourné par curl dans le statusComplement.
   * - Si le dial n'est pas un succés (Error Curl):
   *  - $transaction->status = WAITTING (La transaction sera à rejouée).
   *  - Dans ce cas reponseParms contient $result.$erreur (retourné par curl).
   * - Si le dial n'a pas du tout abouti (Serveur indisponible):
   *  - Mise à jour du status WAITTING (La transaction sera à rejouée).
   * - Dans tous les autres cas (La transaction est en erreur).
   *  - $transaction->status = ERROR.
   *  - On log les paramètres de cette transaction qui est en erreur.
   */
  private function handlingCurlReturn(&$transaction) {
    Logs::critical(__METHOD__, print_r($transaction, true));
    // Extraction du commentaire (encodage en utf8, pour une insertion correcte en base)
    $transaction->responseParms['COMMENTAIRE'] = utf8_encode($transaction->responseParms['COMMENTAIRE']);
    // Si le dial de serveur à serveur s'est déroulé correctement et que la transaction en un succés
    if ($transaction->dial == 'ok') {
      $transaction->responseCode = $transaction->responseParms['CODEREPONSE'];
      if ($transaction->responseCode == '00000') {
        // Mise à jour du status SUCCESS
        $transaction->status = self::SUCCESS;
        // Mémorisation du commentaire dans la complément de status
        $transaction->statusComplement = $transaction->responseParms['COMMENTAIRE'];
        $transaction->transId = $transaction->responseParms['NUMTRANS'];
      } else {
        // Mise à jour du status ERROR
        $transaction->status = self::ERROR;
        Logs::critical(__METHOD__, substr($transaction->responseCode, 2, 1));
        // Mémorisation du type d'erreur dans la complément de status
        if (substr($transaction->responseCode, 2, 1) != '1') {
          $transaction->statusComplement = $this->getErrorCodePPS(substr($transaction->responseCode, 3));
        } else {
          $transaction->statusComplement = $this->getErrorCode(substr($transaction->responseCode, 3));
        }
      }
    }
    // Si le dial n'est pas un succés (Error Curl)
    elseif ($transaction->dial == 'ko')  {
      $transaction->status = self::WAITTING;
      // Dans ce cas reponseParms contient $result.$erreur (retourné par curl)
      $transaction->statusComplement = $transaction->responseParms;
    }
    // Dans tous les autres cas (La transaction est en erreur)
    else {
      $transaction->status = self::ERROR;
      $transaction->responseCode = 'XXXXX';
      $transaction->statusComplement = 'Error inconnue :' . $transaction->responseParms;
      // On log les paramètres de cette transaction qui est en erreur
      Logs::critical(__METHOD__, 'Error inconnue : ', $transaction->responseParms);
      Logs::critical(__METHOD__, print_r($transaction, true) );
    }
  }

  /**
   * \brief Fonction qui retourne le libellé d'une erreur à partir de son code.
   * \param Int $errorCode : Le code erreur retourné par la banque pour une transaction.
   * \return String $errorLabel : Le libellé de l'erreur ou  'Code erreur non documenté'
   */
  protected function getErrorCodePPS($errorCode) {
    $errorTab = [
      '00' => 'Opération réussie.',
      '00000' => 'Opération réussie.',
      '01' => 'La connexion au centre d’autorisation a échoué ou une erreur interne est survenue. Dans ce cas, il est souhaitable de faire une tentative sur le site secondaire : ppps1.paybox.com.',
      '02' => 'Une erreur de cohérence est survenue.',
      '03' => 'Erreur Paybox. Dans ce cas, il est souhaitable de faire une tentative sur le site secondaire : ppps1.paybox.com.',
      '04' => 'Numéro de porteur invalide.',
      '05' => 'Numéro de question invalide.',
      '06' => 'Accès refusé ou site / rang incorrect.',
      '07' => 'Date invalide.',
      '08' => 'Date de fin de validité incorrecte.',
      '09' => 'Type d\'opération invalide.',
      '10' => 'Devise inconnue.',
      '11' => 'Montant incorrect.',
      '12' => 'Référence commande invalide.',
      '13' => 'Cette version n’est plus soutenue.',
      '14' => 'Trame reçue incohérente.',
      '15' => 'Erreur d’accès aux données précédemment référencées.',
      '16' => 'Abonné déjà existant (inscription nouvel abonné).',
      '17' => 'Abonné inexistant.',
      '18' => 'Transaction non trouvée (question du type 11).',
      '19' => 'Résérvé.',
      '20' => 'Cryptogramme visuel non présent.',
      '21' => 'Carte non autorisée.',
      '22' => 'Plafond atteint.',
      '23' => 'Porteur déjà passé aujourd’hui.',
      '24' => 'Code pays filtré pour ce commerçant.',
      '97' => 'Timeout de connexion atteint.',
      '98' => 'Erreur de connexion interne.',
      '99' => 'Incohérence entre la question et la réponse. Refaire une nouvelle tentative ultérieurement.'
    ];
    // Si le code erreur existe dans le tableau
    if (isset($errorTab[$errorCode])) {
      return $errorTab[$errorCode];
    }
    // Si le ccode erreur n'est pas répertorié.
    else {
      return 'Code erreur non documenté';
    }
  }

  public function initOptions() {
    parent::initOptions();
    $alabel = Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'modulename');
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'rang'), 'rang', 'text', NULL, NULL, $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'identifiant'), 'identifiant', 'text', NULL, NULL,
      $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'hashkey'), 'hashKey', 'text',
      ['cols' => 60, 'rows' => 3], NULL, $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'hashkey') . ' preprod', 'hashKeyPreProd', 'text',
      ['cols' => 60, 'rows' => 3], NULL, $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'algohash'), 'algoHash', 'list',
      ['values' => self::codeHash, 'labels' => self::libelleHash], NULL, $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'formurlprincipale'), 'formFirstUrl', 'text',
      ['size' => 60], NULL, $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'formurlsecondaire'), 'form2NdUrl', 'text',
      ['size' => 60], NULL, $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'formurlpreprod'), 'formUrlPreProd', 'text',
      ['size' => 60], NULL, $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'keyFile'), 'keyFile', 'text', ['size' => 60], NULL,
      $alabel);
    $this->_options->setComment('Absolu ou relatif par rapport à ' . realpath(TZR_WWW_DIR . '../') . '/', 'keyFile');
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'urlpppstest'), 'urlPPPStest', 'text', ['size' => 60],
      NULL, $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'urlppps1'), 'urlPPPS1', 'text', ['size' => 60], NULL,
      $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'urlppps2'), 'urlPPPS2', 'text', ['size' => 60], NULL,
      $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'cleppps'), 'clePPPS', 'text', NULL, '', $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'grouppps'), 'groupPPPS', 'text', NULL, '', $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Paybox_Paybox', 'allowcvcpayment'), 'allowCVCpayment', 'boolean', [], false, $alabel);
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Monetique_Monetique', 'cardtype'), 'cardTypes', 'multiplelist',
      ['values' => array_keys($this->cardTypesPaybox), 'labels' => array_values($this->cardTypesPaybox)], NULL,
      $alabel);
  }

  /**
   * \brief Méthode de vérification de la présence des fichiers nécéssaire à Paybox (Paiement Web uniquement)
   * Permet d'afficher une erreur dans les propriètés du module si le fichier keyFile n'est pas présent.
   */
  public function editProperties($ar) {
    parent::editProperties($ar);
    $this->loadKeyFile(); // check key
  }
}
