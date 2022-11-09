<?php
namespace UnitTests;
 /**
 * tests des commentaires */

use \Seolan\Model\DataSource\Comments\Comments;
use \Seolan\Core\DataSource\DataSource;
use \Seolan\Core\Module\Module;
use \Seolan\Model\DataSource\Table\Table;
use \Seolan\Library\Upgrades;

class CommentsTests extends BaseCase {
  
  public function testCreateComment() {
    static::initMockShell();

    $ar['moid'] = 'prova';
    $ar['modulename'] = 'moduleTest';
    $comm = 'testCreate'.uniqid();
    $ar['data'] = $comm;
    $user = "USERS:TZRUNITTESTS";
    $ar['oid'] = $user;
    $test = DataSource::objectFactoryHelper8(Comments::$tablename);
    $test->insertComment($ar);

    $this->assertSQLRowIsOk(Comments::$tablename, ['COMMENTAIRE' => $comm], ['COBJECT' => $user]);
  }

  public function testCreateCommentFromInsertion() {
    static::initMockShell();

    Upgrades::addTable("_TESTTABLE", "_TESTTABLE");

    $testTable = DataSource::objectFactoryHelper8("_TESTTABLE");
    $ar['moid'] = 'test';
    $ar['modulename'] = 'moduleTest';
    $r=$testTable->procInput($ar);

    sleep(1);
    
    if (isset($r['oid'])) {
      $ar2['oid'] = $r['oid'];
      $comm = 'testInsert'.uniqid();
      $ar2['data'] = $comm;
      $ar2['upd'] = getDB()->fetchOne('select UPD from _TESTTABLE where KOID=?', [$r['oid']]); 
      $testComment = DataSource::objectFactoryHelper8(Comments::$tablename);
      $testComment->insertComment($ar2);
      }
    $object = $r['oid'];

    $this->assertSQLRowIsOk(Comments::$tablename, ['COMMENTAIRE' => $comm], ['COBJECT' => $object]);

    $updComment = getDB()->fetchOne('select UPD from '.Comments::$tablename.' where COBJECT=? and LANG=?', [$object,\Seolan\Core\Shell::getLangData()]);
    $updFiche = $ar2['upd'];
    
    $this->assertEquals($updFiche, $updComment);

    return $ar2['oid'];
  }

  /**
   @depends testCreateCommentFromInsertion
   */
  public function testCreateCommentFromEdition($oid) {

    static::initMockShell();

    $ds = DataSource::objectFactoryHelper8("_TESTTABLE");
    
    $ds->createField(
		     "testField",
		     "TESTFIELD",
		     "\Seolan\Field\ShortText\ShortText",
		     "32",
		     1,
		     0,
		     0,
		     0,
		     0,
		     0,
		     0,
		     '');

    DataSource::clearCache();
    $ds = DataSource::objectFactoryHelper8("_TESTTABLE");
     
    $ar['testField'] = 'testField';
    $ar['oid'] = $oid;
    
    $r=$ds->procEdit($ar);

    sleep(1);

    if (isset($r['oid'])) {
      $ar['oid'] = $r['oid'];
      $comm = 'testEdit'.uniqid();
      $ar['data'] = $comm;
      $oid = $r['oid'];
      $ar['upd'] = getDB()->fetchOne('select UPD from _TESTTABLE where KOID=?', [$r['oid']]);
      $testComment = DataSource::objectFactoryHelper8(Comments::$tablename);
      $testComment->insertComment($ar);
    }

    $object = $r['oid'];

    $this->assertSQLRowIsOk(Comments::$tablename, ['COMMENTAIRE' => $comm], ['COBJECT' => $object]);

    $updComment = getDB()->fetchOne('select UPD from '.Comments::$tablename.' where COMMENTAIRE=? and LANG=?', [$comm, \Seolan\Core\Shell::getLangData()]);

    $updFiche =	$ar['upd'];

    $this->assertEquals($updFiche, $updComment);

  }

  public static function clearFixtures() {
    static::trace(__METHOD__);
    static::forceDelTable("_TESTTABLE");
    getDB()->execute('Delete from '.Comments::$tablename.' where OWN="USERS:TZRUNITTESTS"');
  }
}
