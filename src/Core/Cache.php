<?php
namespace Seolan\Core;

/**
 * NAME
 *   \Seolan\Core\Cache -- gestion du cache applicatif
 * DESCRIPTION
 *   Classe dediee a tion du cache
 * SYNOPSIS
 *   La politque de gestion du cache :
 *   none: il n'y a pas de politque de gestion du cache, les informations 
 *   sur la gestion du cache sont recherche dans le local.ini; 
 *   nocache: pas de cache, les pages sont recalcuees;
 *   forcecache: forcer la mise ajour du cache. 
 ****/
class Cache {
  public $_lifetime = 200;// en secondes
  public $_gracetime = 800;// en secondes
  public $_cachepolicies=array('none','_nocache','nocache','forcecache','_forcecache');
  public $filename=NULL;

  function __construct() {
    $this->_lifetime = \Seolan\Core\Ini::get('cache_timeout');
    $this->_gracetime = \Seolan\Core\Ini::get('cache_gracetimeout');
  }

  /****m* \Seolan\Core\Cache/setCachePolicy
   * NAME
   *   \Seolan\Core\Cache::setCachePolicy - change la politique de gestion du cache
   * INPUTS
   *   $cachepolicy - peut prendre pour valeur none, nocache, forcecache.
   * DESCRIPTION
   *   Une fois la politique de gestion du cache positionnée, elle est
   *   propagée sur l'URL dans les macros $self.
   ****/
  function setCachePolicy($cachepolicy=NULL) {
    if (!$cachepolicy && !empty($_REQUEST['_cachepolicy']))
      $cachepolicy = $_REQUEST['_cachepolicy'];
    if (isset($cachepolicy) && in_array($cachepolicy,$this->_cachepolicies)) {
      if (($cachepolicy=='none') || empty($cachepolicy))
        clearSessionVar('cachepolicy');
      else {
        setSessionVar('cachepolicy',$cachepolicy);
      }
    }
  }
  function getCachePolicy() {
    return getSessionVar('cachepolicy');
  }

  /***** \Seolan\Core\Cache/putPageInServerCache
   * NAME
   *   \Seolan\Core\Cache::putPageInServerCache - rend vrai si la page calculée doit être conservée dans le cache serveur
   * DESCRIPTION
   *   rend vrai si la page calculée doit être conservée dans le cache serveur
   * RETURN
   *   true/false
   ****/
  static function putPageInServerCache() {
    // changement des parametres de traitement du cache
    $ret=true;
    if(!\Seolan\Core\Ini::get('cache_activated')) $ret=false;
    elseif(!empty($_REQUEST['_usecache'])){
      \Seolan\Core\Logs::debug(__METHOD__.': forced yes');
      return true;
    }elseif(!empty($_REQUEST['_nocache']) || !empty($_REQUEST['nocache']) ||
            (isset($_REQUEST['_cachepolicy']) && ($_REQUEST['_cachepolicy']=='nocache')) ||
            \Seolan\Core\Shell::admini_mode() ||
            $_SERVER['REQUEST_METHOD']!='GET' ||
            (!groupCacheActive() && sessionActive())) {
      $ret=false;
    }

    if(!$ret) \Seolan\Core\Logs::debug(__METHOD__.': no');
    else \Seolan\Core\Logs::debug(__METHOD__.': yes');
    return $ret;
  }


  function clean_cache() {
    $domain = implode('.', array_slice(explode('.', parse_url($GLOBALS['HOME_ROOT_URL'], PHP_URL_HOST)), -2));
    $cache_dir=TZR_VAR2_DIR."cache/*$domain/*";
    system('/bin/rm -rf '.$cache_dir);
    \Seolan\Core\Logs::update('cache', 0, 'cache cleaned '.$cache_dir);
  }

 /**
  * calcul le fichier de cache à partir du nom du template
  * et des paramètres de la requete
  * la fonction tzr_cache_string() permettent de personaliser
  * crée le répertoire de destination
  */
  function get_filename($template, $ar) {
    if ($this->filename) // évite le recalcul depuis store
      return $this->filename;

    $p = new \Seolan\Core\Param($ar, array());
    $url = self::canonicUrl($_SERVER['REQUEST_URI']);

    $request = parse_url($url);
    $filename = $request['path'];

    // s'il y a des paramètres sur l'uri on les intègre dans le nom du fichier de cache
    if(!empty($request['query'])) {
      parse_str($request['query'], $variables);
      $filename = $request['path'];
      
      foreach($variables as $variable => $value) {
	if(is_array($value)) {
	  $value = json_encode($value);
	}
	$filename.='/'.$variable.'/'.$value;
      }
      $filename=$this->sanitizePath($filename);
    }
    // on change le nom du fichier de maniere a ne pas telescoper un nom de repertoire
    if(empty($filename) || ($filename=='/')) $filename='/_empty-uri.html';
    
    $this->filename=CACHE_DIR.substr($filename,1).'.cache';
    if (!file_exists($this->filename))
      \Seolan\Library\Dir::mkdir($this->filename,true);

    \Seolan\Core\Logs::debug(__METHOD__.'(): '.$this->filename);
    return $this->filename;
  }

