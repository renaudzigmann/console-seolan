<?php
namespace Seolan\Field\InfoTreeThesaurusWithSubSiteConfigField;

/**
 * Class XInfoTreeThesaurusWithSubSiteConfigFieldDef
 *
 * Modifie le comportement de XInfoTreeThesaurusWithFieldDef pour que le champs (config_infotree_field_name) ne renseigne
 * pas directement le moid du infotree, mais vers la ligne de config du sous site.
 */
class InfoTreeThesaurusWithSubSiteConfigField extends \Seolan\Field\InfoTreeThesaurusWithField\InfoTreeThesaurusWithField {

  protected $config_infotree_field_name = 'sub_site_field_name';

  function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('infotree_field_name');
    $this->_options->setOpt(
      \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Application_Application','config_sub_site_field'),
      $this->config_infotree_field_name,
      "text",
      array(
        'compulsory' => True,
        'type' => '\Seolan\Field\ShortText\ShortText'
      )
    );
  }

  /**
   * Retourne le nouveau moid de module en allant chercher l'information dans la table de config des sous sites.
   * {@inheritdoc}
   * @param $fields_complement
   * @return int | null
   */
  protected function getNewModuleMoidFromDataOrNull($fields_complement) {
    if (($app_site_config_koid = parent::getNewModuleMoidFromDataOrNull($fields_complement))) {
      return $this->getXModInfoTreeInConfig($app_site_config_koid);
    }

    return Null;
  }

  /**
   * Retourne le nouveau moid de module en allant chercher l'information dans la table de config des sous sites, en
   * exploitant l'enregistrement courant pour trouver la config de site sélectionné.
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
    return $this->getXModInfoTreeInConfig($current_record['o'.$this->$field_name]->raw);
  }

  /**
   * Retourne le moid d'infotree choisis dans la config donné
   * @param $app_site_config_koid
   * @return int
   */
  protected function getXModInfoTreeInConfig($app_site_config_koid) {
    $sub_site_classes = SUB_SITE_APP_CLASS;
    $config_table_xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$sub_site_classes::getCompleteTableName());
    $config_record = $config_table_xds->display(array(
      'oid' => $app_site_config_koid,
      'selectedfields' => array('params')
    ));
    return json_decode($config_record['oparams']->raw)->infotree;
  }

}