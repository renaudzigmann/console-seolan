<?php

namespace Seolan\Module\Monetique;

use Seolan\Core\Logs;

/**
 * Description of Fake
 * Pour test retour banque
 */
class Fake extends Monetique {

  public $table = 'TRANSACMONETIQUE';

  protected function duplicateHandling($newTransaction) {

  }

  protected function refundHandling($newTransaction) {

  }

  protected function webPaymentHandling(Model\Transaction $transaction) {
    return [];
  }

  protected function webPaymentUnFoldReponse() {
    $p = new \Seolan\Core\Param([], ['status' => self::SUCCESS]);
    $orderOid = $p->get('orderOid');
    if (!$orderOid) {
      Logs::critical(__METHOD__ . " orderOid not provided");
      die();
    }
    $status = $p->get('status');
    $transacOid = getDB()->fetchOne(
      'select koid from TRANSACMONETIQUE where orderOid=? order by dateCreated desc limit 1', [$orderOid]);
    if (!$transacOid) {
      Logs::critical(__METHOD__ . " transaction not found");
      die();
    }
    $transaction = $this->getTransaction($transacOid);
    $transaction->status = $status;
    $transaction->statusComplement = 'Fake response, for testing only';
    return $transaction;
  }

  // reponse au serveur bancaire
  protected function autoResponseError($error) {
    \Seolan\Core\Logs::critical(__METHOD__, $error);
    echo "ko, $error";
  }

  protected function autoResponseOk() {
    echo 'ok';
  }

}
