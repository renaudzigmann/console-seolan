<?php
namespace Seolan\Pack\Leaflet;
class Leaflet extends \Seolan\Pack\Core\AbstractPack implements \Seolan\Core\PackageInterface {
  function __construct() {
  }
  function getJsIncludes() {
    return ['Leaflet/public/js/leaflet.min.js', 'Leaflet/public/js/control.geocoder.js','Leaflet/public/js/leaflet.markercluster.js','Leaflet/public/js/leaflet.omnivore.min.js','Leaflet/public/js/leaflet-gesture-handling.min.js'];
  }
  function getJsAsyncIncludes() {
    return [];
  }
  function getCssIncludes(){
    return ['Leaflet/public/css/leaflet.css', 'Leaflet/public/css/MarkerCluster.css', 'Leaflet/public/css/MarkerCluster.Default.css','Leaflet/public/css/leaflet-gesture-handling.css'];
  }

  function getHeader() {
    return '';
  }

  function getHeader2() {
    return '';
  }
}
