<?php

if (!empty($_REQUEST['_mark']))
  $marker=preg_replace('/([^\(\)a-zA-Z0-9_\:-]+)/','',$_REQUEST['_mark']);
if (empty($_REQUEST['_total']))
  $totalon='(total)';
else
  $totalon=preg_replace('/([^\(\)a-zA-Z0-9_\:-]+)/','',$_REQUEST['_total']);
if(!preg_match('/^([a-zA-Z]{2})$/',$_REQUEST['_lang'])) $_REQUEST['_lang']='';

if(empty($marker) && empty($_REQUEST['_marks'])) $marker=array('(nomarker)');
if(empty($marker)) $marker=explode(",",$_REQUEST['_marks']);
if(!is_array($marker)) $marker=array($marker);
$lang=$_REQUEST['_lang'];
if(empty($lang)) $lang='';
$now=date('Y-m-d');
$nowrepl=date('YmdH');
foreach($marker as $m) {
  $line="$m;$lang;$now;$totalon;".$_SERVER['SERVER_NAME'].";\n";
}
file_put_contents($_SERVER['DOCUMENT_ROOT'].'../var/logs/markers-'.$nowrepl, $line, FILE_APPEND | LOCK_EX);
$last_modified = gmdate('D, d M Y H:i:s T',time()-10);
session_cache_limiter('nocache');
header('Content-Type: image/gif');
header('Pragma: no-cache');
header("Last-Modified: $last_modified");
header("Expires: " . $last_modified);
$size=filesize('../public/templates/images/pixel.gif');
header('Accept-Ranges: bytes');
header("Content-Length: $size");
readfile('../public/templates/images/pixel.gif');
