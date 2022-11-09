<?php
/**
 * \name     \Seolan\Module\Monetique\Monetique
 * \brief   Classe abstraite, base des modules "monetique/VAD".
 * \details Cette classe permet d'unifier les appels et retours des différents modules de paiements en ligne (ATOS/SIPS, SystemPay et Paybox).
 */
namespace Seolan\Module\Monetique;
abstract class Monetique extends \Seolan\Module\Table\Table{
  // Options communes à tout (ou presque) les modules de paiements
  const CATCH_PAYMENT = 'capture';
  const AUTHORIZATION_ONLY = 'autorisation';
  // Constantes correspondant au type de transaction.
  const DUPLICATE = 'subscdebit'; ///< Prelèvement 'manuel' sur un abonné / enrollé.
  const WEB_PAYMENT = 'webpayment';    ///< Paiement de base par internet.
  const MPOS = 'mpos';    ///< Paiement mobile
  const REFUND = 'refund';   ///< Remboursement.
  const UPDATE_CARD = 'update';   ///< Remboursement.
  // Constantes pour la gestion du retour vers la boutique.
  const ASYNC_RESPONSE = 'async'; ///< Notifier la boutique en arrière plan.
  const SYNC_RESPONSE = 'sync';   ///< Notifier la boutique dès le retour.
  const RESPONSE_NONE = 'none';   ///< Ne pas notifier la boutique.
  // Status de notification de la réponse vers la boutique.
  const RESPONSE_STATUS_SENT = 'sent';     ///< Boutique notifiée.
  const RESPONSE_STATUS_TO_SEND = 'tosend'; ///< Boutique à notifier.
  const RESPONSE_STATUS_NOT_TO_SEND = 'nottosend'; ///< Boutique à notifier.
  // Constantes correspondant à l'état d'une transaction
  const RUNNING = 'running';  ///< En cours de traitement (on attend le retour banque, qui ne viendra peut être jamais, si le client n'arrive pas au bout de la transaction).
  const WAITTING = 'waitting'; ///< Opération en attente (Soit le serveur ATOS n'est pas disponible, soit il faut attendre que la transaction est un status permettant l'opération en attente pour SystemPay.
  const SUCCESS = 'success';   ///< Opération réalisée avec succés, que ce soit un paiement web, un remboursement ou une duplication.
  const ERROR = 'error';    ///< Opération échoué, motifs exacte dans compelement de status (statusComplement) \link \Seolan\Module\Monetique\Model\Transaction::$statusComplement \endlink
  const INVALID = 'invalid';    ///< Correspond à une erreur de vérification de signature.

  protected $needTransId = false; // doit-on générer le transId
  protected $oneClickPossible = false; // le système gère les enrollements
  public $transIdMask = '%06d'; // masque de génération du transId
  public $siteId = null; ///< Identifiant du site marchand.
  public $urlPayed = null; ///< Url de redirection du client pour un paiement accepté.
  public $urlCancelled = null; ///< Url de redirection du client en cas d'annulation.
  public $urlRefused = null; ///< Url de redirection du client en cas d'erreur.
  public $urlAutoResponse = null; ///< Url de retour automatique serveur.
  public $oneClick = false; ///< Gestion des enrollements
  public $lang = 'fr'; ///< Langue par defaut si non transmise.
  public $defaultCurrencyCode = '978'; ///< La devise par défaut en l'euro.
  public $defaultCurrency = 'EUR';
  protected $defaultTemplate = null; ///< Template par defaut.

  static public $upgrades = ['20190923'=>''];

  function __construct($ar = NULL) {
    parent::__construct($ar);
    if (in_array(\Seolan\Core\Shell::_function(), ['editProperties', 'procEditProperties', 'newModule'])) {
      return;
    }
    foreach (['urlPayed', 'urlCancelled', 'urlAutoResponse'] as $url) {
      if (substr($this->$url, 0, 1) == '/') {
        $this->$url = \Seolan\Core\Session::makeDomainName() . $this->$url;
      }
    }
  }
  /* Retourne le moid du module qui a servi a payer une commande */
  static function getMonetique($orderOid) {
    return getDB()->fetchOne('SELECT monetiqueMoid FROM TRANSACMONETIQUE WHERE orderOid=?', [$orderOid]);
  }

  /* Fonctions de paiements boutique */

  // retourne les moyens de paiements disponible
  public function getPaymentMethods() {
    if (!$this->checkConf()) {
      return [];
    }
    return ['cards' => $this->getCards(), 'oneClick' => $this->oneClick];
  }

  public function getCards() {
    if (empty($this->cardTypes) || empty($this->cardTypes[0]))
      return ['CB'];
    return $this->cardTypes;
  }

  // vérifie que le module est correctement configuré.
  protected function checkConf() {
    return TRUE;
  }

