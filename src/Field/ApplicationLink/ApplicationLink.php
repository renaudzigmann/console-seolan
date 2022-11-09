<?php
namespace Seolan\Field\ApplicationLink;

use Seolan\Core\Shell;
use Seolan\Field\Link\Link;
use Seolan\Module\Application\Application;

class ApplicationLink extends Link {

  public function __construct($obj = NULL) {
    $obj->FIELD = "APP";
    $obj->TARGET = "APP";
    $obj->TRANSLATABLE = "0";
    $obj->MULTIVALUED = "0";
    parent::__construct($obj);
  }

  function my_edit(&$value, &$options, &$fields_complement = NULL) {
    if(Shell::isRoot()) {
      return parent::my_edit($value, $options, $fields_complement);
    }
    else {
      return parent::my_display($value, $options);
    }
  }

  function input(&$value, &$options = array(), $fields_complement = null) {
    if(TZR_USE_APP) {
      $bootstrapApplication = Application::getBootstrapApplication();
      $value = $bootstrapApplication->oid;
    }
    return parent::input($value, $options, $fields_complement);
  }

}
