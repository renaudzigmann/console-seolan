<?php
namespace Seolan\Application\MiniSite;
/**
 * wizard application minisite = application de base 
 * -> les parametres sont dans les local.ini dedies
 */
class Wizard extends \Seolan\Core\Application\Wizard{
  /**
   * Nom de la table utilisé pour la configuration de cette app
   * @return String
   */
  protected static function getConfigTableName(){
    return 'APP_MINISITE';
  }
}