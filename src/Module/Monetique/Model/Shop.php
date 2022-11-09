<?php
/**
 * \brief Classe \Seolan\Module\Monetique\Model\Shop.
 * Classe contenant les paramètres de la boutique.
 */
namespace Seolan\Module\Monetique\Model;
class Shop{
  public $moid = null; ///< Identifiant du module de la boutique.
  public $class = null;///< Classe de la boutique.
  public $name = null;///< Nom de la boutique.
  public $autoResponseMode = null;///< Mode de reponse.
  public $autoResponseCallBack = null;///< Fonction de la boutique permettant le traitement de la réponse.
  public $cardsType =null; ///< Tableau des cartes voulue par la boutique
}
