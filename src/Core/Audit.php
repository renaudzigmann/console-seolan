<?php
/// Classe de comptage d'un certain nombre d'indicateurs de performance

namespace Seolan\Core;

class Audit {
  static $_status = array();
  static function plusplus($item) {
    if(isset(\Seolan\Core\Audit::$_status[$item])) {
      \Seolan\Core\Audit::$_status[$item]++;
    } else {
      \Seolan\Core\Audit::$_status[$item]=1;  
    }
  }
  static function plus($item,$quanta) {
    if(isset(\Seolan\Core\Audit::$_status[$item])) {
      \Seolan\Core\Audit::$_status[$item]+=$quanta;
    } else {
      \Seolan\Core\Audit::$_status[$item]=$quanta;  
    }
  }
  static function get($item) {
    return \Seolan\Core\Audit::$_status[$item];
  }
  static function show($item=NULL) {
    if(!empty($item)) return $item.': '.\Seolan\Core\Audit::$_status[$item].';';

    $msg='';
    foreach(\Seolan\Core\Audit::$_status as $it => $cnt) {
      $msg.="$it: $cnt; ";
    }
    return $msg;
  }
  static function autoloadLog(){
    if (isset($GLOBALS['autoloadLogs']))
      if (defined('TZR_LOG_LEVEL') && TZR_LOG_LEVEL == \Zend\Log\Logger::DEBUG){
	return $GLOBALS['autoloadLogs']['calls']." load request, ".count($GLOBALS['autoloadLogs']['loaded'])." classes loaded, ".count($GLOBALS['autoloadLogs']['missing'])." delegated/missing : ".implode(',',$GLOBALS['autoloadLogs']['missing']);
      } else
	return $GLOBALS['autoloadLogs']['calls']." load request, ".count($GLOBALS['autoloadLogs']['loaded'])." classes loaded, ".count($GLOBALS['autoloadLogs']['missing'])." delegated/missing";
    else
      return '';
  }
}
?>
