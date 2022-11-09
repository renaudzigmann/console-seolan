<?php

namespace Seolan\Module\ConfigMobApp;

use Seolan\Core\Labels;
use Seolan\Core\Module\Wizard as WizardModule;
use Seolan\Module\Table\Wizard as WizardTable;
use Seolan\Model\DataSource\Table\Table as ModelTable;
use Seolan\Core\DataSource\DataSource;

class Wizard extends WizardTable {
  public function __construct($ar=NULL) {
    parent::__construct($ar);
    Labels::loadLabels('Seolan_Module_ConfigMobApp_ConfigMobApp');
    $this->_module->group = 'Mobile';
  }
  
  public function istep1() {
    WizardModule::istep1();
    $this->_options->setOpt(
      Labels::getSysLabel('Seolan_Core_General', "createstructure", "text"),
      'createstructure', 'boolean');
  }
  
  public function istep2(){
    if(!$this->_module->createstructure){
    
      $opt['emptyok']=false;
    
      $this->_options->setOpt(
        Labels::getSysLabel('Seolan_Core_General','table'),
        'table', 'table', $opt);
    } else {
      $this->_module->bname = $this->_module->modulename;
      $this->_module->btab = ModelTable::newTableNumber('MOBAPP');
      $this->_module->trackchanges = true;
      $this->_options->setOpt(
        Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','table_name'),
        'bname', 'text');
      
      $this->_options->setOpt(
        Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','table_code'),
        'btab', 'text');
      
      $this->_options->setOpt(
        Labels::getTextSysLabel('Seolan_Core_Field_Field','trackchanges'),
        'trackchanges', 'boolean');
    }
  }
  
  public function iend($ar=NULL) {
    if($this->_module->createstructure){
      $this->_module->createstructure = false;
      $ar1=[];
      $ar1['translatable'] = false;
      $ar1['auto_translate'] = false;
      $ar1['trackchanges'] = $this->_module->trackchanges;
      $ar1['cread'] = false;
      $ar1['publish'] = false;
      $ar1['own'] = false;
      $ar1['tag'] = false;
      $ar1['btab'] = $this->_module->btab;
      $ar1['bname'][TZR_DEFAULT_LANG] = $this->_module->bname;
      
      ModelTable::procNewSource($ar1);
      $x = DataSource::objectFactoryHelper8($this->_module->btab);
      
      $options = [
        'url' => [
          'acomment' => [
            TZR_DEFAULT_LANG => 'URL de base avec le protocole se terminant par un "/". '.
                                'Ce champ sera concaténé avec le champ URI ci-dessous.',
          ],
        ],
        'uri' => [
          'acomment' => [
            TZR_DEFAULT_LANG => 'portion d\'URL après l\'URL de base. Ce champ sera concaténé '.
                                'avec le champ URL ci-dessous. Il ne doit pas commencer par un "/".',
          ],
        ]
      ];
      
      $x->createField(
        'url',
        'URL de base',
        '\Seolan\Field\ShortText\ShortText',
        255,
        3,
        true,
        true,
        true,
        false,
        false,
        true,
        '',
        $options['url']);
      
      $x->createField(
        'uri',
        'URI',
        '\Seolan\Field\ShortText\ShortText',
        255,
        4,
        false,
        true,
        true,
        false,
        false,
        false,
        '',
        $options['uri']);
      
      $x->createField(
        'primary_color',
        'Couleur principale',
        '\Seolan\Field\Color\Color',
        20,
        5,
        true,
        false,
        true,
        false);
      
      $x->createField(
        'secondary_color',
        'Couleur secondaire',
        '\Seolan\Field\Color\Color',
        20,
        6,
        true,
        false,
        true,
        false);
      
      $this->_module->table = $this->_module->btab;
    }
    return parent::iend();
  }
}