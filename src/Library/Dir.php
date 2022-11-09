<?php
namespace Seolan\Library;
/** 
 * classe de manipulation des répertoires recursivement
 * - unlink: suppression recursive de repertoire
 * - mkdir: creation recursive de repertoires
 * - scan: liste recursive de repertoire
 * - copy: copy d'une arborescence vers une autre
 */

class Dir {
  static $cache = array();
  // suppression recursive d'un repertoire
  //
  static function unlink($location,$subonly=false, $delfiles=true) {
    if(!is_dir($location)) return;
    if (substr($location,-1) != "/") 
      $location = $location."/";
    $all=@opendir($location); 
    while ($file=readdir($all)) { 
      if (is_dir($location.$file) && ($file != "..") && ($file != ".")) { 
	\Seolan\Library\Dir::unlink($location.$file); 
	@rmdir($location.$file); 
	unset($file); 
      } elseif (!is_dir($location.$file) && $delfiles) { 
	@unlink($location.$file); 
	\Seolan\Core\Logs::debug('[\Seolan\Library\Dir::del]unlink('.$location.$file.')');
	unset($file); 
      } 
    }
    closedir($all);
    if(!$subonly) @rmdir($location);
  }

  /// Creation recursive de repertoire. Le second parametre specifie si le dernier est un fichier ou un repertoire
  public static function mkdir($dirname,$lastisfile=true) {
    \Seolan\Core\Audit::plusplus('xdir-mkdir-nocache');
    $dirs=explode('/',$dirname);
    $nb=count($dirs);
    if($lastisfile) unset($dirs[$nb-1]);
    $nb=count($dirs);
    // on commence chercher le premier repertoire existant histoire de ne pas tout tester
    $found=false;
    while(!$found && ($nb>0)) {
      $path=implode("/",array_slice($dirs,0,$nb));
      if(file_exists($path)) $found=true;
      else $nb--;
    }
    // ensuite on créée tous ceux qui sont en dessous
    $i=$nb;
    $dir=implode("/",array_slice($dirs,0,$nb));
    $nb=count($dirs);
    while($i<$nb) {
      $dir.='/'.$dirs[$i];
      \Seolan\Core\Logs::debug("making $dir");
      if(!file_exists($dir)) mkdir($dir);
      $i++;
    }
  }

  static function scan($dir,$fullpath=true,$files=true,$dirs=false,$omits=NULL,$recur=true) {
     $file_list = array();
     $stack[] = $dir;
     $omits[]='^(\.)$';
     $omits[]='^(\.\.)$';
     $somits=join('|',$omits);
     while ($stack) {
       $current_dir = array_pop($stack);
       $lastchar=substr($current_dir, -1, 1);
       if($lastchar!='/') $current_dir_full=$current_dir.'/';else $current_dir_full=$current_dir;
       if ($dh = @opendir($current_dir)) {
	 while (($file = readdir($dh)) !== false) {
	   if (!preg_match('@'.$somits.'@', $file)) {
	     $current_file = $current_dir_full.$file;
	     if (is_file($current_file) && $files) {
	       if($fullpath)
		 $file_list[] = $current_dir_full.$file;
	       else
		 $file_list[] = $file;
	     }
	     if (is_dir($current_file) && $dirs) {
	       if($fullpath)
		 $file_list[] = $current_dir_full.$file;
	       else
		 $file_list[] = $file;
	     }
	     if(is_dir($current_file) && $recur) {
	       $stack[] = $current_file;
	     }
	   } 
	 }
	 closedir($dh);
       }
     }
     return $file_list;
  }

