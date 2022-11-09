<?php
namespace Seolan\Field\Expression;
class Expression extends \Seolan\Field\Text\Text {
  function __construct($obj=NULL) {
    parent::__construct($obj);
  }
  function initOptions() {
    parent::initOptions();
  }
  function sqltype() {
    return 'text';
  }
  
}
?>
