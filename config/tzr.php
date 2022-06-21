<?php
// experimental
// SYSTEME D'EXPLOITATION. La valeur par defaut est LRH == Linux Redhat & Debian
//
if(!defined('TZR_TARGET_SYS')) define('TZR_TARGET_SYS','LRH');

// DEFINITION DES CHEMINS
//

// repertoire principal de l'application, racine du site Internet
if(empty($TZR_WWW_DIR)) $TZR_WWW_DIR=$_SERVER['DOCUMENT_ROOT'];
if(empty($TZR_WWW_DIR)) $TZR_WWW_DIR=$_SERVER['HOME'].'/';
define('TZR_WWW_DIR', $TZR_WWW_DIR);

// nom du répertoire d'installation
if(!defined('TZR_WWW_CSX')) define('TZR_WWW_CSX', '/csx/');

// repertoire des fichiers programmes locaux et du local.ini
if(empty($LOCALLIBTHEZORRO)) $LOCALLIBTHEZORRO = TZR_WWW_DIR.'../tzr/';

// DEFINITION DES REPOSITORIES
if (!isset($GLOBALS['REPOSITORIES']))
  $GLOBALS['REPOSITORIES'] = [];
$GLOBALS['REPOSITORIES']['Seolan']=['src'=>$GLOBALS['LIBTHEZORRO'].'src/'];
$GLOBALS['REPOSITORIES']['Local']=['src'=>$GLOBALS['LOCALLIBTHEZORRO'],'smarty_plugins'=>$GLOBALS['LOCALLIBTHEZORRO'].'smarty/plugins/'];

// repertoire cgibin
if(!defined('TZR_CGIBIN_DIR')) define('TZR_CGIBIN_DIR',TZR_WWW_DIR.'../cgi-bin/');

// repertoire des données temporaires
if(!defined('TZR_VAR_DIR')) define('TZR_VAR_DIR', '/var/tzr/');
if(!defined('TZR_LOCK_DIR')) define('TZR_LOCK_DIR',TZR_VAR_DIR.'tmp/');
if(!defined('TZR_VAR2_DIR')) define('TZR_VAR2_DIR', TZR_WWW_DIR.'../var/');

// repertoire des fichiers temporaraires, commun a tous les sites
if(!defined('TZR_TMP_DIR')) define('TZR_TMP_DIR',TZR_VAR2_DIR.'tmp/');
if(!defined('TZR_DBCACHE_DIR')) define('TZR_DBCACHE_DIR', TZR_VAR2_DIR.'db/');
if(!defined('TZR_STATUSFILES_DIR')) define('TZR_STATUSFILES_DIR', TZR_VAR2_DIR.'status/');
// doit disparaître @todo
define('TZR_LEGACY_PUBLIC_RESOURCES_URL',TZR_WWW_CSX.'public/templates/');
define('TZR_LEGACY_ICO_URL',TZR_LEGACY_PUBLIC_RESOURCES_URL); 
// URL des icones des labels
define('TZR_ICO_URL',TZR_WWW_CSX.'src/Core/public/'); 
// URL des templates de l'admin, en relatif a la racine
if (!defined('TZR_SHARE_URL')) define('TZR_SHARE_URL',TZR_WWW_CSX.'templates/'); 
if (!defined('TZR_SHARE_DIR')) define('TZR_SHARE_DIR',$LIBTHEZORRO.'src/');

// URL des scripts mutualisés
if(!defined('TZR_SHARE_SCRIPTS_ADMIN')) define('TZR_SHARE_SCRIPTS_ADMIN',TZR_WWW_CSX.'scripts-admin/');
if(!defined('TZR_SHARE_SCRIPTS_FO')) define('TZR_SHARE_SCRIPTS_FO',TZR_WWW_CSX.'scripts/');
if(!defined('TZR_ADMINI')) define('TZR_ADMINI',0);
if(TZR_ADMINI) define('TZR_SHARE_SCRIPTS',TZR_SHARE_SCRIPTS_ADMIN);
else define('TZR_SHARE_SCRIPTS',TZR_SHARE_SCRIPTS_FO);
define('TZR_SHARE_ADMIN_PHP',TZR_SHARE_SCRIPTS.'admin.php');
define('TZR_AJAX8',TZR_SHARE_SCRIPTS.'ajax8.php');
define('TZR_SHARE_MARKER_PHP',TZR_SHARE_SCRIPTS.'marker.php');
define('TZR_SHARE_SSL_PHP',TZR_SHARE_SCRIPTS.'ssl.php');

// URL de la feuille de style principale et personnalisations
if(!defined('TZR_DEFAULT_CSS')) define('TZR_DEFAULT_CSS', TZR_WWW_CSX.'src/Core/public/css/generic.css');
if(!defined('TZR_DEFAULT_CSS_PATH')) define('TZR_DEFAULT_CSS_PATH', TZR_WWW_CSX.'src/Core/public/css/');
if(!defined('TZR_DEFAULT_TEMPLATE')) {
  // template par defaut du BO
  if(defined('TZR_ADMINI') && TZR_ADMINI) define('TZR_DEFAULT_TEMPLATE', 'Core.layout/main.html');
  // template par defaut dans les autres cas
  else define('TZR_DEFAULT_TEMPLATE','index.html');
 }
