<?php
use Seolan\Library\Upgrades;

function Calendar_20210429(){
  //Récupération des modules Agendas
  $moids = getDB()->fetchCol('SELECT MOID FROM MODULES WHERE TOID=?',[6102]);

  foreach($moids as $moid){
    //Récupération de la table Evenements
    $xmod = \Seolan\Core\Module\Module::objectFactory($moid);
    //Ajout du champ alertMail
    $table = $xmod->tevt;
    $fields = [
      [
        'field' => 'alertMail',
        'label' => 'Alerte par mail',
        'ftype' => '\Seolan\Field\Boolean\Boolean',
        'fcount' => '0',
        'forder' => '17',
        'compulsory' => '0',
        'queryable' => '0',
        'browsable' => '0',
        'translatable' => '0',
        'multi' => '0',
        'published' => '0',
        'target' => '0',
        'options' => [
          'default' => 1
        ],
      ]
    ];
    Upgrades::addFields($table,$fields);
  }
}

function Calendar_comment_20210429(){
  return "Mise à jour des tables événements avec l'ajout du champ alertMail";
}