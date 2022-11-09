<?php
namespace Seolan\Pack\Slider;
class Slider extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return array();
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
