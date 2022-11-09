<?php
namespace UnitTests;

use \Seolan\Field\Link\Link;
use \Seolan\Core\Kernel;
use \Seolan\Field\Link\Normalizer;
use \Seolan\Core\DataSource\DataSource;
use \Seolan\Model\DataSource\Table\Table;
use \Seolan\Core\Shell;

/**
 * champs liens : cas table de relation
 * pour récupérer un env avec les tables TNDS : --filter "EnvIsOK|Cond"
 */
class LinkRelationTableTests extends BaseCase {
  static $srcTab = 'TU_TNLDS001'; // tests normalization, effacement
  static $srcTabq = 'TU_TNLDS002'; // tests des queries
  static $targetTab = 'TU_TNLDS001TARGET';
  static $versionOK = false;
  
  protected function initCase($name){
    parent::initCase($name);
    if (!static::$versionOK)
      $this->markTestIncomplete('N/A test : version mismatch, normalization not applicable');
  }
  function testEnvIsOK(){
    
    $this->initCase(__METHOD__);

    $this->assertDSExists(static::$srcTab);
    $this->assertDSExists(static::$srcTabq);
    $this->assertDSExists(static::$targetTab);

    $dsu = DataSource::objectFactoryHelper8(static::$srcTab);

    $field = $dsu->getField('testnormlink1');
    $this->assertTrue($field->normalized==0, "Normalized new link field prop. is false"); 
    $reltable = Normalizer::getRelationTableName($field);
    $this->assertSQLTableNotExists($reltable);

    $field = $dsu->getField('testnormlink2');
    $reltable = Normalizer::getRelationTableName($field);
    $this->assertSQLTableNotExists($reltable);

    $nb = getDB()->fetchOne("select count(*) from ".static::$srcTab);
    static::trace(__METHOD__." src nbre $nb");
    $this->assertTrue($nb>100, "quelques lignes ($nb) dans ".static::$srcTab);
    $this->assertTrue($nb<1000, "quelques lignes ($nb) dans ".static::$srcTab);
    $nbt = getDB()->fetchOne("select count(*) from ".static::$targetTab);
    static::trace(__METHOD__." target nbre $nbt");
    $this->assertTrue($nbt>5, "quelques lignes ($nbt) dans ".static::$targetTab);
    $this->assertTrue($nbt<1000, "quelques lignes ($nbt) dans ".static::$targetTab);
    
  }
  /**
   * number of item not require normalization
   * @depends testEnvIsOK
   */
  function testNormalizeNotNecessary(){
    $this->initCase(__METHOD__);
    $dsu = DataSource::objectFactoryHelper8(static::$srcTab);
    $field = $dsu->getField('testnormlink1');
    $reltable = Normalizer::getRelationTableName($field);
    $mess = "";
    $field->chk($mess);
    
    $this->assertTrue($field->normalized != true);        
    $this->assertSQLTableNotExists($reltable);
    
    $field2 = $dsu->getField('testnormlink2');
    $reltable2 = Normalizer::getRelationTableName($field2);
    $mess = "";
    $field2->chk($mess);
    $this->assertSQLTableNotExists($reltable2);
    $this->assertTrue($field2->normalized != true);        
    
    $field3 = $dsu->getField('testnormlink3');
    $reltable3 = Normalizer::getRelationTableName($field3);
    $mess = "";
    $field3->chk($mess);
    $this->assertSQLTableNotExists($reltable3);
    $this->assertTrue($field2->normalized != true);

    
  }
  /**
   * number of item require normalization
   * @depends testEnvIsOK
   */
  function testNormalizeRequired(){
    $this->initCase(__METHOD__);

    static::createTestData(100, false);
    
    $dsu = DataSource::objectFactoryHelper8(static::$srcTab);

    $nbs = getDB()->fetchOne('select count(*) from '.static::$srcTab);
    $this->assertTrue($nbs>1000, "table source  ($nbs lignes) contient plus de 1000 lignes");
    $nbt = getDB()->fetchOne('select count(*) from '.static::$targetTab);
    $this->assertTrue($nbt<1000, "table destination ($nbt lignes) contient moins 1000 lignes");
    
    $field1= $dsu->getField('testnormlink1');
    $reltable = Normalizer::getRelationTableName($field1);
    $mess = "";
    $field1->chk($mess);

    $this->assertSQLTableExists($reltable);
    $this->assertTrue($field1->normalized == true);

    $field2 = $dsu->getField('testnormlink2');
    $reltable2 = Normalizer::getRelationTableName($field2);
    $mess = "";
    $field2->chk($mess);
    $this->assertTrue($field2->normalized == true);
    $this->assertSQLTableExists($reltable2);

    // check that field's property "normalized" is true
    DataSource::clearcache();
    $dsu = DataSource::objectFactoryHelper8(static::$srcTab);
    $field1 = $dsu->getField('testnormlink1');
    $field2 = $dsu->getField('testnormlink2');
    
    $this->assertTrue($field1->normalized == true);
    $this->assertTrue($field2->normalized == true);

   
  }
  /**
   * version
   * @depends testNormalizeRequired
   */
  function testComponentVersion(){

    $this->initCase(__METHOD__);

    $dsu = DataSource::objectFactoryHelper8(static::$srcTab);
    $field1= $dsu->getField('testnormlink1');
    $field2= $dsu->getField('testnormlink2');
    foreach([$field1, $field2] as $field){
      static::trace("{$field->field} Version : ".Normalizer::getComponentsVersion($field));
      $this->assertEquals(Normalizer::checkComponentsVersion($field), true, "Version des procédures et trigger mal enregistrée (1)?");
    }

    // simulate Normalizer version upgrade
    Normalizer::$version='1.9'; // être sur une version plus haute 
    foreach([$field1, $field2] as $field){
      static::trace("{$field->field} Version : ".Normalizer::getComponentsVersion($field)." / ".Normalizer::$version);
      $this->assertEquals(Normalizer::checkComponentsVersion($field), false, "Version des procédures et trigger mal enregistrée (2)?");
    }
    
    foreach([$field1, $field2] as $field){

      $m = '';
      $field->chk($m);
      
      static::trace("{$field->field} Version : ".Normalizer::getComponentsVersion($field)." / ".Normalizer::$version);
      $this->assertEquals(Normalizer::checkComponentsVersion($field), true, "Version des procédures et trigger mal enregistrée (3)?");

      $this->assertTrue($field->normalized == true);
    }
    
  }
  /**
   * relation table contents
   * @depends testNormalizeRequired
   * @note : le test n'est pas bon car les traitements en insertion dans le trigger
   * sont différents de ceux en mise à jour (delete from ...)
   * => une mise à jour de ligne, en particulier en langue !TZR_DEFAULT_LANG
   * peut casser la table de relation si bug dans la procedure en maj
   */
  function testRelationTableContents(){
    $this->initCase(__METHOD__);

    $dsu = DataSource::objectFactoryHelper8(static::$srcTab);
    $field = $dsu->getField('testnormlink1');
    $reltable = Normalizer::getRelationTableName($field);
    // 1 line / koid in src line
    foreach(array_keys($GLOBALS['TZR_LANGUAGES']) as $lang){
      $nbsrc = getDb()->fetchOne("select count(distinct koidsrc) from $reltable where langsrc=?", [$lang]);
      $nbdst = getDb()->fetchOne("select count(distinct koidsrc, koiddst) from $reltable where langsrc=?", [$lang]);
      $nbo = getDB()->fetchOne('select count(*) from '.static::$srcTab.' where ifnull(testnormlink1, "")!="" and lang=?', [$lang]);
      static::trace("lang=$lang, src $nbsrc dst $nbdst ori $nbo");
      $this->assertEquals($nbsrc, $nbo, "nombre de lignes / valeurs source destination et relation (lang=$lang) - source : $nbo, relation : $nbdst, source dans la relation : $nbsrc");
      $lines = getDB()->select('select koid, testnormlink1 from '.static::$srcTab.' where lang=?', [$lang]);
      $nbo = 0;
      while($line = $lines->fetch()){
	$oids = array_filter(explode('||', $line['testnormlink1']));
	$nbo+=count($oids);
      }
      static::trace("lang=$lang, nb distinct testnormlink1 : $nbo, nb distinct koiddst : $nbdst ");
      $this->assertEquals($nbdst, $nbo, "nombre de couples src/dst dans la table relation / nombre de lignes sources et nombres de liens");
    }

    // vérification sur les contraintes (ne doivent pas faire planter)
    // ex : insertion d'une valeur (via datasource ou directement) qui n'existe pas en table dest
    getDB()->execute('insert into '.$dsu->getTable().' (koid, lang, testnormlink1, testnormlink2) values(?, ?, ?, ?)',
		     [$dsu->getTable().':TESTSFOREIGNKEY1',
		      TZR_DEFAULT_LANG,
		      "$reltable:UNKNOWNOID1",
		      null]
    );
    $nbunknown = getDB()->fetchOne("select count(*) from $reltable where KOIDDST like '%TESTSFOREIGNKEY%' OR KOIDSRC like '%TESTFOReIGNKEY%' ");
    $this->assertEquals(0, $nbunknown, "Foreign keys constraints : unknown koid dst 1");
    $nbunknown = getDB()->fetchOne("select count(*) from $reltable where KOIDDST like '%UNKNOWNOID%' OR KOIDSRC like '%UNKNOWNOID1%'");
    $this->assertEquals(0, $nbunknown, "Foreign keys constraints : unknown koid dst 1");
  }
  /**
   * Insertions sur une table avec champ normalizé
   * Insertions une, plusieurs valeurs, vérification table et table relation
   */
  function testDataSourceProcInput(){
    //static::$tools->sqldump('select * from '.static::$targetTab);
    $srcTab = static::$srcTab;
    $targetTab = static::$targetTab;
    $target1 = getDB()->fetchOne("select koid from $targetTab order by rand() limit 1");
    $target2 = getDB()->fetchOne("select koid from $targetTab order by rand() limit 10,1");
    $target3 = getDB()->fetchOne("select koid from $targetTab order by rand() limit 20,1");
    
    $oid1 = "{$srcTab}:pi1";
    $dsu = DataSource::objectFactoryHelper8($srcTab);
    $dsu->procInput(['newoid'=>$oid1,
		     'testnormlink2'=>$target1,
		     'testnormlink1'=>[$target2,$target1]]);

    static::$tools->sqldump("select * from $srcTab where koid='$oid1' and lang='FR'");
    static::$tools->sqldump("select * from {$srcTab}_testnormlink1 where koidsrc='$oid1' and langsrc='FR'");
    static::$tools->sqldump("select * from {$srcTab}_testnormlink2 where koidsrc='$oid1' and langsrc='FR'");

    $line = getDB()->fetchRow("select * from {$srcTab} where koid=?", [$oid1]);
    $this->assertTrue(($line['testnormlink2']==$target1));
    
    // détection des valeurs nulles + procEdit
    getDB()->execute("update {$srcTab} set testnormlink1=null, testnormlink2=null where koid='{$oid1}'");
    getDB()->execute("delete from {$srcTab}_testnormlink1 where koidsrc='$oid1'");
    getDB()->execute("delete from {$srcTab}_testnormlink2 where koidsrc='$oid1'");
	
    $dsu->procEdit(['oid'=>$oid1,
		    'testnormlink1'=>$target2,
		    'testnormlink3'=>$target1,
		    'testnormlink2'=>[$target2,$target3]]);

    $line = getDB()->fetchRow("select * from {$srcTab} where koid=? and lang=?", [$oid1,'FR']);
    $oids1 = preg_split('@\|\|@', $line['testnormlink1'], -1, PREG_SPLIT_NO_EMPTY);
    $oids2 = preg_split('@\|\|@', $line['testnormlink2'], -1, PREG_SPLIT_NO_EMPTY);

    static::$tools->sqldump("select * from $srcTab where koid='$oid1' and lang='FR'");
    static::$tools->sqldump("select * from {$srcTab}_testnormlink1 where koidsrc='$oid1' and langsrc='FR'");
    static::$tools->sqldump("select * from {$srcTab}_testnormlink2 where koidsrc='$oid1' and langsrc='FR'");

    //$this->assertTrue(empty(array_diff($oids1, [$target2])) && empty(array_diff([$target2], $oids1)),"target testnormlink1 oid mis à jour");
    //$this->assertTrue(empty(array_diff($oids2, [$target2,$target3])) && empty(array_diff([$target2,$target3], $oids1)),"target testnormlink2 mis à jour");

    $noids1 = getDB()->fetchCol("select koiddst from {$srcTab}_testnormlink1 where koidsrc='$oid1' and langsrc='FR'");
    $noids2 = getDB()->fetchCol("select koiddst from {$srcTab}_testnormlink2 where koidsrc='$oid1' and langsrc='FR'");

    sort($noids2);
    sort($oids2);
    $res2 = [$target2, $target3];
    sort($res2);
    
    $this->assertEquals([$target2],$noids1);
    $this->assertEquals($res2,$noids2);      
    
    $this->assertEquals([$target2],$oids1);
    $this->assertEquals($res2,$oids2);      
    
  }
  /**
   * make_cond 
   * -> datasource (Table) make_cond doit utiliser une sous requete non corrélée
   * via make_cond
   * @depends testEnvIsOK
   */
  function testDataSourceQueriesMakeCond(){

    $this->initCase(__METHOD__);
    
    $this->forceNormalize(static::$srcTabq, 'testnormlink1');

    Datasource::clearcache();
    $dsq = DataSource::objectFactoryHelper8(static::$srcTabq);
    
    $field1 = $dsq->getField('testnormlink1');
    $field2 = $dsq->getField('testnormlink0');

    $this->assertTrue($field1->normalized == true, "champ normalisé");
    $this->assertTrue($field2->normalized != true, "champ non normalisé");

    $reltable = $field1->getRelationTableName();
    $nboids = 3;
    $oids1 = getDB()->fetchCol('select koiddst from '.$reltable." order by rand() limit $nboids");
    
    $query1a = $dsq->select_query(['cond'=>[$field1->field=>['=', $oids1[0]]]]);
    $query1b = $dsq->select_query(['cond'=>[$field1->field=>['=', $oids1]]]);

    $query2 = $dsq->select_query(['cond'=>[$field2->field=>['=', 'A']]]);

    static::trace("query1a $query1a");
    static::trace("query1b $query1b");
    
    $this->assertStringNotContainsString("INSTR", $query1a, "Requete $query1a contient des INSTR au lieu de sous requêtes");
    $this->assertStringContainsString("KOID in (", $query1a, "Requete $query1a contient pas de sous requête 'KOID in ('");

    foreach([[$query1a,null], // on sait pas combien en faisant juste comme ça
	     [$query1b,null], // on sait pas combien
	     [$query2,0]] as list($q, $nb)){
      static::trace(__METHOD__.":\n\t".$q);
      try{
	$rs = getDB()->select($q);
	if ($nb!=null)
	  $this->assertTrue($rs->rowCount()==$nb, "$q renvoie {$rs->rowCount()} au lieu de $nb lignes ");
      }catch(\Throwable $t){
	$this->assertTrue(false, $q." \n".$t->getMessage());
      };
      $this->assertTrue($rs!=false, "Erreur SQL requête / sous reqête");
    }
    
  }
  /**
   * recherches (procQuery / post_query des champs)
   *-> via DataSource::procQuery et Field::post_query
   * @depends testEnvIsOK
   */
  function testDataSourceQueriesProcQuery(){
    
    $this->initCase(__METHOD__);

    $this->forceNormalize(static::$srcTabq, 'testnormlink1');
    
    $dsq = DataSource::objectFactoryHelper8(static::$srcTabq);
    $dst = DataSource::objectFactoryHelper8(static::$targetTab);

    $tlang = $this->getRandTradLang();

    static::trace("lang trad $tlang");
  
    // ajout de lignes bien définies en target
    foreach(['A','B','C','D'] as $i){
      $dst->procInput(['description'=>'tests norm link suite '.$i,
		       'newoid'=>static::$targetTab.':POSTQ'.$i]);
      
      $dst->procInput(['description'=>"tests norm link suite $i $tlang",
		       'newoid'=>static::$targetTab.":POSTQ{$i}{$tlang}"]);
    }
    // ajout de lignes en src
    foreach([['A','B'],['A','B','C'],['A','D']] as $links){

      $castest = implode('', $links);

      $oidlinks = array_map(function($val){
	  return static::$targetTab.':POSTQ'.$val;
	},
	$links);
      $tlangoidlinks = array_map(function($val) use($tlang){
	  return static::$targetTab.':POSTQ'.$val.$tlang;
	},
	$links);
      $newoid = static::$srcTabq.':POSTQ'.$castest;
      $dsq->procInput(['newoid'=>$newoid,
		       'description'=>'cas '.$castest,
		       'testnormlink1'=>$oidlinks]);

      
      Shell::setLang($tlang);
      static::trace(implode(',', $tlangoidlinks));
      $dsq->procEdit(['oid'=>$newoid,
		      'description'=>"cas $castest $tlang",
		      'testnormlink1'=>$tlangoidlinks]);
      Shell::setLang(TZR_DEFAULT_LANG);
      
    }

    static::$tools->sqldump('select * from '.static::$srcTabq.' where koid like "%POSTQ%"');
    static::$tools->sqldump('select * from '.static::$targetTab.' where koid like "%POSTQ%"');
    //static::$tools->sqldump('select count(*) from '.$dsq->getTable().'_'.$dsq->getField('testnormlink1')->field.' where koiddst like "%POSTQ%" or koidsrc like "%POSTQ%"');
    static::$tools->sqldump('select * from '.$dsq->getTable().'_'.$dsq->getField('testnormlink1')->field.' where koiddst like "%POSTQ%" or koidsrc like "%POSTQ%"');
    
    // contrôle de la requête avec procQuery (Field::post_query)
    foreach([
      [['A','B'],'OR',[static::$srcTabq.':POSTQAB',
		       static::$srcTabq.':POSTQABC',
		       static::$srcTabq.':POSTQAD']]
     ,[['A','B'],'AND',[static::$srcTabq.':POSTQAB',
			static::$srcTabq.':POSTQABC']]
      // essai injection ? -> en tout cas il ya des getDB()->quote() et la requete doit passer
      ,[['\')) AND 1));((drop table toto; select * from USERS where alias=\'\';'], 'OR', []]
      ,[['A'], '', [static::$srcTabq.':POSTQAB',
		     static::$srcTabq.':POSTQABC',
		     static::$srcTabq.':POSTQAD']]
    ] as list($links, $op, $needles)){
      $case = "'$op' ".implode(' ', $links);
      $oidlinks = array_map(function($val){
	  return static::$targetTab.':POSTQ'.$val;
	},
	$links);
      
      $r = $dsq->procQuery(['_FIELDS'=>['testnormlink1'=>'testnormlink1'],
			    'testnormlink1'=>$oidlinks,
			    'testnormlink1_op'=>$op,
			    'selectedfields'=>['description','testnormlink1'],
			    'tplentry'=>TZR_RETURN_DATA]);
      
      $this->assertArrayHasKey('select', $r);
      $this->assertNotEmpty($r['select']);
      static::trace(__METHOD__."\n\t{$r['select']}");
      $this->assertArrayHasKey('lines_oid', $r);
      static::trace(__METHOD__."\n\t".count($r['lines_oid']));

      foreach($needles as $needle){
	$this->assertContains($needle, $r['lines_oid'],"$case, lines_oid " );
      }

      Shell::setLang($tlang);

      $tlangoidlinks = array_map(function($val)use($tlang){
	  return static::$targetTab.':POSTQ'.$val.$tlang;
	},
	$links);
      
      $tr = $dsq->procQuery(['_FIELDS'=>['testnormlink1'=>'testnormlink1'],
			     'testnormlink1'=>$tlangoidlinks,
			     'testnormlink1_op'=>$op,
			     'selectedfields'=>['description','testnormlink1'],
			     'tplentry'=>TZR_RETURN_DATA]);
      
      static::trace(__METHOD__."\n\t{$case} ".implode(',',$tlangoidlinks)." ".implode(',', $links));
      $this->assertArrayHasKey('select', $tr);
      $this->assertNotEmpty($tr['select']);
      static::trace(__METHOD__."\n\t{$tr['select']}");
      $this->assertArrayHasKey('lines_oid', $tr);
      
      static::trace(__METHOD__."\n\t".count($tr['lines_oid']));

      $nbfound = count($tr['lines_oid']);
      foreach($needles as $needle){
	$description = 'cas '.implode('', $links).' '.$tlang;
	$this->assertContains($needle, $tr['lines_oid'],"(lang=$tlang) cas {$case}, lines_oid \"".implode('"', $tr['lines_oid']).'"'."\n\t{$tr['select']}");
      }
      
      Shell::setLang(TZR_DEFAULT_LANG);
      
    }
  }
  /**
   * field delete imply relation table, triggers and procedure delete
   * field multivalued and normalized reset to monovalued imply same actions
   * @depends testNormalizeRequired
   */
  function testDeleteAndUpdateFieldIsOk(){

    $this->initCase(__METHOD__);
    
    $ds = DataSource::objectFactoryHelper8(static::$srcTab);

    // delete field 1
    $field1 = $ds->getField('testnormlink1');
    $reltable1 = Normalizer::getRelationTableName($field1);

    $ds->delField(['_options'=>['local'=>true],
		   'field'=>$field1->field]);
    
    $this->assertSQLTableNotExists($reltable1);
    
    // set field 2 to monovalued 
    $ds->procEditField(['_options'=>['local'=>true],
			'tplentry'=>TZR_RETURN_DATA,
			'field'=>'testnormlink2',
			'multivalued'=>false
			]);

    DataSource::clearCache();
    $ds = DataSource::objectFactoryHelper8(static::$srcTab);

   
    $field2 = $ds->getField('testnormlink2');
   
    $reltable2 = Normalizer::getRelationTableName($field2);
    $mess = '';
    $field2->chk($mess);

    $this->assertTrue(($field2->normalized!=true), "unset normalized property after reset to mono valued");
    $this->assertSQLTableNotExists($reltable2);
    
  }
  /**
   * datasource delete imply relation table delete
   * @depends testDeleteAndUpdateFieldIsOk
   * link1 deleted, link2 set to mono valued in testDeleteFieldIsOk, 
   * check link3 components not exists
   */
  function testDeleteDataSourceIsOk(){

    $this->initCase(__METHOD__);
    
    $ds = DataSource::objectFactoryHelper8(static::$srcTab);
	
    $field2 = $ds->getField('testnormlink3');
    $reltable2 = Normalizer::getRelationTableName($field2);
    
    $ds->procDeleteDataSource([]);
    
    $this->assertSQLTableNotExists($reltable2);
    
  }
  /**
   * force la normalisation pour le champ $field de la table $tab
   */
  protected function forceNormalize($tab, $fname){

    $dsq = DataSource::objectFactoryHelper8($tab);
    $field = $dsq->getField($fname);
    
    if (getDB()->fetchOne('select count(*) from '.$field->target)<=Normalizer::$targetLimit){
      $this->trace(__METHOD__." adding data $tab $fname");
      static::createTestData(100); 
    } else {
      $this->trace(__METHOD__." data ok $tab $fname");
    }

    $mess = '';
    $field->chk($mess);
    
    DataSource::clearcache();
    
  }

