<?php

namespace Seolan\Module\Monetique\Atos;

/**
 * \brief Classe \Seolan\Module\Monetique\Atos\Atos.
 * Classe de paiement ATOS/SIPS
 */
class Atos extends \Seolan\Module\Monetique\Monetique {

  // Options spécifiques au module
  protected $needTransId = true;
  protected $oneClickPossible = true; // seulement avec SIPS Office Server
  public $path = null; ///< Chemin où se trouvent les CGI Atos.
  public $defaultTemplate = 'Module/Monetique.atos.html'; ///< Template par défaut
  public $templatefile = NULL; ///< Template

  const SERVER = '81.200.32.61'; ///< adresse SIPS Office Server
  const SERVICE_PORT = 7180; ///< port de service
  const COMMAND_PORT = 7181; ///< port de commande

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
    $transaction->callParms = $this->webPaymentAtosForm($transaction);
    $webPaymentAtosForm['fields'] = $this->callExec($transaction->callParms);
    return [$transaction, $webPaymentAtosForm, TZR_SHARE_DIR . $this->defaultTemplate, 'atosForm'];
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
  private function webPaymentAtosForm(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    \Seolan\Core\Logs::debug(__METHOD__ . ' transaction ', print_r($transaction, true));
    $params = [];
    $params['merchant_id'] = $this->siteId;
    $params['merchant_country'] = $this->lang;
    $params['amount'] = $this->formatOrderAmount($transaction->amount);
    $params['currency_code'] = $this->defaultCurrencyCode;
    $params['pathfile'] = $this->getPath() . 'param/pathfile';
    $params['normal_return_url'] = $this->urlPayed;
    $params['cancel_return_url'] = $this->urlCancelled;
    $params['automatic_response_url'] = $this->urlAutoResponse;
    $params['language'] = $this->lang;
    $params['target'] = '_top';
    $params['order_id'] = $transaction->orderReference;
    $params['customer_email'] = $transaction->customerEmail;
    $params['customer_id'] = preg_replace('@^(\w+:)@', '', $transaction->customerOid);
    $params['customer_ip_address'] = $_SERVER['REMOTE_ADDR'];
    $params['return_context'] = urlencode($transaction->oid);
    $params['transaction_id'] = $transaction->transId;
    // Gestion du mode de capture (Capture par défaut, sinon autorisation seulement)
    if ($transaction->captureMode == self::AUTHORIZATION_ONLY) {
      $params['capture_mode'] = 'VALIDATION';
    } else {
      $params['capture_mode'] = 'AUTHOR_CAPTURE';
    }
    // Préparation des paramètres de paiement à multiples échéances
    if ($transaction->nbDeadLine > 1) {
      $params['capture_mode'] = 'PAYMENT_N';
      $reste = $this->formatOrderAmount($transaction->amount) % $transaction->nbDeadLine;
      $montantDivise = $this->formatOrderAmount($transaction->amount) / $transaction->nbDeadLine;
      $montantDivise = explode('.', $montantDivise);
      $montant = $montantDivise[0];
      $montant1 = $montant + $reste;
      $params['data'] = "NB_PAYMENT={$transaction->nbDeadLine}\;PERIOD=";
      $params['data'] .= "{$transaction->frequencyDuplicate}\;INITIAL_AMOUNT=$montant1\;";
    }
    if ($this->templatefile) {
      $params['templatefile'] = $this->templatefile;
    }
    //$params['capture_day'] repésente le nombre de jour avant la capture
    $params['capture_day'] = $transaction->captureDelay; ///< Nombre de jours pour la capture différée
    \Seolan\Core\Logs::debug(__METHOD__ . ' params ', print_r($params, true));
    return $params;
  }

  private function callExec($params) {
    // Création de la chaine de paramètres
    $form = '';
    foreach ($params as $key => $value) {
      $form .= $key . '=' . escapeshellarg($value) . ' ';
      \Seolan\Core\Logs::debug(__METHOD__."'$key'=>'$value'");
    }
    // Appel de l'executable
    $result = exec($this->getPath() . "bin/static/request $form");
    $tableau = explode("!", $result);
    $code = $tableau[1];
    $error = $tableau[2];
    $message = $tableau[3];
    // Analyse du retour de l'éxécution
    if (( $code == "" ) && ( $error == "" )) {
      \Seolan\Core\Logs::critical(__METHOD__, $this->getPath()."bin/static/request $form");
      \Seolan\Core\Logs::critical(__METHOD__, $this->getPath()."bin/static/request $result");
      throw new \Exception('::callExec : Erreur appel API ATOS request : ' . $message);
    } else if ($code != 0) {
      \Seolan\Core\Logs::critical(__METHOD__, $this->getPath()."bin/static/request $form");
      \Seolan\Core\Logs::critical(__METHOD__, $this->getPath()."bin/static/request $result");
      throw new \Exception('::callExec : Erreur appel API de paiement : ' . $code . " : " . $error . ", " . $message);
    }
    return $message;
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

    if ($params['code'] != 0) {
      $transaction->status = self::INVALID;
      $transaction->statusComplement = 'Error message : ' . $params['code'] . ' : ' . $params['error'];
    } else {
      $transaction->status = $transaction->responseCode === '00' ? self::SUCCESS : self::ERROR;
    }
    return $transaction;
  }

