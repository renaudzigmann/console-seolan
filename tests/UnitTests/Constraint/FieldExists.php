<?php
namespace UnitTests\Constraint;

use \Seolan\Core\DataSource\DataSource;
use \PHPUnit\Framework\Constraint\Constraint;
/**
 * https://github.com/sebastianbergmann/phpunit/blob/master/src/Framework/Constraint/Constraint.php
 */
class FieldExists extends Constraint {
  private $op = true;
  private $ds = null;
  public function __construct($ds, $op){
    parent::__construct(); // !!
    $this->op = $op;
    $this->ds = $ds;
  }
  public function matches($other):bool{
    return (is_string($other) && ($this->ds->fieldExists($other) == $this->op));
  }
  public function toString():string{
    return $this->op?"field exists":"field not exists";
  }
  /**
   * ? check sql table, dict, basebase msgs ?
   */
  protected function additionalFailureDescription($other): string    {
    return "Field checked with 'DataSource::fieldExists'";
  }
}
