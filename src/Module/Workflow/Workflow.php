<?php
namespace Seolan\Module\Workflow;

/// gestion des processus de workflow
class Workflow extends \Seolan\Module\Table\Table {
  public $_places = NULL;
  public $_arcs = NULL;
  public $_transitions = NULL;
  public $_cases = NULL;
  public $_tokens = NULL;
  public $_tasks = NULL;
  public $_works = NULL;
  public $runwfengine=false;
  static public $upgrades=[];

  function __construct($ar=NULL) {
    $ar["moid"]=self::getMoid(XMODWORKFLOW_TOID);
    parent::__construct($ar);
    $this->_places=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=WFPLACE');
    $this->_arcs=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=WFARC');
    $this->_transitions=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=WFTRANSITION');
    $this->_cases=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=WFCASE');
    $this->_tokens=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=WFTOKEN');
    $this->_tasks=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=WFTASKS');
    $this->_works=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=WFWORKITEM');
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Workflow_Workflow');
  }

  /// suppression du module
  function delete($ar=NULL) {
    $tables = $this->usedTables();
    foreach($tables as $table) {
      if($table != 'WFWORKFLOW') {
	$modulesusing = $this->modulesUsingTable($table,true);
	foreach($modulesusing as $moid=>$label) {
	  if($moid!=$this->_moid) {
	    $mod=\Seolan\Core\Module\Module::objectFactory($moid);
	    if(!empty($mod))  $mod->delete(array('withtable'=>0));
	  }
	}
      }
    }
    parent::delete($ar);
  }