  /// copie d'une arboresence vers une autre
  static function copy($dirsource, $dirdest, $fulldest=false) {
    // recursive function to copy
    // all subdirectories and contents:
    if(is_dir($dirsource)) $dir_handle=opendir($dirsource);
    @mkdir($dirdest);
    if(!$fulldest) {
      @mkdir($dirdest."/".$dirsource);
      $dird2=$dirdest."/".$dirsource;
    } else $dird2=$dirdest;
    while($file=readdir($dir_handle)) {
      if($file!="." && $file!="..")  {
	if(!is_dir($dirsource."/".$file)) {
	  copy ($dirsource."/".$file, $dird2."/".$file);
	  \Seolan\Core\Logs::debug("[\Seolan\Library\Dir::copy] copying $dirsource/$file to $dird2/$file");
	} else \Seolan\Library\Dir::copy($dirsource."/".$file, $dird2."/".$file, true);
      }
    }
    closedir($dir_handle);
    return true;
  }
  /// Retoure la place occupé par un repertoire en ko
  static function du($dir) {
     if(file_exists($dir)) {
	$du = popen("/usr/bin/du -sk $dir", "r");
	$res = fgets($du, 256);
	pclose($du);
	$r=preg_match('@^([0-9]+)@',$res, $regs);
	if($r) return $regs[1];
     }
     return 0;
   } 

  /// suppression de tous les fichiers et dossiers plus ancien que $timeout secondes
  static function clean($dir, $timeout) {
    if(!file_exists($dir)) mkdir($dir, 0755);
    $tree=\Seolan\Library\Dir::scan($dir, true, true, true);
    $starttime=time();
    $ok=true;
    // on continue tant qu'il y a des entrees a traiter
    while($ok) {
      $ok=false;
      foreach($tree as $i=>$file) {
	if(is_dir($file)) {
	  // suppression des dossiers quand ils sont vides
	  $ok1=@rmdir($file);
	  if($ok1) unset($tree[$i]);
	  $ok=$ok||$ok1;
	} else {
	  $mtime=filemtime($file);
	  if(($starttime-$mtime)>$timeout) {
	    $ok=unlink($file) || $ok;
	    unset($tree[$i]);
	  }
	}
      }
    }
  }

  /// rotation de fichiers
  static function rotate($targetdir, $nbdays=1, $deleteafterdays=0) {
    \Seolan\Core\Logs::debug(__METHOD__.' : start '.$targetdir);
    if(!is_dir($targetdir)) return;
    $omits=array();
    $files = \Seolan\Library\Dir::scan($targetdir,true,true,false,$omits);
    foreach($files as $file){
      \Seolan\Core\Logs::debug(__METHOD__.' : scanning '.$file);
      if(($deleteafterdays>0) && preg_match('/(\.log-[0-9]{8}\.gz)$/',$file)) {
	\Seolan\Core\Logs::debug(__METHOD__.' : examining '.$file.' : '.date("Ymd",filemtime($file)));
	if(filemtime($file) < time()-60*60*24*$deleteafterdays ){
	  \Seolan\Core\Logs::debug(__METHOD__.' : delete '.$file.' : '.date("Ymd",filemtime($file)));
	  unlink($file);
	}
      }	elseif(preg_match('/(\.log-[0-9]{8})$/',$file)){
	//a archivé si plus vieux que $nbdys jours
	if( filemtime($file) < time()-60*60*24*$nbdays ){
	  \Seolan\Core\Logs::debug(__METHOD__.' : archive '.$file.' : '.date("Ymd",filemtime($file)));
	  @exec("gzip -f ".escapeshellarg($file));
	}	
      }elseif(preg_match('/(\.log)$/',$file)){
	//a renommer si le fichier du jour n'existe pas deja
	if(!file_exists($file.'-'.date('Ymd'))){
	  \Seolan\Core\Logs::debug(__METHOD__.' : rename file '.$file);
	  $newFile = $file.'-'.date('Ymd');
	  @rename($file,$newFile);
	  if ($nbdays==0) @exec("gzip -f ".escapeshellarg($newFile));
	} 
      }
    }
    \Seolan\Core\Logs::debug(__METHOD__.' : end '.$targetdir);
  }

  static function tmpFilename($base="tmpfile") {
    return TZR_TMP_DIR.uniqid($base);
  }
}
?>
