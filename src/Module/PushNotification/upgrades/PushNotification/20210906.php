<?php

function PushNotification_20210906() {
  $tables = getDB()->fetchCol('SELECT BTAB FROM BASEBASE WHERE BTAB LIKE \'PUSH_RCPT%\'');
  
  $fields = [
    [
      'field'        => 'device',
      'label'        => array('FR' => 'Périphérique'),
      'ftype'        => '\Seolan\Field\Link\Link',
      'fcount'       => 0,
      'forder'       => 3,
      'compulsory'   => true,
      'queryable'    => true,
      'browsable'    => true,
      'translatable' => false,
      'multi'        => false,
      'published'    => true,
      'target'       => '',
      'options'      => [
        'display_format' => '%_user - %_name',
        'display_text_format' => '%_user - %_name',
      ],
    ],
  ];
  
  foreach ($tables as $table) {
    $fields[0]['target'] = str_replace('PUSH_RCPT', 'DEVICES', $table);
    \Seolan\Library\Upgrades::addFields($table, $fields);
  }
  
  foreach ($tables as $table) {
    getDB()->execute('UPDATE `'.$table.'` a SET device = (SELECT KOID FROM `'.str_replace('PUSH_RCPT', 'DEVICES', $table).'` b WHERE b.`user` = a.`user` AND ifnull(push_token, \'\') <> \'\' ORDER BY last_activity DESC LIMIT 1)');
    \Seolan\Library\Upgrades::deleteFields($table, ['user']);
  }
}

function PushNotification_comment_20210906(){
  return 'Ajout champ lien vers les devices et suppression du lien vers users dans le module des destinataires.';
}