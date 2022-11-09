<?php
namespace Seolan\Module\Media;
class Wizard extends \Seolan\Core\Module\Wizard{
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Media_Media');
  }

  function istep1() {
    parent::istep1();
    $this->_options->setOpt('Créer la table principale?', 'createstructure1', 'boolean');
    $this->_options->setOpt('Créer la table des collections?', 'createstructure3', 'boolean');
    $this->_options->setOpt('Créer la table des mots clé?', 'createstructure4', 'boolean');
    $this->_options->setOpt('Créer la table des procédures d\'import?', 'createstructure5', 'boolean');
  }

  function istep2() {
    if(!$this->_module->createstructure1) $this->_options->setOpt('Table principale des médias', 'table', 'table');
    if(!$this->_module->createstructure3) $this->_options->setOpt('Module des collections', 'collection', 'module', array('toid'=>XMODMEDIACOLLECTION_TOID));
    if(!$this->_module->createstructure4) $this->_options->setOpt('Table des mots clé', 'tkey', 'table');
    if(!$this->_module->createstructure5) $this->_options->setOpt('Table des procédures d\'import', 'imports', 'table');
  }

  function iend($ar=NULL) {
    if($this->_module->createstructure3 || (!$this->_module->createstructure3 && !$this->_module->collection)){
      $this->createStructure3();
    }else{
      $mod=\Seolan\Core\Module\Module::objectFactory($this->_module->collection);
      $this->_module->tcol=$mod->table;
    }
    if($this->_module->createstructure4) $this->createStructure4();
    if($this->_module->createstructure5) $this->createStructure5();
    if($this->_module->createstructure1) $this->createStructure1();

    $moid=parent::iend();
    if(!$this->_module->collection){
      $mod=new \Seolan\Module\Media\Collection\Wizard(array('newmoid'=>XMODMEDIACOLLECTION_TOID));
      $mod->_module->modulename='Collections';
      $mod->_module->group=$this->_module->group;
      $mod->_module->table=$this->_module->tcol;
      $mod->_module->order='title';
      $moidcol=$mod->iend();
      \Seolan\Core\Module\Module::$_mcache=NULL;
      $mod=\Seolan\Core\Module\Module::objectFactory($moid);
      $ar['options']['collection']=$moidcol;
      $mod->procEditProperties($ar);
    }
    if($this->_module->createstructure4){
      $mod=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
      $mod->_module->modulename='Mots clé';
      $mod->_module->group=$this->_module->group;
      $mod->_module->table=$this->_module->tkey;
      $mod->_module->order='title';
      $mod->iend();
    }
    if($this->_module->createstructure5){
      $mod=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
      $mod->_module->modulename='Procédures d\'import';
      $mod->_module->group=$this->_module->group;
      $mod->_module->table=$this->_module->imports;
      $mod->_module->order='title';
      $mod->iend();
    }
    return $moid;
  }

  // Création de la table principale
  private function createStructure1() {
    $module=(array)$this->_module;
    $modulename=$module[modulename];

    $newtable=\Seolan\Model\DataSource\Table\Table::newTableNumber();
    $ar1=array();
    $ar1['translatable']='1';
    $ar1['auto_translate']='1';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Table principale';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    //                                                                        size ord  obl que bro tra mul pub tar
    $x->createField('media','Media','\Seolan\Field\File\File',                                '0','2' ,'0','0','1','0','0','0');
    $x->procEditField(array('field'=>'media','table'=>$newtable,'_todo'=>'save','options'=>array('usemimehtml'=>1,'viewlink'=>false)));
    $x->createField('ref','Référence','\Seolan\Field\ShortText\ShortText',                        '60','4' ,'0','1','1','1','0','1');
    $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',                         '255','5' ,'0','1','1','1','0','1');
    $x->procEditField(array('field'=>'title','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.dc:title\nXMP.mediahd.dc:title\n".
					                    "IPTC.media.object_name\nIPTC.mediahd.object_name")));
    $x->createField('collection','Collection','\Seolan\Field\Thesaurus\Thesaurus',                 '0','6' ,'0','1','0','0','1','0',$this->_module->tcol);
    $x->procEditField(array('field'=>'collection','table'=>$newtable,'_todo'=>'save','target'=>$this->_module->tcol,
			    'options'=>array('fparent'=>'parent','flabel'=>'title',
					     'comment'=>array(TZR_DEFAULT_LANG=>'Double cliquez sur une valeur pour l\'ajouter'))));
    $x->createField('keywords','Mots clé','\Seolan\Field\Thesaurus\Thesaurus',                     '0','7' ,'0','1','0','0','1','0',$this->_module->tkey);
    $x->procEditField(array('field'=>'keywords','table'=>$newtable,'_todo'=>'save','target'=>$this->_module->tkey,
			    'options'=>array('fparent'=>'parent','flabel'=>'title','exif_source'=>"XMP.media.dc:subject\n".
					                                                          "IPTC.media.keywords",
					     'comment'=>array(TZR_DEFAULT_LANG=>'Double cliquez sur une valeur pour l\'ajouter'))));
    $x->createField('urgency','Urgence','\Seolan\Field\Real\Real',                            '2','8' ,'0','1','0','0','0','0');
    $x->procEditField(array('field'=>'urgency','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.photoshop:Urgency\n".
					                    "IPTC.media.priority")));
    $x->createField('category','Catégorie','\Seolan\Field\ShortText\ShortText',                    '3','9' ,'0','1','0','0','0','0');
    $x->procEditField(array('field'=>'category','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.photoshop:Category\n".
					                    "IPTC.media.category")));
    $x->createField('othercategories','Autres catégories','\Seolan\Field\ShortText\ShortText',   '255','10','0','1','0','0','0','0');
    $x->procEditField(array('field'=>'othercategories','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.photoshop:SupplementalCategory\n".
					                    "IPTC.media.supplementary_category")));
    $x->createField('instructions','Instructions','\Seolan\Field\Text\Text',                 '60','11','0','1','0','1','0','0');
    $x->procEditField(array('field'=>'instructions','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.photoshop:Instructions\n".
					                    "IPTC.media.special_instructions")));
    $x->createField('created','Date de création','\Seolan\Field\DateTime\DateTime',               '0','12','0','1','0','0','0','0');
    $x->procEditField(array('field'=>'created','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"EXIF.media.DateTimeOriginal\n".
					                    "XMP.media.photoshop:DateCreated\n".
					                    "IPTC.media.created_date")));
    $x->createField('author','Auteur','\Seolan\Field\ShortText\ShortText',                       '255','13','0','1','0','0','0','0');
    $x->procEditField(array('field'=>'author','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.dc:creator\n".
					                    "IPTC.media.byline")));
    $x->createField('authortitle','Titre de l\'auteur','\Seolan\Field\ShortText\ShortText',    '255','14','0','1','0','1','0','0');
    $x->procEditField(array('field'=>'authortitle','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.photoshop:AuthorsPosition\n".
					                    "IPTC.media.byline_title")));
    $x->createField('city','Ville','\Seolan\Field\ShortText\ShortText',                          '255','15','0','1','0','0','0','0');
    $x->procEditField(array('field'=>'city','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.photoshop:City\n".
					                    "IPTC.media.city")));
    $x->createField('state','Etat','\Seolan\Field\ShortText\ShortText',                          '255','16','0','1','0','0','0','0');
    $x->procEditField(array('field'=>'state','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.photoshop:State\n".
					                    "IPTC.media.province_state")));
    $x->createField('country','Pays','\Seolan\Field\ShortText\ShortText',                        '255','17','0','1','0','0','0','0');
    $x->procEditField(array('field'=>'country','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.photoshop:Country\n".
					                    "IPTC.media.country")));
    $x->createField('headline','Headline','\Seolan\Field\ShortText\ShortText',                   '255','18','0','1','0','1','0','0');
    $x->procEditField(array('field'=>'headline','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.photoshop:Headline\n".
					                    "IPTC.media.headline")));
    $x->createField('credit','Crédit','\Seolan\Field\Text\Text',                             '60','19','0','1','0','1','0','0');
    $x->procEditField(array('field'=>'credit','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.photoshop:Credit\n".
					                    "IPTC.media.credit")));
    $x->createField('source','Source','\Seolan\Field\ShortText\ShortText',                       '255','20','0','1','0','1','0','0');
    $x->procEditField(array('field'=>'source','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.photoshop:Source\n".
					                    "IPTC.media.source")));
    $x->createField('copyright','Copyright','\Seolan\Field\ShortText\ShortText',                 '255','21','0','1','0','1','0','0');
    $x->procEditField(array('field'=>'copyright','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.dc:rights\n".
					                    "IPTC.media.copyright_string")));
    $x->createField('caption','Description','\Seolan\Field\Text\Text',                       '60','22','0','1','0','1','0','0');
    $x->procEditField(array('field'=>'caption','table'=>$newtable,'_todo'=>'save',
			    'options'=>array('exif_source'=>"XMP.media.dc:description\n".
					                    "IPTC.media.caption")));
    $this->_module->table=$newtable;
  }

  // Création table collection
  private function createStructure3() {
    $module=(array)$this->_module;
    $modulename=$module[modulename];

    $newtable=\Seolan\Model\DataSource\Table\Table::newTableNumber();
    $ar1=array();
    $ar1['translatable']='1';
    $ar1['auto_translate']='1';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Collections';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    //                                                          size ord  obl que bro tra mul pub tar
    $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',           '255','2' ,'1','1','1','1','0','1');
    $x->createField('parent','Parent','\Seolan\Field\Link\Link',                '0','3' ,'0','1','1','0','0','0',$newtable);
    $this->_module->tcol=$newtable;
  }

  // Création table mots clé
  private function createStructure4() {
    $module=(array)$this->_module;
    $modulename=$module[modulename];

    $newtable=\Seolan\Model\DataSource\Table\Table::newTableNumber();
    $ar1=array();
    $ar1['translatable']='1';
    $ar1['auto_translate']='1';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Mots clé';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    $x->delField(array('field'=>'PUBLISH','action'=>'OK'));
    //                                                          size ord  obl que bro tra mul pub tar
    $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',           '255','2' ,'1','1','1','1','0','1');
    $x->createField('parent','Parent','\Seolan\Field\Link\Link',                '0','3' ,'0','1','1','0','0','0',$newtable);
    $this->_module->tkey=$newtable;
  }

  // Création table procedures d'import
  private function createStructure5() {
    $module=(array)$this->_module;
    $modulename=$module[modulename];

    $newtable=\Seolan\Model\DataSource\Table\Table::newTableNumber();
    $ar1=array();
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$newtable;
    $ar1['bname'][TZR_DEFAULT_LANG]=$modulename.' - Procédures d\'imports';
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    //                                                                                         size ord  obl que bro tra mul pub tar
    $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',                                          '255','3' ,'1','1','1','0','0','1');
    $x->createField('ftph','Serveur FTP','\Seolan\Field\ShortText\ShortText',                                     '255','4' ,'1','1','1','0','0','1');
    $x->createField('ftpu','User FTP','\Seolan\Field\ShortText\ShortText',                                        '255','5' ,'1','1','0','0','0','0');
    $x->createField('ftpp','Password FTP','\Seolan\Field\ShortText\ShortText',                                    '255','6' ,'1','1','0','0','0','0');
    $x->createField('mediadir','Répertoire des médias','\Seolan\Field\ShortText\ShortText',                       '255','7' ,'0','1','1','0','0','0');
    $x->createField('mediahddir','Répertoire des médias HD','\Seolan\Field\ShortText\ShortText',                  '255','8' ,'0','1','1','0','0','0');
    $x->createField('uniqname','Nom unique?','\Seolan\Field\Boolean\Boolean',                                        '0','9','0','1','1','0','0','0');
    $x->createField('onlyone','Exécuter une seule fois','\Seolan\Field\Boolean\Boolean',                             '0','10','0','1','1','0','0','0');
    $x->createField('dateb',"Date d'activation",'\Seolan\Field\Date\Date',                                     '0','11','0','1','1','0','0','0');
    $x->createField('datee',"Date de fin d'activation",'\Seolan\Field\Date\Date',                              '0','12','0','1','1','0','0','0');
    $x->createField('owner','Propriétaire des medias importés','\Seolan\Field\Link\Link',                      '0','13','0','1','1','0','0','0','USERS');
    $x->createField('publishauto','Publier automatiquement','\Seolan\Field\Boolean\Boolean',                         '0','14','0','1','1','0','0','0');
    $x->createField('iptctpl','Template IPTC','\Seolan\Field\File\File',                                       '0','15','0','0','0','0','0','0');
    $x->createField('email','Rapport à','\Seolan\Field\ShortText\ShortText',                                      '255','16','0','1','0','0','0','0');
    $x->createField('more','Complément','\Seolan\Field\Text\Text',                                            '60','17','0','1','0','0','0','0');
    $x->createField('mylog','Activité','\Seolan\Field\Text\Text',                                             '60','18','0','1','0','0','0','0');
    $this->_module->imports=$newtable;
  }
}
?>
