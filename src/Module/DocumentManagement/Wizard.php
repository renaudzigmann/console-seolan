<?php
namespace Seolan\Module\DocumentManagement;
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_DocumentManagement_DocumentManagement');
  }

  static function newPrefix(){
    $tmax='DM1';
    while(\Seolan\Core\System::tableExists($tmax.'IDX')) $tmax++;
    return $tmax;
  }

  function istep1() {
    parent::istep1();
    $this->_module->prefix=$this->newPrefix();
    $this->_options->setOpt('DOC : '.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','createstructure'), "createstructuredoc", "boolean");
    $this->_options->setOpt('Préfixe','prefix','text');
    $this->_options->setOpt('Table par défaut des repertoires (vide pour créer une table)','defaultFolderTable','table',array('emptyok'=>true));
  }
  
  function iend($ar=NULL) {
    // Créé la table des repertoires par defaut
    $newTypes = [];
    if(empty($this->_module->defaultFolderTable)){
      $this->_module->defaultFolderTable=$newtable=\Seolan\Model\DataSource\Table\Table::newTableNumber('REP');
      $ar1=array('translatable'=>'0','auto_translate'=>'0','btab'=>$newtable,
		 'bname'=>array(TZR_DEFAULT_LANG=>"{$this->_module->modulename} - Répertoires"));
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($newtable);
      //                                                                              size ord  obl que bro tra mul pub tar
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',           '255','3' ,'1','1','1','0','0','1');
      $x->createField('remark','Description','\Seolan\Field\Text\Text',               '60','4' ,'0','0','0','0','0','0');
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
      $x->delField(array('field'=>'CREAD','action'=>'OK'));
      $newTypes[] = [$newtable, \Seolan\Core\Labels::gettextSysLabel('Seolan_Module_DocumentManagement_DocumentManagement','directory'), true];
    }
    
    // Créé un table pour les documents si demandé
    if($this->_module->createstructuredoc){
      $newtable=\Seolan\Model\DataSource\Table\Table::newTableNumber('DOC');
      $ar1=array('translatable'=>'0','auto_translate'=>'0','btab'=>$newtable,
		 'bname'=>array(TZR_DEFAULT_LANG=>"{$this->_module->modulename} - Documents"));
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($newtable);
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',           '255','3' ,'1','1','1','0','0','1');
      $this->_module->tabledoc=$newtable;
      $newTypes[] = [$newtable, 'Document', false];
    }

    // Créé la table des types si elle n'existe pas
    if(!\Seolan\Core\System::tableExists('_TYPES')){
      $ar1=array();
      $ar1['translatable']='0';
      $ar1['auto_translate']='0';
      $ar1['btab']='_TYPES';
      $ar1['bname'][TZR_DEFAULT_LANG]='Base documentaire - Types de document';
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_TYPES');
      $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
      $x->delField(array('field'=>'OWN','action'=>'OK'));
      //                                                          size ord  obl que bro tra mul pub tar
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',            '50','1' ,'1','1','1','0','0','1');
      $x->createField('node','Dossier','\Seolan\Field\Boolean\Boolean',                  '','2' ,'1','1','1','0','0','0');
      $x->createField('modid','Module','\Seolan\Field\Module\Module',                '','3' ,'0','1','1','0','0','0');
      $x->createField('modidd','Module des données','\Seolan\Field\Module\Module',   '','4' ,'0','1','1','0','0','0');
      $x->createField('dtab','Table des données','\Seolan\Field\ShortText\ShortText', '20','5' ,'0','1','1','0','0','0');
      $x->createField('icon','Icône','\Seolan\Field\Image\Image',                   '','6' ,'0','0','1','0','0','0');
      $x->createField('smallic','Petit icône','\Seolan\Field\Image\Image',          '','7' ,'0','0','0','0','0','0');
      $x->createField('docclas','Classe','\Seolan\Field\ShortText\ShortText',         '30','8' ,'0','1','1','0','0','0');
      $x->createField('symbol','Symbole','\Seolan\Field\ShortText\ShortText',         '20','9' ,'0','1','1','0','0','0');
      $x->createField('disp','Gabarit d\'affichage','\Seolan\Field\File\File',     '','10','0','0','0','0','0','0');
      $x->createField('edit','Gabarit d\'édition','\Seolan\Field\File\File',       '','11','0','0','0','0','0','0');
      $x->createField('crules','Schéma','\Seolan\Field\Text\Text',               '60','12','0','0','0','0','0','0');
      $x->createField('pattern','Modèle','\Seolan\Field\ShortText\ShortText',         '40','13','0','0','0','0','0','0');
      $x->createField('opts','Options','\Seolan\Field\Text\Text',             '60','14','0','0','0','0','0','0');

    }

    $this->createStructure($this->_module->prefix);

    $moid=parent::iend();

    // Création d'un module sur les types s'il n'y en a pas
    $mods=\Seolan\Core\Module\Module::modulesUsingTable('_TYPES',true,false,true);
    if(empty($mods)) {
      \Seolan\Core\Module\Module::$_mcache=NULL;
      $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
      $mod2->_module->modulename='Types de document';
      $mod2->_module->group=$this->_module->group;
      $mod2->_module->table='_TYPES';
      $mod2->_module->home=1;
      $mod2->iend();
    }
    
    // Création des dossiers obligatoires et des types via la fonction chk de \Seolan\Module\DocumentManagement\DocumentManagement
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod=\Seolan\Core\Module\Module::objectfactory(array('moid'=>$moid,'tplentry'=>TZR_RETURN_DATA));
    $mod->chk();

    // ajout des types créés pour ce module si il y en a
    \Seolan\Core\DataSource\DataSource::clearCache();
    $dsTypes = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_TYPES');
    foreach($newTypes as list($table, $title, $node)){
      $dsTypes->procInput(['_options'=>['local'=>1],
			   'title'=>$title,
			   'node'=>$node,
			   'dtab'=>$table,
			   'modid'=>$moid
      ]);
    }
    
  }
  static function createStructure($prefix) {
    if(!\Seolan\Core\System::tableExists($prefix.'ID',true)){
      getDB()->execute('CREATE TABLE '.$prefix.'ID (KOID varchar(40) NOT NULL default "",DTYPE varchar(40) default NULL,DPARAM text,'.
		  'ENDO TINYINT(1) DEFAULT NULL,UPD TIMESTAMP NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,PRIMARY KEY (KOID)'.
		  ') ENGINE=MyISAM DEFAULT CHARSET=utf8;');
    }
    if(!\Seolan\Core\System::tableExists($prefix.'IDX')){
      getDB()->execute('CREATE TABLE '.$prefix.'IDX(KOIDSRC varchar(40) default NULL,KOIDDST varchar(40) default NULL,KEY KOIDSRC (KOIDSRC)'.
		  ') ENGINE=MyISAM DEFAULT CHARSET=utf8');
    }
  }
	
}
?>
