<?php
namespace Seolan\Pack\NyroModal2;
class NyroModal2 extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return array('NyroModal2/public/js/jquery.nyroModal.custom.min.js');
  }
  function getJsAsyncIncludes() {
    return array();
  }
  function getCssIncludes(){
    return array('NyroModal2/public/styles/nyroModal.css');
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
?>