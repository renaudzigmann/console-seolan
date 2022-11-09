<?php
namespace Seolan\Module\Social;
class Wizard extends \Seolan\Module\Table\Wizard{
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }
  function istep2(){
    if(!$this->_module->createstructure){
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','table'), 'table', 'table');
    }else{
      $this->_module->bname=$this->_module->modulename;
      $this->_module->btab=\Seolan\Model\DataSource\Table\Table::newTableNumber();
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_name'), 'bname', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_code'), 'btab', 'text');
    }
  }
  function iend($ar=NULL) {
    if($this->_module->createstructure){
      $this->_module->createstructure=false;
      $ar1=array();
      $ar1['translatable']=$this->_module->translatable;
      $ar1['auto_translate']=$this->_module->auto_translate;
      $ar1['btab']=$this->_module->btab;
      $ar1['bname'][TZR_DEFAULT_LANG]=$this->_module->bname;
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->_module->btab);
      //                                                                size ord  obl que bro tra mul pub tar
      $opt=array('fgroup'=>array(TZR_DEFAULT_LANG=>'<!--1-->General'));
      $x->createField('shortt','Titre court','\Seolan\Field\ShortText\ShortText',          '140','2' ,'0','1','1','0','0','1','',$opt);
      $x->createField('longt','Titre long','\Seolan\Field\Text\Text',                  '70','3' ,'0','1','1','0','0','0','',$opt);
      $x->createField('url','Lien','\Seolan\Field\Url\Url',                            '0','4' ,'0','1','0','0','0','0','',$opt);
      $x->createField('urldescr','Description du lien','\Seolan\Field\Text\Text',      '70','5' ,'0','1','0','0','0','0','',$opt);
      $x->createField('media','Image','\Seolan\Field\Image\Image',                       '0','6' ,'0','1','0','0','0','0','',$opt);
      $x->createField('publishon','Publier le','\Seolan\Field\Date\Date',               '0','7' ,'1','1','1','0','0','1','',$opt);
      $x->createField('fbaccount','Compte Facebook','\Seolan\Field\Link\Link',          '0','8' ,'0','1','0','0','0','0','_ACCOUNTS',
		      array('fgroup'=>array(TZR_DEFAULT_LANG=>'<!--2-->Facebook'),'filter'=>'atype="Facebook"','checkbox'=>0));
      $x->createField('fbok','Publié','\Seolan\Field\Boolean\Boolean',                        '0','9' ,'0','1','0','0','0','0','',
		      array('fgroup'=>array(TZR_DEFAULT_LANG=>'<!--2-->Facebook'),'readonly'=>true));
      $x->createField('fbstate','Rapport','\Seolan\Field\Text\Text',                   '70','10','0','1','0','0','0','0','',
		      array('fgroup'=>array(TZR_DEFAULT_LANG=>'<!--2-->Facebook'),'readonly'=>true));
      $x->createField('twitteraccount','Compte Twitter','\Seolan\Field\Link\Link',      '0','11','0','1','0','0','0','0','_ACCOUNTS',
		      array('fgroup'=>array(TZR_DEFAULT_LANG=>'<!--3-->Twitter'),'filter'=>'atype="Twitter"','checkbox'=>0));
      $x->createField('twitterok','Publié','\Seolan\Field\Boolean\Boolean',                   '0','12','0','1','0','0','0','0','',
		      array('fgroup'=>array(TZR_DEFAULT_LANG=>'<!--3-->Twitter'),'readonly'=>true));
      $x->createField('twitterstate','Rapport','\Seolan\Field\Text\Text',              '70','13','0','1','0','0','0','0','',
		      array('fgroup'=>array(TZR_DEFAULT_LANG=>'<!--3-->Twitter'),'readonly'=>true));
      $this->_module->table=$this->_module->btab;
    }
    return parent::iend();
  }
}
?>
