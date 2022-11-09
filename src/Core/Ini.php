<?php
//BDOC
// NAME
//   \Seolan\Core\Ini -- gestion du fichier de paramètres
// DESCRIPTION
//   Lecture et écriture, consultation des fichiers au format windows
//   .ini. Cette classe utilise les fonctions systèmes, et réalise elle
//   même certaines écritures.
// SYNOPSIS
//   La class lit le contenu du fichier local.ini ainsi que le contenu
//   du fichier config/tzr.ini. Le contenu du local.ini a toujours priorité.
//EDOC

namespace Seolan\Core;

class Ini {
  private $_filename=NULL;
  function __construct($f=NULL) {
    if($f==NULL) $f=CONFIG_INI;
    $this->_filename=$f;
  }
  function load($process=false) {
    $a1=$this->loadRO($process);
    $a2=@parse_ini_file($this->_filename,$process);
    if(!is_array($a2)) $a3=$a1;
    else $a3=array_merge($a1,$a2);
    return $a3;
  }
  function loadRO($process=false) {
    global $LIBTHEZORRO;
    $a1=parse_ini_file($LIBTHEZORRO."config/tzr.ini");
    $a2=@parse_ini_file('/etc/seolan8/config.ini',$process);
    if(!is_array($a2)) $a2=array();
    $a3=array_merge($a1,$a2);
    return $a3;
  }

  /// rend un tableau avec toutes les configs
  function getConfig() {
    $_content_ro=$this->loadRO(true);
    $_content=parse_ini_file($this->_filename,true);
    return array_merge($_content,$_content_ro);
  }

  function edit($ar=NULL) {
    global $LIBTHEZORRO;
    $_content_ro=$this->loadRO(true);
    $_content=parse_ini_file($this->_filename,true);
    foreach($_content_ro as $section => $ar) {
      foreach($ar as $k => $v) {
	$GLOBALS['XSHELL']->tpldata['ini']['content'][$section][$k]['val']=$v;
	$GLOBALS['XSHELL']->tpldata['ini']['content'][$section][$k]['ro']=1;
      }
    }
    foreach($_content as $section => $ar) {
      foreach($ar as $k => $v) {
	$GLOBALS['XSHELL']->tpldata['ini']['content'][$section][$k]['val']=$v;
	$GLOBALS['XSHELL']->tpldata['ini']['content'][$section][$k]['ro']=0;
      }
    }
    foreach($GLOBALS['XSHELL']->tpldata['ini']['content'] as $section => $ar) {
      ksort($GLOBALS['XSHELL']->tpldata['ini']['content'][$section]);
    }
  }
  function procEdit($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $ini=$p->get("ini");
    $fp=fopen($this->_filename,"w");
    foreach($ini as $section => $cont) {
      $txt.="[$section]\n";
      foreach($cont as $var => $val) {
	if(get_magic_quotes_gpc())
	  $val=stripslashes($val);
	$txt.="$var = \"$val\"\n";
      }
    }
    fwrite($fp, $txt);
    fclose($fp);
  }
  function addVariable($ar) {
    global $LIBTHEZORRO;
    $p=new \Seolan\Core\Param($ar, array());
    $glob=$p->get("section");
    $vari=$p->get("variable");
    $valu=$p->get("value");
      
    if(!empty($vari) && $vari != '' ){
      $_content_ro=$this->loadRO(true);
      $_content=parse_ini_file($this->_filename,true);
      $_content[$glob][$vari]=$valu;
      $fp=fopen($this->_filename,"w");
      $txt="";
      foreach($_content as $section => $cont) {
	$txt.="[$section]\n";
	foreach($cont as $var => $val) {
	  $txt.="$var = \"$val\"\n";
	}
      }
      fwrite($fp, $txt);
      fclose($fp);
    }
    $this->edit();
  }
  function delVariable($ar) {
    global $LIBTHEZORRO;
    $p=new \Seolan\Core\Param($ar, array());
    $glob=$p->get("section");
    $vari=$p->get("variable");
    $_content_ro=$this->loadRO(true);
    $_content=parse_ini_file($this->_filename,true);
    $fp=fopen($this->_filename,"w");
    $txt="";
    foreach($_content as $section => $cont) {
      $first = true;
      foreach($cont as $var => $val) {
	if(($glob && $glob==$section && $var==$vari) || (!$glob && $var==$vari)) continue;
        if($first) {
          $txt.="[$section]\n";
          $first=false;
        }
	$txt.="$var = \"$val\"\n";
      }
    }
    fwrite($fp, $txt);
    fclose($fp);
    $this->edit();
  }
  static function get($n) {
    if(isset($GLOBALS['TZR'][$n])) return $GLOBALS['TZR'][$n];
    return NULL;
  }
  static function getWithDefault($n,$default=null) {
    if(isset($GLOBALS['TZR'][$n])) return $GLOBALS['TZR'][$n];
    return $default;
  }
}
