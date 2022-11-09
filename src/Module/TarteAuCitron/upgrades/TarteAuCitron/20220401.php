<?php
/**
 * Module Tarte au citron : ajout de 3 champs : 
 * - showIcon : qui permet d'affiche un icone cookie en bas à droite
 * - iconPosition : qui permet d'indiquer la position de l'icone cookie
 * - closePopup : qui permet d'indiquer si l'on souhaite afficher automatiquement une croix pour fermer la banière
 */
use \Seolan\Core\Logs;
function TarteAuCitron_20220401(){

  $moid = \Seolan\Core\Module\Module::getMoid(XMODTARTEAUCITRON_TOID);
  if(is_null($moid)) return true;

  try{
    $xmodtarteaucitron = \Seolan\Core\Module\Module::objectFactory($moid);

    $nbFieldsCreated = 0;
    $FORDER = \getDb()->fetchOne('SELECT FORDER FROM DICT WHERE FIELD="showAlertSmall"'); // On le place juste après "showAlertSmall"
    // Création du champ "showIcon"
    $FORDER += 1;
    if(!$xmodtarteaucitron->xset->fieldExists('showIcon')){
      $xmodtarteaucitron->xset->procNewField([
        'field' => 'showIcon',
        'label' => [TZR_DEFAULT_LANG => \Seolan\Core\Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"showIcon","text")],
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'forder'=>$FORDER,
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => '2']
      ]);
      Logs::upgradeLog("TarteAuCitron : champ showIcon créé");
      $xmodtarteaucitron->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"showIcon","text"), 'showIcon', 'boolean', NULL, 2, $xmodtarteaucitron->group);
      $nbFieldsCreated++;
    }else{
      Logs::upgradeLog("TarteAuCitron : champ showIcon déjà présent dans la table");
    }

    // Création du champ "iconPosition"
    $FORDER += 1;
    if(!$xmodtarteaucitron->xset->fieldExists('iconPosition')){
      $xmodtarteaucitron->xset->procNewField([
        'field' => 'iconPosition',
        'label' => ['FR' => \Seolan\Core\Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"iconPosition","text")],
        'ftype' => '\Seolan\Field\ShortText\ShortText',
        'forder'=>$FORDER,
        'fcount'=>50,
        'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0',
        'options' => [
            'default' => 'BottomRight',
            'acomment'=>[
                'FR' => 'BottomRight, BottomLeft, TopRight and TopLeft'
            ]
        ]
      ]);
      Logs::upgradeLog("TarteAuCitron : champ iconPosition créé");
      $xmodtarteaucitron->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"iconPosition","text"), 'iconPosition', 'text', NULL, 'BottomRight', $xmodtarteaucitron->group);
      $nbFieldsCreated++;
    }else{
      Logs::upgradeLog("TarteAuCitron : champ iconPosition déjà présent dans la table");
    }

    // Création du champ "closePopup"
    if(!$xmodtarteaucitron->xset->fieldExists('closePopup')){
        $xmodtarteaucitron->xset->procNewField([
            'field' => 'closePopup',
            'label' => [TZR_DEFAULT_LANG => \Seolan\Core\Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"closePopup","text")],
            'ftype' => '\Seolan\Field\Boolean\Boolean',
            'compulsory' => '0','browsable' => '0','queryable' => '1','published' => '0','options' => ['default' => '2']
        ]);
        Logs::upgradeLog("TarteAuCitron : champ closePopup créé");
        $xmodtarteaucitron->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"closePopup","text"), 'closePopup', 'boolean', NULL, 2, $xmodtarteaucitron->group);
        $nbFieldsCreated++;
    }else{
        Logs::upgradeLog("TarteAuCitron : champ closePopup déjà présent dans la table");
    }

    // On ajoute les champs dans les options du module principal
    if($nbFieldsCreated > 0){
        $ar=[];
        $xmodtarteaucitron->procEditProperties($ar);
    }

  }catch(\Exception $ex){
    Logs::upgradeLog("TarteAuCitron : exception ".$ex->getMessage() . '<br /><pre>'.$ex->getTraceAsString().'</pre>');
  }
}