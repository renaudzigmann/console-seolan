<?php
/**
 * \Seolan\Module\MiniSite\MiniSite class
 *
 * Cette classe gère un ensemble de minisites.
 */
namespace Seolan\Module\MiniSite;
class MiniSite extends \Seolan\Module\Table\Table {
  public $domain;
  public $table;
  public $vhost;
  public $database_prefix;
  public $htdig;
  public $htdig_tpl;
  public $grpadmin = 'GRP:MINISITE';
  public $sharedTablesPattern = '\Seolan\Module\MiniSite\Model\DataSource\Shared\Shared';
  
  static $_master_config = null;
  static $_langues = null;

  function __construct($ar=NULL) {
    $this->table = 'VHOSTS';
    parent::__construct($ar);
    $this->multipleedit = false;
    \Seolan\Core\Labels::loadLabels('Seolan_Module_MiniSite_MiniSite');
  }

  /**
   * ajout fonctions spécifiques : ajout d'un modele, execution SQL sur les minisites
   * désactivation et restrictions d'accès de certaines fonctions
   */
  public function secGroups($function,$group=NULL) {
    $g = array();
    $g['del'] = array('admin');
    $g['editSelection'] = array('');
    $g['insertTemplate'] = array('admin');
    $g['procEditDup'] = array('');
    $g['editDup'] = array('');
    $g['duplicate'] = array('');
    $g['checkBOAccount'] = array('admin');
    if (isset($g[$function])) {
      if (!empty($group)) {
        return in_array($group, $g[$function]);
      }
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Domaine par défaut', 'domain', 'text');
    $this->_options->setOpt('Database prefix', 'database_prefix', 'text');
    $this->_options->setOpt('Gérer Htdig', 'htdig', 'boolean');
    $this->_options->setOpt('Htdig template', 'htdig_tpl', 'text');
    $this->_options->setOpt('Htdig prefix', 'htdig_prefix', 'text');
    $this->_options->setOpt('Groupe admin', 'grpadmin', 'text');
  }

  protected function _actionlist(&$my, $alfunction=true) {
    parent::_actionlist($my);
    if ($this->secure('', 'insertTemplate')) {
      $o1=new \Seolan\Core\Module\Action($this, 'insertTemplate', 'Nouveau modèle',
                            '&moid='.$this->_moid.'&_function=insertTemplate&template=Module/Table.new.html&tplentry=br', 'edit');
      $o1->menuable=true;
      $o1->containable=true;
      $my['insertTemplate']=$o1;
    }
  }

  function browse_actions(&$r, $assubmodule=false, $ar=null) {
    parent::browse_actions($r, $assubmodule, $ar);
    $bo_ico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'move');
    $bo_text = \Seolan\Core\Labels::getSysLabel('Seolan_Module_MiniSite_MiniSite', 'bo', 'text');
    $fo_ico = \Seolan\Core\Labels::getSysLabel('Seolan_Module_MiniSite_MiniSite', 'navigate');
    $fo_text = \Seolan\Core\Labels::getSysLabel('Seolan_Module_MiniSite_MiniSite', 'navigate', 'text');
    foreach($r['lines_oid'] as $i =>$oid) {
      // vhost console
      if ($vhmoid = $this->getAdminMoid($oid)) {
        $url = $r['lines_ovhost'][$i]->raw.TZR_SHARE_SCRIPTS.'admin.php?'.session_name().'='.session_id().'&moid='.$vhmoid.'&template=Core.layout/main.html&function=portail&setmssession=1';
         //Accès BO User FFCAM
        if (!\Seolan\Core\Shell::isRoot()) {
          $r['actions'][$i]['bo'] = '<a href="http://'.$url.'" target="bo'.$oid.'"
                                title="'.$bo_text.'">'.$bo_ico.'</a>';
        }
        // Accès BO Xsalto
        else {
          $url2 = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&_function=checkBOAccount&oid='.$oid.'&template=Core.empty.html';
          $r['actions'][$i]['bo'] = '<a href="http://'.$url.'" onclick="TZR.jQueryPost({target:this.modulecontainer,url:\''.$url2.'\'});" title="'.$bo_text.' (xsalto user)" target="_blank">'.$bo_ico.'</a>';
        }
      }
      // vhost website
      $url = $r['lines_ovhost'][$i]->raw;
      $r['actions'][$i]['fo'] = '<a href="http://'.$url.'/index.php?_cachepolicy=nocache&nocache=1" target="fo'.$oid.'"
                              title="'.$fo_text.'">'.$fo_ico.'</a>';
    }
  }
  
  //Nécessaire pour accéder au BO d'un minisite non publié avec un utilisateur xsalto
  function checkBOAccount($ar=null) {
    global $XUSER;
    $p = new \Seolan\Core\Param($ar);
    $oid = $p->get('oid');
    $vhmoid = $this->getAdminMoid($oid);
    
    if (\Seolan\Core\Shell::isRoot() && $XUSER->_cur['GRP']==TZR_GROUPID_ROOT && $XUSER->_cur['directoryname']=='xsalto') {
      $vhostDB = getDB()->select("SELECT db FROM VHOSTS WHERE KOID =?",[$oid])->fetch(\PDO::FETCH_COLUMN);
      $userExists = getDB()->select("SELECT KOID FROM $vhostDB.USERS WHERE GRP=? and directoryname=? and email=? and alias=?",
        [$XUSER->_cur['GRP'], $XUSER->_cur['directoryname'], $XUSER->_cur['email'], $XUSER->_cur['alias']])->fetch(\PDO::FETCH_COLUMN);
      if (empty($userExists)) {
        $vhostUserFields = getDB()->select("SELECT field FROM $vhostDB.DICT WHERE DTAB=? and field not in (?,?)",['USERS','UPD','refuge'])->fetchAll(\PDO::FETCH_COLUMN);
        $vhostUserFields = 'KOID,'.implode(',',array_filter($vhostUserFields));
        getDB()->execute("INSERT into {$vhostDB}.USERS({$vhostUserFields}) select $vhostUserFields from USERS where KOID=?",[$XUSER->_cur['KOID']]);
      }
    }
  }

 /**
  * Retourne l'oid du module admin d'un vhost
  * @param KOID $koid koid du vhost
  * @param TOID $toid toid du module
  * @return MOID
  */
  private function getAdminMoid($koid) {
    try{
      $db = getDB()->select("SELECT db FROM VHOSTS " .
			    "WHERE KOID = '$koid'")->fetch(\PDO::FETCH_COLUMN);
      if ($stmt = getDB()->select("SELECT MOID FROM " . $db . ".MODULES " .
				  "WHERE TOID = " . XMODADMIN_TOID)) {
	return $stmt->fetch(\PDO::FETCH_COLUMN);
      }
    }catch(\Exception $e){
      return null;
    }
    return null;
  }
 /**
  * Retourne la base d'un vhost
  * @param KOID $vhost vhost
  * @return db
  */
  static function getDBFromVhost($vhost) {
    static $_cache = null;
    if (!$_cache)
      $_cache = getDB()->select("SELECT vhost, db FROM VHOSTS")->fetchAll(\PDO::FETCH_COLUMN|\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE);
    return $_cache[$vhost];
  }

