<?php
namespace Seolan\Module\Comment;

/// module de gestion de commentaires BO ou FO
class Comment extends \Seolan\Module\Table\Table {
  public $object_sec=true;

  function __construct($ar=NULL) {
    $ar["moid"]=self::getMoid(XMODCOMMENT_TOID);
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Comment_Comment');
  }

  // suppression du module
  //
  function delete($ar=NULL) {
    parent::delete($ar);
  }

  // initialisation des proprietes
  //
  public function initOptions() {
    parent::initOptions();
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array('workflowEngine'=>array('ro','rw','rwv','admin'));
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }


  /// filtrage des champs en fonction du type de commentaire
  function _filterFields($type, &$ar) {
    if($type=='comment') $ar['selectedfields']=array('title','comment','object','closed');
    if($type=='note') $ar['selectedfields']=array('title','comment','note','object','closed');
    if($type=='vote') $ar['selectedfields']=array('title','comment','vote','object','closed');
  }

  /// gestion des champs en fonction du type
  function edit($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $oid=$p->get('oid');
    $d1=$this->xset->rDisplay($oid);
    $this->_filterFields($d1['otype']->text, $ar);
    return parent::edit($ar);
  }

  /// gestion des champs en fonction du type
  function display($ar=null) {
    $p=new \Seolan\Core\Param($ar, array());
    $oid=$p->get('oid');
    $d1=$this->xset->rDisplay($oid);
    $this->_filterFields($d1['otype']->text, $ar);

    return parent::display($ar);
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=browse&tplentry=br&template=Module/Table.browse.html';
  }

  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my);
    $myclass=get_class($this);
    $moid=$this->_moid;
    $myoid=@$_REQUEST['oid'];
    $user=\Seolan\Core\User::get_user();
  }


  /// preparation d'une operation de vote
  public function prepareVote($oid, $grp, $title, $body, $workid=NULL,$u2=NULL) {
    $r=array();
    $u=array();
    if(!empty($grp)) {
      $users=\Seolan\Module\Group\Group::users($grp);
      foreach($users as $goid) {
	$ret=$this->xset->procInput(array('title'=>$title,
					  'object'=>$oid,
					  'vote'=>2,
					  'type'=>'vote', /* request for vote */
					  'OWN'=>$goid, 'wid'=>$workid));
	$r[]=$ret['oid'];
	$u[]=$goid;
      }
    }
    if(!empty($u2)) {
      if(!is_array($u2)) $users=array($u2);
      else $users=$u2;
      foreach($users as $goid) {
	$ret=$this->xset->procInput(array('title'=>$title,
					  'object'=>$oid,
					  'vote'=>2,
					  'type'=>'vote', /* request for vote */
					  'OWN'=>$goid, 'wid'=>$workid));
	$r[]=$ret['oid'];
	$u[]=$goid;
      }
    }
    $ret['comments']=$r;
    $ret['users']=$u;
    return $ret;
  }

  public function checkVote($oid, $workid) {
    $poll=array();
    $cnt=getDB()->count("SELECT COUNT(KOID) FROM _COMMENTS WHERE object='$oid' and wid='$workid'");
    $poll['total']=(int)$cnt;
    $rs=getDB()->select("SELECT * FROM _COMMENTS WHERE object='$oid' and wid='$workid' and closed='1'");
    $poll['closed']=0;
    while($rs && $o=$rs->fetch()) {
      $poll['closed']++;
      $poll['vote'][$o['vote']]++;
    }
    return $poll;
  }
  
  public function checkVotesByOids($oids) {
    $cond=' KOID in ("'.implode('","', $oids).'")';
    $poll=array();
    $cnt=getDB()->count("SELECT COUNT(KOID) FROM _COMMENTS WHERE ".$cond);
    $poll['total']=(int)$cnt;
    $rs=getDB()->select("SELECT * FROM _COMMENTS WHERE $cond and closed='1'");
    $poll['closed']=0;
    while($rs && $o=$rs->fetch()) {
      $poll['closed']++;
      $poll['vote'][$o['vote']]++;
    }
    return $poll;
  }

}

?>
