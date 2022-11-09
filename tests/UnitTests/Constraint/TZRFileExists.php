<?php
namespace UnitTests\Constraint;
use \PHPUnit\Framework\Constraint\Constraint;
/**
 * Vérifie qu'un fichier (Field/Value) existe
 * à voir mono et multi, fonctionnent pas pareil
 */
class TZRFileExists extends Constraint {
  private $fieldValue = null;
  private $details = null;
  public function __construct(\Seolan\Core\Field\Value $value, ?string $details=null) { 
    parent::__construct(); // !!!
    $this->fieldValue = $value;
    $this->details = $details;
  }
  //
  public function matches($op=true): bool {
    if ($this->fieldValue->fielddef->multivalued){
      if ($op === true)
	return file_exists($this->fieldValue->dirname);
      else
	return !file_exists($this->fieldValue->dirname);
    } else {
      if ($op === true)
	return file_exists($this->fieldValue->filename);
      else
	return !file_exists($this->fieldValue->filename);
    }
  }
  public function toString(): string  {

    if ($this->fieldValue->fielddef->multivalued)
      $path = "multivalued '{$this->fieldValue->dirname}'";
    else
      $path = "monovalued '{$this->fieldValue->filename}'";
    
    return "file {$path} ".$this->details??'exists/not exists';
  }  
}
