<?php
namespace Seolan\Module\Subscription;
/// Module de gestion des abonnements
class Subscription extends \Seolan\Module\Table\Table {
  public $sender='noreply@xsalto.com';
  public $sendername='noreply@xsalto.com';
  public $freq = 'daily;*/4';
  static public $upgrades=[];
  public static $singleton = true;

  // Description :
  function __construct($ar=NULL) {
    $ar['moid']=self::getMoid(XMODSUB_TOID);
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Subscription_Subscription');
    $this->group=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','systemproperties');
    $this->modulename=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Subscription_Subscription','modulename');
  }
  
  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['runSubs']=array('none','ro','rw','rwv','admin');
    $g['rmSub']=array('ro','rw','rwv','admin');
    $g['lsSubs']=array('ro','rw','rwv','admin');
    $g['preSubscribe']=array('ro','rw','rwv','admin');
    $g['subscribe']=array('ro','rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  // suppression du module
  function delete($ar=NULL) {
    parent::delete($ar);
  }

  // initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Subscription_Subscription','modulename');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','sender'),'sender','text',NULL,'noreply@xsalto.com',$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','sendername'),'sendername','text',NULL,'Abonnement Console Seolan',$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','cronfrequency'),'freq','text',NULL,'daily;*/4',$alabel);
  }
  /**
   * retourne l'instancce xtemplate pour les mail d'abonnements  
   */
  function getSubMailTemplate(){
    return new \Seolan\Core\Template(TZR_SHARE_DIR.'Module/Subscription.message-core.html');
  }
  // execution des abonnements sur tous les modules pour chaque utilisateur
  //
  function runSubs(\Seolan\Module\Scheduler\Scheduler& $s, $o, $arraymore) {
    $users=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=USERS');
    $users_oids_tmp=getDB()->fetchCol('select distinct user from OPTS where dtype="sub" and modid!=?', [$this->_moid]);
    $users_oids=[];
    $modules=[];
    foreach($users_oids_tmp as $userid) {
      if(strpos($userid,'GRP:')!==false){
	$foo=\Seolan\Module\Group\Group::users(array($userid));
	if(is_array($foo)) $users_oids=array_merge($users_oids,$foo);
      }elseif(strpos($userid,'USERS:')!==false){
	$users_oids[]=$userid;
      }
    }
    $users_oids=array_unique($users_oids);
    $modules_id=getDB()->fetchCol('select distinct modid from OPTS where dtype="sub" and modid!=?', array($this->_moid));
    foreach($modules_id as $moid) {
      $mod=\Seolan\Core\Module\Module::objectFactory($moid);
      if(is_object($mod)) $modules[]=$mod;
    }
    $timestamp=date("Y-m-d H:i:s");// date a laquelle on examine les abonnements
    foreach($users_oids as $userid) {
      if(\Seolan\Core\Kernel::objectExists($userid)) {
	$olduser=$GLOBALS['XUSER'];
	$GLOBALS['XUSER']=new \Seolan\Core\User(array('UID'=>$userid));
	$groups=$GLOBALS['XUSER']->groups();
	setSessionVar('UID',$userid);
	
	// pour chaque module
	$txt='';
	foreach ($modules as $module) {
	  if(is_object($module) && !empty($module)) {
	    $txt1 = $module->runSub($userid, $groups, $timestamp);
	    if(!empty($txt1)) {
	      $txt.='<p><b>'.$module->getLabel().'</b><ul>'.$txt1.'</ul></p>';
	    }
	  }
	}

	if(!empty($txt)) {
	  $user = $users->display(array('tplentry'=>TZR_RETURN_DATA,'oid'=>$userid));
	  $intro=$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Subscription_Subscription','intro','mail');
	  $tpldata['br']=array('intro'=>$intro,'text'=>$txt);
	  $r3=array();
	  $xt = $this->getSubMailTemplate();
	  $labels=$GLOBALS['XSHELL']->labels->get_labels(array('selectors'=>array('global'),'local'=>true));
	  $xt->set_glob(array('labels'=>&$labels));
	  $content=$xt->parse($tpldata,$r3,NULL);

	  $mail = $this->getMailer();
	  $mail->From = $this->sender;
	  $mail->FromName = $this->sendername;
	  $destinataires = $this->getUserEmails($user);
	  foreach ($destinataires as $useremail=>$username){
	    $mail->AddAddress($useremail, $useremail, $username);
	  }
	  $mail->AltBody = strip_tags($content);
	  $mail->sendPrettyMail($this->getLabel(), $content,null,null,['sign'=>0]);
	}
	setSessionVar('UID',$olduser->uid());
	$GLOBALS['XUSER']=$olduser;
      }
    }
    
    foreach ($modules_id as $moid) {
      getDB()->execute("UPDATE OPTS SET UPD=? WHERE modid=? AND dtype='sub'", array($timestamp, $moid));
    }
  }