if(!defined('TZR_USER_CSS_PATH') && file_exists(TZR_WWW_DIR.'console')) define('TZR_USER_CSS_PATH',TZR_WWW_DIR.'/console/');
if(!defined('TZR_USER_CSS') && file_exists(TZR_WWW_DIR.'console/styles.css')) define('TZR_USER_CSS','/console/styles.css');
if(!defined('TZR_USER_LOGINCSS') && file_exists(TZR_WWW_DIR.'console/styles-auth.css')) define('TZR_USER_LOGINCSS','/console/styles-auth.css');
if (!defined('TZR_CUSTOM_CONSOLE_LOGO') && file_exists(TZR_WWW_DIR.'console/logo-bo.svg')){
    define('TZR_CUSTOM_CONSOLE_LOGO', '/console/logo-bo.svg'); 
} else {
    if (file_exists(TZR_WWW_DIR.'console/logo-bo.png')){
        define('TZR_CUSTOM_CONSOLE_LOGO', '/console/logo-bo.png'); 
    }
}
if (!defined('TZR_CUSTOM_CONSOLE_LOGO_HOME') && file_exists(TZR_WWW_DIR.'console/logo-login.svg')){
    define('TZR_CUSTOM_CONSOLE_LOGO_HOME', '/console/logo-login.svg'); 
} else {
    if (file_exists(TZR_WWW_DIR.'console/logo-login.png')){
        define('TZR_CUSTOM_CONSOLE_LOGO_HOME', '/console/logo-login.png'); 
    }
}
if(!defined('TZR_JQUERYUI_CSS') && file_exists(TZR_WWW_DIR.'console/styles-ui.css')) define('TZR_JQUERYUI_CSS','/console/styles-ui.css');


// chemins contenant des polices de caracteres
putenv('GDFONTPATH=/usr/share/fonts/truetype/msfonts');
// classe css par défaut des images 
if (!defined('TZR_FO_IMG_CLASS')) define('TZR_FO_IMG_CLASS', 'img-responsive');
if (!defined('TZR_BO_IMG_CLASS')) define('TZR_BO_IMG_CLASS', 'tzr-image');
if(empty($TZR_SRCSETS)) $TZR_SRCSETS=include_once('tzrsrcsets.php');

// parametrage des logs
//
// Nom du fichier de log
if (!defined('TZR_LOG_DIR'))
  define('TZR_LOG_DIR',TZR_VAR2_DIR.'logs/');
define('TZR_UPGRADE_LOG_FILE',TZR_LOG_DIR.'upgrade.log');
if(!empty($_SERVER['SERVER_NAME'])) {
  define('TZR_SERVER_NAME',$_SERVER['SERVER_NAME']);
  define('TZR_LOG_FILE',TZR_LOG_DIR.'console.'.$_SERVER['SERVER_NAME'].'.log');
  define('TZR_ACCESS_LOG_FILE',TZR_WWW_DIR.'../logs/tzr_log');
} else {
  define('TZR_SERVER_NAME',$HOME);
  if (strpos($_SERVER['PHP_SELF'], 'fastdaemon.php')) {
    define('TZR_LOG_FILE',TZR_LOG_DIR.'fastdaemon.log');
  } else {
    define('TZR_LOG_FILE',TZR_LOG_DIR.'scheduler.log');
  }
  define('TZR_ACCESS_LOG_FILE', TZR_LOG_FILE);
}
if(!defined('TZR_ARCHIVES_DIR')) define('TZR_ARCHIVES_DIR',TZR_VAR2_DIR.'archives/');
// definition de l'endoit où on envoie les archives au dela de x jours (60 par défaut)
if(!defined('TZR_ARCHIVES_LOCKER')) define('TZR_ARCHIVES_LOCKER', ["login"=>"","url"=>"","passwd"=>""]);
if(!defined('TZR_ARCHIVES_RETENTION_DAYS')) define('TZR_ARCHIVES_RETENTION_DAYS',60);

if(!defined('CACHE_DIR')) define('CACHE_DIR',TZR_VAR2_DIR.'cache/'.TZR_SERVER_NAME.'/');
if(!defined('CACHE_BASE_DIR')) define('CACHE_BASE_DIR',TZR_VAR2_DIR.'cache/');
if(!defined('TZR_LOG_LEVEL')) define('TZR_LOG_LEVEL', 'Zend\Log\Logger::CRIT');
// constantes pour compatibilité avec ancien package de gestion de logs
if(!defined('PEAR_LOG_DEBUG')) define('PEAR_LOG_DEBUG', 'Zend\Log\Logger::DEBUG');
if(!defined('PEAR_LOG_CRIT')) define('PEAR_LOG_CRIT', 'Zend\Log\Logger::CRIT');
if(!defined('TZR_LOG_ROTATE')) define('TZR_LOG_ROTATE', 7);

// chemin vers la doc
if (!defined('TZR_USERGUIDE_URL')) define('TZR_USERGUIDE_URL', 'https://publicdocs.seolan.com/');

// service de test spf, dkim et dmark des adresses mail
if (!defined('TZR_FROM_URL')) define('TZR_FROM_URL', 'https://foo.com/');

