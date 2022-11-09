<?php
namespace Seolan\Library;
class Database extends \PDO{
  private $cache=array();
  static private $connections=array();
  static private $cache_activated=true;

  /// Récupère une connexion de base de données
  public static function instance($name='master'){
    if(!isset(self::$connections[$name])) self::initialize($name,null,null,null,null);
    return self::$connections[$name];
  }
  /// Initialise une connexion
  public static function initialize($name,$dsn,$user,$passwd,$options){
    if(empty($dsn)){
      @list($host,$port)=explode(':',$GLOBALS['DATABASE_HOST']);
      $dsn='mysql:host='.$host.';dbname='.$GLOBALS['DATABASE_NAME'];
      if($port) $dsn.=';port='.$port;
    }
    if(empty($user)) $user=$GLOBALS['DATABASE_USER'];
    if(empty($passwd)) $passwd=$GLOBALS['DATABASE_PASSWORD'];
    if(!is_array($options)) $options=array();
    $options=$options+array(
      \PDO::ATTR_PERSISTENT=>false,
      \PDO::MYSQL_ATTR_INIT_COMMAND=>'SET NAMES utf8',
      \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true,
      \PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC
    );
    try{
      $conn=new Database($dsn,$user,$passwd,$options);
    }catch(\Exception $e) {
      \Seolan\Core\Logs::critical('Database::initialize','error '.$e->getMessage());
      die();
    }

    if (TZR_TIMEZONE != 'Europe/Paris') {
      $tz=\Seolan\Core\Config::load('timezones')->get(TZR_TIMEZONE);
      if($tz) $conn->exec("SET time_zone=\"".$tz->get(date("I"))."\"");
    }
    
    self::$connections[$name]=$conn;
  }
  
  /// Retourne la 1ere colonne de la 1ere ligne de resultat
  public function fetchOne($q,$params=array(),$log=true){
    return $this->executeForFetch($q,$params,$log)->fetchColumn(0);
  }
  /// Retourne la 1ere ligne du resultat
  public function fetchRow($q,$params=array(),$log=true){
    return $this->executeForFetch($q,$params,$log)->fetch();
  }
  /// Retourne la colonne $col de toutes les lignes du resultat
  public function fetchCol($q,$params=array(),$col=0,$log=true){
    return $this->executeForFetch($q,$params,$log)->fetchAll(\PDO::FETCH_COLUMN,$col);
  }
  /// Retourne tout le resultat
  public function fetchAll($q,$params=array(),$log=true){
    \Seolan\Core\Audit::plusplus('fetch-all');
    return $this->executeForFetch($q,$params,$log)->fetchAll();
  }
  /// Retourne vrai si la requête retourne au moins un résultat
  public function fetchExists($q,$params=array(),$log=true){
    $row=$this->fetchRow($q,$params,$log);
    return !empty($row);
  }

  /// Prépare et execute une requête puis retourne le nombre de lignes affectées ou le statement
  public function execute($q,$params=array(),$journalize=true,$returncount=true,$log=true){
    \Seolan\Core\Audit::plusplus('update-queries');
    if ($log)
      \Seolan\Core\Logs::debug('\Seolan\Library\Database::execute:'.$q.' ('.implode(',',$params).')');
    $sth=$this->prepareAndCache($q);
    if(!$sth->execute($params)) $this->critical('execute.execute',$q,$params,$sth);
    if(isset($GLOBALS['XREPLI']) && $journalize){
      $qs=$GLOBALS['XREPLI']->marshallQuery($sth->queryString,$params);
      $GLOBALS['XREPLI']->journalize('sql', $qs);
    }
    if($returncount) return $sth->rowCount();
    else return $sth;
  }
  /// ? à voir 
  public function exec($q, $log=true){
    if($log)
      \Seolan\Core\Logs::debug(__METHOD__.' '.$q);
    $r = parent::exec($q);
    if($log){
      list($sqlstate, $errcode, $errmess) = \PDO::errorInfo();
      \Seolan\Core\Logs::debug(__METHOD__." $sqlstate $errcode $errmess $r");
    }
    return $r;
  }
  /**
   * Prépare et execute une requête puis retourne le PDOStatement
   * Contrairement aux autres méthodes, le cache ne peut être activé par défaut car le resultat n'est pas parcouru immédiatement
   * De ce fait, un troisième paramètre permet de gérer le cache et ne doit être spécifié que si le contenu du statement resultant de
   * l'execution n est fini d'être utilisé au moment de l'execution n+1
   * @param string $q
   * @param array $params
   * @param string $cache_name
   * @param int $fetch_mode
   * @param bool $log
   * @return \PDOStatement
   */
  public function select($q,$params=array(),$cache_name=false,$fetch_mode=NULL,$log=true){
    \Seolan\Core\Audit::plusplus('select-queries');
    if($log)
      \Seolan\Core\Logs::debug('\Seolan\Library\Database::select():'.$q.' ('.implode(',',$params).')');
    $sth=$this->prepareAndCache($q,$cache_name);
    if(!$sth->execute($params)) $this->critical('select.execute',$q,$params,$sth);
    if($fetch_mode) $sth->setFetchMode($fetch_mode);
    return $sth;
  }

