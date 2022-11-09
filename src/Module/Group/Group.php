<?php
namespace Seolan\Module\Group;
/// Gestion des groupes de securite d'acces a la console Seolan
class Group extends \Seolan\Module\User\AbstractUser {

  function __construct($ar=NULL) {
    $ar['moid']=self::getMoid(XMODGROUP_TOID);
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Group_Group');
    if(!$this->group){
      $this->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties','text');
    }
    if(!$this->getLabel()){
      $this->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Group_Group','modulename','text');
    }
    $this->order='GRP';
  }

  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my, $alfunction);
    $goid=\Seolan\Core\Module\Module::getMoid(XMODUSER2_TOID);
    $o1=new \Seolan\Core\Module\Action($this,'users',\Seolan\Core\Labels::getSysLabel('Seolan_Module_User_User','users','text'),
			  '&function=browse&moid='.$goid.'&template=Module/Table.browse.html&tplentry=br','display');
    $o1->menuable=true;
    $o1->setToolbar('Seolan_Module_User_User','users');
    $my['users']=$o1;
  }

  function al_browse(&$my){
    parent::al_browse($my);
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    if($this->secure('','editSec')){
      $o1=new \Seolan\Core\Module\Action($this,'editsec',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','security','text'),
			    'javascript:'.$uniqid.'.applyfunction("editSec","",{template:"Module/User.secedit.html"},true,true);','edit');
      $o1->order=4;
      $o1->setToolbar('Seolan_Core_General','security');
      $my['editsec']=$o1;
    }
  }

  /// rend la liste des utilisateurs appartenant a au moins un groupe passe en parametre
  static function &users($grps, $valid=false) {
    $cond=$result=array();
    $users=\Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
    $limits['USERS']=array('GRP');
    $limits['GRP']=array('GRPS');
    $grpss=\Seolan\Core\Kernel::followLinksUp($grps,$limits);
    if(empty($grpss)) return array();
    if(in_array(TZR_GROUPID_AUTH,$grpss) && $users->xset->fieldExists('BO')){
      $cond = array('cond'=>array('BO'=>array('=',1)));
    }elseif(!in_array(TZR_GROUPID_NOBODY,$grpss) && !in_array(TZR_GROUPID_AUTH,$grpss)){
      $cond = array('cond'=>array('GRP'=>array('=',$grpss)));
    }else{
      $cond = null;
    }
    if ($valid){
      if($users->xset->fieldExists('DATEF') && $users->xset->fieldExists('DATET')) {
	$cond['cond']['DATET']=array('>=',date('Y-m-d'));
	$cond['cond']['DATEF']=array('<=',date('Y-m-d'));
      }
      if($users->xset->fieldExists('PUBLISH')) {
	$cond['cond']['PUBLISH']=array('=',1);
      }
    }
    if ($cond){
      $select=$users->xset->select_query($cond);
    } else {
      $select = null;
    }
    $selectedfields=array('alias');
    $userlist=$users->browse(['pagesize'=>9999,
			      'tplentry'=>TZR_RETURN_DATA,
			      'selectedfields'=>$selectedfields,
			      'select'=>$select,
			      '_options'=>['local'=>1]]);
    $result=$userlist['lines_oid'];
    return $result;
  }

  /// Suppression de groupe
  function del($ar=NULL){
    $p=new \Seolan\Core\Param($ar);
    $delusers=$p->get('delusers');
    $goid=$p->get("oid");
    
    if($ret=parent::del($ar) && !is_array($goid)) {
      // nettoyage divers suite à suppression d'un groupe
      $this->cleanUserOrGroup($goid);
    }

    // Supprime les utilisateurs qui ne sont rattachés qu'au groupe à supprimer
    if($delusers){
      $umod=\Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
      $oids=\Seolan\Core\Kernel::getSelectedOids($p);
      foreach($oids as $oid){
	$uids=getDB()->fetchCol('select KOID from USERS where GRP="'.$oid.'" or GRP="||'.$oid.'||"');
	if($uids) $umod->del(array('oid'=>$uids,'_options'=>array('local'=>true)));
	getDB()->execute('update USERS set UPD=UPD,GRP=REPLACE(GRP,"||'.$oid.'||","||") where INSTR(GRP,"||'.$oid.'||")');
      }
    }
    return $ret;
  }

  /// Controle si une édition est valide
  function procEditCtrl(&$ar) {
    $p=new \Seolan\Core\Param($ar,array());
    // Verifie que les nouveaux droits ne sont pas supérieurs aux droits de l'utilisateur actuel
    $grp=$p->get('GRPS');
    if(!empty($grp) && !\Seolan\Core\Shell::isRoot()){
      $grp=$this->xset->desc['GRPS']->post_edit($grp,array('GRPS_HID'=>$p->get('GRPS_HID'),'GRPS_FMT'=>$p->get('GRPS_FMT')));
      if(!empty($grp)){
	$rs=getDB()->fetchCol('select MOID from MODULES');
	foreach($rs as $ors) {
	  foreach($GLOBALS['TZR_LANGUAGES'] as $lang=>$foo){
	    $nlvl=\Seolan\Core\User::secure8maxlevel($ors,'',$grp->raw,$lang);
	    $alvl=\Seolan\Core\User::secure8maxlevel($ors,'',null,$lang);
	    if(!\Seolan\Core\User::compareSecLevelsLte($ors,$nlvl,$alvl)){
	      \Seolan\Core\Shell::toScreen2('','message',\Seolan\Core\Labels::getSysLabel('Seolan_Core_Security','noauthtosetsec'));;
	      return false;
	    }
	  }
	}
	unset($rs);
      }
    }
    return true;
  }

  /// Ajoute les actions du browse
  function browse_actions(&$r,$assubmodule=false,$ar=null) {
    $ar['noeditoids']=array(TZR_GROUPID_NOBODY,TZR_GROUPID_BACKOFFICE,TZR_GROUPID_AUTH);
    return parent::browse_actions($r,$assubmodule,$ar);
  }

  /// Ajoute les actions du browse à une ligne donnée
  function browseActionsForLine(&$r,&$i,&$oid,&$oidlvl,&$noeditoids){
    if(!\Seolan\Core\Shell::isRoot() && $oid==TZR_GROUPID_ROOT){
      $this->browseActionView($r,$i,$oid,$oidlvl);
    }else{
      parent::browseActionsForLine($r,$i,$oid,$oidlvl,$noeditoids);
    }
  }

  /// Rend l'accessibilite du module avec l'oid donne
  function secure($oid, string $func, $user=NULL, $lang=TZR_DEFAULT_LANG) {
    if($oid==TZR_GROUPID_ROOT && !\Seolan\Core\Shell::isRoot() && !$this->secGroups($func,'ro')) return false; 
    return parent::secure($oid,$func,$user,$lang);
  }
}


