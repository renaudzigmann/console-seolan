<?php
namespace UnitTests;
use \Seolan\Core\DbIni;
use \Seolan\Core\Token;
/**
 * génération et contrôle des tokens
 */
class TokenTests extends BaseCase {
  public function testCreate(){
    $mngt = Token::factory();
    $control = uniqid(date('Ymdhis'));
    $id = $mngt->create('preview', false, 10, null, ['tests'=>'TokenTests','control'=>$control]);
    static::$tools->sqldump('select name, value, active from _VARS where name=?', [$id]);
    list($ok, $token) = $mngt->check($id);
    //static::$tools->traceObject($token); 
    // créé et accessible plusieurs fois 
    $this->assertEquals('ok', $ok);
    $this->assertEquals('preview', $token['type']);
    $this->assertEquals($control, $token['more']['control'],"erreur données de contrôle");
    $this->assertEquals('ok', $ok,"peristant sur la durée");
    $this->assertEquals('preview', $token['type']);
    
    //notfound

    list($p1,$p2,$p3) = explode(':',$id);
    $idko = implode(':', [$p1,$p2,str_shuffle($p3)]);
    
    static::trace("$id => $idko");
		    
    list($ok2, $token2) = $mngt->check($idko);
    $this->assertEquals('notfound', $ok2);

    // expired
    $token['expirationDate'] = date('Ymdhis');
    DbIni::set($id, json_encode($token));

    static::$tools->sqldump('select name, value, active from _VARS where name=?', [$id]);
    list($ok3, $token3) = $mngt->check($id);
    $this->assertEquals('expired', $ok3);

    unset($ok, $ok2, $ok3, $token, $token2, $token3, $control);
   
  }
  public function testSingleUse(){
    $mngt = \Seolan\Core\Token::factory();
    $control = uniqid(date('Ymdhis'));
    $id = $mngt->create('preview', true, 10, null, ['tests'=>'TokenTests singleuse','control'=>$control]);
    static::$tools->sqldump('select name, value, active from _VARS where name=?', [$id]);
    list($ok, $token) = $mngt->check($id);
    $this->assertEquals('ok', $ok);
    $this->assertEquals($token['singleUse'], true);
    
    list($ok2, $token2) = $mngt->check($id);
    $this->assertEquals('notfound', $ok2, "destruction single use");
    
  }
  public static function clearFixtures(){
    static::trace(__METHOD__);
    getDB()->execute("delete from _VARS where value like '%tokentests%'"); 
  }
  public function testPurge(){
    $mngt = \Seolan\Core\Token::factory();
    $id1 = $mngt->create('preview', false, 10, null, ['tests'=>'TokenTests']);
    $id2 = $mngt->create('preview', false, 1, null, ['tests'=>'TokenTests']);
    list($ok1, $token1) = $mngt->check($id1);
    list($ok2, $token2) = $mngt->check($id2);
    $this->assertEquals('ok', $ok1);
    $this->assertEquals('ok', $ok2);
    sleep(65);
    $mngt->purge();
    list($ok1, $token1) = $mngt->check($id1);
    list($ok2, $token2) = $mngt->check($id2);
    $this->assertEquals('ok', $ok1);
    $this->assertEquals('notfound', $ok2);
  }
}
