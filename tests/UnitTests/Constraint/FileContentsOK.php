<?php
namespace UnitTests\Constraint;

use \PHPUnit\Framework\Constraint\Constraint;

/**
 * Vérifie qu'un fichier (Field/Value) contient un contenu donné
 */

class FileContentsOK extends Constraint {
  private $fieldValue = null;
  public function __construct(\Seolan\Core\Field\Value $value)  {
    parent::__construct(); // !!!
    $this->fieldValue = $value;
  }
  // 
  public function matches($contents): bool {
    if (!$file_exists($this->fieldValue->filename))
      return false;
    $actual = file_get_contents($this->fieldValue->filename);
    return $actual == $contents;
  }
  public function toString(): string  {
    return "file {$this->fieldValue->fielddef->target} {$this->fieldValue->fielddef->field} contents expected value"; 
  }
  
}