// répertoire des templates
if(empty($TEMPLATES_DIR)) {
  $TEMPLATES_DIR = $LIBTHEZORRO.'src/';
  $TEMPLATES_URL = TZR_WWW_CSX.'templates/';
}
define('TEMPLATES_DIR',$TEMPLATES_DIR);

// liste des templates qui peuvent etre accedes sans authentification dans la console
if(empty($TZR_PUBLIC_TEMPLATES)) $TZR_PUBLIC_TEMPLATES=array();
$TZR_PUBLIC_TEMPLATES[]='Core.layout/auth.html';
$TZR_PUBLIC_TEMPLATES[]='Core.layout/main.html';
$TZR_PUBLIC_TEMPLATES[]='Core.message.html';
$TZR_PUBLIC_TEMPLATES[]='Core.empty.html';
$TZR_PUBLIC_TEMPLATES[]='Core.empty.txt';
$TZR_PUBLIC_TEMPLATES[]='Core.layout/raw.html';
$TZR_PUBLIC_TEMPLATES[]='Core.menu/bookmark.html';
$TZR_PUBLIC_TEMPLATES[]='Core.menu/module.html';
$TZR_PUBLIC_TEMPLATES[]='Application/MiniSite/public/templates/index.html';
$TZR_PUBLIC_TEMPLATES[]='Module/MailingList.unsubscribe.html';
$TZR_PUBLIC_TEMPLATES[]='Module/MailingList.newsletter.html';
$TZR_PUBLIC_TEMPLATES[]='Module/Calendar.displayMonth.html';
$TZR_PUBLIC_TEMPLATES[]='Module/Cart.inmail.html';
$TZR_PUBLIC_TEMPLATES[]='Module/Ssl.auth.html';
$TZR_PUBLIC_TEMPLATES[]='Module/Social.FBConnect.html';
$TZR_PUBLIC_TEMPLATES[]='Module/Social.getFBToken.html';
$TZR_PUBLIC_TEMPLATES[]='Module/Social.getTwitterToken.html';
$TZR_PUBLIC_TEMPLATES[]='Module/Form.viewForm.html';
$TZR_PUBLIC_TEMPLATES[]='Module/Media.viewMedia.html';
$TZR_PUBLIC_TEMPLATES[]='Module/User.requestAnAccount.html';
$TZR_PUBLIC_TEMPLATES[]='empty.txt';
$TZR_PUBLIC_TEMPLATES[]='message1.html';
// pour tests RR MA ? 
$TZR_PUBLIC_TEMPLATES[]='Field/HyperVideo.popup.html';

// repertoire de cache de smarty
if(empty($TEMPLATES_CACHE)){
  $TEMPLATES_CACHE = $TZR_WWW_DIR.'templates_c/'.md5($TEMPLATES_DIR).'/';
}

// gestion de la securité sur les répertoires
if(empty($TZR_SECURE)) $TZR_SECURE=[];

// repertoire contenant les fichiers de données
if(empty($DATA_DIR)) $DATA_DIR = $TZR_WWW_DIR.'data/';
// url contenant les fichiers de données en relatifa  la racine du site
if(empty($DATA_URL)) $DATA_URL = 'data/';
if(empty($HOME_URL)) $HOME_URL = '/';
if(empty($SELF_PREFIX)) $SELF_PREFIX = '/';
// classe de démarrage de l'application
if(empty($START_CLASS)) $START_CLASS = '\Seolan\Core\Shell';
if(empty($ADMIN_START_CLASS)) $ADMIN_START_CLASS = '\Seolan\Core\Shell';
if(empty($JSON_START_CLASS)) $JSON_START_CLASS = '\Seolan\Core\Json';

if(defined('TZR_LOGIN_MANAGER')) $TZR_SESSION_MANAGER=TZR_LOGIN_MANAGER;
if(empty($TZR_SESSION_MANAGER)) $TZR_SESSION_MANAGER='\Seolan\Core\Session';
define('TZR_SESSION_MANAGER',$TZR_SESSION_MANAGER);
if(defined('TZR_SEARCH_MANAGER')) $TZR_SEARCH_MANAGER=TZR_SEARCH_MANAGER;
if (!isset($TZR_SEARCH_MANAGER)){
  $TZR_SEARCH_MANAGER = '\Seolan\Library\SolR\Search';
  define('TZR_SEARCH_MANAGER', $TZR_SEARCH_MANAGER);
}
if(defined('TZR_SEARCH_MANAGER2')) $TZR_SEARCH_MANAGER2=TZR_SEARCH_MANAGER2;
if (!isset($TZR_SEARCH_MANAGER2)){
  $TZR_SEARCH_MANAGER2 = '\Seolan\Library\SolR\SearchV2';
  define('TZR_SEARCH_MANAGER2', $TZR_SEARCH_MANAGER2);
}
if (!defined('TZR_INDEXABLE_FILE_MAXSIZE'))
  define('TZR_INDEXABLE_FILE_MAXSIZE', '100M');
if(!defined('CONFIG_INI'))
  define('CONFIG_INI',$LOCALLIBTHEZORRO.'local.ini');

// nom et chemin du script, en relatif a la racine (URL)
if(empty($TZR_SELF)) $TZR_SELF=$_SERVER['SCRIPT_NAME'];

