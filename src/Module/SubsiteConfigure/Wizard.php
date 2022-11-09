<?php
namespace Seolan\Module\SubsiteConfigure;

/**
 * Class XModSubsiteConfigureWd
 * @author Bastien Sevajol
 *
 * Wizard de du module d'accès à le configuration du sous site.
 */
class Wizard extends \Seolan\Module\Record\Wizard {

  public function __construct($ar = array()) {
    parent::__construct($ar);
    $this->_module->theclass = '\Seolan\Module\SubsiteConfigure\SubsiteConfigure';
    $this->_module->modulename = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Application_Application','module_configure_sub_website');
    $this->_module->group = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties','text');
    $this->_module->table = SUB_SITE_APP_CLASS::getCompleteTableName();
  }

}