  private function extractAutoresponse() {
    // Appel de l'executable
    $path = $this->getPath();
    $path_bin = $path . 'bin/static/response';
    $pathfile = 'pathfile=' . $path . 'param/pathfile';
    $message = "message=" . $_REQUEST['DATA'];
    $message = escapeshellcmd($message);
    $result = exec("$path_bin $pathfile $message", $output, $ret);
    if ($ret != 0) {
      throw new Exception(__METHOD__ . ' erreur appel executable sips : ' . $result);
    }
    $tableau = explode("!", $result);
    $params = [];
    $params['code'] = $tableau[1];
    $params['error'] = $tableau[2];
    $params['merchant_id'] = $tableau[3];
    $params['merchant_country'] = $tableau[4];
    $params['amount'] = $tableau[5];
    $params['transaction_id'] = $tableau[6];
    $params['payment_means'] = $tableau[7];
    $params['transmission_date'] = $tableau[8];
    $params['payment_time'] = $tableau[9];
    $params['payment_date'] = $tableau[10];
    $params['response_code'] = $tableau[11];
    $params['payment_certificate'] = $tableau[12];
    $params['authorisation_id'] = $tableau[13];
    $params['currency_code'] = $tableau[14];
    $params['card_number'] = $tableau[15];
    $params['cvv_flag'] = $tableau[16];
    $params['cvv_response_code'] = $tableau[17];
    $params['bank_response_code'] = $tableau[18];
    $params['complementary_code'] = $tableau[19];
    $params['complementary_info'] = $tableau[20];
    $params['return_context'] = $tableau[21];
    $params['caddie'] = $tableau[22];
    $params['receipt_complement'] = $tableau[23];
    $params['merchant_language'] = $tableau[24];
    $params['language'] = $tableau[25];
    $params['customer_id'] = $tableau[26];
    $params['order_id'] = $tableau[27];
    $params['customer_email'] = $tableau[28];
    $params['customer_ip_address'] = $tableau[29];
    $params['capture_day'] = $tableau[30];
    $params['capture_mode'] = $tableau[31];
    $params['data'] = $tableau[32];
    $params['order_validity'] = $tableau[33];
    $params['transaction_condition'] = $tableau[34];
    $params['statement_reference'] = $tableau[35];
    $params['card_validity'] = $tableau[36];
    return $params;
  }

  /**
   * Lecture des données de l'autoresponse
   */
  private function getReturnTransaction($params) {
    $transaction = new \Seolan\Module\Monetique\Model\Transaction();
    $transaction->oid = urldecode($params['return_context']);
    $transaction->responseCode = isset($params['bank_response_code']) ? $params['bank_response_code'] : $params['response_code'];
    $transaction->dateVal = substr($params['card_validity'], 4, 2) . substr($params['card_validity'], 2, 2);
    $transaction->amount = sprintf('%.02f', $params['amount'] / 100);
    $numCarte = explode('.', $params['card_number']);
    $transaction->numCarte = chunk_split($numCarte[0] . 'XXXXXXXXXX' . $numCarte[1], 4, ' ');
    $transaction->transId = $params['transaction_id'];
    $transaction->captureDelay = $params['capture_day'];
    if ($params['capture_mode'] == 'AUTHOR_CAPTURE' || $params['capture_mode'] == 'PAYMENT_N') {
      $transaction->captureMode = self::CATCH_PAYMENT;
    } else {
      $transaction->captureMode = self::AUTHORIZATION_ONLY;
    }

    if (!empty($params['complementary_info'])) {
      $transaction->statusComplement = $params['complementary_info'];
    } else {
      $transaction->statusComplement = $this->getErrorCode($transaction->responseCode);
    }
    // un seul retour, pas d'appel supplementaire sur paiement fractionné
    $transaction->nbDeadLine = 1;
    if (preg_match('/NB_PAYMENT=(\d*);/', $params['data'], $data)) {
      $transaction->nbDeadLine = $data[1];
    }
    $transaction->cvv = 'N/A';
    $transaction->porteur = 'N/A';
    $transaction->responseParms = $params;
    return $transaction;
  }