if(!defined('TZR_SESSION_COOKIE_PARAM')){
  define('TZR_SESSION_COOKIE_PARAM','/');
}
define('TZR_FO_SESSION_NAME','PHPSESSID');
define('TZR_BO_SESSION_NAME','BOPHPSESSID');
if(!defined('TZR_ALLOW_FO_SESSION')) define('TZR_ALLOW_FO_SESSION',1);
if(!defined('TZR_SESSION_NAME')){
  if(TZR_ALLOW_FO_SESSION){
    if(TZR_ADMINI) define('TZR_SESSION_NAME',TZR_BO_SESSION_NAME);
    else define('TZR_SESSION_NAME',TZR_FO_SESSION_NAME);
  }else{
    define('TZR_SESSION_NAME',TZR_FO_SESSION_NAME);
  }
}

$TZR_SELF=str_replace('scheduler.php','index.php',$TZR_SELF);

// CHEMIN DES SCRIPTS MUTUALISES DE TRAITEMENTS DIVERS
//
if(!defined('TZR_DOWNLOADER')) define('TZR_DOWNLOADER',TZR_SHARE_SCRIPTS.'downloader2.php');
if(!defined('TZR_DOWNLOADER_PUBLIC')) define('TZR_DOWNLOADER_PUBLIC',TZR_SHARE_SCRIPTS.'downloader-public.php');
if(!defined('TZR_DOWNLOADER_TMP')) define('TZR_DOWNLOADER_TMP',TZR_SHARE_SCRIPTS.'downloader-tmp.php');
if(!defined('TZR_FILE_EDITOR_UPLOADER')) define('TZR_FILE_EDITOR_UPLOADER',TZR_SHARE_SCRIPTS.'fileeditoruploader.php');
if(!defined('TZR_RESIZER')) define('TZR_RESIZER',TZR_SHARE_SCRIPTS.'resizer.php');
if(!isset($TZR_RESIZER_DEFAULT_OPTIONS)) {
  $TZR_RESIZER_DEFAULT_OPTIONS['-strip']='';
  $TZR_RESIZER_DEFAULT_OPTIONS['-interlace']='line';
  $TZR_RESIZER_DEFAULT_OPTIONS['-quality']='75';
}
if(!defined('TZR_WERESIZER')) define('TZR_WERESIZER',TZR_SHARE_SCRIPTS.'weresizer.php');
if(!defined('TZR_VIDEOCONVERT')) define('TZR_VIDEOCONVERT',TZR_SHARE_SCRIPTS.'video-convert.php');
if(!defined('TZR_AUDIOCONVERT')) define('TZR_AUDIOCONVERT',TZR_SHARE_SCRIPTS.'audio-convert.php');

// DEFINITION DE CHEMINS POUR LES COMMANDES EXTERNES
//
if(TZR_TARGET_SYS=='LRH') {
  define('TZR_MOGRIFY_RESIZER','/usr/bin/convert');
  define('TZR_UPTIME_PATH', '/proc/loadavg');
  define('TZR_RDDTOOL_PATH','/usr/bin/rrdtool');

  // nouvelle configuration serveurs à partir de web01 inclus
  if(!defined('TZR_PRINCE2_PATH')) define('TZR_PRINCE2_PATH','/usr/bin/prince');
  if(!defined('TZR_PRINCE2_LIB')) define('TZR_PRINCE2_LIB','/usr/lib/prince11/');
  define('TZR_PASSWORD_DICTIONARY',$LIBTHEZORRO.'Vendor/password_generator/words');
  if(!defined('TZR_PASSWORD_NBLETTERS')) define('TZR_PASSWORD_NBLETTERS',12);
  if(!defined('TZR_PASSWORD_NBDIGITS')) define('TZR_PASSWORD_NBDIGITS',2);
  umask(0000);
}
if(!defined('TZR_MAX_LOGIN_ATTEMPTS')) define('TZR_MAX_LOGIN_ATTEMPTS',4);
if(!defined('TZR_LOGIN_BANISH_TIME')) define('TZR_LOGIN_BANISH_TIME',30);
if(!defined('TZR_LOGIN_BANISH_MAXTIME')) define('TZR_LOGIN_BANISH_MAXTIME',120);
if (!defined('FFMPEG'))
  define('FFMPEG', '/usr/bin/ffmpeg');
if (!defined('FFMPEG_WEBM_OPTS'))
  define('FFMPEG_WEBM_OPTS', '-ab 64k -f webm');
if (!defined('FFMPEG_OGG_OPTS'))
  define('FFMPEG_OGG_OPTS', '-acodec libvorbis -ab 64k -f ogg');
if (!defined('FFMPEG_MP4_OPTS'))
  define('FFMPEG_MP4_OPTS', '-ab 64k -f mp4 -vcodec libx264 -acodec libfdk_aac -preset medium -f mp4');
