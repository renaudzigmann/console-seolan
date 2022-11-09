<?php
namespace Seolan\Module\BackOfficeStats;
/// Statistiques d'utilisation du back office
class BackOfficeStats extends \Seolan\Core\Module\Module {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['index']=array('ro','rw','rwv','admin');
    $g['clearStats']=array('admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=index&tplentry=mods&template=Module/BackOfficeStats.index.html';
  }

  protected function _actionlist(&$my=NULL, $alfunction=true) {
    parent::_actionlist($my);
    $moid=$this->_moid;
    $o1=new \Seolan\Core\Module\Action($this,'index',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','browse'),
			  '&moid='.$moid.'&function=index&tplentry=mods&template=Module/BackOfficeStats.index.html');
    $o1->setToolbar('Seolan_Core_General','browse');
    $my['index']=$o1;
    if($this->secure('','clearStats')) {
      $o1=new \Seolan\Core\Module\Action($this,'clearStats',\Seolan\Core\Labels::getSysLabel('Seolan_Module_BackOfficeStats_BackOfficeStats','clearstats','text'),
			    '&moid='.$moid.'&_function=clearStats&template=Module/BackOfficeStats.index.html');
      $o1->needsconfirm=\Seolan\Core\Labels::getSysLabel('Seolan_Module_BackOfficeStats_BackOfficeStats','clearconfirm','text');
      $o1->setToolbar('Seolan_Core_General','delete');
      $my['clearStats']=$o1;
    }
  }

  /// Increment les statistique
  static function count($oid,$lang,$moid,$user,$func,$nb=1) {
    if(!\Seolan\Core\System::tableExists('_STATS')) return false;
    if(empty($user)) $user=$GLOBALS['XUSER'];
    $now=date("Y-m-d");
    $rs=getDB()->fetchRow('select KOID from _STATS where TS="'.$now.'" and SGRP="'.$user->_curoid.'" and SMOID="'.$moid.'" and SFUNCTION="'.$func.'" '.
			  'limit 1');
    if(empty($rs)) getDB()->execute('insert into _STATS set KOID="'.\Seolan\Core\DataSource\DataSource::getNewBasicOID('_STATS').'",CNT='.$nb.',TS="'.$now.'",SFUNCTION="'.$func.'",SMOID="'.$moid.'",'.
			       'SGRP="'.$user->_curoid.'"');
    else{
      $noid=$rs['KOID'];
      getDB()->execute('update LOW_PRIORITY _STATS set CNT=CNT+'.$nb.' where KOID="'.$noid.'"');
    }
  }

  /// Comptage des visites des utilisateurs pour un module et un mois donnés
  function cntUsers($moid,$begin,$end) {
    if(!is_numeric($moid) && $moid!='%'){
      $rs1=getDB()->select('SELECT SGRP,SUM(CNT) F1,TS FROM _STATS WHERE SFUNCTION="'.$moid.'" AND TS BETWEEN "'.$begin.'" AND "'.$end.'" GROUP BY SGRP,TS');
    }else{
      $rs1=getDB()->select('SELECT SGRP,SUM(CNT) F1,TS FROM _STATS WHERE SMOID LIKE "'.$moid.'" AND TS BETWEEN "'.$begin.'" AND "'.$end.'" GROUP BY SGRP,TS');
    }
    $d=$begin;
    $days=array();
    while($d<=$end){
      $days[$d]=0;
      $d=date('Y-m-d',strtotime($d.' +1 day'));
    }
    while($ors1=$rs1->fetch()) {
      $user=$ors1['SGRP'];
      if(empty($table[$user])) {
	$table[$user]=$days;
	$xu=new \Seolan\Core\User(array('UID'=>$user));
	if(is_object($xu)) $users[$user]=$xu->fullname();
      }
      $table[$user][$ors1['TS']]=$ors1['F1'];
    }
    asort($users);
    return array($users,$table,$days);
  }

  /// Comptage des visites par module pour un utilisateur et un mois donnés
  function cntModules($user,$begin,$end) {
    if(\Seolan\Core\Kernel::getTable($user)=='GRP') {
      $users=\Seolan\Module\Group\Group::users(array($user));
      $ucond='SGRP IN ("'.implode('","',$users).'")';
    }else{
      $ucond='SGRP = "'.$user.'"';
    }
    $rs1=getDB()->select('SELECT SMOID, TS, SUM(CNT) F1 FROM _STATS WHERE '.$ucond.' AND TS BETWEEN ? AND ? GROUP BY SMOID, TS', [$begin, $end]);
    $d=$begin;
    $days=array();
    while($d<=$end){
      $days[$d]=0;
      $d=date('Y-m-d',strtotime($d.' +1 day'));
    }
    while($rs1 && $ors1=$rs1->fetch()) {
      $smoid=$ors1['SMOID'];
      if(empty($table[$smoid])) $table[$smoid]=$days;
      $table[$smoid][$ors1['TS']]=$ors1['F1'];
    }
    return array(NULL,$table,$days);
  }

