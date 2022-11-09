<?php
namespace Seolan\Pack\NyroModal;
class NyroModal extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return array('js/nyroModal/jquery.nyroModal.js');
  }
  function getJsAsyncIncludes() {
    return array();
  }
  function getCssIncludes(){
    return array('js/nyroModal/css/nyroModal.full.css');
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
?>