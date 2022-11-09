<?php
session_cache_limiter('public');
ini_set("display_errors", 0);
header('Content-Type: text/css');
header('Last-Modified: '.gmdate('D, d M Y H').':00:00 GMT');
$files = explode(':', $_GET['files']);
$TZR_WWW_DIR = $_SERVER['DOCUMENT_ROOT'];
$txt = '';
foreach($files as $file) {
  if(!preg_match('/(\.css)$/', $file)) continue;
  $file = preg_replace('/([^a-z0-9_\/\.-]+)/i', '', $file);
  $file = str_replace('..', '', $file);
  $buffer = file_get_contents($TZR_WWW_DIR.'/'.$file);

  if($_GET['minify']) {
    $buffer = preg_replace('#(/\*(?:[^*]*(?:\*(?!/))*)*\*/|\s)+#', ' ', $buffer);
  }

  $txt .= $buffer." ";
}

header('Accept-Ranges: bytes');
header('Content-Length: '.strlen($txt));
echo $txt;