  /**
   * Récupération du(des) emails de contact de l'utilisateur, function surchargable par défaut utilise champ USER:email, USER:fullnam
   * 
   * @param Array $user: display de la table USER  console
   *
   * @return Array : tableau ("senderemail"=>"sendername",...)
   */

  function getUserEmails($user):array{
    return [$user['oemail']->raw=>$user['ofullnam']->raw];
  }

  /// Ajoute un abonnement
  function addSub($users, $amoid, $specs=[]) {
    if($amoid==$this->_moid) return false;
    foreach($users as $i=>$user) {
      if(!empty($oid)) {
	$cnt=getDB()->count("select COUNT(*) from OPTS where modid='$amoid' and user='$user' and dtype='sub' and specs like '%\"oid\":\"$oid\"%'");
      } else {
	$cnt=getDB()->count("select COUNT(*) from OPTS where modid='$amoid' and user='$user' and dtype='sub' and (specs is NULL or specs = '')");
      }
      if($cnt<=0) {
	$a1=array();
	$a1['specs']=\Seolan\Library\Opts::encodeSpecs($specs);
	$a1['modid']=$amoid;
	$a1['user']=$user;
	$a1['dtype']='sub';
	$this->xset->procInput($a1);
      }
    }
    return true;
  }

  /// Supprime un abonnement
  function rmSub($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $suboid=$p->get('suboid');
    $rs=getDB()->fetchRow('SELECT * FROM OPTS WHERE KOID=? LIMIT 1', array($suboid));
    $mod=\Seolan\Core\Module\Module::objectFactory($rs['modid']);
    $uid=$rs['user'];
    $deletable=($mod->secure('',':admin')?true:($uid==\Seolan\Core\User::get_current_user_uid()));
    if($deletable) getDB()->execute('DELETE FROM OPTS WHERE KOID=?', array($suboid));
  }
  
