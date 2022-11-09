<?php
namespace Seolan\Core;

class Param {
  public $_ar=array();
  public $_def=array();
  public $_paramoptions="all";
  public $_validation="all";
  static $post;
  static $get;
  static $put;
  static $cookie;
  static $file;
  static $request;

  function __construct($ar,$def=array(), $option="all", $validation=array()) {
    $this->_paramoptions=$option;
    $this->_validation=$validation;
    \Seolan\Core\Audit::plusplus("xparam");

    $this->_options=(isset($ar["_options"])?$ar["_options"]:NULL);
    $this->_local=isset($this->_options["local"]) || ($option!="all")|| isset($ar['_local']);

    // $newdef est le tableau des valeurs préparées pour les cas "norequest"
    if(!is_array($def)) $def=array();
    $newdef=$ar;
    foreach($def as $v => &$val) {
      $newdef[$v] = (isset($ar[$v])?$ar[$v]:$val);
      if(!isset($ar[$v]) && !$this->_local) {
	$v1 = (isset($_REQUEST[$v])?$_REQUEST[$v]:NULL);
	/// validation des entrées quand elles viennent de l'interface
	$this->validateParameter($v, $v1);
      }
      
      if(isset($v1)) {
	//on convertit dans le cas ou la valeur vient de $REQUEST
	$ch = \Seolan\Core\Lang::getCharset();
	if($ch != TZR_INTERNAL_CHARSET){
	  if(is_array($v1)){
	    array_walk_recursive($v1, 'array_convert_charset', array($ch,TZR_INTERNAL_CHARSET) );
	  }else{
	    convert_charset($v1,$ch,TZR_INTERNAL_CHARSET);
	  }
	}
	$ar[$v]=$v1;
	unset($v1);
      }
      if(!isset($ar[$v])) $ar[$v]=$val;
    }
    $this->_ar=$ar;
    $this->_def=$newdef;
  }

  /// validation d'un paramètre en fonction des règles de contrôle
  function validateParameter($field, $value) {
    $parameters=@$this->_validation[$field];
    if(!empty($value) && isset($parameters)) {
      if(filter_var($value, $parameters[0], $parameters[1])===FALSE) {
	\Seolan\Library\Security::alert('REQUEST parameter <'.$field.'> with value <'.$value.'> is unsafe');
      }
    }
    return true;
  }

  function is_set($p, $checkall=false) {
    if(isset($this->_ar[$p])) return true;
    if(isset($this->_def[$p])) return true;
    if($this->_local) return false;
    if(isset($_REQUEST[$p])) return true;
    return $checkall && (isset($_SESSION[$p]) || isset($GLOBALS[$p]));
  }

  function set($p, $v) {
    $this->_ar[$p]=$this->_def[$p]=$v;
  }

  /// Changement de la variable $p pour la methode $method dans le contexte de la query.
  // la query d'origine n'est pas modifiée
  // ex: $p->setQueryContext('post', 'pagesize', 10);
  function setQueryContext($method, $p, $v) {
    $this->{$method}[$p]=$v;
  }
  

  function get($p,$option=NULL) {
    $ret=NULL;
    $local=$this->_local || ($option=="local");
    $norequest=($option=="norequest");

    if(isset($this->_ar[$p]) && !$norequest) {
      return $this->_ar[$p];
    } elseif(isset($this->_def[$p]) && $norequest) {
      return $this->_def[$p];
    } elseif(!$local) {
      if(isset($_REQUEST[$p]) && !$norequest) {
	$ret = $_REQUEST[$p];
      } elseif(isset($_SESSION[$p])) {
	$ret = $_SESSION[$p];
      } elseif(!$norequest) {
	$ret = (isset($GLOBALS[$p])?$GLOBALS[$p]:NULL);
      }
    } else {
	return; // voir \Seolan\Core\DataSource\DataSource display
      $ret = NULL;
    }
    if($ret){
      //a voir si on le fait dans le cas ou la valeur vient de $GLOBALS ??
      $ch = \Seolan\Core\Lang::getCharset();
      if($ch != TZR_INTERNAL_CHARSET){
	if(is_array($ret)){
	  array_walk_recursive($ret, 'array_convert_charset', array($ch,TZR_INTERNAL_CHARSET) );
	}else{
	  convert_charset($ret,$ch,TZR_INTERNAL_CHARSET);
	}
      }
    }	
    return $ret;
  }
  function getArray($option=NULL) {
    $ret=array();
    foreach($this->_ar as $p=>$foo) {
      $ret[$p]=$this->get($p,$option);
    }
    return $ret;
  }
}

\Seolan\Core\Param::$post = $_POST;
\Seolan\Core\Param::$get = $_GET;
\Seolan\Core\Param::$put = @$_PUT;
\Seolan\Core\Param::$cookie = $_COOKIE;
\Seolan\Core\Param::$file = $_FILES;
\Seolan\Core\Param::$request = $_REQUEST;
