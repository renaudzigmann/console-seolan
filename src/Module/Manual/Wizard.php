<?php
namespace Seolan\Module\Manual;

class Wizard extends \Seolan\Module\Table\Wizard {
  function __construct($ar=NULL) {
    if(!\Seolan\Core\System::tableExists('_MANUAL')) $this->createStructure();
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('\Seolan\Module\Manual\Wizard');
    $this->_module->table='_MANUAL';
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties');
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Manual_Manual','modulename');
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
    $this->_module->table='_MANUAL';
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties');
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Manual_Manual','modulename');
    parent::iend();
  }

  function createStructure() {
    $wf='_MANUAL';
    \Seolan\Library\Upgrades::addTable("_MANUAL", [TZR_DEFAULT_LANG=>"Documentation locale", "GB"=>"Local Doc"], []);
    $fields =
      [
       ["field"        => "modid",
	"label"        => "Module",
	"ftype"        => "\Seolan\Field\Module\Module",
	"fcount"       => 0,
	"forder"       => 1,
	"compulsory"   => 0,
	"queryable"    => 1,
	"browsable"    => 1,
	"translatable" => 0,
	"multi"        => 0,
	"published"    => 0,
	"target"       => "",
	"options"      => []],
       ["field"        => "func",
	"label"        => "Fonction",
	"ftype"        => "\Seolan\Field\ShortText\ShortText",
	"fcount"       => 40,
	"forder"       => 5,
	"compulsory"   => 1,
	"queryable"    => 1,
	"browsable"    => 1,
	"translatable" => 0,
	"multi"        => 0,
	"published"    => 0,
	"target"       => "",
	"options"      => []],
       ["field"        => "fld",
	"label"        => "Champ",
	"ftype"        => "\Seolan\Field\ShortText\ShortText",
	"fcount"       => 20,
	"forder"       => 6,
	"compulsory"   => 1,
	"queryable"    => 1,
	"browsable"    => 1,
	"translatable" => 0,
	"multi"        => 0,
	"published"    => 0,
	"target"       => "",
	"options"      => []],
       ["field"        => "title",
	"label"        => "Titre",
	"ftype"        => "\Seolan\Field\ShortText\ShortText",
	"fcount"       => 160,
	"forder"       => 7,
	"compulsory"   => 0,
	"queryable"    => 0,
	"browsable"    => 0,
	"translatable" => 0,
	"multi"        => 0,
	"published"    => 0,
	"target"       => "",
	"options"      => []],
       ["field"        => "detail",
	"label"        => "Détail",
	"ftype"        => "\Seolan\Field\RichText\RichText",
	"fcount"       => 80,
	"forder"       => 8,
	"compulsory"   => 0,
	"queryable"    => 0,
	"browsable"    => 0,
	"translatable" => 0,
	"multi"        => 0,
	"published"    => 0,
	"target"       => "",
	"options"      => []],
       
       ];
    \Seolan\Library\Upgrades::addFields("_MANUAL", $fields);
    $moid = \Seolan\Library\Upgrades::addModule("_MANUAL", \Seolan\Core\Labels::getSysLabel('Seolan_Module_Manual_Manual','modulename'),
						\Seolan\Core\Labels::getSysLabel('Seolan_Module_Manual_Manual','modulename'),
						'\Seolan\Module\Manual\Manual');
  }
}

?>
