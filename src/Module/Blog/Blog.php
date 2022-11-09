<?php
namespace Seolan\Module\Blog;
// Blog
//
class Blog extends \Seolan\Module\Table\Table {
  /// duree en jours pendant laquelle un event ou un commentaire sont consideres comme recents
  public $recentevents=7;
  public $public=true;
  static protected $iconcssclass='csico-library-chat';
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Blog_Blog');
  }

  /// suppression du module
  function delete($ar=NULL) {
    parent::delete($ar);
  }

  /// corbeille des taches
  public function getTasklet(){
    // liste des entites en attente de validation
    $blogs = array();
    // blogs a valider 
    $query=$this->xset->select_query(array("cond"=>array('PUBLISH'=>array('=',2),
							 'dtype'=>array('=','blog'))));
    $rs=getDB()->select($query);
    while($ors=$rs->fetch()) {
      if (!isset($blogs[$ors['KOID']])){
	$blogs[$ors['KOID']] = array('publish'=>2,'posts'=>array());
      }
    }
    unset($rs); unset($ors);
    // posts a valider
    $query=$this->xset->select_query(array("cond"=>array('PUBLISH'=>array('=',2),
							 'dtype'=>array('=','post'))));
    $rs=getDB()->fetchAll($query);
    foreach($rs as $ors) {
      if (!isset($blogs[$ors['blog']])){
	$blogs[$ors['blog']] = array('publish'=>1,'posts'=>array());
      }
      $blogs[$ors['blog']]['posts'][$ors['KOID']]=array('title'=>$ors['title'], 'publish'=>2, 'comments'=>array());
    }
    unset($rs); 
    // comptage du nombre de commentaires a valider
    $query=$this->xset->select_query(array("cond"=>array('PUBLISH'=>array('=',2),
							 'dtype'=>array('=','comment'))));
    $rs=getDB()->fetchAll($query);
    foreach($rs as $ors) {
      if (!isset($blogs[$ors['blog']])){
	$blogs[$ors['blog']] = array('publish'=>1, 'posts'=>array());
      }
      if (!isset($blogs[$ors['blog']]['posts'][$ors['paperup']])){
	$blogs[$ors['blog']]['posts'][$ors['paperup']] = array('publish'=>1, 'comments'=>array());
      }
      $blogs[$ors['blog']]['posts'][$ors['paperup']]['comments'][$ors['KOID']] = array('publish'=>2, 'title'=>$ors['title']);
    }
    unset($rs); unset($ors);
    $t= '';
    foreach($blogs as $oidblog=>$blog){
      $rdb = $this->xset->rdisplay($oidblog);
      $bt = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Blog_Blog', 'modulename').' : '.$rdb['otitle']->html;
      /*
      if ($blog['publish'] == 2 && $this->secure($oidblog, ':rwv')){
	$url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&function=browseBlog&moid='.$this->_moid.'&oid='.$oidblog.'&template=Module/Blog.browseblog.html&tplentry=br';
	$a1 = '<a class="cv8-ajaxlink" href="'.$url.'">';
	$a2 = '</a>';
	$t .= '<p>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','view').' '.$a1.$rdb['otitle']->html.$a2.'</p>';
      }
      */
      foreach($blog['posts'] as $oidpost=>$post){
	$rdp = $this->xset->rdisplay($oidpost);
	$a1=$a2='';
	if ($post['publish'] == 2 && $this->secure($oidblog, ':rwv')){
	  $url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&function=displayPost&moid='.$this->_moid.'&oid='.$oidblog.'&post='.$oidpost.'&template=Module/Blog.displaypost.html&tplentry=br';
	  $a1 = '<a class="cv8-ajaxlink" href="'.$url.'">';
	  $a2 = '</a>';
	  $t .= '<p>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','view').' '.$a1.$rdp['otitle']->html.$a2.' - '.$bt.'</p>';
	}
	$pt = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Blog_Blog', 'post').' : '.$rdp['otitle']->raw.', '.$bt;
	foreach($post['comments'] as $oidcomment=>$comment){
	  $b1=$b2='';
	  if ($comment['publish'] == 2 && $this->secure($oidcomment, ':rwv')){
	    $url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&function=displayPost&moid='.$this->_moid.'&oid='.$oidblog.'&post='.$oidpost.'&template=Module/Blog.displaypost.html&tplentry=br&comment='.$oidcomment;
	    $b1 = '<a class="cv8-ajaxlink" href="'.$url.'">';
	    $b2 = '</a>';
	    $rdc = $this->xset->rdisplay($oidcomment);
	    $t .= '<p>'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','view').' '.$b1.$rdc['otitle']->raw.$b2.' - '.$pt.'</p>';
	  }
	}
      }
    }
    if (!empty($t)){
      $t = '<h2>En attente de validation</h2>'.$t;
    }
    return $t;
  }

  /// affichage dans la partie page d'accueil de la corbeille de taches
  public function getShortTasklet(){
    // posts a valider
    $urln=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&moid='.$this->_moid.
      '&_function=browseBlogs&tplentry=br&template=Module/Blog.browseblogs.html';
    $url1=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&moid='.$this->_moid.
      '&_function=browseBlog&tplentry=br&template=Module/Blog.browseblog.html';
    $query=$this->xset->select_query(array("cond"=>array('PUBLISH'=>array('=',2),
							 'dtype'=>array('=','post'))));
    $rs=getDB()->select($query);
    $posts=0;
    $blogsposts=array();
    while($rs && ($ors = $rs->fetch())){
      if($this->secure($ors['blog'], ':rwv')) {
	$posts++;$blogsposts[$ors['blog']]=1;
      }
    }
    $t='';

    if($posts>0) {
      if(count($blogsposts)>1) $url=$urln;
      else {
	$tab=array_keys($blogsposts);
	$url=$url1.'&oid='.$tab[0];
      }
      if($posts==1) $t.='<p><a class="cv8-ajaxlink" href="'.$url.'">Un</a> article &agrave; valider</p>';
      elseif($posts>0) $t.='<p><a class="cv8-ajaxlink" href="'.$url.'">'.$posts.'</a> articles &agrave; valider</p>';
    }

    // posts a valider
    $query=$this->xset->select_query(array("cond"=>array('PUBLISH'=>array('=',2),
							 'dtype'=>array('=','comment'))));
    $rs=getDB()->select($query);
    $comments=0;
    $blogscomments=array();
    while($rs && ($ors = $rs->fetch())){
      if($this->secure($ors['blog'], ':rwv')) {
	$comments++;$blogscomments[$ors['blog']]=1;
      }
    }
    if($comments>0) {
      if(count($blogscomments)>1) $url=$urln;
      else {
	$tab=array_keys($blogscomments);
	$url=$url1.'&oid='.$tab[0];
      }
      if($comments==1) $t.='<p><a class="cv8-ajaxlink" href="'.$url.'">Un</a> commentaire &agrave; valider</p>';
      elseif($comments>0) $t.='<p><a class="cv8-ajaxlink" href="'.$url.'">'.$comments.'</a> commentaires &agrave; valider</p>';
    }
    return $t;
  }

  /// liste des articles recents
  function &portlet2() {
    $urln=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&moid='.$this->_moid.
      '&_function=browseBlogs&tplentry=br&template=Module/Blog.browseblogs.html';
    $url1=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&moid='.$this->_moid.
      '&_function=browseBlog&tplentry=br&template=Module/Blog.browseblog.html';
    $query=$this->xset->select_query(array("cond"=>array('PUBLISH'=>array('=',1),
							 'UPD'=>array('>', '=DATE_SUB(NOW(), INTERVAL '.$this->recentevents.' DAY)'),
							 'dtype'=>array('=','post'))));
    $rs=getDB()->fetchAll($query);
    $posts=0;
    $blogsposts=array();
    foreach($rs as $ors) {
      if($this->secure($ors['blog'],':ro')) {
	$posts++;$blogsposts[$ors['blog']]=1;
      }
    }
    unset($rs);
    $query=$this->xset->select_query(array("cond"=>array('PUBLISH'=>array('=',1),
							 'UPD'=>array('>', '=DATE_SUB(NOW(), INTERVAL '.$this->recentevents.' DAY)'),
							 'dtype'=>array('=','comment'))));
    $rs=getDB()->fetchAll($query);
    $comments=0;
    $blogscomments=array();
    foreach($rs as $ors) {
      if($this->secure($ors['blog'],':ro')) {
	$comments++;$blogscomments[$ors['blog']]=1;
      }
    }
    unset($rs);

    if(($comments+$posts)>0) {
      $txt ='<h1>'.$this->getLabel().'</h1>';
      if($posts>0) {
	if(count($blogsposts)>1) $url=$urln;
	else {
	  $tab=array_keys($blogsposts);
	  $url=$url1.'&oid='.$tab[0];
	}
	if($posts==1) $txt.='<p><a class="cv8-ajaxlink" href="'.$url.'">Un</a> nouvel article</p>';
	else $txt.='<p><a class="cv8-ajaxlink" href="'.$url.'">'.$posts.'</a> nouveaux articles</p>';
      }
      if($comments>0) {
	if(count($blogscomments)>1) $url=$urln;
	else {
	  $tab=array_keys($blogscomments);
	  $url=$url1.'&oid='.$tab[0];
	}
	if($comments==1) $txt.='<p><a class="cv8-ajaxlink" href="'.$url.'">Un</a> nouveau commentaire</p>';
	else $txt.='<p><a class="cv8-ajaxlink" href="'.$url.'">'.$comments.'</a> nouveaux commentaires</p>';
      }
    }
    return $txt;
  }
  /// initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Blog_Blog','modulename');
    $this->_options->setOpt('Blog public', 'public', 'boolean', NULL, true, $alabel);
    $this->_options->setOpt('Duree en jours de la nouveaut&eacute;', 'recentevents', 'text', NULL,7, $alabel);
  }
  function display($ar=NULL) {
    return $this->goto1($ar);
  }
  /// recherche des nouveautes pour abonnement
  protected function _whatsNew($ts,$user, $group=NULL, $specs=NULL, $timestamp=NULL) {
    $koid=$specs['oid'];
    $details=$specs['details'];
    if(\Seolan\Core\Kernel::isAKoid($koid)) $query='select * from '.$this->table.' where UPD>="'.$ts.'" and UPD<="'.$timestamp.'" and PUBLISH="1" AND blog="'.$koid.'"';
    else $query='select * from '.$this->table.' where UPD>="'.$ts.'" and UPD<="'.$timestamp.'" and PUBLISH="1"';
    $r=$this->xset->browse(array('select'=>$query, 'selected'=>'0', 'pagesize'=>'99', 'tplentry'=>TZR_RETURN_DATA,'selectedfields'=>'all'));
    $txt='';
    foreach($r['lines_oid'] as $i => $oid) {
      $d1=$this->xset->display(array('_lastupdate'=>true,'tplentry'=>TZR_RETURN_DATA,'oid'=>$oid,'_options'=>array('error'=>'return')));
      $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'&moid='.$this->_moid.'&function=goto1&oid='.$oid.'&tplentry=br&template=Module/Table.view.html&_direct=1';
      if(is_array($d1)) {
	$when=$d1['oUPD']->html;
	$who=$d1['lst_upd']['usernam'];
	$txt.='<li><a href="'.$url.'">'.$d1['oblog']->toText().' : '.getTextFromHTML($d1['link']).'</a> ('.$when.', '.$who.')</li>';
      }
    }
    return $txt;
  }
  function goto1($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $moid=$this->_moid;

    $right= $this->secure($oid, 'displayPost');
    if(!$right) \Seolan\Library\Security::alert('\Seolan\Module\Blog\Blog::goto1: could not access to objet '.$oid.' in module '.$moid);

    $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false);
    $disp=$this->xset->display(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$oid));
    $oidblog=$disp['oblog']->raw;
    $oidpost=$disp['opaperup']->raw;
    $oid=$disp['oid'];
    if($disp['odtype']->raw=='post')
      $durl="{$url}&moid=$moid&template=Module/Blog.displaypost.html&oid={$oidblog}&post={$oid}&function=displayPost&tplentry=br&skip=1";
    if($disp['odtype']->raw=='comment')
      $durl="{$url}&moid=$moid&template=Module/Blog.displaypost.html&oid={$oidblog}&post={$oidpost}&function=displayPost&tplentry=br&skip=1#{$oid}";
    header("Location: $durl");
    exit();
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $min=$this->public?'none':'list';
    $g=array('addBlog'=>array('rwv','admin'),
	     'addComment'=>array('ro','rw','rwv','admin'),
	     'addPost'=>array('rw','rwv','admin'),
	     'addLink'=>array('rw','rwv','admin'),
	     'browseBlog'=>array($min,'ro','rw','rwv','admin'),
	     'browseBlog2'=>array($min,'ro','rw','rwv','admin'),
	     'browseBlogs'=>array($min,'ro','rw','rwv','admin'),
	     'delBlog'=>array('rw','rwv','admin'),
	     'delPost'=>array('rw','rwv','admin'),
	     'delLink'=>array('rw','rwv','admin'),
	     'displayPost'=>array($min,'ro','rw','rwv','admin'),
	     'displayPost2'=>array($min,'ro','rw','rwv','admin'),
	     'editBlog'=>array('rwv','admin'),
	     'editComment'=>array('rw','rwv','admin'),
	     'editPost'=>array('rw','rwv','admin'),
	     'procAddBlog'=>array('rwv','admin'),
	     'procAddComment'=>array($min,'ro','rw','rwv','admin'),
	     'procAddPost'=>array('rw','rwv','admin'),
	     'procAddLink'=>array('rw','rwv','admin'),
	     'procEditBlog'=>array('rwv','admin'),
	     'procEditComment'=>array('rw','rwv','admin'),
	     'procEditPost'=>array('rw','rwv','admin'),
	     'validatePost'=>array('rwv','admin'),
	     'validateBlog'=>array('rwv','admin'));
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// rend vrai si $oid est l'identifiant d'un article/blog/post
  private function isA($oid, $dtype) {
    return (getDB()->count('SELECT COUNT(KOID) FROM '.$this->table.' WHERE KOID="'.$oid.'" AND dtype="'.$dtype.'"')>0);
  }

  /// rend le nombre de blogs
  private function countBlogs() {
    return getDB()->count('SELECT COUNT(KOID) FROM '.$this->table.' WHERE dtype="blog"');
  }

  /// rend l'oid d'un blog
  private function oneBlog() {
    if($ors=getDB()->fetchRow('SELECT KOID FROM '.$this->table.' WHERE dtype="blog"')) return $ors['KOID'];
    return NULL;
  }
  
  /// recherche de la liste des blogs disponibles
  function browseBlogs($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array('order'=>'title'));
    $tplentry=$p->get('tplentry');
    $ar['select']=$this->xset->select_query(array('order'=>'title','cond'=>
						  array('dtype'=>array('=','blog'))));
    $ar['pagesize']=1000;
    $this->browse($ar);
    $br=\Seolan\Core\Shell::from_screen('br');
    foreach($br['lines_oid'] as $i=>$oid) {
      // comptage du nombre de posts
      if ($this->secure($oid, ':rwv'))
	$query=$this->xset->select_query(array("cond"=>array('blog'=>array('=',$oid),
							     'dtype'=>array('=','post'))));
      else 
	$query=$this->xset->select_query(array("cond"=>array('blog'=>array('=', $oid),
							     'PUBLISH'=>array('=', 1),
							     'dtype'=>array('=','post'))));
      $nbposts=getDB()->count($query,array(),true);
      $br['lines_posts'][$i]=$nbposts;

      $br['lines_poststopublish'][$i]=0;
      if ($this->secure($oid, ':rwv')){
	// comptage du nombre de posts a valider
	$query=$this->xset->select_query(array("cond"=>array('blog'=>array('=',$oid),
							     'PUBLISH'=>array('=',2),
							     'dtype'=>array('=','post'))));
	$nbposts=getDB()->count($query,array(),true);
	$br['lines_poststopublish'][$i]=$nbposts;
      }

      // comptage du nombre de commentaires
      if ($this->secure($oid, ':rwv'))
	$query=$this->xset->select_query(array("cond"=>array('blog'=>array('=',$oid),
							     'dtype'=>array('=','comment'))));
      else
	$query=$this->xset->select_query(array("cond"=>array('blog'=>array('=',$oid),
							     'PUBLISH'=>array('=', 1),
							     'dtype'=>array('=','comment'))));
	
      $nbposts=getDB()->count($query,array(),true);
      $br['lines_comments'][$i]=$nbposts;

      // comptage du nombre de commentaires a valider
      $br['lines_commentstopublish'][$i]=0;
      if ($this->secure($oid, ':rwv')){
	$query=$this->xset->select_query(array("cond"=>array('blog'=>array('=',$oid),
							     'PUBLISH'=>array('=',2),
							     'dtype'=>array('=','comment'))));
	$nbposts=getDB()->count($query,array(),true);
	$br['lines_commentstopublish'][$i]=$nbposts;
      }

      // article le plus recent
      $query=$this->xset->select_query(array("cond"=>array('blog'=>array('=',$oid),
							   'dtype'=>array('=','post'))));
      $rs=getDB()->fetchRow(str_replace($this->xset->getTable().'.*', 'max(UPD) as maxupd', $query));
      $br['lines_lastpostdate'][$i]=$rs['maxupd'];
    }
    return \Seolan\Core\Shell::toScreen1('br',$br);
  }
  /// affichage du contenu d'un blog
  // gestion des categorie et archive
  // affichage par défaut des 5 derniers post + list de categ
  // si on passe categ, affichage des post de la categ
  // si on passe mois = YYYYmm on affiche les post du mois
  function browseBlog2($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $oid=$p->get('oid');
    $categ = $p->get('categ');
    $mois = $p->get('mois');

    $right= $this->secure($oid, 'validatePost');
    //info du blog
    $r = $this->xset->display(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$oid));
    \Seolan\Core\Shell::toScreen1($tplentry.'blog',$r);
    // list des posts
    $arsel =array();
    //classer par date decroissant 
    $arsel['order']='datep DESC';
    //selection des post du blog voulu dans br_lines_oid
    $arsel['cond'] = array();
    $arsel['cond']['dtype'] = array('=','post');
    $arsel['cond']['blog'] = array('=',$oid);

    if(!$right) $arsel['cond']['PUBLISH'] = array('=',1);
    
    if($categ){
      $arsel['cond']['categ'] = array('=',$categ);
      $ar['pagesize'] = 9999;
    }elseif($mois){
      $arsel['cond']['datep'] = array('LIKE',"$mois%");
      $ar['pagesize'] = 9999;
    }else $ar['pagesize'] = 5;
    $ar['select']=$this->xset->select_query($arsel);
    $ar['selectedfields']='all';

    $this->xset->browse($ar);
    $br=\Seolan\Core\Shell::from_screen($tplentry);
    foreach($br['lines_oid'] as $i=>$oid2) {
      if($right)
	$query=$this->xset->select_query(array('order'=>'datep ASC',
					       'cond'=>array('blog'=>array('=',$oid),
							     'paperup'=>array('=',$oid2),
							     'dtype'=>array('=','comment'))));
      else
	$query=$this->xset->select_query(array('order'=>'datep ASC',
					       'cond'=>array('blog'=>array('=',$oid),
							     'PUBLISH'=>array('=',1),
							     'paperup'=>array('=',$oid2),
							     'dtype'=>array('=','comment'))));
      // ligne un peu inutile a transformer en count pour avoir le nombre de commentaires
      $br['lines_comments'][$i]=$this->xset->browse(array('tplentry'=>TZR_RETURN_DATA,'select'=>$query, 
							  'pagesize'=>100, 'selectedfields'=>'all'));
    }
    \Seolan\Core\Shell::toScreen1($tplentry,$br);

    // commentaires recents
    $ar=array();
    $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
						  array('dtype'=>array('=','comment'),
							'blog'=>array('=',$oid))));
    $ar['selectedfields']=array('datep','title','blog','paperup');
    $ar['pagesize']=10;
    $ar['tplentry']=$tplentry.'comments';
    $this->xset->browse($ar);

    // Liens
    $ar=array();
    $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
                                                  array('dtype'=>array('=','link'),
                                                        'blog'=>array('=',$oid))));
    $ar['selectedfields']=array('who','title','txt');
    $ar['pagesize']=100;
    $ar['tplentry']=$tplentry.'links';
    $this->xset->browse($ar);
    
    //liste de tous les posts
    $ar=array();
    // list des posts
    $arsel =array();
    //classer par date decroissant 
    $arsel['order']='datep DESC';

    //selection detous les post  brpost_lines_oid
    $arsel['cond'] = array();
    $arsel['cond']['dtype'] = array('=','post');
    $arsel['cond']['blog'] = array('=',$oid);

    $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
						  array('dtype'=>array('=','post'),
							'blog'=>array('=',$oid))));
    $ar['selectedfields']=array('title','datep','categ');
    $ar['pagesize']=9999;
    $ar['tplentry']=$tplentry.'posts';
    $brpost = $this->xset->browse($ar);

    //construction du tableau des categ et archive
    $tbcateg = array_unique($brpost['lines_categ']); 
    sort($tbcateg,SORT_STRING);
    $tb['categ'] = $tbcateg;
    $tbodatep = $brpost['lines_odatep'];
    foreach($tbodatep as $k=>$v){
      $tbdatep[$k] =       date('Y-m',strtotime($v->raw));
    }
    $tbdatep = array_unique($tbdatep);
    sort($tbdatep);
    $tb['datep'] = $tbdatep;
    \Seolan\Core\Shell::toScreen1($tplentry."classement",$tb);
  }



  /// rend les infos fondamentales sur le blog : titre, auteur, etc
  private function _getBlogTitle($oid) {
    return $this->xset->rDisplay($oid);
  }
  /// rend les infos fondamentales sur le post : titre, auteur, etc
  private function _getPostTitle($oid) {
    return $this->xset->rDisplay($oid);
  }
  /// rend les infos fondamentales sur le commentaire : titre, auteur, etc
  private function _getCommentTitle($oid) {
    return $this->xset->rDisplay($oid);
  }
  /// affichage du contenu d'un blog
  function browseBlog($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $oid=$p->get('oid');

    $right= $this->secure($oid, 'validatePost');

    $r = $this->xset->display(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$oid));
    \Seolan\Core\Shell::toScreen1($tplentry.'blog',$r);
    if($right)
      $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
						    array('dtype'=>array('=','post'),
							  'blog'=>array('=',$oid))));
    else
      $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
						    array('dtype'=>array('=','post'),
							  'PUBLISH'=>array('=',1),
							  'blog'=>array('=',$oid))));
    $ar['selectedfields']='all';
    $br = $this->xset->browse($ar);
    // calcul des droits sur les objets
    if($this->object_sec){
      $lang_data = \Seolan\Core\Shell::getLangData();
      $br['objects_sec']=$GLOBALS['XUSER']->getObjectsAccess($this, $lang_data, $br['lines_oid']);
    }
    foreach($br['lines_oid'] as $i=>$oid2) {
      if($right)
	$query=$this->xset->select_query(array('order'=>'datep ASC',
					       'cond'=>array('blog'=>array('=',$oid),
							     'paperup'=>array('=',$oid2),
							     'dtype'=>array('=','comment'))));
      else
	$query=$this->xset->select_query(array('order'=>'datep ASC',
					       'cond'=>array('blog'=>array('=',$oid),
							     'PUBLISH'=>array('=',1),
							     'paperup'=>array('=',$oid2),
							     'dtype'=>array('=','comment'))));
      // ligne un peu inutile a transformer en count pour avoir le nombre de commentaires
      $br['lines_comments'][$i]=$this->xset->browse(array('tplentry'=>TZR_RETURN_DATA,'select'=>$query, 
							  'pagesize'=>100, 'selectedfields'=>'all'));
    }
    \Seolan\Core\Shell::toScreen1($tplentry,$br);
    $ar=array();
    $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
						  array('dtype'=>array('=','post'),
							'blog'=>array('=',$oid))));
    $ar['selectedfields']=array('datep','title');
    $ar['pagesize']=100;
    $ar['tplentry']=$tplentry.'posts';
    $this->xset->browse($ar);

    // commentaires recents
    $ar=array();
    $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
						  array('dtype'=>array('=','comment'),
							'blog'=>array('=',$oid))));
    $ar['selectedfields']=array('datep','title','blog','paperup');
    $ar['pagesize']=100;
    $ar['tplentry']=$tplentry.'comments';
    $this->xset->browse($ar);

    // Liens
    $ar=array();
    $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
                                                  array('dtype'=>array('=','link'),
                                                        'blog'=>array('=',$oid))));
    $ar['selectedfields']=array('who','title','txt');
    $ar['pagesize']=100;
    $ar['tplentry']=$tplentry.'links';
    $this->xset->browse($ar);
  }
  /// affichage d'un article
  function displayPost2($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $oid=$p->get('oid');
    $post=$p->get('post');

    //display du blog
    $r = $this->xset->display(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$oid));
    \Seolan\Core\Shell::toScreen1($tplentry.'blog',$r);
    //display du post
    $r = $this->xset->display(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$post));
    \Seolan\Core\Shell::toScreen1($tplentry.'post',$r);
    $right= $this->secure($oid, 'validatePost');
    if($right)
      $query=$this->xset->select_query(array('order'=>'datep ASC',
					     'cond'=>array('blog'=>array('=',$oid),
							   'paperup'=>array('=',$post),
							   'dtype'=>array('=','comment'))));
    else
      $query=$this->xset->select_query(array('order'=>'datep ASC',
					     'cond'=>array('blog'=>array('=',$oid),
							   'paperup'=>array('=',$post),
							   'PUBLISH'=>array('=',1),
							   'dtype'=>array('=','comment'))));

    //commentaire du post
    $this->xset->browse(array('tplentry'=>$tplentry.'comment','select'=>$query, 
			      'pagesize'=>100, 'selectedfields'=>'all'));
    $this->addComment(array('tplentry'=>$tplentry.'add'));	

    // commentaires recents
    $ar=array();
    $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
						  array('dtype'=>array('=','comment'),'blog'=>array('=',$oid))));
    $ar['selectedfields']=array('datep','title','blog','paperup');
    $ar['pagesize']=10;
    $ar['tplentry']=$tplentry.'comments';
    \Seolan\Module\Table\Table::browse($ar);
    //liste de tous les posts
    $ar=array();
    // list des posts
    $arsel =array();
    //classer par date decroissant 
    $arsel['order']='datep DESC';

    //selection de tous les posts  brposts_lines_oid
    $arsel['cond'] = array();
    $arsel['cond']['dtype'] = array('=','post');
    $arsel['cond']['blog'] = array('=',$oid);

    $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
						  array('dtype'=>array('=','post'),
							'blog'=>array('=',$oid))));
    $ar['selectedfields']=array('title','datep','categ');
    $ar['pagesize']=9999;
    $ar['tplentry']=$tplentry.'posts';
    $brpost = $this->xset->browse($ar);

    //construction du tableau des categ et archive (? V7 )
        $tbcateg = array_unique($brpost['lines_ocateg']); 
    sort($tbcateg,SORT_STRING);
    $tb['categ'] = $tbcateg;
    $tbodatep = $brpost['lines_odatep'];

    foreach($tbodatep as $k=>$v){
      $tbdatep[$k] =       date('Y-m',strtotime($v->raw));
    }
    $tbdatep = array_unique($tbdatep);
    sort($tbdatep);
    $tb['datep'] = $tbdatep;
    \Seolan\Core\Shell::toScreen1($tplentry."classement",$tb);

  }
  /// affichage d'un article
  function displayPost($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $oid=$p->get('oid');
    $post=$p->get('post');

    $r = $this->xset->display(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$oid));
    \Seolan\Core\Shell::toScreen1($tplentry.'blog',$r);
    $r = $this->xset->display(array('tplentry'=>TZR_RETURN_DATA, 'oid'=>$post));
    if ($this->object_sec){
      $lang_data = \Seolan\Core\Shell::getLangData();
      $oids = array($post);
      list($r['object_sec']) = $GLOBALS['XUSER']->getObjectsAccess($this, $lang_data, $oids);
    }
    \Seolan\Core\Shell::toScreen1($tplentry.'post',$r);
    $right= $this->secure($oid, 'validatePost');
    if($right)
      $query=$this->xset->select_query(array('order'=>'datep ASC',
					     'cond'=>array('blog'=>array('=',$oid),
							   'paperup'=>array('=',$post),
							   'dtype'=>array('=','comment'))));
    else
      $query=$this->xset->select_query(array('order'=>'datep ASC',
					     'cond'=>array('blog'=>array('=',$oid),
							   'paperup'=>array('=',$post),
							   'PUBLISH'=>array('=',1),
							   'dtype'=>array('=','comment'))));

    $this->xset->browse(array('tplentry'=>$tplentry.'comment','select'=>$query, 
			      'pagesize'=>100, 'selectedfields'=>'all'));
    $this->addComment(array('tplentry'=>$tplentry.'add'));	

    $ar=array();
    $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
						  array('dtype'=>array('=','post'),
							'blog'=>array('=',$oid))));
    $ar['selectedfields']=array('datep','title');
    $ar['pagesize']=100;
    $ar['tplentry']=$tplentry.'posts';
    \Seolan\Module\Table\Table::browse($ar);
    // commentaires recents
    $ar=array();
    $ar['select']=$this->xset->select_query(array('order'=>'datep DESC','cond'=>
						  array('dtype'=>array('=','comment'),'blog'=>array('=',$oid))));
    $ar['selectedfields']=array('datep','title','blog','paperup');
    $ar['pagesize']=10;
    $ar['tplentry']=$tplentry.'comments';
    \Seolan\Module\Table\Table::browse($ar);
  }

  /// suppression d'un article et tous les commentaires associes
  function delPost($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $post=$p->get('post');
    getDB()->execute("delete from {$this->table} where KOID=? OR paperup=?", [$post, $post]);
  }
  /// validation d'un blog
  function validateBlog($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $oid=$p->get('oid');
    $this->publish(array('oid'=>$oid));
  }
  /// validation d'un article ou d'un commentaire
  function validatePost($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $post=$p->get('post');
    $this->publish(array('oid'=>$post));
  }


  function addComment($ar=NULL) {
    parent::insert($ar);
  }

  /// ajout d'un commentaire sur un article.
  function procAddComment($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $ar['dtype']='comment';
    $ar['blog']=$p->get('oid');
    $ar['paperup']=$p->get('post');
    $ar['datep']=date('Y-m-d H:i:s');
    if(!\Seolan\Core\User::isNobody()) {
      $ar['whoa'] = \Seolan\Core\User::get_current_user_uid();
    }
    parent::procInsert($ar);
  }

  /// ajout d'un blog dans la liste des blogs
  function addBlog($ar) {
    parent::insert($ar);
  }

  /// ajout d'un blog dans la liste des blogs
  function procAddBlog($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $ar['dtype']='blog';
    $ar['datep']=date('Y-m-d H:i:s');
    parent::procInsert($ar);
  }
  /// generation de l'ecran des proproetes d'un blog
  function editBlog($ar) {
    parent::edit($ar);
  }
  /// modifications d'un blog
  function procEditBlog($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    parent::procEdit($ar);
  }

  /// suppression d'un blog
  function delBlog($ar=NULL){
    $p=new \Seolan\Core\Param($ar, array());
    $oid=$p->get('oid');
    $blog=$this->xset->display(array(
				     'oid'=>$oid,
				     'tplentry'=>TZR_RETURN_DATA
				     )
			       );
    if($blog['odtype']->raw=='blog'){
      getDB()->execute("delete from {$this->table} where KOID=? OR blog=?", [$oid, $oid]);
    }
  }

  /// gestion des posts
  function editPost($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $ar['oid']=$p->get('post');
    $ar['options']['blog']['readonly']=2;
    parent::edit($ar);
  }
  function procEditPost($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    parent::procEdit($ar);
  }

  function addPost($ar=NULL) {
    parent::insert($ar);
  }
  function procAddPost($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $ar['dtype']='post';
    $ar['blog']=$p->get('oid');
    $ar['datep']=date('Y-m-d H:i:s');
    $who = $p->get('who');
    if (!empty($who['url'])){
      $ar['who'] = array('label'=>$who['label'],
			 'url'=>'mailto:'.$who['url'],
			 '_target'=>'_blank');
	
    }
    parent::procInsert($ar);
  }

  // gestion des commentaires
  //
  function editComment($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $ar['oid']=$p->get('post');
    parent::edit($ar);
  }
  function procEditComment($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    parent::procEdit($ar);
  }

  /// gestion des liens
  function addLink($ar=NULL) {
    parent::insert($ar);
  }
  function procAddLink($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $ar['dtype']='link';
    $ar['blog']=$p->get('oid');
    $ar['datep']=date('Y-m-d H:i:s');
    parent::procInsert($ar);
  }
  function delLink($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $link=$p->get('link');
    getDB()->execute("delete from {$this->table} where KOID=? AND dtype=?", [$link, "link"]);
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=browseBlogs&tplentry=br&template=Module/Blog.browseblogs.html';
  }

  /// cette fonction est appliquee pour afficher l'ensemble des methodes de ce module
  protected function _actionlist(&$my,$alfunction=true) {
    \Seolan\Core\Module\Module::_actionlist($my);
    parent::_clearActionlist($my);
    $myclass='\Seolan\Module\Blog\Blog';
    $moid=$this->_moid;
    $dir='Module/Blog.';
    $cnt=$this->countBlogs();
    $o1=new \Seolan\Core\Module\Action($this, 'browseBlogs', \Seolan\Core\Labels::getTextSysLabel($myclass,'browseblogs'),
			  '&amp;moid='.$moid.
			  '&amp;_function=browseBlogs&amp;tplentry=br&amp;'.
			  'template='.$dir.'browseblogs.html');
    $o1->containerable=$o1->menuable=true;
    $o1->setToolbar('Seolan_Core_General','browse');
    $my['browse']=$o1;
    $oid=@$_REQUEST['oid'];
    $post=@$_REQUEST['post'];
    if($this->secure('', ':admin')) {
      $o1=new \Seolan\Core\Module\Action($this, 'administration', \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'administration'),
			    '&moid='.$this->_moid.'&function=adminBrowseFields&template=Core/Module.admin/browseFields.html');
      $o1->homepageable = false;
      $o1->menuable=true;
      $o1->quicklinkable=true;
      $o1->group='actions';
      $o1->setToolbar('Seolan_Core_General', 'administration');
      $my['administration']=$o1;
    }
    if($this->secure('', 'addBlog')) {
      $o1=new \Seolan\Core\Module\Action($this, 'addblog', \Seolan\Core\Labels::getTextSysLabel($myclass,'addblog'),
			    '&amp;moid='.$moid.
			    '&amp;_function=addBlog&amp;tplentry=br&amp;'.
			    'template='.$dir.'addblog.html');
      $o1->homepageable=false;
      $o1->menuable=$o1->quicklinkable=true;
      $o1->group='edit';
      $o1->setToolbar('Seolan_Core_General','new');
      $my['insert']=$o1;
    }
    if(!empty($oid)) {
      $o1=new \Seolan\Core\Module\Action($this, 'browseBlog', \Seolan\Core\Labels::getTextSysLabel($myclass,'browseblog'),
			    '&amp;moid='.$moid.
			    '&amp;_function=browseBlog&amp;tplentry=br&amp;oid='.$_REQUEST['oid'].'&amp;'.
			    'template='.$dir.'browseblog.html');
      $o1->homepageable=$o1->menuable=$o1->quicklinkable=true;
      $o1->setToolbar('Seolan_Core_General','view');
      $my['browseblog']=$o1;
      $right= $this->secure($oid, 'addPost');
      $isablog=$this->isA($oid,'blog');
      $isapost=$this->isA($post,'post');
      $isacomment=$this->isA($post,'comment');
      if($isablog  && $right) {
	$o1=new \Seolan\Core\Module\Action($this, 'addpost', \Seolan\Core\Labels::getTextSysLabel($myclass,'addpost'),
			      '&amp;moid='.$moid.
			      '&amp;_function=addPost&amp;tplentry=br&amp;oid='.$oid.'&amp;'.
			      'template='.$dir.'addpost.html');
	$o1->homepageable=$o1->menuable=$o1->quicklinkable=true;
	$o1->group='edit';
	$o1->setToolbar('Seolan_Core_General','new');
	$my['insert']=$o1;
        
        $this->commentActionList($this,$my,$moid,$oid);
      }
      $right= $this->secure($oid, 'addComment');
      if($isapost){
	if($right) {
	  $o1=new \Seolan\Core\Module\Action($this, 'addcomment', \Seolan\Core\Labels::getTextSysLabel($myclass,'addcomment'),
				'&amp;moid='.$moid.
				'&amp;_function=addComment&amp;tplentry=br&amp;oid='.$oid.'&amp;'.
				'&amp;post='.$post.'&amp;template='.$dir.'addcomment.html');
	  $o1->homepageable=$o1->menuable=$o1->quicklinkable=true;
	  $o1->group='edit';
	  $o1->setToolbar('Seolan_Core_General','new');
	  $my['insert']=$o1;
	}
	$right= $this->secure($oid, 'editPost');
	if($right) {
	  $o1=new \Seolan\Core\Module\Action($this, 'edit', \Seolan\Core\Labels::getTextSysLabel($myclass,'editpost'),
				'&amp;moid='.$moid.
				'&amp;_function=editPost&amp;tplentry=br&amp;oid='.$oid.'&amp;'.
				'&amp;post='.$post.'&amp;template='.$dir.'editpost.html');
	  $o1->homepageable=$o1->menuable=$o1->quicklinkable=true;
	  $o1->group='edit';
	  $o1->setToolbar('Seolan_Core_General','edit');
	  $my['edit']=$o1;
	}
      }
    }
    unset($my['query']);

    if($this->interactive) {
      $o1=new \Seolan\Core\Module\Action($this,'browseBlogs',$this->getLabel(),
					 '&moid='.$moid.'&_function=browseBlogs&tplentry=br&template='.$dir.'browseblogs.html');
      $my['stack'][]=$o1;
      if(!empty($oid)) {
	$blog=$this->_getBlogTitle($oid);
	$o1=new \Seolan\Core\Module\Action($this,'browseBlog',$blog['otitle']->toText(),
			      '&moid='.$moid.'&_function=browseBlog&tplentry=br&oid='.$oid.'&template='.$dir.'browseblog.html');
	$my['stack'][]=$o1;
	if($isapost){
	  $postd=$this->_getPostTitle($post);
	  $o1=new \Seolan\Core\Module\Action($this,'displayPost',$postd['otitle']->toText(),
				'&moid='.$moid.'&_function=displayPost&tplentry=br&oid='.$oid.'&post='.$post.
				'&template='.$dir.'displaypost.html');
	  $my['stack'][]=$o1;
	}elseif($isacomment){
	  $com=$this->_getCommentTitle($post);
	  $postd=$this->_getPostTitle($com['opaperup']->raw);
	  $o1=new \Seolan\Core\Module\Action($this,'displayPost',$postd['otitle']->toText(),
				'&moid='.$moid.'&_function=displayPost&tplentry=br&oid='.$oid.'&post='.$postd['oid'].
				'&template='.$dir.'displaypost.html');
	  $my['stack'][]=$o1;
	  $o1=new \Seolan\Core\Module\Action($this,'editComment',$com['otitle']->toText(),
				'&moid='.$moid.'&_function=editComment&tplentry=br&oid='.$oid.'&post='.$com['oid'].
				'&template='.$dir.'editcomment.html');
	  $my['stack'][]=$o1;
	}
      }
      $modsubmoid=\Seolan\Core\Module\Module::getMoid(XMODSUB_TOID);
      if(!empty($modsubmoid)){
	$o1=new \Seolan\Core\Module\Action($this, 'subscribe', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Subscription_Subscription','subadd'),
			      '&amoid='.$this->_moid.'&class=\Seolan\Module\Subscription\Subscription&moid='.$modsubmoid.
			      '&_function=preSubscribe&tplentry=br&template=Module/Subscription.sub.html&aoid='.$oid);
	$o1->menuable=true;
	$o1->group='more';
	$my['subscribe']=$o1;
      }
      $this->resetCommentsActionList($my);
    }
  }
}
?>
