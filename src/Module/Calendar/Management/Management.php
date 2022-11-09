<?php
namespace Seolan\Module\Calendar\Management;
class Management extends \Seolan\Module\Table\Table{
  public $calmod;  /* MOID du module agenda à administrer */

  function __construct($ar=NULL){
    parent::__construct($ar);
    $this->xset->desc['cons']->sys=true;
    $this->xset->desc['OWN']->readonly=false;
    $this->xset->desc['OWN']->compulsory=true;
    $this->xset->desc['OWN']->sys=false;
  }

  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Module agenda à administrer', 'calmod', 'module');
  }

  function insert($ar=NULL){
    $this->xset->desc['OWN']->default=\Seolan\Core\User::get_current_user_uid();
    $ar['options']['tz']['value']='Europe/Paris';
    $ar['options']['begin']['value']='08:00';
    $ar['options']['end']['value']='20:00';
    return parent::insert($ar);
  }

  function procInsert($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $def=$p->get('def_HID');
    $own=$p->get('OWN');
    $name=$this->controlCalendarName($ar);
    if(!empty($name)){
      $ar['name']=$this->check_synchro_cond($name);
    } elseif($p->get('name')){
      // Le protocole de synchronisation impose qu'il n'y ait pas de () dans le nom de l'agenda donc on remplace par des []
      $ar['name']=$this->check_synchro_cond($p->get('name'));
    }
    $ret=parent::procInsert($ar);
    if(!empty($own)) getDB()->execute('update '.$this->table.' set OWN="'.$own.'"  where KOID=?',array($ret['oid']));
    if(!empty($def['val'])){
      $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$this->calmod,'tplentry'=>TZR_RETURN_DATA,'oid'=>$ret['oid']));
      $mod->setDefault(array('uid'=>$own,'_nonext'=>true));
    }
    return $ret;
  }

  function procEdit($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    $def=$p->get('def_HID');
    $own=$p->get('OWN');
    $cat=$p->get('cat');

    if(is_array($oid)) return parent::procEdit($ar);
    if(empty($own) || empty($cat)){
      $d=$this->xset->rdisplay($oid);
      if(empty($own)) $own=$ar['OWN']=$d['oOWN']->raw;
      if(empty($cat)) $cat=$ar['cat']=$d['ocat']->raw;
    }
    $name=$this->controlCalendarName($ar);
    if(!empty($name)){
      $ar['name']=$this->check_synchro_cond($name);
    } elseif($p->get('name')){
      // Le protocole de synchronisation impose qu'il n'y ait pas de () dans le nom de l'agenda donc on remplace par des []
      $ar['name']=$this->check_synchro_cond($p->get('name'));
    }
    if(!empty($def['val'])){
      $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$this->calmod,'tplentry'=>TZR_RETURN_DATA,'oid'=>$oid));
      $mod->setDefault(array('uid'=>$own,'_nonext'=>true));
    }
    return parent::procEdit($ar);
  }

/// Assure que le nom de l'agenda sera conforme au protocole de synchronisation en enlevant les parenthèses (transformés en crochets si présente)
  function check_synchro_cond($name){
    $name=str_replace('(','[',$name);
    $name=str_replace(')',']',$name);
    return $name;
  }

  /// Force le nom de l'agenda lors de sa creation/modification si c'est un agenda perso
  function controlCalendarName($ar=NULL){
    $mod=\Seolan\Core\Module\Module::objectFactory($this->calmod);
    $p=new \Seolan\Core\Param($ar,NULL);
    $cat=$p->get('cat');
    $own=$p->get('OWN');
    if($cat==$mod->catperso){
      $xuser=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=USERS');
      $d=$xuser->rdisplay($own);
      return $d['ofullnam']->html;
    }
    return;
  }

  /// Controle la presence d'un seul agenda perso par user
  function procEditCtrl(&$ar){
    $mod=\Seolan\Core\Module\Module::objectFactory($this->calmod);
    $p=new \Seolan\Core\Param($ar,NULL);
    $own=$p->get('OWN');
    $oid=$p->get('oid');
    $cat=$p->get('cat');
    if($cat==$mod->catperso){
      $rs=getDB()->select('select KOID from '.$mod->tagenda.' where cat="'.$mod->catperso.'" and OWN="'.$own.'" and KOID!="'.$oid.'"');
      if($rs && $rs->rowCount()>0){
	\Seolan\Core\Shell::toScreen2('','message','1 seul agenda personnel est autorisé par utilisateur.');
	return false;
      }
    }
    return true;
  }

  function del($ar=NULL){
    $p=new \Seolan\Core\Param($ar, array('_selectedok'=>'nok'));
    $oid=$p->get('_selected');
    $selectedok=$p->get('_selectedok');
    if(($selectedok!='ok')||empty($oid)) $oid=$p->get('oid');
    if(!is_array($oid)) $oid=array($oid);
    $mod=\Seolan\Core\Module\Module::objectFactory($this->calmod);
    foreach($oid as $k=>$v) {
      // Supprime tous les évènements source
      $rs=getDB()->select('select KOID from '.$mod->tevt.' where KOIDD="'.$v.'" and (KOIDS is null or KOIDS="")');
      while($rs && ($ors=$rs->fetch()))
	$mod->delEvt(array('koid'=>$ors['KOID'],'noalert'=>true));
      // Supprime les liens des évènements non source
      getDB()->execute('delete from '.$mod->tlinks.' where KOIDD ="'.$v.'"');
    }
    return parent::del($ar);
  }

  /// Ajoute les actions du browse à une ligne donnée
  function browseActionsForLine(&$r,&$i,&$oid,&$oidlvl,&$noeditoids){
    parent::browseActionsForLine($r,$i,$oid,$oidlvl,$noeditoids);
    $this->browseActionToday($r,$i,$oid,$oidlvl);
  }

  /// Retourne les infos de l'action today du browse
  function browseActionToday(&$r,&$i,&$oid,&$oidlvl,$usersel=false){
    $this->browseActionForLine('today',$r,$i,$oid,$oidlvl,$usersel);
  }
  function browseActionTodayText(){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','today','text');
  }
  function browseActionTodayIco(){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','today');
  }
  function browseActionTodayLvl(){
    $mod=\Seolan\Core\Module\Module::objectFactory($this->calmod);
    return $mod->secGroups($mod->defview);
  }
  function browseActionTodayHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
    return 'class="cv8-ajaxlink"';
  }
  function browseActionTodayUrl($usersel){
    $mod=\Seolan\Core\Module\Module::objectFactory($this->calmod);
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->calmod.'&oid=<oid>&tplentry=br&function='.$mod->defview.'&template=Module/Calendar.'.$mod->defview.'.html&now';
  }

  /// Duplique les droits saisis sur le module aganda administré
  function procSecEdit($ar) {
    
    // droits sur les objets pour ce module (Calendar/Management)
    parent::procSecEdit($ar);
    $message1 = getSessionVar('message');
    clearSessionVar('message');

    // droits identiques sur lesdits objets pour le module agenda associé
    $calmod=\Seolan\Core\Module\Module::objectFactory($this->calmod);

    $calmod->procSecEdit($ar);
    
    $message2 = getSessionVar('message');
    clearSessionVar('message');
    if (!empty($message1) || !empty($message2))
      setSessionVar('message', "{$message1}<br>{$message2}");

    
  }
}
?>
