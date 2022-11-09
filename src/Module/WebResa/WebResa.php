<?php
namespace Seolan\Module\WebResa;
/**
 * File Webresa
 * Module d'interface générique avec webresa.fr
 * utilisation des flux standards de webresa
 *
 * \author Julien Guillaume <julien.guillaume@xsalto.com>
 * \version 0.1 
 * Décembre 2012 pour seolan 8.1
 *
 */

class WebResa extends \Seolan\Module\Table\Table {
  public $prefix = "WR_";
  public $needUpdate = false;
  public $versionInstalled = false;
  public $fluxurl = NULL;
  public $agenceid = NULL;
  public $codeflux = NULL;
  public $codesite = NULL;
  public $moidagences = NULL;
  public $moidattributs = NULL;
  public $moidpays = NULL;
  public $onlinebooking = false;
  public $onlinebookingurl = NULL;
  public $importoptionstarifs = false;
  public $purgeoldtreks = false;
  public $purgeoldtreksdepart = false;
  public $defaultSelectedFields = array('libelle','commentaire','prix_minimum','duree','niveau_oid');
  public static $module_version=2;
 
  private $cachefile = NULL;

  const MODE_LIBERTE = 'liberte';
  function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->cachefile = TZR_TMP_DIR.$this->_moid.'webresa.xml';
    
