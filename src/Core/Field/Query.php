<?php
namespace Seolan\Core\Field;
class Query {
  public $op=NULL;
  public $value=NULL;
  public $fmt=NULL;
  public $hid=NULL;
  public $par=NULL;
  public $quote="'";
  function __construct() {
  }
  /// Appel une fonction du xfielddef
  public function __call($f,$param){
    array_unshift($param, $this);
    return call_user_func_array(array($this->fielddef,$f),$param);
  }
}
