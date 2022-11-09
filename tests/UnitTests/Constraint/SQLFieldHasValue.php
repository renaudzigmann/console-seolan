<?php

namespace UnitTests\Constraint;

use \PHPUnit\Framework\Constraint\Constraint;

/**
 * Vérifie qu'un champ doné contient la valeur demandé.
 */

class SQLFieldHasValue extends Constraint
{
  public function __construct($field_name, $table_name, $and = null)
  {
    parent::__construct();
    $this->field_name = $field_name;
    $this->table_name = $table_name;
    $this->and = $and;
  }
  public function matches($other): bool
  {

    if ($this->and == null) {
      $db_c = getDB()->select("SELECT ? FROM {$this->table_name} WHERE {$this->field_name} = ?   ", [$this->field_name, $other]); //$other=$field_value;
    } else {
      $db_c = getDB()->select("SELECT ? FROM {$this->table_name} WHERE {$this->field_name} = ? AND " . $this->and, [$this->field_name, $other]); //$other=$field_value;

    }

    if ($db_c->rowCount() == 0) {
      return false;
    } else {
      return true;
    }
  }

  public function toString(): string
  {
    return "is a value of SQL field " . $this->field_name;
  }
  protected function additionalFailureDescription($other): string
  {
    if ($this->and == null) {
      return "Check with the following SQL request: SELECT " . $this->field_name . "
          FROM  " . $this->table_name . "
          WHERE " . $this->field_name . " = '" . $other . "'  ";
    } else {
      return "Check with the following SQL request: SELECT " . $this->field_name . "
          FROM  " . $this->table_name . "
          WHERE " . $this->field_name . " = '" . $other . "' AND " . $this->and;
    }
  }
}
