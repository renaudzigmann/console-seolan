<?php
namespace Seolan\Pack\PictureFill;
class PictureFill extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return array();
  }
  function getJsAsyncIncludes() {
    return array('js/picturefill.min.js');
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
