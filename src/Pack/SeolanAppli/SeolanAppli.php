<?php

namespace Seolan\Pack\SeolanAppli;
class SeolanAppli extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  
  function getHeader() {
    return '';
  }
  
  function getJsIncludes() {
    return [
      'SeolanAppli/public/seolan_appli.js',
    ];
  }
  
  function getJsAsyncIncludes() {
    return [];
  }
  
  function getCssIncludes() {
    return [];
  }
  
  function getHeader2() {
    return '';
  }
}
