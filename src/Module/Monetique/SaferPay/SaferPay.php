<?php
namespace Seolan\Module\Monetique\SaferPay;

/**
 * SIX Payment Saferpay
 */
class SaferPay extends \Seolan\Module\Monetique\Monetique {

  const PROD_URL = 'https://www.saferpay.com/api';
  const TEST_URL = 'https://test.saferpay.com/api';
  const SPEC_VERSION = '1.7';

  protected $oneClickPossible = true;

  public $defaultTemplate = 'Module/Monetique.saferpay.html';
  public $prodTerminalId = '';
  public $prodPassword = '';
  public $testUsername = '';
  public $testTerminalId = '';
  public $testPassword = '';
  public $prodUsername = '';

  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('N° de terminal', 'prodTerminalId', 'text', ['size' => 30], NULL, 'Saferpay');
    $this->_options->setOpt('Nom d\'utilisateur', 'prodUsername', 'text', ['size' => 30], NULL, 'Saferpay');
    $this->_options->setOpt('Mot de passe', 'prodPassword', 'text', ['size' => 30], NULL, 'Saferpay');
    $this->_options->setOpt('N° de boutique', 'testSiteId', 'text', ['size' => 30], NULL, 'Saferpay Test');
    $this->_options->setOpt('N° de terminal', 'testTerminalId', 'text', ['size' => 30], NULL, 'Saferpay Test');
    $this->_options->setOpt('Nom d\'utilisateur', 'testUsername', 'text', ['size' => 30], NULL, 'Saferpay Test');
    $this->_options->setOpt('Mot de passe', 'testPassword', 'text', ['size' => 30], NULL, 'Saferpay Test');
  }

  private function preparePayload($id) {
    if ($this->testMode(TRUE)) {
      $siteId = $this->testSiteId;
    } else {
      $siteId = $this->siteId;
    }
    return (object) [
        'RequestHeader' => (object) [
          'SpecVersion' => self::SPEC_VERSION,
          'CustomerId' => $siteId,
          'RequestId' => $id,
          'RetryIndicator' => 0,
          'ClientInfo' => (object) [
            'ShopInfo' => \Seolan\Core\Ini::get('societe'),
          ],
        ],
        'TerminalId' => $this->testMode(true) ? $this->testTerminalId : $this->prodTerminalId
    ];
  }

  private function getUrl($action) {
    if ($this->testMode(TRUE)) {
      $url = self::TEST_URL;
    } else {
      $url = self::PROD_URL;
    }
    switch ($action) {
      case 'initialize':
        $url .= '/Payment/v1/PaymentPage/Initialize';
        break;
      case 'assert':
        $url .= '/Payment/v1/PaymentPage/Assert';
        break;
      case 'capture':
        $url .= '/Payment/v1/Transaction/Capture';
        break;
      case 'duplicate':
        $url .= '/Payment/v1/Transaction/AuthorizeReferenced';
        break;
      case 'refund':
        $url .= '/Payment/v1/Transaction/Refund';
        break;
      default:
        break;
    }
    return $url;
  }

  private function getCredentials() {
    if ($this->testMode(TRUE)) {
      return $this->testUsername . ':' . $this->testPassword;
    }
    return $this->prodUsername . ':' . $this->prodPassword;
  }

  private function do_post($action, $payload) {
    // set Options for CURL
    $url = $this->getUrl($action);
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLINFO_HEADER_OUT, true);
    // return Response to Application
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    // set Content-Headers to JSON
    curl_setopt($curl, CURLOPT_HTTPHEADER,
      [
      "Content-type: application/json",
      "Accept: application/json"
    ]);
    //execute call via http-POST
    curl_setopt($curl, CURLOPT_POST, true);
    // set POST-Body
    // convert DATA-Array into a JSON-Object
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload)); /*     * * */
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    // HTTP-Basic Authentication for the Saferpay JSON-API
    curl_setopt($curl, CURLOPT_USERPWD, $this->getCredentials());
    // CURL-Execute & catch response
    $jsonResponse = curl_exec($curl);
    \Seolan\Core\Logs::debug(__METHOD__ . ' curl header ' . curl_getinfo($curl, CURLINFO_HEADER_OUT));
    if (FALSE === $jsonResponse) {
      throw new \Exception("call to $url failed curl_error: " . curl_error($curl), curl_errno($curl));
    }
    // convert response into a multidimensional Array
    $response = json_decode($jsonResponse, FALSE);
    $response->_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $response->_success = $response->_status == 200;
    curl_close($curl);
    return $response;
  }

  protected function webPaymentHandling(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    $payload = $this->preparePayload($transaction->oid);
    $payload->Payment = (object) [
        'OrderId' => $transaction->orderReference,
        'Description' => $transaction->orderReference,
        'Amount' => (object) [
          'CurrencyCode' => $this->defaultCurrency,
          'Value' => $this->formatOrderAmount($transaction->amount)
        ]
    ];
    $payload->ReturnUrls = (object) [
        'Success' => $this->urlPayed,
        'Fail' => $this->urlCancelled,
        'Abort' => $this->urlCancelled,
    ];
    $payload->Notification = (object) [
        'NotifyUrl' => $this->urlAutoResponse . '&transaction=' . $transaction->oid,
    ];
    $payload->Payer = (object) [
        'LanguageCode' => \Seolan\Core\Lang::getLocale($transaction->lang)['code'],
    ];
    if ($transaction->captureMode == self::AUTHORIZATION_ONLY) {
      $payload->Options->PreAuth = TRUE;
    }
    if ($transaction->enrollement) {
      $payload->Payment->Recurring = (object) ['Initial' =>  TRUE];
    }
    // a voir
//    elseif ($transaction->nbDeadLine) {
//      $payload->Installment = (object) ['Initial' =>  TRUE];
//    }
    $transaction->callParms = $payload;
    $response = $this->do_post('initialize', $payload);
    if (!$response->_success) {
      throw new \Exception($response->ErrorMessage, $response->_status);
    }
    $transaction->callParms->_Token = $response->Token;
    return [
      $transaction,
      [
        'token' => $response->Token,
        'redirectUrl' => $response->RedirectUrl,
      ],
      TZR_SHARE_DIR . $this->defaultTemplate,
      'sp'
    ];
  }

  protected function webPaymentUnFoldReponse() {
    $transactionOid = $_REQUEST['transaction'];
    $transaction = $this->getTransaction($transactionOid);
    $payload = $this->preparePayload($transaction->oid);
    $payload->Token = $transaction->_callParms['_Token'];

    $assert = $this->do_post('assert', $payload);
    $transaction->responseParms = ['assert' => $assert];
    if (!$assert->_success) {
      $transaction->status = self::ERROR;
      $transaction->statusComplement = $assert->ErrorMessage;
      return $transaction;
    }
    $transaction->status = self::SUCCESS;
    $transaction->statusComplement = $assert->Transaction->Status;
    $transaction->transId = $assert->Transaction->Id;
    $transaction->responseCode = $assert->Transaction->ApprovalCode;
    $transaction->cvv = 'N/A';
    $transaction->porteur = $assert->PaymentMeans->Card->HolderName;
    $transaction->dateVal = $assert->PaymentMeans->Card->ExpMonth . substr($assert->PaymentMeans->Card->ExpYear, -2);
    $transaction->numCarte = $assert->PaymentMeans->DisplayText;
    if (!empty($transaction->_callParms['Payment']['Recurring']['Initial'])) {
      $transaction->refAbonneBoutique = $assert->Transaction->Id;
    }
    if ($transaction->captureMode == self::AUTHORIZATION_ONLY) {
      return $transaction;
    }
    // immediate capture
    return $this->capture($transaction);
  }

  protected function refundHandling($transaction) {
    $payload = $this->preparePayload($transaction->oid);
    $payload->Refund = (object) [
        'Amount' => (object) [
          'CurrencyCode' => $this->defaultCurrency,
          'Value' => $this->formatOrderAmount($transaction->amount)
        ]
    ];
    $payload->TransactionReference = (object) [
      'TransactionId' => $transaction->transactionOrigin->transId
    ];

    $transaction->callParms = $payload;
    $refund = $this->do_post('refund', $payload);
    $transaction->responseParms = ['refund' => $refund];
    if (!$refund->_success) {
      $transaction->status = self::ERROR;
      $transaction->statusComplement = $refund->ErrorMessage;
      return $transaction;
    }
    $transaction->status = self::SUCCESS;
    $transaction->statusComplement = $refund->Transaction->Status;
    $transaction->transId = $refund->Transaction->Id;
    $transaction->responseCode = $refund->Transaction->ApprovalCode;
    $transaction->cvv = 'N/A';
    $transaction->porteur = $refund->PaymentMeans->Card->HolderName;
    $transaction->dateVal = $refund->PaymentMeans->Card->ExpMonth . substr($refund->PaymentMeans->Card->ExpYear, -2);
    $transaction->numCarte = $refund->PaymentMeans->DisplayText;
    return $this->capture($transaction);
  }

  protected function capture($transaction) {
    $capturePayload = $this->preparePayload($transaction->oid);
    $capturePayload->TransactionReference = (object) [
      'TransactionId' => $transaction->transId
    ];
    $capture = $this->do_post('capture', $capturePayload);
    $transaction->responseParms['capture'] = $capture;
    if (!$capture->_success) {
      $transaction->status = self::ERROR;
      $transaction->statusComplement = $capture->ErrorMessage;
      return $transaction;
    }
    $transaction->statusComplement = $capture->Status;
    return $transaction;
  }

  protected function duplicateHandling($transaction) {
    $payload = $this->preparePayload($transaction->oid);
    $payload->Payment = (object) [
        'PayerNote' => $transaction->orderReference,
        'Description' => $transaction->orderReference,
        'Amount' => (object) [
          'CurrencyCode' => $this->defaultCurrency,
          'Value' => $this->formatOrderAmount($transaction->amount)
        ]
    ];
    $payload->TransactionReference = (object) [
      'TransactionId' => $transaction->refAbonneBoutique
    ];
    $transaction->callParms = $payload;
    $response = $this->do_post('duplicate', $payload);
    $transaction->responseParms = ['duplicate' => $response];
    if (!$response->_success) {
      $transaction->status = self::ERROR;
      $transaction->statusComplement = $response->ErrorMessage;
      return $transaction;
    }
    $transaction->status = self::SUCCESS;
    $transaction->statusComplement = $response->Transaction->Status;
    $transaction->transId = $response->Transaction->Id;
    $transaction->responseCode = $response->Transaction->ApprovalCode;
    return $this->capture($transaction);
  }

}
