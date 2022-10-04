<?php
namespace Seolan\Module\Workflow;

class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    if(!\Seolan\Core\System::tableExists('WFWORKFLOW')) $this->createStructure();
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('\Seolan\Module\Workflow\Wizard');
    $this->_module->table='WFWORKFLOW';
    $this->_module->group='Workflow';
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Workflow_Workflow','modulename');
  }

  function istep1() {
    parent::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','table'), 'table', 'table', 
			    array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','modulename'), 'modulename', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','group'), 'group', 'text');
    $this->_options->setRO('table');
    $this->_options->setRO('modulename');
    $this->_options->setRO('group');
  }
  function iend($ar=NULL) {
    $this->_module->table='WFWORKFLOW';
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Workflow_Workflow','modulename');
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Workflow_Workflow','modulename');

    parent::iend();

    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename='Processus en cours';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table='WFCASE';
    $mod2->_module->trackchanges=0;
    $casemoid=$mod2->iend();
	
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename='Transition';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table='WFTRANSITION';
    $mod2->_module->trackchanges=0;
    $mod2->_module->home=false;
    $transmoid=$mod2->iend();
	
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename='Etats';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table='WFPLACE';
    $mod2->_module->trackchanges=0;
    $mod2->_module->home=false;
    $placemoid=$mod2->iend();
	
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename='Arcs';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table='WFARC';
    $mod2->_module->trackchanges=0;
    $mod2->_module->home=false;
    $arcmoid=$mod2->iend();
	
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename='Taches en cours';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table='WFWORKITEM';
    $mod2->_module->trackchanges=0;
    $mod2->_module->home=false;
    $worksmoid=$mod2->iend();
	
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename='Taches';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table='WFTASKS';
    $mod2->_module->trackchanges=0;
    $mod2->_module->ssmod1=$worksmoid;
    $tasksmoid=$mod2->iend();
	
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
    $ar['options']['ssmod1']=$transmoid;
    $ar['options']['ssmod2']=$placemoid;
    $ar['options']['ssmod3']=$arcmoid;
    $mod->procEditProperties($ar);
  }

  // Creation des structures
  public function createStructure() {
    $module=(array)$this->_module;
    $modulename=$module["modulename"];

    $wf='WFWORKFLOW';
    if(!\Seolan\Core\System::tableExists($wf)) {
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']=$wf;
      $ar1['bname'][TZR_DEFAULT_LANG]='Workflow - Processus';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$wf);
      //                                                                s,o,c,q,b,t,m,p,t
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',                100,0,1,1,1,0,0,1);
      $x->createField('descr','Description','\Seolan\Field\Text\Text',                50,0,1,0,0,0,0,0);
      $x->createField('funct','Fonction','\Seolan\Field\Text\Text',                50,0,1,0,0,0,0,0);
      $x->createField('source','Source','\Seolan\Field\Text\Text',                50,0,0,0,0,0,0,0);
      $x->createField('modid','Module applicable','\Seolan\Field\Module\Module',         0,0,1,0,1,0,1,0);
      $x->createField('grps','Groupes applicables','\Seolan\Field\Link\Link',          0,0,1,0,1,0,1,0,'GRP');
      $x->createField('trig','Evenement de declenchement','\Seolan\Field\ShortText\ShortText',20,0,1,0,1,0,0,0);
      $x->createField('graph','Representation graphique','\Seolan\Field\Image\Image',   0,0,0,0,0,0,0,0);
      $x->createField('parameters','Parametres','\Seolan\Field\Options\Options',          0,0,0,0,0,0,0,0);
      $x->createField('conds','Condition de declenchement','\Seolan\Field\Text\Text', 60,0,0,0,0,0,0,0);
      //                                                                  s,o,c,q,b,t,m,p,t
      $x->createField('deldays','Supprimer les processus clos','\Seolan\Field\Real\Real',0,0,0,1,0,0,0,0,'%',
		      array('comment'=>'Nombre de jours au bout duquel les processus termines sont supprimes',
			    'acomment'=>'Nombre de jours au bout duquel les processus termines sont supprimes'));
    }
    $wf='WFPLACE';
    if(!\Seolan\Core\System::tableExists($wf)) {
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']=$wf;
      $ar1['bname'][TZR_DEFAULT_LANG]='Workflow - Etats';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$wf);
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));

      //                                                 s,o,c,q,b,t,m,p,t
      $x->createField('wfid','Workflow','\Seolan\Field\Link\Link',      0,0,1,1,1,0,0,0,'WFWORKFLOW');
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText', 100,0,1,1,1,0,0,1);
      $x->createField('descr','Description','\Seolan\Field\Text\Text', 50,0,1,0,0,0,0,0);
      $x->createField('type','Type','\Seolan\Field\ShortText\ShortText',    20,0,0,1,1,0,0,0,array('comment'=>'start ou end ou vide'));
    }

    $wf='WFTASKS';
    if(!\Seolan\Core\System::tableExists($wf)) {
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']=$wf;
      $ar1['bname'][TZR_DEFAULT_LANG]='Workflow - Taches';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$wf);
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));

      //                                                s,o,c,q,b,t,m,p,t
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText', 100,0,1,1,1,0,0,1);
      $x->createField('descr','Description','\Seolan\Field\Text\Text',50,0,1,0,0,0,0,0);
      $x->createField('task','Tache','\Seolan\Field\Text\Text',       50,0,0,0,0,0,0,0);
      $x->createField('tocheck','Condition a verifier','\Seolan\Field\Text\Text',50,0,0,0,0,0,0,0);
      $x->createField('class','Classe','\Seolan\Field\ShortText\ShortText',20,0,0,0,1,0,0,0);
      $x->createField('parameters','Parametres','\Seolan\Field\Options\Options',          0,0,0,0,0,0,0,0);
      $x->createField('properties',\Seolan\Core\Labels::getSysLabel('general.properties','text'),'\Seolan\Field\Text\Text',          80,0,0,0,0,0,0,0);
    }

    $wf='WFTRANSITION';
    if(!\Seolan\Core\System::tableExists($wf)) {
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']=$wf;
      $ar1['bname'][TZR_DEFAULT_LANG]='Workflow - Transitions';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$wf);
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));

      //                                                  s,o,c,q,b,t,m,p,t
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',   100,0,1,1,1,0,0,1);
      $x->createField('descr','Description','\Seolan\Field\Text\Text',  50,0,1,0,0,0,0,0);
      $x->createField('wfid','Workflow','\Seolan\Field\Link\Link',       0,0,1,1,1,0,0,0,'WFWORKFLOW');
      $x->createField('trig','Evenement de declenchement','\Seolan\Field\ShortText\ShortText',
                                                         20,0,1,0,1,0,0,0);
      $x->createField('timelimit','Temps maximum','\Seolan\Field\Real\Real',20,0,0,0,1,0,0,0, '%', array('decimals'=>'0','edit_format'=>'', 'default'=>'0'));
      //                                                  s,o,c,q,b,t,m,p,t
      $x->createField('taskid','Tache','\Seolan\Field\Link\Link',        0,0,1,1,0,0,0,0,'WFTASKS');
      $x->createField('roleid','Role','\Seolan\Field\Link\Link',         0,0,1,1,0,0,0,0,'USERS');
    }

    $wf='WFARC';
    if(!\Seolan\Core\System::tableExists($wf)) {
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']=$wf;
      $ar1['bname'][TZR_DEFAULT_LANG]='Workflow - Arcs';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$wf);
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));

      //                                                  s,o,c,q,b,t,m,p,t
      $x->createField('wfid','Workflow','\Seolan\Field\Link\Link',       0,0,1,1,1,0,0,0,'WFWORKFLOW');
      $x->createField('transid','Transition','\Seolan\Field\Link\Link',  0,0,1,1,1,0,0,1,'WFTRANSITION');
      $x->createField('placeid','Etat','\Seolan\Field\Link\Link',        0,0,1,1,1,0,0,1,'WFPLACE');
      //                                                       s,o,c,q,b,t,m,p,t
      $x->createField('direction','Direction','\Seolan\Field\ShortText\ShortText',30,0,1,1,0,0,0,1);
      $x->createField('type','Type arc','\Seolan\Field\ShortText\ShortText',      30,0,1,1,0,0,0,0);
      $x->createField('precondition','Precondition','\Seolan\Field\ShortText\ShortText',30,0,1,1,0,0,0,0);
    }

    $wf='WFCASE';
    if(!\Seolan\Core\System::tableExists($wf)) {
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']=$wf;
      $ar1['bname'][TZR_DEFAULT_LANG]='Workflow - Cas';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$wf);
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
      //                                                  s,o,c,q,b,t,m,p,t
      $x->createField('wfid','Workflow','\Seolan\Field\Link\Link',       0,0,1,1,1,0,0,1,'WFWORKFLOW');
      $x->createField('status','Status','\Seolan\Field\ShortText\ShortText', 30,0,1,1,1,0,0,0);
      $x->createField('startdate','Date de debut','\Seolan\Field\DateTime\DateTime',30,0,1,1,1,0,0,0);
      $x->createField('enddate','Date de fin','\Seolan\Field\DateTime\DateTime',30,0,1,1,1,0,0,0);
      $x->createField('context','Contexte','\Seolan\Field\Text\Text',   50,0,0,0,0,0,0,0);
      $x->createField('doc','Documents','\Seolan\Field\Link\Link',       0,0,1,1,1,0,0,1,'%');
      $x->createField('log','Journal','\Seolan\Field\Text\Text',        70,0,0,0,0,0,0,0);
      $x->createField('modid','Module','\Seolan\Field\Module\Module',     70,0,0,0,0,0,0,0);
    }

    $wf='WFTOKEN';
    if(!\Seolan\Core\System::tableExists($wf)) {
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']=$wf;
      $ar1['bname'][TZR_DEFAULT_LANG]='Workflow - Jetons';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$wf);
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
      //                                                  s,o,c,q,b,t,m,p,t
      $x->createField('wfid','Workflow','\Seolan\Field\Link\Link',       0,0,1,1,1,0,0,1,'WFWORKFLOW');
      $x->createField('placeid','Etat','\Seolan\Field\Link\Link',        0,0,1,1,1,0,0,1,'WFPLACE');
      $x->createField('caseid','Cas','\Seolan\Field\Link\Link',          0,0,1,1,1,0,0,1,'WFCASE');
      $x->createField('cancelled','Date annulation','\Seolan\Field\DateTime\DateTime',0,0,1,1,1,0,0,1);
      $x->createField('enabled','Date activation','\Seolan\Field\DateTime\DateTime',  0,0,1,1,1,0,0,1);
      $x->createField('consumed','Date consommation','\Seolan\Field\DateTime\DateTime',0,0,1,1,1,0,0,1);
      $x->createField('status','Status','\Seolan\Field\ShortText\ShortText', 30,0,1,1,1,0,0,1);
      //                                                  s,o,c,q,b,t,m,p,t
    }

    $wf='WFWORKITEM';
    if(!\Seolan\Core\System::tableExists($wf)) {
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']=$wf;
      $ar1['bname'][TZR_DEFAULT_LANG]='Workflow - Taches en cours';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$wf);
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
      //                                                      s,o,c,q,b,t,m,p,t
      $x->createField('wfid','Workflow','\Seolan\Field\Link\Link',           0,0,1,1,1,0,0,1,'WFWORKFLOW');
      $x->createField('transid','Transition','\Seolan\Field\Link\Link',      0,0,1,1,1,0,0,1,'WFTRANSITION');
      $x->createField('caseid','Cas','\Seolan\Field\Link\Link',              0,0,1,1,1,0,0,1,'WFCASE');
      $x->createField('taskid','Tache','\Seolan\Field\Link\Link',            0,0,1,1,1,0,0,1,'WFTASKS');
      $x->createField('userid','Utilisateur','\Seolan\Field\Link\Link',      0,0,0,1,1,0,0,1,'USERS');

      //                                                                 s,o,c,q,b,t,m,p,t
      $x->createField('context','Contexte','\Seolan\Field\Text\Text',                  50,0,0,0,0,0,0,0);
      $x->createField('status','Status','\Seolan\Field\ShortText\ShortText',                30,0,1,1,1,0,0,0);
      $x->createField('result','Resultat de traitement','\Seolan\Field\ShortText\ShortText',30,0,0,1,1,0,0,0);
      $x->createField('cancelled','Date annulation','\Seolan\Field\DateTime\DateTime',      0,0,0,1,0,0,0,0);
      $x->createField('enabled','Date activation','\Seolan\Field\DateTime\DateTime',        0,0,0,1,1,0,0,0);
      $x->createField('finished','Date fin','\Seolan\Field\DateTime\DateTime',              0,0,0,1,0,0,0,0);
      $x->createField('deadline','Date limite','\Seolan\Field\DateTime\DateTime',           0,0,0,1,0,0,0,0);
    }
  }
}
  

?>