  /// Comptage des nombres de pages par module
  function index($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get("tplentry");
    $r=array();
    $datedef=new \Seolan\Field\Date\Date();
    $datedef->compulsory=false;
    $mods=self::modlist($ar);
    list($acl_user,$acl_grp)=\Seolan\Core\User::getUsersAndGroups();
    \Seolan\Core\Shell::toScreen1('users',$acl_user);
    \Seolan\Core\Shell::toScreen1('grps',$acl_grp);
    $module=$p->get('module');
    $user=$p->get('user');
    $ym=$p->get('ym');
    if(!empty($ym)){
      $begin=date('Y-m-d',strtotime($ym.'-01'));
      $end=date('Y-m-t',strtotime($begin));
    }else{
      $begin=$p->get('begin');
      $end=$p->get('end');
    }
    $foo=$datedef->post_edit($begin);
    $begin=($foo->raw!=TZR_DATE_EMPTY?$foo->raw:'');
    $foo=$datedef->post_edit($end);
    $end=($foo->raw!=TZR_DATE_EMPTY?$foo->raw:'');
    if(!empty($user)) {
      $xuser=new \Seolan\Core\User(array('UID'=>$user));
      \Seolan\Core\Shell::toScreen2('br','user',$xuser);
    }
    // Prepare les filtres sql sur le module et l'utilisateur/groupe
    if(empty($user)) {
      $ucond='1';
    }elseif(\Seolan\Core\Kernel::getTable($user)=='GRP'){
      $users=\Seolan\Module\Group\Group::users(array($user));
      $ucond='SGRP IN ("'.implode('","',$users).'")';
    }else{
      $ucond='SGRP = "'.$user.'"';
    }
    if(empty($module)) $module="%";
    
    if(!empty($begin) && !empty($end)) {
      // Stats sur les jours d'un intervalle donné
      if(!is_numeric($module) && $module!='%'){
	$r1=getDB()->fetchAll('SELECT SUM(CNT) F1,TS FROM _STATS WHERE TS BETWEEN ? AND ? AND SFUNCTION=? AND '.$ucond.' '.
			      'GROUP BY TS ORDER BY TS',[$begin, $end, $module]);
      }else{
	$r1=getDB()->fetchAll('SELECT SUM(CNT) F1,TS FROM _STATS WHERE TS BETWEEN ? AND ? AND SMOID LIKE ? AND '.$ucond.' '.
			      'GROUP BY TS ORDER BY TS',[$begin, $end, $module]);
      }
      // Stats du mois du module selectionné
      if($module!='%' && empty($user)) {
	$r3=$this->cntUsers($module,$begin,$end);
	\Seolan\Core\Shell::toScreen2('cnt','days',$r3[2]);
	\Seolan\Core\Shell::toScreen2('cnt','details',$r3[1]);
	\Seolan\Core\Shell::toScreen2('cnt','users',$r3[0]);
      }
      // Stats du mois de l'utilisateur selectionné
      if(($module=='%') && !empty($user)) {
	$r3=$this->cntModules($user,$begin,$end);
	\Seolan\Core\Shell::toScreen2('cnt','days',$r3[2]);
	\Seolan\Core\Shell::toScreen2('cnt','details2',$r3[1]);
      }
    }else{
      // Stats sur les 31 derniers jours
      if(!is_numeric($module) && $module!='%') $r1=getDB()->fetchAll('SELECT SUM(CNT) F1,TS FROM _STATS WHERE SFUNCTION=? AND '.$ucond.' GROUP BY TS ORDER BY TS DESC LIMIT 0,31',[$module]);
      else $r1=getDB()->fetchAll('SELECT SUM(CNT) F1,TS FROM _STATS WHERE SMOID LIKE ? AND '.$ucond.' GROUP BY TS ORDER BY TS DESC LIMIT 0,31',[$module]);
    }
    \Seolan\Core\Shell::toScreen2('cnt','daily',$r1);

    // Stats sur les 24 derniers mois
    $first=strtotime('-24 month');
    $foo=array('','','','','','','','','','','','');
    for($i=date('Y',$first);$i<=date('Y');$i++) $r2[$i]=$foo;
    $first=date('Y-m-01',$first);
    if(!is_numeric($module) && $module!='%'){
      $rs=getDB()->select('SELECT SUM(CNT) F1,YEAR(TS) F3,MONTH(TS) F2 FROM _STATS WHERE SFUNCTION=? AND '.$ucond.' AND TS>=? GROUP BY YEAR(TS),MONTH(TS)', [$module, $first]);
    }else{
      $rs=getDB()->select('SELECT SUM(CNT) F1,YEAR(TS) F3,MONTH(TS) F2 FROM _STATS WHERE SMOID LIKE ? AND '.$ucond.' AND TS>=? GROUP BY YEAR(TS),MONTH(TS)', [$module,$first]);
    }
    while($ors=$rs->fetch()){
      $r2[$ors['F3']][(int)$ors['F2']-1]=$ors['F1'];
    }
    \Seolan\Core\Shell::toScreen2('cnt','monthly',$r2);
    // Champs date pour le filtre
    \Seolan\Core\Shell::toScreen2('param','begin',$datedef->edit($begin,$o=array('fieldname'=>'begin')));
    \Seolan\Core\Shell::toScreen2('param','end',$datedef->edit($end,$o=array('fieldname'=>'end')));
    
    return \Seolan\Core\Shell::toScreen1($tplentry,$mods);
  }

  /// Suppression des donnes pour un module
  static function clean($moid=NULL,$user=NULL) {
    if(!\Seolan\Core\System::tableExists('_STATS')) return false;
    if(!empty($moid)) getDB()->execute('DELETE FROM _STATS WHERE SMOID="'.$moid.'"');
  }

  /// Suppression de toutes les données 
  static function clearStats() {
    if(!\Seolan\Core\System::tableExists('_STATS')) return false;
    getDB()->execute('DELETE FROM _STATS');
  }

  /// Vérification du module
  function chk(&$message=NULL) {
    // Suppression des donnees statistiques sur les modules supprimes
    $rs=getDB()->fetchAll("select distinct MOID,SMOID from _STATS LEFT OUTER JOIN MODULES ON MOID=SMOID WHERE ISNULL(MOID) and NOT SMOID='';");
    foreach($rs as $ors) {
      getDB()->execute('DELETE FROM _STATS WHERE SMOID=?',array($ors['SMOID']));
      $message.='Deleted messages in _STATS table for module '.$ors['SMOID'];
    }
  }
}
?>
