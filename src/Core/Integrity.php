<?php
namespace Seolan\Core;
/// Classe de verification de l'integrite des donnees
class Integrity {
  function __construct() {
  }

  /**
   * controle de l'accès aux répertoires définis 
   * dans TZR_SECURE
   */
  public static function checkDataDirAccess(&$message){
    // check de l'accès web à un rep de fichiers d'une table 
    // -> essaie d'accès au fichier chk.csx (qui existe pas)
    // -> doit rendre forbiden et pas not found
    $checkDataDir = function($table, $field){
      $dataurl = $GLOBALS['HOME_ROOT_URL'].$GLOBALS['DATA_URL'];
      $filetocheck = '/chk.csx';
      $urltocheck = $dataurl.$table.'/'.$field.$filetocheck;
      $h = get_headers($urltocheck);
      if ($h!==false && !in_array('HTTP/1.1 403 Forbidden', $h)){
	if ($GLOBALS['DATA_URL'] == '*')
	  return false;
	else
	  return true;
      } else {
	return false;
      }
    };

    if (!isset($GLOBALS['TZR_SECURE'])){
      return true;
    }

    if (isset($GLOBALS['TZR_SECURE']['_all'])){
      $tn = array_keys(\Seolan\Core\DataSource\DataSource::getBaseList(true, true));
      $tables = array_fill_keys($tn, '_all');
    } else {
      $tables = $GLOBALS['TZR_SECURE'];
    }
    // suppression des erreurs avant recalcul
    getDB()->execute('delete from _VARS where name like ?', ['securityerror::%']);
    foreach($tables as $table=>$fields){
      if ($table == '_public') // éventuellement en lister le contenu ?
        continue;
      $ds= \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$table);
      if ($fields == '_all' || isset($fields['_all'])){
	$fields = $ds->getFieldsList();
	$fields = array_fill_keys($fields, '1');
      } 
      foreach($fields as $field=>$on){
	if (!$on || !isSecureField($table, $field)) {
	  continue;
	}
	if ($ds->fieldExists($field)){
	  $f = $ds->getField($field);
	  if (is_a($f, '\Seolan\Field\File\File')){
	    if ($checkDataDir($table, $field)){
	      $message .= "\nfield $table/$field TZR_SECURE defined error : access allowed";
	      \Seolan\Core\DbIni::set("securityerror::dataaccess::$table::$field", "TZR_SECURE $table $field, error: access allowed");
	    }
	  }
	} else {
	  \Seolan\Core\Logs::notice(__METHOD__, "field $table/$field TZR_SECURE defined not exists in catalog");
	}
      }
    }
    return true;
  }
  /// Verification des indexes
  static function chkDatabases(&$message) {
    $rs=getDB()->fetchAll('SELECT BTAB FROM BASEBASE WHERE BOID NOT IN(SELECT MOID FROM AMSG)');
    foreach($rs as $ors) {
      \Seolan\Core\Logs::critical("Table {$ors['BTAB']} has no AMSG, it should be deleted from table BASEBASE and from database");
      getDB()->execute("DELETE FROM BASEBASE WHERE BTAB='{$ors['BTAB']}'");
      getDB()->execute("DROP TABLE {$ors['BTAB']}");
    }
    unset($rs);
    $rs=getDB()->fetchAll('SELECT * FROM BASEBASE');
    foreach($rs as $ors) {
      if(!\Seolan\Core\System::isView($ors['BTAB'])) {
        // Verifie l'existence de la table dans la base
        if(!\Seolan\Core\System::tableExists($ors['BTAB'])) {
          $message.="Table {$ors['BTAB']} exists on catalog but not in database\n";
          continue;
        }
        
        // Verifie les cles primaires
        $r1=getMetaPrimaryKeys($ors['BTAB']);
        if(empty($r1)) {
          $message.="No primary index on {$ors['BTAB']}\n";
          getDB()->execute('ALTER TABLE '.$ors['BTAB'].' ADD PRIMARY KEY(KOID,LANG);');
        }elseif($r1[0]!="KOID" || $r1[1]!="LANG") {
          $message.="Primary index on {$ors['BTAB']} is invalid ".implode(":",$ors['BTAB'])."\n";
        }
        // Nettoie la table si elle n'est pas traduisible
        if($ors['TRANSLATABLE']==0){
          $toclean = getDB()->select('select count(*) from '.$ors['BTAB'].' where LANG!=?',[TZR_DEFAULT_LANG])->fetch(\PDO::FETCH_COLUMN);
          if ($toclean)
            getDB()->execute('delete from '.$ors['BTAB'].' where LANG!="'.TZR_DEFAULT_LANG.'"');
        }
	// Vérifie la table archivage
	$archiveTable = 'A_'.$ors['BTAB'];
	if (\Seolan\Core\System::tableExists($archiveTable) && $ors['LOG'] == 1){
	  $alog = getMetaKeys($archiveTable,'ALOG');
	  if (!$alog){
	    getDB()->execute('CREATE INDEX ALOG on '.$archiveTable.'(KOID,UPD,LANG)');
	  }
	}
        // Execute la fonction de nettoyage de la source 
	$xa=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$ors['BTAB']);
        if (!is_object($xa)) {
          $message.="Cannot build \Seolan\Core\DataSource\DataSource for {$ors['BTAB']}\n";
        } else {
          $r1=$xa->chk(array("tplentry"=>TZR_RETURN_DATA,"repair"=>"false"));
          $message.=$r1['message'];
        }
      }
    }
    unset($rs);

    $files=scandir($GLOBALS['DATA_DIR']);
    foreach($files as $dir) {
      if(substr($dir,0,1)==".") continue;
      if(preg_match('/^A_([a-z0-9_]+)$/i',$dir, $eregs)) {
	$t1=$eregs[1];
	if(!\Seolan\Core\System::tableExists($t1)) {
	  $message.=$dir.' tentative de suppression (1) '."\n";
	  \Seolan\Library\Dir::unlink($GLOBALS['DATA_DIR'].$dir,false,true);
	}
      } elseif(\Seolan\Core\System::tableExists($dir)) {
	$fields=scandir($GLOBALS['DATA_DIR'].$dir);
	foreach($fields as $field) {
	  if(substr($field,0,1)==".") continue;
	  if(!fieldExists($dir,$field)) {
	    $message.=$dir.':'.$field.' tentative de suppression (2) '."\n";
	    \Seolan\Library\Dir::unlink($GLOBALS['DATA_DIR'].$dir.'/'.$field,false,true);
	  }
	}
      } else {
	$message.=$dir.' tentative de suppression (3) '."\n";
	\Seolan\Library\Dir::unlink($GLOBALS['DATA_DIR'].$dir,false,true);
      }
    }
  }
  /// Verification des parametres de demarrage
  static function chkParameters(&$message) {
    // this is supposed to move quite quickly
    if(!\Seolan\Core\Ini::get('nocheck_debug') && (defined('TZR_DEBUG') || TZR_LOG_LEVEL>PEAR_LOG_INFO)) {
      $message.="Debug parameters is set on ".$GLOBALS['HOME_ROOT_URL']."\n";
    }
    $version=phpversion();
    if(version_compare($version, TZR_PHP_RELEASE)<0) {
      $message.="PHP Release is $version instead of ".TZR_PHP_RELEASE."\n";
    }
  }

  /// Verification des paramètres importants du runtime
  static function chkCriticalRuntimeParameters(){
    $message = '';
    if (empty($GLOBALS['HOME_ROOT_URL'])){
      $message .= "Global variable 'HOME_ROOT_URL' is not set, server name : ".$_SERVER['SERVER_NAME'].', home '.$GLOBALS['HOME'];
      bugWarning($message, false, true);
    }
  }

  /// verification de la table des options et des abonnements
  static function chkOpts(&$message) {
    if(\Seolan\Core\System::tableExists('OPTS')) {
      $rs = getDB()->select('SELECT KOID,user FROM OPTS');
      while($rs && $ors=$rs->fetch()) {
	if(\Seolan\Core\Kernel::isAKoid($ors['user']) && !\Seolan\Core\Kernel::objectExists($ors['user'])) {
	  \Seolan\Core\Kernel::delObject($ors['KOID']);
	  $message.='Deleting object '.$ors['KOID']."\n";
	}
      }
    }
  }

  /// Verification des modules
  static function chkModules(&$message) {
    // Suppression du champ PUBLISH dans TEMPLATES
    if(fieldExists('TEMPLATES','PUBLISH')){
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    }

    $list=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA, 'noauth'=>true, 'withmodules' => true));
    foreach($list['lines_mod'] as $i=>&$o) {
      $o->chk($message);
    }

    \Seolan\Core\Integrity::chkLogInfo();
  }

  /// mise a jour de la propriété TOLOG dans la table BASBEASE
  static function chkLogInfo() {
    $list=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA, 'noauth'=>true, 'withmodules' => true));
    $tables=array();
    foreach($list['lines_mod'] as $i=>&$o) {
      $t1=$o->tablesToTrack();
      if(!empty($t1)) $tables =array_merge($tables,$t1);
    }
    $bl = \Seolan\Core\DataSource\DataSource::getBaseList();
    foreach($bl as $t=>&$foo) {
      if(in_array($t, $tables))
	$track[] = $t;
      else
	$untrack[] = $t;
    }
    if (count($track)>0){
      $track = array_unique($track);
      getDB()->execute('UPDATE BASEBASE SET LOG=? WHERE BTAB in ('.implode(',', array_fill(0, count($track), '?')).')',
		       array_merge(['1'], $track));
    }
    if (count($untrack)>0){
      $untrack=array_unique($untrack);
      getDB()->execute('UPDATE BASEBASE SET LOG=? WHERE BTAB in ('.implode(',', array_fill(0, count($untrack), '?')).')',
		       array_merge(['0'], $untrack));
    }
  }

  /// Verification des dossiers obligatoires
  static function chkDirs(&$message) {
    if(!file_exists(TZR_VAR2_DIR)){
      mkdir(TZR_VAR2_DIR);
      $message.='Directory '.TZR_VAR2_DIR." created\n";
    }
    if(!file_exists(TZR_TMP_DIR)){
      mkdir(TZR_TMP_DIR);
      $message.='Directory '.TZR_TMP_DIR." created\n";
    }
    if(!file_exists(TZR_STATUSFILES_DIR)){
      mkdir(TZR_STATUSFILES_DIR);
      $message.='Directory '.TZR_STATUSFILES_DIR." created\n";
    }
    if(!file_exists(TZR_LOG_DIR)){
      mkdir(TZR_LOG_DIR);
      $message.='Directory '.TZR_LOG_DIR." created\n";
    }
  }

  /// Teste la présence de certaines fonctions dans les cgi (cli et php) (doit etre exécuté par le cli)
  static function chkCGIs(&$message, &$error){
    // Test de l'environnement en cours d'execution (cli)
    if(!function_exists('ftp_connect')){
      $message.="No ftp functions on cli. Update your cli\n";
      $error=true;
    }

    // Test de la presence du daemon permettant de convertir les documents doc, xls... en txt
    if(!\Seolan\Core\Ini::get('nocheck_oo')){
      $ok=false;
      $a=array();
      exec('ps aux | grep -i "office-converter"',$a);
      foreach($a as $l){
	if(strpos($l,'/usr/bin/office-converter')){
	  $ok=true;
	  break;
	}
      }
      if(!$ok){
	$error=true;
	$message.="No OpenOffice daemon to convert document. Please install it.\n";
      }
    }
  }
  
  /// Verification des tables de logs
  static function chkLogs(&$message, &$error){
    // Nettoyage des logs mail
    getDB()->execute('DELETE FROM _MLOGS where datee<DATE_ADD(NOW(),INTERVAL -31 DAY) and '.
		'mtype not in ("mailing","fax mailing","sms mailing")');
    getDB()->execute('DELETE FROM _MLOGSD where mlogh not in (select KOID from _MLOGS)');
    // nettoyage des logs
    \Seolan\Core\Logs::clean($message, $error);
    // rotation des fichiers de log
    \Seolan\Core\Logs::rotate();
    \Seolan\Core\Archive::clean();
  }

  /// Envoi du rapport de check
  static function sendReport($report,$error=false) {
    if(!empty($report)) {
      $report = TZR_SERVER_NAME." check report \n\n".$report."\n\n";
      if(!$error) $sub=TZR_SERVER_NAME.' check report';
      else $sub='!!ERROR!! '.TZR_SERVER_NAME.' check report !!ERROR!!';
      if (substr($report,6) != '<html>'){
	$report = '<pre>'.$report.'</pre>';
      }
      $GLOBALS['XUSER']->sendMail2User($sub, $report, TZR_DEBUG_ADDRESS);
    }
  }
}
?>
