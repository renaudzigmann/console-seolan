<?php
/**
 * \brief Classe \Seolan\Module\Monetique\Model\Order.
 * Classe représentant une commande.
 */

namespace Seolan\Module\Monetique\Model;
class Order{
  public $oid = null; ///< Identifiant oid de la commande.
  public $reference = null; ///< Référence de la commande.
  public $amount = null; ///< Montant de la commande.
  public $traceId = null; ///< Id de traçabilité (supposé remonté à la banque)
  /**
   * \brief Contient les options éventuelles de la commande.
   * \details Doit être valorisé à :
   * - Pour un enrollement :
   *  - $order->options['enrollement']= true;
   *  - $order->options['refAbonne'] = '<Référence de l'abonné>';
   * - Pour un paiement multiple :
   *  - $order->options['nbDeadLine'] = 3;
   *  - Facultatif:
   *   - $order->options['frequencyDuplicate'] = 31; Fréquence de prélevement en jours. Par défaut 30.
   *   - $order->options['captureDay'] = 10; Spécifique à paybox : Numéro du jour de prelevement dans le mois. Par défaut le 1.
   * - Pour modifier le délai de la capture :
   *   - $order->options['captureDelay'] = 10; Délais de capture réelle de la transaction en jours. Par défaut 0.
   *  - Pour une demande d'autorisation seule :
   *   - $order->options['noCapture'] = true; ///< Demande d'autorisation uniquement
   */
  public $options = [];
  public $cardsType = [];
  public $returnContext = null; ///<Url origine de l'appel>
  public $statementReference = null; ///Atos Sips : référence envoyée dans le flux de remise en banque, apparaissant sur le compte du porteur (client) (?)
}