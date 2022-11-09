<?php

use \Seolan\Core\Logs;
function TarteAuCitron_20220426(){
  $moid = \Seolan\Core\Module\Module::getMoid(XMODTARTEAUCITRON_TOID);
  if(is_null($moid)) return true;

  $GA4 = new \Seolan\Module\TarteAuCitron\Services\GoogleAnalytics4();  
  Logs::upgradeLog('TarteAuCitron : ajout du service "'.$GA4->title.'"');

  try{
    $xmodtarteaucitron = \Seolan\Core\Module\Module::objectFactory($moid);
    // Le module des Services
    $ssmodMoid = $xmodtarteaucitron->{"ssmod1"};
    if($ssmodMoid){
        $xmodtarteaucitronservice = \Seolan\Core\Module\Module::objectFactory($ssmodMoid); 
        $xmodtarteaucitronservice->adminBrowseStrings(['tplentry'=>'br', 'field'=>'service']);
        $br = \Seolan\Core\Shell::from_screen('br');
        if(!in_array($GA4->name, $br['tset_oid'])){
          $ar = [
              'field' => 'service',
              'label' => ['FR' => $GA4->title, 'GB' => $GA4->title],
              'sorder' => count($br['tset_oid']),
              'soid' => $GA4->name
          ];
          $xmodtarteaucitronservice->adminProcNewString($ar);
          Logs::upgradeLog('TarteAuCitron : "'.$GA4->title.'" a bien été ajouté dans la liste des services');

          // Réordonner les services par leur nom
          Logs::upgradeLog('TarteAuCitron : Réordonner la liste des services par leur nom');
          $sets = \getDb()->fetchAll('SELECT SOID,STXT FROM `SETS` WHERE STAB=? AND FIELD=? AND SLANG=? ORDER BY STXT ASC', ['TARTEAUCITRON_SERVICES', 'service', 'FR']);
          foreach($sets as $i=> $set){
            Logs::upgradeLog($set['STXT'] . ' : ' . ($i + 1));
            \getDb()->execute('UPDATE `SETS` SET SORDER=? WHERE STAB=? AND FIELD=? AND SOID=?', [($i+1), 'TARTEAUCITRON_SERVICES', 'service', $set['SOID']]);
          }
        }else{
            Logs::upgradeLog('TarteAuCitron : "'.$GA4->title.'" existe déjà dans la liste des services');
        }
    }
  }catch(\Exception $ex){
    Logs::upgradeLog("TarteAuCitron : exception ".$ex->getMessage() . '<br /><pre>'.$ex->getTraceAsString().'</pre>');
  }
}