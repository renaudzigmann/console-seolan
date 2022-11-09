<?php
namespace Seolan\Field\Date;
/**
 * méthodes utilitaires communes aux dates (Date, Date/Heure, Timestamp)
 */
use \Seolan\Core\Labels;

trait DateTrait {
  function getQueryText($o){
    if($o->op == 'now') {
      $ret=$this->getQueryTextOp($o->op,true);
    } else {
      $ret = parent::getQueryText($o);
    }
    return $ret;
  }
  function getQueryTextOp($op,$addscape=false){
    $ret = '';
    switch($op){
      case 'now':
	$ret = Labels::getTextSysLabel('Seolan_Core_Field_Field','today').' ';
	break;
      case '<':
	$ret = Labels::getTextSysLabel('Seolan_Core_Field_Field','date_before').' ';
	break;
      case '>':
	$ret = Labels::getTextSysLabel('Seolan_Core_Field_Field','date_after').' ';
	break;
      default:
	$ret = parent::getQueryTextOp($op, false);
    }
    if($addscape && $ret) $ret.=' ';
    return $ret;
  }
  /// Vérifie si une date est valide
  public static function dateIsValid($date, $format){
    $d = \DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
  }
}
