<?php
function Logs_comment_20200220(){
  return 'Ajout du champ setUID dans la table LOGS';
}
function Logs_20200220(){
  \Seolan\Library\Upgrades::addFields('LOGS', [
    ['field'=>'setuid',
     ['FR'=>'setUID'],
     'ftype'=>'\Seolan\Field\ShortText\ShortText',
     'fcount'=>'64',
     'forder'=>null,
     'compulsory'=>0,
     'queryable'=>1,
     'browsable'=>0,
     'translatable'=>0,
     'multi'=>0,
     'target'=>null,
     'options'=>null]
  ]);
}
