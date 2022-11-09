<?php
namespace UnitTests\Tools;
use \UnitTests\BaseCase;
class Tools {
  protected $start = null;
  function __construct(){
    $this->start = microtime(true);
  }
  public static function var_dump($var,$title=''){
    BaseCase::trace("\n\n{$title} : \n\t".tzr_var_dump($var));
  }
  public static function spl_object_id($o){
    ob_start();
    var_dump($o);
    $t = ob_get_clean();
    $r = preg_match('/(#[0-9]+) /',$t, $parts);
    if ($r)
      return $parts[1];
    else
      return '';
  }
  public static function traceObject($o, $anddump=false, $tostring=null){
    $class = get_class($o);
    if (method_exists($o, 'toString')){
      BaseCase::trace("\n".static::spl_object_id($o)." ".$o->toString());
    } elseif ($tostring){
      BaseCase::trace("\n".static::spl_object_id($o)." ".$tostring($o));
    }
    BaseCase::trace("\n".static::spl_object_id($o)." is a : \"$class\" and a : \"".implode('","', class_parents($o))."\"");
    BaseCase::trace(" and implements : \"".implode('","', class_implements($o))."\"\n");
    if ($anddump){
      ob_start();
      var_dump($o);
      $d = ob_get_clean();
      BaseCase::trace("\n......");
      BaseCase::trace($d);
      BaseCase::trace("\n......");
    }
  }
  function log($m){
    $time = sprintf("% 04.4f", microtime(true) - $this->start);
    BaseCase::trace("\n$time : $m");
  }
  function sqldump($q,$values=[]){
    $trace = "$q".implode(",",$values);
    $mecho = function($m) use(&$trace){
      $trace .= $m;
    };
    $res = getDB()->select($q,$values)->fetchAll();
    $cols = [];
    foreach($res as $line){
      foreach($line as $k=>$v){
	if (!isset($cols[$k]))
	  $cols[$k] = strlen($k);
	$cols[$k] = max($cols[$k], strlen($v));
      }
    }
    $mecho("\n");
    foreach(array_keys($cols) as $k){
      $mecho('+--'.str_pad(str_pad('',strlen($k),'-'), $cols[$k],'-').'--');
    }
    $mecho('+');
    $mecho("\n");
    foreach(array_keys($cols) as $k){
      $mecho('|  '.str_pad($k, $cols[$k]).'  ');
    }
    $mecho("|\n");
    foreach(array_keys($cols) as $k){
      $mecho('+--'.str_pad(str_pad('',strlen($k),'-'), $cols[$k],'-').'--');
    }
    $mecho('+');
    foreach($res as $line){
      $mecho("\n");
      foreach(array_keys($cols) as $k){
	$mecho('|  '.str_pad($line[$k], $cols[$k]).'  ');
      }
      $mecho('|');
    }
    $mecho("\n");
    foreach(array_keys($cols) as $k){
      $mecho('+--'.str_pad(str_pad('',strlen($k),'-'), $cols[$k],'-').'--');
    }
    $mecho('+');
    $mecho("\n");
    BaseCase::trace($trace);
  }
  function execDump($cmd){
    exec($cmd, $r);
    foreach($r as $line)
      BaseCase::trace($line);
  }
}
