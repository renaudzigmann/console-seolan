<?php
/**
 * Changement des champs de type tableau de sérialisé à json 
 */
use \Seolan\Core\Logs;

function Module_20190701() {
  $majTable_test = getDB()->select("SELECT BOID from BASEBASE");
  foreach($majTable_test as $parametre) {
    $datasource=\Seolan\Core\DataSource\DataSource::objectFactory8($parametre['BOID']);
    foreach($datasource->desc as $fn=>$fd) {
      if($fd instanceof \Seolan\Field\Table\Table ) {
        $table=$fd->table;
        $field=$fd->field;
        $tab_serialiser=getDB()->select("SELECT ".$field." , KOID, LANG from ".$table);
        $tot_field_ligne=$tab_serialiser->rowCount();
        $nb_execute=0;
        $dbh=getDB()->prepare("UPDATE ".$table." SET ".$field." = ?  ,UPD=UPD WHERE ".$field." = ?  AND KOID= ? AND LANG=? ");
        foreach($tab_serialiser as $ligne3) { // un resultat PDOStatement est traversable (implements iterable)
	  if( unserialize($ligne3[$field])) { //si notre fichier sérialiser et que l'unserialization fonctione
            $tab_unseria=unserialize($ligne3[$field]); //on unserialise la ligne du champs table..
            $json_tab=json_encode($tab_unseria); //on encode notre chaine en json
	    
	    $dbh->execute([$json_tab,
			   $ligne3[$field],
			   $ligne3['KOID'],
			   $ligne3['LANG']]);
	    
            $nb_execute+=$dbh->rowCount();
	    
          }
        }
	
	Logs::upgradeLog("Table '$table', champ '$field' : $tot_field_ligne lignes, x$nb_execute mise(s) à jour");

      }
    }
  }
}