  /**
    * Execute et formate une requete pour compter le nombre de ligne
    * Faire des /U (ungreedy) évite de matcher jusque dans les sous requêtes
    */
  public function count($q,$params=array(),$format=false){

    \Seolan\Core\Audit::plusplus('count-select-queries');
    // Format un select en select count
    if($format){
      $r=array();
      // Dans le cas d'un distinct, reformate l'intérieur du count pour le prendre en compte, sinon simple count(*)
      if(stripos($q,'distinct')==7){
	preg_match('/^select distinct (.*) from/Ui',$q,$r);
	if(strpos($r[1],'*')!==false){
	  if(strpos($r[1],'.*')!==false) $r[1]=preg_replace('/(\w)\.\*/Ui','$1.KOID',$r[1]);
	  else $r[1]='KOID';
	  $q=preg_replace('/^select distinct (.*) from/Ui','SELECT COUNT(DISTINCT '.$r[1].') FROM',$q,1);
	}elseif(preg_match('/from .* join .*/i', $q)){
          $q=preg_replace('/^select distinct (.*) from (\S*) (.*)/Ui','select count(distinct $2.KOID) from $2 $3',$q);
	}else{
	  $q = preg_replace_callback('/^select distinct (.*) from/Ui', function($matches) {
            // On ne peut pas faire de "as" à l'intérieur d'un count
            $match = preg_replace('/ as \w+(\W)/i', '$1', $matches[1]);
            return 'SELECT COUNT(DISTINCT '.$match.') FROM';
          }, $q, 1);
	}
      }else if(strpos($q,'STRAIGHT_JOIN')==7){
	$q=preg_replace('/^select STRAIGHT_JOIN (.*) from/Ui','SELECT STRAIGHT_JOIN COUNT(*) FROM',$q,1);
      }else{
	$q=preg_replace('/^select (.*) from/Ui','SELECT COUNT(*) FROM',$q,1);
      }
    }
    $v=$this->fetchCol($q,$params);
    if(count($v) > 1)       return count($v);
    elseif(count($v) == 1)  return $v[0];
    else                    return 0;
  }