// chemin de la commande curl qui permet d'automatiser des consultations web
if(!defined('TZR_CURL_PATH')) define('TZR_CURL_PATH','/usr/bin/curl ');
// chemin de la commande de dump de la base de données
if(!defined('TZR_MYSQLDUMP_PATH')) define('TZR_MYSQLDUMP_PATH','/usr/bin/mysqldump');
if(!defined('TZR_VIZGRAPH_DOT_PATH')) define('TZR_VIZGRAPH_DOT_PATH','/usr/bin/dot');
if(!defined('TZR_VIZGRAPH_NEATO_PATH')) define('TZR_VIZGRAPH_NEATO_PATH','/usr/bin/neato');

// Chemin du dossier dans lequel se trouvent le spool de OpenOffice
if(!defined('TZR_OPENOFFICE_SPOOL_DIR')) define('TZR_OPENOFFICE_SPOOL_DIR','/var/spool/office-converter/');
define('TZR_CHARTS_LICENSE','E1XHF7MEW9L.HSK5T4Q79KLYCK07EK');

// Chemin du convertiseur de documents
if(!defined('TZR_VIEWER_CONVERTER')) define('TZR_VIEWER_CONVERTER', '/usr/bin/pyodconverter3.py');
if(!defined('TZR_VIEWER_CONVERTER_PORT')) define('TZR_VIEWER_CONVERTER_PORT', 8102);
if(!defined('TZR_VIEWER_SPOOL_BIN')) define('TZR_VIEWER_SPOOL_BIN','office-converter');
if(!defined('TZR_VIEWER_SPOOL_DIR')) define('TZR_VIEWER_SPOOL_DIR','/var/spool/office-converter/');
if(!defined('TZR_VIEWER_TMP_DIR')) define('TZR_VIEWER_TMP_DIR',TZR_TMP_DIR.'/viewer/');


// PARAMETRES DIVERS
//
if(!defined('TZR_ALIAS_MINLEN')) define('TZR_ALIAS_MINLEN', 3);
if(!defined('TZR_SUPPORT_ADDRESS')) define('TZR_SUPPORT_ADDRESS', 'support-tzr@foo.com');
if(!defined('TZR_ARCHIVES_ADDRESS')) define('TZR_ARCHIVE_ADDRESS', 'archive-tzr@foo.com');
if(!defined('TZR_SENDER_ADDRESS')) define('TZR_SENDER_ADDRESS', 'console@foo.com');
if(!defined('TZR_XMODMAILLIST_RETURN_ADDRESS')) define('TZR_XMODMAILLIST_RETURN_ADDRESS','ml-return+xx@foo.com');
if(!defined('TZR_RETURN_ADDRESS')) define('TZR_RETURN_ADDRESS',TZR_XMODMAILLIST_RETURN_ADDRESS);
if(!defined('TZR_BOUNCE_NB_CHECK')) define('TZR_BOUNCE_NB_CHECK',3);
if(!defined('TZR_FAX_SENDER')) define('TZR_FAX_SENDER', 'faxsender@foo.com');
if(!defined('TZR_SMS_SENDER')) define('TZR_SMS_SENDER', 'faxsender@foo.com');
if(!defined('UPDATE_USED_VALUES')) define('UPDATE_USED_VALUES', false);
if(!defined('USED_VALUES_STORAGE_TIME')) define('USED_VALUES_STORAGE_TIME', '7 days');
if(!defined('TZR_ALLPACKS')) define('TZR_ALLPACKS', 0);
// longueur max d'un message sms
define('TZR_SMS_MAXLENGTH',155);
if (!defined('TZR_NOREPLY_ADDRESS')) define('TZR_NOREPLY_ADDRESS', 'noreply@foo.com');
if(!defined('TZR_DEBUG_ADDRESS')) define('TZR_DEBUG_ADDRESS', 'console@foo.com');
define('TZR_MAX_LOAD',8);	/* devrait être égal au nombre de processeurs */
define('TZR_XMODSCHEDULER_RUNNINGPENDING',3);
define('TZR_XMODSCHEDULER_TASKDURATION',5);
// delai pendant lequel cette page peut etre considérée comme valide dans le cache navigateur
if (!defined('TZR_PAGE_EXPIRES'))
  define('TZR_PAGE_EXPIRES',86400); 
if (!defined('TZR_SESSION_DURATION'))
  define('TZR_SESSION_DURATION', 3600);
// limite des exports browse au format excel
if (!defined('TZR_MAX_EXPORT_XLS'))
  define('TZR_MAX_EXPORT_XLS', 10000);
if (!defined('TZR_MAX_EXECUTION_TIME'))
  define('TZR_MAX_EXECUTION_TIME', 0);
// limite des imports 'inline' en Mo
if (!defined('TZR_MAX_IMPORT_SIZE'))
  define('TZR_MAX_IMPORT_SIZE', 10);
// DIVERSES CONSTANTES UTILISEES DANS L'APPLICATION
//
define('TZR_DEFAULT_TARGET', '%');
define('DATA_GENDER', 'Data');
define('OID_GENDER', 'Oid');
define('TZR_DATE_EMPTY','0000-00-00');
define('TZR_UNCHANGED','TZR_unchanged');
define('TZR_DATETIME_EMPTY','0000-00-00 00:00:00');
define('COMPULSORY_ITEM','<b>></b>');
define('OPTIONAL_ITEM','&nbsp;');
if(!defined('TZR_THUMB_SIZE')) define('TZR_THUMB_SIZE', 200);
if(!defined('TZR_MEDIA_THUMB_SIZE')) define('TZR_MEDIA_THUMB_SIZE',TZR_THUMB_SIZE);
define('TZR_RETURN_DATA','*return*');
define('TZR_LANG_FREELANG',3);
define('TZR_LANG_BASEDLANG',1);
define('TZR_LANG_ONELANG',0);

