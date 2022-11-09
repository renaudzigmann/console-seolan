<?php
namespace Seolan\Module\Workflow;

/// classe implementant la tache de vote dans un workflow
class Vote extends \Seolan\Module\Workflow\Task {
  public $mode="all";
  public $subject="Demande de validation";

  function __construct(\Seolan\Core\Module\Module $workflow, $workid) {
    parent::__construct($workflow, $workid);
  }

  /// execution de la tache, mise a jour de UPD si deja en cours d'execution
  function run() {
    $ret=parent::run();
    if($ret) {
      $mod=\Seolan\Core\Module\Module::singletonFactory(XMODCOMMENT_TOID);
      $r=$mod->prepareVote($this->case['odoc']->raw, $this->context['grp'], 'Demande de vote', 'Vote en attente', 
			   $this->_workid, $this->context['usr']);
      foreach($r['users'] as $goid) {
	$this->_wfmodule->_notify2User($this->caseid, $this->subject, 
				       'Demande de vote concernant '.$this->case['odoc']->text, $goid);
	$this->_wfmodule->log($this->caseid, 'Work '.$this->_workid.' sent mail to '.$goid);
      }

      $this->_wfmodule->_works->procEdit(array('oid'=>$this->_workid, 'context'=>serialize($r['comments']), '_local'=>1));
    }
    return $ret;
  }
  
  function check() {
    $mod=\Seolan\Core\Module\Module::singletonFactory(XMODCOMMENT_TOID);
    $votes=unserialize($this->work['ocontext']->raw);
    $poll=$mod->checkVotesByOids($votes);
    if(($this->mode=="all") && ($poll['total']==$poll['closed'])) {
      if($poll['total']!=$poll['vote'][1]) $result='failure';
      else $result='success';
      $this->setStatus('finished',$result);
    }
    if(is_int($this->mode) && ($poll['closed']>=$this->mode) && ($poll['vote'][1]>=$this->mode)) {
      $result='success';
      $this->setStatus('finished',$result);
    }
    elseif(is_int($this->mode) && (($poll['total']-$poll['vote'][2])<$this->mode)) {
      $result='failure';
      $this->setStatus('finished',$result);
    }
  }
}

?>