  /// enregistrement dans le fichier pagedefaults-<date> des pages à recalculer
  static function registerPageDefault($url) {
    $nowrepl=date('YmdHi');
    \Seolan\Core\Logs::debug(__METHOD__.'()');
    file_put_contents(TZR_LOG_DIR.'pagedefaults-'.$nowrepl, $url."\n", FILE_APPEND | LOCK_EX);
  }
    
  static function canonicUrl($url) {
    if(empty($url)) $url=getCurrentPageUrl();
    $url = urldecode($url);
    // On rajoute le md5 du groupe si on utilise le cache par groupe
    if(groupCacheActive() && sessionActive() && strpos($url, 'md5group')===false) {
      $groups = array_unique(getSessionVar('Groups'));
      if($groups) {
        sort($groups);
        if(strpos($url, '?')!==false) {
          $url .= '&md5group='.md5(implode($groups));
        }
        else {
          $url .= '?md5group='.md5(implode($groups));
        }
      }
    }
    return $url;
  }


 /**
  * Délivre le fichier en cache
  * si la page est en cache, qu'elle est moins agée que la periode de grace on rend la page en cache
  * si la page est en cache, qu'on est en surcharge de serveur, on rend la page en cache
  * si la page n'est pas en cache on rend rien
  * si la page est en cache, que son age est entre la duree de vie et la periode de grave on la recalcule
  * cette page ne calcule pas la page mais indique en retour s'il faut las recalculer
  * @return bool rend vrai si la page a ete livree
  */
  function delivery($template, $mime, $_disp=null, $ar) {
    // test si le cache est possible
    if(!$this->putPageInServerCache())
      return false;
    // calcul le fichier de cache
    if (!$cache_filename = $this->get_filename($template, $ar))
      return false;
    // force le recalcul du cache si 'forcecache=1'
    $p = new \Seolan\Core\Param($ar);
    if ($p->get('forcecache') == 1) {
      if($p->get('cacheoid')) {
        $uid=getDB()->fetchOne('select OWN from _PCACHE c where c.KOID=?', [$p->get('cacheoid')]);
        if(!empty($uid) && ($uid!=TZR_USERID_NOBODY)) {
	  (new $GLOBALS['TZR_SESSION_MANAGER'])->setUserFromUid($uid, __METHOD__);
        }
      }
      \Seolan\Core\Logs::debug("[\Seolan\Core\Cache::delivery] forcecache:unlink($cache_filename)");
      @unlink($cache_filename);
      @unlink($cache_filename.'.headers');
      return false;
    }
    // date de modification
    if (!$file_date = filectime($cache_filename))
      return false;
    
    // age du fichier en secondes
    $age = (time()-$file_date);
    $lf=(integer)$this->_lifetime;
    // calcul de la charge serveur
    $deliver=true;
    $recompute=false;
    if($age > $lf) {
      $load = \Seolan\Core\System::uptime();
      $server_is_ok = ($load['procs.r']<=TZR_MAX_LOAD);
      if($server_is_ok) {
        // periode de grace : on retourne le cache mais on lance le calcul de la page malgre tout apres coup
        if($age < $this->_gracetime) {
          \Seolan\Core\Logs::notice('\Seolan\Core\Cache::delivery', "cache ok age $age > {$this->_lifetime}, grace period {$this->_gracetime}, server load ".$load['procs.r']);
          $deliver=true;
          $recompute=true;
        } else {
          \Seolan\Core\Logs::notice('\Seolan\Core\Cache::delivery', "cache nok age $age > {$this->_lifetime} server load ".$load['procs.r']);
          $deliver=false;
          $recompute=true;
        }
      } else {
        $deliver=true;
	$recompute=true;
        \Seolan\Core\Logs::notice('\Seolan\Core\Cache::delivery', "cache nok age $age > {$this->_lifetime} server overloaded ".$load['procs.r']);
      }
    } else {
      $deliver=true;
      \Seolan\Core\Logs::notice('\Seolan\Core\Cache::delivery', "cache ok age $age < {$this->_lifetime}");
    }
    
    if($deliver) {
      ob_start();
      // cas où il faut générer le header
      if(!file_exists($cache_filename.'.headers')) {
	$mtime = gmdate('D, d M Y H:i:s', $file_date). ' GMT';
	\Seolan\Core\Logs::debug('\Seolan\Core\Cache::delivery: before cache read '.$mtime);
	
	header('Last-Modified: ' . $mtime);
	header('Expires: ' . gmdate('D, d M Y H:i:s', time()+TZR_PAGE_EXPIRES) . ' GMT');
	header('Content-type: '.$mime.'; charset='.strtolower($charset));
	if (!empty($_disp)) {
	  header('Content-disposition: '.$_disp.'; filename=\"'.$template.'"');
	}
      } else {
	$headers=file($cache_filename.'.headers');
	foreach($headers as $header) header($header);
      }
      // lecture des données depuis le cache et envoi
      $display = file_get_contents($cache_filename);
      $this->contentDelivery($display);
      
      @ob_end_flush();
      \Seolan\Core\Logs::debug(__METHOD__."(template=$template, mime=$mime): page delivered ".$cache_filename.
		   ", delivery=$deliver, recompute=$recompute");
    }
    if($recompute && $deliver) {
      if (groupCacheActive() && sessionActive()) {
        // ne peux être recalculer, nécessite une authentification
        unlink($cache_filename);
      } else {
        self::registerPageDefault($GLOBALS['XSHELL']->fullurl);
      }
    }
    return $deliver;
  }
  /**
   * Store in cache file
   * @param string $content to store
   */
  function store($content, $template, $ar) {
    if(!$this->putPageInServerCache()) {
      \Seolan\Core\Logs::debug('\Seolan\Core\Cache::store: no cache');
      return false;
    }
    $cache_filename = $this->get_filename($template, $ar);
    $url=$GLOBALS['XSHELL']->fullurl;
    if(empty($url)) $url=getCurrentPageUrl();
    \Seolan\Core\Logs::debug('\Seolan\Core\Cache::store: cache <'.$url.'> to '.$cache_filename);
    file_put_contents($cache_filename, $content, LOCK_EX);
    $headers = headers_list();
    // on stocke dans le fichier le code retour qui a été renseigné
    $headersText = 'HTTP/1.1 '.http_response_code();
    // on vire les headers qui parlent des cookies
    foreach($headers as $header) {
      if(!preg_match('/(cookie)/i',$header)) {
	if(empty($headersText)) $headersText=$header;
	else $headersText.="\n".$header;
      }
    }
    // on garde les headers dans un fichier séparé
    file_put_contents($cache_filename.'.headers', $headersText, LOCK_EX);

    // on note la page dans le module de gestion du cache
    if(\Seolan\Core\System::tableExists('_PCACHE')) {
      $cache = \Seolan\Core\Module\Module::singletonFactory(XMODCACHE_TOID);
      $cache->registerPage($cache_filename,$url);
    }
  }
  /**
   * Store in cache file and delivers the content to user
   * Set http headers if not sets
   * @param string $content to store
   */
  function storeAndDeliver($content, $template, $ar, $complementHeaders=array()) {
    // envoi des headers
    header('Expires: ' . gmdate('D, d M Y H:i:s', time()+TZR_PAGE_EXPIRES) . ' GMT');
    foreach($complementHeaders as $header) header($header, true); // on remplace les headers existants
    // stockage dans le cache des contenus et des headers
    $this->store($content, $template, $ar);
    // envoi du contenu
    $this->contentDelivery($content);
  }

