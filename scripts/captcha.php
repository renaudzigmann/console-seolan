<?php
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

// vérification de l'URL
include_once($LIBTHEZORRO.'src/Library/SecurityCheck.php');
\Seolan\Library\SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);
\Seolan\Library\SecurityCheck::assertIsSimpleString($_SERVER['id'], "Bad ID in captcha", false);

session_cache_limiter('nocache');

$conn=&getDB();

\Seolan\Core\System::loadVendor('captcha/kcaptcha.php');
$captcha = new KCAPTCHA();
$captcha_str = md5($captcha->getKeyString());
$captcha_id=$_REQUEST['id'];

$rs=$conn->execute('DELETE FROM _VARS WHERE name=?', ["CAPTCHA_{$captcha_id}"]);
$rs=$conn->execute('INSERT INTO _VARS(UPD,name,value) VALUES(NULL,?, ?)', ["CAPTCHA_{$captcha_id}",$captcha_str]);