  /**
   * \brief Méthode de génération des données de paiement.
   * Cette fonction permet de générer les données d'un paiement et l'insert dans la table TRANSACMONETIQUE.
   * \param \Seolan\Module\Monetique\Model\Order $order :  Objet des données issues de la commande.
   * \param \Seolan\Module\Monetique\Model\Customer $customer : Objet des données issues du client.
   * \param \Seolan\Module\Monetique\Model\Shop $shop : Objet comportant les paramètres de la boutique.
   * \note
   * - Gestion du paiement à multiple écheances. \link \Seolan\Module\Monetique\Model\Order::$options \endlink
   * - Mémorise le nombre de retour banque (Spécifique à Paybox, maximum 3).\Seolan\Module\Monetique\Model\Order::$options \endlink
   * \exception:
   * - Error : \link webPaymentHandling(\Seolan\Module\Monetique\Model\Order $order, \Seolan\Module\Monetique\Model\Customer $customer, \Seolan\Module\Monetique\Model\Shop $shop, $transactionOid) \endlink.
   * \return array :
   * - String ok/ko.
   * - Array $formulaireAppel : Contient le formulaire envoyé en banque
   * - String $template : Le template correspondant correspondant un module de traitement
   * - String $tplentry : L'entrée smarty du template.
   * - Sring $transaction->oid : L'oid de la transaction \link \Seolan\Module\Monetique\Model\Transaction::$oid \endlink
   * \note
   * - Vérifie que toutes les infos nécésaires sont bien présentes
   * - Paramètres la transaction, l'insère en base et récupère son oid.
   * - Délègue au module approprié la création du formaulaire.
   * - Mémorise les données brutes d'appel et le status.
   */
  public function paymentCall(\Seolan\Module\Monetique\Model\Order $order, \Seolan\Module\Monetique\Model\Customer $customer, \Seolan\Module\Monetique\Model\Shop $shop) {
    // En mode test, seules les ip de test sont autorisées
    if ($this->testMode(true) && !$this->testMode()) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' erreur : test mode on, ip not allowed');
      return ['ko'];
    }
    if (!$this->checkConf()) {
      return ['ko'];
    }
    // Vérifie que toutes les infos nécessaires sont bien présentes.
    try {
      $this->checkPaymentData($order, $customer, $shop);
    } catch (\Exception $e) {
      \Seolan\Core\Logs::critical(__METHOD__ . " {$order->reference} checkPaymentData(): " . $e->getMessage());
      return ['ko'];
    }
    $transaction = new \Seolan\Module\Monetique\Model\Transaction();

    if (isset($customer->lang)) {
      $transaction->lang = $customer->lang;
    }

    if (!empty($shop->cardTypes)) {
      $transaction->cardTypes = is_array($shop->cardTypes) ? $shop->cardTypes : [$shop->cardTypes];
    } else {
      $transaction->cardTypes = $this->getCards();
    }
    $transaction->status = self::RUNNING;
    if ($order->transactionType) {
      $transaction->type = $order->transactionType;
    } else {
      $transaction->type = self::WEB_PAYMENT;
    }
    if (!empty($order->returnContext))
      $transaction->returnContext = $order->returnContext;
    if (!empty($order->statementReference))
      $transaction->statementReference = $order->statementReference;
    if (!empty($order->phonePaymentType)) {
      $transaction->phonePaymentType = $order->phonePaymentType;
      $transaction->phone = $customer->phone;
    }
    /* Vérification des paramètres de la commande */
    $transaction->orderOid = $order->oid;
    $transaction->orderReference = $order->reference;
    $transaction->amount = $order->amount;
    $transaction->orderDate = $order->date;
    // Préparation des paiement à multiples échéances
    if (isset($order->options['nbDeadLine']) && $order->options['nbDeadLine'] > 1) {
      $transaction->nbDeadLine = $order->options['nbDeadLine'];
      // Définition de la fréquence de prélèvement en jours
      if (empty($order->options['frequencyDuplicate'])) {
        $transaction->frequencyDuplicate = '30';
      } else {
        $transaction->frequencyDuplicate = $order->options['frequencyDuplicate'];
      }
    }
    // Si la commande indique que la capture doit être différé
    if (isset($order->options['captureDelay'])) {
      $transaction->captureDelay = $order->options['captureDelay'];
    }
    // Gestion du mode de capture (Capture par défaut, sinon autorisation seulement)
    if ($order->options['noCapture'] == true) {
      $transaction->captureMode = self::AUTHORIZATION_ONLY;
    } else {
      $transaction->captureMode = self::CATCH_PAYMENT;
    }
    // Vérification de l'option enrollement
    if (isset($order->options['refAbonne']) && $order->options['enrollement'] == true) {
      $transaction->enrollement = true;
      $transaction->refAbonneBoutique = $order->options['refAbonne'];
    }
    $transaction->customerOid = $customer->oid;
    if (isset($customer->email)) {
      $transaction->customerEmail = $customer->email;
    }
    if (isset($shop->class)) {
      $transaction->shopClass = $shop->class;
    }
    // Paramètres de traitement de la réponse.
    $transaction->autoResponseMode = $shop->autoResponseMode;
    $transaction->shopCallBack = $shop->autoResponseCallBack;
    $transaction->shopName = $shop->name;
    $transaction->shopMoid = $shop->moid;
    $transaction->dateCreated = date('Y-m-d H:i:s');
    // traçabilité des commandes
    if ($order->traceId) {
      $transaction->traceId = $order->traceId;
    }
    // 3D Secure
    if (isset($shop->threeDS)) {
      $transaction->threeDS = $shop->threeDS;
    }
    // Créer la transaction en base et récupère son oid pour les paramètres d'appel.
    $transaction->customerInfos = $customer;
    $transaction->shop = $shop;
    $transaction = $this->insertTransaction($transaction);
    // Tentative de crétion du formulaire.
    try {
      // Appel au module concerné
      list($transaction, $formulaireAppel, $template, $tplentry) = $this->webPaymentHandling($transaction);
      $formulaireAppel['noECard'] = ($transaction->nbDeadLine > 1 || $transaction->enrollement);
      $formulaireAppel['moid'] = $this->_moid;
      $formulaireAppel['customer'] = $customer;
      $formulaireAppel['oneClick'] = $this->oneClick;
      $formulaireAppel['transaction'] = $transaction;
      $returnValue = ['ok', $formulaireAppel, $template, $tplentry, $transaction->oid];
    } catch (\Exception $e) {
      $transaction->status = self::ERROR;
      \Seolan\Core\Logs::critical(__METHOD__ . ' exeception ' . $e->getMessage() . ' Code ' . $e->getCode());
      $transaction->statusComplement = $e->getMessage() . ' / ' . $e->getCode();
      throw new \Exception(__METHOD__ . ' ' . $transaction->statusComplement);
    }

    // Mémorisation de la date d'émission
    $transaction->dateTimeOut = date('Y-m-d H:i:s');
    // Enregistrement des paramètres d'appel
    $this->procEditTransaction($transaction);
    return ['ok', $formulaireAppel, $template, $tplentry, $transaction->oid];
  }

  /**
   * \brief Fonction de traitement du retour banque automatique.
   * Cette fonction permet de faire le traitement du retour banque en passant par le module approprié.
   * \exception:
   * - Transaction introuvable.
   * - Montant d'appel et de réponse incohérents.
   * - Trop grand nombre de retour pour une transaction.
   * \return boolean : True (Pour un succès) ou False ( Error : \link webPaymentUnFoldReponse() \endlink )
   * \note
   * - Traite la réponse avec le module approprié. \link webPaymentUnFoldReponse() \endlink.
   * - Si l'oid de la transaction est introuvable, elle crée une nouvelle transaction pour mémoriser le paramètres reçus et lève une exception.
   * - Récupère les informations de la transaction d'origine. \link getTransaction($transactionOid) \endlink
   * - Lève une exception si il y a une incohérence du montant, entre les paramètres d'appel et de réponse du serveur bancaire.
   * - Mémorise le retour.
   * - Recupère tous les champs de la transaction non renseigné dans la reponse.
   * - Si la signature est vérifié, gestion de la notification boutique.
   */
  public function autoresponse() {
    // Tentative de traitement de la réponse par le module approprié
    try {
      $transaction = $this->webPaymentUnFoldReponse();
      \Seolan\Core\Logs::critical(__METHOD__ . ' Transaction: ' . var_export($transaction, 1));
    } catch (\Exception $e) {
      return $this->autoResponseError('Exception : ' . $e->getMessage() . ' Code : ' . $e->getCode());
    }
    // Si l'oid de la transaction n'a pas été trouvé par le module approprié, on crée une nouvelle transaction pour mémoriser les paramètres reçus.
    if ($transaction->oid == null) {
      $transaction->statusComplement .= ' Transaction d\'origine non trouvée';
      $transaction->status = self::ERROR;
      $this->insertTransaction($transaction);
      return $this->autoResponseOk();
    }
    // Si la transaction à été trouvée, on récupère les informations de la transaction d'origine
    $transactionOrigin = $this->getTransaction($transaction->oid);
    // Récupération des informations d'origine
    foreach ($transactionOrigin as $key => $value) {
      if (empty($transaction->$key)) {
        $transaction->$key = $value;
      }
    }
    $xml = '';
    if ($transaction->status == self::INVALID) {
      \Seolan\Core\System::array2xml($transaction->responseParms, $xml);
      \Seolan\Core\Logs::update('update', $transaction->oid, 'invalid response received : ' . $transaction->statusComplement . $xml);
      return $this->autoResponseOk();
    }
    // selon les backend, notification de tous les refus
    if ($transaction->status == self::ERROR) {
      \Seolan\Core\System::array2xml($transaction->responseParms, $xml);
      \Seolan\Core\Logs::update('update', $transaction->oid, 'payment error response received : ' . $transaction->statusComplement . $xml);
      return $this->autoResponseOk();
    }
    $this->removeOtherTransactionWithEqualsRef($transaction);
    // Incrémentation du nombre de retour
    $transaction->nbReturn = 1 + $transactionOrigin->nbReturn;
    // Verification de l'etat de la transaction
    if (!$this->checkStatus($transaction, $transactionOrigin)) {
      \Seolan\Core\System::array2xml($transaction->responseParms, $xml);
      \Seolan\Core\Logs::update('update', $transaction->oid, 'transaction déjà validée : ' . $transaction->statusComplement . $xml);
      return $this->autoResponseOk();
    }
    if ($this->isPartialTransaction($transaction, $transactionOrigin)) {
      \Seolan\Core\System::array2xml($transaction->responseParms, $xml);
      \Seolan\Core\Logs::update('update', $transaction->oid, 'transaction en cours : ' . $transaction->statusComplement . $xml);
      $this->procEditTransaction($transaction);
      return $this->autoResponseOk();
    }
    $transaction->dateTimeIn = date('Y-m-d H:i:s');
    // Vérification de la cohérence du montant, entre les paramètres d'appel et de réponse du serveur bancaire
    if (!$this->checkResponseAmount($transaction, $transactionOrigin)) {
      $transaction->status = self::ERROR;
      $transaction->responseStatus = $transactionOrigin->autoResponseMode === self::ASYNC_RESPONSE ? self::RESPONSE_STATUS_TO_SEND : 'NA';
      $transaction->statusComplement = "Montants incorrects, paiement $transaction->amount, total commande $transaction->checkAmount";
      $this->procEditTransaction($transaction);
      try {
        $order = new Model\Order();
        $order->amount = $transactionOrigin->amount;
        $order->reference = $transactionOrigin->orderReference;
        $this->refundCall($transaction->oid, $order, new Model\Shop());
      } catch (\Exception $e) {
        $message = "Commande $transactionOrigin->orderReference\n";
        $message .= "Montant reçu: $transaction->amount, montant commande: $transaction->checkAmount\n";
        $message .= 'Erreur annulation: ' . $e->getMessage();
        bugWarning($message, false, false);
      }
      if ($transactionOrigin->autoResponseMode === self::SYNC_RESPONSE) {
        $this->notifyShop($transaction);
      }
      return $this->autoResponseOk();
    }

    // Affectation du libellé du code erreur dans le statusComplement si il est vide
    if (empty($transaction->statusComplement)) {
      $transaction->statusComplement = $this->getErrorCode($transaction->responseCode);
    }
    // Mémorisation de la réponse
    $this->procEditTransaction($transaction);
    // création enrollement
    if ($transaction->refAbonneBoutique && $transaction->status == self::SUCCESS) {
      $transaction->enrollement = $this->insertEnrollement($transaction);
    }
    // Si nécessaire on notifie le retour à la boutique
    if ($transaction->autoResponseMode === self::SYNC_RESPONSE) {
      $this->notifyShop($transaction);
    }
    // Sinon on valorise le statut de réponse à : RESPONSE_STATUS_TO_SEND, afin qu'il soit traité de manière asynchrone
    elseif ($transaction->autoResponseMode === self::ASYNC_RESPONSE) {
      $this->xset->procEdit([
        'oid' => $transaction->oid,
        'responseStatus' => self::RESPONSE_STATUS_TO_SEND,
        '_options' => ['local' => true]
      ]);
    }
    return $this->autoResponseOk();
  }

  // reponse au serveur bancaire
  protected function autoResponseError($error) {
    \Seolan\Core\Logs::critical(__METHOD__ , $error);
    header('HTTP/1.1 500 Seolan Server Error');
    return false;
  }

  protected function autoResponseOk() {
    return true;
  }

  // controle le status de la transaction d'origine
  protected function checkStatus($transaction, $transactionOrigin) {
    return $transactionOrigin->status != self::RUNNING || $transaction->nbReturn <= $transactionOrigin->nbDeadLine;
  }

  protected function isPartialTransaction($transaction, $transactionOrigin) {
    return false;
  }

  /**
   * \brief Fonction d'insertion d'une transaction en base.
   * Cette fonction permet d'inserer une transaction avant l'appel en banque.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction mise à jour
   */
  protected function insertTransaction($transaction) {
    $transaction->monetiqueMoid = $this->_moid;
    $transaction->status = $transaction->status ?: self::RUNNING;
    $transaction->responseStatus = self::RESPONSE_NONE;
    $transaction->ip = $_SERVER['REMOTE_ADDR'];
    $transaction->userAgent = $_SERVER['HTTP_USER_AGENT'];
    $transaction->transId = $this->genTransId();
    $ar = $this->prepareTransac($transaction);
    $ar['_nolog'] = true;
    $r = $this->xset->procInput($ar);
    $transaction->oid = $r['oid'];
    return $transaction;
  }

  protected function procEditTransaction($transaction) {
    return $this->xset->procEdit($this->prepareTransac($transaction));
  }

  protected function prepareTransac($transaction) {
    if (is_object($transaction)) {
      $transaction = (array) $transaction;
    }
    if (!isset($transaction['options'])) {
      $transaction['options'] = [];
    }
    foreach (['callParms', 'responseParms', 'customerInfos'] as $field) {
      $transaction['options'][$field] = ['raw' => true];
      if (is_object($transaction[$field])) {
        $transaction[$field] = (array) $transaction[$field];
      }
      if (is_array($transaction[$field])) {
        $transaction['options'][$field]['toxml'] = true;
      }
    }
    $transaction['_options']['local'] = true;
    return $transaction;
  }

  protected function removeOtherTransactionWithEqualsRef($transaction) {
    getDB()->execute(
      'delete from TRANSACMONETIQUE where KOID!=? and orderReference=? and status=?',
      [$transaction->oid, $transaction->orderReference, self::RUNNING]);
  }
  /**
   * Fonction de suppression d'un enrollement
   * @param String $enrollmentOid
   */
  public function deleteEnrollement($enrollmentOid){
    \Seolan\Core\Logs::debug(__METHOD__.' : '.$enrollmentOid);
    \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('ENROMONETIQUE')->del(['_options'=>['local'=>1],'oid'=>$enrollmentOid]);
  }
  /**
   * Fonction de création d'un enrollement.
   * @param type $transaction
   * @return String Oid du nouvel enrollement.
   */
  protected function insertEnrollement($transaction) {
    // updateifexists ne match pas le n° de carte (like)
    $exist = getDB()->fetchOne('select KOID from ENROMONETIQUE where customerOid=? and numCarte=? and dateVal=?',
      [$transaction->customerOid, $transaction->numCarte, $transaction->dateVal]);
    $function = $exist ? 'procEdit' : 'procInput';
    $ret = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('ENROMONETIQUE')->$function([
      'oid' => $exist,
      'customerOid' => $transaction->customerOid,
      'refAbonne' => $transaction->refAbonneBoutique,
      'transOri' => $transaction->oid,
      'porteur' => $transaction->porteur,
      'dateVal' => $transaction->dateVal,
      'cvv' => $transaction->cvv,
      'numCarte' => $transaction->numCarte,
      'monetiqueMoid' => $this->_moid,
      '_options' => ['local' => true]
    ]);
    return $ret['oid'];
  }

  /**********************************************************
   *************** Fonctions de remboursement ***************
   *********************************************************/

  /**
   * Fonction de remboursement.
   * @param type $transactionOriginOid oid transaction d'origine.
   * @param type $order Paramètre du remboursement, avec le montant.
   * @param type $shop paramètres de la boutique (autoResponseCallBack, autoResponseMode, ...).
   * @return Array :
   * - String ok/ko.
   * - String oid de transaction de remboursement.
   * - Transaction transaction de remboursement.
   * @throws Exception
   */
  public function refundCall($transactionOriginOid, $order, $shop) {
    // Recherche la transaction d'origine
    if (empty($transactionOriginOid)) {
      $transactionOriginOid = $this->getIdTransactionWithOrderRef($order->reference, self::SUCCESS);
    }
    $transactionOrigin = $this->getTransaction($transactionOriginOid);
    // Problème de paramètres, impossible de trouver la transaction d'origine
    if (!$transactionOrigin) {
      throw new \Exception('refundCall : Transaction d\'origine introuvable. Order reference : ' . $order->reference);
    }
    // Vérification que le montant à rembourser à été saisi
    if (empty($order->amount)) {
      throw new \Exception('refundCall : Aucun montant de remboursement saisie.');
    }
    // check cohérence montant remboursement
    if ((int)$order->amount * 100 > (int)$transactionOrigin->amount * 100) {
      throw new \Exception('refundCall : Le montant du remboursement ne peut être supérieur au montant d\'origine : '
        . $order->amount . ' > ' . $transactionOrigin->amount);
    }
    /* Initialisation de la transaction de remboursement */
    $refundTransaction = new \Seolan\Module\Monetique\Model\Transaction();
    // Mémorisation de la transaction d'origine
    $refundTransaction->transOri = $transactionOrigin->oid;
    $refundTransaction->transactionOrigin = $transactionOrigin;
    // Affectation de la date de création du remboursement
    $refundTransaction->dateCreated = date('Y-m-d H:i:s');
    // Affectation du montant à rembourser
    $refundTransaction->amount = $order->amount;
    $refundTransaction->refundedAmount = $this->getRefundedAmount($transactionOrigin->oid);
    // vérification du montant / remboursement déjà effectués
    if ((int)$order->amount * 100 > (int)$transactionOrigin->amount * 100 - (int)$refundTransaction->refundedAmount * 100) {
      throw new \Exception('refundCall : Le montant du remboursement ne peut être supérieur au montant restant : '
        . $order->amount . ' > ' . $transactionOrigin->amount . ' - ' . $refundTransaction->refundedAmount);
    }
    // Récupération de l'oid de la commande à remboursée
    $refundTransaction->orderOid = $transactionOrigin->orderOid;
    // Génération de la référence de la commande de remboursement
    $refundTransaction->orderReference = $order->reference;
    // Le remboursement est immédiat
    $refundTransaction->captureMode = self::CATCH_PAYMENT;
    // Le type de transaction est un remboursement
    $refundTransaction->type = self::REFUND;
    // Il n'y a pas de dalai, si c'est faisable, on le fait
    $refundTransaction->captureDelay = 0;
    // Récupération de l'oid du client à rembourser
    $refundTransaction->customerOid = $transactionOrigin->customerOid;
    // On regarde s'il y a des infos supplémentaires au sujet du client
    if (isset($transactionOrigin->refAbonneBoutique)) {
      $refundTransaction->refAbonneBoutique = $transactionOrigin->refAbonneBoutique;
    }
    if (isset($transactionOrigin->customerEmail)) {
      $refundTransaction->customerEmail = $transactionOrigin->customerEmail;
    }
    /* Paramètres de la boutique */
    // On mémorise dans la transaction le mode de réponse attendu par la boutique.
    $refundTransaction->autoResponseMode = $shop->autoResponseMode;
    // On mémorise dans la transaction la fonction de traitement de la reponse attendu par la boutique.
    $refundTransaction->shopCallBack = $shop->autoResponseCallBack;
    $refundTransaction->shopMoid = $shop->moid;
    $refundTransaction->shopName = $shop->name;
    $refundTransaction->shopClass = $shop->class;
    // traçabilité des commandes
    if ($order->traceId)
      $refundTransaction->traceId = $order->traceId;
    // Créer la transaction en base.
    $refundTransaction = $this->insertTransaction($refundTransaction);
    // Traiter la requête
    try {
      // Cette méthode retourne la transaction après l'appel en banque
      $refundTransaction = $this->refundHandling($refundTransaction);
      if ($refundTransaction->status == self::SUCCESS) {
        $returnValue = ['ok', $refundTransaction->oid, $refundTransaction];
      } else {
        $returnValue = ['ko', $refundTransaction->oid, $refundTransaction];
      }
    } catch (\Exception $e) {
      $refundTransaction->status = self::ERROR;
      $refundTransaction->statusComplement = 'Exception : ' . $e->getMessage();
      $returnValue = ['ko', $refundTransaction->oid, $refundTransaction];
      \Seolan\Core\Logs::critical(__METHOD__ . ' exception: ' . $e->getMessage() . ' Code ' . $e->getCode());
    } finally {
      // Mémorise les données brutes de reponse et le status
      if ($refundTransaction->autoResponseMode === self::ASYNC_RESPONSE)
        $refundTransaction->responseStatus = self::RESPONSE_STATUS_TO_SEND;
      elseif ($refundTransaction->autoResponseMode === self::RESPONSE_NONE)
        $refundTransaction->responseStatus = self::RESPONSE_STATUS_NOT_TO_SEND;
      $refundTransaction->dateTimeIn = date('Y-m-d H:i:s');
      $this->procEditTransaction($refundTransaction);
    }
    // si nécessaire notifier le retour à la boutique
    if ($refundTransaction->autoResponseMode === self::SYNC_RESPONSE) {
      $this->notifyShop($refundTransaction);
    }
    return $returnValue;
  }

  // calcul la somme des remboursement d'une transaction
  protected function getRefundedAmount($transacOid) {
    return getDB()->fetchOne('select sum(amount) from TRANSACMONETIQUE where transOri=? and status=? and type=?',
      [$transacOid, self::SUCCESS, self::REFUND]);
  }

  /**
   * \brief Fonction d'insertion d'un remboursement en base.
   * Cette fonction permet d'inserer une transaction avant l'appel en banque.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : Contient les informations relatives au remboursement. \link \Seolan\Module\Monetique\Model\Transaction \endlink
   * \return String Oid de la transaction associée à ce remboursement.  \link \Seolan\Module\Monetique\Model\Transaction::$oid \endlink
   */
  protected function insertRefund($transaction, $shop){
    $r = $this->xset->procInput( (array) $transaction);
    if (empty($r)) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' Problème d\'insertion de la transaction');
    }
    return $r['oid'];
  }

  /**
   * \brief Fonction de ré-émission des remboursements ayant le status waitting.
   * Cette fonction permet de rejouer les remboursement en attente.
   * \return Array :
   * - Int $nbTotal : Nombre total de transactions rejouée.
   * - Int $nbSucces : Nombre total de succès.
   * - Array $error : Le details des erreurs (statusComplement de chaque transaction échouée).
   * \throws:
   * - Error : \link refundHandling(& $transaction) \endlink.
   * - Impossible de trouver la transaction d'origine.
   * \note
   * - Séléctionne les transactions à rejouer.
   * - Pour chaque transaction :
   *  - Formate le montant de la transaction en centimes.
   *  - Transforme les paramètres d'appels mémorisés en XML en tableau associatif.
   *  - Appel à la fonction refundReplay spécifique au module de la transaction.
   *  - Mémorise la réponse et les paramètres de la transaction.
   *  - Si la signature est vérifié, gestion de la notification boutique.
   * \see
   * - \Seolan\Module\Monetique\Monetique::refundReplay(& $transaction);
   */
  public function refundAsyncHandling() {
    $transactionOids = getDB()->fetchCol(
      'select KOID from TRANSACMONETIQUE where status=? and type=? and monetiqueMoid=?',
      [self::WAITTING, self::REFUND, $this->_moid]
    );
    $nbTotal = count($transactionOids);
    $nbSucces = '0';
    $error = [];
    // Tant qu'il y a des transactions à rejouer
    foreach ($transactionOids as $transactionOid) {
      $transaction = $this->getTransaction($transactionOid);
      // Appel à la fonction refundReplay spécifique au module de la transaction
      $transaction = $this->refundReplay($transaction);
      // Mémorise la réponse et les paramètres de la transaction
      if ($transaction->autoResponseMode === self::ASYNC_RESPONSE)
        $transaction->responseStatus = self::RESPONSE_STATUS_TO_SEND;
      elseif ($transaction->autoResponseMode === self::RESPONSE_NONE)
        $transaction->responseStatus = self::RESPONSE_STATUS_NOT_TO_SEND;
      $this->procEditTransaction($transaction);
      if ($transaction->status == self::SUCCESS) {
        $nbSucces++;
      } else {
        $error[$transaction->oid][$transaction->status] = $transaction->statusComplement;
      }
      // si nécessaire notifier le retour à la boutique
      if ($transaction->autoResponseMode === self::SYNC_RESPONSE) {
        $this->notifyShop($transaction);
      }
    }
    return [$nbTotal, $nbSucces, $error];
  }

  /* Fonctions de débit forcé d'un abonné */

  /**
   * \brief Fonction de génération des données de prelevement.
   * Cette fonction permet de dupliquer un paiement à partir d'un paiement web ayant généré un enrollement.
   * \param \Seolan\Module\Monetique\Model\Order $order : Commande relative à la duplication, avec le montant de la duplication.
   * - $order->amount est obligatoire.
   * \param \Seolan\Module\Monetique\Model\Customer $customer : Le client qui va être débité.
   * - $customer->oid et $customer->refAbonne sont obligatoire.
   * \param \Seolan\Module\Monetique\Model\Shop $shop : Comporte les paramètres de la boutique (autoResponseCallBack, autoResponseMode, ...).
   * \return \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction après l'appel banque avec tous ses paramètres renseignés.
   * \exception:
   * - Error : \link refundHandling(& $transaction) \endlink
   * - Si $order->amount n'est pas initialisé.
   * - Si \link \Seolan\Module\Monetique\Monetique::getEnrollement($customerOid,$refAbonne)  \endlink retourne null.
   * - Impossible de trouver la transaction d'origine.
   * \note
   * - Vérifie que le montant est saisi.
   * - Récupère les informations concernant l'abonné. \link getEnrollement($customerOid,$refAbonne); \endlink
   * - Initialise les paramètres de base de la nouvelle transaction.
   * - Formate le montant passé en paramètres. \link formatOrderAmount($amount); \endlink
   * - Récupère les informations de la transaction d'origine. \link getTransaction($transactionOid) \endlink
   * - Crée la transaction en base, avant l'appel en banque.  \link insertDuplicate($transaction); \endlink
   * - L'appel à \link duplicateHandling($newTransaction); \endlink permet de laissé le traitement de la duplication au module concerné. * Cette appel renseigne le retour dans $newTransaction.
   * - Mémorise les données brutes de reponse et le status.
   * - Si la signature est vérifié, gestion de la notification boutique.
   * \see
   * - getEnrollement($customerOid,$refAbonne);
   * - formatOrderAmount($amount);
   * - getTransaction ($transactionOid);
   * - refundHandling($transaction);
   */
  public function duplicateCall(\Seolan\Module\Monetique\Model\Order $order, \Seolan\Module\Monetique\Model\Customer $customer, \Seolan\Module\Monetique\Model\Shop $shop) {
    // En mode test, seules les ip de test sont autorisées
    if ($this->testMode(true) && !$this->testMode()) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' erreur : test mode on, ip not allowed');
      throw new \Exception('duplicateCall test mode on, ip not allowed');
    }
    // Vérification que le montant est saisi
    if (empty($order->amount)) {
      throw new \Exception('duplicateCall Aucun montant de débit saisie.');
    }
    // Récupération des informations concernant l'abonné
    $customerEnrollment = $this->getEnrollement($customer->oid, $customer->refAbonne, $customer->enrollementOid);

    // Si l'enrollement est introuvable
    if (empty($customerEnrollment)) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' erreur : Customer enrollemnt introuvable.');
      throw new \Exception('duplicateCall erreur : Customer enrollemnt introuvable.');
    }
    $newTransaction = new \Seolan\Module\Monetique\Model\Transaction();
    // Affectation du type de transaction : DEBIT
    $newTransaction->type = self::DUPLICATE;
    // Mémorisation de la transaction d'origine
    $newTransaction->transOri = $customerEnrollment->transOri;
    // Mémorisation de la référence abonné
    $newTransaction->refAbonneBoutique = $customerEnrollment->refAbonne;
    // Mémorisation du cvv
    $newTransaction->cvv = $customerEnrollment->cvv;
    // Mémorisation du porteur
    $newTransaction->porteur = $customerEnrollment->porteur;
    // Mémorisation de la date de validitée de la carte
    $newTransaction->dateVal = $customerEnrollment->dateVal;
    // Affectation du montant à rembourser
    $newTransaction->amount = $order->amount;
    // Récupération de la référence
    $newTransaction->orderReference = $order->reference;
    // Récupération de l'oid de la commande
    $newTransaction->orderOid = $order->oid;
    // Si la boutique fournis le mode de réponse attendu. Sinon on utilisera le même mode que la transaction d'origine.
    if (isset($shop->autoResponseMode)) {
      $newTransaction->autoResponseMode = $shop->autoResponseMode;
    }
    // Si la boutique fournis la fonction de traitement de la reponse. Sinon on utilisera la même fonction que la transaction d'origine.
    if (isset($shop->autoResponseCallBack)) {
      $newTransaction->shopCallBack = $shop->autoResponseCallBack;
    }
    $newTransaction->shopMoid = $shop->moid;
    $newTransaction->shopName = $shop->name;
    $newTransaction->shopClass = $shop->class;
    $newTransaction->dateCreated = date('Y-m-d H:i:s');
    // traçabilité des commandes
    if ($order->traceId)
      $newTransaction->traceId = $order->traceId;

    // Récupération de la transaction d'origine
    $transactionOrigin = $this->getTransaction($customerEnrollment->transOri);
    // Si la transaction d'origine à n'a pas été trouvée, on lève une exception.
    if (empty($transactionOrigin)) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' Transaction d\'origine non trouvée ' . print_r($customerEnrollment, true));
      throw new \Exception('duplicateCall : Transaction d\'origine non trouvée.');
    }
    $newTransaction->transactionOrigin = $transactionOrigin;
    // Récupération des informations concernant le client
    if (isset($transactionOrigin->customerOid)) {
      $newTransaction->customerOid = $transactionOrigin->customerOid;
    }
    if (isset($transactionOrigin->customerEmail)) {
      $newTransaction->customerEmail = $transactionOrigin->customerEmail;
    }
    // Créer la transaction en base.
    $newTransaction = $this->insertTransaction($newTransaction);
    // Traiter la requête
    try {
      // Cette méthode retourne la transaction après l'appel banque.
      $newTransaction = $this->duplicateHandling($newTransaction);
    } catch (\Exception $e) {
      $newTransaction->status = self::ERROR;
      $newTransaction->statusComplement = '\Exception : ' . $e->getMessage();
      \Seolan\Core\Logs::critical(__METHOD__ . ' exception: ' . $e->getMessage() . ', Code' . $e->getCode());
    } finally {
      $newTransaction->dateTimeIn = date('Y-m-d H:i:s');
      $this->procEditTransaction($newTransaction);
    }
    // si nécessaire notifier le retour à la boutique
    if ($newTransaction->autoResponseMode === self::SYNC_RESPONSE) {
      $this->notifyShop($newTransaction);
    }

    $this->removeOtherTransactionWithEqualsRef($newTransaction);

    return $newTransaction;
  }

  /**
   * \brief Fonction de ré-émission des duplications ayant le status waitting.
   * Cette fonction permet de rejouer les duplications en attente.
   * \return Array :
   * - Int $nbTotal : Nombre total de transactions rejouée.
   * - Int $nbSucces : Nombre total de succès.
   * - Array $error : Le details des erreurs (statusComplement de chaque transaction échouée).
   * \exception:
   * - Error : \link refundHandling(& $transaction) \endlink.
   * - Impossible de trouver la transaction d'origine.
   * \note
   * - Séléctionne des transaction à rejouer.
   * - Pour chaque transaction :
   *  - Formate le montant de la transaction en centimes.
   *  - Transforme les paramètres d'appels mémorisés en XML en tableau associatif.
   *  - Appel à la fonction refundReplay spécifique au module de la transaction.
   *  - Mémorise la réponse et les paramètres de la transaction.
   *  - Si la signature est vérifié, gestion de la notification boutique.
   * \see
   * - duplicateReplay(& $transaction);
   */
  public function duplicateAsyncHandling() {
    $transactionOids = getDB()->fetchCol(
      'select KOID from TRANSACMONETIQUE where status=? and type=? and monetiqueMoid=?',
      [self::WAITTING, self::REFUND, $this->_moid]
    );
    $nbTotal = count($transactionOids);
    $nbSucces = 0;
    $error = [];
    foreach ($transactionOids as $transactionOid) {
      $transaction = $this->getTransaction($transactionOid);
      // Appel à la fonction duplicateReplay spécifique au module de la transaction
      $transaction = $this->duplicateReplay($transaction);
      // On mémorise le retour
      if ($transaction->autoResponseMode === self::ASYNC_RESPONSE)
        $transaction->responseStatus = self::RESPONSE_STATUS_TO_SEND;
      elseif ($transaction->autoResponseMode === self::RESPONSE_NONE)
        $transaction->responseStatus = self::RESPONSE_STATUS_NOT_TO_SEND;
      $this->procEditTransaction($transaction);
      if ($transaction->status == self::SUCCESS) {
        $nbSucces++;
      } else {
        $error[$transaction->oid][$transaction->status] = $transaction->statusComplement;
      }
      // On vérifie s'il faut notifier la boutique
      if ($transaction->autoResponseMode === self::SYNC_RESPONSE) {
        $this->notifyShop($transaction);
      }
    }
    return [$nbTotal, $nbSucces, $error];
  }

  /* Fonctions de notifications boutique */

  /**
   * \brief Fonction de notification de la boutique.
   * Cette fonction permet de notifier la boutique au sujet du paiement d'une commande.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : Transaction à partir de laquelle la boutique sera notifiée.
   * Il faut au minimum :
   * - $transaction->shopMoid ou $transaction->shopClass. \link \Seolan\Module\Monetique\Model\Transaction::$shopMoid \endlink \link \Seolan\Module\Monetique\Model\Transaction::$shopClass \endlink
   * - $transaction->orderOid. \link \Seolan\Module\Monetique\Model\Transaction::$orderOid \endlink
   * - $transaction->shopCallBack.  \link \Seolan\Module\Monetique\Model\Transaction::$shopCallBack \endlink
   * - $transaction->oid. \link \Seolan\Module\Monetique\Model\Transaction::$oid \endlink
   * \note
   * - Vérifie que la transaction est initialisée.
   * - Si la transaction connait le moid de la boutique, cette fonction pourra la notifier.
   */
  protected function notifyShop($transaction) {
    // On vérifie que la transaction est initialisée
    if ($transaction == null) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' notif : transaction null');
    }
    // Si la transaction connait le moid de la boutique elle pourra la notifier
    if (!empty($transaction->shopMoid)) {
      \Seolan\Core\Logs::notice(__METHOD__ . ' notif module : ' . $transaction->shopMoid . ', function ' . $transaction->shopCallBack . ', order ' . $transaction->orderOid);
      $mod = \Seolan\Core\Module\Module::objectFactory(['moid' => $transaction->shopMoid, 'interactive' => 0, 'tplentry' => TZR_RETURN_DATA]);
      $f = $transaction->shopCallBack;
      $mod->$f($transaction->orderOid, $transaction);
    } elseif (!empty($transaction->shopClass)) {
      \Seolan\Core\Logs::notice(__METHOD__ . ' notif classe : ' . $transaction->shopClass . 'function ' . $transaction->shopCallBack . ', order ' . $transaction->orderOid);
      $c = new $transaction->shopClass();
      $f = $transaction->shopCallBack;
      $c->$f($transaction->orderOid, $transaction);
    }
    // Mise à jour du status de reponse à la boutique
    $this->xset->procEdit([
      'oid' => $transaction->oid,
      'responseStatus' => self::RESPONSE_STATUS_SENT,
      '_options' => ['local' => true]
    ]);
  }

  /**
   * \brief Fonction de notification en asynchone des retours de paiement.
   * Cette fonction permet de notifier la boutique de manière asynchrone au sujet du paiement d'une commande.
   * \note
   * - Séléctionne toute les transaction ayant un status différent de ( \link \Seolan\Module\Monetique\Monetique::ERROR \endlink ou  \link \Seolan\Module\Monetique\Monetique::INVALID \endlink) et un autoResponseMode =  \link \Seolan\Module\Monetique\Monetique::ASYNC_RESPONSE \endlink et un responseStatus =  \link \Seolan\Module\Monetique\Monetique::RESPONSE_STATUS_TO_SEND \endlink .
   * - Pour chaque transaction :
   *  - Appel à \link notifyShop($transaction) \endlink
   */
  public function notifyShopAsync() {
    $rs = getDB()->select('select KOID from TRANSACMONETIQUE where (status != "' . self::ERROR . '" or status != "' . self::INVALID . '" ) and autoResponseMode="' . self::ASYNC_RESPONSE . '" and responseStatus="' . self::RESPONSE_STATUS_TO_SEND . '"');
    while ($ors = $rs->fetch()) {
      $transaction = $this->getTransaction($ors['KOID']);
      $transaction->oid = $ors['KOID'];
      $this->notifyShop($transaction);
    }
  }

  /* Fonctions utilitaires */

  /**
   * \brief Fonction de controle des paramètres attendus pour le paiement d'une commande.
   * Cette fonction permet de controler :
   * - $order->oid : L'oid de la commande correspondant à la transaction. \link \Seolan\Module\Monetique\Model\Order::oid \endlink
   * - $order->amount : Le montant de la commande. \link \Seolan\Module\Monetique\Model\Order::amount \endlink
   * - $shop->moid ou $shop->class : La boutique à l'origine de la commande. \link \Seolan\Module\Monetique\Model\Shop::moid \endlink \link \Seolan\Module\Monetique\Model\Shop::class  \endlink
   * - $order->reference : La référence de la commande. \link \Seolan\Module\Monetique\Model\Order::reference \endlink
   * - $shop->autoResponseMode : Le mode de reponse doit être cohérent avec les paramètres attendus. \link \Seolan\Module\Monetique\Model\Shop::autoResponseMode \endlink
   * \exception:
   * - Si l'un de tous ces champs n'est pas correctement initialisé.
   */
  protected function checkPaymentData(\Seolan\Module\Monetique\Model\Order $order, \Seolan\Module\Monetique\Model\Customer $customer, \Seolan\Module\Monetique\Model\Shop $shop) {
    if (empty($order->oid)) {
      throw new \Exception('order oid is not set');
    }
    if (!isset($order->amount)) {
      throw new \Exception('order amount is not set');
    }
    if (empty($shop->moid) && empty($shop->class)) {
      throw new \Exception('must provide shopMoid or shop class');
    }
    if (empty($order->reference)) {
      throw new \Exception('must provide order reference');
    }
    if (!in_array($shop->autoResponseMode, [self::RESPONSE_NONE, self::ASYNC_RESPONSE, self::SYNC_RESPONSE])) {
      throw new \Exception('must provide options auto response (none|async|live)');
    }
    if ($order->options['enrollement'] && !$this->oneClick) {
      throw new \Exception('oneClick required but not enabled', 06);
    }
  }

  /**
   * \brief Fonction de chargement d'une transaction depuis la table grace à son KOID.
   * Cette fonction permet de récupérer tous les paramètres d'une transaction depuis la table.
   * @param String $transactionOid : L'identifiant d'une transaction. \link \Seolan\Module\Monetique\Model\MTransaction::$oid \endlink
   * @return \Seolan\Module\Monetique\Model\Transaction
   */
  protected function getTransaction($transactionOid) {
    $transaction = getDB()->select('select * from TRANSACMONETIQUE where KOID=?', [$transactionOid])->fetch(\PDO::FETCH_OBJ);
    if (!$transaction) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' Impossible de trouver la transaction ayant pour KOID : ' . $transactionOid);
      return null;
    }
    $transaction->oid = $transactionOid;
    $transaction->_callParms = \Seolan\Core\System::xml2array($transaction->callParms);
    $transaction->_responseParms = \Seolan\Core\System::xml2array($transaction->responseParms);
    $transaction->_customerInfos = \Seolan\Core\System::xml2array($transaction->customerInfos);
    return $transaction;
  }

  /**
   * \brief Fonction de recherche de le KOID d'une transaction depuis la table grace à la référence commande.
   * Cette fonction permet de récupérer le KOID d'une transaction depuis la table.
   * \param String $orderReference : La référence d'une commande. \link \Seolan\Module\Monetique\Model\Transaction::$orderReference \endlink
   * \param String $status : l'état à prendre en compte
   * \return String $transactionOid : \link \Seolan\Module\Monetique\Model\Transaction::$oid \endlink
   * \see
   * \Seolan\Module\Monetique\Model\Transaction.
   */
  protected function getIdTransactionWithOrderRef($orderReference, $status) {
    $transactionOid = getDB()->fetchOne(
      'select KOID from TRANSACMONETIQUE where orderReference=? and status=? order by UPD desc',
      [$orderReference, $status]);

    if (!$transactionOid) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' Transaction de la commande ' . $orderReference . ' non trouvé!');
    }
    return $transactionOid;
  }

  /**
   * \brief Fonction de recherche du type de capture d'une transaction.
   * Cette fonction permet de récupérer le captureMode d'une transaction depuis la table du module.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction pour laquelle on veut connaitre le captureMode. \link \Seolan\Module\Monetique\Model\Transaction::$captureMode \endlink
   * \return String $captureMode.
   * \see
   * \Seolan\Module\Monetique\Model\Transaction.
   */
  protected function getCaptureMode($transaction) {
    $rs = getDB()->select('select captureMode from TRANSACMONETIQUE where `KOID`="' . $transaction->oid . '"');
    $res = null;
    if ($rs->rowCount() == 1) {
      $res = $rs->fetch(\PDO::FETCH_COLUMN);
    } else {
      \Seolan\Core\Logs::critical(__METHOD__ . ' Transaction ayant pour KOID ' . $transaction->oid . ' non trouvé!');
    }
    return $res;
  }

  /**
   * \brief Fonction de recherche d'un enrollement dans la table ENROMONETIQUE.
   * Cette fonction permet de récupérer tous les paramètres d'un enrollement.
   * \param String $customerOid : L'identifiant d'un client. \link \Seolan\Module\Monetique\Model\Customer::$oid \endlink
   * \param String $refAbonne : La référence abonné d'un client. \link \Seolan\Module\Monetique\Model\Customer::$refAbonne \endlink
   * \return $customerEnrollement
   */
  protected function getEnrollement($customerOid, $refAbonne, $enrollementOid = null) {
    if ($enrollementOid) {
      $enrollement = getDB()->select('select * from ENROMONETIQUE where KOID=?', [$enrollementOid])->fetch(\PDO::FETCH_OBJ);
    } elseif ($customerOid && $refAbonne) {
      $enrollement = getDB()->select(
          'select * from ENROMONETIQUE where customerOid=? and refAbonne=? order by CREAD desc limit 1',
          [$customerOid, $refAbonne]
        )->fetch(\PDO::FETCH_OBJ);
    }
    if (!$enrollement) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' Impossible de trouver l\'enrollement ayant pour customerOid :' . $customerOid);
    }
    return $enrollement;
  }

  /// Retourne la liste des enrollements [actif] d'un customer
  public static function getEnrollements($customerOid, $all = false) {
    if (empty($customerOid))
      return null;
    $_enrollements = getDB()->fetchAll('select * from ENROMONETIQUE where dateVal is not null and customerOid=?', [$customerOid]);
    if (!$_enrollements)
      return null;
    $enrollements = [];
    $curYear = date('y');
    $curMonth = date('m');
    foreach ($_enrollements as $enrollement) {
      $monthVal = substr($enrollement['dateVal'], 0, 2);
      $yearVal = substr($enrollement['dateVal'], 2, 2);
      if ($all || $yearVal > $curYear || ($yearVal == $curYear && $monthVal >= $curMonth))
        $enrollements[$enrollement['KOID']] = $enrollement;
    }
    return $enrollements;
  }

  /**
   * \brief Fonction de génération d'une référence unique pour la journée.
   * Cette fonction utilise \b checkPidFiles() pour assurer un accès unique à la ressource.
   * \param String $format : Le format attendu de la référence. Ex : '$myVar%Y%m%d%-%H%M%S' ou simplement 'Y%m%d%-%H%M%S'.
   * \param String $myVar : Ce que l'on veut concatener à la référence.
   * \return String  : La référence généré.
   */
  public static function genOrderRef($format, $vars = null) {
    $lock = \Seolan\Library\Lock::getLock('monetiqueOrderRef', 1, 1, 0, true);
    try {
      $chrono = \Seolan\Core\DbIni::getStatic('\Seolan\Module\Monetique\Monetique::orderRef', 'val');
      $hour = date('YmdH');
      if (!$chrono || $chrono['hour'] != $hour) {
        // check in base
        $last = getDB()->fetchOne(
          'select ifnull(max(substring(orderReference, -4)), 0) from TRANSACMONETIQUE where dateCreated like ?',
          [date('Y-m-d H:%')]);
        $chrono = ['value' => $last + 1, 'hour' => $hour];
      } else {
        $chrono['value'] ++;
      }
      \Seolan\Core\DbIni::setStatic('\Seolan\Module\Monetique\Monetique::orderRef', $chrono);
    } catch (\Exception $e) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' exception ' . $e->getMessage());
    } finally {
      \Seolan\Library\Lock::releaseLock($lock);
    }
    $refbase = strftime($format);
    if (!empty($vars)) {
      $refbase = str_replace($vars[0], $vars[1], $refbase);
    }
    return $refbase . sprintf('%04d', $chrono['value']);
  }

  /**
   * \brief Fonction de génération d'un identifiant de transaction unique pour la journée.
   * \return String $newTransId : identifiant unique d'une transaction (6 charactères).
   */
  protected function genTransId() {
    if (!$this->needTransId) {
      return '';
    }
    $lock = \Seolan\Library\Lock::getLock('monetiqueTransId', 1, 1, 0, true);
    try {
      $transId = \Seolan\Core\DbIni::getStatic('\Seolan\Module\Monetique\Monetique::transId', 'val');
      $today = date('Ymd');
      if (!$transId || $transId['date'] != $today) {
        // check in base
        $last = getDB()->fetchOne(
          'select ifnull(max(transId), 0) from TRANSACMONETIQUE where dateCreated=?', [date('Y-m-d%')]);
        $transId = ['value' => $last + 1, 'date' => $today];
      } else {
        $transId['value'] ++;
      }
      \Seolan\Core\DbIni::setStatic('\Seolan\Module\Monetique\Monetique::transId', $transId);
    } catch (\Exception $e) {
      \Seolan\Core\Logs::critical(__METHOD__ . ' exception ' . $e->getMessage());
    } finally {
      \Seolan\Library\Lock::releaseLock($lock);
    }
    if (empty($this->transIdMask)) {
      $this->transIdMask = '%06d';
    }
    return sprintf($this->transIdMask, $transId['value']);
  }

  /**
   * \brief Fonction retourne un motant en euros passé en paramètre, en centimes.
   * \param Float $amount : Un montant en euros.
   * \return String $amount*100 : Le montant en centimes.
   */
  protected function formatOrderAmount($amount) {
    return (string) ($amount * 100);
  }

  /**
   * \brief Fonction vérifie les montants
   * \return Bool $test : Renvoi True si le montant est le même, False sinon.
   */
  protected function checkResponseAmount($transactionResponse, $transactionOrigin) {
    $shop = self::objectFactory($transactionOrigin->shopMoid);
    if (method_exists($shop, 'getOrderAmount')) {
      $transactionResponse->checkAmount = $shop->getOrderAmount($transactionOrigin->orderOid);
    } else {
      $transactionResponse->checkAmount = $transactionOrigin->amount;
    }
    return ($transactionResponse->amount == $transactionResponse->checkAmount);
  }

  /**
   * \brief Fonction retourne un motant en euros passé en paramètre, en centimes.
   * \param String $amount: Un montant en centimes.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction: Une transaction dont $transaction->amount est en euros.
   * \return Bool $test : Renvoi True si le montant est le même, False sinon.
   */
  protected function checkNbDeadLine($nbDeadLineResponse, $nbDeadLineOri) {
    return ($nbDeadLineResponse == $nbDeadLineOri);
  }
  function al_browse(&$my){

    parent::al_browse($my);

    if($this->secure('','testPaymentCall')){
      $o1=new \Seolan\Core\Module\Action($this, 'testPaymentCall', 'Tests appel banque',
        $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true,true).'moid='.$this->_moid.'&function=testPaymentCall&template=Module/Monetique.testPaymentCall.html','more');
      $o1->menuable=true;
      $my['testPaymentCall']=$o1;
    }
  }
  /**
   * vérif de l'appel bq en BO ...
   */
  public function testPaymentCall($ar=null) {
    $order = new \Seolan\Module\Monetique\Model\Order();
    $order->oid = 'TESTS:ORDER'.uniqid();
    $order->reference = 'Ref'.date('Ymdhis').rand(0,1000);
    $order->amount = 10.0;
    $order->traceId = rand(0,2000);
    $customer = new \Seolan\Module\Monetique\Model\Customer();
    $customer->oid = 'TESTS:CUSTOMER'.uniqid();
    $customer->email = 'foo@xsalto.com';
    $shop = new \Seolan\Module\Monetique\Model\Shop();
    $shop->moid = $this->_moid;
    $shop->name ='Tests, moid faux';
    $shop->autoResponseMode = 'async';
    $shop->autoResponseCallBack = 'responseCallBackTests';
    try{
      $r = $this->paymentCall($order, $customer, $shop);
    } catch(\Exception $e){
      $r = ['ko:'.$e->getMessage()];
    }
    $ret = ['ok'=>null, 'data'=>null, 'template'=>null, 'tplentry'=>null, 'transoid'=>null];
    list($ret['ok'], $ret['data'], $ret['template'], $ret['tplentry'], $ret['transoid']) = $r;
    if ($ret['ok']=='ok'){
      \Seolan\Core\Shell::toScreen1($ret['tplentry'], $ret['data']);
      \Seolan\Core\Shell::toSCreen1('tpc', $ret);
    } else {
      var_dump($r);
    }
  }

  public function secGroups($function, $group = NULL) {
    $g = [];
    $g['cronSendAdvertEnrollementExpiration'] = ['ro', 'rw','rwv', 'admin'];
    $g['testPaymentCall'] = ['admin'];
    $g['autoresponse'] = ['none'];
    if (isset($g[$function])) {
      return $g[$function];
    }
    if (isset($g[$function])) {
      if (!empty($group)) {
        return in_array($group, $g[$function]);
      }
      return $g[$function];
    }
    return parent::secGroups($function, $group);
  }

  /**
   * \brief Fonction qui retourne le libellé d'une erreur à partir de son code.
   * \param Int $errorCode : Le code erreur retourné par la banque pour une transaction.
   * \return String $errorLabel : Le libellé de l'erreur ou  'Code erreur non documenté'
   */
  protected function getErrorCode($errorCode) {
    $errorTab = [
      '00' => 'Paiement autorisé.',
      '00000' => 'Paiement autorisé.',
      '02' => 'Contacter l’émetteur de carte.',
      '03' => 'Accepteur invalide.',
      '04' => 'Conserver la carte.',
      '05' => 'Ne pas honorer.',
      '07' => 'Conserver la carte, conditions spéciales.',
      '08' => 'Approuver après identification.',
      '12' => 'Transaction invalide.',
      '13' => 'Montant invalide.',
      '14' => 'Numéro de porteur invalide.',
      '17' => 'Annulation du client.',
      '24' => 'Opération impossible. L\'opération que vous souhaitez réaliser n\'est pas compatible avec l\'état de la transaction.',
      '30' => 'Error de format.',
      '31' => 'Identifiant de l\'organisme acquéreur inconnu.',
      '33' => 'Date de validité de la carte dépassée.',
      '34' => 'Suspicion de fraude.',
      '41' => 'Carte perdue.',
      '43' => 'Carte volée.',
      '51' => 'Provision insuffisante ou crédit dépassé.',
      '54' => 'Date de validité de la carte dépassée.',
      '56' => 'Carte absente du fichier.',
      '57' => 'Transaction non permise à ce porteur.',
      '58' => 'Transaction interdite au terminal.',
      '59' => 'Suspicion de fraude.',
      '60' => 'L\'accepteur de carte doit contacter l’acquéreur.',
      '61' => 'Montant de retrait hors limite.',
      '63' => 'Règles de sécurité non respectées.',
      '68' => 'Réponse non parvenue ou reçue trop tard.',
      '75' => 'Nombre de tentatives de saisie du numéro de carte dépassé.',
      '90' => 'Arret momentané du système.',
      '91' => 'Emetteur de cartes inaccessible.',
      '94' => 'Transaction dupliquée.',
      '96' => 'Mauvais fonctionnement du système.',
      '97' => 'Echéance de la temporisation de surveillance globale.',
      '98' => 'Serveur indisponible routage réseau demandé à nouveau.',
      '99' => 'Incident domaine initiateur.'];
    // Si le code erreur existe dans le tableau
    if (isset($errorTab[$errorCode])) {
      return $errorTab[$errorCode];
    }
    // Si le ccode erreur n'est pas répertorié.
    else {
      return 'Code erreur non documenté';
    }
  }

  /**
   * \brief Fonction d'initialisation des options communes à tout les systèmes de paiement
   * (permet d'ajouter dans champs dans les propriétés du module).
   * Permet d'initialiser les options communes aux trois modules (Paybox, SystemPay et Atos).
   * \note
   * Crée les champs:
   * - siteId \link \Seolan\Module\Monetique\Monetique::$siteId \endlink
   * - urlPayed \link \Seolan\Module\Monetique\Monetique::$urlPayed \endlink
   * - urlCancelled \link \Seolan\Module\Monetique\Monetique::$urlCancelled \endlink
   * - urlAutoResponse \link \Seolan\Module\Monetique\Monetique::$urlAutoResponse \endlink
   */
  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Monetique','modulename');
    $this->_options->setOpt('Devise', 'defaultCurrency', 'text', ['size' => 3], 'EUR', $alabel);
    if ($this->needTransId) {
      $this->_options->setOpt('Masque ID transaction générée', 'transIdMask', 'text', NULL, '%06d', $alabel);
      $this->_options->setComment('Masque sprintf pour la génération de l\'Id transaction, 6 caractères numériques', 'transIdMask');
    }
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Monetique', 'siteid'), 'siteId', 'text', NULL, NULL, $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Monetique', 'urlPayed'), 'urlPayed', 'text', ['size' => 60], NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Monetique', 'urlCancelled'), 'urlCancelled', 'text',
      ['size' => 60], NULL, $alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Monetique_Monetique', 'urlAutoResponse'), 'urlAutoResponse', 'text',
      ['size' => 60], NULL, $alabel);
    if ($this->oneClickPossible) {
      $this->_options->setOpt('Gestion des enrollements', 'oneClick', 'boolean', [], false, $alabel);
      $this->_options->setComment('Paiement One Click / récurrents', 'oneClick', 'boolean', [], NULL, $alabel);
    }
  }

  /**
   * \brief Méthode de vérification de la présence du fichier de retour automatique.
   * Permet d'afficher une erreur dans les propriètés du module si le fichier de retour automatique n'est pas présent.
   */
  public function editProperties($ar) {
    parent::editProperties($ar);
    $messages = $matches = [];
    if (!preg_match('@^(?:https?://)?(?:[^/]+)?/([^?]*)(\?[^?]*)?$@', $this->urlAutoResponse, $matches))
      $messages[] = 'Url de retour automatique incorrecte : ' . $this->urlAutoResponse;
    // Si le fichier n'exite pas, affiche l'erreur
    elseif (!file_exists(TZR_WWW_DIR . $matches[1])) {
      $messages[] = 'Fichier de retour automatique non présent à l\'adresse : ' . TZR_WWW_DIR . $matches[1];
    }
    if ($this->needTransId) {
      $testTransId = sprintf($this->transIdMask, 1);
      if (strlen($testTransId) != 6 || !is_numeric($testTransId)) {
        $messages[] = 'Masque ID transaction incorrect';
      }
    }
    if (!empty($messages)) {
       setSessionVar('message', implode('<br>', $messages));
    }
  }

  /**
   * \brief Fonction abstraite de preparation des paramètres d'appel en banque spécifique à chaques module.
   * \param \Seolan\Module\Monetique\Model\Order $order : La commande à l'origine de la transaction.
   * \param \Seolan\Module\Monetique\Model\Customer $customer : Le client à l'origine de la commande.
   * \param \Seolan\Module\Monetique\Model\Shop $shop : La boutique demandant la création de la transaction.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction en cours de préparation.
   * @return Array :
   * - \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction avec ses paramètres.
   * - Array $payboxForm : Le formulaire à soumettre à la banque.
   * - String $template : Le template à utilisé pour afficher le formulaire avant l'envoi en banque.
   * - String $tplEntry : L'entrée smarty du template.
   * \see
   * - \Seolan\Module\Monetique\Paybox\Paybox::webPaymentHandling(\Seolan\Module\Monetique\Model\Order $order, \Seolan\Module\Monetique\Model\Customer $customer, \Seolan\Module\Monetique\Model\Shop $shop, $transaction);
   * - \Seolan\Module\Monetique\SystemPay\SystemPay::webPaymentHandling(\Seolan\Module\Monetique\Model\Order $order, \Seolan\Module\Monetique\Model\Customer $customer, \Seolan\Module\Monetique\Model\Shop $shop, $transaction);
   * - \Seolan\Module\Monetique\Atos\Atos::webPaymentHandling(\Seolan\Module\Monetique\Model\Order $order, \Seolan\Module\Monetique\Model\Customer $customer, \Seolan\Module\Monetique\Model\Shop $shop, $transaction);
   */
  abstract protected function webPaymentHandling(\Seolan\Module\Monetique\Model\Transaction $transaction);

  /**
   * \brief Fonction abstraite de traitement automatique d'un retour banque spécifique à chaques module.
   * @return
   * \Seolan\Module\Monetique\Model\Transaction $transaction : La transaction avec ses paramètres concernée par le retour.
   * \see
   * - \Seolan\Module\Monetique\Paybox\Paybox::webPaymentUnFoldReponse();
   * - \Seolan\Module\Monetique\SystemPay\SystemPay::webPaymentUnFoldReponse();
   * - \Seolan\Module\Monetique\Atos\Atos::webPaymentUnFoldReponse();
   */
  abstract protected function webPaymentUnFoldReponse();

  /**
   * \brief Fonction abstraite de création d'un remboursement.
   * Cette fonction permet à chaque module héritant de cette classe, de créer un remboursement avec les paramètres attendus par la banque.
   * \param \Seolan\Module\Monetique\Model\Transaction &$newTransaction : Transaction relative au remboursement.
   * \return \Seolan\Module\Monetique\Model\Transaction $newTransaction : La transaction passé en paramètre, contenant les paramètres d'appel et la réponse.
   * \see
   * - \Seolan\Module\Monetique\Paybox\Paybox::refundHandling(& $transaction);
   * - \Seolan\Module\Monetique\Atos\Atos::refundHandling(& $transaction);
   * - \Seolan\Module\Monetique\SystemPay\SystemPay::refundHandling(& $transaction);
   */
  abstract protected function refundHandling($newTransaction);

  /**
   * \brief Fonction de réémission d'un remboursement.
   * Cette fonction permet à chaque module héritant de cette classe, de rejouer un remboursement avec les même paramètres.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : Transaction relative au remboursement.
   * \return \Seolan\Module\Monetique\Model\Transaction $newTransaction : La transaction contenant les paramètres d'appel et la réponse.
   * \see
   * - \Seolan\Module\Monetique\Paybox\Paybox::refundReplay(& $transaction);
   * - \Seolan\Module\Monetique\Atos\Atos::refundReplay(& $transaction);
   * - \Seolan\Module\Monetique\SystemPay\SystemPay::refundReplay(& $transaction);
   */
  protected function refundReplay($transaction) {
    return $this->refundHandling($transaction);
  }

  /**
   * \brief Fonction abstraite de création d'une duplication.
   * Cette fonction permet à chaque module héritant de cette classe, de créer une duplication avec les paramètres attendus par la banque.
   * \param \Seolan\Module\Monetique\Model\Transaction $newTransaction : Transaction relative à la duplication.
   * \return \Seolan\Module\Monetique\Model\Transaction : La transaction passé en paramètre mis à jour.
   */
  abstract protected function duplicateHandling($newTransaction);

  /**
   * \brief Fonction de réémission d'une duplication.
   * Cette fonction permet à chaque module héritant de cette classe, de rejouer une duplication avec les même paramètres.
   * \param \Seolan\Module\Monetique\Model\Transaction $transaction : Transaction relative à la duplication.
   * \return \Seolan\Module\Monetique\Model\Transaction : La transaction passé en paramètre mis à jour.
   */
  protected function duplicateReplay($transaction) {
    return $this->duplicateHandling($transaction);
  }

  /* \brief Fonction qui génère une liste d'enrollement arivant à expiration le mois passé en paramètre .
   * \param String $date au format mmyy
   * \return Array $customerEnrollemntToExpire : Le tableau des enrollements arrivants à expiration.
   */
  public function listEnrollementToExpire($date) {
    return getDB()->select('select * from ENROMONETIQUE where dateVal=?', [$date])->fetchAll();
  }
  /**
   * \brief Un "enrollement est-il utilisable
   * \param Array $row la ligne avec au moins dateVal
   */
  public static function enrollementActif($row, $when=null){
    if (!isset($row['dateVal'])){
      return false;
    }
    if ($when == null){
      $when = date('ym');
    }
    return substr($row['dateVal'], -2).substr($row['dateVal'], 0, 2)>=$when;
  }
  /**
   * \brief : sélection des enrollement liés au module (via transori) expirés et notificaiton du module paramétré
   * dans la tâche
   * \note : paramètres pour la tache :
   * <tr><th>customer_moid</th><td>54</td></tr><tr><th>customer_method</th><td>enrollmentExpiration</td></tr>
   * et la planif mensuelle :
   * <?xml version="1.0" encoding="utf-8"?>
   * <tzrdata v="1">
   * <table>
   * <tr><th>period</th><td>weekly</td></tr><tr><th>day</th><td>month</td></tr><tr><th>time</th><td>08:00:00</td></tr>
   * </table></tzrdata>
   */
  function cronSendAdvertEnrollementExpiration(\Seolan\Module\Scheduler\Scheduler $scheduler=null, $o, $more){

    if (isset($more->customer_moid) && isset($more->customer_method)){
      $mod = \Seolan\Core\Module\Module::objectFactory(['tplentry'=>TZR_RETURN_DATA,
							'moid'=>$more->customer_moid,
							'interactive'=>false]);
      if (!is_object($mod) || !method_exists($mod,$more->customer_method)){
	$mod = false;
      } else {
	$method = $more->customer_method;
      }
    }

    $customers =  getDB()->select('select e.*, t.customerEmail from ENROMONETIQUE e, TRANSACMONETIQUE t where e.transOri = t.koid and dateVal = ? and t.monetiqueMoid=?', [date('my'), $this->_moid])->fetchAll();

    $cr = '';
    $nb = 0;
    foreach ($customers as $customer) {
      $nb++;
      if ($mod){
	$mod->$method($this->_moid, $customer);
      }
    }
    $cr = "\nNombre d'enrollement(s) à exipiration au ".date('Ym')." : $nb";
    if (!$mod){
      $cr = "\nPas de module destinataire parametré\n".$cr;
    }
    return $cr;
  }
}
