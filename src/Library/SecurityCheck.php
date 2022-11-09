<?php
namespace Seolan\Library;


/// ensemble de méthodes permettant de valider des paramètres d'un script
// si le paramètre n'est pas validé, sortie avec un signalement de sécurité
class SecurityCheck {
  private const VALIDSTRINGS = ['GRP:*'];
  static function assertIsNumeric($p, $message='') {
    if(!is_numeric($p)) {
      self::alert(__METHOD__.':'.$message);
    }
  }
  static function assertIsSimpleString($p, $message='', $withpoint=false) {
    if($withpoint) $pattern='[0-9.a-z_\:-]{0,80}';
    else $pattern="[0-9a-z_\:-]{0,80}";
    if(!preg_match('/^('.$pattern.')$/i',$p)) {
      self::alert(__METHOD__.":{$message} '{$p}'");
    }
  }
  static function assertIsExtendedKOID($p, $message='', $withpoint=false){
    if (in_array($p, static::VALIDSTRINGS))
      return;
    return static::assertIsSimpleString($p, $message, $withpoint);
  }
  static function assertIsKOID($p, $message='', $emptyok=false) {

    $pattern="[_a-z0-9]+)\:([a-z.0-9_-]+";
    $pattern="|".$pattern;	/* prendre en compte les chaines vides */
    if(!preg_match('/^('.$pattern.')$/i',$p)) {
      self::alert(__METHOD__.":{$message} '{$p}'");
    }
  }
  static function assertIsSessionID($session_id, $message='Bad session ID') {
    if(!preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $session_id)) {
      self::alert(__METHOD__.":{$message} '{$session_id}'");
    }
  }
  static function assertIsClass($p, $message='Bad class') {
    if(!preg_match('/^([0-9a-z_\\\\]{1,128})$/i',$p)) {
      self::alert(__METHOD__.":{$message} '{$p}'");
    }
  }
  static function assertIsLang($p, $message='Bas LANG spec') {
    if(!preg_match('/^([a-z]{1,4})$/i',$p)) {
      self::alert(__METHOD__.":{$message} '{$p}'");
    }
  }
  static function assertIsMime($p, $message='') {
    global $LIBTHEZORRO;
    if(!preg_match('@^(|[a-z0-9-]+/[a-z\.0-9\+ -]+)$@i', $p)) {
      self::alert(__METHOD__.":{$message} '{$p}'");
    }
    include_once($LIBTHEZORRO.'src/Library/MimeTypes.php');
    $mimeClass=\Seolan\Library\MimeTypes::getInstance();
    if(!$mimeClass->isValidMime($p)) {
      self::warning(__METHOD__.':'.$message);
      critical_exit($message, 403);
    }
  }
  static function assertIsGravity($p, $message='') {
    if(!in_array($p,['NorthWest','North','NorthEast','West','Center','East','SouthWest','South','SouthEast'])) {
      self::alert(__METHOD__.":{$message} '{$p}'");
    }
  }
  static function assertIsRotate($p, $message='') {
    if(!preg_match('/^([0-9]*[\>\<]{0,1})$/',$p)) {
      self::alert(__METHOD__.":{$message} '{$p}'");
    }
  }

  /// nettoyage de $filename et vérification que $filename est bien sous $parentname
  static function assertFileIsUnder($filename, $parentname, $message) {
    if(empty(@$GLOBALS['HAS_VHOSTS'])) {
      $filename=preg_replace("@([^a-z0-9/_.:-]+)@i","",$filename);
      if(file_exists($filename)) {
	$filename=realpath($filename);
	$parentname=realpath($parentname);
	if(strpos($filename, $parentname)!==0) {
	  self::alert(__METHOD__.":{$message} '{$filename}' '{$parentname}'");
	}
      }
    }
  }
  
  static function assertIsGeometry($p, $message='') {

    $number = "\d*(?:\.\d+)?"; // It's a reference to use in other cases that matches any kind of number/float
    
    $width = "(?<w>(?:$number)?%?)?"; // This is the first part, the width
    $height = "(?:x(?<h>(?:$number)?%?))?"; // Here is the height, the same as "width" but starting with an "x"
    
    $aspect = "[!><@%^]{0,5}"; // These are the different filters one can use to stretch, shrink, etc.
    
    $size = "$width$height"; // To match any size we need width and height at least (aspect comes later)
    
    $offset = "(?<x>[+-]$number)?(?<y>[+-]$number)?"; // This is the geometry offset
    
    $regexp = "(?<size>$size)(?<aspect>$aspect)?(?<offset>$offset)"; // Here we have the full regexp
    if(!preg_match('/^'.$regexp.'$/', $p)) {
      self::alert(__METHOD__.":{$message} '{$p}'");
    }
  }

  static function assertIsUrl($url, $message='') {
    $exprs=array('/etc/','proc/','../..','GLOBALS','mosConfig');
    foreach($exprs as $foo) {
      if(strstr($url, $foo)!=FALSE) {
	self::fatal('url is not secure (rule_url1:'.$foo.')'." ({$url})");
      }
    }
    if(preg_match_all('/[^a-z](schema|chr|null|all|union|select|char|concat|from|sleep|\(|\))[^a-z0-9]/i',$url, $matches)) {
      if(!empty($matches)) {
	$matches2=array_unique($matches[1]);
	if(!empty($matches2) && count($matches2)>=3) {
	  self::fatal(__METHOD__.": ".implode(",", $matches2)." ".$message);
	}
      }
    }
  }
  static function assertIsAuthToken($p, $message=''){
    if(!preg_match('/^(AUTHTOKEN:[0-9a-z]{1,24}:[0-9a-z]{1,80})$/i',$p)) {
      self::alert(__METHOD__.":{$message} '{$p}'");
    }
  }
  /// on relaie à la classe sécurity pour une sortie et un blocage immédiat
  static function fatal($message) {
    global $LIBTHEZORRO;
    include_once($LIBTHEZORRO.'src/Helper/system.php');
    include_once($LIBTHEZORRO.'src/Library/Security.php');
    \Seolan\Library\Security::fatal($message);
  }
  /// on relaie à la classe sécurity pour une sortie et un blocage au bout de la 3eme tentative
  static function alert($message) {
    global $LIBTHEZORRO;
    include_once($LIBTHEZORRO.'src/Helper/system.php');
    include_once($LIBTHEZORRO.'src/Library/Security.php');
    \Seolan\Library\Security::alert($message);
  }
  static function warning($message) {
    global $LIBTHEZORRO;
    include_once($LIBTHEZORRO.'src/Helper/system.php');
    include_once($LIBTHEZORRO.'src/Library/Security.php');
    \Seolan\Library\Security::warning($message);
  }
}
