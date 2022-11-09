<?php
namespace UnitTests\Constraint;

use \PHPUnit\Framework\Constraint\Constraint;

/**
 * Vérifie qu'une colonne donnée existe bien dans la table
 */
class ColumnExists extends Constraint {
  public function __construct($table_name){
    parent::__construct(); //!! toujours bien faire appel au constructeur parent.
    $this->table_name=$table_name;
    
  }
  public function matches($other):bool{
    $dbC=getDB()->select("SHOW COLUMNS FROM ".$this->table_name." LIKE ? " ,[$other]);  
    if($dbC->rowCount()==0){
      return false;
    } else {
      return true;
    }
  }
  public function toString():string{
    return " is a SQL column of the Table: ".$this->table_name;
  }
  protected function additionalFailureDescription($other): string    {
    return "checked with the following SQL request: SHOW COLUMNS FROM ".$this->table_name." LIKE ".$other." ";
  }
}