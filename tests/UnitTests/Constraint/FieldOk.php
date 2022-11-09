<?php

namespace UnitTests\Constraint;


use \PHPUnit\Framework\Constraint\Constraint;

/**
 * Vérifie que lorsqu'on crée un champ, celui-ci est bien présent dans la table SQL et configuré dans DICT et MSGS.
 */

class FieldOk extends Constraint
{
  public function __construct($table_name)
  {
    parent::__construct(); //!! toujours bien faire appel au constructeur parent.
    $this->table_name = $table_name;
  }
  public function matches($other): bool
  {
    list($ok, $messerr) = $this->verifyFieldExistInThe3Bases($other);
    return $ok; //matches doit retournée true or false donc on  doit récupérer que le première élement du tableau.
  }
  public function verifyFieldExistInThe3Bases($other)
  {

    if (is_array($other)) {
      list($fieldname, $propsToCheck) = $other;
    } elseif (is_string($other)) {
      $fieldname = $other;
      $propsToCheck = [];
    } else {
      return [false, 'invalid parameters'];
    }
    $dbC = getDB()->select("SHOW COLUMNS FROM " . $this->table_name . " LIKE ? ", [$fieldname]);
    if ($dbC->rowCount() == 0) {
      $errorString = "field $fieldname doesn't exist in Table: {$this->table_name}";
      return [false, $errorString];
    }

    $dbC = getDB()->select("Select * from DICT WHERE DTAB = ? AND FIELD= ? ", [$this->table_name, $fieldname]);
    if ($dbC->rowCount() == 0) {
      $errorString = "field {$fieldname} doesn't exist in table DICT";
      return [false, $errorString];
    }

    $dbC = getDB()->select("Select * from MSGS WHERE MTAB = ? AND FIELD= ? ", [$this->table_name, $fieldname]);
    if ($dbC->rowCount() == 0) {
      $errorString = "field {$fieldname} doesn't exist in table MSGS";
      return [false, $errorString];
    }
    // on vérifie les valeurs des prop. des champs
    if (isset($propsToCheck['labels'])) {
      foreach ($propsToCheck['labels'] as $lang => $label) {
        $exists = getDB()->fetchOne("Select 1 from MSGS WHERE MTAB = ? AND FIELD= ? and MLANG=? and MTXT = ?", [$this->table_name, $fieldname, $lang, $label]);
        if (!$exists) {
          $errorString = "label '{$label}' does not exists in table MSGS for field '{$fieldname}' and lang code '{$lang}'";
          return [false, $errorString];
        }
      }
    }

    return [true, null];
  }

  public function toString(): string
  {
    return " is correctly configured ({$this->table_name})";
  }
  protected function additionalFailureDescription($other): string
  {
    list($ok, $messerr) =  $this->verifyFieldExistInThe3Bases($other);  //pour voir le message détaille on refait le calcule.
    return $messerr;
  }
}
