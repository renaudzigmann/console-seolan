<?php
namespace Seolan\Module\Sitra;

class SitraTypeSetDef extends \Seolan\Field\StringSet\StringSet {
  protected $values;

  protected function getComputedValues() {
    if(\Seolan\Core\System::fieldExists('SIT_OBJETSTOURISTIQUES', 'type')) {
      return getDB()->fetchCol("SELECT DISTINCT type FROM SIT_OBJETSTOURISTIQUES WHERE type <> ''");
    }
  }

  protected function getValues() {
    if ($this->types === Null) {
      $this->types = $this->getComputedValues();
    }
    return $this->types;
  }

  protected function getChoices() {
    return array_combine($this->getValues(), $this->getValues());
  }

  protected function getChoicesOids($options, $msg) {
    return $this->getValues();
  }

  public function getTextFromSoid($soid,$lang=NULL) {
    return $this->getChoices()[$soid];
  }

}
