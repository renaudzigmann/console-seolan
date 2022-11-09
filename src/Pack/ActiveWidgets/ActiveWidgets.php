<?php
namespace Seolan\Pack\ActiveWidgets;
class ActiveWidgets extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return array('../active-widgets/lib/grid.js');
  }
  function getJsAsyncIncludes() {
    return array();
  }
  function getCssIncludes(){
    return array();
  }
  function getHeader() {
    $txt="<link href=\"/tzr/active-widgets/styles/classic/grid.css\" rel=\"stylesheet\" type=\"text/css\" />";
    return $txt;
  }
  function getHeader2() {
    return "";
  }
}
?>