  public function usedTables() {
    return array('VHOSTS', 'VHOSTS_LANG');
  }

 /**
  * Vérifie l'existence d'un host dans
  * les hosts et aliases existants
  * @param string $vhost host (fqdn)
  * @param string $koid vhost oid (update)
  * @return bool true si le vhost existe.
  */
  private function hostExist($vhost, $koid = null) {
    return getDB()->select("SELECT count(*) FROM VHOSTS " .
                      "WHERE (vhost='$vhost' or aliases REGEXP '$vhost') " .(isset($koid)?" and KOID <> '$koid'":''))
                      ->fetch(\PDO::FETCH_COLUMN);
  }

 /**
  * Vérifie l'existence d'une liste d'alias dans
  * les hosts et aliases existants
  * @param string $aliases liste d'alias(fqdn)
  * @param string $koid vhost à exclure du check (maj)
  * @return bool true si un des alias existe.
  */
  private function aliasExist($aliases, $koid = Null) {
    if (empty($aliases))
      return false;

    $where_clause[] = "vhost REGEXP '$aliases' ";
    $aliases = preg_split("/([\n\s;,]+)/m", $aliases);
    foreach ($aliases as $alias)
      $where_clause[] = "aliases REGEXP '$alias'";
    $where_clause = implode(' or ', $where_clause);
    if ($koid)
      $where_clause = "koid <> '$koid' and ($where_clause)";
    return getDB()->select("SELECT count(*) FROM VHOSTS WHERE $where_clause")
            ->fetch(\PDO::FETCH_COLUMN);
  }

  /**
   * Installation htdig
   * @param string $vhost nom d'hote (fqdn)
   * @param string $htdig_start_url url de départ de l'indexation
   */
  private function enableHtdig($vhost, $htdig_start_url='/sitemap.html') {
    if (empty($vhost))
      return;
    @mkdir(MS_VAR_DIR.$vhost.'/htdig/db', 0755, true);

    $conf_file = MS_VAR_DIR.$vhost.'/'.$this->htdig_prefix.str_replace('.', '_', $vhost).'.conf';

    // purge pour procEdit
    @system('sudo -u htdig /usr/bin/remove-htdig-link '.$conf_file);

    $contents = '
database_dir:  '.MS_VAR_DIR.$vhost.'/htdig/db
start_url:    http://'.$vhost.$htdig_start_url.'
limit_urls_to:   http://'.$vhost.'/
search_dir: '.MS_WWW_DIR.$vhost.'/htdig'."\n\n";

    $contents .= file_get_contents($this->htdig_tpl);
    file_put_contents($conf_file, $contents);

    system('sudo -u htdig /usr/bin/create-htdig-link '.$conf_file);
  }

 /**
  * Modification du fichier local.ini
  * insertion du nom d'hote dans l'url
  * @param string $vhost nom d'hote (fqdn)
  * @param string $site_name title du site (local.ini societe)
  * @param bool $add_htdig_prefix ajouter le prefix htdig
  */
  private function changeIniFile($vhost, $site_name, $add_htdig_prefix=false) {
    $ini_file = MS_TZR_DIR . "$vhost/local.ini";
    $ini = parse_ini_file($ini_file, true);
    $ini['global']['societe'] = $site_name;
    $ini['global']['societe_url'] = 'http://' . $vhost . '/';
    if ($add_htdig_prefix) {
      $ini['Minisites']['htdig_prefix'] = $this->htdig_prefix;
    }

    foreach ($ini as $section => $content) {
      $txt .= "[$section]\n";
      foreach ($content as $var => $value)
        $txt .= "$var = \"$value\"\n";
    }
    file_put_contents($ini_file, $txt);
  }

