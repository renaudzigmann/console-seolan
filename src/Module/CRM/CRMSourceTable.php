<?php

namespace Seolan\Module\CRM;

/**
 * Table implémentant CRMSourceInterface
 * champ mail et champs à consolider en option
 */
class CRMSourceTable extends \Seolan\Module\Table\Table implements CRMSourceInterface {

  public $CRMEmail;
  public $CRMFields;
  public $CRMTypes;

  public function initOptions() {
    parent::initOptions();
    if (\Seolan\Core\Module\Module::getMoid(XMODCRM2_TOID)) {
      $this->_options->setOpt('Champ Email', 'CRMEmail', 'field', ['type' => '\Seolan\Field\ShortText\ShortText'], '', 'Source CRM');
      $this->_options->setOpt('Champs à consolider', 'CRMFields', 'field', ['multivalued' => true], '', 'Source CRM');
      $this->_options->setOpt('Type de contact', 'CRMTypes', 'multiplelist', [
        'labels' => ['Marketing', 'Commercial', 'Technique'],
        'values' => ['Marketing', 'Commercial', 'Technic']], '', 'Source CRM');
      $this->_options->setComment('Type tous les contacts de ce module', 'CRMTypes');
    }
  }

  public function getCRMFields() {
    $fields = [];
    foreach ($this->CRMFields as $fieldName) {
      if (!in_array($fieldName, ['Marketing', 'Commercial', 'Technic'])) {
        $fields[$fieldName] = $this->xset->desc[$fieldName];
      }
    }
    return $fields;
  }

  public function getCRMEmails($since = TZR_DATETIME_EMPTY) {
    if (empty($this->CRMEmail)) {
      throw new \Exception('CRMEmail option not set in module ' . $this->getLabel());
    }
    $filter = [$this->getFilter()];
    $filter[] = "ifnull($this->CRMEmail, '') != '' and $this->CRMEmail != 'xxx'";
    $filter[] = "UPD >= '$since'";
    return getDB()->fetchCol(
      "select distinct {$this->CRMEmail} from {$this->table} where " . implode(' and ', array_filter($filter)));
  }

  public function getCRMContactInfos($email) {
    $filter = $this->getFilter();
    $rows = getDB()->fetchAll(
      "select * from {$this->table} where " . ($filter ? "$filter and" : '') . " {$this->CRMEmail}=?", [$email]);
    if (empty($rows)) {
      return null;
    }
    $contact = [];
    foreach ($rows as $row) {
      foreach ($this->CRMFields as $fieldName) {
        if (in_array($fieldName, ['Marketing', 'Commercial', 'Technic'])) {
          $contact[$fieldName] = $row[$fieldName] == 1;
          continue;
        }
        if (empty($contact[$fieldName])) {
          $contact[$fieldName] = $row[$fieldName];
        }
      }
      foreach ($this->CRMTypes as $type) {
        $contact[$type] = true;
      }
      $contact['Sources'][] = $row['KOID'];
    }
    $contact['_rows'] =  $rows; // pour surcharge
    return $contact;
  }

}
