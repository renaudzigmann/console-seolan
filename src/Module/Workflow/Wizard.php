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
    $mod2->_module->modulename='Tâches en cours';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table='WFWORKITEM';
    $mod2->_module->trackchanges=0;
    $mod2->_module->home=false;
    $worksmoid=$mod2->iend();
	
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename='Tâches';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table='WFTASKS';
    $mod2->_module->trackchanges=0;
    $mod2->_module->ssmod1=$worksmoid;
    $tasksmoid=$mod2->iend();
	
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
    $op=[];
    $op['options']['submodmax']=3;
    $mod->procEditProperties($op);
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod=\Seolan\Core\Module\Module::singletonFactory(XMODWORKFLOW_TOID);
    $op['options']['ssmod1']=$transmoid;
    $op['options']['ssmod2']=$placemoid;
    $op['options']['ssmod3']=$arcmoid;
    $mod->procEditProperties($op);

    $tasksmod=\Seolan\Core\Module\Module::objectFactory($tasksmoid);
    $taskoid=$tasksmod->procInsert(["title"=>"Attente validation", "descr"=>"Attente de la validation d'une fiche",
				    "myclass"=>"\Seolan\Module\Workflow\Task\Published", "parameters"=>"", "properties"=>""])["oid"];

    // génération d'un worflow de test
    $wfid=$mod->procInsert(["PUBLISH"=>1, "title"=>"Workflow de test", "descr"=>"Workflow généré automatiquement pour test",
                            "funct"=>"display, edit", "source"=>"", "trig"=>"user",
                            "modid" => \Seolan\Core\Module\Module::getMoid(XMODUSER2_TOID),
                            "grps"=>"GRP:1", "deldays"=>2,
                            "parameters"=>"LABEL=Groupe valideur;FTYPE=\Seolan\Field\Link\Link;FIELD=grp;TARGET=GRP"])["oid"];

    $placemod=\Seolan\Core\Module\Module::objectFactory($placemoid);
    $oidplacestart=getDB()->fetchOne("SELECT KOID FROM WFPLACE WHERE wfid=? and type=?",[$wfid, "start"]);
    $oidplaceend=getDB()->fetchOne("SELECT KOID FROM WFPLACE WHERE wfid=? and type=?",[$wfid, "end"]);
    
    $arcmod=\Seolan\Core\Module\Module::objectFactory($arcmoid);
    $transmod=\Seolan\Core\Module\Module::objectFactory($transmoid);
    $oidtrans=$transmod->procInsert(["title"=>"Attente validation", "descr"=>"Attente validation du document", "wfid"=>$wfid, "parameters"=>"grp",
				     "trig"=>"", "timelimit"=>0, "taskid"=>$taskoid])["oid"];
    $oidarc=$arcmod->procInsert(["wfid"=>$wfid, "placeid"=>$oidplacestart,"direction"=>"in", "transid"=>$oidtrans])["oid"];
    $oidarc2=$arcmod->procInsert(["wfid"=>$wfid, "placeid"=>$oidplaceend,"direction"=>"out", "transid"=>$oidtrans])["oid"];
    
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
      //                                                                                       s,o,c,q,b,t,m,p,t
      $x->createField('trig','Evénement de déclenchement','\Seolan\Field\StringSet\StringSet',20,0,1,1,1,0,0,0);
      $x->createField('graph','Représentation graphique','\Seolan\Field\Image\Image',   0,0,0,0,0,0,0,0);
      $x->createField('parameters','Paramètres','\Seolan\Field\Options\Options',          0,0,0,0,0,0,0,0);
      $x->createField('conds','Condition de déclenchement','\Seolan\Field\Text\Text', 60,0,0,0,0,0,0,0);
      //                                                                  s,o,c,q,b,t,m,p,t
      $x->createField('deldays','Supprimer les processus clos','\Seolan\Field\Real\Real',0,0,0,1,0,0,0,0,'%',
                      array('decimal'=>'0',
                            'comment'=>'Nombre de jours au boût duquel les processus terminés sont supprimés',
                            'acomment'=>'Nombre de jours au boût duquel les processus terminés sont supprimés'));
      $states = [
		 ["ID" => "auto", "TEXT" => "Automatique", "ORDER" => 1],
		 ["ID" => "user", "TEXT" => "Manuel", "ORDER" => 2],
		 ];
      
      $newtable=$wf;
      $field="trig";
      foreach ($states as $state) {
	if(!getDB()->fetchExists("select 1 from SETS where STAB=? and FIELD=? and SOID=?", array($newtable, $field, $state["ID"]))) {
        getDB()->execute("insert into SETS (SOID, STAB, FIELD, SLANG, STXT, SORDER) values (?, ?, ?, ?, ?, ?)",
			 [$state["ID"], $newtable, $field, TZR_DEFAULT_LANG, $state["TEXT"], $state["ORDER"]]);
	}
      }


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

      //                                                                     s,o,c,q,b,t,m,p,t
      $x->createField('wfid','Workflow','\Seolan\Field\Link\Link',      0,0,1,1,1,0,0,0,'WFWORKFLOW');
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText', 100,0,1,1,1,0,0,1);
      $x->createField('descr','Description','\Seolan\Field\Text\Text', 50,0,1,0,0,0,0,0);
      $x->createField('type','Type','\Seolan\Field\StringSet\StringSet',    20,1,1,1,1,0,0,0,array('comment'=>'start ou end ou vide'));

      $states = [
		 ['ID' => 'start', 'TEXT' => 'Début', 'ORDER' => 1],
		 ['ID' => 'end', 'TEXT' => 'Fin', 'ORDER' => 2],
		 ['ID' => 'other', 'TEXT' => 'Autre', 'ORDER' => 3],
		 ];
      
      $newtable=$wf;
      $field="type";
      foreach ($states as $state) {
	if(!getDB()->fetchExists("select 1 from SETS where STAB=? and FIELD=? and SOID=?", array($newtable, $field, $state["ID"]))) {
        getDB()->execute("insert into SETS (SOID, STAB, FIELD, SLANG, STXT, SORDER) values (?, ?, ?, ?, ?, ?)",
			 [$state["ID"], $newtable, $field, TZR_DEFAULT_LANG, $state["TEXT"], $state["ORDER"]]);
	}
      }
    }

    $wf='WFTASKS';
    if(!\Seolan\Core\System::tableExists($wf)) {
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']=$wf;
      $ar1['bname'][TZR_DEFAULT_LANG]='Workflow - Tâches';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$wf);
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));

      //                                                s,o,c,q,b,t,m,p,t
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText', 100,0,1,1,1,0,0,1);
      $x->createField('descr','Description','\Seolan\Field\Text\Text',50,0,1,0,0,0,0,0);
      $x->createField('tocheck','Condition à verifier','\Seolan\Field\Text\Text',50,0,0,0,0,0,0,0);
      $x->createField('myclass','Classe','\Seolan\Field\ShortText\ShortText',200,0,0,0,1,0,0,0);
      $x->createField('parameters','Paramètres','\Seolan\Field\Options\Options',          0,0,0,0,0,0,0,0);
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
      $x->createField('trig','Evénement de déclenchement','\Seolan\Field\ShortText\ShortText',
                                                         20,0,1,0,1,0,0,0);
      $x->createField('timelimit','Temps maximum','\Seolan\Field\Real\Real',20,0,0,0,1,0,0,0, '%', array('decimal'=>'0','edit_format'=>'', 'default'=>'0'));
      //                                                  s,o,c,q,b,t,m,p,t
      $x->createField('parameters','Paramètres','\Seolan\Field\ShortText\ShortText',   100,0,1,1,1,0,0,1);
      $x->createField('taskid','Tâche','\Seolan\Field\Link\Link',        0,0,1,1,0,0,0,0,'WFTASKS');
      $x->createField('roleid','Rôle','\Seolan\Field\Link\Link',         0,0,1,1,0,0,0,0,'USERS');
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
      //                                                                           s,o,c,q,b,t,m,p,t
      $x->createField('direction','Direction','\Seolan\Field\StringSet\StringSet',30,0,1,1,0,0,0,1);
      $x->createField('type','Type arc','\Seolan\Field\ShortText\ShortText',      30,0,1,0,0,0,0,0);
      $x->createField('precondition','Précondition','\Seolan\Field\ShortText\ShortText',30,0,1,1,0,0,0,0);
      $states = [
		 ['ID' => 'in', 'TEXT' => 'In', 'ORDER' => 1],
		 ['ID' => 'out', 'TEXT' => 'Out', 'ORDER' => 1],
		 ];

      $newtable=$wf;
      $field="direction";
      foreach ($states as $state) {
	if(!getDB()->fetchExists("select 1 from SETS where STAB=? and FIELD=? and SOID=?", array($newtable, $field, $state["ID"]))) {
        getDB()->execute("insert into SETS (SOID, STAB, FIELD, SLANG, STXT, SORDER) values (?, ?, ?, ?, ?, ?)",
			 [$state["ID"], $newtable, $field, TZR_DEFAULT_LANG, $state["TEXT"], $state["ORDER"]]);
	}
      }
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
      //                                                                     s,o,c,q,b,t,m,p,t
      $x->createField('wfid','Workflow','\Seolan\Field\Link\Link',           0,0,1,1,1,0,0,1,'WFWORKFLOW');
      $x->createField('status','Status','\Seolan\Field\StringSet\StringSet', 0,1,1,1,1,0,0,0);
      $x->createField('startdate','Date de début','\Seolan\Field\DateTime\DateTime',30,0,1,1,1,0,0,0);
      $x->createField('enddate','Date de fin','\Seolan\Field\DateTime\DateTime',30,0,1,1,1,0,0,0);
      $x->createField('context','Contexte','\Seolan\Field\Text\Text',   50,0,0,0,0,0,0,0);
      $x->createField('doc','Documents','\Seolan\Field\Link\Link',       0,0,1,1,1,0,0,1,'%');
      $x->createField('log','Journal','\Seolan\Field\Text\Text',        70,0,0,0,0,0,0,0);
      $x->createField('modid','Module','\Seolan\Field\Module\Module',     70,0,0,0,0,0,0,0);

      $states = [
		 ['ID' => 'open', 'TEXT' => 'En cours', 'ORDER' => 1],
		 ['ID' => 'cancelled', 'TEXT' => 'Annulé', 'ORDER' => 2],
		 ['ID' => 'closed', 'TEXT' => 'Terminé', 'ORDER' => 3],
		 ];

      $newtable=$wf;
      $field="status";
      foreach ($states as $state) {
	if(!getDB()->fetchExists("select 1 from SETS where STAB=? and FIELD=? and SOID=?", array($newtable, $field, $state["ID"]))) {
        getDB()->execute("insert into SETS (SOID, STAB, FIELD, SLANG, STXT, SORDER) values (?, ?, ?, ?, ?, ?)",
			 [$state["ID"], $newtable, $field, TZR_DEFAULT_LANG, $state["TEXT"], $state["ORDER"]]);
	}
      }
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
      $ar1['bname'][TZR_DEFAULT_LANG]='Workflow - Tâches en cours';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$wf);
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
      //                                                      s,o,c,q,b,t,m,p,t
      $x->createField('wfid','Workflow','\Seolan\Field\Link\Link',           0,0,1,1,1,0,0,1,'WFWORKFLOW');
      $x->createField('transid','Transition','\Seolan\Field\Link\Link',      0,0,1,1,1,0,0,1,'WFTRANSITION');
      $x->createField('caseid','Cas','\Seolan\Field\Link\Link',              0,0,1,1,1,0,0,1,'WFCASE');
      $x->createField('taskid','Tâche','\Seolan\Field\Link\Link',            0,0,1,1,1,0,0,1,'WFTASKS');
      $x->createField('userid','Utilisateur','\Seolan\Field\Link\Link',      0,0,0,1,1,0,0,1,'USERS');

      //                                                                 s,o,c,q,b,t,m,p,t
      $x->createField('context','Contexte','\Seolan\Field\Text\Text',                  50,0,0,0,0,0,0,0);
      $x->createField('status','Status','\Seolan\Field\ShortText\ShortText',                30,0,1,1,1,0,0,0);
      $x->createField('result','Résultat de traitement','\Seolan\Field\ShortText\ShortText',30,0,0,1,1,0,0,0);
      $x->createField('cancelled','Date annulation','\Seolan\Field\DateTime\DateTime',      0,0,0,1,0,0,0,0);
      $x->createField('enabled','Date activation','\Seolan\Field\DateTime\DateTime',        0,0,0,1,1,0,0,0);
      $x->createField('finished','Date fin','\Seolan\Field\DateTime\DateTime',              0,0,0,1,0,0,0,0);
      $x->createField('deadline','Date limite','\Seolan\Field\DateTime\DateTime',           0,0,0,1,0,0,0,0);
    }
  }


}
  

?>
