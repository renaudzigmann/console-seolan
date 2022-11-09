<?php
namespace UnitTests;
/**
 * tests des imports / Module/Table
 * imports testés (juste 2 types de champs, TC et real)
 * par defaut de base
 * avec procedure 
 * clearbefore = false
 * clearbefore = true
 * clearbefore = true et updateifexists + 1 clé
 * @todo : plusieurs clés
 * @todo : éténdre les types de champs
 * en particulier les champs liens + création de données
 */
use \Seolan\Core\Module\Module;
use \Seolan\Core\DataSource\DataSource;
use \Seolan\Model\DataSource\Table\Table;
use \Seolan\Module\Table\Table as MTable;
use \Seolan\Module\Table\Wizard as MTableWizard;
class ModuleTableImportTests extends BaseCase {
  private static $tab1 = 'TU_TESTS_IMPORTS1';
  private static $mod1 = null;
  private static $todelete = [];
  /**
   * import basique avec une procédure par défaut
   */
  function testDeBase(){
    $modImport = static::$mod1;
    // check env
    $this->assertDSExists(static::$tab1);

    // import immédiat par la procédure par defaut, entête noms de champs
    $this->assertEquals(0, ($nb=getDB()->fetchOne("select count(*) from {$modImport->table}")), "La table de test est vide ($nb)");

    $this->assertEquals(static::$tab1, $modImport->table);

    $filename = $this->makeTab1ImportFile();
    
    // === import par default
    $modImport->import([
      'spec'=>'default',
      'fieldsname'=>'sql',
      'linestoskip'=>1
    ]);

    $this->assertEquals(2, getDB()->fetchOne("select count(*) from {$modImport->table}"), "La table de test est contient 2 lignes");

    // vérifier aussi les contenus => fait un peu plus bas avec le updateif exists

    // === import avec une spec, en ajout
    $p1id = $this->makeSpec($modImport, 'clearbefore=false', ['clearbefore'=>false]);

    $this->assertEquals(1, getDB()->fetchOne('select count(*) from IMPORTS where ID like ?', ['%clearbefore%']), "procedure d'import bien ajoutée en table");
    
    $filename = $this->makeTab1ImportFile();
    
    $modImport->import(['spec'=>$p1id]);

    $this->assertEquals(4, getDB()->fetchOne("select count(*) from {$modImport->table}"), "La table de test est contient 4 lignes");

    $filename = $this->makeTab1ImportFile();

    // === import avec clear before
    $p2id = $this->makeSpec($modImport, 'clearbefore=true', ['clearbefore'=>true]);
    
    static::$tools->sqldump("select json_detailed(spec) from IMPORTS where ID=?", [$p2id]);
        
    $modImport->import(['spec'=>$p2id]);

    $this->assertEquals(2, getDB()->fetchOne("select count(*) from {$modImport->table}"), "La table de test est contient 2 lignes");

    $filename = $this->makeTab1ImportFile();

    // === import update if exists sans clear before du coup ...
    $p3id = $this->makeSpec($modImport, 'updateifexists', ['clearbefore'=>false]);

    static::$tools->sqldump("select json_detailed(spec) from IMPORTS where ID=?", [$p3id]);
    
    $proc = getDB()->fetchRow('select * from IMPORTS where ID=?', [$p3id]);

    $specs = json_decode($proc['spec']);

    $specs->general->strategy->updateifexists = true;
    $specs->general->keys=['unreal'];

    getDB()->execute('update IMPORTS set spec=? where koid=?', [json_encode($specs), $proc['KOID']]);
    getDB()->execute("update {$modImport->table} set untextecourt=? where unreal=?",['...', 1]);
    $this->assertEquals('...', getDB()->fetchOne("select untextecourt from {$modImport->table} where unreal=?", [1]), "Valeur ... mise à jour pour unreal=1");

   Tools\Tools::sqldump("select * from {$modImport->table}", []);
    
    $modImport->import(['spec'=>$p3id]);

    Tools\Tools::sqldump("select * from {$modImport->table}", []);
    
    $this->assertEquals(1, getDB()->fetchOne("select count(*) from {$modImport->table} where unreal=?", [1]));
    $this->assertEquals('valeur 1 un texte court', getDB()->fetchOne("select untextecourt from {$modImport->table} where unreal=?", [1]), "Valeur ... mise à jour pour unreal=1");


    // === import avec les labels en en tètes de colonne
    $p4id = $this->makeSpec($modImport, 'clearbeforeandlabels', ['clearbefore'=>true,'fieldsname'=>'label']);

    static::$tools->sqldump("select json_detailed(spec) from IMPORTS where ID=?", [$p4id]);

    getDB()->execute("truncate {$modImport->table}");


    Tools\Tools::sqldump("select * from {$modImport->table}", []);

    $this->assertEquals(0, getDB()->fetchOne("select count(*) from {$modImport->table}", []));

    $filename = $this->makeTab1ImportFile(true);

    static::trace(file_get_contents($filename), true);
 

    $modImport->import(['spec'=>$p4id]);

    $this->assertEquals(2, getDB()->fetchOne("select count(*) from {$modImport->table}", []));
    
  }
  /**
   * tests avec un fichier excel
   * @depends testDeBase
   */
  function testImportExcel(){
    $modImport = static::$mod1;
    // check env
    $this->assertDSExists(static::$tab1);

    getDB()->execute("truncate table {$modImport->table}");

    $this->assertEquals(0, getDB()->fetchOne("select count(*) from {$modImport->table}", []),"table à importer excel vide");
    
    // fichier excel
    $this->makeTab1ImportFile(true, true);

    // spec de base
    $p1id = $this->makeSpec($modImport, 'defaultexcel', ['format'=>'xl07','clearbefore'=>true,'fieldsname'=>'label']);

    static::$tools->sqldump("select json_detailed(spec) from IMPORTS where ID=?", [$p1id]);

    $modImport->import(['spec'=>$p1id]);
    
    Tools\Tools::sqldump("select * from {$modImport->table}", []);

    $this->assertEquals(2, getDB()->fetchOne("select count(*) from {$modImport->table}", []));

    $this->assertEquals(1, getDB()->fetchOne("select count(*) from {$modImport->table} where lang=? and untextecourt=?",
					     [TZR_DEFAULT_LANG, 'valeur 1 un texte court XLS']),
			"valeur excel importée");
    
  }
  /**
   * génère une spec d'import de tous les champs
   * et la mets en table des imports
   */
  private function makeSpec($module, $specid, $options){
    
    $options = array_merge(['format'=>'csv',
			    'fieldsname'=>'sql',
			    'linestoskip'=>1,
			    'clearbefore'=>false], $options);

    foreach($options as $k=>$v){
      $$k=$v;
    }
    
    $specs = [
      'general'=>['format'=>$format,
		  'fieldsinheader'=>true,
		  'linestoskip'=>$linestoskip,
		  'location'=>null,
		  'strategy'=>['clearbefore'=>$clearbefore,
			       'updateifexists'=>false],
		  'keys'=>null,
      ],
      'catalog'=>[
	'fields'=>[
	  ['tzr'=>'KOID', 'name'=>'KOID'],
	  ['tzr'=>'KOID', 'name'=>'OID'],
	  ['tzr'=>'LANG', 'name'=>'LANG'],
	]
      ]
    ];
    if ($format == 'csv'){
      $specs['general']['separator']=';';
      $specs['general']['quote']='"';
      $specs['general']['endofline']="\n";
    } else {
      $specs['general']['separator']=null;
      $specs['general']['quote']=null;
      $specs['general']['endofline']=null;
    }
    foreach($module->xset->desc as $fn=>$fd){
      if($fieldsname=='label')
	$specs['catalog']['fields'][] = ['tzr'=>$fn, 'name'=>$fd->label];
      else 
	$specs['catalog']['fields'][] = ['tzr'=>$fn, 'name'=>$fn];
    }
        
    $ds = DataSource::objectFactoryHelper8('IMPORTS');

    $id = "IMPORTS TESTS TU {$specid}";

    $ret = $ds->procInput(['_options'=>['local'=>1],
			    'spec'=>json_encode($specs),
			    'modid'=>$module->_moid,
			    'remark'=>'générée par les TU',
			    'auto'=>2,
			    'ID'=>$id]);
    // on a pas besoin du koid pour les IMPORTS c'est ID qui permet les acccès 

    return $id;
  }
  // générère un fichier et le place dans $_FILES
  private function makeTab1ImportFile($labels=false, $excel=false){
    $filename = TZR_TMP_DIR.uniqid('tufileimport').($excel?'.xls':'.csv');
    $data = [['untextecourt','unreal', 'untextecourtavecaccents'],
	     ['valeur 1 un texte court', 1, 'valeur 1 tchamp label accentué'],
	     ['valeur 2 un texte court', 2, 'valeur 2 tchamp label accentué']];
    if ($labels){
      $data[0] = ['Champ texte', 'Champ numérique', 'Ch. t`êxte accentué'];
    }
    if ($excel){
      $ss=new \PHPExcel();
      $ss->setActiveSheetIndex(0);
      $ws=$ss->getActiveSheet();
      $ws->setTitle('Main');
      foreach($data as $r=>$line){
	if ($r>0)
	  $line[0].=' XLS';
	foreach($line as $c=> $cell){
	  $ws->setCellValueByColumnAndRow($c,$r+1, $cell);
	}
      }
      $objWriter=new \PHPExcel_Writer_Excel2007($ss);
      $objWriter->save($filename);
      unset($objWriter);
      unset($ss);
    } else {
      $lines=null;;
      $lines = array_reduce($data, function($lines, $item){
	$lines[] = "\"".implode('";"', $item)."\"";
	return $lines;
      }, []);
      $csv = implode("\n", $lines);
      file_put_contents($filename, $csv);
    } 
    static::$todelete[] = $filename;
    
    $_FILES=[
      'file'=>[
	'name'=>basename($filename),
	'tmp_name'=>$filename
      ]
    ];
    
    return $filename;

  }
  
