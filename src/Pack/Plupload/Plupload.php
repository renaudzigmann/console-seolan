<?php
namespace Seolan\Pack\Plupload;
class Plupload extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return array('Plupload/public/plupload.full.min.js','Plupload/public/i18n/'.\Seolan\Core\Lang::get(\Seolan\Core\Shell::getLangData())['iso'].'.js');
  }
  function getJsAsyncIncludes() {
    return array();
  }
  function getCssIncludes(){
    return array('Plupload/public/style.css');
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
?>