/// Fonction appelée en ajax pour recupérer la liste des utilisateurs d'un groupe (seulement si authentifié)
function xmodgroup_getGroupTree(){
  activeSec();
  if(!\Seolan\Core\User::authentified()) die();
  $grp=$_REQUEST['grp'];
  $selected=$_REQUEST['selected'];

  if(!\Seolan\Core\Kernel::isAKoid($grp)) die();

  $cond = [];
  $dirmod = null;
  if (isset($_REQUEST['directorymodule']) && !empty($_REQUEST['directorymodule'])){
    $usersmoids = \Seolan\Core\Module\Module::modulesUsingTable('USERS', true, 'only', true/*auth*/, true);
    if (in_array($_REQUEST['directorymodule'], array_keys($usersmoids))){
      $dirmod = \Seolan\Core\Module\Module::objectFactory(['tplentry'=>TZR_RETURN_DATA,
					'moid'=>$_REQUEST['directorymodule'],
					'_options'=>['local'=>1]
      ]);
    } else {
      \Seolan\Core\Logs::critical(__METHOD__, $_REQUEST['directorymodule'].' does not use USERS table or not readable');
    }
  }
  if ($dirmod == null){
    $usersoid=\Seolan\Module\Group\Group::users(array($grp));
    if (empty($usersoid)){
      // usersoid est le premier critère de la requete suivante qui l'ignore si vide 
      header('Content-type: text/html; charset='.TZR_INTERNAL_CHARSET);
      die('');
    }
    $cond['KOID']=array('=',$usersoid);
  } else {
    // simple requete sur les groupes dans le dirmod
    $cond['GRP'] = ['=', $grp];
  }
  $xuser=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=USERS');
  if($xuser->fieldExists('DATEF') && $xuser->fieldExists('DATET')) {
    $cond['DATET']=array('>=',date('Y-m-d'));
    $cond['DATEF']=array('<=',date('Y-m-d'));
  }
  if($xuser->fieldExists('PUBLISH')) $cond['PUBLISH']=array('=',1);

  $select=$xuser->select_query(array('cond'=>$cond));

  if ($dirmod != null){
    $users=$dirmod->browse(array('tplentry'=>TZR_RETURN_DATA, 
				 '_options'=>['local'=>1],
				 'order'=>'fullnam', 
				 'select'=>$select,
				 'pagesize'=>'1000',
				 'selectedfields'=>array('fullnam')));
  } else {
    $users=$xuser->browse(array('tplentry'=>TZR_RETURN_DATA, 'order'=>'fullnam', 'select'=>$select,
				'pagesize'=>'1000','selectedfields'=>array('fullnam')));
  }

  header('Content-type: text/html; charset='.TZR_INTERNAL_CHARSET);
  foreach($users['lines_oid'] as $i=>$oid){
    if(in_array($oid, $selected)) $sel="selected";
    else $sel="unselected";
    echo "<li x-value=\"$oid\" x-name=\"{$_REQUEST['name']}\" x-type=\"doc\"><span><span class=\"$sel\">".
      $users['lines_ofullnam'][$i]->html.'</span></span></li>';
  }
  die();
}
?>
