<?php
namespace Seolan\Application\MiniSite;

/// Classe pour minisite responsive (templates : application:public/templates/mini-responsive/
/// Gestion de base des gabarits via application templates dirs (sans utliser application:)
/// Dernier point a voir ?
class MiniSite extends \Seolan\Core\Application\ConfigurableApplication implements \Seolan\Core\ISec, \Seolan\Core\IRewriting {
  private $_infoTree = null;
  private $_charte = null;
  protected static $defaultTemplate = 'index.html';

  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Logs::debug(__METHOD__.'::application minisite');
  }
  /**
   * run de l'application
   * template par defaut
   */
  public function run(){
    parent::run ();
    if (!TZR_ADMINI){
      if (empty ( $_REQUEST ['template'] ) || $_REQUEST ['template'] == TZR_DEFAULT_TEMPLATE) {
	\Seolan\Core\Logs::notice(__METHOD__, 'set template for FO '.static::$defaultTemplate);
	$_REQUEST ['template'] = static::$defaultTemplate;
      }
    }
    $xcharte = $this->getCharte();
    \Seolan\Core\Shell::toScreen1('charte',$xcharte);
  }
  /**
   * la locallibthezorro CSX/src sont dans les smarty dirs
   * -> src/Application/MiniSite/public/templates/mini-responsive
   * -> tzr/ ....
   * -> on ajoute www/minisites/templates ? si local templates ?
   */
  protected function getApplicationTemplatesDirs() : ?array{
    if (defined('MS_WWW_DIR'))
      return [MS_WWW_DIR.$_SERVER['SERVER_NAME'].'/templates/'];
    return null;
  }
  /**
   * ... voir Core\Shell
   */
  public function getObjectForFunctionExecution($function) : ?\Seolan\Core\ISec{
    if ($this->secGroups($function, null) !== false){
      return $this;
    }
    return null;
  }
  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['index']=array('none','ro','rw','rwv','admin');
    $g['menu']=array('none','ro','rw','rwv','admin');
    $g['rssNews']=array('none','ro','rw','rwv','admin');
    $g['surchargecss']=array('none','ro','rw','rwv','admin');
    $g['savecss']=array('none','ro','rw','rwv','admin');

    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      else return $g[$function];
    }
    return parent::secGroups($function, $group);
  }
  function secList() {
    return array('none');
  }
  function surchargeCss($ar){
    return true;
  }
  function getInfoTree(){
    if($this->_infoTree == NULL){
      $modinfotree = \Seolan\Core\Ini::get('corailv3_xmodinfotree');
      $this->_infoTree = \Seolan\Core\Module\Module::objectFactory($modinfotree);
    }
    return $this->_infoTree;
  }
  function getCharte(){
    if($this->_charte == NULL){
      $modcharte = \Seolan\Core\Ini::get('moidcharte');
      $charte = \Seolan\Core\Module\Module::objectFactory($modcharte);
      $linkOptions = array('target_fields' => array('title', 'alias'));
      $options = array('RUB_ACTUS' => $linkOptions,
		       'RUB_PLUSINFO' => $linkOptions,
		       'RUB_NEWSLETTER' => $linkOptions,
		       'RUB_PARTENAIRES' => $linkOptions,
		       'RUB_HOME' => $linkOptions
		       );
      $this->_charte = $charte->display(array( '_local'=>true,
					       'options' => $options,
					       'tplentry'=>TZR_RETURN_DATA,
					       'selectedfields'=>'all'));
    }
    return $this->_charte;
  }

  function index($ar=NULL) {
    $param = new \Seolan\Core\Param($ar, array());

    $modinfoplus=\Seolan\Core\Ini::get('corailv3_infoplus');
    $modpartenaires=\Seolan\Core\Ini::get('corailv3_partenaires');
    $modagenda=\Seolan\Core\Ini::get('corailv3_agenda');
    $modnewsletter=\Seolan\Core\Ini::get('CorailNewsLetter');

    $xcharte = $this->getCharte();
    \Seolan\Core\Shell::toScreen1('charte',$xcharte);

    $home = $xcharte['oRUB_HOME']->link['oalias']->raw;
    $top = \Seolan\Core\Ini::get('corailv3_top');
    $bot = \Seolan\Core\Ini::get('corailv3_bottom');
    $error = \Seolan\Core\Ini::get('corailv3_error');

    $xmodinfotree = $this->getInfoTree();

    $ar['defaultalias'] = $home;
    $ar['erroralias'] = $error;
    $ar['tplentry']='it';

    // calcul de la page (contenu)
    $page = $xmodinfotree->viewpage($ar);

    // calcul du menu top
    //$tm = $xmodinfotree->home(array('tplentry'=>'tm', 'aliastop'=>$top, "maxlevel"=>3, 'do'=>'showtree'));
    $xmodinfotree->getTreeMenu(array('tplentry'=>'topmenu', 'aliastop'=>$top, "maxlevel"=>4,'linkinfollow'=>false));

    // calcul du menu bas
    $bm = $xmodinfotree->home(array('tplentry'=>'bm', 'aliastop'=>$bot, "maxlevel"=>1));

    // calcul du chemin d'acces a la page, de la feuille de style
    $path=$xmodinfotree->getPath(array('oid'=>$page['oidit'],'tplentry'=>'path'));
    foreach($path['stack'] as $i=>&$node) {
      if(!empty($node['ostyle']->raw)) $style=$node['ostyle']->raw;
    }
    $tabstyle = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS=STYLES');
    if(!isset($style)){
        $brstyle = $tabstyle->browse(array());
        $style = $brstyle['lines_oid'][0];
    }
    $tabstyle = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS=STYLES');
    $ostyle = $tabstyle->rDisplay($style);

    // calcul du menu gauche
    // on regarde si on est sous le top menu, sous le bottom menu
    $idx = array_search($bot, $path['aliasdown']);
    if(empty($idx)) $idx = array_search($top, $path['aliasdown']);

    $xmodinfotree->home(array('tplentry'=>'lm', 'aliastop'=>$path['aliasdown'][$idx+1],
			      'level'=>1, 'maxlevel'=>3, 'do'=>'showtree'));

    \Seolan\Core\Shell::toScreen1('path',$path);
    \Seolan\Core\Shell::toScreen2('general','style', $ostyle['ostyle']->raw);
    \Seolan\Core\Shell::toScreen2('general','tpl', $ostyle['otpl']->raw);
    \Seolan\Core\Shell::toScreen2('general','minisite',$GLOBALS['minisite']);


    $langs=array();
    foreach($GLOBALS['TZR_LANGUAGES'] as $i => $ii) {
      if($xcharte['o'.$i]->raw==1) {
	       $langs[]=$i;
      }
    }
    \Seolan\Core\Shell::toScreen2('general','langs', $langs);

    // calcul des news
    if(!empty($modinfoplus) && \Seolan\Core\Module\Module::moduleExists($modinfoplus)) {
      $today = getdate();
      $today = date('Y-m-d');
      $xmodinfoplus = \Seolan\Core\Module\Module::objectFactory($modinfoplus);
      $cond1=[];
      if($xmodinfoplus->xset->fieldExists('F0004') && $xmodinfoplus->xset->fieldExists('F0005')){
          $cond1 = array("F0004"=>array("<=","$today"),
          "F0005"=>array(">=","$today"));
      }
      if($xmodinfoplus->xset->fieldExists('rubq'))
          $cond1['rubq']=array("=",$path['oiddown']);

      $cond = $xmodinfoplus->xset->select_query(array("cond"=>$cond1));
      $xmodinfoplus->browse(array('tplentry'=>'ip','selectedfields'=>'all','first'=>'0','select'=>$cond));
    }

    // calcul des partenaires
    if(!empty($modpartenaires) && \Seolan\Core\Module\Module::moduleExists($modpartenaires)) {
      $xmodpartenaires = \Seolan\Core\Module\Module::objectFactory($modpartenaires);

      $xmodpartenaires->browse(array('tplentry'=>'pa','selectedfields'=>'all','first'=>'0','pagesize'=>'99'));
    }

    // calcul agenda - 4 en colonnes maxi
    if(!empty($modagenda) && \Seolan\Core\Module\Module::moduleExists($modagenda)) {

      $xmodagenda = \Seolan\Core\Module\Module::objectFactory($modagenda);

      // Order by + length
      $first = 0;
      $order = 'start ASC, title ASC';
      $filters = $param->get('sectionopts');
      foreach($filters as $filtersDetail){
        if(isset($filtersDetail['first'])){
          $first = $filtersDetail['first'];
        }
        if(isset($filtersDetail['order'])){
          $order = $filtersDetail['order'];
        }
      }

      // Filtres
      $today = date('Y-m-d');
      $cond1=[];
      if($xmodagenda->xset->fieldExists('start') && $xmodagenda->xset->fieldExists('end')){
          $cond1 = array("end"=>array(">=","$today"));
      }
      $cond = $xmodagenda->xset->select_query(array("cond"=>$cond1));

      $xmodagenda->browse(
        array('tplentry'=>'ag','selectedfields'=>'all','first'=>$first,'pagesize'=>'4','select' => $cond,'order' => $order)
      );
    }

    // Newsletter
    if($modnewsletter) {
      $xmodnewsletter=\Seolan\Core\Module\Module::objectFactory($modnewsletter);
      $xmodnewsletter->insert(array('options'=>array('email'=>array('labelin'=>true)),'tplentry'=>'frmnewsletter'));
    }

  }
  function menu($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array('maxlevel'=>4));
    $xmodinfotree = $this->getInfoTree();

    $top=\Seolan\Core\Ini::get('corailv3_top');
    $bot=\Seolan\Core\Ini::get('corailv3_bottom');


    $aliastop=$p->get('aliastop');
    if(empty($top)) $top='site';
    $oidtop=$p->get('oidtop');
    if(empty($aliastop) && empty($oidtop)) {
      $aliastop=$top;
    }
    $maxlevel=$p->get('maxlevel');
    $xmodinfotree->home(array('tplentry'=>'menu','maxlevel'=>$maxlevel,'level'=>1,
			     'do'=>'showtree','oidtop'=>$oidtop,'aliastop'=>$aliastop));
    if(!empty($bot)){
      $xmodinfotree->home(array('tplentry'=>'menubot','maxlevel'=>$maxlevel,'level'=>1,
				'do'=>'showtree','oidtop'=>$oidtop,'aliastop'=>$bot));
    }
  }
  public function rssNews($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $modinfoplus=\Seolan\Core\Ini::get('corailv3_infoplus');
    if(!empty($modinfoplus) && \Seolan\Core\Module\Module::moduleExists($modinfoplus)) {
      $today = getdate();
      $today = date('Y-m-d');
      $xmodinfoplus = \Seolan\Core\Module\Module::objectFactory($modinfoplus);
      $cond = $xmodinfoplus->xset->select_query(array("cond"=>array(
								    "F0004"=>array("<=","$today"),
								    "F0005"=>array(">=","$today")
	       							    )
						      )
						);
      $xmodinfoplus->browse(array('tplentry'=>'rss','pagesize'=>'10','selectedfields'=>'all','first'=>'0','select'=>$cond,'order'=>'UPD desc'));
    }
  }
  function savecss($ar) {
    $params = new \Seolan\Core\Param($ar,array());
    $cssextracted = $params->get('cssextracted');
    if($cssEditorPack = $GLOBALS['TZR_PACKS']->getNamedPack('csseditor')){
      $savedCss = $cssEditorPack->saveCss($cssextracted);
    }

    header("Content-type: text/css");
    die($savedCss);
  }
  /////////////////////// interface rewriting ////////////////////////
  /**
   * objet pour le rewriting
   */
  public function getRewriter() : ?\Seolan\Core\IRewriting{
    return $this;
  }
  /**
   * fonctions d'encodage et decodage de \Seolan\Core\IRewriting
   * l'encodage par defaut suffit
   */
  public function encodeRewriting(&$html) {
     $GLOBALS['XSHELL']->encodeRewriting($html);
  }
  protected function decodeRewritingMapping($url){
      global $TZR_LANGUAGES;
      foreach($TZR_LANGUAGES as $k=>$v){
          $url1["/".$k."_sitemap.xml"]="_lang=".$k."&function=menu&template=Application/MiniSite/public/templates/sitemap.xml";
          $url1["/".$k."_rssnews.xml"]="_lang=".$k."&function=rssNews&template=Application/MiniSite/public/templates/rssnews.xml";
      }
      $url1["/sitemap.xml"]="_lang=".TZR_DEFAULT_LANG."&function=menu&template=Application/MiniSite/public/templates/sitemap.xml";
      $url1["/rssnews.xml"]="_lang=".TZR_DEFAULT_LANG."&function=rssNews&template=Application/MiniSite/public/templates/rssnews.xml";
      return $url1;
  }
  public function decodeRewriting($url) {
      $url1 = $this->decodeRewritingMapping($url);
      if(!empty($url1[$url])) {
          parse_str($url1[$url], $_REQUEST);
          $nurl='index.php?'.$url1[$url];
          $_SERVER['REQUEST_URI']="/".$nurl;
          $GLOBALS['TZR_SELF']='/index.php';
          $_SERVER['SCRIPT_NAME']='/index.php';
          return;
      }
      $GLOBALS['XSHELL']->decodeRewriting($url);
  }
}
