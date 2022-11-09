<?php
namespace UnitTests\Constraint;

use \seolan\Core\Kernel;
use \PHPUnit\Framework\Constraint\Constraint;
/**
 * https://github.com/sebastianbergmann/phpunit/blob/master/src/Framework/Constraint/Constraint.php
 */
class ObjectExists extends Constraint {
  private $op = true;
  public function __construct($op){
    parent::__construct(); // !!
    $this->op = $op;
  }
  public function matches($other):bool{
    return (is_string($other) && (Kernel::objectExists($other) == $this->op));
  }
  public function toString():string{
    return $this->op?"object exists":"object not exists";
  }
  /**
   * ? check sql table, dict, basebase msgs ?
   */
  protected function additionalFailureDescription($other): string    {
    return "Field checked with 'Kernel::objectExists'";
  }
}