<?php
namespace UnitTests;
/**
 * classe de base pour cas de tests utilisant des gabarits
 */
use \Seolan\Core\Template;
class BaseCaseWithTemplates extends BaseCase {
  protected static $parser = null;
  // un parser adapté aux tests
  protected function getParser(){
    if (static::$parser == null)
      
      static::$parser = new Class() extends Template {
	function __construct(){
	  parent::__construct('**not yet set**');
	  $this->rewriter = new Class(){
	    function encodeRewriting($text){}
	  };
	}
	function parseText($text, $tpldata=[], $rawdata=[]){
	  $filename = TZR_TMP_DIR.'unit-tests-text-template'.md5($text);
	  file_put_contents($filename, $text);
	  $this->tplfile = $filename;
	  return $this->parse($tpldata, $rawdata);
	}
      };
    
    return static::$parser;
  }
  public static function clearFixtures(){
    static::trace(__METHOD__);
    // les gabarits créés pour l'occasion
    exec('rm '.TZR_TMP_DIR.'unit-tests-text-template*');
  }
}