  /// Emet un log critique avec le contexte
  private function critical($m,&$q,&$params,$sth){
    $sql_error_message = $this->getErrorMessage($sth);
    \Seolan\Core\Logs::critical(__METHOD__.":$m ($q : ".implode(',',$params).") error: $sql_error_message Trace: \n".
				print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
    throw new \Exception('SQL Error: '.$sql_error_message);
  }

  /// Prépare et cache une requete
  private function prepareAndCache($q,$cache_name=NULL){
    if($cache_name===NULL) $cache_name=$q;

    if(!$cache_name || empty($this->cache[$cache_name])){
      \Seolan\Core\Audit::plusplus('prepare');
      $sth=$this->prepare($q);
      if(self::$cache_activated && $cache_name) {
	static $cache_size=0;
	$this->cache[$cache_name]=$sth;
	if($cache_size++> (TZR_MEMCACHE_MAXSIZE/100)) {
	  $this->cache=array();
	  $cache_size=0;
	}
      }
      if(!$sth) $this->critical('prepareAndCache.prepare',$q,$params,$sth);
    }else{
      \Seolan\Core\Audit::plusplus('prepare-use-cache');
      $sth=$this->cache[$cache_name];
    }
    return $sth;
  }

  /// Prépare et exécute une requête en vue d'un fetch immédiat
  private function executeForFetch(&$q,&$params,$log=true){
    \Seolan\Core\Audit::plusplus('select-queries');
    if($log)
      \Seolan\Core\Logs::debug('\Seolan\Library\Database::executeForFetch():'.$q.' ('.implode(',',$params).')');
    $sth=$this->prepareAndCache($q);
    if(!$sth->execute($params)) $this->critical('executeForFetch.execute',$q,$params,$sth);
    return $sth;
  }

  /// Retourne une requete d'insertion pour une table et un tableau de données
  function &getInsertQuery($table,$record) {
    foreach($record as &$v) $v = $v === null ? 'NULL' : $this->quote($v);
    $insertSQL="INSERT INTO `$table`(".implode(',',array_keys($record)).') values('.implode(',',array_values($record)).')';
    return $insertSQL;
  }

  /// Active le cache
  public static function activateCache(){
    self::$cache_activated=true;
  }

  /// Desactive le cache
  public static function deactivateCache(){
    self::$cache_activated=false;
  }
  
  /**
   * @desc Retourne le message d'erreur du PDOStatement si il y en a un
   * @param PDOStatement $statement
   * @return String
   */
  private function getErrorMessage(\PDOStatement $statement)
  {
    if (($sql_error = $statement->errorInfo()))
      // @see http://www.php.net/manual/en/pdo.errorinfo.php#refsect1-pdo.errorinfo-returnvalues
      if (array_key_exists(2, $sql_error))
        return $sql_error[2];
      
    return Null;
  }
  
  /// Applique un dump SQL sur la base
  function loadSQLDump($database, $opts, $filename){
    list($host, $port)=explode(':',$database["host"]??$GLOBALS["DATABASE_HOST"]);
    if(empty($port)) $port="3306";
    $databasename=$database["name"]??$GLOBALS["DATABASE_NAME"];
    $databasepassword=$database["password"]??$GLOBALS["DATABASE_PASSWORD"];
    $databaseuser=$database["user"]??$GLOBALS["DATABASE_USER"];
    
    \Seolan\Core\Logs::notice(__METHOD__,$filename);
    if(!empty($opts['del'])){
      \Seolan\Core\Logs::notice(__METHOD__," drop tables, procedures and functions");
      // enlever d'abord les tables de liens avec clés étrangères
      // sans quoi  => erreur d'intégrité
      foreach(getTablesWithForeignKeys() as $tableName){
	getDB()->execute("DROP TABLE `{$tableName}`",[],false);
      }
      // enlever les autres tables
      foreach(getMetaTables() as $table){
        // On ne supprime pas LOGS car le dump ne la contient pas forcement
        if($table['table']!='LOGS' && $table['type'] != 'VIEW')
	  getDB()->execute("DROP TABLE IF EXISTS `{$table['table']}`",[],false);
	else
	  if ($table['type'] != 'VIEW')
	    getDB()->execute("DROP VIEW IF EXISTS `{$table['table']}`",[],false);
      }
      // enlever les procedures et fonctions (les triggers sont détruits avec leur table associée)
      foreach(getRoutines('PROCEDURE') as $pname){
	getDB()->execute("DROP PROCEDURE IF EXISTS `{$pname}`",[],false);
      }
      foreach(getRoutines('FUNCTION') as $fname){
	getDB()->execute("DROP FUNCTION IF EXISTS `{$fname}`",[],false);
      }
    }
    system("mysql -u".escapeshellarg($databaseuser)." -p".escapeshellarg($databasepassword)." ".
           "-h".escapeshellarg($host).(!empty($port)?" -P".escapeshellarg($port):"")." ".escapeshellarg($databasename).
	   " < $filename");
    if(!empty($opts['delfileafter'])) unlink($filename);
    return true;
  }

  /// Créé un dump SQL de la base
  function createSQLDump($database, $opts, $filename) {

    list($host, $port)=explode(':',$database["host"]??$GLOBALS["DATABASE_HOST"]);
    if(empty($port)) $port="3306";
    $databasename=$database["name"]??$GLOBALS["DATABASE_NAME"];
    $databasepassword=$database["password"]??$GLOBALS["DATABASE_PASSWORD"];
    $databaseuser=$database["user"]??$GLOBALS["DATABASE_USER"];
    

    $cmd=TZR_MYSQLDUMP_PATH." --routines ";
    // Pas de dump des data
    if(!empty($opts["no_data"])) $cmd.=" --no-data";
    // Ajout drop table
    if(!empty($opts["drop_table"])) $cmd.=" --add-drop-table";
    // Pas de create table
    if(!empty($opts["no_create"])) $cmd.=" --no-create-info";
    // Ignore les logs
    if(!empty($opts["no_logs"])) $cmd.=" --ignore-table ".escapeshellarg("$databasename.LOGS");
    // Users, db...
    $cmd.=" -u".escapeshellarg($databaseuser)." -p".escapeshellarg($databasepassword)." ".
      "-h ".escapeshellarg($host)." -P ".escapeshellarg($port)." ".escapeshellarg($databasename);
    // Tables spécifiques
    if(!empty($opts["tables"])) $cmd.=" ".implode(" ",$opts["tables"]); 
    // Suppressiondu 'definer'
    
    $cmd.=" | sed -E -e 's/CREATE DEFINER[^ ]+ (VIEW|PROCEDURE|FUNCTION|TRIGGER)/CREATE \\1/' -e 's/DEFINER[ ]*=[ ]*[^*]*\*/\*/'";
    // Sortie
    
    $cmd.=" > $filename ";
    
    \Seolan\Core\Logs::debug(__METHOD__."$cmd");
    
    system($cmd);
    
  }

}
