<?php
namespace Seolan\Pack\CookieBar;
class CookieBar extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return array('CookieBar/public/jquery.cookiebar/jquery.cookiebar.js');
  }
  function getJsAsyncIncludes() {
    return array();
  }
  function getCssIncludes(){
    return array('CookieBar/public/jquery.cookiebar/jquery.cookiebar.css');
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
?>
