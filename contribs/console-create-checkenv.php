<?php
include_once('lib2.php');


// modules php

$modules = ['bcmath','calendar','Core','ctype','curl','date','dom','exif','fileinfo','filter','ftp','gd','hash','iconv','imap','intl','json','ldap','libxml','mbstring','mysqli','mysqlnd','openssl','pcre','PDO','pdo_mysql','Phar','posix','readline','redis','Reflection','session','soap','sockets','SPL','standard','tidy','tokenizer','xml','xmlreader','xmlwriter','xsl','Zend OPcache','zip','zlib'];

$php = myReadline("Commande php 7.4 ", "php7.4");

echo("\r\nphp => $php");

// modules installés - modules attendus

$res=[];
exec("{$php} -m", $res);
$installed = [];
foreach($res as $line){
  $line=trim($line);
  if (preg_match('/^\[.+\]$/', $line) || empty($line))
    continue;
  $installed[] = $line;
}
$missing = array_diff($modules, $installed);
if (!empty($missing)){
  echo("\r\nModules php  à installer :");
  echo("\r\n\r\n\t".implode("\r\n\t", $missing));
} else {
  echo("\r\nmodules php ok");
}

// autres paquets
$libs = ['clamav','graphviz','pandoc'];

$res = [];
$installed = [];
exec("apt list --installed", $res);
foreach($res as $line){
  $line = trim($line);
  if (!empty($line)){
    list($name) = explode(',', $line);
    list($name) = explode('/', $name);
    $installed[] = $name;
  }
}
$missing = array_diff($libs, $installed);

if (!empty($missing)){
  echo("\r\nPaquets  à installer :");
  echo("\r\n\r\n\t".implode("\r\n\t", $missing));
} else {
  echo("\r\nAutres paquets ok");
}




