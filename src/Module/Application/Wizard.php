<?php
namespace Seolan\Module\Application;
class Wizard extends \Seolan\Core\Module\Wizard{
  function iend($ar=NULL) {
    $this->createStructure();
    return parent::iend($ar);
  }

  function createStructure(){
    // Creation de la table de des appplications
    if(!\Seolan\Core\DataSource\DataSource::sourceExists('APP')){
      $lg=TZR_DEFAULT_LANG;
      $table='APP';
      $ar1=array();
      $ar1["btnNewBase"]="OK";
      $ar1["translatable"]="0";
      $ar1["publish"]="0";
      $ar1["auto_translate"]="0";
      $ar1["btab"]=$table;
      $ar1["bname"][$lg]="System - Applications";
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
      //                                                                size ord  obl que bro tra mul pub tar
      $x->createField('name','Nom','\Seolan\Field\ShortText\ShortText',                    '255','2' ,'1','1','1','0','0','1');
      $x->createField('classname','Classe','\Seolan\Field\ShortText\ShortText',            '100','3' ,'1','1','1','0','0','0');
      $x->createField('domain','Domaine','\Seolan\Field\ShortText\ShortText',              '255','4' ,'0','1','1','0','0','0');
      $x->createField('domain_is_regex','Domaine est une expression','\Seolan\Field\Boolean\Boolean',            '100','3' ,'1','1','1','0','0','0');
      $x->createField('params','Paramètres','\Seolan\Field\Serialize\Serialize',            '70','5' ,'0','0','0','0','0','0');
    }
  }
}
