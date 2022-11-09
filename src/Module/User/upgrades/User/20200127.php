<?php
/// correction des directoryname non renseignés
function User_20200127(){

  echo("Valeur par défaut du champ directory name : set default 'local'\n");
  getDB()->execute('alter table USERS alter directoryname set default "local"');

  $nb = getDB()->fetchOne('select count(*) from USERS where ifnull(directoryname, "")=""');
  if ($nb>0){
    echo("Correction des champs directoryname parfois non renseignés\n");
    echo("\nCorrection de $nb fiches\n");
    getDB()->execute('update USERS set directoryname="local" where ifnull(directoryname, "")=""');
  }

}
