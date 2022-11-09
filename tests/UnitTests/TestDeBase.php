<?php
namespace UnitTests;
use \PHPUnit\Framework\TestCase;
use \Seolan\Core\Logs;
class TestDeBase extends BaseCase {
  //TestCase {
  function testDeTest($a=[]){
    // un moyen de faire des affichages
    $this->assertTrue(true, "une trace ...  un peu pourri");
  }
  function testDBAccessMarche(){
    static::trace(__METHOD__);
    $db = getDB();
    $this->assertTrue(($db!==null), "connexion ok à la base ?");
    $rootAliasFound = $db->fetchOne('select alias from USERS where alias=?', ['root']);
    $this->assertEquals($rootAliasFound, 'root');
  }
}