  /// ran before class cases execution
  public static function setUpBeforeClass(){
    BaseCase::setUpBeforeClass();
    static::$versionOK = Normalizer::versionOK();
    try{ // semble nécessaire pour que les warning et autres ne remontent pas à PHPUNIT
      static::clearFixtures();
      static::createTestStructures();
      static::createTestData(10);
    } catch(\Throwable $t){
      static::$errors[] = $t;
    }
  }
  /// utilities
  protected static function createTestStructures(){

    Table::procNewSource(['translatable'=>'0',
			 'auto_translate'=>'0',
			 'btab'=>static::$targetTab,
			 'bname'=>[TZR_DEFAULT_LANG=>'Tests Table Relation Liens '.static::$targetTab]]);

    DataSource::clearCAche();
    
    $dst = DataSource::objectFactoryHelper8(static::$targetTab);
    
    $dst->procNewField(['field'=>'description',
			'ftype'=>'\Seolan\Field\ShortText\ShortText',
			'forder'=>null,
			'fcount'=>120,
			'label'=>[TZR_DEFAULT_LANG=>'Description'],
			'target'=>null,
			'browsable'=>1,
			'queryable'=>1,
			'translatable'=>1,
			'multivalued'=>0,  // !!!
			'published'=>1]);

    // table traduisible en src
    Table::procNewSource(['translatable'=>'1',
			  'auto_translate'=>'1',
			  'btab'=>static::$srcTab,
			  'bname'=>[TZR_DEFAULT_LANG=>'Tests Table Relation Liens '.static::$srcTab]]);
    DataSource::clearCAche();    

    $ds = DataSource::objectFactoryHelper8(static::$srcTab);

    $ds->procNewField(['field'=>'description',
			'ftype'=>'\Seolan\Field\ShortText\ShortText',
			'forder'=>null,
			'fcount'=>120,
			'label'=>[TZR_DEFAULT_LANG=>'Description'],
			'target'=>null,
			'browsable'=>1,
			'queryable'=>1,
			'translatable'=>1,
			'multivalued'=>0,  // !!!
			'published'=>1]);
        
    $newlink = [
      'field'=>'testnormlink1',
      'ftype'=>'\Seolan\Field\Link\Link',
      'forder'=>null,
      'fcount'=>null,
      'label'=>[TZR_DEFAULT_LANG=>'Lien tests normalize link 1'],
      'target'=>static::$targetTab,
      'browsable'=>1,
      'queryable'=>1,
      'translatable'=>1,
      'multivalued'=>1,  // !!!
      'published'=>1,
    ];
    
    $ds->procNewField($newlink);

    $newlink['field'] = 'testnormlink2';
    $newlink['label']=[TZR_DEFAULT_LANG=>'Lien tests normalize link 2'];

    $ds->procNewField($newlink);

    $newlink['field'] = 'testnormlink3';
    $newlink['label']=[TZR_DEFAULT_LANG=>'Lien tests normalize link 3'];

    $ds->procNewField($newlink);

    // table pour les tests de requetes

    Table::procNewSource(['translatable'=>'1',
			  'auto_translate'=>'1',
			  'btab'=>static::$srcTabq,
			  'bname'=>[TZR_DEFAULT_LANG=>'Tests Table Relation Liens requêtes '.static::$srcTabq]]);
    
    DataSource::clearCAche();    
    
    $ds = DataSource::objectFactoryHelper8(static::$srcTabq);

    $ds->procNewField(['field'=>'description',
		       'ftype'=>'\Seolan\Field\ShortText\ShortText',
		       'forder'=>null,
		       'fcount'=>120,
		       'label'=>[TZR_DEFAULT_LANG=>'Description'],
		       'target'=>null,
		       'browsable'=>1,
		       'queryable'=>1,
		       'translatable'=>1,
		       'multivalued'=>0,  // !!!
		       'published'=>1]);
    
    $newlink = [
		'field'=>'testnormlink1',
		'ftype'=>'\Seolan\Field\Link\Link',
		'forder'=>null,
		'fcount'=>null,
		'label'=>[TZR_DEFAULT_LANG=>'Lien tests normalize link 1'],
		'target'=>static::$targetTab,
		'browsable'=>1,
		'queryable'=>1,
		'translatable'=>1,
		'multivalued'=>1,  // !!!
		'published'=>1,
		];
    
    $ds->procNewField($newlink);

    $newlink['field']='testnormlink0';
    $newlink['label']=[TZR_DEFAULT_LANG=>'Lien tests normalize link 0'];

    $ds->procNewField($newlink);

    $newlink['field']='testlink0';
    $newlink['label']=[TZR_DEFAULT_LANG=>'Lien tests link 0'];
    $newlink['options'] = ['checkbox'=>true, 'autocomplete'=>false, 'autocomplete_limit'=>999999,'filter'=>'(description like "tests norm link 12_")'];

    $ds->procNewField($newlink);
    
  }
  /**
   *
   */
  protected static function createTestData($nb=100, $trace=true){
    
    $ds = DataSource::objectFactoryHelper8(static::$srcTab);
    $dsq = DataSource::objectFactoryHelper8(static::$srcTabq);
    $dst = DataSource::objectFactoryHelper8(static::$targetTab);
    
    $nbs = $nb*10+1;
    $nbt = $nb*5+1;

    for($i=1; $i<=$nbs; $i++){
      $links1 = $links2 = [];
      for($l=1; $l<=rand(1,50); $l++){
	$links1[] = static::$targetTab.':'.sprintf("%03d", rand(1,$nbt));
      }
      for($l=1; $l<=rand(1,50); $l++){
	$links2[] = static::$targetTab.':'.sprintf("%03d", rand(1,$nbt));
      }
      $ds->procInput(['description'=>'TNL '.sprintf("%03d", $i),
		      'newoid'=>static::$srcTab.':'.sprintf("%03d", $i),
		      'testnormlink1'=>array_unique($links1),
		      'testnormlink2'=>array_unique($links2),
      ]);
      $dsq->procInput(['description'=>'TNL '.sprintf("%03d", $i),
		      'newoid'=>static::$srcTabq.':'.sprintf("%03d", $i),
		      'testnormlink1'=>array_unique($links1)
      ]);
    }

    for($i=1; $i<=$nbt; $i++){
      $dst->procInput(['description'=>'tests norm link '.sprintf("%03d", $i),
		       'newoid'=>static::$targetTab.':'.sprintf("%03d", $i)]);
    }

    if ($trace){
      static::$tools->sqldump('select count(*) as "nb src '.static::$srcTab.'" from '.static::$srcTab);
      static::$tools->sqldump('select count(*) as "nb target '.static::$targetTab.'" from '.static::$targetTab);
      static::$tools->sqldump('select count(*) as "nb query tab '.static::$srcTabq.'" from '.static::$srcTabq);
    }

  }
  public static function clearFixtures(){
    static::trace(__METHOD__);
    // manque les proc, trigger table de liaison ?
    foreach([
      static::$srcTab."_testnormlink1",
      static::$srcTab."_testnormlink2",
      static::$srcTab."_testnormlink3",
      static::$srcTabq."_testnormlink1",
      static::$srcTabq."_testnormlink2",
      static::$srcTabq."_testnormlink3",
      static::$srcTab,
      static::$srcTabq,
      static::$targetTab,
    ] as $name){
      static::trace("delete $name");
      static::forceDelTable($name);
    }
  }
}
