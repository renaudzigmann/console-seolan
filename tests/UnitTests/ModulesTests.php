<?php
namespace UnitTests;
use \Seolan\Core\Module\Module;
/**
 * tests sur les modules 
 * -lecture de la configuration du module
 */
class ModulesTests extends BaseCase {
  public function initCase($name){
    parent::initCase($name);
  }
  public static function setUpBeforeClass(){
    parent::setUpBeforeClass();
    if (!file_exists("{$GLOBALS['LOCALLIBTHEZORRO']}config")){
       throw new \Exception("{$GLOBALS['LOCALLIBTHEZORRO']}config existe pas");
    }
    // save d'une conf éventuelle, restaurée en clearfixtures
    static::trace("backup {$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php ");
    if (file_exists("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php")
	&& !file_exists("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php.back.tu")
	){
      copy("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php",
	   "{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php.back.tu");
    }
    
  }
  /**
   * chargement de la conf. sur un module toujours présent
   * manque accès par moid versus par toid
   */
  public function testLoadConfig(){

    $moidlog = getDB()->fetchOne("select moid from MODULES where toid=?", [XMODMAILLOGS_TOID]);

    $confFile = "{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php";
    $valoption1 = 'tests option 1 (toid infotree)';
    $valoption2 = 'tests options 1 (toid maillog)';
    $valoption3 = 'tests options maillog moid';
    $conf = var_export([
      'toid:'.XMODBACKOFFICEINFOTREE_TOID=>['options'=>['option1'=>$valoption1]],
      'toid:'.XMODMAILLOGS_TOID=>['options'=>['option1'=>$valoption2]],
      "{$moidlog}"=>['options'=>['option1'=>$valoption3]]
    ], true);
    
    $conf = "<?php\n return {$conf};\n?>";
    static::trace($conf);
    file_put_contents($confFile,$conf);

    // !! recharger les modules après mise en place de la conf
    Module::clearCache();
    $modit = Module::singletonFactory(XMODBACKOFFICEINFOTREE_TOID);
    $modlog = Module::singletonFactory(XMODMAILLOGS_TOID);
    
    $this->assertEquals($valoption1, $modit->getConfigurationOption('option1'), "lecture d'une option de confguration");

    $this->assertEquals(null, $modit->getConfigurationOption('option2'), "lecture d'une option de confguration manquante");

    $this->assertEquals($valoption3, $modlog->getConfigurationOption('option1'), "lecture d'une option de confguration par moid versus toid");

    // valeur par défaut d'une option non définie
    $this->assertEquals('default value', $modlog->getConfigurationOption('option-inconnue', 'default value'), "lecture d'une option de confguration manquante : valeur par défaut");
    
  }
  public static function clearFixtures(){
    static::trace(__METHOD__);
    unlink("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php"); 
    if (file_exists("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php.back.tu")){
      copy("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php.back.tu",
	     "{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php");
    }
  }
}
