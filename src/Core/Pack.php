<?php
namespace Seolan\Core;
class Pack {
  private $_packages=array();
  private $_addfiles=array();
  static $namedPacks=array();

  function __construct() {
  }

  function add($name, $jsinclude, $jsheader) {
    $this->_packages[$name]=true;
    foreach($jsinclude as $i=>$filename) {
      if(empty($this->_jsinclude[$filename])) {
	$this->_jsinclude[$filename]=1;
      } else {
	$this->_jsinclude[$filename]++;
      }
    }
  }

  // ajout d'un package dans la liste des packages
  function addNamedPack($name, $add_files=true) {
    if(empty($this->_packages[$name])) {
      $this->_packages[$name]=new $name();
      $this->_addfiles[$name]=$add_files;
    }
  }

  function getNamedPack($name){
    if($this->packDefined($name)) {
      return $this->_packages[$name];
    }
    return false;
  }

  function getJsHeader() {
    $header1ToInclude="";
    $header2ToInlucde="";
    $jsinclude=array();
    $jsasyncinclude=array();
    $cssinclude=array();
    $debug = "\n<!-- ";
    foreach($this->_packages as $packageName => $package) {
      if(!$this->_addfiles[$packageName]) continue;
      $jsToInclude=$package->getJsIncludes();
      $jsAsyncToInclude = $package->getJsAsyncIncludes();
      $cssToInclude = $package->getCssIncludes();
      $header1ToInclude .= $package->getHeader();
      $header2ToInlucde .= $package->getHeader2();
      $jsRootPath = $package->getJSRootPath();
      $jsAsyncRootPath = $package->getJSAsyncRootPath();
      $cssRootPath = $package->getCSSRootPath();
      $debug .= "\n $packageName require ($jsRootPath) :  ".implode(',', $jsToInclude);
      foreach($jsToInclude as $file) {
		if(($ipack = array_search($file,$jsinclude)) === false) {
		  	$jsinclude[] = array( "path"=>$jsRootPath, "file"=>$file, 'name'=>[$packageName] );
		} else {
			$jsinclude[$ipack]['name'][] = $packageName;
		}
      }
      foreach($jsAsyncToInclude as $file) {
		if(!in_array($file,$jsasyncinclude)) {
	  $jsasyncinclude[] = ['path' => substr($file, 0, 1) == '/' ? '' : $jsAsyncRootPath, 'file' => $file];
		}
      }
      foreach($cssToInclude as $file){
        if (is_array($file)) {
          $_css = array_merge($file, ['path' => $cssRootPath, 'file' => $file['href']]);
        } else {
          $_css = ['path' => $cssRootPath, 'file' => $file];
        }
	if (!in_array($_css, $cssinclude)) {
          $cssinclude[] = $_css;
        }
      }
    }
    $debug .= "\n-->\n";
    $jsToIncludeAsStringConcat = '';
    $packsnames = [];
    foreach($jsinclude as $jsFile) {
      if(!empty($jsToIncludeAsStringConcat)) $jsToIncludeAsStringConcat.=':';
      $jsToIncludeAsStringConcat .= $jsFile['path'].$jsFile['file'];
      $packsnames[] = implode(',', $jsFile['name']);
    }
    $jsAsyncToIncludeAsStringConcat = '';
    foreach($jsasyncinclude as $jsAsyncFile) {
      if(!empty($jsAsyncToIncludeAsStringConcat)) $jsAsyncToIncludeAsStringConcat.=':';
      $jsAsyncToIncludeAsStringConcat.=$jsAsyncFile['path'].$jsAsyncFile['file'];
    }
    if (defined('TZR_DEBUG_MODE') && TZR_DEBUG_MODE == 1) {
      $header1ToInclude.= '<!-- '.implode(',', $packsnames)." -->\n";
      $header1ToInclude.= $debug;
    }
    $header1ToInclude.='<script src="'.myUrl2cdn(TZR_SHARE_SCRIPTS.'onejs.php?files='.$jsToIncludeAsStringConcat).'"></script>';
    if(!empty($jsAsyncToIncludeAsStringConcat)) {
      $header1ToInclude.="\n".'<script async src="'.myUrl2cdn(TZR_SHARE_SCRIPTS.'onejs.php?files='.$jsAsyncToIncludeAsStringConcat).'"></script>';
    }
    $header1ToInclude.="\n<script>";
    foreach($this->_packages as $name=>$f) {
      $header1ToInclude.='TZR["'.$name.'"]=true;';
    }
    $header1ToInclude.='</script>';
    foreach($cssinclude as $link) {
      $href = $link['file'];
      $media = 'all';
      if (array_key_exists('media',$link)) {
        $media = $link['media'];
      }
      $header1ToInclude.="\n".'<link href="'.$link['path'].$href.'" rel="stylesheet" type="text/css" media="'.$media.'"'.(OutputIsXHTML() ? ' />' : '>');
    }
    $header1ToInclude .= $header2ToInlucde;
    return $header1ToInclude;
  }

  function getStubs() {
    return array("header"=>$this->getJsHeader());
  }

  function packDefined($name) {
    return isset($this->_packages[$name]);
  }

  function delNamedPack($name) {
    unset($this->_packages[$name]);
  }
}
