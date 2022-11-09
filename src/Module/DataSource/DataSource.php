<?php
namespace Seolan\Module\DataSource;
/// Module de gestion des sources de donnees
class DataSource extends \Seolan\Core\Module\Module {
  public static $singleton = true;

  function __construct($ar=NULL) {
    $ar['moid']=self::getMoid(XMODDATASOURCE_TOID);
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_DataSource_DataSource');
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['browse']=array('ro','rw','rwv','admin');
    $g['XDSContentDisplay']=array('ro','rw','rwv','admin');
    $g['XDSContentInput']=array('rw','rwv','admin');
    $g['XDSContentProcInput']=array('rw','rwv','admin');
    $g['XDSContentEdit']=array('rw','rwv','admin');
    $g['XDSContentProcEdit']=array('rw','rwv','admin');
    $g['XDSContentBrowse']=array('ro','rw','rwv','admin');
    $g['XDSContentPublish']=array('rwv','admin');
    $g['XDSContentDel']=array('rw','rwv','admin');
    $g['XDSCreateXModTable']=array('admin');
    $g['XDSDel']=array('rw','rwv','admin');
    $g['XDSClear']=array('rw','rwv','admin');
    $g['XDSDuplicate']=array('rw','rwv','admin');
    $g['XDSProcDuplicate']=array('rw','rwv','admin');
    $g['XDSChk']=array('rw','rwv','admin');
    $g['XDSEditSourceProperties']=array('rw','rwv','admin');
    $g['XDSProcEditSourceProperties']=array('rw','rwv','admin');
    $g['XDSBrowseFields']=array('rw','rwv','admin');
    $g['XDSNewField']=array('rw','rwv','admin');
    $g['XDSProcNewField']=array('rw','rwv','admin');
    $g['XDSEditField']=array('rw','rwv','admin');
    $g['XDSProcEditField']=array('rw','rwv','admin');
    $g['XDSProcEditFields']=array('rw','rwv','admin');
    $g['XDSDelField']=array('rw','rwv','admin');
    $g['XDSBrowseStrings']=array('rw','rwv','admin');
    $g['XDSNewString']=array('rw','rwv','admin');
    $g['XDSProcNewString']=array('rw','rwv','admin');
    $g['XDSEditString']=array('rw','rwv','admin');
    $g['XDSProcEditString']=array('rw','rwv','admin');
    $g['XDSDelString']=array('rw','rwv','admin');
    $g['XDSClearStrings']=array('rw','rwv','admin');
    $g['XDSSortStrings']=array('rw','rwv','admin');
    $g['XDSResetChrono']=array('rw','rwv','admin');
    $g['preImportSources']=array('rw','rwv','admin');
    $g['preImportSourcesAndFields']=array('rw','rwv','admin');
    $g['preImportFields']=array('rw','rwv','admin');
    $g['importSources']=array('rw','rwv','admin');
    $g['importSourcesAndFields']=array('rw','rwv','admin');
    $g['importFields']=array('rw','rwv','admin');
    $g['exportSourcesAndFields']=array('rw','rwv','admin');
    $g['exportFields']=array('rw','rwv','admin');
    $g['tablesToUml']=array('admin');
    $g['csvToTable']=array('rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// initialisation des proprietes
  public function initOptions() {
    parent::initOptions();
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=browse&tplentry=br&template=Module/DataSource.browse.html';
  }

  /// Listes des actions générales du module
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my,false);
    $myclass='Seolan_Module_DataSource_DataSource';
    $moid=$this->_moid;
    $boid=@$_REQUEST['boid'];
    $field=@$_REQUEST['field'];
    $func=@$_REQUEST['function'];
    $dir='Module/DataSource.';
    $o1=new \Seolan\Core\Module\Action($this,'browse',\Seolan\Core\Labels::getSysLabel($myclass,'browse','text'),
			  '&moid='.$moid.'&_function=browse&tplentry=br&template='.$dir.'/browse.html','edit');
    $o1->setToolbar('Seolan_Core_General','browse');
    $my['browse']=$o1;
    if($alfunction) $this->_actionlistonfunction($my);
    if($this->interactive) {
      $o1=new \Seolan\Core\Module\Action($this,'',$this->getLabel(),"&moid={$moid}&_function=browse&tplentry=br&template={$dir}browse.html",'edit');
      $my['stack'][]=$o1;
      if(!empty($boid)){
        $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
         if ($xds->fieldExists($field)){
           $o1=new \Seolan\Core\Module\Action($this,'XDSBrowseFields',$xds->getSourceName().' ('.$xds->getTable().')',
			      '&moid='.$moid.'&boid='.$boid.'&_function=XDSBrowseFields&template='.$dir.'/browseFields.html','edit');
	$my['stack'][]=$o1;
	if(!empty($field)){
	  $ffield=$xds->getField($field);
	  $o1=new \Seolan\Core\Module\Action($this,'XDSEditField',$ffield->get_label().' ('.$field.')',
				'&moid='.$moid.'&boid='.$boid.'&_function=XDSEditField&template='.$dir.'editField.html&field='.$field,'edit');
	  $my['stack'][]=$o1;
	}
        }
      }
      
    }
    // Imports
    $o1=new \Seolan\Core\Module\Action($this,'import',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','import','text'),'#','more');
    $o1->newgroup='import';
    $o1->menuable=true;
    $my['import']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'importSources',\Seolan\Core\Labels::getSysLabel($myclass,'preimportsources','text'),
			  '&moid='.$moid.'&_function=preImportSources&tplentry=br&template='.$dir.'preImportSources.html','import');
    $o1->menuable=true;
    $my['importSources']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'importFields',\Seolan\Core\Labels::getSysLabel($myclass,'preimportfields','text'),
			  '&moid='.$moid.'&_function=preImportFields&tplentry=br&template='.$dir.'preImportFields.html','import');
    $o1->menuable=true;
    $my['importFields']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'importSourcesAndFields',\Seolan\Core\Labels::getSysLabel($myclass,'preimportsourcesandfields','text'),
			  '&moid='.$moid.'&_function=preImportSourcesAndFields&template=Module/DataSource.preImportSourcesAndFields.html','import');
    $o1->menuable=true;
    $my['importSourcesAndFields']=$o1;

    $o1=new \Seolan\Core\Module\Action($this, 'csvToTable', \Seolan\Core\Labels::getSysLabel($myclass, 'csvtotable', 'text'),
			  '&moid='.$moid.'&_function=csvToTable', 'import');
    $o1->menuable=true;
    $my['csvToTable']=$o1;
  }

  /// Listes des actions sur le browse
  protected function al_browse(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $moid=$this->_moid;
    $boid=@$_REQUEST['boid'];
    $field=@$_REQUEST['field'];
    $dir='Module/DataSource.';
    $o1=new \Seolan\Core\Module\Action($this,'del',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete','text'),'javascript:'.$uniqid.'.deletebase(\'\',true);','edit');
    $o1->setToolbar('Seolan_Core_General','delete');
    $my['del']=$o1;
    // Export
    $o1=new \Seolan\Core\Module\Action($this,'export',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','export','text'),'#','more');
    $o1->newgroup='export';
    $o1->menuable=true;
    $my['export']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'exportSourcesAndFields',\Seolan\Core\Labels::getSysLabel('Seolan_Module_DataSource_DataSource','exportsourcesandfields','text'),
			  'javascript:'.$uniqid.'.exportbases();','export');
    $o1->menuable=true;
    $my['exportSourcesAndFields']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'exportSourcesAndFieldsWithValues',
                                       \Seolan\Core\Labels::getSysLabel('Seolan_Module_DataSource_DataSource','exportsourcesandfieldswithvalues','text'),
                                       'javascript:'.$uniqid.'.exportbases(true);','export');
    $o1->menuable=true;
    $my['exportSourcesAndFieldsWithValues']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'tablesToUml',\Seolan\Core\Labels::getSysLabel('Seolan_Module_DataSource_DataSource','tablestouml','text'),
                                       'javascript:'.$uniqid.'.tablesToUML();','display');
    $o1->menuable=true;
    $my['tablesToUml']=$o1;
  }

  /// Listes des actions sur le browseFields
  function al_XDSBrowseFields(&$my){
    $boid=$_REQUEST['boid'];
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $o1=new \Seolan\Core\Module\Action($this,'newfield',\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','new_field','text'),
			  '&moid='.$this->_moid.'&boid='.$boid.'&function=XDSNewField&template=Module/DataSource.newField.html','more');
    $o1->menuable=true;
    $my['newfield']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'emptydata',\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','empty_data','text'),
			  'javascript:'.$uniqid.'.emptybase();','more');
    $o1->menuable=true;
    $my['emptydata']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'deletebase',\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','delete_table','more'),
			  'javascript:'.$uniqid.'.deletebase();','more');
    $o1->menuable=true;
    $my['deletebase']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'clonebase',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','clone','text'),
			  '&moid='.$this->_moid.'&boid='.$boid.'&function=XDSDuplicate&template=Module/DataSource.duplicate.html&tplentry=br','more');
    $o1->menuable=true;
    $my['clonebase']=$o1;

    $o1=new \Seolan\Core\Module\Action($this,'checkrbase',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','check_and_repair','text'),
			  '&moid='.$this->_moid.'&boid='.$boid.'&function=XDSChk&skip=1&repair=1&_next='.rawurlencode(\Seolan\Core\Shell::get_back_url(0)),'more');
    $o1->menuable=true;
    $my['checkrbase']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'propbase',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','properties','text'),
			  '&moid='.$this->_moid.'&boid='.$boid.'&function=XDSEditSourceProperties&template=Module/DataSource.editSource.html','more');
    $o1->menuable=true;
    $my['propbase']=$o1;

    // Exports
    $o1=new \Seolan\Core\Module\Action($this,'export',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','export','text'),'#','more');
    $o1->newgroup='export';
    $o1->menuable=true;
    $my['export']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'exportSourcesAndFields',\Seolan\Core\Labels::getSysLabel('Seolan_Module_DataSource_DataSource','exportsourcesandfields','text'),
			  '&moid='.$this->_moid.'&boid='.$boid.'&_selected['.$boid.']=1&function=exportSourcesAndFields','export');
    $o1->menuable=true;
    $o1->target='_self';
    $my['exportSourcesAndFields']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'exportfields',\Seolan\Core\Labels::getSysLabel('Seolan_Module_DataSource_DataSource','exportfields','text'),'#','export');
    $o1->newgroup='exportfields';
    $o1->menuable=true;
    $my['exportfields']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'exportfieldscsv','CSV','&moid='.$this->_moid.'&boid='.$boid.'&function=exportFields','exportfields');
    $o1->menuable=true;
    $o1->target='_self';
    $my['exportfieldscsv']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'exportfieldsxl07','Excel 2007','&moid='.$this->_moid.'&boid='.$boid.'&function=exportFields&format=xl07','exportfields');
    $o1->menuable=true;
    $o1->target='_self';
    $my['exportfieldsxl07']=$o1;
  }
  /// Fonctions sur gestion des champs
  function al_XDSBrowseStrings(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $boid=$_REQUEST['boid'];
    $o1=new \Seolan\Core\Module\Action($this,'alphasort',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','alpha_sort','text'),
			  '&moid='.$this->_moid.'&boid='.$boid.'&function=XDSSorStrings&skip=1&field='.$_REQUEST['field'].'&_next='.rawurlencode(\Seolan\Core\Shell::get_back_url(0)));
    $o1->menuable=true;
    $my['alphasort']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'deletebase',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete_all','text'),
			  'javascript:'.$uniqid.'.deleteall()');
    $o1->menuable=true;
    $my['deleteall']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'newstring',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','new','text'),
			  '&moid='.$this->_moid.'&boid='.$boid.'&function=XDSNewString&template=Module/DataSource.newString.html&field='.$_REQUEST['field']);
    $o1->menuable=true;
    $my['newstring']=$o1;
  }

  /// Fonctions sur gestion des champs
  function al_XDSEditField(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $br=\Seolan\Core\Shell::from_screen('');
    $o1=new \Seolan\Core\Module\Action($this,'resetchrono','Reset',
			  '&moid='.$this->_moid.'&_function=XDSResetChrono&field='.$br['field'].'&boid='.$br['boid'].'&skip=1&_next='.
			  rawurlencode(\Seolan\Core\Shell::get_back_url(0)),'more');
    $o1->menuable=true;
    $my['resetchrono']=$o1;
  }

  /// Suppression du module
  function delete($ar=NULL) {
    parent::delete($ar);
  }
  
  /// affichage de la liste des sources de donnees
  function &browse($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array('tplentry'=>'br','count'=>'0'));
    $tplentry=$p->get('tplentry');
    $refresh=$p->get('refresh');
    $list=\Seolan\Core\DataSource\DataSource::getBaseList8(true,$refresh);
    $tablecode=array();
    $rightsneeded=array();
    $tablelabel=array();
    $tabletrans=array();
    $tableautotrans=array();
    $tablecount=array();
    foreach($list as $boid=>$txt){
      $sourcecode[]=$boid;
      $sourcelabel[]=$txt;
      $class=\Seolan\Core\DataSource\DataSource::getBoidProp($boid,'BCLASS');
      $type=\Seolan\Core\DataSource\DataSource::$_sources[$class]['SOURCE'];
      $sourcetype[]=(!empty($type)?$type:$class);
      $sourceinfo[]=\Seolan\Core\DataSource\DataSource::getBoidProp($boid,'BTAB');
    }
    $result=array();
    $nullmoid=NULL;
    $result['navigtitle']='Base list';
    $result['lines_sourcecode']=$sourcecode;
    $result['lines_sourcelabel']=$sourcelabel;
    $result['lines_sourcetype']=$sourcetype;
    $result['lines_sourceinfo']=$sourceinfo;

    foreach($list as $boid=>$txt){
      $result['lines_source'][]=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    }
    $result['sources']=&\Seolan\Core\DataSource\DataSource::$_sources;
    return \Seolan\Core\Shell::toScreen1($tplentry, $result);
  }

  function XDSContentDel($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    return $xds->del($ar);
  }
  function XDSContentDisplay($ar=NULL){
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Table_Table');
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    return $xds->display($ar);
  }
  function XDSContentPublish($ar=NULL){
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Table_Table');
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    return $xds->publish($ar);
  }
  function XDSContentInput($ar=NULL){
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Table_Table');
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    return $xds->input($ar);
  }
  function XDSContentProcInput($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    return $xds->procInput($ar);
  }
  function XDSContentEdit($ar=NULL){
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Table_Table');
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    return $xds->edit($ar);
  }
  function XDSContentProcEdit($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    return $xds->procEdit($ar);
  }
  function XDSContentBrowse($ar=NULL){
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Table_Table');
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    return $xds->browse($ar);
  }
  function al_XDSContentBrowse(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $moid=$this->_moid;
    $myoid=@$_REQUEST['oid'];
    $boid=$_REQUEST['boid'];
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);

    $o1=new \Seolan\Core\Module\Action($this,'del',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete','text'),
			  'javascript:'.$uniqid.'.deleteselected();','edit');
    $o1->setToolbar('Seolan_Core_General','delete');
    $my['del']=$o1;

    if(isset($xds->desc['PUBLISH'])){
      $sec=$this->secure($myoid,'XDSContentPublish');
      if($sec){
	$o1=new \Seolan\Core\Module\Action($this,'approve',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','approve','text'),
			      'javascript:'.$uniqid.".applyfunctiontoselection('XDSContentPublish','',{value:1});",'edit');
	$o1->menuable=true;
	$my['approve']=$o1;
	$o1=new \Seolan\Core\Module\Action($this,'unapprove',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','unapprove','text'),
			      'javascript:'.$uniqid.".applyfunctiontoselection('XDSContentPublish','',{value:2});",'edit');
	$o1->menuable=true;
	$my['unapprove']=$o1;
      }
    }

    // Affichage (changement taille page, ajout de champ...)
    $o1=new \Seolan\Core\Module\Action($this,'fieldgrp',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','field_label','text'),'#','display');
    $o1->menuable=true;
    $o1->newgroup='fieldgrp';
    $my['fieldgrp']=$o1;
    foreach($xds->orddesc as $i=>$fn){
      $f=$xds->getField($fn);
      $o1=new \Seolan\Core\Module\Action($this,'field'.$fn,$f->label,
			  'javascript:'.$uniqid.'.add_field(\''.$fn.'\');'.$uniqid.'.go_browse(\'\',0);','fieldgrp');
      $o1->menuable=true;
      $my['field'.$fn]=$o1;
    }
    $o1=new \Seolan\Core\Module\Action($this,'pgmore',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','page_size','text').' * 2',
			  'javascript:'.$uniqid.'.go_browse("start","*2");','display');
    $o1->menuable=true;
    $my['pgmore']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'pgless',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','page_size','text').' / 2',
			  'javascript:'.$uniqid.'.go_browse("start","/2");','display');
    $o1->menuable=true;
    $my['pgless']=$o1;
  }

  /// Supprime une source
  function XDSDel($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $selectedok=$p->get('_selectedok');
    $selected=$p->get('_selected');
    $boid=$p->get('boid');
    $message='';
    if(!$selectedok) $selected=array($boid=>1);
    foreach($selected as $boid=>&$foo){
      if(empty($boid)) continue;
      $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
      $ret=$xds->procDeleteDataSource(array('tplentry'=>TZR_RETURN_DATA));
      $message.=$ret['message'];
    }
    if(\Seolan\Core\Shell::hasNext()) setSessionVar('message',$message);
    $result['message']=$message;
    $result['back']=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=browse&template=Module/DataSource.browse.html&tplentry=br';
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }

  /// Vide une source
  function XDSClear($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $selectedok=$p->get('_selectedok');
    $selected=$p->get('_selected');
    $boid=$p->get('boid');
    $message='';
    if(!$selectedok) $selected=array($boid=>1);
    foreach($selected as $boid=>&$foo){
      if(empty($boid)) continue;
      $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
      $ret=$xds->clear(array('tplentry'=>TZR_RETURN_DATA));
      $message.=$ret['message'];
    }
    if(\Seolan\Core\Shell::hasNext()) setSessionVar('message',$message);
    $result['message']=$message;
    $result['back']=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&function=browse&template=Module/DataSource.browse.html&tplentry=br';
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }

  /// Prepare la duplication d'une source
  function XDSDuplicate($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid); 
    $ret=$xds->duplicateDataSource($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Duplique une source
  function XDSProcDuplicate($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $ret=$xds->procDuplicateDataSource($ar);
    if(\Seolan\Core\Shell::hasNext()) setSessionVar('message',$ret['message']);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Check/repare une source de donnée
  function XDSChk($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $ret=$xds->chk($ar);
    if(\Seolan\Core\Shell::hasNext()) setSessionVar('message',$ret['message']);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// prepare l'edition des propriété de la source
  function XDSEditSourceProperties($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $ret=$xds->editProperties($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Enregistre les modifications sur les propriétés de la source
  function XDSProcEditSourceProperties($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $ret=$xds->procEditProperties($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Parcours les champs d'une source
  function XDSBrowseFields($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $ret=$xds->browseFields($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Préparation de la création d'un nouveau champ
  function XDSNewField($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $ret=$xds->newField($ar);
    $ret['boid']=$boid;
    $ret['_xds'] = true;
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Enregistrement d'un nouveau champ
  function XDSProcNewField($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $ret=$xds->procNewField($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Préparation de la modification d'un champ
  function XDSEditField($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $ret=$xds->editField($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Enregistrement des modifications d'un champ
  function XDSProcEditField($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $ret=$xds->procEditField($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Enregistrement des modifications d'un champ
  function XDSProcEditFields($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $ret=$xds->procEditFields($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Suppression d'un champ
  function XDSDelField($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $ret=$xds->delField($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Parcours les valeurs d'un \Seolan\Field\StringSet\StringSet
  function XDSBrowseStrings($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $boid=$p->get('boid');
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$boid,'_options'=>array('local'=>true)));
    $tset->browse($ar);
  }
  
  /// Prepare l'ajout d'une valeur à un \Seolan\Field\StringSet\StringSet
  function XDSNewString($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $boid=$p->get('boid');
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$boid,'_options'=>array('local'=>true)));
    return $tset->newString($ar);
  }
  
  /// Ajout d'une valeur à un \Seolan\Field\StringSet\StringSet
  function XDSProcNewString($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $boid=$p->get('boid');
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$boid,'_options'=>array('local'=>true)));
    $ret=$tset->procNewString($ar);
    if($ret['error']) setSessionVar('message',$ret['message']);
    return $ret;
  }

  /// Prepare l'edition d'une valeur d'un \Seolan\Field\StringSet\StringSet
  function XDSEditString($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $boid=$p->get('boid');
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$boid,'_options'=>array('local'=>true)));
    return $tset->editString($ar);
  }
  
  /// Enregistre les modification d'une valeur d'un \Seolan\Field\StringSet\StringSet
  function XDSProcEditString($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $boid=$p->get('boid');
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$boid,'_options'=>array('local'=>true)));
    $ret=$tset->procEditString($ar);
    if($ret['error']) setSessionVar('message',$ret['message']);
    return $ret;
  }

  /// Supprime une valeur d'un \Seolan\Field\StringSet\StringSet
  function XDSDelString($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $boid=$p->get('boid');
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$boid,'_options'=>array('local'=>true)));
    return $tset->delString($ar);
  }

  /// Supprime toutes les valeurs d'un \Seolan\Field\StringSet\StringSet
  function XDSClearStrings($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $boid=$p->get('boid');
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$boid,'_options'=>array('local'=>true)));
    return $tset->clearStrings($ar);
  }

  /// Reoordonne les valeurs d'un \Seolan\Field\StringSet\StringSet par ordre alphabétique
  function XDSSortStrings($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $boid=$p->get('boid');
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$boid,'_options'=>array('local'=>true)));
    return $tset->sortStrings($ar);
  }

  /// Reinitialise un chrono
  public function XDSResetChrono($ar=NULL){
    $p=new \Seolan\Core\Param($ar, array());
    $boid=$p->get('boid');
    $field=$p->get('field');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $table=$xds->getTable();
    \Seolan\Core\DbIni::clear('Chrono::'.$table.'::'.$field);
  }

  /// Fonctions sur gestion des champs
  function al_adminEditField(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $br=\Seolan\Core\Shell::from_screen('');
    $o1=new \Seolan\Core\Module\Action($this,'resetchrono','Reset',
			  '&moid='.$this->_moid.'&_function=adminResetChrono&field='.$br['field'].'&boid='.$br['boid'].'&skip=1&_next='.
			  rawurlencode(\Seolan\Core\Shell::get_back_url(0)),'more');
    $o1->menuable=true;
    $my['resetchrono']=$o1;
  }

  function status($ar=NULL) {
    // inserez votre code personnalise ici
    $msg="Info Personnalisee";
    $msg1=\Seolan\Core\Shell::from_screen('imod','status');
    if(empty($msg)) $msg1=array();
    if(!empty($msg)) $msg1[]=$msg;
    \Seolan\Core\Shell::toScreen2('imod','status',$msg1);
  }

  /// Prepare l'import de sources
  function preImportSources($ar=NULL){
  }
  /// Importe des sources
  /// Entete sur premiere ligne : btab;bname[FR];translatable;auto_translate;own;publish
  ///                             str ;str      ;0/1         ;0/1           ;0/1   ;0/1
  function importSources($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('file'=>$_FILES['file']['tmp_name'],'endofline'=>"\r\n",'separator'=>';','quote'=>"\""));
    $file=$p->get('file');
    $data=$p->get('data');
    $prefixSQL=$p->get('prefixSQL');
    $prefix=$p->get('prefix');
    $oo=array('translatable','auto_translate'); // Champ booleen de base
    if(empty($data) || !is_array($data)){
      $xmime=\Seolan\Library\MimeTypes::getInstance();
      $mime=$xmime->getValidMime($upload_type,$file,(!empty($_FILES['file']['name'])?$_FILES['file']['name']:$file));
      if($mime=='application/vnd.ms-excel' || $mime=='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' || $mime=='application/zip') {
        $data=_getXLSData($file);
      } else {
	$spec->general->endofline=$p->get('endofline');
	$spec->general->separator=$p->get('separator');
	$spec->general->quote=$p->get('quote');
	$rawdata=@file_get_contents($file);
	$data=_getCSVData($rawdata,$spec);
      }
    }
    $head=$data[0];
    $message='';
    $l=count($data);
    for($i=1;$i<$l;$i++){
      // Construit les données de la table a créer
      $row=array();
      foreach($head as $j=>$h){
        $pos=strpos($h,'[');
        if($pos) $tmp='['.substr($h,0,$pos).']'.substr($h,$pos);
        else $tmp='['.$h.']';
        $tmp=str_replace(array('[',']'),array("['","']"),$tmp);
	utf8_cp1252_replace($data[$i][$j]);
        eval('$row'.$tmp.'="'.str_replace('"','\\"',trim($data[$i][$j])).'";');
      }
      // Verifie si le nom de la table est valide
      if(empty($row['btab']) || !preg_match("/[a-zA-Z0-9_-]+/",$row['btab'])){
	$message.='- La table "'.$row['btab'].'" n\'a pas un nom valide.<br>';
	continue;
      }
      // Ajoute les prefixe si necessaire
      if(!empty($prefixSQL)) $row['btab']=$prefixSQL.$row['btab'];
      if(!empty($prefix)){
	foreach($row['bname'] as &$name){
	  $name=$prefix.$name;
	}
      }
      // Verifie si la table existe deja
      if(\Seolan\Core\System::tableExists($row['btab'])){
	$message.='- La table "'.$row['btab'].'" existe déjà.<br>';
	continue;
      }

      // Création de la table
      foreach($oo as $o) if(empty($row[$o])) $row[$o]=0;
      if(empty($row['bname'][TZR_DEFAULT_LANG])){
	$t1=reset($row['bname']);
	$row['bname'][TZR_DEFAULT_LANG]=$t1;
      }
      \Seolan\Model\DataSource\Table\Table::procNewSource($row);
      $message.='- La table '.$row['btab'].' a été crée.<br>';
    }
    if(empty($message)) $message='- Aucune table n\'a été importée.';
    return \Seolan\Core\Shell::toScreen2('','message',$message);
  }

  /// Prepare l'import de champs de sources
  function preImportFields($ar=NULL){
  }
  /// Importe les champs de source (format de exportFields)
  /// delallfields => supprime tous les champs d'une table avant de créer ses champs
  /// delotherfields => supprime tous les champs encore présent et qui n'ont pas été importé
  /// \Seolan\Field\Link\Link, option target : tzrprefix_ pour ajouter le prefix au nom de la table cible
  function importFields($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('delallfields'=>0,'delotherfields'=>0,'file'=>$_FILES['file']['tmp_name']));
    $file=$p->get('file');
    $param=array('prefixSQL'=>$p->get('prefixSQL'),'delallfields'=>$p->get('delallfields'),'delotherfields'=>$p->get('delotherfields'));
    $ss=getExcelReader($file,$_FILES['file']['name']);
    $ss->setActiveSheetIndex(0);
    $ws=$ss->getActiveSheet();
    $tables=array();
    $message='';
    // Recherche de l'entete
    $col=0;
    $h=$ws->getCellByColumnAndRow($col,1)->getValue();
    while(!empty($h)) {
      if($h=='table') break;
      $col++;
      $h=$ws->getCellByColumnAndRow($col,1)->getValue();
    }
    unset($h);
    $it=$ws->getRowIterator();
    foreach ($it as $ii) {
      $i=$ii->getRowIndex();
      if($i==1) continue;
      $t=$ws->getCellByColumnAndRow($col,$i)->getValue();
      if(!in_array($param['prefixSQL'].$t,$tables)){
	if(!\Seolan\Core\System::tableExists($param['prefixSQL'].$t)){
	  $message.='- Ligne '.$i.' : table "'.$t.'" non existante<br>';
	  continue;
	}
	$message.='- Table '.$t.'<br>';
	$x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Tables&SPECS='.$param['prefixSQL'].$t);	
	$x->importSpec($ws,$message,$param);
	$tables[]=$param['prefixSQL'].$t;
      }
    }
    return \Seolan\Core\Shell::toScreen2('','message',$message);
  }

  /// Prepare l'import de sources et de leurs champs
  function preImportSourcesAndFields($ar=NULL){
  }
  /// Import de sources et de leurs champs via un fichier XLSX (1ere feuille : sources, feuilles suivantes : champs) (format de exportSourcesAndFields)
  function importSourcesAndFields($ar=NULL){ 
    $p=new \Seolan\Core\Param($ar,array('file'=>$_FILES['file']['tmp_name']));
    $param=array('prefixSQL'=>$p->get('prefixSQL'),'delallfields'=>$p->get('delallfields'),'delotherfields'=>$p->get('delotherfields'));
    $truncate=$p->get('truncatetable');
    $file=$p->get('file');
    $prefixSQL=$p->get('prefixSQL');
    $prefix=$p->get('prefix');
    $oo=array('translatable','auto_translate'); // Champ booleen de base
    $message='';
    $reader = new \PHPExcel_Reader_Excel2007();
    $ss=$reader->load($file);
    $ss->setActiveSheetIndex(0);
    $ws=$ss->getActiveSheet();
    $col=0;
    $h=$ws->getCellByColumnAndRow($col,1)->getValue();
    while(!empty($h)) {
      $head[$col]=$h;
      $col++;
      $h=$ws->getCellByColumnAndRow($col,1)->getValue();
    }
    unset($h);

    $it=$ws->getRowIterator();
    foreach ($it as $ii) {
      $i=$ii->getRowIndex();
      if($i==1) continue;
      $row=array();
      foreach($head as $j=>$h) {
	$value=$ws->getCellByColumnAndRow($j,$i)->getValue();
        if($value==='') continue;
        $pos=strpos($h,'[');
        if($pos) $tmp='['.substr($h,0,$pos).']'.substr($h,$pos);
        else $tmp='['.$h.']';
        $tmp=str_replace(array('[',']'),array("['","']"),$tmp);
	utf8_cp1252_replace($value);
        if($value==='_empty_') eval('$row'.$tmp.'="";');
        else eval('$row'.$tmp.'="'.str_replace('"','\\"',trim($value)).'";');
      }
      // Verifie les parametres
      if(empty($row['btab']) || empty($row['bclass'])){
	$message.='- Ligne '.$i.' : La table ou la classe n\'est pas renseignée<br>';
	continue;
      }
      // Verifie si le nom de la table est valide
      if(!preg_match("/[a-zA-Z0-9_-]+/",$row['btab'])){
	$message.='- Ligne '.$i.' : La table "'.$row['btab'].'" n\'a pas un nom valide.<br>';
	continue;
      }
      // Ajoute les prefixe si necessaire
      if(!empty($prefixSQL)) $row['btab']=$prefixSQL.$row['btab'];
      if(!empty($prefix)){
	foreach($row['bname'] as &$name){
	  $name=$prefix.$name;
	}
      }
      // Verifie si la table existe deja
      if(!\Seolan\Core\System::tableExists($row['btab'])){
	// Création de la table
	foreach($oo as $o) if(empty($row[$o])) $row[$o]=0;
	if(empty($row['bname'][TZR_DEFAULT_LANG])){
	  $t1=reset($row['bname']);
	  $row['bname'][TZR_DEFAULT_LANG]=$t1;
	}
        $row['classname'] = $row['bclass'];
	$ret=$row['bclass']::procNewSource($row);
	if($ret['error']) {
	  $message.='- Ligne '.$i.' : Erreur dans la creation de la table '.$row['btab'].':'.$ret['message'].'<br>';
	  continue;
	}
	$message.='- Ligne '.$i.' : La table '.$row['btab'].' a été créée.<br>';
      }else{
        $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS='.$row['bclass'].'&SPECS='.$row['btab']);
        $boid = $x->getBoid();
        
        $x->procEditProperties(array('options'=>$row));
	$message.='- Ligne '.$i.' : La table "'.$row['btab'].'" existe déjà => ';
	$message.='Mise à jour <br>';
      }
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS='.$row['bclass'].'&SPECS='.$row['btab']);
      if(empty($x)) {
	$message.='- Ligne '.$i.' : Erreur dans la construction de la table '.$row['btab'].' apres creation<br>';
	continue;
      }
      if($truncate || $row['truncate']){ 
	$x->clear();
	$message.='<dd>Table vidée</dd>';
      }

      // calcul du sheet name si > 22
      $sheetname = $row['btab'];
      if (strlen($row['btab'])>22){
          $sheetname = substr($row['btab'], 0, 19).sprintf('%03d', $i);
      }
      \Seolan\Core\Logs::notice(__METHOD__, 'importSpec for '.$row['btab'].' in sheet '.$sheetname);
      $s=$ss->getSheetByName($sheetname);
      if($s){
	$message.='<dd>Importe les specs pour '.$sheetname.'</dd>';
        $x->importSpec($s,$message,$param);
      }
      $s=$ss->getSheetByName($this->getSheetWithValuesName($row['btab']));

      if($s){
	$message.='<dd>Importe les valeurs</dd>';
	$x->importValues($s,$message,$param);
      }
    }
    // import SETS
    if ($ws = $ss->getSheetByName('SETS')) {
      $data = array();
      $rowIterator = $ws->getRowIterator();
      foreach ($rowIterator as $row) {
        $i = $row->getRowIndex();
        $cellIterator = $row->getCellIterator();
        foreach ($cellIterator as $cell)
          $data[$i][] = $cell->getValue();
      }
      $message .= $this->_importStringSet(array_values($data));
    }
    \Seolan\Core\Shell::toScreen2('','message',$message);
  }

  /// Retourne le nom de la feuille d'import/export Excel2007 contenant les valeurs des tables à importer/exporter
  function getSheetWithValuesName($table_name) {
    return substr($table_name, 0, 22).' - VALUES';
  }

  /// Exporte les champs d'une table au format csv (format de importFields)
  function exportFields($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('format'=>'csv'));
    $fmt=$p->get('format');
    $boid=$p->get('boid');
    $x=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $table=$x->getTable();
    $ss=new \PHPExcel();
    $ss->setActiveSheetIndex(0);
    $ws=$ss->getActiveSheet();
    $x->exportSpec($ws);
    sendPHPExcelFile($ss,$fmt,'exportfields_'.$table);
    exit(0);
  }

  /// Export de sources et de leurs champs (format de importSourcesAndFields)
  function exportSourcesAndFields($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array());
    $withvalues=$p->get('withvalues');
    $selected=$p->get('_selected');
    $bases=array_keys($selected);
    $ss=new \PHPExcel();
    $ss->setActiveSheetIndex(0);
    $ws=$ss->getActiveSheet();
    $ws->setTitle('BASEBASE');
    $ws->setCellValueByColumnAndRow($col++,1,'boid');
    $langs=array_keys($GLOBALS['TZR_LANGUAGES']);
    foreach($langs as $code) {
      $ws->setCellValueByColumnAndRow($col++,1,'bname['.$code.']');
    }    
    $ws->setCellValueByColumnAndRow($col++,1,'btab');
    $ws->setCellValueByColumnAndRow($col++,1,'auto_translate');
    $ws->setCellValueByColumnAndRow($col++,1,'translatable');
    $ws->setCellValueByColumnAndRow($col++,1,'nottorepli');
    $ws->setCellValueByColumnAndRow($col++,1,'bclass');
    $ws->setCellValueByColumnAndRow($col++,1,'log');
    $ws->setCellValueByColumnAndRow($col++,1,'bparam');
    $ws->setCellValueByColumnAndRow($col++,1,'own');
    $ws->setCellValueByColumnAndRow($col++,1,'publish');
    $r=getDB()->fetchAll('SELECT * FROM BASEBASE WHERE BOID IN ("'.implode('","',$bases).'")');
    $line=1;
    foreach($r as $base) {
      $col=0;
      $line++;
      $ws->setCellValueByColumnAndRow($col++,$line,$base['BOID']);
      foreach($langs as $code) {
	$amsg=getDB()->fetchRow('SELECT * FROM AMSG where MOID="'.$base['BOID'].'" AND MLANG="'.$code.'" LIMIT 1');
	if(empty($amsg)) $amsg['MTXT']='';
	$ws->setCellValueByColumnAndRow($col++,$line,$amsg['MTXT']);
      }
      $ws->setCellValueByColumnAndRow($col++,$line,$base['BTAB']);
      $ws->setCellValueByColumnAndRow($col++,$line,$base['AUTO_TRANSLATE']);
      $ws->setCellValueByColumnAndRow($col++,$line,$base['TRANSLATABLE']);
      $ws->setCellValueByColumnAndRow($col++,$line,$base['NOTTOREPLI']);
      $ws->setCellValueByColumnAndRow($col++,$line,$base['BCLASS']);
      $ws->setCellValueByColumnAndRow($col++,$line,$base['LOG']);
      $ws->setCellValueByColumnAndRow($col++,$line,$base['BPARAM']);
      $ws->setCellValueByColumnAndRow($col++,$line,(fieldExists($base['BTAB'],'OWN')?1:0));
      $ws->setCellValueByColumnAndRow($col++,$line,(fieldExists($base['BTAB'],'PUBLISH')?1:0));
      // sheet name : max 31 char
      if (strlen($base['BTAB'])>22){
          $sheetname = substr($base['BTAB'], 0,19).sprintf('%03d', $line);
      } else {
          $sheetname = $base['BTAB'];
      }
      $x=\Seolan\Core\DataSource\DataSource::objectFactory8($base['BOID']);
      $s1=$ss->createSheet();
      $s1->SetTitle($sheetname);
      $x->exportSpec($s1);
      if($withvalues){
	$s2=$ss->createSheet();
	$s2->SetTitle($this->getSheetWithValuesName($base['BTAB']));
	$x->exportValues($s2);
      }
      $tables[] = $base['BTAB'];
    }
    // export SETS
    $sets = getDB()->fetchAll('SELECT * FROM SETS WHERE STAB IN ("'.implode('","',$tables).'") order by STAB, FIELD, SORDER');
    if (count($sets)) {
      $ws = $ss->createSheet();
      $ws->SetTitle('SETS');
      $col = 0;
      $ws->setCellValueByColumnAndRow($col++,1,'soid');
      $ws->setCellValueByColumnAndRow($col++,1,'table');
      $ws->setCellValueByColumnAndRow($col++,1,'field');
      $ws->setCellValueByColumnAndRow($col++,1,'sorder');
      foreach ($langs as $code)
        $ws->setCellValueByColumnAndRow($col++,1,'label['.$code.']');
      $line=1;
      foreach ($sets as $set) {
        $_sets[$set['SLANG']][$set['SOID'].$set['STAB'].$set['FIELD']] = $set;
      }
      foreach ($_sets[TZR_DEFAULT_LANG] as $set) {
        $col=0;
        $line++;
        $ws->setCellValueByColumnAndRow($col++,$line,$set['SOID']);
        $ws->setCellValueByColumnAndRow($col++,$line,$set['STAB']);
        $ws->setCellValueByColumnAndRow($col++,$line,$set['FIELD']);
        $ws->setCellValueByColumnAndRow($col++,$line,$set['SORDER']);
        foreach ($langs as $code)
          $ws->setCellValueByColumnAndRow($col++,$line,$_sets[$code][$set['SOID'].$set['STAB'].$set['FIELD']]['STXT']);
      }
    }
    unset($r);
    sendPHPExcelFile($ss,'xl07','export');
  }
  
  /// Prépare le formulaire pour un import de data stringset
  function preImportStringSet($ar=NULL){
  }
  /// Import de data stringset
  function importStringSet($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('file'=>$_FILES['file']['tmp_name'],'endofline'=>"\r\n",'separator'=>';','quote'=>"\""));
    $file=$p->get('file');
    $prefix=$p->get('prefix');
    
    $spec->general->endofline=$p->get('endofline');
    $spec->general->separator=$p->get('separator');
    $spec->general->quote=$p->get('quote');
    $rawdata=@file_get_contents($file);
    $data=_getCSVData($rawdata,$spec);
    $message = $this->_importStringSet($data);
    \Seolan\Core\Shell::toScreen2('', 'message', $message);
  }

  private function _importStringSet($data) {
    $head=$data[0];
    $message='';
    $l=count($data);
    for($i=1;$i<$l;$i++){
      // Construit les données de la table a créer
      $row=array();
      foreach($head as $j=>$h){
        if($data[$i][$j]==='') continue;
        $pos=strpos($h,'[');
        if($pos) $tmp='['.substr($h,0,$pos).']'.substr($h,$pos);
        else $tmp='['.$h.']';
        $tmp=str_replace(array('[',']'),array("['","']"),$tmp);
	utf8_cp1252_replace($data[$i][$j]);
	eval('$row'.$tmp.'="'.str_replace('"','\\"',$data[$i][$j]).'";');
      }

      // Verifie si le nom de la table est valide
      if(empty($row['table']) || !preg_match("/[a-zA-Z0-9_-]+/",$row['table']) || !\Seolan\Core\System::tableExists($row['table'])){
	$message.='- La table "'.$row['table'].'" n\'a pas un nom valide ou n\'existe pas.<br>';
	continue;
      }
      if(empty($row['field']) || !preg_match("/[a-zA-Z0-9_-]+/",$row['field']) || !fieldExists($row['table'],$row['field'])){
	$message.='- Le champ "'.$row['table'].' -> '.$row['field'].'" n\'a pas un nom valide ou n\'existe pas.<br>';
	continue;
      }
      if(empty($row['label'][TZR_DEFAULT_LANG])){
        $t1=reset($row['label']);
        $row['label'][TZR_DEFAULT_LANG]=$t1;
      }
      $n1=new \Seolan\Field\StringSet\Management($row);
      if($n1->tsetExists($row['field'],array(TZR_DEFAULT_LANG),array(TZR_DEFAULT_LANG=>$row['label'][TZR_DEFAULT_LANG]))){
	$message.=$row['table'].' -> '.$row['field'].' : chaine "'.$row['label'][TZR_DEFAULT_LANG].'" existante<br>';
	continue;
      }
      if(empty($row['sorder'])) $row['sorder']=$n1->newTSetOrder($row['field']);
      $row['tplentry']=TZR_RETURN_DATA;
      $ret=$n1->procNewString($row);
      $message.=$row['table'].' -> '.$row['field'].' : chaine "'.$row['label'][TZR_DEFAULT_LANG].'" créée ('.$ret['oid'].')<br>';
    }
    return $message;
  }
  function XDSCreateXModTable($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $boid=$p->get('boid');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactory8($boid);
    $wd=new \Seolan\Module\Table\Wizard();
    $options=array();
    $options['group']='Auto';
    $options['table']=$xds->getTable();
    $wd->quickCreate($xds->getSourceName(), $options);
    setSessionVar('_reloadmods',1);
    setSessionVar('_reloadmenu',1);
  }

  // creation d'une table à partir d'un csv
  function csvToTable($ar) {
    \Seolan\Core\Shell::changeTemplate('Module/DataSource.csvToTable.html');
    $p = new \Seolan\Core\Param($ar, array('file' => $_FILES['file']['tmp_name'], 'separator' => ';'));
    $file = $p->get('file');
    $btab = $p->get('btab');
    $bname = $p->get('bname');
    if (empty($file) && empty($btab)) {
      return;
    }
    if (\Seolan\Core\System::tableExists($btab)) {
      \Seolan\Core\Shell::toScreen2('br', 'message', 'La table existe');
      return;
    }
    // premier post lecture des entêtes pour paramétrage des champs
    if ($file) {
      $separator = $p->get('separator');
      if ($separator == '\t')
        $separator = "\t";
      $data = array();
      if (($handle = fopen($file, 'r')) !== FALSE) {
        while (($data[] = fgetcsv($handle, 1000, $separator)) !== FALSE);
        fclose($handle);
      }
      $header = $data[0];
      $labels = $fields = $fcounts = [];
      foreach ($header as $i => $col) {
        $asciify = str_replace('-', '', rewriteToAscii($col));
        $labels[] = ucfirst($asciify);
        if(isSQLKeyword($asciify) || isTZRKeyword($asciify) || is_numeric($asciify)) {
          $asciify .= '_';
        }
        $fields[] = $asciify;
        $fcounts[$i] = 10;
        for ($j = 1, $count = count($data); $j < $count; $j++) {
          $fcounts[$i] = max($fcounts[$i], ceil(strlen($data[$j][$i]) / 10) * 10);
        }
      }
      $tmpFile = uniqid();
      file_put_contents(TZR_TMP_DIR . $tmpFile, serialize($data));

      return \Seolan\Core\Shell::toScreen2('import', 'csv', $r = array(
        'action' => 'selectFields',
        'tmpFile' => $tmpFile,
        'labels' => $labels,
        'fields' => $fields,
        'fcounts' => $fcounts,
        'fieldTypes' => \Seolan\Core\Field\Field::getTypes(),
        'btab' => $btab,
        'bname' => $bname,
        'sample' => $data[1],
      ));
    }
    // step2 creation table
    ini_set('max_execution_time', 0);
    $tmpFile = $p->get('tmpFile');
    $data = unserialize(file_get_contents(TZR_TMP_DIR . $tmpFile));
    unlink(TZR_TMP_DIR . $tmpFile);
    $labels = $p->get('labels');
    $fields = $p->get('fields');
    $ftypes = $p->get('ftypes');
    $fcounts = $p->get('fcounts');
    $fmts = $p->get('fmts');
    $oidstruct = $p->get('oidstruct');
    $bparam = [];
    $i = 1;
    foreach ($oidstruct as $id) {
      $bparam['oidstruct' . $i++] = $fields[$id];
    }
    \Seolan\Model\DataSource\Table\Table::procNewSource(array(
      'translatable' => 0,
      'auto_translate' => 0,
      'btab' => $btab,
      'bname' => array(TZR_DEFAULT_LANG => $bname),
      'publish' => false,
      'own' => false,
      'bparam' => $bparam
    ));
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($btab);
    $boid = $ds->getBoid();

    // création des champs
    foreach ($fields as $i => $field) {
      $ds->createField($field, $labels[$i], $ftypes[$i], $fcounts[$i], '', 0, 1, $i<6, 0, 0, 0);
      switch ($ftypes[$i]) {
        case '\Seolan\Field\StringSet\StringSet':
          $values = [];
          $order = 0;
          for ($j = 1, $count = count($data)-1; $j < $count; $j++) {
            $values[] = $data[$j][$i];
          }
          foreach (array_unique($values) as $val) {
            $this->XDSProcNewString(array(
              'boid' => $boid, 'field' => $field,
              'label' => array(TZR_DEFAULT_LANG => $val), 'sorder' => $order++, 'soid' => $val));
          }
          break;
        // TODO other type
      }
    }
    // pour oidstruct clearCache
    \Seolan\Core\System::clearCache();
    \Seolan\Core\DataSource\DataSource::clearCache();
    $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($btab);
    // import des lignes
    for ($i = 1, $count = count($data)-1; $i < $count; $i++) {
      $inputs = array_combine($fields, $data[$i]);
      foreach ($fields as $j => $field) {
        switch ($ftypes[$j]) {
          case '\Seolan\Field\Date\Date':
            $inputs[$field] = $ds->desc[$field]->convert($inputs[$field], $fmts[$j], 'Y-m-d');
            break;
          // TODO other type
        }
      }
      $ds->procInput(array_merge($inputs, array(
        '_nolog' => true,
        '_options' => array('local' => true)
      )));
    }
    \Seolan\Core\Shell::setNext('moid='.$this->_moid.'&boid='.$boid.'&function=XDSContentBrowse&tplentry=br&&template=Module/DataSource.XDSContentBrowse.html');
  }

  /// representation graphique des liens et des champs des tables
  function tablesToUml($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $selected=$p->get('_selected');
    $bases=array_keys($selected);
    $rs = getDB()->fetchAll("select btab, bname, boid from BASEBASE order by bname asc");
    $tl = array(
      'lines_tableselected'=>array(), 
      'lines_linkto'=>array(),
      'lines_tablecode'=>array(),
      'lines_tablelabel'=>array(),
      'lines_tableset'=>array()
    );
    foreach($rs as $i=>$ors){
      $tl['lines_tableselected'][] = (!empty($bases) && in_array($ors['boid'], $bases));
      $tl['lines_tablecode'][] = $rs[$i]['btab'];
      $tl['lines_tablelabel'][] = $rs[$i]['bname'];
    }
    foreach($tl['lines_tablelabel'] as $i=>$tlabel){
      $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$tl['lines_tablecode'][$i]);
      $tl['lines_linkto'][$i] = array('link'=>array());
      foreach($x->desc as $fn=>&$fo){
        $min = $fo->compulsory?'1':'0';
        $max = $fo->multivalued?'n':'1';
        $fo->_card = "\{$min:$max\}";
        if ($fo->isLink() && \Seolan\Core\System::tableExists($fo->target) /*&& !$fo->sys*/){
          $it = array_search($fo->target, $tl['lines_tablecode']);
          if ($it !== false)
            $tl['lines_linkto'][$i]['link'][] = array('fn'=>$fn, 'it'=>$it);
        }
      }
      $tl['lines_tableset'][$i] = $x;
    }
    \Seolan\Core\Shell::toScreen1('br', $tl);
  }


}
?>