  /* Fonctions de remboursement d'un abonné */

  /**
   * \brief Méthode de traitement d'un remboursement ATOS.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La nouvelle transaction correspondant au remboursement.
   * Doit contenir dans responseParms les paramètre de retour de la transaction à l'origine du remboursement.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le remboursement après l'appel banque.
   * \note
   * - Récupération des informations nécéssaires au remboursement (renseigne $transaction->numTransOri(avec le numero de transaction et $transaction->fromDate).
   * - Diagnostique de l'état de la transaction.
   * - Création du formulaire de remboursement.
   * - Mémorisation des paramètres d'appel.
   * - Choix de l'opération en fonction du status de la transaction (Remboursement ou annulation).
   * - Traitement du retour.
   * - Si le serveur n'a pas répondu:
   *  - $transaction->status = \link \Seolan\Module\Monetique\Monetique::WAITTING \endlink, afin qu'elle puisse être rejouée.
   * - Sinon traitement habituel avec mise en forme des paramètres.
   */
  protected function refundHandling($transaction, $replay = false) {
    if (empty($replay)) {
      list($transaction->numTransOri, $transaction->fromDate, $amountOri) = $this->getInfoTransOri($transaction);
      // Création du formulaire de remboursement
      $transaction->callParms = $this->refundAtosForm($transaction);
      // Mémorisation des paramètres d'appel
      $appel['oid'] = $transaction->oid;
      $appel['callParms'] = $transaction->callParms;
      $appel['options'] = ['callParms' => ['raw' => true, 'toxml' => true]];
      $this->xset->procEdit($appel);
    }
    // Diagnostique de l'état de la transaction
    $retourDiag = $this->diagnosticHandling($transaction);
    if ($retourDiag == '-1') {
      $transaction->status = self::WAITTING;
      $transaction->statusComplement = 'Serveur indisponible le ' . date('d-m-Y') . ' à : ' . date('H:i:s');
      return $transaction;
    } else if ($retourDiag == '0') {
      $transaction->status = self::WAITTING;
      $transaction->statusComplement = 'Services du serveur indisponible le ' . date('d-m-Y') . ' à : ' . date('H:i:s');
      return $transaction;
    }
    // Le retour du diagnostique est correct
    else {
      // Si la transaction est capturée on peut éfféctuer le remboursement
      if ($retourDiag['transaction_status'] == 'CAPTURED') {
        $result = $this->sipsOfficeRequest('office', 'credit', $transaction->callParms);
      }
      // Si la transaction n'est pas capturée on peut l'annullée partiellement
      else if ($retourDiag['transaction_status'] == 'TO_CAPTURE') {
        $result = $this->sipsOfficeRequest('office', 'cancel', $transaction->callParms);
      }
      // Si la transaction est déjà annulée, c'est une erreur
      else if ($retourDiag['transaction_status'] == 'CANCELLED') {
        $transaction->status = self::ERROR;
        $transaction->statusComplement = 'Le remboursement ne peut aboutir, la transaction est déjà annulé.';
        $transaction->responseParms = $retourDiag;
        return $transaction;
      }
      // Si la transaction est déjà annulée, c'est une erreur
      else if ($retourDiag['transaction_status'] == 'REFUSED') {
        $transaction->status = self::ERROR;
        $transaction->statusComplement = 'Le remboursement ne peut aboutir, la transaction d\'origine à été refusé.';
        $transaction->responseParms = $retourDiag;
        return $transaction;
      }
      // Si la transaction est déjà annulée, c'est une erreur
      else {
        $transaction->status = self::ERROR;
        $transaction->statusComplement = 'Le remboursement ne peut aboutir, la transaction d\'origine possède un status à vérifier.';
        $transaction->responseParms = $retourDiag;
        return $transaction;
      }
      // Le serveur est indisponible
      if ($result == '0') {
        $transaction->status = self::WAITTING;
        $transaction->statusComplement = 'Serveur indisponible le ' . date('d-m-Y') . ' à : ' . date('H:i:s');
        return $transaction;
      }
      // Les services du serveur sont indisponibles
      else if ($result == '-1') {
        $transaction->status = self::WAITTING;
        $transaction->statusComplement = 'Services du serveur indisponible le ' . date('d-m-Y') . ' à : ' . date('H:i:s');
        return $transaction;
      }
      // C'est ici que l'on traite réellement le retour du remboursement
      else {
        $transaction->responseParms = $result;
        if ($transaction->responseParms['response_code'] == '00') {
          $transaction->status = self::SUCCESS;
        } else {
          $transaction->status = self::ERROR;
        }
        $transaction->statusComplement = $this->getErrorCode($transaction->responseParms['response_code']);
        return $transaction;
      }
    }
  }

