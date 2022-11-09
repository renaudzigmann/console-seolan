<?php
namespace Seolan\Pack\SlickGrid;
class Slickgrid extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }

  function getJsIncludes() {
    return array(
      'SlickGrid/public/SlickGrid-2.4.29/lib/jquery.event.drag-2.3.0.js',
      'SlickGrid/public/SlickGrid-2.4.29/slick.core.js',
      'SlickGrid/public/SlickGrid-2.4.29/slick.editors.js',
      'SlickGrid/public/SlickGrid-2.4.29/slick.grid.js',
      'SlickGrid/public/SlickGrid-2.4.29/plugins/slick.cellrangedecorator.js',
      'SlickGrid/public/SlickGrid-2.4.29/plugins/slick.cellrangeselector.js',
      'SlickGrid/public/SlickGrid-2.4.29/plugins/slick.cellexternalcopymanager.js',
      'SlickGrid/public/SlickGrid-2.4.29/plugins/slick.cellselectionmodel.js',
    );
  }

  function getJsAsyncIncludes() {
    return array();
  }

  function getCssIncludes(){
    return array(
      'SlickGrid/public/SlickGrid-2.4.29/slick.grid.css',
      'SlickGrid/public/SlickGrid-2.4.29/slick-default-theme.css',
      'SlickGrid/public/contextmenu.css',
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
