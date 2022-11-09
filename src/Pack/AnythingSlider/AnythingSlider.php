<?php
namespace Seolan\Pack\AnythingSlider;
class AnythingSlider extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return array('js/jquery-anythingslider/js/jquery.anythingslider.min.js');
  }
  function getJsAsyncIncludes() {
    return array();
  }
  function getCssIncludes(){
    return array(
      'js/jquery-anythingslider/css/anythingslider.css',
      'css8/section-slider.css',
    );
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
?>
