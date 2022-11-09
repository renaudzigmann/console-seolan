<?php

namespace UnitTests\Constraint;

use \PHPUnit\Framework\Constraint\Constraint;

/**
 * Vérifie qu'une ligne existe et qu'elle contient bien les colonnes avec les valeurs démandées
 * La ligne est supposée identifiée par les colonnes de la clé primaire de la table. Dans cette classe la clé primaire est un tableau contenue dans la variable $other
 * Donc, si plusieurs lignes correspondent, c'est considéré comme une erreur
 * Les valeurs des colonnes à vérifier sont passées sans un tableau associatif 'nom de colonne'=>'valeurs attentdue'
 */

class SQLRowIsOk extends Constraint
{
  public function __construct($table_name, $ColumnsAndExpectedValues)
  {
    parent::__construct();
    $this->table_name = $table_name;
    $this->ColumnsAndExpectedValues = $ColumnsAndExpectedValues; //les valeurs attendue

  }
  public function matches($other): bool
  {
    list($ok, $messerr) = $this->verifyRow($other);
    return $ok; //matches doit retournée true or false donc on  doit récupérer que le première élement du tableau.
  }
  public function verifyRow($other)
  {


    $request = "SELECT  * FROM {$this->table_name} WHERE ";
    $param = [];
    foreach ($other as $column => $value) {  //other = des clés primaires
      $request .= $column . " = :" . $column . " AND ";
      $param[$column] = $value;
    }
    $request = substr($request, 0, -4);
    $dbc = getDB()->select($request, $param);


    if ($dbc->rowCount() == 0) {
      $errorString = "The target line doesn't exist in the table : " . $this->table_name;
      return [false, $errorString];
    } elseif ($dbc->rowCount() > 1) {
      $errorString = "The parameters passed as primary key are not unique. They match with " . $dbc->rowCount() . " lines";
      return [false, $errorString];
    }

    $dbc = $dbc->fetch();
    foreach ($this->ColumnsAndExpectedValues as $column => $value) {
      if (!array_key_exists($column, $dbc)) {
        $errorString = "The column " . $column . " doesn't exist in table : " . $this->table_name;
        return [false, $errorString];
      }
      if (is_array($value)) {
        $string = '||';
        foreach ($value as $val) {
          $string .= $val . '||';
        }
        $value = $string;
      }
      if ($dbc[$column] != $value) {
        $errorString = "The column " . $column . " does not have the expected value for column. Values in the data base: " . $dbc[$column] . ", expected value : " . $value;
        return [false, $errorString];
      }
    }

    return [true, null];
  }

  public function toString(): string
  {
    return "is in table : " . $this->table_name;
  }
  protected function additionalFailureDescription($other): string
  {
    list($ok, $messerr) = $this->verifyRow($other);
    return $messerr;
  }
}
