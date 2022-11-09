<?php
namespace Seolan\Module\MultiTable;
class Wizard extends \Seolan\Module\Table\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }
  function iend($ar=NULL) {

    if($this->_module->createstructure){
      $this->_module->typeField='type';
      $createstructure = 1;
    }

    $r = parent::iend($ar);

    if($createstructure){
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->_module->btab);
      $x->createField('type','Type','\Seolan\Field\StringSet\StringSet','255','3','1','1','1','0','0','1');
    }

    return $r;
  }
}