  /**
   * \brief Méthode de génération du formulaire de remboursement qui sera envoyé à la banque.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction correspondant au remboursement.
   * \return Array $params : Tableau contenant les paramètres à transamettre en banque pour éfféctuer le remboursement.
   */
  private function refundAtosForm($transaction) {
    // Construction du formulaire
    $params = [
      'origin' => 'Console Seolan',
      'merchant_id' => $this->siteId,
      'merchant_country' => $this->lang,
      'transaction_id' => $transaction->numTransOri,
      'currency_code' => $this->defaultCurrencyCode,
      'payment_date' => $transaction->fromDate,
      'amount' => $this->formatOrderAmount($transaction->amount)
    ];
    return $params;
  }

  /* Fonctions de diagnotique d'une transaction */

  /**
   * \brief Méthode de diagnotique d'une transaction ATOS.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction à dignostiquer.
   * \return Array:
   * - String $result : 'serveurHS', 'reponseEmpty' ou le résultat en XML
   * - Array $retourDiag : Un tableau associatif contenant le résultat du diagnotique ou null (si le serveur est HS par exemple).
   * \note
   * - Création du formulaire de diagnostique.
   * - Execution de la requête.
   * - Transaformation du retour en tableau associatif.
   */
  protected function diagnosticHandling($transaction) {
    // Création du formulaire de diagnostique
    $atosParams = $this->diagnosticAtosForm($transaction);
    // Execution de la requête
    return $this->sipsOfficeRequest('diag', 'diagnostic', $atosParams);
  }

  /**
   * \brief Méthode de génération du formulaire de diagnostique qui sera envoyé à la banque.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction à diagnostiquer.
   * \return Array $params : Tableau contenant les paramètres à transamettre en banque pour éfféctuer le diagnostique.
   */
  private function diagnosticAtosForm($transaction) {
    $params = [
      'merchant_id' => $this->siteId,
      'merchant_country' => 'fr',
      'transaction_id' => $transaction->numTransOri,
      'payment_date' => $transaction->fromDate
    ];
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
    if ($replay) {
      $result = $this->sipsOfficeRequest('office', 'duplicate', $transaction->_callParms);
    } else {
      list($transaction->numTransOri, $transaction->fromDate) = $this->getInfoTransOri($transaction);

      // Création du formulaire envoyer
      $transaction->callParms = $this->duplicateAtosForm($transaction);
      // Mémorisation des paramètres d\'appel
      $appel['oid'] = $transaction->oid;
      $appel['callParms'] = $transaction->callParms;
      $appel['dateTimeOut'] = date('Y-m-d H:i:s');
      $appel['options'] = ['callParms' => ['raw' => true, 'toxml' => true]];
      $this->xset->procEdit($appel);
      $result = $this->sipsOfficeRequest('office', 'duplicate', $transaction->callParms);
    }
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
    $transaction->responseParms = $result;
    \Seolan\Core\Logs::critical(__METHOD__, print_r($result, true));
    $transaction->responseCode = $transaction->responseParms['bank_response_code'];
    $transaction->transId = $transaction->responseParms['transaction_id'];
    if ($transaction->responseParms['bank_response_code'] == '00') {
      $transaction->status = self::SUCCESS;
    } else {
      $transaction->status = self::ERROR;
    }
    $transaction->statusComplement = $this->getErrorCode($transaction->responseParms['response_code']);
    // Mise à jour des paramètres de la transaction
    return $transaction;
  }

  /**
   * \brief Méthode de génération du formulaire de duplication qui sera envoyé à la banque.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction à dupliquer.
   * \return Array $param : Tableau contenant les paramètres à transamettre en banque pour éfféctuer la duplication.
   */
  private function duplicateAtosForm($transaction) {
    if ($transaction->captureMode == self::CATCH_PAYMENT) {
      $captureMode = 'AUTHOR_CAPTURE';
    } else {
      $captureMode = 'VALIDATION';
    }
    return [
      'data' => /* 'FROM_MERCHANT_ID='.$this->siteId.';FROM_MERCHANT_COUNTRY=fr' */'',
      'origin' => 'console seolan',
      'merchant_id' => $this->siteId,
      'merchant_country' => 'fr',
      'transaction_id' => $transaction->transId,
      'amount' => $this->formatOrderAmount($transaction->amount),
      'currency_code' => $this->defaultCurrencyCode,
      'from_transaction_id' => $transaction->numTransOri,
      'from_payment_date' => $transaction->fromDate,
      'capture_mode' => $captureMode,
      'capture_day' => $transaction->captureDelay,
      'order_validity' => '',
      'order_id' => $transaction->orderReference
    ];
  }

