<?php

namespace Seolan\Field\File;

use PDO;
use Seolan\Core\Labels;
use Seolan\Core\Shell;

/**
 * Données confidentielles à stockage temporaire
 * fichier monovalué, non traduisible
 * les fichiers sont supprimés par validation utilisateur ou automatiquement après validationDelay
 * conservation su statut et de la date de validation
 */

class ConfidentialData extends File {

  const STATUS = ['waiting', 'conform', 'invalid', 'expired'];

  public $validationDelay;
  public $dateQueryFormat;

  function __construct($obj = null) {
    if ($obj) {
      $obj->MULTIVALUED = false;
      $obj->TRANSLATABLE = false;
    }
    parent::__construct($obj);
    $this->usealt = false;
  }

  function initOptions() {
    parent::initOptions();
    $this->_options->setDefaultGroup(Labels::getTextSysLabel('Seolan_Core_Field_Field', '\seolan\field\file\confidentialdata'));
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_Field_Field', 'cd_validation_delay'), 'validationDelay', 'integer', [], 60);
    $date = new \Seolan\Field\Date\Date();
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Core_Field_Field','query_formats') . ' (date)', 'dateQueryFormat', 'list',
      ['labels' => $date->query_formats, 'values'=> $date->query_formats], 'classic');
  }

  // @TODO
  function my_getJSon($o, $options) {
    return parent::my_getJSon($o, $options);
  }

  public function isEmpty($r) {
    if (!empty($r->decoded_raw->cd)) {
      return false;
    }
    return parent::isEmpty($r);
  }

  public function my_browse(&$value, &$options, $genid = false) {
    $r = parent::my_browse($value, $options, $genid);
    if (!Shell::admini_mode() || $this->isEmpty($r)) {
      return $r;
    }
    if (empty($r->decoded_raw->cd->status)) {
      $r->html = Labels::getTextSysLabel('Seolan_Core_General', 'empty');
    } else {
      $status = $r->decoded_raw->cd->status;
      $r->html = Labels::getTextSysLabel('Seolan_Core_Field_Field', "cd_status_$status");
    }
    return $r;
  }

  function my_display_simple(&$value, &$options, $genid = false) {
    $r = parent::my_display_simple($value, $options, $genid);
    if (!Shell::admini_mode() || !empty($options['_edit']) || $this->isEmpty($r)) {
      return $r;
    }
    $status = $r->decoded_raw->cd->status;
    if (empty($status)) {
      $html = Labels::getTextSysLabel('Seolan_Core_General', 'empty');
    } else {
      $html = Labels::getTextSysLabel('Seolan_Core_Field_Field', "cd_status_$status");
      if ($status == 'waiting') {
        $dateLabel = Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar', 'until');
        $date = \Seolan\Field\Date\Date::printDate($r->decoded_raw->cd->date);
        $html .= ' ' . $dateLabel . ' ' . $date;
      } elseif ($r->decoded_raw->cd->date) {
        $date = \Seolan\Field\Date\Date::printDate($r->decoded_raw->cd->date) . substr($r->decoded_raw->cd->date, 10);
        $html .= ', ' . Labels::getTextSysLabel('Seolan_Core_Field_Field', 'cd_validation_date') . ' : ' . $date;
      }
    }
    $r->html = $html . '<br>' . $r->html;
    return $r;
  }

  function my_input(&$value, &$options, &$fields_complement = null) {
    if (!empty($options['editbatch'])) {
      return $this->my_edit_simple($value, $options, $fields_complement);
    }
    return parent::my_edit_simple($value, $options, $fields_complement);
  }

  function post_input($value, $options = null, &$fields_complement = null) {
    $options['_input'] = true;
    return parent::post_edit($value, $options, $fields_complement);
  }

  protected function my_edit_simple(&$value, &$options, &$fields_complement = null) {
    $options['_edit'] = true;
    $r = parent::my_edit_simple($value, $options, $fields_complement);
    if (!Shell::admini_mode()) {
      return $r;
    }
    if ($this->isEmpty($r) && empty($options['editbatch'])) {
      $r->html = '';
      return $r;
    }
    if (isset($options['intable'])) {
      $o = $options['intable'];
      $r->hiddenname = $this->field . '_HID[' . $o . ']';
    } elseif (!empty($options['fieldname'])) {
      $r->hiddenname = $options['fieldname'] . '_HID';
    } else {
      $r->hiddenname = $this->field . '_HID';
    }
    $status = $r->decoded_raw->cd->status ?? 'empty';
    $html = '<select name="' . $r->hiddenname . '[status]">';
    foreach (self::STATUS as $_status) {
      $selected = $status == $_status ? ' selected' : '';
      $disabled = !$selected && 'expired' == $_status ? ' disabled' : '';
      $html .= '<option value="' . $_status . '"' . $selected . $disabled . '>'
        . Labels::getTextSysLabel('Seolan_Core_Field_Field', "cd_status_$_status")
        . ($selected && $status == 'waiting' ? ' ' . Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar', 'until')
          . ' ' . \Seolan\Field\Date\Date::printDate($r->decoded_raw->cd->date) : '')
        . '</option>';
    }
    $html .= '</select><br>';
    $html .= '<input type="hidden" id="' . $r->varid . '-old" name="' . $r->hiddenname . '[old]" value="'
      . htmlentities($value, ENT_COMPAT, 'UTF-8') . '"/>';
    $r->html = $html . $r->disphtml;
    return $r;
  }

  protected function post_edit_simple($value, $options = null, &$fields_complement = null) {
    global $DATA_DIR;
    // cas d'un input, ajout status waiting et date d'expiration
    if (!Shell::admini_mode() || !empty($options['_input'])) {
      $r = parent::post_edit_simple($value, $options, $fields_complement);
      if (!$this->isEmpty($r)) {
        $json = json_decode($r->raw);
        $json->cd = ['status' => 'waiting', 'date' => date('Y-m-d', strtotime("+ $this->validationDelay DAYS"))];
        $r->raw = json_encode($json);
      }
      return $r;
    }
    $r = $this->_newXFieldVal($options);
    $hidden = $options[$this->field . '_HID'];
    $status = $hidden['status'];
    $oldvalue = json_decode($hidden['old']);
    $oldvalue->cd = $oldvalue->cd ?? (object) [];

    if ($status && $oldvalue->cd->status != $status) {
      //procEdit avec modification du statut
      $oldvalueStatus = $oldvalue->cd->status;
      $oldvalue->cd->status = $status;
      $oldvalue->cd->date = $status == 'waiting' ? date('Y-m-d', strtotime("+ $this->validationDelay DAYS")) : date('Y-m-d H:i:s');
      $this->trace($options['old'], $r, "status $oldvalueStatus => {$status}");
      if ($oldvalue->file && in_array($status, ['conform', 'invalid'])) {
        array_map('unlink', glob($DATA_DIR . $this->filename($oldvalue->file) . '*'));
        $oldvalue->file = '';
        $this->trace($options['old'], $r, 'file deleted');
      }
      $retval = json_encode($oldvalue);
    } elseif ($options['oidsrc'] && $options['oiddst']) {
      //duplication BO
      $retval = $hidden['old'];
    } else {
      //procEdit sans modif statut
      $retval = TZR_UNCHANGED;
    }
    $r->raw = $retval;
    return $r;
  }
  
  protected function copyExternalsToSimple($value,$oidsrc,$oiddst,$upd=null) {
    $retval = parent::copyExternalsToSimple($value,$oidsrc,$oiddst,$upd);
    if ($retval != TZR_UNCHANGED) {
      $json = json_decode($retval);
      if (is_object($json)) {
        $json->cd = ['status' => 'waiting', 'date' => date('Y-m-d', strtotime("+ $this->validationDelay DAYS"))];
        $retval = json_encode($json);
      }
    }
    return $retval;
  }

  function my_query($value, $options = null) {
    $r = $this->_newXFieldVal($options, true);
    $fname = isset($options['fieldname']) ? $options['fieldname'] : $this->field;
    $selected = $value['status'] == 'empty' ? ' selected' : '';
    $html = '<div class="cvx-cdfile">'
      . '<select name="' . $fname . '[status]" onchange="jQuery(this).siblings(\'.cddate\').toggle(this.value==\'waiting\');">'
      . '<option value="">---</option>'
      . '<option value="empty"' . $selected . '>' . Labels::getTextSysLabel('Seolan_Core_General', 'empty') . '</option>';
    foreach (self::STATUS as $_status) {
      $selected = $value['status'] == $_status ? ' selected' : '';
      $html .= '<option value="' . $_status . '"' . $selected . '>'
        . Labels::getTextSysLabel('Seolan_Core_Field_Field', "cd_status_$_status") . '</option>';
    }
    $html .= '</select>';
    if ($options['query_format'] == 'quick') {
      $date = new \Seolan\Field\Date\Date((object) ['FIELD' => $fname . '[date]', 'DPARAM' => ['query_format' => $this->dateQueryFormat]]);
      $dateHtml = preg_replace("/{$fname}\[date\]_op/", "{$fname}_op", $date->my_query($value['date'], $options)->html);
      $style = $value['status'] == 'waiting' ? '' : ' style="display:none;"';
      $html .= '<span class="cddate"' . $style . '>Date ' . $dateHtml . '</span>';
      $html .= '</div>';
    }
    $r->html = $html;
    $r->raw = $value;
    return $r;
  }

  function post_query($o, $ar) {
    if (!self::jsonExt()) {
      return $this->_post_query($o, $ar);
    }
    $conds = $qt = [];
    if ($o->value['status'] == 'empty') {
      $conds[] = "ifnull($o->field, '')='' or $o->field='TZR_unchanged' or JSON_VALUE($o->field, '$.cd.status') is null";
      $qt[] = Labels::getTextSysLabel('Seolan_Core_General', 'empty');
    } else {
      if (!empty($o->value['status'])) {
        $conds[] = $o->field . '!="TZR_unchanged"';
        $conds[] = 'JSON_VALUE(' . $o->field . ', "$.cd.status") = "' . $o->value['status'] . '"';
        $qt[] = Labels::getTextSysLabel('Seolan_Core_Field_Field', "cd_status_{$o->value['status']}");
      }
      if ($o->value['status'] == 'waiting' /*&& !empty($o->value['date'])*/) {
        $date = new \Seolan\Field\Date\Date((object) ['DPARAM' => ['query_format' => $this->dateQueryFormat]]);
        $dateQuery = $date->_newXFieldQuery();
        $dateQuery->field = 'JSON_VALUE(' . $o->field . ', "$.cd.date")';
        $dateQuery->value = $o->value['date'];
        $dateQuery->op = $o->op;
        $dateQuery->fmt = $o->fmt['date'];
        $date->post_query($dateQuery, $ar);
        if ($dateQuery->op == '=') {
          $dateQuery->rq = preg_replace('/=\'(.*)\'/', ' like "$1%"', $dateQuery->rq);
        }
        $conds[] = $dateQuery->rq;
        $dateQT = $date->getQueryText($dateQuery);
        if ($dateQT) {
          $qt[] = 'Date ' . $date->getQueryText($dateQuery);
        }
      }
    }
    if (!empty($conds)) {
      $o->rq = '(' . implode(' and ', array_unique(array_filter($conds))) . ')';
      $o->_qt = implode(', ', $qt);
    }
    return $o;
  }

  function _post_query($o, $ar) {
    $conds = $qt = [];
    if ($o->value['status'] == 'empty') {
      $like = '%"cd":{%"status":"%"%';
      $conds[] = "ifnull($o->field, '')='' or $o->field='TZR_unchanged' or $o->field not like '$like'";
      $qt[] = Labels::getTextSysLabel('Seolan_Core_General', 'empty');
    } else {
      if (!empty($o->value['status'])) {
        $conds[] = $o->field . '!="TZR_unchanged"';
        $like = '%"cd":{%"status":"' . $o->value['status'] . '"%';
        $conds[] = "$o->field like '$like'";
        $qt[] = Labels::getTextSysLabel('Seolan_Core_Field_Field', "cd_status_{$o->value['status']}");
      }
      if ($o->value['status'] == 'waiting' && !empty($o->value['date'])) {
        $date = new \Seolan\Field\Date\Date((object) ['DPARAM' => ['query_format' => $this->dateQueryFormat]]);
        $dateQuery = $date->_newXFieldQuery();
        $dateQuery->field = "SUBSTR($o->field, locate('\"date\":\"', $o->field) +8, 10)";
        $dateQuery->value = $o->value['date'];
        $dateQuery->op = $o->op;
        $dateQuery->fmt = $o->fmt['date'];
        $date->post_query($dateQuery, $ar);
        if ($dateQuery->op == '=') {
          $dateQuery->rq = preg_replace('/=\'(.*)\'/', ' like "$1%"', $dateQuery->rq);
        }
        $conds[] = $dateQuery->rq;
        $qt[] = 'Date ' . $date->getQueryText($dateQuery);
      }
    }
    if (!empty($conds)) {
      $o->rq = '(' . implode(' and ', array_unique($conds)) . ')';
      $o->_qt = implode(', ', $qt);
    }
    return $o;
  }

  public function getQueryText($o) {
    return $o->_qt;
  }

  // pas traduisible, au cas ou
  function data_duplicate($value, $langSrc, $langDest, $copy = false) {
    return TZR_UNCHANGED;
  }

  // mysql 5.7.8+, mariadb 10.2.3+
  protected static function jsonExt() {
    $version = getDB()->getAttribute(PDO::ATTR_SERVER_VERSION);
    preg_match('/^((\d+).(\d+).(\d+))-(((\d+).(\d+).(\d+))-MariaDB)?.*$/', $version, $matches);
    $mysql = $matches[1];
    $mariadb = $matches['6'];
    return (!empty($mariadb) && $mariadb >= '10.2.3') || $mysql >= '5.7.8';
  }

  protected function chkSimple(&$messages) {
    $this->checkDiskImages($messages);
    $this->checkExpiration($messages);
  }

  protected function checkExpiration(&$messages) {
    global $DATA_DIR;
    if (self::jsonExt()) {
      $lines = getDB()->select(
          "select koid, $this->field from $this->table "
          . "where lang=? "
          . "and ifnull($this->field, '')!='' "
          . "and $this->field != 'TZR_unchanged' "
          . "and (JSON_VALUE($this->field, '$.cd.status') is null "
          . "  or (JSON_VALUE($this->field, '$.cd.status') = 'waiting' and JSON_VALUE($this->field, '$.cd.date') < curDate()))",
          [TZR_DEFAULT_LANG])->fetchAll(PDO::FETCH_KEY_PAIR);
    } else {
      $like = '%"cd":{%"status":"waiting"%';
      $lines = getDB()->select(
          "select koid, $this->field from $this->table "
          . "where lang=? "
          . "and ifnull($this->field, '')!='' "
          . "and $this->field != 'TZR_unchanged' "
          . "and ($this->field not like '%status%' "
          . "  or ($this->field like '$like' and SUBSTR($this->field, locate('\"date\":\"', $this->field) +8, 10) < curDate()))",
          [TZR_DEFAULT_LANG])->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    foreach ($lines as $oid => $value) {
      $json = json_decode($value);
      $json->cd->status = 'expired';
      $json->cd->date = date('Y-m-d H:i:s');
      array_map('unlink', glob($DATA_DIR . $this->filename($json->file) . '*'));
      $json->file = '';
      getDB()->execute(
        'UPDATE ' . $this->table . ' SET UPD=UPD, ' . $this->field . '=? where koid=?', [json_encode($json), $oid]);
    }
  }

}
