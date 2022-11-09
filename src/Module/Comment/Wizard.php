<?php
namespace Seolan\Module\Comment;

class Wizard extends \Seolan\Module\Table\Wizard {
  function __construct($ar=NULL) {
    if(!\Seolan\Core\System::tableExists('_COMMENTS')) $this->createStructure();
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('\Seolan\Module\Comment\Wizard');
    $this->_module->table='_COMMENTS';
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties');
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Comment_Comment','modulename');
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
    $this->_module->table='_COMMENTS';
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties');
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Comment_Comment','modulename');
    parent::iend();
  }

  function createStructure() {
    $wf='_COMMENTS';
    if(!\Seolan\Core\System::tableExists($wf)) {
      $ar1['translatable']='1';
      $ar1['auto_translate']='1';
      $ar1['btab']=$wf;
      $ar1['bname'][TZR_DEFAULT_LANG]='System - '.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Comment_Comment','modulename');
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$wf);
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',100,0,1,1,1,0,0,1);
      $x->createField('comment','Commentaire','\Seolan\Field\RichText\RichText',60,0,1,1,0,0,0,0);
      //                                             s,o,c,q,b,t,m,p,t
      $x->createField('object','Document','\Seolan\Field\Link\Link',0,0,1,0,1,0,0,0,'%');
      $x->createField('vote','Vote','\Seolan\Field\Boolean\Boolean',      0,0,1,0,0,0,0,0);
      $x->createField('closed','Ferme','\Seolan\Field\Boolean\Boolean',      0,0,1,0,0,0,0,0);
      $x->createField('note','Evaluation','\Seolan\Field\Rating\Rating',0,0,0,0,0,0,0,0);
      $x->createField('type','Type','\Seolan\Field\ShortText\ShortText', 10,0,1,1,1,0,0,0);
      $x->createField('wid','Lien vers un tache','\Seolan\Field\Link\Link',0,0,1,0,1,0,0,0,'WFWORKITEM');
    }
    
  }
}

?>