  // initialisation des proprietes
  //
  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Workflow_Workflow','modulename');
    $this->_options->setOpt('Run Workflow engine','runwfengine','boolean',NULL,false,$alabel);
  }

  /// liste des tables utilisees par ce module
  public function usedTables() {
    return array('WFWORKFLOW','WFARC','WFTRANSITION','WFWORKITEM','WFTOKEN','WFPLACE','WFCASE','WFTASKS',);
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array('workflowEngine'=>array('none','list','ro','rw','rwv','admin'));
    $g['pendingCases']=array('list','ro','rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=browse&tplentry=br&template=Module/Table.browse.html';
  }


  /// creation d'un nouveau workflow. Il y a au moins un etat depart et arrivee
  function procInsert($ar=NULL) {
    $ret=parent::procInsert($ar);
    if(!empty($ret['oid'])) {
      $this->_addPlace($ret['oid'], 'start', 'Start');
      $this->_addPlace($ret['oid'], 'end', 'End');
    }
  }


  /// verifie si un workflow s'applique, et si oui cree un cas
  public function checkAndRun(\Seolan\Core\Module\Module $mod, $interface, $oid, $event) {
    if(!\Seolan\Core\Kernel::objectExists($oid)) return;

    // affichage de l'objet
    $display=$interface->XMCdisplay(array('oid'=>$oid, 'tplentry'=>TZR_RETURN_DATA, '_options'=>array('error'=>'return')));
    if(!is_array($display)) return;

    // on recupere l'utilisateur courant
    $user = \Seolan\Core\User::get_user();

    // recuperation de la table
    $table = \Seolan\Core\Kernel::getTable($oid);

    // recherche des workflow qui s'appliquent
    $wfs=getDB()->fetchAll('SELECT distinct KOID FROM WFWORKFLOW WHERE  (modid= ? OR modid like "%'.
			   $mod->_moid.'%") AND trig = ? and funct like "%'.$event.'%"', array($mod->_moid, 'auto'));
    foreach($wfs as $wf) {
      $wfd=$this->xset->rDisplay($wf['KOID']);
      // verification qu'on est dans les groupes qui permettent le lancement
      if($user->inGroups(explode('|',$wfd['ogrps']->raw))) {
	// verification que la condition de declenchement est vide ou rend le KOID
	$ok=true;
	if(!empty($wfd['oconds']->raw)) {
	  $oids=getDB()->fetchAll($wfd['oconds']->raw);
	  $ok=in_array($oid, $oids);
	}
	$caseid=$this->addCase($wf['KOID'], $oid, $user, array(), $mod->_moid);
	if($ok) $this->workflowEngine($caseid);
      }
    }
    unset($wfs);
  }

  /// ajout d'une place
  private function _addPlace($wfid, $type, $title) {
    $this->_places->procInput(array('wfid'=>$wfid, 
				    'type'=>$type,
				    'title'=>$title,
				    '_local'=>1));
  }


  /// ajout d'un cas sur le workflow pour l'oid document $oid
  public function addCase($wfid, $oid, \Seolan\Core\User $uid, $context, $modid) {
    $ret=$this->_cases->procInput(array('wfid'=>$wfid, 
					'OWN'=>$uid->uid(),
					'status'=>'open',
					'doc'=>array($oid),
					'doc_HID'=>'',
					'startdate'=>date('Y-m-d H:i:s'),
					'context'=>serialize($context),
					'modid'=>$modid,
					'_local'=>1));
    $caseid=$ret['oid'];
    $this->log($caseid, 'Creation');
    $all = getDB()->fetchAll('SELECT distinct KOID,title FROM WFPLACE WHERE type=? and wfid=?', array('start', $wfid));
    if(!empty($all) && !empty($all[0])) {
      $placeid=$all[0]['KOID'];
      $this->_createToken($wfid, $caseid, $placeid, $all[0]['title']);
    }
    $this->_notify2User($caseid, NULL, \Seolan\Core\Labels::getSysLabel('Seolan_Module_Workflow_Workflow','casecreated'));
    $this->workflowEngine($caseid);
    return $ret['oid'];
  }


  public function getCaseContext($case=NULL, $caseid=NULL, $var1=NULL) {
    if(empty($case))  $case=$this->_cases->rDisplay($caseid);
    $context = unserialize($case['ocontext']->raw);
    if(empty($var1))
      return $context;
    else
      return $context[$var1];
  }
  public function isPendingCase($oid) {
    $select_query = $this->_cases->select_query(array('cond'=>array('doc'=>array('=', $oid))));
    $select_query.=" AND NOT status in ('cancelled','closed')";
    $all=getDB()->fetchAll($select_query);
    if(empty($all)) return NULL;
    return $all[0]['KOID'];
  }

  public function _pendingCases($oid) {
    $select_query = $this->_cases->select_query(array('cond'=>array('doc'=>array('=', $oid))));
    $select_query.=" AND NOT status in ('cancelled','closed')";
    $all=getDB()->fetchAll($select_query);
    return $all;
  }

  /// mise a jour d'un modele de processus, calcul du graphe
  public function procEdit($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    parent::procEdit($ar);
    $oid=$p->get('oid');
    $txt=$this->graphit($oid);
    $tmp_name=TZR_TMP_DIR.uniqid();
    file_put_contents($tmp_name.'.viz',$txt);
    system(TZR_VIZGRAPH_NEATO_PATH.' -Tpng '.$tmp_name.'.viz > '.$tmp_name.'.png');
    parent::procEdit(array('oid'=>$oid, 'graph_HID'=>array('from'=>$tmp_name.'.png','link'=>0), '_local'=>1));
    unlink($tmp_name.'.png');
    unlink($tmp_name.'.viz');
  }

  /// traitement d'une transition
  public function fireTransition($caseid, $transid) {
    // mettre le token en position verrouille
    $rs1=getDB()->select("SELECT TOK.KOID,PLA.title FROM WFTOKEN TOK,WFPLACE PLA WHERE ".
		     "TOK.caseid = ? AND TOK.placeid=PLA.KOID AND TOK.status=?", array($caseid, 'free'));
    // recherche des taches dans cette transition
    $transition = $this->_transitions->rDisplay($transid);
    $this->log($caseid, 'Firing transition '.$transition['otitle']->text);
    while($rs1 && $token=$rs1->fetch()) {
      $tokenid=$token['KOID'];
      $this->log($caseid, 'token '.$token['title'].' status consumed');
      $this->_tokens->procEdit(array('oid'=>$tokenid, 'status'=>'consumed',
				     'consumed'=>date('Y-m-d H:i:s'),
				     '_local'=>1));
    }

    // recherche des taches dans cette transition
    $transition = $this->_transitions->rDisplay($transid);
    $taskid=$transition['otaskid']->raw;
    if(!empty($taskid)) {
      $taskstemplate = $this->_tasks->rDisplay($taskid);
      $tocheck=$taskstemplate['otocheck']->raw;
      $case = $this->_cases->rDisplay($caseid);
      $wfid=$case['owfid']->raw;
      
      $header="// \$workstatus['status'] pour la mise de l'etat\n".
        "// \$data[] pour acceder aux donnees du document\n".
        "// \$dataupdate[] pour mettre a jour le document courant\n";
      
      // Creation d'un travail
      $deadline = new \DateTime(date('Y-m-d H:i:s'));
      $deadline->add(new \DateInterval('PT'.$transition['otimelimit']->text.'H'));
      $this->_works->procInput(array('wfid'=>$wfid,
                                     'caseid'=>$caseid, 'transid'=>$transid,
                                     'status'=>'enabled',
                                     'enabled'=>date('Y-m-d H:i:s'),
                                     'taskid'=>$taskid,
                                     'trig'=>$transition['otrig']->raw,
                                     'deadline'=>$deadline->format('Y-m-d H:i:s')));
      
      $this->log($caseid, 'Creating workitem '.$taskstemplate['otitle']->raw);
    }
    
    return true;
  }


  /// rend un cas a partir d'un id de tache
  protected function &_getCaseFromWork($workid) {
    $work=$this->_works->rDisplay($workid);
    $case=$this->_cases->rDisplay($caseid);
    return $case;
  }

  /// taches a excuter
  public function executeTasks($caseid=NULL) {
    $cond=(empty($caseid)?'':' AND WFWORKITEM.caseid="'.$caseid.'"');
    $updated=false;

    // lancement des taches en attente de lancement
    $rs1=getDB()->select('SELECT WFWORKITEM.*,WFTASKS.myclass FROM WFWORKITEM,WFTASKS WHERE status IN ("enabled","inprogress") AND WFWORKITEM.taskid=WFTASKS.KOID'.$cond);
    while($rs1 && $task=$rs1->fetch()) {
      $workid=$task['KOID'];
      $work=\Seolan\Module\Workflow\Task::objectFactory($this, $workid);
      $updated = $work->run() || $updated;
      $update = $this->checkAndUpdateTask($workid) || $updated;
    }

    return $updated;
  }

  /// execution du moteur de workflow
  public function _daemon($when='any') {
    \Seolan\Core\Logs::debug('\Seolan\Module\Workflow\Workflow::daemon::workflowEngine');
    $this->workflowEngine();
  }

  /// boucle generale
  public function workflowEngine($caseid=NULL) {
    if(!$this->runwfengine) return;
    if(!$lock=\Seolan\Library\Lock::getLock('workflow')) return;
    \Seolan\Library\ProcessCache::deactivate();
    $myi=0;
    $updated=true;
    $nbmax=0;	
    $cond=(empty($caseid)?'':' AND WFTOKEN.caseid="'.$caseid.'"');
    // on tourne tant qu'il a des modifs mais pas plus de x fois
    while($updated && ($nbmax<=50)) {
      $nbmax++;
      // traitement des tokens free
      if($caseid) $updated=$this->executeTasks($caseid);
      else {
	$cases = getDB()->fetchAll('SELECT DISTINCT * FROM WFCASE WHERE status="open" ');
	foreach($cases as $case) {
	  $updated=$this->executeTasks($case['KOID'])||$updated;
	}
      }
      // traitement des tokens free
      while($token = getDB()->fetchRow('SELECT DISTINCT WFTOKEN.* FROM WFTOKEN,WFCASE WHERE WFTOKEN.caseid=WFCASE.KOID AND WFTOKEN.status="free" '.$cond)) {
	$tokenid=$token['KOID'];
	$caseid=$token['caseid'];
	$placeid=$token['placeid'];
	$arcs=getDB()->fetchAll('SELECT WFARC.* FROM WFARC WHERE placeid=? AND direction="in"', array($placeid));
	if(!empty($arcs)) {
	  foreach($arcs as $arc) {
	    $transid=$arc['transid'];
	    $updated = $this->fireTransition($caseid, $transid) || $updated;
	  }
	} else {
	  // un noeud free sans successeur
	  $this->log($caseid, 'token '.$tokenid.' status consumed no successor to place '.$placeid);
	  $this->endCase($caseid);
	}
      }


      // traitement des etats terminaux
      $rs=getDB()->select("SELECT DISTINCT WFTOKEN.* FROM WFCASE, WFTOKEN, WFPLACE WHERE WFCASE.KOID=WFTOKEN.caseid AND WFTOKEN.placeid=WFPLACE.KOID AND ".
			  "(WFTOKEN.status IN ('free','consumed') AND WFPLACE.type='end') ".$cond);
      while($rs && $token=$rs->fetch()) {
	$this->endCase($token['caseid']);
	$updated=true;
      }
    }

    /// suppression des processus termines et qui ont depasse le delai de garde
    $rs1=getDB()->fetchAll("SELECT wc.KOID FROM WFCASE as wc,WFWORKFLOW as wf WHERE wc.wfid=wf.KOID AND (DATE_ADD(wc.enddate, INTERVAL wf.deldays DAY) < NOW()) AND wc.status IN ('closed','cancelled')");
    foreach($rs1 as $ors) {
      $oid=$ors['KOID'];
      getDB()->execute('DELETE FROM WFTOKEN, WFCASE, WFWORKITEM USING WFTOKEN, WFCASE, WFWORKITEM WHERE WFCASE.KOID = ? '.
		       'AND WFTOKEN.caseid = WFCASE.KOID AND WFWORKITEM.caseid = WFCASE.KOID', array($oid));
    }
    \Seolan\Core\Logs::debug('\Seolan\Module\Workflow\Workflow::workflowEngine(): cycles '.$nbmax);
    \Seolan\Core\Logs::debug('\Seolan\Module\Workflow\Workflow::workflowEngine(): cycles '.$nbmax);
    \Seolan\Library\ProcessCache::activate();
    \Seolan\Library\Lock::releaseLock($lock);
    return $updated;
  }


  /// rend la liste des workflows disponibles pour ce document. Si $checkexisting est
  /// positionné à vrai, on ne rend que les workflows disponibles, c'est à dire par exemple
  /// aucun si il en existe déjà un actif sur ce document
  public function getWorkflows($mod, $trig, $funct, $oid=NULL, $checkexisting=true) {
    // un seul processus à la fois...
    if(!empty($oid) && $checkexisting && $this->isPendingCase($oid)) return array();

    $table=\Seolan\Core\Kernel::getTable($oid);
    $rs=getDB()->select('SELECT * FROM WFWORKFLOW WHERE trig=? AND funct like ? and (modid=? OR modid like "%'.$mod->_moid.'%") AND '.
			' (source like "%'.$table.'%" OR source ="")', array($trig, '%'.$funct.'%', $mod->_moid));
    $actions=array();
    $user = \Seolan\Core\User::get_user();

    while($rs && $o=$rs->fetch()) {
      if($user->inGroups(explode('|',$o['grps']))) {
	$actions[]=array($o['KOID'], $o['title'], ($o['princip']==1));
      }
    }
    return $actions;
  }

  protected function _actionlist(&$my, $alfunction=true) {
    parent::_actionlist($my);
    $myclass=get_class($this);
    $moid=$this->_moid;
    $myoid=@$_REQUEST['oid'];
    $user=\Seolan\Core\User::get_user();

    $o1=new \Seolan\Core\Module\Action($this,'pending','Taches en attente',
			  '&moid='.$moid.'&_function=pendingCases&template=Module/Table.browse.html&tplentry=br','display');
    $o1->menuable=true;
    $my['pending']=$o1;
  }

  /// liste des cas en attente de traitement par l'utilisateur courant
  public function pendingCases($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());

    $user = \Seolan\Core\User::get_user();
    $groups = $user->groups();
    
    $cond='userid IN ('.implode(',',$groups).') AND trig="user"';
    return $this->_works->browse(array('selectedfields'=>array('wfid','caseid','enabled','status'),'first'=>'0','select'=>$cond));
  }

  /// mise a jour de l'etat d'une tache
  public function checkAndUpdateTask($workid) {
    \Seolan\Core\Logs::debug('\Seolan\Module\Workflow\Workflow::checkAndUpdateTask(): start '.$workid);
    $work=$this->_works->rDisplay($workid);
    $caseid=$work['ocaseid']->raw;
    $transid=$work['otransid']->raw;
    $case=$this->_cases->rDisplay($caseid);
    $docid=$case['odoc']->raw;
    $wfid=$case['owfid']->raw;
    $workflow=$this->xset->rDisplay($wfid);

    $work=$this->_works->rDisplay($workid);
    $result=$work['oresult']->raw;
    $updated=false;
    if($work['ostatus']->raw == 'finished' || $work['ostatus']->raw =='cancelled') { 
      // distribution des jetons en aval de la transition si les conditions sont réunies
      $rs=getDB()->select('SELECT distinct WFPLACE.KOID, WFPLACE.title FROM WFPLACE,WFTRANSITION,WFARC WHERE WFTRANSITION.KOID=? AND WFARC.direction="out" AND '.
			  'WFARC.transid=WFTRANSITION.KOID AND WFPLACE.KOID=WFARC.placeid AND (WFARC.precondition=? OR WFARC.precondition="" OR WFARC.precondition="-")',
			  array($transid, $result));
      $this->log($caseid, 'Trying to distribute token '.$transid);
      while($rs && $o=$rs->fetch()) {
	$transok=getDB()->count('SELECT count(distinct WFTRANSITION.KOID) FROM WFPLACE,WFTRANSITION,WFARC,WFWORKITEM WHERE WFPLACE.KOID=? AND WFARC.direction="out" AND '.
				'WFARC.transid=WFTRANSITION.KOID AND WFPLACE.KOID=WFARC.placeid AND (WFARC.precondition=WFWORKITEM.result OR WFARC.precondition="" '.
				'OR WFARC.precondition="-") AND WFWORKITEM.status in ("cancelled","finished") AND WFWORKITEM.caseid=? AND WFWORKITEM.transid=WFTRANSITION.KOID',
				array($o['KOID'], $caseid));
	$this->log($caseid, "transitions : $transtotal, ok: $transok");
	if($transok>=1) {
	  $updated=true;
	  $this->_createToken($wfid, $caseid, $o['KOID'], $o['title']);
	}
      }
    }
    return $updated;
  }


  /// creation d'un token
  private function _createToken($wfid, $caseid, $placeid, $title=NULL) {
    $this->log($caseid, 'Create token '.$title.' status free');
    $this->_tokens->procInput(array('wfid'=>$wfid, 'caseid'=>$caseid, 'placeid'=>$placeid,
				    'status'=>'free', 'enabled'=>date('Y-m-d H:i:s'),
				    'cancelled'=>TZR_DATETIME_EMPTY,
				    'consumed'=>TZR_DATETIME_EMPTY,
				    '_local'=>1));
  }


  /// introduit un log dans le workflow pour permettre de suivre les etapes
  function log($caseid, $message) {
    $d=$this->_cases->rDisplay($caseid);
    \Seolan\Core\Logs::debug('\Seolan\Module\Workflow\Workflow::log::'.$message);
    $this->_cases->procEdit(array('oid'=>$caseid, 'log'=>$d['olog']->raw."\n".date('Y-m-d H:i:s').':'.$message, '_local'=>1));
  }

  /// graph 
  function graphit($wfid) {
    $txt="digraph WorkFlow {\n";
	
    $txt.="node [shape=box, fontsize=10,fixedsize=true,width=0.5];";
    $rs=getDB()->select('SELECT * FROM WFTRANSITION WHERE wfid=? ORDER BY KOID', array($wfid));
    $t=1;
    while($rs && $o=$rs->fetch()) {
      $n[$o['KOID']]='t'.$t++;
      $txt.= $n[$o['KOID']]."[label = \"".$o['title']."\"];\n";
    }
    $txt.="\n";

    $txt.='node [shape=circle,fixedsize=true,width=0.5];';
    $rs=getDB()->select("SELECT * FROM WFPLACE WHERE wfid=? ORDER BY KOID", array($wfid));
    $p=1;
    while($rs && $o=$rs->fetch()) {
      $n[$o['KOID']]='p'.$p++;
      $txt.= $n[$o['KOID']]."[label = \"".$o['title']."\"];\n";
    }
    $txt.="\n";

    $txt.='edge [fontsize=8];';
    $rs=getDB()->select("SELECT * FROM WFARC WHERE wfid=? ORDER BY KOID", array($wfid));
    while($rs && $o=$rs->fetch()) {
      if($o['direction']=='in') {
	$txt.=$n[$o['placeid']].'->'.$n[$o['transid']]."[label = \"".$o['precondition']."\"];\n";
      } else {
	$txt.=$n[$o['transid']].'->'.$n[$o['placeid']]."[label = \"".$o['precondition']."\"];\n";
      }
    }
      
    $txt.="overlap=false\n";
    $txt.="fontsize=9\n";
    $txt.="}\n";
    //neato -Tpng thisfile > thisfile.png
    return $txt;
  }

  /// notification a un utilisateur
  function _notify2User($caseid, $title, $message, $user=NULL) {
    $case = $this->_cases->rDisplay($caseid);
    $wf = $this->xset->rDisplay($case['owfid']->raw);
    // pas de notification si propriété notif non renseignée
    if(!fieldExists('WFWORKFLOW','notif') || ($wf['onotif']->raw!='2')) {
      $uid=$case['oOWN']->raw;
      $u=new \Seolan\Core\User(array('UID'=>$case['oOWN']->raw));
      $docxset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$case['odoc']->raw);
      if(!empty($docxset) && \Seolan\Core\Kernel::objectExists($case['odoc']->raw)) {
        $document = $docxset->rDisplayText($case['odoc']->raw);
        $documentlink=$document['link'];
      }
      if(!empty($case['omodid']->raw)) {
        $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'&moid='.$case['omodid']->raw.
          '&function=goto1&oid='.$case['odoc']->raw.'&tplentry=br&template=Core.message.html&_direct=1';
      }
      $wftitle = $case['owfid']->text;
      $body='<html><body><p><b>Processus: </b>'.$wftitle.'<br/><b>Document : </b><a href="'.$url.'">'.$documentlink.'</a></p><p>'.$message.'</p></body></html>';
      $subject=$wftitle.' '.$title;
      $u->sendMail2User($subject, $body, NULL, NULL, true, NULL, NULL, NULL, NULL, array('sign'=>true));
      unset($u);
    }
  }


  /// terminer un cas
  function endCase($caseid) {
    $this->log($caseid, 'Status end reached, tokens and cases closed');
    $this->_cases->procEdit(array('oid'=>$caseid, 'status'=>'closed', 'enddate'=>date('Y-m-d H:i:s'),'_local'=>1));
    $case = $this->_cases->rDisplay($caseid);

    /// fermeture de tous les tokens en attente
    $rs=getDB()->select('SELECT DISTINCT WFTOKEN.KOID FROM WFTOKEN,WFCASE WHERE WFTOKEN.caseid=WFCASE.KOID AND WFTOKEN.status!="consumed"');
    while($rs && $token=$rs->fetch()) {
      $tokenid=$token['KOID'];
      $this->_tokens->procEdit(array('oid'=>$tokenid, 'status'=>'consumed','_local'=>1));
    }

    // suppression de toutes les règles temporaires de sécurité qui ont pu être posée
    \Seolan\Core\User::get_user()->clearUserAccess(NULL, $case['omodid']->raw, TZR_DEFAULT_LANG, $case['odoc']->raw, NULL, $caseid);

    // suppression des token et des tâches en attente. Elles ne sont plus nécessaires une fois le workflow terminé
    getDB()->execute('DELETE FROM WFTOKEN, WFWORKITEM USING WFTOKEN, WFCASE, WFWORKITEM WHERE WFCASE.KOID = ? '.
		     'AND WFTOKEN.caseid = WFCASE.KOID AND WFWORKITEM.caseid = WFCASE.KOID', array($caseid));
    
    // notification de la fin du process
    $this->_notify2User($caseid, NULL, \Seolan\Core\Labels::getSysLabel('Seolan_Module_Workflow_Workflow','caseended'));
  }
  
  /// callback pour une édition
  function editDocumentCallback($oid, $module) {
    $cases=$this->_pendingCases($oid);
    foreach($cases as $case) {
      $caseoid=$case['KOID'];
      $case=$this->_cases->rDisplay($caseoid);
      $context=$this->getCaseContext($case);
      if(!empty($context['_fieldssec'])) {
	$module->loadFieldsSec1($context['_fieldssec']);
	$this->log($caseoid, 'Set rights to '.var_export($context['_fieldssec'],true));
      }
    }
  }
}

