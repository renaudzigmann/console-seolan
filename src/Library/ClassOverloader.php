<?php
namespace Seolan\Library;

/**
 * @author Bastien Sevajol <bastien.sevajol@xsalto.com>
 */
class ClassOverloader {
  
  protected $object;
  
  public function __construct($object) {
    $this->object = $object;
  }
  
  public function __call($name, $arguments) {
    return call_user_func_array(array($this->object, $name), $arguments);
  }
  
  public function __set($name , $value){
    $this->object->$name = $value;
  }
  
  public function __get($name){
    return $this->object->$name;
  }
  
  public function __isset($name){
    return isset($this->object->$name);
  }

  public function __unset($name){
    unset($this->object->$name);
  }
  
  public static function __callStatic($name, $arguments){
    return forward_static_call_array(array($this->object, $name), $arguments);
  }
  
  public function _getOverloadedObject(){
    return $this->object;
  }
  
}