  /// conversion de charset et envoi du contenu $content
  function contentDelivery($content) {
    $charset = \Seolan\Core\Lang::getCharset();
    if( (empty($_SERVER['HTTP_USER_AGENT']) || substr($_SERVER['HTTP_USER_AGENT'],0,6) != "Smarty") && $charset != TZR_INTERNAL_CHARSET){
      convert_charset($content,  TZR_INTERNAL_CHARSET, $charset);
    }
    if(!empty($_REQUEST['sessionid'])) {
      $content = preg_replace('/sessionid=\w+/', 'sessionid=' . $_REQUEST['sessionid'], $content);
      $content = preg_replace('/"sessionid":"\w+"/', '"sessionid":"'.$_REQUEST['sessionid'].'"', $content);
    }
    header('Accept-Ranges: bytes');
    header('Content-Length: '.strlen($content));
    echo $content;
  }
  
  /**
   * Sanitizes a filename, replacing whitespace with dashes.
   */
  public static function sanitizeFileName( $filename ) {
    $special_chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}", "%", "+", chr(0));
    $special_chars_hex = array_map(function($item) { return '0x'.dechex(ord($item)); }, $special_chars);
    $filename = preg_replace( "#\x{00a0}#siu", ' ', $filename );
    $filename = str_replace( $special_chars, $special_chars_hex, $filename );
    $filename = str_replace( array( '%20', '+' ), '-', $filename );
    $filename = preg_replace( '/[\r\n\t -]+/', '-', $filename );
    $filename = trim( $filename, '.-_' );
    
    return $filename;
  }
  
  protected function sanitizePath( string $path) {
    $path = preg_replace( '/\/$/', '/index', $path );
    $parts = explode('/', $path);
    $path2 = array_map('\Seolan\Core\Cache::sanitizeFileName', $parts);
    return preg_replace('/\/+/','/',implode('/',$path2));
  }
}
?>
