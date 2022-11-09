<?php
namespace Seolan\Model\DataSource\Table;
class Wizard extends \Seolan\Core\DataSource\Wizard{
  function __construct($ar=NULL){
    parent::__construct($ar);
  }

  function istep1($ar=NULL){
    if(empty($this->_datasource->btab)) $this->_datasource->btab=\Seolan\Model\DataSource\Table\Table::newTableNumber();
    if(empty($this->_datasource->classname)) $this->_datasource->classname=\Seolan\Core\DataSource\DataSource::$_sources['\Seolan\Model\DataSource\Table\Table']['CLASSNAME'];
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','table_name'),'bname','ttext');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','table_code'),'btab','text');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','translatable'),'translate','list',
			    array('values'=>array('1','0',TZR_LANG_FREELANG),
				  'labels'=>array(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','yes','text'),
						  \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','no','text'),
						  \Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','freelang','text'))));
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','automatic_translation'),'auto_translate','boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','published'),'publish','boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','theclass'),'classname','text');
  }

  function iend($ar=NULL){
    global $XSHELL;
    $param=array('btab'=>$this->_datasource->btab, 'bname'=>$this->_datasource->bname,
		 'publish'=>$this->_datasource->publish, 'classname'=>$this->_datasource->classname,
		 'translatable'=>$this->_datasource->translatable,'auto_translate'=>$this->_datasource->auto_translate,
		 'bparam'=>(array)$this->_datasource);
    $ret=\Seolan\Model\DataSource\Table\Table::procNewSource($param);
    if($ret['error']){
      $this->_step=1;
      $XSHELL->tpldata["wd"]['message']=$ret['message'];
      $this->irun($ar);
      return;
    }
    if(!empty($ret['message'])) setSessionVar('message',$ret['message']);
    $moid=\Seolan\Core\Module\Module::getMoid(XMODDATASOURCE_TOID);
    $XSHELL->setNext('&moid='.$moid.'&function=browse&tplentry=br&template=Module/DataSource.browse.html');
    clearSessionVar('DataSourceWd');
    return $ret['boid'];
  }
}
?>
