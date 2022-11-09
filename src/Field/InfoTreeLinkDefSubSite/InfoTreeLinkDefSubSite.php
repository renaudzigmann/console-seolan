<?php
namespace Seolan\Field\InfoTreeLinkDefSubSite;

/**
 * Class XThesaurusSubSiteDef
 *
 * Ce champs modifie sa cible en fonction du sous-site actif.
 *
 * @author Bastien Sevajol
 */
class InfoTreeLinkDefSubSite extends \Seolan\Field\Thesaurus\Thesaurus {

  /**
   * Logique d'application du contexte sur un XLinkDef
   */
  use SubSiteContextOneInfoTreeApplierTrait;

  public function __construct($obj=NULL) {
    parent::__construct($obj);
    $this->applyOneInfoTreeSubSiteContext();
  }
}
