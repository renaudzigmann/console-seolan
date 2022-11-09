<?php
/**
 * mise à niveau champ ico du menu BO
 */

function BackOfficeInfoTree_20190624() {
  $mod = \Seolan\Core\Module\Module::objectFactory(['toid' => XMODBACKOFFICEINFOTREE_TOID, '_options' => array('local' => true)]);
  $fn = 'icon';
  // ajout du champ icon en shorttext len 64
  if (!$mod->xset->fieldExists($fn)){
    echo("\n<br>ajout du champ icon menu gauche {$mod->_moid} {$mod->table}");
    $mod->xset->createField($fn,'Icon','\Seolan\Field\ShortText\ShortText',64,100,0,0,0,0,0,0,null);
    $icon = $mod->xset->getField($fn);
    $mod->xset->procEditField(['field'=>$fn,'label'=>['FR'=>'Icône', 'GB'=>'Icon']]);
  } else {
    $icon = $mod->xset->getField($fn);
    if (($icon instanceof \Seolan\Field\ShortText\ShortText) && $icon->fcount < 64){
      echo("\n<br>correction du champ icon menu gauche {$mod->_moid} {$mod->table}");
      $mod->xset->procEditField(['field'=>$fn,'fcount'=>64]);
    } else {
      echo("\n<br>champ icon menu gauche {$mod->_moid} {$mod->table} ok");
    }
  } 
}

