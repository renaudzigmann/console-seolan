<?php

/**
 * Monétique PayFip (TIPI)
 * Trésor public
 * Une seule URL de retour
 * au 15/10/19 payfip n'accepte pas les certificats SNI,
 * demander une règle d'exclusion de la redirection https pour l'url /csx/scripts/monetique-retour-auto.php
 */

namespace Seolan\Module\Monetique\PayFip;

use \Seolan\Module\Monetique\Model\Transaction;
use Seolan\Core\Logs;

class PayFip extends \Seolan\Module\Monetique\Monetique {

  const GUID_REGEX = '@^(\{{0,1}([0-9a-fA-F]){8}-([0-9a-fA-F]){4}-([0-9a-fA-F]){4}-([0-9a-fA-F]){4}-([0-9a-fA-F]){12}\}{0,1})$';
  // IPN ip appelante 145.242.11.3

  private $formUrl = 'https://www.payfip.gouv.fr/tpa/paiementws.web';
  protected $needTransId = false;
  protected $oneClickPossible = false;
  protected $defaultTemplate = 'Module/Monetique.payfip.html';
  public $urlBack;
  public $activation;

  public function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('transIdMask');
    $this->_options->delOpt('oneClick');
    $this->_options->delOpt('urlPayed');
    $this->_options->delOpt('urlCancelled');
    $this->_options->setOpt('Url de retour client', 'urlBack', 'text', ['size' => 60], NULL, 'Monétique');
    $this->_options->setOpt('Paiement d\'activation', 'activation', 'boolean', [], 0, 'Monétique');
    $this->_options->setComment('Permet de faire des paiements en mode "activation", le module doit-être en test', 'activation');
  }

  private function soapClient() {
    static $client = null;
    if (!$client) {
      $client = new \SoapClient(__DIR__ . '/wsdl/PaiementSecuriseService.wsdl',
        ['features' => SOAP_SINGLE_ELEMENT_ARRAYS, 'exceptions' => TRUE, 'trace' => true]);
    }
    return $client;
  }

  protected function webPaymentHandling(Transaction $transaction) {
    try {
      $request = [
        'arg0' => [
          'mel' => $transaction->customerEmail,
          'montant' => $this->formatOrderAmount($transaction->amount),
          'numcli' => $this->siteId,
          'objet' => 'Paiement commande ' . $transaction->orderReference,
          'refdet' => $transaction->orderReference,
          'saisie' => $this->testMode(true) ? ($this->activation ? 'X' : 'T') : 'W',
          'urlnotif' => str_replace(['https', ':443'], ['http', ''], $this->urlAutoResponse), // payfip n'accepte pas les certifs SNI
          'urlredirect' => $this->urlBack
        ]
      ];
      $payment = $this->soapClient()->creerPaiementSecurise($request);
      $payFip = ['url' => $this->formUrl . '?idop=' . $payment->return->idOp, 'creerPaiementSecuriseRequest' => $request];
      $transaction->callParms = $payFip;
      $transaction->transId = $payment->return->idOp;
    } catch (\SoapFault $e) {
      Logs::critical(__METHOD__ . ' soapFault '
        . $e->detail->FonctionnelleErreur->code . ' ' . $e->detail->FonctionnelleErreur->libelle
        . "\nrequest\n" . $this->soapClient()->__getLastRequest()
        . "\nresponse\n" . $this->soapClient()->__getLastResponse());
      throw new \Exception('Soap Erreur', 0, $e);
    }
    return [$transaction, $payFip, TZR_SHARE_DIR . $this->defaultTemplate, 'payFip'];
  }

  protected function webPaymentUnFoldReponse() {
    $idOp = $_REQUEST['idop'];
    if (preg_match(self::GUID_REGEX, $idOp)) {
      throw new \Exception('invalid idop');
    }
    try {
      $paymentDetails = $this->soapClient()->recupererDetailPaiementSecurise([
          'arg0' => [
            'idOp' => $idOp
          ]
        ]);
    } catch (\SoapFault $e) {
      Logs::critical(__METHOD__ . ' soapFault '
        . $e->detail->FonctionnelleErreur->code . ' ' . $e->detail->FonctionnelleErreur->libelle
        . "\nrequest\n" . $this->soapClient()->__getLastRequest()
        . "\nresponse\n" . $this->soapClient()->__getLastResponse());
      throw new \Exception('Soap Erreur', 0, $e);
    }
    $transacOid = getDB()->fetchOne('select koid from TRANSACMONETIQUE where transId=?', [$idOp]);
    if (!$transacOid) {
      throw new \Exception("transId $idOp not found");
    }
    $transaction = new Transaction();
    $transaction->oid = $transacOid;
    $transaction->amount = sprintf('%.02f', $paymentDetails->return->montant / 100);
    $transaction->orderReference = $paymentDetails->return->refdet;
    $transaction->transId = $idOp;
    $transaction->responseParms = $paymentDetails->return;
    $transaction->responseCode = $paymentDetails->return->resultrans;
    if ($paymentDetails->return->resultrans === "P" || $paymentDetails->return->resultrans === "V") {
      $transaction->status = self::SUCCESS;
    } else {
      $transaction->status = self::ERROR;
    }
    return $transaction;
  }

  protected function duplicateHandling($newTransaction) {
    throw new \Exception(__METHOD__ . ' not implemented');
  }

  protected function refundHandling($newTransaction) {
    throw new \Exception(__METHOD__ . ' not implemented');
  }

}
