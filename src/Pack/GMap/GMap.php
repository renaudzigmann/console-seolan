<?php
namespace Seolan\Pack\GMap;
class GMap extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return array('GMap/public/markerclusterer.js',
                 'GMap/public/jquery.seolanmap.js');
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
