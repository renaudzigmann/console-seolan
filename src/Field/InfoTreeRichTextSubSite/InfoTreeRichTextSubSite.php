<?php
namespace Seolan\Field\InfoTreeRichTextSubSite;

/**
 * Ce champs modifie sa cible en fonction du sous-site actif.
 *
 * @author Bastien Sevajol
 */
class InfoTreeRichTextSubSite extends \Seolan\Field\RichText\RichText {

  public function __construct($obj=NULL) {
    parent::__construct($obj);
    \Seolan\Module\Application\Application::addRunAppCallback(SUB_SITE_APP_CLASS, function(\Seolan\Application\Site\Site $app) {
      $this->sourcemodule = $app->getXModInfoTreeMoid();
    });
  }
}
