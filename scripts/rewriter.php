<?php
include_once(__DIR__.'/isadmin.php');
if (isAdmin()) define('TZR_ADMINI',1);
$url='/'.$_GET['url'].".html";
if(file_exists($_SERVER['DOCUMENT_ROOT'].'../tzr/localref.inc')) {
  @include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/localref.inc');
  if(function_exists('TZR_url2Dynamic')) {
    $url2 = TZR_url2Dynamic($_GET['url'].".html");
    if(!empty($url2)) {
      $url2="http://".$_SERVER['SERVER_NAME']."/".$url2;
      readfile($url2);
      die();
    }
  }
}

// comportement par défaut
if(eregi('^/([a-z]{2})_([^\./]+)\.html$',$url)) {
  if(eregi('^/([a-z]{2})_oidit_([^_]+)_([^/\._]+)\.html$',$url)) {
    $url=eregi_replace('^/([a-z]{2})_oidit_([^_]+)_([^/\._]+)\.html$','/index.php?_lang=\\1&oidit=\\2:\\3',$url);
    $url="http://".$_SERVER['SERVER_NAME'].$url;
  }  elseif(eregi('^/([a-z]{2})_([^_]{1}[^/\.]+)\.html$',$url)) {
    $url=eregi_replace('^/([a-z]{2})_([^/\.]+)\.html$','/index.php?_lang=\\1&alias=\\2',$url);
    $url="http://".$_SERVER['SERVER_NAME'].$url;
  }
} else {
  if(eregi('^/oidit_([^/\._]+)_([^/\._]+)\.html$',$url)) {
    $url=eregi_replace('^/oidit_([^/\._]+)_([^/\._]+)\.html$','/index.php?oidit=\\1:\\2',$url);
    $url="http://".$_SERVER['SERVER_NAME'].$url;
  } elseif(eregi('^/([^/\.]+)\.html$',$url)) {
    $url=eregi_replace('^/([^/\.]+)\.html$','/index.php?alias=\\1',$url);
    $url="http://".$_SERVER['SERVER_NAME'].$url;
  } 
}
readfile($url);
die();

?>