    /*
    if($this->interactive && \Seolan\Core\Shell::admini_mode()){
      /// detection d'un nouveau schema dans le Wizard
      $currentversion = \Seolan\Core\DbIni::getStatic($this->_moid.'version','val');
      $this->versionInstalled = $currentversion;

      if( $currentversion != \Seolan\Module\WebResa\Wizard::version){
        setSessionVar('message', '\Seolan\Module\WebResa\WebResa (moid:'.$this->_moid.') : Mise à jour disponible '.$currentversion." => ".\Seolan\Module\WebResa\Wizard::version);
        $this->needUpdate = true;
      }
    }
    */
    \Seolan\Core\Labels::loadLabels('Seolan_Module_WebResa_WebResa');
  }
  /**
   * Initialisation des propriétés
   */
  public function initOptions() {
    $genlabel=\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','general');
    \Seolan\Core\Labels::loadLabels('Seolan_Module_WebResa_WebResa');
    $labels = \Seolan\Core\Labels::$LABELS['\Seolan\Module\WebResa\WebResa'];

    $configgrp = $labels['configgroup'];
    $configgrpasso = $labels['configgroupasso'];
      

    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','version'),'versionInstalled','text',array('compulsory'=>true),NULL,$genlabel);
    $this->_options->setRO('versionInstalled');
    $this->_options->setOpt($labels['iptodeclare'],'ipsortante','text',array('compulsory'=>true),NULL,$genlabel);

    parent::initOptions();
    $this->_options->setOpt($labels['tableprefix'], 'prefix', 'text',NULL,NULL,$configgrp);
    $this->_options->setRO('prefix');
    $this->_options->setOpt($labels['fluxurl'], 'fluxurl', 'text',array('size'=>100),'http://webresafeed.webresa.fr/rss/getWebResaFlow.aspx?key=CODEFLUX&id=AGENCE&dest=SITE',$configgrp);
    $this->_options->setOpt($labels['agenceid'], 'agenceid', 'text',NULL,NULL,$configgrp);
    $this->_options->setOpt($labels['codeflux'], 'codeflux', 'list',array('labels'=>array($labels['codefluxall'],$labels['codefluxsite'],$labels['codefluxsiteextended']),'values'=>array('all_etendu','all_etendu_export','all_dates_etendu_export')),'all_etendu',$configgrp);
    $this->_options->setOpt($labels['codesite'], 'codesite', 'text',NULL,'',$configgrp);
    $this->_options->setOpt($labels['useiframe'], 'onlinebooking', 'boolean',NULL,'',$configgrp);
    $this->_options->setOpt($labels['importoptionstarifs'], 'importoptionstarifs', 'boolean',NULL,'',$configgrp);
    $this->_options->setOpt($labels['moduleagence'], 'moidagences', 'module',NULL,'',$configgrpasso);
    $this->_options->setOpt($labels['moduleattributs'], 'moidattributs', 'module',NULL,'',$configgrpasso);
    $this->_options->setOpt($labels['modulepays'], 'moidpays', 'module',NULL,'',$configgrpasso);
    $this->_options->setOpt('Invalider les treks sans départ à venir', 'purgeoldtreks', 'boolean',NULL,'',$configgrp);
    $this->_options->setOpt('Invalider les treks sans départ', 'purgeoldtreksdepart', 'boolean',NULL,'',$configgrp);

    $this->_options->setOpt($labels['onlinebookingurl'], 'onlinebookingurl', 'text',array('size'=>100),'',$configgrp);
    $this->_options->setOpt($labels['noimportimages'], 'noimportimages', 'boolean',NULL,'',$configgrp);
  }

  function secGroups($function, $group=NULL) {
    $g=array();
    $g['updateSchema'] = array('admin');
    $g['importFluxManual'] = array('admin');
    $g['importFluxCron'] = array('admin');
    $g['deleteDeparts'] = array('admin');
    // 
    $g['getTrekFromCache'] = array('admin');
    $g['getTrekToScreen'] = array('admin');
    // 
    $g['preManualImportTrek'] = array('admin');
    $g['procManualImportTrek'] = array('admin');
    $g['forceImport'] = array('rwv', 'admin');

    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /**
   * liste des actions du browse
   */
  function al_browse(&$my){
    parent::al_browse($my);
    $moid=$this->_moid;
    if($this->secure('', 'forceImport')){
      $o1=new \Seolan\Core\Module\Action($this,
			    'manualupdate',
			    'Forcer l\'import',
			    '&moid='.$this->_moid.'&_function=forceImport&template=Core.empty.html&tplentry=br', 
			    'more');
      $o1->menuable = true;
      $o1->containable = false;
      $my['manualupdate']=$o1;
    }
  }
  /// Listes des actions générales du module
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my,$alfunction);
    $myclass=get_class($this);
    $myoid=@$_REQUEST['oid'];
    if(!empty($myoid) && $this->secure('', 'preManualImportTrek')){
      $o1=new \Seolan\Core\Module\Action($this,'manualupdate','Chargement manuel',
			    '&moid='.$this->_moid.'&_function=preManualImportTrek&template=Module/WebResa.manualupdate.html&tplentry=raw&oid='.$myoid, 'more');
      $o1->menuable = true;
      $o1->containable = false;
      $my['manualupdate']=$o1;
    }
    // mise a jour des tables
    if($this->secure('','updateSchema') ){
      $o1=new \Seolan\Core\Module\Action($this,'updateSchema','Mettre à jour les tables & modules',
			    '&moid='.$this->_moid.'&_function=updateSchema&template=Core.message.html&_nohistory=1');
      $o1->needsconfirm=true;
      $my['updateSchema']=$o1;
    }
    if($this->secure('','deleteDeparts') ){
      $o1=new \Seolan\Core\Module\Action($this,'updateSchema','Vider Depart/Tarifs/Options',
			    '&moid='.$this->_moid.'&_function=deleteDeparts&template=Core.message.html&_nohistory=1');
      $o1->needsconfirm=true;
      $my['updateSchema']=$o1;
    }
  }
  function deleteDeparts($ar){
    $sel = 'TRUNCATE '.$this->prefix.'OPTIONS; ';
    $o = getDB()->execute($sel);
    $sel = 'TRUNCATE '.$this->prefix.'TARIFS; ';
    $t = getDB()->execute($sel);
    $sel = 'TRUNCATE '.$this->prefix.'DEPARTS; ';
    $d = getDB()->execute($sel);
    \Seolan\Core\Shell::toScreen2('','message','Options, tarifs et depart vider ');

  }
  /**
   Methode permettant la surcharge avant insertion ou maj d'une rando 
   $rando : tableau qui sera fournit au procEdit pour insertion ou Maj. Les valeurs sont déja rempli au moment de l'appel de la fonction
   $xpath : DOMXPath sur le flux global
   $trek : DOMNode du circuit dans le flux global
   */
  protected function preInsertUpdateRando(&$rando,$xpath,$trek,&$message){
    //          $rando['libelle'] = $xpath->query('libelle',$trek)->item(0)->nodeValue;

  }
  /**
   Methode permettant la surcharge avant maj du complement d'une rando (fiche technique) 
   $randocpl : tableau qui sera fournit au procEdit pour insertion ou Maj. Les valeurs sont déja rempli au moment de l'appel de la fonction
   $xpathft : DOMXPath sur la fiche technique
   */
  protected function preInsertUpdateRandoCpl(&$randocpl,$xpathft,&$message){
    //            $randocpl['auteur'] = $xpathft->query('/fiche/header/auteur')->item(0)->nodeValue;

  }
  /**
   fonction post import permettant la surcharge par les classes filles
  **/
  function post_importFlux(&$message,$resinsert){
  }
  

  /**
   * comparaison du flux et de la base 
   */
  function checkTreks($ar=NULL){
    if (isset($ar['cachefilename'])){
      $cachefilename = $ar['cachefilename'];
    } else {
      $cachefilename = 'total_flux_check';
    }
    $fluxurl = $this->constructFluxUrl('all_etendu_export'); 
    list($mainFluxStream, $dateflux) = $this->_getFluxCache($fluxurl, $cachefilename);
    $dom = new \DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->loadXML($mainFluxStream);
    $xpath = new \DOMXpath($dom);
    
    // recherche des treks 
    $treks = $xpath->query('//agences/agence/treks/trek');
    $trekids = array();
    
    foreach($treks as $trek){
      $trekids[] = $trek->attributes->getNamedItem("id")->nodeValue;
    }
    // trek totaux en base et diff avec la liste du flux
    $nb0 = getDB()->fetchCol('select trek_id from '.$this->table.' where LANG="'.TZR_DEFAULT_LANG.'"');
    $nbn = array_diff($trekids, $nb0);
    $mess = count($nb0).' treks in base and '.count($nbn).' new trek(s). ';
    // treks de la base présents dans le flux
    $nb1 = getDB()->fetchAll('select koid, trek_id from '.$this->table.' where LANG="'.TZR_DEFAULT_LANG.'" and trek_id in ("'.implode('","', $trekids).'")');
    // treks de la base absents du flux
    $nb2 = getDB()->fetchAll('select koid, trek_id from '.$this->table.' where LANG="'.TZR_DEFAULT_LANG.'" and trek_id not in ("'.implode('","', $trekids).'")');
    // trek sans mises à jour
    $nb3 = getDB()->fetchAll('select koid, trek_id from '.$this->table.' where LANG="'.TZR_DEFAULT_LANG.'" and nomaj=1');
    
    
    $mess .= count($trekids).' treks in xml flux, of wich '.count($nb1).' in base.'.count($nb2).' trek in base not in flux.'.count($nb3).' treks nomaj';

    // trek sans départs 
    $nb4 = getDB()->count('select WR1_TREKS.koid from WR1_TREKS where WR1_TREKS.LANG="FR" and WR1_TREKS.PUBLISH=1 and not exists(select 1 from WR1_DEPARTS where WR1_DEPARTS.treks_id = WR1_TREKS.koid)',array(),true);
    $nb5 = getDB()->count('select WR1_TREKS.koid from WR1_TREKS where WR1_TREKS.LANG="FR" and WR1_TREKS.PUBLISH=1 and not exists(select 1 from WR1_DEPARTS where WR1_DEPARTS.treks_id = WR1_TREKS.koid and WR1_DEPARTS.date_depart>=now() and WR1_DEPARTS.PUBLISH=1) and exists (select 1 from WR1_DEPARTS where WR1_DEPARTS.treks_id = WR1_TREKS.koid and WR1_DEPARTS.PUBLISH=1)',array(),true);
    $mess .= "\n $nb4 treks (valides) sans départ. $nb5 treks (valides)  n'ayant plus de départ(valide) à venir";
  
    return $mess;
  }
  /**
   * purge des treks qui ne sont plus dans le flux
   */
  function purgeTreks($ar){
    ini_set('max_execution_time',1500); /// le traitement peux être long ...
    try{
      $fluxurl = $this->constructFluxUrl('all_etendu_export'); 
      list($mainFluxStream, $dateflux) = $this->_getFluxCache($fluxurl, 'total_flux_for_purge');
      ///construction du dom
      $dom = new \DOMDocument();
      $dom->preserveWhiteSpace = false;
      $dom->loadXML($mainFluxStream);
      $xpath = new \DOMXpath($dom);

      // recherche des treks transmis
      $treks = $xpath->query('/agences/agence/treks/trek');
      $trekids = array();

      foreach($treks as $trek){
	$trekids[] = $trek->attributes->getNamedItem("id")->nodeValue;
      }
      if (count($trekids) == 0){
	$mess .= "\nno trek in flux, purge aborted\n";
	return $mess;
      }

      $mess = $this->checkTreks(array('cachefilename'=>'total_flux_for_purge'));

      $mess .= "\n";
      // treks de la base absents du flux
      if ($this->xset->fieldExists('PUBLISH')){
	 $rstreks = getDB()->fetchAll('select * from '.$this->table.' where PUBLISH=1 and LANG="'.TZR_DEFAULT_LANG.'" and trek_id not in ("'.implode('","', $trekids).'")');
      } else {
	$rstreks = getDB()->fetchAll('select * from '.$this->table.' where LANG="'.TZR_DEFAULT_LANG.'" and trek_id not in ("'.implode('","', $trekids).'")');
      }

      foreach($rstreks as $trekors){
	$this->purgeTrek($trekors);
      }
      
      return $mess;

    } catch(\Exception $ex){
      \Seolan\Core\Logs::critical('Webresa synchro purge',$message);
      return 'Error in purge found:'.$message.$ex->getMessage();
    }
  }
  /**
   * purge d'un trek
   * @param array : KOID, trek_id, ...
   */
  function purgeTrek($ors){
    // est-ce un flux local ? est-ce une duplication ?
    // si non, on depublie (dans cette version)
    if ($ors['nomaj'] == 1){
      return 'skip local '.$ors['trek_id'];
    }
    
    $this->_unpublishRando($ors['KOID']);
    return 'purge trek '.$ors['trek_id'];

  }
  /**
   * invalide un trek en fonction des departs
   */
  function checkDeparts2($trekoid){
    $tabletreks = $this->table;
    $xmoddate = \Seolan\Core\Module\Module::objectFactory($this->ssmod3);
    $tabledeparts = $xmoddate->table;

    $rstrek = getDB()->fetchAll('select * from '.$tabletreks.' where KOID="'.$trekoid.'" and '.$tabletreks.'.LANG="'.TZR_DEFAULT_LANG.'" and '.$tabletreks.'.PUBLISH=1 and not exists(select 1 from '.$tabledeparts.' where '.$tabledeparts.'.treks_id = '.$tabletreks.'.koid)');

    if (count($rstrek) == 0){
      return;
    }

    $ors = $rstrek[0];
    if ($ors['nomaj'] == 1){
      return 'skiped';
    }
    $this->_unpublishRando($trekoid);
    return 'unpublished ';
  }
  /**
   * invalide un trek en fonction des departs restants (optionnel, par defaut non)
   */
  function checkDeparts1($trekoid){
    
    $tabletreks = $this->table;
    $xmoddate = \Seolan\Core\Module\Module::objectFactory($this->ssmod3);
    $tabledeparts = $xmoddate->table;
    $rstrek = getDB()->fetchAll('select * from '.$tabletreks.' where KOID="'.$trekoid.'" and '.$tabletreks.'.LANG="'.TZR_DEFAULT_LANG.'" and '.$tabletreks.'.PUBLISH=1 and not exists(select 1 from '.$tabledeparts.' where '.$tabledeparts.'.treks_id = '.$tabletreks.'.koid and '.$tabledeparts.'.date_depart>="'.date('Y-m-d').'" and '.$tabledeparts.'.PUBLISH=1) and exists (select 1 from '.$tabledeparts.' where '.$tabledeparts.'.treks_id='.$tabletreks.'.koid)');
    if (count($rstrek) == 0){
      return;
    }
    $ors = $rstrek[0];
    if ($ors['nomaj'] == 1){
      return 'skiped';
    }
    $this->_unpublishRando($trekoid);
    return 'unpublished ';
  }
  /**
   * mise a jour manuelle d'un trek
   */
  function procManualImportTrek($ar){
    $p = new \Seolan\Core\Param($ar, array('updatefic'=>0, 'updatetarifs'=>1));
    $general = $p->get('mainflux');
    $optionsflux = $p->get('optionsflux');
    $oid = $p->get('oid');
    if (empty($oid)){
      \Seolan\Core\Shell::setNext($this->getMainAction());
      return;
    }
    $ar2 = array('_options'=>array('local'=>1),
		 'mainflux'=>$general,
		 'trekoid'=>$oid,
		 'optionsflux'=>$optionsflux);
    $message = $this->importFlux($ar2);
    
    \Seolan\Core\Logs::update('update', $oid, 'import manuel');
    setSessionVar('message', $message);
    \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true).'&moid='.$this->_moid.'&function=display&tplentry=br&template=Module/Table.view.html&oid='.$oid);
  }
  /**
   * preparation de la mise a jour manuelle 
   * lecture des flux et affichage pour confirmation
   * @param string oid via $ar
   * @return fluxs : general, tarifs/options
   */
  function preManualImportTrek($ar){
    $p = new \Seolan\Core\Param($ar, array('tplentry'=>'raw'));
    $oid = $p->get('oid');
    $tpl = $p->get('tplentry');
    $force = $p->get('force');
    $lf = ($force == 1)?1:NULL;
    $res = array('oid'=>$oid);

    $ors = getDB()->select('select webresa_id, trek_id, webresa_num from '.$this->table.' where LANG="'.TZR_DEFAULT_LANG.'" and KOID="'.$oid.'"')->fetch();
    $res['dtrek'] = $this->xset->rdisplay($oid);
    $dom1 = new \DOMDocument();
    $dom2 = new \DOMDocument();

    $res['ors'] = $ors;

    // flux general
    $flux = 'all_etendu_export';
    //    $flux='all_dates_etendu_export';
    $fluxurl = $this->constructFluxUrl($flux, $ors['webresa_num']); 
    list($content, $date) = $this->_getFluxCache($fluxurl, $ors['webresa_num'].'-'.$flux, $lf); 
    $dom1->loadXML($content);
    $dom1->formatOutput = true;

    $res['general'] = $dom1->saveXML();
    $res['general_date'] = $date;
    $res['general_url'] = $fluxurl;
    if($this->importoptionstarifs){
      // flux dates et options
      $flux = 'all_dates_tarifs';
      $fluxurl = $this->constructFluxUrl($flux, $ors['webresa_num']); 
      list($content, $date) = $this->_getFluxCache($fluxurl, $ors['webresa_num'].'-'.$flux, $lf); 
      $dom2->loadXML($content);
      $dom2->formatOutput = true;
      $res['tarifs'] = $dom2->saveXML();
      $res['tarifs_date'] = $date;
      $res['tarifs_url'] = $fluxurl;
    }
    return \Seolan\Core\Shell::toScreen1($tpl, $res);
  }
  function getUIFunctionList() {
    $r = parent::getUIFunctionList();
    $r['reservation'] = 'Iframe de reservation';

    return $r;
  }

  function UIEdit_reservation($ar) {
  }
  function UIProcEdit_reservation($ar) {
  }
  function UIView_reservation($ar) {
    $ret = array('onlinebookingurl'=>$this->onlinebookingurl);
    return $ret;
  }

  public function &UIEdit_procQuery($ar=NULL){
    if( empty($ar['__selectedfields']) ) $ar['__selectedfields'] = $this->defaultSelectedFields; 
    return parent::UIEdit_procQuery($ar);
  }
  public function &UIView_procQuery($ar=NULL){
    if( empty($ar['__selectedfields']) ) $ar['__selectedfields'] = implode('||',$this->defaultSelectedFields); 

    if(isset($_REQUEST['date_depart'])){
      $this->submodsearch=true;
      $ar['_ssmodsearch'.$this->ssmod3]=array(
        'date_depart'=>$_REQUEST['date_depart'],
        'date_depart_FMT'=>$_REQUEST['date_depart_FMT'],
        'date_depart_op'=>(!empty($_REQUEST['date_depart_flexi'])?array('flexi',$_REQUEST['date_depart_flexi']):'')
      );
    }
    $ret=parent::UIView_procQuery($ar);

    //ajout des média
    foreach($ret['lines_oid'] as $key => $oid){
      $ret['lines_ssmod'][$key] = array('oid'=>$oid);
      $ars = array('ssmoid'=>$this->ssmod1);
      $this->setSubModules($ars, $ret['lines_ssmod'][$key]); 
    }
    return $ret;
  }
  public function UIView_display($params) {
    if($this->onlinebooking){
      $params['__selectedfields'] .= '||urlIframeDate||url2IframeDate';
    }

    $ret = parent::UIView_display($params);
    $ret['onlinebookingurl'] = $this->onlinebookingurl;
    return $ret;
  }
  public function &UIParam_query($ar=NULL){
    $fs=parent::UIParam_query($ar);

    $grp=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','result','text');
    $fs['__searchondate']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__searchondate','FTYPE'=>'\Seolan\Field\Boolean\Boolean','MULTIVALUED'=>0,
                                                                 'COMPULSORY'=>false,
                                                                 'LABEL'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_WebResa_WebResa','uiquery_searchondate','text')));
    $fs['__searchondate']->default=2;
    $fs['__searchondate']->fgroup=$grp;
    $fs['__searchondate_flexi']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__searchondate_flexi','FTYPE'=>'\Seolan\Field\Real\Real','MULTIVALUED'=>0,
                                                                       'COMPULSORY'=>false,
                                                                       'LABEL'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_WebResa_WebResa','uiquery_searchondate_flexi','text')));
    $fs['__searchondate_flexi']->decimal=0;
    $fs['__searchondate_flexi']->default=0;
    $fs['__searchondate_flexi']->fgroup=$grp;

    return $fs;
  }
  public function &UIView_query($ar=NULL){
    $ret=parent::UIView_query($ar);
    $xmoddate=\Seolan\Core\Module\Module::objectFactory($this->ssmod3);
    $xmoddate->xset->desc['date_depart']->queryable=true;
    $xmoddate->xset->desc['date_depart']->query_format='noop';
    $q=$xmoddate->query(array('selectedfields'=>array('date_depart'),'tplentry'=>TZR_RETURN_DATA));
    $ret['date_depart']=$q['odate_depart'];
    return $ret;
  }

  /**
   * Functions utilisées pour la synchronisation des données
   *
   *
   *
   */
  
  /**
   * Construit l'url du flux a partir des paramètre du module
   * @TODO : le numero=trek n'est pas "valable" pour tous les flux ?
   */
  public function constructFluxUrl($codeflux=NULL,$trek=NULL){
    if($codeflux == NULL) $codeflux = $this->codeflux;
    if($this->fluxurl){
      $ret = str_replace(array('CODEFLUX','AGENCE','SITE'),array($codeflux,$this->agenceid,$this->codesite),$this->fluxurl); 
    }
    if($trek)
      $ret .= '&numero='.$trek;
    return $ret;
  }
  /**
   * retourne un flux et gère un cache
   */
  function _getFluxCache($url, $filecachename, $lf=NULL){
    $date = date('Y-m-d h:i:s');
    if ($lf == NULL){
      if (defined('TZR_WR_CACHE_LF'))
	$lf = TZR_WR_CACHE_LF;
      else
	$lf = 0;
    } 
    $filecachename = TZR_TMP_DIR.$filecachename;
    if (!file_exists($filecachename)){
      $age = $lf+1;
    } else {
      $file_date = filectime($filecachename);
      $age = (time()-$file_date); 
      $date = date('Y-m-d h:i:s', $file_date);
    }
    if($age > $lf) {
      \Seolan\Core\Logs::debug(get_class($this).'::_getFluxCache '.$filecachename.' load');
      $contents = $this->_getFlux($url);
      if ($contents){
	file_put_contents($filecachename, $contents);
      } else {
	unlink($filecachename);
      }
    } else {
      \Seolan\Core\Logs::debug(get_class($this).'::_getFluxCache '.$filecachename.' in cache');
      $contents = file_get_contents($filecachename);
    }
    return array($contents, $date);
  }
  /**
   * Retourne le contenu de l'url passé en paramètre
   * @return le contenu du flux ou False en cas d'erreur 
   */
  private function _getFlux($url){
    if(!$url) return false;
    $default_opts = array('http'=>array('method'=>"GET",
					'timeout'=>80,
					'header'=>"Accept-language: en\r\n" .
					"Cookie: foo=bar")
                          );
    /// utiliser l'ip sortante configurer
    if($this->ipsortante)
      $default_opts['socket'] = array(
                                       'bindto' => $this->ipsortante.':0'
                                       );
    
    $context = stream_context_create($default_opts);
    $ret = file_get_contents($url,false,$context);
    $this->http_response_header = $http_response_header;
    return $ret;
    //pour analyser les header en retour : $http_response_header
  }
  /**
   * Controle que l'url existe et n'est pas vide (lenght>0)
   * @return l'url ou False en cas d'erreur 
   */
  private function _getHttpRealFile($url){
    if(empty($url)) return false;
    $parseurl = parse_url($url);
    $parseurl['path'] = dirname($parseurl['path']).'/'.rawurlencode(basename($parseurl['path']));
    $url = $parseurl['scheme'].'://'.$parseurl['host'].$parseurl['path'];
    if($parseurl['query'])
      $url .= '?'.$parseurl['query'];
    if($url === FALSE) return false;

    $default_opts = array('http'=>array('method'=>"GET",
					'timeout'=>5,
                                        'header'=>"Accept-language: en\r\n" .
					"Cookie: foo=bar"));
    if($this->ipsortante)
      $default_opts['socket'] = array(
                                       'bindto' => $this->ipsortante.':0'
                                       );

    $default = stream_context_create($default_opts);
    $header = get_headers($url,1);

    if(preg_match("/HTTP\/.* 301/" ,$header[0]) && $header['Location'] ) {
      return $this->_getHttpRealFile($header['Location']);
    }
    
    if(preg_match("/HTTP\/.* 200/" ,$header[0]) && $header['Content-Length'] > 0)
      return $url;
    else return false;
  }
  
  /**
   * récupère l'oid d'une agence a partir de son code
   * si l'agence n'existe pas on la crée validé par défaut
   * si elle existe mais non validé on retourne false
   * $code : code webresa de l'agence
   * $nom : libelle
   * $date : date de création du flux chez webresa
   * \return l'oid si trouvé false si non valide
   */
  private function _getAgenceOid($code,$nom,$date){
    $xmodagence = \Seolan\Core\Module\Module::objectFactory($this->moidagences);
    $rs = getDB()->fetchRow('SELECT * FROM '.$xmodagence->table.' WHERE code=\''.$code.'\'' );
    if($rs && $rs['KOID'] ){
      return ($rs['PUBLISH'] == 1)?$rs['KOID']:false;
    }else{
      $ins = array();
      $ins['code'] = $code; 
      $ins['libelle'] = $nom; 
      $ins['date_crea'] = $date; 
      $ins['PUBLISH'] = 1; 
      $insert = $xmodagence->procInsert($ins);
      return $insert['oid'];
    }
  }

  /**
   * récupère l'oid du pays
   * si le pays n'existe pas on le crée
   * sinon on ajoute l'agence au pays
   * si il existe mais non validé on retourne false
   * $nom : libelle
   * $agence : oid de l'agence
   * \return l'oid si trouvé false si non valide
   */
  private function _getPaysOid($nom,$agence){
    if(empty($nom)) return false;
    $xmodpays = \Seolan\Core\Module\Module::objectFactory($this->moidpays);
    $cond = array( 'libelle'=>array('=',$nom) );
    $res = $xmodpays->browse(array('selectedfields'=>'all','tplentry'=>TZR_RETURN_DATA,'cond'=>$cond));
    if(count($res['lines_oid'])> 0 ){
      $agences = $res['lines_oagences'][0]->oidcollection;
      $agences[] = $agence;
      $xmodpays->procEdit(array(
        'oid' => $res['lines_oid'][0],
        'agences' => array_unique($agences),
        '_nolog' => true
      ));
      $ret = ($res['lines_oPUBLISH'][0]->raw==1)?$res['lines_oid'][0]:false;
      unset($xmodpays);
      return $ret;
    }elseif(count($res['lines_oid']) == 0){
      $insert = $xmodpays->procInsert(array('libelle'=>$nom,'agences'=>$agence,'PUBLISH'=>1));
      unset($xmodpays);
      return $insert['oid'];
    }
  }
  /**
   * Récupère l'oid de la rando à partir du trek_id
   */
  private function _getRando($trek_id){
    $sel = 'SELECT KOID FROM '.$this->table.' WHERE trek_id =\''.$trek_id.'\' AND LANG=\''.TZR_DEFAULT_LANG.'\'';
    $rs = getDB()->fetchRow($sel);
    if($rs){
      return $this->display(array('oid'=>$rs['KOID'],'tplentry'=>TZR_RETURN_DATA));
    }else return false;
  }
  /** insert/met a jour une rando a partir de son trek_id
   * $ar : tableau à insérer
   * return oid si mise à jour ou inséré
   * false sinon
  */
  private function _insertUpdateRando($ar){
    foreach ($ar as $key => $value)
    {
        if($value==null) $ar[$key]="";
    }
    $ar['_local'] = 1;
    $ar['_updateifexists'] = true;
    $ar['_unique'] = array('trek_id');
    $ar['_nolog'] = true;
    return $this->xset->procInput($ar);
  }
  /**
   * insere/met à jour une option pour une date de depart
   */
  private function _insertUpdateOption($ar, $modoptions){
    // pas de mise à jour des tarifs 'locaux'

    // ajout ou mise à jour
    $ar['_local'] = 1;
    $ar['_updateifexists'] = true;
    $ar['_unique'] = array('depart_id','libelle');
    $ar['_nolog'] = true;
    return $modoptions->xset->procInput($ar);
  }
  /**
   * insere/met à jour un tarif pour une date de départ
   */
  private function _insertUpdateTarif($ar, $modtarifs){
    // pas de mise à jour des tarifs 'locaux'

    // ajout ou mise à jour 
    $ar['_local'] = 1;
    $ar['_updateifexists'] = true;
    $ar['_unique'] = array('depart_id','libelle');
    $ar['_nolog'] = true;
    return $modtarifs->xset->procInput($ar);
  }
  /** insert/met a jour une date de départ d'un séjour
   * $ar : tableau à insérer
   * $ar['oid'] renseigné si depart deja connu en base
   * return oid si mise à jour ou inséré
   * false sinon
  */
  private function _insertUpdateDepart($ar){
    $xmoddate = \Seolan\Core\Module\Module::objectFactory($this->ssmod3);
    if(empty($xmoddate))
      return array('message'=>'Module Départ ('.$this->ssmod3.') inexistant');
    $ar['_local'] = 1;
    $ar['_nolog'] = true;
    if (isset($ar['oid'])){
      return $xmoddate->xset->procEdit($ar);
    } else {
      return $xmoddate->xset->procInput($ar);
    }
  }
  /** insert/met a jour un jour du programme d'un séjour
   * $ar : tableau à insérer
   * return oid si mise à jour ou inséré
   * false sinon
  */
  private function _insertUpdateProgramme($ar){
    $xmoddate = \Seolan\Core\Module\Module::objectFactory($this->ssmod4);
    if(empty($xmoddate))
      return array('message'=>'Module Programme ('.$this->ssmod4.') inexistant');
    $ar['_local'] = 1;
    $ar['_updateifexists'] = true;
    $ar['_unique'] = array('treks_id','webresa_id');
    $ar['_nolog'] = true;
    return $xmoddate->xset->procInput($ar);
  }
  /**
   * nettoie le programme
   * supprime pour un circuit les jours provenant de webresa qui ne sont plus dans le flux
   *
   */
  private function _cleanProgramme($trekoid,$oids){
    $xmodp = \Seolan\Core\Module\Module::objectFactory($this->ssmod4);
    if(empty($xmodp))
      return false;
    $sel = 'DELETE FROM '. $xmodp->table .
      ' WHERE treks_id = \''. $trekoid .'\' AND (webresa_id <> \'\' OR webresa_id IS NOT NULL) AND KOID NOT IN (\''.implode("','",$oids).'\')';
    getDB()->select($sel);
  }
  /** insert/met a jour une rubrique ou fiche pratique
   * $ar : tableau à insérer
   * return oid si mise à jour ou inséré
   * false sinon
  */
  private function _insertUpdateRubrique($ar,$mod=5){
    $nummod = 'ssmod'.$mod;  
    $xmoddate = \Seolan\Core\Module\Module::objectFactory($this->$nummod);
    if(empty($xmoddate))
      return array('message'=>'Sous Module '.$mod.' ('.$this->$nummod.') inexistant');

    $ar['_local'] = 1;
    $ar['_updateifexists'] = true;
    $ar['_unique'] = array('treks_id','webresa_id');
    $ar['_nolog'] = true;
    return $xmoddate->xset->procInput($ar);
  }
  /**
   * nettoie les rubriques
   *
   */
  private function _cleanRubriques($trekoid,$oids){
    $xmodp = \Seolan\Core\Module\Module::objectFactory($this->ssmod5);
    if(empty($xmodp))
      return false;
    $sel = 'DELETE FROM '. $xmodp->table .
      ' WHERE treks_id = \''. $trekoid .'\' AND (webresa_id <> \'\' OR webresa_id IS NOT NULL) AND KOID NOT IN (\''.implode("','",$oids).'\')';
    getDB()->select($sel);
    $xmodp = \Seolan\Core\Module\Module::objectFactory($this->ssmod6);
    $sel = 'DELETE FROM '. $xmodp->table .
      ' WHERE treks_id = \''. $trekoid .'\' AND (webresa_id <> \'\' OR webresa_id IS NOT NULL) AND KOID NOT IN (\''.implode("','",$oids).'\')';
    getDB()->select($sel);
  }
  
  /** invalide une rando
   * $oid
   * return oid si mise à jour ou inséré
   * false sinon
  */
  private function _unpublishRando($oid){
    $ar['_local'] = 1;
    $ar['value'] = 2;
    $ar['oid'] = $oid;
    $ar['_nolog'] = true;
    return $this->xset->publish($ar);
  }
  /**
   * boolean
   */
  function setBoolean($v){
    return strtolower($v); 
  }
  /**
   * mode des options
   */
  function setOptionMode($mode){
    if (in_array($mode, array(1, 2,3))){
      return $mode;
    }
    \Seolan\Core\Logs::critical(get_class($this), '::setOptionMode '.$mode.' inconnue');
    return $mode;
  }
  /** retourne le soid console du mode fournit dans le flux 
   * Vide ou Accompagné => accompagne
   * Liberté => liberte
   * 
  */
  private function _setMode($mode){
    switch ($mode){
    case 'Liberté':
      return 'liberte';
    case '':
    case 'Accompagné':
    default:
      return 'accompagne';
    }
  }
  /** retourne le soid console de l'etat de la date 
   * Vide  => disponible
   * Confirmé => confirme
   * Annulé => annule
   * Complet => complet
   * 
  */
  private function _setEtatDate($etat){
    switch ($etat){
    case 'Complet':
      return 'complet';
    case 'Annulé':
      return 'annule';
    case 'Confirmé':
      return 'confirme';
    case 'Invisible VEL':
      return 'invisible';
    case '':
    default:
      return 'disponible';
    }
  }
  protected function _setAttribut($str,$fieldname,$agence){
    $str = (string) $str;
    if($str == '') return false;
    $tb_typeAttr =  array('type_oid'=>'typer',
                          'niveau_oid'=>'niveau',
                          'niveau_tech_oid'=>'niveau_tech',
                          'hebergements'=>'hebergement',
                          'themes'=>'theme',
                          'activite'=>'activite',
                          'portage'=>'portage'
                          );
    $att_type = $tb_typeAttr[$fieldname];

    $xmodattr = \Seolan\Core\Module\Module::objectFactory($this->moidattributs);
    if(empty($xmodattr))
      return false;
    $cond = array('libelle'=>array('=',$str));
    $cond['att_type'] = array('=',$att_type);

    $res = $xmodattr->browse(array('tplentry'=>TZR_RETURN_DATA,'cond'=>$cond,'selectedfields'=>'all'));
    if(count($res['lines_oid'])> 0 ){
      \Seolan\Core\Logs::debug(get_class($this).'::_setAttribut update '.$str);
      //@TODO ne mettre à jour que si l'agence existe pas dans oidcollection
      $ret = $res['lines_oid'][0];
      $agences = $res['lines_oagence'][0]->oidcollection;
      $agences[] = $agence;
      $xmodattr->xset->procEdit(array('_options'=>array('local'=>1),
        '_nolog' => true, 'oid'=>$res['lines_oid'][0],'agence'=>array_unique($agences)));
      return $ret;
    }elseif(count($res['lines_oid']) == 0){
      \Seolan\Core\Logs::debug(get_class($this).'::_setAttribut create '.$str);
      $res = $xmodattr->xset->procInput(array('_options'=>array('local'=>1),
        '_nolog' => true, 'libelle'=>$str,'agence'=>$agence,'att_type'=>$att_type, 'libelleweb'=>$str));
      return $res['oid']?$res['oid']:false;
    }else{
      \Seolan\Core\Logs::debug(get_class($this).'::_setAttribut '.$str);
    }
  }

  /**
   * Mise à jour des départ pour le trek
   * selon conf. importoptionstarifs on a le flux specif des dates contenant les option et tarifs sinon le flux general
   * $trekoid: KOID du trek
   * $trekdata : données telles que pour l'insert/update d'un trek
   * $departs: DOMNodeList de depart => Flux general ou Flux des dates 
   * $xpath: Xpath du flux general ou flux des dates
   * @return tableau des oids mis à jour ou inséré
   */
  private function _updateDeparts2($trekoid, $trekdata, $departs, DOMXPath $xpath){
    $xmoddate = \Seolan\Core\Module\Module::objectFactory($this->ssmod3);
    if($this->importoptionstarifs){
      // module des tarifs est le premier 
      $xmodtarifs = \Seolan\Core\Module\Module::objectFactory($xmoddate->ssmod1);
      if(empty($xmodtarifs))
	return array('message'=>'Module tarifs ('.$xmoddate->ssmod1.') inexistant');
      // module des options est le deuxieme 
      $xmodoptions = \Seolan\Core\Module\Module::objectFactory($xmoddate->ssmod2);
      if(empty($xmodoptions))
	return array('message'=>'Module options ('.$xmoddate->ssmod2.') inexistant');
    }

    $retOid = array();
    foreach($departs as $depart){
      $date= array();
      $date['treks_id'] = $trekoid;
      $date['PUBLISH'] = 1;
      $date['date_depart'] = $xpath->query('date',$depart)->item(0)->nodeValue;
      $date['date_fin'] = $xpath->query('date_fin_sejour',$depart)->item(0)->nodeValue;

      //recherche du depart en base pour MAJ sinon création
      // en mode liberté : recherche par date de fin seulement, sinon (accompagné) date depart et fin
      // en mode liberte on insere la date de depart mais on ne la met pas à jour
      $ddepart = NULL;
      if ($trekdata['mode'] == self::MODE_LIBERTE && isset($date['date_fin']) && $date['date_fin']!=""){
	$rsc = getDB()->fetchAll('select koid from '.$xmoddate->table.' where LANG="'.TZR_DEFAULT_LANG.'" and treks_id="'.$trekoid.'" and date_fin = "'.$date['date_fin'].'"');
      } elseif (isset($date['date_fin']) && $date['date_fin']!="") {
	$rsc = getDB()->fetchAll('select koid from '.$xmoddate->table.' where LANG="'.TZR_DEFAULT_LANG.'" and treks_id="'.$trekoid.'" and date_fin="'.$date['date_fin'].'" and date_depart="'.$date['date_depart'].'"');
      } else {
        $rsc = getDB()->fetchAll('select koid from '.$xmoddate->table.' where LANG="'.TZR_DEFAULT_LANG.'" and treks_id="'.$trekoid.'" and date_depart="'.$date['date_depart'].'"');
      }
      if (count($rsc) >= 1){
	$ddepart = $xmoddate->xset->rdisplay($rsc[0]['koid']);
	// depart connu en base 
	$date['oid'] = $ddepart['oid'];
	if ($trekdata['mode'] == self::MODE_LIBERTE && $date['date_depart']==date("Y-m-d") && strtotime($ddepart['odate_depart']->raw)<time() && isset($date['date_fin']) && $date['date_fin']!=""){
	  unset($date['date_depart']);
	}
      } 
      //le prix n'ai dispo que dans le flux general
      if(!$this->importoptionstarifs){
	$date['prix'] = $this->_formatPrice($xpath->query('prix',$depart)->item(0)->nodeValue);
	$date['ancien_prix'] = $this->_formatPrice($xpath->query('ancien_prix',$depart)->item(0)->nodeValue);
      }
      $date['etat'] = $this->_setEtatDate($xpath->query('etat',$depart)->item(0)->nodeValue);
      if($date['etat']=='invisible') $date['PUBLISH'] = 2;
      $date['disponibilite'] = $xpath->query('disponibilite',$depart)->item(0)->nodeValue;
      $date['capacite'] = $xpath->query('capacite',$depart)->item(0)->nodeValue;
      $date['resa'] = $xpath->query('resa',$depart)->item(0)->nodeValue;
      $date['place_option'] = $xpath->query('option',$depart)->item(0)->nodeValue;

      // mise a jour de l'etat
      if ($ddepart != NULL && isset($ddepart['onomaje']) && $ddepart['onomaje']->raw == 1){
	unset($date['etat']);
      }
      //maj / insertion
      $res = $this->_insertUpdateDepart($date);
      \Seolan\Core\Logs::notice(get_class($this).'::updateDeparts done '.$res['oid'].' '.$trekoid);

      if ($res['oid']){
	$retOid[] = $res['oid']; 
	//ajout des options et tarifs du départ
	if($this->importoptionstarifs && ($ddepart == NULL || !isset($ddepart['onomajt']) || $ddepart['onomajt']->raw != 1)){
	  
	  \Seolan\Core\Logs::notice(get_class($this).'::import options tarifs : '.$res['oid'].' '.$trekoid);

	  // les differents tarifs de ce depart
	  $detailTarifs = $xpath->query("tarifs/tarif", $depart);
	  unset($departPrix);
	  unset($departAncienPrix);
	  
	  foreach($detailTarifs as $detailTarif){
	    $montant = $this->_formatPrice($xpath->query('montant', $detailTarif)->item(0)->nodeValue);
	    $ancien_montant = $this->_formatPrice($xpath->query('ancien_montant', $detailTarif)->item(0)->nodeValue);
            if(!isset($departPrix) || (($montant<$departPrix || $departPrix==0) && $montant>0)){
	      $departPrix = $montant;
	    }
	    if(!isset($departAncienPrix) || $ancien_montant<$departAncienPrix){
	      $departAncienPrix = $ancien_montant;
	    }

	    $restarif = $this->_insertUpdateTarif(array(
							'libelle'=>$xpath->query('libelle', $detailTarif)->item(0)->nodeValue,
							'montant'=>$montant,
							'ancien_montant'=>$ancien_montant,
							'depart_id'=>$res['oid'],
							'PUBLISH'=>1
							), $xmodtarifs);
	    if (isset($restarif['oid'])){
	      $currenttarifs[] = $restarif['oid'];
	    }
	  } // boucle sur les tarifs
	  // les différentes options de ce depart
	  $departOptions = $xpath->query("options/option", $depart);
	  foreach($departOptions as $departOption){
	    $resoption = $this->_insertUpdateOption(array(
							  'libelle'=>$xpath->query('libelle', $departOption)->item(0)->nodeValue,
							  'montant'=>$this->_formatPrice($xpath->query('montant', $departOption)->item(0)->nodeValue),
							  'obligatoire'=>$this->setBoolean($xpath->query('montant', $departOption)->item(0)->nodeValue),
							  'mode'=>$this->setOptionMode($xpath->query('mode', $departOption)->item(0)->nodeValue),
							  'depart_id'=>$res['oid'],
							  'PUBLISH'=>1
							  ), $xmodoptions);
	    if (isset($resoption['oid'])){
	      $currentoptions[] = $resoption['oid'];
	    } 
	  }// boucle sur les departs
	  
	  // invalidation des options et tarifs qui ne sont plus dans le flux
	  $this->_unpublishSsmodDepart($trekoid, $res['oid'], $currenttarifs, $xmodtarifs);
	  $this->_unpublishSsmodDepart($trekoid, $res['oid'], $currentoptions, $xmodoptions);
	  //Maj du prix mini de l'offre
	  if(isset($departPrix) || isset($departAncienPrix))
	    $resM = $this->_insertUpdateDepart(array('oid'=>$res['oid'],'prix'=>$departPrix,'ancien_prix'=>$departAncienPrix));

	} // si import options tarifs 

      } // depart existant ok ou ajouté ok
    } // boucle sur les départs
    return $retOid;
  }

  /**
   * Mise à jour des départ pour le trek
   * celon conf. les tarifs et les options du depart sont mises à jour en plus de la fiche départ
   * $trekoid: KOID du trek
   * $trekdata : données telles que pour l'insert/update d'un trek
   * $departs: DOMNodeList de depart 
   * $xpath: Xpath du flux general
   * $xpathOptions: XPath sur e flux des options et tarifs du trek (si update options tarifs)
   * @return tableau des oids mis à jour ou inséré
   */
  private function _updateDeparts($trekoid, $trekdata, DOMNodeList &$departs,DOMXPath &$xpath,DOMXPath &$xpathOptions=NULL){
    $xmoddate = \Seolan\Core\Module\Module::objectFactory($this->ssmod3);
    if($this->importoptionstarifs){
      // module des tarifs est le premier 
      $xmodtarifs = \Seolan\Core\Module\Module::objectFactory($xmoddate->ssmod1);
      if(empty($xmodtarifs))
	return array('message'=>'Module tarifs ('.$xmoddate->ssmod1.') inexistant');
      // module des options est le deuxieme 
      $xmodoptions = \Seolan\Core\Module\Module::objectFactory($xmoddate->ssmod2);
      if(empty($xmodoptions))
	return array('message'=>'Module options ('.$xmoddate->ssmod2.') inexistant');
    }
      
    $retOid = array();
    foreach($departs as $depart){
      $date= array();
      $date['treks_id'] = $trekoid;
      $date['PUBLISH'] = 1;
      $date['date_depart'] = $xpath->query('date',$depart)->item(0)->nodeValue;
      $date['date_fin'] = $xpath->query('date_fin_sejour',$depart)->item(0)->nodeValue;

      // en mode liberté : recherche par date de fin seulement, sinon (accompagné) date depart et fin
      // en mode liberte on insere la date de depart mais on ne la met pas à jour
      $ddepart = NULL;
      if ($trekdata['mode'] == self::MODE_LIBERTE){
	$rsc = getDB()->fetchAll('select koid from '.$xmoddate->table.' where LANG="'.TZR_DEFAULT_LANG.'" and treks_id="'.$trekoid.'" and date_fin = "'.$date['date_fin'].'"');
      } else {
	$rsc = getDB()->fetchAll('select koid from '.$xmoddate->table.' where LANG="'.TZR_DEFAULT_LANG.'" and treks_id="'.$trekoid.'" and date_fin="'.$date['date_fin'].'" and date_depart="'.$date['date_depart'].'"');
      }
      if (count($rsc) >= 1){
	$ddepart = $xmoddate->xset->rdisplay($rsc[0]['koid']);
	// depart connu en base 
	$date['oid'] = $ddepart['oid'];
	if ($trekdata['mode'] == self::MODE_LIBERTE){
	  unset($date['date_depart']);
	}
      } 
      // a compter de fin 02/2014 les dates sont toujours renseignées
      /*
      if($xpath->query('date_fin_sejour',$depart)->item(0)->nodeValue){
        $date['date_fin'] = $xpath->query('date_fin_sejour',$depart)->item(0)->nodeValue;
      }
      else{
	// @TODO : pour les accompagné, mais cela devrait changer
        $datedts = strptime($date['date_depart'],"%Y-%m-%d");
        $date['date_fin'] = date('Y-m-d',mktime(12,0,0,$datedts['tm_mon']+1,$datedts['tm_mday']+$rando['NBJOUR']-1,1900+$datedts['tm_year']));
      }
      */
      $date['prix'] = $this->_formatPrice($xpath->query('prix',$depart)->item(0)->nodeValue);
      $date['ancien_prix'] = $this->_formatPrice($xpath->query('ancien_prix',$depart)->item(0)->nodeValue);
      $date['etat'] = $this->_setEtatDate($xpath->query('etat',$depart)->item(0)->nodeValue);
      if($date['etat']=='invisible') $date['PUBLISH'] = 2;
      $date['disponibilite'] = $xpath->query('disponibilite',$depart)->item(0)->nodeValue;
      $date['capacite'] = $xpath->query('capacite',$depart)->item(0)->nodeValue;
      $date['resa'] = $xpath->query('resa',$depart)->item(0)->nodeValue;
      $date['place_option'] = $xpath->query('option',$depart)->item(0)->nodeValue;

      // mise a jour de l'etat
      if ($ddepart != NULL && isset($ddepart['onomaje']) && $ddepart['onomaje']->raw == 1){
	unset($date['etat']);
      }

      $res = $this->_insertUpdateDepart($date);

      \Seolan\Core\Logs::notice(get_class($this).'::updateDeparts done '.$res['oid'].' '.$trekoid);

      if ($res['oid']){
	$retOid[] = $res['oid']; 
	//ajout des options et tarifs du départ
	if($this->importoptionstarifs && ($ddepart == NULL || !isset($ddepart['onomajt']) || $ddepart['onomajt']->raw != 1)){
	  
	  \Seolan\Core\Logs::notice(get_class($this).'::import options tarifs : '.$res['oid'].' '.$trekoid);

	  // rem : selon flux de départ, on a liste des dates à benir ou plus complete donc on traitera pas toujours toutes les dates
	  $detailDeparts = $xpathOptions->query("/offre/departs/depart[date_fin_sejour[text()='".$date['date_fin']."']]");
	  if ($detailDeparts->length == 0){
	    \Seolan\Core\Logs::notice(get_class($this), '::_updateDeparts no tarif/option found for this date '.$date['date_fin'].' trekoid : '.$trekoid);
	  } else {
	    // les differents tarifs de ce depart
	    $detailTarifs = $xpathOptions->query("tarifs/tarif", $detailDeparts->item(0));
	    foreach($detailTarifs as $detailTarif){
	      $restarif = $this->_insertUpdateTarif(array(
							   'libelle'=>$xpathOptions->query('libelle', $detailTarif)->item(0)->nodeValue,
							   'montant'=>$this->_formatPrice($xpathOptions->query('montant', $detailTarif)->item(0)->nodeValue),
							   'ancien_montant'=>$this->_formatPrice($xpathOptions->query('ancien_montant', $detailTarif)->item(0)->nodeValue),
							   'depart_id'=>$res['oid'],
							    'PUBLISH'=>1
							  ), $xmodtarifs);
	      if (isset($restarif['oid'])){
		$currenttarifs[] = $restarif['oid'];
	      }
	    } // boucle sur les tarifs
	    // les différentes options de ce depart
	    $departOptions = $xpathOptions->query("options/option", $detailDeparts->item(0));
	    foreach($departOptions as $departOption){
	      $resoption = $this->_insertUpdateOption(array(
							    'libelle'=>$xpathOptions->query('libelle', $departOption)->item(0)->nodeValue,
							    'montant'=>$this->_formatPrice($xpathOptions->query('montant', $departOption)->item(0)->nodeValue),
							    'obligatoire'=>$this->setBoolean($xpathOptions->query('montant', $departOption)->item(0)->nodeValue),
							    'mode'=>$this->setOptionMode($xpathOptions->query('mode', $departOption)->item(0)->nodeValue),
							    'depart_id'=>$res['oid'],
							    'PUBLISH'=>1
							    ), $xmodoptions);
	      if (isset($resoption['oid'])){
		$currentoptions[] = $resoption['oid'];
	      } 
	    }
	    // invalidation des options et tarifs qui ne sont plus dans le flux
	    $this->_unpublishSsmodDepart($trekoid, $res['oid'], $currenttarifs, $xmodtarifs);
	    $this->_unpublishSsmodDepart($trekoid, $res['oid'], $currentoptions, $xmodoptions);
	  } // fin cas depart trouvé
	} // si import options tarifs 
      } // depart existant ok ou ajouté ok
    } // boucle sur les départs
    return $retOid;
  }
  /**
   * invalide un tarif ou une option (sous modules de departs)
   */
  private function _unpublishSsmodDepart($trekoid, $departoid, $oids, $mod){
    $sel = 'UPDATE '.$mod->table.' SET PUBLISH = 2 WHERE depart_id = \''.$departoid.'\' AND KOID NOT IN (\''.implode("','",$oids).'\') ';
    getDB()->execute($sel);
  }
  /**
   * invalide les element du sous module
   * $trekoid : oid de la rando
   * $oids : tableau des oid à ne pas invalidé
   */
  private function _unpublishSsmod($trekoid,$oids,$modnum=3/* depart */){
    $modkey = 'ssmod'.$modnum;
    if($modnum == 1 || $modnum == 2){ // medias
      $cpl = "AND source <> '' OR source IS NULL ";
    }elseif( $modnum > 3 ){ //chapitre ? cf les parms de la fonction
      $cpl = "AND webresa_id <> '' OR  webresa_id IS NULL ";
    }
    $xmoddate = \Seolan\Core\Module\Module::objectFactory($this->$modkey);
    $sel = 'UPDATE '.$xmoddate->table.' SET PUBLISH = 2 WHERE treks_id = \''.$trekoid.'\' AND KOID NOT IN (\''.implode("','",$oids).'\') '.$cpl;
    getDB()->select($sel);
  }
  /**
   * Format un prix en entier (suppression de la virgule
   *
   */
  private function _formatPrice($str){
    return (float) str_replace(',','.',$str);
  }
  /**
   * Réparation du code html lavant insertion
   */
  function tidyRepair($txt){
    tidyString($txt);
    return $txt;
  }

  /**
   * Ajoute un media a un circuit si elle n'existe pas
   * $trek_id: oid du circuit
   * $url: url de l'image source
   * return oid du media si mise à jour ou inséré
   *        false sinon
   */
  private function _addMediaRando($trek_id, $url, $type, $ordre){
    if(empty($url)) return;
    $url = $this->_getHttpRealFile($url);
    $xmodmedia = \Seolan\Core\Module\Module::objectFactory($this->ssmod1);
    $ar= array();
    $ar['_local'] = 1;
    $ar['_nolog'] = true;
    $ar['_updateifexists']=true;
    $ar['_unique'] = array('trek_id','source');
    $ar['treks_id'] = $trek_id;
    
    $ar['typem'] = $type;
    $ar['ordre'] = $ordre;
    $ar['media'] = $ar['source'] = $url;
    //Est que l'image existe
    $cnt = getDB()->count("select count(KOID) from {$xmodmedia->table} where source = '".$ar['source']."' AND treks_id = '".$ar['treks_id']."'");
    if( $cnt ){
      $ar['PUBLISH'] = 1;
    }
    $ret = $xmodmedia->xset->procInput($ar);
    return $ret['oid'];
  }

  /**
   * Function réalisant l'import des séjours depuis le flux 
   * possibilité de passer un flux préchargé et son flux tarifsoptiosn/dates associés
   * @param string mainflux (via $ar)
   * @param string optionsflux (via $ar)
   * @return string
   * losrque les flux sont passés en paramètres, ils sont considérés comme partiels (=1 trek)
   * -> pas de controle des sejours absents du flux
   */
  function importFlux($ar=NULL) {
    $param = new \Seolan\Core\Param($ar,array('mainflux'=>NULL, 'optionsflux'=>NULL));
    $completeFlux = true;
    $fromcache = $param->get('fromcache');
    $cachefile = $this->cachefile;

    ini_set('max_execution_time',1500); /// le traitement peux être long ...
    try{
      if ($param->is_set('mainflux')){
	$completeFlux = false;
	\Seolan\Core\Logs::notice(get_class($this), '::importFlux from local stream');
	$mainFluxStream = $param->get('mainflux');
      } else {
	/// Récupération du flux global
	if($fromcache == 1 && file_exists($cachefile)){
	  $mainFluxUrl = $cachefile;
	}else{
	  $mainFluxUrl = $this->constructFluxUrl(); 
	}
	$mainFluxUrl = $this->_getHttpRealFile($mainFluxUrl);
	$mainFluxStream = $this->_getFlux($mainFluxUrl); 
	$message .= "flux url $mainFluxUrl\n";
	if(!$mainFluxStream) {
	  throw new \Exception("Flux $mainFluxUrl ne répond pas ");
	}else{
	  \Seolan\Core\DbIni::setStatic($this->_moid.'lastfluxurl',$mainFluxUrl);
	  \Seolan\Core\DbIni::setStatic($this->_moid.'lastfluxdate',date('Y-m-d H:m:s'));
	  \Seolan\Core\DbIni::setStatic($this->_moid.'lastfluxheader',implode("\n",$this->http_response_header));
	}
	if($mainFluxUrl != $cachefile) /// Copier le fichier en local
	  file_put_contents($cachefile,$mainFluxStream);
      }

      ///construction du dom
      $dom = new \DOMDocument();
      $dom->preserveWhiteSpace = false;
      $dom->loadXML($mainFluxStream);
      $xpath = new \DOMXpath($dom);
      
      /// list des agences
      $agences = $xpath->query('//agence');
      $message .= $agences->length." agence(s) found in the xml\n";

      $tb_oidrando = array();///tableau des oids traités

      foreach ($agences as $agence) {
        $codeAgence = $xpath->query('infos/code',$agence)->item(0)->nodeValue;
        $nomAgence = $xpath->query('infos/libelle',$agence)->item(0)->nodeValue;
        $dateAgence = $xpath->query('infos/date',$agence)->item(0)->nodeValue;
        $oidAgence = $this->_getAgenceOid($codeAgence,$nomAgence,$dateAgence);
        if(!$oidAgence){ /// si l'agence n'est pas publié on n'import rien
          $nbcircuit = $treks->length;
          $message .= "Agence $nomAgence ($codeAgence) non trouvée en base ($nbcircuit circuits)\n";
          continue;
        }
        /// parcours des treks de l'agence
        $tb_oidrando[$oidAgence] = array();
        $treks = $xpath->query('treks/trek',$agence);
        $message .= '- '.$nomAgence.' : '.$treks->length.' trek(s) found(s) '."\n";
        foreach ($treks as $trek){
          $rando = array();
          $rando['agences_oid'] = $oidAgence;
          $rando['trek_id'] = $trek->attributes->getNamedItem("id")->nodeValue;
          $disp_rando = $this->_getRando($rando['trek_id']);
          ///si la mise à jour est interdite on ne fait rien 
          if($disp_rando['onomaj']->raw == 1){
	    $message .= $rando['trek_id']." {$disp_rando['onomaj']->fielddef->label} \n";
            $tb_oidrando[$oidAgence][] = $disp_rando['oid'];
            continue;
          }
          /// on vérifie que le pays est validé en console, si non on invalide la rando
          /// si le pays n'est pas remplit dans webresa le circuit ne sera pas remonté
          $oidPays = $this->_getPaysOid(strtolower(($pays = $xpath->query('pays',$trek)->item(0)->nodeValue)),$oidAgence);
          if(!$oidPays ){
	    $message .= "trek id({$rando['trek_id']}) country ($pays) error, skip\n";
            if($disp_rando['oid']) $this->_unpublishRando($disp_rando['oid']);
            continue;
          }
          ///si la rando n'existe pas on met le champ publié dans l'etat initial
          if(!$disp_rando['oid'])
            $rando['PUBLISH'] = $this->defaultispublished?1:2;
	  // si rando existe et invalide : revalider
	  if ($disp_rando['oid'] && $disp_rando['oPUBLISH']->raw == 2){
	    $rando['PUBLISH'] = 1;
	  }
          $rando['pays'] = $oidPays;
          $rando['webresa_id'] = $xpath->query('uid',$trek)->item(0)->nodeValue;
          $rando['webresa_num'] = $xpath->query('numero',$trek)->item(0)->nodeValue;


          $rando['code'] = $xpath->query('code',$trek)->item(0)->nodeValue;
          $rando['href'] = $xpath->query('href',$trek)->item(0)->nodeValue;
          $rando['region'] = $xpath->query('region',$trek)->item(0)->nodeValue;
          $rando['mode'] = $this->_setMode($xpath->query('mode',$trek)->item(0)->nodeValue);
          $rando['libelle'] = $xpath->query('libelle',$trek)->item(0)->nodeValue;


          $rando['duree'] = $xpath->query('duree',$trek)->item(0)->nodeValue;
          $rando['duree_nuit'] = $xpath->query('duree_nuit',$trek)->item(0)->nodeValue;
          $rando['marche'] = $xpath->query('marche',$trek)->item(0)->nodeValue;

          $rando['type_oid'] = $this->_setAttribut($xpath->query('type',$trek)->item(0)->nodeValue,'type_oid',$oidAgence);
          $rando['niveau_oid'] = $this->_setAttribut($xpath->query('niveau',$trek)->item(0)->nodeValue,'niveau_oid',$oidAgence);
          $rando['niveau_tech_oid'] = $this->_setAttribut($xpath->query('niveau_tech',$trek)->item(0)->nodeValue,'niveau_tech_oid',$oidAgence);

          $hebergements = $xpath->query('hebergements/hebergement',$trek);
          $rando['hebergements'] = array();
          foreach($hebergements as $hebergement){
            $rando['hebergements'][] = $this->_setAttribut($hebergement->nodeValue,'hebergements',$oidAgence);
          }
          $rando['hebergements'] = array_unique($rando['hebergements']);
          
          $themes = $xpath->query('themes/theme',$trek);
          $rando['themes'] = array();
          foreach($themes as $theme){
            $rando['themes'][] = $this->_setAttribut($theme->nodeValue,'themes',$oidAgence);
          }
          $rando['themes'] = array_unique($rando['themes']);
         
          $rando['activite'] = $this->_setAttribut($xpath->query('activite',$trek)->item(0)->nodeValue,'activite',$oidAgence);
          $rando['portage'] = $this->_setAttribut($xpath->query('portage',$trek)->item(0)->nodeValue,'portage',$oidAgence);

          $rando['nb_jour_portage'] = $xpath->query('nb_jours_portage',$trek)->item(0)->nodeValue;
          $rando['circuit_exception'] = ($xpath->query('circuit_exception',$trek)->item(0)->nodeValue == 1 || $xpath->query('circuit_exception',$trek)->item(0)->nodeValue == 'oui' )?1:2;
          $rando['ville_depart'] = $xpath->query('ville_depart',$trek)->item(0)->nodeValue;
          $rando['ville_arrivee'] = $xpath->query('ville_arrivee',$trek)->item(0)->nodeValue;

          $rando['geoloc_depart'] = $this->degree2dec($xpath->query('latitude_depart',$trek)->item(0)->nodeValue).";".$this->degree2dec($xpath->query('longitude_depart',$trek)->item(0)->nodeValue);
          $rando['geoloc_arrivee'] = $this->degree2dec($xpath->query('latitude_arrivee',$trek)->item(0)->nodeValue).";".$this->degree2dec($xpath->query('longitude_arrivee',$trek)->item(0)->nodeValue);

          $rando['prix_minimum'] = $this->_formatPrice($xpath->query('prix_minimum',$trek)->item(0)->nodeValue);
          $rando['prix_maximum'] = $this->_formatPrice($xpath->query('prix_maximum',$trek)->item(0)->nodeValue);
          $rando['tel'] = $xpath->query('tel',$trek)->item(0)->nodeValue;
          $rando['commentaire'] = $xpath->query('commentaire',$trek)->item(0)->nodeValue;
          $rando['fiche_technique'] = $xpath->query('fiche_technique',$trek)->item(0)->nodeValue;
	  //appel de la fonction permettant la surcharge
	  $this->preInsertUpdateRando($rando,$xpath,$trek,$message);
          ///insertion ou Mise a jour
          $resinsert = $this->_insertUpdateRando($rando);
          if(!$resinsert['oid']){
            $message .= "\n".$rando['trek_id'].' erreur d\'insertion'.$resinsert;
            continue;
          }else{
            $tb_oidrando[$oidAgence][] = $resinsert['oid'];
          }
          
          ///traitement des images
          $insertedmedia = array();
	  if(!$this->noimportimages){
	    $images = $xpath->query('images/image',$trek);
	    $ordrei = 1;
	    foreach($images as $image){
	      $insertedmedia[] = $this->_addMediaRando($resinsert['oid'], $image->nodeValue, 'image', $ordrei);
	    }
	  }
          ///traitement des pdf
          $pdfs = $xpath->query('pdfs/pdf',$trek);
          $ordrep = 1;
          foreach($pdfs as $pdf){
            $insertedmedia[] = $this->_addMediaRando($resinsert['oid'], $pdf->nodeValue, 'pdf', $ordrep);
          }

          $this->_unpublishSsmod($resinsert['oid'], $insertedmedia ,1);

          ///traitement des dates de departs
          
	  // recuparation des tarifs et options
	  if($this->importoptionstarifs){
	    
	    if ($param->is_set('mainflux') && $param->is_set('optionsflux')){
	      $optionsFluxStream = $param->get('optionsflux');
	    }else{
	      $optionsFluxUrl = $this->constructFluxUrl('all_dates_tarifs',$rando['webresa_num']); 
	      $optionsFluxStream = $this->_getFlux($optionsFluxUrl); 
	    }
	    ///construction du dom
	    $domO = new \DOMDocument();
	    $domO->preserveWhiteSpace = false;
	    $domO->loadXML($optionsFluxStream);
	    $xpathD = new \DOMXpath($domO);
	    $departs = $xpathD->query('departs/depart');
	    
	  }else{
	    $departs = $xpath->query('departs/depart',$trek);
	    $xpathD = $xpath;
	  }
	  
	  //	  $oidsdate = $this->_updateDeparts($resinsert['oid'], $rando, $departs, $xpath, $xpathOptions);
	  $oidsdate = $this->_updateDeparts2($resinsert['oid'], $rando, $departs, $xpathD);
          $this->_unpublishSsmod($resinsert['oid'],$oidsdate,3);
          
          ///traitement fiche technique
          if(!empty($rando['fiche_technique'])){
            $fichetech = new \DOMDocument();
            $fichetech->preserveWhiteSpace = false;

            $fichetech->load($rando['fiche_technique']);
            $xpathft = new \DOMXpath($fichetech);
           
            $randocpl = array();
            $randocpl['trek_id'] = $rando['trek_id'];

            $randocpl['auteur'] = $xpathft->query('/fiche/header/auteur')->item(0)->nodeValue;
            
            $randocpl['datePublication'] = $this->sqlDate( $xpathft->query('/fiche/header/datePublication')->item(0)->nodeValue,"%d/%m/%Y" );
            $randocpl['dateLastModification'] = $this->sqlDate( $xpathft->query('/fiche/header/dateLastModification')->item(0)->nodeValue,"%d/%m/%Y" );
            $randocpl['urlIframeDate'] = $xpathft->query('/fiche/header/urlIframeDates')->item(0)->nodeValue;
            $randocpl['url2IframeDate'] = $xpathft->query('/fiche/header/url2IframeDates')->item(0)->nodeValue;

            $randocpl['resume'] = $xpathft->query('/fiche/contenu/resume')->item(0)->nodeValue;
            $randocpl['complement'] = $xpathft->query("//chapitre[nomChapitre='PROGRAMME']/complementProgramme")->item(0)->nodeValue;
            $randocpl['libelle1'] = $xpathft->query('/fiche/contenu/sousTitre')->item(0)->nodeValue;
            
            $jours = $xpathft->query("//chapitre[nomChapitre='PROGRAMME']/jours/jour");
            $cntjour = 1;
            $idprogramme = array();
            $oidFiches = array();
            foreach($jours as $jour){
              $prog = array();
              $prog['PUBLISH'] = 1;
              $prog['treks_id'] = $resinsert['oid'];
              $prog['ordre'] = $cntjour++;
              $prog['nom_jour'] = $xpathft->query("nomJour",$jour)->item(0)->nodeValue;
              $prog['description'] = $this->tidyRepair($xpathft->query("contenuJour",$jour)->item(0)->nodeValue);
              $idprogramme[] = $prog['webresa_id'] = $jour->attributes->getNamedItem("ID")->nodeValue;;
              $insertProg = $this->_insertUpdateProgramme($prog);
              if($insertProg['oid']) $oidFiches[] = $insertProg['oid'];
            }
            $this->_cleanProgramme($resinsert['oid'],$oidFiches);

            $rubriques = $xpathft->query("//chapitre[nomChapitre='FICHE PRATIQUE']/rubriques/rubrique");
            $cnt = 1;
            $oidFiches = array();
            foreach($rubriques as $rubrique){
              $prog = array();
              $prog['PUBLISH'] = 1;
              $prog['treks_id'] = $resinsert['oid'];
              $prog['ordre'] = $cnt++;
              $prog['libelle'] = $xpathft->query("nomRubrique",$rubrique)->item(0)->nodeValue;
              $prog['description'] = $this->tidyRepair($xpathft->query("contenuRubrique",$rubrique)->item(0)->nodeValue);
              $prog['webresa_id'] = $rubrique->attributes->getNamedItem("ID")->nodeValue;;
              $insertChap = $this->_insertUpdateRubrique($prog,5);
              if($insertChap['oid']) $oidFiches[] = $insertChap['oid'];
            }
            $rubriques = $xpathft->query("//chapitreLibre");
            $cnt = 1;
            foreach($rubriques as $rubrique){
              $prog = array();
              $prog['PUBLISH'] = 1;
              $prog['treks_id'] = $resinsert['oid'];
              $prog['ordre'] = $cnt++;
              $prog['libelle'] = $xpathft->query("nomChapitreLibre",$rubrique)->item(0)->nodeValue;
              $prog['description'] = $this->tidyRepair($xpathft->query("contenuChapitreLibre",$rubrique)->item(0)->nodeValue);
              $prog['webresa_id'] = $rubrique->parentNode->attributes->getNamedItem("ID")->nodeValue;;
              $insertLibre = $this->_insertUpdateRubrique($prog,6);
              if($insertLibre['oid']) $oidFiches[] = $insertLibre['oid'];
            }

            $this->_cleanRubriques($resinsert['oid'],$oidFiches);

	    //appel de la fonction permettant la surcharge
	    $this->preInsertUpdateRandoCpl($randocpl,$xpathft,$message);

            $resinsert = $this->_insertUpdateRando($randocpl);
	  }

	  // status dependant des departs
	  if ($this->purgeoldtreks){
	    $this->checkDeparts1($resinsert['oid']);
	  }
	  if ($this->purgeoldtreksdepart){
	    $this->checkDeparts2($resinsert['oid']);
	  }
	  
	  $this->post_importFlux($message,$resinsert);

          unset($rando);

          if($param->get('getOne'))
            die($message);

        } // treks

      } // agences

      // actions post import
      if ($completeFlux){
	$message .= $this->purgeTreks();
      }

      \Seolan\Core\Logs::notice('Webresa synchro',$message);

      return $message;

    }catch(\Exception $e){
      \Seolan\Core\Logs::critical('Webresa synchro',$message);
      return 'Error found:'.$message.$e->getMessage();
    }
  }
  
  /**
    Appel manuel de la fonction d'import 
    \return message de log a l'ecran
  */
  function importFluxManual($ar=NULL) {
    die(nl2br($this->importFlux($ar)));
  }
  /**
    Appel planifié de la fonction d'import 
    \return message dans la cron
  */
  function importFluxCron(\Seolan\Module\Scheduler\Scheduler &$s, $o, $arraymore) {
    $mess = $this->importFlux($ar);
    
    $s->setStatusJob($o->KOID, 'finished', $mess);
  }
  

  /**
    Affichage du Flux webresa sur un crcuit 
    \return message de log a l'ecran
  */
  function getTrekFromCache($ar=NULL) {
    $ar['fromcache'] = 1;
    return $this->getTrekToScreen($ar);
  }
  
  function getTrekToScreen($ar=NULL) {
    $param = new \Seolan\Core\Param($ar,array());
    $cachefile = $this->cachefile;
    $fromcache = $param->get('fromcache');

    $id = $param->get('trek_id');
    ini_set('max_execution_time',1500); /// le traitement peux être long ...
    try{
      /// Récupération du flux 
      if($fromcache == 1 && file_exists($cachefile)){
        $mainFluxUrl = $cachefile;
      }else{
        $mainFluxUrl = $this->constructFluxUrl(); 
      }

      $mainFluxStream = $this->_getFlux($mainFluxUrl); 
      if(!$mainFluxStream) 
        throw new \Exception("Flux $mainFluxUrl ne répond pas ");

      if($mainFluxUrl != $cachefile) /// Copier le fichier en local
        file_put_contents($cachefile,$mainFluxStream);

      ///construction du dom
      $dom = new \DOMDocument();
      $dom->preserveWhiteSpace = false;
      $dom->loadXML($mainFluxStream);
      $xpath = new \DOMXpath($dom);
      
      $treks = $xpath->query("//trek[@id='$id']");
      foreach($treks as $trek){
        header ("Content-Type:text/xml");  
        echo $trek->C14N();
        $num = $xpath->query('numero',$trek)->item(0)->nodeValue;
	$fluxUrl = $this->constructFluxUrl('all_dates_tarifs',$num); 
	$fluxStream = $this->_getFlux($fluxUrl); 
	die($fluxStream);
	
      } 
      die("Trek $id not found");
    }catch(\Exception $e){
      die('Erreur found:'.$message.$e->message);
    }

  }
  /**
   * Groupe de Functions utilisés par la gestion du module
   * - Mise à jour 
   * - supression
   *
   */
  public function updateSchema($ar){
    $mess = \Seolan\Module\WebResa\Wizard::updateSchema($this->prefix);
    $mess .= \Seolan\Module\WebResa\Wizard::updateProperties($this);
    $mess .= \Seolan\Module\WebResa\Wizard::addTemplates($this);
    \Seolan\Core\DbIni::setStatic($this->_moid.'version',\Seolan\Module\WebResa\Wizard::version);
    \Seolan\Core\Shell::toScreen2('','message',$mess);
  }

  /**
   * Liste des tables utilisées par le module
   */
  public function usedTables() {
    $schematable = \Seolan\Module\WebResa\Wizard::getSchemaTables();
    $tables = array();
    foreach($schematable as $key => $tabledef){
      if ($key>0 && $tabledef[0])
	$tables[] = $this->prefix.$tabledef[0];
    }
    return $tables;
  }
  /**
   * Liste des tables principales du module
   */
  public function usedMainTables() {
    return array($this->table);
  }
  /**
   * Suppression du module et modules associés
   * @todo : lors de la duplication, cloner les sous modules ?
   */
  function delete($ar=NULL) {
    //Module associé
    $modules  = array($this->moidagences,$this->moidattributs,$this->moidpays,$this->ssmod1,$this->ssmod2,$this->ssmod3,$this->ssmod4,$this->ssmod4,$this->ssmod4);

    foreach($modules as $km=>$moid){
      if($moid && \Seolan\Core\Module\Module::moduleExists($moid) ){
        $mod = \Seolan\Core\Module\Module::objectFactory($moid);
	$ar1['withtable'] = false;
	$message .= $mod->delete($ar1)."\n";
	\Seolan\Core\Module\Module::clearCache();
      }
    }
    $message .= parent::delete($ar);
    return \Seolan\Core\Shell::toScreen2($tplentry,'message',$message);
  }
  /**
   * Page d'information du module
   */
  function getInfos($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>TZR_RETURN_DATA));
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=parent::getInfos($ar);
    unset($ret['infos']['size']);
    
    
    $ret['infos']['version']->label = 'Version du schema';
    $currentversion = \Seolan\Core\DbIni::getStatic($this->_moid.'version','val');
    $ret['infos']['version']->html = $currentversion;

    $ret['infos']['lastfluxurl']->label = 'Url du dernier flux analysé';
    $ret['infos']['lastfluxurl']->html = \Seolan\Core\DbIni::getStatic($this->_moid.'lastfluxurl','val');
    $ret['infos']['lastfluxdate']->label = 'Date de la dernière analyse';
    $ret['infos']['lastfluxdate']->html = \Seolan\Core\DbIni::getStatic($this->_moid.'lastfluxdate','val');
    $ret['infos']['lastfluxheader']->label = 'header de la réponse lors de la dernière analyse';
    $ret['infos']['lastfluxheader']->html = nl2br(\Seolan\Core\DbIni::getStatic($this->_moid.'lastfluxheader','val'));
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  
  function degree2dec($str){
    if(!$str) return false;
    $tmp = preg_split("/[°'\"]{1,2}/",$str,NULL,PREG_SPLIT_NO_EMPTY);
    $deg = (integer)$tmp[0];
    $min = (integer)$tmp[1];
    $sec = (integer)$tmp[2];
    if($deg<0 || strpos($tmp[0], '-') !== false){
      $res = $deg - ((($sec/60)+$min)/60);
    }
    else{
      $res = $deg + ((($sec/60)+$min)/60);      
    }
    return $res;
  }
  function alphatosqldate($str){
    $tb = explode('.',$str);
    return $tb[2].'-'.$tb[1].'-'.$tb[0];
  }
  function sqlDate($str,$fmt="%d/%m/%Y %H:%M:%S"){
    if(empty($str)) return '';
    $ar = strptime($str,$fmt);
    return  date('Y-m-d',mktime(12,0,0,$ar['tm_mon']+1,$ar['tm_mday'],1900+$ar['tm_year']));
  }
  //retourn le nombre de jour entre 2 date
  function diffDate($d1,$d2){
    $ar1 = strptime($d2,"%Y-%m-%d");
    $ar2 = strptime($d1,"%Y-%m-%d");
    $diffs = mktime(12,0,0,$ar1['tm_mon']+1,$ar1['tm_mday'],1900+$ar1['tm_year'])-mktime(12,0,0,$ar2['tm_mon']+1,$ar2['tm_mday'],1900+$ar2['tm_year']);
    return round($diffs/60/60/24);
  }
  
  /**
   * Creation automatique de la tâche
   * 
   */
  public function chk(&$message=NULL) {
    $this->_createTask();
    return parent::chk($message);
  }
  /**
   * forcer l'execution de l'import
   */
  function forceImport($ar){
    $title = $this->getLabel().' - Import Flux';
    $rs = getDB()->fetchAll("select KOID from TASKS where amoid='{$this->_moid}' and status='scheduled'");
    if(count($rs) < 1) {
      setSessionVar('message', 'Pas de tache planifiée en attente.');
    } else {
      $time=date("Y-m-d H:i:s");
      getDB()->execute("update TASKS set ptime='{$time}', status = 'scheduled' where LANG='".TZR_DEFAULT_LANG."' and KOID='{$rs[0]['koid']}'");
      setSessionVar("message", "Tache d'import activée");
    }
    \Seolan\Core\Shell::setNext($this->getMainAction());
  }
  protected function _createTask(){
    $cnt = getDB()->count("select count(*) from TASKS where amoid='".$this->_moid."' and status='cron' ");
    if(!$cnt) {
      \Seolan\Core\Module\Module::singletonFactory(XMODSCHEDULER_TOID)->createSimpleJob(
	'cron',
	$this->_moid,
	'importFluxCron',
	'today',
	'root',
	$this->getLabel().' - Import Flux',
	'',
	null,
	'daily',
	'*/4'
      );
      \Seolan\Core\Shell::toScreen2('','message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_WebResa_WebResa','task_missing'));
    }
  }
}
?>
