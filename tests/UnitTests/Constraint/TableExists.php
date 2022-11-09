<?php
namespace UnitTests\Constraint;

use \Seolan\Core\System;
use \PHPUnit\Framework\Constraint\Constraint;

/**
 * https://github.com/sebastianbergmann/phpunit/blob/master/src/Framework/Constraint/Constraint.php
 * Vérifie qu'une table SQL existe
 */
class TableExists extends Constraint {
  private $op = true;
  public function __construct($op){
    parent::__construct(); // !!
    $this->op = $op;
  }
  public function matches($other):bool{
    return (is_string($other) && (System::tableExists($other, true) == $this->op));
  }
  public function toString():string{
    return $this->op?"sql table exists":"sql table not exists";
  }
  protected function additionalFailureDescription($other): string    {
    return "SQL table checked with 'System::tableExists'";
  }
}
