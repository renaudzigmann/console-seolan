<?php
use Seolan\Library\Upgrades;

function Shell_20191121(){
  $table = '_IMPEXP';
  $fields = [
    [
      'field'        => 'isdefault',
      'label'        => 'Is default',
      'ftype'        => '\Seolan\Field\Boolean\Boolean',
      'fcount'       => 0,
      'forder'       => 8,
      'compulsory'   => 0,
      'queryable'    => 1,
      'browsable'    => 1,
      'translatable' => 0,
      'multi'        => 0,
      'published'    => 0,
      'target'       => '',
      'options'      => [],
    ]
  ];

  Upgrades::addFields($table, $fields);
}

