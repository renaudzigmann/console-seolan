<?php
namespace Seolan\Module\Tif;
class Tif extends \Seolan\Module\Table\Table{
  public $submodmax=20;
  public $oiddc;                  // oid de la ressource en cours
  public $NSURI;                  // NS principal des XML TiF
  public $prefixSQL;              // Prefix SQL sur les tables du module (TiF1_, TiF2_ ...)
  public $alreadyexport=array(); 
  public $structparam=array();    // Paramètres sur la structure des granules => renseigner dans prepareParam
  public $idlist=array();
  public $acttype='';             // Indique le type d'import en cours (ACVS, GITD...)
  public $tzrns='';
  private $_cache=array();        // Cache du module
  public $TiFTables=array("CAPAGLOB","DMULTIMEDIA","DCONTACT","DADRESSE","DPERSONNE","DMOYENCOM","INFOLEG","DCLASSEMENT","DGEOLOC","ZONES",
			  "DPOINT","DCOOR","DENV","DCARTE","DACCES","DPERIODE","DDATE","DJOURS","JOURS","HORAIRES","DCLIENTELES","DCLIENT",
			  "USAGE","DMODERESA","CAPACAPA","CAPASUP","DCAPAPREST","DCAPAUNIT","DDISP","DOFFREPRESTA","DPRESTA","DTARIF",
			  "DDESCRCOMP","DITI","DPLANNING","DPRESTAPLA","JOURPLA","PRESTLIEE");
  public $withCodeAttr=array('table'  =>  array('DC'),
			     'target'  =>  array('LS_Communes','LS_ZoneNoms','LS_Pays'));
  
  // Propriétés ACVS
  public $ACVSUrl='http://www.acvsnet.net/SI-ACVS/WebServices';
  public $ACVSFile='ACVSWebServices.asmx';
  public $ACVSFileWSDL='ACVSWebServices.asmx?WSDL';
  public $ACVSNSUri='http://www.acvsnet.net/tif3acvs/';
  public $ACVSEnvUris='xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"';
  public $ACVSLogin;
  public $ACVSPwd;
  public $ACVSFilter;

  // Propriétés GITD
  public $GITDUrl='http://extranet.hautes-alpes.net/publication?WSDL';
  public $GITDUrlDomain='http://webservice.hautes-alpes.net';
  public $GITDLogin;
  public $GITDPwd;
  public $GITDFilter;

  // Propriétés SITRA
  public $SITRANSUri='';
  public $SITRADirs='';
  public $SITRAExportFileRegex='/^\([0-9]+\)_ListeOI_([a-z]+)_([0-9]{8}_[0-9]{6})\.xml$/i';
  public $SITRADelFileRegex='/^\([0-9]+\)_DEL_ListeOI_([0-9]{8}_[0-9]{6})\.xml$/i';
  public $SITRAImagesFileRegex='/^\([0-9]+\)_ImagesOI_([0-9]{8}_[0-9]{6})\.zip$/i';
  public $SITRASelectionsFileRegex='/^\([0-9]+\)_Selections_([0-9]{8}_[0-9]{6})\.xml$/i';

  function __construct($ar=NULL){
    parent::__construct($ar);
    if(in_array('ACVS',$this->type)) $this->TiFTables[]="MODESPAIEMENT";
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['importNomenclature']=array('admin');
    $g['ACVSImport']=array('rwv','admin','none');
    $g['GITDImport']=array('rwv','admin','none');
    $g['GITDCheckService']=array('none');
    $g['SITRAImport']=array('rwv','admin','none');
    $g['exportTiF']=array('rwv','admin','none');

    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Prefix SQL des tables', 'prefixSQL', 'text');
    $this->_options->setOpt('Type de la structure', 'type', 'multiplelist', array('values'  =>  array('','ACVS','GITD','SITRA'),
										  'labels'  =>  array('---','ACVS','GITD','SITRA')));
    
    $this->_options->setOpt('ACVS : Login', 'ACVSLogin', 'text');
    $this->_options->setOpt('ACVS : Password', 'ACVSPwd', 'text');
    $this->_options->setOpt('ACVS : Filtre', 'ACVSFilter', 'text', array('rows' => 6,'cols' => 80));

    $this->_options->setOpt('GITD : Login', 'GITDLogin', 'text');
    $this->_options->setOpt('GITD : Password', 'GITDPwd', 'text');
    $this->_options->setOpt('GITD : Filtre', 'GITDFilter', 'text', array('rows' => 6,'cols' => 80));

    $this->_options->setOpt('SITRA : Répertoires', 'SITRADirs', 'text', array('rows' => 6,'cols' => 80));
  }

  protected function _actionlist(&$my, $alfunction=true) {
    parent::_actionlist($my);
    $myclass=get_class($this);
    $moid=$this->_moid;
    $o1=new \Seolan\Core\Module\Action($this,'deletewithtable',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Tif_Tif','deletewithtable','text'),
                          'class='.$myclass.'&moid='.$moid.'&_function=delete&template=proc.html&withtable=1');
    $o1->needsconfirm=true;
    $my['deletewithtable']=$o1;
  }
  
