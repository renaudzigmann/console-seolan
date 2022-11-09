<?php
namespace UnitTests;

use \PHPUnit\Framework\TestCase;
use \Seolan\Core\DataSource\DataSource;
use \Seolan\Core\Module\Module;
use \Seolan\Model\DataSource\Table\Table;
/**
 * Classe de base pour tests unitaire
 * -> outils, asssertion dédiées
 */
class BaseCase extends TestCase{
  public static $tools = null;
  public static $errors = null;
  protected static $logfile = null;
  protected static $traceActive = null;
  protected static $keepstructures = false;
  protected static $debug = false;
  protected $testInfoTree = null;
  protected static $unittestuserkoid = 'USERS:TZRUNITTESTS';
  /**
   * initialise un shell à la demande, par défaut il y est pas 
   * (voir config.php)
   * mettre la dedans toutes les méthodes nécessaires et rien de plus
   */
  public static function initMockShell(){
    if (!isset($GLOBALS['XSHELL']))
      $GLOBALS['XSHELL'] = new Class(){
	function __construct(){
	  $this->labels = new \Seolan\Core\Labels();
	}
	static function _function(){
	  return null;
	}
      };
  }
  /**
   * récupère un gestionnaire de rubrique pour des tests divers
   */
  protected function getTestInfoTree() {
    if (isset($this->testInfoTree))
      return $this->testInfoTree;
    $this->testInfoTree = Module::singletonFactory(XMODBACKOFFICEINFOTREE_TOID);
    $oidhome = getDB()->fetchOne("select koid from {$this->testInfoTree->table} where alias=?", [
        'home'
    ]);
    $oidtop = getDB()->fetchOne("select koid from {$this->testInfoTree->table} where alias=?", [
        'top'
    ]);
    $oidbottom = getDB()->fetchOne("select koid from {$this->testInfoTree->table} where alias=?", [
        'bottom'
    ]);
    return [$this->testInfoTree, 
        ['home'=>$oidhome, 
            'top'=>$oidtop,
            'bottom'=>$oidbottom]
        ];
  }
  function assertArraySameValues(array $arr1, array $arr2){
    $this->assertThat($arr2, new Constraint\ArraySameValues($arr1));
  }
  
