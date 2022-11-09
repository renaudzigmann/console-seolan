<?php
namespace Seolan\Core\Application;

/**
 * Class ConfigurableApp
 * Cette classe abstraite est à utiliser comme classe parente de votre
 * "app" si cette "app" doit exploiter une table de configuration.
 * Le wizard de cette app doit étendre la classe namespace Seolan\Core\Application\ConfigurableApplication\Wizard 
 */
abstract class ConfigurableApplication extends \Seolan\Core\Application\Application {

  /**
   * Retourne le nom complet de la table contenant la configuration
   * de l'app.
   * @return string
   */
  public static function getCompleteTableName() {
    return \Seolan\Application\Site\Wizard::getCompleteTableName();
  }

}