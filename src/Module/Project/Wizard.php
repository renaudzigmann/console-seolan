<?php
namespace Seolan\Module\Project;
class Wizard extends \Seolan\Module\Table\Wizard{
  function __construct($ar=NULL){
    parent::__construct($ar);
  }

  function istep3(){
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Project_Project','annumod'),'annumod','module');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Project_Project','calmod'),'calmod','module',array('toid'=>XMODCALENDARADM_TOID));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Project_Project','projectfield'),'projectfield','field',
			    array('table'=>'USERS','compulsory'=>false,'type'=>array('\Seolan\Field\Link\Link')));

  }

  function iend($ar=NULL) {
    if($this->_module->createstructure){
      if($this->_module->calmod) $modcal=\Seolan\Core\Module\Module::objectFactory($this->_module->calmod);
      $this->_module->createstructure=false;
      $ar1=array();
      $ar1['translatable']=$this->_module->translatable;
      $ar1['auto_translate']=$this->_module->auto_translate;
      $ar1['btab']=$this->_module->btab;
      $ar1['bname'][TZR_DEFAULT_LANG]=$this->_module->bname;
      $ar1['publish']=0;
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->_module->btab);
      //                                                              size ord  obl que bro tra mul pub tar
      $x->createField('title','Titre','\Seolan\Field\ShortText\ShortText',                '255','2' ,'1','1','1','0','0','1');
      $x->createField('prefix','Préfixe','\Seolan\Field\ShortText\ShortText',              '20','3' ,'1','1','0','0','0','0');
      $x->createField('descr','Description','\Seolan\Field\Text\Text',                '60','4' ,'0','1','0','0','0','0');
      $x->createField('logo','Logo','\Seolan\Field\Image\Image',                        '0','5' ,'0','1','1','0','0','0');
      $x->createField('mods','Modules modèles','\Seolan\Field\Module\Module',            '0','6' ,'1','1','0','0','1','0','',
                      ['filter'=>"(select count(MTXT) from AMSG where MOID=concat('module:',MODULES.MOID,':modulename') and MTXT like 'Mod%:%' and MLANG = 'FR')>0"]);
      $x->createField('cal','Agenda à dupliquer','\Seolan\Field\Link\Link',            '0','7' ,'0','1','0','0','0','0',$modcal->table,array('checkbox'=>0));
      $x->createField('grps','Groupes modèles','\Seolan\Field\Link\Link',              '0','8' ,'1','1','0','0','1','0','GRP',
                      ['checkbox'=>0,'doublebox'=>1,'filter'=>"(GRP like 'Mod%:%')"]);
      $x->createField('tgrps','Groupes transversaux','\Seolan\Field\Link\Link',         '0','9' ,'1','1','0','0','1','0','GRP',array('checkbox'=>0,'doublebox'=>1));
      $x->createField('amods','Modules rattachés','\Seolan\Field\Module\Module',         '0','10' ,'0','1','0','0','1','0',NULL,array('readonly'=>1));
      $x->createField('agrps','Groupes rattachés','\Seolan\Field\Link\Link',           '0','11','0','1','0','0','1','0','GRP',array('readonly'=>1));
      $x->createField('acal','Agenda rattaché','\Seolan\Field\Link\Link',              '0','12','0','1','0','0','0','0',$modcal->table,array('readonly'=>1,'checkbox'=>0));
      $this->_module->table=$this->_module->btab;
    }
    return parent::iend();
  }
}
?>
