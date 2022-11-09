<?php
namespace Seolan\Module\Blog;

/// Wizard de creation d'un module  de gestion de blog
class Wizard extends \Seolan\Module\Table\Wizard {

  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Blog_Blog');
  }

  function istep1() {
    \Seolan\Core\Module\Wizard::istep1();
    $this->createStructure();
  }
  function iend($ar=NULL) {
    parent::iend();
  }
  private function createStructure() {
    $newtable=\Seolan\Model\DataSource\Table\Table::newTableNumber();
    $cmdtable=$newtable;
    $lg = TZR_DEFAULT_LANG;
    $ar1=array();
    $ar1["translatable"]="1";
    $ar1["publish"]="1";
    $ar1["auto_translate"]="0";
    $ar1["btab"]=$newtable;
    $ar1["bname"][$lg]="Blog - Messages";
    \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$newtable);

    $x->createField('dtype', 'Type',                                              // ord obl que bro tra mul pub tar
		    '\Seolan\Field\ShortText\ShortText',                                              '20','3','1','1','1','0','0','1');
    $x->createField('title',                                           
		    \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','title'),               // ord obl que bro tra mul pub tar
		    '\Seolan\Field\ShortText\ShortText',                                             '100','4','1','1','1','0','0','1');
    $x->createField('categ',                                           
		    \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Blog_Blog','categ'),               // ord obl que bro tra mul pub tar
		    '\Seolan\Field\ShortText\ShortText',                                             '20','4','1','1','1','0','0','1');
    $x->createField('txt',                                                        // ord obl que bro tra mul pub tar
    \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Blog_Blog','paper'),'\Seolan\Field\RichText\RichText',     '60','5','1','1','1','0','0','0');
    $x->createField('afile',                                                      // ord obl que bro tra mul pub tar
		    \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Blog_Blog','file'),'\Seolan\Field\File\File',              '','6','1','1','1','0','0','0');
    $x->createField('paperup',                                                    // ord obl que bro tra mul pub tar
		    \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Blog_Blog','paperup'),'\Seolan\Field\Link\Link',            '60','7','0','1','1','0','0','0',$newtable);
    $x->createField('blog',                                                       // ord obl que bro tra mul pub tar
		    'Blog','\Seolan\Field\Link\Link',                                      	'60','8','0','1','1','0','0','0',$newtable);
    $x->createField('who',                                                       // ord obl que bro tra mul pub tar
		    'Who','\Seolan\Field\Url\Url',                                      '200','9','0','1','0','0','0','0');
    $x->createField('datep',                                                       // ord obl que bro tra mul pub tar
		    'Date','\Seolan\Field\DateTime\DateTime',                                    '20','10','0','0','0','0','0','0');
    $this->_module->table=$newtable;
  }
}

?>
