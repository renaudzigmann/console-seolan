<?php
namespace Seolan\Module\MiniSite;
class Wizard extends \Seolan\Core\Module\Wizard {

  function __construct($ar=null) {
    parent::__construct($ar);
    $this->_module->group = \Seolan\Core\Labels::getSysLabel('Seolan_Module_MiniSite_MiniSite', 'modulename');
    $this->_module->modulename = \Seolan\Core\Labels::getSysLabel('Seolan_Module_MiniSite_MiniSite', 'modulename');
  }

  function istep1($ar=null) {
    parent::istep1($ar);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'modulename'),
                            'modulename', 'text');
    $this->_options->setRO('modulename');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','group'),
                            'group', 'text');
    preg_match('/^(.*)\.([^\.]*\.[^\.]*)$/', $_SERVER['SERVER_NAME'], $matches);
    $this->_module->domain = $matches[2];
    $this->_options->setOpt('Database prefix', 'database_prefix', 'text');
    $this->_options->setOpt('Domaine par défaut', 'domain', 'text');
    $this->_options->setOpt('Gérer Htdig', 'htdig', 'boolean');
    $this->_options->setOpt('Htdig template', 'htdig_tpl', 'text');
    $this->_options->setOpt('Htdig prefix', 'htdig_prefix', 'text');
  }

  function istep2($ar=null) {
    if (!$this->_module->database_prefix) {
      $message = 'Database préfix incorrect';
      \Seolan\Core\Shell::toScreen2('wd', 'message', $message);
      $this->_step = 1;
      return $this->irun($ar);
    }
    $dbmodele = $this->getDB();
    if (!$dbmodele) {
      $message = 'Impossible de trouver des bases de données avec ce prefix.';
      \Seolan\Core\Shell::toScreen2('wd', 'message', $message);
      $this->_step = 1;
      return $this->irun($ar);
    }
    if ($dbmodele == $GLOBALS['DATABASE_NAME']) {
      $message = 'Danger, le database préfix match la base de données de ce site.';
      \Seolan\Core\Shell::toScreen2('wd', 'message', $message);
      $this->_step = 1;
      return $this->irun($ar);
    }
    $message = "Bases de données disponibles ($dbmodele ...)";
    \Seolan\Core\Shell::toScreen2('wd', 'message', $message);
  }

  function iend($ar=null) {
    $this->_module->table='VHOSTS';
    $dbmodele = $this->createSqlStructure();
    $this->createFileStructure();
    $moid = parent::iend($ar);
    $this->createModules();
    // ajout groupe gestionnaire
    getDB()->execute("insert IGNORE into GRP (KOID,LANG,UPD,GRP,DESCR) values('GRP:MINISITE','FR',now(),'Gestionnaire minisites', 'Groupe des personnes disposant des droits de gestion des minisites')");
    // ajout des droits sur le module pour ce groupe
    $aoid = substr(md5(uniqid()), 0, 40);
    getDB()->execute("insert IGNORE into ACL4 (AOID, UPD, AGRP, AFUNCTION, ALANG, AMOID, OK) values ('$aoid', NULL, 'GRP:MINISITE', 'rwv', 'FR', '$moid', 1)");

    $home = substr($_SERVER['DOCUMENT_ROOT'], 0, -4);
    $domain = str_replace('.', '\.', $this->_module->domain);
    $ip = str_replace('.', '\.', $_SERVER['SERVER_ADDR']);
    $message =<<<EOT
    Un modèle a été créé, utilisez<br>
    <pre>
    base: $dbmodele,
    répertoire web: {$home}www/minisites/modele.{$this->_module->domain}
    ini_file: {$home}tzr/minisites/modele.{$this->_module->domain}/local.ini (copie du local.ini)
    </pre>
    pour l'installer<br>
    Ajoutez lans le fichier local.php :<br>
    <pre>
    // minisites
    &#36;HAS_VHOSTS = true;
    &#36;HOME_DIR = '{$home}';
    define('MS_WWW_DIR', &#36;HOME_DIR . 'www/minisites/');
    define('MS_VAR_DIR', &#36;HOME_DIR . 'var/minisites/');
    define('MS_TZR_DIR', &#36;HOME_DIR . 'tzr/minisites/');
    </pre>
    Ajoutez dans .htaccess ou dans la config du virtual host
    <pre>
      #
      DirectoryIndex index.php  index.html
      RewriteEngine On
      RewriteCond   %{HTTP_HOST}                 !^(www\.)*{$domain}$
      RewriteCond   %{HTTP_HOST}                 !^{$ip}$
      RewriteCond   %{REQUEST_URI}               !/scripts-admin/
      RewriteCond   %{REQUEST_URI}               !/scripts/
      RewriteCond   %{REQUEST_URI}               !/index
      RewriteCond   %{REQUEST_URI}               !\.html
      RewriteCond   %{REQUEST_URI}               !\.xml
      RewriteCond   %{REQUEST_URI}               !~
      RewriteCond   %{REQUEST_URI}               !^/minisites
      RewriteRule   ^(.+)                        /minisites/%{HTTP_HOST}/&#36;1
    </pre>
    moid: $moid
EOT;
    \Seolan\Core\Shell::toScreen2('wd', 'message', $message);
    return $moid;
  }

  // création de la table sql
  private function createSqlStructure() {
    if(\Seolan\Core\System::tableExists('VHOSTS')) {
      return;
    }
    $lg = TZR_DEFAULT_LANG;
    $ar['translatable'] = 0;
    $ar['auto_translate'] = 0;
    $ar['own'] = 0;
    $ar['btab'] = 'VHOSTS';
    $ar['bname'][$lg] = 'Minisites - Mini Sites';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar);
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=VHOSTS');
    //               count ord obl que bro tra mul pub tar
    $ds->createField('CREAD','Date de création','\Seolan\Field\Date\Date',
                    '','1','1','0','0','0','0','0');
    $ds->createField('name','Nom du site','\Seolan\Field\ShortText\ShortText',
                    '60','2','1','1','1','0','0','1');
    $ds->createField('vhost',"Nom d'hôte",'\Seolan\Field\ShortText\ShortText',
                    '60','3','1','1','1','0','0','0');
    $ds->createField('aliases',"Liste d'alias (fqdn)",'\Seolan\Field\Text\Text',
                    '60','4','0','1','1','0','0','0');
    $ds->createField('email','Adresse email','\Seolan\Field\ShortText\ShortText',
                    '60','5','1','0','1','0','0','0');
    $ds->createField('db','Base de données','\Seolan\Field\ShortText\ShortText',
                    '30','6','0','0','0','0','0','0');
    $ds->createField('startclass','Shell (startclass)','\Seolan\Field\ShortText\ShortText',
                    '60','7','0','0','0','0','0','0');
    $ds->createField('comment','Commentaires','\Seolan\Field\Text\Text',
                    '60','8','0','0','0','0','0','0');
    $ds->createField('langues','Langues','\Seolan\Field\Link\Link',
                    '','9','1','0','0','0','1','0', 'VHOSTS_LANG');
    $ds->createField('ismaster','Master','\Seolan\Field\Boolean\Boolean',
                    '','10','1','0','0','0','0','0');
    $ds->createField('templat','Modèle','\Seolan\Field\Link\Link',
                    '','11','1','0','0','0','0','0', 'VHOSTS');
    $ds->createField('grpadmin','Groupe administrateur (oid)','\Seolan\Field\ShortText\ShortText',
                    '60','12','1','0','0','0','0','0');

    $ar['btab'] = 'VHOSTS_LANG';
    $ar['bname'][$lg] = 'Minisites - Langues';
    $ar['publish'] = false;
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar);
    $ds_lang = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=VHOSTS_LANG');
    $ds_lang->createField('title','Titre','\Seolan\Field\ShortText\ShortText',
                    '60','1','1','1','1','0','0','1');
    $ds_lang->createField('codeiso','Code Iso','\Seolan\Field\ShortText\ShortText',
                    '2','2','1','0','1','0','0','0','1');
    $ds_lang->createField('codetzr','Code tzr','\Seolan\Field\ShortText\ShortText',
                    '2','5','1','0','1','0','0','0','0');
    $ds_lang->procInput(array(
      'newoid' => 'VHOSTS_LANG:FR',
      'title' => 'Français',
      'codetzr' => 'FR',
      'codeiso' => 'fr'
    ));
    $ds_lang->procInput(array(
      'newoid' => 'VHOSTS_LANG:GB',
      'title' => 'Anglais',
      'codetzr' => 'GB',
      'codeiso' => 'en'
    ));
    // création d'un modèle
    $dbmodele = $this->getDB();
    $ds->procInput(array(
      'newoid' => 'VHOSTS:MODELE',
      'name' => 'Modèle',
      'vhost' => 'modele.' . $this->_module->domain,
      'email' => TZR_SENDER_ADDRESS,
      'db' => $dbmodele,
      'comment' => 'Modèle de base',
      'langues' => '||VHOSTS_LANG:FR||',
      'ismaster' => 1
    ));

    return $dbmodele;
  }

  private function createFileStructure () {
    // repertoires principaux
    mkdir(TZR_WWW_DIR.'minisites/', 0755);
    mkdir(TZR_VAR2_DIR.'minisites/', 0755);
    mkdir($GLOBALS['LOCALLIBTHEZORRO'].'/minisites/', 0755);

    // répertoire modele de base
    mkdir(TZR_WWW_DIR.'minisites/modele.' . $this->_module->domain, 0755);
    mkdir(TZR_VAR2_DIR.'minisites/modele.' . $this->_module->domain, 0755);
    mkdir($GLOBALS['LOCALLIBTHEZORRO'].'minisites/modele.' . $this->_module->domain, 0755);
    copy(CONFIG_INI, $GLOBALS['LOCALLIBTHEZORRO'].'minisites/modele.' . $this->_module->domain . '/local.ini');
  }

  // création des modules annexe
  private function createModules() {
    // langues
    $mod = new \Seolan\Module\Table\Wizard(array('newmoid' => XMODTABLE_TOID));
    $mod->_module->modulename = 'Langues';
    $mod->_module->group = $this->_module->group;
    $mod->_module->table = 'VHOSTS_LANG';
    $mod->_module->home = 1;
    $mod->_module->trackchanges = 0;
    $mod->_module->order = 'title';
    $mod->iend();
  }
  private function getDB() {
    return getDB()->select('show databases like "'.$this->_module->database_prefix.'_%"')->fetch(\PDO::FETCH_COLUMN);
  }
}
?>
