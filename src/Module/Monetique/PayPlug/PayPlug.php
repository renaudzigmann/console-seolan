<?php
namespace Seolan\Module\Monetique\PayPlug;

use Seolan\Core\Labels;
use Seolan\Core\System;
use Seolan\Module\Monetique\Model\Transaction;
use Seolan\Module\Monetique\Monetique;

/**
 * Classe de gestion des transactions PayPlug.
 */
class PayPlug extends Monetique {

  public $oneClickPossible = true;
  public $defaultTemplate = 'Module/Monetique.payplug.html';
  public $secretkey;
  public $secretkeytest;

  public function initOptions() {
    parent::initOptions();
    $alabel = Labels::getSysLabel('Seolan_Module_Monetique_PayPlug_PayPlug', 'modulename');
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_PayPlug_PayPlug', 'secretkey'), 'secretkey', 'text', NULL, NULL, $alabel);
    $this->_options->setOpt(Labels::getSysLabel('Seolan_Module_Monetique_PayPlug_PayPlug', 'secretkeytest'), 'secretkeytest', 'text', NULL, NULL, $alabel);
  }

  private function init() {
    System::loadVendor('payplug/payplug-php/lib/init.php');
    \Payplug\Payplug::init(array('secretKey' => $this->testMode(true) ? $this->secretkeytest : $this->secretkey));
  }

  protected function webPaymentHandling(Transaction $transaction){
    $this->init();

    $customer = $transaction->customerInfos;
    $billing = $shipping = array(
      'first_name'   => $customer->firstName ?: ' ',
      'last_name'    => $customer->lastName ?: ' ',
      'email'        => $transaction->customerEmail,
      'address1'     => $customer->address ?: ' ',
      'address2'     => $customer->address2 ?: ' ',
      'postcode'     => $customer->postalCode ?: ' ',
      'city'         => $customer->city ?: ' ',
      'country'      => $customer->countryCode ?: 'FR'
    );
    $shipping['delivery_type'] = 'OTHER';

    $params = array(
      'amount' => intval($transaction->amount * 100), // Il faut envoyer le montant en centimes
      'currency' => 'EUR',
      'billing'  => $billing,
      'shipping'  => $shipping,
      'hosted_payment' => array(
        'return_url' => $this->urlPayed,
        'cancel_url' => $this->urlCancelled
      ),
      'notification_url' => $this->urlAutoResponse,
      'metadata' => array(
        'customer_id' => $transaction->customerOid
      )
    );

    if($transaction->porteur) {
      $params['payment_method'] = $transaction->porteur;
    }

    if($transaction->enrollement) {
      $params['save_card'] = true;
    }

    $payment = \Payplug\Payment::create($params);

    $transaction->callParms['url'] = $payment->hosted_payment->payment_url;
    $transaction->callParms['method'] = "GET";
    $transaction->transId = $payment->id;
    if($payment->is_paid) {
      $transaction->status = self::SUCCESS;
    }

    $payPlug = ['url' => $payment->hosted_payment->payment_url];

    return [$transaction, $payPlug, TZR_SHARE_DIR . $this->defaultTemplate, 'payPlug'];
  }

  protected function webPaymentUnFoldReponse() {
    $this->init();

    $requestBody = file_get_contents('php://input');
    \Seolan\Core\Logs::critical(__METHOD__ . ' requestBody: ' . var_export($requestBody, 1));
    $resource = \Payplug\Notification::treat($requestBody);

    $isPayment = ($resource instanceof \Payplug\Resource\Payment);
    $isRefund = ($resource instanceof \Payplug\Resource\Refund);

    $transId = $resource->id;
    $transaction = new Transaction();
    $transaction->oid = getDB()->fetchOne('select KOID from TRANSACMONETIQUE where transId=?', array($transId));
    $transaction->responseParms = json_decode($requestBody, true);
    $transaction->status = (($isPayment && $resource->is_paid) || $isRefund) ? self::SUCCESS : self::ERROR;
    $transaction->amount = $resource->amount / 100;

    // Enrollement
    if($resource->card->id) {
      $transaction->porteur = $resource->card->id;
      $transaction->numCarte = '**** **** **** ' . $resource->card->last4;
      $transaction->dateVal = $resource->card->exp_month . substr($resource->card->exp_year, 2, 2);
      $transaction->cvv = 'N/A';
    }

    return $transaction;
  }

  protected function duplicateHandling($transaction) {
    list($transaction, $formulaireAppel, $template, $tplentry) = $this->webPaymentHandling($transaction);
    return $transaction;
  }

  protected function refundHandling($newTransaction){
    $this->init();

    $transId = getDB()->fetchOne('select transId from TRANSACMONETIQUE where KOID=?', array($newTransaction->transOri));
    $data = array(
      'amount'   => $newTransaction->amount * 100,
      'metadata' => array(
        'customer_id' => $newTransaction->customerOid
      )
    );
    $refund = \Payplug\Refund::create($transId, $data);
    $newTransaction->amount = $refund->amount / 100;
    $newTransaction->status = self::SUCCESS;

    return $newTransaction;
  }

}
