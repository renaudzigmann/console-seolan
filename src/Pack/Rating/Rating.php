<?php
namespace Seolan\Pack\Rating;
class Rating extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    if (defined('TZR_DEBUG_MODE'))
      return array('Rating/public/jquery.rating.js');
    else
      return array('Rating/public/jquery.rating.pack.js');
  }
  function getJsAsyncIncludes() {
    return array();
  }
  function getCssIncludes(){
    return array('Rating/public/jquery.rating.css');
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
?>
