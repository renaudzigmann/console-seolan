<?php
  /**
   * tests certaines methodes et fonctionnalités des datasources
   */

namespace UnitTests;

use \Seolan\Core\DataSource\DataSource;
use \Seolan\Model\DataSource\Table\Table;
use \Seolan\Core\User;

class DataSourceFuntionsTests extends BaseCase {
  private const TABNAME = 'TU_TABLE_TRASH_TESTS';
  /**
   * gestion des archives par le datasource => suppression physique
   */
  function testTrash2(){

    $this->forceDelTable(self::TABNAME);
    DataSource::clearCache();          
    
    $this->createTestTable();
    DataSource::clearCache();
    
    $this->assertDSExists(self::TABNAME);
    
    $prefix = 'tests2';
    $this->populateTable($prefix, 5);

    $ds = DataSource::objectFactoryHelper8(self::TABNAME);
    
    $lines = [];
    $oids = [];
    for($i=0; $i<5; $i++){
      $oid = sprintf("%s:%s%03d", self::TABNAME, $prefix, $i);
      $lines[] = $ds->rdisplay($oid);
      $oids[] = $oid;
    }
    
    // création de lignes d'archives
    $uoids = [];
    foreach([0,1,2] as $i){
      $uoids[] = $oid = $oids[$i];
      sleep(1);
      $ds->procEdit([
	'_archive'=>1,
	'oid'=>$oid,
	'title'=>"title update for {$oid}"
      ]);
    }

    static::$tools->sqldump("/*A*/ select * from A_".self::TABNAME);

    /// vérification des lignes d'archives + mémorisation de l'upd
    $upds=[];
    foreach([0,1,2] as $i){
      $upda= getDB()->fetchOne('select UPD from A_'.self::TABNAME.' where koid=?',[$oids[$i]]);
      $this->assertTrue($upda!==false);
      $upds[$i] = $upda = date('YmdHis', strtotime($upda));
      $file = str_replace(self::TABNAME, 'A_'.self::TABNAME."/{$upda}", $lines[$i]['ofile1']->filename);
      $this->assertFileExists($file);
    }
 
    // suppression physique
    $ds->del(['oid'=>$oids[0],
	      '_fullDelete'=>1,
	      'tplentry'=>TZR_RETURN_DATA]);
    $ds->del(['oid'=>$oids[1],
	      '_fullDelete'=>1,
	      'tplentry'=>TZR_RETURN_DATA]);
    $ds->del(['oid'=>$oids[2],
	      '_fullDelete'=>0,
	      'tplentry'=>TZR_RETURN_DATA]);

    static::$tools->sqldump("/*B*/ select * from A_".self::TABNAME);
    
    /// vérification des lignes d'archives et des fichiers
    foreach([0=>0,1=>0,2=>1] as $i=>$nb){
      $nba = getDB()->fetchOne('select count(*) from A_'.self::TABNAME.' where koid=?',[$oids[$i]]);
      $this->assertEquals($nb, $nba,"nbre de lignes d'archives pour oid={$oids[$i]} ($i) egal à $nb ");
      $file = str_replace(self::TABNAME, 'A_'.self::TABNAME."/{$upds[$i]}", $lines[$i]['ofile1']->filename);
      if ($nb == 0){ // fichier devrait avoir été supprimé
	$this->assertFileNotExists($file);
      } else { // fichier doit être en place
	$this->assertFileExists($file);
      }
    }

  }
  /**
   * gestion de la corbeille pas le datasource
   */
  function testTrash1(){

    $foomoid = 0;
    $foouser = User::get_current_user_uid();

    $this->forceDelTable(self::TABNAME);
    DataSource::clearCache();
    
    $this->createTestTable();
    DataSource::clearCache();
    
    $this->assertDSExists(self::TABNAME);

    $prefix = 'tests1';
    
    $this->populateTable($prefix, 5);
    
    $ds = DataSource::objectFactoryHelper8(self::TABNAME);

    $lines = [];
    $oids = [];
    for($i=0; $i<5; $i++){
      $oid = sprintf("%s:%s%03d", self::TABNAME, $prefix, $i);
      $lines[] = $ds->rdisplay($oid);
      $oids[] = $oid;
    }

    // générer des lignes d'archives
    foreach([0,2] as $i){
      $oid = $oids[$i];
      sleep(1);
      $ds->procEdit([
	'_archive'=>1,
	'oid'=>$oid,
	'title'=>"title update for {$oid}"
      ]);
    }

    sleep(1);
    // générer des lignes d'archives en edit multiple (même upd)
    $uoids = [];
    $titles = [];
    foreach([1,3] as $i){
      $uoids[] = $oids[$i];
      $titles[] = "title update batch for {$oid}";
    }

    $ds->procEdit(['oid'=>$uoids,
		   'title'=>$titles]);

    // effacer des lignes => corbeille
    $deloids = [];
    foreach([0,3,4] as $i){
      $deloids[] = $deloid = $oids[$i];
      $ds->del(['oid'=>$deloid,
		'_trashmoid'=>$foomoid,
		'_trashuser'=>$foouser,
		'_movetotrash'=>true]); // prop. du module parent d'un DS
    }

    $t = $ds->browseTrash([]);

    /* 

       static::$tools->sqldump("desc A_".self::TABNAME);
       static::$tools->sqldump($t['select']);
       static::$tools->sqldump("select object,  usernam, upd, etype, details from LOGS where object like '".self::TABNAME.":test1%'");
       static::trace("browse trash lines ".count($t['lines_oid']));
       static::trace("browse trash lines ".implode(',',$t['lines_oid']));
       static::$tools->sqldump("select * from ".self::TABNAME);
       static::$tools->sqldump("select * from A_".self::TABNAME);
       if (file_exists('/home/reynaud/tree'))
       static::$tools->execdump("/home/reynaud/tree ".$GLOBALS['DATA_DIR']."A_".self::TABNAME."");

     */

    $this->assertArraySameValues($deloids, $t['lines_oid']);

    //les fichiers 1 3 et 4 doivent avoir disparu
    $this->assertTZRFileNotExists($lines[0]['ofile1']);
    $this->assertTZRFileNotExists($lines[3]['ofile1']);
    $this->assertTZRFileNotExists($lines[4]['ofile1']);
    
    // les fichiers 2 et 3 doivent être en place
    $this->assertTZRFileExists($lines[1]['ofile1']);
    $this->assertTZRFileExists($lines[2]['ofile1']);

    // restauration (d'une)
    foreach($t['lines_oid'] as $i=>$roid){
      if ($roid == $oids[3]){
	$ds->restoreArchive($roid, $t['lines_oUPD'][$i]->raw, $foomoid, $foouser);
      }
    }
    
    $t = $ds->browseTrash([]);

    $this->assertEquals(2, count($t['lines_oid']), "1 line restored from trash");
    $this->assertObjectExists($oids[3]);
    
    // suppression de la corbeille (=suppression de la ligne via correspondante oid/upd)
    foreach($t['lines_oid'] as $i=>$roid){
      if ($roid == $oids[0]){
	$ds->delArchive($roid, $t['lines_oUPD'][$i]->raw);
      }
    }

    $t = $ds->browseTrash([]);
    
    $this->assertEquals(1, count($t['lines_oid']), "1 line deleted from trash");

    // vidage complet
    $ds->delArchiveAll();
    
    $t = $ds->browseTrash([]);

    $this->assertEquals(0, count($t['lines_oid']), "trash is empty after emptying whole trash");

  }
  protected function populateTable(string $prefix, int $num=10){
    
    $ds = DataSource::objectFactoryHelper8(self::TABNAME);
    for($i=0;$i<$num; $i++){
      $file = TZR_TMP_DIR.uniqid("testtrashfile{$i}");
      file_put_contents($file, "content for {$i} {$file}");
      static::trace($file);
      $ds->procInput([
		      'newoid'=>sprintf("%s:%s%03d", self::TABNAME, $prefix, $i),
	'title'=>"Title for {$i}",
	'file1'=>$file
      ]);
    }

  }
  protected function createTestTable( ){
    Table::procNewSource([
      "translatable" => "0",
      "publish" => "0",
      "auto_translate" => "0",
      "btab" => self::TABNAME,
      "bname" => [TZR_DEFAULT_LANG => self::TABNAME]
    ]);

    $ds = DataSource::objectFactoryHelper8(self::TABNAME);
    $ds->createField(
      'title',
      'title',
      '\Seolan\Field\ShortText\ShortText',
      '64','1','0','0','0','0','0','1',null,[]
      ); 
      
    $ds->createField(
      'file1',
      'file1',
      '\Seolan\Field\File\File',
      '64','2','0','0','0','0','0','1',null,[]
      ); 

    static::$tools->sqldump("select * from BASEBASE where btab=?", [self::TABNAME]);
   
  }
  public static function clearFixtures(){
    static::forceDelTable(self::TABNAME);
  }
} 
