<?php
namespace Seolan\Module\Monetique\PaypalBraintree;
use \Seolan\Core\Logs;

/**
 * Paypal Braintree
 */
class PaypalBraintree extends \Seolan\Module\Monetique\Monetique {

  public $defaultTemplate = 'Module/Monetique.paypal-braintree.html';
  public $sandboxToken = '';
  public $prodToken = '';


  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Token', 'prodToken', 'text', ['size' => 80], NuLL, 'PayPal');
    $this->_options->setOpt('Token sandbox', 'sandboxToken', 'text', ['size' => 80], NuLL, 'PayPal');
  }

  public function secGroups($function, $group = NULL) {
    $g = [
      'nonce' => ['none', 'ro', 'rwv', 'admin']
    ];
    if (isset($g[$function])) {
      if (!empty($group)) {
        return in_array($group, $g[$function]);
      }
      return $g[$function];
    }
    return parent::secGroups($function, $group);
  }

  // @return \Braintree_Gateway
  private function gateway() {
    static $gateway = NULL;
    if (!$gateway) {
      if ($this->testMode(true)) {
        $gateway = new \Braintree_Gateway(['accessToken' => $this->sandboxToken]);
      } else {
        $gateway = new \Braintree_Gateway(['accessToken' => $this->prodToken]);
      }
    }
    return $gateway;
  }

  protected function webPaymentHandling(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    $token = $this->gateway()->clientToken()->generate();
    return [$transaction, [
        'token' => $token,
        'amount' => sprintf('%.02f', $transaction->amount),
        'currency' => $this->defaultCurrency,
        'locale' => \Seolan\Core\Lang::getLocale($transaction->lang)['locale_code'],
      ], TZR_SHARE_DIR . $this->defaultTemplate, 'paypal'];
  }

  // ajax call from form
  public function nonce($ar) {
    Logs::critical(__METHOD__ . ' request : ' . var_export($_REQUEST, 1));
    $p = new \Seolan\Core\Param($ar);
    $transactionOid = $p->get('transaction');
    $transaction = $this->getTransaction($transactionOid);
    if (!$transaction) {
      Logs::critical(__METHOD__ . " Transaction not found : $transactionOid");
      returnJson(['err' => 'Transaction not found.']);
    }
    if ($transaction->status === self::SUCCESS) {
      Logs::critical(__METHOD__ . " Transaction already paid : $transactionOid");
      returnJson(['err' => 'Transaction already paid.']);
    }
    $tokenizationPayload = $p->get('tokenizationPayload');
    Logs::debug(__METHOD__ . ' ' . var_export($tokenizationPayload, 1));
    $sale = [
      'amount' => sprintf('%.02f', $transaction->amount),
      'orderId' => $transaction->orderReference,
      'paymentMethodNonce' => $tokenizationPayload['nonce'],
      'billing' => [
        'firstName' => $transaction->_customerInfos['firstName'],
        'lastName' => $transaction->_customerInfos['lastName'],
        'locality' => $transaction->_customerInfos['city'],
        'postalCode' => $transaction->_customerInfos['postalCode'],
        'streetAddress' => $transaction->_customerInfos['address'],
        'extendedAddress' => $transaction->_customerInfos['address2'],
        'countryCodeAlpha2' => $transaction->_customerInfos['countryCode']
      ],
      'options' => [
        'submitForSettlement' => TRUE
      ]
    ];
    $transaction->callParms = $sale;
    try {
      $result = $this->gateway()->transaction()->sale($sale);
    } catch(\Exception $e) {
      Logs::critical(__METHOD__ . ' Braintree_Transaction::sale exception : ' . $e->getMessage());
      returnJson(['error' => $e->getMessage()]);
    }
    $transaction->dateTimeIn = date('Y-m-d H:i:s');
    $transaction->responseParms = $result->transaction->paypal;
    $transaction->transId = $result->transaction->id;
    if ($result->success) {
      $transaction->status =  self::SUCCESS;
      $transaction->statusComplement = $result->transaction->status;
    } else {
      $transaction->status =  self::ERROR;
      $transaction->statusComplement = $result->transaction->status . ' /' . $result->message;
    }
    $this->procEditTransaction($transaction);
    if ($result->success && $transaction->autoResponseMode === self::SYNC_RESPONSE) {
      $this->notifyShop($transaction);
    }
    $return = ['redirect' => $result->success ? $this->urlPayed : $this->urlCancelled];
    Logs::critical(__METHOD__ . " return " . var_export($return, 1));
    returnJson($return);
  }

  // unused
  protected function webPaymentUnFoldReponse() {}


  protected function refundHandling($transaction) {
    $result = $this->gateway()->transaction()->refund($transaction->transactionOrigin->transId, $transaction->amount);
    if ($result->success) {
      $transaction->status = self::SUCCESS;
    } else {
      $transaction->status = self::ERROR;
    }
    $transaction->statusComplement = $result->transaction->status;
    return $transaction;
  }

  protected function duplicateHandling($transaction) {
    throw new \Exception(__METHOD__ . ' not implemented');
  }

}
