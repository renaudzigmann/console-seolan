<?php
namespace Seolan\Module\Project;
class Project extends \Seolan\Module\Table\Table{
  public $multipleedit=false;
  static public $upgrades=['20220111'=>'','20220118'=>'','20220131'=>''];
  public static $singleton = true;

  function __construct($ar=NULL){
    parent::__construct($ar);
    $fieldssec = $this->fieldssec;
    $this->fieldssec['amods']='ro';
    $this->fieldssec['agrps']='ro';
    $this->fieldssec['acal']='ro';
    if (static::hasUpgrade($this, '20220118')){
      $this->fieldssec['directorymodule']='ro';
    }
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['preDel']=array('rw','rwv','admin');
    $g['dashboard']=array('ro','rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  public function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('multipleedit');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Project_Project','annumod'),'annumod','module');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Project_Project','calmod'),'calmod','module',array('toid'=>XMODCALENDARADM_TOID));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Project_Project','projectfield'),'projectfield','field',
			    array('table'=>'USERS','compulsory'=>false,'type'=>array('\Seolan\Field\Link\Link')));
  }
  /**
   * compte tenu des traitements de suppression (archivage etc)
   * seule la suppression fiche à fiche est possible
   */
  function al_browse(&$my){
    parent::al_browse($my);
    unset($my['del']);
  }
  function al_edit(&$my){
    parent::al_edit($my);
    unset($my['del']);
  }
  function al_display(&$my){
    parent::al_display($my);
    unset($my['del']);
  }
  function dashboard($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $d=$this->display(array('tplentry'=>TZR_RETURN_DATA,array('selectedfields'=>array('amods','agrps'))));
    // Infos générale
    if(\Seolan\Core\System::tableExists('_STATS')){
      $users=\Seolan\Module\Group\Group::users($d['oagrps']->oidcollection);
      $nb=getDB()->fetchOne('select ifnull(sum(cnt),0) from _STATS '.
                            'where SFUNCTION="procAuth" AND SMOID IN ("'.implode('","',$d['oamods']->oidcollection).'") AND SGRP IN ("'.implode('","',$users).'")');
      $ret['geninfos']['login']=$nb;
    }
    // Infos par module
    foreach($d['oamods']->oidcollection as $i=>$moid){
      $mod=\Seolan\Core\Module\Module::objectFactory($moid);
      if (is_object($mod)){
	$infos[$moid]=$mod->getInfos(array('grps'=>$d['oagrps']->oidcollection));
      } else {
	$infos[$moid]=array('modulename'=>'moid : '.$moid, 'infos'=>array('error'.$moid=>(object)array('html'=>'unable to load module',
									'__label'=>'moid : '.$moid)));
      }
    }
    $ret['modsinfos']=$infos;
    // Module statistique
    $mod=\Seolan\Core\Module\Module::objectFactory(array('toid'=>XMODSTATS_TOID));
    if($mod && $mod->secure('','index')) $ret['statsmodule']=$moid;

    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  
  function display($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=parent::display($ar);
    if (is_array($ret)){
      $ret['__ajaxtabs'][]=array('title'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Project_Project','dashboard','text'),
				 'url'=>$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid='.$ret['oid'].
				 '&tplentry=br&function=dashboard&template=Module/Project.dashboard.html&_ajax=1&_raw=1&skip=1&_uniqid='.\Seolan\Core\Shell::uniqid());
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  function edit($ar=NULL){
    $ar['fieldssec']=['title'=>'ro','prefix'=>'ro','mods'=>'ro','cal'=>'ro','grps'=>'ro','amods'=>'ro','agrps'=>'ro','acal'=>'ro','tgrps'=>'ro'];

    if ($this->xset->fieldExists('menuid'))
      $ar['fieldssec']['menuid'] = 'ro';

    $edit =  parent::edit($ar);
    
    return $edit;
  }
  /**
   * positionne le logo comme picto du menu dédié au projet
   */
  function updateSectionLogo($project){
    if (isset($project['ologo']) && !empty($project['ologo']->resizer)){
      $amod=\Seolan\Core\Module\Module::singletonFactory(XMODBACKOFFICEINFOTREE_TOID);
      $menuoid=str_replace($this->table,$amod->table,$project['oid']);
      // vérification que l'entrée existe encore pour l'oid calculé
      $ors=getDB()->fetchRow('select distinct KOID as KOID from '.$amod->table.' where koid=?', [$menuoid]);
      if ($ors){
	$tofilename = TZR_TMP_DIR.uniqid('plogo500-'.$this->_moid.'-');
	$resizer = $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().$project['ologo']->resizer.'&geometry=500x500&extent=500x500&gravity=Center';

	$resizerContext = null;
	$table = $this->table;
	$field = 'logo';
	$is_secure = (!empty($GLOBALS['TZR_SECURE']['_all']) || !empty($GLOBALS['TZR_SECURE'][$table]['logo']) || (!empty($GLOBALS['TZR_SECURE'][$table]) && $GLOBALS['TZR_SECURE'][$table] == '_all'));
	$sessionCookieName = \Seolan\Core\Shell::admini_mode()?TZR_BO_SESSION_NAME:TZR_FO_SESSION_NAME;
	if ($is_secure && isset($_COOKIE[$sessionCookieName])){
	  
	  sessionClose(); // ! accès au resizer champ secure, verrour session
	  
	  // contexte avec cookie de session pour cas fichiers "secures"
	  $resizerContext = stream_context_create(['http'=>['method'=>'GET',
							    'header'=>['Cookie: '.$sessionCookieName.'='.$_COOKIE[$sessionCookieName]]]]);
	}

	\Seolan\Core\Logs::notice(__METHOD__, "resizer $resizer -> $tofilename");

	$contents = file_get_contents($resizer, false, $resizerContext);

	if (empty($contents))
	  \Seolan\Core\Logs::critical(__METHOD__, "error resize logo $resizer");

	$ret = file_put_contents($tofilename, $contents);
	unset($contents);
	$amod->xset->procEdit(['_options'=>['local'=>1],
			       'oid'=>$ors['KOID'],
			       'picto'=>$tofilename,
			       '__picto_HID'=>['from'=>$project['ologo']->filename]
			       ]);
	
	if (file_exists($tofilename))
	  unlink($tofilename);
	
      }
    }
  }
  /**
   * surcharge des mises à jours -> récpercution du logo
   */
  function procEdit($ar=null){
    $r = parent::procEdit($ar);
    if ($r['updated']){
      $rd = $this->xset->rdisplay($r['oid'],
				  null,
				  false,
				  null,
				  null,
				  ['selectedfields'=>['directorymodule','directoryfiltergroups','logo']]);
      $this->updateSectionLogo($rd);
      if (static::hasUpgrade($this, '20220118'))
	$this->updateAnnuaire($rd);
    }
    return $r;
  }
  /// mise à jour du filtre sur l'annuaire
  protected function updateAnnuaire($rd){
    
    if (empty($rd['odirectorymodule']->raw) || !$this->exists((int)$rd['odirectorymodule']->raw))
      return;
   
    $amod = \Seolan\Core\Module\Module::objectFactory($rd['odirectorymodule']->raw);
    $filter=$amod->xset->make_cond($amod->xset->desc['GRP'],
				   ['=',$rd['odirectoryfiltergroups']->oidcollection]
				   );
    \Seolan\Core\Logs::notice(__METHOD__,"filter : {$filter}, '{$rd['odirectoryfiltergroups']->text}', annuaire : '{$amod->_moid}, {$amod->getLabel()}' ");

    $amod->procEditProperties(['_options'=>['local'=>1],
			       'options'=> ['filter'=>$filter]]);
  }
  function procInsert($ar=NULL){
    $p = new \Seolan\Core\Param($ar, []);
    $retInsert = parent::procInsert($ar);

    $newproject=$this->display(array('tplentry'=>TZR_RETURN_DATA,'oid'=>$retInsert['oid']));
    $newa='';
    $allmods=$alltables=$newm=$newg=$documenttypes=[];
    // Duplication des modules
    foreach($newproject['omods']->oidcollection as $i=>$moid){
      if(!empty($allmods[$moid])) {
	$newm[$moid]=$allmods[$moid];
	continue;
      }
      $mod=\Seolan\Core\Module\Module::objectFactory($moid);
      $ret=$mod->duplicateModule(['prefix'=>$newproject['oprefix']->raw,
				  'group'=>$newproject['otitle']->raw,
				  'tables'=>$alltables,
				  'mods'=>$allmods]);
      $newm[$moid]=$ret['moid'];
      $allmods=$allmods+$ret['duplicatemods'];
      $alltables=$alltables+$ret['duplicatetables'];
      if(isset($ret['duplicatedocumenttypes']) && is_array($ret['duplicatedocumenttypes']))
	$documenttypes=$documenttypes+$ret['duplicatedocumenttypes'];
    }

    \Seolan\Core\Module\Module::clearCache();
    // Duplication des groupes
    // recherche de tous les groupes référencés dans le modèle
    if(!empty($newproject['ogrps']->oidcollection) && $newproject['oprefix']->raw){
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=GRP');
      foreach($newproject['ogrps']->oidcollection as $i=>$gid){
	// Duplique le groupe, change son nom si necessaire
	$nid=$x->duplicate(array('oid'=>$gid));
	$newg[$gid]=$nid;
	$ors=getDB()->fetchRow('select GRP from GRP where KOID=? LIMIT 1', [$nid]);
	if(($pos=strpos($ors['GRP'],':'))!==false) $name=$newproject['oprefix']->raw.substr($ors['GRP'],$pos);
	else $name=$newproject['oprefix']->raw.':'.$ors['GRP'];
	getDB()->execute('UPDATE GRP SET GRP=? where KOID=?', array($name, $nid));
      }
      
      // Si un groupe dupliqué fait partie d'un groupe de securité lui aussi dupliqué, on met le nouveau module à la place du modele
      foreach($newg as $gid=>$nid){
	$g=$x->display(array('oid'=>$nid,'selectedfields'=>array('GRPS'),'tplentry'=>TZR_RETURN_DATA));
	$change=false;
	foreach($g['oGRPS']->oidcollection as $i=>$pid){
	  if(in_array($pid,$newproject['ogrps']->oidcollection)){
	    $g['oGRPS']->oidcollection[$i]=$newg[$pid];
	    $change=true;
	  }
	}
	if($change){
	  $x->procEdit(array('oid'=>$g['oid'],'GRPS'=>$g['oGRPS']->oidcollection,'_options'=>array('local'=>true)));
	}
      }
    }
    // Création du menu dans l'arbo de l'admin : 1 rubrique/section par module, oid caclulé pour le menu projet
    $i=1;
    $amod=\Seolan\Core\Module\Module::singletonFactory(XMODBACKOFFICEINFOTREE_TOID);
    if ($this->xset->fieldExists('menuid') && !empty($newproject['omenuid']->raw))
      $projectLinkup = $newproject['omenuid']->raw;
    elseif (!$p->is_set('_projectLinkup')){
      $ors=getDB()->fetchAll('select KOID from '.$amod->table.' where alias="top"');
      $projectLinkup = $ors[0]['KOID'];
    } else
       $projectLinkup = $p->get('_projectLinkup', 'local');
    
    $noid=str_replace($this->table,$amod->table,$newproject['oid']);
    $pnode=$amod->procInput(array('newoid'=>$noid,'title'=>$newproject['otitle']->raw,'descr'=>$newproject['odescr']->html,'linkup'=>$projectLinkup,'corder'=>1,'alias'=>rewriteToAscii($newproject['oprefix']->raw.'-'.$newproject['otitle']->raw),'_options'=>array('local'=>true)));
    foreach($newm as $moid){
      $mod=\Seolan\Core\Module\Module::objectFactory($moid);
      $moduleIcon = $mod->getIconCssClass();
      $url=$mod->getMainAction();
      if(!empty($url)){
	$title=substr($mod->getLabel(),strpos($mod->getLabel(),':')+1);
	$ret=$amod->procInput(['title'=>$title,
			       'icon'=>$moduleIcon,
			       'linkup'=>$pnode['oid'],
			       'corder'=>$i,
			       'alias'=>rewriteToAscii($newproject['oprefix']->raw.'-'.$title),
			       'descr'=>$mod->comment,
			       '_options'=>['local'=>true]]);
	$amod->insertsection(array('oidit'=>$ret['oid'],'oidtpl'=>'TEMPLATES:ADMIN-'.$amod->_moid,'position'=>1,'title'=>$mod->getLabel(),'comment'=>'',
				   'modid'=>$moid,'fct'=>$url));
	$i++;
      }
    }    
    // Création d'un agenda + ajout d'une page dans l'arbo du projet
    if($newproject['ocal']->raw){
      $mod=\Seolan\Core\Module\Module::objectFactory($this->calmod);
      $newa=$mod->xset->duplicate(array('oid'=>$newproject['ocal']->raw,'newoid'=>str_replace($this->table,$mod->table,$newproject['oid'])));
      getDB()->execute('update '.$mod->table.' set name=? where KOID=?', array($newproject['otitle']->raw,$newa));
      $mod=\Seolan\Core\Module\Module::objectFactory(array('moid'=>$mod->calmod,'oid'=>$newa,'tplentry'=>TZR_RETURN_DATA));
      $ret=$amod->procInput(array('title'=>$mod->getLabel(),'linkup'=>$pnode['oid'],'corder'=>$i,'alias'=>rewriteToAscii($newproject['oprefix']->raw.'-'.$mod->getLabel()),
				  'icon'=>$mod->getIconCssClass(),
				  'descr'=>$mod->comment,
				  '_options'=>array('local'=>true)));
      $url=$mod->getMainAction();
      $url=preg_replace('/&oid=[^&]+/','&oid='.$newa,$url);
      $amod->insertsection(array('oidit'=>$ret['oid'],'oidtpl'=>'TEMPLATES:ADMIN-'.$amod->_moid,'position'=>1,
				 'title'=>$mod->getLabel(),
				 'comment'=>'',
				 'modid'=>$moid,
				 'fct'=>$url));
      $i++;
    }
    // Creation d'un module annuaire filtré + ajout d'une page dans l'arbo du projet
    if(!empty($this->annumod)){
      $mod=\Seolan\Core\Module\Module::objectFactory($this->annumod);
      if ($mod){
	// filtre sur groupes du projet : groupes calculés au dessus si la version le permet
	if (static::hasUpgrade($this, '20220118')
	    && isset($newproject['odirectoryfiltergroups']->oidcollection)
	    && !empty($newproject['odirectoryfiltergroups']->oidcollection)){
	  $filterGrp = array_unique(
	    array_merge(array_values($newg),
			$newproject['odirectoryfiltergroups']->oidcollection)
	  );
	} else {
	  $filterGrp = $newg;
	}
	$filter=$mod->xset->make_cond($mod->xset->desc['GRP'],array('=',$filterGrp));
	$ret=$mod->duplicateModule(array('prefix'=>$newproject['oprefix']->raw, 'group'=>$newproject['otitle']->raw,'filter'=>$filter,'noduplicatetable'=>1));
	$allmods=$allmods+$ret['duplicatemods'];
	$moid=$ret['moid'];
	$mod=\Seolan\Core\Module\Module::objectFactory($moid);
	$title=substr($mod->getLabel(), strpos($mod->getLabel(), ':')+1);
	$ret=$amod->procInput(array('title'=>$title,'linkup'=>$pnode['oid'],'corder'=>$i,'alias'=>rewriteToAscii($newproject['oprefix']->raw.'-'.$title),
				   'icon'=>$mod->getIconCssClass(),
				    'descr'=>$mod->comment,
				    '_options'=>array('local'=>true)));
	
	$amod->insertsection(array('oidit'=>$ret['oid'],'oidtpl'=>'TEMPLATES:ADMIN-'.$amod->_moid,
				   'position'=>1,
				   'title'=>$mod->getLabel(),
				   'comment'=>'',
				   'modid'=>$moid,'fct'=>$mod->getMainAction()));
	$i++;
	$newm[$this->annumod]=$moid;

	// mémorisation du module annuaire et du filtre
	if (static::hasUpgrade($this, '20220118')){
	  $this->xset->procEdit([
	    'oid'=>$retInsert['oid'],
	    '_options'=>['local'=>true],
	    'directorymodule'=>$moid,
	    'directoryfiltergroups'=>$filterGrp
	  ]);
	  
	  $newproject['odirectoryfiltergroups'] = $this->xset->rdisplay($retInsert['oid'],
									null,
									false,
									null,
									null,
									['selectedfield'=>['directoryfiltergroups']])['odirectoryfiltergroups'];
	  
	}
	
	// pour chacun des nouveaux modules, on paramètre l'annuaire comme annuaire de notification
	// voir dessous
	
      }
    }
    // mise à jour des propriétés des nouveaux modules : home = false et annuaire projet si présent
    $newModulesOptions = ['home'=>'0'];
    if ($this->annumod && isset($newm[$this->annumod])) {
      $newModulesOptions['directorymodule']=$newm[$this->annumod];
    }
    foreach($newm as $moid){
      $mod=\Seolan\Core\Module\Module::objectFactory($moid);
      $mod->procEditProperties(['_options'=>['local'=>true],
				'options'=>$newModulesOptions]);
    }
    // Duplication des proprietes
    foreach($newg as $gid=>$nid){
      foreach($allmods as $moid=>$nmoid) {
	$rs=getDB()->select("SELECT * FROM OPTS WHERE user=? AND modid=?", [$gid, $moid]);
	while($rs && $o=$rs->fetch()) {
	  $nkoid=\Seolan\Core\DataSource\DataSource::getNewBasicOID('SUB');
	  getDB()->execute("INSERT INTO OPTS SET KOID=?, LANG=?, OWN=?, user=?, ".
			   "modid=?, dtype=?, specs=?", [$nkoid, $o['LANG'], $o['OWN'], $nid, $nmoid, $o['dtype'], $o['specs']]);
	}
      }
    }
    unset($o);

    // Duplique les droits puis change la cible des droits pour les modules dupliqués, agenda, annuaire...

    foreach($newg as $gid=>$nid){
      $GLOBALS['XUSER']->copyAllUserAccess($gid,$nid);
      
      \Seolan\Core\Logs::debug(__METHOD__."copying acl for $gid to $nid for doc types".implode(',', array_keys($documenttypes)).' => '.implode(',', array_values($documenttypes)));
	
      $GLOBALS['XUSER']->copyAllUserAccessOnDocumentTypes($gid,$nid, $documenttypes);
      
      foreach($allmods as $moid=>$nmoid) {
	getDB()->execute('update ACL4 set AMOID=? where AMOID=? and AGRP=?',[$nmoid, $moid, $nid]);
	\Seolan\Core\Logs::secEvent(__METHOD__,"Set rules for $nmoid on $nid", $nmoid);
      }
      
      if($newa)
	getDB()->execute('update ACL4 set AKOID=? where AKOID=? AND AGRP=?',[$newa, $newproject['ocal']->rawx, $nid]);
    }
    // duplication des droits existant des groupes transversaux (groupes hors projet) sur les nouveaux modules/documents types 
    if (isset($newproject['otgrps']->oidcollection)){
      foreach($newproject['otgrps']->oidcollection as $tgid){
	\Seolan\Core\Logs::debug(__METHOD__.' tgid : '.$tgid);
	$params = $oldmoids= array_keys($allmods);
	$params[] = $tgid;
	// droits sur les modules - et objets éventuels des modules 
	$ori = getDB()->fetchAll('select AGRP,AFUNCTION,ACLASS,ALANG,AMOID,AKOID,OK FROM ACL4 where AMOID in ('.implode(',', array_fill(0,count($oldmoids), '?')).') and AGRP=?',$params);
	foreach($ori as $srcline){
	  $aclvalues = [':AOID'=>\Seolan\Core\User::getNewAoid()];
	  foreach($srcline as $k=>$v){
	    if ($k == 'AMOID') {
	      $aclvalues[':'.$k] = $allmods[$v];
	    } else {
	      $aclvalues[':'.$k] = $v;
	    }
	  }
	  $aclvalues[':ACOMMENT'] = "Module\Project tgrps : {$srcline['AMOID']} -> {$aclvalues[':AMOID']}";
	  getDB()->execute('INSERT INTO ACL4 (AOID,AGRP,AFUNCTION,ACLASS,ALANG,AMOID,AKOID,OK,ACOMMENT) values (:AOID,:AGRP,:AFUNCTION,:ACLASS,:ALANG,:AMOID,:AKOID,:OK,:ACOMMENT)', $aclvalues);
	  \Seolan\Core\Logs::secEvent(__METHOD__, "Copy rules from model {$srcline['AMOID']} to module {$aclvalues[':AMOID']}", $aclvalues[':AMOID']);
	}
	// droits sur les documents types
	$GLOBALS['XUSER']->copyAllUserAccessOnDocumentTypes($tgid,$tgid, $documenttypes);
	// agenda idem
	if($newa) {
	  $GLOBALS['XUSER']->copyAllUserAccessOnObjects($tgid,$tgid, [$newproject['ocal']->raw=>$newa]);
	}
      }
    } 

    // modification des droits positionnés sur des OID individuels lorsqu'il y a duplication des données (les droits ont été dupliqués par copyAllUserAccess et tgid au dessus)
    foreach($allmods as $moid=>$nmoid) {
      foreach($alltables as $oldtable => $newtable) {
	getDB()->execute("UPDATE ACL4 set AKOID=REPLACE(AKOID,?,?) WHERE AMOID=? AND AKOID!=''",["$oldtable:", "$newtable:", $nmoid]);
	\Seolan\Core\Logs::secEvent(__METHOD__,"Set rules for $nmoid $newtable objects", $nmoid);
	getDB()->execute("UPDATE OPTS set specs=REPLACE(specs,'$oldtable:','$newtable:') WHERE modid=?",[$nmoid]);
      }
    }
    // Enregistrement des modules/groupes rattachés
    $this->xset->procEdit(array('oid'=>$newproject['oid'],'amods'=>$allmods,'agrps'=>array_values($newg),'acal'=>$newa,'_options'=>array('local'=>true)));

    // diverses tâches à effectuer sur les modules une fois que la duplication est terminée
    foreach($newm as $oldmoid => $newmoid) {
      $mod=\Seolan\Core\Module\Module::objectFactory($newmoid);
      $mod->postDuplicateModule($newm);
    }

    // Force le rechargement des menus
    setSessionVar('_reloadmods',1);
    setSessionVar('_reloadmenu',1);

    $this->updateSectionLogo($newproject);

    return $retInsert;

  }

  /// Prepare la suppression d'un projet
  function preDel($ar=NULL){
    return $this->display($ar);
  }

  /// Fonction de suppression d'un projet
  function del($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    $arch=$p->get('arch');
    $delmods=$p->get('delmods');
    $delusers=$p->get('delusers');
    if(!is_array($oid)){
      $gmod=\Seolan\Core\Module\Module::singletonFactory(XMODGROUP_TOID);
      $d=$this->xset->display(array('oid'=>$oid,'selectedfields'=>array('agrps','amods','acal'),'_options'=>array('local'=>true)));
      if($arch){
	// Suppression groupes
	foreach($d['oagrps']->oidcollection as $i=>$gid){
	  $gmod->del(array('oid'=>$gid,'delusers'=>false,'_options'=>array('local'=>true)));
	}
	// Deplacement modules
	foreach($d['oamods']->oidcollection as $i=>$moid){
	  $mod=\Seolan\Core\Module\Module::objectFactory($moid);
	  $mod->procEditProperties(array('options'=>array('group'=>$arch)));
	}
      }else{
	// Suppression utilisateurs et/ou groupes
	foreach($d['oagrps']->oidcollection as $i=>$gid){
	  if($delusers) $gmod->del(array('oid'=>$gid,'delusers'=>true,'_options'=>array('local'=>true)));
	  else $gmod->del(array('oid'=>$gid,'delusers'=>false,'_options'=>array('local'=>true)));

	}
	if($delmods){
	  // Suppression des modules
	  foreach($d['oamods']->oidcollection as $i=>$moid){
	    $mod=\Seolan\Core\Module\Module::objectFactory($moid);
	    if(is_object($mod)) $mod->delete(array('withtable'=>true));
	  }
	  \Seolan\Core\Module\Module::clearCache();
	  // Suppression de l'agenda
	  if(!empty($d['ocal']->raw)){
	    $mod=\Seolan\Core\Module\Module::objectFactory($this->calmod);
	    $mod->del(array('oid'=>$d['ocal']->raw,'_options'=>array('local'=>1)));
	  }
	  // suppression de l'agenda
	  if($d['oacal']->raw){
	    $mod=\Seolan\Core\Module\Module::objectFactory($this->calmod);
	    $mod->del(array('oid'=>$d['oacal']->raw,'_options'=>array('local'=>1)));
	  }
	}
      }
      // Suppression du noeud de l'arbo
      $amod=\Seolan\Core\Module\Module::singletonFactory(XMODBACKOFFICEINFOTREE_TOID);
      $noid=str_replace($this->table,$amod->table,$oid);
      $amod->delCat(array('oid'=>$noid));

      // Force le rechargement des menus
      setSessionVar('_reloadmods',1);
      setSessionVar('_reloadmenu',1);
      parent::del($ar);
      \Seolan\Core\Shell::setNext($this->getMainAction()); 
    }
  }

  /**
   * surcharge de la suppression : écran des options
   */
  function browseActionDelHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
    return 'class="cv8-ajaxlink cv8-delaction"';
  }
  /**
   * surcharge suppression : mise en forme
   */
  function browseActionDelUrl($usersel, $linecontext=null){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=preDel&template=Module/Project.preDel.html';
  }
  /**
   * surcharge suppression : mise en forme
   */
  protected function formatBrowseDeleteAction($oid, $txt, $ico, $url, $attr, $urlparms=''){
    $furl=str_replace('<oid>',$oid,$url).$urlparms;
    $fa=str_replace('<oid>',$oid,$attr);
    return array(
        'link'=>'<a href="'.$furl.'" '.$fa.' title="'.$txt.'">'.$ico.'</a>',
        'url'=>$furl,
        'label'=>$ico);
  }

  /// rend tous les projets qui utilisent un module donné
  function getProjectsWithModule($moid) {
    $oids=getDB()->fetchCol('SELECT KOID FROM '.$this->table.' WHERE INSTR(amods, "|'.$moid.'|")');
    return $oids;
  }
}
?>
