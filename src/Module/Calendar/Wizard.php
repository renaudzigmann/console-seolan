<?php
namespace Seolan\Module\Calendar;
/// Wizard de creation d'un module  Agenda
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Calendar_Calendar');
  }

  function istep1() {
    parent::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','createstructure'), "createstructure", "boolean");
  }

  function istep2() {
    if($this->_module->createstructure) {
      $this->createStructure();
      $this->createStructure2();
    } else {
      $this->_options->setOpt('Agenda : table des agendas', 'tagenda', 'table');
      $this->_options->setOpt('Agenda : table des évènements', 'tevt', 'table');
      $this->_options->setOpt('Agenda : table des liens', 'tlinks', 'table');
      $this->_options->setOpt('Agenda : table des catégories d\'evenements', 'tcatevt', 'table');
      $this->_options->setOpt('Agenda : table des catégories d\'agendas', 'tcatagenda', 'table');
      $this->_options->setOpt('Planification : table des planifications', 'tplan', 'table');
      $this->_options->setOpt('Planification : table des paticipants', 'tplaninv', 'table');
      $this->_options->setOpt('Planification : table des dates', 'tplandates', 'table');
    }
  }

  function istep3() {
    $this->_options->setOpt('Catégorie "agenda personnel"', 'catperso', 'object', array('table'=>$this->_module->tcatagenda));
    $this->_options->setOpt('Vue par défaut', 'defview', 'list',
			    array('values'=>array('displayDay','displayWeek','displayMonth','displayYear'),
				  'labels'=>array('Jour','Semaine','Mois','Année')));
  }

  function iend($ar=NULL) {
    $moid=parent::iend();
    $modadm=new \Seolan\Module\Calendar\Management\Wizard(array('newmoid'=>XMODCALENDARADM_TOID));
    $modadm->_module->modulename='Gestion '.$this->_module->modulename;
    $modadm->_module->group=$this->_module->group;
    $modadm->_module->calmod=$moid;
    $modadm->_module->table=$this->_module->tagenda;
    $modadm->_module->order='name,OWN';
    $modadm->iend();
  }

  private function createStructure() {
    $module=(array)$this->_module;
    $modulename=$module[modulename];

    $tables=array();

    // TABLE DES CATEGORIES D'AGENDA
    //
    $tables[4]=\Seolan\Model\DataSource\Table\Table::newTableNumber('AG');
    $newtable=$tables[4];
    $ar1=array();
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Catégories d\'agendas';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    //                                                          size ord  obl que bro tra mul pub tar
    $x->createField('name','Nom','\Seolan\Field\ShortText\ShortText',               '60','2' ,'1','1','1','1','0','1');
    $xsetcatag=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $catperso=$xsetcatag->procInput(array('name'=>'Personnel','tplentry'=>TZR_RETURN_DATA));
    $xsetcatag->procInput(array('name'=>'Professionnel'));
    $xsetcatag->procInput(array('name'=>'Congé'));
    $xsetcatag->procInput(array('name'=>'Divers'));

    // TABLE DES AGENDAS
    //
    $tables[0]=\Seolan\Model\DataSource\Table\Table::newTableNumber('AG');
    $newtable=$tables[0];
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$tables[0];
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Agendas';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    //                                                                   size ord  obl que bro tra mul pub tar
    $x->createField('name','Nom','\Seolan\Field\ShortText\ShortText',                       '100','1' ,'1','1','1','0','0','1');
    $x->procEditField(array('field'=>'OWN','table'=>$newtable,'_todo'=>'save','forder'=>2,'target'=>'USERS',
			    'published'=>'on','queryable'=>'on','browsable'=>'on','compulsory'=>'on','translatable'=>'off',
			    'options'=>array('comment'=>array('FR'=>'Propriétaire de l\'agenda'))));
    $x->createField('cat','Catégorie','\Seolan\Field\Link\Link',                        '60','3' ,'1','1','1','0','0','0',$tables[4]);
    $x->createField('begin','Début','\Seolan\Field\Time\Time',                           '5','4' ,'1','1','1','0','0','0');
    $x->createField('end','Fin','\Seolan\Field\Time\Time',                               '5','5' ,'1','1','1','0','0','0');
    $x->createField('def','Agenda par défaut','\Seolan\Field\Boolean\Boolean',                 '0','6' ,'0','0','0','0','0','0');
    $x->createField('defvisi','Visibilitée par défaut','\Seolan\Field\ShortText\ShortText',   '2','7' ,'1','0','0','0','0','0');
    $x->createField('mail','Notifier le propriétaire des modifications de l\\\'agenda','\Seolan\Field\Boolean\Boolean','0','8','0','0','0','0','0','0');
    $x->createField('cons','Consolidation','\Seolan\Field\Boolean\Boolean',                   '60','9' ,'0','0','0','0','0','0');
    $x->createField('tz','Fuseau horaire','\Seolan\Field\ShortText\ShortText',              '100','10','1','1','0','0','0','0');
    $x->createField('agcons','Agenda pour la consolidation','\Seolan\Field\Link\Link', '100','11','0','0','0','0','1','0',$newtable);

    $x->createField('synctoken','Compteur de synchronisation CalDAV','\Seolan\Field\Real\Real', '10','12','0','0','0','0','1','0');
    $x->createField('color',    'Couleur',                           '\Seolan\Field\ShortText\ShortText', '10','13','0','0','0','0','1','0');

    $x->procEditField(array('field'=>'name','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('listbox'=>0,'comment'=>array('FR'=>'Nom de l\'agenda à l\'affichage'))));
    $x->procEditField(array('field'=>'cat','table'=>$newtable,'_todo'=>'save','target'=>$tables[4],'options'=>array('checkbox'=>0)));
    $x->procEditField(array('field'=>'begin','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('comment'=>array('FR'=>'Heure de début de l\'affichage par défaut'),
					     'display_format'=>'H:M')));
    $x->procEditField(array('field'=>'end','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('comment'=>array('FR'=>'Heure de fin de l\'affichage par défaut'),
					     'display_format'=>'H:M')));
    $x->procEditField(array('field'=>'def','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('comment'=>array('FR'=>'Cochez la case pour positionner cet agenda en tant '.
							      'qu\'agenda par défaut du propriétaire.'))));
    $x->procEditField(array('field'=>'defvisi','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('comment'=>array('FR'=>'Visibilitée par défaut des évènements '.
							      '(PU=Public, OC=Occupé, PR=Privé)'),
					     'edit_format'=>'^((PR)||(PU)||(OC))$')));
    $x->procEditField(array('field'=>'agcons','table'=>$newtable,'_todo'=>'save','target'=>$newtable,
			    'options'=>array('checkbox'=>'0','checkbox_limit'=>'0','autocomplete'=>'0','doublebox'=>1,
					     'display_format'=>'%s (%s)',
					     'comment'=>array('FR'=>'Si cet agenda est consolidé, liste des agendas à utiliser')
					     )
			    )
		      );
    
    // TABLE DES CATEGORIES D'EVENEMENTS
    //
    $tables[3]=\Seolan\Model\DataSource\Table\Table::newTableNumber('AG');
    $newtable=$tables[3];
    $ar1=array();
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Catégories d\'évènements';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    //                                                          size ord  obl que bro tra mul pub tar
    $x->createField('name','Nom','\Seolan\Field\ShortText\ShortText',               '60','2' ,'1','1','1','1','0','1');
    $x->createField('color','Couleur','\Seolan\Field\Color\Color',              '10','3' ,'1','1','1','0','0','0');
    $x->createField('recall','Rappel','\Seolan\Field\Real\Real',                '5','4' ,'1','0','1','0','0','0');
    $x->createField('time','Durée','\Seolan\Field\Real\Real',                   '5','5' ,'1','0','1','0','0','0');
    $x->createField('allday','Journée entière','\Seolan\Field\Boolean\Boolean',       '1','6' ,'0','1','1','0','0','0');
    $x->createField('commun','Catégorie commune?','\Seolan\Field\Boolean\Boolean',    '1','7' ,'0','1','1','0','0','0');
    $xsetcatev=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $xsetcatev->procInput(array('name'=>'Rendez-vous',
                                'recall'=>0,
                                'time'=>60,
				'commun'=>1,
                                'allday'=>0));
    $xsetcatev->procInput(array('name'=>'Réunion',
                                'recall'=>0,
                                'time'=>60,
				'commun'=>1,
                                'allday'=>0));
    $xsetcatev->procInput(array('name'=>'Appel',
                                'recall'=>0,
                                'time'=>60,
				'commun'=>1,
                                'allday'=>0));
    $xsetcatev->procInput(array('name'=>'Congé',
                                'recall'=>0,
                                'time'=>60,
				'commun'=>1,
                                'allday'=>1));
    $xsetcatev->procInput(array('name'=>'Anniversaire',
                                'recall'=>0,
                                'time'=>60,
				'commun'=>1,
                                'allday'=>0));

    // TABLE DES EVENEMENTS
    //
    $tables[1]=\Seolan\Model\DataSource\Table\Table::newTableNumber('AG');
    $newtable=$tables[1];
    $ar1=array();
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Evènements';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    //                                                                 size ord  obl que bro tra mul pub tar
    $x->createField('text','Intitulé','\Seolan\Field\ShortText\ShortText',                '255','2' ,'1','1','1','0','0','1');
    $x->createField('begin','Début','\Seolan\Field\DateTime\DateTime',                     '0','3' ,'1','1','1','0','0','0');
    $x->createField('end','Fin','\Seolan\Field\DateTime\DateTime',                         '0','4' ,'1','1','1','0','0','0');
    $x->createField('allday','Journée entière','\Seolan\Field\Boolean\Boolean',              '0','5' ,'0','1','1','0','0','0');
    $x->createField('cat','Catégorie','\Seolan\Field\Link\Link',		        '0','6' ,'1','1','1','0','0','0',$tables[3]);
    $x->createField('place','Lieu','\Seolan\Field\ShortText\ShortText',                   '255','7' ,'0','1','1','0','0','0');
    $x->createField('descr','Description','\Seolan\Field\Text\Text',                  '60','8' ,'0','0','0','0','0','0');
    $x->createField('visib','Visibilité','\Seolan\Field\ShortText\ShortText',              '10','9' ,'1','1','1','0','0','0');
    $x->createField('repet','Répétition','\Seolan\Field\ShortText\ShortText',              '10','10','0','0','0','0','0','0');
    $x->createField('end_rep','Fin de la répétition','\Seolan\Field\Date\Date',        '0','11','0','0','0','0','0','0');
    $x->createField('repexcept','Exception','\Seolan\Field\Text\Text',                   '60','12','0','0','0','0','0','0');
    $x->createField('rrule','Règles de répétition avancées','\Seolan\Field\Text\Text','60','13','0','0','0','0','0','0');
    $x->createField('recall','Rappel','\Seolan\Field\Real\Real',                       '5','14','0','0','0','0','0','0');
    $x->createField('isrecal','Rappel effectué','\Seolan\Field\Boolean\Boolean',             '0','15','0','0','0','0','0','0');
    $x->createField('attext','Invités extérieur','\Seolan\Field\Text\Text',           '60','16','0','0','0','0','0','0');
    $x->createField('UIDI','UID Import','\Seolan\Field\ShortText\ShortText',              '255','17','0','0','0','0','0','0');
    $x->createField('KOIDD','Agenda proprietaire','\Seolan\Field\Link\Link',           '0','18','1','0','0','0','0','0',$tables[0]);
    $x->createField('KOIDS','KOID Source','\Seolan\Field\Link\Link',                   '0','19','0','0','0','0','0','0',$tables[1]);
    $x->procEditField(array('field'=>'text','table'=>$newtable,'_todo'=>'save','options'=>array('listbox'=>'0')));
    $x->procEditField(array('field'=>'cat','table'=>$newtable,'_todo'=>'save','target'=>$tables[3],
			    'options'=>array('checkbox'=>'0','autocomplete'=>'0')));
    $x->procEditField(array('field'=>'place','table'=>$newtable,'todo'=>'save','options'=>array('listbox'=>'0')));
    $x->procEditField(array('field'=>'begin','table'=>$newtable,'_todo'=>'save','options'=>array('display_format'=>'H:M')));
    $x->procEditField(array('field'=>'end','table'=>$newtable,'_todo'=>'save','options'=>array('display_format'=>'H:M')));

    // TABLE DES LIENS AG/EV
    //
    $tables[2]=\Seolan\Model\DataSource\Table\Table::newTableNumber('AG');
    $newtable=$tables[2];
    $ar1=array();
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Liens';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    //                                                          size ord  obl que bro tra mul pub tar
    $x->createField('KOIDD','Cible','\Seolan\Field\Link\Link',		         '0','2' ,'1','1','1','0','0','1');
    $x->createField('KOIDE','Evènement','\Seolan\Field\Link\Link',              '0','3' ,'1','1','1','0','0','1',$tables[1]);

    $this->_module->tagenda=$tables[0];
    $this->_module->tevt=$tables[1];
    $this->_module->tlinks=$tables[2];
    $this->_module->tcatevt=$tables[3];
    $this->_module->tcatagenda=$tables[4];
    $this->_module->catperso=$catperso['oid'];
  }

  // Creation des structures pour planification
  public function createStructure2() {
    $module=(array)$this->_module;
    $modulename=$module[modulename];
    
    $newtable=$tplan=\Seolan\Model\DataSource\Table\Table::newTableNumber('AG');
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Planifications';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    //                                                               size ord  obl que bro tra mul pub tar
    $x->createField('ag','Agenda','\Seolan\Field\Link\Link',                         '0','2' ,'1','1','1','0','0','0',$module['tagenda']);
    $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',                '255','3' ,'1','1','1','0','0','1');
    $x->createField('cat','Catégorie','\Seolan\Field\Link\Link',                    '60','4' ,'1','1','1','0','0','0',$module['tcatevt']);
    $x->createField('descr','Description','\Seolan\Field\Text\Text',                '60','5' ,'0','1','0','0','0','0');
    $x->createField('datelim','Date limite de réponse','\Seolan\Field\Date\Date',    '0','6' ,'1','1','1','0','0','1');
    $x->procEditField(array('field'=>'datelim','table'=>$newtable,'_todo'=>'save'));  //,'options'=>array('default'=>'+7 days')));
    $x->createField('invitt','Texte d\\\'invitation','\Seolan\Field\RichText\RichText', '60','7' ,'1','1','0','0','0','0');
    $x->createField('begin','Date de début retenue','\Seolan\Field\DateTime\DateTime',   '0','8' ,'0','1','0','0','0','0');
    $x->createField('end','Date de fin retenue','\Seolan\Field\DateTime\DateTime',       '0','9' ,'0','1','0','0','0','0');
    $x->createField('rem','Remarque','\Seolan\Field\RichText\RichText',                 '60','10','0','1','0','0','0','0');
    $x->createField('cancel','Annulé','\Seolan\Field\Boolean\Boolean',                     '0','11','0','1','0','0','0','0');
    $x->createField('close','Close','\Seolan\Field\Boolean\Boolean',                       '0','12','0','1','0','0','0','0');
    $x->procEditField(array('field'=>'ag','table'=>$newtable,'_todo'=>'save','target'=>$module['tagenda'],
			    'options'=>array('readonly'=>1)));
    $x->procEditField(array('field'=>'begin','table'=>$newtable,'_todo'=>'save','options'=>array('display_format'=>'H:M')));
    $x->procEditField(array('field'=>'end','table'=>$newtable,'_todo'=>'save','options'=>array('display_format'=>'H:M')));
    $x->procEditField(array('field'=>'cat','table'=>$newtable,'_todo'=>'save','target'=>$module['tcatevt'],
			    'options'=>array('checkbox'=>0,'filter'=>'commun=1')));
    
    $newtable=$tplaninv=\Seolan\Model\DataSource\Table\Table::newTableNumber('AG');
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Invités aux planifications';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    //                                                               size ord  obl que bro tra mul pub tar
    $x->createField('planif','Planification','\Seolan\Field\Link\Link',              '0','2' ,'1','1','1','0','0','0',$tplan);
    $x->createField('who','Cible','\Seolan\Field\Link\Link',                         '0','3' ,'1','1','1','0','0','1',$tplaninv);
    $x->createField('part','Participe','\Seolan\Field\Boolean\Boolean',                    '0','4' ,'0','1','1','0','0','0');
    $x->createField('remark','Remarque','\Seolan\Field\Text\Text',                  '60','5' ,'0','1','1','0','0','0');

    $newtable=$tplandates=\Seolan\Model\DataSource\Table\Table::newTableNumber('AG');
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Dates des planifications';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    //                                                               size ord  obl que bro tra mul pub tar
    $x->createField('planif','Planification','\Seolan\Field\Link\Link',              '0','2' ,'1','1','1','0','0','0',$tplan);
    $x->createField('begin','Date de début','\Seolan\Field\DateTime\DateTime',           '0','3' ,'1','1','1','0','0','1');
    $x->createField('end','Date de fin','\Seolan\Field\DateTime\DateTime',               '0','4' ,'1','1','1','0','0','1');
    $x->createField('confirm','Personnes ayant confirmés','\Seolan\Field\Link\Link', '0','5' ,'0','1','1','0','1','0');
    $x->procEditField(array('field'=>'begin','table'=>$newtable,'_todo'=>'save','options'=>array('display_format'=>'H:M')));
    $x->procEditField(array('field'=>'end','table'=>$newtable,'_todo'=>'save','options'=>array('display_format'=>'H:M')));

    $this->_module->tplan=$tplan;
    $this->_module->tplaninv=$tplaninv;
    $this->_module->tplandates=$tplandates;
  }
}
?>
