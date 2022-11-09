<?php
namespace Seolan\Core;

class System {
  static $tables = NULL;
  static $cfields = [];
  static $cindexes = [];
  
  /// Charge un helper
  public static function loadHelper($name){
    require_once('src/Helper/'.$name);
  }

  /// Charge une librairie externe
  public static function loadVendor($name){
    require_once('Vendor/'.$name);
  }

  /// Rend vrai si la table $table existe dans la base de données
  public static function tableExists($table, $refresh=false) {
    if(empty(self::$tables) || $refresh)
      self::$tables = getMetaTables();
    return !empty(self::$tables[$table]);
  }

  /// Rend vrai si la table $table existe dans la base de données
  public static function isView($table, $refresh=false) {
    if(empty(self::$tables) || $refresh)
      self::$tables = getMetaTables();
    return !empty(self::$tables[$table]) && (trim(self::$tables[$table]['type']) == 'VIEW');
  }

  /// Rend vrai si le champ $field de la table $table existe dans la base de donnée
  // faux si la table n'existe pas
  public static function fieldExists($table, $field) {
    if(!self::tableExists($table))
      return false;
    if(empty(self::$cfields[$table]))
      self::$cfields[$table] = getDB()->select('SHOW COLUMNS FROM '.$table.'/*1*/')->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    return isset(self::$cfields[$table][$field]);
  }

  public static function getFields($table) {
    if(!self::tableExists($table))
      return false;
    if(empty(self::$cfields[$table]))
      self::$cfields[$table] = getDB()->select('SHOW COLUMNS FROM '.$table.'/*2*/')->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    return self::$cfields[$table];
  }

  public static function getIndexes($table) {
    if(!self::tableExists($table))
      return false;
    if(!isset(self::$cindexes[$table])) {
      self::$cindexes[$table] = [];
      $indexes = getDB()->fetchAll('show index from ' . $table);
      foreach ($indexes as $index) {
        self::$cindexes[$table][$index['Key_name']][$index['Seq_in_index'] - 1] = $index['Column_name'];
      }
    }
    return self::$cindexes[$table];
  }

  public static function clearCache() {
    self::$tables = NULL;
    self::$cfields = array();
  }

  public static function getmicrotime() {
    list($usec, $sec) = explode(' ',microtime()); 
    return ((float)$usec + (float)$sec);
  } 

  public static function getusertime() {
    $t = posix_times();
    return $t['utime'];
  } 
  
  // separation d'une ligne en token separes par le $delim avec un caractere delimiteur
  //
  public static function csv2array ($str, $delim=';', $qual="\"") {
    if(is_array($str)) {
      for($i=0;$i<count($str);$i++) {
	$ret[]=csv2array($str[$i],$delim,$qual);
      }
      return $ret;
    }
    
    // Taille de la ligne
    $width=strlen($str);
    
    // Enclosed
    $enclosed=false;
    
    // Item
    $item="";
    
    for ( $i=0; $i<$width; $i++) {
      if ( $str[$i] == $delim && !$enclosed ) {
	$retval[] = $item;
	$item="";
      } else if ( $str[$i] == $qual && ( $i<$width && $str[$i+1] == $qual ) ) {
	$item .= $qual;
	$i++;
      } else if ( $str[$i] == $qual ) {
	$enclosed = !$enclosed;
      } else {
	$item .= $str[$i];
      }
    }
    
    // We give back the matrix
    $retval[]=$item;
    return $retval;
  }
  public static function uptime() {
    if(!defined('TZR_UPTIME_PATH')) return array('procs.r'=>"0");
    $r = explode(" ",file_get_contents(TZR_UPTIME_PATH),2);
    return array('procs.r'=>$r[0]);
  }

  public static function def($const, $val) {
    if(!defined($const)) define($const, $val);
  }

  // creation d'une archive a partir d'une liste de fichiers
  // la liste de fichier est au format "téléchargé
  //
  public static function untarfiles($filename) {
    $dir=TZR_TMP_DIR.uniqid('tzr');
    @mkdir($dir);
    @copy($filename, "$dir/ar.tgz");
    @system("cd $dir ; tar xzf ar.tgz");
    @unlink("$dir/ar.tgz");
    $catalog=@file("$dir/tzr-catalog.txt");
    if(empty($catalog)) $catalog=array();
    $files=array();
    foreach($catalog as $i => $l) {
      list($v1,$v2,$v3)=explode(";",rtrim($l));
      $file['tmp_name']="$dir/$v1";
      $file['type']=$v2;
      $file['name']=$v3;
      $files[$v1]=$file;
    }
    $files['tzr-dir']=$dir;
    return $files;
  }
  public static function tarfiles(&$ar, $fmt="tgz") {
    // construction de la liste des fichiers
    $dir=TZR_TMP_DIR.uniqid('tzr');
    mkdir($dir, 0750, true);
    $catalog="";
    foreach($ar as $l => $b) {
      $cat.="$l;".$b['type'].";".$b['name']."\n";
      //move_uploaded_file($b['tmp_name'], $dir."/".$l);
      if(!copy($b['tmp_name'], $dir."/".$l)){
	\Seolan\Core\Logs::critical('\Seolan\Core\System::tarfiles',"moving file fail : ".$dir."/".$l);		
      }
    }
    file_put_contents("$dir/tzr-catalog.txt",$cat);
    $files=scandir($dir);
    $un  = TZR_TMP_DIR.uniqid('tzr');
    $un .= '.'.$fmt;
    unset($files[0]);
    unset($files[1]);
    $list=implode(' ',$files);
    if($fmt=="tgz") {
      system("cd $dir ; tar -czf $un $list");
      \Seolan\Library\Dir::unlink($dir,false,true);
    }
    if($fmt=="zip") {
      system("cd $dir ; zip $un $list");
      \Seolan\Library\Dir::unlink($dir,false,true);
    }
    return $un;
  }

