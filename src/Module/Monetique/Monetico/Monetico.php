<?php
namespace Seolan\Module\Monetique\Monetico;

/**
 * Classe Monetico.
 * Classe de gestion des transactions Monetico.
 * ici siteID = N° TPE
 * on ne gère que des euros
 * les modes de paiement (immediat, différé, partiel ...) sont exclusifs et dependant du TPE
 * l'url de l'IPN (monetique-retour-auto) est à configurer dans le BO Monético
 * TODO: test mode de paiement != immediat
 * Remboursements:
 *     Avant de pouvoir effectuer des requêtes de remboursement dans l'environnement de production,
 *     il vous faudra communiquer par courriel à l'assistance technique la liste des adresses IP à autoriser,
 *     ainsi que le nombre de remboursement quotidiens maximum pour chacune d’entre elles.
 */

class Monetico extends \Seolan\Module\Monetique\Monetique {

  const PAIEMENT_VERSION = '3.0';
  const RECREDIT_VERSION = '3.0';

  // Options spécifiques au module
  public $defaultTemplate = 'Module/Monetique.monetico.html';
  public $companyCode = NULL;
  public $hashKey;
  public $urlCancel;
  public $orderReferenceSize = 12;
  public $_additionalFields;
  private $additionalFields;

  function __construct($ar = NULL) {
    parent::__construct($ar);
    if (in_array(\Seolan\Core\Shell::_function(), ['editProperties', 'procEditProperties', 'newModule'])) {
      return;
    }
    if (substr($this->urlCancel, 0, 1) == '/') {
      $this->urlCancel = \Seolan\Core\Session::makeDomainName() . $this->urlCancel;
    }
    preg_match_all('/\s*(\S*)\s*=>\s*(\S*)\s*/', $this->_additionalFields, $matches);
    $this->additionalFields = array_combine($matches[1], $matches[2]);
  }

  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Code société', 'companyCode', 'text', [], '', 'Monetico');
    $this->_options->setOpt('Clé', 'hashKey', 'text', ['size' => 60], '', 'Monetico');
    $this->_options->setOpt('Taille de la référence de commande', 'orderReferenceSize', 'text', ['size' => 2], '12', 'Monetico');
    $this->_options->setOpt('Url de retour annulation client', 'urlCancel', 'text', ['size' => 60], '', 'Monetico');
    $this->_options->setOpt('Champs supplémentaires', '_additionalFields', 'text', ['cols' => 40, 'rows' => 4], '', 'Monetico');
    $this->_options->setComment("à insérer dans le formulaire de paiement, format :<br>name => value", '_additionalFields');
    $this->_options->setComment("l'url de l'IPN (monetique-retour-auto) est à configurer dans le BO de Monético", 'urlAutoResponse');
  }

  private function getPaymentUrl() {
    if ($this->testMode()) {
      return 'https://p.monetico-services.com/test/paiement.cgi';
    }
    return 'https://p.monetico-services.com/paiement.cgi';
  }

  private function getRecreditUrl() {
    if ($this->testMode()) {
      return 'https://p.monetico-services.com/test/recredit_paiement.cgi';
    }
    return 'https://p.monetico-services.com/recredit_paiement.cgi';
  }

  /**
   * Méthode de génération des données de paiement.
   */
  protected function webPaymentHandling(\Seolan\Module\Monetique\Model\Transaction $transaction) {

    $callParms = [
      'TPE' => $this->siteId,
      'date' => date('d/m/Y:H:i:s', strtotime($transaction->orderDate)),
      'montant' => $this->formatAmount($transaction->amount),
      'reference' => substr($transaction->orderReference, -($this->orderReferenceSize)),
      'texte-libre' => $transaction->oid . '/' . $transaction->orderOid . '/' . $transaction->orderReference,
      'version' => static::PAIEMENT_VERSION,
      'lgue' => $this->getLang($transaction->lang),
      'societe' => $this->companyCode,
      'mail' => $transaction->customerEmail,
      'url_retour' => empty($this->urlCancel) ? $this->urlCancelled : $this->urlCancel,
      'url_retour_ok' => $this->urlPayed,
      'url_retour_err' => $this->urlCancelled,
    ];
    // paiement fractionné
    if ($transaction->nbDeadLine > 1) {
      if ($transaction->nbDeadLine > 5) {
        throw new \Exception(__METHOD__ . 'trop d\'échéances, monetico max = 5');
      }
      $reste = sprintf('%.02d', (((string) ($transaction->amount * 100)) % $transaction->nbDeadLine) / 100);
      $montant = explode('.', 100 * $transaction->amount / $transaction->nbDeadLine)[0] / 100;
      $montant1 = $montant + $reste;
      $callParms['nbrech'] = $transaction->nbDeadLine;
      $timeStamp = strtotime('+ ' . $transaction->captureDelay . ' days');
      $callParms['dateech1'] = date('d/m/Y', $timeStamp);
      $callParms['montantech1'] = $this->formatAmount($montant1);
      for ($i = 2; $i < $transaction->nbDeadLine; $i++) {
        $timeStamp = strtotime('next month', $timeStamp);
        $callParms['dateech' . $i] = date('d/m/Y', $timeStamp);
        $callParms['montantech' . $i] = $this->formatAmount($montant);
      }
    }
    $options = '';
    // Si la commande nécessite l'abonnement du client
    if (isset($transaction->refAbonneBoutique) && $transaction->enrollement == true) {
      $options .= 'aliascb=' . $transaction->refAbonneBoutique . '&forcesaisiecb=1';
    }
    // 3D Secure
    if (isset($transaction->threeDS) && $transaction->threeDS == false) {
      $options .= '3dsdebrayable=1';
    }
    if (!empty($options)) {
      $callParms['options'] = $options;
    }
    foreach ($this->additionalFields as $name => $value) {
      $callParms[$name] = $value;
    }
    $callParms['MAC'] = $this->getMacPayment($callParms);

    $monetico = [
      'url' => $this->getPaymentUrl(),
      'forms' => [] // un seul pour l'instant, pas trouvé le choix des cartes
    ];
    // Création du formulaire à envoyer en banque
    $fields = '';
    foreach ($callParms as $key => $value) {
      $fields .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
    }
    $monetico['forms']['CB'] = $fields;
    $transaction->callParms = $callParms;

    // Retourne la transaction en cours, le formulaire envoyé en banque ainsi que le template et son entrée
    return [$transaction, $monetico, TZR_SHARE_DIR . $this->defaultTemplate, 'monetico'];
  }

  /**
   * Méthode de traitement du retour banque.
   */
  protected function webPaymentUnFoldReponse() {
    $return = $_REQUEST;
    $transaction = $this->getReturnTransaction($return);
    $mac = $this->getMacReturn($return);

    if ($mac != strtolower($return['MAC']))  {
      $transaction->status = self::INVALID;
      $transaction->statusComplement = 'Erreur à la vérification de la signature, risque de fraude : ' . $mac;
      return $transaction;
    }
    list($codeRetour, $numEcheance) = preg_split('/(\[|\])/', $return['code-retour'], 0, PREG_SPLIT_NO_EMPTY);
    switch ($codeRetour) {
      case 'payetest':
        if (!$this->testMode(true)) { // ip banque => true
          $transaction->responseCode = '-1';
          $transaction->status = self::ERROR;
          $transaction->statusComplement = 'Paiement test, testMode désactivé';
        } else {
          $transaction->responseCode = '00';
          $transaction->status = self::SUCCESS;
          $transaction->statusComplement = 'Transaction en mode Test';
        }
        break;
      case 'Annulation':
        $transaction->responseCode = '-2';
        $transaction->status = self::ERROR;
        $transaction->statusComplement = $return['motifrefus'];
        if ($return['filtragecause']) {
          $transaction->statusComplement .= ' / filtrage (' . $return['filtragevaleur'] . ')' . $this->filtrageCause[$return['filtragecause']];
        }
        break;
      case 'paiement':
        $transaction->status = self::SUCCESS;
        $transaction->responseCode = '00';
        break;
      case 'Annulation_pf':
        $transaction->status = self::ERROR;
        $transaction->responseCode = '-3';
        $transaction->statusComplement = "Retour paiement échéange $numEcheance";
        break;
      case 'paiement_pf':
        $transaction->status = self::SUCCESS;
        $transaction->responseCode = '00';
        $transaction->statusComplement = "Retour paiement échéange $numEcheance";
        break;
      default:
        $transaction->status = self::ERROR;
        $transaction->responseCode = '-4';
        $transaction->statusComplement = "Code retour inconnu $codeRetour";
    }
    return $transaction;
  }

  /**
   * Lecture des données de l'autoresponse
   */
  private function getReturnTransaction($fields) {
    $transaction = new \Seolan\Module\Monetique\Model\Transaction();
    $transaction->oid = explode('/', $fields['texte-libre'])[0];
    $transaction->orderOid = explode('/', $fields['texte-libre'])[1];
    $transaction->amount = sprintf('%.02f', $fields['montant']);
    $transaction->orderReference = explode('/', $fields['texte-libre'])[2];
    $transaction->transId = $fields['numauto'];
    $transaction->responseParms = $fields;
    return $transaction;
  }

  // controle le status de la transaction d'origine
  // ici, on peux recevoir une acceptation après des refus, ou plusieurs retour ok
  protected function checkStatus($transaction, $transactionOrigin) {
    if ($transactionOrigin->status == self::SUCCESS) {
      return false;
    }
    return true;
  }

  // reponse au serveur bancaire
  protected function autoResponseError($error) {
    \Seolan\Core\Logs::critical(__METHOD__ , $error);
    echo "version=2\rcdr=1\r";
  }

  protected function autoResponseOk() {
    echo "version=2\rcdr=0\r";
  }

  private function formatAmount($amount) {
    return sprintf('%.02fEUR', $amount);
  }

  // Calcul MAC
  private function getMacPayment($values) {
    // les champs dans l'ordre
    $fields = ['TPE', 'date', 'montant', 'reference', 'texte-libre', 'version', 'lgue', 'societe', 'mail', 'nbrech',
      'dateech1', 'montantech1', 'dateech2', 'montantech2', 'dateech3', 'montantech3', 'dateech4', 'montantech4'];
    return $this->getMac($values, $fields);
  }

  private function getMacReturn($values) {
    // les champs dans l'ordre
    $fields = ['TPE', 'date', 'montant', 'reference', 'texte-libre', 'version', 'code-retour', 'cvx', 'vld', 'brand',
      'status3ds', 'numauto', 'motifrefus', 'originecb', 'bincb', 'hpancb', 'ipclient', 'originetr', 'veres', 'pares'];
    $values['version'] = self::PAIEMENT_VERSION;
    return $this->getMac($values, $fields);
  }

  private function getMacRefund($values) {
    // les champs dans l'ordre
    $fields = ['TPE', 'date', 'montant_recredit', 'montant_possible', 'reference', 'texte-libre', 'version', 'lgue', 'societe'];
    $values['version'] = self::RECREDIT_VERSION;
    return $this->getMac($values, $fields);
  }

  private function getMac($values, $fields) {
    $orderedValues = [];
    foreach ($fields as $field) {
      $orderedValues[] = isset($values[$field]) ? $values[$field] : '';
    }
    $sData = implode('*', $orderedValues) . '*';
    \Seolan\Core\Logs::debug(__METHOD__ . " sceau: $sData");
    $mac = strtolower(hash_hmac('sha1', $sData, $this->_getUsableKey()));
    \Seolan\Core\Logs::debug(__METHOD__ . " mac: $mac");
    return $mac;
  }

  private function _getUsableKey() {
    $hexStrKey = substr($this->hashKey, 0, 38);
    $hexFinal = '' . substr($this->hashKey, 38, 2) . '00';

    $cca0 = ord($hexFinal);

    if ($cca0 > 70 && $cca0 < 97)
      $hexStrKey .= chr($cca0 - 23) . substr($hexFinal, 1, 1);
    else {
      if (substr($hexFinal, 1, 1) == 'M')
        $hexStrKey .= substr($hexFinal, 0, 1) . '0';
      else
        $hexStrKey .= substr($hexFinal, 0, 2);
    }
    return pack('H*', $hexStrKey);
  }

  /**
   * Méthode de traitement d'un remboursement
   */
  protected function refundHandling($transaction) {
    // Création du formulaire de remboursement
    $transaction->callParms = $this->refundForm($transaction);
    // Mémorisation des paramètres d'appel
    $this->procEditTransaction($transaction);
    // Envoi du formulaire et mise à jour du retour dans $transaction
    $result = $this->sendCurlForm($this->getRecreditUrl(), $transaction);
    parse_str(str_replace("\n", '&', $result), $transaction->responseParms);
    $transaction->responseCode = $transaction->responseParms['cdr'];
    $transaction->statusComplement = $transaction->responseParms['lib'];
    $transaction->status = $transaction->responseCode == 0 ? self::SUCCESS : self::ERROR;
    return $transaction;
  }

  /**
   * Méthode de génération du formulaire de remboursement qui sera envoyé à la banque.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction correspondant au remboursement.
   * \return array la liste des champs pour l'appel remboursement
   */
  private function refundForm($transaction) {
    $fields = [
      'version' => self::RECREDIT_VERSION,
      'TPE' => $this->siteId,
      'date' => date('d/m/Y:H:i:s', strtotime($transaction->dateCreated)),
      'date_commande' => date('d/m/Y', strtotime($transaction->transactionOrigin->dateCreated)),
      // TODO: cas paiement différé
      // 'date_remise' => date('d/m/Y', strtotime($transaction->transactionOrigin->dateCreated)),
      'num_autorisation' => $transaction->transactionOrigin->transId,
      'montant' => $this->formatAmount($transaction->transactionOrigin->amount),
      'montant_recredit' => $this->formatAmount($transaction->amount),
      'montant_possible' => $this->formatAmount($transaction->transactionOrigin->amount - $transaction->refundedAmount),
      'reference' => $transaction->orderReference,
      'texte-libre' => $transaction->orderReference . '/' . $transaction->orderOid,
      'lgue' => 'FR',
      'societe' => $this->companyCode
    ];
    $fields['MAC'] = $this->getMacRefund($fields);
    return $fields;
  }

  /* Fonctions de débit forcé d'un abonné */

  /**
   * Méthode de traitement d'une duplication
   */
  protected function duplicateHandling($transaction) {
    // pas la doc de l'option "paiement express"
    throw new \Exception(__METHOD__ . ' not implemented');
  }

  /**
   * Fonction d'émission d'une requête
   */
  private function sendCurlForm($url, &$transaction) {
    // Initialisation d'une session CURL
    $ch = curl_init($url);
    // Définition de la méthode POST
    curl_setopt($ch, CURLOPT_POST, 1);
    // Insertion des champs du formulaire
    $fields = array_map($transaction->callParms, 'urlencode');
    curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $fields));
    // Définit que le retour doit être mis dans une variable
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // La réponse du serveur sera dans $result
    $result = curl_exec($ch);
    if ($result === false) {
      throw new \Exception('curl error ' . curl_error($ch));
    }
    curl_close($ch);
    return $result;
  }

  /* Fonctions utilitaires */

  private $filtrageCause = [
      1 => 'Adresse IP',
      2 => 'Numéro de carte',
      3 => 'BIN de carte',
      4 => 'Pays de la carte',
      5 => 'Pays de l’IP',
      6 => 'Cohérence pays de la carte / pays de l’IP',
      7 => 'Email jetable',
      8 => 'Limitation en montant pour une CB sur une période donnée',
      9 => 'Limitation en nombre de transactions pour une CB sur une période donnée',
      11 => 'Limitation en nombre de transactions par alias sur une période donnée',
      12 => 'Limitation en montant par alias sur une période donnée',
      13 => 'Limitation en montant par IP sur une période donnée',
      14 => 'Limitation en nombre de transactions par IP sur une période donnée',
      15 => 'Testeurs de cartes',
      16 => 'Limitation en nombre d’alias par CB',
  ];

  private function getLang($code = null) {
    switch ($code) {
      case 'GB':
        return 'EN';
      case 'SP';
        return 'ES';
      case 'DE':
      case 'FR':
      case 'IT':
      case 'JA':
      case 'NL':
      case 'PT':
      case 'SV':
        return $code;
      default:
        return 'FR';
    }
  }

}
