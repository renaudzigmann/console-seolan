<?php
namespace Seolan\Field\InfoTreeThesaurusSubSiteItself;

/**
 * Class XThesaurusSubSiteItselfDef
 *
 * Ce champ permet de modifier sa cible dynamiquement sur le module et table qui porte ce champ.
 * La propriété $this->target est modifié à partir du contexte à la construction de l'objet. Cepenant il n'est pas
 * possible (actuellement) de trouver le module courant ou est utilisé le champs lors de la construction de l'objet.
 *
 * @author Bastien Sevajol
 */
class InfoTreeThesaurusSubSiteItself extends \Seolan\Field\Thesaurus\Thesaurus {

  /**
   * {@inheritdoc}
   * Modifie la propriété $target à partir de $obj->DTAB.
   */
  public function __construct($obj=NULL) {
    parent::__construct($obj);
    $this->target = $obj->DTAB;
  }

  /**
   * {@inheritdoc}
   * Si on peux trouver le module courant ou est posé ce champs, on modifie la cible du champ sur ce module.
   */
  public function my_edit(&$value,&$options,&$fields_complement=NULL) {
    if ($options['fmoid']) {
      $this->changeTargetWithModuleMoid($options['fmoid']);
    }
    return parent::my_edit($value,$options,$fields_complement);
  }
}
