<?php
namespace Seolan\Field\Link;
use \Seolan\Core\System;
/**
 * Tools for Link normalization
 */
class Normalizer {
  protected static $dbversions = ['innodb_version'=>'10.3.18']; // add mysql version if necessary ?
  public static $targetLimit = 1000;
  public static $version='1.2';
  /**
   * version requise
   */
  public static function versionOK(){
    foreach(static::$dbversions as $varname=>$minvalue){
      $v =getDB()->fetchRow("show variables like '$varname'");
      if ($v && version_compare($minvalue, $v['Value'], '<='))
	return true;
    }
    return false;
  }
  /**
   * Field Normalization
   * -> check tables engine (InnoDB required)
   * -> create relation table, triggers and procedures
   * -> populate relation table
   * -> update DICT 
   */
  public static function normalize(\Seolan\Field\Link\Link $field){
    // engine
    static::checkEngine($field->table);
    static::checkEngine($field->target);
    // rel table
    $reltable =  static::getRelationTableName($field);
    \Seolan\Core\Logs::notice(__METHOD__, "create/recreate rel table $reltable");
    getDB()->exec("DROP TABLE IF EXISTS $reltable");
    getDB()->exec(static::relationTableDDL($reltable, $field->table, $field->target));
    \Seolan\Core\Logs::notice(__METHOD__, "create/recreate triggers and procedure for $reltable");
    getDB()->exec("DROP PROCEDURE IF EXISTS INSERT_{$reltable}");
    getDB()->exec("DROP TRIGGER IF EXISTS {$reltable}_ONUPDATE");
    getDB()->exec("DROP TRIGGER IF EXISTS {$reltable}_ONINSERT");
    getDB()->exec(static::relationTableProcedureInsertionDDL($reltable, $field->table, $field->target));
    getDB()->exec(static::relationTableTriggersInsertDDL($reltable,$field->table, $field->field));
    getDB()->exec(static::relationTableTriggersUpdateDDL($reltable,$field->table, $field->field));
    if (!static::checkComponents($field)){
      getDB()->exec("DROP PROCEDURE IF EXISTS INSERT_{$reltable}");
      getDB()->exec("DROP TRIGGER IF EXISTS {$reltable}_ONUPDATE");
      getDB()->exec("DROP TRIGGER IF EXISTS {$reltable}_ONINSERT");
      getDB()->exec("DROP TABLE IF EXISTS $reltable");	
      throw new \Exception("Error attempting field normalization {$field->field} {$field->table} {$field->target}");
    }
    static::populateRelationTable($field);
    return true;
  }
  protected static function checkEngine($table){
    $engine = strtoupper(getDB()->fetchOne('SELECT engine FROM information_schema.tables WHERE table_name=? AND table_schema = ?', [$table, $GLOBALS['DATABASE_NAME']]));
    \Seolan\Core\Logs::notice(__METHOD__, "table '$table' engine ok ($engine)");
    if ($engine != 'INNODB'){
      $nb = getDB()->fetchOne("select count(*) from $table");
      if ($nb < 10000){
	\Seolan\Core\Logs::notice(__METHOD__, "set table '$table' engine to InnoDB");
	getDB()->execute("ALTER TABLE $table ENGINE=INNODB");
      } else {
	throw new \Seolan\Core\Exception\Exception(__METHOD__. " table '$table' engine ('$engine') not InnoDB");
      }
    } else {
      \Seolan\Core\Logs::notice(__METHOD__, "table '$table' engine ok ($engine)");
    }
  }
  protected static function relationTableDDL($table, $src, $dst){
    // foreign key koiddst -> index sur la colonne et qui fait pour les sous requêtes ensuite
    $srcType = getDB()->fetchOne(
      'select COLUMN_TYPE from information_schema.columns where TABLE_SCHEMA=? and TABLE_NAME=? and  COLUMN_NAME="KOID"',
      [$GLOBALS['DATABASE_NAME'], $src]);
    $dstType = getDB()->fetchOne(
      'select COLUMN_TYPE from information_schema.columns where TABLE_SCHEMA=? and TABLE_NAME=? and  COLUMN_NAME="KOID"',
      [$GLOBALS['DATABASE_NAME'], $dst]);
    return "
    CREATE TABLE $table (
			 KOIDSRC $srcType NOT NULL
			 ,LANGSRC char(2) NOT NULL
			 ,KOIDDST $dstType NOT NULL
			 ,PRIMARY KEY (KOIDSRC,LANGSRC,KOIDDST)
			 ,FOREIGN KEY (KOIDSRC,LANGSRC) REFERENCES {$src}(KOID,LANG) ON DELETE CASCADE
			 ,FOREIGN KEY (KOIDDST) REFERENCES {$dst}(KOID) ON DELETE CASCADE
			 ) ENGINE INNODB;
    ";
  }
  /**
   * Ajout de la procedure qui associée aux triggers d'insertion pour une table de relation donnée
   * La procedure d'insertion porte la version dans son commentaire
   */
  protected static function relationTableProcedureInsertionDDL($reltable, $src, $targetTable){
    $srcType = getDB()->fetchOne(
      'select COLUMN_TYPE from information_schema.columns where TABLE_SCHEMA=? and TABLE_NAME=? and  COLUMN_NAME="KOID"',
      [$GLOBALS['DATABASE_NAME'], $src]);
    $dstType = getDB()->fetchOne(
      'select COLUMN_TYPE from information_schema.columns where TABLE_SCHEMA=? and TABLE_NAME=? and  COLUMN_NAME="KOID"',
      [$GLOBALS['DATABASE_NAME'], $targetTable]);
    $version = static::$version;
    return "
    CREATE PROCEDURE INSERT_{$reltable} (IN rawdst TEXT, IN srcid $srcType, IN srclang CHAR(2))
    COMMENT '$version'
    BEGIN
    DECLARE p INT;
    DECLARE s INT;
    DECLARE l INT;
    DECLARE dstid $dstType;
    DECLARE sep char(2);
    DECLARE targetOk TINYINT DEFAULT 0;
    SET sep = \"||\" ;
    SET l = LENGTH(rawdst);
    SET p = LOCATE(sep,rawdst,1);
    IF (p = 0 AND l > 0) THEN
    SELECT EXISTS(SELECT 1 FROM {$targetTable} WHERE KOID=rawdst) INTO targetOk;
    IF targetOk THEN
    INSERT IGNORE INTO {$reltable} (KOIDSRC, KOIDDST, LANGSRC) values (srcid, rawdst, srclang);
    END IF;
    ELSEIF (p > 0 AND l > 0) THEN
    lp1:LOOP
    SET s = LOCATE(sep,rawdst,p+2);
    IF (s = 0) THEN
    LEAVE lp1;
    END IF;
    SET dstid = MID(rawdst, p+2, s-1-p-2+1);
    IF (LENGTH(dstid)>0) THEN 
    SELECT EXISTS(SELECT 1 FROM {$targetTable} WHERE KOID=dstid) INTO targetOk;
    IF targetOk THEN
    INSERT INTO {$reltable} (KOIDSRC, KOIDDST, LANGSRC) values (srcid, dstid, srclang);
    END IF;
    END IF;
    IF  (s >= (l-1)) THEN
    LEAVE lp1;
    END IF;
    SET p = s;
    END LOOP lp1;
    END IF;   
    END
    ";
  }
  /**
   * Ajout des trigger pour la table source pour la table de relation
   * Si plusieurs champs liens -> plusieurs triggers 
   */
  protected static function relationTableTriggersInsertDDL($reltable, $srctable, $fname){
    return "
    CREATE TRIGGER {$reltable}_ONINSERT AFTER INSERT ON {$srctable}
    FOR EACH ROW
    BEGIN
    CALL INSERT_{$reltable}(NEW.{$fname}, NEW.KOID, NEW.LANG);
    END
    ";
  }
  protected static function relationTableTriggersUpdateDDL($reltable, $srctable,$fname){
    return "
    CREATE TRIGGER {$reltable}_ONUPDATE AFTER UPDATE ON {$srctable}
    FOR EACH ROW
    BEGIN
    IF (ISNULL(OLD.{$fname}) OR (OLD.{$fname} != NEW.{$fname})) THEN
    DELETE FROM {$reltable} WHERE KOIDSRC=OLD.KOID AND LANGSRC=OLD.LANG;
    CALL INSERT_{$reltable}(NEW.{$fname}, NEW.KOID, NEW.LANG);
    END IF;
    END
    ";
  }
  /**
   * relation table name include table, field name
   */
  public static function getRelationTableName(\Seolan\Core\Field\Field $field){
    return "{$field->table}_{$field->field}";
  }
  /**
   * check procedure and triggers version are ok
   */
  public static function checkComponentsVersion(\Seolan\Core\Field\Field $field){
    $actual = static::getComponentsVersion($field);
    return ($actual >= static::$version);
  }
  public static function getComponentsVersion(\Seolan\Core\Field\Field $field){
    $reltable =  static::getRelationTableName($field);
    $actual='0.0';
    if (System::tableExists($reltable,true)){
      $rs = getDB()->select("SHOW PROCEDURE STATUS WHERE Name='INSERT_{$reltable}'")->fetchAll();
      if (count($rs) == 1){
	$line = $rs[0];
	if (isset($line['Comment']))
	  $actual=$line['Comment'];
      }
    }
    return $actual;
  }
  /**
   * check that all components exists (table, triggers, stored procedure)
   */
  public static function checkComponents(\Seolan\Core\Field\Field $field){
    $reltable =  static::getRelationTableName($field);
    if (!System::tableExists($reltable,true)){
      \Seolan\Core\Logs::critical(__METHOD__, "relation table '$reltable' missing ({$GLOBALS['DATABASE_NAME']})");
      return false;
    }
    $rs = getDB()->select("SHOW PROCEDURE STATUS WHERE Name=? AND Db=?",["INSERT_{$reltable}", $GLOBALS['DATABASE_NAME']])->fetchAll();

    if (count($rs) != 1){
      \Seolan\Core\Logs::critical(__METHOD__, "create/recreate relation table '$reltable' error, no insert procedure ({$GLOBALS['DATABASE_NAME']})");
      return false;
    }
    $tofinds = ["{$reltable}_ONUPDATE", "{$reltable}_ONINSERT"];
    $founds = 0;
    $rs = getDB()->select("SHOW TRIGGERS FROM `{$GLOBALS['DATABASE_NAME']}`")->fetchAll();
    foreach($rs as $ors){
      if (in_array($ors['Trigger'], $tofinds)){
	$founds++;
      }
      if ($founds == count($tofinds))
	break;
    }
    if ($founds != count($tofinds)){
      \Seolan\Core\Logs::critical(__METHOD__, "create/recreate relation table '$reltable' error, no triggers ({$GLOBALS['DATABASE_NAME']})");
      return false;
    }
    return true;
  }
  /**
   * initialize relation table with actual src/dst table contents
   */
  protected static function populateRelationTable($field){
    $reltable =  static::getRelationTableName($field);
    $lienName = $field->field;
    $srcTable = $field->table;
    $dstTable = $field->target;
    $avt = getDB()->fetchOne("select count(*) from $reltable");
    \Seolan\Core\Logs::notice(__METHOD__, "$srcTable, $dstTable, $lienName => $reltable ($avt)");
    $rs = getDB()->select("select KOID, $lienName, LANG from {$srcTable} where ifnull({$lienName}, '')!='' and not exists(select 1 from {$reltable} where KOIDSRC={$srcTable}.KOID and {$srcTable}.LANG=LANGSRC)");
    $tot = $rs->rowCount();
    \Seolan\Core\Logs::notice(__METHOD__, "$tot");
    $n = 0;
    while($ors = $rs->fetch()){

        $dsts = preg_split("/\\|\\|/", $ors[$lienName], -1, PREG_SPLIT_NO_EMPTY);
        $dsts = array_unique($dsts); // doublons impossibles en table relation
        $values = [];
        $tuples = [];
        foreach($dsts as $dst){
            // données corrompues : oid dest doit exister sinon erreur contrainte clé étrangère
            $exists = getDB()->fetchOne("select 1 from $dstTable where koid=?", [$dst]);
            if ($exists){
                $tuples[] = "(?,?,?)";
                $values[] = $ors['KOID'];
                $values[] = $ors['LANG'];
                $values[] = $dst;
                $n++;
            } else {
                \Seolan\Core\Logs::notice(__METHOD__, "dst oid : $dst does not exists (src : {$ors['KOID']}");
            }
        }
        if (count($values)>0){
            
            // insertion de toutes les lignes en 1 requete
            
            $q = "insert into $reltable (KOIDSRC, LANGSRC, KOIDDST) values ".implode(',',$tuples)."";
            
            getDb()->execute($q, $values);
            
        }
        
        if ($n%1000 == 0){
            \Seolan\Core\Logs::notice(__METHOD__, " ".sprintf("%7d", $n)." / $tot");
        }
        
    }
    \Seolan\Core\Logs::notice(__METHOD__, " ".sprintf("%7d", $n)." / $tot");
    $apr = getDB()->fetchOne("select count(*) from $reltable");
    \Seolan\Core\Logs::notice(__METHOD__, "$srcTable, $dstTable, $lienName => $reltable ($avt -> $apr)");
  }
  /**
   * Drop relation table, procedures and triggers associated with a field
   */
  public static function dropComponents($field){
    $reltable =  static::getRelationTablename($field);
    getDB()->execute("DROP TRIGGER IF EXISTS {$reltable}_ONUPDATE");
    getDB()->execute("DROP TRIGGER IF EXISTS {$reltable}_ONINSERT");
    getDB()->execute("DROP PROCEDURE IF EXISTS INSERT_{$reltable}");
    getDB()->execute("DROP TABLE IF EXISTS $reltable");
  }
}
