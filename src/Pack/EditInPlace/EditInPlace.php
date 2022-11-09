<?php
namespace Seolan\Pack\EditInPlace;
class EditInPlace extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return array('js/jquery.jeditable.js');
  }
  function getJsAsyncIncludes() {
    return array();
  }
  function getCssIncludes(){
    return array();
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
?>