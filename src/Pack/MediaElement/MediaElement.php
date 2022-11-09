<?php
namespace Seolan\Pack\MediaElement;
class MediaElement extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {}
  function getJsIncludes() {
    if (defined('TZR_DEBUG_MODE'))
      return ['MediaElement/public/mediaelement-and-player.js'];
    else
      return ['MediaElement/public/mediaelement-and-player.min.js'];
  }
  function getJsAsyncIncludes() {
    return array();
  }
  function getCssIncludes(){
    return ['MediaElement/public/mediaelementplayer.css', 'MediaElement/public/mejs-skins.css'];
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
?>