<?php

namespace UnitTests\Constraint;

use \Seolan\Core\DataSource\DataSource;
use \PHPUnit\Framework\Constraint\Constraint;

/**
 * Vérifie qu'un champ n'existe plus 
 * en regardant qu'il n'existe plus dans les  3 tables: la table du champ, DICT, MSGS
 * enfin on regarde que l'assertion FieldExiste renvoie False
 */
class FieldIsDeleted extends Constraint
{
  private $ds = null;
  public function __construct($ds)
  {
    parent::__construct();
    $this->ds = $ds;
    $this->table_name = $ds->getTable();
  }
  public function matches($other): bool
  {
    list($ok, $messerr) = $this->VerifyFieldNotExistInThe3Bases($other);
    return (is_string($other) && $ok);
  }
  public function verifyFieldNotExistInThe3Bases($other)
  {
    $dbC = getDB()->select("SHOW COLUMNS FROM " . $this->table_name . " LIKE ? ", [$other]);
    if ($dbC->rowCount() > 0) {
      $errorString = "Field $other still exist in Table: {$this->table_name}";
      return [false, $errorString];
    }

    $dbC = getDB()->select("Select * from DICT WHERE DTAB = ? AND FIELD= ? ", [$this->table_name, $other]);
    if ($dbC->rowCount() > 0) {
      $errorString = "Field {$other} still exist in Table: DICT";
      return [false, $errorString];
    }

    $dbC = getDB()->select("Select * from MSGS WHERE MTAB = ? AND FIELD= ? ", [$this->table_name, $other]);
    if ($dbC->rowCount() > 0) {
      $errorString = "Field {$other} still exist in Table: MSGS";
      return [false, $errorString];
    }
    if ($this->ds->fieldExists($other)) {
      $errorString = "Field {$other} still exist checked with 'DataSource::fieldExists'";
      return [false, $errorString];
    }


    return [true, null];
  }
  public function toString(): string
  {
    return "is deleted";
  }
  protected function additionalFailureDescription($other): string
  {
    list($ok, $messerr) =  $this->VerifyFieldNotExistInThe3Bases($other);
    return  $messerr;
  }
}
