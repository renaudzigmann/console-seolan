<?php
if (strpos($_SERVER['REQUEST_URI'],'/csx/scripts-admin')===0)
  define('TZR_ADMINI',1);
if (false === include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/local.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}
if (false === include_once($LIBTHEZORRO.'bootstrap.php')) {
  header("HTTP/1.1 500 Seolan Server Error");
  exit(0);
}

// vérification de l'URL
include_once($LIBTHEZORRO.'src/Library/SecurityCheck.php');
\Seolan\Library\SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);

use \Seolan\Core\Logs;
use \Seolan\Core\Module\Module;
use \Seolan\Core\Kernel;
use \Seolan\Core\User;
use \Seolan\Core\Lang;
use \Seolan\Core\DataSource\DataSource;
use \Seolan\Library\Security;
use \Seolan\Core\System;

sessionStart();

// Récupération de l'image
$img = $_POST['imageData'];
$format_image = $_POST["formatImageSave"];
$data = base64_decode($img);
// Récupération du module et du champ etc 

$moid=$_GET['moid'];
\Seolan\Library\SecurityCheck::assertIsNumeric($moid, 'image-uploader: moid');

$field = $_REQUEST['field'];
$oid = $_REQUEST['oid'];

if (empty($moid) || empty($field) || empty($oid)){
  Security::alert(__FILE__." : invalid/incomplete request parameters moid '{$moid}', field '{$field}', oid '{$oid}'");
}
$table=Kernel::getTable($oid);
if (empty($table) || !System::tableExists($table) || !Kernel::objectExists($oid))
  Security::alert(__FILE__." : invalid request oid {$oid}");

$GLOBALS['XUSER']=new User(array('UID'=>getSessionVar('UID')));
$GLOBALS['XLANG']=new Lang();

$xset = DataSource::objectFactoryHelper8('SPECS='.$table);

if (!$xset->fieldExists($field))
  Security::alert(__FILE__." : unknown field table '{$table}', field '{$field}'");

list($levelAccessOk) = User::secure8(':rw', $moid, $oid);
if (!$levelAccessOk)
  Security::alert(__FILE__." : invalid ACL moid '{$moid}', uid '{$GLOBALS['XUSER']->_curoid}'");

// écriture d'un fichier temp pour ce champ, table, varid
$tmpfilename = md5(session_id().$table.$field.$oid);
file_put_contents(TZR_TMP_DIR.$tmpfilename, $data);

// calcul de la vignette 
$tmpthumbfilename = 'thumb'.$tmpfilename;
$profile = '+profile "*" ';

if ($format_image == 'png') { // à voir $st:filedest
  $st = 'png32';
} 
$g = '-resize "'.TZR_THUMB_SIZE.'x'.TZR_THUMB_SIZE.'>"';

exec(TZR_MOGRIFY_RESIZER." $profile $g ".
     " -colorspace sRGB ".
     " '".TZR_TMP_DIR."$tmpfilename' '".TZR_TMP_DIR."$tmpthumbfilename' 2>&1  > /dev/null", $res);
echo('{"thumb":"'.$tmpthumbfilename.'","tmp":"'.$tmpfilename.'"}');

