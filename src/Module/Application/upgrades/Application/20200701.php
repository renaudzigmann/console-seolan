<?php

use Seolan\Library\Upgrades;

function Application_20200701(){
  if (!Seolan\Core\System::tableExists('APP')) {
    return;
  }
  Upgrades::addFields('APP', array(
    [
      'field'        => 'groups',
      'label'        => ['FR' => 'Groupes liés'],
      'ftype'        => '\Seolan\Field\Link\Link',
      'fcount'       => 0,
      'forder'       => 7,
      'compulsory'   => 0,
      'queryable'    => 1,
      'browsable'    => 1,
      'translatable' => 0,
      'multi'        => 1,
      'published'    => 0,
      'target'       => 'GRP',
      'options'      => ['acomment' => [TZR_DEFAULT_LANG => 'Attention : Ces groupes seront supprimés quand l\'application sera supprimée, ou dupliqués quand l\'application sera dupliquée.']],
    ],
  ));
}

function Application_comment_20200701(){
  return "Ajout du champ des groupes liés aux applications";
}
