<?php
namespace Seolan\Core\DataSource;

/**
 * Contrôle l'existance d'un table et de ses champs. Crée la table et/ou les champs si besoin.
 * Si la table ou les champs existent, on ne met pas à jour les propriétés de la table ou des champs.
 * Les propriétés ne sont utilisées uniquement lors de la création de la table/champs.
 * @author Bastien Sevajol
 */
class CheckSpecs {

  /**
   * Description de la table. Tableau contenant le tableau de paramétres pour \Seolan\Model\DataSource\Table\Table::procNewSource
   * NOTE: (jan 2015) Dans l'absolue ce serait bien que ce soit un objet ici, de façon a pouvoir décrire en plus
   *       des propriétés d'un champ, celle que l'on veut qui soient obligatoire (et donc vérifier ici par \Seolan\Core\DataSource\CheckSpecs)
   * @var array
   */
  private $table_description;

  /**
   * Description des champs de la table. Tableau contenant des tableaux contenant les paramétres pour \Seolan\Model\DataSource\Table\Table::createField
   * NOTE: (jan 2015) Dans l'absolue ce serait bien que ce soit un objet ici, de façon a pouvoir décrire en plus
   *       des propriétés d'un champ, celle que l'on veut qui soient obligatoire (et donc vérifier ici par \Seolan\Core\DataSource\CheckSpecs)
   * @var array
   */
  private $table_fields_descriptions;

  /**
   * @param array $table_description
   * @param array $table_fields_descriptions
   */
  public function __construct(array $table_description, array $table_fields_descriptions) {
    $this->table_description = $table_description;
    $this->table_fields_descriptions = $table_fields_descriptions;
  }

  /**
   * Vérifie l'existence de la table, puis des champs de cette table.
   * Si la table n'existe pas elle est créé, pareil pour les champs.
   * @return void
   */
  public function perform() {
    $this->assureTable();
    $this->assureTableFields();
  }

  /**
   * Vérifie l'existence de la table, si la table n'existe pas elle est créé.
   * @throws ImproperlyConfiguredException
   * @throws SeolanException
   * @return void
   */
  protected function assureTable() {
    $table_name = $this->getTableName();
    if(!\Seolan\Core\System::tableExists($table_name)) {
      $return_message = \Seolan\Model\DataSource\Table\Table::procNewSource($this->table_description);
      if ($return_message['error']) {
        throw new \Seolan\Core\Exception\Exception("Unable to create table (error message: \"".$return_message['message'].'"');
      }
    }
  }

  /**
   * Retourne le nom de la table à partir de la description de la table
   * @return String
   * @throws ImproperlyConfiguredException
   */
  private function getTableName() {
    if (!$this->table_description['btab']) {
      throw new \Seolan\Core\Exception\ImproperlyConfigured("Table name not found in table description");
    }

    return $this->table_description['btab'];
  }

  /**
   * Vérifie l'existence des champs de la table, si un champs n'existe pas il est créé.
   * @return void
   */
  protected function assureTableFields() {
    $table_xds = $this->getDataSource();
    foreach ($this->table_fields_descriptions as $field_description) {
      if ($field_description[0] && !$table_xds->fieldExists($field_description[0])) {
	// permet le passage de tableau en tant que paramètres formels ...
        call_user_func_array(array($table_xds, 'createField'), $field_description);
      }
    }
  }

  /**
   * @return null
   * @throws ImproperlyConfiguredException
   * @return DataSource
   */
  private function getDataSource() {
    return \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$this->getTableName());
  }

}