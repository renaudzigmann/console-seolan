<?php
namespace UnitTests;
use \Seolan\Core\Module\Module;
use \Seolan\Library\SolR\{Search,SearchV2,Helper};
use \Seolan\Core\{Ini,DbIni};
use \Seolan\Core\DataSource\DataSource;

/**
 * tests recherche et solr
 * - paramétrer un coeur et activer dans local.ini
 * le coeur doit exister dans l'instance de solr
 * - vérification de version
 * - status/info d'un coeur
 * - création d'une table avec 1 champ fichier et 1 champ texte indexes
 * - ajout de fiches
 * - vérification de l'indexation et de la recherce
 */

class SearchAndSolrTests extends BaseCase {

  protected static $modName = 'SolR test module';
  protected static $tabName = 'SOLRTESTTABLE';
  protected static $testMod = null;

  function testDetectVersion(){
    //$this->clearFixtures();
    
    static::trace(__METHOD__,__METHOD__);
    
    //DbIni::getStatic('solr_v2_ready'), DbIni::getStatic('solr_v2_core_ready'));
    
    DbIni::clearStatic('solr_v2_ready');

    static::$tools->sqldump('select name, value, upd from _STATICVARS where name like ?', ["solr_v2_ready"]);

    //getDB()->execute('delete from _STATICVARS where name=?', ['solr_v2_ready']);
    
    $classname = $GLOBALS['TZR_SEARCH_MANAGER2'];

    Helper::checkVersion();

    static::$tools->sqldump('select name, value, upd from _STATICVARS where name like ?', ["solr_v2_ready"]);

    $v2 = $classname::v2Ready();

    static::$tools::var_dump($v2,"v2 ready ?");
    
    static::$tools->sqldump('select name, value, upd from _STATICVARS where name like ?', ["solr_v2_ready"]);
    
    $this->assertTrue($v2,"solr v2 ready");
    
  }
  /**
   * @depends testDetectVersion
   */
  function testSearchModule(){

    static::trace(__METHOD__,__METHOD__);
    
    $search = Search::objectFactory();

    static::trace(get_class($search));

    $this->assertEquals('Seolan\Library\SolR\SearchV2',get_class($search),"Instance du moteur de recherche ");
  
    $mod = Module::singletonFactory(XMODSEARCH_TOID);

    $this->assertTrue(!empty($mod), "Module de recherche");
    
    $this->assertTrue(is_a($mod, \Seolan\Module\Search\Search::class),
			   "Classe du module de recherche is a '".\Seolan\Module\Search\Search::class."'");
    
  }
  /**
   * @depends testSearchModule
   */
  function testStatus(){

    static::trace(__METHOD__,__METHOD__);

    $search = Search::objectFactory();
    
    static::$tools::var_dump($s = $search->detailCoreStatus());

    $this->assertTrue((isset($s['status']) && $s['status']=='ok'),'status ok');
    
  }
  /**
   * optim après mises à jour (+/-) test des optimize et commit
   */
  private static function searchValidate($search){
    $search->optimize();
    $search->commit();
  }
  /**
   * insertion + indexation
   * recherche 
   * suppression via db puis recherche => suppression dans SolR
   * suppression via DS puis recherche => doit avoir été supprimé dans SolrR déjà
   * @depends testStatus
   */
  function testSearchAndIndexation(){

    static::trace(__METHOD__,__METHOD__);
    
    $search = Search::objectFactory();
    
    $table = static::$tabName;
    
    getDb()->execute("truncate $table");

    $now = date('YmdHis').rand(10,99);
    $a = "{$table}:a{$now}";
    $b = "{$table}:b{$now}";
    $c = "{$table}:c{$now}";
    $d = "{$table}:d{$now}";
    
    $ds = DataSource::objectFactoryHelper8($table);
    foreach([$a,
	     $b,
	     $c,
	     $d] as $newoid){
 
      $ds->procInput(['newoid'=>$newoid,
		      'textin'=>'textein '.'other'.str_replace($table,'', $newoid),
		      'textout'=>'texteout '.'other'.str_replace($table,'', $newoid)
	// file, todo
      ]);
    }

    static::$tools->sqldump("select * from $table");
    
    $mod = static::$testMod;

    $before = $search->detailCoreStatus();

    DbIni::clear('lastindexation_'.$mod->_moid);
    
    $mod->_buildSearchIndex($search, false, 20);

    static::searchValidate($search);
    
    // pour test compatibilite V1
    $search->index->optimize();
    $search->index->commit();
    
    $after = $search->detailCoreStatus();
    
    $added = $after['numberofdocs'] - $before['numberofdocs'];

    $this->assertEquals(4, $added, "4 document ajoutés après buildsearchindex sur le module {$mod->getLabel()} {$mod->table}");

    
    foreach([$a=>true, $b=>true, $c=>true, $d=>true, 'not_exists_oid'=>false] as $oid=>$e){
      $exists = $search->docExists($oid, $mod->_moid, TZR_DEFAULT_LANG);
      $this->assertEquals($e, $exists, "document {$oid} ajouté");
    }
    unset($GLOBALS['XSHELL']);
    //    var_dump($GLOBALS['XSHELL']);
    //    return;
    // à voir 
    $this->initMockShell(true);
    
    $res = $search->globalSearch(['query'=>'textein',
				  'moidfilter'=>[$mod->_moid]]);

    
//    static::$tools::var_dump(array_keys($res['modules'][$mod->_moid]),'result array keys');
    static::$tools::var_dump($res['modules'][$mod->_moid]['count']??'N/A','Nbre de doc trouvé (count) pour le module {$mod->_moid}');

    $this->assertEquals($res['modules'][$mod->_moid]['count'], 4, "nbre de doc recherche 'textein'");
    // voir title ? notices et autres ?
    
    getDB()->execute("delete from {$table} where koid=?", [$b]);

    // les oid présent solr absents en base ne doivent pas remonter
    $res = $search->globalSearch(['query'=>'textein','moidfilter'=>[$mod->_moid]]);

    static::$tools::var_dump($res['modules'][$mod->_moid]['count'],'count');
    
    $this->assertEquals($res['modules'][$mod->_moid]['count'], 3, "nbre de doc recherche 'textein'  3");

    static::searchValidate($search);

    $before = $search->detailCoreStatus() ;
    
    $search->deleteItem($c, $mod->_moid, TZR_DEFAULT_LANG);

    static::searchValidate($search);
   
    $res = $search->globalSearch(['query'=>'textein','moidfilter'=>[$mod->_moid]]);
    
    $after = $search->detailCoreStatus();

    // on a viré b et c => res = 2
    $this->assertEquals(1, $before['numberofdocs']-$after['numberofdocs'], "suppression directe de 1 document");
    $this->assertEquals($res['modules'][$mod->_moid]['count'], 2, "nbre de doc recherche 'textein' 2");

    $beforeNb =  $search->globalSearch(['query'=>'*','moidfilter'=>[$mod->_moid]])['modules'][$mod->_moid]['count'];
    $beforeTot = $search->detailCoreStatus()['numberofdocs'];
    
    $search->deleteQuery("moid:{$mod->_moid}");

    static::searchValidate($search);

    // à voir quand trouve rien => ne retourne aucune entrée pour le module
    $afterNb =  $search->globalSearch(['query'=>'*','moidfilter'=>[$mod->_moid]])['modules'][$mod->_moid]['count']??null;
    $afterTot = $search->detailCoreStatus()['numberofdocs'];

    static::trace($beforeNb.' '.$afterNb);
    static::trace($beforeTot.' '.$afterTot);

    $this->assertEquals($afterTot, $beforeTot-$beforeNb, "suppression de toutes les lignes pour le module de tests");
    
  }
  function __testGlobalSearchA(){
    $search = Search::objectFactory();
    $res = $search->globalSearch(['query'=>'richard','moidfilter'=>[206,19]]);
    
    var_dump(array_keys($res));
    var_dump($res);
    
    foreach($res['modules'] as $moid=>$mres){
      echo("\n".__METHOD__.", found : {$moid} => {$mres['count']}");
    }
    echo("\n\n");
  }
  public static function setUpBeforeClass(){
    parent::setUpBeforeClass();
    // crétion table
    static::createDataSource(static::$tabName, 
			     static::$tabName,
			     [
			       ['textin', 'Texte', '\Seolan\Field\ShortText\ShortText',
				64, 10, 1, 0, 1, 1, 0, 1, null, ['insearchengine'=>1]],
			       ['textout', 'Texte', '\Seolan\Field\ShortText\ShortText',
				64, 15, 1, 0, 1, 1, 0, 0, null, ['insearchengine'=>0]],
			       ['filein', 'Ficher', '\Seolan\Field\File\File',
				null, 20, 1, 0, 1, 1, 0, 0, null, ['insearchengine'=>1]]
    ]);
    // ajout d'un module dessus
    // faudrait uassi un module insearch = false  ?
    $moid = static::createModuleTable(static::$modName, static::$tabName,['insearchengine'=>1]);
    static::$testMod = Module::objectFactory(['moid'=>$moid,
					      'tplentry'=>TZR_RETURN_DATA,
					      'interactive'=>false]);
  }
  public static function clearFixtures(){

    parent::clearFixtures();
    
    static::forceDelModule(static::$modName);

    static::forceDelTable(static::$tabName);
    
  }
    
}
