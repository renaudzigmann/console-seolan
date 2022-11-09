<?php
namespace Seolan\Module\SubsiteConfigure;

/**
 * Class XModSubsiteConfigure
 * @author Bastien Sevajol
 *
 * Module d'accès à la configuration du sous site (voir l'app \Seolan\Application\Site\Site).
 */
class SubsiteConfigure extends \Seolan\Module\Record\Record {

  public function __construct($ar = array()) {
    parent::__construct($ar);
    $this->addCallback(self::EVENT_PRE_CRUD, function(\Seolan\Core\Module\Module $module, $ar) {
      // Désactivation du champ security_group
      $ar['fieldssec']['security_group'] = 'none';
      return $ar;
    });
  }

}