define('TZR_CONSOLE_RELEASE','X3');
define('TZR_CONSOLE_RELEASE_NICKNAME','Oligocène');
define('TZR_CONSOLE_SUB_RELEASE','20220905');
				     
if(!defined('TZR_SENDACOPY_MAXSIZE')) define('TZR_SENDACOPY_MAXSIZE',10000000);
// version minimale de PHP requise
if(!defined('TZR_PHP_RELEASE')) define('TZR_PHP_RELEASE','7.4');
// nom du CLI pour vérification ou génération dans les crontab
if(!defined('PHP_SEOLAN') || !PHP_SEOLAN) define('PHP_SEOLAN', 'php-seolan10-7.4');
// versions minimales d'upgrades pour les modules qui le nécessitent
if(!defined('TZR_UPGRADE_RELEASE')) define('TZR_UPGRADE_RELEASE',['\Seolan\Module\Scheduler\Scheduler', '20200205']);
if(!defined('TZR_LOG_DAYS')) define('TZR_LOG_DAYS',30);
if(!defined('TZR_LOG_MAXSIZE')) define('TZR_LOG_MAXSIZE',1000000);

define('TZR_STATUS','testing');

if(!defined('TZR_MODTABLE_SUBMOD_MAX')) define('TZR_MODTABLE_SUBMOD_MAX',1);
define('TZR_XMODTABLE_BROWSE_PAGESIZE',   40);
define('TZR_XMODTABLE_BROWSE_MAXPAGESIZE',1000);
// Mode de retour par défaut des données d'une liste de fiche (both|object|lines)
if(!defined('TZR_BROWSE_DEFAULT_MODE')) define('TZR_BROWSE_DEFAULT_MODE', 'lines');
if(!defined('TZR_DEFAULT_OUTPUT')) define('TZR_DEFAULT_OUTPUT','HTML 4.01 Transitional');
define('TZR_BACK_STACK_SIZE',20);
if(!defined('TZR_SELF_USER')) define('TZR_SELF_USER','USERS:self');
if(!defined('TZR_EMAIL_REGEXP')) define('TZR_EMAIL_REGEXP', "^[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$");

// PARAMETRAGE DES UTILISATEURS CONVENTIONNELS
//
define('TZR_USERID_NOBODY','USERS:0');
define('TZR_USERID_ROOT','USERS:1');
define('TZR_GROUPID_NOBODY','GRP:*');
define('TZR_GROUPID_BACKOFFICE','GRP:A'); /* utilisateurs autorises a se connecter dans le backoffice */
define('TZR_GROUPID_ROOT','GRP:1');
define('TZR_GROUPID_AUTH','GRP:2');	// groupe des utilisateurs authentifies
define('TZR_GROUPID_REMOTEUSE','GRP:REMOTEUSE'); // groupe des comptes utilisables à distance
if(!defined('TZR_ROOTAUTH_USEIMAP')) define('TZR_ROOTAUTH_USEIMAP',false);
if(!defined('TZR_ROOTAUTH_ALIAS')) define('TZR_ROOTAUTH_ALIAS','root');
if(!defined('TZR_ROOTAUTH_IMAP')) define('TZR_ROOTAUTH_IMAP','imap.foo.com');
if(!defined('TZR_ROOTAUTH_IMAPUSER')) define('TZR_ROOTAUTH_IMAPUSER','console-root-urgency@foo.com');

// PARAMETRAGE DES TRAITEMENTS DES LANGUES
if (!defined('TZR_DEFAULT_LOCALE_PROPERTY_CODE')){
   define('TZR_DEFAULT_LOCALE_PROPERTY_CODE', 'GB');
}
if(empty($TZR_AUTH)) $TZR_AUTH=array('none','ro','rw','rwv','admin');
// liste des langues traitees
if(empty($TZR_LANGUAGES)) $TZR_LANGUAGES=array('FR'=>'fr');
if(empty($TZR_ADMIN_LANGUAGES)) $TZR_ADMIN_LANGUAGES=$TZR_LANGUAGES;
// recherche de la langue par défaut
if(!defined('TZR_DEFAULT_LANG')) {
  define('TZR_DEFAULT_LANG', array_keys($TZR_LANGUAGES)[0]);
}
if (!defined('CURRENCY'))
  define('CURRENCY', "&euro;");

ini_set('include_path',$LIBTHEZORRO.':'.$LOCALLIBTHEZORRO.':'.ini_get('include_path'));
    // TODO not adding "library" to TZR_ZEND_PATH would allow using the versions packaged in Debian squeeze & Ubuntu precise

