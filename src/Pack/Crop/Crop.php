<?php
namespace Seolan\Pack\Crop;
class Crop extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return array('Crop/public/jquery.Jcrop.min.js');
  }
  function getJsAsyncIncludes() {
    return array();
  }
  function getCssIncludes(){
    return array('Crop/public/jquery.Jcrop.css');
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
?>