  /**
   * \brief Méthode de ré-émissions des remboursement en attente (Serveur indisponible).
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction correspondant au remboursement qui sera mise à jour pendant la ré-émission.
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction concernée par le remboursement après sa ré-émission.
   */
  protected function duplicateReplay($transaction) {
    return duplicateHandling($transaction, TRUE);
  }

  /* Fonctions utilitaires */

  /// appel server sips pour un service donné

  /**
   * \brief Fonction d'émission d'une requête SIPS Office Server.
   * \param String $component : Type de la requête:
   * - 'diag' : Pour une requête du type 'diagnostic'.
   * - 'office' : Pour une requête du type :
   *  - 'credit'.
   *  - 'cancel'.
   *  - 'duplicate'.
   * \param String $service : Le service appelé :
   * - 'credit' : Correspond à un remboursement.
   * - 'cancel' : Corresspond à une annulation.
   * - 'duplicate' : Correspond à une duplication.
   * - 'diagnostic' : Correspond à un diagnotique de la transaction.
   * \return String $result : 'serveurHS', 'responseEmpty' ou la reponse sous forme de chaine XML.
   */
  private function sipsOfficeRequest($component, $service, $params) {
    $errno = $errstr = '';
    // Pour connaitre l'état du serveur
    if ($component == 'status') {
      $port = self::COMMAND_PORT;
    } else {
      $port = self::SERVICE_PORT;
    }
    $fp = fsockopen(self::SERVER, $port, $errno, $errstr, 10);
    if (!$fp) {
      \Seolan\Core\Logs::critical(__METHOD__, ' Errer de connexion ' . $errno . ' ' . $errstr);
      \Seolan\Core\Logs::critical(__METHOD__, 'Serveur : ' . self::SERVER . ' port :' . $port);
      return -1;
    } else {
      $request = "<service component=\"$component\" name=\"$service\"><$service ";
      ;
      foreach ($params as $key => $value) {
        $request .= "$key=\"$value\" ";
      }
      $request .= "></$service></service>\n"; // !!!

      \Seolan\Core\Logs::debug('sips request ' . self::SERVER . ':' . $port . ' socket = ' . $fp . ' > ' . $request);
      fputs($fp, $request);
      $response = fgets($fp);
      \Seolan\Core\Logs::critical(__METHOD__, 'Retour : ' . $response);
      fclose($fp);
      if (!$response) {
        \Seolan\Core\Logs::critical(__METHOD__, 'erreur lecture reponse ' . self::SERVER . ':' . self::SERVICE_PORT);
        return 0;
      }
      // Parse la reponse
      $xres = simplexml_load_string($response);
      if (!$xres) {
        $t = '';
        foreach (libxml_get_errors() as $error) {
          $t .= $error->message;
        }
        \Seolan\Core\Logs::critical(__METHOD__, 'erreur sips : ' . $t);
        return false;
      }
      return $this->serverResponse2array($xres);
    }
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
  private function getInfoTransOri($transaction) {
    $rs = getDB()->select('select responseParms, amount from ' . $this->xset->getTable() . ' where `KOID`="' . $transaction->transOri . '"');
    $res = null;
    if ($rs->rowCount() == 1) {
      $res = $rs->fetch();
      $params = \Seolan\Core\System::xml2array($res['responseParms']);
      /// Récupération du numéro de transaction
      return [$params['transaction_id'], $params['payment_date'], $res['amount']];
    } else {
      \Seolan\Core\Logs::critical(__METHOD__,
        'responseParms, amount de la transaction d\'origine ayant pour KOID ' . $transaction->transOri . ' non trouvé!');
      throw new \Exception(__METHOD__ . ' responseParms, amount de la transaction d\'origine ayant pour KOID ' . $transaction->transOri . ' non trouvé!');
    }
  }

  /**
   * \brief Fonction de transformation de la réponse du serveur en XML.
   * \param String reponse : La reponse renvoyé par \link \Seolan\Module\Monetique\Atos\Atos::sipsOfficeRequest($component, $service, $params); \endlink
   * \return Array $params : Un tableau associatif de la réponse ou null.
   */
  private function serverResponse2array($response) {
    $params = [];
    if (($response != '-1') && ($response != '0') && (!empty($response))) {
      foreach ($response->children() as $element) {
        foreach ($element->attributes() as $attribut) {
          $params[$attribut->getName()] = (string) $attribut;
        }
      }
      return $params;
    } else {
      \Seolan\Core\Logs::critical(__METHOD__ . ' Reponse :', print_r($response));
      return null;
    }
  }

  /**
   * \brief Fonction d'initialisation des options spécifiques à ATOS.
   * \note
   * Crée les champs:
   * - path \link \Seolan\Module\Monetique\Atos\Atos::$path \endlink
   */
  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Template atos', 'templatefile', 'text', NULL, NULL, 'Monétique');
    $this->_options->setOpt('Chemin de l\'API Atos/Sips', 'path', 'text', NULL, NULL, 'Monétique');
    $this->_options->setComment('Absolu ou relatif par rapport à ' . realpath(TZR_WWW_DIR . '../') . '/', 'path');
  }

