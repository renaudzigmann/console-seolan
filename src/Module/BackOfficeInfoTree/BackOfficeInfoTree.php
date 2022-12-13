<?php
namespace Seolan\Module\BackOfficeInfoTree;
/****c* \Seolan\Core\Module\Module/\Seolan\Module\InfoTree\InfoTree
 * NAME
 *   \Seolan\Module\InfoTree\InfoTree -- gestion d'un ensemble de rubriques structurees
 * DESCRIPTION
 *   Module central de gestion d'un site internet, integrant la
 *   gestion de rubriques structurees, le rattachement d'informations
 *   a ces rubriques, ainsi que la creation de requetes.
 * SYNOPSIS
 ****/
/// Module de gestion d'un ensemble de rbriques et de pages web
class BackOfficeInfoTree extends \Seolan\Module\InfoTree\InfoTree {
  static public $upgrades = [];
  static $singleton=true;
  public $insearchengine = false;
  /// construction de la classe gestion de rubriques
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=[];
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      else return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  public function &home($ar=null) {
    $p = new \Seolan\Core\Param($ar);

    $LANG_TRAD = \Seolan\Core\Shell::getLangData($p->get('LANG_USER'));
    if(!empty($LANG_TRAD)) {
      $ar['LANG_TRAD']=$LANG_TRAD;
    }
    return parent::home($ar);
  }
  /// préparation de la visibilité des rubriques du backoffice en fonction de leur contenu
  public function prepareBOTree($user) {

    $this->checkACLStatus($user);
    
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=CS8SEC');
    $m=$this->home(array('maxlevel'=>999,'tplentry'=>TZR_RETURN_DATA,'do'=>'showtree','aliastop'=>$user->botop(),'norubric'=>true));
    foreach($m['lines_oid'] as $i=>$oid) {
      $l=$m['lines_level'][$i];
      $nl=(empty($m['lines_level'][$i+1])?1:$m['lines_level'][$i+1]);
      if($l==1) $levels=$todel=array();
      $levels[$l]=$oid;
      $auth=false;
      // recherche des sections associées à chaque page
      $secs=$x->browse(array('select'=>'select * from CS8SEC left outer join ITCS8 on KOIDDST=KOID where KOIDSRC="'.$oid.'" '.
			     'and (fct is not null and fct!="")','selectedfields'=>array('fct'),'_published'=>'public','tplentry'=>TZR_RETURN_DATA));
      if(!empty($secs['lines_ofct']) && count($secs['lines_ofct'])>0){
	// sur chaque section "module", on décode les paramètres 
	foreach($secs['lines_ofct'] as $fct){
	  parse_str($fct->raw,$params);
	  if(empty($params['moid']) || (empty($params['_function']) && empty($params['function']))) continue;
	  $mod2=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$params['moid'],'tplentry'=>TZR_RETURN_DATA));
	  if(empty($mod2)) {
	    bugWarning('could not build module '.$params['moid'], false, false);
	    break;
	  }
	  // cas Media::browseCollection, Media::procQuery
	  $collections = array_filter($params['oidcoll'] ?? $params['collection'] ?? []);
	  if ($collections && $mod2->collectionmod->object_sec) {
	    $_auth = false;
	    foreach ($collections as $oidCollection) {
	      $_auth |= $mod2->collectionmod->secureNotEmpty($oidCollection, ':ro');
	    }
	    $auth = $_auth;
	  } else {
	    // est ce que la section est authorisée ?
	    $auth=$mod2->secureNotEmpty($params['oid'] ?? $params['oidcoll'][0] ?? '',(!empty($params['_function'])?$params['_function']:$params['function']));
	  }
	  // s'il y a au moins une section visible, alors la rubrique du BO est visible
	  if($auth) break;
	}
      }else{
	$auth=getDB()->count("SELECT COUNT(*) FROM ITCS8 WHERE KOIDSRC=?", array($oid));
      }
      if(!$auth){
	$todel[$oid]=1;
	if($l>=$nl){
	  for($j=$l;$j>=$nl;$j--){
	    if(isset($todel[$levels[$j]])) $GLOBALS['XUSER']->setUserAccess(null,$this->_moid,'all',$levels[$j],':list',null,true,true,\Seolan\Core\Session::CS8_EMPTY_CHECK);
	    else break;
	  }
	}
      }else{
	$todel=array();
      }
    }
  }
  /**
   * effacer le cache des droits pour cet utilisateur si trop ancien
   * par rapport au user (ex : on lui a ajouté un groupe)
   * aux droits posés sur les groupes auxquels il appartient
   * Tous modules confondus : les droits sur les modules déterminent
   * la visibilité des sections fonctions
   */
  protected function checkACLStatus($user){

    $purge = 0;
    
    $cacheupd = getDB()->fetchOne('SELECT min(UPD) FROM ACL4_CACHE WHERE AGRP=?', [$user->_curoid]);

    if (empty($cacheupd))
      return;
    
    if ($user->_cur['UPD'] >= $cacheupd){
      $purge = 1;
    } else {
      $groups = $user->groups(true);
      $ingroups = implode(',', array_fill(0,count($groups), '?'));
      $aclupd = getDB()->fetchOne("select max(upd) from ACL4 where agrp in  ($ingroups) ", $groups);
      if ($aclupd >= $cacheupd){
	$purge = 2;
      }
    }
    \Seolan\Core\Logs::debug(__METHOD__." purge {$purge} user upd :'{$user->_cur['UPD']}', acl max(upd) : '{$aclupd}' cache max(upd) : '{$cacheupd}'");
    if ($purge !== 0){
      getDB()->execute('DELETE FROM ACL4_CACHE WHERE AGRP=?', [$user->_curoid]);
    }
  }
}
