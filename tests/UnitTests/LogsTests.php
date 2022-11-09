<?php
namespace UnitTests;

use \Seolan\Core\Logs;

class LogsTests extends BaseCase {
  private static $objectIdPrefix = 'TestObject:';
  /**
   * test trace securité events
   */
  function testSecEvent(){

    // methode secEvents
    $date = date('y-m-d H:i:s');
    $source = "Source tests $date";
    $details = "Details tests $date";
    $object = "ObjectTests:$date";
    Logs::secEvent($source, $details, $object);
    // à remplacer par l'assertion test ligne existe et ok, voir version Adrien (au 2020-03-13)
    $res = getDB()->fetchAll('select * from LOGS where etype=? and comment=? and details=? and object=?',
			     ['security', $source, $details, $object]);

    $this->assertEquals(count($res), 1, "ajout d'un event de sécurité via secEvent dans les logs");

    $uid = getSessionVar('UID');
    $usernam = $GLOBALS['XUSER']->_cur['fullnam'];
    $this->assertEquals($uid, $res[0]['user'], "user renseigné avec l'utilisateur en cours");
    $this->assertEquals($usernam, $res[0]['usernam'], "user renseigné avec l'utilisateur en cours");
    
  }
  public static function setUpBeforeClass(){
    BaseCase::setUpBeforeClass();
    static::clearFixtures();
  }
  public static function clearFixtures(){
    static::trace(__METHOD__);
    getDB()->execute('delete from LOGS where object like ?', [static::$objectIdPrefix."%"]);
  }
}
?>
