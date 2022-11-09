<?php
namespace Seolan\Module\Contact;
/// Wizard de creation d'un module gestion de contact
class Wizard extends \Seolan\Module\Table\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }

  function istep1() {
    $this->_module->sender='you@email.com';
    $this->_module->sendername='Your full name';
    $this->_module->createstructure=false;
    \Seolan\Core\Module\Wizard::istep1();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','sender'), 'sender', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','sendername'), 'sendername', 'text');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','createstructure'), 'createstructure', 'boolean');
  }
  private function createStructure() {
    $newtable=\Seolan\Model\DataSource\Table\Table::newTableNumber();
    $lg=TZR_DEFAULT_LANG;
    $ar1=array();
    $ar1['translatable']='0';
    $ar1['auto_translate']='0';
    $ar1['btab']=$newtable;
    $ar1['bname'][$lg]='Website - Demandes d\'infos '.$newtable;
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);
    //                                                          ord obl que bro tra mul pub tar
    $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',        '100','3','1','1','1','0','0','1');
    $x->createField('body','Question','\Seolan\Field\Text\Text',             '60','4','1','1','1','0','0','0');
    $x->createField('email','Email','\Seolan\Field\ShortText\ShortText',        '120','5','1','0','0','0','0','0');
    $x->createField('pok','Traîté ?','\Seolan\Field\Boolean\Boolean',              '','6','1','1','1','0','0','0');
    $x->createField('mailok','Inscription en mailing list ?','\Seolan\Field\Boolean\Boolean', 
		                                             '','7','1','1','0','0','0','0');
    $x->createField('arch','Archive','\Seolan\Field\Text\Text',              '60','8','0','0','0','0','0','0');
    $this->_module->processedfield='pok';
    $this->_module->archivefield='arch';
    $this->_module->emailfield='email';
    $this->_module->mailingokfield='mailok';
    $this->_module->table=$newtable;
  }
  function istep2() {
    if(!\Seolan\Core\System::tableExists('LETTERS')) \Seolan\Core\DataSource\DataSource::createLetters();
    if($this->_module->createstructure) {
      $this->createStructure();
      $this->_module->createstructure=false;
    } else {
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','table'), 'table', 'table');
    }
  }
  function istep3() {
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','emailfield'),'emailfield',
			    'field', array('table'=>$this->_module->table));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','processedfield'),'processedfield',
			    'field', array('table'=>$this->_module->table,'type'=>'\Seolan\Field\Boolean\Boolean'));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','archivefield'),'archivefield',
			    'field', array('table'=>$this->_module->table));
  }
  function iend($ar=NULL) {
    parent::iend();
  }
}
?>