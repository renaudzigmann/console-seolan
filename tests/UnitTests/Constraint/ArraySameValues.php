<?php
namespace UnitTests\Constraint;

use \PHPUnit\Framework\Constraint\Constraint;

/**
 * Vérifie que 2 tableaux ont les mêmes valeurs indépendamment des ordres et des clés
 */

class ArraySameValues extends Constraint {
  private $arr1 = null;
  public function __construct($arr)  {
    parent::__construct(); // !!!
    $this->arr1 = $arr;
    $this->arr1;
    sort($this->arr1);
  }
  public function matches($arr2): bool {
    sort($arr2);
    return $this->arr1 == $arr2;
  }
  public function toString():string{
    $t = var_export($this->arr1, true);
    return "Le tableau doit contenir les mêmes valeurs (indépendament de l'ordre) que le tableau {$t}";
  }
  
}
