<?php
namespace Seolan\Pack\DatePicker;
class DatePicker extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  /*
   * requires : 'js/jquery-ui/jquery-ui.min.js',
   */
  function getJsIncludes() {
    $lang = \Seolan\Core\Lang::get(\Seolan\Core\Shell::getLangData())['iso'];
    return [
	    'DatePicker/public/jquery.ui.datepicker-'.$lang.'.js',
	    'DatePicker/public/date-'.$lang.'.js',
	    'DatePicker/public/daterangepicker.jQuery-'.$lang.'.js',
	    //'DatePicker/public/daterangepicker.jQuery.js'
	    ];
  }
  function getJsAsyncIncludes() {
    return [];
  }
  function getCssIncludes(){
    return [];
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
?>