  /**
   * \brief Méthode de vérification de la présence des fichiers nécéssaire à ATOS/SIPS (Paiement Web uniquement)
   * Affiche un message d'erreur dans la configuration du module si des fichiers qui lui sont nécéssaire, sont manquants.
   */
  public function editProperties($ar) {
    parent::editProperties($ar);
    $path = $this->getPath();
    if (!file_exists($path)) {
      setSessionVar('message', "Dossier SIPS non présent à l'adresse : '$path'");
    } else {
      if (!file_exists($path . 'bin/static/request')) {
        setSessionVar('message', "Fichier binaire 'request' non présent à l'adresse : '{$path}bin/static/request'");
      }
      if (!file_exists($path . 'bin/static/response')) {
        setSessionVar('message', "Fichier binaire request non présent à l'adresse : '{$path}bin/static/response'");
      }
      if (!file_exists($path . 'param/certif.fr.' . $this->siteId)) {
        setSessionVar('message',
          "Fichier de certificat commerçant non présent à l'adresse : '{$path}param/certif.fr.{$this->siteId}'");
      }
      if (!file_exists($path . 'param/parmcom.' . $this->siteId)) {
        setSessionVar('message',
          "Fichier de parmcom commerçant non présent à l'adresse : '{$path}param/parmcom.{$this->siteId}");
      }
      if (!file_exists($path . 'param/parmcom.defaut')) {
        setSessionVar('message', "Fichier parmcom.defaut non présent à l\'adresse : {$path}param/parmcom.defaut'");
      }
      if (!file_exists($path . 'param/pathfile')) {
        setSessionVar('message', "Fichier pathfile non présent à l'adresse :'{$path}param/pathfile'");
      }
    }
  }

  protected function getPath() {
    if (substr($this->path, 0, 1) == '/') {
      return $this->path;
    }
    return realpath(TZR_WWW_DIR . '../') . '/' . $this->path;
  }

