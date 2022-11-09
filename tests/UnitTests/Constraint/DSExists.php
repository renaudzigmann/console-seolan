<?php
namespace UnitTests\Constraint;

use \Seolan\Core\DataSource\DataSource;
use \PHPUnit\Framework\Constraint\Constraint;
/**
 * https://github.com/sebastianbergmann/phpunit/blob/master/src/Framework/Constraint/Constraint.php
 */
class DSExists extends Constraint {
  private $op = true;
  public function __construct($op){
    parent::__construct(); // !!
    $this->op = $op;
  }
  public function matches($other):bool{
    return (is_string($other) && (DataSource::sourceExists($other,true) == $this->op));
  }
  public function toString():string{
    return $this->op?"datasource exists":"datasource not exists";
  }
  /**
   * ? check sql table, dict, basebase msgs ?
   */
  protected function additionalFailureDescription($other): string    {
    return "Datasource checked with 'DataSource::sourceExists'";
  }
}
