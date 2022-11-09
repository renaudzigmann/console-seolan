<?php
namespace Seolan\Module\MailLogs;

/// Wizard de creation d'un module  de gestion de cache
class Wizard extends \Seolan\Module\Table\Wizard {

  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('\Seolan\Module\MailLogs\MailLogs');
    $this->_module->table='_MLOGS';
    $this->_module->group=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','systemproperties');
    $this->_module->modulename=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailLogs_MailLogs','modulename');
  }

  function istep1() {
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Table_Table','table'), 'table', 'table', 
			    array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','modulename'), 'modulename', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','group'), 'group', 'text');
    $this->_options->setRO('table');
    $this->_options->setRO('modulename');
    $this->_options->setRO('group');
    $this->createStructure();
  }

  function iend($ar=NULL) {
    $this->_module->group=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','systemproperties');
    $this->_module->modulename=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailLogs_MailLogs','modulename');
    parent::iend();
  }

  private function createStructure() {
    $ord=20;
    if(!\Seolan\Core\System::tableExists('_MLOGS')) {
      $lg = TZR_DEFAULT_LANG;
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']='_MLOGS';
      $ar1['bname'][$lg]='System - '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailLogs_MailLogs','modulename');
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $ord=3;
    }
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_MLOGS');
    if(!$x->fieldExists('modid')) {
      $x->createField('modid', 'Module', '\Seolan\Field\Module\Module',
		      //  ord   obl que bro tra mul pub tar 
		      '',$ord++,'0','1','1','0','0','0');
    }
    if(!$x->fieldExists('mtype')) {
      $x->createField('mtype', 'Type', '\Seolan\Field\ShortText\ShortText',
		      //  ord   obl que bro tra mul pub tar 
		      '100',$ord++,'0','1','1','0','0','0');
    }
    if(!$x->fieldExists('subject')) {
      $x->createField('subject', 'Sujet', '\Seolan\Field\ShortText\ShortText',
		      //  ord   obl que bro tra mul pub tar 
		      '255',$ord++,'0','1','1','0','0','0');
    }
    if(!$x->fieldExists('body')) {
      $x->createField('body', 'Message', '\Seolan\Field\Text\Text',
		      //  ord      obl que bro tra mul pub tar 
		      '60',$ord++,'0','1','0','0','0','0');
    }
    if(!$x->fieldExists('bodyfile')) {
      $ds->createField('bodyfile', 'HTML body', '\Seolan\Field\File\File', 
		       //  ord      obl que bro tra mul pub tar 
		       '0', $ord++, '0', '1', '1', '0', '0', '0');
    }
    if(!$x->fieldExists('size')) {
      $x->createField('size', 'Taille', '\Seolan\Field\Real\Real',
		      //  ord   obl que bro tra mul pub tar 
		      '5',$ord++,'0','1','0','0','0','0');
    }
    if(!$x->fieldExists('sender')) {
      $x->createField('sender', 'Emetteur', '\Seolan\Field\ShortText\ShortText',
		      //  ord   obl que bro tra mul pub tar 
		      '255',$ord++,'0','1','0','0','0','0');
    }
    if(!$x->fieldExists('datep')) {
      $x->createField('datep', 'Date programmée', '\Seolan\Field\DateTime\DateTime',
		      //  ord   obl que bro tra mul pub tar 
		      '',$ord++,'0','1','1','0','0','0');
    }
    if(!$x->fieldExists('datee')) {
      $x->createField('datee', 'Date d\'exécution', '\Seolan\Field\DateTime\DateTime',
		      //  ord   obl que bro tra mul pub tar 
		      '',$ord++,'0','1','1','0','0','0');
    }
    if(!$x->fieldExists('html')) {
      $x->createField('html', 'Format HTML', '\Seolan\Field\Boolean\Boolean',
		      //  ord   obl que bro tra mul pub tar 
		      '',$ord++,'0','1','0','0','0','0');
    }
    if(!$x->fieldExists('comment')) {
      $x->createField('comment', 'Commentaires', '\Seolan\Field\Text\Text',
		      //  ord   obl que bro tra mul pub tar 
		      '60',$ord++,'0','1','0','0','0','0');
    }
    if(!$x->fieldExists('nbdest')) {
      $x->createField('nbdest', 'Destinataires', '\Seolan\Field\Real\Real',
		      //  ord   obl que bro tra mul pub tar 
		      '5',$ord++,'0','1','1','0','0','0');
    }
    if(!$x->fieldExists('nberr')) {
      $x->createField('nberr', 'Erreurs', '\Seolan\Field\Real\Real',
		      //  ord   obl que bro tra mul pub tar 
		      '5',$ord++,'0','1','1','0','0','0');
    }
    if(!$x->fieldExists('nbouv')) {
      $x->createField('nbouv', 'Ouvertures', '\Seolan\Field\Real\Real',
		      //  ord   obl que bro tra mul pub tar 
		      '5',$ord++,'0','1','1','0','0','0');
    }
    if(!$x->fieldExists('req')) {
      $x->createField('req', 'Requête', '\Seolan\Field\Text\Text',
		      //  ord   obl que bro tra mul pub tar 
		      '60',$ord++,'0','1','0','0','0','0');
    }
    if(!$x->fieldExists('data')) {
      $x->createField('data', 'Données consolidées', '\Seolan\Field\Text\Text',
		      //  ord   obl que bro tra mul pub tar 
		      '60',$ord++,'0','1','0','0','0','0');
    }
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));


    $ord=20;
    if(!\Seolan\Core\System::tableExists('_MLOGSD')) {
      $lg = TZR_DEFAULT_LANG;
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']='_MLOGSD';
      $ar1['bname'][$lg]='System - '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailLogs_MailLogs','modulename'). ' - détails';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $ord=3;
    }
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_MLOGSD');
    if($x->fieldExists('PUBLISH')) {
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    }
    if(!$x->fieldExists('mlogh')) {
      $x->createField('mlogh', 'Module', '\Seolan\Field\Module\Module',
		      //  ord   obl que bro tra mul pub tar 
		      '',$ord++,'0','1','1','0','0','0');
    }
    if(!$x->fieldExists('CREAD')) {
      $x->createField('CREAD', 'Date création', '\Seolan\Field\DateTime\DateTime',
		      //  ord   obl que bro tra mul pub tar 
		      '',$ord++,'0','1','0','0','0','0');
    }
    if(!$x->fieldExists('sstatus')) {
      $x->createField('sstatus', 'Statut émission', '\Seolan\Field\ShortText\ShortText',
		      //  ord   obl que bro tra mul pub tar 
		      '60',$ord++,'0','1','0','0','0','0');
    }
    if(!$x->fieldExists('mails')) {
      $x->createField('mails', 'Emails', '\Seolan\Field\Text\Text',
		      //  ord   obl que bro tra mul pub tar 
		      '60',$ord++,'0','1','0','0','0','0');
    }
    if(!$x->fieldExists('subject')) {
      $x->createField('subject', 'Sujet', '\Seolan\Field\ShortText\ShortText',
		      //  ord     obl que bro tra mul pub tar 
		      '255',$ord++,'0','1','1','0','0','0');
    }
    if(!$x->fieldExists('body')) {
      $x->createField('body', 'Message', '\Seolan\Field\Text\Text',
		      //  ord     obl que bro tra mul pub tar 
		      '60',$ord++,'0','1','0','0','0','0');
    }
    if(!$x->fieldExists('bodyfile')) {
      $ds->createField('bodyfile', 'HTML body', '\Seolan\Field\File\File', 
		       //  ord      obl que bro tra mul pub tar 
		       '0', $ord++, '0', '1', '1', '0', '0', '0');
    }
    if(!$x->fieldExists('files')) {
      $x->createField('files', 'Fichiers', '\Seolan\Field\File\File',
		      //  ord     obl que bro tra mul pub tar 
		      '60',$ord++,'0','1','0','0','1','0');
    }
    if(!$x->fieldExists('errmess')) {
      $x->createField('errmess', 'Complément erreur', '\Seolan\Field\Text\Text',
		      //  ord     obl que bro tra mul pub tar 
		      '60',$ord++,'0','1','0','0','0','0');
    }
    if(!$x->fieldExists('reex')) {
      $x->createField('reex', 'Tentative de réexpédition', '\Seolan\Field\Real\Real',
		      //  ord     obl que bro tra mul pub tar 
		      '5',$ord++,'0','1','0','0','0','0');
    }

    if(!\Seolan\Core\System::tableExists('_MBOUNCE')) {
      $lg = TZR_DEFAULT_LANG;
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']='_MBOUNCE';
      $ar1['bname'][$lg]='System - '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailLogs_MailLogs','modulename'). ' - bounce';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_MBOUNCE');
      $ord=3;
      if(!$x->fieldExists('nbdetect')) {
	$x->createField('nbdetect', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailLogs_MailLogs','nbdetect'), '\Seolan\Field\Real\Real',
			'5',$ord++,'1','1','1','0','0','0');
      }
      if(!$x->fieldExists('email')) {
	$x->createField('email', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailLogs_MailLogs','email'),'\Seolan\Field\ShortText\ShortText',
			//              size  ord   obl que bro tra mul pub tar 
			'200',$ord++,'1','1','1','0','0','1');
      }
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
      $x->delField(array('field'=>'OWN','action'=>'OK'));
    }
  }
}
