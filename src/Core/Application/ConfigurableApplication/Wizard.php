<?php
namespace Seolan\Core\Application\ConfigurableApplication;

/**
 * Les application qui nécessite une table de configuration doivent avoir un Wizard qui hérite de cette classe
 * afin de posséder la logique de création/maintient de cette table.
 * @author Bastien Sevajol
 */
abstract class Wizard extends \Seolan\Core\Application\Wizard {

  /**
   * @var XDataSource
   */
  private $config_xds;

  /**
   * Cet objet est utilisé pour assurer la présence de la table et des champs nécessaire a l'application.
   * @var XDataSourceCheckSpecs
   */
  private $table_structure_insurer;

  /**
   * @param null $params Cf. Julien Maurel
   */
  public function __construct($params=NULL) {
    parent::__construct($params);
    $this->config_xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.static::getCompleteTableName());
    $table_description = $this->getConfigTableDescription();
    $table_fields_descriptions = $this->getConfigTableFieldsDescriptions();
    $this->table_structure_insurer = new \Seolan\Core\DataSource\CheckSpecs($table_description, $table_fields_descriptions);
  }

  /**
   * Méthode appelé au lancement du wizard ? Cf. Julien Maurel
   */
  public function irun() {
    parent::irun();
    $this->configureTable();
  }

  /**
   * Assure la présence de la table et des champs de table permettant la configuration du module
   * @return void
   */
  private function configureTable() {
    $this->table_structure_insurer->perform();
    $this->getConfigDataSource(True);
  }

  /**
   * Configuration de la table de configuration de l'application
   * @return array
   */
  protected function getConfigTableDescription() {
    $table_name = static::getCompleteTableName();
    return array(
      'translatable' => True,
      'auto_translate' => True,
      'btab' => $table_name,
      'bname' => array(TZR_DEFAULT_LANG => 'System Application - '.get_class($this)),
      'publish' => False
    );
  }

  /**
   * @return string
   */
  public static function getCompleteTableName() {
    return 'APP_'.static::getConfigTableName();
  }

  /**
   * Retourne le XDataSource de la table de configuration.
   *
   * @param bool $instanciate Force instantation of config_xds (use it just after created it)
   * @return XDataSource
   * @throws Exception
   */
  protected function getConfigDataSource($instanciate = False) {
    if ($instanciate) {
      $this->config_xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.static::getCompleteTableName());
    }

    if (!$this->config_xds) {
      throw new Exception("Can't use config_xds before prepared it !");
    }
    return $this->config_xds;
  }

}