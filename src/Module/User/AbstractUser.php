<?php
namespace Seolan\Module\User;
/**
 * Classe de base pour les modules de gestion des utilisateurs et des groupes pour les méthodes communes
 */
class AbstractUser extends \Seolan\Module\Table\Table{
  static protected $iconcssclass='csico-users';
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }

  /// Securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array('editSec'=>array('admin'),
	     'procEditSec'=>array('admin'),
             'copyACL'=>array('admin'),
             'procEditDupWithACL'=>array('admin'),
	     'secSummary'=>array('admin'));
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }
  
  function edit($ar) {
    $ret = parent::edit($ar);
    $p = new \Seolan\Core\Param($ar, NULL);
    $oid = $p->get('oid');
    if ($this->secure($oid, 'procEditDupWithACL'))
      \Seolan\Core\Shell::toScreen2('add', 'functions', $r = array(
        'procEditDupWithACL' => \Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','clone_with_acl')
      ));
    return $ret;
  }

  /// Duplication d'un compte utilisateur/group avec copie des droits
  function procEditDupWithACL($ar) {
    $p = new \Seolan\Core\Param($ar, NULL);
    $from = $p->get('oid');
    $ret = $this->procEditDup($ar);
    if (!empty($ret['oid']))
      $this->copyACL(array('from' => $from, 'to' => $ret['oid']));
    return $ret;
  }

  /// Copie des droits
  function copyACL($ar) {
    $p = new \Seolan\Core\Param($ar, NULL);
    $from = $p->get('from');
    $to = $p->get('to');
    if (!empty($from) &&!empty($to)) {
      if ($p->get('clearBefore'))
        getDB()->execute("delete from ACL4 where AGRP='$to'");
      $acls = getDB()->select("select * from ACL4 where AGRP='$from'");
      while ($acl = $acls->fetch()) {
        $acl['AOID'] = substr(md5(uniqid("")), 0, 40);
        $acl['AGRP'] = $to;
        unset($acl['UPD']);
        getDB()->execute('INSERT INTO ACL4('.implode(',', array_keys($acl)).') 
              values("'.implode('","', $acl).'")');
	\Seolan\Core\Logs::secEvent(__METHOD__,"", "Copy rules from $from to $to", $to);
      }
      if ($p->get('withGrp'))
        if ($this->table == 'USERS') {
          $grp = getDB()->select("select GRP from USERS where koid='$from'")->fetch(\PDO::FETCH_COLUMN);
          getDB()->execute("update USERS set GRP='$grp' where koid='$to'");
        } elseif  ($this->table == 'GRP') {
          $grp = getDB()->select("select GRPS from GRP where koid='$from'")->fetch(\PDO::FETCH_COLUMN);
          getDB()->execute("update GRP set GRPS='$grp' where koid='$to'");
        }
    }
  }
  
  /// Generation de l'écran de paramétrage des autorisations
  function editSec($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oids=\Seolan\Core\Kernel::getSelectedOids($p);
    $this->xset->browse(array('tplentry'=>'br','selectedfields'=>array('alias','GRPA','GRP','GRPS'),'cond'=>array('KOID'=>array('=',$oids))));
    \Seolan\Core\Labels::loadLabels('Seolan_Core_Security');
    $r=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA,'templates'=>false));
    $l=\Seolan\Core\Lang::getCodes();
    $r['lines_level']=array();
    $r['lines_fulllevel']=array();
    $levellist=array();
    foreach($r['lines_classname'] as $i => $classname) {
      $r['lines_level'][$i]=array();
      $r['lines_fulllevel'][$i]=array();
      foreach($l['code'] as $j => $lang) {
	if(count($oids)==1){
	  list($ua,$full)=$GLOBALS['XUSER']->getUserAccess($classname,$r['lines_oid'][$i],$lang,'',$oids[0]);
	  $r['lines_level'][$i][$lang]=end($ua);
	  $r['lines_fulllevel'][$i][$lang]=$full;
	}else{
	  $mod=\Seolan\Core\Module\Module::objectFactory($r['lines_oid'][$i]);
	  $r['lines_level'][$i][$lang]='';
	  $r['lines_fulllevel'][$i][$lang]=$mod->secList();
	}
        $levellist=array_merge($levellist,$r['lines_fulllevel'][$i][$lang]);
	array_unshift($r['lines_fulllevel'][$i][$lang],'default');
      }
    }
    $levellist=array_values(array_unique($levellist));
    \Seolan\Core\Shell::toScreen1('seceditlang',$l);
    \Seolan\Core\Shell::toScreen1('seceditmods',$r);
    \Seolan\Core\Shell::toScreen2('seceditlevellist','lines',$levellist);
    // champ pour la copie des ACL
    $link = new \Seolan\Field\Link\Link();
    $link->field = 'from';
    $link->target = $this->table;
    $link->autocomplete = false;
    $link->checkbox = false;
    $link->compulsory = false;
    \Seolan\Core\Shell::toScreen2('', 'from', $link->edit(($emptyValue=null), ($options=[])));
  }

  /// Sauvegarde une modification de droits
  function procEditSec($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oids=$p->get('oid');
    $r=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA));
    $message=array();
    $noauth=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Security','noauthtosetsec');
    $level = $p->get('level');
    foreach($level as $i => $modlevel) {
      foreach($modlevel as $lang => $lev) {
	foreach($oids as $oid){
	  $ok=$GLOBALS['XUSER']->setUserAccess($r['lines_classname'][$i],$r['lines_oid'][$i],$lang,NULL,$lev,$oid,false,false);
	  if(!$ok) $message[]=$r['lines_name'][$i].' ('.$lang.') : '.$noauth;
	}
      }
    }
    if(!empty($message)) setSessionVar('message',implode('<br/>',$message));
  }

  /// Génère un récapitulatif de l'ensemble des droits pour un user
  function secSummary($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $oid=$p->get('oid');
    $smoid=$p->get('smoid');
    $slang=$p->get('slang');
    $tplentry=$p->get('tplentry');
    $ret=array('oid'=>$oid,'smoid'=>$smoid,'langs'=>\Seolan\Core\Lang::getCodes(),'slang'=>$slang);
    if(empty($smoid)){
      $modules=\Seolan\Core\Module\Module::modlist();
      foreach($modules['lines_classname'] as $i => $classname) {
        list($ua,$full)=$GLOBALS['XUSER']->getUserAccess($classname,$modules['lines_oid'][$i],$slang,'',$oid);
        if (end($ua) == 'none') {
          foreach($modules as $k=>$a){
            unset($modules[$k][$i]);
          }
        }
      }
      foreach($modules as $k=>$a){
        $modules[$k] = array_values($a);
      }
      $ret['modules'] = $modules;
    }else{
      \Seolan\Core\Labels::loadLabels('Seolan_Core_Security');
      $mod=\Seolan\Core\Module\Module::objectFactory($smoid);
      $ooids=\Seolan\Core\User::getObjectsWithSec($mod,$oid);
      array_unshift($ooids,'');
      foreach($ooids as $ooid){
	if($ooid){
	  $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ooid);
	  $title=$xset->rDisplayText($ooid, array());
	  if(!is_array($title)) continue;
	  $title=$title['link'];
	}else{
	  $title=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','module','text');
	}
        $sec=$mod->mySecure($ooid,':list',$oid,$slang,false);
	$ret['secs'][]=array('lvl'=>$sec[2],'who'=>$sec[3],'title'=>$title,'oid'=>$ooid);
	if(!empty($sec[3]) && empty($ret['groups'][$sec[3]])){
	  $ret['groups'][$sec[3]]=getDB()->fetchOne("select GRP from GRP where KOID=?", [$sec[3]]);
	}
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// nettoyage dans les base suite à la suppression d'un utilisateur ou d'un groupe d'utilisateurs
  function cleanUserOrGroup($oid) { 
    // suppression des preférences et données temporaires éventuelles
    \Seolan\Core\DbIni::clear(\Seolan\Core\Session::LOGIN_BANISHED.$oid);
    \Seolan\Core\DbIni::clear(\Seolan\Core\Session::LOGIN_ATTEMPTS_COUNT.$oid);
    \Seolan\Core\DbIni::clearForUser($oid);
    
    // suppression des regles de securite inutiles
    \Seolan\Core\User::clearUserAccess(NULL,NULL,NULL,NULL,$oid);
    
    // suppression des preférences et données temporaires éventuelles
    if($mod=\Seolan\Core\Module\Module::singletonFactory(XMODSUB_TOID)) {
      $mod->rmSubUser($oid);
    }

    // suppression des données dans la base des registres
    $register=\Seolan\Core\Registry::getInstance();
    $register->cleanForUser($oid);
    
    // suppression des réservation s'il y en a
    if($xlock=\Seolan\Core\Shell::getXModLock()) {
      $xlock->cleanLocksForUser($oid);
    }
  }

  /// Liste des fonctions utilisable sur la selection du module
  function userSelectionActions(){
    $actions=parent::userSelectionActions();
    if($this->secure('','editSec')) {
      $sectxt=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','security','text');
      $actions['secselection']='<a href="#" onclick="TZR.SELECTION.applyToInContentDiv('.$this->_moid.',\'editSec\',false,{template:\'Module/User.secedit.html\',tplentry:\'br\'}); return false;">'.$sectxt.'</a>';
    }
    return $actions;
  }

  /// Retourne les infos de l'action securite du browse
  function browseActionSecHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
    return 'class="cv8-secaction cv8-ajaxlink"';
  }
  function browseActionSecLvl($linecontext=null){
    return $this->secGroups('editSec');
  }
  function browseActionSecUrl($usersel, $linecontext=null){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=editSec&template=Module/User.secedit.html';
  }
}