 /**
  * Modification email et url dans les paramètres
  * des modules et pour le webmaster
  * @param string $vh_db base de donnée
  * @param string $email nouvel email
  * @param string $site_name expediteur
  * @param bool $cleartable vidage des tables, TRUE lors de la création
  */
  public function updateEmail($vh_db, $email, $site_name, $cleartable = false) {
    if (empty($vh_db))
      return;
    //   modules contact -> email sender
    getDB()->execute("update $vh_db.USERS set email='$email' where alias='webmaster'");
    $modcrm_stmt = getDB()->select("SELECT moid, mparam FROM $vh_db.MODULES ".
                           "WHERE toid=".XMODCRM_TOID);
    while ($modcrm_stmt && $modcrm = $modcrm_stmt->fetch()) {
      $mparam = \Seolan\Core\Options::decode($modcrm['mparam']);
      $mparam['sender'] = $email;
      $mparam['reportto'] = $email;
      $mparam['sendername'] = $site_name;
      getDB()->execute("UPDATE $vh_db.MODULES
                  SET mparam=? 
                  WHERE MOID=?",[\Seolan\Core\Options::rawToJSON($mparam), $modcrm['moid']]);
      // vider la table
      if ($cleartable)
        getDB()->execute("truncate table $vh_db.".$mparam['table']);
    }
    //   modules mailling list -> email sender
    $modml_stmt = getDB()->select("SELECT moid, mparam FROM $vh_db.MODULES
                              WHERE toid=".XMODMAILINGLIST_TOID);
    while ($modml_stmt && $modml = $modml_stmt->fetch()) {
      $mparam = \Seolan\Core\Options::decode($modml['mparam']);
      $mparam['sender'] = $email;
      $mparam['reportto'] = $email;
      $mparam['from'] = $email;
      $mparam['sendername'] = $site_name;
      $mparam['fromname'] = $site_name;
      $mparam['newsletterurl'] = preg_replace('|http://([^/]*)(.*)|',
                                    'http://'.$this->vhost.'$2',
                                    $mparam['newsletterurl']);
      getDB()->execute("UPDATE $vh_db.MODULES SET mparam=? WHERE MOID=?",array(\Seolan\Core\Options::rawToJSON($mparam),$modml['moid']));
      // vider la table
      if ($cleartable)
        getDB()->execute("truncate table $vh_db.".$mparam['table']);
    }
  }

  public function insert($ar) {
    $ar['selectedfields'] = array_diff($this->xset->orddesc, array('db', 'langues', 'ismaster', 'startclass'));
    $this->xset->desc['grpadmin']->default = $this->grpadmin;
    return parent::insert($ar);
  }

  public function edit($ar) {
    $ar['selectedfields'] = array_diff($this->xset->orddesc, array('db', 'ismaster', 'templat', 'CREAD'));
    return parent::edit($ar);
  }

  // prépare l'insertion d'un modèle
  function insertTemplate($ar) {
    \Seolan\Core\Shell::toScreen2('', 'hidden', array('isTemplate' => 1));
    $ar['selectedfields'] = array('name', 'vhost', 'comment', 'startclass', 'ismaster');
    $this->xset->desc['ismaster']->default = '1';
    return parent::insert($ar);
  }


  // Recherche d'une base disponible
  private function getDB() {
    $all_db = getDB()->select('show databases like "'.$this->database_prefix.'_%"')
              ->fetchAll(\PDO::FETCH_COLUMN);
    $used_db = getDB()->select('SELECT db as "Database" FROM VHOSTS')
              ->fetchAll(\PDO::FETCH_COLUMN);
    return array_shift(array_diff($all_db, $used_db));
  }
  /**
   * controle des informations
   * ajout de la fiche
   * initialisation des environnements (du modèle, du site)
   */
  public function procInsert($ar) {
    $p = new \Seolan\Core\Param($ar,array());
    $hidden = $p->get('hidden');
    $this->vhost = str_replace(array('https?://', '/'), '', strtolower(trim($p->get('vhost'))));
    $this->vhost = str_replace('_', '-', $this->vhost);
    $site_name = $p->get('name');
    $email = $p->get('email');
    $ismaster = $p->get('ismaster'); // modele ?
    $templat_oid = $p->get('templat'); // modele
    $error = false;
    if (!defined('MS_TZR_DIR') || !defined('MS_WWW_DIR') || !defined('MS_VAR_DIR')){
      $error .= 'MS constant(s) not defined : MS_TZR|WWW|VAR_DIR.<br />';
    }
    if (!$vh_db = $this->getDB())
      $error .= 'No more databases availables.<br />';
    else
      \Seolan\Core\Logs::notice('XModMiniSite::procInsert','Creating minisite '.$this->vhost.' using database '.$vh_db);

    // vhost -> fqdn, si vhost n'est pas un fqdn, ajout du domain par défaut
    if ( !preg_match('/.+\..+\..+/', $this->vhost) ) {
      if ( preg_match('/\./', $this->vhost) )
        $error .= 'Nom d\'hôte invalide<br />';
      else {
        $this->vhost .= '.'.$this->domain;
      }
    }
    /*https :
     *.*.$this->domain : non supporté  ou action de support requise
     Domaine différent de $this->domain : non supporté  ou action de support requise
    */
    if ( !preg_match('/.+'.$this->domain.'/', $this->vhost) ) {
      $error .= 'Le Nom d\'hôte est défini sur un nom de domaine différent du site. Le support du https nécessite l\'achat d\'un certificat. Le support du http nécessite une configuration spécifique et n\'est pas recommandé.<br />';
    }
    if ( preg_match('/[a-zA-Z0-9\-_]+(\.)[a-zA-Z0-9\-_]+(\.)[a-zA-Z0-9\-_]+(\.)[a-zA-Z0-9]+/', $this->vhost) ) {
      $error .= 'Le Nom d\'hôte définit plusieurs sous-domaines. Le support du https n\'est pas automatique et nécessite une configuration spécifique.<br />';
    }
    
    // vérification non existence vhost et aliases
    if ( $this->hostExist($this->vhost,$oid) )
      $error .= 'Cet hôte existe déjà.<br />';
    if ( $this->aliasExist($p->get('aliases')) )
      $error .= 'Alias déjà défini.<br />';


    if ($error) {
      \Seolan\Core\Shell::toScreen2($tplentry, 'message', $error);
      \Seolan\Core\Shell::changeTemplate('Module/Table.new.html');
      setSessionVar('message',$message);
      \Seolan\Core\Shell::setnext();
      $ar['options'] = $this->xset->prepareReInput($ar);
      $ar['tplentry'] = 'br';
      $ret = $this->insert($ar);
      $ret['error'] = $error;
      return $ret;
    }

    // insertion db
    $ar['vhost'] = $this->vhost;
    $ar['db'] = $vh_db;

    if ($ismaster != 1) {
      $template = $this->xset->display(array(
        'oid' => $templat_oid,
        'tplentry' => TZR_RETURN_DATA,
      ));
      $ar['startclass'] = $template['ostartclass']->raw;
      $ar['langues'] = preg_split("/\|\|/", $template['olangues']->raw, 0, PREG_SPLIT_NO_EMPTY);
      $ar['grpadmin'] = $template['ogrpadmin']->raw;
    }
    $ret = parent::procInsert($ar);

    // creation de la structure des fichiers
    mkdir(MS_TZR_DIR.$this->vhost, 0755, true);
    touch(MS_TZR_DIR.$this->vhost.'/local.ini');
    mkdir(MS_VAR_DIR.$this->vhost.'/tmp', 0755, true);
    mkdir(MS_VAR_DIR.$this->vhost.'/logs', 0755, true);
    $cp_www_dirs = array('data', 'templates_c');
    foreach ($cp_www_dirs as $dir)
      mkdir(MS_WWW_DIR.$this->vhost.'/'.$dir, 0755, true);

    if ($ismaster == 1) { // nouveau modèle
      \Seolan\Core\Shell::setNext('template=Core.message.html');
      $message = "Le modèle est créé,
      <br>base de données: $vh_db
      <br>répertoire web: " . MS_WWW_DIR . $this->vhost .
	"<br>répertoire tzr: " . MS_TZR_DIR . $this->vhost .
	"<br>fichier ini: " . MS_TZR_DIR . $this->vhost . "/local.ini
      <br>Vous devez procéder à l'installation.";
      setSessionVar('message', $message);
      return $ret;
    }

    if ($template['oismaster']->raw == 1)
      // copie d'un modèle
      $original_host = $template['ovhost']->raw;
    else {
      // copie d'un minisite, recherche du modèle pour les liens
      $tploid = $template['otemplat']->raw;
      do {
        list($masteroid, $original_host, $ismaster, $tploid) = getDB()->select("select KOID, vhost, ismaster, templat from VHOSTS where koid='$tploid'")->fetch(\PDO::FETCH_NUM);
      } while ($ismaster != 1);
      getDB()->execute("update VHOSTS set templat='$masteroid' where koid='{$ret[oid]}'");
    }


    // creation des liens
    // www
    foreach (new \DirectoryIterator(MS_WWW_DIR.$original_host) as $fileInfo) {
      if ($fileInfo->isDot() || in_array($fileInfo->getFilename(), $cp_www_dirs))
        continue;
      symlink($fileInfo->getPathname(), MS_WWW_DIR.$this->vhost.'/'.$fileInfo->getFilename());
    }
    // tzr
    foreach (new \DirectoryIterator(MS_TZR_DIR.$original_host) as $fileInfo) {
      if ($fileInfo->isDot())
        continue;
      symlink($fileInfo->getPathname(), MS_TZR_DIR.$this->vhost.'/'.$fileInfo->getFilename());
    }
    $sharedTables = $this->getSharedTable($template['odb']->raw);
    // lien templates (ou autres tables partagées)
    foreach ($sharedTables as $sharedTable)
      symlink(MS_WWW_DIR.$original_host.'/data/'.$sharedTable, MS_WWW_DIR.$this->vhost.'/data/'.$sharedTable);

    // copy des datas (sauf templates ou autres tables partagées)
    foreach (new \DirectoryIterator(MS_WWW_DIR.$template['ovhost']->raw.'/data') as $fileInfo) {
      if ($fileInfo->isDot() || in_array($fileInfo->getFilename(), $sharedTables))
        continue;
      \Seolan\Library\Dir::copy($fileInfo->getPathname(), MS_WWW_DIR.$this->vhost.'/data/'.$fileInfo->getFilename(), true);
    }

    // copy ini file
    copy(MS_TZR_DIR.$original_host.'/local.ini', MS_TZR_DIR.$this->vhost.'/local.ini');
    // modif local.ini
    $this->changeIniFile($this->vhost, $site_name, ($this->htdig == 1));

    // htdig
    if ($this->htdig == 1 && is_file($this->htdig_tpl) )
      $this->enableHtdig($this->vhost);

    // transfert base
    $DBHOST = preg_replace('/([^:]*)(:.*)+/', '$1', $GLOBALS['DATABASE_HOST']);
    system("mysqldump --routines --single-transaction -h $DBHOST -u {$GLOBALS['DATABASE_USER']} -p{$GLOBALS['DATABASE_PASSWORD']} {$template['odb']->raw} | mysql -h $DBHOST -u {$GLOBALS['DATABASE_USER']} -p{$GLOBALS['DATABASE_PASSWORD']} $vh_db", $dumpRet);
    if ($dumpRet) {
      \Seolan\Core\Logs::notice('XModMiniSite::procInsert',"command failed", "Erreur de copie de la base de données du modèle.");
      die("Erreur de copie de la base de données du modèle.");
    }

    // nettoyage
    if (!empty($vh_db)) {
      getDB()->execute("truncate table $vh_db.LOGS");
      getDB()->execute("truncate table $vh_db._LINKS");
      getDB()->execute("truncate table $vh_db._MARKS");
      getDB()->execute("truncate table $vh_db._MLOGS");
      getDB()->execute("truncate table $vh_db._MLOGSD");
      getDB()->execute("truncate table $vh_db._STATS");
      // si le modele est la console de base
      getDB()->execute("drop table IF EXISTS $vh_db.VHOSTS");
      getDB()->execute("delete $vh_db.AMSG from $vh_db.AMSG,$vh_db.BASEBASE where AMSG.MOID=BASEBASE.BOID and BTAB='VHOSTS'");
      getDB()->execute("DELETE FROM $vh_db.BASEBASE where BTAB = 'VHOSTS'");
      getDB()->execute("DELETE FROM $vh_db.MSGS where MTAB='VHOSTS'");
      getDB()->execute("DELETE FROM $vh_db.DICT where DTAB='VHOSTS'");
      getDB()->execute("drop table IF EXISTS $vh_db.VHOSTS_LANG");
      getDB()->execute("delete $vh_db.AMSG from $vh_db.AMSG,BASEBASE where AMSG.MOID=BASEBASE.BOID and BTAB='VHOSTS_LANG'");
      getDB()->execute("DELETE FROM $vh_db.BASEBASE where BTAB = 'VHOSTS_LANG'");
      getDB()->execute("DELETE FROM $vh_db.MSGS where MTAB='VHOSTS_LANG'");
      getDB()->execute("DELETE FROM $vh_db.DICT where DTAB='VHOSTS_LANG'");
      getDB()->execute("delete from $vh_db.MODULES where toid='".XMODMINISITES_TOID."'");
      getDB()->execute("delete from $vh_db.MODULES where toid=".XMODTABLE_TOID." AND ExtractValue(mparam, '//field[@name=\"table\"]/value')='VHOSTS_LANG'");

      // Modification paramètres des modules
      $this->updateEmail($vh_db, $email, $site_name, true);

    }
    $message = $this->getCreateMessage($vh_db, $email, $site_name);
    setSessionVar('message',$message);

    return $ret;
  }
  function getCreateMessage($vh_db, $email, $site_name){
    return $site_name.' a été crée. (database: '.$vh_db.')';
  }
  // retourne la liste des tables partagées d'un minisite
  // par regexp, on ne peux instancier les classes dans ce contexte
  private function getSharedTable($db) {
    return getDB()->select("select BTAB from $db.BASEBASE where BCLASS like '".$this->sharedTablesPattern."'")->fetchAll(\PDO::FETCH_COLUMN);
  }

  public function procEdit($ar) {
    $p = new \Seolan\Core\Param($ar,array());
    $this->vhost = str_replace(array('https?://', '/'), '', trim($p->get('vhost')));
    $site_name = $p->get('name');
    $email = $p->get('email');
    $void = $p->get('oid');

    $cur_vhost = getDB()->select("SELECT * FROM VHOSTS WHERE KOID='$void'")->fetch();
    if ( $this->vhost != $cur_vhost['vhost'] ) {
      // vhost -> fqdn, si vhost n'est pas un fqdn, ajout du domain par défaut
      //Autoriser les sites externes (répertoires site externe ok), l'alias doit contenir une url avec $this->domain
      /*if ( !preg_match('/(.+)\.(.+)\.(.+)/', $this->vhost) ) {
        if ( preg_match('/\./', $this->vhost) )
          $error .= 'Nom d\'hôte invalide<br />';
        else {
          $this->vhost .= '.'.$this->domain;
        }
      }*/
      // vérification non existence vhost et aliases
      if ( $this->hostExist($this->vhost,$void) )
        $error .= 'Cet hôte existe déjà.<br />';
      if ( $this->aliasExist($p->get('aliases'), $void) )
        $error .= 'Alias déjà défini.<br />';
        
      /*https :
        Domaine différent de $this->domain : non supporté  ou action de support requise
        *.*.$this->domain : non supporté  ou action de support requise
      */
      if ( !preg_match('/.+'.$this->domain.'/', $this->vhost) ) {
        $error .= 'Le Nom d\'hôte est défini sur un nom de domaine différent du site. Le support du https nécessite l\'achat d\'un certificat. Le support du http nécessite une configuration spécifique et n\'est pas recommandé.<br />';
      }
      if ( preg_match('/[a-zA-Z0-9\-_]+(\.)[a-zA-Z0-9\-_]+(\.)[a-zA-Z0-9\-_]+(\.)[a-zA-Z0-9]+/', $this->vhost) ) {
        $error .= 'Le Nom d\'hôte définit plusieurs sous-domaines. Le support du https n\'est pas automatique et nécessite une configuration spécifique.<br />';
      }
    }

    if ($error) {
      \Seolan\Core\Shell::toScreen2($tplentry, 'message', $error);
      \Seolan\Core\Shell::changeTemplate('Module/Table.edit.html');
      setSessionVar('message', $error);
      \Seolan\Core\Shell::setNext();

      $ar['options'] = $this->xset->prepareReEdit($ar);
      $ar['tplentry'] = 'br';
      $this->edit($ar);
      return;
    }
    // déplacement des répertoires
    $this->move($cur_vhost);

    if ( ($this->vhost != $cur_vhost['vhost']) || ($site_name != $cur_vhost['name']) ) {
      // modif local.ini
      $this->changeIniFile($this->vhost, $site_name, ($this->htdig == 1));
      // htdig
      if ($this->htdig == 1 && is_file($this->htdig_tpl) )
        $this->enableHtdig($this->vhost);
    }
    elseif ($this->htdig == 1 && is_file($this->htdig_tpl) && !is_dir(MS_VAR_DIR.$this->vhost.'/htdig/db')){
      $this->enableHtdig($this->vhost);
    }

    // Modification paramètres modules et url newsletter
    $this->updateEmail($cur_vhost['db'], $email, $site_name);
    // maj db
    $ar['vhost'] = $this->vhost;
    $ret = parent::procEdit($ar);
    $langues = $p->get('langues');
    $cur_langues = preg_split("/\|\|/", $cur_vhost['langues'], 0, PREG_SPLIT_NO_EMPTY);
    $_langues = getDB()->select("select KOID, codetzr from VHOSTS_LANG")->fetchAll(\PDO::FETCH_COLUMN|\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE);
    foreach ($p->get('langues') as $lang => $value) {
      if ($value == 'on' && $lang != 'Foo' && !in_array($lang, $cur_langues))
        $this->langCreate($cur_vhost['db'], 'FR', $_langues[$lang]);
    }
    return $ret;
  }

 /**
  * déplace les répertoires d'un minisite (changement d'hote)
  * @param array $host donnée actuelle en base
  */
  private function move($host) {
    if ( $this->vhost == $host['vhost'] )
      return;
    // déplacement des répertoires
    rename(MS_TZR_DIR.$host['vhost'], MS_TZR_DIR.$this->vhost);
    rename(MS_VAR_DIR.$host['vhost'], MS_VAR_DIR.$this->vhost);
    rename(MS_WWW_DIR.$host['vhost'], MS_WWW_DIR.$this->vhost);

    if ($host['ismaster'] != 1)
      return;
    // traitement des liens pour un modele
    $followers = getDB()->select('select vhost from VHOSTS where templat="'.$host['KOID'].'" and vhost != "'.$host['vhost'].'"')->fetchAll(\PDO::FETCH_COLUMN);
    $sharedTables = $this->getSharedTable($host['db']);
    return;
    foreach ($followers as $follower) {
      // www
      foreach (new \DirectoryIterator(MS_WWW_DIR.$follower) as $fileInfo) {
        if (!$fileInfo->isLink())
          continue;
        $target = readlink($fileInfo->getPathname());
        if (preg_match('@'.MS_WWW_DIR.$host['vhost'].'@', $target)) {
          unlink($fileInfo->getPathname());
          symlink(MS_WWW_DIR.$this->vhost.'/'.$fileInfo->getFilename(), MS_WWW_DIR.$follower.'/'.$fileInfo->getFilename());
        }
      }
      // tzr
      foreach (new \DirectoryIterator(MS_TZR_DIR.$follower) as $fileInfo) {
        if (!$fileInfo->isLink())
          continue;
        $target = readlink($fileInfo->getPathname());
        if (preg_match('@'.MS_TZR_DIR.$host['vhost'].'@', $target)) {
          unlink($fileInfo->getPathname());
          symlink(MS_TZR_DIR.$this->vhost.'/'.$fileInfo->getFilename(), MS_WWW_DIR.$follower.'/'.$fileInfo->getFilename());
        }
      }
      // data
      foreach ($sharedTables as $sharedTable) {
        unlink(MS_WWW_DIR.$follower.'/data/'.$sharedTable);
        symlink(MS_WWW_DIR.$this->vhost.'/data/'.$sharedTable, MS_WWW_DIR.$follower.'/data/'.$sharedTable);
      }
    }
  }
  /// creation d'une langue pour un minisite
  private function langCreate($db, $langsrc='FR', $langdst) {
    if (empty($db) || empty($langdst))
      return;
    getDB()->execute("create temporary table $db.t_MSGS as select * from $db.MSGS where MLANG='$langsrc'");
    getDB()->execute("update $db.t_MSGS set MLANG='$langdst'");
    getDB()->execute("insert ignore into $db.MSGS select * from $db.t_MSGS");
    getDB()->execute("drop table $db.t_MSGS");
    getDB()->execute("create temporary table $db.t_ACL4 as select * from $db.ACL4 where ALANG='$langsrc'");
    getDB()->execute("update $db.t_ACL4 set ALANG='$langdst'");
    getDB()->execute("insert ignore into $db.ACL4(AOID,AGRP,AFUNCTION,ACLASS,ALANG,AMOID,AKOID,OK) select MD5(RAND()*RAND()),AGRP,AFUNCTION,ACLASS,ALANG,AMOID,AKOID,OK from $db.t_ACL4");
    foreach (new \DirectoryIterator(MS_WWW_DIR.$original_host) as $fileInfo) {
      if ($fileInfo->isDot() || in_array($fileInfo->getFilename(), $cp_www_dirs))
        continue;
      symlink($fileInfo->getPathname(), MS_WWW_DIR.$this->vhost.'/'.$fileInfo->getFilename());
    }
    getDB()->execute("drop table $db.t_ACL4");
    getDB()->execute("create temporary table $db.t_AMSG as select * from $db.AMSG where MLANG='$langsrc'");
    getDB()->execute("update $db.t_AMSG set MLANG='$langdst'");
    getDB()->execute("insert ignore into $db.AMSG select * from $db.t_AMSG");
    getDB()->execute("drop table $db.t_AMSG");
    getDB()->execute("create temporary table $db.t_SETS as select * from $db.SETS where SLANG='$langsrc'");
    getDB()->execute("update $db.t_SETS set SLANG='$langdst'");
    getDB()->execute("insert ignore into $db.SETS select * from $db.t_SETS");
    getDB()->execute("drop table $db.t_SETS");
    $bases = getDB()->select("SELECT BTAB FROM $db.BASEBASE where TRANSLATABLE=1")->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($bases as $base) {
      getDB()->execute("create table $db.t_$base as select * from $db.$base where LANG='$langsrc'");
      getDB()->execute("update $db.t_$base set LANG='$langdst'");
      getDB()->execute("insert ignore into $db.$base select * from $db.t_$base");
      getDB()->execute("drop table $db.t_$base");
    }
  }

 /**
  * Sauvegarde un site et supprime la structure de fichier et la base
  * Produit un tar (vhost.tgz) dans le répertoire var/backup
  * @param KOID $oid koid du vhost à sauvegarder/supprimer
  * @return bool true si effacement
  */
  private function backupAndDelete($oid) {
    $vhost = getDB()->select("SELECT vhost, db, ismaster FROM VHOSTS WHERE KOID='$oid'")->fetch();
    if ($vhost['ismaster'] == 1) {
      $message = 'Suppression modèle impossible';
      setSessionVar('message', $message);
      \Seolan\Core\Shell::setNextData('message', $message);
      return false;
    }
    // dump & drop db
    $DBHOST = preg_replace('/([^:]*)(:.*)+/', '$1', $GLOBALS['DATABASE_HOST']);
    system('mysqldump --routines -h '.$DBHOST.
                    ' -u '.$GLOBALS['DATABASE_USER'].
                    ' -p'.$GLOBALS['DATABASE_PASSWORD'].
                    ' ' .$vhost['db'].' > '.MS_VAR_DIR.$vhost['vhost'].'/dump.sql');
    getDB()->execute('DROP DATABASE '.$vhost['db']);
    getDB()->execute('CREATE DATABASE '.$vhost['db']);
    // backup & delete files
    @mkdir(TZR_VAR2_DIR.'backup/');
    system('tar czf '.TZR_VAR2_DIR.'backup/'.$vhost['vhost'].date('_d_m_y').'.tgz '.
                      MS_TZR_DIR.$vhost['vhost'].' '.
                      MS_VAR_DIR.$vhost['vhost'].' '.
                      MS_WWW_DIR.$vhost['vhost'].' 2>/dev/null;
            rm -r '.MS_TZR_DIR.$vhost['vhost'].' '.MS_VAR_DIR.$vhost['vhost'].' '.MS_WWW_DIR.$vhost['vhost']);
    // suppression htdig
    if ($this->htdig == 1) {
      $conf_file = MS_VAR_DIR.$vhost.'/'.$this->htdig_prefix.str_replace('.', '_', $vhost).'.conf';
      @system('sudo -u htdig /usr/bin/remove-htdig-link '.$conf_file);
    }
    $message = $vhost['vhost'].' a été supprimé après archivage dans : '.TZR_VAR2_DIR.'backup/'.$vhost['vhost'].date('_d_m_y').'.tgz ';

    $_REQUEST['message'] = $_REQUEST['message'].'<br>'.$message;
    \Seolan\Core\Shell::setNextData('message', $message);
    \Seolan\Core\Shell::setNext($this->getMainAction());

    return true;
  }

  function del($ar) {
    $p = new \Seolan\Core\Param($ar,array());
    $oid=\Seolan\Core\Kernel::getSelectedOids($p,true,false);
    if (is_array($oid)){
      return parent::del($ar);
    }
    if (!empty($oid)){
      if($this->backupAndDelete($oid)){
        return parent::del($ar);
      }
    }
  }

  // interdire la duplication
  function procEditDup($ar) {
    $_REQUEST['message'] = 'Duplication impossible.';
    $_REQUEST['template'] = 'Core.message.html';
  }

  static function checkMinisiteConsole() {
    //From checkConsole - plusieurs fois par jour
    // Vidage de la table _TMP
    \Seolan\Core\Logs::notice(__METHOD__." clean _TMP for vhost {$minisite['vhost']} - {$minisite['db']}");
    if(\Seolan\Core\DataSource\DataSource::sourceExists('_TMP')){
      getDB()->execute('delete FROM _TMP where UPD<if(vtime is null or vtime=0,date_sub(NOW(),interval 60 minute),date_sub(NOW(),interval vtime minute))');
    }
    // Vidage du cache utilisateur innactif
    \Seolan\Core\Logs::notice(__METHOD__." Vidage du cache utilisateur innactif for vhost {$minisite['vhost']} - {$minisite['db']}");
    \Seolan\Core\User::clearOldDbSessionDataAndRightCache();
    // Vérification des mail retournés en erreur
    \Seolan\Core\Logs::notice(__METHOD__." Vérification des mail retournés en erreur for vhost {$minisite['vhost']} - {$minisite['db']}");
    \Seolan\Library\Mail::sendQueuedMails();
    
    // nettoyage des tokens périmés
    \Seolan\Core\Token::factory()->purge();
    
    //From checkConsole - 1 fois par jour
    $lastchk = \Seolan\Core\DbIni::get('xmodscheduler:lastchk2','val');
    
    \Seolan\Core\Logs::notice(__METHOD__." Last Daily Check for {$minisite['vhost']} - {$minisite['db']} =".$lastchk);
    if($lastchk!=date("Ymd")) {
      \Seolan\Core\DbIni::set('xmodscheduler:lastchk2',date('Ymd'));
      
      // execution de taches journalieres
      \Seolan\Core\Logs::notice(__METHOD__." Daily tasks for vhost {$minisite['vhost']} - {$minisite['db']} (nocheck=".\Seolan\Core\Ini::get('nocheck').")");
      $report='';
      if(!\Seolan\Core\Ini::get('nocheck')) {
        //\Seolan\Core\Integrity::chkDatabases($report);
        \Seolan\Core\Integrity::chkModules($report);
        \Seolan\Core\Integrity::chkOpts($report);
      }
    }
  }
  // rotation des logs console, appelé par \Seolan\Module\Scheduler\Scheduler::checkConsole()
  static function clean() {
    $vhosts = getDB()->select('select koid, db, grpadmin, vhost from VHOSTS')->fetchAll();
    foreach ($vhosts as $vhost) {
      \Seolan\Library\Dir::rotate(MS_VAR_DIR . $vhost['vhost'] . '/logs/', 0, 7);
      // Nettoyage des fichiers en cache serveur
      \Seolan\Library\Dir::clean(MS_VAR_DIR . $vhost['vhost'] . '/cache/', TZR_PAGE_EXPIRES);
      // Nettoyage des fichiers temporaires
      \Seolan\Library\Dir::clean(MS_VAR_DIR . $vhost['vhost'] . '/tmp/', TZR_PAGE_EXPIRES);
    }
  }
  
  function store_master_context() {
    self::_store_master_context();
  }
  static function _store_master_context() {
    $master_include_path = ini_get('include_path');
    self::$_langues = getDB()->select('select KOID, codetzr, codeiso from VHOSTS_LANG')->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE);
    self::$_master_config = [
      'DATABASE_NAME' => $GLOBALS['DATABASE_NAME'],
      'DATA_DIR'      => $GLOBALS['DATA_DIR'],
      'TZR_WWW_DIR' => $GLOBALS['TZR_WWW_DIR'],
      'HOME_ROOT_URL' => $GLOBALS['HOME_ROOT_URL'],
      'HOME' => $GLOBALS['HOME'],
      'LOCALLIBTHEZORRO' => $GLOBALS['LOCALLIBTHEZORRO'],
      'TZR_LANGUAGES' => $GLOBALS['TZR_LANGUAGES'],
      'XDBINI' => $GLOBALS['XDBINI'],
      'include_path'  => $master_include_path,
      'TZR_SESSION_MANAGER' => $GLOBALS['TZR_SESSION_MANAGER'],
    ];
    
    $GLOBALS['MASTER_DB_NAME'] = $GLOBALS['DATABASE_NAME'];
  }

  function change_context_to_vhost($vhost) {
    self::_change_context_to_vhost($vhost);
  }
  static function _change_context_to_vhost($vhost) {
    if (empty(self::$_langues) || empty(self::$_master_config))
      return false;

    $GLOBALS['IS_VHOST'] = true;
    $GLOBALS['DATABASE_NAME'] = $vhost['db'];
    $GLOBALS['DATA_DIR'] = MS_WWW_DIR.$vhost['vhost'].'/data/';
    $GLOBALS['TZR_WWW_DIR'] = MS_WWW_DIR.$vhost['vhost'];
    $GLOBALS['HOME_ROOT_URL'] = 'http://'.$vhost['vhost'];
    $GLOBALS['HOME'] = $vhost['vhost']; // Pour le sujet des mails
    $GLOBALS['LOCALLIBTHEZORRO'] = MS_TZR_DIR.$vhost['vhost'].'/';
    $GLOBALS['TZR_LANGUAGES'] = array();
    foreach (preg_split('/\|\|/', $vhost['langues'], 0, PREG_SPLIT_NO_EMPTY) as $langOid) {
      $GLOBALS['TZR_LANGUAGES'][self::$_langues[$langOid]['codetzr']] = self::$_langues[$langOid]['codeiso'];
    }
    if (empty($GLOBALS['TZR_LANGUAGES']))
      $GLOBALS['TZR_LANGUAGES'] = self::$_master_config['TZR_LANGUAGES'];
    $GLOBALS['XDBINI'] = new \Seolan\Core\DbIni();
    $localIni = $GLOBALS['LOCALLIBTHEZORRO'].'local.ini';
    if (file_exists($localIni)) {
      $GLOBALS['TZR_INI']=new \Seolan\Core\Ini($localIni);
      $GLOBALS['TZR']=$GLOBALS['TZR_INI']->load();
    } else {
      $GLOBALS['TZR_INI']=new \Seolan\Core\Ini();
	    $GLOBALS['TZR']=$GLOBALS['TZR_INI']->load();
    }
    ini_set('include_path', $GLOBALS['LOCALLIBTHEZORRO'].':'.self::$_master_config['include_path']);
    ini_set('include_path', MS_TZR_DIR.$vhost['vhost'].'/common_inc:'.ini_get('include_path'));

    getDB()->execute("use ".$vhost['db']);
    // vide la cache des modules et XSet
    \Seolan\Core\Module\Module::clearCache();
    \Seolan\Core\DataSource\DataSource::clearCache();
    \Seolan\Core\System::clearCache();
    \Seolan\Core\User::clearCache();
    \Seolan\Library\ProcessCache::deactivate();
    //\Seolan\Library\Database::deactivateCache();
    \Seolan\Core\Shell::getLangData(NULL,true,true);
    //\Seolan\Core\DataSource\DataSource::preLoadBaseBase(true);
    
    $GLOBALS['TZR_SESSION_MANAGER'] = '\Seolan\Core\Session';
    $GLOBALS['minisite'] = $vhost;
    return true;
  }
  
  function change_context_to_master() {
    self::change_context_to_master();
  }
  static function _change_context_to_master() {
    if (empty(self::$_master_config)) return false;
    
    $GLOBALS['IS_VHOST'] = false;
    $GLOBALS['DATABASE_NAME'] = self::$_master_config['DATABASE_NAME'];
    $GLOBALS['DATA_DIR']      = self::$_master_config['DATA_DIR'];
    $GLOBALS['TZR_WWW_DIR'] = self::$_master_config['TZR_WWW_DIR'];
    $GLOBALS['HOME_ROOT_URL'] = self::$_master_config['HOME_ROOT_URL'];
    $GLOBALS['HOME'] = self::$_master_config['HOME'];
    $GLOBALS['LOCALLIBTHEZORRO'] = self::$_master_config['LOCALLIBTHEZORRO'];
    $GLOBALS['TZR_LANGUAGES'] = self::$_master_config['TZR_LANGUAGES'];
    $GLOBALS['XDBINI'] = self::$_master_config['XDBINI'];
    $GLOBALS['TZR_SESSION_MANAGER'] = self::$_master_config['TZR_SESSION_MANAGER'];
    if (defined('CONFIG_INI') && CONFIG_INI) $localIni = CONFIG_INI;
    else $localIni = $GLOBALS['LOCALLIBTHEZORRO'].'local.ini';
    if (file_exists($localIni)) {
      $GLOBALS['TZR_INI']=new \Seolan\Core\Ini($localIni);
      $GLOBALS['TZR']=$GLOBALS['TZR_INI']->load();
    } else {
      $GLOBALS['TZR_INI']=new \Seolan\Core\Ini();
	    $GLOBALS['TZR']=$GLOBALS['TZR_INI']->load();
    }
    ini_set('include_path', $GLOBALS['LOCALLIBTHEZORRO'].':'.self::$_master_config['include_path']);
    
    getDB()->execute("use {$GLOBALS['DATABASE_NAME']}");

    // vide la cache des modules et XSet
    \Seolan\Core\Module\Module::clearCache();
    \Seolan\Core\DataSource\DataSource::clearCache();
    \Seolan\Core\System::clearCache();
    \Seolan\Core\User::clearCache();
    \Seolan\Library\ProcessCache::activate();
    \Seolan\Library\Database::activateCache();
    return true;
  }

  function _daemon($period='any') {
    $vhosts = getDB()->select('select koid, db, grpadmin, vhost from VHOSTS')->fetchAll();
    // mise à jour des utilisateurs du groupe minisite de la console de base
    // dans les utilisateurs du groupe admin du minisite
    $ms_users = getDB()->select('select * from USERS where grp like "%GRP:MINISITE%"')->fetchAll();
    $ms_opts = getDB()->select('select o.* from OPTS o inner join USERS u on o.user = u.KOID where u.grp like "%GRP:MINISITE%" and o.dtype like "password%"')->fetchAll();
    foreach ($vhosts as $vhost) {
      if (empty($vhost['db']) || empty($vhost['grpadmin']))
        continue;
      $users_oids = array();
      // vérifier la présence goupe minisite
      $rs_grpms = getDB()->select("select koid from {$vhost['db']}.GRP where koid='GRP:MINISITE'");
      if (!$rs_grpms)
        continue;
      $grpms = $rs_grpms->fetch(\PDO::FETCH_COLUMN);
      if (!$grpms)
        getDB()->execute("insert into {$vhost['db']}.GRP (KOID,LANG,UPD,GRP,DESCR) values ('GRP:MINISITE', 'FR', null, 'Gestionnaire minisite', 'Groupe des utilisateurs commun avec la console de base')");
      // mettre à jour les utilisateurs
      $fields = getDB()->select("show columns from {$vhost['db']}.USERS")->fetchAll(\PDO::FETCH_COLUMN);
      foreach ($ms_users as $user) {
        $current_user = getDB()->select("select * from {$vhost['db']}.USERS where KOID='{$user['KOID']}'")->fetch();
        foreach ($user as $field => $value) {
          // ajouter les groupes minisite et admin
          if ($field == 'GRP') {
            $user_grps = preg_split("/\|\|/", $current_user['GRP'], 0, PREG_SPLIT_NO_EMPTY);
            if (!in_array('GRP:MINISITE', $user_grps))
              $user_grps[] = 'GRP:MINISITE';
            if (!in_array($vhost['grpadmin'], $user_grps))
              $user_grps[] = $vhost['grpadmin'];
            $current_user['GRP'] = implode('||', $user_grps);
            continue;
          }
          // vérifier la présence du champ dans la base minisite
          if ($current_user && array_key_exists($field, $current_user) || in_array($field, $fields)) {
            $current_user[$field] = $user[$field];
          }
        }
        $current_user['passwd'] = $user['passwd'];
        getDB()->execute('REPLACE INTO '.$vhost['db'].'.USERS ('.implode(',', array_keys($current_user)).') values ("'.implode('","', $current_user).'")');
        $users_oids[] = $current_user['KOID'];
      }

      // mettre à jour les OPTS
      $fields = getDB()->select("show columns from {$vhost['db']}.OPTS")->fetchAll(\PDO::FETCH_COLUMN);
      foreach ($ms_opts as $opt) {
        $current_opt = getDB()->select("select * from {$vhost['db']}.OPTS where user='{$opt['user']}' and dtype='{$opt['dtype']}' ")->fetch();
        foreach ($opt as $field => $value) {
          // vérifier la présence du champ dans la base minisite
          if ($current_opt && array_key_exists($field, $current_opt) || in_array($field, $fields)) {
            \Seolan\Core\Logs::debug("current_opt[$field] = ".$opt[$field]);
            $current_opt[$field] = $opt[$field];
          }
        }
        getDB()->execute('REPLACE INTO '.$vhost['db'].'.OPTS ('.implode(',', array_keys($current_opt)).') values (\''.implode('\',\'', $current_opt).'\')');
      }
      // suppresion des utilisateurs et des OPTS
      $del_users_oids = getDB()->select("select KOID from {$vhost['db']}.USERS where GRP like '%GRP:MINISITE%' and koid not in ('".implode("','", $users_oids)."')")->fetchAll(\PDO::FETCH_COLUMN);
      getDB()->execute("delete from {$vhost['db']}.USERS where koid in ('".implode("','", $del_users_oids)."')");
      getDB()->execute("delete from {$vhost['db']}.OPTS where dtype like 'password%' and user in ('".implode("','", $del_users_oids)."')");
    }
  }
  
  function getInfos($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>TZR_RETURN_DATA));
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret = parent::getInfos($ar);
    if (!defined('MS_TZR_DIR') || !defined('MS_WWW_DIR') || !defined('MS_VAR_DIR')){
      $ret['infos']['conf1'] =(Object)['label'=>'MS constant(s) not defined', 'html'=>'MS_TZR|WWW|VAR_DIR'];
    } else {
      $ret['infos']['conf1'] =(Object)['label'=>'MS_WWW_DIR', 'html'=>MS_WWW_DIR];
      $ret['infos']['conf2'] =(Object)['label'=>'MS_TZR_DIR', 'html'=>MS_TZR_DIR];
      $ret['infos']['conf3'] =(Object)['label'=>'MS_VAR_DIR', 'html'=>MS_VAR_DIR];
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  
  static function applyClassUpgrade($classname, $upgradeno, $dryrun=false) {
    $shellargMinisiteDB = null;
    $shellargclassname = escapeshellcmd($classname);
    $config = $GLOBALS['LOCALLIBTHEZORRO'].'local.php';
    //PHP_BINARY
    if(!defined('PHP_SEOLAN') || !PHP_SEOLAN) define('PHP_SEOLAN', 'php-seolan10-7.3');
    
    $rs = getDB()->fetchAll('select db from VHOSTS /*upgrade*/');
    foreach($rs as $minisite) {
      $shellargMinisiteDB = escapeshellcmd($minisite['db']);
      \Seolan\Core\Logs::upgradeLog(__METHOD__." execute csx/scripts/cli/minisites/applyClassUpgrade.php for $shellargMinisiteDB $shellargclassname, $upgradeno ($config)");

    //exec(PHP_BINARY . " ".$GLOBALS['LIBTHEZORRO']."/scripts/cli/minisites/applyClassUpgrade.php --class=$shellargclassname --upgrade=$upgradeno");
    /* Mode d'execution : 
     * & à la fin (background process) + redirection de la sortie => exec n'attend pas fin du process
     * nohup => continuer le process même en cas de deconnexion
     * > /dev/null => Redirection de stdout dans /dev/null
     * 2>> filename => Rediriger stderr dans filename (mode append)
     * & à la fin => background process = exec n'attend pas fin du process
     * echo $! => retourne le pid du process créé
    **/

      $cmd = 'nohup '.PHP_SEOLAN.' '. $GLOBALS['LIBTHEZORRO']."scripts/cli/minisites/applyClassUpgrade.php --db=$shellargMinisiteDB --class=$shellargclassname --upgrade=$upgradeno -C $config";
      if ($dryrun)
        $cmd .= ' --dryrun';
      $cmd .= ' >> '.TZR_LOG_DIR.'upgradeMinisites.log  2>> '.TZR_LOG_DIR."upgradeMinisitesErrors.log < /dev/null & echo $! ";
      \Seolan\Core\Logs::upgradeLog(__METHOD__." CMD=$cmd");
      $pid = exec($cmd);
      \Seolan\Core\Logs::upgradeLog(__METHOD__."___________________________ launched process pid=$pid");
      usleep(100000);
    }
  }
  
  static function runSchedulerForMinisite($minisite) {
    $shellargMinisiteDB = escapeshellcmd($minisite['db']);
    $config = $GLOBALS['LOCALLIBTHEZORRO'].'local.php';
    \Seolan\Core\Logs::critical(__METHOD__." execute csx/scripts/cli/minisites/runScheduler.php for {$minisite['db']}, {$minisite['vhost']} ($config)");
    
    //PHP_BINARY
    if(!defined('PHP_SEOLAN') || !PHP_SEOLAN) define('PHP_SEOLAN', 'php-seolan10-7.3');
    $cmd = 'nohup '.PHP_SEOLAN.' '. $GLOBALS['LIBTHEZORRO']."scripts/cli/minisites/runScheduler.php --db=$shellargMinisiteDB -C $config";
    $cmd .= ' >> '.TZR_LOG_DIR.'schedulerMinisite.log  2>> '.TZR_LOG_DIR."schedulerMinisiteErrors.log < /dev/null & echo $! ";
    \Seolan\Core\Logs::critical(__METHOD__." CMD=$cmd");
    $pid = exec($cmd);
    \Seolan\Core\Logs::critical(__METHOD__."___________________________ launched process pid=$pid");
  }
}
