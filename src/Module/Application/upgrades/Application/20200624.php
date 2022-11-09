<?php

use Seolan\Library\Upgrades;

function Application_20200624(){
  if (!Seolan\Core\System::tableExists('APP')) {
    return;
  }
  Upgrades::addFields('APP', array(
    [
      'field'        => 'modules',
      'label'        => ['FR' => 'Modules liés'],
      'ftype'        => '\Seolan\Field\Module\Module',
      'fcount'       => 0,
      'forder'       => 7,
      'compulsory'   => 0,
      'queryable'    => 1,
      'browsable'    => 1,
      'translatable' => 0,
      'multi'        => 1,
      'published'    => 0,
      'target'       => '',
      'options'      => ['acomment' => [TZR_DEFAULT_LANG => 'Attention : Ces modules et leur tables associées seront supprimés quand l\'application sera supprimée, ou dupliqués quand l\'application sera dupliquée.']],
    ],
  ));
}

function Application_comment_20200624(){
  return "Ajout de la liste des modules utilisés par une application";
}
