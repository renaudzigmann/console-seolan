<?php
namespace Seolan\Pack\ContextMenu;
class ContextMenu extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }

  function getJsIncludes() {
    return array('ContextMenu/public/jquery-contextmenu/jquery.contextMenu.js');
  }

  function getJsAsyncIncludes() {
    return array();
  }
  function getCssIncludes(){
    return array('ContextMenu/public/jquery-contextmenu/jquery.contextMenu.css');
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
?>