  /// Suppression de tous les modules lié à TiF
  function delete($ar=NULL){
    global $XSHELL;

    $p=new \Seolan\Core\Param($ar,array('tplentry' => ''));
    $withtable=$p->get('withtable');
    if(!empty($withtable)){
      $tplentry=$p->get('tplentry');
      $message=parent::delete(array('tplentry' => TZR_RETURN_DATA));
      $rs=getDB()->select('select MOID from MODULES where MPARAM like "%<field name=\"table\" type=\"%\"><value>'.
		       '<![CDATA['.$this->prefixSQL.'%]]></value></field>%" order by MOID');
      while($rs && ($ors=$rs->fetch())){
	$mod=\Seolan\Core\Module\Module::objectFactory($ors['MOID']);
	$message.=$mod->delete(array('tplentry'=>TZR_RETURN_DATA));
      }
      $rs=getDB()->select('select BTAB from BASEBASE where BTAB LIKE "'.$this->prefixSQL.'LS_%"');
      while($rs && ($ors=$rs->fetch())){
	$xbase=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ors['BTAB']);
	$ret=$xbase->procDeleteDataSource(array('tplentry' => TZR_RETURN_DATA));
	$message.=$ret['message'];
      }
      return \Seolan\Core\Shell::toScreen2($tplentry,'message',$message);
    }else{
      return parent::delete($ar);
    }
  }

  /// Efface toutes les fiches liés à la ressource
  function del($ar) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('_selected');
    $selectedok=$p->get('_selectedok');
    if(($selectedok!='ok') || empty($oid)) $oid=$p->get('oid');
    if(!is_array($oid)) $oid=array($oid => 1);
    if(!empty($oid)){
      $oid=array_keys($oid);
      $oid_list = '"'.implode('","',$oid).'"';
      foreach($this->TiFTables as $table) {
        getDB()->execute('delete from '.$this->prefixSQL.$table.' where  tzr_dc in ('.$oid_list.')');
      }
      getDB()->execute('delete from '.$this->xset->getTable().' where KOID in ('.$oid_list.')');
    }
  }

  /// Prepare les paramètres des différents granules pour l'import/export
  function prepareParam(){
    if(empty($this->_cache['gparam-'.$this->acttype])){
      $gparam=array();
      //--- DC
      $gparam['dc']=array(
          'DC' => array(
              'balisegroup' => NULL,
              'balises' => array(
                  'DublinCore' => array(
                      'table' => 'DC','delNS' => false,'oidfield' => 'dc:identifier',
                      'updfield' => 'dcterms:modified',
                      'ar' => array('tzr_type' => $this->acttype),
                                    'notexport' => array('ModePaiement','ObservationModePaiement',
                                                         'ObservationModePaiement',
                                                         'CapacitesGlobales')))));
      //--- Multimedia
      $gparam['mu']=array('MU' => array('balisegroup' => 'Multimedia',
				      'balises' => array('DetailMultimedia' => array('table' => 'DMULTIMEDIA','fssm' => 'tzr_lie',
										 'notexport' => array('URL_url','URL_img')))));
      //--- Contact
      $param=array('MC' => array('balisegroup' => 'MoyensCommunications',
			       'balises' => array('DetailMoyenCom' => array('table' => 'DMOYENCOM','fssm' => 'tzr_lie'))));
      $param=array('PE' => array('balisegroup' => 'Personnes',
			       'balises' => array('DetailPersonne' => array('table' => 'DPERSONNE','fssm' => 'tzr_lie','ssm' => $param))));
      $param=array('AD' => array('balisegroup' => 'Adresses',
			       'balises' => array('DetailAdresse' => array('table' => 'DADRESSE','fssm' => 'tzr_lie','ssm' => $param))));
      $gparam['co']=array('CO' => array('balisegroup' => 'Contacts',
				      'balises' => array('DetailContact' => array('table' => 'DCONTACT','fssm' => 'tzr_lie','ssm' => $param))));
      $param=array('CT' => array('balisegroup' => 'Contact',
				      'balises' => array('DetailContact' => array('table' => 'DCONTACT','fssm' => 'tzr_lie','ssm' => $param))));
      $gparam['sg']=array('SG' => array('balisegroup' => NULL,
				      'balises' => array('StructureGestion' => array('table' => 'STRGESTION','fssm' => 'tzr_lie','ssm' => $param))));
      $gparam['si']=array('SI' => array('balisegroup' => NULL,
				      'balises' => array('StructureInformation' => array('table' => 'STRINFO','fssm' => 'tzr_lie','ssm' => $param))));
      //--- Informations légales
      $gparam['il']=array('IL' => array('balisegroup' => NULL,
				      'balises' => array('InformationsLegales' => array('table' => 'INFOLEG','fssm' => 'tzr_lie'))));
      //--- Classement
      $gparam['cl']=array('Classement' => array('balisegroup' => 'Classements',
					      'balises' => array('DetailClassement' => array('table' => 'DCLASSEMENT','fssm' => 'tzr_lie'))));
      //--- Geoloc
      $param=array('CO' => array('balisegroup' => 'Coordonnees',
			       'balises' => array('DetailCoordonnees' => array('table' => 'DCOOR','fssm' => 'tzr_lie'))),
		   'EN' => array('balisegroup' => 'Environnements',
			       'balises' => array('DetailEnvironnement' => array('table' => 'DENV','fssm' => 'tzr_lie'))),
		   'CA' => array('balisegroup' => 'Cartes',
			       'balises' => array('DetailCarte' => array('table' => 'DCARTE','fssm' => 'tzr_lie'))),
		   'AC' => array('balisegroup' => 'Acces',
			       'balises' => array('DetailAcces' => array('table' => 'DACCES','fssm' => 'tzr_lie'))),
		   'MU' => array('balisegroup' => 'Multimedia',
			       'balises' => array('DetailMultimedia' => array('table' => 'DMULTIMEDIA','fssm' => 'tzr_lie',
									  'ar' => array('tzr_ispoint' => 1)))));
      $param=array('PO' => array('balisegroup' => 'Points',
			       'balises' => array('DetailPoint' => array('table' => 'DPOINT','fssm' => 'tzr_lie','ssm' => $param))));
      $param=array('ZO' => array('balisegroup' => NULL,
			       'balises' => array('Zone' => array('table' => 'ZONES','fssm' => 'tzr_lie','ssm' => $param))));
      $gparam['ge']=array('GE' => array('balisegroup' => 'Geolocalisations',
				      'balises' => array('DetailGeolocalisation' => array('table' => 'DGEOLOC','fssm' => 'tzr_lie',
										      'ssm' => $param))));
      //--- Periode
      $param=array('HO' => array('balisegroup' => NULL,
			       'balises' => array('Horaires' => array('table' => 'HORAIRES','fssm' => 'tzr_lie'))));
      $param=array('JO' => array('balisegroup' => NULL,
			       'balises' => array('Jour' => array('table' => 'JOURS','fssm' => 'tzr_lie','ssm' => $param))));
      $param=array('DJ' => array('balisegroup' => 'Jours',
			       'balises' => array('DetailJours' => array('table' => 'DJOURS','fssm' => 'tzr_lie','ssm' => $param))));
      $param=array('DA' => array('balisegroup' => 'Dates',
			       'balises' => array('DetailDates' => array('table' => 'DDATE','fssm' => 'tzr_lie','ssm' => $param))));
      $gparam['pe']=array('PE' => array('balisegroup' => 'Periodes',
				      'balises' => array('DetailPeriode' => array('table' => 'DPERIODE','fssm' => 'tzr_lie','ssm' => $param))));
      //--- Clientele
      $param=array('DC' => array('balisegroup' => NULL,
			       'balises' => array('DetailClient' => array('table' => 'DCLIENT','fssm' => 'tzr_lie'))));
      $gparam['cli']=array('CL' => array('balisegroup' => 'Clienteles',
				       'balises' => array('DetailClienteles' => array('table' => 'DCLIENTELES','fssm' => 'tzr_lie',
										  'ssm' => $param))));
      //--- Langues
      $gparam['la']=array('LA' => array('balisegroup' => 'Langues',
				      'balises' => array('Usage' => array('table' => 'USAGE','fssm' => 'tzr_lie'),
						       'ListeLangues' => array('table' => 'LISTELANGUES','fssm' => 'tzr_lie'))));
      //--- Mode resa
      $gparam['mr']=array('MO' => array('balisegroup' => 'ModesReservations',
				      'balises' => array('DetailModeReservation' => array('table' => 'DMODERESA','fssm' => 'tzr_lie',
										      'ssm' => $gparam['co']))));
      //--- Capacité sup
      $gparam['cs']=array('CS' => array('balisegroup' => NULL,
				      'balises' => array('Capacite' => array('table' => 'CAPACAPA','fssm' => 'tzr_lie','direct' => true),
						       'Superficie' => array('table' => 'CAPASUP','fssm' => 'tzr_lie','direct' => true))));
      //--- Capacité presta
      $gparam['cp']=array('CP' => array('balisegroup' => 'CapacitesPrestations',
				      'balises' => array('DetailCapacitePrestation' => array('table' => 'DCAPAPREST','fssm' => 'tzr_lie',
											 'ssm' => $gparam['cs']))));
      //--- Capacité unité
      $param=array('DD' => array('balisegroup' => 'Dispositions',
			       'balises' => array('DetailDisposition' => array('table' => 'DDISP','fssm' => 'tzr_lie'))));
      $gparam['cu']=array('CU' => array('balisegroup' => 'CapacitesUnites',
				      'balises' => array('DetailCapaciteUnite' => array('table' => 'DCAPAUNIT','fssm' => 'tzr_lie',
										    'ssm' => $param))));
      //--- Offre presta
      $oparamcu=$gparam['cu'];
      $oparamcp=$gparam['cp'];
      $oparamcp['CP']['balisegroup']=$oparamcu['CU']['balisegroup']=NULL;
      $oparamcp['CP']['balises']['DetailCapacitePrestation']['ar']=$oparamcu['CU']['balises']['DetailCapaciteUnite']['ar']=
	array('tzr_ispresta' => 1);
      $param=array('PR' => array('balisegroup' => NULL,
			       'balises' => array('DetailPrestation' => array('table' => 'DPRESTA','fssm' => 'tzr_lie','ssm' => $gparam['mr'],
									  'linkparam' => array('DetailCapaciteUnite' => $oparamcu,
											     'DetailCapacitePrestation' => $oparamcp)))));
      $gparam['op']=array('OP' => array('balisegroup' => 'OffresPrestations',
				      'balises' => array('DetailOffrePrestation' => array('table' => 'DOFFREPRESTA','fssm' => 'tzr_lie',
										      'ssm' => $param))));
      //--- Tarif
      $oparamcl=$gparam['cli']['CL']['balises']['DetailClienteles']['ssm'];
      $oparampr=$gparam['op']['OP']['balises']['DetailOffrePrestation']['ssm'];
      $oparampe=$gparam['pe'];
      $oparampe['PE']['balisegroup']=$oparampr['PR']['balisegroup']=$oparamcl['DC']['balisegroup']=NULL;
      $oparampe['PE']['balises']['DetailPeriode']['ar']=$oparampr['PR']['balises']['DetailPrestation']['ar']=
      $oparamcl['DC']['balises']['DetailClient']['ar']=array('tzr_istarif' => 1);
      $oparampe['PE']['balises']['DetailPeriode']['fssm']=$oparampr['PR']['balises']['DetailPrestation']['fssm']=
      $oparamcl['DC']['balises']['DetailClient']['fssm']=NULL;
      $gparam['ta']=array('TA' => array('balisegroup' => 'DetailTarifs',
				      'balises' => array('DetailTarif' => array('table' => 'DTARIF','fssm' => 'tzr_lie',
									    'linkparam' => array('DetailClient' => $oparamcl,
											       'DetailPrestation' => $oparampr,
											       'DetailPeriode' => $oparampe)))));
      //--- Descr comp
      $gparam['desc']=array('DE' => array('balisegroup' => 'DescriptionsComplementaires',
					'balises' => array('DetailDescriptionComplementaire' => array('table' => 'DDESCRCOMP',
												  'fssm' => 'tzr_lie'))));
      //--- Itinieraire
      $oparamzo=$gparam['ge']['GE']['balises']['DetailGeolocalisation']['ssm'];
      $oparamzo['ZO']['balisegroup']=NULL;
      $oparamzo['ZO']['balises']['Zone']['ar']=array('tzr_isiti' => 1);
      $gparam['it']=array('IT' => array('balisegroup' => 'Itineraires',
				      'balises' => array('DetailItineraire' => array('table' => 'DITI','fssm' => 'tzr_lie',
										 'linkparam' => array('Zone' => $oparamzo)))));;
      //--- Planning
      $param=array('JP' => array('balisegroup' => NULL,
			       'balises' => array('JourPlanning' => array('table' => 'JOURPLA','fssm' => 'tzr_lie'))));
      $oparampr['PR']['balises']['DetailPrestation']['ar']=array('tzr_isprestapla' => 1);
      $param=array('PP' => array('balisegroup' => 'PrestationsPlanning',
			       'balises' => array('DetailPrestationPlanning' => array('table' => 'DPRESTAPLA','fssm' => 'tzr_lie',
										  'ssm' => $param,
										  'linkparam' => array('DetailPrestation' => $oparampr)))));
      $oparampe['PE']['balises']['DetailPeriode']['ar']=array('tzr_ispla' => 1);
      $gparam['pla']=array('PL' => array('balisegroup' => 'Plannings',
				       'balises' => array('DetailPlanning' => array('table' => 'DPLANNING','fssm' => 'tzr_lie','ssm' => $param,
										'linkparam' => array('DetailPeriode' => $oparampe)))));
      //--- Prestations liées
      $gparam['pliee']=array('PL' => array('balisegroup' => 'PrestationsLiees',
					 'balises' => array('DetailPrestationLiee' => array('table' => 'PRESTLIEE','fssm' => 'tzr_lie'))));
      $this->_cache['gparam-'.$this->acttype]=$gparam;
    }
    $this->structparam=$this->_cache['gparam-'.$this->acttype];
  }

  /// Importe une chaine/fichier au format TiF
  function _import($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $file=$p->get('file');
    $limit=$p->get('limit');
    $xmlstring=$p->get('xmlstring');
    if(!empty($xmlstring)) $sxml=$xmlstring;
    else $sxml=file_get_contents($file);
    $idlist=array();
    $dom = new \DOMDocument();
    $dom->preserveWhiteSpace=false;
    $dom->validateOnParse = true;
    $dom->loadXML($sxml);
    $this->xpath=new \DOMXpath($dom);
    $this->xpath->registerNamespace('dc','http://purl.org/dc/elements/1.1/');
    $this->xpath->registerNamespace('dcterms', 'http://purl.org/dc/terms/');
    if(!empty($this->NSURI)){
      $this->xpath->registerNamespace('tzrns',$this->NSURI);
      $this->tzrns='tzrns:';
    }else{
      $this->tzrns='';
    }

    $this->prepareParam();
    $ois=$this->xpath->query('/'.$this->tzrns.'OI');
    if($ois->length==0)
      $ois=$this->xpath->query('/ListeOI/'.$this->tzrns.'OI');
    foreach($ois as $i => $oi){
      $oiddc=$this->importDC($oi);                      // DublinCore
      \Seolan\Core\Logs::notice('ModTif::_import', "import $oiddc");
      if(!empty($oiddc)){
	$this->oiddc=$oiddc;
	$this->importMultimedia($oi,$oiddc);	        // Multimédia
	$this->importContacts($oi,$oiddc); 	        // Contacts (Adresses - Personnes - Moyens de communications)
	$this->importStructuresGestion($oi,$oiddc); 	// Structures gestion (Contacts)
	$this->importStructuresInformation($oi,$oiddc); // Structures information (Contacts)
	$this->importInfosLegales($oi,$oiddc);	        // Informations légales
	$this->importClassements($oi,$oiddc);	        // Classements
	$this->importGeolocs($oi,$oiddc);	        // Geolocalisation (Zones - Points - Coords - Envs - Cartes - Acces - Multimédia)
	$this->importPeriodes($oi,$oiddc);	        // Periodes (Dates - Details jours - Jours - Horaires)
	$this->importClienteles($oi,$oiddc);	        // Clientèles (Details client)
	$this->importUsages($oi,$oiddc);	        // Usages/Langues
	$this->importModesResa($oi,$oiddc);	        // Modes de réservation
	$this->importCapacites($oi,$oiddc);	        // Capacités
	$this->importOffresPresta($oi,$oiddc);	        // Offres de prestation (Prestations)
	$this->importTarifs($oi,$oiddc);	        // Tarifs et modes de paiement
	$this->importDescrComp($oi,$oiddc);	        // Descriptions complémentaires
	$this->importItineraires($oi,$oiddc);	        // Itinéraires
	$this->importPlannings($oi,$oiddc);	        // Plannings (Presta planning - Jour planning)
	$this->importPresationsLiees($oi,$oiddc);	// Prestations liées
	$this->importOther($oi,$oiddc);	                // Fonction à personnaliser
	if(!empty($limit) && $limit<=$i) break;
      }
    }
    unset($dom,$xmlstring,$sxml,$ois);
  }
  function importDC($oi){
    $this->idlist=array();
    $dc=$this->xpath->query($this->tzrns.'DublinCore',$oi);
    if($dc->length>0){
      $dc=$dc->item(0);
      $oiddc=$this->analyseDC($dc,NULL,$this->structparam['dc']['DC']['balises']['DublinCore']);
      if(!empty($this->structparam['dc']['DC']['balises']['DublinCore']['ssm'])) $this->analyseQuery($dc,$this->structparam['dc']['DC']['balises']['DublinCore']['ssm'],$oiddc);
    }
    unset($dc);
    return $oiddc;
  }
  function importMultimedia(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['mu'],$oiddc);
  }
  function importContacts(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['co'],$oiddc);
  }
  function importStructuresGestion(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['sg'],$oiddc);
  }
  function importStructuresInformation(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['si'],$oiddc);
  }
  function importInfosLegales(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['il'],$oiddc);
  }
  function importClassements(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['cl'],$oiddc);
  }
  function importGeolocs(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['ge'],$oiddc);
  }
  function importPeriodes(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['pe'],$oiddc);
  }
  function importClienteles(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['cli'],$oiddc);
  }
  function importUsages(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['la'],$oiddc);
  }
  function importModesResa(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['mr'],$oiddc);
  }
  function importCapacites(&$oi,$oiddc){
    $capa=$this->xpath->query($this->tzrns.'Capacites',$oi);
    if($capa->length>0){
      $capa=$capa->item(0);
      // Capacité globale
      $capaglob=$this->xpath->query($this->tzrns.'CapacitesGlobales',$capa);
      if($capaglob->length>0){
	$capaglob=$capaglob->item(0);
 	$param=array('table' => 'CAPAGLOB','fssm' => 'tzr_lie');
 	$oid=$this->analyseDC($capaglob,$oiddc,$param);
	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DC');
	$xset->procEdit(array('oid' => $oiddc,'CapacitesGlobales' => $oid,'_nolog' => true));
	$this->analyseQuery($capaglob,$this->structparam['cs'],$oid);
      }
      // Capacités prestations
      $this->analyseQuery($capa,$this->structparam['cp'],$oiddc);
      // Capacités unités
      $this->analyseQuery($capa,$this->structparam['cu'],$oiddc);
    }
  }
  function importOffresPresta(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['op'],$oiddc);
  }
  function importTarifs(&$oi,$oiddc){
    // Modes de paiement
    $mp=$this->xpath->query($this->tzrns.'Tarifs/'.$this->tzrns.'ModesPaiement',$oi);
    if($mp->length>0){
      $mp=$mp->item(0);
      $mdps=$this->xpath->query($this->tzrns.'ModePaiement',$mp);
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DC');
      $xsetmp=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$xset->desc['ModePaiement']->target);
      $ar2=array();
      foreach($mdps as $mpd){
	$oidmp=$xset->desc['ModePaiement']->target.':'.str_replace('.','-',$mpd->getAttribute('type'));
	$xsetmp->procInput(array('newoid' => $oidmp,'_nolog' => true,
				 'code' => $mpd->getAttribute('type'),'libelle' => $mpd->textContent));
	$ar2['ModePaiement'][]=$oidmp;
      }
      $obs=$this->xpath->query($this->tzrns.'ObservationModePaiement',$mp);
      if($obs->length>0) $ar2['ObservationModePaiement']=$obs->item(0)->textContent;
      $ar2['oid']=$oiddc;
      $ar2['_nolog']=true;
      $xset->procEdit($ar2);
    }
    unset($mp,$mdps,$mdp,$ar2,$obs);

    // Tarifs
    $ta=$this->xpath->query($this->tzrns.'Tarifs',$oi);
    if($ta->length>0){
      $ta=$ta->item(0);
      $this->analyseQuery($ta,$this->structparam['ta'],$oiddc);
    }
    unset($ta);
  }
  function importDescrComp(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['desc'],$oiddc);
  }
  function importItineraires(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['it'],$oiddc);
  }
  function importPlannings(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['pla'],$oiddc);
  }
  function importPresationsLiees(&$oi,$oiddc){
    $this->analyseQuery($oi,$this->structparam['pliee'],$oiddc);
  }
  function importOther(&$oi,$oiddc){
  }

  /// Requete un noeud et prepare son analyse
  function analyseQuery(&$base,$params,$oidssm){
    if(isset($params['balises'])) $params=array($params);

    $oids=array();
    foreach($params as $param){
      $balisegrp=$param['balisegroup'];
      $balises=$param['balises'];
      if(!empty($balisegrp)){
	$dc=$this->xpath->query($this->tzrns.$balisegrp,$base);
	if($dc->length>0){
	  $dc=$dc->item(0);
	  $idref=$dc->getAttribute('idref');
	  $id=$dc->getAttribute('id');
	  if(!empty($idref)){
	    foreach($this->idlist[$idref]['idlist'] as $i => $myid){
	      $oids[]=$this->addReference($myid,$oidssm,$this->idlist[$idref]['fssmlist'][$i]);
	    }
	  }else{
	    foreach($balises as $balise => $data){
	      $details=$this->xpath->query($this->tzrns.$balise,$dc);
	      foreach($details as $i => $detail){
		if(!empty($id)){
		  $idd=$detail->getAttribute('id');
		  if(empty($idd)){
		    $idd=$id.'-tzr'.$i;
		    $detail->setAttribute('id',$idd);
		  }
		  $this->idlist[$id]['idlist'][]=$idd;
		  $this->idlist[$id]['fssmlist'][]=$data['fssm'];
		}
		$oid=$this->analyseDC($detail,$oidssm,$data);
		$oids[]=$oid;
		if(!empty($data['ssm'])) $this->analyseQuery($detail,$data['ssm'],$oid);
	      }
	    }
	  }
	}
      }else{
	foreach($balises as $balise => $data){
	  $details=$this->xpath->query($this->tzrns.$balise,$base);
	  foreach($details as $detail){
	    $oid=$this->analyseDC($detail,$oidssm,$data);
	    $oids[]=$oid;
	    if(!empty($data['ssm'])) $this->analyseQuery($detail,$data['ssm'],$oid);
	  }
	}
      }
    }
    unset($dc,$details,$detail,$data);
    return $oids;
  }

  /// Insere les données en base dans le cas d'un objet faisant référence à un autre
  private function addReference($idref,$oidssm,$fssm){
    if(!empty($idref) && !empty($this->idlist[$idref])){
      if(!empty($oidssm) && !empty($fssm)){
	$ar=array('oid' => $this->idlist[$idref]['oid']);
	$ar[$fssm]=$this->idlist[$idref]['data'][$fssm];
	if(is_array($ar[$fssm])){
	  $ar[$fssm][]=$oidssm;
	}else{
	  $ar[$fssm]=array($ar[$fssm],$oidssm);
	}
	$table=\Seolan\Core\Kernel::getTable($this->idlist[$idref]['oid']);
	if(\Seolan\Core\System::tableExists($table)){
	  $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
	  $ar['_nolog']=true;
	  $xset->procEdit($ar);
	}
      }
      return $this->idlist[$idref]['oid'];
    }
    return false;
  }

  /// Analyse un noeud et insere en base
  private function analyseDC(&$dc,$oidssm=NULL,&$param=array()){
    $table=$param['table'];
    if(empty($table)) return;
    $forceedit=false;
    $id=$dc->getAttribute('id');
    $idref=$dc->getAttribute('idref');
    $ar=isset($param['ar'])?$param['ar']:array();
    $fssm=isset($param['fssm'])?$param['fssm']:NULL;
    $delNS=isset($param['delNS'])?$param['delNS']:true;
    $direct=isset($param['direct'])?$param['direct']:false;
    $oidfield=isset($param['oidfield'])?$param['oidfield']:NULL;
    $updfield=isset($param['updfield'])?$param['updfield']:'';
    $linkparam=isset($param['linkparam'])?$param['linkparam']:array();
    if($direct) $items=array($dc);
    else $items=$dc->childNodes;

    // Génération du KOID
    if(\Seolan\Core\Kernel::getTable($oidssm)==$this->prefixSQL.$table){
      $ar['oid']=$oidssm;
      $forceedit=true;
    }elseif(!empty($param['oid'])){
      $ar['newoid']=$newoid=$param['oid'];
    }elseif($oidfield){
      if(strpos($this->acttype,'SITRA')===0 && strpos($oidfield,':')){
	$oidfield=$this->xpath->query(substr($oidfield,strpos($oidfield,':')+1),$dc);
      }else{
	$oidfield=$this->xpath->query($oidfield,$dc);
      }
      if($oidfield->length>0){
	$oidfield=$oidfield->item(0)->textContent;
	$ar['newoid']=$newoid=$this->prefixSQL.$table.':'.preg_replace('/[^a-z0-9_-]/i','_',$oidfield);
      }
    }
    if(empty($newoid)){
      $ar['newoid']=$newoid=\Seolan\Core\DataSource\DataSource::getNewBasicOID($this->prefixSQL.$table);
    }
    \Seolan\Core\Logs::notice('ModTif::analyseDC', "import oid:{$ar['oid']}, newoid:$newoid");

    // Verification du dernier update. Si doit etre mis à jour, on efface pour mieux reinserer
    if(!empty($updfield)){
      if(strpos($this->acttype,'SITRA')===0 && strpos($updfield,':')){
	$updbal=$this->xpath->query(substr($updfield,strpos($updfield,':')+1),$dc);
      }else{
	$updbal=$this->xpath->query($updfield,$dc);
      }
      if($updbal->length>0){
	$updfield=str_replace(':','__',$updfield);
 	$updvalue=$this->xset->desc[$updfield]->post_edit($updbal->item(0)->textContent);
	$updvalue=$updvalue->raw;
 	$rs=getDB()->select('select '.$updfield.' as UPD from '.$this->prefixSQL.$table.' where KOID="'.$newoid.'"');
 	$ors=$rs->fetch();
 	$rs->CloseCursor();
 	if(!empty($ors['UPD'])){
 	  if($ors['UPD']>=$updvalue) {
            \Seolan\Core\Logs::notice('ModTif::analyseDC', "import $newoid not imported {$ors['UPD']} >= $updvalue");
            return false;
 	  }
 	  elseif($this->prefixSQL.$table==$this->table) {
            \Seolan\Core\Logs::notice('ModTif::analyseDC', "delete $newoid");
            $this->del(array('oid' => $newoid,'_nolog' => true));
 	  }
	}
      }
    }

    // Dans le cas d'une référence, on recupère les données
    if(!empty($idref)){
      $ret['oid']=$this->addReference($idref,$oidssm,$fssm);
    }else{
      if((!$dc->hasChildNodes() && !$dc->hasAttributes() && !$direct) || !\Seolan\Core\System::tableExists($this->prefixSQL.$table)) return false;
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.$table);

      $hasData=false;
      // Traite les eventuels attributs de la balise source à enregistrer
      foreach($dc->attributes as $dcatt => $i){
	if(isset($xset->desc['att_'.$dcatt])){
	  $hasData=true;
	  $att='att_'.$dcatt;
	  $value=$dc->getAttribute($dcatt);
	  $ar[$att]=$this->getAttributeValue($xset,$att,$value,$dc);
	}
      }

      // Parcours les enfants
      foreach($items as $item){
	$hasData=true;
	if($delNS){
	  list($ns,$field)=explode(':',$item->tagName);
	  if(empty($field)) $field=$ns;
	}else{
	  $field=str_replace(':','__',$item->tagName);
	  // Patch pour DC de sitra qui n'utilise pas les espaces de noms
	  if(strpos($this->acttype,'SITRA')===0){
	    if(!isset($xset->desc[$field])){
	      if(isset($xset->desc['dc__'.$field])) $field='dc__'.$field;
	      elseif(isset($xset->desc['dcterms__'.$field])) $field='dcterms__'.$field;
	    }
	  }
	}
	if(isset($xset->desc[$field])){
	  $xfield=&$xset->desc[$field];
	  $ftype=$xfield->ftype;
	  switch($ftype){
	  case '\Seolan\Field\File\File': // Tente de recupérer le fichier pour le mettre en local, sinon utilise un champ url
	    $file=TZR_TMP_DIR.'TiF'.uniqid();
	    $content=file_get_contents($item->textContent);
	    if(!empty($content)){
	      file_put_contents($file,$content);
	      unset($content);
	      $mimeClasse=\Seolan\Library\MimeTypes::getInstance();
	      $mime=$mimeClasse->get_file_type($file);
	      if($mimeClasse->isImage($mime)){
		$ar[$field.'_img']='';
		$_FILES[$field.'_img']['tmp_name']=$file;
		$_FILES[$field.'_img']['type']=$mime;
		$_FILES[$field.'_img']['name']=$item->textContent;
		$_FILES[$field.'_img']['size']=filesize($file);
	      }else{
		$ar[$field]='';
		$_FILES[$field]['tmp_name']=$file;
		$_FILES[$field]['type']=$mime;
		$_FILES[$field]['name']=$item->textContent;
		$_FILES[$field]['size']=filesize($file);
	      }
	    }
	    $ar[$field.'_url']['url']=$item->textContent;
	    break;
	  case '\Seolan\Field\Url\Url':
	    $ar[$field]['url']=$item->textContent;
	    break;
	  case '\Seolan\Field\Link\Link':
	    if(strpos($xset->desc[$field]->target,$this->prefixSQL.'LS_')===0){
	      $code=$item->getAttribute('code');
	      if(empty($code)) $code=$item->getAttribute('type');
	      $code=$this->getFormatCode($code);
	      if(!empty($code)){
		$rs=getDB()->select('select KOID from '.$xset->desc[$field]->target.' where code="'.$code.'"');
		if($rs && $rs->rowCount()==0){
		  $codeoid=$xset->desc[$field]->target.':'.str_replace('.','-',$code);
		  $libelle=$item->getAttribute('libelle');
		  if(empty($libelle)) $libelle=$item->textContent;
                  $xsettmp=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$xset->desc[$field]->target);
 		  $xsettmp->procInput(array('newoid' => $codeoid,'code' => $code,'libelle' => $libelle,'_nolog' => true));
		}else{
		  $ors=$rs->fetch();
		  $codeoid=$ors['KOID'];
		}
		$rs->CloseCursor();
		if($xset->desc[$field]->multivalued) $ar[$field][]=$codeoid;
		else $ar[$field]=$codeoid;
	      }
	    }elseif(!empty($linkparam[$field])){
	      $oids=$this->analyseQuery($item->parentNode,$linkparam[$field],$newoid);
	      if(!$xset->desc[$field]->multivalued) $oids=$oids[0];
	      $ar[$field]=$oids;
	    }
	    break;
	  case '\Seolan\Field\Text\Text':
	    if(!empty($ar[$field])) $ar[$field].="\n\n".$item->textContent;
	    else $ar[$field]=$item->textContent;
	    break;
	  case '\Seolan\Field\Boolean\Boolean':
	    if($item->textContent=='O') $ar[$field]=1;
	    else $ar[$field]=0;
	    break;
	  case '\Seolan\Field\DateTime\DateTime':
	    $parts=preg_split('@[\ ]@',$item->textContent);
	    if(!empty($parts[1]) && preg_match('@^[0-9]{2}:[0-9]{2}$@',$parts[1]))
	      $ar[$field]=array('date' => $parts[0],'hour' => $parts[1].":00");
	    else
	      $ar[$field]=$item->textContent;
	    break;
	  default:
	    if($xset->desc[$field]->multivalued){
	      if(empty($ar[$field])) $ar[$field]=$item->textContent;
	      else $ar[$field].=','.$item->textContent;
	    }else{
	      if(!empty($item->textContent)) $ar[$field]=$item->textContent;
	    }
	    break;
	  }
	  
	  // Traite les eventuels attributs à enregistrer
	  $ord=$xset->desc[$field]->forder+1;
	  for($i=$ord;$i<count($xset->desc);$i++){
	    if(preg_match("/^att_".$field."_(.*)$/",$xset->orddesc[$i],$ret)){
	      $att=$xset->orddesc[$i];
	      $atttype=$xset->desc[$att]->ftype;
	      $value=$item->getAttribute($ret[1]);
	      $ar[$att]=$this->getAttributeValue($xset,$att,$value,$item);
	    }else{
	      break;
	    }
	  }
	  
	  // Boucle pour forcer les champs numériques à NULL s'ils ne sont pas renseignés (car sinon 0, et c'est pas bien)
	  foreach($xset->desc as $fn => &$f){
	    if($f->ftype=='\Seolan\Field\Real\Real' && !isset($ar[$fn])) $ar[$fn]='';
	  }
	}
      }
      if($hasData){
	$ar['_nolog']=true;
	$ar['tzr_dc']=$this->oiddc;
	if($forceedit){
          \Seolan\Core\Logs::notice('ModTif::analyseDC', "update {$ar['oid']}");
	  $xset->procEdit($ar);
	  $ret['oid']=$ar['oid'];
	}else{
	  // Renseigne l'oid du module parent si necessaire
	  if(!empty($oidssm) && !empty($fssm)) $ar[$fssm]=$oidssm;
          \Seolan\Core\Logs::notice('ModTif::analyseDC', "insert $newoid");
	  $ret=$xset->procInput($ar);
	}
      }
    }

    // Enregistre les données si la balise à un identifiant
    if(!empty($id)){
      $this->idlist[$id]=array('oid' => $ret['oid'],'data' => $ar);
    }
    unset($ar,$items);
    return $ret['oid'];
  }

  /// Recupere une valeur TZR d'un attribut
  function getAttributeValue(&$xset,$att,$value,&$item){
    $atttype=$xset->desc[$att]->ftype;
    switch($atttype){
    case '\Seolan\Field\Link\Link':
      if(strpos($xset->desc[$att]->target,$this->prefixSQL.'LS_')===0){
	if(!empty($value)){
	  $code=$this->getFormatCode($value);
	  $ors=getDB()->fetchRow('select KOID from '.$xset->desc[$att]->target.' where code=?',array($code));
	  if(!$ors){
	    $value=$xset->desc[$att]->target.':'.str_replace('.','-',$code);
	    $libelle=$item->getAttribute('libelle');
	    if(empty($libelle)) $libelle=$item->textContent;
	    $libelle=$libelle;
	    $xsettmp=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$xset->desc[$att]->target);
	    $xsettmp->procInput(array('newoid' => $value,'code' => $code,'libelle' => $libelle,'_nolog' => true));
	  }else{
	    $value=$ors['KOID'];
	  }
	}
      }else{
	if(!empty($value)) $value=$xset->desc[$att]->target.':'.preg_replace('/([^a-z0-9_-]+)/','-',$value);
      }
      break;
    case '\Seolan\Field\Boolean\Boolean':
      if(strtolower($value)=='o') $value=1;
      else $value=0;
      break;
    default:
      break;
    }
    return $value;
  }
  
  /// Formate un code du thésaurus en xx.xx.xx...
  function getFormatCode($code){
    $acode=explode('.',$code);
    $code=array();
    foreach($acode as $tmp) $code[]=sprintf("%02d",$tmp);
    return implode('.',$code);
  }

  /**
   Fonction pour retourner un resultat plat sur une ressource
   Exemple de $list=array('DCONTACT' => array('filter' => 'RaisonSociale="LES RAILLES"',
                                            'fields' => array('Sigle' => "Sig",'RaisonSociale' => "RS"),
                                            'sstables' => array('DADRESSE' => array('fields' => array('Adr1' => 'adr'),'all' => true,
                                                                                'order' => 'Adr1 DESC','pagesize' => 5)))
   filter : filter sql   /   fields : liste des champs à recupérer (clé=champ TiF, valeur=champ destination)
   order : ordre         /   pagesize : nombre d'éléments à recuperer
  **/
  function getFlatEntry($oid,$list,&$targetxset,$alloids=array()){
    $lang_data=\Seolan\Core\Shell::getLangData();
    $ret=array();
    if(!is_array($oid)) $oid=array($oid);
    if(empty($alloids)) $alloids=$oid;

    foreach($list as $table => $param){
      $p=new \Seolan\Core\Param($param,array('filter' => '','pagesize' => 1,'all' => false,'sstables' => array(),'fields' => array(),'ssmfield' => 'tzr_lie',
				 'order' => 'tzr_lie,UPD desc','multivalued' => array(),'format' => array(),'pageseparator' => array(),
				 'concatseparator' => array(),'boolcond' => array(),'increment' => array(),'ifempty' => array()),'all',
		    array('pagesize'=>array(FILTER_VALIDATE_INT,array()),
			  'order'=>array(FILTER_CALLBACK,array('options'=>'containsNoSQLKeyword'))));

      if(strpos($table,':')!==false) // requête multiple sur la même table
        $table = substr($table,strpos($table,':')+1);
      $table=$this->prefixSQL.$table;
      $all=$p->get('all');
      $eval=$p->get('eval');
      $order=$p->get('order');
      $filter=$p->get('filter');
      $fields=$p->get('fields');
      $format=$p->get('format');
      // Tableau de champs qui ne sont a traiter que si le champ de destination est vide
      $ifempty=$p->get('ifempty');
      // Champ faisant le lien entre l'objet parent et l'objet fils (tzr_lie par defaut)
      $ssmfield=$p->get('ssmfield');
      $sstables=$p->get('sstables');
      // Nombre d'objet à traier (1 par defaut)
      $pagesize=$p->get('pagesize');
      $increment=$p->get('increment');
      $multivalued=$p->get('multivalued');
      // Separateur à utiliser dans le cas ou on concatene plusieurs objets dans le meme champ de destination
      $pageseparator=$p->get('pageseparator');
      // Separateur à utiliser dans le cas ou on concatene plusieurs champ d'un même objet  dans le meme champ de destination
      $concatseparator=$p->get('concatseparator');
      // Tableau des champs => champs cible dans le cas d'un lien sur un objet
      $xlinktargetf=$p->get('xlinktargetf');
      $oidlies=array();
      $lastpage=array();
      if($all) $oid=$alloids;
      
      if($table==$this->table){
 	$q='select * from '.$table.' where LANG="'.$lang_data.'" AND KOID="'.$oid[0].'"';
	$order='';
	$ret['oid']=$ret['newoid']=str_replace($this->prefixSQL.'DC',$targetxset->getTable(),$oid[0]);
      }else{
	$q='select * from '.$table.' where LANG="'.$lang_data.'" AND '.$ssmfield.' IN("'.implode('", "',$oid).'")';
	if(!empty($filter)) $q.=' AND '.$filter;
      }

      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
      $options=array();
      foreach($xlinktargetf as $f=>$targetf){
        $options[$f]=array('target_fields' => $targetf, 'nocache' => 1);
      }
      $br=$xset->browse(array(
        'select' => $q,
        'order' => $order,
        'tplentry' => TZR_RETURN_DATA,
        'selectedfields' => array_keys($fields),
        'options' => $options,
        'nocount' => 1
      ));

      foreach($br['lines_oid'] as $i => $actoid){
	if($i==$pagesize) break;
	foreach($fields as $f => $newf){
	  if(!empty($ret[$newf]) && in_array($f,$ifempty)) continue;
	  if(in_array($newf,$increment)) $newf=$newf.($i+1);
          if($f=='oid')
            $value = $br['lines_oid'][$i];
          else
            $value=$this->getValueForInput($br['lines_o'.$f][$i],$xset->desc[$f],$targetxset->desc[$newf]);
	  if(is_array($value)){
	    if(empty($ret[$newf])) $ret[$newf]=array();
	    $ret[$newf]=array_merge($ret[$newf],$value);
	  }else{
	    if(!empty($eval[$f])) $value=eval('return '.$eval[$f]);
	    if($targetxset->desc[$newf]->ftype=='\Seolan\Field\Boolean\Boolean' && $ret[$newf]!=1) $ret[$newf]=$value; 
	    elseif($targetxset->desc[$newf]->ftype=='\Seolan\Field\GeodesicCoordinates\GeodesicCoordinates' && !empty($ret[$newf])) $ret[$newf].=';'.$value;
	    else{
	      // Formate la valeur si besoin
	      if(!empty($format[$f])) $value=sprintf($format[$f],$value);
	      if(empty($ret[$newf])) $ret[$newf]='';
	      elseif(!empty($value)){
		// Ajoute le separateur suite à un changement d'objet ou pour une simple concat de champ sur un meme objet
		if($lastpage[$newf]!=$i){
		  if(!empty($pageseparator[$newf])) $sep=$pageseparator[$newf];
		  elseif($targetxset->desc[$newf]->ftype=='\Seolan\Field\Text\Text') $sep="\r\n";
		  elseif($targetxset->desc[$newf]->ftype=='\Seolan\Field\ShortText\ShortText') $sep=', ';
		  else $sep='';
		  $value=$sep.$value;
		}else{
 		  if(!array_key_exists($newf,$concatseparator)) $concatseparator[$newf]=' ';
 		  $value=$concatseparator[$newf].$value;
 		}
 	      }
 	      $ret[$newf].=$value;
 	    }
  	  }
  	  if(!empty($ret[$newf])) $lastpage[$newf]=$i;
  	}
  	$oidlies[]=$actoid;
      }
      unset($br,$param);
      
      // Recupere les sous tables
      if(!empty($sstables)) {
        if($table!=$this->table) {
          $ssalloids = getDB()->fetchCol('select KOID from '.$table.' '.
                                    'where tzr_lie IN("'.implode('","',$alloids).'")');
        }
        $ret2=$this->getFlatEntry($oidlies,$sstables,$targetxset,$ssalloids);
      }
      
      if(!empty($ret2)) $ret=array_merge($ret,$ret2);
    }
    return $ret;
  }

  /// Transforme la valeur du champ source en valeur à passer à un procInput pour la champ destination
  function getValueForInput($value,$f,$targetf){
    global $DATA_DIR;
    if($targetf->ftype=='\Seolan\Field\Text\Text' || $targetf->ftype=='\Seolan\Field\ShortText\ShortText'){
      return $value->text;
    }
    if($targetf->ftype=='\Seolan\Field\Boolean\Boolean' && $f->ftype!='\Seolan\Field\Boolean\Boolean'){
      if((empty($targetf->boolcond) && !empty($value->raw)) || (!empty($targetf->boolcond) && in_array($value->raw,$targetf->boolcond)))
	return 1;
      else
	return 2;
    }
    switch($targetf->ftype){
    case '\Seolan\Field\GeodesicCoordinates\GeodesicCoordinates':
      return str_replace(',','.',$value->raw);
      break;
    }
    switch($f->ftype){
    case '\Seolan\Field\Link\Link':
      return explode('||',$value->raw);
      break;
    case '\Seolan\Field\Image\Image':
      $tmp_name=TZR_TMP_DIR.uniqid();
      copy($value->filename,$tmp_name);
      return $tmp_name;
      break;
    default:
      return $value->raw;
      break;
    }
  }
  
  /// Fonction pour checker l'état du webservice de ACVS
  function ACVSCheckService($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $filter=$p->get('ACVSFilter');
    if(empty($filter)) $filter=$this->ACVSFilter;
    $idsql=array();

    ini_set('soap.wsdl_cache_enabled', 0);
    $soap=new \SoapClient($this->ACVSUrl.'/'.$this->ACVSFileWSDL,array('exceptions' => false));
    $header=$this->ACVSParamGetHeader();
    $params=\Seolan\Core\System::xml2array($filter);
    foreach($params as $param){
      $param=$this->ACVSParamGetSearchOnlyIdPrestation($param);
      $request='<?xml version="1.0" encoding="utf-8"?>';
      $request.='<soap:Envelope '.$this->ACVSEnvUris.'>';
      $request.=$header.$param;
      $request.='</soap:Envelope>';
      $xmlidlist=$soap->__doRequest($request,$this->ACVSUrl.'/'.$this->ACVSFile,$this->ACVSUrl.'/SearchOnlyIdPrestation',1);
      if(get_class($xmlidlist)=='SoapFault') die('false');
      $dom=new \DOMDocument();
      $dom->loadXML($xmlidlist);
      $ids=$dom->getElementsByTagName('idPrestation');
      if(empty($ids) || $ids->length==0 || empty($ids->item(0)->textContent)) die('false');
    }
    die('true');
  }

  /// Procédure d'import depuis le webservice d'ACVS
  function ACVSImport(/*$ar=NULL*/$sched, $o, $more) {
    $this->NSURI=$this->ACVSNSUri;
    $filter=$this->ACVSFilter;
    $message='';
    ini_set('soap.wsdl_cache_enabled', 0);
    $soap=new \SoapClient($this->ACVSUrl.'/'.$this->ACVSFileWSDL,array('exceptions' => false));
    $header=$this->ACVSParamGetHeader();
    $params=\Seolan\Core\System::xml2array($filter);
    foreach($params as $rechname => $tzrparam){
      $this->acttype='ACVS ('.$rechname.')';
      $param=$this->ACVSParamGetSearchOnlyIdPrestation($tzrparam);
      $request='<?xml version="1.0" encoding="utf-8"?>';
      $request.='<soap:Envelope '.$this->ACVSEnvUris.'>';
      $request.=$header.$param;
      $request.='</soap:Envelope>';
      $xmlidlist=$soap->__doRequest($request,$this->ACVSUrl.'/'.$this->ACVSFile,$this->ACVSUrl.'/SearchOnlyIdPrestation',1);
      $dom=new \DOMDocument();
      $dom->loadXML($xmlidlist);
      $fault=$dom->getElementsByTagName('faultstring');
      if($fault->length>0){
	if(!empty($this->reportto)){
	  $GLOBALS['XUSER']->sendMail2User('TiF : Erreur import ACVS',"L'interrogation saop a retourné l'erreur suivante : \n".
					   $fault->item(0)->textContent,$this->reportto);
	}
	return "L'interrogation saop a retourné l'erreur suivante : \n".$fault->item(0)->textContent;
      }
      $ids=$dom->getElementsByTagName('idPrestation');
      foreach($ids as $i => $id){
	$dom2=new \DOMDocument();
	$param=$this->ACVSParamGetGetPrestationTIF(array('idPrestation' => $id->textContent));
	$request='<?xml version="1.0" encoding="utf-8"?>';
	$request.='<soap:Envelope '.$this->ACVSEnvUris.'>';
	$request.=$header.$param;
	$request.='</soap:Envelope>';
	$tif=$soap->__doRequest($request,$this->ACVSUrl.'/'.$this->ACVSFile,$this->ACVSUrl.'/GetPrestationTIF',1);
	$dom2->loadXML($tif);
	$tif=$dom2->getElementsByTagName('xmlTif');
	if($tif->length>0)
          $this->_import(array('xmlstring' => '<?xml version="1.0"?>'.$tif->item(0)->textContent));
      }

      // Suppression des élémenents qui ne sont pas dans l'import
      $param=$this->ACVSParamGetSearchOnlyIdPrestation($tzrparam,true);
      $request='<?xml version="1.0" encoding="utf-8"?>';
      $request.='<soap:Envelope '.$this->ACVSEnvUris.'>';
      $request.=$header.$param;
      $request.='</soap:Envelope>';
      $xmlidlist=$soap->__doRequest($request,$this->ACVSUrl.'/'.$this->ACVSFile,$this->ACVSUrl.'/SearchOnlyIdPrestation',1);
      $dom=new \DOMDocument();
      $dom->loadXML($xmlidlist);
      $ids=$dom->getElementsByTagName('idPrestation');
      $idsql=array();
      foreach($ids as $i => $id)
        $idsql[]='ACVS'.$id->textContent;
      if(empty($idsql)){
 	$mail='La requete "'.$rechname.'" n\'a retournée aucun enregistrement.';
 	if(!empty($this->reportto))
          $GLOBALS['XUSER']->sendMail2User('TiF : Erreur import ACVS',$mail,$this->reportto);
 	$message.=$mail."\n";
      }
      $rs=getDB()->select('select KOID from '.$this->table.' where tzr_type="'.$this->acttype.'" and '.
  		       'dc__identifier not in ("'.implode('","',$idsql).'")');
      while($rs && ($ors=$rs->fetch()))
        $this->del(array('oid' => $ors['KOID'],'_nolog' => true));
      $rs->CloseCursor();
    }
    return $message;
  }

  /// Prepare l'entete des requètes Soap d'ACVS (identification)
  function ACVSParamGetHeader(){
    $ret='<soap:Header><AuthHeader xmlns="'.$this->ACVSUrl.'">';
    $ret.='<Login>'.$this->ACVSLogin.'</Login><Password>'.$this->ACVSPwd.'</Password>';
    $ret.='</AuthHeader></soap:Header>';
    return $ret;
  }

  /// Prepare le xml pour la fonction SearchOnlyIdPrestation du Soap ACVS
  function ACVSParamGetSearchOnlyIdPrestation($parameters=array(),$nodate=false){
    $ret='<soap:Body><SearchOnlyIdPrestation xmlns="'.$this->ACVSUrl.'">';
    $defaults=array('' => array('libPrestation','criteres','majApresLe'),
		    '-1' => array('triEvenement','bMonth1','bMonth2','bMonth3','bMonth4','bMonth5','bMonth6',
				'bMonth7','bMonth8','bMonth9','bMonth10','bMonth11','bMonth12','bWeekEnd','idAnneeExercice',
				'dayBorneInfOuverture','dayBorneSupOuverture','bCritereA','bCritereB','bCritereC','bCritereD',
				'bCritereE','bCritereF','bCritereG','bCritereH'));
    if($nodate) unset($parameters['majApresLe']);
    foreach($defaults as $default => $balises){
      foreach($balises as $balise){
	if(isset($parameters[$balise])){
	  if($balise=='majApresLe'){
	    if(preg_match("/[0-9]{4}-[0-9]{2}-[0-9]{2}/",$parameters[$balise]))
	      $ret.='<'.$balise.'>'.$parameters[$balise].'</'.$balise.'>';
	    else
	      $ret.='<'.$balise.'>'.date('Y-m-d',strtotime($parameters[$balise])).'</'.$balise.'>';
	  }else{
	    $ret.='<'.$balise.'>'.$parameters[$balise].'</'.$balise.'>';
	  }
	}else{
	  $ret.='<'.$balise.'>'.$default.'</'.$balise.'>';
	}
      }
    }
    $ret.='</SearchOnlyIdPrestation></soap:Body>';
    return $ret;
  }

  /// Prepare le xml pour la fonction GetPrestationTIF du Soap ACVS
  function ACVSParamGetGetPrestationTIF($parameters = array()){
    $ret='<soap:Body><GetPrestationTIF xmlns="'.$this->ACVSUrl.'">';
    if(isset($parameters['idPrestation'])) $ret.='<idPrestation>'.$parameters['idPrestation'].'</idPrestation>';
    else $ret.='<idPrestation>-1</idPrestation>';
    $ret.='</GetPrestationTIF></soap:Body>';
    return $ret;
  }

  /// Fonction pour checker l'état du webservice de GITD (avec die pour rq)
  function GITDCheckService($ar=NULL){
    $ret=$this->GITDCheckService2($ar);
    if($ret) die('true');
    else die('false');
  }

  /// Fonction pour checker l'état du webservice de GITD (retourne true ou false)
  function GITDCheckService2($ar=NULL){
    $filter=$this->GITDFilter;
    ini_set('soap.wsdl_cache_enabled', 0);
    $soap=new \SoapClient($this->GITDUrl,array('exceptions' => false));
    $params=\Seolan\Core\System::xml2array($filter);
    foreach($params as $param){
      $rep=$soap->executeQueryN(array('arg0' => $this->GITDLogin,'arg1' => $this->GITDPwd,'arg2' => $param['requete'],'arg3' => 1));
      if(get_class($rep)=='SoapFault') return false;
      if(empty($rep)) return false;
      if(empty($rep->return)) return false;
      if(!is_array($rep->return)) $rep1=array($rep->return);
      else $rep1=$rep->return;
      foreach($rep1 as $i => $entry){
	if(empty($entry)) return false;
	if(!isset($entry->id)) return false;
	if(empty($entry->id)) return false;
      }
    }
    return true;
  }

  /// Procédure d'import depuis le webservice de GITD
  function GITDImport(/*$ar=NULL*/$sched, $o, $more) {
    if(!$this->GITDCheckService2($ar)){
      if(!empty($this->reportto)){
	$GLOBALS['XUSER']->sendMail2User('TiF : Erreur import GITD',"Webservice non operationel ou erreur de requete",$this->reportto);
      }
      return "Webservice non operationel ou erreur de requete";
    }
    $filter=$this->GITDFilter;
    ini_set('soap.wsdl_cache_enabled', 0);
    try {
      $soap = new \SoapClient($this->GITDUrl, array('exceptions' => true));
    } catch (\SoapFault $e) {
      $err_msg = "Soap Error : " . $e->faultcode . ', ' . $e->faultstring ."\n";
      $sched->setStatusJob($o->KOID, 'running', $err_msg);
      return $err_msg;
    }
    $params=\Seolan\Core\System::xml2array($filter);
    foreach($params as $rechname => $param){
      $this->acttype='GITD ('.$rechname.')';
      $rep=$soap->executeQueryN(array('arg0' => $this->GITDLogin,'arg1' => $this->GITDPwd,'arg2' => $param['requete'],'arg3' => $param['limit']));
      if(!is_array($rep->return)) $rep->return=array($rep->return);
      if(strpos($param['princtable'],'Ejb3Asc')===0) $idsql=$this->GITDStructAsc($rep,$soap,$param['princtable']);
      elseif(strpos($param['princtable'],'Ejb3Pcu')===0) $idsql=$this->GITDStructPcu($rep,$soap,$param['princtable']);
      elseif(strpos($param['princtable'],'Ejb3Div')===0) $idsql=$this->GITDStructDiv($rep,$soap,$param['princtable']);
      elseif($param['princtable']=='Ejb3Loi') $idsql=$this->GITDStructLoi($rep,$soap);
      elseif($param['princtable']=='Ejb3Fma') $idsql=$this->GITDStructFma($rep,$soap);
      elseif($param['princtable']=='Ejb3Ski') $idsql=$this->GITDStructSki($rep,$soap);
      elseif($param['princtable']=='Ejb3Pna') $idsql=$this->GITDStructPna($rep,$soap);

      // Suppression des élémenents qui ne sont pas dans l'import
      if(empty($idsql)) return "Webservice non operationel ou erreur de requete";
      $rs=getDB()->select('select KOID from '.$this->table.' where tzr_type="'.$this->acttype.'" and '.
		       'dc__identifier not in ("'.implode('","',$idsql).'")');
      while($rs && ($ors=$rs->fetch())){
	$this->del(array('oid' => $ors['KOID'],'_nolog' => true));
      }
    }
  }

  /// Analayse les activités sports/cult de GITD
  function GITDStructAsc(&$rep,&$soap,$type){
    $idsql=array();
    foreach($rep->return as $i => $entry){
      $state=$this->GITDGetEntryState($entry);
      $oiddc=$state['oiddc'];
      if(empty($oiddc)) continue;
      $idsql[]=$entry->id;
      if(!$state['state']) continue;
      $dcparam=array('id' => 'dc__identifier','societe' => 'dc__title','descom' => 'dc__description','modifie' => 'dcterms__modified',
 		     'enfan' => 'gitd_enfants','ascTypstruct' => 'gitd_ts','catPrest' => 'gitd_typep','classification' => 'Classification');
      if($type=='Ejb3AscSport'){
 	$dcparam['sport']='acvs__ClassificationSousCategorie';
 	$entry->classification='02.01.01.02';
      }elseif($type=='Ejb3AscCult'){
 	$dcparam['cult']='acvs__ClassificationSousCategorie';
 	$entry->classification='02.01.01.01';
      }
      $this->GITDGetDC($oiddc,$entry,$soap,$dcparam);
      $this->GITDGetLang($oiddc,$entry,$soap);
      $this->GITDGetContact($oiddc,$entry,$soap);
      $this->GITDGetOuverture($oiddc,$entry,$soap);
      $this->GITDGetAccessibilite($oiddc,$entry,$soap);
      $this->GITDGetTarif($oiddc,$entry,$soap);
      $this->GITDGetGeoloc($oiddc,$entry,$soap);
      $this->GITDGetMultimedia($oiddc,$entry,$soap);
      $this->GITDGetDescrComp($oiddc,$entry,$soap);
    }
    return $idsql;
  }
  /// Analayse les patrimoines culturels
  function GITDStructPcu(&$rep,&$soap,$type){
    $idsql=array();
    foreach($rep->return as $i => $entry){
      $state=$this->GITDGetEntryState($entry);
      $oiddc=$state['oiddc'];
      if(empty($oiddc)) continue;
      $idsql[]=$entry->id;
      if(!$state['state']) continue;
      $dcparam=array('id' => 'dc__identifier','societe' => 'dc__title','descom' => 'dc__description','modifie' => 'dcterms__modified',
 		     'classification' => 'Classification','idTheme' => 'acvs__ClassificationSousCategorie');
      $entry->classification='02.01.11';
      $this->GITDGetDC($oiddc,$entry,$soap,$dcparam);
      $this->GITDGetLang($oiddc,$entry,$soap);
      $this->GITDGetContact($oiddc,$entry,$soap);
      $this->GITDGetOuverture($oiddc,$entry,$soap);
      $this->GITDGetGeoloc($oiddc,$entry,$soap);
      $this->GITDGetMultimedia($oiddc,$entry,$soap);
      $this->GITDGetDescrComp($oiddc,$entry,$soap);
    }
    return $idsql;
  }
  /// Analayse les structures diverses
  function GITDStructDiv(&$rep,&$soap,$type){
    $idsql=array();
    foreach($rep->return as $i => $entry){
      $state=$this->GITDGetEntryState($entry);
      $oiddc=$state['oiddc'];
      if(empty($oiddc)) continue;
      $idsql[]=$entry->id;
      if(!$state['state']) continue;
      $dcparam=array('id' => 'dc__identifier','societe' => 'dc__title','modifie' => 'dcterms__modified',
 		     'classification' => 'Classification','idType' => 'acvs__ClassificationSousCategorie');
      $entry->classification='02.01.10';
      $this->GITDGetDC($oiddc,$entry,$soap,$dcparam);
      $this->GITDGetContact($oiddc,$entry,$soap);
      $this->GITDGetGeoloc($oiddc,$entry,$soap);
      $this->GITDGetMultimedia($oiddc,$entry,$soap);
    }
    return $idsql;
  }
  /// Analayse les patrimoines naturels
  function GITDStructPna(&$rep,&$soap,$type){
    $idsql=array();
    foreach($rep->return as $i => $entry){
      $state=$this->GITDGetEntryState($entry);
      $oiddc=$state['oiddc'];
      if(empty($oiddc)) continue;
      $idsql[]=$entry->id;
      if(!$state['state']) continue;
      $dcparam=array('id' => 'dc__identifier','nom' => 'dc__title','descom' => 'dc__description','modifie' => 'dcterms__modified',
 		     'classification' => 'Classification','idType' => 'acvs__ClassificationSousCategorie');
      $entry->classification='02.01.12';
      $this->GITDGetDC($oiddc,$entry,$soap,$dcparam);
      $this->GITDGetLang($oiddc,$entry,$soap);
      $this->GITDGetContact($oiddc,$entry,$soap);
      $this->GITDGetOuverture($oiddc,$entry,$soap);
      $this->GITDGetGeoloc($oiddc,$entry,$soap);
      $this->GITDGetMultimedia($oiddc,$entry,$soap);
      $this->GITDGetDescrComp($oiddc,$entry,$soap);
    }
    return $idsql;
  }
  /// Analayse les loisirs de GITD
  function GITDStructLoi(&$rep,&$soap){
    $idsql=array();
    foreach($rep->return as $i => $entry){
      $state=$this->GITDGetEntryState($entry);
      $oiddc=$state['oiddc'];
      if(empty($oiddc)) continue;
      $idsql[]=$entry->id;
      if(!$state['state']) continue;
      $entry->classification='02.01.08';
      $this->GITDGetDC($oiddc,$entry,$soap,array('id' => 'dc__identifier','societe' => 'dc__title','descom' => 'dc__description',
 						 'modifie' => 'dcterms__modified','sport' => 'acvs__ClassificationSousCategorie',
 						 'idObjet' => 'gitd_typep','classification' => 'Classification'));
      $this->GITDGetLang($oiddc,$entry,$soap);
      $this->GITDGetContact($oiddc,$entry,$soap);
      $this->GITDGetOuverture($oiddc,$entry,$soap);
      $this->GITDGetAccessibilite($oiddc,$entry,$soap);
      $this->GITDGetTarif($oiddc,$entry,$soap);
      $this->GITDGetGeoloc($oiddc,$entry,$soap);
      $this->GITDGetMultimedia($oiddc,$entry,$soap);
      $this->GITDGetDescrComp($oiddc,$entry,$soap);
    }
    return $idsql;
  }
 
  /// Analyse les evenements/mannifestations de GITD
  function GITDStructFma(&$rep,&$soap){
    $idsql=array();
    foreach($rep->return as $i => $entry){
      $state=$this->GITDGetEntryState($entry);
      $oiddc=$state['oiddc'];
      if(empty($oiddc)) continue;
      $idsql[]=$entry->id;
      if(!$state['state']) continue;
      $entry->classification='02.01.03';
      $this->GITDGetDC($oiddc,$entry,$soap,array('id' => 'dc__identifier','nom' => 'dc__title','descriptif' => 'dc__description',
						 'modifie' => 'dcterms__modified','idType' => 'acvs__ClassificationSousCategorie',
 						 'classification' => 'Classification'));
      $this->GITDGetContact($oiddc,$entry,$soap);
      $this->GITDGetOuvertureManif($oiddc,$entry,$soap);
      $this->GITDGetTarif($oiddc,$entry,$soap);
      $this->GITDGetGeoloc($oiddc,$entry,$soap);
      $this->GITDGetMultimedia($oiddc,$entry,$soap);
      if(!empty($entry->lieu)){
	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DDESCRCOMP');
 	$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':16-01-03',
 			       'att_Description_type' => $xset->desc['att_Description_type']->target.':16-02-01',
 			       'Description' => $entry->lieu));
      }
    }
    return $idsql;
  }
  /// Analyse stations de ski de GITD
  function GITDStructSki(&$rep,&$soap){
    $idsql=array();
    foreach($rep->return as $i => $entry){
      $state=$this->GITDGetEntryState($entry);
      $oiddc=$state['oiddc'];
      if(empty($oiddc)) continue;
      $idsql[]=$entry->id;
      if(!$state['state']) continue;
      $this->GITDGetDC($oiddc,$entry,$soap,array('id' => 'dc__identifier','nom' => 'dc__title','modifie' => 'dcterms__modified',
 						 'descom' => 'dc__description'));
      $this->GITDGetContact($oiddc,$entry,$soap);
      $this->GITDGetGeoloc($oiddc,$entry,$soap);
      $this->GITDGetMultimedia($oiddc,$entry,$soap);
      $this->GITDGetPrestaSki($oiddc,$entry,$soap);
      $entry->cueilcompl='Hiver';
      $this->GITDGetOuverture($oiddc,$entry,$soap);
    }
    return $idsql;
  }
  
  /// Genere l'oid d'une entrée et verifie s'il doit etre importer (efface une eventuelle entrée existante)
  function GITDGetEntryState(&$entry){
    if(empty($entry->id)) return array('oiddc' => '','state' => false);
    $oiddc=$this->table.':'.preg_replace('/[^a-z0-9_-]/i','_',$entry->id);
    $rs=&getDB()->select('select KOID,dcterms__modified from '.$this->table.' where KOID="'.$oiddc.'"');
    if($ors=$rs->fetch()) {
      if($this->xset->desc['dcterms__modified']->get_ftype()=='\Seolan\Field\Date\Date') $date=date('Y-m-d',strtotime($entry->modifie));
      else $date=date('Y-m-d H:i:s',strtotime($entry->modifie));
      if($ors['dcterms__modified'] < $date) $this->del(array('oid' => $oiddc));
      else return array('oiddc' => $oiddc,'state' => false);
    }
    $this->recursiveAddslashes($entry);
    return array('oiddc' => $oiddc,'state' => true);
  }
 
  /// Renseigne le DC avec les données GITD
  function GITDGetDC($oiddc,&$entry,&$soap,$param){
    $ar2=array();
    foreach($param as $ef => $f){
      if(!empty($ar2[$f]) || empty($this->xset->desc[$f])) continue;
      if($this->xset->desc[$f]->isLink()) $ar2[$f]=$this->V2toV3($this->xset->desc[$f]->target,$entry->$ef);
      elseif($this->xset->desc[$f]->get_ftype()=='\Seolan\Field\Boolean\Boolean') $ar2[$f]=!empty($entry->$ef)?1:2;
      else $ar2[$f]=@$entry->$ef;
    }
    $ar2['tzr_type']=$this->acttype;
    $ar2['newoid']=$oiddc;
    $ret=$this->xset->procInput($ar2);
  }
 
  /// Renseigne les langues avec les données GITD
  function GITDGetLang($oiddc,&$entry,&$soap){
    if(!empty($entry->idParle)){
      if(!is_array($entry->idParle)) $entry->idParle=array($entry->idParle);
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'USAGE');
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':11-01-01',
 			     'Langue' => implode(',',$entry->idParle)));;
    }
  }
 
  /// Renseigne les contacts/moyens de communication avec les données GITD
  function GITDGetContact($oiddc,&$entry,&$soap){
    if(!empty($entry->adresse1) || !empty($entry->adresse2)  || !empty($entry->adresse3)|| !empty($entry->codePoste) || 
       !empty($entry->commune) ||
       !empty($entry->coordCom) || !empty($entry->coordCom2) || !empty($entry->coordCom4) || !empty($entry->coordCom5) ||
       !empty($entry->coordCom21) || !empty($entry->coordCom6) || !empty($entry->coordCom7) || !empty($entry->coordCom10) ||
       !empty($entry->coordCom11) || !empty($entry->coordCom20) ||
       !empty($entry->idCiv) || !empty($entry->nomCont) || !empty($entry->prenomCont) || !empty($entry->fonctionCont) ||
       !empty($entry->adr1Cont) || !empty($entry->adr2Cont) || !empty($entry->cpCont) || !empty($entry->communeCont)){
      // Contact
      if(!empty($entry->idCiv) || !empty($entry->nomCont) || !empty($entry->prenomCont) || !empty($entry->fonctionCont) ||
 	 !empty($entry->adr1Cont) || !empty($entry->adr2Cont) || !empty($entry->cpCont) || !empty($entry->communeCont)){
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DCONTACT');
 	$ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':04-03-18',
 				    'RaisonSociale' => $entry->societe));
 	$com=$this->GITDGetCommune($entry->communeCont,$soap);
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DADRESSE');
 	$ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'Adr1' => $entry->adr1Cont,'Adr2' => $entry->adr2Cont,
 				    'CodePostal' => $entry->cpCont,'BureauDistrib' => $com->nom));
 	if(!empty($entry->idCiv) || !empty($entry->nomCont) || !empty($entry->prenomCont) || !empty($entry->fonctionCont)){
 	  $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DPERSONNE');
 	  $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],
 				      'att_type' => $xset->desc['att_type']->target.':04-04-02',
 				      'Civilite' => $this->V2toV3($xset->desc['Civilite']->target,$entry->idCiv),
 				      'Prenom' => $entry->prenomCont,'Nom' => $entry->nomCont,'Fonction' => $entry->fonctionCont));
 	}
      }
      // Adresse-Moyen de com
      if(!empty($entry->adresse1) || !empty($entry->adresse2) || !empty($entry->adresse3) || !empty($entry->codePoste) || 
 	 !empty($entry->commune) ||
 	 !empty($entry->coordCom) || !empty($entry->coordCom2) || !empty($entry->coordCom4) || !empty($entry->coordCom5) ||
 	 !empty($entry->coordCom21) || !empty($entry->coordCom6) || !empty($entry->coordCom7) || !empty($entry->coordCom10) ||
 	 !empty($entry->coordCom11) || !empty($entry->coordCom20)){
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DCONTACT');
 	$ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':04-03-13',
 				    'RaisonSociale' => $entry->societe,'Sigle' => $entry->sigle));
 	$com=$this->GITDGetCommune($entry->commune,$soap);
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DADRESSE');
 	$ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'Adr1' => $entry->adresse1,'Adr2' => $entry->adresse2,
 				    'Adr3' => $entry->adresse3,'CodePostal' => $entry->codePoste,'BureauDistrib' => $com->nom));
 	if(!empty($entry->coordCom) || !empty($entry->coordCom2) || !empty($entry->coordCom4) || !empty($entry->coordCom5) ||
 	   !empty($entry->coordCom21) || !empty($entry->coordCom6) || !empty($entry->coordCom7) || !empty($entry->coordCom10) ||
 	   !empty($entry->coordCom11) || !empty($entry->coordCom20)){
 	  $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DPERSONNE');
 	  $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],
 				      'att_type' => $xset->desc['att_type']->target.':04-04-05'));
 	  $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DMOYENCOM');
 	  if(!empty($entry->coordCom)){
 	    $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':04-02-01',
 				   'Coord' => $entry->coordCom,'ObservationDetailMoyenCom' => 'num1'));
 	  }
 	  if(!empty($entry->coordCom2)){
 	    $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':04-02-02',
 				   'Coord' => $entry->coordCom2));
 	  }
 	  if(!empty($entry->coordCom4)){
 	    $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':04-02-04',
 				   'Coord' => $entry->coordCom4));
 	  }
 	  if(!empty($entry->coordCom5)){
 	    $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':04-02-05',
 				   'Coord' => $entry->coordCom5,'ObservationDetailMoyenCom' => 'num1'));
 	  }
 	  if(!empty($entry->coordCom21)){
 	    $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':04-02-05',
 				   'Coord' => $entry->coordCom21,'ObservationDetailMoyenCom' => 'num2'));
 	  }
 	  if(!empty($entry->coordCom6)){
 	    $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':04-02-01',
 				   'Coord' => $entry->coordCom6,'ObservationDetailMoyenCom' => 'num2'));
 	  }
 	  if(!empty($entry->coordCom7)){
 	    $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':04-02-01',
 				   'Coord' => $entry->coordCom7,'ObservationDetailMoyenCom' => 'num3'));
 	  }
 	  if(!empty($entry->coordCom10)){
 	    $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':04-02-05',
 				   'Coord' => $entry->coordCom10,'ObservationDetailMoyenCom' => 'coordCom10'));
 	  }
 	  if(!empty($entry->coordCom20)){
 	    $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':04-02-05',
 				   'Coord' => $entry->coordCom20,'ObservationDetailMoyenCom' => 'coordCom20'));
 	  }
 	  if(!empty($entry->coordCom11)){
 	    $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':04-02-05',
 				   'Coord' => $entry->coordCom11,'ObservationDetailMoyenCom' => 'coordCom11'));
 	  }
 	}
      }
    }
  }
 
  /// Renseigne les periodes d'ouvertures avec les données GITD (format loisirs)
  function GITDGetOuverture($oiddc,&$entry,&$soap){
    if(!empty($entry->ouvacPerm)){
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DPERIODE');
      $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':09-01-06'));
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DDATE');
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'DateDebut' => date('Y').'-01-01','DateFin' => date('Y').'-12-31',
 			     'ObservationDates' => 'permanant'));
    }elseif((!empty($entry->datDebutOuvac) && !empty($entry->datFinOuvac)) || (!empty($entry->datDebutOuvac2) && !empty($entry->datFinOuvac2)) || (!empty($entry->datDebutOuvac3) && !empty($entry->datFinOuvac3))){
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DPERIODE');
      $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':09-01-06'));
      if(!empty($entry->datDebutOuvac) && !empty($entry->datFinOuvac)){
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DDATE');
 	$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'DateDebut' => $entry->datDebutOuvac,
 			       'DateFin' => $entry->datFinOuvac,'ObservationDates' => $entry->cueilcompl));
      }
      if(!empty($entry->datDebutOuvac2) && !empty($entry->datFinOuvac2)){
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DDATE');
 	$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'DateDebut' => $entry->datDebutOuvac2,
 			       'DateFin' => $entry->datFinOuvac2,'ObservationDates' => $entry->cueilcompl));
      }
      if(!empty($entry->datDebutOuvac3) && !empty($entry->datFinOuvac3)){
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DDATE');
 	$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'DateDebut' => $entry->datDebutOuvac3,
 			       'DateFin' => $entry->datFinOuvac3,'ObservationDates' => $entry->cueilcompl));
      }
    }
  }
  
  /// Renseigne les periodes d'ouvertures avec les données GITD (format manif)
  function GITDGetOuvertureManif($oiddc,&$entry,&$soap){
    if(!empty($entry->datDebut) && !empty($entry->datFin)){
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DPERIODE');
      $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':09-01-06'));
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DDATE');
      $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'DateDebut' => $entry->datDebut,'DateFin' => $entry->datFin));
      if(!empty($entry->ouverture1) && !empty($entry->fermeture1)){
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DJOURS');
 	$ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':03-09-02'));
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'JOURS');
 	$ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':09-02-08'));
	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'HORAIRES');
 	$ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'HoraireDebut' => $entry->ouverture1,
 				    'HoraireFin' => $entry->fermeture1));
      }
    }
  }
   
  /// Renseigne les accessibilités avec les données GITD
  function GITDGetAccessibilite($oiddc,&$entry,&$soap){
    if(!empty($entry->idAcchandi)){
      $handicap=array('AUD' => 'Handicap auditif','MOT' => 'Handicapé moteur','MEN' => 'Handicape mental','VIS' => 'Handicapé visuel');
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DOFFREPRESTA');
      $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':15-01'));
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DPRESTA');
      $descrhandicap=array();
      foreach($entry->idAcchandi as $code) $descrhandicap[]=$handicap[$code];
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':15-01-01',
 			     'DescriptionPrestation' => implode('/',$descrhandicap)."\r\n".$entry->deschandi));
    }
  }
 
  /// Renseigne les tarifs avec les données GITD
  function GITDGetTarif($oiddc,&$entry,&$soap){
    if(!empty($entry->euroTarifs) && empty($entry->gratuit)){
      if(!empty($entry->euroTarifs->debut) && !empty($entry->euroTarifs->fin)){
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DPERIODE');
 	$ret=$xset->procInput(array('tzr_dc' => $oiddc,'att_type' => $xset->desc['att_type']->target.':09-01-08'));
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DDATE');
 	$tp=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'DateDebut' => $entry->euroTarifs->debut,
 				   'DateFin' => $entryeuroTarifs->fin));
      }
      if(!empty($entry->euroTarifs->valeurMini) || !empty($entry->euroTarifs->valeurMaxi)){
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DTARIF');
 	$xset->procInput(array('tzr_dc' => $oiddc,'TarifMax' => $entry->euroTarifs->valeurMaxi,
 			       'TarifMin' => $entry->euroTarifs->valeurMini,'att_type' => $xset->desc['att_type']->target.':13-04-01-01',
 			       'DetailPeriode' => $tp['oid'],'tzr_lie' => $oiddc));
      }
    }elseif(!empty($entry->gratuit)){
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DTARIF');
      $xset->procInput(array('tzr_dc' => $oiddc,'att_type' => $xset->desc['att_type']->target.':13-04-01-34',
 			     'DetailPeriode' => $tp['oid'],'tzr_lie' => $oiddc));
    }
  }
 
  /// Renseigne les geolocalisations avec les données GITD
  function GITDGetGeoloc($oiddc,&$entry,&$soap){
    if(!empty($entry->latitude) || !empty($entry->longitude) || !empty($entry->altitude) || !empty($entry->altMin) || !empty($entry->altMax)){
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DGEOLOC');
      $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':08-01-03'));
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'ZONES');
      $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':08-02-07-02'));
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DPOINT');
      $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':08-02-05-11'));
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DCOOR');
      $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'att_type' => $xset->desc['att_type']->target.':08-02-02-03',
 				  'Longitude' => $entry->longitude,'Latitude' => $entry->latitude,'Altitude' => $entry->altitude,
 				  'att_Altitude_altitudeMini' => $entry->altMin,'att_Altitude_altitudeMaxi' => $entry->altMax));
    }
  } 
 
  /// Renseigne les medias avec les données GITD
  function GITDGetMultimedia($oiddc,&$entry,&$soap){
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DMULTIMEDIA');
    if(!empty($entry->photo)){
      $entry->photoUrl=$this->GITDUrlDomain.$entry->photoUrl;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-01',
 			     'LegendeRessource' => $entry->photoLegende,'URL_img' => $entry->photoUrl,'Nom' => 'Structure'));
    }
    if(!empty($entry->photoenv)){
      $entry->photoenvUrl=$this->GITDUrlDomain.$entry->photoenvUrl;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-01',
 			     'LegendeRessource' => $entry->photoenvLegende,'URL_img' => $entry->photoenvUrl,'Nom' => 'Environnement'));
    }
    if(!empty($entry->photoprest)){
      $entry->photoprestUrl=$this->GITDUrlDomain.$entry->photoprestUrl;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-01',
 			     'LegendeRessource' => $entry->photoprestLegende,'URL_img' => $entry->photoprestUrl,'Nom' => 'Prestataire'));
    }
    if(!empty($entry->photo1)){
      $entry->photo1Url=$this->GITDUrlDomain.$entry->photo1Url;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-01',
 			     'LegendeRessource' => $entry->photo1Legende,'URL_img' => $entry->photo1Url,'Nom' => 'Photo 1'));
    }
    if(!empty($entry->photo2)){
      $entry->photo2Url=$this->GITDUrlDomain.$entry->photo2Url;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-01',
 			     'LegendeRessource' => $entry->photo2Legende,'URL_img' => $entry->photo2Url,'Nom' => 'Photo 2'));
    }
    if(!empty($entry->photo3)){
      $entry->photo3Url=$this->GITDUrlDomain.$entry->photo3Url;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-01',
 			     'LegendeRessource' => $entry->photo3Legende,'URL_img' => $entry->photo3Url,'Nom' => 'Photo 3'));
    }
    if(!empty($entry->photo4)){
      $entry->photo4Url=$this->GITDUrlDomain.$entry->photo4Url;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-01',
 			     'LegendeRessource' => $entry->photo4Legende,'URL_img' => $entry->photo4Url,'Nom' => 'Photo 4'));
    }
    if(!empty($entry->photo5)){
      $entry->photo5Url=$this->GITDUrlDomain.$entry->photo5Url;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-01',
 			     'LegendeRessource' => $entry->photo5Legende,'URL_img' => $entry->photo5Url,'Nom' => 'Photo 5'));
    }
    if(!empty($entry->photoHiver)){
      $entry->photoHiverUrl=$this->GITDUrlDomain.$entry->photoHiverUrl;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-01',
 			     'LegendeRessource' => $entry->legende,'URL_img' => $entry->photoHiverUrl,'Nom' => 'Photo hiver'));
    }
    if(!empty($entry->photoHiver2)){
      $entry->photoHiver2Url=$this->GITDUrlDomain.$entry->photoHiver2Url;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-01',
 			     'LegendeRessource' => $entry->legende2,'URL_img' => $entry->photoHiver2Url,'Nom' => 'Photo été'));
    }
    if(!empty($entry->logo)){
      $entry->logoUrl=$this->GITDUrlDomain.$entry->logoUrl;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-01',
 			     'URL_img' => $entry->logoUrl,'Nom' => 'Logo'));
    }
    if(!empty($entry->document)){
      $entry->documentUrl=$this->GITDUrlDomain.$entry->documentUrl;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-02',
			     'URL' => $entry->documentUrl,'Nom' => 'Document'));
    }
    if(!empty($entry->documentPdf)){
      $entry->documentPdfUrl=$this->GITDUrlDomain.$entry->documentPdfUrl;
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':03-01-02',
 			     'URL' => $entry->documentPdfUrl,'Nom' => 'Document PDF'));
    }
  }
 
  /// Renseigne les descriptions complémentaires avec les données GITD
  function GITDGetDescrComp($oiddc,&$entry,&$soap){
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DDESCRCOMP');
    if(!empty($entry->orientation)){
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':16-01-03',
 			     'att_Description_type' => $xset->desc['att_Description_type']->target.':16-02-101',
 			     'Description' => $entry->orientation));
    }
    if(!empty($entry->accescompl)){
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':16-01-03',
 			     'att_Description_type' => $xset->desc['att_Description_type']->target.':16-02-12',
 			     'Description' => $entry->accescompl));
    }
    if(!empty($entry->cueilcompl)){
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':16-01-03',
 			     'att_Description_type' => $xset->desc['att_Description_type']->target.':16-02-103',
 			     'Description' => $entry->cueilcompl));
    }
    if(!empty($entry->descenv)){
      $xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':16-01-03',
 			     'att_Description_type' => $xset->desc['att_Description_type']->target.':16-02-102',
 			     'Description' => $entry->descenv));
    }
  }

  /// Renseigne les prestations en rapport avec le ski
  function GITDGetPrestaSki($oiddc,&$entry,&$soap){
    if(!empty($entry->lgAlpin) || !empty($entry->nbNoir) || !empty($entry->nbRouge) || !empty($entry->nbBleu) || !empty($entry->nbVert) || !empty($entry->nbTelecab) || !empty($entry->nbTelesie) || !empty($entry->nbTeleski) || !empty($entry->lgFond)){
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DOFFREPRESTA');
      $ret=$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $oiddc,'att_type' => $xset->desc['att_type']->target.':15-05'));
      if(!empty($entry->lgAlpin) || !empty($entry->nbNoir) || !empty($entry->nbRouge) || !empty($entry->nbBleu) || !empty($entry->nbVert)){
	$tot=$entry->nbNoir+$entry->nbRouge+$entry->nbBleu+$entry->nbVert;
	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DPRESTA');
	$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'Prestation' => $xset->desc['Prestation']->target.':15-05-78',
			       'NbPrest' => $tot,'Distance' => $entry->lgAlpin,
			       'att_Distance_unite' => $xset->desc['att_Distance_unite']->target.':01-03-02-01'));
      }
      if(!empty($entry->lgFond)){
	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DPRESTA');
	$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'Prestation' => $xset->desc['Prestation']->target.':15-05-79',
			       'Distance' => $entry->lgFond,'att_Distance_unite' => $xset->desc['att_Distance_unite']->target.':01-03-02-01'));
      }
      if(!empty($entry->nbTelecab) || !empty($entry->nbTelesie) || !empty($entry->nbTeleski)){
	$tot=$entry->nbTelecab+$entry->nbTelesie+$entry->nbTeleski;
	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'DPRESTA');
	$xset->procInput(array('tzr_dc' => $oiddc,'tzr_lie' => $ret['oid'],'Prestation' => $xset->desc['Prestation']->target.':15-05-194',
			       'NbPrest' => $tot));
      }
    }
  }

  /// Recupere les infos d'une comune via son identifiant
  function GITDGetCommune($id,$soap=NULL){
    if(empty($id)) return (object)array('nom' => '');
    if(empty($soap)) {
      ini_set('soap.wsdl_cache_enabled', 0);
      try {
        $soap = new \SoapClient($this->GITDUrl, array('exceptions'  =>  true));
      } catch (\SoapFault $e) {
        echo "Soap Error : " . $e->faultcode . ', ' . $e->faultstring ."\n";
        \Seolan\Core\Logs::critical('ModTif::GITDGetCommune', "Soap Error : " . $e->faultcode . ', ' . $e->faultstring );
        return false;
      }
    }
    $com=$soap->get(array('arg0' => $this->GITDLogin,'arg1' => $this->GITDPwd,'arg2' => "Ejb3Com",'arg3' => $id));
    return $com->return;
  }

  /// Transforme un code V2 en code V3, et le créé s'il n'existe pas
  function V2toV3($table,$value){
    if(empty($value) || empty($table)) return NULL;

    $ret=array();
    $vs=is_array($value)?$value:array($value);
    foreach($vs as $v){
      if(empty($v)){
	$ret[]='';
	continue;
      }
      if(preg_match('/^[A-Z0-9]+$/i',$v)){
 	$rs=getDB()->select('select distinct KOID from '.$table.' where codesv2 like "%|'.$v.'|%" or codesv2="'.$v.'"');
 	if($ors=$rs->fetch()) {
 	  $ret[]=$ors['KOID'];
 	}else{
 	  $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
 	  $ret2=$xset->procInput(array('code' => $v,'libelle' => $v,'codesv2' => $v,'newoid' => $table.':GITD_'.$v));
 	  $ret[]=$ret2['oid'];
 	}
      }else{
 	$ret[]=$table.':'.str_replace('.','-',$v);
      }
    }
    if(is_array($value)) return $ret;
    else return $ret[0];
  }
  
  /// Fonction d'import SITRA
  function SITRAImport($sched, $o=NULL, $more=NULL) {
    \Seolan\Core\Logs::notice('ModTif::SITRAImport', 'start import');
    $this->NSURI=$this->SITRANSUri;
    $params=\Seolan\Core\System::xml2array($this->SITRADirs);
    foreach($params as $rechname => $dir){
      $this->acttype='SITRA ('.$rechname.')';
      $list=array();
      $list2=array();
      $fdir=opendir($dir);
      // Liste les fichiers à traiter et les tri par date de création
      while($file=readdir($fdir)) { 
        $matches=array();
        if(is_file($dir.$file)){
          if(preg_match($this->SITRAExportFileRegex,$file,$matches) || preg_match($this->SITRADelFileRegex,$file,$matches))
            $list[filemtime($dir.$file)][]=$file;
          elseif(preg_match($this->SITRASelectionsFileRegex,$file,$matches))
            $list2[filemtime($dir.$file)][]=$file;
          elseif(preg_match($this->SITRAImagesFileRegex,$file))
            if ($more->keep_files)
              rename($dir.$file, TZR_VAR2_DIR.'sitra_backup/'.$file);
            else
            unlink($dir.$file);
        }
      }
      ksort($list);
      // Traite les imports et les suppressions
      if ($more->keep_files)
        @mkdir(TZR_VAR2_DIR.'sitra_backup/');
      foreach($list as $mfiles){
        foreach($mfiles as $file){
          \Seolan\Core\Logs::notice('ModTif::SITRAImport', "processing $file");
          $msg .= "processing $file\n";
          $i++;
          echo $i.' : '.$file.'...';
          if(preg_match($this->SITRAExportFileRegex,$file)){
            $this->_import(array('file' => $dir.$file));
          }elseif(preg_match($this->SITRADelFileRegex,$file)){
            $content=file_get_contents($dir.$file);
            $dom=new \DOMDocument();
            $dom->preserveWhiteSpace=false;
            $dom->validateOnParse = true;
            $dom->loadXML($content);
            $xpath=new \DOMXpath($dom);
            $todels=$xpath->query('/ListeOI/identifier');
            foreach($todels as $todel){
              $oid=$this->prefixSQL.'DC:'.preg_replace('/[^a-z0-9_-]/i','_',$todel->textContent);
              \Seolan\Core\Logs::notice('ModTif::SITRAImport', "deleting $oid");
              $this->del(array('oid' => $oid,'_options' => array('local' => true),'_nolog' => true));
            }
          }
          if ($more->keep_files)
            rename($dir.$file, TZR_VAR2_DIR.'sitra_backup/'.$file);
          else
            unlink($dir.$file);
        }
      }
      foreach($list2 as $mfiles2){
        foreach($mfiles2 as $file2){
          \Seolan\Core\Logs::notice('ModTif::SITRAImport', "processing $file2 for selection");
          $msg .= "processing $file2 for selection\n";
          $xssels = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.'LS_Selections');
          $content=file_get_contents($dir.$file2);
          $dom=new \DOMDocument();
          $dom->preserveWhiteSpace=false;
          $dom->validateOnParse = true;
          $dom->loadXML($content);
          $xpath=new \DOMXpath($dom);
          $sels=$xpath->query('/Selections/Selection');
          $selstorec=array();
          foreach($sels as $sel){
            $ar=array();
            $ar['tplentry']=TZR_RETURN_DATA;
            foreach($sel->attributes as $selatt){
              $attname = $selatt->name;
              $attfield = 'att_'.$attname;
              if(isset($xssels->desc[$attfield])){
                $value=$selatt->value;
                $ar[$attfield]=$value;
                if($attname == 'code')
                  $oid=$this->prefixSQL.'LS_Selections:'.$value;
              }
            }
            $rcss = getDB()->count('select count(*) from '.$this->prefixSQL.'LS_Selections where KOID="'.$oid.'" limit 1');
            if($rcss > 0){
              $ar['oid']=$oid;
              $xssels->procEdit($ar);
            }else{
              $ar['newoid']=$oid;
              $xssels->procInput($ar);
            }
            foreach($sel->childNodes as $node){
              $dcoid=$this->prefixSQL.'DC:'.preg_replace('/[^a-z0-9_-]/i','_',$node->nodeValue);
              $selstorec[$dcoid][]=$oid;
            }
          }
          foreach($selstorec as $oiddc => $asels){
            $rcdc = getDB()->count('select count(*) from '.$this->table.' where KOID="'.$oiddc.'" limit 1');
            if($rcdc){
              $this->procEdit(array('tplentry' => TZR_RETURN_DATA,'oid' => $oiddc,'dc__selection' => implode('||',$asels)));
            }
          }
          if (@$more->keep_files)
            rename($dir.$file2, TZR_VAR2_DIR.'sitra_backup/'.$file2);
          else
            unlink($dir.$file2);
        }
      }
    }
    if (is_object($sched))
      $sched->setStatusJob($o->KOID, 'finished', $msg);
    else
      echo $msg;
    \Seolan\Core\Logs::notice('ModTif::SITRAImport', 'end import');
  }

  /// Fonction addslashes recurcive
  function recursiveAddslashes(&$obj){
    foreach($obj as &$val){
      if(is_array($val) || is_object($val)) $this->recursiveAddslashes($val);
      else $val=$val;
    }
  }

  /**
   Export au format TiF.
   format : string : retourne  la chaine xml (si une ressource) ou tableau de chaine xml (si pls ressources)
            file : fichier xml (si une ressource) ou zip de xml (si pls ressources)
   attachment : true + format file + une ressource => retourne le fichier attaché, sinon ne sert à rien
  **/
  function exportTiF($ar){
    $p=new \Seolan\Core\Param($ar,array('format' => 'string','attachment' => false));
    $oids=$p->get('_selected');
    $format=$p->get('format');
    $attachment=$p->get('attachment');
    $selectedok=$p->get('_selectedok');
    $oids=array_keys($oids);
    if(($selectedok!='ok') || empty($oids)) $oids=$p->get('oid');
    if(!is_array($oids)) $oids=array($oid);
  
    $file='<?xml version="1.0" encoding="'.TZR_INTERNAL_CHARSET.'"?>';
    $this->prepareParam();
    $ret=array();
    foreach($oids as $oid){
      $file='<'.$this->makeBaliseName('OI').' xmlns="http://www.xsalto.com" xmlns:dc="http://purl.org/dc/elements/1.1/" '.
	'xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsalto="http://www.xsalto.com">';
      $file.=$this->exportDC($oid);                     // Dublin Core
      $file.=$this->exportMultimedia($oid);	        // Multimédia
      $file.=$this->exportContacts($oid); 	        // Contacts (Adresses - Personnes - Moyens de communications)
      $file.=$this->exportInfosLegales($oid);	        // Informations légales
      $file.=$this->exportClassements($oid);	        // Classements
      $file.=$this->exportGeolocs($oid);	        // Geolocalisation (Zones - Points - Coords - Envs - Cartes - Acces - Multimédia)
      $file.=$this->exportPeriodes($oid);	        // Periodes (Dates - Details jours - Jours - Horaires)
      $file.=$this->exportClienteles($oid);	        // Clientèles (Details client)
      $file.=$this->exportUsages($oid);	                // Usages/Langues
      $file.=$this->exportModesResa($oid);	        // Modes de réservation
      $file.=$this->exportCapacites($oid);	        // Capacités
      $file.=$this->exportOffresPresta($oid);	        // Offres de prestation (Prestations)
      $file.=$this->exportTarifs($oid);	                // Tarifs et modes de paiement
      $file.=$this->exportDescrComp($oid);	        // Descriptions complémentaires
      $file.=$this->exportItineraires($oid);	        // Itinéraires
      $file.=$this->exportPlannings($oid);	        // Plannings (Presta planning - Jour planning)
      $file.=$this->exportPresationsLiees($oid);    	// Prestations liées
      $file.=$this->exportOther($oid);	                // Fonctions à personnaliser
      $file.='</'.$this->makeBaliseName('OI').'>';
      $ret[$oid]=$file;
    }
    if($format=='string'){
      if(count($ret)<2) return $ret[$oids[0]];
      else return $ret;
    }elseif($format=='file'){
      if(count($ret)<2){
	ob_clean();
	header('Content-Type:text/xml; charset='.TZR_INTERNAL_CHARSET);
	header('Content-Transfer-Encoding:'.TZR_INTERNAL_CHARSET);
	header('Content-Length:'.strlen($ret[$oids[0]]));
	header('Pragma:private');
	header('Cache-Control:no-cache, must-revalidate');
	if($attachment) header('Content-disposition: attachment; filename=exporttif-'.$oids[0].'.xml');
	header('Expires: 0');
	echo $ret[$oids[0]];
	exit(0);
      }else{
	foreach($ret as $oid => $file){
	  $dir=TZR_TMP_DIR.'exporttif'.uniqid();
	  @mkdir($dir);
	  file_put_contents($dir.'/exporttif-'.$oid.'.xml',$file);
	}
	exec('(cd '.$dir.'; zip -r '.$dir.'.zip .)2>&1 > '.TZR_TMP_DIR.'errorlog');
	$size=filesize($dir.'.zip');
	header('Content-type: application/zip');
	header('Content-disposition: attachment; filename=exporttif.zip');
	header('Accept-Ranges: bytes');
	header('Content-Length: '.$size);
	@readfile($dir.'.zip');
	\Seolan\Library\Dir::unlink($dir);
	unlink($dir.'.zip');
	exit(0);
      }
    }
  }
  function exportDC($oid){
    return $this->exportGranule($oid,$this->structparam['dc']);
  }
  function exportMultimedia($oid){
    return $this->exportGranule($oid,$this->structparam['mu']);
  }
  function exportContacts($oid){
    return $this->exportGranule($oid,$this->structparam['co']);
  }
  function exportInfosLegales($oid){
    return $this->exportGranule($oid,$this->structparam['il']);
  }
  function exportClassements($oid){
    return $this->exportGranule($oid,$this->structparam['cl']);
  }
  function exportGeolocs($oid){
    return $this->exportGranule($oid,$this->structparam['ge']);
  }
  function exportPeriodes($oid){
    return $this->exportGranule($oid,$this->structparam['pe']);
  }
  function exportClienteles($oid){
    return $this->exportGranule($oid,$this->structparam['cli']);
  }
  function exportUsages($oid){
    return $this->exportGranule($oid,$this->structparam['la']);
  }
  function exportModesResa($oid){
    return $this->exportGranule($oid,$this->structparam['mr']);
  }
  function exportCapacites($oid){
    $ret='<'.$this->makeBaliseName('Capacites').'>';
    $d=$this->xset->display(array('oid' => $oid,'tplentry' => TZR_RETURN_DATA));
    $ret.=$this->exportGranule($d['oCapacitesGlobales']->raw,array('balisegroup' => NULL,
							   'balises' => array('CapacitesGlobales' => array('table' => 'CAPAGLOB',
												       'fssm' => 'KOID',
												       'ssm' => $this->structparam['cs']))));
    $ret.=$this->exportGranule($oid,$this->structparam['cp']);
    $ret.=$this->exportGranule($oid,$this->structparam['cu']);
    $ret.='</'.$this->makeBaliseName('Capacites').'>';
    return $ret;
  }
  function exportOffresPresta($oid){
    return $this->exportGranule($oid,$this->structparam['op']);
  }
  function exportTarifs($oid){
    $lang=\Seolan\Core\Lang::getLocale();
    $lang=$lang['code'];

    $ret='<'.$this->makeBaliseName('Tarifs').'>';
    $ret.='<'.$this->makeBaliseName('ModesPaiement').'>';
    $d=$this->xset->display(array('oid' => $oid,'tplentry' => TZR_RETURN_DATA));
    $bn=$this->makeBaliseName('ModePaiement');
    foreach($d['oModePaiement']->oidcollection as $i => $oid2){
      $ret.='<'.$bn.' type="'.$d['oModePaiement']->collection[$i]->link['ocode']->raw.'" xml:lang="'.$lang.'">'.
	$d['oModePaiement']->collection[$i]->link['olibelle']->raw.'</'.$bn.'>';
    }
    $bn=$this->makeBaliseName('ObservationModePaiement');
    $ret.='<'.$bn.'>'.$d['oObservationModePaiement']->text.'</'.$bn.'>';
    $ret.='</'.$this->makeBaliseName('ModesPaiement').'>';
    $ret.=$this->exportGranule($oid,$this->structparam['ta']);
    $ret.='</'.$this->makeBaliseName('Tarifs').'>';
    return $ret;
  }
  function exportDescrComp($oid){
    return $this->exportGranule($oid,$this->structparam['desc']);
  }
  function exportItineraires($oid){
    return $this->exportGranule($oid,$this->structparam['it']);
  }
  function exportPlannings($oid){
    return $this->exportGranule($oid,$this->structparam['pla']);
  }
  function exportPresationsLiees($oid){
    return $this->exportGranule($oid,$this->structparam['pliee']);
  }
  function exportOther(&$oi,$oiddc){
  }
  function exportGranule($oidssm,$params){
    if(isset($params['balises'])) $params=array($params);
    $oids=array();
    $ret='';
    $lang=\Seolan\Core\Lang::getLocale();
    $lang=$lang['code'];
    foreach($params as $param){
      $balisegrp=$param['balisegroup'];
      $balises=$param['balises'];
      if(!empty($balisegrp)) $ret.='<'.$this->makeBaliseName($balisegrp).'>';

      foreach($balises as $balise => $data){
	$table=$data['table'];
	$fssm=isset($data['fssm'])?$data['fssm']:'KOID';
	$direct=isset($data['direct'])?$data['direct']:false;
	$delNS=isset($data['delNS'])?$data['delNS']:true;
	$notexport=isset($data['notexport'])?$data['notexport']:array();
	$linkparam=isset($data['linkparam'])?$data['linkparam']:array();
	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.$table);
	$cond=isset($data['exportcond'])?$data['exportcond']:array($fssm => array('LIKE',"%$oidssm%"));
	$cond=$xset->select_query(array('cond' => $cond));
	$br=$xset->browse(array('select' => $cond,'tplentry' => TZR_RETURN_DATA,'selectedfields' => 'all'));
	foreach($br['lines_oid'] as $i => $oid){
	  if(!isset($this->alreadyexport[$oid])){
	    $values=array();
	    $att='';
	    foreach($xset->desc as $fn => &$fo){
	      if(strpos($fn,'tzr_')===0 || strpos($fn,'gitd_')===0 || $fo->sys || in_array($fn,$notexport)) continue;
	      
	      $value=$cpltatt='';
	      switch($fo->ftype){
	      case '\Seolan\Field\Link\Link':
		if(strpos($fo->target,$this->prefixSQL.'LS_')===0){
		  $tmp=$this->withCodeAttr;
		  if(in_array($table,$tmp['table']) || in_array(str_replace($this->prefixSQL,'',$fo->target),$tmp['target']))
		    $typename='code';
		  else 
		    $typename='type';
		  
		  if(strpos($fn,'att_')!==0){
		    $value=trim($br['lines_o'.$fn][$i]->link['libelle']);
		    $myatt=trim($br['lines_o'.$fn][$i]->link['code']);
		    if(!empty($myatt)) $cpltatt.=' '.$typename.'="'.$myatt.'"';
		  }else{
		    $value=trim($br['lines_o'.$fn][$i]->link['code']);
		    $myatt=trim($br['lines_o'.$fn][$i]->link['libelle']);
		    if(!empty($myatt)) $cpltatt.=' libelle="'.$myatt.'"';
		  }
		  if($br['lines_o'.$fn][$i]->link['olibelle']->fielddef->translatable && strpos($cpltatt,'xml:lang'))
		    $cpltatt.=' xml:lang="'.$lang.'"';
		}else{
		  if($fo->multivalued){
		    $value='';
		    foreach($br['lines_o'.$fn][$i]->oidcollection as $loid)
		      $value.=$this->exportGranule($loid,$linkparam[$fn]);
		  }elseif(!empty($br['lines_o'.$fn][$i]->raw))
		    $value=$this->exportGranule($br['lines_o'.$fn][$i]->raw,$linkparam[$fn]);
		}
		break;
	      case '\Seolan\Field\File\File':
		$value=trim($br['lines_o'.$fn.'_url'][$i]->text);
		break;
	      case '\Seolan\Field\Boolean\Boolean':
		$value=($br['lines_o'.$fn][$i]->raw==1)?'O':'N';
		break;
	      default:
		if($fo->multivalued) $value=explode(',',trim($br['lines_o'.$fn][$i]->text));
		else $value=trim($br['lines_o'.$fn][$i]->text);
		break;
	      }
	      if(strpos($fn,'att_')!==0){
		$values[$fn]['value']=$value;
		if(empty($values[$fn]['att'])) $values[$fn]['att']=$cpltatt;
		if($fo->translatable && strpos($values[$fn]['att'],'xml:lang')) $values[$fn]['att'].=' xml:lang="'.$lang.'"';
	      }else{
		preg_match("/^att_(.+)_(.+)$/",$fn,$matches);
		if(empty($matches[2]) || $fn=='att_lib_jour'){
		  preg_match("/^att_(.+)$/",$fn,$matches);
		  if(!empty($value)) $att.=' '.$matches[1].'="'.$value.'"'.$cpltatt;
		  else $att.=$cpltatt;
		}else{
		  if(empty($values[$matches[1]]['att'])) $values[$matches[1]]['att']='';
		  if(!empty($value)) $values[$matches[1]]['att'].=' '.$matches[2].'="'.$value.'"'.$cpltatt;
		  else $values[$matches[1]]['att'].=$cpltatt;
		}
	      }
	    }
	    $this->alreadyexport[$oid]=$att;
	    $att.=' id="'.$oid.'"';
	    if(!$direct) $ret.='<'.$this->makeBaliseName($balise).$att.'>';
	    foreach($values as $b => $v){
	      if(!is_array($v['value'])) $v['value']=array($v['value']);
	      foreach($v['value'] as $val){
		$bn=$this->makeBaliseName($b,$delNS);
		if(!isset($linkparam[$b])) $ret.='<'.$bn.$v['att'].'>'.$val.'</'.$bn.'>';
		else $ret.=$val;
	      }
	    }
	    $ret.=$this->exportGranule($oid,$data['ssm']);
	    if(!$direct) $ret.='</'.$this->makeBaliseName($balise).'>';
	  }else{
	    $ret.='<'.$this->makeBaliseName($balise).' idref="'.$oid.'"'.$this->alreadyexport[$oid].'>'.
	      '</'.$this->makeBaliseName($balise).'>';
	  }
	}
      }
      if(!empty($balisegrp)) $ret.='</'.$this->makeBaliseName($balisegrp).'>';
    }
    return $ret;
  }

  /// Créé un nom de balise avec le namespace pour l'export
  function makeBaliseName($balise,$delNS=true){
    if($delNS) return 'xsalto:'.$balise;
    else return str_replace('__',':',$balise);;
  }
  
  /// Importe les fichiers de nomenclature
  function importNomenclature($ar=NULL){
    global $LIBTHEZORRO;
    $dir=$LIBTHEZORRO.'src/Module/Tif/misc/';
    $dd=opendir($dir.'nomenclatures/');
    while($file=readdir($dd)) {
      if(is_file($dir.'nomenclatures/'.$file) && preg_match("/^nomenclature_([a-zA-Z0-9_]*)\.csv$/",$file,$ret)){
 	$lines=file($dir.'nomenclatures/'.$file);
 	$xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->prefixSQL.$ret[1]);
 	foreach($lines as $i => $line){
 	  $data=explode(';',rtrim($line));
 	  if(!empty($data[0])) $oid=$this->prefixSQL.$ret[1].':'.str_replace('.','-',$data[0]);
 	  else $oid=$this->prefixSQL.$ret[1].':GITD_'.$data[2];
 	  if(isset($xset->desc['codesv2'])){
 	    getDB()->execute('insert ignore into '.$this->prefixSQL.$ret[1].' (KOID,LANG,UPD,code,libelle,codesv2) '.
 			'values("'.$oid.'","'.TZR_DEFAULT_LANG.'",NOW(),"'.$data[0].'","'.addslashes($data[1]).'","'.$data[2].'")');
 	  }else{
 	    getDB()->execute('insert ignore into '.$this->prefixSQL.$ret[1].' (KOID,LANG,UPD,code,libelle) '.
 			'values("'.$oid.'","'.TZR_DEFAULT_LANG.'",NOW(),"'.$data[0].'","'.addslashes($data[1]).'")');
 	  }
 	  if($xset->getTranslatable() && $xset->getAutoTranslate()) {
 	    $xk=new \Seolan\Core\Kernel;
 	    $xk->data_autoTranslate($oid);
 	  }
 	}
      }
    }
  }
  
  /// Check différents points sur l'ensemble de modules
  function chk(&$message=NULL){
    /// Dans le cas de GITD, la nomenclature doit etre présente
    if(in_array('GITD',$this->type)) $this->importNomenclature();
  }
}
?>
