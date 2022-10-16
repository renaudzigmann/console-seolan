<?php
namespace Seolan\Module\Workflow;
/// classe mere des travaux a realiser dans un workflow
class Task {
  protected $_wfmodule=NULL;
  protected $_workid=NULL;

  /// creation d'un travail a partir de son identificant
  static function objectFactory(\Seolan\Core\Module\Module $module, $workid) {
    try {
      $work=$module->_works->rDisplay($workid);
      $task = $module->_tasks->rDisplay($work['otaskid']->raw);
      $classe=$task['omyclass']->text;
      if(empty($classe)) $classe='\Seolan\Module\Workflow\Task';
      if(!class_exists($classe)) throw new \Exception();
      return new $classe($module, $workid);
    }
    catch(\Exception $e) {
      \Seolan\Core\Logs::critical('\Seolan\Module\Workflow\Tasks::objectFactory',"could create object for work $workid ($classe)");
      return NULL;
    }
  }

  /// constructeur
  function __construct(\Seolan\Core\Module\Module $workflow, $workid) {
    $this->_workid = $workid;
    $this->_wfmodule = $workflow;
    $this->work=      $this->_wfmodule->_works->rDisplay($this->_workid);
    $this->workflow = $this->_wfmodule->xset->rDisplay($this->work['owfid']->raw);
    $this->transition = $workflow->_transitions->rDisplay($this->work['otransid']->raw);
    $this->task =     $workflow->_tasks->rDisplay($this->work['otaskid']->raw);
    $this->case =     $workflow->_cases->rDisplay($this->work['ocaseid']->raw);
    $this->caseid=$this->work['ocaseid']->raw;
    $this->context = unserialize($this->case['ocontext']->raw);
    $this->docid = $this->case['odoc']->raw;
    $this->modid=$this->case['omodid']->raw;

    $docxset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->case['odoc']->raw);
    if(!empty($docxset) && \Seolan\Core\Kernel::objectExists($this->docid)) {
      $this->document = $docxset->rDisplayText($this->docid);
    }
  }

  
  /// fonction d'aide, rend vrai si le champ field a ete mis a jour lors de la derniere mise a jour
  function fieldUpdated($DOCUMENT, $field) {
    $log=\Seolan\Core\Logs::decodeEvent($DOCUMENT['lst_upd']);
    if(isset($log['details'][$field])) return true;
    else return false;
  }

  /// fonctionde progression d'une chaine de caracteres de ' A' a 'ZZ'
  function stringIncrement($str,$incr) {
    $str=trim($str);
    if(strlen($str)==1) $str='@'.$str;
    $c2=ord($str[1])-ord('A')+$incr;
    $c1=ord($str[0])-ord('A')+(int)($c2/26);
    $c2=$c2%26;
    $str=str_replace('@',' ',chr($c1+ord('A')).chr($c2+ord('A')));
    return trim($str);
  }

  /// recupérer la liste des paramètres de la transisiton
  function getParameters() {
    $parameters=$this->transition['oparameters']->raw;
    $params=explode(",", $parameters);
    $paramvalues=[];
    $i=1;
    foreach($params as $param) {
      $value=$this->getContext($param);
      if($value === NULL) {
	if(!empty($this->document["o$param"])) $paramvalues[$i]=$this->document["o$param"];
	else $paramvalues[$i]=NULL;
      } else {
	$paramvalues[$i]=$value;
      }
      $i++;
    }
    return $paramvalues;
  }

  function _check() {
  }
  function _run() {
    return ["status"=>"inprogress"];
  }
  
  /// execution de la tache, mise a jour de UPD si deja en cours d'execution
  function run() {
    if(!empty($this->work['odeadline']->raw) &&
       ($this->work['oenabled']->raw!=$this->work['odeadline']->raw) && 
       (date('Y-m-d H:i:s')>=$this->work['odeadline']->raw)) {
      $this->setStatus('finished','timeout');
    }

    $tocheck=false;
    if(!$this->work['ostatus']->raw=='inprogress') {
      $this->setStatus('inprogress');
    }

    if($this->_wfmodule->testMode(true)) {
      $this->_wfmodule->log($this->caseid, 'Document '.$this->case['odoc']->raw." run _run");
    }

    try {
      $result=$this->_run();
    } catch(\Exception $e) {
      $this->_wfmodule->log($this->caseid, 'Work '.$this->_workid);
    }

    if($result["status"]=="finished") {
      $this->setStatus('finished');
    }
    return $result;
  }


  /// changement status d'une tache
  function setStatus($status, $result=NULL) {
    $workstatus['status']=$status;
    if(!empty($result)) $workstatus['result']=$result;
    $dateupdate=date('Y-m-d H:i:s');
    if($workstatus['status']=='finished') {
      $workstatus['finished']=$dateupdate;
    }
    if($workstatus['status']=='enabled') {
      $workstatus['enabled']=$dateupdate;
    }
    if($workstatus['status']=='cancelled') {
      $workstatus['cancelled']=$dateupdate;
    }

    $workstatus['oid']=$this->_workid;
    $workstatus['UPD']=$dateupdate;
    $workstatus['_local']=1;
    $this->_wfmodule->_works->procEdit($workstatus);
    $this->_wfmodule->log($this->caseid, 'Work '.$this->_workid.' changed to '.$status.' result is '.$result);
  }


  function status() {
    return getDB()->fetchOne('SELECT status from WFWORKITEM WHERE KOID=? LIMIT 1', array($this->_workid));
  }


  function setFieldSec($field, $group, $level) {
    if(is_array($field)) {
      foreach($field as $f) {
	$this->setFieldSec($f, $group, $level);
      }
      return;
    }
    if(is_array($group)) {
      foreach($group as $g) {
	$this->setFieldSec($field, $g, $level);
      }
      return;
    }
    $rules=$this->getContext('_fieldssec');
    $set=false;
    foreach($context as &$rule) {
      if($field==$rule['field'] && $group==$rule['group']) {
	$rules['sec']=$level;
	$set=true;
      }
    }
    if(!$set) $rules[]=array('field'=>$field, 'group'=>$group, 'sec'=>$level);
    $this->setContext('_fieldssec', $rules);
  }

  /** mise en place de droits spécifiques
   * @param string $oid du document sur lequel des droits sont modifiés
   * @param array $acl table;au contenant les acls
   * @param array $usersOrGroup tableau des personnes ou groupes auxquelles cela s'applique
   */
  function setACL($userOrGroup, $level) {
    if(!is_array($userOrGroup)) $users=array($userOrGroup);
    else $users=$userOrGroup;
    foreach($users as $uoid) {
      $this->_wfmodule->log($this->caseid, 'Work '.$this->_workid.' set ACL '.$level.' for '.$uoid);
      $u = new \Seolan\Core\User($uoid);
      $u->setUserAccess(NULL, $this->case['omodid']->raw, TZR_DEFAULT_LANG, $this->case['odoc']->raw, $level, $uoid, false, true, $this->case['oid']);
      unset($u);
    }
  }

  /** reset des ACL
   * @param string $oid du document sur lequel des droits sont modifiés
   * @param array $usersOrGroup tableau des personnes ou groupes auxquelles cela s'applique
   */
  function cleanACL($userOrGroup) {
    foreach($userOrGroup as $uoid) {
      $u = new \Seolan\Core\User($uoid);
      $u->clearUserAccess(NULL, $this->_wfmodule->_moid, TZR_DEFAULT_LANG, $this->case['odoc']->raw, $uoid, $this->case['oid']);
      unset($u);
    }
  }

  /// notification a un utilisateur
  function notify($user, $title, $message, $rem=NULL) {
    // en mode test on ne diffuse qu'au propriétaire du workflow
    if($this->_wfmodule->testMode(true)) $users=array($this->case['oOWN']->raw);
    else {
      if(\Seolan\Core\User::oidIsUser($user)) {
	$users=array($user);
      } else {
	$users=\Seolan\Module\Group\Group::users($user);
      }
    }

    $docxset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->case['odoc']->raw);
    if(!empty($docxset) && \Seolan\Core\Kernel::objectExists($this->docid)) {
      $document = $docxset->rDisplayText($this->docid);
      $documentlink=$document['link'];
    }
    if(!empty($this->modid)) {
      $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'&moid='.$this->modid.
	'&function=goto1&oid='.$this->case['odoc']->raw.'&tplentry=br&template=Core.message.html&_direct=1';
    }
    $remtxt='';
    if(!empty($rem)) {
      $remtxt.='<p><b>Remarques</b><br/>'.$rem.'</p>';
    }
    
    $wftitle = $this->case['owfid']->text;
    $body='<html><body><p><b>Processus : </b>'.$wftitle.'<br/><b>Document : </b><a href="'.$url.'">'.$documentlink.'</a></p><p>'.$message.'</p>'.$remtxt.'</body></html>';
    foreach($users as $uoid) {
      $u=new \Seolan\Core\User(array('UID'=>$uoid));
      $u->sendMail2User($title, $body, NULL, NULL, true, NULL, NULL, NULL, "text/html", array('sign'=>true));
      unset($u);
    }
  }
  
  function setContext($var1, $value) {
    $this->context[$var1] = $value;
    return $this->_wfmodule->_cases->procEdit(array('context'=>serialize($this->context), 'oid'=>$this->caseid, '_local'=>true));
  }
  function getContext($var1) {
    return empty($this->context[$var1])?NULL:$this->context[$var1];
  }

  function setRo($oid, $fieldstoedit) {
    $this->setContext('_edit', $fieldstoedit);
  }
}
