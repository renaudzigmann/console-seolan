<?php
namespace Seolan\Core;

class Batch {
  public $xset=NULL;
  function __construct() {
    if(!\Seolan\Core\System::tableExists('_BATCH')) $this->createStructure();
    $this->xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_BATCH');
  }
  /// creation de la strucure de la base de données des bouts de code
  function createStructure() {
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']='_BATCH';
    $ar1['bname'][TZR_DEFAULT_LANG]='System - Batchcode';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_BATCH');
    //                                                                s,o,c,q,b,t,m,p,t
    $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',                100,0,1,1,1,0,0,1);
    $x->createField('status','Etat','\Seolan\Field\ShortText\ShortText',                20,0,1,1,1,0,0,1);
    $x->createField('task','Tache','\Seolan\Field\Text\Text',       50,0,0,0,0,0,0,0,'',
		    array('arrow2link'=>false));
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
  }

  /// ajout d'un bout de code à exécuter
  function addAction($title, $code) {
    $actions = getDB()->fetchRow('SELECT * FROM _BATCH where status=? and task=? LIMIT 1',array('pending',$code));
    if(empty($actions)) {
      $this->xset->procInput(array('title'=>$title, 'status'=>'pending', 'task'=>$code, '_local'=>true, '_nolog'=>true, 'options' => ['task' => ['raw' => 1]]));
    }
  }

  /// exécution de tous les bouts de code en attente, par ordre de dépot. Attention, cette fonction est faite pour être appelée depuis le scheduler.
  function execute() {
    $lck=\Seolan\Library\Lock::getLock('xbatch_execute');
    if (!$lck) return;
    $actions = getDB()->fetchAll('SELECT * FROM _BATCH where status=? order BY UPD ASC',array('pending'));
    foreach($actions as $action) {
      try {
	eval($action['task']);
	$this->xset->procEdit(array('status'=>'done', 'oid'=>$action['KOID'], '_local'=>true));
      } catch(\Exception $e) {
	$this->xset->procEdit(array('status'=>'crashed', 'oid'=>$action['KOID'], '_local'=>true));
        bugWarning($action['task'].' crashed');
      }
    }
    getDB()->execute('DELETE FROM _BATCH WHERE UPD<DATE_SUB(curDate(),INTERVAL 30 MINUTE) AND status in ("done","crashed")');
    \Seolan\Library\Lock::releaseLock($lck);
  }
}
