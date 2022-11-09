<?php
namespace Seolan\Module\Media;
/// Module de gestion d'une médiathèque
class Media extends \Seolan\Module\Table\Table {
  static protected $iconcssclass='csico-collections';
  public $submodmax=0;
  public $collectionmod;
  public $imgresize='800x600>';
  public $browsethumbsize='550x295';
  public $imports=NULL;
  public $searchtemplate='Module/Media.searchResult.html';
  protected static $contactsheettemplate = 'Module/Media.printContactSheet.xml';
  function __construct($ar=NULL){
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Media_Media');
    if(!empty($this->collection))
      $this->collectionmod=\Seolan\Core\Module\Module::objectFactory($this->collection);
  }

  /// Liste des groupes de droits valides pour ce module
  static function getRoList(){
    return array('ro1','ro2','ro3','ro');
  }

  /// Securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $list=static::secListList();
    $ro=static::secRoList();
    $g=array();
    $g['browse']=$list;
    $g['browseFiles']=$list;
    $g['browseCollections']=$list;
    $g['display']=$ro;
    $g['displayJSon']=$ro;
    $g['displayMedia']=$ro;
    $g['displayInfos']=$ro;
    $g['insert']=array('rw','rwv','admin');
    $g['procInsert']=array('rw','rwv','admin');
    $g['edit']=array('rw','rwv','admin');
    $g['editSelection']=array('rw','rwv','admin');
    $g['editAll']=array('rw','rwv','admin');
    $g['procEdit']=array('rw','rwv','admin');
    $g['procEditAllLang']=array('rw','rwv','admin');
    $g['procEditSelection']=array('rw','rwv','admin');
    $g['del']=array('rw','rwv','admin');
    $g['delAll']=array('rw','rwv','admin');
    $g['query']=$list;
    $g['quickquery']=$list;
    $g['procQuery']=$list;
    $g['procQueryFiles']=$list;
    $g['delStoredQuery']=array('rw','rwv','admin');
    $g['preExportFiles']=$list;
    $g['exportFiles']=$list;
    $g['exportFilesBatch']=$list;
    $g['importOnFly']=array('rw','rwv','admin');
    $g['procImportOnFly']=array('rw','rwv','admin');
    $g['chooseDownloadFormat']=$list;
    $g['downloadMedias']=$ro;
    $g['prePrintContactSheet']=$list;
    $g['printContactSheet']=$list;

    $g['importBrowse']=array('admin');
    $g['importDisplay']=array('admin');
    $g['importInput']=array('admin');
    $g['importProcInput']=array('admin');
    $g['importEdit']=array('admin');
    $g['importProcEdit']=array('admin');
    $g['importDel']=array('admin');
    $g['runImports']=array('admin');

