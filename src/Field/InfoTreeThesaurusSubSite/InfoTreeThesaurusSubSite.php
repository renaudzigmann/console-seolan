<?php
namespace Seolan\Field\InfoTreeThesaurusSubSite;

/**
 * Class XThesaurusSubSiteDef
 *
 * Ce champs modifie sa cible en fonction du sous-site actif.
 *
 * @author Bastien Sevajol
 */
class InfoTreeThesaurusSubSite extends \Seolan\Field\Thesaurus\Thesaurus {

  /**
   * Logique d'application du contexte sur un XLinkDef
   */
  use \Seolan\Field\InfoTreeLinkDefSubSite\SubSiteContextOneInfoTreeApplierTrait;

  /**
   * La recherche ajax ne peux pas être effectué: ajax8.php par lequel passe les scripts ajax ne comporte
   * pas les mecanismes habituels de la console. Mechanismes comme les APP ...
   * @var bool
   */
  public $ajax_search = False;

  public function __construct($obj=NULL) {
    parent::__construct($obj);
    $this->applyOneInfoTreeSubSiteContext();
    $this->applyDefaultFields();
  }

  /**
   * Si les champs nécessaire pour le thésaurus ne sont pas configuré, ont les renseigne avec les champs du
   * XModInfoTree.
   */
  protected function applyDefaultFields() {
    if (!$this->flabel) {
      $this->flabel = 'title';
    }
    if (!$this->fparent) {
      $this->fparent = 'linkup';
    }
  }
}
