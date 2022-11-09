<?php
namespace Seolan\Module\Wall;
/**
 * Module\Wall
 * @todo :
 * - goto1 (recherche / tag => fait un display ...)
 * 
 */
class Wall extends \Seolan\Module\Table\Table {
  protected $replyto = false;
  protected $publishedmode = false;
  static protected $publisherFields = ['fullnam', 'logo'];
  static protected $iconcssclass='csico-communication-01';

  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Wall_Wall');
    if ($this->xset->fieldExists('replyto')){
      $this->replyto = true;
    }
    if ($this->xset->fieldExists('PUBLISH')){
      $this->publishedmode = true;
    }
  }
  /**
   * goto1 <- recherches par exemple
   */
  function goto1($ar=null){
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(false,true);
    $moid=$this->_moid;
    $right= $this->secure($oid, 'display');
    if(!$right) \Seolan\Library\Security::warning(__METHOD__.' could not access to objet '.$oid.' in module '.$moid);
    header("Location: {$url}&moid=$moid&template=Module/Wall.displayWall.html&oid=$oid&function=displayWall&tplentry=br");
  }
  /**
   * fonction d'affichage principal 
   * -> liste des post et action sur le post
   * -> filtre par tag
   * -> saisie d'un post
   */
  function displayWall($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, []);
    $tplentry=$p->get('tplentry');
    $before=$p->get('before');
    $currTagValue=null;
    $cond = [];
    // les posts au statut adhoc ou mes posts quelque soit leur statut
    if ($this->publishedmode){
      $where = ''; 
      $where .= '(PUBLISH = 1 OR (OWN = "'.$GLOBALS['XUSER']->uid().'"))';
    }
    if ($p->is_set('tag')) {
      $currTagValue = $p->get('tag');
      $cond['TAG']=['like', '%'.\Seolan\Field\Tag\Tag::$TAG_PREFIX.$currTagValue.'%'];
    }
    // on filtre les post qui ne sont que des réponses 
    if ($this->replyto){
      $cond['replyto'] = ['=',''];
    }
    $ar['select']=$this->xset->select_query(['where'=>($where??null), 
					     'cond'=>$cond]);
    // aller à l'oid demandé si transmis (goto1 par exemple)
    if ($p->is_set('oid')){
      $seloid = $this->xset->select_query(['fields'=>[''], // pour avoir KOID sel
					   'where'=>($where??null), 
					   'cond'=>$cond,
					   'order'=>'UPD desc']);
      $oids = getDB()->fetchCol($seloid);
      $pos = array_search($p->get('oid'), $oids);
      if ($pos !== false){
	$_REQUEST['first'] = floor($pos/10)*10;
      }
    }
    $ar['selectedfields']='all';
    $options = $ar['options']??[];
    if (!isset($options['OWN'])){
      $options['OWN']=['target_fields'=>static::$publisherFields];
    } else if(!isset($options['OWN']['target_fields'])){
      $options['OWN']['target_fields']=static::$publisherFields;
    }
    $ar['options'] = $options;
    $ar['order'] = 'UPD desc';
    $ar['tplentry'] = TZR_RETURN_DATA;

    $br = $this->browse($ar);
    
    $br['lines__actions'] = [];
    $br['lines__responses'] = [];
    // @todo : voir les actions issues du browse ?
    foreach($br['lines_oid'] as $l=>$oid){
        $br['lines__actions'][$l] = [];
        $this->setItemActions($br['lines__actions'][$l], $oid, $br['lines_oOWN'][$l]->raw, $this->publishedmode?$br['lines_oPUBLISH'][$l]->raw:null);
	if ($this->replyto){
	  $br['lines__responses'][$l] = $this->getResponses($oid);
	}
    }
    $mainurl = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=displayWall&template=Module/Wall.displayWall.html&tplentry=br';
    $tagparam = '&tag=%s';
    $tagfield = new \Seolan\Field\Tag\Tag();

    // mise en forme dédiée des tags => recherche dans ce module
    $br['lines__mtags'] = [];
    if(is_array($br['lines_oTAG'])) {
      $titleone = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Wall_Wall','only_this_tag');
      $titleall = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Wall_Wall','all_tags');
      foreach ($br['lines_oTAG'] as $l=>$rawtags) {
	$tags = $tagfield->format_tags($rawtags->raw,$mainurl,
				       $titleone,
				       NULL,
				       $tagparam,
				       $currTagValue,
				       $titleall);
	$br['lines__mtags'][$l] = $tags;
      }
    }

    // url pagination auto page suivante
    if ($br['first'] < $br['firstlastpage']) {
      $more_url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=displayWall&template=Module/Wall.displayMoreWall.html&tplentry=br&_skip=1';
      if ($tag) {
        $more_url .= '&tag='.$tag;
      }
      $more_url .= '&first='.$br['firstnext'];
      $br['more_url'] = $more_url;
    }
    // réponse possible
    if ($this->replyto && $this->secure('', 'replyto')){
      $br['replyto'] = [];
    } else {
      $br['replyto'] = false;
    }
    // ajout des champs pour insertion rapide
    if ($this->secure('', 'share')){
      $this->share(['tplentry'=>'new']);
    }

    \Seolan\Core\Shell::toScreen1($tplentry,$br);

  }
  /**
   * lecture des réponse à un post donnée
   */
  function browseResponses($ar=null){
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>'br']);
    $oid = $p->get('oid');
    $tplentry = $p->get('tplentry');
    
    $r = $this->browse(['select'=>$this->xset->select_query(['cond'=>['replyto'=>['=', $oid]]]),
			'options'=>['OWN'=>['target_fields'=>['logo','fullnam']]],
			'selectedfields'=>'all',
			'tplentry'=>TZR_RETURN_DATA,
			'pagesize'=>999]);
    $r['_post'] = $this->xset->rdisplay($oid);
    \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }
  /**
   * recupère les réponses associées à un post
   */
  function getResponses($oid){
    return getDB()->fetchOne('select count(*) koid from '.$this->table.' where replyto=?', [$oid]);
  }
  /**
   * ajoute les actions unitaires possibles 
   * un admin a les même droits que l'auteur
   * -> devrait s'obtenir en surchargeant secure() lorsque un oid est passé :
   * si !oid return parent
   * sinon si lire own et traiter
   */
  protected function setItemActions(array &$actions, string $oid, string $own, string $publish=null){
    // droits d'écriture requis cependant
    if (($this->secure('', ':rw') && $GLOBALS['XUSER']->uid() == $own) || $this->secure('', ':admin')) {
          $action = array();
          $action['url'] = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=deleteInfo&oid='.$oid;
          $action['title'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete');
          $action['text'] = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete');
          $action['confirm'] = 1;
          
          $actions[] = $action;
          
          $action = array();
          $action['url'] = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=modify&tplentry=br&template=Module/Wall.modify.html&oid='.$oid;
          $action['title'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','edit');
          $action['text'] = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit');
          
          $actions[] = $action;
    }
    if ($this->xset->fieldExists('PUBLISH') 
	&& (($this->secure('', ':rwv') && $GLOBALS['XUSER']->uid() == $own) || $this->secure('', ':admin'))) {
      if ($publish && $publish != 1) {
	$action = array();
	$action['url'] = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=publish&oid='.$oid.'&_next='.urlencode($this->getMainAction());
	$action['title'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','approve');
	$action['text'] = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','approved');
	
	$actions[] = $action;
      } else {
	$action = array();
	$action['url'] = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=publish&value=2&oid='.$oid.'&_next='.urlencode($this->getMainAction());
	$action['title'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','unapprove');
	$action['text'] = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','not_approved');
	
	$actions[] = $action;
      }
    }
  }
  /**
   * suppression d'un post 
   * et des réponses si présentes (todo)
   */
  function deleteInfo($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $oid=$p->get('oid');
    $fromun=$p->get('fromun');
    getDB()->execute("delete from ".$this->table." where KOID='".$oid."'");

    $url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=displayWall&tplentry=br&template=Module/Wall.displayWall.html';

    header("Location: $url");
  }
  function share($ar=NULL) {
    return parent::insert($ar);
  }
  function procShare($ar=NULL) {
    $r = parent::procInsert($ar);
    if(empty($_REQUEST['back'])){
      \Seolan\Core\Shell::setNext($this->getMainAction());
    }
    return $r;
  }

  function procModify($ar=NULL) {
    return parent::procEdit($ar);
  }

  function modify($ar=NULL) {
    parent::edit($ar);
  }
  /**
   * enregistremenent d'une réponse
   */
  function procReplyTo($ar=null){
    $p = new \Seolan\Core\Param($ar, []);
    $replytoId = trim($p->get('replyto'));
    $content = trim($p->get('content'));
    if ($this->replyto && !empty($replytoId) && !empty($content)){
      parent::procInsert([
		      '_options'=>['local'=>1],
		      'replyto'=>$replytoId,
		      'content'=>$content
		      ]);
    }
    \Seolan\Core\Shell::setNext($this->getMainAction());
  }

  /**
   * securite des fonctions accessibles par le web
   * @todo : $min -> voir Blog, blog public
   */
  function secGroups($function, $group=NULL) {
    $min=$this->public?'none':'list';
    $g=array('displayWall'=>array('ro','rw','rwv','admin'),
	     'modify'=>array('rw','rwv','admin'),
	     'procShare'=>array('rw','rwv','admin'),
	     'procModify'=>array('rw','rwv','admin'),
	     'deleteInfo'=>array('rw', 'rwv','admin'),
	     'procReplyTo'=>array('rw', 'rwv','admin'),
	     'browseResponses'=>array('ro','rw','rwv','admin'),
	     'share'=>array('rw','rwv','admin'));
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// cette fonction est appliquee pour afficher l'ensemble des methodes de ce module
  protected function _actionlist(&$my, $alfunction=true) {
    // pour recuperer certaines actions
    \Seolan\Core\Module\ModuleWithSourceManagement::_actionlist($my);
    $adminAction = $my['administration'];

    static::_clearActionlist($my);

    $moid=$this->_moid;
    $dir='Module/Wall.';
    $oid=@$_REQUEST['oid'];

    if($this->secure('', 'displayWall')) {
      $o1=new \Seolan\Core\Module\Action($this, 'displayWall', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Wall_Wall','displaywall'),
                                         '&amp;moid='.$this->_moid.
                                         '&amp;_function=displayWall&amp;tplentry=br&amp;'.
                                         'template='.$dir.'displayWall.html');
      $o1->containerable=$o1->menuable=true;
      $o1->setToolbar('Seolan_Core_General','browse');
      $o1->group = 'edit';
      $my['displayWall']=$o1;
      
      if($this->interactive) {
	$my['stack'][]=$my['displayWall'];
      }
      
    }

    if (isset($adminAction)){
        $my['administration']=$adminAction;
    }

  }
  /// on garde subscribe
  function _clearActionlist(&$my){
    $subscribe = null;
    if (isset($my['subscribe']))
      $subscribe = $my['subscribe'];

    parent::_clearActionlist($my);

    if ($subscribe != null)
      $my['subscribe'] = $subscribe;
  }
  /// Action sur la liste
  function al_displayWall(&$my){
    $modsubmoid=\Seolan\Core\Module\Module::getMoid(XMODSUB_TOID);
    if(!empty($modsubmoid)){ 
      $o1=new \Seolan\Core\Module\Action($this, 'subscribe', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Subscription_Subscription','subadd'),
					 '&amoid='.$this->_moid.'&moid='.$modsubmoid.
					 '&_function=preSubscribe&tplentry=br&template=Module/Subscription.sub.html');
      $o1->menuable=true;
      $o1->group='more';
      $my['subscribe']=$o1;
    }
  }
  /// Entrée dans les abonnements : simplification / parent(Module/Table)
  protected function _makeSubEntry($oid, $xset, $details, $ts, $timestamp, $user, $title=NULL) {
    if(empty($xset)) $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('&SPECS='.$oid);
    if(empty($xset)) return '';
    $d=$xset->display(array('_lastupdate'=>true,'tplentry'=>TZR_RETURN_DATA,'tlink'=>true,'oid'=>$oid,'_options'=>array('error'=>'return')));
    $txt = '';
    if(is_array($d) && ($d['lst_upd']['user']!=$user)) {
      $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'&moid='.$this->_moid.'&function=goto1&oid='.$oid.'&_direct=1';
      $txt=<<<EOT
<li>
<a href="${url}">
<div class="wall-publisher text-uppercase">
<span class="wall-name">{$d['oOWN']->link['ofullnam']->html}</span>
<span class="wall-date">{$d['oUPD']->html}</span>
</div>
</a>
EOT;
      if ($details){
	$txt .= "<div class='wall-content'>{$d['ocontent']->html}</div>";
      }
      $txt .= '</li>';
    }
    return $txt;
  }
  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=displayWall&tplentry=br&template=Module/Wall.displayWall.html';
  }

  function getUIFunctionList() {
    $functions = parent::getUIFunctionList();
    $functions['displayWall'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Wall_Wall','displaywall');
    $functions['share'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Wall_Wall','share');
    return $functions; 
  }

  function UIParam_displayWall(){
    $ret = array();
    return $ret;
  }

  /// ...
  function __secure($oid, string $func, $user=NULL, $lang=TZR_DEFAULT_LANG) {
    return parent::secure($oid,$func,$user,$lang);
  }
  
  /// suppression du module
  function delete($ar=NULL) {
    parent::delete($ar);
  }
  
}
?>