  /**
   * \brief Fonction qui permet d'ajouter des méthode dans le menu du module.
   * Définit les fonction setCertif() et install() permettant de configurer intégralement le module ATOS.
   */
  protected function _actionlist(&$my, $alfunction = true) {
    // Utilisation de la fonction du parent
    parent::_actionlist($my);
    // Mémorisation du moid
    $moid = $this->_moid;
    if ($this->secure('', 'setCertif')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'certif', 'Mettre à jour le certificat',
        '&moid=' . $moid . '&_function=preSetCertif&template=Module/Monetique.atos-certificat.html&tplentry=br');
      $o1->menuable = 1;
      // Affectation au groupe d'affichage
      $o1->group = 'more';
      $o1->order = 0;
      $my['certif'] = $o1;
    }
    if ($this->secure('', 'install')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'install', 'Upload de l\'archive SIPS',
        '&moid=' . $moid . '&_function=preInstall&template=Module/Monetique.atos-archive.html&tplentry=br');
      $o1->menuable = 1;
      $o1->group = 'more';
      $o1->order = 0;
      $my['install'] = $o1;
    }
  }

  /**
   * \brief Fonction qui prepare la mise à jour du certificat dans le backoffice.
   * Crée le champs file permettant l'upload.
   */
  function preSetCertif($ar) {
    $certifFile = \Seolan\Core\Field\Field::objectFactory((object) ['FIELD' => 'certifFile',
          'FTYPE' => '\Seolan\Field\File\File',
          'MULTIVALUED' => 0,
          'COMPULSORY' => true,
          'TARGET' => '%',
          'LABEL' => '...']);
    // Suppression du champs 'Titre' au-dessus de l'input FILE
    $certifFile->usealt = 0;
    $r['certifFile'] = $certifFile->edit([]);
    return \Seolan\Core\Shell::toScreen1('br', $r);
  }

  /**
   * \brief Fonction qui met à jour du certificat à partir du backoffice.
   * Récupère les champs renseigné dans le formulaire.
   */
  function procSetCertif($ar) {
    $certifFile = \Seolan\Core\Field\Field::objectFactory((object) ['FIELD' => 'certifFile',
          'FTYPE' => '\Seolan\Field\File\File',
          'MULTIVALUED' => 0,
          'COMPULSORY' => true,
          'TARGET' => '%',
          'LABEL' => '...']);

    // Définition du répertoire d'installation
    $installDirectoryCertif = '/home/' . $GLOBALS['HOME'] . '/sips/';
    // Déplacement du certificat vers le repertoire sips/param/
    move_uploaded_file($_FILES['certifFile']['tmp_name'], $installDirectoryCertif . 'param/' . $_FILES['certifFile']['name']);
  }

  /**
   * \brief Fonction qui prepare l'upload de l'archive ATOS/SIPS à partir du  backoffice.
   * Définition des champs permettant le téléchargement de l'archive fournis par ATOS/SIPS.
   */
  function preInstall($ar) {
    /// Définiton du champs archive
    $archive = \Seolan\Core\Field\Field::objectFactory((object) ['FIELD' => 'archive', // key de la varible pour récupérer le file, ex : $_FILES['archive']
          'FTYPE' => '\Seolan\Field\File\File', // Définition du type de champs (Fichier en l'occurence)
          'MULTIVALUED' => 0, // Ce n'est pas un champs multi-valué
          'COMPULSORY' => true, // C'est un champs obligatoire
          'TARGET' => '%'// Qui ne pointe vers rien
    ]);
    $archive->usealt = 0; // Supprime le champs Titre au dessus de l'input du file
    $r['archive'] = $archive->edit([]);
    $certifFile = \Seolan\Core\Field\Field::objectFactory((object) ['FIELD' => 'certifFile',
          'FTYPE' => '\Seolan\Field\File\File',
          'MULTIVALUED' => 0,
          'COMPULSORY' => true,
          'TARGET' => '%'
    ]);
    $certifFile->usealt = 0;
    $r['certifFile'] = $certifFile->edit([]);
    return \Seolan\Core\Shell::toScreen1('br', $r);
  }

  /**
   * \brief Fonction qui install l'archive ATOS.
   * Mise en place des différents file présent dans l'archive.
   */
  function procInstall($ar) {
    $archive = \Seolan\Core\Field\Field::objectFactory((object) ['FIELD' => 'archive',
          'FTYPE' => '\Seolan\Field\File\File',
          'MULTIVALUED' => 0,
          'COMPULSORY' => true,
          'TARGET' => '%',
    ]);

    $certifFile = \Seolan\Core\Field\Field::objectFactory((object) ['FIELD' => 'certifFile',
          'FTYPE' => '\Seolan\Field\File\File',
          'MULTIVALUED' => 0,
          'COMPULSORY' => true,
          'TARGET' => '%',
          'LABEL' => '...']);

    // Définition du répertoire d'installation
    $installDirectory = '/home/' . $GLOBALS['HOME'] . '/sips/';
    // Si le repertoire d'installation existe dejà
    if (is_dir($installDirectory)) {
      /// Suppression du folder d'installation pour une réinstallation propre
      $this->clearDir($installDirectory);
    }
    // Création du repertoire d'installation de l'API
    mkdir($installDirectory);
    // Déplacement de l'archive vers le reperrtoire d'installation
    move_uploaded_file($_FILES['archive']['tmp_name'], $installDirectory . 'archiveSIPS.tar');
    // Décompréssion de l'archive
    $this->untar($installDirectory . 'archiveSIPS.tar', $installDirectory . 'dossierSIPS');
    // Suppression de l'archive
    unlink($installDirectory . 'archiveSIPS.tar');
    // Déplacement du folder param contenu dans le folder de l'archive
    rename($installDirectory . 'dossierSIPS/param', $installDirectory . 'param');
    // Déplacement du folder bin contenu dans le folder de l'archive
    rename($installDirectory . 'dossierSIPS/bin', $installDirectory . 'bin');
    // Suppresion du file Readme.txt
    unlink($installDirectory . 'bin/README.txt');
    // Suppression du folder décompresser
    $this->clearDir($installDirectory . 'dossierSIPS/');
    /* Edition du file pathfile */
    // Ouverture du file
    $ptr = fopen($installDirectory . 'param/pathfile', "r");
    // Récupération du contenu
    $contenu = fread($ptr, filesize($installDirectory . 'param/pathfile'));
    /* On a plus besoin du pointeur */
    fclose($ptr);
    // Transformation du contenu en tableau de lignes
    // PHP_EOL contient le saut à la ligne utilisé sur le serveur (\n linux, \r\n windows ou \r Macintosh
    $contenu = explode(PHP_EOL, $contenu);
    /* Edition des lignes personalisées */
    // Affectation de l'emplacement du file parmcom.defaut
    $contenu[29] = 'F_DEFAULT!/home/' . $GLOBALS['HOME'] . '/sips/param/parmcom.defaut!';
    // Affectation de l'emplacement du file parmcom.numCommerçant
    $contenu[33] = 'F_PARAM!/home/' . $GLOBALS['HOME'] . '/sips/param/parmcom!';
    // Affectation de l'emplacement du file certif.fr.numCommerçant
    $contenu[37] = 'F_CERTIFICATE!/home/' . $GLOBALS['HOME'] . '/sips/param/certif!';
    // On ré-index le tableau
    $contenu = array_values($contenu);
    // On reconstruit le tout
    $contenu = implode(PHP_EOL, $contenu);
    // On réouvre le file
    $ptr = fopen($installDirectory . 'param/pathfile', "w");
    // On écris le nouveau contenu
    fwrite($ptr, $contenu);
    // Déplacement du certificat vers le repertoire sips/param/
    move_uploaded_file($_FILES['certifFile']['tmp_name'], $installDirectory . 'param/' . $_FILES['certifFile']['name']);
  }

  /**
   * \brief Fonction qui qui extrait une archive .tar dans le folder spécifié.
   * \param String $file : Le chemin de l'archive à extraire.
   * \param String $dest : Le chemin du folder dans lequel sera extraite l'archive.
   * \return Bool $result : True ou False;
   */
  function untar($file, $dest = "./") {
    if (!is_readable($file)) {
      return false;
    }
    $filesize = filesize($file);
    // Minimum 4 blocks
    if ($filesize <= 512 * 4) {
      return false;
    }
    if (!preg_match("/\/$/", $dest)) {
      // Force trailing slash
      $dest .= "/";
    }
    //Ensure write to destination
    if (!file_exists($dest)) {
      if (!mkdir($dest, 0777, true)) {
        return false;
      }
    }
    $fh = fopen($file, 'rb');
    $total = 0;
    while (false !== ($block = fread($fh, 512))) {
      $total += 512;
      $meta = [];
      // Extract meta data
      // http://www.mkssoftware.com/docs/man4/tar.4.asp
      $meta['filename'] = trim(substr($block, 0, 99));
      $meta['mode'] = octdec((int) trim(substr($block, 100, 8)));
      $meta['userid'] = octdec(substr($block, 108, 8));
      $meta['groupid'] = octdec(substr($block, 116, 8));
      $meta['filesize'] = octdec(substr($block, 124, 12));
      $meta['mtime'] = octdec(substr($block, 136, 12));
      $meta['header_checksum'] = octdec(substr($block, 148, 8));
      $meta['link_flag'] = octdec(substr($block, 156, 1));
      $meta['linkname'] = trim(substr($block, 157, 99));
      $meta['databytes'] = ($meta['filesize'] + 511) & ~511;
      if ($meta['link_flag'] == 5) {
        // Create folder
        mkdir($dest . $meta['filename'], 0777, true);
        chmod($dest . $meta['filename'], $meta['mode']);
      }
      if ($meta['databytes'] > 0) {
        $block = fread($fh, $meta['databytes']);
        // Extract data
        $data = substr($block, 0, $meta['filesize']);
        // Write data and set permissions
        if (false !== ($ftmp = fopen($dest . $meta['filename'], 'wb'))) {
          fwrite($ftmp, $data);
          fclose($ftmp);
          touch($dest . $meta['filename'], $meta['mtime'], $meta['mtime']);
          if ($meta['mode'] == 0744)
            $meta['mode'] = 0644;
          chmod($dest . $meta['filename'], $meta['mode']);
        }
        $total += $meta['databytes'];
      }
      if ($total >= $filesize - 1024) {
        return true;
      }
    }
  }

  /**
   * \brief Fonction recursive de suppression d'un repertoire contenant des fichiers.
   * \param String $folder : Le chemin du folder à supprimer.
   * \return Bool $result : True ou False.
   */
  function clearDir($folder) {
    $open = opendir($folder);
    if (!$open) {
      return;
    }
    while ($file = readdir($open)) {
      if ($file == '.' || $file == '..') {
        continue;
      }
      if (is_dir($folder . "/" . $file)) {
        $r = $this->clearDir($folder . "/" . $file);
        if (!$r)
          return false;
      } else {
        $r = unlink($folder . "/" . $file);
        if (!$r)
          return false;
      }
    }
    closedir($open);
    $r = rmdir($folder);
    rename($folder, "trash");
    return true;
  }

  /**
   * \brief Fonction de définition des droits sur les fonctions.
   * \param ? $function : ?.
   * \param ? $group : ?.
   * \return ? $result : ?.
   */
  function secGroups($function, $group = NULL) {
    $g = [];
    $g['preInstall'] = ['admin'];
    $g['procInstall'] = ['admin'];
    $g['preSetCertif'] = ['admin'];
    $g['procSetCertif'] = ['admin'];
    if (isset($g[$function])) {
      if (!empty($group))
        return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function, $group);
  }

}
