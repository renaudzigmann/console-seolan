<?php
/**
 * classe de base pour les packs 
 */
namespace Seolan\Pack\Core;
abstract class AbstractPack {
  public static $rootpath = TZR_WWW_CSX.'src/Pack/';
  public static function ressourcePath(){
    return static::$rootpath.implode('/', array_slice(explode('\\', static::class), 2, -1)).'/public/';
  }
  /**
   * repertoire des de ressource des js par defaut
   */
  public function getJSRootPath(){
    return static::$rootpath;
  }
  public function getJSAsyncRootPath(){
    return static::$rootpath;
  }
  public function getCSSRootPath(){
    return static::$rootpath;
  }
}