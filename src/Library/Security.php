<?php
namespace Seolan\Library;

class Security {
  /// signalement qu'une attaque ou un usage inapproprié est en cours en vu d'un bansissement, et sortie du programme
  static function alert($message) {
    checkSERVEREnvVariables();
    $ip=$_SERVER['REMOTE_ADDR'];
    
    $message.="\nREQUEST_URI: ".$_SERVER["REQUEST_URI"];
    if(class_exists("\Seolan\Core\Logs")){
      \Seolan\Core\Logs::update("security","", $message);
      \Seolan\Core\Logs::critical("security ",$message);
    }
    if(!\Seolan\Library\Security::isRobot($ip)) {
      file_put_contents(TZR_WWW_DIR."../logs/blacklist.log", $ip." [".date("c")."] Ban # ".getCurrentPageUrl().PHP_EOL, FILE_APPEND);
    } else {
      file_put_contents(TZR_WWW_DIR."../logs/blacklist.log", "# whitelisted robot: $ip ".$_SERVER["REQUEST_URI"].PHP_EOL, FILE_APPEND);
    }
    critical_exit($message);
  }

  /// sortie et blocage immédiat de l'ip suite à une erreur de sécurité
  static function fatal($message) {
    checkSERVEREnvVariables();
    $ip=$_SERVER['REMOTE_ADDR'];
    
    $message.="\nREQUEST_URI: ".$_SERVER["REQUEST_URI"];
    if(class_exists("\Seolan\Core\Logs")){
      \Seolan\Core\Logs::update("security","", $message);
      \Seolan\Core\Logs::critical("security ",$message);
    }
    if(!\Seolan\Library\Security::isRobot($ip)) {
      file_put_contents(TZR_WWW_DIR."../logs/blacklist.log", "# ".getCurrentPageUrl().PHP_EOL, FILE_APPEND);
      if(!empty($message))
	 file_put_contents(TZR_WWW_DIR."../logs/blacklist.log", "# $message".PHP_EOL, FILE_APPEND);
      file_put_contents(TZR_WWW_DIR."../logs/blacklist.log", $ip." [".date("c")."] Ban # Fatal 1: ".getCurrentPageUrl().PHP_EOL, FILE_APPEND);
      file_put_contents(TZR_WWW_DIR."../logs/blacklist.log", $ip." [".date("c")."] Ban # Fatal 2 ".PHP_EOL, FILE_APPEND);
      file_put_contents(TZR_WWW_DIR."../logs/blacklist.log", $ip." [".date("c")."] Ban # Fatal 3 ".PHP_EOL, FILE_APPEND);
    } else {
      file_put_contents(TZR_WWW_DIR."../logs/blacklist.log", "# whitelisted robot: $ip ".$_SERVER["REQUEST_URI"].PHP_EOL, FILE_APPEND);
    }
    critical_exit($message);
  }
  
  /// message dans les logs signalant un usage inapproprié
  static function warning($message) {
    checkSERVEREnvVariables();
    $ip=$_SERVER["REMOTE_ADDR"];
    
    $message.="\n"."REQUEST_URI: ".$_SERVER["REQUEST_URI"];
    if(class_exists("\Seolan\Core\Logs")){
      \Seolan\Core\Logs::update("security","", $message);
      \Seolan\Core\Logs::critical("security ",$message);
    }
  }
  
  /// savoir si c'est un robot
  static function isRobot(?string $ip) {
    if(empty($ip)) return false; /* pas d'ip, pas d'robot */
    $host=gethostbyaddr($ip);
    $ips=gethostbynamel($host);
    
    if(!in_array($ip, $ips)) return false;
    
    if($host && $ip!=$host && preg_match('/(.google.com|.googlebot.com|search.msn.com|search.qwant.com|.crawl.yahoo.net|.yandex.ru|.yandex.com|.yandex.net|.crawl.baidu.com|.crawl.baidu.jp|.pinterest.com|.semrush.com|.aviso.ci|.googleusercontent.com)$/',$host)) {
      return true;
    }
    return false;
  }
}

