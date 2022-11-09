<?php
/**
 * Module Tarte au citron : ajout de deux champs : 
 * - DenyAllCta : qui permet de désactiver directement tous les cookies
 * - mandatory : qui permet d'indiquer s'il y a des cookies nécessaires au bon fonctionnement du site
 */
use \Seolan\Core\Logs;
function TarteAuCitron_20210323(){

  $moid = \Seolan\Core\Module\Module::getMoid(XMODTARTEAUCITRON_TOID);
  if(is_null($moid)) return true;

  try{
    $tarteAuCitronLabel = \Seolan\Core\Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"modulename","text");

    $xmodtarteaucitron = \Seolan\Core\Module\Module::objectFactory($moid);

    // Etant donné qu'il y a eu une modification du TOID de 8044 à 8045, on met à jour le TOID du module en base de données
    if(!(\is_a($xmodtarteaucitron, '\Seolan\Module\TarteAuCitron\TarteAuCitron') || is_subclass_of($xmodtarteaucitron, 'Seolan\Module\TarteAuCitron\TarteAuCitron'))){
      \getDb()->execute('UPDATE MODULES SET TOID=? WHERE MODULE=?',[XMODTARTEAUCITRON_TOID, $tarteAuCitronLabel]);
      Logs::upgradeLog("TarteAuCitron : changement de TOID de 8044 à ".XMODTARTEAUCITRON_TOID);

      $moid = \Seolan\Core\Module\Module::getMoid(XMODTARTEAUCITRON_TOID);
      if(is_null($moid)) return true;

      $xmodtarteaucitron = \Seolan\Core\Module\Module::objectFactory($moid);
    }
  
    // Création du champ "DenyAllCta"
    if(!$xmodtarteaucitron->xset->fieldExists('DenyAllCta')){
      $FORDER = \getDb()->fetchOne('SELECT FORDER FROM DICT WHERE FIELD="AcceptAllCta"'); // On le place juste après "AcceptAllCta"
      $xmodtarteaucitron->xset->procNewField([
        'field' => 'DenyAllCta',
        'label' => [TZR_DEFAULT_LANG => \Seolan\Core\Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"DenyAllCta","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'forder'=>($FORDER+1),
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => '2']
      ]);
      Logs::upgradeLog("TarteAuCitron : champ DenyAllCta créé");
    }else{
      Logs::upgradeLog("TarteAuCitron : champ DenyAllCta déjà présent dans la table");
    }

    // Création du champ "mandatory"
    if(!$xmodtarteaucitron->xset->fieldExists('mandatory')){
      $xmodtarteaucitron->xset->procNewField([
        'field' => 'mandatory',
        'label' => [TZR_DEFAULT_LANG => \Seolan\Core\Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"mandatory","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => '2']
      ]);
      Logs::upgradeLog("TarteAuCitron : champ mandatory créé");
    }else{
      Logs::upgradeLog("TarteAuCitron : champ mandatory déjà présent dans la table");
    }

    // On ajoute les deux champs dans les options du module principal
    $xmodtarteaucitron->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"DenyAllCta","text"), 'DenyAllCta', 'boolean', NULL, 2, $xmodtarteaucitron->group);
    $xmodtarteaucitron->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"mandatory","text"), 'mandatory', 'boolean', NULL, 2, $xmodtarteaucitron->group);
    $ar=[];
    $xmodtarteaucitron->procEditProperties($ar);

    // Module des Services + Services personnalisés : champ "needConsent" browsable par defaut
    for($i = 1; $i <=2; $i++){
      $ssmodMoid = $xmodtarteaucitron->{"ssmod$i"};
      if($ssmodMoid){
        $xmodtarteaucitronservice = \Seolan\Core\Module\Module::objectFactory($ssmodMoid);
        if($xmodtarteaucitronservice->xset->fieldExists('needConsent')){
          $xmodtarteaucitronservice->xset->procEditField([
            'field' => 'needConsent',
            'browsable' => 'on',
            'browsable_HID' => '1'
          ]);
          Logs::upgradeLog("TarteAuCitron : champ needConsent browsable par défaut sur le module ".$xmodtarteaucitronservice->modulename);
        }
      }
    }
  }catch(\Exception $ex){
    Logs::upgradeLog("TarteAuCitron : exception ".$ex->getMessage() . '<br /><pre>'.$ex->getTraceAsString().'</pre>');
  }
}