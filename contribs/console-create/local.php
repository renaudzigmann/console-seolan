<?php
$DATABASE_USER = "%s";
$DATABASE_PASSWORD = "%s";
$DATABASE_HOST = "%s";
$DATABASE_NAME = "%s";
$LANG_DATA = "FR";
$LANG_USER = "FR";
$LIBTHEZORRO = "%s";
$HOME_ROOT_URL = "%s";

$TZR_LANGUAGES = ["FR"=>"fr"];

define('TZR_USE_APP', 1);
define('HTML5MEDIA', true);

$DEBUG_IPs = ['127.0.0.1'];
if (in_array($_SERVER['REMOTE_ADDR'], $DEBUG_IPs) || in_array($_SERVER["HTTP_X_REAL_IP"], $DEBUG_IPs)) {
  define('TZR_DEBUG_MODE', E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT & ~E_WARNING);
  define('TZR_LOG_LEVEL', 'PEAR_LOG_DEBUG');
  $TZR_LOG_FILTERS = array('.');
  ini_set('display_errors',1);
} else {
  define('TZR_DEBUG_MODE', 0);
}

define('TZR_FROM_URL', 'https://%s/');
define('TZR_SUPPORT_ADDRESS', 'support-tzr@%s');
define('TZR_ARCHIVE_ADDRESS', 'archive-tzr@%s');
define('TZR_SENDER_ADDRESS', 'console@%s');
define('TZR_XMODMAILLIST_RETURN_ADDRESS','ml-return+xx@%s');
define('TZR_FAX_SENDER', 'faxsender@%s');
define('TZR_SMS_SENDER', 'faxsender@%s');
define('TZR_NOREPLY_ADDRESS', 'noreply@%s');
define('TZR_DEBUG_ADDRESS', 'console@%s');
define('TZR_ROOTAUTH_IMAP','imap.%s');
define('TZR_ROOTAUTH_IMAPUSER','console-root-urgency@%s');
define('ROCKETCHAT_SERVEUR_URL', "https://rocketchat.%s");
