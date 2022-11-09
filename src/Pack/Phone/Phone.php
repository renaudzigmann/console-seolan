<?php
namespace Seolan\Pack\Phone;

use Seolan\Core\PackageInterface;
use Seolan\Pack\Core\AbstractPack;

class Phone extends AbstractPack implements PackageInterface {

  function getJsIncludes() {
    return ['Phone/public/js/intlTelInput.min.js', 'Phone/public/js/init.js'];
  }

  function getJsAsyncIncludes() {
    return [];
  }

  function getCssIncludes(){
    return ['Phone/public/css/intlTelInput.min.css'];
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
