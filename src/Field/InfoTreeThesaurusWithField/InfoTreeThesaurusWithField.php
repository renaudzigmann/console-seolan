<?php
namespace Seolan\Field\InfoTreeThesaurusWithField;

/**
 * Class XInfoTreeThesaurusSubSiteConfigDef
 *
 * Ce champ permet de modifier sa cible en fonction d'un autre champs présent dans l'enregistrement où on utilise
 * ce champs.
 *
 * @author Bastien Sevajol
 */
class InfoTreeThesaurusWithField extends \Seolan\Field\Thesaurus\Thesaurus{

  protected $config_infotree_field_name = 'infotree_field_name';

  function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(
      \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Application_Application','config_infotree_field'),
      $this->config_infotree_field_name,
      "text",
      array(
        'compulsory' => True,
        'type' => '\Seolan\Field\ShortText\ShortText'
      )
    );
  }

  /**
   * {@inheritdoc}
   * Exploite la propriété "infotree_field_name" pour modifier la cible du champs.
   * "infotree_field_name" permet d'exploiter un autre champ de la table sur laquelle est posé ce champ
   * pour trouver le module à cibler.
   *
   */
  public function my_edit(&$value,&$options,&$fields_complement=NULL){
    $field_name = $this->config_infotree_field_name;
    if (!$this->$field_name) {
      $xfieldval = parent::my_edit($value,$options,$fields_complement);
      $xfieldval->html = 'Veuillez configurer la propriété du champ "'.$this->$field_name.'" !';
      return $xfieldval;
    }

    $new_module_moid = $this->foundNewModuleMoid($fields_complement);
    $this->changeTargetWithModuleMoid($new_module_moid);

    return parent::my_edit($value,$options,$fields_complement);
  }

  /**
   * Retourne le moid du module cible
   * @param $fields_complement
   * @return int: Identifiant du module
   */
  protected function foundNewModuleMoid($fields_complement) {
    if (!($new_module_moid = $this->getNewModuleMoidFromDataOrNull($fields_complement))) {
      return $new_module_moid;
    }

    return $this->getNewModuleMoidFromRecord($fields_complement['KOID']);
  }

  /**
   * Retourne le moids du module cible a partir des données données en paramètre
   * @param $fields_complement
   * @return int | null
   */
  protected function getNewModuleMoidFromDataOrNull($fields_complement) {
    $field_name = $this->config_infotree_field_name;
    if (($new_module_moid = $fields_complement[$this->$field_name])) {
      return $new_module_moid;
    }

    return Null;
  }

  /**
   * Retourne le moid du nouveau module cible a partir de l'enregistrement de KOID donné
   *
   * @param $record_koid
   * @return int
   */
  protected function getNewModuleMoidFromRecord($record_koid) {
    $field_name = $this->config_infotree_field_name;
    $current_table_xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$this->table);
    $current_record = $current_table_xds->display(array(
      'oid' => $record_koid,
      'selectedfields' => array($this->$field_name)
    ));
    return $current_record['o'.$this->$field_name]->raw;
  }

}