  public static function setUpBeforeClass(){

    parent::setUpBeforeClass(); // !!
    
    try{ // semble nécessaire pour que les warning et autres ne remontent pas à PHPUNIT
      static::clearFixtures();
      static::createTestStructures();
    } catch(\Throwable $t){
      static::$errors[] = $t;
    }
    
  }
  /**
   * création des tables
   */
  private static function createTestStructures(){

    DataSource::clearCAche();

    static::trace(static::$tab1);
    
    try{
      Table::procNewSource(['translatable'=>'0',
			    'auto_translate'=>'0',
			    'btab'=>static::$tab1,
			    'bname'=>[TZR_DEFAULT_LANG=>'Tests Import '.static::$tab1]]);
    }catch(\Throwable $t){
      static::trace(static::$tab1);
    }

    static::trace(static::$tab1);

    DataSource::clearCAche();
    
    $dst = DataSource::objectFactoryHelper8(static::$tab1);

    $dst->procNewField(['field'=>'untextecourt',
			'ftype'=>'\Seolan\Field\ShortText\ShortText',
			'forder'=>null,
			'fcount'=>120,
			'label'=>[TZR_DEFAULT_LANG=>'Champ texte'],
			'target'=>null,
			'browsable'=>1,
			'queryable'=>1,
			'translatable'=>1,
			'multivalued'=>0,  // !!!
			'published'=>1]);

    $dst->procNewField(['field'=>'untextecourtavecaccents',
			'ftype'=>'\Seolan\Field\ShortText\ShortText',
			'forder'=>null,
			'fcount'=>120,
			'label'=>[TZR_DEFAULT_LANG=>'Ch. t`êxte accentué'],
			'target'=>null,
			'browsable'=>1,
			'queryable'=>1,
			'translatable'=>1,
			'multivalued'=>0,  // !!!
			'published'=>1]);
    $dst->procNewField(['field'=>'unreal',
			'ftype'=>'\Seolan\Field\Real\Real',
			'forder'=>null,
			'fcount'=>120,
			'label'=>[TZR_DEFAULT_LANG=>'Champ numérique'],
			'target'=>null,
			'browsable'=>1,
			'queryable'=>1,
			'translatable'=>1,
			'multivalued'=>0,  // !!!
			'published'=>1]);

    DataSource::clearCache();

    // création du module
    $wd = new MTableWizard();
    $moid = $wd->quickCreate('Module '.static::$tab1,
			     ['table'=>static::$tab1,
			      'createstructure'=>0]);

    Module::clearCache();

    static::$mod1 = Module::objectFactory(['moid'=>$moid,
					   'tplentry'=>TZR_RETURN_DATA,
					   'interactive'=>false]);

  }
  
  public static function clearFixtures(){

    static::trace(__METHOD__);
    
    getDB()->execute('delete from IMPORTS where ID like ?', ["IMPORTS TESTS TU%"]);

    foreach(static::$todelete as $filename){
      if (file_exists($filename))
	unlink($filename);
    }

    Module::clearCache();

    static::trace("delete tests data and structures");
    static::forceDelModule('Module '.static::$tab1);

    // manque les proc, trigger table de liaison ?
    foreach([static::$tab1] as $name){
      static::trace("delete $name");
      static::forceDelTable($name);
    }

  }
  
}
