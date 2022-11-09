<?php
/// Classe de gestion des fonctions d'un corail version version CSX
namespace Seolan\Application\Corail;
use \Seolan\Core\Logs;
class Corail extends \Seolan\Core\Application\ConfigurableApplication implements \Seolan\Core\ISec, \Seolan\Core\IRewriting{
  protected static $chartetab = 'CHARTE';
  protected static $stylestab = 'STYLES';
  protected static $defaultTemplate = 'Application/Corail.index.html';
  /**
   * En cas d'url sans moid ni classe, récupère un eventuel objet à utiliser
   */
  function getObjectForFunctionExecution($function) : ?\Seolan\Core\ISec {
      if ($this->secGroups($function, null) !== false){
          return $this;
      }
      return null;
  }
  /**
   * gabarit par défaut en FO
   */
  public function run() {
      Logs::debug(__METHOD__);
      parent::run ();
      if (!TZR_ADMINI){
          Logs::notice(__METHOD__, 'check FO template Request:"'.$_REQUEST ['template'].'",default:"'.TZR_DEFAULT_TEMPLATE.'","class:"'.static::$defaultTemplate.'"');
          if (empty ( $_REQUEST ['template'] ) || $_REQUEST ['template'] == TZR_DEFAULT_TEMPLATE) {
              Logs::notice(__METHOD__, 'set template for FO '.static::$defaultTemplate);
              $_REQUEST ['template'] = static::$defaultTemplate;
          }
          if (\Seolan\Core\Module\Module::moduleExists('', XMODTARTEAUCITRON_TOID)){
            $xmodtarteaucitron = \Seolan\Core\Module\Module::objectFactory(['toid' => XMODTARTEAUCITRON_TOID, '_options' => array('local' => true)]);
            $xmodtarteaucitron->sendScriptsToTemplate($this->oid);
          }
      }
  }
  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['index']=array('none');
    $g['menu']=array('none');
    $g['productDetail']=array('none');
    $g['viewVisio']=array('none');
    $g['addToVisio']=array('none');
    $g['displayPhoto']=array('none');
    $g['addItem']=array('none');
    $g['view']=array('none');
    $g['procOrder']=array('none');
    $g['rssNews']=array('none');

    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      else return $g[$function];
    }
    return false;
  }
  function secList() {
    return array('none');
  }

  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Shell::toScreen2('','corail',$this);
    //$this->set_callback('callback');
    // avoir un domaine qui veut dire qqchose ! incomplet, domain peut-être vide
    if ($this->domain_is_regex == 1 || empty($this->domain)){
      Logs::notice(__METHOD__, "set domain name {$this->domain} -> {$_SERVER['SERVER_NAME']}");
      $this->domain = $_SERVER['SERVER_NAME'];
    }
  }
  function productDetail($ar) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $xmodprod=\Seolan\Core\Module\Module::objectFactory($this->cartproduct);
    $pname ='';
    $ar1['tplentry']='prod';
    $ar1['oid']=$p->get('oidprod');

    $disp = $xmodprod->display($ar1);
    foreach($xmodprod->xset->desc as $fname=>$fdef){
      if( $fdef->get_published() && $fdef->get_ftype() == '\Seolan\Field\ShortText\ShortText' ){
	$pname .= $disp['o'.$fname]->html.' ';
      }
    }
    \Seolan\Core\Shell::toScreen2('page','title', $pname);

    $this->index($ar);
  }

  function callback($ar=NULL) {
  }

  function index($ar=NULL) {
    $xmodinfotree = \Seolan\Core\Module\Module::objectFactory($this->infotree);
    $ar['defaultalias']=$this->infotreehome;
    $ar['authalias']=$this->infotreeauth;
    $ar['erroralias']=$this->infotreerror;
    $ar['tplentry']='it';

    // calcul de la page (contenu)
    $page=$xmodinfotree->viewpage($ar);
    // calcul du menu top
    $tm = $this->getTopMenu($xmodinfotree);
    // calcul du menu general
    $gm=&$xmodinfotree->home(array('tplentry'=>'gm', 'aliastop'=>$this->infotreetop, "maxlevel"=>3, 'do'=>'showtree'));
    // calcul du menu bas
    $bm=&$xmodinfotree->home(array('tplentry'=>'bm', 'aliastop'=>$this->infotreebottom, "maxlevel"=>1));

    // calcul du chemin d'acces a la page
    $path= $this->getPath($xmodinfotree, $page);
    
    // calcul de la mise en page et du gabarit
    $styleOid = null;
    foreach($path['stack'] as $i=>$node) {
      if(!empty($node['ostyle']->raw)) $styleOid=$node['ostyle']->raw;
    }
    $tabstyle = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.static::$stylestab);
    // todo ? : si erreur de conf sur l'alias ou les pages : plante, styleOid n'existant pas
    $ostyle=$tabstyle->rDisplay($styleOid);
    
    if (empty($styleOid) || !is_array($ostyle)){
      Logs::critical(__METHOD__, 'configuration error : no style, no tpl for alias : '.$page['cat_mit']['oalias']->raw.' infotree : '.$xmodinfotree->getLabel());
    }
    // calcul du menu gauche
    // on regarde si on est sous le top menu, sous le bottom menu
    $idx = array_search($this->infotreebottom, $path['aliasdown']);
    if(empty($idx)) $idx = array_search($this->infotreetop, $path['aliasdown']);
    
    $xmodinfotree->home(array('tplentry'=>'lm', 'aliastop'=>$path['aliasdown'][$idx+1], 
			      'level'=>1, 'maxlevel'=>3, 'do'=>'showtree'));

    \Seolan\Core\Shell::toScreen1('path',$path);
    \Seolan\Core\Shell::toScreen1('style', $ostyle);
    \Seolan\Core\Shell::toScreen2('general','style', $ostyle['ostyle']->raw);
    \Seolan\Core\Shell::toScreen2('general','tpl', $ostyle['otpl']->raw);
    if(!empty($this->charte)){
      $charte = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.static::$chartetab);
      $xcharte = &$charte->display(array("tplentry"=>"charte","oid"=>$this->charte,"selectedfields"=>"all"));
    }
    $langs=array();
    foreach($GLOBALS['TZR_LANGUAGES'] as $i => $ii) {
      if(!empty($xcharte['o'.$i]) && $xcharte['o'.$i]->raw==1) {
	$langs[]=$i;
      }
    }
    \Seolan\Core\Shell::toScreen2('general','langs', $langs);

    // calcul des news
    $this->getNews();

    // calcul des partenaires
    $this->getPatners();

    // liens rapides
    $this->getQuickLinks($path);

    // photothèque
    if(!empty($this->photo) && \Seolan\Core\Module\Module::moduleExists($this->photo)){
      $xmodphoto=\Seolan\Core\Module\Module::objectFactory($this->photo);
      //$arqq=getSessionVar('queryobject'.$page['cat_mit']['oid']);
      $arqq['tplentry']='ph';
      $arqq['selectedfields']=explode(',',$this->photofields);
      $xmodphoto->quickquery($arqq);
    }

    // infos photothèque
    if(!empty($this->newsphoto) && \Seolan\Core\Module\Module::moduleExists($this->newsphoto) && $page['cat_mit']['orphoto']->raw==1){
      $xmodinfophoto=\Seolan\Core\Module\Module::objectFactory($this->newsphoto);
      $xmodinfophoto->browse(array('tplentry'=>'iph','pagesize'=>10));
    }

    // Boutique
    if($this->cart) {
      $xmodcart=\Seolan\Core\Module\Module::objectFactory($this->cart);
      $xmodcart->viewShort(array('tplentry'=>'cart'));
    }
    // Newsletter
    if($this->nl) {
      $xmodnewsletter=\Seolan\Core\Module\Module::objectFactory($this->nl);
      $xmodnewsletter->insert(array('tplentry'=>'frmnewsletter'));
    }

  }
  // calcul des lines rapides
  protected function getQuickLinks($path=null){
    if (!isset($this->quicklinks) || !\Seolan\Core\Module\Module::moduleExists($this->quicklinks)){
      return;
    }
    if ($path == null){
      $path = \Seolan\Core\Shell::from_screen('path');
    }
    $xmodlinks = \Seolan\Core\Module\Module::objectFactory($this->quicklinks);
    $condlinks = $xmodlinks->xset->select_query(array("cond"=>array("rubq"=>array("=",$path['oiddown']))));
    $xmodlinks->browse(array('tplentry'=>'ql','selectedfields'=>'all','first'=>'0','select'=>$condlinks));
  }
  public function displayPhoto($ar=NULL){
    $this->index($ar);
    $mod=\Seolan\Core\Module\Module::objectFactory($this->photo);
    $ar['tplentry']=TZR_RETURN_DATA;
    $lines[0]=$mod->display($ar);
    \Seolan\Core\Shell::toScreen2('it','olines',$lines);
  }

  public function addToVisio($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    $ar2=getSessionVar('corailv3_visio');
    if(!in_array($oid,$ar2)){
      $ar2[]=$oid;
      setSessionVar('corailv3_visio',$ar2);
    }
  }

  public function viewVisio($ar=NULL){
    $ar2=getSessionVar('corailv3_visio');
    $br['operator']='OR';
    $br['_select'][]='KOID=""';
    foreach($ar2 as $i=>$oid){
      $br['_select'][]='KOID="'.$oid.'"';
    }
    setSessionVar('queryobject'.$this->infotreephotovisio,$br);
    $ar['keepalive']=1;
    $ar['oidit']=$this->infotreephotovisio;
    $this->index($ar);
  }

  public function view($ar=NULL){
    $this->index($ar);
    $xmodcart=\Seolan\Core\Module\Module::objectFactory($this->cart);
    $xmodcart->view($ar);
  }

  public function procOrder($ar=NULL){
    $xmodcart=\Seolan\Core\Module\Module::objectFactory($this->cart);
    $xmodcart->procOrder($ar);
    //$xmodcart->viewOrder($ar);

    $this->index($ar);
  }

  // rend vrai si l'alias passé en parametre est protege
  //
  public function aliasIsProtected($alias) {
    $prot=array($this->infotreehome,$this->infotreetop,$this->infotreebottom);
    if(in_array($alias,$prot)) return true;
    else return parent::aliasIsProtected($alias);
  }
  function menu($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array('maxlevel'=>4));
    $xmodinfotree = \Seolan\Core\Module\Module::objectFactory($this->infotree);
    $aliastop=$p->get('aliastop');
    if(empty($this->infotreetop)) $this->infotreetop='site';
    $oidtop=$p->get('oidtop');
    if(empty($aliastop) && empty($oidtop)) {
      $aliastop=$this->infotreetop;
    }
    $maxlevel=$p->get('maxlevel');
    $xmodinfotree->home(array('tplentry'=>'menu','maxlevel'=>$maxlevel,'level'=>1,
			     'do'=>'showtree','oidtop'=>$oidtop,'aliastop'=>$aliastop));
    if(!empty($this->infotreebottom)){
      $xmodinfotree->home(array('tplentry'=>'menubot','maxlevel'=>$maxlevel,'level'=>1,
				'do'=>'showtree','oidtop'=>$oidtop,'aliastop'=>$this->infotreebottom));
    }
  }
  public function rssNews($ar){
    $p = new \Seolan\Core\Param($ar, array());
    if(!empty($this->news) && \Seolan\Core\Module\Module::moduleExists($this->news)) {
      $today = getdate();
      $today = date('Y-m-d');
      $xmodinfoplus = \Seolan\Core\Module\Module::objectFactory($this->news);
      $cond = $xmodinfoplus->xset->select_query(array("cond"=>array(
								    "F0004"=>array("<=","$today"),
								    "F0005"=>array(">=","$today")
	       							    )
						      )
						);
      $xmodinfoplus->browse(array('tplentry'=>'rss','pagesize'=>'10','selectedfields'=>'all','first'=>'0','select'=>$cond,'order'=>'UPD desc'));
    }
  }
  /**
   * resolution des gabarits de l'application
   */
  public function getTemplateProtocols() : array{
    return ['application'];
  }
  public function getTemplateResource(string $protocol) : ?\Smarty_Internal_Resource_File{
    if ($protocol == 'application')
      return new \Seolan\Core\ApplicationTemplateResource($this);
    else
      return null;
  } 
  /**
   * objet d'encodage et decodage, '$this' 
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

  /**
   * fonctions d'encodage et decodage de \Seolan\Core\IRewriting
   */
  public function decodeRewriting($url) {
    global $TZR_LANGUAGES;
    foreach($TZR_LANGUAGES as $k=>$v){
        $url1["/".$k."_sitemap.xml"]="_lang=".$k."&function=menu&template=application:sitemap.xml";
        $url1["/".$k."_rssnews.xml"]="_lang=".$k."&function=rssNews&template=application:rssnews.xml";
    } 
    $url1["/sitemap.xml"]="_lang=".TZR_DEFAULT_LANG."&function=menu&template=application:sitemap.xml";
    $url1["/rssnews.xml"]="_lang=".TZR_DEFAULT_LANG."&function=rssNews&template=application:rssnews.xml";
    
    Logs::debug(__METHOD__.'-> '.$url);

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

  protected function getTopMenu($xmodinfotree) {
    if($this->infotreederoule){
      $tm=&$xmodinfotree->home(array('tplentry'=>'tm', 'aliastop'=>$this->infotreetop, "maxlevel"=>3, 'do'=>'showtree'));
    }else{
      $tm=&$xmodinfotree->home(array('tplentry'=>'tm', 'aliastop'=>$this->infotreetop, "maxlevel"=>1));
    }

    return $tm;
  }

  protected function getPath($xmodinfotree, $page) {
    return $xmodinfotree->getPath(array('oid'=>$page['oidit'],'tplentry'=>'path'));
  }

  /**
    * Calcul les news
  **/
  protected function getNews() {
    $path = \Seolan\Core\Shell::from_screen('path');
    if(!empty($this->news) && \Seolan\Core\Module\Module::moduleExists($this->news)) {
      $today = getdate();
      $today = date('Y-m-d');
      $xmodinfoplus = \Seolan\Core\Module\Module::objectFactory($this->news);
      $cond1 = array("F0004"=>array("<=","$today"),
         "F0005"=>array(">=","$today"));
      if($xmodinfoplus->xset->fieldExists('rubq'))
        $cond1['rubq']=array("=",$path['oiddown']);
      
      $cond = $xmodinfoplus->xset->select_query(array("cond"=>$cond1));
      $xmodinfoplus->browse(array('tplentry'=>'ip','selectedfields'=>'all','first'=>'0','select'=>$cond));
    }
  }

  /**
    * Calcul des partenaires
  **/
  protected function getPatners() {
    if(!empty($this->partenaire) && \Seolan\Core\Module\Module::moduleExists($this->partenaire)) {
      $xmodpartenaires = \Seolan\Core\Module\Module::objectFactory($this->partenaire);
      $xmodpartenaires->browse(array('tplentry'=>'pa','selectedfields'=>'all','first'=>'0','pagesize'=>'99'));
    }
  }
  /// retourne la liste des modules pour lesquels les daemons seront appelés dans le contexte appliction
  public function getUsedModules():array{
    $moids = [];
    foreach(['infotree', 'photo', 'partenaire', 'quicklinks', 'news', 'newsphoto'] as $prop){
      if (isset($this->$prop) && is_numeric($this->$prop))
	$moids[] = $this->$prop;
    }
    return $moids;
  }
}
// à voir ?
$GLOBALS['TZR_REWRITING']['myVisio.html']='function=viewVisio';

function corailv3_addItem($sessid,$oid,$q=1,$complement=NULL){
  // Redemmarre une session afin de s'assure d'avoir le bon id
  sessionClose();
  session_id($sessid);
  sessionStart();
  $xmodcart=\Seolan\Core\Module\Module::objectFactory($this->cart);
  if(!empty($complement))
    parse_str($complement,$ar);
  $ar['oid']=$oid;
  $ar['q']=$q;
  $ar['tplentry']=TZR_RETURN_DATA;
  $xmodcart->addItem($ar);
  $q=$xmodcart->viewShort(array('tplentry'=>TZR_RETURN_DATA));
  json_encode(array('code'=>'addOK','q'=>$q));
}
