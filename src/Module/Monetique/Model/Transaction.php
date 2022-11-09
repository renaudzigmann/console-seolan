<?php
namespace Seolan\Module\Monetique\Model;
/**
 * \brief Classe Transaction.
 * Classe représentant une transaction.
 * \note
 * - Pour Paybox et Atos une transaction en état WAITTING signifie que le serveur n'était pas disponible lors de l'appel.
 * - Pour SystemPay une transaction en état WAITTING signifie que le status en banque de la transaction ne permet pas cette action pour le moment. Elle devra donc être rejouée. Exemple: Un remboursement ne peut être éfféctué que si la transaction à été remisé. Si le remboursement est intégrale, on peut annulé la transaction, sinon il faudra attendre.
 * TEST MULTI WEB_PAYMENT :
 * - $order->options['nbDeadLine'] = 3;
 * - $order->options['frequencyDuplicate'] = 31;
 * - $order->options['captureDay'] = 10; ///< Spécifique à paybox (numéro du jour de prélèvement dans le mois)
 * - $order->options['captureDelay'] = 10;
 * - $order->options['noCapture'] = true; ///< Demande d'autorisation uniquement
 *
 * TEST D'ENROLLEMENT :
 * - $order->options['enrollement']= true;
 * - $order->options['refAbonne'] = 'Atos Refus';
 *
 */
class Transaction {

  public $oid = null; ///< Identifiant de la transaction.
  public $orderOid = null; ///< Identifiant oid de la commande.
  public $orderReference = null; ///< Référence de la commande.
  public $customerOid = null; ///< Identifiant oid du client.
  public $customerEmail = null; ///< Email du client.
  public $dateCreated = null; ///< Date de création de la transaction.
  public $responseCode = null; ///< Code reponse de la banque.
  public $amount = null; ///< Float $amount : Montant de la transaction en euros.

  /**
   * \brief Status de la transaction.
   * \details Peut-être valorisé à :
   * - \Seolan\Module\Monetique\Monetique::RUNNING.
   * - \Seolan\Module\Monetique\Monetique::WAITTING.
   * - \Seolan\Module\Monetique\Monetique::SUCCESS.
   * - \Seolan\Module\Monetique\Monetique::ERROR.
   * - \Seolan\Module\Monetique\Monetique::INVALID.
   */
  public $status = null;

  /**
   * \brief Type de la transaction.
   * \details Peut-être valorisé à :
   * - \Seolan\Module\Monetique\Monetique::DUPLICATE.
   * - \Seolan\Module\Monetique\Monetique::WEB_PAYMENT.
   * - \Seolan\Module\Monetique\Monetique::REFUND.
   */
  public $type = null;
  public $dateTimeOut = null; ///< Date et heure d'envoi en banque des paramètres de la transaction.
  public $dateTimeIn = null; ///< Date et heure du retour banque de la transaction.
  public $transOri = null; ///< Lien vers la transaction d'origine (oid de la transaction d'origine).
  public $shopMoid = null; ///< Identifiant du module de la boutique.
  public $shopClass = null; ///< Classe de la boutique.
  public $shopName = null; ///< Nom de la boutique.
  /**
   * \brief  Mode de la notification boutique.
   * \details Peut-être valorisé à :
   * - \link \Seolan\Module\Monetique\Monetique::ASYNC_RESPONSE \endlink
   * - \link \Seolan\Module\Monetique\Monetique::SYNC_RESPONSE \endlink
   * - \link \Seolan\Module\Monetique\Monetique::RESPONSE_NONE \endlink
   */
  public $autoResponseMode = null;
  public $shopCallBack = null; ///< Fonction de la boutique permettant le traitement de la réponse.
  public $statusComplement = null;  ///< Complément de statut de la transaction. Donne des informations complémentaire sur l'état de la transaction.
  public $callParms = null; ///< Paramètres bruts transmis lors de l'appel en banque.
  public $responseParms = null;  ///< Réponse brute retournée par la banque.
  public $refAbonneBoutique = null; ///< Référence d'abonné attribué à un client lors d'une commande définissant un enrollement.
  public $monetiqueMoid = null; ///< Identifiant du module de gestion des transaction utilisé pour celle-ci.
  public $nbReturn = null; ///< Nombre de retour banque (Utile à Paybox uniquement, maximum 3 trois retour pour un paiement web).
  /**
   * \brief  Statut de la notification boutique.
   * \details Peut-être valorisé à :
   * - \Seolan\Module\Monetique\Monetique::RESPONSE_STATUS_SENT \endlink
   * - \Seolan\Module\Monetique\Monetique::RESPONSE_STATUS_TO_SEND \endlink
   * - \Seolan\Module\Monetique\Monetique::RESPONSE_STATUS_NOT_TO_SEND \endlink
   */
  public $responseStatus = null;
  public $transId = null; ///< Identifiant en bancaire de la transaction.
  public $modeTest = True; ///< Mode test par defaut.
  public $captureDelay = '0'; ///< Capture immédiate par défaut.
  /**
   * \brief Capture par défaut, sinon simple demande d'autorisation.
   * \details Peut-être valorisé à :
   * - \link \Seolan\Module\Monetique\Monetique::AUTHORIZATION_ONLY \endlink
   * - \link \Seolan\Module\Monetique\Monetique::CATCH_PAYMENT \endlink
   */
  public $captureMode = null;
  public $frequencyDuplicate = null; ///< Capture immédiate par défaut.
  public $enrollement = False; ///< Pas d'enrollement par défaut
  public $nbDeadLine = 1; ///< Nombre d'échéances traitement de la transaction
  public $ip;
  public $lang = null;
  public $returnContext = null; ///<Url origine de l'appel>
  public $statementReference = null; ///Atos Sips : référence envoyée dans le flux de remise en banque, apparaissant sur le compte du porteur (client) (?)
}
