<?php
/**
 * classe des daemons applications
 */
namespace Seolan\Core\Application;
use \Seolan\Core\Module\Module;
/**
  * singleton positionné lors de l'exéction des daemons des modules
  * dans le contexte d'une application
  */
class Daemon{
  static private $_instance = null;
  private $_processedModules = ['any'=>[],'daily'=>[]];
  private $_application = null;
  private $_module = null;
  public static function running(){
    return static::$_instance != null
        && static::$_instance->_module != null
        && static::$_instance->_application != null;
  }
  public static function getInstance():Daemon{
    if (static::$_instance == null)
      static::$_instance = new Daemon();
    return static::$_instance;
  }
  private function __construct(){}
  public function setApplication(Application $application){
    $this->_application = $application;
  }
  public function getApplication(){
    return $this->_application;
  }
  public function getProcessedModules($period):array{
    return $this->_processedModules[$period];
  }
  public function addProcessedModule(string $period){
    $moid = $this->_module->_moid;
	  if (!isset($this->_processedModules[$period][$moid]))
	    $this->_processedModules[$period][$moid] = [];
	  $this->_processedModules[$period][$moid][] = $this->_application->oid;
  }
  public function setModule(Module $mod){
    $this->_module = $mod;
  }
  public function getModule():Module{
    return $this->_module;
  }
  public function run($period){
    if ($this->_module->applicationDaemon($period))
       $this->addProcessedModule($period);

  }
}
