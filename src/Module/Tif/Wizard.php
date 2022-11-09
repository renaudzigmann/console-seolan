<?php
namespace Seolan\Module\Tif;
class Wizard extends \Seolan\Core\Module\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
  }
  function istep1($ar=NULL){
    parent::istep1($ar);
    $this->_options->setOpt('Type de la structure', 'type', 'multiplelist', array('values'=>array('','ACVS','GITD','SITRA'),
										  'labels'=>array('Normale','ACVS','GITD','SITRA')));
  }

  function istep2($ar=NULL){
    $this->_options->setOpt('Importer le thésaurus','impth','boolean');
    if(in_array('GITD',$this->_module->type)){
      $this->_options->setRO('impth');
      $this->_module->impth=1;
    }else{
      $this->_module->impth=0;
    }
    $this->_options->setOpt('ACVS : Login', 'ACVSLogin', 'text');
    $this->_options->setOpt('ACVS : Password', 'ACVSPwd', 'text');
    $this->_options->setOpt('ACVS : Filtre', 'ACVSFilter', 'text', array('rows'=>6,'cols'=>80));
    $this->_module->ACVSFilter='<?xml version="1.0" encoding="utf-8"?>'."\r\n".'<tzrdata v="2">'."\r\n".'<table>'."\r\n".
      ' <tr>'."\r\n".'  <th>Rech1</th>'."\r\n".'  <td>'."\r\n".
      '   <table>'."\r\n".'    <tr>'."\r\n".'     <th></th><td></td>'."\r\n".'    </tr>'."\r\n".'   </table>'."\r\n".
      '  </td>'."\r\n".' </tr>'."\r\n".'</table>'."\r\n".'</tzrdata>';

    $this->_options->setOpt('GITD : Login', 'GITDLogin', 'text');
    $this->_options->setOpt('GITD : Password', 'GITDPwd', 'text');
    $this->_options->setOpt('GITD : Filtre', 'GITDFilter', 'text', array('rows'=>6,'cols'=>80));
    $this->_module->GITDFilter='<?xml version="1.0" encoding="utf-8"?>'."\r\n<tzrdata v=\"2\">\r\n<table>\r\n <tr>\r\n  <th>Rech1</th>".
      "\r\n  <td>\r\n   <table>\r\n    <tr>\r\n     <th>requete</th><td></td>\r\n    </tr>\r\n    <tr>\r\n     <th>limit</th><td></td>".
      "\r\n    </tr>\r\n    <tr>\r\n     <th>princtable</th><td></td>\r\n    </tr>\r\n   </table>\r\n  </td>\r\n </tr>\r\n</table>".
      "\r\n</tzrdata>";

    $this->_options->setOpt('SITRA : Répertoires', 'SITRARep', 'text', array('rows'=>6,'cols'=>80));
    $this->_module->SITRARep='<?xml version="1.0" encoding="utf-8"?>'."\r\n<tzrdata v=\"2\">\r\n<table>\r\n <tr>\r\n  <th>Rep1</th>".
      "\r\n  <td></td>\r\n </tr>\r\n</table>".
      "\r\n</tzrdata>";
  }
  function iend($ar=NULL) {
    global $LIBTHEZORRO;
    $dir=$LIBTHEZORRO.'src/Module/Tif/misc/';
    set_time_limit(120);

    // Determine les prefixes
    $prefixSQL='TiF';
    $tmax=1;
    while(\Seolan\Core\System::tableExists($prefixSQL.$tmax.'_DC') || \Seolan\Core\DataSource\DataSource::sourceExists($prefixSQL.$tmax.'_DC')) {
      $tmax++;
    }
    $prefixSQL.=$tmax.'_';
    $prefix='TiF'.$tmax.' - ';
    $this->_module->prefixSQL=$prefixSQL;
    $this->_module->table=$prefixSQL.'DC';
    $this->_module->trackchanges=0;
  
    // Créé les tables du module
    // Les fichiers csv étant enregistrés sous Linux, la fin de ligne n'est pas \r\n mais \n
    $x=new \Seolan\Module\DataSource\DataSource();
    $x->importSources(array('file'=>$dir.'tables_nomenclature.csv','endofline'=>"\n",'prefixSQL'=>$prefixSQL,'prefix'=>$prefix));
    $x->importFields(array('file'=>$dir.'fields_nomenclature.csv','endofline'=>"\n",'prefixSQL'=>$prefixSQL));
    $x->importSources(array('file'=>$dir.'tables.csv','endofline'=>"\n",'prefixSQL'=>$prefixSQL,'prefix'=>$prefix));
    $x->importFields(array('file'=>$dir.'fields.csv','endofline'=>"\n",'prefixSQL'=>$prefixSQL));
    \Seolan\Core\Shell::toScreen2('','message','');
    $moid=parent::iend();
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod=\Seolan\Core\Module\Module::objectFactory($moid);

    // Importe les données des différentes nomenclatures (import obligatoire pour GITD car thesaurus en V2)
    if(!empty($this->_module->impth) || in_array('GITD',$this->_module->type)){
      $mod->importNomenclature();
    }
 
    // Multimédia (SSM de DublinCore)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $ar=array();
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Multimédia)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DMULTIMEDIA';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moidmultimedia=$mod2->iend();
    $ar['options']['ssmodtitle1']='Multimédia';
    $ar['options']['ssmodfield1']='tzr_lie';
    $ar['options']['ssmod1']=$moidmultimedia;
    $ar['options']['ssmodactivate_additem1']=1;

    // SSM Contact (Adresses - Personnes - Moyens de communications)/DublinCore
    // Moyens de communication
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Moyens de communication)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DMOYENCOM';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Personnes (avec moyen de com en SSM)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Personnes)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DPERSONNE';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Moyen de communication';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Adresses (avec personnes en SSM)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Adresses)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DADRESSE';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Personne';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Contact (avec adresses en SSM) (SSM de DublinCore)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Contacts)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DCONTACT';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Adresse';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moidcontact=$mod2->iend();
    $ar['options']['ssmodtitle2']='Contact';
    $ar['options']['ssmodfield2']='tzr_lie';
    $ar['options']['ssmod2']=$moidcontact;
    $ar['options']['ssmodactivate_additem2']=1;

    // Infos légales (SSM de DublinCore)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Informations légales)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'INFOLEG';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle3']='Information légale';
    $ar['options']['ssmodfield3']='tzr_lie';
    $ar['options']['ssmod3']=$moid2;
    $ar['options']['ssmodactivate_additem3']=1;

    // Classements (SSM de DublinCore)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Classements)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DCLASSEMENT';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle4']='Classement';
    $ar['options']['ssmodfield4']='tzr_lie';
    $ar['options']['ssmod4']=$moid2;
    $ar['options']['ssmodactivate_additem4']=1;

    // SSM Geolocalisations (Zones - Points - Coordonnées - Environnements - Cartes - Acces - Multimédia)/DublinCore
    // Coordonnées
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Coordonnées)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DCOOR';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid3=$mod2->iend();
    // Environnements
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Environnements)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DENV';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid4=$mod2->iend();
    // Cartes
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Cartes)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DCARTE';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid5=$mod2->iend();
    // Acces
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Acces)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DACCES';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid6=$mod2->iend();
    // Points
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Points)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DPOINT';
    $mod2->_module->home=0;
    $mod2->_module->submodmax=5;
    $mod2->_module->ssmodtitle1='Coordonnées';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid3;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->ssmodtitle2='Environnement';
    $mod2->_module->ssmodfield2='tzr_lie';
    $mod2->_module->ssmod2=$moid4;
    $mod2->_module->ssmodactivate_additem2=1;
    $mod2->_module->ssmodtitle3='Carte';
    $mod2->_module->ssmodfield3='tzr_lie';
    $mod2->_module->ssmod3=$moid5;
    $mod2->_module->ssmodactivate_additem3=1;
    $mod2->_module->ssmodtitle4='Acces';
    $mod2->_module->ssmodfield4='tzr_lie';
    $mod2->_module->ssmod4=$moid6;
    $mod2->_module->ssmodactivate_additem4=1;
    $mod2->_module->ssmodtitle5='Multimedia';
    $mod2->_module->ssmodfield5='tzr_lie';
    $mod2->_module->ssmod5=$moidmultimedia;
    $mod2->_module->ssmodactivate_additem5=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Zones (avec points en SSM)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Zones)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'ZONES';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Point';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Geolocalisations (avec zones en SSM) (SSM de DublinCore)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Géolocalisations)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DGEOLOC';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Zone';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle5']='Géolocalisation';
    $ar['options']['ssmodfield5']='tzr_lie';
    $ar['options']['ssmod5']=$moid2;
    $ar['options']['ssmodactivate_additem5']=1;

    // SSM Periodes (Dates - Detail Jours, Jours, Horaires)/DublinCore
    // Horaires
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Horaires)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'HORAIRES';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Jours (avec horaires en SSM)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Jours)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'JOURS';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Horaire';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Détail jours (avec jours en SSM)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Détail Jours)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DJOURS';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Jour';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Dates (avec détail jours en SSM)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Dates)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DDATE';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Type de jour';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Périodes (avec dates en SSM) (SSM de DublinCore)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Période)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DPERIODE';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Date';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle6']='Période';
    $ar['options']['ssmodfield6']='tzr_lie';
    $ar['options']['ssmod6']=$moid2;
    $ar['options']['ssmodactivate_additem6']=1;

    // SSM Clientèle (Détail client)/DublinCore
    // Détail client
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Détail client)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DCLIENT';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Clientèle (avec détail client en SSM) (SSM de DublinCore)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Clientèles)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DCLIENTELES';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Client';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle7']='Clientèle';
    $ar['options']['ssmodfield7']='tzr_lie';
    $ar['options']['ssmod7']=$moid2;
    $ar['options']['ssmodactivate_additem7']=1;

    // SSM Usage-Langue/DublinCore
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Langues)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'USAGE';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle8']='Langue';
    $ar['options']['ssmodfield8']='tzr_lie';
    $ar['options']['ssmod8']=$moid2;
    $ar['options']['ssmodactivate_additem8']=1;

    // SSM Modes de reservation/DublinCore
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Modes de réservation)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DMODERESA';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Contact';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moidcontact;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moidmoderesa=$mod2->iend();
    $ar['options']['ssmodtitle9']='Mode de réservation';
    $ar['options']['ssmodfield9']='tzr_lie';
    $ar['options']['ssmod9']=$moidmoderesa;
    $ar['options']['ssmodactivate_additem9']=1;

    // Capacités : Capcités
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Détails capacités : capacités)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'CAPACAPA';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moidcapacapa=$mod2->iend();
    // Capacités : Superficie
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Détails capacités : superficies)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'CAPASUP';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moidcapasup=$mod2->iend();
    // Capacités globas (avec capacité et superficie en SSM)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Capacités globales)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'CAPAGLOB';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Capacité';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moidcapacapa;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->ssmodtitle2='Superficie';
    $mod2->_module->ssmodfield2='tzr_lie';
    $mod2->_module->ssmod2=$moidcapasup;
    $mod2->_module->ssmodactivate_additem2=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();

    // SSM capacités prestations (avec capacités et superficie en SSM) (SSM de DublinCore)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Capacités prestations)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DCAPAPREST';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Capacité';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moidcapacapa;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->ssmodtitle2='Superficie';
    $mod2->_module->ssmodfield2='tzr_lie';
    $mod2->_module->ssmod2=$moidcapasup;
    $mod2->_module->ssmodactivate_additem2=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle10']='Capacité prestations';
    $ar['options']['ssmodfield10']='tzr_lie';
    $ar['options']['ssmod10']=$moid2;
    $ar['options']['ssmodactivate_additem10']=1;

    // SSM Capacités unités (Dispositions)/DublinCore
    // Dispositions
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Dipositions)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DDISP';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Capacités unités (avec dispositions en SSM) (SSM de DublinCore)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Capacités unités)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DCAPAUNIT';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Disposition';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle11']='Capacité unité';
    $ar['options']['ssmodfield11']='tzr_lie';
    $ar['options']['ssmod11']=$moid2;
    $ar['options']['ssmodactivate_additem11']=1;

    // SSM offre de prestation (Détail prestation)
    // Details prestations
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Détails prestations)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DPRESTA';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Mode de réservation';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moidmoderesa;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Offre (avec prestations en SSM) (SSM de DublinCore)
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Offres de prestations)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DOFFREPRESTA';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1='Prestation';
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle12']='Offre de prestation';
    $ar['options']['ssmodfield12']='tzr_lie';
    $ar['options']['ssmod12']=$moid2;
    $ar['options']['ssmodactivate_additem12']=1;

    // SSM Tarifs/DublinCore
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Tarifs)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DTARIF';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle13']='Tarif';
    $ar['options']['ssmodfield13']='tzr_lie';
    $ar['options']['ssmod13']=$moid2;
    $ar['options']['ssmodactivate_additem13']=1;

    // SSM Descriptions complémentaires/DublinCore
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Descriptions complémentaires)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DDESCRCOMP';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle14']='Description complémentaire';
    $ar['options']['ssmodfield14']='tzr_lie';
    $ar['options']['ssmod14']=$moid2;
    $ar['options']['ssmodactivate_additem14']=1;

    // SSM Itinéraires/DublinCore
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Itinéraires)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DITI';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle15']='Itinéraire';
    $ar['options']['ssmodfield15']='tzr_lie';
    $ar['options']['ssmod15']=$moid2;
    $ar['options']['ssmodactivate_additem15']=1;

    // SSM Plannings/DublinCore
    // Jour planning
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Jours planning)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'JOURPLA';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Presta planning
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Prestations planning)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DPRESTAPLA';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1="Jour";
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    // Plannings
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Plannings)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'DPLANNING';
    $mod2->_module->home=0;
    $mod2->_module->ssmodtitle1="Prestation";
    $mod2->_module->ssmodfield1='tzr_lie';
    $mod2->_module->ssmod1=$moid2;
    $mod2->_module->ssmodactivate_additem1=1;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle16']='Planning';
    $ar['options']['ssmodfield16']='tzr_lie';
    $ar['options']['ssmod16']=$moid2;
    $ar['options']['ssmodactivate_additem16']=1;

    // SSM Prestations liées/DublinCore
    \Seolan\Core\Module\Module::$_mcache=NULL;
    $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
    $mod2->_module->modulename=$this->_module->modulename.' (Prestations liées)';
    $mod2->_module->group=$this->_module->group;
    $mod2->_module->table=$prefixSQL.'PRESTLIEE';
    $mod2->_module->home=0;
    $mod2->_module->trackchanges=0;
    $moid2=$mod2->iend();
    $ar['options']['ssmodtitle17']='Prestation liée';
    $ar['options']['ssmodfield17']='tzr_lie';
    $ar['options']['ssmod17']=$moid2;
    $ar['options']['ssmodactivate_additem17']=1;

    // Modification selon type (ACVS, GITD...)
    if(in_array('ACVS',$this->_module->type)){
      $mod2=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
      $mod2->_module->modulename=$this->_module->modulename.' (Modes de paiement)';
      $mod2->_module->group=$this->_module->group;
      $mod2->_module->table=$prefixSQL.'MODESPAIEMENT';
      $mod2->_module->home=0;
      $mod2->_module->trackchanges=0;
      $moid2=$mod2->iend();
      $ar['options']['ssmodtitle18']='Mode de paiement';
      $ar['options']['ssmodfield18']='tzr_lie';
      $ar['options']['ssmod18']=$moid2;
      $ar['options']['ssmodactivate_additem18']=1;
      $mod->xset->desc['ModePaiement']->delfield();
      $mod->xset->desc['ObservationModePaiement']->delfield();
    }elseif(in_array('GITD',$this->_module->type)){
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$prefixSQL.'MODESPAIEMENT');
      $xset->procDeleteDataSource();
    }else{
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$prefixSQL.'MODESPAIEMENT');
      $xset->procDeleteDataSource();
      $mod->xset->desc['acvs__ClassificationCategorie']->delfield();
      $mod->xset->desc['acvs__ClassificationGroupe']->delfield();
      $mod->xset->desc['acvs__ClassificationSousCategorie']->delfield();
    }

    $mod->procEditProperties($ar);
    return $moid;
  }
}
?>