  function assertTZRFileExists(\Seolan\Core\Field\Value $fieldValue){
    $this->assertThat(true, new Constraint\TZRFileExists($fieldValue, 'exists'));
  }
  function assertTZRFileNotExists(\Seolan\Core\Field\Value $fieldValue){
    $this->assertThat(false, new Constraint\TZRFileExists($fieldValue, 'not exists'));
  }
  function assertFileContentsOK(\Seolan\Core\Field\Value $fieldValue){
    if ($content !== null)
      $this->assertThat($contents, new Constraint\FileContentsOK($fieldValue));
  }
  function assertMailContainsAddress(\Seolan\Library\Mail $mail, $other){
    $this->assertThat($other, new Constraint\MailContainsAddress($mail));
  }
  function assertFieldExists($ds, $fn){
    $this->assertThat($fn, new Constraint\FieldExists($ds, true));
  }
  function assertFieldNotExists($ds, $fn){
    $this->assertThat($fn, new Constraint\FieldExists($ds, false));
  }
  function assertFieldIsDeleted($ds, $fn){
    $this->assertThat($fn, new Constraint\FieldIsDeleted($ds));
  }
  function assertSQLTableExists($name){
    $this->assertThat($name, new Constraint\TableExists(true));
  }
  function assertSQLTableNotExists($name){
    $this->assertThat($name, new Constraint\TableExists(false));
  }
  function assertDSExists($name){
    $this->assertThat($name, new Constraint\DSExists(true));
  }
  function assertObjectExists($oid){
    $this->assertThat($oid, new Constraint\ObjectExists(true));
  }
  function assertSQLColumnExists($column_name,$table_name){ //COLUMNS
    $this->assertThat($column_name, new Constraint\ColumnExists($table_name));
  }
  function assertFieldOk($column_name,$table_name, $field_desc=null){
    if ($field_desc == null)
      $this->assertThat($column_name, new Constraint\FieldOk($table_name));
    else
      $this->assertThat([$column_name, $field_desc], new Constraint\FieldOK($table_name));
  }
  function assertSQLFieldHasValue($field_name,$field_value,$table_name,$and=null){
    $this->assertThat($field_value, new Constraint\SQLFieldHasValue($field_name, $table_name,$and));
  }
  function assertSQLRowIsOk($table_name, array $primaryKey, array $ColumnsAndExpectedValues){
    $this->assertThat($primaryKey, new Constraint\SQLRowIsOk($table_name, $ColumnsAndExpectedValues));
  }
  protected function initCase($name){
    static::trace($name);
  }
  /// 
  public static function trace($message, $title=false, $source=null){

    if (!static::$traceActive)
      return;
   
    if (static::$logfile != null)
      $out = static::$logfile;
    else
      $out = STDOUT;
   
    if (!is_string($message)){
      ob_start();
      var_dump($message);
      $message = ob_get_contents();
      ob_end_clean();
    }
    if (is_array($source)){
      list($file, $line) = $source;
      $prefix = ">>> ($file,$line)";
    } else
      $prefix = '>>> ';
    if ($title)
      fwrite($out, str_pad("\n", strlen("===> $message"), "=")."");
    fwrite($out, "\n$prefix $message");
    if ($title)
      fwrite($out, str_pad("\n", strlen("===> $message"), "=")."");
  }
  public static function  setUpBeforeClass(){
    if (!defined('TZR_UNITTESTS'))
      define('TZR_UNITTESTS', 1);
    if (!defined('TZR_UNITTESTS_DEBUG'))
      define('TZR_UNITTESTS_DEBUG', 0);
    if (!defined('TZR_UNITTESTS_KEEPSTRUCTURES'))
      define('TZR_UNITTESTS_KEEPSTRUCTURES', 0);
    if (!defined('TZR_UNITTESTS_ASROOT'))
      define('TZR_UNITTESTS_ASROOT', 1);
    if (!defined('TZR_UNITTESTS_TRACEACTIVE'))
      define('TZR_UNITTESTS_TRACEACTIVE', 0);
    
    static::$tools = new Tools\Tools();
    
    //set_error_handler(function($errno, $errstr, $errfile, $errline) {
    //throw new \Error($errstr . " on line " . $errline . " in file " . $errfile);
    //});
    // création et possitionnement du user de tests
    static::setUser();
   
    if (defined('TZR_UNITTESTS_DEBUG') && TZR_UNITTESTS_DEBUG == 1){
      static::$debug=true;
      if (defined('TZR_UNITTESTS_KEEPSTRUCTURES') && TZR_UNITTESTS_KEEPSTRUCTURES == 1)
	    static::$keepstructures=true;
    }
    if (static::$traceActive === null){
      static::$traceActive = defined('TZR_UNITTESTS_TRACEACTIVE')
			  && (TZR_UNITTESTS_TRACEACTIVE == 1);
    }
    if (static::$traceActive && defined('TZR_UNITTEST_LOGS')){
      $log = TZR_UNITTEST_LOGS;
      if (!empty($log))
	static::$logfile=fopen($log, 'a');
      else
	static::$logfile=null;
    }
  }
  /**
   * positionnement d'un user pour les tests
   * @descr : le KOID est toujours le même : USERS:TZRUNITTESTS
   * à fin de pouvoir nettoyer a table des logs à la fin
   * On peut par contre le positionner en tant q'u'admin ou tout autre groupe
   */
  protected static function setUser(string $alias='TzrUnitTest', string $fullnam='Tzr Unit Tests'){
    $grps = static::getUnitTestUserGrps();
    getDB()->execute(
        'replace into USERS '
        .'(koid, lang, alias, fullnam, grp, ldata, luser, directoryname,email) '
        .'values(?,?,?,?,?,?,?,?,?)',
        [static::$unittestuserkoid, TZR_DEFAULT_LANG, $alias, $fullnam, $grps, TZR_DEFAULT_LANG, TZR_DEFAULT_LANG, 'local', defined('TZR_UNITTESTS_EMAIL')? TZR_UNITESTS_EMAIL:TZR_DEBUG_ADDRESS] // définissez votre mail dans le fichier local.php
     );
    setSessionVar('UID', static::$unittestuserkoid);
    $GLOBALS['XUSER'] = new \Seolan\Core\User(static::$unittestuserkoid);
    
  }
  protected static function getUnitTestUserGrps(){
    return '||GRP:1||';
  }
  /// ran before each case method
  function setUp(){
    parent::setUp();
    static::$errors = [];
    DataSource::clearCache();
    Module::clearCache();
  }
  function tearDown(){
    static::flushErrors();
  }
  public static function tearDownAfterClass(){
    //set_error_handler();
    static::_clearFixtures();
    if (static::$logfile != null)
      fclose(static::$logfile);
    getDB()->execute('delete from USERS where koid=?', [static::$unittestuserkoid]);
    getDB()->execute('delete from LOGS where user=?', [static::$unittestuserkoid]);
  }
  /// nettoyage des tables etc 
  private static function _clearFixtures(){
    if (!static::$keepstructures){
      static::trace('clearing fixtures', true);
      static::clearFixtures();
    } else {
      static::trace('skipping clearFixtures (constant TZR_UNITTESTS_DEBUG && )');
    }
  }
  public static function clearFixtures() {
  }
  function flushErrors() {
    if (count(static::$errors) > 0){
      static::trace("===================================");
      static::trace(sprintf("% 3d", count(static::$errors)) . " error during setup or test !!!!");
      static::trace("===================================");
      foreach($this->errors as $err){
        static::trace($err->getMessage() . " line : " . $err->getLine() . " in : " . $err->getFile());
        static::trace($err->getTraceAsString());
      }
      static::trace("===================================");
    }
    static::$errors = [];
  }
  // essai de delete std et nettoyage des tables
  public static function forceDelModule($name){

    foreach(getDB()->fetchCol('select moid from MODULES where module=?', [$name]) as $moid){
      if (!empty($moid)){
	$module = Module::objectFactory(['moid'=>$moid,
					 'tplentry'=>TZR_RETURN_DATA,
					 'interactive'=>false]);
	if (isset($module)){
	  static::trace("delete module using table {$module->_moid} {$module->getLabel()}");
	  $module->delete(['withtable'=>false,'tplentry'=>TZR_RETURN_DATA]);
	}
	getDB()->execute('DELETE FROM MODULES where MOID=?', [$moid]);
	getDB()->execute('DELETE FROM AMSG where MOID=?', ["module:{$moid}':comment"]);
      }
    }
  }
  public static function forceDelTable($name) { // method permetant de suprimer une table
    if (empty(trim($name)))
      return;
    try{
      $mods = Module::modulesUsingTable($name, true, false, false);
      static::trace("delete modules using table $name : " . implode(',', array_keys($mods)));
      foreach($mods as $moid=>$modname){
        // static::trace("delete module $moid");
        // $mod = Module::objectFactory(['moid'=>$moid,'interactive'=>false,'tplentry'=>TZR_RETURN_DATA]);
        // $mod->delete(['withtable'=>false,'tplentry'=>TZR_RETURN_DATA]);
      }
      if (DataSource::sourceExists($name, true)){
        $ds = DataSource::objectFactoryHelper8($name);
        $ds->procDeleteDataSource([]);
      }
      // pour les base en vrac ...
      // nettoyer les tables de relation avant (<- foreign key)
      foreach(getDB()->fetchCol("show tables like \"$name\\_%\"") as $tn){
        if (! DataSource::sourceExists($tn, true)){
          getDB()->exec("drop table if exists $tn");
        }
      }

      getDB()->exec("drop table if exists {$name}");

      getDB()->exec("drop table if exists A_{$name}");
      
      getDB()->execute("delete from DICT where dtab=?", [
          $name
      ]);
      getDB()->execute("delete from MSGS where mtab=?", [
          $name
      ]);
      getDB()->execute("delete from BASEBASE where btab=?", [
          $name
      ]);
      getDB()->execute('DELETE FROM AMSG where MOID LIKE ?', [
          "$name:%:%"
      ]);
      getDB()->execute('DELETE FROM SETS where STAB=?', [$name]);

      $dir = "{$GLOBALS['DATA_DIR']}$name";
      if (file_exists($dir)){
	\Seolan\Library\Dir::unlink($dir, false, false);
      }
      $adir = "{$GLOBALS['DATA_DIR']}A_$name";
      if (file_exists($adir)){
	\Seolan\Library\Dir::unlink($adir, false, false);
      }
      
    } catch(\Throwable $t){}
  }
  public function getRandTradLang(){
    $langs = $GLOBALS['TZR_LANGUAGES'];
    unset($langs[TZR_DEFAULT_LANG]);
    $langs = array_keys($langs);
    return $langs[rand(0, count($langs)-1)];
  }
  protected static function createModuleTable($name, $btab, $options){
    $options['group'] = 'TU Group ';
    $options['table'] = $btab;
    // description ?
    $wd = new \Seolan\Module\Table\Wizard();
    $moid = $wd->quickCreate($name, $options);
    Module::clearCache();
    return $moid;
  }
  protected static function createDataSource(string $bname, string $btab, array $fields){
    $dt = Table::procNewSource([
      'translatable' => '0',
      'publish' => '0',
      'auto_translate' => '0',
      'tag'=>1,
      'btab' => $btab,
      'bname' => [TZR_DEFAULT_LANG=>"(TU table) $bname"]
    ]);
      
    DataSource::clearCache();
    
    $ds = DataSource::objectFactoryHelper8($btab);

    foreach($fields as $fd){
      list($name, $label, $type, $fcount, $ord, $obl, $que, $bro, $tra, $mul, $pub, $target, $opts) = $fd;
      $ds->createField(
	$name,
	$label,
	$type,
	//fcount ord obl que bro tra mul pub tar
	$fcount,
	$ord,
	$obl,
	$que,
	$bro,
	$tra,
	$mul,
	$pub,
	$target
      );
      if (!empty($opts)){
	DataSource::clearCache();
	$ds = DataSource::objectFactoryHelper8($btab);
	$ds->procEditField([
          'field'=>$name,
          'options'=>$opts
	]);
      }
    }
    DataSource::clearCache();
  }
}
