<?php
/*
handler for request initiated by radupload applet
purpose : 
store updloaded files in temporary directory with additionnals information
 - additionnal informations : which tzr table, which field, which form (uniqid)
return list of currently stored files
handler for request initited by swfupload
purpose :
store uploader files
*/
include_once(__DIR__.'/isadmin.php');
if (isAdmin())
  define('TZR_ADMINI',1);
if (false === include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/local.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}
if (false === include_once($LIBTHEZORRO.'bootstrap.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

include_once($LIBTHEZORRO.'src/Library/SecurityCheck.php');

\Seolan\Library\SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);

$swf = 0;
if (isset($_REQUEST['swf'])){
  // check for test upload request ??? (see flask.net.FileReference)
  if (!isset($_FILES['Filedata'])){
    die('no files');
  } 
  // construct an array from Filedata field 
  // -> ensure sames parameters as  multifiles upload
  $_FILES['userfile'] = array();

  foreach($_FILES['Filedata'] as $k=>$v){
    $_FILES['userfile'][$k] = array(); 
    $_FILES['userfile'][$k][] = $v;
  }

  $parms = explode('*', $_REQUEST['swf']);
  $_REQUEST['uniqid'] = $parms[0];
  $_REQUEST['table'] = $parms[1];
  $_REQUEST['field'] = $parms[2];
}

$table = $_REQUEST['table'];
$field = $_REQUEST['field'];
$uniqid = $_REQUEST['uniqid'];

\Seolan\Library\SecurityCheck::assertIsSimpleString($table, 'uploader: table');
\Seolan\Library\SecurityCheck::assertIsSimpleString($field, 'uploader: table');
\Seolan\Library\SecurityCheck::assertIsSimpleString($uniqid, 'uploader: table');

$ret = '';
// create unique directory under tmp dir for this form/field/table
$destdir = TZR_TMP_DIR.'upload'.$uniqid.'/'.$table.'/'.$field;
$ret .=  'destdir = ' . $destdir;
\Seolan\Library\Dir::mkdir($destdir, false);
$files = $_FILES['userfile'];
$k = count($files['name']);
$catalog = array();
$fc = $destdir.'/'.$uniqid.'_catalog.txt';
// permettre de deposer plusieurs fichiers en plusieurs fois
// ajouter un controle sur les doublons serait une bonne idee
if (file_exists($fc)){
  $catalog = unserialize(file_get_contents($fc));
}
$errors = array();
for($i=0;$i<$k;$i++){
  // si pas erreur
  $shortName = explode('/',urldecode($files['name'][$i]));
  $shortName = stripslashes($shortName[count($shortName)-1]);
  if (empty($files['error'][$i])){
    move_uploaded_file($files['tmp_name'][$i], $destdir. '/' . $shortName);
    $ret .= "\n".$files['tmp_name'][$i]."=>".$shortName;
    $catalog['fullname'][] = stripslashes(urldecode($files['name'][$i]));
    $catalog['name'][] = $shortName;
    $mimeClasse = \Seolan\Library\MimeTypes::getInstance();
    $catalog['type'][] = $mimeClasse->getValidMime($files['type'][$i],$files['tmp_name'][$i],$files['name'][$i]);
    $catalog['size'][] = $files['size'][$i];
    $catalog['tmp_name'][] = $destdir.'/'.$shortName;
  } else {
    $errors[] = array($shorName=>$files['error'][$i]);
  }
}

file_put_contents($fc, serialize($catalog));
?>
<html>
<head></head>
<body bgcolor="#eeeeee">
<table width="100%" cellspacing="1" border="0">
<?foreach($errors as $name=>$error){?>
<tr><td bgcolor="#eeeeee"><font color="#ff0000"><?=$name?></font></td><td align="right" bgcolor="#ffffff">0 ko</td></tr>
<?}?>
<?foreach($catalog['name'] as $i=>$name){?>
<tr><td bgcolor="#ffffff"><?=$name?></td><td  align="right" bgcolor="#ffffff"><?=round($catalog['size'][$i]/1024,3)?> Ko</td></tr>
<?}?>
</table>
</body>
</html>
<?die();?>
