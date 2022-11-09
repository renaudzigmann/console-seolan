<?php
\Seolan\Core\Labels::$LABELS['Seolan_Model_DataSource_Table_Table']
= array(
'oidstruct'=>'Composition de l\'oid',
'oidstruct1'=>'Champ N°1',
'oidstruct2'=>'Champ N°2',
'oidstruct3'=>'Champ N°3',
'oidstructcomment'=>"Champs permettant de composer l'oid (3 champs maximum, les valeurs sont asciifiées, taille de 40 caractères maximum, format final : TABLE:chp1[-chp2][-chp3]).<br>".
                    "Attention, l'oid devant être unique, les champs séléctionnés doivent assurer une excellent unicité.<br>".
                    "De plus l'oid étant initialisé au moment de l'insertion, lors d'un edit, la modification d'un des champs entrant dans la composition de l'oid ne changera pas ce dernier<br>".
                    "De la même façcon, changer ces propriétés n'affectera pas les objets déjà existant.",
'multifilewarning'=>'Attention : Passer le champ fichier multivalué "%s" en monovalué peut potentiellement '.
                    'provoquer une perte de fichier lors du prochain check journalier de la console, seuls '.
                    'les fichiers les plus récents seront conservés (lancer le check manuellement pour récupérer immédiatement les fichiers)',
'zz'=>''
);
?>