  /// supprime tous les abonnements d'une personne ou d'un groupe. On vérifie quand même que la personne a le droit de supprimer
  function rmSubUser($uoid) {
    $rs=getDB()->fetchAll('SELECT * FROM OPTS WHERE user=?', array($uoid));
    foreach($rs as $ors) {
      $mod=\Seolan\Core\Module\Module::objectFactory($ors['modid']);
      $deletable=($mod->secure('',':admin')?true:($uid==\Seolan\Core\User::get_current_user_uid()));
      if($deletable) getDB()->execute('DELETE FROM OPTS WHERE KOID=?', array($suboid));
    }
  }

  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my, $alfunction);
    $moid=$this->_moid;
    $o1=new \Seolan\Core\Module\Action($this,'lsSubs',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Subscription_Subscription','suball'),
			  '&moid='.$moid.'&_function=lsSubs&template=Module/Subscription.browse.html&tplentry=br','display');
    $o1->containable=true;
    $o1->setToolbar('Seolan_Core_General','browse');
    $my['browse']=$o1;
    if(!\Seolan\Core\Shell::isRoot()) unset($my['query']);
    if($this->interactive) {
      $my['stack'][0]=$o1;
    }
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=lsSubs&template=Module/Subscription.browse.html&tplentry=br';
  }

  // recherche de tous les abonnements et envoi pour affichage
  //
  function lsSubs($ar) {
    $p = new \Seolan\Core\Param($ar, array('user'=>\Seolan\Core\User::get_current_user_uid(),
			       'amoid'=>''));
    $user=$p->get('user');
    if($this->secure('', ':admin') || (\Seolan\Core\User::get_current_user_uid()==$user)) {
      $amoid=$p->get('amoid');
      $aoid=$p->get('aoid');
      $tplentry=$p->get('tplentry');
      $u=new \Seolan\Core\User(array('UID'=>$user));
      $groups=$u->groups();
      $groups[]=$user;
      $LANG_DATA = \Seolan\Core\Shell::getLangData();
      
      if(!empty($amoid) && !empty($user)) {
	$txt='';
	$o2=array();
	$sub=array();
	$mod=\Seolan\Core\Module\Module::objectFactory($amoid);
	if(is_object($mod)) {
	  $txt1=$mod->lsSub($user,$groups);
	  if(!empty($txt1)) {
	    $sub[$amoid]['title']=$mod->getLabel();
	  $sub[$amoid]['content']=$txt1;
	  }
	}
	$r['sub']=&$sub;
      } elseif(!empty($amoid) && empty($user)) {
	$txt='';
	$o2=array();
	$sub=array();
	$mod=\Seolan\Core\Module\Module::objectFactory($amoid);
	if(is_object($mod)) {
	  $txt1=$mod->lsSub(NULL,NULL,NULL);
	  if(!empty($txt1)) {
	    $sub[$amoid]['title']=$mod->getLabel();
	    $sub[$amoid]['content']=$txt1;
	  }
	}
	$r['sub']=&$sub;
      } elseif(empty($amoid) && !empty($user)) {
	$rs2=getDB()->fetchCol('select MOID from MODULES');
	$txt='';
	$sub=array();
	foreach($rs2 as $o2) {
	  $mod=\Seolan\Core\Module\Module::objectFactory($o2);
	  if(is_object($mod)) {
	    $txt1=$mod->lsSub($user, $groups);
	    if(!empty($txt1)) {
	      $sub[$o2]['title']=$mod->getLabel();
	      $sub[$o2]['content']=$txt1;
	    }
	  }
	}
	unset($rs2);
	$r['sub']=&$sub;
      }
      $r['user']=&$u;
      if($this->secure('',':admin',null,$LANG_DATA)) {
	list($acl_user, $acl_grp)=\Seolan\Core\User::getUsersAndGroups();
	\Seolan\Core\Shell::toScreen1('users',$acl_user);
	\Seolan\Core\Shell::toScreen1('grps',$acl_grp);
	$rmod=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA));
	\Seolan\Core\Shell::toScreen1('modules',$rmod);
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }

  // verification qu'un module est bien installé. Si le parametre
  // verification: existence du scheduler, verification qu'une t^ache de verification des abonnements est bien planifiee
  //
  public function chk(&$message=NULL) {
    $scheduler=\Seolan\Core\Module\Module::singletonFactory(XMODSCHEDULER_TOID);
    if(!is_object($scheduler)) {
      $m=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Messages','xmodscheduler_missing');
      $message.=$m;
    } else {
      list($period, $freq) = explode(';', $this->freq);
      if (empty($period) || empty($freq)){
	$period = 'daily';
	$freq = '*/4';
      }
      $scheduler->createSimpleJob("cron", $this->_moid, 'runSubs', NULL, "root", "Abonnements", "Created by chk", NULL, $period, $freq);
    }
    return parent::chk($message);
  }

  /// affichage des specifications de l'abonnement et demande de confirmation
  function preSubscribe($ar) {
    $p=new \Seolan\Core\Param($ar, array('user'=>\Seolan\Core\User::get_current_user_uid()));
    $amoid=$p->get("amoid");
    $tplentry=$p->get("tplentry");
    $user=$p->get('user');
    if($this->secure('', ':admin') || (\Seolan\Core\User::get_current_user_uid()==$user)) {
      $br['amoid']=$amoid;
      $br['user']=$user;
      $br['aoid']=$p->get("aoid");
      if(!empty($br['aoid'])) {
	$amod=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$br['aoid']);
	$rd=$amod->rDisplay($br['aoid'],array(), true);
	$br['atitle']=$rd['link'];
      }
      list($acl_user, $acl_grp)=\Seolan\Core\User::getUsersAndGroups();
      \Seolan\Core\Shell::toScreen1('users',$acl_user);
      \Seolan\Core\Shell::toScreen1('grps',$acl_grp);
    }
    return \Seolan\Core\Shell::toScreen1($tplentry, $br);
  }
  /// creation de l'abonnement
  function subscribe($ar=null) {
    $p=new \Seolan\Core\Param($ar, array('user'=>\Seolan\Core\User::get_current_user_uid()));
    $amoid=$p->get("amoid");
    $aoid=$p->get("aoid");
    $tplentry=$p->get("tplentry");
    $user=$p->get('user');
    $specs=array('oid'=>$aoid, 'details'=>$p->get('details'));
    if($this->secure('', ':admin') || (\Seolan\Core\User::get_current_user_uid()==$user)) {
      if($this->addSub(array($user), $amoid, $specs)) {
	\Seolan\Core\Shell::toScreen2('br','message',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Subscription_Subscription','abonnementok','text'));
      }
    } else {
	\Seolan\Core\Shell::toScreen2('br','message',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Subscription_Subscription','abonnementnok','text'));
    }
  }
}