  /****f* System/getRemote
   * NAME
   *   \Seolan\Core\System::getRemote - recupération des données depuis un repertoire temporaire
   * DESCRIPTION
   *   Recupération des données depuis le répertoire passé en
   *   paramètre, qui peut être un répertoire distant. Si le transfert
   *   s'est bien passé, les fichiers sur le répertoire d'origine sont
   *   supprimés au fur etaà mesure des transferts.
   *   Le nom du répertoire peut être une url.
   * INPUTS
   * $dirpath - répertoire
   ****/
  static function getRemote($dirpath) {
    $tmpdir = TZR_TMP_DIR."download".uniqid();
    @mkdir($tmpdir);
    $dir = @opendir($dirpath);
    if($dir) {
      while (($file = readdir($dir)) !== false) {
	$filename = "$dirpath/$file";
	$newfilename = $tmpdir."/".$file;
	$fo = file_get_contents($filename);
	file_put_contents($newfilename,$fo);
	if(file_exists($newfilename)) {
	  unlink($filename);
	}
      }
      closedir($dir);
    }
    return $tmpdir;
  }

  static function xml2html($xml){
    $xml=str_replace('<?xml version="1.0" encoding="utf-8" ?>','',$xml);
    $xml=str_replace('<tzrdata v="2">','',$xml);
    $xml=str_replace('</tzrdata>','',$xml);
    return $xml;
  }
  static function array2xml($ar, &$xml) {
    $init=false;
    if(empty($xml)) {
      $init=true;
      $xml="<?xml version=\"1.0\" encoding=\"utf-8\" ?><tzrdata v=\"2\">";
    }
    if (is_array($ar) || is_object($ar)) {
      $xml.="<table>";
      foreach($ar as $idx => $val) {
	$xml.="<tr><th>".htmlspecialchars($idx,ENT_COMPAT,TZR_INTERNAL_CHARSET)."</th><td>";
	if(is_array($val) || is_object($val)) \Seolan\Core\System::array2xml($val,$xml);
	else $xml.=htmlspecialchars($val,ENT_COMPAT,TZR_INTERNAL_CHARSET);
	$xml.="</td></tr>";
      }
      $xml.="</table>";
    }
    if($init) $xml.="</tzrdata>";
  }
  static function xml2arrayitem($idx,$val) {
    $v1=$val->children();
    if(!empty($v1[0])) {
      $val = \Seolan\Core\System::xml2array($val);
    } else { $val=(string)$val;}
    return array((string)$idx, $val);
  }
  static function xml2array($xml){
    if(!is_object($xml)) {
      $xml=simplexml_load_string($xml);
      if($xml["v"]>2) \Seolan\Core\Logs::critical("\Seolan\Core\System::xml2array","bad xml array release ".var_export($xml,true));
    }
    $ar=array();
    if(!empty($xml->table)) {
      $e=$xml->table->children();
      foreach($e as $idx=>$val) {
	list($i, $v)=\Seolan\Core\System::xml2arrayitem($val->th, $val->td);
	$ar[$i]=$v;
      }
    }
    return $ar;
  }
   // fonction de copie capable de prendre en compte de tres gros fichiers : permet
   // de contourner les limitations de php qui charge tout le fichier
   // en memoire lors d'une copie de fichier.
   //
  static function copy($src, $dst) {
     if(!file_exists($src)) return;
     if(filesize($src)<10*1024*1024) {
       return copy($src,$dst);
     }
     $fh = fopen($src, "r");
     $fh22 = fopen($dst, 'w+');
     if(!$fh || !$fh22) return;
     
     $blocksize=1024*1024;// 10Ko
     while (!feof($fh)) {
       $contents = fread($fh, $blocksize);
       fwrite($fh22, $contents, $blocksize);
     }
     fclose($fh);
     fclose($fh22);
   }
   

  // formatage d'une taille pour être lisible
  function formatBytes($size, $precision = 2)
  {
    $base = log($size, 1024);
    $suffixes = array('', 'K', 'M', 'G', 'T');   

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
  }
}