    $g['exportBrowse']=array('admin');
    $g['exportDisplay']=array('admin');
    $g['exportInput']=array('admin');
    $g['exportProcInput']=array('admin');
    $g['exportEdit']=array('admin');
    $g['exportProcEdit']=array('admin');
    $g['exportDel']=array('admin');
    $g['runExports']=array('admin');

    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Rend la liste des fonctions utilisables dans le gestionnaire de rubriques en mode fonction (tableau de paires fonction=>label)
  function getUIFunctionList() {
      return array('procQuery'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','uiquery','text'),
		   'display'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','uidisplay','text'));
  }

  /// Options du module
  public function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('object_sec');
    $this->_options->delOpt('multipleedit');
    $this->_options->delOpt('owner_sec');
    $this->_options->delOpt('captcha');
    $this->_options->delOpt('submodmax');
    $this->_options->delOpt('submodsearch');

    $alabel=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','modulename');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','collectionmod'),'collection','module',array('validate'=>true,'emptyok'=>false,'toid'=>XMODMEDIACOLLECTION_TOID),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','imports'),'imports','table',array('validate'=>true,'emptyok'=>true),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','imgresize'),'imgresize','text',NULL,'800x600>',$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','advanced_dl'),'advanced_dl','boolean',NULL,false,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','browsethumbsize'),'browsethumbsize','text',NULL,'550x295',$alabel);
  }

  /// Cette fonction est appliquee pour afficher l'ensemble des methodes de ce module
  protected function _actionlist(&$my, $alfunction=true) {
    parent::_actionlist($my, $alfunction);
    $f=\Seolan\Core\Shell::_function();
    $myoid=$_REQUEST['oid'] ?? '';
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $p = new \Seolan\Core\Param(array(),array());
    // Stack
    if($this->interactive){
      $o1=new \Seolan\Core\Module\Action($this,'browse',$this->getLabel(),
			    '&moid='.$this->_moid.'&_function=browse&template=Module/Media.browse.html&tplentry=br','display');
      $my['stack'][0]=$o1;
      if(strpos($f,'import')===0 && $f!='importOnFly'){
	$o1=new \Seolan\Core\Module\Action($this,'importBrowse',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','imports','text'),
			      '&moid='.$this->_moid.'&function=importBrowse&template=Module/Media.importBrowse.html&tplentry=br');
	$my['stack'][]=$o1;
      }
      if(strpos($f,'export')===0){
	$o1=new \Seolan\Core\Module\Action($this,'exportBrowse',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','exports','text'),
			      '&moid='.$this->_moid.'&function=exportBrowse&template=Module/Media.exportBrowse.html&tplentry=br');
	$my['stack'][]=$o1;
      }
      if(strpos($f, 'browseCollections')===0) {

	$o1=new \Seolan\Core\Module\Action($this,'browseCollections',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','collections','text'),
			      '&moid='.$this->_moid.'&function=browseCollections&template=Module/Media.browseCollections.html&tplentry=br');
	$my['stack'][]=$o1;
	$oidcoll=$p->get('oidcoll');
	if(!empty($oidcoll)) {
	  $oidcoll=array_pop($oidcoll);
	  $coll=$this->collectionmod->xset->rDisplay($oidcoll);
	  $parent=NULL;
	  if(!empty($coll['oparent']->raw)) {
	    $parent=$this->collectionmod->xset->rDisplay($coll['oparent']->raw);
	    $o1=new \Seolan\Core\Module\Action($this,'browseCollections',$parent['otitle']->html,
				  '&moid='.$this->_moid.'&function=browseCollections&template=Module/Media.browseCollections.html&tplentry=br&oidcoll[]='.$coll['oparent']->raw);
	    $my['stack'][]=$o1;
	  }
	  if(!empty($coll)) {
	    $o1=new \Seolan\Core\Module\Action($this,'browseCollections',$coll['otitle']->html,
				  '&moid='.$this->_moid.'&function=browseCollections&template=Module/Media.browseCollections.html&tplentry=br&oidcoll[]='.$oidcoll);
	    $my['stack'][]=$o1;
	  }
	}
      }
    }

    if($this->secure('','browse')){
      // Parcourir
      $o1=new \Seolan\Core\Module\Action($this,'browse',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','browse','text'),
			    '&moid='.$this->_moid.'&_function=browse&template=Module/Media.browse.html&tplentry=br','display');
      $o1->containable=true;
      $o1->setToolbar('Seolan_Core_General','browse');
      $my['browse']=$o1;
    }
    if($this->secure('','browseCollections')){
      // Parcourir les collections
      $o1=new \Seolan\Core\Module\Action($this,'browseCollections',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','browsecollections','text'),
			    '&moid='.$this->_moid.'&_function=browseCollections&template=Module/Media.browseCollections.html&tplentry=br','display');
      $o1->containable=true;
      $o1->menuable=true;
      $my['browseCollections']=$o1;
    }
    // Recherche
    if($this->secure('','query')){
      $o1=new \Seolan\Core\Module\Action($this,'query',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','query','text'),
			    '&moid='.$this->_moid.'&_function=query&template=Module/Media.query.html&tplentry=br&querymode=query2','display');
      $o1->containable=true;
      $o1->setToolbar('Seolan_Core_General','query');
      $my['query']=$o1;
    }

    // Recherche en cours
    if($this->isThereAQueryActive()) {
      $o1=new \Seolan\Core\Module\Action($this,'procQuery',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','currentquery','text'),
			    '&moid='.$this->_moid.'&_function=procQuery&template=Module/Media.browse.html&tplentry=br','display');
      $o1->setToolbar('Seolan_Core_General','currentquery');
      $my['procquery']=$o1;
    }

    // Insert
    $translatable = $this->xset->getTranslatable();
    $lang_data=\Seolan\Core\Shell::getLangData();
    if(TZR_LANG_FREELANG==$translatable) $sec=$this->secure($myoid,'insert',($foo=null),$lang_data);
    else if (TZR_LANG_BASEDLANG==$translatable && $lang_data!=TZR_DEFAULT_LANG) $sec=false;
    else $sec=$this->secure($myoid,'insert');
    if($sec && $lang_data!=TZR_DEFAULT_LANG) {
      $o1=new \Seolan\Core\Module\Action($this,
					 'insert',
					 \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','new','text'),
					 http_build_query(['moid'=>$this->_moid,
							   '&funtion'=>'insert',
							   'template'=>'Module/Media.new.html',
							   'tplentry'=>'br',
							   'lang_data'=>$lang_data]),
					 'edit');
      //'&moid='.$this->_moid.'&_function=insert&template=Module/Media.new.html&tplentry=br&lang_data='.$lang,'edit');							  
      $o1->order=1;
      $o1->setToolbar('Seolan_Core_General','new');
      $my['insert']=$o1;
    }

    // Avertir
    if ($this->sendacopyto && $this->secure('', 'sendACopyTo')){
      if(!empty($myoid)) {
	$o1=new \Seolan\Core\Module\Action($this,'sendACopy',\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','sendacopyto','text'),
			      '&moid='.$this->_moid.'&tplentry=br&oid='.$myoid.'&_function=sendACopyTo&template=Core/Module.sendacopyto.html&tplentry=br');
      }else{
  $o1=new \Seolan\Core\Module\Action($this,'sendACopy',\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','sendacopyto','text'),
                                     'javascript:TZR.Table.applyfunction("'.substr($uniqid, 1).'","sendACopyTo","",{template:"Core/Module.sendacopyto.html"},true,true);return false;');
      }
      $o1->menuable=true;
      $o1->group='more';
      $my['sendacopy']=$o1;
    }

    // Imports
    if($this->imports){
      if($this->secure('','importOnFly')){
	$o1=new \Seolan\Core\Module\Action($this,'import',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','importonfly','text'),
			      '&moid='.$this->_moid.'&function=importOnFly&template=Module/Media.importOnFly.html&tplentry=br','more');
	$o1->menuable=true;
	$my['importonfly']=$o1;
      }
      if($this->secure('','importBrowse')){
	$o1=new \Seolan\Core\Module\Action($this,'import',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','imports','text'),'#','more');
	$o1->menuable=true;
	$o1->newgroup='impgrp';
	$my['importbis']=$o1;
	$o1=new \Seolan\Core\Module\Action($this,'importbrowse',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','browse','text'),
			      '&moid='.$this->_moid.'&function=importBrowse&template=Module/Media.importBrowse.html&tplentry=br','impgrp');
	$o1->menuable=true;
	$my['importBrowse']=$o1;
	if($this->secure($myoid,'importInput')){
	  $o1=new \Seolan\Core\Module\Action($this,'importinput',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','new','text'),
				'&moid='.$this->_moid.'&function=importInput&template=Module/Media.importNew.html&tplentry=br','impgrp');
	  $o1->menuable=true;
	  $my['importNew']=$o1;
	}
      }
    }

    // Exports
    if($this->secure('','exportBrowse')){
      $o1=new \Seolan\Core\Module\Action($this,'import',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','exports','text'),'#','more');
      $o1->menuable=true;
      $o1->newgroup='expgrp';
      $my['exports']=$o1;
      $o1=new \Seolan\Core\Module\Action($this,'exportbrowse',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','browse','text'),
			    '&moid='.$this->_moid.'&function=exportBrowse&template=Module/Media.exportBrowse.html&tplentry=br','expgrp');
      $o1->menuable=true;
      $my['exportBrowse']=$o1;
      if($this->secure($myoid,'exportInput')){
	$o1=new \Seolan\Core\Module\Action($this,'exportinput',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','new','text'),
			      '&moid='.$this->_moid.'&function=exportInput&template=Module/Media.exportNew.html&tplentry=br','expgrp');
	$o1->menuable=true;
	$my['exportNew']=$o1;
      }
    }

    // Regles workflow
    if($this->stored_query){
      $modrulemoid=\Seolan\Core\Module\Module::getMoid(XMODRULE_TOID);
      if(!empty($modrulemoid)){
	$o1=new \Seolan\Core\Module\Action($this,'rule',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Workflow_Rule_Rule','addrule','text'),
			      '&amoid='.$this->_moid.'&moid='.$modrulemoid.
			      '&_function=insertRule&tplentry=br&template=Module/Workflow/Rule.newRule.html&atemplate=Module/Media.editSelection.html');
	$o1->menuable=true;
	$o1->group='more';
	$my['rule']=$o1;
      }
    }

    // Mode conception et administration
    if($this->secure('',':admin')){
      $o1=new \Seolan\Core\Module\Action($this, 'administration', \Seolan\Core\Labels::getSysLabel('Seolan_Core_General', 'administration', 'text'),
			    '&moid='.$this->_moid.'&function=adminBrowseFields&template=Core/Module.admin/browseFields.html&boid='.$this->boid,
			    'admin');
      $o1->setToolbar('Seolan_Core_General','administration');
      $my['administration']=$o1;
    }
  }
  /// Actions du browse
  function al_browse(&$my){
    $uniqid=\Seolan\Core\Shell::uniqid();
    $moid=$this->_moid;

    // Changement taille page
    $o1=new \Seolan\Core\Module\Action($this,'pgmore',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','page_size','text').' * 2',
			  'javascript:TZR.Table.go_browse("'.$uniqid.'","start","*2");','display');
    $o1->menuable=true;
    $my['pgmore']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'pgless',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','page_size','text').' / 2',
			  'javascript:TZR.Table.go_browse("'.$uniqid.'","start","*2");','display');
    $o1->menuable=true;
    $my['pgless']=$o1;

    if($this->secure('','del')){
      $o1=new \Seolan\Core\Module\Action($this,'del',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete','text'),
					 'javascript:TZR.Table.deleteselected("'.$uniqid.'", "del");',
					 'edit');
      /*
,"'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','confirm_delete_object','text').'")
       */
      $o1->order=3;
      $o1->setToolbar('Seolan_Core_General','delete');
      $my['del']=$o1;
    }
    // Export
    if($this->secure('','exportFiles')) {
      $o1=new \Seolan\Core\Module\Action($this,'export',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','export','text'),
					 'javascript:TZR.Table.applyfunction("'.$uniqid.'","preExportFiles","",{fromfunction:"'.\Seolan\Core\Shell::_function().'",'.
					 'template:"Module/Media.preExportFiles.html"},false,true,true);','edit');
      $my['export']=$o1;
    }
    if ($this->secure('', 'addToUserSelection')) {
      $o1=new \Seolan\Core\Module\Action($this,'addToUserSelection',\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','addtoselection','text'),
					 "javascript:TZR.Table.addToSelection('$moid','".$uniqid."');");
      $o1->menuable=true;
      $o1->group='edit';
      $my['addToUserSelection']=$o1;
    }

    // Impression contact
    if($this->secure('','prePrintContactSheet')) {
      $o1=new \Seolan\Core\Module\Action($this,'sheet',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','contactsheet','text'),
					 'javascript:TZR.Table.applyfunction("'.$uniqid.'", "prePrintContactSheet","",{fromfunction:"'.\Seolan\Core\Shell::_function().'",'.
					 'template:"Module/Media.prePrintContactSheet.html"},false,true,false);','edit');
      $o1->menuable=true;
      $my['sheet']=$o1;
    }
    // Edition par lot
    if($this->secure('','editSelection')){
      $o1=new \Seolan\Core\Module\Action($this,'editselection',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','editselection','text'),
			    'javascript:TZR.Table.applyfunction("'.$uniqid.'","editSelection","",{template:"Module/Media.editSelection.html"},true,true);','edit');
      $o1->order=2;
      $o1->setToolbar('Seolan_Core_General','edit');
      $my['editselection']=$o1;
    }

    // Edition/suppression sur le resultat d'une recherche
    if($this->isThereAQueryActive()){
      if($this->secure('','editAll')){
	$o1=new \Seolan\Core\Module\Action($this,'editall',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','editall','text'),
			      "javascript:TZR.Table.applyfunction('{$uniqid}', 'editAll', '', {template:'Module/Media.editSelection.html'},false,true);",'edit');
	$o1->order=2;
	$o1->menuable=true;
	$my['editall']=$o1;
      }
      if($this->secure('','delAll')){
	$o1=new \Seolan\Core\Module\Action($this,'delall',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delall','text'),
			      'javascript:TZR.Table.applyfunction("'.$uniqid.'","delAll","'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','confirm_delete_object','text').'","");','edit');
	$o1->order=3;
	$o1->menuable=true;
	$my['delall']=$o1;
      }
    }
  }
  function al_procQuery(&$my){
    $this->al_browse($my);
  }
  function al_display(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    if($this->secure('','edit')){
      $o1=new \Seolan\Core\Module\Action($this,'edit',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit','text'),
			    '&function=edit&moid='.$this->_moid.'&template=Module/Media.edit.html&tplentry=br&oid='.@$_REQUEST['oid'],'edit');
      $o1->order=2;
      $o1->setToolbar('Seolan_Core_General','edit');
      $my['edit']=$o1;
    }
  }
  function al_input(&$my){
    $o1=new \Seolan\Core\Module\Action($this,'save',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','save','text'),'javascript:TZR.Record.save("'.\Seolan\Core\Shell::uniqid().'");','edit');
    $o1->order=1;
    $o1->setToolbar('Seolan_Core_General','save');
    $my['save']=$o1;
  }
  function al_edit(&$my){
    $o1=new \Seolan\Core\Module\Action($this,'save',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','save','text'),'javascript:TZR.Record.save("'.\Seolan\Core\Shell::uniqid().'");','edit');
    $o1->order=1;
    $o1->setToolbar('Seolan_Core_General','save');
    $my['save']=$o1;
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=browse&tplentry=br&template=Module/Media.browse.html&_persistent=1';
  }

  /// Fonction daemon
  public function _daemon($period='any'){
    $this->runExports();
    if($this->imports) $this->runImports();
    return parent::_daemon($period);
  }

  /// Liste des tables utilisées par le module
  public function usedTables(){
    $ret=array($this->table,$this->collectionmod->table,$this->imports);
    return $ret;
  }

  /// Affichage du media uniquement
  function &displayMedia($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('selectedfields'=>array()));
    $selectedfields=$p->get('selectedfields');
    $selectedfields[]='media';
    $ar['selectedfields']=$selectedfields;
    $this->xset->desc['media']->autoplay=true;
    $template=\Seolan\Core\Shell::getTemplate();
    if (!file_exists($GLOBALS['TEMPLATES_DIR'].$template)){
      $file= TZR_SHARE_DIR.'Module/Media.viewMedia.html';
      $xt = new \Seolan\Core\Template('file:/'.$file);
      $r3=array();
      $ret=$this->xset->display($ar);
      $tpldata['br']=$ret;
      $content=$xt->parse($tpldata,$r3,NULL);
      echo($content);
      exit(0);
    }
    return $this->xset->display($ar);
  }

  /// Affichage des infos d'un media
  function &displayInfos($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $d=$this->xset->display($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry,$d);
  }

  /// parcourir les collections pour acceder aux photos
  function browseCollections($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $oidcoll=$p->get('oidcoll');

    // recherche des collections accessibles a l'utilisateur
    // si un oid de collection est passé on ne recherche que les collections filles
    if(!empty($oidcoll))
      $rq=$this->collectionmod->xset->select_query(array('cond'=>array('parent'=>array('=',$oidcoll))));
    else
      $rq=$this->collectionmod->xset->select_query(array('cond'=>array('parent'=>array('=',''))));

    $collections = $this->collectionmod->browse(array('tplentry'=>TZR_RETURN_DATA, 'pagesize'=>'1000',
							 'select'=>$rq, '_options'=>array('local'=>1)));

    $imagesdone=array();
    foreach($collections['lines_oid'] as $i=>$oid) {
      // recherche dans chaque collection d'une image a afficher en tête de gondole.
      // comme certaines images sont dans plusieurs collections, on essaie d'en prendre des uniques
      $oidimage=NULL;
      $q=$this->xset->procQuery(array('pagesize'=>10,'tplentry'=>TZR_RETURN_DATA, 'collection'=>array($oid), 'order'=>'UPD DESC','_options'=>array('local'=>1)));
      $j=0;$found=false;
      while(!$found && $j<count($q['lines_oid'])) {
	if(!empty($q['lines_oid'][$j]) && !in_array($q['lines_oid'][$j], $imagesdone)) {
	  $found=true;
	  $oidimage=$q['lines_oid'][$j];
	  array_push($imagesdone, $oidimage);
	} else $j++;
      }

      // recherche des sous-collections
      if(!empty($oidimage)) {
	$image=$this->xset->rDisplay($oidimage);
	$collections['lines_omedia'][$i]=$image['omedia'];
	$collections['lines_url'][$i]='&function=procQuery&moid='.$this->_moid.'&template=Module/Media.browse.html&collection[]='.$oid.
	  '&tplentry=br&clearrequest=1&_persistent=1&pagesize='.$this->pagesize;

	$rq='SELECT DISTINCT KOID FROM '.$this->collectionmod->xset->getTable().' WHERE PARENT =?';
	$cnt=getDB()->count($rq, array($oid),true);
	if($cnt>0) $collections['lines_url'][$i]='&function=browseCollections&moid='.$this->_moid.'&template=Module/Media.browseCollections.html&oidcoll[]='.$oid.'&tplentry=br';
	$collections['lines_sub']=$cnt;

      } else
	$collections['lines_omedia'][$i]=NULL;
    }
    $collections['pagesize']=$this->pagesize;

    // calcul des infos sur la collection courante
    if(!empty($oidcoll)) {
      $oidcoll=array_pop($oidcoll);
      $collections['currentcollection']=$this->collectionmod->xset->rDisplay($oidcoll);
    }
    $collections['_browsethumbsize'] = $this->browsethumbsize;
    return \Seolan\Core\Shell::toScreen1($tplentry, $collections);
  }


  /// Mode parcourir
  function browse($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array(), "all",
                              array('pagesize'=>array(FILTER_VALIDATE_INT,array()),
                                    'order'=>array(FILTER_CALLBACK,array('options'=>'containsNoSQLKeyword'))));
    $p=new \Seolan\Core\Param($ar,NULL);
    $select=$p->get('select','norequest');
    $tplentry=$p->get('tplentry');
    $persistent=$p->get('_persistent');
    $order=$this->checkOrderFields($p->get('order'));
    $pagesize=$p->get('pagesize');
    if(empty($pagesize)) $pagesize=$this->pagesize;
    if($this->persistentquery && $persistent) clearSessionVar('filterquery'.$this->_moid);
    if($this->interactive){
      $this->_setSession('lastorder',$order);
      if($this->isThereAQueryActive()) $this->_clearSession('query');
    }
    $ar['pagesize']=$pagesize;
    $filter=$this->getFilter(true,$ar);
    if($filter===NULL) return NULL;
    $ar['_filter']=$filter;
    $ar['order']=$order;
    $lang_data=\Seolan\Core\Shell::getLangData();
    $ar['tplentry']=TZR_RETURN_DATA;
    if(empty($ar['fmoid'])) $ar['fmoid']=$this->_moid;
    $ar['fieldssec']=$this->fieldssec;
    // on ne recherche que les champs affichés
    $selectedfields = $p->get('selectedfields');
    if(!empty($selectedfields))
      $ar['selectedfields']=$selectedfields;
    else
      $ar['selectedfields']=$this->xset->getPublished();
    if (!empty($ar['selectedfields']) && is_array($ar['selectedfields'])){
      if(!in_array('media',$ar['selectedfields'])) $ar['selectedfields'][]='media';
      if(!in_array('PUBLISH',$ar['selectedfields'])) $ar['selectedfields'][]='PUBLISH';
    } // else = is_string && == 'all'
    $r = $this->xset->browse($ar);
    $r['function']='browse';

    // Permet de surcharger le template d'affichage de la liste en back-office via les propriétés du module
    if(!empty($this->_templates) && !empty($this->btemplates)) {
      $this->_templates->display(array('oid'=>$this->btemplates,'_options'=>array('error'=>'return'),'tplentry'=>$tplentry.'t'));
    }

    $this->applyObjectsSec($r);
    unset($ar['selectedfields']);

    if(\Seolan\Core\Shell::admini_mode()) {
      $translation_mode = $this->translationMode($p);
      if ($translation_mode){
	$r['lang_trad'] = $translation_mode->LANG_TRAD;
      }
      if (!$p->get('without_actions')) {
	$this->browse_actions($r);
      }
      // Recherche rapide
      if ($this->quickquery) {
        $ar['tplentry']=TZR_RETURN_DATA;
        $r['_qq']=$this->quickquery($ar);
      }
    }
    $r['_browsethumbsize'] = $this->browsethumbsize;
    return \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }

  /// Parcours le module pour la selection d'un fichier
  public function &browseFiles($ar) {
    // pas d'edition ni suppression depuis l'écran de parcours
    $fields = array();
    //cinq champ publié max et les champ fichier
    foreach($this->xset->desc as $fname=>$fdef){
      if( (count($fields) < 5 && $fdef->get_published()) || ($fdef->get_ftype() == '\Seolan\Field\File\File' || $fdef->get_ftype() == '\Seolan\Field\Image\Image' || $fdef->get_ftype() == '\Seolan\Field\Video\Video')){
	$fields[]=$fname;
      }
    }
    $ar['selectedfields']=$fields;
    $ar['without_actions'] = true;
    $browse = $this->browse($ar);
    // si pas de titre aux images, ajout depuis le champ title
    // permet la récupération d'un titre dans "parcourir les modules"
    foreach ($browse['lines_oid'] as $i => $oid) {
      if (preg_match('/title=""/', $browse['lines_omedia'][$i]->html)) {
	$browse['lines_omedia'][$i]->html = preg_replace('/(title|alt)=""/', '$1="'.$browse['lines_otitle'][$i]->html.'"', $browse['lines_omedia'][$i]->html);
      }
    }
    return $browse;
  }

  /**
   * Surcharge RL 29-06-2022 : 
   *  traitement systématique de la sécurité sur les objets : pas d'option objects_sec dans le module Media
   *  traitement des oids en fonction de la sécurité calculé par applyObjectsSec()
   * 
   * Affiche une section fonction de type "Liste" dans un gestionnaire de rubriques
   * @param $ar array Récupère les paramètres de la section
   * @return $result array Liste des résultats
   */
  public function &UIView_procQuery($ar=NULL){
    //stockage des paramètres de la section
    $sectionFilter = $ar;
    //valeur eposté dans le formulaire
    $params = $_REQUEST['sectionopts'][$ar['itoid']];
    $ar['_storedquery'] = $ar['___storedquery'];
    if(!empty($ar['__selectedfields']))
      $selectedfields = explode('||',$ar['__selectedfields']);
    else
      $selectedfields = $this->xset->browsableFields();
    $groupfields = preg_split('/\|\|/', $ar['__groupfields'], 0, PREG_SPLIT_NO_EMPTY);
    $sortfields = preg_split('/\|\|/', $ar['__sortfields'], 0, PREG_SPLIT_NO_EMPTY);
    $orderfields = preg_split('/\|\|/', $ar['__order'], 0, PREG_SPLIT_NO_EMPTY);
    $ar['order'] = implode(',', $orderfields);
    $forceorder = isset($ar['forceorder'])?$ar['forceorder']:(isset($params['forceorder'])?$params['forceorder']:NULL);

    // nb de réponses
    $pagesizes = explode(',', $ar['__pagesize']);
    if ($params['pagesize']) {
      $pagesize = $params['pagesize'];
      $this->_setSession('pagesize_'.$ar['itoid'], $pagesize, '_TZRSF');
    } else {
      if ($this->_issetSession('pagesize_'.$ar['itoid'], '_TZRSF'))
        $pagesize = $this->_getSession('pagesize_'.$ar['itoid'], '_TZRSF');
      elseif($pagesizes[0])
        $pagesize = $pagesizes[0];
    }
    if (empty($pagesize))
      $pagesize = TZR_XMODTABLE_BROWSE_PAGESIZE;
    // recupération des parametres postés (pagination, ordre, )
    if ($params)
      $ar = array_merge($ar, $params);

    // ajouter les champs de groupage en premier dans l'ordre
    // permettre de trier sur ces champs
    $sqlorder = $ar['order'];
    if ($groupfields) {
      $grpfields = $groupfields;
      $sqlorderfields = preg_split('/\|\|/', $sqlorder, 0, PREG_SPLIT_NO_EMPTY);
      foreach ($sqlorderfields as $i => $orderfield) {
        $field = preg_replace('/( ASC| DESC)/i', '', $orderfield);
        if (($index = array_search($field, $grpfields)) !== false) {
          $grpfields[$index] = $orderfield;
          unset($sqlorderfields[$i]);
        }
      }
      $ar['order'] = implode(',', array_merge($grpfields, $sqlorderfields));
    }

    // retour depuis une fiche
    if (!$this->_issetSession('oids_'.$ar['itoid'], '_TZRSF')) {
      $do_select = true;
      $first = $params['first']? $params['first']:0;
    } else {
      // les oids
      $oids = array_unique($this->_getSession('oids_'.$ar['itoid'], '_TZRSF'));

      if ($params['oid']
          && ($index = array_search($params['oid'], $oids)) !== false) {
        $do_select = false;
        $first = floor($index/$pagesize) * $pagesize;
      }
      // on ne poste pas le filtre (pagination)
      elseif($params && !$params['insidefilter']) {
        $do_select = false;
        $storedfilter = $this->_getSession('filter_'.$ar['itoid'], '_TZRSF');
        $first = $params['first'];
      } else {
        $do_select = true;
        $first = $params['first']? $params['first']:0;
      }
    }

    // calcul des filtres et récupération des valeurs
      $filterfields=null;
    if(!empty($ar['__resultonly']) && $ar['__resultonly']!=2){
      $searchon=array_keys($this->xset->desc);
    }elseif(!empty($ar['__filterfields'])) {
      $filterfields=$searchon=preg_split('/\|\|/', $ar['__filterfields'], 0, PREG_SPLIT_NO_EMPTY);
    }
    if(isset($searchon)){
      $filter = array('tplentry' => TZR_RETURN_DATA);
      if ($params['insidefilter'] || !empty($_REQUEST['initsearch'])) {
        foreach ($searchon as $field) {
          if (!empty($filterfields) && fieldIsInArray($field,$_REQUEST) || !fieldIsInArray($field,$ar) && fieldIsInArray($field,$_REQUEST)){
            $filter[$field] = $_REQUEST[$field];
            $filter[$field.'_empty'] = $_REQUEST[$field.'_empty'];
            $filter[$field.'_op']  = $_REQUEST[$field.'_op'];
            $filter[$field.'_HID'] = $_REQUEST[$field.'_HID'];
            $filter[$field.'_FMT'] = $_REQUEST[$field.'_FMT'];
            $filter[$field.'_PAR'] = $_REQUEST[$field.'_PAR'];
          }
        }
        $ar = array_merge($ar, $filter);
      }
      // pagination
      elseif ($storedfilter) {
        $filter = array_merge($filter, $storedfilter);
        $ar = array_merge($ar, $storedfilter);
      }
    }
    $ordered_filterfields = array();
    if($filterfields){
      // préparation des inputs
      $filter['selectedfields'] = $filterfields;
      $labelin = $ar['__filterlabelin'] == 1;
      // Si filtrage actif sur un champ, les valeurs/options de ce champ ne
      // sont pas impactées par son propre filtrage contrairement aux
      // filterfields voisins. Ex: Si on filtre selon un champ "type", on ne
      // filtre pas les valeurs/options du champ "type" avec "where
      // type='un_type'" dans la query sinon les options/valeurs de ce champ
      // seront restreinte aux valeurs transmises par le filtre lui-même
      foreach ($filterfields as $field) {
        if(isset($ar['options'][$field]['labelin']))
          $filter['options'][$field]['labelin'] = $ar['options'][$field]['labelin'];
        else
          $filter['options'][$field]['labelin'] = $labelin;
        $filter['options'][$field]['searchmode'] = 'simple';
        $actualquery = $ar;
        unset($actualquery[$field]);
        $actualquery[$field] = @$sectionFilter[$field];
        $actualquery[$field.'_op'] = @$sectionFilter[$field.'_op'];
        $actualquery['selectedfield'] = array($field);
        $actualquery['getselectonly'] = true;
        $actualquery['_options']['local'] = true;
        $filter['options'][$field]['select'] = $this->procQuery($actualquery);
        if ( is_array($ar['options'][$field]) ){
          $filter['options'][$field] = array_merge($filter['options'][$field], $ar['options'][$field]);
        }
      }
      $queryfields = $this->query($filter);
      // mise dans l'ordre du select
      $ordered_filterfields = array_flip($filterfields);
      foreach ($queryfields as $ofield) {
        if ($ofield->field)
          $ordered_filterfields[$ofield->field] = $ofield;
      }
    }

    // Mémorise les OID correspondant à la requête (permet de ne pas les recalculer lors d'un changement de page)
    if ($do_select) {
      $quickquery_value = $this->quickquery;
      $this->quickquery = false;
      $ar['getselectonly'] = true;
      if(isset($forceorder)) $ar['order'] = $forceorder;
      $select = $this->procQuery($ar);
      $this->quickquery = $quickquery_value;
      // calcul des oids
      $select = preg_replace('/^select .* from '.$this->table.'/', 'select '.$this->table.'.KOID from '.$this->table, $select);
      if ($rs = getDB()->select($select))
        $oids = array_unique($rs->fetchAll(\PDO::FETCH_COLUMN));
      // verification des droits sur les enregistrements
      $tmp = array('lines_oid' => $oids);
      $this->applyObjectsSec($tmp);
      $oids = [];
      foreach ( $tmp["lines_oid"] as $k => $oid){
        $rolist=static::getRoList();
        $intersect=array_intersect($rolist,array_keys($tmp["objects_sec"][$k]));
        if(!empty($intersect)){
          $oids[] = $oid;
        }
      }
      // sauvegarde des oids en session
      $this->_setSession('oids_'.$ar['itoid'], $oids, '_TZRSF');
    }
    $result = $this->xset->browse(array(
      'tplentry' => TZR_RETURN_DATA,
      'selectedfields' => array_merge($selectedfields, $groupfields),
      'fieldssec'=>$this->fieldssec,
      'nocount' => 1,
      'tlink' => 1,
      'pagesize' => $pagesize,
      'where' => 'KOID in ("'.implode('","', array_slice($oids, $first, $pagesize)).'")',
      'order' => 'field(koid, "'.implode('","', array_slice($oids, $first, $pagesize)).'")',
      'options' => $ar['options'],
      '_options' => array('genpublishtag' => false),
      '_mode' => @$ar['_mode']
    ));
    $result['_select'] = $select;
    // calcul de la pagination
    $result['pagesize'] = $pagesize;
    $result['first'] = $first;
    $result['last'] = $last = count($oids);
    if($last-$pagesize<=0) $result['firstlastpage']=0;
    elseif($last%$pagesize==0) $result['firstlastpage']=$pagesize*((int)($last/$pagesize)-1);
    else $result['firstlastpage']=$pagesize*(int)($last/$pagesize);
    $result['firstnext']=($first+$pagesize);
    $result['firstprev']=($first-$pagesize>=0?($first-$pagesize):$first);


    if ($pagesize>0 && $pagesize < $result['last']) {
      for ($p=0, $i=0; ($i<$result['last']); $p++, $i+=$pagesize) {
        $result['pages'][$p] = $i;
        if ($first == $i) $result['currentpageindex'] = $p;
      }
    }

    // asciify keywords (url detail)
    $l_count=count($result['lines_tlink']);
    for ($i=0; $i<$l_count; $i++)
      $result['lines_tlink'][$i] = rewriteToAscii(trim($result['lines_tlink'][$i]));

    // passer le filtre au résultat
    $result['filterfields'] = $ordered_filterfields;
    // trie
    $result['defaultOrder'] = implode(',', $orderfields);
    foreach ($sortfields as $field) {
      if ($ar['__sortAscDesc'] == 1) {
        $result['sortfields'][$field] = array(
          'label' => $this->xset->desc[$field]->label . ' +',
          'selected' => $field == $sqlorder ? 'selected="selected"' : '');
        $result['sortfields']["$field DESC"] = array(
          'label' => $this->xset->desc[$field]->label . ' -',
          'selected' => "$field DESC" == $sqlorder ? 'selected="selected"' : '');
      } else
        $result['sortfields'][$field] = array(
          'label' => $this->xset->desc[$field]->label,
          'selected' => $field == $sqlorder ? 'selected="selected"' : '');
    }
    if (count($pagesizes) > 1)
      $result['pagesizes'] = $pagesizes;

    // grouper
    if ($groupfields) {
      $result['groups'] = $this->group($groupfields, $selectedfields, $result);
      // supprimer les champs de groupage (dans le header)
      for ($i=count($result['header_fields'])-1; $i>=0; $i--) {
        if (in_array($result['header_fields'][$i]->field, $groupfields))
          unset($result['header_fields'][$i]);
      }
      $result['header_fields'] = array_values($result['header_fields']);
    }

    // stocker le filtre pour la pagination
    if($params['insidefilter']) {
      $filter['order'] = $ar['order'];
      unset($filter['selectedfields']);
      $this->_setSession('filter_'.$ar['itoid'], $filter, '_TZRSF');
    }

    return $result;
  }

  /// Initialise les champs sélectionnés par défaut
  public function &UIEdit_procQuery($ar=NULL) {
    if (empty($ar['__selectedfields'])) {
      $ar['__selectedfields'] = array('media');
    }
    return parent::UIEdit_procQuery($ar);
  }

  /// Mode recherche
  function &procQuery($ar) {
    $p=new \Seolan\Core\Param($ar,array('first'=>'0'), "all",
                              array('pagesize'=>array(FILTER_VALIDATE_INT,array()),
                                    'order'=>array(FILTER_CALLBACK,array('options'=>'containsNoSQLKeyword'))));
    $tplentry=$p->get('tplentry');
    $clearrequest=$p->get('clearrequest');
    $storedquery=$p->get('_storedquery');
    if(!empty($storedquery)) $clearrequest=1;
    $first=$p->get('first');
    $getselectonly=$p->get('getselectonly');
    $persistent=$p->get('_persistent');
    $pagesize=$p->get('pagesize');
    if(empty($pagesize)) $pagesize=$this->pagesize;
    $order=$this->checkOrderFields($p->get('order'));
    if($this->persistentquery && $persistent) clearSessionVar('filterquery'.$this->_moid);
    if($this->interactive) $this->_setSession('lastorder',$p->get('order'));
    $ar['pagesize']=$pagesize;
    $ar['table']=$this->table;
    $filter=$this->getFilter(true,$ar);
    if($filter===NULL) return NULL;
    $ar['_filter']=$filter;
    $ar['order']=$order;
    $ar['tplentry']=TZR_RETURN_DATA;

    // Patch de stockage des requêtes nommées
    if(\Seolan\Core\DataSource\DataSource::sourceExists('QUERIES')) {
      $storename=$p->get('_storename');
      $storegroup=$p->get('_storegroup');
      if(!empty($storename)) {
	$fields=$p->get('_FIELDS');
	$st=$this->xset->captureQuery($ar);
	$queries=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=QUERIES');
	$queries->procInput(array('title'=>$storename,'rtype'=>'table','modid'=>$this->_moid,'grp'=>$storegroup,
				  'query'=>addslashes(serialize($st))));
      }
    }

    if($this->isThereAQueryActive() && empty($clearrequest) && $this->interactive) {
      // recuperation de la requete active s'il y en a une
      $_storedquery=$this->_getSession('query');
      $ar=array_merge($_storedquery,$ar);
    }elseif(!empty($storedquery)){
      $_storedquery=$this->xset->prepareQuery($ar,$storedquery);
      $_storedquery=$this->xset->captureQuery($ar);
      $this->_setSession('query',$_storedquery);
      $ar=array_merge($_storedquery,$ar);
      $ar['_storedquery']='';
    } elseif($this->interactive && sessionActive()) {
      $fields=$p->get('_FIELDS');
      $_storedquery=$this->xset->captureQuery($ar);
      // Mode affinage
      if((int)$clearrequest==2){
	$_storedquery2=$this->_getSession('query');
	$_storedquery['_FIELDS']=array_merge($_storedquery2['_FIELDS'],$_storedquery['_FIELDS']);
	$_storedquery=array_merge($_storedquery2,$_storedquery);
      }
      $this->_setSession('query',$_storedquery);
      $ar=array_merge($_storedquery,$ar);
    }
    if(empty($ar['fmoid'])) $ar['fmoid']=$this->_moid;
    $ar['fieldssec']=$this->fieldssec;
    $ar['selectedfields']=$p->get('selectedfields');
    if(empty($ar['selectedfields'])) $ar['selectedfields']=$this->xset->getPublished();
    if (!empty($ar['selectedfields']) && is_array($ar['selectedfields'])){
      if(!in_array('media',$ar['selectedfields'])) $ar['selectedfields'][]='media';
      if(!in_array('PUBLISH',$ar['selectedfields'])) $ar['selectedfields'][]='PUBLISH';
    }
    $r=$this->xset->procQuery($ar);
    unset($ar['selectedfields']);
    if(!empty($getselectonly)) return $r;
    elseif($this->persistentquery && $persistent) setSessionVar('filterquery'.$this->_moid,$r['select']);
    $r['function']='procQuery';
    $this->applyObjectsSec($r);
    $this->browse_actions($r);

    if(\Seolan\Core\Shell::admini_mode() && $this->quickquery) {
      // Permet de surcharger le template d'affichage de la liste en back-office via les propriétés du module
      if(!empty($this->_templates) && !empty($this->btemplates)) {
	$this->_templates->display(array('oid'=>$this->btemplates,'_options'=>array('error'=>'return'),'tplentry'=>$tplentry.'t'));
      }
      $ar['tplentry']=TZR_RETURN_DATA;
      $r['_qq']=$this->quickquery($ar);
    }
    $r['_browsethumbsize'] = $this->browsethumbsize;
    return \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }

  /// Parcours le module pour la selection d'un fichier
  public function &procQueryFiles($ar) {
    $browse = $this->procQuery($ar);
    // si pas de titre aux images, ajout depuis le champ title
    // permet la récupération d'un titre dans "parcourir les modules"
    foreach ($browse['lines_oid'] as $i => $oid) {
      if (preg_match('/title=""/', $browse['lines_omedia'][$i]->html)) {
	$browse['lines_omedia'][$i]->html = preg_replace('/(title|alt)=""/', '$1="'.$browse['lines_otitle'][$i]->html.'"', $browse['lines_omedia'][$i]->html);
      }
    }
    return $browse;
  }

  /// Affiche toutes les infos d'un media
  function &display($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get('tplentry');
    $ar['table']=$this->table;
    $ar['tplentry']=TZR_RETURN_DATA;
    if(empty($ar['fmoid'])) $ar['fmoid']=$this->_moid;
    $ar['fieldssec']=$this->fieldssec;
    $r2=$this->xset->display($ar);

    // Choix du templates d'édition s'il existe
    if(!empty($this->_templates) && !empty($this->templates) && $tplentry!=TZR_RETURN_DATA) {
      $r=$this->_templates->display(array('oid'=>$this->templates,
					  '_options'=>array('error'=>'return'),
					  'tplentry'=>TZR_RETURN_DATA));
      \Seolan\Core\Shell::toScreen1($tplentry.'t',$r);
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$r2);
  }

  /// Supression de média
  function del($ar) {
    $p=new \Seolan\Core\Param($ar,null);
    $oids=$p->get('_selected');
    $selectedok=$p->get('_selectedok');
    $oids=array_keys($oids);
    if($selectedok!='ok' || empty($oids)) $oids=array($p->get('oid'));
    $ar['table']=$this->table;
    $ar['action']='OK';

    // corbeille
    if ($this->usetrash){
      $trashmoid = $p->get('_trashmoid','local');
      $trashuser = $p->get('_trashuser','local');
      $ar['_movetotrash'] = true;
      $ar['_trashmoid'] = $trashmoid ?? $this->_moid;
      $ar['_trashuser'] = $trashuser ??\Seolan\Core\User::get_current_user_uid();
      $ar['_trashdata'] = $p->get('_trashdata','local');
    }

    $this->xset->del($ar);
  }

  /// Insere une nouvelle fiche
  function &procInsert($ar) {
    $p=new \Seolan\Core\Param($ar,array('applyrules'=>true));
    $tplentry=$p->get('tplentry');
    $applyrules=$p->get('applyrules');
    if($this->procInsertCtrl($ar)===true) {
      $ar['table']=$this->table;
      $ar['tplentry']=TZR_RETURN_DATA;
      $ar['fieldssec']=$this->fieldssec;
      if(empty($ar['fmoid'])) $ar['fmoid']=$this->_moid;
      if(!$p->is_set('PUBLISH')) $ar['PUBLISH']=($this->defaultispublished?1:2);
      $r=$this->xset->procInput($ar);
      if($applyrules) \Seolan\Module\Workflow\Rule\Rule::applyRules($this,$r['oid']);

      // cas insertion depuis pop champs liens : template dédié
      if (!$p->is_set('_template')){
	if($this->savenext=='display' && $tplentry!=TZR_RETURN_DATA){
	  \Seolan\Core\Shell::setnext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true,true).'&moid='.$this->_moid.'&function=display&'.
								       'template=Module/Table.view.html&tplentry=br&oid='.$r['oid']);
	}elseif($p->is_set('save_and_edit')){
	  \Seolan\Core\Shell::setnext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true,true).'&moid='.$this->_moid.'&function=edit&'.
								       'template=Module/Table.edit.html&tplentry=br&oid='.$r['oid']);
	}
      }
    }elseif($tplentry!=TZR_RETURN_DATA){
      $options=$this->xset->prepareReInput($ar);
      $t='Module/Media.new.html';
      \Seolan\Core\Shell::changeTemplate($t);
      \Seolan\Core\Shell::setnext();
      $ar['options']=$options;
      $ar['tplentry']='br';
      $this->insert($ar);
      return;
    }
    return \Seolan\Core\Shell::toScreen1($tplentry, $r);
  }

  /// Enregistre les modifications d'une fiche
  function &procEdit($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('applyrules'=>true));
    if($p->is_set('procEditAllLang')) {
      $this->procEditAllLang($ar);
      $p=new \Seolan\Core\Param($ar,array('applyrules'=>true));
    }
    $ar['table']=$this->table;
    $ar['_track']=$this->trackchanges;
    $ar['_archive']=$this->archive;
    $ar['fieldssec']=$this->fieldssec;
    if(empty($ar['fmoid'])) $ar['fmoid']=$this->_moid;
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $editbatch=$p->get('editbatch');
    $mediadel=$p->get('media_del');
    $applyrules=$p->get('applyrules');

    // Edition d'une selection
    if(is_array($oid) && $editbatch) {
      $reeditone=$p->get('reeditone');
      unset($ar['reeditone']);
      foreach($oid as $oid1){
	$ar['oid']=$oid1;
	$this->procEdit($ar);
      }
      if($reeditone){
	$_storedquery=$this->xset->captureQuery(array('_options'=>array('local'=>true),'oids'=>$oid,'order'=>'KOID'));
	$this->_setSession('query',$_storedquery);
	$this->_setSession('lastorder','KOID');
	list($p,$n,$a)=$this->mkNavParms(array('_options'=>array('local'=>true)));
	\Seolan\Core\Shell::setNext('moid='.$this->_moid.'&function=edit&template=Module/Media.edit.html&tplentry=br&oid='.$a.'&usenav=1');
      }
      return;
    }
    if($this->procEditCtrl($ar)===true) {
      $ar['media_title'] = $p->get('media_title');
      $r=$this->xset->procEdit($ar);
      // Traitement des champs spécifiques

      if($applyrules) \Seolan\Module\Workflow\Rule\Rule::applyRules($this,$oid);
      if($this->savenext && $this->savenext=='display' && $tplentry!=TZR_RETURN_DATA){
	\Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).
			'&moid='.$this->_moid.'&function=display&template=Module/Table.view.html&tplentry=br&oid='.$oid);
      }
    }else{
      $options=$this->xset->prepareReEdit($ar);
      $t='Module/Media.edit.html';
      \Seolan\Core\Shell::changeTemplate($t);
      \Seolan\Core\Shell::setNext();
      $ar['options']=$options;
      $ar['tplentry']= 'br';
      $this->edit($ar);
      return;
    }

    return $r;
  }

  /// Retourne la liste des formats de téléchargement disponibles sous forme de tableau
  function getAllDLFormats(){
    $formats=array();
    // vignette
    $formats['ro1']['image/*']['425x282']=array('label'=>'425 x 282','command'=>'-resize 425x282>');

    // web
    $formats['ro2']=$formats['ro1'];
    $formats['ro2']['image/*']['850x565']=array('label'=>'850 x 565','command'=>'-resize 850x565>');

    // basse Def
    $formats['ro3']=$formats['ro2'];
    $formats['ro3']['image/*']['1691x1123']=array('label'=>'1691 x 1123','command'=>'-resize 1691x1123>');
    $formats['ro3']['image/*']['2360x1568']=array('label'=>'2360 x 1568','command'=>'-resize 2360x1568>');

    // haute def (tous)
    $formats['ro']=$formats['ro3'];
    $formats['ro']['image/*']['4288x2848']=array('label'=>'4288 x 2848','command'=>'-resize 4288x2848>');
    $formats['ro']['image/*']['original']=array('label'=>'Original','command'=>'');
    
    if ($this->advanced_dl) {
      $formats['ro']['image/*']['custom']=array('label'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','download_custom'),'command'=>'-resize %s>');
    }

    $formats['rw']=$formats['rwv']=$formats['admin']=$formats['ro'];

    // Format par defaut si aucun autre n'est défini, a laisser en derniere position
    $formats['ro1']['*']['original']=$formats['ro2']['*']['original']=$formats['ro3']['*']['original']=$formats['ro']['*']['original']=
      $formats['rw']['*']['original']=$formats['admin']['*']['original']=array('label'=>'Original','command'=>null);

    return $formats;
  }

  /// Retourne la liste des formats de téléchargement disponibles en fonction des droits de l'utilisateur sur le module
  function getSecDLFormats($objs=NULL){
    static $formats,$rw,$user,$lang_data,$colmodlvl,$file;

    // Cache en cas d'appel multiple de la méthode
    if(!$formats){
      $formats=$this->getAllDLFormats();
      $user=\Seolan\Core\User::get_user();
      $lang_data=\Seolan\Core\Shell::getLangData();
      $rw=$this->secure('',':rw');
      $colmodlvl=\Seolan\Core\User::secure8maxlevel($this->collectionmod,'',null,$lang_data);
    }

    // Si le parametre passé n'est pas un tableau d'oid ou un tableau de display
    if(!is_array($objs) || $objs['oid']) $objs=array($objs);

    $main=$files=array();
    foreach($objs as $i=>$d){
      if(!is_array($d)){
	$d=$this->xset->display(array('tplentry'=>TZR_RETURN_DATA,'oid'=>$d,'selectedfields'=>array('ref','collection','media')));
      }
      $oid=$d['oid'];

      if(!$this->collectionmod->object_sec || $rw || !$d['ocollection']->oidcollection){
	$ri=$colmodlvl;
      }else{
	// les droits sur l'image sont les droits les plus permissifs sur les collections auxquelles elle appartient
	$oidsrights=$GLOBALS['XUSER']->getObjectsAccess($this->collectionmod,$lang_data,$d['ocollection']->oidcollection);
	$lvl1=array_shift($oidsrights);
	while($lvl2=array_shift($oidsrights)){
	  $lvl1=array_merge($lvl1,$lvl2);
	}
	$ri=array_pop(array_keys($lvl1));
      }
      if($d['omedia']->filename || @$d['omedia']->isExternal) $file=$d['omedia'];
      if($file->isExternal){
        continue;
      }elseif($file->mime && $formats[$ri]){
	foreach($formats[$ri] as $fmtmime=>$fmts){
	  if(preg_match('#'.str_replace('*','.*',$fmtmime).'#',$file->mime)){
	    break;
	  }
	}
      }else{
	$fmts=$formats[$ri];
      }
      $file->formats=$fmts;
      $file->oid = $oid;
      $files[]=$file;
      if(!empty($main) && $main!==$fmts){
	$main=array('min'=>array('label'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','downloadminsize'),'command'=>''),
		    'max'=>array('label'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','downloadmaxsize'),'command'=>''));
	break;
      }else{
	$main=$fmts;
      }
    }

    return array('formats'=>$main,'files'=>$files);
  }

  /// Liste les formats possible pour le téléchargement. Si un seul format possible, on lance le téléchargement directement
  function chooseDownloadFormat($ar=NULL){
    $p=new \Seolan\Core\Param($ar,[]);
    $oid=$p->get('oid');
    if(!is_array($oid)) $oid=array($oid);
    $fmts=$this->getSecDLFormats($oid);
    $files = $fmts['files'];
    $fmts=$fmts['formats'];
    
    $forcePopup = false;

    // cas de fichier d'extension pas reconnue
    $mimeko = count($files)==1 && empty($files[0]->mime);
    
    if ((bool)$this->advanced_dl) {
      $mimeClasse = \Seolan\Library\MimeTypes::getInstance();
      if (count($files)===1 && $mimeClasse->isImage($files[0]->mime)) {
        $forcePopup = true;
      }
    }
    if(!$mimeko && (count($fmts)>1 || $forcePopup)){
      $content=$this->getTemplateContent('Module/Media.chooseDownloadFormat.html',
					 ['br'=>['fmts'=>$fmts,
						 'oid'=>$oid,
						 'advanced_dl' => (bool)$this->advanced_dl]
					 ]);
      echo json_encode(array('content'=>$content,'url'=>''));
      die();
    }elseif(count($fmts)==1 || $mimeko){
      $fmts=array_keys($fmts);
      echo json_encode(array('content'=>'','url'=>$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=downloadMedias&'.http_build_query(['oid'=>$oid]).'&dlfmt='.$fmts[0]));
      die();
    }
  }


  public function displayJSon($ar) {
    $json = parent::displayJSon($ar);
    if ($json['attributes']['media']->url) {
      $p = new \Seolan\Core\Param($ar);
      $oid = $p->get('oid');
      $formats = $this->getSecDLFormats($oid)['formats'];
      $urls = [];
      $baseurl = $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . 'moid=' . $this->_moid . '&function=downloadMedias&oid=' . $oid . '&dlfmt=';
      foreach ($formats as $geometry => $format) {
        $urls[] = $baseurl . $geometry;
      }
      $json['attributes']['media']->url = $urls;
    }
    return $json;
  }

  /// Telecharge des medias à un certain format
  function downloadMedias($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();

    $dlfmt=$p->get('dlfmt');
    $oid=$p->get('oid');
    $dir=TZR_TMP_DIR.uniqid('dl');
    $fmts=$this->getSecDLFormats($oid);
    $files=$fmts['files'];
    $fmts=$fmts['formats'];
    if(!$fmts[$dlfmt]){
      die('Unauthorized format');
    }
  
    $dlmime = '';
    $mode = [];
    if ($this->advanced_dl) {
      $dlmime = $p->get('dlmime');
      $mode = $p->get('mode');
      $width = (int)$p->get('width');
      $height = (int)$p->get('height');
    }
    
    if ($dlfmt === 'custom') {
      $fmts[$dlfmt]['command'] = sprintf($fmts[$dlfmt]['command'], $width.'x'.$height);
    }
    
    mkdir($dir);
    //compteur de fichier du meme nom
    $cnt = array();
    foreach($files as $file){
      $fileoriginalname = $this->getOriginalFilename($file);
      if(strpos($fileoriginalname,'.') === false){
	//pas d'extension on tente de l'ajouter
	$mime=$mimeClasse->getValidMime($file->mime,$dir.'/'.$fileoriginalname,NULL);
	$fileoriginalname .= '.'.$mimeClasse->get_extension($mime);
      }
  
      if ($this->advanced_dl && !empty($dlmime)) {
        $pos = strrpos($fileoriginalname, '.');
        if ($pos===false) {
          $fileoriginalname .= '.';
        } else {
          $fileoriginalname = substr($fileoriginalname, 0, $pos + 1);
        }
        
        $fileoriginalname .= $dlmime;
      }
      
      if(file_exists($dir.'/'.$fileoriginalname)){
	$cnt[$fileoriginalname]++;
	//on ajoute un increment pour éviter d'écraser
	$fileoriginalname = addFileSuffix($fileoriginalname,$cnt[$fileoriginalname]);
      }
      if($dlfmt=='min'){
	$fmt=array_shift($file->formats);
	$command=$fmt['command'];
      }elseif($dlfmt=='max'){
	$fmt=array_pop($file->formats);
	$command=$fmt['command'];
      }else{
	$command=$fmts[$dlfmt]['command'];
      }
  
      if ($this->advanced_dl) {
        if (is_array($mode) && in_array('crop', $mode)) {
          $command = str_replace('>', '^', $command);
          $command .= ' -gravity center -crop '.$width.'x'.$height.'+0+0';
        }
  
        if (is_array($mode) && in_array('extent', $mode)) {
          $command .= ' -gravity center -extent '.$width.'x'.$height;
        }
        
        if ($dlmime === 'jpg') {
          $command .= ' -background white -alpha remove';
        }
      }
      
      if($file->isImage){
      	if($command) {
	  $mediainfo=$this->xset->rDisplay($file->oid);
	  $command = str_replace('<ref>',$mediainfo['oref']->raw,$command);
	  $cmdline
	    = TZR_MOGRIFY_RESIZER
	    . ' '
	    . single_quote_into_sh_arg ($file->filename)
	    . ' '
	    . str_replace(['\|', "\'"], ['|', "'"], escapeshellcmd ($command))
	    . ' '
	    . single_quote_into_sh_arg ($dir . '/' . $fileoriginalname)
	    ;
	  system ($cmdline);
        }
	else copy($file->filename,$dir.'/'.$fileoriginalname);
      }else{
	copy($file->filename,$dir.'/'.$fileoriginalname);
      }
    }
    if(count($files)>1){
      exec('(cd '.$dir.'; zip -r '.$dir.'.zip .)2>&1 > '.TZR_TMP_DIR.'errorlog');
      $size=filesize($dir.'.zip');
      header('Content-type: application/zip');
      header('Content-disposition: attachment; filename="export_'.date('Y-m-d-H-i-s').'.zip"');
      header('Accept-Ranges: bytes');
      header('Content-Length: '.$size);
      @readfile($dir.'.zip');
      register_shutdown_function('unlink',$dir.'.zip');
    }else{
      $downloadedFile = $dir.'/'.$fileoriginalname;

      $size=filesize($downloadedFile);
      $mime=$mimeClasse->getValidMime(NULL,$downloadedFile,NULL);
      header('Content-type: '.$mime);
      header('Content-disposition: attachment; filename="'.$fileoriginalname.'"');
      header('Accept-Ranges: bytes');
      header('Content-Length: '.$size);
      @readfile($downloadedFile);
      register_shutdown_function('unlink',$downloadedFile);

    }
    register_shutdown_function('\Seolan\Library\Dir::unlink',$dir);
    die();
  }
    
  /// Forme l'originalfilename pour le download
  protected function getOriginalFilename($file){
    return rewriteToFilename($file->originalname);
  }
    
  /// Prepare l'impression d'une planche contact
  function prePrintContactSheet($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get('tplentry');
    $ar['table']=$this->table;
    $ar['_filter']=$this->getFilter(true,$ar);
    $ar['tplentry']=TZR_RETURN_DATA;
    $ar['selectedfields']='all';
    $ar['pagesize']=1;
    // Recherche des templates d'impression
    if(empty($this->_templates)) $this->_templates=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
    if(!empty($this->_templates)) {
      $q1=$this->_templates->select_query(array('cond'=>array('modid'=>array('=',$this->_moid),
							      'gtype'=>array('=','xmodmedia_sheet_print'))));
      $r=$this->_templates->browse(array('select'=>$q1,'pagesize'=>100,'tplentry'=>TZR_RETURN_DATA));
      \Seolan\Core\Shell::toScreen1($tplentry.'t',$r);
    }
    // Calcul du nombre d'enregistrements impactes (passage d'oid, browseSelection ou browse/query classique)
    $r=$this->xset->browse($ar);
    $r['_selected']=$p->get('_selected');
    if(is_array($r['_selected'])) {
      $r['record_count']=count($r['_selected']);
    }elseif($p->get('fromfunction')=='browseSelection') {
      $r['_selected']=$this->getSelectionFromSession();
      $r['record_count']=count($r['_selected']);
    }else{
      $context=$this->getContextQuery($ar,false);
      $ar['select']=$context['query'];
      $q=$this->xset->getSelectQuery($ar);
      $r['record_count']=getDB()->count($q[1],array(),true);
      $r['queryfields']=$context['all']['queryfields'];
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  /// Impression d'une planche contact
  function printContactSheet($ar=NULL){
    $contactsheet = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','contactsheet','text');
    $p=new \Seolan\Core\Param($ar,array('title'=>$contactsheet.' '.date('Y-m-d h:i:s')));
    $pdfname=$p->get('pdfname');
    $title=$p->get('title');
    if (empty($pdfname))
      $pdfname = $title.'.pdf';
    $tplentry=$p->get('tplentry');
    $email=$p->get('dest');
    $fmt=$p->get('fmt');
    $dpi=$p->get('dpi');
    $mmsize=$p->get('mmsize');
    $q=$this->getContextQuery($ar);
    $ar['selected']='0';
    $ar['pagesize']='100000';
    $ar['select']=$q;
    $ar['norow']=1;
    $ar['nodef']=1;
    $ar['selectedfield']=array('ref','title','media');
    $ar['_options']=['genpublishtag'=>false];
    $ar['tplentry']=TZR_RETURN_DATA;
    $ar['_format']='application/prince';
    $pdfname = rewriteToFilename($pdfname);
    if (substr(strtolower($pdfname), -4) != '.pdf')
      $pdfname = $pdfname.'.pdf';
    $oldinteractive=$this->interactive;
    $this->interactive=false;
    $imgsize=round($mmsize['w']/10*0.3937*$dpi).'x'.round($mmsize['h']/10*0.3937*$dpi).'%3E';

    $tpldata['']=['mmsize'=>$mmsize,
		  'imgsize'=>$imgsize,
		  'margin'=>$p->get('margin'),
		  'imargin'=>$p->get('imargin'),
		  'pformat'=>$p->get('pformat'),
		  'title'=>$p->get('title'),
		  'descr'=>$p->get('descr'),
		  ];

    $closeSession = false;
    // maintenir la session pour les fichiers sécurisés
    if ($this->xset->getField('media')->isSecure()){
      $ar['options']['media']['keep_session']=true;
      $closeSession  =true;
    }

    if(\Seolan\Core\Kernel::isAKoid($fmt)){
      // Impression via un template d'impressions
      $t=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$fmt);
      $dispfmt=$t->display(array('oid'=>$fmt,'tplentry'=>TZR_RETURN_DATA));
      $displayformats=explode(',',$dispfmt['odfmt']->raw);
      if(in_array('application/prince',$displayformats)) 
	$content=$this->_printGetContent($ar,$dispfmt['oprint']->filename,'browse',$tpldata);
    }else{
      $content=$this->_printGetContent($ar,static::$contactsheettemplate,'browse',$tpldata);
    }
    $this->interactive=$oldinteractive;

    // si fichiers securisés seulement 
    if ($closeSession)
      sessionClose();

    $tmpname=princeTidyXML2PDF(null, $content);
    if(!empty($email) && !empty($tmpname)) {
      $content=file_get_contents($tmpname);
      $this->sendMail2User($title,'',$email,NULL,false,NULL,$pdfname,$content,'application/pdf');
    }

    header('Content-type: application/pdf');
    header("Content-disposition: attachment; filename=\"$pdfname\"");
    $size=filesize($tmpname);
    header('Accept-Ranges: bytes');
    header('Content-Length: '.$size);
    readfile($tmpname);
    unlink($tmpname);
    exit(0);
  }

  /// Recupere le contenu pour une impression
  function &_printGetContent($ar,$filename,$f,$tpldata=array()){
    $p=new \Seolan\Core\Param($ar,NULL);
    $title=$p->get('title');
    $res=$this->$f($ar);
    $xt=new \Seolan\Core\Template('file:'.$filename);
    $labels=$GLOBALS['XSHELL']->labels->get_labels(array('selectors'=>array('global'),'local'=>true));
    $xt->set_glob(array('labels'=>&$labels));
    $r3=array();
    $tpldata['']['moid']=$this->_moid;
    $tpldata['param']=array('title'=>$title);
    $tpldata['br']=$res;
    $tpldata['imod']=\Seolan\Core\Shell::from_screen('imod');
    $content=$xt->parse($tpldata,$r3,NULL);
    return $content;
  }

  /// Prepare l'exportation des media avec leurs meta
  function preExportFiles($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('fname'=>'Export'));
    $tplentry=$p->get('tplentry');
    $q=$this->getContextQuery($ar);
    $ar['selected']='0';
    $ar['pagesize']='100000';
    $ar['select']=$q;
    $ar['norow']=1;
    $ar['nodef']=1;
    $ar['selectedfield']=array('ref','title','media');
    $ar['_options']=array('genpublishtag'=>false);
    $ar['tplentry']=TZR_RETURN_DATA;
    $oldinteractive=$this->interactive;
    $this->interactive=false;
    $br=$this->browse($ar);
    $this->interactive=$oldinteractive;
    $size=0;
    $br['exportoid']=array();
    $br['exportref']=array();
    foreach($br['lines_oid'] as $i=>$oid){
      if($this->secure($oid, ':sro')) {
	$size+=filesize($br['lines_omedia'][$i]->filename);
	$br['exportoid'][]=$oid;
	$br['exportref'][]=$br['lines_oref'][$i]->text;
      }
    }
    $br['size']=$size;
    $br['exportcount']=count($br['exportoid']);
    return \Seolan\Core\Shell::toScreen1($tplentry,$br);
  }

  /// Exporte les medias avec leurs metas
  function exportFiles($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('fname'=>'Export','zip'=>true));
    $fname=$p->get('fname');
    $fname=$zipname=$p->get('fname');
    $fmt=$p->get('fmt');
    $zip=$p->get('zip');
    $tplentry=$p->get('tplentry');
    $q=$p->get('select','local');
    if(empty($q)) $q=$this->getContextQuery($ar);
    if($fmt!='ftp'){
      $ar['selected']='0';
      $ar['pagesize']='100000';
      $ar['select']=$q;
      $ar['norow']=1;
      $ar['nodef']=1;
      $ar['selectedfield']=array('KOID','media');
      $ar['_options']=array('genpublishtag'=>false);
      $ar['tplentry']=TZR_RETURN_DATA;
      $oldinteractive=$this->interactive;
      $this->interactive=false;
      $br=$this->browse($ar);
      $dir=TZR_TMP_DIR.uniqid('exportmedia');
      mkdir($dir);
      foreach($br['lines_oid'] as $i=>$oid){
	if($this->secure($oid, ':sro')) {
	  $media=$br['lines_omedia'][$i];
	  $fname=$media->originalname;
	  // Pour éviter qu'un fichier en ecrase un autre s'ils ont le même nom
	  if(file_exists($dir.'/'.$fname)){
	    $fname=substr(strrchr($oid,':'),1).'_'.$fname;
	  }
	  copy($media->filename,$dir.'/'.$fname);
	}
      }
      $this->interactive=$oldinteractive;
    }
    // Mode : direct download, envoi mail, envoi ftp en asynchrone
    if($fmt=='dl'){
      exec('(cd '.$dir.'; zip -r '.$dir.'.zip .)2>&1 > '.TZR_TMP_DIR.'errorlog');
      $size=filesize($dir.'.zip');
      header('Content-type: application/zip');
      header('Content-disposition: attachment; filename="'.str_replace(' ','_',rewriteToFilename($zipname)).'.zip"');
      header('Accept-Ranges: bytes');
      header('Content-Length: '.$size);
      @readfile($dir.'.zip');
      \Seolan\Library\Dir::unlink($dir);
      unlink($dir.'.zip');
      die();
    }elseif($fmt=='ftp'){
      $scheduler=new \Seolan\Module\Scheduler\Scheduler();
      $o=array();
      $o['function']='exportFilesBatch';
      $o['uid']=getSessionVar('UID');
      $o['ftpserver']=$p->get('ftpserver');
      $o['ftplogin']=$p->get('ftplogin');
      $o['ftppassword']=$p->get('ftppassword');
      $o['ftpdir']=$p->get('ftpdir');
      $o['zip']=$p->get('zip');
      $o['select']=$q;
      $o['fname']=$fname.'.zip';
      $scheduler->createJob($this->_moid,date('Y-m-d H:i:s'),'Export media',$o,'');
      $message=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','exportftpok');
      \Seolan\Core\Shell::toScreen2('','message',$message);
    }elseif($fmt=='ftpbatch'){
      $ftpserver=$ar['ftpserver'];
      $ftpserver=str_replace('ftp://','',$ftpserver);
      if(substr($ftpserver,-1)=='/') $ftpserver=substr($ftpserver,0,-1);
      $ftplogin=$ar['ftplogin'];
      $ftppass=$ar['ftppassword'];
      $ftpdir=$ar['ftpdir'];
      $reportto=$ar['reportto'];
      if(empty($reportto)) $reportto=$GLOBALS['XUSER']->email();
      $zipname=basename($dir.'.zip');
      $msg=$msglist='';
      $files=\Seolan\Library\Dir::scan($dir);
      if(empty($files)) return;
      $ftp=ftp_connect($ftpserver);
      if(!$ftp) $msg='Unable to connect to '.$ftpserver;
      else{
	$ftpl=ftp_login($ftp,$ftplogin,$ftppass);
	if(!$ftpl) $msg='Error logging into '.$ftpserver;
	else{
	  if(!empty($ftpdir) && !ftp_chdir($ftp,$ftpdir)) $msg='Unable to change dir to '.$ftpdir;
	  else{
	    if($zip){
	      exec('(cd '.$dir.'; zip -r '.$dir.'.zip .)2>&1 > '.TZR_TMP_DIR.'errorlog');
	      $ftpp=ftp_put($ftp,$zipname,$dir.'.zip',FTP_BINARY);
	      if(!$ftpp) $msg='Unable to send file on the ftp server '.$ftpserver;
	    }else{
	      foreach($files as $file){
		$ftpp=ftp_put($ftp,basename($file),$file,FTP_BINARY);
		if(!$ftpp) $msglist="\n".basename($file).' => transfert error';
		else $msglist="\n".basename($file).' => ok';
	      }
	    }
	  }
	}
	ftp_close($ftp);
      }
      if($reportto){
	if(!empty($msg)) $msg=sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','exportftpbatcherror'),$msg);
	elseif($zip) $msg=sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','exportftpbatchok'),$zipname);
	else $msg=sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','exportftpbatchok'),$ftpdir).$msglist;
	$this->sendMail2User(sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','exportftpbatchsub'),$this->modulename),$msg,$reportto);
      }
      \Seolan\Library\Dir::unlink($dir);
      @unlink($dir.'.zip');
    }
  }

  /// Exporte les media avec leurs meta via tache planifiée
  function exportFilesBatch(\Seolan\Module\Scheduler\Scheduler &$scheduler,&$o,&$more){
    $ar=(array)$more;
    $ar['fmt']='ftpbatch';
    $this->exportFiles($ar);
  }

  /// Prépare une importation à la volée
  function importOnFly($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->imports);
    $r = $xds->input(array('tplentry'=>TZR_RETURN_DATA));
    $o=new \Seolan\Field\File\File((object)(array('FIELD'=>'medias','FTYPE'=>'\Seolan\Field\Folder\Folder','MULTIVALUED'=>true)));
    $o->table=$this->table;
    $r['omedias']=$o->edit(($value=null), ($options=null));
    $r['hashd']=false;
    $r['ocollection']=$this->xset->desc['collection']->edit(($value=null), ($options=null));
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  /// Importe des medias à la volée
  function procImportOnFly($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $hd=$p->get('hdmedia');
    $uniqname=$p->get('uniqname');
    $publishauto=$p->get('publishauto');
    $uniqid=$p->get('medias_HID');
    $uniqid=$uniqid['id'];
    $tmpdir=TZR_TMP_DIR.'upload'.$uniqid.'/'.$this->table.'/medias';
    $flist=unserialize(file_get_contents($tmpdir.'/'.$uniqid.'_catalog.txt'));
    $ari=array('tplentry'=>TZR_RETURN_DATA,'_options'=>array('local'=>true),'_allfields'=>true,'collection'=>$p->get('collection'));
    if($publishauto) $ari['PUBLISH']=1;
    foreach($flist['tmp_name'] as $i=>$fname){
      $infos=pathinfo($flist['name'][$i]);
      $ar2=$ari;
      $ar2['ref']=$infos['filename'];
      $ar2['media']=array('tmp_name'=>$fname,'name'=>$flist['name'][$i]);
      $ret=$this->importAMedia($ar2,array('uniq'=>$uniqname));
    }
  }
  /// Action sur liste
  function browse_actions(&$r, $assubmodule=false, $ar=null) {
    return parent::browse_actions($r, $assubmodule, $ar);
  }
  /// Ajoute les actions du browse à une ligne donnée
  function browseActionsForLine(&$r,&$i,&$oid,&$oidlvl,&$noeditoids){
    if (($r['translation_mode'] == 1)){
      if ($r['lines_translation_ok'][$i]){
        $this->browseActionInfo($r,$i,$oid,$oidlvl);
	if (!$r['lines_omedia'][$i]->fielddef->isEmpty($r['lines_omedia'][$i]))
          $this->browseActionDownload($r,$i,$oid,$oidlvl);
        $this->browseActionEdit($r,$i,$oid,$oidlvl);
        $this->browseActionDel($r,$i,$oid,$oidlvl);
      } else {
        $this->browseActionEditInsert($r,$i,$oid,$oidlvl, (!$r['lines_translation_ok'][$i]));
      }
    } else {
      $this->browseActionInfo($r,$i,$oid,$oidlvl);
      if (!$r['lines_omedia'][$i]->fielddef->isEmpty($r['lines_omedia'][$i]))
	$this->browseActionDownload($r,$i,$oid,$oidlvl);
      // actions standard fiche
      $this->browseActionEdit($r,$i,$oid,$oidlvl);
      $this->browseActionDel($r,$i,$oid,$oidlvl);
    }
  }
  
  /// Retourne les infos de l'action info du browse
  function browseActionInfo(&$r,&$i,&$oid,&$oidlvl,$usersel=false){
    $this->browseActionForLine('info',$r,$i,$oid,$oidlvl,$usersel);
  }
  function browseActionInfoText($linecontext=null){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','informations','text');
  }
  function browseActionInfoIco($linecontext=null){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','informations');
  }
  function browseActionInfoLvl($linecontext=null){
    return $this->secGroups('displayInfos');
  }
  function browseActionInfoHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
    $infosUrl = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=displayInfos&template=Module/Media.viewInfos.html&skip=1&_raw=2&_ajax=1';
    $infosUrl.=isset($linecontext['browse']['urlparms'])?'&'.$linecontext['browse']['urlparms']:'';
    return "class=\"mediainfos\" data-infosurl=\"$infosUrl\"";
  }
  /**
   * ce ne sont que des scripts
   */
  function browseActionInfoUrl($usersel,$linecontext=null){
    return '';
  }
  /// Retourne les infos de l'action download du browse
  function browseActionDownload(&$r,&$i,&$oid,&$oidlvl,$usersel=false){
    $external = isset($r['lines_omedia'][$i]) ? @$r['lines_omedia'][$i]->isExternal : @$r['lines'][$i]['omedia']->isExternal;
    if(!$external) $this->browseActionForLine('download',$r,$i,$oid,$oidlvl,$usersel);
  }
  function browseActionDownloadText($linecontext=null){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','download','text');
  }
  function browseActionDownloadIco($linecontext=null){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','download');
  }
  function browseActionDownloadLvl($linecontext=null){
    return $this->secGroups('chooseDownloadFormat');
  }
  function browseActionDownloadHtmlAttributes(&$url,&$text,&$icon,$linecontext=null){
    $lang_data = \Seolan\Core\Shell::getLangData();
    return 'onclick="return TZR.Media.downloadMedia('.$this->_moid.',\'<oid>\', \''.$lang_data.'\');"';
  }
  function browseActionDownloadUrl($usersel){
    return '#';
  }
  /// Retourne les infos de l'action edit du browse
  function browseActionEditUrl($usersel, $linecontext=null){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().($usersel?'&_bdxnewstack=1':'').'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=edit&template=Module/Media.edit.html';
  }

  /// Retourne le filtre du module
  public function getFilter($instanciate=true,$ar=array()) {
    $cfilter=$this->getCollectionFilter();
    if($cfilter===NULL) return NULL;

    $filter=parent::getFilter($instanciate,$ar);

    if(!empty($cfilter)){
      $q=getSessionVar('filterquery'.$this->_moid);
      if(empty($q)){
	if(empty($filter)) $filter='('.$cfilter.')';
	else $filter=$filter.' AND ('.$cfilter.')';
      }
    }
    if(empty($filter)) return '';
    else {
      return '('.$filter.')';
    }
  }

  /// Parcours la sélection utilisateur pour ce module
  function browseUserSelection($ar=NULL){
    $result=parent::browseUserSelection($ar);
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $result['_template']='Module/Media.browseSelection.html';
    $result['_browsethumbsize'] = $this->browsethumbsize;
    return \Seolan\Core\Shell::toScreen1($tplentry,$result);
  }

  /// Sous fonctions de parcours de la selection
  function &_browseUserSelection($oid,&$data){
    if(!$this->collectionmod->object_sec || $this->secure($oid,'display')){
      $selectedfields = $this->xset->getFieldsList(NULL,true);
      if(!in_array('media',$selectedfields )) $selectedfields []='media';
      return $this->xset->rDisplay($oid,array(),false,'','',array('selectedfields'=>$selectedfields));
    }
    return false;
  }

  /// Ajoute les actions de la selection à une ligne donnée
  /// La seule acion est : editer, les autres sont gérés en "selected"
  function browseActionsUserSelectionForLine(&$r,&$i,&$oid,&$oidlvl){
    //$this->browseActionInfo($r,$i,$oid,$oidlvl,true);
    //$this->browseActionDownload($r,$i,$oid,$oidlvl,true);
    $this->browseActionEdit($r,$i,$oid,$oidlvl,true);
  }

  /// Ajoute les droits sur les objets de la selection
  function applyObjectsSecUserSelection(&$r){
    $this->applyObjectsSec($r);
  }

  /// Définit si la sécurité sur les objets est activé
  public function objectSecurityEnabled(){
    return $this->collectionmod->objectSecurityEnabled();
  }

  /// Liste des fonctions utilisable sur la selection du module
  function userSelectionActions(){
    // actions sur les fiches : procQuery(view), editSelection(editselection), del(del), sendACopyTo(sendacopy), export(exportSelection)
    $actions = parent::userSelectionActions();
    unset($actions['exportSelection']);
    // on met les bons gabarits
    if (isset($actions['view'])){
      $viewtxt=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','view','text');
      $actions['view']='<a href="#" onclick="TZR.SELECTION.viewselected(\''.$this->_moid.'\', \'procQuery\', \'Module/Media.browse.html\'); return false;">'.$viewtxt.'</a>';
    }
    // actions spécifiques
    // à voir c'était du nyro, on reprend pas pour le moment
    //$actions['diaporama']='<a href="#" onclick="jQuery(\'#imagelistsel'.$this->_moid.' li div.imagelist_img a.nyro:first\').nm().nmCall();return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','diaporama','text').'</a>';

    // passé sur Module/Table, à voir cependant (redmine)
    if($this->secure('','exportFiles')) {
      $actions['export']='<a href="#" onclick="TZR.SELECTION.exportSelection(\''.$this->_moid.'\'); return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','export','text').'</a>';
    }
    if($this->secure('','printContactSheet')) {
      $actions['printcontactsheet']='<a href="#" onclick="TZR.SELECTION.printsheetselected('.$this->_moid.'); return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Media_Media','contactsheet','text').'</a>';
    }
    if($this->secure('','chooseDownloadFormat')) {
      $actions['download']='<a href="#" onclick="TZR.SELECTION.downloadselected'.$this->_moid.'(); return false;">'.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','download','text').'</a>';
    }
    return $actions;
  }

  /// Rend l'accessibilite du module avec l'oid donné
  function secure($oid, string $func, $user=NULL, $lang=TZR_DEFAULT_LANG) {
    if(!\Seolan\Core\Shell::isRoot()){
      // On va verifier les droits sur les collections si activé
      if ($this->collectionmod->object_sec && !empty($oid)) {
	$sec=parent::secure($oid,$func,$user,$lang);
	if($sec) return $sec;
	$levels=$this->getObjectCollectionsAccess($oid,$user,$lang);
	$secs=$this->secGroups($func,NULL);
	if(!is_array($secs)) return false;
	if(array_intersect($secs,array_keys($levels))) return true;
	else return false;
      }
    }
    return parent::secure($oid,$func,$user,$lang);
  }

  /// Calcule les droits sur les collections d'un objet (ors peut etre un oid ou l'ors correspondant à un oid)
  /// L'acces sur l'oid ainsi que les acces sur les collections visitées sont mis en cache
  function getObjectCollectionsAccess($ors,&$user=NULL,$lang=NULL){
    $oid=$ors;
    if(is_array($oid)) $oid=$ors['KOID'];
    // Si les parametres ne sont pas ceux du contexte courant, on ne doit pas se servir du cache
    if(!empty($user) && $user!==$GLOBALS['XUSER'] || !empty($lang) && $lang!=\Seolan\Core\Shell::getLangData()){
      $cache=false;
      $oldcache=$this->cache->colsrights;
      $this->cache->colsrights=array();
    }else{
      $cache=true;
      if(array_key_exists($oid,$this->cache->oidsrights)) return $this->cache->oidsrights[$oid];
    }
    // Recupere le contexte si pas passé en parametre
    if(empty($user)) $user=$GLOBALS['XUSER'];
    if(empty($lang)) $lang=\Seolan\Core\Shell::getLangData();
    // Recupere l'ors si besoin
    if(!is_array($ors)){
      $lang_data = $this->xset->getTranslatable() ? \Seolan\Core\Shell::getLangData() : TZR_DEFAULT_LANG;
      $sql = 'select collection from '.$this->table.' where KOID=? AND LANG = ?';
      $koid = $ors;
      $ors=getDB()->fetchRow($sql, [$koid, $lang]);
      //Si l'enregistrement n'existe pas dans la langue et que le champ collection n'est pas traduisible
      // on va récupérer les collections dans la langue de base.
      if ($ors === false && $this->xset->desc['collection']->translatable == 0) {
        $ors=getDB()->fetchRow($sql, [$koid, TZR_DEFAULT_LANG]);
      }
    }
    // Recupere le niveau max selon les collections de l'objet
    $maxlevel=array();
    $cols=explode('||',$ors['collection']);
    foreach($cols as $coid){
      if(empty($coid)) continue;
      if(empty($this->cache->colsrights[$coid])){
	$right=$user->getObjectAccess($this->collectionmod,$lang,$coid);
	$this->cache->colsrights[$coid]=array_flip($right[0]);
      }
      if(empty($maxlevel) || count($maxlevel)<count($this->cache->colsrights[$coid])) $maxlevel=$this->cache->colsrights[$coid];
    }
    // Mise en cache ou restitution du cache d'origine
    if(!$cache) $this->cache->colsrights=$oldcache;
    else $this->cache->oidsrights[$oid]=$maxlevel;
    return $maxlevel;
  }

  /// Recupere le filtre à appliquer à une requete en fonction des collections
  /// Le filtre ainsi que les acces de toutes les collections sont mis en cache
  function getCollectionFilter(){
    $filter=\Seolan\Core\User::getDbCacheData('collectionFilter'.$this->_moid);
    if($filter===NULL){
      $filter='';
      // Si les droits sont géré sur les collections et que le droit de lecture minimum n'est pas actif
      if($this->collectionmod->object_sec && !$this->secure('',':ro')){
	$lang_data=\Seolan\Core\Shell::getLangData();
	$oids=getDB()->fetchCol('select DISTINCT KOID from '.$this->collectionmod->table);
	$oidsrights=$GLOBALS['XUSER']->getObjectsAccess($this->collectionmod,$lang_data,$oids);
	if(empty($oidsrights)) return NULL;
	$this->cache->colsrights=$filter=array();
	$colsok=0;
	$rolist=static::secRoList();
	foreach($oidsrights as $i=>$rights) {
	  $this->cache->colsrights[$oids[$i]]=$rights;
	  // On teste que le niveau de lecture le plus bas est autorisé
	  $intersect=array_intersect($rolist, array_flip($rights));
	  if(!empty($intersect)) {
	    $filter[]='(collection="'.$oids[$i].'" or INSTR(collection,"||'.$oids[$i].'||"))';
	    $colsok++;
	  }
	}
	if($colsok==0) $filter=NULL;
	if(!empty($filter) && $colsok != count($oids)) $filter='('.implode('OR',$filter).')';
	else $filter='(collection is not null or collection!="")';
      }
      \Seolan\Core\User::setDbCacheData('collectionFilter'.$this->_moid,$filter);
    }
    return $filter;
  }

  /// Applique la sécurité en fonction des collections sur un browse/procQuery
  function applyObjectsSec(&$r){
    $lang_data = $this->xset->getTranslatable() ? \Seolan\Core\Shell::getLangData() : TZR_DEFAULT_LANG;
    // Recupere pour chaque objet les droits max en fct de ses collections
    if($this->collectionmod->object_sec && !\Seolan\Core\Shell::isRoot()){
      $colsmaxlevel=array();
      $oidlist='"'.implode('","',$r['lines_oid']).'"';
      $rs=getDB()->select('select KOID,collection from '.$this->table.' where KOID in('.$oidlist.') and LANG="'.TZR_DEFAULT_LANG.'" order by field(KOID,'.$oidlist.')');
      $i = 0;
      while($rs && $ors=$rs->fetch()){
        $colsmaxlevel[$i++]=$this->getObjectCollectionsAccess($ors, $GLOBALS['XUSER'], $lang_data);
      }
    }
    if($this->collectionmod->object_sec && !\Seolan\Core\Shell::isRoot()){
      $right=$this->secList();
      $right=array_flip($right);
      foreach($r['lines_oid'] as $i=>$oid) $r['objects_sec'][$i]=array_intersect_assoc($right,$colsmaxlevel[$i]);
    }else if (count($r['lines_oid'])>0){ // en recherche,
      $right=$GLOBALS['XUSER']->getObjectAccess($this, $lang_data, $r['lines_oid'][0]);
      $right=array_flip($right[0]);
      foreach($r['lines_oid'] as $i=>$oid) $r['objects_sec'][$i]=$right;
    }
  }

  /***********************/
  /* Gestion des imports */
  /***********************/
  /// Parcours les procédures d'import
  public function importBrowse($ar=NULL){
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->imports);
    $ar['order']='title';
    $ar['pagesize'] = -1;
    return $xds->browse($ar);
  }
  /// Affiche une procédure
  public function importDisplay($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->imports);
    $ar['tplentry']=TZR_RETURN_DATA;
    $xds->desc['more']->sys=true;
    $ar['oid'] = $p->get('oidimport');
    $ret=$xds->display($ar);
    $legend=\Seolan\Core\System::xml2array($ret['omore']->raw);
    $legend=$legend['legend'];
    $values=$options=array();
    parse_str($legend,$values);
    foreach($this->xset->desc as $fn=>&$f){
      if($values[$fn] || $values[$fn.'_HID']){
	$v=$values[$fn];
	$foo=$f->post_edit($v,$values);
	$options[$fn]['value']=(is_array($foo->raw)?implode('||',$foo->raw):$foo->raw);
	if($foo->raw){
	  $ret['__fields'][]=$f->display($foo->raw);
	}
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /// Prepare l'ajout d'une nouvelle procédure
  public function importInput($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->imports);
    $xds->desc['more']->sys=true;
    $ret=$xds->input($ar);
    $editParams = $p->get('editParams');
    $editParams['tplentry'] = TZR_RETURN_DATA;
    $ret['__edit']=$this->insert($editParams);
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /// Ajoute une nouvelle procédure
  public function importProcInput($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $legend=$p->get('legend');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->imports);
    \Seolan\Core\System::array2xml(array('legend'=>$legend),$ar['more']);
    if(!$this->xset->fieldExists('mediahd')){
      $ar['mediahddir']='';
    }
    $ret=$xds->procInput($ar);
    $this->FTPRecursiveMkdir($ret['inputs']['ftph']->raw,$ret['inputs']['ftpu']->raw,$ret['inputs']['ftpp']->raw,array($ret['inputs']['mediadir']->raw,$ret['inputs']['mediahddir']->raw));
    return $ret;
  }
  /// Prepare l'édition d'une procédure
  public function importEdit($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->imports);
    $xds->desc['more']->sys=true;
    $ar['oid'] = $p->get('oidimport');
    $ret=$xds->edit($ar);
    $legend=\Seolan\Core\System::xml2array($ret['omore']->raw);
    $legend=$legend['legend'];
    $values=$options=array();
    parse_str($legend,$values);
    foreach($this->xset->desc as $fn=>&$f){
      if($values[$fn] || $values[$fn.'_HID']){
	$v=$values[$fn];
	$foo=$f->post_edit($v,$values);
	$options[$fn]['value']=(is_array($foo->raw)?implode('||',$foo->raw):$foo->raw);
      }
    }
    $ret['__edit']=$this->insert(array('tplentry'=>TZR_RETURN_DATA,'options'=>$options));
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /// Edite une procédure
  public function importProcEdit($ar=NULL){
    $p=new \Seolan\Core\Param($ar,[]);
    $legend=$p->get('legend');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->imports);
    \Seolan\Core\System::array2xml(array('legend'=>$legend),$ar['more']);
    if(!$this->xset->fieldExists('mediahd')){
      $ar['mediahddir']='';
    }
    $ar['oid'] = $p->get('oidimport');
    return $xds->procEdit($ar);
  }
  /// Efface une procédure
  public function importDel($ar=NULL){
    $p=new \Seolan\Core\Param($ar=NULL);
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->imports);
    $ar['oid'] = $p->get('oidimport');
    return $xds->del($ar);
  }
  /// Execute les procédures
  public function runImports($ar=NULL){
    if(!$lckglobal=\Seolan\Library\Lock::getLock('runimports')) return;
    \Seolan\Core\Logs::debug('\Seolan\Module\Media\Media::runImports start');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->imports);
    $ar['tplentry']=TZR_RETURN_DATA;
    if($xds->fieldExists('dateb') && $xds->fieldExists('datee')) {
      $ar['_filter']='PUBLISH=1 AND (dateb < NOW() OR dateb="0000-00-00") AND (datee > NOW() OR dateb="0000-00-00")';
    }else{
      $ar['_filter']='PUBLISH=1';
    }
    $ar['selectedfields']='all';
    $ar['pagesize'] = -1;

    $br=$xds->browse($ar);
    $tmpdir=TZR_TMP_DIR.uniqid('importmedia').'/';

    @mkdir($tmpdir);
    foreach($br['lines_oid'] as $i=>$oid){
      // ?? a verifier if(empty($br['lines_omediadir'][$i]->raw) && empty($br['lines_omediahddir'][$i]->raw)) continue;
      if(empty($br['lines_omediadir'][$i]->raw)) continue;
      $lck = \Seolan\Library\Lock::getLock('mediaImport'.$oid);
      if (!$lck) continue;
      if(!empty($br['lines_oOWN'][$i]->raw)){
	$olduser=$GLOBALS['XUSER'];
	$GLOBALS['XUSER']=new \Seolan\Core\User(array("UID"=>$br['lines_oOWN'][$i]->raw));
	setSessionVar("UID",$br['lines_oOWN'][$i]->raw);
      }
      $message='';
      $tot=$add=$update=0;
      // Recupere tout les parametres
      $ari=array('tplentry'=>TZR_RETURN_DATA,'_options'=>array('local'=>true),'_allfields'=>true,'options'=>array());
      $more=\Seolan\Core\System::xml2array($br['lines_omore'][$i]->raw);
      if(!empty($more['values'])){
	foreach($more['values'] as $f=>$v) $ari[$f]=$v;
      }elseif($more['legend']){
	$foo=array();
	parse_str($more['legend'],$foo);
	foreach($foo as $f=>$v) $ari[$f]=$v;
      }
      if($br['lines_opublishauto'][$i]->raw==1) $ari['PUBLISH']=1;
      $ftph=str_replace('ftp://','',$br['lines_oftph'][$i]->raw);
      if(substr($ftph,-1)=='/') $ftph=substr($ftph,0,-1);
      $dir=$br['lines_omediadir'][$i]->raw;
      if(!empty($dir) && substr($dir,-1)!='/') $dir.='/';
      $dirhd='';

      // Connexion au ftp et récupération des media et mediahd
      $ftp=ftp_connect($ftph);
      if(!$ftp){
        \Seolan\Core\Logs::debug('\Seolan\Module\Media\Media::runImport Bad FTP Hostname');
	if(!empty($br['lines_oemail'][$i]->raw)){
        $this->sendMail2User($this->getLabel().' : import '.$br['lines_otitle'][$i]->raw,'Bad FTP Hostname',
			       $br['lines_oemail'][$i]->raw);
	}
	continue;
      }
      $login=ftp_login($ftp,$br['lines_oftpu'][$i]->raw,$br['lines_oftpp'][$i]->raw);
      if(!$login){
        \Seolan\Core\Logs::debug('\Seolan\Module\Media\Media::runImport Bad FTP User/FTP Pass');
	if(!empty($br['lines_oemail'][$i]->raw)){
        $this->sendMail2User($this->getLabel().' : import '.$br['lines_otitle'][$i]->raw,'Bad FTP User/FTP Pass',
			       $br['lines_oemail'][$i]->raw);
	}
	continue;
      }
      $dirhome=ftp_pwd($ftp);
      // Teste l'acces au repertoire LD
      if(!empty($dir) && !ftp_chdir($ftp,$dir)){
        \Seolan\Core\Logs::debug("\Seolan\Module\Media\Media::runImport Directory $dir not exist");
	if(!empty($br['lines_oemail'][$i]->raw)){
        $this->sendMail2User($this->getLabel().' : import '.$br['lines_otitle'][$i]->raw, "Directory $dir not exist",
			       $br['lines_oemail'][$i]->raw);
	}
	continue;
      }
      ftp_chdir($ftp,$dirhome);
      // Teste l'acces au repertoire HD
      if(!empty($dirhd) && !ftp_chdir($ftp,$dirhd)){
        \Seolan\Core\Logs::debug("\Seolan\Module\Media\Media::runImport Directory $dirhd HD not exist");
	if(!empty($br['lines_oemail'][$i]->raw)){
        $this->sendMail2User($this->getLabel().' : import '.$br['lines_otitle'][$i]->raw, "Directory $dirhd HD not exist ",
			       $br['lines_oemail'][$i]->raw);
	}
	continue;
      }
      ftp_chdir($ftp,$dirhome);
      $files=array();
      // Recupere la liste des fichiers LD
      if(ftp_chdir($ftp,$dir)) {
	$files=$this->FTPRecursiveList($ftp,'./');
      }

      // Recupere la liste des fichiers HD
      ftp_chdir($ftp,$dirhome);
      $fileshd=array();
      if(!empty($dirhd) && ftp_chdir($ftp,$dirhd)) {
	$fileshd=$this->FTPRecursiveList($ftp,'./');
      }

      ftp_chdir($ftp,$dirhome);

      // Importe les medias LD et leur equivalent HD si présent
      foreach($files as $file) {
	$infos=pathinfo($tmpdir.$file);
	@mkdir($infos['dirname'],0777,true);
	// Controle que le transfert est fini
	if(!waitForFileCompletion($ftp,$dir.$file,5000000)) continue;
	$get=ftp_get($ftp,$tmpdir.$file,$dir.$file,FTP_BINARY);
	if(!$get) continue;
	$ar2=$ari;
	$tot++;
	$message.=$file.' (LD';
	$ar2['media']=array('tmp_name'=>$tmpdir.$file,'name'=>$file);
	$message.=') => ';
	$ar2['ref']=$infos['filename'];
	$ar2['importedfrom']=$oid;
	if(!empty($br['lines_oowner'][$i]->raw)){
	  $ar2['OWN']=$br['lines_oowner'][$i]->raw;
	}
	$ret=$this->importAMedia($ar2,array('uniq'=>$br['lines_ouniqname'][$i]->raw,'iptctpl'=>@$br['lines_oiptctpl'][$i]->filename));

	// message de log
	$i1=$xds->rDisplay($oid);
	$xds->procEdit(array('oid'=>$oid,'mylog'=>"LD/".date('Y-m-d H:i:s').'/'.$ar2['ref'].':'.$ret['message']."\n".$i1['omylog']->text,'_local'=>true));
	$message.=$ret['message']."\r\n";
	ftp_delete($ftp,$dir.$file);
      }

      // Importe les medias HD qui n'ont pas de LD
      $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
      foreach($fileshd as $file){
	if(in_array($file,$files)) continue;
	$infos=pathinfo($tmpdir.'hd_'.$file);
	mkdir($infos['dirname'],0777,true);
	// controle que le transfert est fini
	if(!waitForFileCompletion($ftp, $dirhd.$file)) continue;
	$get=ftp_get($ftp,$tmpdir.'hd_'.$file,$dirhd.$file,FTP_BINARY);
	if(!$get) continue;
	$ar2=$ari;
	$tot++;
	$message.=$file.' (HD) => ';
	$ld=$this->generateLDFromHD($tmpdir.'hd_'.$file,$file,NULL);
	if($ld){
	  $ar2['mediahd']=array('tmp_name'=>$tmpdir.'hd_'.$file,'name'=>$file);
	  $ar2['media']=array('tmp_name'=>$ld,'name'=>$file);
	}else{
	  $ar2['media']=array('tmp_name'=>$tmpdir.'hd_'.$file,'name'=>$file);
	}
	$infos=pathinfo($file);
	$ar2['ref']=$infos['filename'];
	$ar2['importedfrom']=$oid;
	$ret=$this->importAMedia($ar2,array('uniq'=>$br['lines_ouniqname'][$i]->raw,'iptctpl'=>@$br['lines_oiptctpl'][$i]->filename));
	$message.=$ret['message']."\r\n";

	// message de log
	$i1=$xds->rDisplay($oid);
	$xds->procEdit(array('oid'=>$oid,'mylog'=>"HD/".date('Y-m-d H:i:s').'/'.$ar2['ref'].':'.$ret['message']."\n".$i1['omylog']->text,'_local'=>true));
	ftp_delete($ftp,$dirhd.$file);
      }
      ftp_close($ftp);

      \Seolan\Library\Dir::unlink($tmpdir);
      // Desctivation de la procedure si usage unique
      if($br['lines_oonlyone'][$i]->raw==1) getDB()->execute("update {$this->imports} set PUBLISH=? where KOID=?", ["2", $oid]);
      // Envoie du mail de rapport
      if(!empty($br['lines_oemail'][$i]->raw) && $tot){
	\Seolan\Core\Logs::update($this->getLabel().' : import '.$br['lines_otitle'][$i]->raw,NULL,$message);
	$this->sendMail2User($this->getLabel().' : import '.$br['lines_otitle'][$i]->raw,$message,$br['lines_oemail'][$i]->raw);
      }
      if(!empty($olduser)) {
	setSessionVar('UID',$olduser->uid());
	$GLOBALS["XUSER"]=$olduser;
	unset($oluser);
      }
      \Seolan\Library\Lock::releaseLock($lck);
    }
    \Seolan\Core\Logs::debug('\Seolan\Module\Media\Media::runImports end');
    \Seolan\Library\Lock::releaseLock($lckglobal);
  }

  /// Créé des repertoires sur un ftp de facon récursive
  public function FTPRecursiveMkdir($host,$login,$pass,$dirs){
    $ftph=str_replace('ftp://','',$host);
    $ftp=ftp_connect($ftph);
    if($ftp){
      $login=ftp_login($ftp,$login,$pass);
      if($login){
	foreach($dirs as $dir){
	  if(empty($dir)) continue;
	  $ndir='';
	  $sdirs=explode('/',$dir);
	  foreach($sdirs as $sdir){
	    if(empty($sdir)) continue;
	    $ndir.=$sdir.'/';
	    ftp_mkdir($ftp,$ndir);
	  }
	}
      }
      ftp_close($ftp);
    }
  }

  /// Liste de facon recursive les fichiers d'un repertoire
  function FTPRecursiveList(&$ftp,$dir){
    if(empty($dir)) return array();
    $alls=array();
    $files=ftp_nlist($ftp,$dir);
    $pwd=ftp_pwd($ftp);
    foreach($files as $file){
      if(@ftp_chdir($ftp,$file)){
	ftp_chdir($ftp,$pwd);
	$sfiles=$this->FTPRecursiveList($ftp,$file.'/');
	if($sfiles){
	  foreach($sfiles as $foo){
	    $alls[]=$file.'/'.$foo;
	  }
	}
      }else{
	$alls[]=$file;
      }
    }
    return $alls;
  }

  /// Importe un media
  function importAMedia($ar,$ari){
    $f='';
    $uniq=$ari['uniq'];
    if($uniq==1) {
      $rs=getDB()->select('select KOID from '.$this->table.' where ref=?',array($ar['ref']));
      if($rs->rowCount()>0){
	$ors=$rs->fetch();
	$ar['oid']=$ors['KOID'];
	$f='procEdit';
	$message='updated';
      }
    }
    if(empty($f)){
      $f='procInsert';
      $message='added';
    }
    if(!empty($ari['iptctpl'])){
      if(!empty($ar['media']['tmp_name'])) \Seolan\Field\File\File::setFileMetaWithTemplate($ari['iptctpl'],$ar['media']['tmp_name']);
      if(!empty($ar['mediahd']['tmp_name'])) \Seolan\Field\File\File::setFileMetaWithTemplate($ari['iptctpl'],$ar['mediahd']['tmp_name']);
    }
    $ret=$this->$f($ar);
    if($ret['oid']) $oid=$ret['oid'];
    else $oid=$ar['oid'];
    return array('oid'=>$oid,'message'=>$message);
  }

  /// Génère une version LD d'un media HD
  public function generateLDFromHD($file,$filename=NULL,$mime=NULL,$opt=NULL){
    $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
    $mime=$mimeClasse->getValidMime($mime,$file,$filename);
    // Génération de la LD car champ obligatoire
    if($mimeClasse->isImage($mime)){
      if(empty($opt)) $opt=$this->imgresize;
      system(TZR_MOGRIFY_RESIZER.' -resize "'.$opt.'" '.$file.' '.$file.'.ld');
      return $file.'.ld';
    }else{
      return false;
    }
  }

  /***********************/
  /* Gestion des exports */
  /***********************/
  /// Parcourt les exports
  function exportBrowse($ar=NULL){
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_ACCOUNTS');
    $ar['_filter']='modid='.$this->_moid.' and atype="export"';
    $ar['pagesize'] = -1;
    return $xds->browse($ar);
  }
  /// Affiche un export
  function exportDisplay($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_ACCOUNTS');
    $ret=$xds->display($ar);
    $cplt=\Seolan\Core\System::xml2array($ret['ocplt']->raw);
    $link=new \Seolan\Field\Link\Link();
    $link->field='query';
    $link->target='QUERIES';
    $ret['oquery']=$link->display($cplt['query']);
    $ret['ocplt']=$cplt;
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /// Prepare l'insertion d'un export
  function exportInput($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_ACCOUNTS');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=$xds->input($ar);
    $link=new \Seolan\Field\Link\Link();
    $link->field='cplt[query]';
    $link->target='QUERIES';
    $link->checkbox=false;
    $link->filter='(modid = '.$this->_moid.')';
    $ret['oquery']=$link->edit(NULL);
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /// Insere un export
  function exportProcInput($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $cplt=$p->get('cplt');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_ACCOUNTS');
    \Seolan\Core\System::array2xml($cplt,$ar['cplt']);
    $ar['modid']=$this->_moid;
    $ar['atype']='export';
    return $xds->procInput($ar);
  }
  /// Prepare l'edition d'un export
  function exportEdit($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_ACCOUNTS');
    $ret=$xds->edit($ar);
    $cplt=\Seolan\Core\System::xml2array($ret['ocplt']->raw);
    $link=new \Seolan\Field\Link\Link();
    $link->field='cplt[query]';
    $link->target='QUERIES';
    $link->checkbox=false;
    $ret['oquery']=$link->edit($cplt['query']);
    $ret['ocplt']=$cplt;
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /// Edite un export
  function exportProcEdit($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $cplt=$p->get('cplt');
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_ACCOUNTS');
    \Seolan\Core\System::array2xml($cplt,$ar['cplt']);
    $ar['modid']=$this->_moid;
    $ar['atype']='export';
    return $xds->procEdit($ar);
  }
  /// Efface un export
  public function exportDel($ar=NULL){
    $xds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_ACCOUNTS');
    return $xds->del($ar);
  }
  /// Execute les exports
  public function runExports($ar=NULL){
    $rs=getDB()->select('select * from _ACCOUNTS where modid=? and atype="export"', array($this->_moid));
    while($rs && $ors=$rs->fetch()){
      $cplt=\Seolan\Core\System::xml2array($ors['cplt']);
      $upd=\Seolan\Core\DbIni::get('lastexport_'.$ors['KOID'].'_'.$this->_moid,'val');
      $oldfilter=$this->filter;
      $this->filter=$this->table.'.UPD>="'.$upd.'"';
      $query=$this->procQuery(array('getselectonly'=>true,'_storedquery'=>$cplt['query']));
      $this->filter=$oldfilter;
      \Seolan\Core\DbIni::set('lastexport_'.$ors['KOID'].'_'.$this->_moid,date('Y-m-d H:i:s'));
      $this->exportFiles(array('select'=>$query,'fmt'=>'ftpbatch','ftpserver'=>$ors['url'],'ftplogin'=>$ors['login'],
			       'ftppassword'=>$ors['passwd'],'ftpdir'=>$cplt['dir'],'zip'=>0,'reportto'=>$cplt['reportto']));
    }
  }

  /***************************/
  /* Gestion de l'indexation */
  /***************************/
  /// presentation d'un resultat de recherche dans le module
  public function showSearchResult($oids) {
    $_REQUEST = array(
      'function' => 'procQuery',
      'template' => 'Module/Media.browse.html',
      'moid' => $this->_moid,
      'tplentry' => 'br',
      'clearrequest' => 1,
      '_persistent' => 1,
      'oids' => $oids
    );
    $GLOBALS['XSHELL']->run();
    exit;
  }

  /// preview des resultats de recherche dans la liste globale des resultats de la recherche plain texte
  public function previewSearchResult($oids) {
    $browse = $this->xset->browse(array(
      'tplentry' => TZR_RETURN_DATA,
      'selectedfields' => array('media', 'ref'),
      'cond' => array('KOID' => array('=', array_slice($oids, 0, 100))),
      'order' => 'FIELD(KOID,"'.implode('","', array_slice($oids, 0, 100)).'")',
      'pagesize' => 100,
      'nocount' => 1
    ));
    $this->browse_actions($browse);
    $browse['count'] = count($browse['lines_oid']);
    return $browse;
  }

  function goto1($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(false,true);
    $moid=$this->_moid;
    $right= $this->secure($oid, 'display');
    if(!$right) \Seolan\Library\Security::warning('\Seolan\Module\Media\Media::goto1: could not access to objet '.$oid.' in module '.$moid);
    header("Location: {$url}&moid=$moid&template=Module/Table.view.html&oid=$oid&function=display&tplentry=br");
  }
}