// CONFIGURATION DU REWRITING
if(!defined('TZR_REWRITING_PREFIX')) define('TZR_REWRITING_PREFIX','~');
if(!isset($TZR_REWRITING_CASESENSITIVE)) {
  $TZR_REWRITING_CASESENSITIVE=true;
}
if(empty($TZR_REWRITING)) {
  $TZR_REWRITING=array();
}
// CONFIGURATION DE REFERENCEMENT
if(empty($TZR_REF_PREFIX)){
  $TZR_REF_PREFIX='';
}
// CONFIGURATION EN FONCTION DES MODULES COMPILES
// 
// GESTION DU CACHE SESSION
if(empty($SESSION_CACHE_LIMITER)) $SESSION_CACHE_LIMITER='private, must-revalidate';
if(empty($SESSION_LIFETIME)) $SESSION_LIFETIME=0;
if(empty($SESSION_LIFETIME_REMEMBER_ME)) $SESSION_LIFETIME_REMEMBER_ME=604800;

// GESTION DES TYPES MIME
//
if(!defined('TZR_MIME_FILE')) define('TZR_MIME_FILE','/etc/mime.types');
if(!defined('TZR_FILE_CMD')) define('TZR_FILE_CMD','/usr/bin/file');
if(!defined('TZR_LD_IMAGE_SIZE')) define('TZR_LD_IMAGE_SIZE', '1000x1000');

// GESTION DES CHARSETS
//
if(!defined('TZR_ADMINI_CHARSET')) define('TZR_ADMINI_CHARSET','UTF-8');
if(!defined('TZR_INTERNAL_CHARSET')) define('TZR_INTERNAL_CHARSET','UTF-8');

// CAPTCHA CONFIG
if(!defined('TZR_CAPTCHA_LENGTH')) define('TZR_CAPTCHA_LENGTH', 6); 
if(!defined('TZR_CAPTCHA_WIDTH')) define('TZR_CAPTCHA_WIDTH', '120');
if(!defined('TZR_CAPTCHA_HEIGHT')) define('TZR_CAPTCHA_HEIGHT', '60');
if(!defined('TZR_CAPTCHA_CREDITS')) define('TZR_CAPTCHA_CREDITS', '');
if(!defined('TZR_CAPTCHA_FORGROUND_COLOR')) define('TZR_CAPTCHA_FORGROUND_COLOR', '0,0,0');
if(!defined('TZR_CAPTCHA_BACKGROUND_COLOR')) define('TZR_CAPTCHA_BACKGROUND_COLOR', '255,255,255');
if(!defined('TZR_CAPTCHA_ALLOWED_SYMBOLS')) define('TZR_CAPTCHA_ALLOWED_SYMBOLS', '23456789abcdeghkmnpqsuvxyz');
if(!defined('TZR_CAPTCHA_NO_SPACES')) define('TZR_CAPTCHA_NO_SPACES', true);
if(!defined('TZR_CAPTCHA_FLUCTUATION_AMPLITUDE')) define('TZR_CAPTCHA_FLUCTUATION_AMPLITUDE', 5);
if(!defined('TZR_CAPTCHA')) define('TZR_CAPTCHA',TZR_SHARE_SCRIPTS.'captcha.php');

// Configuration du cache memoire
// definition du nombre maximum de references dans le cache.
if(!defined('TZR_MEMCACHE_MAXSIZE'))  define('TZR_MEMCACHE_MAXSIZE', 5000);
// Expiration par défaut des données stockées dans memcached
if(!defined('TZR_MEMCACHED_DEFAULT_EXPIRATION'))  define('TZR_MEMCACHED_DEFAULT_EXPIRATION', 1200);

// XSearch nb de resultats par module
if (!defined('TZR_XSEARCH_MAXRESULTS'))
  define('TZR_XSEARCH_MAXRESULTS', 500);

if (!defined('TZR_SESSION_PREFIX'))
  define('TZR_SESSION_PREFIX', '');
if (!defined('HISTORY_SESSION_VAR'))
  define('HISTORY_SESSION_VAR', TZR_SESSION_PREFIX.'history');

if (!defined('TZR_TIMEZONE'))
  define('TZR_TIMEZONE', 'Europe/Paris');
// IPs and USERS id pour la lecture de configuration (private(web03), serveurs nagios)
if (!defined('TZR_METACONF_IP'))
  define('TZR_METACONF_IP', '81.200.40.200');
date_default_timezone_set(TZR_TIMEZONE);
define('TZR_START_TIME', microtime(true));

if (!defined('TZR_CUSTOM_LOGO_OVERLAY'))
  define('TZR_CUSTOM_LOGO_OVERLAY', 'TZR_CUSTOM_LOGO_OVERLAY');

define('TZR_AUTHTOKEN_NAME', 'AUTHTOKEN');

// ModApp
if (!defined('TZR_USE_APP'))
  define('TZR_USE_APP', 0);
if (TZR_USE_APP) {
  if (!isset($LOCAL_APPS)) {
    $LOCAL_APPS = array();
  }
  // Liste des "App" disponibles.
  $LOCAL_APPS['\Seolan\Application\Site\Site'] = 'Sous-site';
}
// Config pour les sous-sites
if(!defined('SUB_SITE_ENABLED')) define('SUB_SITE_ENABLED', False);
if(!defined('SUB_SITE_APP_CLASS')) define('SUB_SITE_APP_CLASS', '\Seolan\Application\Corail\Corail');

// ModSecurity
if (!defined('TZR_USE_SECS'))
  define('TZR_USE_SECS', 0);

