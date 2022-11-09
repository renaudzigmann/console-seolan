<?php
namespace UnitTests;
use \Seolan\Library\Upgrades;
use \Seolan\Core\DataSource\DataSource;
/**
 * tests outils des procedures d'upgrades
 */
class UpgradesTests extends BaseCase {
  private const TABLENAME = 'TU_TESTSUPGRADES';
  private function reload($tablename){
    DataSource::clearCache();
    return  DataSource::objectFactoryHelper8($tablename);
  }
  /**
   * Verifs des refus de renomage
   */
  function testRenameFieldError(){
    $this->initCase(__METHOD__);
    $tlnk = self::TABLENAME.'LNK';
    $ds = $this->reload(self::TABLENAME);
    $this->assertFieldExists($ds, 'texte1');
    Upgrades::editFields($tlnk, [['field'=>'link', 'options'=>['filter'=>'texte1="coucou"']]]);
    $ds = $this->reload(self::TABLENAME);
    $mess='';
    try{
      // le champ lien utilise texte1 de self::TABLENAME, donc on refuse
      // de renomer et on le détecte
      Upgrades::renameField(self::TABLENAME, 'texte1', 'texte1renamed');
      $notRenamed = false;
    
    }catch(\Throwable $t){
      $mess = $t->getMessage();
      $notRenamed = true;
    }
    $ds = $this->reload(self::TABLENAME);
    
    $this->assertTrue($notRenamed, "champ texte1 non renomable ($mess)");
    $this->assertFieldExists($ds, 'texte1');

    Upgrades::editFields($tlnk, [['field'=>'link', 'options'=>['filter'=>'']]]);
    $ds = $this->reload(self::TABLENAME);
    $mess2='';
    try{
      // le champs lien de self::TABLENAME n'utilise plus texte2 donc le renomage est fait
      Upgrades::renameField(self::TABLENAME, 'texte1', 'texte1renamed');
      $notRenamed = false;
    }catch(\Throwable $t){
      $mess2 = $t->getMessage();
      $notRenamed = true;
    }
    $ds = $this->reload(self::TABLENAME);
    $this->assertFieldNotExists($ds, 'texte1');
    $this->assertFieldExists($ds, 'texte1renamed');
    $this->assertFalse($notRenamed, "champ texte1 renomable ($mess2)");
  }
  /**
   *  Vérif des renomages de champs
   */
  function aa_testRenameField(){

    $this->initCase(__METHOD__);
    
    $ds = $this->reload(self::TABLENAME);
    // cas de base un champ texte
    foreach(['texte1', 'set1', 'file1', 'file2'] as $fn){
      $this->assertFieldExists($ds, $fn);
    }
    foreach(['texte1', 'set1', 'file1', 'file2', 'link1'] as $fn){
      Upgrades::renameField(self::TABLENAME, $fn, "{$fn}renamed");
    }
    $ds = $this->reload(self::TABLENAME);
    foreach(['texte1', 'set1', 'file1', 'file2', 'link1'] as $fn){
      $this->assertFieldExists($ds, "{$fn}renamed");
      $this->assertFieldNotExists($ds, $fn);
    }
    // vérifs dans les SETS
    $nb = getdb()->fetchOne('select count(*) from SETS where stab=? and field=?', [self::TABLENAME, 'set1']);
    $this->assertEquals($nb, 0);
    $nb2 = getdb()->fetchOne('select count(*) from SETS where stab=? and field=?', [self::TABLENAME, 'set1renamed']);
    $this->assertEquals($nb2, 2);
    
    // vérifs dans les fichiers : les répertoires doivent être renomés
    foreach(['file1', 'file2'] as $fn){
      $olddir = $GLOBALS['DATA_DIR'].self::TABLENAME.'/'.$fn;
      $newdir = $GLOBALS['DATA_DIR'].self::TABLENAME.'/'.$fn.'renamed';
      $this->assertDirectoryExists($newdir, "$newdir  exists");
      $this->assertFalse(file_exists($olddir), "$olddir still exists");
    }

    // vérifs des tables archives (todo)
    
  }
  static function setUpBeforeClass(){
    static::createFixtures();
  }
  function setUp(){
    parent::setUp();
    // tables propres avant chaque tests
    self::clearFixtures();
    static::createFixtures();
  }
  static function createFixtures(){
    define('UNITTEST_NOLOG_ECHO', 1);
    define('TZR_UNITTESTS_DEBUG',1);
    self::clearFixtures();
    DataSource::clearCache();
    
    \Seolan\Model\DataSource\Table\Table::procNewSource([
      "translatable"=>"0",
      "publish"=>"0",
      "auto_translate"=>"0",
      "btab"=>self::TABLENAME,
      "bname"=>[TZR_DEFAULT_LANG=>self::TABLENAME]
    ]);
    
    Upgrades::addFields(self::TABLENAME, [
      ['field'=>'texte1',
       'label'=>'Texte 1',
       'ftype'=>'\Seolan\Field\Text\Text',
       'fcount'=>70,
       'forder'=>10,
       'queryable'=>1,
       'compulsory'=>1,
       'browsable'=> 1,
       'translatable'=>0,
       'multi'=>0,
       'published'=>1,
       'target'=>null,
       'options'=>[]
      ],
      ['field'=>'set1',
       'label'=>'Set 1',
       'ftype'=>'\Seolan\Field\StringSet\StringSet',
       'fcount'=>70,
       'forder'=>20,
       'queryable'=>1,
       'compulsory'=>1,
       'browsable'=> 1,
       'translatable'=>1,
       'multi'=>0,
       'published'=>1,
       'target'=>null,
       'options'=>[]
       ],
      ['field'=>'file1',
       'label'=>'File 1',
       'ftype'=>'\Seolan\Field\File\File',
       'fcount'=>70,
       'forder'=>30,
       'queryable'=>1,
       'compulsory'=>1,
       'browsable'=> 1,
       'translatable'=>1,
       'multi'=>0,
       'published'=>1,
       'target'=>null,
       'options'=>[]
       ],
      ['field'=>'file2',
       'label'=>'File 2',
       'ftype'=>'\Seolan\Field\File\File',
       'fcount'=>70,
       'forder'=>40,
       'queryable'=>1,
       'compulsory'=>1,
       'browsable'=> 1,
       'translatable'=>1,
       'multi'=>1,
       'published'=>1,
       'target'=>null,
       'options'=>[]
       ],
      ['field'=>'link1',
       'label'=>'Link 1',
       'ftype'=>'\Seolan\Field\Link\Link',
       'fcount'=>70,
       'forder'=>40,
       'queryable'=>1,
       'compulsory'=>1,
       'browsable'=> 1,
       'translatable'=>1,
       'multi'=>1,
       'published'=>1,
       'target'=>'GRP',
       'options'=>[]
      ]
    ]);
    Upgrades::addStringsetVal(self::TABLENAME, 'set1', 'SET11', 'SET 1 1', 1);
    Upgrades::addStringsetVal(self::TABLENAME, 'set1', 'SET12', 'SET 1 2', 1);
    DataSource::clearCache();
    $ds = DataSource::objectFactoryHelper8(self::TABLENAME);
    $filename = TZR_TMP_DIR.uniqid().'.txt';
    file_put_contents($filename, 'contenu fichier de test');
    static::trace($filename);
    $ds->procInput(['file1'=>$filename]);
    for($i=1; $i<=5;$i++){
      $filename = TZR_TMP_DIR.uniqid()."_$i.txt";
      file_put_contents($filename, "contenu fichier de test $i");
      static::trace($filename);
      $ds->procInput(['file2'=>$filename]);
    }
    \Seolan\Model\DataSource\Table\Table::procNewSource([
      "translatable"=>"0",
      "publish"=>"0",
      "auto_translate"=>"0",
      "btab"=>self::TABLENAME.'LNK',
      "bname"=>[TZR_DEFAULT_LANG=>self::TABLENAME.'LNK']
    ]);
    
    Upgrades::addFields(self::TABLENAME.'LNK', [
      ['field'=>'texte',
       'label'=>'Texte',
       'ftype'=>'\Seolan\Field\Text\Text',
       'fcount'=>70,
       'forder'=>10,
       'queryable'=>1,
       'compulsory'=>1,
       'browsable'=> 1,
       'translatable'=>0,
       'multi'=>0,
       'published'=>1,
       'target'=>null,
       'options'=>[]
      ],
      ['field'=>'link',
       'label'=>'Link',
       'ftype'=>'\Seolan\Field\Link\Link',
       'fcount'=>70,
       'forder'=>20,
       'queryable'=>1,
       'compulsory'=>1,
       'browsable'=> 1,
       'translatable'=>1,
       'multi'=>0,
       'published'=>1,
       'target'=>self::TABLENAME,
       'options'=>[]
      ],
    ]);
    DataSource::clearCache();
  }
  public static function clearFixtures(){
    static::trace(__METHOD__);
    DataSourceAndFieldsTests::forceDelTable(self::TABLENAME);
    DataSourceAndFieldsTests::forceDelTable(self::TABLENAME.'LNK');
  }
}

