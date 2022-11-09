<?php	
namespace UnitTests;
use \Seolan\Library\ProcessCache;

class ProcessCacheTests extends BaseCase {
  
  function testOne(){

$values = ['toto'=>'totoval'];
    $this->assertTrue(isset($values['toto'])," 'toto' exists in values");
    
    ProcessCache::set('rr', 'rr', $values);

    $valuesFromCache = ProcessCache::get('rr', 'rr');
    $this->assertTrue(isset($valuesFromCache['toto'])," 'toto' exists in values from cache");
    
    ProcessCache::delete('rr/rr');
    
    $valuesFromCacheDeleted = ProcessCache::get('rr','rr');

    static::$tools->traceObject(ProcessCache::debugGetCache());

    $this->assertFalse(isset($valuesFromCacheDeleted['toto'])," after cache clear, value 'toto' don't exists");
  }

}