// table _REWRITE
if (!defined('TZR_USE_REWRITE'))
  define('TZR_USE_REWRITE', 0);

//table des commentaires sur les objects
if(!defined('TZR_TABLE_COMMENT_NAME'))
    define('TZR_TABLE_COMMENT_NAME','_COMMENTS2');

if(!defined('TZR_IMPORT_SEPARATOR'))
    define('TZR_IMPORT_SEPARATOR', array('|', ','));

// Cache par groupe d'utilisateur
if(!defined('TZR_USE_GROUP_CACHE'))
  define('TZR_USE_GROUP_CACHE', false);

// OBLIGATOIRE : IP du serveur hébergeant le site devant être validé par service-public.fr
if(!defined('TZR_COMARQUAGE_IP'))
  define('TZR_COMARQUAGE_IP', '81.200.33.59');

// OPTIONNEL : Répertoire où seront stockés les XML téléchargées depuis service-public.fr et les fichiers HTML compilés
if(!defined('TZR_COMARQUAGE_CACHE_DIR'))
  define('TZR_COMARQUAGE_CACHE_DIR', TZR_TMP_DIR.'/comarquage/');

// OPTIONNEL : Répertoire où seront stockés les XML personnalisés et le dossier des XSL
if(!defined('TZR_COMARQUAGE_LIBRARY'))
  define('TZR_COMARQUAGE_LIBRARY', $LIBTHEZORRO.'src/Library/Comarquage/');

// URL où réccupérer les fichiers XML du flux services publiques v3. $CATEGORIE est remplacé par 'part', 'pro' ou 'asso'
if(!defined('TZR_COMARQUAGE_URL'))
  define('TZR_COMARQUAGE_URL', 'https://lecomarquage.service-public.fr/vdd/3.0/$CATEGORIE/zip/vosdroits-latest.zip');

// URL ajax du service comarquage de la console
if(!defined('TZR_COMARQUAGE_AJAX_URL'))
  define('TZR_COMARQUAGE_AJAX_URL', TZR_WWW_CSX.'public/modsp/modsp.php');

// Messagerie: champ de la talble utilisateur utilisé comme alias
if(!defined('TZR_CHAT_USER_FIELD'))
  define('TZR_CHAT_USER_FIELD', 'alias');

// APIDAE
if(!defined('TZR_APIDAE_AUTH_URL'))
  define('TZR_APIDAE_AUTH_URL', 'http://base.apidae-tourisme-recette.accelance.net/oauth/token?grant_type=client_credentials');

if(!defined('TZR_APIDAE_WRITE_URL'))
  define('TZR_APIDAE_WRITE_URL', 'http://api.apidae-tourisme-recette.accelance.net/api/v002/ecriture/');

if(!defined('TZR_APIDAE_AUTH_USERID'))
  define('TZR_APIDAE_AUTH_USERID', NULL);

if(!defined('TZR_APIDAE_AUTH_PASSWD'))
  define('TZR_APIDAE_AUTH_PASSWD', NULL);

if(!defined('TZR_APIDAE_SKIP_VALIDATION'))
  define('TZR_APIDAE_SKIP_VALIDATION', false);

// fastdaemon, liste de paramètres de Module::objectFactory sur lesquels appeler _fastDaemon()
// toid as string
$FAST_DAEMONS = array_merge($FAST_DAEMONS ?? [], [
  ['toid' => 'XMODWAITINGROOM_TOID'],
]);

if(!defined('TZR_USER_AGENT_MOBILE_APP')) {
  define('TZR_USER_AGENT_MOBILE_APP', 'SEOLAN_MOBILE_APP');
}

if(!defined('TZR_CHECKBOX_CHECKALL_LIMIT'))
  define('TZR_CHECKBOX_CHECKALL_LIMIT', 7);


// Constante API de DeepL
if(!defined('TZR_DEEPL_WEBSERVICE_URL'))
  define('TZR_DEEPL_WEBSERVICE_URL', 'https://api.deepl.com/v2/translate');
if(!defined('TZR_DEEPL_WEBSERVICE_KEY'))
  define('TZR_DEEPL_WEBSERVICE_KEY', '');

//email
if(!defined('TZR_SMTP_SECURE'))
  define('TZR_SMTP_SECURE', 'ssl');

// RocketChat constants
if(!defined('ADMIN_ROCKETCHAT_ID'))
    define('ADMIN_ROCKETCHAT_ID', "H5MkSSqdRxr4afhwL");

if(!defined('ADMIN_ROCKETCHAT_TOKEN'))
    define('ADMIN_ROCKETCHAT_TOKEN', "lRChfceeQZYjuYhbD-mecKRJxjj0bNFVFFjkOtihdi4");

if(!defined('DEFAULT_ROCKETCHAT_PWD'))
    define('DEFAULT_ROCKETCHAT_PWD', "ezKa!choSûr");

if(!defined('ROCKETCHAT_SERVEUR_URL'))
    define('ROCKETCHAT_SERVEUR_URL', "https://rocketchat.foo.com");

if(!defined('ROCKETCHAT_API'))
    define('ROCKETCHAT_API', ROCKETCHAT_SERVEUR_URL."/api/v1/");

