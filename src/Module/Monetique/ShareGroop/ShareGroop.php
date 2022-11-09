<?php

namespace Seolan\Module\Monetique\ShareGroop;

use \Seolan\Core\Logs;

class ShareGroop extends \Seolan\Module\Monetique\Monetique {

  const PROD_URL = 'https://api.sharegroop.com/v1';
  const TEST_URL = 'https://api.sandbox.sharegroop.com/v1';

  public $defaultTemplate = 'Module/Monetique.sharegroop.html';
  public $publicKeyTest = '';
  public $secretKeyTest = '';
  public $webhookSecretKeyTest = '';
  public $publicKeyProd = '';
  public $secretKeyProd = '';
  public $webhookSecretKeyProd = '';
  public $sharingLimit;

  public function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('siteId');
    $this->_options->setOpt('Clé publique de test', 'publicKeyTest', 'text', ['size' => 40], null, 'ShareGroop');
    $this->_options->setOpt('Clé secrète de test', 'secretKeyTest', 'text', ['size' => 40], null, 'ShareGroop');
    $this->_options->setOpt('Clé secrète webhook de test', 'webhookSecretKeyTest', 'text', ['size' => 40], null, 'ShareGroop');
    $this->_options->setOpt('Clé publique de prod', 'publicKeyProd', 'text', ['size' => 40], null, 'ShareGroop');
    $this->_options->setOpt('Clé secrète de prod', 'secretKeyProd', 'text', ['size' => 40], null, 'ShareGroop');
    $this->_options->setOpt('Clé secrète webhook de prod', 'webhookSecretKeyProd', 'text', ['size' => 40], null, 'ShareGroop');
    $this->_options->setOpt('Limite de temps de partage (en jours, max 6)', 'sharingLimit', 'integer', null, 5, 'ShareGroop');
  }

  private function getUrl($action) {
    if ($this->testMode(TRUE)) {
      $url = self::TEST_URL;
    } else {
      $url = self::PROD_URL;
    }
    $url .= $action;
    return $url;
  }

  private function do_post($action, $payload) {
    // set Options for CURL
    $url = $this->getUrl($action);
    $curl = curl_init($url);
    curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: ' . ($this->testMode(true) ? $this->secretKeyTest : $this->secretKeyProd)
      ],
    ]);
    // CURL-Execute & catch response
    $jsonResponse = curl_exec($curl);
    if (FALSE === $jsonResponse) {
      throw new \Exception("call to $url failed curl_error: " . curl_error($curl), curl_errno($curl));
    }
    // convert response into a multidimensional Array
    $response = json_decode($jsonResponse, true);
    curl_close($curl);
    return $response;
  }

  private function do_get($action) {
    // set Options for CURL
    $url = $this->getUrl($action);
    $curl = curl_init($url);
    curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => [
        'Authorization: ' . ($this->testMode(true) ? $this->secretKeyTest : $this->secretKeyProd)
      ],
    ]);
    // CURL-Execute & catch response
    $jsonResponse = curl_exec($curl);
    if (FALSE === $jsonResponse) {
      throw new \Exception("call to $url failed curl_error: " . curl_error($curl), curl_errno($curl));
    }
    // convert response into a multidimensional Array
    $response = json_decode($jsonResponse, true);
    curl_close($curl);
    return $response;
  }

  protected function webPaymentHandling(\Seolan\Module\Monetique\Model\Transaction $transaction) {
    // Chez ShareGroop la durée max de partage est de 6 jours.
    $sharingLimit = $this->sharingLimit <= 6 ? $this->sharingLimit : 6;
    $dueDate = strtotime('now +' . $sharingLimit . ' days') * 1000;
    // Pour éviter le problème du changement d'heure on enlève toujours une heure si on utilise la sharing limit
    // et qu'elle vaut 6. Sinon risque de problème au lancement du widget.
    if ($sharingLimit == 6) {
      $dueDate -= 3600000;
    }
    $payload = (object) [
        'trackId' => $transaction->orderReference,
        'amount' => $this->formatOrderAmount($transaction->amount),
        'dueDate' => $dueDate,
        'currency' => $this->defaultCurrency,
        'locale' => \Seolan\Core\Lang::getLocale($transaction->lang)['code'],
        'ux' => 'collect',
        'secure3D' => true,
        'email' => $transaction->customerInfos->email,
        'firstName' => $transaction->customerInfos->firstName,
        'lastName' => $transaction->customerInfos->lastName,
        'notifyUrl' => $this->urlAutoResponse . '&transaction=' . $transaction->oid,
    ];
    $transaction->callParms = (array) $payload;
    $response = $this->do_post('/orders', $payload);
    if (!$response['success']) {
      throw new \Exception($response['errors'][0], $response['status']);
    }
    return [
      $transaction,
      [
        'publicKey' => $this->testMode(true) ? $this->publicKeyTest : $this->publicKeyProd,
        'orderId' => $response['data']['id'],
        'platformId' => $response['data']['platformId'],
        'url' => $this->testMode(true) ? 'https://widget.sandbox.sharegroop.com/widget.js' : 'https://widget.sharegroop.com/widget.js',
        'urlPayed' => $this->urlPayed,
        'urlCancelled' => $this->urlCancelled,
      ],
      TZR_SHARE_DIR . $this->defaultTemplate,
      'sg'
    ];
  }

  protected function webPaymentUnFoldReponse() {
    $rawRequestBody = file_get_contents('php://input');
    $requestBody = json_decode($rawRequestBody, true);
    if (!in_array($requestBody['event'], ['order.confirmed'])) {
      Logs::critical(__METHOD__ . " L'événement : " . $requestBody['event'] . " n'est pas écouté.");
      $this->autoResponseOk();
      exit;
    }
    $transactionOid = $_REQUEST['transaction'];
    $transaction = $this->getTransaction($transactionOid);
    //Pour vérifier l'intégrité des données que l'on reçoit depuis les webhooks
    $headers = apache_request_headers();
    $signature = substr($headers["Sg-Signature"], 3);
    $webhookSecretKey = $this->testMode(true) ? $this->webhookSecretKeyTest : $this->webhookSecretKeyProd;
    $hmac = hash_hmac('sha256', $rawRequestBody, $webhookSecretKey);
    if (!hash_equals($hmac, $signature)) {
      $transaction->status = self::ERROR;
      $transaction->statusComplement = 'Données sharegroop compromises';
      return $transaction;
    }
    $transaction->responseParms = $requestBody;
    $transaction->statusComplement = 'Paiement autorisé.';
    $transaction->transId = $requestBody['id'];
    $transaction->status = self::SUCCESS;
    return $transaction;
  }

  protected function refundHandling($transaction) {
    if (round((float) $transaction->amount, 2) != round((float) $transaction->transactionOrigin->amount, 2)) {
      $transaction->status = self::ERROR;
      $transaction->statusComplement = "Les commandes payées via ShareGroop ne sont pas partiellement remboursables.";
      return $transaction;
    }
    $orderId = $transaction->transactionOrigin->_responseParms['orderId'];
    $orderSG = $this->do_get('/orders/' . $orderId);
    if (!$orderSG['success']) {
      throw new \Exception($orderSG['errors'][0], $orderSG['status']);
    }

    // Si commande confirmée mais pas complétée (il n'y a pas eu de prélèvement sur le compte du client),
    // on annule la commande au lieu de la rembourser
    switch ($orderSG['data']['status']) {
      case 'confirmed':
        $orderStatus = '/cancel';
        break;
      case 'completed':
        $orderStatus = '/refund';
        break;
      default:
        $transaction->status = self::ERROR;
        $transaction->statusComplement = "La commande n'est ni remboursable ni annulable";
        return $transaction;
        break;
    }
    $response = $this->do_post('/orders/' . $orderId . $orderStatus, []);
    if (!$response['success']) {
      $transaction->status = self::ERROR;
      $transaction->statusComplement = $response['errors'][0];
      return $transaction;
    }
    $transaction->responseParms = ['refund' => $response['data']];
    $transaction->status = self::SUCCESS;
    return $transaction;
  }

  protected function duplicateHandling($newTransaction) {
    throw new \Exception(__METHOD__ . ' not implemented');
  }

}
