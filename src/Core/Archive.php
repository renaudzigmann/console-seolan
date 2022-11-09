<?php
namespace Seolan\Core;

use \phpseclib3\Net\SFTP;

/// archivage des donnees de log et autres dans des fichiers textes
class Archive {
  static $_archiver = NULL;
  static $_writer = NULL;
  static function &_init(){
    if(!file_exists(TZR_ARCHIVES_DIR)) {
      \Seolan\Core\Logs::notice('\Seolan\Core\Archive::_init',TZR_ARCHIVES_DIR.' created');
      \Seolan\Library\Dir::mkdir(TZR_ARCHIVES_DIR,false);
    }
    if(empty(self::$_archiver)) {
      self::$_writer = new \Zend\Log\Writer\Stream(TZR_ARCHIVES_DIR.'archives.log');
      self::$_writer->setFormatter(new \Zend\Log\Formatter\Simple('%message%;'));
      self::$_archiver = new \Zend\Log\Logger();
      self::$_archiver->addWriter(self::$_writer);
    }
    return self::$_archiver;
  }
  static function &_close(){
    self::$_archiver->close();
  }

  static function appendQuery($rq) {
    if(empty(self::$_archiver)) self::_init();
    self::$_archiver->notice($query);
  }

  static function appendData($table, $data) {
    if(empty(self::$_archiver)) self::_init();
    $query=getDB()->getInsertQuery($table, $data);
    self::$_archiver->notice($query);
  }

  static function appendOid($oid, $field='KOID', $delete=false, $logs=false, $deletelogs=false) {
    if(empty(self::$_archiver)) self::_init();
    $table=\Seolan\Core\Kernel::getTable($oid);
    if($field != 'KOID') list($table, $field)=explode('.',$field);
    $rs=getDB()->select('SELECT * FROM '.$table.' where '.$field.'=?',[$oid]);
    while($ors=$rs->fetch()) {
      $query=getDB()->getInsertQuery($table, $ors);
      self::$_archiver->notice($query);
    }
    if($delete) getDB()->execute('DELETE FROM '.$table.' WHERE  '.$field.'= ?', [$oid]);
    $rs=getDB()->select('SELECT * FROM LOGS where object=?',[$oid]);
    while($ors=$rs->fetch()) {
      $query=getDB()->getInsertQuery('LOGS', $ors);
      self::$_archiver->notice($query);
    }
    if($delete) getDB()->execute('DELETE FROM LOGS WHERE object=?',[$oid]);
  }
  static function rotate() {
    \Seolan\Library\Dir::rotate(TZR_ARCHIVES_DIR,1);
  }
  
  /// supprime ou envoie les anciennes archives dans le coffrefort
  /*
   Le fonctionnement est le suivant :
   Tu te connectes en SFTP à moncoffre01
   Tu arrives dans un dossier chrooté /home/import/sftp/, à partir de là, tu crées ton arborescence /tag/timestamp/fichier(s)
   Une fois le ou les fichiers déposés, c'est seulement à la déconnexion du SFTP qu'un script s'active et crypte les fichiers dans /home/renaud/import/tag/timestamp/fichier.gpg
   
   S'il y a une erreur en cours de route un mail est envoyé au support et les fichiers conservés, sinon le dossier et les fichiers d'origine sont supprimés.
  */
  static function clean() {
    $locker=TZR_ARCHIVES_LOCKER;
    $moncoffre=getDB()->fetchRow("SELECT * FROM _ACCOUNTS WHERE atype='locker'");
    if($moncoffre) {
      $locker=$moncoffre;
    }
    if(empty($locker)){ return; }
    $url=$locker["url"];
    $login=$locker["login"];
    $passwd=$locker["passwd"];
    
    $files=\Seolan\Library\Dir::scan(TZR_ARCHIVES_DIR, false);
    $tag=preg_replace("@[^a-z0-9\.\-]@i","",strstr($GLOBALS["HOME_ROOT_URL"],":"));
    $ts=date("Ymd");
    $filestosend=[];
    foreach($files as $file) {
      // si plus de 60 jours
      $src=TZR_ARCHIVES_DIR.$file;
      if( filemtime($src) < time()-60*60*24*TZR_ARCHIVES_RETENTION_DAYS){
	$filestosend[]=$file;
      }
    }
    if(!empty($filestosend)) {
      $sftp = new SFTP($url);
      if(!$sftp->login($login, $passwd)) {
	\Seolan\Core\Logs::notice(__METHOD__, "cannot log to locker");
      }
      foreach($filestosend as $file) {
	$dest="/sftp/$tag/$ts/$file";
	$src=TZR_ARCHIVES_DIR.$file;
	if(!$sftp->is_dir("/sftp/$tag")) $sftp->mkdir("/sftp/$tag");
	if(!$sftp->is_dir("/sftp/$tag/$ts")) $sftp->mkdir("/sftp/$tag/$ts");
	if($sftp->is_dir("/sftp/$tag/$ts") && $sftp->put($dest, $src, SFTP::SOURCE_LOCAL_FILE)) {
	  \Seolan\Core\Logs::debug(__METHOD__.": unlinking $src, copied to $dest");
	  unlink($src);
	} else {
	  \Seolan\Core\Logs::notice(__METHOD__, "cannot send $src to $dest");
	}
      }
      $sftp->disconnect();
    }
  }
}
